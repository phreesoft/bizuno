# Changelog

All notable changes to Bizuno ERP are documented in this file.

## [Unreleased]

### Added
- **Monthly statement test mode and Run Now.** PhreeBooks → Customers has a **Monthly Statement Test
  Address**. While it is set, the statement run sends every statement to that address (subject
  `[TEST for <customer email>]`), skips the CC and the customer log entry, and marks the summary
  TEST. A **Run Now** button starts a run from the settings screen for administrators. A statement
  PDF that cannot be built is reported as failed for that customer instead of stopping the run. New
  page: *Administration → Monthly customer statements*.
- **Paid/Unpaid report filter.** PhreeForm has a new filter type, *All : Paid : Unpaid*, for
  `journal_main.closed` (Paid = closed, Unpaid = open). The default Customer Statement form has a
  visible **Paid Status** filter using it. It defaults to All, so existing statements and the monthly
  statement run are unchanged. To add it to an installed statement form, open the form in
  PhreeForm and add a filter row: field `journal_main.closed`, type *All:Paid:Unpaid*, visible.

## [7.4.13] — 2026-11-28

### Security
- **Internal helpers are not routes.** A `bizRt` route may only target a controller method that takes
  no arguments or takes the layout by reference. Public helpers whose first parameter is a value (an
  address, a SKU, a payment method, a chart file, ...) used to run with the request's layout in its
  place. One example is `contacts/main/addressUpdate`, which saved an address from the posted
  fields. The rule applies to every module, `myExt` included. Internal `compose()` calls and hooks
  are unaffected.
- **Report security on direct access.** The Users/Roles settings of a PhreeForm report applied only
  to the report lists. Opening, rendering or emailing a report by ID (`phreeform/render/open`,
  `render`, `phreeformBody`) and the group list used by the print buttons now apply them too. Default
  reports allow everyone and the token-checked statement cron is not restricted.
- **Permission checks on the remaining routes that read or change business data:**
  - backups: listing and restore upload need administrator level 4;
  - fixed-asset schedules and export;
  - tables repair, when routed, needs administrator level 4;
  - payroll import: `j2_mgr`;
  - tax bulk change;
  - EDI fetch and transmit: sales rights, or the token-checked `ediCron`;
  - chart of accounts export;
  - exchange-rate update;
  - customer payment history and contact status;
  - promotion e-mail sending: `Promotions` 3;
  - the inventory and contact chart/export downloads;
  - assembly stock;
  - inventory attributes;
  - Document Manager rename;
  - method settings: administrator level 3, since they carry stored credentials;
  - API order add;
  - API inventory sync;
  - unshipped orders;
  - fulfillment, checked before it raises the journal rights;
  - ACH file close.

### Removed
- **Leftover WordPress file manager and placeholder pages.** `controllers/office/files.php` and
  `storage.php` (a WordPress-only file manager, reachable only through the retired plugin) and the
  empty `artist`, `author`, `calendar`, `slides` and `tables` pages are gone. The Document Manager
  (`office/docs`) is unchanged.

## [7.4.12] — 2026-11-28

### Security
- **Data files need a sign-in.** `portal/api/fs` (reachable without a session) serves files from the data
  folder only from `images/` (logos, product images) and `myExt/`, resolved with `realpath()`. Attachments,
  shipping labels and other data files with an image extension now need a signed-in user.
- **Lifecycle methods are not routable.** A direct route to a module's `install`, `installFirst`,
  `initialize`, `remove`, `adminInstMethods` or `installPhysicalFields` needs administrator level 4.
  Re-running one could drop columns or reset every carrier, gateway and funnel to its default settings.
  The installer and the Settings screens call them internally and are unaffected.
- **SQL escaping on role-gated screens.** Values from the request are escaped (or validated) before they
  reach raw SQL in shipping manager save, inventory rename/copy, module delete, budget load/save,
  currency save/delete (ISO codes must be three letters), bank reconciliation and the inventory design
  save. The inventory history grid escapes the SKU read from the database.
- **Grid sorting.** The contact history, promotions, contact log, inventory history/work-order and
  custom field/tab grids accept only field names for the sort column and `asc`/`desc` for the
  direction.
- **Permission checks.** Saving the RESTful sales tax nexus settings needs administrator level 3. The
  dashboard CSV downloads (`phreebooks/tools/jrnlData`, `agingData`, `exportSales`, `salesByRep`)
  need the same journal right as their dashboard.

## [7.4.11] — 2026-11-28

### Security
- **Module settings saves require administrator level 3.** The `adminSave` action of the bizuno,
  api, inventory, payment, phreebooks and phreeform modules had no permission check, so any
  signed-in user could rewrite that module's settings by calling the route directly. The PhreeBooks
  order-totals save (`phreebooks/admin/orderTotals`) also requires administrator level 3, and the
  fiscal-year panel and role-editor extensions require administrator access.
