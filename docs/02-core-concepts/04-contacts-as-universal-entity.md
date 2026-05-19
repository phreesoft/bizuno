---
title: Contacts as the Universal Entity
category: Core Concepts
order: 4
status: stub
audience: [bookkeeper, admin]
last-updated: 2026-05-15
---

# Contacts as the Universal Entity

> **Status:** Stub — not yet drafted.

## What this page will cover

- The single `contacts` table holds everyone: customer, vendor, employee, branch, CRM lead, project, system user
- The `ctype_*` flags (c/v/e/b/i/j/u) — one record can carry several
- Address book is separate (`address_book`) — one contact, many addresses (mail/ship/billing)
- Why this matters: a person who is both a customer and a vendor shares aged-history; an employee who becomes a contractor doesn't lose their HR record
- Common gotchas: deleting a contact that has both flags, restricted-store users, ctype_u (system user) vs. ctype_e (employee) overlap

## Why it matters

Most accounting systems force two records ("Bob the customer" and "Bob the
vendor") and never connect them. Bizuno doesn't. That's powerful and
occasionally confusing.

## Related

- [The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md)
- [Users vs. employees vs. contacts](../05-administration/02-users-employees-contacts.md)
