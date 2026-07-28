<?php
/*
 * Tests for the two pieces of logic that are easy to get quietly wrong:
 * the moderation engine and automatic tagging.
 *
 * Both are pure functions over text with no database involved, so they can be
 * checked directly:
 *
 *     php tests/run.php
 *
 * Exits non-zero if anything fails. Not deployed -- see the exclude list in
 * .github/workflows/deploy.yml.
 *
 * The moderation cases are as much a false-positive guard as a detection test.
 * A filter that eats "I spent a month in Nigeria" or "this deploy is crap" is
 * worse than no filter, so those cases are pinned here on purpose.
 */

require __DIR__ . '/../src/moderation.php';
require __DIR__ . '/../src/tags.php';
require __DIR__ . '/../src/emoji.php';
require __DIR__ . '/../src/urls.php';

$failures = 0;
$checks = 0;

function section(string $name): void {
  echo "\n\033[1m$name\033[0m\n";
}

function check(bool $ok, string $label, string $detail = ''): void {
  global $failures, $checks;
  $checks++;
  if ($ok) {
    echo "  \033[32mok\033[0m   $label\n";
  } else {
    $failures++;
    echo "  \033[31mFAIL\033[0m $label" . ($detail !== '' ? "  ($detail)" : '') . "\n";
  }
}

// ---------------------------------------------------------------------------
section('Moderation - legitimate posts must pass');
// ---------------------------------------------------------------------------

$allowed = [
  'ordinary post' => [
    'Legacy code is just code that outlived its documentation',
    'Every codebase people call "legacy" was once someone\'s best effort under a real deadline. The code did not rot -- the context around it did.',
  ],
  // The Scunthorpe problem: substrings of listed terms inside innocent words.
  'place and personal names' => [
    'Shipping from Scunthorpe to Penistone',
    'Our therapist met the assassin in Sussex. Grapes, cocktails, and a cockpit view.',
  ],
  'Niger and Nigeria' => [
    'Notes on Nigeria',
    'I spent a month in Nigeria and Niger doing classroom analysis. The bassist was Nigerian.',
  ],
  'mild swearing' => [
    'This deploy is crap',
    'Honestly the whole pipeline is crap and I am pissed about it, but we shipped anyway.',
  ],
  'one strong swear' => [
    'A rant',
    'This fucking build system is fucked.',
  ],
  'all caps but short' => ['WOW OK', 'Short and loud.'],
  'markdown and a link' => [
    'Reading commit history',
    'Use `git log -p` on a confusing file. See [the docs](https://example.com/docs) for more.',
  ],
  'long all-caps only' => [
    'PLEASE READ',
    'THIS IS ABSOLUTELY OUTRAGEOUS AND I DEMAND THAT EVERYONE READ IT RIGHT NOW OK THANKS',
  ],
];

foreach ($allowed as $label => [$title, $body]) {
  $r = moderate_content($title, $body);
  check($r['verdict'] === 'allow', $label, "got {$r['verdict']} score {$r['score']} [" . implode(',', $r['reasons']) . ']');
}

// ---------------------------------------------------------------------------
section('Moderation - abuse must be blocked');
// ---------------------------------------------------------------------------

$blocked = [
  'slurs'                => ['hey', 'you are all a bunch of faggots and retards'],
  'spaced-out slur'      => ['hey', 'f a g g o t s everywhere in this thread'],
  'leetspeak slur'       => ['hey', 'ch1nk and g00k go home'],
  'n-word'               => ['hey', 'niggers get out'],
  'punctuated slur'      => ['hey', 'you are a f.a.g.g.o.t'],
  'threat of violence'   => ['hey', 'i am going to kill you and your whole family'],
  'suicide bait'         => ['hey', 'kys loser'],
  'explicit sexual'      => ['free stuff', 'free porn and hentai cumshot gangbang videos here'],
  'link and pill spam'   => [
    'Buy now',
    'Buy viagra and cialis now! Make $5000 per day with bitcoin guaranteed profit. '
      . 'http://a.xyz http://b.top http://c.com http://d.club http://e.online http://f.site http://g.shop',
  ],
];

foreach ($blocked as $label => [$title, $body]) {
  $r = moderate_content($title, $body);
  check($r['verdict'] === 'block', $label, "got {$r['verdict']} score {$r['score']} [" . implode(',', $r['reasons']) . ']');
  check($r['message'] !== '', "$label - explains itself to the author");
}

// ---------------------------------------------------------------------------
section('Moderation - ambiguous content goes to the review queue');
// ---------------------------------------------------------------------------

$flagged = [
  'ambiguous slur'     => ['hey', 'that guy is such a fag about it'],
  'sustained swearing' => ['A bigger rant', 'This fucking build is fucked, what a shitty bitch of a bastard pipeline.'],
  'single adult link'  => ['found this', 'I found this creator on onlyfans yesterday'],
  'soft spam'          => ['offer', 'Try our casino and payday loan service today.'],
  'personal attack'    => ['hey', 'you are such a worthless pathetic person, nobody likes you'],
];

