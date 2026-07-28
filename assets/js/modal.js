/*
 * Reading modal: opens a post in a scrollable overlay from the feed.
 *
 * A long post inside a feed card either dominates the page or gets clipped.
 * This gives it its own scroll container without losing the reader's place in
 * the feed behind it.
 *
 * PROGRESSIVE ENHANCEMENT
 *   Post titles are ordinary links to /post/{id}. This intercepts the click
 *   only when it can genuinely do better: plain left-click, no modifier keys,
 *   fetch available. Middle-click, Cmd-click, "open in new tab", right-click,
 *   and a crawler all get the real page.
 *
 *   The URL is pushed to the permalink while the modal is open, so the address
 *   bar, the Back button, and copy-link all behave as if it were a page. Back
 *   closes the modal rather than leaving the site.
 *
 * Click handling is delegated from the document, so posts that arrive later by
 * scroll-loading need no extra wiring.
 */

(function () {
  "use strict";

  var overlay = null;
  var panel = null;
  var contentEl = null;
  var titleEl = null;
  var lastFocused = null;
  var openPostId = null;
  var pushedState = false;

  var FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  function build() {
    overlay = document.createElement("div");
    overlay.className = "modal-overlay";
    overlay.hidden = true;

    overlay.innerHTML = [
      '<div class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="modal-heading">',
      '  <div class="modal-header">',
      '    <h2 class="modal-heading" id="modal-heading">Post</h2>',
      '    <div class="modal-header-actions">',
      '      <a class="modal-open-full button secondary" href="/">Open full page</a>',
      '      <button type="button" class="modal-close" aria-label="Close">&times;</button>',
      "    </div>",
      "  </div>",
      '  <div class="modal-content" tabindex="-1"></div>',
      "</div>",
    ].join("\n");

    document.body.appendChild(overlay);
    panel = overlay.querySelector(".modal-panel");
    contentEl = overlay.querySelector(".modal-content");
    titleEl = overlay.querySelector(".modal-heading");

    overlay.addEventListener("click", function (event) {
      if (event.target === overlay) close();
    });
    overlay.querySelector(".modal-close").addEventListener("click", function () {
      close();
    });
  }

  function lockScroll(lock) {
    // Compensating for the scrollbar keeps the page behind from shifting
    // sideways the moment the modal opens.
    if (lock) {
      var width = window.innerWidth - document.documentElement.clientWidth;
      document.body.style.overflow = "hidden";
      if (width > 0) document.body.style.paddingRight = width + "px";
    } else {
      document.body.style.overflow = "";
      document.body.style.paddingRight = "";
    }
  }

  function trapFocus(event) {
    if (event.key !== "Tab" || overlay.hidden) return;
    var items = Array.prototype.filter.call(panel.querySelectorAll(FOCUSABLE), function (n) {
      return n.offsetParent !== null;
    });
    if (!items.length) return;

    var first = items[0];
    var last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  function onKeydown(event) {
    if (overlay.hidden) return;
    if (event.key === "Escape") {
      event.preventDefault();
      close();
      return;
    }
    trapFocus(event);
  }

  function open(postId, href, title) {
    if (!overlay) build();

    lastFocused = document.activeElement;
    openPostId = postId;

    titleEl.textContent = title || "Post";
    overlay.querySelector(".modal-open-full").setAttribute("href", href);
    contentEl.innerHTML = '<div class="modal-loading"><span class="feed-spinner"></span> Loading…</div>';
    overlay.hidden = false;
    lockScroll(true);
    contentEl.focus();

    if (window.history && window.history.pushState) {
      window.history.pushState({ emiModal: postId }, "", href);
      pushedState = true;
    }

    fetch("/partial/post/" + encodeURIComponent(postId), { credentials: "same-origin" })
      .then(function (r) {
        if (!r.ok) throw new Error("HTTP " + r.status);
        return r.text();
      })
      .then(function (html) {
        if (openPostId !== postId) return; // a different post was opened meanwhile
        contentEl.innerHTML = html;

        // The heading is inside the fetched fragment; mirror it into the bar.
        var heading = contentEl.querySelector("#modal-title");
        if (heading) titleEl.textContent = heading.textContent.trim();

        // Likes, sharing and owner controls need the same wiring the page gives
        // them. Without this the modal renders correctly but does nothing.
        if (window.EMI && window.EMI.bindDynamic) {
          window.EMI.bindDynamic([contentEl]);
        }
      })
      .catch(function () {
        // Never strand the reader: fall back to the real page.
        window.location.href = href;
      });
  }

  function close(fromPopState) {
    if (!overlay || overlay.hidden) return;
    overlay.hidden = true;
    contentEl.innerHTML = "";
    openPostId = null;
    lockScroll(false);

    if (!fromPopState && pushedState && window.history && window.history.back) {
      window.history.back();
    }
    pushedState = false;

    if (lastFocused && lastFocused.focus) lastFocused.focus();
  }

  window.addEventListener("DOMContentLoaded", function () {
    if (typeof fetch !== "function" || !document.querySelector(".feed")) return;

    document.addEventListener("click", function (event) {
      // Respect every way a reader might mean "not here": new tab, new window,
      // download, and non-primary buttons.
      if (event.defaultPrevented || event.button !== 0) return;
      if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

      var link = event.target.closest ? event.target.closest("a.title-link") : null;
      if (!link) return;

      var card = link.closest(".post");
      if (!card || !card.id) return;

      event.preventDefault();
      open(card.id, link.getAttribute("href"), link.textContent.trim());
    });

    document.addEventListener("keydown", onKeydown);

    window.addEventListener("popstate", function () {
      if (overlay && !overlay.hidden) close(true);
    });
  });
})();
