<?php

    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $id_class = $_POST["code"];

    $result_cancellation = $conn -> query("DELETE FROM class WHERE code = '$id_class'");
    if($result_cancellation){
        echo "<script> alert('Classe eliminata correttamente!'); window.location.href='1_home.html'; </script>";
    } else {
        die($conn->error); 
    }

    // Close DB Connection
    $conn -> close();
?>