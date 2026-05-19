---
title: Period Drift and Recurring Entries
category: Troubleshooting
order: 3
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Period Drift and Recurring Entries

> **Status:** Stub — not yet drafted.

## What this page will cover

- Diagnosing "the report grid only shows a handful of records when I know there's more"
- The two failure shapes:
  1. **`journal_main.period` doesn't match `post_date`** — common on quality tickets (jID=30), work orders (jID=32), and future-dated recurs from before 7.3.9
  2. **The grid is silently filtering to the current period** — defaults misconfigured (fixed in tickets manager 7.3.9)
- The diagnostic JOIN that flags drifted rows
- The repair SQL (one-shot, idempotent)
- How 7.3.9's upgrade gate runs the repair automatically
- How `fyClose` now self-heals on close (no more accumulation)
- How `calculatePeriod` auto-extends fiscal years for future post_dates (`ensureFiscalYearCovers`) so new recurs don't reintroduce drift

## Why it matters

This was years of slow data drift across thousands of installs. Doc captures
the canonical diagnosis + fix so anyone reproducing the symptom has the
right SQL on day one.

## Related

- [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md)
- [Recurring invoices and POs](../03-daily-workflows/05-recurring-invoices-and-pos.md)
- [Release 7.3.9](../08-migration-and-upgrade/03-release-notes/7-3-9.md)
