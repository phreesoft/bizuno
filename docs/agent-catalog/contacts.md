---
title: Contacts — Agent Action Catalog
module: contacts
category: Agent Catalog
status: draft
audience: [developer]
last-updated: 2026-05-28
---

# Contacts — Agent Action Catalog

Machine-readable actions for the `contacts` module — the universal entity in
Bizuno. Customers, vendors, employees, branches, CRM leads, and projects are
all rows in the same `contacts` table, distinguished by independent role flags
(`ctype_*`). Read the [catalog schema and conventions](./README.md) first; this
file assumes the route, auth-level, and field conventions defined there.

Pages in this module: `main` (the entity CRUD), `address` (multi-address
sub-records), `api` (bulk CSV import/export), `history` (transaction history),
`projects`, `promos`, `tools` (merge/charts), `admin` (module settings).

## Data model summary

```yaml
table: contacts            # one row per contact, all roles
key_natural: short_name    # human "Contact ID", unique, max 32 chars
key_surrogate: id          # integer rID used everywhere in routes
role_flags:                # non-exclusive ENUM('0','1') booleans
  ctype_c: customer
  ctype_v: vendor
  ctype_b: branch
  ctype_i: crm_contact
  ctype_e: employee
  ctype_j: project
  ctype_u: user
type_param:                # single char used by routes and the import feed
  c: customer
  v: vendor
  b: branch
  i: crm_contact
  e: employee
  j: project
related_tables:
  contacts_meta: addresses (address_b billing, address_s shipping, address_i CRM), notes, terms — keyed by ref_id
  contacts_log:  CRM activity log entries — keyed by contact_id
  address_book:  legacy/primary address fields stored on the contacts row itself
gl_impact: none            # creating/editing a contact never posts to the GL
```

> **Key safety fact for an acting agent:** no action in this module posts a
> general-ledger journal or moves inventory. Contacts are reference data. The
> only destructive action is `delete`, and it is *blocked* by the code if any
> journal transaction references the contact. So this module is safe to
> automate without accounting consequences.

---

## contacts.list

```yaml
id: contacts.list
title: List / query contacts of a given type
route: contacts/main/manager
http_method: GET
ui_path: Contacts ▸ Customers (or Vendors / Employees / …)
auth:
  sec_id: contacts (manager)
  min_level: 1
preconditions:
  - contacts module enabled for the business
inputs:
  required:
    - name: type
      format: char
      source: get
      notes: c|v|b|i|e|j — selects which role list to return. Defaults to the page's type.
  optional:
    - name: search
      format: text
      source: get
      notes: free-text filter applied across name/ID/contact fields
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - read-only; renders an EasyUI datagrid. Use contacts.list.rows for raw rows.
returns:
  success_signal: datagrid layout returned
  identifier: none
errors: []
idempotency: safe (read-only)
related: [contacts.list.rows, contacts.read]
confidence: high
source: src/controllers/contacts/main.php:75 (manager)
```

## contacts.list.rows

```yaml
id: contacts.list.rows
title: Fetch contact rows (data only, for programmatic consumption)
route: contacts/main/managerRows
http_method: GET
ui_path: (AJAX backing the datagrid)
auth:
  sec_id: contacts (manager)
  min_level: 1
preconditions: []
inputs:
  required:
    - name: type
      format: char
      source: get
      notes: c|v|b|i|e|j
  optional:
    - name: search
      format: text
      source: get
    - name: page
      format: integer
      source: get
      notes: pagination page number
    - name: rows
      format: integer
      source: get
      notes: rows per page
    - name: sort
      format: text
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: JSON rows + total count
  identifier: each row includes id (rID) and short_name
errors: []
idempotency: safe (read-only)
related: [contacts.list, contacts.read]
confidence: high
source: src/controllers/contacts/main.php:106 (managerRows)
```

## contacts.read

