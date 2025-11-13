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
 * @version    7.x Last Update: 2025-11-12
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
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_bank` VARCHAR(32) NULL DEFAULT NULL COMMENT 'order:12;tag:ACHBankName' AFTER ach_enable");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_routing` INT(9) NULL DEFAULT NULL COMMENT 'type:integer;order:14;tag:ACHRouting' AFTER ach_bank");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts ADD `ach_account` VARCHAR(16) NULL DEFAULT NULL COMMENT 'type:integer;order:16;tag:ACHAccount' AFTER ach_routing");
        }
        // These config values need to be merged into the new format
        $modMap = ['api', 'contacts', 'quality', 'phreebooks', 'shipping'];
        foreach ($modMap as $dest) {
            switch ($dest) {
                case 'api': // merge the proIF settings
                    break;
                case 'contacts': // merge the proCust settings
                    $nexus = getModuleCache('proCust', 'settings', 'nexusSt');
                    if (!empty($nexus)){ dbMetaSet(0, 'nexus',$nexus); }
                    $edi   = getModuleCache('proCust', 'edi');
                    if (!empty($edi))  { dbMetaSet(0, 'edi',  $edi); }
                    break;
                case 'quality': setModuleCache('quality', 'settings', 'manual', getModuleCache('proQA', 'settings', 'manual')); break;
                case 'phreebooks':
                    $banks = getModuleCache('proPayment', 'banks');
                    if (!empty($banks)){ dbMetaSet(0, 'ach_banks', $banks); }
                    break;
                case 'shipping': // merge the proLgstc settings
                    break;
            }
        }
    }

    if (version_compare($dbVer, '7.2') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'marketplace')) { // Add marketplace checkbox for tax remittance calculations
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` ADD `marketplace` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:selNoYes;tag:Marketplace;order:20' AFTER `ach_account`");
        }
        // Remove duplicate WO's
        $allBlds = dbMetaGet('%', 'production_job', 'inventory', '%');
        msgDebug("\nLooking at all builds with count = ".sizeof($allBlds));
        $skus = $delIDs = [];
        foreach ($allBlds as $build) { // override rID, i.e. only allow one production job per sku
            if (!in_array($build['sku_id'], $skus)) { $skus[] = $build['sku_id']; continue; }
            $delIDs[] = $build['_rID'];
        }
        msgDebug("\nFound duplicates = ".print_r($delIDs, true));
        if (!empty($delIDs)) { // delete the meta, the job has been orphaned
            $sql = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE id IN (".implode(',', $delIDs).")";
            msgDebug(" ... Executing sql = $sql");
            dbGetResult($sql); 
        }
        // Prices methods in common_meta is not used, delete it
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."common_meta` WHERE meta_key='methods_prices'");
        // These have no useful information and can just be deleted. Maybe in next upgrade to make sure no data is lost
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proEDI'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proGL'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proHR'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='public'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='toolXlate'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrData'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrPhreeBooks'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='xfrQuickBooks'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proIF'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proCust'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proVend'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proInv'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proQA'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proPayment'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='proLgstc'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='ispPortal'");
        dbGetResult("DELETE FROM `".BIZUNO_DB_PREFIX."configuration` WHERE config_key='myPortal'");
    }

    if (version_compare($dbVer, '7.3') < 0) {
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'price_byItem')) { // Field no longer used
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` DROP price_byItem");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'block_discount')) { // New field to prevent discounts from being applied to certain inventory items
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` ADD `block_discount` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:selNoYes;tag:BlockDiscount;order:40' AFTER `price_sheet_c`");
        }
    }

    if (version_compare($dbVer, '7.3.3') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'newsletter'))    {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` ADD newsletter ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;order:10;tag:Newsletter' AFTER `website`");
        }
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'newsletter')) { // fix nulls to enums or integers as needed, prevents strict errors
            dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` set newsletter='0' WHERE newsletter IS NULL;");
        }
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'ach_routing')) { // fix nulls to enums or integers as needed, prevents strict errors
            dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` set ach_routing=0 WHERE ach_routing IS NULL;");
        }
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'woocommerce_sync') ) {
            dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."inventory` set woocommerce_sync=0 WHERE woocommerce_sync IS NULL;");
        }
    }

    if (version_compare($dbVer, '7.3.4') < 0) {
        $exists = dbMetaGet(0, 'bizuno_refs');
        if (empty($exists)) {
            // convert the references to common meta
            bizAutoLoad(BIZBOOKS_ROOT.'controllers/bizuno/install/install.php', 'bizInstall');
            $crnt = getModuleCache('bizuno', 'references');
            $inst = new bizInstall();
            $meta = [];
            foreach ($inst->refs as $key => $value) { $meta[$key] = isset($crnt[$key]) ? $crnt[$key] : $value; }
            $null= dbMetaGet(0, 'bizuno_refs');
            $rID = metaIdxClean($null);
            dbMetaSet($rID, 'bizuno_refs', $meta);
        }
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_main` ADD INDEX(`store_id`)");
    }

    // At every upgrade, run the comments repair tool to fix changes to the view structure and add any new phreeform categories
    require_once(BIZBOOKS_ROOT.'controllers/administrate/tools.php');
    $ctl = new administrateTools();
    $ctl->repairComments(false);

    dbTransactionCommit();
    setModuleCache('bizuno', 'properties', 'version', MODULE_BIZUNO_VERSION); // set newest version
    bizCacheExpClear(); // clear cache to force reload 
}
