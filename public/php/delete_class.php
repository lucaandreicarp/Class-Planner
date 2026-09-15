<?php
    require_once dirname(__DIR__, 2) . '/config/database.php';

    $code = $_POST["code"];

    // Class cancellation
    $stmt = $conn->prepare(
        "DELETE FROM class WHERE code=?"    // Enough because all tables are delete on cascade 
    );
    $stmt->bind_param("s", $code);

    if (!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile eliminare la classe.");
    }

    echo "<script> alert('Classe eliminata correttamente!'); window.location.href='../index.html?deleted=1'; </script>";

    // Close DB Connection
    $conn -> close();
?>