<?php
/*
 * PhreeBooks Totals - Balance
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
 * @version    7.x Last Update: 2025-07-07
 * @filesource /controllers/phreebooks/totals/balance/balance.php
 */

namespace bizuno;

class balance
{
    public $code      = 'balance';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;
    public $settings;
    public $lang = ['title'=>'Debit/Credit Difference',
        'description'=> 'This method calculates the total difference between the total debits and credits for general journal entries. This method is only used on General ledger transactions and should be the highest ordered total method.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[2]','order'=>99];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type' => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'position'=>'after','options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    public function render()
    {
        $html  = '<div style="text-align:right">'."\n";
        $html .= html5('total_balance',['label'=>$this->lang['title'],'attr'=>['type'=>'currency','size'=>15,'value'=>0]]);
        $html .= html5('total_amount', ['attr'=>['type'=>'hidden','value'=>0]]);
        $html .= "</div>\n";
        htmlQueue("function totals_balance(begBalance) {
    var newBalance = begBalance;
    if (newBalance == 0) jqBiz('#total_balance').css({color:'#000000'}); else jqBiz('#total_balance').css({color:'#FF0000'});
    bizNumSet('total_balance', newBalance);
    var totalDebit = cleanCurrency(bizNumGet('totals_debit'));
    jqBiz('#total_amount').val(totalDebit);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
