<?php
/*
 * Shipping extension for Federal Express - Tracking
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
 * @filesource /controllers/shipping/carriers/fedex/tracking.php
 *
 */

namespace bizuno;

class fedexTracking extends fedexCommon
{
    function __construct()
    {
        parent::__construct();
    }

    public function trackBulk()
    {
        msgAdd("This method has not been finished!");
    }

    /**
     * This is the WSDL version but has the generic code written
     * @global type $io
     * @return type
     */
    private function trackBulkWSDL()
    {
        return msgAdd("The FedEx bulk tracking tool needs to be converted from the WSDL to the RESTful API.");
        global $io;
        if (empty($this->settings['meter_number'])) { return msgAdd($this->lang['err_no_creds']); }
        $output = ['error'=>[], 'caution'=>[], 'success'=>[]];
        if ($this->settings['test_mode'] != 'prod') { return msgAdd('Tracking only works on the FedEx production server!'); }
        $start_date = clean('datefedExTrack', ['format'=>'date','default'=>false], 'post');
        msgDebug("\nlooking for date = $start_date");
        if (!$start_date) { return msgAdd("Bad or missing date specified!"); }
        $end_date   = localeCalculateDate($start_date, 1);
        $stmt = dbGetResult("SELECT journal_main.id, journal_meta.id, journal_meta.ref_id, primary_name_b, freight, purch_order_id, meta_value
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE invoice_num='$inv_num' AND journal_id IN (12, 13)");
        $shipments = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
//      $metaVals['r'.$jMain['journal_meta.id']]= json_decode($jMain['meta_value'], true);
        $client = new \SoapClient(FEDEX_TRACK_WSDL, ['trace' => 1]);
        foreach ($shipments as $row) {
            msgDebug("\nrow tracking = {$row['tracking_id']} and actual_date = {$row['actual_date']}");
            if (!$row['tracking_id'] || $row['actual_date']) { continue; }// skip if already tracked
            $request = $this->FormatFedExTrackRequest($row['tracking_id']);
            $response = $this->queryWSDL($client, 'track', $request);
            if ($response) {
                if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR') {
                    foreach ((array)$response->CompletedTrackDetails->TrackDetails->DatesOrTimes as $value) {
                        if ($value->Type == 'ACTUAL_DELIVERY') { $actual_date = substr(str_replace('T', ' ', $value->DateOrTimestamp), 0, -6); }
                    }
                    // see if the package was late, flag if so
                    $invoice_num = explode('-',$row['ref_id']);
                    $main = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', ['id','primary_name_b'], "journal_id=12 AND invoice_num='{$invoice_num[0]}'");
                    $row['ref_id'] .= " ({$main['primary_name_b']})";
                    $late = '0'; // no data
                    if ($row['deliver_date'] < $actual_date) {
                        $late = 'L';
                        $frtDesc = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'description', "ref_id={$main['id']} AND gl_type='frt'");
                        if (strpos($frtDesc, 'type:sender') === false) {
                            $row['ref_id'] .= " [ !!! 3rd Party Billing !!! ]";
                            continue; // remove to show late deliveries to third party billing and collect billing
                        }
                        $output['error'][]  = sprintf($this->lang['track_status'], $row['ref_id'], $row['tracking_id'], $actual_date, $row['deliver_date'], $row['cost']);
                    } elseif ($response->CompletedTrackDetails->TrackDetails->StatusDetail->Code <> 'DL') {
                        $late = 'T';
                        $output['caution'][]= sprintf($this->lang['track_in_transit'], $row['ref_id'], $response->CompletedTrackDetails->TrackDetails->StatusDetail->Code, $response->CompletedTrackDetails->TrackDetails->StatusDetail->Description);
                    } else {
                        $output['success'][]= sprintf($this->lang['track_status'], $row['ref_id'], $row['tracking_id'], $actual_date, $row['deliver_date'], $row['cost']);
                    }
// @TODO - Update the actual meta
$valuesToUpdate = ['actual_date' => $actual_date, 'deliver_late' => $late];
//dbMetaSet();
                } else {
                    $message = '';
                    foreach ($response->Notifications as $notification) {
                        $message .= is_object($notification) ? " ($notification->Severity) $notification->Message" : " - $notification";
                    }
                    msgAdd("($this->code) $message", 'caution');
                    $output['error'][] = "($this->code) $message";
                }
            }
        }
        msgLog("Report Date: ".biz_date('Y-m-d').".\n\nFedEx Bulk Tracking for packages shipped on $start_date.\n\n");
        msgDebug("\nReturning with output = ".print_r($output, true));
        $rpt = "FedEx tracking results for shipments on $start_date\n\n";
        $rpt.= "The following LATE shipments were found:\n\n".implode("\n", $output['error'])."\n\n";
        $rpt.= "The following UNDELIVERED shipments were found:\n\n".implode("\n", $output['caution'])."\n\n";
        $rpt.= "The following ON SCHEDULE delivered shipments were found:\n\n".implode("\n", $output['success']);
        $io->download('data', $rpt, "FedExTracking-$start_date.txt");
    }

    private function FormatFedExTrackRequest($tracking_id = '')
    {
        return [
            'WebAuthenticationDetail'=>['UserCredential'=>['Key'=>$this->settings['auth_key'], 'Password'=>$this->settings['auth_pw']]],
            'ClientDetail' => [
                'AccountNumber'=> $this->settings['acct_number'],
                'MeterNumber'  => $this->settings['meter_number'],
                'Localization' => ['LanguageCode'=>'EN', 'LocaleCode'=>'us']],
            'TransactionDetail'=> [
                'CustomerTransactionId' => 'Basic_TrackRequest_q0_Internal',//'*** TC030_WSVC_Track_v4 _POS ***',
                'Localization' => ['LanguageCode'=>'EN', 'LocaleCode'=>'us']],
            'Version'          => ['ServiceId'=>'trck','Major'=>FEDEX_TRACKING_VERSION, 'Intermediate'=>'0', 'Minor'=>'0'],
            'SelectionDetails' => ['PackageIdentifier'=>['Type'=>'TRACKING_NUMBER_OR_DOORTAG','Value'=>$tracking_id]]];
    }
}
