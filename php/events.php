<?php
    require_once "../config/database.php";

    $id_class = $_POST["idclass"];
    $type = $_POST["type"];

    $idslot = $_POST["idslot"] ?? null;     // Check if slot already created
    $idevent = $_POST["idevent"] ?? null;   // Check if event already created

    if ($type == "oral"){       // Event: oral
        if ($idslot == null){   // Create event

            $code = $_POST["code"];
            $id_subject = $_POST["subject"];
            $capacity = $_POST["capacity"];

            // Inserting values in db
            $inserted = 0;      // Inserted events

            foreach ($capacity as $date => $cap) {
                $stmt = $conn->prepare(
                    "INSERT INTO slot (date, capacity, idclass, idsubject) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE idslot = idslot"    // If duplicate, nothing changes
                );
                $stmt->bind_param("siii", $date, $cap, $id_class, $id_subject);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile inserire una interrogazione.");
                }

                if($conn -> affected_rows > 0){     // If something changed (could be all duplicates)
                    $inserted++;
                }
            }

            $skipped = count($capacity) - $inserted;   // Skipped events

            // Output 
            if ($inserted > 0) {
                if ($skipped > 0){
                    echo "<script>
                    alert(" . json_encode("$inserted interrogazioni inserite. $skipped erano già presenti.") . ");
                    window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                    </script>";
                } else {
                    echo "<script>
                    alert(" . json_encode("$inserted interrogazioni inserite!") . ");
                    window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                    </script>";
                }
            } else {
                echo "<script>
                alert('Nessuna nuova interrogazione inserita: erano già presenti.');
                window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                </script>";
            }
        } else {    // Remove event
            $stmt = $conn->prepare(
                "DELETE FROM slot WHERE idslot=?"
            );
            $stmt->bind_param("i", $idslot);

            if (!$stmt->execute()) {
                dbError($stmt->error, "Non è stato possibile rimuovere la interrogazione.");
            }

            echo "Interrogazione rimossa con successo!";
        }
    } else {        // Event: other
        if ($idevent == null){      // Create event
            $code = $_POST["code"];
            $name = $_POST["event_name"];
            $description = $_POST["description"];
            $start_date = $_POST["start_date"];
            $end_date = $_POST["end_date"];

            $stmt = $conn->prepare(
                "INSERT INTO event (name, description, start_date, end_date, idclass)
                VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "ssssi",
                $name,
                $description,
                $start_date,
                $end_date,
                $id_class
            );

            if (!$stmt->execute()) {
                dbError($stmt->error, "Non è stato possibile inserire l'evento.");
            }

            echo "<script>
            alert('Evento inserito con successo!');
            window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
            </script>";

        } else {    // Remove event
            $stmt = $conn->prepare(
                "DELETE FROM event WHERE idevent=?"
            );
            $stmt->bind_param("i", $idevent);

            if (!$stmt->execute()) {
                dbError($stmt->error, "Non è stato possibile rimuovere l'evento.");
            }

            echo "Evento rimosso con successo!";
        }
    }

    // Close DB Connection
    $conn -> close();
?>