<?php
/*
 * Registry class used to manage user/business environmental variables and settings
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
 * @version    7.x Last Update: 2025-11-22
 * @filesource /model/registry.php
 */

namespace bizuno;

final class bizRegistry
{
    private $addUpgrade = 0;
    private $menuBar    = [];
    private $langCache  = [];
    private $validMods;
    public  $dbVersion;
    public $roles;
    public $userInfo;

    function __construct()
    {
        $this->dbVersion = MODULE_BIZUNO_VERSION;
    }

    /**
     * Takes basic module properties and builds inter-dependencies
     * @global array $bizunoMod
     * @param string $usrEmail - user email
     * @param integer $bizID - business ID
     * @return  null - registry created and saved
     */
    public function initRegistry($usrEmail='', $bizID=0)
    {
        global $bizunoMod;
        msgDebug("\nEntering initRegistry with email = $usrEmail and bizID=$bizID");
        $this->validMods = portalModuleList();
        $this->initLang();
        $bizunoMod = $this->initSettings();
        foreach ($this->validMods as $module => $path) { $this->initModule($module, $path); }
        $this->setRoleMenus();
        $this->initBizuno();
        $this->initPayment();
        $this->initPhreeBooks();
        $this->initPhreeForm($bizunoMod);
        $this->initDashboards();
        dbWriteCache();
        $this->setLangCache();
        msgDebug("\nReturning from initRegistry");
    }

    /**
     * Reloads the language from scratch to build the language cache
     */
    private function initLang()
    {
        global $bizunoLang;
        msgDebug("\nFetching lang from file system.");
        $langCore = $langByRef = [];
        require(BIZUNO_FS_LIBRARY.'locale/en_US/language.php');  // pulls the current language in English
        include(BIZUNO_FS_LIBRARY.'locale/en_US/langByRef.php'); // lang by reference (no translation required)
        $bizunoLang = [
            'core'      => array_merge($langCore, $langByRef),
            'modules'   => [],
            'dashboards'=> [],
            'methods'   => []];
        foreach (array_keys($this->validMods) as $modID) {
            $lang = [];
            $fullPath = bizAutoLoadMap(BIZUNO_FS_LIBRARY."locale/en_US/modules/$modID/language.php");
            msgDebug("\nLooking for language for module $modID at: $fullPath");
            if (file_exists($fullPath)) { require($fullPath); } // populates $lang
            ksort($lang);
            $bizunoLang['modules'][$modID] = $lang;
        }
    }
    
    /**
     * Load original configuration, properties get reloaded but others do not
     * @return type
     */
    private function initSettings()
    {
        global $bizunoMod;
        $layout = $modSettings = [];
        $dbMods = dbGetMulti(BIZUNO_DB_PREFIX.'configuration', "config_key IN ('".implode("','", array_keys($this->validMods))."')");
        foreach ($dbMods as $row) { $cfgMods[$row['config_key']] = json_decode($row['config_value'], true); }
        foreach ($this->validMods as $modID => $path) {
            if (isset($cfgMods[$modID])) {
                msgDebug("\nConfig database data is set for module: $modID");
                $modSettings[$modID] = $cfgMods[$modID];
            } else {
                msgDebug("\nConfig database data is NOT set for module: $modID, trying to install.");
                bizAutoLoad(BIZUNO_FS_LIBRARY .'controllers/bizuno/settings.php', 'bizunoSettings');
                setUserCache('role', 'administrate', 1);
                $bAdmin = new bizunoSettings();
                $bAdmin->moduleInstall($layout, $modID, $path);
                $modSettings[$modID] = $bizunoMod[$modID];
            }
            unset($modSettings[$modID]['hooks']); // will clear hooks to be rebuilt later
        }
        unset($modSettings['bizuno']['api']); // will clear list to be rebuilt later
        // get the Bizuno database version and retain for upgrade check
        $this->dbVersion = !empty($modSettings['bizuno']['properties']['version']) ? $modSettings['bizuno']['properties']['version'] : '4.1.0';
        msgDebug("\ndbVersion has been stored for bizuno module with $this->dbVersion");
        return $modSettings;
    }

