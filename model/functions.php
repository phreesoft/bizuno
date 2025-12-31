<?php
/*
 * Common functions
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
 * @version    7.x Last Update: 2025-12-29
 * @filesource /model/functions.php
 */

namespace bizuno;

/**
 * Auto loads files, if it's already loaded, returns true. if not, tests for files existence before requiring else dies.
 * @param string $path - Path to load file
 * @param string $method - [Default: false] A class or function within the file to test for the loaded presence
 * @param string $type - [Default: class] Whether 'class' or 'function' are being tested
 * @return boolean - true if already loaded, script dies with notice if the file is not there
 */
function bizAutoLoad($path, $method='', $type='class')
{
    if (function_exists('msgDebug')) { msgDebug("\nAutoloading path: $path, and method: $method of type: $type"); }
    if     (!empty($method)) { $method = __NAMESPACE__.'\\'. str_replace(__NAMESPACE__, '', $method); } // check for just one namespace
    if     ($type=='class'    && !empty($method) && class_exists   ($method)) { return true; }
    elseif ($type=='function' && !empty($method) && function_exists($method)) { return true; }
    $absPath = bizAutoLoadMap($path);
    if     (file_exists($absPath) && is_file($absPath)) { require_once($absPath); return true; }
    return false;
}

function bizAutoLoadMap($path='')
{
    if (empty($path)) { return BIZUNO_FS_LIBRARY; }
    $max = 1;
    if (strpos($path, 'BIZUNO_FS_LIBRARY')===0) { return str_replace('BIZUNO_FS_LIBRARY',BIZUNO_FS_LIBRARY,$path, $max); }
    if (strpos($path, 'BIZUNO_DATA')  ===0)     { return str_replace('BIZUNO_DATA',      BIZUNO_DATA,      $path, $max); }
    if (strpos($path, 'BIZUNO_URL_PORTAL')===0) { return str_replace('BIZUNO_URL_PORTAL',BIZUNO_URL_PORTAL,$path, $max); }
    if (strpos($path, 'BIZUNO_URL_FS')  ===0)   { return str_replace('BIZUNO_URL_FS',    BIZUNO_URL_FS,    $path, $max); }
    return $path;
}

function loadBusinessCache()
{
    global $bizunoMod;
    $bizunoMod= [];
    $rows     = dbGetMulti(BIZUNO_DB_PREFIX.'configuration');
    foreach ($rows as $row) { $bizunoMod[$row['config_key']] = json_decode($row['config_value'], true); }
}

/**
 * Composer gathers the module and mods, sorts them and executes in sequence.
 * @param string $module - Module ID
 * @param string $page - Page (filename) where the method is requested
 * @param string $method - Method on the given page to execute
 * @param array $layout - Current working layout, typically enters with empty array
 * @return boolean false, message Stack will have results as well as layout array
 */
function compose($module, $page, $method, &$layout=[])
{
    global $io;
    $processes = mergeHooks($module, $page, $method);
    foreach ($processes as $modID => $modProps) {
        if (empty($modProps['page'])) { $modProps['page'] = 'admin'; }
        $fqdn = isset($modProps['class']) ? "\\bizuno\\".$modProps['class'] : "\\bizuno\\".$modID.ucfirst($modProps['page']);
        $controller = "{$modProps['path']}{$modProps['page']}.php";
        if (!bizAutoLoad($controller, $fqdn)) {
            msgDebug("\nCache hooks for module: $module contains: ".print_r(getModuleCache($module, 'hooks'), true));
            msgAdd("Path = $controller - Expecting method: {$modProps['method']} in module $modID and page {$modProps['page']} with controller: $fqdn but could not find the method. Ignoring!", 'caution');
            continue;
        }
        msgDebug("\nWorking with controller: $controller");
        if (!class_exists($fqdn)) { return msgAdd("Path = $controller - Method: {$modProps['method']} NOT FOUND! Module $modID and page {$modProps['page']} with controller: $fqdn but could not find the method. Ignoring!"); }
        $process = new $fqdn();
        if (!isset($modProps['method'])) { $modProps['method'] = $method; }
        if (method_exists($process, $modProps['method'])) {
            $process->{$modProps['method']}($layout);
        } else {
            msgAdd("Path = $controller - Method: {$modProps['method']} NOT FOUND! Module $modID and page {$modProps['page']} with controller: $fqdn but could not find the method. Ignoring!", 'caution');
        }
    }
    // cURL action moved outside of loop as mods may need to augment layout before calling cURL, causes dups if inside loop with mods, before and after mod, see PrestaShop.
    if (isset($layout['curlAction'])) {
        $layout['cURLresp'] = $io->doCurlAction($layout['curlAction']);
        if (isset($layout['curlResponse'])) {
            $fqdn = "\\bizuno\\".$layout['curlResponse']['module'].ucfirst($layout['curlResponse']['page']);
            $process = new $fqdn();
            $process->{$layout['curlResponse']['method']}($layout);
        }
    }
    if (isset($layout['dbAction'])) { dbAction($layout); }  // act on the db, if needed
}

/**
 * Sets the paths for the modules, core and extensions needed to build the registry
 * *** Sequence is important, do not change! ***
 * @return module keyed array with path the modules requested
 */
function portalModuleList() {
    $modList = [];
    portalModuleListScan($modList, 'BIZUNO_FS_LIBRARY/controllers/'); // Core
    portalModuleListScan($modList, 'BIZUNO_DATA/myExt/controllers/'); // Custom
    msgDebug("\nReturning from portalModuleList with list: ".print_r($modList, true));
    return $modList;
}

function portalModuleListScan(&$modList, $path) {
    $absPath= bizAutoLoadMap($path);
    msgDebug("\nIn portalModuleListScan with path = $path and mapped path = $absPath");
    if (!is_dir($absPath)) { return; }
    $custom = scandir($absPath);
    msgDebug("\nScanned folders = ".print_r($custom, true));
    foreach ($custom as $name) {
        if ($name=='.' || $name=='..' || !is_dir($absPath.$name)) { continue; }
        if (file_exists($absPath."$name/admin.php")) { $modList[$name] = $path."$name/"; }
    }
}

function portalGetBizIDVal() {
    return defined('BIZUNO_TITLE') ? BIZUNO_TITLE : 'My Business';
}

/**
 * This function merges the primary method (at position 0) with any hooks, hooks with a negative order will preceed the primary method, positive order will follow
 * @param string $module - Module ID
 * @param string $page - Page ID, is also the filename where to find the method
 * @param string $method - method ID within the page
 * @return string $hooks - Sorted list of processes to execute
 */
function mergeHooks($module, $page, $method)
{
    $thisHooks = getModuleCache($module, 'hooks', $page, $method, []);
//  msgDebug("\nthisHooks for module: $module contains: ".print_r($thisHooks, true));
    // add in the primary method
    $thisHooks[$module] = ['order'=>0,'path'=>getModuleCache($module, 'properties', 'path'),'page'=>$page,'class'=>$module.ucfirst($page),'method'=>$method]; // put primary method at 0
    $output = sortOrder($thisHooks); // sort them all up
    msgDebug("\nTotal methods to process with hooks = ".print_r($output, true));
    return $output;
}

/**
 * Error handler function to aid in debugging
 * @param integer $errno - PHP error number
 * @param string $errstr - PHP error description
 * @param string $errfile - PHP file where the error occurred
 * @param integer $errline - line in the script that the error occurred
 * @return boolean true
 */
function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) { return; } // This error code is not included in error_reporting
    $debug = defined('BIZUNO_DEBUG') && constant('BIZUNO_DEBUG')===true ? true : false;
     switch ($errno) {
        case E_USER_ERROR:
            msgAdd("<b>ERROR</b> [$errno] $errstr<br />\n  Fatal error on line $errline in file $errfile, PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\nAborting...<br />\n", 'trap');
            msgDebugWrite();
            exit(1);
        case E_USER_WARNING:
            if ($debug){ msgAdd("<b>WARNING</b> [$errno] $errstr<br />\n", 'caution'); }
            else       { error_log("<b>WARNING</b> [$errno] $errstr<br />\n"); }
            break;
        default:
        case E_USER_NOTICE:
            if ($debug){ msgAdd("<b>NOTICE</b> [$errno] $errstr - Line $errline in file $errfile", 'caution'); }
            else       { error_log("<b>NOTICE</b> [$errno] $errstr - Line $errline in file $errfile"); }
            break;
    }
    return true; /* Don't execute PHP internal error handler */
}

/**
 * Handles fatal errors gracefully
 * @global array $msgStack
 * @param object $e - the exception that triggered this function
 */
function myExceptionHandler($e)
{
    global $msgStack;
    msgTrap ();
    msgDebug("\nFatal error on line ".$e->getLine()." in file ".$e->getFile().". Description: ".$e->getCode()." - ".$e->getMessage());
    msgAdd("Fatal error on line ".$e->getLine()." in file ".$e->getFile().". Description: ".$e->getCode()." - ".$e->getMessage());
    if (bizDbConnected()) { msgDebugWrite(); }
    exit(json_encode(['message' => $msgStack->error]));
//  exit("Program Exception! Please fill out a support ticket with the details that got you here.");
}

/**
 * Wrapper to test if the users db connection is valid
 * @global object $db - database object to do the work
 * @return boolean - true if connected to db, false otherwise
 */
function bizDbConnected()
{
    global $db;
    if (!is_object($db)) { return false; }
    return $db->connected ? true : false;
}

/**
 * Validates the user is logged in and returns the creds if true
 */
