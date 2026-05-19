---
title: Inventory Types
category: Inventory
order: 1
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Inventory Types

> **Status:** Stub — not yet drafted.

## What this page will cover

- Full per-type reference: physical stock, service, kit, raw material, finished good, charge-only, etc.
- Which types are in `INVENTORY_COGS_TYPES` and therefore post to COGS
- Behavior at receive, sale, return, adjustment per type
- Type-specific fields (e.g. assembly components on `ma`/`mi`)
- Picking the right type — decision flowchart
- Why changing type after transactions is a re-build, not an edit

## Why it matters

Builds on [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
with the full per-letter reference.

## Related

- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
- [History and costing](./02-history-and-costing.md)
