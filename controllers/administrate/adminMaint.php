<?php
/*
 * Bizuno Extension - Maintenance Manager - Administration
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
 * @filesource /controllers/administrate/adminMaint.php
 */

namespace bizuno;

class administrateAdminMaint extends mgrJournal
{
    public    $moduleID   = 'administrate';
    public    $pageID     = 'maint';
    protected $secID      = 'mgr_maint';
    protected $domSuffix  = 'Maint';
    protected $metaPrefix = 'maintenance';
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
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                                                     'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]], // For common_meta
            'id'        => ['panel'=>'general','order'=> 1,'dbField'=>'id',                                     'clean'=>'integer',  'attr'=>['type'=>'hidden']], // For journal_main
            'ref_id'    => ['panel'=>'general','order'=> 1,'dbField'=>'so_po_ref_id',                           'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'dbField'=>'invoice_num','label'=>lang('task_id'),   'clean'=>'filename', 'attr'=>['type'=>'hidden','value'=>'']],
            'title'     => ['panel'=>'general','order'=>10,'dbField'=>'description','label'=>lang('title'),     'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'frequency' => ['panel'=>'general','order'=>20,'dbField'=>'recur_id',   'label'=>lang('frequency'), 'clean'=>'char',     'attr'=>['type'=>'hidden','value'=>'m'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>30,'dbField'=>'method_code','label'=>lang('lead_time'), 'clean'=>'alpha_num','attr'=>['type'=>'hidden','value'=>'1w'],'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>40,'dbField'=>'store_id',   'label'=>lang('store_id'),  'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>50,'dbField'=>'admin_id',   'label'=>lang('user_id'),   'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>getUserCache('profile', 'userID')]],
            'contact_id'=> ['panel'=>'general','order'=>60,'dbField'=>'rep_id',     'label'=>lang('maintainer'),'clean'=>'integer',  'attr'=>['type'=>'select','value'=> 0],   'values'=>listUsers()],
            'maint_date'=> ['panel'=>'general','order'=>70,'dbField'=>'post_date',  'label'=>lang('date_maint'),'clean'=>'dateMeta', 'attr'=>['type'=>'date',  'value'=>biz_date()]],
            'doc_link'  => ['panel'=>'general','order'=>50,                         'label'=>lang('doc_link'),  'clean'=>'text',     'attr'=>['type'=>'text']],
            'notes'     => ['panel'=>'notes',  'order'=>10,'dbField'=>'notes',      'label'=>lang('notes'),     'clean'=>'text',     'attr'=>['type'=>'editor','value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[], $admin=false)
    {
        $data = array_replace_recursive(parent::gridBase($security, $args, $admin), [
            'source' => ['search'=>['ref_num', 'title', 'notes']],
            'columns'=> [
                'ref_num'   => ['order'=>10, 'field'=>'invoice_num','label'=>lang('task_id'),      'attr'=>['sortable'=>true, 'resizable'=>true]],
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
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>'y'],'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1], 'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'],'post');
    }

    /******************************** Administration ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_task'), lang('maintenance'))];
        parent::managerMain($layout, $security, $args);
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";\nvar fmtLeadTime = ".json_encode($this->leadTimes).";";
    }
    public function managerRows(&$layout=[])
    {
        $this->defaults['sort'] = clean('sort', ['format'=>'db_field','default'=>'title'],'post'); // change the default entry sorting/order
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
        parent::mgrRowsMeta($layout);
        unset($layout['metagrid']['source']['filters']['period'], $layout['metagrid']['source']['filters']['jID']);
        // customize for page filters
        if ($this->defaults['status']  =='a') { unset($layout['metagrid']['source']['filters']['status']); } // all statuses
        if ($this->defaults['store_id']==-1)  { unset($layout['metagrid']['source']['filters']['store_id']); } // all stores
    }
    public function edit(&$layout=[])
    {
        parent::editMeta($layout);
        $layout['fields']['train_date']['label']       = sprintf(lang('tbd_next'), lang('date_training'));
        $layout['fields']['frequency']['attr']['type'] = 'select';
        $layout['fields']['lead_time']['attr']['type'] = 'select';
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
