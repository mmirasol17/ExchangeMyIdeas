window.addEventListener("DOMContentLoaded", () => {
  // "Go back to posts" returns to the blog feed
  const goToPosts = document.getElementById("go-to-posts");
  goToPosts.addEventListener("click", () => {
    window.location.href = "./index.php";
  });
});
