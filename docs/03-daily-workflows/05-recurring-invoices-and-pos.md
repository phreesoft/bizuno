---
title: Recurring Invoices and POs
category: Daily Workflows
order: 5
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Recurring Invoices and POs

> **Status:** Stub — not yet drafted.

## What this page will cover

- Setting up a recurring transaction: frequency (daily/weekly/monthly/quarterly), terminal date, recur_id linking
- How Bizuno projects future occurrences — they're inserted ahead of time, not generated lazily
- How `calculatePeriod()` stamps period on each future occurrence (post-7.3.9: auto-extends the fiscal calendar forward if needed)
- Editing a recurring chain: change one, change all-future, change just this
- Stopping a recurring chain
- The known pre-7.3.9 bug (recurs into uncreated fiscal years stamped `period=0`) and how the 7.3.9 upgrade self-heals existing data

## Why it matters

Recurring entries are the feature accountants love and most cheap accounting
apps half-implement. Bizuno's projection model is unusually thorough — but
the auto-extend semantics added in 7.3.9 deserve documentation so users
understand why a five-year-out recur "just works."

## Related

- [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md)
- [Period drift and recurring entries](../09-troubleshooting/03-period-drift-and-recurs.md)
