---
title: PhreeBooks — Agent Action Catalog
module: phreebooks
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# PhreeBooks — Agent Action Catalog

Machine-readable actions for the `phreebooks` module — Bizuno's double-entry
general-ledger / journals core. This is the **most accounting-critical module**:
many actions post or reverse GL journals and move inventory. Read the
[catalog schema](./README.md) first; route/auth/`clean()` conventions are defined there.

Pages: `main` (universal journal editor), `register`, `reconcile`, `chart`,
`budget`, `currency`, `tax`/`restfulTax`, `returns`, `fulfillment`, `dropShip`,
`autoAssy`, `drillDown`, `mainPOS`/`bizPOS`, `ediAPI`/`ediMain`, `payroll`,
`api` (journal import/export), `tools` (GL repost/repair/aging/analytics), `admin`.

## The module is journal-type-generic (jID drives everything)

One journal editor. `phreebooksMain` resolves the journal id once
(`main.php:54-57`): from the row for an existing rID, else `clean('jID','integer','get')`.
Every CRUD route (`manager`/`managerRows`/`edit`/`save`/`delete`) is the **same code
path** for every type — `jID` selects journal behavior, the security key
(**`j{jID}_mgr`**), which GL accounts post, and whether stock moves. There is no
`phreebooks/sales/save`; it is `phreebooks/main/save&jID=12`.

## Journal ID (jID) taxonomy — the safety-critical key

GL/inventory effect of `phreebooks.journal.save` and `.delete` is **entirely
determined by jID**. Know the jID before treating a save as neutral.

```yaml
jID_taxonomy:
  2:  General Journal           # manual GL; no inventory
  3:  Vendor Quote / RFQ        # NON-posting
  4:  Purchase Order            # NON-posting commitment
  6:  Vendor Receipt/Purchase   # POSTS GL (inventory asset/AP) + RECEIVES stock
  7:  Vendor Credit / AP adj    # POSTS GL (AP)
  9:  Sales Quote               # NON-posting
  10: Sales Order (SO)          # NON-posting commitment
  12: Sales Invoice/Shipment    # POSTS GL (AR/Sales/COGS) + RELIEVES stock + COGS
  13: Customer Credit / RMA     # POSTS GL; RETURNS stock
  14: Inventory Assembly        # RELIEVES components, ADDS assembly to stock
  15: Inventory adjustment      # POSTS GL + adjusts stock qty/value
  17: Bank General Deposit      # POSTS GL (cash)
  18: Cash Receipt (AR pmt)     # POSTS GL (cash debit / AR credit); no inventory
  19: Sales / AR (general)      # POSTS GL (AR)
  20: Vendor Payment (AP pmt)   # POSTS GL (cash credit / AP debit); no inventory
  21: Vendor purchase (alt)     # vendor-side posting
  22: Credit memo / refund      # POSTS GL (reverses revenue/AR)
posting_engine:
  class: journal                 # journal.php
  post: Post('insert')           # journal.php:84 — writes GL + inventory movement
  unpost: unPost()               # journal.php:119 -> Post('delete') — reverses GL + inventory
  type_vendor_jids: [2,3,4,6,7,17,20,21]   # journal.php:58
```

> **Safety fact:** `phreebooks.journal.save` runs `new journal(rID,jID,…)->Post()`,
> writing GL rows + inventory movement atomically per jID. Non-posting jIDs
> (3,4,9,10) are commitments — safe to automate freely. Posting jIDs
> (6,7,12,13,14,15,17,18,19,20,21,22) move money/stock — treat as financial
> events. `.delete` calls `unPost()` and **reverses** the posting.

## Data model summary

```yaml
tables:
  journal_main:    one row per transaction header; journal_id = jID
  journal_item:    line items + GL debit/credit distribution rows
  journal_history: period GL balances (updateJournalHistory)
  journal_cogs:    FIFO COGS layers consumed/created by stock-moving journals
  inventory / inventory_history: stock + cost, adjusted by posting jIDs
  gl_account:      chart of accounts
security_keys:
  "j{jID}_mgr": per-journal manager key for every main.php CRUD method
  j20_bulk: bulk vendor payment (NACHA/ACH)
  register: bank/GL register;  recon: reconciliation
  admin:    chart merge (level 5!), beg-bal import, journal import
  impexp:   beg-bal / journal export
gl_impact: PER-JID — see jID_taxonomy. Reads are neutral; save/delete are not.
```

---

## phreebooks.journal.list

```yaml
id: phreebooks.journal.list
title: List/query transactions of a journal type (datagrid)
route: phreebooks/main/manager
http_method: GET
ui_path: PhreeBooks ▸ (Quotes|Orders|Invoices|Purchases|General Journal|…)
auth: {sec_id: j{jID}_mgr, min_level: 1}
preconditions: [phreebooks module enabled]
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: "selects type, security key, columns; 0 = global search"}
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [read-only EasyUI datagrid; use .list.rows for raw rows]
returns: {success_signal: datagrid layout, identifier: none}
errors: [level 1 on j{jID}_mgr required]
idempotency: safe (read-only)
related: [phreebooks.journal.list.rows, phreebooks.journal.read]
confidence: high
source: src/controllers/phreebooks/main.php:85
```

## phreebooks.journal.list.rows

```yaml
id: phreebooks.journal.list.rows
title: Fetch transaction rows (data only)
route: phreebooks/main/managerRows
http_method: GET
ui_path: (AJAX backing the journal grid)
auth: {sec_id: j{jID}_mgr, min_level: 1}
preconditions: []
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: "0 forces strict mode (searches journal_item)"}
  optional:
    - {name: search, format: text, source: get}
    - {name: page, format: integer, source: get}
    - {name: rows, format: integer, source: get}
    - {name: sort, format: text, source: get}
  fixed: []
effects: {db_writes: [], gl_journal: none, inventory: none, side_effects: []}
returns: {success_signal: JSON rows + total, identifier: journal_main.id per row}
errors: [level 1 required]
idempotency: safe (read-only)
related: [phreebooks.journal.list, phreebooks.journal.read]
confidence: high
source: src/controllers/phreebooks/main.php:209
```

## phreebooks.order.list.rows

```yaml
id: phreebooks.order.list.rows
title: Fetch open order rows (order-specific grid)
route: phreebooks/main/managerRowsOrder
http_method: GET
ui_path: PhreeBooks ▸ Orders
auth: {sec_id: j{jID}_mgr, min_level: 1}
preconditions: []
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: "typically 4 (PO) or 10 (SO)"}
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects: {db_writes: [], gl_journal: none, inventory: none, side_effects: [filtered to open/unclosed orders]}
returns: {success_signal: JSON rows + total, identifier: journal_main.id}
errors: [level 1 required]
idempotency: safe (read-only)
related: [phreebooks.journal.list.rows, phreebooks.quote.to_order]
confidence: high
source: src/controllers/phreebooks/main.php:222
```

## phreebooks.bank.list.rows

```yaml
id: phreebooks.bank.list.rows
title: Fetch bank-journal rows (deposits/payments grid)
route: phreebooks/main/managerRowsBank
http_method: GET
ui_path: PhreeBooks ▸ Banking
auth: {sec_id: j{jID}_mgr, min_level: 1}
preconditions: []
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: "17/18/20/22; jID=20 adds j2 rows if user holds j2_mgr lvl1"}
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects: {db_writes: [], gl_journal: none, inventory: none, side_effects: []}
returns: {success_signal: JSON rows + total, identifier: journal_main.id}
errors: [level 1 required]
idempotency: safe (read-only)
related: [phreebooks.register.list.rows, phreebooks.bulkpay.save]
confidence: high
source: src/controllers/phreebooks/main.php:245
```

## phreebooks.journal.read

