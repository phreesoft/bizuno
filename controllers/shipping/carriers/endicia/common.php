<?php
/*
 * Shipping extension for Endicia - Common
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
 * @version    7.x Last Update: 2025-11-21
 * @filesource /controllers/shipping/carriers/endicia/common.php
 */

namespace bizuno;

class endiciaCommon
{
    public    $moduleID = 'shipping';
    public    $methodDir= 'carriers';
    public    $code     = 'endicia';
    protected $secID    = 'shipping';
    public    $defaults;
    public    $options;
    public    $settings;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    private   $psRequest= 'https://www.phreesoft.com/wp-json/phreesoft-api/v1/sera_request';
    public    $lang     = ['title'=>'US Postal Service',
        'acronym'    => 'Endicia',
        'description'=> 'US Postal Service shipping with rates pulled directly from USPS (Stamps.com/Endicia) servers. (Requires account/validation with Endicia)',
        'instructions' => '<h3>Step 1. Register for an account</h3>
    <p>Open the Endicia registration application form and follow the instructions to create an account. Usually the basic service is all that is needed (about $15.95 per month). <a href="http://www.endicia.com/labelserver/login.cfm?partid=lpst" target="_blank"><b>Click HERE to Signup</b></a></p>
    <h3>Step 2. Wait for Confirmation</h3><p>Check you email for a confirmation from Endicia with your account number. Enter it in the field below, save the changes. Also enter your temporary Pass Phrase you used during registration.</p>
    <h3>Step 3. Change Pass Phrase to Activate Account</h3><p>Navigate to Tools -> Shipping Manager -> US Postal Service tab. Click the <b>Change Pass Phrase</b> button to change your temporary pass phrase used to sign up for the account to a new pass phrase. The new pass phrase will be automatically updated in the field below.</p>
    <h3>Step 4. Buy Postage</h3><p>Navigate to Tools -> Shipping Manager -> US Postal Service tab. Select an amount and click the <b>Buy Postage</b> button to add a balance to your postage account.</p>
    <h3>Step 5. Configure Options</h3><p>Make sure the mode is set to Production for rate estimation and label printing. Contact PhreeSoft for the Dial-A-Zip password as it is a shared account with PhreeSoft and we would like to know who is using this service. You should now be able to retrieve rates, and print labels using the service. NOTE: PhreeSoft has performed all label testing on a Zebra ZP 505 printer. Older Eltron/Zebra 2442 and 2844 printers don\'t support the bar code types necessary for USPS postage printing.</p>',
        // configuration
//        'auth_user_lbl' => 'Enter your Stamps.com/Endicia account username',
//        'auth_pass_lbl' => 'Enter your Stamps.com/Endicia account password',
        'client_id_lbl' => 'Enter the Client ID from your RESTful API registration',
        'client_secret_lbl' => 'Enter the Client Secret from your RESTful API registration',
        'handling_fee' => 'Handling Fee',
        'package_types' => 'Package Types',
        'test_mode' => 'Test/Production mode used for testing shipping labels',
        'printer_type' => 'Type of printer to use for printing labels. PDF for plain paper, Thermal for Eltron/Zebra Label Printer',
        'printer_name' => 'Sets then name of the printer to use for printing labels as defined in the printer preferences for the local workstation',
        // general
        'reference1' => 'Reference 1',
        'label_thermal' => 'Label Material',
        'doc_tab' => '4x6 Thermal with DocTab',
        'lbl_msg_1' => 'Line 1 - Message to put on shipping label',
        'lbl_msg_2' => 'Line 2 - Message to put on shipping label',
        'lbl_msg_3' => 'Line 3 - Message to put on shipping label',
        // General defines
        'funds_min' => 'Postage balance minimum (in USD) to trigger reminder to buy more.',
        'funds_purch' => 'Amount of postage to buy when adding funds to your account.',
        'ship_pkg_title' => 'Ship Package',
        'ship_pkg_desc' => 'To generate a label to ship a package via US Postal Service, Click Go and fill out the form.',
        'postage_buy_title' => 'Buy Postage',
        'postage_buy_desc' => 'To add more postage to your account with Endicia, select an amount to purchase and press Go.',
        'passphrase_change' => 'Change Pass Phrase',
        'passphrase_current' => 'Enter Current Passphrase',
        'passphrase_new' => 'Enter New Passphrase',
        'passphrase_validate' => 'Re-enter Passphrase',
        'partner_id' => 'Partner ID',
        'msg_label_retrieve' => 'Successfully retrieved the USPS label, tracking # %s. Your postage balance is: %s',
        'err_postal_weight_zero' => 'The package weight must be greater than zero!',
        'err_pkg_too_heavy' => 'The package weight exceeds the maximum supported by this carrier!',
        'msg_purchase_success' => 'Your purchase was successful, your balance is now %s (transaction reference %s)',
        'msg_passphrase_desc' => 'Pass Phrase must be at least 5 characters long with a maximum of 64 characters. For added security, the Pass Phrase should be at least 10 characters long and include more than one word, use at least one uppercase and lowercase letter, one number and one non-text character (for example, punctuation). A Pass Phrase which has been used previously will be rejected.',
        'err_passphrase_wrong' => 'Your current Pass Phrase does not match what is stored in the system!',
        'err_passphrase_not_match' => 'Your new Pass Phrase does not match the confirmed Pass Phrase or is too short!',
        'msg_passphrase_change' => 'Your passphrase was successfully changed!',
        'msg_refund_approved' => 'Endicia tracking # %s refund approved: %s - %s',
        'err_postal_code_blank' => 'Postal Code is required to use the Endicia module!',
        'msg_tracking_results' => 'Tracking results from USPS for shipment id %s, tracking # %s is: %s',
        'msg_signup_confirm' => 'Signup confirmation from Endicia servers: %s. You will receive an email shortly to complete your activation.',
        'err_zip_not_match' => 'Address Validation error (%s) %s. The address must be corrected before a label can be generated.',
        '1DA' => 'UPS Next Day Air',
        '1DP' => 'USPS Priority Mail Express',
        '2DA' => 'UPS 2nd Day Air A.M.',
        '2DM' => 'UPS 2nd Day Air',
        '2DP' => 'USPS Priority Mail',
        '3DM' => 'UPS 3 Day Select',
        '3DP' => 'USPS Parcel Select',
        'GND' => 'USPS Ground Advantage',
        'GDR' => 'UPS Ground',
        'MPS_01' => 'Package',
        'MPS_02' => 'Large Envelope',
        'MPS_03' => 'Flat Rate Envelope',
        'MPS_04' => 'Flat Rate Padded Envelope',
        'MPS_05' => 'Flat Rate Legal Envelope',
        'MPS_06' => 'Small Flat Rate Box',
        'MPS_07' => 'Medium Flat Rate Box',
        'MPS_08' => 'Large Flat Rate Box',
        'MPS_09' => 'Regional Rate Box A',
        'MPS_10' => 'Regional Rate Box B',
        // Buy Postage Amounts
        '0010_dollars' => '$ 10.00',
        '0025_dollars' => '$ 25.00',
        '0100_dollars' => '$ 100.00',
        '0250_dollars' => '$ 250.00',
        '0500_dollars' => '$ 500.00',
        '1000_dollars' => "$ 1,000.00"];

