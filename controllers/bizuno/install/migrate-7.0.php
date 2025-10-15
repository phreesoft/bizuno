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
 * @version    7.x Last Update: 2025-10-11
 * @filesource /controllers/bizuno/install/migrate-7.0.php
 */

namespace bizuno;

if (!defined('BIZUNO_DB_PREFIX')) { exit('Illegal Access!'); }

function migrateBizunoPrep()
{
    msgDebug("\nEntering migrateBizunoPrep.");
    $cron = ['curStep'=>0, 'ttlSteps'=>0, 'curBlk'=>0, 'ttlBlk'=>0, 'ttlRecord'=>0];
    $cron['ttlSteps']++; $cron['ttlBlk']++; // upgrade to 6.7.8.2
    $cron['ttlSteps']++; $cron['ttlBlk']++; // migrate_tables_part_1
    $cron['ttlSteps']++; $cron['ttlBlk']++; // migrate_tables_part_2
    migrate_roles($cron, true);
    migrate_users($cron, true);
    migrate_address_book($cron, true);
    migrate_crm_add_book($cron, true);
    migrate_crm_projects($cron, true);
    migrate_current_stat($cron, true);
    migrate_sales_tax($cron, true);
    migrate_inv_prices($cron, true);
    migrate_inv_assy($cron, true);
    migrate_dashboards($cron, true);
    migrate_phreeform($cron, true);
    migrate_docs($cron, true);
    migrate_shipping_log($cron, true);
    migrate_returns($cron, true);
    migrate_fixed_assets($cron, true);
    migrate_maint($cron, true);
    migrate_training($cron, true);
    migrate_prod_tasks($cron, true);
    migrate_prod_jobs($cron, true);
    migrate_prod_journal($cron, true);
    migrate_quality($cron, true);
    migrate_qual_audit($cron, true);
    migrate_crm_promos($cron, true);
    migrate_crm_promoHist($cron, true);
    migrate_edi_log($cron, true);
    migrate_dash_users($cron, true);
    migrate_misc($cron, true);
    migrate_map($cron, true);
    $cron['ttlSteps']++; $cron['ttlBlk']++; // migrate_rm_tables_pt1
    $cron['ttlSteps']++; $cron['ttlBlk']++; // migrate_rm_tables_pt2
    $cron['ttlSteps']++; $cron['ttlBlk']++; // since we start at zero
    msgDebug("\nReturning from migrateBizunoPrep with cron = ".print_r($cron, true));
    return $cron;
}

function migrateBizuno(&$cron)
{
    msgDebug("\nEntering migrateBizuno with cron = ".print_r($cron, true));
    $dbVersion= getModuleCache('bizuno', 'properties', 'version');
    switch ($cron['curStep']) {
        case  0: // bring to latest free version level, 6.7.8.2
            bizAutoLoad(BIZBOOKS_ROOT.'controllers/bizuno/install/upgrade-pre7.php');
            upgrade_pre7($dbVersion);
            $cron['curStep']++;
            break;
        case  1: migrate_tables_part_1($cron);break; // Add/modify contacts table
        case  2: migrate_tables_part_2($cron);break; // Add/modify the rest of the tables
        case  3: migrate_roles($cron);        break; // roles table
        case  4: migrate_users($cron);        break; // users table
        case  5: migrate_address_book($cron); break; // address_book table
        case  6: migrate_crm_add_book($cron); break; // Contacts table CRM (type i) to meta
        case  7: migrate_crm_projects($cron); break;
        case  8: migrate_current_stat($cron); break; // current_status table
        case  9: migrate_sales_tax($cron);    break; // sales_tax table
        case 10: migrate_inv_prices($cron);   break; // inventory_prices_table
        case 11: migrate_inv_assy($cron);     break; // inventory_assembly table
        case 12: migrate_dashboards($cron);   break; // Convert Chart of Accounts to meta
        case 13: migrate_phreeform($cron);    break; // phreeform table
        case 14: migrate_docs($cron);         break; // extDocs table
        case 15: migrate_shipping_log($cron); break; // Convert shipping logs to journal_meta
        case 16: migrate_returns($cron);      break; // extReturns table
        case 17: migrate_fixed_assets($cron); break; // extFixedAssets table
        case 18: migrate_maint($cron);        break; // extMaint table
        case 19: migrate_training($cron);     break;
        case 20: migrate_prod_tasks($cron);   break; // Word Orders
        case 21: migrate_prod_jobs($cron);    break;
        case 22: migrate_prod_journal($cron); break;
        case 23: migrate_quality($cron);      break; // Quality
        case 24: migrate_qual_audit($cron);   break;
        case 25: migrate_crm_promos($cron);   break; // CRM
        case 26: migrate_crm_promoHist($cron);break;
        case 27: migrate_edi_log($cron);      break; // EDI
        case 28: migrate_dash_users($cron);   break; // Fix the dashboard structures to new format
        case 29: migrate_misc($cron);         break; // Tabs, fields, etc.
        case 30: migrate_map($cron);          break; // Map the new user IDs to old IDs
        case 31: migrate_rm_tables_pt1($cron);break; // Drop tables
        case 32: migrate_rm_tables_pt2($cron);break; // Fix contact type restructure
        default: $cron['curStep']++; $cron['curBlk']++; break; // for missing steps
    }
    msgDebug("\nLeaving migrateBizuno with cron = ".print_r($cron, true));
}

/**
 * Add/Modify contacts tables
 * @param type $cron
 */
function migrate_tables_part_1(&$cron=[])
{
    msgDebug("\nEntering migrate_tables_part_1.");
    // ************************ Bizuno-core tables - Part 1 ********************************** //
    dbTransactionStart();
    // Fix some strict issues with the db tables
    dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."contacts CHANGE tax_rate_id tax_rate_id INT(11) DEFAULT '0' COMMENT 'type:tax;tag:TaxRateID;order:16'");
    // change table address_book type to default to m
    dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."address_book` CHANGE `type` `type` CHAR(1) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci NOT NULL DEFAULT 'm' COMMENT 'type:hidden;tag:AddressType;order:3';");
    // Add address fields to contacts table
    if (!dbFieldExists(BIZUNO_DB_PREFIX.'contacts', 'email')) { // add email field to contacts table
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts`
ADD `ctype_b` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:branch;order:4' AFTER `type`,
ADD `ctype_c` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:customer;order:4' AFTER `ctype_b`,
ADD `ctype_e` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:employee;order:4' AFTER `ctype_c`,
ADD `ctype_i` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:crm;order:4' AFTER `ctype_e`,
ADD `ctype_j` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:project;order:4' AFTER `ctype_i`,
ADD `ctype_u` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:user;order:4' AFTER `ctype_j`,
ADD `ctype_v` ENUM('0','1') DEFAULT '0' COMMENT 'type:checkbox;tag:vendor;order:4' AFTER `ctype_u`,
ADD `primary_name` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'tag:PrimaryName;order:30' AFTER `flex_field_1`,
ADD `contact` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'tag:Contact;order:32' AFTER `primary_name`,
ADD `address1` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'tag:Address1;order:34' AFTER `contact`,
ADD `address2` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'tag:Address2;order:36' AFTER `address1`,
ADD `city` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'tag:City;order:38' AFTER `address2`,
ADD `state` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'tag:State;order:40' AFTER `city`,
ADD `postal_code` VARCHAR(12) NOT NULL DEFAULT '' COMMENT 'tag:PostalCode;order:42' AFTER `state`,
ADD `country` VARCHAR(3) NOT NULL DEFAULT 'USA' COMMENT 'type:country;tag:CountryISO3;order:44' AFTER `postal_code`,
ADD `email` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'tag:Email;order:46' AFTER `country`,
ADD `email2` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'tag:Email2;order:48' AFTER `email`,
ADD `email3` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'tag:Email3;order:50' AFTER `email2`,
ADD `email4` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'tag:Email4;order:52' AFTER `email3`,
ADD `telephone1` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'tag:Telephone1;order:54' AFTER `email4`,
ADD `telephone2` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'tag:Telephone2;order:56' AFTER `telephone1`,
ADD `telephone3` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'tag:Telephone3;order:58' AFTER `telephone2`,
ADD `telephone4` VARCHAR(20) NOT NULL DEFAULT '' COMMENT 'tag:Telephone4;order:60' AFTER `telephone3`,
ADD `website` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'tag:Website;order:62' AFTER `telephone4`;");
    }
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

/**
 * Add/Modify the rest of the tables
 * @param type $cron
 */
function migrate_tables_part_2(&$cron=[])
{
    msgDebug("\nEntering migrate_tables_part_2.");
    // ************************ Bizuno-core tables - Part 1 ********************************** //
    dbTransactionStart();
    // Add table common_meta
    if (!dbTableExists(BIZUNO_DB_PREFIX.'common_meta')) {
        dbGetResult("CREATE TABLE `".BIZUNO_DB_PREFIX."common_meta` (
  `id` int(11) NOT NULL COMMENT 'tag:ID;order:1',
  `meta_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'tag:MetaKey;order:10',
  `meta_value` text NOT NULL DEFAULT '' COMMENT 'tag:MetaValue;order:20'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."common_meta` ADD PRIMARY KEY (`id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."common_meta` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'tag:ID;order:10';");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."common_meta` ADD INDEX(`meta_key`);");
    } else {
        dbGetResult("TRUNCATE TABLE `".BIZUNO_DB_PREFIX."common_meta`");
    }
    // Add table contacts_meta
    if (!dbTableExists(BIZUNO_DB_PREFIX.'contacts_meta')) {
        dbGetResult("CREATE TABLE `".BIZUNO_DB_PREFIX."contacts_meta` (
  `id` int(11) NOT NULL COMMENT 'tag:ID;order:1',
  `ref_id` int(11) NOT NULL COMMENT 'type:hidden;tag:ReferenceID;order:10',
  `meta_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'tag:MetaKey;order:20',
  `meta_value` text NOT NULL DEFAULT '' COMMENT 'tag:MetaValue;order:30'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts_meta` ADD PRIMARY KEY (`id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts_meta` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'tag:ID;order:10';");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts_meta` ADD INDEX(`ref_id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts_meta` ADD INDEX(`meta_key`);");
    } else {
        dbGetResult("TRUNCATE TABLE `".BIZUNO_DB_PREFIX."contacts_meta`");
    }
    // Add table inventory_meta
    if (!dbTableExists(BIZUNO_DB_PREFIX.'inventory_meta')) {
        dbGetResult("CREATE TABLE `".BIZUNO_DB_PREFIX."inventory_meta` (
  `id` int(11) NOT NULL COMMENT 'tag:ID;order:1',
  `ref_id` int(11) NOT NULL COMMENT 'type:hidden;tag:ReferenceID;order:10',
  `meta_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'tag:MetaKey;order:20',
  `meta_value` text NOT NULL DEFAULT '' COMMENT 'tag:MetaValue;order:30'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory_meta` ADD PRIMARY KEY (`id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory_meta` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'tag:ID;order:10';");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory_meta` ADD INDEX(`ref_id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."inventory_meta` ADD INDEX(`meta_key`);");
    } else {
        dbGetResult("TRUNCATE TABLE `".BIZUNO_DB_PREFIX."inventory_meta`");
    }
    // Add table journal_meta
    if (!dbTableExists(BIZUNO_DB_PREFIX.'journal_meta')) {
        dbGetResult("CREATE TABLE `".BIZUNO_DB_PREFIX."journal_meta` (
  `id` int(11) NOT NULL COMMENT 'tag:ID;order:1',
  `ref_id` int(11) NOT NULL COMMENT 'type:hidden;tag:ReferenceID;order:10',
  `meta_key` varchar(64) NOT NULL DEFAULT '' COMMENT 'tag:MetaKey;order:20',
  `meta_value` text NOT NULL DEFAULT '' COMMENT 'tag:MetaValue;order:30'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_meta` ADD PRIMARY KEY (`id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_meta` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'tag:ID;order:10';");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_meta` ADD INDEX(`ref_id`);");
        dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."journal_meta` ADD INDEX(`meta_key`);");
    } else {
        dbGetResult("TRUNCATE TABLE `".BIZUNO_DB_PREFIX."journal_meta`");
    }
    // Fix moved dashboards
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."users_profiles` SET module_id='contacts' WHERE module_id='proCust'");
    dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."journal_main CHANGE notes notes TEXT");
    // Set some initial common meta values
    dbMetaSet(0, 'bizuno_cache_expires', 0); // record ID: 1
    dbMetaSet(0, 'methods_totals',  getModuleCache('phreebooks', 'totals')); // record ID: 2
    dbMetaSet(0, 'methods_prices',  getModuleCache('inventory', 'prices')); // record ID: 3
    dbMetaSet(0, 'methods_gateways',getModuleCache('payment', 'methods')); // record ID: 4
    dbMetaSet(0, 'methods_carriers',getModuleCache('proLgstc', 'carriers')); // record ID: 5
    dbMetaSet(0, 'methods_funnels', getModuleCache('proIF', 'channels')); // record ID: 6
    dbMetaSet(0, 'methods_payroll', getModuleCache('proHR', 'methods')); // record ID: 7
    migrate_chart_of_accounts(); // needs to be done here as when cache reloads 
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

