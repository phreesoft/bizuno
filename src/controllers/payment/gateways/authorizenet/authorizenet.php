<?php
/*
 * Payment Method - Authorize.net
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
 * @filesource /controllers/payment/gateways/authorizenet.php
 *
 * Source Information:
 * @link https://developer.authorize.net/api/reference/index.html - API Documentation
 * @link https://github.com/AuthorizeNet/sdk-php - GitHub PHP SDK
 *
 * Public entry points (generic gateway interface shared with other gateways):
 *   payment($action, $data=[])  - card-transaction dispatch
 *   wallet ($action, $data=[])  - stored customer/payment-profile dispatch
 *   report ($action, $data=[])  - reporting dispatch
 *
 * Normalized return shape:
 *   ['ok'=>bool, 'txID'=>'', 'code'=>'', 'msg'=>'', 'data'=>[], 'raw'=>$sdkResponse|null]
 */

namespace bizuno;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;
use net\authorize\api\constants\ANetEnvironment;

class authorizenet
{
    public  $moduleID  = 'payment';
    public  $methodDir = 'gateways';
    public  $code      = 'authorizenet';
    public  $defaults;
    public  $settings;
    /** Cached customerProfileId from walletList()/lookupCustomerProfileId() so we don't refetch in the same request. */
    private $cachedCustID = '';
    public  $lang      = [
        'title'              => 'Authorize.net',
        'description'        => 'Accept credit card payments through the Authorize.net payment gateway.',
        'at_authorizenet'    => '@Authorize.net',
        'user_id'            => 'User ID (provided by Authorize.net)',
        'txn_key'            => 'Transaction Key',
        'mode'               => 'Gateway Mode',
        'auth_type'          => 'Authorization Type',
        'prefix_amex'        => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
        'allow_refund'       => 'Allow Void/Refunds? This must be enabled by Authorize.net for your merchant account or refunds will not be allowed.',
        'msg_website'        => 'This must be done manually at the Authorize.net website.',
        'msg_capture_manual' => 'The payment was not processed through the Authorize.net gateway.',
        'save_to_wallet'     => 'Save card to wallet',
        'msg_address_result' => 'Address verification results: %s',
        'err_process_decline'=> 'Decline Code #%s: %s',
        'err_process_failed' => 'The credit card did not process, the response from Authorize.net:'];

