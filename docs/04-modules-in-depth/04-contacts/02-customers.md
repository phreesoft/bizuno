---
title: Customers
category: Contacts
order: 2
status: draft
audience: [bookkeeper]
last-updated: 2026-06-07
---

# Customers

A customer is a [contact](./01-the-contacts-table.md) with the customer role
(`ctype_c`) set. This page is the bookkeeper's path: add one fast, then layer on
the optional setup (terms, tax exemption, ship-to branches, pricing) only if you
need it.

---

## Add a customer — the fast path

*Customers → Customers → New.* The only things you truly need are a **Contact ID**
(`short_name`) and a **primary name**; everything else has a sensible default:

- **Default GL / revenue account** seeds from *Settings → PhreeBooks → Customers*
  (`gl_sales`); the AR control account from the same place (`gl_acct_c`).
- **Default tax authority** (`tax_rate_id`) seeds from the customer tax default.
- **Sales rep** (`rep_id`) — optional; drives rep filtering and commission reports.

Save and you can immediately quote, order, and invoice them.

---

## Terms

The **terms** control sets the payment expectation (Net 30, Due on receipt, etc.)
and seeds the due date on every invoice. Edit them from the customer's terms
dialog (the gear next to the terms field). Terms are stored encoded on the contact
and can be overridden per transaction at entry time.

---

## Credit limit and hold

The customer's **credit limit** is carried inside the terms data (default 1000).
Two things to understand about how Bizuno uses it:

- **It's a warning, not a wall.** When a customer's open AR exceeds their credit
  limit, the order/entry screen shows a **yellow status** flag. Bizuno does **not**
  block new orders over the limit — the decision stays with you.
- **Credit hold** is the `inactive = H` status. It marks the account on hold
  (shown highlighted in the customer list) but, likewise, is a flag for humans
  rather than an automated gate.

If you need hard enforcement (refuse to save an over-limit order), that's a
customization, not a built-in setting.

---

## Tax exemption

Set **`tax_exempt`** on a customer (e.g. a reseller with a resale certificate) and
no sales tax is calculated on their transactions. For a customer who is itself a
marketplace, there's a `marketplace` flag — but note it's currently a label only
and doesn't change tax posting (see
[The contacts table](./01-the-contacts-table.md#tax)).

---

## Ship-to addresses (branches)

A customer keeps **one billing address on the record** and **any number of ship-to
addresses** alongside it (stored as `address_s` entries). Add a ship-to per branch
or delivery point from the customer's address tab; pick the right one at order
time. You don't create a separate contact per delivery location.

(There is also a distinct **Branch** contact role, `ctype_b`, for modeling store
locations — different from a customer's multiple ship-to addresses.)

---

## Customer pricing

A customer can be assigned a **price sheet** (`price_sheet`) — a price level that
overrides default item pricing for that customer. Pricing is **sheet-based**:
assign the customer to a sheet and they get those prices. It is *not* a dynamic
volume/date tier engine — it's a static level you manage in the
[inventory pricing](../03-inventory/01-inventory-types.md) setup and attach here.

---

## Aged AR and history

- **Aged receivables** for a customer come from the open sales invoices and credit
  memos (journals 12 and 13), bucketed by due date. The status panel on the
  customer (and the Aged Receivables dashboard) shows current / 1-30 / 31-60 /
  61-90 / 91-120 / over-120 / total.
- **History grids** on the customer's History tab show their quotes (jID 9), sales
  orders (jID 10), and invoices (jID 12), plus a payment-history view — the full
  trail for that account in one place.

---

## Related

- [The contacts table](./01-the-contacts-table.md) — the underlying fields
- [Vendors](./03-vendors.md) — the buying-side mirror
- [Quote → SO → Invoice → Payment → Deposit](../../03-daily-workflows/01-quote-so-invoice-payment-deposit.md)
