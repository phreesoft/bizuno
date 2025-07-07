<?php
/*
 * PhreeBooks Totals - Discount at order level
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
 * @filesource /controllers/phreebooks/totals/discount/discount.php
 */

namespace bizuno;

class discount
{
    public $code     = 'discount';
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $settings;
    public $lang     = ['title'=>'Discount (By Order)',
        'label'      => 'Discount',
        'description'=> 'This method takes a discount based on the running total of the order. Typically used for order transactions, Customer Sales and Vendor Purchases.'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->jID     = clean('jID', ['format'=>'integer', 'default'=>2], 'get');
        $type          = in_array($this->jID, [3,4,6,7,21]) ? 'vendors' : 'customers';
        $this->settings= ['gl_type'=>'dsc','journals'=>'[3,4,6,7,9,10,12,13,19,21]','gl_account'=>getModuleCache('phreebooks','settings',$type,'gl_discount'),'order'=>30];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_account']]],
            'order'     => ['label'=>lang('order'),'options'=>['min'=>5,'max'=>95,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order']]]];
    }

    public function glEntry(&$main, &$item, &$begBal=0)
    {
        $discount= clean("totals_{$this->code}", ['format'=>'float','default'=>0], 'post');
        if ($discount == 0) { return; }
        $desc    = $this->lang['title'].': '.clean('primary_name_b', ['format'=>'text','default'=>''], 'post');
        $item[]  = [
            'id'           => clean("totals_{$this->code}_id", ['format'=>'float','default'=>0], 'post'),
            'ref_id'       => clean('id', 'integer', 'post'),
            'gl_type'      => $this->settings['gl_type'],
            'qty'          => '1',
            'description'  => $desc,
            'debit_amount' => in_array($this->jID, [7, 9,10,12,19]) ? $discount : 0,
            'credit_amount'=> in_array($this->jID, [3, 4, 6,13,21]) ? $discount : 0,
            'gl_account'   => clean("totals_{$this->code}_gl", ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
            'post_date'    => $main['post_date']];
        if (empty($main['discount'])) { $main['discount'] = 0; }
        $main['discount'] += $discount;
        $begBal -= $discount;
        msgDebug("\n{$this->code} is returning balance = ".$begBal);
    }

    public function render($data=[])
    {
        $this->fields = [
          "totals_{$this->code}_id" => ['attr'=>['type'=>'hidden']],
          "totals_{$this->code}_gl" => ['label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
          "totals_{$this->code}_opt"=> ['icon'=>'settings', 'size'=>'small','events'=>['onClick'=>"jqBiz('#phreebooks_totals_".$this->code."').toggle('slow');"]],
          "totals_{$this->code}_pct"=> ['label'=>lang('percent'),'lblStyle'=>['min-width'=>'60px'],'options'=>['width'=>60,'value'=>0],'attr'=>['type'=>'float','size'=>5],
              'events'=>['onBlur'=>"discountType='pct'; totalUpdate('Discount percent onBlur');"]],
          "totals_{$this->code}"    => ['label'=>$this->lang['label'],'lblStyle'=>['min-width'=>'60px'],'attr'=>['type'=>'currency','value'=>0],
            'events' => ['onBlur'=>"discountType='amt'; totalUpdate('Discount total onBlur');"]]];
        if (isset($data['items'])) {
            foreach ($data['items'] as $row) { // fill in the data if available
                if ($row['gl_type'] == $this->settings['gl_type']) {
                    msgDebug("\nPhreeBooks Totals Discount, found dsc row = ".print_r($row, true));
                    $this->fields["totals_{$this->code}_id"]['attr']['value']= isset($row['id']) ? $row['id'] : 0;
                    $this->fields["totals_{$this->code}_gl"]['attr']['value']= $row['gl_account'];
                    $this->fields["totals_{$this->code}"]['attr']['value']   = $row['credit_amount'] + $row['debit_amount'];
                }
            }
        }
        $hide = !empty($this->hidden) ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'."\n";
        $html .= html5("totals_{$this->code}_id", $this->fields["totals_{$this->code}_id"]);
        $html .= html5("totals_{$this->code}_pct",$this->fields["totals_{$this->code}_pct"]);
        $html .= html5("totals_{$this->code}",    $this->fields["totals_{$this->code}"]);
        $html .= html5('',                        $this->fields["totals_{$this->code}_opt"]);
        $html .= "</div>\n";
        $html .= '<div id="phreebooks_totals_'.$this->code.'" style="display:none" class="layout-expand-over">'."\n";
        $html .= html5("totals_{$this->code}_gl", $this->fields["totals_{$this->code}_gl"])."\n";
        $html .= "</div>\n";
        htmlQueue("function totals_{$this->code}(begBalance) {
    var newBalance= begBalance;
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen    = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    if (discountType=='pct') {
        var percent = parseFloat(jqBiz('#totals_{$this->code}_pct').val());
        if (isNaN(percent)) { percent = 0; }
        var discount= roundCurrency(newBalance * (percent / 100));
        bizNumSet('totals_{$this->code}', discount);
        discountType= '';
    } else { // amt
        var discount= bizNumGet('totals_{$this->code}');
        if (isNaN(discount)) { discount = 0; }
        var percent = begBalance ? 100 * (1 - ((begBalance - discount) / begBalance)) : 0;
        percent     = percent.toFixed(decLen+2);
        bizNumSet('totals_{$this->code}_pct', percent);
        bizNumSet('totals_{$this->code}', discount);
    }
    newBalance -= discount;
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
        return $html;
    }
}
