<?php
/*
 * @name Bizuno ERP - Document Manager Extension
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
 * @filesource /controllers/office/docs.php
 *
 */

namespace bizuno;

class officeDocs extends mgrJournal
{
    public    $moduleID  = 'office';
    public    $pageID    = 'docs';
    protected $secID     = 'mgr_docs';
    protected $domSuffix = 'Docs';
    protected $metaPrefix= 'document';
    private   $limit     = 25; // limits the number of results for recent docs

    function __construct()
    {
        parent::__construct();
        $this->mgrTitle = $this->lang['docs_title'];
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
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
            'filename'   => ['panel'=>'general','order'=> 1,                             'clean'=>'filename','attr'=>['type'=>'hidden',  'value'=>'']],
            'parent_id'  => ['panel'=>'general','order'=> 1,                             'clean'=>'integer', 'attr'=>['type'=>'hidden',  'value'=>0]],
            'mime_type'  => ['panel'=>'general','order'=> 1,                             'clean'=>'db_field','attr'=>['type'=>'hidden']],
            'docfile'    => ['panel'=>'general','order'=>20,'label'=>lang('upload_new'), 'clean'=>'',        'attr'=>['type'=>'file']],
            'title'      => ['panel'=>'general','order'=>25,'label'=>lang('title'),      'clean'=>'text',    'attr'=>['type'=>'text',    'value'=>'']],
            'bookmark'   => ['panel'=>'general','order'=>30,'label'=>lang('bookmark'),   'clean'=>'boolean', 'attr'=>['type'=>'checkbox']],
            'users'      => ['panel'=>'general','order'=>35,'label'=>lang('users'),      'clean'=>'array',   'attr'=>['type'=>'select','name'=>'users[]','value'=>[-1]],
                'options'=>['multiple'=>'true','multivalue'=>'true'], 'values'=>listUsers()],
            'roles'      => ['panel'=>'general','order'=>40,'label'=>lang('roles'),      'clean'=>'array',   'attr'=>['type'=>'select','name'=>'roles[]','value'=>[-1]],
                'options'=>['multiple'=>'true','multivalue'=>'true'], 'values'=>listRoles()],
            'description'=> ['panel'=>'general','order'=>45,'label'=>lang('description'),'clean'=>'text',    'attr'=>['type'=>'textarea','value'=>'']],
            'create_date'=> ['panel'=>'general','order'=>50,'label'=>lang('date_create'),'clean'=>'date',    'attr'=>['type'=>'date', 'readonly'=>true, 'value'=>biz_date()]],
            'last_update'=> ['panel'=>'general','order'=>55,'label'=>lang('date_update'),'clean'=>'date',    'attr'=>['type'=>'date', 'readonly'=>true, 'value'=>'']],
            'owner'      => ['panel'=>'general','order'=>60,'label'=>lang('owner'),      'clean'=>'integer', 'attr'=>['type'=>'text', 'readonly'=>true, 'value'=>''],'format'=>'contactID'],
            'filename'   => ['panel'=>'general','order'=>65,'label'=>lang('filename'),   'clean'=>'filename','attr'=>['type'=>'text', 'readonly'=>true, 'value'=>'', 'size'=>64]]];
    }

    /**
     * Entry point for this extension
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID    = clean('rID', ['format'=>'integer','default'=>0], 'get');
        $js     = $this->mgrJS('treeDocs', 'treeMenu', $security);
        $divSrch= html5('', ['options'=>['mode'=>"'remote'",'url'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/search'",'editable'=>'true','idField'=>"'id'",'textField'=>"'text'",'width'=>250,'panelWidth'=>400,
            'onClick'=>"function (row) { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID='+row.id); }"],'attr'=>['type'=>'select']]);
        $data   = ['title'=> $this->mgrTitle,
            'divs' => [
                'title'=> ['order'=>10,'type'=>'html','html'=>"<h1>$this->mgrTitle</h1>"],
                'body' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'tree'   => ['order'=>20,'type'=>'panel','key'=>'accDoc','classes'=>['block50']],
                    'search' => ['order'=>40,'type'=>'panel','key'=>'myDocs','classes'=>['block50']]]]],
            'panels' => [
                'accDoc'   => ['type'=>'accordion','key' =>'accDocs','options'=>['height'=>640]],
                'myDocs'   => ['type'=>'divs','divs'=>[
                    'search'=> ['type'=>'panel','key'=>'docSearch'],
                    'panel' => ['type'=>'panel','key'=>'docBookMk'],
                    'hist'  => ['type'=>'panel','key'=>'myHist']]],
                'docSearch'=> ['label'=>lang('search'),               'type'=>'html','html'=>$divSrch],
                'docBookMk'=> ['label'=>$this->lang['my_bookmarks'],  'type'=>'html','options'=>['collapsible'=>'true','href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/docsBookmarked'"],'html'=>'&nbsp;'],
                'myHist'   => ['label'=>$this->lang['recently_added'],'type'=>'html','options'=>['collapsible'=>'true','href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/docsRecent'"],    'html'=>'&nbsp;']],
            'accordion'=> ['accDocs'=>['styles'=>['height'=>'100%'],'divs'=>[
                'divTree'  => ['order'=>10,'label'=>$this->lang['my_docs'],'type'=>'divs','styles'=>['overflow'=>'auto','padding'=>'10px'],
                    'divs'=>[
                        'toolbar'=> ['order'=>10,'type'=>'fields','keys'=>['expand','collapse']],
                        'tree'   => ['order'=>50,'type'=>'tree',  'key' =>'treeDocs']]],
                'divDetail'=> ['order'=>30,'label'=>lang('details'),'type'=>'html','html'=>'&nbsp;']]]],
            'tree'     => ['treeDocs'=>[
                'attr'  => ['dnd'=>true, 'animate'=>true, 'lines'=>true, 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/managerTree"],
                'events'=> [
                    'onClick'      => "function(node) { attr = JSON.parse(node.attributes); if (attr.mime_type != 'dir') { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/docs/edit&rID='+node.id); } }",
                    'onDrop'       => "function(target,source,point) { alert('dropped source id '+source.id+' on target id = '+target.id); }",
                    'onContextMenu'=> $js['onContextMenu'],
                    'onAfterEdit'  => "function(node) { docRename(node); }",
// This needs fixin. The event fires before the new data is rendered so the node doesn't exist. Perhaps find a way to load the entire tree at startup.
//        'onLoadSuccess'=> "function() { alert('rID = '+rID); var node=jqBiz('#treeDocs').tree('find', '0'); jqBiz('#treeDocs').tree('expandTo', node.target); jqBiz('#treeDocs').tree('expand', node.target); }",
                    ],
                'menu'  => ['attr'=>['id'=>'treeMenu', 'class'=>"easyui-menu", 'style'=>"width:120px;"],
                    'actions'=> [
                        'add'     => ['label'=>$this->lang['doc_add'],   'hidden'=>$security>1?false:true,'options'=>["iconCls"=>"'icon-docNew'"],'attr'=>['onClick'=>"docAdd('doc');"]],
                        'addDir'  => ['label'=>lang('add_folder'),       'hidden'=>$security>1?false:true,'options'=>["iconCls"=>"'icon-dirNew'"],'attr'=>['onClick'=>"docAdd('dir');"]],
                        'addGgl'  => ['label'=>$this->lang['add_google'],'hidden'=>$security>1?false:true,'options'=>["iconCls"=>"'icon-dirNew'"],'attr'=>['onClick'=>"if (gID = prompt('Enter Google Drive document ID:')) { docAdd('ggl', gID); }"]],
                        'edit'    => ['label'=>lang('edit'),             'hidden'=>$security>2?false:true,'options'=>["iconCls"=>"'icon-edit'"],  'attr'=>['onClick'=>"var node = jqBiz('#treeDocs').tree('getSelected'); jqBiz('#treeDocs').tree('beginEdit',node.target);"]],
                        'trash'   => ['label'=>lang('trash'),            'hidden'=>$security>3?false:true,'options'=>["iconCls"=>"'icon-trash'"], 'attr'=>['onClick'=>"docDel();"]],
                        'hr1'     => ['attr' =>['class'=>"menu-sep"]],
                        'expand'  => ['label'=>lang('expand'),  'options'=>["iconCls"=>"'icon-expand'"],  'attr'=>['onClick'=>"var node=jqBiz('#treeDocs').tree('getSelected'); jqBiz('#treeDocs').tree('expand',node.target);"]],
                        'collapse'=> ['label'=>lang('collapse'),'options'=>["iconCls"=>"'icon-collapse'"],'attr'=>['onClick'=>"var node=jqBiz('#treeDocs').tree('getSelected'); jqBiz('#treeDocs').tree('collapse',node.target);"]]]],
                'footnotes'=> ['help'=>lang('tip_right_click_for_options')]]],
            'fields' => [
                'expand'  => ['events'=>['onClick'=>"jqBiz('#treeDocs').tree('expandAll');"],  'attr'=>['type'=>'button','value'=>lang('expand_all')]],
                'collapse'=> ['events'=>['onClick'=>"jqBiz('#treeDocs').tree('collapseAll');"],'attr'=>['type'=>'button','value'=>lang('collapse_all')]]],
            'jsHead'   => ['init'=>$js['jsHead']]];
        if ($rID) { $data['jsReady']['jsOpen'] = "jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/docs/edit&rID=$rID');"; }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Sets the JavaScript for this extension
     * @param string $treeID - DOM id of the tree UI
     * @param sring $menuID - DOM id of the tree menu
     * @param integer $security - users security setting
     * @return JavaScript string
     */
    private function mgrJS($treeID, $menuID, $security)
    {
        $js = [];
        $js['onContextMenu'] = "function(e,node) {
    e.preventDefault();
    var security = $security;
    attr = JSON.parse(node.attributes);
    if (attr.mime_type != 'dir') jqBiz('#$menuID').menu('disableItem', jqBiz('#add')[0]);      else jqBiz('#$menuID').menu('enableItem', jqBiz('#add')[0]);
    if (attr.mime_type != 'dir') jqBiz('#$menuID').menu('disableItem', jqBiz('#addDir')[0]);   else jqBiz('#$menuID').menu('enableItem', jqBiz('#addDir')[0]);
    if (attr.mime_type != 'dir') jqBiz('#$menuID').menu('disableItem', jqBiz('#expand')[0]);   else jqBiz('#$menuID').menu('enableItem', jqBiz('#expand')[0]);
    if (attr.mime_type != 'dir') jqBiz('#$menuID').menu('disableItem', jqBiz('#collapse')[0]); else jqBiz('#$menuID').menu('enableItem', jqBiz('#collapse')[0]);
    jqBiz(this).tree('select',node.target);
    jqBiz('#$menuID').menu('show',{ left: e.pageX, top: e.pageY });
}";
        $js['jsHead'] = "var rID=0;
