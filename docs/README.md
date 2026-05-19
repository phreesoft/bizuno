# Bizuno User Manual — Source

This is the Markdown source for the Bizuno user manual that ships at
[bizuno.com/docs](https://bizuno.com/docs) (rendered via BetterDocs in WordPress).
The source-of-truth is here in `bizuno/docs/`; the WP site is the output.

Contributors edit Markdown here, open PRs against the `bizuno` repo, and a sync
step pushes published pages to BetterDocs via the WP REST API. See
[CONTRIBUTING-DOCS.md](./CONTRIBUTING-DOCS.md) for the workflow.

---

## Audience guide

Most pages are tagged with the audience(s) they serve. When you're reading or
writing, keep the lens in mind:

| Tag           | Reader            | What they need                                                |
|---------------|-------------------|---------------------------------------------------------------|
| `bookkeeper`  | Daily operator    | Workflows. "How do I record X?" Step-by-step with screenshots.|
| `admin`       | Self-host / IT    | Concepts + operations. Install, configure, customize, recover.|
| `developer`   | Extension author  | Architecture. Hooks, override points, API surface, internals. |

A page can carry one, two, or all three tags. Pages that try to serve all three
without distinction usually serve none — split when in doubt.

---

## Status legend

| Status      | Meaning                                                       |
|-------------|---------------------------------------------------------------|
| `stub`      | Heading + intent bullets only. Not yet written.               |
| `draft`     | First pass written. Needs review for accuracy + voice.        |
| `review`    | Awaiting a second pair of eyes / SME validation.              |
| `published` | Live at bizuno.com/docs. Edit cautiously; users land here.    |

---

## Table of contents

### 1. [Getting Started](./01-getting-started/)
Three install paths, first-hour walkthrough, what Bizuno is and isn't.

### 2. [Core Concepts](./02-core-concepts/)
The must-read chapter. Multi-store, multi-period, multi-currency. Journal IDs.
Fiscal vs. calendar. Contacts as the universal entity. Inventory types + COGS.

### 3. [Daily Workflows](./03-daily-workflows/)
End-to-end transaction stories: quote→SO→invoice→payment, PO→receive→pay,
backorders, returns, recurring, multi-currency settlement.

### 4. [Modules in Depth](./04-modules-in-depth/)
Reference per module: PhreeBooks, PhreeForm, Inventory, Contacts, Quality, Shipping.

### 5. [Administration](./05-administration/)
Roles & security, users vs. employees vs. contacts, backup, FY close, cache, hooks.

### 6. [Customization & Extensions](./06-customization/)
The `myExt/` pattern. Custom payment gateway, custom journal, custom PhreeForm processor.

### 7. [Integration](./07-integration/)
WooCommerce via bizuno-api, EDI X12, REST surface.

### 8. [Migration & Upgrade](./08-migration-and-upgrade/)
From PhreeBooks v5, from QuickBooks, per-release upgrade notes.

### 9. [Troubleshooting](./09-troubleshooting/)
Reading trap/trace files, cache out of sync, period drift, PDF rendering.

---

## Design principles

1. **Workflow before feature.** Tell users how to *do something they actually
   want to do*, not what every field on a form means. "Anatomy of a sales
   transaction" beats "Reference of journal_main fields."
2. **Name the nuance.** Bizuno is more capable than a typical small-business
   bookkeeping app — multi-store, multi-period, multi-currency, plug-in
   journals, override hooks. Pages should *say so*, not let users discover it
   the hard way.
3. **One canonical place per concept.** Cross-link, don't duplicate.
4. **Conservative on screenshots.** They go stale faster than the prose. Use
   them where text fails (visual UI state). Don't decorate.
5. **Code blocks in monospace, runnable when possible.** SQL, shell, PHP.
6. **Date-stamp every published page.** Front-matter `last-updated` so users
   know how stale a page is when they land on it from Google.
