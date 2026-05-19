---
title: Quote → SO → Invoice → Payment → Deposit
category: Daily Workflows
order: 1
status: draft
audience: [bookkeeper]
last-updated: 2026-05-19
---

# Quote → SO → Invoice → Payment → Deposit

The single most common workflow in any small-business book: convince a
customer to buy, sell to them, get paid, and bank the money. Bizuno breaks
it into four (sometimes five) journal entries. This page walks the full
happy path, names every journal type that fires, and shows what hits the
General Ledger at each step.

If you only ever learn one Bizuno workflow, learn this one — half of every
other workflow in the system is a variation on it.

---

## The full cycle at a glance

```
   ┌─────────┐   convert   ┌─────────┐   convert   ┌─────────┐
   │ Quote   │ ──────────▶ │ Sales   │ ──────────▶ │ Sales   │
   │ jID=9   │             │ Order   │             │Invoice  │
   └─────────┘             │ jID=10  │             │ jID=12  │
                           └─────────┘             └────┬────┘
                                                        │
                                                  apply payment
                                                        ▼
                                                  ┌─────────┐
                                                  │ Cash    │
                                                  │ Receipt │
                                                  │ jID=18  │
                                                  └────┬────┘
                                                       │
                                              (optional, if you use
                                               undeposited funds)
                                                       ▼
                                                  ┌─────────┐
                                                  │ Bank    │
                                                  │ Deposit │
                                                  │ jID=2   │
                                                  └─────────┘
```

You can enter the cycle at any node — most businesses skip the Quote (and
sometimes the SO) for repeat customers and jump straight to Invoice.

---

## Step 1 — Sales Quote (jID=9)

