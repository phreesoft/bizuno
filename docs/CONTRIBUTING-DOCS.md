# Contributing to the Bizuno Manual

The manual is authored in Markdown here in `bizuno/docs/`, version-controlled
alongside the code, and read on GitHub.

## Why we work this way

- **Source-of-truth in git** — PRs, history, blame, code review for docs same
  as for code.
- **Docs ship with code** — a feature PR can update the docs in the same diff.
- **No vendor lock-in** — the Markdown can be rendered to MkDocs, Docusaurus,
  or static HTML later without rewriting content.
- **Community contributions** — anyone can propose a change with a PR.

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
4. Merge to `main`; the page is live on GitHub.

## When you're stuck

Search this repo for the relevant code. The docs are usually clearer when the
writer has read the implementation, not just the UI. The `controllers/<module>/`
trees are the canonical reference for what each module actually does.
