<?php
/*
 * Methods related to locale taxes, authorities and rates
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
 * @version    7.x Last Update: 2026-01-27
 * @filesource /controllers/phreebooks/tax.php
 */

namespace bizuno;

class phreebooksTax extends mgrJournal
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'tax';
    protected $secID     = 'admin';
    protected $domSuffix = 'TaxRate';
    protected $metaPrefix= 'tax_rate_';
    private   $change    = false;

    function __construct()
    {
        parent::__construct();
        $this->status     = [['id'=>'a','text'=>lang('all')],['id'=>'0','text'=>lang('active')], ['id'=>'1','text'=>lang('inactive')]];
        $this->type       = clean('type', ['format'=>'char', 'default'=>'c'], 'get');
        $this->domSuffix .= $this->type;
        $this->metaPrefix.= $this->type;
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure and sets the defaults
     * @return array - page structure
     */
    protected function fieldStructure()
    {
        $newAuth = ['rows'=>[], 'total'=>0, 'footer'=>[['glAcct'=>lang('total'),'rate'=>0]]];
        $this->struc = [ 
            '_rID'      => ['panel'=>'general','order'=> 1,                                'clean'=>'integer',  'attr'=>['type'=>'hidden',  'value'=>0]],
            'taxAuths'  => ['panel'=>'general','order'=> 1,                                'clean'=>'json',     'attr'=>['type'=>'hidden',  'value'=>$newAuth]],
            'tax_rate'  => ['panel'=>'general','order'=> 1,                                'clean'=>'float',    'attr'=>['type'=>'hidden',  'value'=>0]],
            'inactive'  => ['panel'=>'general','order'=>20,'label'=>lang('inactive'),      'clean'=>'char',     'attr'=>['type'=>'selNoYes','value'=>'0']],
            'title'     => ['panel'=>'general','order'=>10,'label'=>lang('title'),         'clean'=>'text',     'attr'=>['type'=>'text',    'value'=>'']],
            'start_date'=> ['panel'=>'general','order'=>70,'label'=>lang('date_effective'),'clean'=>'dateMeta', 'attr'=>['type'=>'date',    'value'=>biz_date()]],
            'end_date'  => ['panel'=>'general','order'=>70,'label'=>lang('date_expire'),   'clean'=>'dateMeta', 'attr'=>['type'=>'date',    'value'=>'']]];
    }

    /**
     * Grid for main tax listing
     * @param string $name - DOM field name
     * @param char $type - choice are c (customer) or v (vendor)
     * @param integer $security - users security level for visibility
     * @return array
     */
    protected function managerGrid($security=0, $args=[])
    {
        
        // add suffix to args
        
        $yes_no_choices = [['id'=>'a','text'=>lang('all')], ['id'=>'0','text'=>lang('active')], ['id'=>'1','text'=>lang('inactive')]];
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'attr'   => ['url'=>BIZUNO_URL_AJAX."&bizRt=phreebooks/tax/managerRows&type=$this->type"],
            'source' => [
                'search' => ['ref_num', 'title', 'description'],
                'filters'=> [
                    'status'=> ['order'=>10,'label'=>lang('status'),'values'=>$yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]]],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData) { accordionEdit('acc{$this->domSuffix}', 'dg{$this->domSuffix}', 'dtl{$this->domSuffix}', '".lang('details')."', 'phreebooks/tax/edit&type=$this->type', rowData._rID); }",
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; } else {
                    if (typeof row.start_date=='undefined' || typeof row.end_date=='undefined') { return; }
                    if (compareDate(dbDate(row.start_date)) == 1 || compareDate(dbDate(row.end_date)) == -1) { return {class:'journal-waiting'}; } } }"],
            'source' => [
                'actions'=> [
                    'bulk' => ['order'=>80,'icon'=>'merge','events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/bulkChange&type=$this->type', 0);"]]],
                'filters'=> [
                    'status'=> ['order'=>10, 'label'=>lang('status'),'break'=>true,'values'=>$this->status,'attr'=>['type'=>'select']]]],
                'footnotes'=> ['status'=>lang('status').':<span class="journal-waiting">'.$this->lang['not_current'].'</span><span class="row-inactive">'.lang('inactive').'</span>'],
                'columns'  => [
                    'inactive'  => ['order'=>0,                              'attr'=>['hidden'=>true]],
                    'title'     => ['order'=>10, 'label'=>lang('title'),     'attr'=>['type'=>'text', 'sortable'=>true, 'resizable'=>true]],
                    'start_date'=> ['order'=>20, 'label'=>lang('date_start'),'attr'=>['type'=>'date', 'sortable'=>true, 'resizable'=>true], 'format'=>'date'],
                    'end_date'  => ['order'=>30, 'label'=>lang('date_end'),  'attr'=>['type'=>'date', 'sortable'=>true, 'resizable'=>true], 'format'=>'date'],
                    'tax_rate'  => ['order'=>40, 'label'=>lang('tax_rate'),  'attr'=>['type'=>'float','sortable'=>true, 'resizable'=>true]]]]);
        return $data;
    }

    /**
     * Sets the session variables with the users current filter settings
     */
    protected function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort']  = clean('sort',  ['format'=>'cmd', 'default'=>'title'],'post');
        $this->defaults['status']= clean('status',['format'=>'char','default'=>'a'],    'post');
    }

    /******************************** Admin Manager ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $args = ['dom'=>'div', 'title'=>sprintf(lang('tbd_manager'), ($this->type=='v' ? lang('purchase_tax') : lang('sales_tax'))), 'xGet'=>"&type={$this->type}"];
        parent::managerMain($layout, $security, $args);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd',     'default'=>'ref_num'],'post');
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],    'post');
        $args = ['xGet'=>"&type={$this->type}"];
        parent::mgrRowsMeta($layout, $security, $args);
    }
    public function edit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $args = ['xGet'=>"&type={$this->type}"];
        parent::editMeta($layout, $security, $args);
        $auths  = [];
        $jsReady= '';
        if (!empty($rID)) { // existing record
            $meta = dbMetaGet($rID, "tax_rate_{$this->type}");
            metaIdxClean($meta);
            $auths = !empty($meta['taxAuths']) ? $meta['taxAuths'] : [];
        } else { // new record
            $jsReady.= " bizGridAddRow('dgTaxVendors{$this->type}');";
        }
        $data = [
            'divs'    => [
                'content'=>['divs'=>['datagrid'=>['order'=>70,'type'=>'datagrid','key' =>'dgTaxVendors']]]],
            'datagrid'=> ['dgTaxVendors'=>$this->dgTaxVendors("dgTaxVendors$this->type")],
            'jsHead'  => ['pbChart'=> "var pbChart=bizDefaults.glAccounts.rows;", 
                $this->pageID      => "var pbRates{$this->type}=".json_encode($layout['fields']['taxAuths']['attr']['value']).";"],
            'jsBody'  => [$this->pageID => $this->getJsBody($this->type)],
            'jsReady' => [$this->pageID => $jsReady]];
        $layout = array_replace_recursive($layout, $data);
        msgDebug("\nModified structure: ".print_r($layout, true));
    }
    private function getJsBody($type)
    {
        return "function taxTotal$type(newVal) {
    var total = 0;
    if (typeof curIndex == 'undefined') return;
    jqBiz('#dgTaxVendors$type').datagrid('getRows')[curIndex]['rate'] = newVal;
    var items = jqBiz('#dgTaxVendors$type').datagrid('getData');
    for (var i=0; i<items['rows'].length; i++) {
        var amount = parseFloat(items['rows'][i]['rate']);
        if (isNaN(amount)) amount = 0;
        total += amount;
    }
    var footer= jqBiz('#dgTaxVendors$type').datagrid('getFooterRows');
    footer[0]['rate'] = formatNumber(total);
    jqBiz('#tax_rate').val(formatNumber(total));
    jqBiz('#dgTaxVendors$type').datagrid('reloadFooter')
}
function preSubmit() {
    jqBiz('#dgTaxVendors$type').edatagrid('saveRow');
    var items = jqBiz('#dgTaxVendors$type').datagrid('getData');
    var serializedItems = JSON.stringify(items);
    jqBiz('#taxAuths').val(serializedItems);
    return true;
}";
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, empty($rID)?2:3)) { return; }
        parent::saveMeta($layout, $args=['_rID'=>$rID]);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteMeta($layout, ['_table'=>'common']);
    }

    private function cleanAuths($auths=[])
    {
        foreach ($auths['rows'] as $key => $auth) {
            if     (empty($auth['cID'])) { unset($auths['rows'][$key]); }
            elseif (empty($auth['rate'])){ unset($auths['rows'][$key]); }
        }
        return $auths;
    }
    public function bulkChange(&$layout=[])
    {
        $type  = clean('type', ['format'=>'char','default'=>'c'], 'get');
        $subjects = [['id'=>'c','text'=>lang('contacts')],['id'=>'i','text'=>lang('inventory')]];
        $icnGo = ['icon'=>'next', 'label'=>lang('go'),
            'events'=>  ['onClick'=>"var data='&type=$type&subject='+jqBiz('#subject').val()+'&srcID='+jqBiz('#taxSrc').combogrid('getValue')+'&destID='+jqBiz('#taxDest').combogrid('getValue');
                jsonAction('phreebooks/tax/bulkChangeSave'+data);"]];
        $html  = $this->lang['tax_bulk_src'] .'<br /><input id="taxSrc" name="taxSrc"><br />'.
                 $this->lang['tax_bulk_dest'].'<br /><input id="taxDest" name="taxDest"><br />'.
                 $this->lang['tax_subject']  .'<br />'.html5('subject', ['values'=>$subjects,'attr'=>['type'=>'select']]).'<br />'.html5('', $icnGo).'<br />';
        $jsBody= "function taxBulkChange(id, taxData) {
    jqBiz('#'+id).combogrid({data:taxData,width:120,panelWidth:210,idField:'id',textField:'text',
        rowStyler:function(idx, row) { if (row.status==1) { return {class:'journal-waiting'}; } else if (row.status==2) { return {class:'row-inactive'}; }  },
        columns:[[{field:'id',hidden:true},
            {field:'text',    width:120,title:'".jsLang('tax_rate_id')."'},
            {field:'tax_rate',width:70, title:'".jsLang('amount')."',align:'center'}]]
    });
}";
        $jsReady= "taxBulkChange('taxSrc', bizDefaults.taxRates.$type.rows);\ntaxBulkChange('taxDest', bizDefaults.taxRates.$type.rows);";
        $data = ['type'=>'popup','title'=>$this->lang['tax_bulk_title'],'attr'=>['id'=>'winTaxChange'],
            'divs'   => ['body'=> ['order'=>50,'type'=>'html', 'html'=>$html]],
            'jsBody' => ['taxJSBody'=> $jsBody],
            'jsReady'=> ['taxJSRdy' => $jsReady]];
        $layout = array_replace_recursive($layout, $data);
    }
    public function bulkChangeSave(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $type   = clean('type',   'char',    'get');
        $subject= clean('subject','char',    'get'); // either contact (c) or inventory (i)
        $srcID  = clean('srcID',  'integer', 'get'); // record ID to merge
        $destID = clean('destID', 'integer', 'get'); // record ID to keep
//      if (!$srcID || !$destID){ return msgAdd("Bad IDs, Source ID = $srcID and Destination ID = $destID"); } // commented out to allow None as a choice
        if ($srcID == $destID)  { return msgAdd("Source and destination cannot be the same!"); }
        $cnt = 0;
        if ($subject == 'i') {
            $field = $type=='v' ? 'tax_rate_id_v' : 'tax_rate_id_c';
            $cnt = dbWrite(BIZUNO_DB_PREFIX.'inventory', [$field => $destID], 'update', "$field = $srcID");
        } else {
            $cnt = dbWrite(BIZUNO_DB_PREFIX.'contacts', ['tax_rate_id'=>$destID], 'update', "tax_rate_id=$srcID");
        }
        msgAdd(sprintf($this->lang['tax_bulk_success'], $cnt), 'success');
        $table = $subject=='i' ? lang('inventory') : lang('contacts');
        msgLog(lang("phreebooks").'-'.$this->lang['tax_bulk_title']." $table: $srcID => $destID)");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winTaxChange');"]]);
    }
    private function dgTaxVendors($name)
    {
        $data = ['id' => $name, 'type'=>'edatagrid', 'title'=> 'Tax Authorities',
            'attr'    => ['toolbar'=>"#{$name}Toolbar",'rownumbers'=>true, 'showFooter'=>true, 'pagination'=>false],
            'events'  => ['data'=> "pbRates{$this->type}",
                'onClickRow'  => "function(rowIndex) { jqBiz('#$name').edatagrid('editRow', rowIndex); }",
                'onBeforeEdit'=> "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'=> ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['invVendTrash'=>['icon'=>'trash','order'=>20,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'cTitle'=> ['order'=>0, 'attr'=>['hidden'=>'true']],
                'cID'   => ['order'=>10,'label'=>lang('short_name_v'), 'attr'=>['width'=>250,'resizable'=>true,'align'=>'center'],
                    'events' => ['formatter'=>"function(value, row) { return row.cTitle; }",'editor'=>dgEditComboTax($name)]],
                'text'  => ['order'=>20,'label'=>lang('description'),'attr'=>['width'=>250,'resizable'=>true,'editor'=>'text']],
                'glAcct'=> ['order'=>30,'label'=>lang('gl_account'), 'attr'=>['width'=>100,'resizable'=>true,'align' =>'center'],'events'=>['editor'=>dgEditGL()]],
                'rate'  => ['order'=>40,'label'=>lang('tax_rate'),   'attr'=>['width'=>100,'resizable'=>true],
                    'events' => ['editor'=>dgEditNumber("taxTotal$this->type(newValue);")]]]];
        return $data;
    }
}
