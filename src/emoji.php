<?php
/*
 * Emoji derived from content, not stored with it.
 *
 * Tags are generated automatically (see tags.php), so their icons have to be
 * generated too -- nobody is around to pick one. Everything here is a pure
 * function of a slug or code, which has two useful consequences:
 *
 *   - No schema change and no backfill. Improving a mapping here immediately
 *     improves every tag already in the database.
 *   - The same tag always looks the same, everywhere it appears. That is what
 *     makes the icon a recognisable landmark rather than decoration.
 *
 * Anything unrecognised still gets an icon, chosen deterministically from a
 * neutral set, so a tag chip is never left visually half-built.
 */

/** Curated icons for the topics in the tag dictionary. */
function topic_emoji_map(): array {
  return [
    'ai'           => '🤖',
    'legacy-code'  => '🏚️',
    'automation'   => '⚙️',
    'engineering'  => '🛠️',
    'testing'      => '🧪',
    'security'     => '🔐',
    'databases'    => '🗄️',
    'web'          => '🌐',
    'devops'       => '🚀',
    'open-source'  => '🐙',
    'career'       => '💼',
    'startups'     => '📈',
    'productivity' => '⏱️',
    'design'       => '🎨',
    'writing'      => '✍️',
    'learning'     => '📚',
    'remote-work'  => '🏡',
    'money'        => '💰',
    'health'       => '🫀',
    'philosophy'   => '🧠',
    'science'      => '🔬',
    'music'        => '🎵',
    'gaming'       => '🎮',
    'community'    => '💬',
  ];
}

/**
 * Icons for common keyword tags, which pass-2 extraction can produce for any
 * subject the dictionary has never heard of.
 *
 * Matched on the singular stem, so "bees" and "bee" land on the same icon.
 */
function keyword_emoji_map(): array {
  return [
    'bee' => '🐝', 'beekeeping' => '🐝', 'honey' => '🍯',
    'coffee' => '☕', 'tea' => '🍵', 'food' => '🍽️', 'cooking' => '👨‍🍳',
    'recipe' => '🍳', 'bread' => '🍞', 'garden' => '🌱', 'plant' => '🪴',
    'dog' => '🐕', 'cat' => '🐈', 'bird' => '🐦', 'ocean' => '🌊',
    'mountain' => '⛰️', 'travel' => '✈️', 'city' => '🏙️', 'weather' => '🌦️',
    'winter' => '❄️', 'summer' => '☀️', 'space' => '🚀', 'star' => '⭐',
    'book' => '📖', 'reading' => '📖', 'story' => '📜', 'poetry' => '🪶',
    'film' => '🎬', 'movie' => '🎬', 'photo' => '📷', 'camera' => '📷',
    'guitar' => '🎸', 'piano' => '🎹', 'art' => '🖼️', 'drawing' => '✏️',
    'running' => '🏃', 'cycling' => '🚴', 'swimming' => '🏊', 'sport' => '🏅',
    'football' => '⚽', 'basketball' => '🏀', 'chess' => '♟️', 'puzzle' => '🧩',
    'sleep' => '😴', 'coffee-shop' => '☕', 'meeting' => '📅', 'email' => '📧',
    'phone' => '📱', 'laptop' => '💻', 'keyboard' => '⌨️', 'robot' => '🤖',
    'car' => '🚗', 'train' => '🚆', 'bike' => '🚲', 'house' => '🏠',
    'family' => '👪', 'friend' => '🤝', 'love' => '❤️', 'baby' => '👶',
    'school' => '🏫', 'teacher' => '🧑‍🏫', 'student' => '🎓', 'exam' => '📝',
    'math' => '🔢', 'physic' => '⚛️', 'chemistry' => '⚗️', 'biology' => '🧬',
    'history' => '🏛️', 'language' => '🗣️', 'translation' => '🌍',
    'idea' => '💡', 'question' => '❓', 'answer' => '✅', 'problem' => '🧯',
    'time' => '⏳', 'future' => '🔮', 'change' => '🔄', 'growth' => '🌿',
    'failure' => '💥', 'success' => '🏆', 'mistake' => '🩹', 'lesson' => '🎯',
    'tool' => '🔧', 'bug' => '🐛', 'server' => '🖥️', 'cloud' => '☁️',
    'data' => '📊', 'chart' => '📊', 'graph' => '📉', 'number' => '🔢',
    'privacy' => '🕵️', 'law' => '⚖️', 'politics' => '🏛️', 'news' => '📰',
    'climate' => '🌍', 'energy' => '⚡', 'water' => '💧', 'fire' => '🔥',
  ];
}

/**
 * The pool an unrecognised tag draws from.
 *
 * Intentionally abstract shapes rather than nouns: a wrong-but-specific icon
 * ("beekeeping 🚗") reads as a bug, while a neutral one reads as a bullet.
 */
function fallback_emoji_pool(): array {
  return ['💡', '🔷', '🔶', '🧭', '📌', '🧵', '🪄', '🌀', '📎', '🔖', '🧱', '🪧'];
}

/**
 * An icon for a tag slug. Always returns something.
 *
 * Order matters: curated topics first, then a keyword match (whole slug, then
 * each word within it), then a stable pick from the neutral pool.
 */
function tag_emoji(string $slug): string {
  static $cache = [];
  if (isset($cache[$slug])) {
    return $cache[$slug];
  }

  $topics = topic_emoji_map();
  if (isset($topics[$slug])) {
    return $cache[$slug] = $topics[$slug];
  }

  $keywords = keyword_emoji_map();
  if (isset($keywords[$slug])) {
    return $cache[$slug] = $keywords[$slug];
  }

  // "beekeeping-notes" should still find "beekeeping".
  foreach (explode('-', $slug) as $word) {
    $stem = function_exists('tag_singular') ? tag_singular($word) : $word;
    if (isset($keywords[$word])) {
      return $cache[$slug] = $keywords[$word];
    }
    if (isset($keywords[$stem])) {
      return $cache[$slug] = $keywords[$stem];
    }
    if (isset($topics[$word])) {
      return $cache[$slug] = $topics[$word];
    }
  }

  // crc32 rather than rand(): the same tag must keep the same icon across
  // requests, or the feed appears to shuffle itself on every reload.
  $pool = fallback_emoji_pool();
  return $cache[$slug] = $pool[crc32($slug) % count($pool)];
}

/** Icon for a moderation reason code, used in the review queue. */
function reason_emoji(string $code): string {
  return [
    'hate_speech'    => '🚫',
    'slur_ambiguous' => '⚠️',
    'threat'         => '🔪',
    'harassment'     => '😠',
    'sexual_content' => '🔞',
    'profanity'      => '🤬',
    'profanity_mild' => '😐',
    'spam'           => '📢',
    'link_spam'      => '🔗',
    'shouting'       => '📣',
    'gibberish'      => '🔡',
    'character_spam' => '⌨️',
    'repetition'     => '🔁',
  ][$code] ?? '🚩';
}

/**
 * A face for how busy a thread is. Small signal, but it makes the difference
 * between "nobody replied" and "this one got going" readable at a glance.
 */
function activity_emoji(int $replyCount, int $likes = 0): string {
  $heat = $replyCount * 2 + $likes;
  if ($heat >= 20) return '🔥';
  if ($heat >= 8)  return '💬';
  if ($heat >= 1)  return '🗨️';
  return '🌱';
}
