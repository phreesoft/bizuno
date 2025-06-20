<?php
/*
 * Shipping extension for Endicia - Shipping and Delete Shipment
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
 * @filesource /controllers/shipping/carriers/endicia/ship.php
 */

namespace bizuno;

class endiciaShip extends endiciaCommon
{
    private $endicia_results = [];

    function __construct()
    {
        parent::__construct();
        msgDebug("\nendiciaShip with options = " .print_r($this->options, true));
        msgDebug("\nendiciaShip with settings = ".print_r($this->settings, true));
    }

    public function labelGet($request=[])
    {
        $this->getLabel($request, false);
        return $this->endicia_results;
    }

    /**
     * Retrieves label(s) from FedEx
     * @param array $request
     * @return boolean|array a array of the successful getting the labels or false on fail w/message send through messageStack
     */
    private function getLabel($request=[])
    {
        global $io;
        msgDebug("\nEntering getLabel with request = ".print_r($request, true));
        $payload = $this->getPayload($request);
        msgDebug("\nPayload: ".print_r($payload, true));
        $resp = $this->queryREST('getLabel', $payload);
        if (empty($resp)) { return msgAdd('No response from Endicia server! You may not be logged in.'); }
        if (!empty($resp['message'])) { return;}
        if (!empty($resp['errors'])) {
            foreach ($resp['errors'] as $error) { msgAdd("Error! Endicia label request <br />Code: {$error['error_code']}<br />Message: {$error['error_message']}"); }
            return;
        }
        $this->endicia_results[] = [ // log record
            'ref_id'       => $request['ship_ref_1'],
            'method'       => $this->options['rateCodes'][$resp['service_type']],
            'pkg_type'     => $this->options['PackageMap'][$resp['packaging_type']],
            'ship_date'    => strtotime($request['ship_date']), // will be updated in payload generation to today or later
            'tracking'     => $resp['tracking_number'],
            'book_cost'    => $resp['shipment_cost']['total_amount'],
            'net_cost'     => $resp['shipment_cost']['total_amount'],
            'total_cost'   => $resp['shipment_cost']['total_amount'],
            'delivery_date'=> substr($resp['estimated_delivery_date'], 0, 10),
            'notes'        => $resp['label_id']];
        // label
        if (empty($resp['labels'])) { return msgAdd('Error - No tracking or labels found in return string.', 'trap'); }
        $date    = explode('-', $request['ship_date']);
        $fileName= $resp['tracking_number'].'.lpt';
        $filePath= "data/shipping/labels/$this->code/{$date[0]}/{$date[1]}/{$date[2]}/";
        foreach ($resp['labels'] as $label) {
            if (!$io->fileWrite(base64_decode($label['label_data']), $filePath.$fileName, true)) { return; }
            msgDebug("\nSuccessfully retrieved the $this->code shipping label. Filename # {$filePath}$fileName");
        }
        $this->chkNeedToBuy($resp['account_balance']['amount_available']); // Check account balance
    }

