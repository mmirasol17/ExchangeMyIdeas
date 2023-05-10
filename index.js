window.addEventListener("load", () => {
  const postElement = document.getElementById("post");
  postElement.addEventListener("click", () => {
    window.location.href = "./create_blog.php";
  });

  const searchElement = document.getElementById("search");
  searchElement.addEventListener("keypress", (event) => {
    if (event.key !== "Enter") {
      return;
    }

    const searchInput = event.target.value;
    console.log(searchInput);
  });

  const posts = document.getElementsByClassName("post");
  for (const post of posts) {
    const postId = post.id;
    const replies = post.querySelector(".replies");
    const replyButton = post.querySelector(".reply");

    function replyListener() {
      const inputContentTitle = document.createElement("div");
      inputContentTitle.className = "reply_content_title";
      inputContentTitle.innerText = "What would you like to reply?";

      const inputContent = document.createElement("textarea");
      inputContent.name = "reply_content";
      inputContent.className = "reply_content";

      const authorContentTitle = document.createElement("div");
      authorContentTitle.className = "reply_content_author";
      authorContentTitle.innerText = "What's your name?";

      const authorContent = document.createElement("input");
      authorContent.name = "reply_author";
      authorContent.className = "reply_author";

      const postReplyButton = document.createElement("div");
      postReplyButton.className = "post_reply";
      postReplyButton.innerText = "Post Reply";

      const postReplyButtonInvisibleInput = document.createElement("input");
      postReplyButtonInvisibleInput.type = "submit";
      postReplyButtonInvisibleInput.style.display = "none";

      const postIdInput = document.createElement("input");
      postIdInput.type = "hidden";
      postIdInput.name = "blog_post_id";
      postIdInput.value = postId;

      const reply = document.createElement("form");
      reply.className = "reply";
      reply.method = "POST";
      reply.action = "./index.php";

      postReplyButton.addEventListener("click", () => {
        reply.submit();
      });

      reply.appendChild(inputContentTitle);
      reply.appendChild(inputContent);
      reply.appendChild(authorContentTitle);
      reply.appendChild(authorContent);
      reply.appendChild(postReplyButtonInvisibleInput);
      reply.appendChild(postIdInput);
      reply.appendChild(postReplyButton);

      post.insertBefore(reply, replies);
      replyButton.removeEventListener("click", replyListener);
    }

    replyButton.addEventListener("click", replyListener);
  }
});
