<?php
/*
 * @name Bizuno ERP - QuickBooks Conversion Extension - functions
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
 * @version    7.x Last Update: 2025-02-06
 * @filesource /EXTENSION_PATH/xfrQuickBooks/functions.php
 */

namespace bizuno;

/****************************************************** General functions *************************************/
/**
 * Reads a csv file into a keyed array
 * @param string $filename - name of the file to import into a keyed array
 * @return array - contents of the file keyed to the first row
 */
function readCSV($filename='') {
    ini_set('auto_detect_line_endings',TRUE);
//  $runaway= 0;
    if (!file_exists(BIZUNO_DATA."temp/qbfiles/$filename")) { return msgAdd("Cannot find file: $filename to import in folder BIZUNO_DATA/temp/qbfiles/"); }
    $handle= fopen(BIZUNO_DATA  ."temp/qbfiles/$filename", 'r');
    $keys  = fgetcsv($handle);
    $output= [];
    while (($values = fgetcsv($handle)) !== false) {
        if (sizeof($values) <= 1) { continue; } // blank lines, skip
        if (sizeof($keys) <> sizeof($values)) { return msgAdd("The csv file is malformed, the total number of columns are not the same between the header and data!"); }
        $output[] = array_combine($keys, $values);
//      $runaway++; if ($runaway >= 100) { break; }
    }
    fclose($handle);
    ini_set('auto_detect_line_endings',FALSE);
//msgDebug("\nReturning with keyed array = ".print_r($output, true));
    return $output;
}

/**
 * Tries to determine the best gl account to use based on the data available
 * @param string $title - Title of the GL account as specified from the QB feed
 * @param type $invType - Type of inventory item, i.e. field inventory_type value
 * @param type $glType - Type of gl account, choices are sales, inv, and cogs
 * @return string - best guess gl account, empty string if nothing found
 */
function guessGL($title='', $invType='', $glType='sales') {
    $accts= getModuleCache('phreebooks', 'chart');
    foreach ($accts as $acct) { if ($acct['title'] == $title) { return $acct['id']; } }
    if (empty($invType) || empty($glType)) { return ''; }
    $defs = getModuleCache('inventory', 'settings', 'phreebooks');
    if (!empty($defs[$glType.'_'.$invType])) { return $defs[$glType.'_'.$invType]; }
    return ''; // didn't find a match
}

/**
 * Writes the log file by appending it to the current working file
 */
function logWrite($step=0) {
    dbWriteCache();
    msgDebug("\nWriting log for step $step.");
    msgDebugWrite("upgrade_log_$step.txt", true, true);
}

/********************************************* Chart of Accounts ************************************************/
/**
 * Takes the QB chart and creates a Bizuno compatible chart, then imports it. Also generates the trial balance file for adjusting at the end of this process
 * @return type
 */
function processChart() {
    global $io;
    $chart  = [];
    $fn     = mapFilename('chart');
    $glTypes= mapGLTypes();
    $rows   = readCSV($fn);
    if (empty($rows)) { return msgAdd("Cannot find the chart file: BIZUNO_DATA/temp/qbfiles/$fn. Bailing..."); }
    foreach ($rows as $row) {
        $props = getAcctProps($row, getModuleCache('xfrQuickBooks', 'settings', 'general', 'gl_digits', 5), $glTypes);
        if ($props['id'] < 0) { continue; } // didn't find it
        $chart['account'][]= ['id'=>$props['acctNum'], 'type'=>$props['id'], 'title'=>$row['Account Name']];
        $chart['begbal'][] = ['acct'=>$props['acctNum'], 'balance'=>$row['Balance']];
    }
    setDefaults($chart, $glTypes); // sets chart['default'] glChart numbers to update settings, assumes first one
    $chart['account'] = sortOrder($chart['account'], 'id');
    $chart['account'] = sortOrder($chart['account'], 'type');
    foreach ($chart['account'] as $row) {
        $title = preg_replace('/&(?!#?[a-z0-9]+;)/', '&amp;', $row['title']);
        $chart['xml'][]    = "\t<account><id>{$row['id']}</id><type>{$row['type']}</type><title>$title</title></account>";
    }
    $fileChart = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n<!DOCTYPE xml>\n<ChartofAccounts>\n\t<defaults>\n";
    $fileChart.= implode("\n", $chart['defaults'])."\n\t</defaults>\n".implode("\n", $chart['xml'])."\n</ChartofAccounts>\n";
    $io->fileWrite($fileChart, 'temp/qbfiles/chart.xml');
    $io->fileWrite(json_encode($chart['begbal']), 'temp/qbfiles/trial_balance.txt');
    bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/chart.php", 'import');
    $_GET['data'] = 'temp/qbfiles/chart.xml';
    $pbChart = new phreebooksChart();
    if (!$pbChart->import()) { msgAdd('There was an error importing the chart. Please fix the error and try again.'); }
    return true; // since we are finished successfully
}

