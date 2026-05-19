<?php
/*
 * Shipping extension for United Parcel Service shipments - Common API methods
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
 * @version    7.x Last Update: 2026-04-03
 * @filesource /controllers/shipping/carriers/ups/common.php
 */

namespace bizuno;

class upsCommon
{
    public  $moduleID   = 'shipping';
    public  $methodDir  = 'carriers';
    public  $code       = 'ups';
    public  $restVersion= 'v1';
    private $url_test   = 'https://wwwcie.ups.com/'; // the rest of the url: api/rating/{version}/{requestoption}';
    private $url_prod   = 'https://onlinetools.ups.com/'; // the rest of the url: api/rating/{version}/{requestoption}';
    private $retries    = 5;
    public  $default_insurance_value = 99.00; // FedEx maximum insurance value in USD if not specified
    public $defaults;
    public $options;
    public $settings;
    public $today;
    public $currency;
    public $test;
    public $creds;
    public $urlREST;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    public  $lang       = ['title' => 'United Parcel Service',
        'acronym'      => 'UPS',
        'description'  => 'UPS shipping with rates pulled directly from UPS servers. (Requires an account with UPS, <a href="#" onClick="jsonAction(\'shipping/admin/signup&carrier=ups\')">instructions</a>)',
        'instructions' => '<h3>Step 1. Develop and Test Web Services Enabled Application</h3> This step has been completed by PhreeSoft. The remaining steps must be followed to enable label generation.
<h3>Step 2. Register for Access Key (requires UPS web account)</h3>
Start the certification process by applying for a UPS Access Key at the UPS Developer Resource Center. <a href="https://www.ups.com/upsdeveloperkit?loc=en_US" target="_blank">https://www.ups.com/upsdeveloperkit</a>
<h3>Step 3. Obtain Production Credentials</h3>
Obtain your production credentials (production access key) online during the registration process. Same site as above, click ManageAccess Keys in the Access and Administration section of the page. Then enter your development access key in the Request Production Access section and click Request Production Access.
<br />Important Note: Due to the sensitivity of this information, the production authentication key is not provided in the confirmation email (Step 4). Please retain this information for your records.
<h3>Step 4. UPS Certification</h3>UPS will guide you through the remaining process after your request has been submitted. This may include submitting sample labels and other forms of validation.
<h3>Step 5. Replace URL and Credentials</h3>In the Bizuno UPS Settings, change the mode to Production and update any other information resulting from the certification. Save your changes.',
        'ship_pkg_title' => 'Ship Package',
        'ship_pkg_desc' => 'To generate a label to ship a package via UPS, Click GO and fill out the form.',
        // General
        'pkg_envelope' => 'Envelope/Letter',
        'pkg_your_box' => 'Customer Supplied',
        'pkg_tube' => 'Carrier Tube',
        'pkg_pak' => 'Carrier Pak',
        'pkg_box' => 'Carrier  Box',
        'pkg_10kg_box' => '25kg Box',
        'pkg_25kg_box' => '10kg Box',
        'regular_pickup' => 'Daily Pickup',
        'request_courier' => 'Carrier Customer Counter',
        'drop_box' => 'Request/One Time Pickup',
        'service_center' => 'On Call Air',
        'station' => 'Suggested Retail Rates',
        'print_return_label' => 'Print Return Label Here',
        // Configuration
        'acct_number'  => 'Enter the UPS account number to use for rate estimates',
        'shipping_settings_default_rate' => 'Whether to include this, by default, in rate estimates.',
        'shipping_settings_default_service' => 'Select the services to be offered by default.',
        'auth_user'    => 'Enter the UPS username for your account.',
        'auth_pass'    => 'Enter the UPS password for your account.',
        'license_key'  => 'Enter the access key supplied to you from UPS.',
        'test_mode'    => 'Test/Production mode used for testing shipping labels',
        'printer_type' => 'Type of printer to use for printing labels. PDF for plain paper, Thermal for UPS Thermal Label Printer (See Help file before selecting Thermal printer)',
        'printer_name' => 'Sets then name of the printer to use for printing labels as defined in the printer preferences for the local workstation.',
        'max_weight'   => 'Maximum allowed box weight, UPS limits this to 150 pounds.',
        'def_ltl_class'=> 'Default weight class to use for LTL shipments.',
        'def_ltl_desc' => 'Default commodity description to use for LTL shipments.',
        'label_thermal'=> 'Label size for thermal printing.',
        'label_pdf'    => 'Label size for plain paper printing.',
        'recon_fee'    => 'Constant amount to add to shipping rate for reconciliation to avoid flagging charges larger than estimate.',
        'recon_percent'=> 'Percentage error band for reconciliation to avoid flagging shipping mismatches.',
        // Settings
        'GND' => 'Ground', // service options
        '1DM' => 'Next Day Air Early',
        '1DA' => 'Next Day Air',
        '1DP' => 'Next Day Air Saver',
        '2DA' => '2nd Day Air A.M.',
        '2DP' => '2nd Day Air',
        '3DP' => '3 Day Select',
        'I1D' => 'Worldwide Express Plus',
        'I2D' => 'Worldwide Express',
        'I3D' => 'Worldwide Expedited',
        'I4D' => 'Worldwide Saver',
        'IGD' => 'Standard',
        '1DF' => 'Next Day Air Freight',
        '2DF' => '2nd Day Air Freight',
        '3DF' => '3 Day Freight',
        'GDF' => 'Freight LTL',
        'IP1' => 'Europe First Priority',
        'IFE' => 'Air Freight Consolidated',
        'IFP' => 'Worldwide Express Freight',
        'label_01' => 'Label 4x6',
        'label_02' => 'Label 4x8',
        // General defines
        'RATE_ERROR' => 'UPS rate response error: ',
        'RATE_CITY_MATCH' => 'City does not match zip code.',
        'RATE_TRANSIT' => ' Day(s) Transit, arrives ',
        'TNT_ERROR' => ' UPS Time in Transit Error # ',
        'DEL_ERROR' => 'UPS Delete Label Error: ',
        'DEL_SUCCESS' => 'Successfully deleted the UPS shipping label. Tracking # ',
        'TRACK_ERROR' => 'UPS Package Tracking Error: ',
        'TRACK_SUCCESS' => 'Successfully Tracked Package Reference # ',
        'track_status' => 'The package reference: %s is not delivered, the status is: (Code %s) %s.',
        'TRACK_FAIL' => 'The following package reference number was deliverd after the expected date/time: ',
        'CLOSE_SUCCESS' => 'Successfully closed the UPS shipments for today.',
        'err_address_val_country' => 'The UPS address validation tool only works in the USA',
        // Label manager
        'error_postal_code' => 'Postal Code is required to use the UPS module'];

