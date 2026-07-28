<?php
/*
 * Automatic topic tagging.
 *
 * Nobody types tags in. extract_tags() reads a post's title and body and
 * decides what it is about, so tagging cannot be skipped, misspelled, or used
 * as a spam vector -- which is exactly why it is derived rather than entered.
 *
 * TWO PASSES
 *   1. A curated topic dictionary. Each topic owns a set of trigger terms, and
 *      a topic scores by how many of its terms appear (title matches count for
 *      more, since a title is a summary the author already wrote). This is what
 *      produces clean, shared tags like "ai" or "legacy-code" that group posts
 *      together instead of splintering into near-duplicates.
 *
 *   2. Salient keywords, for posts the dictionary does not recognise. Ordinary
 *      words are dropped and what remains is ranked by weighted frequency, so a
 *      post about something the dictionary has never heard of still gets a
 *      usable tag rather than none.
 *
 *   An explicit #hashtag, if someone writes one anyway, is honoured first.
 *
 * Tags are stored in the tags / post_tags tables (migration 003) and refreshed
 * whenever a post is created or edited.
 */

/** How many tags a single post may carry. */
const TAG_LIMIT = 4;

/**
 * The topic dictionary: canonical slug => label and trigger terms.
 *
 * Terms are matched as whole words, so "ai" does not fire inside "said". Add
 * topics freely; posts are re-tagged on their next edit, and the review queue
 * in moderate.php has a button to re-tag everything.
 */
