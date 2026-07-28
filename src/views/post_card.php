<?php
/*
 * One post as it appears in the feed.
 *
 * Rendered by pages/feed.php on first load and by pages/feed_partial.php for every page the
 * browser scrolls in. Both go through this function so a card that arrived by
 * fetch is byte-for-byte the card the server would have rendered - which is
 * what stops scroll-loading from slowly drifting away from the real thing.
 */

/**
 * @param array $post    Row from fetch_posts()
 * @param array $context replies, tags, caps, index
 */
function render_post_card(array $post, array $context = []): void {
  $replies   = $context['replies'] ?? [];
  $tags      = $context['tags'] ?? [];
  $caps      = $context['caps'] ?? [];
  $index     = (int) ($context['index'] ?? 0);

  $replyCount = (int) $post['reply_count'];
  $shown      = array_slice($replies, 0, FEED_REPLY_PREVIEW);
  $remaining  = count($replies) - count($shown);
  $permalink  = post_path($post['post_id']);
  ?>
  <article class="post" id="<?= e($post['post_id']) ?>" style="--i: <?= $index ?>">
    <div class="post-head">
      <span class="avatar" style="background: <?= e(avatar_color($post['author_name'])) ?>"><?= e(initials($post['author_name'])) ?></span>
      <div class="post-meta">
        <span class="author-name"><?= e($post['author_name']) ?></span>
        <span class="date">
          <?= e(relative_time($post['date_posted'])) ?>
          <?php if (!empty($post['edited_at'])): ?><span class="edited-mark" title="Edited">· edited</span><?php endif; ?>
        </span>
      </div>
      <?php render_flag_badge($post); ?>
    </div>

    <h2 class="title">
      <a class="title-link" href="<?= e($permalink) ?>">
        <span class="title-emoji" aria-hidden="true"><?= activity_emoji($replyCount, (int) $post['likes']) ?></span>
        <?= e($post['title']) ?>
      </a>
    </h2>
    <div class="body"><?= render_markdown($post['content']) ?></div>

    <?php render_tags($tags); ?>

    <div class="footer">
      <div class="footer-actions">
        <?php if (!empty($caps['likes'])): ?>
          <button type="button" class="like-button" data-post-id="<?= e($post['post_id']) ?>" aria-label="Like this post">
            <span class="like-icon">&#9829;</span>
            <span class="like-count"><?= (int) $post['likes'] ?></span>
          </button>
        <?php endif; ?>

        <?php if ($replyCount > 0): ?>
          <button type="button" class="reply-toggle" aria-expanded="false">
            <span class="chat-icon" aria-hidden="true">💬</span> <?= $replyCount ?> repl<?= $replyCount === 1 ? 'y' : 'ies' ?>
          </button>
        <?php else: ?>
          <span class="no-replies">No replies yet</span>
        <?php endif; ?>

        <?php render_share(site_url(post_path($post['post_id'])), $post['title']); ?>
        <?php if (!empty($caps['editing'])): ?><?php render_owner_actions($post['post_id']); ?><?php endif; ?>
      </div>
      <a class="reply-button button secondary" href="<?= e($permalink) ?>#reply">Reply</a>
    </div>

    <div class="replies<?= $replyCount > 0 ? ' collapsed' : '' ?>">
      <?php foreach ($shown as $reply): ?>
        <div class="reply">
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

      <?php if ($remaining > 0): ?>
        <a class="more-replies" href="<?= e($permalink) ?>">
          View <?= $remaining ?> more repl<?= $remaining === 1 ? 'y' : 'ies' ?> &rarr;
        </a>
      <?php endif; ?>
    </div>
  </article>
  <?php
}

/**
 * Render a whole page of cards, with the in-feed ad in its usual slot.
 *
 * $adAfter is the zero-based card index to place the ad after, or null for
 * none - scroll-loaded pages skip it so the feed does not turn into a stack
 * of ad units on a long scroll.
 */
function render_post_cards(array $posts, array $repliesByPost, array $tagsByPost, array $caps, ?int $adAfter = 1): void {
  foreach ($posts as $i => $post) {
    render_post_card($post, [
      'replies' => $repliesByPost[$post['post_id']] ?? [],
      'tags'    => $tagsByPost[$post['post_id']] ?? [],
      'caps'    => $caps,
      'index'   => $i,
    ]);

    if ($adAfter !== null && $i === $adAfter) {
      ad_slot();
    }
  }
}
