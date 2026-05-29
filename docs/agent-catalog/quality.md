---
title: Quality — Agent Action Catalog
module: quality
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Quality — Agent Action Catalog

Machine-readable actions for the `quality` module — Bizuno's quality-control
surface: corrective/preventive-action tickets (CA/PA), quality objectives,
audits, and training records, plus the reusable task templates and document
links behind them. Read the [catalog schema and conventions](./README.md)
first; this file assumes the route, auth-level, and field conventions defined
there.

Pages in this module: `tickets` (CA/PA tickets), `objectives` (quality
objectives), `audits` (audit activity), `training` (training activity),
`adminAudits` (audit task templates), `adminTraining` (training task
templates), and `admin` (module settings, QA-document rendering, and a
Roles-tab hook). The dashboards under `dashboards/` are render-only widgets and
are not catalogued as standalone routes.

## Everything here is bookkeeping-neutral

> **Key safety fact for an acting agent:** **no action in this module posts a
> general-ledger journal or moves inventory.** Every write goes to one of two
> places only: a header row in `journal_main` (tickets jID 30, audits jID 31,
> training jID 34) or a `common_meta` row (objectives, audit/training task
> templates, option dictionaries). The shared `mgrJournal` base
> (`saveDB`/`deleteDB`) writes and deletes **only the `journal_main` header
> row** — it never touches `journal_item`, never calls the posting engine, and
> never writes an inventory table. The ticket `contact_id`/`sku_id` fields are
> stored as plain reference pointers (`contact_id_b`, `purch_order_id`); they
> do not create stock movement or AP/AR. So this module is safe to automate
> without accounting consequences.

## Two storage shapes

Quality screens all extend the same `mgrJournal` base but split across two
persistence styles:

- **Journal-backed** (`tickets`, `audits`, `training`): the *activity* record
  is a `journal_main` header row stamped with the page's `journal_id`. The
  field structure maps editor keys onto repurposed `journal_main` columns
  (e.g. `title`→`description`, `status`→`printed`, `creation_date`/`due_date`/
  `train_date`→`post_date`, `contact_id`→`rep_id` or `contact_id_b`). Tickets
  additionally store their workflow panels (stop-work, work-around, root-cause,
  corrective-action, close-out) in `common_meta` keyed `qa_ticket`. Auto-number
  references come from `getNextReference()` against the page's counter
  (`next_ticket_num`, `next_audit_num`, `next_training_num`).
- **Meta-backed** (`objectives`, `adminAudits` audit task templates,
  `adminTraining` training task templates): the record is a single
  `common_meta` row, `meta_key` = the page's `metaPrefix`
  (`quality_objective`, `quality_audit`, `training`), `meta_value` = a JSON
  blob of the field structure. The surrogate id is the meta row id, posted as
  `_rID` (not `rID`).

## Data model summary

```yaml
journal_backed:
  tickets:
    table: journal_main          # one header row per CA/PA ticket
    journal_id: 30
    key_surrogate: id            # rID in routes
    ref_counter: next_ticket_num # invoice_num auto-number
    meta: common_meta meta_key=qa_ticket (workflow panels, keyed by journal id)
    sec_id: qa_ticket
  audits:
    table: journal_main
    journal_id: 31
    ref_counter: next_audit_num
    sec_id: qa_audit
  training:
    table: journal_main
    journal_id: 34
    ref_counter: next_training_num
    sec_id: qa_train
meta_backed:
  objectives:
    table: common_meta
    meta_key: quality_objective
    key_surrogate: _rID
    ref_counter: next_qaobj_num
    sec_id: qa_obj
  audit_task_templates:           # adminAudits page
    table: common_meta
    meta_key: quality_audit
    ref_counter: next_audit_num
    sec_id: qa_audit
  training_task_templates:        # adminTraining page
    table: common_meta
    meta_key: training
    ref_counter: next_training_num
    sec_id: admin                 # NB: gated on the admin key, not qa_train
option_dictionaries:              # rebuilt by qualityAdmin::initialize()
  common_meta: options_qa_status, options_frequencies, options_lead_times
security_keys:                    # the validateAccess() $secID per page
  qa_ticket: tickets
  qa_obj:    objectives
  qa_audit:  audits + audit task templates
  qa_train:  training activity
  admin:     training task templates, module settings, QA-doc render
gl_impact: none                   # no action in this module posts GL
inventory_impact: none            # no action in this module moves stock
```

> **Quirk to respect:** `qualityTickets::edit` validates at **level 2 (add)**,
> not level 1 (view) like every other read route in this module. An agent with
> only view rights on `qa_ticket` can list and read rows via `managerRows` but
> **cannot open the edit form**. See `quality.ticket.read` below.

---

## quality.ticket.list

```yaml
id: quality.ticket.list
title: List / query CA-PA tickets (datagrid shell)
route: quality/tickets/manager
http_method: GET
ui_path: Quality ▸ Tickets (Corrective / Preventive Actions)
auth:
  sec_id: qa_ticket
  min_level: 1
preconditions:
  - quality module enabled for the business
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: post
      notes: store filter; -1 = all stores (default)
    - name: closed
      format: char
      source: post
      notes: a (all, default) | 1 (closed) | 0 (open)
    - name: status
      format: db_field
      source: post
      notes: a (all, default) or a qa_status code (1..99) from options_qa_status
    - name: period
      format: cmd
      source: post
      notes: defaults to 'a' (all periods) — tickets are deliberately not period-bound
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders the EasyUI dgTickets datagrid
    - emits bizQualStatuses JS map (status code → localized label)
    - conditionally adds QA process/standard/instruction toolbar buttons when the matching module setting (proc_/stnd_/inst_qa_ticket) is populated
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks qa_ticket level 1
idempotency: safe (read-only)
related: [quality.ticket.list.rows, quality.ticket.read]
confidence: high
source: src/controllers/quality/tickets.php:206 (manager)
```

## quality.ticket.list.rows

