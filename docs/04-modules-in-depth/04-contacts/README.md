# 4.4 Contacts

Customers, vendors, employees, branches, CRM contacts, and projects all live
in a **single `contacts` table** with type flags (`ctype_c`, `ctype_v`,
`ctype_e`, `ctype_b`, `ctype_i`, `ctype_j`, `ctype_u`). One person who is both
a customer *and* a vendor is one record, not two. This is intentional and is
the source of a lot of Bizuno's flexibility — and a lot of first-encounter
confusion.

See [Contacts as the universal entity](../../02-core-concepts/04-contacts-as-universal-entity.md)
for the conceptual reasoning before drilling into the per-role pages below.

## Pages

| #  | Page                                                                  | Status | Audience       |
|----|-----------------------------------------------------------------------|--------|----------------|
| 01 | [The contacts table](./01-the-contacts-table.md)                      | stub   | admin, developer |
| 02 | [Customers](./02-customers.md)                                        | stub   | bookkeeper     |
| 03 | [Vendors](./03-vendors.md)                                            | stub   | bookkeeper     |
| 04 | [Employees](./04-employees.md)                                        | stub   | bookkeeper, admin |
| 05 | [Projects and CRM](./05-projects-and-crm.md)                          | stub   | bookkeeper     |
