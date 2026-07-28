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

`index.php` is a front controller and the **only** PHP file a browser can
address. Everything else lives in `src/`, which is denied at the web server.
The web root otherwise holds just the files that must sit at a fixed path.

```
├── index.php            Front controller (12 lines; hands off to the router)
├── .htaccess            Rewrites, front-controller fallback, headers, caching
├── robots.txt
├── site.webmanifest
│
├── src/
│   ├── bootstrap.php    Single ordered require for everything below
│   ├── router.php       Route table, legacy 301s, dispatch
│   ├── config.php       PDO connection ($conn)
│   ├── helpers.php      Escaping, formatting, Markdown, schema probes
│   ├── urls.php         Every public URL is built here
│   ├── seo.php          Canonicals, JSON-LD, structured data
│   ├── emoji.php        Computed emoji for tags and UI signals
│   ├── moderation.php   Offensive-content scoring engine
│   ├── moderation_words.php   Term and pattern lists
│   ├── tags.php         Automatic topic extraction
│   ├── posts.php        Shared queries + feature detection
│   ├── view.php         Layout partials
│   ├── views/
│   │   └── post_card.php   One feed card, shared by the feed and the fragment
│   └── pages/
│       ├── feed.php     /, /page/N, /topic/{slug}, /search
│       ├── post.php     /post/{id}  (also owns reply submission)
│       ├── create.php   /new
│       ├── edit.php     /post/{id}/edit
│       ├── moderate.php /moderate   (key-protected)
│       ├── migrate.php  /migrate    (status open; running needs a key)
│       ├── like.php     /like       (JSON)
│       ├── privacy.php  /privacy
│       ├── rss.php      /feed.xml
│       ├── sitemap.php  /sitemap.xml
│       ├── ads.php      /ads.txt    (generated from config)
│       ├── feed_partial.php  /partial/feed  (scroll-loading fragment)
│       └── not_found.php     404
│
├── assets/
│   ├── css/styles.css
│   ├── js/              app.js, index.js, feed-scroll.js, post.js, …
│   └── img/             app icons, and icons/ for favicons and touch icons
│
├── migrations/          Numbered .sql files, applied once each
├── db/                  Schema and seed data (not deployed)
└── tests/run.php        Moderation, tagging, emoji, URL, and SQL-shape tests
```

### URLs

| URL | Page |
|-----|------|
| `/` · `/page/2` | Feed |
| `/topic/ai` · `/topic/ai/page/2` | Posts under a topic |
| `/search?q=…` | Search (never indexed) |
| `/post/{id}` | A post and its replies |
| `/post/{id}/edit` | Edit your own post |
| `/new` | Write a post |
| `/privacy` · `/feed.xml` · `/sitemap.xml` · `/ads.txt` | — |

Sorting stays a query parameter (`/?sort=liked`) on purpose: it is a view
preference, not a distinct document, and giving it a path would invite search
engines to index three URLs holding the same posts in a different order.

Every pre-front-controller URL (`/index.php`, `/post.php?id=…`,
`/create_blog.php`, …) **301s** to its modern equivalent, so anything already
shared or indexed keeps working and passes its ranking on.

### The one deployment dependency

Routing needs `mod_rewrite`. If it is missing, `.htaccess` falls back to
`ErrorDocument 404 /index.php`, and the site still serves entirely correct
pages — but Apache stamps them all `404`, which is fine for humans and fatal
for search engines. This was measured, not assumed: Apache 2.4 behind
`mod_proxy_fcgi` ignores a `Status:` header from an ErrorDocument handler.

Confirm rewrites are live in about ten seconds after a deploy:

