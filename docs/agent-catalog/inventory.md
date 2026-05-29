---
title: "Inventory — Agent Action Catalog"
module: inventory
category: "Agent Catalog"
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Inventory — Agent Action Catalog

A machine-readable catalog of every action a software agent can perform in the
Bizuno **inventory** module, grounded in the actual controller code under
`src/controllers/inventory/`. The audience is an automating AI agent (and the
humans wiring one up). For the catalog contract, schema, and auth-level table,
see [`README.md`](./README.md).

Every authenticated action is a route of the form `inventory/<page>/<method>`,
dispatched by `compose()` and invoked by POSTing/GETing to the portal AJAX
endpoint with a `bizRt` parameter. All input passes through
`clean($name, $format, $method)`; the catalog lists the exact field, format, and
source (`get`/`post`) per action. Access is gated by
`validateAccess($secID, $level)` — level `1` view, `2` add, `3` edit, `4` delete
(the inventory module also uses `5` for the destructive merge tool). A third
argument of `false` makes the check *soft* (it sets a flag but does not return
early).

> **Safety facts an acting agent must respect:**
> - The **only** action in this module that posts to the general ledger and
>   moves stock is `inventory.build.complete_step` (`build.php::saveStep`), which
>   on a completed assembly step creates an **Assembly journal (jID 14)** via
>   `new journal(0, 14)` → `Post()`. Everything else in inventory is bookkeeping-
>   and stock-neutral *except* the allocation adjustments described below.
> - `inventory.build.save` and `inventory.build.complete_step` adjust
>   `inventory.qty_alloc` on the BOM component SKUs (via `allocateAdj`) for
>   COGS-tracked component types. This changes *allocation*, not on-hand stock or
>   GL.
> - `inventory.item.rename` cascades the SKU string across `inventory`,
>   `inventory_history`, `journal_cogs_owed`, and `journal_item` — it rewrites
>   historical transaction rows. Treat as high-impact.
> - `inventory.item.delete` is blocked when the SKU is a component in any BOM or
>   when it is a COGS type with existing `journal_item` history.
> - Several `tools.php` / `attributes.php` / `images.php` methods perform writes
>   with **no access check** — see *Open questions* before automating against them.

## Data model summary

```yaml
tables:
  inventory:
    key_natural: sku            # human SKU string, the upsert/merge key
    key_surrogate: id           # auto-increment, referenced as rID/iID elsewhere
    notable_columns:
      inventory_type: si|sr|ma|sa|ns|lb|sv|sf|ci|ai|ds|ia|mi|ms
      qty_stock:    on-hand quantity
      qty_alloc:    quantity allocated to open orders / assemblies
      qty_on_order: quantity on open purchase orders
      item_cost:    current unit cost
      gl_inv/gl_sales/gl_cogs: default GL accounts for the SKU
      invAccessory/invOptions/invVendors/invImages: json blobs (extensions)
  inventory_meta:
    keyed_by: ref_id (-> inventory.id) + mID/type
    holds: bill_of_materials (BOM), production_job, production_task,
           price_c (customer price sheets), price_v (vendor cost sheets)
  journal_main / journal_item:
    jID_14: Assembly post (gl_type asy/asi) — the build/un-build entry
    jID_32: Work Order header/lines (woProd, build.php) — NOT a GL post itself
  inventory_history: per-movement cost/qty audit trail (read-only here)
inventory_types_that_hit_cogs_and_stock: [si, sr, ma, sa]   # INVENTORY_COGS_TYPES
security_keys:
  inv_mgr:      inventory manager (main, prices aging/details, history, tools merge/forecast)
  woProd:       work-order production (build.php), journalID 32
  woDesign:     work-order designs (design.php)
  woTasks:      work-order tasks (tasks.php)
  invBulkEdit:  bulk field editor (bulkEdit.php)
  prices_{c|v}: price/cost sheets (prices.php), type from clean('type','char')
  admin:        cross-cutting import/export/repair
```

---

## inventory.item.list

```yaml
id:            inventory.item.list
title:         List inventory items (manager grid shell)
route:         inventory/main/manager
http_method:   GET
ui_path:       Inventory ▸ Manage Inventory
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: clr, format: integer, source: get, schema_field: null, notes: "clear stored grid filters when 1" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders EasyUI datagrid bound to managerRows
returns:
  success_signal: HTML layout for the manager grid
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.list_rows, inventory.item.edit]
confidence:    high
source:        src/controllers/inventory/main.php:104
```

## inventory.item.list_rows

```yaml
id:            inventory.item.list_rows
title:         Fetch inventory rows (grid data feed)
route:         inventory/main/managerRows
http_method:   GET
ui_path:       Inventory ▸ Manage Inventory (grid AJAX)
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: search, format: text, source: get, schema_field: null, notes: "free-text filter over sku/description" }
    - { name: clr, format: integer, source: get, schema_field: null, notes: "reset filters" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: builds SQL via gridBase; honors stored user filters
returns:
  success_signal: JSON {total, rows[]} of inventory records
  identifier:     row.id per item
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.list]
confidence:    high
source:        src/controllers/inventory/main.php:134
```

## inventory.item.bom_list

```yaml
id:            inventory.item.bom_list
title:         List bill-of-materials components for an assembly
route:         inventory/main/managerBOMList
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Assembly tab
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: ["item is an assembly type (ma/sa)"]
inputs:
  required:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "assembly item id" }
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: reads bill_of_materials from inventory_meta
returns:
  success_signal: JSON rows of component sku/qty
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.edit, inventory.build.complete_step]
confidence:    high
source:        src/controllers/inventory/main.php:195
```

## inventory.item.edit

```yaml
id:            inventory.item.edit
title:         Render the inventory item editor
route:         inventory/main/edit
http_method:   GET
ui_path:       Inventory ▸ Manage Inventory ▸ (open/new item)
auth: { sec_id: inv_mgr, min_level: 1 }   # NOTE: see errors/idempotency
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "0/absent => new item form" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: loads tabs (vendors, images, accessory, options) via compose
returns:
  success_signal: HTML editor layout; $security flag toggles edit/delete icons
  identifier:     null
errors:        ["level check is computed but NOT enforced as early-return; method renders regardless"]
idempotency:   safe; read-only
related:       [inventory.item.save, inventory.item.properties]
confidence:    high
source:        src/controllers/inventory/main.php:236
```

