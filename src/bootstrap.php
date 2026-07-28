<?php
/*
 * Single require for the application's internals, in dependency order.
 *
 * index.php at the web root is the only caller. There is no autoloader because
 * there are no classes and no Composer on this host - an explicit, ordered list
 * is the honest version of the same thing, and it makes the dependency order
 * visible instead of implicit.
 */

// Pure helpers first: nothing below works without escaping and formatting.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/markdown.php';
require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/emoji.php';

// Domain logic.
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/tags.php';

// Database connection ($conn). Must precede posts.php, which queries on demand.
require_once __DIR__ . '/config.php';

// Apply any pending schema migrations before anything inspects the schema, so
// the request that triggers them still renders with the new features on.
// Costs one filesystem read when there is nothing to do. See migrator.php.
require_once __DIR__ . '/migrator.php';
migrations_auto_apply($conn);

require_once __DIR__ . '/posts.php';

// Presentation and routing last: both call everything above.
require_once __DIR__ . '/seo.php';
require_once __DIR__ . '/view.php';
require_once __DIR__ . '/router.php';
