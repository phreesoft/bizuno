<?php
/*
 * @name Bizuno ERP - QuickBooks Conversion Extension - Conversion Map file
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
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    PhreeSoft Proprietary
 * @version    7.x Last Update: 2025-02-26
 * @filesource /EXTENSION_PATH/xfrQuickBooks/map.php
 */

namespace bizuno;

function mapFilename($idx) {
    $files = [
        'chart'     => 'chart.csv',
        'inventory' => 'inventory.csv',
        'assemblies'=> 'assemblies.csv',
        'customers' => 'customers.csv',
        'vendors'   => 'vendors.csv',
        'j2'        => 'general_journal.csv',
        'j2x'       => 'funds_transfer.csv',
        'j3'        => 'vendor_quotes.csv',
        'j4'        => 'purchase_orders.csv',
        'j6'        => 'bills.csv',
        'j7'        => 'vendor_credits.csv',
        'j9'        => 'customer_quotes.csv',
        'j10'       => 'sales_orders.csv',
        'j12'       => 'sales.csv',
        'j13'       => 'customer_credits.csv',
        'j16'       => 'adjustments.csv',
        'j18'       => 'receive_payment.csv',
        'j20'       => 'bill_payment.csv'];
    return $files[$idx];
}

function mapGLTypes() {
    $glTypes = [ // copied and enhanced from function selGLTypes()
        't0' => ['id'=> 0,'text'=>'Bank',                      'asset'=>true, 'acctNum'=>10000], // Cash
        't2' => ['id'=> 2,'text'=>'Accounts Receivable',       'asset'=>true, 'acctNum'=>12000], // Accounts Receivable
        't4' => ['id'=> 4,'text'=>'Inventory',                 'asset'=>true, 'acctNum'=>13000], // Inventory
        't6' => ['id'=> 6,'text'=>'Other Current Asset',       'asset'=>true, 'acctNum'=>17000], // Other Current Assets
        't8' => ['id'=> 8,'text'=>'Fixed Asset',               'asset'=>true, 'acctNum'=>15000], // Fixed Assets
        't10'=> ['id'=>10,'text'=>'Accumulated Depreciation',  'asset'=>false,'acctNum'=>16000], // Accumulated Depreciation
        't12'=> ['id'=>12,'text'=>'Other Assets',              'asset'=>true, 'acctNum'=>19000], // Other Assets
        't20'=> ['id'=>20,'text'=>'Accounts Payable',          'asset'=>false,'acctNum'=>21000], // Accounts Payable
        't23'=> ['id'=>22,'text'=>'Other Current Liability',   'asset'=>false,'acctNum'=>25000], // Other Current Liability
        't22'=> ['id'=>22,'text'=>'Credit Card',               'asset'=>false,'acctNum'=>25000], // Credit Card (Specific to QuickBooks Map to Other Current Liability)
        't24'=> ['id'=>24,'text'=>'Long Term Liability',       'asset'=>false,'acctNum'=>27000], // Long Term Liabilities
        't30'=> ['id'=>30,'text'=>'Income',                    'asset'=>false,'acctNum'=>40000], // Income
        't31'=> ['id'=>30,'text'=>'Other Income',              'asset'=>false,'acctNum'=>40000], // Other Income (Specific to QuickBooks Map to Income)
        't32'=> ['id'=>32,'text'=>'Cost Of Goods Sold',        'asset'=>true, 'acctNum'=>50000], // Cost of Sales
        't34'=> ['id'=>34,'text'=>'Expense',                   'asset'=>true, 'acctNum'=>60000], // Expenses
        't35'=> ['id'=>34,'text'=>'Other Expense',             'asset'=>true, 'acctNum'=>60000], // Other Expenses (Specific to QuickBooks Map to Expenses)
        't40'=> ['id'=>40,'text'=>'Equity',                    'asset'=>false,'acctNum'=>30000], // Equity - Doesn't Close
        't42'=> ['id'=>42,'text'=>'Equity - Gets Closed',      'asset'=>false,'acctNum'=>29000], // Equity - Gets Closed
        't44'=> ['id'=>44,'text'=>'Equity - Retained Earnings','asset'=>false,'acctNum'=>39000]];// Equity - Retained Earnings
    if (getModuleCache('xfrQuickBooks', 'settings', 'general', 'gl_digits', 5) == 4) { // correction for five digit account numbers
        foreach ($glTypes as $idx => $row) { $glTypes[$idx]['acctNum'] = $row['acctNm'] / 10; }
    }
    return $glTypes;
}
/**
 * Maps QuickBooks inventory types to Bizuno types, if Bizuno inventory type is empty, the row will be skipped
 * @return type
 */
