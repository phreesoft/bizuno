<?php
/*
 * Tools methods for Inventory Module
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
 * @version    7.x Last Update: 2025-11-07
 * @filesource /controllers/inventory/tools.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/functions.php', 'inventoryProcess', 'function');

class inventoryTools
{
    public $moduleID = 'inventory';
    public $pageID   = 'tools';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->inv_types = INVENTORY_COGS_TYPES;
    }

    /**
     * form builder - Merges 2 database inventory items to a single record
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function merge(&$layout=[])
    {
        $icnSave= ['icon'=>'save','label'=>lang('merge'),
            'events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/mergeSave', jqBiz('#mergeSrc').val(), jqBiz('#mergeDest').val());"]];
        $props  = ['defaults'=>['callback'=>''],'attr'=>['type'=>'inventory']];
        $html   = "<p>".$this->lang['msg_inventory_merge_src'] ."</p><p>".html5('mergeSrc', $props)."</p>".
                  "<p>".$this->lang['msg_inventory_merge_dest']."</p><p>".html5('mergeDest',$props)."</p>".html5('icnMergeSave', $icnSave).
                  "<p>".$this->lang['msg_inventory_merge_note']."</p>";
        $data   = ['type'=>'popup','title'=>$this->lang['inventory_merge'],'attr'=>['id'=>'winMerge'],
            'divs'   => ['body'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>"bizFocus('mergeSrc');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Performs the merge of 2 inventory items in the db
     * @param array $layout - current working structure
     * @return modifed $layout
     */
    public function mergeSave(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('inv_mgr', 5)) { return; }
        $srcID  = clean('rID', 'integer', 'get'); // record ID to merge
        $destID = clean('data','integer', 'get'); // record ID to keep
        if (empty($srcID) || empty($destID)) { return msgAdd("Bad SKU IDs, Source ID = $srcID and Destination ID = $destID"); }
        if ($srcID == $destID)               { return msgAdd("Source and destination SKU cannot be the same!"); }
        $srcSKU = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$srcID");
        $destSKU= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$destID");
        msgAdd(lang('Inventory merge stats').':', 'info');
        msgDebug("\nmergeSave with src SKU = $srcSKU (ID=$srcID) and destSKU = $destSKU (destID=$destID)");
        dbTransactionStart();
        // SKU based changes
        msgDebug("\nReady to write table journal_item to merge from SKU: $srcSKU => $destSKU");
        $jrnlCnt= dbWrite(BIZUNO_DB_PREFIX.'journal_item',     ['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("journal_item table SKU changes: $jrnlCnt;", 'info');
        $histCnt= dbWrite(BIZUNO_DB_PREFIX.'inventory_history',['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("inventory_history table SKU changes: $histCnt;", 'info');
        $owedCnt= dbWrite(BIZUNO_DB_PREFIX.'journal_cogs_owed',['sku'=>$destSKU], 'update', "sku='".addslashes($srcSKU)."'");
        msgAdd("journal_cogs_owed table SKU changes: $owedCnt;", 'info');
        $assyCnt= renameMetaBOM($srcSKU, $destSKU);
        msgAdd("Assembly BOM SKU changes: $assyCnt;",'info');
        $rtnCnt = $this->renameMetaReturn($srcSKU, $destSKU);
        msgAdd("Customer/Vendor return changes: $rtnCnt;",'info');
        $this->mergeIfExists($srcID, $destID); // special processing for some meta keys
        // SKU ID based changes
        msgDebug("\nDeleting meta from source SKU: $srcSKU");
        $jMeta  = dbMetaGet(0, '%', 'inventory', $srcID);
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id=$srcID");
        msgAdd("Table inventory_meta number of source meta entries removed = ".sizeof($jMeta), 'info');
        // Move the main image if not existing at dest
        $srcImg = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id=$srcID");
        $destImg= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id=$destID");
        if (empty($destImg && !empty($srcImg))) { // move image to dest
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['image_with_path'=>$srcImg], 'update', "id=$destID");
        }
        // Merge the attachments
        msgDebug("\nMoving file at path: ".getModuleCache($this->moduleID, 'properties', 'attachPath', 'inventory')." from rID_{$srcID}_ to rID_{$destID}_");
        $io->fileMove(getModuleCache($this->moduleID, 'properties', 'attachPath', 'inventory'), "rID_{$srcID}_", "rID_{$destID}_");
        // fix the qty's
        $stks = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['qty_stock','qty_po','qty_so','qty_alloc'], "id=$srcID");
        if (!empty($stks)) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_stock=qty_stock+{$stks['qty_stock']}, qty_po=qty_po+{$stks['qty_po']}, qty_so=qty_so+{$stks['qty_so']}, qty_alloc=qty_alloc+{$stks['qty_alloc']} WHERE id=$destID");
        }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory WHERE id=$srcID");
        dbTransactionCommit();
        msgAdd("Finished Merging SKU: $srcSKU (ID=$srcID) INTO SKU: $destSKU (ID=$destID)", 'info');
        msgLog(lang('inventory').'-'.lang('merge').": $srcSKU => $destSKU");
        $layout  = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winMerge'); bizGridReload('dgInventory');"]]);
    }

    /**
     * Keeps specific meta if it exists in the source and not in the destination
     */
    private function mergeIfExists($srcID, $destID)
    {
        $metaS = dbMetaGet(0, 'production_job', 'inventory', $srcID);
        $metaD = dbMetaGet(0, 'production_job', 'inventory', $destID);
        if (empty($metaD) && !empty($metaS)) { // saves the production job template from the old SKU to the new SKU
            $jobID = metaIdxClean($metaS);
            $metaS['sku_id'] = $destID;
            dbWrite(BIZUNO_DB_PREFIX.'inventory_meta', ['ref_id'=>$destID, 'meta_value'=>json_encode($metaS)], 'update', "id=$jobID");
        }
    }
    
    /**
     * Handles the merge of journal_meta 'return' key
     */
    private function renameMetaReturn($srcSKU='', $destSKU='')
    {
        $rows= dbMetaGet(0, 'return', 'journal', '%');
        msgDebug("\nWorking on journal_meta:return key , read ".sizeof($rows)." rows to process.");
        $cnt = 0;
        foreach ($rows as $row) {
            $changed= false;
            if (!empty($row['receive_details']) && is_array($row['receive_details']) && empty($row['receive_details']['rows'])) {  // bring the structure current
                $changed = true;
                $row['receive_details'] = ['total'=>sizeof($row['receive_details']), 'rows'=>$row['receive_details']];
            }
            if (!empty($row['receive_details']['rows'])) { foreach ($row['receive_details']['rows'] as $idx => $item ) {
                if ($item['sku']==$srcSKU) { $row['receive_details']['rows'][$idx]['sku'] = $destSKU; $changed = true; }
            } }
            if (!empty($row['close_details']) && is_array($row['close_details']) && empty($row['close_details']['rows'])) {  // bring the structure current
                $changed = true;
                $row['close_details'] = ['total'=>sizeof($row['close_details']), 'rows'=>$row['close_details']];
            }
            if (!empty($row['close_details']['rows'])) { foreach ($row['close_details']['rows'] as $idx => $item ) {
                if ($item['sku']==$srcSKU) { $row['close_details']['rows'][$idx]['sku'] = $destSKU; $changed = true; }
            } }
            if ($changed) {
                $refID = $row['_refID'];
                $metaID= metaIdxClean($row);
                dbMetaSet($metaID, 'return', $row, 'journal', $refID);
                $cnt++;
            }
        }
        return $cnt;
    }

    /**
     * 
     * @param type $skuID
     * @return type
     */
    private function chartForecastData($skuID)
    {
        $numWeeks= 26;
        $delta = $ints = $data = [];
        if (empty($skuID)) { return msgAdd(lang('bad_id')); }
        $inv   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['sku', 'qty_stock', 'qty_alloc'], "id=$skuID");
        msgDebug("\nRead values for this SKU ID: ".print_r($inv, true));
        $weekOf= strtotime("this week");
        for ($i=0; $i<$numWeeks; $i++) {
            $delta[]= 0; // initialize
            $ints[] = $weekOf;
            $weekOf = $weekOf + (60 * 60 * 24 * 7); // add a week
        }
        $rInts = array_reverse($ints, true);
        $sql   = "SELECT m.journal_id, m.invoice_num, i.id, i.qty, i.date_1 FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
             WHERE m.journal_id IN (4, 10) AND m.closed='0' AND i.sku='".addslashes($inv['sku'])."' ORDER BY i.date_1";
        $stmt  = dbGetResult($sql);
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($result as $row) {
            $qtyFilled = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty)", "item_ref_id={$row['id']} AND gl_type='itm'", false); // so/po - filled
            $balance = $row['qty'] - $qtyFilled;
            msgDebug("\nQty = {$row['qty']} and filled = $qtyFilled, balance = $balance");
            if ($row['qty']==$qtyFilled) { msgDebug("\nFilled - Continuing"); continue; } // line item has been filled.
            msgDebug("\nProcessing row from db: ".print_r($row, true));
            foreach ($rInts as $key => $value) {
                $delDate = strtotime($row['date_1']);
                if ($delDate >= $value) {
                    $delta[$key] += $row['journal_id']==4 ? $balance : -$balance;
                    break;
                } elseif ($key==0 && $delDate < $value) { // for late deliveries before first date, put into first week
                    $delta[0] += $row['journal_id']==4 ? $balance : -$balance;
                }
            }
        }
        msgDebug("\nDeltas calculation = ".print_r($delta, true));
        $data[]= [lang('date'), lang('total')];
        $bal   = $inv['qty_stock'] - $inv['qty_alloc'];
        foreach ($delta as $key => $value) {
            $bal += $value;
            $data[] = [date('M d', $ints[$key]), $bal];
        }
        msgDebug("\nReturning with data = ".print_r($data, true));
        return $data;
    }

    public function chartForecastGo()
    {
        global $io;
        $skuID   = clean('rID', 'integer', 'get');
        $struc = $this->chartForecastData($skuID);
        $sku   = clean(dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id=$skuID"), 'alpha_num');
        $output= [];
        foreach ($struc as $row) { $output[] = implode(",", $row); }
        $io->download('data', implode("\n", $output), "Forecast: $sku.csv");
    }

    /**
     * Generates a pop up bar chart for monthly sales of inventory items
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function chartSales(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        if (!$rID) { return msgAdd(lang('err_bad_id')); }
        $struc = $this->chartSalesData($sku);
        $output= ['divID'=>"chartInventoryChart",'type'=>'column','attr'=>['legend'=>'none','title'=>lang('sales')],'data'=>array_values($struc)];
        $action= BIZUNO_AJAX."&bizRt=inventory/tools/chartSalesGo&sku=$sku";
        $js    = "ajaxDownload('frmInventoryChart');\n";
        $js   .= "var dataInventoryChart = ".json_encode($output).";\n";
        $js   .= "function funcInventoryChart() { drawBizunoChart(dataInventoryChart); };";
        $js   .= "google.charts.load('current', {'packages':['corechart']});\n";
        $js   .= "google.charts.setOnLoadCallback(funcInventoryChart);\n";
        $layout = array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'  => [
                'body'  =>['order'=>50,'type'=>'html',  'html'=>'<div style="width:100%" id="chartInventoryChart"></div>'],
                'divExp'=>['order'=>70,'type'=>'html',  'html'=>'<form id="frmInventoryChart" action="'.$action.'"></form>'],
                'btnExp'=>['order'=>90,'type'=>'fields','keys'=>['icnExp']]],
            'fields'=> ['icnExp'=>['attr'=>['type'=>'button','value'=>lang('download_data')],'events'=>['onClick'=>"jqBiz('#frmInventoryChart').submit();"]]],
            'jsHead'=> ['init'=>$js]]);
        }

    private function chartSalesData($sku)
    {
        $dates = localeGetDates(localeCalculateDate(biz_date('Y-m-d'), 0, 0, -1));
        $jIDs  = '(12,13)';
        msgDebug("\nDates = ".print_r($dates, true));
          $sql = "SELECT MONTH(m.post_date) AS month, YEAR(m.post_date) AS year, SUM(i.credit_amount+i.debit_amount) AS total
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE i.sku='$sku' and m.journal_id IN $jIDs AND m.post_date>='{$dates['ThisYear']}-{$dates['ThisMonth']}-01'
              GROUP BY year, month LIMIT 12";
        msgDebug("\nSQL = $sql");
        if (!$stmt = dbGetResult($sql)) { return msgAdd(lang('err_bad_sql')); }
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nresult = ".print_r($result, true));
        $precision = getModuleCache('phreebooks', 'currency', 'iso')[getDefaultCurrency()]['dec_len'];
        $struc[] = [lang('date'), lang('total')];
        for ($i = 0; $i < 12; $i++) { // since we have 12 months to work with we need 12 array entries
            $struc[$dates['ThisYear'].$dates['ThisMonth']] = [$dates['ThisYear'].'-'.$dates['ThisMonth'], 0];
            $dates['ThisMonth']++;
              if ($dates['ThisMonth'] == 13) {
                  $dates['ThisYear']++;
                  $dates['ThisMonth'] = 1;
              }
        }
        foreach ($result as $row) {
            if (isset($struc[$row['year'].$row['month']])) { $struc[$row['year'].$row['month']][1] = round($row['total'], $precision); }
          }
        return $struc;
    }

    public function chartSalesGo()
    {
        global $io;
        $sku   = clean('sku', 'text', 'get');
        $struc = $this->chartSalesData($sku);
        $output= [];
        foreach ($struc as $row) { $output[] = implode(",", $row); }
        $io->download('data', implode("\n", $output), "SKU-Sales-$sku.csv");
    }

    /**
     * Works with dashboard inv-stock to re-generate data and download
     * @global type $io
     */
    public function invDataGo()
    {
        global $io;
        $fqdn = "\\bizuno\\inv_stock";
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/dashboards/inv_stock/inv_stock.php', $fqdn);
        $dash = new $fqdn();
        $data = $dash->getData();
        foreach ($data as $row) { $output[] = '"'.implode('","', $row).'"'; }
        $io->download('data', implode("\n", $output), "InvValData-".biz_date('Y-m-d').".csv");
    }

    /**
     * Downloads the stock aging data
     * @global class $io - I/O class
     * @return exits if successful, msg otherwise
     */
    public function stockAging()
    {
        global $io;
        $ttlQty = $ttlCost = 0;
        $output = [];
        $this->ageFld = dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'shelf_life') ? true : false;
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "remaining>0", 'post_date', ['sku', 'post_date', 'remaining', 'unit_cost']);
        $raw[] = [lang('post_date'), lang('sku'), lang('inventory_description_short'), lang('remaining'), lang('value')];
        foreach ($rows as $row) {
            $ageDate = $this->getAgingValue($row['sku']);
            msgDebug("\nsku {$row['sku']} comparing ageDate: $ageDate with post date: {$row['post_date']}");
            if ($row['post_date'] >= $ageDate) { continue; }
            $ttlQty += $row['remaining'];
            $value   = $row['unit_cost'] * $row['remaining'];
            $ttlCost+= $value;
            $raw[]   = [viewFormat($row['post_date'], 'date'), $row['sku'], viewProcess($row['sku'], 'sku_name'), intval($row['remaining']), viewFormat($value, 'currency')];
        }
        $raw[]  = [jslang('total'), '', '', intval($ttlQty), viewFormat($ttlCost,'currency')];
        if (sizeof($raw) < 2) { return msgAdd('There are no items aged over their expected aging date!'); }
        foreach ($raw as $row) { $output[] = '"'.implode('","', $row).'"'; }
        $io->download('data', implode("\n", $output), "Stock-Aging-".biz_date('Y-m-d').".csv");
    }

    /**
     * Retrieves the aging date based on the SKU provided
     * @param string $sku - sku to search
     * @return string - aged date to compare for filter
     */
    private function getAgingValue($sku)
    {
        if (!empty($this->skuDates[$sku])) { return $this->skuDates[$sku]; }
        $numWeeks = $this->ageFld ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'shelf_life', "sku='$sku'") : $this->struc['defAge']['attr']['value'];
        $this->skuDates[$sku] = localeCalculateDate(biz_date('Y-m-d'), -($numWeeks * 7));
        msgDebug("\n num weeks = $numWeeks and calculated date = {$this->skuDates[$sku]}");
        return $this->skuDates[$sku];
    }

    /**
     * This function balances the inventory stock levels with the inventory_history table
     */
    public function historyTestRepair()
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $action   = clean('data', ['format'=>'alpha_num', 'default'=>'test'], 'get');
        $precision= 1 / pow(10, getModuleCache('bizuno', 'settings', 'locale', 'number_precision', 2));
        $roundPrec= getModuleCache('bizuno', 'settings', 'locale', 'number_precision', 2);
        $result0  = dbGetMulti(BIZUNO_DB_PREFIX."journal_cogs_owed");
        $owed     = [];
        foreach ($result0 as $row) {
            if (!isset($owed[$row['sku']])) { $owed[$row['sku']] = 0; }
            $owed[$row['sku']] += $row['qty'];
        }
        // fetch the inventory items that we track COGS and get qty on hand
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $this->inv_types)."')", 'sku', ['sku','qty_stock']);
        $cnt    = 0;
        $repair = [];
        foreach ($result as $row) { // for each item, find the history remaining Qty's
            // check for quantity on hand not rounded properly
            $on_hand = round($row['qty_stock'], $roundPrec);
            if ($on_hand <> $row['qty_stock']) {
                $repair[$row['sku']] = $on_hand;
                if ($action <> 'fix') {
                    $dispVal = round($on_hand, $roundPrec);
                    msgAdd(sprintf($this->lang['inv_tools_stock_rounding_error'], $row['sku'], $row['qty_stock'], $dispVal));
                    $cnt++;
                }
            }
            // now check with inventory history
            $remaining= dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', "SUM(remaining) AS remaining", "sku='".addslashes($row['sku'])."'", false);
            $cog_owed = isset($owed[$row['sku']]) ? $owed[$row['sku']] : 0;
            $cog_diff = round($remaining - $cog_owed, $roundPrec);
            if ($on_hand <> $cog_diff && abs($on_hand-$cog_diff) > 0.01) {
                $repair[$row['sku']] = $cog_diff;
                if ($action <> 'fix') {
                    msgAdd(sprintf($this->lang['inv_tools_out_of_balance'], $row['sku'], $on_hand, $cog_diff));
                    $cnt++;
                }
            }
            msgDebug("\nsku = {$row['sku']}, qty_stock = {$row['qty_stock']}, on_hand = $on_hand, cog_diff = $cog_diff, remaining = $remaining, owed = $cog_owed");
        }
        if ($action == 'fix') {
            // zero out balances that are less than the precision
            dbWrite(BIZUNO_DB_PREFIX.'inventory_history', ['remaining'=>0], 'update', "remaining<$precision");
            if (sizeof($repair) > 0) { foreach ($repair as $key => $value) {
                // commented out, the value has already been rounded.
//                $value = round($value, $roundPrec);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_stock'=>$value], 'update', "sku='".addslashes($key)."'");
                msgAdd(sprintf($this->lang['inv_tools_balance_corrected'], $key, $value), 'info');
            } }
        }
        if ($cnt == 0) { msgAdd($this->lang['inv_tools_in_balance'], 'info'); }
        msgLog($this->lang['inv_tools_val_inv']);
    }

    /**
     * Re-aligns table inventory.qty_alloc with open activities.
     * Here, the function is mostly an entry point that resets all qty_on alloc values to zero, they will
     * be reset to the proper value through mods in the extensions.
     */
    public function qtyAllocRepair()
    {
        dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_alloc'=>0], 'update', "qty_alloc<>0");
        msgAdd(lang('msg_database_write'), 'success');
        $stmt = dbGetResult("SELECT journal_main.description, journal_item.sku, journal_item.qty FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_item 
            ON journal_main.id=journal_item.ref_id WHERE journal_id=32 AND closed='0'");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nFetched open work order rows = ".print_r($rows, true));
        $updates = [];
        foreach ($rows as $row) {
            $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($row['sku'])."'");
            msgDebug("\nTitle: {$row['description']} with skuID = $skuID with qty = {$row['qty']}");
            $assy = getMetaInventory($skuID, 'bill_of_materials');
            foreach ($assy as $piece) {
                $pcID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($piece['sku'])."'");
                if (!isset($updates[$pcID])) { $updates[$pcID] = ['id'=>$pcID, 'sku'=>$piece['sku'], 'qty'=>0]; }
                $updates[$pcID]['qty'] += $row['qty'] * $piece['qty'];
            }
        }
        foreach ($updates as $data) {
            msgDebug("\nWriting sku = {$data['sku']} with qty = {$data['qty']}");
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_alloc'=>$data['qty']], 'update', "id='{$data['id']}'");
        }
    }

    /**
     * This function balances the open sales orders and purchase orders with the displayed levels from the inventory table
     */
    public function onOrderRepair()
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $skuList = [];
        $this->inv_types[] = 'ns'; // add some more that should be checked
        $jItems = $this->getJournalQty(); // fetch the PO's and SO's balances
        $items  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','",$this->inv_types)."')", 'sku', ['id','sku','qty_so','qty_po']);
        foreach ($items as $row) {
            $adjPO = false;
            if (isset($jItems[4][$row['sku']]) && $jItems[4][$row['sku']] != $row['qty_po']) {
                $adjPO = max(0, round($jItems[4][$row['sku']], 4));
            } elseif (!isset($jItems[4][$row['sku']]) && $row['qty_po'] != 0) {
                $adjPO = 0;
            }
            if ($adjPO !== false) {
                $skuList[] = sprintf('Quantity of SKU: %s on %s was adjusted to %f', $row['sku'], lang('journal_id_4'), $adjPO);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_po'=>$adjPO], 'update', "id={$row['id']}");
            }
            $adjSO = false;
            if (isset($jItems[10][$row['sku']]) && $jItems[10][$row['sku']] != $row['qty_so']) {
                $adjSO = max(0, round($jItems[10][$row['sku']], 4));
            } elseif (!isset($jItems[10][$row['sku']]) && $row['qty_so'] != 0) {
                $adjSO = 0;
            }
            if ($adjSO !== false) {
                $skuList[] = sprintf('Quantity of SKU: %s on %s was adjusted to %f', $row['sku'], lang('journal_id_10'), $adjSO);
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['qty_so'=>$adjSO], 'update', "id={$row['id']}");
            }
        }
        msgLog($this->lang['inv_tools_repair_so_po']);
        if (sizeof($skuList) > 0) { return msgAdd(implode("<br />", $skuList), 'caution'); }
        msgAdd($this->lang['inv_tools_so_po_result'], 'success');
    }

    /**
     * Checks order status for order balances, items received/shipped
     * @return array - indexed by journal_id total qty on SO, PO
     */
    private function getJournalQty()
    {
        $item_list = [];
        $orders = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "closed='0' AND journal_id IN (4,10)", '', ['id', 'journal_id']);
        foreach ($orders as $row) {
            $ordr_items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$row['id']} AND gl_type='itm'", '', ['id', 'sku', 'qty']);
            foreach ($ordr_items as $item) {
                if (!isset($item_list[$row['journal_id']][$item['sku']])) { $item_list[$row['journal_id']][$item['sku']] = 0; }
                $filled = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty) AS qty", "item_ref_id={$item['id']}", false);
                if (empty($filled)) { $filled = 0; }
