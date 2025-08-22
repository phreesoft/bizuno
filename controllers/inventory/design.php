<?php
/*
 * @name Bizuno ERP - Service Builder (Manufacturing) Extension
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
 * @version    7.x Last Update: 2025-08-17
 * @filesource /controllers/inventory/design.php
 */

namespace bizuno;

class inventoryDesign extends mgrJournal
{
    public    $moduleID  = 'inventory';
    public    $pageID    = 'design';
    protected $secID     = 'woDesign';
    protected $domSuffix = 'Design';
    protected $metaPrefix= 'production_job';
    public $struc;
    public $tasks;

    function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            '_rID'       => ['panel'=>'general','order'=> 1,                             'clean'=>'integer','attr'=>['type'=>'hidden',   'value'=>0]],
            'steps'      => ['panel'=>'general','order'=> 1,                             'clean'=>'json',   'attr'=>['type'=>'hidden',   'value'=>[]]],
            'sku_id'     => ['panel'=>'general','order'=>10,'label'=>lang('sku'),        'clean'=>'integer','attr'=>['type'=>'inventory','value'=>'']],
            'title'      => ['panel'=>'general','order'=>15,'label'=>lang('title'),      'clean'=>'text',   'attr'=>[]],
            'inactive'   => ['panel'=>'general','order'=>20,'label'=>lang('inactive'),   'clean'=>'text',   'attr'=>['type'=>'selNoYes', 'value'=>0]],
            'description'=> ['panel'=>'general','order'=>25,'label'=>lang('description'),'clean'=>'text',   'attr'=>['type'=>'textarea']],
            'allocate'   => ['panel'=>'general','order'=>30,'label'=>lang('allocate'),   'clean'=>'text',   'attr'=>['type'=>'selNoYes']],
            'ref_doc'    => ['panel'=>'general','order'=>35,'label'=>lang('ref_doc'),    'clean'=>'text',   'attr'=>[]],
            'ref_spec'   => ['panel'=>'general','order'=>40,'label'=>lang('ref_spec'),   'clean'=>'text',   'attr'=>[]],
            'date_last'  => ['panel'=>'general','order'=>45,'label'=>lang('date_last'),  'clean'=>'text',   'attr'=>['type'=>'date',     'value'=>biz_date()]]];
    }
    protected function managerGrid($security, $args=[])
    {
        msgDebug("\nEntering managerGrid with args = ".print_r($args, true));
        $defaults = ['dom'=>'page', '_table'=>'inventory', '_refID'=>'%'];
        $opts = array_replace($defaults, $args);
        $yes_no_choices = [['id'=>'a','text'=>lang('all')], ['id'=>'y','text'=>lang('active')], ['id'=>'n','text'=>lang('inactive')]];
        $data     = array_replace_recursive(parent::gridBase($security, $opts), [
            'footnotes'=> ['codes'=>lang('color_codes').': <span class="row-inactive">'.lang('inactive').'</span>'],
            'source'   => [
                'search' => ['title', 'description', 'sku'],
                'filters'=> ['status'=>['order'=>10, 'label'=>lang('status'),'values'=>$yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns' => [
                'id'         => ['order'=>0, 'attr'=>['hidden'=>true]],
                'sku_id'     => ['order'=>0, 'attr'=>['hidden'=>true]],
                'inactive'   => ['order'=>0, 'attr'=>['hidden'=>true]],
                'sku'        => ['order'=>10, 'label'=>lang('sku'),        'attr'=>['width'=>100,'resizable'=>true],
                    'events'=>  ['styler'=>"function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; }}"]],
                'title'      => ['order'=>20, 'label'=>lang('title'),      'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true]],
                'description'=> ['order'=>30, 'label'=>lang('description'),'attr'=>['width'=>300,'sortable'=>true,'resizable'=>true]],
                'date_last'  => ['order'=>50, 'label'=>lang('date_last'),  'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true], 'format'=>'date']]]);
        return $data;
    }
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'cmd',     'default'=>'title'],'post');
        $this->defaults['order'] = clean('order', ['format'=>'db_field','default'=>'ASC'],  'post');
        $this->defaults['status']= clean('status',['format'=>'cmd',     'default'=>'a'],    'post');
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $args = ['_table'=>'inventory', 'title'=>lang('wo_design')];
        parent::managerMain($layout, $security, $args);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $grid  = $this->managerGrid($security); // , $args
        if ($this->defaults['status']=='a') { unset($grid['source']['filters']['status']); } // status=all so don't test it
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode($this->managerRowsSort($grid))]);
    }
    private function managerRowsSort($dg)
    {
        msgDebug("\nEntering managerRowsSort");
        $this->tasks = dbMetaGet('%', 'production_task');
        $data  = dbMetaGet('%', $this->metaPrefix, 'inventory', '%');
        $search= getSearch();
        $output= [];
        // @TODO - This can be re-written to read all sku data in a single sql and then iterrate
        foreach ($data as $key => $row) {
            $hit = false;
            $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['sku', 'description_short'], "id={$row['sku_id']}");
            if (empty($sku)) { // delete the meta, orphaned job
                msgDebug("\n    SKU not found, skipping!");
                $sql = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id={$row['sku_id']}";
                msgDebug(" .. Executing sql = $sql"); dbGetResult($sql); 
                continue;
            }
            if (!empty($search)) {
                $skuHit = array_filter($sku, function($value) use ($search) { return strpos($value, $search) !== false; });
                if     (!empty($skuHit))                   { $hit = true; } // check for hits in inventory
                elseif ($this->searchTasks($row['steps'])) { $hit = true; } // check for hits in production tasks
            } else { $hit = true; }
            $row['sku'] = $sku['sku'];
            if ($hit) { $output[] = $row; }
        }
        msgDebug("\nafter adjustment for view, sizeof data = ".sizeof($data));
        $output1= sortOrder($output, $this->defaults['sort'], strtolower($this->defaults['order'])=='desc'?'desc':'asc'); // sort
        foreach ($output1 as $idx => $row) {
            foreach ($row as $key => $value) {
                if (!empty($dg['columns'][$key]['process'])){ $output1[$idx][$key] = viewProcess($value,              $dg['columns'][$key]['process']); }
                if (!empty($dg['columns'][$key]['format'])) { $output1[$idx][$key] = viewFormat ($output1[$idx][$key],$dg['columns'][$key]['format']); }
            }
        }
        $results = array_slice($output1, ($this->defaults['page']-1)*$this->defaults['rows'], $this->defaults['rows']); // get slice
        msgDebug("\nPost processing, returning row count = ".sizeof($results));
        return ['total'=>sizeof($output), 'rows'=>$results];
    }
    private function searchTasks($search, $steps=[])
    {
        $hit = false;
        foreach ($this->tasks as $task) {
            if (!in_array($task['_rID'], $steps)) { continue; }
            $taskHit = array_filter($task, function($value) use ($search) { return strpos($value, $search) !== false; });
            if (!empty($taskHit)) { $hit = true; } 
        }
        return $hit;
    }

    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $title = clean('data', 'text', 'get');
        $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($title)."'");
        $exists= !empty($skuID) ? dbMetaGet('%', $this->metaPrefix, 'inventory', $skuID) : false;
        if (empty($skuID) || !empty($exists)) { return msgAdd('The SKU you entered could not be found or already has a build template! The new template was not created.'); }
        $args = ['_refID'=>$skuID, '_table'=>'inventory'];
        parent::copyMeta($layout, $args);
        // Make some custom changes
        $newID = clean('newID', 'integer', 'get');
        $metaVal = dbMetaGet($newID, $this->metaPrefix, 'inventory', $skuID);
        metaIdxClean($metaVal); // remove the indexes
        $metaVal['sku_id'] = $skuID;
        dbMetaSet($newID, $this->metaPrefix, $metaVal, 'inventory', $skuID);
    }
    public function edit(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, !empty($rID)?3:2)) { return; }
        $args = ['_rID'=>$rID, '_table'=>'inventory'];
        parent::editMeta($layout, $security, $args);
        // add the grids
        $layout['datagrid'] = ['dgSteps'=>$this->dgSteps('dgSteps')];
        $layout['divs']['content']['divs']['dgSteps'] = ['order'=>80, 'type'=>'panel', 'key'=>'dgSteps', 'classes'=>['block66']];
        $layout['panels']['dgSteps'] = ['type'=>'datagrid', 'key'=>'dgSteps'];
        // Build the task list
        $steps = array_values($layout['fields']['steps']['attr']['value']);
        $tasks = [];
        foreach ($steps as $value) {
            $task = dbMetaGet($value['task_id'], 'production_task');
            msgDebug("\nTask {$value['task_id']} with data = ".print_r($task, true));
            $tasks[] = $task;
        }
        $layout['jsHead'][$this->pageID] = "var stepsData = ".json_encode(['total'=>sizeof($tasks), 'rows'=>$tasks])."
