<?php
/*
 * Inventory history functions
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
 * @filesource /controllers/inventory/history.php
 */

namespace bizuno;

class inventoryHistory
{
    public $moduleID = 'inventory';
    public $lang;
    public $percent_diff  = 0.10; // the percentage differnece from current value to notify for adjustment
    public $months_of_data= 12;   // valid values are 1, 3, 6, or 12
    public $med_avg_diff  = 0.25; // the maximum percentage difference from the median and average, for large swings

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Ajax call to refresh the movement tab of an inventory item being edited.
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function movement(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return ("This SKU does not have any history!"); }
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        $data = ['type'=>'divHTML',
            'divs'    => [
                'main' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'dgJ10' => ['order'=>20,'type'=>'panel','key'=>'dgJ10','classes'=>['block99']],
                    'dgWO'  => ['order'=>25,'type'=>'panel','key'=>'dgWO', 'classes'=>['block99']], // forces it on it's own line so the rest line up
                    'dgJ04' => ['order'=>30,'type'=>'panel','key'=>'dgJ04','classes'=>['block99']],
                    'dgJ09' => ['order'=>35,'type'=>'panel','key'=>'dgJ09','classes'=>['block99']],
                    'dgJ03' => ['order'=>40,'type'=>'panel','key'=>'dgJ03','classes'=>['block99']],
                    'dgJ12' => ['order'=>45,'type'=>'panel','key'=>'dgJ12','classes'=>['block99']],
                    'dgJ06' => ['order'=>50,'type'=>'panel','key'=>'dgJ06','classes'=>['block99']]]]],
            'panels'  => [
                'dgJ03' => ['type'=>'datagrid','key'=>'dgJ03'],
                'dgJ04' => ['type'=>'datagrid','key'=>'dgJ04'],
                'dgJ06' => ['type'=>'datagrid','key'=>'dgJ06'],
                'dgJ09' => ['type'=>'datagrid','key'=>'dgJ09'],
                'dgJ10' => ['type'=>'datagrid','key'=>'dgJ10'],
                'dgJ12' => ['type'=>'datagrid','key'=>'dgJ12'],
                'dgWO'  => ['type'=>'datagrid','key'=>'dgWO']],
            'datagrid'=> [
                'dgJ03' => $this->dgJ04J10( 3, $rID),
                'dgJ04' => $this->dgJ04J10( 4, $rID),
                'dgJ06' => $this->dgJ06J12( 6, $rID, $sku),
                'dgJ09' => $this->dgJ04J10( 9, $rID),
                'dgJ10' => $this->dgJ04J10(10, $rID),
                'dgJ12' => $this->dgJ06J12(12, $rID, $sku),
                'dgWO'  => $this->dgWO($rID)]];
        $layout = array_replace_recursive($layout, $data);
        // drop ship PO
        if (validateAccess('j4_mgr', 2, false)) { // Drop Ship action
            $layout['datagrid']['dgJ10']['columns']['action']['actions']['xDropShip'] = ['order'=>45,'icon'=>'order','label'=>lang('create_po'),
                'events' => ['onClick'=>"if (confirm('".lang('msg_confirm_create_po')."')) jsonAction('$this->moduleID/dropShip/savePO', idTBD);"]];
        }
    }

    /**
     * Rows for the inventory movement grid for purchases and sales
     * @param type $layout
     */
    public function movementRows(&$layout=[])
    {
        $jID   = clean('jID',   'integer', 'get');
        $skuID = clean('skuID', 'integer', 'get');
        if (!$security = validateAccess("j{$jID}_mgr", 1)) { return; }
        $sku   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$skuID");
        $struc = $this->dgJ06J12($jID, $skuID, $sku);
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$struc]]);
        msgDebug("\nLeaving movementRows with layout = ".print_r($layout, true));
    }

    /**
     * Builds the rows for Work Order movement
     * @param array $layout
     * @return modified $layout
     */
    public function buildRows(&$layout=[])
    {
        if (!$security = validateAccess('woProd', 1)) { return; }
        $output = $skus = [];
        $skuID  = clean('skuID', 'integer', 'get');
        $sku    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$skuID");
        $stmt   = dbGetResult("SELECT journal_main.id as id, journal_item.sku, journal_item.qty, journal_main.notes, journal_main.post_date, journal_main.terminal_date, journal_main.store_id
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_item ON journal_main.id=journal_item.ref_id WHERE journal_id=32 AND closed='0'");
        $openWOs= $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nRead ".sizeof($openWOs)." open WO's while searching for sku: $sku = ");
        foreach ($openWOs as $row) { // search within the BOM for each skuID for this SKU
            if ($row['sku']==$sku) {
                $output[]= ['id'=>$row['id'], 'sku'=>$row['sku'], 'qty'=>$row['qty'], 'store_id'=>viewFormat($row['store_id'], 'storeID'),
                    'post_date'=>$row['post_date'], 'terminal_date'=>$row['terminal_date'], 'notes'=>substr($row['notes'], 0, 30)];
            }
            // get the skuID and then meta for that SKU
            $bomID= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($row['sku'])."'");
            if (empty($bomID)) { continue; } // flagged as assembly but has no BOM
            $bom  = getMetaInventory($bomID, 'bill_of_materials');
            foreach ($bom as $item) {
                if (empty($item['sku'])) { continue; }
                if ($item['sku']==$sku) {
                    $output[]= ['id'=>$row['id'], 'sku'=>$row['sku'], 'qty'=>intval($row['qty'] * $item['qty']), 'store_id'=>viewFormat($row['store_id'], 'storeID'), 
                        'post_date'=>$row['post_date'], 'terminal_date'=>$row['terminal_date'], 'notes'=>substr($row['notes'], 0, 30)];
                }
            }
        }
        $gridData = viewGridFilter($output);
        msgDebug("\nOutput = ".print_r($gridData, true));
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode(['total'=>sizeof($output),'rows'=>$gridData])]);
    }

    /**
     * Generates lists of historical values for an inventory item for the past 12 months
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function historian(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID    = clean('rID', 'integer', 'get');
        if (!$rID) { return ("This SKU does not have any history!"); }
        $skuInfo= dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id='$rID'");
        $history= $this->historyData($skuInfo);
        $fields = [
            'create' => ['order'=>10,'label'=>lang('creation_date'),    'attr'=>['type'=>'date','readonly'=>true,'value'=>$skuInfo['creation_date']]],
            'update' => ['order'=>20,'label'=>lang('last_update'),      'attr'=>['type'=>'date','readonly'=>true,'value'=>$skuInfo['last_update']]],
            'journal'=> ['order'=>30,'label'=>lang('last_journal_date'),'attr'=>['type'=>'date','readonly'=>true,'value'=>$skuInfo['last_journal_date']]],
            'usage'  => ['order'=>60,'html' =>$this->viewHistorianUsage($history),'attr'=>['type'=>'raw']]];
        $data   = ['type'=>'divHTML',
            'divs'    =>[
                'main'  => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'annual'=> ['order'=>10,'type'=>'panel','key'=>'annual','classes'=>['block33']],
                    'usage' => ['order'=>20,'type'=>'panel','key'=>'usage', 'classes'=>['block33']],
                    'dates' => ['order'=>30,'type'=>'panel','key'=>'dates', 'classes'=>['block33']],
                    'dgJ06' => ['order'=>50,'type'=>'panel','key'=>'dgJ06', 'classes'=>['block50']],
                    'dgJ12' => ['order'=>60,'type'=>'panel','key'=>'dgJ12', 'classes'=>['block50']],
                    ]]],
            'panels'  => [
                'annual'=> ['label'=>$this->lang['annual_usage'],'type'=>'datagrid','key'=>'annual'],
                'usage' => ['label'=>$this->lang['average_usage'],  'type'=>'fields','keys'=>['usage']],
                'dates' => ['label'=>lang('details'),'type'=>'fields','keys'=>['create','update','journal']],
                'dgJ06' => ['type'=>'datagrid','key'=>'dgJ06'],
                'dgJ12' => ['type'=>'datagrid','key'=>'dgJ12']],
            'datagrid'=> [
                'annual'=>$this->dgAnnualHistory('dgAnnual', $this->annualHistory($skuInfo['sku'])),
                'dgJ06' =>$this->dgJ06J12Avg(6),
                'dgJ12' =>$this->dgJ06J12Avg(12)],
            'fields'  => $fields,
            'jsHead'  => ['init' =>"var dataJ6 = " .json_encode($history['purchases']).";\nvar dataJ12 = ".json_encode($history['sales']).";"]];
        msgDebug("\nReturning from inventory history with array = ".print_r($history, true));
        $layout = array_replace_recursive($layout, $data);
    }

    private function viewHistorianUsage($history)
    {
        // Calculate the annual averages

        $usage  = '<div><br /><hr /><h2>'.$this->lang['stock_usage_lbl'].'</h2>
<table style="width:100%"><thead class="panel-header"><tr><th style="width:50%">&nbsp;</th><th style="width:25%">'.lang('journal_id_6').'</th><th style="width:25%">'.lang('journal_id_12')."</th></tr></thead><tbody>
    <tr><td>".$this->lang['01month'].'</td><td style="text-align:center;">'.$history['01purch'].'</td><td style="text-align:center;">'.$history['01sales']."</td></tr>
    <tr><td>".$this->lang['03month'].'</td><td style="text-align:center;">'.$history['03purch'].'</td><td style="text-align:center;">'.$history['03sales']."</td></tr>
    <tr><td>".$this->lang['06month'].'</td><td style="text-align:center;">'.$history['06purch'].'</td><td style="text-align:center;">'.$history['06sales']."</td></tr>
    <tr><td>".$this->lang['12month'].'</td><td style="text-align:center;">'.$history['12purch'].'</td><td style="text-align:center;">'.$history['12sales'].'</td></tr>
</tbody></table>';
        if (!empty($history['id'])) { $usage .= '<br />'.lang('id').': '.$history['id']."\n"; }
        $usage  .= '</div>';
        return $usage;
    }

    /**
     * Generates complete history of a SKU as far back as the db table journal_main supports
     * @param type $sku
     */
    public function annualHistory($sku)
    {
        // For monthly
//      $stmt  = dbGetResult("SELECT DATE_FORMAT(m.post_date, '%Y') AS 'year', DATE_FORMAT(m.post_date, '%m') AS 'month', SUM(i.qty) AS 'qty', SUM(i.credit_amount) AS 'total'
//          FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id WHERE m.journal_id=12 AND sku='$sku' GROUP BY DATE_FORMAT(m.post_date, '%Y%m') DESC");
        // For yearly
        $stmt  = dbGetResult("SELECT DATE_FORMAT(m.post_date, '%Y') AS 'year', SUM(ABS(i.qty)) AS 'qty', SUM(i.credit_amount + i.debit_amount) AS 'total'
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id WHERE sku='".addslashes($sku)."' AND ((m.journal_id=12 AND i.gl_type='itm') OR (m.journal_id=14 AND i.gl_type='asi'))
            GROUP BY DATE_FORMAT(m.post_date, '%Y') DESC");
        // Get the data
        $rows  = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        return $rows;
    }

    /**
     * Retrieves the historical PO/SO data for a given SKU
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function historyRows(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $jID  = clean('jID',  'integer', 'get');
        $skuID= clean('skuID','integer', 'get');
        $sort = clean('sort', ['format'=>'cmd','default'=>'date_1'], 'post');
        $order= strtoupper(clean('order', ['format'=>'cmd','default'=>'ASC'], 'post'));
        $tail = "ORDER BY $sort $order";
        $data = [];
        $sku  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$skuID");
        $stmt = dbGetResult("SELECT m.id, m.journal_id, m.store_id, m.invoice_num, m.purch_order_id, m.primary_name_b, m.rep_id, m.waiting, i.qty, i.post_date, i.date_1,
            i.id AS item_id FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.journal_id='$jID' AND i.sku='$sku' AND m.closed='0' $tail");
        if ($stmt) {
            $result= $stmt->fetchAll(\PDO::FETCH_ASSOC);
            msgDebug("\nReturned number of open SO/PO rows = ".sizeof($result));
            foreach ($result as $row) {
                $adj = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty) AS qty", "gl_type='itm' AND item_ref_id={$row['item_id']}", false);
                if ($row['qty'] > $adj) { $data[] = [
                    'id'            => $row['id'],
                    'store_id'      => viewFormat($row['store_id'],'storeID'),
                    'rep_id'        => viewFormat($row['rep_id'],  'contactID'),
                    'waiting'       => $row['waiting'],
                    'invoice_num'   => $row['invoice_num'],
                    'primary_name_b'=> $row['primary_name_b'],
                    'purch_order_id'=> $row['purch_order_id'],
                    'post_date'     => viewDate($row['post_date']),
                    'qty'           => $row['qty'] - $adj,
                    'date_1'        => viewDate($row['date_1'])];
                }
            }
        }
        $layout = array_replace_recursive($layout, ['content'=>['total'=>sizeof($data),'rows'=>$data]]);
    }

    /**
     * Builds the history for a given sku
     * @param array $skuInfo
     * @return array
     */
    public function historyData($skuInfo=[], $verbose=true)
    {
        $history = ['id'=>$skuInfo['id'],'purchases'=>[],'sales'=>[]];
        // load the units received and sold, assembled and adjusted
        $dates = localeGetDates();
        $cur_month = $dates['ThisYear'].'-'.substr('0'.$dates['ThisMonth'], -2).'-01';
        for ($i = 0; $i < 13; $i++) {
            $index = substr($cur_month, 0, 7);
            $month = substr($index, 5, 2);
            $year  = substr($index, 0, 4);
            $history['purchases'][$index]= ['year'=>$year, 'month'=>lang('month_'.$month), 'qty'=>0, 'total'=>0, 'usage'=>0];
            $history['sales'][$index]    = ['year'=>$year, 'month'=>lang('month_'.$month), 'qty'=>0, 'total'=>0, 'usage'=>0];
            $cur_month = localeCalculateDate($cur_month, 0, -1, 0);
        }
        $next_month = localeCalculateDate($dates['ThisYear'].'-'.substr('0'.$dates['ThisMonth'], -2).'-01', 0, 1, 0);
        $last_year = ($dates['ThisYear']-1).'-'.substr('0'.$dates['ThisMonth'], -2).'-01';
        $sql = "SELECT m.journal_id, m.post_date, i.qty, i.gl_type, i.credit_amount, i.debit_amount
            FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.journal_id IN (6,7,12,13,14,16,19,21) AND i.sku='".addslashes($skuInfo['sku'])."' AND m.post_date>='$last_year' AND m.post_date<'$next_month'
            ORDER BY m.post_date DESC";
        $stmt  = dbGetResult($sql);
        $result2= $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nReturned monthly sales/purchases rows = ".sizeof($result2));
        foreach ($result2 as $row) {
            $index = substr($row['post_date'], 0, 7);
            switch ($row['journal_id']) {
                case  6:
                case 21: $history['purchases'][$index]['qty']   += $row['qty'];
                         $history['purchases'][$index]['usage'] += $row['qty'];
                         $history['purchases'][$index]['total'] += $row['debit_amount']; break;
                case  7: $history['purchases'][$index]['qty']   -= $row['qty'];
                         $history['purchases'][$index]['usage'] -= $row['qty'];
                         $history['purchases'][$index]['total'] -= $row['debit_amount']; break;
                case 12:
                case 19: $history['sales'][$index]['qty']       += $row['qty'];
                         $history['sales'][$index]['usage']     += $row['qty'];
                         $history['sales'][$index]['total']     += $row['credit_amount']; break;
                case 13: $history['sales'][$index]['qty']       -= $row['qty'];
                         $history['sales'][$index]['usage']     -= $row['qty'];
                         $history['sales'][$index]['total']     -= $row['debit_amount']; break;
                case 14: if ($row['gl_type'] == 'asi') { $history['sales'][$index]['usage'] -= $row['qty']; } break;
                case 16: $history['sales'][$index]['usage']     += $row['qty']; break;
            }
        }
        // calculate average usage
        $history['01purch']  = 0;
        $history['03purch']  = 0;
        $history['06purch']  = 0;
        $history['12purch']  = 0;
        $cnt = 0;
        $history['purchases']= array_values($history['purchases']);
        foreach ($history['purchases'] as $key => $value) {
            if ($cnt == 0) { $cnt++; continue; } // skip current month since we probably don't have the full months worth
            $history['12purch']               += $value['usage'];
            if ($cnt < 7) { $history['06purch'] += $value['usage']; }
            if ($cnt < 4) { $history['03purch'] += $value['usage']; }
            if ($cnt < 2) { $history['01purch'] += $value['usage']; }
            $cnt++;
        }
        $history['12purch'] = round($history['12purch'] / 12);
        $history['06purch'] = round($history['06purch'] /  6);
        $history['03purch'] = round($history['03purch'] /  3);

        $history['01sales'] = $history['02sales'] = $history['03sales'] = $history['06sales'] = $history['12sales'] = 0;
        $cnt1  = 0;
        $sales = [];
        $history['sales']   = array_values($history['sales']);
        foreach ($history['sales'] as $key => $value) {
            if ($cnt1 == 0) { $cnt1++; continue; }
            $history['12sales']               += $value['usage'];
            if ($cnt1 < 7) { $history['06sales'] += $value['usage']; }
            if ($cnt1 < 4) { $history['03sales'] += $value['usage']; }
            if ($cnt1 < 3) { $history['02sales'] += $value['usage']; }
            if ($cnt1 < 2) { $history['01sales'] += $value['usage']; }
            if ($cnt1 <= $this->months_of_data) { $sales[] = $value['usage']; }
            $cnt1++;
        }
        $history['12sales'] = round($history['12sales'] / 12);
        $history['06sales'] = round($history['06sales'] /  6);
        $history['03sales'] = round($history['03sales'] /  3);
        // find the restock levels that need adjustment
        if (getModuleCache('inventory', 'settings', 'general', 'stock_usage')
                && validateAccess('j6_mgr', 3, false)
                && in_array($skuInfo['inventory_type'], explode(',', COG_ITEM_TYPES))) {
//          $inv = dbGetValue(BIZUNO_DB_PREFIX."inventory", ['qty_min', 'lead_time'], "sku='{$skuInfo['sku']}'");
            sort($sales);
            $months        = substr('0'.$this->months_of_data, -2);
            $idx           = ceil(count($sales) / 2);
            $median_sales  = $sales[$idx];
            $average_sales = ceil($history[$months.'sales']);
            $new_min_stock = ceil($skuInfo['lead_time'] / 30) * $average_sales;
            $high_band     = $skuInfo['qty_min'] * (1 + $this->percent_diff);
            $low_band      = $skuInfo['qty_min'] * (1 - $this->percent_diff);
            msgDebug("\nAverage Sales = $average_sales and new min = $new_min_stock and High band = $high_band and low band = $low_band");
            $high_avg      = $average_sales  * (1 + $this->med_avg_diff);
            $low_avg       = $average_sales  * (1 - $this->med_avg_diff);
            msgDebug("\nHigh average = $high_avg and low average = $low_avg");
            if ($new_min_stock > $high_band || $new_min_stock < $low_band) {
                if ($verbose) { msgAdd(sprintf($this->lang['msg_inv_qty_min'], $new_min_stock), 'caution'); }
            }
            if ($median_sales > $high_avg || $median_sales < $low_avg) {
                if ($verbose) { msgAdd(sprintf($this->lang['msg_inv_median'], $median_sales, $average_sales), 'caution'); }
            }
        }
        return $history;
    }

    private function dgAnnualHistory($name, $rows=[])
    {
        return ['id' => $name,
            'attr'   => ['title'=>$this->lang['annual_history'], 'pagination'=>false, 'width'=>300],
            'events' => ['data'=>json_encode($rows)],
            'columns'=> [
                'year' => ['order'=>10,'label'=>'Year', 'attr'=>['width'=>200,'resizable'=>true,'align'=>'center']],
//              'month'=> ['order'=>20,'label'=>'Month','attr'=>['width'=>150,'resizable'=>true,'align'=>'center']],
                'qty'  => ['order'=>30,'label'=>'Qty',  'attr'=>['width'=>150,'resizable'=>true,'align'=>'center']],
                'total'=> ['order'=>40,'label'=>'Sales','attr'=>['width'=>200,'resizable'=>true,'align'=>'right'],'events'=>['formatter'=>"function(value) { return formatCurrency(value); }"]]]];
    }

    /**
     * Grid structure for PO/SO history with options
     * @param type $jID
     * @return type
     */
    private function dgJ04J10($jID=10, $skuID=0)
    {
        $hide_cost= validateAccess('j6_mgr', 1, false) ? false : true;
        $stores   = sizeof(getModuleCache('bizuno', 'stores'))>1 ? false : true;
        $label    = 'TBD';
        $icon     = 'phreesoft';
        $invID    = 0;
        switch ($jID) {
            case  3:
                $props = ['name'=>'dgJ03','title'=>lang('open_journal_3'), 'data'=>'dataPQ'];
                $hide  = validateAccess("j3_mgr", 1, false);
                $frmGrp= 'vend:j3';
                break;
            case  4:
                $props = ['name'=>'dgJ04','title'=>lang('open_journal_4'), 'data'=>'dataPO'];
                $label = lang('fill_purchase');
                $invID = 6;
                $icon  = 'purchase';
                $hide  = validateAccess("j4_mgr", 1, false);
                $frmGrp= 'vend:j4';
                break;
            case  9:
                $props = ['name'=>'dgJ09','title'=>lang('open_journal_9'), 'data'=>'dataSQ'];
                $hide  = validateAccess("j3_mgr", 1, false);
                $frmGrp= 'cust:j9';
                break;
            case 10:
                $props = ['name'=>'dgJ10','title'=>lang('open_journal_10'),'data'=>'dataSO'];
                $label = lang('fill_sale');
                $invID = 12;
                $icon  = 'sales';
                $hide  = validateAccess("j10_mgr", 1, false);
                $frmGrp= 'cust:j10';
                break;
        }
        $data = ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false, 'idField'=>'id'],
            'events' => ['url'=>"'".BIZUNO_AJAX."&bizRt=inventory/history/historyRows&jID=$jID&skuID=$skuID'"],
            'columns'=> ['id'   => ['attr'=>['hidden'=>true]],
                'action'        => ['order'=>1,'label'=>lang('action'),'attr'=>['hidden'=>$hide_cost?true:false],
                    'events'    => ['formatter'=>"function(value,row,index) { return {$props['name']}Formatter(value,row,index); }"],
                    'actions'   => [
                        'print' => ['order'=>10,'icon'=>'print', 'events' => ['onClick'=>"var jID=jqBiz('#journal_id').val(); winOpen('phreeformOpen', 'phreeform/render/open&group={$frmGrp}&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]],
                        'edit'  => ['order'=>20,'icon'=>'edit',  'label'=>lang('edit'),          'hidden'=>$hide>0?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'toggle'=> ['order'=>40,'icon'=>'toggle','label'=>lang('toggle_status'), 'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"jsonAction('phreebooks/main/toggleWaiting&jID=$jID&dgID={$props['name']}', idTBD);"]],
                        'dates' => ['order'=>50,'icon'=>'date',  'label'=>lang('delivery_dates'),'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"]],
                        'fill'  => ['order'=>80,'icon'=>$icon,   'label'=>$label,                'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=$invID&bizAction=inv');"]]]],
                'invoice_num'   => ['order'=>20,'label'=>lang('invoice_num', $jID),   'attr'=>['resizable'=>true]],
                'primary_name_b'=> ['order'=>30,'label'=>lang('primary_name'),        'attr'=>['sortable'=>true,'resizable'=>true]],
                'purch_order_id'=> ['order'=>40,'label'=>lang('purch_order_id', $jID),'attr'=>['resizable'=>true,'sortable'=>true]],
                'store_id'      => ['order'=>50,'label'=>lang('short_name_b'),        'attr'=>['resizable'=>true,'sortable'=>true,'hidden'=>$stores]],
                'rep_id'        => ['order'=>60,'label'=>lang('rep_id_c'),            'attr'=>['resizable'=>true,'align'=>'center']],
                'post_date'     => ['order'=>70,'label'=>lang('post_date'),           'attr'=>['resizable'=>true,'sortable'=>true,'align'=>'center']],
                'qty'           => ['order'=>80,'label'=>lang('balance'),             'attr'=>['resizable'=>true,'align'=>'center']],
                'date_1'        => ['order'=>90,'label'=>jsLang('date_1',10),         'attr'=>['resizable'=>true,'sortable'=>true,'align'=>'center'],
                    'events'=>['styler'=>"function(value,row,index) { if (row.waiting==1) { return {style:'background-color:yellowgreen'}; } }"]]]];
        if (in_array($jID, [3,9])) { // remove some action items
            unset($data['columns']['action']['actions']['toggle'],$data['columns']['action']['actions']['dates'],$data['columns']['action']['actions']['fill']);
        }
        return $data;
    }

    private function dgJ06J12($jID=6, $skuID=0, $sku='')
    {
        $rows = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post');
        $page = clean('page', ['format'=>'integer','default'=>1], 'post');
        $sort = clean('sort', ['format'=>'text',   'default'=>'post_date'],'post');
        $order= clean('order',['format'=>'text',   'default'=>'desc'],     'post');
        $gID  = explode(":", getDefaultFormID($jID)); // used for printing from popup
        $name = "dgMvmt$jID";
        switch ($jID) {
            case  6: $jPmt = 20; break;
            case 12:
            default: $jPmt = 18; break;
        }
        $data = ['id'=>$name, 'strict'=>true, 'rows'=>$rows, 'page'=>$page, 'title'=>sprintf(lang('tbd_history'), lang('journal_id', $jID)),
            'attr'   => ['idField'=>'id','url'=>BIZUNO_AJAX."&bizRt=inventory/history/movementRows&jID=$jID&skuID=$skuID"],
            'source' => [
                'tables' => [
                    'journal_main'=>['table'=>BIZUNO_DB_PREFIX.'journal_main'],
                    'journal_item'=>['table'=>BIZUNO_DB_PREFIX.'journal_item','join'=>'join','links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_item.ref_id"]],
                'filters' => [
                    'jID'  => ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."journal_main.journal_id=$jID"],
                    'rID'  => ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."journal_item.sku='$sku'"]],
                'sort' => ['s0'=>['order'=>10, 'field'=>("$sort $order")]]],
            'columns' => [
                'id'        => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX.'journal_main.id',        'attr'=>['hidden'=>true]],
                'closed'    => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX.'journal_main.closed',    'attr'=>['hidden'=>true]],
                'journal_id'=> ['order'=>0, 'field'=>BIZUNO_DB_PREFIX.'journal_main.journal_id','attr'=>['hidden'=>true]],
                'bal_due'   => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX.'journal_main.id','process'=>'paymentRcv','attr'=>['hidden'=>true]],
                'action'    => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'       => ['order'=>20,'icon'=>'edit',    'label'=>lang('edit'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'print'      => ['order'=>40,'icon'=>'print',   'label'=>lang('print'),
                            'events' => ['onClick'=>"var idx=jqBiz('#$name').datagrid('getRowIndex', idTBD); var jID=jqBiz('#$name').datagrid('getRows')[idx].journal_id; ('fitColumns', true); winOpen('phreeformOpen', 'phreeform/render/open&group={$gID[0]}:j'+jID+'&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]],
                                    ]],
                'invoice_num'   => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num','label'=>pullTableLabel('journal_main', 'invoice_num', $jID),
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'primary_name_b'=> ['order'=>20, 'field'=>BIZUNO_DB_PREFIX.'journal_main.primary_name_b','label'=>lang('primary_name', '_b'),
                    'attr'  => ['sortable'=>true, 'resizable'=>true]],
                'state_s'       => ['order'=>30, 'field'=>BIZUNO_DB_PREFIX.'journal_main.state_s','label'=>sprintf(lang('tbd_ship'), lang('state', '_s')),
                    'attr'  => ['align'=>'center', 'sortable'=>true, 'resizable'=>true]],
                'purch_order_id'=> ['order'=>40, 'field'=>BIZUNO_DB_PREFIX.'journal_main.purch_order_id','label'=>pullTableLabel('journal_main', 'purch_order_id', $jID),
                    'attr'  => ['sortable'=>true, 'resizable'=>true]],
                'store_id'      => ['order'=>50, 'field'=>BIZUNO_DB_PREFIX.'journal_main.store_id','label'=>pullTableLabel('journal_main', 'store_id', $jID),'format'=>'storeID',
                    'attr'  => ['sortable'=>false, 'resizable'=>true]],
                'post_date'     => ['order'=>60, 'field' => BIZUNO_DB_PREFIX.'journal_main.post_date', 'format'=>'date','label' => pullTableLabel('journal_main', 'post_date', $jID),
                    'attr' => ['align'=>'center', 'sortable'=>true, 'resizable'=>true]],
                'qty'           => ['order'=>70, 'field' => BIZUNO_DB_PREFIX.'journal_item.qty', 'format'=>'integer','label' => lang('quantity'),
                    'attr' => ['align'=>'center', 'sortable'=>true, 'resizable'=>true]],
                ]];
        return $data;
    }

    /**
     * Lists open Work Orders for a give SKU
     * @param integer $skuID - id field from the inventory db
     */
    private function dgWO($skuID=0)
    {
        $rows  = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post');
        $page  = clean('page', ['format'=>'integer','default'=>1],            'post');
        $sort  = clean('sort', ['format'=>'text',   'default'=>'create_date'],'post');
        $order = clean('order',['format'=>'text',   'default'=>'desc'],       'post');
        $hide  = 1; //getUserCache('role', 'security', 'woProd') ? false : true;
        $data  = ['id'=>'dgWO', 'strict'=>true, 'rows'=>$rows, 'page'=>$page, 'title'=>'Open Work Orders',
            'attr'    => ['idField'=>'id','url'=>BIZUNO_AJAX."&bizRt=inventory/history/buildRows&skuID=$skuID"],
            'source'  => [
                'tables' => ['journal_main'=>['table'=>BIZUNO_DB_PREFIX.'journal_main']],
                'filters' => [
                    'closed'=> ['order'=>99, 'hidden'=>true, 'sql'=>"closed='0'"],
                    'rID'   => ['order'=>99, 'hidden'=>true, 'sql'=>"sku_id='$skuID'"]],
                'sort' => ['s0'=>['order'=>10, 'field'=>("$sort $order")]]],
            'columns' => [
                'id'           => ['order'=>1,'attr'=>['hidden'=>true]],
                'action'       => ['order'=>5,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index) { return dgWOFormatter(value,row,index); }"],
                    'actions'=> [
                        'edit'       => ['order'=>20,'icon'=>'edit',    'label'=>lang('edit'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=inventory/build/manager&rID=idTBD');"]],
                        'print'=>['order'=>20,'icon'=>'print','label'=>lang('print'),'hidden'=>$hide>0?false:true,'events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=mfg:wo&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]]]],
                'sku'          => ['order'=>10,'field'=>'sku',          'label'=>lang('sku'),      'attr'=>['align'=>'center','sortable'=>true,'resizable'=>true],
                    'events' => ['styler'=>"function(value,row,index) { if (row.started=='1') { return {style:'background-color:lightgreen'}; } }"]],
                'store_id'     => ['order'=>20,'field'=>'store_id',     'label'=>lang('store_id'), 'attr'=>['align'=>'center','sortable'=>true,'resizable'=>true]],
                'post_date'    => ['order'=>30,'field'=>'post_dte',     'label'=>lang('post_date'),'attr'=>['align'=>'center','sortable'=>true,'resizable'=>true], 'format'=>'date'],
                'terminal_date'=> ['order'=>40,'field'=>'terminal_date','label'=>lang('due_date'), 'attr'=>['align'=>'center','sortable'=>true,'resizable'=>true], 'format'=>'date',],
                'qty'          => ['order'=>50,'field'=>'qty',          'label'=>lang('quantity'), 'attr'=>['align'=>'center','sortable'=>true,'resizable'=>true]],
                'notes'        => ['order'=>60,'field'=>'notes',        'label'=>lang('notes'),    'attr'=>['sortable'=>true,'resizable'=>true]]],
            'footnotes'=> ['status'=>lang('status').': <span style="background-color:lightgreen">'.'Work Order Started'.'</span>']];
        return $data;
    }

    private function dgJ06J12Avg($jID=12)
    {
        if ($jID==6) {
            $props = ['name'=>'dgJ06','title'=>sprintf(lang('tbd_history'), lang('journal_id', '6')), 'data'=>'dataJ6'];
            $label = jsLang('cost');
        } else {
            $props = ['name'=>'dgJ12','title'=>sprintf(lang('tbd_history'), lang('journal_id', '12')),'data'=>'dataJ12'];
            $label = jsLang('sales');
        }
        return ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false],
            'events' => ['data' =>$props['data']],
            'columns'=> [
                'year' => ['order'=>20,'label'=>lang('year'), 'attr'=>['align'=>'center','resizable'=>true]],
                'month'=> ['order'=>30,'label'=>lang('month'),'attr'=>['align'=>'center','resizable'=>true]],
                'qty'  => ['order'=>40,'label'=>lang('qty'),  'attr'=>['align'=>'center','resizable'=>true]],
                'total'=> ['order'=>50,'label'=>$label,       'attr'=>['align'=>'right', 'resizable'=>true],'events'=>['formatter'=>"function(value) { return formatCurrency(value); }"]]]];
    }
}
