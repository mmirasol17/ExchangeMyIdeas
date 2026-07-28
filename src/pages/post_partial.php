<?php
/*
 * A single post and its replies as a bare HTML fragment.
 * Serves /partial/post/{id}, which the reading modal fetches.
 *
 * Same content as the permalink page, minus the page chrome. It is a fragment
 * rather than JSON for the same reason the feed fragment is: the server already
 * knows how to render a post, and asking the browser to re-implement that in
 * JavaScript is how a modal ends up subtly different from the page it mirrors.
 *
 * Replying is not offered here. The modal is for reading -- it links out to the
 * full page to reply, which keeps one submission path instead of two.
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$caps = site_caps($conn);
$post = find_post($conn, (string) ($params['id'] ?? ''));

if ($post === null) {
  http_response_code(404);
  echo '<p class="preview-empty">That post no longer exists.</p>';
  return;
}

$replies = fetch_replies($conn, [$post['post_id']])[$post['post_id']] ?? [];
$tags = $caps['tags'] ? (fetch_tags_for_posts($conn, [$post['post_id']])[$post['post_id']] ?? []) : [];
$permalink = post_path($post['post_id']);
?>
<article class="modal-post">
  <div class="post-head">
    <span class="avatar" style="background: <?= e(avatar_color($post['author_name'])) ?>"><?= e(initials($post['author_name'])) ?></span>
    <div class="post-meta">
      <span class="author-name"><?= e($post['author_name']) ?></span>
      <span class="date">
        <time datetime="<?= e(date('c', strtotime($post['date_posted']))) ?>"><?= e(relative_time($post['date_posted'])) ?></time>
        <?php if (!empty($post['edited_at'])): ?><span class="edited-mark" title="Edited">&middot; edited</span><?php endif; ?>
      </span>
    </div>
    <?php render_flag_badge($post); ?>
  </div>

  <h2 class="title modal-title-text" id="modal-title"><?= e($post['title']) ?></h2>
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
      <?php render_share(site_url($permalink), $post['title']); ?>
      <?php if ($caps['editing']): ?><?php render_owner_actions($post['post_id']); ?><?php endif; ?>
    </div>
    <a class="button secondary" href="<?= e($permalink) ?>#reply">Reply on the full page &rarr;</a>
  </div>
</article>

<?php if ($replies): ?>
  <section class="modal-replies">
    <h3 class="section-title"><?= count($replies) ?> repl<?= count($replies) === 1 ? 'y' : 'ies' ?></h3>
    <?php foreach ($replies as $reply): ?>
      <div class="reply reply-card">
        <span class="avatar avatar-sm" style="background: <?= e(avatar_color($reply['author_name'])) ?>"><?= e(initials($reply['author_name'])) ?></span>
        <div class="reply-content">
          <div class="reply-head">
            <span class="author-name"><?= e($reply['author_name']) ?></span>
            <span class="date"><?= e(relative_time($reply['date_posted'])) ?></span>
            <?php render_flag_badge($reply); ?>
          </div>
          <div class="body"><?= render_markdown($reply['content']) ?></div>
        </div>
      </div>
    <?php endforeach; ?>
  </section>
<?php else: ?>
  <p class="sidebar-text modal-noreplies">No replies yet.
    <a class="clear-search" href="<?= e($permalink) ?>#reply">Start the conversation &rarr;</a>
  </p>
<?php endif; ?>
