<?php
require_once('lib.php');
render_head('ExchangeMyIdeas — Privacy Policy', 'create_blog.js');
?>

  <div class="container container-narrow">
    <header class="hero">
      <h1 class="page-title">Privacy Policy</h1>
      <p class="page-subtitle">How ExchangeMyIdeas handles your data.</p>
    </header>

    <div class="header">
      <a href="./index.php" class="button secondary">&larr; Back to posts</a>
    </div>

    <div class="post-form prose">
      <p>Last updated: <?= date('F Y') ?>.</p>

      <h2>What we collect</h2>
      <p>ExchangeMyIdeas is a minimalist blog. When you submit a post or reply,
        we store the text you enter and the display name you provide (or
        &ldquo;Anonymous&rdquo; if you leave it blank). We do not require an
        account and we do not ask for your email address.</p>

      <h2>Cookies and advertising</h2>
      <p>This site may display ads served by Google AdSense. Third-party
        vendors, including Google, use cookies to serve ads based on your prior
        visits to this and other websites. Google's use of advertising cookies
        enables it and its partners to serve ads to you based on your visit to
        this site and/or other sites on the Internet.</p>
      <p>You may opt out of personalized advertising by visiting
        <a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer">Google Ads Settings</a>.
        For more on how Google uses data, see
        <a href="https://policies.google.com/technologies/partner-sites" target="_blank" rel="noopener noreferrer">Google's policies</a>.</p>

      <h2>Analytics</h2>
      <p>Basic, non-identifying request information (such as counts of posts and
        replies) may be recorded to keep the site running. We do not sell any
        data.</p>

      <h2>Contact</h2>
      <p>Questions? Reach out via
        <a href="https://marinmirasol.com" target="_blank" rel="noopener noreferrer">marinmirasol.com</a>.</p>
    </div>
  </div>

<?php render_footer(); ?>
