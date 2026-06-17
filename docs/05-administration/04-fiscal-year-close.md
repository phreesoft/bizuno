---
title: Fiscal-Year Close
category: Administration
order: 4
status: published
audience: [admin, bookkeeper]
last-updated: 2026-05-19
---

# Fiscal-Year Close

**This is the most-destructive routine operation in Bizuno.** Closing a
fiscal year permanently deletes every transactional row in that year from
`journal_main` and `journal_item`, renumbers all subsequent periods, and
collapses the year's per-account activity into roll-forward summary rows
in `journal_history`. There is **no undo**. The only recovery from a
botched close is restoring from backup.

Read this page top-to-bottom before you start. The order of the steps
isn't ceremonial — each one protects against a specific failure mode.

---

## When to close vs. when to leave a year open

You **don't have to close** a fiscal year on December 31st (or whenever
your FY ends). Many businesses leave the previous year open for 12+
months past its end so adjustments, late vendor invoices, and audit
queries can still be filed against it. Bizuno tolerates as many open
fiscal years as you like; reporting works fine across them.

**Close when:**

- You're sure no more transactions need to be posted into that year
- Audit/tax filing for that year is complete and final
- The `journal_main` row count for that year is large enough that
  reports are getting slow
- You want to permanently revoke staff ability to edit prior-year data
  (close locks it harder than period-locking does)

**Don't close when:**

- You haven't reconciled every bank account in that year
- You're "almost done" with audit — wait until the auditor signs off
- The year hasn't physically been audited or tax-filed yet
- You aren't 100% sure you've taken a verified backup

A reasonable cadence is closing the year **one year after it ended** —
so you close FY 2024 in early 2026, after the FY 2025 books are settling
and you're sure 2024 is final.

---

## Pre-flight checklist

Run every item before you click *Close Fiscal Year*. Skipping any of
them is gambling.

### ☐ 1. Take a full backup

**Database AND filesystem.** The filesystem backup is `BIZUNO_DATA`
(uploaded attachments, configuration, custom forms, log files). The
database is everything else. See
[Backup and restore](./03-backup-and-restore.md).

```sh
# Database
mysqldump -u <user> -p <bizuno_db> | gzip > bizuno-pre-close-$(date +%Y%m%d).sql.gz

# Filesystem
tar czf bizuno-data-pre-close-$(date +%Y%m%d).tar.gz /path/to/BIZUNO_DATA
```

Copy both to off-host storage. Don't leave them on the same disk.

### ☐ 2. Verify the backup

A backup you haven't tested is a wish. Spin up a sandbox, restore both
files there, log in. If the sandbox works, your real backup is real.

### ☐ 3. Reconcile every bank, credit card, and merchant account

Run the reconcile through to the last day of the closing FY. Reconciles
that drag across the close boundary are painful to fix afterward.

### ☐ 4. Post all late transactions

Sweep your in-box for vendor bills, customer payments, mileage logs, and
anything else you've been sitting on. The day after close, posting
anything to the closed year requires reopening (which means restoring
from backup).

### ☐ 5. Run reconciling reports

- Trial Balance as of FY-end — does it balance?
- Balance Sheet as of FY-end — assets = liabilities + equity?
- AR Aging and AP Aging — do they match the GL AR/AP control accounts?
- Inventory Valuation — does it match the GL Inventory account?

Save copies of these reports outside Bizuno. They're your record of what
the year looked like immediately before close.

### ☐ 6. Tell your team

The close runs for **anywhere from a few minutes to an hour** depending
on how much data is in the year. Bizuno locks the database for parts of
that runtime. Pick an off-peak window (evening, weekend) and let staff
know not to log in until you signal back.

### ☐ 7. Confirm the right year

The Bizuno UI defaults to closing the **earliest open year**. If you
have multiple open years, double-check the dialog before clicking. There
is no "are you sure?" recoverable click — once cron starts, it runs to
completion.

---

## What `fyClose` actually does

Internally, `fyClose` is a multi-step cron job. Each step is idempotent
on its own, but the overall sequence is destructive. As of release 7.3.9
the steps are:

| Step | Function                          | Effect                                                                 |
|------|-----------------------------------|------------------------------------------------------------------------|
| 0    | `fyCloseHistorySales`             | Roll each customer's sales history into the contact's `journal_history` summary |
| 1    | `fyCloseHistoryPurch`             | Same, vendor-side                                                      |
| 2    | `fyCloseTableGenJournal`          | **DELETE** all rows in `journal_main` / `journal_item` with `post_date <= fyEndDate` |
|      |                                   | **DELETE** matching rows in `journal_history` and `journal_periods`    |
| 3    | `fyCloseReindexJrnlMain`          | Decrement `period` in remaining `journal_main` rows by `periodEnd`     |
| 4    | `fyCloseReindexJrnlItem`          | Decrement `reconciled` in remaining `journal_item` rows by `periodEnd` |
| 5    | `fyCloseReindexGenJournal`        | Decrement `period` in remaining `journal_history` and `journal_periods` rows; then **self-heal** by re-JOINing `journal_main.period` against the now-renumbered `journal_periods` (added 7.3.9) |
| 6    | `fyCloseCleanAudit`               | Remove audit-log rows older than the closing FY                        |
| 7    | `fyCloseCleanChart`               | Remove orphaned chart-history rows                                     |
| 8    | `fyCloseCleanContact`             | Remove orphaned contact-meta rows                                      |
| 9    | `fyCloseCleanInventory`           | Trim inventory metadata to current FY range                            |
| 10   | `fyCloseCleanInvUsage`            | Trim inventory usage records                                           |
| 11   | `fyCloseCleanInvHist`             | Trim inventory history records                                         |

