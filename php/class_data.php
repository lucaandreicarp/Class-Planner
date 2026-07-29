<?php
    require_once "../config/database.php";

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
        $stmt->execute();

        $result_idclass = $stmt->get_result();
        if ($result_idclass) {
            $row_idclass = $result_idclass -> fetch_assoc();

            if(!$row_idclass){
                die("Classe non trovata");
            }

            $idclass = $row_idclass["idclass"];

            // Extracting data from database to compare

            $idsubjects_db = [];
            $idstudents_db = [];
            $schedule_db = []; 
            
            $stmt = $conn->prepare(     // Subjects
                "SELECT idsubject FROM subject WHERE idclass=?"
            );
            $stmt->bind_param("i", $idclass);
            $stmt->execute();

            $result_idsubject = $stmt->get_result();
            if ($result_idsubject) {
                while ($row_idsubject = $result_idsubject -> fetch_assoc()) {
                    $idsubjects_db[] = $row_idsubject["idsubject"];
                }
            } else {
                die($conn->error);
            }

            $stmt = $conn->prepare(        // Students
                "SELECT idstudent FROM student WHERE idclass=?"
            );
            $stmt->bind_param("i", $idclass);
            $stmt->execute();

            $result_idstudent = $stmt->get_result();
            if($result_idstudent){
                while ($row_idstudent = $result_idstudent -> fetch_assoc()) {
                    $idstudents_db[] = $row_idstudent["idstudent"];
                }
            } else {
                die($conn->error);
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
            $stmt->execute();

            $result_schedule = $stmt->get_result();
            if ($result_schedule) {
                while ($row = $result_schedule -> fetch_assoc()) {
                    $schedule_db[$row["idsubject"]][$row["day_of_week"]] = $row["idschedule"];
                }
            } else {
                die($conn->error);
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
                            die($conn->error);
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
                        die($conn->error);
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
                        die($conn->error);
                    }
                }
            }

            // Update and insert of subjects and schedule
            foreach ($subjects_form as $id => $subject_name) {
                if (is_numeric($id)) {      // Update existing subjects
                    $stmt = $conn->prepare(
                        "UPDATE subject SET name=? WHERE idsubject=?"
                    );
                    $stmt->bind_param("si", $subject_name, $id);
                    if ($stmt->execute()) {
                        $idsubject = $id;
                    } else {
                        die($conn->error);
                    }
                } else {        // Insert new subjects
                    $stmt = $conn->prepare(
                        "INSERT INTO subject (name, idclass) VALUES (?, ?)"
                    );
                    $stmt->bind_param("si", $subject_name, $idclass);
                    if ($stmt->execute()) {
                        $idsubject = $conn->insert_id;
                    } else {
                        die($conn->error);
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
                                die($conn->error);
                            }
                        }
                    }
                }
            }

            // Update and insert students
            foreach ($students_form as $id => $student_name) {
                if (is_numeric($id)) {      // Update existing students
                    $stmt = $conn->prepare(
                        "UPDATE student SET name=? WHERE idstudent=?"
                    );

                    $stmt->bind_param("si", $student_name, $id);

                    if (!$stmt->execute()) {
                        die($conn->error);
                    }

                } else {        
                    $stmt = $conn->prepare(     // Insert new students
                        "INSERT INTO student (name, idclass) VALUES (?, ?)"
                    );

                    $stmt->bind_param("si", $student_name, $idclass);

                    if (!$stmt->execute()) {
                        die($conn->error);
                    }
                }
            }
            
            // Update class name
            $stmt = $conn->prepare(
                "UPDATE class SET name=? WHERE idclass=?"
            );

            $stmt->bind_param("si", $name_form, $idclass);
            
            if ($stmt->execute()){
                echo "<script>
                alert(" . json_encode("La classe $name_form è stata aggiornata!") . ");
                window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                </script>";
            } else {
                die($conn->error);
            }
        } else {
            die($conn->error);
        }
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

            $stmt->execute();

            $res = $stmt->get_result();
            if ($res){
                $row = $res->fetch_assoc();
            } else {
                die($conn->error);
            }

        } while($row['cnt'] > 0);       // continue if there is already a class with that code

        $stmt = $conn->prepare(
            "INSERT INTO class (name, code) VALUES (?, ?)"
        );

        $stmt->bind_param("ss", $name_form, $code);

        $class_insert = $stmt->execute();
        if (!$class_insert){
            die($conn->error);
        }
        $idclass = $conn->insert_id; 

        // Insert Subjects and Schedule in DB
        foreach ($subjects_form as $subject_number => $subject_name){
            $stmt = $conn->prepare(
                "INSERT INTO subject (name, idclass) VALUES (?, ?)"
            );

            $stmt->bind_param("si", $subject_name, $idclass);

            $subject_insert = $stmt->execute();
            if (!$subject_insert){
                die($conn->error);
            }
            $idsubject = $conn->insert_id;

            if (isset($schedule_form[$subject_number])) {
                foreach ($schedule_form[$subject_number] as $day => $on) {
                    $stmt = $conn->prepare(
                        "INSERT INTO schedule (day_of_week, idsubject) VALUES (?, ?)"
                    );

                    $stmt->bind_param("ii", $day, $idsubject);

                    $schedule_insert = $stmt->execute();
                    if (!$schedule_insert){
                        die($conn->error);
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

            $student_insert = $stmt->execute();
            if (!$student_insert){
                die($conn->error);
            }
        }
    }

    // Output
    if ($isNewClass) {
        echo "<script>
        alert(" . json_encode("La classe $name_form è stata creata! Il codice della classe è: $code") . ");
        window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
        </script>";
    }

    // Close DB Connection
    $conn -> close();
?>