<?php
/*
 * Layout partials - everything that writes markup.
 *
 * The post card itself lives in views/post_card.php because two callers need
 * it: the feed, and the fragment endpoint that scroll-loading appends from.
 * Rendering it in one place is what keeps a scrolled-in card identical to a
 * server-rendered one.
 *
 * All links are root-absolute ("/post/abc"). With a front controller the same
 * markup is served from many URL depths, so relative links would resolve
 * differently depending on how the reader arrived.
 */

require_once __DIR__ . '/views/post_card.php';

/**
 * A versioned URL for a static asset.
 *
 * .htaccess asks for a long cache lifetime on CSS and JS, which is right for
 * speed and wrong for deploys: a returning visitor would keep running the old
 * script against freshly rendered HTML until the cache expired. Stamping the
 * file's modification time into the query string means a changed file is a
 * changed URL, so an update is picked up immediately while unchanged files stay
 * cached.
 *
 * Caught this the honest way -- a stale editor.js in a test browser silently
 * ran the previous version of a just-deployed change.
 */
function asset(string $path): string {
  static $versions = [];

  if (!isset($versions[$path])) {
    $file = dirname(__DIR__) . $path;
    $versions[$path] = is_file($file) ? (string) filemtime($file) : '';
  }

  return $versions[$path] === '' ? $path : $path . '?v=' . $versions[$path];
}

/**
 * Renders the document head, opening body, and top navbar.
 *
 * @param string|string[] $script Page script filename(s) in assets/js.
 * @param array $meta description, canonical, image, type, noindex, jsonld,
 *                    prev, next, published, modified, author
 */
function render_head(string $title, string|array $script, array $meta = []): void {
  global $adsenseClient;

  $description = $meta['description']
    ?? "A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared.";
  $canonical = site_url($meta['canonical'] ?? request_path());
  $image = site_url($meta['image'] ?? '/assets/img/icons/android-chrome-512x512.png');
  $type  = $meta['type'] ?? 'website';
  ?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title><?= e($title) ?></title>
  <meta name="description" content="<?= e($description) ?>" />
  <?php if (!empty($meta['noindex'])): ?>
  <meta name="robots" content="noindex, nofollow" />
  <!-- The moderation queue carries its access key in the query string; never
       leak it in a Referer header. -->
  <meta name="referrer" content="no-referrer" />
  <?php else: ?>
  <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1" />
  <?php endif; ?>
  <link rel="canonical" href="<?= e($canonical) ?>" />
  <?php if (!empty($meta['prev'])): ?><link rel="prev" href="<?= e(site_url($meta['prev'])) ?>" /><?php endif; ?>
  <?php if (!empty($meta['next'])): ?><link rel="next" href="<?= e(site_url($meta['next'])) ?>" /><?php endif; ?>

  <meta property="og:site_name" content="ExchangeMyIdeas" />
  <meta property="og:title" content="<?= e($title) ?>" />
  <meta property="og:description" content="<?= e($description) ?>" />
  <meta property="og:type" content="<?= e($type) ?>" />
  <meta property="og:url" content="<?= e($canonical) ?>" />
  <meta property="og:image" content="<?= e($image) ?>" />
  <meta property="og:locale" content="en_US" />
  <?php if ($type === 'article'): ?>
  <meta property="article:published_time" content="<?= e((string) ($meta['published'] ?? '')) ?>" />
  <meta property="article:modified_time" content="<?= e((string) ($meta['modified'] ?? '')) ?>" />
  <meta property="article:author" content="<?= e((string) ($meta['author'] ?? '')) ?>" />
  <?php endif; ?>
  <meta name="twitter:card" content="summary" />
  <meta name="twitter:title" content="<?= e($title) ?>" />
  <meta name="twitter:description" content="<?= e($description) ?>" />
  <meta name="twitter:image" content="<?= e($image) ?>" />

  <link rel="icon" type="image/svg+xml" href="/assets/img/icons/favicon.svg" />
  <link rel="icon" type="image/png" sizes="32x32" href="/assets/img/icons/favicon-32x32.png" />
  <link rel="icon" type="image/png" sizes="16x16" href="/assets/img/icons/favicon-16x16.png" />
  <link rel="apple-touch-icon" sizes="180x180" href="/assets/img/icons/apple-touch-icon.png" />
  <link rel="manifest" href="/site.webmanifest" />
  <link rel="alternate" type="application/rss+xml" title="ExchangeMyIdeas" href="/feed.xml" />
  <meta name="theme-color" content="#1e3a8a" />
  <meta name="color-scheme" content="dark" />
  <link rel="stylesheet" href="<?= e(asset("/assets/css/styles.css")) ?>" />
  <?php if (!empty($adsenseClient)): ?>
  <link rel="preconnect" href="https://pagead2.googlesyndication.com" crossorigin />
  <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= e($adsenseClient) ?>" crossorigin="anonymous"></script>
  <?php endif; ?>
  <?php if (!empty($meta['jsonld'])): ?>
  <script type="application/ld+json"><?= jsonld_graph($meta['jsonld']) ?></script>
  <?php endif; ?>
  <!-- app.js carries what every page needs (toasts, sharing, likes, ownership).
       The rest add that page's own behaviour; a page may have none. -->
  <script src="<?= e(asset("/assets/js/app.js")) ?>" defer></script>
  <?php foreach (array_filter((array) $script) as $file): ?>
  <script src="<?= e(asset("/assets/js/" . $file)) ?>" defer></script>
  <?php endforeach; ?>
</head>

<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <nav class="navbar">
    <a class="brand" href="/">
      <img class="brand-icon" src="/assets/img/icons/favicon.svg" alt="" width="28" height="28" />
      <span class="brand-text">Exchange<span class="brand-accent">My</span>Ideas</span>
    </a>
    <div class="nav-actions">
      <a class="nav-post button" href="/new">
        <span aria-hidden="true">&#9997;</span> <span class="nav-post-text">Post</span>
      </a>
      <a class="home-link" href="https://marinmirasol.com" target="_blank" rel="noopener noreferrer">
        <span aria-hidden="true">&larr;</span> <span class="home-link-full">marinmirasol.com</span><span class="home-link-short">Portfolio</span>
      </a>
    </div>
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
      &middot; <a href="/privacy" class="footer-link">Privacy Policy</a>
      &middot; <a href="/feed.xml" class="footer-link">RSS</a>
    </div>
  </footer>
  <div id="toast" class="toast" role="status" aria-live="polite"></div>
</body>

</html>
  <?php
}

