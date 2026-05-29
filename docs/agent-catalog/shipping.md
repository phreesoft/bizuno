---
title: Shipping — Agent Action Catalog
module: shipping
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Shipping — Agent Action Catalog

Machine-readable actions for the `shipping` module — Bizuno's label-generation,
rate-quoting, carrier-integration, address-validation and inventory-receiving
surface. Read the [catalog schema and conventions](./README.md) first; this file
assumes the route, auth-level, and field conventions defined there.

Pages in this module: `manager` (the shipment/label journal grid + edit),
`ship` (the label generator and PDF/thermal label download), `rate` (rate
quotes — UI + API), `common` (shared carrier loader, package guesser, carrier
option AJAX), `address` (carrier address validation), `admin` (settings, hooks,
install/remove, package presets, postage funds, FedEx per-store creds),
`tools` (label-file cleanup), `track` (bulk tracking), `reconcile` (carrier
invoice reconciliation), `invReceiving` (inventory receiving — delegates to
phreebooks). There is also an `unshipped_orders` dashboard widget.

## How shipping differs from the contacts module

Shipping is **not** a normal CRUD journal. A "shipment record" is a
**meta blob** (`dbMetaSet`/`dbMetaGet` with prefix `shipment`) attached to a
PhreeBooks `journal_main` row (the sales order, journal_id 12) — or, when no
invoice can be matched, to a standalone `common` meta record. The blob holds
the carrier method, ship date, and a `packages.rows[]` list of
`{tracking_id, deliver_date, actual_date, cost, book, reconciled}`. Generating
a label appends to that blob and **clears the order's `waiting` flag** so it
drops off the unshipped queue.

Most actions here are bookkeeping-neutral. The two places money/stock actually
move are:

- **`shipping/invReceiving/receivingSave`** — this is a thin wrapper that
  instantiates `phreebooksMain` and calls its `save()` on the Purchase journal
  (jID 6). That delegated save **posts a GL journal and moves inventory**
  (goods received). Treat it as a phreebooks posting, not a shipping action.
- **`shipping/admin/fundsBuy`** — delegates to the carrier's `fundsBuy()`
  (e.g. Endicia) which **buys real postage with real money** from the carrier
  account. It is currently **ungated** (no `validateAccess`). See Open
  questions.

Label generation itself (`shipping/ship/labelGet`) spends money at the carrier
to mint a shipping label and contacts the carrier API, but creates **no Bizuno
GL posting** — the freight cost is recorded on the originating sales order, not
re-journaled here.

## Data model summary

```yaml
shipment_record:
  storage: meta blob via dbMetaSet/dbMetaGet      # NOT a dedicated table row
  meta_prefix: shipment                            # manager.php / ship.php
  attached_to:
    journal: journal_main row (sales order, journal_id=12) keyed by ref_id
    common:  standalone common_meta row when no invoice can be matched
  meta_id_field: the journal_meta/common_meta row id (used as rID/metaID in routes)
  blob_fields:
    ref_num:      auto-numbered shipment id from counter next_shipment_num
    invoice_num:  source order invoice_num (when journal-attached)
    store_id:     originating store/branch
    method_code:  "<carrier>:<service>"  e.g. fedex:GND  (VARCHAR(20) on journal_main.method_code)
    ship_date / deliver_date / actual_date
    total_cost / total_book / total_billed
    reconciled:   per-package boolean rolled up to yes/partial/blank
    packages:
      total: n
      rows:  [{tracking_id, deliver_date, actual_date, cost, book, reconciled}]
journal_main_fields_touched:
  waiting:     ENUM('0','1')  # '1' = unshipped/pending; cleared to '0' when a label/record is created
  freight:     DOUBLE         # order freight, seeded into shipment total_billed
  method_code: VARCHAR(20)    # carrier:service on the order itself
carriers:
  registry: getMetaMethod('carriers')              # enabled carriers + path + settings.services
  classes:  controllers/shipping/carriers/<carrier>/<carrier>.php   (FQCN \bizuno\<carrier>)
  optional_methods: rateQuote, labelGet, labelDelete, validateAddress,
                    trackBulk, reconcileInvoice, reconcileList, fundsBuy,
                    manager/managerForm, pkgPanel, labelKeys
inventory_ship_dims:
  table: inventory
  column: bizProShip (JSON)   # box_q/l/w/h/wt, plt_q/l/w/h/wt — feeds package guesser
sec_keys:
  shipping: module key for label/rate/ship/manager actions
  j12_mgr:  Sales (Order) journal manager key — shipping log + phreebooks grid hooks
  j6_mgr:   Purchase journal manager key — inventory receiving
  inv_mgr:  inventory manager key — per-SKU ship dims
  admin:    admin key — settings, tools, package presets, csv ship-dim import
```

> **Key safety facts for an acting agent:**
> 1. `shipping/invReceiving/receivingSave` posts a GL journal and moves
>    inventory via the delegated phreebooks save — it is **not**
>    bookkeeping-neutral.
> 2. `shipping/ship/labelGet` and `shipping/admin/fundsBuy` spend real money
>    at the carrier (mint a label / buy postage). `fundsBuy` is **ungated**.
> 3. Everything else (rate quotes, address validation, manager grids, settings,
>    tracking, reconcile toggles, label viewing/downloading) creates no GL or
>    inventory movement.

---

## shipping.manager

```yaml
id: shipping.manager
title: Open the shipment/label manager (grid + label generator)
route: shipping/manager/manager
http_method: GET
ui_path: Tools ▸ Shipping
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - shipping module enabled; at least one carrier configured
inputs:
  required: []
  optional:
    - name: period
      format: cmd
      source: post
      notes: date-range token (default 'l'); scopes which shipments list
    - name: store_id
      format: integer
      source: post
      notes: -1 = all stores
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders the manager accordion plus one tab per enabled carrier (each tab lazy-loads getCarrier)
    - builds the unshipped-order combogrid (backed by getUnshippedOrders)
returns:
  success_signal: layout with datagrid dgShipping + carrier tabs
  identifier: none
errors:
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.manager.rows, shipping.unshippedOrders, shipping.getCarrier, shipping.label.main]
confidence: high
source: src/controllers/shipping/manager.php:142 (manager)
```

## shipping.manager.rows

```yaml
id: shipping.manager.rows
title: Fetch shipment-record rows for the manager grid (data only)
route: shipping/manager/managerRows
http_method: GET
ui_path: (AJAX backing dgShipping)
auth:
  sec_id: shipping
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: matches invoice_num, ref_num, primary_name, ship_date, tracking
    - name: page
      format: integer
      source: get
    - name: rows
      format: integer
      source: get
    - name: sort
      format: db_field
      source: post
      notes: default ship_date
    - name: order
      format: db_field
      source: post
      notes: ASC|DESC, default DESC
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - joins journal_main to journal_meta (meta_key=shipment) and merges standalone common-meta shipments
returns:
  success_signal: JSON {total, rows}
  identifier: each row has id (= the shipment meta id) and _table (journal|common)
errors:
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.manager, shipping.manager.edit]
confidence: high
source: src/controllers/shipping/manager.php:205 (managerRows), :230 (getMgrRows)
```