    function __construct()
    {
        $this->defaults = [
            'order'        => 10, 'test_mode'=>'prod', 'acct_number'=>'', 'rest_api_key'=>'', 'rest_secret'=>'',
            'service_types'=> 'GND:1DM:1DA:1DP:2DA:2DP:3DP:I1D:I2D:I3D:IGD:1DF:2DF:3DF:GDF', 'max_weight'=>150,
            'printer_type' => 'PDF','printer_name' =>'zebra','label_pdf' =>'PAPER_8.5X11_TOP_HALF_LABEL','label_thermal'=>'STOCK_4X6.75_LEADING_DOC_TAB',
            'ltl_class'    => '125', 'ltl_desc'    =>'', 'recon_fee'=>3, 'recon_percent'=>0.1,
            'gl_acct_c'    => getModuleCache('shipping','settings','general','gl_shipping_c'),
            'gl_acct_v'    => getModuleCache('shipping','settings','general','gl_shipping_v'),
            'default'      => '1', 'token'=>'', 'token_date'=>''];
        $this->options = $this->getOptions();
        $this->settings= array_replace_recursive($this->defaults, getMetaMethod($this->methodDir, $this->code)['settings'] ?? []);
        $this->today   = biz_date('Y-m-d');
        $this->currency= 'USD';
        $this->test    = $this->settings['test_mode']=='test' ? true : false;
        $this->urlREST = $this->test ? $this->url_test : $this->url_prod;
        $this->creds   = $this->getCreds(0);
    }

