-- Adds a like counter to posts. Applied once via migrate.php.
ALTER TABLE blog_posts ADD COLUMN likes INT NOT NULL DEFAULT 0;
