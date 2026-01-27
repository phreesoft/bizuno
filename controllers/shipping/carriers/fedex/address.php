<?php
/*
 * Shipping extension for Federal Express - Address
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
 * @version    7.x Last Update: 2025-07-14
 * @filesource /controllers/shipping/carriers/fedex/address.php
 *
 */

namespace bizuno;

class fedexAddress extends fedexCommon
{
    function __construct()
    {
        parent::__construct();
    }

    public function validateAddress($post=[])
    {
        $output= ['score'=>0, 'status'=>lang('fail'), 'notes'=>''];
        if (empty($this->settings['rest_api_key']) || empty($this->settings['rest_secret'])) { $output['notes']=$this->lang['err_no_creds']; return $output; }
        if (!in_array($post['country'], ['USA']))                                      { $output['notes']=$this->lang['err_address_val_country']; return $output; }
        $resp  = $this->queryREST('address/v1/addresses/resolve', $this->payloadREST($post));
        if (!empty($resp['errors'])) {
            $output['notes'] .= "Error! Response from FedEx for an address validation:<br />";
            foreach ($resp['errors'] as $error) { $output['notes'] .= "Code: {$error['code']}; Message: {$error['message']}<br />"; }
            return $output;
        }
        if (empty ($resp['output']['resolvedAddresses'])) { return; }
        if (sizeof($resp['output']['resolvedAddresses']) > 1) { msgAdd('More than one address was returned! Further validation is necessary', 'caution'); }
        foreach ($resp['output']['resolvedAddresses'] as $addr) {
            if (in_array($addr['classification'], ['BUSINESS', 'RESIDENTIAL'])) {
                $address = [
                    'primary_name'=> strtoupper(clean($post['primary_name'], 'text')),
                    'contact'     => isset($post['contact']) ? strtoupper($post['contact']) : '',
                    'address1'    => $addr['streetLinesToken'][0],
                    'address2'    => isset($addr['streetLinesToken'][1]) ? $addr['streetLinesToken'][1] : (isset($post['address2']) ? strtoupper($post['address2']) : ''),
                    'city'        => $addr['city'],
                    'state'       => $addr['stateOrProvinceCode'],
                    'postal_code' => $addr['postalCode'],
                    'country'     => clean($addr['countryCode'], ['format'=>'country','option'=>'ISO2'])];
                $notes = "Address Found, type => {$addr['classification']}";
                $status = 'success';
            } elseif (in_array($addr['classification'], ['UNKNOWN'])) {
                $address= [];
                $notes  = 'ADDRESS WAS FOUND BUT TYPE COULD NOT BE VERIFIED! Please verify using another tool.';
                $status = 'fail';
            } else {
                $address= [];
                $notes  = 'ADDRESS NOT FOUND!';
                $status = 'fail';
            }
            $output = [
                'status' => $status,
                'score'  => 'N/A',
                'notes'  => $notes,
                'resi'   => $addr['classification']=='RESIDENTIAL' ? 1 : 0,
                'address'=> $address];
            msgDebug("\nsuccess output = ".print_r($output, true));
            break; // just the first for now since we only validate one per iteration
        }
       return $output;
    }

    private function payloadREST($post)
    {
        msgDebug("\nEntering payloadREST with post = ".print_r($post, true));
        $streetLines= [clean($post['address1'], 'text')];
        if (isset($post['address2'])) { $streetLines[] = clean($post['address2'], 'text'); }
//      $pri_name   = isset($post['primary_name']) ? clean($post['primary_name'], 'text') : '';
        $payload    = [
//          'inEffectAsOfTimestamp'=> $timestamp,
//          'validateAddressControlParameters'=>['includeResolutionTokens'=>true],
            'addressesToValidate'=> [[
                'address'=> [
                    'streetLines'        => $streetLines,
                    'city'               => clean($post['city'], 'alpha_num'),
                    'stateOrProvinceCode'=> clean($post['state'], 'text'),
                    'postalCode'         => clean($post['postal_code'], 'text'),
                    'countryCode'        => 'US',
//                  'urbanizationCode'   => 'EXT VISTA BELLA', // only valid for Puerto Rico
//                  'addressVerificationId'=> $pri_name,
                ]]]];
        return $payload;
    }
}
