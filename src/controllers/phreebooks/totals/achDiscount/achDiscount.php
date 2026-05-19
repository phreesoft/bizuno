<?php
/*
 * PhreeBooks Totals - ACH Discounts by checkbox total
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/phreebooks/totals/achDiscount/achDiscount.php
 */

namespace bizuno;

class achDiscount
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'achDiscount';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'ACH Discount',
        'label'      => 'Discount',
        'description'=> 'This method will total the discounts for vendor payments, if taken.'];

    public function __construct()
    {
        $this->settings= ['gl_type'=>'dsc','journals'=>'[20]','gl_account'=>getModuleCache('phreebooks','settings','vendors','gl_discount'),'order'=>30];
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

    public function render($data=[])
    {
        $this->fields = [
            'totals_achDiscount_gl' => ['label'=>lang('gl_account'),'attr'=>['value'=>$this->settings['gl_account'],'readonly'=>true]],
            'totals_achDiscount_opt'=> ['icon' =>'settings','size'=>'small','events'=>['onClick'=>"jqBiz('#totals_achDiscount_div').toggle('slow');"]],
            'totals_achDiscount'    => ['label'=>lang('discount'),'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly'],'events'=>['onClick'=>"discountType='amt'; totalUpdate();"]]];
        msgDebug("\nSettings for achDiscountChk = ".print_r($this->settings, true));
        if (isset($data['items'])) { foreach ($data['items'] as $row) { // fill in the data if available
            if ($row['gl_type'] == $this->settings['gl_type']) {
                msgDebug("\nGL TYPE MATCH "); // never hits this loop as the dsc row has been removed
                $this->fields['totals_achDiscount_id']['attr']['value'] = $row['id'];
                $this->fields['totals_achDiscount_gl']['attr']['value'] = $row['gl_account'];
            }
        } }
        $html  = '<div style="text-align:right">';
        $html .= html5('totals_achDiscount', $this->fields['totals_achDiscount']);
        $html .= html5('',                   $this->fields['totals_achDiscount_opt']);
        $html .= "</div>";
        $html .= '<div id="totals_achDiscount_div" style="display:none" class="layout-expand-over">';
        $html .= html5('totals_achDiscount_gl', $this->fields['totals_achDiscount_gl']);
        $html .= "</div>";
        htmlQueue("function totals_achDiscount(begBalance) {
    var totalDisc = 0;
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<rowData.rows.length; i++) {
        if (rowData.rows[i].is_ach==1 && rowData.rows[i]['checked']) {
            var discount = parseFloat(rowData.rows[i].discount);
            if (!isNaN(discount)) totalDisc += discount;
        }
    }
    bizNumSet('totals_achDiscount', totalDisc);
    var newBalance = begBalance - totalDisc;
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen= parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
        return $html;
    }
}
