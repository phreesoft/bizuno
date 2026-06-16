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

All drafted chapters below are **merged to `main` but still `status: draft`** —
i.e. written and committed, but **not yet published** to bizuno.com/docs.

- **02-core-concepts:** ✅ fully drafted (merged to `main`)
- **03-daily-workflows:** ✅ fully drafted (merged to `main` — pages 02–06; 01 was already done)
- **05-administration:** ✅ fully drafted (merged to `main`)
- **06-customization:** ✅ fully drafted (merged to `main`)
- **09-troubleshooting:** ✅ fully drafted (branch `docs/troubleshooting` — trap-and-trace-files, cache-out-of-sync, period-drift-and-recurs, pdf-rendering-issues)
- **07-integration:** woocommerce-via-bizuno-api, edi-x12, rest-api-surface
- **08-migration-and-upgrade:** from-phreebooks-v5, from-quickbooks, release-notes/7-3-9
- **01-getting-started:** what-is-bizuno

To publish a drafted chapter live: flip its pages' `status: draft → published`
(and the section README), then sync.

Suggested next chapters: **Integration** or **Migration**
(Getting-Started's lone `what-is-bizuno` stub is a quick win too).

> **When drafting `08-migration/03-release-notes/`:** there is **no 7.3.9 release**
> — rename the `7-3-9` stub to **`7-4-0`**. The features it was meant to cover
> (period self-heal, parent-menu-from-child access, Clear Business Cache button,
> the tFPDF migration, `bizScrubSensitive`, EDI N9/MSG no-op) all shipped in
> **v7.4.0**. The `options_*` rebuild was 7.3.8; the PDF Image() x/y casts were
> 7.4.3/7.4.4.

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
  Verified real releases: period self-heal / `ensureFiscalYearCovers`, parent-menu-from-child
  role access, and the Clear Business Cache button → **v7.4.0**; the `options_*`
  rebuild into `common_meta` → **7.3.8**. Rename the `08-migration/release-notes/7-3-9`
  stub — that release doesn't exist.
- **Backup tool is database-only.** Built-in Administrate → Backup runs `mysqldump`
  (`.sql.gz`); it does NOT capture the `BIZUNO_DATA` filesystem (images/PDFs/`myExt/`) —
  that's a manual tar. (Drafted in `05-administration/03`.)
- **Passkeys/WebAuthn are vendored but NOT wired up** — only emailed-code 2FA is live.
- **Daily-workflow corrections found while drafting (all verified against the journal classes):**
  - PO is **jID=4**, not jID=7 (jID=7 is Vendor Credit). The old stub had this wrong.
  - **No GRNI / accrued-receipts step.** Bizuno receives stock *and* books AP in one
    entry — the Purchase (jID=6) — there is no separate receive journal and no
    goods-received-not-invoiced account. Three-way match is manual.
  - **No auto-PO-from-backorder.** There's a read-only reorder/stock-status dashboard
    and manual (or drop-ship) PO creation — nothing generates POs from short SO lines.
  - **Returns:** the RMA-tracking module is real (status/codes/preventable, receive→close),
    but the **Quality tie-in is NOT automatic** — return codes don't open CA/PA tickets.
  - **Recurring:** occurrences are inserted **eagerly** (all up front, linked by `recur_id`);
    edit is binary (this / all-future, no "all incl. past"); and **"delete all future" is
    effectively unreachable in the UI** — stopping a chain means deleting future rows one
    by one, so don't over-project. Frequencies are only weekly/bi-weekly/monthly/quarterly.
  - **Multi-currency:** Bizuno records foreign-currency txns and posts the GL in the home
    currency at a **manually-entered** rate (auto-feed is deprecated). It does **NOT** post
    realized FX gain/loss at payment, and has **no** period-end unrealized revaluation —
    both are manual General Journal entries. Reports are default-currency only. Don't let
    later pages imply automatic FX accounting.
- **Customization corrections found while drafting (verified against the code):**
  - **Payment gateways** have **no base class** and **no `sale()`/`void()`/`refund()`**
    methods — a gateway implements a single `payment($action, $data)` dispatcher (actions
    `capture`/`authorize`/`capAuth`/`refund`/`void`) plus optional `wallet()`/`report()`,
    all returning the normalized `['ok','txID','code','msg','data','raw']` array. Gateway
    credentials are stored as method settings metadata and are **not** `$mixer`-encrypted —
    don't claim encryption; steer to tokenization/wallet for PCI.
  - **Custom journal classes load from the CORE library path** (`getJournal()` resolves
    `jNN.php` under `BIZUNO_FS_LIBRARY`), so a custom journal does **not** drop into `myExt/`
    like other controllers — it's the exception to the myExt pattern. Real method contract is
    `Post/unPost/getDataItem/customizeView/getRepostData` (+ jCommon helpers), not the stub's
    `loadJournal`/`journalize`. `setJournalHistory()` does NOT balance for you — the item rows
    must net to zero. Use jID **40+** for custom.
  - **PhreeForm processors/formatters:** declared via `$phreeformProcessing` /
    `$phreeformFormatting` / `$phreeformSeparators` on a module's `…Admin`; `initPhreeForm()`
    merges them; handler signature is `($value, $key)` dispatching on key. New ones need a
    cache rebuild to appear in the designer.
