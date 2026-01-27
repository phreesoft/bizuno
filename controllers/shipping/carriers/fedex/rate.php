<?php
/*
 * Shipping extension for Federal Express - Rate
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
 * @version    7.x Last Update: 2025-07-23
 * @filesource /controllers/shipping/carriers/fedex/rate.php
 *
 * FedEx Developer Rate:
 * FedEx Developer LTL Rate: https://developer.fedex.com/api/en-us/catalog/ltl-freight/v1/docs.html#operation/Freight%20Shipment
 */

namespace bizuno;

class fedexRate extends fedexCommon
{
    public $contact_type;
    public $currency;
    public $choices;
    public $storeID;
    
    function __construct()
    {
        parent::__construct();
        $this->contact_type = clean('cType', ['format'=>'char', 'default'=>'c'], 'post');
        $this->currency = getDefaultCurrency();
        $this->choices  = explode(':', str_replace(' ', '', $this->settings['service_types']));
        $this->storeID  = clean('store_id_b', 'integer', 'post');
        msgDebug("\nfedexRate with options = ".print_r($this->options, true));
        msgDebug("\nfedexRate with settings = ".print_r($this->settings, true));
    }

    public function rateQuote($pkg)
    {
        $arrRates = [];
        $this->addCreds($pkg);
        if (empty($this->settings['rest_api_key']) || empty($this->settings['rest_secret'])) { msgAdd($this->lang['err_no_creds']); return $arrRates; }
        if ($pkg['settings']['weight'] == 0)   { msgAdd($this->lang['err_weight_zero']);   return $arrRates; }
        if (empty($pkg['destination']['postal_code'])) { msgAdd($this->lang['error_postal_code']); return $arrRates; }
        $this->prepShipment($pkg);
        if (($pkg['settings']['weight']/$pkg['settings']['num_boxes']) <= $this->settings['max_weight']) { // small package
            $request = $this->payloadREST($pkg, $pkg['settings']['num_boxes']);
            $arrRates= $this->getRatesREST('rate/v1/rates/quotes', $request);
        } elseif ($pkg['settings']['weight'] > $this->settings['max_weight']) { // Express/Ground LTL Freight
            $expReq  = $this->payloadREST($pkg, $pkg['settings']['num_boxes']);
            $arrRates= array_merge($arrRates, $this->getRatesREST('rate/v1/rates/quotes', $expReq));
            if (in_array($pkg['origin']['country'], ['US','CA'])) {
                $frtReq  = $this->payloadREST_LTL($pkg, $pkg['settings']['num_boxes']);
                $arrRates= array_merge($arrRates, $this->getRatesREST('rate/v1/freight/rates/quotes', $frtReq));
            }
        }
        return sortOrder($arrRates, 'cost');
    }

    /**
     * Needs to be public to fetch LTL rates when printing labels
     * @param type $path
     * @param type $request
     * @return string|array
     */
    public function getRatesREST($path, $request)
    {
        $arrRates= [];
        $resp    = $this->queryREST($path, $request);
        if (!empty($resp['errors'])) {
            foreach ($resp['errors'] as $error) {
                msgAdd("Error! FedEx rate request <br />Code: {$error['code']}<br />Message: {$error['message']}");
            }
            return $arrRates;
        }
        if (empty($resp['output']['rateReplyDetails'])) { return $arrRates; }
        foreach ($resp['output']['rateReplyDetails'] as $rateReply) {
            $service = !empty($this->options['rateCodes'][$rateReply['serviceType']]) ? $this->options['rateCodes'][$rateReply['serviceType']] : 'XXX';
            msgDebug("\nFound Bizuno service code: $service and FedEx ServiceType: ".$rateReply['serviceType']);
            if (!in_array($service, $this->choices)) { continue; }
            $arrRates[$service] = ['title'=>$this->lang[$service], 'gl_acct'=>$this->settings['gl_acct_'.$this->contact_type], 'book'=>'', 'cost'=>'', 'quote'=>'', 'note'=>''];
            if (!empty($rateReply['commit']['dateDetail']['dayFormat'])) {
                $arrRates[$service]['note'] = ' Commit: '.biz_date("D M j, g:i a",strtotime($rateReply['commit']['dateDetail']['dayFormat']));
                $arrRates[$service]['delDate']= substr($rateReply['commit']['dateDetail']['dayFormat'], 0 ,10);
            } else {
                $arrRates[$service]['note'] = 'N/A';
            }
            foreach ($rateReply['ratedShipmentDetails'] as $rates) {
                switch ($rates['rateType']) {
                    case 'ACCOUNT':$arrRates[$service]['cost'] = $rates['totalNetFedExCharge']; break;
                    case 'LIST':   $arrRates[$service]['quote']= $rates['totalNetFedExCharge'];
                                   $arrRates[$service]['book'] = $rates['totalNetFedExCharge']; break;
                }
            }
        }
        msgDebug("\nFedEx returned arrRates = ".print_r($arrRates, true));
        return $arrRates;
    }

