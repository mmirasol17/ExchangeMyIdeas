/*
 * Create-post page behaviour: the character counter, and minting the edit
 * token that later proves this browser wrote the post.
 *
 * The token is generated here rather than server-side so the raw value never
 * has to travel back in a redirect URL, where it would end up in browser
 * history and server logs. The server only ever stores its hash.
 */

window.addEventListener("DOMContentLoaded", function () {
  var form = document.querySelector("form.post-form");
  var tokenField = document.getElementById("edit-token");

  if (form && tokenField && window.EMI) {
    var token = window.EMI.mintToken();
    tokenField.value = token;

    // Stashed under a "pending" key because the post has no id until the
    // server assigns one. app.js rebinds it to the real id on arrival.
    form.addEventListener("submit", function () {
      window.EMI.store.set(window.EMI.pendingKey, token);
    });
  }

  var content = document.getElementById("content");
  var counter = document.getElementById("char-count");
  if (content && counter) {
    var update = function () {
      var n = content.value.length;
      counter.textContent = n + " character" + (n === 1 ? "" : "s");
    };
    content.addEventListener("input", update);
    update();
  }
});
