---
title: Office — Agent Action Catalog
module: office
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Office — Agent Action Catalog

Machine-readable actions for the `office` module. In the current code the
module's only live, standard-routable surface is the **Document Manager**
(`controllers/office/docs.php`, class `officeDocs`, security key `mgr_docs`,
routes `office/docs/<method>`). Read the
[catalog schema and conventions](./README.md) first; this file assumes the
route, auth-level, and field conventions defined there.

The Document Manager stores each document as a metadata record and keeps the
binary content (with revision history) on the filesystem. No action in this
module posts to the general ledger or moves inventory — it is a file/metadata
manager.

## What is and is not routable in this module

The directory `controllers/office/` contains eight PHP files, but most are not
live agent surfaces:

- **`docs.php`** — `officeDocs` (extends `mgrJournal`), namespace `bizuno`,
  security key `mgr_docs`. **The only live, standard-routable page.** Every
  action below is one of its public methods, addressed as
  `office/docs/<method>`.
- **`artist.php`, `author.php`, `calendar.php`, `slides.php`, `tables.php`** —
  empty stubs. Each defines a class (`bizArtistAdmin`, `bizAuthorAdmin`,
  `bizCalendarAdmin`, `bizImpressAdmin`, `bizTablesAdmin`) whose only member is
  an empty `__construct()`. They carry no methods and produce **no actions**.
  Treat them as placeholders for future PhreeSoft commercial modules; an agent
  cannot do anything with them.
- **`files.php` / `storage.php`** — a WordPress-only file manager
  (`bizFiles` / `bizAdmin`) in namespace **`bizoffice`**, not `bizuno`. It is
  addressed as `bizRt=bizStorage/<method>` (its own module slug), **not** as
  `office/files/<method>` or `office/storage/<method>`, so it is *not*
  reachable through the `office/<page>/<method>` dispatch this catalog covers.
  It is summarized in the data-model section and flagged under open questions,
  but is intentionally **not** given full per-action schema blocks here.

## Data model summary

```yaml
storage_model: common_meta            # documents are metadata rows, NOT a dedicated table in 7.x
table: common_meta                    # generic key/value meta store (id, meta_key, meta_value)
meta_key: document                    # officeDocs::$metaPrefix = 'document'; dbMetaGet/Set(..., 'document') → common_meta
key_surrogate: _rID                   # common_meta.id; the integer used as rID in every docs route
meta_value_payload:                   # JSON object stored in common_meta.meta_value per document
  title:        document title (shown in tree/search)
  parent_id:    _rID of the containing folder (0 = root); folders are docs with mime_type='dir'
  mime_type:    file extension (txt, pdf, …), 'dir' for folders, 'ggl' for a linked Google Drive folder
  filename:     original uploaded filename (display only)
  owner:        userID that uploaded the latest revision
  create_date / last_update: dates
  users:        array of userIDs allowed to see the doc (-1 = everyone); see validateUsersRoles
  roles:        array of roleIDs allowed to see the doc
  google_id:    Drive folder id (mime_type='ggl' only)
file_storage:                         # binary content + revisions live on the filesystem, not the DB
  attach_path:  getModuleCache('office','properties','attachPath','docs')   # officeDocs::$attachPath
  naming:       rID_<8-digit-zero-padded-_rID>_<rev>      # one file per revision, rev increments on each upload
  revision:     parsed from the trailing _<rev> of the filename (older revs retained until explicitly deleted)
bookmarks:                            # per-user bookmark list
  table: contacts_meta
  meta_key: bookmarks_docs
  ref_id: <userID>                    # dbMetaGet(0,'bookmarks_docs','contacts', userID) → array of bookmarked _rIDs
legacy_table: extDocs                 # pre-7.0 documents table — DROPPED during the 7.0 migration
                                      # (migrate-7.0.php DROP TABLE IF EXISTS …extDocs). The search() method
                                      # still queries it; on a migrated install that query returns nothing.
sec_id: mgr_docs                      # the single security key for the whole Document Manager
gl_impact: none                       # no action posts to the GL
inventory_impact: none                # no action moves stock
wordpress_file_manager:               # NOT covered by per-action blocks below
  files:   controllers/office/files.php   → class bizFiles  (namespace bizoffice)
  storage: controllers/office/storage.php → class bizAdmin  (namespace bizoffice)
  route:   bizRt=bizStorage/<method>       # own module slug, NOT office/<page>/<method>
  note:    WordPress-only; uses its own folder/share/thumbnail model, separate from officeDocs.
```

