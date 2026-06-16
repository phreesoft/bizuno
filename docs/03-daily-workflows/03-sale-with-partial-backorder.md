---
title: Sale with Partial Backorder
category: Daily Workflows
order: 3
status: draft
audience: [bookkeeper]
last-updated: 2026-06-16
---

# Sale with Partial Backorder

You have 3 in stock, the customer ordered 5. You want to ship the 3 now, bill for
those 3, and keep the other 2 on order until stock arrives. Small accounting apps
usually punt on this ("just adjust it manually"). Bizuno handles it natively
through the Sales Order, and once you see the mechanism it's straightforward.

This builds on the [main sales cycle](./01-quote-so-invoice-payment-deposit.md) —
read that first if the quote→SO→invoice flow isn't familiar.

---

## How partial fulfillment is tracked

The key is the link between a **Sales Order** (jID=10) and the **invoices**
(jID=12) drawn from it. When you invoice part of an SO:

- Each invoice line carries an **`item_ref_id`** pointing back to the SO line it
  fulfills.
- The SO line's **balance** = ordered quantity − sum of everything invoiced
  against it so far.
- The SO **stays open** as long as any line still has a balance to ship. It
  closes automatically once every line is fully invoiced (balance reaches zero),
  or when you close it by hand.

So the SO is the running record of "what's still owed to the customer," and each
partial invoice chips away at it.

---

## The workflow

### 1. Enter the Sales Order for the full quantity

Order all 5 on the SO (jID=10). No GL yet — an SO doesn't post (see the
[main cycle](./01-quote-so-invoice-payment-deposit.md#step-2--sales-order-jid10)).
The 5 are now committed against the item.

### 2. Invoice the quantity you can ship

Convert the SO to an invoice (jID=12). Bizuno seeds the invoice from the SO lines
but sets the **quantity to ship to 0**, showing the outstanding balance alongside
— you fill in what's actually going out. Enter **3**.

This is the whole trick: you're not editing the order down to 3, you're saying
"ship 3 of the 5 against this line right now."

### 3. The partial invoice posts — for the shipped quantity only

Only the 3 units you entered hit the GL. Revenue, tax, AR, COGS, and inventory
relief are all computed on the shipped quantity, exactly as in a normal sale —
just for 3, not 5:

```
  Dr  Accounts Receivable    (3 units' price + tax)
        Cr  Sales Revenue          (3 units' price)
        Cr  Sales Tax Collected    (tax on 3)
  Dr  Cost of Goods Sold     (3 units' cost)
        Cr  Inventory              (3 units' cost)
```

The 2 backordered units post **nothing** — they're not shipped, so there's no
revenue and no inventory relief for them. The SO stays open with a balance of 2
on that line.

### 4. Fulfill the rest when stock arrives

When the 2 come back into stock, open the still-open SO and invoice again — enter
2 this time. A second invoice posts for those 2, the line's balance reaches zero,
and the SO closes. The customer ends up with two invoices for one order, each
covering what actually shipped that day.

---

## Finding what's still owed

- **An individual order:** open the SO — each line shows its remaining balance, so
  you can see at a glance what's left to ship.
- **What's waiting to go out:** the **Unshipped Orders** dashboard widget surfaces
  invoices still flagged as not-yet-shipped. (Note it tracks unshipped *invoices*,
  not the SO backorder balance — it's the shipping queue, not the open-order list.)

---

## Restocking the backorder

Here's where to set expectations honestly:

- **There is no "auto-create a PO from this backorder" button.** Bizuno does not
  generate a purchase order off a short sales order line.
- **What it does give you is a reorder view.** The inventory **reorder/stock-status
  dashboard** lists items where stock has fallen below the reorder math
  (on-hand < minimum + committed-to-orders − already-on-PO), grouped by vendor —
  so you can see *what* to reorder and from *whom*. It's a read-only guide; you
  then create the [Purchase Order](./02-po-receive-bill-pay.md) yourself.
- **Drop-ship is the exception.** If the item ships straight from your vendor, you
  can create a drop-ship PO directly from the order — see
  [PO → Receive → Vendor Invoice → Pay Bill](./02-po-receive-bill-pay.md#drop-ship).

So the backorder *restock* loop is: ship what you have → the reorder dashboard
flags the shortfall → you raise the PO → receive it → invoice the remainder of the
open SO.

---

## Common gotchas

- **Don't reduce the SO to what you can ship.** Editing the order down to 3 loses
  the record that the customer wanted 5. Leave the SO at 5 and invoice partials
  against it — the open balance *is* the backorder.
- **The backordered units carry no cost or revenue until shipped.** If a margin
  report looks light mid-backorder, that's correct — only delivered goods have
  posted.
- **Closing an SO with a balance abandons the backorder.** Manually closing an SO
  that still has unshipped quantity tells Bizuno you're not going to ship the rest.
  Only do that when you genuinely mean "cancel the remainder."
- **No automatic PO.** If you're hunting for where backorders turn into purchase
  orders on their own — they don't. Use the reorder dashboard and raise the PO.

---

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md) — the full sales cycle this extends
- [PO → Receive → Vendor Invoice → Pay Bill](./02-po-receive-bill-pay.md) — raising the PO to restock
- [Inventory types and COGS](../02-core-concepts/05-inventory-types-and-cogs.md) — why only shipped units relieve inventory
