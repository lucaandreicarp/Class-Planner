<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $code = $_POST["code"];

    // Extracting id class
    $result_id_class = $conn -> query("SELECT idclass FROM class WHERE code = '$code'");

    if ($result_id_class && $result_id_class->num_rows > 0) {
        $row = $result_id_class->fetch_assoc();
        $id_class = $row["idclass"];
    } else {
        echo "Classe non trovata";
        die($conn->error);
    }
?>