    private function payloadREST($pkg, $num_packages=1)
    {
        msgDebug("\nEntering payloadREST with num_packages = $num_packages and pkg = ".print_r($pkg, true));
        // check the ship date to see if it is within range
        $ship_date = substr(biz_date('c', strtotime($pkg['settings']['ship_date'])), 0, 19);
        if ($ship_date < $this->today) { $ship_date = biz_date('c'); msgAdd($this->lang['err_invalid_ship_date'], 'caution'); }
        $payload = [
            'accountNumber'     => ['value'=>$pkg['origin']['creds']['acct_number']],
            'rateRequestControlParameters' => [
                'returnTransitTimes'         => true, // false
//              'servicesNeededOnRateFailure'=> true,
//              'rateSortOrder'              =>'SERVICENAMETRADITIONAL',
                'variableOptions'            =>'FREIGHT_GUARANTEE'],
//          'carrierCodes'=> [ 'FDXE' ],
            'requestedShipment' => [
                'shipper'          => ['address'=>$this->mapAddress($pkg['origin'])], // address only
                'recipient'        => ['address'=>$this->mapAddress($pkg['destination'])], // address only
//              'preferredCurrency'=> $this->currency,
                'shippingChargesPayment'   => [
                    'paymentType'=> 'SENDER',
                    'payor'      => ['responsibleParty'=>['address'=>$this->mapAddress($pkg['origin']),'accountNumber'=>['value'=>$pkg['origin']['creds']['acct_number']]]]],
                'rateRequestType'  => ['ACCOUNT','LIST'],
                'shipDatestamp'    => $ship_date, // DOESN'T MATCH DOCUMENTATION, [shipDateStamp]
                'pickupType'       => $this->test ? 'DROPOFF_AT_FEDEX_LOCATION' : 'USE_SCHEDULED_PICKUP',
//              'documentShipment' => false,
                'packagingType'    => 'YOUR_PACKAGING',
//              'totalPackageCount'=> $num_packages,
//              'totalWeight'      => number_format($pkg['settings']['weight'],1,'.',''), // INTERNATIONAL ONLY
//              'groupShipment'    => true,
//              'serviceTypeDetail'=> ['carrierCode'=>'FDXE','description'=>'string','serviceName'=>'string','serviceCategory'=>'string'],
                'smartPostInfoDetail' =>['hubId'=>$this->settings['sp_hub'], 'indicia'=> 'PARCEL_SELECT'], // 'ancillaryEndorsement'=>'ADDRESS_CORRECTION', 'specialServices'=> 'USPS_DELIVERY_CONFIRMATION'
//              'expressFreightDetail'=>['bookingConfirmationNumber'=>'string','shippersLoadAndCount'=> 0],
//              'groundShipment'   => false,
                'requestedPackageLineItems' => $this->addPkgs($pkg)], // EOF - requestedShipment
            'carrierCodes'=> ['FDXE', 'FDXG', 'FXSP']]; // Required to get Ground Economy rates (despite documentation)
        $this->addEmailNotifications($payload, $pkg);
        $this->addCustoms($payload, $pkg);
        msgDebug("\nReturning from payloadREST with payload = ".print_r($payload, true));
        return $payload;
    }