    private function getPayload(&$pkg)
    {
        msgDebug("\nEntering payload with pkg = ".print_r($pkg, true));
        $pkg['ship_date'] = max($pkg['ship_date'], biz_date());
        $methods = array_flip($this->options['rateCodes']);
        $payload = [
            'ship_date'      => $pkg['ship_date'],
//          'is_return_label'=> true,
//          'is_test_label'  => false,
            'from_address'   => $this->mapAddress($pkg['shipper']),
            'return_address' => $this->mapAddress($pkg['shipper']),
            'to_address'     => $this->mapAddress($pkg['destination']),
            'service_type'   => $methods[$pkg['ship_method']], // 'usps_first_class_mail',
            'delivery_confirmation_type' => 'tracking',
//          'insurance'    => ['insurance_provider'=>'stamps_com', 'insured_value'=>['amount'=>0, 'currency'=>'usd']],
/*          'customs'      => [
                'contents_type'=>'gift', 'contents_description'=>'string', 'non_delivery_option'=>'treat_as_abandoned',
                'sender_info' = > ['license_number'=>'string', 'certificate_number'=>'string', 'invoice_number'=>'string',
                    'internal_transaction_number'=>'string', 'passport_number'=>'string', 'passport_issue_date'=>'string', 'passport_expiration_date'=>'string'],
                'recipient_info' => ['tax_id'=>'string'],
                'customs_items' => [[
                    'item_description'=>'string', 'quantity'=>0, 'unit_value'=>['amount'=>0, 'currency'=>'usd'],
                    'item_weight'=>0, 'weight_unit'=>'ounce', 'harmonized_tariff_code'=>'string', 'country_of_origin'=>'string', 'sku'=>'string']]], */
/*          'advanced_options' => [
                'certified_mail'        => true, 'return_receipt'=>true, 'return_receipt_electronic'=>true, 'collect_on_delivery'=>['amount'=>0, 'currency'=>'usd'],
                'registered_mail'       => ['amount'=>0, 'currency'=>'usd'], 'sunday_delivery'=>true, 'holiday_delivery'=>true, 'restricted_delivery'=>true,
                'notice_of_non_delivery'=> true, 'special_handling'         => ['special_contents_type'=>'hazardous_materials','fragile'=>true],
                'no_label'              => ['is_drop_off'=>true, 'is_prepackaged'=>true], 'is_pay_on_use'=>true, 'return_options'=>['outbound_label_id' => 'string'] ], */
            'label_options'=> ['label_size'=>$this->settings['label_thermal'], 'label_format'=>'zpl', 'label_output_type'=>'base64'], // 'url' to go fetch it, 'label_logo_image_id'=>0,
//          'email_label'  => ['email'=>'string', 'email_notes'=>'string', 'bcc_email'=>'string'],
            'references'   => [
                'printed_message1'=> $this->settings['lbl_msg_1'], // Label messages, up to 3 lines
                'printed_message2'=> $this->settings['lbl_msg_2'],
                'printed_message3'=> $this->settings['lbl_msg_3'],
//              'cost_code_id'    => 0,
                'reference1'      => !empty($pkg['ship_ref_1']) ? $pkg['ship_ref_1'] : '',
                'reference2'      => !empty($pkg['ship_ref_2']) ? $pkg['ship_ref_2'] : '',
//              'reference3'      => 'string',
//              'reference4'      => 'string',
            ],
//          'order_details'=> ['order_source'=>'string', 'order_number'=>'string', 'items_ordered'=>[['item_name'=>'string', 'quantity'=>0, 'image_url'=>'string', 'item_options'=>[['attribute'=>'string', 'value'=>'string']]]]],
        ];
        $weight = 16 * clean('weight', 'float', 'post') + clean('weightOz', 'float', 'post');
        $pkgUOM = 'ounce';
        if ($weight >= 16) { // convert to pounds and use that in integer increments
            $weight = ceil($weight / 16);
            $pkgUOM= 'pound';
        }
        $payload['package'] = [
            'packaging_type'=> clean('ship_pkg', 'cmd', 'post'),
            'weight'        => $weight,
            'weight_unit'   => $pkgUOM,
            'length'        => clean('length','float', 'post'),
            'width'         => clean('width', 'float', 'post'),
            'height'        => clean('height','float', 'post'),
            'dimension_unit'=> 'inch'];
        msgDebug("\nReturning from getPayload with payload = ".print_r($payload, true));
        return $payload;
    }

// ***************************************************************************************************************
//                                Endicia Label Void/Refund Request
// ***************************************************************************************************************
    public function labelDelete($trckNum='')
    {
        msgDebug("\nEntering labelDelete with tracking number = ".print_r($trckNum, true));
        $resp = $this->queryREST('deleteLabel', ['tracking'=>$trckNum]);
        if (!empty($resp['errors']) || !empty($resp['error'])) {
            if (!empty($resp['error']))                { msgAdd("Error! Endicia label void <br />Message: {$resp['error_description']}"); }
            foreach ((array)$resp['errors'] as $error) { msgAdd("Error! Endicia label void <br />Code: {$error['error_code']}<br />Message: {$error['error_message']}"); }
            return;
        }
        return true;
    }
}