function getUserCookie() {
    if (!isset($_COOKIE['bizunoSession'])) { return false;}
    $scramble = preg_replace("/[^a-zA-Z0-9\+\/\=]/", '', $_COOKIE['bizunoSession']);
    msgDebug("\nChecking cookie to validate creds. read scrambled value = $scramble");
    if (empty($scramble)) { return false; }
    $creds = json_decode(base64_decode($scramble), true);
    msgDebug("\nDecoded creds = ".print_r($creds ,true));
    return !empty($creds) ? $creds : false;
}

function setUserCookie($user)
{
    msgDebug("\nEntering setUserCookie with user = ".print_r($user, true));
    // get the mapped local contact ID from the db
    if     (dbTableExists(BIZUNO_DB_PREFIX.'address_book')) { $user['userID'] = 0; } // for migration purposes to avoid errors on log in before migration
    elseif (empty($user['userID']) && dbTableExists(BIZUNO_DB_PREFIX.'contacts')) { // try to get it from db, if installed
        $user['userID'] = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "ctype_u='1' AND email='{$user['userEmail']}'");
        if (empty($user['userID'])) { // record not found in contacts table, create a new one
            $user['userID'] = dbWrite(BIZUNO_DB_PREFIX.'contacts', ['ctype_u'=>'1', 'email'=>$user['userEmail'], 'primary_name'=>$user['userName'], 'short_name'=>$user['userName'],
                'inactive'=>0, 'store_id'=>0, 'terms'=>0, 'price_sheet'=>0, 'tax_rate_id'=>0]);
            dbMetaSet(0, 'user_profile', ['email'=>$user['userEmail'], 'role_id'=>$user['userRole']], 'contacts', $user['userID']);
        }
    }
    if (!empty($user['userID'])) { // set the users preferences
        $meta   = dbMetaGet(0, 'user_profile', 'contacts', $user['userID']);
        $metaIdx= metaIdxClean($meta);
        if (!isset($meta['mode']))  { $meta['mode']  = 'dark'; }
        if (!isset($meta['screen'])){ $meta['screen']= '2048'; }
        $mode   = clean('mode',  'alpha_num', 'get');
        if (!empty($mode) && $mode<>$meta['mode']) { $meta['mode'] = $mode; }
        $device = clean('screen','alpha_num', 'get');
        if (!empty($device) && $device<>$meta['screen']) { $meta['screen'] = $device; } // only if device changes
        dbMetaSet($metaIdx, 'user_profile', $meta, 'contacts', $user['userID']);
    }
    setUserCache('profile', 'userID',  $user['userID']); // Local user ID
    setUserCache('profile', 'email',   $user['userEmail']);
    setUserCache('profile', 'psID',    $user['psID']); // PhreeSoft user ID
    setUserCache('profile', 'userRole',$user['userRole']);
    $args   = [$user['userID'], $user['psID'], $user['userEmail'], $user['userRole'], $_SERVER['REMOTE_ADDR']];
    msgDebug("\nSetting user session cookie bizunoSession with args = ".print_r($args, true));
    $cookie = base64_encode(json_encode($args));
    bizSetCookie('bizunoUser',    $user['userEmail'], time()+(60*60*24*7)); // 7 days
    bizSetCookie('bizunoSession', $cookie, time()+(60*60*10)); // 10 hours
}

/**
 *
 * @param type $name
 * @param type $value
 * @param type $time
 * @param type $options
 */
function bizSetCookie($name, $value, $time=86400, $options=[]) // 24 hours
{
    msgDebug("\nSetting cookie $name with value = $value and exp time = $time");
    $_COOKIE[$name] = $value;
    if (PHP_VERSION_ID < 70300) {
        setcookie($name, $value, $time, '/; samesite=lax');
    } else {
        $opts = array_merge($options, ['expires'=>$time,'path'=>'/','secure'=>true,'samesite'=>'lax']);
        setcookie($name, $value, $opts);
    }
}

/**
 *
 * @param type $name
 */
function bizClrCookie($name)
{
    $_COOKIE[$name] = '';
    if (PHP_VERSION_ID < 70300) {
        setcookie($name, '', time()-1, '/; samesite=lax');
    } else {
        setcookie($name, '', ['expires'=>time()-1,'path'=>'/','secure'=>true,'samesite'=>'lax']);
    }
}


/**
 * Clears the Bizuno module cache forcing a reload at next page load
 */
function bizCacheExpClear()
{
    $rID = dbGetValue(BIZUNO_DB_PREFIX.'common_meta', 'id', "meta_key='bizuno_cache_expires'");
    dbMetaSet($rID, 'bizuno_cache_expires', 0);
}

/**
 * Fetches the Bizuno module cache expiration date 
 * @return cache expiration timestamp
 */
function bizCacheExpGet()
{
    $rows = dbGetMulti(BIZUNO_DB_PREFIX.'common_meta', "meta_key='bizuno_cache_expires'", 'id');
    if (empty($rows))     { return 0; }
    if (sizeof($rows)==1) { return $rows['0']['meta_value']; }
    for ($i=1; $i<sizeof($rows); $i++) { dbMetaDelete($rows[$i]['id']); } // remove the duplicates
    return $rows[0]['meta_value'];
}

/**
 * Sets the cache expiration timestamp
 * @param integer $time - Timestamp to set in table common_meta
 */
function bizCacheExpSet($time=0)
{
    $rID = dbGetValue(BIZUNO_DB_PREFIX.'common_meta', 'id', "meta_key='bizuno_cache_expires'");
    dbMetaSet($rID, 'bizuno_cache_expires', $time);
}

function bizClrEncrypt() {
    clearUserCache('profile', 'admin_encrypt');
}

/**
 * Loads the language, tries cache first, then if stale or missing loads en_US first then overlays non-en_US if necessary
 * @param string $lang - ISO language code to load
 * @return array - core language array
 */
function loadBaseLang($lang='en_US')
{
    msgDebug("\nEntering loadBaseLang with lang = $lang");
    $langCore = $langByRef = [];
    if (strlen($lang) <> 5) { $lang = 'en_US'; }
    if (defined('BIZUNO_DATA') && file_exists(BIZUNO_DATA."cache/lang_{$lang}.json")) {
        msgDebug("\nFetching lang from cache.");
        $langCache = json_decode(file_get_contents(BIZUNO_DATA."cache/lang_{$lang}.json"), true);
    } else {
        msgDebug("\nFetching lang from file system.");
        require(BIZUNO_FS_LIBRARY.'locale/en_US/language.php');  // pulls the current language in English
        include(BIZUNO_FS_LIBRARY.'locale/en_US/langByRef.php'); // lang by reference (no translation required)
        $langCache['core'] = array_merge($langCore, $langByRef);
    }
    if ($lang == 'en_US') { return $langCache; } // just english, we're done
    $otherLang = [];
    if (file_exists(BIZUNO_FS_LIBRARY."locale/$lang/language.php")) {
        msgDebug("\nFetching lang: $lang from file system.");
        require(BIZUNO_FS_LIBRARY."locale/$lang/language.php");  // pulls locale overlay
        $langCore = array_replace_recursive($langCache, $langCore); // overlay ISO lang on top of working cache file
        include(BIZUNO_FS_LIBRARY.'locale/en_US/langByRef.php'); // lang by reference (reset after loading translation)
        $otherLang = array_replace_recursive($langCore, $langByRef);
    }
    return array_replace($langCache, $otherLang);
}

function langFillLabels(&$data, $lang=[])
{
    global $bizunoLang;
    foreach (array_keys($data) as $key) {
        $bizLabel= isset($bizunoLang['core'][$key.'_lbl']) ? $bizunoLang['core'][$key.'_lbl'] : false;
        $bizTip  = isset($bizunoLang['core'][$key.'_tip']) ? $bizunoLang['core'][$key.'_tip'] : false;
        msgDebug("\nLooking for key = $key and label = $bizLabel");
        if     (!empty($data[$key]['label'])){ $data[$key]['label']= $data[$key]['label']; }
        elseif (!empty($bizLabel))           { $data[$key]['label']= $bizLabel; }
        elseif (!empty($lang[$key.'_lbl']))  { $data[$key]['label']= $lang[$key.'_lbl']; }
        else                                 { $data[$key]['label']= lang($key); }
        if     (!empty($data[$key]['tip']))  { $data[$key]['tip']  = $data[$key]['tip']; }
        elseif (!empty($bizTip))             { $data[$key]['tip']  = $bizTip; }
        elseif (!empty($lang[$key.'_tip']))  { $data[$key]['tip']  = $lang[$key.'_tip']; }
//      else                                 { $data[$key]['tip']  = ''; } // Don't set it if nothing to show
    }
}

function getColumns()
{
    $jsCols = clean('numCols', 'integer', 'get');
    switch (getUserCache('profile', 'device')) {
        case 'mobile':  $default = 1;
        case 'tablet':  $default = 2;
        default:
        case 'desktop': $default = 3;
    }
    return !empty($jsCols) ? $jsCols : $default;
//    return getUserCache('profile', 'cols', false, 3);
}

/**
 * Loads and initializes a requested dashboard
 * @param string $module - Module where the dashboard is located
 * @param string $dashID - Name of the dashboard
 * @param array $usrSettings - Users settings for this dashboard
 * @return object - Dashboard object, initialized, false if not found or no security to access
 */