```yaml
id: contacts.read
title: Read a single contact's full detail (read-only)
route: contacts/main/details
http_method: GET
ui_path: (popup / detail view)
auth:
  sec_id: contacts (manager)
  min_level: 1
preconditions:
  - the contact rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
      notes: contact record id. rID=0 returns the business's own company info.
  optional:
    - name: prefix
      format: text
      source: get
    - name: suffix
      format: text
      source: get
    - name: fill
      format: char
      source: get
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects:
    - resolves terms to text (terms_text) and a terminal/due date (terminal_date)
    - loads primary (m), billing (b) and shipping (s) addresses
returns:
  success_signal: layout.content populated with {contact, address[]}
  identifier: contact + address arrays
errors: []
idempotency: safe (read-only)
related: [contacts.list.rows, contacts.address.list]
confidence: high
source: src/controllers/contacts/main.php:523 (details)
```

## contacts.customer.create

```yaml
id: contacts.customer.create
title: Create a customer
route: contacts/main/save
http_method: POST
ui_path: Contacts ▸ Customers ▸ New
auth:
  sec_id: contacts (manager)
  min_level: 2
preconditions:
  - phreebooks default GL accounts configured (used as the contact's default gl_account)
inputs:
  required:
    - name: primary_name
      format: text
      source: post
      schema_field: contacts.primary_name
      notes: Business/primary name as it appears in correspondence. Required by addressUpdate.
  optional:
    - name: short_name
      format: text
      source: post
      schema_field: contacts.short_name
      notes: Contact ID, max 32 chars, must be unique. Leave blank to auto-assign from next_cust_id_num.
    - name: contact_first
      format: text
      source: post
      schema_field: contacts.contact_first
    - name: contact_last
      format: text
      source: post
      schema_field: contacts.contact_last
    - name: email
      format: email
      source: post
      schema_field: contacts.email
      notes: primary (sales) email; email2=AR, email3=purchasing, email4=AP
    - name: telephone1
      format: text
      source: post
      schema_field: contacts.telephone1
    - name: address1
      format: text
      source: post
    - name: city
      format: text
      source: post
    - name: state
      format: text
      source: post
    - name: postal_code
      format: text
      source: post
    - name: country
      format: text
      source: post
      notes: 3-char ISO code (USA, CAN, JPN…). Defaults to company country.
    - name: tax_rate_id
      format: text
      source: post
      schema_field: contacts.tax_rate_id
      notes: ID from the tax rate settings; 0 = none
    - name: tax_exempt
      format: char
      source: post
      notes: 0=no, 1=yes
    - name: rep_id
      format: integer
      source: post
      schema_field: contacts.rep_id
      notes: contact id of the assigned sales/dept rep
    - name: price_sheet
      format: integer
      source: post
      notes: inventory price-sheet record id; 0=none
    - name: terms
      format: text
      source: post
      notes: encoded payment-terms value; generally set via contacts/main/editTerms, not raw
    - name: store_id
      format: integer
      source: post
      notes: store/branch contact id; defaults to the acting user's store
    - name: gl_account
      format: text
      source: post
      notes: default GL; if blank on import, set to customers.gl_sales setting
    - name: account_number
      format: text
      source: post
    - name: inactive
      format: char
      source: post
      notes: status 0=active, 2=locked
  fixed:
    - name: ctype_c
      value: "1"
      notes: forced — the save method stamps the role flag for type c
    - name: type
      value: c
      notes: passed on the route (&type=c)
effects:
  db_writes:
    - table: contacts
      op: insert
    - table: contacts_meta
      op: insert/update
      notes: any billing/shipping/CRM addresses submitted with suffix fields
  gl_journal: none
  inventory: none
  side_effects:
    - if short_name blank, auto-generates next reference (next_cust_id_num) and retries on collision
    - wraps the write in a DB transaction (start/commit)
    - saves any uploaded attachment to the contacts attach path and flags attach=1
    - emits success message and reloads the dgContacts grid
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: new contact id (rID); echoed into $_GET['rID']/$_POST['id']
errors:
  - error_duplicate_id: short_name already exists on another contact
  - permission denied if user lacks level 2
idempotency: >
  NOT idempotent as a raw insert. For safe automated upserts use
  contacts.contact.import (matches existing rows on short_name + type) or
  pre-check with contacts.list.rows.
related: [contacts.update, contacts.vendor.create, contacts.contact.import, contacts.address.upsert]
confidence: high
source: src/controllers/contacts/main.php:427 (save), :592 (dbContactSave)
```

