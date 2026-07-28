<?php
/*
 * A small, safe Markdown subset.
 *
 * Supports headings, lists, blockquotes, fenced and inline code, links, bold,
 * italic, strikethrough, and horizontal rules. Deliberately not a full
 * CommonMark implementation - this is a blog for ideas, and every construct
 * added here is one more thing that has to be proven safe.
 *
 * THE SECURITY RULE
 *   Structure is detected on the RAW text, but no raw text is ever emitted.
 *   Every fragment that reaches the output goes through e() first, and the
 *   only HTML tags in the result are ones this file wrote. A user cannot
 *   inject markup because their "<" became "&lt;" before it was ever placed.
 *
 *   The earlier version escaped the whole string up front and then pattern
 *   matched. That works for inline formatting but falls apart on block syntax:
 *   after escaping, a blockquote's ">" is "&gt;", so the parser has to match
 *   entities rather than characters, and every rule grows a subtle second form.
 *   Parsing raw and escaping on emit avoids that whole class of bug.
 *
 * WHAT IS NOT SUPPORTED, ON PURPOSE
 *   Images. The content filter reads text; it cannot judge what is behind a
 *   URL. Allowing <img> would let any post pull an arbitrary remote resource
 *   into a reader's browser, which is both a moderation and a privacy hole.
 *   Raw HTML, for the same reason.
 */

/** Headings start at h3: the page title is h1, and a feed card's title is h2. */
const MARKDOWN_HEADING_BASE = 2;

/**
 * Render a Markdown subset to safe HTML.
 */
function render_markdown(string $text): string {
  $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
  $html = [];
  $i = 0;
  $count = count($lines);

  while ($i < $count) {
    $line = $lines[$i];

    // --- Fenced code block ---------------------------------------------
    if (preg_match('/^\s*```/', $line)) {
      $code = [];
      $i++;
      while ($i < $count && !preg_match('/^\s*```\s*$/', $lines[$i])) {
        $code[] = $lines[$i];
        $i++;
      }
      $i++; // consume the closing fence (or run off the end, which is fine)
      $html[] = '<pre><code>' . e(implode("\n", $code)) . '</code></pre>';
      continue;
    }

    // --- Horizontal rule ------------------------------------------------
    if (preg_match('/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/', $line)) {
      $html[] = '<hr />';
      $i++;
      continue;
    }

    // --- Heading ---------------------------------------------------------
    if (preg_match('/^\s*(#{1,4})\s+(.+?)\s*#*\s*$/', $line, $m)) {
      $level = min(6, MARKDOWN_HEADING_BASE + strlen($m[1]));
      $html[] = "<h$level>" . markdown_inline($m[2]) . "</h$level>";
      $i++;
      continue;
    }

    // --- Blockquote -------------------------------------------------------
    if (preg_match('/^\s*>\s?/', $line)) {
      $quote = [];
      while ($i < $count && preg_match('/^\s*>\s?(.*)$/', $lines[$i], $m)) {
        $quote[] = $m[1];
        $i++;
      }
      $html[] = '<blockquote>' . markdown_paragraph_body($quote) . '</blockquote>';
      continue;
    }

    // --- Lists -------------------------------------------------------------
    if (preg_match('/^\s*[-*+]\s+(.*)$/', $line)) {
      [$items, $i] = markdown_collect_list($lines, $i, '/^\s*[-*+]\s+(.*)$/');
      $html[] = '<ul>' . implode('', array_map(
        fn(string $item) => '<li>' . markdown_inline($item) . '</li>',
        $items
      )) . '</ul>';
      continue;
    }

    if (preg_match('/^\s*\d+[.)]\s+(.*)$/', $line)) {
      [$items, $i] = markdown_collect_list($lines, $i, '/^\s*\d+[.)]\s+(.*)$/');
      $html[] = '<ol>' . implode('', array_map(
        fn(string $item) => '<li>' . markdown_inline($item) . '</li>',
        $items
      )) . '</ol>';
      continue;
    }

    // --- Blank line --------------------------------------------------------
    if (trim($line) === '') {
      $i++;
      continue;
    }

    // --- Paragraph ---------------------------------------------------------
    $paragraph = [];
    while ($i < $count && trim($lines[$i]) !== '' && !markdown_starts_block($lines[$i])) {
      $paragraph[] = $lines[$i];
      $i++;
    }
    $html[] = '<p>' . markdown_paragraph_body($paragraph) . '</p>';
  }

  return implode("\n", $html);
}

/** Whether a line begins a block that must interrupt an open paragraph. */
function markdown_starts_block(string $line): bool {
  return (bool) preg_match(
    '/^\s*(?:```|#{1,4}\s|>|[-*+]\s|\d+[.)]\s|(?:-{3,}|\*{3,}|_{3,})\s*$)/',
    $line
  );
}

