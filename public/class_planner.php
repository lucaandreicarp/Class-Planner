<?php
    require_once "../config/database.php";

    $code = $_GET["code"];
    $currentStudentId = $_GET["student"] ?? null;   // If student's or class' view

    // Extracting class 
    $stmt = $conn->prepare(
        "SELECT idclass, name FROM class WHERE code=?"
    );

    $stmt->bind_param("s", $code);

    if(!$stmt->execute()){
        dbError($stmt->error, "Non è stato possibile cercare la classe.");
    }

    $result_class = $stmt->get_result();

    if ($result_class->num_rows > 0) {
        $row_class = $result_class->fetch_assoc();
        $id_class = $row_class["idclass"];
        $class_name = $row_class["name"];
    } else {
        dbError("", "Codice classe non trovato!");
    }

    // Extracting student
    $stmt = $conn->prepare(
        "SELECT idstudent, name FROM student WHERE idclass=?"
    );

    $stmt->bind_param("i", $id_class);

    if(!$stmt->execute()){
        dbError($stmt->error, "Non è stato possibile caricare gli studenti della classe.");
    }

    $result_student = $stmt->get_result();
    $students = [];

    while ($row_student = $result_student->fetch_assoc()) {
        $students[$row_student["idstudent"]] = $row_student["name"];
    }

    // Extracting subjects (and schedule)
    $stmt = $conn->prepare("
        SELECT s.idsubject, s.name, sc.day_of_week
        FROM subject s
        JOIN schedule sc ON s.idsubject = sc.idsubject
        WHERE s.idclass = ?
    ");

    $stmt->bind_param("i", $id_class);
    
    if(!$stmt->execute()){
        dbError($stmt->error, "Non è stato possibile caricare le materie e l'orario della classe.");
    }

    $result_subject = $stmt->get_result();
    $subjects = [];

    while ($row_subject = $result_subject->fetch_assoc()) {
        $id_subject = $row_subject["idsubject"];

        // If the subject doesn't exist yet, create it
        if (!isset($subjects[$id_subject])) {
            $subjects[$id_subject] = [
                "name" => $row_subject["name"],
                "days" => []
            ];
        }

        // Add the day
        $subjects[$id_subject]["days"][] = $row_subject["day_of_week"];
    }

    // Calculate current week
    $week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;

    $start_week = new DateTime();
    $start_week->modify("monday this week");
    if($week_offset != 0){
        $start_week->modify("$week_offset week");
    }

    $end_week = clone $start_week;
    $end_week->modify('+6 days');

    // Translation to italian names
    $days_italian = [
        'Monday' => 'Lunedì',
        'Tuesday' => 'Martedì',
        'Wednesday' => 'Mercoledì',
        'Thursday' => 'Giovedì',
        'Friday' => 'Venerdì',
        'Saturday' => 'Sabato',
        'Sunday' => 'Domenica'
    ];

    $months_italian = [
        'January' => 'Gen',
        'February' => 'Feb',
        'March' => 'Mar',
        'April' => 'Apr',
        'May' => 'Mag',
        'June' => 'Giu',
        'July' => 'Lug',
        'August' => 'Ago',
        'September' => 'Set',
        'October' => 'Ott',
        'November' => 'Nov',
        'December' => 'Dic'
    ];

    // Week structure
    $week_dates = [];
    for($i = 0; $i < 7; $i++){
        $d = clone $start_week;
        $d->modify("+$i days");
        $week_dates[$d->format('Y-m-d')] = [
            'day_name' => $days_italian[$d->format('l')],   // Extracting week's day
            'slots' => [],
            'events' => [],
            'has_oral' => false
        ];
    }

    // Extracting slots
    $start_date = array_key_first($week_dates);
    $end_date   = array_key_last($week_dates);

    $stmt = $conn->prepare("
        SELECT s.date, sub.name AS subject, o.idstudent, st.name AS student_name, s.idslot
        FROM slot s
        JOIN subject sub ON s.idsubject = sub.idsubject
        LEFT JOIN oral o ON s.idslot = o.idslot
        LEFT JOIN student st ON o.idstudent = st.idstudent
        WHERE s.idclass = ?
        AND s.date BETWEEN ? AND ?
        ORDER BY s.date, sub.name
    ");

    $stmt->bind_param("iss", $id_class, $start_date, $end_date);
    
    if(!$stmt->execute()){
        dbError($stmt->error, "Non è stato possibile caricare le interrogazioni della settimana.");
    }

    $result_slots = $stmt->get_result();

    while($row = $result_slots->fetch_assoc()){
        $date = $row['date'];
        if(!isset($week_dates[$date]['slots'][$row['subject']])){
            $week_dates[$date]['slots'][$row['subject']] = [
                'idslot' => $row['idslot'],     // Extracting id slot
                'students' => []
            ];
        }
        if($row['student_name']){       // Extracting student
            $week_dates[$date]['slots'][$row['subject']]['students'][] = [
                'id' => $row['idstudent'],
                'name' => $row['student_name']
            ];
        }
        if ($currentStudentId !== null && $row['idstudent'] == $currentStudentId) {
            $week_dates[$date]['has_oral'] = true;
        }
    }

    // Extracting events
    $stmt = $conn->prepare("
        SELECT idevent, name, description, start_date, end_date
        FROM event
        WHERE idclass = ?
        AND start_date <= ?
        AND end_date >= ?
    ");

    $stmt->bind_param("iss", $id_class, $end_date, $start_date);
    
    if(!$stmt->execute()){
        dbError($stmt->error, "Non è stato possibile caricare gli eventi della settimana.");
    }

    $result_events = $stmt->get_result();

    while($row = $result_events->fetch_assoc()){
        $event_start = new DateTime($row['start_date']);
        $event_end   = new DateTime($row['end_date']);
        foreach($week_dates as $date => $day){
            $d = new DateTime($date);
            if($d >= $event_start && $d <= $event_end){
                $week_dates[$date]['events'][] = [
                    'idevent' => $row['idevent'],
                    'name' => $row['name'],
                    'description' => $row['description'],
                ];
            }
        }
    }

    // Close DB Connection
    $conn -> close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8'); ?> | Class Planner</title>
    <link rel="stylesheet" href="css/common.css">
    <link rel="stylesheet" href="css/class_planner.css">
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body data-idclass="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
    <header>
        <h1><?php echo htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8'); ?></h1>
        <span id="settings">
            <i data-lucide="settings"></i>
        </span>
    </header>
    <div id="app-layout">
        <aside>
            <div id="div_view">   <!-- View -->
                <label for="view" class="hidden_mobile">Visuale</label>
                <select name="view" id="view">
                    <option value="CLASS" <?= $currentStudentId === null ? "selected" : "" ?>>
                        CLASSE
                    </option>

                    <?php 
                        foreach($students as $id_student => $name){
                            $selected = ($currentStudentId == $id_student) ? "selected" : "";
                            echo "<option value='" . htmlspecialchars($id_student, ENT_QUOTES, 'UTF-8') . "' $selected>" 
                                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') 
                                . "</option>";
                        }
                    ?>
                </select>
            </div>

            <div id="div_event">    <!-- Event button -->
                <button id="add_event"><i data-lucide="plus"></i></button>
                <label for="add_event" id="label_button_event" class="hidden_mobile">Aggiungi evento</label>
            </div>
        </aside>
        <main id="calendar">        <!-- Calendar page -->
            <div id="calendar-content">     <!-- Wrapper -->
                <div id="header-content">  <!-- Calendar header -->
                    <?php $studentParam = $currentStudentId ? "&student=" . urlencode($currentStudentId) : ""; ?>

                    <!-- Year -->
                    <span id="year">
                        <?= $end_week->format('Y'); ?>
                    </span>
                    
                    <div id="calendar-header">
                        <!-- Previous week -->
                        <a href="<?= htmlspecialchars("?week=" . ($week_offset - 1) . "&code=" . urlencode($code) . $studentParam, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="arrow-icon"><i data-lucide="chevron-left"></i></button>
                        </a>

                        <!-- Date range -->
                        <span id="date-range">
                            <span>
                                <?= $start_week->format('d') ?>
                                <?= $months_italian[$start_week->format('F')] ?>
                            </span>
                            <span>-</span>
                            <span>
                                <?= $end_week->format('d') ?>
                                <?= $months_italian[$end_week->format('F')] ?>
                            </span>
                        </span>

                        <!-- Next week -->
                        <a href="<?= htmlspecialchars("?week=" . ($week_offset + 1) . "&code=" . urlencode($code) . $studentParam, ENT_QUOTES, 'UTF-8') ?>">
                            <button class="arrow-icon"><i data-lucide="chevron-right"></i></button>
                        </a>
                    </div>
                </div>

                <div id="week-container">
                <?php foreach($week_dates as $date => $day): ?>
                    <?php 
                    
                        $todayDate = new DateTime();
                        $today_string = $todayDate->format('Y-m-d');

                        $isToday = ($date === $today_string);
                        $today = "";

                        $hasOral = ""; 

                        if ($day['has_oral']) {
                            $hasOral = 'has-oral';
                        } elseif ($isToday && $currentStudentId === null) {
                            $today = 'today';
                        }

                    ?>
                    <div class="day <?= $today ?> <?= $hasOral ?>">
                        <h4><?= $day['day_name'] . " " . date('d', strtotime($date)) ?></h4>      <!-- Day name and date -->

                        <?php foreach($day['slots'] as $subject => $slot): ?>       <!-- Slot -->
                            <div class="slot">
                                <div class="header-event">
                                    <strong><?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?></strong>
                                    <?php       // To determine button's text
                                    $isInSlot = false;

                                    if ($currentStudentId !== null) {
                                        foreach ($slot['students'] as $student) {
                                            if ($student['id'] == $currentStudentId) {      // If student's already in slot
                                                $isInSlot = true;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <button class="<?= $isInSlot ? 'remove-oral' : 'add-oral' ?>" data-slotid="<?= htmlspecialchars($slot['idslot'], ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="<?= $isInSlot ? 'minus' : 'plus' ?>"></i></button>
                                    <button class="slot-elimination" data-slotid="<?= $slot['idslot'] ?>"><i data-lucide="x"></i></button>
                                </div>
                    
                                <?php if (count($slot['students']) > 0): ?>
                                    <ul class="students">
                                    <?php foreach($slot['students'] as $student): ?>
                                        <li><?= htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8') ?></li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($day['events'] as $event): ?>     <!-- Event -->
                            <div class="event">
                                <div class="header-event">
                                    <strong><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <button class="event-elimination" data-eventid="<?= htmlspecialchars($event['idevent'], ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="x"></i></button>
                                </div> 
                                <?php if (!empty($event['description'])): ?>
                                    <p><?= htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>
    <section id="overlay">
        <div id="div_settings" class="hidden">      <!-- Settings page -->
            <div class="modal-header">
                <h2>Impostazioni</h2>
                <button class="close-modal"><i data-lucide="x"></i></button>
            </div>
            <p>Codice classe: <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>    
            <button id="edit">Modifica</button>
            <form method="post" action="../php/delete_class.php" id="form_cancellation" onsubmit="return confirm('Sei sicuro di voler eliminare questa classe? Questa operazione è irreversibile.');">
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="submit" value="Cancella classe">
            </form>            
            <a href="index.html" id="logout">
                <i data-lucide="log-out" id="logout-icon"></i>
                Logout
            </a>
        </div>
        <div id="edit-setting" class="hidden">
            <div class="modal-header">
                <h2>Modifica</h2>
                <button class="close-modal"><i data-lucide="x"></i></button>
            </div>
            <form method="post" action="../php/class_data.php" id="form_class">
                <input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">    <!-- Create hidden code -->
                <div>
                    <label for="name">Classe</label>
                    <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div>
                    <table id="schedule_table">
                        <tr><td>Materie</td><td>Lun</td><td>Mar</td><td>Mer</td><td>Gio</td><td>Ven</td><td>Sab</td></tr>
                        <?php 
                            foreach ($subjects as $idsubject => $subject){
                                $name = $subject["name"];
                                echo "<tr>";
                                echo "<td><input type='text' name='subjects[$idsubject]' value='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' required></td>";
                                for ($i = 1; $i <= 6; $i++){
                                    $checked = in_array($i, $subject['days']) ? "checked" : "";
                                    echo "<td><input type='checkbox' name='schedule[$idsubject][$i]' $checked></td>";
                                }
                                echo "</tr>";
                            }
                        ?>
                    </table>
                    
                    <div class="form_buttons">
                        <button type="button" id="add_subject">
                            <i data-lucide="plus"></i>
                            <span>Aggiungi materia</span>
                        </button>

                        <button type="button" id="remove_subject">
                            <i data-lucide="trash-2"></i>
                            <span>Rimuovi materia</span>
                        </button>
                    </div>
                </div>

                <div>
                    <b id="students_label"></b>
                    <div id="students_container">
                        <?php 
                            foreach ($students as $id_student => $name){
                                echo "<input type='text' name='students[$id_student]' value='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' required>";
                            }
                        ?>
                    </div>

                    <div class="form_buttons">
                        <button type="button" id="add_student">
                            <i data-lucide="plus"></i>
                            <span>Aggiungi studente</span>
                        </button>
                        <button type="button" id="remove_student">
                            <i data-lucide="trash-2"></i>
                            <span>Rimuovi studente</span>
                        </button>
                    </div>
                </div>

                <div id="submit_container">
                    <input type="submit" value="Aggiorna" class="submit_button">
                </div>
            </form>
        </div>
        <form method="post" action="../php/events.php" id="form_events" class="hidden">    <!-- Events page -->
            <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">    
            <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">    

            <div class="modal-header">
                <h2>Aggiungi evento</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>
            
            <div>
                <label for="type">Tipologia</label>
                <select name="type" id="type">
                    <option value="oral">Interrogazioni</option>
                    <option value="other">Altro</option>
                </select>
            </div>

            <div class="oral">
                <label for="subject">Materia</label>
                <select name="subject" id="subject">
                    <?php 
                        foreach($subjects as $idsubject => $subject){
                            $name = $subject["name"];
                            echo "<option value='" . htmlspecialchars($idsubject, ENT_QUOTES, 'UTF-8') . "'>" 
                                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') 
                                . "</option>";
                        }
                    ?>
                </select>
            </div>

            <div class="other">
                <div>
                    <label for="event_name">Nome</label>
                    <input type="text" name="event_name" id="event_name" placeholder="Inserire il nome dell'evento" required>
                </div>

                <div>
                    <label for="description">Descrizione</label>
                    <textarea name="description" id="description" placeholder="Inserire una descrizione"></textarea>
                </div>
            </div>

            <div>
                <label for="start_date">Data Inizio</label>
                <input type="date" name="start_date" id="start_date" required>
            </div>

            <div>
                <label for="end_date">Data Fine</label>
                <input type="date" name="end_date" id="end_date" required>
            </div>

            <div id="submit_container">
                <input type="submit" value="Continua" class="submit_button">
            </div>
        </form>
        <form method="post" action="../php/events.php" id="form_slots" class="hidden">
            <input type="hidden" name="code" value="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>">  
            <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="type" value="oral">
            <input type="hidden" name="subject" id="slots_subject">
            
            <div class="modal-header">
                <h2>Crea interrogazioni</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>

            <div id="slots_container"></div>

            <div class="form_buttons">
                <button type="button" id="add_slot">
                    <i data-lucide="plus"></i>
                    <span>Aggiungi slot</span>
                </button>
                <button type="button" id="remove_slot">
                    <i data-lucide="trash-2"></i>
                    <span>Rimuovi slot</span>
                </button>
            </div>
            
            <div id="automatic_assignment_container">
                <input type="checkbox" name="automatic_assignment" id="automatic_assignment">
                <label for="automatic_assignment">Assegnazione automatica</label>
            </div>

            <div id="submit_container">
                <input type="submit" value="Conferma" class="submit_button">
            </div>
        </form>
    </section>

    <script>
        const subjects = <?= json_encode($subjects, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="js/class_planner.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>