    /**
     * Initializes a single module
     * @global array $bizunoMod - working module registry
     * @param string $module - module to initialize
     * @param string $path - path to module
     * @return updated $bizunoMod
     */
    public function initModule($module, $relPath)
    {
        global $bizunoMod;
        $path = bizAutoLoadMap($relPath);
        if (!file_exists("{$path}admin.php")) {
            unset($bizunoMod[$module]);
            // @todo delete the configuration db entry as the module has been removed manually and cannot be found
            return msgAdd("initModule cannot find module $module at path: $path");
        }
        msgDebug("\nBuilding registry for module $module, relPath = $relPath and path $path");
        $fqcn  = "\\bizuno\\{$module}Admin";
        bizAutoLoad("{$path}admin.php", $fqcn);
        $admin = new $fqcn();
// Temporary for now but doesn't slow things down
unset($bizunoMod[$module]['dashboards']);
        $bizunoMod[$module]['settings'] = isset($admin->settings) ? $admin->settings : [];
        // set some system properties
        $admin->structure['id']         = $module;
        $admin->structure['title']      = !empty($admin->lang['title'])       ? $admin->lang['title']       : $fqcn;
        $admin->structure['description']= !empty($admin->lang['description']) ? $admin->lang['description'] : $fqcn." description";
        $admin->structure['path']       = $relPath;
        $admin->structure['url']        = str_replace('BIZUNO_FS_LIBRARY', 'BIZUNO_URL_PORTAL', $relPath);
        if (!isset($admin->structure['status'])) { $admin->structure['status'] = 1; }
        $admin->structure['hasAdmin']   = method_exists($admin, 'adminHome') ? true : false;
        $admin->structure['devStatus']  = !empty($admin->devStatus) ? $admin->devStatus : false;
        $this->setMenus($admin->structure);
        $this->setGlobalLang($admin->structure);
        $this->setHooks($admin->structure, $module, $relPath);
        $this->setAPI($admin->structure);
        $this->initMethods($admin->structure);
        if (method_exists($admin, 'initialize')) { $admin->initialize(); }
        unset($admin->structure['lang'], $admin->structure['hooks'], $admin->structure['api']);
        $bizunoMod[$module]['properties']= $admin->structure;
        // Restore Bizuno database version for upgrade check. If the dbVersion is the same, then nothing is done
        if ($module=='bizuno') { $bizunoMod['bizuno']['properties']['version'] = $this->dbVersion; }
        msgDebug("\nFinished initModule for module: $module, updating cache.");
        $GLOBALS['updateModuleCache'][$module] = true;
    }

    private function setLangCache()
    {
        global $bizunoLang, $io;
        ksort($bizunoLang['core']);
        ksort($bizunoLang['modules']);
        ksort($bizunoLang['dashboards']);
        ksort($bizunoLang['methods']);
        $iso = getUserCache('profile', 'language');
        msgDebug("\nin initLangCache, writing lang file to iso = $iso");
        if (!empty($iso)) { $io->fileWrite(json_encode($bizunoLang), "cache/lang_{$iso}.json", false, false, true); }
    }

    /**
     * Adds the module menus to the overall menu structure
     * @param type $struc
     */
    private function setMenus(&$struc)
    {
        if (!empty($struc['menuBar'])) {
            $this->menuBar = array_replace_recursive($this->menuBar, $struc['menuBar']);
            unset($struc['menuBar']);
        }
    }

    /**
     * Load any system wide language to the registry language cache
     * @global type $structure
     */
    public function setGlobalLang($structure)
    {
        global $bizunoLang;
        if (!isset($structure['lang'])) { return; }
        foreach ($structure['lang'] as $key => $value) { $bizunoLang['core'][$key] = $value; }
    }


    /**
     * Sets the hooks array from a given module, if present
     * @param array $structure - array of hooks for the requested module
     * @param string $hookID -
     * @return type
     */
    public function setHooks($structure, $module, $path)
    {
        global $bizunoMod;
        if (!isset($structure['hooks'])) { return; }
        foreach ($structure['hooks'] as $mod => $page) {
            foreach ($page as $pageID => $pageProps) {
                foreach ($pageProps as $method => $methodProps) {
                    $methodProps['path'] = $path;
                    if (!isset($methodProps['page']))  { $methodProps['page'] = 'admin'; }
                    if (!isset($methodProps['class'])) { $methodProps['class']= $module.ucfirst($methodProps['page']); }
                    $bizunoMod[$mod]['hooks'][$pageID][$method][$module] = $methodProps;
                }
            }
        }
    }

