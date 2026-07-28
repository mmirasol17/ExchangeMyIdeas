<?php
/*
 * Database migration runner. Serves /migrate?key=...
 *
 * Applies any not-yet-applied .sql files in ./migrations, in filename order,
 * and records them in a schema_migrations table so each runs exactly once.
 *
 * WHY THIS IS BROWSER-TRIGGERED, NOT AUTOMATED IN CI:
 *   InfinityFree does not allow remote MySQL connections, so a GitHub Actions
 *   runner cannot reach the database to run migrations directly. It also serves
 *   an anti-bot challenge to non-browser HTTP requests, which blocks curl-ing
 *   this endpoint from CI. So the flow is:
 *     1. Add a migration file, commit, push -> it auto-deploys over FTP.
 *     2. Visit this page ONCE in your browser to apply it:
 *        https://exchangemyideas.marinmirasol.com/migrate?key=YOUR_KEY
 *
 * SECURITY:
 *   Protected by a secret key set as $migrateKey in config.local.php (which
 *   lives only on the server, never in git). If $migrateKey is unset, this
 *   endpoint is disabled.
 */

global $migrateKey;

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$provided = (string) ($_GET['key'] ?? '');
if (empty($migrateKey) || !hash_equals((string) $migrateKey, $provided)) {
  http_response_code(403);
  exit("Forbidden.\n");
}

// Track which migrations have run.
$conn->exec('
  CREATE TABLE IF NOT EXISTS schema_migrations (
    filename VARCHAR(255) NOT NULL,
    applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (filename)
  )
');

$applied = array_flip(
  $conn->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)
);

// dirname twice: this file is src/pages/, migrations/ is at the project root.
$files = glob(dirname(__DIR__, 2) . '/migrations/*.sql') ?: [];
sort($files); // filename order -> run 001_, then 002_, ...

$ran = [];
foreach ($files as $file) {
  $name = basename($file);
  if (isset($applied[$name])) {
    continue;
  }

  $sql = file_get_contents($file);
  if ($sql === false || trim($sql) === '') {
    continue;
  }

  try {
    $conn->exec($sql);
    $stmt = $conn->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
    $stmt->execute([$name]);
    $ran[] = $name;
  } catch (PDOException $ex) {
    http_response_code(500);
    echo "FAILED on {$name}:\n{$ex->getMessage()}\n\n";
    echo $ran ? "Applied before failure:\n- " . implode("\n- ", $ran) . "\n" : "Nothing applied before failure.\n";
    return;
  }
}

echo $ran
  ? 'Applied ' . count($ran) . " migration(s):\n- " . implode("\n- ", $ran) . "\n"
  : "No pending migrations. Database is up to date.\n";
