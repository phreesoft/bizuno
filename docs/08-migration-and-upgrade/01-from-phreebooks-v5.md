---
title: From PhreeBooks v5
category: Migration & Upgrade
order: 1
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# From PhreeBooks v5

> **Status:** Stub — not yet drafted.

## What this page will cover

- What changed structurally between PhreeBooks 5 and Bizuno 7: schema, file layout, namespace, dependency stack
- The built-in migration script (`controllers/bizuno/install/migrate-7.0.php`) — what it does step by step (32 sub-steps covering tables, roles, users, address book, CRM, inventory, dashboards, PhreeForm)
- Pre-migration checklist: full backup, off-peak window, expected runtime
- What's preserved (essentially everything financial)
- What's reshaped (CRM into contact-type-i, dashboards into the new format)
- What's discarded (legacy `phreemsg`, `tax_rates_table`, etc.)
- Post-migration cache rebuild and validation queries
- Recovering from a failed migration

## Why it matters

The PhreeBooks 5 → Bizuno 7 jump is large. People going through it need to
know what to expect.

## Related

- [Backup and restore](../05-administration/03-backup-and-restore.md)
- [Cache mechanics](../05-administration/06-cache-mechanics.md)
