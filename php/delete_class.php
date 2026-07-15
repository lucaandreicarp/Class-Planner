<?php
    require_once "../config/database.php";

    $code = $_POST["code"];

    // Class cancellation
    $stmt = $conn->prepare(
        "DELETE FROM class WHERE code=?"    // Enough because all tables are delete on cascade 
    );
    $stmt->bind_param("s", $code);

    $result_cancellation = $stmt->execute();  

    if($result_cancellation){
        echo "<script> alert('Classe eliminata correttamente!'); window.location.href='../public/index.html'; </script>";
    } else {
        die($conn->error); 
    }

    // Close DB Connection
    $conn -> close();
?>