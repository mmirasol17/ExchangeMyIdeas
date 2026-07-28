<?php
/*
 * A single post and its full reply thread. Serves /post/{id}.
 *
 * This page also owns reply submission for the whole site. The feed links here
 * to reply rather than posting inline, so there is one handler to keep correct
 * instead of two that drift apart.
 *
 * A refused reply re-renders this page with the text still in the box. Only a
 * successful one redirects (post/redirect/get), so a refresh cannot repost and
 * a rejection never costs someone what they wrote.
 */

$caps = site_caps($conn);
$postId = (string) ($params['id'] ?? '');

$replyError = '';
$replyNotice = '';
$replyDraft = ['content' => '', 'author' => ''];

// ---------------------------------------------------------------------------
// Reply submission
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Honeypot: real users leave this empty; bots tend to fill every field.
  if (trim($_POST['website'] ?? '') !== '') {
    header('Location: /', true, 302);
    exit;
  }

  $content = trim($_POST['reply_content'] ?? '');
  $author  = trim($_POST['reply_author'] ?? '');
  $author  = $author !== '' ? $author : 'Anonymous';

  $replyDraft = ['content' => $content, 'author' => $_POST['reply_author'] ?? ''];
  $target = find_post($conn, $postId);

  if ($content === '') {
    $replyError = 'A reply needs some text.';
  } elseif ($target === null) {
    $replyError = 'That post no longer exists.';
  } elseif (mb_strlen($content) > 5000) {
    $replyError = 'That reply is too long - keep it under 5,000 characters.';
  } else {
    $ipHash = $caps['moderation'] ? client_ip_hash() : null;

    if ($ipHash !== null && moderation_rate_limited($conn, 'blog_replies', $ipHash, 10, 300)) {
      $replyError = 'You are replying very quickly. Give it a minute and try again.';
    } else {
      $verdict = moderate_content('', $content, $author);

      if ($verdict['verdict'] === 'block') {
        $replyError = $verdict['message'];
      } else {
        try {
          if ($caps['moderation']) {
            $stmt = $conn->prepare('
              INSERT INTO blog_replies
                (reply_id, blog_post_id, author_name, content, date_posted, status, flag_score, flag_reasons, ip_hash)
              VALUES (uuid(), :post_id, :author, :content, NOW(), :status, :score, :reasons, :ip)');
            $stmt->execute([
              ':post_id' => $postId,
              ':author'  => $author,
              ':content' => $content,
              ':status'  => $verdict['verdict'] === 'flag' ? 'flagged' : 'visible',
              ':score'   => $verdict['score'],
              ':reasons' => implode(',', $verdict['reasons']),
              ':ip'      => $ipHash,
            ]);
          } else {
            $stmt = $conn->prepare('
              INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted)
              VALUES (uuid(), :post_id, :author, :content, NOW())');
            $stmt->execute([
              ':post_id' => $postId,
              ':author'  => $author,
              ':content' => $content,
            ]);
          }

          $flag = $verdict['verdict'] === 'flag' ? '?reply=flagged' : '?reply=ok';
          header('Location: ' . post_path($postId) . $flag . '#replies', true, 303);
          exit;
        } catch (PDOException $ex) {
          error_log('reply insert failed: ' . $ex->getMessage());
          $replyError = 'Something went wrong saving that reply. Try again.';
        }
      }
    }
  }
}

// ---------------------------------------------------------------------------
// Render
// ---------------------------------------------------------------------------
$post = find_post($conn, $postId);

if ($post === null) {
  http_response_code(404);
  render_page('not_found');
  return;
}

if (isset($_GET['reply'])) {
  $replyNotice = match ($_GET['reply']) {
    'ok'      => 'Reply posted.',
    'flagged' => 'Reply posted. The automated filter was unsure about it, so it is queued for review.',
    default   => '',
  };
}

$replies   = fetch_replies($conn, [$post['post_id']])[$post['post_id']] ?? [];
$tags      = $caps['tags'] ? (fetch_tags_for_posts($conn, [$post['post_id']])[$post['post_id']] ?? []) : [];
$topics    = $caps['tags'] ? popular_tags($conn, 12, $caps['moderation']) : [];
$permalink = site_url(post_path($post['post_id']));
$excerpt   = post_excerpt($post['content'], 200);
$published = date('c', strtotime($post['date_posted']));
$modified  = !empty($post['edited_at']) ? date('c', strtotime($post['edited_at'])) : $published;