function migrate_chart_of_accounts()
{
    $set   = dbGetRow(BIZUNO_DB_PREFIX.'configuration', "config_key='phreebooks'"); // needs to come from db as cache is not loaded
    $rows  = json_decode($set['config_value'], true);
    msgDebug("\nRead phreebooks config value: ".print_r($rows, true));
    $output=[];
    if (!empty($rows['chart']['accounts'])) {
        foreach ($rows['chart']['accounts'] as $row) {
            if (empty($row['inactive'])) { $row['inactive'] = 0; }
            $output[$row['id']] = $row;
        }
    }
    // set the defaults
    $defaults = array_shift($rows['chart']['defaults']); // just the first ISO, assume this is the default ISO
    foreach ((array)$defaults as $acct) { if (isset($output[$acct])) { $output[$acct]['default'] = 1; } }
    msgDebug("\nReady to write output = ".print_r($output, true));
    dbMetaSet(0, 'chart_of_accounts', $output); // record ID: 8
//  clearModuleCache('phreebooks', 'chart'); // don't do this until this script is rock solid, maybe in next release.
//  setModuleCache('phreebooks', 'chart', false, $output);
}

/**
 * Convert roles
 */
function migrate_roles(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_roles.");
    $table= 'roles';
    $chunk= 1000;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = $roleMap = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        $settings = json_decode($row['settings'], true);
        // fix some goofy security indexes
        $administrate = !empty($settings['security']['admin']) ? 1 : 0;
        $settings['security']['training'] = isset($settings['security']['extTraining']) ? $settings['security']['extTraining'] : 0;
        $settings['security']['shipping'] = isset($settings['security']['extShipping']) ? $settings['security']['extShipping'] : 0;
//      $settings['security']['mgr_fa']   = isset($settings['security']['fixedAssets']) ? $settings['security']['fixedAssets'] : 0;
        $settings['security']['mgr_docs'] = isset($settings['security']['extDocs'])     ? $settings['security']['extDocs']     : 0;
        $settings['security']['mgr_maint']= isset($settings['security']['extMaint'])    ? $settings['security']['extMaint']    : 0;
        // remove old indexes
        unset($settings['security']['extTraining'],$settings['security']['extShipping']);
        unset($settings['security']['mgr_e'],      $settings['security']['mgr_b']);
        unset($settings['security']['fixedAssets'],$settings['security']['extDocs']);
        unset($settings['security']['extMaint'],   $settings['security']['admin']);
        // fix the groups
        $groups = isset($settings['bizuno']['roles']) ? $settings['bizuno']['roles'] : [];
        if (!empty($groups['rtn'])) { $groups['csr'] = 1; unset($groups['rtn']); }
        unset($groups['proQA']); // this is weird but has to go
        // set the migrated role
        $role = [
            'title'       => $row['title'],
            'inactive'    => $row['inactive'],
            'restrict'    => $settings['restrict'],
            'administrate'=> $administrate,
            'groups'      => $groups,
            'security'    => $settings['security'],
            'notes'       => isset($settings['pps_req']) ? $settings['pps_req'] : (isset($settings['notes']) ? $settings['notes'] : ''),
            'menuBar'     => $settings['menuBar']];
        $newID = dbMetaSet(0, 'bizuno_role', $role);
        $roleMap['r'.$row['id']] = $newID;
    }
    dbMetaSet(0, '_ROLE_ID_MAP', $roleMap); // save the role map as common meta for use later
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Move users to contacts table, map settings to contacts_meta
 */
function migrate_users(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_users.");
    global $io;
    $table= 'users';
    $chunk= 1000; // try to do in a single pass
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rIDs = $pIDs = $userMap = [];
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'admin_id', '', $chunk);
    $roleMap = dbMetaGet(0, '_ROLE_ID_MAP');
    foreach ($rows as $user) {
        $rIDs[]= $user['admin_id'];
        // try to find existing record with same email, use the first one
        $newID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "email='".addslashes($user['email'])."'");
        if (!empty($newID)) {
            dbWrite(BIZUNO_DB_PREFIX.'contacts', ['ctype_u'=>'1'], 'update', "id=$newID");
        } else {
            $newID = dbWrite(BIZUNO_DB_PREFIX.'contacts', ['type'=>'u', 'email'=>$user['email'], 'short_name'=>$user['title'], 'primary_name'=>$user['title'], 'inactive'=>$user['inactive'], 'first_date'=>$user['first_date']]);
        }
        $userMap['r'.$user['admin_id']] = $newID;
        if (!empty($user['attach'])) { // move attachments
            $io->fileMove(getModuleCache('bizuno', 'properties', 'usersAttachPath'), "rID_{$user['admin_id']}_", "rID_{$newID}_");
        }
        $attrs  = json_decode($user['settings'], true); // save the settings
        $profile= [
            'language'       => !empty($attrs['profile']['language'])       ? $attrs['profile']['language']       : 'en_US',
            'title'          => !empty($attrs['profile']['title'])          ? $attrs['profile']['title']          : $user['email'],
            'icons'          => !empty($attrs['profile']['icons'])          ? $attrs['profile']['icons']          : 'default',
            'theme'          => !empty($attrs['profile']['theme'])          ? $attrs['profile']['theme']          : 'bizuno',
            'gzone'          => !empty($attrs['profile']['gzone'])          ? $attrs['profile']['gzone']          : 'America/New_York',
            'def_periods'    => !empty($attrs['profile']['def_periods'])    ? $attrs['profile']['def_periods']    : 'l',
            'grid_rows'      => !empty($attrs['profile']['grid_rows'])      ? $attrs['profile']['grid_rows']      : 20,
            'store_id'       => !empty($attrs['profile']['store_id'])       ? $attrs['profile']['store_id']       : 0,
            'role_id'        => $roleMap['r'.$attrs['profile']['role_id']],
            'restrict_store' => !empty($attrs['profile']['restrict_store']) ? $attrs['profile']['restrict_store'] : 0,
            'restrict_user'  => !empty($attrs['profile']['restrict_user'])  ? $attrs['profile']['restrict_user']  : 0,
            'restrict_period'=> !empty($attrs['profile']['restrict_period'])? $attrs['profile']['restrict_period']: 0,
            'cash_acct'      => !empty($attrs['profile']['cash_acct'])      ? $attrs['profile']['cash_acct']      : '',
            'ar_acct'        => !empty($attrs['profile']['ar_acct'])        ? $attrs['profile']['ar_acct']        : '',
            'ap_acct'        => !empty($attrs['profile']['ap_acct'])        ? $attrs['profile']['ap_acct']        : '',
            'smtp_enable'    => !empty($attrs['profile']['smtp_enable'])    ? $attrs['profile']['smtp_enable']    : 0,
            'smtp_host'      => !empty($attrs['profile']['smtp_host'])      ? $attrs['profile']['smtp_host']      : 'https://smtp.gmail.com',
            'smtp_port'      => !empty($attrs['profile']['smtp_port'])      ? $attrs['profile']['smtp_port']      : 587,
            'smtp_user'      => !empty($attrs['profile']['smtp_user'])      ? $attrs['profile']['smtp_user']      : '',
            'smtp_pass'      => !empty($attrs['profile']['smtp_pass'])      ? $attrs['profile']['smtp_pass']      : ''
            ];
        dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['ref_id'=>$newID, 'meta_key'=>'user_profile', 'meta_value'=>json_encode($profile)]);
    }
    dbMetaSet(0, '_ADMIN_ID_MAP', $userMap); // save the user map as common meta for use later
    // migrate the users_profile table
    foreach ($rows as $user) { // map users_profile table to contacts_meta
        msgDebug("\nWorking with user to map dashboards: ".print_r($user, true));
        $dashboards = dbGetMulti(BIZUNO_DB_PREFIX.'users_profiles', "user_id={$user['admin_id']}", 'menu_id');
        $output = [];
        foreach ($dashboards as $menu) {
            msgDebug("\nWorking on menu item: ".print_r($menu, true));
            $pIDs[]  = $menu['id'];
            $settings= json_decode($menu['settings'], true);
            if (empty($settings)) { $settings = []; }
            unset($settings['roles'], $settings['users']); // users/roles should not be in the dashboard settings at the user/menu level, it's a global setting
            $output[$menu['menu_id']][$menu['dashboard_id']] = ['col'=>$menu['column_id'], 'row'=>$menu['row_id'], 'opts'=>$settings];
        }
        msgDebug("\ndashboard options output = ".print_r($output, true));
        foreach ($output as $menu => $options) { dbMetaSet(0, "dashboard_{$menu}", $options, 'contacts', $userMap['r'.$user['admin_id']]); }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE admin_id IN (".implode(',', $rIDs).")"); }
    if (!empty($pIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."users_profiles WHERE id IN (".implode(',', $pIDs).")"); }
    dbTransactionCommit();
}

