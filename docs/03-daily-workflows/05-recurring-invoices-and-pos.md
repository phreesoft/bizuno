---
title: Recurring Invoices and POs
category: Daily Workflows
order: 5
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-16
---

# Recurring Invoices and POs

Subscriptions, retainers, standing monthly orders — the transactions that repeat
on a schedule. Recurring entries are the feature accountants love and most cheap
apps half-implement. Bizuno's model is unusually literal: it doesn't "remember to
generate one later," it **creates all the future occurrences up front**, the
moment you set the recurrence. Understanding that one design choice explains
everything else on this page.

---

## Setting one up

When entering a transaction (a Sales Order, an invoice, a PO, a Purchase, or a
General Journal entry — and credit memos), open its recurring option and set:

- **Frequency** — one of four: **Weekly**, **Bi-weekly**, **Monthly** (the
  default), or **Quarterly**. (There is no daily or annual option.)
- **Number of entries** — how many occurrences to create in total.
- **Terminal date** — the through-date carried onto each occurrence.

Save, and Bizuno posts the whole series at once.

---

## How projection works: eager, not lazy

This is the part that surprises people. Set a monthly invoice to recur 12 times
and Bizuno **immediately inserts 12 rows** into `journal_main` — one per month,
each with its own future `post_date` already calculated. It does not wait and
generate next month's invoice next month; the future is laid down now.

Each row in the series shares a **`recur_id`** — a single value stamped on every
member that links them as one chain. From any occurrence you can pull the rest of
the chain (the current one and everything still ahead of it) by that `recur_id`.

Consequences worth knowing:

- **Future occurrences are real, queryable transactions** from day one — they show
  up when you look ahead, because they already exist.
- **Each occurrence gets its fiscal period stamped at creation.** As the projector
  walks the dates forward, it resolves each `post_date` to a period — see the
  fiscal-calendar note below.

---

## The fiscal-calendar catch (and the fix)

A five-year-out recurrence projects `post_date`s into fiscal years that **don't
exist yet** in `journal_periods`. Historically that broke: with no period to
resolve to, those rows were stamped **`period = 0`** — a transaction with a valid
date but no fiscal bucket, invisible to period-based reports.

This is now handled automatically. As it stamps each occurrence's period, Bizuno
**auto-extends the fiscal calendar forward** to cover the date, so every projected
row lands in a real period instead of period 0. *(since v7.4.0)* And the
[fiscal-year close](../05-administration/04-fiscal-year-close.md) re-stamps any
existing `period = 0` rows left over from before the fix. So a recurrence that
reaches years into the future "just works."

> **No 7.3.9.** If you've seen these recurring/period fixes attributed to "7.3.9,"
> that release doesn't exist — they shipped in **v7.4.0**. See
> [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md).

---

## Editing a chain

Because the occurrences already exist, editing is about **how far the change
reaches**. When you save an edit to a recurring transaction, Bizuno asks whether
to update future entries too:

- **Yes → this and all *future* occurrences** are re-posted with your changes
  (amounts, items, dates — the whole entry is re-applied to each remaining row).
- **No (Cancel) → only this one** occurrence changes.

There are exactly **two** choices — there's no "change all including past" option.
Past occurrences are history and stay as posted.

---

## Stopping a chain — and an honest caveat

Here's where to set expectations: **there is no working "stop this recurrence and
all future occurrences" button.** Deleting a recurring transaction from the
toolbar removes (unposts) **only that single occurrence** — it does not sweep away
the rest of the chain.

So to end a recurrence early, you **delete the remaining future occurrences
yourself**, one at a time, from the point you want it to stop. It's tedious for a
long series; the practical mitigation is to **not over-project in the first
place** — set the number of entries to what you actually expect, and start a fresh
recurrence later if it continues, rather than booking 60 occurrences and pruning
them.

---

## Common gotchas

- **The future entries are already on the books.** Forecast and period reports
  that look ahead will include projected recurs, because they're real rows — not a
  bug.
- **A recurring SO or PO still has to be acted on.** Recurrence creates the *order*
  occurrences; converting each to an invoice/purchase (and shipping/receiving) is
  still the normal per-occurrence workflow. Recurrence automates the entry, not the
  fulfillment.
- **Editing "all future" re-posts every remaining occurrence.** That's powerful
  (fix a price once, fix it everywhere ahead) but it *does* re-touch the GL on each
  future row — expect the change to ripple.
- **Don't over-project.** Given the manual stop, a too-long series is a chore to
  unwind. Project conservatively.

---

## Related

- [Fiscal periods vs. calendar dates](../02-core-concepts/03-fiscal-periods-vs-calendar-dates.md) — why future occurrences need the calendar extended
- [Quote → SO → Invoice → Payment → Deposit](./01-quote-so-invoice-payment-deposit.md) — the cycle a recurring SO/invoice automates the entry side of
- [Period drift and recurring entries](../09-troubleshooting/03-period-drift-and-recurs.md) — diagnosing leftover `period = 0`
- [Fiscal-year close](../05-administration/04-fiscal-year-close.md) — where existing period drift self-heals