function tag_topics(): array {
  return [
    'ai' => ['label' => 'AI', 'terms' => [
      'ai', 'artificial intelligence', 'llm', 'llms', 'gpt', 'chatgpt', 'claude',
      'machine learning', 'neural network', 'deep learning', 'model', 'models',
      'prompt', 'prompting', 'copilot', 'inference', 'training data', 'agent', 'agents',
    ]],
    'legacy-code' => ['label' => 'Legacy code', 'terms' => [
      'legacy', 'legacy code', 'technical debt', 'tech debt', 'refactor',
      'refactoring', 'rewrite', 'maintenance', 'codebase', 'cruft', 'migration',
      'deprecated', 'brownfield',
    ]],
    'automation' => ['label' => 'Automation', 'terms' => [
      'automation', 'automate', 'automated', 'ci', 'cd', 'ci/cd', 'pipeline',
      'cron', 'scheduler', 'workflow', 'script', 'scripting', 'bot', 'orchestration',
    ]],
    'engineering' => ['label' => 'Engineering', 'terms' => [
      'engineering', 'engineer', 'software', 'programming', 'developer', 'coding',
      'architecture', 'abstraction', 'api', 'framework', 'library', 'compiler',
    ]],
    'testing' => ['label' => 'Testing', 'terms' => [
      'test', 'tests', 'testing', 'unit test', 'integration test', 'coverage',
      'tdd', 'regression', 'flaky', 'qa', 'assertion',
    ]],
    'security' => ['label' => 'Security', 'terms' => [
      'security', 'vulnerability', 'exploit', 'injection', 'xss', 'csrf', 'auth',
      'authentication', 'encryption', 'password', 'breach', 'phishing', 'malware',
    ]],
    'databases' => ['label' => 'Databases', 'terms' => [
      'database', 'sql', 'mysql', 'postgres', 'postgresql', 'query', 'schema',
      'index', 'migration', 'nosql', 'mongodb', 'redis', 'transaction',
    ]],
    'web' => ['label' => 'Web', 'terms' => [
      'web', 'website', 'browser', 'html', 'css', 'javascript', 'frontend',
      'backend', 'http', 'responsive', 'dom', 'react', 'php',
    ]],
    'devops' => ['label' => 'DevOps', 'terms' => [
      'devops', 'deploy', 'deployment', 'docker', 'kubernetes', 'server',
      'infrastructure', 'hosting', 'cloud', 'aws', 'uptime', 'monitoring', 'outage',
    ]],
    'open-source' => ['label' => 'Open source', 'terms' => [
      'open source', 'opensource', 'github', 'git', 'pull request', 'maintainer',
      'contributor', 'license', 'fork', 'repository',
    ]],
    'career' => ['label' => 'Career', 'terms' => [
      'career', 'job', 'jobs', 'hiring', 'interview', 'resume', 'promotion',
      'manager', 'salary', 'quit', 'burnout', 'onboarding', 'mentor', 'standup',
    ]],
    'startups' => ['label' => 'Startups', 'terms' => [
      'startup', 'startups', 'founder', 'funding', 'vc', 'venture', 'pitch',
      'mvp', 'traction', 'bootstrapped', 'product market fit', 'runway',
    ]],
    'productivity' => ['label' => 'Productivity', 'terms' => [
      'productivity', 'focus', 'habit', 'habits', 'routine', 'procrastination',
      'deadline', 'time management', 'workflow', 'distraction', 'deep work',
    ]],
    'design' => ['label' => 'Design', 'terms' => [
      'design', 'ux', 'ui', 'usability', 'typography', 'layout', 'accessibility',
      'user experience', 'interface', 'prototype', 'wireframe',
    ]],
    'writing' => ['label' => 'Writing', 'terms' => [
      'writing', 'write', 'wrote', 'essay', 'blog', 'documentation', 'docs',
      'prose', 'editing', 'draft', 'newsletter',
    ]],
    'learning' => ['label' => 'Learning', 'terms' => [
      'learning', 'learn', 'teaching', 'student', 'school', 'university',
      'college', 'course', 'tutorial', 'curriculum', 'study', 'beginner',
    ]],
    'remote-work' => ['label' => 'Remote work', 'terms' => [
      'remote', 'remote work', 'work from home', 'hybrid', 'office', 'commute',
      'async', 'distributed team', 'zoom',
    ]],
    'money' => ['label' => 'Money', 'terms' => [
      'money', 'budget', 'saving', 'savings', 'invest', 'investing', 'finance',
      'income', 'expense', 'debt', 'pricing', 'revenue', 'cost',
    ]],
    'health' => ['label' => 'Health', 'terms' => [
      'health', 'fitness', 'exercise', 'sleep', 'diet', 'nutrition', 'mental health',
      'stress', 'anxiety', 'workout', 'running', 'wellness',
    ]],
    'philosophy' => ['label' => 'Philosophy', 'terms' => [
      'philosophy', 'ethics', 'moral', 'meaning', 'consciousness', 'existential',
      'truth', 'belief', 'wisdom', 'stoic', 'stoicism',
    ]],
    'science' => ['label' => 'Science', 'terms' => [
      'science', 'physics', 'biology', 'chemistry', 'research', 'experiment',
      'hypothesis', 'evolution', 'climate', 'space', 'quantum',
    ]],
    'music' => ['label' => 'Music', 'terms' => [
      'music', 'song', 'songs', 'album', 'guitar', 'playlist', 'spotify',
      'band', 'concert', 'melody', 'producer',
    ]],
    'gaming' => ['label' => 'Gaming', 'terms' => [
      'game', 'games', 'gaming', 'gamer', 'console', 'playstation', 'xbox',
      'nintendo', 'steam', 'multiplayer', 'speedrun',
    ]],
    'community' => ['label' => 'Community', 'terms' => [
      'community', 'forum', 'discussion', 'moderation', 'comment', 'comments',
      'social', 'reddit', 'twitter', 'discord', 'toxic',
    ]],
  ];
}

/** Words too common to be a useful tag. */
function tag_stopwords(): array {
  static $set = null;
  if ($set !== null) {
    return $set;
  }
  $words = 'a about above after again against all also am an and any are around as at be because been before
    being below between both but by came can cannot come could did do does doing done down during each even
    ever every few for from further get got had has have having he her here hers herself him himself his how
    however i if in into is it its itself just keep let like made make many may me might more most much must
    my myself never new now of off often on once one only or other others ought our ours ourselves out over
    own really said same say says see seen she should since so some someone something still such take than
    that the their theirs them themselves then there these they thing things this those though through thus
    to too under until up upon us use used using very want was way we well were what when where whether which
    while who whom why will with within without would yet you your yours yourself yourselves lot going get
    getting always actually maybe probably basically simply thats theres youre dont doesnt isnt wasnt arent
    cant wont didnt couldnt shouldnt wouldnt ive im id ill youve theyre weve lets etc via per';
  $set = array_fill_keys(preg_split('/\s+/', trim($words), -1, PREG_SPLIT_NO_EMPTY) ?: [], true);
  return $set;
}

