<?php
/*
 * Payment Method - PayPal thought hosted payment terminal @paypal.com
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
 * @filesource /lib/controllers/payment/gateways/paypal.php
 */

namespace bizuno;

class paypal
{
    public  $moduleID = 'payment';
    public  $methodDir= 'gateways';
    public  $code     = 'paypal';
    public  $defaults;
    public  $settings;
    public  $lang = ['title' => 'PayPal',
        'description'=> 'PayPal interface, covers both PayPal Express and PayPal Pro.',
        'at_paypal' => '@PayPal',
        'user' => 'User ID (provided by PayPal)',
        'pass' => 'Password (provided by PayPal)',
        'signature' => 'Signature (provided by PayPal)',
        'auth_type' => 'Authorization Type',
        'prefix_amex' => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
        'allow_refund' => 'Allow Void/Refunds? This must be enabled by PayPal Pro for your merchant account or refunds will not be allowed.',
        'msg_address_result' => 'Address verification results: %s',
        'msg_website' => 'This must be done manually at the PayPal website.',
        'msg_capture_manual' => 'The payment was not processed through the PayPal gateway.',
        'msg_delete_manual' =>'The payment was not deleted through the PayPal gateway.',
        'msg_refund_manual' =>'The payment was not refunded through the PayPal gateway.',
        'err_process_decline' => 'Decline Message: %s',
        'err_process_failed' => 'The credit card did not process, the response from PayPal Pro:'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $pmtDef        = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->defaults= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'prefix'=>'PP','order'=>30];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        return [
            'cash_gl_acct'=> ['label'=>$this->lang['gl_payment_c_lbl'], 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>$this->lang['gl_discount_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'prefix'      => ['label'=>$this->lang['prefix_lbl'], 'position'=>'after', 'attr'=>['size'=>'5', 'value'=>$this->settings['prefix']]],
            'order'       => ['label'=>lang('order'), 'position'=>'after', 'attr'=>['type'=>'integer', 'size'=>'3', 'value'=>$this->settings['order']]]];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        $this->viewData = ['ref_1'=> ['options'=>['width'=>150],'label'=>lang('invoice_num_2'),'break'=>true, 'attr'=>['size'=>'19']]];
        if (is_array($values) && isset($values[1]) && $values[1] == $this->code) {
            $this->viewData['ref_1']['attr']['value'] = isset($values[2]) ? $values[2] : '';
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
function payment_{$this->code}() {
    if (!jqBiz('#id').val()) {
        bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
        bizSelSet('gl_acct_id', arrPmtMethod['$this->code'].cashGL);
        bizSelSet('totals_discount_gl', arrPmtMethod['$this->code'].discGL);
    }
}", 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        $html  = html5($this->code.'_action', ['label'=>$this->lang['at_paypal'],'attr'=>['type'=>'radio','value'=>'w','checked'=>true]]).'<br />';
        return $html;
    }

    public function sale($fields=[])
    {
        return ['txID'=>!empty($fields['ref_1']) ? $fields['ref_1'] : '', 'txTime'=>biz_date('c')];
    }

    /**
     * @method paymentDelete - This method will delete/void a payment made BEFORE the processor commits the payment, typically must be run the same day as the sale
     * @param string $request - data from which to pull transaction and perform delete
     * @return boolean - true on success, false (with messageStack message) on unsuccessful deletion
     */
    public function paymentDelete()
    {
        msgAdd('Deletions through the PayPal API must be handled directly through the PayPal website.', 'caution');
        return true;
    }

    /**
     * @param string $request - data from which to pull transaction and perform refund/credit
     * @return boolean - true on success, false (with messageStack message) on unsuccessful deletion
     */
    public function refund()
    {
        msgAdd('Refunds through the PayPal API must be handled directly through the PayPal website.', 'caution');
        return true;
    }

    private function getDiscGL($data)
    {
        if (isset($data['journal_item'])) {
            foreach ($data['journal_item'] as $row) {
                if ($row['gl_type'] == 'dsc') { return $row['gl_account']; }
            }
        }
        return $this->settings['disc_gl_acct']; // not found, return default
    }
}