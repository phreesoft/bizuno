---
title: PhreeForm — Agent Action Catalog
module: phreeform
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# PhreeForm — Agent Action Catalog

Machine-readable actions for the `phreeform` module — Bizuno's report and form
engine. PhreeForm is the only module whose primary records are **not** a
dedicated table: report and form definitions are stored as JSON blobs in
`common_meta`, keyed by `meta_key = 'phreeform_<id>'`. The render side turns one
of those definitions plus a set of filters into a PDF/HTML/CSV file download (or,
with email delivery, an outbound email). Read the
[catalog schema and conventions](./README.md) first; this file assumes the
route, auth-level, and field conventions defined there.

Pages in this module: `main` (the report tree / manager / favorites / search /
rename / copy / delete), `design` (the report & form designer + its AJAX field
and table helpers), `render` (open dialog and the actual output generator),
`io` (import/export of report definitions), `admin` (module settings).

## How a definition is stored — `common_meta`, not a reports table

```yaml
table: common_meta            # the generic key/value store — NOT a dedicated reports table
columns:                      # only three physical columns exist
  id:         INT(11) AUTO_INCREMENT  # the rID used in every phreeform route
  meta_key:   VARCHAR(64)             # 'phreeform_<id>' for a definition; 'phreeform_cache' for the tree cache
  meta_value: TEXT                    # the JSON blob: {title, type, mime_type, tables, fieldlist, grouplist, sortlist, filterlist, users, roles, page/heading prefs, ...}
key_surrogate: id             # integer rID; routes carry rID (get) or _rID (post on save)
definition_shape:             # the decoded meta_value of a report/form
  title:       human report name
  type:        rpt (columnar report) | lst (list) | frm (form) | ltr (letter)
  mime_type:   dir (a folder/group) | the document mime; 'dir' rows are tree groups, not renderable
  group_id:    "module:group" folder placement, e.g. misc:rpt
  tables:      {rows:[{join_type, tablename, relationship}, ...]} — the SQL FROM/JOIN plan
  fieldlist:   {rows:[{fieldname, title, width, processing, formatting, total, align, settings}, ...]}
  grouplist / sortlist / filterlist: grouping, ORDER BY, and WHERE criteria
  users / roles: [-1] = everyone; otherwise the user/role IDs allowed to see/run it (validateUsersRoles)
  datelist / datedefault / datefield: the run-time date-range prompt
access_helper: validateUsersRoles($report)   # row-level visibility filter layered on top of validateAccess
sec_id: phreeform             # the single module security key checked by every gated route
gl_impact: none               # NOTHING in this module posts to the GL or moves inventory
inventory_impact: none
```

> **Key safety fact for an acting agent:** no action in this module posts a
> general-ledger journal or moves inventory — not even `render`. PhreeForm only
> **reads** business data and emits documents. The one externally observable
> side effect beyond a file download is `render/render` with `delivery=S`, which
> **sends an email** (non-idempotent). The only DB writes are to `common_meta`
> (definition insert/update/delete, rename, copy, import) and a transient design
> cache. So the module is financially safe to automate; treat email delivery and
> live-schema disclosure (see Open questions) as the real risks.

> **Permission caveat:** several render and design AJAX helpers call **no**
> `validateAccess` at all (see each action's `auth` block and the Open questions
> section). An agent must not assume PhreeForm uniformly gates by the `phreeform`
> key — the gated routes are `main/*`, `design/edit`, `design/save`,
> `io/importReport`, and `io/export`; the rest are effectively open to any
> authenticated session.

---

## phreeform.manager

```yaml
id: phreeform.manager
title: Open the PhreeForm manager (report tree + favorites + recent)
route: phreeform/main/manager
http_method: GET
ui_path: Tools ▸ PhreeForm (Reports)
auth:
  sec_id: phreeform
  min_level: 1
preconditions:
  - phreeform module enabled
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: if set, the tree auto-expands to and selects this report on load
    - name: gID
      format: text
      source: get
      notes: group id hint (folder); orientation only
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders the manager page (tree, search box, favorites panel, recent panel)
    - New Report / New Form / Import toolbar buttons are hidden unless security > 1
returns:
  success_signal: manager layout returned
  identifier: none
errors:
  - permission denied if user lacks level 1 on phreeform
idempotency: safe (read-only)
related: [phreeform.tree, phreeform.read, phreeform.favorites, phreeform.recent]
confidence: high
source: src/controllers/phreeform/main.php:76 (manager)
```

