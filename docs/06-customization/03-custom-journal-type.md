---
title: Custom Journal Type
category: Customization
order: 3
status: draft
audience: [developer]
last-updated: 2026-06-16
---

# Custom Journal Type

Adding a journal type is the **deepest** extension Bizuno exposes — and the one
most likely to end as "it kind of works but the GL doesn't balance." This page
gives a scaffold whose postings *do* balance, and is honest about the one place
custom journals don't behave like the rest of `myExt/`.

First read [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md)
— it's the map of what a journal *is* in Bizuno (one `journal_main` row plus
`journal_item` rows, discriminated by `journal_id`).

---

## When you actually need one

Rarely. Most "custom transaction" needs are better served by an existing journal
plus a custom [total line](./01-the-myext-pattern.md#1-path-shadowing-the-workhorse)
or a [PhreeForm](./04-custom-phreeform-processor.md) view. Reach for a custom
journal only when you have a genuinely distinct transaction *type* — its own
lifecycle, its own GL personality — that doesn't fit any of jIDs 0–22.

## Pick a non-colliding journal_id

- **0–22** are PhreeBooks core.
- **30–35** are taken by extensions (Quality 30/31/34, Inventory work-order 32,
  Maintenance 35).
- **40+** is the conventionally safe range for your own. Use it, to leave room
  for future core/extension additions in the 30s.

---

## The class contract

A journal is a class `jNN` (zero-padded — `j40.php`) that **extends `jCommon`**
(the base in `journals/common.php`). The real method surface — correcting the
stub's `loadJournal`/`journalize` guesses:

| Member                                  | Role                                                              |
|-----------------------------------------|-------------------------------------------------------------------|
| `public $journalID = 40;`               | The discriminator. Required.                                      |
| `__construct($main=[], $item=[])`       | Call `parent::__construct()`, then stash `$this->main`/`$this->items` |
| `Post()`                                | Post to the DB and the GL                                         |
| `unPost()`                              | Reverse everything `Post()` did                                   |
| `getDataItem()`                         | Prepare the line-item grid for the edit screen                    |
| `customizeView(&$data, $rID, $cID, $security)` | Tailor the entry-form layout                              |
| `getRepostData()`                       | Return IDs of dependent entries that need reposting (often `[]`)  |

`jCommon` gives you the protected helpers you build on: `postMain()` /
`postItem()` and their `unPost…` counterparts, `setJournalHistory()` /
`unSetJournalHistory()` (the GL writers), and — if you touch stock —
`postInventory()`, `setInvStatus()`, and `calculateCOGS()`.

---

## A balanced skeleton — `j40`

```php
<?php
namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journals/common.php', 'jCommon');

class j40 extends jCommon
{
    public $journalID = 40;
    public $main;
    public $items;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main  = $main;
        $this->items = $item;
    }

    public function getDataItem() { /* format $this->items for the grid */ }

    public function customizeView(&$data, $rID=0, $cID=0, $security=0) { /* tweak layout */ }

    public function Post()
    {
        if (!$this->postMain())           { return; }   // write journal_main
        if (!$this->postItem())           { return; }   // write journal_item rows
        if (!$this->setJournalHistory())  { return; }   // write the GL legs
        return true;
    }

    public function unPost()
    {
        if (!$this->unSetJournalHistory()) { return; }
        if (!$this->unPostMain())          { return; }
        if (!$this->unPostItem())          { return; }
        return true;
    }

    public function getRepostData() { return []; }
}
```

---

## Making the GL balance — the part that bites

`setJournalHistory()` (in `jCommon`) is mechanical: it walks `$this->items`,
sums each line's `debit_amount` and `credit_amount` **per `gl_account`**, and
applies the totals to `journal_history` for the entry's period. It does **not**
balance the entry for you.

So your job in building the item rows is to make debits equal credits. Each GL
leg is an item with three fields:

```php
$this->items = [
  ['gl_account'=>'12000', 'debit_amount'=>100.00, 'credit_amount'=>0],   // Dr an asset
  ['gl_account'=>'40000', 'debit_amount'=>0,      'credit_amount'=>100.00], // Cr revenue
];
// sum(debits) === sum(credits)  → balanced
```

If `sum(debit_amount) !== sum(credit_amount)`, the entry is wrong — and unlike a
hand keyed General Journal, nothing in the UI is going to stop a programmatic
post. **Assert the balance yourself** before calling `setJournalHistory()`.
Mind rounding: `jCommon` compares at the currency's precision plus two, so build
your amounts at that precision.

---

## Registering it

A few small declarations make the journal first-class:

- **Discovery is by file presence.** `getJournal($jID)` maps `40` → `j40` →
  `bizAutoLoad(... /journals/j40.php)`. There's no master list to edit — the file
  existing is the registration.
- **Label:** add `journal_id_40` (and `journal_id_40_mgr` for the manager title)
  to your locale.
- **Menu + security:** add a `j40_mgr` entry (route
  `phreebooks/main/manager&jID=40`) so it appears in menus and the role editor
  can grant access.
- **Reference counter:** the next-number machinery uses `next_ref_j40`
  automatically; seed it (e.g. an initial `configuration` row) so numbering
  starts cleanly.
- **Audit log:** posting through the standard path logs via `msgLog()` like any
  journal — you get the audit entry by using the normal post flow, not by extra
  wiring.

---

## The honest caveat: custom journals don't drop into `myExt/`

This is the one extension that resists the [`myExt/` pattern](./01-the-myext-pattern.md).
`getJournal()` resolves the class **from the core library path**
(`BIZUNO_FS_LIBRARY/controllers/phreebooks/journals/jNN.php`) — it does not scan
`myExt/` for journal classes the way the registry shadows other controllers. In
practice that means a custom journal type **lives in the core tree** (you're
effectively maintaining a patched core), unless you *also* override the loader to
look in `myExt/` first.

That trade-off is part of why a custom journal should be a last resort: it's
powerful, but it doesn't get the clean fork-free deployment the rest of `myExt/`
enjoys. If you can express the need as a total line, a PhreeForm view, or an
existing journal, do that instead.

---

## Related

- [The journal_id taxonomy](../02-core-concepts/02-journal-id-taxonomy.md) — the journal model and the jID ranges
- [The myExt/ pattern](./01-the-myext-pattern.md) — and why journals are the exception to it
- [PhreeBooks journals](../04-modules-in-depth/01-phreebooks/02-journals.md) — read a real `Post()` (e.g. `j12`) before writing your own
