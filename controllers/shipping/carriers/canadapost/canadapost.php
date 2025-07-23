<?php

/*
 * Shipping extension for Canada Post shipments
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
 * @version    7.x Last Update: 2025-07-23
 * @filesource /controllers/shipping/carriers/canadapost/canadapost.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__)."/../../functions.php", 'retrieve_carrier_function');

// Canada Post specifics
ini_set("soap.wsdl_cache_enabled", "0");
define('CANADAPOST_CERT', BIZBOOKS_ROOT.'controllers/shipping/carriers/canadapost/cacert.pem');
define('CANADAPOST_TEST_HOST', 'ct.soa-gw.canadapost.ca');
define('CANADAPOST_HOST', 'soa-gw.canadapost.ca');
define('CANADAPOST_TEST_RATE_URL', 'https://ct.soa-gw.canadapost.ca/rs/soap/rating/v3');
define('CANADAPOST_RATE_URL', 'https://soa-gw.canadapost.ca/rs/soap/rating/v3');
define('CANADAPOST_RATE_WSDL', BIZBOOKS_ROOT.'controllers/shipping/carriers/canadapost/rating.wsdl');

class canadapost
{

    public $default_insurance_value = 99.00; // FedEx maximum insurance value in USD if not specified
    public $moduleID  = 'shipping';
    public $methodDir = 'carriers';
    public $code      = 'canadapost';
    public $settings;
    public $contact_type;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    public $rateCodes = [
        'DOM.RP' => 'GND',
        'DOM.EP' => '1DM',
        'DOM.XP' => '1DA',
        'DOM.XP.CERT' => '1DP',
        'DOM.PC' => '2DA',
//      'DOM.DT' => 'XXX',
        'DOM.LIB' => 'GDR',
        'USA.EP' => 'U2D',
        'USA.PW.ENV' => 'UPE',
        'USA.PW.PAK' => 'UPP',
        'USA.PW.PARCEL' => 'UPB',
        'USA.SP.AIR' => 'UPS',
        'USA.TP' => 'UPT',
        'USA.TP.LVM' => 'UTL',
        'USA.XP' => 'U1D',
        'INT.XP' => 'I1D',
        'INT.IP.AIR' => 'IGD',
        'INT.IP.SURF' => 'I2D',
        'INT.PW.ENV' => 'IPE',
        'INT.PW.PAK' => 'IPP',
        'INT.PW.PARCEL' => 'IP1',
        'INT.SP.AIR' => 'ISP',
        'INT.SP.SURF' => 'ISG',
        'INT.TP' => 'ITP'];
    public $lang = ['title'=>'Canada Post',
        'acronym'      => 'Canada Post',
        'description'  => 'Canada Post shipping with rates pulled directly from their servers.',
        'instructions' => '<h3>How to obtain your credentials from Canada Post</h3>'
        . '<p>Go the the Canada Post website and request credentials through the developer page. You will receive both a Development (test) set of credentials and Production credentials. Enter them in the Settings, save and re-login to reload your cache.</p>',
        'ship_pkg_desc' => 'To generate a label to ship a package via Canada Post, Click GO and fill out the form.',
        // General
        // Configuration
        'test_mode'    => 'Test/Production mode used for testing rates/labels',
        'cust_num'     => 'Customer Number',
        'user_test'    => 'Username for Test ',
        'pass_test'    => 'Password for Test',
        'user_prod'    => 'Username for Production',
        'pass_prod'    => 'Password for Production',
        'max_weight'   => 'Maximum allowed box weight, Canada Post limits this to 70 pounds.',
        'printer_type' => 'Type of printer to use for printing labels. PDF for plain paper, Thermal for FedEx Thermal Label Printer (See Help file before selecting Thermal printer)',
        'printer_name' => 'Sets then name of the printer to use for printing labels as defined in the printer preferences for the local workstation.',
        'label_thermal'=> 'Label size for thermal printing.',
        'label_pdf'    => 'Label size for plain paper printing.',
        // Settings
        'GND' =>  'Regular Parcel',
        '1DM' =>  'Expedited Parcel',
        '1DA' =>  'Xpresspost',
        '1DP' =>  'Xpresspost Certified',
        '2DA' =>  'Priority',
    //  'XXX' =>  'Delivered Tonight',
        'GDR' =>  'Library Books',
        'U2D' =>  'Expedited Parcel USA',
        'UPE' =>  'Priority Worldwide Envelope USA',
        'UPP' =>  'Priority Worldwide Pak USA',
        'UPB' =>  'Priority Worldwide Parcel USA',
        'UPS' =>  'Small Packet USA Air',
        'UPT' =>  'Tracked Packet – USA',
        'UTL' =>  'Tracked Packet – USA (LVM)',
        'U1D' =>  'Xpresspost USA',
        'I1D' =>  'Xpresspost International',
        'IGD' =>  'International Parcel Air',
        'I2D' =>  'International Parcel Surface',
        'IPE' =>  'Priority Worldwide Envelope Int',
        'IPP' =>  'Priority Worldwide Pak Int',
        'IP1' =>  'Priority Worldwide parcel Int',
        'ISP' =>  'Small Packet International Air',
        'ISG' =>  'Small Packet International Surface',
        'ITP' =>  'Tracked Packet – International',
        // General defines
        'err_address_val_country' => 'The Canada Post address validation tool only works in Canada!',
        // Label manager
        'error_postal_code' => 'Postal Code is required to use the Canada Post module'];

    function __construct()
    {
        $this->lang = array_replace(getLang($this->moduleID), $this->lang);
        localizeLang($this->lang, $this->methodDir, $this->code);
        $tabImage = BIZBOOKS_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/tab_logo.png";
        $this->lang['tabTitle']= "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
        $this->getSettings();
        $this->contact_type = clean('cType', ['format' => 'char', 'default' => 'c'], 'post');
    }

    private function getSettings()
    {
        $this->settings = [
            'gl_acct_c'=>getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'),
            'gl_acct_v'=>getModuleCache('shipping', 'settings', 'general', 'gl_shipping_v'),
            'order'=>10,
            'test_mode' => 'test',
            'cust_num' => '',
            'user_test' => '', 'pass_test' => '', 'user_prod' => '', 'pass_prod' => '',
            'max_weight' => 70,
            'printer_type' => 'PDF', 'printer_name' => '', 'label_pdf' => 'PAPER_8.5X11_TOP_HALF_LABEL', 'label_thermal' => 'STOCK_4X6.75_LEADING_DOC_TAB',
            'service_types' => 'GND:GDR:1DM:1DA:1DP:2DA:I1D:I2D:IGD',
            'default'=>'0'];
        $usrSettings = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
    }

    public function settingsStructure()
    {
        $noYes   = [['id' => '0', 'text' => lang('no')], ['id' => '1', 'text' => lang('yes')]];
        $servers = [['id' => 'test', 'text' => lang('test')], ['id' => 'prod', 'text' => lang('production')]];
        $printers= [['id' => 'pdf', 'text' => $this->lang['plain_paper']], ['id' => 'thermal', 'text' => $this->lang['thermal']]];
        $services= [];
        foreach ($this->rateCodes as $code) { $services[] = ['id' => $code, 'text' => $this->lang[$code]]; }
        $paperTypes = [
                ['id' => 'PAPER_4X6', 'text' => $this->lang['label_01']],
                ['id' => 'PAPER_4X8', 'text' => $this->lang['label_02']],
                ['id' => 'PAPER_4X9', 'text' => $this->lang['label_03']],
                ['id' => 'PAPER_7X4.75', 'text' => $this->lang['label_04']],
                ['id' => 'PAPER_8.5X11_BOTTOM_HALF_LABEL', 'text' => $this->lang['label_05']],
                ['id' => 'PAPER_8.5X11_TOP_HALF_LABEL', 'text' => $this->lang['label_06']],
                ['id' => 'STOCK_4X6', 'text' => $this->lang['label_07']],
                ['id' => 'STOCK_4X6.75_LEADING_DOC_TAB', 'text' => $this->lang['label_08']],
                ['id' => 'STOCK_4X6.75_TRAILING_DOC_TAB', 'text' => $this->lang['label_09']],
                ['id' => 'STOCK_4X8', 'text' => $this->lang['label_10']],
                ['id' => 'STOCK_4X9_LEADING_DOC_TAB', 'text' => $this->lang['label_11']],
                ['id' => 'STOCK_4X9_TRAILING_DOC_TAB', 'text' => $this->lang['label_12']]];
        return [
            'gl_acct_c'    => ['label'=>$this->lang['gl_shipping_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'    => ['label'=>$this->lang['gl_shipping_v_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'order'        => ['label'=>lang('sort_order'),          'position'=>'after','attr'=>['type'=>'integer','size'=>'3','value'=>$this->settings['order']]],
            'test_mode'    => ['label'=>$this->lang['test_mode'],    'position'=>'after','values'=>$servers,'attr'=>['type'=>'select','value'=>$this->settings['test_mode']]],
            'cust_num'     => ['label'=>$this->lang['cust_num'],     'position'=>'after','attr'=>['size'=>'35','value'=>$this->settings['cust_num']]],
            'user_test'    => ['label'=>$this->lang['user_test'],    'position'=>'after','attr'=>['size'=>'35','value'=>$this->settings['user_test']]],
            'pass_test'    => ['label'=>$this->lang['pass_test'],    'position'=>'after','attr'=>['size'=>'35','value'=>$this->settings['pass_test']]],
            'user_prod'    => ['label'=>$this->lang['user_prod'],    'position'=>'after','attr'=>['size'=>'35','value'=>$this->settings['user_prod']]],
            'pass_prod'    => ['label'=>$this->lang['pass_prod'],    'position'=>'after','attr'=>['size'=>'35','value'=>$this->settings['pass_prod']]],
            'max_weight'   => ['label'=>$this->lang['max_weight'],   'position'=>'after','attr'=>['type'=>'integer','size'=>'3','maxlength'=>'3','value'=>$this->settings['max_weight']]],
            'printer_type' => ['label'=>$this->lang['printer_type'], 'position'=>'after','values'=>$printers,'attr'=>['type'=>'select','value'=>$this->settings['printer_type']]],
            'printer_name' => ['label'=>$this->lang['printer_name'], 'position'=>'after','attr'=>['value'=>$this->settings['printer_name']]],
            'label_pdf'    => ['label'=>$this->lang['label_pdf'],    'position'=>'after','values'=>$paperTypes,'attr'=>['type'=>'select','value'=>$this->settings['label_pdf']]],
            'label_thermal'=> ['label'=>$this->lang['label_thermal'],'position'=>'after','values'=>$paperTypes,'attr'=>['type'=>'select','value'=>$this->settings['label_thermal']]],
            'service_types'=> ['label'=>$this->lang['shipping_settings_default_service'],'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>'15','multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'default'      => ['label'=>$this->lang['shipping_settings_default_rate'],'position'=>'after','values'=>$noYes,'attr'=>['type'=>'select','value'=>$this->settings['default']]]];
    }

    public function settingSave()
    {
        $meta   = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx= metaIdxClean($meta);
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nSetting settings:services to: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    public function remove() {

    }

// ***************************************************************************************************************
//                                CANADAPOST Homepage Tab HTML Form
// ***************************************************************************************************************

// ***************************************************************************************************************
//                                CANADAPOST Address Validation Service
// ***************************************************************************************************************
    /*
     * This does not work problem with WebAuthenticationDetail, send a request to get information about.
     */
    /*
      function validateAddress($request=false)
      {
      if (!$request) { $request = $_POST; }
      if ($request['country'] <> 'USA') { return ['score'=>0, 'status'=>lang('fail'), 'notes'=>$this->lang['err_address_val_country']]; }
      ini_set("soap.wsdl_cache_enabled", "0");
      $client = new \SoapClient(CANADAPOST_ADDR_VAL_WSDL, ['trace' => 1]);
      $soapRequest = $this->FormatFedExAddressValidation($request);
      //msgDebug("request is: " . print_r($request, true));
      try {
      $response = $client->addressValidation($soapRequest);  // FedEx web service invocation
      if ($response->HighestSeverity != 'FAILURE' && $response->HighestSeverity != 'ERROR'){ // success
      $score       = $response->AddressResults->ProposedAddressDetails->Score;
      $changes = $response->AddressResults->ProposedAddressDetails->Changes;
      if (!is_array($changes)) { $changes = [$changes]; }
      if ($score > 50) {
      $streetLines = $response->AddressResults->ProposedAddressDetails->Address->StreetLines;
      if (!is_array($streetLines)) { $streetLines = [$streetLines]; }
      $address = [
      'primary_name'=> isset($response->AddressResults->AddressId) ? $response->AddressResults->AddressId : '',
      'contact'     => isset($request['contact']) ? strtoupper($request['contact']) : '',
      'address1'    => $streetLines[0],
      'address2'    => isset($streetLines[1]) ? $streetLines[1] : (isset($request['address2']) ? strtoupper($request['address2']) : ''),
      'city'        => $response->AddressResults->ProposedAddressDetails->Address->City,
      'state'       => $response->AddressResults->ProposedAddressDetails->Address->StateOrProvinceCode,
      'postal_code' => $response->AddressResults->ProposedAddressDetails->Address->PostalCode,
      'country'     => clean($response->AddressResults->ProposedAddressDetails->Address->CountryCode, ['format'=>'country','option'=>'ISO2']),
      ];
      $resi = $response->AddressResults->ProposedAddressDetails->ResidentialStatus;
      } else {
      $address = [];
      $resi = 'RESIDENTIAL';
      }
      $output = [
      'status' => 'success', // CONFIRMED or UNAVAILABLE
      'score'  => $score,
      'notes'  => $resi."<br />Delivery: ".$response->AddressResults->ProposedAddressDetails->DeliveryPointValidation.
      ' - '."<br />Changes: ".implode(' - ',$changes),
      'resi'   => $resi=='RESIDENTIAL' ? 1 : 0,
      'address'=> $address,
      ];
      msgDebug("success ".print_r($response, true));
      } else { // failed
      msgDebug("addval error ".print_r($response->Notifications, true));
      msgAdd("addval error request  is:".print_r($soapRequest, true));
      msgAdd("addval error response is:".print_r($response->Notifications, true));
      msgAdd("      Last response: " . htmlspecialchars($client->__getLastResponse()));
      $output = ['score'=>0, 'status'=>lang('fail'), 'notes'=>"Last response: ".htmlspecialchars($client->__getLastResponse())];
      }
      } catch (\SoapFault $exception) {
      msgDebug("soap exception ".$exception->getMessage().", message: ".$exception->__toString());
      msgAdd("soap exception ".$exception->getMessage().", message: ".$exception->__toString());
      $output = ['score'=>0, 'status'=>lang('fail'), 'notes'=>"soap exception ".$exception->getMessage().", message: ".$exception->__toString()];
      }
      return $output;
      }

      private function FormatFedExAddressValidation($post)
      {
      $request['WebAuthenticationDetail'] = [
      'UserCredential' => [
      'Key'     => $this->settings['auth_key'],
      'Password'=> $this->settings['auth_pw']],
      ],
      ];
      $request['ClientDetail'] = [
      'AccountNumber'=> $this->settings['acct_number'],
      'MeterNumber'  => $this->settings['meter_number'],
      ];
      $request['TransactionDetail'] = ['CustomerTransactionId' => ' *** Address Validation Request ***'];
      $request['Version'] = [
      'ServiceId'   => 'aval',
      'Major'       => CANADAPOST_ADD_VAL_VERSION,
      'Intermediate'=> '0',
      'Minor'       => '0',
      ];
      $request['RequestTimestamp'] = biz_date('c');
      $request['Options'] = [
      'CheckResidentialStatus' => 1,
      //        'MaximumNumberOfMatches' => 5,
      //        'StreetAccuracy' => 'LOOSE',
      //        'DirectionalAccuracy' => 'LOOSE',
      //        'CompanyNameAccuracy' => 'LOOSE',
      //        'ConvertToUpperCase' => 1,
      //        'RecognizeAlternateCityNames' => 1,
      //        'ReturnParsedElements' => 1
      ];
      $streetLines = [clean($post['address1'], 'text')];
      if (isset($post['address2'])) { $streetLines[] = clean($post['address2'], 'text'); }
      $pri_name = isset($post['primary_name']) ? clean($post['primary_name'], 'text') : '';
      $request['AddressesToValidate'] = [
      0 => [
      'AddressId' => strtoupper($pri_name),
      'Address' => [
      'CompanyName' => $pri_name,
      'StreetLines' => $streetLines,
      'PostalCode'  => clean($post['postal_code'], 'text'),
      //                'CountryCode' => clean($post['country_s'], ['format'=>'country','option'=>'ISO2']),
      ],
      ],
      ];
      return $request;
      }
     */
