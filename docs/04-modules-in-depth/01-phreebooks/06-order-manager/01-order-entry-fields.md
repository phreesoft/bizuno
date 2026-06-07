---
title: Order Entry — Field Reference
category: PhreeBooks
order: 61
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Order Entry — Field Reference

Every field on the sale/purchase entry form, organized by the panel it lives in.
The form is the same for all eight trading journals; where a field's label,
visibility, or behavior changes by journal, that's called out inline.

Fields are stored on the `journal_main` table (header) and `journal_item` table
(line items). The bill-to block uses the `_b` suffix, the ship-to block uses
`_s`.

> **Reading the journal-dependent labels.** Several fields are *relabeled* per
> journal rather than being different fields. The clearest example is the date
> field (`terminal_date`) — same column, four different labels. Watch for the
> "label varies" notes.

---

## Header / identity panel

### Reference number (`invoice_num`)

The document number. Its **label changes per journal** (`invoice_num_{jID}`):
"Quote #", "PO #", "Invoice #", and so on.

- **Leave it blank to auto-assign.** Bizuno fills the next number in sequence for
  that journal when you save. The on-screen tip says exactly this.
- On a **Purchase (`jID=6`)** you may save *without* a reference number **only if**
  the **Waiting** box is checked (goods received, vendor invoice not arrived yet).
  Otherwise a reference is required.

### Vendor/Customer document number (`purch_order_id`)

The *other party's* number for this transaction — your customer's PO number on a
sale, or (on the buying side) a cross-reference. Free text. Hidden on journals
that don't trade with a contact (General Journal, assemblies, adjustments,
transfers, and the payment journals).

### Store (`store_id`)

The store/location this transaction belongs to in a multi-store install. Defaults
to the user's home store. On a single-store install it's effectively fixed at 0.
On line-item screens that read stock, changing the store re-points the SKU lookup
at that store's inventory.

### Post date (`post_date`)

The transaction date. Drives the fiscal **period** the entry posts into (for the
journals that post). For quotes and orders, which don't post to the GL, it's the
document date.

### The journal date field (`terminal_date`) — label varies

One physical field, repurposed by journal:

| Journal                         | Label shown      | Meaning                         |
|---------------------------------|------------------|---------------------------------|
| Quotes (`jID` 3, 9)             | Expiration Date  | When the quote lapses           |
| Orders (`jID` 4, 10)            | Expected Ship    | Planned ship/receipt date       |
| Sales invoice (`jID=12`)        | Ship Date        | When goods shipped              |
| All others (e.g. bill `jID=6`)  | Due Date         | When payment is due             |

### Terms (`terms` / `terms_text` / `terms_edit`)

Payment terms for the transaction.

- **`terms_text`** is the human-readable display ("Net 30", "Due on receipt") and
  is read-only.
- The **gear / settings icon** (`terms_edit`) opens the terms editor dialog so you
  can override terms for *this* contact without leaving the form.
- The underlying `terms` value defaults from the contact record.

### Sales/Purchase rep (`rep_id`)

The responsible rep. The dropdown is populated from the **purchasing** role list
for vendor journals (`jID` 3, 4, 6, 7) and the **sales** role list otherwise.
Also used by the manager's per-rep filter and by user-restriction rules (a
restricted user only sees their own rep's rows).

### Currency (`currency` / `currency_rate`)

Shown only when the install has **more than one currency** enabled. Selecting a
currency recalculates the totals panel; the exchange `currency_rate` is displayed
read-only (it comes from the currency table for the post date).

---

## Contact block — Bill-to (`_b`) and Ship-to (`_s`)

The contact lookup at the top of each block searches the Contacts table. Picking
a contact fills the name and address fields and pulls that contact's **default GL
account** and **default tax authority** into the form (used as the line-item
defaults below).

Per block, the address fields are: primary name, contact name, address 1/2, city,
state, postal code, country, telephone, email (`primary_name_b`, `address1_b`, …
and the `_s` equivalents).

- **Bill-to (`_b`)** is the party you invoice / pay. On the trading journals
  (3, 4, 6, 7, 9, 10, 12, 13) a bill-to contact is **required** — saving without
  one is rejected.
- **Ship-to (`_s`)** is where goods go. The "copy from billing" control fills it
  from the bill-to; editing the shipping address afterward detaches it
  (`address_id_s` clears) so saving creates a *new* shipping address rather than
  overwriting the contact's stored one.

---

## Line-item grid

The editable grid of what's being bought or sold. One row per SKU/charge.

