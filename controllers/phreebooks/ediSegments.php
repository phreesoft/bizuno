<?php
/*
 * @name Bizuno ERP - Customer Pro extension - EDI segment processing
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
 * @version    7.x Last Update: 2025-06-11
 * @filesource /controllers/phreebooks/ediSegments.php
 */

namespace bizuno;

class phreebooksEdiSegments
{
    public $moduleID = 'phreebooks';
    public $notes    = '';

    function __construct() { }

    protected function ediHeader($spec='810') {
        $segISA = ['ISA', '01', str_pad('', 10, '0'), '01', str_pad('', 10, '0'), 'ZZ', str_pad($this->rcvrID, 15), '01', str_pad($this->ediID, 15),
                   date('ymd'), date('Hi'), 'U', '00401', str_pad($this->ediCntrlNum, 9, '0', STR_PAD_LEFT), '0', 'P', '>'];
        $this->ediResponse[] = implode($this->sepSec, $segISA);
        $this->ediResponse[] = implode($this->sepSec, ['GS', 'FA', $this->rcvrID, $this->ediID, date('Ymd'), date('Hi'), $this->ediCntrlNum, 'X', '004010']);
        $this->ediTransCtlNum = !empty($this->main['id']) ? $this->main['id'] : (!empty($this->ediLines['ST'][2]) ? $this->ediLines['ST'][2] : '0001');
        $this->ediResponse[] = implode($this->sepSec, ['ST', $spec, $this->ediTransCtlNum]);
    }
    protected function ediFooter() {
        $numLines = sizeof($this->items);
        if (empty($this->ediLines['CTT'])) { $this->ediResponse[] = implode($this->sepSec, ['CTT', $numLines]); } // its a transmit, add CTT segment
        $this->ediResponse[] = implode($this->sepSec, ['SE', sizeof($this->ediResponse)-1, $this->ediTransCtlNum]); // SE*6*0001~
        $this->ediResponse[] = implode($this->sepSec, ['GE', '1', $this->ediCntrlNum]);
        $this->ediResponse[] = implode($this->sepSec, ['IEA','1', str_pad($this->ediCntrlNum, 9, '0', STR_PAD_LEFT)]);
    }
    /*************** EDI Tags - Detail tags **********************/
    protected function AK1() { // Process ACK tag (Line Item Acknowledgement)
        $this->ediResponse[] = implode($this->sepSec, ['AK1', 'PO', $this->ediCntrlNum]);
    }
    protected function AK2() { // Process ACK tag (Line Item Acknowledgement)
        $this->ediResponse[] = implode($this->sepSec, ['AK2', '850', $this->ediTransCtlNum]);
    }
    protected function AK5() { // Process ACK tag (Line Item Acknowledgement)
        $this->ediResponse[] = implode($this->sepSec, ['AK5', sizeof($this->errors) ? 'E' : 'A']);
    }
    protected function AK9() { // Process ACK tag (Line Item Acknowledgement)
        $this->ediResponse[] = implode($this->sepSec, ['AK9', sizeof($this->errors) ? 'E' : 'A', '1', '1', '1']);
    }
    protected function BAK() { // Process BAK tag (Beginning Segment for Ack)
        // Position 2:
        //   AC - Acknowledge – With Detail and Change
        //   AD - Acknowledge – With Detail, No Change
        $this->ediResponse[] = implode($this->sepSec, ['BAK', '00', 'AC', $this->main['purch_order_id'], date('Ymd'), '', '', '', $this->main['invoice_num']]);
    }
    protected function BEG() { // 850, Process BEG tag (Beginning Segment for Purchase Order)
        $values = $this->ediLines['BEG'];
        if (sizeof($values) < 6) { $this->errors[] = "Error, tag BEG is too short, received: ".print_r($values, true); }
        if ($values[1]<>'00')    { $this->errors[] = "Error in tag BEG position 1, expecting 00 but received: {$values[1]}"; }
        switch ($values[2]) {
            case 'SA': // Stand alone
            case 'DS': // Drop ship
            case 'RO': // Rush Order
                break; // Nothing to do, treat them all the same, just orders
            default: $this->errors[] = "Error in tag BEG position 2, received: {$values[2]}";
        }
        if (!empty($values[3])) { $this->main['purch_order_id'] = $values[3]; }
            else { $this->errors[] = "Error in tag BEG position 3, expected PO # received nothing."; }
        // $values[4] Release Number - not used
        if (!empty($values[5])) { $this->main['post_date'] = substr($values[5],0,4).'-'.substr($values[5],4,2).'-'.substr($values[5],6,2); }
            else { $this->errors[] = "Error in tag BEG position 5, expected PO date received nothing."; }
    }
    protected function BIG() { // Process BIG tag (Beginning Segment for Invoice)
        $this->ediResponse[] = implode($this->sepSec, ['BIG', date('Ymd'), $this->main['invoice_num'], date('Ymd'), $this->main['purch_order_id']]);
    }
    protected function BSN() { // Process BSN tag (Beginning Segment for Ship Notice)
        $this->ediResponse[] = implode($this->sepSec, ['BSN', '00', $this->main['invoice_num'], date('Ymd'), date('His')]);
    }
    protected function DTM() { // Process DTM tag (Date/Time Reference)
    }
    protected function FOB() { // Process FOB tag (F.O.B. Related Instructions)
        $values = $this->ediLines['FOB'];
        if (sizeof($values) < 4) { $this->errors[] = "Error, tag FOB is too short, received: ".print_r($values, true); }
        switch ($values[1]) {
            case 'BP': // Paid by Buyer
                break; // Nothing to do, treat them all the same, just orders
            default: $this->errors[] = "Error in tag FOB position 1, expected BP received: {$values[1]}";
        }
        switch ($values[2]) {
            case 'ZZ': // Mutually Defined
                break; // Nothing to do, treat them all the same, just orders
            default: $this->errors[] = "Error in tag FOB position 2, expected ZZ received: {$values[2]}";
        }
        if (!empty($values[3])) { $this->notes .= "Ship account: ".$values[3]."<br />"; } // Free form field
    }
    protected function HL($pos3, $pos4, $segments=[]) {
        $this->ediResponse[] = implode($this->sepSec, ['HL', $this->cntHL, $pos3, $pos4]);
        foreach ($segments as $seg) {
            if (strpos($seg, ':')) {
                $segPart = substr($seg, 0, strpos($seg, ':'));
                $varPart = substr($seg, strpos($seg, ':')+1);
                $this->$segPart($varPart);
            } else {
                $this->$seg();
            }
        }
        $this->cntHL++;
    }
    protected function IT1() { // Process IT1 tag (Baseline Item Data (Invoice))
        $notes = json_decode($this->main['notes'], true);
        msgDebug("\nRead SKU details from order notes after decoding: ".print_r($notes, true));
        foreach ($this->items as $idx => $item) {
            $sku    = !empty($notes[$idx]['I']) ? $notes[$idx]['I'] : 'VC'.$this->sepSec.$item['sku']; // use vendor part number
            $output = ['IT1', $item['item_cnt'], $item['qty'], 'EA', number_format($item['credit_amount']/$item['qty'], 2), '', $sku];
            $this->ediResponse[] = implode($this->sepSec, $output);
        }
    }
    protected function ITD() { // Process ITD tag (Terms of Sale/Deferred Terms of Sale)
        $dueDate = str_replace('-', '', localeCalculateDate($this->main['post_date'], 45));
        $this->ediResponse[] = implode($this->sepSec, ['ITD', '', '3', '', '', '', $dueDate]);
    }
    protected function LIN($idx, $cntLine, $notes) { // Process LIN tag (Item Identification)
        $this->ediResponse[] = implode($this->sepSec, ['LIN', $cntLine, $notes[$idx]['I']]);
    }
    protected function MAN() { // Process MAN tag (Marks and Numbers)
        $this->ediResponse[] = implode($this->sepSec, ['MAN', 'GM', 'N/A']);
    }
    protected function N1($address=[]) { // Process N1 tag (Name)
        if (!in_array($address[0][1], ['BY','ST'])) { return; } // just need the billing and shipping, skip PO billing address
        foreach ($address as $values) {
            switch ($values[0]) {
                case 'N1': // 850 - N1|DA|MEDICAL EQUIPMENT DYNAMICS
                    switch ($values[1]) {
                        case 'ST': $this->mainSuffix = 's'; break; // Delivery Address
                        case 'BY': $this->mainSuffix = 'b'; break; // ??? - probably Bill To
                    }
                    $this->main['primary_name_'.$this->mainSuffix] = $values[2];
                    break;
                case 'N2': // 850 N1|DA - N2|BENITO
                    if (!empty($values[1])) { $this->main['contact_'.$this->mainSuffix] = $values[1]; }
                    break;
                case 'N3': // 850 N1|DA - N3|3401 SW 132ND AVE
                    if (!empty($values[1])) { $this->main['address1_'.$this->mainSuffix] = $values[1]; }
                    break;
                case 'N4': // 850 N1|DA - N4|MIAMI|FL|33175
                    if (!empty($values[1])) { $this->main['city_'       .$this->mainSuffix] = $values[1]; }
                        else { $this->errors[] = "Error in tag N4 position 1, expected city received nothing."; }
                    if (!empty($values[2])) { $this->main['state_'      .$this->mainSuffix] = $values[2]; }
                        else { $this->errors[] = "Error in tag N4 position 2, expected state received nothing."; }
                    if (!empty($values[3])) { $this->main['postal_code_'.$this->mainSuffix] = $values[3]; }
                        else { $this->errors[] = "Error in tag N4 position 3, expected postal code received nothing."; }
                    $this->main['country_'.$this->mainSuffix] = preg_match("/[a-z]/i", $this->main['postal_code_'.$this->mainSuffix]) ? 'CAN' : 'USA';
                    break;
            }
        }
    }
    protected function N1x($code) { // Address info for responses
        switch ($code) { // Process N1 response segment
            case 'ST': $this->ediResponse[] = implode($this->sepSec, ['N1', $code, $this->main['primary_name_s']]); break; // removed , '92', 'TBD' from end until known
            case 'BT': $this->ediResponse[] = implode($this->sepSec, ['N1', $code, $this->main['primary_name_b']]); break;
            case 'RE':
            case 'SF': $this->ediResponse[] = implode($this->sepSec, ['N1', $code, getModuleCache('bizuno','settings','company','primary_name')]); break;
        }
        switch ($code) { // Process N2 response segment
            case 'ST':
                if (!empty($this->main['contact_s'])) {
                    $this->ediResponse[] = implode($this->sepSec, ['N2', $this->main['contact_s']]); // removed , '92', 'TBD' from end until known
                }
                break;
        }
        switch ($code) { // Process N3 response segment
            case 'ST': $this->ediResponse[] = implode($this->sepSec, ['N3', $this->main['address1_s'], $this->main['address2_s']]); break;
            case 'BT':
                if (!empty($this->main['contact_b'])) {
                    $this->ediResponse[] = implode($this->sepSec, ['N3', $this->main['contact_b'], $this->main['address1_b']]);
                } else {
                    $this->ediResponse[] = implode($this->sepSec, ['N3', $this->main['address1_b']]);
                }
                break;
            case 'RE':
            case 'SF': $this->ediResponse[] = implode($this->sepSec, ['N3', getModuleCache('bizuno','settings','company','address1')]); break;
        }
        switch ($code) { // Process N4 response segment
            case 'ST': $this->ediResponse[] = implode($this->sepSec, ['N4', $this->main['city_s'], $this->main['state_s'], $this->main['postal_code_s']]);  break;
            case 'BT': $this->ediResponse[] = implode($this->sepSec, ['N4', $this->main['city_b'], $this->main['state_b'], $this->main['postal_code_b']]);  break;
            case 'RE':
            case 'SF': $this->ediResponse[] = implode($this->sepSec, ['N4', getModuleCache('bizuno','settings','company','city'),
                getModuleCache('bizuno','settings','company','state'), getModuleCache('bizuno','settings','company','postal_code')]); break;
        }
    }
    protected function NTE() { // Process NTE tag (Note/Special Instruction)
    }
    protected function PER() {
        $this->main['telephone1_b']= $this->main['telephone1_s']= $this->ediLines['PER'][6];
        $this->main['email_b']     = $this->main['email_s']     = $this->ediLines['PER'][4];
    }
    protected function PID() { } // Nothing to do here, processed with PO1
    protected function PO1() { // Process PO1 tag (Baseline Item Data) - receive
        if (empty($this->ediLines['PO1'])) { // transmit - 855, PO1|1|1|EA|154.43||VN|AS30295
            $cntItem = 1;
            msgDebug("\nTrying to JSON decode notes: ".print_r($this->main['notes'], true));
            $notes   = json_decode($this->main['notes'], true);
            if (empty($notes)) { // JSON error - probably because someone added notes and broke the json string, try to recover
                // Assume only one level array, as users are entering <cr> which was messing up the end of the string, was using strrpos
                $bos = strpos($this->main['notes'], "[");
                $eos = strpos($this->main['notes'], "]");
                $trimmed = substr($this->main['notes'], $bos, ($eos-$bos)+2);
                msgDebug("\nError trying to decode notes. Attempting to remove added text and try again BOS = $bos and EOS = $eos. Trimmed = ".print_r($trimmed, true));
                $notes = json_decode($trimmed, true);
                if (empty($notes)) { msgDebug("\nFailed after trying to recover, notes", 'trap'); }
            }
            msgDebug("\nRead SKU details from order notes after decoding: ".print_r($notes, true));
            foreach ($this->items as $idx => $item) {
                if (empty($item['sku'])) { continue; } // probably just a description line
                $ack01 = 'IA'; // Item Accepted
                $sku   = $this->getSKU($item['sku'], $item['qty']); // $values[9], $values[11] also have sku
                // verify price, adjust if < 1%, error if discrepency
                $poPrice   = number_format($item['credit_amount']/$item['qty'], 2);
                $priceDiff = abs(floatval($item['credit_amount']/$item['qty']) - floatval($sku['price']));
                msgDebug("\nPrice difference of $priceDiff needs to be less than 0.5% of our price => ".floatval($sku['price'])*0.005);
                if ($priceDiff > floatval($sku['price'])*0.005) {
                    $ack01 = 'IR'; // Item Rejected, IE - Item accepted - Price Pending, OR IP - Item  accepted, Price Changed
                    $poPrice = number_format($sku['price'], 2);
                    msgAdd("Error, price mismatch on SKU: {$sku['sku']}, received ".(number_format($item['credit_amount']/$item['qty'], 2)).", our price {$sku['price']}. Vendor needs to update the PO and re-submit!");
                } elseif ($sku['qty_stock'] < $item['qty']) { // check for backorders
                    $ack01 = 'IB'; // IB - Item Backordered, IH - item on Hold
                    msgAdd("Not enough of sku {$item['sku']} to fill this PO, Backorder status has been returned to vendor.");
                }
                if (empty($notes[$idx]['I'])) {  }
                $ackDate = !empty($this->main['terminal_date']) ? substr($this->main['terminal_date'], 0, 10) : substr($this->main['post_date'], 0, 10);
                $this->ediResponse[] = implode($this->sepSec, ['PO1', $cntItem, $item['qty'], 'EA', $poPrice, '', !empty($notes[$idx]['I']) ? $notes[$idx]['I'] : '']);
                $this->ediResponse[] = implode($this->sepSec, ['PID', 'F', '', '', '', substr($item['description'], 0, 75)]);
                $this->ediResponse[] = implode($this->sepSec, ['ACK', $ack01, $item['qty'], 'EA', '068', str_replace('-', '', $ackDate)]);
                $cntItem++;
            }
        } else {
            foreach ($this->ediLines['PO1'] as $idx => $values) {
                if (sizeof($values) < 12) { $this->errors[] = "Error, tag PO1 is too short, received: ".print_r($values, true); }
                $sku = $this->getSKU($values[7]);
                if (empty($sku['price'])) { $sku['price'] = 0; }
                // Build description
                $this->main['notes'][] = ['I'=>implode($this->sepSec, ['VC', $values['7'], 'CB', $values['9'], 'BP', $values['11']]), 'D'=>!empty($this->ediLines['PID'][5])?$this->ediLines['PID'][5]:''];
                // Validate price
                msgDebug("\nChecking price difference of received price : {$values[4]} to contact price: {$sku['price']}");
                if (floatval($values[4]) > floatval($sku['price'])) {
                    msgAdd("Price for SKU: {$values[7]} was adjusted, we received {$values[4]} and our price is {$sku['price']}", 'caution');
                    $values[4] = $sku['price'];
                }
                $this->items[] = ['sku'=>$sku['sku'], 'qty'=>$values[2], 'gl_type'=>'itm', 'gl_account'=>$this->contact['gl_account'], 'post_date'=>$this->main['post_date'],
                    'description'  => $sku['description_sales'],
                    'credit_amount'=> intval($values[2]) * floatval($values[4]),
                    'full_price'   => $sku['full_price']];
                // Process the PID associated with this PO1 segment
                $defInv = getModuleCache('inventory', 'settings', 'phreebooks', 'inv_ns');
                if (!empty($this->ediLines['PID'][$idx][5])) {
                    $this->items[] = ['sku'=>'','qty'=>1, 'gl_type'=>'itm', 'gl_account'=>$defInv, 'post_date'=>$this->main['post_date'],
                        'description'=> 'Vendor Description: '.$this->ediLines['PID'][$idx][5]];
                }
                $this->orderTotal += intval($values[2]) * floatval($values[4]);
            }
        }
    }
    protected function REF($idx=0) { // Process REF tag (Reference Identification)
        if (empty($this->ediLines['REF'])) {
            if (empty($this->package[$idx]['tracking'])) { $this->errors[] = "Error! the tracking number is invalid or missing!"; }
            $this->ediResponse[] = implode($this->sepSec, ['REF', '2I', $this->package[$idx]['tracking']]);
        } else {
            $values = $this->ediLines['REF'];
            if (sizeof($values) < 3) { $this->errors[] = "Error, tag REF is too short, received: ".print_r($values, true); }
            switch ($values[1]) {
                case 'OQ': // Order number
                case 'ZZ': // ??? - from sample (maybe Mutually Defined)
                    break; // Nothing to do, treat them all the same, just orders
                default: $this->errors[] = "Error in tag REF position 1, expected ZZ received: {$values[1]}";
            }
            // $values[2] Reference Identification - not used
        }
    }
    protected function PRF() { // Process PRF tag (Purchase Order Reference)
        $this->ediResponse[] = implode($this->sepSec, ['PRF', $this->main['purch_order_id'], '', '', str_replace('-', '', $this->main['post_date'])]);
    }
    protected function SAC() { // Process SAC tag (Service, Promotion, Allowance, or Charge Information)
        $this->ediResponse[] = implode($this->sepSec, ['SAC', 'C', 'D240', '', '',  number_format($this->main['freight'], 2, '', '')]);
    }
    protected function SN1($item) { // Process SN1 tag (Item Detail (Shipment))
        $this->ediResponse[] = implode($this->sepSec, ['SN1', '', $item['qty'], 'EA']);
    }
    protected function TD1() { // Process TD1 tag (Carrier Details (Quantity and Weight)), FAKE AS SINGLE BOX UNTIL A SAMPLE IS PROVIDED
        $weight = 0;
        foreach ($this->package as $pkg) { $weight += $pkg['weight']; }
        $this->ediResponse[] = implode($this->sepSec, ['TD1', '', sizeof($this->package), '', strtoupper($this->commodity), '', 'A3', $weight, 'BX']);
    }
    protected function TD5() { // Process TD5 tag (Carrier Details (Routing Sequence/Transit Time))
        if (empty($this->ediLines['TD5'])) { // response
            $method = explode(':', $this->main['method_code']);
            $this->ediResponse[] = implode($this->sepSec, ['TD5', '', '2', !empty($method[0]) ? strtoupper($method[0]) : 'N/A', '', '', 'SQ']);
        } else {
            $values = $this->ediLines['TD5'];
            if (sizeof($values) < 4) { $this->errors[] = "Error, tag TD5 is too short, received: ".print_r($values, true); }
            // $values[1] Not used
            if (!in_array($values[2], ['1','2'])) { $this->errors[] = "Error in tag TD5 position 2, expected 2 received: {$values[2]}"; }
            $this->main['method_code'] = $this->mapShipping($values[3]);
            // section 4 and 5 are not used as they are typically a spelled out version of section 3
//          if ($values[4] <> 'M') { $this->errors[] = "Error in tag TD5 position 4, expected M received: {$values[4]}"; }
//          if ($values[5] <> $values[3]) { $this->errors[] = "Error in tag TD5 position 5, expected: {$values[3]} to be equal to: {$values[5]}"; }
        }
    }
    protected function TDS() { // Process TDS tag (Total Monetary Value Summary)
        $this->ediResponse[] = implode($this->sepSec, ['TDS', number_format($this->main['total_amount'], 2, '', '')]);
    }
    private function mapShipping($value='') {
        switch ($value) { // Identification Code
            case 'COUNTER':
            case 'FEDEX HOLD':
            case 'FEDEX SATURDAY':
            case 'UPS STANDARD':
            case 'UPS GROUND':
            case 'UPS SATURDAY':
            case 'FEDEX GROUND':                return 'fedex:GND';
            case 'FEDEX HOME DELIVERY':         return 'fedex:GDR';
            case 'UPS RED 8:30':
            case 'FEDEX P1 8:30':
            case 'FEDEX FIRST OVERNIGHT':       return 'fedex:1DM';
            case 'UPS RED 10:30':
            case 'FEDEX P1 10:30':
            case 'FEDEX P1 10:30':
            case 'FEDEX PRIORITY OVERNIGHT':    return 'fedex:1DA';
            case 'UPS RED 4:30':
            case 'FEDEX P1 HOLD':
            case 'FEDEX P1 4:30':
            case 'FEDEX STANDARD OVERNIGHT':    return 'fedex:1DP';
            case 'UPS 2ND DAY 4:30':
            case 'FEDEX 2ND DY 4:30':
            case 'FEDEX 2 DAY':                 return 'fedex:2DP';
            case 'UPS 2ND DAY 10:30':
            case 'FEDEX 2ND DY 10:30':
            case 'FEDEX 2 DAY AM':              return 'fedex:2DA';
            case 'FEDEX GROUND ECONOMY':        return 'fedex:3DA';
            case 'UPS 3RD DAY':
            case 'FEDEX 3RD DAY':
            case 'FEDEX EXPRESS SAVER':         return 'fedex:3DP';
            case 'UPS CAN ND EOD':
            case 'UPS WORLD EXP PLUS':
            case 'FEDEX CAN ND EOD':
            case 'FEDEX INTL 1ST':
            case 'FEDEX INTERNATIONAL FIRST':   return 'fedex:I1D';
            case 'UPS WORLD EXP':
            case 'UPS WORLD EXPD':
            case 'UPS CAN 2ND DAY':
            case 'FEDEX CAN 2ND DAY':
            case 'FEDEX INTL P1':
            case 'FEDEX INTERNATIONAL PRIORITY':return 'fedex:I2D';
            case 'INTL STANDARD':
            case 'FEDEX INTL ECON':
            case 'UPS INTL SAVER':
            case 'FEDEX INTL SAVER':
            case 'UPS CAN NDN':
            case 'UPS CAN GRD':
            case 'FEDEX CAN NDN':
            case 'FEDEX CAN GRD':
            case 'FEDEX INTERNATIONAL ECONOMY': return 'fedex:IGD';
            case 'FEDEX 1DAY FREIGHT':          return 'fedex:1DF';
            case 'FEDEX 2DAY FREIGHT':          return 'fedex:2DF';
            case 'FEDEX 3DAY FREIGHT':          return 'fedex:3DF';
            case 'UPS FREIGHT':
            case 'FEDEX FREIGHT':
            case 'FEDEX INTL P FRT':
            case 'FEDEX FREIGHT PRIORITY':      return 'fedex:GDF';
            case 'FEDEX INTL E FRT':
            case 'FEDEX FREIGHT ECONOMY':       return 'fedex:ECF';
            case 'CUST PICKUP':                 return 'freeshipper:GDR';
            default: $this->errors[] = "Error in tag TD5 position 3, undefined shipping method: $value";
        }
        return '';
    }
}