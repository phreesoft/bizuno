# Bizuno Manual — Build Status

> Working note for whoever is drafting the manual. The repo is the source of
> truth across machines: each section `README.md` shows per-page status, and
> `git log` shows what's been published. This file is the quick "where we left
> off" summary. Not synced to BetterDocs.

**Last updated:** 2026-06-16

---

## Done & published live (bizuno.com/docs)

**Chapter 4 — Modules in Depth is COMPLETE** (all 6 sections written, merged to
`main`, and synced live):

- **01 PhreeBooks** — chart-of-accounts, journals (per-jID), register & reconcile,
  payroll, fiscal-year, and the `06-order-manager/` sub-section (entry fields, save
  menu, action column)
- **02 PhreeForm** — report engine, form designer, data binding, processors &
  formatters, custom forms
- **03 Inventory** — types, history & costing, assemblies, work orders
- **04 Contacts** — the table, customers, vendors, employees, projects & CRM
- **05 Quality** — CA/PA tickets, audits, training, maintenance, objectives
- **06 Shipping** — carriers, label printing, Zebra Browser Print

Also done earlier: most of **Ch.1 Getting Started**; **Ch.2** journal-id-taxonomy +
multi-store/period/currency; **Ch.3** quote→SO→invoice workflow; **Ch.5**
fiscal-year-close.

---

## Remaining stubs (the next work)

- **02-core-concepts:** ✅ now fully drafted (the last three — fiscal-periods-vs-calendar-dates, contacts-as-universal-entity, inventory-types-and-cogs — on branch `docs/core-concepts-stubs`, **not yet published**)
- **05-administration:** ✅ now fully drafted (roles-and-security, users-employees-contacts, backup-and-restore, override-hooks-and-myext, cache-mechanics — on branch `docs/administration`, **not yet published**; fiscal-year-close was already drafted)
- **03-daily-workflows:** po-receive-bill-pay, partial-backorder, returns-and-credit-memos, recurring-invoices-and-pos, multi-currency-and-gl-settlement
- **06-customization:** the-myext-pattern, custom-payment-gateway, custom-journal-type, custom-phreeform-processor
- **07-integration:** woocommerce-via-bizuno-api, edi-x12, rest-api-surface
- **08-migration-and-upgrade:** from-phreebooks-v5, from-quickbooks, release-notes/7-3-9
- **09-troubleshooting:** trap-and-trace-files, cache-out-of-sync, period-drift-and-recurs, pdf-rendering-issues
- **01-getting-started:** what-is-bizuno

To publish the two drafted chapters live: flip their pages' `status: draft → published`
(and the section READMEs), merge, then sync.

Suggested next chapters: **Daily Workflows** (after the first), then
**Customization** / **Integration** / **Migration** / **Troubleshooting**.

---

## How to resume (per section)

1. `git pull` first.
2. `git checkout -b docs/<section>` off `main`.
3. Read the stub's "What this page will cover" bullets, then **research the actual
   code** under `src/controllers/<module>/` — write from the implementation and
   correct any stub overstatements (this has happened in every section so far).
4. Match house voice — see `docs/CONTRIBUTING-DOCS.md` and any already-drafted page.
   Frontmatter `status: draft` while writing.
5. Verify cross-links, then `php bin/docs-sync.php --dry-run --only=<section>`.
6. Commit, push, open a PR.

### To publish a section live
Flip frontmatter `status: draft` → `published` (and the section README table),
merge the PR, then sync:
```bash
composer install            # first time on a machine (parsedown)
php bin/docs-sync.php --user='support@phreesoft.com' --pass='<wp-app-password>' --only=<section>
```

---

## Gotchas to remember

- **Sync publishes immediately** — `docs-sync.php` always sets the WP post status to
  `publish`; the `status:` frontmatter only gates *whether* a page syncs.
- **The WP app password** is held by the site owner (`support@phreesoft.com`) — the
  owner runs the sync.
- **`docs/.sync-map.json`** caches slug→WP-post-ID; commit it after a sync. If it's
  stale the sync falls back to slug lookup, so re-runs update rather than duplicate.
- **Assembly labor cost:** labor/charge BOM lines are deliberately **not**
  capitalized into an assembly's GL inventory value (labor has its own GL→COGS path;
  capitalizing would double-count). See `03-inventory/03-assemblies.md`.
- **The stubs say "7.3.9" — there is no 7.3.9 tag** (tags jump `7.3.8 → v7.4.0`).
  Verified actual releases for the features the stubs misattributed:
  `ensureFiscalYearCovers` + `fyClose` period self-heal → **v7.4.0**;
  parent-menu-from-child role access → **v7.4.0**; Clear Business Cache button →
  **v7.4.0**; `options_*` rebuild into `common_meta` → **7.3.8**. The drafted pages
  cite these correctly. Use the same when writing `08-migration/release-notes` and
  `09-troubleshooting/03-period-drift-and-recurs` (rename the `release-notes/7-3-9`
  stub — that release doesn't exist).
- **Backup tool is database-only.** The built-in Administrate → Backup runs
  `mysqldump` (`.sql.gz`) and does **not** capture the `BIZUNO_DATA` filesystem
  (images, PDFs, `myExt/`) — that's a manual tar. The drafted backup page says so;
  don't let later pages imply the tool backs up files.
- **Passkeys/WebAuthn are vendored but NOT wired up.** Only emailed-code 2FA is live.
  Don't document hardware passkeys as an available feature until the enrollment UI lands.