```yaml
id: phreebooks.journal.read
title: Open a transaction in the journal editor (read detail)
route: phreebooks/main/edit
http_method: GET
ui_path: PhreeBooks ▸ (any journal) ▸ open a transaction
auth: {sec_id: j{jID}_mgr, min_level: 1}
preconditions: [rID exists or rID=0 for new blank]
inputs:
  required:
    - {name: jID, format: integer, source: get}
    - {name: rID, format: integer, source: get, notes: "journal_main.id; 0 = new blank"}
  optional:
    - {name: cID, format: integer, source: get, notes: pre-fill contact}
    - {name: action, format: char, source: get, notes: "inv|ord conversion context"}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [loads header+items+totals; resolves terms/terminal_date/ship-to per jID]
returns: {success_signal: editor layout populated, identifier: rID}
errors: [level 1 required]
idempotency: safe (read-only)
related: [phreebooks.journal.save, phreebooks.journal.list.rows]
confidence: high
source: src/controllers/phreebooks/main.php:301 (edit), :311 (secID)
```

## phreebooks.journal.save

```yaml
id: phreebooks.journal.save
title: Create/update a journal transaction (POSTS GL + inventory per jID)
route: phreebooks/main/save
http_method: POST
ui_path: PhreeBooks ▸ (any journal) ▸ Save
auth:
  sec_id: j{jID}_mgr
  min_level: 2   # validateAccess("j{jID}_mgr", $rID?3:2) — 2 create, 3 update
preconditions:
  - chart + default GL accounts configured for the jID
  - contact (cID) exists for customer/vendor journals
  - sufficient on-hand stock for relieving jIDs (12,14) or backorder handling
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: "SELECTS posting behavior (see jID_taxonomy). Safety-critical."}
  optional:
    - {name: rID, format: integer, source: post, notes: "empty=create(lvl2), present=update(lvl3)"}
    - {name: contact_id_b, format: integer, source: post, notes: billing contact (cust/vendor)}
    - {name: contact_id_s, format: integer, source: post, notes: ship-to contact}
    - {name: invoice_num, format: text, source: post, notes: auto-numbered per jID if blank}
    - {name: post_date, format: date, source: post}
    - {name: terminal_date, format: date, source: post}
    - {name: store_id, format: integer, source: post}
    - {name: item line arrays, format: mixed, source: post, notes: "sku/qty/price/gl_account/debit/credit -> journal_item"}
    - {name: total fields, format: currency, source: post, notes: subtotal/tax/shipping/total via totals modules}
  fixed:
    - {name: journal_id, value: "= jID", notes: stamped from route jID}
effects:
  db_writes:
    - {table: journal_main, op: insert/update}
    - {table: journal_item, op: insert/update/delete, notes: lines + GL distribution}
    - {table: journal_history, op: update, notes: period balances}
    - {table: inventory/inventory_history/journal_cogs, op: insert/update, notes: "ONLY stock-moving jIDs 6,12,13,14,15"}
  gl_journal: "POSTS for jID 6,7,12,13,15,17,18,19,20,21,22 (balanced debit/credit). NON-POSTING 3,4,9,10. jID 2 = manual GL."
  inventory: "MOVES STOCK: jID6 +stock; jID12 -stock + COGS; jID13 +stock; jID14 relieve components +assembly; jID15 adjust. NONE for cash/AR/AP & non-posting."
  side_effects:
    - DB transaction; auto-numbers invoice_num if blank
    - currencyConvert() applies exchange rate; updateContact() stamps activity/terms
    - recurring transactions spawn future copies
returns: {success_signal: "msgStack success = msg_record_saved; rID echoed", identifier: journal_main.id (rID)}
errors:
  - permission denied (lvl2 create / lvl3 update)
  - out-of-balance GL distribution rejected
  - insufficient stock / costing errors on stock-moving jIDs
idempotency: "NOT idempotent as blind create — re-posts GL/inventory. Pass rID to update, or pre-check invoice_num. Every posting-jID save is a financial event."
related: [phreebooks.journal.read, phreebooks.journal.delete, phreebooks.autoAssy, phreebooks.quote.to_order]
confidence: high
source: src/controllers/phreebooks/main.php:613 (save); journal.php:84 (Post)
```

## phreebooks.journal.delete

```yaml
id: phreebooks.journal.delete
title: Delete a transaction (REVERSES its GL posting + inventory movement)
route: phreebooks/main/delete
http_method: GET
ui_path: PhreeBooks ▸ (any journal) ▸ open ▸ Trash
auth: {sec_id: j{jID}_mgr, min_level: 4}
preconditions:
  - transaction not in a closed period
  - no downstream document references it (e.g. SO already invoiced)
inputs:
  required:
    - {name: jID, format: integer, source: get}
    - {name: rID, format: integer, source: get, notes: journal_main.id to delete}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main, op: delete}
    - {table: journal_item, op: delete}
    - {table: journal_history, op: update, notes: period balances backed out}
    - {table: inventory/inventory_history/journal_cogs, op: update/delete, notes: stock + COGS restored for stock-moving jIDs}
  gl_journal: "REVERSES via unPost() -> Post('delete'). Posting jIDs back debits/credits out of history; non-posting jIDs just remove the commitment row."
  inventory: "REVERSES movement: re-adds relieved stock (12), removes received (6), unwinds assembly (14), reverses adjustment (15)."
  side_effects: [DB transaction; grid reloads]
returns: {success_signal: delete completed; grid reloads, identifier: none}
errors:
  - permission denied (level 4)
  - blocked if period closed or referenced downstream
idempotency: idempotent (deleting a gone row is a no-op); reversal posting happens once
related: [phreebooks.journal.save]
confidence: high
source: src/controllers/phreebooks/main.php:969 (delete); journal.php:119 (unPost)
```

## phreebooks.quote.to_order

```yaml
id: phreebooks.quote.to_order
title: Convert quote->order or order->invoice (status promotion)
route: phreebooks/main/edit
http_method: GET
ui_path: PhreeBooks ▸ Quote/Order ▸ Convert
auth: {sec_id: j{jID}_mgr, min_level: 2}
preconditions: [source quote/order rID exists]
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: target journal (10 SO, 12 invoice)}
    - {name: rID, format: integer, source: get, notes: source transaction id}
    - {name: action, format: char, source: get, notes: "ord|inv (main.php:54 excludes from rID->jID lookup)"}
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [loads source pre-filled as target jID; no posting until subsequent journal.save]
returns: {success_signal: editor pre-filled at target jID, identifier: source rID}
errors: [level 2 required]
idempotency: safe (read-only step); posting is the subsequent save
related: [phreebooks.journal.save, phreebooks.order.list.rows]
confidence: medium   # editor pre-fill via action param; verify per jID pair
source: src/controllers/phreebooks/main.php:301 (edit), :54
```

## phreebooks.bulkpay.save

```yaml
id: phreebooks.bulkpay.save
title: Bulk vendor payment run (NACHA/ACH or check batch)
route: phreebooks/main/saveBulk
http_method: POST
ui_path: PhreeBooks ▸ Vendor Payments ▸ Bulk Pay
auth: {sec_id: j20_bulk, min_level: 2}
preconditions:
  - bank account / ACH origination configured
  - selected open AP invoices exist
inputs:
  required:
    - {name: selected invoice/vendor ids, format: mixed, source: post}
  optional:
    - {name: post_date, format: date, source: post}
    - {name: bank gl_account, format: text, source: post}
  fixed:
    - {name: journal_id, value: "20", notes: each payment posts as jID 20}
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert, notes: one jID20 payment per vendor}
    - {table: journal_history, op: update}
  gl_journal: "POSTS one vendor payment (jID20) per vendor: debit AP, credit cash. Multiple postings per batch."
  inventory: none
  side_effects: [generates NACHA/ACH file (nachaMaps/); marks AP invoices paid]
returns: {success_signal: batch summary + NACHA download, identifier: per-payment journal_main.id}
errors: [permission denied (j20_bulk level 2)]
idempotency: NOT idempotent — re-running pays again. Verify paid status first.
related: [phreebooks.bank.list.rows, phreebooks.journal.save]
confidence: medium   # batch + NACHA config-heavy; verify bank/ACH setup
source: src/controllers/phreebooks/main.php:770 (saveBulk)
```

## phreebooks.notes.save