function getDashboard($dashID='')
{
    msgDebug("\nEntering getDashboard for dashID = $dashID");
    $metaGlobal = getMetaDashboard($dashID);
    if (empty($metaGlobal['path'])) { msgDebug("\nERROR - TRYING TO LOAD DASHBOARD $dashID BUT HAD NO PATH!"); return; }
    $path       = bizAutoLoadMap($metaGlobal['path']);
    msgDebug("\nfetching dashboard = $dashID and path {$path}$dashID.php");
    if (file_exists("{$path}$dashID.php")) {
        $fqcn   = "\\bizuno\\$dashID";
        bizAutoLoad("{$path}$dashID.php", $fqcn);
        $dash = new $fqcn();
        if (empty($dash->struc)) { $dash->struc = []; } // for dashboards that don't have admin settings
        return $dash;
    } elseif (getUserCache('profile', 'userID')) { // delete from profile as the dashboard is no longer there
        msgDebug("\nDeleting dashboard $dashID from the users profile since it no longer exists!");
// @ TODO - This is broken as only the key needs to be deleted.
    }
}

function getChartDefault($type)
{
    if (!empty(getModuleCache('phreebooks', 'chart', 'accounts'))) { // for pre-7.0 migration
        $charts = getModuleCache('phreebooks', 'chart', 'accounts'); // old way
    } else {
        $charts = getModuleCache('phreebooks', 'chart'); // new way
    }
//    msgDebug("\n chart defaults = ".print_r(getModuleCache('phreebooks', 'chart', 'defaults'), true));
//    msgDebug("\nchart of accts from cache = ".print_r($charts, true));
    if (empty($charts)) { msgDebug("\nERROR - chart is not loaded. This is bad!", 'trap'); return; }
    msgDebug("\nEntering getChartDefault looking for default of type $type");
    foreach ($charts as $chart) {
        if (!isset($chart['type'])) {
            msgDebug("\nTrying to get gl account of type $type but the gl account is not set! chart = ".print_r($chart, true), 'trap');
            continue;
        }
        if ($type==$chart['type'] && !empty($chart['default'])) {
            msgDebug("\nFound - returning gl account {$chart['id']}");
            return $chart['id'];
        }
    }
    msgDebug(" ... DEFAULT NOT FOUND FOR type=$type!");
    return; // not found
}

/**
 * Fetches the contact info by the db record id from the Bizuno cache
 * @param type $cID
 */
function getContactById($cID)
{
    $users = getModuleCache('bizuno', 'employees');
    foreach ($users as $user) {
        if ($user['id']==$cID) { return $user['text']; }
    }
    return $cID; // not found, this is bad!
}

/**
 * Fetches the contact info by the db record email from the Bizuno cache
 * @param type $email
 */
function getContactByEmail($email)
{
    $users = getModuleCache('bizuno', 'users');
    foreach ($users as $user) {
        if ($user['email']==$email) { return $user; }
    }
    return $email;
}

/**
 * Returns the businesses default ISO current code
 * @return default ISO currency code
 */
function getDefaultCurrency()
{
    return getModuleCache('phreebooks', 'currency', 'defISO', false, 'USD');
}

/************************ Meta Common functions **************************/
/**
 * Replaces meta values with cleaned post fields from the passed structure
 * @param type $metaVal - array to be updated
 * @param type $struc - structure of the data expected
 */
function metaUpdate($metaVal, $struc, $suffix='')
{
    msgDebug("\nEntering metaUpdate with metaVal = ".print_r($metaVal, true));
    foreach ($struc as $field => $content) {
        if (in_array($content['clean'], ['currency'])) { $content['clean'] = 'float'; } // special case for number boxes with special format options
        if (isset($_POST[$field.$suffix])) {
            $output[$field] = clean($field.$suffix, $content['clean'], 'post');
        } elseif (in_array($content['attr']['type'], ['checkbox', 'selNoYes'])) {
            $output[$field] = !empty($_POST[$field.$suffix]) ? '1' : '0';
        }
    }
    msgDebug("\nReturning from metaUpdate with output: ".print_r($output, true));
    return $output;
}

/**
 * Extracts the values from the supplied structure
 * @param array $struc - method structure
 */
function metaExtract($struc) {
    $output = [];
    foreach ($struc as $field => $values) {
        $output[$field] = isset($values['attr']['value']) ? $values['attr']['value'] : '';
    }
    return $output;
}

/**
 * Populates the meta structure with the meta data values
 * @param array $struc - page structure
 * @param array $metaVal - meta field values
 */
function metaPopulate(&$struc, $metaVal)
{
    foreach (array_keys($struc) as $field) {
        if     (!isset($struc[$field]['attr']['type'])) { $struc[$field]['attr']['type'] = 'text'; }
        if     ( isset($metaVal[$field]) && $struc[$field]['attr']['type']<>'password') { $struc[$field]['attr']['value'] = $metaVal[$field]; } // This option is used for reports, ???
        elseif ( isset($metaVal['opts'][$field]) && $struc[$field]['attr']['type']<>'password') { $struc[$field]['attr']['value'] = $metaVal['opts'][$field]; } // for dashboards?
    }
}

/**
 * Maps the database journal main values to the page field structure
 * @param array $struc - page field structure
 * @param type $dbRow - values to populate, in db field format
 */
function mapDBtoMeta(&$struc, $dbRow)
{
    $output = [];
    foreach ($struc as $index => $field) {
        if (isset($field['dbField'])) { 
            $output[$index] = isset($dbRow[$field['dbField']]) ? $dbRow[$field['dbField']] : '';
        } else {
            $output[$index] = isset($dbRow[$index]) ? $dbRow[$index] : '';
        }
    }
    return $output;
}

/**
 * Maps the posted data from meta field structure to db field names
 * @param array $struc - meta structure
 */
function mapMetaDataToDB($struc)
{
    foreach ($struc as $index => $field) {
        if (!isset($field['dbField'])) { continue; }
        if (isset($_POST[$index])) { $_POST[$field['dbField']] = $_POST[$index]; }
    }
}

/**
 * Maps a meta field structure indexes to the associated db table field names
 * @param array $dg - grid structure
 * @param array $struc - meta field structure
 */
function mapMetaGridToDB(&$dg, $struc)
{
//  $map = [];
//  foreach ($struc as $field => $value) { if (isset($value['dbField'])) { $map[$field] = $value['dbField']; } } // create the map
    foreach ($struc as $field => $value) { // Map the search fields
        $key = array_search($field, (array)$dg['source']['search']);
        if ($key!==false) { $dg['source']['search'][$key] = !empty($value['dbField']) ? $value['dbField'] : $key; }
    }
    $chars = ['.', ' ']; // characters that if present are sql operations or to clear up ambiguities
    foreach ($dg['columns'] as $key => $values) { // map the columns
        if (empty($values['field']) || preg_match('/['.preg_quote(implode(',', $chars)).']+/', $values['field'])) { continue; }
        if ($values['field']<>$key) {
            $dg['columns'][$values['field']] = $values;
            unset($dg['columns'][$key]);
        }
    }
}

/**
 * When expecting only a single hit in the meta table with multiple entries. Meta indexes are stripped
 * @param string $key - meta key to look for
 * @param array $args - arguments to pass to dbMetaGet function 
 * @return type
 */
function getMetaCommon($key, $args=[]) {
    $meta = dbMetaGet(0, $key, 'common', 0, $args);
    metaIdxClean($meta); // remove the db indexes
    return $meta;
}
function getMetaContact($cID, $key) {
    if (empty($cID)) { return []; }
    $meta = dbMetaGet(0, $key, 'contacts', $cID);
    metaIdxClean($meta); // remove the db indexes
    return $meta;    
}
function getMetaInventory($iID, $key) {
    if (empty($iID)) { return []; }
    $meta = dbMetaGet(0, $key, 'inventory', $iID);
    metaIdxClean($meta); // remove the db indexes
    return $meta;
}
function getMetaJournal($jID, $key) {
    if (empty($jID)) { return []; }
    $meta = dbMetaGet(0, $key, 'journal', $jID);
    metaIdxClean($meta); // remove the db indexes
    return $meta;
}

/**
 * Removes the database indexes and returns just the data of a meta record
 * @param type $meta
 */
function metaIdxClean(&$record=[])
{
    if (empty($record)) { $record = []; }
    $rID = !empty($record['_rID']) ? $record['_rID'] : 0; // return 0 if not found
    unset($record['_rID'], $record['_refID'], $record['_table']);
    return $rID;
}

/**
 * Fetches the default details of a specific dashboard from the common meta table
 * @param string $dashID - ID of the dashboard requested
 * @return type
 */
function getMetaDashboard($dashID)
{
    msgDebug("\nEntering getMetaDashboard with dashID = $dashID");
    if (empty($GLOBALS['bizuno_dashboards'])) { 
        $dashboards = dbMetaGet(0, 'dashboards');
        if (empty($dashboards)) { return []; } // at install, this is not set for first registry rebuild
        $rID = metaIdxClean($dashboards);
        msgDebug("\nRead all dashboards with rID = $rID and length = ".sizeof($dashboards));
//      msgDebug("\nRead all dashboards with rID = $rID and from meta = ".print_r($dashboards, true));
        $GLOBALS['bizuno_dashboards'] = $dashboards;
    }
    if (isset($GLOBALS['bizuno_dashboards'][$dashID])) {
        msgDebug(" ... Retrieved dashboard from cache.");
        return $GLOBALS['bizuno_dashboards'][$dashID];
    }
    msgDebug("\nDashboard meta for dashID $dashID NOT FOUND!");
}

/**
 * Fetches the default details of a specific method from the common meta table
 * @param string $folder - folder identifier for all methods of same type
 * @param string $method - specific method, if blank then all methods
 * @return array
 */
