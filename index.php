<?php
  try {
    require_once("./config.php");

    // get the blog info from the submitted form (replying to a blog)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $blog_post_id = $_POST['blog_post_id'];
      $reply_author = $_POST['reply_author'];
      $reply_content = $_POST['reply_content'];

      // if all reply data is set and not empty, insert into the db
      if (isset($blog_post_id) &&
          isset($reply_author) &&
          isset($reply_content) &&
          !empty($blog_post_id) &&
          !empty($reply_author) &&
          !empty($reply_content)
      ) {
        // insert the reply into the db
        $sql = "
          INSERT INTO blog_replies
            VALUES (uuid(), '" . $blog_post_id . "', '" . $reply_author . "', '" . $reply_content . "')
        ";

        // execute the sql query
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
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">

<head>
  <title>CougarBlogs</title>
  <link rel="stylesheet" href="./index.css" />
  <script src="./index.js"></script>
</head>

<body>
  <div class="container">
    <form class="header">
      <input
        id="search"
        name="search"
        method="post"
        placeholder="Search for anything..."
        action="
          <?php echo $_SERVER['PHP_SELF']; ?>"
          <?= isset($_GET['search']) && !empty($_GET['search']) ? " value=\"" . $_GET['search'] . "\"" : ""; ?>
      />
      <input type="submit" style="display: none;" />
      <div id="post" class="button">Post</div>
    </form>

    <?php
      try {
        require_once("./config.php");

        // for the search bar, fetch all posts based on title, content, or author name with the keyword
        $statement = "
          SELECT post_id, author_name, content, title
          FROM blog_posts
        ";

        $search = $_GET['search'];
        if (isset($search) && !empty($search)) {
          $statement .= "
            WHERE title LIKE \"%" . $search . "%\"
              OR content LIKE \"%" . $search . "%\"
              OR author_name LIKE \"%" . $search . "%\"
          ";
        }
        // execute the sql query
        $query = $conn->prepare($statement);
        $query->execute();

        // get all the posts from the db and display them
        while ($postsRow = $query->fetch(PDO::FETCH_ASSOC)) {
          echo "<div class=\"post\"" . $postsRow["post_id"] . "\">";
          echo "  <div class=\"content\">";
          echo "    <div class=\"title\">" . $postsRow["title"] . "</div>";
          echo "    <div class=\"body\">" . $postsRow["content"] . "</div>";
          echo "    <div class=\"footer\">";
          echo "      <div class=\"author\"><span>" . $postsRow["author_name"] . "</span></div>";
          echo "      <div id=\"reply\" class=\"button\">Reply</div>";
          echo "    </div>";
          echo "  </div>";
          echo "  <div class=\"replies\">";

          // get all the replies for the current post and display them
          $repliesStatement = $conn->prepare("
            SELECT author_name, content
            FROM blog_replies
            WHERE blog_post_id = \"" . $postsRow["post_id"] . "\"
          ");
          $repliesStatement->execute();
          
          // post the reply info for the current post
          while ($repliesRow = $repliesStatement->fetch(PDO::FETCH_ASSOC)) {
            echo "    <div id=\"reply\" class=\"reply\">";
            echo "      <div class=\"body\">" . $repliesRow["content"] . "</div>";
            echo "      <div class=\"author\"><span>" . $repliesRow["author_name"] . "</span></div>";
            echo "    </div>";
          }

          echo "  </div>";
          echo "</div>";
        }
      } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
      }
    ?>

  </div>
</body>

</html>