```yaml
id: quality.ticket.list.rows
title: Fetch CA-PA ticket rows (data only)
route: quality/tickets/managerRows
http_method: GET
ui_path: (AJAX backing dgTickets)
auth:
  sec_id: qa_ticket
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: free-text over invoice_num, description, email_b, purch_order_id
    - name: closed
      format: char
      source: post
    - name: status
      format: db_field
      source: post
    - name: store_id
      format: integer
      source: post
    - name: page
      format: integer
      source: get
    - name: rows
      format: integer
      source: get
    - name: mgrAction
      format: cmd
      source: get
      notes: qa_by_vendor | qa_by_sku — dashboard pie-slice drill-down filter
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - maps the meta field structure onto journal_main columns before querying
    - WHERE includes journal_id=30 implicitly via the ticket grid base
returns:
  success_signal: JSON rows + total count
  identifier: each row includes id (rID) and invoice_num (ticket reference)
errors:
  - permission denied if user lacks qa_ticket level 1
idempotency: safe (read-only)
related: [quality.ticket.list, quality.ticket.read]
confidence: high
source: src/controllers/quality/tickets.php:228 (managerRows)
```

## quality.ticket.read

```yaml
id: quality.ticket.read
title: Open a CA-PA ticket edit form (read its full detail + workflow panels)
route: quality/tickets/edit
http_method: GET
ui_path: Quality ▸ Tickets ▸ open record
auth:
  sec_id: qa_ticket
  min_level: 2   # NB: edit() guards at level 2 (add), NOT level 1 — view-only users cannot open it
preconditions:
  - the ticket rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: ticket journal_main id; 0 renders a blank new-ticket form
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the journal_main header then merges the qa_ticket common_meta blob (stop-work / work-around / root-cause / corrective-action / close-out panels) into the form
    - attaches the file-attachment panel (path = quality/tickets/, prefix rID_<rID>_)
returns:
  success_signal: editor layout.fields populated
  identifier: ticket fields + meta panels
errors:
  - permission denied if user lacks qa_ticket level 2
idempotency: safe (read-only)
related: [quality.ticket.list.rows, quality.ticket.save]
confidence: high
source: src/controllers/quality/tickets.php:236 (edit)
```

## quality.ticket.save

```yaml
id: quality.ticket.save
title: Create or update a CA-PA ticket
route: quality/tickets/save
http_method: POST
ui_path: Quality ▸ Tickets ▸ open/new record ▸ Save
auth:
  sec_id: qa_ticket
  min_level: 2 on create (no id posted) / 3 on update (id posted)
preconditions:
  - on update, id refers to an existing ticket journal_main row
inputs:
  required:
    - name: title
      format: text
      source: post
      schema_field: journal_main.description
      notes: ticket title
  optional:
    - name: id
      format: integer
      source: post
      notes: existing ticket rID. Presence switches save into update mode (level 3); absence is create (level 2).
    - name: status
      format: integer
      source: post
      schema_field: journal_main.printed
      notes: qa_status code from options_qa_status (1..99)
    - name: preventable
      format: bolean
      source: post
      schema_field: journal_main.waiting
      notes: 0=corrective / 1=preventive
    - name: store_id
      format: integer
      source: post
      schema_field: journal_main.store_id
    - name: creation_date
      format: date
      source: post
      schema_field: journal_main.post_date
    - name: requested_by
      format: integer
      source: post
      schema_field: journal_main.rep_id
      notes: user id of the person who found/raised the issue
    - name: contact_id
      format: integer
      source: post
      schema_field: journal_main.contact_id_b
      notes: vendor reference pointer only — no AP/AR effect
    - name: sku_id
      format: integer
      source: post
      schema_field: journal_main.purch_order_id
      notes: SKU reference pointer only — no inventory movement
    - name: closed
      format: bolean
      source: post
      schema_field: journal_main.closed
    - name: action_date
      format: date
      source: post
      schema_field: journal_main.terminal_date
    - name: close_end_date
      format: date
      source: post
      schema_field: journal_main.closed_date
    - name: notes / audit_notes / issue_notes / action_notes / contact_notes
      format: text
      source: post
      notes: editor panels (stop-work / work-around / root-cause / corrective / close-out) — stored in the qa_ticket common_meta blob, not journal_main
    - name: file_attach
      format: file
      source: post
      notes: optional attachment; saved to quality/tickets/rID_<rID>_ and sets attach=1
  fixed:
    - name: journal_id
      value: 30
      notes: forced by saveDB
    - name: invoice_num
      value: getNextReference(next_ticket_num)
      notes: assigned only on create (when no id posted)
effects:
  db_writes:
    - table: journal_main
      op: insert (create) / update
      notes: header row only — no journal_item, no posting
    - table: common_meta
      op: insert/update
      notes: meta_key qa_ticket, keyed by the journal id — holds the workflow-panel fields
  gl_journal: none
  inventory: none
  side_effects:
    - on create, auto-assigns invoice_num from next_ticket_num
    - saves any uploaded attachment and flags attach=1
    - emits msg_record_saved; reloads dgTickets
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: ticket rID (new id echoed into $_POST['id'] on insert)
errors:
  - permission denied if user lacks the required level (2 create / 3 update)
idempotency: >
  update is idempotent (re-applying the same fields yields the same row);
  create is NOT idempotent — a blind retry mints a new next_ticket_num row.
  Pre-check via quality.ticket.list.rows or pass the known id to force update.
related: [quality.ticket.read, quality.ticket.delete]
confidence: high
source: src/controllers/quality/tickets.php:253 (save); src/model/manager.php:347 (saveDB)
```

## quality.ticket.delete

```yaml
id: quality.ticket.delete
title: Delete a CA-PA ticket
route: quality/tickets/delete
http_method: GET
ui_path: Quality ▸ Tickets ▸ open record ▸ Trash
auth:
  sec_id: qa_ticket
  min_level: 4
preconditions:
  - rID refers to an existing ticket
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: the qa_ticket workflow blob (when present), deleted first
    - table: journal_main
      op: delete
      notes: the header row (id=rID); raw DELETE, no referential guard
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the ticket's attachment zips (quality/tickets/rID_<rID>_*.zip)
    - reloads dgTickets
returns:
  success_signal: delete eval action returned; grid reloads
  identifier: none
errors:
  - illegal_access if rID missing
  - permission denied if user lacks qa_ticket level 4
idempotency: idempotent (deleting an already-gone row is a no-op)
related: [quality.ticket.save]
confidence: high
source: src/controllers/quality/tickets.php:266 (delete); src/model/manager.php:394 (deleteDB)
```

## quality.ticket.export

