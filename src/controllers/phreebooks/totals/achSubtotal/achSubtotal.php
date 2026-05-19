<?php
/*
 * PhreeBooks Totals - ACH Subtotal by checkbox
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
 * @filesource /controllers/phreebooks/totals/achSubtotal/achSubtotal.php
 */

namespace bizuno;

class achSubtotal
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'achSubtotal';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'ACH Subtotal',
        'description'=> 'This method calculates the subtotal for ACH payments to vendors.',
        'subtotal'   => 'Subtotal'];

    public function __construct()
    {
        $this->settings= ['gl_type'=>'sub','journals'=>'[20]','order'=>0];
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
            'totals_achSubtotal'    => ['label'=>$this->lang['subtotal'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']],
            'totals_achSubtotal_opt'=> ['icon'=>'blank','size'=>'small']];
        $html = '<div style="text-align:right">'
            .html5('totals_achSubtotal', $this->fields['totals_achSubtotal'])
            .html5('',                   $this->fields['totals_achSubtotal_opt'])."</div>\n";
        htmlQueue("function totals_achSubtotal(begBalance) {
    var newBalance = 0;
    var rowData = jqBiz('#dgJournalItem').datagrid('getData');
    for (var i=0; i<rowData.rows.length; i++) {
        if (rowData.rows[i].is_ach==1 && rowData.rows[i]['checked']) {
            var total   = parseFloat(rowData.rows[i].total);
            if (isNaN(total)) { total = 0; }
            var discount= parseFloat(rowData.rows[i].discount);
            if (isNaN(discount)) { discount = 0; }
            newBalance += total + discount;
        }
    }
    bizNumSet('totals_achSubtotal', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
