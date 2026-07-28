-- Topic tags. Tags are derived automatically from a post's title and body by
-- extract_tags() in tags.php -- nobody types them in by hand.
--
-- Deliberately NO foreign key from post_tags.post_id to blog_posts.post_id:
-- InnoDB requires the two columns to share a character set and collation, and
-- the original blog_posts table was created without an explicit charset, so it
-- may not match a freshly created utf8mb4 table on every host. A failed FK here
-- would abort the whole migration. sync_post_tags() and the delete paths clean
-- up post_tags rows in PHP instead.

CREATE TABLE IF NOT EXISTS tags (
  tag_id INT NOT NULL AUTO_INCREMENT,
  slug VARCHAR(50) NOT NULL,
  label VARCHAR(60) NOT NULL,
  PRIMARY KEY (tag_id),
  UNIQUE KEY uniq_tag_slug (slug)
);

CREATE TABLE IF NOT EXISTS post_tags (
  post_id VARCHAR(36) NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_post_tags_tag (tag_id)
);