> **Key safety fact for an acting agent:** the Document Manager never posts a
> GL journal or moves inventory. Its only material side effects are on the
> **filesystem** — uploading a document writes a new revision file under the
> docs attach path, and the delete actions remove revision files. Plan
> file-system effects, not accounting effects, around these actions.

---

## office.docs.manager

```yaml
id: office.docs.manager
title: Open the Document Manager (tree + search + bookmarks layout)
route: office/docs/manager
http_method: GET
ui_path: Office ▸ Document Manager
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions:
  - office module enabled; docs attachPath configured
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: if supplied, the layout auto-opens that document's edit panel on load
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders the EasyUI accordion/tree shell. The tree, search,
      bookmarks and recent panels are populated by separate AJAX routes below.
returns:
  success_signal: manager layout returned
  identifier: none
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.tree, office.docs.search, office.docs.bookmarks, office.docs.recent]
confidence: high
source: src/controllers/office/docs.php:78 (manager)
```

## office.docs.tree

```yaml
id: office.docs.tree
title: Fetch the document/folder tree (JSON for the EasyUI tree)
route: office/docs/managerTree
http_method: POST
ui_path: (AJAX backing the Document Manager tree)
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: id
      format: integer
      source: post
      notes: parent node _rID to expand a subtree; empty/0 returns the full tree from root
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads all 'document' meta rows, filters them per-user via validateUsersRoles
      (a row is hidden if the user's id/role is not in the doc's users/roles), then
      sorts by title within mime_type
returns:
  success_signal: raw JSON tree array; nodes carry id (_rID), text (title), and attributes (incl. mime_type)
  identifier: each node id is the document _rID
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.manager, office.docs.edit]
confidence: high
source: src/controllers/office/docs.php:199 (managerTree)
```

## office.docs.create

```yaml
id: office.docs.create
title: Create a new document node, folder, or linked Google Drive folder
route: office/docs/docNew
http_method: GET
ui_path: Office ▸ Document Manager ▸ tree right-click ▸ Add Document / Add Folder / Add Google
auth:
  sec_id: mgr_docs
  min_level: 2
preconditions:
  - a parent node selected (pID); pID=0 places the node at the tree root
inputs:
  required:
    - name: type
      format: text
      source: get
      notes: >
        node kind — 'doc' (uploadable document, default 'txt' mime if omitted),
        'dir' (folder), or 'ggl' (linked Google Drive folder). Defaults to 'txt'.
    - name: pID
      format: integer
      source: get
      notes: parent folder _rID (0 = root)
  optional:
    - name: title
      format: text
      source: get
      notes: node label; defaults to lang('new_document')
    - name: gID
      format: text
      source: get
      notes: Google Drive folder id — only used when type='ggl'
  fixed:
    - name: users
      value: "[<creating userID>]"
      notes: the new node is owned by the creating user so it is never orphaned
    - name: roles
      value: "[]"
    - name: create_date / last_update
      value: today
effects:
  db_writes:
    - table: common_meta
      op: insert
      notes: a 'document' meta row holding {title, parent_id, mime_type, dates, users, roles, (google_id)}
  gl_journal: none
  inventory: none
  side_effects:
    - logs the creation (msgLog); returns a JS action that reloads the tree (dir/ggl)
      or opens the edit panel (doc) so a file can then be uploaded via office.docs.save
    - NO file is written yet — a 'doc' node has no content until office.docs.save uploads one
returns:
  success_signal: msgStack 'success'; eval action; new _rID set into $_GET['rID']
  identifier: new document _rID
errors:
  - permission denied if user lacks mgr_docs level 2
idempotency: NOT idempotent — each call inserts a new meta row (no natural-key de-dup)
related: [office.docs.save, office.docs.tree, office.docs.edit]
confidence: high
source: src/controllers/office/docs.php:223 (docNew)
```

## office.docs.search

```yaml
id: office.docs.search
title: Search documents by title
route: office/docs/search
http_method: GET
ui_path: Office ▸ Document Manager ▸ Search box
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions: []
inputs:
  required:
    - name: search
      format: text
      source: get
      notes: title substring; the helper getSearch(['search','q']) also accepts a 'q' parameter
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - queries the LEGACY extDocs table (mime_type<>'dir' AND title LIKE '%…%'), then
      filters hits with validateUsersRoles. extDocs is dropped on 7.x installs, so on a
      migrated database this returns no results regardless of the search term.
returns:
  success_signal: JSON array of {id, text}; [{id:'', text:'no_results'}] when empty
  identifier: each hit id is a document id from extDocs (not the common_meta _rID)
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.tree, office.docs.edit]
confidence: medium   # query targets a table that no longer exists post-7.0 migration — see open questions
source: src/controllers/office/docs.php:248 (search)
```

