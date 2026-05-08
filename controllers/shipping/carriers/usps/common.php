<?php
/*
 * Shipping extension for USPS RESTful APIs - Common
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
 * @version    7.x Last Update: 2026-05-08
 * @filesource /controllers/shipping/carriers/usps/common.php
 *
 * Docs: https://developer.usps.com/  (specs in /Documents/USPS RESTFul API)
 *
 * Talks directly to USPS — no PhreeSoft proxy. Customer registers their app at
 * developers.usps.com and supplies client_id + client_secret + EPS account number.
 */

namespace bizuno;

class uspsCommon
{
    public    $moduleID = 'shipping';
    public    $methodDir= 'carriers';
    public    $code     = 'usps';
    protected $secID    = 'shipping';
    public    $defaults;
    public    $options;
    public    $settings;
    public    $weightUOM;
    public    $dimUOM;
    public    $ship_pkg;
    public    $ship_pickup;
    public    $confirm_type;
    public    $contact_type;

    // Production / test hostnames per the v3 specs. The same root serves all
    // resource APIs (oauth2, addresses, prices, labels, payments, tracking),
    // each at its own path prefix — kept as one base so mode switches in a
    // single place.
    private $hostProd = 'https://apis.usps.com';
    private $hostTest = 'https://apis-tem.usps.com';

    public $lang = ['title' => 'USPS Direct',
        'acronym'    => 'USPS',
        'description'=> 'United States Postal Service direct REST integration. Rates, labels and tracking against your own USPS Developer Portal app and Enterprise Payment Account.',
        'instructions' => '<h3>Step 1. Register an App</h3>
    <p>Sign in at <a href="https://developer.usps.com/" target="_blank"><b>developer.usps.com</b></a>, register an app, and request access to the <b>Addresses</b>, <b>Prices</b>, <b>Labels</b>, <b>Payments</b> and <b>Tracking</b> APIs.</p>
    <h3>Step 2. Enroll in EPS</h3><p>You must have an active <a href="https://postalpro.usps.com/EPS" target="_blank">Enterprise Payment Account</a> (or Permit) to print labels. Note the Payment Account Number.</p>
    <h3>Step 3. Find your CRID and MID</h3><p>From the <a href="https://gateway.usps.com/" target="_blank">Business Customer Gateway</a>, capture your Customer Registration ID (CRID) and at least one Mailer ID (MID). Both are required by the Payments API.</p>
    <h3>Step 4. Configure</h3><p>Enter Client ID, Client Secret, EPS Account Number, CRID, and MID in the fields below, save the changes, and toggle Test Mode off when ready. Bizuno will fetch and cache the OAuth bearer token automatically.</p>
    <h3>Step 5. Funding</h3><p>USPS labels are paid from the EPS account at print time — there is no "buy postage" step. Top up your EPS balance from <a href="https://www.usps.com/business/" target="_blank">usps.com</a>.</p>',
        // Configuration field labels
        'client_id_lbl'      => 'Enter the Client ID from your USPS Developer Portal app',
        'client_secret_lbl'  => 'Enter the Client Secret from your USPS Developer Portal app',
        'payment_account_lbl'=> 'Enter your Enterprise Payment Account (EPS) number',
        'crid_lbl'           => 'Customer Registration ID (CRID) — required for the Payments API',
        'mid_lbl'            => 'Mailer ID (MID) — required for label tracking number ranges',
        'test_mode_lbl'      => 'Test Mode (use the apis-tem sandbox)',
        'price_type_lbl'     => 'Price Type — RETAIL is the default; COMMERCIAL/CONTRACT requires negotiated rates on your CRID',
        'handling_fee'       => 'Handling Fee',
        'package_types'      => 'Package Types',
        'lbl_msg_1'          => 'Line 1 - Reference printed on shipping label',
        'lbl_msg_2'          => 'Line 2 - Reference printed on shipping label',
        'lbl_msg_3'          => 'Line 3 - Reference printed on shipping label',
        'label_thermal'      => 'Label Material',
        'funds_min'          => 'EPS balance minimum (in USD) to trigger reminder.',
        // General
        'msg_label_retrieve' => 'Successfully retrieved the USPS label, tracking # %s.',
        'err_postal_weight_zero' => 'The package weight must be greater than zero!',
        'err_pkg_too_heavy'  => 'The package weight exceeds the 70-pound USPS maximum!',
        'err_no_communication'=> 'No response from the USPS server (%s).',
        'msg_low_balance'    => 'Your USPS Enterprise Payment Account balance is %s — below the configured minimum of %s. Top up at usps.com when convenient.',
        // Bizuno carrier service codes (kept identical to Endicia so the rest
        // of the shipping manager / EDI 856 confirms / rate-shopping keep
        // working unchanged when an operator switches carriers).
        '1DP' => 'USPS Priority Mail Express',
        '2DP' => 'USPS Priority Mail',
        'GND' => 'USPS Ground Advantage',
        '3DP' => 'USPS Parcel Select',
        '4DP' => 'USPS Media / Library / Bound Printed Matter',
        // Package types displayed in the rate dropdown. Match Endicia's MPS_*
        // labels so the carrier-switcher UI reads consistently.
        'MPS_01' => 'Package',
        'MPS_02' => 'Large Envelope',
        'MPS_03' => 'Flat Rate Envelope',
        'MPS_04' => 'Flat Rate Padded Envelope',
        'MPS_05' => 'Flat Rate Legal Envelope',
        'MPS_06' => 'Small Flat Rate Box',
        'MPS_07' => 'Medium Flat Rate Box',
        'MPS_08' => 'Large Flat Rate Box'];

