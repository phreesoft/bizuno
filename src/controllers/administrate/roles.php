<?php
/*
 * Handles user roles [meta only]
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/administrate/roles.php
 */

namespace bizuno;

class administrateRoles extends mgrJournal
{
    public    $moduleID   = 'administrate';
    public    $pageID     = 'roles';
    protected $secID      = 'admin';
    protected $domSuffix  = 'Roles';
    protected $metaPrefix = 'bizuno_role';
    private   $menuBar    = [];

    function __construct()
    {
        parent::__construct();
        $this->securityChoices = [
            ['id'=>'-1','text'=>lang('select')],['id'=>'0', 'text'=>lang('none')], ['id'=>'1', 'text'=>lang('readonly')],
            ['id'=>'2', 'text'=>lang('add')],   ['id'=>'3', 'text'=>lang('edit')], ['id'=>'4', 'text'=>lang('full')], ['id'=>'5', 'text'=>lang('admin')]];
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'        => ['panel'=>'general','order'=> 1,                                 'clean'=>'integer','attr'=>['type'=>'hidden',  'value'=>0]], // For common_meta
            'security'    => ['panel'=>'general','order'=> 1,                                 'clean'=>'array',  'attr'=>['type'=>'hidden',  'value'=>'']],
            'title'       => ['panel'=>'general','order'=>10,'label'=>lang('title'),          'clean'=>'text',   'attr'=>['value'=>'']],
            'inactive'    => ['panel'=>'general','order'=>20,'label'=>lang('inactive'),       'clean'=>'char',   'attr'=>['type'=>'selNoYes','value'=>0]],
            'restrict'    => ['panel'=>'general','order'=>30,'label'=>lang('restrict_access'),'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0], 'tip'=>lang('roles_restrict', $this->moduleID)],
            'administrate'=> ['panel'=>'general','order'=>40,'label'=>lang('administrator'),  'clean'=>'char',   'attr'=>['type'=>'selNoYes','value'=>0]],
            'selFill'     => ['panel'=>'general','order'=>50,'label'=>lang('desc_security_fill', $this->moduleID),'clean'=>'integer','attr'=>['type'=>'select',  'value'=>-1],'values'=>$this->securityChoices,'events'=>['onChange'=>"autoFill();"]],
            'notes'       => ['panel'=>'notes',  'order'=>10,                                 'clean'=>'text',   'attr'=>['type'=>'editor',  'value'=>'']]];
    }
    protected function managerGrid($security=0, $args=[])
    {
        $data = array_replace_recursive(parent::gridBase($security, $args), [
            'columns' => [
                'inactive'=> ['order'=>0, 'attr'=>['hidden'=>true]],
                'title'   => ['order'=>10, 'label'=>lang('title'), 'attr'=>['sortable'=>true,'resizable'=>true]]]]);
        return $data;
    }
    private function managerSettings()
    {
        parent::managerDefaults();
        $this->defaults['sort'] = clean('sort', ['format'=>'cmd',     'default'=>'title'],'post');
        $this->defaults['order']= clean('order',['format'=>'db_field','default'=>'ASC'],  'post');
    }
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div']);
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::mgrRowsMeta($layout, $security);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        parent::editMeta($layout, $security);
        $data  = [ // add the security tabs
            'divs'  => ['content' =>['divs'=>['tabs'=>['order'=>80,'type'=>'panel','classes'=>['block66'],'key'=>'pnlMod']]]],
            'panels'=> ['pnlMod'  =>['type'=>'tabs', 'key'=>'tabRoles']],
            'tabs'  => ['tabRoles'=>['attr'=>['tabPosition'=>'left', 'headerWidth'=>200]]],
            'jsHead'=> ['init'=>"function autoFill() {
    var setting = bizSelGet('selFill');
    jqBiz('#frmRoles select').each(function() {
        if (typeof jqBiz(this).attr('id') !== 'undefined' && jqBiz(this).attr('id').substr(0, 8)=='security') { bizSelSet(jqBiz(this).attr('id'), setting); }
    });
}"]];
        $meta = dbMetaGet($rID, $this->metaPrefix);
        if (empty($meta['security'])) { $meta['security']=[]; }
        $this->roleTabs($data, $meta['security']);
        $layout = array_replace_recursive($layout, $data);
    }
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::copyMeta($layout, $security);
    }
    public function save(&$layout=[])
    {
        $rID = clean('_rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, 4)) { return; }
        unset($_POST['selFill']); // Not to be saved
        parent::saveMeta($layout, $args=['rID'=>$rID]);
        bizCacheExpClear();
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $block = [];
        $rID  = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('Illegal Access!'); }
        $users= getModuleCache('bizuno', 'users');
        foreach ($users as $user) { if ($user['role']==$rID) { $block[] = $user['text']; } }
        if (sizeof($block) > 0) { return msgAdd(sprintf(lang('err_delete_role', $this->moduleID), implode(', ', $block))); }
        parent::deleteMeta($layout, ['table'=>'common']);
    }

    /**
     * Loads additional tabs to the roles edit page for modules other than Bizuno
     * @param integer $fldSettings - database field settings encoded in JSON
     * @return string - HTML view
     */
    private function roleTabs(&$data, $security=[])
    {
        $order  = 50;
        $theList= portalModuleList();
        foreach ($theList as $mID => $path) {
            $fqcn = "\\bizuno\\{$mID}Admin";
            bizAutoLoad("{$path}admin.php", $fqcn);
            $tmp = new $fqcn();
            $this->setMenus($tmp->structure);
        }
        $html   = '';
        $this->menuFillLabels($this->menuBar['child']);
        $html  .= $this->roleTabsMain($data, $order, $this->menuBar['child'], $security);
    }

    private function menuFillLabels(&$menu=[])
    {
        msgDebug("\nEntering menuSort with menu = ".msgPrint($menu));
        foreach ($menu as $key => $cat) {
            $menu[$key]['label'] = !empty($cat['label']) ? lang($cat['label']) : lang($key);
            $menu[$key]['child'] = $this->menuFillLabelsChildren($menu[$key]['child']);
        }
        $menu = sortOrder($menu, 'label');
        msgDebug("\noutput after menuFillLabels = ".msgPrint($menu));
    }

    /**
     * Sets the possible role security levels for menu children
     * @param array $children - list of menu children
     * @param string $title - Category title
     * @param array $security - Security setting of parent
     * @return string - HTML view
     */
    private function menuFillLabelsChildren(&$children=[])
    {
        foreach ($children as $id => $props) {
            if (isset($props['child'])) {
                $children[$id]['child'] = $this->menuFillLabelsChildren($props['child']);
            } elseif (empty($props['required'])) {
                if (empty($props['label'])) { msgAdd("label not set: ".print_r($props, true)); }
                $children[$id]['label'] = lang($props['label']);
            }
        }
        return sortOrder($children, 'label');
    }

    private function roleTabsMain(&$data, &$order, $menu, $security)
    {
        foreach ($menu as $mID => $props) {
            $html = '';
            msgDebug("\nprocessing menu ID = $mID");
            if (!empty($props['child'])) { $html .= $this->roleTabsChildren($props['child'], $props['label'], $security); }
            $order++;
            $data['tabs']['tabRoles']['divs'][$mID] = ['order'=>$order,'label'=>lang($props['label']),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'security' => ['order'=>80,'type'=>'panel','classes'=>['block50'],'key'=>"{$mID}Security"]]];
            $data['panels']["{$mID}Security"] = ['label'=>lang('security'),'type'=>'html','html'=>$html];
        }
        return $html;
    }

    /**
     * Sets the possible role security levels for menu children
     * @param array $children - list of menu children
     * @param string $title - Category title
     * @param array $security - Security setting of parent
     * @return string - HTML view
     */
    private function roleTabsChildren($children=[], $title='', $security=[])
    {
        $tab = '';
        foreach ($children as $id => $props) {
            if (isset($props['child'])) {
                $value = array_key_exists($id, $security) ? $security[$id] : 0;
//                $tab .= html5("security[$id]", ['label'=>$props['label'],'values'=>$this->securityChoices,'attr'=>['type'=>'select','value'=>$value]])."<br />\n";
                $tab .= $this->roleTabsChildren($props['child'], $title, $security);
            } elseif (empty($props['required'])) {
                $value = array_key_exists($id, $security) ? $security[$id] : 0;
                $tab  .= html5("security[$id]", ['label'=>$props['label'],'values'=>$this->securityChoices,'attr'=>['type'=>'select','value'=>$value]])."<br />\n";
            }
        }
        return $tab;
    }

    /**
     * Adds the module menus to the overall menu structure
     * @param type $struc
     */
    private function setMenus(&$struc)
    {
        if (!empty($struc['menuBar'])) {
            $this->menuBar = array_replace_recursive($this->menuBar, $struc['menuBar']);
            unset($struc['menuBar']);
        }
    }
}