## inventory.item.properties

```yaml
id:            inventory.item.properties
title:         Item properties panel (delegates to editor)
route:         inventory/main/properties
http_method:   GET
ui_path:       Inventory ▸ item editor (properties section)
auth: { sec_id: inv_mgr, min_level: 1 (inherited from edit) }
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: composes inventory/main/edit; has no own guard
returns:
  success_signal: HTML properties layout
  identifier:     null
errors:        ["no own validateAccess call — relies entirely on edit()"]
idempotency:   safe; read-only
related:       [inventory.item.edit]
confidence:    medium
source:        src/controllers/inventory/main.php:455
```

## inventory.item.details_type

```yaml
id:            inventory.item.details_type
title:         Switch editor field set for an inventory type
route:         inventory/main/detailsType
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Type selector
auth: { sec_id: inv_mgr, min_level: 2 }
preconditions: []
inputs:
  required:
    - { name: type, format: char, source: get, schema_field: inventory.inventory_type, notes: "si|sr|ma|sa|ns|lb|sv|sf|ci|ai|ds|ms" }
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: returns the field structure appropriate to the chosen type
returns:
  success_signal: HTML/JSON field layout fragment
  identifier:     null
errors:        ["permission denied (level < 2)"]
idempotency:   safe; read-only
related:       [inventory.item.edit, inventory.item.save]
confidence:    high
source:        src/controllers/inventory/main.php:431
```

## inventory.item.save

```yaml
id:            inventory.item.save
title:         Create or update an inventory item
route:         inventory/main/save
http_method:   POST
ui_path:       Inventory ▸ item editor ▸ Save
auth: { sec_id: inv_mgr, min_level: "2 if new (no id), 3 if updating (id present)" }
preconditions: ["for assembly types a BOM may be supplied", "sku must be unique on create"]
inputs:
  required:
    - { name: sku, format: text, source: post, schema_field: inventory.sku, notes: "natural key; required identifier" }
    - { name: inventory_type, format: char, source: post, schema_field: inventory.inventory_type, notes: "drives COGS/stock behavior" }
  optional:
    - { name: id, format: integer, source: post, schema_field: inventory.id, notes: "present => update path (level 3)" }
    - { name: description_short, format: text, source: post, schema_field: inventory.description_short, notes: "" }
    - { name: gl_inv, format: text, source: post, schema_field: inventory.gl_inv, notes: "default inventory GL acct" }
    - { name: gl_sales, format: text, source: post, schema_field: inventory.gl_sales, notes: "default sales GL acct" }
    - { name: gl_cogs, format: text, source: post, schema_field: inventory.gl_cogs, notes: "default COGS GL acct" }
    - { name: item_cost, format: currency, source: post, schema_field: inventory.item_cost, notes: "" }
  fixed: []
effects:
  db_writes:   "inventory insert (new) or update (existing); related meta via saveProStuff"
  gl_journal:  none
  inventory:   "writes item master only — does NOT move qty_stock or post GL"
  side_effects: "saveProStuff persists BOM/extension blobs; cache reload"
returns:
  success_signal: JSON success with saved record id
  identifier:     inventory.id
errors:        ["permission denied", "duplicate sku on create", "validation failure"]
idempotency:   "update by id is idempotent; create is upsert-able by unique sku"
related:       [inventory.item.save_pro, inventory.item.edit, inventory.item.copy]
confidence:    high
source:        src/controllers/inventory/main.php:478
```

## inventory.item.save_pro

```yaml
id:            inventory.item.save_pro
title:         Persist Pro/extension sub-data for an item (BOM, vendors, etc.)
route:         inventory/main/saveProStuff
http_method:   POST
ui_path:       (internal, called from save)
auth: { sec_id: inv_mgr, min_level: 2 }
preconditions: ["item id exists or is being created in the same save"]
inputs:
  required:
    - { name: id, format: integer, source: post, schema_field: inventory.id, notes: "target item" }
  optional:
    - { name: invAccessory, format: text, source: post, schema_field: inventory.invAccessory, notes: "json accessory id list" }
    - { name: invVendors, format: text, source: post, schema_field: inventory.invVendors, notes: "json vendor cost list" }
    - { name: invImages, format: text, source: post, schema_field: inventory.invImages, notes: "json image path list" }
    - { name: bom, format: text, source: post, schema_field: inventory_meta.bill_of_materials, notes: "assembly components" }
  fixed: []
effects:
  db_writes:   "inventory (extension json columns), inventory_meta (BOM)"
  gl_journal:  none
  inventory:   none
  side_effects: "may be called as part of item.save"
returns:
  success_signal: persisted sub-records
  identifier:     inventory.id
errors:        ["permission denied (level < 2)"]
idempotency:   "idempotent per item id"
related:       [inventory.item.save]
confidence:    medium
source:        src/controllers/inventory/main.php:508
```

## inventory.item.copy

```yaml
id:            inventory.item.copy
title:         Duplicate an inventory item under a new SKU
route:         inventory/main/copy
http_method:   POST
ui_path:       Inventory ▸ item editor ▸ Copy
auth: { sec_id: inv_mgr, min_level: 2 }
preconditions: ["source item id exists", "new sku is unique"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory.id, notes: "source item" }
    - { name: sku, format: text, source: post, schema_field: inventory.sku, notes: "new SKU (must not collide)" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory insert (new row copied from source)"
  gl_journal:  none
  inventory:   "creates a new item master; no stock/qty carried"
  side_effects: copies BOM/meta where applicable
returns:
  success_signal: JSON success with new item id
  identifier:     new inventory.id
errors:        ["permission denied", "duplicate sku", "source not found"]
idempotency:   "NOT idempotent — repeat creates duplicate; guard on target sku"
related:       [inventory.item.save]
confidence:    high
source:        src/controllers/inventory/main.php:668
```

