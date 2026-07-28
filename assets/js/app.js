/*
 * Behaviour shared by every page: toasts, sharing, likes, and the
 * localStorage-backed sense of "this browser wrote that post".
 *
 * Loaded before each page's own script, which can rely on window.EMI.
 *
 * Every binder takes a root element so it can be run again over cards that
 * arrive later by fetch (see EMI.bindDynamic, used by feed-scroll.js). Without
 * that, scroll-loaded posts would render correctly but do nothing when tapped.
 */

(function () {
  "use strict";

  var TOKEN_PREFIX = "emi_token_";
  var PENDING_TOKEN = "emi_pending_token";

  /*
   * localStorage throws in Safari private browsing and when a user has blocked
   * site data. None of what it holds here is essential, so every access is
   * guarded and simply behaves as "no token" on failure.
   */
  var store = {
    get: function (key) {
      try {
        return localStorage.getItem(key);
      } catch (e) {
        return null;
      }
    },
    set: function (key, value) {
      try {
        localStorage.setItem(key, value);
        return true;
      } catch (e) {
        return false;
      }
    },
    remove: function (key) {
      try {
        localStorage.removeItem(key);
      } catch (e) {
        /* nothing to do */
      }
    },
  };

  /** A random token, used to prove ownership of a post later. */
  function mintToken() {
    var bytes = new Uint8Array(32);
    if (window.crypto && window.crypto.getRandomValues) {
      window.crypto.getRandomValues(bytes);
    } else {
      // Ancient browser: still unguessable enough for an edit key on an
      // anonymous blog post, and the server only ever sees its hash.
      for (var i = 0; i < bytes.length; i++) {
        bytes[i] = Math.floor(Math.random() * 256);
      }
    }
    return Array.prototype.map
      .call(bytes, function (b) {
        return ("0" + b.toString(16)).slice(-2);
      })
      .join("");
  }

  function tokenFor(postId) {
    return store.get(TOKEN_PREFIX + postId);
  }

  /** Brief status message in the corner. */
  var toastTimer = null;
  function toast(message) {
    var el = document.getElementById("toast");
    if (!el) return;
    el.textContent = message;
    el.classList.add("visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      el.classList.remove("visible");
    }, 2600);
  }

  /** Run a selector over a root, whether it is the document or a single card. */
  function each(root, selector, fn) {
    if (root.matches && root.matches(selector)) fn(root);
    root.querySelectorAll(selector).forEach(fn);
  }

  // --- Binders -------------------------------------------------------------

  function bindOwnerActions(root) {
    each(root, "[data-owner-for]", function (el) {
      if (tokenFor(el.getAttribute("data-owner-for"))) {
        el.hidden = false;
      }
    });
  }

  function bindShare(root) {
    each(root, ".share-button", function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = "1";

      btn.addEventListener("click", function () {
        var url = btn.getAttribute("data-share-url");
        var title = btn.getAttribute("data-share-title") || document.title;

        if (navigator.share) {
          navigator.share({ title: title, url: url }).catch(function () {
            /* the user dismissed the sheet */
          });
          return;
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(url).then(
            function () {
              toast("🔗 Link copied to clipboard");
            },
            function () {
              window.prompt("Copy this link:", url);
            }
          );
          return;
        }
        window.prompt("Copy this link:", url);
      });
    });
  }

  function bindLikes(root) {
    each(root, ".like-button", function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = "1";

      var postId = btn.dataset.postId;
      var countEl = btn.querySelector(".like-count");

      if (store.get("liked_" + postId)) {
        btn.classList.add("liked");
      }

      btn.addEventListener("click", function () {
        if (btn.classList.contains("liked")) return;
        btn.classList.add("liked");
        store.set("liked_" + postId, "1");

        fetch("/like", {
          method: "POST",
          headers: { "Content-Type": "application/x-www-form-urlencoded" },
          body: new URLSearchParams({ post_id: postId }).toString(),
        })
          .then(function (r) {
            return r.json();
          })
          .then(function (data) {
            if (data && typeof data.likes === "number") {
              countEl.textContent = data.likes;
            }
          })
          .catch(function () {
            /* keep the optimistic count on network error */
          });
      });
    });
  }

  function bindConfirms(root) {
    each(root, "[data-confirm]", function (btn) {
      if (btn.dataset.bound) return;
      btn.dataset.bound = "1";

      btn.addEventListener("click", function (event) {
        if (!window.confirm(btn.getAttribute("data-confirm"))) {
          event.preventDefault();
        }
      });
    });
  }

  window.EMI = {
    store: store,
    mintToken: mintToken,
    tokenFor: tokenFor,
    tokenKey: function (postId) {
      return TOKEN_PREFIX + postId;
    },
    pendingKey: PENDING_TOKEN,
    toast: toast,

    /** Wire up cards that arrived after the initial render. */
    bindDynamic: function (elements) {
      Array.prototype.forEach.call(elements, function (el) {
        bindOwnerActions(el);
        bindShare(el);
        bindLikes(el);
        bindConfirms(el);
      });
    },
  };

  window.addEventListener("DOMContentLoaded", function () {
    var params = new URLSearchParams(window.location.search);

    // --- A post just created here: bind its pending token to the new id -----
    if (params.get("new") === "1") {
      var newId = params.get("id");
      var pending = store.get(PENDING_TOKEN);
      if (newId && pending) {
        store.set(TOKEN_PREFIX + newId, pending);
        store.remove(PENDING_TOKEN);
      }
      toast(
        params.get("flagged") === "1"
          ? "Posted - the filter wasn't sure about it, so it's queued for review."
          : "🎉 Posted!"
      );
    }

    if (params.get("edited") === "1") toast("✅ Changes saved.");

    // A deleted post's token is dead weight; drop it.
    var deletedId = params.get("deleted");
    if (deletedId) {
      store.remove(TOKEN_PREFIX + deletedId);
      toast("🗑️ Post deleted.");
    }

    bindOwnerActions(document);
    bindShare(document);
    bindLikes(document);
    bindConfirms(document);
  });
})();
