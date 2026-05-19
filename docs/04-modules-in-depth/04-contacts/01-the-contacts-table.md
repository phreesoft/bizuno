---
title: The Contacts Table
category: Contacts
order: 1
status: stub
audience: [admin, developer]
last-updated: 2026-05-15
---

# The Contacts Table

> **Status:** Stub — not yet drafted.

## What this page will cover

- Full field reference: id, type, ctype_*, gl_acct_c/v, ach_*, terms, currency, tax_rate_id, etc.
- The `type` field vs. the `ctype_*` boolean flags — historical legacy + current meaning
- Why `address_book` is a separate table (one contact, many addresses)
- The `gl_acct_c` and `gl_acct_v` per-contact default AR/AP accounts
- Marketplace flag (sales tax remittance considerations)
- Tax exempt setup
- Restricted-store users — when a contact is locked to one store_id

## Why it matters

Reference page for developers writing custom journals or reports and admins
who need to clean up a duplicated customer-vs-vendor mess.

## Related

- [Contacts as the universal entity](../../02-core-concepts/04-contacts-as-universal-entity.md)
- [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md)
