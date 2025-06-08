<?php
/*
 * Method to handle custom tabs for the tables that allow them
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/administrate/tabs.php
 */

namespace bizuno;

class administrateTabs extends mgrJournal
{
    public    $moduleID  = 'administrate';
    public    $pageID    = 'tabs';
    protected $secID     = 'admin';
    protected $domSuffix = 'Tabs';
    protected $metaPrefix= 'tabs';

    function __construct()
    {
        parent::__construct();
        $this->validTables = ['contacts', 'inventory']; // limit which tables can be expanded
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $tables = [];
        foreach ($this->validTables as $table) { $tables[] = ['id'=>$table, 'text'=>lang($table)]; }
        $this->struc = [
            '_rID' => ['panel'=>'general','order'=> 1,                       'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]], // For common_meta
            'table'=> ['panel'=>'general','order'=>10,'label'=>lang('table'),'clean'=>'db_field','attr'=>['type'=>'select'], 'values'=>$tables],
            'title'=> ['panel'=>'general','order'=>20,'label'=>lang('title'),'clean'=>'text',    'attr'=>['value'=>'']],
            'order'=> ['panel'=>'general','order'=>30,'label'=>lang('order'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>50]]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'columns'=> [
                'title' => ['order'=>10,'label'=>lang('title'), 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'order' => ['order'=>20,'label'=>lang('order'), 'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'table' => ['order'=>30,'label'=>lang('table'), 'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'text',    'default'=>'title'], 'post');
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],   'post');
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div']);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::mgrRowsMeta($layout, $security);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // turn off copy
    }
    public function save(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return; }
        parent::saveMeta($layout, []);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout);
    }
}