The percentages reported in the cron progress monitor are approximate —
they're based on row counts pre-flighted in the count-only pass. Don't
worry if it jumps from 73% to 100% suddenly; that's how the row-weight
math shakes out.

---

## What survives close

| Survives                                          | Notes                                              |
|---------------------------------------------------|----------------------------------------------------|
| Customer / vendor / employee master records       | All contacts persist; only their year's transactions go |
| Inventory item master                             | The SKU stays; its usage history for the closed year is trimmed |
| Chart of accounts                                 | Untouched                                          |
| Current/future fiscal-year transactions           | Untouched                                          |
| Future-dated recurring entries                    | Survive the delete (post_date is after fyEndDate)  |
| Fixed assets master + depreciation schedule       | Untouched                                          |
| Per-account history in `journal_history`          | Summary rows aggregated by period — survive as the new "opening balance" data |
| Custom forms, reports, PhreeForm templates        | Untouched (not transactional)                      |

## What does NOT survive close

| Lost                                                | What to do instead                                 |
|-----------------------------------------------------|----------------------------------------------------|
| Individual `journal_main` rows for the closed year  | Saved reports + backup                             |
| Individual `journal_item` lines for the closed year | Same                                               |
| Audit-log rows from the closed year                 | Export the audit log to CSV before closing if you need it |
| Reconcile detail rows for the closed year           | Save the reconcile reports first                   |
| Per-day register detail for the closed year        | Save the register CSV first                        |

The aggregate-by-period activity persists in `journal_history` and is
enough to drive trend reports across multiple years. The per-transaction
detail is gone unless you saved it.

---

## During the run — what to do (and not do)

**Do:**

- Watch the cron progress monitor
- Leave the browser tab open (closing it doesn't stop the cron, but you
  lose the progress feedback)
- Note any "trap set" lines in the trace file — they don't necessarily
  fail the close but are worth reading after

**Don't:**

- Don't navigate away to other Bizuno screens during the run
- Don't let other users log in
- Don't run database admin queries against the same DB
- Don't shut down the web server (the cron is HTTP-driven)
- Don't *touch the keyboard* if you can help it; this is a "watch the
  pot" operation

---

## Post-close validation

After the success screen:

1. **Clear the business cache** (*Settings → Bizuno → Tools → Clear
   Business Cache*) — the registry needs to rebuild against the new
   period numbering
2. **Run the same Trial Balance + Balance Sheet** as in pre-flight, but
   as of the **first day of the now-current FY**. The opening balances
   should match the closing balances from the pre-flight reports.
3. **Spot-check a register** — open any bank account's register and
   confirm the opening balance ties to what it should be.
4. **Verify a future-dated recur survived** — open the SO list, filter
   to "future". The recurring entries projected past fyEndDate should
   still be there with sensible period numbers.
5. **Look at the trace file.** Any traps fired during the close are
   logged there.

If anything looks wrong, **stop using Bizuno** and restore the backup
before posting new transactions. Mixed pre-close / post-close data is
nearly impossible to disentangle after the fact.

---

## Recovery

**There is one and only one recovery path: restore from backup.**

Do not attempt to "undo" a close by manually inserting rows into
`journal_main` or `journal_periods`. The close renumbers periods, rolls
history, and trims metadata in ways that are difficult to reverse-engineer
even with the trace file. Restoring the pre-close backup gets you back to
a known-good state in one step.

Steps:

1. Take a backup of the *current* (post-close, busted) state first — you
   may want it for forensics later
2. Stop the web server (or at least take Bizuno offline so no one posts
   to the broken state)
3. Drop and re-create the database
4. Import the pre-close `.sql.gz`
5. Restore the `BIZUNO_DATA` directory from `bizuno-data-pre-close.tar.gz`
6. Clear the business cache (delete `bizuno_cache_expires` row or set it
   to 0 in `common_meta`)
7. Log in, run the same pre-flight reports, verify they match what you
   saved before close

If you've been posting new transactions in the bad state, those are
lost. Hence: **take the backup. Verify the backup. Trust the backup.**

---

## Related

- [Fiscal-year management](../04-modules-in-depth/01-phreebooks/05-fiscal-year-management.md) — adding and editing fiscal years
- [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md) — what period numbers mean
- [Backup and restore](./03-backup-and-restore.md) — the prerequisite
- [Cache mechanics](./06-cache-mechanics.md) — why you clear it after
- [Release 7.3.9](../08-migration-and-upgrade/03-release-notes/7-3-9.md) — the self-heal pass added to close
