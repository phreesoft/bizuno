---
title: Multi-store, Multi-period, Multi-currency
category: Core Concepts
order: 1
status: published
audience: [admin, bookkeeper]
last-updated: 2026-05-19
---

# Multi-store, Multi-period, Multi-currency

Bizuno carries three independent "multi-" dimensions that most small-business
accounting apps either don't have at all or implement as bolt-ons. They're
orthogonal — turning one on doesn't force the others — but they all interact
at report time, and several of them are **effectively permanent** once you
start transacting against them.

This is the page to read *before* you spend a weekend configuring a business.
Most rebuild-from-scratch pain comes from a day-one decision made without
realizing one of these dimensions existed.

---

## The three dimensions in one paragraph

- **Multi-store** — your business operates from more than one physical location
  (warehouses, retail stores, jobsites), and you want each location's stock
  and revenue tracked separately while sharing one chart of accounts.
- **Multi-period** — you record transactions against a *fiscal calendar* that
  can differ from the Gregorian year and can span many years simultaneously.
- **Multi-currency** — you do business in more than one currency, and you
  want Bizuno to track both the original-currency amount and the
  default-currency equivalent on every transaction.

You can run with zero, one, two, or all three turned on. The defaults at
install assume single-store, single-currency, calendar-year — the simple case.

---

## Multi-store

### When to turn it on

Turn multi-store on when **any** of these is true:

- Inventory physically lives in more than one place and you ship from each
- You want per-store P&L (which store is profitable, which isn't)
- Different staff are restricted to "their" store's data
- You consolidate multiple legal entities into one book and need to keep
  them visually separated in reports

Single-location operations should leave it off. Adding stores later is easy;
collapsing two stores into one after a year of separate transactions is not.

### How it's modeled

Every transactional row in `journal_main`, `inventory`, and `contacts` carries
a `store_id` integer. The default store is `0`. Additional stores get integer
IDs and a configuration row with the store's name, address, default GL
overrides, and per-store contact preferences.

The **chart of accounts is shared.** All stores post to the same GL accounts.
"Store-level P&L" comes from filtering reports by `store_id`, not from
separate ledgers.

### Restricted-store users

A user with `restrict_store = 1` on their profile can only see and post to
their assigned store. The role editor enforces this — most grids invisibly
add a `WHERE store_id = <user's store>` clause for restricted users.

### Common gotchas

- **A SKU is in `inventory` once, but stock-on-hand is per-store.** Each
  store's quantity-on-hand lives in `inventory_history` keyed by store_id.
- **Customers and vendors can belong to one store (`store_id != 0`) or all
  stores (`store_id = 0`).** Restrict-by-store reads the customer's
  store_id, so a customer assigned to Store 2 is invisible from Store 1.
- **Transferring stock between stores is its own transaction (Store
  Transfer in Inventory).** Don't try to fudge it with a manual journal —
  the COGS plumbing breaks.

---

## Multi-period

### What "period" means

In Bizuno, every transaction has two timekeeping fields:

| Field        | Meaning                                                              |
|--------------|----------------------------------------------------------------------|
| `post_date`  | The calendar date the transaction is recorded for.                   |
| `period`     | The fiscal-period number that contains that `post_date`.             |

The fiscal calendar lives in the `journal_periods` table. Each row defines
one period (usually one month) with a `start_date`, `end_date`, and
`fiscal_year`. The first fiscal year you set up at install seeds 12 periods
starting on whatever date you chose; subsequent years extend the sequence.

A "period" is an integer that grows monotonically — period 1 is the first
month of fiscal year 1, period 13 is the first month of fiscal year 2, and so
on. The number itself isn't reused. When you close fiscal year 1, periods
1–12 are deleted and 13–24 get renumbered down to 1–12 — but the renumber is
internal; reports adapt automatically.

### When fiscal year and calendar year differ

If your business runs on a non-January fiscal year (common for retail
ending in January, education ending in June, government on October),
configure that at install via "First Fiscal Year Start Date."

**This is one of the two settings that's effectively permanent.** Changing
it after you've posted transactions means rebuilding `journal_periods` and
re-stamping `period` on every row in `journal_main`. Get it right on day one.

### How Bizuno picks the period when you save a transaction

When you save an invoice or any other journal entry, Bizuno calls
`calculatePeriod($post_date)`:

1. If `post_date` falls inside the cached current period → return that
   period number from cache (the fast path; 99% of transactions hit it).
2. Otherwise, look up the period in `journal_periods` whose `start_date <= post_date <= end_date`.
3. If no period covers that date and the date is past the last fiscal
   year, Bizuno auto-extends the calendar (`ensureFiscalYearCovers`,
   added in 7.3.9) up to 10 years forward, then retries.
4. If the date is *before* the first fiscal year, the transaction is
   rejected. There's no auto-extend backward.

### Common gotchas

- **A pre-dated transaction on the wrong side of a period boundary will
  show in the "wrong" period.** This is correct — the period is calculated
  from `post_date`, not from when you typed it.
- **Locking a period stops new posts but not edits.** Closing FY is the
  permanent option.
- **Reports that span fiscal years (e.g. "last 12 months" in February) read
  from multiple `period` values.** Plan your filters accordingly.

---

## Multi-currency

### When to turn it on

Turn multi-currency on when you sell to or buy from anyone in a currency
other than your default. "We occasionally take a Euro" doesn't justify it —
you can post that as a one-off journal adjustment. "We send EU invoices
monthly in EUR and our books are in USD" does.

Single-currency operations should leave currency at the default. Toggling
multi-currency on later works fine, but every existing transaction is
treated as default-currency.

### The model

Every transaction has two currency fields:

| Field             | Meaning                                                          |
|-------------------|------------------------------------------------------------------|
| `currency`        | The currency the transaction was *struck in* (e.g. `EUR`).       |
| `currency_rate`   | The exchange rate from `currency` → default, as of `post_date`.  |

Bizuno stores both the foreign-currency amount (`1,000.00 EUR`) and the
default-currency amount (`1,000.00 × 1.10 = 1,100.00 USD`). The GL is always
recorded in the default currency.

Exchange rates come from:
- Manual entry (the user types the rate per transaction)
- The configured rate service (set in Settings → Locale → Currency)
- A locked-in rate per customer/vendor (rare — for contracted long-term deals)

### Realized FX gain/loss

When a customer pays in `EUR` two weeks after you invoiced them in `EUR`,
the EUR/USD rate has probably moved. The difference between the
invoice-day rate and the payment-day rate is a **realized FX gain or loss**.
Bizuno posts that automatically to the configured FX gain/loss account at
payment time.

### Unrealized FX

Open AR/AP balances in a foreign currency are exposed to rate movement
every day. Most businesses revalue them at month-end (or fiscal-year-end)
with a single journal entry that brings the default-currency balance back
in line with the current rate. Bizuno has a Revalue tool under
PhreeBooks → Tools.

### Common gotchas

- **The default currency is permanent.** Picked at install. Don't change it.
- **Reports are reportable in either default or transaction currency, but
  one at a time.** A mixed "EUR amounts converted to USD on the fly" report
  is harder to build — usually you do one or the other.
- **Round-trip rates rarely net to zero.** A USD invoice paid in EUR
  immediately re-banked back to USD usually leaves a few cents of FX
  rounding. That's expected, not a bug.

---

## How the three interact

The three dimensions are independent. Combinations:

| Stores | Periods (FY) | Currencies | Common for                                  |
|--------|--------------|------------|---------------------------------------------|
| 1      | Calendar     | 1          | Simple service business, single shop        |
| 2+     | Calendar     | 1          | Multi-location retail, multi-warehouse e-com|
| 1      | Calendar     | 2+         | One-shop exporter (sells in EUR, books in USD) |
| 1      | Non-calendar | 1          | Most education, government, fashion-retail  |
| 2+     | Non-calendar | 2+         | Multi-national; the full-flex configuration |

A few interactions worth knowing:

- **Closing a fiscal year affects all stores at once.** You can't keep store
  2's FY 2024 open while closing store 1. There's one fiscal calendar per
  business.
- **A reconcile happens per-store on bank accounts that are per-store.** If
  a bank account is shared across stores, all stores' transactions flow
  through one reconcile.
- **Currency revaluation operates across all stores.** Open AR in EUR at
  Store 1 and Store 2 both get revalued by the same rate movement.

---

## Configuration gotchas

A short list of decisions to make carefully on day one, in order of
revoke-ability (worst first):

1. **Default currency.** Effectively permanent. Choose your home currency.
2. **First fiscal year start date.** Effectively permanent. Choose carefully.
3. **Multi-currency on/off.** Easy to enable later; awkward to disable after
   any foreign-currency transactions exist.
4. **Multi-store on/off.** Easy to enable later. Collapsing back from
   multi-store to single-store after transacting is painful.
5. **Chart of accounts.** Editable forever, but transactions already posted
   to accounts you delete need re-mapping. Pick a template close to what
   you want, then customize.

If you're unsure on any of these, **err on the side of the more capable
option**. Single-currency books with multi-currency *off* doesn't save
anything material; configuring multi-currency on a single-currency
business is invisible until you need it.

---

## Related

- [Fiscal periods vs. calendar dates](./03-fiscal-periods-vs-calendar-dates.md) — deeper dive on the `period` ↔ `post_date` relationship
- [Fiscal-year close](../05-administration/04-fiscal-year-close.md) — what closing a year actually does to your data
- [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md) — designing a COA that works across stores
- [Multi-currency invoice and GL settlement](../03-daily-workflows/06-multi-currency-and-gl-settlement.md) — worked example of a full FX cycle