| Column            | Field              | Notes                                                          |
|-------------------|--------------------|----------------------------------------------------------------|
| SKU               | `sku`              | Combogrid lookup against inventory for the selected store. Selecting a SKU auto-fills description and the item GL account. |
| Description       | `description`      | Defaults from the item's short description; freely editable.    |
| GL account        | `gl_acct_id`       | Defaults to the contact's GL account, then the item's. The dropdown excludes inactive accounts. |
| Quantity          | `qty`              | The order/sell quantity. The lookup surfaces stock context — on hand (`qty_stock`), on SO (`qty_so`), on PO (`qty_po`), this store vs. all stores (`qty_store` / `qty_all`). |
| Price             | `price`            | Unit price; sale price defaults from the item, purchase from last cost. |
| Tax               | `tax`              | The line's tax authority; defaults from the contact's tax id.  |
| Total             | (calculated)       | Qty × price, less line discount, recomputed live.              |

Adding a row, editing inline, and saving the grid are handled by the embedded
edit-datagrid; the grid is serialized into the hidden `item_array` field on
submit.

---

## Totals panel

The totals area is **assembled from stackable "totals" plugins**, and *which*
plugins appear is configured per journal (Settings → the journal's totals list).
That's why the panel differs between, say, a sales order and a bank payment. The
common methods:

| Method        | Shows as            | Purpose                                            |
|---------------|---------------------|----------------------------------------------------|
| `subtotal`    | Subtotal            | Sum of line items                                  |
| `discount` / `disc_item` | Discount | Whole-order or per-item discount                  |
| `shipping`    | Freight             | Shipping/handling charge                           |
| `tax_item` / `tax_order` | Sales Tax | Tax computed per line or on the order             |
| `tax_other`   | Other Tax           | Secondary tax authority                            |
| `fee_order`   | Fee                 | Flat order fee/surcharge                           |
| `total`       | Total               | Grand total (`total_amount`)                       |
| `total_pmt`   | Payments / Applied  | Payments already applied (on invoices/bills)       |
| `balance`     | Balance Due         | Total minus payments                               |

Each plugin contributes both the on-screen number and the matching General Ledger
distribution when the entry posts. The totals recompute live as you edit lines
(`totalUpdate()`), and the saved total is validated against the recomputed total
on post — a mismatch is trapped rather than silently saved.

---

## Flags, recurrence, and footer

### Waiting (`waiting`)

Marks an order as confirmed-but-not-yet-invoiced (sales side) or
received-but-not-yet-billed (purchase side). On a Purchase you can save without a
reference number *if* Waiting is checked. See the manager's
[waiting view](./README.md#the-waiting-view) and the
[action column toggles](./03-action-column.md).

### Closed (`closed`)

The terminal state for the record: a quote/order that's been fully converted, or
an invoice/bill that's fully paid. Set by the system as downstream documents are
created; you generally don't toggle it by hand. A **closed order cannot be
Saved-As or Moved-To** (the menu blocks it).

### Recurring (`recur` toolbar button + `recur_id` / `recur_frequency`)

The **Recur** toolbar button opens the recurrence dialog. On save, Bizuno can
post the entry multiple times on a schedule — Weekly, Bi-weekly, Monthly, or
Quarterly — advancing the post date, due date, and reference number for each copy.
All copies share one `recur_id` so they can be managed (or rolled forward) as a
set. A record that is part of a recurring series **cannot be Saved-As / Moved-To**
(it's a linked record).

### Notes

A free-text notes panel. On a saved record the inline **save** icon
(`notes_save`) writes just the note without re-posting the whole entry.

### Attachments

The attachment area stores files against the record (quotes, signed POs, vendor
invoices). Attachments follow the record when an order is filled into its
invoice. A paperclip icon appears in the grid action column when a row has files.

---

## Hidden / system fields

These exist on the form but aren't user-facing; listed so the reference is
complete:

- `override_user` / `override_pass` — credentials captured when an action needs to
  override a locked period or a security gate.
- `item_array` — the serialized line-item grid, posted with the form.
- `xAction` / `xChild` — the action selectors the Save menu sets to tell the
  server what post-save step to run (see [The Save menu](./02-save-menu.md)).
- `journal_msg` — the status banner Bizuno renders at the top of the form when it
  has something to tell you about the record.

---

## Related

- [The Save menu](./02-save-menu.md)
- [The action column](./03-action-column.md)
- [Journals (the journal_id reference)](../02-journals.md)
