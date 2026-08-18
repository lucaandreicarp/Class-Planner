<?php
    require_once "../config/database.php";

    $id_slot = $_POST["idslot"];
    $id_student = $_POST["idstudent"];

    // Check if oral already exists
    $stmt = $conn->prepare(
        "SELECT idoral FROM oral WHERE idstudent=? AND idslot=?"
    );
    
    $stmt->bind_param("ii", $id_student, $id_slot);
    
    if(!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile controllare l'esistenza di una prenotazione nell'interrogazione.");
    }

    $result_id_oral = $stmt->get_result();

    if ($result_id_oral -> num_rows > 0) {      // Oral remove
        $row = $result_id_oral -> fetch_object();
        $stmt = $conn->prepare(
            "DELETE FROM oral WHERE idoral=?"
        );

        $stmt->bind_param("i", $row->idoral);

        if(!$stmt->execute()) {
            dbError($stmt->error, "Non è stato possibile rimuovere la prenotazione nell'interrogazione.");
        }

        echo "Rimozione avvenuta con successo!";   
    } else {        // Oral insert
        $stmt = $conn->prepare(
            "INSERT INTO oral (idstudent, idslot) VALUES (?, ?)"
        );
        $stmt->bind_param("ii", $id_student, $id_slot);

        if (!$stmt->execute()) {
            dbError($stmt->error, "Non è stato possibile inserire la prenotazione nell'interrogazione.");
        }

        echo "Inserimento avvenuto con successo!";
    }

    // Close DB Connection
    $conn -> close();
?>