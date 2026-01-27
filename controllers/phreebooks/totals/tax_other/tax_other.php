<?php
/*
 * PhreeBooks Totals - Tax Other - generic tax collection independent of authority
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
 * @version    7.x Last Update: 2026-01-27
 * @filesource /controllers/phreebooks/totals/tax_other/tax_other.php
 */

namespace bizuno;

class tax_other
{
    public $code     = 'tax_other';
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $hidden   = false;
    public $settings;
    public $fields;
    public $lang     = ['title'=>'Sales Tax (Amount)',
        'description'=> 'This method records generic tax collected to a specific account. This method is best suited when tax rates are calculated externally to Bizuno or vary by jurisdiction and you do not want to make a vendor authority/tax rate for every taxing region. Further processing will be necessary to separate tax collected by region to pay the appropriate authorities.',
        'tax_id_v' => 'Default sales tax rate for Purchases (Vendors)',
        'tax_id_c' => 'Default sales tax rate for Sales (Customers)',
        'extra_title'=> '(Amount)'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'glt','journals'=>'[9,10,12,13,19]','gl_account'=>getModuleCache('phreebooks','settings','vendors','gl_liability'),'order'=>75];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            'order'     => ['label'=>lang('order'),'options'=>['min'=>5,'max'=>95,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order']]]];
    }

    public function glEntry(&$main, &$item, &$begBal=0)
    {
        $tax_other= clean("totals_{$this->code}", ['format'=>'float','default'=>0], 'post');
        if ($tax_other == 0) { return; }
        $isoVals  = getModuleCache('phreebooks', 'currency', 'iso', getDefaultCurrency());
        $desc     = $this->lang['title'].': '.clean('primary_name_b', ['format'=>'text','default'=>''], 'post');
        $item[]   = [
            'id'           => clean("totals_{$this->code}_id", ['format'=>'float','default'=>0], 'post'),
            'ref_id'       => clean('id', 'integer', 'post'),
            'gl_type'      => $this->settings['gl_type'],
            'qty'          => 1,
            'description'  => $desc,
            'debit_amount' => in_array($main['journal_id'], [3,4, 6,13,20,21,22])       ? $tax_other : 0,
            'credit_amount'=> in_array($main['journal_id'], [7,9,10,12,14,16,17,18,19]) ? $tax_other : 0,
            'gl_account'   => clean("totals_{$this->code}_gl", ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
            'tax_rate_id'  => 0,
            'post_date'    => $main['post_date']];
        $main['sales_tax'] += roundAmount($tax_other, $isoVals['dec_len']);
        $begBal += roundAmount($tax_other, $isoVals['dec_len']);
        msgDebug("\nTaxOther is returning balance = $begBal");
    }

    public function render($data)
    {
        $jID   = $data['fields']['journal_id']['attr']['value'];
        $type  = in_array($jID, [3,4,6,7,17,20,21]) ? 'v' : 'c';
        $this->fields = [
            'totals_tax_other_id' => ['label'=>'', 'attr'=>['type'=>'hidden']],
            'totals_tax_other_gl' => ['label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            'totals_tax_other_opt'=> ['icon'=>'settings','size'=>'small','events'=>['onClick'=>"jqBiz('#phreebooks_totals_tax_other').toggle('slow');"]],
            'totals_tax_other'    => ['label'=>lang("tax_rate_id_{$type}").' '.$this->lang['extra_title'], 'events'=>['onBlur'=>"totalUpdate('tax_other');"],
                'attr' => ['type'=>'currency','value'=>0]]];
        if (!empty($data['items'])) { foreach ($data['items'] as $row) { // fill in the data if available
            if ($row['gl_type'] == $this->settings['gl_type']) {
                $this->fields['totals_tax_other_id']['attr']['value'] = !empty($row['id']) ? $row['id'] : 0;
                $this->fields['totals_tax_other_gl']['attr']['value'] = $row['gl_account'];
                $this->fields['totals_tax_other']['attr']['value']    = $row['credit_amount'] + $row['debit_amount'];
            }
        } }
        $hide  = $this->hidden ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'."\n";
        $html .= html5('totals_tax_other_id', $this->fields['totals_tax_other_id']);
        $html .= html5('totals_tax_other',    $this->fields['totals_tax_other']);
        $html .= html5('',                    $this->fields['totals_tax_other_opt']);
        $html .= "</div>";
        $html .= '<div id="phreebooks_totals_tax_other" style="display:none" class="layout-expand-over">';
        $html .= html5('totals_tax_other_gl', $this->fields['totals_tax_other_gl']);
        $html .= "</div>";
        htmlQueue("function totals_tax_other(begBalance) {
    var newBalance = begBalance;
    var salesTax = parseFloat(bizNumGet('totals_tax_other'));
    bizNumSet('totals_tax_other', salesTax);
    newBalance += salesTax;
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen= parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
        return $html;
    }
}