## shipping.unshippedOrders

```yaml
id: shipping.unshippedOrders
title: List orders waiting to ship (for the label-generator picker)
route: shipping/manager/getUnshippedOrders
http_method: GET
ui_path: (AJAX backing the selInvoice combogrid on the manager)
auth:
  sec_id: NONE   # method has no validateAccess guard — see Open questions
  min_level: n/a
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: applied as invoice_num LIKE
  fixed:
    - name: filter
      value: "waiting='1' AND journal_id=12"
      notes: only unshipped sales orders
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON {total, rows} with id, invoice, bill_to, store_id, date, method
  identifier: each row id = journal_main.id of the unshipped order
errors: []
idempotency: safe (read-only)
related: [shipping.label.main]
confidence: high
source: src/controllers/shipping/manager.php:212 (getUnshippedOrders)
```

## shipping.getCarrier

```yaml
id: shipping.getCarrier
title: Render a carrier's settings/manager tab panel
route: shipping/manager/getCarrier
http_method: GET
ui_path: Tools ▸ Shipping ▸ <carrier> tab
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - carrier sID is an enabled carrier in the carriers registry
inputs:
  required:
    - name: sID
      format: cmd
      source: get
      notes: carrier code (e.g. fedex, usps, endicia, flat)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - autoloads \bizuno\<carrier> and calls its managerForm() or manager()
returns:
  success_signal: carrier panel layout
  identifier: none
errors:
  - permission denied if user lacks shipping level 2
idempotency: safe (read-only render)
related: [shipping.manager, shipping.admin.home]
confidence: high
source: src/controllers/shipping/manager.php:547 (getCarrier)
```

## shipping.manager.edit

```yaml
id: shipping.manager.edit
title: Open a single shipment record for editing (packages/tracking)
route: shipping/manager/edit
http_method: GET
ui_path: Tools ▸ Shipping ▸ row ▸ edit
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - rID is a shipment meta id; or a journal-attached order that was shipped with a non-label method
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: journal_meta/common_meta id of the shipment record (0 = new manual entry)
    - name: table
      format: db_field
      source: get
      notes: journal | common
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the shipment meta blob and builds the package edit grid
returns:
  success_signal: edit layout with dgPackage grid populated
  identifier: rID
errors:
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only render)
related: [shipping.manager.save, shipping.manager.rows]
confidence: high
source: src/controllers/shipping/manager.php:329 (edit)
```

## shipping.manager.save

```yaml
id: shipping.manager.save
title: Save a shipment record (manual / package + tracking edits)
route: shipping/manager/save
http_method: POST
ui_path: Tools ▸ Shipping ▸ row ▸ edit ▸ Save
auth:
  sec_id: shipping
  min_level: 2   # add (no _rID); 3 when _rID present (update)
preconditions:
  - if manually adding, invoice_num should match an existing journal_id=12 order to attach
inputs:
  required:
    - name: _table
      format: db_field
      source: post
      notes: journal | common
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing shipment meta id; presence switches save to update (level 3)
    - name: _refID
      format: integer
      source: post
      notes: journal_main id the shipment attaches to
    - name: invoice_num
      format: text
      source: post
      notes: if _refID empty, used to locate the order (invoice_num LIKE '<v>%' AND journal_id=12)
    - name: packages
      format: json
      source: post
      notes: serialized package grid {rows:[{tracking_id, deliver_date, actual_date, cost}]}
    - name: method_code
      format: cmd
      source: post
    - name: ship_date
      format: datetime
      source: post
    - name: deliver_date
      format: datetime
      source: post
    - name: total_cost
      format: float
      source: post
    - name: notes
      format: text
      source: post
  fixed: []
effects:
  db_writes:
    - table: journal_meta (or common_meta)
      op: insert/update
      notes: the shipment meta blob (prefix 'shipment')
    - table: journal_main
      op: update
      notes: sets waiting='0' on the attached order (id=_refID)
  gl_journal: none
  inventory: none
  side_effects:
    - clears the order's unshipped/waiting flag
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: shipment meta id
errors:
  - permission denied if user lacks shipping level 2/3
idempotency: idempotent on _rID (re-saving the same blob yields the same record)
related: [shipping.manager.edit, shipping.label.get, shipping.manager.delete]
confidence: high
source: src/controllers/shipping/manager.php:361 (save)
```

## shipping.manager.delete

```yaml
id: shipping.manager.delete
title: Delete a shipment record (and void/remove its carrier labels)
route: shipping/manager/delete
http_method: GET
ui_path: Tools ▸ Shipping ▸ row ▸ trash
auth:
  sec_id: shipping
  min_level: 4
preconditions:
  - shipment ship_date must be today or later (older labels are blocked from deletion)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: shipment meta id
    - name: table
      format: db_field
      source: get
      notes: journal | common
  optional: []
  fixed: []
effects:
  db_writes:
    - table: journal_meta (or common_meta)
      op: delete
    - table: journal_main
      op: update
      notes: if table=journal, sets waiting='1' back on the order (re-queues it)
  gl_journal: none
  inventory: none
  side_effects:
    - calls each carrier's labelDelete() to void the label at the carrier, then removes label files from data/shipping/labels/...
    - per-package carrier void hits the live carrier API
returns:
  success_signal: deleteMeta runs; grid reloads
  identifier: none
errors:
  - error_cannot_delete (caution) if ship_date is before today
  - "Label meta could not be found" if rID invalid
  - "error deleting the label from <carrier>" if the carrier void fails
  - permission denied if user lacks shipping level 4
idempotency: NOT idempotent — voids labels at the carrier; re-running after partial failure is unsafe
related: [shipping.manager.save, shipping.label.get]
confidence: high
source: src/controllers/shipping/manager.php:381 (delete), :397 (deleteLabels)
```

## shipping.manager.toggleReconcile

```yaml
id: shipping.manager.toggleReconcile
title: Toggle the reconciled flag on a shipment's packages
route: shipping/manager/toggleReconcile
http_method: GET
ui_path: Tools ▸ Shipping ▸ row ▸ reconcile action
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - rID is an existing shipment meta record
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: shipment meta id
    - name: table
      format: db_field
      source: get
      notes: journal | common
  optional: []
  fixed: []
effects:
  db_writes:
    - table: journal_meta (or common_meta)
      op: update
      notes: flips reconciled 0<->1 on every package row in the blob
  gl_journal: none
  inventory: none
  side_effects:
    - reloads the manager grid
returns:
  success_signal: eval action reloads dgShipping
  identifier: none
errors:
  - illegal_access if rID missing
  - permission denied if user lacks shipping level 2
idempotency: NOT idempotent — each call flips state (toggle); repeated calls oscillate
related: [shipping.manager.rows, shipping.reconcile.invoice]
confidence: high
source: src/controllers/shipping/manager.php:567 (toggleReconcile)
```

