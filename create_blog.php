<?php

try {
  require_once("./config.php");

  // when creating a blog post, fetch the data submitted from the create blog form
  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $author = $_POST['author'];

    // if all blog data is set and not empty, insert into the db
    if (isset($title) && isset($content) && isset($author)) {
      $sql = "
        INSERT INTO blog_posts
          VALUES (uuid(), '" . $author . "', '" . $content . "', '" . $title . "')
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
  <title>CougarBlogs</title>
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
      <div id="go_to_posts" class="button">Go back to posts</div>
    </div>

    <form class="post" name="post" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
      <div class="content">
        <div class="title">Title</div>
        <input name="title"></input>
        <div class="title">Content</div>
        <textarea name="content"></textarea>
        <div class="author">Name</div>
        <input name="author" />
        <input type="submit" style="display: none;" />
        <div class="reply" onClick="document.forms['post'].submit();">Post Blog</div>
      </div>
    </form>
  </div>
</body>

</html>