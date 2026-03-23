<?php
/**
 * Bizuno Portal entry point
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
 * @version    7.x Last Update: 2026-03-20
 * @filesource /portal/controller.php
 */

namespace bizuno;

use lbuchs\WebAuthn\WebAuthn;

class portalCtl
{
    public  $layout       = []; // Holds the structure for the output display
    private $bizTimer     = 60 * 60 * 8; // reload business cache every 8 hours
    private $userValidated= false;
    private $needsInstall = false;
    private $needsMigrate = false;
    private $creds;
    private $route;
 
    function __construct()
    {
        global $msgStack, $io, $cleaner;
        if (session_status() === PHP_SESSION_NONE) { session_start(); }
        $msgStack= new messageStack();
        $io      = new io();
        $cleaner = new cleaner();

//bizClrCookie('bizunoUser');
//bizClrCookie('bizunoSession');

        $this->creds = getUserCookie();
        $this->userValidated = !empty($this->creds) ? true : false; // validate user
        $this->route = $this->cleanBizRt();
        $this->setDOM(); // load the applicable GUI
        $scope       = $this->getScope(); // get scope
        switch ($scope) { // act accordingly
            case 'api':    $this->goAPI();     break; // API request, may be logged in or guest
            case 'auth':   $this->goAuth();    break; // Normal operation for authorized users
            default:
            case 'guest':  $this->goGuest();   break; // Shows login screen, handles API requests and other things when user is not logged in 
            case 'install':$this->goInstall(); break; // Shows install screen, after verifying credentials
            case 'migrate':$this->goMigrate(); break; // Shows migrate screen after verifying credentials
        }
        new view($this->layout);
    }

    private function setDOM()
    {
        global $html5;
        $modsEasy= ['administrate','api','bizuno','common','contacts','inventory','payment','phreebooks','phreeform','quality','shipping'];
        bizAutoLoad(BIZUNO_FS_LIBRARY.'view/main.php', 'view');
        $ui = !in_array($this->route['module'], $modsEasy) ? 'kendoUI' : 'easyUI';
        bizAutoLoad(BIZUNO_FS_LIBRARY."view/$ui/html5.php", 'html5');
        $html5 = new html5();
    }

