<?php
/*
 * Inventory module support functions
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
 * @version    7.x Last Update: 2025-10-06
 * @filesource /controllers/inventory/functions.php
 */

namespace bizuno;

/**
 * Processes a value by format, used in PhreeForm
 * @global array $report - report structure
 * @param mixed $value - value to process
 * @param type $format - what to do with the value
 * @return mixed, returns $value if no formats match otherwise the formatted value
 */
function inventoryProcess($value, $format='')
{
    global $report;
    switch ($format) {
        case 'image_sku': return dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "sku='".addslashes($value)."'");
        case 'inv_image': return dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "id='".intval($value)."'");
        case 'inv_sku':   return empty($value) ? '' : ($result=dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku',                 "id="  .intval($value))        ? $result : '');
        case 'inv_shrt':  return empty($value) ? '' : ($result=dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short',   "id="  .intval($value))        ? $result : '');
        case 'sku_name':  return empty($value) ? '' : ($result=dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short',   "sku='".addslashes($value)."'")? $result : '');
        case 'inv_assy':  return dbGetInvAssyCost($value);
        case 'inv_j06_id':return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_purchase',"id='$value'")) ? $result : $value;
        case 'inv_j06':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_purchase',"sku='".addslashes($value)."'"))? $result : $value;
        case 'inv_j12_id':return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_sales',   "id='$value'")) ? $result : $value;
        case 'inv_j12':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_sales',   "sku='".addslashes($value)."'"))? $result : $value;
        case 'inv_mv0':   $range = 'm0';
        case 'inv_mv1':   if (empty($range)) { $range = 'm1'; }
        case 'inv_mv3':   if (empty($range)) { $range = 'm3'; }
        case 'inv_mv6':   if (empty($range)) { $range = 'm6'; }
        case 'inv_mv12':  if (empty($range)) { $range = 'm12';}
                          return viewInvSales($value, $range); // value passed should be the SKU
        case 'inv_stk':   return viewInvMinStk($value); // value passed should be the SKU
        case 'storeStock':
            $storeID  = !empty($GLOBALS['bizuno_store_id']) ? $GLOBALS['bizuno_store_id'] : 0;
            $thisStore= dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', 'SUM(remaining) AS remaining', "remaining>0 AND store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisStore) { $thisStore = 0;}
            $thisOwed = dbGetValue(BIZUNO_DB_PREFIX.'journal_cogs_owed', 'SUM(qty) AS qty', "store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisOwed) { $thisOwed = 0;}
            return $thisStore - $thisOwed; // removed viewFormat(*, 'number')
        case 'sbBOM':
            $sku   = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['sku', 'qty'], "ref_id=$value");
            $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($sku['sku'])."'");
            $meta  = getMetaInventory($skuID, 'bill_of_materials');
            msgDebug("\nIn inventoryProcess with value = $value and sku = ".print_r($sku, true));
            $output= lang('qty').' - '.lang('sku').' - '.lang('description')."\n";
            foreach ($meta as $row) { $output .= ($sku['qty']*$row['qty'])." - {$row['sku']} - {$row['description']}\n"; }
            return $output;
        case 'sbOnOrder':
            msgDebug("\nEntering sbOnOrder with value = ".print_r($value, true));
            $sku    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$value");
            $stmt   = dbGetResult("SELECT SUM(i.qty) AS 'qty' FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id 
                WHERE m.journal_id=32 AND m.closed='0' AND sku='".addslashes($sku)."'");
            $row    = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : [];
            return !empty($row['qty']) ? $row['qty'] : 0;
        case 'sbSteps':
            msgDebug("\nEntering sbSteps with value = ".print_r($value, true));
return '';
            $data  = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'srvBuilder_jobs', 'steps', "id='$value'"), true);
            $output= '';
            foreach ($data as $step => $row) { $output .= "$step. ".dbGetValue(BIZUNO_DB_PREFIX."srvBuilder_tasks", 'description', "id='{$row['task_id']}'")."\n"; }
            return $output;
            return 'needs work';
        case 'sbTask':
            msgDebug("\nEntering sbTask with value = ".print_r($value, true));
return '';
            $result = dbGetValue(BIZUNO_DB_PREFIX.'srvBuilder_tasks', 'description', "id='$value'");
            return $result ? $result : $value;
        case 'sbTaskList':
            msgDebug("\nEntering sbTaskList with value = ".print_r($value, true));
            $sku   = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['sku', 'qty'], "ref_id=$value");
            $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($sku['sku'])."'");
            $meta  = getMetaInventory($skuID, 'production_job');
            msgDebug("\nread meta = ".print_r($meta, true));
