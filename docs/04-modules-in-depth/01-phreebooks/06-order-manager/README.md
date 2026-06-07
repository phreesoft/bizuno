---
title: The Sale / Purchase Manager
category: PhreeBooks
order: 6
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# The Sale / Purchase Manager

One screen drives every order in Bizuno — purchase quotes, purchase orders,
bills, sales quotes, sales orders, and invoices all share the **same manager
grid and the same entry form**. What changes from one to the next is the *journal*
the form is pointed at (the `journal_id`), and Bizuno reshapes the toolbar, the
Save menu, the row-action icons, and the field labels accordingly.

That single fact explains almost everything that confuses new users: "why does
the Save button have different choices on a PO than on an invoice?", "why can I
set a ship date here but not there?", "why did the action column lose its Payment
icon?". The answer is always *the journal you're in*.

This sub-section is the field-and-option reference for that screen. It is
deliberately exhaustive — if it's a button, a column, a menu item, or a field on
the order manager, it's documented here.

> **New to the journals themselves?** Read
> [The journal_id taxonomy](../../../02-core-concepts/02-journal-id-taxonomy.md)
> first, and the per-journal
> [Journals reference](../02-journals.md). This section assumes you know what a
> journal *is* and focuses on the manager UI built on top of them.

---

## The journals this manager serves

The order manager handles the eight "trading" journals plus the two payment
journals reached from them. Each side mirrors the other:

| Stage         | Vendor side (purchasing)          | Customer side (selling)        |
|---------------|-----------------------------------|--------------------------------|
| Quote         | Request For Quote — `jID=3`       | Sales Quote — `jID=9`          |
| Order         | Purchase Order — `jID=4`          | Sales Order — `jID=10`         |
| Invoice       | Purchase (bill) — `jID=6`         | Sales (invoice) — `jID=12`     |
| Credit        | Vendor Credit — `jID=7`           | Credit Memo — `jID=13`         |
| Payment       | Pay Bills — `jID=20`              | Cash Receipt — `jID=18`        |
| Refund        | Vendor Refund — `jID=17`          | Customer Refund — `jID=22`     |

The General Journal (`jID=2`) uses a stripped-down version of the same screen and
is noted where it differs.

> The `type` flag you'll see referenced in the pages below is `v` for the vendor
> journals (3, 4, 6, 7) and `c` for the customer journals (9, 10, 12, 13). Bizuno
> uses it to decide which side's labels, role dropdowns, and Save-As targets to
> show.

---

## Anatomy of the screen

```
 ┌──────────────────────────────────────────────────────────────────┐
 │  TOOLBAR:  [ Save ▾ ]  [ Recur ]  [ New ]  [ Delete ]             │  ← per-journal
 ├──────────────────────────────────────────────────────────────────┤
 │  HEADER:   Ref #   PO #   Date   Terms   Rep   Store   Currency    │
 │  CONTACT:  Bill-to (_b)              Ship-to (_s)                   │
 ├──────────────────────────────────────────────────────────────────┤
 │  LINE ITEMS:  SKU │ Description │ GL acct │ Qty │ Price │ Tax │ Tot │
 │               ...                                                   │
 ├──────────────────────────────────────────────────────────────────┤
 │  TOTALS:   Subtotal · Discount · Freight · Tax · Total · Balance   │
 │  NOTES / ATTACHMENTS                                                │
 └──────────────────────────────────────────────────────────────────┘
```

Two distinct views share that layout:

- **The grid (list) view** — `phreebooks/main/manager`. Rows are the
  `journal_main` records for the selected journal. The first column is the
  **action column**, whose icons vary by journal and by row state — see
  [The action column](./03-action-column.md).
- **The entry (edit) form** — `phreebooks/main/edit`. Every header field, the
  line-item grid, and the totals panel are documented in
  [Order entry fields](./01-order-entry-fields.md). Its Save button is a menu
  whose contents depend on the journal — see [The Save menu](./02-save-menu.md).

---

## Order journals live *outside* the period

A subtle but important behavior: quotes and orders (`jID` 3, 4, 9, 10) are listed
**independent of the accounting period**. The grid for those journals drops the
period filter entirely (`managerRowsOrder()`), because an order written in
January may not become a posted invoice until March. Only when an order is
*filled* into its invoice (`jID` 6 / 12) does it land in a fiscal period and hit
the General Ledger.

Quotes and orders therefore carry **no GL impact** while they sit in the manager.
They are commitments, not postings.

---

## The "waiting" view

Orders and invoices carry a `waiting` flag that the manager surfaces two ways:

- A **status color** in the grid (see the footnote legend at the bottom of the
  grid): a confirmed order shows yellow-green; an unshipped sale / unreceived
  bill shows the `journal-waiting` highlight.
- A dedicated **`&waiting=1` filter** that narrows the grid to received-but-not-
  yet-invoiced (or shipped-but-not-yet-invoiced) records, plus those with a
  payment method attached.

What "waiting" *means* depends on the journal: on a Purchase (`jID=6`) it's goods
received but not yet billed; on a Sale (`jID=12`) it's an order confirmed but not
yet shipped/invoiced. The [action column](./03-action-column.md) page documents
the per-journal toggles that flip this flag.

---

## Pages in this sub-section

| #  | Page                                                      | Covers                                            |
|----|-----------------------------------------------------------|---------------------------------------------------|
| 01 | [Order entry fields](./01-order-entry-fields.md)          | Every field on the entry form, panel by panel     |
| 02 | [The Save menu](./02-save-menu.md)                        | Save, Save & …, Save As ▸, Move To ▸              |
| 03 | [The action column](./03-action-column.md)                | Every row-action icon, as a journal × action grid  |

---

## Related

- [Journals (the journal_id reference)](../02-journals.md)
- [The journal_id taxonomy](../../../02-core-concepts/02-journal-id-taxonomy.md)
- [Quote → SO → Invoice → Payment → Deposit](../../../03-daily-workflows/01-quote-so-invoice-payment-deposit.md)
- [PO → Receive → Bill → Pay](../../../03-daily-workflows/02-po-receive-bill-pay.md)
