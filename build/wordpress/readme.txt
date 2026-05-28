=== Bizuno Accounting – ERP/Accounting/CRM (for WordPress) ===
Stable tag: 7.4.2
Contributors: phreesoft
Donate link: https://www.bizuno.com/donate/
Tags: erp, accounting, bookkeeping, inventory, crm, woocommerce, double-entry, invoicing, purchase-orders, sales-tax, shipping, multi-store
Requires at least: 6.5
Tested up to: 6.9.4
Requires PHP: 8.2
License: AGPL-3.0-or-later
License URI: https://www.gnu.org/licenses/agpl-3.0.html

The complete, self-hosted Bizuno ERP powerhouse – full double-entry accounting, inventory, CRM & business automation running seamlessly as a portal inside your WordPress site!

== Description ==

**Bizuno** by PhreeSoft is the ultimate open-source ERP/Accounting/CRM solution – modern, fast, secure, and infinitely customizable. Evolved from PhreeBooks, Bizuno delivers enterprise-grade tools without the enterprise price tag.

Install this plugin, activate it, click the Bizuno menu – and in minutes you have a full-featured business management system running privately on your own server. No cloud dependency, full data ownership, unlimited users, and total control.

= Standout Features to Transform Your Business =

* **True Double-Entry Accounting** – General ledger, AR/AP, bank rec, multi-currency, financial reports.
* **Advanced Inventory & Multi-Warehouse** – Serial/lot tracking, BOMs/assemblies, real-time stock across locations.
* **Multi-Store Mastery** – Manage multiple stores, locations, or companies from one central Bizuno instance.
* **US Sales Tax Automation** – Built-in calculator with accurate, real-time US tax handling (powered by PhreeSoft API).
* **Integrated Shipping Powerhouse** – Connect USPS, FedEx, UPS for real-time rate quotes, label generation, package tracking, and freight bill reconciliation – streamline logistics and cut costs.
* **ISO 9001 Compliance Streamlining** – Process tracking, audit trails, customizable reports/forms, and quality management tools to simplify certification and ongoing audits.
* **CRM & Sales/Purchasing Cycle** – Customers/vendors, quotes → orders → invoices, RFQs → POs → bills/payments.
* **50+ Professional Reports + PhreeForm Builder** – Drag-and-drop custom reports, invoices, statements, and analytics.
* **Personalized Dashboards** – 20+ widgets, per-menu/user configurable – instant business insights.
* **Responsive & User-Friendly** – Works flawlessly on desktop, tablet, mobile; multi-language, role-based security, custom themes/icons/colors.
* **Extensible Ecosystem** – Modules for payments (Stripe, PayFabric), marketplaces, advanced shipping/tax – plus excellent WooCommerce sync via Bizuno API plugin.
* **Self-Hosted Freedom** – Your server, your data, no subscriptions (optional PhreeSoft Cloud available).

Trusted evolution of PhreeBooks since 2007 – now faster, more secure, and packed with modern features.

== Installation ==

**Self-Hosted Setup in Minutes**

1. Upload and activate the **Bizuno** plugin via WordPress admin (or install from ZIP downloaded from bizuno.com or GitHub).
2. Navigate to the new **Bizuno** menu item in WP admin.
3. On the welcome/portal screen, click **Install** (or **Upgrade** if updating).
4. Follow the quick setup wizard: enter preferences, database config (auto-handled in most cases), and preferences.
5. The full Bizuno library downloads securely and installs (~10-30 seconds depending on connection).
6. Page reloads → welcome to your new Bizuno dashboard and ERP home!

**Minimum Requirements**  
- PHP 8.2+ (8.0 minimum, but 8.2+ recommended)  
- MySQL 5.6+/MariaDB 10.2+  
- WordPress 6.5+  
- Modern browser

For full standalone (non-WordPress) install, see GitHub: https://github.com/phreesoft/bizuno

== Frequently Asked Questions ==

= How does Bizuno help achieve/maintain ISO 9001? =  
Built-in audit trails, process documentation, customizable quality reports, and compliance-friendly workflows make certification faster and audits easier.

= Multi-store support included? =  
Yes – handle multiple warehouses, locations, or even separate businesses from one secure Bizuno instance.

= US sales tax and shipping details? =  
Accurate US tax calc via API; full integration with USPS, FedEx, UPS – real-time quotes, labels, tracking, and freight reconciliation to save time/money.

= Where is the actual Bizuno code? =
Bundled inside the plugin — no external download step. The full Bizuno PHP library ships in `bizuno-accounting/src/`, third-party UI assets in `bizuno-accounting/scripts/`, and composer-installed dependencies in `bizuno-accounting/vendor/`. Activating the plugin is enough; the installer wizard on first hit just sets up the database tables.

