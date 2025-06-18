<?php
/*
 * Language translation for Contacts module
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
 * @version    7.x Last Update: 2025-06-18
 * @filesource /locale/en_US/modules/contacts/language.php
 */

$lang = [
    'title' => 'Contacts',
    'description' => 'The contacts module manages all customer, vendors, employees, and branches used in the Bizuno Business Toolkit.',
    // Settings
    'primary_name_lbl' => 'Require Primary Name',
    'primary_name_tip' => 'Require Primary Name when creating a new address. For all required fields, posts will not be allowed unless there is data in the field. The exception is shipping addresses and customer/vendor contacts entered in the Customer/Vendor Manager screens.',
    'address1_lbl' => 'Require Address 1',
    'city_lbl' => 'Require City/Town',
    'state_lbl' => 'Require State/Province',
    'postal_code_lbl' => 'Require Postal Code',
    'telephone1_lbl' => 'Require Telephone 1',
    'email_lbl' => 'Require Email',
    // Titles
    'contacts_merge' => 'Merge Contacts',
    'sales_by_month' => 'Sales by Month',
    'purchases_by_month' => 'Purchases by Month',
    'payment_history' => 'Payment History',
    'payment_history_resp' => 'Current terms = %s with average sale of %s and average payment in %s days from expected terms.',
    // Messages
    'msg_contacts_merge_src' => '<h4>Merge Contacts</h4>Select a contact as the source contact. This contact will be removed after the merge:',
    'msg_contacts_merge_dest' => 'Select a contact as the destination contact. This contact will remain after the merge:',
    'msg_contacts_merge_bill' => 'Check to keep old main address as new Billing address.',
    // Error Messages
    'err_contacts_delete' => 'This record cannot be deleted because there are journal entries involving this contact. Try setting to Inactive.',
    'err_contacts_delete_address' => 'The address cannot be deleted since it is a main address, delete the contact instead!',
    // CRM Defines
    'crm_dg_notes' => 'To enter a valid Contacts entry, the Contact ID and Name/Business must be present. If either is left blank, the record will not be saved.',
    'contacts_crm_new_call' =>'New Call',
    'contacts_crm_call_back' =>'Return Call',
    'contacts_crm_follow_up' =>'Follow Up',
    'contacts_crm_new_lead' =>'New Lead',
    // API
    'conapi_desc' => 'The Contacts API currently supports the base contacts table, one main address and one shipping address for both inserts and updates. Extra custom fields are supported. To import an contacts file:<br>1. Download the contacts template which lists the field headers and descriptions.<br>2. Add your data to your .csv file.<br>3. Select the file and press the import icon.<br>The results will be displayed after the script completes. Any errors will also be displayed.',
    'conapi_template' => 'Step 1: Download the contacts template => ',
    'conapi_import' => 'Step 2: Add your contacts to the template, browse to select the file and press Import. ',
    'conapi_export' => 'OPTIONAL: Export your contacts database table in .csv format for backup => ',
    // Tools
    'close_j9_title' => 'Bulk Close Customer Quotes',
    'close_j9_desc' => 'This tool closes all Customer Quotes prior to the date specified. ',
    'close_j9_label' => 'Close all Customer Quotes Before: ',
    'close_j9_success' => 'The number of journal entries closed was: %s',
    'sync_attach_title' => 'Remove/Repair Attachments',
    'sync_attach_desc' => 'Clean orphaned attachments and repair attachment flag.',
    // Returns
    'returns_title' => 'Returns Manager',
    'returns_desc' => 'The Returns Manager extension provides the ability to track products returned by customers.',
    'edi_description' => 'The EDI module allow connections to your vendors and customers through the X.12 industry standard.',
    'promos_title' => 'Promotion Manager',
    'promos_desc' => 'The crmPromos module manages and distributes promotional emails to specified customer lists.',
    'crm_title' => 'Customer CRM Manager',
    'crm_desc' => 'The customer CRM manager has many useful features to manage your customer base. Features include including multiple contacts to a customer, sales reports, sales tracking, reminders and more.',
    'drop_ship_title' => 'Drop Ship Action Button',
    'drop_ship_desc' => 'This extension adds an icon to a customer Sale/Sales Order action bar (Sale Manager screen). When selected, Bizuno will generate a purchase order from the products preferred vendor to ship directly to the orders ship to address. All items on the Sale/Sales Order must be from the same preferred vendor.',
    'fulfill_title' => 'Order Fulfillment',
    'fulfill_desc' => 'The order fulfillment module alters the Customers -> Orders function to ship open sales orders. This module is helpful for shipping departments where limited order details are desired.',
    'item_disc_title' => 'Line Item Discount',
    'item_disc_desc' => 'The customer line item discount extension adds an expandable row to the item grid on orders to allow for discounts at the individual item level.',
    'projects_title' => 'CRM Projects',
    'projects_desc' => 'New customer project tracker',
    'pos_title' => 'Point of Sale',
    'pos_desc' => 'This extension creates a Point of Sale (POS) system integrated with Bizuno and PhreeBooks accounting.',
    'msg_return_reason' => 'What is the reason for this return?',
    'close_details' => 'Closing Information',
    'item_details' => 'Items to be Returned',
    'receive_details' => 'Items Received',
    'warranty_exp_date'=>'Warranty Exp Date',
    'preventable' => 'Preventable',
    'at_fault' => 'At Fault',
    'return_num' => 'Return #',
    'entered_by' => 'Entered By',
    'caller_name' => 'Caller Name',
    'received_by' => 'Received By',
    'closed_by' => 'Closed By',
    'invoice_date' => 'Date Invoiced',
    'receive_date' => 'Date Received',
    'warranty_date' => 'Warranty Exp Date',
    // custPromo
    'promos_mgr' => 'Promotion Manager',
    'promo_desc' => 'Select from the list of available promotions and recipient list below to send out promotion emails. Click Start to begin.',
    'start_date' => 'Start Date',
    'end_date' => 'End Date',
    'promo_date' => 'Promotion Date',
    'promos_list' => 'Available Promotions',
    'promos_option' => 'Limit List To',
    'promo_instr' => 'How to operate this module...',
    'promo_edit_instr' => 'Please make your changes and press Save when finished.',
    'desc_promo_empty' => 'From the Available Promotions above, press the New icon to create a new promotion or double click on an existing promotion to edit.',
    'msg_copy_promo' => 'Enter a new Title for this Promotion and press Ok',
    'msg_email_progress' => 'Sent %s of %s email addressses',
    'msg_email_complete' => 'Mailing Complete',
    'marketing' => 'Marketing',
    'newsletter' => 'Newsletter',
    // CRM
    'restrict_user_lbl' => 'Restrict Reps',
    'restrict_user_tip' => 'Restricts reps to only see accounts that are assigned to them.',
    'enable_crm_lbl' => 'Enable CRM functionality',
    'enable_crm_tip' => 'CRM functionality includes tools and features to better manage your customer base.',
    'err_multiple_vendors' => 'More than one preferred vendor was selected, this process only works with a single preferred vendor. The Purchase Order was not created!',
    'err_no_vendor_found' => 'No vendors were found on the preferred vendor list for this order that have inventory types that affect you cost of goods sold (type stock, assembly, etc.)',
    // Projects
    'projects' => 'Projects',
    'new_proj' => 'New Project',
    'proj_num' => 'Project #',
    'market' => 'Market',
    'oem' => 'OEM',
    'market' => 'Market',
    'created_by' => 'Created By',
    'created_date' => 'Created Date',
    'assigned_to' => 'Assigned To',
    'assigned_date' => 'Assigned Date',
    'reminder_date' => 'Reminder Date',
    'working_by' => 'Working By',
    'working_notes' => 'Working Notes',
    'contact_id' => 'Customer Search/ID',
    //settings
    'rounding_lbl' => 'Round ?',
    'rounding_tip' => 'Rounding is already set in PhreeBooks module, not sure what this is for?',
    'multiLine_lbl' => 'Enable Multi-line Items',
    'multiLine_tip' => 'Enable Multiline for small displays or when line item discounts are enabled. This feature will move the sales tax and GL account to an expandable second line in the grid for each item.',
    'email_to_lbl' => 'Received EDI Email',
    'email_to_tip' => 'Email address to send confirmations to once an EDI transmission has been received. This is typically your sales or customer service email address.',
    'email_error_lbl' => 'EDI Errors Email',
    'email_error_tip' => 'Email address to send failed EDI transactions. This email contains the source data string and can be used to identify EDI format errors. Typically this is your IT person.',
    'sku_cross_lbl' => 'Missing SKU Placeholder',
    'sku_cross_tip' => 'Enter a SKU to use as a placeholder. Bizuno will receive the purchase order and search for a matching SKU. If not found and a value exists here, the PO will be accepted and the SKU must be manually corrected in the newly created Sales Order.',
    'commodity_lbl' => 'Commodity',
    'commodity_tip' => 'Describe the product being shipped.',
];
