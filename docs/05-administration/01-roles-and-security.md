---
title: Roles and Security
category: Administration
order: 1
status: published
audience: [admin]
last-updated: 2026-06-16
---

# Roles and Security

Bizuno's access control is **role-based**. You define roles, assign each user to
a role, and the role carries a per-screen security map. Get this right and the
right people see the right screens; get it wrong — almost always by
over-granting — and a sales clerk can void journal entries. This page is the
admin's guide to the model and the common role recipes.

---

## The model: roles → users

A **role** is a named bundle of permissions. A **user** (a contact with the
`ctype_u` login flag — see [Users vs. employees vs. contacts](./02-users-employees-contacts.md))
is assigned exactly one role, and inherits its permissions.

Under the hood, a role is stored as JSON in `common_meta` (key `bizuno_role`)
and its permissions are a **flat map** of `screen_key => level`:

```
"inventory/main/manager" => 4,
"phreebooks/main/manage" => 2,
"contacts/main/manage"   => 1,
...
```

You edit this map through the role editor — **Settings → Roles** — never by
hand.

---

## Security levels

Each screen in a role gets one level. They are **cumulative** — a higher level
includes everything below it:

| Level | Name      | Grants                                          |
|:-----:|-----------|-------------------------------------------------|
| 0     | None      | No access — the screen doesn't appear           |
| 1     | Read Only | View / open records, run reports                |
| 2     | Add       | …plus create new records                        |
| 3     | Edit      | …plus modify existing records                   |
| 4     | Full      | …plus delete                                    |

> **Mind the order.** It's None → Read → **Add → Edit** → Full. "Add" sits
> *below* "Edit": a level-2 user can create a record but not change one that
> already exists. A screen that requires level 3 (edit) is satisfied by a role
> granting 3 or 4, because the check is "is the user's level ≥ what the screen
> needs."

The role editor's dropdown also shows a **level 5 ("Administrate")** label, but
permission checks top out at 4 — an ordinary screen is never granted more than
Full. Level 5 is the editor's way of marking admin-only controls; the real
all-access switch is the super-flag below.

---

## The `administrate` super-flag

A role can carry an `administrate` flag. When set, any check of the special
`admin` key returns Full automatically — so admin-only operations (password
resets, fiscal-year close, the cache-clear button, role editing itself) unlock
without needing a per-screen entry for each. It's the difference between "an
operator" and "an administrator."

Grant `administrate` sparingly — it's the one switch that bypasses the per-screen
discipline the rest of this page is about.

---

## Parent-menu access from child screens

A container screen — say `inventory/build/manager`, which has child screens — is
reachable even though the role editor only exposes checkboxes for its *children*.
Bizuno derives a parent menu's effective level from the **maximum of its
children's levels**: grant access to any child and the parent menu that holds it
becomes reachable, with no separate checkbox to forget. *(since v7.4.0)*

The practical upshot: you grant the leaf screens a user needs, and the menus
that contain them light up on their own. You don't hunt for a parent toggle.

---

## Restricting a user to one store

Beyond the role, a **user profile** flag scopes *which records* a user sees:

- **`restrict_store`** — the user only sees (and creates) records in their
  assigned `store_id`. A multi-store business gives each location's staff this
  flag so they don't see each other's contacts, orders, or stock.
- **`restrict_user`** — a rep only sees contacts assigned to them (`rep_id`
  matches their user id).

These are per-user (set on the user's profile), not per-role — two users on the
same role can have different store restrictions. See
[Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md).

---

## Common role recipes

Start from least privilege and add only what the job needs:

| Role             | Shape                                                                 |
|------------------|-----------------------------------------------------------------------|
| **Read-only auditor** | Level 1 (Read) across PhreeBooks, Contacts, Inventory, reports. No add/edit anywhere. |
| **AR clerk**     | Full on sales journals (quote/SO/invoice/cash receipt) and customers; Read on inventory; nothing in AP or banking. |
| **AP clerk**     | Full on purchase journals (PO/bill/pay) and vendors; Read on inventory; nothing in AR. |
| **Sales-only**   | Add/Edit on quotes and sales orders, Read on customers and inventory; no invoicing, no GL. |
| **Bookkeeper**   | Full across PhreeBooks and Contacts, Read on Inventory and Quality; **no** `administrate`. |
| **Administrator**| `administrate` flag set. Reserve for the one or two people who actually run the install. |

The single most common mistake is handing out `administrate` (or blanket Full)
because it's easier than picking screens. Resist it — the recipes above cover
almost everyone.

---

## Who changed what

Bizuno keeps a general **audit log** (`audit_log`, written by `msgLog()`) that
records actions like logins, posts, and admin operations — and you can back it
up and prune it from the [backup tool](./03-backup-and-restore.md). Note its
limit: it logs *that* a role was saved, not a field-by-field diff of which
permission changed. For a precise before/after of a role's permission map, the
record is the role's current JSON, not a change history.

---

## Related

- [Users vs. employees vs. contacts](./02-users-employees-contacts.md) — what a "user" actually is
- [Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md) — the role-flag model users sit on
- [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md) — store restriction in context
- [Backup and restore](./03-backup-and-restore.md) — backing up and pruning the audit log
