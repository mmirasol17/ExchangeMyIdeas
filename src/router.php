<?php
/*
 * Request routing.
 *
 * One entry point (index.php at the web root) hands every request here, and
 * this file decides which page in src/pages/ answers it. That is what lets the
 * application live outside the document root: the only PHP file a browser can
 * address directly is the front controller.
 *
 * HOW A REQUEST REACHES US
 *   Two independent mechanisms, because shared hosting is not guaranteed:
 *
 *     1. mod_rewrite sends anything that is not a real file to index.php.
 *     2. If mod_rewrite is unavailable, Apache 404s and ErrorDocument sends the
 *        request to index.php instead. REQUEST_URI still holds the original
 *        path, so routing is identical -- only the status needs resetting,
 *        which reset_error_document_status() handles.
 *
 *   The site therefore works with or without rewrites. Both paths are covered
 *   by the Apache tests in tests/.
 *
 * LEGACY URLS
 *   Every pre-front-controller URL (/index.php, /post.php?id=…) 301s to its
 *   modern equivalent rather than 404ing, so links already shared or indexed
 *   keep working and pass their ranking on.
 */

/**
 * Start every request from a clean 200.
 *
 * This matters only on the ErrorDocument fallback path, where Apache has
 * already decided the response is a 404 before PHP runs.
 *
 * MEASURED LIMITATION
 *   Apache 2.4 behind mod_proxy_fcgi ignores a Status header from an
 *   ErrorDocument handler and sends its original 404 anyway -- verified, not
 *   assumed. So without mod_rewrite the site still serves entirely correct
 *   pages, but every one of them carries a 404 status. A human sees a working
 *   site; a crawler sees a site that does not exist.
 *
 *   That makes mod_rewrite the supported configuration and this the safety net,
 *   not an equal alternative. The README says how to confirm rewrites are live
 *   in about ten seconds. The call is kept because it costs nothing and does
 *   fix the status on servers that do honour it.
 *
 * Any page that genuinely means 404, 403, or 503 sets it afterwards, and wins.
 */
function reset_error_document_status(): void {
  http_response_code(200);
}

/** The request path, decoded per segment so an encoded slash cannot forge one. */
function request_path(): string {
  $raw = (string) ($_SERVER['REQUEST_URI'] ?? '/');
  $path = parse_url($raw, PHP_URL_PATH);
  if (!is_string($path) || $path === '') {
    $path = '/';
  }
  $segments = array_map('rawurldecode', explode('/', $path));
  return implode('/', $segments);
}

/**
 * The route table.
 *
 * Each entry is [methods, pattern, page, capture names]. Patterns are anchored
 * and deliberately strict -- a post id is a MySQL uuid() and nothing else, so a
 * malformed one 404s here rather than reaching a query.
 */
function routes(): array {
  $uuid = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
  $slug = '[a-z0-9][a-z0-9-]{0,49}';

  return [
    ['GET',       '#^/$#',                                  'feed',         []],
    ['GET',       '#^/page/([1-9]\d{0,5})$#',               'feed',         ['page']],
    ['GET',       "#^/topic/($slug)$#",                     'feed',         ['tag']],
    ['GET',       "#^/topic/($slug)/page/([1-9]\d{0,5})$#", 'feed',         ['tag', 'page']],
    ['GET',       '#^/search$#',                            'feed',         []],
    ['GET',       '#^/partial/feed$#',                      'feed_partial', []],

    ['GET|POST',  "#^/post/($uuid)$#",                      'post',         ['id']],
    ['GET|POST',  "#^/post/($uuid)/edit$#",                 'edit',         ['id']],
    ['GET|POST',  '#^/new$#',                               'create',       []],
    ['POST',      '#^/like$#',                              'like',         []],

    ['GET',       '#^/privacy$#',                           'privacy',      []],
    ['GET',       '#^/feed\.xml$#',                         'rss',          []],
    ['GET',       '#^/sitemap\.xml$#',                      'sitemap',      []],
    ['GET',       '#^/ads\.txt$#',                          'ads',          []],

    ['GET|POST',  '#^/moderate$#',                          'moderate',     []],
    ['GET',       '#^/migrate$#',                           'migrate',      []],
  ];
}

/**
 * Redirect the old .php URLs to their replacements.
 *
 * Returns true if it handled (and ended) the request.
 */
function handle_legacy_url(string $path): bool {
  $id   = trim((string) ($_GET['id'] ?? ''));
  $key  = trim((string) ($_GET['key'] ?? ''));
  $keyQ = $key !== '' ? '?key=' . rawurlencode($key) : '';

  switch ($path) {
    case '/index.php':
      redirect_permanent(feed_path([
        'tag'    => trim((string) ($_GET['tag'] ?? '')),
        'search' => trim((string) ($_GET['search'] ?? '')),
        'sort'   => trim((string) ($_GET['sort'] ?? 'recent')) ?: 'recent',
        'page'   => (int) ($_GET['page'] ?? 1),
      ]));

    case '/post.php':
      redirect_permanent($id !== '' ? post_path($id) : '/');

    case '/edit_post.php':
      redirect_permanent($id !== '' ? post_edit_path($id) : '/');

    case '/create_blog.php':
      redirect_permanent('/new');

    case '/privacy.php':
      redirect_permanent('/privacy');

    case '/feed.php':
      redirect_permanent('/feed.xml');

    case '/sitemap.php':
      redirect_permanent('/sitemap.xml');

    case '/ads.php':
      redirect_permanent('/ads.txt');

    case '/feed_more.php':
      redirect_permanent('/partial/feed');

    case '/moderate.php':
      redirect_permanent('/moderate' . $keyQ);

    case '/migrate.php':
      redirect_permanent('/migrate' . $keyQ);

    case '/like.php':
      // 308 rather than 301: a 301 lets a browser downgrade POST to GET, which
      // would silently turn a like into a method-not-allowed.
      header('Location: /like', true, 308);
      exit;
  }

  return false;
}

/** Render a page from src/pages, with $params in scope. */
function render_page(string $page, array $params = []): void {
  global $conn;
  $file = __DIR__ . '/pages/' . $page . '.php';
  if (!is_file($file)) {
    http_response_code(500);
    error_log("missing page: $page");
    exit('Internal error.');
  }
  require $file;
}

/** Match the request against the route table and hand off. */
function dispatch(): void {
  reset_error_document_status();

  $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
  $path = request_path();

  if (handle_legacy_url($path)) {
    return;
  }

  // One canonical spelling per page: "/post/x/" and "/post/x" must not both
  // serve content, or search engines index the same post twice.
  if ($path !== '/' && str_ends_with($path, '/')) {
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    redirect_permanent(rtrim($path, '/') . ($query !== '' ? '?' . $query : ''));
  }

  foreach (routes() as [$methods, $pattern, $page, $captures]) {
    if (!preg_match($pattern, $path, $matches)) {
      continue;
    }
    if (!in_array($method, explode('|', $methods), true)) {
      http_response_code(405);
      header('Allow: ' . str_replace('|', ', ', $methods));
      exit('Method not allowed.');
    }

    $params = [];
    foreach ($captures as $i => $name) {
      $params[$name] = $matches[$i + 1] ?? null;
    }
    render_page($page, $params);
    return;
  }

  http_response_code(404);
  render_page('not_found');
}
