window.addEventListener("load", () => {
  const postElement = document.getElementById("post");
  postElement.addEventListener("click", () => {
    window.location.href = "./create_blog.html";
  });

  const searchElement = document.getElementById("search");
  searchElement.addEventListener("keypress", (event) => {
    if (event.key !== "Enter") {
      return;
    }

    const searchInput = event.target.value;
    console.log(searchInput);
  });

  const post = document.getElementById("post_id_here");
  const replies = post.querySelector(".replies");
  const replyButton = post.querySelector(".reply");

  function replyListener() {
    const inputContentTitle = document.createElement("div");
    inputContentTitle.className = "reply_content_title";
    inputContentTitle.innerText = "What would you like to reply?";

    const inputContent = document.createElement("textarea");
    inputContent.className = "reply_content";

    const authorContentTitle = document.createElement("div");
    authorContentTitle.className = "reply_content_author";
    authorContentTitle.innerText = "What's your name?";

    const authorContent = document.createElement("input");
    authorContent.className = "reply_author";

    const postReplyButton = document.createElement("div");
    postReplyButton.className = "post_reply";
    postReplyButton.innerText = "Post Reply";

    postReplyButton.addEventListener("click", () => {
      console.log("post reply button clicked");
      console.log("content", inputContent.value);
      console.log("author", authorContent.value);
    });

    const reply = document.createElement("div");
    reply.className = "reply";
    reply.appendChild(inputContentTitle);
    reply.appendChild(inputContent);
    reply.appendChild(authorContentTitle);
    reply.appendChild(authorContent);
    reply.appendChild(postReplyButton);

    post.insertBefore(reply, replies);
    replyButton.removeEventListener("click", replyListener);
  }

  replyButton.addEventListener("click", replyListener);
});
