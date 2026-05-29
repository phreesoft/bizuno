---
title: Bizuno Core — Agent Action Catalog
module: bizuno
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-29
---

# Bizuno Core — Agent Action Catalog

Machine-readable actions for the `bizuno` core module — the engine room of the
product. This module holds the **dashboard / widget** machinery (the home-page
panels), the **My Business** company profile (`settings`), the **module & method
manager** that turns features on and off, the per-user **profile**, recurring
**reminders**, **attachment** listing/download/delete, and the **image manager**.
Read the [catalog schema and conventions](./README.md) first; this file assumes
the route, auth-level, and field conventions defined there.

Pages in this module: `main` (home, attachments, file download/delete, the
encryption-key prompt), `admin` (the My-Business settings form + DB tools),
`settings` (module/method install/remove), `dashboard` (per-user widget
placement), `profile` (the acting user's own preferences + password change),
`reminder` (the user's recurring to-do list), `image` (the images-folder
browser). The `install/` subdir is fresh-install / schema-migration scope and is
**out of catalog** — its tables are only referenced here as a column source.

## What this module touches — and what it does NOT

> **Key safety fact for an acting agent:** no action in the `bizuno` module
> posts a general-ledger journal or moves inventory. Everything here is
> configuration, reference data, files, or per-user UI state. `gl_journal` and
> `inventory` are **`none`** on every action below.
>
> That does **not** make the module low-risk. Several actions have
> **business-wide, irreversible side effects** that an automating agent must
> respect even though no money moves:
>
> - **`bizuno.module.delete`** (`settings/moduleDelete`) hard-`DELETE`s the
>   module's `configuration` row, **recursively `rmdir`s the module's data
>   directories**, and calls `bizCacheExpClear()` which **forces a cache rebuild
>   for every user** on their next page load. This is destructive and global.
> - **`bizuno.module.install`** (`settings/moduleInstall`) **re-imports every
>   report XML** the module ships and **recursively grants the acting user's
>   role security level 4** on every menu item the module adds. It mutates the
>   role's permission set.
> - **`bizuno.settings.save`** (`admin/adminSave` via the core settings writer)
>   changes **company-wide** locale, currency precision, mail server, session
>   timeout, and the company name/logo — every user and every printed document
>   is affected.
> - **`bizuno.image.delete`** does a **recursive folder delete** when the target
>   is a directory — no "folder must be empty" guard is enforced (the guard is
>   commented out in the code).
> - **`bizuno.attachment.delete`** / **`bizuno.file.delete`** delete files off
>   disk in the business data area.

## Data model summary

```yaml
key_tables:
  configuration:            # one row per installed module/extension
    pk: config_key          # module id (e.g. 'bizuno','contacts','inventory')
    value: config_value     # JSON blob: {settings, properties{status,path,…}}
    note: status=0 means "installed but disabled"; moduleDelete physically removes the row
  common_meta:              # business-global key/value dictionaries (NOT per user)
    pk: id
    key: meta_key           # e.g. options_frequencies, options_lead_times, bizuno_refs, dashboards, bizuno_role
    value: meta_value       # JSON
  contacts_meta:            # per-USER and per-CONTACT key/value blobs (ref_id = the contact/user id)
    pk: id
    ref_id: owning contact/user id
    key: meta_key           # e.g. user_profile, user_auth, reminder, dashboard_<menuID>, methods_<folder>
    value: meta_value       # JSON
settings_home:              # 'bizuno' configuration.settings sub-tree (the My-Business form)
  general: {password_min, max_rows, session_max, hide_filters}
  company: {id, primary_name, contact, email, address1..2, city, state, postal_code, country, telephone1..4, website, gov_id_number, logo}
  mail:    {SMTP / mailer fields from bizunoMailer->struc}
  locale:  {timezone, number_precision, number_decimal, number_thousand, number_prefix, number_suffix, number_neg_pfx, number_neg_sfx, date_short}
gl_impact: none             # nothing in this module posts to the GL or moves stock
```

> **Auth note unique to this module.** Two different gating patterns appear:
> 1. Most config/admin actions call `validateAccess('admin', <level>)` or a
>    feature key (`imgmgr`, `phreeform`, `profile`).
> 2. `profile/*` and `reminder/*` **do not call `validateAccess`** at all — they
>    guard with an `if (empty(getUserCache('profile','userID'))) return;`
>    early-exit. Effect: any authenticated user with a real `userID` may run
>    them, scoped to **their own** `ref_id`. They are self-scoped, not
>    role-gated. Each such action lists `sec_id: (self / userID present)`.
> 3. A handful of routes are **reachable with no auth check at all** — see
>    *Open questions* at the foot of this file.

---

## bizuno.home

```yaml
id: bizuno.home
title: Render the home / menu dashboard page
route: bizuno/main/bizunoHome
http_method: GET
ui_path: Home (the landing page after login, and each top-menu landing page)
auth:
  sec_id: (none — render only)
  min_level: 1
preconditions:
  - an authenticated session (logged-in user) for the 'home' menu; otherwise the 'portal' menu renders
inputs:
  required: []
  optional:
    - name: menuID
      format: text
      source: get
      notes: which menu's dashboard set to show; defaults to 'home' (logged in) or 'portal' (guest)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only page assembly; pulls company primary_name for the title
returns:
  success_signal: full page layout returned
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.dashboard.render, bizuno.dashboard.manager]
confidence: high
source: src/controllers/bizuno/main.php:43 (bizunoHome)
```

## bizuno.dashboard.page

```yaml
id: bizuno.dashboard.page
title: Render the empty dashboard column grid for a menu
route: bizuno/main/dashboard
http_method: GET
ui_path: (the dashboard container that dashboards drop into)
auth:
  sec_id: (none — render only)
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: menuID
      format: text
      source: get
      notes: menu context; defaults to 'home'
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - builds N empty column divs sized by getColumns() — the per-user column count
returns:
  success_signal: page layout with #dashboard container
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.dashboard.render]
confidence: high
source: src/controllers/bizuno/main.php:74 (dashboard)
```

## bizuno.session.refresh

```yaml
id: bizuno.session.refresh
title: Keep-alive ping that resets the session idle clock
route: bizuno/main/sessionRefresh
http_method: GET
ui_path: (background AJAX heartbeat)
auth:
  sec_id: (none — relies on session middleware)
  min_level: 1
preconditions:
  - an active session; forced sign-off after 8h of no activity
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - resets the session timeout clock by virtue of being a request (method body is empty)
returns:
  success_signal: empty 200 response
  identifier: none
errors: []
idempotency: safe (no-op body)
related: []
confidence: high
source: src/controllers/bizuno/main.php:91 (sessionRefresh)
```

## bizuno.attachment.list

```yaml
id: bizuno.attachment.list
title: List attachment files for a module record
route: bizuno/main/attachRows
http_method: GET
ui_path: (AJAX backing an attachments datagrid on a record)
auth:
  sec_id: profile
  min_level: 1
preconditions:
  - mID names a loaded module that defines an attachPath property
inputs:
  required:
    - name: mID
      format: cmd
      source: get
      notes: module id whose attachPath is read (getModuleCache(mID,'properties','attachPath'))
  optional:
    - name: prefix
      format: filename
      source: get
      notes: filename prefix that scopes the glob to one record's files
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - globs the module attach path on disk for valid 'file' extensions
returns:
  success_signal: raw JSON {total, rows}
  identifier: each row is a file entry
errors:
  - "Bad ID — mID missing"
  - permission denied if user lacks profile level 1
idempotency: safe (read-only)
related: [bizuno.file.download, bizuno.file.delete]
confidence: high
source: src/controllers/bizuno/main.php:62 (attachRows)
```

## bizuno.file.download

```yaml
id: bizuno.file.download
title: Download a stored file/attachment
route: bizuno/main/fileDownload
http_method: GET
ui_path: (download icon on attachment grids, across modules)
auth:
  sec_id: phreeform
  min_level: 1
  notes: deliberately keyed to 'phreeform' (not the owning module) so download works module-wide
preconditions:
  - the file exists under pathID
inputs:
  required:
    - name: pathID
      format: path
      source: get
      notes: directory the file lives in
    - name: fileID
      format: file
      source: get
      notes: filename; a "prefix:prefixFilename" form splits into a sub-dir + filename
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - writes a msgLog "download - <file>" audit entry, then streams the file to the browser
returns:
  success_signal: file stream (no layout return)
  identifier: none
errors:
  - permission denied if user lacks phreeform level 1
idempotency: safe (read-only)
related: [bizuno.attachment.list]
confidence: high
source: src/controllers/bizuno/main.php:146 (fileDownload)
```

## bizuno.file.delete

```yaml
id: bizuno.file.delete
title: Delete a stored file/attachment from the data area
route: bizuno/main/fileDelete
http_method: GET
ui_path: (trash icon on attachment grids)
auth:
  sec_id: secID (the value passed in 'secID'); falls back to 'admin' if blank
  min_level: 4
preconditions:
  - the file path resolves under the business data area
inputs:
  required:
    - name: data
      format: text
      source: get
      notes: the file path to delete (passed to $io->fileDelete)
  optional:
    - name: secID
      format: cmd
      source: get
      notes: security key to validate against; if empty, defaults to 'admin'
    - name: rID
      format: text
      source: get
      notes: datagrid dom id, used only to remove the row from the UI on success
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - DESTRUCTIVE — deletes the file from disk; writes a msgLog "delete - <file>" audit entry
returns:
  success_signal: eval action removing the selected datagrid row
  identifier: none
errors:
  - permission denied if user lacks level 4 on secID (or 'admin' when secID blank)
idempotency: idempotent (deleting an already-gone file is a no-op)
related: [bizuno.attachment.list, bizuno.file.download]
confidence: high
source: src/controllers/bizuno/main.php:169 (fileDelete)
```

## bizuno.encryption.form

```yaml
id: bizuno.encryption.form
title: Render the encryption-key entry popup
route: bizuno/main/encryptionForm
http_method: GET
ui_path: Quick-launch ▸ lock icon (unlock encrypted fields for the session)
auth:
  sec_id: profile
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
  side_effects: []
returns:
  success_signal: popup form with the password field
  identifier: none
errors:
  - permission denied if user lacks profile level 1
idempotency: safe (read-only)
related: [bizuno.encryption.set]
confidence: high
source: src/controllers/bizuno/main.php:99 (encryptionForm)
```

## bizuno.encryption.set

```yaml
id: bizuno.encryption.set
title: Validate and stash the data-encryption key for this session
route: bizuno/main/encryptionSet
http_method: GET
ui_path: encryption popup ▸ Save
auth:
  sec_id: profile
  min_level: 1
preconditions:
  - an encKey is configured for the business (bizuno.encKey); otherwise an error is returned
inputs:
  required:
    - name: data
      format: password
      source: get
      notes: the encryption passphrase to verify against the stored md5(salt+key) digest
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - on success stores the key in the user's session cache (setUserCache profile/admin_encrypt) — NOT persisted to DB
    - hides the lock quick-launch icon
returns:
  success_signal: eval closes winEncrypt and hides #ql_encrypt
  identifier: none
errors:
  - err_encryption_not_set: no encKey configured for the business
  - err_login_failed: key does not match (constant-time compare)
idempotency: idempotent (re-setting the same valid key has the same effect)
related: [bizuno.encryption.form]
confidence: high
source: src/controllers/bizuno/main.php:120 (encryptionSet)
```

## bizuno.settings.home

```yaml
id: bizuno.settings.home
title: Render the My-Business settings + admin-tools page
route: bizuno/admin/adminHome
http_method: GET
ui_path: My Business ▸ Settings
auth:
  sec_id: admin
  min_level: 3
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
    - runs "SHOW TABLE STATUS" to populate the DB statistics datagrid (read-only)
returns:
  success_signal: settings page with tabs (maint, schedules, extra tabs/fields, tools, statistics)
  identifier: none
errors:
  - permission denied if user lacks admin level 3
idempotency: safe (read-only)
related: [bizuno.settings.save]
confidence: high
source: src/controllers/bizuno/admin.php:257 (adminHome)
```

## bizuno.settings.save

```yaml
id: bizuno.settings.save
title: Save the business-wide settings (company / locale / mail / general)
route: bizuno/admin/adminSave
http_method: POST
ui_path: My Business ▸ Settings ▸ Save
auth:
  sec_id: (NONE — see Open questions)
  min_level: 3
  notes: >
    adminSave() itself does NOT call validateAccess. In the normal UI it is
    reached only as a post-processing hook behind the core settings save (the
    page that renders it is admin-3 gated), but the route is not independently
    guarded. Treat as admin-3 by convention; verify before exposing it.
preconditions: []
inputs:
  required: []
  optional:
    - name: company_primary_name
      format: text
      source: post
      schema_field: configuration(bizuno).settings.company.primary_name
      notes: business display name; a change should propagate to the portal title
    - name: locale_timezone
      format: text
      source: post
      schema_field: configuration(bizuno).settings.locale.timezone
    - name: "(any field from the settings structure)"
      format: per-structure
      source: post
      notes: general.*, company.*, mail.*, locale.* — written by readModuleSettings()
  fixed: []
effects:
  db_writes:
    - table: configuration
      op: update
      notes: config_key='bizuno' config_value JSON 'settings' sub-tree rewritten by readModuleSettings()
  gl_journal: none
  inventory: none
  side_effects:
    - BUSINESS-WIDE — changes locale/number formatting, mail server, session timeout, company name & logo for every user and every document
returns:
  success_signal: settings persisted (no explicit success layout in adminSave; the surrounding save emits the message)
  identifier: none
errors: []
idempotency: idempotent — re-saving the same field values yields the same config blob
related: [bizuno.settings.home, bizuno.session.loadBrowser]
confidence: medium   # adminSave is a thin hook over readModuleSettings(); the full field set lives in settingsStructure()
source: src/controllers/bizuno/admin.php:332 (adminSave), :103 (settingsStructure)
```

## bizuno.session.loadBrowser

```yaml
id: bizuno.session.loadBrowser
title: Push static reference data (countries, currency, locale, dictionary) to the browser
route: bizuno/admin/loadBrowserSession
http_method: GET
ui_path: (background load on page boot to speed up later AJAX)
auth:
  sec_id: (none — render only)
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
    - reads locale DB and module caches; returns a JSON content blob (read-only)
returns:
  success_signal: JSON content {version, calendar, country, currency, language, timezone, locale, dictionary, countries, regions}
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.settings.save]
confidence: high
source: src/controllers/bizuno/admin.php:184 (loadBrowserSession)
```

## bizuno.module.install

```yaml
id: bizuno.module.install
title: Install (or re-enable) a module / extension
route: bizuno/settings/moduleInstall
http_method: GET
ui_path: My Business ▸ Modules ▸ (install button on an available module)
auth:
  sec_id: admin
  min_level: 3
preconditions:
  - the module folder exists and contains admin.php (defining <module>Admin)
inputs:
  required:
    - name: rID
      format: cmd
      source: get
      notes: module id to install (becomes configuration.config_key)
    - name: data
      format: filename
      source: get
      notes: relative path to the module folder (resolved via bizAutoLoadMap)
  optional: []
  fixed:
    - name: status
      value: 1
      notes: forced — the module is marked enabled
effects:
  db_writes:
    - table: configuration
      op: insert (new) or update (re-enable a status=0 row)
      notes: config_key=module, config_value=JSON {settings, properties}
    - table: common_meta
      op: update
      notes: bizuno_role security blob — see side_effects (grants menu access)
  gl_journal: none
  inventory: none
  side_effects:
    - RE-IMPORTS every report XML the module ships (adminAddRpts → phreeformImport) — can overwrite/duplicate report definitions
    - RECURSIVELY GRANTS security level 4 to the acting user's role on every menu item the module adds (setSecurity → addSecurity)
    - calls the module's own install() hook if present
    - if the module was already enabled, returns a 'caution' and makes no change
returns:
  success_signal: href redirect back to bizuno/settings/manager with rID=module
  identifier: module id
errors:
  - "unknown module — no name/path passed"
  - "admin.php not found at the module path"
  - err_install_module_exists: module already enabled (caution, no change)
  - permission denied if user lacks admin level 3
idempotency: >
  partially — re-running on an already-enabled module is a no-op caution; on a
  disabled (status=0) row it re-enables. But it RE-IMPORTS reports and RE-GRANTS
  role security every time, so it is NOT side-effect-free to retry.
related: [bizuno.module.delete, bizuno.method.install, bizuno.module.reports.add]
confidence: high
source: src/controllers/bizuno/settings.php:47 (moduleInstall), :335 (adminAddRpts), :359 (setSecurity)
```

## bizuno.module.delete

```yaml
id: bizuno.module.delete
title: Remove / disable a module (DESTRUCTIVE)
route: bizuno/settings/moduleDelete
http_method: GET
ui_path: My Business ▸ Modules ▸ (remove button on an installed module)
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - the module is installed
inputs:
  required:
    - name: rID
      format: text
      source: get
      notes: module id to remove
  optional: []
  fixed: []
effects:
  db_writes:
    - table: configuration
      op: delete
      notes: physically DELETEs WHERE config_key=module (not just status=0, despite the method's docblock)
  gl_journal: none
  inventory: none
  side_effects:
    - DESTRUCTIVE — recursively rmdir()s the module's declared data directories (adminDelDirs over dirlist)
    - calls the module's remove() hook if present (may delete more)
    - bizCacheExpClear() — FORCES a cache/menu/permission rebuild for EVERY user on next page load
returns:
  success_signal: href redirect back to bizuno/settings/manager
  identifier: module id
errors:
  - "error removing module: <module> — the module's remove() hook returned false"
  - permission denied if user lacks admin level 4
idempotency: NOT safe to assume — the data-dir removal and cache flush are global and irreversible
related: [bizuno.module.install]
confidence: high
source: src/controllers/bizuno/settings.php:99 (moduleDelete), :318 (adminDelDirs)
```

## bizuno.methods.list

```yaml
id: bizuno.methods.list
title: Render the method manager for a module folder (e.g. payment, shipping methods)
route: bizuno/settings/adminMethods
http_method: GET
ui_path: My Business ▸ (module) ▸ Methods tab
auth:
  sec_id: (NONE — see Open questions)
  min_level: 1
  notes: adminMethods() does NOT call validateAccess; the install/remove sub-actions it links to ARE admin-gated
preconditions:
  - methods_<folder> meta exists (created at module install)
inputs:
  required:
    - name: module
      format: db_field
      source: get
    - name: folder
      format: db_field
      source: get
      notes: method-group folder (the methods_<folder> meta key)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - autoloads each method class to render its settings form; a missing class is flagged and dropped from the cache (a write side effect of a read screen)
returns:
  success_signal: HTML table of available/enabled methods with enable/disable/settings controls
  identifier: none
errors: []
idempotency: safe (read-mostly; may prune stale cache entries)
related: [bizuno.method.install, bizuno.method.remove, bizuno.method.settings.save]
confidence: high
source: src/controllers/bizuno/settings.php:129 (adminMethods)
```

## bizuno.method.install

```yaml
id: bizuno.method.install
title: Enable a method within a module (payment/shipping/etc. provider)
route: bizuno/settings/methodInstall
http_method: GET
ui_path: My Business ▸ (module) ▸ Methods ▸ Enable
auth:
  sec_id: admin
  min_level: 3
preconditions:
  - the method class exists under methods_<path> meta
inputs:
  required:
    - name: path
      format: text
      source: get
      notes: method-group folder (methods_<path> meta key)
    - name: method
      format: text
      source: get
      notes: method class/id to enable
  optional: []
  fixed:
    - name: status
      value: 1
      notes: forced — method marked enabled
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: methods_<path> meta — sets [method].status=1 and seeds [method].settings
  gl_journal: none
  inventory: none
  side_effects:
    - calls the method's install() hook if present
    - refreshes the methods tab panel
returns:
  success_signal: msgStack 'success' = msg_settings_saved
  identifier: method id
errors:
  - "Bad data installing method! — path or method missing"
  - permission denied if user lacks admin level 3
idempotency: idempotent — re-enabling an already-enabled method merges the same status/settings
related: [bizuno.method.remove, bizuno.method.settings.save, bizuno.methods.list]
confidence: high
source: src/controllers/bizuno/settings.php:193 (methodInstall)
```

## bizuno.method.settings.save

```yaml
id: bizuno.method.settings.save
title: Save the per-method configuration
route: bizuno/settings/methodSettingsSave
http_method: POST
ui_path: My Business ▸ (module) ▸ Methods ▸ (gear) ▸ Save
auth:
  sec_id: admin
  min_level: 3
preconditions:
  - the method is enabled
inputs:
  required:
    - name: type
      format: text
      source: get
      notes: method-group folder (methods_<type> meta key)
    - name: method
      format: text
      source: get
  optional:
    - name: "<method>_<setting>"
      format: per-structure
      source: post
      notes: one field per the method's settingsStructure(); collected by settingsSaveMethod()
  fixed: []
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: methods_<type> meta — replaces [method].settings
  gl_journal: none
  inventory: none
  side_effects:
    - calls the method's settingSave() hook if present (may do provider-specific work)
returns:
  success_signal: msgStack 'success' = msg_settings_saved; eval hides the settings panel
  identifier: none
errors:
  - "Not all the information was provided! — type or method missing"
  - permission denied if user lacks admin level 3
idempotency: idempotent — re-saving the same values yields the same settings blob
related: [bizuno.method.install, bizuno.methods.list]
confidence: high
source: src/controllers/bizuno/settings.php:221 (methodSettingsSave)
```

## bizuno.method.remove

```yaml
id: bizuno.method.remove
title: Disable a method within a module
route: bizuno/settings/methodRemove
http_method: GET
ui_path: My Business ▸ (module) ▸ Methods ▸ Disable
auth:
  sec_id: admin
  min_level: 4
preconditions:
  - the method exists in methods_<type> meta
inputs:
  required:
    - name: type
      format: text
      source: get
      notes: method-group folder (methods_<type> meta key)
    - name: method
      format: text
      source: get
  optional: []
  fixed:
    - name: status
      value: 0
      notes: forced — method marked disabled and its settings blanked
effects:
  db_writes:
    - table: common_meta
      op: update
      notes: methods_<type> meta — sets [method].status=0 and clears [method].settings
  gl_journal: none
  inventory: none
  side_effects:
    - calls the method's remove() hook if present
    - the method row is NOT deleted from meta, only disabled (settings are cleared, so re-enabling resets config)
returns:
  success_signal: eval refreshes the methods tab panel
  identifier: none
errors:
  - "Bad method data provided! — type missing"
  - permission denied if user lacks admin level 4
idempotency: idempotent (disabling an already-disabled method is a no-op)
related: [bizuno.method.install]
confidence: high
source: src/controllers/bizuno/settings.php:248 (methodRemove)
```

## bizuno.module.reports.add

```yaml
id: bizuno.module.reports.add
title: Import a module's bundled PhreeForm report XMLs
route: (not a standalone route — invoked by moduleInstall)
http_method: n/a
ui_path: (runs during module install)
auth:
  sec_id: admin
  min_level: 3
  notes: only reachable via bizuno.module.install, which is admin-3 gated; the method itself is public but takes a path argument and is not wired to a route
preconditions:
  - a locale/<lang>/reports/ (or locale/en_US/reports/) folder of .xml exists under the module
inputs:
  required:
    - name: path
      format: (php arg)
      source: n/a
      notes: module root path; passed by moduleInstall, not from the request
  optional: []
  fixed: []
effects:
  db_writes:
    - table: phreeform (report definitions)
      op: insert/update
      notes: each .xml imported via phreeformImport()
  gl_journal: none
  inventory: none
  side_effects:
    - re-importing on every install can duplicate or overwrite report rows
returns:
  success_signal: boolean true on full success, false if any import failed
  identifier: none
errors:
  - "import failure on a malformed report XML (returns false)"
idempotency: NOT idempotent in isolation — re-import behavior depends on phreeformImport's dedup
related: [bizuno.module.install]
confidence: medium   # behavior depends on phreeformImport() dedup logic in the phreeform module
source: src/controllers/bizuno/settings.php:335 (adminAddRpts)
```

## bizuno.dashboard.manager

```yaml
id: bizuno.dashboard.manager
title: Render the "choose which dashboards show on this menu" editor
route: bizuno/dashboard/manager
http_method: GET
ui_path: Home ▸ (gear) ▸ Edit dashboards
auth:
  sec_id: (self — administrate role flag controls "all dashboards" visibility)
  min_level: 1
  notes: no validateAccess; getUserCache('role','administrate') only widens which dashboards are listed
preconditions: []
inputs:
  required: []
  optional:
    - name: menuID
      format: text
      source: get
      notes: which menu's dashboard set to edit; defaults to 'home'
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - reads the global 'dashboards' meta and the user's current dashboard_<menuID> selection (read-only)
returns:
  success_signal: checkbox grid of available dashboards grouped by category
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.dashboard.save, bizuno.dashboard.render]
confidence: high
source: src/controllers/bizuno/dashboard.php:48 (manager)
```

## bizuno.dashboard.render

```yaml
id: bizuno.dashboard.render
title: List the dashboards (and their layout state) for a menu
route: bizuno/dashboard/render
http_method: GET
ui_path: (AJAX that populates a menu's dashboard panels)
auth:
  sec_id: (self — userID present; guests get the default 'portal' set)
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: menuID
      format: text
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: content {Dashboard:[...], State:"col,col:col"}
  identifier: each dashboard entry has an id + href to its settings render
errors: []
idempotency: safe (read-only)
related: [bizuno.dashboard.settings, bizuno.dashboard.organize]
confidence: high
source: src/controllers/bizuno/dashboard.php:108 (render), :234 (listDashboards)
```

## bizuno.dashboard.settings

```yaml
id: bizuno.dashboard.settings
title: Render a single dashboard widget's contents
route: bizuno/dashboard/settings
http_method: GET
ui_path: (AJAX per-panel render — the href on each dashboard)
auth:
  sec_id: (self — userID present; dashboard's own validateDashboardSecurity governs visibility)
  min_level: 1
preconditions:
  - dashID names a dashboard available to the user
inputs:
  required:
    - name: dashID
      format: db_field
      source: get
    - name: menu
      format: db_field
      source: get
      notes: menu context; 'portal' uses only global options (no per-user merge)
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - merges global widget options with the user's saved opts (read-only); delegates to the dashboard's render()
returns:
  success_signal: rendered widget layout
  identifier: none
errors:
  - "ERROR: Dashboard <id> NOT FOUND!"
idempotency: safe (read-only)
related: [bizuno.dashboard.render, bizuno.dashboard.attr]
confidence: high
source: src/controllers/bizuno/dashboard.php:166 (settings)
```

## bizuno.dashboard.organize

```yaml
id: bizuno.dashboard.organize
title: Persist a dashboard's column/row placement after drag-and-drop
route: bizuno/dashboard/organize
http_method: GET
ui_path: Home ▸ (drag a dashboard panel)
auth:
  sec_id: (self — writes to the acting user's own meta)
  min_level: 1
  notes: no validateAccess; scoped to getUserCache('profile','userID')
preconditions: []
inputs:
  required:
    - name: menuID
      format: text
      source: get
    - name: state
      format: text
      source: get
      notes: "col0,col0:col1,col1" — colon-separated columns, comma-separated dashIDs in row order
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: update
      notes: dashboard_<menuID> meta for the current user — sets per-dash col/row
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: meta written (no explicit content)
  identifier: none
errors: []
idempotency: idempotent — re-applying the same state yields the same layout
related: [bizuno.dashboard.save, bizuno.dashboard.render]
confidence: high
source: src/controllers/bizuno/dashboard.php:117 (organize)
```

## bizuno.dashboard.save

```yaml
id: bizuno.dashboard.save
title: Save which dashboards are active on a menu (add/remove from the user's set)
route: bizuno/dashboard/save
http_method: POST
ui_path: Home ▸ Edit dashboards ▸ Save
auth:
  sec_id: (self — writes the acting user's own meta)
  min_level: 1
preconditions: []
inputs:
  required:
    - name: menuID
      format: db_field
      source: get
    - name: dashID
      format: array
      source: post
      notes: list of dashboard ids to keep active; ids not in the list are removed from the user's set
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: insert/update
      notes: dashboard_<menuID> meta for the current user — adds new dashIDs (col0,row-1), removes deselected ones
  gl_journal: none
  inventory: none
  side_effects:
    - newly added dashboards default to col 0, row -1 (top); existing placements are preserved
returns:
  success_signal: href redirect back to bizuno/main/bizunoHome
  identifier: none
errors: []
idempotency: idempotent — the resulting active set equals the posted dashID list
related: [bizuno.dashboard.manager, bizuno.dashboard.organize, bizuno.dashboard.delete]
confidence: high
source: src/controllers/bizuno/dashboard.php:142 (save)
```

## bizuno.dashboard.attr

```yaml
id: bizuno.dashboard.attr
title: Save a single dashboard widget's user options
route: bizuno/dashboard/attr
http_method: POST
ui_path: Home ▸ (dashboard panel) ▸ (edit gear) ▸ Save
auth:
  sec_id: (self — writes the acting user's own meta)
  min_level: 1
preconditions:
  - dashID names an available dashboard
inputs:
  required:
    - name: menuID
      format: db_field
      source: get
    - name: dashID
      format: db_field
      source: get
  optional:
    - name: "<dashID><settingKey>"
      format: per-widget (each struc entry's 'clean')
      source: post
      notes: one field per non-admin entry in the widget's struc; admin-flagged settings are skipped
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: update
      notes: dashboard_<menuID> meta — sets [dashID].opts[...] for the current user
  gl_journal: none
  inventory: none
  side_effects:
    - calls the widget's save() hook if present (some widgets do special processing, e.g. favorite reports)
    - refreshes the panel
returns:
  success_signal: eval refreshes the #<dashID> panel
  identifier: none
errors:
  - "illegal_access: dashboard:attr — dashboard not found"
idempotency: idempotent — re-saving the same options yields the same opts blob
related: [bizuno.dashboard.settings]
confidence: high
source: src/controllers/bizuno/dashboard.php:205 (attr)
```

## bizuno.dashboard.delete

```yaml
id: bizuno.dashboard.delete
title: Remove a dashboard from the user's menu
route: bizuno/dashboard/delete
http_method: GET
ui_path: Home ▸ (dashboard panel) ▸ close (x)
auth:
  sec_id: (self — writes the acting user's own meta)
  min_level: 1
preconditions:
  - dashboardID names a known dashboard
inputs:
  required:
    - name: menuID
      format: text
      source: get
    - name: dashboardID
      format: text
      source: get
      notes: dashboard id to remove from this menu
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: update
      notes: dashboard_<menuID> meta — unsets the dashboard for the current user
  gl_journal: none
  inventory: none
  side_effects:
    - calls the widget's remove($menuID) hook if present
returns:
  success_signal: meta written (no explicit content)
  identifier: none
errors:
  - "ERROR: Dashboard delete failed! — dashboard not found"
idempotency: idempotent (removing an already-removed dashboard is a no-op)
related: [bizuno.dashboard.save]
confidence: high
source: src/controllers/bizuno/dashboard.php:188 (delete)
```

## bizuno.profile.edit

```yaml
id: bizuno.profile.edit
title: Render the acting user's profile editor
route: bizuno/profile/edit
http_method: GET
ui_path: (top bar) ▸ My Profile
auth:
  sec_id: (self / userID present — no validateAccess; early-return if no userID)
  min_level: 1
preconditions:
  - an authenticated user (non-empty profile userID)
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - loads the user's user_profile meta; adds a Reminders tab; hides new/copy toolbar icons
returns:
  success_signal: profile form (general + password panels + mail settings)
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.profile.save, bizuno.reminder.list]
confidence: high
source: src/controllers/bizuno/profile.php:75 (edit)
```

## bizuno.profile.save

```yaml
id: bizuno.profile.save
title: Save the acting user's profile (and optionally change their password)
route: bizuno/profile/save
http_method: POST
ui_path: My Profile ▸ Save
auth:
  sec_id: (self / userID present — no validateAccess; always scoped to the acting user)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required: []
  optional:
    - name: language
      format: db_field
      source: post
      notes: UI language (en_US, …)
    - name: def_periods
      format: db_field
      source: post
    - name: grid_rows
      format: integer
      source: post
      notes: default datagrid rows per page
    - name: icons
      format: alpha_num
      source: post
    - name: theme
      format: alpha_num
      source: post
    - name: user_pin
      format: integer
      source: post
      notes: numeric PIN; only updated if a value is supplied (blank keeps the existing PIN)
    - name: bizPassCur
      format: password
      source: post
      notes: current password — required IF changing the password
    - name: bizPass0
      format: password
      source: post
      notes: new password — must be >= 8 chars
    - name: bizPass1
      format: password
      source: post
      notes: confirm new password — must equal bizPass0
    - name: "(mail settings fields)"
      format: per-structure
      source: post
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: update
      notes: user_profile meta for the acting user (theme/language/grid prefs/PIN/mail)
    - table: contacts_meta
      op: update
      notes: user_auth meta — ONLY when a valid password change is submitted (hashed via encryptPassword)
  gl_journal: none
  inventory: none
  side_effects:
    - password fields are NEVER written into user_profile — handled separately into user_auth then unset
    - dbSetBizunoUsers() reloads the user cache so the new prefs take effect
    - a failed/partial password change adds a message but DOES NOT abort the profile save (theme etc. still saved)
returns:
  success_signal: msgStack 'success' = msg_record_saved (and msg_password_changed if applicable)
  identifier: none
errors:
  - err_password_fields_required: partial password submission
  - err_password_mismatch: new != confirm
  - err_password_short: new < 8 chars
  - err_password_current_wrong: current password failed verification
idempotency: >
  profile prefs are idempotent. Password change is NOT a safe blind retry —
  a second run with the same 'current' value fails (the password already changed).
related: [bizuno.profile.edit, bizuno.profile.update]
confidence: high
source: src/controllers/bizuno/profile.php:91 (save), :133 (savePasswordChange)
```

## bizuno.profile.update

```yaml
id: bizuno.profile.update
title: Persist a transient profile UI tweak (menu size)
route: bizuno/profile/update
http_method: GET
ui_path: (AJAX when the user collapses/expands the menu)
auth:
  sec_id: (self / userID present)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required: []
  optional:
    - name: menuSize
      format: cmd
      source: get
      notes: stored as user_profile.menuSize; only written when non-empty
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: update
      notes: user_profile meta — menuSize only
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: meta written (no explicit content)
  identifier: none
errors: []
idempotency: idempotent
related: [bizuno.profile.save]
confidence: high
source: src/controllers/bizuno/profile.php:174 (update)
```

## bizuno.reminder.list

```yaml
id: bizuno.reminder.list
title: Render the acting user's reminders datagrid
route: bizuno/reminder/manager
http_method: GET
ui_path: My Profile ▸ Reminders
auth:
  sec_id: profile (declared on the class; gated in practice by the userID early-return)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required: []
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - frequency labels sourced from common_meta options_frequencies
returns:
  success_signal: reminders datagrid layout
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.reminder.rows, bizuno.reminder.save]
confidence: high
source: src/controllers/bizuno/reminder.php:81 (manager)
```

## bizuno.reminder.rows

```yaml
id: bizuno.reminder.rows
title: Fetch the acting user's reminder rows (data only)
route: bizuno/reminder/managerRows
http_method: GET
ui_path: (AJAX backing the reminders grid)
auth:
  sec_id: (self / userID present)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required: []
  optional:
    - name: sort
      format: cmd
      source: post
      notes: default 'title'
    - name: order
      format: db_field
      source: post
      notes: ASC | DESC, default ASC
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows of reminder meta for the user
  identifier: each row carries its meta _rID
errors: []
idempotency: safe (read-only)
related: [bizuno.reminder.list]
confidence: high
source: src/controllers/bizuno/reminder.php:88 (managerRows)
```

## bizuno.reminder.edit

```yaml
id: bizuno.reminder.edit
title: Render the add/edit form for one reminder
route: bizuno/reminder/edit
http_method: GET
ui_path: My Profile ▸ Reminders ▸ New / open
auth:
  sec_id: (self / userID present)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required: []
  optional:
    - name: rID
      format: integer
      source: get
      notes: reminder meta id; 0/absent for a new reminder
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: reminder edit form (title, frequency, dateStart, dateNext)
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.reminder.save]
confidence: high
source: src/controllers/bizuno/reminder.php:96 (edit)
```

## bizuno.reminder.save

```yaml
id: bizuno.reminder.save
title: Create or update one of the acting user's reminders
route: bizuno/reminder/save
http_method: POST
ui_path: My Profile ▸ Reminders ▸ Save
auth:
  sec_id: (self / userID present — scoped to the acting user's meta)
  min_level: 1
preconditions:
  - an authenticated user
inputs:
  required:
    - name: title
      format: text
      source: post
      notes: reminder title
  optional:
    - name: _rID
      format: integer
      source: post
      notes: existing reminder meta id; 0/absent inserts a new one
    - name: recur
      format: char
      source: post
      notes: frequency key (d/w/b/h/m/q/y/3/z); default 'm'
    - name: dateStart
      format: dateMeta
      source: post
    - name: dateNext
      format: dateMeta
      source: post
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: insert/update
      notes: reminder meta keyed to the acting user (saveMeta)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack success (via mgrJournal saveMeta)
  identifier: reminder meta _rID
errors: []
idempotency: >
  upsert — supply _rID to update a specific reminder; omit it to add a new one.
related: [bizuno.reminder.edit, bizuno.reminder.delete]
confidence: high
source: src/controllers/bizuno/reminder.php:102 (save)
```

## bizuno.reminder.delete

```yaml
id: bizuno.reminder.delete
title: Delete one of the acting user's reminders
route: bizuno/reminder/delete
http_method: GET
ui_path: My Profile ▸ Reminders ▸ (trash)
auth:
  sec_id: (self / userID present)
  min_level: 1
  notes: class-declared secID='profile'; the practical guard is the userID early-return and self-scoped meta
preconditions:
  - an authenticated user
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: reminder meta id to delete
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: delete
      notes: removes the reminder meta entry for the acting user (deleteMeta)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: grid row removed
  identifier: none
errors: []
idempotency: idempotent (deleting an already-gone reminder is a no-op)
related: [bizuno.reminder.save]
confidence: high
source: src/controllers/bizuno/reminder.php:107 (delete)
```

## bizuno.image.manager

```yaml
id: bizuno.image.manager
title: Open the image-folder browser (and perform folder/upload actions)
route: bizuno/image/manager
http_method: GET
ui_path: My Business ▸ Tools ▸ Image Manager (also as a field picker popup)
auth:
  sec_id: imgmgr
  min_level: 1
preconditions: []
inputs:
  required: []
  optional:
    - name: dom
      format: alpha_num
      source: get
      notes: 'page' for full page, otherwise a popup
    - name: imgMgrPath
      format: path_rel
      source: get
      notes: folder relative to images/ root; default 'images/'
    - name: imgSearch
      format: text
      source: get
    - name: imgTarget
      format: text
      source: get
      notes: form field id the chosen image path is written back to
    - name: imgAction
      format: text
      source: get
      notes: >
        parent | refresh | search | upload | "newdir:<name>". 'upload' saves
        imgFile into images/<path>/; 'newdir' creates a folder (writes an
        index.php stub). These mutate the filesystem.
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - imgAction=upload writes the uploaded image into images/<path>/ (filesystem write)
    - imgAction=newdir:<name> creates a new folder with an index.php guard (filesystem write)
    - auto-creates the target folder if missing; remembers last path in the user's imgMgr cache
returns:
  success_signal: image grid HTML (or upload-save result)
  identifier: none
errors:
  - "Folder name is required! — newdir with empty name"
  - permission denied if user lacks imgmgr level 1
idempotency: >
  read/navigate is safe. upload is NOT idempotent (re-upload duplicates/overwrites
  by filename). newdir is idempotent on an existing folder name.
related: [bizuno.image.delete, bizuno.image.view]
confidence: high
source: src/controllers/bizuno/image.php:38 (manager)
```

## bizuno.image.delete

```yaml
id: bizuno.image.delete
title: Delete an image file OR an entire image folder (recursive)
route: bizuno/image/delete
http_method: GET
ui_path: Image Manager ▸ (trash on a thumbnail/folder)
auth:
  sec_id: imgmgr
  min_level: 4
preconditions:
  - the target exists under images/<path>
inputs:
  required:
    - name: path
      format: path_rel
      source: get
      notes: folder relative to images/ root
    - name: fn
      format: text
      source: get
      notes: file or folder name within path
  optional:
    - name: target
      format: text
      source: get
      notes: picker field id (used only to rebuild the refresh href)
    - name: search
      format: text
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - DESTRUCTIVE — if the target is a directory it is deleted RECURSIVELY ($io->folderDelete); the "folder must be empty" guard is commented out in the code
    - if the target is a file, it is deleted ($io->fileDelete)
returns:
  success_signal: eval refreshes the image manager window
  identifier: none
errors:
  - permission denied if user lacks imgmgr level 4
idempotency: idempotent (deleting an already-gone path is a no-op) — but the recursion makes a wrong path costly
related: [bizuno.image.manager]
confidence: high
source: src/controllers/bizuno/image.php:186 (delete)
```

## bizuno.image.view

```yaml
id: bizuno.image.view
title: Show a single image full-size in a popup
route: bizuno/image/view
http_method: GET
ui_path: (click an image thumbnail / "current image" preview)
auth:
  sec_id: (NONE — see Open questions)
  min_level: 1
  notes: view() does NOT call validateAccess; it builds an <img> src from rID(bID) + data(path)
preconditions: []
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: business id (bID) used in the file-serving URL
    - name: data
      format: path
      source: get
      notes: image path under that business's images/ folder
  optional: []
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: popup containing the image
  identifier: none
errors: []
idempotency: safe (read-only)
related: [bizuno.image.manager]
confidence: high
source: src/controllers/bizuno/image.php:209 (view)
```

---

## Common agent recipes

```yaml
recipe_set_company_locale:
  goal: Set the business name, timezone, and number precision
  steps:
    - action: bizuno.settings.home          # load the form (admin level 3 required)
    - action: bizuno.settings.save
      with: {company_primary_name, locale_timezone, locale_number_precision}
  note: BUSINESS-WIDE change — affects every user and every printed document. Not for casual automation.

recipe_enable_payment_method:
  goal: Turn on a payment/shipping provider and configure it
  steps:
    - action: bizuno.methods.list
      with: {module: payment, folder: methods}   # discover the method id + status
    - action: bizuno.method.install
      with: {path: methods, method: <providerId>}
    - action: bizuno.method.settings.save
      with: {type: methods, method: <providerId>, "<providerId>_<setting>": <value>}

recipe_curate_home_dashboards:
  goal: Choose and arrange the home-page widgets for the acting user
  steps:
    - action: bizuno.dashboard.manager  with: {menuID: home}     # see what's available
    - action: bizuno.dashboard.save     with: {menuID: home, dashID: [<id1>, <id2>]}
    - action: bizuno.dashboard.organize with: {menuID: home, state: "<id1>:<id2>"}
  note: all self-scoped to the acting user — safe to automate per-user.

recipe_change_my_password:
  goal: Rotate the acting user's password without an admin
  steps:
    - action: bizuno.profile.save
      with: {bizPassCur: <old>, bizPass0: <new>, bizPass1: <new>}
  note: NOT a safe blind retry — a second attempt with the same 'old' value fails because the password already changed.

recipe_install_then_remove_module:
  goal: Add an optional module
  steps:
    - action: bizuno.module.install with: {rID: <module>, data: <relPath>}
  note: >
    install RE-IMPORTS report XMLs and RE-GRANTS the acting role level-4 menu
    security every run. DO NOT loop it. NEVER pair with bizuno.module.delete to
    "reset" — delete recursively rmdir()s the module's data dirs and flushes the
    cache for ALL users.
```

## Open questions / verify-before-automating

- **Ungated routes.** Several public methods do **not** call `validateAccess`
  and rely on softer guards or none at all. Confirm your deployment's portal
  middleware before exposing them to an agent:
  - `bizuno/admin/adminSave` (`admin.php:332`) — no `validateAccess`; runs the
    full business-settings writer. Reached as a hook behind the admin-3 settings
    page in the UI, but the route itself is unguarded.
  - `bizuno/settings/adminMethods` (`settings.php:129`) — renders the method
    manager with no auth check (the enable/disable/save sub-actions it links to
    *are* admin-gated). It also prunes stale cache entries as a side effect of a
    "read" screen.
  - `bizuno/image/view` (`image.php:209`) — no auth check; builds an image URL
    from a caller-supplied `rID` (business id) and `data` (path). Verify it
    cannot be coerced to reference another business's files.
  - `bizuno/dashboard/*` self-scoped methods (`organize`, `save`, `attr`,
    `delete`, `render`, `settings`) — no `validateAccess`; they read/write the
    **acting user's own** `dashboard_<menuID>` meta. The `administrate` role flag
    only widens which dashboards are *listed*, it is not an access gate.
- **profile / reminder gating.** Every `profile/*` and `reminder/*` method gates
  via `if (empty(getUserCache('profile','userID'))) return;` rather than
  `validateAccess`. They are self-scoped to the acting user's `ref_id`. The
  `reminder` class declares `secID='profile'` but does not call
  `validateAccess('profile', …)` directly — don't assume role-level enforcement.
- **`bizuno.module.delete` docblock is wrong.** The comment says "Modules are
  not deleted, just status changed to 0," but the code physically
  `DELETE`s the `configuration` row, `rmdir`s the module's data directories, and
  flushes every user's cache. Treat it as fully destructive.
- **Report re-import (`bizuno.module.reports.add`, `settings.php:335`).** Whether
  re-importing on every install duplicates or overwrites report rows depends on
  `phreeformImport()` in the phreeform module — verify before automating repeated
  installs (`confidence: medium`).
- **`bizuno.settings.save` field set.** `adminSave()` is a thin hook over
  `readModuleSettings()`; the authoritative list of writable fields is
  `settingsStructure()` (`admin.php:103`). Re-derive the field names from there
  for any non-trivial automated config change (`confidence: medium`).
