<?php
/*
 * Bizuno Settings methods
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
 * @version    7.x Last Update: 2025-06-10
 * @filesource /controllers/administrate/main.php
 */

namespace bizuno;

class administrateMain extends mgrJournal
{
    public    $moduleID= 'administrate';
    public    $pageID  = 'main';
    protected $secID   = 'admin';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * Main Settings page, builds a list of all available modules and puts into groups
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        // @TODO - Need to add hamburger menu at top with menuBar
        
        $title = getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        if (empty($title)) { $title = portalGetBizIDVal(getUserCache('business', 'bizID'), 'title'); }
        $data  = ['title'=>lang('settings').'-'.$title,
            'west'  =>['header' =>['divs'=>['menu'=>['type'=>'menu', 'data'=>$this->viewMenu()]]]],
            'jsHead'=>['menu_id'=> "var menuID='settings';"]];
        $order = 10;
        $validMods = portalModuleList();
        foreach (array_keys($validMods) as $module) { // add the apps dynamically
            if (!empty(getModuleCache($module, 'properties', 'hasAdmin'))) {
                $data['west']['header']['divs']['menu']['data']['child']['apps']['child'][$module] = ['order'=>$order,'label'=>lang($module),'icon'=>$module,
                    'events'=>['onClick'=>"bizPanelReload('bizBody', '$module/admin/adminHome');"]];
                $order = $order + 5;
            }
        }
        viewDashJS($data);
        $struc = viewMain();
        unset($struc['west']['header']['divs']['menu']['data']);
        msgDebug("\nBefore merge, struc = ".print_r($struc, true));
        msgDebug("\nBefore merge, data = ".print_r($data, true));
        $layout = array_replace_recursive($layout, $struc, $data);
        msgDebug("\ndata AFTER array replace = ".print_r($layout, true));
    }

    /**
     * Builds the menu for the settings page
     * @return array
     */
    private function viewMenu()
    {
        $data = ['child'=>[
            'home'     => ['order'=>10,'label'=>lang('home'),             'icon'=>'settings', 'events'=>['onClick'=>"hrefClick('$this->moduleID/$this->pageID/manager');"]],
            'roster'   => ['order'=>20,'label'=>lang('directory'),        'icon'=>'address',  'child'=>[
                'users'     => ['order'=>10,'label'=>lang('users'),       'icon'=>'users',    'events'=>['onClick'=>"bizPanelReload('bizBody', 'contacts/main/manager&type=u&dom=div');"]],
                'roles'     => ['order'=>20,'label'=>lang('roles'),       'icon'=>'roles',    'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/roles/manager');"]],
                'mgr_e'     => ['order'=>30,'label'=>lang('employees'),   'icon'=>'badge',    'events'=>['onClick'=>"bizPanelReload('bizBody', 'contacts/main/manager&type=e&dom=div');"]],
                'factory'   => ['order'=>40,'label'=>lang('store'),       'icon'=>'factory',  'events'=>['onClick'=>"bizPanelReload('bizBody', 'contacts/main/manager&type=b&dom=div');"]],
                'fxdasts'   => ['order'=>50,'label'=>lang('fixed_assets'),'icon'=>'fxdasts',  'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/fixedAssets/manager');"]]]],
            'apps'     => ['order'=>30,'label'=>lang('apps'),             'icon'=>'apps',     'child'=>[]], // Apps are filled in real time
            'security' => ['order'=>40,'label'=>lang('security'),         'icon'=>'shield',   'child'=>[
                'dashboard' => ['order'=>10,'label'=>lang('dashboard'),   'icon'=>'grid',     'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/dashboard/manager');"]]]],
            'data'     => ['order'=>50,'label'=>lang('storage'),          'icon'=>'disk',     'child'=>[
                'backup'    => ['order'=>10,'label'=>lang('backup'),      'icon'=>'backup',   'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/backup/manager');"]]]],
//          'reporting'=> ['order'=>60,'label'=>lang('reporting'),        'icon'=>'report',   'events'=>['onClick'=>"bizPanelReload('bizBody', 'phreeform/main/manager');"]],
            'billing'  => ['order'=>70,'label'=>lang('billing'),          'icon'=>'checkbook','child' =>[
                'purchases' => ['order'=>10,'label'=>lang('purchases'),   'icon'=>'money',    'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/dashboard/manager');"]],
                'wallet'    => ['order'=>20,'label'=>lang('wallet'),      'icon'=>'wallet',   'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/dashboard/manager');"]]]],
            'account'  => ['order'=>80,'label'=>lang('account'),          'icon'=>'account',  'child'=>[
                'phreesoft' => ['order'=>10,'label'=>lang('my_phreesoft'),'icon'=>'phreesoft','events'=>['onClick'=>"hrefClick('https://www.phreesoft.com/my-account');"]]]]]];
        return $data;
    }
}
