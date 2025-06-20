<?php
/*
 * Shipping Extension - Administration, installation and removal methods
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
 * @filesource /controllers/shipping/functions.php
 */

namespace bizuno;

/**
 * Processes a value by format, used in PhreeForm
 * @global array $report - report structure
 * @param mixed $value - value to process
 * @param type $format - what to do with the value
 * @return mixed, returns $value if no formats match otherwise the formatted value
 */
function shippingView($value, $format='')
{
    switch ($format) {
        case 'shipInfo': if (!$value) { return ''; }
            $parts  = explode(':', $value);
            $props  = getMetaMethod('carriers', $parts[0]);
            $title  = !empty($props['acronym']) ? $props['acronym'] : $parts[0];
            if (empty($props['settings']['services'])) { return $title; }
            $srvcs  = $props['settings']['services'];
            foreach ($srvcs as $row) { if ($row['id']==$value) { $service = $row['text']; } }
            return !empty($service) ? $service : $title;
        case 'shipRecon': // determines the reconciliation status of a shipment, yes, partial, no (blank)
            $meta = dbMetaGet($value, 'shipment', 'journal');
            if (empty($meta['packages']['rows'])) { return ''; }
            $recon = 0;
            foreach ($meta['packages']['rows'] as $row) { if (!empty($row['reconciled'])) { $recon++; } }
            if     (sizeof($meta['packages']['rows'])==$recon) { return lang('yes'); }
            elseif (!empty($recon) && $recon<sizeof($meta['packages']['rows'])) { return lang('partial'); }
            else   { return ''; }
        case 'shipReq': // Pulls the shipping preference form the order and parses it to a readable string
            $output = [];
            $result = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'description', "ref_id='$value' AND gl_type='frt'");
            if (empty($result)) { return ''; }
            $parts  = explode(';', $result);
            foreach ($parts as $part) {
                $ele = explode(':', $part);
                switch ($ele[0]) {
                    case 'type': $output[] = "Bill: ".ucfirst($ele[1]).(!empty($ele[2]) ? ucfirst($ele[2]) : '' ); break;
                    case 'resi': $output[] = "Resi: ".(empty($ele[1]) ? 'No' : 'YES'); break;
                    default:     $output[] = ucfirst($ele[0]).": ".(!empty($ele[1]) ? ucfirst($ele[1]) : '');
                }
            }
            return implode(', ', $output);
        case 'shipTrack': // get tracking from journal_main.invoice_num
            $meta = dbMetaGet(0, 'shipment', 'journal', $value);
            if (empty($meta['packages']['rows'])) { return ''; }
            $track = [];
            foreach ($meta['packages']['rows'] as $row) { if (!empty($row['tracking_id'])) { $track[] = $row['tracking_id']; } }
            return lang('tracking_num').' '.implode(', ', $track);
        default:
    }
}

function getCarrierText($encValue='')
{
    msgDebug("\nEntering getCarrierText with value = ".print_r($encValue, true));
    $parts = explode(':', $encValue);
    $props = getMetaMethod('carriers', $parts[0]);
    $title = !empty($props['acronym']) ? $props['acronym'] : $parts[0];
    if (empty($props['settings']['services'])) { return $title; }
    $srvcs = $props['settings']['services'];
    foreach ($srvcs as $row) { if ($row['id']==$encValue) { $service = $row['text']; } }
    return !empty($service) ? $service : $title;
}

/**
 * Wrapper to use specific carrier methods if they exist
 * @param string $carrier - carrier class name
 * @param string $function - method within the carrier class
 * @param mixed $var0 - first parameter passed to the method, specific to the carrier/method used
 * @param mixed $var1 - second parameter passed to the method, specific to the carrier/method used
 * @param mixed $var2 - third parameter passed to the method, specific to the carrier/method used
 * @return boolean function result
 */
function retrieve_carrier_function($carrier, $function, $var0='', $var1='', $var2='')
{
    if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
        $fqcn = "\\bizuno\\$carrier";
        bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn);
        $shipper = new $fqcn();
        if (method_exists($shipper, $function) ) { return $shipper->{$function}($var0, $var1, $var2); }
    }
    msgDebug("\nError trying to retrieve function $function for shipping carrier: $carrier");
}

/**
 * This function builds a list of services for a given carrier enabled by the user through the settings.
 * @param string $services - imploded (:) valid services as selected by the user
 * @param array $lang - Language file to build list
 * @return array - keyed by type list of service titles used to build drop down menus
 */
function viewCarrierServices($method, $services='GND', $lang=[], $options=[])
{
    $choices = [];
    $values = explode(':', $services);
    $title = isset($lang['acronym']) ? $lang['acronym'] : $lang['title'];
    foreach ($values as $value) {
        if (!empty($options) && !in_array($value, $options)) { continue; } // removes obsolete and unused services no longer needed
        $text = isset($lang[$value]) ? $lang[$value] : $value;
        $choices[] = ['id'=>"$method:$value", 'text'=>"$title $text"];
    }
    return $choices;
}
