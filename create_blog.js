window.addEventListener("load", () => {
  // reference the form so when the user clicks the submit button, the form is submitted
  const goToPosts = document.getElementById("go-to-posts");
  goToPosts.addEventListener("click", () => {
    window.location.href = "./index.php";
  });
});
