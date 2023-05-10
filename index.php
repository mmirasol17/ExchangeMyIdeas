<?php

try {
  require_once("./config.php");

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $blog_post_id = $_POST['blog_post_id'];
    $reply_author = $_POST['reply_author'];
    $reply_content = $_POST['reply_content'];

    if (isset($blog_post_id) && isset($reply_author) && isset($reply_content) && !empty($blog_post_id) && !empty($reply_author) && !empty($reply_content)) {
      $sql = "INSERT INTO blog_replies VALUES (uuid(), '" . $blog_post_id . "', '" . $reply_author . "', '" . $reply_content . "')";
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
  <link rel="stylesheet" href="./index.css" />
  <script src="./index.js"></script>
</head>

<body>
  <div class="container">
    <form class="header">
      <div class="title">Search:</div>
      <input id="search" name="search" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>" <?= isset($_GET['search']) && !empty($_GET['search']) ? " value=\"" . $_GET['search'] . "\"" : ""; ?> />
      <input type="submit" style="display: none;" />
      <div id="post">Post</div>
    </form>

    <?php

    try {
      require_once("./config.php");

      $statement = "SELECT post_id, author_name, content, title FROM blog_posts";

      $search = $_GET['search'];
      if (isset($search) && !empty($search)) {
        $statement .= " WHERE title LIKE \"%" . $search . "%\" OR content LIKE \"%" . $search . "%\" OR author_name LIKE \"%" . $search . "%\"";
      }

      $query = $conn->prepare($statement);
      $query->execute();

      while ($postsRow = $query->fetch(PDO::FETCH_ASSOC)) {
        echo "<div class=\"post\" id=\"" . $postsRow["post_id"] . "\">";
        echo "  <div class=\"content\">";
        echo "    <div class=\"title\">" . $postsRow["title"] . "</div>";
        echo "    <div class=\"body\">" . $postsRow["content"] . "</div>";
        echo "    <div class=\"author\">Author: <span>" . $postsRow["author_name"] . "</span></div>";
        echo "    <div class=\"reply\">Reply</div>";
        echo "  </div>";
        echo "  <div class=\"replies\">";

        $repliesStatement = $conn->prepare("SELECT author_name, content FROM blog_replies WHERE blog_post_id = \"" . $postsRow["post_id"] . "\"");
        $repliesStatement->execute();

        while ($repliesRow = $repliesStatement->fetch(PDO::FETCH_ASSOC)) {
          echo "    <div class=\"reply\">";
          echo "      <div class=\"body\">" . $repliesRow["content"] . "</div>";
          echo "      <div class=\"author\">Reply Author: <span>" . $repliesRow["author_name"] . "</span></div>";
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