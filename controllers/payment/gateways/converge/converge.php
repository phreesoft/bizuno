<?php
/*
 * Payment Method - Converge (Elavon)
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
 * @version    7.x Last Update: 2026-04-28
 * @filesource /controllers/payment/gateways/converge.php
 *
 * Source Information:
 * @copyright 2013 Converge, Incorporated, Two Concourse Parkway, Suite 800, Atlanta, GA 30328
 * @link https://www.myvirtualmerchant.com - Main Website
 * @link https://www.myvirtualmerchant.com/VirtualMerchant/download/developerGuide.pdf - Developer Guide
 *
 * Public entry points (generic gateway interface shared with other gateways):
 *   payment($action, $data=[])  - card-transaction dispatch
 *   wallet ($action, $data=[])  - stored customer/payment-profile dispatch (not implemented for converge)
 *   report ($action, $data=[])  - reporting dispatch (not implemented for converge)
 *
 * Normalized return shape:
 *   ['ok'=>bool, 'txID'=>'', 'code'=>'', 'msg'=>'', 'data'=>[], 'raw'=>$xmlResponse|null]
 */

namespace bizuno;

if (!defined('PAYMENT_CONVERGE_URL'))     { define('PAYMENT_CONVERGE_URL',     'https://www.myvirtualmerchant.com/VirtualMerchant/processxml.do'); }
if (!defined('PAYMENT_CONVERGE_URL_TEST')){ define('PAYMENT_CONVERGE_URL_TEST','https://demo.myvirtualmerchant.com/VirtualMerchantDemo/processxml.do'); }

class converge
{
    public  $moduleID = 'payment';
    public  $methodDir= 'gateways';
    public  $code     = 'converge';
    public  $defaults;
    public  $settings;
    public  $lang     = ['title' => 'Converge',
        'description' => 'Accept credit card payments through the Converge payment gateway.',
        'at_converge' => '@Converge',
        'merchant_id' => 'Merchant ID (provided by Converge)',
        'user_id'     => 'User ID (provided by Converge)',
        'pin'         => 'PIN (provided by Converge)',
        'mode'        => 'Gateway Mode',
        'auth_type'   => 'Authorization Type',
        'prefix_amex' => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
        'allow_refund'=> 'Allow Void/Refunds? This must be enabled by Converge for your merchant account or refunds will not be allowed.',
        'msg_website' => 'This must be done manually at the Converge website.',
        'msg_capture_manual' => 'The payment was not processed through the Converge gateway.',
        'msg_address_result' => 'Address verification results: %s',
        'err_process_decline' => 'Decline Code #%s: %s',
        'err_process_failed' => 'The credit card did not process, the response from Converge:'];