```yaml
id: phreebooks.notes.save
title: Save internal notes on a transaction
route: phreebooks/main/notesSave
http_method: POST
ui_path: PhreeBooks ▸ open transaction ▸ Notes
auth: {sec_id: NONE (ungated), min_level: n/a}   # no validateAccess — see Open questions
preconditions: [rID exists]
inputs:
  required:
    - {name: rID, format: integer, source: get}
    - {name: notes, format: text, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main, op: update, notes: notes/description on header}
  gl_journal: none
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: none}
errors: [silent no-op if rID missing]
idempotency: idempotent (overwrites notes)
related: [phreebooks.detail.status]
confidence: high
source: src/controllers/phreebooks/main.php:1082
```

## phreebooks.detail.status

```yaml
id: phreebooks.detail.status
title: Render/refresh a transaction's status detail panel
route: phreebooks/main/detailStatus
http_method: GET
ui_path: PhreeBooks ▸ open transaction ▸ Status panel
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [rID exists]
inputs:
  required:
    - {name: rID, format: integer, source: get}
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [reads journal_main/item to compute fulfillment/payment status]
returns: {success_signal: status panel layout, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.notes.save, phreebooks.status.toggle]
confidence: high
source: src/controllers/phreebooks/main.php:1154
```

## phreebooks.delivery.dates.save

```yaml
id: phreebooks.delivery.dates.save
title: Save promised/delivery dates on an order
route: phreebooks/main/deliveryDatesSave
http_method: POST
ui_path: PhreeBooks ▸ Order ▸ Delivery dates
auth: {sec_id: j{jID}_mgr, min_level: 3}
preconditions: [order rID exists]
inputs:
  required:
    - {name: jID, format: integer, source: get}
    - {name: rID, format: integer, source: post}
    - {name: per-line delivery dates, format: date, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_item, op: update, notes: delivery/promised date columns}
  gl_journal: none
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: rID}
errors: [permission denied (level 3)]
idempotency: idempotent (overwrites date fields)
related: [phreebooks.journal.save]
confidence: high
source: src/controllers/phreebooks/main.php:1880
```

## phreebooks.journal.balance

```yaml
id: phreebooks.journal.balance
title: Recompute/display a vendor-payment journal balance
route: phreebooks/main/(balance)
http_method: GET
ui_path: PhreeBooks ▸ Vendor Payments ▸ balance
auth: {sec_id: j20_mgr, min_level: 3}
preconditions: []
inputs:
  required:
    - {name: jID, format: integer, source: get}
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [computes running balance for the payment journal display]
returns: {success_signal: balance figure, identifier: none}
errors: [permission denied (j20_mgr level 3)]
idempotency: safe (read-only)
related: [phreebooks.bank.list.rows]
confidence: medium
source: src/controllers/phreebooks/main.php:1899 (validateAccess('j20_mgr',3) at :1901)
```

## phreebooks.status.toggle

```yaml
id: phreebooks.status.toggle
title: Toggle a transaction status flag (closed/printed/waiting)
route: phreebooks/main/(status toggle)
http_method: POST
ui_path: PhreeBooks ▸ transaction grid ▸ status checkbox
auth: {sec_id: j{jID}_mgr, min_level: 3}
preconditions: [rID exists]
inputs:
  required:
    - {name: jID, format: integer, source: get}
    - {name: rID, format: integer, source: post}
  optional:
    - {name: status flag name/value, format: char, source: post}
  fixed: []
effects:
  db_writes:
    - {table: journal_main, op: update, notes: a status flag column}
  gl_journal: none
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success; grid refresh, identifier: rID}
errors: [permission denied (level 3)]
idempotency: idempotent for a set value; a true toggle flips on repeat — pass explicit value
related: [phreebooks.detail.status]
confidence: medium
source: src/controllers/phreebooks/main.php:2020-2049 (level 3)
```

## phreebooks.recur.popup

```yaml
id: phreebooks.recur.popup
title: Open the recurring-transaction setup popup
route: phreebooks/main/popupRecur
http_method: GET
ui_path: PhreeBooks ▸ transaction ▸ Recur
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [source rID exists]
inputs:
  required:
    - {name: rID, format: integer, source: get}
  optional:
    - {name: jID, format: integer, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders recurrence popup; persistence happens in journal.save]
returns: {success_signal: popup layout, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.journal.save]
confidence: high
source: src/controllers/phreebooks/main.php:2067
```

## phreebooks.buysell.edit

```yaml
id: phreebooks.buysell.edit
title: Open buy/sell (intercompany/markup) editor
route: phreebooks/main/buySellEdit
http_method: GET
ui_path: PhreeBooks ▸ transaction ▸ Buy/Sell
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required:
    - {name: rID, format: integer, source: get}
  optional:
    - {name: jID, format: integer, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders buy/sell config editor]
returns: {success_signal: editor layout, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.buysell.save]
confidence: high
source: src/controllers/phreebooks/main.php:2111
```

## phreebooks.buysell.save

```yaml
id: phreebooks.buysell.save
title: Save buy/sell (intercompany/markup) configuration
route: phreebooks/main/buySellSave
http_method: POST
ui_path: PhreeBooks ▸ transaction ▸ Buy/Sell ▸ Save
auth: {sec_id: NONE (ungated), min_level: n/a}   # WRITE path; see Open questions
preconditions: []
inputs:
  required:
    - {name: rID, format: integer, source: get}
    - {name: buy/sell field values, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main/journal_item, op: update, notes: buy/sell pricing/markup}
  gl_journal: none   # config only; GL realized when related transaction posts
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: rID}
errors: [none enforced server-side (ungated)]
idempotency: idempotent (overwrites config)
related: [phreebooks.buysell.edit, phreebooks.journal.save]
confidence: medium
source: src/controllers/phreebooks/main.php:2145
```

## phreebooks.register.list

```yaml
id: phreebooks.register.list
title: Bank/GL register view (datagrid)
route: phreebooks/register/manager
http_method: GET
ui_path: PhreeBooks ▸ Banking ▸ Register
auth: {sec_id: register, min_level: 1}
preconditions: [a bank/GL account selected]
inputs:
  required: []
  optional:
    - {name: gl_account, format: text, source: get}
    - {name: search, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders register datagrid]
returns: {success_signal: register layout, identifier: none}
errors: [permission denied (register level 1)]
idempotency: safe (read-only)
related: [phreebooks.register.list.rows, phreebooks.reconcile.list]
confidence: high
source: src/controllers/phreebooks/register.php:37 (validateAccess('register',1) at :39)
```

## phreebooks.register.list.rows

```yaml
id: phreebooks.register.list.rows
title: Fetch register rows (data only)
route: phreebooks/register/managerRows
http_method: GET
ui_path: (AJAX backing the register grid)
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: gl_account, format: text, source: get}
    - {name: page, format: integer, source: get}
    - {name: rows, format: integer, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [returns register rows (running balance) — read-only but ungated, exposes GL]
returns: {success_signal: JSON rows + total, identifier: journal_main.id}
errors: []
idempotency: safe (read-only)
related: [phreebooks.register.list]
confidence: high
source: src/controllers/phreebooks/register.php:54
```

## phreebooks.reconcile.list

```yaml
id: phreebooks.reconcile.list
title: Bank reconciliation worksheet
route: phreebooks/reconcile/manager
http_method: GET
ui_path: PhreeBooks ▸ Banking ▸ Reconcile
auth: {sec_id: recon, min_level: 3}
preconditions: [bank gl_account + statement period selected]
inputs:
  required:
    - {name: gl_account, format: text, source: get}
  optional:
    - {name: period, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders reconciliation worksheet]
returns: {success_signal: reconcile layout, identifier: none}
errors: [permission denied (recon level 3)]
idempotency: safe (read-only)
related: [phreebooks.reconcile.save, phreebooks.register.list]
confidence: high
source: src/controllers/phreebooks/reconcile.php:37 (validateAccess('recon',3) at :39)
```

## phreebooks.reconcile.save