    /**
     * FedEx now uses RESTful API
     * @param type $client
     * @param type $function
     * @param type $data
     * @return type
     */
    protected function queryREST($path, $data=[], $type='post')
    {
        global $io;
        if (empty($this->settings['token']) || $this->settings['token_date'] < time()) { // token expired, get a new token
            $this->settings['token'] = $this->getTokenREST();
            if (!$this->settings['token']) { return msgAdd("Error retrieving token from UPS REST, all services will be unavailable!"); }
        }
        $destURL = $this->urlREST.$path;
        $opts    = ['headers'=>['Content-Type'=>'application/x-www-form-urlencoded', 'Authorization'=>"Bearer {$this->settings['token']}"]];
        $response= json_decode($io->cURL($destURL, $data, $type, $opts));
        if (empty($response)) { return; }
        msgDebug("\nParsed token response = ".print_r($response, true));
        if (!empty($response->response->errors)) {
            foreach ($response->response->errors as $error) { msgAdd("UPS REST Error: $error->code: $error->message", 'trap'); }
            return;
        }
        return $response;
    }

    /**
     * Fetch oAuth token from fedEx servers
     * @global class $io
     * @return token if successful, null if error
     */
    private function getTokenREST()
    {
        global $io;
        $httpUrl  = $this->urlREST.'security/v1/oauth/token';
        $creds    = base64_encode("{$this->settings['rest_api_key']}:{$this->settings['rest_secret']}");
        $opts     = ['headers'=>['Content-Type'=>'application/x-www-form-urlencoded', 'Accept'=>'application/json, text/plain, */*', 'Authorization'=>"Basic $creds"]];
        $request  = ['grant_type'=>'client_credentials'];
        $response = json_decode($io->cURL($httpUrl, $request, 'post', $opts));
        if (!empty($response->error)) { msgAdd("UPS REST Error: $response->error: $response->error_description"); return; }
        msgDebug("\Decoded token response = ".print_r($response, true));
        $this->settings['token']     = $response->access_token;
        $this->settings['token_date']= time()+$response->expires_in;
        $methProps= getModuleCache($this->moduleID, $this->methodDir, $this->code);
        $methProps['settings']       = $this->settings;
        setModuleCache($this->moduleID, $this->methodDir, $this->code, $methProps);
        return $response->access_token;
    }

    protected function queryWSDL($client, $function='', $data=[])
    {
        $response = false;
        $upss = [
            'UsernameToken'     => ['Username'=>$this->settings['auth_user'], 'Password'=>$this->settings['auth_pass']],
            'ServiceAccessToken'=> ['AccessLicenseNumber'=>$this->settings['license_key']]];
        $header = new \SoapHeader('http://www.ups.com/XMLSchema/XOLTWS/UPSS/v1.0', 'UPSSecurity', $upss);
        $client->__setSoapHeaders($header);
        for ($i=0; $i<$this->retries; $i++) {
            try {
                $response = $client->__soapCall($function ,  [$data]);
            } catch (\SoapFault $exception) {
                $this->excNotes = " ({$exception->faultcode}) {$exception->faultstring}";
                msgDebug("\nEntire exception: ".print_r($exception, true));
                msgDebug("SOAP exception ".$exception->getMessage().", with details: ".$exception->__toString());
            }
            msgDebug("\n\nLast Request is: ".$client->__getLastRequest()."\n\n Last Response is: ".$client->__getLastResponse());
            if ($response) { break; }
        }
        if (!$response) { msgAdd(sprintf(lang('err_no_communication'), $this->code).' '.$this->excNotes, 'trap'); }
        return $response;
    }