    public function __construct()
    {
        $pmtDef        = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->defaults= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'order'=>10,'merchant_id'=>'','user_id'=>'',
            'pin'=>'','mode'=>'test','auth_type'=>'Authorize/Capture','prefix'=>'CC','prefixAX'=>'AX','allowRefund'=>'0'];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        $noYes = [['id'=>'0','text'=>lang('no')], ['id'=>'1','text'=>lang('yes')]];
        $modes = [['id'=>'test','text'=>'Test (Demo)'], ['id'=>'prod','text'=>'Production']];
        $auths = [['id'=>'Authorize/Capture','text'=>lang('capture')], ['id'=>'Authorize','text'=>lang('authorize')]];
        return [
            'cash_gl_acct'=> ['label'=>lang('gl_payment_c_lbl', $this->moduleID), 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>lang('gl_discount_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'order'       => ['label'=>lang('order'),             'position'=>'after','attr'=>['type'=>'integer', 'size'=>'3','value'=>$this->settings['order']]],
            'merchant_id' => ['label'=>$this->lang['merchant_id'],'position'=>'after','attr'=>['type'=>'text', 'size'=>'20','value'=>$this->settings['merchant_id']]],
            'user_id'     => ['label'=>$this->lang['user_id'],    'position'=>'after','attr'=>['type'=>'text', 'size'=>'20','value'=>$this->settings['user_id']]],
            'pin'         => ['label'=>$this->lang['pin'],        'position'=>'after','attr'=>['type'=>'text','value'=>$this->settings['pin']]],
            'mode'        => ['label'=>$this->lang['mode'],       'values'=>$modes,   'attr'=>['type'=>'select','value'=>$this->settings['mode']]],
            'auth_type'   => ['label'=>$this->lang['auth_type'],  'values'=>$auths,   'attr'=>['type'=>'select','value'=>$this->settings['auth_type']]],
            'prefix'      => ['label'=>lang('prefix_lbl', $this->moduleID), 'position'=>'after','attr'=>['size'=>'5','value'=>$this->settings['prefix']]],
            'prefixAX'    => ['label'=>$this->lang['prefix_amex'],'position'=>'after','attr'=>['size'=>'5','value'=>$this->settings['prefixAX']]],
            'allowRefund' => ['label'=>$this->lang['allow_refund'],'values'=>$noYes,  'attr'=>['type'=>'select','value'=>$this->settings['allowRefund']]]];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        msgDebug("\nWorking with values = ".print_r($values, true));
        $cc_exp = pullExpDates();
        $this->viewData = [
            'trans_code'=> ['attr'=>['type'=>'hidden']],
            'selCards'  => ['attr'=>['type'=>'select'],'events'=>['onChange'=>"convergeRefNum('stored');"]],
            'save'      => ['label'=>lang('save'),'break'=>true,'attr'=>['type'=>'checkbox','value'=>'1']],
            'payment_id'=> ['attr'=>['type'=>'hidden']], // hidden
            'name'      => ['options'=>['width'=>200],'break'=>true,'label'=>lang('payment_name')],
            'number'    => ['options'=>['width'=>200],'break'=>true,'label'=>lang('payment_number'),'events'=>['onChange'=>"convergeRefNum('number');"]],
            'month'     => ['label'=>lang('payment_expiration'),'options'=>['width'=>130],'values'=>$cc_exp['months'],'attr'=>['type'=>'select','value'=>biz_date('m')]],
            'year'      => ['break'=>true,'options'=>['width'=>70],'values'=>$cc_exp['years'],'attr'=>['type'=>'select','value'=>biz_date('Y')]],
            'cvv'       => ['options'=>['width'=> 45],'label'=>lang('payment_cvv')]];
        if (!empty($values['method']) && $values['method']==$this->code && !empty($data['fields']['id']['attr']['value'])) { // edit
            $this->viewData['number']['attr']['value'] = isset($values['hint']) ? $values['hint'] : '****';
            $invoice_num = $invoice_amex = $data['fields']['invoice_num']['attr']['value'];
            $gl_account  = $data['fields']['gl_acct_id']['attr']['value'];
            $discount_gl = $this->getDiscGL($data['fields']['id']['attr']['value']);
            $show_s      = false;  // since it's an edit, all adjustments need to be made at the gateway, this prevents duplicate charges when re-posting a transaction
            $show_c      = false;
            $show_n      = false;
            $checked     = 'w';
        } else { // defaults
            $invoice_num = $this->settings['prefix'].biz_date('Ymd');
            $invoice_amex= $this->settings['prefixAX'].biz_date('Ymd');
            $gl_account  = $this->settings['cash_gl_acct'];
            $discount_gl = $this->settings['disc_gl_acct'];
            $show_n      = true;
            $checked     = 'n';
            $cID         = isset($data['fields']['contact_id_b']['attr']['value']) ? $data['fields']['contact_id_b']['attr']['value'] : 0;
            if ($cID) { // find if stored values
                $this->viewData['selCards']['values'] = [];
                if (sizeof($this->viewData['selCards']['values']) == 0) {
                    $this->viewData['selCards']['hidden'] = true;
                    $show_s      = false;
                } else {
                    $checked     = 's';
                    $show_s      = true;
                    $first_prefix= $this->viewData['selCards']['values'][0]['hint'];
                    $invoice_num = substr($first_prefix, 0, 2)=='37' ? $invoice_amex : $invoice_num;
                }
            } else { $show_s = false; }
            if (!empty($values['trans_code'])) {
                $invoice_num = isset($values['hint']) && substr($values['hint'], 0, 2)=='37' ? $invoice_amex : $invoice_num;
                $this->viewData['trans_code']['attr']['value'] = $values['trans_code'];
                $checked = 'c';
                $show_c = true;
            } else { $show_c = false; }
        }
        htmlQueue("
arrPmtMethod['$this->code'] = {cashGL:'$gl_account', discGL:'$discount_gl', ref:'$invoice_num', refAX:'$invoice_amex'};
function payment_$this->code() {
    bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
    bizSelSet('gl_acct_id', arrPmtMethod['$this->code'].cashGL);
    bizSelSet('totals_discount_gl', arrPmtMethod['$this->code'].discGL);
}
function convergeRefNum(type) {
    if (type=='stored') { var ccNum = jqBiz('#{$this->code}selCards').val(); }
      else { var ccNum = jqBiz('#{$this->code}_number').val();  }
    var prefix= ccNum.substr(0, 2);
    var newRef = prefix=='37' ? arrPmtMethod['$this->code'].refAX : arrPmtMethod['$this->code'].ref;
    bizTextSet('invoice_num', newRef);
}", 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        $html  = html5($this->code.'_action', ['label'=>lang('capture'),'hidden'=>($show_c?false:true),'attr'=>['type'=>'radio','value'=>'c','checked'=>$checked=='c'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').hide(); jqBiz('#div{$this->code}c').show();"]]).
html5($this->code.'_action', ['label'=>$this->lang['stored'] ?? lang('stored'), 'hidden'=>($show_s?false:true),'attr'=>['type'=>'radio','value'=>'s','checked'=>$checked=='s'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}n').hide(); jqBiz('#div{$this->code}s').show();"]]).
html5($this->code.'_action', ['label'=>lang('new'),    'hidden'=>($show_n?false:true),'attr'=>['type'=>'radio','value'=>'n','checked'=>$checked=='n'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').show();"]]).
html5($this->code.'_action', ['label'=>$this->lang['at_converge'],                    'attr'=>['type'=>'radio','value'=>'w','checked'=>$checked=='w'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').hide();"]]).'<br />';
        $html .= '<div id="div'.$this->code.'c"'.($show_c?'':'style=" display:none"').'>';
        if ($show_c) {
            $html .= html5($this->code.'trans_code',$this->viewData['trans_code']).sprintf(lang('msg_capture_payment'), viewFormat($values['total'],'currency'));
        }
        $html .= '</div><div id="div'.$this->code.'s"'.(!$show_c?'':'style=" display:none"').'>';
        if ($show_s) { $html .= lang('payment_stored_cards').'<br />'.html5($this->code.'selCards', $this->viewData['selCards']); }
        $html .= '</div>
<div id="div'.$this->code.'n"'.(!$show_c&&!$show_s?'':'style=" display:none"').'>'.
    html5($this->code.'_save',  $this->viewData['save']).
    html5($this->code.'_name',  $this->viewData['name']).
    html5($this->code.'_number',$this->viewData['number']).
    html5($this->code.'_month', $this->viewData['month']).
    html5($this->code.'_year',  $this->viewData['year']).
    html5($this->code.'_cvv',   $this->viewData['cvv']).'
</div>';
        return $html;
    }

    // ========================================================================
    // Generic dispatchers — these three public methods are the gateway API
    // ========================================================================

    /**
     * Card-transaction dispatch.
     * @param string $action - one of: capture, authorize, capAuth, refund, void
     * @param array  $data   - context (see each private method for required keys)
     * @return array normalized ['ok','txID','code','msg','data','raw']
     */
    public function payment($action, $data=[])
    {
        msgDebug("\nEntering converge::payment ($action)");
        switch ($action) {
            case 'capture':   return $this->pmtCapture($data);   // CCSALE
            case 'authorize': return $this->pmtAuthorize($data); // CCAUTHONLY
            case 'capAuth':   return $this->pmtCapAuth($data);   // CCCOMPLETE
            case 'refund':    return $this->pmtRefund($data);    // CCRETURN
            case 'void':      return $this->pmtVoid($data);      // CCVOID
        }
        return $this->notImplemented("payment/$action");
    }

    /** Converge in this implementation has no stored-customer/wallet API surface. */
    public function wallet($action, $data=[])
    {
        msgDebug("\nEntering converge::wallet ($action)");
        return $this->notImplemented("wallet/$action");
    }

    /** Converge reporting not implemented in this gateway. */
    public function report($action, $data=[])
    {
        msgDebug("\nEntering converge::report ($action)");
        return $this->notImplemented("report/$action");
    }

    // ========================================================================
    // payment() action implementations
    // ========================================================================

    private function pmtCapture($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        $fields = !empty($data['fields']) ? $data['fields'] : [];
        if (!$ledger) { return $this->failure('Ledger not provided to converge capture'); }
        $req = array_merge(
            $this->buildAuthFields(),
            ['ssl_transaction_type'=>'CCSALE'],
            $this->buildCardFields($fields),
            $this->buildOrderFields($ledger->main, $ledger->main['invoice_num'] ?? ''),
            $this->buildBillingFields($ledger->main, $fields)
        );
        return $this->runConverge($req);
    }

    private function pmtAuthorize($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        $fields = !empty($data['fields']) ? $data['fields'] : [];
        if (!$ledger) { return $this->failure('Ledger not provided to converge authorize'); }
        $refs = $this->guessInv($ledger);
        $req = array_merge(
            $this->buildAuthFields(),
            ['ssl_transaction_type'=>'CCAUTHONLY'],
            $this->buildCardFields($fields),
            $this->buildOrderFields($ledger->main, $refs['inv']),
            $this->buildBillingFields($ledger->main, $fields)
        );
        return $this->runConverge($req);
    }

    private function pmtCapAuth($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (empty($data['txID'])) { return $this->failure('txID required for priorAuthCapture'); }
        if (!$ledger) { return $this->failure('Ledger required for capture amount'); }
        $req = array_merge(
            $this->buildAuthFields(),
            ['ssl_transaction_type'=>'CCCOMPLETE',
             'ssl_txn_id'          =>(string)$data['txID'],
             'ssl_amount'          => $ledger->main['total_amount']]
        );
        return $this->runConverge($req);
    }

    /**
     * Refund a settled transaction. Returns ok=true with code='skipped' when
     * refunds are disabled or the prior txID/amount isn't recoverable.
     */
    private function pmtRefund($data)
    {
        if (empty($this->settings['allowRefund'])) {
            msgAdd(lang('err_cc_no_transaction_id'), 'caution');
            return $this->success('', 'skipped', 'Refunds disabled — non-fatal skip');
        }
        if (empty($data['txID']) || empty($data['amount'])) {
            msgAdd(lang('err_cc_no_transaction_id'), 'caution');
            return $this->success('', 'skipped', 'Missing txID/amount — non-fatal skip');
        }
        $amount = floatval($data['amount']);
        if ($amount <= 0) { return $this->failure(lang('err_cc_amount_negative')); }
        $req = array_merge(
            $this->buildAuthFields(),
            ['ssl_transaction_type'=>'ccreturn',
             'ssl_txn_id'          =>(string)$data['txID'],
             'ssl_amount'          => number_format($amount, 2, '.', '')]
        );
        return $this->runConverge($req);
    }

    /**
     * Void an unsettled transaction. Accepts either txID or rID (does the
     * journal_item trans_code lookup itself when given only an rID).
     */
    private function pmtVoid($data)
    {
        if (empty($this->settings['allowRefund'])) {
            msgAdd(lang('err_cc_no_transaction_id'), 'caution');
            return $this->success('', 'skipped', 'Voids disabled — non-fatal skip');
        }
        $txID = !empty($data['txID']) ? (string)$data['txID'] : '';
        if ($txID === '' && !empty($data['rID'])) {
            $txID = (string)dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'trans_code', "ref_id={$data['rID']} AND gl_type='ttl'");
        }
        if ($txID === '') {
            msgAdd(lang('err_cc_no_transaction_id'), 'caution');
            return $this->success('', 'skipped', 'No txID for void — non-fatal skip');
        }
        $req = array_merge(
            $this->buildAuthFields(),
            ['ssl_transaction_type'=>'ccvoid',
             'ssl_txn_id'          =>$txID]
        );
        return $this->runConverge($req);
    }

    // ========================================================================
    // Request-builder helpers
    // ========================================================================

    /** Merchant-credentials block, included on every request. */
    private function buildAuthFields()
    {
        return [
            'ssl_merchant_id' => $this->settings['merchant_id'],
            'ssl_user_id'     => $this->settings['user_id'],
            'ssl_pin'         => $this->settings['pin'],
            'ssl_show_form'   => 'FALSE',
            'ssl_result_format'=>'ASCII'];
    }

    /** Card data for sale/auth (number/exp/cvv/amount). */
    private function buildCardFields($fields)
    {
        $cvv = $fields['cvv'] ?? '';
        return [
            'ssl_card_number'       => $fields['number'] ?? '',
            'ssl_exp_date'          => ($fields['month'] ?? '').substr($fields['year'] ?? '', -2), // 2-digit year per spec
            'ssl_cvv2cvc2'          => $cvv,
            'ssl_cvv2cvc2_indicator'=> strlen((string)$cvv) > 0 ? '1' : '9'];
    }

    /** Order/amount/description block. */
    private function buildOrderFields($main, $invoiceNum)
    {
        return [
            'ssl_amount'         => $main['total_amount'],
            'ssl_invoice_number' => (string)$invoiceNum,
            'ssl_salestax'       => isset($main['sales_tax']) ? $main['sales_tax'] : 0,
            'ssl_description'    => $main['description'] ?? ''];
    }

    /** Billing-address block built from ledger->main + posted card-holder name. */
    private function buildBillingFields($main, $fields)
    {
        $first = $fields['first_name'] ?? '';
        $last  = $fields['last_name']  ?? '';
        return [
            'ssl_company'    => str_replace('&', '-', trim("$first $last")),
            'ssl_avs_address'=> str_replace('&', '-', substr($main['address1_b'] ?? '', 0, 20)),
            'ssl_address2'   => str_replace('&', '-', substr($main['address2_b'] ?? '', 0, 20)),
            'ssl_city'       => $main['city_b']    ?? '',
            'ssl_state'      => $main['state_b']   ?? '',
            'ssl_country'    => $main['country_b'] ?? '',
            'ssl_avs_zip'    => preg_replace('/[^A-Za-z0-9]/','', $main['postal_code_b'] ?? ''),
            'ssl_phone'      => substr(preg_replace('/[^0-9]/','', $main['telephone1_b'] ?? ''), 0, 14)];
    }

    // ========================================================================
    // SDK plumbing — environment, runner, response parsing
    // ========================================================================

    private function env()
    {
        return ($this->settings['mode'] ?? 'test') === 'prod' ? PAYMENT_CONVERGE_URL : PAYMENT_CONVERGE_URL_TEST;
    }

    /**
     * Posts the request to the configured Converge endpoint, parses the XML response,
     * and returns the normalized shape. Used by every payment() action.
     */
    private function runConverge($request=[])
    {
        global $io;
        $tags = '';
        foreach ($request as $key => $value) {
            if ($value === '' || $value === null) { continue; }
            if (is_array($value)) { msgDebug("\nconverge: skipping array value for $key"); continue; }
            $tags .= "<$key>".urlencode(str_replace('&', '+', (string)$value))."</$key>";
        }
        $data = "xmldata=<txn>$tags</txn>";
        msgDebug("\nRequest to send to Converge: $data");
        $url = $this->env();
        $strXML = $io->cURL($url, $data, 'post');
        if (!$strXML) { return $this->failure('Gateway communication error'); }
        msgDebug("\nReceived raw data back from Converge: ".print_r($strXML, true));
        $resp = parseXMLstring($strXML);
        msgDebug("\nReceived back from Converge: ".print_r($resp, true));
        if (isset($resp->errorCode)) {
            $msg = sprintf($this->lang['err_process_decline'], (string)$resp->errorCode, (string)$resp->errorMessage);
            msgLog($msg);
            msgAdd($msg);
            return ['ok'=>false, 'txID'=>'', 'code'=>(string)$resp->errorCode, 'msg'=>(string)$resp->errorMessage, 'data'=>[], 'raw'=>$resp];
        }
        if (!isset($resp->ssl_result) || (string)$resp->ssl_result !== '0') {
            $msg = $this->lang['err_process_failed'].' - '.(string)($resp->ssl_result_message ?? '');
            msgAdd($msg);
            return ['ok'=>false, 'txID'=>'', 'code'=>(string)($resp->ssl_result ?? ''), 'msg'=>$msg, 'data'=>[], 'raw'=>$resp];
        }
        // Success path — surface CVV/AVS warnings as cautions but don't fail
        if (!empty($resp->ssl_cvv2_response) && (string)$resp->ssl_cvv2_response !== 'M') {
            $key = 'CVV_'.(string)$resp->ssl_cvv2_response;
            msgAdd(sprintf($this->lang['err_cvv_mismatch'] ?? 'CVV mismatch: %s', $this->lang[$key] ?? (string)$resp->ssl_cvv2_response), 'caution');
        }
        if (!empty($resp->ssl_avs_response) && !in_array((string)$resp->ssl_avs_response, ['X','Y'])) {
            $key = 'AVS_'.(string)$resp->ssl_avs_response;
            msgAdd(sprintf($this->lang['err_avs_mismatch'] ?? 'AVS mismatch: %s', $this->lang[$key] ?? (string)$resp->ssl_avs_response), 'caution');
        }
        $cvvLabel = !empty($resp->ssl_cvv2_response) ? ($this->lang['CVV_'.(string)$resp->ssl_cvv2_response] ?? 'n/a') : 'n/a';
        msgAdd(sprintf($this->lang['msg_approval_success'] ?? '%s — auth: %s — CVV: %s', (string)$resp->ssl_result_message, (string)$resp->ssl_approval_code, $cvvLabel), 'success');
        return $this->success(
            (string)$resp->ssl_txn_id,
            (string)$resp->ssl_approval_code,
            (string)$resp->ssl_result_message,
            ['txTime'=>(string)$resp->ssl_txn_time],
            $resp
        );
    }

    private function success($txID='', $code='', $msg='', $data=[], $raw=null)
    {
        return ['ok'=>true, 'txID'=>$txID, 'code'=>$code, 'msg'=>$msg, 'data'=>$data, 'raw'=>$raw];
    }

    private function failure($msg='')
    {
        if ($msg) { msgAdd($msg); msgDebug("\nConverge failure: $msg"); }
        return ['ok'=>false, 'txID'=>'', 'code'=>'', 'msg'=>$msg, 'data'=>[], 'raw'=>null];
    }

    private function notImplemented($action)
    {
        msgAdd("Converge action '$action' is not implemented.");
        return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented', 'msg'=>"not implemented: $action", 'data'=>[], 'raw'=>null];
    }

    // ========================================================================
    // Original helpers (preserved)
    // ========================================================================

    private function getDiscGL($data)
    {
        if (isset($data['fields'])) {
            foreach ($data['fields'] as $row) {
                if ($row['gl_type'] == 'dsc') { return $row['gl_account']; }
            }
        }
        return $this->settings['disc_gl_acct']; // not found, return default
    }

    /**
     * Tries to guess the invoice number and po number of the first pmt record of the item array
     */
    private function guessInv($ledger)
    {
        $refs = ['inv'=>$ledger->main['invoice_num'], 'po'=>$ledger->main['invoice_num']];
        if (empty($ledger->items)) { return $refs; }
        foreach ($ledger->items as $row) {
            if ($row['gl_type'] <> 'pmt') { continue; }
            $vals = explode(' ', $row['description'], 4);
            if (!empty($vals[1])) { $refs['inv']= $vals[1]; }
            if (!empty($vals[3])) { $refs['po'] = $vals[3]; }
            break;
        }
        return $refs;
    }

}
