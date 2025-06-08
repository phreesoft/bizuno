<?php
/*
 * Shipping extension for United Parcel Service - Tracking
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
 * @filesource /controllers/shipping/carriers/ups/tracking.php
 *
 */

namespace bizuno;

class upsTracking extends upsCommon
{
    function __construct()
    {
        parent::__construct();
    }

    public function trackBulk()
    {
        msgAdd("This method has not been finished!");
    }

    private function trackBulkWSDL()
    {
        return msgAdd("The UPS bulk tracking tool needs to be written by PhreeSoft.");
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