```yaml
id: phreebooks.reconcile.save
title: Save/finalize a bank reconciliation
route: phreebooks/reconcile/save
http_method: POST
ui_path: PhreeBooks ▸ Banking ▸ Reconcile ▸ Save
auth: {sec_id: recon, min_level: 3}
preconditions: [cleared items selected; statement ending balance entered]
inputs:
  required:
    - {name: gl_account, format: text, source: post}
    - {name: cleared transaction ids, format: mixed, source: post}
    - {name: ending_balance, format: currency, source: post}
  optional:
    - {name: period, format: text, source: post}
  fixed: []
effects:
  db_writes:
    - {table: journal_main / reconciliation records, op: update, notes: marks cleared/reconciled}
  gl_journal: none   # cleared status only; posts no new GL
  inventory: none
  side_effects: [locks reconciled period when balanced]
returns: {success_signal: msgStack success; balanced, identifier: none}
errors: [permission denied (recon level 3); out-of-balance rejected]
idempotency: idempotent for same cleared set; re-saving updates flags
related: [phreebooks.reconcile.list, phreebooks.register.list]
confidence: medium
source: src/controllers/phreebooks/reconcile.php:76, :167
```

## phreebooks.chart.list

```yaml
id: phreebooks.chart.list
title: Chart of accounts manager (datagrid)
route: phreebooks/chart/manager
http_method: GET
ui_path: PhreeBooks ▸ Settings ▸ Chart of Accounts
auth: {sec_id: admin, min_level: 1}   # chart secID = 'admin' (chart.php:36)
preconditions: []
inputs:
  required: []
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders chart-of-accounts datagrid]
returns: {success_signal: chart layout, identifier: none}
errors: [permission denied (admin level 1)]
idempotency: safe (read-only)
related: [phreebooks.chart.save, phreebooks.chart.list.rows]
confidence: high
source: src/controllers/phreebooks/chart.php:110 (validateAccess(secID,1) at :112)
```

## phreebooks.chart.list.rows

```yaml
id: phreebooks.chart.list.rows
title: Fetch chart-of-accounts rows (data only)
route: phreebooks/chart/managerRows
http_method: GET
ui_path: (AJAX backing the chart grid)
auth: {sec_id: admin, min_level: 1}
preconditions: []
inputs:
  required: []
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects: {db_writes: [], gl_journal: none, inventory: none, side_effects: []}
returns: {success_signal: JSON rows + total, identifier: gl_account id}
errors: [permission denied (admin level 1)]
idempotency: safe (read-only)
related: [phreebooks.chart.list]
confidence: high
source: src/controllers/phreebooks/chart.php:126 (validateAccess(secID,1) at :128)
```

## phreebooks.chart.save

```yaml
id: phreebooks.chart.save
title: Add or edit a chart-of-accounts (GL) account
route: phreebooks/chart/save
http_method: POST
ui_path: PhreeBooks ▸ Chart of Accounts ▸ New/Edit
auth:
  sec_id: admin
  min_level: 2   # validateAccess(secID, empty($glID)?2:3) — 2 add, 3 edit
preconditions: []
inputs:
  required:
    - {name: gl_account, format: text, source: post, notes: "account number; empty glID=create(lvl2), present=edit(lvl3)"}
    - {name: description, format: text, source: post}
    - {name: type, format: integer, source: post, notes: chart type from PHREEBOOKS_CHART_TYPES}
  optional:
    - {name: heading, format: char, source: post}
    - {name: inactive, format: char, source: post}
  fixed: []
effects:
  db_writes:
    - {table: gl_account, op: insert/update}
  gl_journal: none   # defines an account; posts no transaction
  inventory: none
  side_effects: [refreshes chart cache]
returns: {success_signal: msgStack success, identifier: gl_account}
errors: [permission denied (admin 2 add / 3 edit); duplicate account rejected]
idempotency: idempotent on gl_account (upsert by account number)
related: [phreebooks.chart.delete, phreebooks.chart.merge.save]
confidence: high
source: src/controllers/phreebooks/chart.php:227 (validateAccess at :230)
```

## phreebooks.chart.delete

```yaml
id: phreebooks.chart.delete
title: Delete a chart-of-accounts account
route: phreebooks/chart/delete
http_method: GET
ui_path: PhreeBooks ▸ Chart of Accounts ▸ Trash
auth: {sec_id: admin, min_level: 4}
preconditions: [account has no posted journal_item rows]
inputs:
  required:
    - {name: gl_account, format: text, source: get}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: gl_account, op: delete}
  gl_journal: none
  inventory: none
  side_effects: [refreshes chart cache]
returns: {success_signal: delete completed; grid reload, identifier: none}
errors: [permission denied (admin level 4); blocked if posted activity]
idempotency: idempotent (deleting an absent account is a no-op)
related: [phreebooks.chart.merge.save]
confidence: high
source: src/controllers/phreebooks/chart.php:352 (validateAccess(secID,4) at :355)
```

## phreebooks.chart.merge.save

```yaml
id: phreebooks.chart.merge.save
title: Merge one GL account into another (repoints all journal_item rows)
route: phreebooks/chart/mergeSave
http_method: POST
ui_path: PhreeBooks ▸ Chart of Accounts ▸ Merge
auth:
  sec_id: admin
  min_level: 5   # *** LEVEL 5 — ABOVE the documented 1-4 scale; see Open questions ***
preconditions: [source + destination GL accounts both exist]
inputs:
  required:
    - {name: source gl_account, format: text, source: post}
    - {name: destination gl_account, format: text, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_item (+ gl_account, history), op: update/delete, notes: repoints all distribution rows source->dest, removes source}
  gl_journal: none   # repoints existing postings; no new posting but RE-ATTRIBUTES history
  inventory: none
  side_effects: [historical GL reporting changes; period balances recomputed]
returns: {success_signal: merge completion message, identifier: surviving dest gl_account}
errors: [permission denied (admin level 5 — above 1-4 scale)]
idempotency: NOT idempotent — source ceases to exist after first run
related: [phreebooks.chart.delete]
confidence: high
source: src/controllers/phreebooks/chart.php:288 (validateAccess('admin',5) at :290)
```

## phreebooks.chart.merge

```yaml
id: phreebooks.chart.merge
title: Open the chart-merge popup
route: phreebooks/chart/merge
http_method: GET
ui_path: PhreeBooks ▸ Chart of Accounts ▸ Merge (open)
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs: {required: [], optional: [], fixed: []}
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders merge form; actual merge is mergeSave (level 5)]
returns: {success_signal: merge popup layout, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.chart.merge.save]
confidence: high
source: src/controllers/phreebooks/chart.php:269
```

## phreebooks.chart.export

```yaml
id: phreebooks.chart.export
title: Export chart of accounts to CSV
route: phreebooks/chart/export
http_method: GET
ui_path: PhreeBooks ▸ Chart of Accounts ▸ Export
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs: {required: [], optional: [], fixed: []}
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [streams chart CSV (exposes GL structure; ungated)]
returns: {success_signal: file download, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.chart.list]
confidence: high
source: src/controllers/phreebooks/chart.php:422
```

## phreebooks.autoAssy

```yaml
id: phreebooks.autoAssy
title: Auto-build inventory assemblies (jID 14) to satisfy demand
route: phreebooks/autoAssy/autoAssy
http_method: POST
ui_path: PhreeBooks ▸ Inventory ▸ Auto-Assembly
auth: {sec_id: j14_mgr, min_level: 2}
preconditions:
  - assembly SKU has a bill of materials
  - sufficient component stock to build
inputs:
  required:
    - {name: assembly sku / qty to build, format: mixed, source: post}
  optional: []
  fixed:
    - {name: journal_id, value: "14", notes: each build posts as jID 14}
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert, notes: one jID14 assembly per build}
    - {table: inventory/inventory_history/journal_cogs, op: update/insert, notes: components consumed; finished-good layers created}
  gl_journal: "POSTS jID14: moves cost from component inventory accounts into the assembled finished-good account (no P&L unless variance)."
  inventory: "RELIEVES component SKUs (qty down, COGS layers consumed) and ADDS the assembled SKU at rolled-up component cost. Real stock movement."
  side_effects: [DB transaction; may build in batches]
returns: {success_signal: msgStack success; assemblies built, identifier: per-build journal_main.id}
errors: [permission denied (j14_mgr level 2); insufficient component stock]
idempotency: NOT idempotent — each run builds/posts again. Verify demand/stock first.
related: [phreebooks.journal.save, phreebooks.dropShip]
confidence: high
source: src/controllers/phreebooks/autoAssy.php:41 (validateAccess('j14_mgr',2) at :43)
```