## contacts.vendor.create

```yaml
id: contacts.vendor.create
title: Create a vendor
route: contacts/main/save
http_method: POST
ui_path: Contacts ▸ Vendors ▸ New
auth:
  sec_id: contacts (manager)
  min_level: 2
preconditions:
  - phreebooks vendors.gl_expense configured (used as default gl_account)
inputs:
  required:
    - name: primary_name
      format: text
      source: post
  optional:
    - name: short_name
      format: text
      source: post
      notes: blank auto-assigns from next_vend_id_num
    - name: account_number
      format: text
      source: post
      notes: for vendors, your account number with that vendor
    - name: gl_account
      format: text
      source: post
      notes: if blank on import, set to vendors.gl_expense
    # …all the same address/contact/terms/tax fields as contacts.customer.create
  fixed:
    - name: ctype_v
      value: "1"
    - name: type
      value: v
effects:
  db_writes:
    - table: contacts
      op: insert
    - table: contacts_meta
      op: insert/update
  gl_journal: none
  inventory: none
  side_effects:
    - blank short_name auto-generated from next_vend_id_num
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: new contact id (rID)
errors:
  - error_duplicate_id
idempotency: NOT idempotent; upsert via contacts.contact.import
related: [contacts.customer.create, contacts.update, contacts.contact.import]
confidence: high
source: src/controllers/contacts/main.php:427 (save), :592 (dbContactSave)
```

## contacts.update

```yaml
id: contacts.update
title: Update an existing contact
route: contacts/main/save
http_method: POST
ui_path: Contacts ▸ (any type) ▸ open record ▸ Save
auth:
  sec_id: contacts (manager)
  min_level: 3
preconditions:
  - id (rID) refers to an existing contact
inputs:
  required:
    - name: id
      format: integer
      source: post
      notes: existing contact rID. Presence of id switches save into update mode (level 3).
  optional:
    - name: short_name
      format: text
      source: post
      notes: if omitted on an existing record, the current value is left unchanged
    # …any contacts fields to change (same set as create)
  fixed:
    - name: date_last
      value: today
      notes: stamped automatically on update
effects:
  db_writes:
    - table: contacts
      op: update
  gl_journal: none
  inventory: none
  side_effects:
    - duplicate short_name (on a different id) is rejected
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: rID (unchanged)
errors:
  - error_duplicate_id
  - permission denied if user lacks level 3
idempotency: idempotent — re-applying the same field values yields the same row
related: [contacts.customer.create, contacts.read]
confidence: high
source: src/controllers/contacts/main.php:427 (save), :592 (dbContactSave)
```

## contacts.delete

```yaml
id: contacts.delete
title: Delete a contact
route: contacts/main/delete
http_method: GET
ui_path: Contacts ▸ open record ▸ Trash
auth:
  sec_id: contacts (manager)
  min_level: 4
preconditions:
  - NO journal transaction references the contact (as contact_id_b, contact_id_s, or store_id)
inputs:
  required:
    - name: rID
      format: integer
      source: get
  optional:
    - name: data
      format: text
      source: get
      notes: optional "reload:<grid>" directive controlling which grid to refresh
  fixed: []
effects:
  db_writes:
    - table: contacts
      op: delete
    - table: contacts_meta
      op: delete
      notes: WHERE ref_id = rID
    - table: contacts_log
      op: delete
      notes: WHERE contact_id = rID
  gl_journal: none
  inventory: none
  side_effects:
    - deletes attachment zip files for the contact from the attach path
returns:
  success_signal: dbAction delete statements returned; grid reloads
  identifier: none
errors:
  - err_contacts_delete: blocked — a journal entry references this contact (delete refused, no change)
  - "missing id message if rID not passed"
idempotency: idempotent (deleting an already-gone row is a no-op)
related: [contacts.merge]
confidence: high
source: src/controllers/contacts/main.php:491 (delete)
```

