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
        SELECT s.date, sub.name AS subject, o.idstudent, st.name AS student_name, s.idslot, s.capacity
        FROM slot s
        JOIN subject sub ON s.idsubject = sub.idsubject
        LEFT JOIN oral o ON s.idslot = o.idslot
        LEFT JOIN student st ON o.idstudent = st.idstudent
        WHERE s.idclass = ?
        AND s.date BETWEEN ? AND ?
        ORDER BY s.date, s.idslot
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
                'capacity' => $row['capacity'],
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
                    'start_date' => $row['start_date'],
                    'end_date' => $row['end_date']
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
                                    <?php       
                                        $isInSlot = false;      // If visual student is in slot

                                        if ($currentStudentId !== null) {
                                            foreach ($slot['students'] as $student) {
                                                if ($student['id'] == $currentStudentId) {      // If student's already in slot
                                                    $isInSlot = true;
                                                    break;
                                                }
                                            }
                                        }

                                        $isSlotFull = ($slot['capacity'] <= count($slot['students']));     // If slot is full
                                        
                                        // To determine button's text
                                        if ($isInSlot) {
                                            $buttonClass = 'remove-oral';
                                            $icon = 'minus';
                                        } elseif ($isSlotFull) {
                                            $buttonClass = 'hidden';    // hide the button
                                            $icon = 'plus';
                                        } else {
                                            $buttonClass = 'add-oral';
                                            $icon = 'plus';
                                        }
                                    ?>

                                    <button class="<?= $buttonClass ?>" data-slotid="<?= htmlspecialchars($slot['idslot'], ENT_QUOTES, 'UTF-8') ?>"><i data-lucide="<?= $icon ?>"></i></button>
                                    <button
                                        class="slot-modify"
                                        data-slotid="<?= htmlspecialchars($slot['idslot'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-date="<?= htmlspecialchars($date, ENT_QUOTES, 'UTF-8') ?>"
                                        data-subject="<?= htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') ?>"
                                        data-capacity="<?= htmlspecialchars($slot['capacity'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-students="<?= count($slot['students']) ?>"
                                    >
                                        <i data-lucide="pencil"></i>
                                    </button>
                                </div>
                            
                                <table class="students">
                                    <?php for ($i = 0; $i < $slot['capacity']; $i++): ?>
                                        <tr>
                                            <td>
                                                <?php 
                                                    if (array_key_exists($i, $slot['students'])){
                                                        echo htmlspecialchars($slot['students'][$i]['name'], ENT_QUOTES, 'UTF-8');
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                </table>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach($day['events'] as $event): ?>     <!-- Event -->
                            <div class="event">
                                <div class="header-event">
                                    <strong><?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                    <button class="event-modify" 
                                        data-eventid="<?= htmlspecialchars($event['idevent'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-name="<?= htmlspecialchars($event['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-description="<?= htmlspecialchars($event['description'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-start_date="<?= htmlspecialchars($event['start_date'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-end_date="<?= htmlspecialchars($event['end_date'], ENT_QUOTES, 'UTF-8') ?>"
                                    >
                                        <i data-lucide="pencil"></i>
                                    </button>
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
            <div class="modal_content">
                <p>Codice classe: <?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?></p>    
                <button id="edit">Modifica classe</button>
                <form method="post" action="../php/delete_class.php" id="form_cancellation" onsubmit="return confirm('Sei sicuro di voler eliminare questa classe? Questa operazione è irreversibile.');">
                    <input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="submit" value="Elimina classe" class="delete">
                </form>            
                <a href="index.html" id="logout">
                    <i data-lucide="log-out" id="logout-icon"></i>
                    Logout
                </a>
            </div>
        </div>
        <form method="post" action="../php/class_data.php" id="form_class" class="hidden">
            <input type="hidden" name="code" value="<?php echo htmlspecialchars($code, ENT_QUOTES, 'UTF-8'); ?>">    <!-- Create hidden code -->
        
            <div class="modal-header">
                <h2>Modifica classe</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>

            <div>
                <label for="name">Classe</label>
                <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($class_name, ENT_QUOTES, 'UTF-8'); ?>" required placeholder="Nome">
            </div>

            <div>
                <table id="schedule_table">
                    <tr><td>Materie</td><td>Lun</td><td>Mar</td><td>Mer</td><td>Gio</td><td>Ven</td><td>Sab</td></tr>
                    <?php 
                        foreach ($subjects as $idsubject => $subject){
                            $name = $subject["name"];
                            echo "<tr>";
                            echo "<td>
                                    <div>
                                        <input type='text' name='subjects[$idsubject]' value='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' required placeholder='Materia'>
                                        <button type='button' class='remove' onclick='removeSubject(this)'><i data-lucide='trash-2'></i></button>
                                    </div>
                                </td>";
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
                </div>
            </div>

            <div>
                <div id="students_label"></div>
                <div id="students_container">
                    <?php 
                        foreach ($students as $id_student => $name){
                            echo "<div>
                                    <input type='text' name='students[$id_student]' value='" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' required placeholder='Nome dello studente'>
                                    <button type='button' class='remove' onclick='removeStudent(this)'><i data-lucide='trash-2'></i></button>
                                </div>";
                        }
                    ?>
                </div>

                <div class="form_buttons">
                    <button type="button" id="add_student">
                        <i data-lucide="plus"></i>
                        <span>Aggiungi studente</span>
                    </button>
                </div>
            </div>

            <div class="submit_container">
                <input type="submit" value="Aggiorna">
            </div>
        </form>
       <form method="post" action="../php/events.php" id="form_events" class="hidden">    <!-- Events page -->
            <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">    

            <div class="modal-header">
                <h2>Aggiungi evento</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>

            <div class="modal_content">
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
                        <input type="text" name="event_name" id="event_name" placeholder="Nome dell'evento" required>
                    </div>

                    <div>
                        <label for="description">Descrizione</label>
                        <textarea name="description" id="description" placeholder="Descrizione dell'evento"></textarea>
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
            </div>

            <div class="submit_container">
                <input type="submit" value="Continua">
            </div>
        </form>
        <form method="post" action="../php/events.php" id="form_slots" class="hidden">
            <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="type" value="oral">
            <input type="hidden" name="subject" id="slots_subject">
            
            <div class="modal-header">
                <h2 id="slots_title"></h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>

            <div>
                <table id="slots_container"></table>

                <div id="slots_summary"></div>

                <div class="form_buttons">
                    <button type="button" id="add_slot">
                        <i data-lucide="plus"></i>
                        <span>Aggiungi data</span>
                    </button>
                </div>
            </div>
            
            <div id="automatic_assignment_container">
                <div class="automatic_assignment_header">
                    <input type="checkbox" name="automatic_assignment" id="automatic_assignment" disabled>
                    <label for="automatic_assignment">Assegnazione automatica studenti</label>
                    
                    <button type="button" id="automatic_assignment_info"class="info_button"><i data-lucide="circle-help"></i></button>
                </div>

                <div id="automatic_assignment_panel" class="info_panel">
                    Cerca di distribuire in modo equilibrato le interrogazioni tra gli studenti della classe,
                    considerando le interrogazioni già svolte e quelle future già programmate.
                </div>
            </div>

            <div class="submit_container">
                <input type="submit" value="Conferma">
            </div>
        </form>
        <div id="modal_manage_slot" class="hidden">
            <div class="modal-header">
                <h2>Gestione interrogazione</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>
            <div id="manage_slot_content">
                <form method="post" action="../php/events.php" id="form_edit_slot">
                    <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" value="oral">                
                    <input type="hidden" name="idslot" id="edit_slot_id">
                    
                    <div>
                        <label>Giorno</label>
                        <input type="date" id="edit_date" disabled>
                    </div>

                    <div>
                        <label>Materia</label>
                        <input type="text" id="edit_subject" disabled>
                    </div>

                    <div>
                        <label for="edit_capacity">Posti</label>
                        <input type="number" name="capacity" min="1" id="edit_capacity" required placeholder="Numero di posti">
                    </div>
                </form>
                <form method="post" action="../php/events.php" id="form_delete_slot">
                    <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" value="oral">                
                    <input type="hidden" name="idslot" id="remove_slot_id">
                </form>
            </div>
            <div class="modal_actions">
                <input type="submit" form="form_delete_slot" name="action" value="Elimina interrogazione" class="delete">
                <input type="submit" form="form_edit_slot" name="action" value="Modifica">
            </div>
        </div>
        <div id="modal_manage_event" class="hidden">
            <div class="modal-header">
                <h2>Gestione evento</h2>
                <button type="button" class="close-modal"><i data-lucide="x"></i></button>
            </div>
            <div id="manage_event_content">
                <form method="post" action="../php/events.php" id="form_edit_event">
                    <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" value="other">
                    <input type="hidden" name="idevent" id="edit_event_id">

                    <div>
                        <label for="edit_name">Nome</label>
                        <input type="text" name="event_name" id="edit_name" placeholder="Nome dell'evento" required>
                    </div>
                    
                    <div>
                        <label for="edit_description">Descrizione</label>
                        <textarea name="description" id="edit_description" placeholder="Descrizione dell'evento"></textarea>
                    </div>

                    <div>
                        <label for="edit_start_date">Data inizio</label>
                        <input type="date" name="start_date" id="edit_start_date" required>
                    </div>

                    <div>
                        <label for="edit_end_date">Data fine</label>
                        <input type="date" name="end_date" id="edit_end_date" required>
                    </div>
                </form>
                <form method="post" action="../php/events.php" id="form_remove_event">
                    <input type="hidden" name="idclass" value="<?= htmlspecialchars($id_class, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="type" value="other">
                    <input type="hidden" name="idevent" id="remove_event_id">
                </form>
            </div>
            <div class="modal_actions">
                <input type="submit" form="form_remove_event" name="action" value="Elimina evento" class="delete">
                <input type="submit" form="form_edit_event" name="action" value="Modifica">
            </div>
        </div>
    </section>

    <script>
        const subjects = <?= json_encode($subjects, JSON_UNESCAPED_UNICODE) ?>;
    </script>

    <script src="js/class_planner.js"></script>
    <script>lucide.createIcons();</script>
</body>
</html>