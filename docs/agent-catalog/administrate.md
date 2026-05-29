---
title: Administration — Agent Action Catalog
module: administrate
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Administration — Agent Action Catalog

Machine-readable actions for the `administrate` module — Bizuno's system
administration surface: roles & permissions, user provisioning, full-database
backup/restore, the audit log, custom-field/tab schema editing, dashboards,
fixed assets, and a recurring-maintenance register. Read the
[catalog schema and conventions](./README.md) first; this file assumes the
route, auth-level, and field conventions defined there.

Pages in this module: `main` (settings landing/redirect), `roles` (permission
roles), `admin` (hooks that extend the contacts editor for users), `backup`
(DB backup/restore + audit-log purge), `tools` (support ticket, cache, FY-close
hooks, table repair), `fields` (custom columns), `tabs` (custom tabs),
`dashboard` (dashboard defaults), `fixedAssets` (asset register + depreciation
schedules), `maint` (maintenance activity log, journal 35), `adminMaint`
(maintenance task templates).

## Safety facts for an acting agent

> **No action in this module posts a general-ledger journal or moves
> inventory.** `gl_journal` and `inventory` are `none` everywhere. This module
> is *administrative* — but several actions are far more destructive than the
> contacts module: a database restore replaces every table, audit-log purges are
> permanent, and custom-field save/delete run live `ALTER TABLE` / `DROP COLUMN`
> DDL. Treat the level-4 actions here as high-impact even though they touch no
> money directly.

## The `admin` security key and the administrator override

Almost every page sets `protected $secID = 'admin'`. `validateAccess('admin',
$level)` has a special branch (`model/functions.php:1618`): **if the acting
user's role has the `administrate` flag set, the check returns level 4
unconditionally** — a full override regardless of the requested level or any
per-key grant. So a user in an "administrator" role passes *every* `admin`-keyed
action below at delete level. For non-administrator roles the granular
per-module security map applies. Two pages (`maint`) use the `mgr_maint`
security key instead of `admin`; those are not subject to the override.

## How permission levels were read off the code