// ***************************************************************************************************************
//                                CANADAPOST RATE AND SERVICE REQUEST
// ***************************************************************************************************************
    function rateQuote($pkg) {
        $arrRates = [];
        if ($pkg['settings']['weight'] == 0) {
            msgAdd(lang('ERROR_WEIGHT_ZERO'));
            return $arrRates;
        }
        if ($pkg['ship']['postal_code'] == '') {
            msgAdd($this->lang['error_postal_code']);
            return $arrRates;
        }
        $user_choices = explode(':', str_replace(' ', '', $this->settings['service_types']));
        $hostname = $this->settings['test_mode'] == 'test' ? CANADAPOST_TEST_HOST : CANADAPOST_HOST;
        $location = $this->settings['test_mode'] == 'test' ? CANADAPOST_TEST_RATE_URL : CANADAPOST_RATE_URL;
        $opts = ['ssl' => ['verify_peer' => false, 'cafile' => CANADAPOST_CERT, 'peer_name' => $hostname]];
        $ctx = stream_context_create($opts);
        $client = new \SoapClient(CANADAPOST_RATE_WSDL, ['location' => $location, 'features' => SOAP_SINGLE_ELEMENT_ARRAYS, 'stream_context' => $ctx]);
        // Set WS Security UsernameToken
        $WSSENS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $usernameToken = new stdClass();
        $usernameToken->Username = new SoapVar($this->settings['user_test'], XSD_STRING, null, null, null, $WSSENS);
        $usernameToken->Password = new SoapVar($this->settings['pass_test'], XSD_STRING, null, null, null, $WSSENS);
        $content = new stdClass();
        $content->UsernameToken = new SoapVar($usernameToken, SOAP_ENC_OBJECT, null, null, null, $WSSENS);
        $header = new SOAPHeader($WSSENS, 'Security', $content);
        $client->__setSoapHeaders($header);
        if (($pkg['settings']['weight'] / $pkg['settings']['num_boxes']) <= $this->settings['max_weight']) {
            $arrRates = $this->queryCaPost($client, $pkg, $user_choices);
        }
        msgDebug("array rates is: " . print_r($arrRates, true));
        return $arrRates;
    }

    function queryCaPost($client, $pkg, $user_choices) {
        $arrRates = [];
        $request = $this->FormatCaPostRateRequest($pkg);
        msgDebug("\nCanada Post XML Submit String: " . print_r($request, true));
        try {
            $response = $client->__soapCall('GetRates', $request, NULL, NULL);
            if (isset($response->{'price-quotes'})) {
                foreach ($response->{'price-quotes'}->{'price-quote'} as $rateReply) {
                    $service = isset($this->rateCodes[$rateReply->{'service-code'}]) ? $this->rateCodes[$rateReply->{'service-code'}] : false;
                    msgDebug("\nFound Canada Post service code: " . $rateReply->{'service-code'} . " with cost " . $rateReply->{'price-details'}->{'due'});
                    if ($service && in_array($service, $user_choices)) {
                        $arrRates[$service] = ['title' => $this->lang[$service], 'gl_acct' => $this->settings['gl_acct_' . $this->contact_type], 'book' => '', 'cost' => '', 'quote' => '', 'note' => ''];
                        $arrRates[$service]['cost'] = $rateReply->{'price-details'}->{'due'};
                        msgDebug("\nSetting cost = " . $rateReply->{'price-details'}->{'due'} . " for service $service");
                        if (isset($rateReply->{'service-standard'})) {
                            $note = "Guaranteed: " . ($rateReply->{'service-standard'}->{'guaranteed-delivery'} ? lang('yes') : lang('no'));
                            if (isset($rateReply->{'service-standard'}->{'expected-delivery-date'})) {
                                $note .= ", Commit: " . biz_date("D M j, g:i a", strtotime($rateReply->{'service-standard'}->{'expected-delivery-date'}));
                            }
                            $arrRates[$service]['note'] = $note;
                        }
                        $arrRates[$service]['book'] = $rateReply->{'price-details'}->{'due'};
                        $arrRates[$service]['quote'] = $arrRates[$service]['book'];
                    }
                }
            } else {
                $message = '';
                foreach ($response->{'messages'}->{'message'} as $notification) {
                    $message .= "Code: $notification->code - $notification->description<br />";
                }
                msgDebug("($this->code) " . lang('error') . " - $message");
                msgAdd("($this->code) " . lang('error') . " - $message");
            }
        } catch (\SoapFault $exception) {
            $message = " [soap fault] ({$exception->faultcode}) {$exception->faultstring}";
            msgDebug(sprintf(lang('err_no_communication'), $this->code) . $message);
            msgAdd(sprintf(lang('err_no_communication'), $this->code) . $message);
//          echo 'Fault Code: ' . trim($e->faultcode) . "\n";
//          echo 'Fault Reason: ' . trim($e->getMessage()) . "\n";
        }
        msgDebug("\nCanada Post Request " . $client->__getLastRequest());
        msgDebug("\nCanada Post Response " . $client->__getLastResponse());
        msgDebug("\nCanada Post returned arrRates = " . print_r($arrRates, true));
        return $arrRates;
    }

    private function FormatCaPostRateRequest($pkg) {
        $myLang = substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2);
        $lang = $myLang == 'fr' ? 'FR' : 'EN';
        $request = [
            'get-rates-request' => [
                'locale' => $lang,
                'mailing-scenario' => [
                    'customer-number' => $this->settings['cust_num'],
                    'parcel-characteristics' => [
                        'weight' => $pkg['settings']['weight'],
                    ],
                    'origin-postal-code' => $pkg['bill']['postal_code'],
                ],
            ],
        ];
        if ($pkg['ship']['country'] == 'CAN' || $pkg['ship']['country'] == 'CA') {
            $request['get-rates-request']['mailing-scenario']['destination']['domestic']['postal-code'] = $pkg['ship']['postal_code'];
        } elseif ($pkg['ship']['country'] == 'USA' || $pkg['ship']['country'] == 'US') {
            $request['get-rates-request']['mailing-scenario']['destination']['united-states']['zip-code'] = $pkg['ship']['postal_code'];
        } else {
            $countryCode = clean($pkg['ship']['country'], ['format'=>'country','option'=>'ISO2']);
            $request['get-rates-request']['mailing-scenario']['destination']['international']['country-code'] = $countryCode;
        }
        return $request;
    }

    /**
     * @param array $post
     * @param array $pkg
     * @param integer $cnt - current package count for multi-piece shipments
     * @param bool $is_freight - specifies if this is a freight shipment (true) or not (false)
     * @return string
     */
    private function FormatFedExShipRequest($post, $pkg, $cnt, $is_freight = false) {
        global $ZONE001_DEFINES;
        $request['WebAuthenticationDetail'] = array(
            'UserCredential' => array(
                'Key' => $this->settings['auth_key'],
                'Password' => $this->settings['auth_pw'],
            ),
        );
        $request['ClientDetail'] = array(
            'AccountNumber' => $this->settings['acct_number'],
            'MeterNumber' => $this->settings['meter_number'],
        );
        $request['TransactionDetail'] = array(
            'CustomerTransactionId' => '*** FedEx Shipping Request ***',
        );
        $request['Version'] = array(
            'ServiceId' => 'ship',
            'Major' => CANADAPOST_SHIP_WSDL_VERSION,
            'Intermediate' => '0',
            'Minor' => '0',
        );
        $request['RequestedShipment'] = array(
            'ShipTimestamp' => biz_date('c', $post['ship_date']),
            'DropoffType' => $post['ship_pickup'],
            'ServiceType' => $post['ship_method'],
            'PackagingType' => $post['ship_pkg'],
        );
        $request['RequestedShipment']['Shipper'] = array(
            'Contact' => array(
                'PersonName'  => getModuleCache('bizuno', 'settings', 'company', 'contact'),
                'CompanyName' => getModuleCache('bizuno', 'settings', 'company', 'primary_name'),
                'PhoneNumber' => getModuleCache('bizuno', 'settings', 'company', 'telephone1'),
            ),
            'Address' => array(
                'StreetLines' => array(
                    '0' => getModuleCache('bizuno', 'settings', 'company', 'address1'),
                    '1' => getModuleCache('bizuno', 'settings', 'company', 'address2'),
                ),
                'City' => getModuleCache('bizuno', 'settings', 'company', 'city'),
                'StateOrProvinceCode' => getModuleCache('bizuno', 'settings', 'company', 'state'),
                'PostalCode' => getModuleCache('bizuno', 'settings', 'company', 'postal_code'),
                'CountryCode' => clean(getModuleCache('bizuno', 'settings', 'company', 'country'), ['format'=>'country','option'=>'ISO2']),
            ),
        );
        $request['RequestedShipment']['Recipient'] = array(
            'Contact' => array(
                'PersonName' => clean($post['contact_s'], 'text'),
                'CompanyName' => clean($post['primary_name_s'], 'text'),
                'PhoneNumber' => clean($post['telephone1_s'], 'telephone'),
            ),
            'Address' => array(
                'StreetLines' => array(
                    '0' => clean($post['address1_s'], 'text'),
                    '1' => clean($post['address2_s'], 'text'),
                ),
                'City' => strtoupper($post['city_s']),
                'StateOrProvinceCode' => strtoupper($post['state_s']),
                'PostalCode' => clean($post['postal_code_s'], 'text'),
                'CountryCode' => clean($post['country_s'], ['format'=>'country','option'=>'ISO2']),
                'Residential' => isset($post['ship_resi']) ? '1' : '0',
            ),
        );
        if ($is_freight) {
            $pay_acct = $this->settings['ltl_acct_num'];
        } else {
            $pay_acct = $this->settings['acct_number'];
        }
        if ($post['ship_bill_to'] != 'SENDER')
            $pay_acct = $post['ship_bill_act'];
        $request['RequestedShipment']['ShippingChargesPayment'] = array('PaymentType' => $post['ship_bill_to'],);
        $request['RequestedShipment']['ShippingChargesPayment']['Payor']['ResponsibleParty'] = array(
            'AccountNumber' => $pay_acct,
            'Contact' => 'payables',
        );
        if ($is_freight) {
//            $request['ClientDetail']['AccountNumber'] = $this->settings['ltl_acct_num']; // causes acct and meter not consistent error
            $request['RequestedShipment']['FreightShipmentDetail'] = array(
                'FedExFreightAccountNumber' => $pay_acct, //problem with account number, need to move to production to test further
                'FedExFreightBillingContactAndAddress' => array(
                    'Contact' => array(
                        'PersonName'  => getModuleCache('bizuno', 'settings', 'company', 'contact'),
                        'CompanyName' => getModuleCache('bizuno', 'settings', 'company', 'primary_name'),
                        'PhoneNumber' => getModuleCache('bizuno', 'settings', 'company', 'telephone1'),
                    ),
                    'Address' => array(
                        'StreetLines' => array(
                            '0' => clean(getModuleCache('bizuno', 'settings', 'company', 'address1'), 'text'), //'1202 Chalet Ln',
//                            '1' => clean(getModuleCache('bizuno', 'settings', 'company', 'address2'), 'text'),//'Do Not Delete - Test Account'
                        ),
                        'City' => getModuleCache('bizuno', 'settings', 'company', 'city'), //'Harrison',
                        'StateOrProvinceCode' => getModuleCache('bizuno', 'settings', 'company', 'state'), //'AR',
                        'PostalCode' => getModuleCache('bizuno', 'settings', 'company', 'postal_code'), //'72601',
                        'CountryCode' => clean(getModuleCache('bizuno', 'settings', 'company', 'country'), ['format'=>'country','option'=>'ISO2']),
                    ),
                ),
                'PrintedReferences' => array(
                    'Type' => 'SHIPPER_ID_NUMBER',
                    'Value' => $post['ship_ref_1'],
                ),
                'Role' => 'SHIPPER', // valid values are SHIPPER, THIRD_PARTY, and CONSIGNEE
                'PaymentType' => 'PREPAID', // $pkg['bill_charges']
                'CollectTermsType' => 'STANDARD',
                'DeclaredValuePerUnit' => array(
                    'Amount' => number_format(isset($post['total_value']) && $post['total_value'] ? clean($post['total_value'], 'currency') : 100, 2),
                    'Currency' => clean($post['currencyUOM'], 'text'),
                ),
//                'LiabilityCoverageDetail' => array(
//                  'CoverageType'   => 'NEW',
//                  'CoverageAmount' => array(
//                    'Currency' => 'CAD',
//                    'Amount'   => '50',
//                  ),
//                ),
                'TotalHandlingUnits' => $post['total_packages'],
//                'ClientDiscountPercent' => 0, // should be actual charge
//                'PalletWeight' => array(
//                    'Units' => substr(getModuleCache('shipping', 'settings', 'general', 'weight_uom'),0,2),
//                    'Value' => round(25, 0),
//                ),
            );
            foreach ($pkg as $lineItem) {
                $request['RequestedShipment']['FreightShipmentDetail']['LineItems'][] = array(
                    'FreightClass' => 'CLASS_' . $post['ltl_class'],
                    'Packaging' => 'PALLET',
                    'Description' => $post['ltl_desc'],
//                    'ClassProvidedByCustomer' => false,
                    'HandlingUnits' => $lineItem['qty'],
                    'Pieces' => $lineItem['qty'],
                    'BillOfLaddingNumber' => $post['ship_ref_1'],
                    'PurchaseOrderNumber' => $post['ship_ref_2'],
                    'Weight' => array(
                        'Units' => substr($post['weightUOM'], 0, 2),
                        'Value' => round($lineItem['weight'], 0),
                    ),
                    'Dimensions' => array(// set some minimum dimensions in case defaults are not sent, assume inches for now
                        'Length' => $lineItem['length'],
                        'Width' => $lineItem['width'],
                        'Height' => $lineItem['height'],
                        'Units' => clean($post['dimUOM'], 'text'),
                    ),
//                    'Volume' => array( // I think this gets calculated
//                        'Units' => 'CUBIC_FT',
//                        'Value' => 30
//                    ),
                );
            }
        } else { // provide small package/express freight details
            $pay_acct = $post['ship_bill_to'] <> 'SENDER' ? $post['ship_bill_act'] : $this->settings['acct_number']['attr']['value'];
            if (isset($post['cod'])) {
                $request['RequestedShipment']['SpecialServicesRequested'] = array(
                    'SpecialServiceTypes' => array('COD'),
                    'CodDetail' => array('CollectionType' => $post['ship_cod_type']),
                );
            }
            if (isset($post['ship_saturday'])) {
                $request['RequestedShipment']['SpecialServicesRequested']['SpecialServiceTypes'][] = 'SATURDAY_DELIVERY';
            }
            $request['RequestedShipment']['RequestedPackageLineItems'] = array(
                'SequenceNumber' => $cnt + 1,
                'InsuredValue' => array(
                    'Amount' => $pkg['value'] ? clean($pkg['value'], 'currency') : '',
                    'Currency' => $post['currencyUOM'],
                ),
                'Weight' => array(
                    'Value' => number_format($pkg['weight'], 1, '.', ''),
                    'Units' => substr($post['weightUOM'], 0, 2),
                ),
                'Dimensions' => array(
                    'Length' => max(8, $pkg['length']),
                    'Width' => max(6, $pkg['width']),
                    'Height' => max(4, $pkg['height']),
                    'Units' => clean($post['dimUOM'], 'text'),
                ),
                'CustomerReferences' => array(// valid values CUSTOMER_REFERENCE, INVOICE_NUMBER, P_O_NUMBER and SHIPMENT_INTEGRITY
                    '0' => array(
                        'CustomerReferenceType' => 'CUSTOMER_REFERENCE',
                        'Value' => $post['ship_ref_1'] . '-' . ($cnt + 1),
                    ),
                    '1' => array(
                        'CustomerReferenceType' => 'INVOICE_NUMBER',
                        'Value' => $post['ship_ref_1'],
                    ),
                    '2' => array(
                        'CustomerReferenceType' => 'P_O_NUMBER',
                        'Value' => $post['ship_ref_2'],
                    )
                ),
            );
            if (isset($post['MasterTracking'])) { // link to the master package, for package 2 on
                $request['RequestedShipment']['MasterTrackingId']['TrackingNumber'] = $post['MasterTracking'];
            }
            if (isset($post['cod'])) {
                $request['RequestedShipment']['RequestedPackageLineItems']['SpecialServicesRequested'] = array(
                    'CodCollectionAmount' => array(
                        'Amount' => number_format($pkg['value'], 2),
                        'Currency' => $post['currencyUOM'],
                    ),
                    'EMailNotificationDetail' => array(
                        'Shipper' => array(
                            'EMailAddress' => getModuleCache('bizuno', 'settings', 'company', 'email'),
                            'NotifyOnShipment' => isset($post['email_sender_ship']) ? '1' : '0',
                            'NotifyOnException' => isset($post['email_sender_exc']) ? '1' : '0',
                            'NotifyOnDelivery' => isset($post['email_sender_del']) ? '1' : '0',
                            'Localization"' => substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2),
                        ),
                        'Recipient' => array(
                            'EMailAddress' => clean($post['email_s'], 'email'),
                            'NotifyOnShipment' => isset($post['email_recipient_ship']) ? '1' : '0',
                            'NotifyOnException' => isset($post['email_recipient_exc']) ? '1' : '0',
                            'NotifyOnDelivery' => isset($post['email_recipient_del']) ? '1' : '0',
                            'Localization"' => substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2),
                        ),
                    ),
                );
            }
        } // end if
        // SmartPost
        if ($post['ship_method'] == 'SMART_POST') {
            $request['RequestedShipment']['SmartPostDetail'] = array(
                'Indicia' => $post['total_weight'] < 1 ? 'PRESORTED_STANDARD' : 'PARCEL_SELECT',
                'HubId' => $this->settings['sp_hub'],
            );
            unset($request['RequestedShipment']['RequestedPackageLineItems']['InsuredValue']);
        }
        $request['RequestedShipment']['PackageCount'] = $post['total_packages'];
        $request['RequestedShipment']['TotalShipmentWeight'] = $post['total_weight'];
        $request['RequestedShipment']['PackageDetail'] = 'INDIVIDUAL_PACKAGES';
        $request['RequestedShipment']['RateRequestTypes'] = 'LIST'; // valid values ACCOUNT and LIST
        $request['RequestedShipment']['LabelSpecification']['LabelFormatType'] = 'COMMON2D';
        $request['RequestedShipment']['LabelSpecification']['CustomerSpecifiedDetail']['MaskedData'] = 'SHIPPER_ACCOUNT_NUMBER';
        // For thermal labels
        if (!$is_freight && $this->settings['printer_type'] == 'thermal') {
            $request['RequestedShipment']['LabelSpecification']['ImageType'] = 'EPL2'; // ZPLII
            $request['RequestedShipment']['LabelSpecification']['LabelStockType'] = $this->settings['label_thermal'];
            $request['RequestedShipment']['LabelSpecification']['LabelPrintingOrientation'] = 'TOP_EDGE_OF_TEXT_FIRST';
            if (CANADAPOST_DOCTABCONTENT == 'Zone001') {
                $request['RequestedShipment']['LabelSpecification']['CustomerSpecifiedDetail']['DocTabContent']['DocTabContentType'] = 'ZONE001';
                // define the zones and values
                $ZONE001_DEFINES = array(// up to 12 zones can be defined
                    '1' => array('Header' => lang('date'), 'Literal' => biz_date('Y-m-d')),
                    '2' => array('Header' => 'Transit', 'Value' => 'REPLY/SHIPMENT/RoutingDetail/TransitTime'),
                    '3' => array('Header' => 'PO Num', 'Literal' => $post['ship_ref_2']),
                    '4' => array('Header' => 'Inv Num', 'Literal' => $post['ship_ref_1']),
                    '5' => array('Header' => 'Weight', 'Value' => 'REQUEST/PACKAGE/Weight/Value'),
                    '6' => array('Header' => 'Dim Wt', 'Value' => 'REPLY/PACKAGE/RATES/ACTUAL/DimWeight/Value'),
                    '7' => array('Header' => 'Pkg #', 'Value' => 'REQUEST/PACKAGE/SequenceNumber'),
                    '8' => array('Header' => 'Pkg Cnt', 'Value' => 'REQUEST/SHIPMENT/PackageCount'),
                    '9' => array('Header' => 'DV', 'Value' => 'REQUEST/PACKAGE/InsuredValue/Amount'),
                    '10' => array('Header' => 'Insured', 'Value' => 'REQUEST/PACKAGE/InsuredValue/Amount'),
                    '11' => array('Header' => 'List', 'Value' => 'REPLY/SHIPMENT/RATES/PAYOR_LIST_PACKAGE/TotalNetCharge/Amount'),
                    '12' => array('Header' => 'Net', 'Value' => 'REPLY/SHIPMENT/RATES/PAYOR_ACCOUNT_PACKAGE/TotalNetCharge/Amount'),
                );
                foreach ($ZONE001_DEFINES as $zone => $settings) {
                    $request['RequestedShipment']['LabelSpecification']['CustomerSpecifiedDetail']['DocTabContent'][CANADAPOST_DOCTABCONTENT]['DocTabZoneSpecifications'][] = array(
                        'ZoneNumber' => $zone,
                        'Header' => $settings['Header'],
                        'DataField' => isset($settings['Value']) ? $settings['Value'] : '',
                        'LiteralValue' => isset($settings['Literal']) ? $settings['Literal'] : '',
                        'Justification' => isset($settings['Justification']) ? $settings['Justification'] : 'RIGHT',
                    );
                }
            }
        } elseif ($is_freight) {
            $request['RequestedShipment']['LabelSpecification']['LabelFormatType'] = 'CANADAPOST_FREIGHT_STRAIGHT_BILL_OF_LADING';
            $request['RequestedShipment']['LabelSpecification']['ImageType'] = 'PDF';
            $request['RequestedShipment']['LabelSpecification']['LabelStockType'] = 'PAPER_LETTER';
            $request['RequestedShipment']['LabelSpecification']['LabelPrintingOrientation'] = 'TOP_EDGE_OF_TEXT_FIRST';
            $request['RequestedShipment']['ShippingDocumentSpecification'] = array(
                'ShippingDocumentTypes' => array('FREIGHT_ADDRESS_LABEL'),
                'FreightAddressLabelDetail' => array(
                    'Format' => array(
                        'ImageType' => ($this->settings['printer_type'] == 'thermal') ? 'EPL2' : 'PDF', // ZPLII
                        'StockType' => ($this->settings['printer_type'] == 'thermal') ? $this->settings['label_thermal'] : 'PAPER_4X6',
                        'ProvideInstuctions' => '0',
                    ),
                    'Copies' => '1',
                ),
            );
        } else {
            $request['RequestedShipment']['LabelSpecification']['ImageType'] = 'PDF';
            $request['RequestedShipment']['LabelSpecification']['LabelStockType'] = $this->settings['label_pdf']; //wrong
        }
        return $request;
    }

