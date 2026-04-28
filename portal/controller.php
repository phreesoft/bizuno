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
 * @version    7.x Last Update: 2026-04-27
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
        if (!$this->validateOrigin()) {
            // CSRF Layer 1: cross-origin write attempt against an authenticated session.
            // Reject before getCodex() runs — the attacker doesn't get to learn anything
            // about the target user beyond what the public sign-in page already shows.
            msgDebug("\nCSRF: rejecting bizRt={$this->route['module']}/{$this->route['page']}/{$this->route['method']} from off-origin caller");
            msgAdd('Illegal Access');
            $this->layout = ['type'=>'page', 'jsHead'=>['redir'=>"window.location='".BIZUNO_URL_PORTAL."';"]];
            return;
        }
        // CSRF Layer 2: synchronizer-token check. Defaults to warn-only mode so existing
        // browser tabs without the meta tag don't get logged out on deploy. Operators flip
        // to enforce mode by setting `define('BIZUNO_CSRF_ENFORCE', true);` in portalCFG.php
        // once the trace logs show no Layer-2 mismatches from legitimate traffic.
        if (!$this->validateCsrf()) {
            $enforce = defined('BIZUNO_CSRF_ENFORCE') && BIZUNO_CSRF_ENFORCE;
            if ($enforce) {
                msgDebug("\nCSRF Layer 2: rejecting bizRt={$this->route['module']}/{$this->route['page']}/{$this->route['method']} — token missing or mismatched");
                msgAdd('Illegal Access');
                $this->layout = ['type'=>'page', 'jsHead'=>['redir'=>"window.location='".BIZUNO_URL_PORTAL."';"]];
                return;
            }
            msgDebug("\nCSRF Layer 2 (warn-only): bizRt={$this->route['module']}/{$this->route['page']}/{$this->route['method']} — token missing or mismatched; would reject under BIZUNO_CSRF_ENFORCE=true");
        }
        if ($this->getCodex()) { compose($this->route['module'], $this->route['page'], $this->route['method'], $this->layout); }
    }

    /**
     * CSRF Layer 2 — synchronizer-token check on every authenticated bizRt route.
     *
     * The token lives in `$_SESSION['biz_csrf']` (server-minted at sign-in, rotated on
     * logout — see bizCsrfRotate(); read via bizCsrfGet()). The JS layer reads it from
     * the `<meta name="biz-csrf">` injected by setHeadEasyUI/setHeadKendoUI and attaches
     * it to every `jqBiz.ajax` call via the `beforeSend` hook in common.js.
     *
     * Token transport on the request side, in order of preference:
     *   1. `X-Bizuno-Csrf` request header  — preferred; keeps token out of URL/Referer/logs
     *   2. POST `_csrf` field              — for form submits
     *   3. GET `_csrf` query param         — last-resort fallback
     *
     * @return bool true if the token matches OR there is none to compare yet (warn-only path)
     */
    private function validateCsrf()
    {
        $supplied = '';
        if (!empty($_SERVER['HTTP_X_BIZUNO_CSRF'])) { $supplied = trim((string)$_SERVER['HTTP_X_BIZUNO_CSRF']); }
        if ($supplied === '' && !empty($_POST['_csrf'])) { $supplied = trim((string)$_POST['_csrf']); }
        if ($supplied === '' && !empty($_GET['_csrf']))  { $supplied = trim((string)$_GET['_csrf']);  }
        return bizCsrfVerify($supplied);
    }

    /**
     * CSRF Layer 1 — Origin/Referer same-site check on every authenticated bizRt route.
     *
     * Rules:
     *  - If `Origin` is present, its host must match the host derived from `BIZUNO_URL_PORTAL`.
     *  - Else if `Referer` is present, its host must match.
     *  - Else (cold navigation: bookmark, address-bar typing, link from a no-referrer
     *    source) — allow. The session cookie carries `SameSite=lax`, so a no-referrer
     *    cross-site request can't ride the user's session anyway.
     *
     * Method-agnostic: bizuno's `jsonAction()` issues GETs for state-changing routes,
     * so a POST-only check would miss the dominant write pattern.
     *
     * Scheme is *not* compared — many production installs run behind a TLS-terminating
     * reverse proxy where PHP sees `HTTPS=off` while the browser sends `Origin: https://…`.
     * Host comparison is sufficient for CSRF; the `Secure` flag on the session cookie
     * already guarantees credentials only travel over HTTPS in a properly-configured setup.
     *
     * @return bool true to allow the request, false to reject
     */
    private function validateOrigin()
    {
        $origin  = isset($_SERVER['HTTP_ORIGIN'])  ? trim((string)$_SERVER['HTTP_ORIGIN'])  : '';
        $referer = isset($_SERVER['HTTP_REFERER']) ? trim((string)$_SERVER['HTTP_REFERER']) : '';
        if ($origin === '' && $referer === '') { return true; } // cold nav; SameSite=lax handles the cookie story
        $supplied = $origin !== '' ? $origin : $referer;
        return $this->originHostMatches($supplied);
    }

    private function originHostMatches($url)
    {
        if (!defined('BIZUNO_URL_PORTAL') || !BIZUNO_URL_PORTAL) {
            msgDebug("\noriginHostMatches: BIZUNO_URL_PORTAL not defined; cannot validate.");
            return false;
        }
        $expected = parse_url(BIZUNO_URL_PORTAL);
        $actual   = parse_url($url);
        if (!$expected || !$actual || empty($expected['host']) || empty($actual['host'])) {
            return false;
        }
        if (strcasecmp($expected['host'], $actual['host']) !== 0) { return false; }
        // If both URLs declare an explicit port, require equality. Otherwise accept (default ports).
        $expPort = $expected['port'] ?? null;
        $actPort = $actual['port']   ?? null;
        if ($expPort !== null && $actPort !== null && $expPort !== $actPort) { return false; }
        return true;
    }
    private function getCodex()
    {
        global $bizunoUser;
        bizAutoLoad(BIZUNO_FS_LIBRARY.'locale/currency.php','currency');
        $this->loadLanguage(); // Just load the minimal language for the portal operation, more can be loaded as needed
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
        // typical case, cookie not expired, now have user, email and role.
        // IP pinning is now a /16 (IPv4) or /48 (IPv6) prefix match instead of full equality —
        // exact pinning kicked mobile/CGNAT users back to login every time their carrier rotated
        // their public IP. The HMAC + Secure + HttpOnly + SameSite=lax stack added by the
        // session-cookie hardening work is the primary defense; this prefix check is now
        // defense-in-depth catching gross network jumps (stolen cookie replayed from a
        // different ISP/continent) without breaking legit mobile sessions.
        if (is_array($this->creds) && sizeof($this->creds)==5 && $this->ipPrefixMatch($this->creds[4], $_SERVER['REMOTE_ADDR'])) {
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

    /**
     * Returns true iff $a and $b share the same network prefix — /16 for IPv4, /48 for IPv6.
     * Used by validateCookie() to permit IP changes within a carrier's CGNAT pool while still
     * rejecting cookies replayed from an entirely different network.
     */
    private function ipPrefixMatch($a, $b)
    {
        if ($a === '' || $b === '' || !is_string($a) || !is_string($b)) { return false; }
        if ($a === $b) { return true; } // fast path, common case
        // IPv4: compare top two octets.
        if (strpos($a, '.') !== false && strpos($b, '.') !== false) {
            $aP = explode('.', $a, 3);
            $bP = explode('.', $b, 3);
            return isset($aP[1], $bP[1]) && $aP[0] === $bP[0] && $aP[1] === $bP[1];
        }
        // IPv6: compare top three hextets (/48).
        if (strpos($a, ':') !== false && strpos($b, ':') !== false) {
            $aBin = @inet_pton($a);
            $bBin = @inet_pton($b);
            if ($aBin === false || $bBin === false) { return false; }
            return substr($aBin, 0, 6) === substr($bBin, 0, 6);
        }
        return false; // mixed v4/v6 between cookie and current request — treat as a network change
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
