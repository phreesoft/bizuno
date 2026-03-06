<?php
/*
 * Shipping Extension - Reconciliation methods
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
 * @filesource /controllers/shipping/reconcile.php
 */

namespace bizuno;

class shippingReconcile
{
    public  $moduleID = 'shipping';
    private $secID    = 'shipping';

    function __construct()
    {
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function reconcileInvoice(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return; }
        $carrier = clean('carrier', 'text', 'get');
        if (!$carrier) { return msgAdd('The action was not completed, the proper carrier was not passed!'); }
        msgDebug("\n Loading carrier = $carrier");
        if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
            $fqcn = "\\bizuno\\$carrier";
            bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn);
            $shipper = new $fqcn();
            if (method_exists($shipper, 'reconcileInvoice')) { return $shipper->reconcileInvoice($layout); }
        } else {
            msgAdd("This carrier does not have a reconciliation method!", 'caution');
        }
    }

    /**
     * This method fills the datagrid for reconciliation with file stored in the data/shipping/{carrier} folder
     * @return json
     */
    public function reconcileList(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 3)) { return; }
        $carrier = clean('carrier', 'text', 'get');
        if (!$carrier) { return msgAdd('The action was not completed, the proper carrier was not passed!'); }
        msgDebug("\n Loading carrier = $carrier");
        if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
            $fqcn = "\\bizuno\\$carrier";
            bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn);
            $shipper = new $fqcn();
            if (method_exists($shipper, 'reconcileList')) { $shipper->reconcileList($layout); }
        } else {
            msgAdd("This carrier does not have a reconciliation method!", 'caution');
        }
        msgDebug("\nLayout after carrier call = ".print_r($layout, true));
    }
}