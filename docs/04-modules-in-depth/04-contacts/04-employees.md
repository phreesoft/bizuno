---
title: Employees
category: Contacts
order: 4
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Employees

An employee is a [contact](./01-the-contacts-table.md) with the employee role
(`ctype_e`) set. The most important thing to understand on this page isn't a field
— it's the distinction between an **employee** and a **user**, which trips up
almost everyone the first time.

> **Employee ≠ User.** Being an employee does not give someone a login, and having
> a login does not make someone an employee. They are two independent role flags on
> the contact record. See [Employee vs. user](#employee-vs-user).

---

## Employee setup

*Employees → Employees → New* creates a contact with `ctype_e`. The employee form
surfaces the fields that matter for a person record:

- **Name** — `contact_first`, `contact_last`, and `flex_field_1` (used as the
  middle name for employees).
- **Contact ID** (`short_name`) — the employee ID.
- **`gov_id_number`** — SSN / government ID.
- **`account_number`** — doubles as the employee's sign-off PIN (used for
  production step sign-offs, see [Work orders](../03-inventory/04-work-orders-production.md)).
- **`store_id`** — the employee's store.
- **`first_date`** — record creation, used as the hire date; address, phones,
  email as usual.

A **pay rate** can be stored in the employee's metadata (`pay_rate`), and
**training** records likewise live in contact metadata.

Employee records are **admin-gated** (the `admin` security key), since they hold
personal data.

---

## What employees do *not* hold

Bizuno core has **no payroll setup on the employee record** — no pay-period
configuration, no W-4 / withholding profile, no deduction templates. That's not an
oversight to work around; it's because **Bizuno doesn't run payroll** — it imports
finished payroll from a provider and posts it to the General Journal. See
[Payroll](../01-phreebooks/04-payroll.md) for the full picture. The employee
record is for identity, assignment, and HR reference, not for computing pay.

---

## Employee vs. user

This is the distinction to internalize:

| | **Employee** (`ctype_e`) | **User** (`ctype_u`) |
|---|---|---|
| What it is | A person you employ | A Bizuno **login** |
| Grants a login? | No | Yes |
| Has a password / profile? | No | Yes (`user_profile` metadata + auth) |
| Security to manage | `admin` | `admin` |

They are separate flags **on the same kind of record**, and a single contact can
be both — your bookkeeper is typically an employee *and* a user. But:

- A **warehouse employee** who never logs in is an employee with **no** user flag —
  don't create a login you don't need.
- An **external accountant** with a login but not on your payroll is a user with
  **no** employee flag.

> **Why it matters for security.** Treating "employee" and "user" as the same thing
> leads to role-permission mistakes — either giving logins to people who shouldn't
> have them, or assuming an employee can sign in when they have no user account.
> Grant the **user** role (and a role's permissions) deliberately, separately from
> recording someone as an employee. See
> [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md).

---

## Hire / termination and HR continuity

`first_date` serves as the hire date; termination is handled by marking the
employee **inactive** rather than deleting them. There's **no automated
date-driven behavior** (no termination triggers, no date-based payroll cutoffs in
core) — the dates are records, not automation.

Because everyone lives in the one contacts table, an employee who leaves — or who
becomes a contractor — keeps their record and history. Flip the roles (drop
`ctype_e`, perhaps add `ctype_v`) rather than starting over; the HR and
transaction history stays intact.

---

## Related

- [The contacts table](./01-the-contacts-table.md)
- [Users vs. employees vs. contacts](../../05-administration/02-users-employees-contacts.md)
- [Payroll](../01-phreebooks/04-payroll.md) — why there's no pay calc here
- [Work orders / production](../03-inventory/04-work-orders-production.md) — employee step sign-offs
