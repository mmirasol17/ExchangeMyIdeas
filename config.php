<?php
/*
 * Database configuration.
 *
 * Credentials come from the environment so they are never committed. On shared
 * hosting without env var support, copy config.local.php.example to
 * config.local.php and fill it in -- that file is gitignored.
 */

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
  require_once($localConfig);
}

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbName = getenv('DB_NAME') ?: 'blog';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$connString = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";

try {
  $conn = new PDO($connString, $dbUser, $dbPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
} catch (PDOException $e) {
  // Never leak connection details (host, user, credentials) to visitors.
  error_log('Database connection failed: ' . $e->getMessage());
  http_response_code(503);
  exit('The site is temporarily unavailable. Please try again shortly.');
}
