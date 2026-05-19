---
title: The journal_id Taxonomy
category: Core Concepts
order: 2
status: draft
audience: [admin, developer]
last-updated: 2026-05-19
---

# The `journal_id` Taxonomy

Bizuno records every transactional event — quotes, orders, invoices,
payments, credits, inventory builds, work orders, quality tickets — as a
single row in **`journal_main`**, discriminated by an integer `journal_id`
field. Understanding this taxonomy unlocks the rest of the codebase: you
can read any report SQL, follow any customization, and pick the right
transaction type from a UI you've never seen before.

This page is the **canonical reference**. The richer per-journal narrative
(GL postings, fields, lifecycle) lives in
[Journals](../04-modules-in-depth/01-phreebooks/02-journals.md).

---

## Mental model

```
┌──────────────────────────────────────────────────────────────┐
│  journal_main                                                │
│  ──────────────────────────────────────────────────────────  │
│  id, journal_id, post_date, period, contact_id_b, store_id,  │
│  invoice_num, total_amount, closed, recur_id, ...            │
└──────────────────────────────────────────────────────────────┘
                          │
                          │  ref_id
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  journal_item                                                │
│  ──────────────────────────────────────────────────────────  │
│  one row per line item (or GL leg); sku, qty, debit, credit, │
│  gl_account, gl_type, reconciled, ...                        │
└──────────────────────────────────────────────────────────────┘
```

A typical sales invoice (jID=12) is **one** `journal_main` row plus several
`journal_item` rows: one per inventory line, one or more per GL posting
(debit AR, credit revenue, debit COGS, credit inventory). Every transaction
in Bizuno follows this shape, regardless of whether it's a sale, a quality
ticket, or a work order.

The `journal_id` itself drives:

- **Which controller** renders the entry screen (`controllers/phreebooks/main.php`
  for most PB journals; `controllers/quality/tickets.php` for jID=30; etc.)
