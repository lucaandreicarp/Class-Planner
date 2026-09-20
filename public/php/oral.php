<?php
    require_once dirname(__DIR__, 2) . '/config/database.php';

    $code = $_POST["code"];
    $id_slot = $_POST["idslot"];
    $id_student = $_POST["idstudent"];

    // Extracting code
    $stmt = $conn->prepare("
        SELECT idclass
        FROM class
        WHERE code = ?
    ");

    $stmt->bind_param("s", $code);

    if (!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile recuperare il codice della classe.");
    }

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $id_class = $row["idclass"]; 

    // Check that both the slot and the student belong to the class
    $stmt = $conn->prepare(
        "SELECT 1
        FROM slot s
        JOIN student st ON st.idclass = s.idclass
        WHERE s.idslot = ?
        AND st.idstudent = ?
        AND s.idclass = ?"
    );

    $stmt->bind_param("iii", $id_slot, $id_student, $id_class);

    if (!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile verificare la prenotazione.");
    }

    if ($stmt->get_result()->num_rows === 0) {
        dbError(
            "Tentativo di modificare una prenotazione con slot o studente non appartenenti alla classe.",
            "Lo studente o l'interrogazione non appartengono alla classe."
        );
    }

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