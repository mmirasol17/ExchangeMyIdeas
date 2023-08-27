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

      // message input
      const inputContentTitle = document.createElement("div");
      inputContentTitle.className = "reply-label";
      inputContentTitle.innerText = "Message";
      const inputContent = document.createElement("textarea");
      inputContent.name = "reply_content";
      inputContent.className = "reply-input";
      inputContent.placeholder = "What do you think about this post? 🤔";

      // author input
      const authorContentTitle = document.createElement("div");
      authorContentTitle.className = "reply-label";
      authorContentTitle.innerText = "Name (optional)";
      const authorContent = document.createElement("input");
      authorContent.name = "reply_author";
      authorContent.className = "reply-input";
      authorContent.placeholder = "How you'd like to be known as 👤";

      // post reply button
      const postReplyButton = document.createElement("div");
      postReplyButton.className = "button";
      postReplyButton.innerText = "Post";
      const postReplyButtonInvisibleInput = document.createElement("input");
      postReplyButtonInvisibleInput.type = "submit";
      postReplyButtonInvisibleInput.style.display = "none";

      // cancel reply button
      const postCancelReplyButton = document.createElement("div");
      postCancelReplyButton.className = "button";
      postCancelReplyButton.innerText = "Cancel";
      const postCancelReplyButtonInvisibleInput = document.createElement("input");
      postCancelReplyButtonInvisibleInput.type = "submit";
      postCancelReplyButtonInvisibleInput.style.display = "none";

      // post id of the post that the user is replying to
      const postIdInput = document.createElement("input");
      postIdInput.type = "hidden";
      postIdInput.name = "blog_post_id";
      postIdInput.value = postId;

      // Create a container for the reply buttons
      const replyButtonsContainer = document.createElement("div");
      replyButtonsContainer.className = "reply-form-footer";
      replyButtonsContainer.appendChild(postCancelReplyButton);
      replyButtonsContainer.appendChild(postReplyButton);

      // create the reply form with all the elements
      const reply = document.createElement("form");
      reply.className = "reply-form";
      reply.method = "POST";
      reply.action = "./index.php";

      // add listener to the cancel button to remove the reply form when the user clicks the cancel button
      postCancelReplyButton.addEventListener("click", () => {
        reply.remove();
        replyFormOpen = false;
        // Add the following line to reattach the replyListener after canceling
        replyButton.addEventListener("click", replyListener);
      });

      // add listener to the reply form to submit the form when the user clicks the post reply button
      postReplyButton.addEventListener("click", () => {
        reply.submit();
        // Add the following line to remove the event listener after posting a reply
        replyButton.removeEventListener("click", replyListener);
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
    }

    // add listener to the reply button to create the reply form when the user clicks the reply button
    replyButton.addEventListener("click", replyListener);
  }
});