    protected function calculateDelivery($transit_time, $res = true) {
        $today       = getdate();
        $today_date  = biz_date('Y-m-d');
        $year        = biz_date('Y');
        $day_of_week = $today['wday'] - 1;  // 0 - Monday thru 6 - Sunday
        $holidays = [
            biz_date('Y-m-d', strtotime("1st January $year")),                 // New Years Day
            biz_date('Y-m-d', strtotime("Last Monday of May $year")),          // Memorial Day
            biz_date('Y-m-d', strtotime("4th July $year")),                    // Independence Day
            biz_date('Y-m-d', strtotime("First Monday of September $year")),   // Labor Day
            biz_date('Y-m-d', strtotime("Fourth Thursday of November $year")), // Thanksgiving Day
            biz_date('Y-m-d', strtotime("25th December $year"))];               // Christmas Day
        if     (($day_of_week + $transit_time) > 9) { $transit_time = $transit_time + 4; } // passed through two weekends
        elseif (($day_of_week + $transit_time) > 4) { $transit_time = $transit_time + 2; } // passed through one weekend
        $curDel = biz_date('Y-m-d', strtotime("+$transit_time days"));
        // check for holidays
        foreach ($holidays as $holiday) {
            if ($today_date <= $holiday && $curDel >= $holiday) {
                $transit_time++;
                $curDel = biz_date('Y-m-d', strtotime("+$transit_time days"));
            }
        }
        $d = getdate(strtotime("+$transit_time days"));
        $delivery = biz_date('Y-m-d', mktime(0, 0, 0, $d['mon'], $d['mday'], $d['year']));
        return $delivery . ' ' . ($res ? $this->lang['del_time_RE'] : $this->lang['del_time_GD']);
    }

    protected function calculateDeliveryTime($method, $code, $res = true)
    {
        switch ($method) {
            default:
            case 'IGD':
            case '2DP':
            case '3DP': $guar_time = $res ? $this->lang['del_time_RE'] : $this->lang['del_time_2D']; break;
            case 'GND': // handled in calculateDelivery
            case 'GDR': // handled in calculateDelivery
            case 'ECF':
            case 'GDF': $guar_time = $this->lang['del_time_CM']; break;
            case 'I1D':
            case 'I2D':
            case '1DF':
            case '2DF':
            case '3DF': $guar_time = $this->lang['del_time_PR']; break;
            case '1DP':
                switch ($code) {
                    default:
                    case 'A1':
                    case 'A2':
                    case 'AA':
                    case 'A4': $guar_time = $this->lang['del_time_CM']; break;
                    case 'A3':
                    case 'A5':
                    case 'AM': $guar_time = $this->lang['del_time_PR']; break;
                }
                break;
            case '1DA':
                switch ($code) {
                    default:
                    case 'A1':
                    case 'A2':
                    case 'AA':
                    case 'A4': $guar_time = $this->lang['del_time_P1']; break;
                    case 'A3':
                    case 'A5':
                    case 'AM': $guar_time = $this->lang['del_time_PR']; break;
                    case 'RM':
                    case 'PM':
                    case 'A6': $guar_time = $this->lang['del_time_CM']; break;
                }
                break;
            case '1DE':
                switch ($code) {
                    default:
                    case 'A1': $guar_time = $this->lang['del_time_A1']; break;
                    case 'A2':
                    case 'A3': $guar_time = $this->lang['del_time_A2']; break;
                    case 'A4': $guar_time = $this->lang['del_time_A4']; break;
                    case 'A5':
                    case 'A6': $guar_time = $this->lang['del_time_A5']; break;
                }
                break;
        }
        return $guar_time;
    }

    protected function GetLTLTransit($request=[])
    {
        $request['settings']['weight']     = $request['total_weight'];
        $request['settings']['num_boxes']  = $request['total_packages'];
        $request['settings']['ship_date']  = biz_date('Y-m-d'); //biz_date('c', $request['ship_date']);
        $request['settings']['residential']= !empty($request['ship_resi']) ? 1 : 0;
        $request['settings']['ltl_class']  = $request['ltl_class'];
        $request['ship'] = [
            'primary_name'=> $request['primary_name_s'],
            'address1'    => $request['address1_s'],
            'address2'    => $request['address2_s'],
            'city'        => $request['city_s'],
            'state'       => $request['state_s'],
            'postal_code' => $request['postal_code_s'],
            'country'     => $request['country_s'],
        ];
// @TODO fix this. This method is called from ship.php and there is no method there either
return '';
        $result = $this->rateQuote($request);
        $commit = $result[$this->options['rateCodes'][$request['ship_method']]]['commit'];
        return $commit;
    }

