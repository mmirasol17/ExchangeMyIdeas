<?php
/*
 * Database configuration.
 *
 * Credentials live in config.local.php (gitignored), which sets the plain
 * variables $dbHost / $dbName / $dbUser / $dbPass. We use plain variables
 * rather than environment variables because some shared hosts (e.g.
 * InfinityFree) disable putenv()/getenv() for user scripts.
 *
 * Falls back to environment variables, then to local-dev defaults.
 */

$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
  require_once($localConfig);
}

$dbHost = $dbHost ?? (getenv('DB_HOST') ?: 'localhost');
$dbName = $dbName ?? (getenv('DB_NAME') ?: 'blog');
$dbUser = $dbUser ?? (getenv('DB_USER') ?: 'root');
$dbPass = $dbPass ?? (getenv('DB_PASS') ?: '');

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