/**
 * Consecutive list items. Returns [items, next index].
 *
 * A blank line between items is tolerated so a "loose" list does not silently
 * split into two.
 */
function markdown_collect_list(array $lines, int $i, string $pattern): array {
  $items = [];
  $count = count($lines);

  while ($i < $count) {
    if (preg_match($pattern, $lines[$i], $m)) {
      $items[] = $m[1];
      $i++;
      continue;
    }
    // Look past a single blank line for another item of the same kind.
    if (trim($lines[$i]) === '' && isset($lines[$i + 1]) && preg_match($pattern, $lines[$i + 1])) {
      $i++;
      continue;
    }
    break;
  }

  return [$items, $i];
}

/** Join wrapped lines into one flow, keeping deliberate line breaks. */
function markdown_paragraph_body(array $lines): string {
  return implode("<br />\n", array_map('markdown_inline', $lines));
}

/**
 * Inline formatting for one line of text.
 *
 * Inline code is lifted out into placeholders before anything else runs, so
 * `**not bold**` inside backticks stays literal. The placeholder uses NUL,
 * which cannot survive e() and therefore cannot be forged by a user.
 */
function markdown_inline(string $raw): string {
  $codes = [];
  $withPlaceholders = preg_replace_callback(
    '/`([^`\n]+)`/',
    function (array $m) use (&$codes): string {
      $codes[] = '<code>' . e($m[1]) . '</code>';
      return "\x00C" . (count($codes) - 1) . "\x00";
    },
    $raw
  ) ?? $raw;

  // Links are extracted from raw text too, so the URL can be validated before
  // it is escaped into an attribute.
  $links = [];
  $withPlaceholders = preg_replace_callback(
    '/\[([^\]\n]+)\]\(([^)\s]+)\)/',
    function (array $m) use (&$links): string {
      $url = $m[2];
      // Only absolute http(s). This is what stops javascript: and data: URLs.
      if (!preg_match('#^https?://#i', $url)) {
        return $m[0];
      }
      $links[] = '<a href="' . e($url) . '" target="_blank" rel="noopener noreferrer nofollow">'
        . e($m[1]) . '</a>';
      return "\x00L" . (count($links) - 1) . "\x00";
    },
    $withPlaceholders
  ) ?? $withPlaceholders;

  $safe = e($withPlaceholders);

  // ***bold italic*** before ** and *, or the shorter rules eat the markers.
  $safe = preg_replace('/\*\*\*([^*\n]+)\*\*\*/', '<strong><em>$1</em></strong>', $safe) ?? $safe;
  $safe = preg_replace('/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $safe) ?? $safe;
  $safe = preg_replace('/(?<!\*)\*(?!\*)([^*\n]+)\*(?!\*)/', '<em>$1</em>', $safe) ?? $safe;
  $safe = preg_replace('/~~([^~\n]+)~~/', '<del>$1</del>', $safe) ?? $safe;

  // Restore the protected fragments, which are already escaped HTML.
  $safe = preg_replace_callback('/\x00C(\d+)\x00/', fn($m) => $codes[(int) $m[1]] ?? '', $safe) ?? $safe;
  $safe = preg_replace_callback('/\x00L(\d+)\x00/', fn($m) => $links[(int) $m[1]] ?? '', $safe) ?? $safe;

  return $safe;
}

/**
 * Plain-text summary, for meta descriptions, RSS, and previews.
 * Strips the Markdown rather than rendering it, and cuts on a word boundary.
 */
function post_excerpt(string $content, int $length = 200): string {
  $text = $content;
  $text = preg_replace('/```.*?```/s', ' ', $text) ?? $text;        // fenced code
  $text = preg_replace('/^\s*(?:-{3,}|\*{3,}|_{3,})\s*$/m', ' ', $text) ?? $text;
  $text = preg_replace('/^\s*#{1,4}\s+/m', '', $text) ?? $text;     // heading markers
  $text = preg_replace('/^\s*>\s?/m', '', $text) ?? $text;          // quote markers
  $text = preg_replace('/^\s*(?:[-*+]|\d+[.)])\s+/m', '', $text) ?? $text; // list markers
  $text = preg_replace('/\[([^\]\n]+)\]\([^)]*\)/', '$1', $text) ?? $text; // links
  $text = preg_replace('/`([^`\n]+)`/', '$1', $text) ?? $text;
  $text = preg_replace('/\*{1,3}([^*\n]+)\*{1,3}/', '$1', $text) ?? $text;
  $text = preg_replace('/~~([^~\n]+)~~/', '$1', $text) ?? $text;
  $text = trim(preg_replace('/\s+/', ' ', $text) ?? $text);

  if (mb_strlen($text) <= $length) {
    return $text;
  }
  $cut = mb_substr($text, 0, $length);
  $space = mb_strrpos($cut, ' ');
  return rtrim($space !== false ? mb_substr($cut, 0, $space) : $cut, " ,.;:-") . '…';
}
