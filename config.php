<?php
// config the db settings
$connString = "mysql:host=localhost; dbname=blog";
$user = "root";
$pass = "root";

// initialize the PHP data obj to access the db
$conn = new pdo($connString, $user, $pass);
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