    /**
     *
     * @global array $bizunoMod
     * @param type $structure
     * @return type
     */
    private function setAPI($structure)
    {
        global $bizunoMod;
        if (!isset($structure['api'])) { return; }
        $bizunoMod['bizuno']['api'][$structure['id']] = $structure['api'];
    }

    /**
     *
     * @param array $bizunoMod
     */
    private function initBizuno()
    {
        msgDebug("\nEntering initBizuno.");
        setModuleCache('bizuno', 'stores', '', dbGetStores());
    }

    private function initPayment()
    {
        // @TODO - Need to reload the methods_gateways meta to account for deleted, new and moved gateways
    }

    private function initPhreeBooks()
    {
        msgDebug("\nEntering initPhreeBooks.");
        $accts = getMetaCommon('chart_of_accounts');
        if (empty($accts)) { return; } // before upgrading to 7.0, this will erase the COA's after restoring the DB and before the upgrade.
        // If the chart accounts are all integers, the json_decode reindexes them to remove the gl Acct ID, put them back.
        $temp = [];
        foreach ($accts as $acct) { $temp[$acct['id']] = $acct; }
        $accts1 = $temp;
        setModuleCache('phreebooks', 'chart', '', $accts1);
    }

    /**
     * Initializes the PhreeForm settings
     * @param array $bizunoMod
     */
    private function initPhreeForm(&$bizunoMod)
    {
        msgDebug("\nEntering initPhreeForm.");
        dbReportsCache();
        $processing = $formatting = $separators = [];
        foreach (array_keys($bizunoMod) as $module) {
            if (!class_exists("\\bizuno\\{$module}Admin")) { continue; }
            $fqcn  = "\\bizuno\\{$module}Admin";
            $admin = new $fqcn();
            if (isset($admin->phreeformProcessing)) { $processing = array_merge($processing, $admin->phreeformProcessing); }
            if (isset($admin->phreeformFormatting)) { $formatting = array_merge($formatting, $admin->phreeformFormatting); }
            if (isset($admin->phreeformSeparators)) { $separators = array_merge($separators, $admin->phreeformSeparators); }
        }
        $bizunoMod['phreeform']['processing'] = $processing;
        $bizunoMod['phreeform']['formatting'] = $formatting;
        $bizunoMod['phreeform']['separators'] = $separators;
    }

    private function setRoleMenus()
    {
        $roleID= getUserCache('profile', 'userRole');
        msgDebug("\nEntering setRoleMenus with roleID = $roleID");
        if (empty($roleID)) { return; } // if empty, then not logged
        $roles = dbMetaGet('%', 'bizuno_role');
        msgDebug("\nFetched role count from meta = ".sizeof($roles));
        $this->userInfo = [];
        foreach ($roles as $role) {
            unset($role['_refID'], $role['_table']); // don't need these
            $tmpMenu = $this->menuBar['child'];
            $this->removeOrphanMenus($tmpMenu, !empty($role['security']) ? $role['security'] : []);
            $role['menuBar'] = sortOrder($tmpMenu);
            dbMetaSet($role['_rID'], 'bizuno_role', $role);
            $this->userInfo['r'.$role['_rID']] = [
                'title'   => $role['title'],
                'restrict'=> $role['restrict'],
                'admin'   => $role['administrate'],
                'groups'  => $role['groups']];
        }
        dbSetBizunoUsers();
        dbSetBizunoEmployees();
    }

