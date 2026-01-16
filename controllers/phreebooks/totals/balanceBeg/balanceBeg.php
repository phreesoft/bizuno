<?php
/*
 * PhreeBooks totals - Beginning balance
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
 * @version    7.x Last Update: 2026-01-16
 * @filesource /controllers/phreebooks/totals/balanceBeg/balanceBeg.php
 */

namespace bizuno;

class balanceBeg
{
    public $code      = 'balanceBeg';
    public $moduleID  = 'phreebooks';
    public $methodDir = 'totals';
    public $required  = true;
    public $settings;
    public $lang      = ['title'=>'Beginning Balance',
        'description'=> 'This method pulls the beginning balance for a given GL account to use for Bills. Typically used for banking transactions, Customer receipts and Vendor Payments. Order is fixed at zero, this should be the first total method.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[20,22]','order'=>0];
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
        // ajax request with GL acct/post_date to get starting balance
        // need to modify post_date and gl_account field to call javascript call
        $this->fields = ['totals_balanceBeg'=>['label'=>$this->lang['title'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']]];
        $html = '<div style="text-align:right">'."\n"
                .html5('totals_balanceBeg',$this->fields['totals_balanceBeg']).html5('', ['icon'=>'blank', 'size'=>'small'])."</div>\n";
        htmlQueue("function totals_balanceBeg(begBalance) { return cleanCurrency(bizTextGet('totals_balanceBeg')); }
function totalsGetBegBalance(postDate) {
    var rID      = jqBiz('#id').val();
    var glAccount= bizSelGet('gl_acct_id');
    jqBiz.ajax({
        url: '".BIZUNO_URL_AJAX."&bizRt=phreebooks/main/journalBalance&rID='+rID+'&postDate='+postDate+'&glAccount='+glAccount,
        success: function (json) {
            processJson(json);
            if (typeof json.balance !== 'undefined') { bizNumSet('totals_balanceBeg', json.balance); }
            else { alert('Balance could not be found!'); }
            totalUpdate('balanceBeg');
       }
    });
}", 'jsHead');
        htmlQueue("totalsGetBegBalance(bizDateGet('post_date'));", 'jsReady');
        return $html;
    }
}