```yaml
id: quality.ticket.export
title: Export quality-dashboard ticket data to CSV
route: quality/tickets/exportData
http_method: GET
ui_path: Quality ▸ Tickets dashboard ▸ Export
auth:
  sec_id: qa_ticket
  min_level: 1
preconditions:
  - the queried source table exists (see confidence note)
inputs:
  required: []
  optional:
    - name: type
      format: alpha_num
      source: get
      notes: report variant; only 'status' is implemented (default falls through to it)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - streams QAdata-<date>.csv of CA tickets by status
returns:
  success_signal: file download
  identifier: none
errors:
  - no_results message if the query returns nothing
  - permission denied if user lacks qa_ticket level 1
idempotency: safe (read-only)
related: [quality.ticket.list.rows]
confidence: medium   # queries BIZUNO_DB_PREFIX.'extISO9001', a table absent from core install/tables.php — verify the table exists before relying on this
source: src/controllers/quality/tickets.php:281 (exportData)
```

## quality.objective.list

```yaml
id: quality.objective.list
title: List quality objectives (datagrid shell)
route: quality/objectives/manager
http_method: GET
ui_path: Quality ▸ Objectives
auth:
  sec_id: qa_obj
  min_level: 1
preconditions:
  - quality module enabled
inputs:
  required: []
  optional:
    - name: closed
      format: char
      source: post
      notes: a (all, default) | 1 | 0
    - name: status
      format: db_field
      source: post
      notes: a (all) or a qa_status code
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders dgObjectives meta-grid
    - emits bizQualStatuses + qualityReps JS maps
    - removes the per-row copy action from the grid
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks qa_obj level 1
idempotency: safe (read-only)
related: [quality.objective.list.rows, quality.objective.read]
confidence: high
source: src/controllers/quality/objectives.php:115 (manager)
```

## quality.objective.list.rows

```yaml
id: quality.objective.list.rows
title: Fetch quality-objective rows (data only)
route: quality/objectives/managerRows
http_method: GET
ui_path: (AJAX backing dgObjectives)
auth:
  sec_id: qa_obj
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: free-text over ref_num, title, obj_desc, obj_test, obj_result
    - name: closed
      format: char
      source: post
    - name: status
      format: db_field
      source: post
    - name: sort
      format: db_field
      source: post
      notes: default ref_num
    - name: order
      format: db_field
      source: post
      notes: ASC (default) / DESC
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads common_meta rows where meta_key = quality_objective
    - drops the status/closed/store_id filters when set to 'all'
returns:
  success_signal: JSON rows + total count
  identifier: each row includes _rID (meta id) and ref_num
errors:
  - permission denied if user lacks qa_obj level 1
idempotency: safe (read-only)
related: [quality.objective.list, quality.objective.read]
confidence: high
source: src/controllers/quality/objectives.php:136 (managerRows)
```

## quality.objective.read

```yaml
id: quality.objective.read
title: Open a quality-objective edit form (read its detail + action grid)
route: quality/objectives/edit
http_method: GET
ui_path: Quality ▸ Objectives ▸ open record
auth:
  sec_id: qa_obj
  min_level: 1
preconditions:
  - the objective _rID exists (or 0 for a new blank form)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: meta row id of the objective
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the quality_objective common_meta blob into the form
    - builds the embedded dgDetails "actions required" sub-grid from the dgObjData JSON field
    - removes the copy toolbar icon
returns:
  success_signal: editor layout.fields populated
  identifier: objective fields + actions sub-grid
errors:
  - permission denied if user lacks qa_obj level 1
idempotency: safe (read-only)
related: [quality.objective.list.rows, quality.objective.save]
confidence: high
source: src/controllers/quality/objectives.php:144 (edit)
```

## quality.objective.save

```yaml
id: quality.objective.save
title: Create or update a quality objective
route: quality/objectives/save
http_method: POST
ui_path: Quality ▸ Objectives ▸ open/new record ▸ Save
auth:
  sec_id: qa_obj
  min_level: 2 on create (no _rID) / 3 on update (_rID present)
preconditions:
  - on update, _rID refers to an existing quality_objective meta row
inputs:
  required:
    - name: title
      format: text
      source: post
      notes: objective title
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing objective meta id; absence = create (level 2), presence = update (level 3)
    - name: status
      format: alpha_num
      source: post
      notes: qa_status code
    - name: closed
      format: char
      source: post
    - name: entered_by
      format: integer
      source: post
      notes: user id
    - name: date_target
      format: date
      source: post
    - name: date_actual
      format: date
      source: post
    - name: obj_desc
      format: text
      source: post
      notes: objective description (editor)
    - name: obj_test
      format: text
      source: post
      notes: testing method (editor)
    - name: obj_result
      format: text
      source: post
      notes: result (editor)
    - name: closed_by
      format: integer
      source: post
    - name: dgObjData
      format: json
      source: post
      notes: JSON array of action-required rows (emp/step/dateS/dateE) from the embedded grid
  fixed:
    - name: ref_num
      value: getNextReference(next_qaobj_num)
      notes: assigned only on create
effects:
  db_writes:
    - table: common_meta
      op: insert (create) / update
      notes: meta_key quality_objective; whole record is one JSON blob
  gl_journal: none
  inventory: none
  side_effects:
    - stamps last_update/date_last to today if those keys exist in the blob
    - on create, auto-assigns ref_num from next_qaobj_num
    - emits msg_record_saved; reloads dgObjectives
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: objective _rID (new id echoed into $_POST['_rID'] on insert)
errors:
  - permission denied if user lacks the required level
idempotency: update idempotent; create NOT idempotent (mints a new next_qaobj_num row)
related: [quality.objective.read, quality.objective.copy, quality.objective.delete]
confidence: high
source: src/controllers/quality/objectives.php:162 (save); src/model/manager.php:367 (saveMeta)
```

## quality.objective.copy

```yaml
id: quality.objective.copy
title: Duplicate a quality objective under a new title
route: quality/objectives/copy
http_method: POST
ui_path: Quality ▸ Objectives ▸ open record ▸ Copy
auth:
  sec_id: qa_obj
  min_level: 2
preconditions:
  - source objective _rID exists; a non-blank new title is supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: source objective meta id to copy from
    - name: data
      format: text
      source: get
      notes: new title for the copy (passed as the prompt value)
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert
      notes: deep-copies the source quality_objective blob, replacing the title; new meta id assigned
  gl_journal: none
  inventory: none
  side_effects:
    - refreshes last_update/date_last; reloads dgObjectives and opens the new copy
returns:
  success_signal: grid reload + edit eval action; new id in $_GET['newID']
  identifier: newID (new meta id)
errors:
  - err_inv_title_blank if rID or the new title is empty
  - permission denied if user lacks qa_obj level 2
idempotency: NOT idempotent — each call inserts a new copy
related: [quality.objective.save]
confidence: high
source: src/controllers/quality/objectives.php:157 (copy); src/model/manager.php:324 (copyMeta)
```

