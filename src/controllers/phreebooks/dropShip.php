<?php
/*
 * @name Bizuno ERP Extension - custDropShip
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/phreebooks/dropShip.php
 */

namespace bizuno;

class phreebooksDropShip
{
    public  $moduleID = 'phreebooks';

    function __construct()
    {
    }

    public function savePO(&$layout=[])
    {
        if (!$security = validateAccess("j4_mgr", 2, false)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $main = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$rID");
        $items= dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        $vID = false;
        foreach ($items as $row) {
            if (!isset($row['sku'])) { continue; }
            $skus[$row['sku']] = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='{$row['sku']}'");
            if (empty($skus[$row['sku']]) || !in_array($skus[$row['sku']]['inventory_type'], INVENTORY_COGS_TYPES)) { continue; } // sku must be a stockable type
            if (!$vID && $skus[$row['sku']]['vendor_id']) { $vID = $skus[$row['sku']]['vendor_id']; }
            elseif ($vID && $vID <> $skus[$row['sku']]['vendor_id']) { return msgAdd(lang('err_multiple_vendors', $this->moduleID)); }
        }
        if (!$vID) { return msgAdd(lang('err_no_vendor_found', $this->moduleID)); }
        $contactV = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$vID");
        // create the new PO
        $main['id'] = $main['waiting'] = $main['attach'] = $main['discount'] = $main['so_po_ref_id'] = $main['invoice_num'] = $main['recur_id'] = $main['freight'] = 0;
        $main['post_date']   = $main['terminal_date'] = biz_date('Y-m-d');
        $main['period']      = getModuleCache('phreebooks', 'fy', 'period');
        $main['journal_id']  = 4;
        foreach ($contactV as $key => $value) { if (isset($main[$key.'_b'])) { $main[$key.'_b'] = $value; } }
        $main['description'] = lang('journal_id_4').": {$main['primary_name_b']}";
        $main['terms']       = $contactV['terms'];
        $main['admin_id']    = getUserCache('profile', 'userID');
//        $main['rep_id']      = getUserCach'profile', 'userID'd'); // Keep the rep the same as the Sale
        $main['gl_acct_id']  = getModuleCache('phreebooks', 'settings', 'vendors', 'gl_payables');
        $main['drop_ship']   = 1;
        $main['contact_id_b']= $vID;
        // now fix the rows
        $total = 0;
        msgDebug("\nItems before processing = ".print_r($items, true));
        foreach ($items as $idx => $row) {
            $items[$idx]['id'] = $items[$idx]['ref_id'] = $items[$idx]['item_ref_id'] = $items[$idx]['trans_code'] = $items[$idx]['reconciled'] = 0;
            $items[$idx]['post_date']    = $items[$idx]['date_1'] = biz_date('Y-m-d');
            switch ($row['gl_type']) {
                case 'itm': // it has a SKU
                    if (!empty($row['sku'])) {
                        $items[$idx]['description']  = $skus[$row['sku']]['description_purchase'];
                        $items[$idx]['gl_account']   = $skus[$row['sku']]['gl_inv'];
                        $items[$idx]['debit_amount'] = $row['qty'] * $skus[$row['sku']]['item_cost'];
                    } else {
                        $items[$idx]['debit_amount'] = $row['credit_amount'];
                    }
                    $items[$idx]['credit_amount']= 0;
                    $items[$idx]['tax_rate_id']  = 0;
                    $total += $items[$idx]['debit_amount'];
                    break;
                case 'frt':
                    $items[$idx]['debit_amount'] = $items[$idx]['credit_amount'] = 0;
                    break;
                case 'tax':// Assume no tax, remove tax rows
                    unset($items[$idx]);
                    break;
                case 'ttl':
                    $items[$idx]['gl_account']   = getModuleCache('phreebooks', 'settings', 'vendors', 'gl_payables');
                    $items[$idx]['debit_amount'] = $items[$idx]['credit_amount']= 0;
                    $ttlIndex = $idx;
                    break;
                default:
            }
        }
        // calculate purchase tax (Assume zero for now)
        $main['sales_tax']  = 0;
        $main['tax_rate_id']= 0;
        // update total based on purchase
        $items[$ttlIndex]['credit_amount'] = $total;
        $main['total_amount'] = $total;
        msgDebug("\nReady to post with main = " .print_r($main, true));
        msgDebug("\nReady to post with items = ".print_r($items, true));
        // Post it
        dbTransactionStart();
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        $ledger = new journal(0, 4);
        $ledger->main  = $main;
        $ledger->items = $items;
        if (!$ledger->Post()) { return; }
        msgDebug("\n  Committing order invoice_num = {$ledger->main['invoice_num']} and id = {$ledger->main['id']}");
        dbTransactionCommit();
        // return with print popup
        $formID     = getDefaultFormID(4);
        $jsonAction = " winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&date=a&xfld=journal_main.id&xcr=equal&xmin={$ledger->main['id']}');";
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>  ['action'=>'eval','actionData'=>$jsonAction]]);
    }
}
