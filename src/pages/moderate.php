<?php
/*
 * Moderation review queue.
 *
 * Everything the filter was unsure about lands here. Clear-cut abuse never
 * reaches this page -- it is refused at submission time -- so what is listed is
 * genuinely borderline and wants a human decision.
 *
 * ACCESS
 *   Guarded by $adminKey in config.local.php, falling back to $migrateKey so an
 *   existing deployment can use this page before re-provisioning. If neither is
 *   set the page is disabled outright, the same posture as migrate.php.
 *
 *     https://exchangemyideas.marinmirasol.com/moderate?key=YOUR_KEY
 *
 *   The key travels in the query string, so the page sends no referrer and asks
 *   not to be indexed. It is a single-operator tool, not a user account system.
 */

global $adminKey, $migrateKey;

$key = (string) ($_GET['key'] ?? $_POST['key'] ?? '');
$expected = (string) ($adminKey ?? $migrateKey ?? '');

if ($expected === '' || !hash_equals($expected, $key)) {
  http_response_code(403);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Forbidden.\n");
}

$caps = site_caps($conn);
$notice = '';

if (!$caps['moderation']) {
  http_response_code(503);
  header('Content-Type: text/plain; charset=utf-8');
  exit("Moderation columns are missing. Apply migration 002 via /migrate first.\n");
}

/** Link back to this page with the key preserved. */
function mod_url(string $extra = ''): string {
  global $key;
  return '/moderate?key=' . rawurlencode($key) . $extra;
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id     = trim($_POST['id'] ?? '');
  $kind   = $_POST['kind'] ?? 'post';

  $table  = $kind === 'reply' ? 'blog_replies' : 'blog_posts';
  $idCol  = $kind === 'reply' ? 'reply_id' : 'post_id';

  try {
    switch ($action) {
      case 'approve':
        // Clear the score too, so an approved item cannot drift back into the
        // queue the next time this page is opened.
        $conn->prepare("UPDATE `$table` SET status = 'visible', flag_score = 0, flag_reasons = '' WHERE `$idCol` = ?")
          ->execute([$id]);
        $notice = 'Approved. It is live and out of the queue.';
        break;

      case 'hide':
        $conn->prepare("UPDATE `$table` SET status = 'hidden' WHERE `$idCol` = ?")->execute([$id]);
        $notice = 'Hidden. It stays in the database but is no longer public.';
        break;

      case 'delete':
        if ($kind === 'reply') {
          $conn->prepare('DELETE FROM blog_replies WHERE reply_id = ?')->execute([$id]);
        } else {
          delete_post($conn, $id);
        }
        $notice = 'Deleted permanently.';
        break;

      case 'retag':
        $notice = mod_retag_all($conn);
        break;
    }
  } catch (PDOException $ex) {
    error_log('moderation action failed: ' . $ex->getMessage());
    $notice = 'That action failed. Check the server error log.';
  }

  header('Location: ' . mod_url('&done=' . urlencode($notice)));
  exit;
}

/**
 * Derive tags for every post that has none.
 *
 * Posts written before automatic tagging existed have no rows in post_tags, so
 * they would never appear under any topic. This backfills them.
 */
function mod_retag_all(PDO $conn): string {
  if (!site_caps($conn)['tags']) {
    return 'Tag tables are missing. Apply migration 003 first.';
  }
  $rows = $conn->query('
    SELECT p.post_id, p.title, p.content
    FROM blog_posts p
    LEFT JOIN post_tags pt ON pt.post_id = p.post_id
    WHERE pt.post_id IS NULL
    LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);

  $tagged = 0;
  foreach ($rows as $row) {
    $tags = extract_tags($row['title'], $row['content']);
    if ($tags) {
      sync_post_tags($conn, $row['post_id'], $tags);
      $tagged++;
    }
  }
  return "Tagged $tagged post(s). Run again if you had more than 500 untagged.";
}

if (isset($_GET['done'])) {
  $notice = (string) $_GET['done'];
}

// ---------------------------------------------------------------------------
// Queue contents
// ---------------------------------------------------------------------------
$flaggedPosts = fetch_posts($conn, [
  'status'        => 'flagged',
  'sort'          => 'flagged',
  'perPage'       => 50,
  'includeHidden' => true,
])['posts'];

$hiddenPosts = fetch_posts($conn, [
  'status'        => 'hidden',
  'perPage'       => 50,
  'includeHidden' => true,
])['posts'];

