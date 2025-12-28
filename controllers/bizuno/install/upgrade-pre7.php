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
 * @version    7.x Last Update: 2025-12-26
 * @filesource /controllers/bizuno/install/upgrade-pre7.php
 */

namespace bizuno;

if (!defined('BIZUNO_DB_PREFIX')) { exit('Illegal Access!'); }


/************************** BOF - Support functions for upgrade script ***************************/
/**
 * Coverts the old extension information to the new way
 * @global type $bizunoMod - Module Cache
 */
function bizunoPre6config() {
    global $bizunoMod;
    setSecurityOverride('admin', 4);
    $core   = ['bizuno','contacts','inventory','payment','phreebooks','phreeform'];
    $modPro = ['proCust','proGL','proHR','proIF','proInv','proLgstc','proQA','proVend'];
    $layout = [];
    bizAutoLoad(BIZUNO_FS_LIBRARY .'controllers/bizuno/settings.php', 'bizunoSettings');
    $bAdmin = new bizunoSettings(); // modules should already be installed, besides this is just for config table
    foreach (portalModuleList() as $modID => $path) {
        if (substr($modID, 0, 3) == 'pro') {
            $exists = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_key', "config_key='$modID'");
            if (!$exists) { $bAdmin->moduleInstall($layout, $modID, $path); } // need to install pro module to create configuration values
        }
    }
    $configs = dbGetMulti(BIZUNO_DB_PREFIX.'configuration');
    foreach ($configs as $row) { $working[$row['config_key']] = json_decode($row['config_value'], true); }
    foreach ($working as $modID => $props) {
        msgDebug("\nWorking with mod = $modID");
        unset($bizunoMod[$modID]['status']);
        switch ($modID) {
            // proCust
            case 'crmPromos':       break;
            case 'custDropShip':    break;
            case 'custFulfillment': break;
            case 'custItemDisc':    break;
            case 'extReturns':      $bizunoMod['proCust']['dashboards']      = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            case 'custCRM':         $bizunoMod['proCust']['settings']['crm'] = $props['settings']['general']; break;
            case 'extBizPOS':       $bizunoMod['proCust']['settings']['pos'] = $props['settings']['general']; break;
            // proGL
            case 'extFixedAssets':  $bizunoMod['proGL']['sched']      = $props['sched']; break;
            case 'extStores':       $bizunoMod['proGL']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            case 'extDocs':         $bizunoMod['proGL']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            case 'extMaint':        $bizunoMod['proGL']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            // proHR
            case 'ifPayrollCentric':$bizunoMod['proHR']['methods']['payroll_centric'] = $props['settings']; break;
            case 'ifIntuitPayroll': $bizunoMod['proHR']['methods']['intuit_payroll']  = $props['settings']; break;
            // proIF
            case 'ifGoogle':        $bizunoMod['proIF']['channels']['google']      = $props['settings']; break;
            case 'ifWooCommerce':   $bizunoMod['proIF']['channels']['woocommerce'] = $props['settings']; break;
            case 'ifAmazon':        $bizunoMod['proIF']['channels']['amazon']      = $props['settings']; break;
            case 'ifPrestaShop':    $bizunoMod['proIF']['channels']['prestashop']  = $props['settings']; break;
            case 'ifOpenCart':      $bizunoMod['proIF']['channels']['opencart']    = $props['settings']; break;
            case 'ifWalmart':       $bizunoMod['proIF']['channels']['walmart']     = $props['settings']; break;
            // proInv
            case 'invAccessory':    break;
            case 'invAutoAssy':     break;
            case 'invAttr':         break;
            case 'invOptions':      break;
            case 'invBulkEdit':     break;
            case 'invImages':       break;
            case 'srvBuilder':      $bizunoMod['proInv']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            // proLgstc
            case 'invReceiving':    break;
            case 'extShipping':     $bizunoMod['proLgstc']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']);
                                    break;
            // proQA
            case 'extISO9001':      $bizunoMod['proQA']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            case 'extTraining':     $bizunoMod['proQA']['dashboards'] = array_merge_recursive($bizunoMod['proCust']['dashboards'], $props['dashboards']); break;
            // proVend
            case 'invBuySell':      break;
            case 'invVendors':      break;
            // others
            case 'toolXlate':
            case 'xfrQuickBooks':   // moved to buzuno-migrate
            case 'xfrPhreeBooks':   // moved to buzuno-migrate
            case 'custWizard':      // added to core
            case 'extQuality':      // deprecated
            default:
        }
    }
    foreach ($modPro as $modID) {
        $exists = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_key', "config_key='$modID'");
        dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_key'=>$modID, 'config_value'=>json_encode($bizunoMod[$modID])], !empty($exists)?'update':'insert', "config_key='$modID'");
    }
    foreach ($core as $modID) {
        dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_value'=>json_encode($bizunoMod[$modID])], 'update', "config_key='$modID'");
    }
    // deletes custom tables so needs re-write
    $delList= ['crmPromos','custDropShip','custFulfillment','custItemDisc','extReturns','custCRM','extBizPOS','extFixedAssets','extStores',
        'extDocs','extMaint','ifPayrollCentric','ifIntuitPayroll','ifGoogle','ifWooCommerce','ifAmazon','ifPrestaShop','ifOpenCart',
        'ifWalmart','invAccessory','invAutoAssy','invAttr','invOptions','invBulkEdit','invImages','srvBuilder','invReceiving','extShipping',
        'extISO9001','extTraining','invBuySell','invVendors','toolXlate','xfrQuickBooks','xfrPhreeBooks','custWizard','extQuality'];
    $sql = "DELETE FROM ".BIZUNO_DB_PREFIX."configuration WHERE config_key IN ('".implode("','", $delList)."')";
    dbGetResult($sql);
}

