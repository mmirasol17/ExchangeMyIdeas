window.addEventListener("DOMContentLoaded", () => {
  // "Go back to posts" returns to the blog feed
  const goToPosts = document.getElementById("go-to-posts");
  if (goToPosts) {
    goToPosts.addEventListener("click", () => {
      window.location.href = "./index.php";
    });
  }

  // Live character counter for the content field
  const content = document.querySelector("textarea[name='content']");
  const counter = document.getElementById("char-count");
  if (content && counter) {
    const update = () => {
      const n = content.value.length;
      counter.textContent = n + " character" + (n === 1 ? "" : "s");
    };
    content.addEventListener("input", update);
    update();
  }
});