function getMetaMethod($folder, $method='')
{
    msgDebug("\nEntering getMetaMethods with folder = $folder and method = $method");
    if (empty($GLOBALS["methods_{$folder}"])) { 
        $methods = dbMetaGet(0, "methods_{$folder}");
        if (empty($methods)) { return []; }
        $rID = metaIdxClean($methods);
        msgDebug("\nRead all methods with rID = $rID and length = ".sizeof($methods));
//      msgDebug("\nRead all methods with rID = $rID and from meta = ".print_r($methods, true));
        $GLOBALS["methods_{$folder}"] = $methods;
    }
    if (isset($GLOBALS["methods_{$folder}"])) {
        msgDebug(" ... Retrieved methods from db meta key.");
        if (!empty($method) && empty($GLOBALS["methods_{$folder}"][$method])) { return []; }
        return !empty($method) ? $GLOBALS["methods_{$folder}"][$method] : $GLOBALS["methods_{$folder}"];
    }
    msgAdd("\nMethod meta for folder $folder NOT FOUND! This is not good.", 'trap');
}

/**
 * Fetches the meta for a single key in the common database to extract values from, i.e. PhreeForm processing/formatting
 * @param string $key - meta_key to search for
 * @param string $index - specific method, if blank then all methods
 * @param string #field - field within the located index
 * @return value if found, index of not
 */
function getMetaValue($key, $index='', $field='')
{
    msgDebug("\nEntering getMetaMethods with key = $key, index = $index and field = $field");
    if (empty($GLOBALS["BIZ_{$key}"])) { 
        $meta = dbMetaGet(0, $key);
        if (empty($meta)) { return []; }
        $rID = metaIdxClean($meta);
        msgDebug("\nRead all methods with rID = $rID and length = ".sizeof($meta));
        $GLOBALS["BIZ_{$key}"] = $meta; // save for later, if needed
    }
    if (isset($GLOBALS["BIZ_{$key}"])) {
        foreach ($GLOBALS["BIZ_{$key}"] as $row) {
            if ($row['id']==$index) { return isset($row[$field]) ? $row[$field] : $index; }
        }
    }
    msgAdd("\nMeta for $key NOT FOUND! This is not good.", 'trap');
}

/**
 * 
 * @param string $table - db table to search valid values are 'contacts', 'inventory', and 'journal'
 * @param string $prefix
 * @return string
 */
function getMetaRandom($table, $prefix)
{
    $try= rand(100000, 999999);
    $id = dbGetValue(BIZUNO_DB_PREFIX."{$table}_meta", 'id', "meta_key='{$prefix}$try'");
    if (empty($id)) { return $try; }
    else            { getMetaRandom($table, $prefix); } // try again, it's a dup
}

/**
 * Extracts the keys from the meta structure to assign to panels
 * @param array $struc - structure coming in
 * @return indexed array of keys
 */
function metaExtractKeys($struc, $suffix='')
{
    $output = [];
    foreach ($struc as $index => $value) {
        if (!isset($value['panel'])) { $value['panel'] = 'general'; }
        $output[$value['panel']][] = $index.$suffix;
    }
    msgDebug("\nParsed keys with results = ".print_r($output, true));
    return $output;
}

/************************ Cron functions **************************/
function getUserCron($idx='unknown')
{
    $meta = dbMetaGet(0, "cron_$idx", 'contacts', getUserCache('profile', 'userID'));
    metaIdxClean($meta);
    return $meta;
}

function setUserCron($idx='unknown', $data=[])
{
    $meta = dbMetaGet(0, "cron_$idx", 'contacts', getUserCache('profile', 'userID'));
    $rID  = metaIdxClean($meta);
    dbMetaSet($rID, "cron_$idx", $data, 'contacts', getUserCache('profile', 'userID'));
}

function clearUserCron($idx='unknown')
{
    $meta = dbMetaGet(0, "cron_$idx", 'contacts', getUserCache('profile', 'userID'));
    $rID  = metaIdxClean($meta);
    dbMetaDelete($rID, 'contacts');
}

/**
 * Tests to retrieve the sending email address transport preferences
 * @param obj $mail - phpMailer object
 */
function getMailCreds()
{
    $creds= getModuleCache('bizuno', 'settings', 'mail'); // company settings
    msgDebug("\nRead creds for the business = ".print_r($creds, true));
    $user = getMetaContact(getUserCache('profile', 'userID'), 'user_profile'); // user settings
    msgDebug("\nRead profile for the user = ".print_r($user, true));
    if (!empty($user['mail_mode']) && in_array($user['mail_mode'], ['smtp', 'gmail'])) { $creds = $user; }
    return $creds;
}

/**
 * Retrieves a value from the user cache
 * @global array $bizunoUser - User Cache
 * @param string $group [default => profile] - Designates the cache group to get, returns [] if group index is not set
 * @param string $lvl1 [default => false] - index of $group, if false (and $lvl2 == false), returns empty array
 * @param string $lvl2 [default => false] - index of $group, if false (and $lvl1 != false), returns $default
 * @param mixed $default [default => null] - returns this value if $lvl1 == false, $lvl2 == false OR array element is not set
 * @return mixed - result of the get, empty array or $default if not found
 */
function getUserCache($group='profile', $lvl1=false, $lvl2=false, $default=null)
{
    global $bizunoUser;
    if       (!$lvl1 && !$lvl2) { // it's a group, should always be an array
        if (is_array($group)) { return [];}
        return isset($bizunoUser[$group]) ? $bizunoUser[$group] : ($default != null ? $default : []);
    } elseif ( $lvl1 && !$lvl2) { // could be array or scalar, assume scalar for default
        return isset($bizunoUser[$group][$lvl1]) ? $bizunoUser[$group][$lvl1] : $default;
    } elseif ( $lvl1 &&  $lvl2) { // lvl1 is an array
        return isset($bizunoUser[$group][$lvl1][$lvl2]) ? $bizunoUser[$group][$lvl1][$lvl2] : $default;
    }
    return $default;
}

/**
 * Sets values in the users registry
 * @global type $bizunoUser - User Cache
 * @param type $group [default => ''] - Designates the cache group to set
 * @param type $lvl1 [default => ''] - index of $group, if empty assumes the group index to be set
 * @param type $value - data to set
 */
function setUserCache($group='', $lvl1='', $value='')
{
    global $bizunoUser;
//  msgDebug("\nSetting user group: $group with lvl1: $lvl1 and value = ".print_r($value, true));
    if     ($group && $lvl1) { $bizunoUser[$group][$lvl1]= $value; }
    elseif ($group)          { $bizunoUser[$group]       = $value; }
}

/**
 * Clears values in the users registry
 * @global type $bizunoUser - Global user cache array
 * @param type $group - group within users cache
 * @param type $lvl1 - first level index
 */
function clearUserCache($group='', $lvl1='')
{
    global $bizunoUser;
    if     ($group && $lvl1) {
        msgDebug("\nClearing user cache group: $group and lvl1 = $lvl1");
        unset($bizunoUser[$group][$lvl1]); }
    elseif ($group)          { unset($bizunoUser[$group]); }
}

function setSecurityOverride($index, $value)
{
    global $bizunoUser;
    msgDebug("\nEntering setSecurityOverride with index = ".print_r($index, true));
    msgDebug("\nand value = ".print_r($value, true));
    msgDebug("\nand bizunoUser = ".print_r($bizunoUser, true));
    if (empty($bizunoUser)) { $bizunoUser = []; } // for cron jobs, this may not be set as user is not logged in.
    $bizunoUser['role']['security'][$index] = $value;
}

function getMimeType($filename='')
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case "aiff":
        case "aif":  return "audio/aiff";
        case "avi":  return "video/msvideo";
        case "bmp":
        case "gif":
        case "png":
        case "tiff": return "image/$ext";
        case "css":  return "text/css";
        case "csv":  return "text/csv";
        case "doc":
        case "dot":  return "application/msword";
        case "docx": return "application/vnd.openxmlformats-officedocument.wordprocessingml.document";
        case "dotx": return "application/vnd.openxmlformats-officedocument.wordprocessingml.template";
        case "docm": return "application/vnd.ms-word.document.macroEnabled.12";
        case "dotm": return "application/vnd.ms-word.template.macroEnabled.12";
        case "gz":
        case "gzip": return "application/x-gzip";
        case "html":
        case "htm":
        case "php":  return "text/html";
        case "jpg":
        case "jpeg":
        case "jpe":  return "image/jpg";
        case "js":   return "text/javascript";
        case "json": return "application/json";
        case "mp3":  return "audio/mpeg3";
        case "mov":  return "video/quicktime";
        case "mpeg":
        case "mpe":
        case "mpg":  return "video/mpeg";
        case "pdf":  return "application/pdf";
        case "pps":
        case "pot":
        case "ppa":
        case "ppt":  return "application/vnd.ms-powerpoint";
        case "pptx": return "application/vnd.openxmlformats-officedocument.presentationml.presentation";
        case "potx": return "application/vnd.openxmlformats-officedocument.presentationml.template";
        case "ppsx": return "application/vnd.openxmlformats-officedocument.presentationml.slideshow";
        case "ppam": return "application/vnd.ms-powerpoint.addin.macroEnabled.12";
        case "pptm": return "application/vnd.ms-powerpoint.presentation.macroEnabled.12";
        case "potm": return "application/vnd.ms-powerpoint.template.macroEnabled.12";
        case "ppsm": return "application/vnd.ms-powerpoint.slideshow.macroEnabled.12";
        case "rtf":  return "application/rtf";
        case "svg":  return "image/svg+xml";
        case "swf":  return "application/x-shockwave-flash";
        case "txt":  return "text/plain";
        case "tar":  return "application/x-tar";
        case "wav":  return "audio/wav";
        case "wmv":  return "video/x-ms-wmv";
        case "xla":
        case "xlc":
        case "xld":
        case "xll":
        case "xlm":
        case "xls":
        case "xlt":
        case "xlt":
        case "xlw":  return "application/vnd.ms-excel";
        case "xlsx": return "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        case "xltx": return "application/vnd.openxmlformats-officedocument.spreadsheetml.template";
        case "xlsm": return "application/vnd.ms-excel.sheet.macroEnabled.12";
        case "xltm": return "application/vnd.ms-excel.template.macroEnabled.12";
        case "xlam": return "application/vnd.ms-excel.addin.macroEnabled.12";
        case "xlsb": return "application/vnd.ms-excel.sheet.binary.macroEnabled.12";
        case "xml":  return "application/xml";
        case "zip":  return "application/zip";
        default:
            if (function_exists(__NAMESPACE__.'\mime_content_type')) { # if mime_content_type exists use it.
                $m = mime_content_type($filename);
            } else {    # if nothing left try shell
                if (!isset($_SERVER['HTTP_USER_AGENT'])) { return ''; } // probably a spam bot or malicious request
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'Windows')) { # Nothing to do on windows
                    return ""; # Blank mime display most files correctly especially images.
                }
                if (strstr($_SERVER['HTTP_USER_AGENT'], 'Macintosh')) { $m = trim(exec('file -b --mime '.escapeshellarg($filename))); }
                else { $m = trim(exec('file -bi '.escapeshellarg($filename))); }
            }
            $m = explode(";", $m);
            return trim($m[0]);
    }
}