## quality.objective.delete

```yaml
id: quality.objective.delete
title: Delete a quality objective
route: quality/objectives/delete
http_method: GET
ui_path: Quality ▸ Objectives ▸ open record ▸ Trash
auth:
  sec_id: qa_obj
  min_level: 4
preconditions:
  - rID refers to an existing objective meta row
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: removes the quality_objective row (_rID)
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the objective's attachment zips; reloads dgObjectives
returns:
  success_signal: delete eval action returned; grid reloads
  identifier: none
errors:
  - illegal_access if rID missing
  - permission denied if user lacks qa_obj level 4
idempotency: idempotent
related: [quality.objective.save]
confidence: high
source: src/controllers/quality/objectives.php:168 (delete); src/model/manager.php:413 (deleteMeta)
```

## quality.audit.list

```yaml
id: quality.audit.list
title: List audit activity records (datagrid shell)
route: quality/audits/manager
http_method: GET
ui_path: Quality ▸ Audits
auth:
  sec_id: qa_audit
  min_level: 1
preconditions:
  - quality module enabled
inputs:
  required: []
  optional:
    - name: period
      format: cmd
      source: post
      notes: date-range key; default 'y' (this year), 'a' = all periods
    - name: store_id
      format: integer
      source: post
    - name: closed
      format: char
      source: post
      notes: a | 1 | 0
    - name: status
      format: db_field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders dgAudits datagrid
    - emits fmtFreqs / fmtLeadTime / bizQualStatuses JS maps
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit.list.rows, quality.audit.read]
confidence: high
source: src/controllers/quality/audits.php:140 (manager)
```

## quality.audit.list.rows

```yaml
id: quality.audit.list.rows
title: Fetch audit-activity rows (data only)
route: quality/audits/managerRows
http_method: GET
ui_path: (AJAX backing dgAudits)
auth:
  sec_id: qa_audit
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: free-text over ref_num, title, notes
    - name: period
      format: cmd
      source: post
    - name: store_id
      format: integer
      source: post
    - name: closed
      format: char
      source: post
    - name: status
      format: db_field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - maps field structure onto journal_main columns; WHERE journal_id=31
    - drops filters set to 'all'
returns:
  success_signal: JSON rows + total count
  identifier: each row includes id (rID) and invoice_num
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit.list, quality.audit.read]
confidence: high
source: src/controllers/quality/audits.php:154 (managerRows)
```

## quality.audit.add

```yaml
id: quality.audit.add
title: Start a new audit activity from an audit task template (selector step)
route: quality/audits/add
http_method: GET
ui_path: Quality ▸ Audits ▸ New
auth:
  sec_id: qa_audit
  min_level: 2
preconditions:
  - at least one quality_audit task template exists to select from
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders a task-selector form (taskID dropdown of quality_audit templates); on Next it opens quality.audit.read pre-seeded from the chosen template. No write occurs here.
returns:
  success_signal: selector dialog HTML returned
  identifier: none
errors:
  - permission denied if user lacks qa_audit level 2
idempotency: safe (no write)
related: [quality.audit_task.list, quality.audit.read, quality.audit.save]
confidence: high
source: src/controllers/quality/audits.php:168 (add); src/model/manager.php:216 (addDB)
```

## quality.audit.read

```yaml
id: quality.audit.read
title: Open an audit-activity edit form
route: quality/audits/edit
http_method: GET
ui_path: Quality ▸ Audits ▸ open record
auth:
  sec_id: qa_audit
  min_level: 1
preconditions:
  - the audit rID exists (or comes seeded from a template via add)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: audit journal_main id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the journal_main header; if linked to a task template (ref_id) renders the template's doc_link
    - hides the frequency field; removes new/copy toolbar icons
    - attaches the file-attachment panel (path quality/audits/)
returns:
  success_signal: editor layout.fields populated
  identifier: audit fields
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit.list.rows, quality.audit.save]
confidence: high
source: src/controllers/quality/audits.php:173 (edit)
```

## quality.audit.save

```yaml
id: quality.audit.save
title: Create or update an audit activity record
route: quality/audits/save
http_method: POST
ui_path: Quality ▸ Audits ▸ open/new record ▸ Save
auth:
  sec_id: qa_audit
  min_level: 2 on create (no rID) / 3 on update (rID present)
preconditions:
  - on update, rID refers to an existing audit journal_main row
inputs:
  required:
    - name: title
      format: text
      source: post
      schema_field: journal_main.description
  optional:
    - name: rID
      format: integer
      source: post
      notes: existing audit id; presence = update (level 3), absence = create (level 2)
    - name: ref_id
      format: integer
      source: post
      schema_field: journal_main.so_po_ref_id
      notes: linked audit task-template meta id
    - name: due_date
      format: date
      source: post
      schema_field: journal_main.post_date
    - name: status
      format: integer
      source: post
      schema_field: journal_main.printed
    - name: closed
      format: bolean
      source: post
      schema_field: journal_main.closed
    - name: frequency
      format: char
      source: post
      schema_field: journal_main.recur_id
    - name: lead_time
      format: alpha_num
      source: post
      schema_field: journal_main.method_code
    - name: store_id
      format: integer
      source: post
      schema_field: journal_main.store_id
    - name: contact_id
      format: integer
      source: post
      schema_field: journal_main.rep_id
      notes: auditor (user id)
    - name: notes
      format: text
      source: post
      schema_field: journal_main.notes
    - name: file_attach
      format: file
      source: post
  fixed:
    - name: journal_id
      value: 31
    - name: invoice_num
      value: getNextReference(next_audit_num)
      notes: assigned only on create
effects:
  db_writes:
    - table: journal_main
      op: insert (create) / update
      notes: header row only — no journal_item, no posting
  gl_journal: none
  inventory: none
  side_effects:
    - on create, auto-assigns invoice_num; saves attachment and flags attach=1
    - emits msg_record_saved; reloads dgAudits
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: audit rID
errors:
  - permission denied if user lacks the required level
idempotency: update idempotent; create NOT idempotent
related: [quality.audit.read, quality.audit.delete]
confidence: high
source: src/controllers/quality/audits.php:190 (save); src/model/manager.php:347 (saveDB)
```

