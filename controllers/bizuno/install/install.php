<?php
/*
 * Module Common - Install methods
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
 * @version    7.x Last Update: 2025-11-21
 * @filesource /controllers/bizuno/install/install.php
 */

namespace bizuno;

class bizInstall // Checking users:
{
    public  $moduleID = 'common';
    public $refs = [
            'next_audit_num'   => 'QA00001',
            'next_cproj_num'   => 'PJ00001',
            'next_cust_id_num' => 'C000001',
            'next_edi_num'     => 'EDI0000001',
            'next_fxdast_num'  => 'FA00001',
            'next_maint_num'   => 'PM00001',
            'next_promo_num'   => 'PR00001',
            'next_qaobj_num'   => 'QO00001',
            'next_ref_j2'      => 'GL00001',
            'next_ref_j3'      => 'VQ00001',
            'next_ref_j4'      => 'VPO00001',
            'next_ref_j7'      => 'VCM00001',
            'next_ref_j9'      => 'QU00001',
            'next_ref_j10'     => 'SO00001',
            'next_ref_j12'     => 'INV00001',
            'next_ref_j13'     => 'CM00001',
            'next_ref_j18'     => 'INV00001',
            'next_ref_j20'     => 'CK00001',
            'next_return_num'  => 'RMA00001',
            'next_shipment_num'=> 'SH00001',
            'next_ticket_num'  => 'QT00001',
            'next_training_num'=> 'TR00001',
            'next_vend_id_num' => 'V000001',
            'next_wo_num'      => 'WO00001'];

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Installs Bizuno
     * @global type $io
     * @param array $layout
     */
    public function installBizuno(&$layout=[])
    {
        global $io, $bizunoMod;
        if (!defined('BIZUNO_DATA') || !defined('BIZUNO_DB_PREFIX')) { die ("Constants are not defined, proper business is not set."); }
        ini_set('memory_limit','1024M'); // temporary
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/bizuno/settings.php',    'bizunoSettings');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/admin.php',   'phreebooksAdmin');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/currency.php','phreebooksCurrency');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreeform/functions.php','phreeformImport', 'function');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'model/mail.php',                     'bizunoMailer');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'model/manager.php',                  'mgrJournal');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'model/registry.php',                 'bizRegistry');
        if (!$this->installBizunoPre()) { return; } // pre-install for portal
        $userID = 1; // Same with user
        setUserCache('profile', 'userID',  $userID); // Local user ID
        $usrEmail = getUserCache('profile', 'email');
        if (empty($usrEmail)) { return msgAdd('User is not logged in!'); }
        setUserCache('profile', 'biz_title',clean('biz_title','text',   'post'));
        setUserCache('profile', 'language', clean('biz_lang', 'text',   'post'));
        setUserCache('profile', 'chart',    clean('biz_chart','text',   'post'));
        setUserCache('profile', 'first_fy', clean('biz_fy',   'integer','post'));
        $curISO = clean('biz_currency', 'text', 'post');
        setModuleCache('phreebooks', 'currency', 'defISO', $curISO); // temp set currency for table loading and PhreeBooks intialization
        // error check title
        if (strlen(getUserCache('profile', 'biz_title')) < 3) { return msgAdd('Your business name needs to be from 3 to 15 characters!'); }
        // Here we go, ready to install
        $bAdmin  = new bizunoSettings();
        msgDebug("\n  Creating the company directory");
        msgDebug("\nReady to install, bizID = ".getUserCache('business', 'bizID')." and BIZUNO_DATA = ".BIZUNO_DATA);
