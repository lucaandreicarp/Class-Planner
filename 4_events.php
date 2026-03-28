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
    $description = $_POST["description"];
    $start_date = new DateTime($_POST["start_date"]);
    $end_date = new DateTime($_POST["end_date"]);

    if ($type == "oral"){       // Event: oral
        $subject_days = [];

        $schedule = $conn -> query("SELECT day_of_week FROM schedule WHERE idsubject = $subject");
        if ($schedule){
            while($row = $schedule -> fetch_object()){
                $subject_days[] = $row -> day_of_week;
            }
        } else {
           die($conn->error); 
        }

        $dates = [];

        for($data = clone $start_date; $data <= $end_date; $data->modify('+1 day')){
            $number_day = $data -> format("N");
            if (in_array($number_day, $subject_days)){
                $dates[] = $data->format("Y-m-d");
            }
        }

        // Manca inserimento in db (Slot)
    } else {        // Event: other
        $event_insert = $conn -> query("INSERT INTO event VALUES ('', '$name', '$description', '$start_date', '$end_date', $id_class)");
        if (!$event_insert){
            die($conn->error);
        }
    }

?>