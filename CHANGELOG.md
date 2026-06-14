# Changelog

All notable changes to Bizuno ERP are documented in this file.

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
