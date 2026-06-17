---
title: Multi-currency Invoice and GL Settlement
category: Daily Workflows
order: 6
status: published
audience: [bookkeeper]
last-updated: 2026-06-16
---

# Multi-currency Invoice and GL Settlement

Bizuno lets you transact with customers and vendors in a foreign currency while
keeping your books in your home currency. It's more than most small apps attempt
— but it's important to be precise about *where the line is*, because Bizuno does
the recording-and-conversion part and **leaves the exchange-rate gain/loss
accounting to you**. This page is honest about both halves.

---

## Default currency vs. transaction currency

- Your **default (home) currency** is set once for the business (it defaults to
  USD). Your **General Ledger is kept entirely in this currency.**
- A transaction can be entered in a **different currency** — each one stores a
  **currency code** and an **exchange rate** alongside the amounts.

The crucial design point: **the GL is always in the default currency.** When you
post a foreign-currency transaction, Bizuno multiplies through by the rate and
records the GL legs in your home currency. The foreign currency and rate are kept
on the transaction for reference and for redisplaying it to you in its original
currency — but every debit and credit that lands in the ledger is in default
currency.

---

## How a EUR invoice posts (default = USD)

You invoice a customer €1,000 with the EUR rate set to 1.10 USD/EUR. You see and
send a €1,000 invoice; the GL records the USD equivalent at 1.10:

```
  Dr  Accounts Receivable    1,100.00 USD
        Cr  Sales Revenue          1,100.00 USD
```

(plus the usual COGS/inventory legs, also in USD). The transaction *remembers* it
was €1,000 at 1.10, so the document and the customer's history show euros, but the
ledger, the trial balance, and the financials are all USD.

---

## Where exchange rates come from

**You enter them manually.** Bizuno keeps a rate per currency that you set and
update by hand.

> **There is no automatic rate feed.** Bizuno once had an auto-update, but it's
> **deprecated** — the upstream free sources went away. Don't expect rates to
> refresh themselves; updating them is a manual task you do on whatever cadence
> your business needs (daily, weekly, at month-end). Keeping the rate current is
> what keeps your conversions reasonable.

---

## The part Bizuno does *not* do: FX gain/loss

This is the most important thing to understand, and the place the feature is
easiest to over-imagine.

### Realized gain/loss at payment — **not automatic**

When a EUR invoice is paid weeks later at a *different* rate, there's a real
economic gain or loss: you booked AR at 1.10 but collected at, say, 1.12. **Bizuno
does not compute or post that difference for you.** There is no automatic realized
FX gain/loss entry in the cash-receipt or payment journals.

If FX gain/loss matters to your books, you record it **as a manual General
Journal entry** (jID=2) for the difference between the booked AR/AP and the cash
actually received/paid, posting the gap to an FX gain/loss account you set up in
your chart. That's the supported path — a deliberate manual entry, not a
behind-the-scenes calculation.

### Unrealized gain/loss at period end — **not built in**

Likewise, Bizuno has **no period-end revaluation** that re-rates your open
foreign-currency AR/AP to the current rate and books an unrealized gain/loss. Open
items stay recorded at the rate they were booked at. If your accounting standard
requires period-end revaluation, that too is a manual journal entry.

---

## Reporting

Financial reports — GL, trial balance, income statement, balance sheet — are in
your **default currency only**, since that's how the ledger is stored. There's no
built-in "show AR by original currency" or "FX-impact" report. Transaction screens
and documents display the original currency and rate; the financial statements
roll everything up in your home currency.

---

## So what *is* the multi-currency story?

To summarize plainly, so nobody is surprised:

| Capability                                   | Bizuno                                   |
|----------------------------------------------|------------------------------------------|
| Enter invoices/bills in a foreign currency   | ✅ Yes                                   |
| Store the currency + rate on the transaction | ✅ Yes                                   |
| Post the GL in your home currency            | ✅ Yes (converted at the entered rate)   |
| Auto-update exchange rates                    | ❌ No — manual entry (auto-feed deprecated) |
| Realized FX gain/loss at payment             | ❌ No — manual General Journal entry      |
| Unrealized FX revaluation at period end      | ❌ No — manual General Journal entry      |
| Per-currency / FX-impact reports             | ❌ No — reports are default-currency only |

It's solid for **recording and converting** foreign-currency activity. It is **not**
a full FX-accounting engine — the gain/loss recognition is your manual journal
entry, by design.

---

## Common gotchas

- **The customer sees euros; your books see dollars.** That's correct — don't
  expect a EUR balance in the GL.
- **A stale rate quietly distorts conversions.** Since rates are manual, an
  out-of-date rate means every transaction booked against it is converted at the
  wrong number. Refresh rates on a schedule.
- **Don't wait for an FX gain/loss line to appear — it won't.** If your books need
  to recognize the rate difference at payment or period end, *you* post the
  General Journal entry. Nothing posts it for you.

---

## Related

- [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md) — the concept and where the default currency is set
- [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md) — adding the FX gain/loss account you post differences to
- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md) — the underlying cycle, here in a foreign currency