## phreebooks.dropShip

```yaml
id: phreebooks.dropShip
title: Create a drop-ship PO from a sales-order line
route: phreebooks/dropShip/(main)
http_method: POST
ui_path: PhreeBooks ▸ Sales Order ▸ Drop Ship
auth: {sec_id: j4_mgr, min_level: 2}   # PO creation gate (main.php:1622)
preconditions: [source SO line exists; vendor selectable]
inputs:
  required:
    - {name: rID, format: integer, source: get, notes: source sales order id}
    - {name: vendor / line selection, format: mixed, source: post}
  optional: []
  fixed:
    - {name: journal_id, value: "4", notes: creates a jID4 PO (non-posting)}
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert, notes: jID4 PO linked to SO line}
  gl_journal: none   # PO (jID4) is a non-posting commitment until received
  inventory: none    # no stock until received (jID6)
  side_effects: [links PO to originating SO for fulfillment tracking]
returns: {success_signal: drop-ship PO created, identifier: new PO journal_main.id}
errors: [permission denied (j4_mgr level 2)]
idempotency: NOT idempotent — re-running creates another PO
related: [phreebooks.journal.save, phreebooks.fulfillment]
confidence: medium
source: src/controllers/phreebooks/dropShip.php; main.php:1622
```

## phreebooks.fulfillment

```yaml
id: phreebooks.fulfillment
title: Order fulfillment workbench (pick/pack/ship)
route: phreebooks/fulfillment/fulfillMain
http_method: GET
ui_path: PhreeBooks ▸ Sales ▸ Fulfillment
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: search, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders workbench (open orders waiting to ship)]
returns: {success_signal: fulfillment layout, identifier: none}
errors: []
idempotency: safe (read-only); invoicing/shipping that moves stock is journal.save (jID12)
related: [phreebooks.journal.save, phreebooks.order.list.rows]
confidence: high
source: src/controllers/phreebooks/fulfillment.php:47
```

## phreebooks.returns

```yaml
id: phreebooks.returns
title: Process a customer return / RMA (jID 13)
route: phreebooks/returns/(save)
http_method: POST
ui_path: PhreeBooks ▸ Sales ▸ Returns
auth: {sec_id: j13_mgr, min_level: 2}
preconditions: [original sales invoice referenced; returned items identified]
inputs:
  required:
    - {name: rID, format: integer, source: post, notes: RMA/return id (0=new)}
    - {name: returned line items, format: mixed, source: post}
  optional: []
  fixed:
    - {name: journal_id, value: "13", notes: posts as jID13 customer credit/return}
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert/update}
    - {table: inventory/inventory_history/journal_cogs, op: update, notes: returned items added back to stock}
  gl_journal: "POSTS jID13: reverses revenue/AR for returned portion, restores COGS."
  inventory: "RETURNS stock — returned SKUs added back to qty_stock with restored cost layers."
  side_effects: [may issue credit memo / refund (jID22) downstream]
returns: {success_signal: msgStack success; return posted, identifier: journal_main.id}
errors: [permission denied (j13_mgr level 2)]
idempotency: NOT idempotent — re-posting duplicates the credit and stock return
related: [phreebooks.journal.save, phreebooks.journal.delete]
confidence: medium
source: src/controllers/phreebooks/returns.php
```

## phreebooks.drillDown

```yaml
id: phreebooks.drillDown
title: Drill from a GL balance into underlying transactions
route: phreebooks/drillDown/(main)
http_method: GET
ui_path: PhreeBooks ▸ Reports/Register ▸ drill-down
auth: {sec_id: register, min_level: 1}
preconditions: [a gl_account/period selected]
inputs:
  required:
    - {name: gl_account, format: text, source: get}
  optional:
    - {name: period, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [returns journal_item rows composing a GL balance]
returns: {success_signal: drill-down grid, identifier: journal_main.id}
errors: [permission denied (register level 1)]
idempotency: safe (read-only)
related: [phreebooks.register.list, phreebooks.journal.read]
confidence: medium
source: src/controllers/phreebooks/drillDown.php
```

## phreebooks.mainPOS.payment

```yaml
id: phreebooks.mainPOS.payment
title: Take a payment in the Point-of-Sale window
route: phreebooks/mainPOS/bizWinPmt
http_method: POST
ui_path: PhreeBooks ▸ POS ▸ Payment
auth: {sec_id: NONE (ungated), min_level: n/a}   # WRITE/payment path; see Open questions
preconditions: [an open POS sale exists]
inputs:
  required:
    - {name: payment amount/method, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert/update, notes: POS sale + payment (jID12 invoice + jID18 receipt)}
  gl_journal: "POSTS POS sale (jID12: AR/Sales/COGS) and cash receipt (jID18: cash debit)."
  inventory: "RELIEVES stock for sold items (jID12 path)."
  side_effects: [may capture a card payment through the payment module]
returns: {success_signal: payment applied; receipt generated, identifier: journal_main.id(s)}
errors: [none enforced server-side (ungated)]
idempotency: NOT idempotent — re-running takes another payment
related: [phreebooks.journal.save]
confidence: medium   # ungated payment write; high caution
source: src/controllers/phreebooks/mainPOS.php:178
```

## phreebooks.tax.bulkChange

```yaml
id: phreebooks.tax.bulkChange
title: Bulk-change tax rate assignments
route: phreebooks/tax/bulkChange
http_method: POST
ui_path: PhreeBooks ▸ Settings ▸ Taxes ▸ Bulk change
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required:
    - {name: old tax id / new tax id, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: contacts / inventory (tax_rate_id), op: update, notes: reassigns a tax rate}
  gl_journal: none
  inventory: none
  side_effects: [bulk reassignment affects future tax calculations]
returns: {success_signal: msgStack success, identifier: none}
errors: [none enforced server-side (ungated)]
idempotency: idempotent for a fixed old->new mapping
related: [phreebooks.restfulTax.save]
confidence: medium
source: src/controllers/phreebooks/tax.php:202
```

## phreebooks.restfulTax.save

```yaml
id: phreebooks.restfulTax.save
title: Save a tax rate definition (REST tax service)
route: phreebooks/restfulTax/save
http_method: POST
ui_path: PhreeBooks ▸ Settings ▸ Taxes ▸ Save
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required:
    - {name: tax rate fields, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: tax_rates, op: insert/update}
  gl_journal: none   # tax GL realized when a transaction posts
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: tax rate id}
errors: [none enforced server-side (ungated)]
idempotency: idempotent (upsert by tax rate id)
related: [phreebooks.tax.bulkChange, phreebooks.restfulTax.calc]
confidence: medium
source: src/controllers/phreebooks/restfulTax.php:124
```

## phreebooks.restfulTax.calc

```yaml
id: phreebooks.restfulTax.calc
title: Compute tax collected for a period
route: phreebooks/restfulTax/calcTaxCollected
http_method: GET
ui_path: PhreeBooks ▸ Taxes ▸ Tax collected report
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: period, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [aggregates tax collected from journal_item]
returns: {success_signal: JSON tax totals, identifier: none}
errors: []
idempotency: safe (read-only); ungated (exposes tax data)
related: [phreebooks.restfulTax.save]
confidence: medium
source: src/controllers/phreebooks/restfulTax.php:339
```

## phreebooks.currency.add

```yaml
id: phreebooks.currency.add
title: Add a currency
route: phreebooks/currency/add
http_method: POST
ui_path: PhreeBooks ▸ Settings ▸ Currencies ▸ Add
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required:
    - {name: iso, format: text, source: post, notes: ISO currency code}
  optional:
    - {name: rate, format: float, source: post}
  fixed: []
effects:
  db_writes:
    - {table: currency settings, op: insert}
  gl_journal: none
  inventory: none
  side_effects: [new currency selectable on transactions]
returns: {success_signal: msgStack success, identifier: currency iso}
errors: [none enforced server-side (ungated)]
idempotency: idempotent (upsert by iso)
related: [phreebooks.currency.setExcRate, phreebooks.currency.update]
confidence: medium
source: src/controllers/phreebooks/currency.php:62
```