## office.docs.rename

```yaml
id: office.docs.rename
title: Rename a document/folder node (title only)
route: office/docs/rename
http_method: GET
ui_path: Office ▸ Document Manager ▸ tree right-click ▸ Edit (inline node edit)
auth:
  sec_id: none           # NO validateAccess call — this method is UNGATED (see open questions)
  min_level: n/a
preconditions:
  - rID refers to an existing 'document' meta row
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: document _rID to rename; method no-ops if missing
    - name: title
      format: text
      source: get
      notes: new title; method no-ops if empty
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: rewrites the 'title' field of the document meta payload
  gl_journal: none
  inventory: none
  side_effects:
    - logs old → new title (msgLog)
returns:
  success_signal: no layout returned; success is implicit (msgStack)
  identifier: none
errors:
  - "silent no-op if rID or title missing"
idempotency: idempotent (re-setting the same title is a no-op)
related: [office.docs.edit, office.docs.tree]
confidence: high
source: src/controllers/office/docs.php:268 (rename)
```

## office.docs.edit

```yaml
id: office.docs.edit
title: Open a document's edit/detail panel (metadata + revision history)
route: office/docs/edit
http_method: GET
ui_path: Office ▸ Document Manager ▸ click a document node
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions:
  - rID refers to an existing document meta row (rID=0/empty renders a blank "new" form)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: document _rID
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the document meta and its revision files from the attach path
    - resolves the user's bookmark state for this doc (contacts_meta 'bookmarks_docs')
    - for mime_type='ggl', renders an embedded Google Drive iframe instead of the form
    - adds a 'caution' message if the meta row exists but has no uploaded file yet
returns:
  success_signal: edit form + history datagrid (or Drive iframe) returned
  identifier: the document _rID being edited
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.save, office.docs.history, office.docs.download]
confidence: high
source: src/controllers/office/docs.php:285 (edit)
```

## office.docs.history

```yaml
id: office.docs.history
title: List the revision history of a document (files on disk)
route: office/docs/historyList
http_method: POST
ui_path: Office ▸ Document Manager ▸ document detail ▸ History grid
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions:
  - rID refers to an existing document; the doc has at least one uploaded revision file
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: document _rID; method errors if 0/missing
  optional:
    - name: page
      format: integer
      source: post
      notes: pagination page number
    - name: rows
      format: integer
      source: post
      notes: rows per page
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - globs the attach path for rID_<padded>_* files, derives each revision number
      from the filename, sorts newest-first, paginates
returns:
  success_signal: JSON {total, rows[]}; each row has id (filename), rev, size, date
  identifier: each row id is the on-disk filename (used as the fileID for download/delete-rev)
errors:
  - "Bad record ID! if rID missing"
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.download, office.docs.deleteRev]
confidence: high
source: src/controllers/office/docs.php:329 (historyList)
```

## office.docs.save

```yaml
id: office.docs.save
title: Save document metadata and (optionally) upload a new revision
route: office/docs/save
http_method: POST
ui_path: Office ▸ Document Manager ▸ document detail ▸ Save
auth:
  sec_id: mgr_docs
  min_level: 3   # save() checks level 3 when _rID is set; level 2 only when _rID is empty
preconditions:
  - _rID refers to an existing document meta row (the method aborts with bad_id if empty)
inputs:
  required:
    - name: _rID
      format: integer
      source: post
      notes: document _rID; REQUIRED — empty _rID aborts (the create path is office.docs.create)
  optional:
    - name: title
      format: text
      source: post
    - name: description
      format: text
      source: post
    - name: users
      format: array
      source: post
      notes: userIDs allowed to view; cleanSecurity() defaults to the current user if users+roles both empty (avoids orphaning)
    - name: roles
      format: array
      source: post
      notes: roleIDs allowed to view
    - name: bookmark
      format: char
      source: post
      notes: 1 sets / 0 clears this user's bookmark for the doc (contacts_meta 'bookmarks_docs')
    - name: docfile
      format: file
      source: post
      notes: optional upload; if present, written as the NEXT revision (latest rev + 1)
  fixed:
    - name: last_update
      value: today
    - name: mime_type / filename / owner
      value: derived from the uploaded file
      notes: set only when a docfile is uploaded
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: merges posted fields into the document meta payload
  gl_journal: none
  inventory: none
  side_effects:
    - FILESYSTEM WRITE — if docfile uploaded, writes rID_<padded>_<latest+1> to the attach
      path, preserving prior revisions (revision history accumulates on disk)
    - applies the bookmark set/clear for the acting user
    - logs the save; reloads the tree, history grid, and recent panel
returns:
  success_signal: msgStack 'success' = save: <title>; eval action
  identifier: document _rID (unchanged)
errors:
  - bad_id if _rID empty
  - permission denied if user lacks mgr_docs level 3
idempotency: >
  Metadata save is idempotent. Each docfile upload, however, appends a NEW revision
  file — re-running save with a file is NOT idempotent on the filesystem.
related: [office.docs.create, office.docs.edit, office.docs.history, office.docs.download]
confidence: high
source: src/controllers/office/docs.php:352 (save)
```

