<?php
    require_once "../config/database.php";

    $code = $_POST["code"];

    // Class cancellation
    $stmt = $conn->prepare(
        "DELETE FROM class WHERE code=?"    // Enough because all tables are delete on cascade 
    );
    $stmt->bind_param("s", $code);

    if (!$stmt->execute()) {
        dbError($stmt->error, "Non è stato possibile eliminare la classe.");
    }

    echo "<script> alert('Classe eliminata correttamente!'); window.location.href='../public/index.html'; </script>";

    // Close DB Connection
    $conn -> close();
?>