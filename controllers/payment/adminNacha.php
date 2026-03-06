<?php
/*
 * Administration for ACH banking accounts
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
 * @filesource /controllers/payment/adminNacha.php
 */

namespace bizuno;

class paymentAdminNacha extends mgrJournal
{
    public    $moduleID  = 'payment';
    public    $pageID    = 'adminNacha';
    protected $secID     = 'admin';
    protected $domSuffix = 'Nacha';
    protected $metaPrefix= 'nacha';

    public function __construct()
    {
        parent::__construct();
        $this->managerSettings();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $this->struc = [ 
            '_rID'     => ['panel'=>'general','order'=> 1,                                                                           'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'cID'      => ['panel'=>'general','order'=>10,'label'=>lang('ctype_v'),'defaults'=>['type'=>'v','data'=>'achVendor','callback'=>''],         'attr'=>['type'=>'contact','value'=>0]],
            'title'    => ['panel'=>'general','order'=>15,'label'=>lang('title'),                                                    'clean'=>'text',    'attr'=>['value'=>'']],
            'mapID'    => ['panel'=>'general','order'=>20,'label'=>lang('nacha_map_lbl', $this->moduleID),'values'=>$this->getMaps(),          'clean'=>'db_field','attr'=>['type'=>'select']],
            'gl_acct'  => ['panel'=>'general','order'=>30,'label'=>lang('gl_account'),                                               'clean'=>'db_field','attr'=>['type'=>'ledger','id'=>'gl_acct','value'=>getModuleCache('phreebooks', 'settings', 'vendors', 'gl_cash')]], // gl cash account
            'biz_route'=> ['panel'=>'general','order'=>40,'label'=>lang('biz_route_lbl', $this->moduleID),'tip'=>lang('biz_route_tip', $this->moduleID), 'clean'=>'db_field','attr'=>['value'=>'', 'options'=>['groupSeparator'=>"''"]]], // EFT Transit Routing Number, assigned by bank
            'biz_id'   => ['panel'=>'general','order'=>50,'label'=>lang('biz_id_lbl', $this->moduleID),   'tip'=>lang('biz_id_tip', $this->moduleID),    'clean'=>'integer', 'attr'=>['value'=>''], 'options'=>['groupSeparator'=>"''"]], // EFT Company ID, assigned by bank
            'biz_entry'=> ['panel'=>'general','order'=>60,'label'=>lang('biz_entry_lbl', $this->moduleID),'tip'=>lang('biz_entry_tip', $this->moduleID), 'clean'=>'db_field','attr'=>['value'=>'']], // Company Entry Description
            'biz_name' => ['panel'=>'general','order'=>70,'label'=>lang('biz_name_lbl', $this->moduleID), 'tip'=>lang('biz_name_tip', $this->moduleID),  'clean'=>'db_field','attr'=>['value'=>'']]]; // My Company Name
    }
    protected function managerGrid($security=0, $args=[], $admin=false)
    {
        $data = array_replace_recursive(parent::gridBase($security, $args, $admin), [
            'source' => ['search'=>['title', 'biz_entry', 'biz_name']],
            'columns'=> [
                'title'  => ['order'=>10,'label'=>lang('ach_title', $this->moduleID)],
                'mapID'  => ['order'=>20,'label'=>lang('nacha_map_lbl', $this->moduleID)],
                'gl_acct'=> ['order'=>30,'label'=>lang('gl_account')]]]);
        return $data;
    }
    protected function managerSettings()
    {
        parent::managerDefaults();
    }
    /******************************** Meta Entries ********************************/
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::managerMain($layout, $security, ['dom'=>'div', 'title'=>sprintf(lang('tbd_manager'),lang('ach_accounts', $this->moduleID))]);
        unset($layout['datagrid']["dg{$this->domSuffix}"]['columns']['action']['actions']['copy']); // Don't allow copy here
    }
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $_POST['search'] = getSearch();
        $grid  = $this->managerGrid($security, ['type'=>'journal']);
        mapMetaGridToDB($grid, $this->struc);
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>"dg{$this->domSuffix}",'datagrid'=>["dg{$this->domSuffix}"=>$grid]]);
    }
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        parent::editMeta($layout, $security, $args=[]);
        $achData = [['id'=>0, 'text'=>'']];
        if (!empty($layout['fields']['cID']['attr']['value'])) {
            $vendor = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id', 'primary_name'], "id={$layout['fields']['cID']['attr']['value']}");
            if (!empty($vendor)) { $achData = [['id'=>$vendor['id'], 'text'=>$vendor['primary_name']]]; }
        }
        $layout['jsHead'][$this->pageID] = "var achVendor = ".json_encode($achData).';';
    }
    public function save(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        parent::saveDB($layout);
    }
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        parent::deleteDB($layout);
    }
    private function getMaps()
    {
        $path = bizAutoLoadMap('BIZUNO_FS_LIBRARY/controllers/phreebooks/nachaMaps/');
        $temp = glob("{$path}*.php");
        msgDebug("\nread from maps: ".print_r($temp, true));
        foreach ($temp as $fn) {
            $map = [];
            include($fn); // resets $map
            if (empty($map['id'])) { continue; }
            $output[] = ['id'=>$map['id'], 'text'=>$map['title']];
        }
        return sortOrder($output, 'text');
    }
}
