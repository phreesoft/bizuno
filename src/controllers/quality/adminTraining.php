<?php
/*
 * Bizuno Extension - Training Manager - Administration
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
 * @filesource /controllers/quality/adminTraining.php
 */

namespace bizuno;

class qualityAdminTraining extends mgrJournal
{
    public    $moduleID  = 'quality';
    public    $pageID    = 'adminTraining';
    protected $secID     = 'admin';
    protected $domSuffix = 'Training';
    protected $metaPrefix= 'training';
    protected $nextRefIdx= 'next_training_num';
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
            '_rID'      => ['panel'=>'general','order'=> 1,                                'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'id'        => ['panel'=>'general','order'=> 1,                                'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_id'    => ['panel'=>'general','order'=> 1,                                'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>0]],
            'ref_num'   => ['panel'=>'general','order'=> 1, 'label'=>lang('ref_num'),      'clean'=>'filename', 'attr'=>['type'=>'hidden','value'=>'']],
            'title'     => ['panel'=>'general','order'=>10, 'label'=>lang('title'),        'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'frequency' => ['panel'=>'general','order'=>15, 'label'=>lang('frequency'),    'clean'=>'char',     'attr'=>['type'=>'select','value'=>'m'], 'values'=>viewKeyDropdown($this->freqs)],
            'lead_time' => ['panel'=>'general','order'=>20, 'label'=>lang('lead_time'),    'clean'=>'alpha_num','attr'=>['type'=>'hidden','value'=>'1w'],'values'=>viewKeyDropdown($this->leadTimes)],
            'store_id'  => ['panel'=>'general','order'=>25, 'label'=>lang('store_id'),     'clean'=>'integer',  'attr'=>['type'=>sizeof($this->stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'user_id'   => ['panel'=>'general','order'=>30, 'label'=>lang('user_id'),      'clean'=>'integer',  'attr'=>['type'=>'hidden','value'=>getUserCache('profile', 'userID')]],
            'contact_id'=> ['panel'=>'general','order'=>35, 'label'=>lang('trainer'),      'clean'=>'integer',  'attr'=>['type'=>'select','value'=>0],   'values'=>listUsers()],
            'train_date'=> ['panel'=>'general','order'=>40, 'label'=>lang('date_training'),'clean'=>'date',     'attr'=>['type'=>'date',  'value'=>biz_date()]],
            'doc_link'  => ['panel'=>'general','order'=>50, 'label'=>lang('doc_link'),     'clean'=>'text',     'attr'=>['type'=>'text',  'value'=>'']],
            'notes'     => ['panel'=>'notes',  'order'=>10,                                'clean'=>'text',     'attr'=>['type'=>'editor','value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $statuses= array_merge([['id'=>'a','text'=>lang('all')]], getModuleCache('contacts', 'statuses'));
        $data    = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search' => ['ref_num', 'title', 'notes'],
                'filters'=> [
                    'store_id'=> ['order'=>20,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'attr'=> ['type'=>sizeof($this->stores)>1?'select':'hidden','value'=>$this->defaults['store_id']], 'values'=>viewStores()],
                    'status'  => ['order'=>30,'break'=>true,'label'=>lang('status'), 'attr'=>['type'=>'select','value'=>$this->defaults['status']], 'values'=>$statuses]]],
            'columns'=> [
                'ref_num'   => ['order'=>10, 'label'=>lang('task_id'),  'attr'=>['sortable'=>true, 'resizable'=>true]],
                'title'     => ['order'=>20, 'label'=>lang('title'),    'attr'=>['sortable'=>true, 'resizable'=>true]],
                'frequency' => ['order'=>25, 'label'=>lang('frequency'),'attr'=>['sortable'=>true, 'resizable'=>true],
                    'events'=>['formatter'=>"function(value,row) { return fmtFreqs[value]; }"]],
                'lead_time' => ['order'=>30, 'label'=>lang('lead_time'),'attr'=>['sortable'=>true, 'resizable'=>true],
                    'events'=>['formatter'=>"function(value,row) { return fmtLeadTime[value]; }"]],
                'store_id'  => ['order'=>40, 'label'=>lang('store_id'), 'format'=>'storeID',
                    'attr' => ['sortable'=>true, 'resizable'=>true,'hidden'=>sizeof($this->stores)>1?false:true]],
                'contact_id'=> ['order'=>50, 'label'=>$this->lang['trainer'], 'format'=>'contactID',
                    'attr' => ['sortable'=>true, 'resizable'=>true]],
                'train_date'=> ['order'=>60, 'label'=>sprintf(lang('tbd_next'), lang('date_training')), 'format'=>'date',
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

    /******************************** Administration ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $tasks = dbMetaGet('%', $this->metaPrefix);
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_tasks'), lang('training'))];
        parent::managerMain($layout, $security, $args);
        $layout['jsHead']['init'] = "var fmtFreqs = ".json_encode($this->freqs).";\nvar fmtLeadTime = ".json_encode($this->leadTimes).";";
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
        if ($this->defaults['store_id']==-1)  { unset($layout['metagrid']['source']['filters']['store_id']); } // all stores
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        $layout['fields']['train_date']['label']       = sprintf(lang('tbd_next'), lang('date_training'));
        $layout['fields']['frequency']['attr']['type'] = 'select';
        $layout['fields']['lead_time']['attr']['type'] = 'select';
        msgDebug("\nReturning from edit with layout = ".print_r($layout, true));
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
