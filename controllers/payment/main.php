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
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-09-09
 * @filesource /controllers/payment/main.php
 */

namespace bizuno;

class paymentMain
{
    public $moduleID = 'payment';
    public $lang;

    public function __construct()
    {
        $this->lang = getLang($this->moduleID);
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
            $values[] = ['id'=>$row['id'], 'text'=>$row['title'], 'order'=>!empty($row['order']) ? $row['order'] : 0];
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
     * This method accepts post variables from ALL methods, determines the method and submits all credit cards for authorization
     * @return array - user message if failed, success contains the authorization_code for credit cards, ref field if supplied.
     */
    public function authorize($ledger=[])
    {
        if (!$security= validateAccess("j12_mgr", 2)) { return; }
        $method = clean('method_code','text', 'post');
        if (!$gateway = $this->getGateway($method)) { return; }
        if (!$fields  = $this->process($method, $ledger)) { return; }
        $txID = '1';
        if (method_exists($gateway, 'paymentAuth')) {
            if (!$response = $gateway->paymentAuth($fields, $ledger)) { return; }
            $txID = $response['txID'];
        }
        return $txID;
    }

    /**
     * This method is the parent to process a sale, both authorize and capture are supported
     * @param array $method - typically $_POST variables to gather the payment details
     * @param array $ledger - contains the current PhreeBooks ledger object with journal details
     * @return false on failure and transaction information array on success
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
        if (method_exists($gateway, 'sale')) {
            msgDebug("\nProcessing sale with method = $method");
            if (!$result = $gateway->sale($fields, $ledger)) { return; }
        } else {
            $result['txID'] = '';
        }
        // add to the description
        $desc = bizDecode($iID['description']);
        $desc['method']= $method;
        $desc['status']= 'cap';
        $fields = ['description'=>bizEncode($desc), 'trans_code'=>!empty($result['txID']) ? $result['txID'] : ''];
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', $fields, 'update', "id={$iID['id']}");
        return $result;
    }

    /**
     * Nothing to do here as this is for same day deletes and are handled at the post delete
     * @TODO - probably should move that here for j17-j22 to handle deletes same day and after
     */
    public function void() { }

    /**
     * Processes a customer refund from a given invoice if it is a credit card
     * @param array $j22ttlRow - journal item row for the current post ttl line
     * @param array $j22pmtRow - journal item row for the current post pmt line
     * @param float $amount - amount to refund, cannot exceed the amount charged.
     * @return boolean|string
     */
    public function refund(&$j22ttlRow, $j22pmtRow, $amount=0)
    {
        msgDebug("\nEntering payment:refund with amount = $amount");
        $j13ttlRow= dbGetRow(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$j22pmtRow['item_ref_id']} AND gl_type='ttl'");
        $transCode= $this->refundTrnsCode($j22ttlRow, $j13ttlRow);
        if (empty($transCode)) { return true; } // had no transaction code so probably wasn't a credit card
        $method   = guessPaymentMethod(0, $j13ttlRow['description']);
        if (!$gateway = $this->getGateway($method)) { return; }
        if (method_exists($gateway, 'refund')) {
            msgDebug("\nProcessing refund with method = $method, amount = $amount");
            if (!$result = $gateway->refund($transCode, $amount)) { return; }
        } else { $result = ['txID'=>'', 'code'=>'']; }
        $parts = ['method'=>$method, 'status'=>'rfnd', 'code'=>$result['code']];
        $desc  = array_replace(bizDecode($j22ttlRow['description']), $parts);
        $fields= ['description'=>bizEncode($desc), 'trans_code'=>$result['txID']];
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
        msgDebug("\nRead j18 mID row: ".print_r($j18mID, true));
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
        return new $fqcn();
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