    function __construct()
    {
        $this->defaults = [
            'client_id'      => '', 'client_secret'=>'', 'payment_account'=>'', 'crid'=>'', 'mid'=>'',
            'test_mode'      => '1', // start in sandbox until operator flips it
            'price_type'     => 'RETAIL',
            'gl_acct'        => getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'),
            'handling_fee'   => 0,
            'order'          => 50,
            'default'        => false,
            'funds_min'      => 25,
            'lbl_msg_1'      => '', 'lbl_msg_2'=>'', 'lbl_msg_3'=>'',
            'label_thermal'  => 'PDF',
            'service_types'  => '1DP:2DP:GND:3DP',
            'package_types'  => 'package:usps_flat_rate_envelope'];
        $this->options  = $this->getOptions();
        $this->settings = array_replace_recursive($this->defaults, getMetaMethod($this->methodDir, $this->code)['settings'] ?? []);
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang, $this->options['rateCodes']);
    }

    /**
     * Active API host based on the test_mode toggle. Centralized so every
     * caller reads from the same place — flipping test_mode in settings is
     * meant to retarget every endpoint atomically.
     */
    protected function host()
    {
        return !empty($this->settings['test_mode']) ? $this->hostTest : $this->hostProd;
    }

    /**
     * OAuth client_credentials flow. Returns a bearer token, cached in the
     * module cache for slightly less than the token lifetime so we don't hit
     * /oauth2/v3/token on every request.
     *
     * USPS issues short tokens (~8 hours per docs) but we keep a 30-second
     * safety margin so a request that races the expiry doesn't 401. On 401 the
     * caller can also force a re-fetch by setting force=true, which we do
     * exactly once before bubbling the error up.
     *
     * Cache key includes the client_id so that swapping credentials in the
     * settings panel doesn't accidentally serve a stale token from the
     * previous app.
     */
    protected function getAccessToken($force=false)
    {
        global $io;
        $cacheKey = 'token_' . md5($this->settings['client_id'] . '|' . ($this->settings['test_mode'] ? 't' : 'p'));
        if (!$force) {
            $cached = getModuleCache($this->moduleID, $this->methodDir, $this->code, [])['settings'][$cacheKey] ?? [];
            if (!empty($cached['token']) && !empty($cached['expires']) && $cached['expires'] > time()) {
                msgDebug("\nUSPS: using cached bearer token, expires in ".($cached['expires']-time())." seconds");
                return $cached['token'];
            }
        }
        if (empty($this->settings['client_id']) || empty($this->settings['client_secret'])) {
            msgAdd("USPS: client_id / client_secret are not configured. See Settings → Shipping → USPS.");
            return false;
        }
        $url = $this->host() . '/oauth2/v3/token';
        // USPS accepts both client_credentials with form-encoded body and JSON
        // body; the form-encoded form is standard OAuth2 and what the spec
        // example uses, so prefer that for compat with libraries that scrub
        // unknown JSON keys.
        $body = http_build_query([
            'grant_type'   => 'client_credentials',
            'client_id'    => $this->settings['client_id'],
            'client_secret'=> $this->settings['client_secret'],
            'scope'        => 'addresses prices labels payments tracking']);
        $opts = ['headers'=>['Content-Type'=>'application/x-www-form-urlencoded', 'Accept'=>'application/json']];
        msgDebug("\nUSPS: requesting OAuth token from $url");
        $raw = $io->cURL($url, $body, 'post', $opts);
        $resp = json_decode($raw, true);
        if (empty($resp['access_token'])) {
            msgAdd("USPS OAuth failed: ".(isset($resp['error_description']) ? $resp['error_description'] : (isset($resp['error']) ? $resp['error'] : 'unknown error')));
            msgDebug("\nUSPS OAuth raw response: ".msgPrint($raw));
            return false;
        }
        $expiresIn = isset($resp['expires_in']) ? (int)$resp['expires_in'] : 3600;
        $payload   = ['token'=>$resp['access_token'], 'expires'=>time() + max(60, $expiresIn - 30)];
        // Persist alongside settings so the cache survives request boundaries.
        // Stored in the same meta blob the settings panel writes to, so a
        // settingSave() that rewrites that blob without preserving cached
        // tokens just costs one extra OAuth round-trip — acceptable.
        $meta = dbMetaGet(0, "methods_{$this->methodDir}");
        $idx  = metaIdxClean($meta);
        if (!isset($meta[$this->code]['settings'])) { $meta[$this->code]['settings'] = []; }
        $meta[$this->code]['settings'][$cacheKey] = $payload;
        dbMetaSet($idx, "methods_{$this->methodDir}", $meta);
        return $payload['token'];
    }

    /**
     * Authenticated REST helper used by every USPS endpoint. Returns the
     * decoded JSON body on success (always an array), or false on auth/transport
     * failure with a message added to the stack.
     *
     * On a 401 we transparently force-refresh the token and retry once — covers
     * the case where USPS revokes a token mid-life (rare, but documented).
     *
     * @param string $method  HTTP method (get/post/put/delete)
     * @param string $path    Path under host(), e.g. "/prices/v3/base-rates/search"
     * @param mixed  $body    Array — sent as JSON; string — sent verbatim; null — no body
     * @param array  $opts    Extra options: 'headers' (assoc), 'query' (assoc, appended to URL)
     */
    protected function queryREST($method, $path, $body=null, $opts=[])
    {
        global $io;
        $token = $this->getAccessToken();
        if (empty($token)) { return false; }
        return $this->doRequest($method, $path, $body, $opts, $token, false);
    }

    private function doRequest($method, $path, $body, $opts, $token, $retried)
    {
        global $io;
        $url = $this->host() . $path;
        if (!empty($opts['query']) && is_array($opts['query'])) {
            // Drop empties — USPS's address validator 400s on empty params.
            $q   = array_filter($opts['query'], function($v){ return $v !== '' && $v !== null; });
            $url .= (strpos($url, '?')===false ? '?' : '&') . http_build_query($q);
        }
        $headers = [
            'Authorization'=> 'Bearer ' . $token,
            'Accept'       => 'application/json',
            'User-Agent'   => 'Bizuno-USPS/' . (defined('MODULE_BIZUNO_VERSION') ? MODULE_BIZUNO_VERSION : 'dev')];
        if (is_array($body)) {
            $headers['Content-Type'] = 'application/json';
            $body = json_encode($body);
        } elseif (is_string($body) && $body !== '' && empty($opts['headers']['Content-Type'])) {
            // String bodies (rare — only used if a future endpoint takes raw)
            // default to JSON unless the caller already specified.
            $headers['Content-Type'] = 'application/json';
        }
        if (!empty($opts['headers'])) { $headers = array_replace($headers, $opts['headers']); }
        msgDebug("\nUSPS: $method $url");
        $callOpts = ['headers'=>$headers];
        // Need the HTTP status to detect 401-and-retry. CURLOPT_HEADER is too
        // intrusive (mixes headers into the body); use a request_done capture
        // via curlinfo by going one level deeper.
        $callOpts['CURLOPT_FAILONERROR']  = false;
        $raw = $io->cURL($url, $body, strtolower($method), $callOpts);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            $hint = is_string($raw) && $raw !== '' ? substr($raw, 0, 200) : 'empty response';
            msgAdd("USPS $method $path failed: $hint");
            msgDebug("\nUSPS raw response: ".msgPrint($raw));
            return false;
        }
        // USPS error envelope: {"error":{"code":"...","message":"...","errors":[{"detail":...}]}}
        if (!empty($decoded['error'])) {
            $err = $decoded['error'];
            // 401 once → force-refresh the token and retry one time.
            $is401 = (isset($err['code']) && (string)$err['code'] === '401') ||
                     (isset($err['status']) && (string)$err['status'] === '401') ||
                     (stripos((string)($err['message'] ?? ''), 'unauthorized') !== false);
            if ($is401 && !$retried) {
                msgDebug("\nUSPS: 401 received — refreshing token and retrying once");
                $newToken = $this->getAccessToken(true);
                if (empty($newToken)) { return false; }
                return $this->doRequest($method, $path, is_string($body) ? json_decode($body, true) : $body, $opts, $newToken, true);
            }
            $msg = isset($err['message']) ? $err['message'] : 'USPS API error';
            if (!empty($err['errors']) && is_array($err['errors'])) {
                foreach ($err['errors'] as $e) {
                    if (!empty($e['detail'])) { $msg .= " — " . $e['detail']; }
                }
            }
            msgAdd("USPS $path: $msg");
            return false;
        }
        return $decoded;
    }

    /**
     * Build a payment authorization token for label calls. USPS labels won't
     * print without one — it carries the EPS payment account + CRID + MID
     * combination that USPS will charge against. Token is short-lived (USPS
     * doc says ~8 hours) but cheap to mint, so we mint per request rather
     * than caching aggressively.
     *
     * @return string|false bearer-style JWT to put in X-Payment-Authorization-Token, or false on fail
     */
    protected function getPaymentAuthToken()
    {
        if (empty($this->settings['payment_account']) || empty($this->settings['crid']) || empty($this->settings['mid'])) {
            msgAdd("USPS: payment_account, CRID, and MID must all be set before printing labels.");
            return false;
        }
        $payload = ['roles' => [
            ['roleName'=>'PAYER',       'CRID'=>$this->settings['crid'], 'MID'=>$this->settings['mid'], 'manifestMID'=>$this->settings['mid'], 'accountType'=>'EPS', 'accountNumber'=>$this->settings['payment_account']],
            ['roleName'=>'LABEL_OWNER', 'CRID'=>$this->settings['crid'], 'MID'=>$this->settings['mid'], 'manifestMID'=>$this->settings['mid']]]];
        $resp = $this->queryREST('post', '/payments/v3/payment-authorization', $payload);
        if (empty($resp['paymentAuthorizationToken'])) {
            msgAdd("USPS: failed to obtain payment authorization token. Verify EPS account, CRID, and MID.");
            return false;
        }
        return $resp['paymentAuthorizationToken'];
    }

    /**
     * Optional balance check after a label is printed. USPS does not return
     * the balance in the label response (unlike Endicia), so this is a separate
     * call. Surface a low-balance warning the same way the Endicia helper
     * does so the operator gets a single consistent UX.
     */
    protected function chkNeedToBuy()
    {
        if (empty($this->settings['payment_account'])) { return; }
        $resp = $this->queryREST('get', '/payments/v3/payment-account/' . urlencode($this->settings['payment_account']));
        if (empty($resp) || !isset($resp['balance'])) { return; } // best-effort; don't block on errors
        $balance = (float)$resp['balance'];
        if ($balance < (float)$this->settings['funds_min']) {
            msgAdd(sprintf($this->lang['msg_low_balance'], viewFormat($balance, 'currency'), viewFormat($this->settings['funds_min'], 'currency')), 'caution');
        }
    }

    /**
     * Maps a Bizuno address row to USPS's address shape. USPS splits the
     * primary recipient into firstName/lastName/firm rather than the single
     * "primary_name + contact" pair Bizuno uses, so we have to make a choice
     * per address: if a company name is present, treat it as the firm and
     * put the contact (a person's name) in firstName/lastName; otherwise
     * split the contact line.
     *
     * @param array $addr Bizuno address (primary_name, contact, address1, ...)
     * @return array USPS address shape (firm/firstName/lastName, streetAddress, city, state, ZIPCode, ...)
     */
    protected function mapAddress($addr=[])
    {
        $out = [];
        $primary = trim($addr['primary_name'] ?? '');
        $contact = trim($addr['contact'] ?? '');
        // Heuristic: primary_name is the firm IF a separate contact is
        // provided. Otherwise primary_name is itself a person's name and we
        // split it. This matches the convention enforced by the Sales/Order
        // mapPost flow elsewhere in the system.
        if ($primary !== '' && $contact !== '') {
            $out['firm'] = $primary;
            $name = $contact;
        } else {
            $name = $primary !== '' ? $primary : $contact;
        }
        if ($name !== '') {
            $parts = preg_split('/\s+/', $name, 2);
            $out['firstName'] = $parts[0];
            if (isset($parts[1])) { $out['lastName'] = $parts[1]; }
        }
        if (!empty($addr['address1']))    { $out['streetAddress']    = $addr['address1']; }
        if (!empty($addr['address2']))    { $out['secondaryAddress'] = $addr['address2']; }
        if (!empty($addr['city']))        { $out['city']             = $addr['city']; }
        if (!empty($addr['state']))       { $out['state']            = $addr['state']; }
        if (!empty($addr['postal_code'])) {
            // USPS API requires `ZIPCode` (5) and `ZIPPlus4` (4) split.
            $zip = preg_replace('/[^0-9]/', '', $addr['postal_code']);
            if (strlen($zip) >= 9) {
                $out['ZIPCode']  = substr($zip, 0, 5);
                $out['ZIPPlus4'] = substr($zip, 5, 4);
            } else {
                $out['ZIPCode'] = substr($zip, 0, 5);
            }
        }
        if (!empty($addr['telephone1'])) { // USPS phone pattern is digits only, no punctuation
            $out['phone'] = preg_replace('/[^0-9+]/', '', $addr['telephone1']);
        }
        if (!empty($addr['email'])) { $out['email'] = $addr['email']; }
        return $out;
    }

    /**
     * Service code maps. Two layers:
     *   - rateCodes:  Bizuno carrier code (1DP/2DP/GND/3DP) ↔ USPS mailClass
     *   - PackageMap: Bizuno package id (matching the cart vocabulary used by
     *                 Endicia / the rate-shopping form) ↔ USPS rateIndicator
     *
     * Kept identical to Endicia's Bizuno-side codes so the rest of the system
     * (shipping manager, EDI 856 confirms, rate-shopping) doesn't care which
     * USPS carrier a customer is using.
     */
    private function getOptions()
    {
        return [
            'rateCodes' => [
                // Bizuno code => USPS mailClass
                'usps_priority_mail_express' => '1DP',
                'usps_priority_mail'         => '2DP',
                'usps_ground_advantage'      => 'GND',
                'usps_parcel_select'         => '3DP',
                'usps_media_mail'            => '4DP'],
            // Reverse map (Bizuno code => USPS mailClass) used when generating
            // a label — once the operator has chosen a service, we need the
            // USPS string back. Kept inline here so it stays in lockstep with
            // rateCodes above.
            'mailClass' => [
                '1DP' => 'PRIORITY_MAIL_EXPRESS',
                '2DP' => 'PRIORITY_MAIL',
                'GND' => 'USPS_GROUND_ADVANTAGE',
                '3DP' => 'PARCEL_SELECT',
                '4DP' => 'MEDIA_MAIL'],
            'PackageMap' => [
                // Bizuno cart-style id           => display label
                'package'                         => $this->lang['MPS_01'],
                'large_envelope'                  => $this->lang['MPS_02'],
                'usps_flat_rate_envelope'         => $this->lang['MPS_03'],
                'usps_padded_flat_rate_envelope'  => $this->lang['MPS_04'],
                'usps_legal_flat_rate_envelope'   => $this->lang['MPS_05'],
                'usps_small_flat_rate_box'        => $this->lang['MPS_06'],
                'usps_medium_flat_rate_box'       => $this->lang['MPS_07'],
                'usps_large_flat_rate_box'        => $this->lang['MPS_08']],
            // USPS rateIndicator codes per package type. SP (Single Piece) is
            // the default for normal packages. Flat-rate boxes/envelopes have
            // their own codes — these drive both the rate query (so USPS
            // returns flat-rate pricing) and the label payload (so USPS knows
            // which barcode/SKU to print).
            'rateIndicator' => [
                'package'                        => 'SP',
                'large_envelope'                 => 'SP',
                'usps_flat_rate_envelope'        => 'FE',
                'usps_padded_flat_rate_envelope' => 'FP',
                'usps_legal_flat_rate_envelope'  => 'FA',
                'usps_small_flat_rate_box'       => 'FS',
                'usps_medium_flat_rate_box'      => 'FB',
                'usps_large_flat_rate_box'       => 'PL'],
            // Processing category — USPS distinguishes machinable (the common
            // case) from non-standard for pricing. Default everyone to
            // MACHINABLE; envelopes/letters override at call sites if needed.
            'processingCategory' => 'MACHINABLE',
            'paperTypes' => [
                ['id'=>'PDF',       'text'=>'PDF (plain paper)'],
                ['id'=>'ZPL203DPI', 'text'=>'Zebra ZPL 203 dpi'],
                ['id'=>'ZPL300DPI', 'text'=>'Zebra ZPL 300 dpi'],
                ['id'=>'PNG',       'text'=>'PNG image']]];
    }
}
