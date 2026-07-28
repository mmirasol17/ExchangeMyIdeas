/*
 * Markdown editor: a formatting toolbar, keyboard shortcuts, and a live
 * preview.
 *
 * The preview is rendered by the server (POST /preview) rather than by a
 * Markdown library in the browser. That is the whole design decision here: one
 * renderer means the preview cannot disagree with the published post, and there
 * is no second set of escaping rules to get wrong.
 *
 * Everything is an enhancement of a plain <textarea>. With this file absent or
 * broken the form still submits exactly as it always did.
 */

(function () {
  "use strict";

  var PREVIEW_DEBOUNCE_MS = 350;

  /** Formatting actions. `block` ones prefix whole lines; the rest wrap a selection. */
  var ACTIONS = [
    { name: "bold", label: "B", title: "Bold (Ctrl/Cmd+B)", wrap: "**", placeholder: "bold text", key: "b", className: "md-bold" },
    { name: "italic", label: "I", title: "Italic (Ctrl/Cmd+I)", wrap: "*", placeholder: "italic text", key: "i", className: "md-italic" },
    { name: "heading", label: "H", title: "Heading", block: "## ", placeholder: "Heading" },
    { name: "quote", label: "❝", title: "Quote", block: "> ", placeholder: "quoted text" },
    { name: "list", label: "•", title: "Bulleted list", block: "- ", placeholder: "list item" },
    { name: "numbered", label: "1.", title: "Numbered list", block: "1. ", placeholder: "list item" },
    { name: "code", label: "</>", title: "Inline code", wrap: "`", placeholder: "code" },
    { name: "link", label: "🔗", title: "Link (Ctrl/Cmd+K)", link: true, key: "k" },
  ];

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  window.addEventListener("DOMContentLoaded", function () {
    var textarea = document.getElementById("content");
    if (!textarea) return;

    var host = el("div", "editor");
    textarea.parentNode.insertBefore(host, textarea);

    // --- Toolbar ------------------------------------------------------------
    var toolbar = el("div", "editor-toolbar");
    toolbar.setAttribute("role", "toolbar");
    toolbar.setAttribute("aria-label", "Formatting");

    ACTIONS.forEach(function (action) {
      var button = el("button", "editor-btn " + (action.className || ""), action.label);
      button.type = "button";
      button.title = action.title;
      button.setAttribute("aria-label", action.title);
      button.addEventListener("click", function () {
        apply(action);
        schedulePreview();
      });
      toolbar.appendChild(button);
    });

    var spacer = el("div", "editor-spacer");
    toolbar.appendChild(spacer);

    // Write/Preview tabs. On a wide screen both panes show at once and these
    // are hidden by CSS; on a phone there is only room for one at a time.
    var tabs = el("div", "editor-tabs");
    var writeTab = el("button", "editor-tab active", "Write");
    var previewTab = el("button", "editor-tab", "Preview");
    [writeTab, previewTab].forEach(function (t) {
      t.type = "button";
    });
    tabs.appendChild(writeTab);
    tabs.appendChild(previewTab);
    toolbar.appendChild(tabs);

    host.appendChild(toolbar);

    // --- Panes ---------------------------------------------------------------
    var panes = el("div", "editor-panes");
    var writePane = el("div", "editor-pane editor-write");
    var previewPane = el("div", "editor-pane editor-preview");

    var previewBody = el("div", "body preview-body");
    previewBody.setAttribute("aria-live", "polite");
    previewBody.innerHTML = '<p class="preview-empty">Nothing to preview yet.</p>';

    var previewLabel = el("div", "editor-pane-label", "Preview");
    previewPane.appendChild(previewLabel);
    previewPane.appendChild(previewBody);

    panes.appendChild(writePane);
    panes.appendChild(previewPane);
    host.appendChild(panes);

    // Move the real textarea inside the write pane. It keeps its name, id and
    // value, so the form submits exactly as before.
    writePane.appendChild(textarea);
    textarea.classList.add("editor-textarea");

    writeTab.addEventListener("click", function () {
      host.classList.remove("show-preview");
      writeTab.classList.add("active");
      previewTab.classList.remove("active");
      textarea.focus();
    });
    previewTab.addEventListener("click", function () {
      host.classList.add("show-preview");
      previewTab.classList.add("active");
      writeTab.classList.remove("active");
      renderPreview();
    });

    // --- Selection helpers ----------------------------------------------------

    function replaceSelection(text, selectStart, selectEnd) {
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var before = textarea.value.slice(0, start);
      var after = textarea.value.slice(end);
      textarea.value = before + text + after;
      textarea.focus();
      textarea.setSelectionRange(start + selectStart, start + selectEnd);
      textarea.dispatchEvent(new Event("input", { bubbles: true }));
    }

    function apply(action) {
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var selected = textarea.value.slice(start, end);

      if (action.link) {
        var text = selected || "link text";
        var out = "[" + text + "](https://)";
        // Land the cursor in the URL, which is the part that always needs typing.
        replaceSelection(out, out.length - 1, out.length - 1);
        return;
      }

      if (action.block) {
        // Prefix every line of the selection, or the current line if nothing is
        // selected. Applying it twice removes the prefix again.
        var lineStart = textarea.value.lastIndexOf("\n", start - 1) + 1;
        var lineEnd = textarea.value.indexOf("\n", end);
        if (lineEnd === -1) lineEnd = textarea.value.length;

        var block = textarea.value.slice(lineStart, lineEnd) || action.placeholder;
        var lines = block.split("\n");
        var allPrefixed = lines.every(function (l) {
          return l.indexOf(action.block) === 0;
        });

        var updated = lines
          .map(function (l) {
            return allPrefixed ? l.slice(action.block.length) : action.block + l;
          })
          .join("\n");

        textarea.setSelectionRange(lineStart, lineEnd);
        replaceSelection(updated, 0, updated.length);
        return;
      }

      var wrap = action.wrap;
      // Toggle off when the selection is already wrapped.
      if (
        selected &&
        textarea.value.slice(start - wrap.length, start) === wrap &&
        textarea.value.slice(end, end + wrap.length) === wrap
      ) {
        textarea.setSelectionRange(start - wrap.length, end + wrap.length);
        replaceSelection(selected, 0, selected.length);
        return;
      }

      var body = selected || action.placeholder;
      var wrapped = wrap + body + wrap;
      replaceSelection(wrapped, wrap.length, wrap.length + body.length);
    }

    // --- Keyboard shortcuts ----------------------------------------------------
    textarea.addEventListener("keydown", function (event) {
      if (!(event.metaKey || event.ctrlKey) || event.altKey) return;
      var key = event.key.toLowerCase();
      var action = ACTIONS.filter(function (a) {
        return a.key === key;
      })[0];
      if (!action) return;
      event.preventDefault();
      apply(action);
      schedulePreview();
    });

    // --- Live preview ------------------------------------------------------------
    var timer = null;
    var lastRendered = null;
    var inFlight = false;

    function schedulePreview() {
      clearTimeout(timer);
      timer = setTimeout(renderPreview, PREVIEW_DEBOUNCE_MS);
    }

    function renderPreview() {
      var value = textarea.value;
      if (value === lastRendered || inFlight) return;
      if (typeof fetch !== "function") return;

      inFlight = true;
      fetch("/preview", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ content: value }).toString(),
        credentials: "same-origin",
      })
        .then(function (r) {
          if (!r.ok) throw new Error("HTTP " + r.status);
          return r.text();
        })
        .then(function (html) {
          lastRendered = value;
          previewBody.innerHTML = html;
        })
        .catch(function () {
          // The preview is a convenience; a failed render must never block
          // writing or submitting.
          previewBody.innerHTML =
            '<p class="preview-empty">Preview unavailable right now &mdash; your post is unaffected.</p>';
        })
        .finally(function () {
          inFlight = false;
        });
    }

    textarea.addEventListener("input", schedulePreview);
    if (textarea.value.trim() !== "") renderPreview();

    // The character counter lives outside the editor; keep it below the panes.
    var counter = document.getElementById("char-count");
    if (counter) host.appendChild(counter);
  });
})();