    protected function get_error_message($code)
    {
        switch ($code) {
            case '6520': return "Your box is too heavy to transport.";
            case '6505': return "Invalid Weight entered. Enter a number greater than zero.";
            case '2430': return "Invalid Length, Width and Height. Enter a number greater than zero.";
            case '8522': return "Too many packages, please split the order or package more.";
            case '2243': return "Shipments for Home Delivery Service must be designated as Residential Delivery";
            case '2254': return "The Package is too small to ship with this method. smallest dimenstions are: ";
            case '': return "";
            case '': return "";
            default: return "Unknown code $code";
        }
    }

    protected function addCreds(&$pkg=[])
    {
        msgDebug("\nEntering addCreds.");
        $pkg['shipper']['creds'] = $this->getCreds($pkg['shipper']['bID']); // base creds for all RESTful transactions
        $billTo = clean('ship_bill_to', 'cmd', 'post');
        switch ($billTo) {
            case 'COLLECT':
            case 'RECIPIENT':
            case 'THIRD_PARTY': $pkg['destination']['creds']['acct_number'] = clean('ship_bill_act', 'alpha_num', 'post'); break;
            default:
            case 'SENDER':      $pkg['payor']['creds'] = $this->getCreds($pkg['payor']['bID']);
        }
        msgDebug("\nLeaving addCreds.");
    }

    protected function getCreds($bID=0)
    {
        msgDebug("\nEntering getCreds store id = $bID");
        $multi = getModuleCache('contacts', $this->moduleID, $this->code, 'multi_store');
        $creds = empty($bID) || empty($multi[$bID]) ? $this->settings : $multi[$bID];
        if (empty($creds['ltl_acct_num'])) { $creds['ltl_acct_num'] = $creds['acct_number']; } // for combo accounts
//      msgDebug("\nReturning from getCreds with: ".print_r($creds, true));
        return $creds;
    }

    protected function mapAddress($addr=[])
    {
        $output = [];
        if (strlen($addr['primary_name'])>35) {
            $addr['primary_name'] = substr($addr['primary_name'], 0, 35); // UPS limits company name to 35 characters
            msgAdd("The primary name was tuncated to 35 characters per UPS spec: [{$addr['primary_name']}]");
        }
        if (!empty($addr['primary_name'])){ $output['Name']                        = $addr['primary_name']; }
        if (!empty($addr['contact']))     { $output['AttentionName']               = $addr['contact']; }
        if (!empty($addr['telephone1']))  { $output['Phone']['Number']             = substr(str_replace(' ', '', clean($addr['telephone1'], 'numeric')), 0, 10); }
        if (!empty($addr['email']))       { $output['EmailAddress']                = $addr['email']; }
        if (!empty($addr['address1']))    { $output['Address']['AddressLine'][]    = $addr['address1']; }
        if (!empty($addr['address2']))    { $output['Address']['AddressLine'][]    = $addr['address2']; }
        if (!empty($addr['city']))        { $output['Address']['City']             = $addr['city']; }
        if (!empty($addr['state']))       { $output['Address']['StateProvinceCode']= $addr['state']; }
        if (!empty($addr['postal_code'])) { $output['Address']['PostalCode']       = $addr['postal_code']; }
        if (!empty($addr['country']))     { $output['Address']['CountryCode']      = $addr['country']; }
        if (!empty($addr['residential'])) { $output['Address']['Residential']      = $addr['residential']; }
        return $output;
    }

