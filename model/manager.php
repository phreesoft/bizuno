<?php
/*
 * Common Manager Template
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
 * @version    7.x Last Update: 2025-10-12
 * @filesource /model/manager.php
 */

namespace bizuno;

class mgrJournal
{
    public $attachPath = '';
    public $defaults;
    public $lang;
    public $mgrTitle;
    public $restrict;
    public $myStore;

    protected function __construct()
    {
        $this->lang    = getLang($this->moduleID);
        $this->mgrTitle= sprintf(lang('tbd_manager'), lang($this->pageID));
        $this->restrict= getUserCache('profile', 'restrict_store',false, 0);
        $this->myStore = getUserCache('profile', 'store_id',      false, 0);
    }

    /**
     * Common properties of a journal_main grid
     * @param integer $security
     * @param array $args
     * @param boolean $admin
     * @return type
     */
    protected function gridBase($security=0, $args=[])
    {
        $defs = array_replace(['dom'=>'page', 'type'=>'meta', '_table'=>'common', '_refID'=>clean('refID', 'integer', 'get'), 'period'=>getModuleCache('phreebooks', 'fy', 'period'), 'textCopy'=>lang('instr_copy'), 'work'=>false, 'xGet'=>''], $args); // valid types are journal or meta
        $data = ['id'=>"dg{$this->domSuffix}", 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'], 'metaPrefix'=>$this->metaPrefix, 'metaTable'=>$defs['_table'], 'metaKey'=>$defs['_refID'],
            'attr'   => ['toolbar'=>"#dg{$this->domSuffix}Toolbar", 'idField'=>$defs['type']=='meta'?'_rID':'id', 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows{$defs['xGet']}"],
            'events' => [
                'rowStyler'    =>"function(index, row) { if (row.status==1) { return {class:'row-inactive'}; }}",
                'onDblClickRow'=>"function(rowIndex, rowData){ accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&table='+rowData._table+'{$defs['xGet']}', ".($defs['type']=='meta'?'rowData._rID':'rowData.id')."); }",
                'onLoadSuccess'=>"function(row) { jqBiz('#dg{$this->domSuffix}Toolbar input').keypress(function (e) { if (e.keyCode == 13) { window['dg{$this->domSuffix}Reload'](); } }); }"],
            'source' => [
                'tables' => ['journal_main'=>['table'=>BIZUNO_DB_PREFIX.'journal_main']],
                'search' => ['ref_num', 'title', 'notes'],
                'actions'=> [
                    'new'   => ['order'=>10,'icon'=>'add',  'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&dom={$defs['dom']}&table={$defs['_table']}&refID={$defs['_refID']}{$defs['xGet']}', 0);"]],
                    'clear' => ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search', ''); dg{$this->domSuffix}Reload();"]]],
                'filters'=> [
                    'search'=> ['order'=>90,'attr'=>['id'=>'search','value'=>$this->defaults['search']]]],
                'sort'   => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'=> [
                'id'     => ['order'=> 0,'field'=>'id','attr'=>['hidden'=>true]], // for journal entries
                '_rID'   => ['order'=> 0,              'attr'=>['hidden'=>true]], // Don't need these as they are in the data array anyway
                '_table' => ['order'=> 0,              'attr'=>['hidden'=>true]],
                'action' => ['order'=> 2,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return dg{$this->domSuffix}Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'  =>['order'=>30,'icon'=>'edit', 'size'=>'small','label'=>lang('edit'),
                            'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit&table=tableTBD{$defs['xGet']}', 'idTBD');"]],
                        'copy'  =>['order'=>50,'icon'=>'copy', 'size'=>'small','label'=>lang('copy'),
                            'events'=>['onClick'=>"var title=prompt('".lang($defs['textCopy'])."'); if (title!=null) jsonAction('$this->moduleID/$this->pageID/copy&table=tableTBD{$defs['xGet']}', 'idTBD', title);"]],
                        'delete'=>['order'=>90,'icon'=>'trash','size'=>'small','label'=>lang('delete'), 'hidden'=> $security > 3 ? false : true,
                            'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/delete&table=tableTBD{$defs['xGet']}', 'idTBD');"]]]]]];
        if ($defs['type']=='journal') {
            $dateRange = dbSqlDates($this->defaults['period'], BIZUNO_DB_PREFIX.'journal_main.post_date'); // changed to post_date for journal searches
            $sqlPeriod = $dateRange['sql'];
            $data['source']['filters']['jID']   = ['order'=> 1,'sql'=>"journal_id=$this->journalID", 'attr'=>['type'=>'hidden', 'value'=>$this->journalID]];
            $data['source']['filters']['period']= ['order'=>10,'break'=>true,'sql'=>$sqlPeriod,'options'=>['width'=>300],
                'label'=>lang('period'), 'values'=>viewKeyDropdown(localeDates(true, true, true, true)),'attr'=>['type'=>'select','value'=>$this->defaults['period']]];
        }
        if (!empty($defs['work'])) {
            $data['source']['actions']['newJ'] = ['order'=>20,'icon'=>'work', 'events'=>['onClick'=>"windowEdit('$this->moduleID/$this->pageID/add{$defs['xGet']}','winNewTask','".lang('new')."',400,200);"]];
        }
        return $data;
    }

    /******************************** Journal Entries ********************************/
    /**
     * Stores the users preferences for this session
     */
    protected function managerDefaults()
    {
        $_POST['search'] = getSearch(); // search can come in many ways
        $this->defaults = [
            'rows'  => clean('rows',  ['format'=>'integer', 'default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post'),
            'page'  => clean('page',  ['format'=>'integer', 'default'=>1],          'post'),
            'sort'  => clean('sort',  ['format'=>'cmd',     'default'=>'post_date'],'post'), // typically overidden at calling class as the default will vary
            'order' => clean('order', ['format'=>'db_field','default'=>'DESC'],     'post'),
            'search'=> clean('search',['format'=>'text',    'default'=>''],         'post')];
    }

    /**
     * Manager page for historical record keeping
     * @param array $layout - page structure coming in
     * @param $security - Access grant security level
     * @paran $args - various arguments to control the view
     * @return modified $layout
     */
    protected function managerMain(&$layout=[], $security=0, $args=[])
    {
        $defs = array_replace(['rID'=>clean('rID', 'integer', 'get'), 'dom'=>'page'], $args);
        msgDebug("\nEntering model/managerMain with defaults = ".print_r($defs, true));
        $grid = $this->managerGrid($security, $defs);
        mapMetaGridToDB($grid, $this->struc);
        $title= !empty($defs['title']) ? $defs['title'] : $this->mgrTitle;
        $data = viewMgrBase($grid, $title, $this->domSuffix);
        if (!empty($defs['rID'])) {
            $data['jsReady']['init'] = "jqBiz(document).ready(function() { accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit', {$defs['rID']}); });";
        }
        if ('div'==$defs['dom']) {
            $data['type'] = 'divHTML';
            $layout = array_replace_recursive($layout, $data);
        } else {
            $layout = array_replace_recursive($layout, viewMain(), $data);
        }
    }

    /**
     * Handles the request for journal meta data tied to journal main records
     * @param array $grid 
     * @return array - layout ready to render requested rows
     */
    protected function mgrRowsDBPrep($grid)
    {
        msgDebug("\nEntering mgrRowsDBPrep with the grid");
        $tables= dbTableReadTables  ($grid['source']['tables']);
        $grid['source']['filters']['search']['attr']['value'] = ''; // clear the search field because we are searching the meta
        $crit  = dbTableReadCriteria($grid['source']['filters'], $grid['source']['search']); // no search here
        $order = ''; // dbTableReadOrder   ($grid['source']['sort']); // no order here
        $fields= [BIZUNO_DB_PREFIX.'journal_meta.id', BIZUNO_DB_PREFIX.'journal_meta.meta_value'];
        $rows  = dbGetMulti($tables, $crit, $order, $fields, 0, false);
        $grid['source']['filters']['search']['attr']['value'] = $this->defaults['search'] = getSearch();
        $meta  = [];
        foreach ($rows as $row) {
            $value = json_validate($row['meta_value']) ? json_decode($row['meta_value'], true) : ['value'=>$row['meta_value']];
            // need both id and _rID to handle meta attached to journal records
            $meta[]= $value + ['_table'=>$grid['metaTable'], 'id'=>$row['id'], '_rID'=>$row['id'], '_refID'=>!empty($row['ref_id'])?$row['ref_id']:0];
        }
        return $meta;
    }

    /**
     * Apply filters and finalize output
     * @param type $grid
     * @param type $meta
     */
    protected function mgrRowsDBFltr(&$layout, $grid, $meta)
    {
        if (!empty($this->defaults['search'])){ $grid['source']['filters']['search']['attr']['value']=$this->defaults['search']; } // set the search after the journal fetch
        else                                  { unset($grid['source']['filters']['search']); }
        $output= dbMetaReadSearch($meta, $grid, $this->defaults['search']);
        foreach ($output as $idx => $row) {
            foreach ($row as $key => $value) {
                if (!empty($grid['columns'][$key]['process'])){ $output[$idx][$key] = viewProcess($value,    $grid['columns'][$key]['process']); }
                if (!empty($grid['columns'][$key]['format'])) { $output[$idx][$key] = viewFormat ($row[$key],$grid['columns'][$key]['format']); }
            }
        }
        $out1  = sortOrder($output, $this->defaults['sort'], strtolower($this->defaults['order'])=='desc'?'desc':'asc'); // sort
        $slice = array_slice($out1, ($this->defaults['page']-1)*$this->defaults['rows'], $this->defaults['rows']); // get slice
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$slice])]);
    }

    /**
     * Main entry point for manager for meta data
     * @param array $layout - structure coming in
     * @return modified structure
     */
    protected function managerMeta(&$layout=[], $security=0, $args=[])
    {
        $defs    = array_replace(['dom'=>'div', 'title'=>$this->mgrTitle], $args);
        $rID     = clean('rID', 'integer', 'get');
        $grid    = $this->managerGrid($security, ['rID'=>$rID], true);
        $data    = viewMgrBase($grid, $defs['title'], $this->domSuffix);
        $data['type'] = $defs['dom']=='div' ? 'divHTML' : 'page';
        $layout  = array_replace_recursive($layout, $data);
    }
    
    /**
     * Handles the request from the user for common_meta records
     * @param array $layout - Structure coming in
     * @return array - layout ready to render requested rows
     */
    protected function mgrRowsMeta(&$layout=[], $security=0, $args=[])
    {
        $_POST['search'] = getSearch();
        $dgStruc= $this->managerGrid($security, $args);
        if (empty($this->defaults['search'])) { unset($dgStruc['source']['filters']['search']); }
        $layout = array_replace_recursive($layout, ['type'=>'metagrid', 'metagrid'=>$dgStruc, 'settings'=>$this->defaults]);
    }

    /**
     * Structure to add a new meta task
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    protected function addDB(&$layout=[], $security=0, $args=[])
    {
        $defs  = array_replace(['table'=>'common', 'label'=>lang('select'), 'desc'=>'Please select an option and press Next.'], $args); // 'fldSel'=>['id'=>'taskID'], 
        $html  = "<p>{$defs['desc']}</p>"; // instructions
        if (!empty($defs['fldSel'])) { //special cases
            $field = $defs['fldSel']['id'];
            $html .= html5($field, $defs['fldSel']['props']);
        } else { // common meta mostly
            $field = 'taskID';
            $rows  = dbMetaGet('%', $this->metaPrefix, $defs['table'], '%');
            msgDebug("\nrows = ".print_r($rows, true));
            $tasks = [['id'=>'', 'text'=>lang('select')]];
            foreach ($rows as $row) { $tasks[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; }
            $html .= html5($field, ['values'=>$tasks,'label'=>$defs['label'],'attr'=>['type'=>'select', 'value'=>'']]);
        }
        $html .= html5('iconGO',['icon'=>'next', 'events'=>['onClick'=>"var taskID=bizSelGet('$field'); accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&$field='+taskID, 0); bizWindowClose('winNewTask');"]]);
        $data  = ['type'=>'divHTML',
            'divs'=>['content'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=>['init'=>"bizFocus('{$defs['fldSel']['id']}');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Editor for tasks and journal entries
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    protected function editDB(&$layout=[], $security=0, $args=[])
    {
        $defs  = array_replace(['rID'=>clean('rID', 'integer', 'get')], $args);
        msgDebug("\nEntering editDB with defs = ".print_r($defs, true));
        $taskID= clean('taskID', 'integer', 'get');
        $dbData= [];
        $defs['title'] = lang('new');
        if (!empty($defs['rID'])) { // edit
            $dbRow   = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id={$defs['rID']}");
            msgDebug("\nRead db row = ".print_r($dbRow, true));
            $dbData  = mapDBtoMeta($this->struc, $dbRow);
            msgDebug("\nMapped db to meta = ".print_r($dbData, true));
            $defs['title'] = '<h1>'.lang('edit').': '.$dbData['title']."</h1>";
        } elseif (!empty($taskID)) { // it's a new task direct from the journal tab
            $metaData= dbMetaGet($taskID, $this->metaPrefix);
            $dbData  = array_replace($metaData, ['id'=>0, 'user_id'=>getUserCache('profile', 'userID')]); 
            $defs['title'] = '<h1>'.lang('new').' - '.$dbData['ref_num'].': '.$dbData['title'].'</h1>';
            msgDebug("\nTaskID not empty, title = {$defs['title']} and data = ".print_r($dbData, true));
        }
        metaPopulate($this->struc, $dbData);
        msgDebug("\nPopulated structure = ".print_r($this->struc, true));
        $data  = $this->setEdit($security, $defs);
        $data['type'] = 'divHTML';
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * 
     * @param type $layout
     * @param type $security
     * @param type $args
     */
    protected function editMeta(&$layout=[], $security=0, $args=[])
    {
        $defs   = array_replace(['dom'=>'div', '_rID'=>clean('rID', 'integer', 'get'), '_table'=>'common', '_refID'=>clean('refID', 'integer', 'get'), 'index'=>'title', 'title'=>lang('new')], $args);
        msgDebug("\nEntering editMeta with defs = ".print_r($defs, true));
        $metaVal= !empty($defs['_rID']) ? dbMetaGet($defs['_rID'], $this->metaPrefix, $defs['_table'], $defs['_refID']) : ['_refID'=>$defs['_refID'], '_table'=>$defs['_table']];
        if (!empty($metaVal['title'])) { 
            $defs['title'] = lang('edit').': '.(!empty($metaVal['ref_num'])?$metaVal['ref_num'].' - ':'').$metaVal['title'];
        }
        msgDebug("\nRead metaVal from db: ".print_r($metaVal, true));
        metaPopulate($this->struc, $metaVal);
        msgDebug("\nPopulated structure = ".print_r($this->struc, true));
        $data   = $this->setEdit($security, $defs);
        customTabs($data, $this->moduleID, "tab$this->moduleID");
        if ('div'==$defs['dom']) {
            $layout = array_replace_recursive($layout, $data);
        } else {
            $data['type'] = 'page';
            $layout = array_replace_recursive($layout, viewMain(), $data);
        }
    }

    protected function renameMeta(&$layout=[], $security=0, $args=[])
    {
        $defs  = array_replace(['table'=>'common', 'label'=>lang('select'), 'desc'=>'Please select an option and press Next.'], $args); // 'fldSel'=>['id'=>'taskID'], 
        $html  = "<p>{$defs['desc']}</p>"; // instructions
        if (!empty($defs['fldSel'])) { //special cases
            $field = $defs['fldSel']['id'];
            $html .= html5($field, $defs['fldSel']['props']);
        } else { // common meta mostly
            $field = 'taskID';
            $rows  = dbMetaGet('%', $this->metaPrefix, $defs['table'], '%');
            msgDebug("\nrows = ".print_r($rows, true));
            $tasks = [['id'=>'', 'text'=>lang('select')]];
            foreach ($rows as $row) { $tasks[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; }
            $html .= html5($field, ['values'=>$tasks,'label'=>$defs['label'],'attr'=>['type'=>'select', 'value'=>'']]);
        }
        $html .= html5('iconGO',['icon'=>'next', 'events'=>['onClick'=>"var taskID=bizSelGet('$field'); accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&$field='+taskID, 0); bizWindowClose('winNewTask');"]]);
        $data  = ['type'=>'divHTML',
            'divs'=>['content'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=>['init'=>"bizFocus('{$defs['fldSel']['id']}');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * 
     * @param type $layout
     * @param type $args
     * @return type
     */
    protected function copyMeta(&$layout=[], $args=[])
    {
        $defs    = array_replace(['_rID'=>clean('rID','integer','get'), '_table'=>'common', '_refID'=>clean('refID','integer','get'), 'title'=>clean('data','text','get'), 'index'=>'title'], (array)$args);
        msgDebug("\nEntering editMeta with replaced defs = ".print_r($defs, true));
        if (empty($defs['_rID']) || empty($defs['title'])) { return msgAdd(lang('err_inv_title_blank')); }
        $metaVal = dbMetaGet($defs['_rID'], $this->metaPrefix, $defs['_table'], $defs['_refID']);
        metaIdxClean($metaVal); // remove the indexes
        $oldTitle= $metaVal[$defs['index']];
        // NOTE: Checking for duplicates needs to be done at calling function
        if     (isset($metaVal['last_update'])){ $metaVal['last_update'] = biz_date(); }
        elseif (isset($metaVal['date_last']))  { $metaVal['date_last'] = biz_date(); }
        $metaVal[$defs['index']]= $defs['title'];
        $newID   = $_GET['newID'] = dbMetaSet(0, $this->metaPrefix, $metaVal, $defs['_table'], $defs['_refID']);
        msgLog("$this->mgrTitle - ".lang('copy')." $oldTitle => {$metaVal[$defs['index']]}");
        $data    = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dg{$this->domSuffix}'); accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&table={$defs['table']}', $newID);"]];
        $layout  = array_replace_recursive($layout, $data);
    }
    
    /**
     * Adds a new task to the list, not possible to edit, need to delete and re-save a new task
     * @param array $layout - structure coming in typically []
     * @return array - modified $layout
     */
    protected function saveDB(&$layout=[])
    {
        global $io;
        $rID   = clean('id', 'integer', 'post');
        mapMetaDataToDB($this->struc); // maps meta structure to db structure
        $values= requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main'));
        $values['journal_id'] = $this->journalID;
        if (empty($rID) && isset($this->nextRefIdx)) { $values['invoice_num'] = getNextReference($this->nextRefIdx); }
//        $values['closed'] = 1; // removed this for audits to allow re-opening
        msgDebug("\nUpdating journal main with values = ".print_r($values, true));
        $mID = dbWrite(BIZUNO_DB_PREFIX.'journal_main', $values, $rID?'update':'insert', "id=$rID");
        if (empty($rID)) { $rID = $_POST['id'] = $mID; }
        if ($io->uploadSave('file_attach', "{$this->attachPath}rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['attach'=>'1'], 'update', "id=$rID");
        }
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("$this->mgrTitle - ".lang('save')." - {$values['invoice_num']} ($rID)");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#acc{$this->domSuffix}').accordion('select', 0); bizGridReload('dg{$this->domSuffix}'); jqBiz('#dtl{$this->domSuffix}').html('&nbsp;');"]]);
    }

    protected function saveMeta(&$layout=[], $args=[])
    {
        global $io;
        // form has _rID set, NOT rID so clean accordingly
        $defs   = array_replace(['_rID'=>clean('_rID', 'integer', 'post'), '_refID'=>clean('_refID', 'integer', 'post'), '_table'=>clean('_table', ['format'=>'db_field', 'default'=>'common'], 'post'), 'tabID'=>0], $args);
        msgDebug("\nEntering saveMeta with args = ".print_r($defs, true));
        $metaVal= !empty($defs['_rID']) ? dbMetaGet($defs['_rID'], $this->metaPrefix, $defs['_table'], $defs['_refID']) : [];
        $output = metaUpdate($metaVal, $this->struc);
        if (empty($defs['_rID']) && isset($this->nextRefIdx)) { $output['ref_num'] = getNextReference($this->nextRefIdx); }
        else { $output['ref_num'] = !empty($output['ref_num']) ? $output['ref_num'] : lang('unknown'); }
        if     (isset($output['last_update'])){ $output['last_update']= biz_date(); }
        elseif (isset($output['date_last']))  { $output['date_last']  = biz_date(); }
        msgDebug("\nWriting fetched metaVal = ".print_r($output, true));
        $result = dbMetaSet($defs['_rID'], $this->metaPrefix, $output, $defs['_table'], $defs['_refID']);
        if (empty($defs['_rID'])) { $defs['_rID'] = $_POST['_rID'] = $result; } // only for inserts
        $io->uploadSave("atch{$this->domSuffix}", "{$this->attachPath}{$output['ref_num']}_{$defs['_rID']}_");
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("$this->mgrTitle - ".lang('save').": ".(!empty($output['title']) ? $output['title'] : ''));
        $data  = ['content'=>['action'=>'eval','actionData'=>"jqBiz('#acc{$this->domSuffix}').accordion('select',{$defs['tabID']}); bizGridReload('dg{$this->domSuffix}'); jqBiz('#dtl{$this->domSuffix}').html('&nbsp;');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Deletes a task from the journal
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    protected function deleteDB(&$layout=[], $security=0, $args=[])
    {
        global $io;
        if (!$rID = clean('rID', 'integer', 'get')) { return msgAdd(lang('illegal_access')); }
        $jEntry = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$rID");
        dbGetResult('DELETE FROM '.BIZUNO_DB_PREFIX."journal_main WHERE id=$rID");
        $io->fileDelete("{$this->attachPath}rID_{$rID}_*.zip");
        msgLog("$this->mgrTitle - ".lang('delete').": {$jEntry['description']} - ".viewFormat($jEntry['post_date'], 'date')." ($rID)");
        $jsData = "jqBiz('#acc{$this->domSuffix}').accordion('select', 0); jqBiz('#dg{$this->domSuffix}').datagrid('reload'); jqBiz('#dtl{$this->domSuffix}').html('&nbsp;');";
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>$jsData]]);
    }

    /**
     * 
     * @global type $io
     * @param type $layout
     * @param type $args
     * @return type
     */
    protected function deleteMeta(&$layout=[], $args=[])
    {
        global $io;
        $defs   = array_replace(['_rID'=>clean('rID', 'integer', 'get'), '_table'=>'common', 'tabID'=>0], $args);
        msgDebug("\nEntering deleteMeta with args = ".print_r($defs, true));
        if (empty($defs['_rID'])) { return msgAdd('Illegal Access!'); }
        $metaVal= dbMetaGet($defs['_rID'], $this->metaPrefix, $defs['_table']);
        dbMetaDelete($defs['_rID'], $defs['_table']);
        msgLog("$this->mgrTitle - ".lang('delete').": {$metaVal['title']}");
        $io->fileDelete("{$this->attachPath}rID_{$defs['_rID']}_*.zip");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#acc{$this->domSuffix}').accordion('select',{$defs['tabID']}); bizGridReload('dg{$this->domSuffix}'); jqBiz('#dtl{$this->domSuffix}').html('&nbsp;');"]]);
    }

    protected function export()
    {
        global $io;
        $header= $output = [];
        $rows  = dbMetaGet('%', $this->metaPrefix);
        foreach ($rows as $idx => $row) {
            $line = [];
            foreach ($this->struc as $key => $value) {
                if (empty($value['label'])) { continue; } // skip hidden fields
                if (empty($idx)) { $header[] = csvEncapsulate($value['label']); } // create the heading row
                $line[]= csvEncapsulate(trim(!empty($value['format']) ? viewFormat($row[$key], $value['format']) : $row[$key]));
            }
            $output[] = implode(',', $line);            
        }
        array_unshift($output, implode(',', $header)); // prepend the heading row
        msgLog($this->domSuffix.' - '.lang('download'));
        $io->download('data', implode("\n", $output), $this->domSuffix.'-'.biz_date('Y-m-d').'.csv');
    }

    /**
     * Shared structure for the editor of tasks and journal entries
     * @param integer $security - security settings of the current user
     * @param array $args - arguments for the method
     * @return structure for the specified editor
     */
    protected function setEdit($security, $args=[], $admin=false)
    {
        $defs = array_replace(['dom'=>'div', '_rID'=>0, '_refID'=>0, '_table'=>'common', 'title'=>lang('new'), 'xGet'=>''], $args);
        $data = ['type'=>'divHTML',
            'divs'    => [
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>"tb{$this->domSuffix}"],
                'heading'=> ['order'=>15,'type'=>'html',   'html'=>"<h1>{$defs['title']}</h1>"],
                'formBOF'=> ['order'=>20,'type'=>'form',   'key' =>"frm{$this->domSuffix}"],
                'formEOF'=> ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'panels'  => [],
            'toolbars'=> ["tb{$this->domSuffix}"=>['icons'=>[
                'save' => ['order'=>20,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frm{$this->domSuffix}').submit();"]],
                'new'  => ['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '{$defs['title']}', '$this->moduleID/$this->pageID/".($admin?'adminEdit':'edit')."&dom={$defs['dom']}&table={$defs['_table']}{$defs['xGet']}', 0);"]],
                'copy' => ['order'=>50,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_copy')."'); if (title!=null) jsonAction('$this->moduleID/$this->pageID/" .($admin?'adminCopy'  :'copy')  ."&refID={$defs['_refID']}&table={$defs['_table']}{$defs['xGet']}','{$defs['_rID']}', title);"]],
                'trash'=> ['order'=>80,'hidden'=>$security>3 && $defs['_rID']?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/".($admin?'adminDelete':'delete')."&refID={$defs['_refID']}&table={$defs['_table']}{$defs['xGet']}','{$defs['_rID']}');"]]]]],
            'forms'   => ["frm{$this->domSuffix}"=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/".($admin?'adminSave':'save')."{$defs['xGet']}"]]],
            'fields'  => $this->struc,
            'jsReady' => ['init'=>"ajaxForm('frm{$this->domSuffix}');"]];
        $tree = $this->extractTabTree();
        if (sizeof($tree)==1 && isset($tree['undefined'])) { // no tabs
            $data['divs']['content']= ['order'=>50, 'type'=>'divs', 'classes'=>['areaView'], 'divs'=>[]];
            $this->buildPanels($data, array_shift($tree));
        } else { // build the tabs
            $data['divs']['tabs']   = ['order'=>50, 'type'=>'tabs', 'key' =>"tab{$this->domSuffix}"];
            $this->buildTabs($data, $tree);
        }
        return $data;
    }

    /**
     * Extracts the field ID's and puts it into the proper location on the page
     * @return type
     */
    private function extractTabTree()
    {
        $tree = [];
        foreach ($this->struc as $field => $value) {
            if (!isset($value['tab']))  { $value['tab']  = 'undefined'; }
            if (!isset($value['panel'])){ $value['panel']= 'general'; }
            if (empty($tree[$value['tab']][$value['panel']])) { $tree[$value['tab']][$value['panel']] = []; }
            $tree[$value['tab']][$value['panel']][] = $field;
        }
        msgDebug("\nReturning from extractTabTree with tree = ".print_r($tree, true));
        return $tree;
    }
    
    /**
     * When no tabs, set the structure for the panels
     * @param type $data
     * @param type $tree
     */
    private function buildPanels(&$data, $tree=[])
    {
        $order = 40;
        foreach ($tree as $key => $fields) {
            $data['divs']['content']['divs'][$key]= ['order'=>$order,'type'=>'panel','key'=>$key, 'classes'=>['block33']];
            $data['panels'][$key]                 = ['type'=>'fields','label'=>lang($key),'keys'=>$fields];
            $order = $order + 4;
        }
    }
    
    /**
     * Set the structure for a page with tabs and panels
     * @param type $data
     * @param type $tree
     */
    private function buildTabs(&$data, $tree=[])
    {
        $data['tabs']["tab{$this->domSuffix}"]['divs'] = [];
        $tOrder = 10;
        foreach ($tree as $tabID => $panels) {
            $data['tabs']["tab{$this->domSuffix}"]['divs'][$tabID] = ['order'=>$tOrder,'label'=>lang($tabID),'type'=>'divs','classes'=>['areaView'],'divs'=>[]];
            $pOrder = 10;
            foreach ($panels as $pnlID => $fields) { // populate the tab with the panels
                $data['tabs']["tab{$this->domSuffix}"]['divs'][$tabID]['divs'][$pnlID] = ['order'=>$pOrder, 'type'=>'panel', 'classes'=>['block33'], 'key'=>$pnlID];
                $data['panels'][$pnlID] = ['type'=>'fields','label'=>lang($pnlID),'keys'=>$fields];
                $pOrder = $pOrder + 5;
            }
            $tOrder = $tOrder + 5;
        }
    }
}
