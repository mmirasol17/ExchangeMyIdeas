-- ============================================================================
-- ExchangeMyIdeas -- seed content
--
-- Run this in phpMyAdmin with your database (if0_..._blog) selected in the
-- left sidebar, via the SQL tab. It inserts a handful of starter posts and
-- replies on legacy code, AI, and automation.
--
-- Note: running this more than once will insert duplicate rows.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Post 1
-- ---------------------------------------------------------------------------
SET @p1 = uuid();
INSERT INTO blog_posts (post_id, author_name, content, title, date_posted) VALUES
(@p1, 'Marin Mirasol',
'Every codebase people call "legacy" was once someone''s best effort under a real deadline. The code didn''t rot -- the context around it did. The people left, the docs went stale, and the assumptions baked into it stopped being obvious.\n\nSo before you rewrite it, try to recover the *why*. Read the commit history, find the oldest tests, and talk to whoever is still around. Nine times out of ten the "weird" code is weird because it was solving a real problem you haven''t hit yet.',
'Legacy code is just code that outlived its documentation',
DATE_SUB(NOW(), INTERVAL 14 DAY));

SET @r1 = uuid();
INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted) VALUES
(@r1, @p1, 'Amer Yono',
'The commit-history point is underrated. `git log -p` on a confusing file has saved me more times than any wiki. The wiki lies; the diffs don''t.',
DATE_SUB(NOW(), INTERVAL 13 DAY));

SET @r2 = uuid();
INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted) VALUES
(@r2, @p1, 'Corey Taylor',
'"The code didn''t rot, the context did" -- stealing that for my next standup.',
DATE_SUB(NOW(), INTERVAL 12 DAY));

-- ---------------------------------------------------------------------------
-- Post 2
-- ---------------------------------------------------------------------------
SET @p2 = uuid();
INSERT INTO blog_posts (post_id, author_name, content, title, date_posted) VALUES
(@p2, 'Marin Mirasol',
'I let an AI refactor a 2,000-line file last week. It did in twenty minutes what would have taken me a slow afternoon -- extracted helpers, killed dead branches, tightened the types.\n\nBut here''s the thing: it was only safe because the file had tests. Without them, I''d have been trading a mess I understood for a cleaner mess I didn''t. The AI moved fast; the tests were what let me actually trust the result. Automation amplifies whatever discipline you already have. It doesn''t create it.',
'I let an AI refactor a 2,000-line file. Here is what actually mattered',
DATE_SUB(NOW(), INTERVAL 9 DAY));

SET @r3 = uuid();
INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted) VALUES
(@r3, @p2, 'Priya N.',
'This matches my experience exactly. The value of a green test suite went *up* the moment I started using AI, not down. It''s the seatbelt.',
DATE_SUB(NOW(), INTERVAL 8 DAY));

-- ---------------------------------------------------------------------------
-- Post 3
-- ---------------------------------------------------------------------------
SET @p3 = uuid();
INSERT INTO blog_posts (post_id, author_name, content, title, date_posted) VALUES
(@p3, 'Marin Mirasol',
'Good automation isn''t about replacing yourself -- it''s about deleting the parts of the job that were never really "you" in the first place.\n\nThe boilerplate. The copy-paste-with-one-change. The fifteen-step deploy you do from memory and occasionally get wrong. Automate those, and what''s left is the actual thinking: the design calls, the tradeoffs, the judgment. That''s the part worth keeping your hands on.',
'Automation is subtraction, not replacement',
DATE_SUB(NOW(), INTERVAL 6 DAY));

SET @r4 = uuid();
INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted) VALUES
(@r4, @p3, 'Corey Taylor',
'The "fifteen-step deploy from memory" hit a little too close to home. Just wrote a script for exactly that this morning.',
DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ---------------------------------------------------------------------------
-- Post 4
-- ---------------------------------------------------------------------------
SET @p4 = uuid();
INSERT INTO blog_posts (post_id, author_name, content, title, date_posted) VALUES
(@p4, 'Marin Mirasol',
'The AI coding workflow that actually works for me: small, verifiable steps. Ask for one focused change, read every line of it, run the tests, commit. Then the next one.\n\nThe failure mode is asking for too much at once -- a giant diff you can''t fully review, so you skim it, approve it, and inherit bugs you never saw. Treat the AI like a very fast junior who needs a code review on every PR. The speed is real, but the reviewing is still your job.',
'The best AI workflow is small, reviewable steps',
DATE_SUB(NOW(), INTERVAL 3 DAY));

SET @r5 = uuid();
INSERT INTO blog_replies (reply_id, blog_post_id, author_name, content, date_posted) VALUES
(@r5, @p4, 'Amer Yono',
'"A very fast junior who needs a review on every PR" is the best framing of this I''ve seen. The people who get burned are the ones who skip the review because it looked right.',
DATE_SUB(NOW(), INTERVAL 2 DAY));

-- ---------------------------------------------------------------------------
-- Post 5
-- ---------------------------------------------------------------------------
SET @p5 = uuid();
INSERT INTO blog_posts (post_id, author_name, content, title, date_posted) VALUES
(@p5, 'Marin Mirasol',
'Reading code is the skill AI made *more* valuable, not less. When a model can generate a plausible-looking function in seconds, the bottleneck moves to your ability to look at it and know whether it''s actually right.\n\nSyntax you can outsource. Judgment you can''t. The engineers who thrive with these tools aren''t the fastest typers -- they''re the fastest, most careful readers.',
'AI made reading code the most valuable skill',
DATE_SUB(NOW(), INTERVAL 1 DAY));
