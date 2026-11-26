# Changelog

All notable changes to Bizuno ERP are documented in this file.

## [Unreleased]

### Fixed
- **Zip-Tax errors were still saved as a 0% rate.** Zip-Tax reports a bad key, quota or address
  problem with an error code and an *empty* `taxSummaries` list, which 7.4.7 still accepted, so
  tax silently stayed at 0 for that ZIP until the next quarter. A lookup now counts only when it
  returns a sales tax rate; otherwise nothing is cached and the order screen shows Zip-Tax's own
  message (e.g. "Key format is not valid or key not found. (code 101)"). The 7.5.3 upgrade gate
  deletes cached 0% rates left by earlier failures.
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