$flaggedReplies = $conn->query("
  SELECT r.reply_id, r.blog_post_id, r.author_name, r.content, r.date_posted,
         r.status, r.flag_score, r.flag_reasons, p.title AS post_title
  FROM blog_replies r
  LEFT JOIN blog_posts p ON p.post_id = r.blog_post_id
  WHERE r.status = 'flagged'
  ORDER BY r.flag_score DESC, r.date_posted DESC
  LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$untagged = 0;
if ($caps['tags']) {
  $untagged = (int) $conn->query('
    SELECT COUNT(*) FROM blog_posts p
    LEFT JOIN post_tags pt ON pt.post_id = p.post_id
    WHERE pt.post_id IS NULL')->fetchColumn();
}

/** Action buttons for one queued item. */
function mod_actions(string $id, string $kind, bool $hidden = false): void {
  global $key;
  ?>
  <form class="mod-actions" method="post" action="/moderate">
    <input type="hidden" name="key" value="<?= e($key) ?>" />
    <input type="hidden" name="id" value="<?= e($id) ?>" />
    <input type="hidden" name="kind" value="<?= e($kind) ?>" />
    <?php if (!$hidden): ?>
      <button type="submit" name="action" value="approve" class="button">Approve</button>
      <button type="submit" name="action" value="hide" class="button secondary">Hide</button>
    <?php else: ?>
      <button type="submit" name="action" value="approve" class="button secondary">Restore</button>
    <?php endif; ?>
    <button type="submit" name="action" value="delete" class="button danger"
      data-confirm="Delete this permanently? This cannot be undone.">Delete</button>
  </form>
  <?php
}

/** The reason codes that made something land in the queue. */
function mod_reasons(string $reasons): void {
  $codes = array_filter(array_map('trim', explode(',', $reasons)));
  if (!$codes) {
    return;
  }
  ?>
  <div class="mod-reasons">
    <?php foreach ($codes as $code): ?>
      <span class="mod-reason">
        <span aria-hidden="true"><?= reason_emoji($code) ?></span> <?= e(moderation_reason_label($code)) ?>
      </span>
    <?php endforeach; ?>
  </div>
  <?php
}

render_head('Moderation queue - ExchangeMyIdeas', '', ['noindex' => true]);
?>

  <div class="container">
    <header class="hero">
      <h1 class="page-title">Moderation queue</h1>
      <p class="page-subtitle">
        Content the automated filter was unsure about. Outright abuse is refused
        before it gets here.
      </p>
      <div class="hero-stats">
        <?= count($flaggedPosts) ?> flagged post<?= count($flaggedPosts) === 1 ? '' : 's' ?>
        &middot; <?= count($flaggedReplies) ?> flagged repl<?= count($flaggedReplies) === 1 ? 'y' : 'ies' ?>
        &middot; <?= count($hiddenPosts) ?> hidden
      </div>
    </header>

    <?php if ($notice !== ''): ?>
      <div class="form-notice"><?= e($notice) ?></div>
    <?php endif; ?>

    <section class="mod-section">
      <h2 class="section-title">Flagged posts</h2>
      <?php if (!$flaggedPosts): ?>
        <div class="empty-state">Nothing waiting. </div>
      <?php endif; ?>
      <?php foreach ($flaggedPosts as $post): ?>
        <article class="post mod-item">
          <div class="mod-head">
            <div>
              <a class="mod-title" href="<?= e(post_path($post["post_id"])) ?>" target="_blank" rel="noopener"><?= e($post['title']) ?></a>
              <div class="date"><?= e($post['author_name']) ?> &middot; <?= e(relative_time($post['date_posted'])) ?></div>
            </div>
            <span class="mod-score" title="Moderation score"><?= (int) $post['flag_score'] ?></span>
          </div>
          <?php mod_reasons($post['flag_reasons']); ?>
          <div class="body mod-body"><?= render_markdown($post['content']) ?></div>
          <?php mod_actions($post['post_id'], 'post'); ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="mod-section">
      <h2 class="section-title">Flagged replies</h2>
      <?php if (!$flaggedReplies): ?>
        <div class="empty-state">Nothing waiting.</div>
      <?php endif; ?>
      <?php foreach ($flaggedReplies as $reply): ?>
        <article class="post mod-item">
          <div class="mod-head">
            <div>
              <div class="date">
                <?= e($reply['author_name']) ?> &middot; <?= e(relative_time($reply['date_posted'])) ?>
                &middot; on <a class="mod-title" href="<?= e(post_path((string) $reply["blog_post_id"])) ?>" target="_blank" rel="noopener"><?= e((string) ($reply['post_title'] ?? 'a deleted post')) ?></a>
              </div>
            </div>
            <span class="mod-score" title="Moderation score"><?= (int) $reply['flag_score'] ?></span>
          </div>
          <?php mod_reasons($reply['flag_reasons']); ?>
          <div class="body mod-body"><?= render_markdown($reply['content']) ?></div>
          <?php mod_actions($reply['reply_id'], 'reply'); ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="mod-section">
      <h2 class="section-title">Hidden posts</h2>
      <?php if (!$hiddenPosts): ?>
        <div class="empty-state">Nothing hidden.</div>
      <?php endif; ?>
      <?php foreach ($hiddenPosts as $post): ?>
        <article class="post mod-item">
          <div class="mod-head">
            <div>
              <span class="mod-title"><?= e($post['title']) ?></span>
              <div class="date"><?= e($post['author_name']) ?> &middot; <?= e(relative_time($post['date_posted'])) ?></div>
            </div>
          </div>
          <div class="body mod-body"><?= render_markdown($post['content']) ?></div>
          <?php mod_actions($post['post_id'], 'post', true); ?>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="mod-section">
      <h2 class="section-title">Tools</h2>
      <div class="post mod-item">
        <p class="sidebar-text">
          <?= $untagged ?> post<?= $untagged === 1 ? ' has' : 's have' ?> no topics yet.
          Posts written before automatic tagging existed need one backfill pass.
        </p>
        <form method="post" action="/moderate">
          <input type="hidden" name="key" value="<?= e($key) ?>" />
          <button type="submit" name="action" value="retag" class="button" <?= $untagged === 0 ? 'disabled' : '' ?>>
            Generate topics for untagged posts
          </button>
        </form>
      </div>
    </section>
  </div>

<?php render_footer(); ?>