## inventory.item.rename

```yaml
id:            inventory.item.rename
title:         Rename a SKU and cascade across history/journals
route:         inventory/main/rename
http_method:   POST
ui_path:       Inventory ▸ item editor ▸ Rename SKU
auth: { sec_id: inv_mgr, min_level: 4 }
preconditions: ["source sku exists", "target sku not already in use"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory.id, notes: "item to rename" }
    - { name: sku, format: text, source: post, schema_field: inventory.sku, notes: "new SKU string" }
  optional: []
  fixed: []
effects:
  db_writes:   "UPDATE sku on inventory, inventory_history, journal_cogs_owed, journal_item"
  gl_journal:  "none (rewrites the sku string on existing journal_item rows; no new posting)"
  inventory:   "no qty movement; rewrites historical references"
  side_effects: "HIGH IMPACT — mutates posted transaction rows in place"
returns:
  success_signal: JSON success
  identifier:     inventory.id
errors:        ["permission denied (level < 4)", "target sku collision"]
idempotency:   "running twice with same target is a no-op after first; cascade is the natural key"
related:       [inventory.item.save, inventory.item.merge]
confidence:    high
source:        src/controllers/inventory/main.php:630
```

## inventory.item.delete

```yaml
id:            inventory.item.delete
title:         Delete an inventory item
route:         inventory/main/delete
http_method:   POST
ui_path:       Inventory ▸ Manage Inventory ▸ Delete
auth: { sec_id: inv_mgr, min_level: 4 }
preconditions:
  - "SKU is not a component in any assembly BOM (else err_inv_delete_assy)"
  - "SKU is not a COGS type with existing journal_item history (else err_inv_delete_gl_entry)"
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory.id, notes: "item to delete" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory delete (and associated meta)"
  gl_journal:  none
  inventory:   "removes the item master if guards pass"
  side_effects: "blocked by referential guards above"
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 4)", "err_inv_delete_assy", "err_inv_delete_gl_entry"]
idempotency:   "safe; deleting an absent item is a no-op"
related:       [inventory.item.rename, inventory.item.merge]
confidence:    high
source:        src/controllers/inventory/main.php:722
```

## inventory.item.usage

```yaml
id:            inventory.item.usage
title:         Show where a SKU is used (assemblies referencing it)
route:         inventory/main/usage
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Usage
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id" }
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: scans BOMs for the SKU
returns:
  success_signal: HTML/JSON of parent assemblies
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.bom_list]
confidence:    high
source:        src/controllers/inventory/main.php:1039
```

## inventory.build.list

```yaml
id:            inventory.build.list
title:         List work orders (production manager grid)
route:         inventory/build/manager
http_method:   GET
ui_path:       Inventory ▸ Assemblies / Work Orders
auth: { sec_id: woProd, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed:
    - { name: jID, value: 32, notes: "work-order journal" }
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders grid bound to managerRows
returns:
  success_signal: HTML grid layout
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.build.list_rows, inventory.build.add]
confidence:    high
source:        src/controllers/inventory/build.php:109
```

## inventory.build.list_rows

```yaml
id:            inventory.build.list_rows
title:         Work-order grid data feed
route:         inventory/build/managerRows
http_method:   GET
ui_path:       Inventory ▸ Assemblies (grid AJAX)
auth: { sec_id: woProd, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: search, format: text, source: get, schema_field: null, notes: "filter" }
  fixed:
    - { name: jID, value: 32, notes: "work-order journal" }
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: builds SQL via gridBase
returns:
  success_signal: JSON {total, rows[]} of work orders
  identifier:     journal_main.id
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.build.list]
confidence:    high
source:        src/controllers/inventory/build.php:135
```

## inventory.build.add

```yaml
id:            inventory.build.add
title:         New work-order editor
route:         inventory/build/add
http_method:   GET
ui_path:       Inventory ▸ Assemblies ▸ New
auth: { sec_id: woProd, min_level: 2 }
preconditions: ["target assembly SKU (ma/sa) exists with a BOM"]
inputs:
  required: []
  optional: []
  fixed:
    - { name: jID, value: 32, notes: "work-order journal" }
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders blank work-order form
returns:
  success_signal: HTML editor
  identifier:     null
errors:        ["permission denied (level < 2)"]
idempotency:   safe; read-only
related:       [inventory.build.save, inventory.build.edit]
confidence:    high
source:        src/controllers/inventory/build.php:157
```

## inventory.build.edit

```yaml
id:            inventory.build.edit
title:         Open an existing work order
route:         inventory/build/edit
http_method:   GET
ui_path:       Inventory ▸ Assemblies ▸ (open)
auth: { sec_id: woProd, min_level: 2 }
preconditions: ["work order id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: get, schema_field: journal_main.id, notes: "work-order id" }
  optional: []
  fixed:
    - { name: jID, value: 32, notes: "" }
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: loads production steps from meta
returns:
  success_signal: HTML editor with steps
  identifier:     journal_main.id
errors:        ["permission denied (level < 2)"]
idempotency:   safe; read-only
related:       [inventory.build.save, inventory.build.complete_step]
confidence:    high
source:        src/controllers/inventory/build.php:165
```

## inventory.build.details

```yaml
id:            inventory.build.details
title:         Read-only work-order details
route:         inventory/build/details
http_method:   GET
ui_path:       Inventory ▸ Assemblies ▸ details
auth: { sec_id: woProd, min_level: 1 }
preconditions: ["work order id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: get, schema_field: journal_main.id, notes: "work-order id" }
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: HTML/JSON details
  identifier:     journal_main.id
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.build.edit]
confidence:    high
source:        src/controllers/inventory/build.php:238
```

## inventory.build.save

