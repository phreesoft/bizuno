---
title: Users vs. Employees vs. Contacts
category: Administration
order: 2
status: published
audience: [admin]
last-updated: 2026-06-16
---

# Users vs. Employees vs. Contacts

"I added an employee but they can't log in." "I want to give my accountant
access but she's not on staff." Both questions come from the same place: in
Bizuno, *user*, *employee*, and *contact* are not three separate lists — they're
three roles one contact record can wear, independently. This page untangles them
for the admin who manages logins.

The underlying model is covered in
[Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md);
here we focus on the user/employee split specifically.

---

## The three things

| Term         | What it is                                          | Flag       |
|--------------|-----------------------------------------------------|------------|
| **Contact**  | A row in the universal `contacts` table             | (the row)  |
| **User**     | A login account — credentials, a role, a profile    | `ctype_u`  |
| **Employee** | A person on payroll / HR                            | `ctype_e`  |

A contact becomes a **user** when it carries `ctype_u` plus authentication data
and a profile (role, store/period restrictions). It becomes an **employee** when
it carries `ctype_e` plus HR data (hire date, pay rate). The two flags are
independent — a record can have neither, either, or both.

So the real staff member who logs in is **one** contact with both flags. But the
flags answer different questions: `ctype_e` is "do we pay this person?";
`ctype_u` is "can this person sign in?".

---

## "I added an employee but they can't log in"

Because adding an employee sets `ctype_e`, not `ctype_u`. An employee record is
HR data — it grants no access to anything. To let that person sign in, you also
have to **create the user side**: give the contact the user role, a role
assignment, and a password, from the Users admin screen. Until the `ctype_u`
side exists, there's no login.

The reverse is just as true: creating a user does not put anyone on payroll.

---

## "I removed the login but the payroll history is intact"

Also expected — and the safe way to do it. To revoke someone's access without
erasing the person, you **remove the user role** (clear `ctype_u` and its profile),
leaving the employee record and its history untouched. The two roles live on the
same row but carry separate data, so dropping one doesn't disturb the other.

> **Don't reach for Delete.** Deleting the *contact* removes the whole row — both
> roles at once — and Bizuno blocks the delete entirely if the contact has any
> journal history (its `id` is referenced by postings). The correct moves are
> "remove the user role" or "mark inactive," not delete. See
> [The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md).

---

## A user who isn't an employee

This is fully supported and common:

- An **outside accountant** you grant a login to at month-end.
- A **contractor** who needs portal access but isn't on payroll.
- A **client** you host who logs into their own books.

Each is just a contact with `ctype_u` set and `ctype_e` left off — a login with a
role, no payroll record, no HR data. Nothing forces the employee flag along with
the user flag.

The inverse — an employee who never logs in (warehouse staff you track for
payroll only) — is the same idea with the flags swapped: `ctype_e` on, `ctype_u`
off.

---

## The bootstrap administrator

The install process creates the first account for you: a single contact with
`ctype_u` set and the built-in **Administrator** role assigned, using the email
and password you supply during setup. Note it's created as a *user only* — the
first admin is not automatically an employee. It's the account you log in with to
create everyone else; secure its password and reserve the Administrator role (and
the `administrate` flag) for the few people who genuinely run the system. See
[Roles and security](./01-roles-and-security.md).

---

## Authentication and password resets

- **Passwords** are hashed and stored with the user's profile data, never in
  plain text.
- **Two-factor authentication** is available as an emailed one-time code: when a
  user has 2FA enabled, login is email → password → emailed code.
- **Admin password reset.** An administrator (security level 5) can set a new
  password for a user directly from the user edit screen, without knowing the old
  one — for when someone is locked out. *(since 7.4.3)*

> **Passkeys are not available yet.** The WebAuthn library ships in the codebase,
> but there is no passkey/security-key enrollment in the UI. Today's
> multi-factor option is the emailed code described above — don't promise users
> hardware passkeys until the enrollment flow lands.

---

## Related

- [Contacts as the universal entity](../02-core-concepts/04-contacts-as-universal-entity.md) — the one-record-many-roles model
- [The contacts table](../04-modules-in-depth/04-contacts/01-the-contacts-table.md) — the field/flag reference
- [Employees](../04-modules-in-depth/04-contacts/04-employees.md) — the HR/payroll side
- [Roles and security](./01-roles-and-security.md) — what a user's role controls
