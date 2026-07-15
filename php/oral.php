<?php
    require_once "../config/database.php";

    $id_slot = $_POST["idslot"];
    $id_student = $_POST["idstudent"];

    // Check if oral already exists
    $stmt = $conn->prepare(
        "SELECT idoral FROM oral WHERE idstudent=? AND idslot=?"
    );
    $stmt->bind_param("ii", $id_student, $id_slot);
    $stmt->execute();

    $result_id_oral = $stmt->get_result();

    if ($result_id_oral) {
        if ($result_id_oral -> num_rows > 0) {      // Oral remove
            $row = $result_id_oral -> fetch_object();
            $stmt = $conn->prepare(
                "DELETE FROM oral WHERE idoral=?"
            );

            $stmt->bind_param("i", $row->idoral);

            $remove_oral = $stmt->execute();
            if ($remove_oral){
                echo "Rimozione avvenuta con successo!";
            } else {
                die($conn->error); 
            }    
        } else {        // Oral insert
            $stmt = $conn->prepare(
                "INSERT INTO oral (idstudent, idslot) VALUES (?, ?)"
            );
            $stmt->bind_param("ii", $id_student, $id_slot);

            $insert_oral = $stmt->execute();

            if ($insert_oral){
                echo "Inserimento avvenuto con successo!";
            } else {
                die($conn->error); 
            }
        }
    } else {
        die($conn->error);
    }

    // Close DB Connection
    $conn -> close();
?>