## quality.audit.delete

```yaml
id: quality.audit.delete
title: Delete an audit activity record
route: quality/audits/delete
http_method: GET
ui_path: Quality ▸ Audits ▸ open record ▸ Trash
auth:
  sec_id: qa_audit
  min_level: 4
preconditions:
  - rID refers to an existing audit row
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: journal_main
      op: delete
      notes: header row (id=rID); raw DELETE, no referential guard
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the audit's attachment zips; reloads dgAudits
returns:
  success_signal: delete eval action returned
  identifier: none
errors:
  - illegal_access if rID missing
  - permission denied if user lacks qa_audit level 4
idempotency: idempotent
related: [quality.audit.save]
confidence: high
source: src/controllers/quality/audits.php:196 (delete); src/model/manager.php:394 (deleteDB)
```

## quality.training.list

```yaml
id: quality.training.list
title: List training activity records (datagrid shell)
route: quality/training/manager
http_method: GET
ui_path: Quality ▸ Training
auth:
  sec_id: qa_train
  min_level: 1
preconditions:
  - quality module enabled
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: post
    - name: status
      format: db_field
      source: post
      notes: a (all) or an options_contact_status code
    - name: period
      format: cmd
      source: post
      notes: default 'y'
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders dgTraining datagrid
    - emits fmtFreqs / fmtLeadTime JS maps
    - removes the 'new' work icon and per-row copy action
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks qa_train level 1
idempotency: safe (read-only)
related: [quality.training.list.rows, quality.training.read]
confidence: high
source: src/controllers/quality/training.php:106 (manager)
```

## quality.training.list.rows

```yaml
id: quality.training.list.rows
title: Fetch training-activity rows (data only)
route: quality/training/managerRows
http_method: GET
ui_path: (AJAX backing dgTraining)
auth:
  sec_id: qa_train
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: free-text over ref_num, title, notes
    - name: store_id
      format: integer
      source: post
    - name: status
      format: db_field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - maps field structure onto journal_main columns; WHERE journal_id=34
returns:
  success_signal: JSON rows + total count
  identifier: each row includes id (rID) and invoice_num
errors:
  - permission denied if user lacks qa_train level 1
idempotency: safe (read-only)
related: [quality.training.list, quality.training.read]
confidence: high
source: src/controllers/quality/training.php:120 (managerRows)
```

## quality.training.add

```yaml
id: quality.training.add
title: Start a new training record from a training task template (selector step)
route: quality/training/add
http_method: GET
ui_path: Quality ▸ Training ▸ New
auth:
  sec_id: qa_train
  min_level: 2
preconditions:
  - at least one training task template exists to select from
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders a task-selector form; on Next it opens quality.training.read pre-seeded from the chosen template. No write here.
returns:
  success_signal: selector dialog HTML returned
  identifier: none
errors:
  - permission denied if user lacks qa_train level 2
idempotency: safe (no write)
related: [quality.training_task.list, quality.training.read, quality.training.save]
confidence: high
source: src/controllers/quality/training.php:128 (add); src/model/manager.php:216 (addDB)
```

## quality.training.read

```yaml
id: quality.training.read
title: Open a training-activity edit form
route: quality/training/edit
http_method: GET
ui_path: Quality ▸ Training ▸ open record
auth:
  sec_id: qa_train
  min_level: 1
preconditions:
  - the training rID exists (or comes seeded from a template via add)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: training journal_main id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the journal_main header; renders linked template doc_link when ref_id set
    - defaults train_date to today; hides frequency; removes new/copy icons
    - attaches the file-attachment panel (path quality/training/)
returns:
  success_signal: editor layout.fields populated
  identifier: training fields
errors:
  - permission denied if user lacks qa_train level 1
idempotency: safe (read-only)
related: [quality.training.list.rows, quality.training.save]
confidence: high
source: src/controllers/quality/training.php:134 (edit)
```

## quality.training.save

```yaml
id: quality.training.save
title: Create or update a training activity record
route: quality/training/save
http_method: POST
ui_path: Quality ▸ Training ▸ open/new record ▸ Save
auth:
  sec_id: qa_train
  min_level: 2 on create (no rID) / 3 on update (rID present)
preconditions:
  - on update, rID refers to an existing training journal_main row
inputs:
  required:
    - name: title
      format: text
      source: post
      schema_field: journal_main.description
  optional:
    - name: rID
      format: integer
      source: post
      notes: existing training id; presence = update (level 3), absence = create (level 2)
    - name: ref_id
      format: integer
      source: post
      schema_field: journal_main.so_po_ref_id
      notes: linked training task-template meta id
    - name: frequency
      format: char
      source: post
      schema_field: journal_main.recur_id
    - name: lead_time
      format: alpha_num
      source: post
      schema_field: journal_main.method_code
    - name: store_id
      format: integer
      source: post
      schema_field: journal_main.store_id
    - name: contact_id
      format: integer
      source: post
      schema_field: journal_main.rep_id
      notes: trainer (user id)
    - name: train_date
      format: date
      source: post
      schema_field: journal_main.post_date
    - name: notes
      format: text
      source: post
      schema_field: journal_main.notes
    - name: file_attach
      format: file
      source: post
  fixed:
    - name: journal_id
      value: 34
    - name: invoice_num
      value: getNextReference(next_training_num)
      notes: assigned only on create
effects:
  db_writes:
    - table: journal_main
      op: insert (create) / update
      notes: header row only — no journal_item, no posting
  gl_journal: none
  inventory: none
  side_effects:
    - on create, auto-assigns invoice_num; saves attachment and flags attach=1
    - emits msg_record_saved; reloads dgTraining
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: training rID
errors:
  - permission denied if user lacks the required level
idempotency: update idempotent; create NOT idempotent
related: [quality.training.read, quality.training.delete]
confidence: high
source: src/controllers/quality/training.php:152 (save); src/model/manager.php:347 (saveDB)
```

## quality.training.delete