/**
 * Move address_book to contacts meta
 * The main address is now part of the contact record, billing and shipping are all part of the contacts meta 'address_book'
 */
function migrate_address_book(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_address_book.");
    $addFields= ['primary_name', 'contact', 'address1', 'address2', 'city', 'state', 'postal_code', 'country',
        'telephone1', 'telephone2', 'telephone3', 'telephone4', 'email', 'email2', 'email3', 'email4', 'website'];
    $table= 'address_book';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    msgDebug("\nRead table $table with cnt = $cnt");
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rIDs = [];
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'ref_id', '', $chunk);
    foreach ($rows as $row) {
        $rIDs[] = $row['address_id'];
        if (empty($row['ref_id'])) { continue; }
        if ('m'==$row['type']) { // main address
            $data  = [];
            foreach ($addFields as $field) { $data[$field] = $row[$field]; }
            dbWrite(BIZUNO_DB_PREFIX.'contacts', $data, 'update', "id={$row['ref_id']}");
            if (!empty($row['notes'])) { dbMetaSet(0, 'notes', $row['notes'], 'contacts', $row['ref_id']); }
        } else { // billing, shipping, etc, make it a meta tied to customer
            $refID = $row['ref_id'];
            unset($row['address_id'], $row['ref_id']);
            dbMetaSet(0, "address_{$row['type']}", $row, 'contacts', $refID);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE address_id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Move customer/vendor linked CRM contacts to contacts meta, rest to trash???
 * THIS NEEDS TO BE DONE IN CHUNKS AFTER ADDRESS_BOOK
 * NOTES: unlinked contacts are orphaned, attachments are orphaned
 */
function migrate_crm_add_book(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_crm_add_book.");
    $addFields= ['primary_name', 'contact', 'address1', 'address2', 'city', 'state', 'postal_code', 'country',
        'telephone1', 'telephone2', 'telephone3', 'telephone4', 'email', 'email2', 'email3', 'email4', 'website'];
    $table= 'contacts';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', "type='i'", false);
    msgDebug("\n[migrate_crm_add_book] Read table $table with cnt = $cnt");
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rIDs = [];
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, "type='i'", '', '', $chunk);
    foreach ($rows as $row) {
        $rIDs[]= $row['id'];
        if (empty($row['rep_id'])) { continue; } // discard orphaned records
        $data  = ['address_id'=>$row['id'], 'name_first'=>$row['contact_first'], 'name_last'=>$row['contact_last'], 'title'=>$row['flex_field_1']];
        foreach ($addFields as $field) { $data[$field] = $row[$field]; }
        dbMetaSet(0, "address_{$row['type']}", $data, 'contacts', $row['rep_id']);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_crm_projects(&$cron=[], $cntOnly=false) // Map CRMProjects to journal_main
{
    msgDebug("\nEntering migrate_crm_projects.");
    $table= 'crmProjects';
    $chunk= 200;
    $cnt  = dbTableExists(BIZUNO_DB_PREFIX.$table) ? dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false) : 0;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[]= $row['id'];
        $cID   = !empty($row['contact_id']) ? $row['contact_id'] : 0;
        $row['ref_num']= $row['proj_num'];
        $row['notes']  = "{$row['working_notes']}\n\nQuantity: {$row['qty']} -> SKU: {$row['sku']}";
        unset($row['id'], $row['proj_num'], $row['sku'], $row['qty'], $row['working_notes']);
        if (empty($cID)) { // need to make new contact to link
            $data = ['type'=>'c', 'primary_name'=>!empty($row['title']) ? substr($row['title'], 0, 48) : "crm_project_{$row['ref_num']}_{$row['id']}",
                'short_name'=>$row['ref_num'], 'first_date'=>$row['created_date']];
            $cID = $row['contact_id'] = dbWrite(BIZUNO_DB_PREFIX.'contacts', $data);
        }
        dbMetaSet(0, 'crm_project', $row, 'contacts', $cID);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * CURRENT STATUS - map table current_status to module cache: bizuno -> references
 */
function migrate_current_stat(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_current_stat.");
    $refs  = dbGetRow(BIZUNO_DB_PREFIX.'current_status'); // should only be one row
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']++; return; }
    dbTransactionStart();
    $output = [];
    foreach ($refs as $key => $value) { $output[$key] = $value; }
    // Make some changes
    $output['next_qaobj_num'] = $output['next_qo_num'];
    $output['next_ticket_num']= $output['next_qa_num'];
    unset($output['next_qa_num'], $output['next_qo_num']);
    setModuleCache('bizuno', 'references', '', $output);
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

/**
 * SALES TAX - map sales_tax table to module cache: phreebooks -> sales_tax
 */
function migrate_sales_tax(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_sales_tax.");
    $table= 'tax_rates';
    $chunk= 100000; // try to do in a single pass
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', '', '', $chunk);
    $rIDs = [];
    foreach ($rows as $value) {
        $rIDs[]= $value['id'];
        $data  = ['title'=>$value['title'], 'inactive'=>$value['inactive'], 'tax_rate'=>$value['tax_rate'], 'start_date'=>$value['start_date'], 'end_date'=>$value['end_date'], 'taxAuths'=>json_decode($value['settings'], true)];
        if ($value['type']=='v') { dbMetaSet(0, 'tax_rate_v', $data); }
        else                     { dbMetaSet(0, 'tax_rate_c', $data); }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Map inventory_prices to inventory_meta table
 */
function migrate_inv_prices(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_inv_prices.");
    $table= 'inventory_prices';
    $chunk= 1000000; // Needs to be done in a single transaction or the price map is split
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', '', '', $chunk);
    $rIDs = $priceMap = [];
    foreach ($rows as $price) {
        $rIDs[]= $rID = $price['id'];
        $temp  = json_decode($price['settings'], true);
        $price['title']      = !empty($temp['title'])  ? $temp['title']  : '';
        $price['last_update']= $temp['last_update'];
        $price['levels']     = getPrices($temp['attr']);
        $price['default']    = !empty($temp['default'])? $temp['default']: 0;
        $price['cID']        = $price['contact_id'];
        $price['iID']        = $price['inventory_id'];
        unset($price['id'], $price['method'], $price['settings'], $price['contact_id'], $price['inventory_id']);
        $key = 'price_'.$price['contact_type'];
        if     (!empty($price['cID'])) { $dbTable='contacts';  $refID=$price['cID']; } // tied to contact meta
        elseif (!empty($price['iID'])) { $dbTable='inventory'; $refID=$price['iID']; } // tied to inventory meta
        else                           { $dbTable='common';    $refID=0; }
        $newID = dbMetaSet(0, $key, $price, $dbTable, $refID);
        if (empty($price['cID']) && empty($price['iID'])) { $priceMap['r'.$rID] = $newID; } // save the general sheet for the contact map
    }
    dbMetaSet(0, '_PRICE_MAP', $priceMap); // save the user map as common meta for use later

    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    clearModuleCache('phreebooks', 'prices'); // clear the module cache as prices are now assigned to skus or contacts
    dbTransactionCommit();
}

/**
 * Decodes the price sheet settings for quantity based pricing and returns array of values for grid display
 * @param string $prices - encoded price value
 * @return array - ready to display in grid
 */
function getPrices($prices='')
{
    msgDebug("\nWorking with price string: $prices");
    $price_levels = explode(';', $prices);
    $arrData = [];
    for ($i=0; $i<sizeof($price_levels); $i++) {
        $level_info = explode(':', $price_levels[$i]);
        $arrData[] = [
            'price'   => isset($level_info[0]) ? $level_info[0] : 0,
            'qty'     => isset($level_info[1]) ? $level_info[1] : ($i+1),
            'source'  => isset($level_info[2]) ? $level_info[2] : '1',
            'adjType' => isset($level_info[3]) ? $level_info[3] : '',
            'adjValue'=> isset($level_info[4]) ? $level_info[4] : 0,
            'rndType' => isset($level_info[5]) ? $level_info[5] : '',
            'rndValue'=> isset($level_info[6]) ? $level_info[6] : 0];
    }
    return ['total'=>sizeof($arrData), 'rows'=>$arrData];
}

/**
 * map inventory_assy_list to common table
 */
function migrate_inv_assy(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_inv_assy.");
    $table= 'inventory_assy_list';
    $chunk= 200;
    $rIDs = [];
    $stmt = dbGetResult("SELECT DISTINCT ref_id FROM ".BIZUNO_DB_PREFIX.$table); // get the assembly SKUs
    $assys= $stmt->fetchAll(\PDO::FETCH_ASSOC);
    $cnt  = sizeof($assys);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    foreach ($assys as $assy) {
        $rIDs[]= $assy['ref_id'];
        $data  = [];
        $parts = dbGetMulti(BIZUNO_DB_PREFIX.$table, "ref_id={$assy['ref_id']}");
        foreach ($parts as $item) { $data[] = ['sku'=>$item['sku'], 'description'=>$item['description'], 'qty'=>$item['qty']]; }
        if (sizeof($data)) { dbMetaSet(0, 'bill_of_materials', $data, 'inventory', $assy['ref_id']); }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE ref_id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Convert chart of accounts and dashboard defaults to db meta data
 */
function migrate_dashboards(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_dashboards.");
    global $bizunoMod;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=75; return; } // approx number of dashboards

    // Let's go
    dbTransactionStart();
    $metaDash= dbMetaGet(0, 'dashboards'); // get rID just in case it got set during registry
    $rID     = metaIdxClean($metaDash);
    $defs    = [];
    $userMap = dbMetaGet(0, '_ADMIN_ID_MAP');
    $roleMap = dbMetaGet(0, '_ROLE_ID_MAP');
    foreach ($bizunoMod as $module => $props) {
        if (empty($props['dashboards'])) { msgDebug("\nNo dashboards for module $module"); continue; }
        msgDebug("\nprocessing dashboards list: ".print_r($props['dashboards'], true));
        foreach ($props['dashboards'] as $idx => $values) {
            unset($values['id'], $values['status'], $values['default'], $values['order'], $values['acronym']); // not needed for dashboards
            // Some installs have a malformed settings, just remove the dashboard for now, the user will need to put it back manually.
            if (empty($values['settings']['users']) || empty($values['settings']['roles'])) { continue; }
            if (is_array($values['settings']['users']) || is_array($values['settings']['roles'])) { continue; }
            if (strpos($values['settings']['users'], ':')!==false) { $parts = explode(':', $values['settings']['users']); }
            elseif (empty($values['settings']['users']))           { $parts = [0]; }
            else                                                   { $parts = [$values['settings']['users']]; }
            $userIDs = [];
            foreach ($parts as $id) { if (isset($userMap['r'.$id])){ $userIDs[] = intval($userMap['r'.$id]); } }
            $values['settings']['users'] = $userIDs;

            if (strpos($values['settings']['roles'], ':')!==false) { $parts = explode(':', $values['settings']['roles']); }
            elseif (empty($values['settings']['roles']))           { $parts = [0]; }
            else                                                   { $parts = [$values['settings']['roles']]; }
            $roleIDs = [];
            foreach ($parts as $id) { if (isset($roleMap['r'.$id])){ $roleIDs[] = intval($roleMap['r'.$id]); } }
            $values['settings']['roles'] = $roleIDs;
            $defs[$idx] = $values;
        }
        // Don't need the modules to store the dashboard properties anymore
        clearModuleCache($module, 'dashboards');
    }
    dbMetaSet($rID, 'dashboards', $defs);
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

/**
 * Convert phreeform
 */
function migrate_phreeform(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_phreeform.");
    $table= 'phreeform';
    $chunk= 10000; // single pass so folder tree doesn't get duplicated
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    msgDebug("\nReturned from cnt with count = $cnt");
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreeform/functions.php', 'phreeFormXML2Obj', 'function');
    dbTransactionStart();
    $map  = dbMetaGet(0, '_ADMIN_ID_MAP');
    $dirs = dbGetMulti(BIZUNO_DB_PREFIX.$table, "mime_type='dir'", 'id');
    $rptMap = ['r0'=>0]; // home id
    foreach ($dirs as $row) { // create the folders and build parent_id map
        msgDebug("\nWorking with row id = {$row['id']} and parent id = {$row['parent_id']}");
        $metaVal = $row;
        $security= mapUsers($row['security']);
        $metaVal['parent_id'] = !empty($rptMap['r'.$row['parent_id']]) ? $rptMap['r'.$row['parent_id']] : 0;
        $metaVal['users'] = !empty($security['users']) ? $security['users'] : [0];
        $metaVal['roles'] = !empty($security['roles']) ? $security['roles'] : [0];
        unset($metaVal['id'], $metaVal['security'], $metaVal['bookmarks'], $metaVal['doc_data']);
        $newID = dbMetaSet(0, 'phreeform', $metaVal);
        $rptMap['r'.$row['id']] = $newID;
    }
    msgDebug("\nFinished processing phreeform folders with parentMap = ".print_r($rptMap, true));
    // Reports and Forms, is there another type (lst)?
    $rIDs  = $theList = [];
    $rows  = dbGetMulti(BIZUNO_DB_PREFIX.$table, "mime_type<>'dir'", 'id'); 
    foreach ($rows as $row) { // report and form
        $rIDs[]  = $row['id'];
        msgDebug("\nWorking with row id = {$row['id']} and parent id = {$row['parent_id']}");
        $security= mapUsers($row['security']);
        $report  = phreeFormXML2Obj($row['doc_data']);
        if (empty($report)) { continue; }
        migrateReports($report, $row, $rptMap, $security);
        $rptMap['r'.$row['id']] = dbMetaSet(0, 'phreeform', $report);
        if (!empty($row['bookmarks'])) {
            $temp = explode(':', trim($row['bookmarks'], ':'));
            foreach ($temp as $admin_id) {
                $newCID = isset($map['r'.$admin_id]) ? $map['r'.$admin_id] : 0;
                if (empty($newCID)) { continue; }
                $theList[$newCID][] = intval($rptMap['r'.$row['id']]);
            }
        }
    }
    // convert the bookmarks to users meta
    dbMetaSet(0, '_PHREEFORM_ID_MAP', $rptMap); // save the user map as common meta for use later
    msgDebug("\nmigrate_phreeform theList = ".print_r($theList, true));
    if (!empty($theList)) { mapBookmarks($theList); }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."phreeform WHERE mime_type='dir'");
    dbTransactionCommit();
}

function migrateReports(&$report, $row, $rptMap, $security)
{
    if ($report->reporttype=='rpt') { 
        $gID = explode(":", $report->groupname); $report->groupname = $gID[0].":rpt";
        // Heading
        $report->headingshow = !empty($report->heading->show) ? $report->heading->show : '';
        $report->headingfont = !empty($report->heading->font) ? $report->heading->font : 'helvetica';
        $report->headingsize = !empty($report->heading->size) ? $report->heading->size : '10';
        $report->headingcolor= !empty($report->heading->color)? $report->heading->color: '#000000';
        $report->headingalign= !empty($report->heading->align)? $report->heading->align: 'L';
        // title1
        $report->title1show  = !empty($report->title1->show) ? $report->title1->show : '';
        $report->title1text  = !empty($report->title1->text) ? $report->title1->text : '%reportname%';
        $report->title1font  = !empty($report->title1->font) ? $report->title1->font : 'helvetica';
        $report->title1size  = !empty($report->title1->size) ? $report->title1->size : '10';
        $report->title1color = !empty($report->title1->color)? $report->title1->color: '#000000';
        $report->title1align = !empty($report->title1->align)? $report->title1->align: 'L';
        // Title2
        $report->title2show  = !empty($report->title2->show) ? $report->title2->show : '';
        $report->title2text  = !empty($report->title2->text) ? $report->title2->text : 'Report Generated %date%';
        $report->title2font  = !empty($report->title2->font) ? $report->title2->font : 'helvetica';
        $report->title2size  = !empty($report->title2->size) ? $report->title2->size : '10';
        $report->title2color = !empty($report->title2->color)? $report->title2->color: '#000000';
        $report->title2align = !empty($report->title2->align)? $report->title2->align: 'L';
        // Filters
        $report->filterfont  = !empty($report->filter->font) ? $report->filter->font : 'helvetica';
        $report->filtersize  = !empty($report->filter->size) ? $report->filter->size : '10';
        $report->filtercolor = !empty($report->filter->color)? $report->filter->color: '#000000';
        $report->filteralign = !empty($report->filter->align)? $report->filter->align: 'L';
        // Data
        $report->datafont    = !empty($report->data->font) ? $report->data->font : 'helvetica';
        $report->datasize    = !empty($report->data->size) ? $report->data->size : '10';
        $report->datacolor   = !empty($report->data->color)? $report->data->color: '#000000';
        $report->dataalign   = !empty($report->data->align)? $report->data->align: 'L';
        // Totals
        $report->totalfont   = !empty($report->total->font) ? $report->total->font : 'helvetica';
        $report->totalsize   = !empty($report->total->size) ? $report->total->size : '10';
        $report->totalcolor  = !empty($report->total->color)? $report->total->color: '#000000';
        $report->totalalign  = !empty($report->total->align)? $report->total->align: 'L';
    } // clean up phreebooks report format to new format
    // Page setup - Need to flatten out the fields
    $report->pagesize    = $report->page->size;
    $report->pageorient  = $report->page->orientation;
    $report->margintop   = $report->page->margin->top;
    $report->marginbottom= $report->page->margin->bottom;
    $report->marginleft  = $report->page->margin->left;
    $report->marginright = $report->page->margin->right;
    // The rest
//  $report->id         = $row['id'];
    $report->dateperiod = !empty($row['dateperiod']) ? $row['dateperiod'] : 'd';
    $report->datelist   = str_split($report->datelist);
    $report->type       = trim($report->reporttype, ':');
    $report->group_id   = $row['group_id'];
    $report->mime_type  = $row['mime_type'];
    $report->parent_id  = !empty($rptMap['r'.$row['parent_id']]) ? $rptMap['r'.$row['parent_id']] : 0;
    $report->users      = !empty($security['users']) ? $security['users'] : [0];
    $report->roles      = !empty($security['roles']) ? $security['roles'] : [0];
    $report->create_date= $row['create_date'];
    $report->last_update= $row['last_update'];
    unset($report->security, $report->groupname, $report->reporttype);
    unset($report->page, $report->heading, $report->title1, $report->title2, $report->filter, $report->data, $report->total);
    // extract the field settings
    foreach ($report->fieldlist as $key => $row) {
        if (!empty($row->settings->boxfield)) { $report->fieldlist[$key]->settings->boxfield = ['total'=>sizeof($row->settings->boxfield), 'rows'=>$row->settings->boxfield]; }
    }
    // re-format the grids
    if (!empty($report->tables))    { $report->tables    = ['total'=>sizeof($report->tables),    'rows'=>$report->tables];     }
    if (!empty($report->fieldlist)) { $report->fieldlist = ['total'=>sizeof($report->fieldlist), 'rows'=>$report->fieldlist];  }
    if (!empty($report->sortlist))  { $report->sortlist  = ['total'=>sizeof($report->sortlist),  'rows'=>$report->sortlist];   }
    if (!empty($report->grouplist)) { $report->grouplist = ['total'=>sizeof($report->grouplist), 'rows'=>$report->grouplist];  }
    if (!empty($report->filterlist)){ $report->filterlist= ['total'=>sizeof($report->filterlist),'rows'=>$report->filterlist]; }
}

/**
 * Maps the users in an array from the admin_id from the users table to the new id in the contact table
 * @param type $access
 */
function mapUsers($access='u:-1;g:-1') // u:0;g:1:2
{
    msgDebug("\nEntering mapUsers with access = ".print_r($access, true));
    if ($access=='u:-1;g:-1') { return ['users'=>[-1], 'roles'=>[-1]]; }
    $types= explode(';', $access);
    $sec  = [];
    foreach ($types as $value) {
        $temp = explode(':', $value);
        $type = array_shift($temp);
        $sec[$type] = $temp; // the users
    }
    msgDebug("\nFinished breaking string into array with sec = ".print_r($sec, true));
    $output = [];
    $uMap = dbMetaGet(0, '_ADMIN_ID_MAP');
    $rMap = dbMetaGet(0, '_ROLE_ID_MAP');
    foreach ($sec as $type => $ids) {
        if ($type=='g') { // don't map groups
            foreach ($ids as $id) { if (isset($rMap['r'.$id])) { $output['roles'][] = intval($rMap['r'.$id]); } }
        } else {
            foreach ($ids as $id) { if (isset($uMap['r'.$id])) { $output['users'][] = intval($uMap['r'.$id]); } }
        }
    }
    msgDebug("\nReturning output = ".print_r($output, true));
    return $output;
}

/**
 * Maps the bookmarks in an array from the admin_id from the users table to the new id in the contact table
 * @param type $temp
 */
function mapBookmarks($adminIDs=[], $metaKey='bookmarks_phreeform')
{
    msgDebug("\nEntering mapBookmarks with adminIDs = ".print_r($adminIDs, true));
    foreach ($adminIDs as $newID => $newRpt) {
        dbMetaSet(0, $metaKey, $newRpt, 'contacts', $newID);
    }
}

/**
 * extDocs table
 * @param type $cron
 * @return type
 */
function migrate_docs(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_docs with cntOnly = ".($cntOnly?'true':'false'));
    $table= 'extDocs';
    $chunk= 10000;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'parent_id');
    $rIDs = $theList = [];
    $pMap = ['p0'=>0]; // home folder
    $map  = dbMetaGet(0, '_ADMIN_ID_MAP');
    foreach ($rows as $row) {
        $rIDs[]  = $row['id'];
        $metaVal = $row;
        $security = mapUsers($row['security']); // turns security into array with new rID's
        $metaVal['users'] = !empty($security['users']) ? $security['users'] : [0];
        $metaVal['roles'] = !empty($security['roles']) ? $security['roles'] : [0];
        $metaVal['settings'] = json_decode($row['settings'], true);
        if (!empty($metaVal['settings']['owner'])) { // map the owner
            $owner = trim($metaVal['settings']['owner'], ':');
            $metaVal['owner'] = isset($map['r'.$owner]) ? $map['r'.$owner] : 0;
        }
        if (!empty($metaVal['settings']['google_id'])) { // map the google doc link
            $metaVal['google_id'] = $metaVal['settings']['google_id'];
        }
        $metaVal['parent_id'] = isset($pMap['p'.$row['parent_id']]) ? $pMap['p'.$row['parent_id']] : -1; // else orphaned
        unset($metaVal['id'], $metaVal['bookmarks'], $metaVal['settings']);
        $pMap['p'.$row['id']] = dbMetaSet(0, 'document', $metaVal);
        if (!empty($row['bookmarks'])) { // move bookmarks to contacts meta
            $temp = explode(':', trim($row['bookmarks'], ':'));
            msgDebug("\nExploded bookmarks = ".print_r($temp, true));
            foreach ($temp as $admin_id) {
                $newCID = isset($map['r'.$admin_id]) ? $map['r'.$admin_id] : 0;
                if (empty($newCID)) { continue; }
                $theList[$newCID][] = intval($pMap['p'.$row['id']]);
            }
        }
    }
    // convert the bookmarks to users meta
    msgDebug("\nmigrate_docs theList = ".print_r($theList, true));
    if (!empty($theList)) { mapBookmarks($theList, 'bookmarks_docs'); }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_shipping_log(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_shipping_log.");
    $table= 'extShipping';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} ($chunk records) of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $meta = $lastMeta = $rIDs = [];
    foreach ($rows as $row) {
        msgDebug("\nWorking on shipping row = ".print_r($row, true));
        $rIDs[]= $row['id'];
        $invNum= strpos($row['ref_id'], '-')>0 ? substr($row['ref_id'], 0, strrpos($row['ref_id'], '-')) : $row['ref_id'];
        $meta  = ['id'=>$row['id'], 'method_code'=>$row['method_code'],'ship_date'=>$row['ship_date'], 'deliver_date'=>$row['deliver_date'], 'store_id'=>$row['store_id'],
            'ref_num'=>$row['shipment_id'], 'invoice_num'=>$invNum, 'total_cost'=>$row['cost'], 'total_billed'=>$row['billed'], 'reconciled'=>$row['reconciled'],
            'notes'  =>$row['notes']];
        $meta['package'][] = ['tracking_id'=>$row['tracking_id'], 'cost'=>$row['cost'],
            'deliver_date'=>substr($row['deliver_date'], 0, 8), 'actual_date'=>substr($row['actual_date'], 0, 8), 'deliver_late'=>$row['deliver_late']];
        if (!empty($lastMeta) && $lastMeta['invoice_num']==$invNum) { // same invoice, add package
            $lastMeta['package'][] = ['tracking_id'=>$row['tracking_id'], 'cost'=>$row['cost'],
                'deliver_date'=>substr($row['deliver_date'], 0, 8), 'actual_date'=>substr($row['actual_date'], 0, 8), 'deliver_late'=>$row['deliver_late']];
            $lastMeta['total_cost'] += $row['cost'];
            continue;
        }
        preprocessShipping($lastMeta); // new package, write the old one
        $lastMeta = $meta; // reset shipment for next round
    }
    preprocessShipping($lastMeta); // write last package in this iterration
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

function preprocessShipping($lastMeta)
{
    if (empty($lastMeta['id'])) { return; } // first iteration is blank.
    $refID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "journal_id IN (12,15) AND invoice_num='".addslashes($lastMeta['invoice_num'])."'");
//    $lastMeta['ref_id']  = $refID; // replaces invoice num with main record id
    $lastMeta['packages']= ['total'=>sizeof($lastMeta['package']), 'rows'=>$lastMeta['package']];
    unset($lastMeta['package']);
    if (!empty($refID)) { dbMetaSet(0, 'shipment', $lastMeta, 'journal', $refID); }
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_returns(&$cron=[], $cntOnly=false) 
{
    msgDebug("\nEntering migrate_returns.");
    $table= 'extReturns';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly)    { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        $refID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "invoice_num='".addslashes($row['invoice_num'])."'");
        if (!empty($refID)) {
            $row['refID']  = $refID; // link to journal main table invoice num
            $row['ref_num']= $row['return_num'];
            $row['receive_details']= json_decode($row['receive_details'],true);
            $row['close_details']  = json_decode($row['close_details'],  true);
            unset($row['id'], $row['return_num']);
            dbMetaSet(0, 'return', $row, 'journal', $refID);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * extFixedAssets table
 * @param type $cron
 * @return type
 */
function migrate_fixed_assets(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_fixed_assets.");
    $table= 'extFixedAssets';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        $row['ref_num'] = $row['asset_num'];
        unset($row['id'], $row['asset_num']);
        dbMetaSet(0, 'fixed_asset', $row);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Map extMaint common
 * @param type $cron
 * @return type
 */
function migrate_maint(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_maint.");
    $table= 'extMaint';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        if ($row['type']=='t') { // task then put into common table
            $row['ref_num'] = $row['task_num'];
            unset($row['id'], $row['task_num'], $row['type']);
            dbMetaSet(0, 'maintenance', $row);
        } else { // journal then map into journal_main
            $main = ['journal_id'=>35, // 'period'=>calculatePeriod($row['maint_date'], false), // causes an error if the date is null
                'invoice_num'=>$row['task_num'], 'post_date'=>$row['maint_date'], 'rep_id'=>$row['user_id'],
                'store_id'=>$row['store_id'], 'contact_id_b'=>$row['contact_id'], 'attach'=>$row['attach'],
                'description'=>$row['title'], 'purch_order_id'=>$row['lead_time'], 'notes'=>$row['notes']];
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Map extTraining to journal_main
 * @param type $cron
 * @return type
 */
function migrate_training(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_training.");
    $table= 'extTraining';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        if ($row['type']=='t') { // task then put into common table
            $row['ref_num'] = $row['task_num'];
            unset($row['id'], $row['task_num'], $row['type']);
            dbMetaSet(0, 'training', $row);
        } else { // journal then map into journal_main
            $main = ['journal_id'=>34, // 'period'=>calculatePeriod($row['train_date'], false), // causes an error if the date is null
                'invoice_num'=>$row['task_num'], 'post_date'=>$row['train_date'], 'rep_id'=>$row['user_id'],
                'store_id'=>$row['store_id'], 'contact_id_b'=>$row['contact_id'], 'attach'=>$row['attach'],
                'description'=>$row['title'], 'purch_order_id'=>$row['lead_time'], 'notes'=>$row['notes']];
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_prod_tasks(&$cron=[], $cntOnly=false) // Map srvBuilder_tasks to common_meta
{
    msgDebug("\nEntering migrate_prod_tasks.");
    $table= 'srvBuilder_tasks';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly)   { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)){ $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = $taskMap = [];
    foreach ($rows as $row) {
        $rIDs[] = $rID = $row['id'];
        unset($row['id']);
        $newID = dbMetaSet(0, 'production_task', $row);
        $taskMap['r'.$rID] = $newID;
    }
    dbMetaSet(0, '_PROD_TASK_ID_MAP', $taskMap); // save the user map as common meta for use later
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_prod_jobs(&$cron=[], $cntOnly=false) // Map srvBuilder_jobs to inventory_meta
{
    msgDebug("\nEntering migrate_prod_jobs.");
    $table= 'srvBuilder_jobs';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step

    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    $taskMap = dbMetaGet(0, '_PROD_TASK_ID_MAP');
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        if (empty($row['sku_id'])) { continue; } // remove orphans
        unset($row['type'], $row['id']);
        $row['steps'] = json_decode($row['steps'], true);
        foreach ($row['steps'] as $idx => $step) { // field $row['steps'] - {"1":{"task_id":"1"},"2":{"task_id":"15"},"3":{"task_id":"8"},"4":{"task_id":"7"},"5":{"task_id":"9"},"6":{"task_id":"30"}}
            $row['steps'][$idx]['task_id'] = isset($taskMap['r'.$step['task_id']]) ? $taskMap['r'.$step['task_id']] : $step['task_id'];
        }
        $meta = dbMetaGet(0, 'production_job', 'inventory', $row['sku_id']);
        $invID= metaIdxClean($meta); // check for duplicates
        if (!empty($invID)) { msgAdd("Duplicate WO Job for SKU record ID = {$row['sku_id']} and title = ".print_r($row['title'], true)); continue; }
        dbMetaSet(0, 'production_job', $row, 'inventory', $row['sku_id']);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_prod_journal(&$cron=[], $cntOnly=false) // Map srvBuilder_journal to journal_main
{
    msgDebug("\nEntering migrate_prod_journal.");
    $table= 'srvBuilder_journal';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    $taskMap = dbMetaGet(0, '_PROD_TASK_ID_MAP');
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        $main = [
            'journal_id'   => 32,
//          'period'       => calculatePeriod($row['create_date'], false), // causes an error if the date is null
            'closed'       => $row['closed'],
            'invoice_num'  => $row['sb_ref'],
            'post_date'    => $row['create_date'],
            'terminal_date'=> $row['due_date'],
            'closed_date'  => $row['close_date'],
            'rep_id'       => $row['job_id'], // template meta reference 
            'store_id'     => $row['store_id'],
            'total_amount' => $row['qty'],
            'description'  => $row['title'],
//          'recur_id'     => $row['sku_id'], // inventory db id field
            'notes'        => $row['notes']];
        $mID = dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
        $item= ['ref_id'=>$mID, 'qty'=>$row['qty'], 'sku'=>dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id={$row['sku_id']}"), 'post_date'=> $row['create_date']];
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', $item);
        if (empty($row['steps'])) { $row['steps'] = []; }
        foreach ($row['steps'] as $idx => $step) { // field $row['steps'] - {"1":{"step":1,"task_id":"1","mfg":"0","mfg_id":0,"qa":"0","qa_id":0,"data_entry":"0","erp_entry":"0","admin_id":"18","complete":"1"},"2":{"step":2,"task_id":"33","mfg":"1","mfg_id":17,"qa":"0","qa_id":0,"data_entry":"0","erp_entry":"0","admin_id":"18","complete":"1","mfg_date":"2016-01-05 14:30:46"},"3":{"step":3,"task_id":"2","mfg":"1","mfg_id":17,"qa":"0","qa_id":0,"data_entry":"1","erp_entry":"0","admin_id":"18","complete":"1","mfg_date":"2016-01-05 14:31:04","data_value":"72:150629A"},"4":{"step":4,"task_id":"12","mfg":"1","mfg_id":17,"qa":"0","qa_id":0,"data_entry":"0","erp_entry":"0","admin_id":"18","complete":"1","mfg_date":"2016-01-05 14:31:10"},"5":{"step":5,"task_id":"34","mfg":"1","mfg_id":17,"qa":"0","qa_id":0,"data_entry":"1","erp_entry":"0","admin_id":"18","complete":"1","mfg_date":"2016-01-05 14:31:20","data_value":"pass"},"6":{"step":6,"task_id":"8","mfg":"0","mfg_id":0,"qa":"0","qa_id":0,"data_entry":"1","erp_entry":"0","admin_id":"18","complete":"1","data_value":"1602"},"7":{"step":7,"task_id":"9","mfg":"0","mfg_id":0,"qa":"0","qa_id":0,"data_entry":"0","erp_entry":"0","admin_id":"18","complete":"1"},"8":{"step":8,"task_id":"30","mfg":"0","mfg_id":0,"qa":"1","qa_id":18,"data_entry":"0","erp_entry":"1","admin_id":"18","complete":"1","qa_date":"2016-01-05 14:39:41"}}
            $row['steps'][$idx]['task_id'] = isset($taskMap['r'.$step['task_id']]) ? $taskMap['r'.$step['task_id']] : $step['task_id'];
        }
        if (!empty($row['steps'])) { dbMetaSet(0, 'production_steps', $row['steps'], 'journal', $mID); }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Map extISO9001 to journal_main
 * @param type $cron
 * @return type
 */
function migrate_quality(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_quality.");
    $table= 'extISO9001';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    $userMap = dbMetaGet(0, '_ADMIN_ID_MAP');
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        if ($row['type']=='p') { // objective so put into common table
            $meta = ['ref_num'=>$row['qa_num'],     'title'      =>$row['ref_qa_num'],   'closed'     =>$row['closed'],                    'status'   =>$row['status'],
                'entered_by'  =>$row['entered_by'], 'date_target'=>$row['creation_date'],'date_actual'=>$row['close_start_date'],            'obj_desc' =>$row['issue_notes'],
                'obj_test'    =>$row['audit_notes'],'obj_result' =>$row['action_notes'], 'closed_by'  =>$userMap['r'.$row['close_end_by']],'dgObjData'=>json_decode($row['notes'], true)];
            dbMetaSet(0, 'quality_objective', $meta);
        } else { // type=='c' (ticket) journal then map into journal_main as tickets
            unset($row['id']);
            $main = [
                'journal_id'    => 30,
                'invoice_num'   => $row['qa_num'],
                'closed'        => $row['closed'],
                'printed'       => $row['status'],
                'waiting'       => $row['preventable'],
                'post_date'     => $row['creation_date'],
                'terminal_date' => $row['action_date'],
                'closed_date'   => $row['close_end_date'],
                'rep_id'        => isset($userMap['r'.$row['requested_by']]) ? $userMap['r'.$row['requested_by']] : 0,
                'primary_name_b'=> $row['contact_name'],
                'email_b'       => $row['email'],
                'telephone1_b'  => $row['telephone'],
                'attach'        => $row['attach'],
                'description'   => $row['issue_notes'],
                'purch_order_id'=> $row['invoice_num']]; // this is the skuID, probably should not be here
            $mID  = dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
            $meta = [
                'entered_by'        => $userMap['r'.$row['requested_by']],
                'contact_id'        => $row['contact_id'],
                'contact_notes'     => $row['contact_notes'],
                'analyze_start_by'  => $userMap['r'.$row['requested_by']],
                'analyze_start_date'=> $row['analyze_start_date'],
                'analyze_end_by'    => $userMap['r'.$row['requested_by']],
                'analyze_end_date'  => $row['analyze_end_date'],
                'repair_start_by'   => $userMap['r'.$row['requested_by']],
                'repair_start_date' => $row['repair_start_date'],
                'repair_end_by'     => $userMap['r'.$row['requested_by']],
                'repair_end_date'   => $row['repair_end_date'],
                'audit_start_by'    => $userMap['r'.$row['requested_by']],
                'audit_start_date'  => $row['audit_start_date'],
                'audit_end_by'      => $userMap['r'.$row['requested_by']],
                'audit_end_date'    => $row['audit_end_date'],
                'close_start_by'    => $userMap['r'.$row['requested_by']],
                'close_start_date'  => $row['close_start_date'],
                'close_end_by'      => $userMap['r'.$row['requested_by']],
                'notes'             => $row['notes'],
                'action_by'         => $userMap['r'.$row['requested_by']],
                'action_notes'      => $row['action_notes'],
                'ref_qa_num'        => $row['ref_qa_num'],
                'audit_notes'       => $row['audit_notes']];
            dbMetaSet(0, 'qa_ticket', $meta, 'journal', $mID);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 *
 * @param type $cron
 * @return type
 */
function migrate_qual_audit(&$cron=[], $cntOnly=false) // Map extISO9001Audit to journal_main
{
    msgDebug("\nEntering migrate_qual_audit.");
    $table= 'extISO9001Audit';
    $chunk= 200;
    $cnt  = dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false);
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        if ($row['type']=='t') { // task then put into common table
            $row['ref_num'] = $row['task_num'];
            unset($row['id'], $row['task_num'], $row['type'], $row['audit_date']); // probably a lot more but this will keep everything for now.
            dbMetaSet(0, 'quality_audit', $row);
        } else { // journal then map into journal_main
            $main = [
                'journal_id'    => 31,
//              'period'        => calculatePeriod($row['due_date'], false), // causes an error when due_date is null or 0000-00-00
                'invoice_num'   => $row['task_num'],
                'post_date'     => $row['due_date'],
                'terminal_date' => $row['due_date'],
                'closed_date'   => $row['audit_date'],
                'closed'        => $row['inactive'],
                'rep_id'        => $row['user_id'],
                'store_id'      => $row['store_id'],
                'contact_id_b'  => $row['contact_id'],
                'attach'        => $row['attach'],
                'description'   => $row['title'],
                'purch_order_id'=> $row['lead_time'],
                'notes'         => $row['notes']];
            dbWrite(BIZUNO_DB_PREFIX.'journal_main', $main);
        }
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Map CRMPromos to common_meta
 * @param type $cron
 * @return type
 */
function migrate_crm_promos(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_crm_promos.");
    $table= 'crmPromos';
    $chunk= 200;
    $cnt  = dbTableExists(BIZUNO_DB_PREFIX.$table) ? dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false) : 0;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        unset($row['id']);
        dbMetaSet(0, 'crm_promotion', $row);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * For now, crmPromos_history will now only be kept in the audit log.
 * @param type $cron
 * @return type
 */
function migrate_crm_promoHist(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_crm_promoHist.");
    $table= 'crmPromos_history';
    $chunk= 200;
    $cnt  = dbTableExists(BIZUNO_DB_PREFIX.$table) ? dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false) : 0;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";

    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) { $rIDs[] = $row['id']; }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/**
 * Move edi_log to journal_meta table
 * @param type $cron
 * @return type
 */
function migrate_edi_log(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_edi_log.");
    $table= 'edi_log';
    $chunk= 200;
    $cnt  = dbTableExists(BIZUNO_DB_PREFIX.$table) ? dbGetValue(BIZUNO_DB_PREFIX.$table, 'COUNT(*) AS cnt', '', false) : 0;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']+=ceil($cnt/$chunk); $cron['ttlRecord']+=$cnt; return; }
    if (empty($cnt)) { $cron['curStep']++; return; } // reset for next step
    $cron['curBlk']++;
    if (empty($cron['ttlBlk'])) { $cron['ttlBlk'] = ceil($cnt/$chunk); }
    $cron['msg'] = "Processing Block {$cron['curBlk']} [$chunk records] of {$cron['ttlBlk']} blocks from table: $table";
    // Let's go
    dbTransactionStart();
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.$table, '', 'id', '', $chunk);
    $rIDs = [];
    foreach ($rows as $row) {
        $rIDs[] = $row['id'];
        $row['ref_num'] = $row['control_num'];
        unset($row['id'], $row['next_edi_num']);
        if (empty($row['main_id'])) { continue; } // remove orphans
        dbMetaSet(0, "edi_spec_{$row['spec']}", $row, 'journal', $row['main_id']);
    }
    if (!empty($rIDs)) { dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."$table WHERE id IN (".implode(',', $rIDs).")"); }
    dbTransactionCommit();
}

/*
 * Migrates the dashboards to the new format and updates reportIDs, userIDs, and roleIDs
 */
function migrate_dash_users(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_dash_users.");
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=1; return; }

    // Let's go
    dbTransactionStart();
    $mapRpts = dbMetaGet(0, '_PHREEFORM_ID_MAP'); msgDebug("\nFor reference: Reports map = ".print_r($mapRpts, true));
    $mapRoles= dbMetaGet(0, '_ROLE_ID_MAP');      msgDebug("\nFor reference: Roles map = "  .print_r($mapRoles, true));
    $mapUsers= dbMetaGet(0, '_ADMIN_ID_MAP');     msgDebug("\nFor reference: Users map = "  .print_r($mapUsers, true));
    $rows    = dbGetMulti(BIZUNO_DB_PREFIX.'contacts_meta', "meta_key LIKE 'dashboard_%'");
    foreach ($rows as $row) {
        $metaVal = json_decode($row['meta_value'], true);
        msgDebug("\nWorking with data = ".print_r($metaVal, true));
        $data = migrateDashData($metaVal, $row['ref_id']);
        msgDebug("\nReady to write data of length: ".strlen(json_encode($data)));
        dbWrite(BIZUNO_DB_PREFIX.'contacts_meta', ['meta_value'=>json_encode($data)], 'update', "id={$row['id']}");
    }
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

function migrateDashData($data, $refID)
{
    $mapRpts  = dbMetaGet(0, '_PHREEFORM_ID_MAP');
    foreach ($data as $dashID => $values) {
        $newVals = [];
        msgDebug("\nProcessing dashID: $dashID and and values = ".print_r(json_encode($values), true));
        switch ($dashID) {
            case 'company_links':
                if (empty($values['opts']['data'])) { break; } // skip if no data
                $bizList= dbMetaGet(0, $dashID);
                $rID    = metaIdxClean($bizList);
                if (!empty($bizList)) { break; } // only do this once
                foreach ((array)$values['opts']['data'] as $title => $url) { $newVals['data'][] = ['title'=>$title, 'url'=>$url]; }
                msgDebug("\nWriting meta for $dashID: ".print_r($newVals, true));
                dbMetaSet($rID, $dashID, $newVals);
                $data[$dashID]['opts'] = [];
                break;
            case 'company_notes':
            case 'company_to_do':
                if (empty($values['opts']['data'])) { break; } // skip if no data
                $bizList= dbMetaGet(0, $dashID);
                $rID    = metaIdxClean($bizList);
                if (!empty($bizList)) { break; } // only do this once
                foreach ((array)$values['opts']['data'] as $title) { $newVals['data'][] = ['title'=>$title]; }
                msgDebug("\nWriting meta for $dashID: ".print_r($newVals, true));
                dbMetaSet($rID, $dashID, $newVals);
                $data[$dashID]['opts'] = [];
                break;
            case 'favorite_reports':
                if (empty($values['opts']['data'])) { $values['opts']['data'] = []; }
                foreach (array_keys((array)$values['opts']['data']) as $rptID) {
                    if (empty($rptID)) { continue; }
                    $newVals[] = $mapRpts['r'.$rptID];
                }
                $data[$dashID]['opts']['data'] = $newVals;
                break;
            case 'launchpad': // currently has the list of security ID's, BUT SOME HAVE CHANGED!
                break;
            case 'my_links':
                msgDebug("\nEntering my_links with strlen values[opts][data] = ".strlen(json_encode($values['opts']['data'])));
                if (empty($values['opts']['data'])) { break; }
                foreach ($values['opts']['data'] as $title => $url) { $newVals[] = ['title'=>$title, 'url'=>$url]; }
                $data[$dashID]['opts']['data'] = $newVals;
                break;
            case 'my_notes':
            case 'my_messages':
            case 'my_to_do';
                if (empty($values['opts']['data'])) { break; }
                foreach ($values['opts']['data'] as $title) { $newVals[] = ['title'=>$title]; }
                $data[$dashID]['opts']['data'] = $newVals;
                break;
            case 'reminder':
                $rmdrC= dbMetaGet(0, 'reminder_list', 'contacts', $refID);
                $cIdx = metaIdxClean($rmdrC);
                if (!empty($cIdx)) { break; } // only do this once per user. Causes duplicates.
                foreach ((array)$values['opts']['current'] as $row) { $current[] = ['title'=>$row['title'], 'date'=>$row['date']]; }
                dbMetaSet($cIdx, 'reminder_list', $current, 'contacts', $refID);
                foreach ((array)$values['opts']['source'] as $row) { 
                    $newMeta = ['title'=>$row['title'], 'recur'=>$row['recur'], 'dateStart'=>$row['dateStart'], 'dateNext'=>$row['dateNext']];
                    msgDebug("\nWriting meta for $dashID: ".print_r($newVals, true));
                    dbMetaSet(0, 'reminder', $newMeta, 'contacts', $refID);
                }
                $data[$dashID]['opts'] = [];
                break;
            default: // Nothing
        }
    }
    return $data;
}

function migrate_misc(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_misc.");
    global $bizunoMod;
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=0; return; } // unknown so make it zero
    
    // Let's go
    dbTransactionStart();
    // Tabs
    $modTabs = $tabMap = [];
    foreach ($bizunoMod as $module => $settings) {
        if (empty($settings['tabs'])) { msgDebug("\nNo tabs for module: $module"); continue; }
        $modTabs[$module] = $module; // save the module to remove from the configuration cache
        foreach ($settings['tabs'] as $tabID => $row) { 
            $newID = dbMetaSet(0, 'tabs', ['table'=>$row['table_id'], 'title'=>$row['title'], 'order'=>$row['sort_order']]);
            $tabMap['r'.$tabID] = $newID;
        }
    }
    dbMetaSet(0, '_TAB_ID_MAP', $tabMap); // save the role map as common meta for use later
    foreach ($modTabs as $module) { clearModuleCache($module, 'tabs'); } // clear the cache settings
    mapTabs('contacts');
    mapTabs('inventory');
    
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

function migrate_map(&$cron=[], $cntOnly=false)
{
    msgDebug("\nEntering migrate_map");
    if ($cntOnly) { $cron['ttlSteps']++; $cron['ttlBlk']++; $cron['ttlRecord']+=0; return; } // unknown so make it zero
    
    // Let's go
    $userMap = dbMetaGet(0, '_ADMIN_ID_MAP'); // get the user map
    metaIdxClean($userMap);
    msgDebug("\nUser map = ".print_r($userMap, true));
    $contMap = dbMetaGet(0, '_CONTACT_MAP');
    msgDebug("\nContact map = ".print_r($contMap, true));
    metaIdxClean($contMap);
    // Does not fix any meta data from extensions.
    dbTransactionStart();
    foreach ($userMap as $key => $newIdx) {
        $oldIdx = substr($key, 1);
        if (!isset($contMap[$key])) { continue; } //  rep_id
        executeMapSQL("UPDATE ".BIZUNO_DB_PREFIX."audit_log SET user_id=$newIdx WHERE user_id=$oldIdx");
        executeMapSQL("UPDATE ".BIZUNO_DB_PREFIX."contacts SET rep_id=$newIdx WHERE rep_id={$contMap[$key]}");
        executeMapSQL("UPDATE ".BIZUNO_DB_PREFIX."contacts_log SET entered_by=$newIdx WHERE entered_by={$contMap[$key]}");
        executeMapSQL("UPDATE ".BIZUNO_DB_PREFIX."journal_main SET admin_id=$newIdx WHERE admin_id=$oldIdx");
        executeMapSQL("UPDATE ".BIZUNO_DB_PREFIX."journal_main SET rep_id=$newIdx WHERE rep_id={$contMap[$key]}");
    }
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}
function executeMapSQL($sql)
{
    msgDebug("\nExecuting sql = $sql");
    dbGetResult($sql);
}
/**
 * Final cleanup and drop tables
 */
function migrate_rm_tables_pt1(&$cron=[])
{
    msgDebug("\nEntering migrate_rm_tables_pt1.");
    dbTransactionStart();
    // Drop the tables
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'current_status');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'data_security');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'inventory_assy_list');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'inventory_prices');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'inventory_cogs_usage');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'inventory_cogs_owed');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'sales_tax');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'tax_rates');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'phreeform');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'roles');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'users');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'users_profiles');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extShipping');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extReturns');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extFixedAssets');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extDocs');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extMaint');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extQuality');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extTraining');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'srvBuilder_jobs');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'srvBuilder_journal');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'srvBuilder_tasks');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'crmProjects');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'crmPromos');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'crmPromos_history');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extISO9001');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'extISO9001Audit');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'edi_log');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'phreehelp');
    dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'toolXlate');
    
    // move some folders to new locations
    if (file_exists(BIZUNO_DATA.'data/extISO9001/audits')) { rename(BIZUNO_DATA.'data/extISO9001/audits',  BIZUNO_DATA.'data/quality/audits'); }
    if (file_exists(BIZUNO_DATA.'data/extISO9001/uploads')){ rename(BIZUNO_DATA.'data/extISO9001/uploads', BIZUNO_DATA.'data/quality/tickets'); }
    if (file_exists(BIZUNO_DATA.'data/extQuality/uploads')){ rename(BIZUNO_DATA.'data/extQuality/uploads', BIZUNO_DATA.'data/quality/objectives'); }
    if (file_exists(BIZUNO_DATA.'data/extReturns'))        { rename(BIZUNO_DATA.'data/extReturns',         BIZUNO_DATA.'data/phreebooks/returns'); }
    if (file_exists(BIZUNO_DATA.'data/srvBuilder'))        { rename(BIZUNO_DATA.'data/srvBuilder',         BIZUNO_DATA.'data/inventory/builds'); }
    if (file_exists(BIZUNO_DATA.'data/extShipping'))       { rename(BIZUNO_DATA.'data/extShipping',        BIZUNO_DATA.'data/shipping'); }
    if (file_exists(BIZUNO_DATA.'data/extDocs'))           { rename(BIZUNO_DATA.'data/extDocs',            BIZUNO_DATA.'data/docs'); }
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