- **Funnel routes check the funnel's permission.** Every `api/admin/*` route that hands off to a
  sales funnel now checks the funnel's role permission first (level 1 to view, 2 to send orders or
  products, 3 for bulk refreshes, feeds, payments and reconciliation). The key is the funnel's
  public `$secID` property when it declares one, otherwise its ID, and administrators (level 3)
  always pass. `cronGet` runs unattended only from the token-checked `portal/api/funnelCron`
  route. The duplicate `api/admin/getRoles` requires administrator level 4; remote servers use
  `portal/api/getRoles`.
- **FedEx branch credentials** (shipping hooks on branch contacts) now require the customer
  manager right: level 1 to view, 3 to save, 4 to delete.

## [7.4.10] — 2026-11-27

### Security
- **Permission checks on maintenance tools.** The general-ledger repost/repair and COGS prune tools
  and the inventory balance/history recalculation tools checked permissions only on their first
  step; every step (each is a routable action) now requires administrator level 3. The repost tool
  also accepts only integer journal IDs.
- **Attachment delete confined to the data folder.** `bizuno/main/fileDelete` now deletes a single
  existing file inside the data folder: no wildcards, `..` or absolute paths, never PHP files,
  `myExt/` or the instance key, and backups require administrator level 4. Downloading a database
  backup through `bizuno/main/fileDownload` also requires administrator level 4.
- **Bank register rows** (`phreebooks/register/managerRows`) now require the same *register* right as
  the register page, and the GL account value is escaped.
- **Monthly statement run** (`phreeform/render/cronStatements`) can only be started by the
  token-checked `portal/api/stmtCron` route or by an administrator.

## [7.4.9] — 2026-11-26

### Security
- **Escaping no longer depends on the server's SQL mode.** Request values are escaped with
  `addslashes()` throughout Bizuno. On a MySQL/MariaDB server with `NO_BACKSLASH_ESCAPES` in its
  SQL mode, a backslash is an ordinary character, so an escaped quote still ended the string and
  allowed SQL injection. The database connection now removes `NO_BACKSLASH_ESCAPES` from the
  session SQL mode, alongside `ONLY_FULL_GROUP_BY` (`src/model/db.php`).

### Fixed
- **Side menu arrow opened the manager instead of expanding.** In 7.4.8, clicking the arrow next to
  *Sales Manager* / *Purchase Manager* opened the page like the label. The theme draws that arrow
  with CSS at the right edge of the row, so the click reached the row's click handler. Clicks on the
  right-most 30px of the row now expand or collapse the sub-items; the label still opens the page.

## [7.4.8] — 2026-11-26

Upgrading from 7.4.7 runs upgrade gate 7.5.3, which deletes cached 0% sales tax rates left by
failed Zip-Tax lookups.

### Fixed
- **Status and code drop-downs saved the wrong numbers.** `dbMetaGet()` used `array_merge()`,
  which renumbers integer keys, so option lists read with `getMetaCommon()` came back as 0..n.
  Since 2026-05-15 the quality ticket/audit/objective status, return status and code, and the
  *3-yearly* frequency were saved as positions, e.g. *Closed Successful* stored as 10 instead of
  99. The lists now keep their real codes. Records saved with the shifted numbers are not
  converted; check quality tickets (`journal_main.printed` 9/10 → 90/99 on journal 30) and returns
  saved since that date.
- **Current Stop Work dashboard listed In Process tickets.** It filtered on ticket status 2
  (*In Process*) instead of 8 (*Stop Work*). It now lists only open tickets with status Stop Work.
- **PhreeForm field lists were empty right after adding a table.** On the Database tab, a newly
  picked table stays in the row's editor until the row is saved, so going straight to the Fields
  tab left the field drop-downs with only *None*. `pfTableList()` now also reads tables from rows
  still being edited.
- **Zip-Tax errors were still saved as a 0% rate.** Zip-Tax reports a bad key, quota or address
  problem with an error code and an *empty* `taxSummaries` list, which 7.4.7 still accepted, so
  tax silently stayed at 0 for that ZIP until the next quarter. A lookup now counts only when it
  returns a sales tax rate; otherwise nothing is cached and the order screen shows why. When
  Zip-Tax rejects the key (expired, cancelled or mistyped) the user gets an error saying sales tax
  was not calculated and the key must be renewed under API Settings -> Sales Tax APIs; the same
  notice goes to the Bizuno log at most once a day, so storefront-only failures are noticed too.
  The 7.5.3 upgrade gate deletes cached 0% rates left by earlier failures.
