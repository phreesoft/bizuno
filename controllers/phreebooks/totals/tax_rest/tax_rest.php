<?php
/*
 * PhreeBooks Total method to calculate sales tax through the PhreeSoft RESTful API
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
 * @filesource /controllers/phreebooks/totals/tax_rest/tax_rest.php
 */

namespace bizuno;

class tax_rest
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'tax_rest';
    public $hidden   = false;
    public $settings;
    public $fields;
    public $lang     = ['title'=>'Sales Tax (REST)',
//      'label' => 'Sales Tax (REST)',
        'extra_title' => '(REST)',
        'description' => 'This method uses the PhreeSoft RESTful API interface to calculate sales tax based on the postal code.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->settings= ['gl_type'=>'tbt','journals'=>'[9,10,12,13,19,21]','gl_account'=>getModuleCache('phreebooks','settings','vendors','gl_liability'),'order'=>75];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
        $this->fields  = [
            $this->code        => ['label'=>pullTableLabel('journal_main', 'tax_rate_id').' '.$this->lang['extra_title'],
                'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']],
            $this->code.'_text'=> ['attr' =>['value'=>'textTBD','size'=>16,'readonly'=>'readonly']],
            $this->code.'_amt' => ['attr' =>['value'=>'amtTBD', 'size'=>10,'style'=>'text-align:right','readonly'=>'readonly']]];
    }

    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>'tbt']], // $this->settings['gl_type']
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            'order'     => ['label'=>lang('order'),'options'=>['min'=>5,'max'=>95,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order']]]];
    }

    /**
     *
     * @param array $main
     * @param type $item
     * @param type $begBal
     */
    public function glEntry(&$main, &$item, &$begBal=0)
    {
        $tax_rest= clean("totals_{$this->code}", ['format'=>'float','default'=>0], 'post');
        if ($tax_rest==0 && in_array($main['journal_id'], [3,4,6]) && getModuleCache('phreebooks', 'settings', 'vendors', 'rm_item_ship')) { return; } // this will discard tax exempt for customers
        $isoVals = getModuleCache('phreebooks', 'currency', 'iso', getDefaultCurrency());
        $desc    = "title:".$this->lang['title'].'-'.clean('primary_name_b', ['format'=>'text','default'=>''], 'post');
        $desc   .= ";exempt:".clean('tax_exempt', 'integer', 'post');
        $item[]  = [
            'id'           => clean("totals_{$this->code}_id", ['format'=>'float','default'=>0], 'post'),
            'ref_id'       => clean('id', 'integer', 'post'),
            'gl_type'      => $this->settings['gl_type'],
            'qty'          => 1,
            'description'  => $desc,
            'debit_amount' => in_array($main['journal_id'], [3,4, 6,13,20,21,22])       ? $tax_rest : 0,
            'credit_amount'=> in_array($main['journal_id'], [7,9,10,12,14,16,17,18,19]) ? $tax_rest : 0,
            'gl_account'   => clean("totals_{$this->code}_gl", ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
            'post_date'    => $main['post_date']];
        $main['sales_tax'] += roundAmount($tax_rest, $isoVals['dec_len']);
        $begBal += roundAmount($tax_rest, $isoVals['dec_len']);
        msgDebug("\ntax_rest is returning balance = $begBal");
    }

    /**
     *
     * @param type $output
     * @param type $data
     */
    public function render($data=[])
    {
        $taxRate= 0;
        $jID    = $data['fields']['journal_id']['attr']['value'];
        $type   = in_array($jID, [3,4,6,7,17,20,21]) ? 'v' : 'c';
        $this->fields = [
            $this->code.'_id'   => ['attr'=>['type'=>'hidden']],
            'tax_exempt'        => ['label'=>lang('tax_exempt'),'events'=>['onChange'=>"totalUpdate('tax_exempt');"],'attr'=>['type'=>'checkbox','value'=>1]],
            $this->code.'_gl'   => ['label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            $this->code.'_opt'  => ['icon'=>'settings','size'=>'small','events'=>['onClick'=>"jqBiz('#phreebooks_$this->code').toggle('slow');"]],
            "totals_$this->code"=> ['label'=>pullTableLabel('journal_main','tax_rate_id',$type).' '.$this->lang['extra_title'],
                'attr' => ['type'=>'currency','value'=>0]]];
        msgDebug("\nTotal class: $this->code method: render working with items: ".print_r($data['items'], true));
        $present = false;
        for ($i=0; $i<sizeof($data['items']); $i++) { // fill in the data if available
            if ($data['items'][$i]['gl_type'] <> $this->settings['gl_type']) { continue; }
            $settings = explode(";", $data['items'][$i]['description']);
            foreach ($settings as $setting) {
                $value = explode(":", $setting);
                if ($value[0]=='exempt') {
                    $present=true;
                    if (!empty($value[1])) { $this->fields['tax_exempt']['attr']['checked'] = 'checked'; }
                    // commented out the line below as this would change the tax rate when opening an old order
//                  else              { $taxRate = $this->getTaxRate($data['fields']['postal_code_s']['attr']['value']); }
                }
            }
            $this->fields[$this->code.'_id']['attr']['value']   = !empty($data['items'][$i]['id']) ? $data['items'][$i]['id'] : 0;
            $this->fields[$this->code.'_gl']['attr']['value']   = $data['items'][$i]['gl_account'];
            $this->fields["totals_$this->code"]['attr']['value']= $data['items'][$i]['credit_amount'] + $data['items'][$i]['debit_amount'];
        }
        // for legacy if the gl entry was not there then there was no tax, set the exempt flag as default
        if (!$present) { $this->fields['tax_exempt']['attr']['checked'] = 'checked'; }
        $hide  = $this->hidden ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'."\n";
        $html .= html5('tax_exempt',        $this->fields['tax_exempt'])."<br />";
        $html .= html5($this->code.'_id',   $this->fields[$this->code.'_id']);
        $html .= html5("totals_$this->code",$this->fields["totals_$this->code"]);
        $html .= html5('',                  $this->fields[$this->code.'_opt']);
        $html .= "</div>";
        $html .= '<div id="phreebooks_'.$this->code.'" style="display:none" class="layout-expand-over">';
        $html .= html5($this->code.'_gl', $this->fields[$this->code.'_gl']);
        $html .= "</div>";
        htmlQueue("
function totals_$this->code(begBalance) {
    var tax_rest= 0;
    var newBalance= begBalance;
    var state   = bizTextGet('state_s');
    var zip     = bizTextGet('postal_code_s');
    var country = bizSelGet('country_s');
    var ship    = bizNumGet('freight');
    var total   = bizNumGet('total_amount');
    var curTax  = bizNumGet('totals_$this->code');
    if (0==bizCheckBoxGet('tax_exempt') && zip !=='' && begBalance !== 0) {
        jqBiz.ajax({ url:bizunoAjax+'&bizRt=phreebooks/restfulTax/getTaxRate&state='+state+'&postal_code='+zip+'&shipping='+ship+'&total='+begBalance, async:false, success:function(resp) {
        tax_rest = resp;
 } });
    } else { tax_rest = 0; }
    newBalance   += parseFloat(tax_rest);
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen    = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    bizNumSet('totals_$this->code', tax_rest);
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
//        htmlQueue("bizTextChange('state_s', tax_rest_get); bizTextChange('postal_code_s', tax_rest_get); bizNumChange('totals_subtotal',tax_rest_get); bizNumChange('freight', tax_rest_get);", 'jsReady');
        return $html;
    }
}