## shipping.manager.shippingLog

```yaml
id: shipping.manager.shippingLog
title: Popup of shipment log/tracking detail for a sales order
route: shipping/manager/shippingLog
http_method: GET
ui_path: PhreeBooks ▸ Sales manager ▸ row ▸ shipping-log action
auth:
  sec_id: j12_mgr
  min_level: 1
preconditions:
  - rID is a journal_main (sales order) id with shipment meta attached
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: journal_main id of the order
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - builds tracking links from each carrier's <CARRIER>_TRACKING_URL constant
returns:
  success_signal: popup HTML with method, dates, tracking numbers, cost
  identifier: none
errors:
  - "record not found if rID not passed"
  - no_results (caution) if no shipment meta
  - permission denied if user lacks j12_mgr level 1
idempotency: safe (read-only)
related: [shipping.track.bulk]
confidence: high
source: src/controllers/shipping/manager.php:439 (shippingLog)
```

## shipping.shpmtDetails.edit

```yaml
id: shipping.shpmtDetails.edit
title: Load per-SKU package/pallet ship dimensions tab
route: shipping/manager/shpmtDetailsEdit
http_method: GET
ui_path: Inventory ▸ item ▸ Shipping tab
auth:
  sec_id: inv_mgr
  min_level: 1
preconditions:
  - rID is an inventory item id
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: inventory item id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads inventory.bizProShip JSON (box_q/l/w/h/wt, plt_q/l/w/h/wt)
returns:
  success_signal: fields panel populated
  identifier: none
errors:
  - permission denied if user lacks inv_mgr level 1
idempotency: safe (read-only)
related: [shipping.shpmtDetails.save, shipping.admin.shipDims.import]
confidence: high
source: src/controllers/shipping/manager.php:489 (shpmtDetailsEdit)
```

## shipping.shpmtDetails.save

```yaml
id: shipping.shpmtDetails.save
title: Save per-SKU package/pallet ship dimensions
route: shipping/manager/shpmtDetailsSave
http_method: POST
ui_path: Inventory ▸ item ▸ Shipping tab ▸ Save (hook on inventory/main/save)
auth:
  sec_id: inv_mgr
  min_level: 3
preconditions:
  - id is an existing inventory item; shippingBoxQ supplied (else no-op)
inputs:
  required:
    - name: id
      format: integer
      source: post
      notes: inventory item id
    - name: shippingBoxQ
      format: integer
      source: post
      notes: box quantity; empty => method returns without writing
  optional:
    - name: shippingBoxL
      format: integer
      source: post
    - name: shippingBoxW
      format: integer
      source: post
    - name: shippingBoxH
      format: integer
      source: post
    - name: shippingBoxWt
      format: integer
      source: post
    - name: shippingPltQ
      format: integer
      source: post
    - name: shippingPltL
      format: integer
      source: post
    - name: shippingPltW
      format: integer
      source: post
    - name: shippingPltH
      format: integer
      source: post
    - name: shippingPltWt
      format: integer
      source: post
  fixed: []
effects:
  db_writes:
    - table: inventory
      op: update
      notes: writes bizProShip JSON for id
  gl_journal: none
  inventory: none   # updates ship-dimension metadata only, not stock levels
  side_effects: []
returns:
  success_signal: row updated (no explicit message)
  identifier: none
errors:
  - permission denied if user lacks inv_mgr level 3
  - silent no-op if id or shippingBoxQ missing
idempotency: idempotent — overwrites the bizProShip blob
related: [shipping.shpmtDetails.edit, shipping.admin.shipDims.import]
confidence: high
source: src/controllers/shipping/manager.php:520 (shpmtDetailsSave)
```

## shipping.label.main

```yaml
id: shipping.label.main
title: Open the label generator for an order (build the label form)
route: shipping/ship/labelMain
http_method: GET
ui_path: Tools ▸ Shipping ▸ pick unshipped order ▸ Label Generator
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - rID is the journal_main id of the order to ship (0 = blank/manual)
  - at least one enabled carrier
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: journal_main order id; 0 builds an empty label form
  fixed: []
effects:
  db_writes:
    - table: journal_meta
      op: insert
      notes: ONLY when the selected carrier has no labelGet() — a log-only shipment record is created (addNewRecord) and the editor opens
  gl_journal: none
  inventory: none
  side_effects:
    - guesses package count/weight/dims from the order's line items (inventory + bizProShip)
    - prefills ship-to address, method, package grid
returns:
  success_signal: label-generator layout (frmLabel) or, for non-label carriers, redirect to the manager edit
  identifier: for non-label carriers, the new shipment meta id
errors:
  - permission denied if user lacks shipping level 2
idempotency: >
  read-only for label-capable carriers. For non-label carriers it inserts a
  log record on each call (NOT idempotent) — re-opening creates duplicate logs.
related: [shipping.label.get, shipping.rate.main, shipping.getCarrierOpts, shipping.getPanelPkg]
confidence: high
source: src/controllers/shipping/ship.php:49 (labelMain), :652 (addNewRecord)
```

## shipping.label.get

```yaml
id: shipping.label.get
title: Buy/generate a shipping label from the carrier and record it
route: shipping/ship/labelGet
http_method: POST
ui_path: Tools ▸ Shipping ▸ Label Generator ▸ Print
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - carrier supplied and resolves to a class with labelGet()
  - carrier account configured; valid ship-to address; package rows present
inputs:
  required:
    - name: carrier
      format: cmd/text
      source: post
      notes: carrier code; aborts if empty
    - name: ship_method
      format: cmd
      source: post
      notes: service code (combined into method_code = carrier:service)
    - name: pkg_array
      format: json
      source: post
      notes: package grid rows (qty/weight/dims/value)
  optional:
    - name: store_id_p
      format: text/integer
      source: post
      notes: pickup/origin store
    - name: store_id_b
      format: integer
      source: post
      notes: billing store
    - name: ship_bill_to
      format: cmd
      source: post
      notes: SENDER | THIRD_PARTY | RECIPIENT | COLLECT
    - name: ship_bill_act
      format: cmd
      source: post
    - name: ship_date
      format: date
      source: post
    - name: insurance / ins_amount
      format: integer/currency
      source: post
    - name: ship_cod / ship_cod_val / ship_cod_type / ship_cod_cur
      format: integer/cmd
      source: post
    - name: ship_confirm / confirm_type
      format: integer/cmd
      source: post
    - name: ship_saturday / ship_return / ship_handling
      format: integer/float
      source: post
    - name: residential
      format: integer
      source: post
    - name: ltl_class / ltl_desc
      format: cmd/text
      source: post
    - name: extra1
      format: array
      source: post
      notes: carrier extras (LIFTGATE_DELIVERY, COD, etc.)
    - name: "address fields (_s shipper, _o origin, _p payor, destination)"
      format: per-field
      source: post
  fixed:
    - name: method_code
      value: "<carrier>:<service>"
    - name: ref_num
      value: getNextReference(next_shipment_num)
effects:
  db_writes:
    - table: journal_meta (or common_meta)
      op: insert
      notes: shipment blob with packages.rows[] (tracking, cost, book, delivery_date)
    - table: journal_main
      op: update
      notes: sets waiting='0' on the matched order (invoice = value['ref_id'])
  gl_journal: none   # no Bizuno GL posting; freight is recorded on the source order
  inventory: none
  side_effects:
    - LIVE carrier API call mints a label and (for postage carriers) SPENDS MONEY
    - downloads/writes label files (gif/pdf/zpl) under data/shipping/labels/<carrier>/Y/M/D
    - wraps the meta write in a DB transaction; writes msgLog audit line
    - if no matching invoice found, attaches as a 'common' record and warns
returns:
  success_signal: eval action that opens labelView for the new metaID; grids reload
  identifier: shipment meta id (metaID) + carrier tracking numbers
errors:
  - "carrier not passed" if carrier empty
  - empty/early return if carrier labelGet returns no array
  - "could not find an invoice to tie this label to" (warning, still saved as common)
  - permission denied if user lacks shipping level 2
idempotency: >
  NOT idempotent — each call buys a new label and inserts a new shipment record.
  A retry after a timeout risks a duplicate paid label. Verify tracking before retry.
related: [shipping.label.main, shipping.label.view, shipping.manager.delete, shipping.rate.list]
confidence: high
source: src/controllers/shipping/ship.php:375 (labelGet), :423 (prepLabel)
```

