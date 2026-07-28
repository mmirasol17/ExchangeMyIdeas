/*
 * Feed page behaviour.
 *
 * Replying happens on the permalink page rather than inline here, so this file
 * only has to deal with the collapsible reply previews. Everything else the
 * feed does - likes, sharing, owner controls - lives in app.js.
 */

window.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".reply-toggle").forEach(function (toggle) {
    var post = toggle.closest(".post");
    if (!post) return;
    var replies = post.querySelector(".replies");
    if (!replies) return;

    toggle.addEventListener("click", function () {
      var opened = !replies.classList.toggle("collapsed");
      toggle.setAttribute("aria-expanded", String(opened));
    });
  });
});