```sh
curl -sI https://exchangemyideas.marinmirasol.com/new | head -1
# HTTP/1.1 200 OK   -> rewrites working
# HTTP/1.1 404      -> running on the fallback; ask the host to enable mod_rewrite
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
- **flag** - held back and queued at `/moderate` for review. The author is
  told their post is awaiting review rather than being shown a 404.
- **allow** - published normally.

`MODERATION_HIDE_FLAGGED` decides whether flagged content waits for approval
or publishes immediately. It is **on**, which is the right posture for a site
carrying ads: an ad network holds the publisher responsible for everything on
the page, including what other people posted, so "publish now, review later"
puts the account on the line for content nobody has looked at yet.

Whichever way it is set, every query that shows something to the public goes
through one `visibility_sql()` helper. Five hand-written status checks had
already drifted apart, and only one of them honoured the setting — turning it
on would have hidden posts from the feed while still counting them, still
showing their replies, and still listing their topics.

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

### Markdown editor

`/new` and the edit page mount a toolbar, keyboard shortcuts (Ctrl/Cmd+B, I, K),
and a live preview onto the plain `<textarea>`. The textarea keeps its name and
value throughout, so with `editor.js` absent the form still submits exactly as
before.

The preview is rendered by the **server** (`POST /preview`), not by a Markdown
library in the browser. Two renderers for one syntax inevitably drift — the
preview shows one thing and the published post another — and the JS one would
carry its own escaping rules to get wrong. Rendering through the same
`render_markdown()` the real post uses means the preview *is* the output, and
one security review covers both paths.

The supported subset is headings, bold, italic, strikethrough, inline and
fenced code, lists, blockquotes, links, and horizontal rules. **Images and raw
HTML are deliberately unsupported**: the content filter reads text and cannot
judge what sits behind a URL, so allowing `<img>` would be both a moderation
and a privacy hole.

### Reading modal

Clicking **anywhere on a feed card** opens the post in a scrollable overlay
instead of navigating — a long post gets its own scroll container without
costing the reader their place in the feed.

"Anywhere" has to be careful, because a card is full of things that own their
own click. Likes, share, tag chips, the Reply button, and links written into
the post body all keep their behaviour; so does finishing a text selection. The
title stays an ordinary link to `/post/{id}`, which is what keeps the post
reachable by keyboard and by crawlers.

The handler is scoped to the feed container specifically, so the permalink page
— which renders the same `.post` markup — cannot open a modal of itself.

The click is intercepted only for a plain left-click with `fetch` available, so
middle-click, Cmd-click, "open in new tab", and crawlers all get the real page.
While open, the URL is pushed to the permalink, so the address bar, Back button,
and copy-link all behave as if it were a page. The pointer cursor is applied by
JavaScript, never in the markup — without it the card is not clickable and
should not claim to be.

### Scroll-loaded pagination

The server renders page 1 plus a working numbered pager;
`assets/js/feed-scroll.js` then appends pages from `/partial/feed` as you
scroll, and hides the pager **only after a page has actually loaded**. That
ordering matters: hiding it up front assumes the observer will fire, and if it
never does (an embedded page, a blocked fetch) the reader is left with no
pagination at all.

Cards come from the same partial either way, so a scrolled-in card is identical
to a server-rendered one. With JavaScript off, or for a crawler, it stays an
ordinary paginated feed.

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
   php -S localhost:8000 index.php
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

**They apply themselves** on the first request after a deploy — there is no key
to remember and no page to visit. Migrating from the request path is normally a
bad idea, so each objection is handled explicitly (see `src/migrator.php`):

| Concern | Handling |
|---------|----------|
| Cost | A marker file records which migration set is applied. When it matches — virtually every request — this costs one filesystem read and **zero** queries. |
| Collision | A MySQL advisory lock (`GET_LOCK`, zero timeout) means exactly one request migrates; the rest serve their page immediately rather than queueing behind an `ALTER`. |
| Failure | Errors are logged, never thrown. The app detects its own schema, so a failed migration degrades to the previous feature set instead of a white page. A failure marker backs off retries. |

Verified with 12 simultaneous requests against an unmigrated database: every
migration recorded exactly once, no duplicate-column errors.

This assumes migrations are **additive and safe to run unattended**, which is
the rule this project already follows. For anything destructive, set
`MIGRATIONS_AUTO_APPLY` to `false` and apply it deliberately.

`/migrate` still exists, and now reports status **without a key** — it only
lists filenames that are already public in this repository. Actually *running*
a migration (`/migrate?run=1&key=…`) still requires `$migrateKey`, because that
writes to the schema.

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
| `MIGRATE_KEY` | Protects `/migrate` |
| `ADMIN_KEY` | Protects `/moderate` (falls back to `MIGRATE_KEY`) |
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
