<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $code = $_GET["code"];
    $currentStudentId = $_GET["student"] ?? null;

    // Extracting class 
    $result_class = $conn -> query("SELECT idclass, name FROM class WHERE code = '$code'");

    if ($result_class && $result_class->num_rows > 0) {
        $row_class = $result_class->fetch_assoc();
        $id_class = $row_class["idclass"];
        $class_name = $row_class["name"];
    } else {
        echo "Classe non trovata";
        die($conn->error);
    }

    // Extracting student
    $result_student = $conn -> query ("SELECT idstudent, name FROM student WHERE idclass = '$id_class'");
    $students = [];
    
    while ($row_student = $result_student->fetch_assoc()) {
        $students[$row_student["idstudent"]] = $row_student["name"];
    }

    // Extracting subjects (and schedule)
    $result_subject = $conn->query("
        SELECT s.idsubject, s.name, sc.day_of_week
        FROM subject s
        JOIN schedule sc ON s.idsubject = sc.idsubject
        WHERE s.idclass = '$id_class'
    ");
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

    $week_dates = [];
    for($i = 0; $i < 6; $i++){
        $d = clone $start_week;
        $d->modify("+$i days");
        $week_dates[$d->format('Y-m-d')] = [
            'day_name' => $d->format('l'),
            'slots' => [],
            'events' => []
        ];
    }

    // Extracting slots
    $start_date = array_key_first($week_dates);
    $end_date   = array_key_last($week_dates);

    $result_slots = $conn->query("
        SELECT s.date, sub.name AS subject, o.idstudent, st.name AS student_name, s.idslot
        FROM slot s
        JOIN subject sub ON s.idsubject = sub.idsubject
        LEFT JOIN oral o ON s.idslot = o.idslot
        LEFT JOIN student st ON o.idstudent = st.idstudent
        WHERE s.idclass = $id_class
        AND s.date BETWEEN '$start_date' AND '$end_date'
        ORDER BY s.date, sub.name
    ");

    if ($result_slots){
        while($row = $result_slots->fetch_assoc()){
            $date = $row['date'];
            if(!isset($week_dates[$date]['slots'][$row['subject']])){
                $week_dates[$date]['slots'][$row['subject']] = [
                    'idslot' => $row['idslot'],
                    'students' => []
                ];
            }
            if($row['student_name']){
                $week_dates[$date]['slots'][$row['subject']]['students'][] = [
                    'id' => $row['idstudent'],
                    'name' => $row['student_name']
                ];
            }
        }
    } else {
        die($conn->error);
    }

    // Extracting events
    $result_events = $conn->query("
        SELECT name, description, start_date, end_date
        FROM event
        WHERE idclass = $id_class
        AND start_date <= '$end_date'
        AND end_date >= '$start_date'
    ");

    if ($result_events) {
        while($row = $result_events->fetch_assoc()){
            $event_start = new DateTime($row['start_date']);
            $event_end   = new DateTime($row['end_date']);
            foreach($week_dates as $date => $day){
                $d = new DateTime($date);
                if($d >= $event_start && $d <= $event_end){
                    $week_dates[$date]['events'][] = [
                        'name' => $row['name'],
                        'description' => $row['description']
                    ];
                }
            }
        }
    } else {
        die($conn->error);
    }

    // Close DB Connection
    $conn -> close();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Planner</title>
</head>
<body>
    <header>
        <h2><?php echo "$class_name" ?></h2>
        <span id="settings">Impostazioni</span>
    </header><br>
    <aside>
        <div>   <!-- View -->
            <label for="view">Visuale</label>
            <select name="view" id="view">
                <option value="CLASS" <?= $currentStudentId === null ? "selected" : "" ?>>
                    CLASSE
                </option>

                <?php 
                    foreach($students as $id_student => $name){
                        $selected = ($currentStudentId == $id_student) ? "selected" : "";
                        echo "<option value='$id_student' $selected>$name</option>";
                    }
                ?>
            </select>
        </div>

        <div id="div_event">    <!-- Event button -->
            <button id="add_event">+</button>
            <label for="add_event" id="label_button_event">Aggiungi evento</label>
        </div>
    </aside><br>
    <section>
        <div id="div_settings" style="display: none;">      <!-- Settings page -->
            <span id="edit">[+] Modifica</span>
            <form method="post" action="2_class_data.php" id="form_class" style="display: none;">
                <input type="hidden" name="code" value="<?php echo $code?>">    <!-- Create hidden code -->
                <div>
                    <label for="name">Classe</label>
                    <input type="text" name="name" id="name" value="<?php echo "$class_name"?>" required>
                </div><br>

                <div>
                    <table border="1" id="schedule_table">
                        <tr><td>Materie</td><td>Lunedì</td><td>Martedì</td><td>Mercoledì</td><td>Giovedì</td><td>Venerdì</td><td>Sabato</td></tr>
                        <?php 
                            $iSubject = 0;
                            foreach ($subjects as $idsubject => $subject){
                                $iSubject ++;
                                $name = $subject["name"];
                                echo "<tr>";
                                echo "<td><input type='text' name='subjects[subject$iSubject]' value='$name' required></td>";
                                for ($i = 1; $i <= 6; $i++){
                                    $checked = in_array($i, $subject['days']) ? "checked" : "";
                                    echo "<td><input type='checkbox' name='schedule[subject$iSubject][$i]' $checked></td>";
                                }
                                echo "</tr>";
                            }
                        ?>
                    </table>

                    <button type="button" id="add_subject">+ Aggiungi materia</button>
                    <button type="button" id="remove_subject">- Rimuovi materia</button>
                </div>

                <div>
                    <p>Studenti</p>
                    <div id="students_container">
                        <?php 
                            foreach ($students as $id_student => $name){
                                echo "<input type='text' name='students[]' value='$name' required>";
                            }
                        ?>
                    </div>
                    <button type="button" id="add_student">+ Aggiungi studente</button>
                    <button type="button" id="remove_student">- Rimuovi studente</button>
                </div><br>

                <input type="submit" value="Aggiorna">
            </form><br>
            <a href="1_home.html">Logout</a>
        </div>
        <form method="post" action="4_events.php" id="form_events" style="display: none;">    <!-- Events page -->
            <input type="hidden" name="idclass" value="<?php echo $id_class?>">
            <label for="type">Tipologia</label>
            <select name="type" id="type">
                <option value="oral">Interrogazioni</option>
                <option value="other">Altro</option>
            </select>
            
            <div class="oral">
                <label for="subject">Materia</label>
                <select name="subject" id="subject">
                    <?php 
                        foreach($subjects as $idsubject => $subject){
                            $name = $subject["name"];
                            echo "<option value='$idsubject'>$name</option>";
                        }
                    ?>
                </select>
            </div>

            <div class="other">
                <label for="event_name">Nome</label>
                <input type="text" name="event_name" id="event_name" placeholder="Inserire il nome dell'evento" required>

                <label for="description">Descrizione</label>
                <textarea name="description" id="description" placeholder="Inserire una descrizione"></textarea>
            </div>

            <label for="start_date">Data Inizio</label>
            <input type="date" name="start_date" id="start_date" required>

            <label for="end_date">Data Fine</label>
            <input type="date" name="end_date" id="end_date" required>

            <input type="submit" value="Crea">
        </form>
    </section>
    <main id="calendar">
        <div style="margin-bottom:10px;">
            <?php $studentParam = $currentStudentId ? "&student=" . urlencode($currentStudentId) : ""; ?>
            <a href="?week=<?= $week_offset-1 ?>&code=<?= urlencode($code) ?><?= $studentParam ?>"><button>&lt;&lt; Settimana prec</button></a>
            <span style="margin:0 10px;"><strong>Settimana del <?= $start_week->format('d/m/Y')?> - <?= $end_week->format('d/m/Y')?></strong>  </span>
            <a href="?week=<?= $week_offset+1 ?>&code=<?= urlencode($code) ?><?= $studentParam ?>"><button>Settimana succ &gt;&gt;</button></a>
        </div>

        <div style="display:flex; gap:10px; overflow-x:auto;">
        <?php foreach($week_dates as $date => $day): ?>
            <div class="day" style="min-width:150px; border:1px solid #ccc; padding:5px;">
                <h4><?= date('l d/m', strtotime($date)) ?></h4>

                <?php foreach($day['slots'] as $subject => $slot): ?>
                    <div class="slot" style="margin-bottom:5px;">
                        <strong><?= $subject ?></strong>
                        <?php
                        $isInSlot = false;

                        if ($currentStudentId !== null) {
                            foreach ($slot['students'] as $student) {
                                if ($student['id'] == $currentStudentId) {
                                    $isInSlot = true;
                                    break;
                                }
                            }
                        }
                        ?>
                        <button class="slot-toggle" data-slotid="<?= $slot['idslot'] ?>"><?= $isInSlot ? '-' : '+' ?></button>
                        <div class="students">
                            <?php foreach($slot['students'] as $student): ?>
                                [<?= $student['name'] ?>]
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php foreach($day['events'] as $event): ?>
                    <div class="event" style="background:#f0f0f0; padding:2px 5px; margin-top:2px;">
                        <strong><?= $event['name'] ?></strong> - <?= $event['description'] ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </main>
    <script>
        // View set
        const view = document.getElementById("view");
        const div_event = document.getElementById("div_event");
        
        const form_events = document.getElementById("form_events")
        let events_displayed = false;
        let button_event = document.getElementById("add_event");
        let label_button_event = document.getElementById("label_button_event");

        let currentStudentId = (view.value === "CLASS") ? null : view.value;
        div_event.style.display = currentStudentId ? "none" : "block";


        view.addEventListener('change', () => {
            const selectedValue = view.value;
            const url = new URL(window.location);

            if (selectedValue == "CLASS"){
                currentStudentId = null;

                url.searchParams.delete("student");
            } else {
                currentStudentId = selectedValue;

                url.searchParams.set("student", selectedValue);
            }
            window.location.href = url.toString();
        });
            
        function toggleSlotButtons(){
            document.querySelectorAll(".slot-toggle").forEach(btn => {
                btn.style.display = currentStudentId ? "inline-block" : "none";
            });
        }

        toggleSlotButtons();

        document.querySelectorAll(".slot-toggle").forEach(btn => {
            btn.addEventListener("click", () => {
                const slotId = btn.getAttribute("data-slotid");

                fetch("5_oral.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: `idslot=${slotId}&idstudent=${currentStudentId}`
                })
                .then(res => res.text())
                .then(msg => {
                    alert(msg);
                    location.reload(); 
                });
            });
        });

        // Settings toggle
        const name_settings = document.getElementById("settings");
        const container_settings = document.getElementById("div_settings"); 

        let settings_displayed = false;
        let edit_displayed = false;

        function toggleSettings() {
            if(!settings_displayed){
                settings_displayed = true;
                container_settings.style.display = "block";
            } else {
                settings_displayed = false;
                container_settings.style.display = "none";
                if (edit_displayed) toggleEdit();
            }
        }

        name_settings.addEventListener('click', () => {
            toggleSettings();
        })

        // Edit toggle
        const edit = document.getElementById("edit");
        const form_class = document.getElementById('form_class');

        function toggleEdit(){
            if(!edit_displayed){
                edit_displayed = true;
                form_class.style.display = "block";
                edit.textContent = "[-] Modifica";
            } else {
                edit_displayed = false;
                form_class.style.display = "none";
                edit.textContent = "[+] Modifica";
            }
        }

        edit.addEventListener('click', () => {
            toggleEdit();
        })

        // Add Subject
        const table = document.getElementById('schedule_table');
        let subjectNumber = (table.getElementsByTagName("tr").length) - 1; // subject counter 

        document.getElementById('add_subject').addEventListener('click', () => {
            subjectNumber++;

            // Create the row
            const tr = document.createElement('tr');

            // First td
            const tdInput = document.createElement('td');
            const inputSubject = document.createElement('input');
            inputSubject.type = 'text';
            inputSubject.name = `subjects[subject${subjectNumber}]`
            inputSubject.placeholder = 'Inserisci una materia';
            inputSubject.required = true;
            tdInput.appendChild(inputSubject);
            tr.appendChild(tdInput);
            // Last 6 td with checkbox's
            const days = [1, 2, 3, 4, 5, 6];
            days.forEach(day => {
                const tdCheckbox = document.createElement('td');
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.name = `schedule[subject${subjectNumber}][${day}]`; // multidimensional array
                tdCheckbox.appendChild(checkbox);
                tr.appendChild(tdCheckbox);
            });

            // Add the tr to the table
            table.appendChild(tr);
        });

        // Remove Subject
        document.getElementById('remove_subject').addEventListener('click', () => {
            if(table.rows.length > 2){ // keep at least the header and the first row
                table.deleteRow(table.rows.length - 1);
                subjectNumber--;  // remove it when you will be able to remove a specific subject, not just the last one
            }
        });

        // Add Student
        const containerStudents = document.getElementById('students_container');
        document.getElementById('add_student').addEventListener('click', () => {
            const inputStudent = document.createElement('input');
            inputStudent.type = 'text';
            inputStudent.name = 'students[]';
            inputStudent.placeholder = 'Inserisci uno studente';
            inputStudent.required = true;
            containerStudents.appendChild(inputStudent);
        });

        // Remove Student
        document.getElementById('remove_student').addEventListener('click', () => {
            const inputsStudents = containerStudents.getElementsByTagName('input');
            if (inputsStudents.length > 1) {
                containerStudents.removeChild(inputsStudents[inputsStudents.length - 1]);
            }
        });

        // Checkbox check
        form_class.addEventListener('submit', (e) => {
            const rows = table.querySelectorAll('tr:not(:first-child)');
            let error = false;

            rows.forEach(row => {
                const checkbox = row.querySelectorAll('input[type="checkbox"]');
                const atleastOneSelected = Array.from(checkbox).some(cb => cb.checked);

                if (!atleastOneSelected) {
                    error = true;
                }
            });

            if (error) {
                e.preventDefault();
                alert("Ogni materia deve avere almeno un giorno selezionato!");
            }            
        });

        // Event toggle
        function toggleEvents(){
            if(!events_displayed){
                events_displayed = true;
                form_events.style.display = "block";
                button_event.textContent = "-";
                label_button_event.textContent = "Rimuovi evento";
            } else {
                events_displayed = false;
                form_events.style.display = "none";
                button_event.textContent = "+";
                label_button_event.textContent = "Aggiungi evento";
            }
        }

        button_event.addEventListener('click', () => {
            toggleEvents();
        })

        // Event form
        const typeSelect = document.getElementById("type");
        const oralDiv = document.querySelector(".oral");
        const otherDiv = document.querySelector(".other");
        const nameInput = document.getElementById("event_name");

        function toggleEventType() {
            if(typeSelect.value === "oral"){
                oralDiv.style.display = "block";  // show oral
                otherDiv.style.display = "none";  // hide other

                nameInput.required = false;
            } else {
                oralDiv.style.display = "none";   // hide oral
                otherDiv.style.display = "block"; // show other

                nameInput.required = true;
            }
        }

        // If the select changes
        typeSelect.addEventListener("change", toggleEventType);

        // Refresh at starting page
        toggleEventType();
    </script>
</body>
</html>