<?php
/*
 * Payment Method - Payfabric (EVO Payments)
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
 * @version    7.x Last Update: 2026-01-24
 * @filesource /controllers/payment/gateways/payfabric.php
 *
 * Source Information:
 * © 2022 PayFabric from EVO Payments
 * @link https://github.com/PayFabric - Main Documentation Site
 *
 * instructions on where/how to get account and fill out settings
 * setting for types of cards/payment to accept
 * setting to void or delete for same day journal deletions, returns for posted payments
 * setting to require AVS or no charge, notify/process anyway
 * settings for authorize only, sale, delete, void, return/credit, AVS (address verification)
 * accept credit cards, debit cards, EBT (Food Stamps), OPTIONAL, Gift Cards, electronic checks, PINless debit
 * OPTIONAL tip processing, EBT balance inquiry, Gift Card Balance inquiry, recurring payments, installments, attach signature
 */

namespace bizuno;

if (!defined('PAYMENT_PAYFABRIC_TOKEN'))     { define('PAYMENT_PAYFABRIC_TOKEN',     'https://www.payfabric.com/payment/api/token/create'); }
if (!defined('PAYMENT_PAYFABRIC_TOKEN_TEST')){ define('PAYMENT_PAYFABRIC_TOKEN_TEST','https://sandbox.payfabric.com/payment/api/token/create'); }
if (!defined('PAYMENT_PAYFABRIC_URL'))       { define('PAYMENT_PAYFABRIC_URL',       'https://www.payfabric.com/'); }
if (!defined('PAYMENT_PAYFABRIC_URL_TEST'))  { define('PAYMENT_PAYFABRIC_URL_TEST',  'https://sandbox.payfabric.com/'); }

class payfabric
{
    public  $moduleID = 'payment';
    public  $methodDir= 'gateways';
    public  $code     = 'payfabric';
    private $mode     = 'prod'; // choices are 'test' (Test) or 'prod' (Production)
    public  $defaults;
    public  $settings;
    public  $mains;
    public  $cID;
    public  $lang     = ['title' => 'PayFabric',
        'description' => 'Accept credit card payments through the PayFabric payment gateway.',
        'at_payfabric'=> '@PayFabric',
        'setup_id'    => 'Setup ID (provided by PhreeSoft)',
        'device_id'   => 'Device ID (provided by PhreeSoft)',
        'device_pw'   => 'Device Password (provided by PhreeSoft)',
        'auth_type'   => 'Authorization Type',
        'prefix_amex' => 'Prefix to use for American Express credit cards. (These cards are processed and reconciled through American Express)',
        'allow_refund'=> 'Allow Void/Refunds? This must be enabled by PayFabric for your merchant account or refunds will not be allowed.',
        'msg_capture_at_payfabric' => 'The payment has already been captured. Any post order actions must be completed at PayFabric gateway.',
        'msg_void_success'   => 'The VOID at PayFabric was Successful - PayFabric Response: %s - Approval code: %s',
        'msg_refund_success' => 'The REFUND at PayFabric was Successful - PayFabric Response: %s - Approval code: %s',
        'msg_address_result' => 'Address verification results: %s',
        'err_process_decline'=> 'Decline Code #%s: %s',
        'err_process_failed' => 'The credit card did not process, the response from PayFabric:',
        // new AVS responses
        'AVS_Match' => 'AVS Match',
        'AVS_NoMatch' => 'No match on address (street) or postal code.',
        'AVS_NotVerified' => 'PayFabric retured status Not Verified',
        'CVV_NotSet' => 'CVV Code Not Set'];

