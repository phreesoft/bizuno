<?php
/*
 * Shipping extension for United Parcel Service - Manager
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
 * @version    7.x Last Update: 2025-06-12
 * @filesource /controllers/shipping/carriers/ups/manager.php
 *
 * UPS Developer Site:
 */

namespace bizuno;

define('UPS_TRACKING_URL', 'http://wwwapps.ups.com/WebTracking/track?track=yes&trackNums=TRACKINGNUM'); // &trackNums=1ZXXXXXXXXXXXXXXXX

bizAutoLoad(dirname(__FILE__).'/common.php', 'upsCommon');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/shipping/functions.php', 'viewCarrierServices', 'function');

class ups extends upsCommon
{
    public  $moduleID  = 'shipping';
    public  $methodDir = 'carriers';
    public  $code      = 'ups';
    private $frtCollect= []; // used to aggregate Freight Collect by Invoice number

    function __construct()
    {
        parent::__construct();
        $tabImage = BIZUNO_SCRIPTS."view/images/shipping/carriers/$this->code/tab_logo.png";
        $this->lang['tabTitle']= "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
        $this->reconcile_path  = 'data/shipping/reconcile/ups/';
    }

    public function settingsStructure()
    {
        $servers  = [['id'=>'test','text'=>lang('test')],['id'=>'prod','text'=>lang('production')]];
        $printers = [['id'=>'pdf', 'text'=>$this->lang['plain_paper']], ['id'=>'thermal', 'text'=>$this->lang['thermal']]];
        $services = [];
        foreach ($this->options['rateCodes'] as $code) { $services[] = ['id'=>$code, 'text'=>$this->lang[$code]]; }
        return [
            'test_mode'    => ['label'=>$this->lang['test_mode'],    'position'=>'after','values'=>$servers,'attr'=>['type'=>'select','value'=>$this->settings['test_mode']]],
            'default'      => ['label'=>$this->lang['shipping_settings_default_rate'],'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]],
            'order'        => ['label'=>lang('sort_order'),          'position'=>'after','attr'=>['type'=>'integer','size'=>3,'value'=>$this->settings['order']]],
            'service_types'=> ['label'=>$this->lang['shipping_settings_default_service'],'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'acct_number'  => ['label'=>$this->lang['acct_number'],  'position'=>'after','attr'=>['value'=>$this->settings['acct_number']]],
            'rest_api_key' => ['label'=>$this->lang['rest_api_key_lbl'],'position'=>'after','attr'=>['size'=>48,'value'=>$this->settings['rest_api_key']]],
            'rest_secret'  => ['label'=>$this->lang['rest_secret_lbl'], 'position'=>'after','attr'=>['size'=>48,'value'=>$this->settings['rest_secret']]],
            'ltl_class'    => ['label'=>$this->lang['def_ltl_class'],'position'=>'after','values'=>viewKeyDropdown($this->options['LTLClasses']),'attr'=>['type'=>'select','value'=>$this->settings['ltl_class']]],
            'ltl_desc'     => ['label'=>$this->lang['def_ltl_desc'], 'position'=>'after','attr'=>['value'=>$this->settings['ltl_desc']]],
            'gl_acct_c'    => ['label'=>$this->lang['gl_shipping_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'    => ['label'=>$this->lang['gl_shipping_v_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'max_weight'   => ['label'=>$this->lang['max_weight'],   'position'=>'after','attr'=>['type'=>'integer','size'=>3,'maxlength'=>3,'value'=>$this->settings['max_weight']]],
            'recon_fee'    => ['label'=>$this->lang['recon_fee'],    'position'=>'after','styles'=>['text-align'=>'right'],'attr'=>['type'=>'float', 'size'=>10,'value'=>$this->settings['recon_fee']]],
            'recon_percent'=> ['label'=>$this->lang['recon_percent'],'position'=>'after','styles'=>['text-align'=>'right'],'attr'=>['type'=>'float', 'size'=> 5,'value'=>$this->settings['recon_percent']]],
            'printer_type' => ['label'=>$this->lang['printer_type'], 'position'=>'after','values'=>$printers,'attr'=>['type'=>'select','value'=>$this->settings['printer_type']]],
            'printer_name' => ['label'=>$this->lang['printer_name'], 'position'=>'after','attr'=>['value'=>$this->settings['printer_name']]],
            'label_pdf'    => ['label'=>$this->lang['label_pdf'],    'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_pdf']]],
            'label_thermal'=> ['label'=>$this->lang['label_thermal'],'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_thermal']]]];
    }

    public function settingSave()
    {
        $meta   = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx= metaIdxClean($meta);
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nSetting settings:services to: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    public function manager(&$layout=[])
    {
        $data = ['type'=>'divHTML',
            'divs'   => [
                'track' => ['order'=>20,'type'=>'panel','key'=>'pnlTrack','classes'=>['block25']]],
            'panels' => [
                'pnlTrack' => ['label'=>$this->lang['track_shipments_title'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',    'key' =>'frmUPSTrack'],
                    'desc'   => ['order'=>20,'type'=>'html',    'html'=>"<p>{$this->lang['track_shipments_desc']}</p>"],
                    'body'   => ['order'=>30,'type'=>'fields',  'keys'=>['frmUPSTrack','dateUPSTrack','btnUPSTrack']],
                    'formEOF'=> ['order'=>90,'type'=>'html',    'html'=>"</form>"]]]],
            'forms'  => [
                'frmUPSTrack' => ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=shipping/track/trackBulk&carrier=$this->code"]]],
            'fields' => [
                'dateUPSTrack'=> ['attr'=>['type'=>'date','value'=>localeCalculateDate(biz_date('Y-m-d'), -7)]],
                'btnUPSTrack' => ['icon'=>'next','events'=>['onClick'=>"jqBiz('#frmUPSTrack').submit();"]],
                'btnUPSShip'  => ['icon'=>'next','events'=>['onClick'=>"windowEdit('shipping/ship/labelMain&rID=0&data=$this->code', 'winLabel', '".$this->lang['title']."', 800, 700);"]]],
            'jsReady'=> ["init{$this->code}"=>"ajaxDownload('frmUPSTrack');"]]; // ajaxForm('frmUPSRecon');
        $layout = array_replace_recursive($layout, $data);
    }

    public function validateAddress($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/address.php', 'upsAddress');
        $api = new upsAddress($this->settings, $this->options, $this->lang);
        return $api->validateAddress($request);
    }

    public function rateQuote($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/rate.php', 'upsRate');
        $api = new upsRate($this->settings, $this->options, $this->lang);
        return $api->rateQuote($request);
    }

    public function labelGet($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'upsShip');
        $api = new upsShip($this->settings, $this->options, $this->lang);
        return $api->labelGet($request);
    }

    public function labelDelete($tracking_number='', $method='GND', $store_id=0) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'upsShip');
        $api = new upsShip($this->settings, $this->options, $this->lang);
        return $api->labelDelete($tracking_number, $method, $store_id);
    }
}
