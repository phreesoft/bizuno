<?php
/*
 * Payment module - Main methods
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
 * @version    7.x Last Update: 2026-04-27
 * @filesource /controllers/payment/main.php
 */

namespace bizuno;

class paymentMain
{
    public $moduleID = 'payment';

    public function __construct()
    {
    }

    /**
     * Generates the structure for viewing enabled payment methods
     * @param array $layout - Structure coming in
     * @output modified $layout
     */
    public function render(&$layout=[])
    {
        $jID   = clean('jID',  'integer','get');
        $type  = clean('type', 'char',   'get');
        if (!$type) { $type = in_array($jID, [17, 20, 21]) ? 'v' : 'c'; }
        $meta = getMetaMethod('gateways');
        foreach ($meta as $row) {
            if (empty($row['status'])) { continue; }
            $values[] = ['id'=>$row['id'], 'text'=>$row['title'], 'order'=>!empty($row['settings']['order']) ? $row['settings']['order'] : 99];
        }
        $values = sortOrder($values);
        msgDebug("\nread meta payments = ".print_r($values, true));
        if (empty($layout['fields']['method_code']['attr']['value'])) {
            $layout['fields']['method_code']['attr']['value'] = $values[0]['id'];
        }
        $layout['fields']['selMethod'] = ['values'=>$values,'events'=>['onChange'=>'selPayment(newVal);'],
            'attr'=>['type'=>'select','value'=>$layout['fields']['method_code']['attr']['value']]];
    }

    /**
     * Authorize a credit card. Prefers the generic `$gateway->payment('authorize', …)`
     * dispatcher; falls back to the legacy `paymentAuth($fields, $ledger)` shim on
     * gateways that haven't yet been ported (e.g. a client myExt that lags the core
     * deploy). Without the fallback this would fatal on any gateway missing `payment()`.
     * @return string|false - txID on success, false on failure
     */
    public function authorize($ledger=[])
    {
        if (!$security= validateAccess("j12_mgr", 2)) { return; }
        $method = clean('method_code','text', 'post');
        if (!$gateway = $this->getGateway($method)) { return; }
        if (!$fields  = $this->process($method, $ledger)) { return; }
        if (method_exists($gateway, 'payment')) {
            $r = $gateway->payment('authorize', ['fields'=>$fields, 'ledger'=>$ledger]);
            if (empty($r['ok'])) { return; }
            return !empty($r['txID']) ? $r['txID'] : '1';
        }
        if (method_exists($gateway, 'paymentAuth')) {
            if (!$response = $gateway->paymentAuth($fields, $ledger)) { return; }
            return !empty($response['txID']) ? $response['txID'] : '1';
        }
        return '1'; // no auth path on this gateway — historic behavior
    }