/** Visible breadcrumb trail. Pairs with the BreadcrumbList in the JSON-LD. */
function render_breadcrumbs(array $trail): void {
  if (!$trail) {
    return;
  }
  $last = count($trail) - 1;
  ?>
  <nav class="breadcrumb" aria-label="Breadcrumb">
    <?php foreach ($trail as $i => $crumb): ?>
      <?php if ($i === $last): ?>
        <span class="breadcrumb-current"><?= e($crumb['name']) ?></span>
      <?php else: ?>
        <a class="clear-search" href="<?= e($crumb['path']) ?>"><?= e($crumb['name']) ?></a>
        <span class="breadcrumb-sep" aria-hidden="true">/</span>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <?php
}

/**
 * An ad slot.
 *
 * Renders a real AdSense unit once $adsenseClient and $adsenseSlot are set in
 * config.local.php, and a labelled placeholder before then. See "Advertising"
 * in the README for the approval steps.
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

/** Topic chips, each with its computed icon. */
function render_tags(array $tags): void {
  if (!$tags) {
    return;
  }
  ?>
  <div class="tag-row">
    <?php foreach ($tags as $tag): ?>
      <a class="tag-chip" href="<?= e(tag_path($tag['slug'])) ?>" rel="tag">
        <span class="tag-emoji" aria-hidden="true"><?= tag_emoji($tag['slug']) ?></span><?= e($tag['label']) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php
}

/** Share button. JS upgrades it to the native share sheet where available. */
function render_share(string $url, string $title): void {
  ?>
  <button type="button" class="share-button" data-share-url="<?= e($url) ?>" data-share-title="<?= e($title) ?>">
    <span aria-hidden="true">&#128279;</span> Share
  </button>
  <?php
}

/**
 * Owner-only controls.
 *
 * Rendered hidden for everyone and revealed by JS only on the browser holding
 * this post's edit token, so the markup never claims who wrote what.
 */
