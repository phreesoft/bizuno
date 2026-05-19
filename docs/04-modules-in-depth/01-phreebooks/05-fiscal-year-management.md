---
title: Fiscal-year Management
category: PhreeBooks
order: 5
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Fiscal-year Management

> **Status:** Stub — not yet drafted.

## What this page will cover

- Adding a new fiscal year (`fyAdd`) — manual vs. auto-extension via `ensureFiscalYearCovers` (7.3.9+)
- Editing fiscal-period dates after they exist
- Closing a fiscal year (`fyClose`): what it deletes, what it preserves, what it re-numbers
- The self-heal pass added in 7.3.9 (re-stamps `journal_main.period` from `post_date` post-renumber)
- Recovering from a botched close (restoring from backup is the only true recovery)
- Multi-year retention strategy (don't close too aggressively — historical reports get harder)

## Why it matters

FY close is one of two operations that genuinely can destroy your books if
done wrong (the other is restoring the wrong backup). Documentation should
read like a checklist, not a tutorial.

## Related

- [Fiscal periods vs. calendar dates](../../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md)
- [Fiscal-year close](../../05-administration/04-fiscal-year-close.md)
- [Backup and restore](../../05-administration/03-backup-and-restore.md)