    /**
     * Process a sale. Prefers the generic `$gateway->payment($action, …)` dispatcher;
     * falls back to the legacy `sale($fields, $ledger)` shim on gateways that haven't
     * yet been ported (e.g. a client myExt that lags the core deploy). The dispatcher
     * path examines the posted `<method>_action` radio:
     *   c       = capture-prior-auth     → payment('capAuth', …)
     *   w       = manual/website         → no gateway call, just record locally
     *   s       = stored-card sale       → payment('wltCap', …)   (gateway resolves
     *                                       custID/payID from ledger + POSTed selCards
     *                                       inside its own pmtWalletCapture())
     *   n, ''   = new-card sale          → payment('capture', …)
     * (The c/s/n/w convention is shared by every credit-card gateway's render(); record-only
     * methods like cod/moneyorder/directdebit don't render the radio and fall through to capture.
     *  Gateways without stored-card support — e.g. converge — return notImplemented on wltCap,
     *  which surfaces as a clear error rather than the historical "card number missing" cryptic
     *  failure from sending `capture` with no POSTed PAN.)
     * @return array|false - normalized response on success, false on failure
     */
    public function sale($method='', $ledger=[])
    {
        msgDebug("\nEntering payment:sale with method = ".print_r($method, true)." and ledger = ".print_r($ledger, true));
        $meta = getMetaMethod('gateways', $method);
        if (empty($meta['path'])) {
            return msgAdd("Cannot apply payment to method: $method since the method is not installed!");
        }
        $iID = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['id', 'description'], "ref_id={$ledger->main['id']} AND gl_type='ttl'");
        $fields = ['ref_1'=>clean($method.'_ref_1', 'text', 'post')];
        if (!$gateway= $this->getGateway($method)) { return; }
        $radio = clean("{$method}_action", 'db_field', 'post');
        if ($radio === 'w') {
            // manual/web-side capture — nothing to send to the gateway, just record
            $r = ['ok'=>true, 'txID'=>'', 'code'=>'', 'data'=>[]];
        } elseif (method_exists($gateway, 'payment')) {
            // Radio → gateway action:
            //   's' → wltCap (stored card)   — previously misrouted to 'capture' which
            //                                  called buildCreditCardFromPost() with no PAN
            //                                  available and broke with an opaque gateway
            //                                  "card number invalid" error
            //   'c' → capAuth (prior auth)
            //   anything else → capture (new card / record-only)
            $action = 'capture';
            if     ($radio === 'c') { $action = 'capAuth'; }
            elseif ($radio === 's') { $action = 'wltCap'; }
            $payData = ['fields'=>$fields, 'ledger'=>$ledger];
            if ($radio === 'c') {
                $payData['txID'] = clean("{$method}trans_code", 'integer', 'post');
            }
            msgDebug("\nProcessing sale with method = $method action = $action");
            $r = $gateway->payment($action, $payData);
            if (empty($r['ok'])) { return; }
        } elseif (method_exists($gateway, 'sale')) {
            // Legacy fallback for un-ported gateways (typically client myExt). Translate the
            // historic ['txID'=>X,'txTime'=>Y,'code'=>Z] return shape into the dispatcher's
            // normalized form so the rest of this method can stay on one code path.
            msgDebug("\nProcessing sale via legacy gateway->sale() — gateway has not been ported to payment() dispatcher");
            if (!$result = $gateway->sale($fields, $ledger)) { return; }
            $r = ['ok'=>true, 'txID'=>$result['txID'] ?? '', 'code'=>$result['code'] ?? '', 'data'=>['txTime'=>$result['txTime'] ?? '']];
        } else {
            // Gateway has neither the dispatcher nor the legacy shim — record-only with nothing to call.
            $r = ['ok'=>true, 'txID'=>'', 'code'=>'', 'data'=>[]];
        }
        // add to the description
        $desc = bizDecode($iID['description']);
        $desc['method']= $method;
        $desc['status']= 'cap';
        if (!empty($r['code'])) { $desc['code'] = $r['code']; } // gateway approval code, read by report field bnkCode
        // Store the card hint — refund() requires its last 4 to build an SDK refundTransaction
        // (without it every in-app charge gets the "refund at the gateway portal" skip), and the
        // edit view / bnkCard / bnkHint report fields display it. Format follows the e-store
        // import map (bizuno/maps/journal.php): leading digit identifies the brand
        // (3=Amex 4=Visa 5=MC 6=Disc), last 4 for display. Prefer the POSTed PAN (new-card
        // sale); fall back to the gateway response's masked account number (stored-card sale).
        if (empty($desc['hint'])) {
            $ccNum = (string)clean("{$method}_number", 'numeric', 'post');
            if (strlen($ccNum) >= 13) {
                $desc['hint'] = substr($ccNum, 0, 4).'********'.substr($ccNum, -4);
            } elseif (!empty($r['data']['accountNumber'])) {
                $brands = ['AmericanExpress'=>'3', 'Visa'=>'4', 'MasterCard'=>'5', 'Discover'=>'6'];
                $brand  = !empty($r['data']['accountType']) && isset($brands[$r['data']['accountType']]) ? $brands[$r['data']['accountType']] : 'X';
                $desc['hint'] = $brand.'***********'.substr((string)$r['data']['accountNumber'], -4);
            }
        }
        $fields = ['description'=>bizEncode($desc), 'trans_code'=>!empty($r['txID']) ? $r['txID'] : ''];
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', $fields, 'update', "id={$iID['id']}");
        return $r;
    }

    /**
     * Void an unsettled transaction. Prefers the generic `$gateway->payment('void', …)`
     * dispatcher (which does the journal_item trans_code lookup and handles graceful
     * skip when refunds are disabled); falls back to legacy `void($rID)` for gateways
     * that haven't been ported.
     * @return bool true on success or non-fatal skip; false only when the gateway call itself failed
     */
    public function void($method='', $rID=0)
    {
        msgDebug("\nEntering payment:void with method=$method rID=$rID");
        if (empty($method) || empty($rID)) { return true; } // nothing to do
        if (!$gateway = $this->getGateway($method)) { return true; } // getGateway already messaged
        if (method_exists($gateway, 'payment')) {
            $r = $gateway->payment('void', ['rID'=>$rID]);
            return !empty($r['ok']);
        }
        if (method_exists($gateway, 'void')) {
            return !empty($gateway->void($rID));
        }
        return true; // no void path — non-fatal, let the journal delete proceed
    }

    /**
     * Process a customer refund. Prefers the generic `$gateway->payment('refund', …)`
     * dispatcher; falls back to legacy `refund($transCode, $amount)` for gateways
     * that haven't been ported. Pulls `last4` from the original payment's description
     * hint so SDK-based gateways (auth.net) that require a card number on
     * refundTransaction can complete.
     * @return boolean - always returns true so the credit memo proceeds even when the
     *                   gateway-side refund fails or is skipped (matches legacy behavior)
     */
    public function refund(&$j22ttlRow, $j22pmtRow, $amount=0)
    {
        msgDebug("\nEntering payment:refund with amount = $amount");
        $j13ttlRow= dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$j22pmtRow['item_ref_id']} AND gl_type='ttl'");
        $transCode= $this->refundTrnsCode($j22ttlRow, $j13ttlRow);
        if (empty($transCode)) { return true; } // had no transaction code so probably wasn't a credit card
        $method   = guessPaymentMethod(0, $j13ttlRow['description']);
        if (!$gateway = $this->getGateway($method)) { return; }
        if (method_exists($gateway, 'payment')) {
            // last4 lives in the original payment's description hint — required by SDK gateways (auth.net)
            $origDesc = bizDecode($j13ttlRow['description']);
            $last4    = !empty($origDesc['hint']) ? substr((string)$origDesc['hint'], -4) : '';
            msgDebug("\nProcessing refund with method = $method, amount = $amount, last4 = $last4");
            $r = $gateway->payment('refund', [
                'txID'   => $transCode,
                'amount' => $amount,
                'last4'  => $last4,
            ]);
            if (empty($r['ok'])) { return; }
        } elseif (method_exists($gateway, 'refund')) {
            msgDebug("\nProcessing refund via legacy gateway->refund() — gateway has not been ported to payment() dispatcher");
            if (!$result = $gateway->refund($transCode, $amount)) { return; }
            $r = ['ok'=>true, 'txID'=>$result['txID'] ?? '', 'code'=>$result['code'] ?? ''];
        } else {
            // Gateway has no refund path — record locally and proceed.
            $r = ['ok'=>true, 'txID'=>'', 'code'=>''];
        }
        $parts = ['method'=>$method, 'status'=>'rfnd', 'code'=>$r['code']];
        $desc  = array_replace(bizDecode($j22ttlRow['description']), $parts);
        $fields= ['description'=>bizEncode($desc), 'trans_code'=>$r['txID']];
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', $fields, 'update', "id={$j22ttlRow['id']}");
        return true;
    }

    public function userSignup(&$layout=[])
    {
        $method = clean('method_code', 'text', 'get');
        if (!$gateway = $this->getGateway($method)) { return; }
        if (method_exists($gateway, 'userSignup')) { // fetches the re-direct link to sign up for a service
            if (!$result = $gateway->userSignup($layout)) { return msgAdd("Houston, we have a problem."); }
            return;
        }
        msgAdd("No userSignup actions. Nothing to do here, bailing...");
    }

    private function refundTrnsCode($j22ttlRow, $j13ttlRow)
    {
        msgDebug("\nEntering refundTrnsCode with j22ttlRow = ".print_r($j22ttlRow, true));
        msgDebug("\nEntering refundTrnsCode with j13ttlRow = ".print_r($j13ttlRow, true));
        if (empty($j13ttlRow['trans_code'])) { return ''; }
        // Test for capture in the original invoice
        $desc0 = bizDecode($j22ttlRow['description']);
        if (!empty($desc0['status']) && $desc0['status']=='cap') { return $j22ttlRow['trans_code']; }
        // Test for capture at time of invoice (e-store)
        $desc1 = bizDecode($j13ttlRow['description']);
        if (!empty($desc1['status']) && $desc1['status']=='cap') { return $j13ttlRow['trans_code']; }
        // Maybe a CM, trace it back to cash receipt via the invoice
        $pairs = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "trans_code='{$j13ttlRow['trans_code']}'", 'id', ['ref_id']);
        msgDebug("\nread after search by trans_code: ".print_r($pairs, true));
        if (empty($pairs)) { return; }
        $j12mID= array_shift($pairs);
        $j18mID= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'ref_id', "item_ref_id={$j12mID['ref_id']}");
        if (empty($j18mID) && sizeof($pairs)) { // may have been a SO, try again
            msgDebug(" ... EMPTY ... Trying next mID");
            $j12mID= array_shift($pairs); 
            $j18mID= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'ref_id', "item_ref_id={$j12mID['ref_id']}");
        }
        msgDebug("\nRead j18 mID value: ".print_r($j18mID, true));
        if (empty($j18mID)) { 
            msgAdd('Transaction code cannot be found, the refund must be handled at the Gateway interface!', 'caution');
            return '';
        }
        // get the trans_code from the cash receipt
        $j18row= dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$j18mID AND gl_type='ttl'");
        msgDebug("\nRead j18row = ".print_r($j18row, true));
        $desc2 = bizDecode($j18row['description']);
        if (!empty($desc2['status']) && $desc2['status']=='cap') { return $j18row['trans_code']; }
    }

    private function getGateway($method='')
    {
        $gateway = getMetaMethod('gateways', $method);
        if (empty($gateway['path'])) {
            return msgAdd("Cannot apply payment to gateway: $method since the method is not installed!");
        }
        bizAutoLoad($gateway['path']."$method.php");
        $fqcn   = "\\bizuno\\$method";
        $myMeth = new $fqcn();
        localizeLang($myMeth->lang, $myMeth->methodDir, $myMeth->code);
        return $myMeth;
    }

    /**
     * Cleans and modifies CVV to meet credit card processor standards and expectations
     * @param integer $cvv - user supplied cvv code
     * @param string $ccNum - credit card number to determine how long to make returning cvv
     * @return string - cleaned cvv ready to submit to method processor
     */
    private function fixCvv($cvv, $ccNum)
    {
        return substr("0000".$cvv, substr($ccNum,0,2)=='37' ? -4 : -3);
    }
}
