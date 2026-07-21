<?php
require_once("./config.php");

// Handle a reply submission, then redirect so a refresh doesn't repost.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $blog_post_id = trim($_POST['blog_post_id'] ?? '');
  $reply_content = trim($_POST['reply_content'] ?? '');
  $reply_author = trim($_POST['reply_author'] ?? '');
  $reply_author = $reply_author !== '' ? $reply_author : "Anonymous";

  if ($blog_post_id !== '' && $reply_content !== '') {
    $stmt = $conn->prepare("
      INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted)
      VALUES (uuid(), :blog_post_id, :reply_author, :reply_content, NOW())
    ");
    $stmt->execute([
      ':blog_post_id' => $blog_post_id,
      ':reply_author' => $reply_author,
      ':reply_content' => $reply_content,
    ]);
  }

  header("Location: ./index.php");
  exit;
}

$search = trim($_GET['search'] ?? '');

// Fetch posts, optionally filtered by the search term.
// Bound as a parameter -- the original built this by string concatenation,
// which allowed arbitrary SQL through the search box.
if ($search !== '') {
  $query = $conn->prepare("
    SELECT post_id, author_name, content, title, date_posted
    FROM blog_posts
    WHERE title LIKE :search
       OR content LIKE :search
       OR author_name LIKE :search
    ORDER BY date_posted DESC
  ");
  $query->execute([':search' => '%' . $search . '%']);
} else {
  $query = $conn->prepare("
    SELECT post_id, author_name, content, title, date_posted
    FROM blog_posts
    ORDER BY date_posted DESC
  ");
  $query->execute();
}

$posts = $query->fetchAll(PDO::FETCH_ASSOC);

// Replies for every post on the page, in one query instead of one per post.
$repliesByPost = [];
if ($posts) {
  $postIds = array_column($posts, 'post_id');
  $placeholders = implode(',', array_fill(0, count($postIds), '?'));
  $repliesStatement = $conn->prepare("
    SELECT blog_post_id, author_name, content, date_posted
    FROM blog_replies
    WHERE blog_post_id IN ($placeholders)
    ORDER BY date_posted ASC
  ");
  $repliesStatement->execute($postIds);

  foreach ($repliesStatement->fetchAll(PDO::FETCH_ASSOC) as $reply) {
    $repliesByPost[$reply['blog_post_id']][] = $reply;
  }
}

// Escape helper -- user content is untrusted and was previously echoed raw.
function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ExchangeMyIdeas</title>
  <meta name="description" content="A minimalistic blog where anyone can post an idea, reply to others, and search everything that's been shared." />
  <link rel="stylesheet" href="./styles.css" />
  <script src="./index.js" defer></script>
</head>

<body>
  <nav class="navbar">
    <a class="brand" href="./index.php">Exchange<span>My</span>Ideas</a>
    <a class="home-link" href="https://marinmirasol.com" target="_blank" rel="noopener noreferrer">&larr; marinmirasol.com</a>
  </nav>

  <div class="container">
    <h1 class="page-title">Ideas worth exchanging</h1>
    <div class="page-subtitle">Post a thought, reply to someone else's, or search everything shared so far.</div>

    <form class="header" method="get" action="./index.php">
      <input
        id="search"
        name="search"
        placeholder="Search for anything..."
        value="<?= e($search) ?>"
      />
      <input type="submit" />
      <button type="button" id="post" class="button">Post</button>
    </form>

    <?php if (!$posts): ?>
      <div class="empty-state">
        <?= $search !== '' ? 'No posts match &ldquo;' . e($search) . '&rdquo;.' : 'No posts yet &mdash; be the first to share an idea.' ?>
      </div>
    <?php endif; ?>

    <?php foreach ($posts as $post): ?>
      <div class="post" id="<?= e($post['post_id']) ?>">
        <div class="content">
          <div class="date"><?= e(date("d M Y", strtotime($post['date_posted']))) ?></div>
          <div class="title"><?= e($post['title']) ?></div>
          <div class="body"><?= nl2br(e($post['content'])) ?></div>
          <div class="footer">
            <div class="author"><span>👤 <?= e($post['author_name']) ?></span></div>
            <button type="button" class="reply-button button secondary">Reply</button>
          </div>
        </div>

        <div class="replies">
          <?php foreach ($repliesByPost[$post['post_id']] ?? [] as $reply): ?>
            <div class="reply">
              <div class="content">
                <div class="date"><?= e(date("d M Y", strtotime($reply['date_posted']))) ?></div>
                <div class="body"><?= nl2br(e($reply['content'])) ?></div>
                <div class="author"><span>👤 <?= e($reply['author_name']) ?></span></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <footer class="site-footer">
    <div class="developer">
      Developed by
      <a href="https://www.linkedin.com/in/marin-mirasol/" target="_blank" rel="noopener noreferrer" class="footer-link">Marin Mirasol</a>,
      <a href="https://www.linkedin.com/in/amer-yono/" target="_blank" rel="noopener noreferrer" class="footer-link">Amer (Junior) Yono</a>, and
      <a href="https://www.linkedin.com/in/corey-taylor-9a9bb1209/" target="_blank" rel="noopener noreferrer" class="footer-link">Corey Taylor</a>.
    </div>
    <div class="copy">&copy; <?= date("Y") ?> ExchangeMyIdeas</div>
  </footer>
</body>

</html>
