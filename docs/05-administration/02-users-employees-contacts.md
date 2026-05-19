---
title: Users vs. Employees vs. Contacts
category: Administration
order: 2
status: stub
audience: [admin]
last-updated: 2026-05-15
---

# Users vs. Employees vs. Contacts

> **Status:** Stub — not yet drafted.

## What this page will cover

- A **User** = a Bizuno login (`ctype_u`); has a role, credentials, MFA setup
- An **Employee** = a contact with `ctype_e`; tracked for payroll, HR
- A **Contact** = the big universal table; one record can carry both flags
- Why "I added an employee but they can't log in" — the User part wasn't created
- Why "I deleted the user but the payroll history is fine" — User and Employee are independent
- The system-administrator account (bootstrap user)
- Adding a user that isn't an employee (contractor with portal access, accountant for a client)

## Why it matters

The "you're really three things" model is one of Bizuno's most-confused
areas. A clear page here saves dozens of support tickets.

## Related

- [Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md)
- [Employees](../04-modules-in-depth/04-contacts/04-employees.md)
- [Roles and security](./01-roles-and-security.md)
