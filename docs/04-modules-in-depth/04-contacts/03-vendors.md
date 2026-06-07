---
title: Vendors
category: Contacts
order: 3
status: draft
audience: [bookkeeper]
last-updated: 2026-06-07
---

# Vendors

A vendor is a [contact](./01-the-contacts-table.md) with the vendor role
(`ctype_v`) set — the buying-side mirror of a [customer](./02-customers.md). Most
of the setup is identical; this page focuses on the vendor-specific parts (default
expense account, ACH payment details, the 1099 reality, aged AP).

---

## Add a vendor

*Vendors → Vendors → New.* As with customers, you need a **Contact ID** and a
**primary name**; the rest defaults:

- **Default GL / expense account** seeds from *Settings → PhreeBooks → Vendors*
  (`gl_expense`); the AP control account from `gl_acct_v`.
- **Default tax authority** from the vendor tax default.
- **Terms** set the bill due date.
- **`account_number`** is your account number *with that vendor*.

---

## ACH payments

To pay a vendor electronically, set up their bank details on the vendor record:
`ach_enable`, `ach_bank`, `ach_routing`, `ach_account`. When you run
[Pay Bills (jID 20)](../01-phreebooks/02-journals.md#jid-20--pay-bills-j20php), the
bulk/ACH check run picks up ACH-enabled vendors and builds the NACHA file; non-ACH
vendors get printed checks with incrementing numbers.

> **Sensitivity.** Bank fields are excluded from exports and hidden behind the
> `admin_encrypt` profile permission, so they're not visible to ordinary users.
> They are *access-restricted*, but the schema doesn't itself encrypt them at rest
> — if you need at-rest encryption, handle it at the database/hosting layer. See
> [The contacts table → ACH](./01-the-contacts-table.md#ach-banking).

---

## 1099 / W-9

Be aware of the gap here: **Bizuno core has no structured 1099 or W-9 tracking.**
There is a single generic `gov_id_number` field (which doubles as SSN for
employees and tax ID for vendors), but **no 1099 vendor flag, no box mapping, and
no 1099/1096 generation.**

If you need to issue 1099-NECs:

- Record the tax ID in `gov_id_number` and flag your 1099 vendors however you
  track them (a naming convention, a custom field, or a report filter).
- Pull the year's payments per vendor with a [PhreeForm](../02-phreeform/01-report-engine-overview.md)
  report and file through your tax service or accountant.

Don't expect a one-click 1099 run — it isn't there.

---

## Marketplace flag

The `marketplace` column exists on contacts, but as noted on
[the table page](./01-the-contacts-table.md#tax) it currently has no posting
behavior wired to it. Treat it as a label, not an automated sales-tax pass-through.

---

## Aged AP and history

- **Aged payables** come from open vendor bills and credits (journals 6 and 7),
  bucketed by due date — the same aging engine as the customer side, pointed at
  the payables journals.
- **History grids** on the vendor's History tab show their RFQs (jID 3), purchase
  orders (jID 4), and bills (jID 6), with the payment trail — the full account
  history in one view.

---

## Related

- [The contacts table](./01-the-contacts-table.md)
- [Customers](./02-customers.md) — the selling-side mirror
- [Pay Bills (jID 20)](../01-phreebooks/02-journals.md#jid-20--pay-bills-j20php)
- [PO → Receive → Bill → Pay](../../03-daily-workflows/02-po-receive-bill-pay.md)
