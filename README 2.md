# ExchangeMyIdeas

A minimalistic blog website where anyone can post an idea, reply to others, and
search everything that's been shared.

Originally a CSUSM CIS444 Web Programming team project, since restyled to match
[marinmirasol.com](https://marinmirasol.com) and rehosted at
**[exchangemyideas.marinmirasol.com](https://exchangemyideas.marinmirasol.com)**
after the original `exchangemyideas.online` domain lapsed.

Built by [Marin Mirasol](https://www.linkedin.com/in/marin-mirasol/),
[Amer (Junior) Yono](https://www.linkedin.com/in/amer-yono/), and
[Corey Taylor](https://www.linkedin.com/in/corey-taylor-9a9bb1209/).

---

## Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP (server-rendered, PDO) |
| Database | MySQL |
| Frontend | Vanilla JavaScript, hand-written CSS |
| Hosting | InfinityFree shared hosting, subdomain of marinmirasol.com |

No build step and no framework - the pages are served directly by PHP.

---

## Layout

The web root holds only two kinds of file: **entry points** (a URL is literally
`/post.php?id=…`, so these cannot move without changing every link) and files
that **must** sit at a fixed path (`robots.txt`, `site.webmanifest`). Everything
else is in `src/` or `assets/`. `src/` ships an `.htaccess` denying direct web
access, since nothing in it is meant to be requested.

```
├── index.php            Feed: search, topic filter, sort, pagination
├── post.php             Permalink + full reply thread (owns reply submission)
├── create_blog.php      New post form and insert
├── edit_post.php        Edit/delete your own post
├── moderate.php         Moderation review queue (key-protected)
├── migrate.php          Migration runner (key-protected)
├── feed_more.php        HTML fragment: one page of feed cards, for scroll-loading
├── feed.php             RSS 2.0  → also served at /rss.xml
├── sitemap.php          XML sitemap → also served at /sitemap.xml
├── ads.php              ads.txt, generated from config → served at /ads.txt
├── like.php             Like endpoint (JSON)
├── privacy.php          Privacy policy
├── .htaccess            Rewrites, security headers, caching
│
├── src/
│   ├── bootstrap.php    Single require for everything below; provides $conn
│   ├── config.php       PDO connection
│   ├── helpers.php      Escaping, formatting, Markdown, schema probes
│   ├── urls.php         URL construction
│   ├── emoji.php        Computed emoji for tags and UI signals
│   ├── moderation.php   Offensive-content scoring engine
│   ├── moderation_words.php   Term and pattern lists
│   ├── tags.php         Automatic topic extraction
│   ├── posts.php        Shared queries + feature detection
│   ├── view.php         Layout partials
│   └── views/
│       └── post_card.php   One feed card, shared by the feed and the fragment
│
├── assets/
│   ├── css/styles.css
│   ├── js/              app.js, index.js, feed-scroll.js, post.js, …
│   └── img/             app icons, and icons/ for favicons and touch icons
│
├── migrations/          Numbered .sql files, applied once each
├── db/                  Schema and seed data (not deployed)
└── tests/run.php        Tests for moderation, tagging, and emoji
```

---

## Features

### Automatic topic tagging

Nobody types tags in. `extract_tags()` reads a post's title and body and works
out what it is about: first against a curated topic dictionary (so posts about
the same thing land on the same tag instead of splintering into near
duplicates), then by salient-keyword extraction for subjects the dictionary has
never heard of. Each tag gets an emoji derived the same way - from the slug, at
render time - so improving the mapping improves every tag already in the
database with no backfill and no schema change.

Posts written before tagging existed can be backfilled from the Tools section
of the moderation queue.

### Offensive-content detection

Every post and reply is scored by a local engine (`src/moderation.php`) with no
network calls - the host blocks outbound HTTP, so a third-party moderation API
would time out on every submission. It normalises leetspeak, homoglyphs, and
spaced-out obfuscation before matching, then returns one of three verdicts:

- **block** - refused at submission time, with an explanation, and the author
  keeps what they wrote.
- **flag** - published, and queued at `moderate.php` for review.
- **allow** - published normally.

The middle tier is the point. Text classification without context gets
ambiguous cases wrong in both directions, so anything genuinely uncertain goes
to a human instead of being silently eaten. Word-boundary matching plus an
allowlist keep the Scunthorpe problem at bay; `tests/run.php` pins the
false-positive cases as carefully as the detection ones.

### Editing without accounts

Creating a post mints a random token in the browser and stores only its
SHA-256 hash server-side. "Logged in" here means "this is the browser that wrote
the post" - clear your browser data and the post becomes uneditable. That is the
honest trade for collecting nothing, and the UI says so rather than hiding it.

### Scroll-loaded pagination

The server renders page 1 plus a working numbered pager;
`assets/js/feed-scroll.js` then hides the pager and appends pages as you scroll,
using `feed_more.php`. Cards come from the same partial either way, so a
scrolled-in card is identical to a server-rendered one. With JavaScript off, or
for a crawler, it stays an ordinary paginated feed.

---

## Local development

1. Create the database and tables:

   ```sh
   mysql -u root -p -e "CREATE DATABASE blog;"
   mysql -u root -p blog < db/schema.sql
   ```

2. Point the app at it - either export the environment variables:

   ```sh
   export DB_HOST=localhost DB_NAME=blog DB_USER=root DB_PASS=your_password
   ```

   …or copy the example config for hosts without env var support:

   ```sh
   cp config.local.php.example config.local.php   # then edit it
   ```

3. Apply the migrations, and optionally seed some content:

   ```sh
   for f in migrations/*.sql; do mysql -u root -p blog < "$f"; done
   mysql -u root -p blog < db/seed.sql
   ```

4. Serve it:

   ```sh
   php -S localhost:8000
   ```

   Then open <http://localhost:8000/index.php>.

5. Run the tests:

   ```sh
   php tests/run.php
   ```

---

## Database migrations

Each `.sql` file in `migrations/` runs **once**, in filename order, tracked in a
`schema_migrations` table.

Migrations are applied by visiting the runner in a browser, not from CI:
InfinityFree blocks remote MySQL connections and challenges non-browser HTTP
requests, so a GitHub Actions runner can reach neither the database nor this
endpoint.

```
https://exchangemyideas.marinmirasol.com/migrate.php?key=YOUR_KEY
```

Because a deploy always lands before its migration does, every feature checks
for its own columns first (`site_caps()` in `src/posts.php`) and falls back to
the previous behaviour until the migration runs. New code on an old schema is a
missing feature, never a broken page.

---

## Deploying

Pushing to `main` deploys over FTP via `.github/workflows/deploy.yml`.

`config.local.php` holds the credentials, lives only on the server, and is
excluded from the deploy so it is never overwritten. To create it, set the
repository secrets and run the **Provision server config** workflow once:

| Secret | Purpose |
|--------|---------|
| `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | Deploy target |
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Database |
| `MIGRATE_KEY` | Protects `migrate.php` |
| `ADMIN_KEY` | Protects `moderate.php` (falls back to `MIGRATE_KEY`) |
| `IP_SALT` | Salt for the one-way IP hashes used in rate limiting |
| `ADSENSE_CLIENT`, `ADSENSE_SLOT` | Advertising, below |

---

## Advertising

The code is already wired for AdSense. Ads appear the moment `$adsenseClient`
and `$adsenseSlot` are set - until then every slot renders a labelled
placeholder. What is left is the account side:

1. **Apply at [google.com/adsense](https://www.google.com/adsense/)** with
   `exchangemyideas.marinmirasol.com`. A subdomain is fine.

2. **Add the site and let Google verify it.** Verification looks for the
   AdSense loader script in the page `<head>`. `render_head()` already emits it
   on every page - but only once `$adsenseClient` is set, so set that (via the
   `ADSENSE_CLIENT` secret, then run the provisioning workflow) *before* asking
   Google to verify. `$adsenseSlot` can stay empty at this stage.

3. **Wait for approval.** This is what actually gates ads showing, and it is a
   content review rather than a technical one. Google wants a site with enough
   original content to be worth advertising on, a privacy policy (there is one
   at `/privacy.php`, linked in the footer), and working navigation. A
   near-empty blog is the usual rejection reason - publish real posts first.

4. **Create an ad unit** once approved and set `ADSENSE_SLOT` to its slot id,
   then re-run the provisioning workflow. Real ads now render in the in-feed
   and sidebar slots.

5. **Check `/ads.txt`.** It is generated by `ads.php` from `$adsenseClient`, so
   it becomes valid on its own. Visit it and confirm it shows a
   `google.com, pub-…` line rather than the "not configured yet" comment.

> The repository used to ship a static `ads.txt` holding a placeholder
> publisher id. That is worse than having no file at all - AdSense reports a
> wrong id as an *invalid* ads.txt, which counts against the account. It is now
> generated, so it is either correct or absent.

Two things worth knowing before spending time here: AdSense will not approve a
site with little content, and ads earn very little at low traffic.

---

## Security notes

The original class project shipped with two vulnerability classes that have
since been fixed:

- **SQL injection** - the search box and the post-insert built SQL by string
  concatenation, so `'` broke queries and crafted input ran arbitrary SQL.
  Every query is now a bound PDO prepared statement with emulation disabled.
- **Stored XSS** - post and reply content was echoed to the page raw, so any
  submitted `<script>` executed for every later visitor. All user-supplied
  output now passes through `htmlspecialchars`, including in the Markdown
  renderer, which escapes first and formats the already-safe text afterwards.

Since then:

- Connection failures log server-side and return a 503 rather than printing
  PDO's exception text, which leaks the host and username.
- Posting is rate limited per hashed IP, with a honeypot field against bots.
- `src/` and `migrations/` deny direct web access.
- Absolute URLs validate the `Host` header instead of trusting it, so it cannot
  be used to rewrite canonical tags, OG metadata, or the RSS feed.
- Edit tokens are compared with `hash_equals()` and stored only as hashes.

Posting remains unauthenticated by design, as in the original project.
Moderation is automated with a human review queue rather than pre-approval.