## phreeform.tree

```yaml
id: phreeform.tree
title: Fetch the report/form tree (folders + leaf documents)
route: phreeform/main/managerTree
http_method: GET
ui_path: (AJAX backing the manager tree)
auth:
  sec_id: phreeform
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: id
      format: integer
      source: post
      notes: subtree root rID; 0/absent returns the whole tree under Home
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the 'phreeform_cache' meta (getMetaCommon) and filters rows through validateUsersRoles
returns:
  success_signal: raw JSON tree array; each leaf node id is a report rID
  identifier: node id = report rID; node attributes carry mime_type (dir = folder)
errors:
  - permission denied if user lacks level 1
idempotency: safe (read-only)
related: [phreeform.manager, phreeform.read]
confidence: high
source: src/controllers/phreeform/main.php:129 (managerTree)
```

## phreeform.read

```yaml
id: phreeform.read
title: Read a report/form definition's detail panel
route: phreeform/main/edit
http_method: GET
ui_path: PhreeForm ▸ click a document in the tree ▸ Details panel
auth:
  sec_id: phreeform
  min_level: 1
preconditions:
  - rID refers to an existing common_meta phreeform definition (rID=0 = "new", returns blank detail)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: report/form rID; loads its meta via dbMetaGet(rID,'phreeform')
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - builds the detail toolbar (Open, Edit, Rename, Export); Edit/Rename/Export hidden by security level
returns:
  success_signal: detail panel layout populated from the definition meta
  identifier: rID (echoed in toolbar action URLs)
errors:
  - permission denied if user lacks level 1
idempotency: safe (read-only)
related: [phreeform.tree, phreeform.render.open, phreeform.design.edit]
confidence: high
source: src/controllers/phreeform/main.php:153 (edit)
```

## phreeform.favorites

```yaml
id: phreeform.favorites
title: List the current user's bookmarked (favorite) reports
route: phreeform/main/favorites
http_method: GET
ui_path: PhreeForm ▸ My Favorites panel
auth:
  sec_id: phreeform
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
    - reads the user's bookmarks_phreeform contact-meta, then the matching common_meta rows; filters by validateUsersRoles
returns:
  success_signal: HTML list of favorite documents (each links to phreeform/main/edit&rID=…)
  identifier: each item carries its report rID
errors:
  - permission denied if user lacks level 1
idempotency: safe (read-only)
related: [phreeform.manager, phreeform.read]
confidence: high
source: src/controllers/phreeform/main.php:229 (favorites)
```

## phreeform.recent

```yaml
id: phreeform.recent
title: List the most recently updated reports/forms (up to 20)
route: phreeform/main/recent
http_method: GET
ui_path: PhreeForm ▸ Recent Reports panel
auth:
  sec_id: phreeform
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
    - reads all phreeform meta, sorts by last_update desc, skips 'dir' folders, filters by validateUsersRoles, caps at 20
returns:
  success_signal: HTML list of recent documents (each links to phreeform/main/edit&rID=…)
  identifier: each item carries its report rID
errors:
  - permission denied if user lacks level 1
idempotency: safe (read-only)
related: [phreeform.manager, phreeform.read]
confidence: high
source: src/controllers/phreeform/main.php:255 (recent)
```

## phreeform.search

```yaml
id: phreeform.search
title: Search report/form titles (typeahead for the manager search box)
route: phreeform/main/search
http_method: GET
ui_path: PhreeForm ▸ Search box
auth:
  sec_id: phreeform
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: get
      notes: query term; method also accepts 'q' (getSearch(['search','q']))
    - name: q
      format: text
      source: get
      notes: alternate query parameter name
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - scans all phreeform meta; matches on title (strpos) AND passes validateUsersRoles
returns:
  success_signal: JSON [{id: rID, text: title}, ...] (or a single no_results row)
  identifier: id = report rID
errors:
  - permission denied if user lacks level 1
idempotency: safe (read-only)
related: [phreeform.read, phreeform.render.open]
confidence: high
source: src/controllers/phreeform/main.php:283 (search)
```

## phreeform.rename

