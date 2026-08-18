<?php

    $conn = new mysqli("localhost", "root", "", "class_planner");

    function dbError($technicalError, $userMessage = "Si è verificato un errore. Riprova più tardi.") {
        error_log($technicalError);

        echo "<script>
            alert(" . json_encode($userMessage) . ");
            window.history.back();
        </script>";

        exit;
    }
    
    if ($conn->connect_errno) {
        dbError($conn->error, "Impossibile connettersi al servizio. Riprova più tardi.");
    }

?>