```yaml
id:            inventory.build.save
title:         Create/update a work order (header, quantities, steps)
route:         inventory/build/save
http_method:   POST
ui_path:       Inventory ▸ Assemblies ▸ Save
auth: { sec_id: woProd, min_level: "2 if new, 3 if existing" }
preconditions: ["assembly SKU with a BOM"]
inputs:
  required:
    - { name: sku, format: text, source: post, schema_field: journal_item.sku, notes: "assembly being built" }
    - { name: qty, format: float, source: post, schema_field: journal_item.qty, notes: "quantity to build" }
  optional:
    - { name: id, format: integer, source: post, schema_field: journal_main.id, notes: "present => update (level 3)" }
  fixed:
    - { name: jID, value: 32, notes: "work-order journal" }
effects:
  db_writes:   "journal_main / journal_item (jID 32); production_steps meta"
  gl_journal:  "none (jID 32 work order is not itself a GL post)"
  inventory:   "ADJUSTS inventory.qty_alloc on COGS-type BOM components when build qty changes (allocateAdj). Does NOT move qty_stock or post GL."
  side_effects: "allocation reservation against component stock"
returns:
  success_signal: JSON success with work-order id
  identifier:     journal_main.id
errors:        ["permission denied", "missing/invalid sku or qty"]
idempotency:   "update by id idempotent; saving a new WO repeatedly creates duplicates"
related:       [inventory.build.complete_step, inventory.build.delete]
confidence:    high
source:        src/controllers/inventory/build.php:327
```

## inventory.build.complete_step

```yaml
id:            inventory.build.complete_step
title:         Save/complete a production step (THE assembly GL post)
route:         inventory/build/saveStep
http_method:   POST
ui_path:       Inventory ▸ Assemblies ▸ (mark step complete)
auth: { sec_id: woProd, min_level: "2 if new, 3 if existing" }
preconditions:
  - "work order exists with a BOM"
  - "erp_entry enabled for the GL post to fire"
  - "sufficient component stock for the assembly"
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: journal_main.id, notes: "work-order id" }
    - { name: step, format: integer, source: post, schema_field: null, notes: "production step index" }
  optional:
    - { name: complete, format: integer, source: post, schema_field: null, notes: "1 marks the step complete; final step triggers assemble()" }
  fixed:
    - { name: jID (work order), value: 32, notes: "the WO journal being updated" }
    - { name: jID (GL post), value: 14, notes: "Assembly journal created by assemble()" }
effects:
  db_writes:   "production_steps meta; on completion journal_main/journal_item for the Assembly post"
  gl_journal:  "ASSEMBLY (jID 14) — on a completed step with erp_entry, assemble() does `new journal(0,14)` then Post(); gl_type asy/asi. THIS MOVES MONEY."
  inventory:   "MOVES STOCK — consumes BOM component qty_stock, produces the assembly's qty_stock; on the final step removes the allocation reservation via allocateAdj."
  side_effects: "the single ledger+stock mutation point of the inventory module"
returns:
  success_signal: JSON success; journal posted
  identifier:     journal_main.id (jID 14 entry)
errors:        ["permission denied", "insufficient component stock", "post failure"]
idempotency:   "NOT idempotent — re-completing a step can double-post. Verify step state before retry after a timeout."
related:       [inventory.build.save, inventory.build.delete]
confidence:    high
source:        src/controllers/inventory/build.php:346 (assemble:430, journal(0,14):434, Post:454)
```

## inventory.build.delete

```yaml
id:            inventory.build.delete
title:         Delete a work order (un-assemble / reverse the build)
route:         inventory/build/delete
http_method:   POST
ui_path:       Inventory ▸ Assemblies ▸ Delete
auth: { sec_id: woProd, min_level: 4 }
preconditions: ["work order id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: journal_main.id, notes: "work-order id" }
  optional: []
  fixed:
    - { name: jID, value: 32, notes: "work order" }
effects:
  db_writes:   "deletes the jID 32 work order; deletes the associated jID 14 Assembly journal via phreebooksMain::delete"
  gl_journal:  "REVERSES the Assembly post (jID 14) if one exists for the WO"
  inventory:   "un-assembles: reverses stock movement and removes allocation"
  side_effects: "compound GL+stock reversal"
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 4)"]
idempotency:   "safe once deleted; do not retry blindly if the GL reversal may have already posted"
related:       [inventory.build.complete_step]
confidence:    high
source:        src/controllers/inventory/build.php:461
```

## inventory.prices.list

```yaml
id:            inventory.prices.list
title:         List price/cost sheets
route:         inventory/prices/manager
http_method:   GET
ui_path:       Inventory ▸ Prices / Costs
auth: { sec_id: "prices_{type}", min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: type, format: char, source: get, schema_field: null, notes: "c=customer price, v=vendor cost; default c" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders grid bound to managerRows
returns:
  success_signal: HTML grid layout
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.prices.list_rows, inventory.prices.edit]
confidence:    high
source:        src/controllers/inventory/prices.php:131
```

## inventory.prices.list_rows

```yaml
id:            inventory.prices.list_rows
title:         Price-sheet grid data feed
route:         inventory/prices/managerRows
http_method:   GET
ui_path:       Inventory ▸ Prices (grid AJAX)
auth: { sec_id: "prices_{type}", min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: type, format: char, source: get, schema_field: null, notes: "c|v" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: reads price_c/price_v from inventory_meta
returns:
  success_signal: JSON {total, rows[]}
  identifier:     ref_id
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.prices.list]
confidence:    high
source:        src/controllers/inventory/prices.php:143
```

## inventory.prices.edit

```yaml
id:            inventory.prices.edit
title:         Edit a price/cost sheet
route:         inventory/prices/edit
http_method:   GET
ui_path:       Inventory ▸ Prices ▸ (open/new)
auth: { sec_id: "prices_{type}", min_level: 2 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory_meta.id, notes: "sheet id; absent => new" }
    - { name: type, format: char, source: get, schema_field: null, notes: "c|v" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders price-sheet editor
returns:
  success_signal: HTML editor
  identifier:     null
errors:        ["permission denied (level < 2)"]
idempotency:   safe; read-only
related:       [inventory.prices.save]
confidence:    high
source:        src/controllers/inventory/prices.php:221
```

