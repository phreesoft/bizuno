<?php
/*
 * Language translation for QuickBooks Import extension
 *
 * NOTICE OF LICENSE
 * This software may be used only for one installation of Bizuno when
 * purchased through the PhreeSoft.com website store. This software may
 * not be re-sold or re-distributed without written consent of PhreeSoft.
 * Please contact us for further information or clarification of you have
 * any questions.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to automatically upgrade to
 * a newer version in the future. If you wish to customize this module, you
 * do so at your own risk, PhreeSoft will not support this extension if it
 * has been modified from its original content.
 *
 * @name       Bizuno ERP
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    PhreeSoft Proprietary
 * @version    7.x Last Update: 2020-08-15
 * @filesource /locale/en_US/ext/xfrQuickBooks/language.php
 */

$lang = [
    'title' => 'QuickBooks Import',
    'description' => 'The QuickBooks import extension imports quickbooks files and accounting information into Bizuno.',
    'msg_restore_confirm' => 'Are you ready to start the QuicBooks import process? You should make a backup of your current Bizuno database before proceeding.',

    'title_step1' => 'Step 1 - Import Chart of Accounts',
    'desc_step1' => 'This step imports your chart of accounts. The CSV formatted file chart.csv is required. This step will also generate the trial balance for '
    . 'later use to adjust your beginning balances. It is recommended to do a backup after this step.<br />'
    . 'Since QuickBooks doesn\'t require account numbers, we make some assumptions. Now is a good time to review your imported chart and update your default gl '
    . 'accounts before importing anything further. Open a new browser tab and go to Admin -> Settings -> Bizuno tab and review the PhreeBooks, Inventory and Contacts module settings.',

    'title_step2' => 'Step 2 - Import Inventory',
    'desc_step2' => 'This step imports your inventory items. It consists of two parts. First, regular stock and non-stock items. Second, inventory assemblies. '
    . 'As with every major step, please remeber to make a backup following this step.',

    'title_step3' => 'Step 3 - Import Inventory Assemblies',
    'desc_step3' => 'This step imports your inventory assembly bill of materials.',

    'title_step4' => 'Step 4 - Import Customers',
    'desc_step4' => 'Import customers, some preparation is necessary. Customer IDs will be generated based on your user settings. If email or telephone are chosen '
    . 'and the fields are blank, then an auto-increment value will be used.',

    'title_step5' => 'Step 5 - Import Vendors',
    'desc_step5' => 'Import vendors, some preparation is necessary. Vendor IDs will be generated based on your user settings. If email or telephone are chosen and '
    . 'the fields are blank, then an auto-increment value will be used.',

    'title_step6' => 'Step 6 - Inventory Adjustments',
    'desc_step6' => 'Creates adjustments to match posted purchases and sales with inventory balances to create starting inventory stock levels.',

    'title_step7' => 'Step 7 - Purchases',
    'desc_step7' => 'Posts all of your purchases.',

    'title_step8' => 'Step 8 - Sales',
    'desc_step8' => 'Posts all of your sales.',

    'title_step9' => 'Step 9 - General Ledger',
    'desc_step9' => 'Imports posted general ledger entries.',

    'title_step10' => 'Step 10 - Funds Transfers (GL Entries)',
    'desc_step10' => 'Imports fund transfers from cash and current liabilities accounts.',

    'title_step11' => 'Step 11 - POs',
    'desc_step11' => 'Creates journal records for open request for quotes, purchase orders, customer quotes, and sales orders. Closed entries of this type are '
    . 'not needed as actual purchases and sales reflect activity.',

    'title_step12' => 'Step 12 - SOs',
    'desc_step12' => 'Creates journal records for open request for quotes, purchase orders, customer quotes, and sales orders. Closed entries of this type are '
    . 'not needed as actual purchases and sales reflect activity.',

    'title_step13' => 'Step 13 - Vendor Quotes',
    'desc_step13' => 'Creates journal records for open request for quotes, purchase orders, customer quotes, and sales orders. Closed entries of this type are '
    . 'not needed as actual purchases and sales reflect activity.',

    'title_step14' => 'Step 14 - Customer Quotes',
    'desc_step14' => 'Creates journal records for open request for quotes, purchase orders, customer quotes, and sales orders. Closed entries of this type are '
    . 'not needed as actual purchases and sales reflect activity.',

    'title_step15' => 'Step 15 - Customer Payments',
    'desc_step15' => 'Banking - Customer Payments (deposits j18)',

    'title_step16' => 'Step 16 - Vendor Payments',
    'desc_step16' => 'Banking - Vendor Payments (deposits j20)',

    'title_step17' => 'Step 17 - Trial Balance',
    'desc_step17' => 'Adjust trial balance, compare new trial balance with current QB trial balance. Delete temp folder.',

    // settings
    'test_mode_lbl' => 'Test Mode',
    'test_mode_tip' => 'Limits the number of records to under 10 to verify your feed before converting the entire file. Good way to verify your csv file quickly.',
    'gl_digits_lbl' => 'Chart Acct # Range',
    'gl_digits_tip' => 'Specifies the number of digits to use for your Bizuno generated chart of accounts. If you total number of accounts is less than 100, use 4 here, otherwise stay with 5.',
    'inv_chunk_lbl' => 'Inventory Chunk',
    'inv_chunk_tip' => 'Number of inventory items to import per AJAX transaction, default 100.',
    'con_chunk_lbl' => 'Contacts Chunk',
    'con_chunk_tip' => 'Number of contacts to import per AJAX transaction, default 100.',
    'jrl_chunk_lbl' => 'Journal Chunk',
    'jrl_chunk_tip' => 'Number of entries to process per AJAX request, default 25. The post process can take some time so this number should stay below 30 to avoid script time-outs. There is not a large performance penalty for going with a small number.',
    'adj_chunk_lbl' => 'Adjustment Chunk',
    'adj_chunk_tip' => 'Number of entries to process per AJAX request, default 25. The post process can take some time so this number should stay below 30 to avoid script time-outs. There is not a large performance penalty for going with a small number.',
    ];
