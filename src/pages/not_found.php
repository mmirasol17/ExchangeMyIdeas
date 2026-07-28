<?php
/*
 * 404 page.
 *
 * The status code is set by whoever routed here, not by this file, so it can
 * serve both "no such route" and "that post is gone". Returning a real 404 (and
 * noindex) matters: a soft 404 that answers 200 teaches search engines to keep
 * requesting dead URLs and can get them indexed as thin pages.
 */

render_head('Page not found - ExchangeMyIdeas', '', [
  'description' => 'That page does not exist on ExchangeMyIdeas.',
  'noindex'     => true,
]);
?>

  <div class="container container-narrow">
    <div class="empty-state">
      <div class="empty-emoji" aria-hidden="true">&#129517;</div>
      <h1 class="page-title">Page not found</h1>
      <p class="sidebar-text">
        The link may be out of date, or the post may have been deleted by its
        author or removed by a moderator.
      </p>
      <div class="form-actions" style="justify-content: center">
        <a class="button" href="/">&larr; Back to all posts</a>
        <a class="button secondary" href="/new">Write something</a>
      </div>
    </div>
  </div>

<?php render_footer(); ?>
