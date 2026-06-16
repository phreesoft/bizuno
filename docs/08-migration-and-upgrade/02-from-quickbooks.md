---
title: From QuickBooks
category: Migration & Upgrade
order: 2
status: draft
audience: [admin, bookkeeper]
last-updated: 2026-06-16
---

# From QuickBooks

QuickBooks refugees are a big share of Bizuno's audience, so be clear from the
start: **there is no QuickBooks importer.** Bizuno does not read `.qbb`, `.qbo`,
or IIF files (an old `xfrQuickBooks` tool was retired to a separate legacy
module). Migrating from QuickBooks is a **start-fresh** exercise — bring your
master data and opening balances, not a full transaction history. The good news
is Bizuno has real CSV importers for the master data, so this is methodical, not
painful.

---

## The strategy in one line

**Set up the chart of accounts → import master data (CSV) → post opening balances
(CSV) → optionally import recent open items → validate the balance sheet.** Cut
over on a clean period boundary (month/quarter/year end) so the opening balances
line up with a QuickBooks statement you can check against.

---

## Step by step

### 1. Chart of accounts (first, before any transactions)

Set up the COA from a Bizuno **template** (XML) or build it out, *before* posting
anything — COA import is blocked once journal entries exist. Map your QuickBooks
accounts to Bizuno accounts as you go; you can export the Bizuno COA to CSV for a
working reference. See [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md).

### 2. Master data via CSV — *Admin → Import/Export*

| Data            | Import?              | Notes                                                        |
|-----------------|----------------------|--------------------------------------------------------------|
| **Customers**   | ✅ CSV               | Contacts tab; download the template, match the columns       |
| **Vendors**     | ✅ CSV               | Same Contacts importer (set the vendor role)                 |
| **Inventory items** | ✅ CSV           | Inventory tab; SKU, description, cost, price, type, GL accounts |
| **Chart of accounts** | ✅ (XML template) | Not CSV — set up before transactions (above)               |
| **Employees**   | ❌ no CSV importer   | Create employees by hand (payroll *entries* can import, but not the employee master) |

Export each list from QuickBooks to CSV, reshape to the Bizuno template columns,
and import. Get contacts and inventory in **before** any transactions that
reference them.

### 3. Opening balances via CSV — *Admin → Import/Export → Beginning Balances*

Bizuno has a dedicated **beginning-balance importer** (you don't hand-key one
giant journal entry). Provide a CSV of `account, balance`; it writes the balances
into the first period and **must balance** — total debits equal total credits, by
account type (assets debit; liabilities/equity credit). Use your QuickBooks trial
balance as of the cutover date as the source.

### 4. Open items and beginning stock (optional)

- **Open AR/AP:** to age individual unpaid invoices/bills, import them as
  historical **sales (j12)** / **purchases (j6)** via the journal CSV importers,
  rather than burying them in the opening lump sum.
- **Beginning inventory quantities:** set starting stock/cost on the inventory
  record at import, or post an **inventory adjustment (jID=16)** for the opening
  counts.

### 5. Validate

Run a **balance sheet / trial balance** as of the cutover date and reconcile it
to the same QuickBooks report. They should match to the penny. If they don't, the
opening-balance CSV is where to look first.

---

## What QuickBooks does that Bizuno does *differently*

Don't expect these to work the QuickBooks way — the capability exists, the shape
differs:

- **Undeposited Funds** → Bizuno uses a holding account plus an explicit bank
  deposit step, not an automatic UF account. See the deposit step in
  [Quote → SO → Invoice → Payment → Deposit](../03-daily-workflows/01-quote-so-invoice-payment-deposit.md#step-5--bank-deposit-jid2-optional).
- **Classes / Locations** → Bizuno's equivalent is **multi-store** (`store_id`),
  not free-form classes. See [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md).
- **Memorized / recurring transactions** → Bizuno's
  [recurring entries](../03-daily-workflows/05-recurring-invoices-and-pos.md)
  (note: occurrences are projected eagerly, and stopping a chain is manual).
- **Sales Tax Centre** → Bizuno calculates tax per contact/authority on the
  transaction; there's no separate tax-centre module.

## What QuickBooks does that Bizuno *doesn't*

Be honest with stakeholders about gaps:

- **Native bank feeds / auto-reconcile from a bank connection** — Bizuno reconciles
  manually against a statement; there's no built-in bank-feed aggregator.
- **The QuickBooks third-party app ecosystem** — integrations written specifically
  for QuickBooks won't carry over. Bizuno integrates via its own
  [REST surface](../07-integration/03-rest-api-surface.md),
  [WooCommerce bridge](../07-integration/01-woocommerce-via-bizuno-api.md), and
  [EDI](../07-integration/02-edi-x12.md).

---

## Keep QuickBooks as a read-only archive

Because you're not importing full history, **keep the QuickBooks file as a
read-only historical reference** for at least a full fiscal year (and as long as
your retention/tax rules require). Pull prior-year comparatives and audit detail
from QuickBooks; run the live books in Bizuno from the cutover date forward. Don't
archive QuickBooks away entirely until you're confident you won't need to look
back at pre-cutover detail.

---

## Related

- [Chart of Accounts](../04-modules-in-depth/01-phreebooks/01-chart-of-accounts.md) — set this up first
- [Quote → SO → Invoice → Payment → Deposit](../03-daily-workflows/01-quote-so-invoice-payment-deposit.md) — the Undeposited-Funds difference
- [Multi-store, multi-period, multi-currency](../02-core-concepts/01-multi-store-multi-period-multi-currency.md) — Bizuno's answer to classes/locations
