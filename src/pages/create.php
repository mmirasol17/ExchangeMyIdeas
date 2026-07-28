<?php
/*
 * New post form and insert. Serves /new.
 *
 * The id is generated up front with SELECT uuid() rather than inline in the
 * INSERT, because the page needs it afterwards - to attach tags, and to send
 * the author to their new post.
 */

$caps = site_caps($conn);

$error = '';
$draft = ['title' => '', 'content' => '', 'author' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Honeypot: real users leave this empty; bots tend to fill every field.
  if (trim($_POST['website'] ?? '') !== '') {
    header('Location: /', true, 302);
    exit;
  }

  $title   = trim($_POST['title'] ?? '');
  $content = trim($_POST['content'] ?? '');
  $author  = trim($_POST['author'] ?? '');
  $author  = $author !== '' ? $author : 'Anonymous';

  // Raw token minted by the browser (see create_blog.js). Only its hash is
  // stored, so the database never holds anything that grants edit rights.
  $editToken = trim($_POST['edit_token'] ?? '');

  $draft = [
    'title'   => $title,
    'content' => $content,
    'author'  => $_POST['author'] ?? '',
  ];

  if ($title === '' || $content === '') {
    $error = 'A title and some content are both required.';
  } elseif (mb_strlen($title) > 200) {
    $error = 'That title is too long - keep it under 200 characters.';
  } elseif (mb_strlen($content) > 20000) {
    $error = 'That post is too long - keep it under 20,000 characters.';
  } elseif (mb_strlen($author) > 60) {
    $error = 'That name is too long - keep it under 60 characters.';
  } else {
    $ipHash = $caps['moderation'] ? client_ip_hash() : null;

    if ($ipHash !== null && moderation_rate_limited($conn, 'blog_posts', $ipHash, 3, 300)) {
      $error = 'You have posted a few times just now. Give it a few minutes and try again.';
    } else {
      $verdict = moderate_content($title, $content, $author);

      if ($verdict['verdict'] === 'block') {
        $error = $verdict['message'];
      } else {
        try {
          $postId = (string) $conn->query('SELECT uuid()')->fetchColumn();

          $columns = ['post_id', 'author_name', 'content', 'title', 'date_posted'];
          $values  = [':post_id', ':author', ':content', ':title', 'NOW()'];
          $bind    = [
            ':post_id' => $postId,
            ':author'  => $author,
            ':content' => $content,
            ':title'   => $title,
          ];

          if ($caps['moderation']) {
            array_push($columns, 'status', 'flag_score', 'flag_reasons', 'ip_hash');
            array_push($values, ':status', ':score', ':reasons', ':ip');
            $bind[':status']  = $verdict['verdict'] === 'flag' ? 'flagged' : 'visible';
            $bind[':score']   = $verdict['score'];
            $bind[':reasons'] = implode(',', $verdict['reasons']);
            $bind[':ip']      = $ipHash;
          }

          if ($caps['editing'] && $editToken !== '') {
            $columns[] = 'edit_token';
            $values[]  = ':edit_token';
            $bind[':edit_token'] = hash('sha256', $editToken);
          }

          $stmt = $conn->prepare(
            'INSERT INTO blog_posts (' . implode(', ', $columns) . ')
             VALUES (' . implode(', ', $values) . ')'
          );
          $stmt->execute($bind);

          if ($caps['tags']) {
            sync_post_tags($conn, $postId, extract_tags($title, $content));
          }

          $query = '?new=1' . ($verdict['verdict'] === 'flag' ? '&flagged=1' : '');
          header('Location: ' . post_path($postId) . $query, true, 303);
          exit;
        } catch (PDOException $ex) {
          error_log('post insert failed: ' . $ex->getMessage());
          $error = 'Something went wrong saving that post. Try again.';
        }
      }
    }
  }
}

render_head('Share an idea - ExchangeMyIdeas', 'create_blog.js', [
  'description' => 'Write and share an idea on ExchangeMyIdeas. No account needed.',
  'canonical'   => '/new',
]);
?>

  <div class="container container-narrow">
    <?php render_breadcrumbs([
      ['name' => 'Home', 'path' => '/'],
      ['name' => 'Share an idea', 'path' => '/new'],
    ]); ?>

    <header class="hero">
      <h1 class="page-title">Share an idea</h1>
      <p class="page-subtitle">Write something worth exchanging.</p>
    </header>

    <?php if ($error !== ''): ?>
      <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form class="post-form" name="post" method="post" action="/new">
      <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true" />
      <input type="hidden" name="edit_token" id="edit-token" value="" />

      <label class="label" for="title">Title</label>
      <input
        id="title"
        class="field"
        name="title"
        maxlength="200"
        placeholder="Share your story in a few words"
        value="<?= e($draft['title']) ?>"
      />

      <label class="label" for="content">Content</label>
      <textarea
        id="content"
        class="field"
        name="content"
        maxlength="20000"
        placeholder="Talk about anything you'd like to share"
      ><?= e($draft['content']) ?></textarea>
      <div id="char-count" class="char-count">0 characters</div>

      <label class="label" for="author">Name (optional)</label>
      <input
        id="author"
        class="field"
        name="author"
        maxlength="60"
        placeholder="How would you like to be known?"
        value="<?= e($draft['author']) ?>"
      />

      <p class="form-hint">
        Supports <code>**bold**</code>, <code>*italic*</code>, <code>`code`</code>, and
        <code>[links](https://&hellip;)</code>. Topics are added automatically.
        <?php if ($caps['editing']): ?>
          You can edit or delete this post afterwards from this browser.
        <?php endif; ?>
      </p>

      <div class="form-actions">
        <button type="submit" class="button">Post Blog</button>
      </div>
    </form>
  </div>

<?php render_footer(); ?>
