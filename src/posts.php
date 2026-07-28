<?php
/*
 * Reading posts and replies.
 *
 * The feed, the permalink page, the RSS feed, the sitemap, and the moderation
 * queue all want the same rows under slightly different filters, so the query
 * lives here once instead of being re-typed (and diverging) in five places.
 *
 * FEATURE DETECTION
 *   site_caps() reports which optional columns and tables exist. Migrations are
 *   applied by hand in a browser on this host (see migrate.php), so a deploy
 *   always lands before its migration does. Every query is built from what the
 *   database actually has, which means new code on an old schema degrades to
 *   the old behaviour instead of throwing "unknown column" at every visitor.
 */

require_once __DIR__ . '/helpers.php';     // column_exists(), table_exists()
require_once __DIR__ . '/moderation.php';  // MODERATION_HIDE_FLAGGED
require_once __DIR__ . '/tags.php';        // delete_post_tags()

/** Posts per page in the feed. */
const POSTS_PER_PAGE = 10;

/** Replies shown inline on a feed card before it defers to the permalink. */
const FEED_REPLY_PREVIEW = 3;

/**
 * Which optional features this database currently supports.
 *
 * @return array{likes:bool,moderation:bool,tags:bool,editing:bool}
 */
function site_caps(PDO $conn): array {
  static $caps = null;
  if ($caps === null) {
    $caps = [
      'likes'      => column_exists($conn, 'blog_posts', 'likes'),       // migration 001
      'moderation' => column_exists($conn, 'blog_posts', 'status'),      // migration 002
      'tags'       => table_exists($conn, 'post_tags'),                  // migration 003
      'editing'    => column_exists($conn, 'blog_posts', 'edit_token'),  // migration 004
    ];
  }
  return $caps;
}

/**
 * Build the WHERE clause shared by the count and page queries.
 *
 * @return array{0:string,1:array<string,mixed>}
 */
function posts_filter(PDO $conn, array $opts): array {
  $caps = site_caps($conn);
  $where = [];
  $params = [];

  // Hidden posts never appear anywhere public.
  if ($caps['moderation'] && empty($opts['includeHidden'])) {
    $where[] = "p.status <> 'hidden'";
    if (MODERATION_HIDE_FLAGGED) {
      $where[] = "p.status <> 'flagged'";
    }
  }

  if (!empty($opts['search'])) {
    // Three separate placeholders holding the same value, deliberately.
    // With PDO::ATTR_EMULATE_PREPARES => false (see config.php) MySQL prepares
    // the statement server-side, and PDO cannot bind one named parameter to
    // several positions -- it fails with "Invalid parameter number" and the
    // search silently returns nothing.
    $where[] = '(p.title LIKE :s_title OR p.content LIKE :s_content OR p.author_name LIKE :s_author)';
    $needle = '%' . $opts['search'] . '%';
    $params[':s_title']   = $needle;
    $params[':s_content'] = $needle;
    $params[':s_author']  = $needle;
  }

  if (!empty($opts['tag']) && $caps['tags']) {
    $where[] = 'p.post_id IN (
      SELECT pt.post_id FROM post_tags pt
      JOIN tags t ON t.tag_id = pt.tag_id
      WHERE t.slug = :tag)';
    $params[':tag'] = $opts['tag'];
  }

  if (!empty($opts['postId'])) {
    $where[] = 'p.post_id = :pid';
    $params[':pid'] = $opts['postId'];
  }

  if (!empty($opts['status']) && $caps['moderation']) {
    $where[] = 'p.status = :status';
    $params[':status'] = $opts['status'];
  }

  return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

/** Sort options the feed offers, given what the schema supports. */
function posts_sort_options(PDO $conn): array {
  $caps = site_caps($conn);
  $sorts = ['recent' => 'Newest', 'discussed' => 'Most discussed'];
  if ($caps['likes']) {
    $sorts['liked'] = 'Most liked';
  }
  return $sorts;
}

/**
 * Fetch a page of posts.
 *
 * @return array{posts:array,total:int,pages:int,page:int}
 */