function render_owner_actions(string $postId): void {
  ?>
  <div class="owner-actions" data-owner-for="<?= e($postId) ?>" hidden>
    <a class="owner-edit" href="<?= e(post_edit_path($postId)) ?>" rel="nofollow">
      <span aria-hidden="true">&#9998;</span> Edit
    </a>
  </div>
  <?php
}

/** "Under review" marker, shown on a flagged item so the state is not secret. */
function render_flag_badge(array $row): void {
  if (($row['status'] ?? 'visible') !== 'flagged') {
    return;
  }
  ?>
  <span class="flag-badge" title="Automatically flagged for review">
    <span aria-hidden="true">&#128681;</span> Under review
  </span>
  <?php
}

/**
 * Numbered pager. Collapses to a window around the current page.
 *
 * Kept even though the feed scroll-loads: it is the fallback when JavaScript is
 * unavailable, and it is the only way a crawler reaches older posts. Hiding it
 * is left to feed-scroll.js, and only after a page has actually loaded.
 */
function render_pager(int $page, int $pages): void {
  if ($pages < 2) {
    return;
  }
  $window = 2;
  $from = max(1, $page - $window);
  $to   = min($pages, $page + $window);
  ?>
  <nav class="pager" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a class="pager-link" href="<?= e(feed_url(['page' => $page - 1])) ?>" rel="prev">&larr; <span class="pager-word">Previous</span></a>
    <?php else: ?>
      <span class="pager-link disabled">&larr; <span class="pager-word">Previous</span></span>
    <?php endif; ?>

    <span class="pager-pages">
      <?php if ($from > 1): ?>
        <a class="pager-num" href="<?= e(feed_url(['page' => 1])) ?>">1</a>
        <?php if ($from > 2): ?><span class="pager-gap">&hellip;</span><?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $from; $i <= $to; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="pager-num active" aria-current="page"><?= $i ?></span>
        <?php else: ?>
          <a class="pager-num" href="<?= e(feed_url(['page' => $i])) ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>

      <?php if ($to < $pages): ?>
        <?php if ($to < $pages - 1): ?><span class="pager-gap">&hellip;</span><?php endif; ?>
        <a class="pager-num" href="<?= e(feed_url(['page' => $pages])) ?>"><?= $pages ?></a>
      <?php endif; ?>
    </span>

    <?php if ($page < $pages): ?>
      <a class="pager-link" href="<?= e(feed_url(['page' => $page + 1])) ?>" rel="next"><span class="pager-word">Next</span> &rarr;</a>
    <?php else: ?>
      <span class="pager-link disabled"><span class="pager-word">Next</span> &rarr;</span>
    <?php endif; ?>
  </nav>
  <?php
}

/** Sidebar: other apps, popular topics, an ad, and an about card. */
function render_sidebar(array $topics = []): void {
  $apps = [
    [
      'name' => 'Lifelyze',
      'tag'  => 'Your whole life, organized - calendar, budget, health, notes, and an AI assistant in one app.',
      'url'  => 'https://lifelyze.com',
      'icon' => '/assets/img/lifelyze.png',
    ],
    [
      'name' => 'Tunelyze',
      'tag'  => "Smarter Spotify playlists built from your library's audio features.",
      'url'  => 'https://tunelyze.com',
      'icon' => '/assets/img/tunelyze.png',
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

  <?php if ($topics): ?>
    <div class="sidebar-card">
      <div class="sidebar-title">Topics</div>
      <div class="tag-row">
        <?php foreach ($topics as $topic): ?>
          <a class="tag-chip" href="<?= e(tag_path($topic['slug'])) ?>" rel="tag">
            <span class="tag-emoji" aria-hidden="true"><?= tag_emoji($topic['slug']) ?></span><?= e($topic['label']) ?>
            <span class="tag-count"><?= (int) $topic['post_count'] ?></span>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="sidebar-note">Topics and their icons are worked out automatically from what each post is about.</p>
    </div>
  <?php endif; ?>

  <?php ad_slot(); ?>

  <div class="sidebar-card">
    <div class="sidebar-title">About this blog</div>
    <p class="sidebar-text">A minimalist place to post an idea, reply to others, and search everything shared. Originally a CSUSM class project, rebuilt and open-sourced.</p>
    <a class="sidebar-link" href="https://github.com/mmirasol17/ExchangeMyIdeas" target="_blank" rel="noopener noreferrer">View source on GitHub &rarr;</a>
  </div>
  <?php
}