// This line is probably not needed except just to make sure the data folder exists and is writeable
        $io->validatePath('index.php'); // create the data folder
        // ready to install, tables first
        if (dbTableExists(BIZUNO_DB_PREFIX.'journal_main')) { return msgAdd("Cannot install, the database has tables present. Aborting!"); }
        $tables  = [];
        require(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/tables.php'); // get the tables
        dbInstallTables($tables);
        // set some meta to force the position of the row ID's
        $roleID   = 10; // needs to sync with phreesoft.com for new installs or menus don't show
        $roleMeta = ['title'=>lang('administrator'), 'administrate'=>1, 'security'=>[]];
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 1, 'meta_key'=>'bizuno_cache_expires','meta_value'=> 0  ]); // cache timestamp to trigger reload
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 2, 'meta_key'=>'chart_of_accounts',   'meta_value'=>'{}']); // Your chart of accounts
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 3, 'meta_key'=>'methods_totals',      'meta_value'=>'{}']); // Collected list of Phrebooks total methods
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 4, 'meta_key'=>'methods_gateways',    'meta_value'=>'{}']); // Collected list of payment gateways
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 5, 'meta_key'=>'methods_carriers',    'meta_value'=>'{}']); // Collected list of shipping carriers
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 6, 'meta_key'=>'methods_funnels',     'meta_value'=>'{}']); // Collected lsit of API funnels 
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 7, 'meta_key'=>'dashboards',          'meta_value'=>'{}']); // Collected list of dashbaords from all modules
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 8, 'meta_key'=>'phreeform_cache',     'meta_value'=>'[]']); // Cache to store phreeform reports and forms to speed things up
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=> 9, 'meta_key'=>'bizuno_refs',         'meta_value'=>'[]']); // Phreebooks references for journal entries
        dbWrite(BIZUNO_DB_PREFIX.'common_meta', ['id'=>$roleID,'meta_key'=>'bizuno_role',     'meta_value'=>json_encode($roleMeta)]);
        // insert a record into the contacts table, use email as the short name for now
        msgDebug("\nFinished installing modules, next up, setting first user.");
        $cID     = dbWrite(BIZUNO_DB_PREFIX.'contacts', ['ctype_u'=>'1', 'email'=>$usrEmail, 'primary_name'=>$usrEmail, 'short_name'=>$usrEmail, 'first_date'=>biz_date()]);
        dbMetaSet(0, 'user_profile', ['email'=>$usrEmail, 'role_id'=>$roleID], 'contacts', $cID);
        setUserCache('profile', 'userRole', $roleID);
        $user    = ['userID'=>$userID, 'psID'=>getUserCache('profile', 'psID'), 'userEmail'=>getUserCache('profile', 'email'), 'userRole'=>$roleID];
        setUserCookie($user);
        // Load PhreeBooks defaults
        $pbAdmin = new phreebooksAdmin();
        $pbAdmin->installFirst(); // load the chart and initialize PhreeBooks stuff
        // now Modules
        setUserCache('role', 'administrate', 1); // set security so modules load
        $this->installReports();
        // build the registry, i.e. set the module cache
        $cur     = new phreebooksCurrency();
        $registry= new bizRegistry();
        $registry->initRegistry(getUserCache('profile', 'email'), getUserCache('business', 'bizID'), 0);
        msgDebug("\nFinished building registry with bizunoMod = ".print_r($bizunoMod, true));
        setModuleCache('phreebooks', 'currency', false, ['defISO'=>$curISO, 'iso'=>[$curISO =>$cur->currencySettings($curISO)]]);
        $this->initDashboards($cID, $bAdmin->notes); // create some starting dashboards
        $this->installRefs();  // Pre set the references for the journal entries
        $company = getModuleCache('bizuno', 'settings', 'company'); // set the business title and id
        $company['id'] = $company['primary_name'] = clean('biz_title', 'text', 'post');
        setModuleCache('bizuno', 'settings', 'company', $company);
        $locale  = getModuleCache('bizuno', 'settings', 'locale'); // set the timezone
        $locale['timezone'] = clean('biz_timezone', 'text', 'post');
        setModuleCache('bizuno', 'settings',  'locale',  $locale);
        setModuleCache('bizuno', 'properties','version', MODULE_BIZUNO_VERSION);
        msgLog(lang('user_login')." ".getUserCache('profile', 'email'));
        bizCacheExpClear(); // clear the cache so the next reload sets everything up properly
        $GLOBALS['BIZUNO_INSTALL_CID'] = $cID; // Set some globals for followup installations
        $layout = ['type'=>'guest','jsReady'=>['reload'=>"location.reload();"]];
    }
 
   /**
     * Pre-install script for this host
     */
    private function installBizunoPre()
    {
        global $io;
        $htaccess = '# secure uploads directory
<Files ~ ".*\..*">
	Order Allow,Deny
	Deny from all
</Files>
<FilesMatch "\.(css|jpg|jpeg|jpe|gif|png|tif|tiff)$">
	Order Deny,Allow
	Allow from all
</FilesMatch>';
        // write the file to the WordPress Bizuno data folder.
        $io->fileWrite($htaccess, '.htaccess', false);
        return true;
    }

    /**
     * Populates the home page dashboard for the admin
     * @param integer $admin_id - current install user database record id
     * @param array $my_to_do - list of action items that is generated during the install
     */
    private function initDashboards($cID, $my_to_do=[])
    {
//      $setCLink = ['data'=>['PhreeSoft Biz School'=>'https://www.phreesoft.com/biz-school/']]; // company links presets
        $setLnch  = ['inv_mgr','mgr_c','mgr_v','j6_mgr','j12_mgr','admin']; // launchpad link presets
        $panels['quick_start']     = ['col'=>0,'row'=>0,'dashboard_id'=>'quick_start'];
        $panels['todays_j12']      = ['col'=>0,'row'=>1,'dashboard_id'=>'todays_j12',      'settings'=>json_encode([])];
        $panels['todays_j06']      = ['col'=>0,'row'=>2,'dashboard_id'=>'todays_j06',      'settings'=>json_encode([])];
        $panels['my_to_do']        = ['col'=>1,'row'=>0,'dashboard_id'=>'my_to_do',        'settings'=>json_encode(['data'=>$my_to_do])];
        $panels['favorite_reports']= ['col'=>1,'row'=>1,'dashboard_id'=>'favorite_reports','settings'=>json_encode(['data'=>[]])];
//      $panels['daily_tip']       = ['col'=>1,'row'=>2,'dashboard_id'=>'daily_tip'];
        $panels['launchpad']       = ['col'=>2,'row'=>0,'dashboard_id'=>'launchpad',       'settings'=>json_encode($setLnch)];
        $panels['company_links']   = ['col'=>2,'row'=>1,'dashboard_id'=>'company_links'];//'settings'=>json_encode($setCLink)];
        $panels['todays_audit']    = ['col'=>2,'row'=>2,'dashboard_id'=>'todays_audit',    'settings'=>json_encode([])];
        dbMetaSet(0, 'dashboard_home', $panels, 'contacts', $cID);
    }
    
    private function installRefs()
    {
        $meta = [];
        foreach ($this->refs as $key => $value) { $meta[$key] = $value; }
        $null= dbMetaGet(0, 'bizuno_refs');
        $rID = metaIdxClean($null);
        dbMetaSet($rID, 'bizuno_refs', $meta);
    }

    public function installReports() // Adds full suite of default reports and forms
    {
        msgDebug("\nEntering installReports, building phreeForm folder tree");
        $today = biz_date();
        $phreeFormStructure = [];
        include(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/phreeform.php');
        foreach ($phreeFormStructure as $idx => $row) { // add the parent folder
            $values = [ 'parent_id'=>0, 'group_id'=>$idx, 'mime_type'=>'dir', 'title'=>$row['title'],
                'create_date'=>$today, 'last_update'=>$today, 'users'=>[-1], 'roles'=>[-1]];
            $parentID = dbMetaSet(0, 'phreeform', $values);
            foreach ($row['folders'] as $idx1 => $member) {
                $values = [ 'parent_id'=>$parentID, 'group_id'=>$idx1, 'mime_type'=>'dir', 'title'=>$member['title'],
                    'create_date'=>$today, 'last_update'=>$today, 'users'=>[-1], 'roles'=>[-1]];
                dbMetaSet(0, 'phreeform', $values);
            }
        }
        // Now load all of the reports since the tree strcuture is set
        if (file_exists (BIZUNO_FS_LIBRARY.'locale/'.getUserCache('profile', 'language', false, 'en_US').'/reports/')) {
            $read_path = BIZUNO_FS_LIBRARY.'locale/'.getUserCache('profile', 'language', false, 'en_US').'/reports/';
        } else {
            $read_path = BIZUNO_FS_LIBRARY.'locale/en_US/reports/';
        }
        $files = scandir($read_path);
        msgDebug("\nread files to import = ".print_r($files, true));
        foreach ($files as $file) {
            if (strtolower(substr($file, -5)) == '.json') {
                msgDebug("\nImporting report name = $file at path $read_path");
                phreeformImport('', $file, $read_path, false);
            }
        }
    }
}
