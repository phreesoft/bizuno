<?php
/*
 * Module contacts history methods
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
 * @filesource /controllers/contacts/history.php
 */

namespace bizuno;

class contactsHistory
{
    private $moduleID   = 'contacts';
    private $pageID     = 'history';
    public $lang;
    public $type;
    public $securityMenu;

    function __construct($type='c')
    {
        $this->lang = getLang($this->moduleID);
        $this->type = clean('type', 'char', 'get');
        $this->securityMenu= 'mgr_'.$this->type;
    }

    /**
     * Ajax call to refresh the history tab of a contact being edited.
     * @param type $layout
     * @return typef'src'
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->securityMenu, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $data= ['type'=>'divHTML',
            'divs'   => [
                'general'=> ['order'=>50,'type'=>'divs','attr'=>['id'=>'crmDiv'],'classes'=>['areaView'],'divs'=>[
                    'dgQuote'=> ['order'=>30,'type'=>'panel','key'=>'dgQuote','classes'=>['block50']],
                    'dgSoPo' => ['order'=>20,'type'=>'panel','key'=>'dgSoPo', 'classes'=>['block50']],
                    'dgInv'  => ['order'=>10,'type'=>'panel','key'=>'dgInv',  'classes'=>['block50']]]]],
            'panels' => [
                'dgQuote'=> ['type'=>'datagrid', 'key'=>'quote'],
                'dgSoPo' => ['type'=>'datagrid', 'key'=>'po_so'],
                'dgInv'  => ['type'=>'datagrid', 'key'=>'inv']],
            'datagrid'=> [
                'quote' => $this->dgHistory('dgHistory09', $this->type=='v'?3:9,  $rID),
                'po_so' => $this->dgHistory('dgHistory10', $this->type=='v'?4:10, $rID),
                'inv'   => $this->dgHistory('dgHistory12', $this->type=='v'?6:12, $rID)]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * builds the history list of orders used on contact history tab
     * @param array $layout - current working structure
     * @return updated $layout
     */
    public function managerRows(&$layout=[])
    {
        $jID = clean('jID', 'integer', 'get');
        $rID = clean('rID', 'integer', 'get');
        $structure = $this->dgHistory('dgHistory'.$jID, $jID, $rID);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'history','datagrid'=>['history'=>$structure]]);
    }

    /**
     *
     * @return type
     */
    public function payment()
    {
        $rID   = clean('rID', 'integer', 'get');
        $terms = viewFormat($rID, 'cTerms');
        $lYear = localeCalculateDate(biz_date('Y-m-d'), 0, 0, -1);
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "contact_id_b=$rID AND journal_id=12 AND closed='1' AND post_date>'$lYear'", 'id', ['journal_id','post_date','closed_date','terms','total_amount']);
        $total = $delta = 0;
        foreach ($rows as $row) {
            $dateDue  = getTermsDate($row['terms'], 'c', $row['post_date']);
            $datetime1= strtotime($row['post_date']);
            $datetime2= strtotime($dateDue);
            $datetime3= strtotime($row['closed_date']);
            $expDays  = ($datetime2 - $datetime1) / (60*60*24);
            $lateDays = ($datetime3 - $datetime1) / (60*60*24);
            $delta   += $lateDays - $expDays;
            $total   += $row['total_amount'];
            msgDebug("\nPost_date = {$row['post_date']} and expected date = $dateDue and actual date = {$row['closed_date']} with delta = $delta and total = {$row['total_amount']}");
        }
        if (empty($rows)) { return msgAdd("No paid invoices this past year!"); }
        $avgSales= viewFormat($total / sizeof($rows), 'currency');
        $avgPmt  = number_format($delta / sizeof($rows), 1);
        msgAdd(sprintf($this->lang['payment_history_resp'], $terms, $avgSales, $avgPmt), 'info');
    }

    /**
     *
     * @param string $name - HTML name of the contacts history grid
     * @param integer $jID - PhreeBooks journal ID to set search criteria
     * @param integer $rID - Contact database record id
     * @return array - grid structure
     */
    private function dgHistory($name, $jID, $rID = 0)
    {
        $rows   = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post');
        $page   = clean('page', ['format'=>'integer','default'=>1], 'post');
        $sort   = clean('sort', ['format'=>'text',   'default'=>'post_date'],'post');
        $order  = clean('order',['format'=>'text',   'default'=>'desc'],     'post');
        $gID    = explode(":", getDefaultFormID($jID));
        $jSearch= in_array($jID, [3,4,9,10]) ? $jID : ($jID==6 ? '6,7' : '12,13');
        $sec4_10= in_array($jID, [3,4,9,10]) ? getUserCache('role', 'security', "j{$jID}_mgr") : 0;
        switch ($jID) {
            case  6: $jPmt = 20; $cmPmt = 17; break;
            case 12:
            default: $jPmt = 18; $cmPmt = 22; break;
        }
        $data = ['id'=>$name, 'strict'=>true, 'rows'=>$rows, 'page'=>$page, 'title'=>sprintf(lang('tbd_history'), lang('journal_id', $jID)),
            'attr'   => ['idField'=>'id','url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&type=$this->type&jID=$jID&rID=$rID"],
            'source' => [
                'tables' => ['journal_main'=>['table'=>BIZUNO_DB_PREFIX.'journal_main']],
                'filters' => [
                    'jID'  => ['order'=>99, 'hidden'=>true, 'sql'=>"journal_id IN ($jSearch)"],
                    'rID'  => ['order'=>99, 'hidden'=>true, 'sql'=>"contact_id_b='$rID'"]],
                'sort' => ['s0'=>['order'=>10, 'field'=>("$sort $order")]]],
            'columns' => [
                'id'        => ['order'=>0, 'field'=>"id",        'attr'=>['hidden'=>true]],
                'closed'    => ['order'=>0, 'field'=>"closed",    'attr'=>['hidden'=>true]],
                'journal_id'=> ['order'=>0, 'field'=>"journal_id",'attr'=>['hidden'=>true]],
                'bal_due'   => ['order'=>0, 'field'=>"id",'process'=>'paymentRcv','attr'=>['hidden'=>true]],
                'action'    => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'       => ['order'=>20,'icon'=>'edit',    'label'=>lang('edit'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'print'      => ['order'=>40,'icon'=>'print',   'label'=>lang('print'),
                            'events' => ['onClick'=>"var idx=jqBiz('#$name').datagrid('getRowIndex', idTBD); var jID=jqBiz('#$name').datagrid('getRows')[idx].journal_id; ('fitColumns', true); winOpen('phreeformOpen', 'phreeform/render/open&group={$gID[0]}:j'+jID+'&date=a&xfld=journal_main.id&xcr=equal&xmin=idTBD');"]],
                        'dates'      => ['order'=>50,'icon'=>'date',   'label'=>lang('delivery_dates'), 'hidden'=>$sec4_10>1?false:true,
                            'events' => ['onClick' => "windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"],
                            'display'=> "row.journal_id=='4' || row.journal_id=='10'"],
                        'purchase'   => ['order'=>80,'icon'=>'purchase','label'=>lang('fill_purchase'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=6&bizAction=inv');"],
                            'display'=> "row.closed=='0' && (row.journal_id=='3' || row.journal_id=='4')"],
                        'sale'       => ['order'=>80,'icon'=>'sales',   'label'=>lang('fill_sale'),
                            'events' => ['onClick' => "winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=12&bizAction=inv');"],
                            'display'=> "row.closed=='0' && (row.journal_id=='9' || row.journal_id=='10')"],
                        'payment'    => ['order'=>80,'icon'=>'payment', 'label'=>lang('payment'),
                            'events' => ['onClick' => "var cID=jqBiz('#id').val(); winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=0&jID=$jPmt&bizAction=inv&iID=idTBD&cID='+cID);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='6' || row.journal_id=='12')"],
                        'cmPmt'      => ['order'=>80,'icon'=>'payment', 'label'=>lang('payment'),
                            'events' => ['onClick' => "var cID=jqBiz('#id').val(); winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=0&jID=$cmPmt&bizAction=inv&iID=idTBD&cID='+cID);"],
                            'display'=> "row.closed=='0' && (row.journal_id=='7' || row.journal_id=='13')"]]],
                'invoice_num'   => ['order'=>10, 'field'=>"invoice_num",'label'=>pullTableLabel('journal_main', 'invoice_num', $jID),
                    'attr'  => ['width'=>125, 'sortable'=>true, 'resizable'=>true]],
                'purch_order_id'=> ['order'=>20, 'field'=>"purch_order_id",'label'=>pullTableLabel('journal_main', 'purch_order_id', $jID),
                    'attr'  => ['width'=>150, 'sortable'=>true, 'resizable'=>true]],
                'post_date'     => ['order'=>30, 'field' => "post_date", 'format'=>'date','label' => pullTableLabel('journal_main', 'post_date', $jID),
                    'attr'  => ['width'=>120,'align'=>'center', 'sortable'=>true, 'resizable'=>true]],
                'closed_date'   => ['order'=>40, 'field'=>"closed_date",'label'=>in_array($jID, [6, 12]) ? lang('paid') : lang('closed'),
                    'attr'  => ['width'=>120,'align'=>'center', 'sortable'=>true, 'resizable'=>true],
                    'events'=> ['formatter'=>"function(value,row) { return (row.closed=='1' && value!='') ? formatDate(value) : (row.bal_due ? formatCurrency(row.bal_due, false) : ''); }"]],
                'total_amount'  => ['order'=>50, 'field'=>"total_amount",'label'=>lang('total'), 'attr'=>['width'=>100, 'align'=>'right', 'sortable'=>true, 'resizable'=>true],
                    'events'=> ['formatter'=>"function(value,row) { return (row.journal_id==7 || row.journal_id==13) ? formatCurrency(-value, false) : formatCurrency(value, false); }"]]]];
        if (in_array($GLOBALS['myDevice'], ['mobile','tablet'])) { $data['columns']['closed_date']['attr']['hidden'] = true; }
        return $data;
    }
}