## contacts.note.add

```yaml
id: contacts.note.add
title: Set/replace the free-text notes on a contact
route: contacts/main/saveNotes
http_method: POST
ui_path: Contacts ▸ open record ▸ Notes tab
auth:
  sec_id: contacts (manager)
  min_level: 1   # method itself only requires a valid rID; gated by page access
preconditions:
  - rID exists
inputs:
  required:
    - name: rID
      format: integer
      source: get
    - name: notes
      format: text
      source: post
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts_meta
      op: insert/update
      notes: meta key 'notes' for the contact (single notes blob, replaced)
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: msgStack 'success' = msg_record_saved
  identifier: none
errors:
  - "silent no-op if rID missing"
idempotency: idempotent (overwrites the single notes blob)
related: [contacts.crmlog.add]
confidence: high
source: src/controllers/contacts/main.php:453 (saveNotes)
```

## contacts.crmlog.add

```yaml
id: contacts.crmlog.add
title: Append a CRM activity-log entry to a contact
route: contacts/main/saveLog
http_method: POST
ui_path: Contacts ▸ open record ▸ CRM / Log
auth:
  sec_id: contacts (manager)
  min_level: 1
preconditions:
  - rID exists; crm_note non-empty
inputs:
  required:
    - name: rID
      format: integer
      source: get
    - name: crm_note
      format: text
      source: post
      notes: the log note; method no-ops if empty
  optional:
    - name: crm_action
      format: text
      source: post
      notes: short action label (call, email, meeting…)
    - name: crm_rep_id
      format: integer
      source: post
      notes: contact id of the rep who performed the action (entered_by)
    - name: crm_date
      format: date
      source: post
      notes: log_date; date of the activity
  fixed: []
effects:
  db_writes:
    - table: contacts_log
      op: insert
      notes: {contact_id, entered_by, log_date, action, notes}
  gl_journal: none
  inventory: none
  side_effects:
    - reloads the dgLog grid; clears the note input
returns:
  success_signal: msgStack 'success' = msg_record_saved (when called standalone)
  identifier: none
errors:
  - "silent no-op if rID or crm_note missing"
idempotency: NOT idempotent — each call appends a new log row
related: [contacts.note.add]
confidence: high
source: src/controllers/contacts/main.php:467 (saveLog)
```

## contacts.address.list

```yaml
id: contacts.address.list
title: List billing/shipping/CRM addresses for a contact
route: contacts/address/manager
http_method: GET
ui_path: Contacts ▸ open record ▸ Billing / Shipping / CRM tab
auth:
  sec_id: contacts (manager)
  min_level: 1
preconditions:
  - refID (contact rID) exists
inputs:
  required:
    - name: refID
      format: integer
      source: get
      notes: contact rID the addresses belong to
    - name: aType
      format: char
      source: get
      notes: address type — b (billing), s (shipping), i (CRM contact)
  optional:
    - name: type
      format: char
      source: get
      notes: contact type context (c/v/…)
  fixed: []
effects:
  db_writes: []
  gl_journal: none
  inventory: none
  side_effects: []
returns:
  success_signal: address datagrid
  identifier: each address row has an aID (meta id)
errors: []
idempotency: safe (read-only)
related: [contacts.address.upsert, contacts.read]
confidence: high
source: src/controllers/contacts/address.php:106 (manager)
```

## contacts.address.upsert

