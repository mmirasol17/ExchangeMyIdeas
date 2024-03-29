<?php

try {
  require_once("./config.php");

  // when creating a blog post, fetch the data submitted from the create blog form
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author = !empty($_POST['author']) ? $_POST['author'] : "Anonymous";

    // if all blog data is set and not empty, insert into the db
    if (isset($title) && isset($content) && isset($author)) {
      $sql = "
        INSERT INTO blog_posts
          VALUES (uuid(), '" . $author . "', '" . $content . "', '" . $title . "', NOW())
      ";

      $conn->exec($sql);
      header("Location: ./index.php");
      die();
    }
  }
} catch (PDOException $e) {
  echo "Error: " . $e->getMessage();
}

?>

<!DOCTYPE html>
<html>

<head>
  <title>ExchangeMyIdeas - Write a Blog</title>
  <link rel="stylesheet" href="./create_blog.css" />
  <script src="./create_blog.js"></script>
</head>

<body>
  <div class="container">
    <div class="header">
      <?php

      if ($_SERVER["REQUEST_METHOD"] == "POST") {
        echo "<div class='title'>Error: Missing required fields</div>";
      }

      ?>
      <div id="go-to-posts" class="back button">
        <svg
          height="1em"
          width="1em"
          fill="#ffffff"
          viewBox="0 0 1024 1024"
          xmlns="http://www.w3.org/2000/svg"
          stroke="#ffffff"
        >
          <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
          <g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g>
          <g id="SVGRepo_iconCarrier">
            <path d="M222.927 580.115l301.354 328.512c24.354 28.708 20.825
                    71.724-7.883 96.078s-71.724 20.825-96.078-7.883L19.576
                    559.963a67.846 67.846 0 01-13.784-20.022 68.03 68.03 0
                    01-5.977-29.488l.001-.063a68.343 68.343 0 017.265-29.134
                    68.28 68.28 0 011.384-2.6 67.59 67.59 0 0110.102-13.687L429.966
                    21.113c25.592-27.611 68.721-29.247 96.331-3.656s29.247
                    68.721 3.656 96.331L224.088 443.784h730.46c37.647 0 68.166
                    30.519 68.166 68.166s-30.519 68.166-68.166 68.166H222.927z"
            ></path>
          </g>
        </svg>
        Go back to posts
      </div>
    </div>

    <form class="post" name="post" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
      <div class="label">Title</div>
      <input class="title" name="title" placeholder="Share your story in a few words 📖"></input>
      <div class="label">Content</div>
      <textarea class="content" name="content" placeholder="Talk about anything you'd like to share 💡"></textarea>
      <div class="label">Name (optional)</div>
      <input class="author" name="author" placeholder="How would you like to be known? 👤" />
      <input type="submit" style="display: none;" />
      <div class="reply button" onClick="document.forms['post'].submit();">Post Blog</div>
    </form>
  </div>
</body>

</html>