function getModuleLang($modID='bizuno')
{
    global $bizunoLang;
    $output = [];
    if (isset($bizunoLang['modules'][$modID])) {
        $output =  $bizunoLang['modules'][$modID];
    }
    msgAdd('Error - language file for module not found!');
    return $output;
}

/**
 * Retrieves an element from the module cache
 * @global type $bizunoMod - Module Cache
 * @param type $module [required] - Module to pull data from
 * @param type $group [default => settings] - Designates the cache group to get
 * @param type $lvl1 [default => false] - index of $group, if false (and $lvl2 == false), returns empty array
 * @param type $lvl2 [default => false] - index of $group, if false (and $lvl1 != false), returns $default
 * @param type $default [default => null] - returns this value if $lvl1 == false, $lvl2 == false OR array element is not set
 * @return mixed - result of the get, empty array or $default if not found
 */
function getModuleCache($module, $group='settings', $lvl1=false, $lvl2=false, $default=null)
{
    global $bizunoMod;
    if       (!$lvl1 && !$lvl2) { // it's a group, should always be an array
        return isset($bizunoMod[$module][$group]) ? $bizunoMod[$module][$group] : ($default ? $default : []);
    } elseif ( $lvl1 && !$lvl2) { // could be array or scalar, assume scalar for default
        if (isset($bizunoMod[$module][$group][$lvl1])) { return $bizunoMod[$module][$group][$lvl1]; }
        if (isset($bizunoMod[$module][$group]) && array_key_exists($lvl1, $bizunoMod[$module][$group])) {
            return $bizunoMod[$module][$group][$lvl1]; // check for index with null
        }
        return isset($bizunoMod[$module][$group][$lvl1]) ? $bizunoMod[$module][$group][$lvl1] : $default;
    } elseif ( $lvl1 &&  $lvl2) { // lvl1 is an array
        if (isset($bizunoMod[$module][$group][$lvl1][$lvl2])) { return $bizunoMod[$module][$group][$lvl1][$lvl2]; }
        if (isset($bizunoMod[$module][$group][$lvl1]) && array_key_exists($lvl2, (array)$bizunoMod[$module][$group][$lvl1])) {
            return $bizunoMod[$module][$group][$lvl1][$lvl2]; // check for index with null
        }
    }
    return $default; // bad index request
}

/**
 * Saves the settings for a given module or module group, updates the cache and sets the flag to save in db at the end of the script
 * @global type $bizunoMod - Module Cache
 * @param type $module [required] - Module to set data to
 * @param type $group [default => settings] - Designates the cache group to set
 * @param type $lvl1 [default => false] - index of $group, if false assumes the group index to be set
 * @param type $value - data to set
 */
function setModuleCache($module, $group=false, $lvl1=false, $value='')
{
    global $bizunoMod;
    msgDebug("\nEntering setModuleCache with module = $module and group = $group and lvl1 = $lvl1");
    if     ($group && $lvl1) { $bizunoMod[$module][$group][$lvl1] = $value; }
    elseif ($group)          { $bizunoMod[$module][$group]        = $value; }
    $GLOBALS['updateModuleCache'][$module] = true;
//    msgDebug("\nSetting module: $module and group: $group with lvl1: $lvl1 and value = ".print_r($value, true));
}

/**
 * Clears the module group or group/level 1 properties from the cache
 * @param type $module
 * @param type $group
 */
function clearModuleCache($module, $group=false, $lvl1=false)
{
    global $bizunoMod;
    if     ($group && $lvl1) { unset($bizunoMod[$module][$group][$lvl1]); }
    elseif ($group)          { unset($bizunoMod[$module][$group]); }
    $GLOBALS['updateModuleCache'][$module] = true;
}

/**
 * Reads the user defined settings for a given module and updates the registry
 * @param string $module - Module index name
 * @param array $structure - Structure of the settings for the given module
 * @return null - Sets module cache for with the users selections
 */
function readModuleSettings($module, $structure=[])
{
    $settings = [];
    foreach ($structure as $group => $values) {
        foreach ($values['fields'] as $setting => $props) {
            $fldVal = clean($group."_".$setting, ['format'=>isset($props['attr']['format']) ? $props['attr']['format'] : 'text'], 'post');
            if (!empty($props['attr']['type']) && $props['attr']['type']=='password' && empty($fldVal)) {
                msgDebug("\nSkipped group: $group and setting = $setting");
                $settings[$group][$setting] = !empty($props['attr']['value']) ? $props['attr']['value'] : '';
            } else {
                $settings[$group][$setting] = $fldVal;
            }
        }
    }
    msgDebug("\nSaving settings array: ".print_r($settings, true));
    setModuleCache($module, 'settings', false, $settings);
    msgAdd(lang('msg_settings_saved'), 'success');
}

/**
 * This function extracts the settings values from the view structure and puts into simple array for usage and registry storage
 * @param array structure - Bizuno settings structure to pull values from
 * @return array
 */
function getStructureValues($structure='')
{
    $output = [];
    if (empty($structure)) { return $output; }
    foreach ($structure as $group => $values) {
        foreach ($values['fields'] as $setting => $props) { $output[$group][$setting] = isset($props['attr']['value']) ? $props['attr']['value'] : ''; }
    }
    return $output;
}

/**
 * USED FOR METHODS - This function strips out the hidden settings values forcing the defaults and replaces the defaults with the user settings values, if set
 * @param array $defaults - defaults settings for the module/method, will be overridden by user settings if not hidden
 * @param array $settings - user defined settings to override
 * @param array $structure - module/method structure to act upon
 */
function settingsReplace(&$defaults, $settings=[], $structure=[]) {
    foreach ($structure as $key => $value) {
        if (empty($value['attr']['type']) || $value['attr']['type'] != 'hidden') {
            if (isset($settings[$key])) { $defaults[$key] = $settings[$key]; }
        }
    }
}

/**
 * For methods, takes the post variables, strips the prefix updates the settings array
 * @param type $settings
 * @param type $structure
 * @param type $prefix
 */
function settingsSaveMethod(&$settings, $structure, $prefix='') {
    foreach ($structure as $key => $props) {
        if (isset($_POST[$prefix.$key])) {
            if (empty($props['attr']['type'])) { $props['attr']['type'] = 'text'; } // default to text type (minimal filter)
            if (!empty($props['attr']['multiple']) && $props['attr']['type']=='select' && $props['attr']['multiple']=='multiple') {
                $settings[$key] = implode(':', clean($prefix.$key, 'array', 'post'));
            } else {
                $settings[$key] = clean($prefix.$key, $props['attr']['type'], 'post');
            }
        } elseif ($props['attr']['type']=='selNoYes') {
            $settings[$key] = 0;
        }
    }
}

/**
 * This function populates the settings view structure with user registry values
 * Priority: table configuration, modCache[$module], default: array()
 * Moved table configuration first to load first if reloading registry after setting save, else doesn't update properly
 * @param array $structure - module structure
 * @param string $module - Module id
 */
function settingsFill(&$structure, $module='')
{
    $settings = getModuleCache($module, 'settings', false, false, []);
    if (empty($settings)) { return; }
    foreach ($settings as $group => $entries) {
        if (!is_array($entries)) { continue; } // mal-formed settings
        foreach ($entries as $key => $value) {
            if (isset($structure[$group]['fields'][$key])) { $structure[$group]['fields'][$key]['attr']['value'] = $value; }
        }
    }
}

/**
 * Verifies the default settings for the PhreeForm processing and formatting options as added by modules and extensions
 * @param array $values - array of processing or formatting to be checked
 * @param string $mID - Module ID
 * @param string $title - Module title
 * @return modified $values
 */
function setProcessingDefaults(&$values, $mID='bizuno', $title='General')
{
    foreach ($values as $idx => $value) {
        if (empty($value['group']))   { $values[$idx]['group']   = $title; }
        if (empty($value['module']))  { $values[$idx]['module']  = $mID; }
        if (empty($value['function'])){ $values[$idx]['function']= $mID=='bizuno' ? 'viewFormat' : "{$mID}Process"; }
    }
}

/**
 * Calculates the due date in database format given the customers/vendors terms
 * @param string $terms_encoded - Encoded payment terms
 * @param char $type [default: c] - customer (c) or vendor (v)
 * @param string $post_date [default: false] - post dat of transaction for date calculations
 * @return string - date in db format
 */
