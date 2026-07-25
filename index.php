<?php
require_once('lib.php');
require_once('config.php');

// Handle a reply submission, then redirect so a refresh doesn't repost.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  // Honeypot: real users leave this empty; bots tend to fill every field.
  if (trim($_POST['website'] ?? '') !== '') {
    header("Location: ./index.php");
    exit;
  }

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

// The likes feature only lights up once migration 001 has added the column.
$hasLikes = column_exists($conn, 'blog_posts', 'likes');

$search = trim($_GET['search'] ?? '');
$validSorts = $hasLikes ? ['recent', 'discussed', 'liked'] : ['recent', 'discussed'];
$sort = in_array($_GET['sort'] ?? '', $validSorts, true) ? $_GET['sort'] : 'recent';

// Whitelisted ordering -- never interpolate user input into ORDER BY.
$orderBy = match ($sort) {
  'discussed' => 'reply_count DESC, p.date_posted DESC',
  'liked'     => 'likes DESC, p.date_posted DESC',
  default     => 'p.date_posted DESC',
};

// Posts, with a reply count for the badge/sorting and likes when available.
$likesCol = $hasLikes ? 'p.likes' : '0 AS likes';
$select = "
  SELECT p.post_id, p.author_name, p.content, p.title, p.date_posted, $likesCol,
    (SELECT COUNT(*) FROM blog_replies r WHERE r.blog_post_id = p.post_id) AS reply_count
  FROM blog_posts p";

if ($search !== '') {
  $query = $conn->prepare("$select
    WHERE p.title LIKE :s OR p.content LIKE :s OR p.author_name LIKE :s
    ORDER BY $orderBy");
  $query->execute([':s' => '%' . $search . '%']);
} else {
  $query = $conn->prepare("$select ORDER BY $orderBy");
  $query->execute();
}
$posts = $query->fetchAll(PDO::FETCH_ASSOC);

$totalPosts = (int) $conn->query("SELECT COUNT(*) FROM blog_posts")->fetchColumn();

// Replies for every post on the page, in one query.
$repliesByPost = [];
if ($posts) {
  $postIds = array_column($posts, 'post_id');
  $placeholders = implode(',', array_fill(0, count($postIds), '?'));
  $repliesStatement = $conn->prepare("
    SELECT blog_post_id, author_name, content, date_posted
    FROM blog_replies
    WHERE blog_post_id IN ($placeholders)
    ORDER BY date_posted ASC");
  $repliesStatement->execute($postIds);
  foreach ($repliesStatement->fetchAll(PDO::FETCH_ASSOC) as $reply) {
    $repliesByPost[$reply['blog_post_id']][] = $reply;
  }
}

// Build sort links that preserve the active search term.
function sort_link(string $sort, string $search): string {
  $params = [];
  if ($search !== '') $params['search'] = $search;
  if ($sort !== 'recent') $params['sort'] = $sort;
  return './index.php' . ($params ? '?' . http_build_query($params) : '');
}

render_head('ExchangeMyIdeas', 'index.js');
?>

  <div class="container">
    <header class="hero">
      <h1 class="page-title">Ideas worth exchanging</h1>
      <p class="page-subtitle">Post a thought, reply to someone else's, or search everything shared so far.</p>
      <?php if ($totalPosts > 0): ?>
        <div class="hero-stats"><?= $totalPosts ?> idea<?= $totalPosts === 1 ? '' : 's' ?> shared</div>
      <?php endif; ?>
    </header>

    <form class="header" method="get" action="./index.php" role="search">
      <input
        id="search"
        name="search"
        type="text"
        autocomplete="off"
        aria-label="Search posts"
        placeholder="Search for anything..."
        value="<?= e($search) ?>"
      />
      <input type="submit" />
      <button type="button" id="post" class="button">Post</button>
    </form>

    <?php if ($search !== ''): ?>
      <div class="search-status">
        <span>Showing results for &ldquo;<?= e($search) ?>&rdquo;</span>
        <a class="clear-search" href="./index.php">Clear search</a>
      </div>
    <?php endif; ?>

    <?php if ($totalPosts > 1): ?>
      <div class="toolbar">
        <div class="sort-control" role="tablist" aria-label="Sort posts">
          <a class="sort-btn <?= $sort === 'recent' ? 'active' : '' ?>" href="<?= e(sort_link('recent', $search)) ?>">Newest</a>
          <a class="sort-btn <?= $sort === 'discussed' ? 'active' : '' ?>" href="<?= e(sort_link('discussed', $search)) ?>">Most discussed</a>
          <?php if ($hasLikes): ?>
            <a class="sort-btn <?= $sort === 'liked' ? 'active' : '' ?>" href="<?= e(sort_link('liked', $search)) ?>">Most liked</a>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>

    <div class="layout">
      <main class="feed">
        <?php if (!$posts): ?>
          <div class="empty-state">
            <?= $search !== '' ? 'No posts match &ldquo;' . e($search) . '&rdquo;.' : 'No posts yet &mdash; be the first to share an idea.' ?>
          </div>
        <?php endif; ?>

        <?php foreach ($posts as $i => $post): ?>
          <?php $rc = (int) $post['reply_count']; ?>
          <article class="post" id="<?= e($post['post_id']) ?>" style="--i: <?= $i ?>">
            <div class="post-head">
              <span class="avatar" style="background: <?= e(avatar_color($post['author_name'])) ?>"><?= e(initials($post['author_name'])) ?></span>
              <div class="post-meta">
                <span class="author-name"><?= e($post['author_name']) ?></span>
                <span class="date"><?= e(relative_time($post['date_posted'])) ?></span>
              </div>
            </div>

            <h2 class="title"><?= e($post['title']) ?></h2>
            <div class="body"><?= render_markdown($post['content']) ?></div>

            <div class="footer">
              <div class="footer-actions">
                <?php if ($hasLikes): ?>
                  <button type="button" class="like-button" data-post-id="<?= e($post['post_id']) ?>" aria-label="Like this post">
                    <span class="like-icon">&#9829;</span>
                    <span class="like-count"><?= (int) $post['likes'] ?></span>
                  </button>
                <?php endif; ?>
                <?php if ($rc > 0): ?>
                  <button type="button" class="reply-toggle" aria-expanded="false">
                    <span class="chat-icon">💬</span> <?= $rc ?> repl<?= $rc === 1 ? 'y' : 'ies' ?>
                  </button>
                <?php else: ?>
                  <span class="no-replies">No replies yet</span>
                <?php endif; ?>
              </div>
              <button type="button" class="reply-button button secondary">Reply</button>
            </div>

            <div class="replies<?= $rc > 0 ? ' collapsed' : '' ?>">
              <?php foreach ($repliesByPost[$post['post_id']] ?? [] as $reply): ?>
                <div class="reply">
                  <span class="avatar avatar-sm" style="background: <?= e(avatar_color($reply['author_name'])) ?>"><?= e(initials($reply['author_name'])) ?></span>
                  <div class="reply-content">
                    <div class="reply-head">
                      <span class="author-name"><?= e($reply['author_name']) ?></span>
                      <span class="date"><?= e(relative_time($reply['date_posted'])) ?></span>
                    </div>
                    <div class="body"><?= render_markdown($reply['content']) ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </article>

          <?php if ($i === 1): ad_slot(); endif; // in-feed ad after the 2nd post ?>
        <?php endforeach; ?>
      </main>

      <aside class="sidebar">
        <?php render_sidebar(); ?>
      </aside>
    </div>
  </div>

<?php render_footer(); ?>
