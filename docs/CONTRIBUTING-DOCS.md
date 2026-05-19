# Contributing to the Bizuno Manual

The manual is authored in Markdown here in `bizuno/docs/`, version-controlled
alongside the code. Published pages are mirrored to BetterDocs at
[bizuno.com/docs](https://bizuno.com/docs) via the WP REST API.

## Why we work this way

- **Source-of-truth in git** — PRs, history, blame, code review for docs same
  as for code.
- **Docs ship with code** — a feature PR can update the docs in the same diff.
- **No vendor lock-in** — if BetterDocs ever falls behind, we render to MkDocs,
  Docusaurus, or static HTML without rewriting content.
- **Community contributions** — `Edit on GitHub` link at the bottom of each
  published page points back to the Markdown source.

## Per-page front matter

Every page starts with YAML front matter. Required fields:

```yaml
---
title: Anatomy of a Sales Transaction
category: Daily Workflows
order: 1
status: draft               # stub | draft | review | published
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---
```

Optional but encouraged:

```yaml
related:
  - 02-core-concepts/02-journal-id-taxonomy.md
  - 04-modules-in-depth/01-phreebooks/02-journals.md
since: 7.3.0                # earliest Bizuno version this is accurate for
deprecated-in: ~            # bump to a version if/when the page becomes wrong
```

## Voice

- Direct second-person. ("Open Settings → Inventory" — not "the user should…")
- Active verbs. ("Bizuno stamps the period from `post_date`" beats "the period
  gets stamped".)
- Don't decorate. No "in today's fast-paced business environment."
- Code blocks for anything literal — paths, SQL, settings keys, error strings.

## Conventions

- File names: kebab-case, numeric prefix for sort order (`01-what-is-bizuno.md`).
- Directory names: same convention.
- A directory README.md is the section landing page (overview + TOC).
- Cross-link with **relative paths** (`../02-core-concepts/...`) so links work
  in both raw GitHub and the rendered site.
- Screenshots: store under the page's directory in an `img/` subfolder; commit
  PNG/WebP, not JPEG for UI captures. Filename: `<page-slug>-<short-desc>.png`.

## Sync to BetterDocs

The sync script lives at [`bin/docs-sync.php`](../bin/docs-sync.php) and is
documented in detail at [`bin/README.md`](../bin/README.md). The short
version:

```bash
# Preview what would happen — no API calls
php bin/docs-sync.php --dry-run

# First time on a new machine: build the slug → post-ID cache
php bin/docs-sync.php --init-map --user=<user> --pass=<app-pass>

# Routine use (or in CI via env vars BIZUNO_DOCS_{USER,PASS,SITE})
php bin/docs-sync.php --user=<user> --pass=<app-pass>
```

Mapping:
- Each `docs/NN-section/` directory becomes a BetterDocs **category** (created
  automatically if missing)
- Each `.md` file becomes a BetterDocs **doc**
- Slug is derived from the file's basename (numeric prefix stripped), so a
  file moved between sections keeps the same WP post — no duplicates
- Front-matter `order` controls display order within the category
- Front-matter `status` gates publishing: by default only `published` files
  sync; `draft` and `stub` files stay in git only

Credentials are a WP **Application Password** (WP admin → Users → Profile).
Never commit them; use `BIZUNO_DOCS_PASS` in CI.

Section `README.md` files are *not* synced as docs — they're for GitHub
readers. BetterDocs shows its own auto-generated category archive page in
their place.

## When to add a new page vs. edit existing

Add a new page when:
- It's a distinct workflow or concept users will search for.
- Cross-linking from 2+ existing pages, length growing past ~600 words.

Edit existing when:
- You're correcting, clarifying, or adding a worked example.
- The information naturally belongs next to what's already there.

## Lifecycle

1. Open a PR — set `status: draft` in front matter.
2. SME review for technical accuracy → `status: review`.
3. Voice/edit pass → `status: published`, bump `last-updated`.
4. Merge to `main` → sync job pushes to bizuno.com/docs.

## When you're stuck

Search this repo for the relevant code. The docs are usually clearer when the
writer has read the implementation, not just the UI. The `controllers/<module>/`
trees are the canonical reference for what each module actually does.
