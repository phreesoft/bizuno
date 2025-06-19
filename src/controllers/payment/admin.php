<?php
/*
 * module Payment - Installation, initialization, and settings
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
 * @version    7.x Last Update: 2025-05-21
 * @filesource /controllers/payment/admin.php
 */

namespace bizuno;

class paymentAdmin
{
    public $moduleID= 'payment';
    public $pageID  = 'admin';

    public function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->defaults = [
            'gl_payment_c'  => getChartDefault(0),
            'gl_discount_c' => getChartDefault(0),
            'gl_payment_v'  => getChartDefault(0),
            'gl_discount_v' => getChartDefault(0)];
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure= [
            'dirMethods'=> ['gateways']];
    }

    /**
     * Defines the structure of user configurable setting values
     * @return array - structure of user configurable settings
     */
    public function settingsStructure()
    {
        $data = ['general'=>['order'=>10,'label'=>lang('general'),'fields'=>[
            'gl_payment_c' =>['attr'=>['type'=>'ledger','id'=>'general_gl_payment_c', 'value'=>$this->defaults['gl_payment_c']]],
            'gl_discount_c'=>['attr'=>['type'=>'ledger','id'=>'general_gl_discount_c','value'=>$this->defaults['gl_discount_c']]],
            'gl_payment_v' =>['attr'=>['type'=>'ledger','id'=>'general_gl_payment_v', 'value'=>$this->defaults['gl_payment_v']]],
            'gl_discount_v'=>['attr'=>['type'=>'ledger','id'=>'general_gl_discount_v','value'=>$this->defaults['gl_discount_v']]],
            'prefix'       =>['attr'=>['value'=>'DP']]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    /**
     * Sets the structure for the home page of payment user defined settings
     * @param array $layout - Home page for user settings for this module
     * @return modified $layout
     */
    public function adminHome(&$layout)
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang));
        // add the nacha manager
        $layout['tabs']['tabAdmin']['divs']['tabACH'] = ['order'=>70,'label'=>$this->lang['ach_accounts'],'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/adminNacha/manager'"]];
    }

    /**
     * Saves the updated settings as requested by the user
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}
