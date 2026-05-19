---
title: Returns and Credit Memos
category: Daily Workflows
order: 4
status: stub
audience: [bookkeeper]
last-updated: 2026-05-15
---

# Returns and Credit Memos

> **Status:** Stub — not yet drafted.

## What this page will cover

- The Returns module (jID=12 with `return` meta) — how it tracks status, codes, preventability
- The receive → inspect → close lifecycle on a customer return
- When to issue a credit memo vs. refund cash directly
- GL impact: reversing the original sale's COGS, returning inventory, settling AR
- Vendor returns (RMA back to supplier) — mirror workflow on the AP side
- Tying returns to the Quality module for trend analysis (top-return SKUs, top-return customers)

## Why it matters

Returns are where small accounting apps quietly let users break the books.
Bizuno's structured workflow exists because returns + COGS + sales tax are
hard to get right manually.

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md)
- [CA/PA tickets](../04-modules-in-depth/05-quality/01-ca-pa-tickets.md)