/** Lowercase, strip markup and punctuation, collapse whitespace. */
function tag_normalize(string $text): string {
  $text = mb_strtolower($text, 'UTF-8');
  $text = preg_replace('/`[^`]*`/', ' ', $text) ?? $text;          // inline code
  $text = preg_replace('~https?://\S+~', ' ', $text) ?? $text;      // urls
  $text = preg_replace("/[’']/u", '', $text) ?? $text;              // don't -> dont
  $text = preg_replace('/[^a-z0-9#\s]+/u', ' ', $text) ?? $text;
  return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
}

/** Turn arbitrary text into a URL-safe tag slug. */
function tag_slugify(string $text): string {
  $slug = preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text, 'UTF-8')) ?? '';
  return trim(substr($slug, 0, 50), '-');
}

/** Crude singularisation so "models" and "model" land on the same tag. */
function tag_singular(string $word): string {
  if (strlen($word) > 4 && str_ends_with($word, 'ies')) {
    return substr($word, 0, -3) . 'y';
  }
  if (strlen($word) > 4 && str_ends_with($word, 'sses')) {
    return substr($word, 0, -2);
  }
  if (strlen($word) > 3 && str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
    return substr($word, 0, -1);
  }
  return $word;
}

/**
 * Decide what a post is about.
 *
 * @return array<int, array{slug:string,label:string}> Up to TAG_LIMIT tags.
 */
function extract_tags(string $title, string $content, int $limit = TAG_LIMIT): array {
  $titleNorm = tag_normalize($title);
  $bodyNorm  = tag_normalize($content);
  $all       = trim($titleNorm . ' ' . $bodyNorm);

  if ($all === '') {
    return [];
  }

  $picked = [];  // slug => label
  $scores = [];  // slug => score

  // --- Pass 0: an explicit #hashtag, if the author wrote one ----------------
  if (preg_match_all('/#([a-z][a-z0-9-]{1,29})\b/', $all, $m)) {
    foreach ($m[1] as $raw) {
      $slug = tag_slugify($raw);
      if ($slug !== '' && !isset($picked[$slug])) {
        $picked[$slug] = ucfirst(str_replace('-', ' ', $slug));
        $scores[$slug] = 1000;  // author intent outranks inference
      }
    }
  }

  // --- Pass 1: the topic dictionary ----------------------------------------
  foreach (tag_topics() as $slug => $topic) {
    $score = 0;
    foreach ($topic['terms'] as $term) {
      $pattern = '/\b' . preg_quote($term, '/') . '\b/';
      $inTitle = preg_match_all($pattern, $titleNorm);
      $inBody  = preg_match_all($pattern, $bodyNorm);
      // A title mention is worth three body mentions: the author already
      // summarised the post there.
      $score += $inTitle * 3 + min($inBody, 4);
    }
    if ($score >= 3) {
      $scores[$slug] = ($scores[$slug] ?? 0) + $score;
      $picked[$slug] = $topic['label'];
    }
  }

  // --- Pass 2: salient keywords, for anything the dictionary missed ---------
  if (count($picked) < $limit) {
    $stop = tag_stopwords();
    $freq = [];

    foreach ([[$titleNorm, 3], [$bodyNorm, 1]] as [$text, $weight]) {
      foreach (preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
        $word = ltrim($word, '#');
        if (strlen($word) < 4 || strlen($word) > 24 || ctype_digit($word)) {
          continue;
        }
        $word = tag_singular($word);
        if (isset($stop[$word]) || strlen($word) < 4) {
          continue;
        }
        $freq[$word] = ($freq[$word] ?? 0) + $weight;
      }
    }

    // Words already covered by a chosen topic. Without this, a post tagged
    // "legacy-code" also picks up "legacy" and "code", which say nothing extra
    // and split the same posts across three tags that should be one.
    $covered = [];
    foreach (array_keys($picked) as $slug) {
      foreach (explode('-', $slug) as $part) {
        $covered[$part] = true;
      }
      $covered[$slug] = true;
    }

    arsort($freq);
    foreach ($freq as $word => $weight) {
      if (count($picked) >= $limit) {
        break;
      }
      // Has to earn its place: title mention plus at least one in the body, or
      // four mentions in the body alone. A bar of 3 lets any title word through,
      // which is how you end up tagging a post "Deserve".
      if ($weight < 4) {
        continue;
      }
      $slug = tag_slugify($word);
      if ($slug === '' || isset($picked[$slug]) || isset($covered[$slug])) {
        continue;
      }
      $picked[$slug] = ucfirst($word);
      $scores[$slug] = $weight;
      $covered[$slug] = true;
    }
  }

  arsort($scores);
  $out = [];
  foreach (array_keys($scores) as $slug) {
    if (!isset($picked[$slug])) {
      continue;
    }
    $out[] = ['slug' => $slug, 'label' => $picked[$slug]];
    if (count($out) >= $limit) {
      break;
    }
  }
  return $out;
}