/**
 * Sets the defaults gl accounts after the verification of all gl accounts
 * @param array $chart - working chart of accounts
 */
function setDefaults(&$chart, $glTypes=[]) {
    $defaults = [];
    foreach ($chart['account'] as $acct) { if (empty($defaults[$acct['type']])) {
        msgDebug("\nAdding defaults for acct: ".print_r($acct, true));
        $defaults[$acct['type']] = ['id'=>$acct['type'], 'account'=>$acct['id'] ,'title'=>$glTypes["t{$acct['type']}"]['text']];
    } }
    foreach ($glTypes as $value) { // set some accounts defaults should they be blank
        if (!empty($defaults[$value['id']])) { continue; }
        msgDebug("\nsetting missing defaults gltype row = ".print_r($value, true));
        $defaults[$value['id']] = ['id'=>$value['id'], 'account'=>$value['acctNum'] ,'title'=>$value['text']];
        $chart['account'][]= ['id'=>$value['acctNum'], 'type'=>$value['id'], 'title'=>$value['text']];
    }
    foreach ($defaults as $row) {
        $chart['defaults'][] = "\t\t<type><id>{$row['id']}</id><account>{$row['account']}</account><title>{$row['title']}</title></type>";
    }
    $chart['defaults'] = sortOrder($chart['defaults'], 'id');
}

/**
 * Tries to get the best gl account number to use
 * @param array $row - indexed chart rows from source file
 * @return array including id and acctNum
 */
function getAcctProps($row, $glDigits=5, $glTypes=[]) {
    $glSkip = $glDigits==5 ? 10 : 5;
    if (empty($GLOBALS['usedAcctNums'])) { $GLOBALS['usedAcctNums'] = []; }
    foreach ($glTypes as $key => $value) {
        if ($row['Account Type'] != $value['text']) { continue; }
        if (!empty($row['Account Number'])) {
            $acctNum = $row['Account Number'];
        } else {
            while (true) {
                $acctNum = $glTypes[$key]['acctNum'];
                $glTypes[$key]['acctNum'] = $acctNum + $glSkip;
                if (!in_array($acctNum, $GLOBALS['usedAcctNums'])) { break; }
            }
        }
        $GLOBALS['usedAcctNums'][] = $acctNum;
        return ['id'=>$value['id'], 'acctNum'=>$acctNum];
    }
    msgAdd("Could not find a gl type for the value {$row['Account Type']}, it will not be used.");
    return ['id'=>-1, 'acctNum'=>'TBD'];
}

/********************************************* Step 2 - Inventory ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp file to iterate via import
 * @global \bizuno\class $io
 * @param string $key - key of source files to fetch source data
 * @param string $dest - destination filename without the extension (.csv)
 * @return array
 */
function getInvSource() {
    global $io;
//  $runaway= 0;
    $output = $stock = [];
    $rows   = readCSV(mapFilename('inventory'));
    $invTypes= mapInvTypes();
    if (empty($rows)) { return []; }
    foreach ($rows as $row) {
        if (!isset($invTypes[$row['Item Type']])) { msgAdd("\nFound new inventory type => {$row['Item Type']}"); } // try to catch for new types to add
        if ( empty($invTypes[$row['Item Type']]) || !isset($invTypes[$row['Item Type']])) { continue; } // not something Bizuno stores in inventory table
        if ( empty($row['Item Name'])) { continue; } // no sku specified
        if (!empty($row['Qty on Hand'])) { $stock[$row['Item Name']] = $row['Qty on Hand']; } // for later to generate the adjustments
        $output[] = qbInventory($row);
//      $runaway++; if ($runaway >= 10) { break; }
    }
    msgDebug("\nNumber of rows to import = ".sizeof($rows));
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizInvTmp.csv",     true, false, true);
    $io->fileWrite(json_encode($stock),  "temp/qbfiles/bizInvBalances.txt",true, false, true);
    return sizeof($output);
}

