<?php
/*
 * Shipping extension for Endicia - Address
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
 * @version    7.x Last Update: 2026-03-01
 * @filesource /controllers/shipping/carriers/endicia/address.php
 *
 */

namespace bizuno;

class endiciaAddress extends endiciaCommon
{
    function __construct()
    {
        parent::__construct();
        msgDebug("\nendiciaAddress with options = " .print_r($this->options, true));
        msgDebug("\nendiciaAddress with settings = ".print_r($this->settings, true));
    }

    /**
     *
     * @param type $address
     * @return boolean|string
     */
    public function validateAddress($address=[]) // &$layout=[]
    {
        msgDebug("\nEntering validateAddress with address = ".print_r($address, true));
        $output= ['score'=>'N/A', 'status'=>lang('fail'), 'notes'=>''];
//      if (!in_array($post['country'], ['USA'])) { $output['notes']=lang('err_address_val_country', $this->moduleID); return $output; }
        $resp  = $this->queryREST('addressValidate', $this->payloadREST($address)); // endicia expects: 'addresses/validate',
        msgDebug("\nBack in validateAddress with resp = ".print_r($resp, true));
        if ( empty($resp)) { return msgAdd('No response from Endicia server! You may not be logged in.'); }
        if (!empty($resp['message'])) { return;}
        if (!empty($resp['errors']) || !empty($resp['message'])) {
            $output['notes'] .= "Error! Response from Stamps.com for an address validation:<br />";
            foreach ($resp['errors'] as $error) { $output['notes'] .= "Code: {$error['code']}; Message: {$error['message']}<br />"; }
            return $output;
        }
        $results = array_shift($resp); // pull the first address response off of the list since we only do one at a time
        if ( empty($results['matched_address'])) { return $output; }
        $notes   = "Residential: {$results['matched_address']['residential_indicator']}<br /><br />\n";
        $output['status'] = 'success';
        $output['resi']   = $results['matched_address']['residential_indicator']=='yes' ? 1 : 0;
        $output['address']= [
            'primary_name'=> $results['matched_address']['company_name'],
            'contact'     => isset($address['contact']) ? strtoupper($address['contact']) : '',
            'address1'    => $results['matched_address']['address_line1'],
            'address2'    => $results['matched_address']['address_line2'],
            'city'        => $results['matched_address']['city'],
            'state'       => $results['matched_address']['state_province'],
            'postal_code' => $results['matched_address']['postal_code'],
            'country'     => clean($address['countryCode'], ['format'=>'country','option'=>'ISO3'])];
        if (!empty($results['validation_results'])) {
            $notes .= "<b>Result Code:</b> {$results['validation_results']['result_code']}: {$results['validation_results']['result_description']}<br /><br />\n";
        }
        if (sizeof($results['candidate_addresses'])) {
            $notes .= "<b>Candidate Addresses:</b><br />";
            foreach ($results['candidate_addresses'] as $addr) {
                $notes .= str_replace("\n", "<br />\n", $addr['formatted_address'])."<br /><br />\n";
            }
        }
        $output['notes'] = $notes;
        msgDebug("\nsuccess output = ".print_r($output, true));
        return $output;
    }

    /**
     *
     * @param type $address
     * @return type
     */
    private function payloadREST($address=[])
    {
        $payload = [];
        if (!empty($address['primary_name'])){ $payload['company_name']  = $address['primary_name']; }
        if (!empty($address['contact']))     { $payload['name']          = $address['contact']; }
        if (!empty($address['address1']))    { $payload['address_line1'] = $address['address1']; }
        if (!empty($address['address2']))    { $payload['address_line2'] = $address['address2']; }
//      if (!empty($address['address3']))    { $payload['address_line3'] = $address['address3']; }
        if (!empty($address['city']))        { $payload['city']          = $address['city']; }
        if (!empty($address['state']))       { $payload['state_province']= $address['state']; }
        if (!empty($address['postal_code'])) { $payload['postal_code']   = $address['postal_code']; }
        if (!empty($address['country_code'])){ $payload['country_code']  = $address['country_code']; }
        if (!empty($address['telephone1']))  { $payload['phone']         = $address['telephone1']; }
        if (!empty($address['email']))       { $payload['email']         = $address['email']; }
//      if (!empty($address['NOTUSED']))     { $payload['residential_indicator'] = $address['primary_name']; }
//      if (!empty($address['TBD']))         { $payload['formatted_address'] = $address['']; }
        msgDebug("\nReturning from payloadREST with payload = ".print_r($payload, true));
        return [$payload]; // array of objects, we are only looking at 1 address
    }
}