function migrate_rm_tables_pt2(&$cron=[])
{
    msgDebug("\nEntering migrate_rm_tables_pt2.");
    dbTransactionStart();
    // update the contacts tables with the new type system
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_b='1' WHERE type='b'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_c='1' WHERE type='c'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_e='1' WHERE type='e'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_i='1' WHERE type='i'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_j='1' WHERE type='j'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_u='1' WHERE type='u'");
    dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts` SET ctype_v='1' WHERE type='v'");
    dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `type` `xtype` CHAR(8) NOT NULL DEFAULT 'c' COMMENT 'type:hidden;tag:Type;order:4';");
    dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."contacts` CHANGE `last_update` `date_last` DATE DEFAULT NULL COMMENT 'type:date;tag:DateLastEntry;order:72';");
    // Some more cleanup
    $priceMap = getMetaCommon('_PRICE_MAP');
    msgDebug("\nPrice map = ".print_r($priceMap, true));
    foreach ($priceMap as $key => $priceID) {
        $srcID = substr($key, 1);
        $destID= !empty($priceID) ? $priceID : 0;
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."contacts`  SET price_sheet  =$destID WHERE price_sheet  =$srcID");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."inventory` SET price_sheet_c=$destID WHERE price_sheet_c=$srcID");
        dbGetResult("UPDATE `".BIZUNO_DB_PREFIX."inventory` SET price_sheet_v=$destID WHERE price_sheet_v=$srcID");
    }
    // delete contacts_meta where meta_key=crm_project AND ref_id=0 // orphaned
    
    dbTransactionCommit();
    $cron['curStep']++;
    $cron['curBlk']++;
}