## inventory.prices.copy

```yaml
id:            inventory.prices.copy
title:         Duplicate a price/cost sheet
route:         inventory/prices/copy
http_method:   POST
ui_path:       Inventory ▸ Prices ▸ Copy
auth: { sec_id: "prices_{type}", min_level: 2 }
preconditions: ["source sheet exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory_meta.id, notes: "source sheet" }
  optional:
    - { name: type, format: char, source: post, schema_field: null, notes: "c|v" }
  fixed: []
effects:
  db_writes:   "inventory_meta insert (new price sheet)"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success with new sheet id
  identifier:     inventory_meta.id
errors:        ["permission denied (level < 2)", "source not found"]
idempotency:   "NOT idempotent — creates a duplicate each call"
related:       [inventory.prices.save]
confidence:    high
source:        src/controllers/inventory/prices.php:279
```

## inventory.prices.save

```yaml
id:            inventory.prices.save
title:         Create/update a price or cost sheet
route:         inventory/prices/save
http_method:   POST
ui_path:       Inventory ▸ Prices ▸ Save
auth: { sec_id: "prices_{type}", min_level: "2 if new, 3 if existing" }
preconditions: []
inputs:
  required:
    - { name: type, format: char, source: post, schema_field: null, notes: "c=customer price / v=vendor cost" }
  optional:
    - { name: id, format: integer, source: post, schema_field: inventory_meta.id, notes: "present => update (level 3)" }
  fixed: []
effects:
  db_writes:   "inventory_meta (price_c or price_v blob)"
  gl_journal:  none
  inventory:   "none — price/cost sheets do not move stock or post GL"
  side_effects: "cache reload of pricing"
returns:
  success_signal: JSON success with sheet id
  identifier:     inventory_meta.id
errors:        ["permission denied", "validation failure"]
idempotency:   "update by id idempotent"
related:       [inventory.prices.edit, inventory.prices.delete]
confidence:    high
source:        src/controllers/inventory/prices.php:300
```

## inventory.prices.delete

```yaml
id:            inventory.prices.delete
title:         Delete a price/cost sheet
route:         inventory/prices/delete
http_method:   POST
ui_path:       Inventory ▸ Prices ▸ Delete
auth: { sec_id: "prices_{type}", min_level: 4 }
preconditions: ["sheet id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory_meta.id, notes: "sheet to delete" }
  optional:
    - { name: type, format: char, source: post, schema_field: null, notes: "c|v" }
  fixed: []
effects:
  db_writes:   "inventory_meta delete"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 4)"]
idempotency:   "safe; deleting an absent sheet is a no-op"
related:       [inventory.prices.save]
confidence:    high
source:        src/controllers/inventory/prices.php:323
```

## inventory.prices.quote

```yaml
id:            inventory.prices.quote
title:         Compute the effective price/cost for an item+contact
route:         inventory/prices/quote
http_method:   GET
ui_path:       (called by order entry / quoting)
auth: { sec_id: "max(prices_{type} 1, j6_mgr/j12_mgr 1)", min_level: 1 }
preconditions: ["item id and contact id supplied"]
inputs:
  required:
    - { name: iID, format: integer, source: get, schema_field: inventory.id, notes: "item" }
    - { name: cID, format: integer, source: get, schema_field: null, notes: "contact id" }
  optional:
    - { name: type, format: char, source: get, schema_field: null, notes: "c|v" }
    - { name: qty, format: float, source: get, schema_field: null, notes: "quantity for tiered pricing" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: "evaluates price levels (pricesLevels); vendors.php::quote extends for vendor cost"
returns:
  success_signal: JSON with computed price/cost
  identifier:     null
errors:        ["permission denied", "missing iID/cID returns empty"]
idempotency:   safe; read-only
related:       [inventory.prices.list]
confidence:    medium
source:        src/controllers/inventory/prices.php:394
```

## inventory.prices.aging

```yaml
id:            inventory.prices.aging
title:         Inventory aging report data
route:         inventory/prices/aging
http_method:   GET
ui_path:       Inventory ▸ Reports ▸ Aging
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: HTML/JSON aging report
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.prices.details]
confidence:    medium
source:        src/controllers/inventory/prices.php:329
```

## inventory.bulk.list

```yaml
id:            inventory.bulk.list
title:         Bulk-edit grid shell
route:         inventory/bulkEdit/manager
http_method:   GET
ui_path:       Inventory ▸ Bulk Edit
auth: { sec_id: invBulkEdit, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders editable grid bound to managerRows
returns:
  success_signal: HTML grid layout
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.bulk.list_rows, inventory.bulk.save]
confidence:    high
source:        src/controllers/inventory/bulkEdit.php:49
```

## inventory.bulk.list_rows

```yaml
id:            inventory.bulk.list_rows
title:         Bulk-edit grid data feed
route:         inventory/bulkEdit/managerRows
http_method:   GET
ui_path:       Inventory ▸ Bulk Edit (grid AJAX)
auth: { sec_id: invBulkEdit, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: search, format: text, source: get, schema_field: null, notes: "filter" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON {total, rows[]}
  identifier:     inventory.id
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.bulk.list]
confidence:    high
source:        src/controllers/inventory/bulkEdit.php:113
```

## inventory.bulk.save

```yaml
id:            inventory.bulk.save
title:         Apply bulk field edits to inventory rows
route:         inventory/bulkEdit/save
http_method:   POST
ui_path:       Inventory ▸ Bulk Edit ▸ Save
auth: { sec_id: invBulkEdit, min_level: 3 }
preconditions: ["target item ids exist"]
inputs:
  required:
    - { name: items, format: text, source: post, schema_field: null, notes: "json array of {id, field:value} edits" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory update (only the edited columns; oversize strings clipped via clipToColumnLengths)"
  gl_journal:  none
  inventory:   "edits item master fields only — no qty/stock or GL movement"
  side_effects: "string clipping to column lengths"
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 3)", "invalid item payload"]
idempotency:   "idempotent — re-applying the same field values is a no-op"
related:       [inventory.item.save]
confidence:    high
source:        src/controllers/inventory/bulkEdit.php:146
```

