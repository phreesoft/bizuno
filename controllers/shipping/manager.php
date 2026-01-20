<?php
/*
 * Shipping Extension - Manager methods
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
 * @version    7.x Last Update: 2026-01-20
 * @filesource /controllers/shipping/manager.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/functions.php', 'shippingView', 'function');

class shippingManager extends mgrJournal
{
    public    $moduleID  = 'shipping';
    public    $pageID    = 'manager';
    protected $domSuffix = 'Shipping';
    protected $secID     = 'shipping';
    protected $metaPrefix= 'shipment';
    protected $nextRefIdx= 'next_shipment_num';
    protected $journalID = 12;
    public    $struc;
    
    /**
     * Mod to add shipping icon to journal manager
     * @param array $data
     * @return $data updated
     */
    function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure - log in this case
     * @return array - page structure
     */
    public function fieldStructure()
    {
        $stores  = getModuleCache('bizuno', 'stores');
        $choices = [['id'=>'', 'text'=>lang('select')]];
        $carriers= getMetaMethod('carriers');
        foreach ($carriers as $carrier) {
            if (empty($carrier['status']) || !isset($carrier['settings']['services'])) { continue; }
            $choices = array_merge_recursive($choices, $carrier['settings']['services']);
        }
        $this->struc = [
            '_rID'        => ['panel'=>'general',   'order'=> 0,                                'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]],
            '_table'      => ['panel'=>'general',   'order'=> 0,                                'clean'=>'db_field','attr'=>['type'=>'hidden',  'value'=>'']],
            '_refID'      => ['panel'=>'general',   'order'=> 0,                                'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]],
            'packages'    => ['panel'=>'general',   'order'=> 1,                                'clean'=>'json',    'attr'=>['type'=>'hidden',  'value'=>'']],
            'ref_num'     => ['panel'=>'general',   'order'=>10,                                'clean'=>'cmd',     'attr'=>['type'=>'hidden',  'value'=>'']],
            'invoice_num' => ['panel'=>'general',   'order'=>15,'label'=>lang('invoice_num_12'),'clean'=>'cmd',     'attr'=>['value'=>'']],
            'store_id'    => ['panel'=>'general',   'order'=>20,'label'=>lang('store_id'),      'clean'=>'integer', 'attr'=>['type'=>sizeof($stores)>1?'select':'hidden','value'=>-1], 'values'=>viewStores()],
            'method_code' => ['panel'=>'general',   'order'=>25,'label'=>lang('method'),        'clean'=>'cmd',     'attr'=>['type'=>'select',  'value'=>''], 'values'=>$choices, 'options'=>['width'=>350], 'format'=>'shipInfo'],
            'ship_date'   => ['panel'=>'general',   'order'=>30,'label'=>lang('ship_date'),     'clean'=>'datetime','attr'=>['type'=>'datetime','value'=>biz_date('Y-m-d H:i:s')]],
            'deliver_date'=> ['panel'=>'general',   'order'=>35,'label'=>lang('date_deliver'),  'clean'=>'datetime','attr'=>['type'=>'datetime','value'=>'']],
            'total_cost'  => ['panel'=>'general',   'order'=>40,'label'=>lang('cost'),          'clean'=>'float',   'attr'=>['type'=>'currency','value'=>0]],
            'actual_date' => ['panel'=>'accounting','order'=>10,'label'=>lang('date_actual'),   'clean'=>'datetime','attr'=>['type'=>'datetime','value'=>'']],
            'deliver_late'=> ['panel'=>'accounting','order'=>20,'label'=>lang('deliver_late'),  'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0]],
            'total_billed'=> ['panel'=>'accounting','order'=>30,'label'=>lang('billed'),        'clean'=>'float',   'attr'=>['type'=>'currency','value'=>0]],
            'reconciled'  => ['panel'=>'accounting','order'=>40,'label'=>lang('reconciled'),    'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0]],
            'notes'       => ['panel'=>'notes',     'order'=>10,                                'clean'=>'text',    'attr'=>['type'=>'editor',  'value'=>'']]];
    }

    /**
     * Builds address list grid structure
     * @param integer $security - working security level
     * @param char $type - contact type
     * @param char $aType - address type
     * @return array - grid structure, ready to render
     */
    protected function managerGrid($security=0, $args=[])
    {
        $refID = isset($args['refID']) ? $args['refID'] : 0;
        $stores= getModuleCache('bizuno', 'stores');
        $data  = array_replace_recursive(parent::gridBase($security, $args), ['metaTable'=>'journal',
            'source'  => [
                'tables' => ['journal_meta'=>['table'=>BIZUNO_DB_PREFIX.'journal_meta','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_meta.ref_id"]],
                'search' => ['invoice_num', 'post_date', 'description', 'meta_value'],
                'filters'=> [
                    'metaKey' => ['order'=> 1,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.meta_key LIKE '{$this->metaPrefix}%'", 'hidden'=>true], // m.meta_key LIKE 'shipment_%'
                    'store_id'=> ['order'=>15,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'values'=>viewStores(),'attr'=>['type'=>sizeof($stores)>1?'select':'hidden','value'=>$this->defaults['store_id']]]]],
            'columns' => [
                'id'          => ['order'=>0, 'field'=>'DISTINCT '.BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]],
                'action'      => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return dg{$this->domSuffix}Formatter(value,row,index); }"],
                    'actions' => [
                        'print'    => ['order'=>10,'icon'=>'print','label'=>lang('print'),                  'events'=>['onClick'=>"winOpen('shippingLabel', '$this->moduleID/ship/labelView&refID=$refID&rID=idTBD');"]],
                        'reconcile'=> ['order'=>70,'icon'=>'apply','label'=>$this->lang['toggle_reconcile'],'events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/toggleReconcile&refID=$refID', idTBD);"]]]],
                'ref_num'     => ['order'=>10,'field'=>BIZUNO_DB_PREFIX.'journal_meta.id','label'=>lang('shipment_id'),   'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true],'process'=>'meta:ref_num:journal'],
                'invoice_num' => ['order'=>15,'field'=>'invoice_num',    'label'=>lang('invoice_num_12'),'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]],
                'store_id'    => ['order'=>20,'field'=>'store_id',       'label'=>lang('store_id'),      'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true],'format'=>'storeID'],
                'primary_name_b'=>['order'=>30,'field'=>'primary_name_b','label'=>lang('primary_name'),  'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true]],
                'method_code' => ['order'=>35,'field'=>BIZUNO_DB_PREFIX.'journal_meta.id','label'=>lang('method_code_12'),'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true],'process'=>'meta:method_code:journal', 'format'=>'shipInfo'],
                'ship_date'   => ['order'=>40,'field'=>BIZUNO_DB_PREFIX.'journal_meta.id','label'=>lang('ship_date'),     'attr'=>['width'=>110,'sortable'=>true,'resizable'=>true],'process'=>'meta:ship_date:journal',   'format'=>'datetime'],
                'deliver_date'=> ['order'=>45,'field'=>BIZUNO_DB_PREFIX.'journal_meta.id','label'=>lang('date_deliver'),  'attr'=>['width'=>110,'sortable'=>true,'resizable'=>true],'process'=>'meta:deliver_date:journal','format'=>'datetime'],
                'reconciled'  => ['order'=>55,'field'=>BIZUNO_DB_PREFIX.'journal_meta.id','label'=>lang('reconciled'),    'attr'=>['width'=> 75,'sortable'=>true,'resizable'=>true, 'align'=>'center'],'process'=>'shipRecon']]]);
        if (!empty(getUserCache('profile', 'restrict_store'))) {
            $data['source']['filters']['store'] = ['order'=>0,'hidden'=>true,'sql'=>'store_id='.getUserCache('profile', 'store_id', false, 0)];
        }
        return $data;
    }

    /**
     * Sets the registry cache with address book user settings
     * @param char $type - contact type
     */
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['store_id']= clean('store_id',['format'=>'integer','default'=>-1], 'post');
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',    'default'=>'l'],'post');
    }

    /**
     * Main entry point for the shipping extension
     * @param type $layout
     * @return type
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['type'=>'journal', 'title'=>sprintf(lang('tbd_manager'), lang('shipping'))]);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']);
        $jsBody = "function shippingLabelMain(rID) {
    bizSelSet('selInvoice', '');
    jqBiz('#selInvoice').combogrid('hidePanel');
    accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'divLabel', '".jsLang($this->lang['label_generator'])."', '$this->moduleID/ship/labelMain', rID);
}
jqBiz('#selInvoice').combogrid({width:150,panelWidth:750,delay:500,idField:'id',textField:'invoice_num',mode:'remote',
  url: bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/managerRowsOrder',
  onLoadSuccess: function (data) {
      jqBiz.parser.parse(jqBiz(this).datagrid('getPanel'));
      var g=jqBiz('#selInvoice').combogrid('grid');
      var r=g.datagrid('getData');
      if (r.rows.length==1 && jqBiz('#selInvoice').combogrid('getText')) { shippingLabelMain(r.rows[0].id); }
  },
  onClickRow: function (id, data) { shippingLabelMain(data.id); },
  columns:[[
    {field:'id', hidden:true},
    {field:'invoice',title:'".jsLang('invoice_num_12')."', width: 80},
    {field:'bill_to',title:'".jsLang('primary_name_b')."', width:150},
    {field:'ship_to',title:'".jsLang('primary_name_s')."', width:150},
    {field:'date',   title:'".jsLang('post_date_12')  ."', width:100},
    {field:'method', title:'".jsLang('method_code')   ."', width:200}]]
});\n";
        $data = [
            'divs'     => ["div{$this->domSuffix}"=>['type'=>'tabs','order'=>30,'key'=>"tab{$this->domSuffix}"]],
            'tabs'     => ["tab{$this->domSuffix}"=>['options'=>['tabHeight'=>40], 'divs'=>[
                'tabShipMgr'=> ['order'=> 1,'label'=>lang('manager'),'type'=>'panel','classes'=>['block99'],'key'=>'manager']]]],
            'accordion'=> ["acc{$this->domSuffix}"=>['label'=>lang('manager'),'divs'=>[
                'shipment'=> ['order'=>20,'label'=>lang('ship'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genShip' => ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'genShip']]],
                'divLabel'=> ['order'=>30,'label'=>$this->lang['label_generator'],'type'=>'html','html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/ship/labelMain'"]]]]],
            'panels'   => [
                'manager' => ['type' =>'accordion','key'=>"acc{$this->domSuffix}"],
                'genShip' => ['label'=>$this->lang['ship_invoice'],'type'=>'fields','keys'=>['shipInst','selInvoice']]],
            'fields'   => [
                'shipInst'  => ['order'=>10,'html'=>$this->lang['ship_invoice_desc'],'attr'=>['type'=>'raw']],
                'selInvoice'=> ['order'=>20,'attr'=>['value'=>'']]],
            'jsBody'   => ['init' => $jsBody],
            'jsReady'  => ['focus'=> "bizFocus('selInvoice');"]]; // needs to be here to collect tab js for each method
        $order = 70;
        $carriers = getMetaMethod('carriers');
        msgDebug("\ncarrier array = ".print_r($carriers, true));
        foreach ($carriers as $carrier => $settings) { 
            if (empty($settings['status'])) { continue; }
            $fqcn = "\\bizuno\\$carrier";
            bizAutoLoad($settings['path']."$carrier.php", $fqcn);
            $est  = new $fqcn();
            $title= isset($est->lang['tabTitle']) ? $est->lang['tabTitle'] : (isset($est->lang['acronym']) ? $est->lang['acronym'] : $est->lang['title']);
            if (method_exists($est, 'manager')) {
                $data['tabs']["tab{$this->domSuffix}"]['divs'][$carrier] = ['order'=>$est->settings['order'],'label'=>$title, 'type'=>'html', 'html'=>'',
                    'options' => ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/getCarrier&sID=$carrier'"]];
            } else {
                $data['tabs']["tab{$this->domSuffix}"]['divs'][$carrier] = ['order'=>$order,'label'=>$title,'type'=>'html','html'=>lang('msg_no_settings')];
            }
            $order++;
        }
        $layout = array_replace_recursive($layout, $data);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $grid= $this->managerGrid($security, ['type'=>'journal']);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function managerRowsOrder(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $search = getSearch();
        $crit = "waiting='1' AND journal_id IN (7,12,15)";
        if (!empty($search)) { $crit .= " AND invoice_num LIKE '%$search%'"; }
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $crit, 'invoice_num');
        $output= [];
        foreach ($rows as $row) {
            $output[] = [
                'id'     => $row['id'],
                'invoice'=> $row['invoice_num'],
                'bill_to'=> $row['primary_name_b'],
                'ship_to'=> $row['primary_name_s'],
                'date'   => viewFormat($row['post_date'], 'date'),
                'method' => viewFormat($row['method_code'],'shipInfo')];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode(['total'=>sizeof($output),'rows'=>array_values($output)])]);
    }
    /**
     * Adds a shipping record with partial information from the journal entry
     * @param type $refID - journal_main record ID 
     */
    public function addRecord($refID=0)
    {
        if (empty($refID)) { return; }
        $main = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$refID");
        $meta = [
            '_table'      => 'journal',
            '_refID'      => $refID,
            'ref_num'     => getNextReference($this->nextRefIdx),
            'invoice_num' => $main['invoice_num'],
            'store_id'    => $main['store_id'],
            'method_code' => $main['method_code'],
            'ship_date'   => biz_date(),
            'total_billed'=> $main['freight']];
        return dbMetaSet(0, $this->metaPrefix, $meta, 'journal', $refID);
    }
    public function edit(&$layout=[])
    {
        $refID= clean('rID', 'integer', 'get'); // this is the journal_main id field
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $meta = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        if (!empty($refID) && empty($meta)) { // We're here because an order was shipped but the method had no label method. Create and pre-load the record.
            $rID  = $_GET['rID'] = $this->addRecord($refID);
            $meta = dbMetaGet($rID, $this->metaPrefix, 'journal', $refID);
        }
        $args = ['_rID'=>!empty($meta['_rID'])?$meta['_rID']:0, '_refID'=>$refID, '_table'=>'journal', 'tabID'=>2];
        parent::editMeta($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
        if (!empty($meta['_rID'])) { // customize JavaScript
            $main = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$refID");
            $layout['divs']['heading']['html']= '<h1>'.lang('shipment').": {$layout['fields']['ref_num']['attr']['value']} - {$main['primary_name_b']}</h1>";
            $layout['fields']['invoice_num']['attr']['value']   = $main['invoice_num'];
            $layout['fields']['invoice_num']['attr']['readonly']= true;
            // @TODO - patch for now until migrate can be fixed
            if (!is_array($this->struc['packages']['attr']['value'])) { $this->struc['packages']['attr']['value'] = json_decode($this->struc['packages']['attr']['value'], true); }
            $layout['jsHead']['dgPackageData']= "var dgPackageData = ".json_encode($this->struc['packages']['attr']['value']).";";
        } else  {
            $layout['jsReady']['jsAddDg']     = "bizGridAddRow('dgPackage');";
            $layout['jsHead']['dgPackageData']= "var dgPackageData = [];";
        }
        $layout['jsHead'][$this->pageID] = "function preSubmit() { bizGridSerializer('dgPackage', 'packages'); return true; }";
        $layout['jsReady'][$this->pageID]= "if (dgPackageData.rows.length == 0) { bizGridAddRow('dgPackage'); }";
        // add the grid
        $layout['datagrid'] = ['dgPackage'=>$this->dgPackage('dgPackage', 'receive')];
        $layout['divs']['content']['divs']['dgPackage'] = ['order'=>80, 'type'=>'panel', 'key'=>'dgPackage','classes'=>['block66']];
        $layout['panels']['dgPackage'] = ['type'=>'datagrid', 'key'=>'dgPackage'];
    }
    public function save(&$layout=[])
    {
        msgDebug("\nEntering addressSave.");
        $rID   = clean('_rID',  'integer', 'post');
        $refID = clean('_refID','integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        $invNum= clean('invoice_num', 'text', 'post');
        if (empty($refID) && !empty($invNum)) { // if $refID is blank and post invoice_num is not then we need to find the record to add
            $mainID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "invoice_num LIKE '$invNum%' AND journal_id=12");
            $refID  = !empty($mainID) ? $mainID : 0;
        }
        if (empty($refID)) { return msgAdd('The journal record referenced could not be found in the Sale journal! it will not be saved.'); }
        $args = ['_rID'=>$rID, '_table'=>'journal', '_refID'=>$refID, 'tabID'=>2];
        parent::saveMeta($layout, $args);
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['waiting'=>'0'], 'update', "id=$refID");
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $refID = clean('rID', 'integer', 'get');
        if (empty($refID)) { return msgAdd(lang('illegal_access')); }
        $meta  = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        msgDebug("\nFetched package meta for refID = $refID => ".print_r($meta, true));
        if (empty($meta)) { return msgAdd("Label meta could not be found!"); }
        $rID = metaIdxClean($meta);
        if (!$this->deleteLabels($meta)) { return; }
        parent::deleteMeta($layout, $args = ['_rID'=>$rID, '_table'=>'journal', 'tabID'=>2]);
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['waiting'=>'1'], 'update', "id=$refID");
    }

    private function deleteLabels($meta)
    {
        global $io;
        msgDebug("\nEntering deleteLabels with ship_date = {$meta['ship_date']} and biz_date = ".biz_date());
        if (substr($meta['ship_date'], 0, 10) < biz_date()) { return msgAdd(sprintf($this->lang['error_cannot_delete'], viewFormat($meta['ship_date'], 'date')), 'caution'); }
        $carrier= explode(':', $meta['method_code']);
        $date   = explode('-', substr($meta['ship_date'], 0, 10));
        $path   = "data/shipping/labels/{$carrier[0]}/{$date[0]}/{$date[1]}/{$date[2]}";
        foreach ($meta['packages']['rows'] as $pkg) {
            if (empty($pkg['tracking_id'])) { continue; }
            $success = $carrier[0] ? retrieve_carrier_function($carrier[0], 'labelDelete', $pkg['tracking_id'], $carrier[1], $pkg['store_id']) : true; // true if successful
            if (!$success) { return msgAdd("There was an error deleting the label from {$carrier[0]}."); }
            $io->fileDelete("$path/{$pkg['tracking_id']}*");
        }
        return true;
    }

    /**
     * 
     * @param type $name
     * @return array
     */
    private function dgPackage($name)
    {
        return ['id'=>$name,'type'=>'edatagrid','title'=>lang('packages'),
            'attr'  => ['toolbar'=>"{$name}Toolbar",'idField'=>'id','singleSelect'=>true],
            'events'=> ['data'   => "{$name}Data",
                'onClickRow'=> "function(rowIndex) { curIndex = rowIndex; }",
                'onDestroy' => "function(rowIndex) { curIndex = -1; }",
                'onAdd'     => "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'id'    => ['order'=> 0,'attr' =>['hidden'=>'true']],
                'action'=> ['order'=> 1,'label'=>lang('action'),'attr'=>['width'=>60],
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'tracking_id' => ['order'=>20,'label'=>lang('tracking_num'),'attr'=>['width'=>120,'editor'=>'text',   'resizable'=>true]],
                'deliver_date'=> ['order'=>30,'label'=>lang('date_deliver'),'attr'=>['width'=>110,'editor'=>'datebox','resizable'=>true], 'format'=>'date'],
                'actual_date' => ['order'=>40,'label'=>lang('date_actual'), 'attr'=>['width'=>110,'editor'=>'datebox','resizable'=>true], 'format'=>'date'],
                'cost'        => ['order'=>50,'label'=>lang('cost'),        'attr'=>['width'=> 60,'editor'=>'text',   'resizable'=>true]]]];
    }

    /**
     * Pop up of shipment log details, selected from Sales Manager actions
     * @param type $layout
     * @return type
     */
    public function shippingLog(&$layout=[])
    {
        if (!$security = validateAccess('j12_mgr', 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('The record was not found, the proper id was not passed!'); }
        $rows = dbMetaGet('%', $this->metaPrefix, 'journal', $rID);
        msgDebug("\nRead meta = ".print_r($rows, true));
        if (empty($rows)) { return msgAdd(lang('no_results'), 'caution'); }
        $html = '';
        foreach ($rows as $meta) {
            if (!empty($html)) { $html .= '<hr />'; }
            $cost  = 0;
            $html .= lang('method_code_12').': '.viewProcess($meta['method_code'],'shipInfo')."<br />";
            $html .= lang('terminal_date') .': '.viewFormat($meta['ship_date'],   'date')    .'<br />';
            $html .= lang('date_deliver')  .': '.viewFormat($meta['deliver_date'],'date')    .'<br /><br />';
            $temp  = explode(':', $meta['method_code']);
            $carrier = $temp[0];
            $tracking_url = false;
            if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
                bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $carrier);
                if (defined(strtoupper($carrier)."_TRACKING_URL")) { $tracking_url = constant(strtoupper($carrier)."_TRACKING_URL"); }
            }
            // build the table
            $html .= '<table style="border-collapse:collapse;width:100%">';
            $html .= ' <thead><tr class="panel-header"><td>'.lang('tracking_num')."</td><td>".lang('date_actual')."</td></tr></thead>";
            $html .= ' <tbody>';
            foreach ($meta['packages']['rows'] as $pkg) {
                if ($tracking_url) {
                    $href = str_replace("TRACKINGNUM", $pkg['tracking_id'], $tracking_url);
                    $html.= '  <tr><td><a href="'.$href.'" target="_blank">'.$pkg['tracking_id']."</a></td>";
                } else { $html .= "  <tr><td>".$pkg['tracking_id']."</td>"; }
                $html .= "  <td>".(!empty($pkg['actual_date'])?viewFormat($pkg['actual_date'], 'date'):'&nbsp;')."</td></tr>";
                if ($pkg['cost'])  { $cost += $pkg['cost']; }
            }
            $html .= " </tbody>";
            $html .= "</table><br />";
            $html .= lang('notes').': '.implode('<br />', (array)$meta['notes']).'<br />';
            $html .= lang('cost').': ' .viewFormat($cost, 'currency').'<br />';

        }
        $data = ['type'=>'popup','title'=>lang('shipping_log'),'attr'=>['id'=>'winShipping','width'=>400,'height'=>350],
            'divs'=>['body'=>['order'=>10,'type'=>'html','html' =>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Loads package details for carriers specific to a SKU
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function shpmtDetailsEdit(&$layout=[])
    {
        if (!$security= validateAccess('inv_mgr', 1, false)) { return; }
        if (!$rID  = clean('rID', 'integer', 'get'))                     { return; }
        $dbShipment= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'bizProShip', "id=$rID");
        $dbPkg     = !empty($dbShipment) ? json_decode($dbShipment, true) : [];
        msgDebug("\nRead dbPkg = ".print_r($dbPkg, true));
        $fields    = [
            'shippingBoxQ' =>['order'=>10,'label'=>$this->lang['box_q'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['box_q']) ?$dbPkg['box_q'] :1]],
            'shippingBoxL' =>['order'=>15,'label'=>$this->lang['box_l'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['box_l']) ?$dbPkg['box_l'] :0]],
            'shippingBoxW' =>['order'=>20,'label'=>$this->lang['box_w'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['box_w']) ?$dbPkg['box_w'] :0]],
            'shippingBoxH' =>['order'=>25,'label'=>$this->lang['box_h'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['box_h']) ?$dbPkg['box_h'] :0]],
            'shippingBoxWt'=>['order'=>30,'label'=>$this->lang['box_wt'],'attr'=>['type'=>'integer','value'=>!empty($dbPkg['box_wt'])?$dbPkg['box_wt']:0]],
            'shippingPltQ' =>['order'=>50,'label'=>$this->lang['plt_q'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['plt_q']) ?$dbPkg['plt_q'] :1]],
            'shippingPltL' =>['order'=>55,'label'=>$this->lang['plt_l'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['plt_l']) ?$dbPkg['plt_l'] :0]],
            'shippingPltW' =>['order'=>60,'label'=>$this->lang['plt_w'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['plt_w']) ?$dbPkg['plt_w'] :0]],
            'shippingPltH' =>['order'=>65,'label'=>$this->lang['plt_h'], 'attr'=>['type'=>'integer','value'=>!empty($dbPkg['plt_h']) ?$dbPkg['plt_h'] :0]],
            'shippingPltWt'=>['order'=>70,'label'=>$this->lang['plt_wt'],'attr'=>['type'=>'integer','value'=>!empty($dbPkg['plt_wt'])?$dbPkg['plt_wt']:0]]];
        $layout    = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'  =>['main'=>['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'details'=> ['order'=>60,'type'=>'panel','key'=>'details','classes'=>['block33']]]]],
            'panels'=> [
                'details'=> ['label'=>lang('details'),'type'=>'fields','keys'=>array_keys($fields)]],
            'fields'=> $fields]);
    }

    /**
     * Loads package details for carriers specific to a SKU
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function shpmtDetailsSave()
    {
        if (!$security= validateAccess('inv_mgr', 3, false)) { return; }
        if (!$rID     = clean('id', 'integer', 'post')) { return; }
        // see if the tab has been populated
        $qty = clean('shippingBoxQ', 'integer', 'post');
        if (empty($qty)) { return; }
        $invShip = [
            'box_q' => $qty,
            'box_l' => clean('shippingBoxL', 'integer', 'post'),
            'box_w' => clean('shippingBoxW', 'integer', 'post'),
            'box_h' => clean('shippingBoxH', 'integer', 'post'),
            'box_wt'=> clean('shippingBoxWt','integer', 'post'),
            'plt_q' => clean('shippingPltQ', 'integer', 'post'),
            'plt_l' => clean('shippingPltL', 'integer', 'post'),
            'plt_w' => clean('shippingPltW', 'integer', 'post'),
            'plt_h' => clean('shippingPltH', 'integer', 'post'),
            'plt_wt'=> clean('shippingPltWt','integer', 'post')];
        msgDebug("\nReady to write ship data = ".print_r($invShip, true));
        dbWrite(BIZUNO_DB_PREFIX.'inventory', ['bizProShip'=>json_encode($invShip)], 'update', "id=$rID");
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function getCarrier(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $carrier = clean('sID', 'cmd', 'get');
        $settings= getMetaMethod('carriers', $carrier);
        $fqcn    = "\\bizuno\\$carrier";
        bizAutoLoad($settings['path']."$carrier.php", $fqcn);
        $est     = new $fqcn();
        if (method_exists($est, 'managerForm')) {
            $est->managerForm($layout);
            return;
        }
        $est->manager($layout);
    }

    /**
     * Toggles the reconciled flag on the journal shipping meta record
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function toggleReconcile(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $refID = clean('rID', 'integer', 'get'); // rID contains the journal_main record
        if (empty($refID)) { return msgAdd(lang('illegal_access')); }
        $meta  = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        msgDebug("\nRead shipment meta data = ".print_r($meta, true));
        $rID   = metaIdxClean($meta);
        foreach ($meta['packages']['rows'] as $idx => $box) {
            $meta['packages']['rows'][$idx]['reconciled'] = empty($box['reconciled']) ? '1' : '0';
        }
        msgDebug("\nReady to write rID = $rID and meta: ".print_r($meta, true));
        dbMetaSet($rID, $this->metaPrefix, $meta, 'journal', $refID);
        $layout= array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"jqBiz('#dg{$this->domSuffix}').datagrid('reload');"]]);
    }
}
