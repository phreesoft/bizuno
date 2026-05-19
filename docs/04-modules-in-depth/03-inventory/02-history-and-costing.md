---
title: History and Costing
category: Inventory
order: 2
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# History and Costing

> **Status:** Stub — not yet drafted.

## What this page will cover

- `inventory_history` table — the per-SKU running ledger Bizuno consults for COGS calculation
- FIFO vs. average vs. standard cost — which one Bizuno uses, how to configure
- How costing happens on receive (PO price, landed cost adjustments)
- COGS calculation at time of sale
- Adjustment transactions (write-down, write-up, count adjustment)
- Recomputing history after data corruption (when, how, the rebuild tool)

## Why it matters

The "what did this widget cost me" question is core to running a profitable
shop. Misunderstanding the costing method leads to wrong margins on every
report.

## Related

- [Inventory types](./01-inventory-types.md)
- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
