---
title: The Contacts Table
category: Contacts
order: 1
status: published
audience: [admin, developer]
last-updated: 2026-06-07
---

# The Contacts Table

Customers, vendors, employees, branches, CRM leads, projects, and login users all
live in **one table**: `contacts`. What a record *is* depends on which **role
flags** it carries — and a single record can carry several at once. This is the
reference page for that table: the fields, the role model, where addresses live,
and the per-contact defaults that drive posting.

> **One record, many roles.** "Bob's Hardware" who both buys from you and supplies
> you is **one** contact with both the customer and vendor flags set — not two
> records. That shared identity (and shared history) is the whole point. See
> [Contacts as the universal entity](../../02-core-concepts/04-contacts-as-universal-entity.md).

---

## Role flags

A contact's roles are independent boolean columns (`ctype_*`):

| Flag       | Role        | Notes                                             |
|------------|-------------|---------------------------------------------------|
| `ctype_c`  | Customer    | You sell to them                                  |
| `ctype_v`  | Vendor      | You buy from them                                 |
| `ctype_e`  | Employee    | A person you employ (admin-gated)                 |
| `ctype_b`  | Branch      | A store/branch location                           |
| `ctype_i`  | CRM contact | A lead/prospect in the CRM                         |
| `ctype_j`  | Project     | A project/job record                              |
| `ctype_u`  | User        | A Bizuno **login** (admin-gated)                  |

The code works with these through the `contacts` class: the `ROLES` constant maps
the letters `c/v/e/b/i/j/u` to the `ctype_*` columns, and helpers like
`hasRole()`, `getRoles()`, and `isEmployee()` read them. Each role also maps to a
**security key** — `mgr_c` (customers), `mgr_v` (vendors), `mgr_i` (CRM), `mgr_j`
(projects), and `admin` for employees and users — so access is granted per role.

> **Legacy note (`type` → `ctype_*`).** Before v7.0 a contact had a single `type`
> character and could be only one thing. The 7.0 migration spread that into the
> boolean flags above and renamed the old column `xtype` (no longer used). If you
> read old code or reports referencing `type`, that's the history.

---

## Field reference

### Identity & status

| Field          | Meaning                                                     |
|----------------|------------------------------------------------------------|
| `id`           | Primary key                                                |
| `short_name`   | The human Contact ID (unique short code)                   |
| `primary_name` | Business / primary name (required)                         |
| `contact_first` / `contact_last` / `contact` | Person name / attention   |
| `flex_field_1` | Title (customers/vendors) or middle name (employees)       |
| `inactive`     | Status: `0` active, `1` inactive, `2` locked, `H` credit hold |
| `first_date`   | Record created (used as hire date for employees)           |
| `date_last`    | Last updated                                               |

### Contact methods

Mailing address is stored **directly on the contact row** (`address1`, `address2`,
`city`, `state`, `postal_code`, `country`) along with up to four each of
`telephone1-4`, `email`/`email2-4`, plus `website` and a `newsletter` opt-in flag.

### Business & posting defaults

| Field          | Meaning                                                          |
|----------------|-----------------------------------------------------------------|
| `account_number` | Their account number for you (vendor-assigned, etc.)          |
| `rep_id`       | The assigned rep/user                                           |
| `store_id`     | The store this contact belongs to                              |
| `terms`        | Encoded payment terms — and the credit limit (see [Customers](./02-customers.md#credit-limit-and-hold)) |
| `price_sheet`  | Default price level (references an inventory price sheet)        |
| `gl_account`   | Default income/expense line account                            |
| `gl_acct_c`    | Customer **AR** control account default                         |
| `gl_acct_v`    | Vendor **AP** control account default                          |

When a transaction line for this contact doesn't specify a GL account, these
defaults fill in — `gl_acct_c`/`gl_acct_v` for the AR/AP control side, `gl_account`
for the revenue/expense side. They seed from the
[PhreeBooks module defaults](../01-phreebooks/01-chart-of-accounts.md#default-accounts--why-posting-just-works)
when the contact is created.

### Tax

| Field         | Meaning                                                           |
|---------------|------------------------------------------------------------------|
| `tax_rate_id` | Default sales-tax authority for this contact                     |
| `tax_exempt`  | `1` = no tax calculated on this contact's transactions           |
| `marketplace` | Flags a customer as a marketplace that remits tax itself         |

> **`marketplace` is a flag, not behavior (yet).** The column exists and you can
> set it, but no posting logic currently keys off it to change tax treatment —
> treat it as a label for now, not an automated marketplace-facilitator
> pass-through.

### ACH banking

`ach_enable`, `ach_bank`, `ach_routing`, `ach_account` hold the bank details used
for [ACH vendor payments / customer drafts](./03-vendors.md#ach-payments). These
fields are **excluded from export** and their panel is gated behind the
`admin_encrypt` profile permission, so they're not shown to ordinary users.

> **Be precise about "encrypted."** The ACH columns are *access-restricted* and
> never exported — but the table schema doesn't define column-level encryption at
> rest. If your deployment needs encryption-at-rest for bank details, treat it as
> a hosting/database concern, not something the schema guarantees.

### `gov_id_number`

A generic government ID (SSN for an employee, tax ID for a vendor). It's a single
free-text field — there is **no** structured 1099/W-9 handling built on it (see
[Vendors → 1099](./03-vendors.md#1099--w-9)).

---

## Where addresses live

A contact has **one primary address on the row** (the mailing address) and **any
number of additional addresses in `contacts_meta`**, keyed by purpose:

| Meta key    | Address                          |
|-------------|----------------------------------|
| `address_b` | Billing                          |
| `address_s` | Shipping / ship-to (branches)    |
| `address_i` | CRM detail contacts              |

So one customer can have many ship-to addresses without duplicating the contact.
The `getAddresses($type)` helper reads them.

> **Legacy note (`address_book`).** Pre-7.0, addresses lived in a separate
> `address_book` table. The 7.0 migration denormalized the primary address onto
> the contact row and moved the rest into `contacts_meta`. If you see
> `address_book` referenced anywhere, it's the old model.

---

## Store restriction and rep locking

Two mechanisms scope who sees which contacts:

- **`store_id`** ties a contact to a store. A user with restricted store access
  only sees (and creates) contacts in their store, and new contacts are locked to
  it.
- **`restrict_user`** (a user profile flag) limits a user to contacts where
  `rep_id` matches their own user ID — a salesperson sees only their accounts.

---

## Related meta and log tables

- **`contacts_meta`** — flexible per-contact key/value data: the extra addresses
  above, `pay_rate` (employees), `crm_project` (projects), price-level meta, and
  more.
- **`contacts_log`** — the per-contact activity/audit trail (CRM actions, changes),
  with `log_date`, `entered_by`, `action`, and `notes`.

---

## Cleaning up a duplicated customer-vs-vendor mess

Because one entity should be one record, the classic cleanup is merging a contact
that was entered twice — once as a customer, once as a vendor. The right end state
is a single record with **both** `ctype_c` and `ctype_v` set. Re-point historical
transactions to the surviving `id`, set both role flags, copy across the
`gl_acct_c` / `gl_acct_v` defaults, and retire (mark inactive) the duplicate.
Don't delete a contact that still has journal history — its postings reference its
`id`.

---

## Related

- [Contacts as the universal entity](../../02-core-concepts/04-contacts-as-universal-entity.md)
- [Customers](./02-customers.md) · [Vendors](./03-vendors.md) · [Employees](./04-employees.md)
- [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md)
- [Chart of Accounts](../01-phreebooks/01-chart-of-accounts.md) — the GL defaults these fields point at
