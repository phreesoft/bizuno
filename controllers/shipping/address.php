<?php
/*
 * Shipping Extension - Address verification methods
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
 * @version    7.x Last Update: 2026-01-12
 * @filesource /controllers/shipping/address.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'shippingCommon');

class shippingAddress extends shippingCommon
{
    public $pageID = 'address';

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function validateAddress(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $ship      = clean(urldecode($_GET['data']), 'json');
        $suffix    = clean('suffix',    ['format'=>'cmd', 'default' =>'_s'],'get');
        $methodCode= clean('methodCode',['format'=>'cmd', 'default;'=>''],  'get');
        if (!$ship || !isset($ship['address1'])) { return msgAdd("Cannot validate address, not enough address information sent!"); }
        $ship['country']= clean('country', ['format'=>'cmd', 'default;'=>'USA'], 'get');
        // first try shipper code validator, else try from registry in sort order
        $carrier   = explode(":", (string)$methodCode)[0];
        $output    = [];
        if (!empty($carrier)){ $output = retrieve_carrier_function($carrier, 'validateAddress', $ship); }
        if ( empty($output)) {
            $failedCarrier = $carrier;
            $carriers = getMetaMethod('carriers');
            foreach ($carriers as $carrier => $settings) {
                if (empty($settings['status']) || $carrier == $failedCarrier) { continue; }
                $output = retrieve_carrier_function($carrier, 'validateAddress', $ship);
                if (!empty($output)) { break; }
            }
        }
        if (empty($output)) { return msgAdd("There are no enabled carriers with address validation. Please enable FedEx, UPS or USPS to get this service.", 'info'); }
        // build response
        $meta = getMetaMethod('carriers', $carrier);
        $js  = '';
        $html= "<p>Carrier Used: ".$meta['title']."</p>";
        if ($output['status'] != 'success') { $html .= '<p style="background-color:red">'."Score: {$output['score']}</p>"; }
        else { $html .= "<p>Score: {$output['score']}</p>"; }
        if ($output['status'] == 'success') {
            if (sizeof($output['address']) > 0) {
                if (!empty($output['resi'])) { $html .= '<p style="background-color:yellow">'.lang('residential_address')."</p>"; }
                $html .= "<p><b><u>Recommended Address</u></b></p>";
                $html .= "<p>".$output['address']['primary_name']."<br />";
                if (isset($output['address']['contact']) && $output['address']['contact']) { $html .= $output['address']['contact']."<br />"; }
                $html .= $output['address']['address1']."<br />";
                if (isset($output['address']['address2']) && $output['address']['address2']) { $html .= $output['address']['address2']."<br />"; }
                $html .= $output['address']['city'].", ".$output['address']['state']." ".$output['address']['postal_code']."<br />";
                $html .= "</p>";
                $html .= "<p>".html5('btnAddrUpdate',['events'=>['onClick'=>"addrValUpdate();"], 'attr'=>['type'=>'button','value'=>lang('update')]])."</p>";
                $js = "function addrValUpdate() {
    bizTextSet('primary_name$suffix', '".addslashes($output['address']['primary_name'])."');
    bizTextSet('contact$suffix', '"     .addslashes($output['address']['contact'])."');
    bizTextSet('address1$suffix', '"    .addslashes($output['address']['address1'])."');
    bizTextSet('address2$suffix', '"    .addslashes($output['address']['address2'])."');
    bizTextSet('city$suffix', '"        .addslashes($output['address']['city'])."');
    bizSelSet('state$suffix', '"        .addslashes($output['address']['state'])."');
    bizTextSet('postal_code$suffix', '" .addslashes($output['address']['postal_code'])."');".
    (!empty($output['resi']) ? "    bizCheckBox('ship_resi');"  : "    bizUncheckBox('ship_resi');"). // order form, next is rate quote popup
    (!empty($output['resi']) ? "    bizCheckBox('residential');": "    bizUncheckBox('residential');")."
    bizWindowClose('window_val');
}";
            }
        }
        $html .= "<p>Notes: {$output['notes']}</p>";
        $data = ['type'=>'popup','title'=>lang('validate_address_results'),'attr'=>['id'=>'window_val','width'=>450,'height'=>600],
            'divs'=>['body'=>['order'=>10,'type'=>'html','html' =>$html]],
            'jsHead'=>['init'=>$js]];
        $layout = array_replace_recursive($layout, $data);
    }
}