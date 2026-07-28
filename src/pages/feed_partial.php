<?php
/*
 * HTML fragment: one page of feed cards, for scroll-loading. Serves
 * /partial/feed?page=N.
 *
 * Returns the same markup the feed would have rendered for that page - no
 * JSON, no client-side templating. The cards come out of the one partial in
 * src/views/post_card.php, so a scrolled-in card cannot drift away from a
 * server-rendered one, and the browser has nothing to re-implement.
 *
 * Anything past the last page returns 204 No Content, the signal to stop asking.
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
// A page of the feed is cheap to regenerate and changes whenever someone posts.
header('Cache-Control: no-store');

$caps = site_caps($conn);

$page = max(1, (int) ($_GET['page'] ?? 1));
$sortOptions = posts_sort_options($conn);
$sort = isset($sortOptions[$_GET['sort'] ?? '']) ? (string) $_GET['sort'] : 'recent';

$result = fetch_posts($conn, [
  'search' => trim((string) ($_GET['search'] ?? '')),
  'tag'    => trim((string) ($_GET['tag'] ?? '')),
  'sort'   => $sort,
  'page'   => $page,
]);

// fetch_posts() clamps an out-of-range page back to the last one; without this
// check that would serve the final page over and over as the reader scrolls.
if (!$result['posts'] || $page > $result['pages']) {
  http_response_code(204);
  return;
}

$postIds = array_column($result['posts'], 'post_id');

render_post_cards(
  $result['posts'],
  fetch_replies($conn, $postIds),
  $caps['tags'] ? fetch_tags_for_posts($conn, $postIds) : [],
  $caps,
  // No in-feed ad here: one per page load is enough, and repeating it on every
  // scrolled page would turn a long feed into a column of ad units.
  null
);
