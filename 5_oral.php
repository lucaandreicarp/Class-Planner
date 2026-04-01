<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_slot = $_POST["idslot"];
    $id_student = $_POST["idstudent"];

    // Oral insert
    $result_oral = $conn -> query("INSERT INTO oral VALUES ('', $id_student, $id_slot)");

    if ($result_oral){
        echo "Inserimento avvenuto con successo!";
    } else {
        die($conn->error); 
    }

    // Close DB Connection
    $conn -> close();
?>