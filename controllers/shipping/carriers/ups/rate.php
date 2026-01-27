<?php
/*
 * Shipping extension for United Parcel Service - Rate
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/shipping/carriers/ups/rate.php
 */

namespace bizuno;

class upsRate extends upsCommon
{
    function __construct()
    {
        parent::__construct();
        $this->contact_type = clean('cType', ['format'=>'char', 'default'=>'c'], 'post');
        $this->currency = getDefaultCurrency();
        $this->choices  = explode(':', str_replace(' ', '', $this->settings['service_types']));
        msgDebug("\nUPSRate with options = ".print_r($this->options, true));
        msgDebug("\nUPSRate with settings = ".print_r($this->settings, true));
    }

    public function rateQuote($pkg)
    {
        $arrRates = [];
        $this->addCreds($pkg);
        if (empty($this->creds['rest_api_key']) || empty($this->creds['rest_secret'])) { msgAdd($this->lang['err_no_creds']); return $arrRates; }
        if ($pkg['settings']['weight'] == 0)   { msgAdd($this->lang['err_weight_zero']);   return $arrRates; }
        if (empty($pkg['destination']['postal_code'])) { msgAdd($this->lang['error_postal_code']); return $arrRates; }
        $services = explode(':', str_replace(' ', '', $this->settings['service_types']));
        if (($pkg['settings']['weight']/$pkg['settings']['num_boxes']) <= $this->settings['max_weight']) { // small package
            $this->rateRequestREST($arrRates, "api/rating/$this->restVersion/Shop", $pkg, $services, $pkg['settings']['num_boxes']);
        }
        if ($pkg['settings']['weight'] > $this->settings['max_weight']) { // freight quote
            msgAdd("UPS REST does not support LTL freight at this time");
        }
        return sortOrder($arrRates, 'cost');
    }

    private function rateRequestREST(&$arrRates, $restURL, $pkg, $user_choices, $num_packages, $ltl=false)
    {
        $request  = $this->payloadREST($pkg, $num_packages, $ltl);
        msgDebug("\nUPS REST Rate Request Submit String: ".print_r($request, true));
        $response = $this->queryREST($restURL, json_encode($request), 'post');
        if ($response) {
            if (!empty($response->RateResponse->Response->Alert)) {
                foreach ($response->RateResponse->Response->Alert as $alert) {
                    msgAdd("Rate response alert - Code: $alert->Code - $alert->Description", 'caution');
                }
            }
            if ($response->RateResponse->Response->ResponseStatus == 1) { // success
                if ($ltl) { $this->rateQuoteResponseLTL($arrRates, $response->RateResponse, $user_choices); }
                else      { $this->rateQuoteResponse   ($arrRates, $response->RateResponse, $user_choices, $pkg); }
            } else {
                msgAdd("There was an unexpected error from UPS REST. Please see trace.", 'trap');
            }
        }
        msgDebug("\narrRates is now: ".print_r($arrRates, true));
    }

    private function payloadREST($pkg, $num_packages, $ltl=false)
    {
        $wtUOM = getModuleCache('shipping', 'settings', 'general', 'weight_uom') <> 'LB' ? 'KGS' : 'LBS';
        $request = [
        'RateRequest' => [
            'Request' => [
                'SubVersion'          => '1701',
                'RequestOption'       => $ltl ? 'Rate' : 'Shop', // Rate or Shop
                'TransactionReference'=> ['TransactionIdentifier'=>'UPS REST Rate Shopping']],
            'CustomerClassification'  => ['Code'=>'01', 'Description'=>'Daily Rates'],
            'Shipment' => [
                'ShipmentRatingOptions'=> ['NegotiatedRatesIndicator'=>'Y'],  // 'TPFCNegotiatedRatesIndicator'=>'Y',
                'ShipmentCharge' => ['BillShipper'=>['AccountNumber'=>$this->settings['acct_number']]],
                'Shipper'        => $this->mapAddress($pkg['shipper']),
                'ShipTo'         => $this->mapAddress($pkg['destination']),
                'ShipFrom'       => $this->mapAddress($pkg['origin']),
                'PaymentDetails' => ['ShipmentCharge'=>['Type'=>'01','BillShipper'=>['AccountNumber'=>$this->settings['acct_number']]]]]]];
        $request['RateRequest']['Shipment']['Shipper']['ShipperNumber'] = $this->settings['acct_number'];
//      if ($ltl) { $request['RateRequest']['Shipment']['Service'] = ['Code'=> '03', 'Description'=>'UPS Ground']; }
        for ($i = 0; $i < $num_packages; $i++) {
            $box = [
//              'LargePackageIndicator'=> "X",
                'PackagingType'=> ['Code'=>'02', 'Description'=>'Your Package'],
                'Dimensions'   => ['UnitOfMeasurement'=>['Code'=>getModuleCache('shipping', 'settings', 'general', 'dim_uom')], // 'Description'=>'INCHES'
                    'Length'=> (string)ceil((!empty($pkg['settings']['length'])?$pkg['settings']['length']:8) / $pkg['settings']['num_boxes']),
                    'Width' => (string)ceil((!empty($pkg['settings']['width']) ?$pkg['settings']['width'] :8) / $pkg['settings']['num_boxes']),
                    'Height'=> (string)ceil((!empty($pkg['settings']['height'])?$pkg['settings']['height']:8) / $pkg['settings']['num_boxes'])],
                'PackageWeight'=> ['UnitOfMeasurement'=>['Code'=>$wtUOM],
                    'Weight'=> number_format($pkg['settings']['weight'] / $num_packages, 1, '.', '')]];
            if (!empty($pkg['settings']['insurance']) && !empty($pkg['settings']['ins_amount'])) {
                $box['InsuredValue'] = [
                    'Amount'  => number_format($pkg['settings']['ins_amount']/$num_packages, 2, '.', ''),
                    'Currency'=> getDefaultCurrency()];
            }
            $request['RateRequest']['Shipment']['Package'][] = $box;
        }
        return $request;
    }

