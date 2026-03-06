<?php
/*
 * Bizuno Extension - Training Manager
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
 * @filesource /controllers/quality/training.php
 */

namespace bizuno;

class qualityTraining extends mgrJournal
{
    public    $moduleID  = 'quality';
    public    $pageID    = 'training';
    protected $secID     = 'qa_train';
    protected $domSuffix = 'Training';
    protected $metaPrefix= 'training';
    protected $journalID = 34;
    public    $attachPath;

    public function __construct()
    {
        parent::__construct();
        $this->stores    = getModuleCache('bizuno', 'stores');
        $this->freqs     = getModuleCache('bizuno', 'options', 'frequencies');
        $this->leadTime  = getModuleCache('bizuno', 'options', 'lead_times');
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', 'training');
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                                                         'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'id'        => ['panel'=>'general','order'=> 1,'dbField'=>'id',                                         'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_id'    => ['panel'=>'general','order'=> 1,'dbField'=>'so_po_ref_id',                               'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'dbField'=>'invoice_num', 'label'=>lang('ref_num'),      'clean'=>'filename', 'attr'=>['type'=>'hidden','value'=>'']],
            'title'     => ['panel'=>'general','order'=>10,'dbField'=>'description', 'label'=>lang('title'),        'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'frequency' => ['panel'=>'general','order'=>15,'dbField'=>'recur_id',    'label'=>lang('frequency'),    'clean'=>'char',     'attr'=>['type'=>'select','value'=>'m'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>20,'dbField'=>'method_code', 'label'=>lang('lead_time'),    'clean'=>'alpha_num','attr'=>['type'=>'hidden','value'=>'1w'],'values'=>viewKeyDropdown($this->leadTime)],
            'store_id'  => ['panel'=>'general','order'=>25,'dbField'=>'store_id',    'label'=>lang('store_id'),     'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>30,'dbField'=>'admin_id',    'label'=>lang('user_id'),      'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>getUserCache('profile', 'userID')]],
            'contact_id'=> ['panel'=>'general','order'=>35,'dbField'=>'rep_id',      'label'=>lang('trainer'),      'clean'=>'integer',  'attr'=>['type'=>'select','value'=>0],   'values'=>listUsers()],
            'train_date'=> ['panel'=>'general','order'=>40,'dbField'=>'post_date',   'label'=>lang('date_training'),'clean'=>'date',     'attr'=>['type'=>'date',  'value'=>biz_date()]],
            'doc_link'  => ['panel'=>'general','order'=>50,                          'label'=>lang('doc_link'),     'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'notes'     => ['panel'=>'notes',  'order'=>10,'dbField'=>'notes',                                      'clean'=>'text',     'attr'=>['type'=>'editor','value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[], $admin=false)
    {
        $statuses= array_merge([['id'=>'a','text'=>lang('all')]], getModuleCache('contacts', 'statuses'));
        $data    = array_replace_recursive(parent::gridBase($security, $args, $admin), [
            'source' => [
                'search' => ['ref_num', 'title', 'notes'],
                'filters'=> [
                    'store_id'=> ['order'=>20,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'attr'=> ['type'=>sizeof($this->stores)>1?'select':'hidden','value'=>$this->defaults['store_id']], 'values'=>viewStores()],
                    'status'  => ['order'=>30,'break'=>true,'label'=>lang('status'), 'attr'=>['type'=>'select','value'=>$this->defaults['status']], 'values'=>$statuses]]],
            'columns'=> [
                'ref_num'   => ['order'=>10, 'field'=>'invoice_num','label'=>lang('task_id'),
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'title'     => ['order'=>20, 'field'=>'description','label'=>lang('title'),
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>20, 'field'=>'recur_id',   'label'=>lang('frequency'),'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>25, 'field'=>'recur_id',   'label'=>lang('lead_time'),'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'store_id'  => ['order'=>40, 'field'=>'store_id',   'label'=>lang('store_id'), 'format'=>'storeID',
                    'attr' => ['sortable'=>true, 'resizable'=>true,'hidden'=>sizeof($this->stores)>1?false:true]],
                'contact_id'=> ['order'=>50, 'field'=>'rep_id', 'label'=>lang('trainer', $this->moduleID), 'format'=>'contactID',
                    'attr' => ['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?true:false]],
                'train_date'=> ['order'=>60, 'field'=>'post_date',  'label'=>$admin?sprintf(lang('tbd_next'), lang('date_training')):jsLang('date'), 'format'=>'date',
                    'attr' => ['sortable'=>true,'resizable'=>true, 'type'=>'date']]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>'y'],    'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1],     'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'],    'post');
    }
    /******************************** Journal Entries ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['type'=>'journal', 'title'=>sprintf(lang('tbd_manager'), lang('training'))]);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['new']); // remove the work icon since this is meta only
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";\nvar fmtLeadTime = ".json_encode($this->leadTime).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        $grid  = $this->managerGrid($security, ['type'=>'journal']);
        mapMetaGridToDB($grid, $this->struc);
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function add(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $args = ['desc'=>'Select a task to generate a new training record.'];
        parent::addDB($layout, $security, $args);
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
            $layout['fields']['doc_link']['html'] = lang('process').': <a href="'.$task['doc_link'].'" target="_blank">'.sprintf(lang('tbd_task'), lang('training')).'</a>';
        }
        $layout['fields']['frequency']['attr']['type'] = 'hidden';
        $layout['fields']['train_date']['attr']['value'] = biz_date();
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