```yaml
id: phreeform.rename
title: Rename a report/form definition
route: phreeform/main/rename
http_method: GET
ui_path: PhreeForm ▸ Details ▸ Rename (prompt)
auth:
  sec_id: phreeform
  min_level: 3
preconditions:
  - rID exists; a non-empty new title is supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: report/form rID to rename
    - name: data
      format: text
      source: get
      notes: the new title; passed as the prompt value
  optional: []
  fixed:
    - name: last_update
      value: today
      notes: stamped on the definition meta on rename
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: rewrites the definition's title (and last_update) in meta_value via dbMetaSet
  gl_journal: none
  inventory: none
  side_effects:
    - msgLog audit entry "Reports - Rename <title>"; reloads tree/favorites/recent and the detail panel
returns:
  success_signal: eval action reloads the tree and detail panel
  identifier: rID (unchanged)
errors:
  - err_rename_fail: rID or new title missing (no change)
  - permission denied if user lacks level 3
idempotency: idempotent — re-applying the same title yields the same row
related: [phreeform.read, phreeform.copy]
confidence: high
source: src/controllers/phreeform/main.php:179 (rename)
```

## phreeform.copy

```yaml
id: phreeform.copy
title: Duplicate a report/form into a new definition
route: phreeform/main/copy
http_method: GET
ui_path: PhreeForm ▸ Details ▸ Copy
auth:
  sec_id: phreeform
  min_level: 2
preconditions:
  - source rID exists; a non-empty title for the copy is supplied
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: source report/form rID to copy
    - name: data
      format: text
      source: get
      notes: title for the new copy
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert
      notes: parent::copyMeta() clones the definition into a new meta row (new rID returned as newID)
  gl_journal: none
  inventory: none
  side_effects:
    - reloads tree/favorites/recent and opens the detail panel on the new rID (newID)
returns:
  success_signal: eval action selects the new copy; newID is the new rID
  identifier: newID (new report rID)
errors:
  - err_copy_fail: rID or title missing (no change)
  - permission denied if user lacks level 2
idempotency: NOT idempotent — each call creates a new definition
related: [phreeform.rename, phreeform.design.edit]
confidence: medium   # copy body delegates to mgrJournal::copyMeta; exact cloned-field set not re-read here
source: src/controllers/phreeform/main.php:200 (copy)
```

## phreeform.delete

```yaml
id: phreeform.delete
title: Delete a report/form definition
route: phreeform/main/delete
http_method: GET
ui_path: PhreeForm ▸ designer ▸ Trash
auth:
  sec_id: phreeform
  min_level: 4
preconditions:
  - rID refers to an existing phreeform definition
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: report/form rID to delete (consumed by mgrJournal::deleteMeta)
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: removes the definition meta row (parent::deleteMeta)
  gl_journal: none
  inventory: none
  side_effects:
    - rebuilds the phreeform reports cache (dbReportsCache); collapses/reloads the tree and panels
returns:
  success_signal: eval action reloads the manager tree and favorites
  identifier: none
errors:
  - permission denied if user lacks level 4
idempotency: idempotent (deleting an already-gone definition is a no-op)
related: [phreeform.copy, phreeform.io.export]
confidence: high
source: src/controllers/phreeform/main.php:216 (delete)
```

## phreeform.design.edit

```yaml
id: phreeform.design.edit
title: Open the report/form designer (full-page editor)
route: phreeform/design/edit
http_method: GET
ui_path: PhreeForm ▸ New Report / New Form, or Details ▸ Edit
auth:
  sec_id: phreeform
  min_level: 2   # when no rID (new); 3 when rID is present (editing an existing definition)
preconditions:
  - to edit: rID exists; to create: type supplied (rpt|frm|lst)
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: existing definition to edit; absent means create-new (level 2)
    - name: type
      format: cmd
      source: get
      notes: rpt (report) | frm (form) | lst (list); ignored if rID set (type read from the existing meta)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - seeds the design-time table cache (setModuleCache('phreeform','designCache','tables',...)) from the definition's tables, so getFields can enumerate columns
    - loads the full designer (tables, fields, sort, groups, filters datagrids; page/heading prefs)
returns:
  success_signal: designer page layout returned
  identifier: rID (or 0 for new)
errors:
  - permission denied if user lacks level 2 (new) / 3 (edit)
idempotency: safe (read-only render; only writes the transient designCache)
related: [phreeform.design.save, phreeform.design.tables, phreeform.design.fields]
confidence: high
source: src/controllers/phreeform/design.php:172 (edit)
```