## inventory.design.list

```yaml
id:            inventory.design.list
title:         List work-order designs
route:         inventory/design/manager
http_method:   GET
ui_path:       Inventory ▸ Designs
auth: { sec_id: woDesign, min_level: 1 }
preconditions: []
inputs: { required: [], optional: [], fixed: [] }
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders grid (managerRows)
returns:
  success_signal: HTML grid layout
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.design.edit]
confidence:    high
source:        src/controllers/inventory/design.php:89
```

## inventory.design.edit

```yaml
id:            inventory.design.edit
title:         Edit a work-order design
route:         inventory/design/edit
http_method:   GET
ui_path:       Inventory ▸ Designs ▸ (open/new)
auth: { sec_id: woDesign, min_level: "2 if new, 3 if existing" }
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory_meta.id, notes: "design id; absent => new" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: renders design editor (production_job meta)
returns:
  success_signal: HTML editor
  identifier:     null
errors:        ["permission denied"]
idempotency:   safe; read-only
related:       [inventory.design.save]
confidence:    high
source:        src/controllers/inventory/design.php:166
```

## inventory.design.save

```yaml
id:            inventory.design.save
title:         Create/update a work-order design
route:         inventory/design/save
http_method:   POST
ui_path:       Inventory ▸ Designs ▸ Save
auth: { sec_id: woDesign, min_level: "2 if new, 3 if existing" }
preconditions: []
inputs:
  required: []
  optional:
    - { name: id, format: integer, source: post, schema_field: inventory_meta.id, notes: "present => update (level 3)" }
  fixed: []
effects:
  db_writes:   "inventory_meta (production_job)"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success with design id
  identifier:     inventory_meta.id
errors:        ["permission denied"]
idempotency:   "update by id idempotent"
related:       [inventory.design.edit, inventory.design.delete]
confidence:    high
source:        src/controllers/inventory/design.php:213
```

## inventory.design.copy

```yaml
id:            inventory.design.copy
title:         Duplicate a work-order design
route:         inventory/design/copy
http_method:   POST
ui_path:       Inventory ▸ Designs ▸ Copy
auth: { sec_id: woDesign, min_level: 2 }
preconditions: ["source design exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory_meta.id, notes: "source design" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory_meta insert (new design)"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success with new id
  identifier:     inventory_meta.id
errors:        ["permission denied (level < 2)"]
idempotency:   "NOT idempotent — duplicates each call"
related:       [inventory.design.save]
confidence:    high
source:        src/controllers/inventory/design.php:150
```

## inventory.design.delete

```yaml
id:            inventory.design.delete
title:         Delete a work-order design
route:         inventory/design/delete
http_method:   POST
ui_path:       Inventory ▸ Designs ▸ Delete
auth: { sec_id: woDesign, min_level: 4 }
preconditions: ["design id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory_meta.id, notes: "design to delete" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory_meta delete"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 4)"]
idempotency:   "safe; no-op if absent"
related:       [inventory.design.save]
confidence:    high
source:        src/controllers/inventory/design.php:237
```

## inventory.task.save

```yaml
id:            inventory.task.save
title:         Create/update a work-order task
route:         inventory/tasks/save
http_method:   POST
ui_path:       Inventory ▸ Tasks ▸ Save
auth: { sec_id: woTasks, min_level: "2 if new, 3 if existing" }
preconditions: []
inputs:
  required: []
  optional:
    - { name: id, format: integer, source: post, schema_field: inventory_meta.id, notes: "present => update (level 3)" }
  fixed: []
effects:
  db_writes:   "inventory_meta (production_task)"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success with task id
  identifier:     inventory_meta.id
errors:        ["permission denied"]
idempotency:   "update by id idempotent"
related:       [inventory.task.delete]
confidence:    high
source:        src/controllers/inventory/tasks.php:103
```

## inventory.task.delete

```yaml
id:            inventory.task.delete
title:         Delete a work-order task
route:         inventory/tasks/delete
http_method:   POST
ui_path:       Inventory ▸ Tasks ▸ Delete
auth: { sec_id: woTasks, min_level: 4 }
preconditions: ["task id exists"]
inputs:
  required:
    - { name: rID, format: integer, source: post, schema_field: inventory_meta.id, notes: "task to delete" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory_meta delete"
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: JSON success
  identifier:     null
errors:        ["permission denied (level < 4)"]
idempotency:   "safe; no-op if absent"
related:       [inventory.task.save]
confidence:    high
source:        src/controllers/inventory/tasks.php:109
```

## inventory.history.movement

```yaml
id:            inventory.history.movement
title:         Inventory movement report (per-SKU transaction history)
route:         inventory/history/movement
http_method:   GET
ui_path:       Inventory ▸ History ▸ Movement
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id to scope to" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: rows feed checks j{jID}_mgr level-1 per journal type
returns:
  success_signal: HTML/JSON movement report
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.history.historian]
confidence:    high
source:        src/controllers/inventory/history.php:46
```

## inventory.history.historian

```yaml
id:            inventory.history.historian
title:         Cost/quantity historian report
route:         inventory/history/historian
http_method:   GET
ui_path:       Inventory ▸ History ▸ Historian
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: none
returns:
  success_signal: HTML/JSON historian report
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.history.movement]
confidence:    high
source:        src/controllers/inventory/history.php:143
```

## inventory.tools.merge_save