/**
 * Processes the inventory file and inventory assembly file
 * @return integer - number of rows remaining
 */
function processInventory() {
    global $io;
    $filePath = "temp/qbfiles/bizInvTmp.csv";
    if (!file_exists(BIZUNO_DATA.$filePath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$filePath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    $cnt  = 0;
    $data = [];
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'inv_chunk', 100)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/inventory/api.php", 'apiImport');
        $layout = [];
        $inv = new inventoryApi();
        $inv->apiImport($layout, $data, $verbose=true);
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$filePath); }
    else                  { $io->fileWrite(json_encode($rows), $filePath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 3 - Inventory Assemblies ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp file to iterate via import
 * @global \bizuno\class $io
 * @param string $key - key of source files to fetch source data
 * @param string $dest - destination filename without the extension (.csv)
 * @return array
 */
function getAssySource() {
//    $runaway= 0;
    $assyCnt= 0;
    $lastSKU= '';
    msgDebug("\nWorking with filename to read: ".mapFilename('assemblies'));
    $rows   = readCSV(mapFilename('assemblies'));
    if (empty($rows)) { msgAdd("No rows to process!"); return 0; }
    $row    = array_shift($rows);
    foreach ($rows as $row) {
        if (empty($row['Assembly Item Name'])) { continue; } // should never happen, but just in case the file is malformed
        $skuID= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($row['Assembly Item Name'])."'");
        if (empty($skuID)) { msgAdd("Assembly SKU: {$row['Assembly Item Name']} cannot bew found in the database, skipping."); continue; }
        if ($row['Assembly Item Name'] <> $lastSKU) {
            msgDebug("\nChanging inventory type for sku {$row['Assembly Item Name']} to ma.");
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['inventory_type'=>'ma'], 'update', "id=$skuID");
            $assyCnt++;
            $lastSKU = $row['Assembly Item Name'];
        }
        $desc  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "sku='".addslashes($row['Component Item Name'])."'");
        $bom   = ['ref_id'=>$skuID, 'sku'=>$row['Component Item Name'], 'description'=>$desc,'qty'=>floatval($row['Component Item Qty'])];
        msgDebug("\nWriting bom for SKU: {$row['Assembly Item Name']} with data: ".print_r($bom, true));
        $meta  = dbMetaGet(0, 'bill_of_materials', 'inventory', $skuID);
        $metaID= metaIdxClean($meta);
        dbMetaSet($metaID, 'bill_of_materials', $bom, 'inventory', $skuID);
    }
    msgDebug("\nNumber of rows to import = ".sizeof($bom));
    return $assyCnt;
}

/********************************************* Step 4 & 5 - Customers/Vendors ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp file to iterate via import
 * @global \bizuno\class $io
 * @param string $key - key of source files to fetch source data
 * @param string $dest - destination filename without the extension (.csv)
 * @param char $type = Contact type, c customers, v vendors
 * @return array
 */
