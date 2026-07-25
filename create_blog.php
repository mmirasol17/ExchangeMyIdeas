<?php
require_once('lib.php');
require_once('config.php');

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

render_head('ExchangeMyIdeas — Write a Blog', 'create_blog.js');
?>

  <div class="container container-narrow">
    <header class="hero">
      <h1 class="page-title">Share an idea</h1>
      <p class="page-subtitle">Write something worth exchanging.</p>
    </header>

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
      <div id="char-count" class="char-count">0 characters</div>

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

<?php render_footer(); ?>
