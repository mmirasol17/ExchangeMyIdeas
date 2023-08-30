<?php
  try {
    require_once("./config.php");

    // get the blog info from the submitted form (replying to a blog)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      $blog_post_id = $_POST['blog_post_id'];
      $reply_content = $_POST['reply_content'];
      $reply_author = !empty($_POST['reply_author']) ? $_POST['reply_author'] : "Anonymous";

      // if all reply data is set and not empty, insert into the db
      if (isset($blog_post_id) &&
          isset($reply_author) &&
          isset($reply_content) &&
          !empty($blog_post_id) &&
          !empty($reply_author) &&
          !empty($reply_content)
      ) {
        $sql = "
          INSERT INTO blog_replies
            VALUES (uuid(), :blog_post_id, :reply_author, :reply_content, NOW())
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':blog_post_id', $blog_post_id);
        $stmt->bindParam(':reply_author', $reply_author);
        $stmt->bindParam(':reply_content', $reply_content);

        $stmt->execute();
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
  <title>ExchangeMyIdeas</title>
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
          SELECT post_id, author_name, content, title, date_posted
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
          echo "<div class=\"post\" id=\"" . $postsRow["post_id"] . "\">";
          echo "  <div class=\"content\">";
          echo "    <div class=\"date\">" . date("d M Y", strtotime($postsRow["date_posted"])) . "</div>";
          echo "    <div class=\"title\">" . $postsRow["title"] . "</div>";
          echo "    <div class=\"body\">" . nl2br($postsRow["content"]) . "</div>";
          echo "    <div class=\"footer\">";
          echo "      <div class=\"author\"><span>👤 " . $postsRow["author_name"] . "</span></div>";
          echo "      <div id=\"reply\" class=\"button\">Reply</div>";
          echo "    </div>";
          echo "  </div>";
          echo "  <div class=\"replies\">";

          // get all the replies for the current post and display them
          $repliesStatement = $conn->prepare("
            SELECT author_name, content, date_posted
            FROM blog_replies
            WHERE blog_post_id = \"" . $postsRow["post_id"] . "\"
          ");
          $repliesStatement->execute();
          
          // post the reply info for the current post
          while ($repliesRow = $repliesStatement->fetch(PDO::FETCH_ASSOC)) {
            echo "    <div id=\"reply\" class=\"reply\">";
            echo "      <div class=\"content\">";
            echo "        <div class=\"date\">" . date("d M Y", strtotime($repliesRow["date_posted"])) . "</div>";
            echo "        <div class=\"body\">" . nl2br($repliesRow["content"]) . "</div>";
            echo "        <div class=\"author\"><span>👤 " . $repliesRow["author_name"] . "</span></div>";
            echo "      </div>";
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

  <!-- site footer -->
  <div class="site-footer">
    <div class="developer">Developed by:&nbsp;
      <!-- show developer's with links to linkedin (don't put a space after each comma) -->
      <a href="https://www.linkedin.com/in/marin-mirasol/" target="_blank" class="footer-link">Marin Mirasol</a>,
      <a href="https://www.linkedin.com/in/amer-yono/" target="_blank" class="footer-link">Amer (Junior) Yono</a>, and
      <a href="https://www.linkedin.com/in/corey-taylor-9a9bb1209/" target="_blank" class="footer-link">Corey Taylor</a>.
    </div>
    <!-- show copyright info -->
    <div class="copy">&copy; <?php echo date("Y"); ?> ExchangeMyIdeas.online</div>
  </div>

</body>

</html>