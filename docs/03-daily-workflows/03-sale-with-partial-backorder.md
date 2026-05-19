---
title: Sale with Partial Backorder
category: Daily Workflows
order: 3
status: stub
audience: [bookkeeper]
last-updated: 2026-05-15
---

# Sale with Partial Backorder

> **Status:** Stub — not yet drafted.

## What this page will cover

The "I have 3 in stock, customer wants 5, I'll invoice the 3 and back-order
the 2" pattern, end-to-end:

- Starting from the SO, splitting the line into shipped and back-ordered
- How Bizuno handles the GL on the partial ship (only the 3 hit COGS)
- The remaining SO record — how to find it, how to fulfill when stock arrives
- Customer communication (the partial-ship invoice vs. the back-order acknowledgment)
- Auto-creating a PO for the back-ordered items from the dashboard

## Why it matters

Backorders are one of the patterns that small accounting apps just punt on
("manually adjust"). Bizuno handles them natively and the workflow rewards
the user who learns it.

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md)
- [Inventory types](../04-modules-in-depth/03-inventory/01-inventory-types.md)
