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
 * @version    7.x Last Update: 2026-06-07 (logout: clear sessionStorage('bizuno') on redirect so the next login reloads a fresh browser session cache)
 * @filesource /portal/api.php
 */

namespace bizuno;

class portalApi
{
    private $ShipTaxSt= ['AR','CT','GA','IL','KS','KY','MI','MS','NE','NJ','NM','NY',
        'NC','ND','OH','OK','PA','RI','SC','SD','TN','TX','UT','VT','WA','WV','WI'];
    public  $lang;
    
    function __construct()
    {
    }

    public function fs()
    {
        $fn   = $fBad = $eBad = false;
        $parts= explode('/', clean('src', 'path_rel', 'get'), 2);
        msgDebug("\nBIZUNO_DATA = ".(defined('BIZUNO_DATA') ? BIZUNO_DATA : 'undefined')." and parts = ".print_r($parts, true));
        if (defined('BIZUNO_DATA') && !empty(BIZUNO_DATA)) {
            if (!empty($parts[1])) {
                $io   = new io(); // needs BIZUNO_DATA
                // Dispatch by path prefix:
                //   'view/…'         → BIZUNO_FS_LIBRARY/view/…   (Bizuno-shipped UI assets:
                //                                                  icons, css, js, fonts)
                //   '' (empty[0])    → BIZUNO_FS_LIBRARY/…        (legacy — the cleaner
                //                                                  strips leading slashes
                //                                                  so this path is rarely
                //                                                  reached via URL, kept
                //                                                  for back-compat)
                //   anything else    → BIZUNO_DATA/parts[1]       (business-uploaded files,
                //                                                  with parts[0] as the
                //                                                  bizID prefix dropped from
                //                                                  the path — DATA is
                //                                                  already business-scoped)
                if ($parts[0] === 'view') {
                    $base = BIZUNO_FS_LIBRARY;
                    $fn   = $base.'view/'.$parts[1];
                } elseif (empty($parts[0])) {
                    $base = BIZUNO_FS_LIBRARY;
                    $fn   = $base.$parts[1];
                } else {
                    $base = BIZUNO_DATA;
                    $fn   = $base.$parts[1];
                }
                $ext  = strtolower(pathinfo($parts[1], PATHINFO_EXTENSION));
                msgDebug("\nLooking for fn = $fn");
                $fBad = !file_exists($fn) ? true : false;
                $validExts = array_merge($io->getValidExt('image'), $io->getValidExt('script'));
                $eBad = !in_array($ext, $validExts) ? true : false;
                // Path-traversal containment: resolve the requested file with realpath()
                // and require it to live inside the chosen base directory. Without this,
                // `path_rel` (which only normalizes slashes) would happily pass through
                // `..` segments — letting any attacker with knowledge of the host fs read
                // any .png/.css/.js file the web user can open.
                if (!$fBad && !$this->fsPathContained($fn, $base)) { $fBad = true; }
            } else { $fBad = true; }
        } else { $fBad = true; }
        msgDebug("\neBad = $eBad and fBad = $fBad");
        if ($eBad || $fBad) { $fn = BIZUNO_FS_LIBRARY.'view/images/bizuno.png'; }
        // Send out the image
        header("Accept-Ranges: bytes");
        header("Content-Type: "  .getMimeType($fn));
        header("Content-Length: ".filesize($fn));
        header("Last-Modified: " .date(DATE_RFC2822, filemtime($fn)));
//msgDebugWrite();
        readfile($fn);
        exit();
    }

