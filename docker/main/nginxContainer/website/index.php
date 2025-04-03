<?php
    $dsn = "mysql:host=mysql_main;port=3306;";
    try {
        $pdo = new PDO($dsn, "root", "jorge_root_password");
        $pdo->exec("CREATE DATABASE IF NOT EXISTS my_new_database");
        echo "Database created successfully";
    } 
    catch (PDOException $e) {
        echo "Database creation failed: " . $e->getMessage();
    }
?>