//            $data  = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'steps', "id='$value'"), true);
            $output= [];
            foreach ($meta['steps'] as $step => $task) {
                $tMeta = dbMetaGet($task['task_id'], 'production_task');
                msgDebug("\nread meta = ".print_r($tMeta, true));
                $output[] = [
                    'r0' => $step,
                    'r1' => $tMeta['description'],
                    'r2' => !empty($tMeta['mfg'])? lang('yes') : '',
                    'r3' => !empty($tMeta['qa']) ? lang('yes') : ''];
            }
            $result= sortOrder($output, 'step');
            return $result;
        case 'sbRefDraw':
return '';
            $result = dbGetValue(BIZUNO_DB_PREFIX.'srvBuilder_jobs', 'ref_spec', "id='$value'");
            return $result ? $result : ' ';
        case 'sbRefDocs':
return '';
            $result = dbGetValue(BIZUNO_DB_PREFIX.'srvBuilder_jobs', 'ref_doc', "id='$value'");
            return $result ? $result : ' ';
        default:
    }
    if (substr($format, 0, 5) == 'skuPS') { // get the sku price based on the price sheet passed
        if (!$value) { return ''; }
        $fld   = explode(':', $format);
        if (empty($report->currentValues['id']) || empty($report->currentValues['unit_price']) || empty($report->currentValues['full_price'])) { // need to get the sku details
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id','item_cost','full_price'], "sku='".addslashes($value)."'");
        } else { $inv = $report->currentValues; }
        $values= ['iID'=>$inv['id'], 'iCost'=>$inv['item_cost'],'iList'=>$inv['full_price'],'iSheetc'=>$fld[1],'iSheetv'=>$fld[1],'cID'=>0,'cSheet'=>$fld[1],'cType'=>'c','qty'=>1];
        $prices= [];
        bizAutoLoad(BIZBOOKS_ROOT."controllers/inventory/prices.php", 'inventoryPrices');
        $mgr   = new inventoryPrices();
        $mgr->pricesLevels($prices, $values);
        return $prices['price'];
    }
    return $value;
}

function inventoryView($value, $format='') {
    switch ($format) {
        case 'buySell':
            // receive value, get id from keyed values, read db, convert value
            if (empty($GLOBALS['currentRow']['id'])) { msgDebug(" ... EMPTY ID, returning $value"); return $value; }
            $prop  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['buy_uom','sell_uom','sell_qty'], "id={$GLOBALS['currentRow']['id']}");
            if (empty($prop['sell_qty']) || $prop['sell_qty'] == 1) { msgDebug(" ... SELL QTY = 1, returning $value"); return $value; }
            $newVal= round($value/$prop['sell_qty'], 2);
            if (empty($GLOBALS['invBuySell']['lang']['uom_'.$prop['buy_uom']])) { $GLOBALS['invBuySell']['lang']['uom_'.$prop['buy_uom']] = '???'; }
            return $newVal." (".$GLOBALS['invBuySell']['lang']['uom_'.$prop['buy_uom']].")";
    }
}

function invIsTracked($type) {
    $tracked = explode(',', COG_ITEM_TYPES);
    msgDebug("\nIn invIsTracked with type = $type and is tracked = ".(in_array($type, $tracked) ? 'true' : 'false'));
    return in_array($type, $tracked) ? true : false;
}

/**
 * Calculates the quantity of a given SKU available to sell
 * @param array $item - pulled directly from the inventory db
 * @return type
 */
function availableQty($item=[], $args=[])
{
    if (empty($item['id']))            { return 0; }
    $incAssy  = isset($args['incAssy'])  ? $args['incAssy']  : getModuleCache('inventory', 'settings', 'general', 'inc_assemblies', 1);
    $incCommit= isset($args['incCommit'])? $args['incCommit']: getModuleCache('inventory', 'settings', 'general', 'inc_committed', 1);
    if (empty($item['qty_stock']))     { $item['qty_stock']      = 0; }
    if (empty($item['qty_so']))        { $item['qty_so']         = 0; }
    if (empty($item['qty_alloc']))     { $item['qty_alloc']      = 0; }
    if (empty($item['inventory_type'])){ $item['inventory_type'] = 'si'; }
    if (strpos(COG_ITEM_TYPES, $item['inventory_type']) === false) { $item['qty_stock'] = 1; } // Fix some special cases, non-stock types need qty > 0
    msgDebug("\nIn availableQty with incAssy = $incAssy and incCommit = $incCommit");
    if ($incAssy && in_array($item['inventory_type'], ['ma', 'sa'])) { // for assemblies, see how many we can build
        $bom = getMetaInventory($item['id'], 'bill_of_materials');
        msgDebug("\nAssy parts = ".print_r($bom, true));
        $min_qty= 999999;
        foreach ($bom as $row) {
            $inv    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['qty_stock', 'inventory_type'], "sku='".addslashes($row['sku'])."'");
            if (strpos(COG_ITEM_TYPES, $inv['inventory_type']) === false) { continue; } // non-stock stuff so move along
            $qtyStk = !empty($inv['qty_stock']) ? $inv['qty_stock'] : 0;
            $min_qty= $row['qty'] == 0 ? 0 : min($min_qty, floor($qtyStk / $row['qty']));
        }
        $item['qty_stock'] += $min_qty;
        msgDebug("\nAfter assembly item[qty_stock] = ".print_r($item['qty_stock'], true));
    }
    if ($incCommit) { $item['qty_stock'] -= ($item['qty_so'] + $item['qty_alloc']); }
    $toSell = max(0, $item['qty_stock']);
    msgDebug("\nReturning with available = $toSell");
    return $toSell;
}

