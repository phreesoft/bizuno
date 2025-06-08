<?php
/*
 * Shipping extension for United Parcel Service - Ship
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
 * @filesource /controllers/shipping/carriers/ups/ship.php
 *
 */

namespace bizuno;

class upsShip extends upsCommon
{
    function __construct()
    {
        parent::__construct();
        msgDebug("\nUPSShip with options = " .print_r($this->options, true));
        msgDebug("\nUPSShip with settings = ".print_r($this->settings, true));
    }

// ***************************************************************************************************************
//                                UPS LABEL REQUEST (multipiece compatible)
// ***************************************************************************************************************
    public function labelGet($request=false)
    {
        global $io;
        $is_freight = (in_array($request['ship_method'], ['308'])) ? true : false;
        $this->prepShipment($request, $is_freight);
        $ups_results = [];
        if (empty($this->settings['rest_api_key'])) { return msgAdd($this->lang['err_no_creds']); }
        if (in_array($request['ship_method'], ['07','08','11','96'])) { // unsupported ship carriers
            return msgAdd('The ship method requested is not supported by this tool presently. Please ship the package via the ups website (www.UPS.com).');
        }
        if ((empty($request['destination']['contact']) && $request['ship_method']=='1DM') || $request['origin']['country'] <> $request['destination']['country']) {
            return msgAdd('A Ship To contact is required for Next Day Air® Early service, or when ShipTo country <> ShipFrom country.');
        }
        if ($is_freight) { $restReq = $this->payloadRESTLTL($request); }
        else             { $restReq = $this->payloadREST   ($request); }
        $restURL = "api/shipments/$this->restVersion/ship";
        msgDebug("\nReady to send address request to url $restURL with data: ".print_r($restReq, true));
        $getUPS = $this->queryREST($restURL, json_encode($restReq), 'post');
        if (empty($getUPS)) { return []; }
        $response = $getUPS->ShipmentResponse;
        if ($response->Response->ResponseStatus->Code == '1') {
            $net_cost = $book_cost = 0;
            $del_date = '';
            if (isset($response->ShipmentResults->NegotiatedRateCharges->TotalCharge)) {
                $net_cost = $response->ShipmentResults->NegotiatedRateCharges->TotalCharge->MonetaryValue;
            } elseif (isset($response->ShipmentResults->TotalShipmentCharge)) { // freight returns this
                $net_cost = $response->ShipmentResults->TotalShipmentCharge->MonetaryValue;
            } else {
                $net_cost = $response->ShipmentResults->ShipmentCharges->TotalCharges->MonetaryValue;
            }
            if (isset($response->ShipmentResults->ShipmentCharges->TotalCharges)) {
                $book_cost = $response->ShipmentResults->ShipmentCharges->TotalCharges->MonetaryValue;
            }
            $boxes = $response->ShipmentResults->PackageResults;
            if (!is_array($boxes)) { $boxes = [$boxes]; } // for single box shipments
            msgDebug("\nWorking with boxes = ".print_r($boxes, true));
            if (sizeof($boxes)) { $net_cost =  $net_cost / sizeof($boxes); } // UPS only gives total cost, divide out so it adds back properly
            foreach ($boxes as $cnt => $box) {
                $ups_results[$cnt] = [
                    'ref_id'       => $request['ship_ref_1'],
                    'method'       => $request['ship_method'],
                    'ship_date'    => $request['ship_date'],
                    'tracking'     => $box->TrackingNumber,
                    'zone'         => '',
                    'book_cost'    => $book_cost,
                    'net_cost'     => $net_cost,
                    'delivery_date'=> $del_date,
                    'labeltypes'   => $box->ShippingLabel->ImageFormat->Code,
                    'label'        => base64_decode($box->ShippingLabel->GraphicImage)];
            }
            if (empty($ups_results[0]['tracking'])) { return msgAdd('Error - No tracking found in return string.'); }
            $date      = explode('-', biz_date('Y-m-d', $request['ship_date']));
            $file_path = "data/shipping/labels/$this->code/{$date[0]}/{$date[1]}/{$date[2]}/";
            foreach ($ups_results as $cnt => $box) { // keep the thermal label encoded for now
                if     ($box['labeltypes'] == 'EPL') { $file_name = $box['tracking'].($cnt > 0 ? '-'.$cnt : '').'.lpt'; } // EPL format - thermal printer
                elseif ($box['labeltypes'] == 'ZPL') { $file_name = $box['tracking'].($cnt > 0 ? '-'.$cnt : '').'.lpt'; } // ZPL format - thermal printer
                elseif ($box['labeltypes'] == 'GIF') { $file_name = $box['tracking'].($cnt > 0 ? '-'.$cnt : '').'.gif'; } // plain paper, gif format
                elseif ($box['labeltypes'] == 'PDF') { $file_name = $box['tracking'].($cnt > 0 ? '-'.$cnt : '').'.pdf'; } // plain paper
                else { msgAdd("unkown label type: ".$box['labeltypes']); }
                if (!$io->fileWrite($box['label'], $file_path.$file_name, $verbose = true)) { return; }
                msgDebug("Successfully retrieved the UPS shipping label. Tracking # {$box['tracking']}");
            }
        } else {
            $message = '';
            foreach ($response->Notifications as $notification) {
                if (is_object($notification)) { $message .= print_r($notification, true); }
                else                          { $message .= " $notification"; }
            }
            if (isset($response->Notifications->Code)) { $message .= "\n".$this->get_error_message($response->Notifications->Code); }
            return msgAdd($this->lang['RATE_ERROR'] . $message);
        }
        return $ups_results;
    }

