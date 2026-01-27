<?php
/*
 * Phreebooks Totals - ACH Ending Balance
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
 * @filesource /controllers/phreebooks/totals/achBalEnd/achBalEnd.php
 */

namespace bizuno;

class achBalEnd
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'achBalEnd';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'ACH Ending Balance',
        'description'=> 'This calculates the ending balance for ACH bill pay.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'','journals'=>'[20]','order'=>99];
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
        $fields= ['totals_achBalEnd'=>['label'=>$this->lang['title'],'attr'=>['type'=>'currency','value'=>'0','readonly'=>'readonly']]];
        $html  = '<div style="text-align:right">'.html5('totals_achBalEnd',$fields['totals_achBalEnd']).html5('',['icon'=>'blank','size'=>'small'])."</div>\n";
        htmlQueue("function totals_achBalEnd(begBalance) {
    var newBalance = begBalance;
    var begBal = bizNumGet('totals_achBalBeg');
    bizNumSet('totals_achBalEnd', begBal - newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
