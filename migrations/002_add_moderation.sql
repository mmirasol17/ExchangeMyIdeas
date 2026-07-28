-- Moderation support for posts and replies.
--
-- status      'visible' (normal), 'flagged' (published but queued for review),
--             or 'hidden' (removed by a moderator; never rendered publicly).
-- flag_score  The moderation engine's score at submission time.
-- flag_reasons Comma-separated reason codes, e.g. "profanity,link_spam".
-- ip_hash     Salted SHA-256 of the submitter's IP. Used for rate limiting and
--             for spotting a repeat abuser. The raw IP is never stored.
--
-- Applied once via migrate.php. Until it runs, the app detects the missing
-- columns and simply skips moderation (see site_caps() in posts.php).

ALTER TABLE blog_posts
  ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'visible',
  ADD COLUMN flag_score INT NOT NULL DEFAULT 0,
  ADD COLUMN flag_reasons VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN ip_hash CHAR(64) NULL;

ALTER TABLE blog_replies
  ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'visible',
  ADD COLUMN flag_score INT NOT NULL DEFAULT 0,
  ADD COLUMN flag_reasons VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN ip_hash CHAR(64) NULL;

-- The feed filters on status and orders by date, so index both.
CREATE INDEX idx_blog_posts_status ON blog_posts (status);
CREATE INDEX idx_blog_posts_date ON blog_posts (date_posted);
CREATE INDEX idx_blog_replies_status ON blog_replies (status);
