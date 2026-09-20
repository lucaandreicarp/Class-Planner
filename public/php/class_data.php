<?php
    require_once dirname(__DIR__, 2) . '/config/database.php';

    $name_form = $_POST["name"];
    $subjects_form = $_POST["subjects"];
    $schedule_form = $_POST["schedule"];
    $students_form = $_POST["students"];

    $code = $_POST['code'] ?? null;
    $isNewClass = !$code;       // Even if code it's null, it won't be because it will be created

    if ($code) {        // Edit Class
        $stmt = $conn->prepare(
            "SELECT idclass FROM class WHERE code=?"
        );

        $stmt->bind_param("s", $code);
        
        if(!$stmt->execute()){
            dbError($stmt->error, "Non è stato possibile caricare la classe.");
        }

        $result_idclass = $stmt->get_result();
        $row_idclass = $result_idclass -> fetch_assoc();
        $idclass = $row_idclass["idclass"];

        // Extracting data from database to compare

        $idsubjects_db = [];
        $idstudents_db = [];
        $schedule_db = []; 
        
        $stmt = $conn->prepare(     // Subjects
            "SELECT idsubject FROM subject WHERE idclass=?"
        );

        $stmt->bind_param("i", $idclass);
        
        if(!$stmt->execute()){
            dbError($stmt->error, "Non è stato possibile caricare le materie della classe.");
        }

        $result_idsubject = $stmt->get_result();

        while ($row_idsubject = $result_idsubject -> fetch_assoc()) {
            $idsubjects_db[] = $row_idsubject["idsubject"];
        }

        $stmt = $conn->prepare(        // Students
            "SELECT idstudent FROM student WHERE idclass=?"
        );

        $stmt->bind_param("i", $idclass);
        
        if(!$stmt->execute()){
            dbError($stmt->error, "Non è stato possibile caricare gli studenti della classe.");
        }

        $result_idstudent = $stmt->get_result();

        while ($row_idstudent = $result_idstudent -> fetch_assoc()) {
            $idstudents_db[] = $row_idstudent["idstudent"];
        }

        $stmt = $conn->prepare(         // Schedule      
            "SELECT 
                sub.idsubject,
                sc.idschedule,
                sc.day_of_week
            FROM subject sub
            JOIN schedule sc ON sub.idsubject = sc.idsubject
            WHERE sub.idclass=?"
        );

        $stmt->bind_param("i", $idclass);
        
        if(!$stmt->execute()){
            dbError($stmt->error, "Non è stato possibile caricare l'orario della classe.");
        }

        $result_schedule = $stmt->get_result();

        while ($row = $result_schedule -> fetch_assoc()) {
            $schedule_db[$row["idsubject"]][$row["day_of_week"]] = $row["idschedule"];
        }

        // Delete missing data
        foreach ($schedule_db as $idsubject_db => $days_db) {      // Schedule
            foreach ($days_db as $day_db => $idschedule_db) {
                if (!isset($schedule_form[$idsubject_db]) || !isset($schedule_form[$idsubject_db][$day_db])) {

                    $stmt = $conn->prepare(
                        "DELETE FROM schedule WHERE idschedule=?"
                    );

                    $stmt->bind_param("i", $idschedule_db);

                    if (!$stmt->execute()) {
                        dbError($stmt->error, "Non è stato possibile eliminare un orario della classe.");
                    }
                }
            }
        }

        foreach ($idsubjects_db as $idsubject_db) {     // Subjects
            if (!isset($subjects_form[$idsubject_db])) {

                $stmt = $conn->prepare(
                    "DELETE FROM subject WHERE idsubject=?"
                );

                $stmt->bind_param("i", $idsubject_db);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile eliminare una materia della classe.");
                }
            }
        }

        foreach ($idstudents_db as $idstudent_db) {     // Students
            if (!isset($students_form[$idstudent_db])) {

                $stmt = $conn->prepare(
                    "DELETE FROM student WHERE idstudent=?"
                );

                $stmt->bind_param("i", $idstudent_db);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile eliminare uno studente della classe.");
                }
            }
        }

        // Update and insert of subjects and schedule
        foreach ($subjects_form as $id => $subject_name) {
            if (is_numeric($id)) {      // Update existing subjects
                if (!in_array((int)$id, array_map('intval', $idsubjects_db), true)) {
                    dbError("Tentativo di modificare una materia non appartenente alla classe.", "Non è possibile modificare una materia che non appartiene alla classe.");
                }

                $stmt = $conn->prepare(
                    "UPDATE subject SET name=? WHERE idsubject=? AND idclass=?"
                );
                $stmt->bind_param("sii", $subject_name, $id, $idclass);
                if ($stmt->execute()) {
                    $idsubject = $id;
                } else {
                    dbError($stmt->error, "Non è stato possibile aggiornare una materia della classe.");
                }
            } else {        // Insert new subjects
                $stmt = $conn->prepare(
                    "INSERT INTO subject (name, idclass) VALUES (?, ?)"
                );
                $stmt->bind_param("si", $subject_name, $idclass);
                if ($stmt->execute()) {
                    $idsubject = $conn->insert_id;
                } else {
                    dbError($stmt->error, "Non è stato possibile inserire una nuova materia nella classe.");
                }
            }

            if (isset($schedule_form[$id])) {   // Insert new schedule
                foreach ($schedule_form[$id] as $day => $on) {
                    if (!isset($schedule_db[$idsubject][$day])) {

                        $stmt = $conn->prepare(
                            "INSERT INTO schedule (day_of_week, idsubject) VALUES (?, ?)"
                        );

                        $stmt->bind_param("ii", $day, $idsubject);

                        if (!$stmt->execute()) {
                            dbError($stmt->error, "Non è stato possibile inserire un nuovo orario nella classe.");
                        }
                    }
                }
            }
        }

        // Update and insert students
        foreach ($students_form as $id => $student_name) {
            if (is_numeric($id)) {      // Update existing students
                $stmt = $conn->prepare(
                    "UPDATE student SET name=? WHERE idstudent=? AND idclass = ?"
                );

                $stmt->bind_param("sii", $student_name, $id, $idclass);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile aggiornare uno studente della classe.");
                }

            } else {        
                $stmt = $conn->prepare(     // Insert new students
                    "INSERT INTO student (name, idclass) VALUES (?, ?)"
                );

                $stmt->bind_param("si", $student_name, $idclass);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile inserire un nuovo studente nella classe.");
                }
            }
        }
        
        // Update class name
        $stmt = $conn->prepare(
            "UPDATE class SET name=? WHERE idclass=?"
        );

        $stmt->bind_param("si", $name_form, $idclass);
        
        if (!$stmt->execute()){
            dbError($stmt->error, "Non è stato possibile aggiornare il nome della classe.");
        }
        
        echo "<script>
        alert(" . json_encode("La classe $name_form è stata aggiornata!") . ");
        window.location.href=" . json_encode("../class_planner.php?code=" . urlencode($code)) . ";
        </script>";
    } else {
        // Insert Class in DB
        function generateCode($length = 6) {
            $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $code;
        }

        do {
            $code = generateCode(6); // generate random code
            $stmt = $conn->prepare(
                "SELECT COUNT(*) AS cnt FROM class WHERE code=?"
            );

            $stmt->bind_param("s", $code);

            if (!$stmt->execute()) {
                dbError($stmt->error, "Non è stato possibile controllare l'esistenza di una classe con il codice generato.");
            }

            $res = $stmt->get_result();
            $row = $res->fetch_assoc();

        } while($row['cnt'] > 0);       // continue if there is already a class with that code

        $stmt = $conn->prepare(
            "INSERT INTO class (name, code) VALUES (?, ?)"
        );

        $stmt->bind_param("ss", $name_form, $code);

        if (!$stmt->execute()) {
            dbError($stmt->error, "Non è stato possibile creare la classe.");
        }

        $idclass = $conn->insert_id; 

        // Insert Subjects and Schedule in DB
        foreach ($subjects_form as $subject_number => $subject_name){
            $stmt = $conn->prepare(
                "INSERT INTO subject (name, idclass) VALUES (?, ?)"
            );

            $stmt->bind_param("si", $subject_name, $idclass);

            if(!$stmt->execute()){
                dbError($stmt->error, "Non è stato possibile creare una materia nella classe.");
            }

            $idsubject = $conn->insert_id;

            if (isset($schedule_form[$subject_number])) {
                foreach ($schedule_form[$subject_number] as $day => $on) {
                    $stmt = $conn->prepare(
                        "INSERT INTO schedule (day_of_week, idsubject) VALUES (?, ?)"
                    );

                    $stmt->bind_param("ii", $day, $idsubject);

                    if (!$stmt->execute()) {
                        dbError($stmt->error, "Non è stato possibile creare un orario per una materia.");
                    }
                }
            }
        }

        // Insert Students in DB
        foreach ($students_form as $student){
            $stmt = $conn->prepare(
                "INSERT INTO student (name, idclass) VALUES (?, ?)"
            );

            $stmt->bind_param("si", $student, $idclass);

            if (!$stmt->execute()) {
                dbError($stmt->error, "Non è stato possibile creare uno studente nella classe.");
            }
        }
    }

    // Output
    if ($isNewClass) {
        echo "<script>
        alert(" . json_encode("La classe $name_form è stata creata! Il codice della classe è: $code") . ");
        window.location.href=" . json_encode("../class_planner.php?code=" . urlencode($code)) . ";
        </script>";
    }

    // Close DB Connection
    $conn -> close();
?>