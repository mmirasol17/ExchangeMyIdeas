<?php
/*
 * Edit or delete your own post, without an account. Serves /post/{id}/edit.
 *
 * HOW OWNERSHIP WORKS
 *   When a post is created the browser mints a random token, keeps it in
 *   localStorage, and sends it along. The server stores only its SHA-256 hash
 *   (migration 004). To edit, the browser sends the raw token back and the
 *   server compares hashes.
 *
 *   So "logged in" here means "this is the browser that wrote the post". That
 *   is the honest limit of anonymous posting: clear the browser data and the
 *   post becomes uneditable, which is the trade for not collecting anything.
 *   It is stated plainly on the page rather than hidden.
 *
 *   The comparison uses hash_equals() to keep it constant-time, and posts from
 *   before this feature existed have no token at all and cannot be claimed.
 */

$caps = site_caps($conn);
$postId = (string) ($params['id'] ?? '');

$error = '';
$draft = null;

/** Constant-time check that a raw token matches the stored hash. */
function owns_post(?string $storedHash, string $rawToken): bool {
  if ($storedHash === null || $storedHash === '' || $rawToken === '') {
    return false;
  }
  return hash_equals($storedHash, hash('sha256', $rawToken));
}

if (!$caps['editing']) {
  http_response_code(503);
  render_head('Editing unavailable - ExchangeMyIdeas', '', ['noindex' => true]);
  ?>
  <div class="container container-narrow">
    <div class="empty-state">
      <h1 class="page-title">Editing is not enabled yet</h1>
      <p class="sidebar-text">Migration 004 has not been applied to this database.</p>
      <a class="button" href="/">&larr; Back to posts</a>
    </div>
  </div>
  <?php
  render_footer();
  return;
}

// ---------------------------------------------------------------------------
// Save or delete
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $token  = trim($_POST['edit_token'] ?? '');
  $action = $_POST['action'] ?? 'save';

  $stmt = $conn->prepare('SELECT edit_token FROM blog_posts WHERE post_id = ?');
  $stmt->execute([$postId]);
  $storedHash = $stmt->fetchColumn();

  if ($storedHash === false) {
    $error = 'That post no longer exists.';
  } elseif (!owns_post($storedHash === null ? null : (string) $storedHash, $token)) {
    http_response_code(403);
    $error = 'This browser does not hold the edit key for that post.';
  } elseif ($action === 'delete') {
    if (delete_post($conn, $postId)) {
      header('Location: /?deleted=' . rawurlencode($postId), true, 303);
      exit;
    }
    $error = 'Could not delete that post. Try again.';
  } else {
    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $draft = ['title' => $title, 'content' => $content];

    if ($title === '' || $content === '') {
      $error = 'A title and some content are both required.';
    } elseif (mb_strlen($title) > 200) {
      $error = 'That title is too long - keep it under 200 characters.';
    } elseif (mb_strlen($content) > 20000) {
      $error = 'That post is too long - keep it under 20,000 characters.';
    } else {
      // An edit is moderated exactly like a new post. Otherwise a clean post
      // could be published and then edited into anything.
      $verdict = moderate_content($title, $content);

      if ($verdict['verdict'] === 'block') {
        $error = $verdict['message'];
      } else {
        try {
          if ($caps['moderation']) {
            $update = $conn->prepare('
              UPDATE blog_posts
              SET title = :title, content = :content, edited_at = NOW(),
                  status = :status, flag_score = :score, flag_reasons = :reasons
              WHERE post_id = :post_id');
            $update->execute([
              ':title'   => $title,
              ':content' => $content,
              ':status'  => $verdict['verdict'] === 'flag' ? 'flagged' : 'visible',
              ':score'   => $verdict['score'],
              ':reasons' => implode(',', $verdict['reasons']),
              ':post_id' => $postId,
            ]);
          } else {
            $update = $conn->prepare('
              UPDATE blog_posts
              SET title = :title, content = :content, edited_at = NOW()
              WHERE post_id = :post_id');
            $update->execute([
              ':title'   => $title,
              ':content' => $content,
              ':post_id' => $postId,
            ]);
          }

          if ($caps['tags']) {
            sync_post_tags($conn, $postId, extract_tags($title, $content));
          }

          header('Location: ' . post_path($postId) . '?edited=1', true, 303);
          exit;
        } catch (PDOException $ex) {
          error_log('post update failed: ' . $ex->getMessage());
          $error = 'Something went wrong saving that post. Try again.';
        }
      }
    }
  }
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
$post = find_post($conn, $postId, true);

if ($post === null) {
  http_response_code(404);
  render_page('not_found');
  return;
}

$draft ??= ['title' => $post['title'], 'content' => $post['content']];

render_head('Edit post - ExchangeMyIdeas', ['edit_post.js', 'editor.js'], ['noindex' => true]);
?>

  <div class="container container-narrow">
    <header class="hero">
      <h1 class="page-title">Edit your post</h1>
      <p class="page-subtitle">Changes are re-checked by the content filter, same as a new post.</p>
    </header>

    <div class="header">
      <a href="<?= e(post_path($post['post_id'])) ?>" class="button secondary">&larr; Back to post</a>
    </div>

    <?php if ($error !== ''): ?>
      <div class="form-error"><?= e($error) ?></div>
    <?php endif; ?>

    <!-- Shown by edit_post.js when this browser holds no token for this post. -->
    <div class="form-error" id="not-owner" hidden>
      This browser does not hold the edit key for this post. Only the browser it
      was written from can change it &mdash; there are no accounts, so there is
      nothing to sign in to.
      <a class="clear-search" href="<?= e(post_path($post['post_id'])) ?>">Back to the post</a>
    </div>

    <form class="post-form" id="edit-form" method="post" action="<?= e(post_edit_path($post['post_id'])) ?>" hidden>
      <input type="hidden" name="post_id" value="<?= e($post['post_id']) ?>" />
      <input type="hidden" name="edit_token" id="edit-token" value="" />
      <input type="hidden" name="action" id="form-action" value="save" />

      <label class="label" for="title">Title</label>
      <input
        id="title"
        class="field"
        name="title"
        maxlength="200"
        value="<?= e($draft['title']) ?>"
      />

      <label class="label" for="content">Content</label>
      <textarea
        id="content"
        class="field"
        name="content"
        maxlength="20000"
      ><?= e($draft['content']) ?></textarea>
      <div id="char-count" class="char-count">0 characters</div>

      <div class="form-actions form-actions-split">
        <button type="button" class="button danger" id="delete-button">Delete post</button>
        <button type="submit" class="button">Save changes</button>
      </div>
    </form>
  </div>

<?php render_footer(); ?>