    public function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $pmtDef        = getModuleCache($this->moduleID, 'settings', 'general', false, []);
        $this->defaults= ['cash_gl_acct'=>$pmtDef['gl_payment_c'],'disc_gl_acct'=>$pmtDef['gl_discount_c'],'order'=>10,
            'setup_id'=>'','device_id'=>'','device_pw'=>'','prefix'=>'CC','allowRefund'=>'0']; // ,'prefixAX'=>'AX'
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        return [
            'cash_gl_acct'=> ['label'=>$this->lang['gl_payment_c_lbl'], 'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_cash_gl_acct",'value'=>$this->settings['cash_gl_acct']]],
            'disc_gl_acct'=> ['label'=>$this->lang['gl_discount_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_disc_gl_acct",'value'=>$this->settings['disc_gl_acct']]],
            'order'       => ['label'=>lang('order'),             'position'=>'after','attr'=>['type'=>'integer', 'size'=>'3','value'=>$this->settings['order']]],
            'setup_id'    => ['label'=>$this->lang['setup_id'],   'position'=>'after','attr'=>['size'=>'32','value'=>$this->settings['setup_id']]],
            'device_id'   => ['label'=>$this->lang['device_id'],  'position'=>'after','attr'=>['size'=>'48','value'=>$this->settings['device_id']]],
            'device_pw'   => ['label'=>$this->lang['device_pw'],  'position'=>'after','attr'=>['size'=>'24','value'=>$this->settings['device_pw']]],
            'prefix'      => ['label'=>$this->lang['prefix_lbl'], 'position'=>'after','attr'=>['size'=> '5','value'=>$this->settings['prefix']]],
            'allowRefund' => ['label'=>$this->lang['allow_refund'],                   'attr'=>['type'=>'selNoYes','value'=>$this->settings['allowRefund']]]];
    }

    public function render($layout, $values=[], $dispFirst=false)
    {
        msgDebug("\nEntering $this->code render, working with values = ".print_r($values, true));
        $show_c = $show_s = false;
        $this->cID = clean($layout['fields']['contact_id_b']['attr']['value'], 'integer');
        $fields= [
            'payment_id'=> ['attr'=>['type'=>'hidden']],
            'trans_code'=> ['attr'=>['type'=>'hidden']],
            'selWallet' => ['values'=>[],'attr'=>['type'=>'select'],       'events'=>['onChange'=>"{$this->code}RefNum('stored');"]],
            'refresh'   => ['attr'=>['type'=>'button','value'=>'Reload'],  'events'=>['onClick'=>"jsonAction('payment/wallet/reload', $this->cID);"]],
            'addCard'   => ['attr'=>['type'=>'button','value'=>'Add Card'],'events'=>['onClick'=>"jsonAction('payment/wallet/add',$this->cID);"]]];
        if (!empty($values['method']) && $values['method']==$this->code && !empty($layout['fields']['id']['attr']['value'])) { // edit
            $invoice_num= $layout['fields']['invoice_num']['attr']['value'];
            $gl_account = $layout['fields']['gl_acct_id']['attr']['value'];
            $discount_gl= $this->getDiscGL($layout['fields']['id']['attr']['value']);
            $checked    = 'w';
        } else { // defaults
            $checked= 's';
            $invoice_num= $this->settings['prefix'].biz_date('Ymd');
            $gl_account = $this->settings['cash_gl_acct'];
            $discount_gl= $this->settings['disc_gl_acct'];
            if (!empty($this->cID)) { // find if stored values
                $pfID  = 'C'.str_pad($this->cID, 9, '0', STR_PAD_LEFT);
                $temp  = $this->walletList($pfID);
                $cards = [];
                foreach ($temp as $card) { $cards[] = ['id'=>$card['id'],'text'=>$card['text']]; } // remove the extra data
                $fields['selWallet']['values'] = $cards;
            }
            if (!empty($values['trans_code'])) {
                $fields['trans_code']['attr']['value'] = $values['trans_code'];
                $checked = 'w';
                if (!empty($values['status']) && $values['status']=='auth') {
                    $show_c =  true;
                    $checked= 'c';
                } else { // capture done at the cart, show message
                    msgAdd("Payment for this order was captured by {$this->lang['title']} at the store checkout, the {$this->lang['at_payfabric']} box should be checked to avoid duplicate charges.", 'info');
                }
            }
        }
        htmlQueue("arrPmtMethod['$this->code'] = {cashGL:'$gl_account', discGL:'$discount_gl', ref:'$invoice_num'};\n".$this->eventJS($this->cID), 'jsHead');
        if ($this->code == $dispFirst) { htmlQueue("bizTextSet('invoice_num', '$invoice_num');", 'jsReady'); }
        $html  =
html5($this->code.'_action', ['label'=>lang('capture'),            'attr'=>['type'=>'radio','value'=>'c','checked'=>$checked=='c'?true:false],'hidden'=>$show_c?false:true,
    'events'=>['onChange'=>"jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}w').hide(); jqBiz('#div{$this->code}c').show();"]]).
html5($this->code.'_action', ['label'=>$this->lang['stored'],      'attr'=>['type'=>'radio','value'=>'s','checked'=>$checked=='s'?true:false],
    'events'=>['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}w').hide(); jqBiz('#div{$this->code}s').show();"]]).
html5($this->code.'_action', ['label'=>$this->lang['at_payfabric'],'attr'=>['type'=>'radio','value'=>'w','checked'=>$checked=='w'?true:false],
    'events'=>['onChange'=>"jqBiz('#div{$this->code}c').hide(); jqBiz('#div{$this->code}s').hide(); jqBiz('#div{$this->code}w').show();"]]).'<br />';
        $html .= '<div id="div'.$this->code.'c"'.($show_c?'':'style=" display:none"').'>';
        if ($show_c) {
            msgDebug("\nRendering trans_code html.");
            $html .= html5($this->code.'trans_code',$fields['trans_code']).sprintf(lang('msg_capture_payment'), viewFormat($values['total'],'currency'));
        }
        $html .= '</div>';
        $html .= '<div id="div'.$this->code.'s"'.(!$show_c?'':'style=" display:none"').'>';
        $html .= lang('payment_stored_cards').'<br />'.html5($this->code.'selWallet', $fields['selWallet']);
        $html .= '<p>'.html5($this->code.'addCard',  $fields['refresh']).'&nbsp;&nbsp;&nbsp;';
        $html .= html5($this->code.'addCard',  $fields['addCard']).'</p>';
        $html .= '</div>'; // the card ID provived
        return $html;
    }

    public function eventJS($cID)
    {
        $subDom = ($this->mode=='test' ? 'sandbox' : 'www');
        return "
function payment_$this->code() {
    bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
    bizSelSet('gl_acct_id', arrPmtMethod['$this->code'].cashGL);
    bizSelSet('totals_discount_gl', arrPmtMethod['$this->code'].discGL);
}
function {$this->code}RefNum(type) {
    if (type=='stored') { var ccNum = jqBiz('#{$this->code}selWallet').val(); }
      else { var ccNum = jqBiz('#{$this->code}_number').val();  }
    bizTextSet('invoice_num', arrPmtMethod['$this->code'].ref);
}
function payFabricSale() { payFabricCardID = bizSelGet('{$this->code}selWallet'); }
function {$this->code}WalletEvent(event) {
    var objMsg;
    objMsg = JSON.parse(event.data);
    if (event.origin !== 'https://$subDom.payfabric.com') { return; }
    switch(objMsg.Event) {
        case 'btn_Close':                  jqBiz('#pmtIFrame').remove();        break;
        case 'OnSaveTransactionCompleted': alert('OnSaveTransactionCompleted'); break;
        case 'OnTransactionCompleted':     alert('OnTransactionCompleted');     break;
        case 'OnWalletCreateCompleted':
        case 'OnWalletUpdateCompleted':
            jqBiz('#pmtIFrame').remove();
            window.removeEventListener('message', {$this->code}WalletEvent, false);
            bizWindowClose('winIFrame');
            if (jqBiz('#wallet').length)                { bizPanelRefresh('wallet'); }
            if (jqBiz('#{$this->code}selWallet').length){ jsonAction('payment/wallet/reload', $cID); }
            break;
    }
}
window.addEventListener('message', {$this->code}WalletEvent, false);";
    }

/* methods to write
** Version 3.0 **
* Payment Gateway Profiles
- Retrieve a Payment Gateway Profile
- Retrieve Payment Gateway Profiles
- Manual Batch Close
* Addresses
- Retrieve a Shipping Address
- Retrieve Shipping Addresses
* JSON Web Tokens
- Create JWT Token
- Validate JWT Token
** Version 3.1 **
* Email Transaction Receipt
- Send a Transaction Receipt
* Email Transaction Receipt Template
- Get Email Receipt Templates
- Update Email Receipt Template
* Batched Transactions
- Get Current Batches
- Process Batch
- Delete Batch
- Search by Batch Number
* Scheduled Transactions
- Search for Future Dated Transactions
* Custom Reports
- Get Custom Reports
- Create Custom Report
- Edit Custom Report
- Delete Custom Report
- Manual Execute Custom Report
* Shipping Addresses
- Create Shipping Address
- Delete Shipping Address
* Payment Terminal
- Get Registered Terminals
- Create new Registered Terminal
- Update Registered Terminal
- Remove Registered Terminal
- Get Terminal Settings
- Update Terminal Settings
* Products
- Get Products
- Create Product
- Edit Product
- Delete Product
- Upload Products
* Theme
- Get Themes
- Create Theme
- Update Theme
- Delete Theme
* Transaction Adjustments
- Transaction Adjustments
*/
    // ***************************************************************************************************************
    //                                Transaction Methods
    // ***************************************************************************************************************
    /**
     * @param type $fields
     * @param type $ledger
     * @return type
     */
    public function authorization()
    {
        msgAdd('PhreeSoft is working on this feature, please process credit cards directly on the PayFabric website.');
        // The user has selected a card from the list or entered a new card, at this point we have the cardID
//        $cardID = clean('cardID', 'alpha_num', 'get');
        // do the transaction to just authorize the card
        // if failed, return false, else return array
//        return ['txID'=>$resp->ssl_txn_id, 'txTime'=>$resp->ssl_txn_time, 'code'=>$resp->ssl_approval_code];
    }

    /**
     * 'process' - Captures a pre-authorized sale, requires transaction ID for authorization
     * @param type $fields
     * @param type $ledger
     */
    public function capture()
    {
        msgAdd('PhreeSoft is working on this feature, please process credit cards directly on the PayFabric website.');
        // The user has selected a card from the list or entered a new card, at this point we have the cardID
//        $cardID = clean('cardID', 'alpha_num', 'get');
        // do the transaction to capture the card
        // if failed, return false, else return array
//        return ['txID'=>$resp->ssl_txn_id, 'txTime'=>$resp->ssl_txn_time, 'code'=>$resp->ssl_approval_code];
    }

    /**
     * This method will capture payment, if payment was authorized in a prior transaction, a ccComplete is done
     * @param integer $ledger
     * @return array - On success, false (with messageStack message) on unsuccessful deletion
     */
    public function sale($fields, $ledger)
    {
        msgDebug("\nEntering $this->code:sale working with fields = ".print_r($fields, true));
        $action = clean("{$this->code}_action", 'db_field', 'post');
        switch ($action) { // figure out what type of sale it is
            case 'c': return $this->saleAuth($ledger); // Capture a pre-authorized transaction, full or partial
            case 's': return $this->saleNew($ledger); // Capture a new transaction
            default:
            case 'w': // website capture, just post it and let the user know
                msgAdd($this->lang['msg_capture_at_payfabric'], 'info');
                return true;
        }
    }

    /*
     * Handles pre-authorized captures, either partial or full
     */
    private function saleAuth($ledger)
    {
        $tOptions = [];
        $invAmount= 0;
        $invID    = '';
        $txID     = clean($this->code.'trans_code', 'integer', 'post');
        foreach ($ledger->items as $item) { // test for partial payment, assume only the first is captured
            if ($item['gl_type']=='pmt') {
                $invID = $item['trans_code'];
                break; // assume only one to capture
            }
        }
        if (!empty($invID)) {
            $invAmount = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'total_amount', "invoice_num='$invID' AND journal_id IN (12, 13)");
            if ($ledger->main['total_amount'] > $invAmount){ return msgAdd(lang('err_cc_amount_too_big')); }
            if ($ledger->main['total_amount'] <= 0)        { return msgAdd(lang('err_cc_amount_negative')); }
            if (empty($txID))                              { msgAdd(lang('err_cc_no_transaction_id'), 'caution'); return true; }
        }
        $partial = $invAmount<>$ledger->main['total_amount'] ? true : false;
        if ($partial) { // process partial payment
            msgDebug("\nProcessing partial payment for invID = $invID");
            $request = ['Amount'=>$ledger->main['total_amount'], 'Type'=>'Capture', 'ReferenceKey'=>$txID,
                'Document'=>['Head'=>[['Name'=>'CaptureComplete', 'Value'=>false]]]];
            $tOptions= [CURLOPT_POSTFIELDS=>json_encode($request)];
            $saleUrl = 'payment/api/transaction/process';
        } else { // process full capture
            msgDebug("\nProcessing full payment for invID = $invID");
            $saleUrl = "payment/api/reference/$txID?trxtype=Capture";
        }
        msgDebug("\nReady to send sale request to url $saleUrl with options: ".print_r($tOptions, true));
        $resp   = $this->queryAPI($saleUrl, $tOptions);
        if (empty($resp)) { return; }
        $txDate = !empty($resp['TrxDateUTC']) ? biz_date('Y-m-d H:i:s', strtotime($resp['TrxDateUTC'])) : biz_date('Y-m-d H:i:s');
        msgAdd(sprintf($this->lang['msg_approval_success'], $resp['Message'], $resp['AuthCode'], 'N/A'), 'success');
        return ['txID'=>$resp['TrxKey'], 'txTime'=>$txDate, 'code'=>$resp['AuthCode']];
    }

    private function saleNew($ledger)
    {
        $custID = getWalletID($ledger->main['contact_id_b']);
        $cardID = clean('payfabricselWallet', 'cmd', 'post');
        if (empty($cardID)) { return msgAdd("No card information was provided!"); }
        if (!$this->walletValidate($custID, $cardID)) {
            return msgAdd("Error - the card ID provived ins't in the customers wallet! Please submit a support ticket right away describing the steps taken to get this error. PhreeSoft needs to get this bug fixed ASAP.", 'trap');
        }
        $this->mains = [];
        foreach ($ledger->items as $item) { // load the invoices
            if ($item['gl_type'] <> 'pmt') { continue; }
            $this->mains[] = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "invoice_num='{$item['trans_code']}' AND journal_id IN (12, 13)");
        }
        $salesTax= $this->calculateTax();
        $request = [
            'Type'    => 'Sale',
            'SetupId' => $this->settings['setup_id'], // 'EVO US_CC', // specific to PhreeSoft Test for CC processing
            'Amount'  => number_format($ledger->main['total_amount'], 2, '.', ''),
            'Card'    => ['ID'=> $cardID],
            'Currency'=> 'USD',
            'Customer'=> $custID,
            'Tender'  => 'CreditCard',
//          'BatchNumber' => '', 'PayDate' => '', 'ReferenceKey' => null, 'ReferenceTrxs' => [], 'ReqAuthCode' => '',
//          'Shipto' => ['City'=>'','Country'=>'','Customer'=>'','Email'=>'','Line1'=>'','Line2'=>'','Line3'=>'','Phone'=>'','State'=>'','Zip'=>''],
//          'TrxUserDefine1' => '', 'TrxUserDefine2' => '', 'TrxUserDefine3' => '', 'TrxUserDefine4' => '',
            'Document'=> [
                'Head'=> [
                    [ 'Name'=> 'InvoiceNumber', 'Value'=> $this->calculateInvoice() ], // cash receipt number, something to track back
                    [ 'Name'=> 'PONumber',      'Value'=> $this->calculatePONumber() ],
                    [ 'Name'=> 'DiscountAmount','Value'=> $ledger->main['discount'] ],
                    [ 'Name'=> 'FreightAmount', 'Value'=> $this->calculateShipping() ],
                    [ 'Name'=> 'HandlingAmount','Value'=> '0.00' ],
                    [ 'Name'=> 'TaxExempt',     'Value'=> empty($salesTax) ? 'Y' : 'N' ],
                    [ 'Name'=> 'TaxAmount',     'Value'=> $salesTax ],
//                  [ 'Name'=> 'DutyAmount',    'Value'=> '0.00' ],
//                  [ 'Name'=> 'VATTaxAmount',  'Value'=> '' ],
//                  [ 'Name'=> 'VATTaxRate',    'Value'=> '' ],
//                  [ 'Name'=> 'ShipFromZip',   'Value'=> '' ],
//                  [ 'Name'=> 'ShipToZip',     'Value'=> '' ],
                    [ 'Name'=> 'OrderDate',     'Value'=> date('m/d/Y', strtotime($ledger->main['post_date'])) ]],
                'UserDefined'=>[
                    [ 'Name'=> 'AppID',         'Value'=>'PhreeSoft_Bizuno']],
                ]];
/*      foreach ($ledger->items as $item) { // Since this can be a bulk payment, we don't know the items. skip for now
            $request['Document']['Lines'][] = [
                'Columns'=> [
                    [ 'Name'=> 'ItemCommodityCode', 'Value'=> '24X BIKE' ],
                    [ 'Name'=> 'ItemProdCode', 'Value'=> 'B872' ],
                    [ 'Name'=> 'ItemUPC', 'Value'=> 'B872' ],
                    [ 'Name'=> 'ItemUOM', 'Value'=> 'SET' ],
                    [ 'Name'=> 'ItemDesc', 'Value'=> 'Mountain Bicycle' ],
                    [ 'Name'=> 'ItemAmount', 'Value'=> '2000.00' ],
                    [ 'Name'=> 'ItemCost', 'Value'=> '112.00' ],
                    [ 'Name'=> 'ItemDiscount', 'Value'=> '100.00' ],
                    [ 'Name'=> 'ItemFreightAmount', 'Value'=> '12.00' ],
                    [ 'Name'=> 'ItemHandlingAmount', 'Value'=> '10.00' ],
                    [ 'Name'=> 'ItemQuantity', 'Value'=> '10' ]]];
        }
*/
        msgDebug("\nReady to send sale request: ".print_r($request, true));
        $transURL= 'payment/api/transaction/create'; // Optional: cvc={CVCValue}
        $tOptions= [CURLOPT_POSTFIELDS=>json_encode($request)]; // removed CURLOPT_VERBOSE=>true,
        $trans   = $this->queryAPI($transURL, $tOptions); // Create the transaction
        if (empty($trans)) { return; }
        $saleUrl = "payment/api/transaction/process/{$trans['Key']}";
        $sale    = $this->queryAPI($saleUrl); // Process the order
        if (empty($sale)) { return; }
        // Process the results
/*  [AVSAddressResponse] =>
    [AVSZipResponse] =>
    [AuthCode] => 34XTZW
    [CVV2Response] => NotSet
    [CardType] => Debit
    [ExpectedSettledTime] => 2022-05-24T03:00:00.0000000Z
    [FinalAmount] => 6.00
    [IAVSAddressResponse] =>
    [Message] => APPROVED
    [OrigTrxAmount] => 6.00
    [OriginationID] => E2666BDA8D834865BB23132D988CFCA7
    [PayFabricErrorCode] =>
    [RespTrxTag] => 5/23/2022 9:03:03 PM
    [ResultCode] => 1
    [SettledTime] =>
    [Status] => Approved
    [SurchargeAmount] => 0.00
    [SurchargePercentage] => 0.0
    [TerminalID] =>
    [TerminalResultCode] =>
    [TrxAmount] => 6.00
    [TrxDate] => 5/23/2022 3:03:03 PM
    [TrxDateUTC] => 5/23/2022 10:03:03 PM
    [TrxKey] => 22052301848600
    [WalletID] => b2624872-6456-4a4b-8778-adfb847a3877
*/
        if ($sale['Status']=='Approved') {
            if (!empty($sale['CVV2Response']) && $sale['CVV2Response'] != 'NotSet') {
                msgAdd(sprintf($this->lang['err_cvv_mismatch'], $this->lang["CVV_{$sale['CVV2Response']}"]));
            }
            if (!empty($sale['AVSZipResponse']) && !in_array($sale['AVSZipResponse'], ['X','Y'])) {
                msgAdd(sprintf($this->lang['err_avs_mismatch'], $this->lang["AVS_{$sale['AVSZipResponse']}"]));
            }
            $cvv = !empty($sale['CVV2Response']) ? $this->lang["CVV_{$sale['CVV2Response']}"] : 'n/a';
            msgAdd(sprintf($this->lang['msg_approval_success'], $sale['Message'], $sale['AuthCode'], $cvv), 'success');
            return ['txID'=>$trans['Key'], 'txTime'=>$sale['TrxDate'], 'code'=>$sale['AuthCode']];
        }
        // else $sale['Status'] => Failure
        msgLog(sprintf($this->lang['err_process_decline'], $sale['PayFabricErrorCode'], $sale['Message']));
        msgAdd(sprintf($this->lang['err_process_decline'], $sale['PayFabricErrorCode'], $sale['Message']));
    }

    /**
     * @method void will delete/void a payment made BEFORE the processor commits the payment, typically must be run the same day as the sale
     * @param integer $rID Record id from table journal_main to generate the void
     * @return array merchant response On success, false (with messageStack message) on unsuccessful deletion
     */
    public function void($rID=0)
    {
        if (!$rID) { return msgAdd('Bad record ID passed'); }
        $txID = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'trans_code', "ref_id=$rID AND gl_type='ttl'");
        if (!$txID || !$this->settings['allowRefund']) { msgAdd(lang('err_cc_no_transaction_id'), 'caution'); return true; }
        // create the transaction
        $voidUrl = "payment/api/reference/$txID?trxtype=Void";
        $void    = $this->queryAPI($voidUrl);
        if (empty($void)) { return; }
        msgLog(sprintf($this->lang['msg_void_success'], $void['Message'], $void['AuthCode']));
        msgAdd(sprintf($this->lang['msg_void_success'], $void['Message'], $void['AuthCode']), 'success');
        return ['txID'=>$txID, 'txTime'=>$void['TrxDate'], 'code'=>$void['AuthCode']];
    }

    /**
     * This method will refund a payment made AFTER the batch is processed, typically must be run any day after the sale
     * @param integer $rID - record id from table journal_main to generate the refund
     * @param float $amount - amount to be refunded (leave blank for full amount)
     * @return array - On success, false (with messageStack message) on unsuccessful deletion
     */
    public function refund($transCode='', $amount=0)
    {
        // This should be working now. Once tested, the below lines can be removed.
// @TODO - This may be an option to be performed during the credit memo refund in banking to save the user the hassle of refunding through the portal.
//return msgAdd("Deleting a Payment after the batch has been processed has been disabled. To refund a customer after the batch has ben processed requires a Credit Memo and then payment applied against it. Then, the credit card refund needs to be completed throught the portal.");
        if (floatval($amount) <= 0) { return msgAdd(lang('err_cc_amount_negative')); }
        $options = [];
        // using this trans code, get ref_id (mID) of the charge and all credits
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "trans_code='$transCode'", 'id');
        msgDebug("\nIn refund with rows from trans code = ".print_r($rows, true));
        $charged = $refunded = 0;
        foreach ($rows as $row) {
            if ($row['debit_amount']>0 || $row['credit_amount']<0) { $charged += $row['debit_amount'] - $row['credit_amount']; }
            if ($row['debit_amount']<0 || $row['credit_amount']>0) { $refunded+= $row['credit_amount']- $row['debit_amount']; }
        }
        msgDebug("\nReady to refund amount $amount, charged = $charged and refunded already = $refunded");
        if (empty($refunded) && $charged==$amount ) { // Full refund
            $transURL = "payment/api/reference/$transCode?trxtype=Refund";
        } else { // partial refund
            $transURL = 'payment/api/transaction/process';
            $transArgs= ['Amount'=>number_format($amount, 2, '.', ''), 'ReferenceKey'=>$transCode, 'Type'=>'Refund'];
            $options  = [CURLOPT_POSTFIELDS=>json_encode($transArgs)]; // removed CURLOPT_VERBOSE=>true,
        }
        msgDebug("\nReady to call API with url $transURL and options = ".print_r($options, true));
        $refund  = $this->queryAPI($transURL, $options);
        if (empty($refund)) { return msgAdd("\nThere was an error processing the transaction, see trace.", 'trap'); }
        msgLog(sprintf($this->lang['msg_refund_success'], $refund['Message'], $refund['AuthCode']));
        msgAdd(sprintf($this->lang['msg_refund_success'], $refund['Message'], $refund['AuthCode']), 'success');
        return ['txID'=>$refund['TrxKey'], 'txTime'=>$refund['TrxDate'], 'code'=>$refund['AuthCode']];
    }

