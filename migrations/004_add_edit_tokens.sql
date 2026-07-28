-- Lets an anonymous author edit or delete their own post without an account.
--
-- The browser generates a random token, keeps it in localStorage, and sends it
-- with the post. Only the SHA-256 hash is stored here, so a database leak does
-- not hand out edit rights. See edit_post.php.
--
-- edited_at is NULL until the post is edited; the UI shows an "edited" marker
-- once it is set.

ALTER TABLE blog_posts
  ADD COLUMN edit_token CHAR(64) NULL,
  ADD COLUMN edited_at TIMESTAMP NULL DEFAULT NULL;