## shipping.label.view

```yaml
id: shipping.label.view
title: View/print a generated label (image, PDF button, thermal)
route: shipping/ship/labelView
http_method: GET
ui_path: (popup opened after labelGet)
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - metaID is an existing shipment record with package tracking ids and label files on disk
inputs:
  required:
    - name: metaID
      format: integer
      source: get
      notes: shipment meta id
    - name: table
      format: db_field
      source: get
      notes: journal | common
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads label files from data/shipping/labels/<carrier>/Y/M/D/<tracking>*; renders gif inline, PDF download button, Zebra thermal print button
returns:
  success_signal: label page rendered
  identifier: none
errors:
  - "Failed to pull the record" if meta missing
  - "Label file ... cannot be found" per missing file (caution)
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.label.get, shipping.label.download]
confidence: high
source: src/controllers/shipping/ship.php:476 (labelView)
```

## shipping.label.download

```yaml
id: shipping.label.download
title: Download a label PDF file
route: shipping/ship/labelDownload
http_method: POST
ui_path: (PDF Download button on the label view)
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - data path points to an existing label file under BIZUNO_DATA
inputs:
  required:
    - name: data
      format: filename
      source: get
      notes: relative file path of the label to download
  optional:
    - name: rID
      format: integer
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - streams the file (CSRF token spliced into URL for the iframe download path)
returns:
  success_signal: file download (no layout return)
  identifier: none
errors:
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.label.view]
confidence: high
source: src/controllers/shipping/ship.php:640 (labelDownload)
```

## shipping.getCarrierOpts

```yaml
id: shipping.getCarrierOpts
title: Get a carrier's selectable options (methods, packaging, COD, signature)
route: shipping/ship/getCarrierOpts
http_method: GET
ui_path: (AJAX when carrier dropdown changes on the label form)
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - carrier resolves to an enabled carrier class
inputs:
  required:
    - name: carrier
      format: cmd
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON of method/package/pickup/COD/signature/LTL option lists + defaults
  identifier: none
errors:
  - permission denied if user lacks shipping level 2
idempotency: safe (read-only)
related: [shipping.label.main, shipping.getPanelPkg]
confidence: high
source: src/controllers/shipping/common.php:192 (getCarrierOpts)
```

## shipping.getPanelPkg

```yaml
id: shipping.getPanelPkg
title: Render the package-dimensions grid panel for a carrier
route: shipping/ship/getPanelPkg
http_method: GET
ui_path: (AJAX refreshing the package panel on the label form)
auth:
  sec_id: NONE   # method has no validateAccess guard — see Open questions
  min_level: n/a
preconditions: []
inputs:
  required:
    - name: carrier
      format: cmd
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - calls the carrier's pkgPanel() if present
returns:
  success_signal: package grid layout
  identifier: none
errors: []
idempotency: safe (read-only)
related: [shipping.getCarrierOpts, shipping.label.main]
confidence: medium   # ungated; render-only but reachable without auth
source: src/controllers/shipping/common.php:265 (getPanelPkg)
```

## shipping.rate.main

```yaml
id: shipping.rate.main
title: Open the rate-quote form (estimate shipping cost)
route: shipping/rate/rateMain
http_method: GET
ui_path: Order entry ▸ Rate Quote (shipping estimator popup)
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - at least one enabled carrier with rateQuote()
inputs:
  required: []
  optional:
    - name: data
      format: json
      source: get
      notes: order context (ship address, items, totals) used to pre-guess package/weight
    - name: resi
      format: integer
      source: get
      notes: pre-check residential
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - guesses shipment dims/weight from passed items
returns:
  success_signal: rate-quote form layout (frmEstimate)
  identifier: none
errors:
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.rate.list]
confidence: high
source: src/controllers/shipping/rate.php:47 (rateMain)
```

## shipping.rate.list

```yaml
id: shipping.rate.list
title: Get rate quotes from the selected carriers
route: shipping/rate/rateList
http_method: POST
ui_path: Rate Quote form ▸ Rate
auth:
  sec_id: NONE   # rateList itself has no validateAccess guard (reached via the gated rateMain form) — see Open questions
  min_level: n/a
preconditions:
  - method[] lists carriers to quote; each must implement rateQuote()
inputs:
  required:
    - name: method
      format: array
      source: post
      notes: carrier codes to quote
    - name: weight
      format: float
      source: post
    - name: num_boxes
      format: integer
      source: post
  optional:
    - name: ship_date
      format: date
      source: post
    - name: length / width / height
      format: float
      source: post
    - name: insurance / ins_amount
      format: integer/currency
      source: post
    - name: ltl_class
      format: text
      source: post
    - name: residential
      format: boolean
      source: post
    - name: extra1
      format: array
      source: post
    - name: store_id_b / store_id_p
      format: integer
      source: post
      notes: billing / origin stores
    - name: "destination address fields"
      format: per-field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - LIVE carrier API calls to fetch rates (read-only at the carrier; no purchase)
returns:
  success_signal: HTML rate table; clicking a row posts method/service/cost/GL back to the order
  identifier: per-row carrier:service + quoted cost + gl_acct
errors:
  - no_results if no carriers return rates
idempotency: safe (read-only quote; no purchase, no record written)
related: [shipping.rate.main, shipping.rateAPI, shipping.label.get]
confidence: high
source: src/controllers/shipping/rate.php:149 (rateList)
```

## shipping.rateAPI

