window.addEventListener("load", () => {
  const goToPosts = document.getElementById("go_to_posts");
  goToPosts.addEventListener("click", () => {
    window.location.href = "./index.html";
  });

  const postButton = document.getElementById("post_blog");
  postButton.addEventListener("click", () => {
    // do stuff here
    window.location.href = "./index.html";
  });
});