    private function getScope()
    {
        global $db;
        if (function_exists("\\bizuno\\portalGetScope")) { return portalGetScope($this->route['page'], $this->userValidated); } // Hook for customization
        msgDebug("\nEntering getScope in library");
        if (!defined('BIZUNO_DB_CREDS')) { msgDebug("\nBIZUNO_DB_CREDS not defined, returning guest"); return 'guest'; } // Path to db not defined, needs install and creds set
        $creds= defined('BIZUNO_DB_CREDS') ? BIZUNO_DB_CREDS : [];
        $db   = new db($creds);
        msgDebug("\nConnected to db with result = ".($db->connected ? 'connected'  : 'NOT CONNECTED!' ));
        // This test first or standalone new installs breaks loading of css/js files
        if ('portal'==$this->route['module'] && 'api'==$this->route['page'])          { msgDebug("\nAPI Request, returning api");         return 'api'; }
        if (!$db->connected       || !dbTableExists(BIZUNO_DB_PREFIX.'configuration')){ msgDebug("\nDB not connected, returning install");return 'install'; }
        if ( dbTableExists(BIZUNO_DB_PREFIX.'address_book'))                          { msgDebug("\nNeed to migrate, returning migrate"); return 'migrate'; }
        if ( $this->userValidated &&  dbTableExists(BIZUNO_DB_PREFIX.'common_meta'))  { msgDebug("\nNormal operation, returning auth");   return 'auth'; }
        msgDebug("\nFalling through, returning guest.");
        return 'guest';
    }
    /**
     * Handles requests when user has not been authenticated
     * @param type $layout
     */
    private function goGuest()
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewAuth.php');
        $view = new portalViewAuth();
        $view->login($this->layout);
    }
    /**
     * Handles API requests when user has not been authenticated, not necessarily secure!
     * FOR PUBLIC DATA ONLY UNLESS AUTHENTICATED IN SCRIPT
     * @param type $layout
     */
    private function goAPI()
    {
        require(BIZUNO_FS_LIBRARY . 'portal/api.php');
        $view = new portalApi();
        $method = $this->route['method'];
        if (method_exists($view, $method)) {
            $this->loadLanguage($lang='en_US');
            msgDebug("\nProcessing API request {$method}");
            $view->$method($this->layout);
            return;
        }
        msgDebug("\nAPI request {$method} WAS NOT FOUND, falling through to sign in!");
        require(BIZUNO_FS_LIBRARY . 'portal/viewAuth.php');
        $guest = new portalViewAuth(); // Fall through to login screen
        $guest->login($this->layout);
    }
    private function goInstall()
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->install($this->layout);
    }
    private function goMigrate()
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->migrate($this->layout);
    }
    private function goAuth()
    {
        if ($this->getCodex()) { compose($this->route['module'], $this->route['page'], $this->route['method'], $this->layout); }
    }
    private function getCodex()
    {
        global $mixer, $bizunoUser;
        bizAutoLoad(BIZUNO_FS_LIBRARY.'locale/currency.php','currency');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'model/encrypter.php','encryption');
        $this->loadLanguage(); // Just load the minimal language for the portal operation, more can be loaded as needed
        $mixer     = new encryption();
        $bizunoUser= $this->setGuestCache();
        $this->validateCookie(); // Validates sign in status
        if (!$this->userValidated) { // not logged in, changed ip's (laptop changing locations) or an attack in progress, sign out
            $this->layout = ['type'=>'page', 'jsHead'=>['redir'=>"window.location='".BIZUNO_URL_PORTAL."';"]];
            return;
        }
        $this->initUserCache();
        $this->initBusinessCache();
        $this->cacheValidate();
        $this->validateVersion();
        return true;
    }

    public function setGuestCache()
    {
        msgDebug("\nEntering setGuestCache");
        return [
            'profile'   => ['userID'=>0, 'email'=>'', 'language'=>'en_US'],
            'business'  => ['bizID' =>defined('BIZUNO_BIZID') ? BIZUNO_BIZID : 0],
            'dashboards'=> []];
    }

    private function validateCookie()
    {
        msgDebug("\nEntering validateCookie.");
        // typical case, cookie not expired, now have user, email and role
        if (is_array($this->creds) && sizeof($this->creds)==5 && $this->creds[4]==$_SERVER['REMOTE_ADDR']) {
            setUserCache('profile', 'userID',  $this->creds[0]);
            setUserCache('profile', 'admin_id',$this->creds[0]); // DEPRECATED - for compatibility to older versions
            setUserCache('profile', 'psID',    $this->creds[1]);
            setUserCache('profile', 'email',   $this->creds[2]);
            setUserCache('profile', 'userRole',$this->creds[3]);
            $this->userValidated = true;
        } else { // computer changed networks, stolen cookie, etc.
            bizClrCookie('bizunoSession');
            $this->userValidated = false;
        }
        setlocale(LC_COLLATE,getUserCache('profile', 'language'));
        setlocale(LC_CTYPE,  getUserCache('profile', 'language'));
        msgDebug("\nLeaving validateUser with user validated = ".($this->userValidated?'true':'false'));
    }

    private function initUserCache()
    {
        if (empty($this->userValidated)) { return; }
        $roleID = getUserCache('profile', 'userRole');
        msgDebug("\nEntering initUserCache with roleID = $roleID");
        $profile = array_replace(getUserCache('profile'), getMetaContact(getUserCache('profile', 'userID'), 'user_profile'));
        setUserCache('profile', '', $profile);
        $role = dbMetaGet($roleID, 'bizuno_role');
        setUserCache('role', '', $role);
        msgDebug("\nLeaving initUserCache with administrate = ".getUserCache('role', 'administrate'));
    }

    private function initBusinessCache()
    {
        global $currencies;
        msgDebug("\nEntering initBusinessCache");
        if ($this->needsInstall) { $this->cacheReload('guest'); }
        else { // normal operation
            msgDebug("\nBizuno is installed, loading cache");
            loadBusinessCache();
            if (biz_date('Y-m-d') > getModuleCache('phreebooks', 'fy', 'period_end')) { periodAutoUpdate(false); }
            date_default_timezone_set(getModuleCache('bizuno', 'settings', 'locale', 'timezone'));
        }
        if ($this->needsMigrate) { $this->cacheReload('migrate'); } // limit the dashboard list to just the portal
        $currencies = new currency(); // Needs PhreeBooks cache loaded to properly initialize otherwise defaults to USD
    }

    private function validateVersion()
    {
        $dbVer = getModuleCache('bizuno', 'properties', 'version');
        msgDebug("\nValidating installed Bizuno version ".MODULE_BIZUNO_VERSION." to db version: $dbVer");
        if (empty(getUserCache('business', 'bizID')) || empty(getUserCache('profile', 'email'))) { $this->cacheReload('guest'); return; } // not logged in
        if (version_compare($dbVer, MODULE_BIZUNO_VERSION) < 0) {
            msgDebug("\nDB is downlevel, upgrading!");
            bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/upgrade.php');
            bizunoUpgrade();
        }
    }

    private function cacheValidate()
    {
        if (empty($this->userValidated)) { return; }
        $cacheExp = bizCacheExpGet();
        msgDebug("\nIn cacheValidate with Cache expiration time = $cacheExp and time = ".time());
        if ($cacheExp < time()) { // cache expired
            msgDebug("\n  Cache expired! reloading...");
            $this->cacheReload();
        } else { msgDebug("\n  Cache is valid! NOT reloading..."); }
    }

    private function cacheReload()
    {
        msgDebug("\nEntering reloadCache");
        if (!bizAutoLoad(BIZUNO_FS_LIBRARY.'model/registry.php', 'bizRegistry')) { return msgAdd("Cannot locate bizRegistry. BIZUNO_FS_LIBRARY = ".BIZUNO_FS_LIBRARY); }
        $registry = new bizRegistry();
        $registry->initRegistry(getUserCache('profile', 'email'), getUserCache('business', 'bizID'));
        bizCacheExpSet(time() + $this->bizTimer);
    }

    private function loadLanguage($lang='en_US')
    {
        global $bizunoLang;
        $myLang = clean('bizunoLang', ['format'=>'cmd', 'default'=>'en_US'], 'get');
        if ($myLang<>'en_US') { $lang = $myLang; }
        $bizunoLang = loadBaseLang($lang);
    }

    private function cleanBizRt()
    {
        $value = isset($_GET['bizRt']) ? preg_replace("/[^a-zA-Z0-9\/]/", '', $_GET['bizRt']) : '';
        if (substr_count($value, '/') != 2) { // check for valid structure, else home
            msgDebug("\nNo path sent, overriding with userValidated = ".$this->userValidated);
            $_GET['menuID'] = 'home';
            $value = 'bizuno/main/bizunoHome';
        }
        $temp = explode('/', $value, 3);
        if (!$this->userValidated && !in_array($temp[1], ['api'])) { // not logged in or not installed, restrict to portal api class
            msgDebug("\nNot logged in or not installed, restrict to parts of module bizuno");
            $temp = ['bizuno', 'main', 'bizunoHome'];
        }
        $GLOBALS['bizunoModule'] = $temp[0];
        $GLOBALS['bizunoPage']   = $temp[1];
        $GLOBALS['bizunoMethod'] = preg_replace("/[^a-zA-Z0-9\_\-]/", '', $temp[2]); // remove illegal characters
        return ['module'=>$GLOBALS['bizunoModule'] , 'page'=>$GLOBALS['bizunoPage'], 'method'=>$GLOBALS['bizunoMethod'] ];
    }
}
