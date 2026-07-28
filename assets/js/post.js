/*
 * Permalink page behaviour.
 *
 * The reply form is server-rendered and works without JavaScript; this only
 * smooths the path to it.
 */

window.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("reply");
  var content = document.getElementById("reply_content");

  // Arriving from a "Reply" link in the feed should land in the box, not just
  // near it.
  if (window.location.hash === "#reply" && content) {
    content.focus({ preventScroll: true });
  }

  // A rejected reply re-renders with the text intact; put the cursor back at
  // the end of it so the fix can be typed straight away.
  if (form && form.querySelector(".form-error") && content) {
    content.focus();
    content.setSelectionRange(content.value.length, content.value.length);
  }
});
