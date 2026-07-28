<?php
/*
 * Live Markdown preview for the editor. Serves POST /preview.
 *
 * WHY THIS IS SERVER-SIDE
 *   The obvious implementation is a JavaScript Markdown renderer in the editor.
 *   That means two renderers for one syntax, and they drift: the preview shows
 *   one thing, the published post shows another, and worse, the JS one has its
 *   own escaping rules to get wrong.
 *
 *   Rendering here calls the exact function that renders the real post. The
 *   preview is not an approximation of the output - it *is* the output. It also
 *   means the security review of render_markdown() covers this path too, rather
 *   than there being a second, unreviewed one in the browser.
 *
 *   The cost is a request per edit pause, which the editor debounces. For a
 *   blog on shared hosting that is a fair trade for the two renderers never
 *   disagreeing.
 *
 * Reads nothing and writes nothing, so there is no CSRF surface.
 */

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');

$content = (string) ($_POST['content'] ?? '');

// Same ceiling the create and edit forms enforce, so the preview cannot be
// used to render something the site would refuse to store.
if (mb_strlen($content) > 20000) {
  $content = mb_substr($content, 0, 20000);
}

if (trim($content) === '') {
  echo '<p class="preview-empty">Nothing to preview yet.</p>';
  return;
}

echo render_markdown($content);
