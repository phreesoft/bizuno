---
title: Fiscal Periods vs. Calendar Dates
category: Core Concepts
order: 3
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Fiscal Periods vs. Calendar Dates

> **Status:** Stub — not yet drafted.

## What this page will cover

- The two timekeeping systems Bizuno uses side-by-side
- `post_date` = calendar date; `period` = fiscal period number (resolved from `post_date` against `journal_periods`)
- Why both exist — reports group by period, audit groups by date, security can lock prior periods without locking dates
- "Current period" — what the cache holds, when it updates, what `periodAutoUpdate()` does
- The auto-extend behavior added in 7.3.9 (`ensureFiscalYearCovers`)
- How recurring entries projecting into uncreated fiscal years used to break (period=0) and how 7.3.9 fixed it
- How `fyClose` self-heals `journal_main.period` against `journal_periods` to prevent drift (added in 7.3.9)
- What a "locked period" means and how to undo it carefully

## Why it matters

Every confused "the date is right but the report shows last month" support
ticket comes from misunderstanding this distinction.

## Related

- [Multi-store, multi-period, multi-currency](./01-multi-store-multi-period-multi-currency.md)
- [Fiscal-year close](../05-administration/04-fiscal-year-close.md)
- [Period drift and recurring entries](../09-troubleshooting/03-period-drift-and-recurs.md)
