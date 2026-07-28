<?php
/*
 * Automated offensive-content detection.
 *
 * Everything here runs locally in PHP with no network calls, because the host
 * (InfinityFree) blocks outbound HTTP from user scripts, so a third-party
 * moderation API would simply time out on every submission.
 *
 * THE SHAPE OF IT
 *   moderate_content() scores a submission and returns one of three verdicts:
 *
 *     block  The submission is refused and the author sees an explanation.
 *     flag   The submission is published, but recorded with status 'flagged'
 *            so it shows up in the moderate.php review queue.
 *     allow  Nothing interesting; published normally.
 *
 *   Scoring rather than a single blocklist hit matters: it lets an unambiguous
 *   slur block on its own while ordinary swearing only becomes interesting when
 *   it piles up, and it lets weak signals (shouting + link spam + a spam phrase)
 *   combine into a block that none of them would earn alone.
 *
 * WHY THE MIDDLE TIER EXISTS
 *   Text classification without context gets ambiguous cases wrong in both
 *   directions. Rather than guess, anything genuinely uncertain is published
 *   and queued for a human. That keeps false positives from silently eating
 *   legitimate posts while still surfacing them for review.
 *
 * Term lists live in moderation_words.php.
 */

/** Score at or above which a submission is refused outright. */
const MODERATION_BLOCK_THRESHOLD = 100;

/** Score at or above which a submission is published but queued for review. */
const MODERATION_FLAG_THRESHOLD = 40;

/**
 * Whether flagged content waits for approval before the public can see it.
 *
 * true  - flagged submissions are held. Safer, and the right posture once the
 *         site carries ads: an ad network holds the publisher responsible for
 *         everything on the page, including what other people posted, so
 *         "publish now, review later" puts the account on the line for content
 *         nobody has looked at yet.
 * false - flagged submissions publish immediately and are queued for review.
 *         Friendlier on a site with no commercial exposure.
 *
 * Either way the author is told what happened; see the pending-review state in
 * pages/post.php. Nothing is silently swallowed.
 */
const MODERATION_HIDE_FLAGGED = true;

/** Load and cache the term lists. */
function moderation_lists(): array {
  static $lists = null;
  if ($lists === null) {
    $lists = require __DIR__ . '/moderation_words.php';
  }
  return $lists;
}

/**
 * Fold characters people use to dodge filters back to plain ASCII: Cyrillic
 * and Greek lookalikes, full-width forms, and accented Latin.
 */
function moderation_defang(string $text): string {
  $map = [
    // Cyrillic homoglyphs
    'а' => 'a', 'в' => 'b', 'е' => 'e', 'к' => 'k', 'м' => 'm', 'н' => 'h',
    'о' => 'o', 'р' => 'p', 'с' => 'c', 'т' => 't', 'у' => 'y', 'х' => 'x',
    'і' => 'i', 'ѕ' => 's', 'ј' => 'j', 'ԁ' => 'd', 'ɡ' => 'g',
    // Greek homoglyphs
    'α' => 'a', 'β' => 'b', 'ε' => 'e', 'ι' => 'i', 'κ' => 'k', 'ο' => 'o',
    'ρ' => 'p', 'τ' => 't', 'υ' => 'u', 'χ' => 'x', 'ν' => 'v',
    // Accented Latin
    'á' => 'a', 'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ã' => 'a', 'å' => 'a',
    'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
    'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
    'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'ö' => 'o', 'õ' => 'o', 'ø' => 'o',
    'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
    'ñ' => 'n', 'ç' => 'c', 'ß' => 'ss', 'ÿ' => 'y',
  ];
  $text = strtr($text, $map);

  // Full-width Latin (ｆｕｃｋ) back to ASCII.
  return preg_replace_callback('/[\x{FF21}-\x{FF3A}\x{FF41}-\x{FF5A}]/u', function ($m) {
    $cp = mb_ord($m[0], 'UTF-8');
    return chr($cp - 0xFEE0);
  }, $text) ?? $text;
}