    public function __construct()
    {
        $pmtDef  = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $defaults= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'order'=>10,'user_id'=>'','txn_key'=>'',
            'mode'=>'test','auth_type'=>'Authorize/Capture','prefix'=>'CC','prefixAX'=>'AX','allowRefund'=>'0'];
        $userMeta= getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        $noYes = [['id'=>'0','text'=>lang('no')], ['id'=>'1','text'=>lang('yes')]];
        $modes = [['id'=>'test','text'=>'Test (Sandbox)'], ['id'=>'prod','text'=>'Production']];
        $auths = [['id'=>'Authorize/Capture','text'=>lang('capture')], ['id'=>'Authorize','text'=>lang('authorize')]];
        return [
            'cash_gl_acct'=> ['label'=>lang('gl_payment_c_lbl', $this->moduleID), 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>lang('gl_discount_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'order'       => ['label'=>lang('order'), 'position'=>'after', 'attr'=>  ['type'=>'integer', 'size'=>'3','value'=>$this->settings['order']]],
            'user_id'     => ['label'=>$this->lang['user_id'], 'position'=>'after','attr'=>['type'=>'text', 'size'=>'20','value'=>$this->settings['user_id']]],
            'txn_key'     => ['label'=>$this->lang['txn_key'], 'position'=>'after','attr'=>['type'=>'text','value'=>$this->settings['txn_key']]],
            'mode'        => ['label'=>$this->lang['mode'],    'values'=>$modes,   'attr'=>['type'=>'select','value'=>$this->settings['mode']]],
            'auth_type'   => ['label'=>$this->lang['auth_type'],'values'=>$auths,  'attr'=>['type'=>'select','value'=>$this->settings['auth_type']]],
            'prefix'      => ['label'=>lang('prefix_lbl', $this->moduleID), 'position'=>'after','attr'=>['size'=>'5','value'=>$this->settings['prefix']]],
            'prefixAX'    => ['label'=>$this->lang['prefix_amex'],'position'=>'after','attr'=>['size'=>'5','value'=>$this->settings['prefixAX']]],
            'allowRefund' => ['label'=>$this->lang['allow_refund'],'values'=>$noYes, 'attr'=>['type'=>'select','value'=>$this->settings['allowRefund']]]];
    }

    public function render($data, $values=[], $dispFirst=false)
    {
        msgDebug("\nWorking with values = ".print_r($values, true));
        $cc_exp = pullExpDates();
        $this->viewData = [
            'trans_code'=> ['attr'=>['type'=>'hidden']],
            'selCards'  => ['attr'=>['type'=>'select'],'events'=>['onChange'=>"authorizenetRefNum('stored');"]],
            'name'      => ['options'=>['width'=>200],'break'=>true,'label'=>lang('payment_name')],
            'number'    => ['options'=>['width'=>200],'break'=>true,'label'=>lang('payment_number'),'events'=>['onChange'=>"authorizenetRefNum('number');"]],
            'month'     => ['label'=>lang('payment_expiration'),'options'=>['width'=>130],'values'=>$cc_exp['months'],'attr'=>['type'=>'select','value'=>biz_date('m')]],
            'year'      => ['break'=>true,'options'=>['width'=>70],'values'=>$cc_exp['years'],'attr'=>['type'=>'select','value'=>biz_date('Y')]],
            'cvv'       => ['options'=>['width'=> 45],'label'=>lang('payment_cvv')],
            'save'      => ['break'=>true,'label'=>$this->lang['save_to_wallet'],'attr'=>['type'=>'checkbox','value'=>'1']]];
        if (isset($values['method']) && $values['method']==$this->code && !empty($data['fields']['id']['attr']['value'])) { // edit
            $this->viewData['number']['attr']['value'] = isset($values['hint']) ? $values['hint'] : '****';
            $invoice_num = $invoice_amex = $data['fields']['invoice_num']['attr']['value'];
            $gl_account  = $data['fields']['gl_acct_id']['attr']['value'];
            $discount_gl = $this->getDiscGL($data['fields']['id']['attr']['value']);
            $show_s = false;  // since it's an edit, all adjustments need to be made at the gateway, this prevents duplicate charges when re-posting a transaction
            $show_c = false;
            $show_n = false;
            $checked = 'w';
        } else { // defaults
            $invoice_num = $this->settings['prefix'].biz_date('Ymd');
            $invoice_amex= $this->settings['prefixAX'].biz_date('Ymd');
            $gl_account  = $this->settings['cash_gl_acct'];
            $discount_gl = $this->settings['disc_gl_acct'];
            $show_n = true;
            $checked = 'n';
            $cID = isset($data['fields']['contact_id_b']['attr']['value']) ? $data['fields']['contact_id_b']['attr']['value'] : 0;
            if ($cID) { // pull stored wallet from Authorize.net keyed on getWalletID($cID)
                $this->viewData['selCards']['values'] = $this->walletList(getWalletID((int)$cID));
                if (empty($this->viewData['selCards']['values'])) {
                    $this->viewData['selCards']['hidden'] = true;
                    $show_s = false;
                } else {
                    $checked = 's';
                    $show_s  = true;
                    $first   = $this->viewData['selCards']['values'][0];
                    if (!empty($first['isAmex'])) { $invoice_num = $invoice_amex; }
                }
            } else { $show_s = false; }
            if (isset($values['trans_code']) && $values['trans_code']) {
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
function authorizenetRefNum(type) {
    if (type=='stored') { var ccNum = jqBiz('#{$this->code}selCards').val(); }
      else { var ccNum = bizTextGet('{$this->code}_number');  }
    var prefix= ccNum.substr(0, 2);
    var newRef = prefix=='37' ? arrPmtMethod['$this->code'].refAX : arrPmtMethod['$this->code'].ref;
    bizTextSet('invoice_num', newRef);
}", 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        $html  = html5($this->code.'_action', ['label'=>lang('capture'),'hidden'=>($show_c?false:true),'attr'=>['type'=>'radio','value'=>'c','checked'=>$checked=='c'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').hide(); jqBiz('#div{$this->code}c').show();"]]).
html5($this->code.'_action', ['label'=>lang('stored'), 'hidden'=>($show_s?false:true),'attr'=>['type'=>'radio','value'=>'s','checked'=>$checked=='s'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}n').hide(); jqBiz('#div{$this->code}s').show();"]]).
html5($this->code.'_action', ['label'=>lang('new'),    'hidden'=>($show_n?false:true),'attr'=>['type'=>'radio','value'=>'n','checked'=>$checked=='n'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').show();"]]).
html5($this->code.'_action', ['label'=>$this->lang['at_authorizenet'],                    'attr'=>['type'=>'radio','value'=>'w','checked'=>$checked=='w'?true:false],
    'events'=>  ['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}n').hide();"]]).'<br />';
        $html .= '<div id="div'.$this->code.'c"'.($show_c?'':'style=" display:none"').'>';
        if ($show_c) {
            $html .= html5($this->code.'trans_code',$this->viewData['trans_code']).sprintf(lang('msg_capture_payment'), viewFormat($values['total'],'currency'));
        }
        $html .= '</div><div id="div'.$this->code.'s"'.(!$show_c?'':'style=" display:none"').'>';
        if ($show_s) { $html .= lang('payment_stored_cards').'<br />'.html5($this->code.'selCards', $this->viewData['selCards']); }
        $html .= '</div>
<div id="div'.$this->code.'n"'.(!$show_c&&!$show_s?'':'style=" display:none"').'>'.
    html5($this->code.'_name',  $this->viewData['name']).
    html5($this->code.'_number',$this->viewData['number']).
    html5($this->code.'_month', $this->viewData['month']).
    html5($this->code.'_year',  $this->viewData['year']).
    html5($this->code.'_cvv',   $this->viewData['cvv']).
    html5($this->code.'_save',  $this->viewData['save']).'
</div>';
        return $html;
    }

    // ========================================================================
    // Generic dispatchers — these three public methods are the gateway API
    // ========================================================================

    /**
     * Card-transaction dispatch.
     * @param string $action - one of: capture, authorize, capAuth, refund, void, wltCap
     * @param array  $data   - context (see each private method for required keys)
     * @return array normalized ['ok','txID','code','msg','data','raw']
     */
    public function payment($action, $data=[])
    {
        msgDebug("\nEntering authorize.net::payment ($action)");
        switch ($action) {
            case 'capture':   return $this->pmtCapture($data);        // authorize + capture in one step
            case 'authorize': return $this->pmtAuthorize($data);      // auth only
            case 'capAuth':   return $this->pmtCapAuth($data);        // capture a prior authorize-only txn
            case 'refund':    return $this->pmtRefund($data);         // refund a settled transaction
            case 'void':      return $this->pmtVoid($data);           // void an unsettled transaction
            case 'wltCap':    return $this->pmtWalletCapture($data);  // charge a stored payment profile
        }
        return $this->notImplemented("payment/$action");
    }

    /**
     * Customer-profile and stored-payment dispatch.
     * @param string $action - custCreate, custGet, custGetIDs, custUpdate, custDelete, wltNew, wltGet, wltDelete
     * @param array  $data   - context (see each private method for required keys)
     * @return array normalized response
     */
    public function wallet($action, $data=[])
    {
        msgDebug("\nEntering authorize.net::wallet ($action)");
        switch ($action) {
            case 'custCreate': return $this->walletCustCreate($data);
            case 'custGet':    return $this->walletCustGet($data);
            case 'custGetIDs': return $this->walletCustGetIDs($data);
            case 'custUpdate': return $this->walletCustUpdate($data);
            case 'custDelete': return $this->walletCustDelete($data);
            case 'wltNew':     return $this->walletPayNew($data);
            case 'wltGet':     return $this->walletPayGet($data);
            case 'wltDelete':  return $this->walletPayDelete($data);
        }
        return $this->notImplemented("wallet/$action");
    }

    /**
     * Reporting dispatch.
     * @param string $action - rptBatch (list transactions in a batch), rptTrans (detail for one txn)
     * @param array  $data   - ['batchID'=>...] or ['txID'=>...]
     * @return array normalized response
     */
    public function report($action, $data=[])
    {
        msgDebug("\nEntering authorize.net::report ($action)");
        switch ($action) {
            case 'rptBatch': return $this->rptBatch($data);
            case 'rptTrans': return $this->rptTrans($data);
        }
        return $this->notImplemented("report/$action");
    }

    // ========================================================================
    // payment() action implementations
    // ========================================================================

    private function pmtCapture($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (!$ledger) { return $this->failure('Ledger not provided to authorize.net capture'); }
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('authCaptureTransaction');
        $txn->setAmount(number_format($ledger->main['total_amount'], 2, '.', ''));
        $pay = new AnetAPI\PaymentType();
        $pay->setCreditCard($this->buildCreditCardFromPost());
        $txn->setPayment($pay);
        $txn->setOrder($this->buildOrder($ledger->main));
        $txn->setBillTo($this->buildBillTo($ledger->main));
        $txn->setCustomer($this->buildCustomerData($ledger->main));
        $result = $this->runTransaction($txn);
        $this->maybeSaveCardToWallet($result, $ledger);
        return $result;
    }

    private function pmtAuthorize($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (!$ledger) { return $this->failure('Ledger not provided to authorize.net authorize'); }
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('authOnlyTransaction');
        $txn->setAmount(number_format($ledger->main['total_amount'], 2, '.', ''));
        $pay = new AnetAPI\PaymentType();
        $pay->setCreditCard($this->buildCreditCardFromPost());
        $txn->setPayment($pay);
        $txn->setOrder($this->buildOrder($ledger->main));
        $txn->setBillTo($this->buildBillTo($ledger->main));
        $txn->setCustomer($this->buildCustomerData($ledger->main));
        $result = $this->runTransaction($txn);
        $this->maybeSaveCardToWallet($result, $ledger);
        return $result;
    }

    /**
     * If the user checked "Save card to wallet" on a new-card transaction that succeeded,
     * create (or attach to) an Authorize.net customer profile keyed by the Bizuno wallet ID
     * (e.g. "C000000123" — same format PayFabric uses, see getWalletID()).
     */
    private function maybeSaveCardToWallet($result, $ledger)
    {
        if (empty($result['ok']) || empty($ledger) || empty($ledger->main['contact_id_b'])) { return; }
        if (!clean("{$this->code}_save", 'boolean', 'post')) { return; }
        $action = clean("{$this->code}_action", 'cmd', 'post');
        if ($action !== 'n' && $action !== 'c') { return; } // only save when paying with a new card
        $this->wallet('custCreate', ['ledger'=>$ledger]);
    }

    private function pmtCapAuth($data)
    {
        if (empty($data['txID'])) { return $this->failure('txID required for priorAuthCapture'); }
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('priorAuthCaptureTransaction');
        $txn->setRefTransId((string)$data['txID']);
        if (!empty($data['amount'])) { $txn->setAmount(number_format($data['amount'], 2, '.', '')); }
        return $this->runTransaction($txn);
    }

    /**
     * Refund a settled transaction.
     * Required: txID + amount + last4. The caller layer (paymentMain::refund) pulls
     * last4 from the original payment's stored description hint.
     * Returns ok=true with code='skipped' when refunds are disabled or the prior
     * txID/last4 isn't recoverable — preserves the legacy "non-fatal skip" so a
     * customer-refund credit memo can still post even if the gateway side fails.
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
        if (empty($data['last4'])) {
            msgAdd('Authorize.net refund requires the last 4 digits of the original card; the legacy stored description was missing the hint. Refund must be issued at the Authorize.net portal.', 'caution');
            return $this->success('', 'skipped', 'Missing last4 — non-fatal skip');
        }
        $cc = new AnetAPI\CreditCardType();
        $cc->setCardNumber(str_pad(substr((string)$data['last4'], -4), 4, '0', STR_PAD_LEFT));
        $cc->setExpirationDate('XXXX');
        $pay = new AnetAPI\PaymentType();
        $pay->setCreditCard($cc);
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('refundTransaction');
        $txn->setAmount(number_format($data['amount'], 2, '.', ''));
        $txn->setPayment($pay);
        $txn->setRefTransId((string)$data['txID']);
        return $this->runTransaction($txn);
    }

    /**
     * Void an unsettled transaction. Accepts either txID or rID (looks up trans_code
     * from journal_item when only rID is provided — supports the phreebooks/main.php
     * same-day-delete path that holds the journal_main record id).
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
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('voidTransaction');
        $txn->setRefTransId($txID);
        return $this->runTransaction($txn);
    }

    private function pmtWalletCapture($data)
    {
        // Auto-resolve the three required fields from the ledger + POST when
        // they're not pre-supplied. This is what lets payment/main.php:sale()
        // route stored-card sales here just by passing the ledger handle —
        // the gateway-specific lookups (Authorize.net customer profile ID,
        // payment profile ID) live inside the gateway where they belong,
        // not in the generic dispatcher.
        //
        // Pre-supplied calls (programmatic use, tests) still work — the
        // !empty() guards mean we only fill in what's missing.
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (empty($data['payID'])) {
            // The selCards dropdown POSTs the gateway's stored payment-profile ID.
            // Convention matches the rest of this file: <code>selCards (no underscore).
            $data['payID'] = clean("{$this->code}selCards", 'numeric', 'post');
        }
        if (empty($data['custID']) && $ledger && !empty($ledger->main['contact_id_b'])) {
            $cID = (int)$ledger->main['contact_id_b'];
            // cachedCustID short-circuits a duplicate lookup if walletCustCreate
            // or an earlier wallet() call already resolved this profile in the
            // same request.
            $data['custID'] = $this->cachedCustID ?: $this->lookupCustomerProfileId(getWalletID($cID));
        }
        if (empty($data['amount']) && $ledger && isset($ledger->main['total_amount'])) {
            $data['amount'] = $ledger->main['total_amount'];
        }
        if (empty($data['custID']) || empty($data['payID'])) { return $this->failure('custID and payID required for wallet capture'); }
        if (empty($data['amount'])) { return $this->failure('Amount required for wallet capture'); }
        $profile = new AnetAPI\CustomerProfilePaymentType();
        $profile->setCustomerProfileId((string)$data['custID']);
        $payProf = new AnetAPI\PaymentProfileType();
        $payProf->setPaymentProfileId((string)$data['payID']);
        $profile->setPaymentProfile($payProf);
        $txn = new AnetAPI\TransactionRequestType();
        $txn->setTransactionType('authCaptureTransaction');
        $txn->setAmount(number_format($data['amount'], 2, '.', ''));
        $txn->setProfile($profile);
        if (!empty($data['ledger'])) { $txn->setOrder($this->buildOrder($data['ledger']->main)); }
        return $this->runTransaction($txn);
    }

    // ========================================================================
    // wallet() action implementations
    // ========================================================================

    private function walletCustCreate($data)
    {
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (!$ledger) { return $this->failure('Ledger required for customer profile creation'); }
        $cID = !empty($ledger->main['contact_id_b']) ? (int)$ledger->main['contact_id_b'] : 0;
        if (!$cID) { return $this->failure('contact_id_b required to derive wallet ID'); }
        $profile = new AnetAPI\CustomerProfileType();
        $profile->setMerchantCustomerId(getWalletID($cID));
        if (!empty($ledger->main['email_b']))       { $profile->setEmail($ledger->main['email_b']); }
        if (!empty($ledger->main['primary_name_b'])){ $profile->setDescription(substr($ledger->main['primary_name_b'], 0, 255)); }
        // Attach one payment profile if the form has a CC number
        $ccNum = clean("{$this->code}_number", 'numeric', 'post');
        if (!empty($ccNum)) {
            $payProf = new AnetAPI\CustomerPaymentProfileType();
            $payProf->setCustomerType('individual');
            $payProf->setBillTo($this->buildBillTo($ledger->main));
            $pay = new AnetAPI\PaymentType();
            $pay->setCreditCard($this->buildCreditCardFromPost());
            $payProf->setPayment($pay);
            $profile->setPaymentProfiles([$payProf]);
        }
        $request = new AnetAPI\CreateCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setRefId('ref' . time());
        $request->setProfile($profile);
        $controller = new AnetController\CreateCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') {
            // E00039 = duplicate profile already exists for this MerchantCustomerId.
            // Recover the existing profile ID and attach the new card to it instead of failing.
            $msgs = $response->getMessages() ? $response->getMessages()->getMessage() : [];
            $code = !empty($msgs[0]) ? (string)$msgs[0]->getCode() : '';
            if ($code === 'E00039' && !empty($ccNum)) {
                $existingID = $this->extractDuplicateProfileId(!empty($msgs[0]) ? (string)$msgs[0]->getText() : '');
                if (!$existingID) { $existingID = $this->lookupCustomerProfileId(getWalletID($cID)); }
                if ($existingID) {
                    msgDebug("\nAuthorize.net profile already exists for ".getWalletID($cID).", attaching card to custID=$existingID");
                    return $this->wallet('wltNew', ['custID'=>$existingID, 'ledger'=>$ledger]);
                }
            }
            return $this->describeError($response);
        }
        $payIDs = $response->getCustomerPaymentProfileIdList() ?: [];
        return $this->success(
            $response->getCustomerProfileId(),
            'Ok',
            'Customer profile created',
            ['custID'=>$response->getCustomerProfileId(), 'payIDs'=>is_array($payIDs) ? $payIDs : []],
            $response
        );
    }

    /** E00039 message text is e.g. "A duplicate record with ID 12345678 already exists." */
    private function extractDuplicateProfileId($text)
    {
        if (preg_match('/ID\s+(\d+)/', (string)$text, $m)) { return $m[1]; }
        return '';
    }

    /** Fallback when the duplicate-message text doesn't include the existing profile ID. */
    private function lookupCustomerProfileId($merchantCustomerId)
    {
        if ($this->cachedCustID) { return $this->cachedCustID; }
        $request = new AnetAPI\GetCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setMerchantCustomerId((string)$merchantCustomerId);
        $controller = new AnetController\GetCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response || !$response->getMessages() || $response->getMessages()->getResultCode() != 'Ok') { return ''; }
        $profile = $response->getProfile();
        if (!$profile) { return ''; }
        $this->cachedCustID = (string)$profile->getCustomerProfileId();
        return $this->cachedCustID;
    }

    private function walletCustGet($data)
    {
        if (empty($data['custID'])) { return $this->failure('custID required'); }
        $request = new AnetAPI\GetCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setCustomerProfileId((string)$data['custID']);
        $controller = new AnetController\GetCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        $profile = $response->getProfile();
        return $this->success(
            $profile ? $profile->getCustomerProfileId() : '',
            'Ok',
            'Customer profile retrieved',
            ['profile'=>$profile],
            $response
        );
    }

    private function walletCustGetIDs($data)
    {
        $request = new AnetAPI\GetCustomerProfileIdsRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $controller = new AnetController\GetCustomerProfileIdsController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        $ids = $response->getIds() ?: [];
        return $this->success('', 'Ok', 'Customer profile IDs retrieved', ['ids'=>$ids], $response);
    }

    private function walletCustUpdate($data)
    {
        if (empty($data['custID'])) { return $this->failure('custID required'); }
        $profile = new AnetAPI\CustomerProfileExType();
        $profile->setCustomerProfileId((string)$data['custID']);
        if (!empty($data['merchantCustomerID'])) { $profile->setMerchantCustomerId((string)$data['merchantCustomerID']); }
        if (!empty($data['email']))              { $profile->setEmail($data['email']); }
        if (!empty($data['description']))        { $profile->setDescription(substr((string)$data['description'], 0, 255)); }
        $request = new AnetAPI\UpdateCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setProfile($profile);
        $controller = new AnetController\UpdateCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success((string)$data['custID'], 'Ok', 'Customer profile updated', [], $response);
    }

    private function walletCustDelete($data)
    {
        if (empty($data['custID'])) { return $this->failure('custID required'); }
        $request = new AnetAPI\DeleteCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setCustomerProfileId((string)$data['custID']);
        $controller = new AnetController\DeleteCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success((string)$data['custID'], 'Ok', 'Customer profile deleted', [], $response);
    }

    private function walletPayNew($data)
    {
        if (empty($data['custID'])) { return $this->failure('custID required'); }
        $ledger = !empty($data['ledger']) ? $data['ledger'] : null;
        if (!$ledger) { return $this->failure('Ledger required for billing info'); }
        $payProf = new AnetAPI\CustomerPaymentProfileType();
        $payProf->setCustomerType('individual');
        $payProf->setBillTo($this->buildBillTo($ledger->main));
        $pay = new AnetAPI\PaymentType();
        $pay->setCreditCard($this->buildCreditCardFromPost());
        $payProf->setPayment($pay);
        $request = new AnetAPI\CreateCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setCustomerProfileId((string)$data['custID']);
        $request->setPaymentProfile($payProf);
        $controller = new AnetController\CreateCustomerPaymentProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success(
            $response->getCustomerPaymentProfileId(),
            'Ok',
            'Payment profile created',
            ['payID'=>$response->getCustomerPaymentProfileId()],
            $response
        );
    }

    private function walletPayGet($data)
    {
        if (empty($data['custID']) || empty($data['payID'])) { return $this->failure('custID and payID required'); }
        $request = new AnetAPI\GetCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setCustomerProfileId((string)$data['custID']);
        $request->setCustomerPaymentProfileId((string)$data['payID']);
        $controller = new AnetController\GetCustomerPaymentProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success(
            (string)$data['payID'],
            'Ok',
            'Payment profile retrieved',
            ['paymentProfile'=>$response->getPaymentProfile()],
            $response
        );
    }

    /**
     * Build a dropdown-friendly list of stored cards for a Bizuno contact.
     * Public wallet-provider entry point — same signature as PayFabric's walletList()
     * so paymentWallet can dispatch to either gateway interchangeably.
     *
     * @param string $pfID - Bizuno wallet ID, e.g. "C000000123" (see getWalletID())
     * @return array [['id'=>paymentProfileId, 'text'=>'Visa - 1234', 'hint'=>'1234', 'type'=>'credit', 'isAmex'=>bool, 'CardName'=>str, 'CardHolder'=>[...], 'Billto'=>[...]], ...]
     *
     * The Authorize.net customerProfileId is cached on the instance so subsequent
     * walletDelete()/wallet-capture calls in the same request avoid a second round-trip.
     */
    public function walletList($pfID)
    {
        if (empty($pfID)) { return []; }
        $request = new AnetAPI\GetCustomerProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setMerchantCustomerId((string)$pfID);
        $controller = new AnetController\GetCustomerProfileController($request);
        $response = $this->execute($controller);
        if (!$response || !$response->getMessages() || $response->getMessages()->getResultCode() != 'Ok') { return []; }
        $profile = $response->getProfile();
        if (!$profile) { return []; }
        $this->cachedCustID = (string)$profile->getCustomerProfileId();
        $cards = [];
        foreach ($profile->getPaymentProfiles() ?: [] as $pp) {
            $pay = $pp->getPayment();
            $cc  = $pay ? $pay->getCreditCard() : null;
            if (!$cc) { continue; } // skip eChecks for now
            $masked = (string)$cc->getCardNumber(); // typically "XXXX1234"
            $hint   = substr($masked, -4);
            $type   = (string)$cc->getCardType();   // may be empty on some accounts
            $label  = ($type !== '' ? $type : 'Card') . ' - ' . $hint;
            $bill   = $pp->getBillTo();
            $cards[] = [
                'id'      => (string)$pp->getCustomerPaymentProfileId(),
                'text'    => $label,
                'hint'    => $hint,
                'type'    => 'credit',
                'isAmex'  => stripos($type, 'american') !== false,
                'CardName'=> $type,
                'CardHolder'=> [
                    'FirstName' => $bill ? (string)$bill->getFirstName() : '',
                    'LastName'  => $bill ? (string)$bill->getLastName()  : '',
                ],
                'Billto'  => [
                    'Line1'   => $bill ? (string)$bill->getAddress() : '',
                    'Line2'   => '',
                    'Line3'   => '',
                    'City'    => $bill ? (string)$bill->getCity()    : '',
                    'State'   => $bill ? (string)$bill->getState()   : '',
                    'Zip'     => $bill ? (string)$bill->getZip()     : '',
                    'Phone'   => $bill ? (string)$bill->getPhoneNumber() : '',
                    'Email'   => '',
                ],
            ];
        }
        return $cards;
    }

    /**
     * Wallet-provider entry point, delete a stored card.
     * Mirrors PayFabric's walletDelete($cardID) signature; the optional $pfID lets us
     * recover the customerProfileId without a separate setter when the cache is cold.
     */
    public function walletDelete($cardID='', $pfID=null)
    {
        if (empty($cardID)) { return false; }
        $custID = $this->cachedCustID;
        if (empty($custID) && !empty($pfID)) { $custID = $this->lookupCustomerProfileId((string)$pfID); }
        if (empty($custID)) { msgAdd('Could not locate Authorize.net customer profile for delete'); return false; }
        $r = $this->wallet('wltDelete', ['custID'=>$custID, 'payID'=>$cardID]);
        return !empty($r['ok']);
    }

    /**
     * Wallet-provider entry point, called by paymentWallet::reload() after add/edit.
     * Same shape as PayFabric's walletReload(): mutates $layout with a JS action
     * that re-hydrates the gateway's stored-card dropdown on the cash-receipt form.
     */
    public function walletReload(&$layout=[], $pfID=0)
    {
        $output = [];
        foreach ($this->walletList($pfID) as $card) { $output[] = ['id'=>$card['id'], 'text'=>$card['text']]; }
        $action = "sel_{$this->code}selCards = ".json_encode($output)."; bizSelReload('{$this->code}selCards', sel_{$this->code}selCards);";
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>$action]]);
    }

    private function walletPayDelete($data)
    {
        if (empty($data['custID']) || empty($data['payID'])) { return $this->failure('custID and payID required'); }
        $request = new AnetAPI\DeleteCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setCustomerProfileId((string)$data['custID']);
        $request->setCustomerPaymentProfileId((string)$data['payID']);
        $controller = new AnetController\DeleteCustomerPaymentProfileController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success((string)$data['payID'], 'Ok', 'Payment profile deleted', [], $response);
    }

    // ========================================================================
    // report() action implementations
    // ========================================================================

    private function rptBatch($data)
    {
        if (empty($data['batchID'])) { return $this->failure('batchID required'); }
        $request = new AnetAPI\GetTransactionListRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setBatchId((string)$data['batchID']);
        $controller = new AnetController\GetTransactionListController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success(
            (string)$data['batchID'],
            'Ok',
            'Transaction list retrieved',
            ['transactions'=>$response->getTransactions()],
            $response
        );
    }

    private function rptTrans($data)
    {
        if (empty($data['txID'])) { return $this->failure('txID required'); }
        $request = new AnetAPI\GetTransactionDetailsRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setTransId((string)$data['txID']);
        $controller = new AnetController\GetTransactionDetailsController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if ($response->getMessages()->getResultCode() != 'Ok') { return $this->describeError($response); }
        return $this->success(
            (string)$data['txID'],
            'Ok',
            'Transaction details retrieved',
            ['transaction'=>$response->getTransaction()],
            $response
        );
    }

    // ========================================================================
    // SDK plumbing — environment, auth, request runner, response parsing
    // ========================================================================

    private function env()
    {
        return ($this->settings['mode'] ?? 'test') === 'prod' ? ANetEnvironment::PRODUCTION : ANetEnvironment::SANDBOX;
    }

    private function merchantAuthentication()
    {
        $auth = new AnetAPI\MerchantAuthenticationType();
        $auth->setName($this->settings['user_id']);
        $auth->setTransactionKey($this->settings['txn_key']);
        return $auth;
    }

    /**
     * Wraps CreateTransactionController execution + normalization.
     * Used by every action that posts a TransactionRequestType (capture/auth/refund/void/etc).
     */
    private function runTransaction($txnRequestType)
    {
        $request = new AnetAPI\CreateTransactionRequest();
        $request->setMerchantAuthentication($this->merchantAuthentication());
        $request->setRefId('ref' . time());
        $request->setTransactionRequest($txnRequestType);
        $controller = new AnetController\CreateTransactionController($request);
        $response = $this->execute($controller);
        if (!$response) { return $this->failure('Gateway communication error'); }
        if (!$response->getMessages() || $response->getMessages()->getResultCode() != 'Ok') {
            return $this->describeError($response);
        }
        $tresponse = $response->getTransactionResponse();
        if (!$tresponse || !$tresponse->getMessages()) { return $this->describeError($response); }
        return $this->success(
            (string)$tresponse->getTransId(),
            (string)$tresponse->getAuthCode(),
            (string)$tresponse->getMessages()[0]->getDescription(),
            ['responseCode'=>$tresponse->getResponseCode(), 'accountNumber'=>$tresponse->getAccountNumber()],
            $response
        );
    }

    /**
     * Runs an SDK controller against the configured environment, catching transport errors.
     * Returns the SDK response object, or null on transport failure.
     */
    private function execute($controller)
    {
        try {
            return $controller->executeWithApiResponse($this->env());
        } catch (\Throwable $e) {
            msgDebug("\nAuthorize.net exception: ".$e->getMessage());
            return null;
        }
    }

    /** Extract a usable error message + code from any SDK response, surface to user, return normalized failure. */
    private function describeError($response)
    {
        $code = '';
        $text = $this->lang['err_process_failed'];
        $tresponse = method_exists($response, 'getTransactionResponse') ? $response->getTransactionResponse() : null;
        if ($tresponse && $tresponse->getErrors()) {
            $code = (string)$tresponse->getErrors()[0]->getErrorCode();
            $text = (string)$tresponse->getErrors()[0]->getErrorText();
        } else {
            $msgs = $response->getMessages() ? $response->getMessages()->getMessage() : [];
            if (!empty($msgs[0])) {
                $code = (string)$msgs[0]->getCode();
                $text = (string)$msgs[0]->getText();
            }
        }
        msgAdd($this->lang['err_process_failed'].' '.$text);
        msgDebug("\nAuthorize.net error: [$code] $text");
        return ['ok'=>false, 'txID'=>'', 'code'=>$code, 'msg'=>$text, 'data'=>[], 'raw'=>$response];
    }

    private function success($txID='', $code='', $msg='', $data=[], $raw=null)
    {
        return ['ok'=>true, 'txID'=>$txID, 'code'=>$code, 'msg'=>$msg, 'data'=>$data, 'raw'=>$raw];
    }

    private function failure($msg='')
    {
        if ($msg) { msgAdd($msg); msgDebug("\nAuthorize.net failure: $msg"); }
        return ['ok'=>false, 'txID'=>'', 'code'=>'', 'msg'=>$msg, 'data'=>[], 'raw'=>null];
    }

    private function notImplemented($action)
    {
        msgAdd("Authorize.net action '$action' is not implemented yet.");
        return ['ok'=>false, 'txID'=>'', 'code'=>'not_implemented', 'msg'=>"not implemented: $action", 'data'=>[], 'raw'=>null];
    }

    // ========================================================================
    // Request-object builders (shared across actions)
    // ========================================================================

    private function buildOrder($main)
    {
        $order = new AnetAPI\OrderType();
        if (!empty($main['invoice_num'])) { $order->setInvoiceNumber(substr((string)$main['invoice_num'], 0, 20)); }
        if (!empty($main['description']))  { $order->setDescription(substr((string)$main['description'], 0, 255)); }
        return $order;
    }

    private function buildBillTo($main)
    {
        $parts = explode(' ', $main['primary_name_b'] ?? '', 2);
        $addr = new AnetAPI\CustomerAddressType();
        $addr->setFirstName(substr($parts[0] ?? '', 0, 50));
        $addr->setLastName(substr($parts[1] ?? '', 0, 50));
        if (!empty($main['primary_name_b'])){ $addr->setCompany(substr($main['primary_name_b'], 0, 50)); }
        if (!empty($main['address1_b']))    { $addr->setAddress(substr($main['address1_b'], 0, 60)); }
        if (!empty($main['city_b']))        { $addr->setCity(substr($main['city_b'], 0, 40)); }
        if (!empty($main['state_b']))       { $addr->setState(substr($main['state_b'], 0, 40)); }
        if (!empty($main['postal_code_b'])) { $addr->setZip(preg_replace('/[^A-Za-z0-9]/','',$main['postal_code_b'])); }
        if (!empty($main['country_b']))     { $addr->setCountry(substr($main['country_b'], 0, 60)); }
        return $addr;
    }

    private function buildCustomerData($main)
    {
        $customer = new AnetAPI\CustomerDataType();
        $customer->setType('individual');
        if (!empty($main['contact_id_b'])) { $customer->setId((string)$main['contact_id_b']); }
        if (!empty($main['email_b']))      { $customer->setEmail($main['email_b']); }
        return $customer;
    }

    private function buildCreditCardFromPost()
    {
        $cc = new AnetAPI\CreditCardType();
        $cc->setCardNumber((string)clean("{$this->code}_number", 'numeric', 'post'));
        $month = clean("{$this->code}_month", 'numeric', 'post');
        $year  = clean("{$this->code}_year",  'integer', 'post');
        $cc->setExpirationDate(sprintf('%04d-%02d', (int)$year, (int)$month));
        $cvv = clean("{$this->code}_cvv", 'numeric', 'post');
        if (!empty($cvv)) { $cc->setCardCode((string)$cvv); }
        return $cc;
    }

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
