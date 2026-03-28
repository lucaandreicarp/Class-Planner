<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_class = $_POST["idclass"];
    $type = $_POST["type"];
    $subject = $_POST["subject"];
    $name = $_POST["name"];
    $description = ["description"];
    $start_date = ["start_date"];
    $end_date = ["end_date"];

    if ($type == "oral"){       // Event: oral
        $schedule_days = [];
        $subject_days = [];

        $schedule = $conn -> query("SELECT day_of_week FROM schedule WHERE idsubject = $subject");
        if ($schedule){
            while($row = $schedule -> fetch_object()){
                $subject_days[] = $row -> day_of_week;
            }
        } else {
           die($conn->error); 
        }
    } else {        // Event: other
        $event_insert = $conn -> query("INSERT INTO event VALUES ('', '$name', '$description', '$start_date', '$end_date', $id_class)");
        if (!$event_insert){
            die($conn->error);
        }
    }

?>