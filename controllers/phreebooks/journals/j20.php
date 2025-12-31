<?php
/*
 * PhreeBooks journal class for Journal 20, Vendor Payments (Pay Bills)
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
 * @version    7.x Last Update: 2025-12-29
 * @filesource /controllers/phreebooks/journals/j20.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/journals/common.php", 'jCommon');

class j20 extends jCommon
{
    public  $journalID= 20;
    private $isACH    = []; // tracks if vendor is ACH enabled
    private $achTotals= ['achBalBeg','achSubtotal','achDiscount','achTotal','achBalEnd'];
    public $main;
    public $items;
    public $type;
    public $lang;
    public $rID;
    public $totals;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main = $main;
        $this->items= $item;
    }

/*******************************************************************************************************************/
// START Edit Methods
/*******************************************************************************************************************/
    /**
     * Tailors the structure for the specific journal
     */
    public function getDataItem() { }

    /**
     * Customizes the layout for this particular journal
     * @param array $data - Current working structure
     * @param integer $rID - current db record ID
     * @param integer $cID - current customer db record ID
     */
    public function customizeView(&$data, $rID=0, $cID=0)
    {
        $fldKeys = ['id','journal_id','so_po_ref_id','terms','override_user','override_pass','recur_id','recur_frequency','item_array','xChild','xAction','store_id',
            'purch_order_id','invoice_num','waiting','closed','terms_text','post_date','rep_id','currency','currency_rate'];
        $fldAddr = ['contact_id','address_id','primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'];
        $data['fields']['currency']['callback'] = 'totalsCurrency';
        if ($cID || $this->action=='bulk') {
            $data['fields']['purch_order_id']['attr']['type'] = 'hidden';
            if (!$rID) { $data['fields']['invoice_num']['attr']['value'] = getNextReference('next_ref_j20'); }
            $data['fields']['terminal_date']['attr']['type'] = 'hidden';
            $dgData = [];
            foreach ($this->items as $row) {
                if ($row['gl_type'] <> 'pmt') { continue; }
                $row['is_ach'] = !empty($row['contact_id']) ? $this->checkACH($row['contact_id']) : false;
                msgDebug("\nJournal 20 row = ".print_r($row, true));
                $dgData[] = $row;
            }
            $dgStructure= $this->action=='bulk' ? $this->dgBankingBulk('dgJournalItem', $this->journalID) : $this->dgBanking('dgJournalItem', $this->journalID);
            $map['credit_amount']= ['type'=>'field', 'index'=>'amount'];
            $data['jsHead']['datagridData'] = formatDatagrid($dgData, 'datagridData', $dgStructure['columns'], $map);
            if ($rID || $cID || $this->action=='bulk') { $data['datagrid']['item'] = $dgStructure; }
            if (isset($data['fields']['waiting']['attr']['checked']) && $data['fields']['waiting']['attr']['checked'] == 'checked') {
                $data['fields']['waiting']= ['attr'=>['type'=>'hidden','value'=>'1']];
            } else {
                $data['fields']['waiting']= ['attr'=>['type'=>'hidden','value'=>'0']];
            }
            if (isset($data['fields']['closed']['attr']['checked']) && $data['fields']['closed']['attr']['checked'] == 'checked') {
                $data['fields']['closed']= ['attr'=>['type'=>'hidden','value'=>'1']];
            } else {
                $data['fields']['closed']= ['attr'=>['type'=>'hidden','value'=>'0']];
            }
            $data['divs']['divDetail'] = ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'billAD' => ['order'=>20,'type'=>'panel','key'=>'billAD', 'classes'=>['block25']],
                'props'  => ['order'=>30,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
                'totals' => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
                'dgItems'=> ['order'=>60,'type'=>'panel','key'=>'dgItems','classes'=>['block99']]]];
            $data['panels']['billAD']   = ['label'=>lang('pay_to'), 'type'=>'address','attr'   =>['id'=>'address_b'],'keys'=>$fldAddr,
                'settings'=>['suffix'=>'_b','clear'=>false,'props'=>false,'required'=>true,'store'=>false,'cols'=>false]];
            $data['panels']['props']    = ['label'=>lang('details'),'type'=>'fields', 'keys'   =>$fldKeys];
            $data['panels']['totals']   = ['label'=>lang('totals'), 'type'=>'totals', 'content'=>$data['totals']];
            $data['panels']['dgItems']  = ['type'=>'datagrid','key'=>'item'];
            $data['jsHead']['preSubmit']= "function preSubmit() {
    var items = new Array();
    var dgData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<dgData.rows.length; i++) if (dgData.rows[i]['checked']) items.push(dgData.rows[i]);
    var serializedItems = JSON.stringify(items);
    jqBiz('#item_array').val(serializedItems);
    if (!formValidate()) return false;
    return true;
}";
            unset($data['jsReady']['focus']);
            $data['jsReady']['init'] = "ajaxForm('frmJournal');
