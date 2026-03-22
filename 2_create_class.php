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

    // Inserimento Classe in DB
    function generateCode($length = 6) {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }

    do {
        $code = generateCode(6); // genero un codice casuale
        $res = $conn->query("SELECT COUNT(*) as cnt FROM class WHERE code='$code'");
        $row = $res->fetch_assoc();
    } while($row['cnt'] > 0);

    $class_insert = $conn -> query("INSERT INTO class VALUES ('', '$name', '$code')");
    if (!$class_insert){
        die($conn->error);
    }
    $idclass = $conn->insert_id;

    // Inserimento Materie e Schedule in DB
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

    // Inserimento Studenti in DB
    foreach ($students as $student){
        $student_insert = $conn -> query("INSERT INTO student VALUES ('', '$student', $idclass)");
        if (!$student_insert){
            die($conn->error);
        }
    }

    // Output
    echo "La classe $name è stata creata! Accedi ora con il seguente codice: $code";

    // Chiusura connessione DB
    $conn -> close();
?>