<?php
/*
 * @name Bizuno ERP - Fixed Assets Extension
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
 * @version    7.x Last Update: 2026-03-25
 * @filesource /controllers/administrate/fixedAssets.php
 */

namespace bizuno;

class administrateFixedAssets extends mgrJournal
{
    public    $moduleID  = 'administrate';
    public    $pageID    = 'fixedAssets';
    protected $secID     = 'admin';
    protected $domSuffix = 'FixedAssets';
    protected $metaPrefix= 'fixed_asset';
    protected $nextRefIdx= 'next_fxdast_num';

    function __construct()
    {
        parent::__construct();
        $this->faTypes   = getModuleCache('bizuno', 'options', 'faTypes');
        $this->schedules = getMetaCommon('fixed_assets_schedules');
        msgDebug("\nRead schedules = ".print_r($this->schedules, true));
        $this->condition = ['n'=>lang('new'),   'u'=>lang('used')];
        $this->status    = [ 0 =>lang('active'), 1 =>lang('inactive')];
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
        $this->managerSettings();
        $this->fieldStructure();
    }

    private function fieldStructure()
    {
        $stores = getModuleCache('bizuno', 'stores');
        $this->struc = [ // Props panel
            '_rID'         => ['panel'=>'properties','order'=> 1,                                 'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]],
            'ref_num'      => ['panel'=>'properties','order'=>10,'label'=>lang('reference'),      'clean'=>'cmd',      'attr'=>['value'=>'']],
            'title'        => ['panel'=>'properties','order'=>15,'label'=>lang('title'),          'clean'=>'text',     'attr'=>['value'=>'']],
            'description'  => ['panel'=>'properties','order'=>20,'label'=>lang('description'),    'clean'=>'text',     'attr'=>['value'=>'']],
            'type'         => ['panel'=>'properties','order'=>25,'label'=>lang('asset_type'),     'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>''], 'values'=>viewKeyDropdown($this->faTypes), 'format'=>'fa_type'],
            'status'       => ['panel'=>'properties','order'=>30,'label'=>lang('status'),         'clean'=>'alpha_num','attr'=>['type'=>'hidden',  'value'=>0],  'values'=>viewKeyDropdown($this->status)],
            'store_id'     => ['panel'=>'properties','order'=>35,'label'=>lang('store_id'),       'clean'=>'integer',  'attr'=>['type'=>sizeof($stores)>1?'select':'hidden', 'value'=>0], 'values'=>viewStores(), 'format'=>'storeID'],
            'cost'         => ['panel'=>'properties','order'=>45,'label'=>lang('cost'),           'clean'=>'currency', 'attr'=>['type'=>'currency','value'=>'']],
            'serial_number'=> ['panel'=>'properties','order'=>50,'label'=>lang('serial_number'),  'clean'=>'cmd',      'attr'=>['value'=>'']],
            // Status pane
            'date_acq'     => ['panel'=>'status',    'order'=>10,'label'=>lang('date_acq'),       'clean'=>'dateMeta', 'attr'=>['type'=>'date',    'value'=>biz_date()], 'format'=>'date'],
            'date_maint'   => ['panel'=>'status',    'order'=>20,'label'=>lang('date_maint'),     'clean'=>'dateMeta', 'attr'=>['type'=>'date',    'value'=>''], 'format'=>'date'],
            'date_retire'  => ['panel'=>'status',    'order'=>30,'label'=>lang('date_retire'),    'clean'=>'dateMeta', 'attr'=>['type'=>'date',    'value'=>''], 'format'=>'date'],
            'dep_value'    => ['panel'=>'status',    'order'=>40,'label'=>lang('value_dep'),'break'=>false,'clean'=>'currency','attr'=>['type'=>'currency','value'=>'','readonly'=>true]],
            'dep_sched'    => ['panel'=>'status',    'order'=>50,'label'=>lang('dep_sched'),      'clean'=>'text',     'attr'=>['type'=>'select',  'value'=>''],  'values'=>$this->getTitles(true)],
            // Image panel
            'image_with_path'=>['panel'=>'image',    'order'=>20,'label'=>lang('image_with_path'),'clean'=>'filename', 'attr'=>['type'=>'hidden'], 'value'=>''],
            // Accounting panel
            'purch_cond'   => ['panel'=>'accounting','order'=>10,'label'=>lang('purch_cond'),     'clean'=>'char',     'attr'=>['type'=>'select',  'value'=>'n'], 'values'=>viewKeyDropdown($this->condition), 'format'=>'fa_condition'],
            'gl_asset'     => ['panel'=>'accounting','order'=>20,'label'=>lang('gl_asset'),       'clean'=>'cmd',      'attr'=>['type'=>'ledger',  'value'=>'']],
            'gl_maint'     => ['panel'=>'accounting','order'=>30,'label'=>lang('gl_maint'),       'clean'=>'cmd',      'attr'=>['type'=>'ledger',  'value'=>'']],
            'gl_dep'       => ['panel'=>'accounting','order'=>40,'label'=>lang('gl_dep'),         'clean'=>'cmd',      'attr'=>['type'=>'ledger',  'value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $yes_no_choices = [['id'=>'a','text'=>lang('all')], ['id'=>'0','text'=>lang('active')], ['id'=>'1','text'=>lang('inactive')]];
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'search' => ['ref_num', 'title', 'description'],
                'actions'=>[
                    'export'  =>['order'=>80,'icon'=>'export','events'=>['onClick'=>"hrefClick('$this->moduleID/$this->pageID/export');"]]],
                'filters'=> [
                    'status'=> ['order'=>10,'label'=>lang('status'),'values'=>$yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns'=> [
                'status'       => ['order'=> 0,                                     'attr'=>['hidden'=>true]],
                'ref_num'      => ['order'=>10, 'label'=>lang('asset_num'),    'attr'=>['width'=> 70, 'sortable'=>true, 'resizable'=>true]],
                'type'         => ['order'=>20, 'label'=>lang('asset_type', $this->moduleID), 'attr'=>['width'=> 70, 'sortable'=>true, 'resizable'=>true], 'format'=>'fa_type'],
                'purch_cond'   => ['order'=>30, 'label'=>lang('purch_cond', $this->moduleID), 'attr'=>['width'=> 70, 'sortable'=>true, 'resizable'=>true], 'format'=>'fa_condition'],
                'serial_number'=> ['order'=>40, 'label'=>lang('serial_number', $this->moduleID),'attr'=>['width'=>120,'sortable'=>true,'resizable'=>true]],
                'title'        => ['order'=>50, 'label'=>lang('description'),       'attr'=>['width'=>180, 'sortable'=>true, 'resizable'=>true]],
                'store_id'     => ['order'=>60, 'label'=>lang('store_id'),          'attr'=>['width'=> 90, 'sortable'=>true, 'resizable'=>true], 'format'=>'storeID'],
                'date_acq'     => ['order'=>70, 'label'=>lang('date_acq', $this->moduleID),   'attr'=>['width'=> 90, 'sortable'=>true, 'resizable'=>true], 'format'=>'date'],
                'date_retire'  => ['order'=>80, 'label'=>lang('date_retire', $this->moduleID),'attr'=>['width'=> 90, 'sortable'=>true, 'resizable'=>true], 'format'=>'date']]]);
//        unset($data['source']['tables']); // maybe $data['source']['filters']['jID']
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'cmd', 'default'=>'post_date'],'post');
        $this->defaults['status']= clean('status',['format'=>'char','default'=>'a'],        'post');
    }
    /******************************** Common Meta Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div','title'=>sprintf(lang('tbd_manager'), lang('gl_acct_type_8'))]);
        $layout['jsHead'] = [
            'faType' => "var extFixedAssetsType = ".json_encode($this->faTypes,  JSON_UNESCAPED_UNICODE).";\n",
            'faCond' => "var extFixedAssetsCond = ".json_encode($this->condition,JSON_UNESCAPED_UNICODE).";\n"];
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', 'integer', 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $layout['datagrid']['extFixedAssets']['columns']['store_id'] = ['order'=>25,'field'=>'store_id','format'=>'storeID',
            'label'=> lang('store_id'),'attr'=>['sortable'=>true,'resizable'=>true]];
        $layout['datagrid']['extFixedAssets']['source']['filters']['store'] = ['order'=>15,'label'=>lang('ctype_b'), 'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        switch ($storeID) {
            case -1: $layout['datagrid']['extFixedAssets']['source']['filters']['store']['sql'] = ''; break;
            default: $layout['datagrid']['extFixedAssets']['source']['filters']['store']['sql'] = "store_id=$storeID"; break;
        }
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::mgrRowsMeta($layout, $security);
        if ($this->defaults['status']=='a') { unset($layout['metagrid']['source']['filters']['status']); } // status=all so do test it
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', 'integer', 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $layout['datagrid']['extFixedAssets']['columns']['store_id'] = ['order'=>25,'field'=>'store_id','format'=>'storeID',
            'label'=> lang('store_id'),'attr'=>['sortable'=>true,'resizable'=>true]];
        $layout['datagrid']['extFixedAssets']['source']['filters']['store'] = ['order'=>15,'label'=>lang('ctype_b'), 'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        switch ($storeID) {
            case -1: $layout['datagrid']['extFixedAssets']['source']['filters']['store']['sql'] = ''; break;
            default: $layout['datagrid']['extFixedAssets']['source']['filters']['store']['sql'] = "store_id=$storeID"; break;
        }
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security);
        // Add image
        if (!empty($this->struc['image_with_path']['attr']['value'])) {
            $imgSrc  = $this->struc['image_with_path']['attr']['value']; // clean($structure['image_with_path']['attr']['value'], 'path_rel')
            $imgDir  = dirname($this->struc['image_with_path']['attr']['value']).'/';
        } else {
            $imgSrc  = '';
            $imgDir  = '/';
        }
        $layout['jsReady']['image'] = "imgManagerInit('image_with_path', '$imgSrc', '$imgDir', ".json_encode(['style'=>"max-height:200px;max-width:100%;"]).");";
        // add the attachment panel
        $layout['divs']['content']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
        // add calculate depreciation button
        $layout['fields']['calc_dep'] = ['order'=>50, 'label'=>lang('calculate_cost'), 'icon'=>'price', 'events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/getSchedValue', $rID);"]];
        $layout['panels']['status']['keys'][] = 'calc_dep';
        // Stores
        $rID = clean('rID', 'integer', 'get');
        if (!empty($this->restrict)) {
            $layout['fields']['store_id']['attr']['value'] = $this->myStore;
            return;
        }
        $layout['fields']['store_id']['label'] = lang('store_id');
        if (!$rID) {
            $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $layout['fields']['store_id']['attr']['type'] = 'select';
            $layout['fields']['store_id']['values'] = viewStores();
        }
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        parent::copyMeta($layout);
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!validateAccess($this->secID, empty($rID)?2:3)) { return; }
        parent::saveMeta($layout, $args=['_rID'=>$rID]);
    }
    public function export()
    {
        parent::export();
    }

    public function delete(&$layout=[])
    {
        if (!validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout);
    }

    /**
     * Method to initiate calculation of depreciation value
     * @param array $layout - structure of view
     * @return array - modified $layout
     */
    public function depValueBulk(&$layout=[])
    {
        if (!$security = validateAccess('admin', $rID?3:2)) { return; }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'common_meta', "meta_key='$this->metaPrefix'");
        if (sizeof($result) == 0) { return msgAdd(lang('no_results')); }
        $rows=[];
        foreach ($result as $row) {
            $value = json_decode($row['meta_value'], true);
            if (empty($value['status'])) { $rows[] = $row['id']; }
        }
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCron('faCalc', ['cnt'=>0, 'total'=>sizeof($rows), 'rows'=>$rows]);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"cronInit('faCalc','$this->moduleID/$this->pageID/faCalcBulkNext');"]]);
    }

    /**
     * Ajax continuation of depValueBulk
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function faCalcBulkNext(&$layout=[])
    {
        if (!$security = validateAccess('admin', $rID?3:2)) { return; }
        $cron= getUserCron('faCalc');
        $id  = array_shift($cron['rows']);
        if ($id) {
            $_GET['rID'] = $id;
            $this->getSchedValue($layout, false);
        }
        $cron['cnt']++;
        if (sizeof($cron['rows']) == 0) {
            msgLog(lang('gl_acct_type_8')." - Calculate Current Depreciated Values in Bulk");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} Assets",'baseID'=>'faCalc','urlID'=>"$this->moduleID/$this->pageID/faCalcBulkNext"]];
            clearUserCron('faCalc');
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed asset id = $id",'baseID'=>'faCalc','urlID'=>"$this->moduleID/$this->pageID/faCalcBulkNext"]];
        }
        setUserCron('faCalc', $cron);
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Gets the depreciation schedule values
     * @param array $layout - structure coming in
     * @param type $verbose
     * @return modified $layout
     */
    public function getSchedValue(&$layout=[], $verbose=true)
    {
        $rID  = clean('rID', 'integer', 'get');
        if (!$security = validateAccess('admin', $rID?3:2)) { return; }
        $row  = dbGetRow(BIZUNO_DB_PREFIX.'common_meta', "id=$rID");
        $value= json_decode($row['meta_value'], true);
        msgDebug("\nEntering getSchedValue, found row = ".print_r($value, true));
        if (empty($value['dep_sched'])) { return msgAdd(sprintf(lang('err_no_sched', $this->moduleID), "{$value['title']} [$rID]")); }
        $age  = biz_date('Y') - substr($value['date_acq'], 0, 4) - 1; // map to index of schedule
        $curValue = $value['cost'];
        msgDebug("\nWorking with age = $age and value = $curValue");
        if (!sizeof($this->schedules)) { return msgAdd(sprintf(lang('err_no_sched', $this->moduleID), lang('all'))); }
        foreach ($this->schedules as $title => $sched) {
            msgDebug("\nAnalyzing schedule titled: $title");
            if ($value['dep_sched'] <> $title) { continue; }
            msgDebug("\nMatched title: $title and schedule: ".print_r($sched, true));
            foreach ($sched as $idx => $percent) {
                if ($age >= $idx) {
                    msgDebug("\nCalculating cost off of index $idx");
                    $curValue = $value['cost'] * ($percent/100);
                }
            }
        }
        $value['dep_value'] = $curValue;
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['meta_value'=>json_encode($value)], 'update', "id={$row['id']}");
        $viewValue= round($curValue, 2);
        $layout   = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizNumSet('dep_value', $viewValue);"]]);
        if ($verbose) {
            msgLog(lang('gl_acct_type_8')."- Calculate Current Value - {$value['title']} ($rID) - $viewValue");
            msgAdd(lang('msg_database_write'), 'success');
        }
    }

    /**
     * Loads the depreciation schedule grid structure
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminSchedLoad(&$layout=[])
    {
        $title  = clean('rID', 'text', 'get');
        $titles = $this->getTitles();
        if (!$title && sizeof($titles) > 0) { $title = $titles[0]['id']; }
        $dgRows = [];
        if (empty($this->schedules[$title])) { $this->schedules[$title] = []; }
        foreach ($this->schedules[$title] as $row) { $dgRows[] = ['label'=>$row]; }
        $jsBody = "var faSchedData = ".json_encode(['total'=>sizeof($dgRows),'rows'=>$dgRows]).";
function faSchedSave() {
    var title = jqBiz('#schedCat').combobox('getValue');
    jqBiz('#dgFaSched').edatagrid('saveRow');
    var rows = JSON.stringify(jqBiz('#dgFaSched').datagrid('getData'));
    jsonAction('$this->moduleID/$this->pageID/adminSchedSave', title, rows);
}";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => [
                'head'=> ['order'=>10,'type'=>'html',    'html'=>lang('fa_intro', $this->moduleID)],
                'body'=> ['order'=>50,'type'=>'datagrid','key' =>'dgFaSched']],
            'jsHead'  => ['faSchedHead'=>$jsBody],
            'datagrid'=> ['dgFaSched'  =>$this->dgAdminSched('dgFaSched', $title, $titles)]]);
    }

    /**
     * Saves the depreciation schedule to the database
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminSchedSave(&$layout=[])
    {
        $title = clean('rID', 'text', 'get');
        $rows  = clean('data','json', 'get');
        if (empty($title)) { return msgAdd("Category field is required!"); }
        $scheds= dbMetaGet(0, 'fixed_assets_schedules');
        $sIdx  = metaIdxClean($scheds);
        if (sizeof($rows['rows']) == 0) { // delete category
            unset($scheds[$title]);
        } else {
            $output = [];
            foreach ($rows['rows'] as $row) { $output[] = $row['label']; }
            $scheds[$title] = $output;
            ksort($scheds, SORT_NATURAL);
        }
        dbMetaSet($sIdx, 'fixed_assets_schedules', $scheds);
        msgAdd(lang('msg_settings_saved'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizPanelRefresh('sched');"]]);
    }

    /**
     * Generates the pull down structure of depreciation schedules
     * @param type $addNull
     * @return type
     */
    private function getTitles($addNull = false)
    {
        msgDebug("\nEntering getTitles with addNull = $addNull");
        $tmp = array_keys($this->schedules);
        $titles = $addNull ? [['id'=>'','text'=>lang('new')]] : [];
        foreach ($tmp as $value) { $titles[] = ['id'=>$value, 'text'=>$value]; }
        return $titles;
    }

    /**
     * Sets the grid structure for the depreciation schedules
     * @param string $name - DOM element id of the grid
     * @param string $title - current depreciation schedule title
     * @param array $titles - list of available depreciation schedules
     * @return array - grid structure
     */
    private function dgAdminSched($name, $title='', $titles=[])
    {
        return ['id'=>$name,'type'=>'edatagrid','attr'=>['width'=>400,'toolbar'=>"#{$name}Toolbar",'rownumbers'=>true],
            'events' => [
                'data'       => "faSchedData",
                'onClickRow' => "function(rowIndex) { curIndex = rowIndex; }",
                'onBeginEdit'=> "function(rowIndex) { curIndex = rowIndex; }",
                'onDestroy'  => "function(rowIndex) { curIndex = undefined; }",
                'onAdd'      => "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => [
                'actions'=> [
                    'schedSave'=>['order'=>10,'icon'=>'save','events'=>['onClick'=>"faSchedSave();"]],
                    'schedNew' =>['order'=>30,'icon'=>'add', 'events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]],
                    'schedCat'=> ['order'=>70,'label'=>lang('category', $this->moduleID),'values'=>$titles,'attr'=>['type'=>'select','value'=>$title],
                        'options'=>['width'=>150,'editable'=>true,'onClick'=>"function(row) { var tab=jqBiz('#tabAdmin').tabs('getSelected'); tab.panel('refresh','".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/adminSchedLoad&rID='+row.text); }"]]]],
            'columns' => [
                'action' => ['order'=>1, 'label'=>lang('action'), 'attr'=>  ['width'=>80],
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['icon'=>'trash','order'=>20,'size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jqBiz('#$name').edatagrid('deleteRow', curIndex);"]]]],
                'label'=> ['order'=>40,'label'=>lang('percent_good', $this->moduleID),'attr'=>['width'=>200,'editor'=>'numberbox','resizable'=>true]]]];
    }
}