    /**
     * Formats a request to ship a package (or packages)
     * @param array $pkg
     * @return string
     */
    private function payloadREST($pkg)
    {
        msgDebug("\nEntering payloadREST with pkg = ".print_r($pkg, true));
        $methods = array_flip($this->options['rateCodes']);
        $request = [];
        $request['ShipmentRequest']['Request'] = [
            'RequestOption'       => 'nonvalidate',
            'SubVersion'          => '1801',
            'TransactionReference'=> ['CustomerContext'=>'', 'TransactionIdentifier'=>'?']];
        $request['ShipmentRequest']['Shipment'] = [
            'TaxInformationIndicator'          => "",
            'ShipmentRatingOptions'            => ['NegotiatedRatesIndicator'=>'X'],
            'ItemizedChargesRequestedIndicator'=> 'X',
            'Description'                      => '*** UPS Shipping Request ***'];
        $request['ShipmentRequest']['Shipment']['Shipper'] = $this->mapAddress($pkg['shipper']);
        $request['ShipmentRequest']['Shipment']['Shipper']['ShipperNumber'] = $this->settings['acct_number'];
        $request['ShipmentRequest']['Shipment']['ShipFrom']= $this->mapAddress($pkg['origin']);
        $request['ShipmentRequest']['Shipment']['ShipTo']  = $this->mapAddress($pkg['destination']);
        $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['Type'] = '01';
        switch ($pkg['ship_bill_to']) {
            case 'SENDER':
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillShipper']['AccountNumber'] = $this->settings['acct_number'];
                break;
            case 'RECIPIENT':
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillReceiver']['AccountNumber']         = $pkg['ship_bill_act'];
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillReceiver']['Address']['PostalCode'] = $pkg['destination']['postal_code'];
                break;
            case 'THIRD_PARTY':
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillThirdParty']['AccountNumber']         = $pkg['ship_bill_act'];
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillThirdParty']['Address']['PostalCode'] = $pkg['destination']['postal_code'];
                $request['ShipmentRequest']['Shipment']['PaymentInformation']['ShipmentCharge']['BillThirdParty']['Address']['CountryCode']= $pkg['destination']['country'];
                break;
        }
        $request['ShipmentRequest']['Shipment']['Service'] = ['Code'=>(string)$methods[$pkg['ship_method']]]; // , 'Description'=>'UPS Ground'
        foreach ($pkg['packages'] as $idx => $item ) {
            $box = [
                'Description'  => 'Package-'.($idx+1),
                'Packaging'    => ['Code'=>$pkg['ship_pkg']], // , 'Description'=>'Customer Supplied Package'
                'Dimensions'   => ['UnitOfMeasurement'=>['Code'=>$pkg['dimUOM']], // , 'Description'=>"Centimeters"
                    'Length' => (string)max(8, $item['length']),
                    'Width'  => (string)max(6, $item['width']),
                    'Height' => (string)max(4, $item['height'])],
                'PackageWeight'=> ['UnitOfMeasurement'=>['Code'=>$pkg['weightUOM']], // , 'Description'=>'Kilograms'
                    'Weight' => number_format($item['weight'], 1, '.', '')]];
            if (!empty($pkg['ship_ref_1'])) { $box['ReferenceNumber'][] = ['Code'=>'IK', 'Value'=>$pkg['ship_ref_1']]; }
            if (!empty($pkg['ship_ref_2'])) { $box['ReferenceNumber'][] = ['Code'=>'PO', 'Value'=>$pkg['ship_ref_2']]; }
            $request['ShipmentRequest']['Shipment']['Package'][] = $box;
        }
        $request['ShipmentRequest']['LabelSpecification']['HTTPUserAgent'] = 'Mozilla/4.5';
        if ($this->settings['printer_type']=='thermal') {
            $request['ShipmentRequest']['LabelSpecification']['LabelImageFormat']['Code']= 'ZPL'; // choices: GIF, ZPL, EPL and SPL.
            $request['ShipmentRequest']['LabelSpecification']['LabelStockSize']          = ['Height'=>'6', 'Width'=>'4'];
        } else {
            $request['ShipmentRequest']['LabelSpecification']['LabelImageFormat']['Code']= 'GIF';
        }
        return $request;
    }