    /**
     * Needs to be public to fetch LTL rates when printing labels
     * @param type $pkg
     * @param type $num_packages
     * @return array
     */
    public function payloadREST_LTL($pkg, $num_packages=1)
    {
        msgDebug("\nEntering payloadREST_LTL with num_packages = $num_packages and pkg = ".print_r($pkg, true));
        // check the ship date to see if it is within range
        $ship_date  = substr(biz_date('c', strtotime($pkg['settings']['ship_date'])), 0, 19);
        if ($ship_date < $this->today) { $ship_date = biz_date('c'); msgAdd($this->lang['err_invalid_ship_date'], 'caution'); }
        // Error checks
        $payload = [
            'accountNumber'               => ['value'=>$pkg['origin']['creds']['ltl_acct_num']], // $this->testBill['account']
            'rateRequestControlParameters'=> ['returnTransitTimes'=>true], // false
            'freightRequestedShipment'    => [
//              'serviceType'              => 'FEDEX_FREIGHT_PRIORITY', // leave off to shop rates??? FedEx says required
                'shipper'                  => ['address'=>$this->mapAddress($pkg['origin'])],
                'recipient'                => ['address'=>$this->mapAddress($pkg['destination'])], // address only
//              'packagingType'            => 'PALLET', // FedEx has required
                'shippingChargesPayment'   => [
                    'paymentType'=> 'SENDER',
                    'payor'      => ['responsibleParty'=>['address'=>$this->mapAddress($pkg['origin']),'accountNumber'=>['value'=>$pkg['origin']['creds']['ltl_acct_num']]]]],
                'rateRequestType'          => ['ACCOUNT','LIST'],
                'freightShipmentDetail'    => $this->addPkgsFrt($pkg),
                'requestedPackageLineItems'=> $this->reqPkgsFrt($pkg),
//              'preferredCurrency'        => $this->currency,
//              'shipDatestamp'            => $ship_date, // DOESN'T MATCH DOCUMENTATION, [shipDateStamp]
//              'pickupType'               => 'USE_SCHEDULED_PICKUP',
//              'totalPackageCount'        => $num_packages,
//              'totalWeight'              => number_format($pkg['settings']['weight'],1,'.',''), // optional - Integer only
            ], // EOF - freightRequestedShipment
        ];
        $this->addSpSrvFrt($payload);
//      msgDebug("\nReturning from payloadREST_LTL with payload = ".print_r($payload, true));
        return $payload;
    }

// ***************************************************************************************************************
//                                FEDEX RATE SUPPORT METHODS
// ***************************************************************************************************************
    private function prepShipment(&$pkg)
    {
        msgDebug("\nEntering prepShipment");
        $pkg['packages'] = [];
        $pkg['ship_date']= strtotime($pkg['settings']['ship_date']);
        for ($i=0; $i<$pkg['settings']['num_boxes']; $i++) {
            $box = [
                'weight' => ceil($pkg['settings']['weight']/$pkg['settings']['num_boxes']),
                'length' => !empty($pkg['settings']['length'])? ceil($pkg['settings']['length']): 8,
                'width'  => !empty($pkg['settings']['width']) ? ceil($pkg['settings']['width']) : 6,
                'height' => !empty($pkg['settings']['height'])? ceil($pkg['settings']['height']): 4,
                'value'  => !empty($pkg['settings']['ins_amount']) ? intval($pkg['settings']['ins_amount']/$pkg['settings']['num_boxes']) : $this->default_insurance_value];
            $pkg['packages'][] = $box;
        }
        $pkg['total_weight']   = $pkg['settings']['weight'];
        $pkg['total_insurance']= !empty($pkg['settings']['ins_amount']) ? $pkg['settings']['ins_amount'] : $this->default_insurance_value;
        $pkg['total_packages'] = sizeof($pkg['packages']);
        if (sizeof($pkg['packages'])>1 && in_array('3DA', $this->choices)) {
            msgAdd('FedEx Ground Economy is not permitted for multi-piece shipments. No rates will be returned for this service.', 'caution');
            $key = array_search('3DA', $this->choices);
            unset($this->choices[$key]);
        }
        msgDebug("\nReturning from prepShipment with packages = ".print_r($pkg['packages'], true));
    }
}
