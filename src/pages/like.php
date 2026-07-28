<?php
/*
 * Likes endpoint. Serves POST /like.
 *
 * Increments a post's like count and returns the new total as JSON. Called by
 * fetch() from app.js. The like button only renders once the `likes` column
 * exists, so this is normally only reached after migration 001 has run - but it
 * checks anyway, since anyone can POST here directly.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');

$postId = trim($_POST['post_id'] ?? '');
if ($postId === '') {
  http_response_code(400);
  echo json_encode(['error' => 'missing post_id']);
  return;
}

if (!column_exists($conn, 'blog_posts', 'likes')) {
  http_response_code(409);
  echo json_encode(['error' => 'likes not enabled']);
  return;
}

try {
  $update = $conn->prepare('UPDATE blog_posts SET likes = likes + 1 WHERE post_id = ?');
  $update->execute([$postId]);

  if ($update->rowCount() === 0) {
    http_response_code(404);
    echo json_encode(['error' => 'post not found']);
    return;
  }

  $read = $conn->prepare('SELECT likes FROM blog_posts WHERE post_id = ?');
  $read->execute([$postId]);
  echo json_encode(['likes' => (int) $read->fetchColumn()]);
} catch (PDOException $e) {
  error_log('like failed: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode(['error' => 'server error']);
}