function getTermsDate($terms_encoded='', $type='c', $post_date=false)
{
    $idx = $type=='v' ? 'vendors' : 'customers';
    if (empty($post_date)) { $post_date = biz_date('Y-m-d'); }
    $terms_def = explode(':', getModuleCache('phreebooks', 'settings', $idx, 'terms'));
    if (!$terms_encoded){ $terms = $terms_def; }
    else                { $terms = explode(':', $terms_encoded); }
    if ($terms[0]==0)   { $terms = $terms_def; }
    switch ($terms[0]) {
        default:
        case '0': // Default terms
        case '3': // Special terms
            if (!isset($terms[3])) { $terms[3] = 30; }
            return localeCalculateDate($post_date, $terms[3]);
        case '1': // Cash on Delivery (COD)
        case '2': // Prepaid
        case '6': // Due upon receipt
            return $post_date;
        case '4': return $terms[3];     // Due on date
        case '5': return localeCalculateDate(substr($post_date, 0, 7)."-01", -1, 1); // Due at end of month
    }
}

/**
 * Returns the first hit from $_REQUEST of the array of possible indices.
 * @param array $indices - [default: array('search','q')] - List of indices to comb through, q first as when instantiating the combo, q is empty but once
 * the use start typing, q has a value and should take precedence.
 * @return string - First hit
 */
function getSearch($indices=['q', 'search']) {
    if (!is_array($indices)) { $indices = [$indices]; }
    foreach ($indices as $idx) {
        if (isset($_REQUEST[$idx])) { return $_REQUEST[$idx]; }
    }
    return '';
}

/**
 * Retrieves and returns the next reference number from the cache and increments the value in the cache.
 * @param string $idx - index of the reference  number
 * @param string $default
 * @return type
 */
function getNextReference($idx, $default='R1000')
{
    $meta= dbMetaGet(0, 'bizuno_refs');
    $rID = metaIdxClean($meta);
    $ref = !empty($meta[$idx]) ? $meta[$idx] : $default;
    $output = $ref;
    $ref++;
    msgDebug("\nIn getNextReference, retrieved for field: $idx value: $output and incremented to get $ref");
    $meta[$idx] = $ref;
    dbMetaSet($rID, 'bizuno_refs', $meta);
    return $output;
}

function setNextReference($idx, $value='R1000')
{
    $meta= dbMetaGet(0, 'bizuno_refs');
    $rID = metaIdxClean($meta);
    $meta[$idx] = $value;
    dbMetaSet($rID, 'bizuno_refs', $meta);
}

/**
 * pulls the text value of a Bizuno formatted select data set matching the provided key
 * @param string $key - Key to search for
 * @param type $values - data set to search within
 * @return string - text value if found
 */
function getSelLabel($key, $values=[]) {
    foreach ($values as $value) {
        if ($key==$value['id']) { return $value['text']; }
    }
    return 'undefined';
}
/**
 * Sorts an array by specified key
 * @param type $arrToSort - Array to be sorted
 * @param type $sortKey [default: order] Specifies the key to use as the base for the sort order
 * @param string - [default: asc] Sort order: asc - Ascending, desc - descending
 * @return array - Sorted array by key
 */
function sortOrder($arrToSort=[], $sortKey='order', $order='asc')
{
    $temp = [];
    if (!is_array($arrToSort)) { return $arrToSort; }
    foreach ($arrToSort as $key => $value) {
        $temp[$key] = isset($value[$sortKey]) ? strtolower($value[$sortKey]) : 999;
    }
    $type = $order=='desc' ? SORT_DESC : SORT_ASC;
    array_multisort($temp, $type, $arrToSort);
    return $arrToSort;
}

/**
 * Sorts an array by specified key after the language translation has been applied, typically used for lists
 * @param type $arrToSort - Array to be sorted
 * @param type $sortKey [default: order] Specifies the key to use as the base for the sort order
 * @return array - Sorted array by key
 */
function sortOrderLang($arrToSort=[], $sortKey='title')
{
    $temp = [];
    if (!is_array($arrToSort)) { return $arrToSort; }
    foreach ($arrToSort as $key => $value) {
        $temp[$key] = isset($value[$sortKey]) ? lang($value[$sortKey]) : 'ZZZ';
    }
    array_multisort($temp, SORT_ASC, $arrToSort);
    return $arrToSort;
}

function structureFill(&$structure, $data=[])
{
    msgDebug("\nEntering structureFill with data = ".print_r($data, true));
    foreach ($structure as $key => $value) {
        if (!isset($data[$key])) { continue; }
        if (empty($value['attr']['type'])) { $value['attr']['type']='text'; }
        switch ($value['attr']['type']) {
            case 'checkbox':
            case 'radio':   $structure[$key]['attr']['checked']= !empty($data[$key]) ? true : false; break;
            default:        $structure[$key]['attr']['value']  = $data[$key]; break;
        }
    }
}

/**
 * Takes input global variables and updates the cache to store user selections on a given manager screen.
 * @param array $data - structure to clean and store user preferences
 * @return updated SESSION with users posted preferences
 */
function updateSelection($data)
{
    $output = [];
    foreach ($data['values'] as $settings) {
        $method = isset($settings['method']) ? $settings['method'] : 'post';
        $output[$settings['index']] = clean($settings['index'], ['format'=>$settings['clean'],'default'=>$settings['default']], $method);
    }
    return $output;
}

/**
 * Given a file, i.e. /css/base.js, replaces it with a string containing the file's mtime, i.e. /css/base.1221534296.js
 * @param string file - The file to be loaded.  Must be an absolute path (i.e. starting with slash).
 * @return string - Adjusted filename with date inserted into it
 */
function auto_version($file)
{
    $mtime = filemtime($file);
    return preg_replace('{\\.([^./]+)$}', ".$mtime.\$1", $file);
}

/**
 * Determines the fiscal calendar period based on a passed date
 * @param string $post_date - date to retrieve period information
 * @param boolean $verbose - [default true] set to false to suppress user messages
 * @return integer - fiscal year period based on the submitted date
 */
function calculatePeriod($post_date, $verbose=true)
{
    msgDebug("\nEntering calculatePeriod with post_date = $post_date");
    if (!defined('BIZUNO_DB_PREFIX')) { return; } // if not activated then this will happen before Bizuno is installed
    if (getModuleCache('phreebooks', 'fy', 'period')) {
        $post_time_stamp         = strtotime($post_date);
        $period_start_time_stamp = strtotime(getModuleCache('phreebooks', 'fy', 'period_start'));
        $period_end_time_stamp   = strtotime(getModuleCache('phreebooks', 'fy', 'period_end'));
        if (($post_time_stamp >= $period_start_time_stamp) && ($post_time_stamp <= $period_end_time_stamp)) {
            return getModuleCache('phreebooks', 'fy', 'period', false, 0);
        }
    }
    $period = dbGetValue(BIZUNO_DB_PREFIX.'journal_periods', 'period', "start_date<='$post_date' AND end_date>='$post_date'");
    msgDebug("\nHad to get period from db, retrieved period = $period");
    if (!$period) { // post_date is out of range of defined accounting periods
        return msgAdd(sprintf(lang('err_gl_post_date_invalid'), $post_date));
    }
    if ($verbose) { msgAdd(lang('msg_gl_post_date_out_of_period'), 'caution'); }
    return $period;
}

/**
 * This function automatically updates the period and sets the new constants in the configuration db table
 * MOVED HERE FROM phreebooks/functions as it tests with every page load
 * @param boolean $verbose
 * @return boolean
 */
function periodAutoUpdate($verbose=true)
{
    $period = calculatePeriod(biz_date('Y-m-d'), false);
    if ($period == getModuleCache('phreebooks', 'fy', 'period')) { return true; } // we're in the current period
    if (!$period) { // we're outside of the defined fiscal years
        if ($verbose) { msgAdd(sprintf(lang('err_gl_post_date_invalid'), $period)); } // removed 'trap' as auto fiscal year creates debug files everywhwere
        setUserCache('role', 'administrate', 1);
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/tools.php', 'phreebooksTools');
        $tools = new phreebooksTools();
        $tools->fyAdd(); // auto-add new fiscal year
        return true;
    } else {
        $props = dbGetPeriodInfo($period);
        setModuleCache('phreebooks', 'fy', false, $props);
        msgLog(sprintf(lang('msg_period_changed'), $period));
        if ($verbose) { msgAdd(sprintf(lang('msg_period_changed'), $period), 'success'); }
    }
    return true;
}

/**
 * Generates a random string of given length, characters used are A-Za-z0-9
 * @param integer $length - (Default 12) Length of string to generate
 * @return string - Random string of length $length
 */
function randomValue($length = 12)
{
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890';
    $numChar= strlen($chars) - 1;
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $random = rand(0, $numChar);
        $string.= substr($chars, $random, 1);
    }
    return $string;
}

/**
 * Round to a certain precision, includes correction for floating point issues like calculated value of 162.694999999 rounding to 162.69 instead of 162.70
 * @param float $value - Value to round
 * @param integer $precision - [default 2] precision to round to
 * @return rounded $value
 */
function roundAmount($value, $precision=2)
{
    $pass1 = round($value, $precision+4, PHP_ROUND_HALF_UP); // increased from 2 to 4 for customer tax calculation 5.62495 rounded to 5.63 vs order 5.62
    return round($pass1, $precision, PHP_ROUND_HALF_UP);
}

