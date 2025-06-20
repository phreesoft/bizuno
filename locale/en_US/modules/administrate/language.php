<?php
/*
 * Language translation for Administrate module
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-06-03
 * @filesource /locale/en_US/module/administrate/language.php
 */

$lang = [
    'title' => 'Bizuno Administration',
    'description' => 'Bizuno ERP core application.',
    // Backup/Restore
    'queries' => 'Queries',
    'desc_backup' => 'Backup creates a zip compressed download file that contains the database information for your business. If selected, the business data files can also be included. Note: including the business data files will increase the download file size and take longer to process.',
    'desc_backup_all' => 'Include Business Data Files (larger download, longer wait)',
    'msg_restore_success' => 'Restore complete! Press OK to complete the restore, sign off and return to the welcome screen.',
    'msg_backup_success' => 'Your backup is ready to download from the list below.',
    'msg_restore_confirm' => 'Warning! This operation will delete and re-write the database. Are you sure you want to continue?',
    'audit_log_backup' => 'Backup and Clean Transaction History',
    'audit_log_backup_desc' => 'This operation creates a downloaded backup of your transaction history stored in your audit log database file. Downloading and cleaning this database table will help keep the database size down and reduce business backup file sizes. Backing up this log is recommended before cleaning out to preserve PhreeBooks transaction history.',
    'desc_audit_log_clean' => 'Cleaning out the audit log will remove all other records up to and including the selected date. It is recommended that the audit log be backed up prior to cleaning out the historical data.',
    'bizuno_upgrade' => 'Bizuno Upgrade',
    'bizuno_upgrade_desc' => 'Upgrade to the latest version of Bizuno with a push of a button. Before doing this, please make a backup of your business, download it to your local machine and make sure all users are not using the system.',
    'desc_security_fill' => 'Fill ALL Security Settings To:',
    // Extra tabs
    'new_tab_desc' => 'Select the Module/Table to create a new tab in and click Next.',
    'err_tab_in_use' => 'This tab has custom fields assigned to it, the fields must be deleted before this tab can be deleted! The fields are: ',
    // Extra fields
    'custom_field_manager' => 'Custom Field Manager',
    'xf_lbl_field' => 'Field Name',
    'xf_lbl_field_tip' => 'Fieldnames must be database compliant, they cannot contain spaces or special characters and must be 64 characters or less.',
    'xf_lbl_label' => 'Label to display with field',
    'xf_lbl_tag' => 'Tag used for Import/Export',
    'xf_lbl_tab' => 'Tab Member (Selection must be from the pull down list, add new tabs in Bizuno Settings)',
    'xf_lbl_tab_tip' => 'Additional tabs may be created in My Business -> Settings -> Extra Tabs',
    'xf_lbl_group' => 'Group Member',
    'xf_lbl_order' => 'Sort Order (within tab/group)',
    'xf_lbl_text' => 'Text Field',
    'xf_lbl_html' => 'HTML Field',
    'xf_lbl_text_length' => 'Maximum number of characters',
    'xf_lbl_link_url' => 'Link (URL)',
    'xf_lbl_link_image' => 'Link (Image)',
    'xf_lbl_link_inventory' => 'Link (Inventory Image)',
    'xf_lbl_int' => 'Integer',
    'xf_lbl_float' => 'Floating Point Number',
    'xf_lbl_db_float' => 'Single Precision',
    'xf_lbl_db_double' => 'Double Precision',
    'xf_lbl_checkbox_multi' => 'Checkbox (Multiple Entry)',
    'xf_lbl_select' => 'Dropdown List',
    'xf_lbl_data_list' => 'DataList (text field with suggestions)',
    'xf_lbl_radio' => 'Radio Button',
    'xf_lbl_radio_default' => 'Enter choices formatted as follows:<br />opt1:desc1;opt2:desc2;opt3:desc3<br />Key:<br />optX = The value to place into the database<br />descX = Textual description of the choice<br />Note: The first entry will be the default.',
    'xf_lbl_checkbox' => 'Check Box<br />(Yes or No Choice)',
    'xf_lbl_datetime' => 'Date and Time',
    'xf_lbl_timestamp' => 'DB Timestamp',
    'xf_err_field_exists' => 'Cannot rename field as the new field name already exists in the table!',
    'xf_msg_edit_warn' => 'WARNING: If the field type or properties of the field type are changed, data loss may occur! Specifically, shortening text field lengths (will truncate data) or changing types (e.g. text to integer will drop all non-numeric characters) may result in data loss.',
    // Support Ticket
    'ticket_desc' => 'Use this form to request support from the PhreeSoft support team. Please select a reason to help us properly route your request.',
    'ticket_attachment' => 'A document or picture may be attached to help clarify your request.',
    'ticket_question' => 'Question',
    'ticket_bug' => 'Found a Bug',
    'ticket_suggestion' => 'Suggestion',
    'ticket_my_account' => 'My Account',
    // User Manager - PhreeBooks
    'restrict_user_lbl' => 'Restrict Reps',
    'restrict_user_tip' => 'Restricts reps to only see accounts that are assigned to them.',
    'restrict_store_lbl' => 'Restrict to Store',
    'restrict_store_tip' => 'Restrict user to a specific store, All for no restrictions, Select store to restrict viewable activity to a specific store.',
    'restrict_period_lbl' => 'Lock Current Period',
    'restrict_period_tip' => 'Restrict the transactions of this user to the current period.',
    'store_id_lbl' => 'Home Store',
    'store_id_tip' => 'Sets the home store in multiple store businesses. Used for reports and listings for store stock levels, contacts, etc.',
    'gl_cash_lbl' => 'Cash GL Account',
    'gl_cash_tip' => 'Default account to use for cash transactions involving payment of invoices. Typically a Cash type account.',
    'gl_receivables_lbl' => 'Accounts Receivable GL Account',
    'gl_receivables_tip' => 'Default Accounts Receivables account. Typically an Accounts Receivable type account.',
    'gl_purchases_lbl' => 'Purchases GL Account',
    'gl_purchases_tip' => 'Default account to use for purchased items. This account can be over written through the individual item record. Typically an Inventory or Expense type account.',
    // Install notes
    'note_bizuno_install_1' => 'PRIORITY LOW: Create an account at www.PhreeSoft.com, if you do not have one, and then enter your credentials in your Bizuno Business under: Account (Login Name) -> Settings -> Bizuno tab -> Bizuno Settings icon -> Settings tab -> My PhreeSoft Account accordion.',
    // Fixed Assets
    'fa_intro' => 'This is where depreciation schedules can be managed. The row numbers represents the age of the asset in years.<br /><br />
        Percent Good values are represented as decimals (i.e. 80% => 0.80) and all % Good fields are limited to numerical values.<br /><br />
        To add a new schedule, replace the schedule title with a new title and add/delete/edit rows as necessary, press Save.<br />
        To remove a schedule, select the schedule and delete all the rows of the table and press Save.',
    'category'      => 'Category',
    'schedules'     => 'Schedules',
    'asset_num'    => 'Asset ID',
    'asset_type'   => 'Asset Type',
    'dep_value'    => 'Current Value',
    'dep_sched'    => 'Depreciation Schedule',
    'purch_cond'   => 'Condition',
    'serial_number'=> 'Make/Model/VIN',
    'gl_asset'     => 'GL Asset Account',
    'gl_dep'       => 'GL Depreciation Account',
    'gl_maint'     => 'GL Maintenance Account',
    'date_acq'     => 'Purchase Date',
    'date_maint'   => 'Last Mantainence Date',
    'date_retire'  => 'Retire Date',
    'err_no_sched' => 'The depreciation schedule has not been set and saved for this asset (%s), the current cost cannot be calculated. Depreciation schedules can be set in My Business -> Settings -> Extensions -> Fixed Assets settings.',
    'limit_store' => 'Limit to Store',
    'stock_all' => 'In Stock-All',
    'all_stores' => 'All Stores',
    'store_stock' => 'Stock-Here',
    'transfer_picklist' => 'Transfer Forms',
];