// ***************************************************************************************************************
//                                Support Functions
// ***************************************************************************************************************
    function calculateDelivery($transit_time, $res = true) {
        $today = getdate();
        $today_date = biz_date('Y-m-d');
        $day_of_week = $today['wday'] - 1;  // 0 - Monday thru 6 - Sunday
        $holidays = [
            biz_date('Y-m-d', strtotime('1st January')), // New Years Day
            biz_date('Y-m-d', strtotime('Last Monday May')), // Memorial Day
            biz_date('Y-m-d', strtotime('4th July')), // Independence Day
            biz_date('Y-m-d', strtotime('First Monday September')), // Labor Day
            biz_date('Y-m-d', strtotime('Fourth Thursday November')), // Thanksgiving Day
            biz_date('Y-m-d', strtotime('25th December')), // Christmas Day
        ];
        switch ($transit_time) {
            case 'ONE_DAY': $offset = 1;
                break;
            case 'TWO_DAYS': $offset = 2;
                break;
            case 'THREE_DAYS':$offset = 3;
                break;
            case 'FOUR_DAYS': $offset = 4;
                break;
            case 'FIVE_DAYS': $offset = 5;
                break;
            case 'SIX_DAYS': $offset = 6;
                break;
            case 'SEVEN_DAYS':$offset = 7;
                break;
            case 'EIGHT_DAYS':$offset = 8;
                break;
            case 'NINE_DAYS': $offset = 9;
                break;
        }
        if (($day_of_week + $offset) > 9) {
            $offset = $offset + 4;
        } // passed through two weekends
        elseif (($day_of_week + $offset) > 4) {
            $offset = $offset + 2;
        } // passed through one weekend
        $delivery = biz_date('Y-m-d', strtotime("+$offset days"));
        // check for holidays
        foreach ($holidays as $holiday) {
            if ($today_date <= $holiday && $delivery >= $holiday) {
                $offset++;
                $delivery = biz_date('Y-m-d', strtotime("+$offset days"));
            }
        }
        $d = getdate(strtotime("+$offset days"));
        $delivery = biz_date('Y-m-d', mktime(0, 0, 0, $d['mon'], $d['mday'], $d['year']));
        return $delivery . ' ' . ($res ? $this->lang['del_time_RE'] : $this->lang['del_time_GD']);
    }

    function calculateDeliveryTime($method, $code, $res = true) {
        switch ($method) {
            default:
            case 'IGD':
            case '2DP':
            case '3DP': $guar_time = $res ? $this->lang['del_time_RE'] : $this->lang['del_time_2D'];
                break;
            case 'GND': // handled in calculateDelivery
            case 'GDR': // handled in calculateDelivery
            case 'ECF':
            case 'GDF': $guar_time = $this->lang['del_time_CM'];
                break;
            case 'I1D':
            case 'I2D':
            case '1DF':
            case '2DF':
            case '3DF': $guar_time = $this->lang['del_time_PR'];
                break;
            case '1DP':
                switch ($code) {
                    default:
                    case 'A1':
                    case 'A2':
                    case 'AA':
                    case 'A4': $guar_time = $this->lang['del_time_CM'];
                        break;
                    case 'A3':
                    case 'A5':
                    case 'AM': $guar_time = $this->lang['del_time_PR'];
                        break;
                }
                break;
            case '1DA':
                switch ($code) {
                    default:
                    case 'A1':
                    case 'A2':
                    case 'AA':
                    case 'A4': $guar_time = $this->lang['del_time_P1'];
                        break;
                    case 'A3':
                    case 'A5':
                    case 'AM': $guar_time = $this->lang['del_time_PR'];
                        break;
                    case 'RM':
                    case 'PM':
                    case 'A6': $guar_time = $this->lang['del_time_CM'];
                        break;
                }
                break;
            case '1DE':
                switch ($code) {
                    default:
                    case 'A1': $guar_time = $this->lang['del_time_A1'];
                        break;
                    case 'A2':
                    case 'A3': $guar_time = $this->lang['del_time_A2'];
                        break;
                    case 'A4': $guar_time = $this->lang['del_time_A4'];
                        break;
                    case 'A5':
                    case 'A6': $guar_time = $this->lang['del_time_A5'];
                        break;
                }
                break;
        }
        return $guar_time;
    }

    private function get_error_message($code) {
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

}