```yaml
id: shipping.rateAPI
title: Programmatic rate quote from default carriers (internal API)
route: shipping/rate/rateAPI
http_method: POST
ui_path: (not a UI button — called by the Bizuno API / WooCommerce bridge)
auth:
  sec_id: NONE   # public method, no validateAccess guard — see Open questions
  min_level: n/a
preconditions:
  - $pkg passed in BIZUNO API FORMAT
  - one or more carriers flagged settings.default with rateQuote()
inputs:
  required:
    - name: pkg
      format: array (method arg)
      source: caller
      notes: pre-formatted package/address payload (NOT read via clean(); passed by the calling API method)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - LIVE carrier API calls for rates (read-only at carrier)
returns:
  success_signal: array of {id: carrier_idx, title, cost, quote}
  identifier: composite carrier_<idx> ids
errors: []
idempotency: safe (read-only quote)
related: [shipping.rate.list]
confidence: medium   # invoked internally; verify the caller and pkg shape before automating
source: src/controllers/shipping/rate.php:201 (rateAPI)
```

## shipping.address.validate

```yaml
id: shipping.address.validate
title: Validate / standardize a ship-to address via a carrier
route: shipping/address/validateAddress
http_method: GET
ui_path: Label/Rate form ▸ Validate Address
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - at least one enabled carrier with validateAddress() (FedEx, UPS, USPS, Endicia)
inputs:
  required:
    - name: data
      format: json
      source: get
      notes: address blob (must include address1); state/country sent separately and merged in
  optional:
    - name: suffix
      format: cmd
      source: get
      notes: target field suffix (_s default) for the update-into-form JS
    - name: methodCode
      format: cmd
      source: get
      notes: carrier:service; the carrier prefix is tried first
    - name: country
      format: cmd
      source: get
    - name: state
      format: cmd
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - LIVE carrier API call; falls back through enabled carriers in sort order if the first fails
    - returns recommended address + residential flag (read-only; nothing persisted)
returns:
  success_signal: popup with score, recommended address, and an Update button
  identifier: none
errors:
  - "not enough address information sent" if address1 missing
  - "no enabled carriers with address validation" (info) if none succeed
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only)
related: [shipping.rate.list, shipping.label.get]
confidence: high
source: src/controllers/shipping/address.php:45 (validateAddress)
```

## shipping.track.bulk

```yaml
id: shipping.track.bulk
title: Bulk-track shipments for a carrier and download a report
route: shipping/track/trackBulk
http_method: GET
ui_path: Tools ▸ Shipping ▸ <carrier> ▸ Track
auth:
  sec_id: shipping
  min_level: 1
preconditions:
  - carrier resolves to a class implementing trackBulk()
inputs:
  required:
    - name: carrier
      format: cmd
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to the carrier's trackBulk(); typically queries the carrier API and downloads a file
returns:
  success_signal: file download (usually does not return a layout)
  identifier: none
errors:
  - "carrier not passed" if carrier empty
  - permission denied if user lacks shipping level 1
idempotency: safe (read-only tracking query)
related: [shipping.manager.shippingLog]
confidence: medium   # behavior depends on the carrier's trackBulk implementation
source: src/controllers/shipping/track.php:44 (trackBulk)
```

## shipping.reconcile.invoice

```yaml
id: shipping.reconcile.invoice
title: Start carrier-invoice reconciliation for a carrier
route: shipping/reconcile/reconcileInvoice
http_method: GET
ui_path: Tools ▸ Shipping ▸ <carrier> ▸ Reconcile
auth:
  sec_id: shipping
  min_level: 3
preconditions:
  - carrier resolves to a class implementing reconcileInvoice()
inputs:
  required:
    - name: carrier
      format: text
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none   # the shipping reconcile dispatcher itself posts nothing; verify the carrier method
  inventory: none
  side_effects:
    - delegates to the carrier's reconcileInvoice()
returns:
  success_signal: carrier reconcile UI/result
  identifier: none
errors:
  - "carrier not passed"
  - "carrier does not have a reconciliation method" (caution)
  - permission denied if user lacks shipping level 3
idempotency: depends on the carrier implementation — verify before automating
related: [shipping.reconcile.list, shipping.manager.toggleReconcile]
confidence: medium   # actual effects live in each carrier class
source: src/controllers/shipping/reconcile.php:44 (reconcileInvoice)
```

## shipping.reconcile.list

```yaml
id: shipping.reconcile.list
title: List carrier-invoice reconciliation rows
route: shipping/reconcile/reconcileList
http_method: GET
ui_path: (AJAX backing the reconcile grid)
auth:
  sec_id: shipping
  min_level: 3
preconditions:
  - carrier resolves to a class implementing reconcileList()
inputs:
  required:
    - name: carrier
      format: text
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - delegates to the carrier's reconcileList() (reads files in data/shipping/<carrier>)
returns:
  success_signal: reconcile datagrid/JSON
  identifier: none
errors:
  - "carrier not passed"
  - "carrier does not have a reconciliation method" (caution)
  - permission denied if user lacks shipping level 3
idempotency: safe (read-only)
related: [shipping.reconcile.invoice]
confidence: medium
source: src/controllers/shipping/reconcile.php:64 (reconcileList)
```

## shipping.invReceiving.main

```yaml
id: shipping.invReceiving.main
title: Open the inventory-receiving screen
route: shipping/invReceiving/receivingMain
http_method: GET
ui_path: Inventory ▸ Receiving
auth:
  sec_id: j6_mgr
  min_level: 2
preconditions:
  - Purchase journal (jID 6) accessible
inputs:
  required: []
  optional: []
  fixed:
    - name: jID
      value: 6
      notes: forces the Purchase journal
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - composes phreebooks/main/manager and reshapes it into the receiving UI
returns:
  success_signal: receiving layout
  identifier: none
errors:
  - permission denied if user lacks j6_mgr level 2
idempotency: safe (read-only render)
related: [shipping.invReceiving.list, shipping.invReceiving.edit, shipping.invReceiving.save]
confidence: high
source: src/controllers/shipping/invReceiving.php:40 (receivingMain)
```

## shipping.invReceiving.list

```yaml
id: shipping.invReceiving.list
title: List open purchase orders to receive against
route: shipping/invReceiving/receivingList
http_method: GET
ui_path: (AJAX backing the receiving contact picker)
auth:
  sec_id: j6_mgr
  min_level: 2
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
  fixed:
    - name: filter
      value: "journal_id=4 AND closed='0'"
      notes: open purchase orders
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reuses phreebooks dgPhreeBooks grid with receiving filters
returns:
  success_signal: datagrid of open POs
  identifier: each row journal_main.id
errors:
  - permission denied if user lacks j6_mgr level 2
idempotency: safe (read-only)
related: [shipping.invReceiving.main, shipping.invReceiving.edit]
confidence: high
source: src/controllers/shipping/invReceiving.php:61 (receivingList)
```

## shipping.invReceiving.edit

