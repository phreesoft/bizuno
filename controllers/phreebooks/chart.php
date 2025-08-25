<?php
/*
 * Methods related to the chart of accounts used in PhreeBooks
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
 * @version    7.x Last Update: 2025-08-23
 * @filesource /controllers/phreebooks/chart.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');

class phreebooksChart extends mgrJournal
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'chart';
    protected $secID     = 'admin';
    protected $domSuffix = 'Chart';
    protected $metaPrefix= 'chart_of_accounts';
    public $lang;
    public $chartMeta;
    public $chartMetaID;

    function __construct()
    {
        parent::__construct();
        $this->lang       = getLang($this->moduleID);
        $meta  = dbMetaGet(0, $this->metaPrefix);
        $this->chartMetaID= metaIdxClean($meta);
        $this->chartMeta  = $this->validateMeta($meta); // if chart acct ID's are integers then the array reindexes the keys to 0, 1, 2, 3 ...
        msgDebug("\nRead meta ID = $this->chartMetaID and sizeof chart = ".sizeof($this->chartMeta));
        $this->managerSettings();
        $this->fieldStructure();
    }
    private function validateMeta($meta) // Fixes charts that have integer keys
    {
        $output = [];
        foreach ((array)$meta as $row) { $output[$row['id']] = $row; }
        return $output;
    }
    private function fieldStructure()
    {
        $this->struc = [
            'id'      => ['panel'=>'general','order'=>10,'label'=>lang('gl_account'),     'clean'=>'text',     'attr'=>['type'=>'text',    'value'=>'', 'readonly'=>true]],
            'default' => ['panel'=>'general','order'=>70,'label'=>lang('default'),        'clean'=>'integer',  'attr'=>['type'=>'selNoYes']],
            'inactive'=> ['panel'=>'general','order'=>30,'label'=>lang('inactive'),       'clean'=>'boolean',  'attr'=>['type'=>'selNoYes','value'=> 0]],
            'type'    => ['panel'=>'general','order'=>40,'label'=>lang('type'),           'clean'=>'integer',  'attr'=>['type'=>'select',  'value'=>''],'options'=>['width'=>250],'values'=>selGLTypes()],
            'cur'     => ['panel'=>'general','order'=> 1,'label'=>lang('currency'),       'clean'=>'alpha_num','attr'=>['type'=>'hidden',  'value'=>getDefaultCurrency()]],
            'title'   => ['panel'=>'general','order'=>20,'label'=>lang('title'),          'clean'=>'text',     'attr'=>['type'=>'text',    'value'=>'']],
            'parent'  => ['panel'=>'general','order'=>60,'label'=>lang('primary_gl_acct'),'clean'=>'filename', 'attr'=>['type'=>'ledger',  'value'=>'']],
            'heading' => ['panel'=>'general','order'=>50,'label'=>lang('heading'),        'clean'=>'integer',  'attr'=>['type'=>'selNoYes']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $yes_no_choices = [['id'=>'a','text'=>lang('all')], ['id'=>'0','text'=>lang('active')], ['id'=>'1','text'=>lang('inactive')]];
        $defs  = array_replace(['textCopy'=>'Enter the new account ID:'], $args);
        $ftNote= lang('color_codes').': <span class="row-default">&nbsp;'.lang('default').'&nbsp;</span>&nbsp;';
        $styler= "function(value, row, index) { if (row.default=='1') { return { class:'row-default' }; } }";
        $data  = array_replace_recursive(parent::gridBase($security, $defs), [
            'attr'   => ['idField'=>'id'],
            'events' => [
                'onDblClickRow'=>"function(rowIndex, rowData){ accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', '$this->moduleID/$this->pageID/edit', rowData.id); }"],
            'footnotes' => ['codes'=>$ftNote],
            'source' => [
                'search' => ['account', 'title', 'cur'],
                'actions'=>[
                    'mergeGL'=>['order'=>30,'icon'=>'merge', 'hidden'=>$security>3?false:true,'events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/merge', 0);"]],
                    'export' =>['order'=>80,'icon'=>'export','events'=>['onClick'=>"hrefClick('$this->moduleID/$this->pageID/export');"]]],
                'filters'=> [
                    'inactive' => ['order'=>10,'label'=>lang('status'),'values'=>$yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'columns'  => [
                'action' => [
                    'actions'=> [
                        'rename'=> ['order'=>40,'icon'=>'rename', 'events'=>['onClick'=>"var title=prompt('".$this->lang['chart_rename']."'); if (title!=null) jsonAction('$this->moduleID/$this->pageID/rename', 'idTBD', title);"]]]],
                'inactive'=> ['order'=> 0,                                 'attr'=>['hidden'=>true]],
                'default' => ['order'=> 0,                                 'attr'=>['hidden'=>true]],
                'id'      => ['order'=>20,'label'=>lang('gl_account'),     'attr'=>['hidden'=>false,'width'=> 80,'sortable'=>true,'resizable'=>true],
                    'events'=>['styler'=>"function(value, row) { if (row.inactive==1) return {class:'row-inactive'}; }",'sorter'=>"function(a,b){return parseInt(a) > parseInt(b) ? 1 : -1;}"]],
                'title'   => ['order'=>30,'label'=>lang('title'),          'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true],'events'=>['styler'=>$styler]],
                'type'    => ['order'=>40,'label'=>lang('type'),           'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true],'format'=>'glTypeLbl'],
                'cur'     => ['order'=>50,'label'=>lang('currency'),       'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true, 'align' =>'center']],
                'parent'  => ['order'=>70,'label'=>lang('primary_gl_acct'),'attr'=>['width'=> 80,'sortable'=>true,'align'=>'center'],
                    'events'=>['formatter'=>"function(value,row){ return value ? value : ''; }"]]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'cmd', 'default'=>'id'], 'post');
        $this->defaults['order'] = clean('order', ['format'=>'cmd', 'default'=>'ASC'],'post');
        $this->defaults['status']= clean('status',['format'=>'char','default'=>'a'],  'post');
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div']);
        $coa_blocked = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id') ? true : false;
        $charts = [];
        $fields = [
            'sel_coa'     => ['order'=>10,'values'=>$charts,'break'=>false,'attr'=>['type'=>'select','size'=>10]],
            'btn_coa_imp' => ['order'=>20,'icon'=>'import', 'size'=>'large','events'=>['onClick'=>"if (confirm('".$this->lang['msg_gl_replace_confirm']."')) jsonAction('phreebooks/chart/import', 0, bizSelGet('sel_coa'));"]],
            'btn_coa_pre' => ['order'=>25,'icon'=>'preview','size'=>'large','events'=>['onClick'=>"winOpen('popupGL', 'phreebooks/chart/preview&chart='+bizSelGet('sel_coa'), 800, 600);"]],
            'upload_txt'  => ['order'=>30,'type'=>'html','html'=>$this->lang['coa_upload_file'],'attr'=>['type'=>'raw']],
            'file_coa'    => ['order'=>35,'label'=>'', 'attr'=>['type'=>'file']],
            'btn_coa_upl' => ['order'=>40,'attr'=>['type'=>'button', 'value'=>$this->lang['btn_coa_upload']], 'events'=>['onClick'=>"if (confirm('".$this->lang['msg_gl_replace_confirm']."')) jqBiz('#frmGlUpload').submit();"]]];
        if (!$coa_blocked) { $fields['sel_coa']['values'] = localeLoadCharts(); }
    }

    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        // regular parent::mgrRowsMeta won't work as these are combined in single meta record.
        $grid = $this->managerGrid($security);
        msgDebug("\nread grid = ".print_r($grid, true));
        if ($this->defaults['status']=='a') { unset($grid['source']['filters']['inactive']); } // all statuses
        $row = dbGetRow(BIZUNO_DB_PREFIX.'common_meta', "meta_key='$this->metaPrefix'");
        $meta = json_decode($row['meta_value'], true);
        msgDebug("\nRead chart before processing = ".print_r($meta, true));
        $this->mgrRowsDBFltr($layout, $grid, $meta);
        msgDebug("\nlayout before render = ".print_r($layout, true));
    }

    /**
     * structure to review a sample chart of accounts, only visible until first GL entry
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function preview(&$layout=[])
    {
        if (!$security = validateAccess('admin', 4)) { return; }
        $chart   = clean('chart', 'path', 'get');
        if (!file_exists(BIZBOOKS_ROOT.$chart)) { return msgAdd('Bad path to chart!'); }
        $accounts= $this->prepData(BIZBOOKS_ROOT.$chart);
        $output  = [];
        foreach ($accounts as $row) {
            $output[] = ['id'=>$row['id'], 'type'=>lang('gl_acct_type_'.trim($row['code'])), 'title'=>$row['description'],
                'heading'=> !empty($row['parent']) ? $row['parent'] : ''];
        }
        $jsReady = "var winChart = ".json_encode($output).";
jqBiz('#dgPopupGL').datagrid({ pagination:false,data:winChart,columns:[[{field:'id',title:'".jsLang('gl_account')."',width: 50},{field:'type',title:'" .jsLang('type')."',width:100},{field:'title',title:'".jsLang('title')."',width:200} ]] });";
        $layout = array_replace_recursive($layout, ['type'=>'page', 'title'=>$this->lang['btn_coa_preview'],
            'divs'=>['divLabel'=>['order'=>60,'type'=>'html','html'=>"<table id=\"dgPopupGL\"></table>"]],
            'jsReady' => ['init'=>$jsReady]]);
    }

    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return; }
        $srcID = clean('rID','integer','get');
        $destID= clean('data','text','get');
        // validate input
        if (!isset($this->chartMeta[$srcID])) { return msgAdd(lang('illegal_access')); }
        if (!empty($this->chartMeta[$destID])){ return msgAdd(lang('title_already_exists')); }
        $this->chartMeta[$destID] = $this->chartMeta[$srcID];
        $this->chartMeta[$destID]['id']   = $destID;
        $this->chartMeta[$destID]['title']= lang('untitled');
        ksort($this->chartMeta);
        msgDebug("\nReady to write updated full chart = ".print_r($this->chartMeta, true));
        dbTransactionStart();
        dbMetaSet($this->chartMetaID, $this->metaPrefix, $this->chartMeta);
        insertChartOfAccountsHistory($this->chartMeta[$destID]['id'], $this->chartMeta[$destID]['type']); // build the journal_history entries
        dbTransactionCommit();
        msgLog("$this->mgrTitle - ".lang('copy').": {$this->chartMeta[$destID]['title']}");
        $data  = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dg{$this->domSuffix}'); accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit', $destID);"]];
        $layout= array_replace_recursive($layout, $data);
    }

    public function rename(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $oldID = clean('rID', 'filename', 'get');
        $newID = clean('data','filename', 'get');
        // Error check
        if ( empty($this->chartMeta[$oldID])) { return msgAdd(lang('invalid_id')); }
        if (!empty($this->chartMeta[$newID])) { return msgAdd(lang('title_already_exists')); }
        $this->chartMeta[$newID] = $this->chartMeta[$oldID];
        $this->chartMeta[$newID]['id'] = $newID;
        unset($this->chartMeta[$oldID]);
        ksort($this->chartMeta);
        msgDebug("\nReady to write updated full chart = ".print_r($this->chartMeta, true));
        dbTransactionStart();
        dbMetaSet($this->chartMetaID, $this->metaPrefix, $this->chartMeta);
        // @TODO - BOF - DEPRECATED
        dbWrite(BIZUNO_DB_PREFIX.'contacts',       ['gl_account'=>$newID], 'update', "gl_account='$oldID'");
        // EOF - DEPRECATED
        dbWrite(BIZUNO_DB_PREFIX.'contacts',       ['gl_acct_v' =>$newID], 'update', "gl_acct_v='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'contacts',       ['gl_acct_c' =>$newID], 'update', "gl_acct_c='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'inventory',      ['gl_sales'  =>$newID], 'update', "gl_sales='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'inventory',      ['gl_inv'    =>$newID], 'update', "gl_inv='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'inventory',      ['gl_cogs'   =>$newID], 'update', "gl_cogs='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'journal_history',['gl_account'=>$newID], 'update', "gl_account='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'journal_item',   ['gl_account'=>$newID], 'update', "gl_account='$oldID'");
        dbWrite(BIZUNO_DB_PREFIX.'journal_main',   ['gl_acct_id'=>$newID], 'update', "gl_acct_id='$oldID'");
        dbTransactionCommit();
        msgLog("$this->mgrTitle - ".lang('rename').": {$this->chartMeta[$newID]['title']}");
        $data  = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dg{$this->domSuffix}'); reloadSessionStorage();"]];
        $layout= array_replace_recursive($layout, $data);
    }
    public function edit(&$layout=[])
    {
        $account = clean('rID', 'filename', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        if (empty($this->chartMeta[$account])) { unset($this->struc['id']['attr']['readonly']); } // allow GL account entry if new
        metaPopulate($this->struc, $this->chartMeta[$account]);
        msgDebug("\nUpdated meta for account $account to get: ".print_r($this->struc, true));
        $title = !empty($account) ? lang('edit').': '.$this->chartMeta[$account]['title'] : lang('new');
        $args  = ['_rID'=>$account, 'title'=>$title]; // '_refID'=>0, '_table'=>'common', 
        $data  = $this->setEdit($security, $args);
        $layout= array_replace_recursive($layout, $data);
    }
    public function save(&$layout=[])
    {
        $rID = clean('id', 'filename', 'post');
        if (!validateAccess($this->secID, empty($rID)?2:3)) { return; }
        $metaVal= !empty($rID) ? $this->chartMeta[$rID] : [];
        $output = metaUpdate($metaVal, $this->struc);
        // error check
        // @TODO - Need to verify the commented out tests
//      if (!empty($rID) && empty($output['title'])){ return msgAdd($this->lang['chart_save_01']); }
        if ( empty($output['title']))                { return msgAdd($this->lang['chart_save_02']); }
        if ( empty($rID) && isset($this->chartMeta[$output['id']])) { return msgAdd(lang('chart_save_03')); }
        if ( empty($rID) && $output['type']==44)     { foreach ($this->chartMeta as $row) { if ($row['type']==44) { return msgAdd(lang('chart_save_04')); } } }
//      if ($used && $heading)      { return msgAdd($this->lang['chart_save_05']); }
//      if (!empty($output['parent']) && empty($glAccounts[$parent]['heading'])) { return msgAdd(sprintf($this->lang['chart_save_06'], $parent)); }
        msgDebug("\nReady to write updated chart record = ".print_r($output, true));
        if (empty($rID)) { $this->chartMeta[$output['id']]= $output; }
        else             { $this->chartMeta[$rID]         = $output; }
        $this->chartMeta = sortOrder($this->chartMeta, 'id');
// On 7.0 releases, this reorderd the indexes if they are numeric so re-index them by chart of accounts ID
        $temp = [];
        foreach ($this->chartMeta as $acct) { $temp[$acct['id']] = $acct; }
        $this->chartMeta = $temp;
// End re-index
        msgDebug("\nReady to write updated full chart = ".print_r($this->chartMeta, true));
        dbTransactionStart();
        dbMetaSet($this->chartMetaID, $this->metaPrefix, $this->chartMeta);
        insertChartOfAccountsHistory($output['id'], $output['type']); // build the journal_history entries, if not existing
        if (!empty($metaVal['type']) && $metaVal['type']<>$output['type']) { // check for type change
            dbWrite(BIZUNO_DB_PREFIX.'journal_history',['gl_type'=>$output['type']], 'update', "gl_account='{$output['id']}'");
        }
        dbTransactionCommit();
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("$this->mgrTitle - ".lang('save').": {$output['title']}");
        $data  = ['content'=>['action'=>'eval','actionData'=>"jqBiz('#acc{$this->domSuffix}').accordion('select',0); bizGridReload('dg{$this->domSuffix}'); reloadSessionStorage();"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * form builder to merge 2 gl accounts into a single record
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function merge(&$layout=[])
    {
        $icnSave= ['icon'=>'save','label'=>lang('merge'),
            'events'=>['onClick'=>"jsonAction('$this->moduleID/chart/mergeSave', jqBiz('#mergeSrc').val(), jqBiz('#mergeDest').val());"]];
        $props  = ['defaults'=>['callback'=>''],'attr'=>['type'=>'ledger']];
        $html   = "<p>".$this->lang['msg_chart_merge_src'] ."</p><p>".html5('mergeSrc', $props)."</p>".
                  "<p>".$this->lang['msg_chart_merge_dest']."</p><p>".html5('mergeDest',$props)."</p>".html5('icnMergeSave', $icnSave).
                  "<p>".$this->lang['msg_chart_merge_note']."</p>";
        $data   = ['type'=>'popup','title'=>$this->lang['chart_merge'],'attr'=>['id'=>'winMerge'],
            'divs'   => ['body'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>"bizFocus('mergeSrc');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Performs the merge of 2 gl accounts
     * @param array $layout - current working structure
     * @return modifed $layout
     */
    public function mergeSave(&$layout=[])
    {
        if (!$security = validateAccess('admin', 5)) { return; }
        $srcGL  = clean('rID', 'filename', 'get'); // GL Acct to merge
        $destGL = clean('data','filename', 'get'); // GL Acct to keep
        if (empty($srcGL) || empty($destGL)) { return msgAdd("Bad GL Accounts, Source GL = $srcGL and Destination GL = $destGL"); }
        if ($srcGL == $destGL)               { return msgAdd("Error: Source and destination GL Accounts cannot be the same! Nothing was done."); }
        // Check to make sure the types are not the same
        $srcType = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'gl_type', "gl_account='$srcGL'");
        $destType= dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'gl_type', "gl_account='$destGL'");
        if ($srcType <> $destType)           { return msgAdd("Error: Source and destination GL Accounts must be of the same type! Source: $srcType and destination: $destType Nothing was done."); }
        // Let's go
        dbTransactionStart();
        msgAdd(lang('GL Account merge stats').':', 'info');
        msgDebug("\nmergeSave with src GL = $srcGL and dest GL = $destGL");
        // Database changes
        msgDebug("\nReady to write table contacts to merge from GL Account: $srcGL => $destGL");
        $contCnt= dbWrite(BIZUNO_DB_PREFIX.'contacts',     ['gl_account'=>$destGL], 'update', "gl_account='".addslashes($srcGL)."'");
        msgAdd("contacts table SKU changes: $contCnt;", 'info');
        $invSCnt= dbWrite(BIZUNO_DB_PREFIX.'inventory',    ['gl_sales'  =>$destGL], 'update', "gl_sales='"  .addslashes($srcGL)."'");
        msgAdd("inventory.gl_sales table GL Account changes: $invSCnt;",'info');
        $invICnt= dbWrite(BIZUNO_DB_PREFIX.'inventory',    ['gl_inv'    =>$destGL], 'update', "gl_inv='"    .addslashes($srcGL)."'");
        msgAdd("inventory.gl_inv table GL Account changes: $invICnt;",'info');
        $invCCnt= dbWrite(BIZUNO_DB_PREFIX.'inventory',    ['gl_cogs'   =>$destGL], 'update', "gl_cogs='"   .addslashes($srcGL)."'");
        msgAdd("inventory.gl_cogs table GL Account changes: $invCCnt;",'info');
        $itemCnt= dbWrite(BIZUNO_DB_PREFIX.'journal_item', ['gl_account'=>$destGL], 'update', "gl_account='".addslashes($srcGL)."'");
        msgAdd("journal_item table GL Account changes: $itemCnt;",  'info');
        $mainCnt= dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['gl_acct_id'=>$destGL], 'update', "gl_acct_id='".addslashes($srcGL)."'");
        msgAdd("journal_main table GL Account changes: $mainCnt;",  'info');
        // Fix the journal_history table
        $cnt    = 0;
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "gl_account='$srcGL'");
        foreach ($rows as $row) {
            $dest  = dbGetRow(BIZUNO_DB_PREFIX.'journal_history', "period='{$row['period']}' AND gl_account='$destGL'");
            $values= [
                'beginning_balance'=> $row['beginning_balance']+$dest['beginning_balance'],
                'debit_amount'     => $row['debit_amount']     +$dest['debit_amount'],
                'credit_amount'    => $row['credit_amount']    +$dest['credit_amount'],
                'budget'           => $row['budget']           +$dest['budget'],
                'stmt_balance'     => $row['stmt_balance']     +$dest['stmt_balance']];
            dbWrite(BIZUNO_DB_PREFIX.'journal_history', $values, 'update', "id={$dest['id']}");
            $cnt++;
        }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_history WHERE gl_account='$srcGL'");
        msgAdd("journal_history table rows modified: $cnt;", 'info');
        // Fix the meta, cache
        unset($this->chartMeta[$srcGL]);
        dbMetaSet($this->chartMetaID, $this->metaPrefix, $this->chartMeta);
        $glAccounts= getModuleCache('phreebooks', 'chart');
        unset($glAccounts[$srcGL]);
        setModuleCache('phreebooks', 'chart', '', $glAccounts);
        dbTransactionCommit();
        // Wrap it up
        msgAdd("Finished Merging GL Acct $srcGL -> $destGL", 'info');
        msgLog(lang('gl_account').'-'.lang('merge').": $srcGL => $destGL");
        $data    = ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winMerge'); bizGridReload('dg{$this->domSuffix}'); reloadSessionStorage();"]];
        $layout  = array_replace_recursive($layout, $data);
    }

    /**
     * Structure for deleting a chart of accounts record.
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        $rID = clean('rID', 'filename', 'get');
        if (!$security = validateAccess($this->secID, 4)) { return; }
        if (empty($rID) || !isset($this->chartMeta[$rID])) { return msgAdd(lang('illegal_access')); }
        // Can't delete gl account if it was used in a journal entry
        if (dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "gl_acct_id='$rID'")) { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'journal_main')); }
        if (dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "gl_account='$rID'")) { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'journal_item')); }
        if (dbGetValue(BIZUNO_DB_PREFIX.'contacts',     'id', "gl_acct_v='$rID'"))  { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'contacts')); }
        if (dbGetValue(BIZUNO_DB_PREFIX.'contacts',     'id', "gl_acct_c='$rID'"))  { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'contacts')); }
        // @TODO - BOF - DEPRECATED
        if (dbGetValue(BIZUNO_DB_PREFIX.'contacts',     'id', "gl_account='$rID'")) { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'contacts')); }
        // EOF - DEPRECATED
        if (dbGetValue(BIZUNO_DB_PREFIX.'inventory',    'id', "gl_sales='$rID' OR gl_inv='$rID' OR gl_cogs='$rID'")) { return msgAdd(sprintf($this->lang['err_gl_chart_delete'], 'inventory')); }
        if ($this->chartMeta[$rID]['type']==44)                                   { return msgAdd('Sorry, you cannot delete your retained earnings account.'); }
        $maxPeriod = dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'MAX(period) as period', "", false);
        if (dbGetValue(BIZUNO_DB_PREFIX.'journal_history', 'beginning_balance', "gl_account='$rID' AND period=$maxPeriod")) { return msgAdd("The GL account cannot be deleted if the last fiscal year ending balance is not zero!"); }
        $title = $this->chartMeta[$rID]['title'];
        unset($this->chartMeta[$rID]);
        msgDebug("\nReady to write updated full chart = ".print_r($this->chartMeta, true));
        dbTransactionStart();
        dbMetaSet($this->chartMetaID, $this->metaPrefix, $this->chartMeta);
        dbGetResult('DELETE FROM '.BIZUNO_DB_PREFIX."journal_history WHERE gl_account='$rID'");
        dbTransactionCommit();
        msgLog("$this->mgrTitle - ".lang('delete').": $title");
        $layout= array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dg{$this->domSuffix}'); reloadSessionStorage();"]]);
    }

    /**
     * Imports the user selected GL chart of accounts
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function import(&$layout=[])
    {
        if (!$security = validateAccess('admin', 4)){ return; }
        $chart = clean('data', 'path', 'get');
        if (dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id'))  { return msgAdd($this->lang['coa_import_blocked']); }
        if (!$this->chartInstall($chart))            { return; }
        dbGetResult('TRUNCATE '.BIZUNO_DB_PREFIX.'journal_history');
        buildChartOfAccountsHistory();
        msgAdd($this->lang['msg_gl_replace_success'], 'caution');
        msgLog($this->lang['msg_gl_replace_success']);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"reloadSessionStorage(chartRefresh);"]]);
        return true;
    }

    /**
     * Uploads a chart of accounts xml file to import
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function upload(&$layout)
    {
        global $io;
        msgDebug("\nupload file array = ".print_r($_FILES, true));
        if (!$security = validateAccess('admin', 4)){ return; }
        if (!$io->validateUpload('file_coa', '', 'xml', true))  { return; }
        $filename = $filename = clean($_FILES['file_coa']['name'], 'filename');
        $io->uploadSave('file_coa', 'temp/', '', 'xml');
        if (dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id'))  { return msgAdd($this->lang['coa_import_blocked']); }
        if (!$this->chartInstall("temp/$filename"))             { return; }
        dbGetResult("TRUNCATE ".BIZUNO_DB_PREFIX.'journal_history');
        buildChartOfAccountsHistory();
        msgAdd($this->lang['msg_gl_replace_success'], 'success');
        msgLog($this->lang['msg_gl_replace_success']);
        $io->fileDelete("temp/$filename");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"reloadSessionStorage(chartRefresh);"]]);
    }

    public function export()
    {
        global $io;
        $header= $output = [];
        foreach (array_values($this->chartMeta) as $idx =>$row) {
            $line = [];
            foreach ($this->struc as $key => $value) {
                if (empty($idx)) { $header[] = csvEncapsulate($value['label']); } // create the heading row
                $line[]= csvEncapsulate(trim($row[$key]));
            }
            $output[] = implode(',', $line);            
        }
        array_unshift($output, implode(',', $header)); // prepend the heading row
        msgLog($this->domSuffix.' - '.lang('download'));
        $io->download('data', implode("\n", $output), $this->domSuffix.'-'.biz_date('Y-m-d').'.csv');
    }

    /**
     * Installs a chart of accounts, only valid during Bizuno installation and changing chart of accounts
     * @param string $chart - relative path to chart to install
     * @return user message with status
     */
    public function chartInstall($chart)
    {
        msgDebug("\nTrying to load chart: $chart");
        if     (file_exists(BIZBOOKS_ROOT."locale/en_US/modules/phreebooks/charts/$chart")) { $path=BIZBOOKS_ROOT."locale/en_US/modules/phreebooks/charts/$chart"; }
        else { return msgAdd('Bad path to chart!', 'trap'); }
        if (!dbTableExists(BIZUNO_DB_PREFIX.'journal_main') || dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id')) { return msgAdd(lang('coa_import_blocked')); }
        $accounts= $this->prepData($path);
        if (empty($accounts)) { return msgAdd('Invalid chart of accounts. Is the CSV properly formed?'); }
        $output = [];
        $reCnt  = 0;
        $curISO = getDefaultCurrency();
        foreach ($accounts as $row) {
            if (44==$row['code']) { $reCnt++; } // count the retained earnings accounts to make sure there is only one.
            $output[$row['id']] = ['id'=>trim($row['id']),
                'default' => !empty($row['default']) ? 1 : 0,
                'inactive'=> !empty($row['inactive'])? 1 : 0,
                'type'    => trim($row['code']),
                'cur'     => $curISO,
                'title'   => trim($row['description']),
                'parent'  => !empty($row['parent'])  ? $row['parent'] : ''];
        }
        ksort($output, SORT_STRING);
        $accts = dbMetaGet(0, $this->metaPrefix);
        $coaIdx= metaIdxClean($accts);
        dbMetaSet($coaIdx, $this->metaPrefix, $output);
        // error check
        if (empty($reCnt)) { return msgAdd('No retained earnings accounts were found! There can to be ONLY 1 retained earnings account in your chart!'); }
        if ($reCnt>1)      { return msgAdd('More than one retained earnings accounts were found. There can to be ONLY 1 retained earnings account in your chart!'); }
        setModuleCache('phreebooks', 'chart', '', $output);
        return true;
    }

    /**
     * reads the file from the path and converts it into a keyed array
     * @param array $path - table field structure
     * @return array - keyed data array of file contents
     */
    public function prepData($path)
    {
        $output= [];
        $skip  = 2;
        $rows  = array_map('str_getcsv', file($path));
        for ($i=0; $i<$skip; $i++) { array_shift($rows); }
        $head  = array_shift($rows); // pull the header row
        if ($head[0]<>'id') { return msgAdd('This doesn\'t look like the correct file. Please check your csv file and try again!'); }
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        msgDebug("\nReturning from chart:prepData with number of accounts = ".sizeof((array)$output));
        return $output;
    }
}
