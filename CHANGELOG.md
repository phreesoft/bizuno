# Changelog

All notable changes to Bizuno ERP are documented in this file.

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