/**
 * Replace a post's tags with a freshly derived set.
 *
 * post_tags has no foreign key (see migration 003), so rows are cleaned up here
 * rather than by the database.
 */
function sync_post_tags(PDO $conn, string $postId, array $tags): void {
  try {
    $del = $conn->prepare("DELETE FROM post_tags WHERE post_id = ?");
    $del->execute([$postId]);

    if (!$tags) {
      return;
    }

    $find   = $conn->prepare("SELECT tag_id FROM tags WHERE slug = ?");
    $create = $conn->prepare("INSERT INTO tags (slug, label) VALUES (?, ?)");
    $link   = $conn->prepare("INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)");

    foreach ($tags as $tag) {
      $find->execute([$tag['slug']]);
      $tagId = $find->fetchColumn();
      if ($tagId === false) {
        $create->execute([$tag['slug'], $tag['label']]);
        $tagId = $conn->lastInsertId();
      }
      $link->execute([$postId, (int) $tagId]);
    }
  } catch (PDOException $e) {
    // Tagging is decoration. Never fail a post because of it.
    error_log('sync_post_tags failed: ' . $e->getMessage());
  }
}

/** Remove tag links for a deleted post. */
function delete_post_tags(PDO $conn, string $postId): void {
  try {
    $conn->prepare("DELETE FROM post_tags WHERE post_id = ?")->execute([$postId]);
  } catch (PDOException $e) {
    error_log('delete_post_tags failed: ' . $e->getMessage());
  }
}

/**
 * Tags for a set of posts.
 *
 * @return array<string, array<int, array{slug:string,label:string}>> Keyed by post_id.
 */
function fetch_tags_for_posts(PDO $conn, array $postIds): array {
  if (!$postIds) {
    return [];
  }
  try {
    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $conn->prepare("
      SELECT pt.post_id, t.slug, t.label
      FROM post_tags pt
      JOIN tags t ON t.tag_id = pt.tag_id
      WHERE pt.post_id IN ($placeholders)
      ORDER BY t.label ASC");
    $stmt->execute(array_values($postIds));

    $byPost = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
      $byPost[$row['post_id']][] = ['slug' => $row['slug'], 'label' => $row['label']];
    }
    return $byPost;
  } catch (PDOException $e) {
    error_log('fetch_tags_for_posts failed: ' . $e->getMessage());
    return [];
  }
}

/**
 * The most-used tags, for the sidebar. Only counts posts that are publicly
 * visible, so a tag cannot be kept alive by hidden content.
 */
function popular_tags(PDO $conn, int $limit = 12, bool $moderationEnabled = false): array {
  try {
    $visible = $moderationEnabled ? 'AND ' . visibility_sql('p') : '';
    $stmt = $conn->query("
      SELECT t.slug, t.label, COUNT(*) AS post_count
      FROM post_tags pt
      JOIN tags t ON t.tag_id = pt.tag_id
      JOIN blog_posts p ON p.post_id = pt.post_id
      WHERE 1=1 $visible
      GROUP BY t.tag_id, t.slug, t.label
      ORDER BY post_count DESC, t.label ASC
      LIMIT " . (int) $limit);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log('popular_tags failed: ' . $e->getMessage());
    return [];
  }
}

/** Look up a tag's label by slug, or null if it does not exist. */
function tag_label(PDO $conn, string $slug): ?string {
  try {
    $stmt = $conn->prepare("SELECT label FROM tags WHERE slug = ?");
    $stmt->execute([$slug]);
    $label = $stmt->fetchColumn();
    return $label === false ? null : (string) $label;
  } catch (PDOException $e) {
    return null;
  }
}
