<?php
/*
 * PhreeBooks Totals - ACH total checked
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
 * @filesource /controllers/phreebooks/totals/achTotal/achTotal.php
 */

namespace bizuno;

class achTotal
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'achTotal';
    public $required = true;
    public $settings;
    public $lang     = ['title'=>'Total (ACH)',
        'description'=> 'This method calculates the total for ACH payments to vendors in Bill Pay.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'ttl','journals'=>'[20]','gl_account'=>getModuleCache('phreebooks','settings', 'vendors', 'gl_cash_ach'),'order'=>99];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
     }

    /**
     *
     * @return type
     */
    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_account']]], // set in phreebooks settings
            'order'     => ['label'=>lang('order'),'options'=>['width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order'],'readonly'=>true]]];
    }

    /**
     * Renders the HTML for this method
     * @param array $output - running output buffer
     * @param array $data - source data
     */
    public function render($data=[])
    {
        $this->fields = [
            'totals_achTotal_id'  => ['attr'=>['type'=>'hidden']],
            'totals_achTotal_desc'=> ['attr'=>['type'=>'hidden']],
            'totals_achTotal_txid'=> ['attr'=>['type'=>'hidden']],
            'ach_gl_acct_id'      => ['label'=>lang('gl_account'),'attr'=>['value'=>$this->settings['gl_account'],'readonly'=>true]],
            'totals_achTotal_opt' => ['icon'=>'settings', 'size'=>'small','events'=>['onClick'=>"jqBiz('#totals_achTotal_div').toggle('slow');"]],
            'achTotal_amount'     => ['label'=>lang('total'),     'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]];
        if (isset($data['items'])) { foreach ($data['items'] as $row) { // fill in the data if available
            if ($row['gl_type'] == $this->settings['gl_type']) {
                $this->fields['totals_achTotal_id']['attr']['value']  = isset($row['id']) ? $row['id'] : 0;
                $this->fields['totals_achTotal_desc']['attr']['value']= isset($row['description']) ? $row['description'] : '';
                $this->fields['totals_achTotal_txid']['attr']['value']= isset($row['trans_code']) ? $row['trans_code'] : '';
                $this->fields['ach_gl_acct_id']['attr']['value']      = $row['gl_account'];
                $this->fields['achTotal_amount']['attr']['value']     = $row['credit_amount'] + $row['debit_amount'];
            }
        } }
        $hide = !empty($this->hidden) ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'."\n";
        $html .= html5('totals_achTotal_id',  $this->fields['totals_achTotal_id']);
        $html .= html5('totals_achTotal_desc',$this->fields['totals_achTotal_desc']);
        $html .= html5('totals_achTotal_txid',$this->fields['totals_achTotal_txid']);
        $html .= html5('achTotal_amount',     $this->fields['achTotal_amount']);
        $html .= html5('',                    $this->fields['totals_achTotal_opt']);
        $html .= "</div>\n";
        $html .= '<div id="totals_achTotal_div" style="display:none" class="layout-expand-over">'."\n";
        $html .= html5('ach_gl_acct_id',       $this->fields['ach_gl_acct_id'])."\n";
        $html .= "</div>\n";
        htmlQueue("function totals_achTotal(begBalance) {
    var newBalance = begBalance;
    bizNumSet('achTotal_amount', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