## phreebooks.currency.setExcRate

```yaml
id: phreebooks.currency.setExcRate
title: Set/refresh a currency exchange rate
route: phreebooks/currency/setExcRate
http_method: POST
ui_path: PhreeBooks ▸ Settings ▸ Currencies ▸ Rate
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [currency already added]
inputs:
  required:
    - {name: iso, format: text, source: post}
    - {name: rate, format: float, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: currency settings, op: update}
  gl_journal: none   # affects conversion on future postings only
  inventory: none
  side_effects: [changes currencyConvert() for new foreign-currency transactions]
returns: {success_signal: msgStack success, identifier: iso}
errors: [none enforced server-side (ungated)]
idempotency: idempotent (overwrites rate)
related: [phreebooks.currency.add, phreebooks.currency.update]
confidence: medium
source: src/controllers/phreebooks/currency.php:220
```

## phreebooks.currency.update

```yaml
id: phreebooks.currency.update
title: Update currency settings
route: phreebooks/currency/update
http_method: POST
ui_path: PhreeBooks ▸ Settings ▸ Currencies ▸ Save
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required:
    - {name: iso, format: text, source: post}
  optional:
    - {name: currency display/precision fields, format: mixed, source: post}
  fixed: []
effects:
  db_writes:
    - {table: currency settings, op: update}
  gl_journal: none
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: iso}
errors: [none enforced server-side (ungated)]
idempotency: idempotent (overwrites settings)
related: [phreebooks.currency.add, phreebooks.currency.setExcRate]
confidence: medium
source: src/controllers/phreebooks/currency.php:237
```

## phreebooks.budget

```yaml
id: phreebooks.budget
title: GL budget manager (set per-account period budgets)
route: phreebooks/budget/(manager/save)
http_method: GET/POST
ui_path: PhreeBooks ▸ Reports ▸ Budgets
auth: {sec_id: admin, min_level: 1}   # view; saves require higher — verify
preconditions: []
inputs:
  required: []
  optional:
    - {name: gl_account, format: text, source: get}
    - {name: period budget amounts, format: currency, source: post}
  fixed: []
effects:
  db_writes:
    - {table: budget records, op: insert/update, notes: on save only}
  gl_journal: none   # budgets are planning data, never posted
  inventory: none
  side_effects: [budgets feed budget-vs-actual reports]
returns: {success_signal: budget grid / msgStack success, identifier: none}
errors: [permission denied per guard]
idempotency: idempotent on save (overwrites period budget)
related: [phreebooks.chart.list]
confidence: low   # budget guards not individually verified
source: src/controllers/phreebooks/budget.php
```

## phreebooks.payroll.importForm

```yaml
id: phreebooks.payroll.importForm
title: Open the payroll import form
route: phreebooks/payroll/importForm
http_method: GET
ui_path: PhreeBooks ▸ Payroll ▸ Import
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs: {required: [], optional: [], fixed: []}
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [renders payroll-import upload form]
returns: {success_signal: import form layout, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.payroll.importGo]
confidence: high
source: src/controllers/phreebooks/payroll.php:34
```

## phreebooks.payroll.importGo

```yaml
id: phreebooks.payroll.importGo
title: Execute a payroll import (posts payroll GL entries)
route: phreebooks/payroll/importGo
http_method: POST
ui_path: PhreeBooks ▸ Payroll ▸ Import ▸ Go
auth: {sec_id: NONE (ungated), min_level: n/a}   # POSTS GL; see Open questions
preconditions: [a valid payroll import file uploaded]
inputs:
  required:
    - {name: payroll import file / mapping, format: file, source: post}
  optional: []
  fixed:
    - {name: journal_id, value: "2", notes: payroll typically posts as general-journal (jID2)}
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert, notes: payroll GL transactions}
    - {table: journal_history, op: update}
  gl_journal: "POSTS payroll GL: wage expense, tax/withholding liabilities, net-pay cash."
  inventory: none
  side_effects: [imports per-employee runs in a batch]
returns: {success_signal: import summary; posted, identifier: per-run journal_main.id(s)}
errors: [none enforced server-side (ungated) — high caution, posts GL]
idempotency: NOT idempotent — re-importing re-posts payroll
related: [phreebooks.journal.save]
confidence: medium
source: src/controllers/phreebooks/payroll.php:43
```

## phreebooks.ediAPI.get

```yaml
id: phreebooks.ediAPI.get
title: Retrieve EDI documents from the trading partner
route: phreebooks/ediAPI/ediGet
http_method: GET
ui_path: PhreeBooks ▸ EDI ▸ Receive
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [EDI partner/mailbox configured]
inputs:
  required: []
  optional:
    - {name: partner / doc type, format: mixed, source: get}
  fixed: []
effects:
  db_writes:
    - {table: EDI staging tables, op: insert, notes: fetched documents staged}
  gl_journal: none
  inventory: none
  side_effects: [pulls documents from the EDI mailbox/API]
returns: {success_signal: documents fetched count, identifier: none}
errors: [none enforced server-side (ungated)]
idempotency: depends on partner (may re-fetch already-seen docs)
related: [phreebooks.ediAPI.manual, phreebooks.ediAPI.transmit]
confidence: medium
source: src/controllers/phreebooks/ediAPI.php:106
```

## phreebooks.ediAPI.manual

```yaml
id: phreebooks.ediAPI.manual
title: Manually process/map a staged EDI document into a journal
route: phreebooks/ediAPI/ediManual
http_method: POST
ui_path: PhreeBooks ▸ EDI ▸ Process
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [a staged EDI document exists]
inputs:
  required:
    - {name: staged doc id / mapping, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert, notes: creates corresponding journal (e.g. SO from 850)}
  gl_journal: "MAY POST depending on resulting jID (e.g. 810 invoice -> jID12 posts)."
  inventory: "MAY MOVE STOCK if resulting jID is stock-moving (e.g. 12)."
  side_effects: [maps EDI segments to a Bizuno transaction]
returns: {success_signal: journal created from EDI doc, identifier: journal_main.id}
errors: [none enforced server-side (ungated) — high caution]
idempotency: NOT idempotent — re-processing duplicates the journal
related: [phreebooks.ediAPI.get, phreebooks.journal.save]
confidence: low   # mapping -> jID is partner/data dependent
source: src/controllers/phreebooks/ediAPI.php:153
```

## phreebooks.ediAPI.transmit

```yaml
id: phreebooks.ediAPI.transmit
title: Transmit outbound EDI documents to the trading partner
route: phreebooks/ediAPI/ediTransmit
http_method: POST
ui_path: PhreeBooks ▸ EDI ▸ Send
auth: {sec_id: NONE (ungated), min_level: n/a}   # *** UNGATED TRANSMIT; see Open questions ***
preconditions: [outbound documents queued; partner connection configured]
inputs:
  required: []
  optional:
    - {name: doc selection, format: mixed, source: post}
  fixed: []
effects:
  db_writes:
    - {table: EDI log/status tables, op: update, notes: marks documents transmitted}
  gl_journal: none
  inventory: none
  side_effects: [SENDS data to an external trading partner (irreversible external effect)]
returns: {success_signal: transmission confirmation, identifier: none}
errors: [none enforced server-side (ungated) — irreversible external effect, high caution]
idempotency: NOT idempotent — re-transmits documents to the partner
related: [phreebooks.ediAPI.get, phreebooks.ediAPI.manual]
confidence: medium
source: src/controllers/phreebooks/ediAPI.php:189
```

## phreebooks.api.journal