        /**
     * This method creates the select values that are particular to this carrier
     */
    private function getOptions()
    {
        return [
            'rateCodes' => ['03'=>'GND','14'=>'1DM','01'=>'1DA','13'=>'1DP','59'=>'2DA', '02'=>'2DP','12'=>'3DP',
                            '54'=>'I1D','07'=>'I2D','08'=>'I3D','65'=>'I4D','11'=>'IGD','308'=>'GDF','96'=>'IP1'],
            'PickupMap'  => [
                'REGULAR_PICKUP'         => $this->lang['regular_pickup'],
                'REQUEST_COURIER'        => $this->lang['request_courier'],
                'DROP_BOX'               => $this->lang['drop_box'],
                'BUSINESS_SERVICE_CENTER'=> $this->lang['service_center'],
                'STATION'                => $this->lang['station']],
            'returnServiceMap' => [
                'PRINTRETURNLABEL'=> $this->lang['print_return_label'],
                'UPSTAG'          => lang('carrier_print_return', $this->moduleID)],
            'PackageMap' => [
                '02' => $this->lang['pkg_your_box'],
                '21' => $this->lang['pkg_box'],
                '04' => $this->lang['pkg_pak'],
                '03' => $this->lang['pkg_tube'],
                '01' => $this->lang['pkg_envelope'],
                '25' => $this->lang['pkg_10kg_box'],
                '24' => $this->lang['pkg_25kg_box'],
                //30 = Pallet
                //2a = Small Express Box
                //2b = Medium Express Box
                //2c = Large Express Box
                //56 = Flats
                //57 = Parcels
                //58 = BPM
                //59 = First Class
                //60 = Priority
                //61 = Machinables
                //62 = Irregulars
                //63 = Parcel Post
                //64 = BPM Parcel
                //65 = Media Mail
                //66 = BPM Flat
                //67 = Standard Flat
            ],
            'CODMap' => [
                'ANY'             => lang('any'),
                'GUARANTEED_FUNDS'=> lang('guaranteed_funds', $this->moduleID),
                'CASH'            => lang('cash')],
            'PaymentMap' => [
                'SENDER'     => lang('sender', $this->moduleID),
                'RECIPIENT'  => lang('recipient', $this->moduleID),
                'THIRD_PARTY'=> lang('third_party', $this->moduleID),
                'COLLECT'    => lang('collect', $this->moduleID)],
            'SignatureMap' => [
                'DELIVERYWITHOUTSIGNATURE'=> lang('no_sig_rqd', $this->moduleID),
                'INDIRECT'                => lang('sig_rqd', $this->moduleID),
                'ADULT'                   => lang('adult_sig', $this->moduleID)],
            'LTLClasses' => [
                '0'   => lang('select'),
                '50'  => '50',
                '55'  => '55',
                '60'  => '60',
                '65'  => '65',
                '70'  => '70',
                '77.5'=> '77.5',
                '85'  => '85',
                '92.5'=> '92.5',
                '100' => '100',
                '110' => '110',
                '125' => '125',
                '150' => '150',
                '175' => '175',
                '200' => '200',
                '250' => '250',
                '300' => '300'],
             'paperTypes' => [
                ['id'=>'PAPER_4X6',                     'text'=>lang('label_01', $this->moduleID)],
                ['id'=>'PAPER_4X8',                     'text'=>lang('label_02', $this->moduleID)],
                ['id'=>'PAPER_4X9',                     'text'=>lang('label_03', $this->moduleID)],
                ['id'=>'PAPER_7X4.75',                  'text'=>lang('label_04', $this->moduleID)],
                ['id'=>'PAPER_8.5X11_BOTTOM_HALF_LABEL','text'=>lang('label_05', $this->moduleID)],
                ['id'=>'PAPER_8.5X11_TOP_HALF_LABEL',   'text'=>lang('label_06', $this->moduleID)],
                ['id'=>'STOCK_4X6',                     'text'=>lang('label_07', $this->moduleID)],
                ['id'=>'STOCK_4X6.75_LEADING_DOC_TAB',  'text'=>lang('label_08', $this->moduleID)],
                ['id'=>'STOCK_4X6.75_TRAILING_DOC_TAB', 'text'=>lang('label_09', $this->moduleID)],
                ['id'=>'STOCK_4X8',                     'text'=>lang('label_10', $this->moduleID)],
                ['id'=>'STOCK_4X9_LEADING_DOC_TAB',     'text'=>lang('label_11', $this->moduleID)],
                ['id'=>'STOCK_4X9_TRAILING_DOC_TAB',    'text'=>lang('label_12', $this->moduleID)]]];
    }
}