function validateDashboardSecurity($myDash)
{
    msgDebug("\nEntering validateDashboardSecurity with profile:userID = ".getUserCache('profile', 'userID')." and roleID = ".getUserCache('profile', 'userRole')); 
    if (empty($myDash->security)) { return false; }
    $usersNone = $rolesNone = false;
    if (isset($myDash->settings['users'])) {
        $users = is_array($myDash->settings['users']) ? $myDash->settings['users'] : explode(':', $myDash->settings['users']);
        msgDebug("\nChecking users: ".print_r($users, true));
        if (in_array('-1',$users)) { return true; } // all users
        if (in_array(getUserCache('profile', 'userID', false, 0), $users)) { return true; } // this user
        if (in_array('0', $users)) { $usersNone = true; } // no users
    }
    if (isset($myDash->settings['roles'])) {
        $roles = explode(':', $myDash->settings['roles']);
        msgDebug("\nChecking roles: ".print_r($roles, true));
        if (in_array('-1', $roles)) { return true; } // all roles
        if (in_array(getUserCache('profile', 'userRole', false, 0), $roles)) { return true; } // this role
        if (in_array('0', $roles)) { $rolesNone = true; } // no users
    }
    if (!$usersNone && !$rolesNone && $myDash->security > 0) { return true; }
    return false;
}

/**
 * This function takes the structure and verifies the data is of the correct type and length if a string
 * @param type $structure
 * @param type $data
 */
function validateData($structure=[], &$data=[])
{
    foreach ($structure as $field => $props) {
        if (!isset($data[$field])) { continue; } // if it is not set, skip it, prevents injecting values when importing
        if       (in_array($props['attr']['type'],['currency'])) {
            if (empty($data[$field]))         { $data[$field] = 0; } // make sure a value is present for strict db
            $data[$field] = clean($data[$field], 'currency'); // clean currency formatting to float
        } elseif (in_array($props['format'],      ['currency','integer','float'])) {
            if (empty($data[$field]))         { $data[$field] = 0; } // make sure a value is present for strict db
            if ($props['format']=='currency') { $data[$field] = clean($data[$field], 'currency'); } // clean currency formatting to float
        } elseif (in_array($props['attr']['type'],['date','datetime','time'])) {
            if (empty($data[$field])) { $data[$field] = 'null'; } // make sure date is null or has value
        } elseif (!empty($props['attr']['maxlength'])) {
            if (strlen($data[$field]) > $props['attr']['maxlength']) {
                msgAdd("The data ({$data[$field]}) for field {$props['label']} is too long! It was truncated to {$props['attr']['maxlength']} characters.", 'info');
                $data[$field] = substr($data[$field], 0, $props['attr']['maxlength']);
            }
        }
    }
}

/**
 * Validates user security levels to access any given method.
 * @param string $index - Menu item to check against
 * @param integer $min_level - minimum security range 1 to 4 to set security access levels
 * @param boolean $verbose - true add error message to stack if no permission, false to suppress message
 * @return integer - Security level of user for given module/menu, false if no access is permitted
 */
function validateAccess($index, $min_level=1, $verbose=true)
{
    msgDebug("\nEntering validateAccess with index = $index and administrate = ".getUserCache('role', 'administrate'));
    if ('admin'==$index && !empty(getUserCache('role', 'administrate'))) {
        $approved = 4;
    } else {
        $level = intval(getUserCache('role', 'security', $index));
        $approved = ($level >= $min_level) ? $level : 0;
    }
    if (!$approved && $verbose) { msgAdd(lang('err_no_permission')." [$index]"); }
    msgDebug("\nLeaving validateAccess with index = $index and min level = $min_level and approved = $approved");
    return $approved;
}

/**
 * Validates the user and password (set in the users profile) to validate input data
 * @param integer $userPIN - Users Pin as entered into Employees contact record
 * @param string $userGroup - Group to be a part of. Set in Roles->Edit.
 * @return if found then table contact record id else null
 */
function validateSignoff($userPIN=0, $userGroup='')
{
    msgDebug("\nIn validateSignoff with userPIN = $userPIN and group = $userGroup with result");
    if (empty($userPIN) || empty($userGroup)) { return msgDebug(" ... FAILED INPUT!"); }
    $staff = getModuleCache('bizuno', 'employees');
//    msgDebug("\nRead employee list = ".print_r($staff, true));
    foreach ($staff as $user) {
        if (!empty($user['pin']) && $user['pin']==$userPIN) { msgDebug(" ... Passed!"); return $user['id']; }
    }
    msgDebug(" ... FAILED!");
}

function validateUsersRoles($data=false) {
//    msgDebug("\nEntering validateUsersRoles with imploded users array: ".implode(':', $data['users'])." and imploded roles array: ".implode(':', $data['roles']));
    if (in_array(-1, $data['users']))                                 { return true; }
    if (in_array(getUserCache('profile', 'userID'),  $data['users'])) { return true; }
    if (in_array(-1, $data['roles']))                                 { return true; }
    if (in_array(getUserCache('profile', 'userRole'),$data['roles'])) { return true; }
    return false;
}

/**
 * Pulls the main address record from the database if $cID > 0, else returns business address information from Bizuno settings
 * @param integer $cID - record id of the address
 * @param string $suffix - suffix to append to index of returned array
 * @return array - keyed array of address information
 */
function addressLoad($cID=0, $suffix='')
{
    msgDebug("\nEntering addressLoad with cID = $cID and suffix = $suffix");
    $fields = ['primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','telephone2','telephone3','telephone4','email','email2','email3','email4','website','notes'];
    if (empty($cID) && !empty(getUserCache('profile', 'restrict_store')) && !empty(getUserCache('profile', 'store_id'))) {
        $result = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=".getUserCache('profile', 'store_id'));
    } elseif (!empty($cID)) {
        $result = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
    } else { // load home address from registry
        $result = getModuleCache('bizuno', 'settings', 'company');
    }
    $output = [];
    foreach ($fields as $key) {
        if (isset($result[$key])) { $output[$key.$suffix] = $result[$key]; }
    }
    return $output;
}

/**
 * Bizuno operates in local time. 
 * @param string $format - [default: 'Y-m-d'] From the PHP function date()
 * @param integer $timestamp - Unix timestamp, defaults to now
 * @return string
 */
function biz_date($format='Y-m-d', $timestamp=null) {
    return !is_null($timestamp) ? date($format, $timestamp) : date($format);
}


/**
 * Returns the pull down list of skins from the bizuno-skins plugin if installed and enabled.
 */
function portalSkins() {
    if (!defined('BIZTHEMES_EASYUI')) { return [['id'=>'default', 'text'=>ucwords('default')]]; }
    $output = [];
    foreach (BIZTHEMES_EASYUI as $choice) { $output[] = ['id'=>$choice, 'text'=>$choice=='auto'?lang('auto_detect'):ucwords(str_replace('-', ' ', $choice))]; }
    return $output;
}

/**
 * Returns the pull down list of icons from the bizuno-icons plugin if installed and enabled.
 */
function portalIcons(&$icons=[]) {
    if (!defined('BIZTHEMES_ICONS')) { return [['id'=>'default', 'text'=>lang('default')]]; }
    $output = [];
    foreach (BIZTHEMES_ICONS as $choice) { $output[] = ['id'=>$choice, 'text'=>ucwords(str_replace('-', ' ', $choice))]; }
    return $output;
}

/*
 * Calculates the number of days between 2 db formatted dates.
 */
function dateNumDaysDiff($start_date, $end_date)
{
    $diff = strtotime($start_date) - strtotime($end_date);
    return ceil($diff / 86400);
}

function encryptPassword($pass, $key='')
{
    if (empty($key)) { $key = BIZUNO_KEY; }
    $peppered= hash_hmac('sha256', $pass, $key);
    return password_hash($peppered, PASSWORD_DEFAULT);
}

/**
 * Pads the index to the specified length
 * @param integer $value - value to be padded
 * @param integer $padLen - [default 6] Total length of the string
 * @return type
 */
function setMetaID($value, $padLen=6)
{
    return str_pad($value, $padLen, '0', STR_PAD_LEFT);
}

function getWalletID($cID)
{
    return 'C'.str_pad($cID, 9, '0', STR_PAD_LEFT);
}

/**
 * Takes an array and encodes it into the bizuno db string [key0:value0;key1:value1;key2:value2]
 * @param array $arrValue - array to be encoded
 */
function bizEncode($arrValue=[])
{
    $output = [];
    foreach ($arrValue as $key => $value) { $output[] = "$key:$value"; }
    return implode(';', $output);
}

/**
 * Takes a Bizuno encoded string and parses it into a keyed array
 * @param string $strValue - encoded string to parse
 */
function bizDecode($strValue='')
{
    $output= [];
    $rows  = explode(';', $strValue);
    foreach ($rows as $row) {
        $subrow = explode(':', trim($row), 2);
        $output[$subrow[0]] = !empty($subrow[1]) ? trim($subrow[1]) : '';
    }
    return $output;
}

/**
 * Generates a list of expiration dates, months/years. Typically used for credit card entry forms
 * @return array - index: months, index: years ready for pull down view
 */
function pullExpDates()
{
    $output = [];
    $output['months'][]= ['id'=>0, 'text'=>lang('select')];
    $output['years'][] = ['id'=>0, 'text'=>lang('select')];
    for ($i = 1; $i < 13; $i++) {
        $j = ($i < 10) ? '0' . $i : $i;
        $output['months'][] = ['id'=>sprintf('%02d', $i), 'text'=>$j.'-'.strftime('%B',mktime(0,0,0,$i,1,2000))];
    }
    $today = getdate();
    for ($i = $today['year']; $i < $today['year'] + 10; $i++) {
        $output['years'][] = ['id'=>strftime('%Y',mktime(0,0,0,1,1,$i)), 'text'=>strftime('%Y',mktime(0,0,0,1,1,$i))];
    }
    return $output;
}

