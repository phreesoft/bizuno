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
 * @version    7.x Last Update: 2025-06-17
 * @filesource /controllers/inventory/build.php
 */

namespace bizuno;

class inventoryBuild extends mgrJournal
{
    public    $moduleID  = 'inventory';
    public    $pageID    = 'build';
    protected $secID     = 'woProd';
    protected $domSuffix = 'Build';
    protected $metaPrefix= 'bill_of_materials';
    protected $nextRefIdx= 'next_wo_num';
    protected $journalID = 32;
    public    $attachPath;
    public    $struc;

    function __construct()
    {
        parent::__construct();
        $this->attachPath = getModuleCache($this->moduleID, 'properties', 'attachPath', 'production');
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $stores  = getModuleCache('bizuno', 'stores');
        $this->struc = [
            'id'         => ['panel'=>'details','order'=> 1,                                                            'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'xChild'     => ['panel'=>'details','order'=> 1,                                                            'clean'=>'db_field','attr'=>['type'=>'hidden', 'value'=>'']],
            'sb_ref'     => ['panel'=>'details','order'=> 1,'dbField'=>'invoice_num',                                   'clean'=>'filename','attr'=>['type'=>'hidden', 'value'=>'']],
            'sku'        => ['panel'=>'details','order'=>10,'dbField'=>'sku',            'label'=>lang('sku'),          'clean'=>'text',    'attr'=>['type'=>'text',   'value'=>'', 'readonly'=>true]],
            'qty'        => ['panel'=>'details','order'=>20,'dbField'=>'qty',            'label'=>lang('qty'),          'clean'=>'float',   'attr'=>['type'=>'integer','value'=>'']],
            'title'      => ['panel'=>'details','order'=>30,'dbField'=>'description',    'label'=>lang('title'),        'clean'=>'text',    'attr'=>['type'=>'text',   'value'=>'']],
            'store_id'   => ['panel'=>'details','order'=>40,'dbField'=>'store_id',       'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>sizeof($stores)>1?'select':'hidden', 'value'=>0],'values'=>viewStores()],
            'closed'     => ['panel'=>'details','order'=>50,'dbField'=>'closed',         'label'=>lang('closed'),       'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0, 'readonly'=>true]],
            'create_date'=> ['panel'=>'details','order'=>60,'dbField'=>'post_date',      'label'=>lang('date_created'), 'clean'=>'date',    'attr'=>['type'=>'date',   'value'=>biz_date(), 'readonly'=>true]],
            'due_date'   => ['panel'=>'details','order'=>70,'dbField'=>'terminal_date',  'label'=>lang('date_due'),     'clean'=>'date',    'attr'=>['type'=>'date',   'value'=>biz_date()]],
            'close_date' => ['panel'=>'details','order'=>80,'dbField'=>'closed_date',    'label'=>lang('date_closed'),  'clean'=>'date',    'attr'=>['type'=>'date',   'value'=>'', 'readonly'=>true]],
            'imgSKU'     => ['panel'=>'image',  'order'=>10,'dbField'=>'image_with_path','label'=>lang('product_image'),'clean'=>'url',     'attr'=>['type'=>'img',    'width'=>200]],
            'notes'      => ['panel'=>'notes',  'order'=>10,'dbField'=>'notes',                                         'clean'=>'text',    'attr'=>['type'=>'editor', 'value'=>'']]];
    }
    protected function managerGrid($security, $args=[])
    {
        msgDebug("\nEntering managerGrid with args = ".print_r($args, true));
        $statuses= [['id'=>'a','text'=>lang('all')], ['id'=>'o','text'=>lang('open')], ['id'=>'c','text'=>lang('closed')]];
        switch ($this->defaults['status']) {
            default:
            case 'a': $sqlStatus = "";           break;
            case 'o': $sqlStatus = "closed='0'"; break;
            case 'c': $sqlStatus = "closed='1'"; break;
        }
        $data    = array_replace_recursive(parent::gridBase($security, $args), [
            'source' => [
                'tables' => ['journal_item'=>['table'=>BIZUNO_DB_PREFIX.'journal_item', 'join'=>'JOIN', 'links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_item.ref_id"]],
                'search' => ['sb_ref', 'sku', 'notes', BIZUNO_DB_PREFIX.'journal_main.description'],
                'filters'=> [
                    'store_id'  => ['order'=>20,'break'=>true,'label'=>lang('ctype_b'),'sql'=>($this->defaults['store_id']<>-1 ? BIZUNO_DB_PREFIX."journal_main.store_id={$this->defaults['store_id']}" : ''),
                        'attr' =>['type'=>sizeof(getModuleCache('bizuno', 'stores'))>1?'select':'hidden','value'=>$this->defaults['store_id']], 'values'=>viewStores()],
                    'status'    => ['order'=>40,'break'=>true,'label'=>lang('status'),'sql'=>$sqlStatus,'attr'=>['type'=>'select','value'=>$this->defaults['status']],'values'=>$statuses]],
                'sort'   => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'=> [
                'id'         => ['order'=>0, 'field'=>'DISTINCT '.BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]],
                'action'  => [
                    'actions'=> [
                        'print'=> ['order'=>40,'icon'=>'print','events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=mfg:wo&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]]]],
                'sb_ref'     => ['order'=>10, 'field'=>'invoice_num',  'label'=>lang('reference'), 'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]],
                'store_id'   => ['order'=>20, 'field'=>'store_id',     'label'=>lang('store_id'),  'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true], 'format'=>'storeID'],
                'sku'        => ['order'=>30, 'field'=>'sku',          'label'=>lang('sku'),       'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]],
                'qty'        => ['order'=>40, 'field'=>'qty',          'label'=>lang('quantity'),  'attr'=>['width'=> 60,'sortable'=>true,'resizable'=>true]],
                'title'      => ['order'=>50, 'field'=>BIZUNO_DB_PREFIX.'journal_main.description','label'=>lang('title'),      'attr'=>['width'=>240,'sortable'=>true,'resizable'=>true]],
                'create_date'=> ['order'=>60, 'field'=>BIZUNO_DB_PREFIX.'journal_main.post_date',  'label'=>lang('create_date'),'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true],'format'=>'date'],
                'due_date'   => ['order'=>70, 'field'=>'terminal_date','label'=>lang('due_date'),  'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true],'format'=>'date'],
                'close_date' => ['order'=>80, 'field'=>'closed_date',  'label'=>lang('close_date'),'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true],'format'=>'date']]]);
        return $data;
    }
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']    = clean('sort',    ['format'=>'cmd',     'default'=>'invoice_num'],'post');
        $this->defaults['period']  = clean('period',  ['format'=>'cmd',     'default'=>'y'],          'post');
        $this->defaults['store_id']= clean('store_id',['format'=>'integer', 'default'=>-1],           'post');
        $this->defaults['status']  = clean('status',  ['format'=>'db_field','default'=>'a'],          'post');
   }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $refID= clean('refID', 'integer', 'get');
        $dom  = clean('dom', ['format'=>'db_field', 'default'=>'page'], 'get');
        $args = ['dom'=>$dom, 'type'=>'journal', 'work'=>true, 'title'=>sprintf(lang('tbd_manager'), lang('production'))];
        parent::managerMain($layout, $security, $args);
        if ('div'==$dom && !empty($refID)) { // if inside the inventory edit screen
            $layout['datagrid']["dg{$this->domSuffix}"]['attr']['url'] = BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&refID=$refID";
        }
        unset($layout['datagrid']["dg{$this->domSuffix}"]['source']['actions']['new']); // remove the work icon since this is meta only
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', 'integer', 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $layout['datagrid']['dgSrvJrnl']['columns']['store_id'] = ['order'=>50,'field'=>'store_id','format'=>'storeID',
            'label' => pullTableLabel("contacts", 'store_id'),'attr'=>['sortable'=>true,'resizable'=>true]];
        $layout['datagrid']['dgSrvJrnl']['source']['filters']['store'] = ['order'=>15,'label'=>lang('ctype_b'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        switch ($storeID) {
            case -1: $layout['datagrid']['dgSrvJrnl']['source']['filters']['store']['sql'] = ''; break;
            default: $layout['datagrid']['dgSrvJrnl']['source']['filters']['store']['sql'] = "store_id=$storeID"; break;
        }
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $refID = clean('refID', 'integer', 'get');
        if (!empty($refID)) { $this->defaults['search'] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$refID"); }
        $grid  = $this->managerGrid($security, ['type'=>'journal']);
        mapMetaGridToDB($grid, $this->struc);
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID = clean('store', 'integer', 'post');
        if (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $layout['datagrid']['dgSrvJrnl']['columns']['store_id'] = ['order'=>50,'field'=>'store_id','format'=>'storeID',
            'label' => pullTableLabel("contacts", 'store_id'),'attr'=>['sortable'=>true,'resizable'=>true]];
        $layout['datagrid']['dgSrvJrnl']['source']['filters']['store'] = ['order'=>15,'label'=>lang('ctype_b'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        switch ($storeID) {
            case -1: $layout['datagrid']['dgSrvJrnl']['source']['filters']['store']['sql'] = ''; break;
            default: $layout['datagrid']['dgSrvJrnl']['source']['filters']['store']['sql'] = "store_id=$storeID"; break;
        }
    }
    public function add(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $fldSel= ['id'=>'newSKU', 'props'=>['attr'=>['type'=>'inventory'],
            'defaults'=>['url'=>"'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&filter=assy&clr=1'"]]];
        $args  = ['_table'=>'inventory', 'fldSel'=>$fldSel, 'desc'=>'Select a SKU to generate a new Work Order.'];
        parent::addDB($layout, $security, $args);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $rID   = clean('rID',   'integer','get');
        $newSKU= clean('newSKU','integer','get'); // newSKU coming in from add above is the skuID
        if (empty($rID) && !empty($newSKU)) { $rID = $this->newBuild($newSKU); }
        $args  = ['rID'=>$rID, '_table'=>'journal'];
        parent::editDB($layout, $security, $args);
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow copy here
        // add the attachment panels
        $layout['divs']['content']['divs']['atch'] = ['order'=>80,'type'=>'panel','key'=>"atch{$this->domSuffix}",'classes'=>['block50']];
        $layout['panels']["atch{$this->domSuffix}"]= ['type'=>'attach','attr'=>['id'=>"atch{$this->domSuffix}"],'defaults'=>['path'=>$this->attachPath, 'prefix'=>"rID_{$rID}_"]];
        $item  = !empty($rID) ? dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID") : ['sku'=>'', 'qty'=>1];
        $layout['fields']['sku']['attr']['value'] = $item['sku'];
        $layout['fields']['qty']['attr']['value'] = $item['qty'];
        $image = clean(dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'image_with_path', "sku='".addslashes($item['sku'])."'"), 'path_rel');
        $layout['fields']['imgSKU']['attr']['src']      = BIZBOOKS_URL_FS.getUserCache('business', 'bizID')."/images/$image";
        $layout['fields']['imgSKU']['events']['onClick']= "jsonAction('bizuno/image/view', '".getUserCache('business', 'bizID')."', '$image');";
        $jsBody= "function testStock() {\nvar sku = jqBiz('#sku').val();\nvar qty = jqBiz('#qty').val();
    jqBiz.ajax({url:bizunoAjax+'&bizRt=inventory/main/getStockAssy&sku='+sku+'&qty='+qty, success:function(data){processJson(data);}});}";
        $data  = [
            'divs'    => [
                'steps'  => ['order'=>60, 'type'=>'panel', 'key'=>'build']],
            'toolbars'=> ["tb{$this->domSuffix}"=>['icons'=>[
                'print'=> ['order'=>40,'hidden'=>$rID==0 && $security>1?false:true,'events'=>['onClick'=>"jqBiz('#xChild').val('print'); jqBiz('#frmJournal').submit();"]]]]],
            'panels'  => [
                'build'  => ['label'=>$this->lang['msg_build_num'],'id'=>'build','type'=>'html','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/build/details&woID=$rID'"],'html'=>'&nbsp;']],
            'jsBody'  => ['init'=>$jsBody]];
        $layout= array_replace_recursive($layout, $data);
        // Stores
        if (!empty($this->restrict)) {
            $layout['fields']['store_id']['attr']['value'] = $this->myStore;
            return;
        }
        $layout['fields']['store_id']['label'] = pullTableLabel("contacts", 'store_id');
        if (!$rID) {
            $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $layout['fields']['store_id']['attr']['type'] = 'select';
            $layout['fields']['store_id']['values'] = viewStores();
        }
    }
    /** 
     * Generates a new WO build as part of the Add method
     * @param type $skuID
     */
    private function newBuild($skuID)
    {
        msgDebug("\nEntering newBuild with skuID = $skuID");
        $sku = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id={$skuID}");
        if (empty($sku)) { return msgAdd("I cannot find the SKU, did you select it from the list?"); }
        $main= [
            'journal_id' => $this->journalID,
            'invoice_num'=> getNextReference($this->nextRefIdx),
            'post_date'  => biz_date(),
            'store_id'   => getUserCache('profile', 'store_id'),
            'description'=> $sku['description_short']];
        $mID = $_GET['rID'] = dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
        $item= ['ref_id'=>$mID, 'qty'=>0, 'sku'=>$sku['sku'], 'post_date'=>biz_date()];
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', $item);
        $job = getMetaInventory($skuID, 'production_job');
        msgDebug("\nRead job from sku = ".print_r($job, true));
        dbMetaSet(0, 'production_steps', $job['steps'], 'journal', $mID);
        return $mID;
    }

    /**
     * Lists the steps with the progress and step requirements
     * @param type $layout
     */
    public function details(&$layout=[])
    {
        $woID  = clean('woID', 'integer', 'get');
        // woID is the journla main id
        if (!$security = validateAccess($this->secID, 1)) { return; }
        
        $steps = getMetaJournal($woID, 'production_steps');
        msgDebug("\nEntering details with woID = $woID and steps = ".print_r($steps, true));
        $fields= [
            'step_id'   => ['attr'=>['type'=>'hidden','value'=>0]],
            'step_notes'=> ['attr'=>['type'=>'hidden']],
            'woID'      => ['attr'=>['type'=>'hidden','value'=>$woID]]];
        $onStep= null;
        foreach ($steps as $step => $value) {
            if (empty($value['task_id'])) { msgAdd('Task_id cannot is empty!'); continue; }
            $task = dbMetaGet($value['task_id'], 'production_task');
            metaIdxClean($task);
            msgDebug("\nTask {$value['task_id']} with data = ".print_r($task, true));
            $fields['steps'][$step] = array_replace($task, $value);
            msgDebug("\nAfter replace, task = {$value['task_id']} with data = ".print_r($fields['steps'][$step], true));
            if (empty($value['complete']) && is_null($onStep)) { $onStep = $fields['step_id']['attr']['value'] = $step; }
        }
        $data  = ['type'=>'divHTML',
            'divs'   => [
                'formBOF'=> ['order'=>20,'type'=>'form','key' =>'frmSteps'],
                'body'   => ['order'=>50,'type'=>'html','html'=>$this->getViewDetail($fields, $onStep)],
                'formEOF'=> ['order'=>90,'type'=>'html','html'=>"</form>"]],
            'forms'  => ['frmSteps' =>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/build/saveStep"]]],
            'fields' => $fields,
            'jsBody' => ['init'=>"function preSubmit() { jqBiz('#step_notes').val(jqBiz('#notes').val()); return true; }"],
            'jsReady'=> ['init'=>"ajaxForm('frmSteps');"]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Generates the HTML for the step table
     * @param type $fields
     * @return string
     */
    private function getViewDetail($fields, $onStep=null)
    {
        msgDebug("\nEntering getViewDetail with onStep = $onStep");
        $output = html5('step_id',$fields['step_id']).html5('step_notes',$fields['step_notes']).html5('id',$fields['woID']).'
<table id="tblJournal" style="border-collapse:collapse;width:100%;">
<thead class="panel-header">
    <tr><th>'.lang('step')."</th><th>".lang('title')."</th><th>".lang('description')."</th><th>".$this->lang['mfg_signoff']."</th><th>".$this->lang['qa_signoff']."</th><th>".$this->lang['erp_entry']."</th><th>".lang('action')."</th></tr>
</thead><tbody>\n";
        $cnt = false;
        if (empty($fields['steps']) || !is_array($fields['steps'])) { return $output .= "Error: No Steps found!</tbody>\n</table>"; }
        foreach ($fields['steps'] as $idx => $step) {
            $style = $cnt ? ' class="datagrid-row-selected"' : '';
            $output .= '<tr'.$style.'>'."\n";
            $output .= "<td>".($idx+1)."</td>\n";
            $output .= "<td>".$step['title']   ."</td>\n";
            if (!is_null($onStep) && $onStep==$idx) {
                if ($step['data_entry']) {
                    if (!empty($step['data_value'])) { $output .= "<td>".$step['description']."<br />".$this->lang['data_value'].": {$step['data_value']}</td>\n"; }
                    else                             { $output .= "<td>".$step['description']."<br />".html5('step_data', ['attr'=>  ['size'=>'60']])."</td>\n"; }
                } else { $output .= "<td>".$step['description']."</td>\n"; }
                if ($step['mfg']) {
                    if ($step['mfg_id']) { $output .= '<td style="text-align:center">'.getContactById($step['mfg_id'])."</td>\n"; }
                    else                 { $output .= "<td>".html5('bldr_pin',  ['label'=>lang('sign_off_pin'), 'attr'=>['type'=>'password']])."</td>\n"; }
                } else { $output .= "<td>&nbsp;</td>\n"; }
                if ($step['qa']) {
                    if ($step['qa_id'])  { $output .= '<td style="text-align:center">'.getContactById($step['qa_id'])."</td>\n"; }
                    else                 { $output .= "<td>".html5('qa_pin', ['label'=>lang('sign_off_pin'), 'attr'=>['type'=>'password']])."</td>\n"; }
                } else { $output .= "<td>&nbsp;</td>\n"; }
                $output .= "<td>&nbsp;</td>\n<td>".html5('', ['icon'=>'save','size'=>'large','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmSteps').submit();"]])."</td>\n";
            } else {
                $output .= "<td>".$step['description'];
                $output .= !empty($step['data_value']) ? ("<br />".$this->lang['data_value'].": {$step['data_value']}") : "";
                $output .= "</td>\n";
                $output .= '<td style="text-align:center">'.(!empty($step['mfg'])?($step['mfg_id']? getContactById($step['mfg_id']): lang('yes')): ' ')."</td>\n";
                $output .= '<td style="text-align:center">'.(!empty($step['qa']) ?($step['qa_id'] ? getContactById($step['qa_id']) : lang('yes')): ' ')."</td>\n";
                $output .= '<td style="text-align:center">'.($step['erp_entry']?lang('yes'): ' ')."</td>\n";
                $output .= "<td>&nbsp;</td>\n";
            }
            $output .= "</tr>\n";
            $cnt = !$cnt;
        }
        $output .= "</tbody>\n</table>";
        return $output;
    }

    /**
     * This function saves the build instructions/step build operation for a given step and builds the form to move to the next step
     * @param array $request
     * @return array $data - json response (divHTML)
     */
    public function save(&$layout=[])
    {
        $rID = clean('id', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        $item= dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        parent::saveDB($layout);
        $qty = clean('qty', 'float', 'post');
        if ($qty <> $item['qty']) { // check for qty change, if so then adjust allocation
            dbWrite(BIZUNO_DB_PREFIX.'journal_item', ['qty'=>$qty], 'update', "ref_id=$rID");
            $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($item['sku'])."'");
            $this->allocateAdj($skuID, $qty-$item['qty']);
        }
    }

    /**
     * Saves a work order step and closes out work order if applicable
     * @param array $layout
     * @return type
     */
    public function saveStep(&$layout=[])
    {
        $woID     = clean('id',       'integer','post');
        if (!validateAccess($this->secID, $woID?3:2)) { return; }
        $step_id  = clean('step_id',  'integer','post');
        $notes    = clean('notes',    'text',   'post');
        $step_data= clean('step_data','text',   'post');
        $bldr_pin = clean('bldr_pin','integer', 'post');
        $qa_pin   = clean('qa_pin',  'integer', 'post');
        if (empty($woID)) { return msgAdd(lang('bad_data')); }
        $main     = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$woID");
        msgDebug("\nRead main from db = ".print_r($main, true));
        $item     = dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$woID");
        msgDebug("\nRead item from db = ".print_r($item, true));
        $steps    = dbMetaGet(0, 'production_steps', 'journal', $woID);
        $metaID   = metaIdxClean($steps);
        msgDebug("\nRead steps from db = ".print_r($steps, true));
        $task = dbMetaGet($steps[$step_id]['task_id'], 'production_task');
        msgDebug("\nTask {$steps[$step_id]['task_id']} with data = ".print_r($task, true));
        $stepData = $steps[$step_id];
        if (!empty($task['mfg']) && empty($stepData['mfg_id'])) {
            msgDebug("\nmfg is required in this step, mfg_id = {$stepData['mfg_id']} and bldr_pin = $bldr_pin");
            if (!empty($bldr_pin)) {
                $bldr_user = validateSignoff($bldr_pin, 'mfg');
                if (!empty($bldr_user)) { // the mfg signoff is required and present
                    $stepData['mfg_id']   = $bldr_user;
                    $stepData['mfg_date'] = biz_date('Y-m-d H:i:s');
                    $mfg_val = true;
                } else { $mfg_val = false; }
            } else { $mfg_val = false; }
        } else { $mfg_val = true; } // mfg signoff has passed or not needed
        if (!empty($task['qa']) && empty($stepData['qa_id'])) {
            msgDebug("\nQA is required in this step, qa_id = {$stepData['qa_id']}");
            if (!empty($qa_pin)) {
                $qa_user = validateSignoff($qa_pin, 'qa');
                if (!empty($qa_user)) { // the qa signoff is required and present
                    $stepData['qa_id']   = $qa_user;
                    $stepData['qa_date'] = biz_date('Y-m-d H:i:s');
                    $qa_val = true;
                } else { $qa_val = false; }
            } else { $qa_val = false; }
        } else { $qa_val = true; } // qa signoff has passed or not needed
        if (!empty($task['data_entry'])) {
            $stepData['data_value'] = $step_data;
            msgDebug("\nData is required in this step, data = $step_data");
            if (strlen($step_data) == 0) { msgAdd("Data entry is required in this step!", 'caution'); }
            $data_val = strlen($step_data) > 0 ? true : false;
        } else { $data_val = true; }
        $stepData['complete'] = ($mfg_val && $qa_val && $data_val) ? 1 : 0;
        msgDebug("\nComplete has been calculated: {$stepData['complete']}, mfg_val = $mfg_val AND qa_val = $qa_val AND data_val = $data_val");
        $mainData = [];
        if (!empty($notes)) { $mainData['notes'] = $notes; } // check notes
        $steps[$step_id] = $stepData;
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        dbMetaSet($metaID, 'production_steps', $steps, 'journal', $woID); // write the steps meta
        // Assemble the part if applicable
        if ($stepData['complete'] && !empty($task['erp_entry'])) { // assemble if needed
            if (!$this->assemble($main, $item)) { return dbTransactionRollback(); }
        }
        if (sizeof($steps)==($step_id+1) && $stepData['complete']) { // the build is complete, close
            $mainData['closed']     = '1';
            $mainData['closed_date']= biz_date('Y-m-d h:i:s');
            // check for allocation, remove allocation if so
            $skuID  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($item['sku'])."'");
            $jobMeta= dbMetaGet(0, 'production_job', 'inventory', $skuID);
            $jobID  = metaIdxClean($jobMeta);
            if (!empty($jobMeta['allocate'])) { $this->allocateAdj($skuID, -$item['qty']); }
            dbMetaSet($jobID, 'production_job', $jobMeta, 'inventory', $skuID);
        }
        if (!empty($mainData)) { dbWrite(BIZUNO_DB_PREFIX.'journal_main', $mainData, 'update', "id=$woID"); }
        dbTransactionCommit();
        // *************** END TRANSACTION *************************
        if (!empty($mainData['closed'])) {
            msgLog(sprintf($this->lang['msg_build_complete'], $main['sb_ref'], $item['qty'], $item['sku']));
            msgAdd(sprintf($this->lang['msg_build_complete'], $main['sb_ref'], $item['qty'], $item['sku']),'success');
            $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#acc{$this->domSuffix}').accordion('select', 0); jqBiz('#dg{$this->domSuffix}').datagrid('reload'); jqBiz('#dtl{$this->domSuffix}').html('&nbsp;');"]]);
        } else {
            msgLog(sprintf($this->lang['msg_build_step'], $main['sb_ref'], $step_id+1));
            msgAdd(sprintf($this->lang['msg_build_step'], $main['sb_ref'], $step_id+1),'success');
            $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#build').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/build/details&woID={$main['id']}');"]]);
        }
    }

    private function assemble($main, $item)
    {
        $glInv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'gl_inv', "sku='".addslashes($item['sku'])."'");
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journal.php", 'journal');
        $glEntry = new journal(0, 14);
        $glEntry->main['description'] = "{$this->lang['msg_build_num']} ({$item['qty']}) {$main['description']}";
        $glEntry->main['invoice_num'] = $main['invoice_num'];
        $glEntry->main['store_id']    = $main['store_id'];
        $glEntry->main['gl_acct_id']  = $glInv;
        $glEntry->main['closed']      = 1;
        $glEntry->main['closed_date'] = biz_date('Y-m-d');
        $glEntry->items[]= [
            'gl_type'    => 'asy',
            'sku'        => $item['sku'],
            'qty'        => $item['qty'],
            'description'=> $main['description'],
            'gl_account' => $glInv,
//          'trans_code' => $serial,
            'post_date'  => biz_date()];
        $_POST['description'] = $glEntry->main['description'];
        if ($glEntry->Post()) { return true; }
    }

    /**
     *
     * @param type $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        $woMain= dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$rID");
        $woItem= dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "id=$rID");
        $args  = ['_table'=>'journal'];
        parent::deleteDB($layout, $security, $args);
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/main.php', 'phreebooksMain');
        $steps = getMetaJournal($rID, 'production_steps');
        msgDebug("\nRead steps to be analyzed = ".print_r($steps, true));
        $wasAssembled = false; // un-assemble the product if assembled
        foreach ($steps as $step) { if (!empty($step['erp_entry']) && !empty($step['complete'])) { $wasAssembled = true; } }
        if ($wasAssembled) {
            $mID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "invoice_num='{$woMain['invoice_num']}' AND journal_id=14");
            if ($mID) {
                $_GET['rID'] = $mID; // fake journal_main db record ID
                $jrnl = new phreebooksMain();
                $jrnl->delete();
                $_GET['rID'] = $rID; // restore srv_builder ID
            }
        }
        $skuID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($woItem['sku'])."'");
        $job   = getMetaInventory($skuID, 'production_job');
        if (!$woMain['closed'] && $job['allocate']) { $this->allocateAdj($skuID, -$woItem['qty']); }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_item WHERE ref_id=$rID");
        dbMetaDelete($rID, 'journal');
    }

    /**
     * This function adjusts the qty_on_allocation field for a given assembly in the table inventory.
     * @param integer $skuID - The database record ID from table inventory of the SKU being allocated
     * @param float $qty - The master assembly quantity to adjust allocation for (positive when creating builds and negative when completing builds)
    */
    private function allocateAdj($skuID, $qty=0)
    {
        $invMeta = getMetaInventory($skuID, 'bill_of_materials');
        foreach ($invMeta as $row) { // if not tracked in cogs then skip
            $type = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "sku='".addslashes($row['sku'])."'");
            $total= $qty * $row['qty'];
            if (!empty($total) && strpos(COG_ITEM_TYPES, $type)!==false) { dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_alloc=qty_alloc+$total WHERE sku='{$row['sku']}'"); }
        }
    }
}
