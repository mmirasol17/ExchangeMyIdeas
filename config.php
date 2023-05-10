<?php


$connString = "mysql:host=localhost; dbname=blog";
$user = "root";
$pass = "root";

$conn = new pdo($connString, $user, $pass);

$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);//useful during initial development and debugging
