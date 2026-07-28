<?php
/*
 * Small, dependency-free helpers: escaping, formatting, Markdown, and the
 * schema probes that let features wait for their migration.
 *
 * Nothing here touches layout or knows about a request. Anything that renders
 * markup belongs in view.php.
 */

/** Escape untrusted output for HTML. */
function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Human-friendly relative time, e.g. "3 days ago". */
function relative_time(string $timestamp): string {
  $t = strtotime($timestamp);
  if ($t === false) {
    return '';
  }
  $diff = time() - $t;
  if ($diff < 60)     return 'just now';
  if ($diff < 3600)   { $m = (int) floor($diff / 60);    return $m . ' minute' . ($m === 1 ? '' : 's') . ' ago'; }
  if ($diff < 86400)  { $h = (int) floor($diff / 3600);  return $h . ' hour'   . ($h === 1 ? '' : 's') . ' ago'; }
  if ($diff < 604800) { $d = (int) floor($diff / 86400); return $d . ' day'    . ($d === 1 ? '' : 's') . ' ago'; }
  return date('M j, Y', $t);
}

/** Up to two uppercase initials from a name. */
function initials(string $name): string {
  $parts = preg_split('/\s+/', trim($name)) ?: [];
  $first = mb_substr($parts[0] ?? '?', 0, 1);
  $last  = count($parts) > 1 ? mb_substr($parts[count($parts) - 1], 0, 1) : '';
  return mb_strtoupper($first . $last);
}

/** Deterministic avatar background colour derived from a name. */
function avatar_color(string $name): string {
  $hue = crc32($name) % 360;
  return "hsl({$hue}deg 55% 45%)";
}

/**
 * Whether a column exists on a table. Cached per request. Lets features that
 * depend on a migration degrade gracefully until it has run, so a deploy can
 * never reference a not-yet-created column and break the site.
 * Table name is a trusted constant, never user input.
 */
function column_exists(PDO $conn, string $table, string $column): bool {
  static $cache = [];
  $key = "$table.$column";
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }
  try {
    // Use a direct query (not a prepared statement): SHOW COLUMNS with a bound
    // LIKE param fails when server-side prepares are enabled (emulation off),
    // as on InfinityFree. Table name is a trusted constant, never user input.
    $cols = $conn->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    return $cache[$key] = in_array($column, $cols, true);
  } catch (PDOException $e) {
    return $cache[$key] = false;
  }
}

/** Whether a table exists. Cached per request, same rationale as column_exists(). */
function table_exists(PDO $conn, string $table): bool {
  static $cache = [];
  if (array_key_exists($table, $cache)) {
    return $cache[$table];
  }
  try {
    $conn->query("SELECT 1 FROM `$table` LIMIT 1");
    return $cache[$table] = true;
  } catch (PDOException $e) {
    return $cache[$table] = false;
  }
}

/**
 * Renders a safe, minimal subset of Markdown: **bold**, *italic*, `code`, and
 * [text](https://url) links. Input is HTML-escaped FIRST, so user content can
 * never inject markup - the formatting is applied to already-safe text.
 */
function render_markdown(string $text): string {
  $safe = e($text);
  // `inline code`
  $safe = preg_replace_callback('/`([^`\n]+)`/', fn($m) => '<code>' . $m[1] . '</code>', $safe);
  // **bold**
  $safe = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $safe);
  // *italic* (not part of a ** run)
  $safe = preg_replace('/(?<!\*)\*(?!\*)([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe);
  // [text](https://url) -- URL is already entity-escaped by e()
  $safe = preg_replace_callback(
    '/\[([^\]\n]+)\]\((https?:\/\/[^\s)]+)\)/',
    fn($m) => '<a href="' . $m[2] . '" target="_blank" rel="noopener noreferrer nofollow">' . $m[1] . '</a>',
    $safe
  );
  return nl2br($safe);
}

/**
 * Plain-text summary of a post, for meta descriptions, RSS, and previews.
 * Strips the Markdown rather than rendering it, and cuts on a word boundary.
 */
function post_excerpt(string $content, int $length = 200): string {
  $text = preg_replace('/`([^`\n]+)`/', '$1', $content) ?? $content;
  $text = preg_replace('/\*\*([^*\n]+)\*\*/', '$1', $text) ?? $text;
  $text = preg_replace('/(?<!\*)\*(?!\*)([^*\n]+)\*(?!\*)/', '$1', $text) ?? $text;
  $text = preg_replace('/\[([^\]\n]+)\]\([^)]*\)/', '$1', $text) ?? $text;
  $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

  if (mb_strlen($text) <= $length) {
    return $text;
  }
  $cut = mb_substr($text, 0, $length);
  $space = mb_strrpos($cut, ' ');
  return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
}
