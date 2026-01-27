<?php
/*
 * Shipping extension for free shipping shipments
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
 * @version    7.x Last Update: 2025-09-11
 * @filesource /controllers/shipping/carriers/freeshipper/freeshipper.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__)."/../../functions.php", 'viewCarrierServices');

class freeshipper {
    public $moduleID = 'shipping';
    public $methodDir= 'carriers';
    public $code     = 'freeshipper';
    public $required = true;
    public $settings;
    public $options;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    public $lang     = ['title'=>'Free Shipping!',
        'acronym'    => 'Free/Other', // 'other', // leave null as this really translates to 'other'
        'description'=> 'Free shipping with allowance for a flat rate charge.',
        'rate'       => 'The shipping cost for all orders using this shipping method.',
        'GND' => 'Local Delivery',
        'GDR' => 'Customer Pickup',
        '1DM' => 'Best Way',
        '1DA' => 'Sender Paid 2 Day',
        '1DP' => 'Sender Paid 1 Day',
        '2DP' => 'Courier',
        '3DP' => 'Sender Paid Economy'];

    public function __construct()
    {
        $this->lang = array_replace(getLang($this->moduleID), $this->lang);
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->getSettings();
    }

    private function getSettings()
    {
        $this->settings= ['rate'=>0,'order'=>50,'service_types'=>'GND:GDR:1DM:1DA:1DP:2DP:3DP','default'=>'0',
            'gl_acct_c'=> getModuleCache('shipping','settings','general','gl_shipping_c'),
            'gl_acct_v'=> getModuleCache('shipping','settings','general','gl_shipping_v')];
        $usrSettings   = getModuleCache($this->moduleID, $this->methodDir, $this->code, 'settings', []);
        settingsReplace($this->settings, $usrSettings, $this->settingsStructure());
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
    }

    public function settingsStructure()
    {
        $srv = ['GND','GDR','1DM','1DA','1DP','2DP','3DP'];
        $services = [];
        foreach ($srv as $value) { $services[] = ['id'=>$value,'text'=>$this->lang[$value]]; }
        return [
            'gl_acct_c'=> ['label'=>$this->lang['gl_shipping_c_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'=> ['label'=>$this->lang['gl_shipping_v_lbl'],'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'rate'     => ['label'=>$this->lang['rate'], 'position'=>'after','attr' =>['type'=>'float', 'size'=>10, 'value'=>$this->settings['rate']]],
            'order'    => ['label'=>lang('sort_order'), 'position'=>'after', 'attr'=>['type'=>'integer', 'size'=>3, 'value'=>$this->settings['order']]],
            'service_types'=> ['label'=>$this->lang['shipping_settings_default_service'], 'position'=>'after', 'values'=>$services,'attr'=>['type'=>'select', 'size'=>15, 'multiple'=>'multiple', 'format'=>'array', 'value'=>$this->settings['service_types']]],
            'default'  => ['label'=>$this->lang['shipping_settings_default_rate'],'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]]];
    }

    public function settingSave()
    {
        $meta   = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx= metaIdxClean($meta);
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nSetting settings:services to: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    public function rateQuote() {
        return [
            'GND' => [
                'title'  => $this->lang['GND'],
                'gl_acct'=> $this->settings['gl_acct'],
                'book'   => $this->settings['rate'],
                'cost'   => '',
                'quote'  => $this->settings['rate'],
                'note'   => '']];
    }
}