    /**
     * Removes main menu heading if there are no sub menus underneath
     * @param array $menu - working menu
     * @return integer - maximum security value found during the removal process
     */
    private function removeOrphanMenus(&$menu, $userSecurity=[])
    {
        $security = 0;
        foreach ($menu as $key => $props) {
            if (isset($props['child'])) {
                $menu[$key]['security'] = $this->removeOrphanMenus($menu[$key]['child'], $userSecurity);
            } elseif (!empty($menu[$key]['required'])) {
                $menu[$key]['security'] = 4;
                setUserCache('security', $key, $menu[$key]['security']);
            } else {
                $menu[$key]['security'] = array_key_exists($key, $userSecurity) ? $userSecurity[$key] : 0;
            }
            if (!empty($menu[$key]['manager'])) { // managers can stand alone or have children, this prevents them from being removed if all children are no access
                $menu[$key]['security'] = array_key_exists($key, $userSecurity) ? $userSecurity[$key] : 0;
            }
            if (!$menu[$key]['security']) {
                unset($menu[$key]);
                continue;
            }
            $security = max($security, $menu[$key]['security']);
        }
        return $security;
    }

    /**
     *
     */
    private function initMethods($structure)
    {
        if (!isset($structure['dirMethods']))    { $structure['dirMethods'] = []; }
        if (!is_array($structure['dirMethods'])) { $structure['dirMethods'] = [$structure['dirMethods']]; }
        foreach ($structure['dirMethods'] as $folderID) {
            $methods = [];
            msgDebug("\ninitMethods is looking at module: {$structure['id']} folder: $folderID and relPath: {$structure['path']}");
            $path = bizAutoLoadMap($structure['path']);
            if (!file_exists("{$path}$folderID/")) { msgDebug("\nFolder is not there, bailing!"); continue; }
            msgDebug("\nreading methods");
            $this->methodRead($methods, "{$structure['path']}$folderID/");
            if (defined('BIZUNO_DATA')) {
                msgDebug("\ninitMethods is looking at customizations for module: {$structure['id']} and folder $folderID");
                $this->methodRead($methods, "BIZUNO_DATA/myExt/controllers/{$structure['id']}/$folderID/");
            }
            $this->cleanMissingMethods($structure['id'], $folderID, $methods);
            $this->initMethodList($structure, $folderID, $methods);
        }
    }

    /**
     *
     * @global array $bizunoMod
     * @param type $structure
     * @param type $folderID
     * @param type $methods
     */
    private function initMethodList($structure, $folderID, $methods)
    {
        global $bizunoLang;
        $module = $structure['id'];
        msgDebug("\ninitMethodList is looking at methods = ".print_r($methods, true));
        $meta = dbMetaGet(0, "methods_{$folderID}");
        $metaIdx= metaIdxClean($meta);
        $newMeta= [];
        foreach ($methods as $method => $path) {
            if (defined('BIZUNO_DATA') && strpos($path, 'BIZUNO_DATA/') === 0) {
                $bizID = getUserCache('business', 'bizID');
                $url   = BIZUNO_URL_FS."$bizID/myExt/controllers/$module/$folderID/$method/";
            } else {
                $url   = isset($structure['url']) ? "{$structure['url']}$folderID/$method/" : '';
            }
            msgDebug("\nregistry looking for method $method at path = ".print_r($path, true));
            $fqcn = "\\bizuno\\$method";
            if (!bizAutoLoad("{$path}$method.php", $fqcn)) { continue; }
            $clsMeth = new $fqcn();
            if (is_array($clsMeth->lang)) { ksort($clsMeth->lang); }
            $bizunoLang['methods'][$folderID][$method] = $clsMeth->lang;
            $title = !empty($clsMeth->lang['title']) ? $clsMeth->lang['title'] : $method;
            msgDebug("\nUpdating cache with new path = $path");
            $args = [ // just the values to update
                'id'         => $method, // This should already be set via the registry install, probably not needed here
                'title'      => $title,
                'acronym'    =>  isset($clsMeth->lang['acronym'])    ? $clsMeth->lang['acronym']    : $title,
                'description'=> !empty($clsMeth->lang['description'])? $clsMeth->lang['description']: "Description - $method",
                'path'       => $path,
                'url'        => $url,
                'menuID'     => !empty($clsMeth->menuID) ? $clsMeth->menuID : ''];
//msgDebug("\nregistry args array = ".print_r($args, true));
            $merged = array_replace(!empty($meta[$method])?$meta[$method]:[], $args);
//msgDebug("\nmerged array = ".print_r($merged, true));
            $newMeta[$method] = $merged;
            if (isset($clsMeth->structure)) { $this->setHooks($clsMeth->structure, $method, $path); }
        }
        msgDebug("\ninitMethodList is writing to module cache: {$structure['id']} folder: $folderID"); // with data = ".print_r($newMeta, true));
        dbMetaSet($metaIdx, "methods_{$folderID}", $newMeta);
    }

