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
 * @version    7.x Last Update: 2026-04-08
 * @filesource /controllers/bizuno/settings.php
 */

namespace bizuno;

class bizunoSettings
{
    public  $moduleID= 'bizuno';
    public  $notes   = [];

    function __construct()
    {
    }

    /**
     * Handles the installation of a module
     * @global array $msgStack - working messages to be returned to user
     * @param array $layout - structure coming in
     * @param string $module - name of module to install
     * @param string $relPath - relative path to module
     * @return modified $layout
     */
    public function moduleInstall(&$layout=[], $module=false, $relPath='')
    {
        global $msgStack, $bizunoMod;
        if (!$security = validateAccess('admin', 3)) { return; }
        if (!$module) {
            $module = clean('rID', 'cmd', 'get');
            $relPath= clean('data','filename', 'get');
        }
        $path = bizAutoLoadMap($relPath);
        if (!$module || !$path) { return msgAdd("Error installing module: unknown. No name/path passed!"); }
        $installed = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value',  "config_key='$module'");
        if ($installed) {
            $settings = json_decode($installed, true);
            if (!$settings['properties']['status']) {
                $settings['properties']['status'] = 1;
                $bizunoMod[$module] = $settings;
                dbWrite(BIZUNO_DB_PREFIX.'configuration', ['config_value'=>json_encode($settings)], 'update', "config_key='$module'");
            } else { return msgAdd(sprintf(lang('err_install_module_exists'), $module), 'caution'); }
        } else {
            $path = rtrim($path, '/') . '/';
            msgDebug("\nInstalling module: $module at path: $path");
            if (!file_exists("{$path}admin.php")) { return msgAdd(sprintf("There was an error finding file %s", "{$path}admin.php")); }
            $fqcn = "\\bizuno\\{$module}Admin";
            bizAutoLoad("{$path}admin.php", $fqcn);
            $adm = new $fqcn();
            $bizunoMod[$module]['settings']                 = isset($adm->settings) ? $adm->settings : [];
            $bizunoMod[$module]['properties']               = $adm->structure;
            $bizunoMod[$module]['properties']['id']         = $module;
//          $bizunoMod[$module]['properties']['title']      = $adm->lang['title']; // These are now handled in the locale process
//          $bizunoMod[$module]['properties']['description']= $adm->lang['description'];
            $bizunoMod[$module]['properties']['status']     = 1;
            $bizunoMod[$module]['properties']['path']       = $relPath;
            $this->adminInstDirs($adm);
            $this->adminAddRpts($path);
            if (method_exists($adm, 'install')) { $adm->install(); }
            if (isset($adm->notes)) { $this->notes = array_merge($this->notes, $adm->notes); }
            // create the initial configuration table record
            $exists = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_key', "config_key='$module'");
            $dbData = ['config_key'=>$module, 'config_value'=>json_encode($bizunoMod[$module])];
            dbWrite(BIZUNO_DB_PREFIX.'configuration', $dbData, $exists?'update':'insert', "config_key='$module'");
            if (!empty($adm->structure['menuBar']['child'])) { $this->setSecurity($adm->structure['menuBar']['child']); }
            msgLog  ("Installed module: $module");
            msgDebug("\nInstalled module: $module");
            if (isset($msgStack->error['error']) && sizeof($msgStack->error['error']) > 0) { msgDebug("\nMsgStack had an error, returning!"); return; }
        }
        $layout = array_replace_recursive($layout, ['content'=>['rID'=>$module,'action'=>'href','link'=>BIZUNO_URL_PORTAL."?bizRt=bizuno/settings/manager"]]);
    }

