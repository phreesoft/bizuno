<?php
/*
 * Module PhreeForm main functions
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
 * @version    7.x Last Update: 2025-07-29
 * @filesource /controllers/phreeform/admin.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreeform/functions.php', 'phreeformFonts', 'function');

class phreeformAdmin
{
    public $moduleID = 'phreeform';
    public $pageID   = 'admin';
    public $lang;
    public $settings;
    public $structure;

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure= [
            'menuBar' => ['child'=>['tools'=>['child'=>[
                'phreeform'=>['order'=>50,'label'=>lang('phreeform_manager'),'icon'=>'mimeDoc','route'=>'phreeform/main/manager']]]]]];
    }

    /**
     * Sets the structure for the user definable settings for module PhreeForm
     * @return array - structure ready to render in PhreeForm settings
     */
    public function settingsStructure()
    {
        $data = [
            'general' => ['order'=>10,'label'=>lang('general'),'fields'=>[
                'default_font'=> ['values'=>phreeformFonts(false), 'attr'=>  ['type'=>'select', 'value'=>'helvetica']],
                'column_width'=> ['attr'=>['value'=>25]],
                'margin'      => ['attr'=>['value'=>8]],
                'title1'      => ['attr'=>['value'=>'%reportname%']],
                'title2'      => ['attr'=>['value'=>$this->lang['phreeform_heading_2']]], // 'Report Generated %date%'
                'paper_size'  => ['values'=>phreeformPages($this->lang), 'attr'=>  ['type'=>'select', 'value'=>'Letter:216:282']],
                'orientation' => ['values'=>phreeformOrientation($this->lang),'attr'=>  ['type'=>'select', 'value'=>'P']],
                'truncate_len'=> ['attr'=>['value'=>'25']]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    /**
     * Sets the structure for the settings home page for module PhreeForm
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang));
    }

    /**
     * Saves the user defined settings in the cache and database
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}
