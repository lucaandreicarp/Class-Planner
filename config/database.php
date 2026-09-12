<?php

    $config = require __DIR__ . '/database.local.php';

    $conn = new mysqli(
        $config['host'],
        $config['user'],
        $config['password'],
        $config['database']
    );

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

    $conn->set_charset("utf8mb4");
?>