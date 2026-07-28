/*
 * Scroll-loaded pagination.
 *
 * The server renders page 1 plus a working numbered pager. This upgrades that
 * into infinite scroll and hides the pager - so with JavaScript off, or if this
 * file fails to load, the feed is still a perfectly good paginated list. That
 * ordering is deliberate: the fallback is the thing that already works, not a
 * thing that has to be built.
 *
 * IntersectionObserver watches a sentinel above the fold-end and fetches the
 * next page when it comes near, so loading starts before the reader arrives.
 */

window.addEventListener("DOMContentLoaded", function () {
  var items = document.getElementById("feed-items");
  var pager = document.getElementById("feed-pager");
  var status = document.getElementById("feed-status");
  var end = document.getElementById("feed-end");

  if (!items || !("IntersectionObserver" in window) || typeof fetch !== "function") {
    return; // leave the server-rendered pager in charge
  }

  var totalPages = parseInt(items.getAttribute("data-total-pages"), 10) || 1;
  var nextPage = parseInt(items.getAttribute("data-next-page"), 10) || 2;
  var endpoint = items.getAttribute("data-endpoint");
  if (!endpoint || totalPages < 2) {
    if (end && totalPages < 2) end.hidden = false;
    return;
  }

  var loading = false;
  var failures = 0;

  /*
   * The pager is hidden only after a page has actually loaded, never up front.
   *
   * Hiding it on startup assumes the observer will fire, and there are real
   * situations where it does not -- an implicit-root observer computes against
   * the top-level viewport, so a page embedded in an iframe never triggers it.
   * Hiding the pager first would leave that reader with no pagination at all.
   * Take the fallback away only once the replacement has demonstrably worked.
   */
  function retirePager() {
    if (pager) pager.hidden = true;
  }

  var sentinel = document.createElement("div");
  sentinel.className = "feed-sentinel";
  items.parentNode.insertBefore(sentinel, items.nextSibling);

  function finish(message) {
    observer.disconnect();
    sentinel.remove();
    if (status) status.hidden = true;
    if (end) {
      if (message) end.textContent = message;
      end.hidden = false;
    }
  }

  function loadNext() {
    if (loading || nextPage > totalPages) return;
    loading = true;
    if (status) status.hidden = false;

    var separator = endpoint.indexOf("?") === -1 ? "?" : "&";

    fetch(endpoint + separator + "page=" + nextPage, {
      headers: { "X-Requested-With": "fetch" },
      credentials: "same-origin",
    })
      .then(function (response) {
        if (response.status === 204) return null; // no more pages
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.text();
      })
      .then(function (html) {
        if (html === null || !html.trim()) {
          finish();
          return;
        }

        // Parsing into a template rather than assigning innerHTML on the live
        // container keeps the existing cards from being re-parsed, which would
        // drop their event listeners and restart their entry animation.
        var template = document.createElement("template");
        template.innerHTML = html;
        var added = template.content.querySelectorAll(".post");

        // Entry animations are staggered by --i; restart the count each page so
        // the delay never grows to something absurd deep in the feed.
        added.forEach(function (el, i) {
          el.style.setProperty("--i", String(i));
        });

        items.appendChild(template.content);
        bindNewCards(added);
        retirePager();

        nextPage++;
        items.setAttribute("data-next-page", String(nextPage));
        failures = 0;
        loading = false;
        if (status) status.hidden = true;

        if (nextPage > totalPages) {
          finish();
        } else {
          // The appended page may be short enough that the sentinel is still on
          // screen; re-check rather than waiting for a scroll that never comes.
          if (isNearViewport(sentinel)) loadNext();
        }
      })
      .catch(function () {
        loading = false;
        failures++;
        if (status) status.hidden = true;
        // Two consecutive failures: stop retrying and hand the reader back the
        // pager, which does not depend on any of this working.
        if (failures >= 2) {
          if (pager) pager.hidden = false;
          finish("Could not load more posts. Use the pages below.");
        }
      });
  }

  function isNearViewport(el) {
    var box = el.getBoundingClientRect();
    return box.top < window.innerHeight + 200;
  }

  /* Cards that arrive by fetch need the same wiring index.js and app.js gave
     the server-rendered ones. */
  function bindNewCards(cards) {
    cards.forEach(function (card) {
      var toggle = card.querySelector(".reply-toggle");
      var replies = card.querySelector(".replies");
      if (toggle && replies) {
        toggle.addEventListener("click", function () {
          var opened = !replies.classList.toggle("collapsed");
          toggle.setAttribute("aria-expanded", String(opened));
        });
      }
    });

    if (window.EMI && window.EMI.bindDynamic) {
      window.EMI.bindDynamic(cards);
    }
  }

  var observer = new IntersectionObserver(
    function (entries) {
      if (entries.some(function (entry) { return entry.isIntersecting; })) {
        loadNext();
      }
    },
    // Start fetching a screen and a half early so the next page is usually
    // already in place by the time the reader gets there.
    { rootMargin: "600px 0px" }
  );

  observer.observe(sentinel);
});