```yaml
id: phreebooks.api.journal
title: REST endpoint to read/create a journal transaction via API
route: phreebooks/api/journalAPI
http_method: GET/POST
ui_path: (programmatic REST surface)
auth: {sec_id: NONE (ungated at method), min_level: n/a}   # no validateAccess at :48; see Open questions
preconditions: [valid API session]
inputs:
  required:
    - {name: jID, format: integer, source: get, notes: journal type to act on}
  optional:
    - {name: transaction payload, format: mixed, source: post, notes: header + items for a create}
  fixed: []
effects:
  db_writes:
    - {table: journal_main/journal_item, op: insert/update, notes: on create payloads}
  gl_journal: "POSTS per jID when creating (delegates to journal class) — same semantics as journal.save."
  inventory: "MOVES STOCK per jID on create (same as journal.save)."
  side_effects: [JSON in / JSON out]
returns: {success_signal: JSON with resulting transaction id, identifier: journal_main.id}
errors: [relies on API session layer for auth (method ungated) — verify]
idempotency: NOT idempotent for create — supply rID to update; else dedupe by invoice_num
related: [phreebooks.journal.save, phreebooks.api.importJournal]
confidence: medium
source: src/controllers/phreebooks/api.php:48
```

## phreebooks.api.importJournal

```yaml
id: phreebooks.api.importJournal
title: Bulk-import journal transactions from a file (posts GL)
route: phreebooks/api/importJournal
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Import journals
auth: {sec_id: admin, min_level: 4}
preconditions: [import file matches journal template; periods open]
inputs:
  required:
    - {name: journal import file, format: file, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_main/journal_item/journal_history, op: insert/update}
  gl_journal: "POSTS each imported transaction per its jID (full GL). Highest-impact bulk write."
  inventory: "MOVES STOCK for any stock-moving jIDs in the file."
  side_effects: [DB transaction; per-row results reported]
returns: {success_signal: import summary (added/updated), identifier: per-row journal_main.id}
errors: [permission denied (admin level 4)]
idempotency: NOT idempotent unless rows carry existing ids — verify dedupe
related: [phreebooks.api.journal, phreebooks.api.begBalImport]
confidence: high
source: src/controllers/phreebooks/api.php:258 (validateAccess('admin',4) at :262)
```

## phreebooks.api.begBalImport

```yaml
id: phreebooks.api.begBalImport
title: Import GL beginning balances
route: phreebooks/api/importBegBal
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Beginning balances import
auth: {sec_id: admin, min_level: 4}
preconditions: [chart configured; correct fiscal period]
inputs:
  required:
    - {name: beginning-balance file, format: file, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_history (period balances), op: insert/update}
  gl_journal: "Sets opening GL balances per account — GL effect on period balances."
  inventory: none
  side_effects: [validates balances net to zero]
returns: {success_signal: import summary, identifier: none}
errors: [permission denied (admin level 4); unbalanced rejected]
idempotency: idempotent per account+period (overwrites opening balance)
related: [phreebooks.api.begBalSave, phreebooks.api.importJournal]
confidence: high
source: src/controllers/phreebooks/api.php:212 (validateAccess('admin',4) at :215)
```

## phreebooks.api.begBalSave

```yaml
id: phreebooks.api.begBalSave
title: Save GL beginning balances (interactive)
route: phreebooks/api/begBalSave
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Beginning balances ▸ Save
auth: {sec_id: impexp, min_level: 3}
preconditions: [chart configured; fiscal period selected]
inputs:
  required:
    - {name: per-account opening amounts, format: currency, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_history (period balances), op: update}
  gl_journal: "Sets opening GL balances per account (GL effect on period balances)."
  inventory: none
  side_effects: []
returns: {success_signal: msgStack success, identifier: none}
errors: [permission denied (impexp level 3); unbalanced totals rejected]
idempotency: idempotent per account+period
related: [phreebooks.api.begBalImport]
confidence: high
source: src/controllers/phreebooks/api.php:185 (validateAccess('impexp',3) at :187)
```

## phreebooks.tools.jrnlData

```yaml
id: phreebooks.tools.jrnlData
title: Journal-data report extract
route: phreebooks/tools/jrnlData
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Journal data
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: period / filters, format: mixed, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [aggregates journal data (ungated, exposes GL data)]
returns: {success_signal: JSON/report data, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.tools.agingData, phreebooks.tools.salesByRep]
confidence: high
source: src/controllers/phreebooks/tools.php:42
```

## phreebooks.tools.agingData

```yaml
id: phreebooks.tools.agingData
title: AR/AP aging report extract
route: phreebooks/tools/agingData
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Aging
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: as-of date, format: date, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [computes aging buckets from open AR/AP (ungated, exposes balances)]
returns: {success_signal: aging JSON, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.tools.jrnlData]
confidence: high
source: src/controllers/phreebooks/tools.php:65
```

## phreebooks.tools.exportSales

```yaml
id: phreebooks.tools.exportSales
title: Export sales data
route: phreebooks/tools/exportSales
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Export sales
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: period, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [streams a sales CSV (ungated, exposes sales data)]
returns: {success_signal: file download, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.tools.salesByRep]
confidence: high
source: src/controllers/phreebooks/tools.php:77
```

## phreebooks.tools.fyCloseValidate

```yaml
id: phreebooks.tools.fyCloseValidate
title: Validate a fiscal-year close (pre-checks)
route: phreebooks/tools/fyCloseValidate
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Year-end close ▸ Validate
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs: {required: [], optional: [], fixed: []}
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [checks balances/integrity prior to year-end close (read-only validation)]
returns: {success_signal: validation report, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.tools.glRepostBulk]
confidence: medium
source: src/controllers/phreebooks/tools.php:173
```

## phreebooks.tools.glRepostBulk

```yaml
id: phreebooks.tools.glRepostBulk
title: Bulk re-post GL transactions (recompute postings)
route: phreebooks/tools/glRepostBulk
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Repost GL
auth: {sec_id: NONE (ungated), min_level: n/a}   # rewrites GL; see Open questions
preconditions: [backup recommended; periods open]
inputs:
  required: []
  optional:
    - {name: range / batch params, format: mixed, source: post}
  fixed: []
effects:
  db_writes:
    - {table: journal_item/journal_history, op: update, notes: re-derives GL distribution + period balances}
  gl_journal: "REWRITES GL postings in bulk (unPost/Post cycle per transaction). Mass GL effect."
  inventory: "MAY re-derive COGS/stock layers depending on transactions reposted."
  side_effects: [long-running; chained via glRepostBulkNext]
returns: {success_signal: batch progress; completion, identifier: none}
errors: [none enforced server-side (ungated) — VERY high caution, mass GL rewrite]
idempotency: idempotent in result (recomputes deterministically) but heavy
related: [phreebooks.tools.glRepostBulkNext, phreebooks.tools.glRepairNext]
confidence: medium
source: src/controllers/phreebooks/tools.php:757
```

## phreebooks.tools.glRepostBulkNext

```yaml
id: phreebooks.tools.glRepostBulkNext
title: Continue a bulk GL repost batch (next chunk)
route: phreebooks/tools/glRepostBulkNext
http_method: POST
ui_path: (AJAX continuation of glRepostBulk)
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [a glRepostBulk batch is in progress]
inputs:
  required:
    - {name: batch cursor, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_item/journal_history, op: update}
  gl_journal: "REWRITES GL postings for the next chunk (see glRepostBulk)."
  inventory: "MAY re-derive COGS/stock layers."
  side_effects: [continuation step]
returns: {success_signal: chunk progress, identifier: cursor for next call}
errors: [none enforced server-side (ungated)]
idempotency: idempotent in result, heavy
related: [phreebooks.tools.glRepostBulk]
confidence: medium
source: src/controllers/phreebooks/tools.php:777
```

## phreebooks.tools.glRepairNext

```yaml
id: phreebooks.tools.glRepairNext
title: Repair GL integrity (next chunk)
route: phreebooks/tools/glRepairNext
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Repair GL
auth: {sec_id: NONE (ungated), min_level: n/a}   # modifies GL; see Open questions
preconditions: [backup recommended]
inputs:
  required:
    - {name: batch cursor, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_item/journal_history, op: update, notes: fixes detected GL inconsistencies}
  gl_journal: "REPAIRS/REWRITES GL distribution rows."
  inventory: none
  side_effects: [continuation step]
returns: {success_signal: chunk progress, identifier: cursor}
errors: [none enforced server-side (ungated) — high caution]
idempotency: idempotent in result
related: [phreebooks.tools.glRepostBulk]
confidence: medium
source: src/controllers/phreebooks/tools.php:815
```