function getContactSource($type='c') {
    global $io;
//  $runaway= 0;
    $output = $stock = [];
    $key    = $type=='c' ? 'customers': 'vendors';
    $fPath  = $type=='c' ? "temp/qbfiles/bizCustTmp.csv" : "temp/qbfiles/bizVendTmp.csv";
    $rows   = readCSV(mapFilename($key));
    if (empty($rows)) { return []; }
    foreach ($rows as $row) {
        $output[] = qbContacts($row, $type);
//      $runaway++; if ($runaway >= 10) { break; }
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    $io->fileWrite(json_encode($output), $fPath, true, false, true);
    return sizeof($output);
}

/**
 * Processes the inventory file and inventory assembly file
 * @param string $type - Type of contact, c customers or v vendors
 * @return integer - number of rows remaining
 */
function processContacts($type='c') {
    global $io;
    $fPath= $type=='c' ? "temp/qbfiles/bizCustTmp.csv" : "temp/qbfiles/bizVendTmp.csv";
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    $cnt  = 0;
    $data = [];
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'con_chunk', 100)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/contacts/api.php", 'apiImport');
        $layout = [];
        $inv = new contactsApi();
        $inv->apiImport($layout, $data, $verbose=true);
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

function getCountry($src='') {
    if (empty($GLOBALS['locales'])) { $GLOBALS['locales'] = localeLoadDB(); }
    if (empty($src))     { return getModuleCache('bizuno','settings','company','country'); } // company country
    if (strlen($src)==3) { return strtoupper($src); } // already ISO3
    foreach ($GLOBALS['locales']->Locale as $value) {
        if ($src == $value->Country->Title) { return $value->Country->ISO3; } // full name
        if ($src == $value->Country->ISO2)  { return $value->Country->ISO3; } // ISO2 format
    }
    return getModuleCache('bizuno','settings','company','country'); // no match, return business default country
}

/********************************************* Step 6: Inventory Adjustments ************************************************/
/**
 * Pulls the inventory current stock JSON file created during import inventory and matches with stock level imports through J6 and J12 to create beginning balances.
 * @global \bizuno\class $io
 * @return integer - Count of the number of entries to process
 */
function getAdjustments() {
    global $io;
//  $runaway= 0;
    $output = [];
    $iTypes = explode(',', COG_ITEM_TYPES);
    $iPath  = "temp/qbfiles/bizInvBalances.txt";
    if (!file_exists(BIZUNO_DATA.$iPath)) { msgAdd("Cannot find the inventory balances files! Inventory import must be completed BEFORE this step to generate the current stock level data."); return 0; }
    msgDebug("\nWorking in getAdjustments with source file to read: $iPath");
    $stock  = json_decode(file_get_contents(BIZUNO_DATA.$iPath), true); // current stock level
//  msgDebug("\nStored stock levels has ".sizeof($stock)." rows.");
    // add total sales
    $sales  = readCSV(mapFilename('j12'));
    if (empty($sales)) { msgAdd("No rows sales rows to process!"); return []; }
    msgDebug("\nWorking with jID = 12 and read filename to read: ".mapFilename('j12')." with ".sizeof($sales)." rows.");
    foreach ($sales as $row) {
        if (empty($row['TxnLine Item'])) { continue; } // no SKU
        if (empty($stock[$row['TxnLine Item']])) { $stock[$row['TxnLine Item']] = 0; }
        $stock[$row['TxnLine Item']] += floatval($row['TxnLine Quantity']); // add sales
    }
    // subtract total purchases
    $purch  = readCSV(mapFilename('j6'));
    if (empty($purch)) { msgAdd("No rows purchase rows to process!"); return []; }
    msgDebug("\nWorking with jID = 6 and read filename to read: ".mapFilename('j6')." with ".sizeof($purch)." rows.");
    foreach ($purch as $row) {
        if (empty($row['TxnItemLine Item'])) { continue; } // no SKU
        if (empty($stock[$row['TxnItemLine Item']])) { $stock[$row['TxnItemLine Item']] = 0; }
        $stock[$row['TxnItemLine Item']] -= floatval($row['TxnItemLine Quantity']); // subtract purchases
    }
    foreach ($stock as $sku => $qty) {
        if (!empty($row)) {
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id','inventory_type'], "sku='$sku'");
            if (!empty($inv['id']) && in_array($inv['inventory_type'], $iTypes)) { $output[] = ['sku'=>$sku, 'qty'=>$qty]; }
//          $runaway++; if ($runaway >= 10) { break; }
        }
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    msgDebug("\nAdjustment rows to import = ".print_r($output, true));
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizAdjTmp.txt", true, false, true);
    return sizeof($output);
}

function processAdjustments() {
    global $io;
    $fPath= "temp/qbfiles/bizAdjTmp.txt";
    $cnt  = $total = 0;
    $items= [];
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $items[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'adj_chunk', 25)) { break; }
    }
    if (sizeof($items)) {
        $post_date = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', 'start_date', "period=1");
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
        $ledger = new journal(0, $jID=16, $post_date);
        foreach ($items as $item) {
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['gl_inv','item_cost','description_short'], "sku='{$item['sku']}'");
            $ledger->items[] = [
                'qty'         => $item['qty'],
                'sku'         => $item['sku'],
                'gl_type'     => 'adj',
                'gl_account'  => $inv['gl_inv'],
                'debit_amount'=> $item['qty'] * $inv['item_cost'],
                'description' => $inv['description_short'],
            ];
            $total += $item['qty'] * $inv['item_cost'];
        }
        $ledger->items[] = [ // set the total
            'qty'          => 1,
            'gl_type'      => 'ttl',
            'gl_account'   => getModuleCache('inventory', 'settings',  'phreebooks', 'cogs_si'),
            'credit_amount'=> $total,
            'description'  => 'Adjustment Total',
        ];
        $ledger->Post();
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 7, 8, 11, 12 - Quote, SO, PO, Sales & Purchases Entries ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp journal file to iterate via import
 * @global \bizuno\class $io
 * @param string $key - key of source files to fetch source data
 * @param string $dest - destination filename without the extension (.csv)
 * @param char $type = Contact type, c customers, v vendors
 * @return array
 */
function getJournalSource($jID=0) {
    global $io;
    $GLOBALS['badInvoices'] = [];
// $runaway= 0;
    $total  = 0;
    $output = $main = [];
    msgDebug("\nWorking with jID = $jID and read filename to read: ".mapFilename('j'.$jID));
    $rows   = readCSV(mapFilename('j'.$jID));
    if (empty($rows)) { msgAdd("No rows to process!"); return 0; }
    $row    = array_shift($rows);
    while (true) {
        if (empty($row)) { break; } // should never happen, but just in case
        if (in_array($jID, [3,4,9,10]) && !empty($row['Is Fully Received']) && strtolower($row['Is Fully Received'])=='true') {
            msgDebug("\nRow has been fully received ({$row['Is Fully Received']}), skiping.");
            $row = array_shift($rows); continue;
        }
        $total += qbJournal($main, $row, $jID);
        $nextRow = array_shift($rows);
        if (!empty($nextRow['TxnId'])) { msgDebug("\nContinuing order check, next order = {$nextRow['TxnId']}, this order = {$row['TxnId']}"); }
        if (empty($nextRow) || $nextRow['TxnId'] <> $row['TxnId'] || $nextRow['RefNumber'] <> $row['RefNumber']) { // finish the record
            $main['General']['OrderTotal'] = $total; // calculated total
            $output[] = $main;
            if (empty($nextRow)) { break; } // we're done
            $main = []; // reset the entry for the next one
            $total= 0;
//          $runaway++; if ($runaway >= 5) { break; }
        }
        $row = $nextRow;
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    if (sizeof($GLOBALS['badInvoices']) > 0) {
        msgAdd("Encountered a non-numeric total for the following orders, the csv may be malformed: ".print_r(implode(', ', $GLOBALS['badInvoices']), true));
    }
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizJ{$jID}Tmp.csv", true, false, true);
    return sizeof($output);
}

/**
 * Processes the inventory file and inventory assembly file
 * @param string $jID - Journal ID to post to, must be supported in the Bizuno API
 * @return integer - number of rows remaining
 */
function processJournal($jID=0) {
    global $io;
    $fPath = "temp/qbfiles/bizJ{$jID}Tmp.csv"; // adjustments
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    $cnt  = 0;
    $data = [];
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'jrl_chunk', 25)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/bizuno/import.php", 'bizunoImport');
        $layout= [];
        $api   = new bizunoImport();
        foreach ($data as $entry) {
            $_GET['rID']=0; // This gets set after first run through, need to reset it
            $api->apiJournalEntry($layout, $entry, $jID);
        }
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 9 - General Ledger Entries ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp journal file to iterate via import
 * @global \bizuno\class $io
 * @return integer - Count of number of records to process
 */
function getGLSource() {
    global $io;
//  $runaway= 0;
    $total  = 0;
    $output = $main = [];
    msgDebug("\nWorking with and read filename to read: ".mapFilename('j2'));
    $rows   = readCSV(mapFilename('j2'));
    if (empty($rows)) { msgAdd("No rows to process!"); return []; }
    $row    = array_shift($rows);
    while (true) {
        if (empty($row)) { break; } // should never happen, but just in case
        $total  += qbGL($main, $row);
        $nextRow = array_shift($rows);
        if (!empty($nextRow['TxnId'])) { msgDebug("\nContinuing order check, next order = {$nextRow['TxnId']}, this order = {$row['TxnId']}"); }
        if (empty($nextRow) || $nextRow['TxnId'] <> $row['TxnId']) { // finish the record
            $main['total_amount'] = $total; // calculated total
            $output[] = $main;
            if (empty($nextRow)) { break; } // we're done
            $main = []; // reset the entry for the next one
            $total= 0;
//          $runaway++; if ($runaway >= 25) { break; }
        }
        $row = $nextRow;
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizJ2Tmp.txt", true, false, true);
    return sizeof($output);
}

function processGL() {
    global $io;
    $fPath= "temp/qbfiles/bizJ2Tmp.txt";
    $cnt  = $total = 0;
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'jrl_chunk', 25)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
        foreach ($data as $entry) {
            $_GET['rID']   = 0; // This gets set after first run through, need to reset it
            $ledger = new journal(0, 2, $entry['post_date']);
            $ledger->main['total_amount'] = $entry['total_amount']; // calculated total
            $ledger->items = $entry['items'];
            $ledger->Post();
        }
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 10 - GL Funds Transfers Entries ************************************************/
/**
 * Pulls the source data from the csv file and creates a temp journal file to iterate via import
 * @global \bizuno\class $io
 * @return integer - Count of number of records to process
 */
function getXfrSource() {
    global $io;
//    $runaway= 0;
    $output = $main = [];
    msgDebug("\nWorking with and read filename to read: ".mapFilename('j2'));
    $rows   = readCSV(mapFilename('j2x'));
    if (empty($rows)) { msgAdd("No rows to process!"); return []; }
    foreach ($rows as $row) {
        if (empty($row)) { continue; } // should never happen, but just in case
        $output[] = qbXfr($row);
//        $runaway++; if ($runaway >= 25) { break; }
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizJ2xTmp.txt", true, false, true);
    return sizeof($output);
}

function processXfr() {
    global $io;
    $fPath= "temp/qbfiles/bizJ2xTmp.txt";
    $cnt  = $total = 0;
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'jrl_chunk', 25)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
        foreach ($data as $entry) {
            $_GET['rID']   = 0; // This gets set after first run through, need to reset it
            $ledger = new journal(0, 2, $entry['post_date']);
            $ledger->main['total_amount'] = $entry['total_amount']; // calculated total
            $ledger->items = $entry['items'];
            $ledger->Post();
        }
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 15 & 16 - Banking Entries ************************************************/
/**
 * Pulls the source data from the .csv file and creates a temp journal file to iterate via import
 * @global \bizuno\class $io
 * @param integer $jID - journal ID
 * @return record count
 */
function getBankSource($jID) {
    global $io;
//  $runaway= 0;
    $total  = 0;
    $output = $main = [];
    msgDebug("\nWorking with and read filename to read: ".mapFilename("j$jID"));
    $rows   = readCSV(mapFilename("j$jID"));
    if (empty($rows)) { msgAdd("No rows to process!"); return []; }
    $row    = array_shift($rows);
    while (true) {
        if (empty($row)) { break; } // should never happen, but just in case
        $total  += qbBank($main, $row, $jID);
        $nextRow = array_shift($rows);
        if (!empty($nextRow['TxnId'])) { msgDebug("\nContinuing order check, next order = {$nextRow['TxnId']}, this order = {$row['TxnId']}"); }
        if ( empty($nextRow) || $nextRow['TxnID'] <> $row['TxnID']) { // finish the record
            $main['item'][] = [
                'gl_type'      => 'ttl',
                'qty'          => 1,
                'description'  => 'Total: '.(in_array($jID, [18]) ? $row['Customer'] : $row['Payee']),
                'debit_amount' => in_array($jID, [18]) ? $row['Payment Amount'] : 0,
                'credit_amount'=> in_array($jID, [18]) ? 0 : $row['Payment Amount'],
                'gl_account'   => $main['main']['gl_acct_id'],
                'post_date'    => biz_date('Y-m-d', strtotime(in_array($jID, [18]) ? $row['Date'] : $row['TxnDate']))];
            $output[] = $main;
            if (empty($nextRow)) { break; } // we're done
            $main = []; // reset the entry for the next one
            $total= 0;
//          $runaway++; if ($runaway >= 25) { break; }
        }
        $row = $nextRow;
    }
    msgDebug("\nNumber of rows to import = ".sizeof($output));
    $io->fileWrite(json_encode($output), "temp/qbfiles/bizJ{$jID}Tmp.csv", true, false, true);
    return sizeof($output);
}

/**
 * Process a block of banking data
 * @return int - # of rows remaining
 */
function processBank($jID) {
    global $io;
    $fPath= "temp/qbfiles/bizJ{$jID}Tmp.csv"; // adjustments
    if (!file_exists(BIZUNO_DATA.$fPath)) { return 0; }
    $rows = json_decode(file_get_contents(BIZUNO_DATA.$fPath), true);
    msgDebug("\nRead ".sizeof($rows)." rows from file to process.");
    $cnt  = 0;
    $data = [];
    while (true) {
        if (sizeof($rows) == 0) { break; }
        $data[] = array_shift($rows);
        $cnt++;
        if ($cnt >= getModuleCache('xfrQuickBooks', 'settings', 'general', 'jrl_chunk', 25)) { break; }
    }
    if (sizeof($data)) {
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
        dbTransactionStart();
        foreach ($data as $entry) {
            $items = $entry['item'];
            unset($entry['item']);
            $journal = new journal(0, $jID, $entry['main']['post_date']);
            $journal->main = array_replace($journal->main, $entry['main']);
            $journal->items= $items;
            if (!$journal->Post()) { dbTransactionRollback(); return; } // we do not permit failures or the entire operation fails and restore/restart is necessary
        }
        dbTransactionCommit();
    }
    msgDebug("\nAfter processing size of rows is now: ".sizeof($rows));
    if (sizeof($rows)==0) { unlink(BIZUNO_DATA.$fPath); }
    else                  { $io->fileWrite(json_encode($rows), $fPath, true, false, true); }
    return sizeof($rows);
}

/********************************************* Step 17 - Fix Trial Balance ************************************************/

function processTrialBalance() {
    bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
    $dbData   = $beg_bal = $coa_asset = [];
    $journal  = new journal();
    $filePath = "temp/qbfiles/trial_balance.txt";
    $maxPeriod= dbGetValue(BIZUNO_DB_PREFIX."journal_history", 'MAX(period) as period', "", false);
    // get the data
    $temp   = json_decode(file_get_contents(BIZUNO_DATA.$filePath), true);
    foreach ($temp as $row) { $importBal[$row['acct']] = $row['balance']; }
    msgDebug("\nImported Beg Bal = ".print_r($importBal, true));
    $startBB  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period=1", 'gl_account'); // get starting beginning balances
    msgDebug("\nStart History = ".print_r($startBB, true));
    $endBB    = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "period=$maxPeriod", 'gl_account'); // get ending balances
    msgDebug("\nEnding History = ".print_r($endBB, true));
    foreach ($startBB as $idx => $row) {
        if (empty($importBal[$row['gl_account']])) { $importBal[$row['gl_account']] = 0; }
        if       (in_array($row['gl_type'], [0,2,4,6,8,12])) { // debit, get current end and subtract begBal
            $amount = $importBal[$row['gl_account']] - $endBB[$idx]['debit_amount'] + $endBB[$idx]['credit_amount'];
            $dbData[$row['gl_account']] = ['beginning_balance'=>$amount, 'last_update'=>biz_date('Y-m-d')];
        } elseif (in_array($row['gl_type'], [10,20,22,24,40,44])) { // credit, get current end and subtract begBal
            $amount = $importBal[$row['gl_account']] - $endBB[$idx]['debit_amount'] + $endBB[$idx]['credit_amount'];
            $dbData[$row['gl_account']] = ['beginning_balance'=>$amount, 'last_update'=>biz_date('Y-m-d')];
        } else { // closes, endBB should equal 0, if not, adjust
            $balance = $endBB[$idx]['beginning_balance'] + $endBB[$idx]['debit_amount'] - $endBB[$idx]['credit_amount'];
            if ($importBal[$row['gl_account']] <> $balance) {
                msgAdd("Problem with closing account {$row['gl_account']}, balance is $balance and QB balance = {$importBal[$row['gl_account']]}");
                msgDebug("\nProblem with closing account {}, balance is $balance and QB balance = {$importBal[$row['gl_account']]}");
            }
        }
    }
    msgDebug("\nReady to update journal history with values: ".print_r($dbData, true));
    foreach ($dbData as $glAcct => $row) {
//        dbWrite(BIZUNO_DB_PREFIX.'journal_history', $row, 'update', "period=1 AND gl_account='$glAcct'");
    }
    if ($balance <> 0) { return msgAdd("Cannot update beginning balances as the debits are not equal to the credits."); }
    $journal->affectedGlAccts = array_keys($dbData);
//    if (!$journal->updateJournalHistory(1)) { return; }
    return 0;
}
