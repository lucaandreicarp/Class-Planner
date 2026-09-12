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

            $conn->begin_transaction();

            try {
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
                        throw new Exception("Errore nell'inserimento di una interrogazione.");                    
                    }

                    if ($stmt->affected_rows === 1) {   // Inserted
                        $inserted++;
                    } else {    // Skipped
                        $skipped++;
                        unset($capacity[$date]);    // Automatic assignment will work only on new slots
                    }
                }
                
                // Automatic assignment
                if (isset($_POST["automatic_assignment"])) {

                    // Extracting students
                    $stmt = $conn->prepare("
                        SELECT idstudent
                        FROM student
                        WHERE idclass = ?
                    ");

                    $stmt->bind_param("i", $id_class);

                    if (!$stmt->execute()) {
                        throw new Exception("Errore nel recupero degli studenti della classe.");
                    }

                    $result = $stmt->get_result();
                    $students = [];

                    while ($student = $result->fetch_assoc()) {
                        $students[] = $student["idstudent"];
                    }

                    // Automatic assignment works only if all students can be assigned to a slot
                    $totalCapacity = array_sum($capacity);
                    $totalStudents = count($students);

                    if ($totalCapacity < $totalStudents) {
                        throw new Exception(
                            "La capacità totale dei nuovi slot (" . $totalCapacity . ") è inferiore al numero di studenti della classe (" . $totalStudents . ").\nLe interrogazioni già esistenti (" . $skipped . ") non vengono considerate nell'assegnazione automatica."
                        );
                    }

                    // Assigning students to available slots
                    foreach ($capacity as $date => $cap) {

                        $currentDate = new DateTime($date);
                        $scores = [];

                        shuffle($students); // Randomize student order to avoid bias

                        // Calculate scores for each student
                        foreach ($students as $idstudent) {
                            // Extract last and next oral dates for the student
                            $stmt = $conn->prepare("
                                SELECT 
                                    MAX(CASE WHEN s.date <= ? THEN s.date END) AS last_date,
                                    MIN(CASE WHEN s.date >= ? THEN s.date END) AS next_date
                                FROM Oral o
                                JOIN Slot s ON o.idslot = s.idslot
                                WHERE o.idstudent = ?
                            ");

                            $stmt->bind_param("ssi", $date, $date, $idstudent);

                            if (!$stmt->execute()) {
                                throw new Exception("Errore nel recupero delle date precedenti e successive per uno studente.");
                            }

                            $result = $stmt->get_result();
                            $row = $result->fetch_assoc();

                            // Calculate the distance for the previous date
                            if (!empty($row["last_date"])) {
                                $lastObj = new DateTime($row["last_date"]);
                                $raw_last_distance = $currentDate->diff($lastObj)->days;
                                $last_distance = min($raw_last_distance, 30); // Capped a 30
                            } else {
                                $last_distance = 30; // Assign a maximum distance if no previous oral exists
                            }

                            // Calculate the distance for the next date
                            if (!empty($row["next_date"])) {
                                $nextObj = new DateTime($row["next_date"]);
                                $raw_next_distance = $nextObj->diff($currentDate)->days;
                                $next_distance = min($raw_next_distance, 30); // Capped a 30
                            } else {
                                $next_distance = 30; // Assign a maximum distance if no next oral exists
                            }

                            // Calculate and assign score
                            $scores[$idstudent] = ((($last_distance * $last_distance) + $last_distance) * ($next_distance / ($next_distance + 3))) + ((($next_distance * $next_distance) + $next_distance) * ($last_distance / ($last_distance + 3)));
                        }

                        arsort($scores);

                        // Get the slot ID
                        $stmt = $conn->prepare("
                            SELECT idslot
                            FROM slot
                            WHERE date = ? AND idsubject = ? AND idclass = ?
                        ");

                        $stmt->bind_param("sii", $date, $id_subject, $id_class);

                        if (!$stmt->execute()) {
                            throw new Exception("Errore durante l'estrazione di una interrogazione.");
                        }

                        $result = $stmt->get_result();
                        $row = $result->fetch_assoc();

                        $idslot = $row["idslot"];

                        for ($i = 0; $i < $cap; $i++) {

                            // Stop if all students have been assigned
                            if (empty($scores)) {
                                break;
                            }

                            // Get the student with the highest score
                            $idstudent = array_key_first($scores);

                            // Assign student to slot
                            $stmt = $conn->prepare("
                                INSERT INTO oral (idstudent, idslot)
                                VALUES (?, ?)
                            ");

                            $stmt->bind_param("ii", $idstudent, $idslot);

                            if (!$stmt->execute()) {
                                throw new Exception("Errore durante l'assegnazione di uno studente.");
                            }

                            // Remove student from the list
                            $index = array_search($idstudent, $students);

                            unset($students[$index]);
                            unset($scores[$idstudent]);
                        }
                    }
                }

                $conn->commit();

                $message = "$inserted " . ($inserted === 1 ? "interrogazione inserita." : "interrogazioni inserite.");
                
                if ($skipped > 0) {
                    $message .= " $skipped " . ($skipped === 1 ? "interrogazione era già presente." : "interrogazioni erano già presenti.");
                }

                if (isset($_POST["automatic_assignment"])) {
                    $message .= "\nAssegnazione automatica completata con successo: tutti gli studenti sono stati assegnati secondo la distribuzione più equilibrata possibile.";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "Operazione annullata: " . $e->getMessage();
            }

            // Output 
            echo "<script>
            alert(" . json_encode("$message") . ");
            window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
            </script>";

        } else {    
            if ($_POST["action"] === "Modifica") {      // Update event
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

        } else {    
            if ($_POST["action"] === "Modifica") {      // Update event
                $name = $_POST["event_name"];
                $description = $_POST["description"];
                $start_date = $_POST["start_date"];
                $end_date = $_POST["end_date"];

                $stmt = $conn->prepare(
                    "UPDATE event 
                    SET name = ?, description = ?, start_date = ?, end_date = ? 
                    WHERE idevent = ?"
                );
                
                $stmt->bind_param(
                    "ssssi", 
                    $name, 
                    $description, 
                    $start_date, 
                    $end_date, 
                    $idevent
                );

                if (!$stmt->execute()) {
                    dbError($stmt->error, "Non è stato possibile modificare l'evento.");
                }

                echo "<script>
                alert('Evento modificato con successo!');
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

                echo "<script>
                alert('Evento rimosso con successo!');
                window.location.href=" . json_encode("../public/class_planner.php?code=" . urlencode($code)) . ";
                </script>";
            }
        }
    }

    // Close DB Connection
    $conn -> close();
?>