function docRename(node) {
    node.id;
    node.text;
    jqBiz.ajax({
        url:     bizunoAjax+'&bizRt=$this->moduleID/docs/rename&rID='+node.id+'&title='+node.text,
        success: function (data) { processJson(data); }
    });
}
function docAdd(type, gID){
    var node  = jqBiz('#$treeID').tree('getSelected');
    var title = prompt('Enter title:', '');
    if (title!=null) {
        jqBiz.ajax({
            url:     bizunoAjax+'&bizRt=$this->moduleID/docs/docNew&pID='+node.id+'&type='+type+'&title='+title+'&gID='+gID,
            success: function (data) {
                processJson(data);
                if (type != 'dir') rID = jqBiz('#id').val();
                jqBiz('#$treeID').tree('append', { parent:(node?node.target:null), data:[{ id:rID, text:title }] });
                var newNode = jqBiz('#$treeID').tree('find', rID);
                jqBiz('#$treeID').tree('select', newNode.target);
                jqBiz('#$treeID').tree('update', { target: newNode.target, iconCls: (type=='dir')?'icon-mimeDir':'icon-mimeTxt' });
            }
        });
    }
}
function docDel(){
    if (confirm('".jsLang('msg_confirm_delete')."')) {
        var node = jqBiz('#$treeID').tree('getSelected');
        jqBiz.ajax({
            url:     bizunoAjax+'&bizRt=$this->moduleID/docs/docDelete&rID='+node.id,
            success: function (data) { processJson(data); jqBiz('#$treeID').tree('reload'); }
        });
    }
}";
        return $js;
    }

    /**
     * AJAX call to fetch the tree contents
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function managerTree(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $metaVal = dbMetaGet('%', $this->metaPrefix);
        $output = [];
        foreach ($metaVal as $row) { if (validateUsersRoles($row)) { $output[] = $row; } }
        $sort0 = sortOrder($output,'title');
        $sort1 = sortOrder($sort0, 'mime_type');
        msgDebug("\n extDocs number of rows returned: ".sizeof($metaVal)." and security filtered to ".sizeof($sort1));
        $id = clean('id', 'integer', 'post');
        msgDebug("\nread docs = ".print_r($sort1, true));
        if (sizeof($sort1) == 0) { // no documents, make sure the folder shows as a folder so they can add docs!
            $data = ['_rID'=>0,'text'=>lang('home'),'children'=>[['text'=>lang('msg_no_documents')]],'mime_type'=>'dir'];
        } else {
            $data = !empty($id) ? viewTree($sort1, $id) : ['_rID'=>0,'text'=>lang('home'),'mime_type'=>'dir','children'=>viewTree($sort1, 0)];
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>'['.json_encode($data, JSON_UNESCAPED_UNICODE).']']);
    }

    /**
     * Adds a new document/folder to the tree
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docNew(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $title   = clean('title',['format'=>'text','default'=>lang('new_document')], 'get');
        $mime    = clean('type', ['format'=>'text','default'=>'txt'], 'get');
        $parent  = clean('pID', 'integer', 'get');
        $metaData= ['title'=> $title, 'parent_id'=>$parent, 'mime_type'=>$mime,
            'create_date'=>biz_date(), 'last_update'=>biz_date(), 'users'=>[getModuleCache('profile', 'userID')], 'roles'=>[]];
        if ($mime=='ggl') { $metaData['google_id'] = clean('gID', ['format'=>'text','default'=>''], 'get'); }
        $_GET['rID'] = $rID = dbMetaSet(0, $this->metaPrefix, $metaData);
        msgLog($this->mgrTitle.'-'.lang('new').": $title ($rID)");
        msgAdd($this->mgrTitle.'-'.lang('new').": $title", 'success');
        switch ($mime) {
            case 'ggl':
            case 'dir': $action = "jqBiz('#accDocs').accordion('select', 0); jqBiz('#treeDocs').tree('reload');"; break;
            case 'doc': $action = "jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID=$rID');";  break;
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>$action]]);
    }

    /**
     * Performs the doc search feature
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function search(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $search = getSearch(['search','q']);
        if (empty($search)) {
            $output[] = ['id'=>'','text'=>lang('no_results')];
        } else {
            $dbData = dbGetMulti(BIZUNO_DB_PREFIX.'extDocs', "mime_type<>'dir' AND title LIKE ('%$search%')", 'title');
            foreach ($dbData as $row) {
                if (validateUsersRoles($row)) { $output[] = ['id'=>$row['id'],'text'=>$row['title']]; }
            }
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode($output)]);
    }

    /**
     * Renames a tree node element
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function rename()
    {
        $rID  = clean('rID',   'integer','get');
        $title= clean('title', 'text',   'get');
        if (!$rID || !$title) { return msgAdd(); }
        $meta = metaIdxClean(dbGetMeta($rID, $this->metaPrefix));
        $oldTitle = $meta['title'];
        $meta['title'] = $title;
        dbMetaSet(0, $this->metaPrefix, $meta);
        msgLog($this->mgrTitle.'-'.lang('rename').": $oldTitle => $title ");
    }

    /**
     * Edits a document, can be either a locally stored document or Google embedded drive folder
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function edit(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $meta = !empty($rID) ? dbMetaGet($rID, $this->metaPrefix) : ['title'=>lang('new')];
        $args = ['dom'=>'div', 'title'=>lang('edit')." - {$meta['title']}"];
        parent::editMeta($layout, $security, $args);
        $files = $io->fileReadGlob($this->attachPath."rID_".str_pad($rID, 8, "0", STR_PAD_LEFT)."_");
        if (empty($files) && !empty($rID)) { msgAdd($this->lang['no_files'], 'caution'); }
        // need to get bookmarks from user meta
        $metaBM = dbMetaGet(0, 'bookmarks_docs', 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nRead bookmarks for user = ".print_r($metaBM, true));
        $isBM = (!empty($metaBM) && in_array($rID, $metaBM)) ? true : false;
        if ($meta['mime_type'] == 'ggl') { return $this->editGoogleDrive($layout, $meta['google_id']); }
        $data = [
            'divs'    => ['content'=>['order'=>50,'type'=>'divs','divs'=>[
                'general' => ['classes'=>['block50']], // change the width of the panel
                'dgHist'  => ['order'=>80,'type'=>'datagrid','key' =>'docHistory']]]],
            'toolbars'=> ["tb{$this->domSuffix}" =>['hideLabels'=>true, 'icons'=>[
                'download'=> ['order'=>60,'hidden'=>empty($files) || empty($rID)?true:false,'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src',bizunoAjax+'&bizRt=$this->moduleID/docs/docExport&rID=$rID');"]]]]],
            'datagrid'=> ['docHistory'=>$this->dgHistory('dgHistory', $rID, $security)],
            'fields'  => ['bookmark'=>['attr'=>['checked'=>$isBM]]]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Sets the iFrame HTML for a Google Drive linked document
     * @param array $layout - structure coming in
     * @return modified $structure
     * @param type $gID
     */
    private function editGoogleDrive(&$layout=[], $gID=0)
    {
//      $html = '<div><iframe src="https://drive.google.com/a/mydomain.com/embeddedfolderview?id='.urlencode($gID).'#list" width="100%" height="480" frameborder="0"></iframe></div>';
        $html = '<div><iframe src="https://drive.google.com/embeddedfolderview?id='.urlencode($gID).'#list" width="100%" height="480" frameborder="0"></iframe></div>';
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['iframe'=>['order'=>50,'type'=>'html','html'=>$html]]]);
    }

    /**
     * Load stored doc files
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function historyList(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd("Bad record ID!"); }
        $page  = clean('page','integer', 'post');
        $rows  = clean('rows','integer', 'post');
        $files = $io->fileReadGlob($this->attachPath."rID_".str_pad($rID, 8, "0", STR_PAD_LEFT)."_");
        foreach ($files as $key => $file) { // need to add the revision level
            $files[$key]['rev'] = str_replace('.dc', '', substr($file['name'], strrpos($file['name'], '_')+1));
            $files[$key]['id'] = $files[$key]['name'];
        }
        $output= sortOrder($files, 'rev', 'desc');
        $slice = array_slice($output, ($page-1)*$rows, $rows);
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$slice])]);
    }

    /**
     * Save user changes
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function save(&$layout=[])
    {
        global $io;
        $rID   = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        if (empty($rID)) { return msgAdd(lang('bad_id')); }
        $meta  = dbMetaGet($rID, $this->metaPrefix);
        $access= $this->cleanSecurity();
        // clean up the users and roles for conflicts
        $post = [
            'title'      => clean('title', 'text', 'post'),
            'description'=> clean('description', 'text', 'post'),
            'users'      => $access['users'],
            'roles'      => $access['roles'],
            'last_update'=> biz_date()];
        $metaVal = array_replace($meta, $post);
        if (isset($_FILES['docfile']) && is_uploaded_file($_FILES['docfile']['tmp_name'])) {
            if ($_FILES['docfile']["error"] == UPLOAD_ERR_OK && $_FILES['docfile']['size'] > 0) {
                $uploadname = $_FILES['docfile']['name'];
                $metaVal['mime_type']= substr($_FILES['docfile']['name'], strrpos($uploadname, '.')+1);
                $metaVal['filename'] = $uploadname;
                $metaVal['owner']    = getUserCache('profile', 'userID');
                $revs = $io->fileReadGlob($this->attachPath."rID_".str_pad($rID, 8, "0", STR_PAD_LEFT)."_", $io->getValidExt('file'));
                msgDebug("\nglob results: ".print_r($revs, true));
                $latest = 0;
                foreach ($revs as $file) { $latest = max($latest, str_replace('.dc', '', substr($file['name'], strrpos($file['name'], '_')+1))); }
                $latest++;
                $filename = "rID_".str_pad($rID, 8, "0", STR_PAD_LEFT)."_$latest";
                msgDebug("\nReady to move uploaded file $uploadname to $filename");
                $io->fileWrite(file_get_contents($_FILES['docfile']['tmp_name']), $this->attachPath.$filename);
            }
        }
        $newID = dbMetaSet($rID, $this->metaPrefix, $metaVal);
        if (empty($rID)) { $rID = $_POST['_rID'] = $newID; }
        $this->bookmarkUpdate($rID); // set/clear the bookmark
        msgLog($this->mgrTitle.'-'.lang('save').": {$metaVal['title']} ($rID)");
        msgAdd(lang('save').": {$metaVal['title']}", 'success');
        $action = "rID=$rID; jqBiz('#dgHistory').datagrid('reload');
jqBiz('#docRecent').panel('refresh');
jqBiz('#accDocs').accordion('select', 0); 
jqBiz('#treeDocs').tree('reload');";
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>$action]]);
    }

    /**
     * Deletes a document form the tree, db and file system
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docDelete(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (empty($rID)) { return msgAdd(lang('bad_id')); }
        $meta = dbMetaGet($rID, $this->metaPrefix);
        if ($this->hasChildren($meta['_rID'])) { return msgAdd($this->lang['msg_delete_error']); }
        $io->fileDelete($this->attachPath."rID_" . str_pad($rID, 8, "0", STR_PAD_LEFT) . "_*");
        msgLog($this->mgrTitle.'-'.lang('delete').": {$meta['title']} ($rID)");
        dbMetaDelete($rID);
        $action = "jqBiz('#accDocs').accordion('select', 0); jqBiz('#treeDocs').tree('reload'); jqBiz('#divDetail').panel('clear'); jqBiz('#docBookMk').panel('refresh'); jqBiz('#docRecent').panel('refresh');";
        $layout = array_replace_recursive($layout, ['content'=> ['action'=>'eval','actionData'=>$action]]);
    }

    private function hasChildren($metaID)
    {
        $docs = dbMetaGet('%', $this->metaPrefix);
        foreach ($docs as $doc) { if ($metaID==$doc['parent_id']) { return true; } }
    }
    
    /**
     * Deletes a document revision from the history grid
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docDeleteRev(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $docID = clean('rID', 'filename', 'get');
        if (!$docID) { return msgAdd('The document was not deleted, the proper id was not passed!'); }
        $parts = explode('_', $docID);
        $rev   = str_replace('.dc', '', array_pop($parts));
        $rID   = intval(array_pop($parts));
        $title = dbMetaGet($rID, $this->metaPrefix); //dbGetValue(BIZUNO_DB_PREFIX.'extDocs', 'title', "id='$rID'");
        msgDebug("\nDeleting file: $title with filename: ".$docID);
        $io->fileDelete($docID);
        msgLog($this->mgrTitle.'-'.lang('delete').": $title (Rev $rev)");
        $data['content'] = ['action'=>'eval','actionData'=>"jqBiz('#dgHistory').datagrid('reload');"];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Exports the selected document
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docExport()
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID    = clean('rID', 'integer','get');
        $fileID = clean('fileID', 'text','get');
        if ($fileID) { // specific rev, path is provided
            $tmpID = str_replace($this->attachPath, '', $fileID);
            $rID = intval(substr($tmpID, 4, 8)); // need the rID to get the filename
            $filename = $fileID;
        } else { // get the newest rev
            $path= "rID_".str_pad($rID, 8, "0", STR_PAD_LEFT)."_";
            $files = $io->fileReadGlob($this->attachPath.$path, ['']);
            $versions = array_pop($files);
            $filename = $versions['name'];
        }
        msgDebug("\nWorking with rID = $rID and filename = $filename");
        if (!$row = dbMetaGet($rID, $this->metaPrefix)) { return; }
        if (!$result = $io->fileRead($filename, 'rb')) { return; }
        msgLog($this->mgrTitle.'-'.lang('download')." (id: $rID)");
        msgDebug("\nReady to download filename {$row['filename']}");
        $io->download('data', $result['data'], $row['filename']);
    }

    /**
     * Gets a list of bookmarked documents for the specific user
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docsBookmarked(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $docs  = dbMetaGet('%', $this->metaPrefix);
        foreach ($docs as $key => $doc) { if (!validateUsersRoles($doc)) { unset($docs[$key]); } }
        $output= sortOrder($docs, 'title');
        $html  = html5('', ['classes'=>['easyui-datalist'],'options'=>['lines'=>'true','onClickRow'=>"function (idx, row) { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/docs/edit&rID='+row.value); }"],'attr'=>['type'=>'ul']]);
        if (sizeof($output) > 0) {
            foreach ($output as $doc) {
                if ($doc['mime_type']=='dir') { continue; }
                $html .= html5('', ['options'=>['value'=>$doc['_rID']],'attr'=>['type'=>'li']]);
                $html .= html5('', ['icon'=>viewMimeIcon($doc['mime_type']), 'size'=>'small', 'label'=>$doc['title']]).' '.$doc['title']."</li>";
            }
        } else { $html .= "<li>".lang('msg_no_documents')."</li>"; }
        $html .= "</ul>";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['body'=>['order'=>50,'type'=>'html','html'=>$html]]]);
    }

    /**
     * Gets a list of recently added documents for the main page
     * @param array $layout - structure coming in
     * @return modified $structure
     */
    public function docsRecent(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $output= [];
        $docs  = dbMetaGet('%', $this->metaPrefix);
        $data  = sortOrder($docs, 'last_update', 'desc');
        msgDebug("\nread sorted docs = ".print_r($data, true));
        $cnt = 0;
        foreach ($data as $doc) {
            if ($doc['mime_type']=='dir') { continue; }
            if (validateUsersRoles($doc)) { $output[] = $doc; $cnt++;}
            if ($cnt >= $this->limit) { break; }
        }
        msgDebug("\nAfter validate, output = ".print_r($output, true));
        $html  = html5('', ['classes'=>['easyui-datalist'],'options'=>['lines'=>'true','onClickRow'=>"function (idx, row) { jqBiz('#accDocs').accordion('select', 1); jqBiz('#divDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/edit&rID='+row.value); }"],'attr'=>['type'=>'ul']]);
        if (sizeof($output) > 0) {
            foreach ($output as $doc) {
                $html .= html5('', ['options'=>['value'=>$doc['_rID']],'attr'=>['type'=>'li']]);
                $html .= html5('', ['icon'=>viewMimeIcon($doc['mime_type']), 'size'=>'small', 'label'=>$doc['title']]).' '.viewDate($doc['last_update']).'-'.$doc['title']."</li>";
            }
        } else { $html .= "<li>".lang('msg_no_documents')."</li>"; }
        $html .= "</ul>";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['body'=>['order'=>50,'type'=>'html','html'=>$html]]]);
    }

    private function cleanSecurity()
    {
        $users = clean('users', 'array', 'post');
        $roles = clean('roles', 'array', 'post');
        if (sizeof($users)>1 && in_array($users, -1)) { $key=array_search(-1, $users); unset($users[$key]); }
        if (sizeof($roles)>1 && in_array($roles, -1)) { $key=array_search(-1, $roles); unset($roles[$key]); }
        if (sizeof($users)>1 && in_array($users,  0)) { $key=array_search( 0, $users); unset($users[$key]); }
        if (sizeof($roles)>1 && in_array($roles,  0)) { $key=array_search( 0, $roles); unset($roles[$key]); }
        if (empty($users) && empty($roles)) {
            msgAdd('No user selections were made, the value has been set to you so the document is not orphaned!', 'info'); 
            $users[] = getUserCache('profile', 'userID');
        }
        return ['users'=>$users, 'roles'=>$roles];
    }
    
    /**
     * Updates the bookmark meta for the user
     * @param type $docID
     */
    private function bookmarkUpdate($docID=0)
    {
        $bm   = clean('bookmark', 'char', 'post');
        $conBM= dbMetaGet(0, 'bookmarks_docs', 'contacts', getUserCache('profile', 'userID'));
        $rID  = !empty($conBM) ? $conBM['_rID'] : 0;
        $data = metaIdxClean($conBM);
        if     ( empty($rID) && !empty($bm)) { dbMetaSet(0, 'bookmarks_docs', [$docID], 'contacts', getUserCache('profile', 'userID')); }
        elseif (!empty($rID)) { // test to set/clear
            $key=array_search($docID, $data);
            if     ($key!==false &&  empty($bm)) { unset($data[$key]); } // clear it
            elseif ($key===false && !empty($bm)) { $data[] = $docID; } // set it
            if (empty($data)) { // delete the meta record
                dbMetaDelete($rID);
            } else { // add/update it
                dbMetaSet($rID, 'bookmarks_docs', $data, 'contacts', getUserCache('profile', 'userID'));            
            }
        }
    }
    
    /**
     * Grid structure for document history
     * @param string $name - grid name
     * @param integer $rID - database record id of the document to retrieve revision history
     * @param integer $security - users security setting
     * @return array - grid structure
     */
    private function dgHistory($name, $rID, $security)
    {
        return ['id'=>$name, 'title'=>lang('history'),
            'attr'   => ['idField'=>'id', 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/historyList&rID=$rID"],
            'events' => [], // add data:docsHistory
            'columns'=> ['id'=>['order'=>0,'attr'=>['hidden'=>true]],
                'action'=> ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'download'=> ['order'=>30,'icon'=>'download','events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src',bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/docExport&fileID=idTBD');"]],
                        'trash'   => ['order'=>90,'icon'=>'trash',   'label'=>lang('delete'), 'hidden'=> $security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/docDeleteRev', 'idTBD');"]]]],
                'rev' => ['order'=>10,'label'=>lang('revision'),'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'size'=> ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'date'=> ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=>100,'align'=>'center','resizable'=>true]]]];
    }
}
