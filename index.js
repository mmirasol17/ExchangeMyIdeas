window.addEventListener("load", () => {
  // add listener to go to post button to go to the create blog page
  const postElement = document.getElementById("post");
  postElement.addEventListener("click", () => {
    window.location.href = "./create_blog.php";
  });

  // add listener to search bar when user presses enter
  const searchElement = document.getElementById("search");
  searchElement.addEventListener("keypress", (event) => {
    if (event.key !== "Enter") {
      return;
    }
    const searchInput = event.target.value;
    console.log(searchInput);
  });

  // add listeners for each post for the reply button click
  const posts = document.getElementsByClassName("post");
  for (const post of posts) {
    const postId = post.id;
    const replies = post.querySelector(".replies");
    const replyButton = post.querySelector("#reply");

    // Flag to track whether a reply form is currently open
    let replyFormOpen = false;

    // create all of the elements for the reply form
    function replyListener() {
      // Check if a reply form is already open
      if (replyFormOpen) {
        return; // Don't create a new reply form
      }

      replyFormOpen = true;

      // Create elements with class names
      const inputContentTitle = document.createElement("div");
      inputContentTitle.className = "reply-content-title";
      inputContentTitle.innerText = "Message";

      const inputContent = document.createElement("textarea");
      inputContent.name = "reply_content";
      inputContent.className = "reply-content";

      const authorContentTitle = document.createElement("div");
      authorContentTitle.className = "reply-content-author";
      authorContentTitle.innerText = "Name";

      const authorContent = document.createElement("input");
      authorContent.name = "reply_author";
      authorContent.className = "reply-author";

      const postCancelReplyButtonInvisibleInput = document.createElement("input");
      postCancelReplyButtonInvisibleInput.type = "submit";
      postCancelReplyButtonInvisibleInput.style.display = "none";

      const postReplyButton = document.createElement("button");
      postReplyButton.className = "post-reply";
      postReplyButton.innerText = "Post";

      const postCancelReplyButton = document.createElement("button");
      postCancelReplyButton.className = "post-cancel-reply";
      postCancelReplyButton.innerText = "Cancel";

      const postReplyButtonInvisibleInput = document.createElement("input");
      postReplyButtonInvisibleInput.type = "submit";
      postReplyButtonInvisibleInput.style.display = "none";

      const postIdInput = document.createElement("input");
      postIdInput.type = "hidden";
      postIdInput.name = "blog_post_id";
      postIdInput.value = postId;

      // Create a container for the reply buttons
      const replyButtonsContainer = document.createElement("div");
      replyButtonsContainer.className = "reply-buttons";
      replyButtonsContainer.appendChild(postCancelReplyButton);
      replyButtonsContainer.appendChild(postReplyButton);

      // create the reply form with all the elements
      const reply = document.createElement("form");
      reply.className = "reply";
      reply.method = "POST";
      reply.action = "./index.php";

      // add listener to the cancel button to remove the reply form when the user clicks the cancel button
      postCancelReplyButton.addEventListener("click", () => {
        reply.remove();
        replyFormOpen = false;
        replyButton.addEventListener("click", replyListener);
      });

      // add listener to the reply form to submit the form when the user clicks the post reply button
      postReplyButton.addEventListener("click", () => {
        reply.submit();
      });

      // append all the elements to the reply form
      reply.appendChild(inputContentTitle);
      reply.appendChild(inputContent);
      reply.appendChild(authorContentTitle);
      reply.appendChild(authorContent);
      reply.appendChild(postCancelReplyButtonInvisibleInput);
      reply.appendChild(postReplyButtonInvisibleInput);
      reply.appendChild(postIdInput);
      reply.appendChild(replyButtonsContainer);

      // insert the reply form before the existing replies
      post.insertBefore(reply, replies);

      // remove the listener so the user can't click the reply button again
      replyButton.addEventListener("click", replyListener);
    }

    // add listener to the reply button to create the reply form when the user clicks the reply button
    replyButton.addEventListener("click", replyListener);
  }
});
