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
    $isNewClass = !$code;

    if ($code) {        // Edit Class
        $result_idclass = $conn -> query("SELECT idclass FROM class WHERE code ='$code'");
        $row_idclass = $result_idclass -> fetch_assoc();
        $idclass = $row_idclass["idclass"];

        // Remove existing data
        $conn->query("DELETE FROM schedule WHERE idsubject IN (SELECT idsubject FROM subject WHERE idclass=$idclass)");
        $conn->query("DELETE FROM subject WHERE idclass=$idclass");
        $conn->query("DELETE FROM student WHERE idclass=$idclass");

        // Update class name
        $conn->query("UPDATE class SET name='$name' WHERE idclass=$idclass");

        echo "La classe $name è stata aggiornata! <a href='1_home.html'>Accedi nuovamente</a> per vederla";
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
            $res = $conn->query("SELECT COUNT(*) as cnt FROM class WHERE code='$code'");
            $row = $res->fetch_assoc();
        } while($row['cnt'] > 0);

        $class_insert = $conn -> query("INSERT INTO class VALUES ('', '$name', '$code')");
        if (!$class_insert){
            die($conn->error);
        }
        $idclass = $conn->insert_id; 
    }

    // Insert Subjects and Schedule in DB
    foreach ($subjects as $subject_number => $subject_name){
        $subject_insert = $conn -> query("INSERT INTO subject VALUES ('', '$subject_name', $idclass)");
        if (!$subject_insert){
            die($conn->error);
        }
        $idsubject = $conn->insert_id;

        if (isset($schedule[$subject_number])) {
            foreach ($schedule[$subject_number] as $day => $on) {
                $schedule_insert = $conn -> query("INSERT INTO schedule VALUES ('', $day, $idsubject)");
                if (!$schedule_insert){
                    die($conn->error);
                }
            }
        }
    }

    // Insert Students in DB
    foreach ($students as $student){
        $student_insert = $conn -> query("INSERT INTO student VALUES ('', '$student', $idclass)");
        if (!$student_insert){
            die($conn->error);
        }
    }

    if ($isNewClass) {
        echo "<script> alert('La classe $name è stata creata! Il codice della classe è: $code'); window.location.href='3_class_planner.php?code=$code'; </script>";
    }

    // Close DB Connection
    $conn -> close();
?>