/**
 * Takes an XML input string and parses into an object
 * @param string $xmlReport - raw xml report to parse to an object
 * @return object - parser report
 */
function phreeFormXML2Obj($xmlReport)
{
//    msgDebug("\nstarted with = ".print_r($xmlReport, true));
    // fix reports coming out of db
    // deprecate this line after WordPress Update from initial release
    if (strpos($xmlReport, '<?xml') !== 0 || strpos($xmlReport, '<PhreeformReport>') === false) { $xmlReport = '<PhreeformReport>'.$xmlReport.'</PhreeformReport>'; }
    $report = parseXMLstring($xmlReport);
//    msgDebug("\nparsed report = ".print_r($report, true));
    if (isset($report->PhreeformReport) && is_object($report->PhreeformReport)) { $report= $report->PhreeformReport; } // remove container tag, if present
    if (isset($report->tables)    && is_object($report->tables))    { $report->tables    = [$report->tables]; }
    if (isset($report->fieldlist) && is_object($report->fieldlist)) { $report->fieldlist = [$report->fieldlist]; }
    if (isset($report->grouplist) && is_object($report->grouplist)) { $report->grouplist = [$report->grouplist]; } // if only one entry, make it an array of length one
    if (isset($report->sortlist)  && is_object($report->sortlist))  { $report->sortlist  = [$report->sortlist]; }
    if (isset($report->filterlist)&& is_object($report->filterlist)){ $report->filterlist= [$report->filterlist]; }
    if (isset($report->fieldlist)) { foreach ($report->fieldlist as $key => $field) {
        if (isset($field->settings->boxfield) && is_object($field->settings->boxfield)) {
            $report->fieldlist[$key]->settings->boxfield = [$report->fieldlist[$key]->settings->boxfield];
        }
    } }
//    msgDebug("\nAfter cleaning parsed report = ".print_r($report, true));
    return $report;
}