```yaml
id: contacts.address.upsert
title: Add or update a billing/shipping/CRM address on a contact
route: contacts/address/save
http_method: POST
ui_path: Contacts ▸ open record ▸ address tab ▸ Save
auth:
  sec_id: contacts (manager)
  min_level: 2   # 3 when updating an existing address
preconditions:
  - target contact exists (cID); a new contact is created if cID is empty and a primary_name is given
inputs:
  required:
    - name: primary_name
      format: text
      source: post
      notes: always required (with the address suffix); aborts otherwise
  optional:
    - name: cID
      format: integer
      source: post
      notes: contact rID to attach to; blank creates a new contact
    - name: aID
      format: integer
      source: post
      notes: address meta id; blank inserts a new address
    - name: aType
      format: char
      source: post
      notes: b | s | i (billing / shipping / CRM)
    - name: contact
      format: text
      source: post
    - name: address1
      format: text
      source: post
    - name: address2
      format: text
      source: post
    - name: city
      format: text
      source: post
    - name: state
      format: text
      source: post
    - name: postal_code
      format: text
      source: post
    - name: country
      format: text
      source: post
  fixed: []
effects:
  db_writes:
    - table: contacts
      op: insert/update
      notes: billing address (aType=b) is written onto the contact row itself
    - table: contacts_meta
      op: insert/update
      notes: shipping/CRM addresses stored as address_<aType> meta keyed by cID
  gl_journal: none
  inventory: none
  side_effects:
    - sanitizes date fields; sets rep_id on a newly created contact
returns:
  success_signal: returns {cID, aID}
  identifier: cID (contact) and aID (address meta id)
errors:
  - primary_name_required: primary_name missing
idempotency: >
  upsert — supply aID to update a specific address; omit it to add a new one.
related: [contacts.address.list, contacts.customer.create]
confidence: medium   # address routing (row vs meta) depends on aType and whether cID was passed
source: src/controllers/contacts/main.php:641 (addressUpdate); src/controllers/contacts/address.php:152 (save)
```

## contacts.contact.import

```yaml
id: contacts.contact.import
title: Bulk import/upsert contacts from CSV
route: contacts/api/apiImport
http_method: POST
ui_path: Contacts ▸ Tools/API ▸ Import (upload CSV)
auth:
  sec_id: admin
  min_level: 2
preconditions:
  - CSV columns match the template tags from contacts.contact.template
  - each row has a Type (c/v/b/i/e/j) and a Contact ID (short_name) or auto-assignable name
inputs:
  required:
    - name: fileContacts
      format: file
      source: post
      notes: uploaded .csv; header row of tag names, one contact per row
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts
      op: insert (new short_name) or update (existing short_name+type)
  gl_journal: none
  inventory: none
  side_effects:
    - matches existing rows on short_name + type → UPDATE; otherwise INSERT
    - blank short_name auto-generated per type via getShortName (email/tele/auto)
    - new rows without gl_account get the type's default GL (customers.gl_sales / vendors.gl_expense)
    - validates each row against the table structure; whole import wrapped in one DB transaction
    - skips rows with missing/invalid Type and reports counts
returns:
  success_signal: info message "Imported total rows: N, Added: A, Updated: U"
  identifier: none (per-row); query afterward by short_name
errors:
  - "abort if the Contact ID (short_name) column is absent from the file"
  - "row skipped on invalid/missing Type or unassignable short_name"
  - permission denied if user lacks admin level 2
idempotency: >
  IDEMPOTENT on short_name + type — re-importing the same file updates in place
  rather than duplicating. This is the preferred path for automated contact sync.
related: [contacts.contact.template, contacts.contact.export, contacts.customer.create]
confidence: high
source: src/controllers/contacts/api.php:87 (apiImport)
```

## contacts.contact.template

