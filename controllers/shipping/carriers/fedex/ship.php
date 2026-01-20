<?php
/*
 * Shipping extension for Federal Express - Ship
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
 * @version    7.x Last Update: 2026-01-20
 * @filesource /controllers/shipping/carriers/fedex/ship.php
 *
 */

namespace bizuno;

class fedexShip extends fedexCommon
{
    private $fedex_results= [];
    private $delDate      = 0;
    private $pkgNotes     = '';
    public  $is_freight;
    public  $labeltypes;
    public  $labels;

    function __construct()
    {
        parent::__construct();
    }

// ***************************************************************************************************************
//                                FEDEX LABEL REQUEST (multipiece compatible)
// ***************************************************************************************************************
    public function labelGet($request=[])
    {
        $this->is_freight = (in_array($request['ship_method'], ['GDF','ECF'])) ? true : false;
        $this->prepShipment($request);
        $this->addCreds($request);
        if ($this->is_freight) { $this->getFreightRate($request); }
        $this->getLabelREST($request, false);
        if (!empty($request['ship_return'])) { // Return shipment, switch to-from and some other things and get return label
            msgDebug("\nProcessing return package array: ".print_r($request['packages'], true));
            $packages = $request['packages'];
            foreach ($packages as $package) { // Because REST API cannot do multi-piece return labels
                unset($request['packages']);
                $request['packages'][] = $package;
                $this->getLabelREST($request, true);
            }
            $request['packages'] = $packages;
        }
        return $this->fedex_results;
    }

    /**
     * Retrieves label(s) from FedEx
     * @param array $request
     * @return boolean|array a array of the successful getting the labels or false on fail w/message send through messageStack
     */
    private function getLabelREST($request=[], $is_return=false)
    {
        global $io;
        msgDebug("\nEntering getLabelREST");
        if (empty($this->settings['rest_api_key']) || empty($this->settings['rest_secret'])) { return msgAdd($this->lang['err_no_creds']); }
        if (in_array($request['ship_method'], ['I1D','I2D','IGD'])) { // unsupported ship carriers
          return msgAdd('The ship method requested is not supported by this tool presently. Please ship the package via the fedex website (www.FedEx.com).');
        }
        $payload= $this->is_freight ? $this->payloadREST_LTL($request) : $this->payloadREST($request, $is_return);
        msgDebug("\njson encoded payload = ".print_r(json_encode($payload), true));
        $resp   = $this->queryREST($this->is_freight?'ship/v1/freight/shipments':'ship/v1/shipments', $payload);
        if (!empty($resp['errors'])) {
            foreach ($resp['errors'] as $error) { msgAdd("Error! FedEx rate request <br />Code: {$error['code']}<br />Message: {$error['message']}"); }
            return;
        }
        if (empty($resp['output']['transactionShipments'])) { return; }
        foreach ( $resp['output']['transactionShipments'] as $cnt => $pkg) {
            $this->labeltypes = $this->labels = [];
            if ($cnt == 0 && !$is_return && isset($pkg['masterTrackingNumber'])) { $request['MasterTracking'] = $pkg['masterTrackingNumber']; }

            if (isset($pkg['shipmentDocuments']) && is_array($pkg['shipmentDocuments']) && sizeof($pkg['shipmentDocuments'])>0) {
                $this->extractLabels($pkg['shipmentDocuments'], $pkg['masterTrackingNumber']);
            }
            $this->extractPackages($pkg, $request, $is_return);
            if (empty($this->labels)) { return msgAdd('Error - No tracking or labels found in return string.'); }
            $date      = explode('-', biz_date('Y-m-d')); // , $request['ship_date'] - Removed returned ship date because shipments can go to next day as FedEx is probably using UTC-0
            $file_path = "data/shipping/labels/$this->code/{$date[0]}/{$date[1]}/{$date[2]}/";
            foreach ($this->labels as $label) {
                if (!$io->fileWrite($label['label'], $file_path.$label['filename'], true)) { return; }
                msgDebug("\nSuccessfully retrieved the FedEx shipping label. Filename # {$label['filename']}");
            }
        }
    }