## phreeform.design.save

```yaml
id: phreeform.design.save
title: Save a report/form definition (create or update)
route: phreeform/design/save
http_method: POST
ui_path: PhreeForm ▸ designer ▸ Save (or Preview)
auth:
  sec_id: phreeform
  min_level: 2   # save checks level 2 when _rID is empty (create), level 3 when _rID is present (update)
preconditions:
  - the table/field/filter datagrids have been serialized into the hidden JSON inputs by preSubmit()
inputs:
  required:
    - name: title
      format: text
      source: post
      schema_field: common_meta.meta_value (title)
      notes: report/form title, max 64 chars
    - name: type
      format: db_field
      source: post
      notes: rpt | frm | lst — document type
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing rID; empty = insert (level 2), present = update (level 3)
    - name: tables
      format: json
      source: post
      notes: '{rows:[{join_type, tablename, relationship}]}' — the FROM/JOIN plan
    - name: fieldlist
      format: json
      source: post
      notes: column list; per-field settings are re-decoded server-side (preProcessFields)
    - name: grouplist
      format: json
      source: post
    - name: sortlist
      format: json
      source: post
    - name: filterlist
      format: json
      source: post
    - name: group_id
      format: cmd
      source: post
      notes: "module:group" folder placement
    - name: description
      format: text
      source: post
    - name: users
      format: array
      source: post
      notes: allowed user IDs; [-1] (or empty) = everyone
    - name: roles
      format: array
      source: post
      notes: allowed role IDs; [-1] = all roles
    - name: dateperiod / datelist / datefield / datedefault
      format: char/array/db_field/char
      source: post
      notes: run-time date-range prompt config
    - name: pagesize / pageorient / margintop / marginbottom / marginleft / marginright
      format: cmd/char/integer
      source: post
      notes: page geometry (reports/lists)
    - name: special_class
      format: db_field
      source: post
      notes: optional PHP class hook invoked at render time
    - name: xChild
      format: text
      source: post
      notes: if 'print', after save the render dialog (render/open) is spawned for a preview
  fixed:
    - name: last_update
      value: today
      notes: stamped on every save (preProcessFields)
    - name: create_date
      value: today
      notes: filled if previously empty (preProcessFields)
effects:
  db_writes:
    - table: common_meta
      op: insert (no _rID) / update (with _rID)
      notes: writes the whole definition JSON blob via mgrJournal::saveMeta
  gl_journal: none
  inventory: none
  side_effects:
    - on insert, the new rID comes back as id and is stamped into the form
    - with xChild='print', spawns the render/open preview window
returns:
  success_signal: eval action sets #id to the rID; msg_record_saved
  identifier: rID (new id for inserts)
errors:
  - permission denied if user lacks level 2 (create) / 3 (update)
idempotency: >
  update (with _rID) is idempotent — re-posting the same blob yields the same row.
  insert (no _rID) is NOT idempotent; supply _rID to upsert in place.
related: [phreeform.design.edit, phreeform.render.open, phreeform.io.export]
confidence: high
source: src/controllers/phreeform/design.php:350 (save), :369 (preProcessFields)
```

## phreeform.design.tables

```yaml
id: phreeform.design.tables
title: List available database tables for the designer (table picker)
route: phreeform/design/getTables
http_method: GET
ui_path: PhreeForm ▸ designer ▸ Database tab ▸ table name combobox
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
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
    - runs SHOW TABLES LIKE '<prefix>%' against the live DB and returns every table name
returns:
  success_signal: raw JSON [{id: table, text: label}, ...]
  identifier: none
errors: []
idempotency: safe (read-only)
related: [phreeform.design.tablesSession, phreeform.design.fields]
confidence: high
source: src/controllers/phreeform/design.php:414 (getTables)
```

## phreeform.design.tablesSession