/**
 * Produce the text variants the rules match against.
 *
 *   text      Lowercased, punctuation collapsed to spaces. Digits and word
 *             shapes intact, so the spam and threat regexes still work.
 *   folded    text with leet substitutions applied and allowlisted words
 *             removed. This is what the term lists are matched against.
 *   squeezed  folded with runs of single letters joined, so "f u c k" and
 *             "f.u.c.k" (whose dots already became spaces) collapse to "fuck".
 */
function moderation_variants(string $input): array {
  $lower = mb_strtolower(moderation_defang($input), 'UTF-8');

  // Anything that is not a letter, digit, or space becomes a space. This is
  // what turns "f.u.c.k" into "f u c k" for the squeezed pass below.
  $text = preg_replace('/[^a-z0-9\s]+/u', ' ', $lower) ?? $lower;
  $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

  // Leet folding. Applied only to the wordlist variant, because it mangles the
  // digits that the spam patterns rely on.
  $folded = strtr($text, [
    '4' => 'a', '3' => 'e', '1' => 'i', '0' => 'o', '5' => 's',
    '7' => 't', '8' => 'b', '9' => 'g', '6' => 'g', '2' => 'z',
  ]);

  // Drop ordinary words that overlap a listed term, so they can never trip it.
  $allow = moderation_lists()['allow'] ?? [];
  if ($allow) {
    $folded = preg_replace('/\b(' . implode('|', $allow) . ')\b/', ' ', $folded) ?? $folded;
  }

  // Join runs of three or more single letters: "f u c k off" -> "fuck off".
  $squeezed = preg_replace_callback('/(?:\b[a-z]\b[ ]?){3,}/', function ($m) {
    $run = str_replace(' ', '', $m[0]);
    // A neighbouring one-letter word gets swallowed into the run -- "a f.a.g"
    // squeezes to "afag", which no longer starts on a word boundary. So also
    // offer the run without its first and last letter. Matching is whole-word,
    // and ordinary prose almost never has three single letters in a row, so
    // the extra candidates cost nothing in false positives.
    $candidates = [$run];
    if (strlen($run) > 3) {
      $candidates[] = substr($run, 1);
      $candidates[] = substr($run, 0, -1);
    }
    return ' ' . implode(' ', $candidates) . ' ';
  }, $folded) ?? $folded;

  return [
    'text'     => $text,
    'folded'   => $folded,
    'squeezed' => trim(preg_replace('/\s+/', ' ', $squeezed) ?? $squeezed),
  ];
}

/**
 * Turn a plain term into a whole-word regex that tolerates stretched letters,
 * so "fuck" also matches "fuuuck" and "fuckkk". Word boundaries keep it from
 * firing inside unrelated words.
 */
function moderation_term_pattern(string $term): string {
  $out = '';
  foreach (str_split($term) as $ch) {
    if ($ch === ' ') {
      $out .= '\s+';
    } elseif (ctype_alnum($ch)) {
      $out .= $ch . '+';
    } else {
      $out .= preg_quote($ch, '/');
    }
  }
  return $out;
}

/** Count how many distinct terms from a list appear in the given variants. */
function moderation_count_terms(array $terms, array $variants): int {
  $hits = 0;
  foreach ($terms as $term) {
    $pattern = '/\b' . moderation_term_pattern($term) . '\b/';
    if (preg_match($pattern, $variants['folded']) || preg_match($pattern, $variants['squeezed'])) {
      $hits++;
    }
  }
  return $hits;
}

/** Whether any raw regex in the list matches. */
function moderation_match_patterns(array $patterns, string $subject): bool {
  foreach ($patterns as $pattern) {
    if (preg_match('/' . $pattern . '/', $subject)) {
      return true;
    }
  }
  return false;
}

