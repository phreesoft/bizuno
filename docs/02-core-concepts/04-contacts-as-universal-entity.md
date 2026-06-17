---
title: Contacts as the Universal Entity
category: Core Concepts
order: 4
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-16
---

# Contacts as the Universal Entity

In most accounting systems, "Bob's Hardware" who both buys from you and supplies
you is two records — a customer in one list, a vendor in another — that never
know about each other. Bizuno doesn't work that way. Customers, vendors,
employees, branches, CRM leads, projects, and login users all live in **one**
`contacts` table. What a record *is* depends on which **role flags** it carries,
and a single record can carry several at once.

This is the concept page. The field-by-field reference lives in
[The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md).

---

## One record, many roles

A contact's roles are independent boolean columns — `ctype_*`, one per role:

| Flag      | Role        | Meaning                          |
|-----------|-------------|----------------------------------|
| `ctype_c` | Customer    | You sell to them                 |
| `ctype_v` | Vendor      | You buy from them                |
| `ctype_e` | Employee    | A person you employ              |
| `ctype_b` | Branch      | A store / branch location        |
| `ctype_i` | CRM contact | A lead or prospect in the CRM    |
| `ctype_j` | Project     | A project / job record           |
| `ctype_u` | User        | A Bizuno **login** account       |

These are separate flags, not one "type" field — so they're not mutually
exclusive. "Bob's Hardware" who both sells to you and buys from you is **one**
contact with `ctype_c` *and* `ctype_v` both set. Saving a contact as a vendor
sets its vendor flag without clearing whatever flags it already had; roles
accumulate on the record.

> **Legacy note.** Before v7.0 a contact had a single `type` character and could
> be only one thing. The 7.0 migration spread that one column into the boolean
> flags above. If you find old code or reports referencing a `type` character on
> a contact, that's the history — the live model is the flags.

---

## Why a shared record is the point

The payoff isn't tidiness — it's that one real-world entity keeps one identity
and one history:

- **A customer who is also a vendor** shares aged history, contact details, and
  account context across both sides. You see the whole relationship on one
  record instead of reconciling two.
- **An employee who becomes a contractor** keeps their record; you add the vendor
  role rather than re-keying them and orphaning their HR history.
- **Reps, projects, and CRM activity** all hang off the same `id`, so a lead that
  converts to a customer doesn't lose the trail that got it there.

Every transaction references a contact by its `id`. Because the entity is one
record, that reference is unambiguous no matter how many roles the entity plays.

---

## Users vs. employees — the overlap worth understanding

Two roles are easy to conflate, and they are genuinely different things:

- **`ctype_e` (employee)** is a *person you employ* — it carries HR data (hire
  date, pay rate) and feeds payroll. It grants no access to anything.
- **`ctype_u` (user)** is a *login account* — it carries authentication and a
  security profile (role, store/period restrictions). It's what lets someone
  sign in.

A real employee who logs in is one contact with **both** flags. But the roles
are independent: a bookkeeper login that isn't on payroll is a user without the
employee flag, and an employee who never signs in is an employee without the
user flag. Both roles are admin-gated — you manage users from
[administration](../05-administration/02-users-employees-contacts.md), not the
ordinary contacts screen.

---

## Where addresses live

A contact is one entity, but it can need many addresses — bill-to, several
ship-tos, CRM detail contacts. Bizuno keeps the **primary mailing address on the
contact row itself** and stores additional addresses as metadata keyed by
purpose (billing, shipping, and so on). So a single customer can have a dozen
ship-to locations without ever becoming a dozen contacts. The address detail is
covered in [The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md#where-addresses-live).

---

## Common gotchas

- **Don't delete a contact that has history.** If any journal transaction
  references the contact, Bizuno blocks the delete (the postings point at its
  `id`). Mark it **inactive** instead. This is exactly what protects a
  dual-role contact: you can't accidentally erase the vendor side of someone
  who's also a customer.
- **The duplicate customer/vendor mess.** The classic cleanup is an entity
  entered twice — once as a customer, once as a vendor. The right end state is
  *one* record with both flags set, with historical transactions re-pointed to
  the surviving `id`. Don't "fix" it by deleting one; merge into one.
- **Restricted-store and rep-locked users** only see contacts within their store
  (`store_id`) or assigned to them (`rep_id`). If a contact "disappears" for one
  user but not another, suspect a restriction, not a missing record.
- **Removing a role isn't deleting the contact.** Clearing `ctype_v` makes
  someone no longer a vendor while keeping them as the customer they still are —
  the record and its history stay intact.

---

## Why this matters

The single-entity model is powerful and occasionally surprising. The power: one
identity, one history, no reconciling parallel lists. The surprise: a record can
quietly wear several hats, so "delete this vendor" might be trying to remove a
customer too. Once you think in terms of *one entity, a set of role flags, one
shared history*, the contacts module — and a lot of the rest of Bizuno — stops
having sharp edges.

---

## Related

- [The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md) — the full field and role-flag reference
- [Users vs. employees vs. contacts](../05-administration/02-users-employees-contacts.md) — the user/employee split in administration
- [Roles and security](../05-administration/01-roles-and-security.md) — how user role flags map to access
