<?php
/*
 * Bizuno Extension - Quality Objectives Manager [Meta only]
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
 * @version    7.x Last Update: 2025-06-14
 * @filesource /controllers/quality/objectives.php
 */

namespace bizuno;

class qualityObjectives extends mgrJournal
{
    public    $moduleID  = 'quality';
    public    $pageID    = 'objectives';
    protected $secID     = 'qa_obj';
    protected $domSuffix = 'Objectives';
    protected $metaPrefix= 'quality_objective';
    protected $nextRefIdx= 'next_qaobj_num';

    function __construct()
    {
        parent::__construct();
        $this->status= getModuleCache('bizuno', 'options', 'qa_status');
        $this->reps  = listUsers();
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
            '_rID'       => ['panel'=>'general',  'order'=> 1,                             'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=> 0]],
            'dgObjData'  => ['panel'=>'general',  'order'=> 1,                             'clean'=>'json',     'attr'=>['type'=>'hidden',  'value'=> 0]],
            'ref_num'    => ['panel'=>'general',  'order'=> 1,'label'=>lang('task_id'),    'clean'=>'alpha_num','attr'=>['type'=>'text',    'value'=>'']],
            'title'      => ['panel'=>'general',  'order'=>10,'label'=>lang('title'),      'clean'=>'text',     'attr'=>['type'=>'text',    'value'=>'']],
            'closed'     => ['panel'=>'general',  'order'=>20,'label'=>lang('closed'),     'clean'=>'char',     'attr'=>['type'=>'selNoYes','value'=> 0]],
            'status'     => ['panel'=>'general',  'order'=>30,'label'=>lang('status'),     'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>''], 'values'=>viewKeyDropdown($this->status)],
            'entered_by' => ['panel'=>'general',  'order'=>40,'label'=>lang('entered_by'), 'clean'=>'integer',  'attr'=>['type'=>'select',  'value'=> 0], 'values'=>listUsers()],
            'date_target'=> ['panel'=>'general',  'order'=>50,'label'=>lang('date_target'),'clean'=>'date',     'attr'=>['type'=>'date',    'value'=>'']],
            'date_actual'=> ['panel'=>'general',  'order'=>60,'label'=>lang('date_actual'),'clean'=>'date',     'attr'=>['type'=>'date',    'value'=>'']],
            'obj_desc'   => ['panel'=>'objective','order'=>10,                             'clean'=>'text',     'attr'=>['type'=>'editor',  'value'=>'']],
            'obj_test'   => ['panel'=>'testing',  'order'=>10,                             'clean'=>'text',     'attr'=>['type'=>'editor',  'value'=>'']],
            'obj_result' => ['panel'=>'result',   'order'=>10,                             'clean'=>'text',     'attr'=>['type'=>'editor',  'value'=>'']],
            'closed_by'  => ['panel'=>'result',   'order'=>20,'label'=>lang('closed_by'),  'clean'=>'integer',  'attr'=>['type'=>'select',  'value'=> 0], 'values'=>listUsers()]];
    }

    /**
     * This function builds the grid structure for retrieving data from the database
     * @param string $name - grid div id
     * @param integer $security - access level range 0-4
     * @param string $rID - return record id for opening specific return
     * @return array $data - structure of the grid to render
    */
    protected function managerGrid($security=0, $args=[])
    {
        $statuses = array_merge([['id'=>'a','text'=>lang('all')]], viewKeyDropdown(getModuleCache('bizuno', 'options', 'qa_status')));
        $selClosed= [['id'=>'a','text'=>lang('all')], ['id'=>'1','text'=>lang('yes')], ['id'=>'0','text'=>lang('no')]];
        $data     = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search' => ['ref_num', 'title', 'obj_desc', 'obj_test', 'obj_result'],
                'filters' => [
                    'closed' => ['order'=>20,'break'=>true,'label'=>lang('closed'),'attr'=>['type'=>'select','value'=>$this->defaults['closed']],'values'=>$selClosed],
                    'status' => ['order'=>30,'break'=>true,'label'=>lang('status'),'attr'=>['type'=>'select','value'=>$this->defaults['status']],'values'=>$statuses]]],
            'columns'=> [
                'ref_num'    => ['order'=>10,'label'=>lang('reference'),  'attr'=>['type'=>'text','width'=> 50, 'sortable'=>true, 'resizable'=>true]],
                'title'      => ['order'=>20,'label'=>lang('title'),      'attr'=>['type'=>'text','width'=>200, 'sortable'=>true, 'resizable'=>true]],
                'status'     => ['order'=>30,'label'=>lang('status'),     'attr'=>['width'=>100, 'sortable'=>true, 'resizable'=>true],
                    'events' => ['formatter'=>"function(value,row) { return bizQualStatuses[value]; }"]],
                'date_target'=> ['order'=>40,'label'=>lang('date_target'),'attr'=>['type'=>'date','width'=> 80, 'sortable'=>true, 'resizable'=>true],'format'=>'date'],
                'date_actual'=> ['order'=>50,'label'=>lang('date_actual'),'attr'=>['type'=>'date','width'=> 80, 'sortable'=>true, 'resizable'=>true],'format'=>'date'],
                ]]);
        return $data;
    }

    /**
     * Settings for the manager grid
     */
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'db_field','default'=>'ref_num'],'post');
        $this->defaults['order'] = clean('order', ['format'=>'db_field','default'=>'ASC'],    'post');
        $this->defaults['closed']= clean('closed',['format'=>'char',    'default'=>'a'],      'post');
        $this->defaults['status']= clean('status',['format'=>'db_field','default'=>'a'],      'post');
    }

    /******************************** Common Meta Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']);
        $keyedReps = [];
        foreach ($this->reps as $row) { $keyedReps[$row['id']] = $row['text']; }
        $layout['jsHead'][$this->moduleID] = "
function preSubmit() {
    jqBiz('#dgDetails').edatagrid('saveRow');
    var actNotes = jqBiz('#dgDetails').datagrid('getData');
    jqBiz('#dgObjData').val(JSON.stringify(actNotes));
    return true;
}
var bizQualStatuses = ".json_encode($this->status).";
var {$this->moduleID}Reps = ".json_encode($keyedReps).";";
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::mgrRowsMeta($layout, $security);
        if ($this->defaults['status']  =='a') { unset($layout['metagrid']['source']['filters']['status']); } // status=all so do test it
        if ($this->defaults['closed']  =='a') { unset($layout['metagrid']['source']['filters']['closed']); } // all closed
        if ($this->defaults['store_id']==-1)  { unset($layout['metagrid']['source']['filters']['store_id']); } // all stores
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        // Add grid
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']);
        $dgData = $layout['fields']['dgObjData']['attr']['value'];
        $layout['jsHead']['dgDetails']= "var dgObjData = ".json_encode(!empty($dgData) ? $dgData : []).";\n";
        $layout['datagrid'] = ['dgDetails'=>$this->dgDetails('dgDetails')];
        $layout['divs']['content']['divs']['dgDetails'] = ['order'=>80, 'type'=>'panel', 'key'=>'dgDetails',  'classes'=>['block66']];
        $layout['panels']['dgDetails'] = ['type'=>'datagrid', 'key'=>'dgDetails'];
        msgDebug("\nsending updated layout = ".print_r($layout, true));
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::copyMeta($layout, $security);
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, empty($rID)?2:3)) { return; }
        parent::saveMeta($layout, $args=['rID'=>$rID]);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout, ['table'=>'common']);
    }
    /**
     * Grid for action list on edit screen
     * @param string $name
     * @return array - grid structure
     */
    private function dgDetails($name='dgDetail')
    {
        return ['id'=>$name,'type'=>'edatagrid','title'=>lang('actions_required'),
            'attr'  => ['toolbar'=>"{$name}Toolbar", 'singleSelect'=>true],
            'events'=> ['data' => 'dgObjData',
                'onAfterRender'=> "function(row) { if ({$name}Data.length == 0) jqBiz('#$name').edatagrid('addRow'); }",
                'onClickRow'   => "function(rowIndex) { curIndex = rowIndex; }",
                'onDestroy'    => "function(rowIndex, row) { curIndex = -1; }",
                'onAdd'        => "function(rowIndex, row) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['newItem'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'=> ['order'=> 1,'label'=>lang('action'),     'attr'=>['width'=> 60],  'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['order'=>80,'icon'=>'trash','label'=>lang('trash'),'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'emp'   => ['order'=>20,'label'=>lang('employee'),   'attr'=>['width'=>120],
                    'events' => ['formatter'=>"function(value) { return {$this->moduleID}Reps[value]; }",
                        'editor'=>"{type:'combobox',options:{width:250,valueField:'id',textField:'text',editable:false, data:".json_encode($this->reps)."}}"]],
                'step'  => ['order'=>30,'label'=>lang('action'),     'attr'=>['width'=>400,'editor'=>'text',   'resizable'=>true]],
                'dateS' => ['order'=>50,'label'=>lang('date_target'),'attr'=>['width'=>120,'editor'=>'datebox','resizable'=>true]],
                'dateE' => ['order'=>60,'label'=>lang('date_actual'),'attr'=>['width'=>120,'editor'=>'datebox','resizable'=>true]]]];
    }
}