## office.docs.download

```yaml
id: office.docs.download
title: Download a document (newest revision, or a specific revision by fileID)
route: office/docs/docExport
http_method: GET
ui_path: Office ▸ Document Manager ▸ detail ▸ Download (or History grid ▸ Download)
auth:
  sec_id: mgr_docs
  min_level: 1
preconditions:
  - the document has at least one revision file on disk
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: document _rID; used to locate the newest revision when fileID is not supplied
    - name: fileID
      format: text
      source: get
      notes: >
        full path/filename of a specific revision (as returned by office.docs.history). Cleaned
        only as 'text' (NOT 'filename') and passed straight to the filesystem read — see open questions.
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the revision file from disk and streams it as a download using the
      original filename stored in the doc meta
    - logs the download (msgLog)
returns:
  success_signal: binary file download (no layout return)
  identifier: none
errors:
  - "returns silently if the doc meta or the file cannot be read"
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.history, office.docs.edit]
confidence: medium   # fileID is taken raw (clean 'text') and used as a path — see open questions
source: src/controllers/office/docs.php:449 (docExport)
```

## office.docs.delete

```yaml
id: office.docs.delete
title: Delete a document and all its revisions
route: office/docs/docDelete
http_method: GET
ui_path: Office ▸ Document Manager ▸ tree right-click ▸ Trash
auth:
  sec_id: mgr_docs
  min_level: 4
preconditions:
  - the node has NO children (a folder with documents/subfolders inside cannot be deleted)
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: document/folder _rID
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: delete
      notes: removes the 'document' meta row
  gl_journal: none
  inventory: none
  side_effects:
    - FILESYSTEM DELETE — removes all rID_<padded>_* revision files for the document
    - logs the deletion; reloads the tree and bookmark/recent panels
returns:
  success_signal: eval action reloading the tree (no rows-affected count)
  identifier: none
errors:
  - bad_id if rID empty
  - msg_delete_error if the node has children (delete refused, no change)
  - permission denied if user lacks mgr_docs level 4
idempotency: idempotent (deleting an already-gone document is effectively a no-op)
related: [office.docs.deleteRev, office.docs.tree]
confidence: high
source: src/controllers/office/docs.php:401 (docDelete)
```

## office.docs.deleteRev

```yaml
id: office.docs.deleteRev
title: Delete a single revision of a document from history
route: office/docs/docDeleteRev
http_method: GET
ui_path: Office ▸ Document Manager ▸ detail ▸ History grid ▸ Trash
auth:
  sec_id: mgr_docs
  min_level: 4
preconditions:
  - the revision file identified by fileID exists on disk
inputs:
  required:
    - name: rID
      format: filename
      source: get
      notes: >
        despite the name, this is the revision FILE ID (filename/path, as returned by
        office.docs.history), NOT the document _rID. Cleaned as 'filename'. The document
        _rID and revision number are parsed out of it.
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - FILESYSTEM DELETE — removes the one revision file; the document meta row and other
      revisions are untouched
    - logs the deletion with the parsed revision number; reloads the history grid
returns:
  success_signal: eval action reloading the history grid
  identifier: none
errors:
  - "'document was not deleted, proper id was not passed' if fileID missing"
  - permission denied if user lacks mgr_docs level 4
idempotency: idempotent (deleting an already-gone revision is a no-op)
related: [office.docs.delete, office.docs.history]
confidence: high
source: src/controllers/office/docs.php:427 (docDeleteRev)
```

## office.docs.bookmarks

```yaml
id: office.docs.bookmarks
title: List the acting user's bookmarked documents
route: office/docs/docsBookmarked
http_method: GET
ui_path: Office ▸ Document Manager ▸ My Bookmarks panel
auth:
  sec_id: mgr_docs
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
    - reads all 'document' meta, filters by validateUsersRoles, skips folders,
      renders an HTML datalist (clicking a row opens that doc's edit panel)
returns:
  success_signal: HTML list of bookmarkable documents
  identifier: each list item value is the document _rID
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.save, office.docs.edit]
confidence: high
source: src/controllers/office/docs.php:478 (docsBookmarked)
```