```yaml
id: phreeform.design.tablesSession
title: Record the designer's current table set (drives the field picker)
route: phreeform/design/getTablesSession
http_method: GET
ui_path: (AJAX fired when tables are added/removed in the designer)
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
preconditions: []
inputs:
  required:
    - name: data
      format: text
      source: get
      notes: colon-delimited list of table names currently in the design
  optional: []
  fixed: []
effects:
  db_writes:
    - table: configuration (module cache)
      op: update
      notes: setModuleCache('phreeform','designCache','tables',[...]) — transient design-time cache, not a definition
  gl_journal: none
  inventory: none
  side_effects:
    - subsequent getFields uses this cached table list to enumerate columns
returns:
  success_signal: none (no layout returned; cache write only)
  identifier: none
errors: []
idempotency: idempotent — overwrites the cached table list each call
related: [phreeform.design.tables, phreeform.design.fields]
confidence: medium   # writes a shared module cache from an ungated route; effect is transient but observable across the session
source: src/controllers/phreeform/design.php:430 (getTablesSession)
```

## phreeform.design.tablesJoin

```yaml
id: phreeform.design.tablesJoin
title: List the SQL JOIN types for the designer join combobox
route: phreeform/design/getTablesJoin
http_method: GET
ui_path: PhreeForm ▸ designer ▸ Database tab ▸ join type combobox
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
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
  success_signal: static raw JSON list of JOIN keywords (JOIN, LEFT JOIN, INNER JOIN, …)
  identifier: none
errors: []
idempotency: safe (read-only, static)
related: [phreeform.design.tables]
confidence: high
source: src/controllers/phreeform/design.php:446 (getTablesJoin)
```

## phreeform.design.fields

```yaml
id: phreeform.design.fields
title: List columns of the designer's current tables (field picker)
route: phreeform/design/getFields
http_method: GET
ui_path: PhreeForm ▸ designer ▸ Fields tab ▸ fieldname combobox
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
preconditions:
  - designCache tables were set (typically via design.edit or design.tablesSession)
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the cached table list and loads each table's structure (dbLoadStructure), returning every <table>.<column>
returns:
  success_signal: raw JSON [{id: 'table.field', text: 'Table.Label'}, ...]
  identifier: none
errors: []
idempotency: safe (read-only)
related: [phreeform.design.tablesSession, phreeform.design.fieldSettings]
confidence: high
source: src/controllers/phreeform/design.php:535 (getFields)
```

## phreeform.design.fieldSettings

```yaml
id: phreeform.design.fieldSettings
title: Build the per-field settings popup (form/letter field properties)
route: phreeform/design/getFieldSettings
http_method: GET
ui_path: PhreeForm ▸ form designer ▸ Fields tab ▸ field ▸ Settings
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
preconditions:
  - data carries a JSON field object that includes a 'type'
inputs:
  required:
    - name: data
      format: jsonObj
      source: get
      notes: the field row JSON (must include .type and .settings)
  optional:
    - name: rID
      format: integer
      source: get
      notes: the row index of the field being edited (carried back as the save index)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - returns a popup layout pre-filled from the field's settings; no persistence (saved later via design.save)
returns:
  success_signal: settings popup layout
  identifier: none
errors:
  - "No type received" — data lacked a .type
idempotency: safe (read-only render)
related: [phreeform.design.fields, phreeform.design.save]
confidence: high
source: src/controllers/phreeform/design.php:555 (getFieldSettings)
```

## phreeform.render.open

```yaml
id: phreeform.render.open
title: Open the run/print dialog for a report, form, or group
route: phreeform/render/open
http_method: GET
ui_path: PhreeForm ▸ Details ▸ Open (popup window)
auth:
  sec_id: (none)   # UNGATED — no validateAccess call (note: validateUsersRoles is NOT applied here either)
  min_level: 0
preconditions:
  - a report rID OR a group id is supplied
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: report/form rID to run; loads its definition from meta
    - name: group
      format: cmd
      source: get
      notes: group id — opens a group chooser; a single-report group auto-selects that report
    - name: xfld
      format: text
      source: get
      notes: external filter fieldname (preset a WHERE field)
    - name: xcr
      format: text
      source: get
      notes: external filter criteria/operator
    - name: xmin
      format: text
      source: get
      notes: external filter min/value
    - name: xmax
      format: text
      source: get
      notes: external filter max
    - name: date
      format: text
      source: get
      notes: preset date-range selection
    - name: mID
      format: integer
      source: get
      notes: source record id passed through to render
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - builds the dialog: output buttons (PDF/HTML/CSV) and the email From/To/CC/Subject/Body fields
    - the dialog form posts to phreeform/render/render
returns:
  success_signal: render dialog page returned
  identifier: rID (carried into the dialog form)
errors:
  - "called without a group or report ID" (msgAdd)
  - "cannot find the report referenced by id" if the rID has no meta
idempotency: safe (read-only render)
related: [phreeform.render.render, phreeform.render.body, phreeform.read]
confidence: high
source: src/controllers/phreeform/render.php:66 (open)
```

