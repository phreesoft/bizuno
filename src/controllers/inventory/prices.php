<?php
/*
 * Functions related to inventory pricing for customers and vendors
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
 * @version    7.x Last Update: 2025-05-21
 * @filesource /controllers/inventory/prices.php
 */

namespace bizuno;

class inventoryPrices extends mgrJournal
{
    public    $moduleID  = 'inventory';
    public    $pageID    = 'prices';
    protected $domSuffix = 'Prices';
    protected $metaPrefix= 'price_';
    private   $mgrRowData= [];
    private   $first     = 0; // first price for level calculations
    private   $locked    = false;
    private   $levelIdx  = 0;
    
    function __construct()
    {
        msgDebug("\nConstructing inventoryPrices");
        $this->lang       = getLang($this->moduleID);
        $this->dom        = clean('dom', ['format'=>'cmd', 'default'=>'page'],'get');
        $this->type       = clean('type',['format'=>'char','default'=>'c'],   'get');
        $this->secID      = "prices_{$this->type}";
        $this->qtySource  = ['1'=>lang('direct_entry'), '2'=>lang('item_cost'), '3'=>lang('full_price'), '4'=>lang('price_level_1'), '5'=>lang('fixed_discount')];
        $this->qtyAdj     = ['0'=>lang('none'), '1'=>lang('decrease_by_amount'),'2'=>lang('decrease_by_percent'),'3'=>lang('increase_by_amount'), '4'=>lang('increase_by_percent')];
        $this->qtyRnd     = ['0'=>lang('none'), '1'=>lang('next_integer'),      '2'=>lang('next_fraction'),      '3'=>lang('next_increment')];
        $this->metaPrefix.= $this->type; 
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            '_rID'       => ['panel'=>'general','order'=> 0,                             'clean'=>'integer',  'attr'=>['type'=>'hidden',   'value'=>0]],
            '_table'     => ['panel'=>'general','order'=> 0,                             'clean'=>'db_field', 'attr'=>['type'=>'hidden',   'value'=>'']],
            '_refID'     => ['panel'=>'general','order'=> 0,                             'clean'=>'integer',  'attr'=>['type'=>'hidden',   'value'=>0]],
            'cType'      => ['panel'=>'general','order'=> 0,                             'clean'=>'alpha_num','attr'=>['type'=>'hidden',   'value'=>$this->type]],
            'levels'     => ['panel'=>'general','order'=> 0,                             'clean'=>'json',     'attr'=>['type'=>'hidden',   'value'=>[]]],
            'title'      => ['panel'=>'general','order'=>10,'label'=>lang('title'),      'clean'=>'text',     'attr'=>['type'=>'text',     'value'=>'']],
            'cID'        => ['panel'=>'general','order'=>20,'label'=>lang('short_name'), 'clean'=>'integer',  'attr'=>['type'=>'contact',  'value'=>'']],
            'iID'        => ['panel'=>'general','order'=>30,'label'=>lang('sku'),        'clean'=>'integer',  'attr'=>['type'=>'inventory','value'=>'']],
            'inactive'   => ['panel'=>'general','order'=>40,'label'=>lang('inactive'),   'clean'=>'char',     'attr'=>['type'=>'selNoYes', 'value'=>0]],
            'default'    => ['panel'=>'general','order'=>50,'label'=>lang('default'),    'clean'=>'char',     'attr'=>['type'=>'selNoYes', 'value'=>0],'tip'=>lang('prices_inactive_tip')],
            'postCalc'   => ['panel'=>'general','order'=>60,'label'=>lang('prices_calc'),'clean'=>'char',     'attr'=>['type'=>'selNoYes', 'value'=>0],'tip'=>lang('prices_calc_tip')],
            'currency'   => ['panel'=>'general','order'=>70,'label'=>lang('currency'),   'clean'=>'db_field', 'attr'=>['type'=>'hidden',   'value'=>getDefaultCurrency()]], // for now just a single currency
            'last_update'=> ['panel'=>'general','order'=>80,'label'=>lang('date_last'),  'clean'=>'date',     'attr'=>['type'=>'date',     'value'=>biz_date(), 'readonly'=>true]]];
    }
    protected function managerGrid($security, $args=[])
    {
        msgDebug("\nEntering prices::managerGrid with args = ".print_r($args, true));
        $opts = array_replace(['cID'=>0, 'iID'=>0, 'dom'=>$this->dom], $args);
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'attr'     => ['url'=>BIZUNO_AJAX."&bizRt=inventory/prices/managerRows&type=$this->type&dom={$opts['dom']}&cID={$opts['cID']}&iID={$opts['iID']}"],
            'events'   => ['onDblClickRow' => "function(rowIndex, rowData){ accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&type=$this->type&table='+rowData._table, rowData._rID); }"],
            'source'   => ['search'=>['title', 'cName', 'iName']],
            'footnotes'=> ['codes'=>lang('color_codes').': <span class="row-default">'.lang('default').'</span>'],
            'columns'  => ['action'=>['actions'=>['edit'=>['events'=>[
                    'onClick' => "function(rowIndex, rowData){ accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&type=$this->type&table='+rowData._table, rowData._rID); }"]]]],
                'title'      => ['order'=>10, 'label'=>lang('title'),       'attr'=>['size'=>120,'sortable'=>true,'resizable'=>true]],
                'cName'      => ['order'=>40, 'label'=>lang('primary_name'),'attr'=>['size'=>120,'sortable'=>true,'resizable'=>true]],
                'iName'      => ['order'=>50, 'label'=>lang('SKU'),         'attr'=>['size'=>120,'sortable'=>true,'resizable'=>true]],
                'postCalc'   => ['order'=>50, 'label'=>lang('post_calc'),   'attr'=>['size'=> 60,'sortable'=>true,'resizable'=>true],'format'=>'noYes'],
                'last_update'=> ['order'=>70, 'label'=>lang('date_last'),   'attr'=>['size'=>120,'sortable'=>true,'resizable'=>true],'format'=>'date']]]);
        if (in_array($GLOBALS['myDevice'], ['mobile', 'tablet'])) { $data['columns']['last_update']['attr']['hidden']= true; }
        return $data;
    }
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd','default'=>'cName'],'post');
        $this->defaults['order']= clean('order',['format'=>'cmd','default'=>'ASC'],  'post');
    }
    public function manager(&$layout=[])
    {
        msgDebug("\nEntering inventory:prices:manager");
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID', 'integer', 'get'); // For specific row ID (NEEDS TABLE???)
        $cID = clean('cID', 'integer', 'get'); // For contacts tab
        $iID = clean('iID', 'integer', 'get'); // For inventory tab
        $args= ['dom'=>$this->dom, 'rID'=>$rID, 'cID'=>$cID, 'iID'=>$iID, 'title'=>lang('price_sheet', $this->type)]; // 
        if     (!empty($cID) && 'div'==$this->dom) { $args['refID'] = $cID; $args['table'] = 'contacts'; }
        elseif (!empty($iID) && 'div'==$this->dom) { $args['refID'] = $iID; $args['table'] = 'inventory'; }
        parent::managerMain($layout, $security, $args);
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', ['format'=>'integer','default'=>-1], 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        if ($storeID > -1) {
            $layout['datagrid']['dgPricesMgr']['source']['filters']['contact_id'] = ['order'=>15,'sql'=>'('.BIZUNO_DB_PREFIX."contact_id=$storeID OR ".BIZUNO_DB_PREFIX."contact_id=0)",
                'attr'=>['type='=>'hidden','value'=>$storeID]];
        }
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID',  'integer', 'get');
        $cID = clean('cID',  'integer', 'get');
        $iID = clean('iID',  'integer', 'get');
        $_POST['search'] = getSearch();
        $args= ['rID'=>$rID, 'cID'=>$cID, 'iID'=>$iID, 'dom'=>$this->dom];
        // Can't use parent::mgrRowsMeta, it's more complicated as meta is spread across three tables
        $grid = $this->managerGrid($security, $args);
        if (empty($this->defaults['search'])) { unset($grid['source']['filters']['search']); }
        if ($args['dom']<>'div') {
            $this->mgrRowData = array_merge($this->mgrRowData, dbMetaGet('%',$this->metaPrefix));
        }
        if ($args['dom']=='page' || !empty($cID)) {
            $this->mgrRowData = array_merge($this->mgrRowData, dbMetaGet('%',  $this->metaPrefix, 'contacts', !empty($cID) ? $cID : '%'));
        }
        if (!empty($iID)) { // removed $args['dom']=='page' as this causes an out of memory error
            $this->mgrRowData = array_merge($this->mgrRowData, dbMetaGet('%',  $this->metaPrefix, 'inventory', !empty($iID) ? $iID : '%'));
        }
        msgDebug("\nsizeof output = ".sizeof($this->mgrRowData));
        $layout  = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode($this->managerRowsSort($grid, $this->mgrRowData, $args))]);
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', ['format'=>'integer','default'=>-1], 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        if ($storeID > -1) {
            $layout['datagrid']['dgPricesMgr']['source']['filters']['contact_id'] = ['order'=>15,'sql'=>'('.BIZUNO_DB_PREFIX."contact_id=$storeID OR ".BIZUNO_DB_PREFIX."contact_id=0)",
                'attr'=>['type='=>'hidden','value'=>$storeID]];
        }
    }
    
    private function managerRowsSort($dg, $data=[], $args=[])
    {
        msgDebug("\nEntering managerRowsSort with args = ".print_r($args, true));
        $cIDs = $iIDs = $cNames = $iNames = [];
        if ($this->dom<>'page') { // we're in a tab, remove not applicable rows
            foreach ($data as $key => $row) {
                $hit = false;
                if (!empty($args['cID']) && $row['cID']==$args['cID']) { $hit=true; }
                if (!empty($args['iID']) && $row['iID']==$args['iID']) { $hit=true; }
                if (!$hit) { unset($data[$key]); }
            }
        }
        msgDebug("\nafter adjustment for view, sizeof data = ".sizeof($data));
        foreach ($data as $key => $row) {
            if (!empty($row['cID'])) { $cIDs[$row['cID']] = $row['cID']; }
            if (!empty($row['iID'])) { $iIDs[$row['iID']] = $row['iID']; }
        }
        if (!empty($cIDs)) {
            $rows = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "id IN (".implode(',', $cIDs).")", 'id', ['id', 'primary_name']);
            foreach ($rows as $row) { $cNames[$row['id']] = $row['primary_name']; }
        }
        if (!empty($iIDs)) {
            $rows = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "id IN (".implode(',', $iIDs).")", 'id', ['id', 'description_short']);
            foreach ($rows as $row) { $iNames[$row['id']] = $row['description_short']; }
        }
        foreach ($data as $key => $row) {
            $data[$key]['cName'] = !empty($row['cID']) ? $cNames[$row['cID']] : '';
            $data[$key]['iName'] = !empty($row['iID']) ? $iNames[$row['iID']] : '';
        }
        $output = dbMetaReadSearch($data, $dg, $this->defaults['search']);
        $output1= sortOrder($output, $this->defaults['sort'], strtolower($this->defaults['order'])=='desc'?'desc':'asc'); // sort
        foreach ($output1 as $idx => $row) {
            foreach ($row as $key => $value) {
                if (!empty($dg['columns'][$key]['process'])){ $output1[$idx][$key] = viewProcess($value,              $dg['columns'][$key]['process']); }
                if (!empty($dg['columns'][$key]['format'])) { $output1[$idx][$key] = viewFormat ($output1[$idx][$key],$dg['columns'][$key]['format']); }
            }
        }
        $results = array_slice($output1, ($this->defaults['page']-1)*$this->defaults['rows'], $this->defaults['rows']); // get slice
        msgDebug("\nPost processing, returning row count = ".sizeof($results));
        return ['total'=>sizeof($output), 'rows'=>$results];
    }

    public function edit(&$layout=[])
    {
        $rID  = clean('rID',  'integer', 'get');
        $refID= clean('refID','integer', 'get');
        $table= clean('table',['format'=>'db_field','default'=>'common'], 'get');
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $args = ['_rID'=>$rID, '_table'=>$table];
        parent::editMeta($layout, $security, $args);
        $layout['forms']["frm{$this->domSuffix}"]['attr']['action'] = BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/save&type=$this->type";
        if ('common'<>$table) {
           $layout['fields']['default']['attr']['type'] = 'hidden';
           $layout['fields']['postCalc']['attr']['type']= 'hidden';
        }
        $cID  = $layout['fields']['cID']['attr']['value'];
        $iID  = $layout['fields']['iID']['attr']['value'];
        $cName= !empty($cID) ? dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name',     "id=$cID") : '';
        $iName= !empty($iID) ? dbGetValue(BIZUNO_DB_PREFIX.'inventory','description_short',"id=$iID") : '';
        if (empty($rID) && $table=='common') { 
            $layout['fields']['cID']['attr']['type'] = 'contact';
            $layout['fields']['iID']['attr']['type'] = 'inventory';
        }
        if (!empty($iID)) { $this->calculateMargin($layout['fields']['levels']['attr']['value'], $iID); }
        if ($table=='common') {
            $layout['fields']['title']['attr']['type']  = 'text';
//          $layout['fields']['locked']['attr']['type'] = 'selNoYes';
            $layout['fields']['default']['attr']['type']= 'selNoYes';
        } elseif ($table=='contacts') {
            $layout['fields']['cID']['attr']['type']  = 'hidden';
            if (!empty($refID)) { $layout['fields']['cID']['attr']['value']  = $refID; }
        }
        // Add the preSubmit to the Save action
        if (!empty($rID)) {
            if (empty($cID) && empty($iID)) {
                $layout['divs']['heading']['html'] = '<h1>'.lang('edit')." - ".$layout['fields']['title']['attr']['value']."</h1>";
            } else {
                $layout['divs']['heading']['html'] = '<h1>'.lang('edit')." - $cName ".(!empty($iName)?" [$iName]":'')."</h1>";
            }
        }
        $layout['toolbars']["tb{$this->domSuffix}"]['icons']['save'] = ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"if (preSubmitPrices()) jqBiz('#frm{$this->domSuffix}').submit();"]];
        // add the header data
        $layout['jsHead']['dgLevels'] = "
