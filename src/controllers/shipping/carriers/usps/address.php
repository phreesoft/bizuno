<?php
/*
 * Shipping extension for USPS - Address Validation
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
 * @version    7.x Last Update: 2026-05-08
 * @filesource /controllers/shipping/carriers/usps/address.php
 *
 * USPS Addresses v3 — GET /addresses/v3/address
 */

namespace bizuno;

class uspsAddress extends uspsCommon
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * Validates a single address against USPS's standardization service.
     *
     * Bizuno's address validation contract (matched by Endicia's equivalent):
     *   return ['score'=>..., 'status'=>'success'|lang('fail'), 'notes'=>html, 'address'=>[...], 'resi'=>0|1]
     *
     * @param array $address Bizuno address row
     * @return array Validated/standardized address per Bizuno contract
     */
    public function validateAddress($address=[])
    {
        msgDebug("\nUSPS validateAddress with address = ".print_r($address, true));
        $output = ['score'=>'N/A', 'status'=>lang('fail'), 'notes'=>''];

        // USPS only validates US addresses. Anything else falls back to "we
        // can't validate this" so the operator isn't blocked.
        $country = strtoupper($address['country'] ?? 'US');
        if (!in_array($country, ['US', 'USA'])) {
            $output['notes'] = 'USPS address validation only supports U.S. addresses.';
            return $output;
        }
        if (empty($address['address1']) || empty($address['state'])) {
            $output['notes'] = 'USPS validation requires at least street address and state.';
            return $output;
        }

        // /address requires streetAddress + state, plus city or ZIPCode.
        $query = [
            'streetAddress'    => $address['address1'],
            'secondaryAddress' => $address['address2'] ?? '',
            'city'             => $address['city']     ?? '',
            'state'            => substr($address['state'], 0, 2),
            'firm'             => $address['primary_name'] ?? ''];
        if (!empty($address['postal_code'])) {
            $zip = preg_replace('/[^0-9]/', '', $address['postal_code']);
            if (strlen($zip) >= 5) { $query['ZIPCode'] = substr($zip, 0, 5); }
            if (strlen($zip) >= 9) { $query['ZIPPlus4'] = substr($zip, 5, 4); }
        }
        $resp = $this->queryREST('get', '/addresses/v3/address', null, ['query'=>$query]);
        msgDebug("\nUSPS validateAddress response = ".print_r($resp, true));
        if (empty($resp) || empty($resp['address'])) { return $output; } // queryREST already wrote any error

        $std = $resp['address'];
        $info= $resp['additionalInfo'] ?? [];

        // DPV (Delivery Point Validation) confirmation = 'Y' means USPS could
        // confirm the address is deliverable. 'D'/'S' are partial matches; 'N'
        // is a fail. Bizuno's older score field is purely cosmetic, so we
        // surface the DPV confirmation as the score.
        $dpv = $info['DPVConfirmation'] ?? '';
        $output['score']  = $dpv ?: 'N/A';
        $output['status'] = ($dpv === 'Y') ? 'success' : lang('fail');
        // Business indicator is the closest USPS analog to residential_indicator.
        // It's 'Y' for known business addresses; everything else is treated
        // residential. Endicia's contract is residential=1/0, so we invert.
        $business = ($info['business'] ?? '') === 'Y';
        $output['resi'] = $business ? 0 : 1;

        $output['address'] = [
            'primary_name'=> $resp['firm'] ?? ($address['primary_name'] ?? ''),
            'contact'     => isset($address['contact']) ? strtoupper($address['contact']) : '',
            'address1'    => $std['streetAddress']    ?? '',
            'address2'    => $std['secondaryAddress'] ?? '',
            'city'        => $std['city']             ?? '',
            'state'       => $std['state']            ?? '',
            'postal_code' => trim(($std['ZIPCode'] ?? '') . ((!empty($std['ZIPPlus4'])) ? '-'.$std['ZIPPlus4'] : '')),
            'country'     => 'USA'];

        // Build the HTML notes block — useful diagnostic when DPV != Y so the
        // operator can see *why* USPS rejected (multiple matches, missing
        // unit, vacant, etc.). USPS returns these as `corrections` (failures)
        // and `matches` (informational), each an array of {code, text}.
        $notes = "Business: ".($business ? 'Yes' : 'No')."<br />\n";
        if (!empty($dpv)) { $notes .= "DPV Confirmation: ".$dpv."<br />\n"; }
        if (!empty($resp['corrections']) && is_array($resp['corrections'])) {
            $notes .= "<b>Corrections suggested:</b><br />";
            foreach ($resp['corrections'] as $c) {
                $notes .= "Code {$c['code']}: ".($c['text'] ?? '')."<br />\n";
            }
        }
        if (!empty($resp['matches']) && is_array($resp['matches'])) {
            foreach ($resp['matches'] as $m) {
                $notes .= "Match {$m['code']}: ".($m['text'] ?? '')."<br />\n";
            }
        }
        if (!empty($resp['warnings']) && is_array($resp['warnings'])) {
            foreach ($resp['warnings'] as $w) { $notes .= "Warning: $w<br />\n"; }
        }
        $output['notes'] = $notes;
        msgDebug("\nUSPS validateAddress output = ".print_r($output, true));
        return $output;
    }
}
