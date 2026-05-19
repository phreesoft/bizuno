---
title: Work Orders / Production
category: Inventory
order: 4
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Work Orders / Production

> **Status:** Stub — not yet drafted.

## What this page will cover

- Work-order lifecycle: design → schedule → release → in-progress → complete → close
- The Production Manager (`inventory/build/manager`) and its child screens (Designer + Tasks)
- Role security pattern (parent's effective access = max-child access, since the role editor doesn't expose a checkbox for the parent — see `removeOrphanMenus` change in 7.3.9)
- Capturing labor and overhead onto the work order
- Partial completions (split a work order)
- Tying work orders to sales orders (build-to-order)
- Stop-Work integration with the Quality module

## Why it matters

Work orders are where Bizuno crosses from "accounting app" into "light MRP."
Documentation needs to be careful — over-promise and you draw users who
needed a real MRP; under-describe and you hide real value.

## Related

- [Assemblies](./03-assemblies.md)
- [CA/PA tickets](../05-quality/01-ca-pa-tickets.md)
- [Roles and security](../../05-administration/01-roles-and-security.md)
