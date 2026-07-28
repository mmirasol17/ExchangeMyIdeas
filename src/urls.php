<?php
/*
 * URL construction.
 *
 * Every public URL is built here, so the shape of the site's address space is
 * one file rather than a hundred string concatenations. Paths are root-absolute
 * ("/post/abc"), which is what a front controller makes possible and what stops
 * relative links from breaking at different nesting depths.
 *
 * THE SCHEME
 *   /                          feed, newest first
 *   /page/2                    feed, page 2
 *   /topic/ai                  posts tagged "ai"
 *   /topic/ai/page/2
 *   /search?q=...              search results (never indexed)
 *   /post/{id}                 a post and its replies
 *   /post/{id}/edit            edit your own post
 *   /new                       write a post
 *   /privacy  /feed.xml  /sitemap.xml  /ads.txt
 *
 * Sorting stays a query parameter on purpose: it is a view preference, not a
 * distinct document, and giving it a path would invite search engines to index
 * three URLs holding the same posts in a different order.
 */

/** The site's own hostname, used when a request does not supply a usable one. */
const SITE_HOST = 'exchangemyideas.marinmirasol.com';

/**
 * Absolute URL for a root-absolute path.
 *
 * Prefers the requested host so local development produces working links, but
 * only after checking it looks like a bare hostname. Host headers are attacker
 * controlled, and these URLs end up in canonical tags, OG metadata, and the RSS
 * feed, so an unvalidated one would let a visitor rewrite where those point.
 */
function site_url(string $path = '/'): string {
  $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
  if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
    $host = SITE_HOST;
  }
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

  return ($https ? 'https' : 'http') . '://' . $host . '/' . ltrim($path, '/');
}

/** Permalink for a post. */
function post_path(string $postId): string {
  return '/post/' . rawurlencode($postId);
}

/** Edit page for a post. */
function post_edit_path(string $postId): string {
  return post_path($postId) . '/edit';
}

/** A topic's filtered feed. */
function tag_path(string $slug, int $page = 1): string {
  $path = '/topic/' . rawurlencode($slug);
  return $page > 1 ? $path . '/page/' . $page : $path;
}

/**
 * Build a feed URL from a view state.
 *
 * @param array $state tag, search, page, sort
 */
function feed_path(array $state = []): string {
  $tag    = trim((string) ($state['tag'] ?? ''));
  $search = trim((string) ($state['search'] ?? ''));
  $page   = max(1, (int) ($state['page'] ?? 1));
  $sort   = (string) ($state['sort'] ?? 'recent');

  // Search is inherently a query, so it stays one -- and stays out of the index.
  if ($search !== '') {
    $query = ['q' => $search];
    if ($tag !== '')      $query['tag'] = $tag;
    if ($sort !== 'recent') $query['sort'] = $sort;
    if ($page > 1)        $query['page'] = $page;
    return '/search?' . http_build_query($query);
  }

  $path = $tag !== '' ? tag_path($tag, $page) : ($page > 1 ? '/page/' . $page : '/');

  return $sort !== 'recent' ? $path . '?sort=' . rawurlencode($sort) : $path;
}

/**
 * The feed view the current request is showing.
 *
 * The feed page records its state here once, so shared partials (the pager, the
 * sort tabs) can build links without every one of them re-parsing the request.
 */
function feed_state(?array $set = null): array {
  static $state = ['tag' => '', 'search' => '', 'page' => 1, 'sort' => 'recent'];
  if ($set !== null) {
    $state = array_merge($state, $set);
  }
  return $state;
}

/** A feed URL carrying the current view forward, changing only what is passed. */
function feed_url(array $overrides = []): string {
  return feed_path(array_merge(feed_state(), $overrides));
}

/** The fragment endpoint scroll-loading pulls pages from. */
function feed_partial_path(array $state = []): string {
  $state = array_merge(feed_state(), $state);
  $query = [];
  foreach (['tag', 'search', 'sort'] as $key) {
    if (($state[$key] ?? '') !== '' && !($key === 'sort' && $state[$key] === 'recent')) {
      $query[$key] = $state[$key];
    }
  }
  return '/partial/feed' . ($query ? '?' . http_build_query($query) : '');
}

/** Send a permanent redirect and stop. Used by every legacy URL. */
function redirect_permanent(string $path): never {
  header('Location: ' . $path, true, 301);
  exit;
}
