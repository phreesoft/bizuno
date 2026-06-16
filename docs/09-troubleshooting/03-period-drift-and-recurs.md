---
title: Period Drift and Recurring Entries
category: Troubleshooting
order: 3
status: draft
audience: [admin]
last-updated: 2026-06-16
---

# Period Drift and Recurring Entries

The classic report: *"the grid only shows a handful of records when I know
there are hundreds."* Almost always this is one of two period problems — a
stored period that drifted away from the transaction's date, or a grid quietly
filtering to the current period. This page is the canonical diagnosis and fix.

The concept behind it — that every transaction carries both a `post_date` and a
resolved `period` — is in
[Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md).

---

## The two failure shapes

### Shape 1 — `journal_main.period` doesn't match `post_date`

A row's stored `period` disagrees with the period its `post_date` falls in. Since
period-based reports group by `period`, a drifted row lands in the wrong bucket
(or, when `period = 0`, in *no* bucket — invisible).

Where it comes from:

- **Quality tickets (jID=30)** and **work orders (jID=32)** — these save through a
  generic manager path that doesn't run the period-stamping the PhreeBooks
  journals do, so their `period` could be left 0/unset.
- **Future-dated recurring entries created before v7.4.0** — when a recurrence
  projected into a fiscal year that didn't exist yet, `calculatePeriod()` had
  nothing to resolve to and the row was stamped `period = 0`. (Fixed going
  forward — see below.)

### Shape 2 — the grid is silently filtering to the current period

Here the data is *fine*; the **grid** is narrowing. If a manager doesn't set a
period default, the date-filter logic falls through to "current fiscal period,"
emitting `WHERE period = <current>` even while the on-screen dropdown says
**All**. You see a handful of rows and assume the rest are gone.

This bit the **quality tickets manager** specifically — CA/PA tickets are worked
across many periods, so defaulting to the current one hid most of them. It now
defaults to all-periods. *(Fixed in v7.4.0.)* If you meet this on an older build,
the tell is that switching the period dropdown to a specific old period suddenly
reveals the "missing" rows.

---

## Diagnosing drift

To see whether stored periods disagree with the dates, join `journal_main`
against the fiscal calendar and look for mismatches:

```sql
SELECT jm.id, jm.journal_id, jm.post_date, jm.period AS stored_period, jp.period AS should_be
  FROM journal_main jm
  JOIN journal_periods jp
    ON jm.post_date >= jp.start_date AND jm.post_date <= jp.end_date
 WHERE jm.period <> jp.period;
```

Any rows returned are drifted. (Rows with `period = 0` whose `post_date` is inside
a defined period show up here too — `0 <> jp.period`.) Add
`AND jm.journal_id IN (30,32)` to confirm the quality-ticket / work-order
suspicion.

---

## The repair

The fix is the same JOIN as an `UPDATE` — re-stamp each row's `period` from the
period its `post_date` implies:

```sql
UPDATE journal_main jm
  JOIN journal_periods jp
    ON jm.post_date >= jp.start_date AND jm.post_date <= jp.end_date
   SET jm.period = jp.period
 WHERE jm.period <> jp.period;
```

It's **idempotent** — a no-op on clean data, safe to run again. Rows whose
`post_date` falls outside every defined period (rare) are left alone, since
there's no period to map them to; if you have those, the fix is to make sure the
fiscal calendar covers them first.

> **Back up before running repair SQL** against a production database — see
> [Backup and restore](../05-administration/03-backup-and-restore.md).

---

## You may not need to run it by hand

Bizuno repairs and prevents this on its own as of **v7.4.0**:

- **The upgrade runs the repair automatically.** The v7.4.0 upgrade gate executes
  the same idempotent re-stamp against existing data, so a site that upgrades is
  cleaned up without manual SQL. (Internally the gate is keyed off a `< 7.3.9`
  *database-version* check — a defensive threshold; the code itself shipped in
  v7.4.0. There is no 7.3.9 release.)
- **Fiscal-year close self-heals.** [Closing a fiscal year](../05-administration/04-fiscal-year-close.md)
  re-stamps any drifted rows as part of the close, so drift no longer
  accumulates over time.
- **New recurs can't reintroduce `period = 0`.** `calculatePeriod()` now calls
  `ensureFiscalYearCovers()`, which auto-extends the fiscal calendar forward when
  a future `post_date` would otherwise have no period — so a recurrence projected
  years out resolves to a real period instead of 0. See
  [Recurring invoices and POs](../03-daily-workflows/05-recurring-invoices-and-pos.md).

So on a current build the manual repair is a rare belt-and-suspenders step; on an
older one, run it (or upgrade) and the symptom clears.

---

## Related

- [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md) — the date/period model
- [Recurring invoices and POs](../03-daily-workflows/05-recurring-invoices-and-pos.md) — where future-dated `period = 0` used to come from
- [Fiscal-year close](../05-administration/04-fiscal-year-close.md) — where the self-heal runs
- [Backup and restore](../05-administration/03-backup-and-restore.md) — before any repair SQL
