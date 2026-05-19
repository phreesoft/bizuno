---
title: From QuickBooks
category: Migration & Upgrade
order: 2
status: stub
audience: [admin, bookkeeper]
last-updated: 2026-05-15
---

# From QuickBooks

> **Status:** Stub — not yet drafted.

## What this page will cover

- The recommended import strategy: start fresh in Bizuno; bring opening balances + master data, not full history
- Master-data CSV imports: customers, vendors, inventory, employees, COA
- Opening-balance journal entry (one big GL adjustment as of the migration date)
- Things QuickBooks does that Bizuno does differently: Undeposited Funds, classes/locations, recurring memorized transactions, sales-tax centre
- Things QuickBooks does that Bizuno doesn't: native bank feeds, QuickBooks-only third-party integrations
- Validating the migration: a basic balance-sheet match before / after
- When to keep QB read-only as historical reference (vs. archiving entirely)

## Why it matters

QuickBooks-refugees are a sizable share of Bizuno's potential audience. Doc
has to be honest about what's different so they don't keep expecting QB
behaviors.

## Related

- [First-hour walkthrough](../01-getting-started/03-first-hour-walkthrough.md)
- [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md)
