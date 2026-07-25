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

/** Renders the document head, opening body, and top navbar. */
function render_head(string $title, string $script): void {
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
  <!--
    Google AdSense: after you're approved at https://www.google.com/adsense,
    uncomment this and swap in your publisher ID (ca-pub-XXXXXXXXXXXXXXXX).
    Then replace the placeholders inside ad_slot() with your real <ins> unit.
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-XXXXXXXXXXXXXXXX" crossorigin="anonymous"></script>
  -->
  <script src="./<?= e($script) ?>" defer></script>
</head>

<body>
  <nav class="navbar">
    <a class="brand" href="./index.php">Exchange<span>My</span>Ideas</a>
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
    <div class="copy">&copy; <?= date('Y') ?> ExchangeMyIdeas</div>
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
  ?>
  <div class="ad-slot" role="complementary" aria-label="Advertisement">
    <span class="ad-label">Advertisement</span>
    <div class="ad-placeholder">Your ad could be here</div>
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
      'mono' => 'L',
      'grad' => 'linear-gradient(135deg, #3b82f6, #22c55e)',
    ],
    [
      'name' => 'Tunelyze',
      'tag'  => "Smarter Spotify playlists built from your library's audio features.",
      'url'  => 'https://tunelyze.com',
      'mono' => 'T',
      'grad' => 'linear-gradient(135deg, #22c55e, #0ea5e9)',
    ],
  ];
  ?>
  <div class="sidebar-card">
    <div class="sidebar-title">More from Marin</div>
    <?php foreach ($apps as $app): ?>
      <a class="app-item" href="<?= e($app['url']) ?>" target="_blank" rel="noopener noreferrer">
        <span class="app-mono" style="background: <?= e($app['grad']) ?>"><?= e($app['mono']) ?></span>
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