    function __construct()
    {
        $this->lang = array_replace(getLang($this->moduleID), $this->lang);
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->defaults = ['client_key'=>'', 'gl_acct'=>getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'), 'handling_fee'=>0,
            'order'        => 50, 'default'=>false, 'funds_min'=>25, 'funds_purch'=>'100',
            'lbl_msg_1'    => '', 'lbl_msg_2'=>'', 'lbl_msg_3'=>'', 'label_thermal'=>'4x6.75-doctab',
            'service_types'=> '1DA:1DP:2DA:2DM:2DP:3DM:3DP:GND:GDR', 'package_types'=>'package:usps_flat_rate_envelope'];
        $this->options = $this->getOptions();
        $this->settings= array_replace_recursive($this->defaults, getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []));
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang, $this->options['rateCodes']);
    }

    /**
     *
     * @param type $endpoint
     * @param type $data
     * @return type
     */
    protected function queryREST($endpoint, $data=[]) // , $method='post'
    {
        global $io;
        msgDebug("\nEntering queryREST with endpoint = $endpoint");
        // get PhreeSoft API settings
        $apiUser= getModuleCache('api', 'settings', 'phreesoft_api', 'api_user');
        $apiPass= getModuleCache('api', 'settings', 'phreesoft_api', 'api_pass');
        msgDebug("\nread client key = {$this->settings['client_key']} and user = $apiUser");
        if (empty($this->settings['client_key'])) { return msgAdd("Error! You need a PhreeSoft.com API Client Key to get credentials to access the Stamps.com servers. Please contact PhreeSoft."); }
        $opts   = ['headers'=>['email'=>$apiUser, 'pass'=>$apiPass]];
        $content= ['psKey'  =>$this->settings['client_key'], 'endpoint'=>$endpoint, 'payload'=>$data];
        msgDebug("\nCalling Phreesoft.com with content = ".print_r($content, true));
        $resp= json_decode($io->cURL($this->psRequest, $content, 'post', $opts), true);
        if (empty($resp)) { msgAdd(sprintf(lang('err_no_communication'), $this->code), 'trap'); }
        if (!empty($resp['message'])) { 
            msgDebug("\nMessage received back from PhreeSoft servers: ".print_r($resp['message'], true));
            msgMerge($resp['message']); }
        return $resp;
    }

    /**  DEPRECATED (it's broken and not used anyway)
    protected function fundsCheck()
    {
        $resp = $this->queryREST('checkBalance', []);
        if (!empty($resp['Error'])) { return msgAdd("Error! Endicia error checking your funds balance: {$resp['error_description']}"); }
        $this->chkNeedToBuy($resp['amount_available']);
    } */

    protected function chkNeedToBuy($balance=10000)
    {
        if ($balance < $this->settings['funds_min']) {
            $btn = html5('', ['events'=>['onClick'=>"jsonAction('$this->moduleID/admin/fundsBuy&carrier=$this->code')"],'attr'=>['type'=>'button','value'=>lang('Buy Postage')]]);
            msgAdd("Your postage account funds are running low, please click the button to add more funds.\n\n$btn", 'info');
        }
    }

    public function fundsBuy()
    {
        msgDebug("\nEntering fundsBuy");
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $resp = $this->queryREST('buyPostage', ['amount'=>$this->settings['funds_purch'], 'currency'=>'usd']);
        if (!empty($resp['Error'])) { return msgAdd("Error! Endicia error adding funds: {$resp['error_description']}"); }
        msgAdd("Funds successfully added, your balance is now ".viewFormat($resp['amount_available']), 'success');
    }

    /**
     *
     * @return type
     */
    private function getOptions()
    {
        return [
            'rateCodes' => [
//              "usps_first_class_mail"     => '1DM', // regular enelopes - use a stamp...
                'usps_ground_advantage'     => 'GND', // package
                'usps_priority_mail'        => '2DP', // letter, large_envelope, package, usps_medium_flat_rate_box, usps_small_flat_rate_box, usps_large_flat_rate_box
                                                      // usps_flat_rate_envelope, usps_padded_flat_rate_envelope, usps_legal_flat_rate_envelope
                'usps_priority_mail_express'=> '1DP', // letter, large_envelope, package, usps_flat_rate_envelope, usps_padded_flat_rate_envelope, usps_legal_flat_rate_envelope
                'usps_parcel_select'        => '3DP', // package
//              'usps_media_mail'           => '4DP', // large_envelope, package,
//              "usps_first_class_mail_international" "usps_priority_mail_international" "usps_priority_mail_express_international" "usps_pay_on_use_return"
//              "globalpost_first_class_smartsaver" "globalpost_parcel_select_smartsaver" "globalpost_economy_international" "globalpost_economy_international_smartsaver" "globalpost_standard_international" "globalpost_standard_international_smartsaver" "globalpost_plus" "globalpost_plus_smartsaver" "globalpost_first_class_international" "globalpost_first_class_international_smartsaver" "globalpost_priority_mail_international" "globalpost_priority_mail__international_smartsaver" "globalpost_priority_mail_express_international" "globalpost_ priority_mail_express_international_smartsaver"
                // UPS, FedEx , DHL, and Canada Post
//              'ups_next_day_air_early'    => '1DM',
                'ups_next_day_air'          => '1DA',
//              'ups_next_day_air_saver'    => '1DP',
                'ups_2nd_day_air_am'        => '2DA',
                'ups_2nd_day_air'           => '2DM',
                'ups_3_day_select'          => '3DM',
                'ups_ground'                => 'GDR'],
//              'ups_standard'              => 'GDM',
//              "ups_worldwide_express" "ups_worldwide_express_plus" "ups_worldwide_expedited" "ups_worldwide_saver"
//              "fedex_first_overnight" "fedex_priority_overnight" "fedex_standard_overnight" "fedex_2day_am" "fedex_2day" "fedex_express_saver" "fedex_ground" "fedex_home_delivery"
//              "fedex_international_first" "fedex_international_priority" "fedex_international_economy" "fedex_international_ground"
//              "dhl_express_worldwide"
//              "canada_post_regular_parcel" "canada_post_xpresspost" "canada_post_priority" "canada_post_expedited_parcel" "canada_post_small_packet_air_usa" "canada_post_tracked_packet_usa" "canada_post_expedited_parcel_usa" "canada_post_xpresspost_usa"
//              "canada_post_priority_worldwide_usa" "canada_post_small_packet_international_surface" "canada_post_small_packet_international_air" "canada_post_international_parcel_surface" "canada_post_international_parcel_air" "canada_post_tracked_packet_international" "canada_post_xpresspost_international" "canada_post_priority_worldwide_international"
            'PackageMap' => [ // for rate estimates, assume this set of options
                'package'                       => $this->lang['MPS_01'],
                'large_envelope'                => $this->lang['MPS_02'],
                'usps_flat_rate_envelope'       => $this->lang['MPS_03'],
                'usps_padded_flat_rate_envelope'=> $this->lang['MPS_04'],
                'usps_legal_flat_rate_envelope' => $this->lang['MPS_05'],
                'usps_small_flat_rate_box'      => $this->lang['MPS_06'],
                'usps_medium_flat_rate_box'     => $this->lang['MPS_07'],
                'usps_large_flat_rate_box'      => $this->lang['MPS_08'],
                'usps_regional_rate_box_a'      => $this->lang['MPS_09'],
                'usps_regional_rate_box_b'      => $this->lang['MPS_10']],
//                "ups_letter" "ups_pak" "ups_tube" "ups_express_box_small" "ups_express_box_medium" "ups_express_box_large" "ups_10kg_box" "ups_25kg_box"
//                "fedex_envelope" "fedex_pak" "fedex_tube" "fedex_one_rate_envelope" "fedex_one_rate_pak" "fedex_one_rate_tube" "fedex_one_rate_small_box" "fedex_one_rate_medium_box" "fedex_one_rate_large_box" "fedex_one_rate_extra_large_box" "fedex_10kg_box" "fedex_25kg_box" "express_envelope"
//                "canada_post_envelope" "canada_post_pak"
            'buyPostageAmounts' => [
                '10' => $this->lang['0010_dollars'],
                '25' => $this->lang['0025_dollars'],
               '100' => $this->lang['0100_dollars'],
               '250' => $this->lang['0250_dollars'],
               '500' => $this->lang['0500_dollars'],
              '1000' => $this->lang['1000_dollars']],
            'paperTypes' => [
                ['id'=>'4x6',          'text'=>$this->lang['label_07']],
                ['id'=>'4x6.75-doctab','text'=>$this->lang['label_08']],
                ['id'=>'4x8.25-doctab','text'=>$this->lang['label_10']]]];
    }

    /**
     *
     * @param type $addr
     * @return type
     */
    protected function mapAddress($addr=[])
    {
        $output = [];
        if (!empty($addr['primary_name'])){ $output['company_name']  = $addr['primary_name']; }
//      if (!empty($addr['primary_name'])){ $output['name']          = $addr['primary_name']; }
        $keys= ['contact', 'address1', 'address2'];
        $idx = 1;
        foreach ($keys as $key) {
            if (!empty($addr[$key])) {
                $output['address_line'.$idx] = $addr[$key];
                $idx++;
            }
        }
        if (!empty($addr['city']))        { $output['city']          = $addr['city']; }
        if (!empty($addr['state']))       { $output['state_province']= $addr['state']; }
        if (!empty($addr['postal_code'])) { $output['postal_code']   = $addr['postal_code']; }
        if (!empty($addr['country']))     { $output['country_code']  = $addr['country']; } // they goofed the ISO2 Canada code
        if (!empty($addr['telephone1']))  { $output['phone']         = $addr['telephone1']; } // goofy phone number format
        if (!empty($addr['email']))       { $output['email']         = $addr['email']; }
        if (!empty($addr['residential'])) { $output['residential_indicator'] = !empty($addr['residential']) ? 'yes' : 'no'; }
        return $output;
    }
}
