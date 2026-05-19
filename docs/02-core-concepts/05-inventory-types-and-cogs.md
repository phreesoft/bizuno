---
title: Inventory Types and COGS
category: Core Concepts
order: 5
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Inventory Types and COGS

> **Status:** Stub — not yet drafted.

## What this page will cover

- The inventory type codes: `ma`, `mi`, `ms`, `sa`, `si`, `sr` (COGS-tracking) vs. all the non-COGS types
- What each letter means and the right one to pick for: physical goods, services, kitted assemblies, raw materials, finished goods
- The `INVENTORY_COGS_TYPES` constant and why it matters
- How costing works per type (FIFO/average/standard)
- Why the type is **effectively permanent** once you transact (history can't be retyped without rebuilding inventory_history)
- Decision flowchart: "I sell X — which inventory type?"

## Why it matters

Pick the wrong type on day one and you're either over-reporting COGS, under-reporting assets, or rebuilding history. None of these are fun.

## Related

- [Inventory types](../04-modules-in-depth/03-inventory/01-inventory-types.md)
- [History and costing](../04-modules-in-depth/03-inventory/02-history-and-costing.md)