= How do updates work? =
WordPress's standard update channel. New versions are published to the WordPress.org plugin directory; your site sees them in WP admin → Updates like any other plugin.

== Screenshots ==

1. Customizable Main Dashboard – Tailor insights per user/menu
2. Customer/Vendor CRM Detail – Add custom tabs/fields
3. Sales Invoice / Order / Quote Screen
4. Inventory Manager & Item Editor (BOMs, history, extensions)
5. PhreeForm – Unlimited Custom Reports & Forms
6. Shipping Integration Settings (USPS/FedEx/UPS)
7. Multi-Store / Warehouse Configuration
8. Quality & ISO Tools Overview
9. Dashboard Widget Gallery

== Changelog ==

= 7.4.2 =
* Fix: install/connection failed on locked-down managed MySQL hosts (GoDaddy and similar) with "1227 Access denied; you need ... SESSION_VARIABLES_ADMIN". The DB layer set server-global charset variables on connect, which shared hosts forbid. Now negotiates charset via the PDO DSN (no privileges needed) and uses a guarded `SET NAMES`. Fixes activation on restricted hosting.

= 7.4.1 =
* Fix: dashboard JavaScript broke on fresh 7.4.0 installs — the version string emitted into an inline script carried a trailing newline, producing an unterminated JS string literal that halted page scripts (`bizID is not defined`). Version is now trimmed before use. Upgrade strongly recommended for anyone on 7.4.0.

= 7.4.0 =
* First public release of the consolidated single-plugin architecture (the former `bizuno-wp` library plugin is now bundled inside this one — see the migration notice that appears in admin if `bizuno-wp` is still installed).
* In-app password change in the user profile editor, with current-password verification.
* Lost-password flow gains an inline-reset-link fallback when the site's mail transport isn't configured or send fails — survives cross-server DB restores where encrypted SMTP credentials become unreadable.
* Installer fix: admin passwords set during the first-run wizard now verify correctly on the next login (a key-drift bug in the installer's portalCFG.php generation made the first set password unusable in some cases).
* Favicon + login-screen logo now serve from the plugin's bundled `src/view/images/` instead of pinging `bizuno.com` at every page load — better for air-gapped installs and reduces external dependencies.
* Plugin Check (WordPress.org review tool) compliance: scoped via `phpcs.xml`, security findings cleared, text domain corrected to match slug.
* `bin/reset-bizuno-password.php` — CLI emergency password reset for cases where the web UI can't load.

= 7.3.9 =
* Self-contained plugin — folds the former `bizuno-wp` library plugin into this one. Single install, no sibling plugin required.
* Switched updates to the standard WordPress.org channel; the third-party update-checker library is gone.
* Bizuno path layout: `src/`, `vendor/`, `scripts/` all live inside the plugin directory.
* Library upgrades: tFPDF replaces TCPDF; picqer barcode generator; FPDI for PDF import.

= 7.3.8 =
* Preparation for 2FA via email/biometrics. Locale updates & simplification. And more minor bugs.

= 7.3.7 =
* Bug fixes and prep for locale cleanup, compatibility with WP Bizuno API re-release

= 7.3.6 =
* Prep for stable release – enhanced self-hosted portal stability
* Improved download/install flow for full library
* Bug fixes for price/tax lookups, locales/states
* Compatibility: WordPress 6.9 / PHP 8.2+

= 7.3.5 =
* Fixes: price search in contacts, sales tax lookup
* Locale updates to JSON format + state dropdown
* Patches for locales and minor stability

= Earlier =
See GitHub commits for full history – ongoing modernization since 7.0+ architecture shift.

== Upgrade Notice ==

= 7.4.2 =
Fixes plugin activation on locked-down managed MySQL hosts (GoDaddy etc.) that previously failed with a SESSION_VARIABLES_ADMIN privilege error.

= 7.4.1 =
Critical fix for a 7.4.0 JavaScript regression that broke the dashboard on fresh installs. Anyone on 7.4.0 should update immediately.

= 7.4.0 =
First single-plugin release. If you have the legacy `bizuno-wp` plugin alongside, it is auto-deactivated and an admin notice will prompt you to delete its files. Data and settings preserved.

= 7.3.x =
Auto-update via WordPress. No manual data migration needed – your settings and data remain intact. Re-save any API/shipping configs if prompted.

== About PhreeSoft ==

PhreeSoft pioneered open-source ERP with PhreeBooks in 2007. Bizuno is the next-generation leap: faster performance, better security, richer features, and true self-hosted power.

**Website:** https://www.phreesoft.com  
**Bizuno Hub:** https://www.bizuno.com  
**GitHub (Core):** https://github.com/phreesoft/bizuno  
**Downloads:** https://bizuno.com/download  
**Support:** support@phreesoft.com

Take control of your business – install Bizuno today!