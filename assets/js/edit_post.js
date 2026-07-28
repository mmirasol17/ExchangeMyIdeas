/*
 * Edit page behaviour.
 *
 * The form is rendered hidden and only revealed once this browser is found to
 * hold the post's edit token. That is a convenience, not the security boundary
 * - src/pages/edit.php verifies the token against its stored hash on every POST, so
 * revealing the form early would gain an attacker nothing.
 */

window.addEventListener("DOMContentLoaded", function () {
  var form = document.getElementById("edit-form");
  var notOwner = document.getElementById("not-owner");
  var tokenField = document.getElementById("edit-token");
  if (!form || !tokenField || !window.EMI) return;

  var postIdField = form.querySelector("input[name='post_id']");
  var postId = postIdField ? postIdField.value : "";
  var token = window.EMI.tokenFor(postId);

  if (!token) {
    if (notOwner) notOwner.hidden = false;
    return;
  }

  tokenField.value = token;
  form.hidden = false;

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

  var deleteButton = document.getElementById("delete-button");
  var actionField = document.getElementById("form-action");
  if (deleteButton && actionField) {
    deleteButton.addEventListener("click", function () {
      if (!window.confirm("Delete this post and all its replies? This cannot be undone.")) {
        return;
      }
      actionField.value = "delete";
      form.submit();
    });
  }
});