## phreebooks.tools.pruneCogs

```yaml
id: phreebooks.tools.pruneCogs
title: Prune/compact COGS cost layers
route: phreebooks/tools/pruneCogs
http_method: POST
ui_path: PhreeBooks ▸ Tools ▸ Prune COGS
auth: {sec_id: NONE (ungated), min_level: n/a}   # modifies COGS data; see Open questions
preconditions: [backup recommended]
inputs: {required: [], optional: [], fixed: []}
effects:
  db_writes:
    - {table: journal_cogs, op: update/delete, notes: removes fully-consumed cost layers}
  gl_journal: none   # compaction only; should not change net cost
  inventory: "Touches COGS layers (cost basis) — compaction, not stock-qty change."
  side_effects: [chained via pruneCogsNext]
returns: {success_signal: prune progress, identifier: none}
errors: [none enforced server-side (ungated) — high caution, touches cost data]
idempotency: idempotent in result
related: [phreebooks.tools.pruneCogsNext]
confidence: medium
source: src/controllers/phreebooks/tools.php:1018
```

## phreebooks.tools.pruneCogsNext

```yaml
id: phreebooks.tools.pruneCogsNext
title: Continue COGS prune (next chunk)
route: phreebooks/tools/pruneCogsNext
http_method: POST
ui_path: (AJAX continuation of pruneCogs)
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: [a pruneCogs batch is in progress]
inputs:
  required:
    - {name: batch cursor, format: mixed, source: post}
  optional: []
  fixed: []
effects:
  db_writes:
    - {table: journal_cogs, op: update/delete}
  gl_journal: none
  inventory: "touches COGS cost layers (compaction)"
  side_effects: [continuation step]
returns: {success_signal: chunk progress, identifier: cursor}
errors: [none enforced server-side (ungated)]
idempotency: idempotent in result
related: [phreebooks.tools.pruneCogs]
confidence: medium
source: src/controllers/phreebooks/tools.php:1032
```

## phreebooks.tools.salesByRep

```yaml
id: phreebooks.tools.salesByRep
title: Sales-by-rep report extract
route: phreebooks/tools/salesByRep
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Sales by rep
auth: {sec_id: NONE (ungated), min_level: n/a}   # see Open questions
preconditions: []
inputs:
  required: []
  optional:
    - {name: period, format: text, source: get}
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: [aggregates sales grouped by rep (ungated, exposes sales data)]
returns: {success_signal: JSON/report data, identifier: none}
errors: []
idempotency: safe (read-only)
related: [phreebooks.tools.exportSales]
confidence: high
source: src/controllers/phreebooks/tools.php:1059
```

---

## Common agent recipes

```yaml
recipe_post_sales_invoice:
  goal: Create + post a sales invoice (moves stock + posts AR/Sales/COGS)
  steps:
    - {action: phreebooks.journal.read, with: {jID: 12, rID: 0, cID: <customer id>}}
    - {action: phreebooks.journal.save, with: {jID: 12, contact_id_b: <cID>, item lines, totals}, capture: rID}
  note: jID 12 RELIEVES stock and posts COGS — a financial + inventory event, not neutral.

recipe_receive_purchase:
  goal: Receive goods against a purchase (adds stock, posts inventory asset/AP)
  steps:
    - {action: phreebooks.journal.save, with: {jID: 6, contact_id_b: <vendor id>, item lines}}
  note: jID 6 ADDS stock and posts GL. Non-posting PO (jID 4) does NOT.

recipe_quote_to_order_to_invoice:
  goal: Promote quote(9) -> order(10) -> invoice(12)
  steps:
    - {action: phreebooks.quote.to_order, with: {jID: 10, rID: <quote id>, action: ord}}
    - {action: phreebooks.journal.save,  with: {jID: 10, rID: 0}}          # order, non-posting
    - {action: phreebooks.quote.to_order, with: {jID: 12, rID: <order id>, action: inv}}
    - {action: phreebooks.journal.save,  with: {jID: 12}}                  # POSTS stock + GL
  note: only the final jID-12 save posts GL and moves stock.

recipe_build_assemblies:
  goal: Build finished goods to satisfy demand
  steps:
    - {action: phreebooks.autoAssy, with: {assembly sku, qty}}
  note: jID 14 consumes components and adds the assembly — verify on-hand and demand; NOT idempotent.

recipe_reverse_a_posting:
  goal: Back out a wrongly posted transaction
  steps:
    - {action: phreebooks.journal.delete, with: {jID: <jID>, rID: <id>}}
  note: delete REVERSES GL + restores/removes stock via unPost(). Not a no-op for posted jIDs.

recipe_bulk_import_journals:
  goal: Load many transactions from an external system (admin only)
  steps:
    - {action: phreebooks.api.importJournal, with: {journal import file}}
  note: posts every row's GL per jID. Ensure rows carry ids/invoice_num for dedupe; NOT idempotent on blind import.
```

## Open questions / verify-before-automating

```yaml
journal_save_is_the_safety_pivot:
  - phreebooks.journal.save / .delete effect is ENTIRELY jID-driven. Always resolve jID first; treat posting jIDs (6,7,12,13,14,15,17,18,19,20,21,22) as financial/inventory events and non-posting (3,4,9,10) as safe commitments.

level_5_above_documented_scale:
  - chart.php::mergeSave:288 uses validateAccess('admin',5) — LEVEL 5 is ABOVE the documented 1-4 scale (1=view,2=add,3=edit,4=delete). Reconcile the scale before any client-side enforcement.

ungated_public_routes:   # methods with NO validateAccess() — do NOT automate via these without confirming the API/session layer enforces access
  main.php:
    - notesSave:1082         # WRITE: journal notes
    - detailStatus:1154      # read: status panel
    - popupRecur:2067        # read: recurrence popup
    - buySellEdit:2111       # read: buy/sell editor
    - buySellSave:2145       # WRITE: buy/sell config
  register.php:
    - managerRows:54         # read: register rows (exposes GL)
  chart.php:
    - merge:269              # read: merge popup
    - export:422             # read: chart CSV export
  payroll.php:
    - importForm:34          # read: import form
    - importGo:43            # WRITE + POSTS GL: payroll import
  tax.php:
    - bulkChange:202         # WRITE: bulk tax reassignment
  currency.php:
    - add:62                 # WRITE
    - setExcRate:220         # WRITE
    - update:237             # WRITE
  fulfillment.php:
    - fulfillMain:47         # read: fulfillment workbench
  mainPOS.php:
    - bizWinPmt:178          # WRITE + POSTS GL + moves stock: POS payment (HIGH caution)
  api.php:
    - journalAPI:48          # WRITE + POSTS GL + moves stock per jID (relies on API session auth)
  ediAPI.php:
    - ediGet:106             # WRITE: stages docs
    - ediManual:153          # WRITE + MAY POST GL/stock
    - ediTransmit:189        # *** UNGATED OUTBOUND TRANSMIT — irreversible external effect ***
  tools.php:
    - jrnlData:42, agingData:65, exportSales:77, salesByRep:1059   # read: expose GL/sales data
    - fyCloseValidate:173    # read: pre-close validation
    - glRepostBulk:757, glRepostBulkNext:777   # WRITE: mass GL rewrite
    - glRepairNext:815       # WRITE: GL repair
    - pruneCogs:1018, pruneCogsNext:1032        # WRITE: COGS layer compaction
  restfulTax.php:
    - save:124               # WRITE: tax rate definition
    - calcTaxCollected:339   # read: tax totals

low_confidence_to_verify:
  - phreebooks.budget — guards not individually verified (confidence low)
  - phreebooks.ediAPI.manual — EDI segment -> jID mapping is partner/data dependent (confidence low)
  - phreebooks.returns / phreebooks.dropShip / phreebooks.drillDown — exact routes/columns inferred; confirm method names against the live files before automating writes
```
