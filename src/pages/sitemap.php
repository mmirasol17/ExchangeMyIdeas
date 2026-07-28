<?php
/*
 * XML sitemap: the static pages, every visible post, and every topic.
 * Serves /sitemap.xml, which robots.txt points crawlers at.
 *
 * Only indexable URLs belong here. Listing a page that is noindex (search
 * results, the moderation queue) sends search engines a contradictory signal,
 * so those are deliberately absent.
 */

const SITEMAP_MAX_POSTS = 2000;

$caps = site_caps($conn);
$posts = fetch_posts($conn, ['perPage' => SITEMAP_MAX_POSTS, 'sort' => 'recent'])['posts'];
$topics = $caps['tags'] ? popular_tags($conn, 200, $caps['moderation']) : [];

/** One <url> entry. */
function sitemap_url(string $path, ?string $lastmod = null, string $changefreq = 'weekly', string $priority = '0.5'): void {
  ?>
  <url>
    <loc><?= e(site_url($path)) ?></loc>
    <?php if ($lastmod !== null): ?><lastmod><?= e($lastmod) ?></lastmod><?php endif; ?>
    <changefreq><?= e($changefreq) ?></changefreq>
    <priority><?= e($priority) ?></priority>
  </url>
  <?php
}

// The newest post's date doubles as the home page's last-modified.
$newest = $posts ? date('Y-m-d', strtotime($posts[0]['date_posted'])) : null;

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <?php sitemap_url('/', $newest, 'daily', '1.0'); ?>
  <?php sitemap_url('/new', null, 'monthly', '0.4'); ?>
  <?php sitemap_url('/privacy', null, 'yearly', '0.2'); ?>

  <?php foreach ($posts as $post): ?>
    <?php
      $modified = !empty($post['edited_at']) ? $post['edited_at'] : $post['date_posted'];
      sitemap_url(post_path($post['post_id']), date('Y-m-d', strtotime($modified)), 'weekly', '0.8');
    ?>
  <?php endforeach; ?>

  <?php foreach ($topics as $topic): ?>
    <?php sitemap_url(tag_path($topic['slug']), null, 'weekly', '0.6'); ?>
  <?php endforeach; ?>
</urlset>
