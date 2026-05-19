<?php
/*
 * Shipping extension for percent rated shipments
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
 * @filesource /controllers/shipping/carriers/percent/percent.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__)."/../../functions.php", 'viewCarrierServices');

class percent
{
    public $moduleID = 'shipping';
    public $methodDir= 'carriers';
    public $code     = 'percent';
    public $required = true;
    public $settings;
    public $weightUOM;
    public $dimUOM;
    public $ship_pkg;
    public $ship_pickup;
    public $ship_cod_type;
    public $confirm_type;
    public $lang     = ['title'=>'Percent Rate',
        'acronym'    => 'Percent',
        'description'=> 'Percent Rate shipping sets a single shipping price for an entire order based on the order total cost.',
        'percent'    => 'The percentage to multiply the order total by',
        'min_rate'   => 'The minimum rate to charge for shipping',
        'GND'        => 'Best Way'];

    public function __construct()
    {
        $this->getSettings();
    }

    private function getSettings()
    {
        $this->settings= ['percent'=>'7.5','minRate'=>'0','order'=>50,'service_types'=>'GND','default'=>'0',
            'gl_acct_c'=> getModuleCache('shipping','settings','general','gl_shipping_c'),
            'gl_acct_v'=> getModuleCache('shipping','settings','general','gl_shipping_v')];
        settingsReplace($this->settings, getMetaMethod($this->methodDir, $this->code)['settings'] ?? [], $this->settingsStructure());
        $this->settings['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
    }

    public function settingsStructure()
    {
        $noYes   = [['id'=>'0',  'text'=>lang('no')], ['id'=>'1', 'text'=>lang('yes')]];
        $services= [['id'=>'GND','text'=>$this->lang['GND']]];
        return [
            'gl_acct_c'=> ['label'=>lang('gl_shipping_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'=> ['label'=>lang('gl_shipping_v_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'percent'  => ['label'=>lang('percent', $this->moduleID), 'position'=>'after','attr'=>['type'=>'float', 'size'=>'10', 'value'=>$this->settings['percent']]],
            'minRate'  => ['label'=>lang('min_rate', $this->moduleID),'position'=>'after','attr'=>['type'=>'float', 'size'=>'10', 'value'=>'0']],
            'order'    => ['label'=>lang('sort_order'), 'position'=>'after', 'attr'=>['type'=>'integer', 'size'=>'3', 'value'=>$this->settings['order']]],
            'service_types'=> ['label'=>lang('shipping_settings_default_service', $this->moduleID), 'position'=>'after', 'values'=>$services,
                 'attr'=>['type'=>'select', 'size'=>'15', 'multiple'=>'multiple', 'format'=>'array', 'value'=>$this->settings['service_types']]],
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

    public function rateQuote()
    {
        $total= clean('total_amount', 'currency', 'post');
        $rate = floatval($this->settings['percent']) / 100;
        return ['GND'=>[
            'title'  => $this->lang['GND'],
            'gl_acct'=> $this->settings['gl_acct'],
            'quote'  => max($total * $rate, $this->settings['minRate'])]];
    }
}
