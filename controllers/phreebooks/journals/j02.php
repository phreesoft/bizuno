<?php
/*
 * PhreeBooks journal class for Journal 2, General Ledger
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
 * @filesource /controllers/phreebooks/journals/j02.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/journals/common.php", 'jCommon');

class j02 extends jCommon
{
    public  $journalID = 2;
    public $main;
    public $items;
    private $assets    = [0,2,4,6,8,12,32,34];
    public $type;
    public $lang;
    public $rID;
    public $totals;

    function __construct($main=[], $item=[])
    {
        parent::__construct();
        $this->main  = $main;
        $this->items = $item;
    }

/*******************************************************************************************************************/
// START Edit Methods
/*******************************************************************************************************************/
    /**
     * Tailors the structure for the specific journal
     */
    public function getDataItem()
    {
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID);
        $structure['debit_amount']['attr']['type'] = 'float'; // otherwise datagrid data will be formatted as currency and breaks editor
        $structure['credit_amount']['attr']['type']= 'float';
        $this->addGLNotes($this->items);
        $this->dgDataItem = formatDatagrid($this->items, 'datagridData', $structure);
    }

    /**
     * Customizes the layout for this particular journal
     * @param array $data - Current working structure
     * @param integer $rID - current db record ID
     * @param integer $security - users security setting
     */
    public function customizeView(&$data, $rID=0)
    {
        $fldKeys = ['id','journal_id','recur_id','recur_frequency','item_array','store_id','invoice_num','post_date','currency','currency_rate','closed','rep_id'];
        $fldAddr = ['contact_id','address_id','primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'];
        $data['fields']['currency']['callback'] = 'totalsCurrency';
        $data['jsHead']['datagridData']= $this->dgDataItem;
        $data['jsHead']['pbChart']     = "var pbChart = bizDefaults.glAccounts.rows;"; // show all accounts from the gl chart, including inactive
        $data['datagrid']['item']      = $this->dgLedger('dgJournalItem');
        $data['divs']['divDetail']     = ['order'=>50,'type'=>'divs', 'classes'=>['areaView'],'divs'=>[
            'billAD' => ['order'=>20,'type'=>'panel','key'=>'billAD', 'classes'=>['block25']],
            'props'  => ['order'=>40,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
            'totals' => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
            'dgItems'=> ['order'=>50,'type'=>'panel','key'=>'dgItems','classes'=>['block99']],
            'divAtch'=> ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']]]];
        $data['panels']['billAD'] = ['label'=>lang('bill_to'),'type'=>'address','attr'=>['id'=>'address_b'],'keys'=>$fldAddr,
                'settings'=>['type'=>'a','suffix'=>'_b','search'=>true,'required'=>false,'store'=>false]];
        $data['panels']['props']  = ['label'=>lang('details'),'type'=>'fields', 'keys'   =>$fldKeys];
        $data['panels']['totals'] = ['label'=>lang('totals'), 'type'=>'totals', 'content'=>$data['totals']];
        $data['panels']['dgItems']= ['order'=>60,'type'=>'datagrid','key'=>'item'];
        unset($data['toolbars']['tbPhreeBooks']['icons']['print']);
        unset($data['toolbars']['tbPhreeBooks']['icons']['payment']);
        if ($rID) { unset($data['toolbars']['tbPhreeBooks']['icons']['recur']); }
    }

    /**
     * Adds the notes to a general ledger entry to show if the journal balance will increase or decrease
     * @param array $items - line items from the general ledger grid
     */
    private function addGLNotes(&$items)
    {
        foreach ($items as $idx => $row) {
            $found = false;
            foreach (getModuleCache('phreebooks', 'chart') as $acct) {
                if ($acct['id'] != $row['gl_account']) { continue; }
                $found = true;
                $asset = in_array($acct['type'], $this->assets) ? 1 : 0;
                if ($row['debit_amount']  &&  $asset) { $arrow = 'inc'; }
                if ($row['debit_amount']  && !$asset) { $arrow = 'dec'; }
                if ($row['credit_amount'] &&  $asset) { $arrow = 'dec'; }
                if ($row['credit_amount'] && !$asset) { $arrow = 'inc'; }
                break;
            }
            $incdec = '';
            if ($found && $arrow=='inc')      { $incdec = json_decode('"\u21e7"').' '.$this->lang['bal_increase']; }
            else if ($found && $arrow=='dec') { $incdec = json_decode('"\u21e9"').' '.$this->lang['bal_decrease']; }
            $items[$idx]['notes'] = $incdec;
        }
    }

/*******************************************************************************************************************/
// START Post Journal Function
/*******************************************************************************************************************/
    public function Post()
    {
        msgDebug("\n/********* Posting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        $this->main['description'] = lang('journal_id_2').": {$this->items[0]['description']} ...";
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
        msgDebug("\n  j02 - Checking for re-post records ... end check for Re-post with no action.");
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

    /**
     * Checks and sets/clears the closed status of a journal entry
     * Affects journals - 2
     * @param string $action - [default: 'post']
     * @return boolean true
     */
    private function setStatusClosed($action='post')
    {
        msgDebug("\n  Checking for closed entry. action = $action");
        $closed = true; // all journal entries are closed unless the following conditions are seen
        foreach ($this->items as $row) {
            $type = !empty($row['gl_account']) ? getModuleCache('phreebooks', 'chart', $row['gl_account'], 'type') : 0;
            if (empty($this->main['closed']) && in_array($type, [2,20])) { $closed = false; }
        }
        $this->setCloseStatus($this->main['id'], $closed);
        return true;
    }

    /**
     * Creates the grid structure for general ledger items
     * @param string $name - DOM field name
     * @return array - grid structure
     */
    private function dgLedger($name)
    {
        return ['id' => $name, 'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'rownumbers'=> true,'idField'=>'id'],
            'events' => ['data'=> "datagridData",
                'onLoadSuccess'=> "function(row) { totalUpdate('dgLedger onCheck'); }",
                'onClickRow'   => "function(rowIndex, row) { curIndex = rowIndex; }",
                'onBeginEdit'  => "function(rowIndex, row) { glEditing(rowIndex); }",
                'onDestroy'    => "function(rowIndex, row) { totalUpdate('dgLedger odDestroy'); curIndex = undefined; }"],
            'source' => ['actions'=>['newItem'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> ['id'  => ['order'=>0, 'attr'=>['hidden'=>true]],
                'qty'          => ['order'=>0, 'attr'=>['hidden'=>true,'value'=>1]],
                'action'       => ['order'=>1, 'label'=>lang('action'),'attr'=>['width'=>60],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'  => ['delete'   =>['order'=>20,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'gl_account'   => ['order'=>20,'label'=>lang("gl_account_{$this->journalID}"),'attr'=>['width'=>200,'resizable'=>true,'align'=>'center'],
                    'events'   => ['editor'=>dgEditGL()]],
                'description'  => ['order'=>30,'label'=>lang('description'), 'attr'=>['width'=>400,'editor'=>dgEditText(),'resizable'=>true]],
                'debit_amount' => ['order'=>40,'label'=>lang('debit_amount'),'attr'=>['width'=>150,'resizable'=>true, 'align'=>'right'],
                    'events'   => ['editor'=>dgEditCurrency("glCalc('debit');"),'formatter'=> "function(value,row){ return formatCurrency(value); }"]],
                'credit_amount'=> ['order'=>50, 'label'=>lang('credit_amount'),'attr'=>['width'=>150,'resizable'=>true, 'align'=>'right'],
                    'events'   => ['editor'=>dgEditCurrency("glCalc('credit');"),'formatter'=> "function(value,row){ return formatCurrency(value); }"]],
                'notes'        => ['order'=>90, 'label'=>lang('notes'), 'attr'=>  ['width'=>200,'editor'=>'text','resizable'=>true]]]];
    }
}
