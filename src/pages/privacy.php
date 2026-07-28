<?php
/*
 * Privacy policy. Serves /privacy.
 *
 * Kept accurate rather than boilerplate: AdSense requires a privacy policy, and
 * a policy that does not actually describe what the site does is worse than
 * none. Anything the code stores is disclosed here.
 */

$breadcrumbs = [
  ['name' => 'Home', 'path' => '/'],
  ['name' => 'Privacy Policy', 'path' => '/privacy'],
];

render_head('Privacy Policy - ExchangeMyIdeas', '', [
  'description' => 'What ExchangeMyIdeas stores, how content is moderated, and what your browser keeps.',
  'canonical'   => '/privacy',
  'jsonld'      => [jsonld_website(), jsonld_breadcrumbs($breadcrumbs)],
]);
?>

  <div class="container container-narrow">
    <?php render_breadcrumbs($breadcrumbs); ?>

    <header class="hero">
      <h1 class="page-title">Privacy Policy</h1>
      <p class="page-subtitle">How ExchangeMyIdeas handles your data.</p>
    </header>

    <div class="post-form prose">
      <p>Last updated: <?= date('F Y') ?>.</p>

      <h2>What we collect</h2>
      <p>ExchangeMyIdeas is a minimalist blog. When you submit a post or reply,
        we store the text you enter and the display name you provide (or
        &ldquo;Anonymous&rdquo; if you leave it blank). We do not require an
        account and we do not ask for your email address.</p>
      <p>We also store a one-way, salted hash of your IP address alongside each
        submission. We do <strong>not</strong> store the address itself, and the
        hash cannot be turned back into one. It exists only to enforce rate
        limits and to recognise a repeat abuser, and it is never shared.</p>

      <h2>Content moderation</h2>
      <p>Every post and reply is checked by an automated filter that runs
        entirely on this server. Nothing you write is sent to a third-party
        moderation service. The filter looks for slurs, threats, sexual content,
        and spam, and does one of three things: publishes your submission,
        refuses it and tells you why, or holds it for a human to review before
        it appears publicly. Held content is never deleted, and you are told
        when yours is waiting.</p>
      <p>Automated filters make mistakes in both directions. If yours was
        wrongly refused, rephrasing usually clears it &mdash; and if you think
        something was wrongly removed, get in touch using the link below.</p>

      <h2>What your browser stores</h2>
      <p>The site keeps a small amount of data in your browser's local storage:
        which posts you have liked, and an edit key for each post you write.
        That key is what lets you edit or delete your own post without an
        account &mdash; only a hash of it ever reaches the server. None of this
        is sent anywhere else, and clearing your browser data removes it (along
        with your ability to edit posts you made from that browser).</p>

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
