<?php
/*
 * Shipping extension for Endicia - Rate
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
 * @filesource /controllers/shipping/carriers/endicia/rate.php
 */

namespace bizuno;

class endiciaRate extends endiciaCommon
{
    private $maxWeight = 70; // maximum single box weight for small package in pounds

    function __construct()
    {
        parent::__construct();
        $this->contact_type = clean('cType', ['format'=>'char', 'default'=>'c'], 'post');
        msgDebug("\nendiciaRate with options = " .print_r($this->options, true));
        msgDebug("\nendiciaRate with settings = ".print_r($this->settings, true));
    }

    /**
     * assumes only one package at a time
     * @param type $pkg
     * @return boolean|string|array
     */
    public function rateQuote($pkg)
    {
        if ($pkg['settings']['weight'] == 0)           { msgAdd($this->lang['err_weight_zero']);   return []; }
        if (empty($pkg['destination']['postal_code'])) { msgAdd($this->lang['error_postal_code']); return []; }
        $this->prepShipment($pkg);
        $arrRates= $this->getRates('rateQuote', $this->getPayload($pkg));
        return $arrRates;
    }

    private function getRates($path, $request)
    {
        $arrRates= [];
        $resp    = $this->queryREST($path, $request);
        if (empty($resp)) { return msgAdd('No response from Endicia server! You may not be logged in.'); }
        if (!empty($resp['message'])) { return; }
        if (!empty($resp['errors'])) {
            foreach ($resp['errors'] as $error) { msgAdd("Error! Endicia rate request <br />Code: {$error['error_code']}<br />Message: {$error['error_message']}"); }
            return $arrRates;
        }
        $codes = array_keys($this->options['rateCodes']);
        $pkgs  = array_keys($this->options['PackageMap']);
        msgDebug("\nWorking with codes = ".print_r($codes, true));
        foreach ($resp as $rate) {
            if (!in_array($rate['service_type'], $codes)) { continue; }
            $svcKey = $this->options['rateCodes'][$rate['service_type']];
            $pkgKey = array_search($rate['packaging_type'], $pkgs);
            msgDebug("\nservice key = $svcKey and package key = $pkgKey");
            if (empty($svcKey) || empty($pkgKey)) { continue; }
            $cost   = 0;
            foreach ($rate['shipment_cost']['cost_details'] as $row) { $cost += $row['amount']; }
            if (!empty($rate['estimated_delivery_date'])) {
                $note = 'Commit: '.biz_date("D M j",strtotime($rate['estimated_delivery_date']));
            } else {
                $note = 'Delivery Days: '.$rate['estimated_delivery_days'];
            }
            $arrRates["$svcKey"] = [
                'title'  => $this->lang[$svcKey]." ".$this->options['PackageMap'][$rate['packaging_type']],
                'gl_acct'=> $this->settings['gl_acct_'.$this->contact_type],
                'note'   => $note,
                'cost'   => $cost,
                'quote'  => $cost + $this->settings['handling_fee'],
                'book'   => $cost];
        }
        msgDebug("\nFedEx returned arrRates = ".print_r($arrRates, true));
        return sortOrder($arrRates, 'cost');
    }

    private function getPayload($pkg)
    {
        $payload = [
            'ship_date'   => substr(biz_date('c', strtotime($pkg['settings']['ship_date'])), 0, 19),
            'delivery_confirmation_type'=> 'tracking',
            'from_address'=> $this->mapAddress($pkg['shipper']),
            'to_address'  => $this->mapAddress($pkg['destination']),
            'package'     => [ // 'packaging_type'=>'large_envelope',
                'weight'=>$pkg['settings']['weight'], 'weight_unit'=>'pound',
                'length'=>$pkg['settings']['length'], 'width'=>$pkg['settings']['width'], 'height'=>$pkg['settings']['height'], 'dimension_unit'=>'inch'],
//          'service_type'=> 'usps_first_class_mail',
//          'insurance'     => ['insurance_provider'=>'stamps_com','insured_value'=>['amount'=> 0,'currency'=>'usd']],
/*          'customs'       => [
                'contents_type' => 'gift',
                'contents_description'=> 'string',
                'non_delivery_option'=> 'treat_as_abandoned',
                'sender_info'   => ['license_number'=> 'string','certificate_number'=> 'string','invoice_number'=> 'string','internal_transaction_number'=> 'string','passport_number'=> 'string','passport_issue_date'=> 'string','passport_expiration_date'=> 'string'],
                'recipient_info'=> ['tax_id'=> 'string'],
                'customs_items' => [['item_description'=> 'string','quantity'=> 0,'unit_value'=> ['amount'=> 0,'currency'=> 'usd'],'item_weight'=> 0,'weight_unit'=> 'ounce',
                    'harmonized_tariff_code'=> 'string','country_of_origin'=> 'string','sku'=> 'string']]], */
//          'is_return_label'=> true,
/*          'advanced_options' => ['non_machinable'=> true,'saturday_delivery'=> true,'delivered_duty_paid'=> true,'hold_for_pickup'=> true,'certified_mail'=> true,
                'return_receipt'  => true,'return_receipt_electronic'=> true,'collect_on_delivery'=> ['amount'=> 0,'currency'=> 'usd'],'registered_mail'=> ['amount'=> 0,'currency'=> 'usd'],
                'sunday_delivery' => true,'holiday_delivery'=> true,'restricted_delivery'=> true,'notice_of_non_delivery'=> true,
                'special_handling'=> ['special_contents_type'=> 'hazardous_materials','fragile'=> true],
                'no_label'        => ['is_drop_off'=> true,'is_prepackaged'=> true],'is_pay_on_use'=> true,'return_options'=> ['outbound_label_id'=> 'string']] */
            ];
        msgDebug("\nReturning from buildPayload with request = ".print_r($payload, true));
        return $payload;
    }

// ***************************************************************************************************************
//                                ENDICIA RATE SUPPORT METHODS
// ***************************************************************************************************************
    private function prepShipment(&$pkg)
    {
        msgDebug("\nEntering prepShipment");
        $pkg['packages'] = [];
        $pkg['ship_date']= strtotime($pkg['settings']['ship_date']);
        for ($i=0; $i<$pkg['settings']['num_boxes']; $i++) {
            $box = [
                'weight' => ceil($pkg['settings']['weight']/$pkg['settings']['num_boxes']),
                'length' => ceil($pkg['settings']['length']),
                'width'  => ceil($pkg['settings']['width']),
                'height' => ceil($pkg['settings']['height']),
                'value'  => !empty($pkg['settings']['ins_amount']) ? intval($pkg['settings']['ins_amount']/$pkg['settings']['num_boxes']) : $this->default_insurance_value];
            $pkg['packages'][] = $box;
        }
        $pkg['total_weight']   = $pkg['settings']['weight'];
        $pkg['total_insurance']= $pkg['settings']['ins_amount'];
        $pkg['total_packages'] = sizeof($pkg['packages']);
        msgDebug("\nReturning from prepShipment with packages = ".print_r($pkg['packages'], true));
    }
}
