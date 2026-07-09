<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_class = $_POST["idclass"];
    $type = $_POST["type"];

    $idslot = $_POST["idslot"] ?? null;
    $idevent = $_POST["idevent"] ?? null;

    if ($type == "oral"){       // Event: oral
        if ($idslot == null){

            $code = $_POST["code"];
            $id_subject = $_POST["subject"];
            $start_date = $_POST["start_date"];
            $end_date = $_POST["end_date"];

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

            $inserted = 0;

            foreach ($dates as $date){
                $slot_insert = $conn -> query("INSERT INTO slot VALUES ('', '$date', $id_class, $id_subject) ON DUPLICATE KEY UPDATE idslot = idslot");
                if (!$slot_insert){
                    die($conn->error);               
                }
                    
                if($conn -> affected_rows > 0){
                    $inserted++;
                }
            }

            $skipped = count($dates) - $inserted;

            if ($inserted > 0) {
                if ($skipped > 0){
                    echo "<script> alert('$inserted interrogazioni inserite. $skipped erano già presenti.'); window.location.href='3_class_planner.php?code=$code'; </script>";
                } else {
                    echo "<script> alert('$inserted interrogazioni inserite!'); window.location.href='3_class_planner.php?code=$code'; </script>";
                }
            } else {
                echo "<script> alert('Nessuna nuova interrogazione inserita: erano già presenti.'); window.location.href='3_class_planner.php?code=$code'; </script>";
            }
        } else {
            $slot_remove = $conn -> query("DELETE FROM slot WHERE idslot = $idslot");

            echo "Interrogazione rimossa con successo!";
        }

    } else {        // Event: other
        if ($idevent == null){
            $code = $_POST["code"];
            $name = $_POST["event_name"];
            $description = $_POST["description"];
            $start_date = $_POST["start_date"];
            $end_date = $_POST["end_date"];

            $event_insert = $conn -> query("INSERT INTO event VALUES ('', '$name', '$description', '$start_date', '$end_date', $id_class)");
            if (!$event_insert){
                die($conn->error);
            } else {
                echo "<script> alert('Evento inserito con successo!'); window.location.href='3_class_planner.php?code=$code'; </script>";
            }
        } else {
            $event_remove = $conn -> query("DELETE FROM event WHERE idevent = $idevent");
        
            echo "Evento rimosso con successo!";
        }
    }

    // Close DB Connection
    $conn -> close();
?>