```yaml
id: quality.training.delete
title: Delete a training activity record
route: quality/training/delete
http_method: GET
ui_path: Quality ▸ Training ▸ open record ▸ Trash
auth:
  sec_id: qa_train
  min_level: 4
preconditions:
  - rID refers to an existing training row
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: journal_main
      op: delete
      notes: header row (id=rID); raw DELETE, no referential guard
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the record's attachment zips; reloads dgTraining
returns:
  success_signal: delete eval action returned
  identifier: none
errors:
  - illegal_access if rID missing
  - permission denied if user lacks qa_train level 4
idempotency: idempotent
related: [quality.training.save]
confidence: high
source: src/controllers/quality/training.php:158 (delete); src/model/manager.php:394 (deleteDB)
```

## quality.audit_task.list

```yaml
id: quality.audit_task.list
title: List audit task templates (admin datagrid shell)
route: quality/adminAudits/manager
http_method: GET
ui_path: Quality ▸ Settings ▸ Audit Tasks tab
auth:
  sec_id: qa_audit
  min_level: 1
preconditions:
  - quality module enabled
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: post
    - name: closed
      format: char
      source: post
    - name: status
      format: db_field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders the audit-task-template meta-grid (dgAudits)
    - drops the period/jID filters; emits fmtFreqs / fmtLeadTime / bizQualStatuses maps
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit_task.list.rows, quality.audit_task.read]
confidence: high
source: src/controllers/quality/adminAudits.php:142 (manager)
```

## quality.audit_task.list.rows

```yaml
id: quality.audit_task.list.rows
title: Fetch audit task-template rows (data only)
route: quality/adminAudits/managerRows
http_method: GET
ui_path: (AJAX backing the audit-tasks grid)
auth:
  sec_id: qa_audit
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: sort
      format: db_field
      source: post
      notes: default 'title'
    - name: order
      format: db_field
      source: post
      notes: ASC (default)
    - name: status
      format: db_field
      source: post
    - name: closed
      format: char
      source: post
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads common_meta rows where meta_key = quality_audit
returns:
  success_signal: JSON rows + total count
  identifier: each row includes _rID (meta id)
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit_task.list, quality.audit_task.read]
confidence: high
source: src/controllers/quality/adminAudits.php:159 (managerRows)
```

## quality.audit_task.read

```yaml
id: quality.audit_task.read
title: Open an audit task-template edit form
route: quality/adminAudits/edit
http_method: GET
ui_path: Quality ▸ Settings ▸ Audit Tasks ▸ open record
auth:
  sec_id: qa_audit
  min_level: 1
preconditions:
  - the template _rID exists (or 0 for new)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: template meta id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the quality_audit meta blob; relabels due_date as "next audit"; removes the copy icon
returns:
  success_signal: editor layout.fields populated
  identifier: template fields
errors:
  - permission denied if user lacks qa_audit level 1
idempotency: safe (read-only)
related: [quality.audit_task.save]
confidence: high
source: src/controllers/quality/adminAudits.php:171 (edit)
```

## quality.audit_task.save

```yaml
id: quality.audit_task.save
title: Create or update an audit task template
route: quality/adminAudits/save
http_method: POST
ui_path: Quality ▸ Settings ▸ Audit Tasks ▸ Save
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - on update, _rID refers to an existing quality_audit meta row
inputs:
  required:
    - name: title
      format: text
      source: post
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing template meta id; absence inserts, presence updates
    - name: frequency
      format: char
      source: post
    - name: lead_time
      format: alpha_num
      source: post
    - name: status
      format: integer
      source: post
    - name: closed
      format: bolean
      source: post
    - name: store_id
      format: integer
      source: post
    - name: contact_id
      format: integer
      source: post
      notes: auditor
    - name: doc_link
      format: text
      source: post
    - name: notes
      format: text
      source: post
  fixed:
    - name: ref_num
      value: getNextReference(next_audit_num)
      notes: assigned only on create
effects:
  db_writes:
    - table: common_meta
      op: insert (create) / update
      notes: meta_key quality_audit
  gl_journal: none
  inventory: none
  side_effects:
    - emits msg_record_saved; reloads the audit-tasks grid
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: template _rID
errors: []   # NB: the method performs NO validateAccess() check
idempotency: update idempotent; create NOT idempotent
related: [quality.audit_task.read, quality.audit_task.copy, quality.audit_task.delete]
confidence: high
source: src/controllers/quality/adminAudits.php:182 (save); src/model/manager.php:367 (saveMeta)
```

## quality.audit_task.copy

```yaml
id: quality.audit_task.copy
title: Duplicate an audit task template under a new title
route: quality/adminAudits/copy
http_method: POST
ui_path: Quality ▸ Settings ▸ Audit Tasks ▸ open record ▸ Copy
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - source _rID exists; a non-blank new title is supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: source template meta id
    - name: data
      format: text
      source: get
      notes: new title for the copy
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert
      notes: deep-copies the quality_audit blob with the new title
  gl_journal: none
  inventory: none
  side_effects:
    - reloads the grid and opens the new copy
returns:
  success_signal: grid reload + edit eval action; newID in $_GET['newID']
  identifier: newID (new meta id)
errors:
  - err_inv_title_blank if rID or title empty
  # NB: no validateAccess() check
idempotency: NOT idempotent
related: [quality.audit_task.save]
confidence: high
source: src/controllers/quality/adminAudits.php:178 (copy); src/model/manager.php:324 (copyMeta)
```

## quality.audit_task.delete

```yaml
id: quality.audit_task.delete
title: Delete an audit task template
route: quality/adminAudits/delete
http_method: GET
ui_path: Quality ▸ Settings ▸ Audit Tasks ▸ open record ▸ Trash
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - rID refers to an existing quality_audit meta row
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: removes the quality_audit row (_rID)
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the template's attachment zips; reloads the grid
returns:
  success_signal: delete eval action returned
  identifier: none
errors:
  - illegal_access if rID missing
  # NB: no validateAccess() check
idempotency: idempotent
related: [quality.audit_task.save]
confidence: high
source: src/controllers/quality/adminAudits.php:186 (delete); src/model/manager.php:413 (deleteMeta)
```

## quality.training_task.list