/************************** EOF - Support functions for upgrade script ***************************/

/**
 * Handles the db upgrade for all versions of Bizuno to the current release level
 * @param string $dbVersion - current Bizuno db version
 */
function upgrade_pre7($dbVersion='1.0')
{
    global $io;
    msgDebug("\nEntering upgrade_pre7 function with db Version = $dbVersion");
    if (version_compare($dbVersion, '2.9') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'users', 'cache_date')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."users ADD `cache_date` DATETIME DEFAULT NULL COMMENT 'tag:CacheDate;order:70' AFTER `attach`");
        }
    }
    if (version_compare($dbVersion, '3.0.7') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'journal_main', 'notes')) {
            dbTransactionStart();
            // Increase the configuration value to support big charts and more dashboards
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."configuration CHANGE `config_value` `config_value` MEDIUMTEXT COMMENT 'type:hidden;tag:ConfigValue;order:20'");
            // Convert the date and datetime fields to remove the unsupported default = '0000-00-00' issue for newer versions of MySQL
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `first_date` `first_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateCreated;col:4;order:10'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `last_update` `last_update` DATE DEFAULT NULL COMMENT 'type:date;tag:DateLastEntry;col:4;order:20'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `last_date_1` `last_date_1` DATE DEFAULT NULL COMMENT 'type:date;tag:AltDate1;col:4;order:30'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `last_date_2` `last_date_2` DATE DEFAULT NULL COMMENT 'type:date;tag:AltDate2;col:4;order:40'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_cogs_owed` CHANGE `post_date` `post_date` DATE DEFAULT NULL COMMENT 'type:date;tag:PostDate;order:40'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_history` CHANGE `last_update` `last_update` DATE DEFAULT NULL COMMENT 'type:date;tag:LastUpdate;order:90'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_item` CHANGE `post_date` `post_date` DATE DEFAULT NULL COMMENT 'type:date;tag:PostDate;order:85'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_main` CHANGE `post_date` `post_date` DATE DEFAULT NULL COMMENT 'type:date;tag:PostDate;order:50'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_main` CHANGE `terminal_date` `terminal_date` DATE DEFAULT NULL COMMENT 'type:date;tag:TerminalDate;order:60'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_main` CHANGE `closed_date` `closed_date` DATE DEFAULT NULL COMMENT 'type:hidden;tag:ClosedDate;order:6'");
            dbTransactionCommit();
            dbTransactionStart();
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_periods` CHANGE `start_date` `start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:StartDate;order:20'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_periods` CHANGE `end_date` `end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:EndDate;order:30'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_periods` CHANGE `date_added` `date_added` DATE DEFAULT NULL COMMENT 'type:date;tag:DateAdded;order:40'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."phreeform` CHANGE `create_date` `create_date` DATE DEFAULT NULL COMMENT 'type:date;tag:CreateDate;order:20'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."phreeform` CHANGE `last_update` `last_update` DATE DEFAULT NULL COMMENT 'type:date;tag:LastUpdate;order:30'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."tax_rates` CHANGE `start_date` `start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:StartDate;order:50'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."tax_rates` CHANGE `end_date` `end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:EndDate;order:60'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts_log` CHANGE `log_date` `log_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:LogDate;order:10'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` CHANGE `creation_date` `creation_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateCreated;order:10'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` CHANGE `last_update` `last_update` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateLastUpdate;order:20'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` CHANGE `last_journal_date` `last_journal_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateLastJournal;order:30'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory_history` CHANGE `post_date` `post_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:PostDate;order:70'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_item` CHANGE `date_1` `date_1` DATETIME DEFAULT NULL COMMENT 'type:date;tag:ItemDate1;order:90'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."phreemsg` CHANGE `post_date` `post_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:PostDate;order:10'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."users` CHANGE `cache_date` `cache_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:CacheDate;order:70'");
            dbTransactionCommit();
            // now extensions
            dbTransactionStart();
            if (dbTableExists(BIZUNO_DB_PREFIX.'crmPromos')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."crmPromos` CHANGE `start_date` `start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:StartDate;order:20'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."crmPromos` CHANGE `end_date` `end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:EndDate;order:30'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."crmPromos_history` CHANGE `send_date` `send_date` DATE DEFAULT NULL COMMENT 'type:date;tag:Date;order:20'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extDocs')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extDocs` CHANGE `create_date` `create_date` DATE DEFAULT NULL COMMENT 'type:date;tag:CreateDate;order:60'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extDocs` CHANGE `last_update` `last_update` DATE DEFAULT NULL COMMENT 'type:date;tag:LastUpdate;order:70'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extMaint')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extMaint` CHANGE `maint_date` `maint_date` DATE DEFAULT NULL COMMENT 'type:date;tag:MaintenanceDate;order:30'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extQuality')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `creation_date` `creation_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateCreated;order:35'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `analyze_start_date` `analyze_start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:AnalyzeStartDate;order:80'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `analyze_end_date` `analyze_end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:AnalyzeEndDate;order:90'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `repair_start_date` `repair_start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:RepairStartDate;order:100'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `repair_end_date` `repair_end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:RepairEndDate;order:110'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `audit_start_date` `audit_start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:AuditStartDate;order:120'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `audit_end_date` `audit_end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:AuditEndDate;order:130'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `close_start_date` `close_start_date` DATE DEFAULT NULL COMMENT 'type:date;tag:CloseStartDate;order:140'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `close_end_date` `close_end_date` DATE DEFAULT NULL COMMENT 'type:date;tag:CloseEndDate;order:150'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extQuality` CHANGE `action_date` `action_date` DATE DEFAULT NULL COMMENT 'type:date;tag:ActionDate;order:160'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extReturns')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` CHANGE `creation_date` `creation_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateCreated;order:105'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` CHANGE `invoice_date` `invoice_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateInvoiced;order:110'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` CHANGE `receive_date` `receive_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateReceived;order:115'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` CHANGE `closed_date` `closed_date` DATE DEFAULT NULL COMMENT 'type:date;tag:DateClosed;order:120'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_jobs')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."srvBuilder_jobs` CHANGE `date_last` `date_last` DATE DEFAULT NULL COMMENT 'type:date;tag:DateLastUsed;order:90'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."srvBuilder_journal` CHANGE `create_date` `create_date` DATE DEFAULT NULL COMMENT 'type:date;tag:CreateDate;order:70'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."srvBuilder_journal` CHANGE `close_date` `close_date` DATE DEFAULT NULL COMMENT 'type:date;tag:ClosedDate;order:80'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'toolXlate')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."toolXlate` CHANGE `date_create` `date_create` DATE DEFAULT NULL COMMENT 'type:date;tag:CreateDate;order:60'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extFixedAssets')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` CHANGE `date_acq` `date_acq` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateAcquired;order:75'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` CHANGE `date_maint` `date_maint` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateLastMaintained;order:80'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` CHANGE `date_retire` `date_retire` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DateRetired;order:85'");
            }
            if (dbTableExists(BIZUNO_DB_PREFIX.'extShipping')) {
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extShipping` CHANGE `ship_date` `ship_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:ShipDate;order:30'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extShipping` CHANGE `deliver_date` `deliver_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DueDate;order:35'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extShipping` CHANGE `actual_date` `actual_date` DATETIME DEFAULT NULL COMMENT 'type:date;tag:DeliveryDate;order:40'");
                $found10 = dbFieldExists(BIZUNO_DB_PREFIX.'extShipping', 'billed');
                if (!$found10) {
                    dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extShipping` ADD `billed` FLOAT DEFAULT '0' COMMENT 'type:currency;tag:Billed;order:60' AFTER `cost`");
                }
            }
            dbTransactionCommit();
            dbTransactionStart();
            // Add notes field to the journal_main table
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."journal_main ADD `notes` VARCHAR(255) DEFAULT NULL COMMENT 'tag:Notes;order:90' AFTER terms");
            dbTransactionCommit();
        } // EOF - if (!dbFieldExists(BIZUNO_DB_PREFIX.'journal_main', 'notes'))
    }
    if (version_compare($dbVersion, '3.1.7') < 0) {
        clearModuleCache('bizuno', 'properties', 'encKey'); // Fixes possible bug in storage of encryption key
        // Fix bug in allowing null value in inactive field in table contacts
        dbWrite(BIZUNO_DB_PREFIX.'contacts', ['inactive'=>0], 'update', "inactive=''");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `inactive` `inactive` CHAR(1) NOT NULL DEFAULT '0' COMMENT 'type:select;tag:Status;order:20'");
    }
    if (version_compare($dbVersion, '3.2.4') < 0) {
        if (dbTableExists(BIZUNO_DB_PREFIX.'extReturns')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extReturns', 'fault')) { // add new field to extension returns table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` ADD `fault` INT(11) NOT NULL DEFAULT '0' COMMENT 'type:select;tag:FaultCode;order:22' AFTER `code`");
        } }
    }
    if (version_compare($dbVersion, '3.2.5') < 0) {
        clearModuleCache('bizuno', 'properties', 'encKey'); // Fixes possible bug in storage of encryption key
        // Fixes illegal access to uploads/bizuno folder
        $htaccess = '# secure uploads directory
<Files ~ ".*\..*">
	Order Allow,Deny
	Deny from all
</Files>
<FilesMatch "\.(jpg|jpeg|jpe|gif|png|tif|tiff)$">
	Order Deny,Allow
	Allow from all
</FilesMatch>';
        // write the file to the WordPress Bizuno data folder.
        $io->fileWrite($htaccess, '.htaccess', false);
    }
    if (version_compare($dbVersion, '3.2.6') < 0) {
        // Verify dummy php index files in all data folders to prevent directory browsing on unprotected servers
        $io->validateNullIndex();
    }
    if (version_compare($dbVersion, '3.2.7') < 0) { // add new customer form folder in phreeform from cust:j19 to cust:j18 and move existing forms to it
        $id = dbGetValue(BIZUNO_DB_PREFIX.'phreeform', 'id', "group_id='cust:j18' AND mime_type='dir'");
        if (!$id) {
            $parent = dbGetValue(BIZUNO_DB_PREFIX.'phreeform', 'id', "group_id='cust' AND mime_type='dir'");
            dbWrite(BIZUNO_DB_PREFIX.'phreeform', ['parent_id'=>$parent,'title'=>'sales_receipt','group_id'=>'cust:j18','mime_type'=>'dir','security'=>'u:-1;g:-1','create_date'=>biz_date('Y-m-d')]);
            dbWrite(BIZUNO_DB_PREFIX.'phreeform', ['title'    =>'pos_receipt'],'update', "group_id='cust:j19' AND mime_type ='dir'");
            dbWrite(BIZUNO_DB_PREFIX.'phreeform', ['group_id' =>'cust:j18'],   'update', "group_id='cust:j19' AND mime_type<>'dir'");
        }
    }
    if (version_compare($dbVersion, '3.3.0') < 0) { // New extensions to support ISO 9001 and improved stability
        if (dbTableExists(BIZUNO_DB_PREFIX.'extTraining')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extTraining', 'lead_time')) { // add new field to extension training table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extTraining` ADD `lead_time` CHAR(2) NOT NULL DEFAULT '1w' COMMENT 'type:select;tag:LeadTime;order:25' AFTER `title`");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extTraining` CHANGE `training_date` `train_date` DATE NULL DEFAULT NULL COMMENT 'type:date;tag:TrainingDate;order:30'");
        } }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extMaint')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extMaint', 'lead_time')) { // add new field to extension maintenance table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extMaint` ADD `lead_time` CHAR(2) NOT NULL DEFAULT '1w' COMMENT 'type:select;tag:LeadTime;order:25' AFTER `title`");
        } }
    }
    if (version_compare($dbVersion, '3.3.1') < 0) { }
    if (version_compare($dbVersion, '3.3.2') < 0) {
        if (dbTableExists(BIZUNO_DB_PREFIX.'extFixedAssets')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extFixedAssets', 'store_id')) { // add new field to extension Fixed Assets table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` ADD `store_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'type:hidden;tag:StoreID;order:25' AFTER `status`");
        } }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extFixedAssets')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extFixedAssets', 'dep_sched')) { // add new field to extension Fixed Assets table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` ADD `dep_sched` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'type:select;tag:Schedules;order:90' AFTER `date_retire`");
        } }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extFixedAssets')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extFixedAssets', 'dep_value')) { // add new field to extension Fixed Assets table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extFixedAssets` ADD `dep_value` FLOAT NOT NULL DEFAULT '0' COMMENT 'tag:DepreciatedValue;order:95' AFTER `dep_sched`");
        } }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extReturns')) { if (!dbFieldExists(BIZUNO_DB_PREFIX.'extReturns', 'preventable')) { // add new field to extension Returns table
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` ADD `preventable` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:selNoYes;tag:Preventable;order:21' AFTER `code`");
            dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."extReturns` SET preventable='1' WHERE fault='1' OR fault='3'");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."extReturns` DROP fault");
        } }
    }
    if (version_compare($dbVersion, '3.4.5') < 0) { // add additional emails to the address book
    }
    if (version_compare($dbVersion, '4.4.0') < 0) { // index sku for large inventory tables
        $stmt = dbGetResult("SELECT COUNT(*) AS cnt FROM information_schema.statistics WHERE TABLE_SCHEMA='".BIZUNO_DB_CREDS['name']."' AND TABLE_NAME='".BIZUNO_DB_PREFIX."inventory' AND INDEX_NAME='sku'");
        $data = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (empty($data['cnt'])) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD INDEX('sku')"); }
    }
    if (version_compare($dbVersion, '6.0.0') < 0) {
        bizunoPre6config();
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proCust' WHERE module_id='extReturns'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proGL' WHERE module_id='extMaint'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proGL' WHERE module_id='extDocs'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proGL' WHERE module_id='extStores'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proInv' WHERE module_id='srvBuilder'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proLgstc' WHERE module_id='extShipping'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proQA' WHERE module_id='extISO9001'");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='proQA' WHERE module_id='extTraining'");
    }
    if (version_compare($dbVersion, '6.2.0') < 0) { // updates to support strict mode
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'phreeform', 'bookmarks')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."phreeform` ADD `bookmarks` TEXT DEFAULT NULL COMMENT 'type:checkbox;tag:Bookmarks;order:50' AFTER `title`");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'address_book', 'email2')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."address_book` ADD `email2` VARCHAR(64) NULL DEFAULT '' COMMENT 'tag:Email2;order:40' AFTER `telephone2`");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'address_book', 'email3')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."address_book` ADD `email3` VARCHAR(64) NULL DEFAULT '' COMMENT 'tag:Email3;order:60' AFTER `telephone3`");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'address_book', 'email4')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."address_book` ADD `email4` VARCHAR(64) NULL DEFAULT '' COMMENT 'tag:Email4;order:80' AFTER `telephone4`");
        }
        // some special cases
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['drop_ship'=>'0'], 'update', "drop_ship IS NULL OR drop_ship=''");
    }
    if (version_compare($dbVersion, '6.2.1') < 0) { // another try at adding new field
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'phreeform', 'bookmarks')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."phreeform` ADD `bookmarks` TEXT DEFAULT NULL COMMENT 'type:checkbox;tag:Bookmarks;order:50' AFTER `title`");
        }
    }
    if (version_compare($dbVersion, '6.2.3') < 0) {
        dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['attach'=>'0'], 'update', "attach IS NULL OR attach=''");
    }
    if (version_compare($dbVersion, '6.3.0') < 0) {
        if (dbTableExists(BIZUNO_DB_PREFIX.'extReturns')) { // change email length to align with general journal and contacts
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."extReturns CHANGE `email` `email` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT '' COMMENT 'tag:CallerEmail;order:45'");
        }
        // for WordPress, search though users table and enable Bizuno for users with same email
        // NOTE: This will only work for the current business, the others in multi-business will need to be done manually.
        $users = dbGetMulti(BIZUNO_DB_PREFIX.'users');
        foreach ($users as $user) {
            $wpUser = \get_user_by( 'email', $user['email'] );
            $bizID  = getUserCache('business', 'bizID');
            if (!empty($wpUser)) {
                \update_user_meta( $wpUser->ID, "bizbooks_enable_$bizID", 1 );
                \update_user_meta( $wpUser->ID, "bizbooks_role_$bizID", $user['role_id'] );
            }
        }
    }
    if (version_compare($dbVersion, '6.4.3') < 0) {
        $lbl = sprintf('%s Product','WooCommerce');
        $cat = sprintf('%s Category Path', 'WooCommerce');
        $tag = sprintf('%s Tags', 'WooCommerce');
        $id = validateTab('inventory', lang('estore'), 90);
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'woocommerce_sync') ) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory CHANGE woocommerce_sync woocommerce_sync ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;label:$lbl;tag:WooCommerceSync;tab:$id;order:25;group:WooCommerce'");
        }
        if ( dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'woocommerce_category') ) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory CHANGE woocommerce_category woocommerce_category VARCHAR(255) DEFAULT NULL COMMENT 'label:$cat;tag:WooCommerceCategory;tab:$id;order:26;group:WooCommerce'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'woocommerce_tags')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD woocommerce_tags VARCHAR(255) DEFAULT NULL COMMENT 'label:$tag;tag:WooCommerceTags;tab:$id;order:27;group:WooCommerce'");
        }
    }
    if (version_compare($dbVersion, '6.4.5') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'bizProAttr')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD bizProAttr TEXT DEFAULT NULL COMMENT 'label:BizunoPro Attrs;tag:bizProAttr'");
        }
    }
    if (version_compare($dbVersion, '6.4.7') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'sale_price')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD sale_price DOUBLE NOT NULL DEFAULT '0' COMMENT 'type:currency;tag:SalePrice;order:42' AFTER `full_price`");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extISO9001')) { // add new field preventable
            if (!dbFieldExists(BIZUNO_DB_PREFIX.'extISO9001', 'preventable')) {
                dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."extISO9001 ADD `preventable` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:selNoYes;tag:Preventable;order:22' AFTER `status`");
            }
        }
        dbWriteCache();
        $users = dbGetMulti(BIZUNO_DB_PREFIX.'users');
        foreach ($users as $user) {
            $settings = json_decode($user['settings'], true);
            $newSet['profile'] = $settings['profile'];
            unset($newSet['profile']['menu'], $newSet['profile']['biz_title'], $newSet['profile']['ssl']);
            dbWrite(BIZUNO_DB_PREFIX.'users', ['settings'=>json_encode($newSet)], 'update', "admin_id={$user['admin_id']}");
        }
    }
    if (version_compare($dbVersion, '6.5.0') < 0) {
        // Remove all dashboards that attempt to reach PhreeSoft
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id = 'daily_tip'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id = 'lp_search'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id = 'ps_news'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE dashboard_id = 'subscribe'");
    }
    if (version_compare($dbVersion, '6.5.4') < 0) {
        $id = validateTab('inventory', lang('estore'), 90);
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'woocommerce_slug')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD woocommerce_slug VARCHAR(128) DEFAULT NULL COMMENT 'label:WooCommerce Slug;tag:WooCommerceSlug;tab:$id;order:28;group:WooCommerce'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'bizProShip')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD bizProShip VARCHAR(255) DEFAULT NULL COMMENT 'label:Bizuno Pro Shipping;tag:bizProShip'");
        }
        if (dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'invAttrCat')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP `invAttrCat`"); }
        if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_jobs')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."srvBuilder_jobs CHANGE `title` `title` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT '' COMMENT 'tag:Title;order:10';");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_journal')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."srvBuilder_journal CHANGE `title` `title` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'tag:Title;order:50';");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_tasks')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."srvBuilder_tasks CHANGE `title` `title` VARCHAR(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT '' COMMENT 'tag:Title;order:10';");
        }
        dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."journal_main CHANGE `description` `description` VARCHAR(128) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL COMMENT 'tag:Description;order:30';");
    }
    if (version_compare($dbVersion, '6.5.6') < 0) {
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET qty_po=0, qty_so=0 WHERE inventory_type='ns'");
        if (dbTableExists(BIZUNO_DB_PREFIX.'extISO9001Audit') && !dbFieldExists(BIZUNO_DB_PREFIX.'extISO9001Audit', 'inactive')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."extISO9001Audit ADD `inactive` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;tag:Inactive;order:4' AFTER `task_num`");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'bizProShip')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory CHANGE bizProShip bizProShip TEXT DEFAULT NULL COMMENT 'label:Bizuno Pro Shipping;tag:bizProShip'");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_journal') && !dbFieldExists(BIZUNO_DB_PREFIX.'srvBuilder_journal', 'due_date')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."srvBuilder_journal ADD due_date DATE DEFAULT NULL COMMENT 'type:date;tag:DueDate;order:65' AFTER `create_date`");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'tax_exempt')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` ADD `tax_exempt` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;tag:TaxExempt;order:30' AFTER `store_id`");
        }
        if (!dbTableExists(BIZUNO_DB_PREFIX.'tax_rates_table')) {
            dbGetResult("CREATE TABLE `".BIZUNO_DB_PREFIX."tax_rates_table` (
  `ZipCode` int(5) DEFAULT NULL COMMENT 'tag:ZipCode;order:10',
  `State` varchar(2) DEFAULT NULL COMMENT 'tag:State;order:20',
  `TaxRegionName` varchar(64) DEFAULT NULL COMMENT 'tag:TaxRegionName;order:30',
  `StateRate` decimal(7,6) DEFAULT NULL COMMENT 'tag:StateRate;order:40',
  `EstimatedCombinedRate` decimal(7,6) DEFAULT NULL COMMENT 'tag:EstimatedCombinedRate;order:50',
  `EstimatedCountyRate` decimal(7,6) DEFAULT NULL COMMENT 'tag:EstimatedCountyRate;order:60',
  `EstimatedCityRate` decimal(7,6) DEFAULT NULL COMMENT 'tag:EstimatedCityRate;order:70',
  `EstimatedSpecialRate` decimal(7,6) DEFAULT NULL COMMENT 'tag:EstimatedSpecialRate;order:80',
  `RiskLevel` int(2) DEFAULT NULL COMMENT 'tag:RiskLevel;order:90'
) ENGINE=InnoDB DEFAULT CHARSET=utf8");
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."tax_rates_table` ADD PRIMARY KEY (`ZipCode`)");
        }
    }
    if (version_compare($dbVersion, '6.5.7') < 0) {
        // Fix the store_id set to default where they used to be -1, which was All but makes no sense in multi-store
        if (dbTableExists(BIZUNO_DB_PREFIX.'extFixedAssets')) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."extFixedAssets SET store_id=0 WHERE store_id=-1");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'srvBuilder_journal')) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."srvBuilder_journal SET store_id=0 WHERE store_id=-1");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extShipping')) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."extShipping SET store_id=0 WHERE store_id=-1");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extISO9001Audit')) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."extISO9001Audit SET store_id=0 WHERE store_id=-1");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'extTraining')) {
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."extTraining SET store_id=0 WHERE store_id=-1");
        }
    }

    if (version_compare($dbVersion, '6.6.0') < 0) {
        if (dbTableExists(BIZUNO_DB_PREFIX.'crmProjects')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."crmProjects` CHANGE `market` `market` CHAR(2) NULL DEFAULT '' COMMENT 'type:select;tag:Market;order:35'");
        }
    }
    if (version_compare($dbVersion, '6.6.1') < 0) {
        if (dbTableExists(BIZUNO_DB_PREFIX.'extReturns')) {
            if (!dbFieldExists(BIZUNO_DB_PREFIX.'extReturns', 'store_id')) {
                dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."extReturns ADD `store_id` INT(11) NOT NULL DEFAULT '0' COMMENT 'type:select;tag:StoreID;order:8' AFTER `status`");
            }
        }
        if (dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'ach_enable')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts CHANGE `ach_enable`  `ach_enable`  ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;order:10;tag:ACHEnable'");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts CHANGE `ach_routing` `ach_routing` INT NULL DEFAULT NULL COMMENT 'type:integer;order:30;tag:ACHRouting'");
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts CHANGE `ach_account` `ach_account` VARCHAR(16) NULL DEFAULT NULL COMMENT 'type:integer;order:40;tag:ACHAccount'");
        }
        if (dbTableExists(BIZUNO_DB_PREFIX.'crmProjects')) {
            if (!dbFieldExists(BIZUNO_DB_PREFIX.'crmProjects', 'reminder_date')) {
                dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."crmProjects ADD `reminder_date` DATE DEFAULT NULL COMMENT 'type:date;tag:ReminderDate;order:68' AFTER `assigned_date`");
            }
        }
    }
    if (version_compare($dbVersion, '6.6.4') < 0) {
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."contacts SET store_id=0 WHERE store_id=-1");
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory SET store_id=0 WHERE store_id=-1");
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."inventory_history SET store_id=0 WHERE store_id=-1");
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."journal_cogs_owed SET store_id=0 WHERE store_id=-1");
        dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."journal_main SET store_id=0 WHERE store_id=-1");
    }
    if (version_compare($dbVersion, '6.7.3') < 0) {
        dbGetResult("DROP TABLE IF EXISTS ".BIZUNO_DB_PREFIX."phreemsg");
        dbGetResult("DROP TABLE IF EXISTS ".BIZUNO_DB_PREFIX."tax_rates_table");
    }
    if (version_compare($dbVersion, '6.7.5') < 0) {
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'price_byItem')) {
            dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` ADD `price_byItem` TEXT NULL DEFAULT NULL COMMENT 'tag:PriceByItem;order:44' AFTER `sale_price`;");
        }
    }
    if (version_compare($dbVersion, '6.7.6') < 0) {
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory` CHANGE `inactive` `inactive` CHAR(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT '0' COMMENT 'type:checkbox;tag:Inactive;order:30';");
    }
}