    private function extractLabels($docs, $tracking)
    {
        foreach ($docs as $idx => $label) {
            if       (in_array($label['docType'], ['EPL2', 'ZPLII'])) { // thermal printer, decode to keep prior format
                $filename = $tracking.($idx > 0 ? '-'.$idx : '').'.lpt';
            } elseif (in_array($label['docType'], ['PDF'])) { // plain paper
                $filename = $tracking.($idx > 0 ? '-'.$idx : '').'.pdf';
            } else {
                $filename = '';
                msgAdd("unkown label type: {$label['labeltypes']}");
            }
            if (!empty($filename)) {
                $this->labeltypes[]= $label['docType'];
                $this->labels[] = ['filename'=>$filename, 'label'=>base64_decode($label['encodedLabel'])];
            }
        }
    }

    private function extractPackages($pkg, $request, $is_return)
    {
        $net = $book = $zone = '';
        // sometimes FedEx returns lower rates with PAYOR_ACCOUNT_PACKAGE and others with PAYOR_ACCOUNT_SHIPMENT
        // it appears that the first one is always lower so assume that is the one to use
        if (!empty($pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'])) {
            $net = $pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalNetCharge'];
            $book= $pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalBaseCharge'] +
                   $pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalSurcharges'] +
                   $pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['totalTaxes'];
            $zone= $pkg['completedShipmentDetail']['shipmentRating']['shipmentRateDetails'][0]['rateZone'];
        }
        $ship_date = strtotime(biz_date('Y-m-d H:i:s'));
        foreach ($pkg['pieceResponses'] as $box) {
            $del_date  = !empty($pkg['completedShipmentDetail']['operationalDetail']['deliveryDate']) ? $pkg['completedShipmentDetail']['operationalDetail']['deliveryDate'] : $box['deliveryDatestamp'];
            $this->extractLabels($box['packageDocuments'], $box['trackingNumber']);
            $this->fedex_results[] = [
                'ref_id'       => $request['ship_ref_1'],
                'method'       => $is_return ? 'GND' : $request['ship_method'],
                'ship_date'    => $ship_date,
                'tracking'     => $box['trackingNumber'],
                'zone'         => $zone,
                'book_cost'    => floatval($book) / sizeof($pkg['pieceResponses']),
                'net_cost'     => !empty($box['baseRateAmount']) ? floatval($box['baseRateAmount']) : (floatval($net) / sizeof($pkg['pieceResponses'])), // this needs to be cost per box,
                'delivery_date'=> !empty($this->delDate) ? $this->delDate : $del_date,
                'total_cost'   => $net,
                'notes'        => $this->pkgNotes];
        }
    }

    private function getFreightRate($request=[])
    {
        require_once(__DIR__.'/rate.php');
        $rates   = new fedexRate();
        $request['settings']['ship_date'] = clean('ship_date', 'date', 'post'); // fix the ship date location
        $frtReq  = $rates->payloadREST_LTL($request, $request['settings']['num_boxes']);
        $arrRates= $rates->getRatesREST('rate/v1/freight/rates/quotes', $frtReq);
        msgDebug("\nFetched rates from FedEx Freight: ".print_r($arrRates, true));
        foreach ($arrRates as $meth => $rate) {
            if ($meth == $request['ship_method']) {
                msgDebug("\nSet the delivery date to {$rate['delDate']}");
                $this->delDate  = $rate['delDate'];
                $this->pkgNotes.= $rate['note'];
            }
        }
    }

    private function payloadREST($pkg, $is_return=false)
    {
        msgDebug("\nEntering payloadREST with is_return = $is_return and pkg = ".print_r($pkg, true));
        $methods = array_flip($this->options['rateCodes']);
        $payload = [
            'accountNumber'       => ['value'=>$pkg['shipper']['creds']['acct_number']],
            'labelResponseOptions'=> 'LABEL',  // option 'URL_ONLY' will return link to fedex.com to retireve label
            'requestedShipment'   => [
                'shipper'               => [ 'contact'=>$this->mapContact($pkg['shipper']),    'address'=>$this->mapAddress($pkg['shipper'])],
                'recipients'            => [['contact'=>$this->mapContact($pkg['destination']),'address'=>$this->mapAddress($pkg['destination'])]],
                'shipDatestamp'         => $this->today,
                'serviceType'           => $methods[$pkg['ship_method']],
                'packagingType'         => 'YOUR_PACKAGING',
                'pickupType'            => 'USE_SCHEDULED_PICKUP',
                'blockInsightVisibility'=> false]];
        switch ($pkg['ship_bill_to']) {
            case 'COLLECT':
            case 'RECIPIENT':
            case 'THIRD_PARTY':
                $payload['requestedShipment']['shippingChargesPayment'] = ['paymentType'=>$pkg['ship_bill_to'],
                    'payor'=>['responsibleParty'=>['accountNumber'=>['value'=>$pkg['ship_bill_act']]]]]; // ,'contact'=>'Shipping Dept.'
                break;
            default:
            case 'SENDER':
                $payload['requestedShipment']['shippingChargesPayment'] = ['paymentType'=>'SENDER'];
        }
        $this->addSmartPost($payload, $pkg);
        $this->addLabelSpec($payload, $pkg);
        $this->addEmailNotifications($payload, $pkg);
        $this->addSpSrv($payload, $pkg, $is_return);
        $payload['requestedShipment']['requestedPackageLineItems'] = $this->addPkgs($pkg);
        $this->addCustoms($payload, $pkg);
        if ($is_return) { // make some changes to return label request
            $recip = $this->mapAddress($pkg['shipper']);
            array_unshift($recip['streetLines'], 'Return/Recycle Program'); // append return department
            $payload['requestedShipment']['shipper']     = [ 'contact'=>$this->mapContact($pkg['destination']),'address'=>$this->mapAddress($pkg['destination'])];
            $payload['requestedShipment']['recipients']  = [['contact'=>$this->mapContact($pkg['shipper']),    'address'=>$recip]];
            $payload['requestedShipment']['dropoffType'] = 'BUSINESS_SERVICE_CENTER';
            $payload['requestedShipment']['serviceType'] = 'FEDEX_GROUND';
            $payload['requestedShipment']['shippingChargesPayment'] = ['paymentType'=>'SENDER'];
        }
        msgDebug("\nReturning from payloadREST with payload = ".print_r($payload, true));
        return $payload;
    }

    private function payloadREST_LTL($pkg)
    {
        msgDebug("\nEntering payloadREST_LTL with pkg = ".print_r($pkg, true));
        $methods  = array_flip($this->options['rateCodes']);
        $payload = [
            'accountNumber'           => ['value'=>$pkg['origin']['creds']['ltl_acct_num']],
            'labelResponseOptions'    => 'LABEL',  // option 'URL_ONLY' will return link to fedex.com to retrieve label
            'freightRequestedShipment'=> [
                'shipper'                  => ['contact'=>$this->mapContact($pkg['origin']),     'address'=>$this->mapAddress($pkg['origin'])],
                'recipient'                => ['contact'=>$this->mapContact($pkg['destination']),'address'=>$this->mapAddress($pkg['destination'])],
//              'shipDatestamp'            => $this->today,
                'serviceType'              => $methods[$pkg['ship_method']],
                'packagingType'            => 'YOUR_PACKAGING',
                'pickupType'               => 'USE_SCHEDULED_PICKUP', // 'CONTACT_FEDEX_TO_SCHEDULE'
                'totalWeight'              => $pkg['total_weight'],
//              'blockInsightVisibility'   => false,
                'shippingChargesPayment'   => [
                    'paymentType'=> 'SENDER',
                    'payor'      => ['responsibleParty'=>['accountNumber'=>['value'=>$pkg['origin']['creds']['acct_number']],
                        'contact'=>$this->mapContact($pkg['origin']), 'address'=>$this->mapAddress($pkg['origin'])]]],
                'freightShipmentDetail'    => $this->addPkgsFrt($pkg),
                'requestedPackageLineItems'=> $this->reqPkgsFrt($pkg)]];
        $this->addLabelSpecLTL($payload, $pkg, $methods);
//      $this->addEmailNotifications($payload, $pkg);
        $this->addSpSrvFrt($payload);
        msgDebug("\nReturning from payloadREST with payload = ".print_r($payload, true));
        return $payload;
    }

    private function addSmartPost(&$payload, $pkg)
    {
        msgDebug("\nEntering addSmartPost with ship_method = ".print_r($pkg['ship_method'], true));
        if ($pkg['ship_method']<>'3DA') { return; }
        $payload['requestedShipment']['serviceType'] = 'SMART_POST';
        $payload['requestedShipment']['smartPostInfoDetail'] = [
            'hubId'  => $pkg['shipper']['creds']['gnd_econ_hub'],
            'indicia'=> 'PARCEL_SELECT'];
    }
    
    private function addLabelSpec(&$payload, $pkg)
    {
        $payload['requestedShipment']['labelSpecification'] = [
            'labelFormatType'         => 'COMMON2D',
            'imageType'               => 'ZPLII',
            'labelStockType'          => clean($this->settings['label_thermal'], 'cmd'),
            'labelPrintingOrientation'=> 'TOP_EDGE_OF_TEXT_FIRST',
            'customerSpecifiedDetail' => $this->addLabelDocTab($pkg)];
    }

    private function addLabelSpecLTL(&$payload, $pkg)
    {
        $payload['freightRequestedShipment']['labelSpecification'] = [
            'labelFormatType'         => 'COMMON2D',
            'imageType'               => 'ZPLII',
            'labelStockType'          => clean($this->settings['label_thermal'], 'cmd'),
            'labelPrintingOrientation'=> 'TOP_EDGE_OF_TEXT_FIRST',
            'customerSpecifiedDetail' => $this->addLabelDocTab($pkg)];
        $payload['freightRequestedShipment']['shippingDocumentSpecification'] = [
            'shippingDocumentTypes'    => ['FEDEX_FREIGHT_STRAIGHT_BILL_OF_LADING'],
            'freightBillOfLadingDetail'=> ['format'=>['docType'=>'PDF', 'stockType'=>'PAPER_LETTER']]];
    }

    private function addLabelDocTab() // passed $pkg not used
    {
        $docTab = [
//          "maskedData"      => ["CUSTOMS_VALUE","TOTAL_WEIGHT"],
//          "regulatoryLabels"=> [["generationOptions"=>"CONTENT_ON_SHIPPING_LABEL_ONLY","type"=>"ALCOHOL_SHIPMENT_LABEL"]],
//          "additionalLabels"=> [["type"=>"CONSIGNEE","count"=> 1]],
            'docTabContent'=> [
                'docTabContentType'=> 'ZONE001',
                'zone001'          => [
                    'docTabZoneSpecifications' => [
                        ['zoneNumber'=>  1,'header'=>'Date',    'justification'=>'RIGHT','dataField'=>'REQUEST/SHIPMENT/ShipTimestamp'],
                        ['zoneNumber'=>  2,'header'=>'Del Date','justification'=>'RIGHT','dataField'=>'REPLY/SHIPMENT/OperationalDetail/DeliveryDate'],
                        ['zoneNumber'=>  3,'header'=>'PO Num',  'justification'=>'RIGHT','dataField'=>'REQUEST/PACKAGE/CustomerReferences[2]/Value'],
                        ['zoneNumber'=>  4,'header'=>'Inv Num', 'justification'=>'RIGHT','dataField'=>'REQUEST/PACKAGE/CustomerReferences[1]/Value'],
                        ['zoneNumber'=>  5,'header'=>'Weight',  'justification'=>'RIGHT','dataField'=>'REQUEST/PACKAGE/weight/Value'],
                        ['zoneNumber'=>  6,'header'=>'Dim Wt',  'justification'=>'RIGHT','dataField'=>'REPLY/PACKAGE/RATES/ACTUAL/DimWeight/Value'],
                        ['zoneNumber'=>  7,'header'=>'Pkg #',   'justification'=>'RIGHT','dataField'=>'REQUEST/PACKAGE/SequenceNumber'],
                        ['zoneNumber'=>  8,'header'=>'Pkg Cnt', 'justification'=>'RIGHT','dataField'=>'REQUEST/SHIPMENT/PackageCount'],
                        ['zoneNumber'=>  9,'header'=>'Dec Val', 'justification'=>'RIGHT','dataField'=>'REQUEST/PACKAGE/InsuredValue/Amount'],
//                      ['zoneNumber'=> 10,'header'=>'TBD',     'justification'=>'RIGHT','dataField'=>'string','literalValue'=>'string'],
                        ['zoneNumber'=> 11,'header'=>'List',    'justification'=>'RIGHT','dataField'=>'REPLY/PACKAGE/RATE/LIST/NetCharge/Amount'],
                        ['zoneNumber'=> 12,'header'=>'Net Chrg','justification'=>'RIGHT','dataField'=>'REPLY/SHIPMENT/RATES/ACTUAL/TotalNetCharge/Amount'],
                    ],
                ],
            ]
        ];
        return $docTab;
    }

// ***************************************************************************************************************
//                                FEDEX DELETE LABEL REQUEST
// ***************************************************************************************************************
    public function labelDelete($tracking_number='', $method='GND')
    {
        msgDebug("\nEntering FedEx:labelDelete with tracking # = $tracking_number and method = $method");
        if (empty($this->settings['rest_api_key']) || empty($this->settings['rest_secret'])) { return msgAdd($this->lang['err_no_creds']); }
        if (empty($tracking_number)) { return msgAdd("Cannot delete shipment, tracking number was not provided! $tracking_number"); }
        if ($method=='GDF' || $method=='ECF') { //can not delete freight shipment online
            msgAdd("Cannot delete freight shipment online, please call fedex to cancel or update a freight shipment! In most cases the shipment is not recognized by FedEx unitl a driver scans the shipping label. Tracking # $tracking_number", 'info');
            return true;
        }
        $payload = ['accountNumber'=>['value'=>$this->settings['acct_number']], 'trackingNumber'=>$tracking_number];
        $resp   = $this->queryREST('ship/v1/shipments/cancel', $payload, 'put');
        if (!empty($resp['errors'])) {
            foreach ($resp['errors'] as $error) { msgAdd("Error! FedEx Label Delete <br />Code: {$error['code']}<br />Message: {$error['message']}"); }
        }
        return !empty($resp['output']['cancelledShipment']) ? true : false;
    }

// ***************************************************************************************************************
//                                FEDEX SHIP SUPPORT METHODS
// ***************************************************************************************************************
    private function prepShipment(&$request)
    {
        msgDebug("\nEntering prepShipment");
        $totalWt = $totalIns = 0;
        $request['packages'] = [];
        if (empty($request['ship_ref_1']))    { $request['ship_ref_1']= time(); } // prevent duplicate shipping log records if left blank
        if ($request['ship_method'] == 'GDR') { $request['ship_resi'] = '1'; }
        if (strtotime($request['ship_date']) < time() )  { $request['ship_date'] = time(); }
        foreach ($request['pkgs'] as $row) {
            for ($i=0; $i<$row['qty']; $i++) {
                if ($row['weight'] == 0) { continue; }
                $wt       = max(1, ceil(!empty($row['weight']) ? floatval($row['weight']) : 0));
                $ins      = !empty($row['value']) ? intval($row['value']) : $this->default_insurance_value;
                $request['packages'][] = ['weight'=>$wt, 'value'=>$ins,
                    'length'=> ceil(!empty($row['length'])? floatval($row['length']): 8),
                    'width' => ceil(!empty($row['width']) ? floatval($row['width']) : 6),
                    'height'=> ceil(!empty($row['height'])? floatval($row['height']): 4)];
                $totalWt += $wt;
                $totalIns+= $ins;
            }
        }
        $request['total_weight']   = $totalWt;
        $request['total_insurance']= $totalIns;
        $request['total_packages'] = sizeof($request['packages']);
        msgDebug("\nReturning from prepShipment with packages = ".print_r($request['packages'], true));
    }
}
