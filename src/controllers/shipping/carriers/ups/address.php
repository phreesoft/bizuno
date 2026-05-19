<?php
/*
 * Shipping extension for United Parcel Service - Address
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
 * @filesource /controllers/shipping/carriers/ups/address.php
 *
 */

namespace bizuno;

class upsAddress extends upsCommon
{
    function __construct()
    {
        parent::__construct();
    }

    public function validateAddress($post=[])
    {
        if (empty($post)) { $post = $_POST; }
        msgDebug("\nEntering $this->code validateAddress with fields = ".print_r($post, true));
        $output= ['score'=>'N/A', 'status'=>lang('fail'), 'notes'=>''];
        if (empty($this->settings['rest_api_key']) || empty($this->settings['rest_secret'])){ $output['notes']=$this->lang['err_no_creds']; return $output; }
        if ($post['country'] <> 'USA') { return ['score'=>0, 'status'=>lang('fail'), 'notes'=>$this->lang['err_address_val_country']]; }
        // requestoption valid values: 1 - Address Validation 2 - Address Classification 3 - Address Validation and Address Classification
        $restURL = "api/addressvalidation/$this->restVersion/3";
        $request = $this->payload($post);
        $response= $this->queryREST($restURL, json_encode($request));
        if ($response->XAVResponse->Response->ResponseStatus->Code <> 1) {
            $output['notes'] .= "Error! Response from UPS for an address validation:<br />";
            return $output;
        }
        if (empty($response->XAVResponse->Candidate)) {
            $output['notes'] .= "UPS - Sorry, we could not find a valid address. Please try using another tool.";
            return $output;
        }
        $candidate = $response->XAVResponse->Candidate;
        $notes   = "Address Type: {$candidate->AddressClassification->Description}<br /><br />\n";
        $output['status'] = 'success';
        $output['resi']   = $candidate->AddressClassification->Description=='Residential' ? 1 : 0;
        if (is_array($candidate)) { // returned many options
            $address = array_shift($candidate);
            $notes  .= "<p><b><u>Other Candidates:</u></b></p>";
            foreach ($candidate as $row) { $notes .= $this->pullAddress($row, $post); }
        } else {
            $address = $candidate;
        }
        if (!is_array($address->AddressKeyFormat->AddressLine)) { $address->AddressKeyFormat->AddressLine = [$address->AddressKeyFormat->AddressLine]; }
        msgDebug("\nReady to process with address = ".print_r($address, true));
        $output['address']= [
            'primary_name'=> strtoupper($post['primary_name']),
            'contact'     => isset($post['contact']) ? strtoupper($post['contact']) : '',
            'address1'    => $address->AddressKeyFormat->AddressLine[0],
            'address2'    => !empty($address->AddressKeyFormat->AddressLine[1]) ? $address->AddressKeyFormat->AddressLine[1] : '',
            'city'        => $address->AddressKeyFormat->PoliticalDivision2,
            'state'       => $address->AddressKeyFormat->PoliticalDivision1,
            'postal_code' => $address->AddressKeyFormat->PostcodePrimaryLow,
            'country'     => clean($address->AddressKeyFormat->CountryCode, ['format'=>'country','option'=>'ISO3']),
        ];
        $output['notes'] = $notes;
        msgDebug("\nsuccess output = ".print_r($output, true));
        return $output;
    }

    private function pullAddress($address, $post)
    {
        msgDebug("\nEntering pullAddress with address = ".print_r($address, true));
        if (!is_array($address->AddressKeyFormat->AddressLine)) { $address->AddressKeyFormat->AddressLine = [$address->AddressKeyFormat->AddressLine]; }
        $html  = "<p>".strtoupper($post['primary_name'])."<br />";
        if (!empty($post['contact']))         { $html .= strtoupper($post['contact'])."<br />"; }
        $html .= $address->AddressKeyFormat->AddressLine[0]."<br />";
        if (!empty($address->AddressKeyFormat->AddressLine[1])) { $html .= $address->AddressKeyFormat->AddressLine[1]."<br />"; }
        $html .= $address->AddressKeyFormat->PoliticalDivision2.", ".$address->AddressKeyFormat->PoliticalDivision1." ";
        $html .= $address->AddressKeyFormat->PostcodePrimaryLow.'-'.$address->AddressKeyFormat->PostcodeExtendedLow."<br /></p>";
        return $html;
    }

    private function payload($post)
    {
        msgDebug("\nEntering payloadREST with post = ".print_r($post, true));
        $request = [
            'XAVRequest'=> [
                'AddressKeyFormat'=> [
                    'ConsigneeName'      => clean($post['primary_name'],'text'),
                    'BuildingName'       => !empty($post['contact']) ? clean($post['contact'], 'text') : '',
                    'AddressLine'        => [clean($post['address1'],   'text')],
                    'PoliticalDivision2' => clean($post['city'],        'text'),
                    'PoliticalDivision1' => clean($post['state'],       'alpha_num'),
                    'PostcodePrimaryLow' => clean($post['postal_code'], 'integer'),
//                  'PostcodeExtendedLow'=> '1521',
//                  'Urbanization'       => 'porto arundal',
                    'CountryCode'        => 'US',
                    ]]];
        if (!empty($post['address2'])) {
            $request['XAVRequest']['AddressKeyFormat']['AddressLine'][] = clean($post['address2'], 'text');
        }
        return $request;
    }
}
