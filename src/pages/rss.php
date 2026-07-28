<?php
/*
 * RSS 2.0 feed of the most recent posts. Serves /feed.xml.
 */

const FEED_ITEMS = 30;

$result = fetch_posts($conn, ['perPage' => FEED_ITEMS, 'sort' => 'recent']);
$posts = $result['posts'];

$self  = site_url('/feed.xml');
$home  = site_url('/');
$built = $posts
  ? date(DATE_RSS, strtotime($posts[0]['date_posted']))
  : date(DATE_RSS);

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0"
  xmlns:atom="http://www.w3.org/2005/Atom"
  xmlns:content="http://purl.org/rss/1.0/modules/content/"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <title>ExchangeMyIdeas</title>
    <link><?= e($home) ?></link>
    <description>Ideas worth exchanging - post a thought, reply, and search.</description>
    <language>en</language>
    <lastBuildDate><?= e($built) ?></lastBuildDate>
    <atom:link href="<?= e($self) ?>" rel="self" type="application/rss+xml" />
<?php foreach ($posts as $post): ?>
    <item>
      <title><?= e($post['title']) ?></title>
      <link><?= e(site_url(post_path($post['post_id']))) ?></link>
      <guid isPermaLink="true"><?= e(site_url(post_path($post['post_id']))) ?></guid>
      <pubDate><?= e(date(DATE_RSS, strtotime($post['date_posted']))) ?></pubDate>
      <dc:creator><?= e($post['author_name']) ?></dc:creator>
      <description><?= e(post_excerpt($post['content'], 300)) ?></description>
      <content:encoded><![CDATA[<?= str_replace(']]>', ']]&gt;', render_markdown($post['content'])) ?>]]></content:encoded>
    </item>
<?php endforeach; ?>
  </channel>
</rss>