```yaml
id: shipping.invReceiving.edit
title: Open a purchase order for receiving (item/qty entry)
route: shipping/invReceiving/receivingEdit
http_method: GET
ui_path: Inventory ▸ Receiving ▸ pick PO
auth:
  sec_id: j6_mgr
  min_level: 2
preconditions:
  - iID/jID context for the PO being received
inputs:
  required: []
  optional:
    - name: iID
      format: integer
      source: get
      notes: source order id passed through to phreebooks edit
  fixed:
    - name: jID
      value: 6
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - composes phreebooks/main/edit; hides totals/pricing, shows qty-in-stock; sets waiting checked; points the form at receivingSave
returns:
  success_signal: receiving edit form
  identifier: none
errors:
  - permission denied if user lacks j6_mgr level 2
idempotency: safe (read-only render)
related: [shipping.invReceiving.save]
confidence: high
source: src/controllers/shipping/invReceiving.php:75 (receivingEdit)
```

## shipping.invReceiving.save

```yaml
id: shipping.invReceiving.save
title: Save a receiving (posts the Purchase journal — GL + inventory)
route: shipping/invReceiving/receivingSave
http_method: POST
ui_path: Inventory ▸ Receiving ▸ Save
auth:
  sec_id: j6_mgr
  min_level: 2
preconditions:
  - valid Purchase journal (jID 6) entry with items and quantities
inputs:
  required:
    - name: "(full phreebooks journal entry payload)"
      format: per-field
      source: post
      notes: this method instantiates phreebooksMain and calls save(); all fields are the phreebooks Purchase-journal fields
  optional: []
  fixed:
    - name: jID
      value: 6
effects:
  db_writes:
    - table: journal_main / journal_item
      op: insert/update
      notes: via the delegated phreebooks save
  gl_journal: posts the Purchase journal (jID 6) — see Journal ID taxonomy
  inventory: MOVES STOCK — goods received increment on-hand quantities
  side_effects:
    - redirects back to receivingMain on success (msgErrors()===0)
returns:
  success_signal: eval redirect to receivingMain
  identifier: the phreebooks journal id (from the delegated save)
errors:
  - permission denied if user lacks j6_mgr level 2
  - any phreebooks validation error aborts the post
idempotency: >
  NOT idempotent — each call posts a journal entry and moves inventory.
  This is the ONE shipping route that has accounting consequences; treat it as
  a phreebooks posting, not a shipping action.
related: [shipping.invReceiving.edit]
confidence: high
source: src/controllers/shipping/invReceiving.php:102 (receivingSave)
```

## shipping.admin.home

```yaml
id: shipping.admin.home
title: Open shipping module settings + package presets
route: shipping/admin/adminHome
http_method: GET
ui_path: Settings ▸ Shipping
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders general settings (GL accounts gl_shipping_c/_v, UOMs, requireds, block_trash, skip_guess) + custom package grid + tools link
returns:
  success_signal: admin settings layout
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only render)
related: [shipping.admin.save, shipping.admin.savePkg]
confidence: high
source: src/controllers/shipping/admin.php:288 (adminHome)
```

## shipping.admin.save

```yaml
id: shipping.admin.save
title: Save shipping module settings
route: shipping/admin/adminSave
http_method: POST
ui_path: Settings ▸ Shipping ▸ Save
auth:
  sec_id: admin
  min_level: 3
preconditions: []
inputs:
  required: []
  optional:
    - name: "general_* setting fields"
      format: per-field
      source: post
      notes: bill_hq, block_trash, skip_guess, gl_shipping_c, gl_shipping_v, weight_uom, dim_uom, max_pkg_weight, pallet_weight, ltl_class, resi_checked, *_req flags
  fixed: []
effects:
  db_writes:
    - table: (module settings cache / config)
      op: update
      notes: via readModuleSettings
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: settings persisted
  identifier: none
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent — overwrites settings
related: [shipping.admin.home]
confidence: high
source: src/controllers/shipping/admin.php:315 (adminSave)
```

## shipping.admin.savePkg

```yaml
id: shipping.admin.savePkg
title: Save custom package-size presets
route: shipping/admin/adminSavePkg
http_method: GET
ui_path: Settings ▸ Shipping ▸ Packages ▸ Save
auth:
  sec_id: admin
  min_level: 1   # note: a write gated only at level 1
preconditions: []
inputs:
  required:
    - name: myPkgs
      format: json
      source: get
      notes: rows of {length, width, height}; rows missing any dim are dropped
  optional: []
  fixed: []
effects:
  db_writes:
    - table: (module myPackages cache)
      op: update
      notes: setModuleCache shipping/myPackages
  gl_journal: none
  inventory: none
  side_effects:
    - sorts presets by length
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: idempotent — replaces the preset list
related: [shipping.admin.home]
confidence: high
source: src/controllers/shipping/admin.php:375 (adminSavePkg)
```

## shipping.admin.shipDims.import

```yaml
id: shipping.admin.shipDims.import
title: Import per-SKU ship dimensions from CSV (inventory hook)
route: inventory/api/apiImport  (shipping hook, order 50)
http_method: POST
ui_path: Inventory ▸ Tools/API ▸ Import (ship-dim columns)
auth:
  sec_id: admin
  min_level: 2
preconditions:
  - CSV uploaded as fileInventory with sku + invAttrH00..H09 columns
inputs:
  required:
    - name: fileInventory
      format: file (csv/txt)
      source: post
      notes: header row with sku and invAttrH00..H09 (box/pallet q/l/w/h/wt)
  optional: []
  fixed: []
effects:
  db_writes:
    - table: inventory
      op: update
      notes: writes bizProShip JSON keyed by sku
  gl_journal: none
  inventory: none   # ship-dimension metadata only, not stock
  side_effects:
    - runs as a hook appended to the inventory CSV import
returns:
  success_signal: rows updated (per row)
  identifier: none
errors:
  - permission denied if user lacks admin level 2
  - upload rejected if not csv/txt
  - row skipped if sku blank
idempotency: idempotent on sku — re-importing overwrites bizProShip
related: [shipping.shpmtDetails.save, shipping.shpmtDetails.edit]
confidence: high
source: src/controllers/shipping/admin.php:177 (apiImport)
```

## shipping.admin.fundsBuy

```yaml
id: shipping.admin.fundsBuy
title: Buy postage funds from a carrier account
route: shipping/admin/fundsBuy
http_method: GET
ui_path: Tools ▸ Shipping ▸ Endicia (or other postage carrier) ▸ Buy Funds
auth:
  sec_id: NONE   # NO validateAccess guard — see Open questions (spends real money)
  min_level: n/a
preconditions:
  - carrier (default endicia) has a fundsBuy() implementation and configured account
inputs:
  required: []
  optional:
    - name: carrier
      format: cmd
      source: get
      notes: defaults to 'endicia' if empty
    - name: "(carrier-specific amount/auth fields)"
      format: per-carrier
      source: get/post
  fixed: []
effects:
  db_writes:
    - table: (carrier-specific funds/log)
      op: insert/update
      notes: depends on the carrier
  gl_journal: none
  inventory: none
  side_effects:
    - LIVE carrier API call that PURCHASES POSTAGE (spends real money on the carrier account)
returns:
  success_signal: carrier confirmation
  identifier: carrier transaction reference
errors:
  - carrier-specific failures
idempotency: NOT idempotent — each call buys funds; retries duplicate the purchase
related: []
confidence: medium   # ungated and money-moving — see Open questions before automating
source: src/controllers/shipping/admin.php:109 (fundsBuy)
```

