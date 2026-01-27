<?php
/*
 * @name Bizuno ERP Extension - invAutoAssy
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
 * @version    7.x Last Update: 2025-06-17
 * @filesource /controllers/phreebooks/autoAssy.php
 */

namespace bizuno;

class phreebooksAutoAssy {
    public  $moduleID = 'phreebooks';

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
    }

    /**
     * Auto-assemble based on passed journal record ID
     * @return messages depending on outcome
     */
    public function autoAssy()
    {
        if (!$security = validateAccess('j14_mgr', 2)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        $rID   = clean('rID', 'integer', 'get');
        $main  = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$rID");
        if (!in_array($main['journal_id'], [9, 10, 12])) { return msgAdd("Operation not permitted!"); }
        $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        $acted = 0;
        foreach ($items as $item) {
            if ($item['gl_type'] <> 'itm' || empty($item['sku'])) { continue; } // not a SKU
            $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='".addslashes($item['sku'])."'");
            if (!in_array($inv['inventory_type'], ['sa','ma'])) { continue; } // not an assembly
            switch ($main['journal_id']) {
                case  9:
                case 10:
                    $prior  = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'SUM(qty) AS qty', "item_ref_id={$item['id']} AND gl_type='itm'", false); // so/po - filled
                    $needed = ($item['qty'] - $prior) - $inv['qty_stock'];
                    break;
                case 12: 
                    $needed = min($item['qty'], 0-$inv['qty_stock']);
                    break; // negative in stock
                default:
            }
            
            if ($needed > 0) {
                $this->buildSKU($inv, $needed, $main, $item);
                $acted++;
            }
        }
        msgAdd(sprintf($this->lang['msg_assy_success'], $acted), 'success');
    }

    /**
     * Create the assembly journal entry
     * @param array $skuInfo - db SKU record
     * @param float $qty - quantity to assemble
     * @param array $main - order db main journal record
     * @param array $item - order db single item record
     * @return null - errors added to messages
     */
    private function buildSKU($skuInfo, $qty, $main=[], $item=[]) {
        dbTransactionStart();
        $glEntry = new journal(0, 14);
        $glEntry->main['description'] = "{$this->lang['auto_assy']} ($qty) {$skuInfo['sku']} - {$skuInfo['description_short']}";
        $glEntry->main['invoice_num'] = $main['invoice_num']."-{$item['item_cnt']}";
        $glEntry->main['store_id']    = $main['store_id'];
        $glEntry->main['gl_acct_id']  = $skuInfo['gl_inv'];
        $glEntry->main['closed']      = 1;
        $glEntry->main['closed_date'] = biz_date('Y-m-d');
        $glEntry->items[]= [
            'gl_type'    => 'asy',
            'sku'        => $skuInfo['sku'],
            'qty'        => $qty,
            'description'=> $skuInfo['description_short'],
            'gl_account' => $skuInfo['gl_inv'],
            'post_date'  => biz_date('Y-m-d')];
        if (!$glEntry->Post()) { return dbTransactionRollback(); }
        dbTransactionCommit();
    }
}