function mapInvTypes() {
    return [
        'Discount'          => 'ns',
        'Fixed Asset'       => '',
        'Group'             => '',
        'Inventory Assembly'=> 'sa',
        'Inventory'         => 'si',
        'Non Inventory'     => 'ns',
        'Other Charge'      => 'ns',
        'Sales Tax Group'   => '',
        'Sales Tax Item'    => '',
        'Service'           => 'sv'];

}

function qbInventory($row) { // map QB labels to Bizuno labels
    $invTypes = mapInvTypes();
    $temp     = [
        'sku'                 => !empty($row['UPC']) ? $row['UPC'] : $row['Item Name'],
        'description_short'   => $row['Item Name'],
        'inventory_type'      => $invTypes[$row['Item Type']],
        'inactive'            => empty($row['Is Active']) || $row['Is Active']!='Y' ? 1 : 0,
        'description_purchase'=> $row['Purchase Description'],
        'item_cost'           => $row['Item Cost'],
        'description_sales'   => $row['Sales Description'],
        'full_price'          => $row['Sales Cost'],
        'gl_inv'              => guessGL($row['Asset Account'],         $invTypes[$row['Item Type']], 'inv'),
        'gl_sales'            => guessGL($row['Account/Income Account'],$invTypes[$row['Item Type']], 'sales'),
        'gl_cogs'             => guessGL($row['Expense/COGS Account'],  $invTypes[$row['Item Type']], 'cogs'),
        'qty_min'             => $row['Reorder Point'],
        'upc_code'            => $row['UPC'],
//      '' => $row['Purchase Cost'], // seems to be the same value as Item Cost
//      '' => $row['Qty on Hand'], // will be calculated by the system
//      '' => $row['Qty on Order'], // will be generated by Bizuno when importing open purchase orders
//      '' => $row['Qty on Sales Order'], // will be generated by Bizuno when importing open sales orders
//      '' => $row['MFG Part No'], // not part of the default table fields
//      '' => $row['Unit of Measure'], // not part of the default table fields
//      '' => $row['BarCodeValue'], // not used, appears to be internal QB value
//      '' => $row['Product Type'], // not part of the default table fields
    ];
    return $temp;
}

