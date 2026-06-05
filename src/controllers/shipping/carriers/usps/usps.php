<?php
/*
 * Shipping extension for USPS RESTful APIs - Manager
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
 * @version    7.x Last Update: 2026-06-05
 * @filesource /controllers/shipping/carriers/usps/usps.php
 *
 * Docs: https://developer.usps.com/  (specs in /Documents/USPS RESTFul API)
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'uspsCommon');
bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/shipping/functions.php', 'viewCarrierServices', 'function');

class usps extends uspsCommon
{
    public $moduleID  = 'shipping';
    public $methodDir = 'carriers';
    public $code      = 'usps';

    function __construct()
    {
        parent::__construct();
        $tabImage = BIZUNO_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/tab_logo.png";
        $this->lang['tabTitle'] = "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
    }

    /**
     * USPS doesn't have a "buy postage" workflow — EPS funds the labels at
     * print time. Kept the manager() method present (the shipping module's
     * tabbed UI calls it) but render a brief status panel rather than the
     * Endicia-style buy-postage form.
     */
    public function manager(&$layout=[])
    {
        $modeText = !empty($this->settings['test_mode']) ? '<b>Test (sandbox)</b>' : '<b>Production</b>';
        $info = "<p>USPS labels are paid from your Enterprise Payment Account (EPS) at print time.";
        $info.= " There is no separate &quot;buy postage&quot; step — top up your EPS balance at <a href='https://www.usps.com/business/' target='_blank'>usps.com</a>.</p>";
        $info.= "<p>Current mode: $modeText. EPS Account: <code>".htmlspecialchars($this->settings['payment_account'] ?: '(not set)')."</code></p>";
        $data = ['type'=>'divHTML',
            'divs'  => ['pnlInfo' => ['order'=>10, 'type'=>'panel', 'key'=>'pnlInfo', 'classes'=>['block50']]],
            'panels'=> ['pnlInfo' => ['title'=>'USPS Status', 'type'=>'html', 'html'=>$info]]];
        $layout = array_replace_recursive($layout, $data);
    }

    public function settingsStructure()
    {
        $services = $packages = [];
        foreach ($this->options['rateCodes']  as $key => $code)  { $services[]  = ['id'=>$code, 'text'=>$this->lang[$code]]; }
        foreach ($this->options['PackageMap'] as $key => $style) { $packages[]  = ['id'=>$key,  'text'=>$style]; }
        return [
            'client_id'      => ['label'=>$this->lang['client_id_lbl'],       'position'=>'after','attr'=>['size'=>60,'value'=>$this->settings['client_id']]],
            'client_secret'  => ['label'=>$this->lang['client_secret_lbl'],   'position'=>'after','attr'=>['type'=>'password','size'=>60,'value'=>$this->settings['client_secret']]],
            'payment_account'=> ['label'=>$this->lang['payment_account_lbl'], 'position'=>'after','attr'=>['size'=>20,'value'=>$this->settings['payment_account']]],
            'crid'           => ['label'=>$this->lang['crid_lbl'],            'position'=>'after','attr'=>['size'=>15,'value'=>$this->settings['crid']]],
            'mid'            => ['label'=>$this->lang['mid_lbl'],             'position'=>'after','attr'=>['size'=>15,'value'=>$this->settings['mid']]],
            'test_mode'      => ['label'=>$this->lang['test_mode_lbl'],       'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['test_mode']]],
            'price_type'     => ['label'=>$this->lang['price_type_lbl'],      'position'=>'after','values'=>[['id'=>'RETAIL','text'=>'Retail'],['id'=>'COMMERCIAL','text'=>'Commercial'],['id'=>'CONTRACT','text'=>'Contract']],'attr'=>['type'=>'select','value'=>$this->settings['price_type']]],
            'order'          => ['label'=>lang('sort_order'),                 'position'=>'after','attr'=>['type'=>'integer','size'=>3,'value'=>$this->settings['order']]],
            'lbl_msg_1'      => ['label'=>$this->lang['lbl_msg_1'],           'position'=>'after','attr'=>['size'=>60,'value'=>$this->settings['lbl_msg_1']]],
            'lbl_msg_2'      => ['label'=>$this->lang['lbl_msg_2'],           'position'=>'after','attr'=>['size'=>60,'value'=>$this->settings['lbl_msg_2']]],
            'lbl_msg_3'      => ['label'=>$this->lang['lbl_msg_3'],           'position'=>'after','attr'=>['size'=>60,'value'=>$this->settings['lbl_msg_3']]],
            'handling_fee'   => ['label'=>$this->lang['handling_fee'],        'position'=>'after','attr'=>['type'=>'float','size'=>6,'value'=>$this->settings['handling_fee']]],
            'gl_acct'        => ['label'=>lang('gl_shipping_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct",'value'=>$this->settings['gl_acct']]],
            'service_types'  => ['label'=>lang('shipping_settings_default_service', $this->moduleID),'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>10,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'package_types'  => ['label'=>$this->lang['package_types'],       'position'=>'after','values'=>$packages,'attr'=>['type'=>'select','size'=>10,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['package_types']]],
            'funds_min'      => ['label'=>$this->lang['funds_min'],           'position'=>'after','attr'=>['type'=>'currency','value'=>$this->settings['funds_min']]],
            'label_thermal'  => ['label'=>$this->lang['label_thermal'],       'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_thermal']]],
            'default'        => ['label'=>lang('shipping_settings_default_rate', $this->moduleID),'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]]];
    }

    /**
     * Saves the settings for this method.
     * NOTE: like Endicia, settingSave() resets to ONLY what is in the structure;
     * any out-of-band keys (cached OAuth token blob) will be wiped. This is
     * acceptable: the next API call simply re-mints the token.
     */
    public function settingSave()
    {
        $meta   = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx= metaIdxClean($meta);
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nUSPS settingSave services: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    /**
     * Keys consumed by the label form. Identical to Endicia's set so the
     * shipping manager's form builder doesn't need to branch on carrier.
     */
    public function labelKeys()
    {
        return [
            'address_d'=> ['primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'],
            'options'  => ['carrier','method_code','pkg_array','frt_billed','ship_method','ship_ref_1','ship_ref_2'],
            'details'  => ['store_id_b','store_id_p','ship_date','ship_pkg','weightUOM','dimUOM','currencyUOM']];
    }

    /**
     * Package fields in the ship-form panel. USPS measures weight in pounds
     * (with ounces below a pound rolled into a fractional pound) — same as
     * Endicia's packageRules — and dimensions in inches.
     */
    public function pkgPanel(&$data=[], $pkgs=[], $refresh=false)
    {
        $myPkgs = getModuleCache($this->moduleID, 'myPackages');
        $data['fields'] = array_merge($data['fields'], [
            'weight'  => ['order'=>20,'label'=>'Weight','break'=>false,'attr'=>['type'=>'integer','value'=>!empty($pkgs['Wt']) ? $pkgs['Wt'] : 0.1]],
            'weightOz'=> ['order'=>21,'label'=>'Pounds','break'=>false,'attr'=>['type'=>'integer']],
            'txtOz'   => ['order'=>22,'html' =>'<b>Ounces</b><br />',  'attr'=>['type'=>'raw']],
            'length'  => ['order'=>40,'label'=>'OR Manual Dims','break'=>false,'attr'=>['type'=>'integer','value'=>!empty($pkgs['L']) ? $pkgs['L'] : 8]],
            'width'   => ['order'=>41,'label'=>lang('length'),  'break'=>false,'attr'=>['type'=>'integer','value'=>!empty($pkgs['W']) ? $pkgs['W'] : 6]],
            'height'  => ['order'=>42,'label'=>lang('width'),   'break'=>false,'attr'=>['type'=>'integer','value'=>!empty($pkgs['H']) ? $pkgs['H'] : 4]],
            'txtHt'   => ['order'=>43,'html'=>'<b>Height</b>',                 'attr'=>['type'=>'raw']],
            'pkgValue'=> ['order'=>60,'label'=>lang('value'),'attr'=>['type'=>'float']]]);
        if (!empty($myPkgs)) {
            $packages[] = ['id'=>'', 'text'=>lang('select')];
            foreach ($myPkgs as $pkg) {
                $key  = $pkg['length']. ':' .$pkg['width']. ':' .$pkg['height'];
                $value= $pkg['length'].' x '.$pkg['width'].' x '.$pkg['height'];
                $packages[] = ['id'=>$key, 'text'=>$value];
            }
            $data['fields']['myPkgs'] = ['order'=>30,'label'=>'My Packages','values'=>$packages,'attr'=>['type'=>'select']];
        } else {
            $data['fields']['myPkgs'] = ['order'=>30,'attr'=>['type'=>'hidden']];
        }
        if ($refresh) {
            $data['divs']['pnlPkg']['type']  = 'fields';
            $data['divs']['pnlPkg']['keys']  = ['weight','weightOz','txtOz','myPkgs','length','width','height','txtHt','pkgValue'];
            unset($data['divs']['pnlPkg']['key']);
        } else {
            $data['panels']['pnlPkg']['type']= 'fields';
            $data['panels']['pnlPkg']['keys']= ['weight','weightOz','txtOz','myPkgs','length','width','height','txtHt','pkgValue'];
            unset($data['panels']['pnlPkg']['key']);
        }
    }

    public function validateAddress($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/address.php', 'uspsAddress');
        $api = new uspsAddress($this->settings, $this->options, $this->lang);
        return $api->validateAddress($request);
    }

    public function rateQuote($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/rate.php', 'uspsRate');
        $api = new uspsRate($this->settings, $this->options, $this->lang);
        return $api->rateQuote($request);
    }

    public function labelGet($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'uspsShip');
        $api = new uspsShip($this->settings, $this->options, $this->lang);
        return $api->labelGet($request);
    }

    /**
     * Void a label / request a refund via the Labels API (DELETE label by
     * tracking number). Delegates to uspsShip like labelGet().
     */
    public function labelDelete($tracking_number='', $method='GND', $store_id=0) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'uspsShip');
        $api = new uspsShip($this->settings, $this->options, $this->lang);
        return $api->labelDelete($tracking_number, $method, $store_id);
    }

    public function trackBulk($track_date, $log_id) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'uspsShip');
        $api = new uspsShip($this->settings, $this->options, $this->lang);
        return $api->trackBulk($track_date, $log_id);
    }
}
