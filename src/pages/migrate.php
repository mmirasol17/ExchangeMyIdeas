<?php
/*
 * Migration status and manual runner. Serves /migrate.
 *
 * Migrations normally apply themselves on the first request after a deploy
 * (see migrator.php), so this page exists for visibility and for the cases
 * auto-apply deliberately does not cover:
 *
 *   - MIGRATIONS_AUTO_APPLY has been turned off for a risky change.
 *   - A migration failed and you want to see why, or retry now rather than
 *     waiting out the back-off.
 *
 * ACCESS
 *   Reporting status needs no key: it lists filenames that are already public
 *   in the repository, and says nothing about the data. Actually *running* a
 *   migration still requires $migrateKey, because that writes to the schema.
 *
 *   That split is the point of this page. The common case -- "did my migration
 *   land?" -- no longer depends on a secret nobody can read back out of
 *   GitHub, while the privileged action stays privileged.
 */

global $migrateKey;

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$provided = (string) ($_GET['key'] ?? '');
$hasKey = !empty($migrateKey) && hash_equals((string) $migrateKey, $provided);
$wantsRun = isset($_GET['run']);

// --- Run, if asked and allowed --------------------------------------------
if ($wantsRun) {
  if (!$hasKey) {
    http_response_code(403);
    exit("Forbidden: running migrations requires ?key=...\n");
  }

  @unlink(migrations_dir() . '/.failed'); // an explicit run clears the back-off
  $result = migrations_apply($conn);

  if ($result['failed'] !== null) {
    http_response_code(500);
    echo "FAILED on {$result['failed']}:\n{$result['error']}\n\n";
    echo $result['applied']
      ? "Applied before failure:\n- " . implode("\n- ", $result['applied']) . "\n"
      : "Nothing applied before failure.\n";
    return;
  }

  @file_put_contents(migrations_dir() . '/.applied', migrations_signature(migration_files()));
  echo $result['applied']
    ? 'Applied ' . count($result['applied']) . " migration(s):\n- " . implode("\n- ", $result['applied']) . "\n"
    : "No pending migrations.\n";
  return;
}

// --- Otherwise report ------------------------------------------------------
$status = migrations_status($conn);

if ($status['error'] !== null) {
  http_response_code(500);
  echo "Could not read migration state:\n{$status['error']}\n";
  return;
}

echo "Schema migrations\n";
echo "=================\n\n";

echo 'Auto-apply: ' . (MIGRATIONS_AUTO_APPLY ? "on\n" : "off\n");

$failedAt = (int) @file_get_contents(migrations_dir() . '/.failed');
if ($failedAt > 0) {
  echo 'Last attempt FAILED ' . (time() - $failedAt) . "s ago - see the server error log.\n";
}
echo "\n";

echo 'Applied (' . count($status['applied']) . "):\n";
foreach ($status['applied'] as $name) {
  echo "  [x] $name\n";
}
if (!$status['applied']) {
  echo "  (none)\n";
}

echo "\nPending (" . count($status['pending']) . "):\n";
foreach ($status['pending'] as $name) {
  echo "  [ ] $name\n";
}
if (!$status['pending']) {
  echo "  (none - the database is up to date)\n";
}

if ($status['pending']) {
  echo "\nThese normally apply themselves on the next request.\n";
  echo "To force a run now: /migrate?run=1&key=YOUR_KEY\n";
}
