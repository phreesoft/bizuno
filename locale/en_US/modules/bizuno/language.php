<?php
/*
 * Language translation for Bizuno module
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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-10-21
 * @filesource /locale/en_US/modules/bizuno/language.php
 */

$lang = [
    'title' => 'Bizuno ERP',
    'description' => 'Bizuno ERP core application.',
    // Installation
    'email_new_user_subject' => 'Welcome to Bizuno',
    // You will receive an email from us momentarily with a special ONE-TIME link to the Bizuno NEW Member Log-in page so you can instantly activate your membership. If you do not receive your confirmation email, contact us at biznerds@phreesoft.com. If you are using a spam filter on your email, please be sure that email coming from biznerds@phreesoft.com is directed to your inbox. If you do not, you may miss important subscription information, like your membership activation link.
    'email_new_portal_body' => 'Welcome to Bizuno,<br /><br />%s has added you to the list of users that have access to their business, %s. You will need to set your password before you can log into Bizuno. Please go to <a href="%s">%s</a> to initialize your account.<br />You will need to enter your confirmation code along with a new password. Your confirmation code will expire in 48 hours: <br /><b>%s</b>',
    'email_new_user_body' => 'Welcome to Bizuno,<br /><br />%s has added you to the list of users that have access to their business, %s. Since you already have an Bizuno username, all you have to do is go to the portal <a href="%s">%s</a> and log in using your current credentials.',
    'account_verified' => 'Your credentials have been verified at PhreeSoft. Any purchases you have made in our store should now be ready to download and install.',
    // Settings
    'timezone_lbl' => 'Timezone',
    'timezone_tip' => 'The timezone selected will be used for all posted transactions and reports. This will typically be your primary business location.',
    'phreesoft_user_lbl' => 'PhreeSoft Account Email',
    'phreesoft_user_tip' => 'This is your email you used to purchase your membership, support plans and extensions at the PhreeSoft.com store. It is used to verify your purchases used to enable your extensions.',
    'phreesoft_key_lbl' => 'Product Key',
    'phreesoft_key_tip' => 'Leave this blank if you have not purchased anything from PhreeSoft. Enter the product key of you Bizuno purchase, or, if not available a key from a product purchased from the PhreeSoft store using the username above.',
    'password_min_lbl' => 'Min Password Length',
    'password_min_tip' => 'Sets the minimum password length. Longer passwords lead to a more secure website. The minimum value is 8.',
    'max_rows_lbl' => 'Display Rows',
    'max_rows_tip' => 'Sets the default number of rows to show for datagrid (table) listings. Minimun value 10, maximum value 100.',
    'session_max_lbl' => 'Maximum Idle Time',
    'session_max_tip' => 'Sets the maximum session time to automatically sign off user when inactive in minutes. A value of zero keeps session alive automatically. Minimum value is 5 minutes and maximum value is 300 minutes.',
    'hide_filters_lbl' => 'Hide Dashboard Filters',
    'hide_filters_tip' => 'Select to hide the filter settings on the first row of dashboards that have user defined settings. Helpful to quickly review properties for truncated listings.',
    'number_precision_lbl' => 'Decimal Precision',
    'number_precision_tip' => 'Precision for decimal values [Default: 2]',
    'number_prefix_lbl' => 'Positive Prefix',
    'number_prefix_tip' => 'Sets the prefix for positive numeric values',
    'number_suffix_lbl' => 'Positive Suffix',
    'number_suffix_tip' => 'Sets the suffix for positive numeric values',
    'number_decimal_lbl' => 'Decimal Separator',
    'number_decimal_tip' => 'Decimal separator for numeric values',
    'number_thousand_lbl' => 'Thousands Separator',
    'number_thousand_tip' => 'Thousands separator for numeric values',
    'number_neg_pfx_lbl' => 'Negative Prefix',
    'number_neg_pfx_tip' => 'Sets the prefix for negative numeric values',
    'number_neg_sfx_lbl' => 'Negative Suffix',
    'number_neg_sfx_tip' => 'Sets the suffix for negative numeric values',
    'date_short_lbl' => 'Date Format',
    'date_short_tip' => 'Sets the format for calendar dates',
    'newLogo_lbl' => 'Select an image to upload to use as a new logo',
    'gl_tax_lbl' => 'Sales Tax GL Account',
    'gl_tax_tip' => 'Default account to use for Sales Tax collected. The sales tax passed should be equal to the calculated value based on the default rate specified below.',
    'tax_rate_id_lbl' => 'Default Tax Rate',
    'tax_rate_id_tip' => 'This tax will be the default tax used when importing API transactions.Tax rates can be set up in PhreeBooks settings.',
    'phreesoft_api' => 'PhreeSoft API',
    'api_user_lbl' => 'PhreeSoft.com Username',
    'api_pass_lbl' => 'PhreeSoft.com Password',
    'mail_mode_lbl' => 'Mail Transport',
    'mail_mode_tip' => 'Select CMS host to use the mail transport from the Bizuno host (e.g WordPress). Select server to use the installed PHP mail transport (typically postfix).',
    'oauth2_enable_lbl' => 'Requires OAUTH2',
    'oauth2_enable_tip' => 'Mail services such as Google require OAUTH2 authentication to communicate to their mail servers. Most CMS mailers only support a single account to connect to. Enabing this option will add a BCC to the sender IF the sender is not the same email address as the OAUTH2 allows. This copies the sent mail to the senders account for record keeping purposes.',
    // Labels
    'edit_dashboard' => 'Add/Remove Dashboards for menu: %s',
    'next_cust_id_num' => 'Next Customer ID Number',
    'next_shipment_num'=> 'Next Shipment Number',
    'next_vend_id_num' => 'Next Vendor ID Number',
    'password_now' => 'Current Password',
    'lead01' => 'One Day',
    'lead02' => 'Two Days',
    'lead07' => 'One Week',
    'lead14' => 'Two Weeks',
    'lead30' => 'One Month',
    // Messages
    'roles_restrict' => 'Do not allow access through the www.bizuno.com portal. i.e. For internal use only, members of this role cannot access your business.',
    'msg_module_delete_confirm' => 'Are you sure you want to un-install this module. All database data and files associated with this module will be lost!',
    'msg_module_upgraded' => 'Successfully upgraded module %s to release %s',
    'msg_upgrade_success' => 'The upgrade was successful! Press OK to close this message and sign off of Bizuno to clear your cache.',
    'msg_encryption_changed' => 'The encryption key has been changed.',
    'msg_no_shipments_found' => 'No shipments have been found that have shipped on this date!',
    'msg_new_user' => 'Congratulations! Your business has been created.<br /><br />
        Since you already have an account, click <a href="%s">HERE</a> to access the portal, log in and get started. You new business will be named My Business and should be changed during installation. If you have any questions, please out a support ticket (if logged into Bizuno) or email us at biznerds@phreesoft.com.<br />
        We hope you enjoy the application.<br><br>The PhreeSoft Development Team.',
    // Error Messages
    'err_install_module_exists' => 'Module %s is already installed! The installation was skipped.',
    'err_role_undefined' => 'The role is a required field! Please select a role for this user.',
    'err_delete_user' => 'You cannot delete the user account that you are logged in as!',
    'err_encryption_not_set' => 'The encryption key has not been set! To set a key, go to My Company -> Settings -> Bizuno tab -> Bizuno Settings -> Tools tab.',
    // General
    'table_stats' => 'Table Statistics',
    'dashboard_columns' => 'Dashboard Columns',
    'date_range' => 'Manager Date Range',
    'grid_rows' => 'Grid Rows',
    'icon_set' => 'Icon Set',
    // Buttons
    'btn_security_clean' => 'Clean Data Security Values',
    'wrong_email' => 'Could not find e-mail address for your user.',
    'request_pass'=> 'Your password reset request has been sent to your email address!',
    'email_sub_request' => 'Lost Password Reset',
    'email_request_pass' => 'Dear %s,<br /><br /> We have received a request to reset your password please go to <a href="%s">%s</a> and type in the reset code.<br /><br />If you have not sent this request ignore this e-mail.<br /><br />Your reset code: %s',
    'pass_does_not_match' => 'The passwords do not match.',
    'plz_fill' => 'Please fill out the entire form.',
    'wrong_code_time' => "This is the wrong code or time expired.",
    'password_changed' => 'Your password has been successfully changed.',
    // PhreeForm processing/separators
    'pf_rma_status' => 'RMA Status (status)',
//  'pf_proc_cur_exc' => 'Convert to Currency',
    'pf_proc_json' => 'JSON Decode',
    'pf_proc_json_fld' => 'JSON Decode Field',
    'pf_proc_neg' => 'Negate',
    'pf_proc_lc' => 'Lowercase',
    'pf_proc_uc' => 'Uppercase',
    'pf_proc_date' => 'Formatted Date',
    'pf_proc_datelong' => 'Date with Time',
    'pf_proc_today' => "Today's Date",
    'pf_proc_rnd0d' => 'Round (Integer)',
    'pf_proc_rnd2d' => 'Round (2 decimal)',
    'pf_proc_n2wrd' => 'Number to Words',
    'pf_proc_null0' => 'Null if Zero',
    'pf_proc_blank' => 'Blank Out Data',
    'pf_proc_yesbno' => 'Yes - Blank No',
    'pf_proc_printed' => 'Printed Flag',
    'pf_proc_precise' => 'Precise',
    'pf_proc_rep_id' => 'Sales Rep',
    'pf_cur_null_zero' => 'Currency (Null if Zero)',
    'pf_sep_comma' => 'Comma (,)',
    'pf_sep_commasp' => 'Comma-Space',
    'pf_sep_dashsp' => 'Dash-Space',
    'pf_sep_sepsp' => 'Separator-Space',
    'pf_sep_newline'=> 'Line Break',
    'pf_sep_semisp' => 'Semicolon-space',
    'pf_sep_delnl' => 'Skip Blank-Line Break',
    'pf_sep_space1' => 'Single Space',
    'pf_sep_space2' => 'Double Space',
    // Profile
    'gmail_address' => 'gMail Address',
    'gmail_address_tip' => 'Enter your Google email address if you plan on using Google integration (Calendar, etc.). Note that your browser must also be logged into your Google account under the same email address for the calendar to load.',
    'gmail_zone' => 'gMail GeoZone',
    'gmail_zone_tip' => 'Select the Geozone to use as the Google Calendar default',
    // Reminder dashboard
    'reminders' => 'Reminders',
    'frequency' => 'Frequency',
    'start_date' => 'Date Created',
    'next_date' => 'Next Reminder',
    'reminder_edit' => 'Reminder Editor',
    'reminder_desc' => 'Enter the information below, All fields are required.',
    // Fixed Assets
    'fa_schedules' => 'FA Schedules',
    'calculate_cost'=> 'Calculate Current Value',
    'fa_type_bd' => 'Building',
    'fa_type_pc' => 'Computer',
    'fa_type_fn' => 'Furniture',
    'fa_type_ld' => 'Land',
    'fa_type_ma' => 'Machinery',
    'fa_type_sw' => 'Software',
    'fa_type_te' => 'Tools and Equipment',
    'fa_type_vh' => 'Vehicle',
    'used'       => 'Used',
    // Tools
    'admin_status_update' => 'Change Various Reference Numbers',
    'admin_encrypt_update' => 'Create/Change Encryption Key',
    'admin_encrypt_old' => 'Current Encryption Key',
    'admin_encrypt_new' => 'New Encryption Key',
    'admin_encrypt_confirm' => 'Re-Enter New Key ',
    'admin_fix_comments' => 'Check/Repair Database Comments',
    'desc_update_comments' => '<p>This tool iterrates through your database tables to update the comments used to set positioning, styling and formatting the database fields. Generally this tool does not need to be run more than once or only after recommended by PhreeSoft after an update. If no changes are necessary, this tool will not touch your database.</p><p>Remember to backup your database before running this tool.</p>',
    'admin_fix_tables' => 'Synchronize Database Tables',
    'desc_update_tables' => '<p>This tool verfies that the datbase table structure matches the latest release structure. Generally this tool does not need to be run more than once or only after recommended by PhreeSoft after an update. If no changes are necessary, this tool will not touch your database.</p><p>Remember to backup your database before running this tool.</p>',
    'fa_recalc_title' => 'Update Depreciated Value in Bulk',
    'fa_recalc_desc' => 'This tool updates the current value of ALL of your active assets. Calculations are based off of the acquisition date and current calendar year. The database values are updated.',
    'db_engine' => 'DB Engine',
    'db_rows' => '# Rows',
    'db_collation' => 'Collation',
    'db_data_size' => 'Data Size',
    'db_idx_size' => 'Index Size',
    'db_next_id'   => 'Next Row ID',
    'new_tab' => 'New Custom Tab',
];
