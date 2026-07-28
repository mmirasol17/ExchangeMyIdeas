<?php
/*
 * Schema migrations.
 *
 * Migrations apply themselves on the first request after a deploy. There is no
 * key to remember and no page to visit -- the step that was easiest to forget
 * is gone.
 *
 * WHY THIS IS SAFE TO DO ON A REQUEST
 *   The usual objection to migrating from the request path is that DDL is slow,
 *   can collide between concurrent requests, and fails invisibly. Each is
 *   handled:
 *
 *     Cost      A marker file records which set of migrations is applied. When
 *               it matches, this costs one filesystem read and no database
 *               query at all, which is the case on virtually every request.
 *     Collision A MySQL advisory lock (GET_LOCK, zero timeout) means exactly
 *               one request migrates. Everyone else moves on immediately
 *               rather than queueing behind an ALTER.
 *     Failure   Errors are logged, never thrown. The app already detects its
 *               own schema (site_caps()), so a failed migration degrades to the
 *               previous feature set instead of a white page. A failure marker
 *               backs off retries so a broken migration cannot be re-attempted
 *               on every hit.
 *
 * WHAT THIS ASSUMES
 *   That migrations are additive and safe to run unattended, which is the rule
 *   this project already follows (see migrations/README.md). Anything
 *   destructive should be applied deliberately: set MIGRATIONS_AUTO_APPLY to
 *   false and use /migrate, which still exists for exactly that.
 */

/** Set false to require every migration to be applied by hand via /migrate. */
const MIGRATIONS_AUTO_APPLY = true;

/** After a failure, wait this long before trying again. */
const MIGRATIONS_RETRY_AFTER = 300;

/** Advisory lock name. Scoped by database so two sites cannot block each other. */
function migrations_lock_name(): string {
  global $dbName;
  return 'emi_migrate_' . substr(sha1((string) ($dbName ?? 'blog')), 0, 16);
}

function migrations_dir(): string {
  return dirname(__DIR__) . '/migrations';
}

/** Every migration file, in filename order: 001_, then 002_, ... */
function migration_files(): array {
  $files = glob(migrations_dir() . '/*.sql') ?: [];
  sort($files);
  return $files;
}

/** A short fingerprint of which migrations exist on disk. */
function migrations_signature(array $files): string {
  return substr(sha1(implode('|', array_map('basename', $files))), 0, 20);
}

/** Ensure the tracking table exists. */
function migrations_ensure_table(PDO $conn): void {
  $conn->exec('
    CREATE TABLE IF NOT EXISTS schema_migrations (
      filename VARCHAR(255) NOT NULL,
      applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (filename)
    )
  ');
}

/** Filenames already recorded as applied. */
function migrations_applied(PDO $conn): array {
  return array_flip(
    $conn->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN)
  );
}

/**
 * Apply every pending migration, in order.
 *
 * @return array{applied:string[],failed:?string,error:?string}
 * @throws PDOException if the tracking table itself cannot be read
 */
function migrations_apply(PDO $conn): array {
  migrations_ensure_table($conn);
  $applied = migrations_applied($conn);
  $ran = [];

  foreach (migration_files() as $file) {
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
    } catch (PDOException $e) {
      // Stop at the first failure: a later migration may depend on this one.
      return ['applied' => $ran, 'failed' => $name, 'error' => $e->getMessage()];
    }
  }

  return ['applied' => $ran, 'failed' => null, 'error' => null];
}

/**
 * Apply anything pending, quietly, at most once per deploy.
 *
 * Called from bootstrap before anything inspects the schema, so a request that
 * triggers a migration still renders with the new features available.
 */
function migrations_auto_apply(PDO $conn): void {
  if (!MIGRATIONS_AUTO_APPLY) {
    return;
  }

  $files = migration_files();
  if (!$files) {
    return;
  }

  $signature = migrations_signature($files);
  $marker = migrations_dir() . '/.applied';

  // The fast path: no database work at all once the set of migrations on disk
  // matches what was last applied.
  if (@file_get_contents($marker) === $signature) {
    return;
  }

  $failMarker = migrations_dir() . '/.failed';
  $failedAt = (int) @file_get_contents($failMarker);
  if ($failedAt > 0 && (time() - $failedAt) < MIGRATIONS_RETRY_AFTER) {
    return;
  }

  // Zero timeout: if another request holds the lock it is already migrating,
  // and this one should serve its page rather than wait behind an ALTER.
  try {
    $lock = $conn->query("SELECT GET_LOCK('" . migrations_lock_name() . "', 0)")->fetchColumn();
    if ((int) $lock !== 1) {
      return;
    }
  } catch (PDOException $e) {
    error_log('migration lock unavailable: ' . $e->getMessage());
    return;
  }

  try {
    $result = migrations_apply($conn);

    if ($result['failed'] !== null) {
      error_log("auto-migration failed on {$result['failed']}: {$result['error']}");
      @file_put_contents($failMarker, (string) time());
    } else {
      if ($result['applied']) {
        error_log('auto-migration applied: ' . implode(', ', $result['applied']));
      }
      // If the marker cannot be written (read-only filesystem) the only cost is
      // re-checking the tracking table on later requests, which is cheap.
      @file_put_contents($marker, $signature);
      @unlink($failMarker);
    }
  } catch (Throwable $e) {
    error_log('auto-migration error: ' . $e->getMessage());
    @file_put_contents($failMarker, (string) time());
  } finally {
    try {
      $conn->query("SELECT RELEASE_LOCK('" . migrations_lock_name() . "')");
    } catch (PDOException $e) {
      // The lock is released when the connection closes regardless.
    }
  }
}

/** What is applied and what is not, for the status page. */
function migrations_status(PDO $conn): array {
  try {
    migrations_ensure_table($conn);
    $applied = migrations_applied($conn);
  } catch (PDOException $e) {
    return ['applied' => [], 'pending' => array_map('basename', migration_files()), 'error' => $e->getMessage()];
  }

  $pending = [];
  $done = [];
  foreach (migration_files() as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
      $done[] = $name;
    } else {
      $pending[] = $name;
    }
  }

  return ['applied' => $done, 'pending' => $pending, 'error' => null];
}
