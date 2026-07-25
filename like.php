<?php
/*
 * Likes endpoint. Increments a post's like count and returns the new total.
 * Called via fetch() from index.js. The like button only renders once the
 * `likes` column exists (see column_exists() in lib.php), so this is only
 * reached after migration 001 has run.
 */

require_once('lib.php');
require_once('config.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['error' => 'method not allowed']);
  exit;
}

$postId = trim($_POST['post_id'] ?? '');
if ($postId === '') {
  http_response_code(400);
  echo json_encode(['error' => 'missing post_id']);
  exit;
}

if (!column_exists($conn, 'blog_posts', 'likes')) {
  http_response_code(409);
  echo json_encode(['error' => 'likes not enabled']);
  exit;
}

try {
  $update = $conn->prepare("UPDATE blog_posts SET likes = likes + 1 WHERE post_id = ?");
  $update->execute([$postId]);

  if ($update->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'post not found']);
    exit;
  }

  $read = $conn->prepare("SELECT likes FROM blog_posts WHERE post_id = ?");
  $read->execute([$postId]);
  echo json_encode(['likes' => (int) $read->fetchColumn()]);
} catch (PDOException $e) {
  error_log('like.php failed: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server error']);
}