    /**
     * Returns true iff `realpath($fn)` lies inside `realpath($base)`. Trailing
     * separator on the base prevents prefix-matching `/var/www/data` against
     * `/var/www/data-other`. Mirrors the containment idiom in `model/io.php::validatePath()`,
     * but parameterized by base so this method can guard both BIZUNO_DATA and
     * BIZUNO_FS_LIBRARY (the two roots `fs()` legitimately serves from).
     */
    private function fsPathContained($fn, $base)
    {
        $real     = realpath($fn);
        $baseReal = realpath($base);
        if ($real === false || $baseReal === false) { return false; }
        $baseReal = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strncmp($real . DIRECTORY_SEPARATOR, $baseReal, strlen($baseReal)) !== 0) {
            msgDebug("\nfs(): path-traversal attempt blocked — $real not inside $baseReal");
            return false;
        }
        return true;
    }

    /**
     * Generates the css for the users theme preference, also adds myExt icons
     * This needs to be 
     * @param type $layout
     */
    public function viewCSS()
    {
        $icnSet = clean('icons', ['format'=>'cmd','default'=>'default'], 'get');
        $path   = BIZUNO_FS_LIBRARY.'view/icons/';
        $pathURL= ( defined('BIZUNO_URL_VIEW') ? BIZUNO_URL_VIEW : BIZUNO_URL_PORTAL ) . '/view/icons/'; // BIZUNO_URL_VIEW is the new way
        if (!file_exists("{$path}$icnSet.php")) { $icnSet = 'default'; }// icons cannot be found, use default
        $icons = [];
        $output="/* $icnSet */\n";
        require("{$path}$icnSet.php");
        foreach ($icons as $idx => $icon) {
            $output .= ".icon-$idx  { background:url('{$pathURL}$icnSet/16x16/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconM-$idx { background:url('{$pathURL}$icnSet/24x24/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconL-$idx { background:url('{$pathURL}$icnSet/32x32/{$icon['path']}') no-repeat; }\n";
        }
        if (defined('BIZUNO_DATA')) {
            $this->addCSS($output, 16);
            $this->addCSS($output, 32);
        }
        header("Content-type: text/css; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    /**
     * 
     * @param type $output
     * @param type $type
     * @param type $size
     */
    private function addCSS(&$output, $size=32)
    {
        $dirPath= BIZUNO_DATA    ."myExt/view/icons/{$size}x{$size}/";
        $dirURL = BIZUNO_URL_FS.(defined('BIZUNO_BIZID')?BIZUNO_BIZID:'0')."/myExt/view/icons/{$size}x{$size}/";
        $suffix = $size == 32 ? 'L' : '';
        $output .= "/* $dirURL */\n";
        if (is_dir($dirPath)) {
            $icons = scandir($dirPath);
            foreach ($icons as $icon) {
                if ($icon=='.' || $icon=='..') { continue; }
                $path_parts = pathinfo($icon);
                $output .= ".icon{$suffix}-{$path_parts['filename']} { background:url('{$dirURL}$icon') no-repeat; }\n";
            }
        }
    }

    /**
     * Builds the jQuery EasyUI extensions into a single loaded script
     */
    public function easyuiJS()
    {
        // scripts/ lives at the webroot (web-served directly), NOT under src/ —
        // resolve via BIZUNO_FS_PORTAL. Pre-Phase 2 these two constants were the
        // same value so the difference didn't matter; post-Phase 2 BIZUNO_FS_LIBRARY
        // points at src/ and using it here produced an empty `file_get_contents()`
        // result → empty JS response → "dashContainer.portal is not a function".
        $basePath = BIZUNO_FS_PORTAL.'scripts/jquery-easyui-ext';
        $output  = '';
        $output .= file_get_contents("$basePath/portal/jquery.portal.js")           ."\n"; // Portal
        $output .= file_get_contents("$basePath/color/jquery.color.js")             ."\n"; // Color
        $output .= file_get_contents("$basePath/edatagrid/jquery.edatagrid.js")     ."\n"; // Editable DataGrid
        $output .= file_get_contents("$basePath/datagrid-filter/datagrid-filter.js")."\n"; // Datagrid Filter
        $output .= file_get_contents("$basePath/datagrid-dnd/datagrid-dnd.js")      ."\n"; // Datagrid Drag-n-Drop Rows
        $output .= file_get_contents("$basePath/texteditor/jquery.texteditor.js")   ."\n"; // Text Editor
        header("Content-type: text/javascript; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    /**
     * Builds the jQuery EasyUI extensions into a single loaded file, remaps icons to proper path on www.bizuno.com
     */
    public function easyuiCSS()
    {
        // Same scripts/-is-at-webroot reasoning as easyuiJS above.
        $basePath= BIZUNO_FS_PORTAL.'scripts/jquery-easyui-ext';
        $icons   = [];
        $output  = '';
        $output .= file_get_contents("$basePath/texteditor/texteditor.css")   ."\n"; // Text Editor
        $this->mapImagePath($icons, $basePath, 'texteditor'); // map icons for this extension
        $output .= "\n".implode("\n", $icons);
        msgDebugWrite();
        header("Content-type: text/css; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    private function mapImagePath(&$icons, $basePath, $extName)
    {
        $imgPath = "$basePath/$extName/images";
        if (!is_dir($imgPath)) { return; }
        $urlPath = BIZUNO_URL_SCRIPTS."jquery-easyui-ext/$extName";
        $files = scandir($imgPath);
        foreach ($files as $file) {
            if ($file == "." || $file == "..") { continue; }
            $name   = substr($file, 0, strpos($file, '.'));
            $icons[]= ".icon-$name { background: url('$urlPath/images/$file') center center; }\n";
        }
    }

    public function lostPW(&$layout=[])
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->lostCreds($layout);
    }

    public function lostVal(&$layout=[])
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->lostNewPW($layout);
    }

    /**
     * Signs a user out of Bizuno, destroys the cookie
     */
    public function logout(&$layout=[])
    {
        bizClrCookie('bizunoSession');
        // CSRF Layer 2: rotate the synchronizer token so a token from this session can't
        // be reused after the user signs back in (defense-in-depth against session-fixation).
        if (function_exists("\\bizuno\\bizCsrfRotate")) { bizCsrfRotate(); }
        // Clear the cached browser session so the next login forces a fresh loadBrowserSession
        // (otherwise stale bizDefaults — e.g. an empty taxRates set — survive across re-login).
        $layout = array_replace_recursive($layout, ['type'=>'page', 'jsHead'=>['redir'=>"sessionStorage.removeItem('bizuno'); window.location='".BIZUNO_URL_PORTAL."';"]]);
        if (function_exists("\\bizuno\\portalLogout")) { portalLogout($layout); }
    }

    public function getBizRoles(&$layout=[])
    {
        $bizID = clean('bizID', 'alpha_num', 'get');
//      $bizDB = clean('bizDB', 'alpha_num', 'get');
        msgDebug("\nEntering getRoles with bizID = $bizID");
        if (empty($bizID) || !$this->validatePSrequest($bizID)) { return msgAdd('Illegal Access!'); }
        $result= dbMetaGet('%', 'bizuno_role');
        $roles = [];
        if (!empty($result)) {
            foreach ($result as $row) { $roles[] = ['roleID'=>$row['_rID'], 'label'=>$row['title']]; }
        } else { $roles = []; }
        $layout = array_replace_recursive($layout, ['content'=>['roles'=>$roles]]);
    }

    public function getSalesTax(&$layout=[])
    {
        $args  = [
            'total'   => clean('total',  'float',    'post'),
            'shipping'=> clean('freight','float',    'post'),
            'city'    => clean('city',   'text',     'post'),
            'state'   => clean('state',  'text',     'post'),
            'zipCode' => clean('zip',    'text',     'post'),
            'country' => clean('country','alpha_num','post')];
        // sanity check
        msgDebug("\nEntering getSalesTax with args = ".print_r($args, true));
        $this->cleanZip($args['zipCode'], $args['country']);
        if (empty($args['zipCode'])) { return msgAdd('There are no rates to display, postal code was not provided!'); }
        // see if shipping is taxable
        $taxShip = in_array($args['state'], $this->ShipTaxSt) ? true : false;
        // check for other state specific parameters

        // retrieve from the tax table
        $rate  = dbGetValue('sales_tax_map', 'tax_rate', "zipcode='{$args['zipCode']}'");
        msgDebug("\nresult of db query rate = ".print_r($rate, true));
        $tax   = !$taxShip ? floatval($args['total']) * $rate : (floatval($args['total']) + floatval($args['shipping'])) * $rate;
        $layout= ['type'=>'raw', 'content'=>json_encode(['sales_tax'=>round($tax, 2), 'rate'=>$rate, 'msg'=>''])];
    }

    private function cleanZip(&$zip, $country='USA')
    {
        $zip = preg_replace("/[^a-zA-Z0-9]/", "", strtoupper($zip));
        switch ($country) {
            case 'CA':
            case 'CAN': $zip = substr(trim($zip), 0, 6); break;
            case 'US':
            case 'USA': $zip = substr(trim($zip), 0, 5); break;
            default: // leave it alone
        }
    }

    public function shipGetRates(&$layout=[])
    {
        if (!$this->validateApiToken()) { return; }
        loadBusinessCache();
        compose('api', 'shipping', 'getRates', $layout);
    }

    public function orderAdd(&$layout=[])
    {
        if (!$this->validateApiToken()) { return; }
        loadBusinessCache();
        if (!$this->loadApiUserContext()) { return; }
        // Escalation kept for backward-compatibility with existing integrations: a
        // verified token caller needs prices_c + j10_mgr + j12_mgr to add a sales
        // order or cash receipt. Configure the API user with a role that already
        // grants these to drop the explicit override.
        $security = getUserCache('role', 'security');
        $security['prices_c'] = max((int)($security['prices_c'] ?? 0), 2);
        $security['j10_mgr']  = max((int)($security['j10_mgr']  ?? 0), 2);
        $security['j12_mgr']  = max((int)($security['j12_mgr']  ?? 0), 2);
        setUserCache('role', 'security', $security);
        compose('api', 'order', 'add', $layout);
    }

    /**
     * Executes an EDI cron to poll ALL EDI sources for new orders.
     * Invocation: https://biz.mydomain.com?bizRt=portal/api/ediCron&token=<api_token>
     */
    public function ediCron(&$layout=[])
    {
        if (!$this->validateApiToken()) { return; }
        loadBusinessCache();
        if (!$this->loadApiUserContext()) { return; }
        msgDebug("\nUser has been set, ready to compose");
        compose('phreebooks', 'ediAPI', 'ediGet', $layout);
    }
    
    /**
     * Pass-through to a customer-supplied `myExt/controllers/api/myAPI.php` extension.
     *
     * **Unauthenticated by design.** This route exists so deployments can host their own
     * cron/webhook endpoints (battery-store's `?bizRt=portal/api/myAPI&modID=mectronic`
     * fetches inventory from a supplier; similar patterns exist for `digikey`,
     * `netComponents`). The portal layer cannot know what auth the customer's extension
     * needs, so it deliberately runs `goAction()` without a token check.
     *
     * **Contract for myExt authors:** the loaded `apiMyAPI::goAction()` MUST do its own
     * authentication before performing any state change. Patterns we recommend:
     *   - Match a shared secret in a header or query string (similar to `validateApiToken()`)
     *   - Check `$_SERVER['REMOTE_ADDR']` against an allowlist of known cron sources
     *   - For purely-public lookups (price feeds, status pings), document the public surface
     *     and keep responses idempotent and rate-limited
     *
     * Without auth inside the extension, anything `goAction()` does is reachable by anyone
     * on the internet. This route survives in `validateApiToken()` deliberately because
     * `goAPI()` never calls `validateApiToken()` for it — see `portal/controller.php::goAPI()`.
     *
     * @param type $layout
     */
    public function myAPI()
    {
        if (file_exists(BIZUNO_DATA.'myExt/controllers/api/myAPI.php')) {
            require(BIZUNO_DATA.'myExt/controllers/api/myAPI.php');
            loadBusinessCache();
            $ctl = new apiMyAPI();
            $ctl->goAction();
        }
    }

    /************************ Support Methods **************************/
    /**
     * IP-allowlist gate for the PhreeSoft mothership routes (`getBizRoles`, `getSalesTax`).
     *
     * Layered:
     *   1. `REMOTE_ADDR === PHREESOFT_IP`        — primary check, original behavior.
     *   2. (optional) `validateApiToken()` match — second factor when `api_token` is set.
     *
     * Step 2 hardens against the proxy-misconfiguration footgun the audit flagged: if a
     * reverse proxy sets `REMOTE_ADDR` from `X-Forwarded-For`, an attacker forging that
     * header bypasses step 1. With the token configured, they'd also need the secret to
     * pass step 2. When `api_token` isn't configured, step 2 is skipped — preserves
     * back-compat for installs that have always run with IP-only and don't have the
     * mothership configured to send the token yet.
     */
    private function validatePSrequest($bizID='')
    {
        msgDebug("\nEntering validatePSrequest with bizID = $bizID and remote address = ".$_SERVER['REMOTE_ADDR']." and PHREESOFT_IP = ".PHREESOFT_IP);
        if (PHREESOFT_IP != $_SERVER['REMOTE_ADDR']) { return false; }
        loadBusinessCache();
        $expected = (string)getModuleCache('api', 'settings', 'phreesoft_api', 'api_token', '');
        if ($expected === '') { return true; } // back-compat: no token configured, IP-only
        // Token configured — require it as a second factor. Reuses the same header/POST/GET
        // sources as validateApiToken() but inlined to avoid the "fail if no token configured"
        // path (step 1 already proves it's PhreeSoft, we just want extra assurance).
        $supplied = isset($_SERVER['HTTP_X_BIZUNO_TOKEN']) ? trim((string)$_SERVER['HTTP_X_BIZUNO_TOKEN']) : '';
        if ($supplied === '') { $tok = clean('token', 'cmd', 'post'); if (is_string($tok) && $tok !== '') { $supplied = $tok; } }
        if ($supplied === '') { $tok = clean('token', 'cmd', 'get');  if (is_string($tok) && $tok !== '') { $supplied = $tok; } }
        if ($supplied === '' || !hash_equals($expected, $supplied)) {
            msgDebug("\nvalidatePSrequest: IP matched but api_token check failed (token configured, no/invalid value supplied)");
            return false;
        }
        return true;
    }

    /**
     * Constant-time check of the caller-supplied API token against api.settings.phreesoft_api.api_token.
     * Token sources, in order: `X-Bizuno-Token` request header, then POST `token`, then GET `token`.
     * Header is preferred — keeps the secret out of URL logs / Referer / shell history.
     * Fails closed if the token is not configured — an unconfigured install cannot be exploited
     * via these routes at all.
     */
    private function validateApiToken()
    {
        loadBusinessCache(); // settings live in business cache; safe to call multiple times
        $expected = (string)getModuleCache('api', 'settings', 'phreesoft_api', 'api_token', '');
        if ($expected === '') {
            msgDebug("\napi_token not configured — refusing access to portal API endpoint.");
            msgAdd('Illegal Access');
            return false;
        }
        $supplied = isset($_SERVER['HTTP_X_BIZUNO_TOKEN']) ? trim((string)$_SERVER['HTTP_X_BIZUNO_TOKEN']) : '';
        if ($supplied === '') {
            $tok = clean('token', 'cmd', 'post');
            if (is_string($tok) && $tok !== '') { $supplied = $tok; }
        }
        if ($supplied === '') {
            $tok = clean('token', 'cmd', 'get');
            if (is_string($tok) && $tok !== '') { $supplied = $tok; }
        }
        if ($supplied === '' || !hash_equals($expected, $supplied)) {
            msgDebug("\nportal API token mismatch (supplied len=".strlen($supplied).")");
            msgAdd('Illegal Access');
            return false;
        }
        return true;
    }

    /**
     * After validateApiToken() passes, bind the request to the configured API user so
     * downstream compose() calls operate as a real account (with that user's role/permissions)
     * instead of the empty cache. Mirrors the original ediCron() bootstrap.
     */
    private function loadApiUserContext()
    {
        $email = (string)getModuleCache('api', 'settings', 'phreesoft_api', 'api_user', '');
        if ($email === '') {
            msgDebug("\nphreesoft_api.api_user is not configured.");
            msgAdd('API user not configured');
            return false;
        }
        $userID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "ctype_u='1' AND email='".addslashes($email)."'");
        if (empty($userID)) {
            msgDebug("\nphreesoft_api.api_user '$email' did not resolve to a contact row.");
            msgAdd('API user not found');
            return false;
        }
        $profile = getMetaContact($userID, 'user_profile');
        setUserCache('profile', '', $profile);
        $role = !empty($profile['role_id']) ? dbMetaGet($profile['role_id'], 'bizuno_role') : [];
        setUserCache('role', '', $role);
        msgDebug("\nportal API context loaded as user $email (id=$userID).");
        return true;
    }
}