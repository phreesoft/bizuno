# Bizuno ERP/Accounting User Manual

## Introduction
Bizuno is a full-featured, open-source ERP and accounting application developed by PhreeSoft, Inc., based on the PhreeBooks open-source platform. It provides comprehensive tools for small to medium-sized businesses, including customer and vendor management, double-entry accounting, inventory control, financial transaction management, and e-commerce integration. Bizuno is designed to be flexible, supporting standalone installations, WordPress plugins, and cloud-hosted solutions. This manual guides you through installing, using, and contributing to the Bizuno project.

## Table of Contents
1. [Features](#features)
2. [Prerequisites](#prerequisites)
3. [Installation](#installation)
4. [License](#license)

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
- **MySQL**: Version 5.6 or higher (MySQL 8.0+ recommended). MariaDB 10.2 or higher (10.11+ recommended)
- **Web Server**: Apache or Nginx.
- A modern web browser (e.g., Chrome, Firefox, Safari) for the dashboard.
- **Optional**: WordPress installation for plugin-based deployment.

## Installation
Bizuno can be installed as a standalone application, a WordPress plugin, or hosted in the PhreeSoft cloud. Below are instructions for the standalone installation from the GitHub repository.

1. **Using composer (preferred)**
   - Before installation you will need a website and a database to host your Bizuno business books. Bizuno can be installed in a sub-domain on your current website but it is recommended that your Bizuno database be separate from your other databases.
   - Installing using composer with install Bizuno and all necessary libraries
   ```bash
   composer create-project phreesoft/bizuno
   ```
   - Navigate to the web root and you should see the installation page.
   - Fill out the fields and press Install. It takes about 10 seconds to create the database. Once complete yo should see the Bizuno home dashboard.
   - A pre-set ToDo list will be generated with applicable priorities. Some actions cannot be taken once journal entries have been made.
   - Please refer the the Bizuno help pages for operational tips and procedures.

2. **Manual Install**
   - Download the latest release of Bizuno from the GitHub server into your website document root folder and unzip the file. (e.g., `/var/www/html`).[Bizuno @GitHub](https://github.com/phreesoft/bizuno)
   - Navigate to the web root and you should see the installation page.
   - Fill out the fields and press Install. It takes about 10 seconds to create the database. Once complete yo should see the Bizuno home dashboard. The auto-installer will create the file bizunoCFG.php from the sample file in the package. The file can be created manually if you have special requirements, i.e. want your data files stored in a private folder. Either way, the installer will verify db connectivity and install the core tables.
   - A pre-set ToDo list will be generated with applicable priorities. Some actions cannot be taken once journal entries have been made.
   - Please refer the the Bizuno help pages for operational tips and procedures.

**WordPress Plugin Installation**
- From the Wordpres plugin page, click on upload and search for the bizuno-accounting (search: Bizuno) plugin. Click on Install to retrieve the plugin from the WordPress Repository.
- Activate the plugin and log in to WordPress. The latest Bizuno library plugin (yes, it is a seperate plugin) will be retrieved from the PhreeSoft server if it is not present on your server server.
- Once activated, Bizuno can be accessed from the WordPress admin menu (requires user authorization). This will open a new tab in your browser and land on the install page. Fill out the form and press Install.
- Bizuno will create a new page with the slug bizuno. If you use permalinks to access pages via slug, accessing Bizuno from this point can be by navigating directly to: [My Business](https://www.yourdomain.com/bizuno)
- NOTE: Bizuno keeps a separate user list from the standard Wordpress users table. The user that installs Bizuno will have their account created automatically and assumed to be the administrator within Bizuno. Once installed, new users can be added from within Bizuno.
- Please refer the the Bizuno help pages for operational tips and procedures.

**Cloud Hosting**
- Sign up for PhreeSoft’s cloud hosting at [phreesoft.com](https://www.phreesoft.com). No local installation is required. Phreesoft will assist in the configuration and maintenance of Bizuno.[ISP Bizuno Hosting](https://www.phreesoft.com/product/hosted-bizuno/)

## License
Bizuno is licensed under the GNU Affero General Public License v3 (AGPL3). See [LICENSE](LICENSE) for details.[](https://www.gnu.org/licenses/agpl-3.0.txt)
