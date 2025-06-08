<?php
/*
 * @name Bizuno ERP - Bizuno Pro Inventory plugin - Tools
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
 * @version    7.x Last Update: 2025-04-02
 * @filesource /bizuno-pro/controllers/proInv/tools.php
 */

namespace bizuno;

class proInvTools
{
    public  $moduleID = 'proInv';
    public  $pageID   = 'tools';
    private $dateStart= '2021-12-31';
    private $states   = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD',
                         'MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC',
                         'SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
    private $statesW  = ['AK','AZ','CA','HI','ID','MT','NM','NV','OR','UT','WA'];
    private $statesC  = ['CO','IL','IN','IA','KS','KY','LA','MI','MN','MO','ND','NE','OH','OK','SD','TN','TX','WI','MY'];
    private $statesE  = ['AL','AR','CT','DE','FL','GA','ME','MD','MA','MS','NH','NJ','NC','NY','PA','RI','SC','VT','VA','WV'];

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
    }

    /**
     * Hook for inventory/main/manager
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        $security = validateAccess('inv_mgr', 1, false);
        if ($security > 2 && isset($layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'])) {
            $layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'] .= " && row.inventory_type!='mi'";
        } elseif($security > 2) {
            $layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'] = "row.inventory_type!='mi'";
        }
        if ($security > 1 && isset($layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'])) {
            $layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'] .= " && row.inventory_type!='mi'";
        } elseif ($security > 1) {
            $layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'] = "row.inventory_type!='mi'";
        }
    }

    /**
     * Extends inventory/main/edit
     * @param array $layout - structure coming in
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $tabID= clean('tabID', 'text', 'get');
        // options
        $layout['toolbars']['tbInventory']['icons']['forecast'] = ['icon'=>'mimePpt', 'order'=>70, 'label'=>$this->lang['forecast'],
            'events'=> ['onClick' => "windowEdit('$this->moduleID/tools/invForecast&rID=$rID', 'forecastChart', '{$this->lang['forecast']}', 600, 550);"]];
        if ($layout['fields']['inventory_type']['attr']['value'] == 'ms') {
            $rID = clean('rID', 'integer', 'get');
            $tabID = clean('tabID', 'integer', 'get');
            // Set the current options (in case the tab doesn't get selected)
            $curOpt = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invOptions', "id=$rID");
            msgDebug("\nRead curOpt = ".print_r($curOpt, true));
            if (!$curOpt) { $curOpt = json_encode([]); }
            $html = html5('invOptions', ['attr'=>  ['type'=>'hidden', 'value'=>$curOpt]]);
            $layout['tabs']['tabInventory']['divs']['tabOptions'] = ['order'=>20, 'label'=>lang('options'),'type'=>'html', 'html'=>$html,
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/options/optionsEdit&rID=$rID'"]];
            if ($tabID) { $layout['tabs']['tabInventory']['selected'] = $tabID; }
        }
        $defaults = [
            'sales' => getChartDefault(30),
            'stock' => getChartDefault(4),
            'cogs'  => getChartDefault(32),
            'method'=> 'f'];
        $layout['fields']['lang']['ms_skus_created'] = $this->lang['skus_created'];
        $layout['fields']['inventory_type']['values']['ms'] = ['id'=>'ms','text'=>lang('inventory_type_ms'),'hidden'=>0,'tracked'=>1,'order'=>20,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']]; // Master Stock
        $layout['jsHead']['ms'] = "invTypeMsg['ms'] = '".addslashes($this->lang['msg_sel_ms'])."'";
        // images
        $layout['tabs']['tabInventory']['divs']['invImages'] = ['type'=>'html','order'=>55,'label'=>lang('images'),'html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/images/imagesLoad&rID=$rID'"]];
        // accessory
        $layout['tabs']['tabInventory']['divs']['invAccy'] = ['order'=>90, 'label'=>lang('accessories'),'type'=>'html','html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/accessory/accessoryEdit&rID=$rID'"]];
        // attributes
        $layout['tabs']['tabInventory']['divs']['invAttr'] = ['type'=>'html','order'=>65,'label'=>lang('attributes'),'html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/attributes/attrLoad&rID=$rID'"]];
        // Manufacturing
        if (in_array($layout['fields']['inventory_type']['attr']['value'], ['ma', 'sa'])) {
            $layout['tabs']['tabInventory']['divs']['invWO'] = ['type'=>'html','order'=>52,'label'=>lang('work_orders'),'html'=>'',
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/build/manager&dom=div&refID=$rID'"]];
        }
        if ($tabID) { $layout['tabs']['tabInventory']['selected'] = $tabID; }
    }

    /**
     * Extends inventory/main/save
     * @return type
     */
    public function save()
    {
        if (!$security = validateAccess('inv_mgr', 2)) { return; }
        $rID = clean('id', 'integer', 'post');
        if (!$rID) { return; }
        // Save inventory Images tab
        // if tab is not viewed, the images are not loaded so check to see if there is at least one image before saving
        // this means that there will always need to be at least one image to save the data
        if (isset($_POST['invImg_0'])) { // tab has been opened, process the data
            $output = [];
            $maxCnt = 100; // set max images per item to 100
            for ($i=0; $i<$maxCnt; $i++) {
                $path = clean('invImg_'.$i, 'text', 'post');
                if (!empty($path) && file_exists(BIZUNO_DATA."images/$path")) {
                    msgDebug("\nSaving path = $path");
                    $output[] = $path; }
            }
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['invImages'=>json_encode($output)], 'update', "id=$rID");
        }

        // save attributes
        $attrCat = clean('invAttrCat', 'alpha_num', 'post');
        if (!empty($attrCat)) {
            $invAttr = ['category'=>$attrCat, 'attrs'=>[]];
            foreach($_POST as $key => $value) {
                if (substr($key, 0, 7)!=='invAttr' || $key=='invAttrCat') { continue; }
                $invAttr['attrs'][$key] = $value;
            }
            ksort($invAttr['attrs']);
            msgDebug("\nReady to write attribute data = ".print_r($invAttr, true));
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['bizProAttr'=>json_encode($invAttr)], 'update', "id=$rID");
        }
        // save options
        $options = clean('invOptions', 'json', 'post');
        msgDebug("\nReached invOptions Save, rID = $rID");
        if (!$options) { return; } // no options have been added or edited as this is only there if the tab was accessed
        $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        if ($inv['inventory_type'] <> 'ms') { return; }
        msgDebug("\nSave, invOptions: ".print_r($options, true));
        if (!$options || sizeof($options) < 1) { return; } // no options have been set
        msgDebug("\nWorking with options = ".print_r($options, true));
        $skuData = [[
            'ms_sku'   => $inv['sku'].'-',
            'ms_title' => $inv['description_short'].'-',
            'ms_dSale' => $inv['description_sales'],
            'ms_dPurch'=> $inv['description_purchase']]];
        for ($i = 0; $i < sizeof($options); $i++) {
            unset($options[$i]['isNewRecord']);
            $attrs = explode(';', $options[$i]['attrs']);
            $labels= explode(';', $options[$i]['labels']);
            $tmpData = $skuData;
            $skuData = [];
            for ($j = 0; $j < sizeof($tmpData); $j++) {
                for ($k = 0; $k < sizeof($attrs); $k++) {
                    $t = (sizeof($tmpData) * $k) + $j;
                    if (!isset($skuData[$t])) { $skuData[$t] = []; }
                    $skuData[$t] = [
                        'ms_sku'   => $tmpData[$j]['ms_sku']   .$attrs[$k],
                        'ms_title' => $tmpData[$j]['ms_title'] .$attrs[$k],
                        'ms_dSale' => $tmpData[$j]['ms_dSale'] .' -'.$labels[$k],
                        'ms_dPurch'=> $tmpData[$j]['ms_dPurch'].' -'.$labels[$k]];
                }
            }
        }
        // save production if it's an asembly 
        msgAdd("Hook for saving work order from Inventory screen, Code doesn't exist!");
        // Check length of new SKU to make sure it will fit
        $struc = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $maxSkuLen = $struc['sku']['attr']['maxlength'];
        dbTransactionStart();
        foreach ($skuData as $row) {
            if (strlen($row['ms_sku']) > $maxSkuLen) {
                msgAdd(sprintf($this->lang['err_sku_too_long'], $row['ms_sku']));
                dbTransactionRollback();
                return;
            }
            $tmp = $inv; // copy the inventory record
            unset($tmp['id']);
            $tmp['sku']                 = $row['ms_sku'];
            $tmp['inventory_type']      = 'mi';
            $tmp['description_short']   = $row['ms_title'];
            $tmp['description_sales']   = $row['ms_dSale'];
            $tmp['description_purchase']= $row['ms_dPurch'];
            $tmp['last_update']         = biz_date('Y-m-d');
            unset($tmp['qty_stock'], $tmp['qty_po'], $tmp['qty_so'], $tmp['qty_alloc'], $tmp['upc_code'], $tmp['creation_date'], $tmp['last_journal_date']);
            $newID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='{$tmp['sku']}'");
            if (!$newID) { $tmp['creation_date'] = biz_date('Y-m-d'); }
            msgDebug("\nReady to write: ".print_r($tmp, true));
            dbWrite(BIZUNO_DB_PREFIX.'inventory', $tmp, $newID?'update':'insert', "id=$newID");
        }
        dbTransactionCommit();
    }

    /**
     * Hook for inventory/main/copy
     * @return type
     */
    public function copy()
    {
        $rID   = clean('rID', 'integer', 'get');
        if ('ms' <> dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "id=$rID")) { return; }
        $newSKU= clean('data', 'text', 'get');
        if (!$newID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='$newSKU'")) { return; } // must have been an error
        $this->save();
        // copy the work order job
        msgAdd("Hook for copying work order from Inventory screen, Code doesn't exist!");
    }

    /**
     * Hook for inventory/main/rename
     * @param type $layout
     * @return type
     */
    public function rename(&$layout=[])
    {
        $rID    = clean('rID', 'integer', 'get');
        $oldInv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        if ('ms' <> $oldInv['inventory_type']) { return; }
        $oldSKU = $oldInv['sku'];
        $newSKU = clean('data', 'text', 'get');
        // rename just children as master was renamed in inventory module
        if (isset($layout['dbAction'])) { // then standard rename must have been ok
            $layout['dbAction']['invOpt_inventory'] = "UPDATE ".BIZUNO_DB_PREFIX."inventory           SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_history']   = "UPDATE ".BIZUNO_DB_PREFIX."inventory_history   SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_cogs_owed'] = "UPDATE ".BIZUNO_DB_PREFIX."journal_cogs_owed   SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_Jrnl_item'] = "UPDATE ".BIZUNO_DB_PREFIX."journal_item        SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%' AND gl_type='itm'";
        }
    }

    /**
     * Hook for inventory/main/delete
     * @param type $layout
     * @return type
     */
    public function delete(&$layout=[])
    {
        
        // @TODO - This method needs to go before inventory:delete as the only thing it does is check for cancellation of the delete
        
        
        
        $cancelDelete = false;
        $rID = clean('rID', 'integer', 'get');
        $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        $sku = $inv['sku'];
        if ('ms' <> $inv['inventory_type']) { return; }
        if (!isset($layout['dbAction']['inventory'])) { return; } // the item is not being deleted, probably an error
        // make sure SKU is not part of an assembly
        // @TODO - if it is and the other SKU's are not in journal then let delete AND remove sku from all assembly BOMs

//      if (dbGetValue(BIZUNO_DB_PREFIX."inventory_assy_list", 'id', "sku LIKE '$sku-%'")) { $cancelDelete = msgAdd($this->lang['err_inv_delete_assy']); }
        if (dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'id', "sku LIKE '$sku-%'"))        { $cancelDelete = msgAdd($this->lang['err_inv_delete_gl_entry']); }
        if ($cancelDelete) { unset($layout['dbAction']); }
        else { // get all ID's for the children
            if (!$mID = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "sku LIKE '$sku-%'", '', ['id'])) { return; }
            $range = [];
            foreach ($mID as $row) { $range[] = $row['id']; }
            $range = '('.implode(',', $range).')';
            $layout['dbAction']['invOpt_inventory'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory      WHERE id IN $range";
            $layout['dbAction']['invOpt_assy_list'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id IN $range";
            foreach ($mID as $row) { // remove attachments
                $files = glob(getModuleCache('inventory', 'properties', 'attachPath')."rID_{$row['id']}_*.*");
                if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
            }
        }
        // delete WO, this should be done when the SKU is deleted as it's tied to the inventory meta
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
        $types = explode(',', COG_ITEM_TYPES);
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", $types)."')", 'sku', ['id']);
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
     *
     * @param type $rID
     * @param type $type
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

    /**
     *
     * @global class $io
     */
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
}