- **API keys no longer appear in `trace.txt`.** URLs logged by `io::cURL()` mask `api_key`, `key`,
  `token`, password and similar query parameters (new `bizScrubUrl()`).
- **Side menu items with sub-items open their page again.** Clicking *Customers → Sales Manager*
  (or *Vendors → Purchase Manager*) only expanded its sub-items, because EasyUI's sidemenu won't
  select a parent item. Clicking the label now opens the manager; clicking the arrow opens or
  closes the sub-items. Groups without a page of their own still toggle on any click
  (`bizSidemenuParentNav()` in `src/view/easyUI/common.js`).
- **Update badge stayed on after updating.** The software-update check saved its "update
  available" answer for 24 hours, so after installing an update the gear badge, banner and
  *Update Now* button kept offering the release just installed. The saved answer is now compared
  with the installed version every time (`src/controllers/administrate/updater.php`).

## [7.4.7] — 2026-11-26

First release since 7.4.5. There was no 7.4.6 release; its changes ship here. Development
builds numbered 7.4.6 through 7.5.1 (and the upgrade gates named after them) are all part of
this release.

### Security
- **Lost-password account takeover** (`src/portal/viewMaint.php`) — the reset link was rendered
  in the HTTP response when mail was "not configured" or the send failed. The guest path never
  loads the business cache, so that check always failed and every reset request for a valid
  email returned a working link. The link is now only emailed; the response is identical for
  known and unknown addresses and whether or not the send worked; only a SHA-256 hash of the
  token is stored and it is compared with `hash_equals()`; one reset email per account per
  2 minutes; the token is no longer written to the debug trace. A locked-out admin resets from
  the shell with `bin/reset-bizuno-password.php`.
- **SQL injection in inventory lookups** (`src/controllers/inventory/main.php`,
  `src/controllers/inventory/tools.php`) — the request-supplied `sku` was put into the query
  unescaped. It is now escaped, and `inventory/main/properties` checks `inv_mgr` access before
  running any query.
- **Missing authorization on journal notes** (`src/controllers/phreebooks/main.php`) —
  `notesSave` now requires manager rights (level 2) on the journal of the record being updated.
- **Install/migrate hijack under WordPress** (`src/portal/controller.php`) — the install and
  migrate screens now require a logged-in user with `manage_options`; previously any visitor
  could complete setup between plugin activation and first admin visit.

### Added
- **Monthly customer statements by cron** — new customer property *Monthly Statement*
  (None / Email, skip if no activity / Email, always) and PhreeBooks Settings → Customers
  options for the statement form, date range and CC address. The token-secured route
  `portal/api/stmtCron` (same pattern as `funnelCron`) renders the chosen PhreeForm
  statement for each flagged customer, emails it, writes the CRM log entry and emails a
  run summary to the Manager address. Schedule with one curl line on the 1st of the month.
  DB: `contacts.stmt_email` added by the 7.4.7 upgrade gate.
- **FedEx hazmat / dangerous goods** on REST labels (parcel + LTL): hazmat panel in the
  label generator with battery/lead-acid/limited-quantity profiles, DG declaration or
  OP-900 requested with the label, freight line items flagged for the BOL. Replaces the
  FedEx Ship Manager workflow. Not yet verified against the FedEx sandbox.
- **Hazmat profile on inventory items** — new *Hazmat Profile* select in the item Properties
  panel (choices come from the installed shipping carriers, e.g. the FedEx lithium / lead acid
  profiles). When a SKU with a profile is on an order, the label generator preselects it and opens
  the dangerous goods panel with that profile's defaults. Blank means not hazmat, which is the
  default for every existing item. DB: `inventory.hazmat_profile` (7.4.8 upgrade gate).
- **Inventory Manager** shows a *Qty on Quote* column.

### Changed
- **Storefront sales tax no longer depends on PhreeSoft** (`src/portal/api.php` `getSalesTax`). The
  lookup used a `sales_tax_map` ZIP table that only existed on PhreeSoft's server. It now uses the
  same Zip-Tax engine as the order screen (`phreebooks/restfulTax`, new `storefrontRate()`):
  nexus states only, per-ZIP quarterly cache, and the API token required when one is configured.
  Without a Zip-Tax key, or if Zip-Tax fails, it falls back to the business's PhreeBooks customer
  tax rates matched by title (5-digit ZIP, then leading 2-letter state code). The response adds a
  `source` field. See *WooCommerce via bizuno-api → Sales tax for the storefront*.
- **Zip-Tax failures are no longer cached** (`restfulTax::fetchAndCacheRate`). A network error or
  bad response used to store a 0% rate for that ZIP until the next quarter.
