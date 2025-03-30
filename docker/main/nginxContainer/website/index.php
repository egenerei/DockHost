<?php
    $usuario = "root";
    $contraseña= "";
    $dbname = "client_db";
    $server = "mysql_main";
    try {
        #Conexion a base de datos y creacion (si no existe)
        $conexion = new PDO('mysql:host=localhost', $usuario, $contraseña);
        $query="CREATE DATABASE IF NOT EXISTS $dbname;";
        $conexion->query($query);
        echo "Base creada";
        #Creacion de tablas
        $query="USE club_ajedrez;
                    CREATE TABLE IF NOT EXISTS JUGADORES (
                        IDENTIFICACION_JUGADOR VARCHAR(10) PRIMARY KEY,
                        NOMBRE VARCHAR(20),
                        ELO DECIMAL(4,0),
                        FECHA_NAC DATE,
                        MAIL VARCHAR(50),
                        TELEFONO DECIMAL(9)
                    );
                    CREATE TABLE IF NOT EXISTS JUEGOS(
                    ID_JUGADOR_A VARCHAR(10),
                    ID_JUGADOR_B VARCHAR(10),
                    FECHA DATE,
                    HORA_INICIO VARCHAR(5),
                    HORA_FIN VARCHAR(5),
                    RESULTADO VARCHAR(10),
                    PRIMARY KEY (ID_JUGADOR_A, ID_JUGADOR_B, FECHA),
                    FOREIGN KEY (ID_JUGADOR_A) REFERENCES JUGADORES (IDENTIFICACION_JUGADOR),
                    FOREIGN KEY (ID_JUGADOR_B) REFERENCES JUGADORES (IDENTIFICACION_JUGADOR) 
                    );
        ";
        $conexion->query($query);
    } 
    catch (PDOException $e) {
        print "¡Error!: " . $e->getMessage() . "<br/>";
        die();
    }
    $conexion = null;
    header("Location:form_jugadores.php");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test HTML Page</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 50px;
        }
        h1 {
            color: #333;
        }
        .button {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            color: #fff;
            background-color: #007bff;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <h1>Welcome to the Test Page</h1>
    <p>This is a simple HTML document for testing purposes.</p>
    <a href="#" class="button">Click Me</a>
</body>
</html>
