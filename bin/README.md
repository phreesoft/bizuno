# Bizuno tooling

Small CLI helpers that live with the code but aren't loaded at request time.

## `docs-sync.php`

Pushes the Markdown user manual under `bizuno/docs/` to BetterDocs on a
WordPress site via the WP REST API. See
[../docs/CONTRIBUTING-DOCS.md](../docs/CONTRIBUTING-DOCS.md) for the
authoring workflow that feeds this script.

### One-time setup (per maintainer / per CI runner)

1. **Install dev dependencies** so `erusev/parsedown` is available:
   ```bash
   composer install   # picks up require-dev
   ```

2. **Generate a WordPress Application Password** in your bizuno.com WP
   admin: *Users → Profile → Application Passwords → Add New*. Name it
   something like `docs-sync` so it's revokable. Copy the password.
   *(WP Application Passwords need WP 5.6+ over HTTPS.)*

3. **Make sure the WP user has rights** to create/edit posts in the
   `docs` custom post type and terms in the `doc_category` taxonomy.
   The Administrator role does by default; an Editor role with custom
   caps also works.

4. **Initialize the slug → post-ID cache** by running a one-time map
   rebuild. This lets the script know which existing WP docs match
   which markdown files.
   ```bash
   php bin/docs-sync.php \
     --init-map \
     --user='your-wp-user' \
     --pass='xxxx xxxx xxxx xxxx' \
     --dry-run
   ```
   Drop `--dry-run` once you're happy with what it found.
   `docs/.sync-map.json` gets written; **commit it to git** so future
   runs (and CI) start with the same cache.

### Routine use

```bash
# Default: sync only `status: published` pages
php bin/docs-sync.php --user=<user> --pass=<pass>

# Push drafts to a staging site too (lets you preview before publishing)
php bin/docs-sync.php \
  --site=https://staging.bizuno.com \
  --user=<user> --pass=<pass> \
  --status=draft

# Preview without making any HTTP calls
php bin/docs-sync.php --dry-run

# Sync only one section
php bin/docs-sync.php --only=02-core-concepts

# Sync only a single file
php bin/docs-sync.php --only=fiscal-year-close.md
```

### Credentials in CI

Set environment variables — never put creds in the repo:

```bash
BIZUNO_DOCS_USER='ci'
BIZUNO_DOCS_PASS='xxxx xxxx xxxx xxxx'
BIZUNO_DOCS_SITE='https://bizuno.com'
```

A GitHub Actions step would look like:

```yaml
- name: Sync docs to bizuno.com
  if: github.ref == 'refs/heads/main'
  env:
    BIZUNO_DOCS_USER: ${{ secrets.WP_DOCS_USER }}
    BIZUNO_DOCS_PASS: ${{ secrets.WP_DOCS_PASS }}
    BIZUNO_DOCS_SITE: https://bizuno.com
  run: |
    composer install --no-interaction --prefer-dist
    php bin/docs-sync.php
```

### How it works

For every `.md` file under `docs/` (excluding `README.md`s and the
contributing guide):

1. **Parse front matter** (`title`, `category`, `order`, `status`,
   `audience`, `last-updated`)
2. **Filter by `status`** — default keeps only `published`
3. **Render Markdown → HTML** via Parsedown (handles tables, code blocks,
   lists, links, nested formatting)
4. **Derive a stable slug** from the file's basename (numeric prefix
   stripped) — so a file moved between sections keeps the same WP post
5. **Ensure the BetterDocs category exists** (create if missing)
6. **Look up the WP post by slug** (cache-first, fall back to REST query)
7. **Update or create** the post; persist the slug → ID mapping

### Status levels

| Status      | When to use                                   | Default sync includes |
|-------------|-----------------------------------------------|------------------------|
| `stub`      | Heading + intent bullets, not yet written     | no                     |
| `draft`     | First pass written, needs review              | no                     |
| `review`    | Awaiting SME validation                       | no                     |
| `published` | Approved, ships to bizuno.com                 | **yes**                |

The `--status` flag is a *floor* — `--status=review` syncs `review` +
`published`; `--status=draft` syncs `draft` + `review` + `published`;
`--status=all` syncs everything including stubs (useful for sandbox sites).

### What gets posted to BetterDocs

For each markdown file:

| Markdown source            | Becomes                                    |
|----------------------------|--------------------------------------------|
| `title:` front matter      | WP post title                              |
| File basename (de-prefixed)| WP slug — stable across file moves         |
| `order:` front matter      | `menu_order` (display order within category)|
| Directory name             | BetterDocs category (created if missing)   |
| Markdown body              | HTML body, with audience + last-updated header prepended |
| `audience:` front matter   | Rendered into the meta header              |
| `last-updated:` front matter | Rendered into the meta header            |

A small "Edit on GitHub" link is appended to the meta header pointing
back at the markdown source, so end users can suggest improvements.

### What it does NOT do

- **Doesn't delete WP docs that have been removed from the repo.**
  Removing a markdown file leaves the WP post alone — clean those up
  in WP admin if you want them gone. (A `--prune` flag could be added
  if this becomes needed.)
- **Doesn't sync section README.md files as docs.** They're for
  GitHub readers; on WP they're replaced by BetterDocs' category
  archive pages.
- **Doesn't sync CONTRIBUTING-DOCS.md.** That's for repo contributors,
  not end users.
- **Doesn't sync `.sync-map.json`.** It's committed for cache-prime,
  not as content.
- **Doesn't handle images yet.** Images referenced from markdown need
  to be uploaded to WP separately (or hosted on github.com/phreesoft/bizuno
  and linked by raw URL). Roadmap item.

### Troubleshooting

| Symptom                                       | First check                                |
|-----------------------------------------------|--------------------------------------------|
| `erusev/parsedown not installed`              | `composer install` (you may have a `--no-dev` build) |
| `WP credentials required`                     | Set `--user`/`--pass` or env vars          |
| `HTTP 401` on every request                   | App password wrong, or WP user lacks caps  |
| `HTTP 404` on `docs` endpoint                 | BetterDocs CPT slug differs; edit `CPT_SLUG` in the script |
| `HTTP 404` on `doc_category` endpoint         | BetterDocs taxonomy slug differs; edit `TAX_SLUG` |
| Created duplicates instead of updating        | The slug map went out of sync; run `--rebuild-cache` |
| Slow / hangs                                  | WAF / mod_security blocking REST traffic; whitelist the CI IP |