function qbContacts($row, $type) {
    $key    = $type=='c' ? 'customers': 'vendors';
    $idKey  = $type=='c' ? 'Customer': 'Vendor';
    $glType = $type=='c' ? 'gl_sales' : 'gl_purchases';
    if ($type=='v') { // address fields are different for vendors, missing Bill To and Ship To, remap to customers
        $row['Bill To Address Line1']      = $row['Address Line1'];
        $row['Bill To Address Line2']      = $row['Address Line2'];
        $row['Bill To Address Line3']      = $row['Address Line3'];
        $row['Bill To Address Line4']      = $row['Address Line4'];
        $row['Bill To Address Line5']      = $row['Address Line5'];
        $row['Bill To Address City']       = $row['Address City'];
        $row['Bill To Address State']      = $row['Address State'];
        $row['Bill To Address Country']    = $row['Address Country'];
        $row['Bill To Address Postal Code']= $row['Address Postal Code'];
        $row['Company Name'] = !empty($row['Company Name']) ? $row['Company Name'] : (!empty($row['Address Line1']) ? $row['Address Line1'] : substr($row['Vendor'], 0, 32));
    }
    $temp = [
        'ContactType'            => $type,
        'ContactContactID'       => substr($row[$idKey], 0, 32),
        'ContactStatus'          => empty($row['Is Active']) || $row['Is Active']!='Y' ? 1 : 0,
        'ContactDefaultGLAccount'=> getModuleCache('phreebooks', 'settings', $key, $glType),
        'MainAddressPrimaryName' => !empty($row['Company Name']) ? $row['Company Name'] : $row['Bill To Address Line1'], // set priority
        'MainAddressContact'     => !empty($row['Company Name']) ? $row['Bill To Address Line1'] : $row['Contact'],
        'MainAddressAddress1'    => $row['Bill To Address Line2'],
        'MainAddressAddress2'    => $row['Bill To Address Line3'],
        'MainAddressCity'        => $row['Bill To Address City'],
        'MainAddressState'       => $row['Bill To Address State'],
        'MainAddressCountryISO3' => getCountry($row['Bill To Address Country']),
        'MainAddressPostalCode'  => $row['Bill To Address Postal Code'],
        'ContactAccountNumber'   => $row['Account Number'],
        'MainAddressEmail'       => $row['Email'],
        'MainAddressTelephone1'  => $row['Phone'],
        'MainAddressTelephone3'  => $row['Alt Phone'], // mobile
        'MainAddressTelephone4'  => $row['Fax'],
        'ContactFirstName'       => $row['First Name'],
        'ContactTitle'           => $row['Middle Name'],
        'ContactLastName'        => $row['Last Name'],
        'MainAddressNotes'       => $row['Notes'],
// ************* @TODO Advanced import (needs mapping and pre-set ranges) *********************
// Unassigned Bizuno address book fields:
// ContactNewsletter,ContactPreferredStore,MainAddressTelephone2,MainAddressWebsite,ShipAddressTelephone1,
// ShipAddressTelephone2,ShipAddressTelephone3,ShipAddressTelephone4,ShipAddressEmail,ShipAddressWebsite,ShipAddressNotes
//              'ContactTerms'        => $row['Terms'], // and $row['Credit Limit'] combined
//              'ContactDeptRepID'    => $row['Sales Rep'],
//              'ContactTaxRateID'    => $row['Sales Tax Code'], // used with customers only
//              'ContactTaxRateID'    => $row['TaxID'],  // used with vendors only
//              'ContactPriceSheetID' => $row['Price Level'],
//              'ContactStoreID'      => 0, // use default = 0
// *************************** Not Imported ****************************************
//              '' => $row['Company Name'], // Seems to  match Addres Line 1 or is blank
//              '' => $row['Bill To Address Line4'], // Not imported
//              '' => $row['Bill To Address Line5'], // Not imported
//              '' => $row['Alt Contact'],
//              '' => $row['Saluation'],
//              '' => $row['Customer Type'],
//              '' => $row['Sales Tax Item'],
//              '' => $row['Job Status'],
//              '' => $row['Job Start Date'],
//              '' => $row['Job Projected End'],
//              '' => $row['Job End Date	'],
//              '' => $row['Job Description'],
//              '' => $row['Job Type'],
//              '' => $row['Payment Method'],
    ];
    if ($type=='c') { // special fields for customers
        $temp['ContactGovID'] = $row['Resale Number'];
    } elseif ($type=='v') { // special fields for vendors

    }
    // Compare bill address 1, 2 and 3 and if same then skip the ship address
    if ($type=='c' && ($row['Bill To Address Line1'] <> $row['Ship To Address Line1'] ||
            $row['Bill To Address Line2'] <> $row['Ship To Address Line2'] ||
            $row['Bill To Address Line3'] <> $row['Ship To Address Line3'])) {
        $temp['ShipAddressPrimaryName']= $row['Ship To Address Line1'];
        $temp['ShipAddressAddress1']   = $row['Ship To Address Line2'];
        $temp['ShipAddressAddress2']   = $row['Ship To Address Line3'];
//          $temp[''] = $row['Ship To Address Line4']; // Not imported
//          $temp[''] = $row['Ship To Address Line5']; // Not imported
        $temp['ShipAddressCity']       = $row['Ship To Address City'];
        $temp['ShipAddressState']      = $row['Ship To Address State'];
        $temp['ShipAddressCountryISO3']= getCountry($row['Ship To Address Country']);
        $temp['ShipAddressPostalCode'] = $row['Ship To Address Postal Code'];
    }
    return $temp;
}

