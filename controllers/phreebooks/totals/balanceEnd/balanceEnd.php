<?php
/*
 * Phreebooks Totals - Ending balance
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/phreebooks/totals/balanceEnd/balanceEnd.php
 */

namespace bizuno;

class balanceEnd {
    public $code      = 'balanceEnd';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;
    public $lang = ['title'=>'Ending Balance',
        'description'=> 'This calculates the ending balance for a given GL account to use for Bills. Typically used for banking transactions, Customer receipts and Vendor Payments. Order is fixed at 100, this should be the last total method.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[20,22]','order'=>99];
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
        $fields= ['totals_balanceEnd'=>['label'=>$this->lang['title'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']]];
        $html  = '<div style="text-align:right">'.html5('totals_balanceEnd',$fields['totals_balanceEnd']).html5('',['icon'=>'blank','size'=>'small'])."</div>\n";
        htmlQueue("function totals_balanceEnd(begBalance) {
    var newBalance = begBalance;
    var begBal = bizNumGet('totals_balanceBeg');
    bizNumSet('totals_balanceEnd', begBal - newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