## phreeform.render.render

```yaml
id: phreeform.render.render
title: Generate and deliver a report/form (PDF/HTML/CSV download, or email)
route: phreeform/render/render
http_method: POST
ui_path: PhreeForm ▸ Open dialog ▸ PDF / HTML / CSV (or Email)
auth:
  sec_id: (none)   # UNGATED — no validateAccess call; runs queries and emits the document for any authenticated session
  min_level: 0
preconditions:
  - at least one renderable form/report id is supplied (attachments alone are rejected)
  - the definition's tables/fields resolve to a runnable query
inputs:
  required:
    - name: fmt
      format: text
      source: post
      notes: pdf | html | csv. Reports support all three; forms render pdf.
  optional:
    - name: rID
      format: integer
      source: request
      notes: single report/form rID (get or post)
    - name: rIDs
      format: array
      source: post
      notes: multiple form ids to batch (and 'pdf' attachment markers, which are stitched in)
    - name: delivery
      format: char
      source: post
      notes: I = inline (default), D = download attachment, S = send via email
    - name: xfld / xcr / xmin / xmax
      format: text
      source: post/get
      notes: external filter overrides (fieldname, operator, min, max)
    - name: (filter/date fields)
      format: varies
      source: post
      notes: run-time filter and date-range selections gathered from the open dialog
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - runs the definition's SQL against live business data (read-only)
    - delivery=I/D streams the file and exit()s (Content-Disposition attachment when D or csv)
    - delivery=S sends the rendered PDF as an email (renderEmail) — OUTBOUND SIDE EFFECT
    - a special_class on the definition can run arbitrary module PHP at render time (loadSpecialClass)
    - forms can stamp a "printed" flag / append a contact log per the definition's printedfield/contactlog settings
returns:
  success_signal: file download (PDF/CSV) or rendered HTML page; for delivery=S, the email is sent and a status layout returns
  identifier: filename of the generated document
errors:
  - "At least one form must be selected" (only attachments supplied)
  - "This form had no data" / empty report data → returns without a file
idempotency: >
  read+download (I/D) is repeatable and safe. delivery=S is NOT idempotent — each
  call sends another email. Forms with a printedfield/contactlog may mutate the
  source record's printed flag / add a log entry on each run — treat those as
  non-idempotent too.
related: [phreeform.render.open, phreeform.render.body]
confidence: high
source: src/controllers/phreeform/render.php:363 (render)
```

## phreeform.render.body

```yaml
id: phreeform.render.body
title: Fetch run-time email header/body substitutions for a report
route: phreeform/render/phreeformBody
http_method: GET
ui_path: (AJAX behind the Open dialog's email fields)
auth:
  sec_id: (none)   # UNGATED — no validateAccess call
  min_level: 0
preconditions:
  - rID refers to an existing definition
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: report/form rID; loads its meta to compute email subject/body
  optional:
    - name: xfld / xcr / xmin / xmax
      format: text
      source: get
      notes: external filter context used to resolve recipient/subject substitutions
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - computes default From/To/CC, subject, and body (with %tokens% substituted) for the email form
returns:
  success_signal: content object with msgSubject/msgBody/recipients
  identifier: none
errors:
  - "silent return if rID missing or report not found"
idempotency: safe (read-only)
related: [phreeform.render.open, phreeform.render.render]
confidence: high
source: src/controllers/phreeform/render.php:1126 (phreeformBody)
```

## phreeform.io.manager

```yaml
id: phreeform.io.manager
title: Open the report import page
route: phreeform/io/manager
http_method: GET
ui_path: PhreeForm ▸ Import
auth:
  sec_id: (none)   # UNGATED — manager() has no validateAccess; the import it posts to (io/importReport) IS gated at level 2
  min_level: 0
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
    - scans the bundled report folder (locale/en_US/reports) to list installable default reports
    - renders the import form (upload file or pick from the bundled list) that posts to phreeform/io/importReport
returns:
  success_signal: import page layout returned
  identifier: none
errors: []
idempotency: safe (read-only render)
related: [phreeform.io.import]
confidence: medium   # page itself is ungated; the gate lives on the importReport target, not here
source: src/controllers/phreeform/io.php:45 (manager)
```