- **Upgrade gates run exactly once.** Gates are numbered by development version, which can run
  ahead of the release number. The highest gate applied is stored in
  `bizuno.properties.schema_version` and each gate runs once whatever the release number; a
  database stamped by a pre-release build is re-stamped to the running version without re-running
  gates. Number new gates above `BIZUNO_UPGRADE_GATE` (now 7.5.2) and bump it.
- **PhreeForm designer field lists follow the Database tab** (`src/controllers/phreeform/design.php`).
  Field pickers send the tables currently on the Database tab instead of a list cached in the
  session, which could be stale; `getFields()` skips unknown tables and returns proper JSON.
- **GL posting failures are easier to diagnose** (`src/controllers/phreebooks/journals/common.php`):
  a failed `journal_history` update logs the account, period and row count, and every posting
  failure writes `trace.txt`.

### Removed
- **WordPress plugin retired.** The `bizuno-accounting` plugin is withdrawn from WordPress.org
  and will not be updated. Removed its build (`build/wordpress/`), the WordPress variant and
  WordPress.org SVN push from `release.yml`, the WordPress install walkthrough, and the
  WordPress install instructions in `README.md` and the installer's missing-dependency page.
  Existing WordPress installs should move to a standalone or Docker install; see
  *Four ways to run it* for migrating the `wp_bizuno_` tables.
- **Endicia shipping carrier.** It fetched its credentials from phreesoft.com. The carrier, its
  "buy postage" route (`shipping/admin/fundsBuy`, which had no permission check) and its docs are
  gone. The 7.5.2 upgrade gate removes it from the stored carrier list; old shipment records keep
  their `endicia` method code. Use the USPS carrier for postage.
- **Docs sync to bizuno.com.** The `docs-sync` workflow, `bin/docs-sync.php`, its slug map and
  the `erusev/parsedown` dev dependency are removed; the manual is read on GitHub. Links that
  pointed at bizuno.com/docs (release notes, Docker image label, NextCloud settings, zip README,
  install-check page) now point at the `docs/` folder on GitHub.
- **PhreeSoft News dashboard** now shows that PhreeSoft is no longer in business instead of
  "Coming soon!", and the dead phreesoft.com feed code is removed.

### Fixed
- **Quality dashboards linked to a page that no longer exists.** Clicking a ticket on *Quality
  Tickets Pending Review* or *Current Stop Work* showed "could not find the method" instead of
  opening it. Both now open the ticket in the Quality Tickets manager.
- Loading a carrier that is still listed in a stale cache but no longer in the code base no
  longer fatals; it falls back to the generic shipper.
- FedEx freight tracking links now go to fedexfreight.com for every freight service, not
  only Ground Priority/Economy.
- EDI TD5 map: FEDEX INTL P/E FRT now map to IFP/IFE instead of the LTL codes GDF/ECF.
- Shipping Manager saves log the shipment reference, order, method and tracking numbers
  under the title "Shipping Manager" (was a blank "Manager Manager - Save:" line).

## [7.4.5] — 2026-06-18

## [7.4.4] — 2026-06-14

### Fixed
- **Strict-MySQL (GoDaddy) compatibility** — resolved fatals on dashboard and report
  pages under MySQL strict mode (`src/model/db.php`, plus `install/migrate-7.0.php`,
  `install/upgrade.php`, `install/tables.php`).
- Install table schema, the mini-financial dashboard, and the PhreeForm income
  statement report.
- `bin/docs-sync.php`: removed the deprecated `curl_close()` call (PHP 8.x).
- PhreeForm PDF images: cast the x/y position passed to `tFPDF::Image()` to numeric,
  completing the PHP 8 "Unsupported operand types" hardening (width/height were
  already coerced in 7.4.3).

### Documentation
- Built out the Bizuno user manual (drafted and published to the bizuno.com BetterDocs
  site via `bin/docs-sync.php`):
  - **PhreeBooks** — chart of accounts, register/reconcile, payroll, fiscal year, the
    Sale/Purchase (Order) Manager reference, and journals.
  - **Inventory** — module section, including the assembly labor/overhead costing
    clarification (labor is not capitalized into assembly cost, by design) and the
    stop-work dashboard.
  - **Contacts**, **PhreeForm**, **Quality**, and **Shipping** module sections.

## [7.4.3]

- Restored the jQuery-EasyUI `themes/icons/` set (fixes missing toolbar/button icons).
- Users Manager: administrators (admin security level 5) can reset a user's password
  from the user edit screen.
- PhreeForm PDF images: cast width/height to numeric so a blank dimension renders as
  auto-size instead of throwing a PHP 8 `tFPDF::Image()` "Unsupported operand types"
  fatal when printing forms/reports with a logo. *(Sites still on 7.4.2 hit this until
  upgraded.)*
