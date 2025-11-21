<?php
/*
 * PhreeBooks journal class for Journal 15, Inventory Store Transfer
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
 * @filesource /controllers/phreebooks/journals/j15.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journals/common.php', 'jCommon');

class j15 extends jCommon
{
    protected $journalID = 15;
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
    public function getDataItem()
    {
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item', $this->journalID);
        $dbData = [];// calculate some form fields that are not in the db
        foreach ($this->items as $key => $row) {
            if ($row['gl_type'] <> 'adj') { continue; } // not an adjustment record
            $values = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='{$row['sku']}'");
            if (empty($values)) { $values = ['qty_stock'=>0, 'item_cost'=>0]; }
            $row['qty_stock'] = $values['qty_stock']-$row['qty'];
            $row['item_cost'] = $values['item_cost'];
            $row['balance']   = $values['qty_stock'];
            $row['price']     = viewFormat($values['item_cost'], 'currency');
            $dbData[$key]     = $row;
        }
        $map['credit_amount'] = ['type'=>'field','index'=>'total'];
        $this->dgDataItem = formatDatagrid($dbData, 'datagridData', $structure, $map);
    }

    /**
     * Customizes the layout for this particular journal
     * @param array $data - Current working structure
     * @param integer $rID - current db record ID
     */
    public function customizeView(&$data, $rID=0)
    {
        $fldKeys = ['id','journal_id','recur_id','recur_frequency','item_array','invoice_num','post_date','so_po_ref_id','store_id','selShip','method_code'];
        $fldAddr = ['contact_id','address_id','primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'];
        $data['jsHead']['datagridData'] = $this->dgDataItem;
        if (!$rID && getModuleCache('shipping', 'properties', 'status')) { $data['fields']['waiting']['attr']['value'] ='1'; } // to be seen by ship manager to print label
        $data['datagrid']['item']  = $this->dgAdjust('dgJournalItem');
        $data['fields']['waiting']['attr']['type'] = '1'; // for ship manager
        $data['fields']['so_po_ref_id'] = array_replace_recursive($data['fields']['so_po_ref_id'], ['order'=>17,'values'=>viewStores(),'break'=>true,
            'attr'=>['type'=>'select'],'events'=>['onChange'=>"jqBiz('#dgJournalItem').edatagrid('endEdit', curIndex); crmDetail(this.value, '_b');"]]);
        $data['fields']['selShip'] = ['order'=>19,'label'=>'Final Store','values'=>viewStores(),'break'=>true,
            'attr'=>['type'=>'select','value'=>$data['fields']['contact_id_s']['attr']['value']],'events'=>['onChange'=>"contactsDetail(newVal, '_s');"]];
        $choices = [['id'=>'', 'text'=>lang('select')]];
        $carriers= getMetaMethod('carriers');
        foreach ($carriers as $carrier) {
            if ($carrier['status'] && isset($carrier['settings']['services'])) { $choices = array_merge_recursive($choices, $carrier['settings']['services']); }
        }
        $shipMeth = $data['fields']['method_code']['attr']['value'];
        $data['fields']['method_code'] = ['label'=>lang('method_code'),'options'=>['width'=>300],'values'=>$choices,'attr'=>['type'=>'select','value'=>$shipMeth]];
        unset($data['toolbars']['tbPhreeBooks']['icons']['print'], $data['toolbars']['tbPhreeBooks']['icons']['recur'], $data['toolbars']['tbPhreeBooks']['icons']['payment']);
        $isWaiting = isset($data['fields']['waiting']['attr']['checked']) && $data['fields']['waiting']['attr']['checked'] ? 1 : 0;
        $data['fields']['waiting'] = ['attr'=>['type'=>'hidden','value'=>$isWaiting]];
        $data['divs']['divDetail'] = ['order'=>50,'type'=>'divs',     'classes'=>['areaView'],'divs'=>[
            'shipAD'  => ['order'=>20,'type'=>'panel','key'=>'shipAD', 'classes'=>['block25']],
            'props'   => ['order'=>30,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
            'totals'  => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
            'dgItems' => ['order'=>50,'type'=>'panel','key'=>'dgItems','classes'=>['block99']],
            'divAtch' => ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']],
            'divNotes'=> ['order'=>95,'type'=>'panel','key'=>'notes',  'classes'=>['block50']]]];
//        $data['panels']['shipAD'] = ['label'=>lang('ship_to'),'type'=>'address','attr'=>['id'=>'address_s'],'keys'=>$fldAddr,
//                'settings'=>['suffix'=>'_s','search'=>true,'update'=>true,'validate'=>true,'drop'=>true,'cols'=>false]];
        $data['panels']['shipAD'] = ['label'=>lang('ship_to'),'type'=>'address','attr'=>['id'=>'address_s'],'keys'=>$fldAddr,
                'settings'=>['type'=>'b','suffix'=>'_s','props'=>false,'clear'=>false]];
        $data['panels']['props']  = ['label'=>lang('details'),'type'=>'fields','keys'   =>$fldKeys];
        $data['panels']['totals'] = ['label'=>lang('totals'), 'type'=>'totals','content'=>$data['totals']];
        $data['panels']['dgItems']= ['type'=>'datagrid','key'=>'item'];
    }

/*******************************************************************************************************************/
// START Post Journal Function
/*******************************************************************************************************************/
    public function Post()
    {
        msgDebug("\n/********* Posting Journal main ... id = {$this->main['id']} and journal_id = {$this->main['journal_id']}");
        if (empty($this->items) || !$this->journalFillAddress($this->main)) { return; }
        $this->main['description'] = lang('journal_id_15').": ({$this->items[0]['qty']}) {$this->items[0]['description']}".(sizeof($this->items)>2 ? ' +++' : '');
        $this->unSetCOGSRows(); // unsets rows that will be regenerated during the post
        if (!$this->journalTransfer($this->items))  { return; } // adds offsetting item rows of type: adj as type: xfr
        $this->setItemDefaults(); // makes sure certain journal_item fields have a value
        if (!$this->postMain())                     { return; }
        if (!$this->postItem())                     { return; }
        if (!$this->postInventory())                { return; }
        if (!$this->postJournalHistory())           { return; }
        if (!$this->setStatusClosed('post'))        { return; }
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
     * Get re-post records - applies to journals 6, 7, 12, 13, 14, 15, 16, 19, 21
     * @return array - journal id's that need to be re-posted as a result of this post
     */
    public function getRepostData()
    {
        msgDebug("\n  j15 - Checking for re-post records ... ");
        $out1 = [];
        $out2 = array_merge($out1, $this->getRepostInv());
        $out3 = array_merge($out2, $this->getRepostInvCOG());
//      $out4 = array_merge($out3, $this->getRepostInvAsy());
        $out5 = array_merge($out3, $this->getRepostPayment());
        msgDebug("\n  j15 - End Checking for Re-post.");
        return $out5;
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
     * @return boolean true on success, null on error
     */
    private function postInventory()
    {
        msgDebug("\n  Posting Inventory ...");
        $str_field = 'qty_stock';
        // adjust inventory stock status levels (also fills inv_list array)
        $item_rows_to_process = count($this->items); // NOTE: variable needs to be here because $this->items may grow within for loop (COGS)
// the cogs rows are added after this loop ..... the code below needs to be rewritten
        for ($i = 0; $i < $item_rows_to_process; $i++) {
            if (!in_array($this->items[$i]['gl_type'], ['itm','adj','asy','xfr'])) { continue; }
            if (isset($this->items[$i]['sku']) && $this->items[$i]['sku'] <> '') {
                $inv_list = $this->items[$i];
                $inv_list['price'] = $this->items[$i]['qty'] ? (($this->items[$i]['debit_amount'] + $this->items[$i]['credit_amount']) / $this->items[$i]['qty']) : 0;
                if (!$this->calculateCOGS($inv_list)) { return; }
            }
        }
        // update inventory status
        foreach ($this->items as $row) {
            if (!isset($row['sku']) || !$row['sku']) { continue; } // skip all rows without a SKU
            $item_cost = $full_price = 0;
            if (!$this->setInvStatus($row['sku'], $str_field, $row['qty'], $item_cost, $row['description'], $full_price)) { return false; }
        }
        // build the cogs item rows
        $this->setInvCogItems();
        msgDebug("\n  end Posting Inventory.");
        return true;
    }

    /**
     * unPost inventory
     * @return boolean true on success, null on error
     */
    private function unPostInventory()
    {
        msgDebug("\n  unPosting Inventory ...");
        if (!$this->rollbackCOGS()) { return false; }
        for ($i = 0; $i < count($this->items); $i++) {
            if (empty($this->items[$i]['sku'])) { continue; }
            if (!$this->setInvStatus($this->items[$i]['sku'], 'qty_stock', -$this->items[$i]['qty'])) { return; }
        }
        if ($this->main['so_po_ref_id'] > 0) { $this->setInvRefBalances($this->main['so_po_ref_id'], false); }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_history WHERE ref_id = {$this->main['id']}");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_usage WHERE journal_main_id={$this->main['id']}");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."journal_cogs_owed  WHERE journal_main_id={$this->main['id']}");
        msgDebug("\n  end unPosting Inventory.");
        return true;
    }

    /**
     * Checks and sets/clears the closed status of a journal entry
     * Affects journals - 3, 7, 9, 13, 14, 15, 16
     * @param string $action - [default: 'post']
     * @return boolean true
     */
    private function setStatusClosed($action='post')
    {
        msgDebug("\n  Checking for closed entry. action = $action, returning with no action.");
        return true;
    }

    /**
     * Perform some error checks and fill in the address fields in journal_main
     * @param array $main - journal main
     * @return true on success, false on error
     */
    private function journalFillAddress(&$main)
    {
        if ($this->main['store_id']==$this->main['so_po_ref_id']) { return msgAdd(lang('err_gl_xfr_same_store')); }
        $main['contact_id_b'] = $main['so_po_ref_id']; // copy the source address to the address billing part
        $addSrc     = addressLoad($main['contact_id_b'], '_b');
        foreach ($addSrc as $key => $value) { if (isset($main[$key])) { $main[$key] = $value; } }
//        $main['contact_id_s'] = $main['store_id']; // copy the destination address to the address shipping part
//        $addDest    = addressLoad($main['store_id'], '_s');
//        foreach ($addDest as $key => $value) { if (isset($main[$key])) { $main[$key] = $value; } }
        return true;
    }

    /**
     * This method takes the line items from a transfer operation and builds the new 'effective' line items
     * @param array $item - the list of items to transfer, after initial processing
     */
    private function journalTransfer(&$item)
    {
        msgDebug("\nAdding rows for Inventory Store Transfer");
        // take the line items and create a negative list for the receiving store
        $output = [];
        foreach ($item as $row) {
            if ($row['gl_type']<>'adj') { continue; }
            $row['id']  = 0; // when reposting, this causes duplicate ID errors if not cleared
            $row['qty'] = -$row['qty'];
            $row['gl_type'] = 'xfr';
            $tmp = $row['credit_amount']; // swap debits and credits
            $row['credit_amount'] = $row['debit_amount'];
            $row['debit_amount'] = $tmp;
            $output[] = $row;
        }
        $item = array_merge($item, $output);
        return true;
    }

    /**
     * Creates the datagrid structure for inventory adjustments line items
     * @param string $name - DOM field name
     * @return array - datagrid structure
     */
    private function dgAdjust($name)
    {
        $on_hand  = jsLang('inventory', 'qty_stock');
        $on_order = jsLang('inventory', 'qty_po');
        $store_id = getUserCache('profile', 'store_id', false, 0);
        return ['id' => $name,'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'rownumbers'=>true, 'singleSelect'=>true, 'idField'=>'id'],
            'events' => ['data'=> "datagridData",
                'onLoadSuccess'=> "function(row) { totalUpdate('dgAdjust onCheck'); }",
                'onClickRow'   => "function(rowIndex) { curIndex = rowIndex; }",
                'onBeforeEdit' => "function(rowIndex) {
    var edtURL = jqBiz(this).edatagrid('getColumnOption','sku');
    edtURL.editor.options.url = bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1&bID='+jqBiz('#so_po_ref_id').val();
}",
                'onBeginEdit'  => "function(rowIndex) { curIndex = rowIndex; }", // jqBiz('#$name').edatagrid('editRow', rowIndex);
                'onDestroy'    => "function(rowIndex) { totalUpdate('dgAdjust onDestroy'); curIndex = undefined; }",
                'onAdd'        => "function(rowIndex) { setFields(rowIndex); }"],
            'source' => [
                'actions' => ['newItem' =>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'id'         => ['order'=>0, 'attr'=>['hidden'=>true]],
                'gl_account' => ['order'=>0, 'attr'=>['hidden'=>true]],
                'item_cost'  => ['order'=>0, 'attr'=>['hidden'=>true]],
                'action'     => ['order'=>1, 'label'=>lang('action'),'attr'=>['width'=>60],
                    'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> ['trash' => ['order'=>80,'icon'=>'trash','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'sku'        => ['order'=>20, 'label'=>lang('sku'),'attr'=>['width'=>120,'sortable'=>true,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'combogrid',options:{
    width: 150, panelWidth: 540, delay: 500, idField: 'sku', textField: 'sku', mode: 'remote',
    url:        '".BIZUNO_URL_AJAX."&bizRt=inventory/main/managerRows&clr=1&f0=a&bID=$store_id',
    onClickRow: function (idx, data) { adjFill(data); },
    columns:[[{field:'sku',title:'".jsLang('sku')."',width:100},{field:'description_short',title:'".jsLang('description')."',width:200},{field:'qty_stock',title:'$on_hand', align:'right',width:90},{field:'qty_po',title:'$on_order',align:'right',width:90}]]
}}"]],
                'qty_stock'  => ['order'=>30,'label'=>$on_hand,'attr'=>['width'=>100,'disabled'=>true,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'numberbox',options:{disabled:true}}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'qty'        => ['order'=>40,'label'=>lang('quantity'),'attr' =>['width'=>100,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'numberbox',options:{min:0,onChange:function(){ adjCalc('qty'); } } }",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'balance'    => ['order'=>50, 'label'=>lang('balance'),'styles'=>['text-align'=>'right'],
                    'attr'   => ['width'=>100, 'disabled'=>true, 'resizable'=>true, 'align'=>'center'],
                    'events' => ['editor'=>"{type:'numberbox',options:{disabled:true}}",'formatter'=>"function(value,row){ return formatNumber(value); }"]],
                'total'      => ['order'=>60, 'label'=>lang('total'),'format'=>'currency','attr'=>['width'=>120,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'numberbox'}",'formatter'=>"function(value,row){ return formatCurrency(value); }"]],
                'description'=> ['order'=>70,'label'=>lang('description'),'attr'=>['width'=>250,'editor'=>'text','resizable'=>true]]]];
    }
}