Each action's `min_level` is the integer passed to `validateAccess()` in the
method. Where the level is computed (`empty($rID)?2:3`), the action notes both
branches. Where a method has **no** `validateAccess()` call at all, it is listed
with `min_level: NONE (ungated)` and flagged in
[Open questions](#open-questions--verify-before-automating) — those are documented
as observed, not endorsed.

---

## administrate.settings.home

```yaml
id: administrate.settings.home
title: Open the administration settings landing page
route: administrate/main/manager
http_method: GET
ui_path: Settings (gear)
auth:
  sec_id: admin
  min_level: 4
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
    - builds the settings side-menu dynamically from every module with hasAdmin
    - loads optional client override myExt/controllers/myAdmin/admin.php (myAdminAdmin) if present
returns:
  success_signal: settings layout returned
  identifier: none
errors:
  - permission denied unless the user is an administrator (validateAccess admin,4)
idempotency: safe (read-only)
related: [administrate.roles.list, administrate.backup.home, administrate.dashboard.list]
confidence: high
source: src/controllers/administrate/main.php:47 (manager)
```

## administrate.settings.redir

```yaml
id: administrate.settings.redir
title: Client-side redirect helper (PhreeSoft account / settings home)
route: administrate/main/redir
http_method: GET
ui_path: Settings ▸ Account / Home menu links
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions: []
inputs:
  required:
    - name: url
      format: alpha_num
      source: get
      notes: destination keyword — psAcct (PhreeSoft account) | home (settings manager). Unknown values emit "Unexpected redirect!".
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - returns an HTML fragment with a JS location.href / winHref redirect; no server state change
returns:
  success_signal: divHTML with a jsReady redirect action
  identifier: none
errors:
  - "msgAdd 'Unexpected redirect!' on an unrecognized url keyword"
idempotency: safe (read-only)
related: [administrate.settings.home]
confidence: high
source: src/controllers/administrate/main.php:107 (redir)
```

---
<!-- ===================== ROLES ===================== -->

## administrate.roles.list

```yaml
id: administrate.roles.list
title: List security roles
route: administrate/roles/manager
http_method: GET
ui_path: Settings ▸ Security ▸ Roles
auth:
  sec_id: admin
  min_level: 4
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
    - renders an EasyUI datagrid of role records (common_meta, prefix bizuno_role)
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: safe (read-only)
related: [administrate.roles.list.rows, administrate.roles.read, administrate.roles.save]
confidence: high
source: src/controllers/administrate/roles.php:74 (manager)
```

## administrate.roles.list.rows

```yaml
id: administrate.roles.list.rows
title: Fetch role rows (data only)
route: administrate/roles/managerRows
http_method: GET
ui_path: (AJAX backing the roles grid)
auth:
  sec_id: admin
  min_level: 4
preconditions: []
inputs:
  required: []
  optional:
    - name: sort
      format: cmd
      source: post
      notes: default title
    - name: order
      format: db_field
      source: post
      notes: ASC | DESC, default ASC
    - name: page
      format: integer
      source: post
    - name: rows
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows + total
  identifier: each row has the role meta id (rID)
errors:
  - permission denied unless administrator
idempotency: safe (read-only)
related: [administrate.roles.list]
confidence: high
source: src/controllers/administrate/roles.php:79 (managerRows)
```

## administrate.roles.read

```yaml
id: administrate.roles.read
title: Open a role for editing (renders the per-module security matrix)
route: administrate/roles/edit
http_method: GET
ui_path: Settings ▸ Security ▸ Roles ▸ open record
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - rID refers to an existing role (rID=0 / blank starts a new role)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - dynamically loads every module's *Admin class to assemble the full menu tree, then
      renders one security dropdown per menu item (levels: none/readonly/add/edit/full/admin)
returns:
  success_signal: edit layout with the role security tabs populated from meta['security']
  identifier: role rID
errors:
  - permission denied unless administrator
idempotency: safe (read-only)
related: [administrate.roles.save]
confidence: high
source: src/controllers/administrate/roles.php:84 (edit)
```

## administrate.roles.save

```yaml
id: administrate.roles.save
title: Create or update a security role (rewrites the permission map)
route: administrate/roles/save
http_method: POST
ui_path: Settings ▸ Security ▸ Roles ▸ Save
auth:
  sec_id: admin
  min_level: 4
preconditions: []
inputs:
  required:
    - name: title
      format: text
      source: post
      notes: role display name
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing role meta id; 0/blank creates a new role
    - name: administrate
      format: char
      source: post
      notes: >
        DANGER — selNoYes. Set to 1 to grant the role the administrator override flag.
        validateAccess('admin', …) then returns level 4 for EVERY admin-keyed action for any
        user in this role, bypassing the granular security map. (model/functions.php:1618)
    - name: restrict
      format: boolean
      source: post
      notes: restrict_access toggle (selNoYes)
    - name: inactive
      format: char
      source: post
      notes: selNoYes; inactive roles are excluded from user-assignment lists
    - name: security
      format: array
      source: post
      notes: >
        the permission matrix — security[<menuItemID>] = level (0..5). 0 none, 1 readonly,
        2 add, 3 edit, 4 full, 5 admin. Omitted items default to 0 (no access).
    - name: notes
      format: text
      source: post
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert/update
      notes: role record under meta prefix bizuno_role; the security map is serialized into meta
  gl_journal: none
  inventory: none
  side_effects:
    - selFill is stripped before save (UI autofill helper, not persisted)
    - bizCacheExpClear() — expires the business config cache so the new permissions take effect on next request
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: role rID
errors:
  - permission denied unless administrator
idempotency: idempotent on _rID — re-posting the same matrix yields the same role
related: [administrate.roles.read, administrate.roles.copy, administrate.user.update, administrate.roles.delete]
confidence: high
source: src/controllers/administrate/roles.php:109 (save), :48 (fieldStructure)
```

## administrate.roles.copy

```yaml
id: administrate.roles.copy
title: Duplicate an existing role
route: administrate/roles/copy
http_method: GET
ui_path: Settings ▸ Security ▸ Roles ▸ Copy
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - source role rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: role to copy (per parent::copyMeta convention)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the source role into the editor as a new (unsaved) record; the actual write happens on a subsequent roles.save
returns:
  success_signal: edit layout pre-filled from the source role
  identifier: none yet (new rID assigned on save)
errors:
  - permission denied unless administrator
idempotency: safe until saved (copy only stages an editor)
related: [administrate.roles.save]
confidence: low   # delegates to parent::copyMeta; exact staged-vs-written behavior inherited from mgrJournal — verify before automated use
source: src/controllers/administrate/roles.php:104 (copy)
```

## administrate.roles.delete

```yaml
id: administrate.roles.delete
title: Delete a security role
route: administrate/roles/delete
http_method: GET
ui_path: Settings ▸ Security ▸ Roles ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - NO active user is assigned to the role (delete is blocked otherwise)
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
      notes: removes the bizuno_role record
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: deleteMeta returns; grid reloads
  identifier: none
errors:
  - "err_delete_role: one or more users still belong to this role (lists the user names; delete refused, no change)"
  - "Illegal Access! if rID is missing"
  - permission denied unless administrator
idempotency: idempotent (deleting an already-gone role is a no-op)
related: [administrate.roles.save, administrate.user.update]
confidence: high
source: src/controllers/administrate/roles.php:117 (delete)
```

---
<!-- ===================== USERS ===================== -->

## administrate.user.list

```yaml
id: administrate.user.list
title: List user accounts
route: contacts/main/manager
http_method: GET
ui_path: Settings ▸ Directory ▸ Users
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required:
    - name: type
      format: char
      source: get
      notes: must be u — selects the user role context; getContactSecID('u') resolves to the 'admin' key
  optional:
    - name: search
      format: text
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - users are contact rows with ctype_u='1'; this is the contacts manager scoped to type=u
returns:
  success_signal: datagrid layout returned
  identifier: none
errors:
  - permission denied if not authorized on the admin key
idempotency: safe (read-only)
related: [administrate.user.update, administrate.roles.list]
confidence: high
source: src/controllers/contacts/main.php (manager, type=u); src/controllers/administrate/main.php:88 (menu route)
```

## administrate.user.update

```yaml
id: administrate.user.update
title: Edit a user (assign role + PhreeBooks restrictions via the admin hook)
route: contacts/main/save
http_method: POST
ui_path: Settings ▸ Directory ▸ Users ▸ open record ▸ Save
auth:
  sec_id: admin
  min_level: 3   # contacts save: 3 for an existing record. The admin::contactsSave hook additionally requires validateAccess('admin',4)
preconditions:
  - the user contact record already exists (the hook returns early if no id is posted — users are not created through this hook)
  - acting user is an administrator (hook guard: validateAccess('admin',4,false))
  - type=u
inputs:
  required:
    - name: type
      format: char
      source: get
      notes: must be u for the admin hook to fire
    - name: id
      format: integer
      source: post
      notes: existing user contact rID
  optional:
    - name: role_id
      format: integer
      source: post
      notes: >
        DANGER — assigns the user's security role. Setting this to an administrator role grants
        that user the full-override administrate flag. Stored in user_profile meta, not on the contact row.
    - name: store_id
      format: integer
      source: post
    - name: restrict_store
      format: boolean
      source: post
      notes: limit the user to their assigned store's data
    - name: restrict_user
      format: boolean
      source: post
      notes: limit the user to records they created
    - name: restrict_period
      format: boolean
      source: post
      notes: limit the user to the open accounting period
    - name: cash_acct
      format: cmd
      source: post
      notes: GL cash account override for this user
    - name: ar_acct
      format: cmd
      source: post
      notes: GL A/R account override
    - name: ap_acct
      format: cmd
      source: post
      notes: GL A/P account override
    # plus the standard contacts fields (email, telephone1 …) handled by contacts/main/save
  fixed: []
effects:
  db_writes:
    - table: contacts
      op: update
      notes: the underlying contact row (handled by contacts/main/save)
    - table: common_meta
      op: insert/update
      notes: user_profile meta keyed to the contact — role_id, restrictions, GL account overrides
  gl_journal: none
  inventory: none
  side_effects:
    - the contacts editor is reshaped by administrate::contactsEdit (history/wallet/price tabs removed; a role select + PhreeBooks panel added)
returns:
  success_signal: msgStack 'success' = msg_record_saved (from contacts save)
  identifier: user contact rID
errors:
  - "hook silently no-ops if type!=u, id missing, or the acting user is not an administrator"
  - permission denied if user lacks contacts level 3 on the admin key
idempotency: idempotent on id — re-posting the same profile yields the same row
related: [administrate.roles.save, administrate.user.list]
confidence: high
source: src/controllers/administrate/admin.php:97 (contactsSave), :55 (contactsEdit), :46 (hooks registration)
```

---
<!-- ===================== BACKUP / RESTORE / AUDIT ===================== -->

## administrate.backup.home

```yaml
id: administrate.backup.home
title: Open the backup / audit-log page
route: administrate/backup/manager
http_method: GET
ui_path: Settings ▸ Storage ▸ Backup
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
    - the Restore toolbar button is hidden unless security>3 (level 4)
returns:
  success_signal: backup page layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.backup.create, administrate.backup.list, administrate.audit.backup, administrate.audit.purge]
confidence: high
source: src/controllers/administrate/backup.php:49 (manager)
```

## administrate.backup.list

```yaml
id: administrate.backup.list
title: List stored backup files (filenames, sizes, dates)
route: administrate/backup/mgrRows
http_method: POST
ui_path: (AJAX backing the backup files grid)
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions: []
inputs:
  required: []
  optional:
    - name: rows
      format: integer
      source: post
      notes: page size, default 10
    - name: page
      format: integer
      source: post
      notes: page number, default 1
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - globs the backups/ directory and returns the file list as JSON
returns:
  success_signal: raw JSON {total, rows[]} of backup files
  identifier: each row's title is the backup filename
errors: []
idempotency: safe (read-only)
related: [administrate.backup.create, administrate.restore.apply]
confidence: high
source: src/controllers/administrate/backup.php:125 (mgrRows)
```

## administrate.backup.create

```yaml
id: administrate.backup.create
title: Run a full database backup
route: administrate/backup/save
http_method: POST
ui_path: Settings ▸ Storage ▸ Backup ▸ Go (Backup)
auth:
  sec_id: admin
  min_level: 2
preconditions:
  - PHP exec/dump tooling available (dbDump handles its own error reporting)
inputs:
  required: []
  optional: []
  fixed:
    - name: filename
      value: "<company id>-<Ymd-His>"
      notes: generated from company settings + timestamp; not caller-supplied
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - writes a full DB dump into the backups/ directory
    - msgLog audit entry on success; raises max_execution_time
    - reloads the dgBackup grid
returns:
  success_signal: msgStack 'success' = msg_backup_success
  identifier: the new backup filename (timestamped)
errors:
  - "dbDump emits its own error if the dump fails"
  - permission denied if user lacks admin level 2
idempotency: NOT idempotent — each run writes a new timestamped file
related: [administrate.backup.list, administrate.restore.apply]
confidence: high
source: src/controllers/administrate/backup.php:142 (save)
```

## administrate.restore.home

```yaml
id: administrate.restore.home
title: Open the database restore page
route: administrate/backup/managerRestore
http_method: GET
ui_path: Settings ▸ Storage ▸ Backup ▸ Restore
auth:
  sec_id: admin
  min_level: 4
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
    - renders the restore-file grid (backed by backup/mgrRows) and an upload form
returns:
  success_signal: restore page layout returned
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: safe (read-only)
related: [administrate.restore.upload, administrate.restore.apply]
confidence: high
source: src/controllers/administrate/backup.php:94 (managerRestore)
```

## administrate.restore.upload

```yaml
id: administrate.restore.upload
title: Upload a backup file into the restore-source folder
route: administrate/backup/uploadRestore
http_method: POST
ui_path: Settings ▸ Storage ▸ Backup ▸ Restore ▸ Upload
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions: []
inputs:
  required:
    - name: fldFile
      format: file
      source: post
      notes: uploaded backup file (multipart/form-data)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - io->uploadSave writes the uploaded file into the backups/ directory — the same folder saveRestore reads from
    - reloads the dgRestore grid
returns:
  success_signal: dgRestore grid reload
  identifier: none
errors: []
idempotency: re-uploading the same name overwrites
related: [administrate.restore.apply]
confidence: high
source: src/controllers/administrate/backup.php:187 (uploadRestore)
```

## administrate.restore.apply

```yaml
id: administrate.restore.apply
title: Restore the database from a backup file (IRREVERSIBLE — replaces all tables)
route: administrate/backup/saveRestore
http_method: GET
ui_path: Settings ▸ Storage ▸ Backup ▸ Restore ▸ (file) Restore
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - the backup file exists under BIZUNO_DATA
  - PHP exec() is enabled and valid DB credentials are available
inputs:
  required:
    - name: data
      format: filename
      source: get
      notes: backup filename (relative to BIZUNO_DATA). Supported extensions — .sql, .zip, .gz.
  optional: []
  fixed: []
effects:
  db_writes:
    - table: (ENTIRE DATABASE)
      op: replace
      notes: pipes the dump into the mysql client, overwriting every table. Irreversible.
  gl_journal: none   # no posting, but every existing journal/balance is replaced wholesale
  inventory: none
  side_effects:
    - credentials passed via a chmod-600 --defaults-extra-file (not argv); temp file unlinked after
    - clears the bizunoSession cookie (forces logout) and expires the cache on success
    - sets memory_limit 1024M and time limit 3600s
returns:
  success_signal: on success, redirect to BIZUNO_URL_PORTAL (session cleared → re-login)
  identifier: none
errors:
  - "Bad filename passed! if the file is not found under BIZUNO_DATA"
  - "invalid_credentials if DB name/user/pass cannot be resolved"
  - "php exec is disabled message if exec() unavailable"
  - "restore-error message if the mysql client returns non-zero"
  - permission denied unless administrator
idempotency: >
  NOT a safe retry target. The whole DB is replaced; if it half-completes the message says
  "most likely nothing was done" but state is undefined. Take a fresh backup first.
related: [administrate.backup.create, administrate.restore.upload]
confidence: high
source: src/controllers/administrate/backup.php:199 (saveRestore), :215 (dbRestore)
```

## administrate.audit.backup

```yaml
id: administrate.audit.backup
title: Back up only the audit_log table
route: administrate/backup/saveAudit
http_method: POST
ui_path: Settings ▸ Storage ▸ Backup ▸ Audit ▸ Go
auth:
  sec_id: admin
  min_level: 2
preconditions: []
inputs:
  required: []
  optional: []
  fixed:
    - name: filename
      value: "bizuno_log-<Ymd-His>"
    - name: table
      value: "<prefix>audit_log"
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - dumps the audit_log table into backups/ and reloads the grid
returns:
  success_signal: msgStack 'success' = msg_backup_success
  identifier: the audit backup filename
errors:
  - "dbDump emits its own error on failure"
  - permission denied if user lacks admin level 2
idempotency: NOT idempotent — each run writes a new timestamped file
related: [administrate.audit.purge, administrate.backup.create]
confidence: high
source: src/controllers/administrate/backup.php:161 (saveAudit)
```

## administrate.audit.purge

```yaml
id: administrate.audit.purge
title: Permanently delete audit_log entries on or before a date
route: administrate/backup/cleanAudit
http_method: POST
ui_path: Settings ▸ Storage ▸ Backup ▸ Audit ▸ Clean ▸ Go
auth:
  sec_id: admin
  min_level: 4
preconditions: []
inputs:
  required: []
  optional:
    - name: dateClean
      format: date
      source: post
      notes: cutoff date; rows with date <= '<dateClean> 23:59:59' are deleted. Defaults to one month before today.
  fixed: []
effects:
  db_writes:
    - table: audit_log
      op: delete
      notes: DELETE FROM audit_log WHERE date <= cutoff — permanent
  gl_journal: none
  inventory: none
  side_effects:
    - irreversible loss of audit history; back up first via administrate.audit.backup
returns:
  success_signal: dbAction DELETE statement returned and executed
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: idempotent (re-running with the same cutoff deletes nothing more)
related: [administrate.audit.backup, administrate.fy.close.next]
confidence: high
source: src/controllers/administrate/backup.php:174 (cleanAudit)
```

---
<!-- ===================== TOOLS ===================== -->

## administrate.tools.cacheClear

```yaml
id: administrate.tools.cacheClear
title: Clear the business configuration cache
route: administrate/tools/cacheClear
http_method: POST
ui_path: Settings ▸ Bizuno ▸ Tools ▸ Clear Cache
auth:
  sec_id: admin
  min_level: 3
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: zeros bizuno_cache_expires; next request triggers a full registry rebuild
  gl_journal: none
  inventory: none
  side_effects:
    - forces re-run of every module's initialize() on the next authenticated request (re-seeds options, role menus, dashboards, phreeform)
    - msgLog entry
returns:
  success_signal: msgStack 'success' = admin_cache_clear_done
  identifier: none
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent (safe to re-run)
related: [administrate.roles.save, administrate.tools.statusSave]
confidence: high
source: src/controllers/administrate/tools.php:224 (cacheClear)
```

## administrate.tools.statusSave

```yaml
id: administrate.tools.statusSave
title: Update the auto-numbering reference counters (next document numbers)
route: administrate/tools/statusSave
http_method: POST
ui_path: Settings ▸ Bizuno ▸ Tools ▸ Status / Reference numbers
auth:
  sec_id: admin
  min_level: 3
preconditions:
  - bizuno_refs meta exists
inputs:
  required: []
  optional:
    - name: stat_<key>
      format: alpha_num
      source: post
      notes: >
        one field per reference counter (e.g. stat_next_cust_id_num). Blank values are skipped.
        Handles both dict-shaped (post-7.x) and legacy scalar bizuno_refs entries.
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: bizuno_refs — sets each counter's next value
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_settings_saved
  identifier: none
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent (overwrites named counters)
related: [administrate.tools.cacheClear]
confidence: high
source: src/controllers/administrate/tools.php:200 (statusSave)
```

## administrate.tools.repairTables

```yaml
id: administrate.tools.repairTables
title: Reconcile DB schema against the install definitions (bulk ALTER TABLE)
route: administrate/tools/repairTables
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Tools ▸ Repair Tables
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions: []
inputs:
  required: []
  optional:
    - name: (verbose)
      format: boolean
      source: n/a
      notes: method arg defaults true (emits "finished!"); not a posted field
  fixed: []
effects:
  db_writes:
    - table: (every core table)
      op: alter
      notes: >
        loads install/tables.php and runs ALTER TABLE … ADD/CHANGE COLUMN across all core tables to
        match the shipped schema. Long-running. No permission check at all.
  gl_journal: none
  inventory: none
  side_effects:
    - data-preserving in normal use, but executes DDL on every table; can take a long time
returns:
  success_signal: "finished!" message (when verbose)
  identifier: none
errors:
  - "skips (with a message) any core table that does not exist"
idempotency: idempotent in effect (re-aligns columns to the same target schema)
related: [administrate.fields.save]
confidence: high
source: src/controllers/administrate/tools.php:171 (repairTables)
```

## administrate.tools.ticketForm

```yaml
id: administrate.tools.ticketForm
title: Open the support-ticket form
route: administrate/tools/ticketMain
http_method: GET
ui_path: Help ▸ Support Ticket
auth:
  sec_id: none
  min_level: NONE (ungated)
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
    - pre-fills the form from the user's profile (name/email)
returns:
  success_signal: ticket form layout returned
  identifier: none
errors: []
idempotency: safe (read-only)
related: [administrate.tools.ticketSend]
confidence: high
source: src/controllers/administrate/tools.php:51 (ticketMain)
```

## administrate.tools.ticketSend

```yaml
id: administrate.tools.ticketSend
title: Email a support ticket to PhreeSoft
route: administrate/tools/ticketSave
http_method: POST
ui_path: Help ▸ Support Ticket ▸ Submit
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions:
  - a support email address is configured for the business (BIZUNO_SUPPORT_EMAIL)
inputs:
  required:
    - name: ticketDesc
      format: text
      source: post
      notes: ticket body
  optional:
    - name: ticketUser
      format: text
      source: post
    - name: ticketEmail
      format: text
      source: post
    - name: ticketPhone
      format: text
      source: post
    - name: selReason
      format: text
      source: post
      notes: question | bug | suggestion | account
    - name: selMachine
      format: text
      source: post
    - name: selOS
      format: text
      source: post
    - name: selBrowser
      format: text
      source: post
    - name: ticketFile
      format: file
      source: post
      notes: optional attachment, validated against the file ext whitelist
  fixed:
    - name: msgFrom
      value: "user"
      notes: forces send from the user's mail account
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - sends an email to the configured PhreeSoft support address (external side effect)
returns:
  success_signal: success message; ticket form re-rendered
  identifier: none
errors:
  - "no support email defined message"
  - "send failure message"
idempotency: NOT idempotent — each submit sends another email
related: [administrate.tools.ticketForm]
confidence: high
source: src/controllers/administrate/tools.php:90 (ticketSave)
```

## administrate.fy.close.home

```yaml
id: administrate.fy.close.home
title: Add the Bizuno audit-purge option to the PhreeBooks fiscal-year-close screen
route: administrate/tools/fyCloseHome
http_method: GET
ui_path: PhreeBooks ▸ Tools ▸ Close Fiscal Year (Bizuno tab)
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - invoked as a hook from the PhreeBooks FY-close flow
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - adds a "Do not delete audit log entries" checkbox (bizuno_keep) to the FY-close tab
returns:
  success_signal: FY-close tab augmented with the Bizuno panel
  identifier: none
errors:
  - permission denied unless administrator
idempotency: safe (read-only)
related: [administrate.fy.close.queue, administrate.fy.close.next]
confidence: medium   # hook into PhreeBooks FY-close; behavior depends on the parent flow
source: src/controllers/administrate/tools.php:126 (fyCloseHome)
```

## administrate.fy.close.queue

```yaml
id: administrate.fy.close.queue
title: Queue the Bizuno audit purge as part of fiscal-year close
route: administrate/tools/fyClose
http_method: POST
ui_path: (hook fired by the PhreeBooks FY-close submit)
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - fired as a hook during PhreeBooks FY close
inputs:
  required: []
  optional:
    - name: bizuno_keep
      format: boolean
      source: post
      notes: if checked (1), the audit purge is NOT queued and nothing happens
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - appends a taskClose entry to the fyClose user-cron queue (the purge runs later via fyCloseNext)
returns:
  success_signal: cron task queued (no direct user message)
  identifier: none
errors:
  - permission denied unless administrator
idempotency: idempotent per close run (a single task is queued)
related: [administrate.fy.close.next, administrate.audit.purge]
confidence: medium
source: src/controllers/administrate/tools.php:138 (fyClose)
```

## administrate.fy.close.next

```yaml
id: administrate.fy.close.next
title: Execute the queued fiscal-year audit_log purge (permanent delete)
route: administrate/tools/fyCloseNext
http_method: POST
ui_path: (cron continuation of FY close — not a direct UI button)
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - a fyEndDate is present in the cron payload
inputs:
  required: []
  optional: []
  fixed:
    - name: cutoff
      value: "<cron.fyEndDate> 23:59:59"
      notes: derived from the cron payload, not from request input
effects:
  db_writes:
    - table: audit_log
      op: delete
      notes: DELETE FROM audit_log WHERE date <= fiscal-year-end — permanent
  gl_journal: none
  inventory: none
  side_effects:
    - reports the deleted row count back into the cron message stream
returns:
  success_signal: "Finished processing audit_log table"
  identifier: none
errors:
  - permission denied unless administrator
  - "returns early if no fyEndDate in the cron payload"
idempotency: idempotent (re-running with the same cutoff removes nothing more)
related: [administrate.fy.close.queue, administrate.audit.purge]
confidence: medium   # runs in a cron continuation; the $cron payload is supplied by the FY-close orchestrator, not the request
source: src/controllers/administrate/tools.php:152 (fyCloseNext)
```

---
<!-- ===================== CUSTOM FIELDS ===================== -->

## administrate.fields.list

```yaml
id: administrate.fields.list
title: List custom fields (contacts / inventory)
route: administrate/fields/manager
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Fields
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
    - renders the custom-fields grid; only the contacts and inventory tables are editable
returns:
  success_signal: fields manager layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.fields.list.rows, administrate.fields.add, administrate.fields.save]
confidence: high
source: src/controllers/administrate/fields.php:72 (manager)
```

## administrate.fields.list.rows

```yaml
id: administrate.fields.list.rows
title: Fetch custom-field rows (the existing tab>0 columns on contacts/inventory)
route: administrate/fields/managerRows
http_method: GET
ui_path: (AJAX backing the custom-fields grid)
auth:
  sec_id: admin
  min_level: 4    # note: managerRows here checks level 4, not 1 (asymmetric with manager)
preconditions: []
inputs:
  required: []
  optional:
    - name: sort
      format: text
      source: post
      notes: default label
    - name: page
      format: integer
      source: post
    - name: rows
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - introspects the live table structure (dbLoadStructure) for fields with tab != 0
returns:
  success_signal: JSON rows + total; each _rID is "<table>.<field>"
  identifier: _rID = table.field
errors:
  - permission denied unless administrator (admin,4)
idempotency: safe (read-only)
related: [administrate.fields.list, administrate.fields.read]
confidence: high
source: src/controllers/administrate/fields.php:80 (managerRows)
```

## administrate.fields.add

```yaml
id: administrate.fields.add
title: Start adding a new custom field (choose target table)
route: administrate/fields/add
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Fields ▸ Add
auth:
  sec_id: admin
  min_level: 2
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
    - renders an add form with a table selector (contacts | inventory); the column is written on the subsequent fields.save
returns:
  success_signal: add layout returned
  identifier: none yet
errors:
  - permission denied if user lacks admin level 2
idempotency: safe until saved
related: [administrate.fields.read, administrate.fields.save]
confidence: high
source: src/controllers/administrate/fields.php:98 (add)
```

## administrate.fields.read

```yaml
id: administrate.fields.read
title: Open a custom field for editing (renders the type/attribute builder)
route: administrate/fields/edit
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Fields ▸ open record
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - rID is "<table>.<field>" for an existing field, OR table is supplied for a new field
inputs:
  required:
    - name: rID
      format: text
      source: get
      notes: "table.field" of an existing custom column; empty = add mode
  optional:
    - name: table
      format: db_field
      source: get
      notes: required in add mode; must be contacts or inventory
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - on edit, emits a caution that changing a live column can affect data
returns:
  success_signal: field builder layout returned
  identifier: none
errors:
  - "err_new_field_add if the table is not contacts/inventory (add mode)"
  - "err_new_field_edit if the table.field cannot be parsed (edit mode)"
  - permission denied unless administrator
idempotency: safe (read-only)
related: [administrate.fields.save]
confidence: high
source: src/controllers/administrate/fields.php:108 (edit)
```

## administrate.fields.save

```yaml
id: administrate.fields.save
title: Create or alter a custom field (LIVE ALTER TABLE / DDL)
route: administrate/fields/save
http_method: POST
ui_path: Settings ▸ Bizuno ▸ Custom Fields ▸ Save
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - table is contacts or inventory
  - the new field name does not already exist (rename collisions are rejected)
inputs:
  required:
    - name: table
      format: text
      source: post
      notes: target table (contacts | inventory)
    - name: field
      format: text
      source: post
      notes: new column name; non [A-Za-z0-9_] chars stripped; must be non-empty
    - name: type
      format: text
      source: post
      notes: >
        text | html | link_url | link_image | link_inventory | integer | float | select | radio |
        checkbox_multi | checkbox | date | time | datetime | timestamp. Selects the SQL column type.
  optional:
    - name: id
      format: text
      source: post
      notes: old field name (holds the existing column when renaming/editing); blank = new column
    - name: text_length
      format: integer
      source: post
      notes: for text/html types; <256 → VARCHAR, larger → TEXT/MEDIUMTEXT/LONGTEXT
    - name: text_default / link_default / int_default / float_default / checkbox_default
      format: (per type)
      source: post
      notes: default value for the chosen type
    - name: int_select
      format: text
      source: post
      notes: tinyint | smallint | mediumint | int | bigint
    - name: float_select
      format: text
      source: post
      notes: float | double
    - name: radio_default
      format: text
      source: post
      notes: "for select/radio/checkbox_multi: 'id:label;id:label' pairs → ENUM(...)"
    - name: order
      format: integer
      source: post
      notes: display order (stored in the column COMMENT)
    - name: tab
      format: integer
      source: post
      notes: custom-tab id this field renders under (stored in COMMENT)
    - name: label
      format: text
      source: post
    - name: tag
      format: text
      source: post
    - name: group
      format: text
      source: post
  fixed: []
effects:
  db_writes:
    - table: contacts | inventory
      op: alter
      notes: >
        runs ALTER TABLE … ADD COLUMN (new) or CHANGE … (rename/retype) with the computed SQL type and
        a COMMENT carrying the UI metadata (type, order, tab, label, tag, group, opts). Live DDL.
  gl_journal: none
  inventory: none
  side_effects:
    - msgLog entry; reloads the dgFields grid
    - retyping/shrinking an existing column can truncate or coerce stored data
returns:
  success_signal: msgStack 'success' = extra_fields … msg_database_write
  identifier: the new <table>.<field>
errors:
  - "Table information missing!"
  - "err_field_empty if the cleaned field name is empty"
  - "xf_err_field_exists if the new column name collides with an existing one"
  - permission denied unless administrator
idempotency: >
  re-saving the same definition is effectively idempotent, but a rename changes the natural key
  (table.field). Treat type/length changes as data-affecting; verify on a copy first.
related: [administrate.fields.read, administrate.fields.delete, administrate.tools.repairTables]
confidence: high
source: src/controllers/administrate/fields.php:350 (save)
```

## administrate.fields.delete

```yaml
id: administrate.fields.delete
title: Drop a custom field (DROP COLUMN — destroys the column's data)
route: administrate/fields/delete
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Fields ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - both table and field are supplied
inputs:
  required:
    - name: table
      format: text
      source: get
    - name: data
      format: text
      source: get
      notes: the field (column) name to drop
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts | inventory
      op: alter
      notes: ALTER TABLE … DROP COLUMN — permanently removes the column and all data in it
  gl_journal: none
  inventory: none
  side_effects:
    - msgLog entry; reloads the dgFields grid
returns:
  success_signal: dbAction DROP COLUMN returned; grid reloads
  identifier: none
errors:
  - "Table and/of field information missing!"
  - permission denied unless administrator
idempotency: idempotent (dropping an already-gone column is a no-op, but DROP COLUMN errors if absent)
related: [administrate.fields.save]
confidence: high
source: src/controllers/administrate/fields.php:464 (delete)
```

---
<!-- ===================== CUSTOM TABS ===================== -->

## administrate.tabs.list

```yaml
id: administrate.tabs.list
title: List custom tabs
route: administrate/tabs/manager
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Tabs
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
    - renders the tabs grid; copy action is removed
returns:
  success_signal: tabs manager layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.tabs.list.rows, administrate.tabs.read, administrate.tabs.save]
confidence: high
source: src/controllers/administrate/tabs.php:70 (manager)
```

## administrate.tabs.list.rows

```yaml
id: administrate.tabs.list.rows
title: Fetch custom-tab rows (data only)
route: administrate/tabs/managerRows
http_method: GET
ui_path: (AJAX backing the tabs grid)
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: sort
      format: text
      source: post
      notes: default title
    - name: order
      format: db_field
      source: post
      notes: ASC | DESC
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows + total (common_meta, prefix tabs)
  identifier: each row's _rID
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.tabs.list]
confidence: high
source: src/controllers/administrate/tabs.php:76 (managerRows)
```

## administrate.tabs.read

```yaml
id: administrate.tabs.read
title: Open a custom tab for editing
route: administrate/tabs/edit
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Tabs ▸ open record
auth:
  sec_id: admin
  min_level: 1
preconditions:
  - rID refers to an existing tab (blank/0 = new)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - copy toolbar icon is removed
returns:
  success_signal: tab edit layout returned
  identifier: tab rID
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.tabs.save]
confidence: high
source: src/controllers/administrate/tabs.php:81 (edit)
```

## administrate.tabs.save

```yaml
id: administrate.tabs.save
title: Create or update a custom tab
route: administrate/tabs/save
http_method: POST
ui_path: Settings ▸ Bizuno ▸ Custom Tabs ▸ Save
auth:
  sec_id: admin
  min_level: 3
preconditions: []
inputs:
  required:
    - name: title
      format: text
      source: post
      notes: tab display name
    - name: table
      format: db_field
      source: post
      notes: target table — contacts | inventory
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing tab meta id; 0/blank creates a new tab
    - name: order
      format: integer
      source: post
      notes: display order (default 50)
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert/update
      notes: tab record under the tabs meta prefix
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: tab _rID
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent on _rID
related: [administrate.tabs.read, administrate.tabs.delete, administrate.fields.save]
confidence: high
source: src/controllers/administrate/tabs.php:87 (save)
```

## administrate.tabs.delete

```yaml
id: administrate.tabs.delete
title: Delete a custom tab
route: administrate/tabs/delete
http_method: GET
ui_path: Settings ▸ Bizuno ▸ Custom Tabs ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions: []
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
      notes: removes the tab record (does not drop fields assigned to it)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: deleteMeta returns
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: idempotent
related: [administrate.tabs.save]
confidence: high
source: src/controllers/administrate/tabs.php:92 (delete)
```

---
<!-- ===================== DASHBOARDS ===================== -->

## administrate.dashboard.list

```yaml
id: administrate.dashboard.list
title: Open the dashboard-defaults admin page
route: administrate/dashboard/manager
http_method: GET
ui_path: Settings ▸ Security ▸ Dashboards
auth:
  sec_id: admin
  min_level: 4
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
    - lists all registered dashboards grouped by category; each gets an inline settings form (administrate/dashboard/save)
returns:
  success_signal: dashboard admin layout returned
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: safe (read-only)
related: [administrate.dashboard.save]
confidence: high
source: src/controllers/administrate/dashboard.php:45 (manager)
```

## administrate.dashboard.save

```yaml
id: administrate.dashboard.save
title: Save default settings for a dashboard
route: administrate/dashboard/save
http_method: POST
ui_path: Settings ▸ Security ▸ Dashboards ▸ (dashboard) Save
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - dashID identifies a registered dashboard
inputs:
  required:
    - name: dashID
      format: db_field
      source: get
      notes: the dashboard id whose defaults are being set
  optional:
    - name: <dashID>_<setting>
      format: (per the dashboard's struc)
      source: post
      notes: >
        one posted field per setting declared by the dashboard class. Each is cleaned with the
        clean() format declared in that dashboard's struc. 'users'/'roles' settings are array fields.
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: dashboards meta — sets the named dashboard's opts
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_settings_saved
  identifier: none
errors:
  - "bad_data trap if the dashboard id is unknown"
  - permission denied unless administrator
idempotency: idempotent on dashID
related: [administrate.dashboard.list]
confidence: high
source: src/controllers/administrate/dashboard.php:117 (save)
```

---
<!-- ===================== FIXED ASSETS ===================== -->

## administrate.asset.list

```yaml
id: administrate.asset.list
title: List fixed assets
route: administrate/fixedAssets/manager
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: status
      format: char
      source: post
      notes: a (all) | 0 (active) | 1 (inactive), default a
    - name: store
      format: integer
      source: post
      notes: store filter when multiple stores exist
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - assets are stored in common_meta (prefix fixed_asset); renders the asset grid
returns:
  success_signal: asset manager layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.asset.list.rows, administrate.asset.read, administrate.asset.save]
confidence: high
source: src/controllers/administrate/fixedAssets.php:116 (manager)
```

## administrate.asset.list.rows

```yaml
id: administrate.asset.list.rows
title: Fetch fixed-asset rows (data only)
route: administrate/fixedAssets/managerRows
http_method: GET
ui_path: (AJAX backing the asset grid)
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: status
      format: char
      source: post
      notes: a | 0 | 1
    - name: store
      format: integer
      source: post
    - name: sort
      format: cmd
      source: post
    - name: page
      format: integer
      source: post
    - name: rows
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows + total
  identifier: each row's meta id (rID)
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.asset.list]
confidence: high
source: src/controllers/administrate/fixedAssets.php:142 (managerRows)
```

## administrate.asset.read

```yaml
id: administrate.asset.read
title: Open a fixed asset for editing
route: administrate/fixedAssets/edit
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ open record
auth:
  sec_id: admin
  min_level: 1
preconditions:
  - rID refers to an existing asset (blank/0 = new)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - adds an image manager, attachment panel, and a "Calculate depreciation" button (getSchedValue)
returns:
  success_signal: asset edit layout returned
  identifier: asset rID
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.asset.save, administrate.asset.calcValue]
confidence: high
source: src/controllers/administrate/fixedAssets.php:161 (edit)
```

## administrate.asset.save

```yaml
id: administrate.asset.save
title: Create or update a fixed asset
route: administrate/fixedAssets/save
http_method: POST
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ Save
auth:
  sec_id: admin
  min_level: 2   # create (empty _rID); 3 when updating an existing asset
preconditions: []
inputs:
  required:
    - name: title
      format: text
      source: post
      schema_field: fixed_asset.title
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing asset meta id; empty creates a new asset (level 2) vs update (level 3)
    - name: ref_num
      format: cmd
      source: post
      notes: asset reference; auto-numbered from next_fxdast_num when blank
    - name: description
      format: text
      source: post
    - name: type
      format: alpha_num
      source: post
      notes: asset type key from options_fxdast_types
    - name: store_id
      format: integer
      source: post
    - name: cost
      format: currency
      source: post
    - name: serial_number
      format: cmd
      source: post
    - name: date_acq
      format: dateMeta
      source: post
    - name: date_maint
      format: dateMeta
      source: post
    - name: date_retire
      format: dateMeta
      source: post
    - name: dep_sched
      format: text
      source: post
      notes: depreciation schedule title (see administrate.asset.schedule.save)
    - name: purch_cond
      format: char
      source: post
      notes: n (new) | u (used)
    - name: gl_asset / gl_maint / gl_dep
      format: cmd
      source: post
      notes: GL account references stored on the asset record (not posted to the ledger)
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert/update
      notes: asset record under the fixed_asset meta prefix
  gl_journal: none
  inventory: none
  side_effects:
    - auto-numbers ref_num from next_fxdast_num on create
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: asset _rID
errors:
  - permission denied (level 2 create / 3 update)
idempotency: idempotent on _rID
related: [administrate.asset.read, administrate.asset.calcValue, administrate.asset.delete]
confidence: high
source: src/controllers/administrate/fixedAssets.php:201 (save)
```

## administrate.asset.copy

```yaml
id: administrate.asset.copy
title: Duplicate a fixed asset
route: administrate/fixedAssets/copy
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ Copy
auth:
  sec_id: admin
  min_level: 2
preconditions:
  - source asset rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - stages a copy in the editor (write happens on the subsequent save)
returns:
  success_signal: edit layout pre-filled from the source asset
  identifier: none yet
errors:
  - permission denied if user lacks admin level 2
idempotency: safe until saved
related: [administrate.asset.save]
confidence: low   # delegates to parent::copyMeta; staged-vs-written behavior inherited from mgrJournal
source: src/controllers/administrate/fixedAssets.php:196 (copy)
```

## administrate.asset.delete

```yaml
id: administrate.asset.delete
title: Delete a fixed asset
route: administrate/fixedAssets/delete
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions: []
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
      notes: removes the fixed_asset record
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: deleteMeta returns
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: idempotent
related: [administrate.asset.save]
confidence: high
source: src/controllers/administrate/fixedAssets.php:212 (delete)
```

## administrate.asset.export

```yaml
id: administrate.asset.export
title: Export fixed assets
route: administrate/fixedAssets/export
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ Export
auth:
  sec_id: none (no local check)
  min_level: NONE (delegates to parent::export with no local validateAccess)
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
    - delegates to mgrJournal::export(); any access control is inherited from the parent, not enforced here
returns:
  success_signal: file download (per parent export)
  identifier: none
errors: []
idempotency: safe (read-only)
related: [administrate.asset.list]
confidence: low   # no local guard; relies entirely on parent::export() — verify parent enforces access before automating
source: src/controllers/administrate/fixedAssets.php:207 (export)
```

## administrate.asset.calcValue

```yaml
id: administrate.asset.calcValue
title: Calculate and store the current depreciated value of one asset
route: administrate/fixedAssets/getSchedValue
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ open record ▸ Calculate
auth:
  sec_id: admin
  min_level: 3   # validateAccess('admin', rID?3:2) — rID is read from GET, so an asset id resolves to 3
preconditions:
  - the asset has a dep_sched assigned and a matching schedule exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: asset meta id
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: writes the computed dep_value back onto the asset record
  gl_journal: none
  inventory: none
  side_effects:
    - msgLog entry (when verbose); updates the dep_value field in the editor
returns:
  success_signal: msgStack 'success' = msg_database_write; dep_value set in UI
  identifier: none
errors:
  - "err_no_sched if the asset has no dep_sched or no schedules are defined"
  - permission denied if user lacks admin level 3 on the asset
idempotency: idempotent (recomputes the same value for a given year/cost/schedule)
related: [administrate.asset.calcValue.bulk, administrate.asset.schedule.save]
confidence: high
source: src/controllers/administrate/fixedAssets.php:271 (getSchedValue)
```

## administrate.asset.calcValue.bulk

```yaml
id: administrate.asset.calcValue.bulk
title: Recalculate depreciated values for all active assets (cron batch)
route: administrate/fixedAssets/depValueBulk
http_method: GET
ui_path: Settings ▸ Directory ▸ Fixed Assets ▸ (bulk recalc)
auth:
  sec_id: admin
  min_level: 2   # BUG: validateAccess('admin', $rID?3:2) but $rID is never set here → null → falsy → 2
preconditions: []
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []   # this kickoff only queues a cron; the per-asset writes happen in faCalcBulkNext
  gl_journal: none
  inventory: none
  side_effects:
    - selects all assets with empty status and queues them in the faCalc user-cron
    - returns a cronInit action that iterates faCalcBulkNext
returns:
  success_signal: cronInit action returned
  identifier: none
errors:
  - "no_results if no asset meta rows exist"
  - permission denied if user lacks admin level 2
idempotency: idempotent in effect (recomputes values); re-running re-queues
related: [administrate.asset.calcValue, administrate.asset.calcValue.next]
confidence: medium   # admin-override users (level 4) are unaffected; for granular roles the undefined-$rID bug lowers the gate to level 2 — see Open questions
source: src/controllers/administrate/fixedAssets.php:223 (depValueBulk)
```

## administrate.asset.calcValue.next

```yaml
id: administrate.asset.calcValue.next
title: Cron continuation — recalc the next queued asset
route: administrate/fixedAssets/faCalcBulkNext
http_method: GET
ui_path: (AJAX cron step of the bulk recalc)
auth:
  sec_id: admin
  min_level: 2   # same undefined-$rID bug as depValueBulk → resolves to level 2
preconditions:
  - a faCalc cron queue exists (created by depValueBulk)
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: writes dep_value for the next asset in the queue (via getSchedValue)
  gl_journal: none
  inventory: none
  side_effects:
    - advances the progress bar; msgLog on completion; clears the cron when the queue drains
returns:
  success_signal: progress-bar content (percent + message)
  identifier: none
errors:
  - permission denied if user lacks admin level 2
idempotency: idempotent in effect (recomputes the same values)
related: [administrate.asset.calcValue.bulk]
confidence: medium
source: src/controllers/administrate/fixedAssets.php:243 (faCalcBulkNext)
```

## administrate.asset.schedule.read

```yaml
id: administrate.asset.schedule.read
title: Load a depreciation schedule (percent-good table)
route: administrate/fixedAssets/adminSchedLoad
http_method: GET
ui_path: Settings ▸ Fixed Assets admin ▸ Depreciation Schedules
auth:
  sec_id: none
  min_level: NONE (ungated — render only)
preconditions: []
inputs:
  required: []
  optional:
    - name: rID
      format: text
      source: get
      notes: schedule title to load; defaults to the first schedule
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - renders the editable schedule grid from fixed_assets_schedules meta
returns:
  success_signal: schedule grid layout returned
  identifier: none
errors: []
idempotency: safe (read-only)
related: [administrate.asset.schedule.save]
confidence: high
source: src/controllers/administrate/fixedAssets.php:309 (adminSchedLoad)
```

## administrate.asset.schedule.save

```yaml
id: administrate.asset.schedule.save
title: Save or delete a shared depreciation schedule
route: administrate/fixedAssets/adminSchedSave
http_method: GET
ui_path: Settings ▸ Fixed Assets admin ▸ Depreciation Schedules ▸ Save
auth:
  sec_id: none
  min_level: NONE (ungated)
preconditions: []
inputs:
  required:
    - name: rID
      format: text
      source: get
      notes: schedule title (category); required
    - name: data
      format: json
      source: get
      notes: "{rows:[{label:...}]} percent-good rows. An empty rows array DELETES the named schedule."
  optional: []
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert/update/delete
      notes: >
        fixed_assets_schedules meta — writes the named schedule, or removes it when rows is empty.
        SHARED config affecting every asset that references the schedule. No permission check.
  gl_journal: none
  inventory: none
  side_effects:
    - re-sorts the schedule map; refreshes the sched panel
returns:
  success_signal: msgStack 'success' = msg_settings_saved
  identifier: none
errors:
  - "Category field is required! if rID (title) is empty"
idempotency: idempotent on title (overwrites the named schedule)
related: [administrate.asset.schedule.read, administrate.asset.calcValue]
confidence: high
source: src/controllers/administrate/fixedAssets.php:337 (adminSchedSave)
```

---
<!-- ===================== MAINTENANCE: ACTIVITY (maint) ===================== -->

## administrate.maint.activity.list

```yaml
id: administrate.maint.activity.list
title: List maintenance activity records
route: administrate/maint/manager
http_method: GET
ui_path: (maintenance activity register — journal 35)
auth:
  sec_id: mgr_maint
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: period
      format: cmd
      source: post
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none   # journalID 35 is used as a record container only; no GL posting
  inventory: none
  side_effects:
    - activity records live in journal_main (jID 35); renders the activity grid
returns:
  success_signal: activity manager layout returned
  identifier: none
errors:
  - permission denied if user lacks mgr_maint level 1
idempotency: safe (read-only)
related: [administrate.maint.activity.list.rows, administrate.maint.activity.save, administrate.maint.task.list]
confidence: high
source: src/controllers/administrate/maint.php:122 (manager)
```

## administrate.maint.activity.list.rows

```yaml
id: administrate.maint.activity.list.rows
title: Fetch maintenance activity rows (data only)
route: administrate/maint/managerRows
http_method: GET
ui_path: (AJAX backing the activity grid)
auth:
  sec_id: mgr_maint
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: search
      format: text
      source: post
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: datagrid rows (journal_main, jID 35)
  identifier: each row's journal id
errors:
  - permission denied if user lacks mgr_maint level 1
idempotency: safe (read-only)
related: [administrate.maint.activity.list]
confidence: high
source: src/controllers/administrate/maint.php:136 (managerRows)
```

## administrate.maint.activity.read

```yaml
id: administrate.maint.activity.read
title: Open a maintenance activity record
route: administrate/maint/edit
http_method: GET
ui_path: (maintenance activity ▸ open record)
auth:
  sec_id: mgr_maint
  min_level: 1
preconditions:
  - rID refers to an existing activity (blank = new from a task template)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - resolves the linked task's doc_link; adds an attachment panel; defaults maint_date to today
returns:
  success_signal: activity edit layout returned
  identifier: activity rID
errors:
  - permission denied if user lacks mgr_maint level 1
idempotency: safe (read-only)
related: [administrate.maint.activity.save]
confidence: high
source: src/controllers/administrate/maint.php:150 (edit)
```

## administrate.maint.activity.add

```yaml
id: administrate.maint.activity.add
title: Start a new maintenance activity from a task
route: administrate/maint/add
http_method: GET
ui_path: (maintenance activity ▸ Add)
auth:
  sec_id: mgr_maint
  min_level: 2
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
    - renders an add form that selects a task template to generate the record
returns:
  success_signal: add layout returned
  identifier: none yet
errors:
  - permission denied if user lacks mgr_maint level 2
idempotency: safe until saved
related: [administrate.maint.activity.save, administrate.maint.task.list]
confidence: high
source: src/controllers/administrate/maint.php:144 (add)
```

## administrate.maint.activity.save

```yaml
id: administrate.maint.activity.save
title: Create or update a maintenance activity record
route: administrate/maint/save
http_method: POST
ui_path: (maintenance activity ▸ Save)
auth:
  sec_id: mgr_maint
  min_level: 2   # create (no rID); 3 when updating an existing rID
preconditions: []
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
      notes: existing activity id; empty = create (level 2) vs update (level 3)
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
    - name: contact_id
      format: integer
      source: post
      schema_field: journal_main.rep_id
      notes: the maintainer (a user)
    - name: maint_date
      format: dateMeta
      source: post
      schema_field: journal_main.post_date
    - name: doc_link
      format: text
      source: post
    - name: notes
      format: text
      source: post
      schema_field: journal_main.notes
  fixed:
    - name: journalID
      value: 35
      notes: maintenance journal container
effects:
  db_writes:
    - table: journal_main
      op: insert/update
      notes: jID 35 record (no journal_item / GL lines)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: activity id
errors:
  - permission denied (mgr_maint level 2 create / 3 update)
idempotency: idempotent on rID
related: [administrate.maint.activity.read, administrate.maint.activity.delete]
confidence: medium   # saveDB into journal_main with no GL lines is inherited from mgrJournal — verify no downstream posting hook fires for jID 35
source: src/controllers/administrate/maint.php:167 (save)
```

## administrate.maint.activity.delete

```yaml
id: administrate.maint.activity.delete
title: Delete a maintenance activity record
route: administrate/maint/delete
http_method: GET
ui_path: (maintenance activity ▸ Trash)
auth:
  sec_id: mgr_maint
  min_level: 4
preconditions: []
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
      notes: removes the jID 35 record
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: deleteDB returns
  identifier: none
errors:
  - permission denied if user lacks mgr_maint level 4
idempotency: idempotent
related: [administrate.maint.activity.save]
confidence: high
source: src/controllers/administrate/maint.php:173 (delete)
```

---
<!-- ===================== MAINTENANCE: TASK TEMPLATES (adminMaint) ===================== -->

## administrate.maint.task.list

```yaml
id: administrate.maint.task.list
title: List maintenance task templates
route: administrate/adminMaint/manager
http_method: GET
ui_path: Settings ▸ Maintenance ▸ Tasks
auth:
  sec_id: admin
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - task templates live in common_meta (prefix maintenance); renders the tasks grid
returns:
  success_signal: task manager layout returned
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.maint.task.list.rows, administrate.maint.task.save, administrate.maint.activity.list]
confidence: high
source: src/controllers/administrate/adminMaint.php:102 (manager)
```

## administrate.maint.task.list.rows

```yaml
id: administrate.maint.task.list.rows
title: Fetch maintenance task rows (data only)
route: administrate/adminMaint/managerRows
http_method: GET
ui_path: (AJAX backing the tasks grid)
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
      notes: default title
    - name: order
      format: db_field
      source: post
      notes: ASC | DESC
    - name: store_id
      format: integer
      source: post
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows + total (common_meta, prefix maintenance)
  identifier: each row's meta id
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [administrate.maint.task.list]
confidence: high
source: src/controllers/administrate/adminMaint.php:118 (managerRows)
```

## administrate.maint.task.read

```yaml
id: administrate.maint.task.read
title: Open a maintenance task template for editing
route: administrate/adminMaint/edit
http_method: GET
ui_path: Settings ▸ Maintenance ▸ Tasks ▸ open record
auth:
  sec_id: admin
  min_level: 2   # NOTE: edit checks level 2 (add), but save checks level 3 (edit) — see Open questions
preconditions:
  - rID refers to an existing task (blank/0 = new)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: task edit layout returned
  identifier: task rID
errors:
  - permission denied if user lacks admin level 2
idempotency: safe (read-only)
related: [administrate.maint.task.save]
confidence: high
source: src/controllers/administrate/adminMaint.php:127 (edit)
```

## administrate.maint.task.save

```yaml
id: administrate.maint.task.save
title: Create or update a maintenance task template
route: administrate/adminMaint/save
http_method: POST
ui_path: Settings ▸ Maintenance ▸ Tasks ▸ Save
auth:
  sec_id: admin
  min_level: 3   # fixed level 3 for both create and update (no rID-conditional branch here)
preconditions: []
inputs:
  required:
    - name: title
      format: text
      source: post
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing task meta id; 0/blank creates a new task
    - name: ref_num
      format: filename
      source: post
      notes: task id; auto-numbered from next_maint_num when blank
    - name: frequency
      format: char
      source: post
      notes: schedule frequency key (options_frequencies)
    - name: lead_time
      format: alpha_num
      source: post
      notes: lead-time key (options_lead_times)
    - name: store_id
      format: integer
      source: post
    - name: role_id
      format: integer
      source: post
      notes: role responsible for the task
    - name: maint_date
      format: dateMeta
      source: post
      notes: next maintenance date
    - name: doc_link
      format: text
      source: post
    - name: notes
      format: text
      source: post
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: insert/update
      notes: task template under the maintenance meta prefix
  gl_journal: none
  inventory: none
  side_effects:
    - auto-numbers ref_num from next_maint_num on create
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: task _rID
errors:
  - permission denied if user lacks admin level 3
idempotency: idempotent on _rID
related: [administrate.maint.task.read, administrate.maint.task.copy, administrate.maint.task.delete, administrate.maint.activity.add]
confidence: high
source: src/controllers/administrate/adminMaint.php:137 (save)
```

## administrate.maint.task.copy

```yaml
id: administrate.maint.task.copy
title: Duplicate a maintenance task template
route: administrate/adminMaint/copy
http_method: GET
ui_path: Settings ▸ Maintenance ▸ Tasks ▸ Copy
auth:
  sec_id: admin
  min_level: 2
preconditions:
  - source task rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - stages a copy in the editor (write happens on the subsequent save)
returns:
  success_signal: edit layout pre-filled from the source task
  identifier: none yet
errors:
  - permission denied if user lacks admin level 2
idempotency: safe until saved
related: [administrate.maint.task.save]
confidence: low   # delegates to parent::copyMeta; inherited behavior
source: src/controllers/administrate/adminMaint.php:132 (copy)
```

## administrate.maint.task.delete

```yaml
id: administrate.maint.task.delete
title: Delete a maintenance task template
route: administrate/adminMaint/delete
http_method: GET
ui_path: Settings ▸ Maintenance ▸ Tasks ▸ Trash
auth:
  sec_id: admin
  min_level: 4
preconditions: []
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
      notes: removes the maintenance task record (does not delete activity records generated from it)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: deleteMeta returns
  identifier: none
errors:
  - permission denied unless administrator (admin,4)
idempotency: idempotent
related: [administrate.maint.task.save]
confidence: high
source: src/controllers/administrate/adminMaint.php:142 (delete)
```

---

## Common agent recipes

```yaml
recipe_provision_user:
  goal: Make an existing user contact an administrator
  steps:
    - action: administrate.user.list
      with: {type: u, search: <name>}        # find the user contact rID
      capture: id
    - action: administrate.roles.list.rows   # find the administrator role rID
      capture: roleID
    - action: administrate.user.update
      with: {type: u, id: $id, role_id: $roleID}
  note: >
    Requires the ACTING user to be an administrator (the hook enforces
    validateAccess('admin',4)). Assigning an administrator role grants the target the full
    permission override — treat as privileged.

recipe_new_role_least_privilege:
  goal: Create a non-admin role with a specific permission matrix
  steps:
    - action: administrate.roles.save
      with:
        title: <role name>
        administrate: 0                       # NEVER 1 unless full override is intended
        security: {<menuItemID>: <level 0..5>, ...}
  note: omitted menu items default to level 0 (no access). roles.save calls bizCacheExpClear() so changes take effect next request.

recipe_safe_restore:
  goal: Restore the database from a backup without losing the current state irrecoverably
  steps:
    - action: administrate.backup.create     # snapshot current DB first
    - action: administrate.restore.upload     # (optional) push the target file into backups/
      with: {fldFile: <file>}
    - action: administrate.restore.apply
      with: {data: <backup filename>}
  note: >
    restore.apply REPLACES every table and forces a logout. It is irreversible and not a safe
    retry target — always take a fresh backup immediately before.

recipe_purge_audit_log:
  goal: Trim audit history while keeping a copy
  steps:
    - action: administrate.audit.backup       # keep an off-table copy first
    - action: administrate.audit.purge
      with: {dateClean: <cutoff date>}
  note: purge is a permanent DELETE on/​before the cutoff.

recipe_add_custom_field:
  goal: Add a custom column to contacts or inventory and surface it on a tab
  steps:
    - action: administrate.tabs.save          # (optional) create the tab first
      with: {table: contacts, title: <tab name>}
      capture: tabID
    - action: administrate.fields.save
      with: {table: contacts, field: <col>, type: text, text_length: 64, tab: $tabID, label: <label>}
  note: fields.save runs live ALTER TABLE DDL; type/length changes can coerce or truncate existing data. Test on a copy.
```

## Open questions / verify-before-automating

- **Ungated routes (no `validateAccess` at all).** The following methods run
  with no permission check and should be treated as a security concern, not as
  intended automation surface. Documented as observed; do **not** edit the code
  from this catalog task:
  - `administrate/tools/repairTables` (`tools.php:171`) — bulk `ALTER TABLE`
    across every core table, fully unguarded.
  - `administrate/backup/uploadRestore` (`backup.php:187`) — accepts a file
    upload directly into the restore-source folder (`backups/`) that
    `saveRestore` reads from; no auth, feeding a level-4 restore.
  - `administrate/backup/mgrRows` (`backup.php:125`) — discloses backup
    filenames without a check.
  - `administrate/fixedAssets/adminSchedSave` (`fixedAssets.php:337`) —
    writes/deletes the **shared** depreciation-schedule config unguarded.
  - `administrate/fixedAssets/adminSchedLoad` (`fixedAssets.php:309`) — render
    only, but still unguarded.
  - `administrate/fixedAssets/export` (`fixedAssets.php:207`) — no local guard;
    delegates to `parent::export()`. Confirm the parent enforces access.
  - `administrate/tools/ticketMain` (`tools.php:51`) and
    `administrate/tools/ticketSave` (`tools.php:90`, sends email) — unguarded;
    ticketSave is an external email side effect.
  - `administrate/main/redir` (`main.php:107`) — unguarded but low risk
    (client-side redirect only).
- **`depValueBulk` / `faCalcBulkNext` undefined `$rID` (likely a bug).** Both
  call `validateAccess('admin', $rID?3:2)` but `$rID` is never assigned in
  scope, so it evaluates to null → the gate resolves to **level 2**, not 3
  (`fixedAssets.php:225, :245`). Administrator-override users are unaffected
  (they always get 4); for granular roles the
  bulk recalc is reachable at add-level. Verify before relying on it for access
  control. (Contrast with `getSchedValue` at `fixedAssets.php:271`, which reads
  `$rID` from GET first, so its level-3 gate is correct.)
- **`adminMaint` edit/save asymmetry.** `adminMaint::edit` checks level 2 (add)
  but `adminMaint::save` checks level 3 (edit) (`adminMaint.php:127, :137`). A
  user with add-but-not-edit could open the editor yet be refused on save.
  Confirm this is intentional before automating task-template edits.
- **`fields/managerRows` is level 4** while `fields/manager` is level 1
  (`fields.php:80, :72`). Reading the custom-field *rows* therefore requires
  delete-level access even though opening the page does not — verify when
  scripting field introspection for non-admin roles.
- **`administrate=1` is a full override.** `roles/save` with `administrate: 1`
  grants every `admin`-keyed action at level 4 for all users in that role
  (`model/functions.php:1618`). Any agent that creates or edits roles must treat
  that flag as the single most privileged input in this module.
- **FY-close hooks** (`tools.php:126/138/152`) run inside the PhreeBooks
  fiscal-year-close orchestration; `fyCloseNext` reads its cutoff from the
  `$cron` payload, not request input. Re-verify the parent flow before wiring
  the audit purge into automation (`confidence: medium`).
- **`maint/save` writes `journal_main` (jID 35)** with no GL lines via the
  inherited `mgrJournal::saveDB` (`maint.php:167`). Confirm no downstream
  posting hook fires for journal 35 before treating it as bookkeeping-neutral
  (`confidence: medium`).
- **Copy actions** (`roles/copy`, `fixedAssets/copy`, `adminMaint/copy`) all
  delegate to `parent::copyMeta`; whether they stage an unsaved editor or write
  immediately is inherited from `mgrJournal` and was not verified here
  (`confidence: low`).
