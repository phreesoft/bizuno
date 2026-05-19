<?php
/*
 * @name Bizuno ERP - Point of Sale Extension
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/phreebooks/mainPOS.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/main.php', 'phreebooksMain');

class mainPOSMain
{
    public $moduleID = 'phreebooks';
    public $journalID= 19; // POS Journal

    function __construct()
    {
    }

    /**
     * Generates the main manager page for this module
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess("j{$this->journalID}_mgr", 1)) { return; }
        $_GET['jID'] = $this->journalID;
        compose('phreebooks', 'main', 'edit', $layout); // get the main page layout
        unset($layout['divs']['tbJrnl']);
        // customize it for POS
        $fields = $this->menuFooter();
        $data    = ['type'=>'page', 'title'=> lang('title', $this->moduleID),
            'header'  => ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
                'left'  => ['order'=>10,'type'=>'menu','classes'=>['menuHide','m-left'], 'styles'=>['display'=>'none'],'data'=>$this->menuLeft($security)],
                'center'=> ['order'=>20,'type'=>'html','classes'=>['m-title'],'html'=>lang('title', $this->moduleID)],
                'right' => ['order'=>30,'type'=>'menu','classes'=>['menuHide','m-right'],'styles'=>['display'=>'none'],'data'=>$this->menuRight($security)]]],
            'divs'    => [
                'divDetail' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'billAD' => ['order'=>10,'type'=>'panel','key'=>'billAD', 'classes'=>['block25']],
                    'shipAD' => ['order'=>20,'type'=>'panel','key'=>'shipAD', 'classes'=>['block25']],
                    'props'  => ['order'=>30,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
                    'totals' => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
                    'dgItems'=> ['order'=>50,'type'=>'panel','key'=>'dgItems','classes'=>['block99']],
//                  'divAtch'=> ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']],
                    ]],
                'other'  => ['order'=>70,'type'=>'html','html'=>'<div id="shippingVal"></div>']],
            'footer'  => ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
                'center' => ['order'=>20,'type'=>'fields','classes'=>['m-buttongroup','m-buttongroup-justified'],'styles'=>['width'=>'100%'],'keys'=>array_keys($fields)]]],
            'fields' => $fields,
            'jsReady'=>['initMenu'=>"jqBiz('.menuHide').css('display', 'inline-block'); bizMenuResize();"],
            ];
        $layout = array_replace_recursive($layout, $data);
        msgDebug("\nLayout after replace is now: ".print_r($layout, true));
    }

    /**
     * Generates the header menu - left side
     */
    private function menuLeft($security=0)
    {
        return ['child'=>['tools'=>['order'=>50,'icon'=>'tools','child'=>[
            'openDrwr'  => ['order'=>10,'title'=>lang('open_drawer', $this->moduleID),'icon'=>'open',   'security'=>3,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"alert('open drawer');"]],
            'closeTill' => ['order'=>20,'title'=>lang('close_till', $this->moduleID), 'icon'=>'close',  'security'=>3,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"alert('close till');"]],
            'saveAsSO'  => ['order'=>30,'title'=>lang('save_so', $this->moduleID),    'icon'=>'print',  'security'=>3,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"alert('save as SO');"]],
            'saveAsQu'  => ['order'=>40,'title'=>lang('save_quote', $this->moduleID), 'icon'=>'payment','security'=>3,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"alert('save as quote');"]],
            'reprint'   => ['order'=>50,'title'=>lang('reprint', $this->moduleID),'child'=>[
                'prev'       => ['order'=>10,'title'=>lang('reprint_previous', $this->moduleID),'security'=>3,'events'=>['onClick'=>"alert('reprint receipt');"]],
                'other'      => ['order'=>20,'title'=>lang('reprint_other', $this->moduleID),'security'=>3,   'events'=>['onClick'=>"alert('reprint other receipt');"]]]],
        ]]]];
    }

    /**
     * Generates the header menu - right side
     * @return type
     */
    private function menuRight($security=0)
    {
        return ['child'=>['settings'=>['order'=>50,'icon'=>'settings','child'=>[
            'setting'=> ['order'=>20,'title'=>lang('settings'),'child'=>[
                'changeTill' => ['order'=>10,'title'=>lang('change_till', $this->moduleID),'security'=>3, 'events'=>['onClick'=>"alert('change till');"]],
                'changeStore'=> ['order'=>20,'title'=>lang('change_store', $this->moduleID),'security'=>3,'events'=>['onClick'=>"alert('change store');"]]]],
            'ticket' => ['order'=>80,'label'=>lang('support'), 'icon'=>'support', 'required'=>true,'events'=>['onClick'=>"hrefClick('bizuno/administrate/ticketMain');"],'hidden'=>defined('BIZUNO_SUPPORT_EMAIL')?false:true],
            'exit'   => ['order'=>99,'label'=>lang('exit_pos'),'icon'=>'logout',  'required'=>true,'events'=>['onClick'=>"jsonAction('');"]],
        ]]]];
    }

    /**
     * Generates the footer menu
     * @return type
     */
    private function menuFooter()
    {
        return [
            'payment'=> ['order'=>10,'break'=>false,'attr'=>['type'=>'a','value'=>lang('payment')],'classes'=>['easyui-linkbutton'],
                'options'=>['iconCls'=>"'iconL-payment'",'iconAlign'=>"'top'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"alert('Payment panel')"]],
            'save'   => ['order'=>20,'break'=>false,'attr'=>['type'=>'a','value'=>lang('save')],   'classes'=>['easyui-linkbutton'],
                'options'=>['iconCls'=>"'iconL-save'",   'iconAlign'=>"'top'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"alert('Save and Print Receipt')"]],
            'print'  => ['order'=>30,'break'=>false,'attr'=>['type'=>'a','value'=>lang('print')],  'classes'=>['easyui-linkbutton'],
                'options'=>['iconCls'=>"'iconL-print'",  'iconAlign'=>"'top'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"alert('Print panel')"]],
            'more'   => ['order'=>40,'break'=>false,'attr'=>['type'=>'a','value'=>lang('more')],   'classes'=>['easyui-linkbutton'],
                'options'=>['iconCls'=>"'iconL-more'",   'iconAlign'=>"'top'",'size'=>"'large'",'plain'=>'true'],'events'=>['onClick'=>"alert('More panel')"]]];
    }

    /**
     * Generates the JavaScript for the edit screen
     * @return string - JavaScript to be placed in the HTML head
     */
    private function editJS()
    {
        $rows = [
            ['title'=>lang('subtotal'),'total'=>0],
            ['title'=>lang('discount'),'total'=>0],
            ['title'=>lang('inventory_tax_rate_id_c'),'total'=>0],
            ['title'=>lang('total'),'total'=>0,'font_size'=>16]];
        $footer = [
            ['title'=>lang('balance_due', $this->moduleID),'total'=>0,'font_size'=>16],
            ['title'=>lang('change'),            'total'=>0,'font_size'=>16]];
        return "
var paymentData = {'total':0,'rows':[]};
var summaryData = ".json_encode(['total'=>sizeof($rows), 'rows'=>$rows, 'footer'=>$footer]).";
function bizPOSFont(value, size) { return '<span style=\'font-size:'+size+'px\'>'+value+'</span>'; }
function bizPOSSave(action) {
    var items = jqBiz('#dgJournalItem').datagrid('getData');
    jqBiz('#item_array').val(JSON.stringify(items));
    var items = jqBiz('#dgSummary').datagrid('getData');
    jqBiz('#fldSummary').val(JSON.stringify(items));
    var items = jqBiz('#dgPayment').datagrid('getData');
    jqBiz('#fldPayment').val(JSON.stringify(items));
    divSubmit('extBizPOS/main/save', 'bodyEast');
}
function bizPOSPmtSet(txID) {
    if (!txID) return; // there was an error
    var method = jqBiz('#method_code').val();
    var amount = jqBiz('#pmt_amount').numberbox('getValue');
    paymentData['rows'].push({ 'total':amount,'title':method,'attr':JSON.stringify({'code':method,'total':amount,'txID':txID,'status':'auth'}) });
    paymentData['total']++;
    jqBiz('#dgPayment').datagrid('loadData', paymentData);
    bizPOSBalCalc();
    bizWindowClose('winBizPOS');
}
function bizPOSBalCalc() {
    var footer= jqBiz('#dgSummary').datagrid('getFooterRows');
    var total = summaryData['rows'][3]['total'];
    for (i=0; i<paymentData['rows'].length; i++) {
        total -= paymentData['rows'][i]['total'];
    }
    footer[0]['total'] = total >= 0 ? total  : 0;
    footer[1]['total'] = total <= 0 ? -total : 0;
    jqBiz('#dgSummary').datagrid('reloadFooter');
}";
    }

    /**
     * Generates the payment window pop up to collect full or partial payment
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function bizWinPmt(&$layout=[])
    {
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'fields'=> [
                'method_code'=> ['attr'  =>['type'=>'hidden','value'=>'']],
                'selMethod'  => ['values'=>viewMethods('payment', 'gateways'),'events'=>['onChange'=>'selPayment(newVal);'],'attr'=>['type'=>'select']]],
            'divs'  => [
                'pmtMeth' => ['order'=>60,'type'=>'payment','classes'=>['block25']],
                'pmtAmnt' => ['order'=>80,'type'=>'html',   'html'=>"<br />".html5('pmt_amount',['label'=>lang('amount'),'classes'=>['easyui-numberbox']])."<br />"],
                'pmtSave' => ['order'=>90,'type'=>'html',   'html'=>html5('',['icon'=>'save','label'=>lang('save'),
                    'events' => ['onClick'=>"divSubmit('extBizPOS/main/bizWinPmtAuth', 'winBizPOS');"]])]],
        ]);
    }

    /**
     * Authorizes the payment with the processor
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function bizWinPmtAuth(&$layout=[])
    {
        if (!$security = validateAccess('j19_mgr', 2)) { return; }
        $ledger = [];
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/payment/main.php", 'paymentMain');
        $auth = new paymentMain();
        $txID = addslashes($auth->authorize($ledger));
        $layout = array_replace_recursive($layout, ['content'=> ['action'=>'eval', 'actionData'=>"bizPOSPmtSet('$txID');"]]);
    }

    /**
     * This method saves the posted data from the POS form to the PhreeBooks journal.
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        if (!$security = validateAccess('j19_mgr', 2)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/journal.php", 'journal');
        dbTransactionStart();
        $ledger = new journal(0, $this->journalID);
        if (!$this->getMain    ($ledger)) { return; }
        if (!$this->getItems   ($ledger)) { return; }
        if (!$this->getPayments($ledger)) { return; }
        if (!$this->getSummary ($ledger)) { return; }
        if (!$ledger->Post())             { return; }
        if (!$this->setPayments($ledger)) { return; }
        dbTransactionCommit();
        msgAdd(sprintf(lang('msg_gl_post_success'), lang('invoice_num_12'), $ledger->main['invoice_num']), 'success');
        msgLog(lang('title', $this->moduleID).'-'.lang('save')." ".lang('invoice_num_12')." {$ledger->main['invoice_num']} - {$ledger->main['description']} (rID={$ledger->main['id']}) ".lang('total').": ".viewFormat($ledger->main['total_amount'], 'currency'));
        $formID     = getDefaultFormID($this->journalID);
        $jsonAction = "paymentData = {'total':0,'rows':[]}; jqBiz('#dgPayment').datagrid({data:[]}).datagrid('reload');"; // remove payment, clear summary datagrid
        $jsonAction.= "jqBiz('#dgJournalItem').datagrid({data:[]}).datagrid('reload').edatagrid('addRow');"; // clear the item datagrid
        $jsonAction.= "winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&date=a&xfld=journal_main.id&xcr=equal&xmin={$ledger->main['id']}');";
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>['action'=>'eval','actionData'=>$jsonAction]]);
    }

    /**
     *
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function refund(&$layout=[])
    {
        if (!$security = validateAccess('j19_mgr', 4)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        if (empty($rID)) { return msgAdd("No ID passed, could not delete!", 'error'); }
        $files = glob(getModuleCache('extBizPOS', 'properties', 'attachPath', 'phreebooks')."rID_{$rID}_");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } } // remove attachments
        $oldID = dbGetValue(BIZUNO_DB_PREFIX."extBizPOS",'asset_num', "id=$rID");
        msgLog(lang('extBizPOS_title').' '.lang('delete')." - $oldID ($rID)");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>'dgExtBizPOSReload()'],
            'dbAction' => ["extBizPOS"=>"DELETE FROM ".BIZUNO_DB_PREFIX."extBizPOS WHERE id=$rID"]]);
    }

    /**
     *
     * @param type $ledger
     * @return boolean
     */
    private function getMain(&$ledger)
    {
        $values = cleanRequest(dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', $this->journalID), 'post');
        $ledger->main = array_replace($ledger->main, $values);
        if (!$ledger->updateContact()) { return; }
        $ledger->main['description'] = 'title:' . $ledger->main['primary_name_b'];
        // find which till was used, get till defaults
        //$till = [];
        return true;
    }

    /**
     *
     * @param type $ledger
     * @return boolean
     */
    private function getItems(&$ledger)
    {
        $map = [
            'ref_id'       => ['type'=>'constant', 'value'=>$ledger->main['id']],
            'gl_type'      => ['type'=>'constant', 'value'=>'itm'],
            'debit_amount' => ['type'=>'constant', 'value'=>'0'],
            'post_date'    => ['type'=>'constant', 'value'=>$ledger->main['post_date']],
            'credit_amount'=> ['type'=>'field',    'index'=>'total']];
        $rows = requestDataGrid(clean('item_array', 'json', 'post'), dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item'), $map);
        $skipList = ['sku', 'description', 'credit_amount', 'debit_amount']; // if qty=0 or all these are not set or null, row is blank
        $item_cnt = 1;
        $ledger->items = [];
        foreach ($rows as $row) {
            if (!isBlankRow($row, $skipList)) {
                $row['item_cnt'] = $item_cnt;
                $ledger->items[]  = $row;
            }
            $item_cnt++;
        }
        // check to make sure there is at least one row
        if (sizeof($ledger->items) == 0) { return msgAdd("There are no items to post for this order!"); }
        return true;
    }

    /**
     *
     * @param type $ledger
     * @return boolean
     */
    private function getPayments(&$ledger)
    {
        $rows = clean('fldPayment', 'json', 'post');
        $total= 0;
        foreach ($rows['rows'] as $row) {
            $attr = json_decode($row['attr'], true);
            $ledger->items[] = [
                'gl_type'      => 'pmt',
                'qty'          => '1',
                'description'  => "code:{$attr['code']},status:{$attr['status']}",
                'credit_amount'=> $attr['total'],
                'debit_amount' => 0,
                'gl_account'   => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'),
                'trans_code'   => $attr['txID'],
                'post_date'    => biz_date('Y-m-d')];
            $total += $attr['total'];
        }
        if ($total > $ledger->main['total_amount']) { // change was made, need to create a journal entry for that as well
            $ledger->items[] = [
                'gl_type'      => 'pmt',
                'qty'          => '1',
                'description'  => "code:cod",
                'credit_amount'=> 0,
                'debit_amount' => $total - $ledger->main['total_amount'],
                'gl_account'   => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'),
                'post_date'    => biz_date('Y-m-d')];
        } elseif ($total < $ledger->main['total_amount']) {
            return msgAdd('Not enough payment was received to complete this sale.');
        }
        return true;
    }

    /**
     *
     * @param type $ledger
     * @return boolean
     */
    private function getSummary(&$ledger)
    {
        $current_total = 0;
        foreach ($ledger->items as $row) { $current_total += $row['debit_amount'] + $row['credit_amount']; } // subtotal of all rows
        msgDebug("\nStarting to build total GL rows, starting subtotal = $current_total");
        $pbClass = new phreebooksMain();
        $this->totals = $pbClass->loadTotals(12);
        foreach ($this->totals as $methID) {
            $path = getModuleCache('phreebooks', 'totals', $methID, 'path');
            $fqcn = "\\bizuno\\$methID";
            bizAutoLoad("{$path}$methID.php", $fqcn);
            $totSet = getModuleCache('phreebooks','totals',$methID,'settings');
            $totalEntry = new $fqcn($totSet);
            if (method_exists($totalEntry, 'glEntry')) { $totalEntry->glEntry($ledger->main, $ledger->items, $current_total); }
            if ($methID == 'total') { // need to create a duplicate for the balance
                $last_item = array_pop($ledger->items);
                $ledger->items[] = $last_item; // put it back
                $last_item['gl_type'] = 'bal';
                $last_item['gl_account'] = getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables');
                $ledger->items[] = $last_item; // add balance transaction
            }
        }
        return true;
    }

    /**
     *
     * @param type $ledger
     * @return boolean
     */
    private function setPayments(&$ledger)
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/payment/main.php", 'paymentMain');
        foreach ($ledger->items as $row) { // handle payments
            if ($row['gl_type'] != 'pmt') { continue; }
            $processor = new paymentMain();
            $attr = explode(',', $row['description']);
            $encData = [];
            foreach ($attr as $entry) {
                $vals = explode(':', $entry);
                $encData[$vals[0]] = $vals[1];
            }
            if (!$processor->sale($encData['code'], $ledger)) { return; }
        }
        return true;
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgPayment($name)
    {
        return ['id' => $name,
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'pagination'=>false],
            'events' => ['data'   => 'paymentData'],
            'source' => ['actions'=>[
                'pmtPay'  => ['order'=>10,'icon'=>'payment','label'=>lang('journal_id_18'),
                    'events'=>['onClick'=>"windowEdit('extBizPOS/main/bizWinPmt', 'winBizPOS', '".lang('journal_id_18')."', 400, 400);"]],
                'pmtPrint'=> ['order'=>80,'align'=>'right','icon'=>'saveprint','label'=>lang('save_print'),'events'=>['onClick'=>"bizPOSSave('print');"]],
                'pmtSave' => ['order'=>90,'align'=>'right','icon'=>'save','events'=>['onClick'=>"bizPOSSave('save');"]]]],
            'columns'=> [
                'font_size' => ['order'=> 0, 'attr' =>['hidden'=>'true']],
                'attr'      => ['order'=> 0, 'attr' =>['hidden'=>'true']], // holds line item details
                'title'     => ['order'=>40, 'label'=>'',            'attr'=>['width'=>80,'resizable'=>true],
                    'events'=> ['formatter'=>"function(value,row){ return (row.font_size) ? bizPOSFont(value, row.font_size) : value; }"]],
                'total'     => ['order'=>70, 'label'=>lang('amount'),'attr'=>['width'=>40,'resizable'=>true,'align'=>'right'],
                    'events'=> ['formatter'=>"function(value,row){ var val=formatCurrency(value); return (row.font_size) ? bizPOSFont(val, row.font_size) : val; }"]]]];
    }
}
