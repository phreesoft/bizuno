---
title: What is Bizuno
category: Getting Started
order: 1
status: published
audience: [bookkeeper, admin, developer]
last-updated: 2026-06-16
---

# What is Bizuno

**Bizuno is a full-featured, self-hosted ERP and accounting system — open source
(AGPL-3.0), written in PHP, running on MySQL/MariaDB.** Double-entry books,
inventory, purchasing and sales, CRM, quality management, and reporting in one
application you run on your own server. It's the modern continuation of the
PhreeBooks/PhreeSoft lineage.

If you're deciding whether Bizuno fits, this page is the honest 90-second answer.

---

## What Bizuno does well

| Area | What you get |
|------|--------------|
| **Double-entry accounting** | A real GL: chart of accounts, journals, register, reconciliation, fiscal-year close — see [PhreeBooks](../04-modules-in-depth/01-phreebooks/) |
| **Sales & purchasing** | The full cycle — quotes, orders, invoices, payments, deposits, and the vendor mirror (PO → bill → pay). See [Daily Workflows](../03-daily-workflows/) |
| **Inventory** | Stocked goods, assemblies/BOMs, serialized items, master-stock variants, FIFO/LIFO/average costing — see [Inventory](../04-modules-in-depth/03-inventory/) |
| **Contacts & CRM** | One universal contact record that can be customer, vendor, employee, lead, and more at once — see [Contacts](../04-modules-in-depth/04-contacts/) |
| **Quality (ISO-9001-style)** | CA/PA tickets, audits, training, maintenance, objectives — see [Quality](../04-modules-in-depth/05-quality/) |
| **Reporting & forms** | PhreeForm: a built-in report/form designer with PDF output — see [PhreeForm](../04-modules-in-depth/02-phreeform/) |
| **Multi-everything** | Multiple stores, periods, and currencies in one install — see [Core Concepts](../02-core-concepts/01-multi-store-multi-period-multi-currency.md) |
| **Integrations** | A [WooCommerce bridge](../07-integration/01-woocommerce-via-bizuno-api.md), [EDI X12](../07-integration/02-edi-x12.md), and a [REST surface](../07-integration/03-rest-api-surface.md) |
| **Customizable without forking** | The [`myExt/` pattern](../06-customization/01-the-myext-pattern.md) — client-specific code beside the core, not in it |

---

## What Bizuno is *not*

Setting expectations honestly here saves disappointment everywhere else:

- **Not a SaaS you rent.** Bizuno is software you **host yourself** (or have hosted
  for you). That means no per-seat subscription and no vendor lock-in — but also
  that *you* own backups, updates, and uptime. See
  [Backup and restore](../05-administration/03-backup-and-restore.md).
- **Not a bank-feed aggregator.** There are no automatic bank connections;
  reconciliation is done against a statement, by hand.
- **Not a full FX-accounting engine.** Bizuno *records* foreign-currency
  transactions and posts the GL in your home currency, but it does **not**
  auto-calculate realized or unrealized FX gain/loss — those are manual journal
  entries. See [Multi-currency](../03-daily-workflows/06-multi-currency-and-gl-settlement.md).
- **Not POS-first.** It has a point-of-sale journal, but it's an accounting/ERP
  system with POS, not a retail-checkout product with books bolted on.
- **Not zero-setup.** It rewards understanding a few [core concepts](../02-core-concepts/)
  (fiscal periods, the journal model, inventory types) before you configure a
  business — skipping them is the usual source of early pain.

---

## How it compares

A rough orientation, not a scorecard:

| vs. | The short version |
|-----|-------------------|
| **PhreeBooks 5** | Bizuno *is* its successor — same financial DNA, rebuilt architecture. There's a [built-in migration](../08-migration-and-upgrade/01-from-phreebooks-v5.md). |
| **QuickBooks (Desktop/Online)** | Bizuno is self-hosted and open-source, with deeper inventory/manufacturing and quality features, but without QuickBooks' bank feeds and huge app ecosystem. No direct importer — you [start fresh](../08-migration-and-upgrade/02-from-quickbooks.md). |
| **Wave / other free SaaS** | More capable for product businesses (real inventory, multi-store, EDI, quality) — at the cost of running it yourself. |
| **ERPNext / larger open ERPs** | A similar "self-hosted open ERP" niche; Bizuno is PHP/MySQL and accounting-first, and lighter to stand up on ordinary LAMP hosting. |

---

## Who uses Bizuno

- **Small and mid-sized businesses** that want real double-entry books without a
  subscription or a cloud they don't control.
- **Multi-store retailers and distributors** needing per-store inventory and
  consolidated books.
- **Light manufacturers** using assemblies/BOMs and **ISO-9001-style quality**
  processes.
- **Accountants and consultants** serving multiple clients, who value a
  customizable, self-hostable platform — one install per client, with the
  [`myExt/` pattern](../06-customization/01-the-myext-pattern.md) for per-client
  tweaks.
- **WooCommerce sellers** keeping the storefront public and the books in a
  back-office Bizuno via the [bridge](../07-integration/01-woocommerce-via-bizuno-api.md).

---

## Where to go next

- New to ERP/accounting? Read [Core Concepts](../02-core-concepts/) next — it's
  the section that prevents the common setup mistakes.
- Ready to install? See [the ways to run it](./02-three-ways-to-run-it.md) and the
  install walkthroughs (Docker, LAMP/LEMP, WordPress).
- Coming from PhreeBooks 5 or QuickBooks? Skim this, then go to
  [Migration & Upgrade](../08-migration-and-upgrade/).

---

## Related

- [Ways to run it](./02-three-ways-to-run-it.md)
- [Core Concepts](../02-core-concepts/)
- [Migration & Upgrade](../08-migration-and-upgrade/)