- **Which journal class** posts it (`controllers/phreebooks/journals/jNN.php`)
- **Which GL postings** happen (per the journal class's `Post()` method)
- **Which dashboard widgets** count the entry
- **Which audit-log message** describes it (`lang("journal_id_$jID")`)
- **Which forms/reports** apply (PhreeForm filters by `journal_id`)

Change a row's `journal_id` and you've effectively reclassified the
transaction across the whole system. Don't do this casually.

---

## Reference table — PhreeBooks core (0–29)

| jID | Name                | What it is                              | Class file              |
|-----|---------------------|-----------------------------------------|-------------------------|
| 0   | Search Journal      | Base class for searches (not a real transaction) | `j00.php`      |
| 2   | General Journal     | Manual GL adjustment / journal entry    | `j02.php`               |
| 3   | Request For Quote   | Outbound RFQ to a vendor (no GL)        | `j03.php`               |
| 4   | Purchase Order      | Outbound PO to vendor (no GL until receive) | `j04.php`           |
| 6   | Purchase            | Vendor invoice / bill (Dr inventory or expense, Cr AP) | `j06.php` |
| 7   | Vendor Credit       | Credit memo received from vendor (reverses 6) | `j07.php`         |
| 9   | Sales Quote         | Outbound quote to customer (no GL)      | `j09.php`               |
| 10  | Sales Order         | Inbound order from customer (no GL until invoice; supports recurring) | `j10.php` |
| 12  | Sales               | Customer invoice (Dr AR, Cr revenue + tax; Dr COGS, Cr inventory) | `j12.php` |
| 13  | Credit Memo         | Customer return / credit (reverses 12)  | `j13.php`               |
| 14  | Assembly            | Inventory build (pulls components, produces assembly) | `j14.php` |
| 15  | Store Transfers     | Move inventory between stores           | `j15.php`               |
| 16  | Adjustment          | Inventory adjustment (count, write-down/up) | `j16.php`           |
| 17  | Vendor Refund       | Cash refund from vendor (Dr cash, Cr AP) | `j17.php`              |
| 18  | Cash Receipt        | Customer payment against AR (Dr cash/undeposited, Cr AR) | `j18.php` |
| 19  | Point Of Sale       | Immediate-cash sale (Dr cash, Cr revenue + inventory) | `j19.php` |
| 20  | Pay Bills           | Vendor payment (Dr AP, Cr cash); supports ACH + bulk pay-by-date | `j20.php` |
| 21  | Point Of Purchase   | Immediate-cash purchase (Dr inventory/expense, Cr cash) | `j21.php` |
| 22  | Customer Refund     | Cash refund to customer (Dr AR, Cr cash) | `j22.php`              |

The lang key for the human-readable label is **`journal_id_<N>`** (e.g.
`lang('journal_id_12')` → "Sales"). Many screens use `lang("journal_id_$jID_mgr")`
for the manager label ("Sales Manager").

---

## Reference table — extension journals (30+)

Extension modules use journal IDs ≥ 30 to avoid colliding with the
PhreeBooks core. These transactions still live in `journal_main` and follow
the same shape, but most don't post to the GL — they use the structure for
its workflow / lifecycle / audit-trail value.

| jID | Name              | Module       | What it is                                | Source                          |
|-----|-------------------|--------------|-------------------------------------------|---------------------------------|
| 30  | Quality Ticket    | quality      | Corrective/Preventive Action ticket       | `controllers/quality/tickets.php` |
| 31  | Quality Audit     | quality      | Audit instance                            | `controllers/quality/audits.php`  |
| 32  | Work Order        | inventory    | Production build job (used by jID=14 assembly when complete) | `controllers/inventory/build.php` |
| 34  | Training          | quality      | Training instance                         | `controllers/quality/training.php`|
| 35  | Maintenance       | administrate | Scheduled-maintenance instance            | `controllers/administrate/maint.php` |

If you're writing a custom extension, **use 40+ for your own journal IDs**
to leave room for future core additions in the 30s. See
[Custom journal type](../06-customization/03-custom-journal-type.md).

---

## Anatomy of a journal class

Every journal under `controllers/phreebooks/journals/jNN.php` extends the
shared base in `common.php` and implements a small set of conventions:

```php
namespace bizuno;

class jNN extends jCommon
{
    public $journalID = NN;

    public function Post()        { /* build the journal_item rows */ }
    public function unPost()      { /* reverse them */ }
    public function postJournalEntry() { /* the GL legs */ }
    // ...
}
```

The `Post()` method is where the journal's accounting personality lives. A
read of `j12.php::Post()` is the single best way to understand exactly what
posting a customer invoice does to the GL. Likewise `j20.php::Post()` for
the vendor-payment side.

Extension journals (jID ≥ 30) typically **don't** live under
`controllers/phreebooks/journals/`. They're saved through their own
controller (e.g. `controllers/quality/tickets.php::ticketSave()`) which
writes to `journal_main` directly and skips the GL-posting machinery.

---

## How `journal_id` drives behavior across modules

| System component                  | How it uses journal_id                                                            |
|-----------------------------------|-----------------------------------------------------------------------------------|
| Manager grids                     | Every PhreeBooks grid filter is `WHERE journal_id = N` for the relevant N         |
| Dashboards                        | Each widget is hard-coded to a journal_id (e.g. `qa_stop_work` filters on jID=30) |
| Audit log                         | Action description includes `lang("journal_id_$jID")`                             |
| PhreeForm                         | Reports + forms filter their data source by `journal_id`                          |
| Recurring transactions            | `recur_id` links a chain of `journal_main` rows that share a `journal_id`         |
| Permissions                       | Security keys often follow `j<N>_mgr` (e.g. `j12_mgr` for Sales Manager access)   |
| Next-reference counter            | `next_ref_j<N>` meta key per journal type (next invoice number, next PO number)   |

---

## Why this matters

The journal ID is Bizuno's *primary* discriminator. Almost any question of
the form *"Where does X live?"* resolves to *"Find its journal_id, then read
the journal class and the manager controller."* Yet most existing
introductions to Bizuno don't name it at all, and end-users hit it for the
first time as a column in a custom report they can't make sense of.

If you walk away from this page with one thing, let it be the mental model:
**one row in `journal_main` per transaction, the `journal_id` field tells
you what kind, and the per-jID file is where the behavior lives.**

---

## Related

- [Journals (the journal_id reference)](../04-modules-in-depth/01-phreebooks/02-journals.md) — per-jID narrative + GL posting detail
- [Custom journal type](../06-customization/03-custom-journal-type.md) — adding your own
- [Quote → SO → Invoice → Payment → Deposit](../03-daily-workflows/01-quote-so-invoice-payment-deposit.md) — sees jIDs 9, 10, 12, 18 in action
- [PO → Receive → Vendor Invoice → Pay Bill](../03-daily-workflows/02-po-receive-bill-pay.md) — sees jIDs 4, 6, 20 in action
