<?php
/*
 * @name Bizuno ERP - Return Manager Extension
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
 * @filesource /controllers/phreebooks/returns.php
 */

namespace bizuno;

class phreebooksReturns extends mgrJournal
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'returns';
    protected $domSuffix = 'Returns';
    protected $metaPrefix= 'return';
    protected $secID     = 'returns';
    protected $nextRefIdx= 'next_return_num';
    protected $journalID = 12;

    function __construct()
    {
        $this->lang          = getExtLang($this->moduleID);
        $this->settings      = getModuleCache($this->moduleID, 'settings');
        $this->myStore       = getUserCache('profile', 'store_id', false, 0);
        $this->reps          = viewRoleDropdown();
        $this->return_status = viewKeyDropdown(getModuleCache('bizuno', 'options', 'return_status'),false, true);
        array_unshift($this->return_status,['id'=>0, 'text'=>lang('none')]);
        $this->return_codes  = viewKeyDropdown(getModuleCache('bizuno', 'options', 'return_codes'), false, true);
        array_unshift($this->return_codes, ['id'=>0, 'text'=>lang('none')]);
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $stores  = getModuleCache('bizuno', 'stores');
        $this->struc = [
            '_rID'            => ['tab'=>'general',  'panel'=>'general',   'order'=> 0,                                'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            '_refID'          => ['tab'=>'general',  'panel'=>'general',   'order'=> 0,                                'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'receive_details' => ['tab'=>'general',  'panel'=>'general',   'order'=> 0,                                'clean'=>'json',    'attr'=>['type'=>'hidden', 'value'=>'']],
            'close_details'   => ['tab'=>'general',  'panel'=>'general',   'order'=> 0,                                'clean'=>'json',    'attr'=>['type'=>'hidden', 'value'=>'']],
            'ref_num'         => ['tab'=>'general',  'panel'=>'general',   'order'=>10,'label'=>lang('return_num'),    'clean'=>'cmd','attr'=>['type'=>'text','value'=>'']],
            'store_id'        => ['tab'=>'general',  'panel'=>'general',   'order'=>20,'label'=>lang('store_id'),      'clean'=>'integer',  'values'=>viewStores(), 'attr'=>['type'=>sizeof($stores)>1?'select':'hidden', 'value'=>$this->myStore]],
            'status'          => ['tab'=>'general',  'panel'=>'general',   'order'=>30,'label'=>lang('status'),        'clean'=>'alpha_num','values'=>$this->return_status,'attr'=>['type'=>'select', 'value'=>'1']],
            'code'            => ['tab'=>'general',  'panel'=>'general',   'order'=>40,'label'=>lang('code'),          'clean'=>'alpha_num','values'=>$this->return_codes, 'attr'=>['type'=>'select', 'value'=>'']],
            'preventable'     => ['tab'=>'general',  'panel'=>'general',   'order'=>50,'label'=>lang('preventable'),   'clean'=>'char', 'attr'=>['type'=>'selNoYes', 'value'=>0]],
            'invoice_num'     => ['tab'=>'general',  'panel'=>'general',   'order'=>60,'label'=>lang('invoice_num_12'),'clean'=>'text', 'attr'=>['value'=>'']],
            'caller_name'     => ['tab'=>'general',  'panel'=>'general',   'order'=>70,'label'=>lang('caller_name'),   'clean'=>'text', 'attr'=>['value'=>'']],
            'telephone'       => ['tab'=>'general',  'panel'=>'general',   'order'=>80,'label'=>lang('telephone'),     'clean'=>'text', 'attr'=>['value'=>'']],
            'email'           => ['tab'=>'general',  'panel'=>'general',   'order'=>90,'label'=>lang('email'),         'clean'=>'text', 'attr'=>['value'=>'']],
            'creation_date'   => ['tab'=>'general',  'panel'=>'properties','order'=>10,'label'=>lang('creation_date'), 'clean'=>'date', 'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'entered_by'      => ['tab'=>'general',  'panel'=>'properties','order'=>20,'label'=>lang('entered_by'),    'clean'=>'integer',  'values'=>$this->reps, 'attr'=>['type'=>'select', 'value'=>getUserCache('profile', 'userID')]],
            'notes'           => ['tab'=>'general',  'panel'=>'properties','order'=>30,'label'=>lang('notes'),         'clean'=>'text', 'attr'=>['type'=>'textarea', 'value'=>'']],
            'closed_date'     => ['tab'=>'general',  'panel'=>'close',     'order'=>10,'label'=>lang('closed_date'),   'clean'=>'date', 'attr'=>['type'=>'date', 'value'=>'']],
            'closed_by'       => ['tab'=>'general',  'panel'=>'close',     'order'=>20,'label'=>lang('closed_by'),     'clean'=>'integer',  'values'=>$this->reps, 'attr'=>['type'=>'select', 'value'=>'']],
            'close_notes'     => ['tab'=>'general',  'panel'=>'close',     'order'=>30,'label'=>lang('notes'),         'clean'=>'text', 'attr'=>['type'=>'textarea', 'value'=>'']],
            'receive_date'    => ['tab'=>'receiving','panel'=>'receiving', 'order'=>10,'label'=>lang('date_received'), 'clean'=>'date', 'attr'=>['type'=>'date', 'value'=>'']],
            'received_by'     => ['tab'=>'receiving','panel'=>'receiving', 'order'=>20,'label'=>lang('received_by'),   'clean'=>'integer',  'values'=>$this->reps, 'attr'=>['type'=>'select', 'value'=>'']],
            'receive_carrier' => ['tab'=>'receiving','panel'=>'receiving', 'order'=>30,'label'=>lang('carrier'),       'clean'=>'cmd',  'attr'=>['value'=>'']],
            'receive_tracking'=> ['tab'=>'receiving','panel'=>'receiving', 'order'=>40,'label'=>lang('tracking_num'),  'clean'=>'cmd',  'attr'=>['value'=>'']],
            'receive_notes'   => ['tab'=>'receiving','panel'=>'notes',     'order'=>10,                                'clean'=>'text', 'attr'=>['type'=>'editor', 'value'=>'']]];
    }

    /**
     * This function builds the grid structure
     * @param integer $security - access level range 0-4
     * @return array $data - structure of the grid to render
    */
    protected function managerGrid($security=0, $args=[])
    {
        $action   = clean('mgrAction','cmd',    'get');
        $key      = clean('rIDList',  'integer','get');
        $range    = clean('range',    'integer','get');
        $dateRange= dbSqlDates($this->defaults['period']);
        $sqlPeriod= $dateRange['sql'];
        $stores   = getModuleCache('bizuno', 'stores');
        $data     = array_replace_recursive(parent::gridBase($security, $args), ['metaTable'=>'journal',
            'attr'   => ['url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&mgrAction=$action&rIDList=$key&range=$range"],
            'source' => [
                'tables' => ['journal_meta'=>['table'=>BIZUNO_DB_PREFIX.'journal_meta','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_meta.ref_id"]],
                'search' => ['invoice_num', 'post_date', 'description', 'meta_value'],
                'filters'=> [
                    'metaKey' => ['order'=> 1,'break'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_meta.meta_key = '{$this->metaPrefix}'", 'hidden'=>true], // m.meta_key LIKE 'shipment_%'
                    'period'  => ['order'=>10,'break'=>true,'label'=>lang('period'), 'options'=>['width'=>300],'sql'=>$sqlPeriod,
                        'values'=>viewKeyDropdown(localeDates(true, true, true, true, true)),'attr'=>['type'=>'select','value'=>$this->defaults['period']]],
                    'store_id'=> ['order'=>15,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'values'=>viewStores(),'attr'=>['type'=>sizeof($stores)>1?'select':'hidden','value'=>$this->defaults['store_id']]],
                    'status'    => ['order'=>40,'break'=>true,'label'=>lang('status'), 'values'=>$this->return_status, 'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns'=> [
                'id'          => ['order'=>0, 'field'=>'DISTINCT '.BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]], // need to override gridBase
                'action' => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return dg{$this->domSuffix}Formatter(value,row,index); }"],
                    'actions'=> [
                        'print'=> ['order'=>10,'icon'=>'print',
                            'events' => ['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=cust:rtn&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]]]],
                'ref_num'      => ['order'=>10,'field'=>'journal_meta.id','label'=>lang('return_num'),    'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:ref_num:journal'],
                'store_id'     => ['order'=>20,'field'=>'store_id',       'label'=>lang('store_id'),      'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],'format'=> 'storeID'],
                'caller_name'  => ['order'=>30,'field'=>'journal_meta.id','label'=>lang('caller_name'),   'attr'=>['width'=>240, 'sortable'=>true, 'resizable'=>true],'process'=>'meta:caller_name:journal'],
                'status'       => ['order'=>40,'field'=>'journal_meta.id','label'=>lang('status'),        'attr'=>['width'=>200, 'resizable'=>true],'events'=>['formatter'=>"function(value,row){ return returnsStatus[value]; }"],'process'=>'meta:status:journal'],
                'invoice_num'  => ['order'=>50,'field'=>'invoice_num',    'label'=>lang('invoice_num_12'),'attr'=>['width'=>120, 'sortable'=>true, 'resizable'=>true]],
                'creation_date'=> ['order'=>60,'field'=>'journal_meta.id','label'=>lang('creation_date'), 'attr'=>['width'=>120, 'type'=>'date', 'sortable'=>true, 'resizable'=>true],'process'=>'meta:creation_date:journal','format'=>'date'],
                'closed_date'  => ['order'=>70,'field'=>'journal_meta.id','label'=>lang('close_date'),    'attr'=>['width'=> 80, 'type'=>'date', 'sortable'=>true, 'resizable'=>true],'process'=>'meta:closed_date:journal','format'=>'date']],
                'footnotes'    => ['jType'=>lang('status').': <span class="row-inactive">'.$this->lang['rtn_status_1'].'</span>']]);
        switch($action) {
            case 'rtn_by_cust':   $this->addFilters($data, 'rtn_by_cust');   break;
            case 'return_metrics':$this->addFilters($data, 'return_metrics');break; // returns by SKU
            default:
        }
        msgDebug("\nready to process, grid = ".print_r($data, true));
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']    = clean('sort',    ['format'=>'cmd',      'default'=>'post_date'],'post');
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',      'default'=>getUserCache('profile', 'def_periods', '', 'l')], 'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer',  'default'=>-1],       'post');
        $this->defaults['status']  = clean('status',  ['format'=>'alpha_num','default'=>'a'],      'post');
    }

    /******************************** Journal Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $dom  = clean('dom', ['format'=>'cmd', 'default'=>'page'], 'get'); // or div
        $args = ['dom'=>$dom, 'type'=>'journal', '_table'=>'journal', 'title'=>sprintf(lang('tbd_manager'), lang('returns'))];
        parent::managerMain($layout, $security, $args);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
        $layout['jsHead']['vars'] = "var returnsStatus = ".json_encode(getModuleCache('bizuno', 'options', 'return_status'), JSON_UNESCAPED_UNICODE).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $grid= $this->managerGrid($security, ['type'=>'journal']);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $refID= $_GET['refID']= clean('rID', 'integer', 'get'); // map journal_main.id to refID
        $meta = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        $rID  = $_GET['rID']  = metaIdxClean($meta);
        $args = ['_table'=>'journal', 'title'=>lang('new')];
        parent::editMeta($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
        if ($rID) { // customize JavaScript
            $layout['fields']['ref_num']['attr']['readonly'] = true;
            $layout['divs']['heading']['html']= "<h1>".$this->lang['return_num']." ".$this->struc['ref_num']['attr']['value']." - ".$this->struc['caller_name']['attr']['value']."</h1>";
            $layout['jsHead']['dgReceiveData']= "var dgReceiveData = ".json_encode($this->struc['receive_details']['attr']['value']).";\n";
            $layout['jsHead']['dgCloseData']  = "var dgCloseData = "  .json_encode($this->struc['close_details']['attr']['value']).";\n";
        } else  {
            $layout['fields']['ref_num']['attr']['type'] = 'hidden';
            $layout['jsReady']['jsAddDg'] = "bizGridAddRow('dgClose'); bizGridAddRow('dgReceive');";
            $layout['jsHead']['dgReceiveData']= "var dgReceiveData = [];";
            $layout['jsHead']['dgCloseData']  = "var dgCloseData = [];";
        }
        $layout['jsHead'][$this->pageID] = "function preSubmit() { bizGridSerializer('dgReceive', 'receive_details'); bizGridSerializer('dgClose', 'close_details'); return true; }
function rtnFill(id, row) { bizTextSet('caller_name', row.primary_name); bizTextSet('contact_name', row.attn); bizTextSet('telephone', row.telephone1); bizTextSet('email', row.email); bizTextSet('contact_id', row.short_name); }";
        $layout['jsReady'][$this->pageID] = "if (dgCloseData.rows.length == 0) { bizGridAddRow('dgClose'); } if (dgReceiveData.rows.length == 0) { bizGridAddRow('dgReceive'); }";
        // add the attachment panels
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['general']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
        // add the grids
        $layout['datagrid'] = ['dgReceive'=>$this->dgItems('dgReceive', 'receive'), 'dgClose'=>$this->dgItems('dgClose', 'close')];
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['general']['divs']['dgClose']    = ['order'=>40, 'type'=>'panel', 'key'=>'dgClose',  'classes'=>['block99']];
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['receiving']['divs']['dgReceive']= ['order'=>40, 'type'=>'panel', 'key'=>'dgReceive','classes'=>['block99']];
        $layout['panels']['dgClose']  = ['type'=>'datagrid', 'key'=>'dgClose'];
        $layout['panels']['dgReceive']= ['type'=>'datagrid', 'key'=>'dgReceive'];
    }
    public function save(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        $args = ['_table'=>'journal'];
        parent::saveMeta($layout, $args);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $refID= clean('rID', 'integer', 'get'); // map journal_main.id to refID
        $meta = dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        $rID  = $_GET['rID'] = metaIdxClean($meta);
        $args = ['_table'=>'journal'];
        parent::deleteMeta($layout, $args);
    }
    public function export(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->pageID, 1)) { return; }
        $thisQtr = dbSqlDates('h');
        $lstQStrt= localeCalculateDate($thisQtr['start_date'],0, -3);
        $lstQEnd = localeCalculateDate($thisQtr['end_date'],  0, -3);
        $filter  = "creation_date>='$lstQStrt' AND creation_date<'$lstQEnd'";
        $rows    = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, 'post_date', ['creation_date','return_num','caller_name','close_details','notes']);
        foreach ($rows as $idx => $row) {
            $items = json_decode($row['close_details'], true);
            $sku = [];
            foreach ($items as $item) { $sku[] = $item['sku']; }
            $rows[$idx]['close_details'] = implode('; ', $sku);
        }
        array_unshift($rows, ['Create Date', 'Reference #', 'Customer', 'SKU(s)', 'Notes']);
        if (empty($rows)) { return msgAdd(lang('no_results')); }
        $io->download('data', arrayToCSV($rows), "RMAdata-".biz_date('Y-m-d').".csv");
    }

    /**
     * Pulls the filtered list from the requested dashboard after a user selects a piece of the pie
     * @param array $data - grid data to modify
     * @return modifies the grid data array
     */
    private function addFilters(&$data=[], $dashID='')
    {
        $key  = clean('rIDList','integer','get');
        $range= clean('range',  'integer','get');
        $dash = getDashboard($dashID, []);
        msgDebug("\nFetched dash properties = ".print_r($dash, true));
        $cData= $dash->getData($range);
        unset($data['source']['filters']['period']); // clear date search criteris, i.e. set to all since we are after specific id's
        $data['source']['filters']['rIDList'] = ['order'=> 0, 'hidden'=>true, 'sql'=>"journal_main.id IN (".implode(',', $cData['data'][$key]['rID']).")"];
    }

    /**
     *
     * @param type $name
     * @param type $security
     * @param type $type
     * @return type
     */
    private function dgItems($name, $type='close')
    {
        $on_hand = pullTableLabel('inventory', 'qty_stock');
        return ['id'=>$name,'type'=>'edatagrid','title'=>$type=='close' ? $this->lang['item_details'] : $this->lang['receive_details'],
            'attr'  => ['toolbar'=>"{$name}Toolbar",'idField'=>'id','singleSelect'=>true],
            'events'=> ['data'   => "{$name}Data",
                'onClickRow'=> "function(rowIndex) { curIndex = rowIndex; }",
                'onDestroy' => "function(rowIndex) { curIndex = -1; }",
                'onAdd'     => "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=>["new$type"=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'id'    => ['order'=> 0,'attr' =>['hidden'=>'true']],
                'action'=> ['order'=> 1,'label'=>lang('action'),'attr'=>['width'=>60],'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'qty'   => ['order'=>10,'label'=>lang('qty'),   'attr'=>['width'=>75,'resizable'=>true],'events'=>['editor'=>"{type:'numberbox'}"]],
                'sku'   => ['order'=>20,'label'=>pullTableLabel('journal_item', 'sku'),'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'combogrid',options:{
                        width:150, panelWidth:400, delay:500, idField:'sku', textField:'sku', mode:'remote',
                        url:  bizunoAjax+'&bizRt=inventory/main/managerRows',
                        onClickRow: function (idx, data) {
                            var descEditor = jqBiz('#$name').datagrid('getEditor', {index:curIndex,field:'desc'});
                            jqBiz(descEditor.target).val(data.description_short);
                            jqBiz('#$name').datagrid('getRows')[curIndex]['desc'] = data.description_short;
                        },
                        columns: [[{field:'sku',              title:'".jsLang('sku')."',        width:100},
                                   {field:'description_short',title:'".jsLang('description')."',width:200},
                                   {field:'qty_stock',        title:'$on_hand', align:'right',  width: 90}]]
                    }}"]],
                'desc'  => ['order'=>30,'label'=>lang('description'),'attr'=>['width'=>250,'editor'=>'text','resizable'=>true]],
                'mfg'   => ['order'=>40,'label'=>lang('trans_code'), 'attr'=>['width'=>150,'editor'=>'text','resizable'=>true,'hidden'=>$type=='receive'?false:true]],
                'wrnty' => ['order'=>50,'label'=>$this->lang['warranty_date'],'attr'=>['width'=>200,'editor'=>'text','resizable'=>true,'hidden'=>$type=='receive'?false:true]],
                'notes' => ['order'=>60,'label'=>lang('notes'),      'attr'=>['width'=>400,'editor'=>'text','resizable'=>true, 'hidden'=>$type=='close' ?false:true]]]];
    }

    /**
     * Builds the editor creating returns on the fly in Sale manager
     * @param array $layout - current page structure
     * @return modified $layout
     */
    public function getInfoForm(&$layout=[])
    {
        if (!$security = validateAccess($this->pageID, 2)) { return msgAdd(lang('err_no_permission')); }
        $mID  = clean('rID', 'integer', 'get');
        if (empty($mID)) { return msgAdd('Invalid ID passed'); }
        $main = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['primary_name_b', 'email_b', 'telephone1_b'], "id=$mID");
        $fields = [
            'mID'         => ['order'=> 0,                              'attr'=>['type'=>'hidden',  'value'=>clean('rID', 'integer','get')]],
            'caller_name' => ['order'=>10,'label'=>lang('primary_name'),'attr'=>['type'=>'text',    'value'=>$main['primary_name_b']]],
            'caller_phone'=> ['order'=>20,'label'=>lang('telephone'),   'attr'=>['type'=>'text',    'value'=>$main['telephone1_b']]],
            'caller_email'=> ['order'=>30,'label'=>lang('email'),       'attr'=>['type'=>'text',    'value'=>$main['email_b']]],
            'status'      => ['order'=>40,'label'=>lang('status'),      'attr'=>['type'=>'select',  'value'=>1],'values'=>$this->return_status],
            'code'        => ['order'=>50,'label'=>lang('code'),        'attr'=>['type'=>'select',  'value'=>0],'values'=>$this->return_codes],
            'preventable' => ['order'=>60,'label'=>lang('preventable'), 'attr'=>['type'=>'selNoYes','value'=>0]],
            'notes'       => ['order'=>70,'label'=>lang('notes'),       'attr'=>['type'=>'textarea','rows' =>6]]];
        $data = ['type'=>'popup','title'=>$this->lang['create_return'], 'attr'=>['id'=>'winReturn','width' =>650],
            'toolbars'=> ['tbReturn'=>['icons'=>[
                'cancel' =>['order'=>10,'events'=>['onClick'=>"bizWindowClose('winReturn');"]],
                'next'   =>['order'=>50,'events'=>['onClick'=>"jqBiz('#frmReturn').submit();"]]]]],
            'divs'    => [
                'toolbar'  => ['order'=>10,'type'=>'toolbar','key' =>'tbReturn'],
                'formBOF'  => ['order'=>20,'type'=>'form',   'key' =>'frmReturn'],
                'winReturn'=> ['order'=>50,'type'=>'fields', 'keys'=>array_keys($fields)],
                'formEOF'  => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'forms'   => ['frmReturn'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/getReturnNum"]]],
            'fields'  => $fields,
            'jsReady' => ['init'=>"ajaxForm('frmReturn', true);"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Auto generates a return and returns with the return number
     * @param array $layout - current page structure
     * @return modified $layout
     */
    public function getReturnNum(&$layout=[])
    {
        if (!$security = validateAccess($this->pageID, 2)) { return msgAdd(lang('err_no_permission')); }
        $mID  = clean('mID', 'integer', 'post');
        if (empty($mID)) { return msgAdd('Invalid ID passed'); }
        $main = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "id=$mID");
        $item = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$mID AND gl_type='itm'");
        $items= [];
        foreach ($item as $row) {
            if (empty($row['sku'])) { continue; }
            $items[] = ['sku'=>$row['sku'], 'qty'=>$row['qty'], 'desc'=>$row['description']];
        }
        $nextRef = getNextReference($this->nextRefIdx);
        $values= [
            'ref_num'      => $nextRef,
            'status'       => clean('status', 'text', 'post'),
            'entered_by'   => getUserCache('profile', 'userID'),
            'creation_date'=> biz_date(),
            'code'         => clean('code', 'integer', 'post'),
            'preventable'  => clean('preventable', 'integer', 'post'),
            'caller_name'  => clean('caller_name', 'text', 'post'),
            'telephone'    => clean('caller_phone', 'text', 'post'),
            'email'        => clean('caller_email', 'text', 'post'),
            'invoice_num'  => $main['invoice_num'],
            'notes'        => substr(clean('notes', 'text', 'post'), 0, 254),
            'close_details'=> ['total'=>sizeof($items), 'rows'=>$items]];
        msgDebug("\nReady to write values = ".print_r($values, true));
        $tmp = dbMetaGet(0, 'return', 'journal', $mID);
        $rID = metaIdxClean($tmp);
        dbMetaSet($rID, 'return', $values, 'journal', $mID);
        msgLog(lang('return')."-".lang('save')." - $nextRef");
        msgAdd(sprintf("Successfully created return # %s", $nextRef), 'info');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winReturn');"]]);
    }

    /**
     * Extends the Inventory rename method to change SKU values in the
     * @param array $layout - current page structure
     * @return modified $layout
     */
    public function invRename()
    {
        if (!$security = validateAccess('inv_mgr', 3)) { return; }
        $rID    = clean('rID', 'integer','get');
        $oldSKU = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        $newSKU = clean('data', 'text', 'get');
        if (empty($oldSKU) || empty($newSKU)) { return; }
        $cnt    = 0;
        $returns= dbMetaGet(0, 'return', 'journal', '%');
        msgDebug("\nWorking in renameMetaReturn  with number of returns = ".sizeof($returns));
        foreach ($returns as $return) {
            $found = false;
            if (!empty($return['receive_details']['rows'])) { 
                foreach ($return['receive_details']['rows'] as $key => $value) { if ($value['sku']==$oldSKU) { $return['receive_details']['rows'][$key]= $newSKU; $found = true; } }
            }
            if (!empty($return['close_details']['rows'])) { 
                foreach ($return['close_details']['rows']   as $key => $value) { if ($value['sku']==$oldSKU) { $return['close_details']['rows'][$key]  = $newSKU; $found = true; } }
            }
            if ($found) {
                $cnt++;
                $refID = $return['_refID'];
                $metaID = metaIdxClean($return);
                dbMetaSet($metaID, 'bill_of_materials', $return, 'inventory', $refID);
            }
        }
    }
}