```yaml
id:            inventory.tools.merge_save
title:         Merge one SKU into another (destructive)
route:         inventory/tools/mergeSave
http_method:   POST
ui_path:       Inventory ▸ Tools ▸ Merge SKUs
auth: { sec_id: inv_mgr, min_level: 5 }
preconditions: ["both source and target SKUs exist"]
inputs:
  required:
    - { name: source, format: integer, source: post, schema_field: inventory.id, notes: "SKU to merge FROM (deleted after)" }
    - { name: target, format: integer, source: post, schema_field: inventory.id, notes: "SKU to merge INTO" }
  optional: []
  fixed: []
effects:
  db_writes:   "re-points history/journal references from source to target, then deletes the source item"
  gl_journal:  "none new (re-points existing references)"
  inventory:   "consolidates two item masters; source removed"
  side_effects: "HIGH IMPACT — irreversible consolidation"
returns:
  success_signal: JSON success
  identifier:     target inventory.id
errors:        ["permission denied (level < 5)", "source/target invalid"]
idempotency:   "NOT idempotent — once source is gone a retry fails; verify before retry"
related:       [inventory.item.rename, inventory.item.delete]
confidence:    medium
source:        src/controllers/inventory/tools.php:66
```

## inventory.tools.history_repair

```yaml
id:            inventory.tools.history_repair
title:         Test/repair inventory history integrity
route:         inventory/tools/historyTestRepair
http_method:   POST
ui_path:       Inventory ▸ Tools ▸ Repair History
auth: { sec_id: admin, min_level: 3 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:   "may rewrite inventory_history rows to fix integrity"
  gl_journal:  none
  inventory:   "recomputes/repairs historical cost-layer data"
  side_effects: "maintenance operation; back up first"
returns:
  success_signal: JSON repair summary
  identifier:     null
errors:        ["permission denied (admin level < 3)"]
idempotency:   "re-runnable; converges on a consistent state"
related:       [inventory.tools.onorder_repair]
confidence:    medium
source:        src/controllers/inventory/tools.php:423
```

## inventory.tools.onorder_repair

```yaml
id:            inventory.tools.onorder_repair
title:         Recompute on-order quantities
route:         inventory/tools/onOrderRepair
http_method:   POST
ui_path:       Inventory ▸ Tools ▸ Repair On-Order
auth: { sec_id: admin, min_level: 3 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:   "UPDATE inventory.qty_on_order from open purchase orders"
  gl_journal:  none
  inventory:   "recomputes on-order counters (not on-hand stock or GL)"
  side_effects: "maintenance operation"
returns:
  success_signal: JSON repair summary
  identifier:     null
errors:        ["permission denied (admin level < 3)"]
idempotency:   "re-runnable; recomputes from source of truth"
related:       [inventory.tools.history_repair]
confidence:    medium
source:        src/controllers/inventory/tools.php:510
```

## inventory.api.import

```yaml
id:            inventory.api.import
title:         CSV import / upsert of inventory items
route:         inventory/api/apiImport
http_method:   POST
ui_path:       Inventory ▸ Tools ▸ Import
auth: { sec_id: admin, min_level: 2 }
preconditions: ["CSV columns map to inventory fields; matches InventoryTemplate.csv"]
inputs:
  required:
    - { name: (uploaded CSV), format: file, source: post, schema_field: inventory.*, notes: "rows keyed by sku" }
  optional: []
  fixed: []
effects:
  db_writes:   "inventory insert/update — UPSERT on sku; new rows get default GL accounts"
  gl_journal:  none
  inventory:   "creates/updates item masters; does NOT set opening stock balances or post GL"
  side_effects: "GL defaults applied to newly created items"
returns:
  success_signal: JSON import summary (counts)
  identifier:     null
errors:        ["permission denied (admin level < 2)", "malformed CSV"]
idempotency:   "idempotent on sku — re-import updates the same rows"
related:       [inventory.api.export, inventory.api.template]
confidence:    high
source:        src/controllers/inventory/api.php:92 (upsert at 113)
```

## inventory.api.export

```yaml
id:            inventory.api.export
title:         Export inventory items to CSV
route:         inventory/api/apiExport
http_method:   GET
ui_path:       Inventory ▸ Tools ▸ Export
auth: { sec_id: admin, min_level: 1 }
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: streams a CSV download
returns:
  success_signal: CSV file stream
  identifier:     null
errors:        ["permission denied (admin level < 1)"]
idempotency:   safe; read-only
related:       [inventory.api.import]
confidence:    high
source:        src/controllers/inventory/api.php:186
```

---

## Loaders (editor sub-tabs, read-only render)

These render tabs inside the item editor. They are read-mostly and chained off
`inventory.item.edit`; an agent rarely calls them directly.

## inventory.loader.vendors

```yaml
id:            inventory.loader.vendors
title:         Render the vendor-cost tab for an item
route:         inventory/vendors/vendorsLoad
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Vendors tab
auth: { sec_id: inv_mgr, min_level: 1 (soft) }
preconditions: ["item id supplied"]
inputs:
  required:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id; absent => returns" }
  optional: []
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: reads invVendors json + price_v cache
returns:
  success_signal: HTML vendor grid
  identifier:     null
errors:        ["permission denied (soft check)"]
idempotency:   safe; read-only
related:       [inventory.prices.quote]
confidence:    high
source:        src/controllers/inventory/vendors.php:68
```

## inventory.loader.accessory

```yaml
id:            inventory.loader.accessory
title:         Render the accessories tab (and its row feed)
route:         inventory/accessory/accessoryEdit | accessoryList
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Accessories tab
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: ["item id supplied for the list feed"]
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: reads invAccessory json blob
returns:
  success_signal: HTML grid / JSON {total, rows[]}
  identifier:     accessory item ids
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.edit]
confidence:    high
source:        src/controllers/inventory/accessory.php:43 (list 113)
```

## inventory.loader.options

```yaml
id:            inventory.loader.options
title:         Render the master-item options tab
route:         inventory/options/optionsEdit
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Options tab (ms master items)
auth: { sec_id: inv_mgr, min_level: 1 }
preconditions: ["item id supplied"]
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "master item id" }
  fixed: []
effects:
  db_writes:   none
  gl_journal:  none
  inventory:   none
  side_effects: reads invOptions json blob
returns:
  success_signal: HTML options layout
  identifier:     null
errors:        ["permission denied (level < 1)"]
idempotency:   safe; read-only
related:       [inventory.item.edit]
confidence:    high
source:        src/controllers/inventory/options.php:43
```

