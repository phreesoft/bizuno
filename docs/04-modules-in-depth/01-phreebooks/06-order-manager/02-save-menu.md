---
title: The Save Menu — Save, Save As & Move To
category: PhreeBooks
order: 62
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# The Save Menu — Save, Save As & Move To

The **Save** button on the order entry form is a split menu, and its choices
change with the journal you're in and the state of the record. This page
documents every item: what it does, when it appears, and what you end up with.

There are three families of action:

1. **Save** and the **Save & …** shortcuts — commit this record, then optionally
   do one follow-on step (print it, take a payment, fill it into its invoice).
2. **Save As ▸** — commit this record *and create a brand-new copy* in another
   journal. The original stays put.
3. **Move To ▸** — *relocate this record* into another journal. There's only one
   record afterward, now living in the target journal.

> **Read-only users see no menu.** The entire Save menu is built only for users
> with edit rights (security level 2+) on the journal. At level 1 (read-only) the
> form opens without it.

---

## How the menu is assembled

Bizuno builds the menu in `renderMenuSave()` based on the current `journal_id`
and whether the record has been saved yet. The shape falls into four cases:

| Journal group                         | What the menu offers                                  |
|---------------------------------------|-------------------------------------------------------|
| General Journal (`jID=2`)             | Save As → New only                                    |
| Trading journals (`jID` 3/4/6 vendor, 9/10/12 customer) | The full set below          |
| Payment journals (`jID` 20, 22)       | Save & Print only                                     |
| Everything else                       | Plain Save                                            |

The rest of this page covers the trading-journal menu, since that's where the
richness lives.

---

## Save

Commits the record. Auto-assigns the reference number if you left it blank, posts
to the General Ledger (for journals that post), and returns you to the grid.
Nothing else happens.

---

## The Save & … shortcuts

These commit the record and then immediately launch the *next* logical document,
prefilled. Each one appears only for the journals where it makes sense.

### Save & Print

> Appears on: **Purchase (`jID=6`)** — and on the payment journals (20/22).

Saves, then opens the journal's default PhreeForm document (the bill/check) in the
print viewer. Internally this sets `xChild=print`.

### Save & Prepay

> Appears on: **orders only — Purchase Order (`jID=4`) and Sales Order
> (`jID=10`)**.

Saves the order, then records a **prepayment/deposit** against it and opens the
matching payment journal prefilled:

- PO (`jID=4`) → **Pay Bills (`jID=20`)** — pay a deposit to the vendor.
- SO (`jID=10`) → **Cash Receipt (`jID=18`)** — take a deposit from the customer.

This is how you capture money *before* the order is filled into an invoice.

### Save & Pay

> Appears on: **invoices/bills — Purchase (`jID=6`) and Sales (`jID=12`)**.

Saves the invoice/bill, then opens the payment journal prefilled to settle it:

- Purchase (`jID=6`) → **Pay Bills (`jID=20`)**.
- Sales (`jID=12`) → **Cash Receipt (`jID=18`)**.

(The credit journals follow the same wiring: Vendor Credit `jID=7` → Vendor Refund
`jID=17`; Credit Memo `jID=13` → Customer Refund `jID=22`.)

### Save & Fill

> Appears on: **orders only — PO (`jID=4`) and SO (`jID=10`)**.

Saves the order, then opens its **invoice** prefilled from the order lines so you
can receive/ship and bill in one motion:

- PO (`jID=4`) → **Purchase / bill (`jID=6`)**.
- SO (`jID=10`) → **Sales / invoice (`jID=12`)**.

This is the keyboard-light path for "the goods arrived, turn the order into a
bill." Internally it sets `xAction=invoice`.

---

## Save As ▸ — make a copy in another journal

**Save As** posts the current record *and creates a new, independent record* in
the journal you pick. The original is untouched. Use it when you want to keep the
quote *and* spin off an order from it, for example.

The three targets mirror the current side (vendor vs. customer):

| Menu item | Vendor side target        | Customer side target     |
|-----------|---------------------------|--------------------------|
| Quote     | Request For Quote (`jID=3`)  | Sales Quote (`jID=9`)  |
| Order     | Purchase Order (`jID=4`)     | Sales Order (`jID=10`) |
| Invoice   | Purchase / bill (`jID=6`)    | Sales / invoice (`jID=12`) |

What happens on a Save As:

- The record `id` and every line-item id are reset to 0 → a **new** record is
  written; the totals' stored ids are cleared too.
- The reference number is **blanked** so the *target* journal assigns its own next
  number.
- Saving into a Sales invoice (`jID=12`) forces the **Waiting/unshipped** flag on;
  saving into a quote or order clears the waiting flag.

On the **General Journal (`jID=2`)** the only Save-As option is **New**, which
saves the current entry and immediately starts a fresh blank one.

---

## Move To ▸ — relocate this record

**Move To** changes *this same record's* journal — it does not create a copy.
After a Move To there is exactly one record, now in the target journal, with a
freshly assigned reference number.

The targets are the same three (Quote / Order / Invoice, side-appropriate), with
two UI rules:

- It's **disabled until the record is saved** (you can't move something that
  doesn't exist yet).
- The **current journal's own target is disabled** — you can't "move" a PO to a
  PO.

---

## What blocks Save As / Move To

Both Save As and Move To are refused (with an alert) when the record is *linked*
or *final*, because copying/moving it would orphan the link:

- A **partial fill is in progress** — some line still carries an open balance.
- The record is **tied to another order** (`so_po_ref_id` set) — e.g. an invoice
  filled from an order.
- The record is part of a **recurring series** (`recur_id` / `recur_frequency`
  set).
- The record is **closed** (except on the General Journal).

In any of these cases, finish or unlink first.

---

## Per-journal menu matrix

What the Save menu offers, at a glance. ✓ = shown, — = not shown.

| Menu item       | RFQ 3 | PO 4 | Purch 6 | Quote 9 | SO 10 | Sales 12 | GenJrnl 2 | Pay 20/22 |
|-----------------|:-----:|:----:|:-------:|:-------:|:-----:|:--------:|:---------:|:---------:|
| Save            |   ✓   |  ✓   |   ✓     |   ✓     |  ✓    |   ✓      |    ✓      |    ✓      |
| Save & Print    |   —   |  —   |   ✓     |   —     |  —    |   —      |    —      |    ✓      |
| Save & Prepay   |   —   |  ✓   |   —     |   —     |  ✓    |   —      |    —      |    —      |
| Save & Pay      |   —   |  —   |   ✓     |   —     |  —    |   ✓      |    —      |    —      |
| Save & Fill     |   —   |  ✓   |   —     |   —     |  ✓    |   —      |    —      |    —      |
| Save As ▸       |   ✓   |  ✓   |   ✓     |   ✓     |  ✓    |   ✓      |  ✓ (New)  |    —      |
| Move To ▸       |   ✓   |  ✓   |   ✓     |   ✓     |  ✓    |   ✓      |    —      |    —      |

> All Save-As/Move-To items additionally require security level 3 on the target.

---

## Related

- [Order entry fields](./01-order-entry-fields.md)
- [The action column](./03-action-column.md) — many of the same conversions are
  also available as one-click row actions in the grid.
- [Journals (the journal_id reference)](../02-journals.md)