//              msgDebug("\nWorking with sku: {$item['sku']} with quantity: {$item['qty']} and filled: $filled");
                // in the case when more are received than ordered, don't let qty_po, qty_so go negative (doesn't make sense)
                $item_list[$row['journal_id']][$item['sku']] += max(0, $item['qty'] - $filled);
            }
        }
        msgDebug("\nReturning from getJournalQty with list =  .print_r($item_list, true)");
        return $item_list;
    }

    private function getShipTos($sku)
    {
        $shipW = $shipC = $shipE = $shipO = $shipAll = 0;
        $sql   = "SELECT m.journal_id, m.store_id, i.qty FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.post_date>'$this->dateStart' AND m.journal_id IN (12,13) AND i.sku='".addslashes($sku)."' ORDER BY m.post_date";
        $stmt  = dbGetResult($sql);
        $rows  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            if ($row['journal_id']==13) { $shipAll -= $row['qty']; } else { $shipAll += $row['qty']; }
            switch ($row['store_id']) {
                case '1': if ($row['journal_id']==13) { $shipW -= $row['qty']; } else { $shipW += $row['qty']; } break;
                case '2': if ($row['journal_id']==13) { $shipC -= $row['qty']; } else { $shipC += $row['qty']; } break;
                case '3': if ($row['journal_id']==13) { $shipE -= $row['qty']; } else { $shipE += $row['qty']; } break;
                default:  if ($row['journal_id']==13) { $shipO -= $row['qty']; } else { $shipO += $row['qty']; } break;
            }
        }
        return ['shipAll'=>$shipAll, 'shipW'=>$shipW, 'shipC'=>$shipC, 'shipE'=>$shipE, 'shipO'=>$shipO];
    }

    private function getStockLevels($sku)
    {
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND post_date>'$this->dateStart'", 'post_date', ['qty', 'store_id']);
        foreach ($rows as $row) {
            $stockAll += $row['qty'];
            switch ($row['store_id']) {
                case '1': $stockW += $row['qty']; break;
                case '2': $stockC += $row['qty']; break;
                case '3': $stockE += $row['qty']; break;
                default:  $stockO += $row['qty']; break;
            }
        }
        return ['stockAll'=>$stockAll, 'stockW'=>$stockW, 'stockC'=>$stockC, 'stockE'=>$stockE, 'stockO'=>$stockO];
    }

    /**
     * Re-prices all assemblies based on current item costs, best done after new item costing has been done completed, through ajax steps
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function priceAssy(&$layout=[])
    {
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('ma','sa') AND inactive='0'", 'sku', ['id', 'sku']);
        if (sizeof($result) == 0) { return msgAdd("No assemblies found to process!"); }
        foreach ($result as $row) { $rows[] = ['id'=>$row['id']]; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCron('priceAssy', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows, 'noCost'=>[], 'noQty'=>[]]);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('priceAssy', 'inventory/tools/priceAssyNext');"]]);
    }

    /**
     * Controller for re-costing assemblies, manages a block of 100 SKUs per iteration
     * @param type $layout
     */
    public function priceAssyNext(&$layout=[])
    {
        $blockCnt = 100;
        $cron = getUserCron('priceAssy');
        while ($blockCnt > 0) {
            $row  = array_shift($cron['rows']);
            if (empty($row)) { break; }
            $cost = $this->dbGetInvAssyCost($row['id'], $cron);
            if ($cost > 0) { dbWrite(BIZUNO_DB_PREFIX.'inventory', ['item_cost'=>$cost], 'update', "id={$row['id']}"); }
            $cron['cnt']++;
            $blockCnt--;
        }
        if (sizeof($cron['rows']) == 0) {
            msgLog("inventory Tools (re-cost Assemblies) - ({$cron['total']} records)");
            $output = "<p>Errors found during assembly costing:</p>";
            if (sizeof($cron['noCost'])>0) { $output .= "SKUs with no cost sub-item:<br />"    .implode("<br />", $cron['noCost']); }
            if (sizeof($cron['noQty']) >0) { $output .= "SKUs with no quantity sub-item:<br />".implode("<br />", $cron['noQty']); }
            // Uncomment the following line to create a file of subassembly parts with no cost AND no quantity
//          $io->fileWrite($output, 'temp/assy_errors.txt');
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs<br />$output",'baseID'=>'priceAssy','urlID'=>'inventory/tools/priceAssyNext']];
            clearUserCron('priceAssy');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('priceAssy', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed {$cron['cnt']} of {$cron['total']} SKUs",'baseID'=>'priceAssy','urlID'=>'inventory/tools/priceAssyNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * This tool balances and recommended inventory locations base on sales geographies
     * @param array $layout - Structure coming in
     * @return - Modified $layout
     */
    public function invBalance(&$layout=[])
    {
        global $io;
        $fn    = 'temp/invAnalysis.csv';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $this->inv_types)."')", 'sku', ['id']);
        if (sizeof($result) == 0) { return msgAdd('No rows to process!'); }
        foreach ($result as $row) { $skus[] = $row['id']; }
//$skus = array_slice($skus, 500, 10);
        msgDebug("\nNumber of rows to process = ".sizeof($skus));
        $head  = "skuID,sku,description,type,";
//      $head .= "avgM,avgY,";
        $head .= "stockAll,stockW,stockC,stockE,stockO,";
        $head .= "shipAll,shipW,shipC,shipE,shipO\n";
        $io->fileWrite($head, $fn, true, false, true);
        setUserCron('invBalance', ['filename'=>$fn, 'cnt'=>0, 'total'=>sizeof($skus), 'rows'=>$skus]);
        $layout= array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"cronInit('invBalance', '$this->moduleID/$this->pageID/invBalanceNext');"]]);
    }

    /**
     * Next block of inventory balance tool
     * @param array $layout - Structure coming in
     * @return - Modified layout
     */
    public function invBalanceNext(&$layout=[])
    {
        global $io;
        $output  = [];
        $blockCnt= 50;
        $cron    = getUserCron('invBalance');
        while ($blockCnt > 0) {
            $skuID= array_shift($cron['rows']);
            if (empty($skuID)) { break; }
            $inv  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id','sku','description_short','inventory_type'], "id=$skuID");
            $stks = $this->getStockLevels($inv['sku']);
            $ships= $this->getShipTos($inv['sku']);
            $temp = [
                $inv['id'], csvEncapsulate($inv['sku']), csvEncapsulate($inv['description_short']), lang('inventory_type_'.$inv['inventory_type']),
//              $avgs['avgM'],     $avgs['avgY'],
                $stks['stockAll'], $stks['stockW'], $stks['stockC'], $stks['stockE'], $stks['stockO'],
                $ships['shipAll'], $ships['shipW'], $ships['shipC'], $ships['shipE'], $ships['shipO'],
            ];
            $output[] = implode(",", $temp);
            $cron['cnt']++;
            $blockCnt--;
        }
        $io->fileWrite(implode("\n",$output)."\n", $cron['filename'], true, true);
        if (sizeof($cron['rows']) == 0) {
            msgLog("GL Pro Tools (Balance Inventory) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs",'baseID'=>'invBalance','urlID'=>"$this->moduleID/$this->pageID/invBalanceNext"]];
            clearUserCron('invBalance');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('invBalance', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed next block",'baseID'=>'invBalance','urlID'=>"$this->moduleID/$this->pageID/invBalanceNext"]];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    public function invForecast(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1, false)) { return; }
        $skuID = clean('rID', 'integer', 'get');
        $data  = $this->chartForecastData($skuID);
        $output= ['divID'=>'chartForecastChart','type'=>'column','attr'=>['legend'=>'none','title'=>$this->lang['inv_forecast']],'data'=>array_values($data)];
        $action= BIZUNO_AJAX."&bizRt=$this->moduleID/tools/chartForecastGo&rID=$skuID";
        $js    = "ajaxDownload('frmForecastChart');\n";
        $js   .= "var dataForecastChart = ".json_encode($output).";\n";
        $js   .= "function funcForecastChart() { drawBizunoChart(dataForecastChart); };";
        $js   .= "google.charts.load('current', {'packages':['corechart']});\n";
        $js   .= "google.charts.setOnLoadCallback(funcForecastChart);\n";
        $layout = array_merge_recursive($layout, ['type'=>'divHTML',
            'divs'  => [
                'body'  =>['order'=>50,'type'=>'html',  'html'=>'<div style="width:100%" id="chartForecastChart"></div>'],
                'divExp'=>['order'=>70,'type'=>'html',  'html'=>'<form id="frmForecastChart" action="'.$action.'"></form>'],
                'btnExp'=>['order'=>90,'type'=>'fields','keys'=>['icnExp']]],
            'fields'=> ['icnExp'=>['attr'=>['type'=>'button','value'=>lang('download_data')],'events'=>['onClick'=>"jqBiz('#frmForecastChart').submit();"]]],
            'jsHead'=> ['init'=>$js]]);
    }

    /**
     * Recalculates the inventory history table quantities based on journal entries
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function recalcHistory(&$layout=[])
    {
        global $io;
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $this->inv_types)."') AND inactive='0'", 'sku', ['id']);
        if (sizeof($result) == 0) { return msgAdd('No inventory found to process!'); }
        foreach ($result as $row) { $rows[] = $row['id']; }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCron('recalcHistory', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows]);
        $output = 'Inventory Reconciliation tool run '.date('Y-m-d')."\n";
        $output.= "SKU,Store,Journal Qty,History Qty\n";
        $io->fileWrite($output, 'temp/recalc_inv.csv', true, false, true);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"cronInit('recalcHistory', 'inventory/tools/recalcHistoryNext');"]]);
    }

    /**
     * Block process recalculation of inventory history table
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function recalcHistoryNext(&$layout=[])
    {
        global $io;
        $output  = [];
        $blockCnt= 100;
        $stores  = getModuleCache('bizuno', 'stores');
        $cron    = getUserCron('recalcHistory');
        while ($blockCnt > 0) {
            $hist   = [];
            $id     = array_shift($cron['rows']);
            if (empty($id)) { break; }
            $sku    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$id");
            $result = $this->skuDrillDownActivity($sku);
            $endBal = array_pop($result['cur_bal']);
            msgDebug("\ntotals from drilldown = ".print_r($result['totals'], true));
            // fetch the inventory history balances
            $dbHist = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND remaining>0", 'post_date', ['remaining', 'store_id']);
            foreach ($dbHist as $row) {
                if (!isset($hist['b'.$row['store_id']])) { $hist['b'.$row['store_id']] = 0; }
                $hist['b'.$row['store_id']] += $row['remaining'];
            }
            msgDebug("\nbalances from db table = ".print_r($hist, true));
            foreach ($stores as $store) {
                $strHist= floatval(isset($hist[$store['id']]) ? $hist[$store['id']] : 0);
                $strBal = isset($endBal[$store['id']]) ? $endBal[$store['id']] : 0;
                if ($strHist<>$strBal) {
                    msgAdd("Inventory mismatch for sku = $sku, store {$store['text']}: Journal balance = $strBal and History balance = $strHist", 'trap');
                    $output[] = "$sku,{$store['text']},$strBal,$strHist";
                }
            }
            $cron['cnt']++;
            $blockCnt--;
        }
        if (!empty($output)) {
            msgDebug("\nWriting output to file: ".print_r($output, true));
            $io->fileWrite(implode("\n", $output)."\n", 'temp/recalc_inv.csv', true, true);
        }
        if (sizeof($cron['rows']) == 0) {
            msgLog("Inventory Tools (test/repair history) - ({$cron['total']} records)");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} SKUs",'baseID'=>'recalcHistory','urlID'=>'inventory/tools/recalcHistoryNext']];
            clearUserCron('recalcHistory');
//          $io->download('file', 'temp/', 'recalc_inv.csv', false); // causes error
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            setUserCron('recalcHistory', $cron);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed {$cron['cnt']} of {$cron['total']} SKUs",'baseID'=>'recalcHistory','urlID'=>'inventory/tools/recalcHistoryNext']];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $rID
     * @return int
     */
    private function dbGetInvAssyCost($rID=0, &$cron=[])
    {
        $cost = 0;
        $skip = false;
        if (empty($rID)) { return $cost; }
        $iID  = intval($rID);
        $items= getMetaInventory($iID, 'bill_of_materials');
        if (empty($items)) { $items[] = ['qty'=>1, 'sku'=>dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID")]; } // for non-assemblies
        foreach ($items as $row) {
            if (empty($row['sku'])) { continue; }
            if (empty($GLOBALS['inventory'][$row['sku']]['unit_cost'])) {
                $skuDtl = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['inventory_type','item_cost'], "sku='".addslashes($row['sku'])."'");
                if (!in_array($skuDtl['inventory_type'], $this->inv_types)) { continue; } // not tracked so ignore cost
                if (empty($skuDtl['item_cost'])) {
                    $cron['noCost'][] = $row['sku'];
                    $skip = true;
                }
                $GLOBALS['inventory'][$row['sku']]['unit_cost'] = $skuDtl['item_cost'];
            }
            if (empty($row['qty'])) {
                $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID");
                $cron['noQty'][] = "Assy sku: $sku with BOM SKU: {$row['sku']}";
                $skip = true;
            }
            $cost+= $row['qty'] * $GLOBALS['inventory'][$row['sku']]['unit_cost'];
        }
        return !$skip ? $cost : 0;
    }

    public function skuDrillDown(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $skuID = clean('rID', 'integer','get');
        $date  = clean('data', 'date',  'get'); // start date, do we want an end date?
        $stores= getModuleCache('bizuno', 'stores');
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$skuID");
        msgDebug("\Entering skuDrillDown with skuID = $skuID and sku = $sku and date = $date");
        $result= $this->skuDrillDownActivity($sku, 1, $date);
        // generate the HTML
        $html  = '<table>';
        $html .= '<tr><th style="border:1px solid black;">Ref #</th><th style="border:1px solid black;">Journal</th>';
        $html .= '<th style="border:1px solid black;">Post Date</th><th style="border:1px solid black;">Store</th><th style="border:1px solid black;">Qty</th>';
        foreach ($stores as $store) { $html .= '<th style="border:1px solid black;">'.$store['text'].'</th>'; }
        $html.= '</tr>';
        // generate the begBal row
        $html .= '<tr><td colspan="5" style="border:1px solid black;text-align: right;">Beginning Balance&nbsp;</td>';
        foreach ($result['beg_bal'] as $bb) { $html .= '<td style="border:1px solid black;text-align: center;">'.$bb.'</td>'; }
        $html .= '</tr>';
        $curBal = $result['beg_bal'];
        foreach ($result['rows'] as $row) {
            $curBal['b'.$row['store']] += $row['qty'];
            $html .= '<tr>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID={$row['jID']}&rID={$row['id']}');"],'attr'=>['type'=>'button','value'=>"#{$row['ref']}"]]).'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['jID'], 'j_desc')   .'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['date'], 'date')     .'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.viewFormat($row['store'], 'contactID').'</td>';
            $html .= '<td style="border:1px solid black;text-align: center;">'.$row['qty'].'</td>';
            foreach ($stores as $store) { $html .= '<td style="border:1px solid black;text-align: center;">'.$curBal['b'.$store['id']].'</td>'; }
            $html .= '</tr>';
        }
        $html .= '</table>';
        $data  = ['type'=>'popup','attr'=>['id'=>'invTotals'],'title'=>"Store balances for SKU: $sku",
            'divs' => ['body'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function skuDrillDownActivity($sku='', $qtyReqd=1, $strtDate='')
    {
        if (empty($strtDate)) { $strtDate = '2000-01-01'; }
        $posts = [];
        $jIDs  = '6,7,12,13,14,15,16,19,21';
        $stores= getModuleCache('bizuno', 'stores');
        foreach ($stores as $store) {
            $storeIDs[] = $store['id'];
            $dateBal['b'.$store['id']] = 0;
            $jrnlBal['b'.$store['id']] = 0;
        }
        $curStk= $this->getCurrentStock($sku); // balances from the history table by store
        $stmt  = dbGetResult("SELECT m.id, m.journal_id, m.post_date, m.store_id, m.invoice_num, m.so_po_ref_id, i.qty, i.gl_type FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.journal_id IN ($jIDs) AND i.sku='".addslashes($sku)."' ORDER BY m.post_date");
        $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
//      msgDebug("\nResult from search with sku: $sku and qtyReqd = $qtyReqd and date = $strtDate is: ".print_r($result, true));
        while ($row=array_shift($result)) {
            if (in_array($row['journal_id'], [7,12,19])) { $row['qty'] = -$row['qty']; }
            if (!in_array($row['store_id'], $storeIDs))  { $row['store_id'] = 0; }
            $jrnlBal['b'.$row['store_id']] += $row['qty'];
            if ($row['post_date']<$strtDate) { // freeze the balance at this date as the beginning balance
                $dateBal = $jrnlBal;
                continue;
            }
            if ($row['journal_id']==15) {
                if (!in_array($row['so_po_ref_id'], $storeIDs)) { $row['so_po_ref_id'] = 0; }
// @TODO remove after 2023-01-31 - rewrite next line so if so_po_ref_id is not a store then make it zero
if ($row['post_date']<'2020-01-01') { $row['so_po_ref_id'] = 2; } // patch for transfers between defunct stores
                $qty = abs($row['qty']); // make sure it's positive, sometimes the db reverses the lines, assumes that all store transfers are positive numbers
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['store_id'],    'qty'=> $qty*$qtyReqd];
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['so_po_ref_id'],'qty'=>-$qty*$qtyReqd];
                $row = array_shift($result); // dump the corresponding row
            } else {
                $posts[] = ['id'=>$row['id'], 'ref'=>$row['invoice_num'], 'jID'=>$row['journal_id'], 'date'=>$row['post_date'], 'store'=>$row['store_id'], 'qty'=>$row['qty']*$qtyReqd];
            }
        }
        foreach ($stores as $store) {
            $begBal['b'.$store['id']] = $curStk['b'.$store['id']] - $jrnlBal['b'.$store['id']] - $dateBal['b'.$store['id']];
        }
        msgDebug("\nfinished calculations, begBal = ".print_r($begBal, true));
        msgDebug("\nfinished calculations, jrnlBal = ".print_r($jrnlBal, true));
        msgDebug("\nfinished calculations, dateBal = ".print_r($dateBal, true));
        $output = ['beg_bal'=>$begBal, 'rows'=>$posts, 'cur_bal'=>$curStk];
        msgDebug("\nReturning from skuDrillDownActivity with output = ".print_r($output, true));
        return $output;
    }

    /**
    * Gets the quantity in stock by branch
    * @param type $sku
    * @return type
    */
   private function getCurrentStock($sku='') {
        $stores= getModuleCache('bizuno', 'stores');
        foreach ($stores as $store) { $output['b'.$store['id']] = 0; }
        // get history table values
        $hist  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND remaining>0");
//      msgDebug("\nRead balance from inventory history: ".print_r($balance, true));
        foreach ($hist as $row) { $output['b'.$row['store_id']] += $row['remaining']; }
        // subtract stock owed
        $owed  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_cogs_owed', "sku='".ADDSLASHES($sku)."'");
        foreach ($owed as $row) { $output['b'.$row['store_id']] -= $row['qty']; }
        msgDebug("\nLeaving getCurrentStock with output = ".print_r($output, true));
        return $output;
   }
}
