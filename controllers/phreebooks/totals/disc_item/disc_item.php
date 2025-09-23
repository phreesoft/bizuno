<?php
/*
 * PhreeBooks Total method to allow discounts at the item level
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
 * @version    7.x Last Update: 2025-09-22
 * @filesource /controllers/phreebooks/totals/disc_item/disc_item.php
 */

namespace bizuno;

class disc_item
{
    public $code     = 'disc_item';
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $jID;
    public $type;
    public $settings;
    public $lang     = ['title'=>'Discount (By Item)',
        'label'      => 'Item Discount',
        'description'=> 'This method allows discounts at the item level. It is used for order operations, i.e. Customer Sales and Vendor Purchases. NOTE: THIS METHOD REQUIRES THE CUSTOMER ITEM DISCOUNT EXTENSION!',
        'extra_title'=> '(By Item)'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->jID     = clean('jID', ['format'=>'cmd', 'default'=>2], 'get');
        $this->type    = in_array($this->jID, [3,4,6,7,21]) ? 'vendors' : 'customers';
        $this->settings= ['gl_type'=>'dsi','journals'=>'[3,4,6,7,9,10,12,13,19,21]','gl_account'=>getModuleCache('phreebooks','settings',$this->type,'gl_discount'),'order'=>30];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
    }

    /**
     *
     * @return array - the structure
     */
    public function settingsStructure()
    {
        return [
            'gl_type'   => ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_type']]],
            'journals'  => ['attr'=>['type'=>'hidden','value'=>$this->settings['journals']]],
            'gl_account'=> ['attr'=>['type'=>'hidden','value'=>$this->settings['gl_account']]],
            'order'     => ['label'=>lang('order'),'options'=>['min'=>5,'max'=>95,'width'=>100],'attr'=>['type'=>'spinner','value'=>$this->settings['order']]]];
    }

    /**
     * sets the journal_item record for this method
     * @param array $main - journal main record
     * @param array $item - journal item record
     * @param float $begBal - beginning balance used for calculation purposes
     * @return modified $item
     */
    public function glEntry(&$main, &$item, &$begBal=0)
    {
        $totalDisc= 0;
        $postItm  = clean('item_array', 'json', 'post');
        $isoVals  = getModuleCache('phreebooks', 'currency', 'iso', getDefaultCurrency());
        foreach ($postItm['rows'] as $idx => $row) {
            msgDebug("\nWorking with row = ".print_r($row, true));
            if (empty($row['unit_discount'])) { continue; }
            $item[] = [
                'ref_id'       => clean('id', 'integer', 'post'),
                'gl_type'      => $this->settings['gl_type'],
                'item_cnt'     => $idx + 1,
                'qty'          => 1,
                'description'  => $this->lang['title'],
                'debit_amount' => in_array($this->jID, [7, 9,10,12,19]) ? $row['unit_discount'] : 0,
                'credit_amount'=> in_array($this->jID, [3, 4, 6,13,21]) ? $row['unit_discount'] : 0,
                'gl_account'   => clean("totals_{$this->code}_gl", ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
                'tax_rate_id'  => 0,
                'post_date'    => $main['post_date']];
            $totalDisc += $row['unit_discount'];
        }
        if (empty($main['discount'])) { $main['discount'] = 0; }
        $main['discount'] += roundAmount($totalDisc, $isoVals['dec_len']);
        $begBal -= $totalDisc;
        msgDebug("\ndisc_item applied discount = $totalDisc and is returning balance = $begBal");
    }

    /**
     * generates the structure for the edit page
     * @param array $output - working structure
     * @param array $data - [not used] working page data
     */
    public function render($data=[])
    {
        $this->fields = [
          "totals_{$this->code}_id" => ['attr'=>['type'=>'hidden']],
          "totals_{$this->code}_gl" => ['label'=>lang('gl_account'),'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
          "totals_{$this->code}_opt"=> ['icon'=>'settings','size'=>'small','events'=>['onClick'=>"jqBiz('#phreebooks_totals_".$this->code."').toggle('slow');"]],
          "totals_{$this->code}"    => ['label'=>$this->lang['label'],'lblStyle'=>['min-width'=>'60px'],'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]];
        $html  = '<div style="text-align:right">'."\n";
        $html .= html5("totals_{$this->code}_id",$this->fields["totals_{$this->code}_id"]);
        $html .= html5("totals_{$this->code}",   $this->fields["totals_{$this->code}"]);
        $html .= html5('',                       $this->fields["totals_{$this->code}_opt"]);
        $html .= "</div>\n".'<div id="phreebooks_totals_'.$this->code.'" style="display:none" class="layout-expand-over">'."\n";
        $html .= html5("totals_{$this->code}_gl", $this->fields["totals_{$this->code}_gl"])."\n</div>\n";
        htmlQueue("function totals_{$this->code}(begBalance) {
    var newBalance= begBalance;
    var rowData   = jqBiz('#dgJournalItem').edatagrid('getData');
    var discount  = 0;
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        if (isNaN(rowData.rows[rowIndex].unit_discount)) rowData.rows[rowIndex].unit_discount = 0;
        discount += parseFloat(rowData.rows[rowIndex].unit_discount);
    }
    newBalance   -= discount;
    bizNumSet('totals_{$this->code}', discount);
    var curISO    = jqBiz('#currency').val() ? jqBiz('#currency').val() : bizDefaults.currency.defaultCur;
    var decLen    = parseInt(bizDefaults.currency.currencies[curISO].dec_len);
    return parseFloat(newBalance.toFixed(decLen));
}", 'jsHead');
        return $html;
    }
}
