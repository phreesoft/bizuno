<?php
/*
 * Shipping extension for percent rated shipments - XPO Logistics
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/shipping/carriers/xpo/rate.php
 *
 * Docs website: http://www.xpo.com/content/xml
 */

namespace bizuno;

class xpoRate extends xpoCommon
{
    private $rateURL ='https://api.ltl.xpo.com/rating/1.0/ratequotes';

    function __construct()
    {
        parent::__construct();
        msgDebug("\nxpoRate with options = " .print_r($this->options, true));
        msgDebug("\nxpoRate with settings = ".print_r($this->settings, true));
    }

    public function rateQuote($pkg)
    {
        $arrRates = [];
        if ($pkg['settings']['weight'] == 0)  { msgAdd(lang('ERROR_WEIGHT_ZERO')); return $arrRates; }
//      if ($pkg['settings']['weight'] < 100) { return $arrRates; }
        if (empty($pkg['destination']['postal_code'])) { msgAdd(lang('ERROR_POSTAL_CODE'));    return $arrRates; }
        $request = json_encode($this->formatXPORateRequest($pkg));
        if (!$response = $this->getXPO($this->rateURL, $request)) { return []; }
        if (!empty($response['data']['msgs'])) { foreach ($response['data']['msgs'] as $msg) {
            msgAdd("XPO Message: ".$msg['message'], 'caution');
        } }
        $note   = 'Transit: '.$response['data']['transitTime']['transitDays'].' days, Delivered: '.biz_date('D M j', $response['data']['transitTime']['estdDlvrDate']/1000);
        $note  .= !empty($response['data']['rateQuote']['totGarntAmt']['amt']) ? " (+ {$response['data']['rateQuote']['totGarntAmt']['amt']} Guaranteed)" : '';
        $arrRates['ECF'] = ['title'=>$this->lang['ECF'], 'gl_acct'=>$this->settings['gl_acct'],
            'book' => viewFormat($response['data']['rateQuote']['shipmentInfo']['commodity'][0]['charge']['chargeAmt']['amt'], 'currency'),
            'cost' => viewFormat($response['data']['rateQuote']['totCharge'][0]['amt'], 'currency'),
            'quote'=> viewFormat($response['data']['rateQuote']['totCharge'][0]['amt'], 'currency'),
            'note' => $note];
        return $arrRates;
    }

    function formatXPORateRequest($pkg)
    {
        $bTrim = is_numeric($pkg['bill']['postal_code']) ? 5 : 6;
        $sTrim = is_numeric($pkg['ship']['postal_code']) ? 5 : 6;
        $output= [
            'shipmentInfo' => [
                'paymentTermCd'=> 'P', // P (Prepaid) or C (Collect)
                'accessorials' => [],
                'palletCnt'    => 1,
                'commodity'    => [[
                    'grossWeight'=> ['weight'=>ceil($pkg['settings']['weight']), 'weightUom'=>'LBS'],
                    'pieceCnt'   => 1,
                    'packageCode'=> 'PLT', // SKD, PLT, PCS, CRT, BDL, BOX, CAS
                    'nmfcClass'  => intval($this->settings['ltl_class']),
                    'hazmatInd'  => false,
                    'dimensions' => ['length'=>30, 'width'=>30, 'height'=>16, 'dimensionsUom'=>'INCH']]],
                'bill2Party'   => ['address'=>['postalCd'=>substr($pkg['payor']['postal_code'], 0, $bTrim)]], // XPO fails on +4
                'consignee'    => ['address'=>['postalCd'=>substr($pkg['destination']['postal_code'], 0, $sTrim)]],
                'shipmentDate' => biz_date('c'),
                'shipper'      => ['acctInstId'=>trim($this->settings['acct_id']." "), // make acct # string
//              'address'      => ['postalCd' => substr($pkg['bill']['postal_code'], 0, 5)]
            ]]];
        msgDebug("\nReturning from formatXPORateRequest with; ".print_r($output, true));
        return $output;
    }
}
