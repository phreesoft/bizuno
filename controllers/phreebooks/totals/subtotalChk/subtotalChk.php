<?php
/*
 * PhreeBooks Totals - Subtotal by checkbox
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
 * @version    7.x Last Update: 2025-07-23
 * @filesource /controllers/phreebooks/totals/subtotalChk/subtotalChk.php
 */

namespace bizuno;

class subtotalChk {
    public $code     = 'subtotalChk';
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $required = true;
    public $settings;
    public $fields;
    public $lang     = ['title'=>'Order Subtotal (checked)',
        'description'=> 'This method calculates the order total of all item rows that are checked irregardless of sort order position. This option is used for banking operations, i.e. Customer Receipts and Paying Bills, the order is fixed at zero.',
        'subtotal'   => 'Subtotal'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'sub','journals'=>'[17,18,20,22]','order'=>0];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type' => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    public function render()
    {
        $this->fields = [
            'totals_subtotal'    => ['label'=>$this->lang['subtotal'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']],
            'totals_subtotal_opt'=> ['icon'=>'blank','size'=>'small']];
        $html = '<div style="text-align:right">'
            .html5('totals_subtotal', $this->fields['totals_subtotal'])
            .html5('',                $this->fields['totals_subtotal_opt'])."</div>\n";
        htmlQueue("function totals_subtotalChk(begBalance) {
    var newBalance = 0;
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<rowData.rows.length; i++) {
        if (rowData.rows[i].is_ach!=1 && rowData.rows[i]['checked']) {
            var total   = parseFloat(rowData.rows[i].total);
            if (isNaN(total)) { total = 0; }
            var discount= parseFloat(rowData.rows[i].discount);
            if (isNaN(discount)) { discount = 0; }
            newBalance += total + discount;
        }
    }
    bizNumSet('totals_subtotal', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