$breadcrumbs = [['name' => 'Home', 'path' => '/']];
if ($tags) {
  $breadcrumbs[] = ['name' => $tags[0]['label'], 'path' => tag_path($tags[0]['slug'])];
}
$breadcrumbs[] = ['name' => $post['title'], 'path' => post_path($post['post_id'])];

render_head($post['title'] . ' - ExchangeMyIdeas', 'post.js', [
  'description' => $excerpt,
  'canonical'   => post_path($post['post_id']),
  'type'        => 'article',
  'published'   => $published,
  'modified'    => $modified,
  'author'      => $post['author_name'],
  'jsonld'      => [
    jsonld_website(),
    jsonld_breadcrumbs($breadcrumbs),
    jsonld_post($post, $tags, count($replies)),
  ],
]);
?>

  <div class="container">
    <div class="layout">
      <main class="feed" id="main">
        <?php render_breadcrumbs($breadcrumbs); ?>

        <article class="post post-full" id="<?= e($post['post_id']) ?>">
          <div class="post-head">
            <span class="avatar" style="background: <?= e(avatar_color($post['author_name'])) ?>"><?= e(initials($post['author_name'])) ?></span>
            <div class="post-meta">
              <span class="author-name"><?= e($post['author_name']) ?></span>
              <span class="date">
                <time datetime="<?= e($published) ?>"><?= e(relative_time($post['date_posted'])) ?></time>
                <?php if (!empty($post['edited_at'])): ?><span class="edited-mark" title="Edited">&middot; edited</span><?php endif; ?>
              </span>
            </div>
            <?php render_flag_badge($post); ?>
          </div>

          <h1 class="title"><?= e($post['title']) ?></h1>
          <div class="body"><?= render_markdown($post['content']) ?></div>

          <?php render_tags($tags); ?>

          <div class="footer">
            <div class="footer-actions">
              <?php if ($caps['likes']): ?>
                <button type="button" class="like-button" data-post-id="<?= e($post['post_id']) ?>" aria-label="Like this post">
                  <span class="like-icon">&#9829;</span>
                  <span class="like-count"><?= (int) $post['likes'] ?></span>
                </button>
              <?php endif; ?>
              <?php render_share($permalink, $post['title']); ?>
              <?php if ($caps['editing']): ?><?php render_owner_actions($post['post_id']); ?><?php endif; ?>
            </div>
          </div>
        </article>

        <section class="thread" id="replies">
          <h2 class="section-title">
            <?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?>
          </h2>

          <?php if ($replyNotice !== ''): ?>
            <div class="form-notice"><?= e($replyNotice) ?></div>
          <?php endif; ?>

          <?php if (!$replies): ?>
            <p class="sidebar-text">Nothing yet. Start the conversation below.</p>
          <?php endif; ?>

          <?php foreach ($replies as $reply): ?>
            <div class="reply reply-card">
              <span class="avatar avatar-sm" style="background: <?= e(avatar_color($reply['author_name'])) ?>"><?= e(initials($reply['author_name'])) ?></span>
              <div class="reply-content">
                <div class="reply-head">
                  <span class="author-name"><?= e($reply['author_name']) ?></span>
                  <span class="date">
                    <time datetime="<?= e(date('c', strtotime($reply['date_posted']))) ?>"><?= e(relative_time($reply['date_posted'])) ?></time>
                  </span>
                  <?php render_flag_badge($reply); ?>
                </div>
                <div class="body"><?= render_markdown($reply['content']) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        </section>

        <form class="reply-form" id="reply" method="post" action="<?= e(post_path($post['post_id'])) ?>">
          <h2 class="section-title">Leave a reply</h2>

          <?php if ($replyError !== ''): ?>
            <div class="form-error"><?= e($replyError) ?></div>
          <?php endif; ?>

          <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off" aria-hidden="true" />

          <label class="reply-label" for="reply_content">Message</label>
          <textarea
            id="reply_content"
            name="reply_content"
            class="reply-input"
            placeholder="What do you think about this post?"
            maxlength="5000"
            required
          ><?= e($replyDraft['content']) ?></textarea>

          <label class="reply-label" for="reply_author">Name (optional)</label>
          <input
            id="reply_author"
            name="reply_author"
            class="reply-input"
            maxlength="60"
            placeholder="How would you like to be known?"
            value="<?= e($replyDraft['author']) ?>"
          />

          <div class="reply-form-footer">
            <button type="submit" class="button">Post reply</button>
          </div>
        </form>
      </main>

      <aside class="sidebar">
        <?php render_sidebar($topics); ?>
      </aside>
    </div>
  </div>

<?php render_footer(); ?>