```yaml
id: quality.training_task.list
title: List training task templates (admin datagrid shell)
route: quality/adminTraining/manager
http_method: GET
ui_path: Quality ▸ Settings ▸ Training Tasks tab
auth:
  sec_id: admin   # NB: gated on the admin key, not qa_train
  min_level: 1
preconditions:
  - quality module enabled
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: post
    - name: status
      format: db_field
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders the training-task-template meta-grid (dgTraining)
    - emits fmtFreqs / fmtLeadTime maps
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [quality.training_task.list.rows, quality.training_task.read]
confidence: high
source: src/controllers/quality/adminTraining.php:108 (manager)
```

## quality.training_task.list.rows

```yaml
id: quality.training_task.list.rows
title: Fetch training task-template rows (data only)
route: quality/adminTraining/managerRows
http_method: GET
ui_path: (AJAX backing the training-tasks grid)
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: sort
      format: db_field
      source: post
      notes: default 'title'
    - name: order
      format: db_field
      source: post
    - name: status
      format: db_field
      source: post
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads common_meta rows where meta_key = training
returns:
  success_signal: JSON rows + total count
  identifier: each row includes _rID (meta id)
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [quality.training_task.list, quality.training_task.read]
confidence: high
source: src/controllers/quality/adminTraining.php:122 (managerRows)
```

## quality.training_task.read

```yaml
id: quality.training_task.read
title: Open a training task-template edit form
route: quality/adminTraining/edit
http_method: GET
ui_path: Quality ▸ Settings ▸ Training Tasks ▸ open record
auth:
  sec_id: admin
  min_level: 1
preconditions:
  - the template _rID exists (or 0 for new)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: template meta id
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the training meta blob; relabels train_date as "next training"; shows frequency/lead_time as selects
returns:
  success_signal: editor layout.fields populated
  identifier: template fields
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [quality.training_task.save]
confidence: high
source: src/controllers/quality/adminTraining.php:133 (edit)
```

## quality.training_task.save

```yaml
id: quality.training_task.save
title: Create or update a training task template
route: quality/adminTraining/save
http_method: POST
ui_path: Quality ▸ Settings ▸ Training Tasks ▸ Save
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - on update, _rID refers to an existing training meta row
inputs:
  required:
    - name: title
      format: text
      source: post
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing template meta id; absence inserts, presence updates
    - name: frequency
      format: char
      source: post
    - name: lead_time
      format: alpha_num
      source: post
    - name: store_id
      format: integer
      source: post
    - name: contact_id
      format: integer
      source: post
      notes: trainer
    - name: train_date
      format: date
      source: post
    - name: doc_link
      format: text
      source: post
    - name: notes
      format: text
      source: post
  fixed:
    - name: ref_num
      value: getNextReference(next_training_num)
      notes: assigned only on create
effects:
  db_writes:
    - table: common_meta
      op: insert (create) / update
      notes: meta_key training
  gl_journal: none
  inventory: none
  side_effects:
    - emits msg_record_saved; reloads the training-tasks grid
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: template _rID
errors: []   # NB: the method performs NO validateAccess() check
idempotency: update idempotent; create NOT idempotent
related: [quality.training_task.read, quality.training_task.copy, quality.training_task.delete]
confidence: high
source: src/controllers/quality/adminTraining.php:146 (save); src/model/manager.php:367 (saveMeta)
```

## quality.training_task.copy

```yaml
id: quality.training_task.copy
title: Duplicate a training task template under a new title
route: quality/adminTraining/copy
http_method: POST
ui_path: Quality ▸ Settings ▸ Training Tasks ▸ open record ▸ Copy
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - source _rID exists; a non-blank new title is supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
    - name: data
      format: text
      source: get
      notes: new title for the copy
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert
      notes: deep-copies the training blob with the new title
  gl_journal: none
  inventory: none
  side_effects:
    - reloads the grid and opens the new copy
returns:
  success_signal: grid reload + edit eval action; newID in $_GET['newID']
  identifier: newID (new meta id)
errors:
  - err_inv_title_blank if rID or title empty
  # NB: no validateAccess() check
idempotency: NOT idempotent
related: [quality.training_task.save]
confidence: high
source: src/controllers/quality/adminTraining.php:142 (copy); src/model/manager.php:324 (copyMeta)
```

## quality.training_task.delete

```yaml
id: quality.training_task.delete
title: Delete a training task template
route: quality/adminTraining/delete
http_method: GET
ui_path: Quality ▸ Settings ▸ Training Tasks ▸ open record ▸ Trash
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - rID refers to an existing training meta row
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: removes the training row (_rID)
  gl_journal: none
  inventory: none
  side_effects:
    - deletes the template's attachment zips; reloads the grid
returns:
  success_signal: delete eval action returned
  identifier: none
errors:
  - illegal_access if rID missing
  # NB: no validateAccess() check
idempotency: idempotent
related: [quality.training_task.save]
confidence: high
source: src/controllers/quality/adminTraining.php:150 (delete); src/model/manager.php:413 (deleteMeta)
```

## quality.admin.home

```yaml
id: quality.admin.home
title: Render the Quality module admin/settings page
route: quality/admin/adminHome
http_method: GET
ui_path: Quality ▸ Settings
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
    - renders settings tabs plus iframes embedding the Training Tasks and Audit Tasks grids
returns:
  success_signal: settings layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [quality.admin.save, quality.audit_task.list, quality.training_task.list]
confidence: high
source: src/controllers/quality/admin.php:250 (adminHome)
```

## quality.admin.save

```yaml
id: quality.admin.save
title: Save Quality module settings (QA manual + process/standard/instruction doc links)
route: quality/admin/adminSave
http_method: POST
ui_path: Quality ▸ Settings ▸ Save
auth:
  sec_id: admin
  min_level: 3
preconditions: []
inputs:
  required: []
  optional:
    - name: manual_title
      format: text
      source: post
    - name: manual_link
      format: text
      source: post
      notes: URL of the quality manual; when set, adds a Quality Manual menu item
    - name: proc_/stnd_/inst_<context>
      format: text
      source: post
      notes: process/standard/instruction doc links per context (sales, inventory, receiving, build, qa_ticket, shipping)
  fixed: []
effects:
  db_writes:
    - table: configuration
      op: update
      notes: module settings via readModuleSettings (configuration_key bizuno_quality)
  gl_journal: none
  inventory: none
  side_effects:
    - rebuilds the module settings cache
returns:
  success_signal: settings persisted (no explicit message)
  identifier: none
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent (overwrites the settings blob)
related: [quality.admin.home, quality.admin.renderQA]
confidence: high
source: src/controllers/quality/admin.php:262 (adminSave)
```

