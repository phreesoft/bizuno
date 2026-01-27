<?php
/*
 * @name Bizuno ERP - Work Order (Manufacturing) Extension
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
 * @version    7.x Last Update: 2025-06-03
 * @filesource /controllers/inventory/tasks.php
 */

namespace bizuno;

class inventoryTasks extends mgrJournal
{
    public    $moduleID  = 'inventory';
    public    $pageID    = 'tasks';
    protected $secID     = 'woTasks';
    protected $domSuffix = 'Tasks';
    protected $metaPrefix= 'production_task';

    function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            'title'      => ['panel'=>'general','order'=>10,'label'=>lang('title'),             'clean'=>'text',   'attr'=>['type'=>'text']],
            'description'=> ['panel'=>'general','order'=>15,'label'=>lang('description'),       'clean'=>'text',   'attr'=>['type'=>'textarea']],
            'ref_doc'    => ['panel'=>'general','order'=>20,'label'=>$this->lang['ref_doc'],    'clean'=>'text',   'attr'=>['type'=>'text']],
            'ref_spec'   => ['panel'=>'general','order'=>25,'label'=>$this->lang['ref_spec'],   'clean'=>'text',   'attr'=>['type'=>'text']],
            'dept_id'    => ['panel'=>'general','order'=>30,'label'=>$this->lang['dept_id'],    'clean'=>'integer','attr'=>['type'=>'select'], 'values'=>viewRoleDropdown('mfg')],
            'mfg'        => ['panel'=>'general','order'=>35,'label'=>$this->lang['mfg_signoff'],'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>1]],
            'qa'         => ['panel'=>'general','order'=>40,'label'=>$this->lang['qa_signoff'], 'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0]],
            'data_entry' => ['panel'=>'general','order'=>45,'label'=>$this->lang['data_value'], 'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0]],
            'erp_entry'  => ['panel'=>'general','order'=>50,'label'=>$this->lang['erp_entry'],  'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0]]];
    }
    protected function managerGrid($security, $args=[])
    {
        msgDebug("\nEntering managerGrid with args = ".print_r($args, true));
        $data = array_replace_recursive(parent::gridBase($security), [
            'source' => ['search'=>['title', 'description']],
            'columns'=> [
                'title'      => ['order'=>10,'label'=>lang('title'),          'attr'=>['resizable'=>true,'sortable'=>true]],
                'description'=> ['order'=>20,'label'=>lang('description'),    'attr'=>['resizable'=>true,'sortable'=>true]],
                'ref_doc'    => ['order'=>30,'label'=>$this->lang['ref_doc'], 'attr'=>['resizable'=>true]],
                'ref_spec'   => ['order'=>40,'label'=>$this->lang['ref_spec'],'attr'=>['resizable'=>true]]]]);
        return $data;
    }
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd',     'default'=>'title'],'post');
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
        $rows = clean('rows', 'integer', 'get');
        if (!empty($rows)) { $this->defaults['rows'] = $rows; }
        
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_tasks'), lang('work_order'))];
        parent::managerMain($layout, $security, $args);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        return parent::mgrRowsMeta($layout, $security);
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::editMeta($layout, $security);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']);
        if (empty($rID)) { unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new']); }
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::copyMeta($layout);
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveMeta($layout);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout);
    }
}