**What it is.** A non-binding offer to a customer. *No General Ledger
impact*. Inventory isn't reserved. Tax isn't accrued. It's a record-keeping
formality that buys you traceability ("the customer agreed to this price
last Tuesday").

**Where in the UI.** *Customers → Sales Quotes → New*.

**When to use it.** B2B sales where the customer needs an itemized quote
to get internal approval. Long enough to expire (set the terminal date).

**When to skip it.** Walk-up retail, repeat customers on standing terms,
service businesses that quote verbally.

---

## Step 2 — Sales Order (jID=10)

**What it is.** A commitment-on-paper that the customer wants what's on the
quote (or what they verbally asked for, if you skipped the quote). *No GL
impact yet* — inventory still belongs to you, AR isn't created.

**What changes vs. the quote.** Inventory is **soft-reserved** for the
order (the "Unshipped Orders" dashboard widget counts SO commitments
against quantity-on-hand). Some workflows route the SO to a fulfillment
queue.

**Recurring orders.** SOs support the `recur_id` chain — a single SO can
project itself forward weekly/monthly/quarterly, creating future-dated
copies that auto-invoice when their post date arrives. See
[Recurring invoices and POs](./05-recurring-invoices-and-pos.md).

**Where in the UI.** *Customers → Sales Orders → New*, or "Convert to SO"
from an existing quote.

**When to skip.** Cash-and-carry sales, services billed immediately on
completion, internet orders that ship same-day.

---

## Step 3 — Sales Invoice (jID=12)

**What it is.** The transaction that **actually books revenue**. This is
where the GL gets touched. Bizuno builds the invoice from the SO's lines
(if there was an SO) or from scratch.

**GL impact (typical):**

| Line                | Debit                    | Credit                   |
|---------------------|--------------------------|--------------------------|
| AR booking          | Accounts Receivable      | —                        |
| Revenue line(s)     | —                        | Sales Revenue (per item) |
| Sales tax (if any)  | —                        | Sales Tax Collected      |
| COGS (per item)     | Cost of Goods Sold       | —                        |
| Inventory relief    | —                        | Inventory                |

A $1,000 invoice for a single item with $80 cost and 8% sales tax posts:

```
  Dr  Accounts Receivable    1,080.00
        Cr  Sales Revenue              1,000.00
        Cr  Sales Tax Collected           80.00
  Dr  Cost of Goods Sold        80.00
        Cr  Inventory                     80.00
```

**Inventory comes off the shelf permanently at this step.** The soft
reservation from the SO becomes a hard reduction in `inventory_history`.

**Where in the UI.** *Customers → Sales (Invoice) → New*, or convert from
an SO.

**Recurring invoices.** Like SOs, invoices support `recur_id` for
subscriptions, retainers, and standing service charges.

---

## Step 4 — Cash Receipt (jID=18)

**What it is.** The customer paid. You record it against their open
invoice(s).

**GL impact:**

| Line              | Debit                                       | Credit                |
|-------------------|---------------------------------------------|-----------------------|
| Cash in           | Bank account *(or Undeposited Funds — see below)* | —               |
| AR relief         | —                                           | Accounts Receivable   |

A $1,080 payment received via check posts:

```
  Dr  Checking Account       1,080.00     (or Undeposited Funds)
        Cr  Accounts Receivable     1,080.00
```

**Partial payments are normal.** Apply $500 against a $1,080 invoice and
the invoice's `balance_due` becomes $580. Apply another payment later to
clear it.

**Cross-invoice payments are normal.** One check from a customer can clear
five open invoices in one Cash Receipt entry.

**Overpayments and credits.** Apply more than the open balance and Bizuno
creates a customer-credit-on-file. Use it against a future invoice.

**Where in the UI.** *Customers → Cash Receipts → New*.

---

## Step 5 — Bank Deposit (jID=2, optional)

This step **only applies if your bookkeeping uses an "Undeposited Funds"
holding account.**

The pattern:

- During the day, customer payments post `Dr Undeposited Funds, Cr AR`
- At end-of-day or end-of-week, you take physical checks to the bank
- Record a General Journal entry (jID=2) that moves the total deposited
  amount from Undeposited Funds to the actual bank account:

```
  Dr  Checking Account       5,432.10
        Cr  Undeposited Funds       5,432.10
```

This pattern matches the deposit slip the bank gives you — one Bizuno
journal entry per physical deposit, so the reconcile-to-bank-statement
step has one line per deposit slip.

**If you don't use Undeposited Funds**, payments post directly to the
bank account in Step 4 and there's no Step 5. This is simpler but makes
bank reconciliation harder (the bank shows one deposit while your books
show ten individual receipts).

Most bookkeepers learn to love Undeposited Funds within a month of
starting it.

---

## Common variations

### Skip quote and SO; invoice directly

Walk-in customer pays at the register, repeat customer on standing terms.
Just create the Invoice (jID=12) and you're done. The GL impact is
identical to the full cycle.

### Cash sale (skip AR entirely)

Use **Point of Sale** (jID=19) instead of an invoice. POS combines the
invoice and receipt into one transaction:

```
  Dr  Cash                   108.00
        Cr  Sales Revenue          100.00
        Cr  Sales Tax Collected      8.00
  Dr  COGS                    80.00
        Cr  Inventory               80.00
```

No AR is created; no Cash Receipt step is needed. Used for retail
checkout flows.

### Partial payment / payment plan

Cash Receipt (Step 4) accepts any amount up to the open balance. Repeat
as needed. The invoice stays open in *Customers → Aged AR* until fully
paid.

### Pre-payment / customer deposit on a future order

Record a Cash Receipt against the customer (not against a specific
invoice). Bizuno creates a customer-credit balance. Later, when you
invoice them, apply the credit at invoice time so the invoice is born
already paid.

### Customer credit on file from a return

A Credit Memo (jID=13) reduces AR. If the customer overpaid or returned
goods, the credit memo creates a negative-AR balance that follows them
forward — apply it to the next invoice.

---

## Reading aged AR

After running this cycle for a while, *Customers → Aged AR* shows you who
owes what:

| Bucket    | Meaning                                |
|-----------|----------------------------------------|
| Current   | Not yet due (within terms)             |
| 1–30      | 1–30 days past due                     |
| 31–60     | Worth a polite phone call              |
| 61–90     | Worth a sterner phone call             |
| 90+       | Time to make a decision (collections?) |

The bucket boundaries come from the invoice's terms minus today. They're
configurable in *Settings → PhreeBooks → Aging*.

---

## Common gotchas

- **A "save" on a quote/SO does NOT post to GL.** People assume it must,
  panic when they see no balance change. That's correct — GL only moves at
  invoice time.
- **Editing a posted invoice does NOT automatically re-post GL.** Bizuno
  unposts and re-posts under the hood; if the edit changes amounts/items,
  the GL legs change too. Trust the process; verify with a register view.
- **"I created the invoice but the customer never paid" — leave the
  invoice open.** Don't delete it. Either issue a credit memo (preserves
  audit trail) or write off via journal entry when you're sure it's bad
  debt.
- **The "Deposit Slips" feature in *Banking* is just a report** over the
  Undeposited Funds account. It doesn't create journal entries by itself —
  you still record the deposit as the Step 5 General Journal.

---

## Related

- [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md) — the full set of journal IDs involved here
- [Sale with partial backorder](./03-sale-with-partial-backorder.md) — when you can't ship everything on the SO at once
- [Returns and credit memos](./04-returns-and-credit-memos.md) — the reverse cycle
- [Recurring invoices and POs](./05-recurring-invoices-and-pos.md) — automating the cycle for repeat business
- [Multi-currency invoice and GL settlement](./06-multi-currency-and-gl-settlement.md) — the cycle when the customer pays in a foreign currency
