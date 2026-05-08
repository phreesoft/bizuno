<?php
/*
 * Shipping extension for USPS - Rate quote
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
 * @filesource /controllers/shipping/carriers/usps/rate.php
 *
 * USPS Domestic Prices v3 — POST /prices/v3/base-rates/search
 */

namespace bizuno;

class uspsRate extends uspsCommon
{
    private $maxWeight = 70; // USPS hard cap (pounds)

    function __construct()
    {
        parent::__construct();
        $this->contact_type = clean('cType', ['format'=>'char', 'default'=>'c'], 'post');
    }

    /**
     * Rate quote for a single package across all configured services.
     *
     * USPS's /base-rates/search returns one rate per (mailClass, rateIndicator,
     * processingCategory) tuple — there's no "give me everything for this
     * package" endpoint. We loop the operator's enabled services and the
     * relevant package types, and roll up the responses.
     *
     * @param array $pkg shipping module package envelope (shipper, destination, settings)
     * @return array Bizuno rate format keyed by carrier code
     */
    public function rateQuote($pkg)
    {
        if ($pkg['settings']['weight'] == 0)            { msgAdd($this->lang['err_postal_weight_zero']); return []; }
        if ($pkg['settings']['weight'] > $this->maxWeight) { msgAdd($this->lang['err_pkg_too_heavy']);   return []; }
        if (empty($pkg['destination']['postal_code']))  { msgAdd('Destination postal code is required for USPS rate.'); return []; }

        // Operator can multi-select services on the carrier settings page
        // (service_types is colon-delimited). Iterate them rather than
        // hammering all 5 mailClasses every quote — also lets the customer
        // disable services that don't apply to their use case.
        $bizCodes = array_filter(explode(':', (string)$this->settings['service_types']));
        if (empty($bizCodes)) { $bizCodes = array_values($this->options['rateCodes']); }
        $arrRates = [];
        foreach ($bizCodes as $bizCode) {
            if (empty($this->options['mailClass'][$bizCode])) { continue; }
            $rate = $this->getRate($pkg, $bizCode);
            if (!empty($rate)) { $arrRates[$bizCode] = $rate; }
        }
        return sortOrder($arrRates, 'cost');
    }

    /**
     * Fetches a single base rate for one carrier code (1DP/2DP/GND/3DP/4DP).
     * Returns the Bizuno rate-shape entry, or [] if USPS errored / had no
     * rate for this combination.
     */
    private function getRate($pkg, $bizCode)
    {
        $mailClass = $this->options['mailClass'][$bizCode];
        $payload = $this->getPayload($pkg, $mailClass);
        $resp = $this->queryREST('post', '/prices/v3/base-rates/search', $payload);
        if (empty($resp) || empty($resp['rates'])) { return []; }

        // /base-rates/search response shape: {totalBasePrice, rates:[{price, weight, mailClass, productName, fees:[...], ...}]}
        // We want the cheapest single rate and surface the productName as the
        // line description. Fees are summed with the base price into the
        // "cost" we book against; the "quote" adds the operator's handling fee.
        $cheapest = null;
        foreach ($resp['rates'] as $r) {
            if ($cheapest === null || (float)$r['price'] < (float)$cheapest['price']) { $cheapest = $r; }
        }
        if (empty($cheapest)) { return []; }
        $cost = (float)$cheapest['price'];
        if (!empty($cheapest['fees']) && is_array($cheapest['fees'])) {
            foreach ($cheapest['fees'] as $fee) { $cost += (float)($fee['price'] ?? 0); }
        }
        $note = !empty($cheapest['productDefinition']) ? $cheapest['productDefinition'] : $mailClass;
        return [
            'title'  => $this->lang[$bizCode] . (!empty($cheapest['productName']) ? ' — '.$cheapest['productName'] : ''),
            'gl_acct'=> $this->settings['gl_acct'] ?? '',
            'note'   => $note,
            'cost'   => $cost,
            'quote'  => $cost + (float)$this->settings['handling_fee'],
            'book'   => $cost];
    }

    /**
     * Builds the BaseRatesQuery payload. USPS's required fields are exhaustive
     * (every dimension + processing category + rateIndicator) but we infer
     * sensible defaults for typical parcels:
     *   - rateIndicator SP (Single Piece) for non-flat-rate package types
     *   - destinationEntryFacilityType NONE (sender drops at any post office)
     *   - processingCategory MACHINABLE (the common case for parcels)
     */
    private function getPayload($pkg, $mailClass)
    {
        $shipPkg = clean('ship_pkg', ['format'=>'cmd','default'=>'package'], 'post');
        if (empty($shipPkg) && !empty($pkg['settings']['ship_pkg'])) { $shipPkg = $pkg['settings']['ship_pkg']; }
        $rateIndicator = $this->options['rateIndicator'][$shipPkg] ?? 'SP';

        // USPS takes weight in *pounds* (decimal allowed) for the prices API.
        // Bizuno's package envelope can carry weight in lb already; just cast.
        $weight = (float)$pkg['settings']['weight'];
        if ($weight <= 0) { $weight = 0.1; } // minimum the API allows

        $payload = [
            'originZIPCode'      => $this->extractZip($pkg['shipper']['postal_code']     ?? ''),
            'destinationZIPCode' => $this->extractZip($pkg['destination']['postal_code'] ?? ''),
            'weight'             => $weight,
            'length'             => (float)($pkg['settings']['length'] ?? 0),
            'width'              => (float)($pkg['settings']['width']  ?? 0),
            'height'             => (float)($pkg['settings']['height'] ?? 0),
            'mailClass'          => $mailClass,
            'processingCategory' => $this->options['processingCategory'],
            'rateIndicator'      => $rateIndicator,
            'destinationEntryFacilityType' => 'NONE',
            'priceType'          => $this->settings['price_type'] ?? 'RETAIL',
            'mailingDate'        => substr(biz_date('Y-m-d', strtotime($pkg['settings']['ship_date'] ?? 'now')), 0, 10)];

        // USPS also accepts an EPS account on rate queries so contracted
        // (negotiated) rates resolve correctly. Only included when the
        // operator has set one up — RETAIL queries don't need it.
        if (!empty($this->settings['payment_account']) && in_array($payload['priceType'], ['COMMERCIAL','CONTRACT'])) {
            $payload['accountType']   = 'EPS';
            $payload['accountNumber'] = $this->settings['payment_account'];
        }
        msgDebug("\nUSPS rate payload: ".print_r($payload, true));
        return $payload;
    }

    /**
     * Strips any non-digit and returns the first 5 digits — covers ZIP+4
     * inputs and stray hyphens. USPS's pattern is strict (^\d{5}(?:[-\s]\d{4})?$)
     * but we send only the 5 to keep zone calculations consistent.
     */
    private function extractZip($zip)
    {
        $z = preg_replace('/[^0-9]/', '', (string)$zip);
        return substr($z, 0, 5);
    }
}