    private function rateQuoteResponse(&$arrRates, $response)
    {
        msgDebug("\nEntering rateQuoteResponse with this->options = ".print_r($this->options, true));
        if (empty($response->RatedShipment))     { return; } // happens when no services are available
        if (is_object($response->RatedShipment)) { $response->RatedShipment = [$response->RatedShipment]; } // if only one response
        foreach ($response->RatedShipment as $rateReply) {
            $service = isset($this->options['rateCodes'][$rateReply->Service->Code]) ? $this->options['rateCodes'][$rateReply->Service->Code] : false;
            if (!empty($service) && in_array($service, $this->options['rateCodes'])) {
                msgDebug("\nFound UPS rateReply->ServiceType: ".$rateReply->Service->Code);
                $arrRates[$service] = ['title'=>$this->lang[$service], 'gl_acct'=>$this->settings['gl_acct_'.$this->contact_type], 'book'=>'', 'cost'=>'', 'quote'=>'', 'note'=>''];
                if (isset($rateReply->NegotiatedRateCharges)) {
                    $arrRates[$service]['cost'] = $rateReply->NegotiatedRateCharges->TotalCharge->MonetaryValue;
                } else {
                    $arrRates[$service]['cost'] = $rateReply->RatedPackage->TotalCharges->MonetaryValue;
                }
                $surcharges = $rateReply->ServiceOptionsCharges->MonetaryValue;
                $baserate   = $rateReply->TransportationCharges->MonetaryValue;
                $arrRates[$service]['book']  = $baserate + $surcharges;
                $arrRates[$service]['quote'] = $arrRates[$service]['book'];
                switch ($rateReply->Service->Code) {
                    case '03': $arrRates[$service]['note'] = 'No commit provided.'; break;
                    default:
                        $deliverTime = isset($rateReply->GuaranteedDelivery->DeliveryByTime) ? $rateReply->GuaranteedDelivery->DeliveryByTime : 'End of day';
                        $arrRates[$service]['note'] = ' Commit: '.biz_date("D M j, ", strtotime($this->calculateDelivery($rateReply->GuaranteedDelivery->BusinessDaysInTransit))).' '.$deliverTime;
                }
                if (!isset($rateReply->RatedShipmentAlert))   { $rateReply->RatedShipmentAlert = []; } // happens when no alerts are present
                if (is_object($rateReply->RatedShipmentAlert)){ $rateReply->RatedShipmentAlert = [$rateReply->RatedShipmentAlert]; }
//                foreach ($rateReply->RatedShipmentAlert as $alert) { $alerts[$alert->Code] = $alert->Description; }
            }
        }
    }

    private function rateQuoteResponseLTL(&$arrRates, $response, $user_choices)
    {
        $service = isset($this->rateCodes[$response->Service->Code]) ? $this->rateCodes[$response->Service->Code] : false;
        $arrRates[$service] = ['title'=>$this->lang[$service], 'gl_acct'=>$this->settings['gl_acct_'.$this->contact_type], 'book'=>'', 'cost'=>'', 'quote'=>'', 'note'=>''];
        foreach ($response->Rate as $rate) {
            if ($service && in_array($service, $user_choices)) {
                switch($rate->Type->Code) {
                    case 'LND_GROSS':
                        $arrRates[$service]['book']  = $rate->Factor->Value;
                        $arrRates[$service]['quote'] = $arrRates[$service]['book'];
                    default:
                }
            }
        }
        $arrRates[$service]['cost'] = $response->TotalShipmentCharge->MonetaryValue;
        $arrRates[$service]['note'] = 'No commit provided.';
    }
}