## phreeform.io.import

```yaml
id: phreeform.io.import
title: Import report/form definitions (bundled list, uploaded file, or all)
route: phreeform/io/importReport
http_method: POST
ui_path: PhreeForm ▸ Import ▸ Import / Import Selected / Import All
auth:
  sec_id: phreeform
  min_level: 2
preconditions:
  - a bundled report name (imp_name), an uploaded .json file, or imp_name='all' is supplied
inputs:
  required: []
  optional:
    - name: imp_name
      format: filename
      source: post
      notes: bundled report filename to import; 'all' imports every bundled .json in the language folder
    - name: fileUpload
      format: file
      source: post
      notes: an uploaded report/form .json definition
    - name: new_name
      format: text
      source: post
      notes: optional rename for the imported definition
    - name: selLang
      format: text
      source: post
      notes: language folder (default en_US)
    - name: selModule
      format: text
      source: post
      notes: source module path (default 'locale')
    - name: cbReplace
      format: boolean
      source: post
      notes: if set, replace an existing same-named definition instead of adding a duplicate
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert (new) / update (when cbReplace matches an existing definition)
      notes: each .json definition is written via phreeformImport
  gl_journal: none
  inventory: none
  side_effects:
    - msgLog audit entry; success message with the imported title (or "all N total")
returns:
  success_signal: msgStack 'success' = "PhreeForm Manager: Import: <title>"
  identifier: none (query the tree afterward)
errors:
  - permission denied if user lacks level 2
  - "silent return if the named file fails to import"
idempotency: >
  with cbReplace=true, importing the same definition again replaces it in place
  (idempotent on title/name). Without cbReplace it may create duplicates.
related: [phreeform.io.manager, phreeform.io.export, phreeform.design.save]
confidence: medium   # import behavior delegates to phreeformImport (functions.php); match/replace key not re-read here
source: src/controllers/phreeform/io.php:115 (importReport)
```

## phreeform.io.export

```yaml
id: phreeform.io.export
title: Export a report/form definition as JSON
route: phreeform/io/export
http_method: GET
ui_path: PhreeForm ▸ Details ▸ Export
auth:
  sec_id: phreeform
  min_level: 3
preconditions:
  - rID refers to an existing definition
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: report/form rID to export
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the definition meta, strips db indexes and parent_id/ref_num, resets users/roles to [-1] (everyone)
    - streams <title>.json (io->download) — pretty-printed JSON
returns:
  success_signal: file download (<title>.json)
  identifier: none
errors:
  - "report was not exported, the proper id was not passed" (rID missing)
  - permission denied if user lacks level 3
idempotency: safe (read-only)
related: [phreeform.io.import, phreeform.delete]
confidence: high
source: src/controllers/phreeform/io.php:145 (export)
```

## phreeform.admin.home

```yaml
id: phreeform.admin.home
title: Open the PhreeForm module settings page
route: phreeform/admin/adminHome
http_method: GET
ui_path: Settings ▸ Modules ▸ PhreeForm
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
    - renders the settings form (default font, column width, margins, default titles, paper size, orientation, truncate length)
returns:
  success_signal: settings page layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [phreeform.admin.save]
confidence: high
source: src/controllers/phreeform/admin.php:72 (adminHome)
```

## phreeform.admin.save

```yaml
id: phreeform.admin.save
title: Save the PhreeForm module settings
route: phreeform/admin/adminSave
http_method: POST
ui_path: Settings ▸ Modules ▸ PhreeForm ▸ Save
auth:
  sec_id: (none)   # UNGATED in this method — adminSave() has no validateAccess; gating relied on the admin settings screen entry
  min_level: 0
preconditions: []
inputs:
  required: []
  optional:
    - name: default_font
      format: text
      source: post
      notes: default report font
    - name: column_width
      format: integer
      source: post
    - name: margin
      format: integer
      source: post
    - name: title1
      format: text
      source: post
      notes: default header title 1 (e.g. %reportname%)
    - name: title2
      format: text
      source: post
      notes: default header title 2
    - name: paper_size
      format: text
      source: post
    - name: orientation
      format: char
      source: post
      notes: P (portrait) | L (landscape)
    - name: truncate_len
      format: integer
      source: post
  fixed: []
effects:
  db_writes:
    - table: configuration (module settings cache)
      op: update
      notes: readModuleSettings persists the phreeform settings block
  gl_journal: none
  inventory: none
  side_effects:
    - new defaults apply to subsequently designed reports
returns:
  success_signal: settings saved (module cache updated)
  identifier: none
errors:
  - "none enforced in-method — no permission check"
idempotency: idempotent — re-posting the same settings yields the same configuration
related: [phreeform.admin.home]
confidence: medium   # adminSave performs no validateAccess; the route is reachable without an explicit gate in this method
source: src/controllers/phreeform/admin.php:81 (adminSave)
```