/** Count links, including bare domains people paste without a scheme. */
function moderation_count_links(string $original): int {
  $n = preg_match_all('~https?://~i', $original);
  $n += preg_match_all('~\bwww\.[a-z0-9-]+\.[a-z]{2,}~i', $original);
  $n += preg_match_all('~\b[a-z0-9-]{2,}\.(?:com|net|org|info|biz|ru|cn|xyz|top|club|online|site|shop|link)\b~i', $original);
  return (int) $n;
}

/**
 * Score a submission.
 *
 * @return array{verdict:string,score:int,reasons:string[],message:string}
 */
function moderate_content(string $title, string $content, string $author = ''): array {
  $original = trim($title . "\n" . $content . "\n" . $author);
  if ($original === '') {
    return ['verdict' => 'allow', 'score' => 0, 'reasons' => [], 'message' => ''];
  }

  $lists = moderation_lists();
  $v = moderation_variants($original);

  $score = 0;
  $reasons = [];

  /** Record a reason once, adding its points. */
  $add = function (string $reason, int $points) use (&$score, &$reasons): void {
    $score += $points;
    if (!in_array($reason, $reasons, true)) {
      $reasons[] = $reason;
    }
  };

  // --- Unambiguous: any single hit blocks -----------------------------------

  if (moderation_count_terms($lists['hate_severe'] ?? [], $v) > 0
    || moderation_match_patterns($lists['hate_severe_patterns'] ?? [], $v['squeezed'])
    || moderation_match_patterns($lists['hate_severe_patterns'] ?? [], $v['folded'])) {
    $add('hate_speech', 100);
  }

  if (moderation_match_patterns($lists['threat_patterns'] ?? [], $v['text'])) {
    $add('threat', 100);
  }

  // --- Ambiguous or cumulative ----------------------------------------------

  if ($n = moderation_count_terms($lists['hate_mild'] ?? [], $v)) {
    $add('slur_ambiguous', min(40 + ($n - 1) * 10, 60));
  }

  if ($n = moderation_count_terms($lists['sexual'] ?? [], $v)) {
    $add('sexual_content', min(55 + ($n - 1) * 45, 145));
  }

  if ($n = moderation_count_terms($lists['profanity'] ?? [], $v)) {
    $add('profanity', min($n * 18, 54));
  }

  if ($n = moderation_count_terms($lists['profanity_mild'] ?? [], $v)) {
    $add('profanity_mild', min($n * 6, 24));
  }

  if (moderation_match_patterns($lists['harassment_patterns'] ?? [], $v['text'])) {
    $add('harassment', 45);
  }

  // --- Spam -----------------------------------------------------------------

  $spamHits = moderation_count_terms($lists['spam'] ?? [], $v);
  if (moderation_match_patterns($lists['spam_patterns'] ?? [], $v['text'])) {
    $spamHits++;
  }
  if ($spamHits > 0) {
    $add('spam', min(45 + ($spamHits - 1) * 35, 115));
  }

  $links = moderation_count_links($original);
  if ($links > 6) {
    $add('link_spam', 60);
  } elseif ($links > 3) {
    $add('link_spam', 25);
  }

  // --- Shape signals --------------------------------------------------------

  $letters = preg_replace('/[^a-z]/i', '', $original) ?? '';
  if (strlen($letters) > 40) {
    $upper = strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '');
    if ($upper / strlen($letters) > 0.65) {
      $add('shouting', 15);
    }
    // Text with almost no vowels is keyboard mash or an obfuscation attempt.
    $vowels = strlen(preg_replace('/[^aeiou]/i', '', $letters) ?? '');
    if ($vowels / strlen($letters) < 0.15) {
      $add('gibberish', 20);
    }
  }

  if (preg_match('/(.)\1{7,}/u', $original)) {
    $add('character_spam', 15);
  }

  // The same word over and over, dominating the text.
  $words = preg_split('/\s+/', $v['text'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
  if (count($words) >= 12) {
    $counts = array_count_values($words);
    arsort($counts);
    $top = (int) reset($counts);
    if ($top >= 10 && $top / count($words) > 0.3) {
      $add('repetition', 30);
    }
  }

  $verdict = $score >= MODERATION_BLOCK_THRESHOLD ? 'block'
    : ($score >= MODERATION_FLAG_THRESHOLD ? 'flag' : 'allow');

  return [
    'verdict' => $verdict,
    'score'   => $score,
    'reasons' => $reasons,
    'message' => $verdict === 'block' ? moderation_block_message($reasons) : '',
  ];
}

/** The explanation shown to someone whose submission was refused. */
function moderation_block_message(array $reasons): string {
  if (in_array('hate_speech', $reasons, true) || in_array('slur_ambiguous', $reasons, true)) {
    return 'This post looks like it contains a slur or hate speech, so it was not published. If that is wrong, rephrase and try again.';
  }
  if (in_array('threat', $reasons, true)) {
    return 'This post reads as a threat of violence, so it was not published.';
  }
  if (in_array('sexual_content', $reasons, true)) {
    return 'This post looks like explicit sexual content, which this site does not host.';
  }
  if (in_array('spam', $reasons, true) || in_array('link_spam', $reasons, true)) {
    return 'This post looks like spam - too many links or promotional phrases - so it was not published.';
  }
  if (in_array('harassment', $reasons, true)) {
    return 'This post reads as a personal attack, so it was not published.';
  }
  return 'This post was caught by the automated content filter. Try rewriting it in plainer terms.';
}

/** Human-readable label for a reason code, used in the review queue. */
function moderation_reason_label(string $reason): string {
  return [
    'hate_speech'     => 'Hate speech',
    'slur_ambiguous'  => 'Possible slur',
    'threat'          => 'Threat of violence',
    'harassment'      => 'Personal attack',
    'sexual_content'  => 'Sexual content',
    'profanity'       => 'Profanity',
    'profanity_mild'  => 'Mild profanity',
    'spam'            => 'Spam phrasing',
    'link_spam'       => 'Excessive links',
    'shouting'        => 'ALL CAPS',
    'gibberish'       => 'Gibberish',
    'character_spam'  => 'Repeated characters',
    'repetition'      => 'Repeated words',
  ][$reason] ?? ucfirst(str_replace('_', ' ', $reason));
}

/**
 * A salted, one-way hash of the visitor's IP.
 *
 * Stored instead of the address itself so rate limiting and repeat-abuser
 * checks work without the site ever keeping an identifiable IP. Deliberately
 * reads REMOTE_ADDR only and ignores X-Forwarded-For, which any client can set
 * - trusting it would let an abuser reset their own rate limit at will.
 */
function client_ip_hash(): string {
  global $ipSalt, $dbName;
  $salt = (string) ($ipSalt ?? '');
  if ($salt === '') {
    $salt = 'exchangemyideas:' . (string) ($dbName ?? 'blog');
  }
  return hash('sha256', $salt . '|' . (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
}

/**
 * Whether this IP has submitted too much too fast.
 *
 * $table is a trusted constant from the caller, never user input, so it is
 * interpolated; the interval likewise, because MySQL will not accept a bound
 * parameter inside INTERVAL when prepare emulation is off.
 */
function moderation_rate_limited(PDO $conn, string $table, string $ipHash, int $max, int $seconds): bool {
  try {
    $stmt = $conn->prepare("
      SELECT COUNT(*) FROM `$table`
      WHERE ip_hash = ? AND date_posted > DATE_SUB(NOW(), INTERVAL " . (int) $seconds . " SECOND)
    ");
    $stmt->execute([$ipHash]);
    return (int) $stmt->fetchColumn() >= $max;
  } catch (PDOException $e) {
    // Never let a rate-limit check take the site down.
    error_log('rate limit check failed: ' . $e->getMessage());
    return false;
  }
}
