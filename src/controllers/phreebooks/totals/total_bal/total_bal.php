<?php
/*
 * PhreeBooks total method for balance after payments applied
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
 * @filesource /controllers/phreebooks/totals/total_bal/total_bal.php
 */

namespace bizuno;

class total_bal
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'total_bal';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'Total Balance',
        'description'=> 'This method calculates the total balance for a Point of Sale entry. The order is fixed and should remain the last total method (highest order position).'];

    public function __construct()
    {
        $this->settings= ['journals'=>'[19]','order'=>98];
     }

    /**
     * Sets the structure
     * @return array - method settings
     */
    public function settingsStructure()
    {
        return [
            'journals'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'order'   => ['label'=>lang('order'),'options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    /**
     * Renders the HTML for this method
     * @param array $output - running output buffer
     * @return modified $output
     */
    public function render()
    {
        $html = '<div style="text-align:right">'."\n";
        $html.= html5("totals_{$this->code}", ['label'=>$this->lang['total_pmt'],'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]);
        $html.= "</div>\n";
        htmlQueue("function totals_total_pmt(begBalance) { bizNumSet('total_pmt', begBalance); return begBalance; }", 'jsHead');
        return $html;
    }
}
