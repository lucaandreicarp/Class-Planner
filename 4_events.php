<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_class = $_POST["idclass"];
    $type = $_POST["type"];
    $id_subject = $_POST["subject"];
    $name = $_POST["event_name"];
    $description = $_POST["description"];
    $start_date = $_POST["start_date"];
    $end_date = $_POST["end_date"];

    if ($type == "oral"){       // Event: oral
        $start_date = new DateTime($start_date);
        $end_date = new DateTime($end_date);    
        
        $subject_days = [];

        $schedule = $conn -> query("SELECT day_of_week FROM schedule WHERE idsubject = $id_subject");
        if ($schedule){
            while($row = $schedule -> fetch_object()){
                $subject_days[] = $row -> day_of_week;
            }
        } else {
           die($conn->error); 
        }

        $dates = [];

        for($date = clone $start_date; $date <= $end_date; $date->modify('+1 day')){
            $number_day = $date -> format("N");
            if (in_array($number_day, $subject_days)){
                $dates[] = $date->format("Y-m-d");
            }
        }

        foreach ($dates as $date){
            $slot_insert = $conn -> query("INSERT INTO slot VALUES ('', '$date', $id_class, $id_subject)");
            if (!$slot_insert){
                die($conn->error);                
            }
        }

        echo "Interrogazione inserita con successo! <a href='1_home.html'>Accedi nuovamente</a> per vederla";
    } else {        // Event: other
        $event_insert = $conn -> query("INSERT INTO event VALUES ('', '$name', '$description', '$start_date', '$end_date', $id_class)");
        if (!$event_insert){
            die($conn->error);
        } else {
            echo "Evento inserito con successo! <a href='1_home.html'>Accedi nuovamente</a> per vederlo";
        }
    }

    // Close DB Connection
    $conn -> close();
?>