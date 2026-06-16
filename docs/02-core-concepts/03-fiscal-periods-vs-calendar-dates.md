---
title: Fiscal Periods vs. Calendar Dates
category: Core Concepts
order: 3
status: draft
audience: [bookkeeper, admin]
last-updated: 2026-06-16
---

# Fiscal Periods vs. Calendar Dates

Bizuno keeps two clocks on every transaction, and they are **not** the same
thing:

- **`post_date`** — the calendar date the transaction happened (a real date like
  `2026-06-16`).
- **`period`** — an integer fiscal-period number (1, 2, 3, …) that the date
  *resolves into*.

Almost every "the date is right but the report shows the wrong month" support
ticket comes from not knowing these are separate. This page explains why both
exist, how one becomes the other, and where the seams are.

---

## Why two clocks

A calendar date answers *when*. A fiscal period answers *which accounting
bucket*. Most of the time they line up — June 2026 is "period 6" of a calendar
fiscal year — but they're stored and used independently because each is the
right tool for a different job:

- **Reports group by period.** A Profit & Loss for "period 6" sums every row
  whose `period = 6`, regardless of the exact day.
- **Audit and registers group by date.** When you ask "what posted on June 16,"
  Bizuno filters on `post_date`.
- **A fiscal year doesn't have to start in January.** If your year starts in
  July, calendar June 2026 is period 12, not period 6. The period number, not
  the month, is what the books care about.

The two are reconciled through one table.

---

## The `journal_periods` table

`journal_periods` is the calendar-to-period map. Each row is one fiscal period:

| Column        | Holds                                          |
|---------------|------------------------------------------------|
| `period`      | The period number (primary key) — 1, 2, 3, …   |
| `fiscal_year` | The fiscal year this period belongs to         |
| `start_date`  | First calendar date in the period (inclusive)  |
| `end_date`    | Last calendar date in the period (inclusive)   |

A period is just a labeled date range. Resolving a `post_date` to a `period` is
literally "find the row whose `start_date … end_date` contains this date":

```sql
SELECT period FROM journal_periods
 WHERE start_date <= '2026-06-16' AND end_date >= '2026-06-16'
```

That lookup is wrapped by **`calculatePeriod($post_date)`**
(`model/functions.php`). It checks the cached current period first (below), then
falls back to the table query. Every journal stamps the result into
`journal_main.period` at save time, so a transaction carries *both* its calendar
date and its resolved period number forever after.

> **What the table does not have.** There are no `locked` or `closed` columns. A
> period is never "locked" by a flag — see [Locked periods](#what-a-locked-period-really-means)
> for what locking actually means.

---

## "Current period" and the cache

Bizuno caches the active period rather than recomputing it on every screen. The
cache lives in the PhreeBooks module cache (`getModuleCache('phreebooks', 'fy')`)
and holds `period`, `period_start`, `period_end`, `fiscal_year`, and the
min/max period bounds. New transactions default their `post_date` and `period`
from it.

On each page load, **`periodAutoUpdate()`** checks today's date against the
cached `period_end`. While today still falls inside the cached period, nothing
happens. The day you cross into the next period, it recomputes and rewrites the
cache — which is why the default date on a new entry "rolls over" on its own at a
period boundary without anyone touching settings.

> **Implication:** the current period follows the calendar automatically. You do
> *not* manually advance the period each month. If the default date on a new
> transaction looks wrong, the fix is almost always the fiscal calendar
> (`journal_periods`), not a setting you toggle.

---

## When the calendar runs out: auto-extend

Recurring transactions can project years into the future — a monthly recurring
invoice set to repeat 60 times reaches five years out. If `journal_periods`
doesn't yet have a row covering that future date, the old behavior was that
`calculatePeriod()` found no match and the row was saved with **`period = 0`** —
a transaction with a valid date but no fiscal bucket, invisible to
period-based reports.

`calculatePeriod()` now guards against this by calling
**`ensureFiscalYearCovers($post_date)`** *(since 7.4.0)*. When a date falls past
the last defined fiscal year, this extends the fiscal calendar forward (adding
whole fiscal years, capped at 10 by default) so the date always resolves to a
real period. The recurring-entry projector in `controllers/phreebooks/main.php`
relies on it, so future-dated recurs now get a correct period instead of 0.

---

## How fiscal-year close keeps period numbers honest

Period numbers can drift out of sync with `post_date` — a transaction saved with
`period = 0` before the auto-extend fix, an extension transaction (a quality
ticket or work order) that wrote its row directly, or an old off-by-one. The
**fiscal-year close** process self-heals this *(since 7.4.0)*. As part of close,
it re-stamps every `journal_main` row whose stored period disagrees with the
period its `post_date` implies:

```sql
UPDATE journal_main jm
  JOIN journal_periods jp
    ON jm.post_date >= jp.start_date AND jm.post_date <= jp.end_date
   SET jm.period = jp.period
 WHERE jm.period <> jp.period
```

It's idempotent — a no-op on clean data — so running a close never *creates*
drift, it only corrects it. Rows whose `post_date` falls outside every defined
period (rare) are left alone, since there's no period to map them to. Full
walkthrough in [Fiscal-year close](../05-administration/04-fiscal-year-close.md).

---

## What a "locked period" really means

Because `journal_periods` has no lock column, you don't lock a period with a
checkbox. Locking is implicit: **once a period has any posted transaction, you
can no longer edit that period's start/end dates.** The fiscal-period admin
screen shows the date fields as read-only for any period at or below the
highest one that has postings; only periods with nothing posted above them stay
editable.

The practical consequence: you can reshape *future* periods freely, but you
cannot retroactively change the date boundaries of a period you've already
posted into — that would silently re-bucket history. If you genuinely need to
correct an early period's dates, you're rebuilding, not editing. Treat the
boundaries as settled the moment the first transaction lands in them.

---

## Why this matters

The date/period split is the difference between *when something happened* and
*which books it lands in*. Keep them straight and Bizuno's reporting behaves
exactly as expected. Forget the distinction and you get the classic confused
ticket: a transaction dated correctly that shows up in the "wrong" month — which
is really just its `post_date` and its `period` telling you two true things at
once.

---

## Related

- [Multi-store, multi-period, multi-currency](./01-multi-store-multi-period-multi-currency.md)
- [The journal_id taxonomy](./02-journal-id-taxonomy.md) — `journal_main` carries both date and period
- [Fiscal-year close](../05-administration/04-fiscal-year-close.md) — where the period self-heal runs
- [Period drift and recurring entries](../09-troubleshooting/03-period-drift-and-recurs.md) — diagnosing `period = 0`
