---
title: Fiscal-year Management
category: PhreeBooks
order: 5
status: draft
audience: [admin]
last-updated: 2026-06-07
---

# Fiscal-year Management

This page explains how Bizuno models fiscal years and periods, how to add years
and adjust period dates, and — at a high level — what closing a year does.

> **The close procedure has its own page.** Fiscal-year close is one of the two
> operations that can genuinely destroy your books (the other is restoring the
> wrong backup). The step-by-step checklist, pre-flight backup, and recovery
> guidance live in [Administration → Fiscal-year close](../../05-administration/04-fiscal-year-close.md).
> Read that before you close anything. This page is the model and the routine
> maintenance.

---

## The model: years and periods

Bizuno divides time into **fiscal periods** (normally 12 per year, month-aligned)
stored in the `journal_periods` table:

| Field         | Meaning                                       |
|---------------|-----------------------------------------------|
| `period`      | Sequential period number (1, 2, 3, …) — global, not reset per year |
| `fiscal_year` | The fiscal year this period belongs to        |
| `start_date`  | First day of the period                       |
| `end_date`    | Last day of the period                        |

Period numbers are **continuous** across years — they don't reset to 1 each
January. Period 13 is simply the first period of the second fiscal year. (This is
why [closing a year renumbers everything](#what-closing-does) — it collapses the
oldest year's periods off the front so numbering starts at 1 again.)

### How a transaction gets its period

Every posting carries a `journal_main.period`. Bizuno derives it from the
`post_date` by finding the period whose date range contains it
(`calculatePeriod()`):

```sql
SELECT period FROM journal_periods
 WHERE start_date <= post_date AND end_date >= post_date
```

**The `post_date` is authoritative; `period` is derived from it.** That principle
matters for the self-heal behavior below — if the two ever disagree, the date
wins.

---

## Adding a fiscal year

Use *PhreeBooks → Tools → Fiscal Year* to add the next year (`fyAdd`). It appends
a fresh block of period rows (start/end dates) to `journal_periods` and seeds
opening-balance rows in `journal_history` for every GL account so the new periods
are ready to post into.

### Automatic extension (7.3.9+)

You usually don't have to add years by hand. When a transaction's `post_date`
falls **beyond** the last defined period, `ensureFiscalYearCovers()` extends the
fiscal calendar forward automatically (up to a 10-year cap) so the post succeeds.
This most often fires from **recurring entries** that project into a year you
hadn't set up yet — Bizuno just builds the runway rather than failing the post.

---

## Editing period dates

You can adjust a period's `start_date` / `end_date` from the same Fiscal Year tool
(`fySave`) — for example to align Bizuno's months to a 4-4-5 calendar, or to fix a
period that was set up wrong.

**The guard:** a period's dates are **editable only if nothing has posted at or
beyond that period.** Once transactions exist in a period, its dates lock (the UI
shows them read-only) — moving them would re-bucket already-posted history.
Adjacent dates are kept contiguous, so editing one period's boundary nudges the
neighbor to avoid gaps.

---

## What closing does

Full detail is in the
[admin close page](../../05-administration/04-fiscal-year-close.md); the model-level
summary:

Closing a fiscal year **permanently deletes the closing year's transaction
detail** and rolls it into summary. Specifically it:

- **Rolls** income-statement accounts (types 30/32/34 and equity-that-closes 42)
  into **Retained Earnings** (type 44) — the year's profit becomes an opening
  equity balance.
- **Deletes** the `journal_main` / `journal_item` / `journal_history` /
  `journal_periods` rows dated within the closed year (the detail is gone; the
  aggregate survives in opening balances).
- **Renumbers** everything that remains — the surviving periods are shifted down
  so numbering restarts at 1, and `journal_item.reconciled` period stamps shift
  with them.
- **Preserves** master records: contacts, the chart of accounts, inventory items
  (their *history* is trimmed, the SKUs stay), and all current/future-dated
  transactions — including recurring entries dated after the close.

### The self-heal pass (7.3.9+)

Because the renumber step does a blind "subtract N from every period," any row
whose `period` had **drifted** from its `post_date` would be shifted further
wrong. To prevent that, close now runs a JOIN-based **re-stamp** after renumbering:

```sql
UPDATE journal_main jm
  JOIN journal_periods jp
    ON jm.post_date >= jp.start_date AND jm.post_date <= jp.end_date
  SET jm.period = jp.period
 WHERE jm.period <> jp.period
```

It re-derives every remaining row's period from its date — repairing historical
drift (e.g. quality tickets or work orders that were saved without a clean period
stamp, or future recurs inserted before their year existed). It's idempotent: a
no-op on clean data. Rows whose `post_date` falls outside any defined period are
left alone.

---

## Guidance

- **Don't close aggressively.** Closing is for trimming truly old years, not
  routine month-end. Once a year's detail is deleted you can only run summary
  reports against it — drill-down is gone. Many shops keep several open years.
- **Close is gated** behind the top admin permission (`admin` level 4) and refuses
  to touch the current or a future year.
- **Back up first, and test the backup.** Restoring the correct backup is the
  *only* real recovery from a bad close. See the
  [admin close checklist](../../05-administration/04-fiscal-year-close.md) and
  [Backup and restore](../../05-administration/03-backup-and-restore.md).

---

## Related

- [Fiscal-year close](../../05-administration/04-fiscal-year-close.md) — the operational checklist
- [Fiscal periods vs. calendar dates](../../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md)
- [Backup and restore](../../05-administration/03-backup-and-restore.md)
- [Chart of Accounts](./01-chart-of-accounts.md) — what closes into Retained Earnings
