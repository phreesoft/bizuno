---
title: Returns and Credit Memos
category: Daily Workflows
order: 4
status: draft
audience: [bookkeeper]
last-updated: 2026-06-16
---

# Returns and Credit Memos

Returns are where small accounting apps quietly let users break the books —
because a return touches three things at once (AR, inventory, *and* sales tax),
and getting all three right by hand is easy to botch. Bizuno separates the
**tracking** of a return (the RMA) from the **money** (the credit memo or
refund), so each is handled properly.

This is the reverse of the [sales cycle](./01-quote-so-invoice-payment-deposit.md).

---

## Two distinct things: the RMA and the credit

It helps to keep these separate in your head:

1. **The Return (RMA)** — an operational record: the customer wants to send goods
   back, you receive and inspect them, you close it out. This is logistics and
   tracking. It does not, by itself, post to the GL.
2. **The Credit Memo or Refund** — the financial entry that reverses the sale:
   gives the customer credit (or cash) back, returns the goods to inventory, and
   reverses the COGS and tax. *This* is what hits the books.

You can issue a credit memo without ever opening a formal RMA (common for a quick
"we'll credit you for that"), and a busy operation can track the RMA lifecycle
before deciding the financial disposition. The next two sections cover each.

---

## The Returns module (the RMA)

Bizuno has a structured returns tracker, not just a credit-memo shortcut. A
return record carries:

- An **RMA reference number** (auto-assigned).
- A **status** and a **return code** (the reason — defect, wrong item, warranty,
  etc.), both drawn from configurable option lists.
- A **preventable** flag — was this return avoidable?
- A link to the **original invoice**.
- A **receiving** tab (date, who received it, carrier, tracking) and a **close**
  tab (closed date, who closed it, disposition notes).

That gives you a **receive → inspect → close** lifecycle: log the RMA, record the
goods coming back in, inspect, then close with a disposition. The return data is
stored as metadata against the transaction, and a **returns-by-SKU dashboard**
summarizes what's coming back most often.

---

## The credit memo (jID=13) — the GL

A **customer Credit Memo** is the financial reverse of the sale. For goods coming
back into stock, it mirrors the original invoice leg-for-leg:

```
  Dr  Sales Revenue          (price of returned goods)
  Dr  Sales Tax Collected    (tax on the return)
        Cr  Accounts Receivable    (total credit to the customer)
  Dr  Inventory              (goods back on the shelf, at cost)
        Cr  Cost of Goods Sold     (reverse the COGS)
```

Two things this gets right that hand-entry often misses:

- **Inventory comes back at cost** — the returned units are re-added to
  `inventory_history`, so your stock value and quantity are correct again.
- **Sales tax is reversed too** — the tax you collected and owe is reduced, so
  your tax liability stays accurate.

The credit memo reduces what the customer owes. If it exceeds their open balance,
the difference becomes a **credit on file** that follows the customer forward —
apply it to their next invoice. *Where in the UI:* convert from the original
invoice, or *Customers → Credit Memos → New*.

---

## Credit memo vs. cash refund

| You want to…                                  | Use                          | Effect                                            |
|-----------------------------------------------|------------------------------|---------------------------------------------------|
| Give credit against future purchases          | **Credit Memo** (jID=13)     | Reverses the sale; leaves a credit on the account |
| Give the money back                           | **Customer Refund** (jID=22) | Pays cash out and clears the customer's credit    |

The Credit Memo handles the *goods and revenue* reversal. The Refund handles
*cash leaving*:

```
  Dr  Accounts Receivable    (clears the customer's credit balance)
        Cr  Checking Account       (cash paid back)
```

A return that ends in cash back is therefore often **two entries**: a credit memo
to reverse the sale (and restock the goods), then a refund to pay out the
resulting credit. A return that ends in store credit is just the credit memo.

---

## Vendor returns (jID=7)

Returning goods to a *supplier* is the mirror on the AP side — a **Vendor Credit**
(jID=7). Here the goods leave your inventory (back to the vendor) and your payable
shrinks:

```
  Dr  Accounts Payable       (reduce what you owe the vendor)
        Cr  Inventory              (goods leaving your stock, at cost)
```

It's the reverse of a [Purchase](./02-po-receive-bill-pay.md#step-2--purchase--vendor-invoice-jid6),
the same way the customer credit memo is the reverse of a sale.

---

## A note on the Quality tie-in

Returns capture a **preventable** flag and a **return code**, and the
returns-by-SKU dashboard surfaces what's coming back — useful raw material for
quality analysis. But be clear on the limit: **returns do not automatically open
[Quality CA/PA tickets](../04-modules-in-depth/05-quality/01-ca-pa-tickets.md).**
There's no built-in feed from a return into the Quality module. If a return
pattern warrants a corrective-action ticket, someone raises it by hand. Treat the
return codes and the dashboard as the signal, not as an automated pipeline.

---

## Common gotchas

- **Don't "fix" a return by deleting the original invoice.** That destroys the
  audit trail and unwinds tax and COGS incorrectly. Issue a credit memo — it
  reverses everything properly and both documents survive.
- **A credit on file is not cash.** A credit memo leaves the customer with credit,
  not a refund. If they expect money back, you also need the refund entry.
- **Restocking is the credit memo's job, not the RMA's.** Receiving an RMA tracks
  that goods arrived; it's the **credit memo** that actually puts them back into
  inventory and reverses COGS. Don't expect the books to move until the credit
  memo posts.
- **Quality tickets are manual.** Capturing a return code doesn't open a CA/PA
  ticket — see above.

---

## Related

- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md) — the sale this reverses
- [PO → Receive → Vendor Invoice → Pay Bill](./02-po-receive-bill-pay.md) — the vendor-credit side in context
- [Inventory types and COGS](../02-core-concepts/05-inventory-types-and-cogs.md) — why restocking reverses COGS at cost
- [CA/PA tickets](../04-modules-in-depth/05-quality/01-ca-pa-tickets.md) — Quality tickets (raised manually from return trends)
