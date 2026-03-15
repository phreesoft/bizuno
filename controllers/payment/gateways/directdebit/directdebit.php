<?php
/*
 * Payment Method - Direct Debit
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
 * @filesource /controllers/payment/gateways/directdebit.php
 */

namespace bizuno;

class directdebit
{
    public $moduleID = 'payment';
    public $methodDir= 'gateways';
    public $code     = 'directdebit';
    public $required = true;
    public $defaults;
    public $settings;
    public $viewData;
    public $lang     = ['title' => 'Direct Debits',
        'description'=> 'Direct Debit and Electronic Funds Transfer (EFT) Payments.',
        'check_number' => 'Reference Number'];

    public function __construct()
    {
        $pmtDef        = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->defaults= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'prefix'=>'EF','order'=>35];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        return [
            'cash_gl_acct'=> ['label'=>$this->lang['gl_payment_c_lbl'], 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>$this->lang['gl_discount_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'prefix'      => ['label'=>$this->lang['prefix_lbl'], 'position'=>'after', 'attr'=>  ['size'=>'5', 'value'=>$this->settings['prefix']]],
            'order'       => ['label'=>lang('order'), 'position'=>'after', 'attr'=>  ['type'=>'integer', 'size'=>'3', 'value'=>$this->settings['order']]]];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        $this->viewData = ['ref_1'=> ['options'=>['width'=>150],'label'=>lang('invoice_num_2'),'break'=>true, 'attr'=>  ['size'=>'19']]];
        if (isset($values['method']) && $values['method']==$this->code && !empty($data['fields']['id']['attr']['value'])) { // edit
            $this->viewData['ref_1']['attr']['value'] = $this->getRefValue($data['fields']['id']['attr']['value']);
            $invoice_num = $data['fields']['invoice_num']['attr']['value'];
            $gl_account  = $data['fields']['gl_acct_id']['attr']['value'];
            $discount_gl = $this->getDiscGL($data['fields']['id']['attr']['value']);
        } else {
            $invoice_num = $this->settings['prefix'].biz_date('Ymd');
            $gl_account  = $this->settings['cash_gl_acct'];
            $discount_gl = $this->settings['disc_gl_acct'];
        }
        htmlQueue("
arrPmtMethod['$this->code'] = {cashGL:'$gl_account',discGL:'$discount_gl',ref:'$invoice_num'};
function payment_".$this->code."() {
    bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
    bizSelSet('gl_acct_id', arrPmtMethod['$this->code'].cashGL);
    bizSelSet('totals_discount_gl', arrPmtMethod['$this->code'].discGL);
}", 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        return html5($this->code.'_ref_1',$this->viewData['ref_1']);
    }

    public function sale($fields=[])
    {
        return ['txID'=>!empty($fields['ref_1']) ? $fields['ref_1'] : '', 'txTime'=>biz_date('c')];
    }

    private function getDiscGL($rID=0)
    {
        $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        if (sizeof($items) > 0) { foreach ($items as $row) {
            if ($row['gl_type'] == 'dsc') { return $row['gl_account']; }
        } }
        return $this->settings['disc_gl_acct']; // not found, return default
    }

    private function getRefValue($rID=0)
    {
        $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        if (sizeof($items) > 0) { foreach ($items as $row) {
            if ($row['gl_type'] == 'ttl') { return $row['trans_code']; }
        } }
        return ''; // not found, return default
    }
}
