<?php
/*
 * @name Bizuno ERP - Fixed Assets Extension
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
 * @version    7.x Last Update: 2025-06-16
 * @filesource /controllers/administrate/functions.php
 */

namespace bizuno;

/**
 * Data processing operation used by PhreeForm handle select field text translations.
 * @param string $value - contains the qty:sku_id encoded integer values
 * @param string $format - the work order processing to perform
 * @return string - processed result, depends on the format requested
 */
function administrateView($value, $format='') {
    switch ($format) {
        case 'faType':
            bizAutoLoad(dirname(__FILE__).'/admin.php', 'proGLAdmin');
            $extRtn = new proGLAdmin();
            return isset($extRtn->lang["fa_type_$value"]) ? $extRtn->lang["fa_type_$value"] : $value;
        case 'faCond':
            if ($value=='n') { return lang('new'); }
            elseif ($value=='u') {
                bizAutoLoad(dirname(__FILE__).'/admin.php', 'proGLAdmin');
                $extRtn = new proGLAdmin();
                return $extRtn->lang['used'];
            }
            else { return $value; }
        case 'storeStock': // @TODO - DEPRECATED - removed after 7/1/2025
            $storeID  = !empty($GLOBALS['bizuno_store_id']) ? $GLOBALS['bizuno_store_id'] : 0;
            $thisStore= dbGetValue(BIZUNO_DB_PREFIX.'inventory_history', 'SUM(remaining) AS remaining', "remaining>0 AND store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisStore) { $thisStore = 0;}
            $thisOwed = dbGetValue(BIZUNO_DB_PREFIX.'journal_cogs_owed', 'SUM(qty) AS qty', "store_id=$storeID AND sku='".addslashes($value)."'", false);
            if (!$thisOwed) { $thisOwed = 0;}
            return $thisStore - $thisOwed; // removed viewFormat(*, 'number')
        default:
    }
    return $value;
}
