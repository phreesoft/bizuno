---
title: Chart of Accounts
category: PhreeBooks
order: 1
status: published
audience: [bookkeeper, admin]
last-updated: 2026-06-07
---

# Chart of Accounts

The Chart of Accounts (COA) is the structural skeleton everything else hangs on.
Every journal posting lands in a GL account; every report sums them. Get the COA
right at install and the rest of Bizuno falls into place — get it wrong and you're
fighting it for the life of the company file.

This page covers the account-type system, how the chart is stored, the
default-account mechanism that makes day-to-day posting "just work," and what's
safe to change after transactions have posted.

> **Set it up once, carefully.** Bizuno can't stop you from building a messy
> chart, and rebuilding mid-life means re-mapping defaults, item GL accounts, and
> contact defaults. Start from a template close to your business and prune.

---

## Account types

Every account has a **type** — an integer that tells Bizuno where the account
belongs on the financial statements and how it behaves at year-end. The full set
(defined as `PHREEBOOKS_CHART_TYPES` in `bizunoCFG.php`):

| Type | Name                          | Statement      | Closes at FY end? |
|-----:|-------------------------------|----------------|-------------------|
| 0    | Cash                          | Balance Sheet  | no                |
| 2    | Accounts Receivable           | Balance Sheet  | no                |
| 4    | Inventory                     | Balance Sheet  | no                |
| 6    | Other Current Assets          | Balance Sheet  | no                |
| 8    | Fixed Assets                  | Balance Sheet  | no                |
| 10   | Accumulated Depreciation      | Balance Sheet  | no                |
| 12   | Other Assets                  | Balance Sheet  | no                |
| 20   | Accounts Payable              | Balance Sheet  | no                |
| 22   | Other Current Liabilities     | Balance Sheet  | no                |
| 24   | Long Term Liabilities         | Balance Sheet  | no                |
| 30   | Income / Sales                | Income Stmt    | **yes**           |
| 32   | Cost of Sales (COGS)          | Income Stmt    | **yes**           |
| 34   | Expenses                      | Income Stmt    | **yes**           |
| 40   | Equity — does not close       | Balance Sheet  | no                |
| 42   | Equity — gets closed          | Balance Sheet  | **yes**           |
| 44   | Equity — Retained Earnings    | Balance Sheet  | no (it's the sink)|

The type drives behavior across the system: which side of the statement the
account reports on, whether the account's balance is rolled into Retained Earnings
(type 44) at [fiscal-year close](./05-fiscal-year-management.md), and which
accounts are offered in type-filtered dropdowns (e.g. only Cash accounts in the
register's account picker).

---

## How the chart is stored

The chart **definition** lives in Bizuno's module config (cached as
`getModuleCache('phreebooks', 'chart')`), persisted in the `common_meta` table
under the `chart_of_accounts` key. The chart is **company-wide** — there is no
per-store chart (see [Multi-store](#multi-store)).

Each account row carries:

| Field      | Meaning                                                        |
|------------|----------------------------------------------------------------|
| `id`       | The GL account number (text — `1200`, `40000-01`, etc.)        |
| `title`    | The account name shown in reports and dropdowns                |
| `type`     | One of the types above                                         |
| `heading`  | `1` = heading row, `0` = a real posting account                |
| `default`  | `1` = the default account for its type (see below)             |
| `inactive` | `1` = hidden from new transactions, kept for history           |
| `cur`      | Currency ISO code (defaults to the company locale)             |
| `parent`   | The heading this account rolls up under                        |

Account **balances** are not stored on the chart — they live per-period
per-account in `journal_history`, which is where reports read beginning balances
and period activity.

### Heading rows vs. account rows

A **heading** (`heading=1`) is a label that groups accounts on reports — "Current
Assets", "Operating Expenses". It never receives postings and is skipped when
Bizuno builds account history. Everything with `heading=0` is a real account you
can post to.

---

## Default accounts — why posting "just works"

When you enter an invoice line and don't pick a GL account, Bizuno has to know
which revenue account to credit. It resolves this through a layered default
system, most specific wins:

1. **Per-SKU override** — an inventory item can carry its own `gl_sales`,
   `gl_inv`, and `gl_cogs` accounts. If set, these win.
2. **Per-inventory-type default** — each inventory type (stock, non-stock,
   service, assembly, …) has configured sales/inventory/COGS accounts under
   *Settings → Inventory*.
3. **Module defaults** — *Settings → PhreeBooks* holds the customer-side and
   vendor-side fallbacks:

   | Setting key       | Used for                          |
   |-------------------|-----------------------------------|
   | `gl_receivables`  | Accounts Receivable (customers)   |
   | `gl_sales`        | Default revenue                   |
   | `gl_payables`     | Accounts Payable (vendors)        |
   | `gl_purchases`    | Default inventory/expense on bills |
   | `gl_cash`         | Default cash account              |
   | `gl_discount`     | Discounts given/taken             |
   | `gl_deposit`      | Customer/vendor deposits          |
   | `gl_liability`    | Sales-tax and other liabilities   |
   | `gl_expense`      | Default expense                   |

4. **Type default** — as a last resort, the account flagged `default=1` for the
   relevant type is used (`getChartDefault($type)`).

> **One default per type.** Exactly one account per type should carry the
> `default` flag. The type defaults are the safety net behind the named settings
> above — keep them pointing at sensible accounts even if you rely mostly on the
> module settings.

---

## Building the chart

### Start from a template

Bizuno ships four starter charts (under
`locale/<lang>/modules/phreebooks/charts/`):

| Template        | For                                   |
|-----------------|---------------------------------------|
| `retail-single` | Single-location retail / wholesale    |
| `retail-multi`  | Multi-location retail                  |
| `mfg-single`    | Single-location manufacturing         |
| `mfg-multi`     | Multi-location manufacturing          |

Pick the closest at install. Each is a CSV of account id, default flag, parent,
inactive flag, description, and type code — so you can also open one in a
spreadsheet, tailor it, and import your own.

### Build from scratch

You can start empty and add accounts by hand, but you must cover the essentials
the default system expects — at minimum AR, AP, a cash account, sales, COGS,
inventory, a sales-tax liability, and Retained Earnings — and wire them into the
module settings above.

---

## Editing the chart after go-live

**Editing account properties is allowed** at any time — you can rename an account,
mark it inactive, or change its type. Be deliberate: changing an account's *type*
reclassifies its balance on the statements and changes its year-end close
behavior, retroactively.

**Deleting an account is blocked** when it would orphan data. Bizuno refuses to
delete an account that is:

- referenced by any posted transaction (`journal_main.gl_acct_id` or
  `journal_item.gl_account`),
- set as a default on any contact (customer/vendor AR/AP accounts),
- set as a GL account on any inventory item (`gl_sales` / `gl_inv` / `gl_cogs`),
- the Retained Earnings account (type 44), or
- carrying a non-zero ending balance in the last period.

When you can't delete an account you no longer use, **mark it inactive** instead —
it disappears from new-transaction dropdowns but stays available for historical
reports.

---

## Multi-store

The chart of accounts is **shared across all stores** — there is no per-store
chart. The only store-related wrinkle is the `isolate_stores` setting (*Settings →
PhreeBooks → General*), which, when enabled, isolates **COGS posting** by store. It
does not create separate charts; account structure is global to the company file.

---

## Related

- [Journals (the journal_id reference)](./02-journals.md) — what posts to these accounts
- [Inventory types and COGS](../../02-core-concepts/05-inventory-types-and-cogs.md)
- [Fiscal-year management](./05-fiscal-year-management.md) — how income accounts close into Retained Earnings
- [Register & Reconcile](./03-register-and-reconcile.md)