function qbJournal(&$main, $row=[], $jID=0) {
    $total      = 0;
    $BillPrefix = '';
    $itemPrefix = '';
    switch ($jID) {
        default:
        case  3:
        case  4: $BillPrefix = 'Vendor '; $itemPrefix = 'TxnLine ';
        case  6:
        case  7: if (empty($itemPrefix)) { $itemPrefix = 'TxnItemLine '; }
// TxnId,Terms,RefNumber,TxnDate,Vendor,Address Line 1,Address Line2,Address Line3,Address Line4,Address City,Address State,Address Postal Code,Address Country,
// Amount,Open Amount,Memo,AP Account,Due Date,TxnExpLine Account,TxnExpLine Amount,TxnExpLine Memo,TxnExpLine Customer,TxnExpLine Billable Status,TxnExpLine Class,
// TxnItemLine Item,TxnItemLine Description,TxnItemLine Quantity,TxnItemLine Cost,TxnItemLine Amount,TxnItemLine Customer,TxnItemLine Billable Status,TxnItemLine Class,
// Unit of Measure
            if (empty($main)) {
                $myBiz = getModuleCache('bizuno', 'settings', 'company');
                $main['General'] = [
                    'GLAccount'      => !empty($row['AP Account']) ? $row['AP Account'] : getModuleCache('phreebooks', 'vendors', 'gl_payables'),
                    'OrderID'        => $row['RefNumber'], // for vendors cannot be blank
                    'OrderDate'      => clean($row['TxnDate'], 'date'), // assume same format as Bizuno locale settings, convert to db format
//                  'OrderTax'       => !empty($row['Order Tax TBD']) ? $row['Order Tax TBD'] : '', // order level sales tax
                    'OrderNotes'     => $row['Memo'],
//                  'SalesTaxAmount' => !empty($row['Order Tax TBD']) ? $row['Order Tax TBD'] : 0, // or, if order level sales tax, this is priority 3
//                  'PurchaseOrderID'=> 'purch_order_id', // cart order number with prefix
//                  'OrderTotal'     => $row['SubTotal'], // exists in sales not in purchases needs to be calculated as QB doesn't provide this so it's added just before the record is written
//                  'SalesTaxPercent'=> 'tax_rate_id', //     if order level sales tax, this is priority 1
//                  'SalesTaxTitle'  => 'tax_rate_id', // or, if order level sales tax, this is priority 2
//                  'ShippingTotal'  => 'freight',
//                  'ShippingCarrier'=> 'method_code',
//                  'SalesRepID'     => 'rep_id',
                ];
                $main['Billing'] = [
                    'CustomerID'     => substr($row['Vendor'], 0, 32),
                    'CompanyName'    => !empty($row['Vendor']) ? $row['Vendor'] : $row[$BillPrefix.'Address Line 1'], // AddressLine 1 HAS A SPACE, ONLY ONE
                    'Address1'       => !empty($row['Vendor']) ? $row[$BillPrefix.'Address Line 1']: $row[$BillPrefix.'Address Line2'],
                    'Address2'       => !empty($row['Vendor']) ? $row[$BillPrefix.'Address Line2'] : $row[$BillPrefix.'Address Line3'],
                    'City'           => $row[$BillPrefix.'Address City'],
                    'State'          => $row[$BillPrefix.'Address State'],
                    'PostalCode'     => $row[$BillPrefix.'Address Postal Code'],
                    'Country'        => getCountry($row[$BillPrefix.'Address Country']),
//                  'Contact'        => '', // Not provided in feed
//                  'Address3'       => !empty($row['Vendor']) ? $row['Address Line3'] : $row[$prefix'.Line4'], // Not Supported in Bizuno
//                  'Address4'       => $row['Address Line4'], // Not Supported in Bizuno
//                  'Telephone'      => $row['Address Telephone'], // Not provided in feed
//                  'Email'          => $row['Address Email'], // Not provided in feed
                ];
                $main['Shipping'] = [ // Need to put in Company address if these fields are blank for purchases
                    'CompanyName'    => !empty($row['Ship To Line1'])      ? $row['Ship To Line1']      : $myBiz['primary_name'],
                    'Address1'       => !empty($row['Ship To Line2'])      ? $row['Ship To Line2']      : $myBiz['address1'],
                    'Address2'       => !empty($row['Ship To Line3'])      ? $row['Ship To Line3']      : $myBiz['address2'],
                    'City'           => !empty($row['Ship To City'])       ? $row['Ship To City']       : $myBiz['city'],
                    'State'          => !empty($row['Ship To State'])      ? $row['Ship To State']      : $myBiz['state'],
                    'PostalCode'     => !empty($row['Ship To Postal Code'])? $row['Ship To Postal Code']: $myBiz['postal_code'],
                    'Country'        => !empty($row['Ship To Country'])    ? getCountry($row['Ship To Country']) : $myBiz['country'],
//                  'Contact'        => '', // Not provided in feed
//                  'Address3'       => $row['Ship To Line3'], // Not Supported in Bizuno
//                  'Address4'       => $row['Ship To Line4'], // Not Supported in Bizuno
//                  'Telephone'      => $row['Telephone'], // Not provided in feed
//                  'Email'          => $row['Email'], // Not provided in feed
                ];
                $main['Payment'] = [
                    'Status'         => !empty($row['Entry Closed TBD']) ? 'cap' : '', // possible values are unpaid, auth, and cap [default: unpaid]
//                  'Method'         => 'method_code',
//                  'Title'          => 'title',
//                  'Authorization'  => 'auth_code', // Authorization code from credit cards that need to be captured to complete the sale
//                  'TransactionID'  => 'transaction_id', // Transaction from credit cards that need to be captured to complete the sale
//                  'Hint'           => 'hint',
                ];
            }
            $total  += !empty($row['Order Tax TBD']) ? floatval($row['Order Tax TBD']) : 0;
            if (!empty($row['TxnLine Received Quantity']) && $row['TxnLine Received Quantity']==$row[$itemPrefix.'Quantity']) { msgDebug("\nRecevied qty rcvd = {$row['TxnLine Received Quantity']} and qunty ordered = {$row[$itemPrefix.'Quantity']}, skipping."); break; } // line items received, move on
            $main['Item'][] = [ // generate next item record
                'ItemID'        => $row[$itemPrefix.'Item'],
                'Description'   => $row[$itemPrefix.'Description'], // if empty, then look it up
                'Quantity'      => $row[$itemPrefix.'Quantity'],
                'TotalPrice'    => floatval($row[$itemPrefix.'Amount']),
//              'SalesGLAccount'=> '', // Not provided in feed
//              'SalesTaxAmount'=> !empty($row[$itemPrefix.'Tax TBD']) ? $row[$itemPrefix.'Tax TBD'] : 0, // Not provided in feed
            ];
            $total  += !empty($row[$itemPrefix.'Amount']) ? floatval($row[$itemPrefix.'Amount']) : 0;
            break;
        case  9:
        case 10:
        case 12:
        case 13:
            if (empty($main)) {
                if (!is_numeric($row['SubTotal']) || !is_numeric($row['TxnLine Amount'])) { $GLOBALS['badInvoices'][] = $row['RefNumber']; }
                $main['General'] = [
                    'GLAccount'      => !empty($row['AR Account']) ? $row['AR Account'] : '',
                    'OrderID'        => $row['RefNumber'], // customer specified, force next invoice sequence
                    'OrderDate'      => clean($row['TxnDate'], 'date'), // assume same format as Bizuno locale settings, convert to db format
                    'OrderTax'       => !empty($row['Order Tax TBD']) ? floatval($row['Order Tax TBD']) : '', // order level sales tax
                    'OrderNotes'     => $row['Memo'],
                    'OrderTotal'     => floatval($row['SubTotal']),
                    'PurchaseOrderID'=> $row['PO Number'], // cart order number with prefix
                    'SalesTaxAmount' => floatval($row['SalesTaxTotal']), // or, if order level sales tax, this is priority 3
//                  'SalesTaxPercent'=> $row['SalesTaxPercentage'], //     if order level sales tax, this is priority 1
//                  'SalesTaxTitle'  => 'tax_rate_id', // or, if order level sales tax, this is priority 2
//                  'ShippingTotal'  => 'freight',
//                  'ShippingCarrier'=> 'method_code',
//                  'SalesRepID'     => $row['Sales Rep], // import this???
                ];
                $main['Billing'] = [
                    'CustomerID'     => substr($row['Customer'], 0, 32),
                    'CompanyName'    => !empty($row['Bill To Line1']) ? $row['Bill To Line1'] : $row['Customer'],
                    'Address1'       => $row['Bill To Line2'],
                    'Address2'       => $row['Bill To Line3'],
                    'City'           => $row['Bill To City'],
                    'State'          => $row['Bill To State'],
                    'PostalCode'     => $row['Bill To Postal Code'],
                    'Country'        => getCountry($row['Bill To Country']),
//                  'Contact'        => '', // Not provided in feed
//                  'Address3'       => $row['Bill To Line3'] : $row['Customer'], // Not Supported in Bizuno
//                  'Address4'       => $row['Bill To Line4'], // Not Supported in Bizuno
//                  'Telephone'      => $row['Bill To Telephone'], // Not provided in feed
//                  'Email'          => $row['Bill To Email'], // Not provided in feed
                ];
                $main['Shipping'] = [ // Need to put in Company address if these fields are blank for purchases
                    'CompanyName'    => !empty($row['Ship To Line1'])      ? $row['Ship To Line1']      : '',
                    'Address1'       => !empty($row['Ship To Line2'])      ? $row['Ship To Line2']      : '',
                    'Address2'       => !empty($row['Ship To Line3'])      ? $row['Ship To Line3']      : '',
                    'City'           => !empty($row['Ship To City'])       ? $row['Ship To City']       : '',
                    'State'          => !empty($row['Ship To State'])      ? $row['Ship To State']      : '',
                    'PostalCode'     => !empty($row['Ship To Postal Code'])? $row['Ship To Postal Code']: '',
                    'Country'        => !empty($row['Ship To Country'])    ? getCountry($row['Ship To Country'])    : '',
//                  'Contact'        => '', // Not provided in feed
//                  'Address3'       => $row['Ship To Line3'], // Not Supported in Bizuno
//                  'Address4'       => $row['Ship To Line4'], // Not Supported in Bizuno
//                  'Telephone'      => $row['Telephone'], // Not provided in feed
//                  'Email'          => $row['Email'], // Not provided in feed
                ];
                $main['Payment'] = [
                    'Status'         => !empty($row['Entry Closed TBD']) ? 'cap' : '', // possible values are unpaid, auth, and cap [default: unpaid]
//                  'Method'         => 'method_code',
//                  'Title'          => 'title',
//                  'Authorization'  => 'auth_code', // Authorization code from credit cards that need to be captured to complete the sale
//                  'TransactionID'  => 'transaction_id', // Transaction from credit cards that need to be captured to complete the sale
//                  'Hint'           => 'hint',
                ];
                $total += floatval($row['SubTotal']);
                $total += floatval($row['SalesTaxTotal']);
            }
            $total += !empty($row['Order Tax TBD']) ? floatval($row['Order Tax TBD']) : 0;
            $main['Item'][] = [
                'Quantity'      => $row['TxnLine Quantity'],
                'ItemID'        => $row['TxnLine Item'], // SKU
                'TotalPrice'    => floatval($row['TxnLine Amount']),
                'Description'   => $row['TxnLine Description'], // if empty, then look it up
//              'SalesGLAccount'=> '', // Not provided in feed, need to look this up and use default also
//              'SalesTaxAmount'=> !empty($row['TxnLine Tax TBD']) ? $row['TxnLine Tax TBD'] : 0, // Not provided in feed
            ];
            break;
    }
    msgDebug("\nReturning with total = $total");
    return $total;
}