    // ***************************************************************************************************************
    //                                Wallet Methods
    // ***************************************************************************************************************
    /**
     * Retrieves a list of cards and e-checks from the PayFabric wallet
     * @param type $pfID
     */
    public function walletEdit($pfID)
    {
        $httpUrl = "payment/api/wallet/get/$pfID?tender=CreditCard";
        $response= $this->queryAPI($httpUrl);
        $cards   = [];
        if (empty($response)) { return $cards; }
        foreach ($response as $card) {
            $hint = substr($card['Account'], -5);
            $cards[] = [
                'id'  => $card['Billto']['ID'],
                'text'=> "{$card['CardName']} ({$card['CardType']} - $hint)",
                'name'=> $card['CardName'],
                'type'=> $card['CardType'],
                'hint'=> $hint];
        }
        return $cards; // array of e-checks and cards in wallet.
    }

    public function walletDelete($cardID='')
    {
        $httpUrl = "payment/api/wallet/delete/$cardID";
        $response= $this->queryAPI($httpUrl);
        return !empty($response['Result']) && strtolower($response['Result'])=='true' ? true : false;
    }

    /**
     * Retrieves a list of cards and e-checks from the PayFabric wallet
     * @param type $pfID
     */
    public function walletList($pfID)
    {
        msgDebug("\nEntering walletList with pfID=$pfID");
        $httpUrl = "payment/api/wallet/get/$pfID?tender=CreditCard";
        $response= $this->queryAPI($httpUrl);
        $cards   = [];
        if (empty($response)) {
            msgDebug("\nReturning with no cards found!");
            return $cards;
        }
        foreach ($response as $row) {
            $hint = substr($row['Account'], -5);
            $cards[] = array_merge(['id'=>$row['ID'],'text'=>"{$row['CardName']} ({$row['CardType']} - $hint)",'hint'=>$hint], $row);
        }
        msgDebug("\nReturning from walletList with cards = ".print_r($cards, true));
        return sortOrder($cards, 'IsDefaultCard', 'desc'); // array of e-checks and cards in wallet with default first
    }