## quality.admin.renderQA

```yaml
id: quality.admin.renderQA
title: Render a configured QA document (manual / process / standard / instruction) in an iframe
route: quality/admin/renderQA
http_method: GET
ui_path: Quality ▸ (any manager) ▸ QA Process / Standard / Instruction button; or the Quality Manual menu item
auth:
  sec_id: (none enforced)
  min_level: (none — see Open questions)
preconditions:
  - the requested doc key has a link configured in module settings
inputs:
  required:
    - name: qaIdx
      format: cmd
      source: get
      notes: qa_manual | proc_/stnd_/inst_<context> — which configured doc link to embed
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - returns a divHTML payload embedding the configured link in an iframe
returns:
  success_signal: iframe HTML returned
  identifier: none
errors:
  - caution message "document cannot be found" if qaIdx is not a known settings key
  # NB: no validateAccess() check — any authenticated user can render any configured QA-doc link
idempotency: safe (read-only)
related: [quality.admin.save]
confidence: high
source: src/controllers/quality/admin.php:118 (renderQA)
```

## quality.admin.roles.save

```yaml
id: quality.admin.roles.save
title: Save the Quality additions to a security Role (QA group + assigned training)
route: administrate/roles/save   # runs via the quality hook on the administrate Roles save, not a direct quality route
http_method: POST
ui_path: Administration ▸ Settings ▸ Roles ▸ edit role ▸ Quality tab ▸ Save
auth:
  sec_id: admin
  min_level: 3
preconditions:
  - the role _rID exists
inputs:
  required:
    - name: _rID
      format: integer
      source: post
      notes: bizuno_role meta id being edited
  optional:
    - name: training
      format: array
      source: post
      notes: training[] — list of training task-template ids assigned to the role
    - name: group_qa
      format: boolean
      source: post
      notes: grants the qa group to the role
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: meta_key bizuno_role — merges training[] and groups.qa into the role blob
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: role blob updated (no explicit message)
  identifier: none
errors:
  - silent no-op if _rID missing
  - permission denied if user lacks admin level 3
idempotency: idempotent (overwrites the role's quality fields)
related: [quality.admin.home]
confidence: medium   # invoked through the administrate Roles save hook (rolesSave), not as a top-level quality route — fires only when administrate/roles/save runs
source: src/controllers/quality/admin.php:234 (rolesSave); :209 (rolesEdit); hook registered at :54
```

---

## Common agent recipes

```yaml
recipe_open_capa_ticket:
  goal: Open a corrective-action ticket against a vendor SKU
  steps:
    - action: quality.ticket.save
      with: {title, status, preventable: 0, contact_id: <vendorRID>, sku_id: <skuRID>, notes}
      capture: rID                # invoice_num auto-assigned from next_ticket_num
  note: contact_id/sku_id are reference pointers only — no AP/AR or stock movement

recipe_run_scheduled_audit:
  goal: Record an audit from a reusable audit task template
  steps:
    - action: quality.audit_task.list.rows   # find the template _rID
      capture: templateID
    - action: quality.audit.save
      with: {title, ref_id: $templateID, due_date, status, contact_id: <auditorUserID>, notes}
      capture: rID
  note: quality.audit.add is just the UI selector step; you may post quality.audit.save directly with ref_id

recipe_log_training:
  goal: Record completed training from a training task template
  steps:
    - action: quality.training_task.list.rows
      capture: templateID
    - action: quality.training.save
      with: {title, ref_id: $templateID, contact_id: <trainerUserID>, train_date, notes}

recipe_track_objective:
  goal: Create a quality objective with required-action steps, then close it
  steps:
    - action: quality.objective.save
      with: {title, status, date_target, obj_desc, obj_test, dgObjData: <JSON action rows>}
      capture: _rID
    - action: quality.objective.save
      with: {_rID: $_rID, closed: 1, closed_by: <userID>, obj_result, date_actual}
```

## Open questions / verify-before-automating

- **Ungated task-template writes.** `qualityAdminAudits::save/copy/delete`
  (`adminAudits.php:182/178/186`) and `qualityAdminTraining::save/copy/delete`
  (`adminTraining.php:146/142/150`) call the `mgrJournal` meta helpers
  (`saveMeta`/`copyMeta`/`deleteMeta`) **without any `validateAccess()` check
  first** — and those helpers do not validate either. The result is that
  **any authenticated user can create, copy, or delete audit and training task
  templates** regardless of role. The read routes on these pages *are* gated
  (audit tasks at `qa_audit` 1, training tasks at `admin` 1), but the writes
  are not. Treat these as effectively open writes until a guard is added.
- **Ungated QA-doc render.** `qualityAdmin::renderQA` (`admin.php:118`) has no
  `validateAccess()` check, so any authenticated user can render any configured
  process/standard/instruction/manual link in an iframe. Low risk (read-only,
  links are admin-configured) but worth flagging for an automating agent that
  assumes module routes are role-gated.
- **`quality.ticket.export`** (`tickets.php:281`) queries a
  `BIZUNO_DB_PREFIX.'extISO9001'` table that is **not defined in the core
  `install/tables.php`**. The export will error on a stock install; it
  presumably depends on a client extension or a legacy table. `confidence:
  medium` — verify the table exists before relying on this route.
- **`quality.admin.roles.save`** is reached through the `administrate` Roles
  save **hook** (registered at `admin.php:54`), not as a standalone
  `quality/...` route. It fires only when `administrate/roles/save` runs and
  the role form carries the quality fields. `confidence: medium`.
- **`edit` guard asymmetry on tickets.** `qualityTickets::edit` validates at
  level 2, every other `edit`/`read` in the module at level 1. An agent that
  only holds view rights on `qa_ticket` can read rows via `managerRows` but
  cannot open `quality.ticket.read` — plan permissions accordingly.
- **No referential delete guard.** Unlike contacts, the journal-backed delete
  routes (`tickets`/`audits`/`training`) issue a raw `DELETE` on the
  `journal_main` header with no check for references. There is nothing to block
  the delete, but also nothing posted to reverse — these rows carry no GL/stock
  impact.