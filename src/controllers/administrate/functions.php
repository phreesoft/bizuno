<?php
/*
 * @name Bizuno ERP - Fixed Assets Extension
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
 * @version    7.x Last Update: 2025-06-01
 * @filesource /controllers/administrate/functions.php
 */

namespace bizuno;

/**
 * Data processing operation used by PhreeForm handle select field text translations.
 * @param string $value - contains the qty:sku_id encoded integer values
 * @param string $format - the work order processing to perform
 * @return string - processed result, depends on the format requested
 */
function administrateView($value, $format='') {
    switch ($format) {
        case 'faType':
            bizAutoLoad(dirname(__FILE__).'/admin.php', 'proGLAdmin');
            $extRtn = new proGLAdmin();
            return isset($extRtn->lang["fa_type_$value"]) ? $extRtn->lang["fa_type_$value"] : $value;
        case 'faCond':
            if ($value=='n') { return lang('new'); }
            elseif ($value=='u') {
                bizAutoLoad(dirname(__FILE__).'/admin.php', 'proGLAdmin');
                $extRtn = new proGLAdmin();
                return $extRtn->lang['used'];
            }
            else { return $value; }
        case 'storeStock':
            $storeID  = !empty($GLOBALS['bizuno_store_id']) ? $GLOBALS['bizuno_store_id'] : 0;
            $thisStore= dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', 'SUM(remaining) AS remaining', "remaining>0 AND store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisStore) { $thisStore = 0;}
            $thisOwed = dbGetValue(BIZUNO_DB_PREFIX.'journal_cogs_owed', 'SUM(qty) AS qty', "store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisOwed) { $thisOwed = 0;}
            return $thisStore - $thisOwed; // removed viewFormat(*, 'number')
        default:
    }
    return $value;
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