/**
 * Converts the QuickBooks general journal entries to Bizuno format
 * @param array $main - Main record to build on
 * @param array $row - current row of csv data
 * @return float - value of the debit amount used to calculate the transaction total
 */
function qbGL(&$main, $row) {
    if (empty($main)) { $main['post_date'] = clean($row['TxnDate'], 'date'); }
    $main['items'][] = [
        'qty'          => 1,
        'gl_type'      => 'gl',
        'gl_account'   => guessGL($row['Line Account']),
        'debit_amount' => floatval($row['Line Debit']),
        'credit_amount'=> floatval($row['Line Credit']),
        'description'  => $row['Line Memo'],
        'post_date'    => $row['TxnDate'],
    ];
    return floatval($row['Line Debit']); // just need either debit or credit row
}

/**
 * Converts the QuickBooks general journal entries to Bizuno format
 * @param array $main - Main record to build on
 * @param array $row - current row of csv data
 * @return float - value of the debit amount used to calculate the transaction total
 */
function qbXfr($row=[]) {
    //TxnID,TxnDate,Transfer from Acct,Transfer to Acct,Class,Amount,Memo
    $date = clean($row['TxnDate'], 'date');
    $main = [
        'post_date'   => $date,
        'total_amount'=> $row['Amount'],
        'journal_id'  => 2,
        'invoice_num' => $row['TxnID'],
        'gl_acct_id'  => guessGL($row['Transfer from Acct']),
        'description' => 'GL: '.$row['Memo']];
    $main['items'][] = [
        'qty'          => 1,
        'gl_type'      => 'gl',
        'gl_account'   => guessGL($row['Transfer from Acct']),
        'debit_amount' => 0,
        'credit_amount'=> floatval($row['Amount']),
        'description'  => $row['Memo'],
        'post_date'    => $date];
    $main['items'][] = [
        'qty'          => 1,
        'gl_type'      => 'gl',
        'gl_account'   => guessGL($row['Transfer to Acct']),
        'debit_amount' => floatval($row['Amount']),
        'credit_amount'=> 0,
        'description'  => $row['Memo'],
        'post_date'    => $date];
    return $main; // just need either debit or credit row
}

