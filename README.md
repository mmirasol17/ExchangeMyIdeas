# ExchangeMyIdeas

A minimalistic blog website where users can post blogs, view other posts, and reply to them.

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
| Hosting | Shared PHP host, subdomain of marinmirasol.com |

No build step and no framework — the pages are served directly by PHP.

---

## Files

| File | Purpose |
|------|---------|
| `index.php` | Blog feed, search, and reply handling |
| `create_blog.php` | New post form and insert |
| `config.php` | PDO connection, credentials read from the environment |
| `styles.css` | Shared stylesheet, mirrors the marinmirasol.com design tokens |
| `index.js` | Inline reply form construction |
| `create_blog.js` | Back-navigation on the create page |
| `sql.txt` | Schema for `blog_posts` and `blog_replies` |

---

## Local development

1. Create the database and tables:

   ```sh
   mysql -u root -p -e "CREATE DATABASE blog;"
   mysql -u root -p blog < sql.txt
   ```

2. Point the app at it — either export the environment variables:

   ```sh
   export DB_HOST=localhost DB_NAME=blog DB_USER=root DB_PASS=your_password
   ```

   …or copy the example config for hosts without env var support:

   ```sh
   cp config.local.php.example config.local.php   # then edit it
   ```

3. Serve it:

   ```sh
   php -S localhost:8000
   ```

   Then open <http://localhost:8000/index.php>.

---

## Deploying

The app runs on any shared host with PHP and MySQL. To deploy:

1. Create the database on the host and import `sql.txt`.
2. Upload every file **except** `config.local.php.example` and `.gitignore`.
3. Create `config.local.php` on the server with the host's database
   credentials (or set the env vars, if the host supports them).
4. Register `exchangemyideas.marinmirasol.com` as a subdomain in the host's
   control panel, then add the DNS record it gives you at your registrar.

Because `config.local.php` is gitignored, credentials live only on the server.

---

## Security notes

The original class project shipped with two vulnerability classes that have
since been fixed:

- **SQL injection** — the search box and the post-insert built SQL by string
  concatenation, so `'` broke queries and crafted input ran arbitrary SQL.
  Every query is now a bound PDO prepared statement with emulation disabled.
- **Stored XSS** — post and reply content was echoed to the page raw, so any
  submitted `<script>` executed for every later visitor. All user-supplied
  output now passes through `htmlspecialchars`.

Connection failures also no longer print PDO's exception text (which leaks the
host and username) to visitors; they log server-side and return a 503.

Note that posting is still unauthenticated by design, as in the original
project — there is no spam protection or moderation.
