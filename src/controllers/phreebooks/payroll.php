<?php
/*
 * Methods to handle Payroll methods
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
 * @version    7.x Last Update: 2025-06-18
 * @filesource /controllers/phreebooks/payroll.php
 */

namespace bizuno;

class phreebooksPayroll
{
    function __construct() { }

    public function importForm(&$layout=[])
    {
        msgDebug("\nreached phreebooks:payroll:importForm");
        $vendor= clean('payroll', 'alpha_num', 'get');
        if (empty($vendor)) { return; }
        $biz   = $this->loadVendor($vendor);
        if (method_exists($biz, 'importForm')) { $biz->importForm($layout); }
    }
    
    public function importGo(&$layout=[])
    {
        msgDebug("\nreached phreebooks:payroll:importGo");
        $vendor= clean('modID', 'alpha_num', 'get');
        if (empty($vendor)) { return; }
        $biz = $this->loadVendor($vendor);
        if (method_exists($biz, 'importGo')) { $biz->importGo($layout); }
    }
    
    private function loadVendor($vendor)
    {
        msgDebug("\nEntering loadVendor with vendor = ".print_r($vendor, true));
        if (!is_string($vendor)) { msgAdd("Received bad data, expected a string and got: ".print_r($vendor, true)); return; }
        if (!empty($vendor)) {
            if (!file_exists(dirname(__FILE__)."/payroll/$vendor/$vendor.php")) { return msgAdd("Could not find vendor class, looking for $vendor!"); }
            bizAutoLoad(dirname(__FILE__)."/payroll/$vendor/$vendor.php", $vendor);
            $fqcn    = "\\bizuno\\$vendor";
            msgDebug("\nCreating class $vendor");
            $biz = new $fqcn();
        } else {
            $biz = new \stdClass();
        }
        return $biz;
    }
}