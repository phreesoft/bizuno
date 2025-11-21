<?php
/*
 * Main methods for Phreeform
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
 * @version    7.x Last Update: 2025-07-16
 * @filesource /controllers/phreeform/main.php
 */

namespace bizuno;

class phreeformMain extends mgrJournal
{
    public    $moduleID  = 'phreeform';
    public    $pageID    = 'main';
    protected $secID     = 'phreeform';
    protected $domSuffix = 'Phreeform';
    protected $metaPrefix= 'phreeform';
    private   $limit = 20; // limit the number of results for recent reports
    public $struc;
    public $canAdd;
    public $mgrTitle; 

    function __construct()
    {
        parent::__construct();
        $this->canAdd = validateAccess($this->secID, 2, false);
        $this->mgrTitle = lang('reports');
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [ 
            '_rID'       => ['panel'=>'general','order'=> 1,                             'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]], // For common_meta
            'parent_id'  => ['panel'=>'general','order'=> 1,                             'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]],
            'mime_type'  => ['panel'=>'general','order'=> 1,                             'clean'=>'db_field','attr'=>['type'=>'hidden']],
            'title'      => ['panel'=>'general','order'=>25,'label'=>lang('title'),      'clean'=>'text',    'attr'=>['type'=>'text',    'value'=>'']],
            'bookmark'   => ['panel'=>'general','order'=>30,'label'=>lang('bookmark'),   'clean'=>'boolean', 'attr'=>['type'=>'checkbox']],
            'users'      => ['panel'=>'general','order'=>35,'label'=>lang('users'),      'clean'=>'array',   'attr'=>['type'=>$this->canAdd?'select':'hidden','name'=>'users[]', 'value'=>[-1]],
                'options'=> ['multiple'=>'true','multivalue'=>'true'], 'values'=>listUsers()],
            'roles'      => ['panel'=>'general','order'=>40,'label'=>lang('roles'),      'clean'=>'array',   'attr'=>['type'=>$this->canAdd?'select':'hidden','name'=>'roles[]', 'value'=>[-1]],
                'options'=> ['multiple'=>'true','multivalue'=>'true'], 'values'=>listRoles()],
            'description'=> ['panel'=>'general','order'=>45,'label'=>lang('description'),'clean'=>'text',    'attr'=>['type'=>'textarea','readonly'=>true,'value'=>'']],
            'create_date'=> ['panel'=>'general','order'=>50,'label'=>lang('date_create'),'clean'=>'date',    'attr'=>['type'=>$this->canAdd?'date':'hidden','readonly'=>true,'value'=>biz_date()]],
            'last_update'=> ['panel'=>'general','order'=>55,'label'=>lang('date_update'),'clean'=>'date',    'attr'=>['type'=>$this->canAdd?'date':'hidden','readonly'=>true,'value'=>'']]];
    }

    /**
     * Generates the structure for the PhreeForm home page
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID', ['format'=>'integer','default'=>0], 'get');
        $gID = clean('gID', 'text', 'get');
        $divSrch= html5('', ['options'=>['mode'=>"'remote'",'url'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/search'",'editable'=>'true','idField'=>"'id'",'textField'=>"'text'",'width'=>250,'panelWidth'=>400,
            'onClick'=>"function (row) { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID='+row.id); }"],'attr'=>['type'=>'select']]);
        $data   = ['title'=> lang('reports'),
            'divs'   => [
                'toolbar'  => ['order'=>10,'type'=>'toolbar','key' =>'tbManager'],
                'body'     => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'tree'   => ['order'=>20,'type'=>'panel','key'=>'accDoc','classes'=>['block33']],
                    'search' => ['order'=>40,'type'=>'panel','key'=>'myDocs','classes'=>['block33']],
                    'history'=> ['order'=>60,'type'=>'panel','key'=>'myHist','classes'=>['block33']]]]],
            'toolbars' => ['tbManager'=>['icons'=>[
                'mimeRpt' => ['order'=>30,'icon'=>'mimeTxt','hidden'=>($security>1)?false:true,'events'=>['onClick'=>"hrefClick('$this->moduleID/design/edit&type=rpt', 0);"],
                    'label'=>$this->lang['new_report']],
                'mimeFrm' => ['order'=>40,'icon'=>'mimeDoc','hidden'=>($security>1)?false:true,'events'=>['onClick'=>"hrefClick('$this->moduleID/design/edit&type=frm', 0);"],
                    'label'=>$this->lang['new_form']],
                'import'  => ['order'=>90,'hidden'=>($security>1)?false:true, 'events'=>['onClick'=>"hrefClick('phreeform/io/manager');"]]]]],
            'panels' => [
                'accDoc'    => ['type'=>'accordion','key' =>'accDocs','options'=>['height'=>640]],
                'myDocs'    => ['type'=>'divs','divs'=>[
                    'search'=> ['type'=>'panel','key'=>'docSearch'],
                    'panel' => ['type'=>'panel','key'=>'myBookMark']]],
                'docSearch' => ['label'=>lang('search'),               'type'=>'html','html'=>$divSrch],
                'myBookMark'=> ['label'=>$this->lang['my_favorites'],  'type'=>'html','id'=>'myBookMark','options'=>['collapsible'=>'true','href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/main/favorites'"],'html'=>'&nbsp;'],
                'myHist'    => ['label'=>$this->lang['recent_reports'],'type'=>'html','options'=>['collapsible'=>'true','href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/main/recent'"],   'html'=>'&nbsp;']],
            'accordion'=> ['accDocs'=>['styles'=>['height'=>'100%'],'divs'=>[ // 'attr'=>['halign'=>'left'], crashes older versions of Chrome and Safari
                'divTree'  => ['order'=>10,'label'=>$this->lang['my_reports'],'type'=>'divs','styles'=>['overflow'=>'auto','padding'=>'10px'], // 'attr'=>['titleDirection'=>'up'],
                    'divs'=>[
                        'toolbar'=> ['order'=>10,'type'=>'fields','keys'=>['expand','collapse']],
                        'tree'   => ['order'=>50,'type'=>'tree',  'key' =>'treePhreeform']]],
                'divDetail'=> ['order'=>30,'label'=>lang('details'),'type'=>'html','html'=>'&nbsp;']]]], // 'attr'=>['titleDirection'=>'up'],
            'tree'     => ['treePhreeform'=>['attr'=>['type'=>'tree','url'=>BIZUNO_URL_AJAX."&bizRt=phreeform/main/managerTree"],'events'=>[
                'onClick'  => "function(node) { if (typeof node.id != 'undefined') {
    if (jqBiz('#treePhreeform').tree('isLeaf', node.target)) { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/main/edit&rID='+node.id); }
    else { jqBiz('#treePhreeform').tree('toggle', node.target); } } }"]]],
            'fields'   => [
                'expand'  => ['events'=>['onClick'=>"jqBiz('#treePhreeform').tree('expandAll');"],  'attr'=>['type'=>'button','value'=>lang('expand_all')]],
                'collapse'=> ['events'=>['onClick'=>"jqBiz('#treePhreeform').tree('collapseAll');"],'attr'=>['type'=>'button','value'=>lang('collapse_all')]]]];
        if (!empty($rID)) {
            $data['tree']['treePhreeform']['events']['onLoadSuccess'] = "function() { var node=jqBiz('#treePhreeform').tree('find','$rID'); jqBiz('#treePhreeform').tree('expandTo',node.target);
jqBiz('#treePhreeform').tree('expand', node.target); }";
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Gets the available forms/reports from a JSON call in the database and returns to populate the tree grid
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function managerTree(&$layout=[])
    {
        $id     = clean('id', 'integer', 'post');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $allRpts= getMetaCommon('phreeform_cache');
        $tree   = [];
        foreach ($allRpts as $row) { if (validateUsersRoles($row)) { $tree[] = $row; } }
        msgDebug("\n phreeform number of rows returned: ".sizeof($allRpts)." and security filtered to ".sizeof($tree));
        if (sizeof($tree) == 0) { // no documents, make sure the folder shows as a folder so they can add docs!
            $data = ['_rID'=>0,'text'=>lang('home'),'children'=>[['text'=>lang('msg_no_documents')]],'attributes'=>json_encode(['mime_type'=>'dir'])];
        } else {
            $data = $id ? viewTree($tree, $id) : ['_rID'=>0,'text'=>lang('home'),'attributes'=>json_encode(['mime_type'=>'dir']),'children'=>viewTree($tree, 0)];
        }
        msgDebug("\nLength before trimming = ".sizeof($data));
        trimTree($data);
        msgDebug("\nLength after trimming = ".sizeof($data));
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>'['.json_encode($data, JSON_UNESCAPED_UNICODE).']']);
    }

    /**
     * Builds the right div for details of a requested report/form. Returns div structure
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $meta = !empty($rID) ? dbMetaGet($rID, $this->metaPrefix) : ['title'=>lang('new')];
        $args = ['dom'=>'div', 'title'=>lang('edit')." - {$meta['title']}"];
        parent::editMeta($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['save'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['new']);
        $layout['divs']['content']['divs']['general']['classes'] = ['block50'];
        $data   = [
            'toolbars'  => ["tb{$this->domSuffix}"=>['hideLabels'=>true,'icons'=>[
                'open'  => ['order'=>10,'events'=>['onClick'=>"winOpen('phreeformOpen', '$this->moduleID/render/open&rID=$rID');"]],
                'edit'  => ['order'=>20,'hidden'=>($security>1)?false:true,
                    'events'=>['onClick'=>"window.location.href='".BIZUNO_URL_PORTAL."&bizRt=phreeform/design/edit&rID=$rID';"]],
                'rename'=> ['order'=>30,'hidden'=>($security>2)?false:true,
                    'events'=>['onClick'=>"var title=prompt('".lang('msg_entry_rename')."'); if (title !== null) { jsonAction('$this->moduleID/$this->pageID/rename', '$rID', title); }"]],
                'export'=> ['order'=>60,'hidden'=>($security>2)?false:true,
                    'events'=>['onClick'=>"window.location.href='".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/io/export&rID=$rID';"]]]]]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Generates the structure and executes the report/form renaming operation
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function rename(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return; }
        $rID   = clean('rID',  'integer', 'get');
        $title = clean('data', 'text', 'get');
        if (empty($rID) || empty($title)) { return msgAdd($this->lang['err_rename_fail']); }
        $meta  = dbMetaGet(0, $this->metaPrefix);
        $metaID= metaIdxClean($meta);
        $meta['title']      = $title;
        $meta['last_update']= biz_date();
        dbMetaSet($metaID, $this->metaPrefix, $meta);
        msgLog($this->mgrTitle.' - '.lang('rename')." $title");
        $data  = ['content'=>['action'=>'eval','actionData'=>"bizTreeReload('treePhreeform'); bizPanelRefresh('myBookMark'); jqBiz('#docRecent').panel('refresh'); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID=$rID');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Generates the structure take a report and create a copy, add to the database
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $rID  = clean('rID',  'integer', 'get');
        $title= clean('data', 'text', 'get');
        if (empty($rID) || empty($title)) { return msgAdd($this->lang['err_copy_fail']); }
        parent::copyMeta($layout);
        $newID = clean('newID', 'integer', 'get');
        $layout['content']['actionData'] = "bizTreeReload('treePhreeform'); bizPanelRefresh('myBookMark'); jqBiz('#docRecent').panel('refresh'); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID=$newID');";
    }

    /**
     * Creates the structure to to accept a database record id and deletes a report
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout);
        dbReportsCache(); // rebuild the reports cache
        $layout['content']['actionData']  = "jqBiz('#accDocs').accordion('select', 0); bizTreeReload('treePhreeform'); bizPanelRefresh('myBookMark'); jqBiz('#docRecent').panel('refresh');";
    }

    /**
     *
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function favorites(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $myBMs = getMetaContact(getUserCache('profile', 'userID'), 'bookmarks_phreeform');
        msgDebug("\nRead favorite bookmarks: ".print_r($myBMs, true));
        $docs  = !empty($myBMs) ? dbGetMulti(BIZUNO_DB_PREFIX.'common_meta', "id IN (".implode(',', $myBMs).")") : [];
        $filtered = [];
        foreach ($docs as $doc) {
            $report = json_decode($doc['meta_value'], true);
            if (validateUsersRoles($report)) {  $filtered[] = ['id'=>$doc['id'], 'title'=>$report['title'], 'mime_type'=>$report['mime_type']]; }
        }
        $output= sortOrder($filtered, 'title');
        if (empty($output)) { $rows[] = "<span>".lang('msg_no_documents')."</span>"; }
        else { foreach ($output as $doc) {
            $btnHTML= html5('', ['icon'=>viewMimeIcon($doc['mime_type'])]).$doc['title'];
            $rows[] = html5('', ['attr'=>['type'=>'a','value'=>$btnHTML],
                'events'=>['onClick'=>"jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID='+{$doc['id']});"]]);
        } }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['body'=>['order'=>50,'type'=>'list','key'=>'reports']], 'lists'=> ['reports'=>$rows]]);
    }

    /**
     *
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function recent(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $output= [];
        $meta  = dbMetaGet('%', $this->metaPrefix);
        $docs  = sortOrder($meta, 'last_update', 'desc');
        msgDebug("\nread sorted docs of size = ".sizeof($docs));
        $cnt = 0;
        foreach ($docs as $doc) {
            if ($doc['mime_type']=='dir') { continue; }
            if (validateUsersRoles($doc)) { $output[] = $doc; $cnt++;}
            if ($cnt >= $this->limit) { break; }
        }
        msgDebug("\nAfter validate, sizeof output = ".sizeof($output));
        if (empty($output)) { $rows[] = "<span>".lang('msg_no_documents')."</span>"; }
        else { foreach ($output as $doc) {
                $btnHTML= html5('', ['icon'=>viewMimeIcon($doc['type'])]).$doc['title'];
                $rows[] = html5('', ['attr'=>['type'=>'a','value'=>$btnHTML],
                    'events'=>['onClick'=>"jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID='+{$doc['_rID']});"]]);
        } }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['body'=>['order'=>50,'type'=>'list','key'=>'reports']],'lists'=>['reports'=>$rows]]);
    }

    /**
     *
     * @param array $layout - Structure coming in
     * @return array - Modified $layout
     */
    public function search(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $output = [];
        $search = getSearch(['search','q']);
        if (empty($search)) {
            $output[] = ['id'=>'','text'=>lang('no_results')];
        } else {
            $rptMeta= dbMetaGet('%', $this->metaPrefix);
            foreach ($rptMeta as $rpt) {
                if (strpos($rpt['title'], $search) && validateUsersRoles($rpt)) { $output[] = ['id'=>$rpt['_rID'], 'text'=>$rpt['title']]; }
            }
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode($output)]);
    }
}
