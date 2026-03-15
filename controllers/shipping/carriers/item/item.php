<?php
/*
 * Shipping extension for item rated shipments
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
 * @version    7.x Last Update: 2026-03-20
 * @filesource /controllers/shipping/carriers/item/item.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__)."/../../functions.php", 'viewCarrierServices');

class item {
    public $moduleID = 'shipping';
    public $methodDir= 'carriers';
    public $code     = 'item';
    public $settings;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    public $lang     = ['title'=>'Per Package Shipping',
        'acronym'    => 'Item',
        'description'=> 'Per item shipping set a single flat rate shipping price for each package in an order.',
        'rate'       => 'The shipping cost per package to use for all orders using this shipping method.',
        'GND'        => 'Best Way'];

    public function __construct()
    {
        $this->getSettings();
    }

    private function getSettings()
    {
        $this->settings= ['rate'=>'0','order'=>50,'service_types'=>'GND','default'=>'0',
            'gl_acct_c'=> getModuleCache('shipping','settings','general','gl_shipping_c'),
            'gl_acct_v'=> getModuleCache('shipping','settings','general','gl_shipping_v')];
        settingsReplace($this->settings, getMetaMethod($this->methodDir, $this->code)['settings'], $this->settingsStructure());
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
    }

    public function settingsStructure()
    {
        $noYes    = [['id'=>'0',  'text'=>lang('no')], ['id'=>'1', 'text'=>lang('yes')]];
        $services = [['id'=>'GND','text'=>$this->lang['GND']]]; // only one type
        return [
            'gl_acct_c'=> ['label'=>lang('gl_shipping_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'=> ['label'=>lang('gl_shipping_v_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'rate'     => ['label'=>lang('rate'), 'position'=>'after','attr' =>['type'=>'float','size'=>'10','value'=>$this->settings['rate']]],
            'order'    => ['label'=>lang('sort_order'), 'position'=>'after', 'attr'=>['type'=>'integer', 'size'=> '3','value'=>$this->settings['order']]],
            'service_types'=> ['label'=>lang('shipping_settings_default_service', $this->moduleID), 'position'=>'after', 'values'=>$services,'attr'=>['type'=>'select','size'=>'15','multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'default'  => ['label'=>lang('shipping_settings_default_rate', $this->moduleID),'position'=>'after','values'=>$noYes,'attr'=>['type'=>'select','value'=>$this->settings['default']]]];
    }

    public function settingSave()
    {
        $meta   = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx= metaIdxClean($meta);
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nSetting settings:services to: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    public function rateQuote($pkg=false) {
        if (!$pkg) { $pkg = ['settings'=>  ['num_boxes'=>'1']]; }
        return ['GND' =>['title'=>$this->lang['GND'],'gl_acct'=> $this->settings['gl_acct'],
            'book'   => $pkg['settings']['num_boxes'] * $this->structure['rate']['attr']['value'],'cost'=>'',
            'quote'  => $pkg['settings']['num_boxes'] * $this->structure['rate']['attr']['value'],'note'=>'']];
    }
}
