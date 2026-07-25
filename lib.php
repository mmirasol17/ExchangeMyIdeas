<?php
/*
 * Shared helpers and layout partials for ExchangeMyIdeas.
 * Included by index.php and create_blog.php.
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
 * depend on a migration (e.g. likes) degrade gracefully until it has run, so a
 * deploy can never reference a not-yet-created column and break the site.
 * Table name is a trusted constant, never user input.
 */
function column_exists(PDO $conn, string $table, string $column): bool {
  static $cache = [];
  $key = "$table.$column";
  if (array_key_exists($key, $cache)) {
    return $cache[$key];
  }
  try {
    $stmt = $conn->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    $stmt->execute([$column]);
    return $cache[$key] = (bool) $stmt->fetch();
  } catch (PDOException $e) {
    return $cache[$key] = false;
  }
}

/**
 * Renders a safe, minimal subset of Markdown: **bold**, *italic*, `code`, and
 * [text](https://url) links. Input is HTML-escaped FIRST, so user content can
 * never inject markup — the formatting is applied to already-safe text.
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

/** Renders the document head, opening body, and top navbar. */
function render_head(string $title, string $script): void {
  global $adsenseClient;
  ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= e($title) ?></title>
  <meta name="description" content="A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared." />
  <meta property="og:title" content="<?= e($title) ?>" />
  <meta property="og:description" content="Ideas worth exchanging — post a thought, reply, and search." />
  <meta property="og:type" content="website" />
  <link rel="icon" type="image/svg+xml" href="./favicon.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="./favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="./favicon-16x16.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="./apple-touch-icon.png" />
  <link rel="manifest" href="./site.webmanifest" />
  <meta name="theme-color" content="#1e3a8a" />
  <link rel="stylesheet" href="./styles.css" />
  <?php if (!empty($adsenseClient)): ?>
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e($adsenseClient) ?>" crossorigin="anonymous"></script>
  <?php endif; ?>
  <script src="./<?= e($script) ?>" defer></script>
</head>

<body>
  <nav class="navbar">
    <a class="brand" href="./index.php">
      <img class="brand-icon" src="./favicon.svg" alt="" width="28" height="28" />
      <span class="brand-text">Exchange<span class="brand-accent">My</span>Ideas</span>
    </a>
    <a class="home-link" href="https://marinmirasol.com" target="_blank" rel="noopener noreferrer">&larr; marinmirasol.com</a>
  </nav>
  <?php
}

/** Renders the site footer and closes the document. */
function render_footer(): void {
  ?>
  <footer class="site-footer">
    <div class="developer">
      Developed by
      <a href="https://www.linkedin.com/in/marin-mirasol/" target="_blank" rel="noopener noreferrer" class="footer-link">Marin Mirasol</a>,
      <a href="https://www.linkedin.com/in/amer-yono/" target="_blank" rel="noopener noreferrer" class="footer-link">Amer (Junior) Yono</a>, and
      <a href="https://www.linkedin.com/in/corey-taylor-9a9bb1209/" target="_blank" rel="noopener noreferrer" class="footer-link">Corey Taylor</a>.
    </div>
    <div class="copy">
      &copy; <?= date('Y') ?> ExchangeMyIdeas
      &middot; <a href="./privacy.php" class="footer-link">Privacy Policy</a>
    </div>
  </footer>
</body>

</html>
  <?php
}

/**
 * An ad slot. Renders as a labeled placeholder until you drop in a real ad.
 * To activate Google AdSense: enable the loader script in render_head(), then
 * replace the .ad-placeholder div below with your <ins class="adsbygoogle">.
 */
function ad_slot(): void {
  global $adsenseClient, $adsenseSlot;
  ?>
  <div class="ad-slot" role="complementary" aria-label="Advertisement">
    <span class="ad-label">Advertisement</span>
    <?php if (!empty($adsenseClient) && !empty($adsenseSlot)): ?>
      <ins class="adsbygoogle"
        style="display:block"
        data-ad-client="<?= e($adsenseClient) ?>"
        data-ad-slot="<?= e($adsenseSlot) ?>"
        data-ad-format="auto"
        data-full-width-responsive="true"></ins>
      <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
    <?php else: ?>
      <div class="ad-placeholder">Your ad could be here</div>
    <?php endif; ?>
  </div>
  <?php
}

/** Sidebar cross-promoting Marin's other apps + portfolio link. */
function render_sidebar(): void {
  $apps = [
    [
      'name' => 'Lifelyze',
      'tag'  => 'Your whole life, organized — calendar, budget, health, notes, and an AI assistant in one app.',
      'url'  => 'https://lifelyze.com',
      'icon' => './app-icons/lifelyze.png',
    ],
    [
      'name' => 'Tunelyze',
      'tag'  => "Smarter Spotify playlists built from your library's audio features.",
      'url'  => 'https://tunelyze.com',
      'icon' => './app-icons/tunelyze.png',
    ],
  ];
  ?>
  <div class="sidebar-card">
    <div class="sidebar-title">More apps</div>
    <?php foreach ($apps as $app): ?>
      <a class="app-item" href="<?= e($app['url']) ?>" target="_blank" rel="noopener noreferrer">
        <img class="app-icon" src="<?= e($app['icon']) ?>" alt="<?= e($app['name']) ?> icon" width="40" height="40" loading="lazy" />
        <span class="app-info">
          <span class="app-name"><?= e($app['name']) ?> <span class="app-arrow">&rarr;</span></span>
          <span class="app-tag"><?= e($app['tag']) ?></span>
        </span>
      </a>
    <?php endforeach; ?>
    <a class="app-all" href="https://marinmirasol.com/#projects" target="_blank" rel="noopener noreferrer">See all my projects &rarr;</a>
  </div>

  <?php ad_slot(); ?>

  <div class="sidebar-card">
    <div class="sidebar-title">About this blog</div>
    <p class="sidebar-text">A minimalist place to post an idea, reply to others, and search everything shared. Originally a CSUSM class project, rebuilt and open-sourced.</p>
    <a class="sidebar-link" href="https://github.com/mmirasol17/ExchangeMyIdeas" target="_blank" rel="noopener noreferrer">View source on GitHub &rarr;</a>
  </div>
  <?php
}
