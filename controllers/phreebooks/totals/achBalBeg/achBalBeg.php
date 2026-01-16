<?php
/*
 * PhreeBooks totals - ACH Beginning Balance
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
 * @filesource /controllers/phreebooks/totals/achBalBeg/achBalBeg.php
 */

namespace bizuno;

class achBalBeg
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'achBalBeg';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'ACH Beginning Balance',
        'description'=> 'This calculates the beginning balance for ACH bill pay.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[20]','order'=>0];
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
        $banks   = [];
        $defMap  = '';
        $profiles= getModuleCache('phreebooks', 'banks');
        $defACH  = getModuleCache('phreebooks','settings','vendors','gl_cash_ach');
        foreach ($profiles as $profile) {
            if ($profile['gl_acct']==$defACH) { $defMap = $profile['mapID']; }
            $banks[] = ['id'=>$profile['mapID'],'text'=>$profile['title']];
        }
        $this->fields = [
            'achMapID'=>['label'=>lang('profile'),'values'=>$banks,'attr'=>['type'=>'select','value'=>$defMap],
                'events'=>['onChange'=>"bizTextSet('ach_gl_acct_id', newVal); totalsGetAchBegBal(bizDateGet('post_date'));"]],
            'achBalBeg' =>['label'=>$this->lang['title'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']]];
        $html = '<div style="text-align:right">'.
            html5('totals_achMapID',$this->fields['achMapID']).
            html5('totals_achBalBeg', $this->fields['achBalBeg']).
            html5('', ['icon'=>'blank', 'size'=>'small'])."</div>\n";
        htmlQueue("function totals_achBalBeg(begBalance) { return cleanCurrency(bizTextGet('totals_achBalBeg')); }
function totalsGetAchBegBal(postDate) {
    var rID      = jqBiz('#id').val();
    var achMapID = bizSelGet('totals_achMapID');
    jqBiz.ajax({
        url: '".BIZUNO_URL_AJAX."&bizRt=phreebooks/main/journalBalance&rID='+rID+'&postDate='+postDate+'&achMapID='+achMapID,
        success: function (json) {
            processJson(json);
            if (typeof json.balance !== 'undefined') { bizNumSet('totals_achBalBeg', json.balance); bizTextSet('ach_gl_acct_id', json.gl_account); }
            else { alert('Balance could not be found!'); }
            achTotalUpdate();
       }
    });
}", 'jsHead');
        htmlQueue("totalsGetAchBegBal(bizDateGet('post_date'));", 'jsReady');
        return $html;
    }
}