## shipping.admin.signup

```yaml
id: shipping.admin.signup
title: Show a carrier's setup/signup instructions
route: shipping/admin/signup
http_method: GET
ui_path: Settings ▸ Shipping ▸ <carrier> ▸ Signup/Instructions
auth:
  sec_id: NONE   # no validateAccess guard (info-only) — see Open questions
  min_level: n/a
preconditions:
  - carrier class file exists
inputs:
  required:
    - name: carrier
      format: alpha_num
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - emits the carrier's lang['instructions'] as an info message
returns:
  success_signal: info message with instructions
  identifier: none
errors:
  - "Carrier <x> not found" if class missing
  - "No special instructions found" if none defined
idempotency: safe (read-only)
related: [shipping.getCarrier]
confidence: high
source: src/controllers/shipping/admin.php:271 (signup)
```

## shipping.admin.extraAction

```yaml
id: shipping.admin.extraAction
title: Dispatch a carrier-specific extra action
route: shipping/admin/extraAction
http_method: GET
ui_path: (carrier-specific buttons; see Endicia)
auth:
  sec_id: shipping
  min_level: 2
preconditions:
  - data = "<carrier>:<action>" and the carrier implements <action>()
inputs:
  required:
    - name: data
      format: text
      source: get
      notes: "carrier:action" e.g. endicia:someAction
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none   # depends entirely on the dispatched carrier action
  inventory: none
  side_effects:
    - autoloads the carrier and invokes the named method (effects are carrier/action-specific)
returns:
  success_signal: layout returned by the carrier action
  identifier: none
errors:
  - "does not have enough information" if carrier/action missing
  - BUG: the carrier-found guard is inverted (errors when the carrier IS found) — see Open questions
  - permission denied if user lacks shipping level 2
idempotency: depends on the dispatched action — verify before automating
related: [shipping.admin.fundsBuy]
confidence: low   # inverted guard at admin.php:211 likely makes this path non-functional as written
source: src/controllers/shipping/admin.php:211 (extraAction)
```

## shipping.tools.manager

```yaml
id: shipping.tools.manager
title: Open the shipping tools page (label-file cleanup)
route: shipping/tools/manager
http_method: GET
ui_path: Settings ▸ Shipping ▸ Tools
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders the cleanup form + backups grid
returns:
  success_signal: tools layout
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [shipping.tools.cleanLog]
confidence: high
source: src/controllers/shipping/tools.php:43 (manager)
```

## shipping.tools.cleanLog

```yaml
id: shipping.tools.cleanLog
title: Delete old carrier label files before a cutoff date
route: shipping/tools/cleanLog
http_method: POST
ui_path: Settings ▸ Shipping ▸ Tools ▸ Clean (Go)
auth:
  sec_id: admin
  min_level: 4   # destructive: deletes files on disk
preconditions: []
inputs:
  required: []
  optional:
    - name: dateClean
      format: date
      source: post
      notes: cutoff; folders dated before this are removed (default -3 months)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - deletes label folders under data/shipping/labels/<carrier>/Y/M/D older than the cutoff (folderDelete)
returns:
  success_signal: log_clean_success message
  identifier: none
errors:
  - permission denied if user lacks admin level 4
idempotency: idempotent in effect (deleting already-gone folders is a no-op), but irreversible
related: [shipping.tools.manager]
confidence: high
source: src/controllers/shipping/tools.php:106 (cleanLog)
```

## shipping.tools.mgrRows

```yaml
id: shipping.tools.mgrRows
title: List stored backup/label archive files
route: shipping/tools/mgrRows
http_method: GET
ui_path: (AJAX backing the tools file grid)
auth:
  sec_id: NONE   # no validateAccess guard — see Open questions
  min_level: n/a
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - globs backups/ for txt/zip/gz files
returns:
  success_signal: JSON {total, rows}
  identifier: file names
errors: []
idempotency: safe (read-only)
related: [shipping.tools.manager]
confidence: medium   # ungated read of the backups directory listing
source: src/controllers/shipping/tools.php:95 (mgrRows)
```

## shipping.tools.syncShipments

```yaml
id: shipping.tools.syncShipments
title: (Deprecated) sync shipping logs with journals — no-op
route: shipping/tools/syncShipments
http_method: GET
ui_path: (no longer wired into the UI)
auth:
  sec_id: NONE   # no validateAccess guard; body is a no-op stub
  min_level: n/a
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: message "This tool is no longer needed."
  identifier: none
errors: []
idempotency: safe (no-op)
related: []
confidence: high
source: src/controllers/shipping/tools.php:147 (syncShipments)
```

## shipping.contacts.fedExEdit

```yaml
id: shipping.contacts.fedExEdit
title: Render FedEx per-store credentials tab on a branch contact
route: contacts/main/edit  (shipping hook, order 60)
http_method: GET
ui_path: Contacts ▸ Branch (type b) ▸ FedEx tab
auth:
  sec_id: (inherits the contacts/main/edit gate; this hook adds no own guard)
  min_level: 1
preconditions:
  - contact type is 'b' (branch); otherwise the hook returns immediately
inputs:
  required:
    - name: rID
      format: integer
      source: get
    - name: type
      format: char
      source: get
      notes: must be 'b'
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - injects a FedEx tab (acct_number, ltl_acct_num, gnd_econ_hub) into the contact editor
returns:
  success_signal: contact layout with FedEx tab
  identifier: none
errors: []
idempotency: safe (read-only render)
related: [shipping.contacts.fedExSave, shipping.contacts.fedExDelete]
confidence: high
source: src/controllers/shipping/admin.php:325 (fedExEdit)
```

## shipping.contacts.fedExSave

```yaml
id: shipping.contacts.fedExSave
title: Store FedEx per-store credentials on a branch contact
route: contacts/main/save  (shipping hook, order 60)
http_method: POST
ui_path: Contacts ▸ Branch ▸ Save (with FedEx tab filled)
auth:
  sec_id: (inherits the contacts/main/save gate)
  min_level: 2
preconditions:
  - contact type is 'b'; acct_number supplied (else no write)
inputs:
  required:
    - name: type
      format: char
      source: get
      notes: must be 'b'
    - name: id
      format: integer
      source: post
      notes: contact rID
    - name: acct_number
      format: integer
      source: post
  optional:
    - name: ltl_acct_num
      format: integer
      source: post
    - name: gnd_econ_hub
      format: integer
      source: post
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: insert/update
      notes: meta key 'fedex' for the contact
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: meta saved
  identifier: none
errors:
  - silent no-op if type != b or acct_number empty
idempotency: idempotent — overwrites the fedex meta
related: [shipping.contacts.fedExEdit, shipping.contacts.fedExDelete]
confidence: high
source: src/controllers/shipping/admin.php:345 (fedExSave)
```