/**
 * Renames the SKU in the BOM list meta data
 * @param type $oldSKU - The old SKU
 * @param type $newSKU - The new SKU
 */
function renameMetaBOM($oldSKU, $newSKU)
{
    $cnt = 0;
    $BOMs = dbMetaGet(0, 'bill_of_materials', 'inventory', '%');
    msgDebug("\nWorking in renameMetaBOM  with number of BOMS = ".sizeof($BOMs));
    if (empty($BOMs)) { return $cnt; }
    foreach ($BOMs as $bom) {
        $found= false;
        foreach ($bom as $key => $value) {
            if (empty($value['sku'])) { continue; } // skip blank lines
            if ($value['sku']==$oldSKU) { $bom[$key]['sku'] = $newSKU; $found = true; }
        }
        if ($found) {
            $cnt++;
            $refID = $bom['_refID'];
            $metaID = metaIdxClean($bom);
            dbMetaSet($metaID, 'bill_of_materials', $bom, 'inventory', $refID);
        }
    }
    return $cnt;
}

/**
 * Pulls current stock levels by store with other tidbits of data
 * @param type $sku
 * @return type
 */
function getStoreStock($sku='') {
    $output  = [];
    $newCost = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='".addslashes($sku)."'");
    $balance = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "sku='".addslashes($sku)."' AND remaining>0");
//    msgDebug("\nRead balance from inventory history: ".print_r($balance, true));
    foreach ($balance as $row) {
        if (empty($output['b'.$row['store_id']])) { $output['b'.$row['store_id']] = ['stock'=>0, 'cost'=>$newCost]; }
        $output['b'.$row['store_id']]['stock']+= $row['remaining'];
        $output['b'.$row['store_id']]['cost']  = max($output['b'.$row['store_id']]['cost'], $newCost); // pulls the highest cost
    }
    // subtract stock owed
    $owed = dbGetMulti(BIZUNO_DB_PREFIX.'journal_cogs_owed', "sku='".ADDSLASHES($sku)."'");
    foreach ($owed as $row) {
        if (!isset($output['b'.$row['store_id']]['stock'])) { $output['b'.$row['store_id']]['stock'] = 0; }
        $output['b'.$row['store_id']]['stock'] -= $row['qty'];
    }
    msgDebug("\nLeaving getStoreStock with output = ".print_r($output, true));
    return $output;
}

/**
 * Pulls the quantity of a SKU on order by store
 * @param type $sku
 * @param type $jID
 */
function getStoreOnOrder($sku='', $jID=10) {
    $output=[];
    $stmt = dbGetResult("SELECT i.id, m.store_id, i.qty, i.item_ref_id FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
        WHERE m.journal_id='$jID' AND i.sku='".addslashes($sku)."' AND m.closed='0'");
    if (empty($stmt)) { msgDebug ("\nNo Results for sku = $sku and journal ID = $jID"); return $output; }
    $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
    msgDebug("\nReturned number of open SO/PO rows = ".sizeof($result));
    msgDebug("\nRows = ".print_r($result, true));
    foreach ($result as $row) {
        $bal = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty)", "item_ref_id={$row['id']} AND gl_type='itm'", false); // so/po - filled
        if (empty($bal)) { $bal = 0; }
        msgDebug("\nCalculated balance = ".print_r($bal, true));
        if (!isset($output[$row['store_id']]['order'])) { $output[$row['store_id']]['order'] = 0; }
        $output[$row['store_id']]['order'] += $row['qty'] - $bal;
    }
    msgDebug("\nOutput = ".print_r($output, true));
    return $output;
}
