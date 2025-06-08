<?php
/*
 * Shipping extension for percent rated shipments - XPO Logistics
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
 * @filesource /controllers/shipping/carriers/xpo/xpo.php
 *
 * Docs website: http://www.xpo.com/content/xml
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'xpoCommon');
bizAutoLoad(BIZBOOKS_ROOT.'controllers/shipping/functions.php', 'viewCarrierServices', 'function');

class xpo extends xpoCommon
{
    function __construct()
    {
        parent::__construct();
        $tabImage = BIZUNO_SCRIPTS."view/images/shipping/carriers/$this->code/tab_logo.png";
        $this->lang['tabTitle']= "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
    }

    public function settingsStructure()
    {
        $servers  = [['id'=>'Y','text'=>lang('test')],['id'=>'N','text'=>lang('production')]];
        $services = [];
        foreach ($this->options['rateCodes'] as $code) { $services[] = ['id'=>$code, 'text'=>$this->lang[$code]]; }
        return [
            'gl_acct_c'    => ['label'=>$this->lang['gl_shipping_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'    => ['label'=>$this->lang['gl_shipping_v_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'order'        => ['label'=>lang('sort_order'),'position'=>'after','attr'=>['type'=>'integer','size'=>3,'value'=>$this->settings['order']]],
            'test_mode'    => ['label'=>$this->lang['test_mode'],    'position'=>'after','values'=>$servers,'attr'=>['type'=>'select','value'=>$this->settings['test_mode']]],
            'username'     => ['label'=>$this->lang['username'],     'position'=>'after','attr'=>['value'=>$this->settings['username']]],
            'password'     => ['label'=>$this->lang['password'],     'position'=>'after','attr'=>['value'=>$this->settings['password']]],
            'acct_id'      => ['label'=>$this->lang['acct_id'],      'position'=>'after','attr'=>['type'=>'integer', 'size'=>10, 'maxlength'=>9,'value'=>$this->settings['acct_id']]],
            'authorization'=> ['label'=>$this->lang['authorization'],'position'=>'after','attr'=>['value'=>$this->settings['authorization']]],
            'min_weight'   => ['label'=>$this->lang['min_weight'],   'position'=>'after','attr'=>['type'=>'integer','size'=>4,'maxlength'=>3,'value'=>$this->settings['min_weight']]],
            'ltl_class'    => ['label'=>$this->lang['def_ltl_class'],'position'=>'after','values'=>viewKeyDropdown($this->LTLClasses),'attr'=>['type'=>'select','value'=>$this->settings['ltl_class']]],
            'ltl_desc'     => ['label'=>$this->lang['def_ltl_desc'], 'position'=>'after','attr'=>['value'=>$this->settings['ltl_desc']]],
            'service_types'=> ['label'=>$this->lang['shipping_settings_default_service'],'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'default'      => ['label'=>$this->lang['shipping_settings_default_rate'],'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]]];
    }

    public function settingSave()
    {
        $methProps = getModuleCache($this->moduleID, $this->methodDir, $this->code);
        settingsSaveMethod($this->settings, $this->settingsStructure(), $this->code.'_');
        $this->settings['services']  = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        $methProps['settings'] = $this->settings;
        setModuleCache($this->moduleID, $this->methodDir, $this->code, $methProps);
    }

    public function rateQuote($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/rate.php', 'xpoRate');
        $api = new xpoRate($this->settings, $this->options, $this->lang);
        return $api->rateQuote($request);
    }

    public function labelGet($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'xpoShip');
        $api = new xpoShip($this->settings, $this->options, $this->lang);
        return $api->labelGet($request);
    }

    public function labelDelete($tracking_number='', $method='GND', $store_id=0) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'xpoShip');
        $api = new xpoShip($this->settings, $this->options, $this->lang);
        return $api->labelDelete($tracking_number, $method, $store_id);
    }
}
