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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-07-24
 * @filesource /controllers/administrate/main.php
 */

namespace bizuno;

class administrateMain extends mgrJournal
{
    public    $moduleID= 'administrate';
    public    $pageID  = 'main';
    protected $secID   = 'admin';
    private   $psURL= 'https://www.phreesoft.com/my-account';


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
            'west'  =>['header' =>['divs'=>['menu'=>['type'=>'sidemenu', 'options'=>['dom'=>'div', 'noDash'=>true, 'noMin'=>true], 'data'=>$this->viewMenu()]]]],
            'jsHead'=>['menu_id'=> "var menuID='settings';"]];
        msgDebug("\nLooking for more admin at ".BIZUNO_DATA.'myExt/controllers/myAdmin/main.php');
        bizAutoLoad(BIZUNO_DATA.'myExt/controllers/myAdmin/admin.php', 'myAdminAdmin');
        if (class_exists('\bizuno\myAdminAdmin')) { // Add customizations to administrate
            msgDebug("\nAdding custom admin stuff.");
            $myAdmin = new myAdminAdmin();
            $myAdmin->manager($data);
        }
        $order = 10;
        $validMods = portalModuleList();
        foreach (array_keys($validMods) as $module) { // add the apps dynamically
            if (!empty(getModuleCache($module, 'properties', 'hasAdmin'))) {
                $data['west']['header']['divs']['menu']['data']['child']['apps']['child'][$module] = ['order'=>$order,'label'=>lang($module),'icon'=>$module,
                    'route'=>"$module/admin/adminHome"];
                $order = $order + 5;
            }
        }
        viewDashJS($data);
        $struc = viewMain();
        unset($struc['west']['header']['divs']['menu']['data']);
        $layout = array_replace_recursive($layout, $struc, $data);
    }

    /**
     * Builds the menu for the settings page
     * @return array
     */
    private function viewMenu()
    {
        $data = ['child'=>[
            'home'     => ['order'=>10,'label'=>('home'),             'icon'=>'settings', 'route'=>'administrate/main/redir&url=home'],
            'roster'   => ['order'=>20,'label'=>('directory'),        'icon'=>'address',  'child'=>[
                'users'     => ['order'=>10,'label'=>('users'),       'icon'=>'users',    'route'=>'contacts/main/manager&type=u&dom=div'],
                'mgr_e'     => ['order'=>30,'label'=>('employees'),   'icon'=>'employee', 'route'=>'contacts/main/manager&type=e&dom=div'],
                'factory'   => ['order'=>40,'label'=>('stores'),      'icon'=>'pallet',   'route'=>'contacts/main/manager&type=b&dom=div'],
                'fxdasts'   => ['order'=>50,'label'=>('fixed_assets'),'icon'=>'fixedAsset','route'=>'administrate/fixedAssets/manager']]],
            'apps'     => ['order'=>30,'label'=>('apps'),             'icon'=>'apps',     'child'=>[]], // Apps are filled in real time
            'security' => ['order'=>40,'label'=>('security'),         'icon'=>'shield',   'child'=>[
                'roles'     => ['order'=>10,'label'=>('roles'),       'icon'=>'roles',    'route'=>'administrate/roles/manager'],
                'dashboard' => ['order'=>50,'label'=>('dashboards'),  'icon'=>'dashboard','route'=>'administrate/dashboard/manager']]],
            'data'     => ['order'=>50,'label'=>('storage'),          'icon'=>'disk',     'child'=>[
                'backup'    => ['order'=>10,'label'=>('backup'),      'icon'=>'backup',   'route'=>'administrate/backup/manager']]],
//          'reporting'=> ['order'=>60,'label'=>lang('reporting'),        'icon'=>'report',   'route'=>'phreeform/main/manager'],
            'billing'  => ['order'=>70,'label'=>('billing'),          'icon'=>'checkbook','child' =>[
                'purchases' => ['order'=>10,'label'=>('purchases'),   'icon'=>'money',    'route'=>'administrate/dashboard/manager'],
                'wallet'    => ['order'=>20,'label'=>('wallet'),      'icon'=>'wallet',   'route'=>'administrate/dashboard/manager']]],
            'account'  => ['order'=>80,'label'=>('account'),          'icon'=>'account',  'child'=>[
                'phreesoft' => ['order'=>10,'label'=>('my_phreesoft'),'icon'=>'phreesoft','route'=>'administrate/main/redir&url=psAcct']]]]];
        return $data;
    }
    
    public function redir(&$layout=[])
    {
        $data = [];
        $dest = clean('url', 'alpha_num', 'get');
        switch ($dest) {
            case 'psAcct':
                $html  = 'Manage your PhreeSoft Account at <a href="'.$this->psURL.'">PhreeSoft</a>';
                $action= "winHref('$this->psURL');";
                $data  = ['type'=>'divHTML', 'divs'=>['body'=>['order'=>15, 'type'=>'html', 'html'=>$html]], 'jsReady'=>['init'=>$action]];
                break;
            case 'home':
                $action= "location.href='".BIZUNO_URL_PORTAL."?bizRt=$this->moduleID/$this->pageID/manager'";
                $data  = ['type'=>'divHTML', 'divs'=>['body'=>['order'=>15, 'type'=>'html', 'html'=>$html]], 'jsReady'=>['init'=>$action]];
                break;
            default: msgAdd("Unexpected redirect!");
        }
        $layout = array_replace_recursive($layout, $data);
    }
}
