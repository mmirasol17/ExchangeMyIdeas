<?php
/*
 * The feed: search, topic filter, sort, and pagination.
 *
 * Pages are both scroll-loaded and linkable. The numbered pager is rendered
 * normally and works on its own; feed-scroll.js then hides it and appends the
 * next page as the reader nears the bottom, using the same fragment endpoint.
 * Nothing here depends on that happening - with JavaScript off, or a crawler
 * reading it, this is an ordinary paginated feed.
 */

require_once __DIR__ . '/src/bootstrap.php';

$caps = site_caps($conn);

$search = trim($_GET['search'] ?? '');
$tag    = trim($_GET['tag'] ?? '');
$page   = max(1, (int) ($_GET['page'] ?? 1));

$sortOptions = posts_sort_options($conn);
$sort = isset($sortOptions[$_GET['sort'] ?? '']) ? $_GET['sort'] : 'recent';

$result = fetch_posts($conn, [
  'search' => $search,
  'tag'    => $tag,
  'sort'   => $sort,
  'page'   => $page,
]);

$posts = $result['posts'];
$page  = $result['page'];

$postIds = array_column($posts, 'post_id');
$repliesByPost = fetch_replies($conn, $postIds);
$tagsByPost = $caps['tags'] ? fetch_tags_for_posts($conn, $postIds) : [];
$topics = $caps['tags'] ? popular_tags($conn, 12, $caps['moderation']) : [];

$totalPosts = count_visible_posts($conn);
$tagLabel = ($tag !== '' && $caps['tags']) ? tag_label($conn, $tag) : null;

// Title and description shift with the filter so a tag page is its own thing
// to a search engine rather than a duplicate of the feed.
$pageTitle = 'ExchangeMyIdeas';
$pageDescription = "A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared.";
if ($tagLabel !== null) {
  $pageTitle = $tagLabel . ' - ExchangeMyIdeas';
  $pageDescription = 'Ideas tagged ' . $tagLabel . ' on ExchangeMyIdeas.';
} elseif ($search !== '') {
  $pageTitle = 'Search: ' . $search . ' - ExchangeMyIdeas';
}
if ($page > 1) {
  $pageTitle .= ' (page ' . $page . ')';
}

render_head($pageTitle, ['index.js', 'feed-scroll.js'], [
  'description' => $pageDescription,
  'url'         => site_url(ltrim(feed_url(), './')),
  // A filtered or paged view is navigation, not content worth indexing twice.
  'noindex'     => $page > 1 || $search !== '',
]);
?>

  <div class="container">
    <header class="hero">
      <h1 class="page-title">
        <?php if ($tagLabel !== null): ?>
          <span class="title-emoji" aria-hidden="true"><?= tag_emoji($tag) ?></span> Ideas about <?= e($tagLabel) ?>
        <?php else: ?>
          Ideas worth exchanging
        <?php endif; ?>
      </h1>
      <p class="page-subtitle">Post a thought, reply to someone else's, or search everything shared so far.</p>
      <?php if ($totalPosts > 0): ?>
        <div class="hero-stats">💡 <?= $totalPosts ?> idea<?= $totalPosts === 1 ? '' : 's' ?> shared</div>
      <?php endif; ?>
    </header>

    <form class="header" method="get" action="./index.php" role="search">
      <?php if ($tag !== ''): ?>
        <input type="hidden" name="tag" value="<?= e($tag) ?>" />
      <?php endif; ?>
      <input
        id="search"
        name="search"
        type="search"
        autocomplete="off"
        aria-label="Search posts"
        placeholder="Search for anything..."
        value="<?= e($search) ?>"
      />
      <button type="submit" class="button search-button">
        <span aria-hidden="true">🔍</span> <span class="search-button-text">Search</span>
      </button>
    </form>

    <?php if ($search !== '' || $tagLabel !== null): ?>
      <div class="search-status">
        <span>
          <?php if ($tagLabel !== null): ?>
            Tagged <strong><?= tag_emoji($tag) ?> <?= e($tagLabel) ?></strong><?= $search !== '' ? ' matching &ldquo;' . e($search) . '&rdquo;' : '' ?>
          <?php else: ?>
            Showing results for &ldquo;<?= e($search) ?>&rdquo;
          <?php endif; ?>
          &middot; <?= (int) $result['total'] ?> post<?= $result['total'] === 1 ? '' : 's' ?>
        </span>
        <a class="clear-search" href="./index.php">Clear filters</a>
      </div>
    <?php endif; ?>

    <?php if ($result['total'] > 1): ?>
      <div class="toolbar">
        <div class="sort-control" role="tablist" aria-label="Sort posts">
          <?php foreach ($sortOptions as $key => $label): ?>
            <a
              class="sort-btn <?= $sort === $key ? 'active' : '' ?>"
              href="<?= e(feed_url(['sort' => $key, 'page' => 1])) ?>"
            ><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="layout">
      <main class="feed" id="main">
        <?php if (!$posts): ?>
          <div class="empty-state">
            <div class="empty-emoji" aria-hidden="true"><?= $search !== '' || $tag !== '' ? '🔍' : '🌱' ?></div>
            <?php if ($search !== '' || $tag !== ''): ?>
              Nothing matches that filter yet.
              <a class="clear-search" href="./index.php">Show every post</a>
            <?php else: ?>
              No posts yet &mdash; <a class="clear-search" href="./create_blog.php">be the first to share an idea</a>.
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!--
          Scroll-loaded pages are appended into this container. data-next-page is
          the cursor feed-scroll.js reads and rewrites; data-total-pages tells it
          when to stop asking.
        -->
        <div
          id="feed-items"
          data-next-page="<?= $page + 1 ?>"
          data-total-pages="<?= (int) $result['pages'] ?>"
          data-endpoint="<?= e(feed_url(['page' => null], './feed_more.php')) ?>"
        >
          <?php render_post_cards($posts, $repliesByPost, $tagsByPost, $caps); ?>
        </div>

        <div id="feed-status" class="feed-status" role="status" aria-live="polite" hidden>
          <span class="feed-spinner" aria-hidden="true"></span> Loading more…
        </div>

        <!-- Progressively hidden by feed-scroll.js, which takes over paging. -->
        <div id="feed-pager">
          <?php render_pager($page, $result['pages']); ?>
        </div>

        <div id="feed-end" class="feed-end" hidden>🌙 That's everything.</div>
      </main>

      <aside class="sidebar">
        <?php render_sidebar($topics); ?>
      </aside>
    </div>
  </div>

<?php render_footer(); ?>
