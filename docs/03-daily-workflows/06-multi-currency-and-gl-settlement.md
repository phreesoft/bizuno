---
title: Multi-currency Invoice and GL Settlement
category: Daily Workflows
order: 6
status: stub
audience: [bookkeeper]
last-updated: 2026-05-15
---

# Multi-currency Invoice and GL Settlement

> **Status:** Stub — not yet drafted.

## What this page will cover

- Default currency vs. transaction currency
- How a EUR invoice posts when the default is USD (transaction stored in EUR, GL recorded in USD at the day's rate)
- Where exchange rates come from (manual, API, mid-month update)
- Realized FX gain/loss at payment time (the gap between invoice-day rate and payment-day rate)
- Unrealized FX at period end — revaluing open AR/AP in foreign currencies
- Reporting: per-currency vs. converted-to-default views

## Why it matters

Multi-currency support is something most cheap apps don't even attempt. The
GL plumbing is genuinely complex; the doc has to explain it without scaring
users away.

## Related

- [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md)
- [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md)
