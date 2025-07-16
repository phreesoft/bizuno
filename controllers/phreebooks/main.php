<?php
/*
 * Module PhreeBooks, main functions
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
 * @version    7.x Last Update: 2025-07-17
 * @filesource /controllers/phreebooks/main.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/journal.php','journal');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/payment/main.php',      'paymentMain');

class phreebooksMain
{
    public $moduleID = 'phreebooks';
    public $journalID= 0;
    public $gl_type  = '';
    public $lang;
    public $defaults;
    public $rID;
    public $action;
    public $totals;
    public $type;
    public $isACH;
    public $restrict = false;

    function __construct()
    {
        $this->lang  = getLang($this->moduleID);
        $this->rID   = clean('rID', 'integer', 'get');
        $this->action= clean('bizAction', 'text', 'get');
        if ($this->rID && !in_array($this->action, ['inv','ord'])) { $_GET['jID'] = $this->journalID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'journal_id', "id=$this->rID"); }
        else { $this->journalID = clean('jID', 'integer', 'get'); }
        if (!defined('JOURNAL_ID')) { define('JOURNAL_ID', $this->journalID); }
        $this->type  = clean('type', ['format'=>'char', 'default'=>in_array($this->journalID, [2,3,4,6,7,17,20,21]) ? 'v' : 'c'], 'get');
        switch ($this->journalID) {
            case  2: $this->gl_type= 'gl'; break; // General Journal
            case  3: // Vendor RFQ
            case  4: // Vendor PO
            case  6: // Vendor Purchases
            case  7: // Vendor Credit Memos
            case  9: // Customer RFQ
            case 10: // Customer SO
            case 12: // Customer Sales
            case 13: // Customer Credit Memos
            case 19: // Point of Sale
            case 21: $this->gl_type='itm'; break; // Point of Purchase
            case 14: $this->gl_type='asy'; break; // Inventory Assemblies
            case 15: // Inventory Store Transfers
            case 16: $this->gl_type='adj'; break; // Inventory Adjustments
            case 17: // Vendor Receipts
            case 18: // Customer Receipts
            case 20: // Vendor Payments
            case 22: $this->gl_type='pmt'; break; // Customer Payments
        }
    }

    /**
     * Entry point structure for the PhreeBooks journal manager, handles all journals
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        $cID   = clean('cID', 'integer', 'get');
        $mgr   = clean('mgr', 'boolean', 'get');
        $jsBody= $jsReady = '';
        $detail= lang('journal_id', $this->journalID);
        if     (in_array($this->journalID, [3, 4, 6, 7])) {
            $manager = sprintf(lang('tbd_manager'), lang('journal_id_6'));
        } elseif (in_array($this->journalID, [9,10,12,13])) {
            $manager = sprintf(lang('tbd_manager'), lang('journal_id_12'));
        } else { $manager= sprintf(lang('tbd_manager'), lang('journal_id', $this->journalID)); }
        if ($this->journalID == 0) { // search
            $jsBody  = "jqBiz('#postDateMin').datebox({onChange:function (newDate) { jqBiz('#postDateMin').val(newDate); } });
jqBiz('#postDateMax').datebox({onChange:function (newDate) { jqBiz('#postDateMax').val(newDate); } });";
            $jsReady.= "pbSetPrompt('refID','refIDMin','refIDMax'); pbSetPrompt('contactID','contactIDMin','contactIDMax'); pbSetPrompt('sku','skuMin','skuMax'); pbSetPrompt('amount','amountMin','amountMax'); pbSetPrompt('glAcct','glAcctMin','glAcctMax'); pbSetPrompt('rID','rIDMin','rIDMax');";
        } elseif (!$mgr || $rID || $cID) { // get the detail screen
            if ($this->action=='inv') {
                $jsReady = "setTimeout(function () { journalEdit($this->journalID, 0,    $cID, '$this->action', '', $rID) }, 500);\n";
            } elseif (!in_array($this->journalID, [0]) || $this->action=='bulk') {
                $jsReady = "setTimeout(function () { journalEdit($this->journalID, $rID, $cID, '$this->action') }, 500);\n";
            }
        }
        $jsReady .= "bizFocus('search', 'dgPhreeBooks');";
        $data = ['title'=> $detail,
            'divs'     => ['phreebooks'=>['order'=>60,'type'=>'accordion','key'=>'accJournal']],
            'accordion'=> ['accJournal'=> ['divs'=>[
                'divJournalManager'=> ['order'=>30,'label'=>$manager,'type'=>'datagrid','key' =>'manager'],
                'divJournalDetail' => ['order'=>60,'label'=>$detail, 'type'=>'html',    'html'=>lang('msg_edit_new')]]]],
            'datagrid' => ['manager'=> $this->dgPhreeBooks('dgPhreeBooks', $security)],
            'jsHead'   => ['init' => $this->jsFormValidate()],
            'jsBody'   => ['init' => $jsBody],
            'jsReady'  => ['init' => $jsReady]];
        if ($this->journalID == 0) {
            unset($data['accordion']['accJournal']['divs']['divJournalDetail']);
            for ($i=2; $i<23; $i++) { $jtitles['j'.$i] = lang('journal_id_'.$i); }
            $data['jsHead']['frmGrps'] = "var formGroups = ".json_encode(getDefaultFormID(0, true)).";";
            $data['jsHead']['jTitles'] = "var jrnlTitles = ".json_encode($jtitles).";";
            $data['datagrid']['manager']['columns']['action']['actions']['print']['events']['onClick'] = "winOpen('phreeformOpen', 'phreeform/render/open&group='+formGroups['jjrnlTBD']+'&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');";
        }
        if ($this->journalID == 2) { // add Payroll to the toolbar
            $payrolls = getMetaCommon('methods_payroll');
            msgDebug("\npayrolls read = ".print_r($payrolls, true));
            $order = 40;
            foreach ($payrolls as $idx => $payroll) {
                if (!empty($payroll['status'])) {
                    $data['datagrid']['manager']['source']['actions'][$payroll['id']] = ['order'=>$order,'icon'=>$payroll['id'],'label'=>'Paychex',
                        'events'=>['onClick'=>"jsonAction('$this->moduleID/payroll/importForm&payroll={$payroll['id']}');"]];
                    $order++;
                }
            }
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    private function jsFormValidate()
    {
        return "
 function formValidate() { // check form
    var error  = false;
    var message= '';
    var notes  = '';
    // With edit of order and recur, ask if roll through future entries or only this entry
    if (jqBiz('#id').val() > 0 && parseInt(jqBiz('#recur_id').val()) > 0) {
        if (confirm(bizLangJS('PB_RECUR_EDIT'))) { jqBiz('#recur_frequency').val('1'); }
        else { jqBiz('#recur_frequency').val('0'); }
    }
    switch (bizDefaults.phreebooks.journalID) {
        case  2:
            var balance = cleanCurrency(jqBiz('#total_balance').val());
            if (balance) {
                error = true;
                message += bizLangJS('PB_DBT_CRT_NOT_ZERO')+'<br>';
            }
            break;
        case  6:
        case  7: // Check for invoice_num exists with a recurring entry
            if (!jqBiz('#invoice_num').val() && jqBiz('#recur_id').val()>0) {
                message += bizLangJS('PB_INVOICE_RQD')+'<br>';
                error = true;
            }
            // validate that for purchases, either the waiting box needs to be checked or an invoice number needs to be entered
            if (!jqBiz('#invoice_num').val() && !bizCheckBoxGet('waiting')) {
                message += bizLangJS('PB_INVOICE_WAITING');
                error = true;
            }
            break;
        case  9:
        case 10:
        case 12: //validate item status (inactive, out of stock [SO] etc.)
            var rowData = jqBiz('#dgJournalItem').edatagrid('getData');
            for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
                var sku   = rowData.rows[rowIndex].sku;
                var qty   = cleanNumber(rowData.rows[rowIndex].qty);
                var stock = cleanNumber(rowData.rows[rowIndex].qty_stock);
                var track = jqBiz.inArray(rowData.rows[rowIndex].inventory_type, cogs_types);
                if (rowData.rows[rowIndex].sku != '' && qty>stock && track>-1) {
                    notes+= bizLangJS('PB_NEG_STOCK')+'\\n';
                    notes = notes.replace(/%s/g, rowData.rows[rowIndex].sku);
                    notes = notes.replace(/%i/g, stock);
                }
            }
            break;
        case  3:
        case  4:
        case 13:
        case 18:
        case 20:
        default:
    }
    if (error) { alert(message);    return false; }
    if (notes) if (!confirm(notes)) return false;
    if (!jqBiz('#frmJournal').form('validate')) return false;
    jqBiz('body').addClass('loading');
    return true;
}";
    }

    /**
     * List the journals filter by users selections
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $structure = $this->dgPhreeBooks('dgPhreeBooks', $security);
        if ($this->journalID==0) { $structure['strict'] = true; } // needed to search journal_item and limit rows per id
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$structure]]);
    }

    /**
     * Special grid list request for orders independent of period
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function managerRowsOrder(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $waiting = clean('waiting', 'integer', 'get');
        $_POST['search'] = getSearch();
        $data = $this->dgPhreeBooks('dgPhreeBooks', $security);
        unset($data['source']['filters']['period']);
        // reset some search criteria
        $data['source']['search'] = [BIZUNO_DB_PREFIX.'journal_main.invoice_num'];
        $data['source']['filters']['search'] = ['order'=>90,'label'=>lang('search'),'attr'=>['type'=>'input','value'=>getSearch()]];
        if ($waiting) {
            $data['source']['filters']['waiting']    = ['order'=>99,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_main.waiting='1'"];
            $data['source']['filters']['method_code']= ['order'=>99,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_main.method_code<>''"];
        }
        $data['source']['sort'] = ['s0'=>['order'=>10,'field'=>BIZUNO_DB_PREFIX."journal_main.invoice_num"]];
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$data]]);
    }

    /**
     * Builds a list of matches to a search for customers/vendors with outstanding unpaid invoices, also provides the invoiced total (not necessarily the outstanding amount)
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function managerRowsBank(&$layout=[])
    {
        $output = [];
        $_POST['search'] = $search_text = getSearch();
        switch ($this->journalID) {
            case 17:
            case 20: $jID= '6,7';  break;
            case 18:
            case 22: $jID='12,13'; break;
        }
        if ($this->journalID==20 && validateAccess('j2_mgr', 1)) { $jID .= ',2'; }
        $searchFields = [
            BIZUNO_DB_PREFIX.'journal_main.primary_name_b',
            BIZUNO_DB_PREFIX.'journal_main.primary_name_s',
            BIZUNO_DB_PREFIX.'journal_main.postal_code_b',
            BIZUNO_DB_PREFIX.'journal_main.postal_code_s',
            BIZUNO_DB_PREFIX.'journal_main.invoice_num',
            BIZUNO_DB_PREFIX.'journal_main.purch_order_id'];
        $search = $search_text ? "AND (".implode(" like '%$search_text%' or ", $searchFields)." like '%$search_text%')" : '';
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "closed='0' AND contact_id_b>0 AND journal_id IN ($jID) $search", 'primary_name_b',
            ['id','journal_id','contact_id_b','primary_name_b','city_b','state_b','total_amount']);
        foreach ($rows as $row) {
            if (in_array($row['journal_id'], [7,13])) { $row['total_amount'] = -$row['total_amount']; }
            $row['total_amount'] += getPaymentInfo($row['id'], $row['journal_id']);
            if (empty($output[$row['contact_id_b']])) { $output[$row['contact_id_b']] = $row; }
            else                                      { $output[$row['contact_id_b']]['total_amount'] += $row['total_amount']; }
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode(['total'=>sizeof($output),'rows'=>array_values($output)])]);
    }

    /**
     * Loads the filters from the query to populate the grid
     */
    private function managerSettings()
    {
        $defPeriod= getUserCache('profile', 'def_periods', '', 'l');
        $defRows  = getUserCache('profile', 'grid_rows', '', getModuleCache('bizuno','settings','general','max_rows'));
        $data     = ['path'=>'phreebooks'.$this->journalID, 'values'=>  [
            ['index'=>'rows',  'clean'=>'integer','default'=>$defRows],
            ['index'=>'page',  'clean'=>'integer','default'=>1],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX.'journal_main.post_date'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'DESC'],
            ['index'=>'period','clean'=>'alpha_num','default'=>$defPeriod],
            ['index'=>'jID',   'clean'=>'integer','default'=>'a'],
            ['index'=>'status','clean'=>'char',   'default'=>'a'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        if (clean('clr', 'boolean', 'get')) { clearUserCache($data['path']); }
        $this->defaults = updateSelection($data);
    }

    /**
     * Structure to edit a journal entry
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        global $html5;
//if (getUserCache('profile', 'email')=='support@phreesoft.com') { msgTrap(); }
        $rID        = $this->rID = clean('rID', ['format'=>'integer','default'=>0], 'get');
        $cID        = clean('cID', 'integer', 'get'); // contact record for banking stuff
        $references = explode(":", (string)clean('iID', 'text', 'get'));
        $xAction    = clean('xAction', 'text', 'get');
        $prefix     =  $rID && !in_array($this->action, ['inv','ord']) ? "rID_{$rID}_" : "rID_0_";
        $min_level  = !$rID ||  in_array($this->action, ['inv','ord']) ? 2 : 1; // if converting SO/PO then add else read-only and above
        $secID      = "j{$this->journalID}_mgr";
        if (!$security = validateAccess($secID, $min_level)) { return; }
        if (!$cID && sizeof($references) && !empty($references[0])) { // attempt to pull contact_id from prechecked records
            $cID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'contact_id_b', "id={$references[0]}");
        }
        $default_gl = $this->type=='v' ? getModuleCache('phreebooks', 'settings', 'vendors', 'gl_purchases') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales');
        $default_tax= 0;
        $preSubmit  = "function preSubmit() {\n\tjqBiz('#dgJournalItem').edatagrid('saveRow');\n\t
var items = jqBiz('#dgJournalItem').datagrid('getData');\n\tjqBiz('#item_array').val(JSON.stringify(items));\n\t
if (!formValidate()) return false;\n\treturn true;\n}";
        $jsHead     = $jsBody = '';
        $jsReady    = "ajaxForm('frmJournal');\njqBiz('#dgJournalItem').edatagrid('addRow');\n";
        if ($this->action=='inv' && !empty(getModuleCache('phreebooks', 'settings', $this->type=='v'?'vendors':'customers', 'show_status'))) {
            $jsReady .= "jsonAction('phreebooks/main/detailStatus', $cID);";
        }
        $structure  = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', $this->journalID);
        if ($this->action=='ord') { // special case where quote is converterd to order, duplicate the quote as ordeer and close quote
            $rID = $this->rID = $this->setQuote2Order($rID, $cID, $structure);
            msgDebug("\nreturned with new ID = $rID and journal ID = $this->journalID");
        }
        // complete the structure
        $structure['invoice_num']['tip'] = lang('msg_leave_null_to_assign_ref');
        $structure['rep_id']['values']   = viewRoleDropdown(in_array($this->journalID, [3, 4, 6, 7])?'purch':'sales');
        if (sizeof(getModuleCache('phreebooks', 'currency', 'iso')) > 1) {
            $structure['currency']['callback']    = 'totalsCurrency';
            $structure['currency']['attr']['type']= 'selCurrency';
            $structure['currency_rate']['attr']['readonly']= 'readonly';
            $structure['currency_rate']['attr']['type'] = 'float';
        }
        $ledger = new journal($rID, $this->journalID, false, $cID, $structure, $this->action);
        $ledger->journal->type  = $this->type;
        $ledger->journal->lang  = $this->lang;
        $ledger->journal->rID   = $this->rID;
        $ledger->journal->totals= $this->loadTotals($this->journalID);
        msgDebug("\ntotals to render = ".print_r($ledger->journal->totals, true));
        dbStructureFill($structure, $ledger->main);
        if ($rID > 0 || $cID > 0 || $this->action=='bulk') {
            msgDebug("\nReading unique journal $this->journalID data");
            if ($rID) { $jsReady .= "jqBiz('#spanContactProps_b').show();\n"; }
            $cID    = $structure['contact_id_b']['attr']['value'];
            $defs   = $cID ? dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['gl_account','tax_rate_id'], "id=$cID") : ['gl_account'=>$default_gl,'tax_rate_id'=>$default_tax];
            $jsBody.= "var def_contact_gl_acct = '{$defs['gl_account']}';\nvar def_contact_tax_id  = ".(empty($defs['tax_rate_id']) || $defs['tax_rate_id'] < 0 ? 0 : $defs['tax_rate_id']).";\n";
        } else { // new entry
            $structure['store_id']['attr']['value'] = getUserCache('profile', 'store_id', false, 0);
            $jsHead .= "var datagridData = ".json_encode(['total'=>0, 'rows'=>[]]).";\n";
            if ($this->action<>'inv') { $jsReady .= "bizCheckBox('AddUpdate_b');\n"; }
            $jsBody .= "var def_contact_gl_acct = '$default_gl';\nvar def_contact_tax_id = ".($default_tax ? $default_tax : 0).";\n";
        }
        // Add some new non-db fields
        $structure['terms_text']     = ['order'=>95,'label'=>lang('terms'),'break'=>false,'options'=>['width'=>175],'attr'=>['value'=>viewTerms($structure['terms']['attr']['value'], true, $this->type),'readonly'=>'readonly']];
        $structure['terms_edit']     = ['order'=>96,'icon'=>'settings','size'=>'small','label'=>lang('terms'),'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=$this->type', $cID, jqBiz('#terms').val());"]];
        $structure['journal_msg']    = ['html'=>'','attr'=>['type'=>'raw']];
        $structure['override_user']  = ['attr'=>['type'=>'hidden']];
        $structure['override_pass']  = ['attr'=>['type'=>'hidden']];
        $structure['recur_frequency']= ['attr'=>['type'=>'hidden']];
        $structure['item_array']     = ['attr'=>['type'=>'hidden']];
        $structure['xChild']         = ['attr'=>['type'=>'hidden']];
        $structure['xAction']        = ['attr'=>['type'=>'hidden']];
        // Set the Head , set defaults, gather the totals methods, etc.
        $jsHead  .= "bizDefaults.phreebooks = { journalID:$this->journalID,type:'$this->type' };\n";
        $jsHead  .= "var totalsMethods = ".json_encode($ledger->journal->totals).";\n";
        $jsTotals = "function totalUpdate(func) {\n\tvar taxRunning=0;\n\tvar curTotal=0;\n";
        $jsTotals.= "    if (!jqBiz('#total_amount').length) { return; }\n"; // make sure there is a totals panel
        foreach ($ledger->journal->totals as $methID) {
            $jsTotals .= "    if (typeof totals_{$methID}==='function') { curTotal=totals_{$methID}(curTotal); }\n";
        }
        $jsTotals.= "}";
        $jsHead  .= $jsTotals;
        // strip inactive accounts from the gl chart
        $pbChart  = "if (typeof(bizDefaults.glAccounts) == 'undefined') { alert('I cannot find the chart of accounts stored in your local browser memory. This page will not work properly! Please check your browser settings. You may also visit the PhreeSoft web site for assistance.'); }
var pbChart=[];\njqBiz.each(bizDefaults.glAccounts.rows, function( key, value ) { if (typeof(value['inactive'])=='undefined' || value['inactive'] == '0') { pbChart.push(value); } });";
        // Get the item data
        msgDebug("\nGoing to getDataItem, rID = $rID and cID = $cID");
        $ledger->getDataItem($rID, $cID, $security);
        msgDebug("\nsetting currency to {$structure['currency']['attr']['value']}");

        $data = ['type'=>'divHTML',
            'divs'    => [
                'tbJrnl' => ['order'=>10,'type'=>'toolbar','key' =>'tbPhreeBooks'],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmJournal'],
                'formEOF'=> ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'panels'  => [
                'notes'  => ['label'=>lang('notes'),'type'=>'fields','keys'=>['notes']],
                'divAtch'=> ['type'=>'attach', 'defaults'=>['path'=>getModuleCache($this->moduleID,'properties','attachPath','phreebooks'),'prefix'=>$prefix, 'secID'=>$secID]]],
            'toolbars'=> ['tbPhreeBooks'=>['icons'=>[
                'jSave'  => ['order'=>10,'label'=>lang('save'),  'icon'=>'save','type'=>'menu','hidden'=>$security>1?false:true,
                    'events'=> ['onClick'=>"jqBiz('#frmJournal').submit();"],'child'=>$this->renderMenuSave($security)],
                'recur'  => ['order'=>50,'label'=>lang('recur'), 'tip'=>lang('recur_new'),'hidden'=>$security>1?false:true,
                    'events'=>['onClick'=>"jsonAction('phreebooks/main/popupRecur', jqBiz('#recur_id').val(), jqBiz('#recur_frequency').val());"]],
                'new'  => ['order'=>60,'label'=>lang('new'),   'hidden'=>$security>1?false:true,'events'=>['onClick'=>"journalEdit($this->journalID, 0);"]],
                'trash'=> ['order'=>70,'label'=>lang('delete'),'hidden'=>$rID && $security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('phreebooks/main/delete&jID=$this->journalID', $rID);"]]]]],
            'forms'   => ['frmJournal'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=phreebooks/main/save&jID=$this->journalID"]]],
            'fields'  => $structure,
            'items'   => $ledger->items,
            'totals'  => $ledger->journal->totals,
            'jsHead'  => ['init'=>$jsHead, 'preSubmit'=>$preSubmit, 'pbChart'=>$pbChart],
            'jsBody'  => ['init'=>$jsBody],
            'jsReady' => ['init'=>$jsReady, 'focus'=>"bizFocus('contactSel_b');", 'init_total'=>"totalUpdate('init_edit');"]];

        // onChange so when shipping is edited after copied from Billing the address_id_s is cleared so an add/update results in the creation of a new shipping address
        // move this to the javascript address copy button
        // first turn the change off, then copy address, then turn it back on
        // need to add div for just the address block to capture just the address info and not the controls at the top
        // jqBiz('#address_block_s').off('change'); // turns it off
        // jqBiz('#address_block_s').change(function() { jqBiz('#address_id_s').val(0); }); // turns it back on

        // Customize the layout for this journalID
        $ledger->customizeView($data, $rID, $cID, $security);
        if (!empty($data['fields']['journal_msg']['html'])) {
            $data['divs']['status'] = ['order'=> 5,'styles'=>['float'=>'right'],'type'=>'fields','keys'=>['journal_msg']];
        }
        if (substr((string)$xAction, 0, 8) == 'journal:') { // see if there are any extra actions
            $temp = explode(':', $xAction);
            if (isset($temp[1])) {
                $data['toolbars']['tbPhreeBooks']['icons']['jSave']['events']['onClick'] = "jqBiz('#xAction').val('journal:{$temp[1]}'); jqBiz('#frmJournal').submit();";
                $data['toolbars']['tbPhreeBooks']['icons']['cancel'] = ['order'=>5,'events'=>['onClick'=>"journalEdit({$temp[1]}, 0);"]];
                unset($data['toolbars']['tbPhreeBooks']['icons']['new']);
            }
        }
        msgDebug("\nFinished edit processing!");
        $layout = array_replace_recursive($layout, $data);
        // add buy-sell changes
        $this->buySellEdit($layout);
        // Stores
        $action= clean('bizAction', 'alpha_num', 'get');
        if (!isset($layout['fields']['store_id']['attr']['value'])) { $layout['fields']['store_id']['attr']['value'] = 0; }
        msgDebug("\nEntering store.php with store_id = {$layout['fields']['store_id']['attr']['value']}, rID=$rID, jID=$this->journalID, and action=$action");
        if (empty($rID) && $this->restrict && !in_array($action, ['inv'])) { // if new record, pre-fill some data
            $layout['fields']['store_id']['attr']['value'] = $this->myStore;
            if (in_array($this->journalID, [3,4,6,7]) && !empty($this->myStore)) { // for purchases set default receiver to users branch
                $dbData = addressLoad($this->myStore, '_s', true);
                foreach ($dbData as $field => $value) { $layout['fields'][$field]['attr']['value'] = $value; }
            }
            if (in_array($this->journalID, [14,15,16]) && !empty($this->myStore)) { // set billing address to users Branch
                $dbData = addressLoad($this->myStore, '_b', true);
                foreach ($dbData as $field => $value) { $layout['fields'][$field]['attr']['value'] = $value; }
            }
        }
        msgDebug("\nLeaving prep with store_id = {$layout['fields']['store_id']['attr']['value']}");
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $layout['fields']['store_id'] = array_replace_recursive($layout['fields']['store_id'], ['order'=>18,'values'=>viewStores(),'break'=>true,
                'attr'=>['type'=>'select']]); // ,'events'=>['onChange'=>"crmDetail(this.value, '_s');"] // This should not be needed, don't change address info
        }
        $qty_store= jsLang('qty_store');
        $qty_stock= jsLang('all_stores');
        $qty_order= jsLang('qty_po');
        switch ($this->journalID) {
            case 14: // assemblies
                $layout['fields']['store_id']['events']['onChange']= " jqBiz('#sku').combogrid({ url:'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&clr=1&filter=assy&bID='+bizSelGet('store_id') });";
                $layout['fields']['sku']['defaults']['url']        = "'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&clr=1&filter=assy&bID='+bizSelGet('store_id')";
                $layout['fields']['sku']['defaults']['panelWidth'] = 650;
                $layout['fields']['sku']['defaults']['callback']   = "bizTextSet('description', data.description_short);
    jqBiz('#qty').val('1');
    jqBiz('#gl_account').val(data.gl_inv);
    jqBiz('#gl_acct_id').val(data.gl_inv);
    bizNumSet('qty_stock', data.qty_stock);
    jqBiz('#dgJournalItem').datagrid({ url:'".BIZUNO_AJAX."&bizRt=inventory/main/managerBOMList&rID='+data.id+'&bID='+bizSelGet('store_id') });
    jqBiz('#dgJournalItem').datagrid('reload');
    assyUpdateBalance();";
                $layout['fields']['sku']['defaults']['columns'] = "[[
    {field:'sku',              title:'".jsLang('sku')."',        width:100},
    {field:'description_short',title:'".jsLang('description')."',width:200},
    {field:'qty_stock',        title:'$qty_store',               width: 75,align:'right'},
    {field:'qty_all',          title:'$qty_stock',               width: 75,align:'right'},
    {field:'qty_po',           title:'".jsLang('qty_po')."',     width: 75,align:'right'},
    {field:'qty_so',           title:'".jsLang('qty_so')."',     width: 75,align:'right'}]]";
                break;
            case 15: // store transfers
//                $layout['divs']['divDetail']['divs']['props']['fields']['store_id'] = $layout['fields']['store_id'];
                $layout['datagrid']['item']['events']['onBeforeEdit'] = "function(rowIndex) {
    var edtURL = jqBiz(this).edatagrid('getColumnOption','sku');
    edtURL.editor.options.url = bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&bID='+bizSelGet('so_po_ref_id');
}";
                if (isset($layout['datagrid']['item']['columns']['sku'])) {
                    $layout['datagrid']['item']['columns']['sku']['events']['editor'] = "{type:'combogrid',options:{
    width: 150, panelWidth: 620, delay: 500, idField: 'sku', textField: 'sku', mode: 'remote',
    url:        bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&bID={$this->myStore}',
    onClickRow: function (idx, data) { adjFill(data); },
    columns:[[{field:'sku',        title:'".jsLang('sku')."',        width:100},
        {field:'description_short',title:'".jsLang('description')."',width:200},
        {field:'qty_stock',        title:'$qty_store',align:'right', width: 90},
        {field:'qty_all',          title:'$qty_stock',align:'right', width: 90},
        {field:'qty_po',           title:'$qty_order',align:'right', width: 90}]]
}}";
                }
                break;
            case 16: // adjustments
                if (!empty($this->restrict)) {
                    $layout['fields']['store_id']['attr']['type'] = 'hidden';
                } else {
                    $layout['fields']['store_id']['events']['onChange'] = "jqBiz('#dgJournalItem').edatagrid('endEdit', curIndex);";
                }
                $layout['datagrid']['item']['events']['onBeforeEdit'] = "function(rowIndex) {
    var edtURL = jqBiz(this).edatagrid('getColumnOption','sku');
    edtURL.editor.options.url = bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&bID='+bizSelGet('store_id');
}";
                if (isset($layout['datagrid']['item']['columns']['sku'])) {
                    $layout['datagrid']['item']['columns']['sku']['events']['editor'] = "{type:'combogrid',options:{
    width: 150, panelWidth: 620, delay: 500, idField: 'sku', textField: 'sku', mode: 'remote',
    url:        bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&bID={$this->myStore}',
    onClickRow: function (idx, data) { adjFill(data); },
    columns:[[{field:'sku',              title:'".jsLang('sku')."',width:100},
              {field:'description_short',title:'".jsLang('description')."',width:200},
              {field:'qty_stock',        title:'$qty_store',align:'right',width:90},
              {field:'qty_all',          title:'$qty_stock',align:'right',width:90},
              {field:'qty_po',           title:'$qty_order',align:'right',width:90}]]
}}";
                }
                break;
            default: // orders
                if (!empty($this->restrict)) {
                    $layout['fields']['store_id']['attr']['type'] = 'hidden';
                } else {
                    $layout['fields']['store_id']['events']['onChange'] = "jqBiz('#dgJournalItem').edatagrid('endEdit', curIndex);";
                }
                $type= in_array($this->journalID, [3,4,6,7]) ? 'v'   : 'c';
                $inv_field  = $type=='v' ? 'item_cost'   : 'full_price';
                $inv_title  = $type=='v' ? jsLang('cost'): jsLang('price');
                $gl_account = $type=='v' ? 'gl_inv'      : 'gl_sales';
                if (isset($layout['datagrid']['item']['columns']['sku'])) {
                    $layout['datagrid']['item']['columns']['sku']['events']['editor'] = "{type:'combogrid',options:{ url:bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1',
                        width:150, panelWidth:700, delay:500, idField:'sku', textField:'sku', mode:'remote',
                        onLoadSuccess: function () {
                            var skuEditor = jqBiz('#dgJournalItem').datagrid('getEditor', {index:curIndex,field:'sku'});
                            var g = jqBiz(skuEditor.target).combogrid('grid');
                            var r=g.datagrid('getData');
                            if (r.rows.length==1) { var cbValue = jqBiz(skuEditor.target).combogrid('getValue');
                                if (!cbValue) { return; }
                                if (r.rows[0].sku==cbValue || r.rows[0].upc_code==cbValue) {
                                    jqBiz(skuEditor.target).combogrid('hidePanel'); orderFill(r.rows[0], '$type');
                                }
                            }
                        },
                        onClickRow: function (idx, data) { orderFill(data, '$type'); },
                        columns:[[{field:'sku', title:'".jsLang('sku')."', width:150},
                            {field:'description_short',title:'".jsLang('description')."', width:250},
                            {field:'qty_stock',        title:'$qty_store', width:90,align:'right'},
                            {field:'qty_all',          title:'$qty_stock', width:90,align:'right'},
                            {field:'$inv_field',       title:'$inv_title', width:90,align:'right',formatter: function(value,row,index) { return formatCurrency(value); } },
                            {field:'$gl_account', hidden:true}, {field:'item_weight', hidden:true}]]}}";
                }
        }
        $layout['divs']['divDetail']['divs']['props']['fields']['store_id'] = $layout['fields']['store_id'];
        // Add second line to grids for multi-line display and line item tax ability.
        if (empty($this->settings['general']['multiLine'])) { return; }
        if (!in_array($this->journalID, [3,4,6,7,9,10,12,13])) { return; }
        $name = 'dgJournalItem';
        $type = in_array($this->journalID, [3,4,6,7,21]) ? 'v' : 'c';
        // extract the discounts and fill item array
        $strGrid = substr($layout['jsHead']['datagridData'], strpos($layout['jsHead']['datagridData'], '{'), -2); // remove ; and \n
        msgDebug("\nready to decode datagrid data: $strGrid");
        $items = json_decode($strGrid, true);
        foreach ($layout['items'] as $row) {
            if ($row['gl_type'] <> 'dsi') { continue; } // not a discount row
            msgDebug("\nWorking with validated row: ".print_r($row, true));
            $discount = $row['debit_amount'] + $row['credit_amount'];
            $this->setRowValue($items['rows'], $row['item_cnt'], $discount);
            msgDebug("\nModified row: ".print_r($items, true));
        }
        // rebuild the datagrid data
        $layout['jsHead']['datagridData'] = "var datagridData = ".json_encode(['total'=>sizeof($items['rows']), 'rows'=>$items['rows']]).";\n";
        msgDebug("\nNew datagrid data: {$layout['jsHead']['datagridData']}");
        $layout['datagrid']['item']['columns']['gl_account']    = ['order'=>0,'attr'=>['hidden'=>true]];
        $layout['datagrid']['item']['columns']['tax_rate_id']   = ['order'=>0,'attr'=>['hidden'=>true]];
        $layout['datagrid']['item']['columns']['unit_discount'] = ['order'=>0,'attr'=>['hidden'=>true]];
        $layout['datagrid']['item']['events']['view']           = "detailview";
        $layout['datagrid']['item']['events']['onEndEdit']      = "function(index,row) { jqBiz('#$name').edatagrid('collapseRow',index); }";
        $layout['datagrid']['item']['events']['detailFormatter']= "function(index,row) { return '<div style=\"padding:2px;position:relative;\"><table class=\"ddv\"></table></div>'; }";
        $layout['datagrid']['item']['events']['onCollapseRow']  = "function(index,row) {
    var ddv = jqBiz(this).datagrid('getRowDetail',index).find('table.ddv');
    ddv.edatagrid('endEdit', 0);
}";
        $layout['datagrid']['itemDetail']                       = $this->dgItemDetail($name, $this->journalID, $type);
        $layout['datagrid']['item']['events']['onExpandRow']    = "function(index,row) {
    if (typeof row.unit_discount == 'undefined') { row.unit_discount = 0; }
    var ddv = jqBiz(this).datagrid('getRowDetail',index).find('table.ddv');
    dgItemDetail.data = [{gl_account:row.gl_account, tax_rate_id:row.tax_rate_id, unit_discount:row.unit_discount}];
    ddv.edatagrid(dgItemDetail).edatagrid('editRow', 0);
    jqBiz('#$name').datagrid('fixDetailRowHeight',index);
}";
        $layout['jsHead']['dgItemDetail'] = "index = 0; var dgItemDetail = ".$html5->addGridProps($layout['datagrid']['itemDetail']).";";
        $layout['jsHead'][$this->moduleID] = "
function bizUnitDiscGL(index, row) {
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['gl_account'] = row.id;
}
function bizUnitDiscTax(index, row) {
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['tax_rate_id'] = row.id;
    totalUpdate();
}
function bizUnitDiscDisc(newValue) {
    jqBiz('#dgJournalItem').edatagrid('getRows')[curIndex]['unit_discount'] = newValue;
    totalUpdate();
}";
        msgDebug("\nLayout is now: ".print_r($layout, true));
    }

    /**
     * Posts a journal entry to the db
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        $rID = clean('id', 'integer', 'post');
        if (!$security = validateAccess("j{$this->journalID}_mgr", $rID?3:2)) { return; }
        $xChild   = clean('xChild', 'text', 'post');
        $xAction  = clean('xAction','text', 'post');
        $recurID  = clean('recur_id', 'integer', 'post');
        $recurFreq= clean('recur_frequency', 'integer', 'post');
        $GLOBALS['bizunoCurrency'] = clean('currency',['format'=>'alpha_num','default'=>getDefaultCurrency()],'post');
        $structure= ['journal_main' => dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', $this->journalID)];
        $values   = requestData($structure['journal_main'], '', true);
        $values['period'] = calculatePeriod($values['post_date'] ? $values['post_date'] : biz_date('Y-m-d')); // recalc period as post date may have changed
        // ***************************** START TRANSACTION *******************************
        // Assume all transactions are bulk transactions, or if no contact id set, treat as bulk.
        // iterate through the ref_id's and check for multiple contacts, perhaps pre-processing
        msgDebug("\n  Started order post invoice_num = {$values['invoice_num']} and id = {$rID}");
        dbTransactionStart();
        $ledger = new journal($rID, $this->journalID);
        $ledger->main['description'] = pullTableLabel('journal_main', 'journal_id', $this->journalID).": ".(!empty($values['primary_name_b']) ? $values['primary_name_b'] : '');
        $ledger->main = array_replace($ledger->main, $values);
/********** START add/update fix **************/
// @TODO - ADD THIS ROW
        if (!$ledger->updateContact()) { return; } // Create/Update contact information
// DELETE THESE ROWS AFTER 7.1
        // add/update address book, address updates need to be here so recur doesn't keep making new contacts
/*      if (clean('AddUpdate_b', 'bool', 'post')) { if (!$ledger->updateContact('b')) { return; } }
        if (clean('AddUpdate_s', 'bool', 'post')) {
            if (!$ledger->main['contact_id_s']) { $ledger->main['contact_id_s'] = $ledger->main['contact_id_b']; }
            if ( $ledger->main['address_id_s'] == $ledger->main['address_id_b']) { $ledger->main['address_id_s'] = 0; } // when address copy and then edit shipping, prevents updating billing
            if (!$ledger->updateContact('s'))   { return; }
        } */
/********** END add/update fix **************/
        if (in_array($ledger->journalID, [3,4,6,7,9,10,12,13]) && empty($ledger->main['contact_id_b'])) { return msgAdd($this->lang['msg_missing_contact_id']); }
        if (!$this->getItems($ledger))  { return; }
        if (!$this->getTotals($ledger)) { return; }
        msgDebug("\nMapped journal rows = ".print_r($ledger->items, true));
        $ledger->isACH = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'ach_enable', "id={$ledger->main['contact_id_b']}");
        // ************* POST journal entry *************
        if ($ledger->main['recur_id'] > 0) { // if new record, will contain count, if edit will contain recur_id
            $first_invoice_num   = $ledger->main['invoice_num'];
            if ($ledger->main['id']) { // it's an edit, fetch list of affected records to update if roll is enabled
                $affected_ids = $ledger->get_recur_ids($ledger->main['recur_id'], $ledger->main['id']);
                msgDebug("\nAffected ID's for recurring entry: ".print_r($affected_ids, true));
                for ($i = 0; $i < count($affected_ids); $i++) {
                    $ledger->main = array_replace($ledger->main, $affected_ids[$i]);
                    if ($i > 0) { // Remove row id's for future posts, keep if re-posting single entry
                        for ($j = 0; $j < count($ledger->items); $j++) { $ledger->items[$j]['id'] = 0; }
                    } else { // allow for changes to some main fields in the recur, first post only
                        $ledger->main = array_replace($ledger->main, ['post_date'=>$values['post_date'],'invoice_num'=>$values['invoice_num'],'terminal_date'=>$values['terminal_date']]);
                    }
                    msgDebug("\n************ Re-posting recur id = {$ledger->main['id']} ******************");
                    if (!$ledger->Post()) { return; }
                    $ledger->postList = []; // reset the postList to prevent reposting prior recurs
                    // test for single post versus rolling into future posts, terminate loop if single post
                    if (empty($recurFreq)) { break; }
                }
            } else { // it's an insert, fetch the next recur id
                $forceInv = $ledger->main['invoice_num'] != '' ? $ledger->main['invoice_num'] : false;
                $ledger->main['recur_id'] = time(); // time stamp the transaction to link together
                $origPost = clone $ledger;
                for ($i=1; $i<=$recurID; $i++) { // number of recurs
                    if (!$ledger->Post()) { return; }
                    if ($i == 1) { $first_invoice_num = $ledger->main['invoice_num']; }
                    if ($i == $recurID) { continue; } // we're done, skip the prep
                    // prepare the next post or prepare to exit if finished
                    $ledger = clone $origPost;
                    switch ($recurFreq) {
                        default:
                        case '1': $day_offset = $i*7;  $month_offset = 0; break; // Weekly
                        case '2': $day_offset = $i*14; $month_offset = 0; break; // Bi-weekly
                        case '3': $day_offset = 0; $month_offset = $i;    break; // Monthly
                        case '4': $day_offset = 0; $month_offset = $i*3;  break; // Quarterly
                    }
                    $ledger->main['post_date']    = localeCalculateDate($ledger->main['post_date'],     $day_offset, $month_offset);
                    $ledger->main['terminal_date']= localeCalculateDate($ledger->main['terminal_date'], $day_offset, $month_offset);
                    $ledger->main['period']       = calculatePeriod($ledger->main['post_date'], false);
                    if ($forceInv) { $forceInv++; $ledger->main['invoice_num'] = $forceInv; }
                    foreach ($ledger->items as $key => $row) {
                        $ledger->items[$key]['post_date'] = $ledger->main['post_date'];
                        $ledger->items[$key]['date_1']    = $ledger->main['terminal_date'];
                    }
                }
            }
            // restore the first values to continue with post process
            $ledger->main['invoice_num'] = $first_invoice_num;
        } else {
            if (!$ledger->Post()) { return; }
        }
        // ************* post-POST processing *************
        // @TODO - This probably should be moved to the associated journal class.
        // Not sure 17 should be here anyway.
        if (in_array($this->journalID, [17, 18, 19])) { // process the payment, must be at end since it's hard to undo, no transaction support
            $processor = new paymentMain();
            if (!$processor->sale($ledger->main['method_code'], $ledger)) { return; }
        } elseif (in_array($this->journalID, [20])) { // increment and save the ref for the next bill pay
            $nextInv = $ledger->main['invoice_num'];
            $nextInv++;
            setNextReference('next_ref_j20', $nextInv);
        }
        msgDebug("\n  Committing order invoice_num = {$ledger->main['invoice_num']} and id = {$ledger->main['id']}");
        dbTransactionCommit();
        // ***************************** END TRANSACTION *******************************
        $_POST['rID'] = $ledger->main['id']; // set the record ID as we now have a successfult transaction
        $this->getAttachments('file_attach', $ledger->main['id'], $ledger->main['so_po_ref_id']);
        $invoiceRef = lang('invoice_num', $ledger->main['journal_id']);
        $billName   = isset($ledger->main['primary_name_b']) ? $ledger->main['primary_name_b'] : $ledger->main['description'];
        $journalRef = lang('journal_id', $ledger->main['journal_id']);
        msgAdd(sprintf(lang('msg_gl_post_success'), $invoiceRef, $ledger->main['invoice_num']), 'success');
        msgLog($journalRef.'-'.lang('save')." $invoiceRef ".$ledger->main['invoice_num']." - $billName (rID={$ledger->main['id']}) ".lang('total').": ".viewFormat($ledger->main['total_amount'], 'currency'));
        $jsonAction = "jqBiz('#accJournal').accordion('select',0); bizGridReload('dgPhreeBooks'); jqBiz('#divJournalDetail').html('&nbsp;');";
        if (in_array($ledger->main['journal_id'], [2,3,4,6,9,10,12,20,22])) { $jsonAction = "jqBiz('#jSave').splitbutton('destroy'); ".$jsonAction; } // only for splitbutton, else errors and halts script
        switch ($xAction) { // post processing extra stuff to do
            case 'invoice':
                switch ($this->journalID) {
                    case  4: $next_journal =  6; break;
                    case 10: $next_journal = 12; break;
                    default: $next_journal = false;
                }
                if ($next_journal) {
                    $jsonAction = "bizGridReload('dgPhreeBooks'); journalEdit($next_journal, 0, 0, 'inv', '', {$ledger->main['id']});";
                }
                break;
            case 'journal:12':
                $jsonAction = "bizGridReload('dgPhreeBooks'); journalEdit(12, 0);"; break;
            case 'journal:6':
                $jsonAction = "bizGridReload('dgPhreeBooks'); journalEdit(6, 0);"; break;
            case 'payment':
                switch ($this->journalID) {
                    case  6: $next_journal = 20; break;
                    case  7: $next_journal = 17; break;
                    case 12: $next_journal = 18; break;
                    case 13: $next_journal = 22; break;
                    default: $next_journal = false;
                }
                if ($next_journal) {
                    $jsonAction = "bizGridReload('dgPhreeBooks'); journalEdit($next_journal, 0, {$ledger->main['contact_id_b']}, 'inv', '', {$ledger->main['id']});";
                }
                break;
            case 'prepay':
                if (!in_array($this->journalID, [4,10])) { break; } // only allowed for sales orders and purchase orders
                $invRID    = $this->savePrepay($ledger);
                $jsonAction= "bizGridReload('dgPhreeBooks'); journalEdit(".($this->journalID==4?20:18).", 0, {$ledger->main['contact_id_b']}, 'inv', '', $invRID);";
                break;
        }
        switch ($xChild) { // child screens to spawn
            case 'print':
                $formID     = getDefaultFormID($this->journalID);
                $jsonAction .= " winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&date=a&xfld=journal_main.id&xcr=equal&xmin={$ledger->main['id']}');";
                break;
        }
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>['action'=>'eval','actionData'=>$jsonAction]]);
        // add buy-sell changes
        $this->buySellSave($layout);
        // Stores
        if ($this->journalID <> 15) { return; } // only for store transfers
        $bAddr = clean('contact_id_b', 'integer', 'post');
        $sAddr = clean('contact_id_s', 'integer', 'post');
        $bInfo = addressLoad($bAddr, $suffix='_b');
        foreach ($bInfo as $key => $value) { $_POST[$key] = $value; }
        $sInfo = addressLoad($sAddr, $suffix='_s');
        foreach ($sInfo as $key => $value) { $_POST[$key] = $value; }
    }

    /**
     * Structure to pay bills to multiple vendors from a single page view
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function saveBulk(&$layout=[])
    {
        msgDebug("\n  Started saveBulk");
        if (!$security = validateAccess('j20_bulk', 2)) { return; }
        $xChild = clean('xChild', 'text', 'post');
        $structure= [
            'journal_main' => dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', $this->journalID),
            'journal_item' => dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID)];
        clearUserCache('phreebooks'.$this->journalID); // clear the manager history for new saves, will then show new post on top.
        // organize selections by contact_id create mini 'item_data' for each contact
        $rows = clean('item_array', 'json', 'post');
        $cIDs = $chkIDs = [];
        foreach ($rows as $row) {
            if (!isset($cIDs[$row['contact_id']]['dsc'])) { $cIDs[$row['contact_id']]['dsc'] = 0; }
            if (!isset($cIDs[$row['contact_id']]['ttl'])) { $cIDs[$row['contact_id']]['ttl'] = 0; }
            $cIDs[$row['contact_id']]['dsc']   += clean($row['discount'],'float');
            $cIDs[$row['contact_id']]['ttl']   += clean($row['total'],   'float');
            $cIDs[$row['contact_id']]['rows'][] = $row;
        }
        $post_date = clean('post_date', 'date', 'post');
        $first_chk = $current_chk = $next_chk = clean('invoice_num', 'text', 'post'); // save the first check number for printing
        $pmt_total = clean('total_amount', 'float', 'post');
        if (!$first_chk) { return msgAdd("Ref # cannot be blank!"); }
        // ***************************** START TRANSACTION *******************************
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/payment/nacha.php', 'paymentNacha');
        $achMapID = clean('totals_achMapID', 'alpha_num', 'post');
        $nacha = new paymentNacha($achMapID);
        $nacha->openACH($achMapID);
        dbTransactionStart();
        $postedGLAcct = clean('gl_acct_id', 'text', 'post'); // need to capture the original GL Account desired by the user as ACH overwrites it if an ACH tranaction is posted
        foreach ($cIDs as $cID => $items) {
            $address= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
            $isACH  = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'ach_enable', "id=$cID");
            $ledger = new journal(0, $this->journalID, $post_date);
            $ledger->isACH = $isACH;
            // Fill mains and items
            $_POST['totals_discount']= $items['dsc']; // need to fake out form total for each vendor
            $_POST['total_amount']   = $items['ttl'];
            $_POST['gl_acct_id']     = $isACH ? $nacha->myData['gl_acct'] : $postedGLAcct;
            $_POST['item_array']     = json_encode($items['rows']); // just the rows for this contact
            $current_chk = $next_chk;
            $main = [
                'gl_acct_id'    => clean('gl_acct_id', 'text', 'post'),
                'invoice_num'   => $current_chk,
                'purch_order_id'=> clean('purch_order_id', 'text', 'post'),
                'rep_id'        => clean('rep_id', 'integer', 'post'),
                'contact_id_b'  => $cID,
                'address_id_b'  => 0, // no longer used
                'primary_name_b'=> $address['primary_name'],
                'contact_b'     => $address['contact'],
                'address1_b'    => $address['address1'],
                'address2_b'    => $address['address2'],
                'city_b'        => $address['city'],
                'state_b'       => $address['state'],
                'postal_code_b' => $address['postal_code'],
                'country_b'     => $address['country'],
                'telephone1_b'  => $address['telephone1'],
                'email_b'       => $address['email']];
            $ledger->main = array_replace($ledger->main, $main);
            // pull items
            $map = [
                'ref_id'        => ['type'=>'constant','value'=>$ledger->main['id']],
                'gl_type'       => ['type'=>'constant','value'=>$this->gl_type],
                'post_date'     => ['type'=>'constant','value'=>$ledger->main['post_date']],
                'credit_amount' => ['type'=>'constant','value'=>'0'],
                'debit_amount'  => ['type'=>'field',   'index'=>'total'],
                'date_1'        => ['type'=>'field',   'index'=>'inv_date'],
                'trans_code'    => ['type'=>'field',   'index'=>'inv_num']];
            $ledger->items= requestDataGrid($items['rows'], $structure['journal_item'], $map);
            foreach ($ledger->items as $idx => $row) { $ledger->items[$idx]['item_cnt'] = $idx+1; }
            $ledger->main['description'] = pullTableLabel('journal_main', 'journal_id', $ledger->main['journal_id']);
            $ledger->main['description'].= isset($ledger->main['primary_name_b']) ? ": {$ledger->main['primary_name_b']}" : '';
            // pull totals
            $current_total = 0;
            foreach ($ledger->items as $row) { $current_total += $row['debit_amount'] + $row['credit_amount']; } // subtotal of all rows
            msgDebug("\n\nStarting to build total GL rows, starting subtotal = $current_total");
            $ledger->totals = $this->loadTotals($this->journalID);
            foreach ($ledger->totals as $methID) {
                $meta = getMetaMethod('totals', $methID);
                $fqcn = "\\bizuno\\$methID";
                bizAutoLoad("{$meta['path']}$methID.php", $fqcn);
                $totalEntry = new $fqcn();
                if (method_exists($totalEntry, 'glEntry')) { $totalEntry->glEntry($ledger->main, $ledger->items, $current_total); }
            }
            msgDebug("\n\nMapped journal main = ".print_r($ledger->main,  true));
            msgDebug("\n\nMapped journal item = ".print_r($ledger->items, true));
            // check calculated total against submitted total, course error check
            if (round($current_total, 2) <> round($ledger->main['total_amount'], 2)) {
                msgDebug("\nin SaveBulk, failed comparing calc total =  ".round($current_total, 2)." with submitted total = ".round($ledger->main['total_amount'], 2));
                return msgAdd(sprintf($this->lang['err_total_not_match'], round($current_total, 2), round($ledger->main['total_amount'], 2)), 'trap');
            }
            $nacha->process($ledger);
            if (!$ledger->Post())      { return; }
            if (empty($ledger->ach))   {
                msgDebug("\nNot an ACH transaction, incrementing the check number");
                $next_chk++;
                $chkIDs[] = $ledger->main['id'];
            }
            msgDebug("\n  Committing order invoice_num = $current_chk and id = {$ledger->main['id']}");
        }
        $nacha->closeACH();
        dbTransactionCommit();
        // ***************************** END TRANSACTION *******************************
        $invRef = pullTableLabel('journal_main', 'invoice_num', $ledger->main['journal_id']);
        msgAdd(sprintf(lang('msg_gl_post_success'), $invRef, "$first_chk - $current_chk"), 'success');
        msgLog(lang('phreebooks_manager_bulk').'-'.lang('invoice_num_20')." $first_chk - $current_chk ".lang('total').": ".viewFormat($pmt_total, 'currency'));
        $jsonAction = "jqBiz('#accJournal').accordion('select',0); bizGridReload('dgPhreeBooks'); jqBiz('#divJournalDetail').html('&nbsp;');";
        switch ($xChild) { // post processing extra stuff to do
            case 'print':
                $formID = getDefaultFormID($this->journalID);
                msgDebug("\nReady to print with check IDs array: ".print_r($chkIDs, true));
                if (empty($chkIDs)) {
                    msgAdd("No checks to print, all ACH", 'info');
                } else {
                    $mIDs = implode(',', $chkIDs);
                    $jsonAction.= " winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&date=a&xfld=journal_main.id&xcr=in_list&xmin=$mIDs');";
                }
                break;
        }
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'],'content'=>['action'=>'eval','actionData'=>$jsonAction]]);
    }

    /**
     * Following the post to jID 4/10, this method creates new journal entries for a prepayment/deposit
     * @param object $ledger - journal information of the just posted PO/SO
     * @param float $amount - amount to apply as the prepayment
     * @return journal_main id of the invoice to collect payment against
     */
    private function savePrepay($ledger, $amount=0)
    {
        if (empty($amount)) { $amount = clean('total_prepay', 'float', 'post'); }
        if ($amount <= 0) { return msgAdd("Save and Prepay was selected but the amount of the prepayment must be greater than zero!", 'caution'); } // nothing to do here
        dbTransactionStart();
        $jID = $ledger->main['journal_id'];
        // post the prepay invoice
        $invLdgr = new journal(0, $jID==4?6:12);
        $this->prepJournal($invLdgr, $ledger, $jID==4?6:12, $amount);
        if (!$invLdgr->Post()) { return; }
        $invRID = $invLdgr->main['id'];
        msgDebug("\nReturned from posting prepayment invoice with id = $invRID");
        // Post the credit memo
        $cmJrnl = new journal(0, $jID==4?7:13);
        $this->prepJournal($cmJrnl, $ledger, $jID==4?7:13, $amount);
        $cmJrnl->Post();
        dbTransactionCommit();
        return $invRID;
    }

    /**
     * Clear/alter necessary fields to post a ledger object to a new journal entry
     * @param type $ledger
     * @param type $jID
     */
    private function prepJournal(&$newLdgr, $origLdgr, $jID, $amount)
    {
        $newLdgr->main = $origLdgr->main;
        // retained: all address info plus 'purch_order_id','rep_id','admin_id'
        unset($newLdgr->main['id'],$newLdgr->main['discount'],$newLdgr->main['sales_tax'],$newLdgr->main['freight'],$newLdgr->main['waiting']);
        unset($newLdgr->main['closed'],$newLdgr->main['terms'],$newLdgr->main['notes'],$newLdgr->main['tax_rate_id'],$newLdgr->main['drop_ship']);
        unset($newLdgr->main['method_code'],$newLdgr->main['currency'],$newLdgr->main['currency_rate'],$newLdgr->main['so_po_ref_id'],$newLdgr->main['closed_date']);
        unset($newLdgr->main['recur_id'],$newLdgr->main['printed'],$newLdgr->main['attach']);
        $newLdgr->main['journal_id']  = $jID;
        $newLdgr->main['post_date']   = $newLdgr->main['terminal_date'] = biz_date(); // today
        $newLdgr->main['description'] = 'Invoice/Credit for pre-payment for PO/SO # '.$origLdgr->main['invoice_num'];
        $newLdgr->main['total_amount']= $amount;
        if (in_array($jID, [6,7])) {
            $itmGlAcct = getModuleCache('phreebooks', 'settings', 'vendors', 'gl_deposit');
            $ttlGlAcct = getModuleCache('phreebooks', 'settings', 'vendors', 'gl_payables');
        } else {
            $itmGlAcct = getModuleCache('phreebooks', 'settings', 'customers', 'gl_deposit');
            $ttlGlAcct = getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables');
        }
        $newLdgr->main['invoice_num']= (in_array($jID,[6,12])?'DPI':'DPC').str_pad($origLdgr->main['id'], 7, '0', STR_PAD_LEFT);
        $newLdgr->main['gl_acct_id'] = $ttlGlAcct;
        $newLdgr->items[] = [ // gl account needs to be the default purchases/sales account
            'gl_type'      => 'itm',
            'qty'          => 1,
            'description'  => $newLdgr->main['description'],
            'debit_amount' => in_array($jID, [6,13]) ? $amount : 0,
            'credit_amount'=> in_array($jID, [7,12]) ? $amount : 0,
            'gl_account'   => $itmGlAcct,
            'post_date'    => $newLdgr->main['post_date']];
        $newLdgr->items[] = [ // gl account needs to be the default purchases/sales account
            'gl_type'      => 'ttl',
            'qty'          => 1,
            'description'  => $newLdgr->main['description'],
            'debit_amount' => in_array($jID, [7,12]) ? $amount : 0,
            'credit_amount'=> in_array($jID, [6,13]) ? $amount : 0,
            'gl_account'   => $ttlGlAcct,
            'post_date'    => $newLdgr->main['post_date']];
    }

    /**
     * Structure to delete a single PhreeBooks journal entry
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!validateAccess("j{$this->journalID}_mgr", 4)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $delRecur= clean('delAll', 'integer', 'get');
        $delOrd  = new journal($rID, $this->journalID);
        // Error Check
        if (!$rID) { return msgAdd(lang('err_copy_name_prompt')); }
        if (getUserCache('profile', 'restrict_period') && $delOrd->period <> getModuleCache('phreebooks', 'fy', 'period')) {
            return msgAdd(lang('ORD_ERROR_DEL_NOT_CUR_PERIOD'));
        }
        switch ($this->journalID) { // some rules checking
            case  4: // Purchase Order Journal
            case 10: // Sales Order Journal
                if ($xID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', "id", "so_po_ref_id=$rID")) { return msgAdd(sprintf($this->lang['err_journal_delete'], "(id=$xID) ".lang('journal_id_'.$this->journalID))); }
                break;
            case  6: // Purchase Journal
            case  7: // Vendor Credit Memo Journal
            case 12: // Sales/Invoice Journal
            case 13: // Customer Credit Memo Journal
                // first check for main entries that refer to delete id (credit memos)
                if ($xID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', "id", "so_po_ref_id=$rID")) { return msgAdd(sprintf($this->lang['err_journal_delete'], "(id=$xID) ".lang('journal_id_'.$this->journalID))); }
                // next check for payments that link to deleted id (payments)
                if ($xID = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "id", "gl_type='pmt' AND item_ref_id=$rID")) { return msgAdd(sprintf($this->lang['err_journal_delete'], "(id=$xID) ".lang('journal_id_'.$this->journalID))); }
                break;
            default:
        }
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        // ************* pre-unPOST processing *************
        // @TODO Move this to the specific journal entries, 17 is probably not corect as this should be a refund
        if (in_array($this->journalID, [17, 18, 19])) { // refund the payment, must be at start since record is deleted with trans_code
            $method = $delOrd->main['method_code'];
            $gateway= getMetaMethod('gateways', $method);
            msgDebug("\nmain record = ".print_r($delOrd->main, true));
            msgDebug("\nmethod to apply the delete to = $method");
            $fqcn   = "\\bizuno\\$method";
            bizAutoLoad("{$gateway['path']}$method.php", $fqcn);
            $processor = new $fqcn();
            if ($delOrd->main['post_date'] == biz_date()) {
                if (method_exists($processor, 'void')) { if (!$processor->void($delOrd->main['id'])) { return; } }
            }
        }
        if (isset($delOrd->recur_id) && $delOrd->recur_id > 0 && $delRecur) { // will contain recur_id
            $affected_ids = $delOrd->get_recur_ids($delOrd->recur_id, $delOrd->id);
            foreach  ($affected_ids as $mID) {
                $delRecur = new journal($mID['id']);
                msgDebug("\nunPosting recur id = {$delRecur->main['id']}");
                if (!$delRecur->unPost()) { return dbTransactionRollback(); }
            }
        } else {
            msgDebug("\nunPosting id = {$delOrd->main['id']}");
            if (!$delOrd->unPost()) { return dbTransactionRollback(); }
        }
        dbTransactionCommit();
        // *************** END TRANSACTION *************************
        msgLog(lang('journal_id', $this->journalID).' '.lang('delete')." - {$delOrd->main['invoice_num']} (rID=$rID)");
        $files = glob(getModuleCache('phreebooks', 'properties', 'attachPath', 'phreebooks')."rID_".$rID."_*.*");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } } // remove attachments
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accJournal').accordion('select',0); bizGridReload('dgPhreeBooks'); jqBiz('#divJournalDetail').html('&nbsp;');"]]);
    }

    /**
     * Gets the grid items and removes empty rows
     * @param array $ledger - Structure coming in
     * @return modified $layout
     */
    private function getItems(&$ledger)
    {
        $ledger->items = []; // reset the item list as we start with just the datagrid
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID);
        $map = [
            'ref_id'   => ['type'=>'constant', 'value'=>$ledger->main['id']],
            'gl_type'  => ['type'=>'constant', 'value'=>$this->gl_type],
            'post_date'=> ['type'=>'constant', 'value'=>$ledger->main['post_date']]];
        if (!in_array($this->journalID, [2])) {
            $debitCredit = in_array($this->journalID, [3,4,6,13,16,20,21,22]) ? 'debit' : 'credit';
            $map['credit_amount']= $debitCredit=='credit'? ['type'=>'field','index'=>'total'] : ['type'=>'constant','value'=>0];
            $map['debit_amount'] = $debitCredit=='debit' ? ['type'=>'field','index'=>'total'] : ['type'=>'constant','value'=>0];
        }
        if (in_array($this->journalID, [17,18,20,22])) {
            $map['date_1']    = ['type'=>'field','index'=>'post_date'];
            $map['trans_code']= ['type'=>'field','index'=>'invoice_num'];
        }
        msgDebug("\nitem array before decode: ".print_r($_POST['item_array'], true));
        $temp = clean('item_array', 'json', 'post');
        msgDebug("\nitem array after clean: ".print_r($temp, true));
        msgDebug("\nLast json error: ".print_r(json_last_error(), true));
        $items    = requestDataGrid($temp, $structure, $map);
        $skipList = ['sku', 'description', 'credit_amount', 'debit_amount']; // if qty=0 or all these are not set or null, row is blank
        $item_cnt = 1;
        foreach ($items as $row) {
            if (!isBlankRow($row, $skipList)) {
                $row['item_cnt']     = $item_cnt;
                $row['debit_amount'] = roundAmount($row['debit_amount']);
                $row['credit_amount']= roundAmount($row['credit_amount']);
                $ledger->items[] = $row;
            }
            $item_cnt++;
        }
        if (empty($ledger->items)) { return msgAdd($this->lang['msg_no_items']); }
        $this->mapStoreGL($ledger);
        return true;
    }

    /**
     * Maps the inventory account to the assigned store if used
     */
    private function mapStoreGL(&$ledger=[])
    {
        if (empty($ledger->main['store_id']) || $this->journalID==2) { return; } // skip if home store or general ledger
        $storeGL = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'terms', "id={$ledger->main['store_id']}");
        msgDebug("\nEntering mapStoreGL with store = {$ledger->main['store_id']} and storeGL $storeGL");
        if (empty($storeGL)) { return; }
        foreach ($ledger->items as $key => $value) {
            $glType = getModuleCache('phreebooks', 'chart', $value['gl_account']);
            if ('4'==$glType['type']) { $ledger->items[$key]['gl_account'] = $storeGL; }
        }
    }

    /**
     * Pulls posted total values and creates the GL entries
     * @param array $ledger - structure coming in
     */
    private function getTotals(&$ledger)
    {
        $current_total = 0;
        foreach ($ledger->items as $row) { $current_total += $row['debit_amount'] + $row['credit_amount']; } // subtotal of all rows
        msgDebug("\nStarting to build total GL rows, starting subtotal = $current_total");
        $ledger->main['sales_tax'] = $ledger->main['discount'] = 0; // clear the sales tax and discount before building new values
        $ledger->totals = $this->loadTotals($this->journalID);
        foreach ($ledger->totals as $methID) {
            $meta = getMetaMethod('totals', $methID);
            $fqcn = "\\bizuno\\$methID";
            bizAutoLoad("{$meta['path']}$methID.php", $fqcn);
            $totalEntry = new $fqcn();
            if (method_exists($totalEntry, 'glEntry')) { $totalEntry->glEntry($ledger->main, $ledger->items, $current_total); }
        }
        // check calculated total against submitted total, course error check
        if (!in_array($this->journalID, array(2,14,15,16)) && number_format($current_total, 2) <> number_format($ledger->main['total_amount'], 2)) {
            msgDebug("\nIn getTotals, failed comparing calc total =  ".number_format($current_total, 2)." with submitted total = ".number_format($ledger->main['total_amount'], 2));
            return msgAdd(sprintf($this->lang['err_total_not_match'], number_format($current_total, 2), number_format($ledger->main['total_amount'], 2)), 'trap');
        }
        return true;
    }

    /**
     * Retrieves the detailed status for a single contact, typically used as a pop up
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function detailStatus(&$layout=[], $rID=0)
    {
        $cID     = !empty($rID) ? $rID : clean('rID', 'integer', 'get');
        if (empty($cID)) { return("Bad ID"); }
        $contact = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
        $new_data= calculate_aging($cID);
        $data = ['type'=>'popup','attr'=>['id'=>'winStatus'],
            'title'=> lang('summary'),
            'divs' => [
                'general' => ['order'=>10,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genStat' => ['order'=>10,'type'=>'panel','key'=>'genStat','classes'=>['block50']],
                    'genHist' => ['order'=>30,'type'=>'panel','key'=>'genHist','classes'=>['block50']],
                    'genBal'  => ['order'=>50,'type'=>'panel','key'=>'genBal', 'classes'=>['block50']]]]],
            'panels' => [
                'genStat' => ['label'=>lang('status'),         'type'=>'fields','keys'=>['status']], // ,'terms'
                'genHist' => ['label'=>lang('history'),        'type'=>'fields','keys'=>['inv_orders','open_quotes','open_orders','unpaid_inv','unpaid_crd']],
                'genBal'  => ['label'=>$new_data['terms_lang'],'type'=>'fields','keys'=>['current','late30','late60','late90','late120','late121','total']]],
            'fields'=> $this->getStatusFields($contact, $new_data)];
        $notes = dbMetaGet(0, 'notes', 'contacts', $cID);
        if (!empty($notes['value'])) {
            $data['fields']['notes'] = ['order'=>15,'html'=>str_replace("\n", "<br />", $notes['value']),'attr'=>['type'=>'raw']];
            $data['panels']['genStat']['keys'][] = 'notes';
        }
        $GLOBALS['aging'] = $new_data;
        $layout = array_replace_recursive($layout, $data);
    }

    private function getStatusFields($contact, $new_data)
    {
        $statusHtml = '';
        $acctActive = true;
        $statuses   = getModuleCache('contacts', 'statuses');
        foreach ($statuses as $stat) { if ($stat['id']==$contact['inactive'] && !empty($stat['id'])) { // only for non-active
            $acctActive = false;
            $statusHtml.= '<p style="font-weight:bold;text-align:center;" class="row-'.$stat['color'].'">'.$stat['text'].'</p>';
        } }
        // set the customer/vendor status in order of importance
        if ($new_data['past_due'] > 0)                         { $statusBg='yellow'; $statusMsg=$this->lang['msg_contact_status_past_due']; }
        elseif ($new_data['total'] > $new_data['credit_limit']){ $statusBg='yellow'; $statusMsg=$this->lang['msg_contact_status_over_limit']; }
        else                                                   { $statusBg='green';  $statusMsg=$this->lang['msg_contact_status_good']; }
        if ($acctActive) { $statusHtml.= '<p style="font-weight:bold;text-align:center;background-color:'.$statusBg.'">'.$statusMsg."</p>"; }
        $fields = [
            'status'     => ['order'=>10,'html'=>$statusHtml,     'break'=>false,'attr'=>['type'=>'raw']],
            'current'    => ['order'=>20,'label'=>'Current',      'break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_0']]],
            'late30'     => ['order'=>22,'label'=>'Late 0-30',    'break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_30']]],
            'late60'     => ['order'=>24,'label'=>'Late 31-60',   'break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_60']]],
            'late90'     => ['order'=>26,'label'=>'Late 61-90',   'break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_90']]],
            'late120'    => ['order'=>28,'label'=>'Late 91-120',  'break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_120']]],
            'late121'    => ['order'=>30,'label'=>'Late Over 120','break'=>true,'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['balance_121']]],
            'total'      => ['order'=>32,'label'=>lang('total'),'attr'=>['type'=>'currency','readonly'=>'readonly','value'=>$new_data['total']]],
            'inv_orders' => ['order'=>10,'label'=>$this->lang['status_orders_invoice'],'break'=>true,'html'=>lang('none')."<br />",'attr'=>['type'=>'raw']],
            'open_quotes'=> ['order'=>20,'label'=>$this->lang['status_open_j9'], 'break'=>true,'html'=>lang('none')."<br />",'attr'=>['type'=>'raw']],
            'open_orders'=> ['order'=>30,'label'=>$this->lang['status_open_j10'],'break'=>true,'html'=>lang('none')."<br />",'attr'=>['type'=>'raw']],
            'unpaid_inv' => ['order'=>40,'label'=>$this->lang['status_open_j12'],'break'=>true,'html'=>lang('none')."<br />",'attr'=>['type'=>'raw']],
            'unpaid_crd' => ['order'=>50,'label'=>$this->lang['status_open_j13'],'html'=>lang('none')."<br />",'attr'=>['type'=>'raw']]];
        if ($contact['ctype_v']=='1') { $idx='vendors';   $jQuote=3; $jOrder= 4; $jSale= 6; $jRtn= 7; }
        else                          { $idx='customers'; $jQuote=9; $jOrder=10; $jSale=12; $jRtn=13; }
        if (!empty($new_data['inv_orders']) && sizeof($new_data['inv_orders']) >1) { $fields['inv_orders'] = array_merge($fields['inv_orders'], ['values'=>$this->getStatusOpen($new_data, 'inv_orders'),
            'options'=>['width'=>300],'attr'=>['type'=>'select'],'events'=>['onChange'=>"jqBiz(this).combogrid('destroy'); bizWindowClose('winStatus'); journalEdit($jSale, 0, 0, 'inv', '', newVal);"]]); }
        if (!empty($new_data['open_quotes'])&& sizeof($new_data['open_quotes'])>1) { $fields['open_quotes']= array_merge($fields['open_quotes'],['values'=>$this->getStatusOpen($new_data, 'open_quotes'),
            'options'=>['width'=>300],'attr'=>['type'=>'select'],'events'=>['onChange'=>"jqBiz(this).combogrid('destroy'); bizWindowClose('winStatus'); journalEdit($jQuote, newVal);"]]); }
        if (!empty($new_data['open_orders'])&& sizeof($new_data['open_orders'])>1) { $fields['open_orders']= array_merge($fields['open_orders'],['values'=>$this->getStatusOpen($new_data, 'open_orders'),
             'options'=>['width'=>300],'attr'=>['type'=>'select'],'events'=>['onChange'=>"jqBiz(this).combogrid('destroy'); bizWindowClose('winStatus'); journalEdit($jOrder, newVal);"]]); }
        if (!empty($new_data['unpaid_inv']) && sizeof($new_data['unpaid_inv']) >1) { $fields['unpaid_inv'] = array_merge($fields['unpaid_inv'], ['values'=>$this->getStatusOpen($new_data, 'unpaid_inv'),
            'options'=>['width'=>300],'attr'=>['type'=>'select'], 'events'=>['onChange'=>"jqBiz(this).combogrid('destroy'); bizWindowClose('winStatus'); journalEdit($jSale, newVal);"]]); }
        if (!empty($new_data['unpaid_crd']) && sizeof($new_data['unpaid_crd']) >1) { $fields['unpaid_crd'] = array_merge($fields['unpaid_crd'], ['values'=>$this->getStatusOpen($new_data, 'unpaid_crd'),
            'options'=>['width'=>300],'attr'=>['type'=>'select'], 'events'=>['onChange'=>"jqBiz(this).combogrid('destroy'); bizWindowClose('winStatus'); journalEdit($jRtn, newVal);"]]); }
        return $fields;
    }

    /**
     * generates the pull down for aging data
     * @param array $data - aging data
     * @param string $key - key to pull list from
     */
    private function getStatusOpen($data, $key)
    {
        $output = [['id'=>0, 'text'=>lang('select')]];
        if (empty($data[$key])) { return $output; }
        foreach ($data[$key] as $rID) {
            if ($data['main']["rID_$rID"]['bal'] == 0) { continue; }
            $output[] = ['id'=>$rID, 'text'=>$data['main']["rID_$rID"]['text']];
        }
        return $output;
    }

    /**
     * This method formats a db search field to the proper db syntax.
     * @param string $index - Database field name to search
     * @param array $values - full db path to field, i.e. BIZUNO_DB_PREFIX.journal_main.field_name
     * @param string $type -  type of field to properly create SQL syntax
     * @param string $defSel - Default selection value if not set through $_POST
     * @param string $defMin - Default minimum/equal value if not set through $_POST
     * @param string $defMax - Default maximum value if not set through $_POST
     * @return SQL formatted syntax to be used in search query
     */
    private function searchCriteriaSQL($index, $values=[], $type='text', $defSel='all', $defMin='', $defMax='')
    {
        if (!is_array($values)) { $values = [$values]; }
        $sel = clean($index, ['format'=>'cmd', 'default'=>$defSel], 'post');
        $min = clean($index.'Min', ['format'=>$type, 'default'=>$defMin], 'post');
        $max = clean($index.'Max', ['format'=>$type, 'default'=>$defMax], 'post');
        $sql = [];
        switch ($sel) {
            default:
            case 'all':  break;
            case 'band': foreach ($values as $field) { $sql[] = "($field >= '$min'" . ($max ? " AND $field <= '$max')" : ")"); } break;
            case 'eq':   foreach ($values as $field) { $sql[] = "$field  = '$min'"; }     break;
            case 'not':  foreach ($values as $field) { $sql[] = "$field <> '$min'"; }     break;
            case 'inc':  foreach ($values as $field) { $sql[] = "$field LIKE '%$min%'"; } break;
        }
        msgDebug("\nFinished with index $index and defSel = $defSel and defMin = $defMin and defMax = $defMax and returning sql = ".implode(' AND ', $sql));
        $this->defaults[$index] = "$sel:$min:$max";
        return sizeof($sql) > 1 ? "(".implode(' OR ', $sql).")" : array_shift($sql);
    }

    /**
     * Grid manager structure for all PhreeBooks journals
     * @param string $name - grid HTML ID
     * @param integer $security - Security level to set tool bar and access permissions
     * @return array - Data structure ready to render
     */
    public function dgPhreeBooks($name, $security=0)
    {
        $this->managerSettings();
        msgDebug("\nPhreeBooks Settings = ".print_r($this->defaults, true));
        $dateRange  = dbSqlDates($this->defaults['period']);
        $sqlPeriod  = $dateRange['sql']; // BIZUNO_DB_PREFIX."journal_main.period={$this->defaults['period']}";
        $formID     = explode(':', getDefaultFormID($this->journalID));
        $formGroup  = $formID[0].':jjrnlTBD';
        $jHidden    = true;
        $jID_values = [];
        $valid_jIDs = [$this->journalID];
        $jrnl_sql   = BIZUNO_DB_PREFIX."journal_main.journal_id={$this->journalID}";
        $jID_statuses= [['id'=>'a','text'=>lang('all')],['id'=>'0','text'=>lang('open')],['id'=>'1','text'=>lang('closed')]];
        $jrnl_status= '';
        $sec3_09    = $sec4_10 = $sec6_12 = $sec7_13 = 0;
        switch ($this->journalID) {
            case  0: $valid_jIDs = []; break; // search
            case  3:
            case  4:
            case  6:
            case  7:
                $jHidden = false;
                switch ($this->defaults['jID']) {
                    default:$valid_jIDs = [3,4,6,7]; break;
                    case 3: $valid_jIDs = [3]; break;
                    case 4: $valid_jIDs = [4]; break;
                    case 6: $valid_jIDs = [6]; break;
                    case 7: $valid_jIDs = [7]; break;
                }

                $jID_values = [['id'=>'a','text'=>lang('all')]];
                $sec3_09 = getUserCache('role', 'security', 'j3_mgr');
                $sec4_10 = getUserCache('role', 'security', 'j4_mgr');
                $sec6_12 = getUserCache('role', 'security', 'j6_mgr');
                $sec7_13 = getUserCache('role', 'security', 'j7_mgr');
                if ($sec3_09) { $jID_values[] = ['id'=>'3','text'=>lang('journal_id_3')]; }
                if ($sec4_10) { $jID_values[] = ['id'=>'4','text'=>lang('journal_id_4')]; }
                if ($sec6_12) { $jID_values[] = ['id'=>'6','text'=>lang('journal_id_6')]; }
                if ($sec7_13) { $jID_values[] = ['id'=>'7','text'=>lang('journal_id_7')]; }
                $jID_statuses[] = ['id'=>'w','text'=>lang('confirmed_waiting')];
                switch ($this->defaults['status']) {
                    case '0': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.closed='0'";  break;
                    case '1': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.closed='1'";  break;
                    case 'w': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.waiting='1'"; break;
                }
                break;
            case  9:
            case 10:
            case 12:
            case 13:
                $jHidden = false;
                switch ($this->defaults['jID']) {
                    default: $valid_jIDs = [9,10,12,13]; break;
                    case  9: $valid_jIDs = [9];  break;
                    case 10: $valid_jIDs = [10]; break;
                    case 12: $valid_jIDs = [12]; break;
                    case 13: $valid_jIDs = [13]; break;
                }
                $jID_values = [['id'=>'a','text'=>lang('all')]];
                $sec3_09 = getUserCache('role', 'security', 'j9_mgr');
                $sec4_10 = getUserCache('role', 'security', 'j10_mgr');
                $sec6_12 = getUserCache('role', 'security', 'j12_mgr');
                $sec7_13 = getUserCache('role', 'security', 'j13_mgr');
                if ($sec3_09) { $jID_values[] = ['id'=> '9','text'=>lang('journal_id_9')];  }
                if ($sec4_10) { $jID_values[] = ['id'=>'10','text'=>lang('journal_id_10')]; }
                if ($sec6_12) { $jID_values[] = ['id'=>'12','text'=>lang('journal_id_12')]; }
                if ($sec7_13) { $jID_values[] = ['id'=>'13','text'=>lang('journal_id_13')]; }
                $jID_statuses[] = ['id'=>'w','text'=>lang('confirmed_unshipped')];
                switch ($this->defaults['status']) {
                    case '0': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.closed='0'";  break;
                    case '1': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.closed='1'";  break;
                    case 'w': $jrnl_status = BIZUNO_DB_PREFIX."journal_main.waiting='1'"; break;
                    default:  break;
                }
                break;
            default:
        }
        $jrnl_sql = $this->setAllowedJournals($valid_jIDs);
        $data  = ['id'=>$name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'=> ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=phreebooks/main/managerRows&jID=$this->journalID&type=$this->type",
                'xtraField'=> [['key'=>'jrnlTBD','value'=>"journal_id"],['key'=>'cIDTBD','value'=>"contact_id_b"]]],
            'events' => ['onDblClickRow'=>"function(idx, data){ journalEdit(data.journal_id, data.id); }"],
            'source' => [
                'tables' => ['journal_main'=>['table'=>BIZUNO_DB_PREFIX.'journal_main']],
                'search' => [
                    BIZUNO_DB_PREFIX.'journal_main.description',
                    BIZUNO_DB_PREFIX.'journal_main.primary_name_b',
                    BIZUNO_DB_PREFIX.'journal_main.primary_name_s',
                    BIZUNO_DB_PREFIX.'journal_main.postal_code_b',
                    BIZUNO_DB_PREFIX.'journal_main.postal_code_s',
                    BIZUNO_DB_PREFIX.'journal_main.invoice_num',
                    BIZUNO_DB_PREFIX.'journal_main.purch_order_id',
                    BIZUNO_DB_PREFIX.'journal_main.total_amount'],
                'actions' => [
                    'newJournal'=>['order'=>10,'icon'=>'add',    'events'=>['onClick'=>"jsonAction('phreebooks/main/getJournalEdit&jID='+$this->journalID);"]],
                    'clrSearch' =>['order'=>50,'icon'=>'refresh','events'=>['onClick'=>"hrefClick('phreebooks/main/manager&mgr=1&jID=$this->journalID');"]]],
                'filters' => [
                    'period' => ['order'=>10,'break'=>true,'options'=>['width'=>300],'sql'=>$sqlPeriod,
                        'label'=>lang('period'), 'values'=>viewKeyDropdown(localeDates(true, true, true, true, true)),'attr'=>['type'=>'select','value'=>$this->defaults['period']]],
                    'jID'    => ['order'=>20,'break'=>true,'sql'=>$jrnl_sql, 'hidden'=>$jHidden,
                        'label'=>lang('journal_id'), 'values'=>$jID_values,'attr'=>['type'=>'select','value'=>$this->defaults['jID']]],
                    'status' => ['order'=>30,'break'=>true,'sql'=>$jrnl_status,'hidden'=>$jHidden,
                        'label'=>lang('status'), 'values'=>$jID_statuses,'attr'=>['type'=>'select','value'=>$this->defaults['status']]],
                    'search' => ['order'=>90,'attr'=>['value'=>$this->defaults['search']]]],
                'sort' => ['s0'=> ['order'=>10,'field'=>("{$this->defaults['sort']} {$this->defaults['order']}, ".BIZUNO_DB_PREFIX."journal_main.id DESC")]]],
            'columns' => [
                'id'           => ['order'=>1, 'field'=>'DISTINCT '.BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]],
                'contact_id_b' => ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.contact_id_b',  'attr'=>['hidden'=>true]],
                'rep_id'       => ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.rep_id',        'attr'=>['hidden'=>true]],
                'journal_id'   => ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.journal_id',    'attr'=>['hidden'=>true]],
                'currency'     => ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.currency',      'attr'=>['hidden'=>true]],
                'currency_rate'=> ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.currency_rate', 'attr'=>['hidden'=>true]],
                'attach'       => ['order'=>1, 'field'=>BIZUNO_DB_PREFIX.'journal_main.attach',        'attr'=>['hidden'=>true]],
                'action' => ['order'=>0, 'label'=>lang('action'), // leave order BEFORE id so it stays first on view, id need to go first in sql as DISTINCT
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'print'      => ['order'=>10,'icon'=>'print',
                            'events' => ['onClick'=>"var jID=jqBiz('#journal_id').val(); winOpen('phreeformOpen', 'phreeform/render/open&group={$formGroup}&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]],
                        'edit'       => ['order'=>20,'icon'=>'edit',   'label'=>lang('edit'),
                            'events' => ['onClick' => "journalEdit(jrnlTBD, idTBD);"]],
                        'toggle'     => ['order'=>40,'icon'=>'toggle', 'label'=>lang('toggle_status'),'hidden'=>$sec4_10>2?false:true,
                            'events' => ['onClick' => "jsonAction('phreebooks/main/toggleWaiting&jID=jrnlTBD', idTBD);"],
                            'display'=> "row.journal_id=='4' || row.journal_id=='10'"],
                        'toggle_j12' => ['order'=>40,'icon'=>'toggle', 'label'=>lang('toggle_status'),'hidden'=>$sec6_12>3?false:true,
                            'events' => ['onClick' => "jsonAction('phreebooks/main/toggleWaiting&jID=jrnlTBD', idTBD);"],
                            'display'=> "row.journal_id=='12'"],
                        'xAutoAssy'  => ['order'=>46,'icon'=>'work','label'=>lang('auto_assy'),
                            'events' => ['onClick'=>"if (confirm('".$this->lang['msg_confirm_auto_assy']."')) jsonAction('$this->moduleID/autoAssy/autoAssy', idTBD);"],
                            'display'=> "(row.journal_id=='9' || row.journal_id=='10' || row.journal_id=='12')"],
                        'dates'      => ['order'=>50,'icon'=>'date',   'label'=>lang('delivery_dates'), 'hidden'=>$sec4_10>1?false:true,
                            'events' => ['onClick' => "windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"],
                            'display'=> "row.journal_id=='4' || row.journal_id=='10'"],
                        'invoice'    => ['order'=>70,'icon'=>'invoice','label'=>$this->lang['set_invoice_num'],'hidden'=>$sec6_12>2?false:true,
                            'events' => ['onClick' => "var row = jqBiz('#dgPhreeBooks').datagrid('getRows')[indexTBD]; var invNum=prompt('".$this->lang['enter_invoice_num']."', row.invoice_num); if (invNum) { jsonAction('phreebooks/main/setInvoiceNum&jID=6', idTBD, invNum); }"],
                            'display'=> "row.waiting=='1' && row.journal_id=='6'"],
                        'reference'  => ['order'=>70,'icon'=>'invoice','label'=>$this->lang['set_ref_num'],
                            'events' => ['onClick' => "var invNum=prompt('".$this->lang['enter_ref_num']."'); if (invNum) jsonAction('phreebooks/main/setReferenceNum&jID=18', idTBD, invNum);"],
                            'display'=> "row.journal_id=='18' || row.journal_id=='20'"],
                        'purchase'   => ['order'=>70,'icon'=>'purchase','label'=>lang('fill_purchase'),'hidden'=>$sec6_12>1?false:true,
                            'events' => ['onClick' => "jsonAction('phreebooks/main/getJournalFill&jID=jrnlTBD&cID=cIDTBD', idTBD);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='3' || row.journal_id=='4')"],
                        'sale'       => ['order'=>70,'icon'=>'sales',   'label'=>lang('fill_sale'),'hidden'=>$sec6_12>1?false:true,
                            'events' => ['onClick' => "jsonAction('phreebooks/main/getJournalFill&jID=jrnlTBD&cID=cIDTBD', idTBD);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='9' || row.journal_id=='10')"],
                        'vcred'      => ['order'=>80,'icon'=>'credit',   'label'=>lang('create_credit'),'hidden'=>$sec7_13>1?false:true,
                            'events' => ['onClick' => "setCrJournal(jrnlTBD, cIDTBD, idTBD);"],
                            'display'=> "(row.journal_id=='6' || row.journal_id=='12')"],
                        'payment'    => ['order'=>80,'icon'=>'payment','label'=>lang('payment'),
                            'events' => ['onClick' => "setPmtJournal(jrnlTBD, cIDTBD, idTBD);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='6' || row.journal_id=='7' || row.journal_id=='12' || row.journal_id=='13')"],
                        'attach'     => ['order'=>85,'icon'=>'attachment',
                            'events' => ['onClick' => "jsonAction('phreebooks/main/viewAttachments&jID='+jrnlTBD, idTBD);"],
                            'display'=> "row.attach=='1'"],
                        'trash'      => ['order'=>90,'icon'=>'trash','label'=>lang('delete'),'hidden'=>$security>3?false:true,
//                          'display'=> "(row.journal_id!='12' && row.journal_id!='6') || (row.journal_id=='12' && (row.closed=='0' || row.total_amount==0)) || (row.journal_id=='6' && (row.closed=='0' || row.total_amount==0))",
                            'events' => ['onClick' => "if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('phreebooks/main/delete&jID={$this->journalID}', idTBD);"]]]],
                'post_date' => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX.'journal_main.post_date', 'format'=>'date', 'label' => lang('post_date'),'attr'=>['sortable'=>true, 'resizable'=>true]],
                'invoice_num' => ['order'=>20, 'field'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num', 'label' => lang('invoice_num', $this->journalID),'attr'=>['sortable'=>true, 'resizable'=>true]],
                'so_po_ref_id' => ['order'=>25, 'field'=>BIZUNO_DB_PREFIX.'journal_main.so_po_ref_id','format'=>'storeID', 'label' => lang('so_po_ref_id', $this->journalID),
                    'attr'  => ['width'=>120, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($this->journalID, [15]) ? false : true]],
                'purch_order_id' => ['order'=>30, 'field'=>BIZUNO_DB_PREFIX.'journal_main.purch_order_id', 'label' => lang('purch_order_id', $this->journalID),
                    'attr'  => ['width'=>120, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($this->journalID, [2,14,15,16,17,18,20,22]) ? true : false]],
                'description' => ['order'=>40, 'field'=>BIZUNO_DB_PREFIX.'journal_main.description', 'label' => lang('description', $this->journalID),
                    'attr'  => ['width'=>240, 'sortable'=>true, 'resizable'=>true, 'hidden'=> !in_array($this->journalID, [0,2,14,15,16]) ? true : false]],
                'primary_name_b' => ['order'=>50, 'field'=>BIZUNO_DB_PREFIX.'journal_main.primary_name_b', 'label' => lang('primary_name'),
                    'attr'  => ['width'=>240, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($this->journalID, [0,2,14,15,16]) || $this->type=='e' ? true : false]],
                'email_b' => ['order'=>60, 'field'=>BIZUNO_DB_PREFIX.'journal_main.email_b', 'label' => lang('email', $this->type),
                    'attr'  => ['width'=>220, 'sortable'=>true, 'resizable'=>true, 'hidden'=>true]],
                'total_amount'=> ['order'=>70, 'field' => BIZUNO_DB_PREFIX.'journal_main.total_amount', 'label' => lang('total_amount'),
                    'events' => ['formatter' => "function(value,row,index) { return formatCurrency(value, true, row.currency, row.currency_rate); }"],
                    'attr'  => ['width'=>80, 'align'=>'right', 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($this->journalID, [14,15,16]) ? true : false]],
                'closed'    => ['order'=>90, 'field'=>BIZUNO_DB_PREFIX.'journal_main.closed', 'label' => lang('status'),
                    'attr'  => ['width'=>60, 'align'=>'center', 'resizable'=>true, 'hidden'=>in_array($this->journalID,[14,15,16])?true:false]]]];
        switch ($this->journalID) {
            case 0: // search journal
                $data['events']['onDblClickRow'] = "function(rowIndex, rowData){ winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID='+rowData.id); }";
                $data['source']['tables']['journal_item'] = ['table'=>BIZUNO_DB_PREFIX.'journal_item', 'join'=>'JOIN', 'links'=>BIZUNO_DB_PREFIX."journal_main.id=".BIZUNO_DB_PREFIX."journal_item.ref_id"];
                $data['source']['search'][] = BIZUNO_DB_PREFIX.'journal_main.id';
                $data['source']['search'][] = BIZUNO_DB_PREFIX.'journal_item.sku';
                $data['source']['search'][] = BIZUNO_DB_PREFIX.'journal_item.description';
                unset($data['source']['actions']['newJournal']);
                unset($data['columns']['id']['attr']['hidden']);
                $data['columns']['action']['actions']['edit']['events']['onClick'] = "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');";
                $data['columns']['journal_id'] = ['order'=>15, 'field'=>BIZUNO_DB_PREFIX.'journal_main.journal_id','label'=>pullTableLabel('journal_main', 'journal_id'),
                    'events'=>['formatter'=>"function(value) { return jrnlTitles['j'+value]; }"], 'attr'=>['width'=>80, 'resizable'=>true]];
                $journalID = clean('journalID', 'integer', 'post');
                $jVals = $this->selJournals(); // needs to be here for generating $this->blocked_journals
                if     ($journalID)              { $sql = BIZUNO_DB_PREFIX."journal_main.journal_id=$journalID"; }
                elseif ($this->blocked_journals) { $sql = BIZUNO_DB_PREFIX."journal_main.journal_id NOT IN ($this->blocked_journals)"; }
                else                             { $sql = ''; }
                $data['source']['filters']['journalID']= ['order'=>55,'break'=>true,'sql'=>$sql, 'label'=>pullTableLabel('journal_main', 'journal_id'),'values'=>$jVals,'attr'=>['type'=>'select','value'=>$journalID]];
                $sql1 = $this->searchCriteriaSQL('postDate', BIZUNO_DB_PREFIX.'journal_main.post_date', 'date', 'band', getModuleCache('phreebooks', 'fy', 'period_start'), getModuleCache('phreebooks', 'fy', 'period_end'));
                $temp1 = explode(':', $this->defaults['postDate']);
                if (!$temp1[0]) { $temp1[0] = 'band'; }
                $data['source']['filters']['postDate']    = ['order'=>60,'sql'=>$sql1,'label'=>pullTableLabel('journal_main', 'post_date'),'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp1[0]]];
                $data['source']['filters']['postDateMin'] = ['order'=>61,              'attr'=>['type'=>'date', 'value'=>$temp1[1]]];
                $data['source']['filters']['postDateMax'] = ['order'=>62,'break'=>true,'attr'=>['type'=>'date', 'value'=>$temp1[2]]];

                $sql2 = $this->searchCriteriaSQL('refID', [BIZUNO_DB_PREFIX.'journal_main.invoice_num', BIZUNO_DB_PREFIX.'journal_main.purch_order_id']);
                $temp2 = explode(':', $this->defaults['refID']);
                $data['source']['filters']['refID']    = ['order'=>63,'sql'=>$sql2,'label'=>lang('invoice_num'),'events'=>['onChange'=>"pbSetPrompt('refID', 'refIDMin','refIDMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp2[0]]];
                $data['source']['filters']['refIDMin'] = ['order'=>64,              'attr'=>['value'=>$temp2[1]]];
                $data['source']['filters']['refIDMax'] = ['order'=>65,'break'=>true,'attr'=>['value'=>$temp2[2]]];

                $sql3 = $this->searchCriteriaSQL('contactID', BIZUNO_DB_PREFIX.'journal_main.primary_name_b');
                $temp3 = explode(':', $this->defaults['contactID']);
                $data['source']['filters']['contactID']    = ['order'=>66,'sql'=>$sql3,'label'=>lang('primary_name'),'events'=>['onChange'=>"pbSetPrompt('contactID', 'contactIDMin','contactIDMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp3[0]]];
                $data['source']['filters']['contactIDMin'] = ['order'=>67,              'attr'=>['value'=>$temp3[1]]];
                $data['source']['filters']['contactIDMax'] = ['order'=>68,'break'=>true,'attr'=>['value'=>$temp3[2]]];

                $sql4 = $this->searchCriteriaSQL('sku', BIZUNO_DB_PREFIX.'journal_item.sku');
                $temp4 = explode(':', $this->defaults['sku']);
                $data['source']['filters']['sku']    = ['order'=>69,'sql'=>$sql4,'label'=>('sku'),'events'=>['onChange'=>"pbSetPrompt('sku', 'skuMin','skuMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp4[0]]];
                $data['source']['filters']['skuMin'] = ['order'=>70,              'attr'=>['value'=>$temp4[1]]];
                $data['source']['filters']['skuMax'] = ['order'=>71,'break'=>true,'attr'=>['value'=>$temp4[2]]];

                $sql5 = $this->searchCriteriaSQL('amount',  [BIZUNO_DB_PREFIX.'journal_main.total_amount', BIZUNO_DB_PREFIX.'journal_item.debit_amount', BIZUNO_DB_PREFIX.'journal_item.credit_amount']);
                $temp5 = explode(':', $this->defaults['amount']);
                $data['source']['filters']['amount']    = ['order'=>72,'sql'=>$sql5,'label'=>lang('amount'),'events'=>['onChange'=>"pbSetPrompt('amount', 'amountMin','amountMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp5[0]]];
                $data['source']['filters']['amountMin'] = ['order'=>73,              'attr'=>['value'=>$temp5[1]]];
                $data['source']['filters']['amountMax'] = ['order'=>74,'break'=>true,'attr'=>['value'=>$temp5[2]]];

                $sql6 = $this->searchCriteriaSQL('glAcct',  [BIZUNO_DB_PREFIX.'journal_main.gl_acct_id', BIZUNO_DB_PREFIX.'journal_item.gl_account']);
                $temp6 = explode(':', $this->defaults['glAcct']);
                $data['source']['filters']['glAcct']    = ['order'=>75,'sql'=>$sql6,'label'=>pullTableLabel('journal_main', 'gl_acct_id'),'events'=>['onChange'=>"pbSetPrompt('glAcct', 'glAcctMin','glAcctMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp6[0]]];
                $data['source']['filters']['glAcctMin'] = ['order'=>76,              'attr'=>['value'=>$temp6[1]]];
                $data['source']['filters']['glAcctMax'] = ['order'=>77,'break'=>true,'attr'=>['value'=>$temp6[2]]];

                $sql7 = $this->searchCriteriaSQL('rID', BIZUNO_DB_PREFIX.'journal_main.id');
                $temp7 = explode(':', $this->defaults['rID']);
                $data['source']['filters']['rID']    = ['order'=>78,'sql'=>$sql7,'label'=>lang('users_admin_id'),'events'=>['onChange'=>"pbSetPrompt('rID', 'rIDMin','rIDMax')"],'values'=>selChoices(),'attr'=>['type'=>'select','value'=>$temp7[0]]];
                $data['source']['filters']['rIDMin'] = ['order'=>79,              'attr'=>['value'=>$temp7[1]]];
                $data['source']['filters']['rIDMax'] = ['order'=>80,'break'=>true,'attr'=>['value'=>$temp7[2]]];
                unset($data['source']['filters']['period']);
                break;
            case 2:
//              unset($data['columns']['closed']);
                $data['columns']['closed']['events']['formatter'] = "function(value,row,index) { return value=='1' ? '".jsLang('closed')."' : ''; }";
                break;
            case  3:
            case  4:
            case  6:
            case  7:
                if (getUserCache('role', 'security', 'j20_mgr') < 2) { unset($data['columns']['action']['actions']['payment']); }
                $data['columns']['invoice_num']['events'] = ['styler'=>"function(value,row,index) {
                        if      (row.journal_id=='3') { return {style:'background-color:lightblue'}; }
                        else if (row.journal_id=='4') { return {style:'background-color:orange'}; }
                        else if (row.journal_id=='6') { return {style:'background-color:lightgreen'}; }
                        else if (row.journal_id=='7') { return {style:'background-color:pink'}; }
                    }"];
                $data['columns']['closed']['events']['formatter'] = "function(value,row,index){
                        if      (row.journal_id=='3') { return value=='1' ? '".jsLang('closed')."' : ''; }
                        else if (row.journal_id=='4') { return value=='1' ? '".jsLang('closed')."' : ''; }
                        else if (row.journal_id=='6') { return value=='1' ? '".jsLang('paid')."' : ''; }
                        else if (row.journal_id=='7') { return value=='1' ? '".jsLang('paid')."' : ''; }
                    }";
                $data['columns']['closed']['events']['styler'] = "function(value,row,index) {
                        if      (row.journal_id=='4' && row.waiting==1) { return {style:'background-color:yellowgreen'}; }
                        else if (row.journal_id=='6' && row.waiting==1) { return {class:'journal-waiting'}; }
                    }";
                $data['footnotes'] = ['status'=>lang('journal_id').':
                        <span style="background-color:lightgreen">'.lang('journal_id_6').'</span>
                        <span style="background-color:orange">'    .lang('journal_id_4').'</span>
                        <span style="background-color:lightblue">' .lang('journal_id_3').'</span>
                        <span style="background-color:pink">'      .lang('journal_id_7').'</span>',
                    'jType'=>'<br />'.lang('status').':
                        <span style="background-color:yellowgreen">'.lang('confirmed').'</span>
                        <span class="journal-waiting">'.lang('journal_main_waiting').'</span>'];
                break;
            case  9:
            case 10:
            case 12:
            case 13:
                if (getUserCache('role', 'security', 'j18_mgr') < 2) { unset($data['columns']['action']['actions']['payment']); }
                $data['columns']['invoice_num']['events'] = ['styler'=>"function(value,row,index) {
                        if      (row.journal_id== '9') { return {style:'background-color:lightblue'}; }
                        else if (row.journal_id=='10') { return {style:'background-color:orange'}; }
                        else if (row.journal_id=='12') { return {style:'background-color:lightgreen'}; }
                        else if (row.journal_id=='13') { return {style:'background-color:pink'}; }
                    }"];
                $data['columns']['closed']['events']['formatter'] = "function(value,row,index){
                        if      (row.journal_id== '9') { return value=='1' ? '".jsLang('closed')."' : ''; }
                        else if (row.journal_id=='10') { return value=='1' ? '".jsLang('closed')."' : ''; }
                        else if (row.journal_id=='12') { return value=='1' ? '".jsLang('paid')  ."' : ''; }
                        else if (row.journal_id=='13') { return value=='1' ? '".jsLang('paid')  ."' : ''; }
                    }";
                $data['columns']['closed']['events']['styler'] = "function(value,row,index) {
                    if      (row.journal_id=='10' && row.waiting==1) { return {style:'background-color:yellowgreen'}; }
                    else if (row.journal_id=='12' && row.waiting==1) { return {class:'journal-waiting'}; }
                    }";
                $data['footnotes'] = ['status'=>lang('journal_id').':
                        <span style="background-color:lightgreen">'.lang('journal_id_12').'</span>
                        <span style="background-color:orange">'    .lang('journal_id_10').'</span>
                        <span style="background-color:lightblue">' .lang('journal_id_9') .'</span>
                        <span style="background-color:pink">'      .lang('journal_id_13').'</span>',
                    'jType'=>'<br />'.lang('status').':
                        <span style="background-color:yellowgreen">'.lang('confirmed').'</span>
                        <span class="journal-waiting">'.lang('unshipped').'</span>'];
                break;
            case 14:
                unset($data['columns']['closed']);
                break;
            case 17:
            case 18:
            case 20:
            case 22:
                $data['columns']['closed']['events']['formatter'] = "function(value) { return value=='1'?'".jsLang('reconciled')."':''; }";
                break;
        }
        if (getUserCache('profile', 'restrict_user', false, 0)) { // see if user restrictions are in place
            $uID = getUserCache('profile', 'userID', false, 0);
            $data['source']['filters']['restrict_user'] = ['order'=>99,'hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_main.rep_id='$uID'"];
        }
        $this->dgPhreeBooksMobile($data);

        // CRM, RMA, DropShip
        if (in_array($this->journalID, [3,4,6,7,9,10,12,13])) {
            $type = in_array($this->journalID, [3,4,6,7]) ? 'v' : 'c';
            $reps= viewRoleDropdown();
            array_shift($reps);
            array_unshift($reps, ['id'=>0, 'text'=>lang('all')]);
            $data['source']['filters']['rep_id'] = ['order'=>15,'break'=>true, 'label'=>lang('rep_id', $type), 'values'=>$reps,'attr'=>['type'=>'select']];
//          $uID = getUserCache('profile', 'userID', false, 0);
            $rep = clean('rep_id', 'integer', 'post');
            msgDebug("\nRep after processing = $rep");
            if (!empty($rep)) {
                $data['source']['filters']['rep_id']['sql'] = BIZUNO_DB_PREFIX."journal_main.rep_id='$rep'";
            }
        }
        if (validateAccess('j4_mgr', 2, false)) { // Drop Ship action
            $data['columns']['action']['actions']['xDropShip'] = ['order'=>45,'icon'=>'order','label'=>lang('create_po'),
                'events' => ['onClick'=>"if (confirm('".lang('msg_confirm_create_po')."')) jsonAction('$this->moduleID/dropShip/savePO', idTBD);"],
                'display'=> "row.closed=='0' && (row.journal_id=='9' || row.journal_id=='10' || row.journal_id=='12')"];
        }
        if (validateAccess('j12_mgr', 2, false)) { // Create RMA action
            $data['columns']['action']['actions'][$this->moduleID] = ['order'=>85,'icon'=>'return',
                'label'  =>$this->lang['create_return'],'events'=>['onClick'=>"jsonAction('$this->moduleID/returns/getInfoForm', idTBD);"],
                'display'=>"row.journal_id=='12' && row.waiting=='0'"];
        }
        // Stores
        $storeID= clean('store', 'integer', 'post'); // store 0 is HQ so do not use default in clean
        if ($this->journalID==15) { // for store transfers show all transfer in the filter
            $storeID = -1;
        } elseif (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $data['columns']['store_id'] = ['order'=>25, 'field' => BIZUNO_DB_PREFIX.'journal_main.store_id','format'=>'storeID',
            'label' => pullTableLabel('contacts', 'store_id'),'attr'=>['sortable'=>false,'resizable'=>true]];
        $data['source']['filters']['store'] = ['order'=>15,'break'=>true,'label'=>lang('ctype_b'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        msgDebug("\njournal_id = $this->journalID and storeID = $storeID");
        switch ($storeID) {
            case -1: $data['source']['filters']['store']['sql'] = ''; break;
            default: $data['source']['filters']['store']['sql'] = BIZUNO_DB_PREFIX."journal_main.store_id=$storeID"; break;
        }
        // EDI
// @TODO - THIS NEEDS TO BE MADE INTO A META VALUE FOR SPEED
        if (in_array($this->journalID, [9,10,12,13])) {
            $data['columns']['action']['actions']['ediConfirm'] = ['order'=>5,'icon'=>'apply',
                'label'=>$this->lang['edi_confirm_po'],'events'=>['onClick'=>"jsonAction('$this->moduleID/ediAPI/ediTransmit', idTBD, '855');"],
                'display'=>"row.journal_id=='10' && row.contact_id_b==206"]; // EDI 855 - PO confirmation
            $data['columns']['action']['actions']['ediInvoice'] = ['order'=>6,'icon'=>'mimeHtml',
                'label'=>$this->lang['edi_send_invoice'],'events'=>['onClick'=>"jsonAction('$this->moduleID/ediAPI/ediTransmit', idTBD, '810');"],
                'display'=>"row.journal_id=='12' && row.contact_id_b==206"]; // EDI 810 - Invoice
            $data['columns']['action']['actions']['ediTrack']   = ['order'=>7,'icon'=>'truck',
                'label'=>$this->lang['edi_send_tracking'],'events'=>['onClick'=>"jsonAction('$this->moduleID/ediAPI/ediTransmit', idTBD, '856');"],
                'display'=>"row.journal_id=='12' && row.contact_id_b==206"]; // EDI 856 - Shipment confirmation with tracking
        }
/* PROBABLY DUPLICATES, FROM MANAGERROWS
        // Stores
        $jID    = clean('jID', 'integer', 'get');
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $storeID= clean('store', 'integer', 'post'); // store 0 is HQ so do not use default in clean
        if ($jID==15) { // for store transfers show all transfer in the filter
            $storeID = -1;
        } elseif (!empty($this->restrict)) {
            $storeID = $layout['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        $layout['datagrid']['manager']['columns']['store_id'] = ['order'=>25, 'field' => BIZUNO_DB_PREFIX.'journal_main.store_id','format'=>'storeID',
            'label' => pullTableLabel('contacts', 'store_id'),'attr'=>['sortable'=>false,'resizable'=>true]];
        $layout['datagrid']['manager']['source']['filters']['store'] = ['order'=>15,'break'=>true,'label'=>lang('ctype_b'),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$storeID]];
        msgDebug("\njournal_id = $jID and storeID = $storeID");
        switch ($storeID) {
            case -1: $layout['datagrid']['manager']['source']['filters']['store']['sql'] = ''; break;
            default: $layout['datagrid']['manager']['source']['filters']['store']['sql'] = BIZUNO_DB_PREFIX."journal_main.store_id=$storeID"; break;
        }
 */
        return $data;
    }

    /**
     * Customize grid for mobile devices
     * @param array $data - Structure coming in
     */
    private function dgPhreeBooksMobile(&$data=[])
    {
        if ($GLOBALS['myDevice']=='mobile') {
            $data['columns']['post_date']['label']              = lang('date');
            $data['columns']['total_amount']['label']           = lang('total');
            $data['columns']['post_date']['format']             = 'dateNoY';
            $data['columns']['so_po_ref_id']['attr']['hidden']  = true;
            $data['columns']['purch_order_id']['attr']['hidden']= true;
            $data['columns']['closed']['attr']['hidden']        = true;
        }
    }

    /**
     * This method builds the appropriate Save menu choices depending on the journal and state of the order
     * @param integer $security - users security to set visibility
     * @return array - structure for save menu
     */
    private function renderMenuSave($security=0)
    {
        msgDebug("\nbuilding renderMenuSave, security=$security, id=$this->rID");
        $type = false;
        $data = [];
        if ($security < 2) { return $data; } // Read-only, no operations allowed
        switch ($this->journalID) {
            case 2:
                $data = [
                    'optSaveAs' => ['order'=>40,'label'=>lang('save_as'),'child'=>  [
                        'saveAsNew' => ['order'=>10,'label'=>lang('new'),'security'=>3,'events'=>['onClick'=>"saveAction('saveAs','2');"]]]]];
                break;
            case  3:
            case  4:
            case  6: $type = 'v';
            case  9:
            case 10:
            case 12: if (!$type) { $type = 'c'; }
                $data = [
                    'optPrint'   => ['order'=>10,'icon'=>'print','label'=>lang('save_print'),'security'=>3,
                        'hidden' => !in_array($this->journalID, [6]) && $security>1?false:true,
                        'events' => ['onClick'=>"jqBiz('#xChild').val('print'); jqBiz('#frmJournal').submit();"]],
                    'optPrepay'  => ['order'=>20,'icon'=>'payment','label'=>lang('save_prepay'),'security'=>3,
                        'hidden' =>  in_array($this->journalID, [4,10]) && $security>1?false:true,
                        'events' => ['onClick'=>"jqBiz('#xAction').val('prepay'); jqBiz('#frmJournal').submit();"]],
                    'optPayment' => ['order'=>30,'icon'=>'payment','label'=>lang('save_payment'),'security'=>3,
                        'hidden' =>  in_array($this->journalID, [6,12]) && $security>1?false:true,
                        'events' => ['onClick'=>"jqBiz('#xAction').val('payment'); jqBiz('#frmJournal').submit();"]],
                    'optFill'    => ['order'=>40,'icon'=>'fill','label'=>lang('save_fill'),'security'=>2,
                        'hidden' =>  in_array($this->journalID, [4,10]) && $security>1?false:true,
                        'events' => ['onClick'=>"jqBiz('#xAction').val('invoice'); jqBiz('#frmJournal').submit();"]],
                    'optSaveAs'  => ['order'=>50,'label'=>lang('save_as'),'child'=>  [
                        'saveAsQuote'=> ['order'=>10,'icon'=>'quote','label'=>lang('journal_id', $type=='v'?3: 9),'security'=>3,
//                            'disabled'=> !in_array($this->journalID, array(3,9)) ? false : true,
                            'events'  => ['onClick'=>"saveAction('saveAs','".($type=='v'?3: 9)."');"]],
                        'saveAsSO'   => ['order'=>20,'icon'=>'order','label'=>lang('journal_id', $type=='v'?4:10),'security'=>3,
//                            'disabled'=> !in_array($this->journalID, array(4,10)) ? false : true,
                            'events'  => ['onClick'=>"saveAction('saveAs','".($type=='v'?4:10)."');"]],
                        'saveAsInv'  => ['order'=>30,'icon'=>'sales','label'=>lang('journal_id', $type=='v'?6:12),'security'=>3,
//                            'disabled'=> !in_array($this->journalID, array(6,12)) ? false : true,
                            'events'  => ['onClick'=>"saveAction('saveAs','".($type=='v'?6:12)."');"]]]],
                    'optMoveTo'  => ['order'=>60,'label'=>lang('move_to'),'disabled'=>$this->rID?false:true,'child'=>  [
                        'MoveToQuote'=> ['order'=>10,'icon'=>'quote','label'=>lang('journal_id', $type=='v'?3: 9),
                            'disabled'=> !in_array($this->journalID, [3,9]) ? false : true,'security'=>3,
                            'events'  => ['onClick'=>"saveAction('moveTo','".($type=='v'?3: 9)."');"]],
                        'MoveToSO'   => ['order'=>20,'icon'=>'order','label'=>lang('journal_id', $type=='v'?4:10),
                            'disabled'=> !in_array($this->journalID, [4,10]) ? false : true,'security'=>3,
                            'events'  => ['onClick'=>"saveAction('moveTo','".($type=='v'?4:10)."');"]],
                        'MoveToInv'  => ['order'=>30,'icon'=>$type=='v'?'purchase':'sales','label'=>lang('journal_id', $type=='v'?6:12),
                            'disabled'=> !in_array($this->journalID, [6,12]) ? false : true,'security'=>3,
                            'events'  => ['onClick'=>"saveAction('moveTo','".($type=='v'?6:12)."');"]]]]];
                break;
            case 20:
            case 22:
                $data = ['optPrint'=>['order'=>10,'label'=>lang('save_print'),'icon'=>'print','security'=>3,
                    'hidden'=>$security>1?false:true,'events'=>['onClick'=>"jqBiz('#xChild').val('print'); jqBiz('#frmJournal').submit();"]]];
            break;
            default:
        }
        return $data;
    }

    /**
     * Retrieves list of attachments for a given
     * @param string $srcField - path to PhreeBooks uploads folder
     * @param integer $rID - record id used to create filename to search for attachments
     * @param integer $refID - record id of reference journal entry (SO or PO)
     * @return null, saves files on success, message if fails
     */
    private function getAttachments($srcField, $rID, $refID=0)
    {
        global $io;
        if ($io->uploadSave($srcField, getModuleCache('phreebooks', 'properties', 'attachPath', 'phreebooks')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['attach'=>'1'], 'update', "id=$rID");
        }
        if ($refID) { // if so_po_ref_id, check for attachment to copy over
            $files = glob(getModuleCache('phreebooks', 'properties', 'attachPath', 'phreebooks')."rID_{$refID}_*");
            if ($files === false || sizeof($files) == 0) { return; }
            foreach ($files as $oldFile) {
                $newFile = str_replace("rID_{$refID}_", "rID_{$rID}_", $oldFile);
                copy($oldFile, $newFile);
            }
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['attach'=>'1'], 'update', "id=$rID");
        }
    }

    /**
     * Loads the appropriate journal class to operate on
     * @param integer $jID - journal ID
     * @return object - journal object
     */
    private function getJournal($jID)
    {
        if (empty($jID)) { $jID = 12; }
        $jName = "j".substr('0'.$jID, -2);
        bizAutoLoad(BIZBOOKS_ROOT."controllers/phreebooks/journals/$jName.php", $jName);
        $fqcn = "\\bizuno\\$jName";
        return new $fqcn();
    }

    /*
     * Displays the list of journals to open in edit mode
     * @param array $layout
     * @return modified $layout
     */
    public function getJournalEdit(&$layout=[])
    {
        $jID    = clean('jID', 'integer', 'get');
        msgDebug("\nIn getJournalEdit with jID = $jID");
        if (in_array($jID, [3,4,6,7,9,10,12,13])) {
            $type   = in_array($jID, [3, 4, 6, 7]) ? 'vendors' : 'customers';
            $sType  = in_array($jID, [3, 4, 6, 7]) ? 'purch'   : 'sales';
            $menus  = dbGetRoleMenu();
            $menu   = $menus['menuBar']['child'][$type]['child'][$sType]['child'];
            $theList= sortOrder($menu);
            $rows   = [];
            foreach ($theList as $key => $child) {
                $parts= explode('_', $key);
                $jrnl = intval(substr($parts[0], 1));
                if (empty($jrnl)) { continue; } // not a journal
                $html = ['attr'=>['type'=>'a','value'=>lang($child['label'])],'classes'=>['easyui-linkbutton'],
                    'options'=>['iconCls'=>"'iconL-{$child['icon']}'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"jqBiz('#jrnlSel').window('close'); journalEdit($jrnl, 0);"]];
                $rows[] = $html;
            }
            $data = ['type'=>'popup','title'=>lang('select'),'attr'=>['id'=>'jrnlSel','width'=>300, 'height'=>310],
                'divs' => ['body'=>['order'=>50,'type'=>'list','key'=>'opts']],
                'lists'=> ['opts'=>$rows]];
        } else {
            $data = ['content'=>['action'=>'eval', 'actionData'=>"journalEdit($jID, 0);"]];
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Selects the journal to fill into, mostly to allow quotes fill into orders and sales
     * @param type $layout
     */
    public function getJournalFill(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        $cID = clean('cID', 'integer', 'get');
        $jID = clean('jID', 'integer', 'get');
        msgDebug("\nIn getJournalFill with rID = $rID, cID = $cID and jID = $jID");
        if (in_array($jID, [4,10])) { // if it's a so/po then go straight to invoice
            $jrnl = $jID==4 ? 6 : 12;
            $data = ['content'=>['action'=>'eval', 'actionData'=>"journalEdit($jrnl, 0, $cID, 'inv', '', $rID);"]];
        } else { // if it's a quote then show popup with selection between so/po and invoice
            $sType    = $jID==3 ? 'purchase' : 'sales';
            $lbl_04_10= $jID==3 ? lang('journal_id_4') : lang('journal_id_10');
            $lbl_06_12= $jID==3 ? lang('journal_id_6') : lang('journal_id_12');
            $data = ['type'=>'popup','title'=>lang('select'),'attr'=>['id'=>'jrnlSel','width'=>300,'height'=>310],
                'divs' => ['body'=>['order'=>50,'type'=>'list','key'=>'opts']],
                'lists'=> ['opts'=>[
                    'j04_10'=>['attr'=>['type'=>'a','value'=>$lbl_04_10],'classes'=>['easyui-linkbutton'],
                        'options'=>['iconCls'=>"'iconL-order'", 'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"bizWindowClose('jrnlSel'); journalEdit(".($jID==3?4:10).", $rID, $cID, 'ord');"]],
                    'j06_12'=>['attr'=>['type'=>'a','value'=>$lbl_06_12],'classes'=>['easyui-linkbutton'],
                        'options'=>['iconCls'=>"'iconL-$sType'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"bizWindowClose('jrnlSel'); journalEdit(".($jID==3?6:12).", 0, $cID, 'inv', '', $rID);"]]]]];
        }
        $layout= array_replace_recursive($layout, $data);
    }
    /**
     * Creates the structure for the delivery dates pop up for user entry
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function deliveryDates(&$layout=[])
    {
        if (!$security = validateAccess('j'.$this->journalID.'_mgr', 3)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        $items = [];
        foreach ($result as $row) {
            if ($row['gl_type'] != 'itm') { continue; }
            $items[] = ['id'=>$row['id'],'qty'=>$row['qty'],'sku'=>$row['sku'],'description'=>$row['description'],'date_1'=>$row['date_1']];
        }
        $data = ['type'=>'divHTML','divs'=>['divRate'=>['order'=>50,'type'=>'html','html'=>$this->getViewDelDates($items)]]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function getViewDelDates($items)
    {
        $delSave = ['icon'=>'save','events'=>['onClick'=>"divSubmit('phreebooks/main/deliveryDatesSave&jID=$this->journalID', 'winDelDates');"]];
        $output  = '<table style="border-collapse:collapse;width:100%;"><thead><tr class="panel-header"><th>'.lang('qty')."</th><th>".lang('sku')."</th><th>".lang('description')."</th><th>".lang('date')."</th></tr></thead><tbody>";
        foreach ($items as $row) {
            $output .= "<tr><td>".$row['qty']."</td><td>".$row['sku']."</td><td>".$row['description']."</td><td>".html5("rID_{$row['id']}",['attr'=>['type'=>'date','value'=>$row['date_1']]])."</td></tr>";
        }
        $output .= '</tbody><tfooter><tr><td colspan="4" style="text-align:right">'.html5('delSave', $delSave)."</td></tr></tfooter></table>\n";
        return $output;
    }

    /**
     * Saves the user entered delivery dates (typically for PO's and SO's
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function deliveryDatesSave(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 3)) { return; }
        $request = $_POST;
        foreach ($request as $key => $value) {
            if (strpos($key, "rID_") === 0) {
                $rID = str_replace("rID_", "", $key);
                dbWrite(BIZUNO_DB_PREFIX."journal_item", ['date_1'=>clean($value, 'date')], 'update', "id=$rID");
            }
        }
        msgAdd(lang('msg_database_write'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"bizWindowClose('winDelDates');"]]);
    }

    /**
     * Determines the balance owed for a journal order line item
     * @param type $layout
     * @return type
     */
    public function journalBalance(&$layout=[])
    {
        if (!$security = validateAccess('j20_mgr', 3)) { return; }
        $rID     = clean('rID',      'integer', 'get');
        $postDate= clean('postDate', ['format'=>'date','default'=>biz_date('Y-m-d')], 'get');
        $glAcct  = clean('glAccount',['format'=>'text','default'=>getModuleCache('phreebooks', 'settings', 'vendors', 'gl_cash')], 'get');
        $achMapID= clean('achMapID', 'alpha_num', 'get');
        if (!empty($achMapID)) { // fetch the ACH map to load the proper GL_account
            $profiles= getModuleCache('phreebooks', 'banks');
            foreach ($profiles as $profile) { if ($profile['mapID']==$achMapID) { $glAcct = $profile['gl_acct']; } }
        }
        $balance = dbGetGLBalance($glAcct, $postDate, $rID);
        $layout  = array_replace_recursive($layout, ['content'=>['balance'=>$balance, 'gl_account'=>$glAcct]]);
    }

    /**
     * Builds the list of totals from the session cache for a particular journal
     * @param integer $jID - journal ID used as index
     * @return array - list of totals in order of priority
     */
    public function loadTotals($jID)
    {
        $enabled = [];
        $methods = getMetaMethod('totals');
        foreach ($methods as $methID => $settings) {
            if ( empty($settings['status']))                      { continue; }
            if (!isset($settings['settings']['journals']))        { $settings['settings']['journals'] = []; }
            if (is_string($settings['settings']['journals']))     { $settings['settings']['journals'] = json_decode($settings['settings']['journals']); }
            if (in_array($jID, $settings['settings']['journals'])){ $enabled[$methID] = $settings['settings']['order']; }
        }
        asort($enabled);
        return array_keys($enabled);
    }

    public function setInvoiceNum(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $rID    = clean('rID',  'integer','get');
        $invNum = clean('data', 'text',   'get');
        $panel  = clean('panel','cmd',    'get');
        if (empty($rID))    { return msgAdd(lang('bad_id')); }
        if (empty($invNum)) { return msgAdd($this->lang['msg_inv_waiting']); }
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['waiting'=>'0','invoice_num'=>$invNum], 'update', "id=$rID");
        $action = !empty($panel) ? "bizPanelRefresh('$panel');" : "bizGridReload('dgPhreeBooks');";
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>$action]]);
    }

    private function setQuote2Order($rID, $cID, $structure)
    {
        $this->journalID = $this->journalID==4 ? 3 : 9; // fake out the journal ID to read the data
        $ledger = new journal($rID, $this->journalID, false, $cID, $structure, $this->action); // load the quote
        $_GET['jID'] = $this->journalID = $this->journalID==3 ? 4 : 10;
        unset($ledger->main['id'], $ledger->main['invoice_num'], $ledger->main['description']);
        $ledger->main['journal_id']   = $this->journalID;
        $ledger->main['post_date']    = biz_date();
        $ledger->main['terminal_date']= biz_date();
        $ledger->main['so_po_ref_id'] = $rID;
        foreach (array_keys($ledger->items) as $idx) { // clear all of the id's
            $ledger->items[$idx]['id'] = 0;
            $ledger->items[$idx]['ref_id'] = 0;
            $ledger->items[$idx]['post_date'] = biz_date();
        }
        $ledger->Post();
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['closed'=>'1', 'closed_date'=>biz_date()], 'update', "id=$rID"); // close the quote
        return $ledger->main['id'];
    }

    public function setReferenceNum(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd(lang('bad_id')); }
        $refNum = clean('data', 'text', 'get');
        if (empty($refNum)) { return 'Bad reference number'; } // should never happen
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['invoice_num'=>$refNum], 'update', "id=$rID");
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgPhreeBooks');"]]);
    }

    /**
     * Sets the sql criteria to limit the list of journals to search in the db
     * @param array $jIDs - list of journal ID within scope
     * @return string - sql condition for allowed journals
     */
    private function setAllowedJournals($jIDs=[])
    {
        if (sizeof($jIDs) == 0) { return ''; }
        $valids = [];
        while ($i = array_shift($jIDs)) {
            if (getUserCache('role', 'security', "j{$i}_mgr", false, 0)) { $valids[] = $i; }
        }
        if (sizeof($valids) == 0) { return BIZUNO_DB_PREFIX."journal_main.journal_id=-1"; } // return with no results
        $output = sizeof($valids) == 1 ? " = ".array_shift($valids) : " IN (".implode(',', $valids).")";
        return BIZUNO_DB_PREFIX."journal_main.journal_id".$output;
    }

    /**
     * Builds the drop down of available journals for searching
     * @return array drop down formatted list of available journals filtered by security
     */
    private function selJournals()
    {
        $blocked= [];
        $output = [['id'=>0, 'text'=>lang('all')]];
        for ($i = 1; $i < 30; $i++) {
            if (getUserCache('role', 'security', "j{$i}_mgr", false, 0)) {
                $output[] = ['id'=>$i, 'text'=>lang("journal_id_$i")];
            } else { $blocked[] = $i; }
        }
        $this->blocked_journals = sizeof($blocked) > 0 ? implode(',', $blocked) : false;
        return $output;
    }
    /**
     * Toggles the status of the contact provided
     * @param array $layout - structure coming it
     * @return modified $layout
     */
    public function toggleStatus(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 3)) { return; }
        $cID    = clean('cID', 'integer', 'get');
        $jID    = clean('jID', 'integer', 'get');
        msgDebug("\nEntering toggleStatus with cID = $cID and jID = $jID");
        $curStat= dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'inactive', "id=$cID");
        $html  = '<p>'.lang('Selecte a new status:').'</p>';
        $html .= html5('statusID',['values'=>getModuleCache('contacts', 'statuses'),'attr'=>['type'=>'select', 'value'=>$curStat]]);
        $html .= html5('iconGO',  ['icon'=>'next','events'=>['onClick'=>"jsonAction('phreebooks/main/toggleStatusSet&jID=$jID&cID=$cID&selStat='+bizSelGet('statusID')); bizWindowClose('winNewStat');"]]);
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divs'=>['winNewStat'=>['order'=>50,'type'=>'html','html'=>$html]]]);
    }

    public function toggleStatusSet(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 3)) { return; }
        $cID    = clean('cID',    'integer','get');
        $newStat= clean('selStat','char',   'get');
        msgDebug("\nEntering toggleStatusSet with cID = $cID and newStat = $newStat");
        if (empty($cID)) { return msgAdd("Bad ID!"); }
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['inactive'=>$newStat], 'update', "id=$cID");
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     * Toggles the waiting flag (and database field) for a given journal record
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function toggleWaiting(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 3)) { return; }
        $rID   = clean('rID', 'integer','get');
        if (!$rID) { return msgAdd(lang('err_copy_name_prompt')); }
        $jID   = clean('jID', 'integer','get');
        $dgID  = clean('dgID','cmd',    'get');
        if (empty($dgID)) { $dgID = 'dgPhreeBooks'; }
        $result= dbGetValue(BIZUNO_DB_PREFIX."journal_main", ['waiting','invoice_num','primary_name_b'], "id=$rID");
        $state = $result['waiting']=='0' ? '1' : '0';
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['waiting'=>$state], 'update', "id=$rID");
        msgLog(lang('journal_id_'.$jID).": ".lang('toggle_status')." # {$result['invoice_num']} - {$result['primary_name_b']} (rID=$rID)");
        $layout= array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"bizGridReload('$dgID');"]]);
    }

    /**
     * creates the HTML for a pop up to set the recur parameters of a journal entry
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function popupRecur(&$layout=[])
    {
        $times = clean('rID', ['format'=>'integer', 'default'=>2], 'get');
        $freq  = clean('data',['format'=>'integer', 'default'=>3], 'get');
        $fields= [
            'txtIntro'=> ['order'=> 1,'html' =>"<p>{$this->lang['recur_desc']}</p>",'attr'=>['type'=>'raw']],
            'rcrTimes'=> ['order'=>10,'label'=>$this->lang['recur_times'],'position'=>'after','attr'=>['type'=>'integer','value'=>$times,'size'=>3,'maxlength'=>2]],
            'hr1'     => ['order'=>20,'html' =>'<hr>','attr'=>['type'=>'raw']],
            'txtFreq' => ['order'=>21,'html' =>"<p>{$this->lang['recur_frequency']}</p>",'attr'=>['type'=>'raw']],
            'radio0'  => ['order'=>30,'break'=>true,'label'=>lang('dates_weekly'),   'attr'=>['type'=>'radio','id'=>'radioRecur','name'=>'radioRecur','value'=>1,'checked'=>$freq==1?true:false]],
            'radio1'  => ['order'=>40,'break'=>true,'label'=>lang('dates_bi_weekly'),'attr'=>['type'=>'radio','id'=>'radioRecur','name'=>'radioRecur','value'=>2,'checked'=>$freq==2?true:false]],
            'radio2'  => ['order'=>50,'break'=>true,'label'=>lang('dates_monthly'),  'attr'=>['type'=>'radio','id'=>'radioRecur','name'=>'radioRecur','value'=>3,'checked'=>$freq==3?true:false]],
            'radio3'  => ['order'=>60,'break'=>true,'label'=>lang('dates_quarterly'),'attr'=>['type'=>'radio','id'=>'radioRecur','name'=>'radioRecur','value'=>4,'checked'=>$freq==4?true:false]]];
        $layout= array_replace_recursive($layout, ['type'=>'popup','title'=>lang('recur'),'attr'=>['id'=>'winRecur','height'=>450],
            'toolbars' => ['tbRecur'=>['icons'=>['next'=>['order'=>20,'events'=>['onClick'=>"jqBiz('#recur_id').val(jqBiz('#rcrTimes').val()); jqBiz('#recur_frequency').val(jqBiz('input[name=radioRecur]:checked').val()); bizWindowClose('winRecur');"]]]]],
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbRecur'],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmRecur'],
                'winRecur'=> ['order'=>50,'type'=>'fields', 'keys'=>array_keys($fields)],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'fields'   => $fields,
            'forms'    => ['frmRecur'=>['attr'=>['type'=>'form','action'=>""]]]]);
    }

    /**
     * Creates a popup to allow downloading of attachments from the manager list
     * @param type $layout
     * @return type
     */
    public function viewAttachments(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        if (empty($rID)) { return; }
        $layout= array_replace_recursive($layout, ['type'=>'popup','title'=>'Attachments','attr'=>['id'=>'winMgrAtch'],
            'divs'=>['divAtch'=>['type'=>'attach',
                'defaults'=>['noUpload'=>true,'path'=>getModuleCache($this->moduleID,'properties','attachPath','phreebooks'),'prefix'=>"rID_{$rID}_",'delPath'=>'']]]]);
    }

    /**
     * Modifications to the PhreeBooks module edit method
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function buySellEdit(&$layout=[])
    {
        $rID   = clean('rID',  'integer', 'get');
        $jID   = clean('jID',  'integer', 'get');
        $action= clean('bizAction', 'alpha_num', 'get');// inv
        if ((!$rID && $action <> 'inv') || !in_array($jID, [3,4,6,7])) { return; }
        // snag the item_list, decode, fix, and re-encode
        $curDG = substr(str_replace("var datagridData = ", '', trim($layout['jsHead']['datagridData'])), 0, -1);
        $items = json_decode($curDG, true); // should have just json left
        if (!$items) { return; } //  msgAdd("bad string: ".print_r($workingDG, true));
        foreach ($items['rows'] as $key => $item) {
            if (empty($item['sku'])) { continue; }
            $sellQty = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sell_qty', "sku='{$item['sku']}'");
            if (empty($sellQty)) { // avoid divide by zero errors
                msgAdd("Illegal sell quantity of zero for sku: {$item['sku']}, a value of 1 will be used.");
                $sellQty = 1;
            }
            $items['rows'][$key]['qty']      = round($items['rows'][$key]['qty'] / $sellQty, 2);
            $items['rows'][$key]['bal']      = round($items['rows'][$key]['bal'] / $sellQty, 2);
            $items['rows'][$key]['qty_stock']= round($items['rows'][$key]['qty_stock'] / $sellQty, 2);
            if ($action == 'inv') {
                $items['rows'][$key]['price'] = $item['price'] * $sellQty;
            } else {
                $items['rows'][$key]['price'] = !empty($items['rows'][$key]['qty']) ? $item['total'] / $items['rows'][$key]['qty'] : 0;
            }
        }
        $layout['jsHead']['datagridData'] = "var datagridData = ".json_encode($items).";\n";
    }

    /**
     * Intercepts the post and converts the SKU buy quantity to sell quantity
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function buySellSave(&$layout=[])
    {
        msgDebug("\nStarting getItems, item_array = ".print_r($_POST['item_array'], true));
        $jID   = clean('journal_id', 'integer', 'post');
        if (!in_array($jID, [3,4,6,7])) { return; }
        $items = clean('item_array', 'json', 'post'); // strips slashes if BIZUNO_STRIP_SLASHES is tru
        foreach ($items['rows'] as $key => $item) {
            if (empty($item['sku'])) { continue; }
            $sellQty = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sell_qty', "sku='{$item['sku']}'");
            if (empty($sellQty)) { $sellQty = 1; } // if inventory auto-add is enabled and adding new item
            $items['rows'][$key]['qty'] *= $sellQty;
            $items['rows'][$key]['price'] = $item['price']/$sellQty;
        }
        if (defined('BIZUNO_STRIP_SLASHES') && BIZUNO_STRIP_SLASHES) { // need to restore as post format
            $_POST['item_array'] = addslashes(json_encode($items));
        } else {
            $_POST['item_array'] = json_encode($items);
        }
    }

    private function setRowValue(&$items, $itemCnt=0, $discount=0)
    {
        foreach ($items as $idx => $row) {
            if ($row['gl_type'] <> 'itm') { continue; } // not a item row
            if ($row['item_cnt'] == $itemCnt) { $items[$idx]['unit_discount'] = $discount; }
        }
    }
}
