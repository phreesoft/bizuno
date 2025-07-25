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
 * @version    7.x Last Update: 2025-07-25
 * @filesource /controllers/administrate/adminMaint.php
 */

namespace bizuno;

class administrateAdminMaint extends mgrJournal
{
    public    $moduleID   = 'administrate';
    public    $pageID     = 'adminMaint';
    protected $secID      = 'mgr_maint';
    protected $domSuffix  = 'Maint';
    protected $metaPrefix = 'maintenance';
    public    $stores;
    protected $roles;
    public    $freqs;
    public    $leadTimes;
    public    $attachPath;
    public    $defaults;

    public function __construct()
    {
        parent::__construct();
        $this->stores    = getModuleCache('bizuno', 'stores');
        $this->roles     = listRoles();
        $this->freqs     = getModuleCache('bizuno', 'options', 'frequencies');
        $this->leadTimes = getModuleCache('bizuno', 'options', 'lead_times');
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                            'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]], // For common_meta
            'ref_id'    => ['panel'=>'general','order'=> 1,                            'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1,'label'=>lang('task_id'),   'clean'=>'filename', 'attr'=>['type'=>'hidden','value'=>'']],
            'title'     => ['panel'=>'general','order'=>10,'label'=>lang('title'),     'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'frequency' => ['panel'=>'general','order'=>20,'label'=>lang('frequency'), 'clean'=>'char',     'attr'=>['type'=>'select','value'=>'m'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>30,'label'=>lang('lead_time'), 'clean'=>'alpha_num','attr'=>['type'=>'select','value'=>'1w'],'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>40,'label'=>lang('store_id'),  'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>50,'label'=>lang('user_id'),   'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>getUserCache('profile', 'userID')]],
            'role_id'   => ['panel'=>'general','order'=>60,'label'=>lang('role'),      'clean'=>'integer',  'attr'=>['type'=>'select','value'=>-1], 'values'=>$this->roles],
            'maint_date'=> ['panel'=>'general','order'=>80,'label'=>sprintf(lang('tbd_next'), lang('maintenance')),'clean'=>'dateMeta', 'attr'=>['type'=>'date', 'value'=>biz_date()]],
            'doc_link'  => ['panel'=>'general','order'=>90,'label'=>lang('doc_link'),  'clean'=>'text',     'attr'=>['type'=>'text']],
            'notes'     => ['panel'=>'notes',  'order'=>10,'label'=>lang('notes'),     'clean'=>'text',     'attr'=>['type'=>'editor','value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search'=>['ref_num', 'title', 'notes'],
                'filters'=> [
                    'store_id'=>['order'=>10,'label'=>lang('ctype_b'),'attr' =>['type'=>sizeof($this->stores)>1?'select':'hidden','value'=>$this->defaults['store_id']], 'values'=>viewStores()]]],
            'columns'=> [
                'ref_num'   => ['order'=>10, 'label'=>lang('task_id'),   'attr'=>['sortable'=>true, 'resizable'=>true]],
                'maint_date'=> ['order'=>15, 'label'=>sprintf(lang('tbd_next'), lang('maintenance')), 'attr'=>['type'=>'date','sortable'=>true,'resizable'=>true], 'format'=>'date'],
                'title'     => ['order'=>20, 'label'=>lang('title'),     'attr'=>['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>30, 'label'=>lang('frequency'), 'attr'=>['sortable'=>true, 'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>40, 'label'=>lang('lead_time'), 'attr'=>['sortable'=>true, 'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'role_id'   => ['order'=>50, 'label'=>lang('role'),      'attr'=>['sortable'=>true, 'resizable'=>true],'events'=>['formatter'=>"function(value,row) { return fmtRoles[value]; }"]],
                'store_id'  => ['order'=>60, 'label'=>lang('store_id'),  'attr'=>['sortable'=>true, 'resizable'=>true, 'hidden'=>sizeof(getModuleCache('bizuno', 'stores'))>1?false:true], 'format'=>'storeID'],
                'maint_date'=> ['order'=>70, 'label'=>sprintf(lang('tbd_next'), lang('maintenance')), 'attr'=>['sortable'=>true,'resizable'=>true, 'type'=>'date'], 'format'=>'date']]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1], 'post');
    }

    /******************************** Administration ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_task'), lang('maintenance'))];
        parent::managerMain($layout, $security, $args);
        $roles = [];
        foreach ($this->roles as $role) { $roles[$role['id']] = $role['text']; }
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";\nvar fmtLeadTime = ".json_encode($this->leadTimes).";\nvar fmtRoles = ".json_encode($roles).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $this->defaults['sort'] = clean('sort', ['format'=>'db_field','default'=>'title'],'post'); // change the default entry sorting/order
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
        parent::mgrRowsMeta($layout, $security, []);
        // customize for page filters
        if ($this->defaults['store_id']==-1)  { unset($layout['metagrid']['source']['filters']['store_id']); } // all stores
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess('admin', 2)) { return; }
        parent::editMeta($layout, $security);
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess('admin', 2)) { return; }
        parent::copyMeta($layout);
    }
    public function save(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        parent::saveMeta($layout);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess('admin', 4)) { return; }
        parent::deleteMeta($layout);
    }
}