## inventory.loader.images

```yaml
id:            inventory.loader.images
title:         Render the images tab for an item
route:         inventory/images/imagesLoad
http_method:   GET
ui_path:       Inventory ▸ item editor ▸ Images tab
auth: { sec_id: NONE, min_level: 0 }   # NO validateAccess call — see Open questions
preconditions: ["item id supplied"]
inputs:
  required: []
  optional:
    - { name: rID, format: integer, source: get, schema_field: inventory.id, notes: "item id" }
  fixed: []
effects:
  db_writes:   "UPDATE inventory.invImages when orphaned image paths are pruned (line 70)"
  gl_journal:  none
  inventory:   "no qty/GL movement; rewrites the image json list only"
  side_effects: "self-healing orphan cleanup writes back to the row WITHOUT an access check"
returns:
  success_signal: HTML image gallery
  identifier:     null
errors:        ["none — method is ungated"]
idempotency:   "safe; cleanup converges"
related:       [inventory.item.edit]
confidence:    high
source:        src/controllers/inventory/images.php:42 (dbWrite at 70)
```

---

## Common agent recipes

```yaml
recipes:
  - name: Create a new stocked item
    steps:
      - inventory.item.save        # POST sku, inventory_type=si, gl_inv/gl_sales/gl_cogs, item_cost
      - inventory.item.save_pro    # (auto-chained) persist any vendor/BOM sub-data
    notes: "Sets up the master only. Opening stock balances are NOT set here — post a beginning-balance entry in phreebooks, or use a receipt (jID 6)."

  - name: Define and build an assembly
    steps:
      - inventory.item.save        # create the ma/sa assembly with a bill_of_materials (via save_pro)
      - inventory.build.add        # open a new work order (jID 32)
      - inventory.build.save       # set qty; reserves component qty_alloc
      - inventory.build.complete_step  # complete the final step => Assembly GL post (jID 14) + stock move
    notes: "Only complete_step touches GL/stock. Treat it as a money-moving action. Do not retry blindly."

  - name: Reverse an assembly
    steps:
      - inventory.build.delete     # un-assembles: deletes jID 14 entry, reverses stock + allocation
    notes: "Compound GL+stock reversal; level 4."

  - name: Bulk-update item attributes (price, description, GL accounts)
    steps:
      - inventory.bulk.list_rows   # fetch ids
      - inventory.bulk.save        # POST items=[{id, field:value}, ...]
    notes: "Master-field edits only; never moves stock or posts GL."

  - name: Import a product catalog
    steps:
      - inventory.api.export       # (optional) pull template/current data
      - inventory.api.import       # upsert by sku; admin level 2
    notes: "Idempotent on sku. New rows get default GL accounts; opening stock NOT created."

  - name: Consolidate duplicate SKUs
    steps:
      - inventory.tools.merge_save # level 5, destructive — source SKU is deleted
    notes: "Irreversible. Prefer rename if you only need to change the SKU string."

  - name: Price/cost quote during order entry
    steps:
      - inventory.prices.quote     # GET iID, cID, qty, type
    notes: "Read-only; vendor cost path is extended by vendors.php::quote."
```

---

## Open questions / verify-before-automating

- **`inventory/images/imagesLoad` (images.php:42) is ungated and WRITES.** It has
  no `validateAccess` call at all, and at line 70 it does a `dbWrite` to
  `inventory.invImages` (orphan-image cleanup) for any `rID`. An unauthenticated/
  under-privileged caller can trigger a row write. **Security finding.**
- **`inventory/tools/qtyAllocRepair` (tools.php:482) is ungated and WRITES.** It
  has no `validateAccess` guard and performs `UPDATE inventory.qty_alloc`. This
  mutates allocation counters for any caller. **Security finding.**
- **`inventory/attributes/adminAttrSave` (attributes.php:134) is ungated and
  WRITES.** No access check before persisting attribute meta via
  `setModuleCache`. **Security finding.**
- **`inventory/main/getStockAssy` (main.php:1073) is ungated** (reads assembly
  stock). Read-only but should still be gated to `inv_mgr` 1.
- **`inventory/main/getCostAssy` (main.php:787)**: the `validateAccess('inv_mgr',1)`
  check runs at ~line 794, *after* side-effect computations at 791/793, and
  line 790 appears to reference an undefined `$rID`. Verify the guard order and
  the variable before relying on this; possible bug. Confidence: medium.
- **`inventory/main/edit` (main.php:236) and `inventory/main/properties`
  (main.php:455)**: `edit` computes `$security = validateAccess('inv_mgr',1)`
  but does **not** early-return on failure — it only uses the flag to toggle
  edit/delete icons. `properties` has no own guard and delegates to `edit`.
  These are render-only, but the soft gating is worth confirming is intentional.
- **Ungated read/chart/download helpers in tools.php**: `merge` (47),
  `chartForecastGo` (216), `chartHistPurch` (232), `chartHistSales` (248),
  `chartSales` (311), `chartSalesGo` (353), `invDataGo` (367), `stockAging`
  (383), `priceAssy` (606), `invBalance` (654), `invBalanceNext` (677),
  `recalcHistory` (738), `recalcHistoryNext` (757). Several of the
  `*Next`/`recalc*` pairs perform writes during multi-step batch jobs
  (`priceAssyNext` at 620 writes `item_cost`); confirm these are only reachable
  after a guarded entry method.
- **Ungated loaders in attributes.php**: `attrLoad` (46), `attrDetails` (69),
  `adminAttrLoad` (93), `adminRows` (122) — read-only renders with no guard.
- **`inventory.build.complete_step` GL post (jID 14)** fires only when
  `erp_entry` is enabled and the step is the completing step. Confirm the
  `erp_entry` config and step state before automated completion to avoid a
  double-post; the method is not idempotent.
- **`inventory_type` enum** drives whether an item hits COGS/stock
  (`INVENTORY_COGS_TYPES` = si, sr, ma, sa). Verify the type before assuming a
  save/import has accounting consequences.