/**
 * Maps the old tab ID's to the new tab meta ID's
 * @param string $table - Choices are 'inventory' and 'contacts'
 */
function mapTabs($table)
{
    $tabMap = dbMetaGet(0, '_TAB_ID_MAP');
    msgDebug("\nRead tab map = ".print_r($tabMap, true));
    metaIdxClean($tabMap);
    // read the structure of the table
    $struc = dbLoadStructure(BIZUNO_DB_PREFIX.$table);
    msgDebug("\nRead structure = ".print_r($struc, true));
    // [comment] => order:10;tab:24;label:Search Code;tag:SearchCode;group:General
    // {"r7":"617","r24":"618","r25":"619","r26":"620","r27":"621"}
    foreach ($struc as $props) {
        foreach ($tabMap as $key => $newIdx) {
            $oldIdx = substr($key, 1);
            if (strpos($props['comment'], "tab:{$oldIdx}") !== false) {
                msgDebug("\nFound a hit, oldIdx = $oldIdx and newID = $newIdx");
                $comment = str_replace("tab:{$oldIdx}", "tab:{$newIdx}", $props['comment']);
                msgDebug("\nWriting new comment = $comment");
                $default = in_array($props['default'], ['CURRENT_TIMESTAMP']) ? $props['default'] : "'{$props['default']}'"; // don't quote mysql reserved words
                $params  = $props['dbType'].' ';
                $params .= $props['null']=='NO'      ? 'NOT NULL '         : 'NULL ';
                $params .= !empty($props['default']) ? "DEFAULT $default " : '';
                $params .= $props['extra']           ? $props['extra'].' ' : '';
                msgDebug("\nWriting new db column = "."ALTER TABLE `".BIZUNO_DB_PREFIX."$table` CHANGE `{$props['field']}` `{$props['field']}` $params COMMENT '$comment'");
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."$table` CHANGE `{$props['field']}` `{$props['field']}` $params COMMENT '$comment'");
            }
        }
    }
}
