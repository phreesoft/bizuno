<?php
/*
 * Bizuno Extension - Quality Ticket Manager [journal_main only]
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
 * @version    7.x Last Update: 2025-11-13
 * @filesource /controllers/quality/tickets.php
 */

namespace bizuno;

class qualityTickets extends mgrJournal

{
    public    $moduleID  = 'quality';
    public    $pageID    = 'tickets';
    protected $secID     = 'qa_ticket';
    protected $domSuffix = 'Tickets';
    protected $metaPrefix= 'qa_ticket';
    protected $nextRefIdx= 'next_ticket_num';
    protected $journalID = 30;
    public    $attachPath;

    function __construct()
    {
        parent::__construct();
        $this->qual_status= getModuleCache('bizuno', 'options', 'qa_status');
        $this->mapPanel   = ['title0'=>lang('stop_work'), 'title1'=>lang('work_around'), 'title2'=>lang('root_cause'), 'title3'=>lang('action_corr')];
        $this->attachPath = getModuleCache($this->moduleID, 'properties', 'attachPath', 'correctives');
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    protected function fieldStructure()
    {
        $users = listUsers(); 
        $this->struc = [ // general
            'id'                => ['panel'=>'general','order'=> 1,'dbField'=>'id',                                               'clean'=>'integer', 'attr'=>['type'=>'hidden']],
            'ref_num'           => ['panel'=>'general','order'=> 1,'dbField'=>'invoice_num',   'label'=>lang('task_id'),          'clean'=>'integer',  'attr'=>['type'=>'text', 'readonly'=>true]],
            'title'             => ['panel'=>'general','order'=>10,'dbField'=>'description',   'label'=>lang('title'),            'clean'=>'text',    'attr'=>['type'=>'text',    'value'=>'']],
            'store_id'          => ['panel'=>'general','order'=>15,'dbField'=>'store_id',      'label'=>lang('store_id'),         'clean'=>'integer', 'attr'=>['type'=>'select',  'value'=>-1], 'values'=>viewStores()],
            'preventable'       => ['panel'=>'general','order'=>20,'dbField'=>'waiting',       'label'=>lang('preventable'),      'clean'=>'bolean',  'attr'=>['type'=>'selNoYes','value'=> 0]],
            'status'            => ['panel'=>'general','order'=>25,'dbField'=>'printed',       'label'=>lang('status'),           'clean'=>'integer', 'attr'=>['type'=>'select'], 'values'=>viewKeyDropdown($this->qual_status, true)],
            'closed'            => ['panel'=>'general','order'=>30,'dbField'=>'closed',        'label'=>lang('closed'),           'clean'=>'bolean',  'attr'=>['type'=>'selNoYes','value'=> 0]],
            'requested_by'      => ['panel'=>'general','order'=>35,'dbField'=>'rep_id',        'label'=>$this->lang['found_by'],  'clean'=>'integer', 'attr'=>['type'=>'select'], 'values'=>$users],
            'creation_date'     => ['panel'=>'general','order'=>40,'dbField'=>'post_date',     'label'=>lang('date_created'),     'clean'=>'date',    'attr'=>['type'=>'date',    'value'=>biz_date()]],
            'entered_by'        => ['panel'=>'general','order'=>45,                            'label'=>lang('entered_by'),       'clean'=>'integer', 'attr'=>['type'=>'select'], 'values'=>$users],
            // detail
            'close_start_date'  => ['panel'=>'details','order'=>30,                            'label'=>lang('date_found'),       'clean'=>'date',    'attr'=>['type'=>'date']],
            'close_start_by'    => ['panel'=>'details','order'=>35,                            'label'=>$this->lang['created_by'],'clean'=>'integer', 'attr'=>['type'=>'select'], 'values'=>$users],
            'contact_id'        => ['panel'=>'details','order'=>40,'dbField'=>'contact_id_b',  'label'=>lang('vendor'),           'clean'=>'integer', 'attr'=>['type'=>'contact'],'defaults'=>['type'=>'v','callback'=>'']],
            'audit_start_by'    => ['panel'=>'details','order'=>45,                            'label'=>lang('quantity'),         'clean'=>'float',   'attr'=>['type'=>'integer']],
            'sku_id'            => ['panel'=>'details','order'=>50,'dbField'=>'purch_order_id','label'=>lang('sku'),              'clean'=>'integer', 'attr'=>['type'=>'inventory'],'defaults'=>['callback'=>'']],
            'telephone'         => ['panel'=>'details','order'=>55,'dbField'=>'telephone1_b',  'label'=>lang('telephone1'),       'clean'=>'db_field','attr'=>['type'=>'text']],
            'email'             => ['panel'=>'details','order'=>60,'dbField'=>'email_b',       'label'=>lang('email'),            'clean'=>'email',   'attr'=>['type'=>'text']],
            // stop work cause
            'analyze_end_by'    => ['panel'=>'stop_work','order'=>10,                          'label'=>lang('by'),    'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>$users],
            'analyze_end_date'  => ['panel'=>'stop_work','order'=>20,                          'label'=>lang('date'),  'clean'=>'date',   'attr'=>['type'=>'date']],
            'notes'             => ['panel'=>'stop_work','order'=>30,                          'label'=>lang('notes'), 'clean'=>'text',   'attr'=>['type'=>'editor'], 'break'=>false],
            // work around
            'audit_end_by'      => ['panel'=>'workaround','order'=>10,                         'label'=>lang('by'),    'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>$users],
            'audit_end_date'    => ['panel'=>'workaround','order'=>20,                         'label'=>lang('date'),  'clean'=>'date',   'attr'=>['type'=>'date']],
            'audit_notes'       => ['panel'=>'workaround','order'=>30,                         'label'=>lang('notes'), 'clean'=>'text',   'attr'=>['type'=>'editor'], 'break'=>false],
            // root cause analysis
            'analyze_start_by'  => ['panel'=>'root_cause','order'=>10,                         'label'=>lang('by'),    'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>$users],
            'analyze_start_date'=> ['panel'=>'root_cause','order'=>20,                         'label'=>lang('date'),  'clean'=>'date',   'attr'=>['type'=>'date']],
            'issue_notes'       => ['panel'=>'root_cause','order'=>30,                         'label'=>lang('notes'), 'clean'=>'text',   'attr'=>['type'=>'editor'], 'break'=>false],
            // corrective action
            'action_by'         => ['panel'=>'action_cor','order'=>10,                           'label'=>lang('by'),   'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>$users],
            'action_date'       => ['panel'=>'action_cor','order'=>20,'dbField'=>'terminal_date','label'=>lang('date'), 'clean'=>'date',   'attr'=>['type'=>'date']],
            'action_notes'      => ['panel'=>'action_cor','order'=>30,                           'label'=>lang('notes'),'clean'=>'text',   'attr'=>['type'=>'editor'], 'break'=>false],
            // closed
            'close_end_by'      => ['panel'=>'action_close','order'=>10,                         'label'=>lang('by'),   'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>$users],
            'close_end_date'    => ['panel'=>'action_close','order'=>20,'dbField'=>'closed_date','label'=>lang('date'), 'clean'=>'date',   'attr'=>['type'=>'date']],
            'contact_notes'     => ['panel'=>'action_close','order'=>30,                         'label'=>lang('notes'),'clean'=>'text',   'attr'=>['type'=>'editor'], 'break'=>false]];
    }

    /**
     * This function builds the grid structure for retrieving the data
     * @param integer $security - access level range 0-4
     * @return array $data - structure of the grid to render
    */
    protected function managerGrid($security=0, $args=[])
    {
        $stores   = getModuleCache('bizuno', 'stores');
        $action   = clean('mgrAction','cmd',    'get');
        $rIDList  = clean('rIDList',  'integer','get');
        $range    = clean('range',    'integer','get');
        $menu     = clean('menu',     'cmd',    'get');
        $statuses = array_merge([['id'=>'a','text'=>lang('all')]], viewKeyDropdown(getModuleCache('bizuno', 'options', 'qa_status')));
        $selClosed= [['id'=>'a','text'=>lang('all')], ['id'=>'1','text'=>lang('yes')], ['id'=>'0','text'=>lang('no')]];
        // clean up the filter sqls
        switch ($this->defaults['status']) {
            default:
            case 'a': $f0_value = "";           break;
            case 'y': $f0_value = "closed='1'"; break;
            case 'n': $f0_value = "closed='0'"; break;
        }
        $f1 = clean('f1', 'integer', 'post');
        $f1_value = $f1 ? "printed='$f1'" : "";
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'attr'   => ['url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&menu=$menu&mgrAction=$action&rIDList=$rIDList&range=$range"],
            'source' => [
                'search' => ['contact_name', 'invoice_num', 'contact_id', 'ref_num', 'title'],
                'filters'=> [
                    'store_id'=>['order'=>15,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'values'=>viewStores(),'attr'=>['type'=>sizeof($stores)>1?'select':'hidden','value'=>$this->defaults['store_id']]],
                    'closed' => ['order'=>20,'break'=>true,'label' =>$this->lang['cust_feedback'],'sql'=>$f0_value,'attr'=>['type'=>'select','value'=>$this->defaults['f0']],'values'=>$selClosed],
                    'status' => ['order'=>30,'break'=>true,'label' =>lang('status'),'sql'=>$f1_value,'attr'=>['type'=>'select','value'=>$this->defaults['f1']],'values'=>$statuses]]],
            'columns'=> [
                'invoice_num'  => ['order'=>10, 'label'=>$this->lang['ca_num'],'attr'=>['width'=> 75, 'sortable'=>true, 'resizable'=>true]],
                'store_id'     => ['order'=>20, 'label'=>lang('store_id'),     'attr'=>['width'=> 75, 'sortable'=>true, 'resizable'=>true], 'format'=> 'storeID'],
                'description'  => ['order'=>30, 'label'=>lang('description'),  'attr'=>['width'=>250, 'sortable'=>true, 'resizable'=>true]],
                'printed'      => ['order'=>40, 'label'=>lang('status'),       'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],
                    'events' => ['formatter'=>"function(value,row) { return bizQualStatuses[value]; }"]],
                'terminal_date'=> ['order'=>50, 'label'=>lang('date_found'),   'attr'=>['width'=> 80, 'type'=>'date', 'sortable'=>true, 'resizable'=>true], 'format'=>'date'],
                'closed_date'  => ['order'=>60, 'label'=>lang('date_closed'),  'attr'=>['width'=> 80, 'type'=>'date', 'sortable'=>true, 'resizable'=>true], 'format'=>'date']]]);
            switch($action) {
            case 'qa_by_vendor': $this->addFilters($data, 'qa_by_vendor'); break;
            case 'qa_by_sku':    $this->addFilters($data, 'qa_by_sku');    break;
            default:
        }
        return $data;
    }

    /**
     * Settings for the manager grid
     */
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>getUserCache('profile', 'def_periods', '', 'l')], 'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1], 'post');
        $this->defaults['closed']  = clean('closed',  ['format'=>'char',    'default'=>'a'],'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'],'post');
    }

    /******************************** Journal Manager ********************************/
    public function manager(&$layout=[])
    {
        msgTrap();
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['type'=>'journal', 'title'=>sprintf(lang('tbd_manager'), lang('ticket'))]);
        $layout['jsHead']['vars'] = "var bizQualStatuses = ".json_encode($this->qual_status).";";
        $qSettings = getModuleCache($this->moduleID, 'settings');
        if (!empty($qSettings['general']['proc_inv_mgr'])) {
            $layout['datagrid']['dgTickets']['source']['actions']['qaProc']= ['order'=>95,'icon'=>'steps',  'label'=>lang('qa_processes'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/admin/renderQA&qaIdx=proc_qa_ticket', 'qaDoc', '".lang('processes')."', 1000, 500);"]];
        }
        if (!empty($qSettings['general']['stnd_inv_mgr'])) {
            $layout['datagrid']['dgTickets']['source']['actions']['qaStnd']= ['order'=>96,'icon'=>'mimeTxt','label'=>lang('qa_standards'),   'events'=>['onClick'=>"windowEdit('$this->moduleID/admin/renderQA&qaIdx=stnd_qa_ticket', 'qaDoc', '".lang('standards')."', 1000, 500);"]];
        }
        if (!empty($qSettings['general']['inst_inv_mgr'])) {
            $layout['datagrid']['dgTickets']['source']['actions']['qaInst']= ['order'=>97,'icon'=>'mimeDoc','label'=>lang('qa_instructions'),'events'=>['onClick'=>"windowEdit('$this->moduleID/admin/renderQA&qaIdx=inst_qa_ticket', 'qaDoc', '".lang('instructions')."', 1000, 500);"]];
        }
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        $grid  = $this->managerGrid($security, ['type'=>'journal']);
        mapMetaGridToDB($grid, $this->struc);
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::editDB($layout, $security, ['rID'=>$rID]);
        // pull the meta, add it to the fields
        $meta = dbMetaGet(0, $this->metaPrefix, 'journal', $rID); // future meta_key = qa_ticket
        metaPopulate($this->struc, $meta);
        msgDebug("\nread meta for this entry = ".print_r($meta, true));
        foreach ($this->struc as $key => $row) { // add the meta to the 
            if (!empty($row['dbField'])) { continue; } // already accounted for
            $layout['fields'][$key] = $row;
        }
        // add the attachment panel
        $layout['divs']['content']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
    }
    public function save(&$layout=[])
    {
        $rID = clean('id', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveDB($layout);
        // Now save the meta part
        $refID = clean('id', 'integer', 'post');
        msgDebug("\nSaving meta portion with refID = $refID");
        $metaVal= dbMetaGet(0, $this->metaPrefix, 'journal', $refID);
        $metaID = metaIdxClean($metaVal);
        $output = metaUpdate($metaVal, $this->struc);
        dbMetaSet($metaID, $this->metaPrefix, $output, 'journal', $refID);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $refID = clean('rID', 'integer', 'get');
        $meta = !empty($refID) ? dbMetaGet(0, $this->metaPrefix, 'journal', $refID) : 0;
        if (!empty($meta['_rID'])) { parent::deleteMeta($layout, ['_rID'=>$meta['_rID']]); }
        parent::deleteDB($layout);
    }

    /**
     * Exports data from quality dashboards
     * @global class $io - Input/Output class
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function exportData()
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        global $io;
        $type = clean('type', 'alpha_num','get');
        $rows = $output = [];
        switch ($type) {
            default:
            case 'status': // get the last quarters QA by status where date1 <> date 2
                $filter  = "type='c' AND closed='0' AND status='2'";
                $rows    = dbGetMulti(BIZUNO_DB_PREFIX.'extISO9001', $filter, 'close_start_date', ['close_start_date','ref_num','title','analyze_start_date','audit_end_date']);
                array_unshift($rows, ['Create Date', 'Reference #', 'Title', 'Stop Work Date', 'Work Around Date']);
                break;
        }
        if (empty($rows)) { return msgAdd(lang('no_results')); }
        $io->download('data', arrayToCSV($rows), "QAdata-".biz_date('Y-m-d').".csv");
    }

    /**
     * Pulls the filtered list from the requested dashboard after a user selects a piece of the pie
     * @param array $data - grid data to modify
     * @return modifies the grid data array
     */
    private function addFilters(&$data=[], $dashID='')
    {
        msgDebug("\nEntering addFilters with dashID=$dashID");
        $key  = clean('rIDList','integer','get');
        $menu = clean('menu',   'cmd',    'get');
        $range = clean('range', 'integer','get');
        $props= dbMetaGet(0, "dashboard_{$menu}", 'contacts', getUserCache('profile', 'userID'));
        $dash = getDashboard($dashID, $props['$dashID']['opts']);
        $cData= $dash->getData($range);
        msgDebug("\n Back from readiong cData with results: ".print_r($cData, true));
        $data['source']['filters']['rIDList'] = ['order'=> 0, 'hidden'=>true, 'sql'=>"id IN (".implode(',', $cData['data'][$key]['rID']).")"];
        // clean up some filters
        $data['source']['filters']['search']['attr']['value'] = '';
        $data['source']['filters']['period']['attr']['value'] = 'a';
        $data['source']['filters']['period']['sql'] = '';
        $data['source']['filters']['status']['attr']['value'] = 'a';
        $data['source']['filters']['status']['sql'] = '';
        $data['source']['filters']['closed']['attr']['value'] = '0';
        $data['source']['filters']['closed']['sql'] = '';
    }
}