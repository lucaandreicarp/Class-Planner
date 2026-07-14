<?php

    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $code = $_POST["code"];

    // Class cancellation
    $stmt = $conn->prepare(
        "DELETE FROM class WHERE code=?"    // Enough because all tables are delete on cascade 
    );
    $stmt->bind_param("s", $code);

    $result_cancellation = $stmt->execute();  

    if($result_cancellation){
        echo "<script> alert('Classe eliminata correttamente!'); window.location.href='1_home.html'; </script>";
    } else {
        die($conn->error); 
    }

    // Close DB Connection
    $conn -> close();
?>