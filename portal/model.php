<?php
/*
 * PhreeSoft ISP Clients - Portal Model
 *
 * @author     Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    PhreeSoft Proprietary
 * @version    7.x Last Update: 2025-11-06
 * @filesource /lib/model.php
 */

namespace bizuno;

/**
 * Bizuno operates in local time. Returns WordPress safe date in PHP date() format if no timestamp is present, else PHP date() function
 * @param string $format - [default: 'Y-m-d'] From the PHP function date()
 * @param integer $timestamp - Unix timestamp, defaults to now
 * @return string
 */
function biz_date($format='Y-m-d', $timestamp=null) {
    // @TODO - This needs to be adjusted to the users locale
    return !is_null($timestamp) ? date($format, $timestamp) : date($format);
}

function loadBusinessCache()
{
    global $bizunoMod;
    $bizunoMod= [];
    $rows     = dbGetMulti(BIZUNO_DB_PREFIX.'configuration');
    foreach ($rows as $row) { $bizunoMod[$row['config_key']] = json_decode($row['config_value'], true); }
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

final class portal
{
    public $restHeaders = [];
    public $useOauth = false;
    private $phreeSoftREST =  'https://www.phreesoft.com/wp-json/phreesoft-custom/v1';

    function __construct() { }

    /**
     * Validates the requestors credentials and sets the bizID
     */
    public function portalUserAuth()
    {
        global $portal;
        $userID = clean('HTTP_BIZUSER', 'email','server');
        $userPW = clean('HTTP_BIZPASS', 'text', 'server');
        if (!empty($userID) && !empty($userPW)) {
            $portal->restHeaders = ['email'=>$userID, 'pass'=>$userPW, 'bizID'=>BIZUNO_BIZID];
            $resp = $portal->restRequest('get', $this->phreeSoftREST, 'biz_hosted/get_rights');
            if (empty($resp['userID'])) { return msgAdd('Invalid credentials!'); }
//          $profile = array_replace(getUserCache('profile'), getMetaContact(getUserCache('profile', 'userID'), 'user_profile'));
//          setUserCache('profile', '', $profile);
            $role = dbMetaGet($resp['userRole'], 'bizuno_role');
            setUserCache('role', '', $role);
        }
        return true;
    }
    
    public function restRequest($type, $server, $endpoint='', $data=[], $opts=[]) {
        if (!empty($this->useOauth)) {
            msgDebug("\nSending REST request via oAuth");
            $token = $this->restOauthToken();
            $optsEP= array_replace_recursive(['headers'=>['authorization'=>"Bearer $token", 'x-locale'=>'en_US', 'content-type'=>'application/json']], $opts);
        } else {
            msgDebug("\nSending REST request via User/Password");
            $optsEP = array_replace_recursive(['headers'=>$this->restHeaders,'cookies'=>[]], $opts);
        }
        $url = empty($endpoint) ? $server : "$server/$endpoint";
//      msgDebug("\nHeaders: ".print_r($optsEP, true));
        msgDebug("\nSending request of type $type to url $url and data of size: ".(is_array($data)?'Array('.sizeof($data).')':strlen($data)));
        $response= json_decode($this->cURL($url, $data, strtolower($type), $optsEP), true);
        msgDebug("\nLast response is: ".print_r($response, true));
        if (empty($response) && !is_array($response)) { msgAdd(sprintf(lang('err_no_communication'), $server), 'trap'); }
        if (isset($response['message']) && is_string($response['message'])) { // unexpected message returned
        // Commented out as errors need to be handled individually.
//          msgAdd("Woo restRequest received back from server: {$response['message']}");
//          unset($response['message']);
        }
        return $response;
    }

    /**
     * Fetch oAuth2 token from a RESTful API server
     * @return token if successful, null if error
     */
    public function restOauthToken($server='', $id='', $secret='')
    {
        msgDebug("\nEntering restTokenValidate with path = $server");
        if (empty($server)) { return msgAdd("Error! no server name passed!"); }
        $token = getModuleCache('bizuno', 'rest');
        if (empty($token[$server]['token']) || $token[$server]['expires_in'] < time()-10) { // get a new token for today
            // get an authorization code
            $code = json_decode($this->cURL("{$server}/oauth/authorize", "response_type=code&client_id=$id", 'get'), true);
            if (!is_array($code)) { return msgAdd('A string was returned for the OAuth2 code! Not good.'); }
            // get an access token
            // WHAT TO DO WITH $code['code?']
            $optsA = ['headers'=>['Content-Type'=>'application/x-www-form-urlencoded']];
            $dataA = "grant_type=client_credentials&client_id=$id&client_secret=$secret";
            $tokenA= json_decode($this->cURL("{$server}/oauth/token", $dataA, 'post', $optsA), true);
            if (!is_array($tokenA)) { return msgAdd("A string was returned! Not good."); }
            if (!empty($tokenA['error'])) { return msgAdd("REST Token Request Error: ".print_r($tokenA['errors'], true)); }
            msgDebug("\nread token = {$tokenA['access_token']} and expires_in = {$tokenA['expires_in']}");
            if (empty($tokenA['access_token'])) { return msgAdd("Error retrieving token from $server, all APIs will be unavailable!"); }
            $token[$server]['token']   = $tokenA['access_token'];
            $token[$server]['expires_in']= time()+$tokenA['expires_in'];
            setModuleCache('bizuno', 'rest', '', $token);
        }
        return $token[$server]['token'];
    }

    /**
     * This method retrieves data from a remote server using cURL
     * @param string $url - URL to request data
     * @param string $data - data string, will be attached for get and through setopt as post or an array
     * @param string $type - [default 'get'] Choices are 'get' or 'post'
     * @return result if successful, false (plus messageStack error) if fails
     */
    public function cURL($url, $data=[], $type='get', $opts=[]) {
        $useragent = 'Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0';
        $size = is_array($data) ? 'array('.sizeof($data).')' : strlen($data);
        msgDebug("\nAt class portal, sending request of length $size to url: $url via $type with sizeof opts = ".sizeof($opts)); //  ." with opts = ".print_r($opts, true)
        $rData = is_array($data) ? http_build_query($data) : $data;
        if ($type == 'get') { $url = $url.'?'.$rData; }
        $headers = [];
        if (!empty($opts['headers'])) { foreach ($opts['headers'] as $key => $value) { $headers[] = "$key: $value"; } }
        if (!empty($opts['cookies'])) { foreach ($opts['cookies'] as $key => $value) { $headers[] = "$key: $value"; } }
        unset($opts['headers'], $opts['cookies']);
        $options = [];
        $ch = curl_init();
        if (!empty($options)) { foreach ($options as $opt => $value) {
            switch ($opt) {
                case 'useragent': curl_setopt($ch, CURLOPT_USERAGENT, $useragent); break;
                default:          curl_setopt($ch, constant($opt), $value); break;
            }
        } }
        curl_setopt($ch, CURLOPT_URL,           $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER,    $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_TIMEOUT,       30); // in seconds
        curl_setopt($ch, CURLOPT_HEADER,        false);
        curl_setopt($ch, CURLOPT_VERBOSE,       false);
        curl_setopt($ch, CURLOPT_ENCODING,      ""); // Let cURL handle the response as some hosts mess up the return encoding, e.g. FedEx
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,false);
        if (strtolower($type) == 'post') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rData);
        } elseif (strtolower($type) == 'put') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $rData);
        }
