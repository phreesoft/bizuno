<?php
/*
 * Bizuno dashboard - Launchpad to menu links
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
 * @version    7.x Last Update: 2025-11-24
 * @filesource /controllers/bizuno/dashboards/launchpad/launchpad.php
 */

namespace bizuno;

class launchpad
{
    public  $moduleID = 'bizuno';
    public  $methodDir= 'dashboards';
    public  $code     = 'launchpad';
    public  $category = 'bizuno';
    public  $struc;
    public  $role;
    private $choices = [];
    public  $lang = ['title'=>'Launchpad',
        'description'=> 'Creates a one-click launchpad to popular menu items.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $roleID = getUserCache('profile', 'userRole');
        $this->role = dbMetaGet($roleID, 'bizuno_role');
//msgDebug("\nRead role properties for this user = ".print_r($this->role, true));
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->choices = [['id'=>'', 'text'=>lang('select')]];
        $this->listMenus();
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array',    'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array',    'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true],
            // User fields
            'menuID'=> ['order'=>40,'label'=>lang('action'),'clean'=>'alpha_num','attr'=>['type'=>'select','value'=>''], 'values'=>$this->choices, 'options'=>['groupField'=>"'group'"]]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render($opts=[])
    {
        if (empty($opts['data'])) { $rows[] = '<div><span>'.lang('no_results').'</span></div>'; }
        else { 
            foreach ($opts['data'] as $menuID) {
                $action = $this->findIdx($menuID);
                if (empty($action)) { msgDebug("\nReport ID $menuID not found"); continue; } // menu item is missing or permission changed?
                $action['id']   = $menuID;
                $action['label']= lang($action['label']);
                $theList[]      = $action;
            }
            $sorted = sortOrder($theList, 'label');
            foreach ($sorted as $row) {
                $onClick= isset($row['onClick']) ? $row['onClick'] : "winHref(bizunoHome+'?bizRt={$row['route']}');";
                $content= html5('', ['icon'=>$row['icon'],'events'=>['onClick'=>$onClick]]).viewText($row['label']);
                $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', '{$row['id']}'); }"]]);
                $rows[] = viewDashList($content, $trash);
            }
        }
        return ['lists'=>$rows];
    }

    private function listMenus()
    {
        if (empty($this->role['menuBar'])) { return; }
        foreach ($this->role['menuBar'] as $key => $menu) {
            if (!isset($menu['child'])) { continue; }
            if ( empty($menu['label'])) { $menu['label'] = $key; }
            $menu['child'] = sortOrder($menu['child']);
            foreach ($menu['child'] as $idx => $submenu) {
                if (empty($submenu['label'])) { $submenu['label'] = $idx; }
                if (empty($submenu['security'])) { continue; }
                if (!isset($submenu['hidden']) || !$submenu['hidden']) { $this->choices[] = ['id'=>"$idx", 'text'=>lang($submenu['label']), 'group'=>lang($key)]; }
            }
        }
    }

    private function findIdx($key='')
    {
        $props = false;
        foreach ($this->role['menuBar'] as $menu) {
            if (!isset($menu['child'])) { continue; }
            foreach ($menu['child'] as $idx => $submenu) { if ($key == $idx) { return $submenu; } }
        }
        return $props;
    }

    public function save(&$usrMeta)
    {
        $rmID  = clean('rID', 'alpha_num', 'get');
        $menuID= clean($this->code.'menuID', 'alpha_num', 'post');
        if (empty($rmID) && empty($menuID)) { return msgAdd(lang('illegal_access')); } // do nothing if no title or url entered
        if ($rmID) { array_splice($usrMeta[$this->code]['opts']['data'], array_search($rmID, $usrMeta[$this->code]['opts']['data']), 1); }
        else       { $usrMeta[$this->code]['opts']['data'][] = $menuID; }
        unset($usrMeta[$this->code]['opts']['menuID']); // reset the menuID for the next round
    }
}
