---
title: Chart of Accounts
category: PhreeBooks
order: 1
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Chart of Accounts

> **Status:** Stub — not yet drafted.

## What this page will cover

- The `PHREEBOOKS_CHART_TYPES` map (0=Cash, 2=AR, 4=Inventory, 6=Other Current Assets, 8=Fixed Assets, 10=Accum Depreciation, 12=Other Assets, 20=AP, 22=Other Current Liab, 24=LT Liab, 30=Income, 32=COGS, 34=Expenses, 40=Equity-Open, 42=Equity-Closes, 44=Retained Earnings)
- Picking a template at install vs. building from scratch
- Heading rows vs. account rows
- The "default" account per type (e.g. default AR account used when an invoice line has no GL override)
- Editing an existing COA after transactions have posted — what's safe, what isn't
- Multi-store COA considerations

## Why it matters

The COA is the structural skeleton everything else hangs on. Bad COA = bad
reports forever, and rebuilding mid-life is painful.

## Related

- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
- [Fiscal-year management](./05-fiscal-year-management.md)