foreach ($flagged as $label => [$title, $body]) {
  $r = moderate_content($title, $body);
  check($r['verdict'] === 'flag', $label, "got {$r['verdict']} score {$r['score']} [" . implode(',', $r['reasons']) . ']');
}

check(moderate_content('', '')['verdict'] === 'allow', 'empty input is not an error');

// ---------------------------------------------------------------------------
section('Tagging - topics are recognised');
// ---------------------------------------------------------------------------

/** @return string[] */
function slugs_for(string $title, string $body): array {
  return array_column(extract_tags($title, $body), 'slug');
}

$tagCases = [
  ['ai', 'Will AI replace engineers?',
    'AI is not going to replace engineers, but engineers who use AI will replace those who do not. The models keep getting better at code.'],
  ['legacy-code', 'Legacy code is just code that outlived its documentation',
    'Every codebase people call legacy was once a best effort. Before you rewrite it, recover the why. Legacy systems carry real context.'],
  ['automation', 'Automate the boring parts',
    'A good pipeline automates what you would otherwise do by hand. Automation is a cron job plus a script plus patience.'],
  ['career', 'What I learned from a bad interview',
    'The interview process for this job was a mess. Hiring managers should remember that a candidate is interviewing them too.'],
  ['security', 'SQL injection is still everywhere',
    'A vulnerability like SQL injection survives because nobody audits old code. Security is a habit, not a sprint.'],
];

foreach ($tagCases as [$expected, $title, $body]) {
  $slugs = slugs_for($title, $body);
  check(in_array($expected, $slugs, true), "\"$title\" → $expected", 'got [' . implode(', ', $slugs) . ']');
}

// ---------------------------------------------------------------------------
section('Tagging - behaviour and limits');
// ---------------------------------------------------------------------------

$slugs = slugs_for('Thoughts on beekeeping', 'Beekeeping taught me patience. A hive is a system, and beekeeping rewards people who observe the hive before touching it.');
check($slugs !== [], 'unknown subject still gets a keyword tag', 'got [' . implode(', ', $slugs) . ']');

// A dictionary topic should not be shadowed by its own component words.
$slugs = slugs_for(
  'Why legacy code deserves more respect',
  'Every codebase people call legacy was a real effort under a deadline. Refactoring without recovering the why is how you rewrite the same bugs. Technical debt is a story, not a number.'
);
check(in_array('legacy-code', $slugs, true), 'the topic itself is picked', 'got [' . implode(', ', $slugs) . ']');
check(!in_array('legacy', $slugs, true), 'no redundant "legacy" beside "legacy-code"', 'got [' . implode(', ', $slugs) . ']');
check(!in_array('code', $slugs, true), 'no redundant "code" beside "legacy-code"', 'got [' . implode(', ', $slugs) . ']');
check(!in_array('deserve', $slugs, true), 'a bare title verb is not a topic', 'got [' . implode(', ', $slugs) . ']');

$slugs = slugs_for('A post about #rustlang', 'Nothing else notable here at all.');
check(in_array('rustlang', $slugs, true), 'an explicit hashtag is honoured', 'got [' . implode(', ', $slugs) . ']');

$slugs = slugs_for(
  'AI and legacy code and automation and security and testing and databases',
  'ai llm model legacy refactor technical debt automation pipeline security vulnerability test coverage sql schema query'
);
check(count($slugs) <= TAG_LIMIT, 'never exceeds TAG_LIMIT tags', 'got ' . count($slugs));

check(slugs_for('', '') === [], 'empty post yields no tags');

foreach (slugs_for('Testing slugs', 'Some words about tests and coverage and CI pipelines.') as $slug) {
  check((bool) preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug), "slug \"$slug\" is url-safe");
}

$a = slugs_for('Models and modeling', 'The model and the models behave the same way when you have many models.');
check(in_array('ai', $a, true), 'plural terms match the dictionary', 'got [' . implode(', ', $a) . ']');

// ---------------------------------------------------------------------------
section('Emoji - every tag gets a stable icon');
// ---------------------------------------------------------------------------

check(tag_emoji('ai') === '🤖', 'curated topics use their curated icon');
check(tag_emoji('beekeeping') === '🐝', 'keyword map is consulted for non-dictionary tags');
check(tag_emoji('beekeeping-notes') === '🐝', 'a compound slug matches on its parts');

$invented = 'zzblarghfoo';
check(tag_emoji($invented) !== '', 'an unrecognised tag still gets an icon');
check(tag_emoji($invented) === tag_emoji($invented), 'the same tag always gets the same icon');
check(
  in_array(tag_emoji($invented), fallback_emoji_pool(), true),
  'unrecognised tags fall back to the neutral pool'
);

foreach (array_keys(tag_topics()) as $slug) {
  check(tag_emoji($slug) !== '', "topic \"$slug\" has an icon");
}

check(reason_emoji('hate_speech') !== '', 'moderation reasons have icons');
check(reason_emoji('not_a_real_code') === '🚩', 'an unknown reason code falls back');

