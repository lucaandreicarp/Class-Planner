<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_slot = $_POST["idslot"];
    $id_student = $_POST["idstudent"];

    // Check if oral already exists
    $result_id_oral = $conn -> query("SELECT idoral FROM oral WHERE idstudent = $id_student AND idslot = $id_slot");

    if ($result_id_oral) {
        if ($result_id_oral -> num_rows > 0) {      // Oral remove
            $row = $result_id_oral -> fetch_object();
            $remove_oral = $conn -> query("DELETE FROM oral WHERE idoral = $row->idoral"); 
            if ($remove_oral){
                echo "Rimozione avvenuta con successo!";
            } else {
                die($conn->error); 
            }    
        } else {        // Oral insert
            $insert_oral = $conn -> query("INSERT INTO oral VALUES ('', $id_student, $id_slot)");
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