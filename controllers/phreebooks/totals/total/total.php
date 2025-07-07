<?php
/*
 * PhreeBooks total method for order total - last total operation
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
 * @filesource /controllers/phreebooks/totals/total/total.php
 */

namespace bizuno;

class total
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'totals';
    public $code     = 'total';
    public $hidden   = false;
    public $required = true;
    public $journal_id;
    public $settings;
    public $lang     = ['title'=>'Total',
        'description'=> 'This method calculates the total for the journal entry. It is used for order operations, i.e. Customer Sales and Vendor Purchases. The order is fixed and should remain the last total method (highest order position).'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        if (!defined('JOURNAL_ID')) { define('JOURNAL_ID', 2); }
        $this->journal_id = JOURNAL_ID;
        switch ($this->journal_id) {
            default:
            case  2: $gl_account = ''; break; // General Journal
            case  3: // Purchase Quote
            case  4: // Purchase Order
            case  6: // Purchase/Receive
            case  7: $gl_account = getModuleCache('phreebooks','settings', 'vendors',   'gl_payables');   break; // 7 - Purchase Credit Memo
            case  9: // Sales Quote
            case 10: // Sales Order
            case 12: // Sales/Invoice
            case 13: $gl_account = getModuleCache('phreebooks','settings', 'customers', 'gl_receivables');break; // 13 - Sales Credit Memo
            case 14: $gl_account = getModuleCache('inventory', 'settings', 'phreebooks','inv_si');        break; // 14 - Inventory Assembly
            case 15: // Inventory Store Transfer
            case 16: $gl_account = getModuleCache('inventory', 'settings', 'phreebooks','cogs_si');       break; // 16 - Inventory Adjustment
            case 18: // Customer Receipts
            case 19: // POS
            case 22: $gl_account = getModuleCache('phreebooks','settings', 'customers', 'gl_cash');       break; // 22 - Customer Payments
            case 17: // Vendor Receipts
            case 20: // Cash Distribution
            case 21: $gl_account = getModuleCache('phreebooks','settings', 'vendors',   'gl_cash');       break; // 21 - POP
        }
        $this->settings= ['gl_type'=>'ttl','journals'=>'[3,4,6,7,9,10,12,13,15,16,17,18,19,20,21,22]','gl_account'=>$gl_account,'order'=>99];
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
     * Return false if no GL entry needed
     * @param array $main
     * @param type $item
     */
    public function glEntry(&$main, &$item, &$begBal=0)
    {
        $total = clean('total_amount', ['format'=>'float','default'=>0], 'post');
        $desc  = $this->lang['title'].': '.clean('primary_name_b', ['format'=>'text','default'=>''], 'post');
        $altDsc= clean('totals_total_desc', 'text', 'post');
        $txID  = clean('totals_total_txid', 'text', 'post');
        $item[]= [
            'id'           => clean("totals_{$this->code}_id", ['format'=>'float','default'=>0], 'post'),
            'ref_id'       => clean('id', 'integer', 'post'),
            'gl_type'      => $this->settings['gl_type'],
            'qty'          => '1',
            'description'  => !empty($altDsc) ? $altDsc : $desc,
            'debit_amount' => in_array($main['journal_id'], [7,9,10,12,14,17,18,19]) ? $total : 0,
            'credit_amount'=> in_array($main['journal_id'], [3,4, 6,13,16,20,21,22]) ? $total : 0,
            'gl_account'   => clean('gl_acct_id', ['format'=>'text','default'=>$this->settings['gl_account']], 'post'),
            'trans_code'   => $txID,
            'post_date'    => $main['post_date'],
            'date_1'       => $main['post_date'].' '.biz_date('H:i:s')]; // timestamp for time of day information
        $main['total_amount'] = $total;
    }

    /**
     * Renders the HTML for this method
     * @param array $output - running output buffer
     * @param array $data - source data
     */
    public function render($data=[])
    {
        // @TODO This needs to return structure vs html so it can be modified customized by journal, i.e. credit memos
        $this->fields = [
            'totals_total_id'  => ['attr'=>['type'=>'hidden']],
            'totals_total_desc'=> ['attr'=>['type'=>'hidden']],
            'totals_total_txid'=> ['attr'=>['type'=>'hidden']],
            'gl_acct_id'       => ['label'=>lang('gl_account'),   'attr'=>['type'=>'ledger','value'=>$this->settings['gl_account']]],
            'totals_total_opt' => ['icon'=>'settings', 'size'=>'small','events'=>['onClick'=>"jqBiz('#totals_total_div').toggle('slow');"]],
            'total_prepay'     => ['label'=>lang('prepay_amount'),'attr'=>['type'=>'currency','value'=>0]],
            'total_amount'     => ['label'=>lang('total'),        'attr'=>['type'=>'currency','value'=>0,'readonly'=>'readonly']]];
        msgDebug("\nProcessing items with data = ".print_r($data['items'], true));
        if (isset($data['items'])) { foreach ($data['items'] as $row) { // fill in the data if available
            if ($row['gl_type'] == $this->settings['gl_type']) {
                $this->fields['totals_total_id']['attr']['value']  = isset($row['id']) ? $row['id'] : 0;
                $this->fields['totals_total_desc']['attr']['value']= isset($row['description']) ? $row['description'] : '';
                $this->fields['totals_total_txid']['attr']['value']= isset($row['trans_code']) ? $row['trans_code'] : '';
                $this->fields['gl_acct_id']['attr']['value']       = $row['gl_account'];
                $this->fields['total_amount']['attr']['value']     = $row['credit_amount'] + $row['debit_amount'];
            }
        } }
        $hide = $this->hidden ? ';display:none' : '';
        $html  = '<div style="text-align:right'.$hide.'">'."\n";
        $html .= html5('totals_total_id',  $this->fields['totals_total_id']);
        $html .= html5('totals_total_desc',$this->fields['totals_total_desc']);
        $html .= html5('totals_total_txid',$this->fields['totals_total_txid']);
        $html .= html5('total_amount',     $this->fields['total_amount']);
        $html .= html5('',                 $this->fields['totals_total_opt']);
        $html .= "</div>\n";
        $html .= '<div id="totals_total_div" style="display:none" class="layout-expand-over">'."\n";
        $html .= html5('gl_acct_id',       $this->fields['gl_acct_id'])."\n";
        $html .= "</div>\n";
        if (in_array($this->journal_id, [4,10])) { // allow prepayments for SO/PO
            $html .= '<div style="text-align:right">'.html5('total_prepay', $this->fields['total_prepay'])."</div>\n";
        }
        htmlQueue("function totals_total(begBalance) {
    var newBalance = begBalance;
    bizNumSet('total_amount', newBalance);
    return newBalance;
}", 'jsHead');
        return $html;
    }
}