function qbBank(&$main, $row=[], $jID=20) {
    // j18 => TxnID	Customer	Date	AR Account	RefNumber	Payment Method	Payment Amount	Deposit To Account	Memo	Applied to Invoice Ref	Applied to Invoice Amount
    // Applied to Invoice Disc Account	Applied to Invoice Disc Amount
    // 7B1DB0-1577859608	James Robertson grant-robertson@hotmail.c	1/1/2020	Accounts Receivable	181542		292.45	Paypal Bank	181542	181542	292.45		0

    // J20 => TxnID,AP Account,Bank Account,Credit Card Account,RefNumber,TxnDate,Payee,Payment Method,Payment Amount,Memo,Bill TxnDate,Applied to Bill Ref Number,
    // Amount,Balance Remaining,Discount Account,Discount Amount
    // 7B4BA8-1578341247,Accounts Payable,PNC Main,,1148,1/6/2020,Drum Workshop,Check,1602.67,Memo:CHECK 1148 076476316,12/20/2019,787047,780.23,0,,0
    $postDate= biz_date('Y-m-d', strtotime(in_array($jID, [18]) ? $row['Date'] : $row['TxnDate']));
    // find invoice sale record
    $refID   = in_array($jID, [18]) ? $row['RefNumber'] : $row['Applied to Bill Ref Number'];
    $rID     = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "invoice_num='".addslashes($refID)."'");
    // fetch the customer info
    $cID     = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes(substr(in_array($jID, [18]) ? $row['Customer'] : $row['Payee'], 0, 32))."'");
    msgDebug("\nTried to get cID and returned $cID");
    // find the invoice to get record number
    $address =  !empty($cID) ? dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID") : [];
    $glMain  = in_array($jID, [18]) ? guessGL($row['Deposit To Account']) : (!empty($row['Bank Account']) ? guessGL($row['Bank Account']) : guessGL($row['Credit Card Account']));
    $invoice = in_array($jID, [18]) ? 'DP'.$postDate : (!empty($row['RefNumber']) ? $row['RefNumber'] : $refID);
    if (empty($main)) {
        $main['main'] = [
            'id'            => 0,
            'journal_id'    => $jID,
            'invoice_num'   => $invoice,
            'total_amount'  => $row['Payment Amount'],
            'gl_acct_id'    => $glMain,
            'post_date'     => $postDate,
            'terminal_date' => $postDate,
    //      'period'        => 93,
    //      'admin_id'      => 1,
    //      'rep_id'        => 824,
            'description'   => 'Cash Receipt: '.(!empty($address['primary_name'])? $address['primary_name']: ''),
            'method_code'   => 'cod',
            'contact_id_b'  => $cID,
            'primary_name_b'=> !empty($address['primary_name'])? $address['primary_name']: '',
            'contact_b'     => !empty($address['contact'])     ? $address['contact']     : '',
            'address1_b'    => !empty($address['address1'])    ? $address['address1']    : '',
            'address2_b'    => !empty($address['address2'])    ? $address['address2']    : '',
            'city_b'        => !empty($address['city'])        ? $address['city']        : '',
            'state_b'       => !empty($address['state'])       ? $address['state']       : '',
            'postal_code_b' => !empty($address['postal_code']) ? $address['postal_code'] : '',
            'country_b'     => !empty($address['country'])     ? $address['country']     : 'USA',
            'telephone1_b'  => !empty($address['telephone1'])  ? $address['telephone1']  : '',
            'email_b'       => !empty($address['email'])       ? $address['email']       : '',
            'notes'         => $row['Memo']];
    }
    $main['item'][] = [
        'item_ref_id'  => $rID,
        'gl_type'      => 'pmt',
        'qty'          => 1,
        'description'  => 'Inv# '.$refID,
        'debit_amount' => in_array($jID, [18]) ? 0 : $row['Amount'],
        'credit_amount'=> in_array($jID, [18]) ? $row['Amount'] : 0,
        'gl_account'   => in_array($jID, [18]) ? guessGL($row['AR Account']) : guessGL($row['AP Account']),
        'trans_code'   => $refID,
        'post_date'    => $postDate];
    msgDebug("\nReturning with total = {$row['Amount']}");
    return $row['Amount'];
}