jqBiz('#post_date').datebox({'onChange': function(newVal, oldVal) { totalsGetBegBalance(newVal); } });
jqBiz('#gl_acct_id').combogrid({'onChange': function(newVal, oldVal) { totalsGetBegBalance(bizDateGet('post_date')); } });";
            if ($this->action=='bulk') {
                unset($data['toolbars']['tbPhreeBooks']['icons']['new']);
                unset($data['toolbars']['tbPhreeBooks']['icons']['recur']);
                unset($data['divs']['divDetail']['divs']['billAD']);
                $data['forms']['frmJournal']['attr']['action'] = BIZUNO_URL_AJAX."&bizRt=phreebooks/main/saveBulk&jID=$this->journalID";
                $data['divs']['divDetail']['divs']['tot_ach'] = ['order'=>50,'type'=>'panel','key'=>'tot_ach','classes'=>['block25R']];
                $data['panels']['tot_ach']  = ['label'=>lang('total_ach'),'type'=>'totals', 'content'=>$this->achTotals];
                $jsTotals = "function achTotalUpdate(func) {\n\tvar taxRunning=0;\n\tvar curTotal=0;\n";
                foreach ($this->achTotals as $methID) { $jsTotals .= "\tcurTotal = totals_{$methID}(curTotal);\n"; }
                $jsTotals.= "}";
                $data['jsHead']['achTotal'] = $jsTotals;
                $data['jsReady']['init'] = "ajaxForm('frmJournal');
jqBiz('#post_date').datebox({'onChange': function(newVal, oldVal) { totalsGetBegBalance(newVal); totalsGetAchBegBal(newVal); } });
jqBiz('#gl_acct_id').combogrid({'onChange': function(newVal, oldVal) { totalsGetBegBalance(bizDateGet('post_date')); totalsGetAchBegBal(bizDateGet('post_date')); } });";
            } else {
                $data['jsHead']['achTotal'] = "function achTotalUpdate(func) {}";
            }
        } else {
            unset($data['divs']['tbJrnl'], $data['fields']['notes']);
            $data['divs']['divDetail']  = ['order'=>50,'type'=>'html','html'=>"<p>".sprintf(lang('search_open_journal'),lang('ctype_v'))."</p>".html5('contactSel', ['attr'=>['value'=>'']])];
            $data['jsBody']['selVendor']= "jqBiz('#contactSel').combogrid({width:200,panelWidth:500,delay:500,iconCls:'icon-search',hasDownArrow:false,
    idField:'contact_id_b',textField:'primary_name_b',mode:'remote',
    url:'".BIZUNO_URL_AJAX."&bizRt=phreebooks/main/managerRowsBank&jID=".JOURNAL_ID."',
    onBeforeLoad:function (param) { var newValue = jqBiz('#contactSel').combogrid('getValue'); if (newValue.length < 2) return false; },
    onClickRow:function (idx, row) { journalEdit(".JOURNAL_ID.", 0, row.contact_id_b); },
    columns:[[
        {field:'contact_id_b',  hidden:true},
        {field:'primary_name_b',title:'".jsLang('primary_name')."', width:200},
        {field:'city_b',        title:'".jsLang('city')        ."', width:100},
        {field:'state_b',       title:'".jsLang('state')       ."', width: 50},
        {field:'total_amount',  title:'".jsLang('total')."', width:100, align:'right', formatter:function (value) {return formatCurrency(value);} }]] });";
            $data['jsReady']['init'] = "ajaxForm('frmJournal');";
            $data['jsReady']['focus']= "bizFocus('contactSel');";
        }
    }

