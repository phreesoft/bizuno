# Changelog

All notable changes to Bizuno ERP are documented in this file.

## [7.4.6] — Unreleased

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

### Changed
- **Version set to 7.4.6 for the WordPress.org security release.** Upgrade gates are numbered by
  development version and already go up to 7.5.1, so the highest gate applied is now stored in
  `bizuno.properties.schema_version` and each gate runs once, whatever the release number. A
  database stamped by a pre-release build (7.5.1) is re-stamped to the running version without
  re-running gates. Number new gates above `BIZUNO_UPGRADE_GATE` (7.5.1) and bump it.

## [7.4.8] — Unreleased

### Added
- **Hazmat profile on inventory items** — new *Hazmat Profile* select in the item Properties
  panel (choices come from the installed shipping carriers, e.g. the FedEx lithium / lead acid
  profiles). When a SKU with a profile is on an order, the label generator preselects it and opens
  the dangerous goods panel with that profile's defaults. Blank means not hazmat, which is the
  default for every existing item. DB: `inventory.hazmat_profile` (7.4.8 upgrade gate).

### Changed
- **Inventory Manager** — removed the Vendor and On Open Quotes filters added in 7.4.7; the
  Qty on Quote column stays.

## [7.4.7] — Unreleased

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

### Fixed
- FedEx freight tracking links now go to fedexfreight.com for every freight service, not
  only Ground Priority/Economy.
- EDI TD5 map: FEDEX INTL P/E FRT now map to IFP/IFE instead of the LTL codes GDF/ECF.
- Shipping Manager saves log the shipment reference, order, method and tracking numbers
  under the title "Shipping Manager" (was a blank "Manager Manager - Save:" line).

## [7.4.5] — Unreleased

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
