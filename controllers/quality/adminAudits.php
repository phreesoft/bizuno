<?php
/*
 * Bizuno Extension - Quality Audit Manager - Administration
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
 * @filesource /controllers/quality/adminAudits.php
 */

namespace bizuno;

class qualityAdminAudits extends mgrJournal
{
    public    $moduleID  = 'quality';
    public    $pageID    = 'adminAudits';
    protected $secID     = 'qa_audit';
    protected $domSuffix = 'Audits';
    protected $metaPrefix= 'quality_audit';
    protected $nextRefIdx= 'next_audit_num';
    public    $stores;
    public    $freqs;
    public    $qual_status;
    public    $leadTimes;

    function __construct()
    {
        parent::__construct();
        $this->mgrTitle   = sprintf(lang('tbd_manager'), lang('audit', $this->moduleID));
        $this->stores     = getModuleCache('bizuno', 'stores');
        $this->freqs      = getModuleCache('bizuno', 'options', 'frequencies');
        $this->qual_status= getModuleCache('bizuno', 'options', 'qa_status');
        $this->leadTimes  = getModuleCache('bizuno', 'options', 'lead_times');
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
            '_rID'      => ['panel'=>'general','order'=> 1,                            'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]], // For common_meta
            'id'        => ['panel'=>'general','order'=> 1,                            'clean'=>'integer',  'attr'=>['type'=>'hidden']], // For journal_main
            'ref_id'    => ['panel'=>'general','order'=> 1,                            'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'label'=>lang('task_id'),   'clean'=>'db_field', 'attr'=>['type'=>'hidden']],
            'title'     => ['panel'=>'general','order'=>10,'label'=>lang('title'),     'clean'=>'text'],
            'due_date'  => ['panel'=>'general','order'=>15,'label'=>lang('date_audit'),'clean'=>'date',     'attr'=>['type'=>'date']],
            'status'    => ['panel'=>'general','order'=>20,'label'=>lang('status'),    'clean'=>'integer',  'attr'=>['type'=>'select'], 'values'=>viewKeyDropdown($this->qual_status, true)],
            'closed'    => ['panel'=>'general','order'=>25,'label'=>lang('closed'),    'clean'=>'bolean',   'attr'=>['type'=>'selNoYes','value'=> 0]],
            'inactive'  => ['panel'=>'general','order'=>30,'label'=>lang('inactive'),  'clean'=>'char',     'attr'=>['type'=>'selNoYes','value'=>0]],
            'frequency' => ['panel'=>'general','order'=>35,'label'=>lang('frequency'), 'clean'=>'char',     'attr'=>['type'=>'select'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>40,'label'=>lang('lead_time'), 'clean'=>'alpha_num','attr'=>['type'=>'hidden'], 'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>45,'label'=>lang('store_id'),  'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden'], 'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>50,'label'=>lang('user_id'),   'clean'=>'integer',  'attr'=>['type'=>'hidden']],
            'contact_id'=> ['panel'=>'general','order'=>55,'label'=>lang('auditor'),   'clean'=>'integer',  'attr'=>['type'=>'select'], 'values'=>listUsers()],
            'doc_link'  => ['panel'=>'general','order'=>60,'label'=>lang('doc_link'),  'clean'=>'text',     'attr'=>['type'=>'text']],
            'notes'     => ['panel'=>'notes',  'order'=>50,                            'clean'=>'text',     'attr'=>['type'=>'editor']]];
    }

    /**
     * This function builds and returns the data grid structure for retrieving data from the db
    */
    protected function managerGrid($security=0, $args=[])
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
        $data     = array_replace_recursive(parent::gridBase($security, $args), [
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
                'inactive'  => ['order'=> 0,                                  'attr' =>['hidden'=>true]],
                'closed'    => ['order'=> 0,                                  'attr' =>['hidden'=>true]],
                'ref_num'   => ['order'=>10, 'label'=>lang('task_id'),        'attr'=>['sortable'=>true, 'resizable'=>true]],
                'due_date'  => ['order'=>15, 'label'=>sprintf(lang('tbd_next'), lang('audit')), 'attr'=>['type'=>'date','sortable'=>true,'resizable'=>true], 'format'=>'date'],
                'title'     => ['order'=>20, 'label'=>lang('title'),          'attr'=>['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>20, 'label'=>lang('frequency'),      'attr'=>['sortable'=>true, 'resizable'=>true],
                    'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>25, 'label'=>lang('lead_time'),      'attr'=>['sortable'=>true, 'resizable'=>true],
                    'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'store_id'  => ['order'=>30, 'label'=>lang('store_id'),       'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>sizeof($this->stores)>1?false:true], 'format'=>'storeID'],
                'contact_id'=> ['order'=>35, 'label'=>lang('auditor'),        'attr'=>['sortable'=>true, 'resizable'=>true], 'format'=>'contactID'],
                'status'    => ['order'=>40, 'label'=>lang('status'),         'attr'=>['sortable'=>true, 'resizable'=>true],
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
        $this->defaults['f0']      = clean('f0',      ['format'=>'char',    'default'=>'a'], 'post');
        $this->defaults['f1']      = clean('f1',      ['format'=>'db_field', 'default'=>''], 'post');
    }

    /******************************** Administration ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $tasks = dbMetaGet('%', $this->metaPrefix);
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_tasks'), lang('audit'))];
        parent::managerMain($layout, $security, $args);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['filters']['period'], $layout['datagrid']["dg{$this->domSuffix}"]['source']['filters']['jID']);
        $layout['jsHead'][$this->pageID] = "var fmtFreqs = ".json_encode($this->freqs)."; var fmtLeadTime = ".json_encode($this->leadTimes)."; var bizQualStatuses = ".json_encode($this->qual_status).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $this->defaults['sort'] = clean('sort', ['format'=>'db_field','default'=>'title'],'post'); // change the default entry sorting/order
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
        parent::mgrRowsMeta($layout, $security);
        unset($layout['metagrid']['source']['filters']['period'], $layout['metagrid']['source']['filters']['jID']);
        // customize for page filters
        if ($this->defaults['status']  =='a') { unset($layout['metagrid']['source']['filters']['status']); } // all statuses
        if ($this->defaults['closed']  =='a') { unset($layout['metagrid']['source']['filters']['closed']); } // all statuses
        if ($this->defaults['store_id']==-1)  { unset($layout['metagrid']['source']['filters']['store_id']); } // all stores
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        $layout['fields']['due_date']['label'] = sprintf(lang('tbd_next'), lang('audit'));
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
    }
    public function copy(&$layout=[])
    {
        parent::copyMeta($layout);
    }
    public function save(&$layout=[])
    {
        parent::saveMeta($layout);
    }
    public function delete(&$layout=[])
    {
        parent::deleteMeta($layout);
    }
}