if (!function_exists('json_validate')) { // For pre-php 8.3 installs
    function json_validate($json, $depth = 512, $flags = 0) {
        if (!is_string($json)) { return false; }
        try {
            json_decode($json, false, $depth, $flags | JSON_THROW_ON_ERROR);
            return true;
        } catch (\JsonException $e) {
            return false;
        }
    }
}

if (!function_exists('array_is_list')) { // for Pre php 8.1 installs
    function array_is_list(array $arr) {
        if ($arr === []) { return true; }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Converts an array to an object, typically used to take db entry and make an object out of it
 * @param array $arr - Source data array
 * @return object - Converted array
 */
function array_to_object($arr=[])
{
    if (!is_array($arr)) { return $arr; }
    $output = new \stdClass();
    foreach ($arr as $key => $value) {
        $output->$key = is_array($value) ? array_to_object($value) : $output->$key = $value;
    }
    return $output;
}

/**
 * Recursively converts an object to a XML string
 * @param object/array $params - Current working object, reduces as the string is built
 * @param boolean $multiple - Indicates if the current object fragment is an array (same tag)
 * @param string $multiple_key - Key of multiple, only valid if $multiple is true
 * @param integer $level - depth level of recursion
 * @param boolean $brief - (default false) Skips generation of encapsulated ![CDATA] ]]
 * @return string - XML converted string
 */
function object_to_xml($params, $multiple=false, $multiple_key='', $level=0, $brief=false)
{
    $output = NULL;
    if (!is_array($params) && !is_object($params)) { return; }
    foreach ($params as $key => $value) {
        $xml_key = $multiple ? $multiple_key : $key;
        if       (is_array($value)) {
            $output .= object_to_xml($value, true, $key, $level, $brief);
        } elseif (is_object($value)) {
            for ($i=0; $i<$level; $i++) { $output .= "\t"; }
            $output .= "<" . $xml_key . ">\n";
            $output .= object_to_xml($value, '', '', $level+1, $brief);
            for ($i=0; $i<$level; $i++) { $output .= "\t"; }
            $output .= "</" . $xml_key . ">\n";
        } else {
            if ($value <> '') {
                for ($i=0; $i<$level-1; $i++) { $output .= "\t"; }
                $output .= xmlEntry($xml_key, $value, $brief);
            }
        }
    }
    return $output;
}

/**
 * Parses an XML string to a standard class object or array
 * @param string $strXML
 * @param boolean $assoc - [default false] false returns object, true returns array
 * @return parsed XML string, either object or array
 */
function parseXMLstring($strXML, $assoc=false)
{
    $result = bizuno_simpleXML($strXML);
    if ($assoc) { // associative array
        return json_decode(str_replace(':{}',':null',json_encode($result)), true);
    } else { // object
        return json_decode(str_replace(':{}',':null',json_encode($result)));
    }
}

/**
 * Wrapper for simpleXML library as some PHP installs do not include it.
 * @param string $strXML - XML string to parse
 * @return array
 */
function bizuno_simpleXML($strXML) {
    if (!function_exists('simplexml_load_string')) {
        return msgAdd('The PHP simpleXML library is missing! Bizuno requires this library to function properly.');
    }
    libxml_use_internal_errors(true);
    $sxe = simplexml_load_string(trim($strXML), 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$sxe) {
        foreach(libxml_get_errors() as $error) { msgDebug("simpleXML error: ".$error->message, true); }
        libxml_clear_errors();
        msgAdd("There was a problem reading data from the remote server. Please try again in a few minutes.", 'trap');
        return [];
    }
    return $sxe;
}

/**
 * Generates an XML key/value pair
 * @param string $key - XML key
 * @param string $data - XML value
 * @param boolean $ignore - (default false) if true, uses date without ![CDATA] ]] encapsulation
 * @return sring - Proper XML formatted data
 */
function xmlEntry($key, $data, $ignore = false)
{
    $str = "\t<$key>";
    if ($data != NULL) {
        if ($ignore) { $str .= $data; }
        else { $str .= "<![CDATA[$data]]>"; }
    }
    $str .= "</$key>\n";
    return $str;
}

/**
 * Retrieves the default PhreeForm group id for a specific journal ID
 * @param integer $jID - Journal ID
 * @param boolean $rtnSrc - [default: false] If true returns source array, if false, just the value for the specified journal ID
 * @return string - PhreeForm Form Group encoded ID
 */
function getDefaultFormID($jID=0, $rtnSrc=false)
{
    $values = ['j0'=>'',   'j2' =>'gl:j2',    'j3' =>'vend:j3',  'j4' =>'vend:j4', 'j6' =>'vend:j6', 'j7' =>'vend:j7', 'j9' =>'cust:j9',
        'j10'=>'cust:j10', 'j12'=>'cust:j12', 'j13'=>'cust:j13', 'j14'=>'inv:j14', 'j15'=>'inv:j15', 'j16'=>'inv:j16', 'j17'=>'cust:j18',
        'j18'=>'cust:j18', 'j19'=>'cust:j19', 'j20'=>'bnk:j20',  'j21'=>'bnk:j20', 'j22'=>'bnk:j20'];
    return $rtnSrc ? $values : $values['j'.$jID];
}

/**
 * Returns with the image tag from a URL with a HTML in line icon base 64 encoded
 * @param string $url
 * @return string - HTML img tag for displaying an image
 */
function viewFavicon($url, $title='', $event=false)
{
    global $io;
    $target= $event ? "style=\"cursor:pointer\" onClick=\"winHref('$url');\" " : '';
    $parts = parse_url($url);
    if (empty($parts['host'])) { return ''; }
    if (file_exists(BIZUNO_DATA."cache/icons/{$parts['host']}.fav")) { // load the icon
        $img = file_get_contents(BIZUNO_DATA."cache/icons/{$parts['host']}.fav");
    } else {
        $href = getFavIcon($url); // try full $url
        msgDebug("\nReturned from getFavIcon with value = ".print_r($href, true));
        if (empty($href)) { $href = getFavIcon($parts['host'], $parts['scheme']); } // if empty try domain
        if (empty($href)) { // not found, use Google to guess
            try { $result = @file_get_contents("http://www.google.com/s2/favicons?domain={$parts['host']}"); }
            catch (Exception $ex) { return msgAdd("caught Google exception => ".print_r($ex, true)); }
            msgDebug("\nGoogle approach with results = ".print_r($result, true));
        } else {
            if (strpos(strtolower($href), 'http') === false) { // it's relative, add url
                $host  = "{$parts['scheme']}://{$parts['host']}";
                msgDebug("\nFetching from host = $host/".$href);
                $result= @file_get_contents($host.'/'.$href);
                if (!$result && !empty($parts['path'])) { // might be in a sub-folder
                    $host .= substr($parts['path'], 0, strrpos($parts['path'], '/'));
                    msgDebug("\nFetching from sub host = $host/".$href);
                    $result= @file_get_contents($host.'/'.$href);
                }
            } else {
                $result= @file_get_contents($href);
            }
            msgDebug("\nElse approach with results = ".print_r($result, true));
        }
        $img = base64_encode($result);
        if ($img) { $io->fileWrite($img, "cache/icons/{$parts['host']}.fav"); }
    }
    if (empty($img)) { $img = base64_encode(file_get_contents(BIZUNO_URL_FS.'0/view/images/favicon.ico')); }
    return '<img src="data:image/png;base64,'.$img.'" width="32" height="32" alt="'.$title.'" '.$target.'/>';
}

function getFavIcon($host, $scheme=false)
{
    $output = '';
    if ($scheme) { $host = "$scheme://$host"; }
    msgDebug("\nTrying url: $host");
    $site = @file_get_contents($host);
    $doc = new \DOMDocument('1.0', 'UTF-8');
    $internalErrors = libxml_use_internal_errors(true); // set error level
    $doc->strictErrorChecking = false;
    if (empty($site)) { return; }
    $doc->loadHTML($site);
    libxml_use_internal_errors($internalErrors); // Restore error level
    $xml = simplexml_import_dom($doc);
    $arr = $xml->xpath('//link[@rel="icon"]');
    if ( empty($arr)) { $arr = $xml->xpath('//link[@rel="shortcut icon"]'); } // try other option
    if (is_array($arr) && isset($arr[0])) { $output = getXmlAttribute($arr[0], 'href'); }
    return $output;
}

function getXmlAttribute($object, $attribute)
{
    if (isset($object[$attribute])) { return (string) $object[$attribute]; }
}
/**
 * Converts a array of data (also in array format) to a .csv string to export
 * @param type $rows
 */
function arrayToCSV($rows=[])
{
    $output = [];
    foreach ($rows as $row) {
        foreach ($row as $idx => $value) { $row[$idx] = csvEncapsulate($value); }
        $output[] = implode(",", $row);
    }
    return implode("\n", $output);
}

/**
 * Encapsulates a value in quotes if a comma is present in the string
 * @param string $value - Value to be cleaned
 * @return string - Source string minus CR/LF/tab characters
 */
function csvEncapsulate($value)
{
    $tmp0 = str_replace(["\r\n", "\n", "\r", "\t", "\0", "\x0B"], ' ', $value);
    $tmp1 = str_replace('"', '""', $tmp0);
    $tmp2 = strpos($value, ',') === false ? $tmp1 : '"'.$tmp1.'"';
    return $tmp2;
}

function format_uuidv4()
{
    $strong = false;
    if (function_exists('openssl_random_pseudo_bytes')) {
        $data   = openssl_random_pseudo_bytes(16, $strong);
        assert($data !== false && $strong);
    } else {
        $data = random_bytes(16);
    }
    assert(strlen($data) == 16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}
