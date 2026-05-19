---
title: Assemblies
category: Inventory
order: 3
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Assemblies

> **Status:** Stub — not yet drafted.

## What this page will cover

- Assembly SKUs (`ma`, `mi`) — composed of component SKUs
- Defining the bill of materials (BOM)
- Build-to-order vs. build-to-stock — when each makes sense
- The build transaction (jID=32 work order) — pulls components, produces assembly, captures labor
- COGS rollup: cost of the assembly = sum of components + labor + overhead
- Phantom assemblies (kits that don't track stock at the assembly level)
- Multi-level BOMs (assembly contains an assembly)

## Why it matters

Assemblies are where manufacturing-flavor users separate from straight
retail. The data model is more nuanced than a single-SKU inventory.

## Related

- [Work orders / production](./04-work-orders-production.md)
- [Inventory types](./01-inventory-types.md)
