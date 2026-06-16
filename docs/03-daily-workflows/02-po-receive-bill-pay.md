---
title: PO → Receive → Vendor Invoice → Pay Bill
category: Daily Workflows
order: 2
status: draft
audience: [bookkeeper]
last-updated: 2026-06-16
---

# PO → Receive → Vendor Invoice → Pay Bill

The vendor-side mirror of the [sales cycle](./01-quote-so-invoice-payment-deposit.md):
order goods, receive and bill them, pay the bill. It's the same shape as selling
— a no-GL commitment, then the entry that moves the GL, then the cash entry — run
in reverse.

One thing to get straight up front, because it differs from some other systems:
**in Bizuno, "receiving" and "entering the vendor invoice" are the same step.**
There is no separate goods-received-not-invoiced (GRNI) accrual. The Purchase
entry (jID=6) brings the stock onto the shelf *and* records the payable, in one
posting.

---

## The cycle at a glance

```
   ┌──────────────┐  convert   ┌────────────────────┐   apply    ┌──────────────┐
   │ Purchase     │ ─────────▶ │ Purchase /         │ payment    │ Pay Bills    │
   │ Order        │            │ Vendor Invoice     │ ─────────▶ │              │
   │ jID=4        │            │ jID=6              │            │ jID=20       │
   │ (no GL)      │            │ (receive + book AP)│            │ (pay the AP) │
   └──────────────┘            └────────────────────┘            └──────────────┘
```

Three GL-relevant events, not four — the "receive" in the page title lives
*inside* the Purchase entry.

---

## Step 1 — Purchase Order (jID=4)

**What it is.** A commitment to buy — your order to the vendor. *No General
Ledger impact.* You don't owe anything yet and nothing has arrived.

**What it does track.** Saving a PO increments the item's **`qty_po`**
("quantity on order"), so the inventory reorder math knows more is coming and
you don't double-order. Stock-on-hand doesn't change.

**Where in the UI.** *Vendors → Purchase Orders → New*.

> **It's jID=4, not jID=7.** If you're reading report SQL or custom code:
> Purchase Order is journal **4**. jID=7 is a *Vendor Credit*. (An earlier draft
> of this page had that wrong.) See
> [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md).

---

## Step 2 — Purchase / Vendor Invoice (jID=6)

This is the entry that does the work: it **receives the goods and books the
payable at the same time.** When you enter the vendor's bill (converting from the
PO, or from scratch), Bizuno:

- **Adds the received quantity to `qty_stock`** and lays down a cost layer in
  `inventory_history` at the purchase price — this is the "receive."
- **Books the payable** to Accounts Payable.

**GL impact (a stocked item):**

| Line              | Debit                       | Credit              |
|-------------------|-----------------------------|---------------------|
| Inventory receipt | Inventory (item's `gl_inv`) | —                   |
| Payable           | —                           | Accounts Payable    |

A $1,000 purchase of stock posts:

```
  Dr  Inventory              1,000.00
        Cr  Accounts Payable       1,000.00
```

For a **non-stock or expense** purchase (a service, a utility bill, office
supplies), the debit goes to the expense/asset account on the line instead of
inventory — but the credit is still AP:

```
  Dr  Office Supplies Expense  240.00
        Cr  Accounts Payable        240.00
```

**Where in the UI.** *Vendors → Purchases (Invoice) → New*, or convert from the
PO.

> **Why there's no "accrued receipts" line.** Some ERPs receive stock into a GRNI
> holding account first (Dr Inventory, Cr Accrued-Receipts), then clear it when
> the invoice arrives (Dr Accrued-Receipts, Cr AP). **Bizuno does not** — it has
> no GRNI account and posts straight to AP. If you're coming from a system that
> does two-step receiving, don't go looking for the accrual account; the single
> Purchase entry *is* the receipt.

---

## Step 3 — Pay Bills (jID=20)

**What it is.** You pay the vendor. Recorded against their open bill(s).

**GL impact:**

| Line       | Debit              | Credit         |
|------------|--------------------|----------------|
| Settle AP  | Accounts Payable   | —              |
| Cash out   | —                  | Bank account   |

```
  Dr  Accounts Payable       1,000.00
        Cr  Checking Account       1,000.00
```

**Where in the UI.** *Vendors → Pay Bills*. The screen is built for volume:
filter to bills due by a date and **pay many at once**, and it supports **ACH**
for vendors who have bank details on file (the `ach_*` fields on the contact).

---

## Common variations

### Receiving without a PO

Plenty of bills arrive with no PO behind them — a walk-in purchase, a recurring
utility, a one-off. Just enter the Purchase (jID=6) directly. The GL is identical;
you simply skip Step 1.

### Immediate cash purchase

Paid at the counter, no payable in between? Use **Point of Purchase** (jID=21),
which combines the purchase and the payment:

```
  Dr  Inventory (or Expense)   240.00
        Cr  Cash                     240.00
```

No AP is created and there's no Pay Bills step.

### Drop-ship

For goods shipped straight from your vendor to your customer, a drop-ship PO can
be created from the sales order rather than received into your own stock — the
inventory never makes a physical hop through you. It's a deliberate action from
the order, not the default path.

### Vendor credit / return to vendor (jID=7)

Returning goods to a supplier, or taking a credit from them, is a **Vendor
Credit** (jID=7) — the mirror of the Purchase. It pulls the returned stock back
*out* of inventory and reduces AP:

```
  Dr  Accounts Payable         150.00
        Cr  Inventory                150.00
```

### A word on "three-way match"

Bizuno **links** a PO to its Purchase (so converting carries the lines over) and
you can adjust received quantities on the Purchase, but it does **not** perform an
automated three-way match — it won't flag a quantity or price discrepancy between
PO, receipt, and invoice for you. Reconciling "what we ordered vs. what we got vs.
what they billed" is a manual review.

---

## Common gotchas

- **A PO doesn't change your books or your stock-on-hand.** Only `qty_po` moves.
  If you're waiting for the inventory asset to go up, that happens at the Purchase
  (Step 2), not the PO.
- **Receiving *is* invoicing here.** There's no separate receipt to post — if the
  goods are on the shelf in Bizuno, you've already booked the payable. Don't look
  for a GRNI balance to clear at month-end; there isn't one.
- **Pay Bills settles AP; it is not where the expense is recognized.** The cost
  hit the books at the Purchase. Paying the bill just moves money from AP to cash.

---

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md) — the customer-side mirror of this cycle
- [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md) — jIDs 4, 6, 7, 20, 21 in context
- [Inventory types and COGS](../02-core-concepts/05-inventory-types-and-cogs.md) — which purchases land in inventory vs. expense
- [Returns and credit memos](./04-returns-and-credit-memos.md) — the vendor-credit side in full
