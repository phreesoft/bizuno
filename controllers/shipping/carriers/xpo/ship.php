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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-05-06
 * @filesource /controllers/shipping/carriers/xpo/ship.php
 *
 * Docs website: http://www.xpo.com/content/xml
 */

namespace bizuno;

class xpoShip extends xpoCommon
{
    private $shipURL = 'https://api.ltl.xpo.com/billoflading/1.0/billsoflading';

    function __construct()
    {
        parent::__construct();
        msgDebug("\nxpoShip with options = " .print_r($this->options, true));
        msgDebug("\nxpoShip with settings = ".print_r($this->settings, true));
    }

// ***************************************************************************************************************
//                                XPO LABEL REQUEST (multipiece compatible)
// ***************************************************************************************************************
    /**
     * Retrieves a label from XPO
     * @param array $request
     * @return boolean|array a array of the successful getting the labels or false on fail w/message send through messageStack
     */
    public function labelGet($request=false)
    {
        global $io;
        if (empty($this->settings['acct_id'])) { return msgAdd($this->lang['err_no_creds']); }
        if (strtotime($request['ship_date']) < biz_date() ) { $request['ship_date'] = biz_date(); } // today or later
        // createBillOfLading
        if (!$resp= $this->getXPO($this->shipURL, json_encode($this->formatXPOShipRequest($request)))) { return []; }
        if (empty($resp['code']) || $resp['code'] > 300) { return []; }
        $bolID    = $resp['data']['bolInfo']['bolInstId'];
        $tracking = $resp['data']['bolInfo']['proNbr'];
        // and then getBillofLading details (for shipping record)
        if (!$bolInfo = $this->getXPO($this->shipURL."/$bolID/requester")) { return; }
        // and then getBillOfLadingPdf
        if (!$getBolPDF= $this->getXPO($this->shipURL."/$bolID/pdf")) { return; }
        $date     = explode('-', $request['ship_date']);
        $file_path= "data/shipping/labels/$this->code/{$date[0]}/{$date[1]}/{$date[2]}/";
        $bolPDF   = $getBolPDF['data']['bolpdf']['contentType'];
        $email    = getUserCache('profile', 'email');
        if (strlen($bolPDF) > 0) {
            $io->fileWrite(base64_decode($bolPDF), $file_path."$tracking-bol.pdf", true);
            msgDebug("\nSuccessfully retrieved the XPO shipping label. Tracking # $tracking");
        } else { // try to email it
            msgAdd('Error - No BOL label found in return string.');
            if (!$emailBOL= $this->getXPO($this->shipURL."/$bolID/email", json_encode(['emailId'=>$email, 'bolInstId'=>$bolID]))) { return; }
        }
        //and then getShippingLabelPdf
        $lblAttr  = ['bolInstId'=>$bolID, 'nbrOfLabels'=>1, 'labelPosition'=>1,'printerSettings'=>'Zebra'];
        if (!$getShpLbl = $this->getXPO($this->shipURL."/$bolID/shippinglabels/pdf", json_encode($lblAttr))) { return; }
        $lblPDF= $getShpLbl['data']['shippingLabel']['contentType'];
        if (strlen($lblPDF) > 0) {
            $io->fileWrite(base64_decode($lblPDF), $file_path."$tracking-lbl.pdf", true);
            msgDebug("\nSuccessfully retrieved the XPO shipping label. Tracking # $tracking");
        } else { // try to email it
            msgAdd('Error - No shipping label found in return string.');
            $attr = ['bolInstId'=>$bolID, 'nbrOfLabels'=>1, 'labelPosition'=>1, 'emailId'=>$email, 'printerSettings'=>'Zebra'];
            if (!$emailLbl = $this->getXPO($this->shipURL."/$bolID/shippinglabels/email", json_encode($attr))) { return; }
        }
        return [[ // return with the shipping log entry
            'ref_id'       => !empty($request['ship_ref_1']) ? $request['ship_ref_1'] : time(),
            'method'       => $request['ship_method'],
            'ship_date'    => strtotime($request['ship_date']),
            'tracking'     => $tracking,
            'book_cost'    => !empty($bolInfo['data']['billOfLading']['getFromRate']) ? $bolInfo['data']['billOfLading']['getFromRate'] : 0,
            'net_cost'     => !empty($bolInfo['data']['billOfLading']['getFromRate']) ? $bolInfo['data']['billOfLading']['getFromRate'] : 0,
            'delivery_date'=> !empty($bolInfo['data']['billOfLading']['getFromRate']) ? $bolInfo['data']['billOfLading']['getFromRate'] : 0,
            'notes'        => "bolInstId:$bolID",
        ]];
    }

