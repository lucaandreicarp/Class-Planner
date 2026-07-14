<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $name = $_POST["name"];
    $subjects = $_POST["subjects"];
    $schedule = $_POST["schedule"];
    $students = $_POST["students"];

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

            // Remove existing data
            $stmt = $conn->prepare(
                "DELETE FROM schedule
                WHERE idsubject IN (
                    SELECT idsubject
                    FROM subject
                    WHERE idclass=?
                )"
            );

            $stmt->bind_param("i", $idclass);

            $delete_schedule = $stmt->execute();
            if (!$delete_schedule){
                die($conn->error);
            }

            $stmt = $conn->prepare(
                "DELETE FROM subject WHERE idclass=?"
            );

            $stmt->bind_param("i", $idclass);

            $delete_subject = $stmt->execute();
            if (!$delete_subject){
                die($conn->error);
            }

            $stmt = $conn->prepare(
                "DELETE FROM student WHERE idclass=?"
            );

            $stmt->bind_param("i", $idclass);

            $delete_student = $stmt->execute();
            if (!$delete_student){
                die($conn->error);
            }

            // Update class name
            $stmt = $conn->prepare(
                "UPDATE class SET name=? WHERE idclass=?"
            );
            $stmt->bind_param("si", $name, $idclass);
            
            $update_class = $stmt->execute();
            
            if ($update_class){
                echo "<script>
                alert(" . json_encode("La classe $name è stata aggiornata!") . ");
                window.location.href=" . json_encode("3_class_planner.php?code=" . urlencode($code)) . ";
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

        $stmt->bind_param("ss", $name, $code);

        $class_insert = $stmt->execute();
        if (!$class_insert){
            die($conn->error);
        }
        $idclass = $conn->insert_id; 
    }

    // Insert Subjects and Schedule in DB
    foreach ($subjects as $subject_number => $subject_name){
        $stmt = $conn->prepare(
            "INSERT INTO subject (name, idclass) VALUES (?, ?)"
        );

        $stmt->bind_param("si", $subject_name, $idclass);

        $subject_insert = $stmt->execute();
        if (!$subject_insert){
            die($conn->error);
        }
        $idsubject = $conn->insert_id;

        if (isset($schedule[$subject_number])) {
            foreach ($schedule[$subject_number] as $day => $on) {
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
    foreach ($students as $student){
        $stmt = $conn->prepare(
            "INSERT INTO student (name, idclass) VALUES (?, ?)"
        );

        $stmt->bind_param("si", $student, $idclass);

        $student_insert = $stmt->execute();
        if (!$student_insert){
            die($conn->error);
        }
    }

    // Output
    if ($isNewClass) {
        echo "<script>
        alert(" . json_encode("La classe $name è stata creata! Il codice della classe è: $code") . ");
        window.location.href=" . json_encode("3_class_planner.php?code=" . urlencode($code)) . ";
        </script>";
    }

    // Close DB Connection
    $conn -> close();
?>