check(activity_emoji(0, 0) === '🌱', 'a quiet post reads as new');
check(activity_emoji(30, 0) === '🔥', 'a busy thread reads as busy');

// ---------------------------------------------------------------------------
section('URLs - one canonical spelling per page');
// ---------------------------------------------------------------------------

/*
 * These are cheap assertions guarding an expensive mistake. Every one of these
 * paths is a public URL that search engines will remember, and a duplicate
 * spelling ("/page/1" alongside "/") splits a page's ranking between two URLs
 * that hold the same content.
 */
check(feed_path([]) === '/', 'the feed is "/" ');
check(feed_path(['page' => 1]) === '/', 'page 1 is "/", never "/page/1"');
check(feed_path(['page' => 2]) === '/page/2', 'page 2 is "/page/2"');
check(feed_path(['sort' => 'recent']) === '/', 'the default sort adds nothing');
check(feed_path(['sort' => 'liked']) === '/?sort=liked', 'a non-default sort is a query');

check(tag_path('ai') === '/topic/ai', 'a topic is "/topic/{slug}"');
check(tag_path('ai', 1) === '/topic/ai', 'a topic page 1 has no /page/1');
check(tag_path('ai', 3) === '/topic/ai/page/3', 'a topic pages under itself');
check(feed_path(['tag' => 'legacy-code', 'page' => 2]) === '/topic/legacy-code/page/2', 'tag + page compose');

check(feed_path(['search' => 'legacy']) === '/search?q=legacy', 'search is /search?q=');
check(
  str_starts_with(feed_path(['search' => 'a b', 'tag' => 'ai']), '/search?'),
  'search wins over tag in the path, tag rides along as a query'
);

check(post_path('abc-123') === '/post/abc-123', 'a post is "/post/{id}"');
check(post_edit_path('abc-123') === '/post/abc-123/edit', 'editing hangs off the post');

// A title with a slash or space must not be able to forge a path segment.
$hostile = post_path('a/b c?d=e#f');
check(!str_contains(substr($hostile, 6), '/'), 'an id cannot forge a path segment', $hostile);
check(!str_contains($hostile, ' ') && !str_contains($hostile, '#'), 'an id is fully encoded', $hostile);

// ---------------------------------------------------------------------------
section('URLs - the host header is never trusted');
// ---------------------------------------------------------------------------

/*
 * site_url() output lands in canonical tags, OG metadata, and the RSS feed. A
 * poisoned Host header would rewrite where every one of those points, so the
 * validation is pinned here.
 */
$_SERVER['HTTP_HOST'] = 'evil.com/../x';
check(str_contains(site_url('/'), SITE_HOST), 'a malformed host falls back to SITE_HOST');
$_SERVER['HTTP_HOST'] = "exchangemyideas.marinmirasol.com\r\nX-Injected: 1";
check(!str_contains(site_url('/'), 'X-Injected'), 'a CRLF host cannot inject a header');
$_SERVER['HTTP_HOST'] = 'localhost:8080';
check(str_contains(site_url('/x'), 'localhost:8080'), 'a plain host:port is allowed through');
unset($_SERVER['HTTP_HOST']);

// ---------------------------------------------------------------------------
section('SQL - no named placeholder is reused within one statement');
// ---------------------------------------------------------------------------

/*
 * config.php sets PDO::ATTR_EMULATE_PREPARES => false, so MySQL prepares
 * statements server-side and PDO cannot bind one named parameter to several
 * positions: it throws "Invalid parameter number".
 *
 * This is worth a test rather than a code comment because it fails silently in
 * the worst way. The feed catches PDOException and returns an empty result, so
 * a reused placeholder does not look like an error - it looks like "no posts
 * match your search". Exactly that shipped in the original search query.
 */
$root = dirname(__DIR__);
$phpFiles = array_merge(glob("$root/src/*.php"), glob("$root/src/views/*.php"), glob("$root/*.php"));
$reused = [];

foreach ($phpFiles as $file) {
  $src = file_get_contents($file) ?: '';
  if (!preg_match_all('/(?:prepare|query|exec)\s*\(\s*([\'"])(.*?)\1\s*[,)]/s', $src, $matches)) {
    continue;
  }
  foreach ($matches[2] as $sql) {
    preg_match_all('/:[a-zA-Z_][a-zA-Z0-9_]*/', $sql, $found);
    foreach (array_count_values($found[0]) as $name => $count) {
      if ($count > 1) {
        $reused[] = basename($file) . " uses $name x$count";
      }
    }
  }
}

check($reused === [], 'every placeholder appears exactly once per statement', implode('; ', $reused));
check(count($phpFiles) > 10, 'the scan actually found files to check', count($phpFiles) . ' files');

// ---------------------------------------------------------------------------

echo "\n" . ($failures === 0
  ? "\033[32mAll $checks checks passed.\033[0m\n"
  : "\033[31m$failures of $checks checks failed.\033[0m\n");

exit($failures === 0 ? 0 : 1);
