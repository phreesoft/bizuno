---
title: Register & Reconcile
category: PhreeBooks
order: 3
status: draft
audience: [bookkeeper]
last-updated: 2026-06-07
---

# Register & Reconcile

Two routines every bookkeeper repeats: *reading* a cash/bank account's activity
(the **register**) and *agreeing it to the bank statement* (the **reconcile**).
This page covers both, including the data Bizuno uses to track what's cleared and
how to resume a reconciliation you didn't finish.

> **Reconciliation in Bizuno is manual.** There is no bank feed or statement
> import — you compare your statement to the register by eye and tick off the
> items that cleared. That's by design (see [What it doesn't do](#what-it-doesnt-do)).

---

## The register

The register (*Banking → Register*) is the running ledger for one cash account.
Pick a **GL account** and a **fiscal period** and Bizuno shows that account's
movements with a running balance.

| Column      | Source                                          |
|-------------|-------------------------------------------------|
| Date        | `post_date`                                     |
| Reference   | the document reference (check #, deposit ref)   |
| Description | the transaction description                     |
| Deposit     | debit to the cash account                       |
| Payment     | credit to the cash account                      |
| Balance     | running balance (beginning balance + deposits − payments) |

The beginning balance comes from `journal_history` for the selected period, so the
register always ties to the GL. Filters are the **account** picker (cash-type
accounts only) and the **period** selector.

The register is a *view* — you don't post from it. Transactions arrive here from
the journals that touch cash: Cash Receipts (jID 18), Pay Bills (jID 20), Point of
Sale/Purchase (19/21), refunds (17/22), and any General Journal (jID 2) entry
hitting the account. See [Journals](./02-journals.md).

---

## Reconciliation

Reconciliation (*Banking → Reconcile*) answers one question: **does the bank agree
with the books?** You pick the account and period, enter the statement's ending
balance, tick the items that appear on the statement, and Bizuno tells you whether
the difference is zero.

### How "cleared" is tracked

Each line item carries a **`reconciled`** field on `journal_item`:

- `0` — not reconciled (open)
- a **period number** — reconciled, in that period (e.g. `reconciled = 12` means
  it cleared during period 12)

So reconciliation isn't a separate ledger — it's a stamp on the same item rows the
register reads. The statement's ending balance you type in is stored per
account-per-period as `stmt_balance` on `journal_history`, alongside the date you
last saved (`last_update`).

When **every** item in a cash transaction is reconciled, Bizuno marks that
transaction's `journal_main.closed = 1`; un-reconciling any of them reopens it.

### The workflow

1. **Open** *Banking → Reconcile*, choose the **account** and **period**.
2. Bizuno lists every item in that account that is either open (`reconciled = 0`)
   or already reconciled in this period — multi-line transactions group under a
   parent row so checking the parent checks its children.
3. **Enter the statement ending balance** in the Statement Balance field.
4. **Tick each item** that appears on your bank statement.
5. Watch the footer totals:
   - **Statement Balance** — what you entered
   - **Cleared this period** — sum of ticked items
   - **Outstanding** — sum of un-ticked items
   - **GL Balance** — the book balance from `journal_history`
   - **Difference** — should reach **zero** when you're done
6. **Save.** Ticked items get stamped `reconciled = <period>`; the statement
   balance is persisted.

### Save partial, resume later

Reconciliation persists incrementally — there's no separate "draft" state. Tick
what you can match, **Save**, and leave. When you return to the same account and
period, the items you already cleared come back **pre-ticked** (because they carry
`reconciled = <period>`), and you continue from there. Only the change since last
save (newly ticked / unticked) is written on each Save.

### Undo / re-open

There's no special "reopen" command — just untick. Untick a previously cleared
item and **Save**: its `reconciled` resets to `0` and, if that empties a
transaction's cleared status, the parent `journal_main.closed` flips back to `0`.
You can do this any time within the period.

---

## Handling discrepancies

Bizuno has **no in-reconcile adjustment field** — you can't type a bank fee or
interest credit directly into the reconcile screen. Instead, when the statement
shows something the books don't (a service charge, interest earned, an NSF
return):

1. Leave the reconcile screen and post the item — a **General Journal (jID 2)**
   entry against the cash account is the usual tool (e.g. `Dr Bank Charges`,
   `Cr Cash` for a fee).
2. Return to reconcile. The new entry now appears in the list.
3. Tick it along with the rest. The difference should fall to zero.

This keeps every adjustment as a real, auditable GL transaction rather than a
hidden plug.

---

## What it doesn't do

- **No bank feeds / statement import.** No OFX/QFX/CSV ingest of bank
  transactions; reconciliation is a manual tick-off. (Carrier reconciliation in
  the Shipping module is unrelated.)
- **No auto-matching.** Bizuno won't guess which book entry corresponds to which
  statement line.
- **No in-screen adjustments.** Fees and interest are posted as normal journal
  entries (above).

---

## Related

- [Journals (the journal_id reference)](./02-journals.md) — what lands in the register
- [Chart of Accounts](./01-chart-of-accounts.md) — cash-type accounts
- [Quote → SO → Invoice → Payment → Deposit](../../03-daily-workflows/01-quote-so-invoice-payment-deposit.md)
- [PO → Receive → Bill → Pay](../../03-daily-workflows/02-po-receive-bill-pay.md)