/*******************************************************************************************************************/
// START Post Journal Function
/*******************************************************************************************************************/
    public function Post()
    {
        msgDebug("\n/********* Posting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        $this->setItemDefaults(); // makes sure the journal_item fields have a value
        $this->unSetCOGSRows(); // they will be regenerated during the post
        if (!$this->postMain())              { return; }
        if (!$this->postItem())              { return; }
        if (!$this->postInventory())         { return; }
        if (!$this->postJournalHistory())    { return; }
        if (!$this->setStatusClosed('post')) { return; }
        msgDebug("\n*************** end Posting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    public function unPost()
    {
        msgDebug("\n/********* unPosting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        if (!$this->unPostJournalHistory())    { return; }    // unPost the chart values before inventory where COG rows are removed
        if (!$this->unPostInventory())         { return; }
        if (!$this->unPostMain())              { return; }
        if (!$this->unPostItem())              { return; }
        if (!$this->setStatusClosed('unPost')) { return; } // check to re-open predecessor entries
        msgDebug("\n*************** end unPosting Journal ******************* id = {$this->main['id']}\n\n");
        return true;
    }

    /**
     * Get re-post records - applies to journals 2, 17, 18, 20, 22
     * @return array - empty
     */
    public function getRepostData()
    {
        msgDebug("\n  j20 - Checking for re-post records ... end check for Re-post with no action.");
        return [];
    }

    /**
     * Post journal item array to journal history table
     * applies to journal 2, 6, 7, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22
     * @return boolean - true
     */
    private function postJournalHistory()
    {
        msgDebug("\n  Posting Chart Balances...");
        if ($this->setJournalHistory()) { return true; }
    }

    /**
     * unPosts journal item array from journal history table
     * applies to journal 2, 6, 7, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22
     * @return boolean - true
     */
    private function unPostJournalHistory() {
        msgDebug("\n  unPosting Chart Balances...");
        if ($this->unSetJournalHistory()) { return true; }
    }

    /**
     * Post inventory
     * applies to journal 2, 3, 9, 17, 18, 20, 22
     * @return boolean true on success, null on error
     */
    private function postInventory()
    {
        msgDebug("\n  Posting Inventory ... end Posting Inventory not requiring any action.");
        return true;
    }

    /**
     * unPost inventory
     * applies to journal 2, 3, 9, 17, 18, 20, 22
     * @return boolean true on success, null on error
     */
    private function unPostInventory()
    {
        msgDebug("\n  unPosting Inventory ... end unPosting Inventory with no action.");
        return true;
    }

    private function checkACH($cID = 0)
    {
        if (empty($cID)) { return 0; }
        if (isset($this->isACH[$cID])) { return $this->isACH[$cID]; }
        $enACH = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'ach_enable', "id=$cID");
        $this->isACH[$cID] = !empty($enACH) ? 1 : 0;
        return $enACH;
    }
    /**
     * Checks and sets/clears the closed status of a journal entry
     * Affects journals - 17, 18, 20, 22
     * @param string $action - [default: 'post']
     * @return boolean true
     */
    private function setStatusClosed($action='post')
    {
        // closed can occur many ways including:
        //   forced closure through so/po form (from so/po journal - adjust qty on so/po)
        //   all quantities are reduced to zero (from so/po journal - should be deleted instead but it's possible)
        //   editing quantities on po/so to match the number received (from po/so journal)
        //   receiving all (or more) po/so items through one or more purchases/sales (from purchase/sales journal)
        msgDebug("\n  Checking for closed entry. action = $action");
        if ($action == 'post') {
            $temp = [];
            for ($i = 0; $i < count($this->items); $i++) { // fetch the list of paid invoices
                if (isset($this->items[$i]['item_ref_id']) && $this->items[$i]['item_ref_id']) {
                    $temp[$this->items[$i]['item_ref_id']] = true;
                }
            }
            $invoices = array_keys($temp);
            for ($i = 0; $i < count($invoices); $i++) {
                $result     = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['journal_id','total_amount'], "id={$invoices[$i]}");
                $total_billed= roundAmount($result['total_amount'], $this->rounding);
                $total_paid  = getPaymentInfo($invoices[$i], $result['journal_id']);
                msgDebug("\n    rounding = $this->rounding, raw billed = {$result['total_amount']} which rounded to $total_billed and total_paid = $total_paid");
                $this->setCloseStatus($invoices[$i], $total_billed == -$total_paid ? true : false); // either close or re-open
            }
        } else { // unpost - re-open the purchase/invoices affected
            for ($i = 0; $i < count($this->items); $i++) {
                if ($this->items[$i]['item_ref_id']) { $this->setCloseStatus($this->items[$i]['item_ref_id'], false); }
            }
        }
        return true;
    }
}
