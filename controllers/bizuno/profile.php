<?php
/*
 * This method handles user profiles
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
 * @version    7.x Last Update: 2025-08-03
 * @filesource /controllers/bizuno/profile.php
 */

namespace bizuno;

class bizunoProfile extends mgrJournal
{
    public    $moduleID  = 'bizuno';
    public    $pageID    = 'profile';
    protected $domSuffix = 'Profile';
    protected $metaPrefix= 'user_profile';

    function __construct()
    {
        parent::__construct();
        $this->fieldStructure();
    }
    protected function fieldStructure()
    {
        $periods= viewKeyDropdown(localeDates(true, false, true, true, false));
        $values = [10,20,30,40,50]; // This must match the values set in the UI (EasyUI for now), [10,20,30,40,50] is the default
        foreach ($values as $value) {$rows[] = ['id'=>$value, 'text'=>$value]; }
        $mail = new bizunoMailer();
        $this->struc = [ 
            'title'      => ['tab'=>'options','panel'=>'general','order'=>10, 'clean'=>'text',     'attr'=>['value'=>getUserCache('profile', 'userName'),'readonly'=>true]],
            'email'      => ['tab'=>'options','panel'=>'general','order'=>15, 'clean'=>'email',    'attr'=>['value'=>getUserCache('profile', 'email'),   'readonly'=>true]],
            'user_pin'   => ['tab'=>'options','panel'=>'general','order'=>20, 'clean'=>'integer',  'attr'=>['type'=>'password','value'=>'']],
            'language'   => ['tab'=>'options','panel'=>'general','order'=>25, 'clean'=>'db_field', 'attr'=>['type'=>'select',  'value'=>'en_US'],  'values'=>viewLanguages(),'options'=>['width'=>300]],
            'def_periods'=> ['tab'=>'options','panel'=>'general','order'=>30, 'clean'=>'db_field', 'attr'=>['type'=>'select',  'value'=>'l'],      'values'=>$periods],
            'grid_rows'  => ['tab'=>'options','panel'=>'general','order'=>35, 'clean'=>'integer',  'attr'=>['type'=>'select',  'value'=>20],       'values'=>$rows],
            'icons'      => ['tab'=>'options','panel'=>'general','order'=>40, 'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>'default'],'values'=>portalIcons()],
            'theme'      => ['tab'=>'options','panel'=>'general','order'=>45, 'clean'=>'alpha_num','attr'=>['type'=>'select',  'value'=>'bizuno'], 'values'=>portalSkins()]];
        $this->struc = array_replace($this->struc, $mail->struc); // bring in the mail settings
        langFillLabels($this->struc, $this->lang);
    }
    public function edit(&$layout=[])
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        $metaVal = dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nRead meta pre process = ".print_r($metaVal, true));
        $rID  = metaIdxClean($metaVal); // need a rID since we enter directly from a menu selection
        $args = ['dom'=>'page', '_rID'=>$rID, '_table'=>'contacts', '_refID'=>getUserCache('profile', 'userID'), 'title'=>lang('edit_profile')];
        parent::editMeta($layout, 3, $args);
        $layout['tabs']['tabProfile']['divs']['reminders'] = ['order'=>50,'label'=>$this->lang['reminders'],'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=bizuno/reminder/manager'"]];
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow new, copy here
    }
    public function save()
    {
        if (empty(getUserCache('profile', 'userID'))) { return; }
        if (empty(clean('user_pin', 'text', 'post'))) { unset($this->struc['user_pin']); } // only update password if it there is a value, otherwise keep the value
        $metaVal= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
//if (!isset($metaVal['title']) && isset($metaVal[0])) { msgDebug("\nMeta profile is malformed, fixing it."); $metaVal = array_shift($metaVal); }
        $rID    = metaIdxClean($metaVal);
        $newVal = metaUpdate($metaVal, $this->struc);
        $output = array_replace($metaVal, $newVal);
        msgDebug("\nWriting fetched metaVal = ".print_r($output, true));
        dbMetaSet($rID, $this->metaPrefix, $output, 'contacts', getUserCache('profile', 'userID'));
        dbSetBizunoUsers();
        msgAdd(lang('msg_record_saved'), 'success');
        msgLog("$this->mgrTitle - ".lang('save').": {$output['title']}");
    }
    public function update()
    {
        $menuSize = clean('menuSize', 'cmd', 'get');
        msgDebug("\nread menuSize = $menuSize");
        $metaVal= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
        $rID    = metaIdxClean($metaVal);
        if (!empty($menuSize)) { $metaVal['menuSize'] = $menuSize; }
        msgDebug("\nWriting fetched metaVal = ".print_r($metaVal, true));
        dbMetaSet($rID, $this->metaPrefix, $metaVal, 'contacts', getUserCache('profile', 'userID'));
    }
}