---

## Common agent recipes

```yaml
recipe_run_report_pdf:
  goal: Render an existing report to a PDF download
  steps:
    - action: phreeform.search
      with: {search: <report title>}     # find the rID
      capture: rID
    - action: phreeform.render.open
      with: {rID: $rID}                   # (optional) inspect available filters/dates
    - action: phreeform.render.render
      with: {rID: $rID, fmt: pdf, delivery: D}
  note: delivery=D forces a file download; delivery=I renders inline. Neither posts to the GL.

recipe_email_a_form:
  goal: Render a form and email it to a contact
  steps:
    - action: phreeform.render.open
      with: {rID: $formRID, mID: $sourceRecordID}
    - action: phreeform.render.render
      with: {rID: $formRID, fmt: pdf, delivery: S}   # delivery=S SENDS email
  note: delivery=S is NOT idempotent — each run sends another email; do not blindly retry.

recipe_clone_and_tweak:
  goal: Duplicate a report, then edit the copy
  steps:
    - action: phreeform.copy
      with: {rID: $sourceRID, data: <new title>}
      capture: newID
    - action: phreeform.design.edit
      with: {rID: $newID}
    - action: phreeform.design.save
      with: {_rID: $newID, title, type, tables, fieldlist, ...}
  note: save with _rID set is idempotent (update in place); omit _rID only to create a brand-new definition.

recipe_promote_definition_between_sites:
  goal: Move a report definition from one Bizuno to another
  steps:
    - action: phreeform.io.export
      with: {rID: $rID}                   # downloads <title>.json (users/roles reset to everyone)
    - action: phreeform.io.import
      with: {fileUpload: <that .json>, cbReplace: true}   # on the target site
  note: cbReplace=true makes the import idempotent (replace-in-place); without it duplicates can accumulate.
```

## Open questions / verify-before-automating

- **Ungated render path.** `phreeform/render/open` (render.php:66),
  `phreeform/render/render` (render.php:363) and `phreeform/render/phreeformBody`
  (render.php:1126) perform **no** `validateAccess` and `render`/`open` do not
  apply `validateUsersRoles`. Any authenticated session can therefore run any
  report by rID and have its SQL executed and results streamed — including
  reports the user's role would not see in the tree. Confirm whether your
  deployment fronts these with another guard before exposing them to an agent.
- **Live-schema disclosure.** `phreeform/design/getTables` (design.php:414) runs
  `SHOW TABLES` and `phreeform/design/getFields` (design.php:535) loads full
  table structures — both ungated. These leak the database schema to any
  authenticated caller.
- **Shared cache write from an ungated route.** `phreeform/design/getTablesSession`
  (design.php:430) writes `designCache` via `setModuleCache` with no auth check
  (`confidence: medium`). It is transient but observable across the session and
  steers what `getFields` returns.
- **`phreeform/admin/adminSave`** (admin.php:81) performs no `validateAccess`
  in-method, unlike `adminHome` which gates `admin:1` (`confidence: medium`).
  Verify the route cannot be reached directly before trusting the admin gate.
- **`render/render` mutations.** Forms with a `printedfield`/`contactlog`/
  `serialform` setting may stamp a printed flag or append a contact log on each
  run, and a `special_class` can execute arbitrary module PHP. Treat such
  definitions as non-idempotent and review their `special_class` before
  automated rendering (`confidence` on render is high for the file path, but the
  per-definition side effects depend on the definition's own settings).
- **`phreeform.copy` / `phreeform.io.import`** delegate to `mgrJournal::copyMeta`
  and `phreeformImport` respectively; the exact cloned/matched field set was not
  re-read in those helpers here (`confidence: medium`). Verify the match key
  before relying on import being a true upsert.