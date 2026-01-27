<?php
/*
 * PhreeBooks Totals - Subtotal class
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
 * @version    7.x Last Update: 2025-07-07
 * @filesource /controllers/phreebooks/totals/subtotal/subtotal.php
 */

namespace bizuno;

class subtotal
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'subtotal';
    public $hidden   = false;
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'Order Subtotal',
        'description'=> 'This method calculates the order total of all item rows irregardless of sort order position. This option is used for order operations, i.e. Customer Sales and Vendor Purchases, the order is fixed at zero.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'sub','journals'=>'[3,4,6,7,9,10,12,13,15,16,19,21]','order'=>0];
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
        $fields= ['totals_subtotal'=>['label'=>lang('subtotal'),'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]];
        $hide  = $this->hidden ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'.html5('totals_subtotal',$fields['totals_subtotal']).html5('',['icon'=>'blank','size'=>'small'])."</div>\n";
        htmlQueue("function totals_subtotal(begBalance) {
    taxRunning = 0;
    var newBalance = begBalance;
    var rowData    = jqBiz('#dgJournalItem').edatagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        var amount = roundCurrency(parseFloat(rowData.rows[rowIndex].total));
        if (!isNaN(amount)) newBalance += amount;
    }
    bizNumSet('totals_subtotal', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