    /**
     * Formats a label request with XPO, handles all methods
     * @param array $pkg
     * @return array
     */
    private function formatXPOShipRequest($pkg=[]) {
        $dateShip  = $pkg['ship_date'].'T08:00:00'; 
        $datePickup= $pkg['ship_date'].'T15:00:00'; 
        $dateClose = $pkg['ship_date'].'T17:00:00'; 
        $request   = ['bol' => [
            'requester' => ['role' => 'S'],
            'consignee' => $this->mapAddress($pkg['destination']),
            'shipper'   => $this->mapAddress($pkg['shipper']),
//          'billToCust'=> $this->mapAddress($pkg['payor']),
//          'remarks'              => 'Emergency Remarks',
//          'emergencyContactName' => 'Emergency Contact Name',
//          'emergencyContactPhone'=> ['phoneNbr'=>'503-5551212' ],
            'chargeToCd'=> 'P',
//          'additionalService'    => [['accsrlCode'=>"OIP", 'prepaidOrCollect'=>'P']],
            'suppRef'   => ['otherRefs'=> [
                    ['referenceCode'=>'OOO', 'referenceDescr'=>lang('invoice_number'),'reference'=>!empty($pkg['ship_ref_1']) ? $pkg['ship_ref_1'] : 'NA','referenceTypeCd'=>'Other'], // 'referenceTypeCd'=> "Other",
                    ['referenceCode'=>'OOO', 'referenceDescr'=>lang('po_num'),        'reference'=>!empty($pkg['ship_ref_2']) ? $pkg['ship_ref_2'] : 'NA','referenceTypeCd'=>'Other'], // 'referenceTypeCd'=> "Other",
                ]],
            'pickupInfo'=> ['pkupDate'=>$dateShip, 'pkupTime'=>$datePickup, 'dockCloseTime'=>$dateClose,
                'contact' => [
                    'companyName'=> $pkg['origin']['primary_name'],
                    'fullName'   => !empty($pkg['origin']['contact']) ? $pkg['origin']['contact'] : $pkg['origin']['primary_name'],
                    'phone'      => ['phoneNbr'=> $this->setPhone($pkg['origin']['telephone1'])]]],
//          'excessLiabilityChargeInit'=> 'ABC',
            ],
            'autoAssignPro' => true];
        $decVal = 0;
        foreach ($pkg['pkgs'] as $lineItem) {
            $request['bol']['commodityLine'][] = [
                'pieceCnt'   => intval($lineItem['qty']),
                'packaging'  => ['packageCd'=>'PLT'],
                'grossWeight'=> ['weight'=>ceil($lineItem['weight'])],
                'desc'       => $pkg['ltl_desc'],
//              'nmfcItemCd' => '9999',
//              'hazmatInd'  => true,
                'nmfcClass'  => intval($pkg['ltl_class'])];
            $decVal += $lineItem['value'];
        }
        $request['bol']['declaredValueAmt']     = ['amt'=>floatVal($decVal), 'currencyCd'=>'USD'];
//      $request['bol']['declaredValueAmtPerLb']= $decVal / ($pkg['total_weight'] ? $pkg['total_weight'] : 1);
        msgDebug("\nReturning from formatXPOShipRequest with request = ".print_r($request, true));
        return $request;
    }

    // ***************************************************************************************************************
    //                                XPO DELETE REQUEST
    // ***************************************************************************************************************
    public function labelDelete($bol='')
    {
        // @TODO - this needs to be fed the journal_main rID and go from there.
        return msgAdd("This method needs to be updated to the new architecture.");
        if (empty($this->settings['acct_id'])) { return msgAdd($this->lang['err_no_creds']); }
        if (!$bol) { return msgAdd("Cannot delete shipment, tracking number was not provided! $bol"); }
        $notes = getMetaJournal($refID, 'shipment');
        $tmp = explode(':', $notes);
        if (empty($tmp[1])) { return; }
        msgDebug("\nWorking with bolInstId = {$tmp[1]}");
        if (!$delBOL = $this->getXPO($this->shipURL."/{$tmp[1]}/cancel", json_encode(['bolInstId'=>$tmp[1]]), 'put')) { return; }
        return true;
    }
}