function preSubmit() {
    jqBiz('#dgSteps').edatagrid('saveRow');
    var items = jqBiz('#dgSteps').datagrid('getData');
    var serializedItems = JSON.stringify(items);
    jqBiz('#steps').val(serializedItems);
    return true;
}
function fillTask(data) {
    jqBiz('#dgSteps').edatagrid('getRows')[curIndex]['title']      = data.title;
    jqBiz('#dgSteps').edatagrid('getRows')[curIndex]['description']= data.description;
    jqBiz('#dgSteps').edatagrid('getRows')[curIndex]['_rID']       = data._rID;
    jqBiz('#dgSteps').edatagrid('refreshRow', curIndex);
}";
        if (empty($rID)) {
            unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']);
            $layout['jsReady'][$this->pageID] = "jqBiz('#dgSteps').edatagrid('addRow');
jqBiz('#sku_id').combogrid({ panelWidth: 550, delay:500, value:'', idField:'id', textField:'sku', mode:'remote',
    url:        bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&filter=assy',
    onClickRow: function (id, data) { bizTextSet('title', data.description_short); bizTextSet('description', data.description_short); },
    columns:[[ {field:'id', hidden:true}, {field:'sku', title:'".jsLang('sku')."', width:100}, {field:'description_short', title:'".jsLang('description')."', width:250} ]]
});";
        } else {
            $sku = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['sku', 'description_short'], "id={$layout['fields']['sku_id']['attr']['value']}");
            $layout['divs']['heading']['html'] = "<h1>Edit - {$sku['sku']}: {$sku['description_short']}</h1>";
            $layout['fields']['sku_id']['attr']['type'] = 'hidden';
        }
        msgDebug("\nlayout before render = ".print_r($layout, true));
    }
    public function save(&$layout=[])
    {
        $rID  = clean('_rID',  'integer','post');
        $skuID= clean('sku_id','text',   'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        // Error check
        $exists = dbMetaGet('%', $this->metaPrefix, 'inventory', $skuID);
        msgDebug("\nLooking for exiting records: ".print_r($exists, true));
        if (!empty($exists)) { // override rID, i.e. only allow one production job per sku
            $meta = array_shift($exists);
            $newID = metaIdxClean($meta);
            msgDebug("\nFound a job template already, changing _rID to: $newID");
            $_POST['_rID'] = $newID;
        }
        // clean up the steps, all we need is the task_id
        $steps = clean('steps', 'json', 'post');
        msgDebug("\nsteps after decode = ".print_r($steps, true));
        if (empty($steps['rows'])) { $steps['rows'] = []; }
        $output = [];
        foreach ($steps['rows'] as $step) { $output[] = ['task_id'=>$step['_rID']]; }
        msgDebug("\nsteps to set post = ".print_r($output, true));
        $_POST['steps'] = json_encode($output);
        parent::saveMeta($layout, ['_table'=>'inventory', '_refID'=>$skuID]);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $args = ['_table'=>'inventory'];
        parent::deleteMeta($layout, $args);
    }

    /**
     * Grid structure for template job steps/tasks
     * @param srtring $name - grid name
     * @return array - grid structure
     */
    private function dgSteps($name)
    {
        return ['id' => $name,'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'rownumbers'=>true,'singleSelect'=>true,'idField'=>'_rID'],
            'events' => ['data'=> 'stepsData',
                'onLoadSuccess'=> "function(row) { jqBiz('#$name').datagrid('enableDnd'); }", //
                'onAdd'        => "function(rowIndex, row) { curIndex=rowIndex; jqBiz('#$name').datagrid('enableDnd', rowIndex); }",
                'onClickRow'   => "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['newItem'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                '_rID'       => ['order'=> 0,'attr'=>['hidden'=>'true']],
                'action'     => ['order'=> 1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['order'=>20,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'title'      => ['order'=>30,'label'=>lang('title'),'attr'=>['width'=>120,'resizable'=>true],
                    'events' =>  ['editor'=>"{type:'combogrid',options:{ url:bizunoAjax+'&bizRt=$this->moduleID/tasks/managerRows&rows=1000',
                        width:250, panelWidth:250, delay:500, idField:'_rID', textField:'title', mode:'remote',
                        onClickRow:function (idx, data) { fillTask(data); },
                        formatter: function () { return 'dave'; },
                        columns:   [[{field:'title',title:'".jsLang('title')."',width:220},{field:'description',hidden:true},{field:'id',hidden:true}]] }}"]],
                'description'=>['order'=>40,'label'=>lang('description'),'attr'=>['width'=>320,'resizable'=>true]]]];
    }
}