```yaml
id: contacts.contact.template
title: Download the CSV import template (column tags + field docs)
route: contacts/api/apiTemplate
http_method: GET
ui_path: Contacts ▸ Tools/API ▸ Download template
auth:
  sec_id: contacts (api)
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
    - streams ContactsTemplate.csv — header row of importable field tags plus a
      "Field Information" block marking each [Required]/[Optional] with descriptions
returns:
  success_signal: file download (no layout return)
  identifier: none
errors: []
idempotency: safe (read-only)
related: [contacts.contact.import]
confidence: high
source: src/controllers/contacts/api.php:62 (apiTemplate)
```

## contacts.contact.export

```yaml
id: contacts.contact.export
title: Export all contacts to CSV
route: contacts/api/apiExport
http_method: GET
ui_path: Contacts ▸ Tools/API ▸ Export
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
    - streams Contacts-<date>.csv of all contact rows, exportable fields only,
      ordered by short_name
returns:
  success_signal: file download
  identifier: none
errors:
  - permission denied if user lacks admin level 1
idempotency: safe (read-only)
related: [contacts.contact.import]
confidence: high
source: src/controllers/contacts/api.php:176 (apiExport)
```

## contacts.merge

```yaml
id: contacts.merge
title: Merge one contact into another (de-duplication)
route: contacts/tools/mergeSave
http_method: POST
ui_path: Contacts ▸ Tools ▸ Merge
auth:
  sec_id: admin
  min_level: 4   # destructive: removes the source contact
preconditions:
  - both source and destination contacts exist
inputs:
  required:
    - name: (source contact id)
      format: integer
      source: post
      notes: contact being merged away
    - name: (destination contact id)
      format: integer
      source: post
      notes: contact that survives and absorbs references
  optional: []
  fixed: []
effects:
  db_writes:
    - table: contacts (+ contacts_meta, contacts_log, journal references, wallet, bookmarks)
      op: update/delete
      notes: repoints child records (meta, notes, tax, wallet, bookmarks, journal contact_id) to the destination, then removes the source
  gl_journal: none   # repoints existing journal references; does not create new postings
  inventory: none
  side_effects:
    - changes contact_id on historical journal rows (mergeChangeCID) — affects reporting attribution
returns:
  success_signal: merge completion message
  identifier: surviving destination contact id
errors:
  - permission denied if user lacks admin level 4
idempotency: NOT idempotent — the source ceases to exist after the first run
related: [contacts.delete]
confidence: medium   # exact field set repointed spans several private helpers; verify before automated use
source: src/controllers/contacts/tools.php:66 (mergeSave) and helpers :103-241
```

---

## Common agent recipes

```yaml
recipe_create_customer_with_shipping:
  goal: Create a customer and attach a shipping address
  steps:
    - action: contacts.customer.create
      with: {primary_name, email, terms, tax_rate_id}
      capture: rID
    - action: contacts.address.upsert
      with: {cID: $rID, aType: s, primary_name, address1, city, state, postal_code, country}

recipe_sync_contacts_from_external:
  goal: Keep Bizuno contacts in sync with an external CRM (idempotent)
  steps:
    - action: contacts.contact.template      # once, to learn the column tags
    - build CSV keyed by short_name + Type
    - action: contacts.contact.import        # re-run any time; upserts on short_name+type
  note: prefer import over per-record create — it is the only idempotent write path

recipe_safe_delete:
  goal: Remove a contact only if it has no financial history
  steps:
    - action: contacts.delete
    - on_error err_contacts_delete: the contact has journal history — do NOT force; mark inactive=2 (locked) via contacts.update instead
```

## Open questions / verify-before-automating

- `contacts.merge` repoints references through several private helpers; the
  full set of touched tables should be re-verified against `tools.php` before
  wiring it into an automated de-dup flow (`confidence: medium`).
- `contacts.address.upsert` routes a billing address onto the contact row but
  shipping/CRM addresses into `contacts_meta`; confirm the aType→destination
  mapping for your case (`confidence: medium`).
- Security `sec_id` keys are shown by role; the exact module security string
  per method should be confirmed against `validateAccess` calls if your agent
  enforces permissions client-side.