    public function walletReload(&$layout=[], $pfID=0)
    {
        $output = [];
        $cards = $this->walletList($pfID);
        foreach ($cards as $card) { $output[] = ['id'=>$card['id'], 'text'=>$card['text']]; }
        $action = "sel_{$this->code}selWallet = ".json_encode($output)."; bizSelReload('{$this->code}selWallet', sel_{$this->code}selWallet);";
        $layout  = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>$action]]);
    }

    /**
     * Updates properties for a given customers wallet
     * @param string $pfID - Wallet ID
     * @param type $props - Fields within the wallet to update/change, use ['NewCustomerNumber'=>'CustID_TBD'] to change the CustomerID field
     * @return bool - true on success, false on error
     */
    public function walletRename($pfID, $props=[]) {
        $cards = $this->walletList($pfID); // Get all the cards from a specific wallet
        foreach ($cards as $card) {
            $opts    = array_replace($props, ['ID'=>$card['id']]);
            msgDebug("\nReady to send update request via POST: ".print_r($opts, true));
            $tOptions= [CURLOPT_POSTFIELDS=>json_encode($opts)];
            $resp    = $this->queryAPI('payment/api/wallet/update', $tOptions);
        }
        return !empty($resp['Result']) && strtolower($resp['Result'])=='true' ? true : false;
    }

    /**
     * Validates a cardID is within a customers wallet
     * @param string $custID - Customer ID @PayFabric
     * @param string $cardID - Card ID @PayFabric
     */
    public function walletValidate($custID=0, $cardID='')
    {
        msgDebug("\nEntering walletValidate with custID = $custID and cardID = $cardID");
        $cards = $this->walletList($custID);
        foreach ($cards as $card) {
            if ($card['id']==$cardID) { return true; }
        }
        return false;
    }

    // ***************************************************************************************************************
    //                                Support Methods
    // ***************************************************************************************************************
    private function queryAPI($url, $opts=[])
    {
        $destURL = ($this->mode=='test' ? PAYMENT_PAYFABRIC_URL_TEST : PAYMENT_PAYFABRIC_URL).$url;
        $header  = ["Content-Type: application/json", "authorization: ".$this->settings['device_id']."|".$this->settings['device_pw']];
        $options = array_replace([CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$header], $opts);
        msgDebug("\nEntering PayFabric queryAPI with url: $destURL with options: ".print_r($options, true));
        $handle   = curl_init($destURL);
        curl_setopt_array($handle, $options);
        $respBody = curl_exec($handle);
        $respCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);
        msgDebug("\nResponse Body from PayFabric: ".print_r($respBody, true));
        if ($respCode >= 300) { return msgAdd("PayFabric Transaction Error. Response code: $respCode"); }
        $response = json_decode($respBody, true);
        msgDebug("\ncURL response after decode = ".print_r($response, true));
        if (!empty($response['PayFabricErrorCode'])) {
            return msgAdd("Error: {$response['PayFabricErrorCode']} - {$response['Message']}");
        }
        return $response;
    }

    public function getToken()
    {
        $httpUrl    = $this->mode=='test' ? PAYMENT_PAYFABRIC_TOKEN_TEST : PAYMENT_PAYFABRIC_TOKEN;
        $header = ["Content-Type: application/json", "authorization: ".$this->settings['device_id']."|".$this->settings['device_pw']];
        $curlOptions= [CURLOPT_RETURNTRANSFER=>true, CURLOPT_HTTPHEADER=>$header];
        msgDebug("\nPayFabric url: $httpUrl with header: ".print_r($header, true));
        $curlHandle = curl_init($httpUrl);
        curl_setopt_array($curlHandle, $curlOptions);
        $httpResponseBody = curl_exec($curlHandle);
        $httpResponseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        curl_close($curlHandle);
        msgDebug("\nResponse Body from PayFabric: ".print_r($httpResponseBody, true));
        if ($httpResponseCode >= 300) { return msgAdd("PayFabric Token request Error. Response code: $httpResponseCode"); }
        $response = json_decode($httpResponseBody, TRUE);
        $this->settings['token']     = $response['Token'];
//      $this->settings['token_date']= time()+$response['expires_in']; // no expiration time provided so get a new token everytime
        $methProps = getModuleCache($this->moduleID, $this->methodDir, $this->code);
        $methProps['settings']       = $this->settings;
        setModuleCache($this->moduleID, $this->methodDir, $this->code, $methProps);
        return $response['Token'];
    }
    private function calculateInvoice()
    {
        $output = [];
        foreach ($this->mains as $main) { $output[] = !empty($main['invoice_num']) ? $main['invoice_num'] : 'N/A'; }
        return implode(',', $output);
    }
    private function calculatePONumber()
    {
        $output = [];
        foreach ($this->mains as $main) { $output[] = !empty($main['purch_order_id']) ? $main['purch_order_id'] : 'N/A'; }
        return implode(',', $output);
    }
    private function calculateShipping()
    {
        $total = 0;
        foreach ($this->mains as $main) { $total += $main['freight']; }
        return number_format($total, 2, '.', '');
    }
    private function calculateTax()
    {
        $total = 0;
        foreach ($this->mains as $main) { $total += $main['sales_tax']; }
        return number_format($total, 2, '.', '');
    }
    /**
     *
     * @param type $data
     * @return type
     */
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
     * @param type $ledger
     * @return type
     */
    private function guessInv($ledger)
    {
        $refs = ['inv'=>$ledger->main['invoice_num'], 'po'=>$ledger->main['invoice_num']];
        if (empty($ledger->items)) { return $refs; }
        foreach ($ledger->items as $row) {
            if ($row['gl_type'] <> 'pmt') { continue; } // just the first row
            $vals = explode(' ', $row['description'], 4);
            if (!empty($vals[1])) { $refs['inv']= $vals[1]; }
            if (!empty($vals[3])) { $refs['po'] = $vals[3]; }
            break;
        }
        return $refs;
    }

    private function setAddress($data)
    {
        "ID	Guid	Unique identifier for this record	36
        Customer	String	ID for this customer. This is generated by the client upon creation of the customer.	nvarchar(128)
        Country	String	Country name	varchar(64)
        State	String	State name	varchar(64)
        City	String	City name	varchar(64)
        Line1	String	Street line 1	nvarchar(128)
        Line2	String	Street line 2	nvarchar(128)
        Line3	String	Street line 3	nvarchar(128)
        Email	String	Email address	nvarchar(128)
        Phone	String	Phone number	varchar(16)
        ModifiedOn	String	Timestamp indicating when this record was last modified. It's format should like \"3/23/2015 11:16:19 PM\".	datetime,not null
        Zip	String	Zip code	varchar(16)";

    }

    private function setCard($data)
    {
        'ID	Guid	Unique identifier for a card or wallet record. This ID (aka Wallet ID) is generated by PayFabric upon successful creation of a new card entry for either credit card or ACH. The client cannot set or modify this value.
        Tender*	Options	Tender type. Valid options are CreditCard, ECheck, ApplePay and GooglePay.	nvarchar(64)
        Customer*	String	Customer ID or Customer Number as specified by the client upon creation of the customer. This is a required field in order to associate a card/wallet entry to a customer account.	nvarchar(128)
        Account*	String	The number of the credit card, or the eCheck/ACH account. When creating a new Card this attribute must be provided by the client in plaintext. When a client retrieves a card PayFabric always returns this attribute in masked format. Ignore this attribute when update a existing card.	nvarchar(64)
        ExpDate*	String	Expiration date of the credit card in the format of MMYY. Only valid for credit cards.	varchar(4)
        CheckNumber	String	Check number. Only valid for eChecks, and required for specific Processors (TeleCheck, Encompass).	varchar(128)
        AccountType	String	eCheck account type. Only valid for eCheck accounts.	varchar(32)
        Aba*	String	Bank Routing Number. Only valid for eChecks.	varchar(64)
        CardName	String	Type of credit card: Visa, Mastercard, Discover,JCB,AmericanExpress,DinersClub. Only valid for credit cards.	nvarchar(16)
        IsDefaultCard	Boolean	Indicates whether this is the primary card of the customer. Default value is False.	bit, not null
        IsLocked	Boolean	Indicates whether the card is locked. Default value is False.	bit, null
        IsSaveCard	Boolean	Indicates whether to save this card in the customer\'s wallet. This attribute is only valid and should only be included in the object when using Create and Process a Transaction. And it will be set to false automatically for Verify transactions or transactions with Tender set to ApplePay or GooglePay.
        ModifiedOn	String	This is a response field. Timestamp indicating when this record was last modified. It\'s format should like "3/23/2015 11:16:19 PM".	datetime, not null
        CardHolder*	Object	Cardholder object.
        Billto	Object	Address object.
        Identifier	String	A client-defined identifier for this card. Developer can send a flag value to identify this card	nvarchar(32)
        UserDefine1	String	User-defined field 1. Developer can store additional data in this field.	nvarchar(256)
        UserDefine2	String	User-defined field 2. Developer can store additional data in this field.	nvarchar(256)
        UserDefine3	String	User-defined field 3. Developer can store additional data in this field.	nvarchar(256)
        UserDefine4	String	User-defined field 4. Developer can store additional data in this field.	nvarchar(256)
        Connector	String	The gateway name defined by PayFabric such as FirstDataGGe4, PayflowPro or Paymentech. This field will be set only if this card is a tokenized value for a specific gateway, such as FirstData or Paypal	nvarchar(64)
        GatewayToken	String	Gateway token. PayFabric send this value to gateway for processing a transaction	varchar(32)
        IssueNumber	String	This field is required for UK debit cards	nvarchar(64)
        StartDate	String	This field is required for UK debit cards, format is MMYY.	varchar(4)
        NewCustomerNumber	String	This field is used to submit new customer number for updating this record\'s customer field.	nvarchar(128)
        CardType	String	This is a response field, the possible value is \'Credit\', \'Debit\' or \'Prepaid\' for credit card, and it is blank for eCheck.	varchar(20)
        EncryptedToken	String	The Apple Pay or Google Pay payment token, as provided by the provider. Conditionally required if Tender is ApplePay or GooglePay.	nvarchar(MAX)';
    }

    private function setCardHolder($data)
    {
        "DriverLicense	String	Driver license	varchar(32)
        FirstName*	String	First name	nvarchar(64)
        LastName*	String	Last name	nvarchar(64)
        MiddleName	String	Middle name	nvarchar(64)
        SSN	String	Social security number	varchar(16)";
    }

}