var dgLevelsData = ".json_encode($layout['fields']['levels']['attr']['value']).";
var qtySource = "   .json_encode(viewKeyDropdown($this->qtySource)).";
var qtyAdj    = "   .json_encode(viewKeyDropdown($this->qtyAdj)).";
var qtyRnd    = "   .json_encode(viewKeyDropdown($this->qtyRnd)).";
function preSubmitPrices() {
    jqBiz('#dgLevels').edatagrid('saveRow');
    var items = jqBiz('#dgLevels').datagrid('getData');
    var serializedItems = JSON.stringify(items);
    jqBiz('#levels').val(serializedItems);
    return true;
}";
        // add the grids
        $layout['datagrid'] = ['dgLevels'=>$this->dgLevels('dgLevels')];
        $layout['divs']['content']['divs']['dgLevels'] = ['order'=>80, 'type'=>'panel', 'key'=>'dgLevels', 'classes'=>['block99']];
        $layout['panels']['dgLevels']  = ['type'=>'datagrid', 'key'=>'dgLevels'];
     }

    public function copy(&$layout=[])
    {
        if (!$security = validateAccess('prices_'.$this->type, 2)) { return; }
        $rID     = clean('rID',  'integer', 'get'); // index
        $table   = clean('table','db_field','get');
        $newTitle= clean('data', 'text',    'get');
        if (empty($rID)) { return msgAdd(lang('err_no_permission')); }
        $metaVal = dbMetaGet($rID, $this->metaPrefix, $table);
        msgDebug("\nRead metaValue = ".print_r($metaVal, true));
        $oldTitle= $metaVal['title'];
        $metaVal['title'] = $newTitle;
        $cID = !empty($metaVal['cID']) ? $metaVal['cID'] : 0;
        $iID = !empty($metaVal['iID']) ? $metaVal['iID'] : 0;
        metaIdxClean($metaVal);
        unset($metaVal['method']);
        $refID = 'contacts'==$table ? $cID : ('inventory'==$table ? $iID : 0);
        $newID = dbMetaSet(0, $this->metaPrefix, $metaVal, $table, $refID);
        msgLog(lang('prices').' '.lang('copy')." - $oldTitle => $newTitle");
        $layout  = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&table=$table', $newID);"]]);
    }

    public function save(&$layout=[])
    {
        $rID  = clean('_rID',  'integer', 'post');
        if (!$security = validateAccess($this->secID, empty($rID)?2:3)) { return; }
        $table= clean('_table','db_field','post');
        $refID= clean('_refID','integer', 'post');
        $cID  = clean('cID',   'integer', 'post');
        $iID  = clean('iID',   'integer', 'post');
        $newTable= !empty($cID) ? 'contacts' : (!empty($iID) ? 'inventory' : 'common');
        if (!empty($rID) && $table<>$newTable) { // edit existing record, table may change
            $meta = dbMetaGet($rID, $this->metaPrefix, $table, $refID); // get the current value
            msgDebug("\nTable change, resetting input variables from table $table to $newTable.");
            dbMetaDelete($rID, $meta['_table']);
            $_POST['_rID']   = 0;
            $_POST['_table'] = $newTable;
            $_POST['_refID'] = !empty($cID) ? $cID : (!empty($iID) ? $iID : 0);
        }
        parent::saveMeta($layout);
    }

    public function saveSellPkgs()
    {
        msgDebug("\nentering saveSellPkgs");
        $iID = clean('rID' , 'integer','get');
        $data= clean('data', 'json',   'get');
        if (empty($iID)) { return ("Invalid SKU ID!"); }
        dbWrite(BIZUNO_DB_PREFIX.'inventory', ['price_byItem'=>json_encode($data)], 'update', "id=$iID");
        $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$iID");
        msgLog(lang('prices').'-'.lang('save')." for SKU: $sku");
        msgAdd("Sell package levels saved for SKU: $sku!", 'success');
    }

    public function delete(&$layout=[])
    {
        if (!$security = validateAccess('prices_'.$this->type, 4)) { return; }
        parent::deleteMeta($layout, ['_table'=>clean('table', 'db_field', 'get')]);
    }

    /**
     * retrieves the price sheet details for a given SKU to create a pop up window
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function details(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID   = clean('rID', 'integer','get');
        $sku   = clean('sku', 'text',   'get');
        $cID   = clean('cID', 'integer','get');
        if     ($rID) { $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'sku', 'item_cost', 'full_price'], "id=$rID"); }
        elseif ($sku) { $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'sku', 'item_cost', 'full_price'], "sku='".addslashes($sku)."'"); }
        else   { return msgAdd("Bad SKU sent!"); }
        $cost  = clean('itemCost', ['format'=>'float','default'=>$inv['item_cost']], 'get');
        $full  = clean('fullPrice',['format'=>'float','default'=>$inv['full_price']],'get');
        $layout['args'] = ['sku'=>$inv['sku'], 'cID'=>$cID, 'cost'=>$cost, 'full'=>$full];
        compose('inventory', 'prices', 'quote', $layout);
        $rows[]= ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($layout['content']['price'], 'currency').'</div><div>'.lang('price')."</div>"];
        $rows[]= ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($full, 'currency').'</div><div>'.lang('full_price')."</div>"];
        if (validateAccess('j6_mgr', 1, false)) {
            $rows[] = ['group'=>lang('general'),'text'=>"<div style='float:right'>".viewFormat($cost, 'currency').'</div><div>'.lang('item_cost')."</div>"];
        }
        if (!empty($layout['content']['levels'])) { foreach ($layout['content']['levels'] as $level) {
            $rows[] = ['group'=>$level['title'],'text'=>"<div style='float:right'>".lang('price').'</div><div>'.lang('qty')."</div>"];
            foreach ($level['sheets'] as $entry) {
                $rows[] = ['group'=>$level['title'],'text'=>"<div style='float:right'>".viewFormat($entry['price'], 'currency').'</div><div>'.(float)$entry['qty']."</div>"];
            }
        } }
        $data = ['type'=>'popup', 'title'=>lang('prices', $this->type), 'attr'=>['id'=>'winPrices','width'=>300,'height'=>700],
            'divs'  => ['winStatus'=>['order'=>50,'options'=>['groupField'=>"'group'",'data'=>"pricesData"],'type'=>'list','key' =>'lstPrices']],
            'lists' => ['lstPrices'=>[]], // handled as JavaScript data
            'jsHead'=> ['init'=>"var pricesData = ".json_encode($rows).";"]];
        $layout = array_merge_recursive($layout, $data);
    }

    /**
     * Retrieves the best price for a given customer/SKU using available price sheets
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function quote(&$layout=[])
    {
        $defaults = ['qty'=>1, 'sku'=>'', 'iID'=>0, 'UPC'=>0, 'cID'=>0, 'cost'=>0, 'full'=>0];
        if (empty($layout['args'])) { // may be passed as GET variables
            $this->type = clean('type', ['format'=>'char', 'default'=>'c'],'get');
            $layout['args'] = [
                'qty' => clean('qty', ['format'=>'integer', 'default'=>1], 'get'),
                'sku' => clean('sku', 'text',   'get'),
                'cID' => clean('cID', 'integer','get'),
                'iID' => clean('rID', 'integer','get')];
        }
        $layout['args'] = array_replace($defaults, $layout['args']); // fills out all of the indexes in args
        msgDebug("\nProcessing inventory/prices/quote with processed args = ".print_r($layout['args'], true));
        $iSec = validateAccess('prices_'.$this->type, 1, false);
        $pSec = $this->type=='v' ? validateAccess('j6_mgr', 1, false) : validateAccess('j12_mgr', 1, false);
        if (!$security = max($iSec, $pSec)) { return msgAdd(lang('err_no_permission')." [".'prices_'.$this->type." OR jX_mgr]"); }
        $args = $this->quoteInitArgs($layout['args']);
        $prices = ['levels'=>[], 'sale_price'=>$args['iSale'], 'price_msrp'=>$args['iList'], 'price_cost'=>$args['iCost'], 'price_landed'=>$args['iLand'], 'gl_account'=>$args['glAcct']];
        $this->pricesLevels($prices, $args);
        $layout = array_replace_recursive($layout, ['args'=>$args, 'content'=>$prices]);
    }

    private function quoteInitArgs($args=[])
    {
        msgDebug("\nEntering prices::quoteInitArgs");
        $filter= '';
        if     (!empty($args['iID'])) { $filter = "id = {$args['iID']}"; }
        elseif (!empty($args['sku'])) { $filter = "sku='".addslashes($args['sku'])."'"; }
        elseif (!empty($args['UPC'])) { $filter = "upc='{$args['UPC']}'"; }
        $inv   = !empty($filter)      ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['id', 'sku', 'item_cost', 'full_price', 'sale_price', 'price_sheet_c', 'price_sheet_v'], $filter) : [];
        $cont  = !empty($args['cID']) ? dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['ctype_v', 'price_sheet', 'gl_account'], "id={$args['cID']}") : ['price_sheet'=>0, 'gl_account'=>''];
        if (!empty($args['cost'])) { $inv['item_cost'] = $args['cost']; }
        if (!empty($args['full'])) { $inv['full_price']= $args['full']; }
        if (empty($cont['gl_account'])) { $cont['gl_account'] = getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales'); }
        if (!empty($cont['ctype_v']))   { $cont['gl_account'] = ''; } // they are also a vendor so use inventory gl acct
        return ['iID'=>$inv['id'],           'qty'    =>abs($args['qty']), // to properly handle negative sales/purchases and still get pricing based on method
            'cSheetc'=>$cont['price_sheet'], 'glAcct' =>$cont['gl_account'],
            'iSheetc'=>$inv['price_sheet_c'],'iSheetv'=>$inv['price_sheet_v'],
            'cID'    =>$args['cID'],         'cType'  =>$this->type,
            'iCost'  =>$inv['item_cost'],    'iLand'  =>$inv['item_cost'], // landed cost
            'iList'  =>$inv['full_price'],   'iSale'  =>$inv['sale_price']];
    }

    /**
     * Determines the price matrix for a given SKU and customer
     * @param array $layout - structure coming in
     * @param string $sku [default: ''] - inventory item SKU
     * @param integer $cID [default: 0] - Contact ID, can be customer or vendor
     * @return array - price matrix for the given customer and SKU, default price sheet or special pricing applied
     */
    public function quoteLevels(&$layout=[], $sku='')
    {
        msgDebug("\nEntering quoteLevels with sku = $sku");
        $layout['args']['sku'] = $sku;
        compose('inventory', 'prices', 'quote', $layout);
        if (!empty($layout['content']['sheets']) && is_array($layout['content']['sheets'])) {
            $sheets   = array_shift($layout['content']['sheets']);
            $skuPrices= $sheets['levels']; // first sheet is the default from the quote method
        } else {
            $skuPrices= [['qty'=>1, 'price'=>$layout['content']['price']]];
        }
        return $skuPrices;
    }

    /**
     * Retrieves the price levels for a given price sheet, sets the new low price if needed
     * @param array $prices - current working array with pricing values
     * @param array $args - contains information to retrieve proper price for a given SKU
     */
    public function pricesLevels(&$prices, $args=[])
    {
        msgDebug ("\nEntering prices::pricesLevels with args = ".print_r($args, true));
        $sheets = $this->getSheets($args);
        msgDebug("\nRead number of price sheets = ".sizeof($sheets));
        $prices['price'] = $this->type=='v' ? $args['iCost'] : $args['iList'];
        foreach ($sheets as $sheet) { $this->priceQuote($prices, $sheet, $args); } // Find the best price
        msgDebug("\nStart processing fixed discounts");
        foreach ($sheets as $sheet) { // Now process fixed discounts if criteria met
            if ($this->locked || empty($sheet['postCalc']) || (!empty($sheet['postCalc']) && $args['cSheetc']<>$sheet['_rID'])) { continue; }
            foreach ($sheet['levels']['rows'] as $idx => $level) {
                if ($this->levelIdx == $idx) { $prices['price'] = $this->calcPrice($level, $args['iCost'], $prices['price']); msgDebug("\nSetting new price to ".$prices['price']); }
            }
        }
        msgDebug("\nReturning from pricesLevels with prices = ".print_r($prices, true));
    }

    /**
     * 
     * @param type $args
     * @return type
     */
    private function getSheets($args=[])
    {
        msgDebug("\nEntering prices::getSheets");
        $sheets = $iSheets = $cSheets = [];
        $mSheets = getMetaCommon($this->metaPrefix.'%'); // get common sheets
        if (!empty($mSheets)) { $sheets = array_merge($sheets, $mSheets); }
        if (!empty($args['iID'])) { // get inventory sheets
            $iSheets = getMetaInventory($args['iID'], $this->metaPrefix.'%'); // Inventory Sheets
            if (!empty($iSheets)) { $sheets = array_merge($sheets, $iSheets); }
        }
        if (!empty($args['cID'])) { // get contact sheets
            $cSheets = getMetaContact($args['cID'], $this->metaPrefix.'%'); // Contact Sheets
            if (!empty($cSheets)) { $sheets = array_merge($sheets, $cSheets); }
        }
        msgDebug(" ... returning with sizeof sheets = ".sizeof($sheets));
        return $sheets;
    }
    
    /**
     * Retrieves a price for a given sheet and adds it to the return string, updates price if criteria is met
     * @param type $prices
     * @param type $sheet
     * @param type $args
     */
    public function priceQuote(&$prices, $sheet, $args=[])
    {
        msgDebug("\nEntering price::priceQuote with sheet title = ".$sheet['title']." and price coming in = ".print_r(!empty($prices['price'])?$prices['price']:0, true));
        msgDebug("\nEntering priceQuote with prices = ".print_r($prices, true));
        // make sure the sheet applies
        if (!empty($sheet['postCalc'])) { msgDebug("\npostCalc set, bailing!"); return; } // it's a post calculation to be determined at the end
        if (!empty($args['iID']) && !empty($sheet['iID']) && $sheet['iID']<>$args['iID']) { msgDebug("\nsku doesn't match, bailing!"); return; } // inventory item specified but doesn't match sheet
        // Cleared filters, let's go
        if (!empty($sheet['iID']) && !empty($sheet['cID'])) { msgDebug("\nSetting Locked!"); $this->locked = true; } // Lock the price as the iID and cID have been provided, prevents fixed discounts being applied
        $choices= [];
        $levels = !empty($sheet['levels']['rows']) ? $sheet['levels']['rows'] : [];
        $this->first = 0;
//      msgDebug("\nReady to process args = ".print_r($args, true));
//      msgDebug("\nReady to process sheet = ".print_r($sheet, true));
        msgDebug("\nReady to process levels = ".print_r($levels, true));
        foreach ($levels as $idx => $level) {
            $price = $this->calcPrice($level, $args['iCost'], $args['iList']);
            if (!empty($price) && $args['qty']>=$level['qty']) {
                $this->levelIdx = $idx;
                if (( $sheet['cType']==$args['cType'] && $sheet['cID']==$args['cID'] && $sheet['iID']==$args['iID']) || // type, CID and iID hit
                    ( empty($sheet['cID'])&& !empty($sheet['iID'])&& $sheet['iID']==$args['iID']) || // cID -> *, iID != 0, iID = 
                    ( empty($args['cID']) &&  empty($args['iID']) && !empty($sheet['default'])) || // global price sheets, make sure the default is checked
                    (!empty($args['cSheetc']) && $args['cSheetc']==$sheet['_rID']) || // Contact has specified sheet and this is it
                    (!empty($args['iSheetc']) && $args['iSheetc']==$sheet['_rID']) || // Inventory (customer) has specified this sheet
                    (!empty($args['iSheetv']) && $args['iSheetv']==$sheet['_rID'])) // Inventory (vendor) has specified this sheet
                        { $prices['price'] = min($prices['price'], $price); }
            }
            $choices[] = ['label'=>!empty($level['label'])?$level['label']:'', 'qty'=>$level['qty'], 'price'=>$price, 'weight'=>!empty($level['weight'])?$level['weight']:0];
            if (empty($idx)) { $this->first = $price; } // save level 1 pricing for later if needed
        }
        $prices['levels'][] = ['title'=>$sheet['title'], 'default'=>$sheet['default'], 'sheets'=>$choices];
    }

    /**
     * Calculates the price from the encoded price sheet grid
     * @param string $level - encoded price discount
     * @param float $cost
     * @param float $full
     * @return float - calculated price
     */
    private function calcPrice($level, $cost=0, $full=0)
    {
        msgdebug("\nEntering calcPrice with cost = $cost and full = $full"); // and level = ".print_r($level, true));
        $price   = !empty($level['price']) ? $level['price'] : $full;
        switch ($level['source']) { // source
            case 0: $price = 0;            break; // Not Used
            case 1:                        break; // Direct Entry
            case 2: $price = $cost;        break; // Last Cost
            case 3: $price = $full;        break; // Retail Price
            case 4: $price = $this->first; break; // Price Level 1
            case 5: $price = $price;       break; // Fixed Discount/Increase
        }
        switch ($level['adjType']) { // adjustment
            case 0:                                                break; // None
            case 1: $price -= $level['adjValue'];                  break; // Decrease by Amount
            case 2: $price -= $price * ($level['adjValue'] / 100); break; // Decrease by Percent
            case 3: $price += $level['adjValue'];                  break; // Increase by Amount
            case 4: $price += $price * ($level['adjValue'] / 100); break; // Increase by Percent
        }
        switch ($level['rndType']) { // round
            case 1: $price = ceil($price); break; // Next Integer (whole dollar)
            case 2: // Constant remainder (cents)
                $remainder = $level['rndValue'];
                if ($remainder < 0) { $remainder = 0; } // don't allow less than zero adjustments
                // convert to fraction if greater than 1 (user left out decimal point)
                if ($remainder >= 1) { $remainder = '.' . $level['rndValue']; }
                $price = floor($price) + $remainder;
                break;
            case 3: // Next Increment (round to next value)
                $remainder = $level['rndValue'];
                if ($remainder <= 0) { $price = ceil($price); } // don't allow less than zero adjustments, assume zero
                else { $price = ceil($price / $remainder) * $remainder; }
                break;
        }
        msgDebug("\nReturning from calcPrice with price = $price");
        return $price;
    }

    /**
     * 
     * @param type $levels
     * @param type $iID
     */
    private function calculateMargin(&$levels, $iID=0)
    {
        $cost = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "id=$iID");
        msgDebug("\ncost = $cost and levels = ".print_r($levels, true));
        foreach ($levels['rows'] as $key => $level) {
            if (!empty($cost)) { $levels['rows'][$key]['margin'] = round(100*((floatval($level['price'])/$cost)-1), 1); }
        }
    }

    /**
     * Grid structure for quantity based pricing
     * @param string $name - DOM field name
     * @return array - grid structure
     */
    protected function dgLevels($name) {
        return ['id'=>$name, 'type'=>'edatagrid',
            'attr'     => ['toolbar'=>"#{$name}Toolbar", 'rownumbers'=>true],
            'events'   => ['data'=> $name.'Data',
                'onLoadSuccess'=> "function(row) { var rows=jqBiz('#$name').edatagrid('getData'); if (rows.total == 0) jqBiz('#$name').edatagrid('addRow'); }",
                'onClickRow'   => "function(rowIndex) { jqBiz('#$name').edatagrid('editRow', rowIndex); }"],
            'source'   => ['actions'=>['new'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'footnotes'=> ['currency'=>lang('msg_default_currency_assumed')],
            'columns'  => [
                'action'  => ['order'=> 1,'label'=>lang('action'), 'attr'=>['width'=>60,],
                    'actions'=> ['trash'=>  ['icon'=>'trash','order'=>20,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]],
                    'events' => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"]],
                'qty'     => ['order'=>10,'label'=>lang('qty'), 'attr'=>['width'=>60,'align'=>'right'],
                    'events'=>  ['editor'=>"{type:'numberbox',options:{formatter:function(value){return formatPrecise(value);}}}"]],
                'source'  => ['order'=>20,'label'=>lang('source'), 'attr'=>['width'=>120,'sortable'=>true, 'resizable'=>true, 'align'=>'center'],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtySource, value); }",
                        'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtySource,value:'1'}}"]],
                'adjType' => ['order'=>30,'label'=>lang('adjustment'),'attr' =>['width'=>120,],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtyAdj, value); }",
                        'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtyAdj}}"]],
                'adjValue'=> ['order'=>40,'label'=>$this->lang['adj_value'], 'attr'=>['width'=>60,'align'=>'center', 'size'=>10],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'rndType' => ['order'=>50,'label'=>lang('rounding'),'attr' =>['width'=>120,],
                    'events' => ['formatter'=>"function(value){ return getTextValue(qtyRnd, value); }",'editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:qtyRnd}}"]],
                'rndValue'=> ['order'=>60,'label'=>$this->lang['rnd_value'], 'attr'=>['width'=>60,'align'=>'center', 'size'=>10],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'price'   => ['order'=>70,'label'=>lang('price'), 'attr'=>['width'=>120,'align'=>'right', 'size'=>10],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'margin'  => ['order'=>80,'label'=>lang('margin'),'attr'=>['width'=>60,'align'=>'right', 'size'=>10]]]];
    }

    /**
     * @TODO - DEPRECATED - Decodes the price sheet settings for quantity based pricing and returns array of values for datagrid display
     * @param string $prices - encoded price value
     * @return array - ready to display in datagrid
     */
    protected function getPrices($prices='')
    {
        msgDebug("\nWorking with price string: $prices");
        $price_levels = explode(';', $prices);
        $arrData = [];
        for ($i=0; $i<sizeof($price_levels); $i++) {
            $level_info = explode(':', $price_levels[$i]);
            $arrData[] = [
                'price'   => isset($level_info[0]) ? $level_info[0] : 0,
                'qty'     => isset($level_info[1]) ? $level_info[1] : ($i+1),
                'source'  => isset($level_info[2]) ? $level_info[2] : '1',
                'adjType' => isset($level_info[3]) ? $level_info[3] : '',
                'adjValue'=> isset($level_info[4]) ? $level_info[4] : 0,
                'rndType' => isset($level_info[5]) ? $level_info[5] : '',
                'rndValue'=> isset($level_info[6]) ? $level_info[6] : 0];
        }
        msgDebug("\nReturning from getPrices with value: ".print_r($arrData, true));
        return ['total'=>sizeof($arrData), 'rows'=>$arrData];
    }
}
