<?php
/*
 * The feed: newest posts, a topic filter, search, sort, and pagination.
 *
 * Serves /, /page/{n}, /topic/{slug}, /topic/{slug}/page/{n}, and /search.
 * $params carries whatever the route captured.
 *
 * Pages are both scroll-loaded and linkable. The numbered pager is rendered
 * normally and works on its own; feed-scroll.js then appends the next page as
 * the reader nears the bottom. Nothing here depends on that happening - with
 * JavaScript off, or a crawler reading it, this is an ordinary paginated feed.
 */

$caps = site_caps($conn);
$isSearch = request_path() === '/search';

$search = $isSearch ? trim((string) ($_GET['q'] ?? '')) : '';
$tag    = trim((string) ($params['tag'] ?? ($_GET['tag'] ?? '')));
$page   = max(1, (int) ($params['page'] ?? ($_GET['page'] ?? 1)));

$sortOptions = posts_sort_options($conn);
$sort = isset($sortOptions[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : 'recent';

// Record the view so the pager and sort tabs can build links without each of
// them re-deriving it from the request.
feed_state(['tag' => $tag, 'search' => $search, 'page' => $page, 'sort' => $sort]);

$result = fetch_posts($conn, [
  'search' => $search,
  'tag'    => $tag,
  'sort'   => $sort,
  'page'   => $page,
]);

$posts = $result['posts'];

// A page number past the end is a dead URL, not a quiet fallback.
//
// fetch_posts() clamps an out-of-range page to the last real one, which is the
// right behaviour for its other callers but wrong here: it would let /page/999
// answer 200 with page 4's content, putting unlimited duplicate URLs in the
// index. Compare against what was *asked for*, before the clamp.
if ($page > 1 && $page > $result['pages']) {
  http_response_code(404);
  render_page('not_found');
  return;
}

$page = $result['page'];
feed_state(['page' => $page]);

$tagLabel = ($tag !== '' && $caps['tags']) ? tag_label($conn, $tag) : null;

// A topic nobody has written about does not exist as a page.
if ($tag !== '' && $tagLabel === null) {
  http_response_code(404);
  render_page('not_found');
  return;
}

$postIds = array_column($posts, 'post_id');
$repliesByPost = fetch_replies($conn, $postIds);
$tagsByPost = $caps['tags'] ? fetch_tags_for_posts($conn, $postIds) : [];
$topics = $caps['tags'] ? popular_tags($conn, 12, $caps['moderation']) : [];
$totalPosts = count_visible_posts($conn);

// ---------------------------------------------------------------------------
// Page metadata
// ---------------------------------------------------------------------------
$canonical = feed_path(['tag' => $tag, 'search' => $search, 'page' => $page, 'sort' => 'recent']);
$breadcrumbs = [['name' => 'Home', 'path' => '/']];

if ($isSearch) {
  $title = 'Search: ' . $search . ' - ExchangeMyIdeas';
  $description = 'Posts matching "' . $search . '" on ExchangeMyIdeas.';
} elseif ($tagLabel !== null) {
  $title = $tagLabel . ' - ExchangeMyIdeas';
  $description = 'Ideas about ' . $tagLabel . ' - ' . $result['total'] . ' post'
    . ($result['total'] === 1 ? '' : 's') . ' on ExchangeMyIdeas.';
  $breadcrumbs[] = ['name' => $tagLabel, 'path' => tag_path($tag)];
} else {
  $title = 'ExchangeMyIdeas - ideas worth exchanging';
  $description = "A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared.";
}

if ($page > 1) {
  $title = ($tagLabel !== null ? $tagLabel : 'ExchangeMyIdeas') . ' - page ' . $page;
  $description .= ' Page ' . $page . '.';
}

// Structured data. A paged view describes only what it lists, so each URL
// claims what it actually holds rather than the whole collection.
$graph = [jsonld_website(), jsonld_breadcrumbs($breadcrumbs)];
if (!$isSearch) {
  $graph[] = jsonld_collection(
    $tagLabel !== null ? 'Ideas about ' . $tagLabel : 'ExchangeMyIdeas',
    $description,
    $canonical,
    $posts
  );
}

render_head($title, ['index.js', 'feed-scroll.js'], [
  'description' => $description,
  'canonical'   => $canonical,
  // Search is an unbounded URL space with no content of its own. Paged feeds
  // ARE indexable with a self-canonical: marking them noindex would hide every
  // older post they are the only route to.
  'noindex'     => $isSearch,
  'prev'        => $page > 1 ? feed_path(['tag' => $tag, 'page' => $page - 1]) : null,
  'next'        => $page < $result['pages'] ? feed_path(['tag' => $tag, 'page' => $page + 1]) : null,
  'jsonld'      => $isSearch ? [] : $graph,
]);
?>

  <div class="container">
    <?php if ($tagLabel !== null): ?>
      <?php render_breadcrumbs($breadcrumbs); ?>
    <?php endif; ?>

    <header class="hero">
      <h1 class="page-title">
        <?php if ($isSearch): ?>
          Search results
        <?php elseif ($tagLabel !== null): ?>
          <span class="title-emoji" aria-hidden="true"><?= tag_emoji($tag) ?></span> Ideas about <?= e($tagLabel) ?>
        <?php else: ?>
          Ideas worth exchanging
        <?php endif; ?>
      </h1>
      <p class="page-subtitle">Post a thought, reply to someone else's, or search everything shared so far.</p>
      <?php if ($totalPosts > 0): ?>
        <div class="hero-stats">&#128161; <?= $totalPosts ?> idea<?= $totalPosts === 1 ? '' : 's' ?> shared</div>
      <?php endif; ?>
    </header>

    <form class="header" method="get" action="/search" role="search">
      <?php if ($tag !== ''): ?>
        <input type="hidden" name="tag" value="<?= e($tag) ?>" />
      <?php endif; ?>
      <input
        id="search"
        name="q"
        type="search"
        autocomplete="off"
        aria-label="Search posts"
        placeholder="Search for anything..."
        value="<?= e($search) ?>"
      />
      <button type="submit" class="button search-button">
        <span aria-hidden="true">&#128269;</span> <span class="search-button-text">Search</span>
      </button>
    </form>

    <?php if ($search !== '' || $tagLabel !== null): ?>
      <div class="search-status">
        <span>
          <?php if ($search !== ''): ?>
            Showing results for &ldquo;<?= e($search) ?>&rdquo;
          <?php else: ?>
            Tagged <strong><?= tag_emoji($tag) ?> <?= e($tagLabel) ?></strong>
          <?php endif; ?>
          &middot; <?= (int) $result['total'] ?> post<?= $result['total'] === 1 ? '' : 's' ?>
        </span>
        <a class="clear-search" href="/">Clear filters</a>
      </div>
    <?php endif; ?>

    <?php if ($result['total'] > 1): ?>
      <div class="toolbar">
        <div class="sort-control" role="tablist" aria-label="Sort posts">
          <?php foreach ($sortOptions as $key => $label): ?>
            <a
              class="sort-btn <?= $sort === $key ? 'active' : '' ?>"
              href="<?= e(feed_url(['sort' => $key, 'page' => 1])) ?>"
              <?= $key === 'recent' ? '' : 'rel="nofollow"' ?>
            ><?= e($label) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="layout">
      <main class="feed" id="main">
        <?php if (!$posts): ?>
          <div class="empty-state">
            <div class="empty-emoji" aria-hidden="true"><?= $search !== '' || $tag !== '' ? '&#128269;' : '&#127793;' ?></div>
            <?php if ($search !== '' || $tag !== ''): ?>
              Nothing matches that filter yet.
              <a class="clear-search" href="/">Show every post</a>
            <?php else: ?>
              No posts yet &mdash; <a class="clear-search" href="/new">be the first to share an idea</a>.
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!--
          Scroll-loaded pages are appended here. data-next-page is the cursor
          feed-scroll.js reads and rewrites; data-total-pages tells it when to
          stop asking.
        -->
        <div
          id="feed-items"
          data-next-page="<?= $page + 1 ?>"
          data-total-pages="<?= (int) $result['pages'] ?>"
          data-endpoint="<?= e(feed_partial_path()) ?>"
        >
          <?php render_post_cards($posts, $repliesByPost, $tagsByPost, $caps); ?>
        </div>

        <div id="feed-status" class="feed-status" role="status" aria-live="polite" hidden>
          <span class="feed-spinner" aria-hidden="true"></span> Loading more&hellip;
        </div>

        <!-- Hidden by feed-scroll.js only after a page has actually loaded. -->
        <div id="feed-pager">
          <?php render_pager($page, $result['pages']); ?>
        </div>

        <div id="feed-end" class="feed-end" hidden>&#127769; That's everything.</div>
      </main>

      <aside class="sidebar">
        <?php render_sidebar($topics); ?>
      </aside>
    </div>
  </div>

<?php render_footer(); ?>