    /**
     * Modules are not deleted, just status changed to 0
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function moduleDelete(&$layout=[])
    {
        if (!$security = validateAccess('admin', 4)) { return; }
        $module= clean('rID', 'text', 'get');
        if (empty($module)) { return; }
        $props = getModuleCache($module, 'properties');
        msgDebug("\nRemoving module: $module with properties = ".print_r($props, true));
        if (file_exists("{$props['path']}/admin.php")) {
            $fqcn = "\\bizuno\\{$module}Admin";
            bizAutoLoad("{$props['path']}/admin.php", $fqcn);
            $mod_admin = new $fqcn();
            $this->adminDelDirs($mod_admin);
            if (method_exists($mod_admin, 'remove')) { if (!$mod_admin->remove()) {
                return msgAdd("There was an error removing module: $module");
            } }
        }
        // @TODO - remove the methods, if any 'methods_*'
        msgLog("Removed module: $module");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."configuration WHERE config_key='$module'");
        bizCacheExpClear(); // force reload of all users cache with next page access, menus and permissions, etc.
        $layout= array_replace_recursive($layout, ['content'=>['rID'=>$module, 'action'=>'href', 'link'=>BIZUNO_URL_PORTAL."?bizRt=bizuno/settings/manager"]]);
    }

    /**
     * Generates the view for modules methods including any dashboards
     * @param string $module - module or extension id
     * @param array $props - module properties from cache
     * @param string $folder 
     * @return array - HTML code for the structure
     */
    public function adminMethods(&$layout=[])
    {
        $module = clean('module', 'db_field', 'get');
        $folder = clean('folder', 'db_field', 'get');
        msgDebug("\nEntering adminMethods for module $module, folder $folder");
        $fields = [
            'btnMethodAdd' => ['attr'=>['type'=>'button','value'=>lang('enable')]],
            'btnMethodDel' => ['attr'=>['type'=>'button','value'=>lang('disable')]],
            'btnMethodProp'=> ['icon'=>'settings'],
            'settingSave'  => ['icon'=>'save']];
        $html  = '<table style="border-collapse:collapse;width:100%">'."\n".' <thead class="panel-header">'."\n";
        $html .= '  <tr><th>&nbsp;</th><th>'.lang('method').'</th><th>'.lang('description').'</th><th>'.lang('action')."</th></tr>\n </thead>\n <tbody>\n";
        $props = getMetaMethod($folder);
        msgDebug("\nRead meta from folder $folder: ".print_r($props, true));
        foreach ($props as $method => $settings) {
            $fqcn = "\\bizuno\\$method";
            bizAutoLoad("{$settings['path']}$method.php", $fqcn);
            if (!class_exists($fqcn)) { msgAdd("ERROR - Looking for a class ($fqcn) but it is not where it should be! It will be deleted from the cache. See trace.", 'trap'); continue; }
            if (empty($settings['settings'])) { $settings['settings'] = []; }
            $clsMeth = new $fqcn($settings['settings']);
            if (!empty($clsMeth->hidden) || !empty($clsMeth->devStatus)) { continue; }
            $settings = array_replace($settings, ['module'=>$module, 'folder'=>$folder]);
            $html .= "  <tr>\n".'    <td valign="top">'.htmlFindImage($settings)."</td>\n";
            $html .= '    <td valign="top" '.(!empty($settings['status']) ? ' style="background-color:lightgreen"' : '').">".$settings['title'].'</td>';
            $html .= "    <td><div>".$settings['description']."</div>";
            if (empty($settings['status'])) {
                $html .= "</td>\n";
                $fields['btnMethodAdd']['events']['onClick'] = "jsonAction('bizuno/settings/methodInstall&module=$module&path=$folder&method=$method');";
                $html .= '    <td valign="top" style="text-align:right;">'.html5('install_'.$method, $fields['btnMethodAdd'])."</td>\n";
            } else {
                $html .= '<div id="divMethod_'.$method.'" style="display:none;" class="layout-expand-over">';
                $html .= html5("frmMethod_$method", ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=bizuno/settings/methodSettingsSave&module=$module&type=$folder&method=$method"]]);
                if (method_exists($clsMeth, 'settingsHeader')) { $html .= $clsMeth->settingsHeader(); }
                $structure = method_exists($clsMeth, 'settingsStructure') ? $clsMeth->settingsStructure() : [];
                foreach ($structure as $setting => $values) {
                    $mult = isset($values['attr']['multiple']) ? '[]' : '';
                    if (isset($values['attr']['multiple']) && is_string($values['attr']['value'])) { $values['attr']['value'] = explode(':', $values['attr']['value']); }
                    $html .= html5($method.'_'.$setting.$mult, $values)."<br />\n";
                }
                $fields['settingSave']['events']['onClick'] = "jqBiz('#frmMethod_".$method."').submit();";
                $html  .= '<div style="text-align:right">'.html5('imgMethod_'.$method, $fields['settingSave']).'</div>';
                $html  .= "</form></div>";
                htmlQueue("ajaxForm('frmMethod_$method');", 'jsReady');
                $html  .= "</td>\n".'<td valign="top" nowrap="nowrap" style="text-align:right;">' . "\n";
                $fields['btnMethodDel']['events']['onClick'] = "if (confirm('".lang('msg_method_delete_confirm')."')) jsonAction('bizuno/settings/methodRemove&module=$module&type=$folder&method=$method');";
                if (empty($clsMeth->required)) { $html .= html5('remove_'.$method, $fields['btnMethodDel']) . "\n"; }
                $fields['btnMethodProp']['events']['onClick'] = "jqBiz('#divMethod_{$method}').toggle('slow');";
                $html .= html5('prop_'.$method, $fields['btnMethodProp'])."\n";
                $html .= "</td>\n";
            }
            $html .= "  </tr>\n".'<tr><td colspan="5"><hr /></td></tr>'."\n";
        }
        $html .= " </tbody>\n</table>\n";
        $data  = ['type'=>'divHTML', 'divs'=>['body'=>['order'=>50, 'type'=>'html', 'html'=>$html]]];
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     * Installs a method associated with a module
     * @param array $layout - structure coming in
     * @param array $attrs - details of the module to add method
     * @param boolean $verbose - [default true] true to send user message, false to just install method
     * @return type
     */
    public function methodInstall(&$layout=[], $attrs=[], $verbose=true)
    {
        if (!$security=validateAccess('admin', 3)) { return; }
        $subDir = isset($attrs['path'])   ? $attrs['path']   : clean('path',  'text', 'get');
        $method = isset($attrs['method']) ? $attrs['method'] : clean('method','text', 'get');
        if (!$subDir || !$method) { return msgAdd("Bad data installing method!"); }
        msgDebug("\nInstalling method $method with methodDir = $subDir");
        $meta     = dbMetaGet(0, "methods_{$subDir}");
        $metaIdx  = metaIdxClean($meta);
        $fqcn = "\\bizuno\\$method";
        bizAutoLoad("{$meta[$method]['path']}$method.php", $fqcn);
        $clsMeth = new $fqcn();
        if (method_exists($clsMeth, 'install')) { $clsMeth->install($layout); }
        $settings = ['status'=>1, 'settings'=>$clsMeth->settings];
        $merged = array_replace(!empty($meta[$method])?$meta[$method]:[], $settings);
        msgDebug("\nmerged array = ".print_r($merged, true));
        $meta[$method] = $merged;
        dbMetaSet($metaIdx, "methods_{$subDir}", $meta);
        $data = $verbose ? ['content'=>['action'=>'eval','actionData'=>"bizPanelRefresh('tab{$subDir}');"]] : [];
        msgAdd(lang('msg_settings_saved'), 'success');
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Saves user settings for a specific method
     * @param $layout - structure coming in
     * @return modified structure
     */
    public function methodSettingsSave(&$layout=[])
    {
        if (!$security=validateAccess('admin', 3)) { return; }
        $subDir   = clean('type',  'text', 'get');
        $method   = clean('method','text', 'get');
        if (!$subDir || !$method) { return msgAdd("Not all the information was provided!"); } // !$module || 
        $meta     = dbMetaGet(0, "methods_{$subDir}");
        msgDebug("\nRead meta for this method = ".print_r($meta, true));
        $metaIdx  = metaIdxClean($meta);
        $fqcn     = "\\bizuno\\$method";
        bizAutoLoad("{$meta[$method]['path']}$method.php", $fqcn);
        $objMethod= new $fqcn();
        $structure= method_exists($objMethod, 'settingsStructure') ? $objMethod->settingsStructure() : [];
        $settings = [];
        settingsSaveMethod($settings, $structure, $method.'_');
        $meta[$method]['settings'] = $settings;
        dbMetaSet($metaIdx, "methods_{$subDir}", $meta);
        if (method_exists($objMethod, 'settingSave')) { $objMethod->settingSave(); }
        msgAdd(lang('msg_settings_saved'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"jqBiz('#divMethod_$method').hide('slow');"]]);
    }

    /**
     * Removes a method from the db and session cache
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function methodRemove(&$layout=[], $attrs=[])
    {
        if (!$security=validateAccess('admin', 4)) { return; }
        $subDir = isset($attrs['path'])   ? $attrs['path']   : clean('type',  'text', 'get');
        $method = isset($attrs['method']) ? $attrs['method'] : clean('method','text', 'get');
        if (!$subDir) { return msgAdd("Bad method data provided!"); }
        $meta     = dbMetaGet(0, "methods_{$subDir}");
        $metaIdx  = metaIdxClean($meta);
        if (!empty($meta[$method])) {
            $fqcn = "\\bizuno\\$method";
            bizAutoLoad("{$meta[$method]['path']}$method.php", $fqcn);
            $clsMeth = new $fqcn();
            if (method_exists($clsMeth, 'remove')) { $clsMeth->remove(); }
            $meta[$method]['status']  = 0;
            $meta[$method]['settings']= [];
            dbMetaSet($metaIdx, "methods_{$subDir}", $meta);
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval', 'actionData'=>"bizPanelRefresh('tab{$subDir}');"]]);
    }

    public function adminInstMethods($module, $dirMeth, $defaults=[])
    {
        msgDebug("\nEntering adminInstMethods with module = $module and dirMeth = $dirMeth and sizeof defaults = ".sizeof($defaults, true));
        $newMeta= [];
        $meta   = dbMetaGet(0, "methods_{$dirMeth}"); // get the existing meta, probably won't be there since we are installing
        $metaIdx= metaIdxClean($meta);
        // get all methods in the folder to initialize
        $members= $this->methodRead(BIZUNO_FS_LIBRARY."controllers/$module/$dirMeth/");
        foreach ($members as $method) {
            $path   = BIZUNO_FS_LIBRARY."controllers/$module/$dirMeth/$method/";
            msgDebug("\nlooking for method $method at path = ".print_r($path, true));
            $fqcn   = "\\bizuno\\$method";
            if (!bizAutoLoad("{$path}$method.php", $fqcn)) { continue; } // This should never happen as we just read the folder, except with development bug
            $clsMeth= new $fqcn();
            $title  = !empty($clsMeth->lang['title']) ? $clsMeth->lang['title'] : $method;
            $args   = [
                'id'         => $method, // neweded for select dropdown generation
                'title'      => $title,
                'acronym'    => isset($clsMeth->lang['acronym']) ? $clsMeth->lang['acronym']: $title,
                'status'     => in_array($method, $defaults) ? 1 : 0,
                'description'=> !empty($clsMeth->lang['description']) ? $clsMeth->lang['description'] : "Description - $method",
                'path'       => $path,
//              'url'        => BIZUNO_URL_PORTAL."/controllers/$module/$dirMeth/$method/", // @TODO - url is broken - NOT SURE IF IT IS EVEN USED AS DIRECT ACCESS IS VIA API
                'settings'   => (property_exists($clsMeth, 'settings') ? $clsMeth->settings : []) ?? []];
            msgDebug("\nargs array = ".print_r($args, true));
            $merged = array_replace(!empty($meta[$method])?$meta[$method]:[], $args);
            msgDebug("\nmerged array = ".print_r($merged, true));
            $newMeta[$method] = $merged;
        }
        dbMetaSet($metaIdx, "methods_{$dirMeth}", $newMeta);
    }

    private function methodRead($path='')
    {
        $output = [];
        if (!file_exists($path)) { return $output; }
        $temp = scandir($path);
        if (!is_array($temp)) { return $output; }
        foreach ($temp as $fn) {
            if ($fn!='.' && $fn!='..' && is_dir($path.$fn)) {  $output[] = $fn; }
        }
        return $output;
    }

    /**
     * Installs the file structure for a module, if any
     * @param array $dirlist - list for folders to create
     * @param string $path - folder path to start
     * @return boolean, false on error, true on success
     */
    function adminInstDirs($adm)
    {
/* @TODO - DEPRECATED - Folders are now created on the fly as needed. 
        global $io;
        if (!isset($adm->dirlist)) { return; }
        if (is_array($adm->dirlist)) { foreach ($adm->dirlist as $dir) { $io->validatePath($dir); } }
 */
    }

    /**
     * Removes folders when a module is removed
     * @param array $dirlist - folder list to remove
     * @param string $path - root path where folders can be found
     * @return boolean true
     */
    function adminDelDirs($mod_admin)
    {
        if (!isset($mod_admin->dirlist)) { return; }
        if (is_array($mod_admin->dirlist)) {
            $temp = array_reverse($mod_admin->dirlist);
            foreach($temp as $dir) {
                if (!@rmdir(BIZUNO_DATA . $dir)) { msgAdd(sprintf(lang('err_io_dir_remove'), $dir)); }
            }
        }
        return true;
    }

    /**
     * Adds reports to PhreeForm, typically during a module install
     * @param boolean $path - true if a core Bizuno module, false otherwise
     * @return boolean
     */
    public function adminAddRpts($path='')
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreeform/functions.php', 'phreeformImport', 'function');
        $error = false;
        msgDebug("\nEntering adminAddRpts, adding reports to path = $path");
        if (file_exists ($path.'locale/'.getUserCache('profile', 'language', false, 'en_US').'/reports/')) {
            $read_path = $path.'locale/'.getUserCache('profile', 'language', false, 'en_US').'/reports/';
        } elseif (file_exists($path.'locale/en_US/reports/')) {
            $read_path = $path.'locale/en_US/reports/';
        } else { msgDebug(" ... returning with no reports found!"); return true; } // nothing to import
        $files = scandir($read_path);
        foreach ($files as $file) {
            if (strtolower(substr($file, -4)) == '.xml') {
                msgDebug("\nImporting report name = $file at path $read_path");
                if (!phreeformImport('', $file, $read_path, false)) { $error = true; }
            }
        }
        return $error ? false : true;
    }

    /**
     * Sets security for the menu items into the database
     * @param array $menu - menu structure
     */
    private function setSecurity($menu)
    {
        msgDebug("\nEntering setSecurity with menu = ".print_r($menu, true));
        $roleID= getUserCache('profile', 'userRole');
        $role  = dbMetaGet($roleID, 'bizuno_role');
        metaIdxClean($role); // remove the indexes
        $this->addSecurity($role['security'], $menu);
        dbMetaSet($roleID, 'bizuno_role', $role); // need to save intermediate security
    }

    /**
     * Recursively set all security to 4
     * @param type $security
     * @param type $branch
     */
    private function addSecurity(&$security, $branch)
    {
        msgDebug("\nEntering addSecurity with branch = ".print_r($branch, true));
        foreach ($branch as $props) {
            if (empty($props['child'])) { continue; }
            foreach ($props['child'] as $idx => $subProps) {
                $security[$idx] = 4;
                if (!empty($subProps['child'])) {
                    msgDebug("\nWe have a branch, recursing.");
                    $this->addSecurity($security, $props['child']);
                }
            }
        }
    }
}
