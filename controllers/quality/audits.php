<?php
/*
 * Bizuno Extension - Quality Audit Manager [tasks - common_meta, activity - journal_main]
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
 * @version    7.x Last Update: 2025-10-24
 * @filesource /controllers/quality/audits.php
 */

namespace bizuno;

class qualityAudits extends mgrJournal
{
    public    $moduleID  = 'quality';
    public    $pageID    = 'audits';
    protected $secID     = 'qa_audit';
    protected $domSuffix = 'Audits';
    protected $metaPrefix= 'quality_audit';
    protected $journalID = 31;
    private   $attachPath;

    function __construct()
    {
        parent::__construct();
        $this->mgrTitle   = sprintf(lang('tbd_manager'), $this->lang[$this->pageID]);
        $this->stores     = getModuleCache('bizuno', 'stores');
        $this->freqs      = getModuleCache('bizuno', 'options', 'frequencies');
        $this->qual_status= getModuleCache('bizuno', 'options', 'qa_status');
        $this->leadTimes  = getModuleCache('bizuno', 'options', 'lead_times');
        $this->attachPath = getModuleCache($this->moduleID, 'properties', 'attachPath', 'audits');
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                                                     'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]], // For common_meta
            'id'        => ['panel'=>'general','order'=> 1,'dbField'=>'id',                                     'clean'=>'integer',  'attr'=>['type'=>'hidden']], // For journal_main
            'ref_id'    => ['panel'=>'general','order'=> 1,'dbField'=>'so_po_ref_id',                           'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'dbField'=>'invoice_num','label'=>lang('task_id'),   'clean'=>'db_field', 'attr'=>['type'=>'text',    'readonly'=>true]],
            'title'     => ['panel'=>'general','order'=>10,'dbField'=>'description','label'=>lang('title'),     'clean'=>'text'],
            'due_date'  => ['panel'=>'general','order'=>15,'dbField'=>'post_date',  'label'=>lang('date_audit'),'clean'=>'date',     'attr'=>['type'=>'date']],
            'status'    => ['panel'=>'general','order'=>20,'dbField'=>'printed',    'label'=>lang('status'),    'clean'=>'integer',  'attr'=>['type'=>'select'], 'values'=>viewKeyDropdown($this->qual_status, true)],
            'closed'    => ['panel'=>'general','order'=>25,'dbField'=>'closed',     'label'=>lang('closed'),    'clean'=>'bolean',   'attr'=>['type'=>'selNoYes','value'=> 0]],
            'inactive'  => ['panel'=>'general','order'=>30,                         'label'=>lang('inactive'),  'clean'=>'char',     'attr'=>['type'=>'selNoYes','value'=>0]],
            'frequency' => ['panel'=>'general','order'=>35,'dbField'=>'recur_id',   'label'=>lang('frequency'), 'clean'=>'char',     'attr'=>['type'=>'select'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>40,'dbField'=>'method_code','label'=>lang('lead_time'), 'clean'=>'alpha_num','attr'=>['type'=>'hidden'], 'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>45,'dbField'=>'store_id',   'label'=>lang('store_id'),  'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden'], 'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>50,'dbField'=>'admin_id',   'label'=>lang('user_id'),   'clean'=>'integer',  'attr'=>['type'=>'hidden']],
            'contact_id'=> ['panel'=>'general','order'=>55,'dbField'=>'rep_id',     'label'=>lang('auditor'),   'clean'=>'integer',  'attr'=>['type'=>'select'], 'values'=>listUsers()],
            'doc_link'  => ['panel'=>'general','order'=>60,                         'label'=>lang('doc_link'),  'clean'=>'text',     'attr'=>['type'=>'text']],
            'notes'     => ['panel'=>'audit_notes','order'=>50,'dbField'=>'notes',                              'clean'=>'text',     'attr'=>['type'=>'editor']]];
    }

    /**
     * This function builds and returns the data grid structure for retrieving data from the db
    */
    protected function managerGrid($security=0, $args=[], $admin=false)
    {
        $statuses = array_merge([['id'=>'a','text'=>lang('all')]], viewKeyDropdown(getModuleCache('bizuno', 'options', 'qa_status')));
        $dateRange= dbSqlDates($this->defaults['period']);
        $sqlPeriod= $dateRange['sql'];
        $selClosed= [['id'=>'a','text'=>lang('all')], ['id'=>'1','text'=>lang('yes')], ['id'=>'0','text'=>lang('no')]];
        // clean up the filter sqls
        switch ($this->defaults['closed']) {
            default:
            case 'a': $f0_value = "";           break;
            case '1': $f0_value = "closed='1'"; break;
            case '0': $f0_value = "closed='0'"; break;
        }
        $f1 = clean('status', 'db_field', 'post');
        $f1_value = $f1 ? "printed='$f1'" : "";
        $data     = array_replace_recursive(parent::gridBase($security, $args, $admin), [
            'source' => [
                'search' => ['ref_num', 'title', 'notes'],
                'filters'=> [
                    'period'  => ['order'=>10,'label'=>lang('period'), 'options'=>['width'=>300],'sql'=>$sqlPeriod,
                        'values'=>viewKeyDropdown(localeDates(true, true, true, true)),'attr'=>['type'=>'select','value'=>$this->defaults['period']]],
                    'store_id'  => ['order'=>20,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'attr' =>['type'=>sizeof($this->stores)>1?'select':'hidden','value'=>$this->defaults['store_id']], 'values'=>viewStores()],
                    'closed' => ['order'=>30,'break'=>true,'label' =>lang('closed'),'sql'=>$f0_value,'attr'=>['type'=>'select','value'=>$this->defaults['f0']],'values'=>$selClosed],
                    'status' => ['order'=>40,'break'=>true,'label' =>lang('status'),'sql'=>$f1_value,'attr'=>['type'=>'select','value'=>$this->defaults['f1']],'values'=>$statuses]],
                'sort'   => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'=> [
                'inactive'  => ['order'=> 0, 'attr' =>['hidden'=>true]],
                'closed'    => ['order'=> 0, 'attr' =>['hidden'=>true]],
                'ref_num'   => ['order'=>10, 'field'=>'invoice_num','label'=>$this->lang['task_num'],'attr'=>['sortable'=>true, 'resizable'=>true]],
                'due_date'  => ['order'=>15, 'field'=>'post_date',  'label'=>$admin?sprintf(lang('tbd_next'), lang('audit')):lang('date_audit'), 'attr'=>['type'=>'date','sortable'=>true,'resizable'=>true], 'format'=>'date'],
                'title'     => ['order'=>20, 'field'=>'description','label'=>lang('title'),          'attr'=>['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>20, 'field'=>'recur_id',   'label'=>lang('frequency'),      'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>25, 'field'=>'recur_id',   'label'=>lang('lead_time'),      'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'store_id'  => ['order'=>30, 'field'=>'store_id',   'label'=>lang('store_id'),       'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>sizeof($this->stores)>1?false:true], 'format'=>'storeID'],
                'contact_id'=> ['order'=>35, 'field'=>'rep_id',     'label'=>lang('auditor'),        'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true], 'format'=>'contactID'],
                'status'    => ['order'=>40, 'field'=>'printed',    'label'=>lang('status'),         'attr'=>['sortable'=>true, 'resizable'=>true],
                    'events' => ['formatter'=>"function(value,row) { return bizQualStatuses[value]; }"]]]]);
        return $data;
    }

    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>'y'], 'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1],  'post');
        $this->defaults['closed']  = clean('closed',  ['format'=>'char',    'default'=>'a'], 'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'], 'post');
    }

    /******************************** Journal Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['type'=>'journal', 'title'=>sprintf(lang('tbd_manager'), lang('audit'))]);
        $layout['jsHead'][$this->pageID] = "var fmtFreqs = ".json_encode($this->freqs)."; var fmtLeadTime = ".json_encode($this->leadTimes)."; var bizQualStatuses = ".json_encode($this->qual_status).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        $grid= $this->managerGrid($security, ['type'=>'journal']);
        mapMetaGridToDB($grid, $this->struc);
        // customize for page filters
        if ($this->defaults['period']  =='a') { unset($grid['source']['filters']['period']); } // all periods
        if ($this->defaults['closed']  =='a') { unset($grid['source']['filters']['closed']); }
        if ($this->defaults['status']  =='a') { unset($grid['source']['filters']['status']); } // all statuses
        if ($this->defaults['store_id']==-1)  { unset($grid['source']['filters']['store_id']); } // all stores
        // finalize output
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function add(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::addDB($layout);
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editDB($layout, $security, $args=['rID'=>$rID]);
        $taskID = $layout['fields']['ref_id']['attr']['value'];
        $task   = !empty($taskID) ? dbMetaGet($taskID, $this->metaPrefix) : 0; // fetch the link to the tasks docs
        if (!empty($task)) {
            $layout['fields']['doc_link']['attr']['type'] = 'raw';
            $layout['fields']['doc_link']['html'] = lang('process').': <a href="'.$task['doc_link'].'" target="_blank">'.sprintf(lang('tbd_task'), lang('audit')).'</a>';
        }
        $layout['fields']['frequency']['attr']['type'] = 'hidden';
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
        // add the attachment panel
        $layout['divs']['content']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
    }
    public function save(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveDB($layout);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteDB($layout);
    }
}