function fetch_posts(PDO $conn, array $opts = []): array {
  $caps = site_caps($conn);
  $perPage = max(1, (int) ($opts['perPage'] ?? POSTS_PER_PAGE));
  $page = max(1, (int) ($opts['page'] ?? 1));

  [$whereSql, $params] = posts_filter($conn, $opts);

  // Replies to hidden content should not inflate a post's reply count.
  $replyVisible = $caps['moderation'] ? " AND r.status <> 'hidden'" : '';
  $replyCount = "(SELECT COUNT(*) FROM blog_replies r
    WHERE r.blog_post_id = p.post_id$replyVisible) AS reply_count";

  $columns = [
    'p.post_id', 'p.author_name', 'p.content', 'p.title', 'p.date_posted',
    $caps['likes'] ? 'p.likes' : '0 AS likes',
    $caps['moderation'] ? 'p.status' : "'visible' AS status",
    $caps['moderation'] ? 'p.flag_score' : '0 AS flag_score',
    $caps['moderation'] ? 'p.flag_reasons' : "'' AS flag_reasons",
    $caps['editing'] ? 'p.edited_at' : 'NULL AS edited_at',
    $replyCount,
  ];

  try {
    $countStmt = $conn->prepare("SELECT COUNT(*) FROM blog_posts p $whereSql");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $orderBy = match ($opts['sort'] ?? 'recent') {
      'discussed' => 'reply_count DESC, p.date_posted DESC',
      'liked'     => $caps['likes'] ? 'p.likes DESC, p.date_posted DESC' : 'p.date_posted DESC',
      'oldest'    => 'p.date_posted ASC',
      'flagged'   => $caps['moderation'] ? 'p.flag_score DESC, p.date_posted DESC' : 'p.date_posted DESC',
      default     => 'p.date_posted DESC',
    };

    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // LIMIT and OFFSET are cast integers, not bound parameters: with prepare
    // emulation off, MySQL rejects a bound parameter in a LIMIT clause.
    $stmt = $conn->prepare(
      'SELECT ' . implode(', ', $columns) . "
       FROM blog_posts p
       $whereSql
       ORDER BY $orderBy
       LIMIT " . (int) $perPage . ' OFFSET ' . (int) $offset
    );
    $stmt->execute($params);

    return [
      'posts' => $stmt->fetchAll(PDO::FETCH_ASSOC),
      'total' => $total,
      'pages' => $pages,
      'page'  => $page,
    ];
  } catch (PDOException $e) {
    error_log('fetch_posts failed: ' . $e->getMessage());
    return ['posts' => [], 'total' => 0, 'pages' => 1, 'page' => 1];
  }
}

/** A single post by id, or null. Returns hidden posts only when asked. */
function find_post(PDO $conn, string $postId, bool $includeHidden = false): ?array {
  if ($postId === '') {
    return null;
  }
  $result = fetch_posts($conn, [
    'postId'        => $postId,
    'perPage'       => 1,
    'includeHidden' => $includeHidden,
  ]);
  return $result['posts'][0] ?? null;
}

/**
 * Replies for a set of posts, oldest first.
 *
 * @return array<string, array> Keyed by blog_post_id.
 */
function fetch_replies(PDO $conn, array $postIds, bool $includeHidden = false): array {
  if (!$postIds) {
    return [];
  }
  $caps = site_caps($conn);

  $columns = ['reply_id', 'blog_post_id', 'author_name', 'content', 'date_posted'];
  $columns[] = $caps['moderation'] ? 'status' : "'visible' AS status";
  $columns[] = $caps['moderation'] ? 'flag_score' : '0 AS flag_score';
  $columns[] = $caps['moderation'] ? 'flag_reasons' : "'' AS flag_reasons";

  $visible = ($caps['moderation'] && !$includeHidden) ? " AND status <> 'hidden'" : '';

  try {
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $conn->prepare(
      'SELECT ' . implode(', ', $columns) . "
       FROM blog_replies
       WHERE blog_post_id IN ($placeholders)$visible
       ORDER BY date_posted ASC"
    );
    $stmt->execute(array_values($postIds));

    $byPost = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $reply) {
      $byPost[$reply['blog_post_id']][] = $reply;
    }
    return $byPost;
  } catch (PDOException $e) {
    error_log('fetch_replies failed: ' . $e->getMessage());
    return [];
  }
}

/** Total number of publicly visible posts, for the hero counter. */
function count_visible_posts(PDO $conn): int {
  $caps = site_caps($conn);
  try {
    $sql = 'SELECT COUNT(*) FROM blog_posts p'
      . ($caps['moderation'] ? " WHERE p.status <> 'hidden'" : '');
    return (int) $conn->query($sql)->fetchColumn();
  } catch (PDOException $e) {
    return 0;
  }
}

/** Delete a post and everything hanging off it. */
function delete_post(PDO $conn, string $postId): bool {
  try {
    // blog_replies cascades via its foreign key; post_tags has none by design
    // (see migration 003), so it is cleaned up explicitly.
    if (site_caps($conn)['tags']) {
      delete_post_tags($conn, $postId);
    }
    $stmt = $conn->prepare('DELETE FROM blog_posts WHERE post_id = ?');
    $stmt->execute([$postId]);
    return $stmt->rowCount() > 0;
  } catch (PDOException $e) {
    error_log('delete_post failed: ' . $e->getMessage());
    return false;
  }
}