// for debugging cURL issues, uncomment below
//$fp = fopen(BIZUNO_DATA."cURL_trace.txt", 'w');
//curl_setopt($ch, CURLOPT_VERBOSE, true);
//curl_setopt($ch, CURLOPT_STDERR, $fp);
        $response = curl_exec($ch);
//msgDebug("\nRaw cURL data returned = ".print_r($response, true)); // This can be helpful if headers are sent first
        if (curl_errno($ch)) {
            msgDebug('cURL Error # '.curl_errno($ch).'. '.curl_error($ch));
            msgAdd('cURL Error # '.curl_errno($ch).'. '.curl_error($ch));
            curl_close ($ch);
            return;
        } elseif (empty($response)) { // had an issue connecting with TLSv1.2, returned no error but no response (ALPN, server did not agree to a protocol)
            msgAdd("Oops! I Received an empty response back from the cURL request. There was most likely a problem with the connection that was not reported.", 'caution');
        }
        curl_close ($ch);
        return $response;
    }

    /**
     * Restricts editing ability for PhreeSoft hosted Bizuno users. Managed at PhreeSoft.
     * @param string $layout
     * @param type $type
     * @return bool
     */
    public function contactTypeUser(&$layout, $type='u')
    {
        if ('u'==$type) { 
            $layout   = ['type'=>'divHTML','title'=>lang('edit'),
                'toolbars'=> [
                    'tbUser'=>['icons'=>['close'=>['order'=>20,'events'=>['onClick'=>"jqBiz('#accContacts').accordion('select', 0); jqBiz('#divContactsDetail').html('&nbsp;');"]]]]],
                'divs'    => [
                    'toolbar'=> ['order'=>10,'type'=>'toolbar','key' =>'tbUser'],
                    'body'   => ['order'=>50,'type'=>'html', 'html'=>lang('Manage users @PhreeSoft')]]];            
        }
    }
}

/**
 * Dummy placeholder in case the host has it's own mailing routine.
 */
class portalMail
{
    function __construct() { }
}