<?php
/*
 * Bizuno Extension - Maintenance Manager [tasks - common_meta, activity - journal_main]
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
 * @filesource /controllers/administrate/maint.php
 */

namespace bizuno;

class administrateMaint extends mgrJournal
{
    public    $moduleID   = 'administrate';
    public    $pageID     = 'maint';
    protected $secID      = 'mgr_maint';
    protected $domSuffix  = 'Maint';
    protected $metaPrefix = 'maintenance';
    protected $nextRefIdx = 'next_maint_num';
    protected $journalID  = 35;
    public    $stores;
    public    $freqs;
    public    $leadTimes;
    public    $attachPath;

    public function __construct()
    {
        parent::__construct();
        $this->stores    = getModuleCache('bizuno', 'stores');
        $this->freqs     = getModuleCache('bizuno', 'options', 'frequencies');
        $this->leadTimes = getModuleCache('bizuno', 'options', 'lead_times');
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure and sets the defaults
     * @return array - page structure
     */
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                                                     'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]], // For common_meta
            'id'        => ['panel'=>'general','order'=> 1,'dbField'=>'id',                                     'clean'=>'integer',  'attr'=>['type'=>'hidden']], // For journal_main
            'ref_id'    => ['panel'=>'general','order'=> 1,'dbField'=>'so_po_ref_id',                           'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'dbField'=>'invoice_num','label'=>lang('task_num'),  'clean'=>'filename', 'attr'=>['type'=>'hidden','value'=>'']],
            'title'     => ['panel'=>'general','order'=>10,'dbField'=>'description','label'=>lang('title'),     'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'frequency' => ['panel'=>'general','order'=>20,'dbField'=>'recur_id',   'label'=>lang('frequency'), 'clean'=>'char',     'attr'=>['type'=>'hidden','value'=>'m'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>30,'dbField'=>'method_code','label'=>lang('lead_time'), 'clean'=>'alpha_num','attr'=>['type'=>'hidden','value'=>'1w'],'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>40,'dbField'=>'store_id',   'label'=>lang('store_id'),  'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>50,'dbField'=>'admin_id',   'label'=>lang('user_id'),   'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>getUserCache('profile', 'userID')]],
            'contact_id'=> ['panel'=>'general','order'=>60,'dbField'=>'rep_id',     'label'=>lang('maintainer'),'clean'=>'integer',  'attr'=>['type'=>'select','value'=> 0],   'values'=>listUsers()],
            'maint_date'=> ['panel'=>'general','order'=>70,'dbField'=>'post_date',  'label'=>lang('date_maint'),'clean'=>'dateMeta', 'attr'=>['type'=>'date',  'value'=>biz_date()]],
            'doc_link'  => ['panel'=>'general','order'=>50,                         'label'=>lang('doc_link'),  'clean'=>'text',     'attr'=>['type'=>'text']],
            'notes'     => ['panel'=>'maint_notes','order'=>10,'dbField'=>'notes',                              'clean'=>'text',     'attr'=>['type'=>'editor','value'=>'']]];
    }

    /**
     * This function builds the grid structure for retrieving data from the db
     * @param integer $security - access level range 0-4
     * @param array $args - Grid dependent arguments
     * @param boolean $admin - True if located in admin section, false if standalone page or partial div
     * @return array $data - structure of the grid to render
    */
    protected function managerGrid($security=0, $args=[], $admin=false)
    {
        $data = array_replace_recursive(parent::gridBase($security, $args, $admin), [
            'source' => ['search'=>['ref_num', 'title', 'notes']],
            'columns'=> [
                'ref_num'   => ['order'=>10, 'field'=>'invoice_num','label'=>$this->lang['ref_num'],'attr'=>['sortable'=>true, 'resizable'=>true]],
                'maint_date'=> ['order'=>15, 'field'=>'post_date',  'label'=>$admin?sprintf(lang('tbd_next'), lang('maintenance')):lang('date_maint'), 'attr'=>['type'=>'date','sortable'=>true,'resizable'=>true], 'format'=>'date'],
                'title'     => ['order'=>20, 'field'=>'description','label'=>lang('title'),         'attr'=>['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>30, 'field'=>'recur_id',   'label'=>lang('frequency'),     'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>40, 'field'=>'purch_order_id','label'=>lang('lead_time'),  'attr'=>['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?false:true],
                    'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'store_id'  => ['order'=>50, 'field'=>'store_id',   'label'=>lang('store_id'), 'format'=>'storeID',
                    'attr'  => ['sortable'=>true, 'resizable'=>true,'hidden'=>sizeof(getModuleCache('bizuno', 'stores'))>1?false:true]],
                'contact_id'=> ['order'=>60, 'field'=>'rep_id',     'label'=>lang('maintainer'),'format'=>'contactID',
                    'attr'  => ['sortable'=>true, 'resizable'=>true,'hidden'=>$admin?true:false]],
                'maint_date'=> ['order'=>70, 'field'=>'post_date',  'label'=>$admin?$this->lang['next_maint_date']:lang('date'), 'format'=>'date',
                    'attr'  => ['sortable'=>true,'resizable'=>true, 'type'=>'date']]]]);
        return $data;
    }

    /**
     * Sets the users preferences
     */
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>'y'],'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1], 'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'],'post');
    }

    /******************************** Journal Entries ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['type'=>'journal', 'work'=>true, 'title'=>sprintf(lang('tbd_manager'), lang('maintenance'))]);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['new']); // remove the work icon since this is meta only
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";\nvar fmtLeadTime = ".json_encode($this->leadTimes).";";
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
        $args = ['desc'=>'Select a task to generate a new maintenance record.'];
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
            $layout['fields']['doc_link']['html'] = lang('process').': <a href="'.$task['doc_link'].'" target="_blank">'.sprintf(lang('tbd_task'), lang('audit')).'</a>';
        }
        $layout['fields']['maint_date']['attr']['value'] = biz_date();
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow new, copy here
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
