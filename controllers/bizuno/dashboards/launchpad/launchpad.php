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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/bizuno/dashboards/launchpad/launchpad.php
 */

namespace bizuno;

class launchpad
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'launchpad';
    public $category = 'bizuno';
    public  $struc;
    private $choices = [];
    public $lang = ['title'=>'Launchpad',
        'description'=> 'Creates a one-click launchpad to popular menu items.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true],
            // User fields
            'rptID' => ['order'=>40,'label'=>lang('report_add'),'clean'=>'integer','attr'=>['type'=>'select','value'=>''],'values'=>[]]]; // $reports
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render()
    {
        $this->choices = [['id'=>'', 'text'=>lang('select')]];
        $menus= dbGetRoleMenu();
        $menu1= sortOrderLang($menus['menuBar']['child']);
        $this->listMenus($menu1);
        $data = ['delete_icon'=>['icon'=>'trash', 'size'=>'small']];
        // build the delete list inside of the settings
        $html = $body = 'Needs Fixin';
/*        if (is_array($this->settings)) { foreach ($this->settings as $idx => $value) {
            $parts = explode(':', $value, 2);
            if (sizeof($parts) > 1) { $parts[0] = $parts[1]; } // for legacy
            $props = $this->findIdx($menu1, $parts[0]);
            $data['delete_icon']['events'] = ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->moduleID:$this->code', ($idx+1)); }"];
            $html  .= '<div><div style="float:right;height:17px;">'.html5('delete_icon', $data['delete_icon']).'</div>';
            $html  .= '<div style="min-height:17px;">'.lang($props['label']).'</div></div>';
            // build the body part while we're here
            $btnHTML= html5('', ['icon'=>$props['icon']]).'<br />'.lang($props['label']);
            $body  .= html5('', ['styles'=>['width'=>'100px','height'=>'100px'],'events'=>['onClick'=>$props['events']['onClick']],'attr'=>['type'=>'button','value'=>$btnHTML]])."&nbsp;";
        } } else { $body .= "<span>".lang('no_results')."</span>"; } */
        return ['html'=>$body];
    }

    private function listMenus($source, $cat=false)
    {
        if (empty($source)) { return; }
        foreach ($source as $key => $menu) {
            if (!isset($menu['child'])) { continue; }
            if (empty($menu['label'])) { $menu['label'] = $key; }
            foreach ($menu['child'] as $idx => $submenu) {
                if (empty($submenu['label'])) { $submenu['label'] = $idx; }
                if (empty($submenu['security'])) { continue; }
                if (!isset($submenu['hidden']) || !$submenu['hidden']) {
                    $label = $cat ? $cat : lang($menu['label']);
                    $this->choices[] = ['id'=>"$idx", 'text'=>"$label - ".lang($submenu['label'])];
                    if (isset($submenu['child'])) { $this->listMenus($menu['child']); }
                }
            }
        }
    }

    private function findIdx($source, $key='')
    {
        $props = false;
        foreach ($source as $menu) {
            if (!isset($menu['child'])) { continue; }
            foreach ($menu['child'] as $idx => $submenu) {
                if ($key == $idx) { return $submenu; }
                if (isset($submenu['child'])) {
                    $props = $this->findIdx($menu['child'], $key);
                    if ($props) { return $props; }
                }
            }
        }
        return $props;
    }

    public function save($usrMeta)
    {
        $rmID = clean('rID', 'integer', 'get');
        $rptID= clean($this->code.'rptID', 'integer', 'post');
        if (empty($rmID) && empty($rptID)) { return msgAdd(lang('bad_data')); } // do nothing if no title or url entered
        if ($rmID) { array_splice($usrMeta[$this->code]['opts']['data'], array_search($rmID, $usrMeta[$this->code]['opts']['data']), 1); }
        else       { $usrMeta[$this->code]['opts']['data'][] = $rptID; }
        return $usrMeta;
    }
}
