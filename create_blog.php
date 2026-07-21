<?php
require_once("./config.php");

$error = '';

// Handle a new post, then redirect so a refresh doesn't repost.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $title = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $author = trim($_POST['author'] ?? '');
  $author = $author !== '' ? $author : "Anonymous";

  if ($title === '' || $content === '') {
    $error = 'A title and some content are both required.';
  } else {
    // Prepared statement -- the original concatenated these straight into the
    // INSERT, so any apostrophe broke the query and any SQL ran.
    $stmt = $conn->prepare("
      INSERT INTO blog_posts (post_id, author_name, content, title, date_posted)
      VALUES (uuid(), :author, :content, :title, NOW())
    ");
    $stmt->execute([
      ':author' => $author,
      ':content' => $content,
      ':title' => $title,
    ]);

    header("Location: ./index.php");
    exit;
  }
}

function e(string $value): string {
  return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ExchangeMyIdeas — Write a Blog</title>
  <link rel="stylesheet" href="./styles.css" />
  <script src="./create_blog.js" defer></script>
</head>

<body>
  <nav class="navbar">
    <a class="brand" href="./index.php">Exchange<span>My</span>Ideas</a>
    <a class="home-link" href="https://marinmirasol.com" target="_blank" rel="noopener noreferrer">&larr; marinmirasol.com</a>
  </nav>

  <div class="container">
    <h1 class="page-title">Share an idea</h1>
    <div class="page-subtitle">Write something worth exchanging.</div>

    <div class="header">
      <button type="button" id="go-to-posts" class="button secondary">&larr; Go back to posts</button>
    </div>

    <?php if ($error !== ''): ?>
      <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="post-form" name="post" method="post" action="./create_blog.php">
      <div class="label">Title</div>
      <input
        class="field"
        name="title"
        placeholder="Share your story in a few words 📖"
        value="<?= e($_POST['title'] ?? '') ?>"
      />

      <div class="label">Content</div>
      <textarea
        class="field"
        name="content"
        placeholder="Talk about anything you'd like to share 💡"
      ><?= e($_POST['content'] ?? '') ?></textarea>

      <div class="label">Name (optional)</div>
      <input
        class="field"
        name="author"
        placeholder="How would you like to be known? 👤"
        value="<?= e($_POST['author'] ?? '') ?>"
      />

      <input type="submit" />
      <button type="submit" class="button">Post Blog</button>
    </form>
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
