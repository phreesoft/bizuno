<?php
/*
 * WordPress plugin - Bizuno DB Upgrade Script - from any version to this release
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
 * @version    7.x Last Update: 2025-06-04
 * @filesource /controllers/bizuno/install/upgrade.php
 */

namespace bizuno;

if (!defined('BIZUNO_DB_PREFIX')) { exit('Illegal Access!'); }

/**
 * POST R7.0 - Handles the db upgrade for all versions of Bizuno to the current release level
 * @param string $cron - current cron data
 */
function bizunoUpgrade()
{
    $dbVer = getModuleCache('bizuno', 'properties', 'version');
    msgDebug("\nEntering bizunoUpgrade with db version = $dbVer");

    dbTransactionStart();
    if (version_compare($dbVer, '7.1') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'gl_acct_v')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` ADD `gl_acct_c` VARCHAR(15) DEFAULT NULL COMMENT 'type:ledger;tag:DefGLAcctC;order:69' AFTER `gl_account`");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` ADD `gl_acct_v` VARCHAR(15) DEFAULT NULL COMMENT 'type:ledger;tag:DefGLAcctV;order:68' AFTER `gl_account`");
            dbGetResult("UPDATE `"     .BIZUNO_DB_PREFIX."contacts` SET gl_acct_v=gl_account WHERE ctype_v='1'");
            dbGetResult("UPDATE `"     .BIZUNO_DB_PREFIX."contacts` SET gl_acct_c=gl_account WHERE ctype_c='1'");
//            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` DROP `gl_account`;");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'ach_enable')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_enable` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;order:10;tag:ACHEnable' AFTER account_number");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_bank` VARCHAR(32) NULL DEFAULT NULL COMMENT 'order:20;tag:ACHBankName' AFTER ach_enable");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_routing` INT(9) NULL DEFAULT NULL COMMENT 'type:integer;order:30;tag:ACHRouting' AFTER ach_bank");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_account` VARCHAR(16) NULL DEFAULT NULL COMMENT 'type:integer;order:40;tag:ACHAccount' AFTER ach_routing");
        }
        // These config values need to be merged into the new format
        $modMap = ['api'=>'proIF', 'contacts'=>'proCust', 'contacts'=>'proVend', 'inventory'=>'proInv', 'quality'=>'proQA', 'phreebooks'=>'proPayment', 'shipping'=>'proLgstc'];
        foreach ($modMap as $dest => $source) {
            // pull the source and make sure it is present, else continue
            // pull the destination
            // merge the destination onto the source, dest contains the newest path information
            // update the cache and save the record
        }
        
        // These have no useful information and can just be deleted.
/*      dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proEDI'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proGL'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proHR'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='public'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='toolXlate'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrData'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrPhreeBooks'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrQuickBooks'"); */

        // Need to convert nacha contacts to contacts meta
        
    }

    // At every upgrade, run the comments repair tool to fix changes to the view structure and add any new phreeform categories
    require_once(BIZBOOKS_ROOT.'controllers/administrate/tools.php');
    $ctl = new administrateTools();
    $ctl->repairComments(false);

    dbTransactionCommit();
    setModuleCache('bizuno', 'properties', 'version', MODULE_BIZUNO_VERSION); // set newest version
}
