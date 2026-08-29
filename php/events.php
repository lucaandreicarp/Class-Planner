<?php
    require_once "../config/database.php";

    $id_class = $_POST["idclass"];
    $type = $_POST["type"];

    $idslot = $_POST["idslot"] ?? null;     // Check if slot already created
    $idevent = $_POST["idevent"] ?? null;   // Check if event already created

    // Extracting code
    $stmt = $conn->prepare("
        SELECT code
        FROM class
        WHERE idclass = ?
    ");

    $stmt->bind_param("i", $id_class);

    if (!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile recuperare il codice della classe.");
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $code = $row["code"]; 

    if ($type == "oral"){       // Event: oral
        if ($idslot == null){   // Create event

            $id_subject = $_POST["subject"];
            $capacity = $_POST["capacity"];

            // Inserting values in db
            $inserted = 0;      // Inserted events
            $skipped = 0;       // Skipped events

            foreach ($capacity as $date => $cap) {
                $stmt = $conn->prepare(
                    "INSERT INTO slot (date, capacity, idclass, idsubject) 
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE idslot = idslot"    // If duplicate nothing changes
                );
                $stmt->bind_param("siii", $date, $cap, $id_class, $id_subject);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile inserire una interrogazione.");
                }

                if ($stmt->affected_rows === 1) {   // Inserted
                    $inserted++;
                } else {    // Skipped
                    $skipped++;
                }
            }

            $message = "$inserted interrogazioni inserite. ";
            
            if ($skipped > 0) {
                $message .= "$skipped interrogazioni erano già presenti.";
            }

            // Output 
            echo "<script>
            alert(" . json_encode("$message") . ");
            window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
            </script>";

        } else {    
            if ($_POST["action"] === "Modifica posti") {      // Update event
                $capacity = $_POST["capacity"];
            
                $stmt = $conn->prepare(
                    "UPDATE slot SET capacity = ? WHERE idslot = ?"
                );
                $stmt->bind_param("ii", $capacity, $idslot);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile modificare l'interrogazione.");
                }

                echo "<script>
                alert(" . json_encode("Interrogazione modificata con successo!") . ");
                window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                </script>";
            } else {    // Remove event
                $stmt = $conn->prepare(
                    "DELETE FROM slot WHERE idslot=?"
                );
                $stmt->bind_param("i", $idslot);

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile rimuovere l'interrogazione.");
                }

                echo "<script>
                alert(" . json_encode("Interrogazione rimossa con successo!") . ");
                window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                </script>";
            }
        }
    } else {        // Event: other
        if ($idevent == null){      // Create event
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