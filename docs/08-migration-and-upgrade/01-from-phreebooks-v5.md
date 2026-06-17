---
title: From PhreeBooks v5
category: Migration & Upgrade
order: 1
status: published
audience: [admin]
last-updated: 2026-06-16
---

# From PhreeBooks v5

Bizuno is the descendant of PhreeBooks, and it ships a built-in, in-place
migration that takes a PhreeBooks 5 (6.x) database all the way to the Bizuno 7
schema. The jump is large — the data model was substantially reshaped — but the
migration is automated and preserves your financial history. This page is what to
expect before, during, and after.

> **Back up first — completely.** This migration *drops legacy tables* as it goes.
> Take a full database **and** `BIZUNO_DATA` backup before you start, and verify
> you can restore it. See [Backup and restore](../05-administration/03-backup-and-restore.md).
> Restore-from-backup is the recovery path if anything goes wrong (below).

---

## What changed, structurally

PhreeBooks 5 → Bizuno 7 is a schema and architecture change, not a coat of paint:

- **Namespace & layout** — everything moved under the `bizuno` namespace and an
  MVC module layout (`controllers/<module>/…`), loaded via Composer.
- **The `*_meta` pattern** — four flexible metadata tables (`contacts_meta`,
  `inventory_meta`, `journal_meta`, `common_meta`) replace a sprawl of
  single-purpose legacy tables; much configuration now lives as JSON in the module
  cache / `common_meta`.
- **Contacts unified** — the separate `users` table folds into `contacts`, and the
  single `type` character becomes independent `ctype_*` role flags (a contact can
  be several things at once). See
  [Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md).

The database carries a version stamp; migration is gated on it being `< 7.0` and
sets it to `7.0` on completion.

---

## How the migration runs

When you point a Bizuno install at an old PhreeBooks database, the maintenance
screen detects the legacy `address_book` table and offers **Migrate**. The
process (`install/migrate-7.0.php`) then runs **33 ordered steps** (0–32),
beginning with a pre-7.0 upgrade to the latest 6.x point release, then working
through:

- **Schema** — add the `ctype_*` columns, create the four `*_meta` tables.
- **Identity** — `roles` → `common_meta`; `users` → `contacts` (as `ctype_u`) with
  auth/profile in `contacts_meta`.
- **Addresses & CRM** — `address_book` denormalized onto the contact row (extras
  into `contacts_meta`); CRM contacts → `ctype_i`; projects → metadata.
- **Reference data** — chart of accounts, sales-tax rates, current-status counters,
  inventory price sheets, assemblies/BOMs.
- **Extensions** — shipping, documents, returns, fixed assets, maintenance,
  training, production (srvBuilder), quality (ISO9001), CRM promos, and EDI logs
  each fold into the appropriate `*_meta` table or `journal_main`.
- **Dashboards & PhreeForm** → `common_meta`, with user/role IDs remapped.
- **Cleanup** — drop ~24 legacy tables, rename the old `type` column to `xtype`,
  and finalize the ID maps.

It runs **step-by-step over AJAX** with a progress display, so a large database
migrates in resumable blocks rather than one long request.

---

## What's preserved, reshaped, and discarded

| | |
|---|---|
| **Preserved** | The financial core — `journal_main` / `journal_item` (every transaction), the chart of accounts, and your contacts. Your books come across intact. |
| **Reshaped** | `users` → contacts (`ctype_u`); `address_book` → contact row + `contacts_meta`; the `type` char → `ctype_*` flags; CRM → `ctype_i`; roles/dashboards/PhreeForm/reports → `common_meta`; extension tables → `*_meta` / `journal_main`. |
| **Discarded** | ~24 legacy tables after their data is migrated (incl. `address_book`, the `users`/`roles` tables, the `ext*` extension tables, EDI log); `crmPromos_history` (logged, not carried); the old `type` column (renamed `xtype`, unused). |

---

## Pre-migration checklist

1. **Full backup** of the database and `BIZUNO_DATA`, restore-tested.
2. **Off-peak window** — users should be out; the migration logs sessions out at
   the end anyway.
3. **Composer installed** — the Bizuno 7 dependency stack must be in place
   (`composer install`) so the code the migration calls is present.
4. **Chart of accounts must be readable** — the migration bails early if the COA
   isn't available, so a healthy source database matters.
5. Expect runtime to scale with transaction volume; because it's blocked over
   AJAX, a big database is slow but won't time out in a single request.

---

## After it finishes

On completion the migration sets the version to `7.0`, **clears the business
cache**, drops `address_book` (the "migration done" signal), and **forces a
logout**. When you log back in:

- Confirm the [cache rebuilt](../05-administration/06-cache-mechanics.md) cleanly
  (no raw dropdown keys, menus present).
- Spot-check the financials: a **trial balance / balance sheet** as of a known
  date should match the old system. Open a few historical transactions and
  confirm contacts, line items, and GL postings look right.
- Verify users can log in and roles grant the expected access.

---

## Recovering from a failed migration

Understand the safety model before you start:

- **Steps are transactional individually, not end-to-end.** Each of the 33 steps
  commits on its own — there's no single transaction wrapping the whole run.
- **A mid-step failure is not safely re-runnable.** Because each step deletes from
  the legacy table once it has written the new form, restarting after a partial
  step can skip already-moved data. The version guard prevents an accidental
  full re-run, but it can't un-split a half-finished step.

So the recovery path is unambiguous: **restore the pre-migration backup,
investigate the cause, and run it again from a clean copy.** This is exactly why
the backup is step one and why it must be restore-tested. Don't try to hand-patch
a half-migrated database.

---

## Related

- [Backup and restore](../05-administration/03-backup-and-restore.md) — the prerequisite, and the recovery path
- [Cache mechanics](../05-administration/06-cache-mechanics.md) — the post-migration rebuild
- [Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md) — the `users`/`type` → `ctype_*` reshape
- [Release notes](./03-release-notes/) — what changed in later Bizuno releases
