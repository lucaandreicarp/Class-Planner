<?php

    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn->connect_errno) {
        die("Errore nella connessione al database.");
    }

?>