## shipping.contacts.fedExDelete

```yaml
id: shipping.contacts.fedExDelete
title: Remove FedEx credentials when a contact is deleted
route: contacts/main/delete  (shipping hook, order 60)
http_method: GET
ui_path: (runs as part of contact deletion)
auth:
  sec_id: (inherits the contacts/main/delete gate)
  min_level: 4
preconditions:
  - rID supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: delete
      notes: removes the 'fedex' meta for the contact
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: meta removed
  identifier: none
errors: []
idempotency: idempotent (deleting already-gone meta is a no-op)
related: [shipping.contacts.fedExSave]
confidence: high
source: src/controllers/shipping/admin.php:363 (fedExDelete)
```

## shipping.phreebooks.manager (hook)

```yaml
id: shipping.phreebooks.manager
title: Add shipping-log + method columns/actions to the PhreeBooks manager
route: phreebooks/main/manager  (shipping hook → shippingAdmin::manager)
http_method: GET
ui_path: PhreeBooks ▸ journal manager (jID 9/10/12/13/15)
auth:
  sec_id: j12_mgr
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: jID
      format: integer
      source: get
      notes: journal id; jID 12 adds the shipping-log action and block_trash gating
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - adds method_code column and (jID 12) a shipping-log action; applies block_trash delete gating
returns:
  success_signal: augmented manager layout
  identifier: none
errors:
  - permission denied if user lacks j12_mgr level 1
idempotency: safe (read-only render)
related: [shipping.manager.shippingLog]
confidence: high
source: src/controllers/shipping/admin.php:123 (manager)
```

## shipping.dashboard.unshippedOrders

```yaml
id: shipping.dashboard.unshippedOrders
title: Render the "Unshipped Orders" dashboard widget
route: (dashboard render — shipping/unshipped_orders)
http_method: GET
ui_path: Home dashboard ▸ Unshipped Orders
auth:
  sec_id: shipping
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: dashboard opts
      notes: -1 = all stores
    - name: num_rows
      format: integer
      source: dashboard opts
    - name: order
      format: db_field
      source: dashboard opts
  fixed:
    - name: filter
      value: "journal_id=12 AND waiting='1' AND CHAR_LENGTH(method_code) > 2"
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - lists pending-ship sales orders with a print-form link; auto-refresh every 5 min
returns:
  success_signal: dashboard list
  identifier: each row links to a journal_main order
errors: []
idempotency: safe (read-only)
related: [shipping.unshippedOrders, shipping.label.main]
confidence: high
source: src/controllers/shipping/dashboards/unshipped_orders/unshipped_orders.php:62 (render)
```

---

## Common agent recipes

```yaml
recipe_quote_then_label:
  goal: Quote rates for an order, then buy the chosen label
  steps:
    - action: shipping.rate.list
      with: {method: [fedex, usps], weight, num_boxes, destination_address}
      capture: chosen carrier:service + cost   # read-only, no purchase
    - action: shipping.label.main
      with: {rID: <journal_main order id>}      # build the label form / pre-guess packages
    - action: shipping.label.get
      with: {carrier, ship_method, pkg_array, ship-to address}
      capture: metaID + tracking
      note: SPENDS MONEY at the carrier and is NOT idempotent — never blind-retry on timeout; confirm tracking first
    - action: shipping.label.view
      with: {metaID, table: journal}            # render/print the label

recipe_validate_before_shipping:
  goal: Standardize the ship-to address before generating a label
  steps:
    - action: shipping.address.validate
      with: {data: <address json>, methodCode: "fedex:GND", state, country}
    - apply the recommended address to the order, then proceed to shipping.label.get

recipe_set_sku_ship_dims_bulk:
  goal: Populate per-SKU package/pallet dimensions so the guesser is accurate
  steps:
    - action: shipping.admin.shipDims.import   # CSV keyed by sku, idempotent
    - note: feeds inventory.bizProShip used by the package guesser in rate/label flows

recipe_void_a_label:
  goal: Remove a mistaken shipment and void its carrier label
  steps:
    - action: shipping.manager.delete
      with: {rID: <shipment meta id>, table: journal}
      note: blocked if ship_date is before today; voids the label at the carrier (NOT idempotent)
```

## Open questions / verify-before-automating

- **`shipping/admin/fundsBuy` (admin.php:109) is ungated** — it calls
  `validateAccess` nowhere yet delegates to the carrier `fundsBuy()`, which
  **buys real postage with real money**. Do not expose or automate this route
  without first adding an auth check; treat as high-risk.
- **`shipping/rate/rateAPI` (rate.php:201) is ungated and money-relevant
  context** — it is a public method intended for the internal API/WooCommerce
  bridge. It only fetches rates (read-only at the carrier), but it bypasses
  `validateAccess`; confirm the calling API surface enforces auth before
  relying on it.
- **`shipping/manager/getUnshippedOrders` (manager.php:212),
  `shipping/ship/getPanelPkg` (common.php:265), `shipping/rate/rateList`
  (rate.php:149), `shipping/tools/mgrRows` (tools.php:95),
  `shipping/tools/syncShipments` (tools.php:147), and
  `shipping/admin/signup` (admin.php:271) have no `validateAccess` guard.**
  Most are render/list/no-op, but they are reachable as routes without a
  permission check — review before treating any as trusted.
- **Inverted carrier guard in `shipping/admin/extraAction` (admin.php:211):**
  `if (!empty($meta)) { return msgAdd("Could not find carrier class…"); }` —
  the condition is backwards (it errors when the carrier registry entry IS
  found, and proceeds with an empty `$meta` when it is not). As written this
  dispatcher likely never reaches a found carrier; `confidence: low`. Verify
  before wiring any carrier extra-action automation.
- **`shipping/invReceiving/receivingSave` (invReceiving.php:102) posts a GL
  journal and moves inventory** through the delegated `phreebooksMain::save()`
  on the Purchase journal (jID 6). It is the only shipping route with
  accounting consequences — plan it as a phreebooks posting and confirm the
  full Purchase-journal payload before automating.
- **`shipping/ship/labelGet` (ship.php:375) spends money and is not
  idempotent.** It mints a (paid) carrier label and inserts a shipment record
  per call. A retry after a network timeout can produce a duplicate paid
  label — always reconcile carrier tracking before retrying.
- **Reconcile and track routes delegate to per-carrier classes**
  (`reconcile.php`, `track.php`) — the dispatchers post nothing, but the
  carrier `reconcileInvoice()/trackBulk()` implementations may. Verify the
  specific carrier method's effects (`confidence: medium`).
- **`shipping/admin/adminSavePkg` (admin.php:375) is a write gated only at
  admin level 1** (view), not 3 — note the mismatch if your agent enforces
  level-by-operation conventions.
- **`shipping/manager/shpmtDetailsSave` (manager.php:520) `inventory: none`**
  despite writing the `inventory` table — it updates the `bizProShip`
  ship-dimension metadata column only and does **not** move stock.