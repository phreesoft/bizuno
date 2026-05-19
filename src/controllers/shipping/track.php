<?php
/*
 * Shipping Extension - Tracking methods
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/shipping/track.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'shippingCommon');

class shippingTrack  extends shippingCommon
{
    public $pageID = 'track';

    function __construct()
    {
    }

    /**
     * This method tracks all shipments from a given date and creates a download report
     * @return void|boolean|multitype:multitype:string
     */
    public function trackBulk()
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $carrier = clean('carrier', 'cmd', 'get');
        if (!$carrier) { return msgAdd('The action was not completed, the proper carrier was not passed!'); }
        msgDebug("\n Loading carrier = $carrier");
        $fqcn = "\\bizuno\\$carrier";
        if (bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn)) {
            $shipper = new $fqcn();
            $shipper->trackBulk(); // Should not return from here if successful, file downloaded
        }
    }
}