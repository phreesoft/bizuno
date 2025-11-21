# Bizuno ERP/Accounting User Manual

## Introduction
Bizuno is a full-featured, open-source ERP and accounting application developed by PhreeSoft, Inc., based on the PhreeBooks open-source platform. It provides comprehensive tools for small to medium-sized businesses, including customer and vendor management, double-entry accounting, inventory control, financial transaction management, and e-commerce integration. Bizuno is designed to be flexible, supporting standalone installations, WordPress plugins, and cloud-hosted solutions. This manual guides you through installing, using, and contributing to the Bizuno project.

## Table of Contents
1. [Features](#features)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [Usage](#usage)
5. [Configuration](#configuration)
6. [Contributing](#contributing)
7. [Troubleshooting](#troubleshooting)
8. [License](#license)

## Features
- **Double-Entry Accounting**: Complete financial tracking with general ledger, accounts receivable, and accounts payable.
- **Customer and Vendor Management**: Manage contacts, invoices, and payments.
- **Inventory Control**: Track stock levels, manage products, and synchronize with e-commerce platforms (e.g., WooCommerce with premium extension).
- **Responsive Interface**: Supports desktop, mobile, and tablet devices using jQuery EasyUI.
- **E-Commerce Integration**: Connects with carriers like FedEx, UPS, and USPS for shipping and address validation.
- **Reporting**: Generate over 50 customizable reports for financial and operational insights.
- **Cloud Hosting**: Available via PhreeSoft’s cloud for seamless access.[](https://www.phreesoft.com/)
- **WordPress Plugin**: Integrates with WordPress for easy deployment.[](https://wordpress.com/plugins/bizuno-accounting)

## Prerequisites
Before installing Bizuno, ensure you have:
- **PHP**: Version 8.0 or higher (PHP 8.2 recommended).
- **MySQL**: Version 5.0 or higher (MySQL 5.6 or 5.7 recommended).
- **Web Server**: Apache or Nginx.
- **Git**: For cloning the repository.
- A modern web browser (e.g., Chrome, Firefox) for the dashboard.
- Optional: WordPress installation for plugin-based deployment.

## Installation
Bizuno can be installed as a standalone application, a WordPress plugin, or hosted in the PhreeSoft cloud. Below are instructions for the standalone installation from the GitHub repository.

1. **Clone the Repository**
   ```bash
   git clone https://github.com/bcezarc/PhreeBooksERP.git
   cd PhreeBooksERP
   ```

2. **Upload to Web Server**
   - Copy the repository files to your web server’s root directory or a subdirectory (e.g., `/var/www/html/bizuno`).[](https://github.com/bcezarc/PhreeBooksERP)
   - Ensure the web server has read access to all directories except `/my_files`, which requires write access for company-specific files.[](https://wiki.koozali.org/PhreeBooks)

3. **Set Up Database**
   - Create a MySQL database using a tool like phpMyAdmin or the MySQL CLI.
   - Note your database credentials (host, username, password, database name).

4. **Run Installation**
   - Navigate to the Bizuno root folder in your browser (e.g., `http://yourdomain.com/bizuno`).
   - The installation portal will prompt for:
     - Administrator email and password.
     - Database credentials (host, username, password, database name).
     - Initial settings (e.g., currency, chart of accounts).[](https://github.com/bcezarc/PhreeBooksERP)
   - Select preferences and click **Next**. Wait approximately 10 seconds for configuration. The dashboard will load upon completion.[](https://github.com/bcezarc/PhreeBooksERP)

**WordPress Plugin Installation**
- Install the Bizuno Accounting plugin from the WordPress plugin repository.
- Activate the plugin and log in to WordPress.
- Access Bizuno from the WordPress admin menu (requires user authorization).[](https://www.phreesoft.com/bizuno/)

**Cloud Hosting**
- Sign up for PhreeSoft’s cloud hosting at [phreesoft.com](https://www.phreesoft.com). No local installation is required.[](https://www.phreesoft.com/product/hosted-bizuno/)

### Web Dashboard
1. Access the dashboard at `http://yourdomain.com/bizuno` or via the WordPress admin panel.
2. Log in with your administrator credentials (default: set during installation).
3. Use the interface to:
   - Create and manage tasks, invoices, and payments.
   - Track inventory and generate reports.
   - Configure integrations (e.g., WooCommerce, shipping carriers).[](https://www.phreesoft.com/)

## Configuration
Customize Bizuno by editing the `/portalCFG.php` file to set up your environment settings:
- **Database Settings**: Update `BIZUNO_DB_CREDS` with your database credentials.
- **Business ID**: Set your busienss ID, can be any combination of numbers and letters, best if more than 6 characters
- **Business Data Path**: Path to your business data files. This can be anywhere within the server php path. For security, the your data files should be located out of the reach of your wweb server.
- **Bizuno Key**: Set to a random 16 character string, used to encode your cookies and for other security measures.

For issues or feature requests, visit the [GitHub Issues page](https://github.com/bcezarc/PhreeBooksERP/issues).[](https://github.com/bcezarc/PhreeBooksERP)

## License
Bizuno is licensed under the GNU Affero General Public License v3 (AGPL3). See [LICENSE](LICENSE) for details.[](https://www.gnu.org/licenses/agpl-3.0.txt)
