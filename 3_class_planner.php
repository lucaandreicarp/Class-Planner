<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $code = $_POST["code"];

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
                <option value="CLASS">CLASSE</option>
                <?php 
                    foreach($students as $id_student => $name){
                        echo "<option value='$id_student'>$name</option>";
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
                <label for="name">Nome</label>
                <input type="text" name="name" id="name" placeholder="Inserire il nome dell'evento" required>

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
    <main></main>

    <script>
        // View set
        const view = document.getElementById("view");
        const div_event = document.getElementById("div_event");
        
        const form_events = document.getElementById("form_events")
        let events_displayed = false;
        let button_event = document.getElementById("add_event");
        let label_button_event = document.getElementById("label_button_event");

        view.addEventListener('change', () => {
            const selected_view = view.options[view.selectedIndex];
            const selected_value = selected_view.value;

            if (selected_value == "CLASS"){
                div_event.style.display = "block";
            } else {
                div_event.style.display = "none";
                form_events.style.display = "none";
                events_displayed = false;
                button_event.textContent = "+";
                label_button_event.textContent = "Aggiungi evento";
            }
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
        const nameInput = document.getElementById("name");

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