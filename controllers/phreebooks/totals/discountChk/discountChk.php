<?php
/*
 * PhreeBooks Totals - Discounts by checkbox total
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
 * @version    7.x Last Update: 2025-09-22
 * @filesource /controllers/phreebooks/totals/discountChk/discountChk.php
 */

namespace bizuno;

class discountChk
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'discountChk';
    public $required = true;
    public $jID;
    public $settings;
    public $fields;
    public $lang     = ['title'=>'Discount (Line Checked)',
        'label'      => 'Discount',
        'description'=> 'This method will apply a discount based on each line that is checked. Typically used for banking transactions, Customer Receipts and Vendor Payments.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->jID     = clean('jID', ['format'=>'cmd', 'default'=>'2'], 'get');
        $type          = in_array($this->jID, [17,20,21]) ? 'vendors' : 'customers';
        $this->settings= ['gl_type'=>'dsc','journals'=>'[17,18,20,22]','gl_account'=>getModuleCache('phreebooks','settings',$type,'gl_discount'),'order'=>30];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_account']]],
            'order'     => ['label'=>lang('order'),'options'=>['min'=>5,'max'=>95,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order']]]];
    }

    public function glEntry(&$main, &$items, &$begBal=0)
    {
        $totalDisc = 0;
        $rows = clean('item_array', 'json', 'post');
        foreach ($rows as $row) {
            $discount = clean($row['discount'], 'float');
            if ($discount == 0) { continue; }
            // need to bump up total paid to include discount
            foreach ($items as $key => $item) {
                if (!isset($item['item_ref_id'])) { continue; }
                if ($item['item_ref_id'] == $row['item_ref_id']) { // bump up the pmt to include the discount
                    if (in_array($this->jID, [17,18])) { $items[$key]['credit_amount'] += $discount; }
                    if (in_array($this->jID, [20,22])) { $items[$key]['debit_amount']  += $discount; }
                    break;
                }
            }
            $items[] = [
                'ref_id'       => clean('id', 'integer', 'post'),
                'item_ref_id'  => $row['item_ref_id'],
                'gl_type'      => $this->settings['gl_type'],
                'qty'          => '1',
                'description'  => $row['description'],
                'debit_amount' => in_array($this->jID, [17,18]) ? $discount : 0,
                'credit_amount'=> in_array($this->jID, [20,22]) ? $discount : 0,
                'gl_account'   => clean('totals_discount_gl', ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
                'tax_rate_id'  => 0,
                'post_date'    => $main['post_date']];
            $totalDisc += $discount;
        }
        $main['discount'] = $totalDisc;
//        $begBal += $totalDisc; // don't add this as it has already been included
        msgDebug("\nDiscountChk is returning total discount = ".$totalDisc);
    }

    public function render($data=[])
    {
        $this->fields = [
            'totals_discount_gl' => ['label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            'totals_discount_opt'=> ['icon' =>'settings','size'=>'small','events'=>['onClick'=>"jqBiz('#phreebooks_totals_discount').toggle('slow');"]],
            'totals_discount'    => ['label'=>lang('discount'),'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly'],'events'=>['onClick'=>"discountType='amt'; totalUpdate('discountChk');"]]];
        msgDebug("\nSettings for discountChk = ".print_r($this->settings, true));
        if (isset($data['items'])) { foreach ($data['items'] as $row) { // fill in the data if available
            if ($row['gl_type'] == $this->settings['gl_type']) {
                msgDebug("\nGL TYPE MATCH "); // never hits this loop as the dsc row has been removed
                $this->fields['totals_discount_id']['attr']['value'] = $row['id'];
                $this->fields['totals_discount_gl']['attr']['value'] = $row['gl_account'];
            }
        } }
        $html  = '<div style="text-align:right">';
        $html .= html5('totals_discount',    $this->fields['totals_discount']);
        $html .= html5('',                   $this->fields['totals_discount_opt']);
        $html .= "</div>";
        $html .= '<div id="phreebooks_totals_discount" style="display:none" class="layout-expand-over">';
        $html .= html5('totals_discount_gl', $this->fields['totals_discount_gl']);
        $html .= "</div>";
        htmlQueue("function totals_discountChk(begBalance) {
    var totalDisc = 0;
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<rowData.rows.length; i++) {
        if (rowData.rows[i].is_ach!=1 && rowData.rows[i]['checked']) {
            var discount = parseFloat(rowData.rows[i].discount);
            if (!isNaN(discount)) totalDisc += discount;
        }
    }
    bizNumSet('totals_discount', totalDisc);
    var newBalance = begBalance - totalDisc;
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen= parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
        return $html;
    }
}
