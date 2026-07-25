window.addEventListener("DOMContentLoaded", () => {
  // "Post" in the header goes to the create-blog page
  const postElement = document.getElementById("post");
  if (postElement) {
    postElement.addEventListener("click", () => {
      window.location.href = "./create_blog.php";
    });
  }

  // Collapsible reply threads
  document.querySelectorAll(".reply-toggle").forEach((toggle) => {
    const post = toggle.closest(".post");
    const replies = post.querySelector(".replies");
    toggle.addEventListener("click", () => {
      const opened = !replies.classList.toggle("collapsed");
      toggle.setAttribute("aria-expanded", String(opened));
    });
  });

  // Inline reply form on each post
  for (const post of document.getElementsByClassName("post")) {
    const replies = post.querySelector(".replies");
    const replyButton = post.querySelector(".reply-button");
    const toggle = post.querySelector(".reply-toggle");
    if (!replyButton) continue;
    let openForm = null;

    replyButton.addEventListener("click", () => {
      // Make sure any existing replies are visible while replying
      if (replies) replies.classList.remove("collapsed");
      if (toggle) toggle.setAttribute("aria-expanded", "true");

      if (openForm) {
        openForm.querySelector(".reply-input").focus();
        return;
      }

      const { form, content } = buildReplyForm(post.id, () => {
        form.remove();
        openForm = null;
      });

      post.insertBefore(form, replies);
      openForm = form;
      content.focus();
    });
  }
});

// Build the inline reply form for a given post
function buildReplyForm(postId, onCancel) {
  const form = document.createElement("form");
  form.className = "reply-form";
  form.method = "POST";
  form.action = "./index.php";

  const contentLabel = document.createElement("div");
  contentLabel.className = "reply-label";
  contentLabel.innerText = "Message";

  const content = document.createElement("textarea");
  content.name = "reply_content";
  content.className = "reply-input";
  content.placeholder = "What do you think about this post? 🤔";
  content.required = true;

  const authorLabel = document.createElement("div");
  authorLabel.className = "reply-label";
  authorLabel.innerText = "Name (optional)";

  const author = document.createElement("input");
  author.name = "reply_author";
  author.className = "reply-input";
  author.placeholder = "How would you like to be known? 👤";

  const postIdInput = document.createElement("input");
  postIdInput.type = "hidden";
  postIdInput.name = "blog_post_id";
  postIdInput.value = postId;

  const cancelButton = document.createElement("button");
  cancelButton.type = "button";
  cancelButton.className = "button secondary";
  cancelButton.innerText = "Cancel";
  cancelButton.addEventListener("click", onCancel);

  const submitButton = document.createElement("button");
  submitButton.type = "submit";
  submitButton.className = "button";
  submitButton.innerText = "Post";

  const footer = document.createElement("div");
  footer.className = "reply-form-footer";
  footer.appendChild(cancelButton);
  footer.appendChild(submitButton);

  form.append(contentLabel, content, authorLabel, author, postIdInput, footer);
  return { form, content };
}
