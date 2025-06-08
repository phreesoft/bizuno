<?php
/*
 * Shipping extension for Endicia - Manager
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/shipping/carriers/endicia/endicia.php
 *
 * Docs: https://www.endicia.com/developer/docs
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'endiciaCommon');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/shipping/functions.php', 'viewCarrierServices', 'function');

class endicia extends endiciaCommon
{
    public $moduleID  = 'shipping';
    public $methodDir = 'carriers';
    public $code      = 'endicia';

    function __construct()
    {
        parent::__construct();
        $tabImage = BIZUNO_SCRIPTS."view/images/shipping/carriers/$this->code/tab_logo.png";
        $this->lang['tabTitle']= "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
    }

// ***************************************************************************************************************
//                                Endicia Homepage Tab HTML Form
// ***************************************************************************************************************
    public function manager(&$layout=[])
    {
        $buyAmounts = [];
        foreach ($this->options['buyPostageAmounts'] as $amt => $text) { $buyAmounts[]= ['id'=>$amt, 'text'=>$text]; }
        $data = ['type'=>'divHTML',
            'divs'   => [
                'pnlBuy' => ['order'=>10,'type'=>'panel','key'=>'pnlBuy', 'classes'=>['block33']]],
            'panels' => [
                'pnlBuy' => ['title'=>$this->lang['postage_buy_title'],'type'=>'divs','divs'=>[
                    'desc' => ['order'=>20,'type'=>'html',  'html'=>"<p>{$this->lang['postage_buy_desc']}</p>"],
                    'body' => ['order'=>30,'type'=>'fields','keys'=>['selEndiciaBuy','btnEndiciaBuy']]]]],
            'fields' => [
                'selEndiciaBuy' => ['order'=>20,'values'=>$buyAmounts,'attr'=>['type'=>'select','value'=>$this->settings['funds_purch']]],
                'btnEndiciaBuy' => ['order'=>30,'events'=>['onClick'=>"jsonAction('shipping/admin/fundsBuy')"], 'attr'=>['type'=>'button', 'value'=>lang('go')]]]];
        $layout = array_replace_recursive($layout, $data);
    }

    public function settingsStructure()
    {
        $services = $packages = $buyAmounts = [];
        foreach ($this->options['rateCodes']  as $code)               { $services[]  = ['id'=>$code,'text'=>$this->lang[$code]]; }
        foreach ($this->options['PackageMap'] as $key => $style)      { $packages[]  = ['id'=>$key, 'text'=>$style]; }
        foreach ($this->options['buyPostageAmounts'] as $amt => $text){ $buyAmounts[]= ['id'=>$amt, 'text'=>$text]; }
        return [
            'client_key'   => ['label'=>$this->lang['client_key'],       'position'=>'after','attr'=>['size'=>80,'value'=>$this->settings['client_key']]],
            'order'        => ['label'=>lang('sort_order'),              'position'=>'after','attr'=>['type'=>'integer','size'=>3,'value'=>$this->settings['order']]],
            'lbl_msg_1'    => ['label'=>$this->lang['lbl_msg_1'],        'position'=>'after','attr'=>['size'=>80,'value'=>$this->settings['lbl_msg_1']]],
            'lbl_msg_2'    => ['label'=>$this->lang['lbl_msg_2'],        'position'=>'after','attr'=>['size'=>80,'value'=>$this->settings['lbl_msg_2']]],
            'lbl_msg_3'    => ['label'=>$this->lang['lbl_msg_3'],        'position'=>'after','attr'=>['size'=>80,'value'=>$this->settings['lbl_msg_3']]],
            'handling_fee' => ['label'=>$this->lang['handling_fee'],     'position'=>'after','attr'=>['type'=>'float','size'=>6,'value'=>$this->settings['handling_fee']]],
            'gl_acct'      => ['label'=>$this->lang['gl_shipping_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct",'value'=>$this->settings['gl_acct']]],
            'service_types'=> ['label'=>$this->lang['shipping_settings_default_service'],'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'package_types'=> ['label'=>$this->lang['package_types'],    'position'=>'after','values'=>$packages,'attr'=>['type'=>'select','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['package_types']]],
            'funds_min'    => ['label'=>$this->lang['funds_min'],        'position'=>'after','attr'=>['type'=>'currency','value'=>$this->settings['funds_min']]],
            'funds_purch'  => ['label'=>$this->lang['funds_purch'],      'position'=>'after','values'=>$buyAmounts,'attr'=>['type'=>'select','value'=>$this->settings['funds_purch']]],
            'label_thermal'=> ['label'=>$this->lang['label_thermal'],    'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_thermal']]],
            'default'      => ['label'=>$this->lang['shipping_settings_default_rate'],'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]]];
    }

    /**
     * Saves the settings for this method
     * WARNING: This method resets the settings to ONLY what is in the structure to remove obsoleted settings, hidden and credentials will be lost and need to be reloaded.
     */
    public function settingSave()
    {
        $meta     = dbMetaGet(0, "methods_{$this->methodDir}");
        msgDebug("\nRead meta for this method = ".print_r($meta, true));
        $metaIdx  = metaIdxClean($meta);
        // extract the services
        $services = clean("{$this->code}_service_types", 'array', 'post');
        $meta[$this->code]['settings']['service_types'] = implode(':', $services);
        $meta[$this->code]['settings']['services']  = viewCarrierServices($this->code, $meta[$this->code]['settings']['service_types'], $this->lang);
        msgDebug("\nReady to write updated meta to index $metaIdx = ".print_r($meta, true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    /**
     * Generates the keys for generating labels for this carrier
     * @return array - keys for the specified panels
     */
    public function labelKeys()
    {
        return [
            'address_d'=> ['primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'],
            'options'  => ['carrier','method_code','pkg_array','frt_billed','ship_method','ship_ref_1','ship_ref_2','ship_cod','ship_cod_val','ship_cod_cur'],
            'details'  => ['store_id_b','store_id_p','ship_date','ship_pkg','weightUOM','dimUOM','currencyUOM']];
    }

    /**
     *
     * @param type $data
     * @param type $refresh
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
            $data['fields']['myPkgs'] = ['order'=>30,'label'=>$this->lang['my_packages'],'values'=>$packages,'attr'=>['type'=>'select']];
        } else {
            $data['fields']['myPkgs'] = ['order'=>30,'attr'=>['type'=>'hidden']];
        }
        if ($refresh) { // just refreshing the panel, different structure
            $data['divs']['pnlPkg']['type']  = 'fields';
            $data['divs']['pnlPkg']['keys']  = ['weight','weightOz','txtOz','myPkgs','length','width','height','txtHt','pkgValue'];
            unset($data['divs']['pnlPkg']['key']);
        } else { // on div load
            $data['panels']['pnlPkg']['type']= 'fields';
            $data['panels']['pnlPkg']['keys']= ['weight','weightOz','txtOz','myPkgs','length','width','height','txtHt','pkgValue'];
            unset($data['panels']['pnlPkg']['key']);
        }
    }

    public function getClientToken()
    {
        msgDebug("\nReached getClientToken in the Endicia carrier");
        if ($this->getStampsToken()) {
            msgAdd("The token was retrieved from the PhreeSoft Server. Please close the popup windows and retry the request.");
        }
    }

    public function validateAddress($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/address.php', 'endiciaAddress');
        $api = new endiciaAddress($this->settings, $this->options, $this->lang);
        return $api->validateAddress($request);
    }

    public function rateQuote($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/rate.php', 'endiciaRate');
        $api = new endiciaRate($this->settings, $this->options, $this->lang);
        return $api->rateQuote($request);
    }

    public function labelGet($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'endiciaShip');
        $api = new endiciaShip($this->settings, $this->options, $this->lang);
        return $api->labelGet($request);
    }

    public function labelDelete($tracking_number='', $method='GND', $store_id=0) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'endiciaShip');
        $api = new endiciaShip($this->settings, $this->options, $this->lang);
        return $api->labelDelete($tracking_number, $method, $store_id);
    }

    public function trackBulk($track_date, $log_id) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'endiciaTracking');
        $api = new endiciaTracking($this->settings, $this->options, $this->lang);
        return $api->trackBulk($track_date, $log_id);
    }

}