## office.docs.recent

```yaml
id: office.docs.recent
title: List recently updated documents (most-recent first, capped at 25)
route: office/docs/docsRecent
http_method: GET
ui_path: Office ▸ Document Manager ▸ Recently Added panel
auth:
  sec_id: mgr_docs
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
    - reads all 'document' meta, sorts by last_update desc, skips folders, filters
      by validateUsersRoles, returns up to the first 25 the user may see
returns:
  success_signal: HTML list of recent documents (date + title)
  identifier: each list item value is the document _rID
errors:
  - permission denied if user lacks mgr_docs level 1
idempotency: safe (read-only)
related: [office.docs.edit, office.docs.bookmarks]
confidence: high
source: src/controllers/office/docs.php:501 (docsRecent)
```

---

## Common agent recipes

```yaml
recipe_upload_new_document:
  goal: Add a brand-new document with content to a folder
  steps:
    - action: office.docs.create
      with: {type: doc, pID: <folder _rID or 0>, title: <title>}
      capture: rID            # new document meta _rID
    - action: office.docs.save
      with: {_rID: $rID, title: <title>, description, users, docfile: <upload>}
      note: office.docs.create makes the metadata node; office.docs.save attaches the first revision file

recipe_add_folder_then_document:
  goal: Create a folder, then put a document inside it
  steps:
    - action: office.docs.create
      with: {type: dir, pID: 0, title: <folder name>}
      capture: folderID
    - action: office.docs.create
      with: {type: doc, pID: $folderID, title: <doc title>}
      capture: docID
    - action: office.docs.save
      with: {_rID: $docID, docfile: <upload>}

recipe_revise_a_document:
  goal: Upload a new revision while keeping prior versions
  steps:
    - action: office.docs.history
      with: {rID: <docID>}        # inspect existing revisions
    - action: office.docs.save
      with: {_rID: <docID>, docfile: <new file>}
      note: writes the next revision (latest+1); prior revisions remain on disk

recipe_download_specific_revision:
  goal: Pull a particular historical revision
  steps:
    - action: office.docs.history
      with: {rID: <docID>}
      capture: fileID            # the row 'id' (filename) for the wanted revision
    - action: office.docs.download
      with: {fileID: $fileID}
```

## Open questions / verify-before-automating

- **`office.docs.rename` is ungated.** `rename()` (docs.php:268) has **no**
  `validateAccess` call — unlike every other write method it does not check
  `mgr_docs`. Any authenticated user who can reach the route can change a
  document's title. Do not rely on the security key to gate renames; treat this
  as a known gap (candidate for an AUDIT.md item).
- **`office.docs.download` takes an unsanitized `fileID`.** `docExport()`
  (docs.php:449) reads `fileID` with `clean('fileID','text','get')` — *not*
  the `filename` format — and uses it directly as the path passed to the file
  reader. This is **path-traversal sensitive**: an attacker-supplied `fileID`
  could point outside the docs attach path. Validate/normalize the path before
  wiring this into any automation that accepts external input.
- **`office.docs.search` queries a dropped table.** `search()` (docs.php:255)
  still runs `SELECT … FROM …extDocs`, but `extDocs` is **dropped during the
  7.0 migration** (`migrate-7.0.php` `DROP TABLE IF EXISTS …extDocs`); documents
  now live in `common_meta` (meta_key `document`). On any migrated install the
  search returns nothing. To find documents programmatically, use
  `office.docs.tree` and filter client-side, not `office.docs.search`
  (`confidence: medium`).
- **The WordPress file manager (`files.php` / `storage.php`) is a separate
  surface.** Classes `bizFiles` / `bizAdmin` live in namespace `bizoffice` and
  are addressed as `bizRt=bizStorage/<method>`, **not** `office/<page>/<method>`.
  It has its own folder/share/star/thumbnail/revision model (e.g. `fileUpload`,
  `folderAdd`, `setShareUsers`, `setRevision`, `fileTrash`). It is intentionally
  not cataloged here; if an agent must drive it, document the `bizStorage`
  module separately.
- **The stub pages do nothing.** `artist.php`, `author.php`, `calendar.php`,
  `slides.php`, and `tables.php` are empty placeholder classes. They expose no
  routable methods; ignore them until PhreeSoft ships their implementations.