    private function labelGetFormatLTL($pkg)
    {
        $request = [];
        return $request;
    }

// ***************************************************************************************************************
//                                UPS DELETE LABEL REQUEST
// ***************************************************************************************************************
    public function labelDelete($tracking_number='', $method='GND')
    {
        msgDebug("\nEntering labelDelete with tracking = $tracking_number and method = $method");
        if (empty($this->settings['rest_api_key'])) { return msgAdd($this->lang['err_no_creds']); }
        if (empty($tracking_number)) { return msgAdd("Cannot delete shipment, tracking number was not provided! $tracking_number"); }
        // For UPS multipiece shipments, deleting the first deletes all packages. Set a flag to prevent failures on subsequent deletions
        if (!empty($GLOBALS['upsDeleted'])) { return true; }
        // '1Z12345E0390817264' should work, but doesn't AND '1Z12345E0392508488' should fail
        $restURL = "api/shipments/$this->restVersion/void/cancel/$tracking_number";
        msgDebug("\nReady to send address request to url $restURL");
        $getUPS  = $this->queryREST($restURL, '', 'delete');
        if (empty($getUPS)) { return []; }
        $response= $getUPS->VoidShipmentResponse;
        if ($response->Response->ResponseStatus->Code == '1') {
            $GLOBALS['upsDeleted'] = true; // prevent multipiece deletion errors as the first deletes all in the shipment
            return true;
        } else {
            $message = '';
            if (!empty($response->Response->Alert)) { $message .= "Alert Code: {$response->Response->Alert->Code} - {$response->Response->Alert->Code}"; }
            return msgAdd($this->lang['RATE_ERROR'] . $message);
        }
        msgAdd("Summary Result Code: {$response->SummaryResult->Status->Code} - {$response->SummaryResult->Status->Description}", 'info');
        return ; // true
    }

// ***************************************************************************************************************
//                                UPS SHIP SUPPORT METHODS
// ***************************************************************************************************************
    private function prepShipment(&$request, $is_freight=false)
    {
        msgDebug("\nEntering prepShipment");
        $temp = clean('pkg_array', 'json', 'post');
        $rows = $temp['rows'];
        $request['total_weight']   = 0;
        $request['total_packages'] = 0;
        if ($is_freight) {
            foreach ($rows as $row) {
                $qty = clean($row['qty'], 'integer');
                if (!$qty) {
                    msgAdd('Error - quanity for one row is zero');
                    continue;
                }
                $row['weight']              = $qty * $row['weight']; // for freight get total line item weight
                $request['total_weight']   += $row['weight'];
                $request['total_packages'] += $qty;
                $request['packages'][0][]   = $row;
            }
        } else {
            foreach ($rows as $row) {
                $qty = clean($row['qty'], 'integer');
                if (!$qty) {
                    msgAdd('Error - quanity for one row is zero');
                    continue;
                }
                if ($qty == 1) {
                    $request['total_weight'] += $row['weight'];
                    $request['total_packages']++;
                    if (!$row['value']) { $row['value'] = $this->default_insurance_value; }
                    $request['packages'][] = $row;
                } else { // need to break into a request for each package for small package and express freight
                    $row['qty'] = 1;
                    for ($i=0; $i<$qty; $i++) {
                        $request['total_weight'] += $row['weight'];
                        $request['total_packages']++;
                        if (!$row['value']) { $row['value'] = $this->default_insurance_value; }
                        $request['packages'][] = $row;
                    }
                }
            }
        }
        $request['ship_date'] = strtotime($request['ship_date']);
        if ($request['ship_date'] < time() ) { $request['ship_date'] = time(); }
        if (empty($request['ship_ref_1']))   { $request['ship_ref_1']= time(); } // prevent duplicate shipping log records if left blank
        msgDebug("\nReturning from prepShipment with packages = ".print_r($request['packages'], true));
    }
}
