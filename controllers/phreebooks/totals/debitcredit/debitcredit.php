<?php
/*
 * PhreeBooks Totals - Debits/Credits total
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
 * @filesource /controllers/phreebooks/totals/debitcredit/debitcredit.php
 */

namespace bizuno;

class debitCredit
{
    public $code      = 'debitcredit';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;
    public $settings;
    public $lang      = ['title'=>'Debit/Credit Totals',
        'description'=> 'This method calculates the total debits and credits for general journal entries. This method is only used for General Journal entries. Order is fixed at zero. This should be the first total method.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[2]','order'=>0];
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

    public function render() {
        $this->fields = [
            'totals_debit' =>['label'=>lang('total_debits'), 'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']],
            'totals_credit'=>['label'=>lang('total_credits'),'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]];
        $html  = '<div style="text-align:right">'."
    ".html5('totals_debit', $this->fields['totals_debit'])."<br />
    ".html5('totals_credit',$this->fields['totals_credit'])."</div>\n";
        htmlQueue("function totals_debitcredit() {
    var debitAmount = 0;
    var creditAmount= 0;
    var rows = jqBiz('#dgJournalItem').datagrid('getRows');
    for (var rowIndex=0; rowIndex<rows.length; rowIndex++) {
        debit = roundCurrency(parseFloat(rows[rowIndex].debit_amount));
        if (isNaN(debit)) debit = 0;
        debitAmount  += debit;
        credit= roundCurrency(parseFloat(rows[rowIndex].credit_amount));
        if (isNaN(credit)) credit = 0;
        creditAmount += credit;
    }
    bizNumSet('totals_debit', debitAmount);
    bizNumSet('totals_credit',creditAmount);
    return roundCurrency(debitAmount - creditAmount);
}", 'jsHead');
        return $html;
    }
}