    public function methodRead(&$methods, $relPath)
    {
        $output = [];
        msgDebug("\nEntering methodRead with relPath = $relPath");
        $path = bizAutoLoadMap($relPath);
        if (!is_dir($path)) { msgDebug(" ... returning with folder not found"); return $output; }
        $temp = scandir($path);
        foreach ($temp as $fn) {
            if ($fn == '.' || $fn == '..') { continue; }
            if (!is_dir($path.$fn))        { continue; }
            $methods[$fn] = "{$relPath}$fn/";
        }
        return $output;
    }

    /**
    * This function cleans out stored registry values that have be orphaned in the configuration database table.
    * @param string $module - Module ID
    * @param string $folderID - Method ID
    * @param array $methods - List of all available methods in the specified folder
    * @return null
    */
    public function cleanMissingMethods($module, $folderID, $methods=[])
    {
        global $bizunoMod;
        if (!isset($bizunoMod[$module][$folderID]) || !is_array($methods)) { return; }
        $cache = array_keys($bizunoMod[$module][$folderID]);
        $allMethods = array_keys($methods);
        foreach ($cache as $method) {
            if (!in_array($method, $allMethods)) {
                msgAdd("Module: $module, folder: $folderID, Deleting missing method: $method");
                unset($bizunoMod[$module][$folderID][$method]);
            }
        }
    }

    public function initDashboards($installed=true)
    {
        msgDebug("\nEntering initDashboards.");
        $output = [];
        foreach ($this->validMods as $module => $path) { $this->scanDashFolder($output, $module, $path); }
        ksort($output); // start sorting everything
        if (!$installed) { return $output; }
        // get the id of the global cache values
        $metaDash= dbMetaGet(0, 'dashboards');
        $rID     = metaIdxClean($metaDash);
        dbMetaSet($rID, 'dashboards', $output);
    }
    
    private function scanDashFolder(&$output, $module, $relPath)
    {
        msgDebug("\nSearching for dashboards in module $module at path: $relPath");
        $path= bizAutoLoadMap($relPath);
        if (empty($path) || !file_exists("{$path}dashboards") || !is_dir("{$path}dashboards")) { return msgDebug("\n ... WITH NO DASHBOARDS FOUND at path = "); }
        msgDebug("\nFound path {$relPath}dashboards");
        if (!getModuleCache($module, 'properties', 'status')) { return; } // skip if module not loaded
        $theList = scandir("{$path}dashboards"); // can't use $io as the folders are not within BIZUNO_DATA path
        msgDebug("\nin scanDashFolder with theList read from disk = ".print_r($theList, true));
        foreach ($theList as $dashID) {
            if (in_array($dashID, ['.', '..'])) { continue; }
            if (!file_exists("{$path}dashboards/$dashID/$dashID.php")) { continue; }
            $fqcn   = "\\bizuno\\$dashID";
            bizAutoLoad("{$path}dashboards/$dashID/$dashID.php", $fqcn);
            $myDash = new $fqcn();
            if (empty($myDash)) { continue; }
            $opts = [
                'title'      => $myDash->lang['title'],
                'description'=> $myDash->lang['description'],
                'group'      => !empty($myDash->category) ? $myDash->category : 'general',
                'hidden'     => isset($myDash->hidden) ? $myDash->hidden : false,
                'path'       => "{$relPath}dashboards/$dashID/",
                'opts'       => !empty($myDash->struc) ? metaExtract($myDash->struc) : []];
//          msgDebug("\nSetting dashboard opts with ID: $dashID = ".print_r($opts, true));
            $output[$dashID] = $opts;
        }
    }
    
    /**
     *
     * @param type $myAcct
     * @return type
     */
    private function reSortExtensions($myAcct)
    {
        $output = [];
        if (empty($myAcct['extensions'])) { return []; }
        foreach ($myAcct['extensions'] as $cat) {
            foreach ($cat as $mID => $props) { $output[$mID] = $props; }
        }
        return $output;
    }
}