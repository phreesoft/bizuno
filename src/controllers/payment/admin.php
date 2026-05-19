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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-04-26
 * @filesource /controllers/payment/admin.php
 */

namespace bizuno;

class paymentAdmin
{
    public  $moduleID  = 'payment';
    public  $pageID    = 'admin';
    private $defMethods= ['cod', 'directdebit', 'moneyorder',];
    public  $defaults;
    public  $settings;
    public  $structure;

    public function __construct()
    {
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
        $current = getModuleCache($this->moduleID, 'settings', 'general', 'wallet_provider', '');
        $data = ['general'=>['order'=>10,'label'=>lang('general'),'fields'=>[
            'gl_payment_c'   =>['attr'=>['type'=>'ledger','id'=>'general_gl_payment_c', 'value'=>$this->defaults['gl_payment_c']]],
            'gl_discount_c'  =>['attr'=>['type'=>'ledger','id'=>'general_gl_discount_c','value'=>$this->defaults['gl_discount_c']]],
            'gl_payment_v'   =>['attr'=>['type'=>'ledger','id'=>'general_gl_payment_v', 'value'=>$this->defaults['gl_payment_v']]],
            'gl_discount_v'  =>['attr'=>['type'=>'ledger','id'=>'general_gl_discount_v','value'=>$this->defaults['gl_discount_v']]],
            'prefix'         =>['attr'=>['value'=>'DP']],
            'wallet_provider'=>['values'=>$this->walletProviderOptions(),'attr'=>['type'=>'select','value'=>$current]]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    /**
     * Build the option list for the "wallet provider" select on the payment settings page.
     * Only active gateways (status=1 in methods_gateways meta) that expose a walletList()
     * method qualify — keeps the dropdown in sync with whatever the user has actually enabled.
     * The leading "(auto)" entry tells paymentWallet::resolveGateway() to pick the first match.
     */
    private function walletProviderOptions()
    {
        $opts = [['id'=>'', 'text'=>'(auto)']];
        $methods = getMetaMethod('gateways');
        if (empty($methods) || !is_array($methods)) { return $opts; }
        foreach ($methods as $key => $row) {
            if (empty($row['status']) || empty($row['path'])) { continue; }
            $code = !empty($row['id']) ? $row['id'] : $key;
            // bizAutoLoad resolves the BIZUNO_FS_LIBRARY/BIZUNO_DATA placeholder in $row['path'].
            if (!bizAutoLoad($row['path']."$code.php")) { continue; }
            $cls = "\\bizuno\\$code";
            if (!class_exists($cls) || !method_exists($cls, 'walletList')) { continue; }
            $opts[] = ['id'=>$code, 'text'=>!empty($row['title']) ? $row['title'] : $code];
        }
        return $opts;
    }

    /**
     * Sets the structure for the home page of payment user defined settings
     * @param array $layout - Home page for user settings for this module
     * @return modified $layout
     */
    public function adminHome(&$layout)
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure()));
        // add the nacha manager
        $layout['tabs']['tabAdmin']['divs']['tabACH'] = ['order'=>70,'label'=>lang('ach_accounts', $this->moduleID),'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/adminNacha/manager'"]];
    }

    /**
     * Saves the updated settings as requested by the user
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    public function install(&$layout=[])
    {
        msgDebug("\nEntering $this->moduleID:install");
        // Install the requried and basic list of gateways
        $bAdmin = new bizunoSettings();
        foreach ($this->structure['dirMethods'] as $dirMeth) { $bAdmin->adminInstMethods($this->moduleID, $dirMeth, $this->defMethods); }
    }


}
