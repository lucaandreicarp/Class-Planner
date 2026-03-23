<?php
    $conn = new mysqli("localhost", "root", "", "class_planner");

    if ($conn -> connect_errno){
        echo "Errore nella creazione della connessione";
        exit();
    }

    $code = $_POST["code"];

    // Extracting class 
    $result_class = $conn -> query("SELECT idclass, name FROM class WHERE code = '$code'");

    if ($result_class && $result_class->num_rows > 0) {
        $row_class = $result_class->fetch_assoc();
        $id_class = $row_class["idclass"];
        $class_name = $row_class["name"];
    } else {
        echo "Classe non trovata";
        die($conn->error);
    }

    // Extracting student
    $result_student = $conn -> query ("SELECT idstudent, name FROM student WHERE idclass = '$id_class'");
    $students = [];
    
    while ($row_student = $result_student->fetch_assoc()) {
        $students[$row_student["idstudent"]] = $row_student["name"];
    }

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Planner</title>
</head>
<body>
    <header>
        <h2><?php echo "$class_name" ?></h2>
        <span>Impostazioni</span>
    </header>
    <aside>
        <div>
            <label for="view">Visuale</label>
            <select id="view">
                <option id="CLASS">CLASSE</option>
                <?php 
                    foreach($students as $id_student => $name){
                        echo "<option id='$id_student'>$name</option>";
                    }
                ?>
            </select>
        </div>
    </aside>
</body>
</html>