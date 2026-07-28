<?php
/*
 * Front controller - the only PHP file a browser can address directly.
 *
 * Everything else lives in src/, which the web server is told to deny. Requests
 * arrive here either because mod_rewrite sent them (the normal path) or because
 * Apache 404'd and ErrorDocument redirected them (the fallback for hosts
 * without mod_rewrite). Either way the router sees the original URL.
 */

/*
 * PHP's built-in server has no .htaccess and no rewrite rules. Used as its
 * router script (`php -S localhost:8000 index.php`) this file receives every
 * request, so it hands real files back to the server and routes the rest -
 * making local development behave exactly like Apache in production.
 */
if (PHP_SAPI === 'cli-server') {
  $file = __DIR__ . urldecode((string) parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH));
  if (is_file($file)) {
    return false;
  }
}

require __DIR__ . '/src/bootstrap.php';

dispatch();
