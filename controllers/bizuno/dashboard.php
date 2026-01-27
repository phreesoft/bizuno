<?php
/*
 * All things dashboard related
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
 * @version    7.x Last Update: 2025-12-05
 * @filesource /controllers/bizuno/dashboard.php
 */

namespace bizuno;

class bizunoDashboard
{
    public  $moduleID = 'bizuno';
    public  $pageID   = 'dashboard';
    private $myDash = [];
    public  $lang;
    public  $admin;

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->admin= getUserCache('role', 'administrate');
    }

    /**
     * manager either at admin (all dashboards) or at user/menu level
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function manager(&$layout=[])
    {
        msgdebug("\nlanguage = ".print_r($this->lang, true));
        $menuID= clean('menuID',   ['format'=>'text','default'=>'home'], 'get');
        if    ($menuID=='home')    { $label = lang('home'); }
        elseif($menuID=='settings'){ $label = lang('bizuno_company'); }
        else                       { $menus = dbGetRoleMenu(); $label=$menus['menuBar']['child'][$menuID]['label']; }
        $title = sprintf($this->lang['edit_dashboard'], $label );
        $data  = [
            'title'  => sprintf(lang('tbd_manager'), lang('dashboard')),
            'menu_id'=> $menuID,
            'divs'   => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbDashBoard'],
                'frmDash' => ['order'=>15,'type'=>'html',   'html'=>html5('frmDashboard', ['attr'=>['type'=>'form', 'action'=>BIZUNO_URL_AJAX."&bizRt=bizuno/dashboard/save&menuID=$menuID"]])],
                'heading' => ['order'=>30,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'adminSet'=> ['order'=>50,'type'=>'tabs',   'key' =>'tabSettings'],
                'footer'  => ['order'=>99,'type'=>'html',   'html'=>"</form>"]],
            'jsReady' => ['jsForm'=>"ajaxForm('frmDashboard');"],
            'tabs'    => ['tabSettings'=> ['attr'=>['tabPosition'=>'left']]],
            'toolbars'=> ['tbDashBoard'=> ['icons' => [
                'cancel'=> ['order'=> 10, 'events'=>['onClick'=>"location.href='".BIZUNO_URL_PORTAL."?bizRt=bizuno/main/bizunoHome&menuID=$menuID'"]],
                'save'  => ['order'=> 20, 'events'=>['onClick'=>"jqBiz('#frmDashboard').submit();"]]]]]];
        $allDash = dbMetaGet(0, 'dashboards'); // Fetch list of all dashboards
        metaIdxClean($allDash); // remove the row id, etc
        msgDebug("\nRead all dashboards with and from meta = ".print_r($allDash, true));
        $tree    = [];
        foreach ($allDash as $dashID => $opts) { // put them into the tabbed lists
            if ($this->admin || $this->autenticateUser($opts['opts'])) {
                if (!empty($opts['hidden'])) { continue; }
                $tree[$opts['group']][$dashID] = ['title'=>$opts['title'], 'description'=>$opts['description']];
            }
        }
        msgDebug("\nTree is now: ".print_r($tree, true));
        $userMenu= dbMetaGet(0, "dashboard_{$menuID}", 'contacts', getUserCache('profile', 'userID'));
        if (!empty($userMenu)) { metaIdxClean($userMenu); } else { $userMenu = []; }
        $userIDs = array_keys($userMenu); // Fetch the current users menu list to set the checkboxes
        msgDebug("\nCurrent menu active dashboards = ".print_r($userIDs, true));
        $header = '<table style="border-collapse:collapse;width:100%">'."\n".' <thead class="panel-header">'."\n";
        $header.= "  <tr><th>".lang('active')."</th><th>".lang('title')."</th><th>".lang('description')."</th></tr>\n</thead>\n <tbody>\n";
        $footer = " </tbody>\n</table>\n";
        $order  = 1;
        foreach ($tree as $group => $dashIDs) {
            $ordered = sortOrder($dashIDs, 'title');
            $html = $header;
            foreach ($ordered as $dashID => $piece) {
                $htmlEl= ['attr'=>['type'=>'checkbox','value'=>$dashID, 'checked'=>in_array($dashID, $userIDs)?true:false]];
                $html .= "  <tr><td>".html5("dashID[]", $htmlEl)."</td><td>".$piece['title']."</td><td>".$piece['description']."</td></tr>\n";
                $html .= '  <tr><td colspan="4"><hr /></td></tr>'."\n";
            }
            $html .= $footer;
            $data['tabs']['tabSettings']['divs'][$group] = ['order'=>$order,'label'=>lang($group),'type'=>'html','html'=>$html];
            $order++;
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /*
     * Retrieves the dashboards with settings for a given menu, from there each is loaded separately
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function render(&$layout=[])
    {
        $menu_id = clean('menuID', 'text', 'get');
        $layout = array_replace_recursive($layout, ['content'=>$this->listDashboards($menu_id)]);
    }

    /**
     * Saves state after user moves dashboards on first level screen. Stores the dashboard placement on a given menu in the users profile
     */
    public function organize()
    {
        $menu_id = clean('menuID', 'text', 'get');
        $state   = clean('state',  'text', 'get');
        $columns = explode(':', $state);
        msgDebug("\nEntering organize with num columns = ".getUserCache('profile', 'cols', false, 3));
        $metaMenu= dbMetaGet(0, "dashboard_{$menu_id}", 'contacts', getUserCache('profile', 'userID'));
        $rID     = metaIdxClean($metaMenu);
        msgDebug("\nRead all dashboards with rID = $rID and from meta = ".print_r($metaMenu, true));
        for ($col = 0; $col < sizeof($columns); $col++) {
            $rows = explode(',', $columns[$col]);
            foreach ($rows as $row => $dashID) {
                $metaMenu[$dashID]['col'] = $col;
                $metaMenu[$dashID]['row'] = $row;
            }
        }
        msgDebug("\nPost processing ready to write  meta = ".print_r($metaMenu, true));
        dbMetaSet($rID, "dashboard_{$menu_id}", $metaMenu, 'contacts', getUserCache('profile', 'userID'));
    }

    /**
     * Save selected dashboards into the users profile
     * @param array $layout - structure entering the method
     * @return array - $layout modified with the dashboard settings
     */
    public function save(&$layout=[])
    {
        $menuID  = clean('menuID', 'db_field','get');
        $dashIDs = clean('dashID', 'array',   'post');
        // Get the user/menu list
        $userMenu= dbMetaGet(0, "dashboard_{$menuID}", 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nWorking with userMenu = : ".print_r($userMenu, true));
        if (!empty($userMenu)) { $rID = metaIdxClean($userMenu); }
        else                   { $rID = 0; $userMenu = []; }
        foreach (array_keys($userMenu) as $dashID) { // removes
            if (!in_array($dashID, $dashIDs) || empty($dashID)) { unset($userMenu[$dashID]); }
        }
        foreach ($dashIDs as $dashID) { // adds
            if (!isset($userMenu[$dashID])) { $userMenu[$dashID] = ['col'=>0, 'row'=>-1, 'opts'=>[]]; }
        }
        msgDebug("\nReady to write updated dashboard list: ".print_r($userMenu, true));
        dbMetaSet($rID, "dashboard_{$menuID}", $userMenu, 'contacts', getUserCache('profile', 'userID'));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'href', 'link'=>BIZUNO_URL_PORTAL."?bizRt=bizuno/main/bizunoHome&menuID=$menuID"]]);
    }

    /**
     * Renders the dashboard contents, called when loading menu home pages
     * @param modified array - $layout modified with the dashboard settings
     */
    public function settings(&$layout=[])
    {
        $dashID = clean('dashID','db_field', 'get');
        $menuID = clean('menu',  'db_field', 'get');
        msgDebug("\nEntering bizuno:dashboards:settings with dashID = $dashID and menu = $menuID");
        $this->myDash = getDashboard($dashID);
        if (empty($this->myDash)) { return msgAdd("ERROR: Dashboard $dashID NOT FOUND!"); }
        $gblMeta= !empty($this->myDash->struc) ? metaExtract($this->myDash->struc) : []; // get the global options
        msgDebug("\nglobal meta = ".print_r($gblMeta, true));
        $usrMeta= !empty(getUserCache('profile', 'userID')) && $menuID<>'portal' ? dbMetaGet(0, "dashboard_{$menuID}", 'contacts', getUserCache('profile', 'userID')) : [];
        msgDebug("\nuser meta = ".print_r($usrMeta, true));
        $meta   = array_replace($gblMeta, !empty($usrMeta[$dashID]['opts']) ? $usrMeta[$dashID]['opts'] : []);
        msgDebug("\nmerged meta = ".print_r($meta, true));
        $data   = $this->myDash->render($meta);
        $layout = array_replace_recursive($layout, $this->viewDash($data, $meta));
        msgDebug("\nlayout after processing = ".print_r($layout, true));
    }

    /**
     * Deletes a dashboard from the users profile
     * @return null, removes the table row from the users profile
     */
    public function delete()
    {
        $menuID  = clean('menuID',     'text','get');
        $dashID  = clean('dashboardID','text','get');
        $myDash  = getDashboard($dashID);
        if (empty($myDash)) { return msgAdd('ERROR: Dashboard delete failed!'); }
        if (method_exists($myDash, 'remove')) { $myDash->remove($menuID); }
        $userMenu= dbMetaGet(0, "dashboard_{$menuID}", 'contacts', getUserCache('profile', 'userID'));
        $rID     = metaIdxClean($userMenu);
        unset($userMenu[$dashID]);
        dbMetaSet($rID, "dashboard_{$menuID}", $userMenu, 'contacts', getUserCache('profile', 'userID'));
    }

    /**
     * Updates a dashboard settings from the menu settings submit
     * @return null, just saves the new settings so next time the dashboard is loaded, the new settings will be there
     */
    public function attr(&$layout=[])
    {
        $menuID = clean('menuID','db_field','get');
        $dashID = clean('dashID','db_field','get');
        $myDash = getDashboard($dashID);
        if (empty($myDash)) { return msgAdd(lang('illegal_access').": dashboard:attr"); }
        $userMenu= dbMetaGet(0, "dashboard_{$menuID}", 'contacts', getUserCache('profile', 'userID'));
        $rID     = metaIdxClean($userMenu);
        foreach ($myDash->struc as $idx => $values) {
            if (!empty($values['admin'])) { continue; } // ignore if admin settings - removed !isset($_POST[$dashID.$idx]) as checkboxes are not cleared
            $cleaned = clean($dashID.$idx, $values['clean'], 'post');
            msgDebug("\nCleaning post variable {$dashID}$idx with clean = {$values['clean']} resulting in: $cleaned");
            $userMenu[$dashID]['opts'][$idx] = $cleaned;
        }
        // Moved this AFTER saving all of the value because sometimes we don't want to save the values, e.g. favorite_reports 
        if (method_exists($myDash, 'save')) { // special processing, like Work, just do and continue in case it was a settings change
            msgDebug("\nDashboard has save method, going there to do more stuff.");
            $myDash->save($userMenu);
        }
        msgDebug("\nWriting userMeta: ".print_r($userMenu, true));
        dbMetaSet($rID, "dashboard_{$menuID}", $userMenu, 'contacts', getUserCache('profile', 'userID'));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#$dashID').panel('refresh');"]]);
    }

    /**
     * Builds the dashboard list for a given menu
     * @param string $menu_id
     * @return array - structure of dashboards to render menu page
     */
    private function listDashboards($menu_id='home')
    {
        msgDebug("\nEntering listDashboards with menu_id = $menu_id");
        $cols = getColumns();
        $result = $dashboard = $state = $temp = [];
        if (!empty(getUserCache('profile', 'userID')) && $menu_id=='settings') { // Administration page
            $homeList = ['adm_module']; // ['adm_user', 'adm_role', 'adm_alert', 'adm_notice', 'adm_billing', 'adm_employee']; // THIS SHOULD BE A CONSTANT SET IN BIZUNO
            $curCol = $curRow = 0;
            foreach ($homeList as $dash) {
                $result[$dash] = ['col'=>$curCol,'row'=>$curRow,'module_id'=>'ispPortal','dashboard_id'=>$dash];
                $curCol++;
                if ($curCol>=$cols) { $curRow++; $curCol=0; }
            }
            msgDebug("\ndashboard list for settings is ".print_r($result, true));
        } elseif (!empty(getUserCache('profile', 'userID')) && $menu_id<>'portal' && dbTableExists(BIZUNO_DB_PREFIX.'configuration')) { // Normal Operation
            $rows  = dbMetaGet(0, "dashboard_{$menu_id}", 'contacts', getUserCache('profile', 'userID'));
            $rID   = metaIdxClean($rows);
            msgDebug("\nRead dashboards for menu $menu_id with rID = $rID and rows = ".print_r($rows, true));
            $temp1 = sortOrder($rows, 'row');
            $result= sortOrder($temp1,'col');
        } else { // Not logged in so just show startup dashboards
            $result = getUserCache('dashboards');
            $menu_id='portal';
        }
        msgDebug("\ncols = $cols and menu_id = $menu_id and sizeof sorted dashboard list = ".sizeof(!empty($result)?$result:[]));
        foreach ($result as $dashID => $attrs) {
            $colID = min($cols-1, !empty($attrs['col']) ? $attrs['col'] : 0);
            $myDash = getDashboard($dashID);
            if (empty($myDash) && getUserCache('profile', 'userID')) { continue; }
            $temp[$colID][] = $dashID;
            $icnTools = [['iconCls' => 'icon-refresh','handler'=>"(function () { jqBiz('#{$dashID}').panel('refresh'); })"]];
            if (empty($myDash->noSettings)) {
                $icnTools[] = ['iconCls'=>'icon-edit','handler'=>"(function () { jqBiz('#{$dashID}_attr').toggle('slow'); })"];
            }
            $dashboard[] = [
                'id'         => $dashID,
                'title'      => empty($myDash->lang['title'])? $dashID: $myDash->lang['title'],
                'noHeader'   => empty($myDash->noHeader)     ? true   : false,
                'collapsible'=> empty($myDash->noCollapse)   ? true   : false,
                'closable'   => empty($myDash->noClose)      ? true   : false,
                'tools'      => $icnTools,
                'href'       => BIZUNO_URL_AJAX."&bizRt=bizuno/dashboard/settings&dashID=$dashID&menu=$menu_id"];
        }
        msgDebug("\nList dashboards for menu ID = $menu_id is: ".print_r($dashboard, true));
        for ($i = 0; $i < $cols; $i++) { $state[] = !empty($temp[$i]) && is_array($temp[$i]) ? implode(',', $temp[$i]) : ''; }
        return ['Dashboard'=>$dashboard, 'State'=>implode(':', $state)];
    }

    /**
    * This function loads the details of all dashboards for active modules ONLY
    * @param string $menu_id - (default: home) Lists the menu index to find loaded dashboards
    * @return array $result
    */
    private function loadDashboards($menu_id='home')
    {
        global $bizunoMod;
        $output = $loaded = [];
        $loaded_dashboards = $this->listDashboards($menu_id);
        if (is_array($loaded_dashboards['Dashboard'])) { foreach ($loaded_dashboards['Dashboard'] as $dashboard) { $loaded[] = $dashboard['id']; } }
        foreach ($bizunoMod as $module => $settings) {
            $path    = bizAutoLoadMap($settings['properties']['path']);
            if (empty($path) || !file_exists("{$path}dashboards") || !is_dir("{$path}dashboards")) { continue; }
            msgDebug("\nFound path {$path}dashboards");
            if (!getModuleCache($module, 'properties', 'status')) { continue; } // skip if module not loaded
            $thelist = scandir("{$path}dashboards");
            msgDebug("\nIn loadDashboards with theList read from disk = ".print_r($thelist, true));
            foreach ($thelist as $dashID) {
                if ($dashID == '.' || $dashID == '..' || !is_dir("{$path}/dashboards/$dashID")) { continue; }
                $myDash = getDashboard($dashID);
                if (isset($myDash->hidden) && $myDash->hidden) { continue; }
                if (isset($myDash->settings)) { msgDebug("\nmyDash defaults = ".print_r($myDash->settings, true)); }
                if ($myDash) {
                    $category = isset($myDash->category) ? lang($myDash->category) : lang('misc');
                    if (validateDashboardSecurity($myDash)) { // security check dashboard
                        msgDebug("\nPassed Security");
                        $output[$category][] = [
                            'id'         => $dashID,
                            'title'      => $myDash->lang['title'],
                            'description'=> $myDash->lang['description'],
                            'module'     => $module,
                            'security'   => $myDash->security,
                            'active'     => in_array($dashID, $loaded) ? true : false];
                    }
                }
            }
        }
        ksort($output); // start sorting everything
        return $output;
    }
    
    /**
     * Validates the users ability to see a dashboard
     * @param type $opts
     * @return bool
     */
    private function autenticateUser($opts=[])
    {
        $role = getUserCache('profile', 'userRole');
        $user = getUserCache('profile', 'userID');
        msgDebug("\nEntering autenticateUser user $user and role = $role and opts = ".print_r($opts, true));
        if (empty($opts['users']) || empty($opts['roles']))                     { msgDebug(" ... and returning false: empty"); return; }
        if (in_array(-1, $opts['roles'])) { msgDebug(" ... and returning true: matched roles");return true; }
        if (in_array(getUserCache('profile', 'userRole'), $opts['roles'])) { msgDebug(" ... and returning true: matched roles");return true; }
        if (in_array(-1, $opts['users'])) { msgDebug(" ... and returning true: matched users");return true; }
        if (in_array(getUserCache('profile', 'userID'),  $opts['users'])) { msgDebug(" ... and returning true: matched users");return true; }
        if (!empty($opts['secID']) && !empty(getUserCache('role', 'security', $opts['secID']))){ return true; }
    }
    
    private function viewDash($data=[], $meta=[])
    {
        msgDebug("\nEntering viewDash with meta = ".print_r($meta, true));
        $dashID = $this->myDash->code;
        $output = ['type'=>'divHTML', // core structure
            'divs'=>[
                'divBOF' => ['order'=> 1,'type'=>'html','html'=>"<div>"],
                'divEOF' => ['order'=>99,'type'=>'html','html'=>"</div>"]]];
        metaPopulate($this->myDash->struc, $meta);
        $this->viewDashAdmin($output, $dashID); // Build the admin section
        // Build the contents
        $this->viewDashDiv($output, $data, $dashID);
//msgDebug("\nresults from viewDash = ".print_r($output, true));
        return $output;
    }
    private function viewDashAdmin(&$output, $dashID)
    {
        $admFlds = $admKeys = [];
        $jsReady = '';
        foreach ($this->myDash->struc as $field => $vals) {
            if (!empty($vals['admin'])) { continue; } //only allow user enabled options
            $admKeys[] = $dashID.$field;
            if (in_array($vals['attr']['type'], ['spinner'])) {
                $jsReady .= "dashDelay('$dashID', 0, '{$dashID}$field');\n";
                $admFlds[$dashID.$field] = array_replace_recursive($vals, ['position'=>'after','events'=>['onChange'=>"jqBiz('#{$dashID}$field').keyup();"]]);
            } elseif (in_array($vals['attr']['type'], ['select', 'selNoYes'])) {
                $admFlds[$dashID.$field] = array_replace_recursive($vals, ['position'=>'after','events'=>['onChange'=>"dashSubmit('{$dashID}', 0);"]]);
            } else {
                $admFlds[$dashID.$field] = array_replace_recursive($vals, ['position'=>'after']);
                $addSave = true; // need to add the save button since the form doesn't auto-submit
            }
        }
        if (!empty($addSave)) {
            $admKeys[] = $dashID.'Save';
            $admFlds[$dashID.'Save'] = ['attr'=>['type'=>'button','value'=>lang('save')],'events'=>['onClick'=>"dashSubmit('{$dashID}', 0);"]];
        }
        if (!empty($admKeys)) {
            $jsReady .= "jqBiz('#{$dashID}Attr').keypress(function(event) { var keycode=event.keyCode?event.keyCode:event.which; if (keycode=='13') { dashSubmit('{$dashID}', 0); } });\n";
//            $jsReady .= "ajaxForm('{$dashID}Form');\n";
            $output['divs']['admin'] = ['order'=>10,'styles'=>['display'=>'none'],'attr'=>['id'=>"{$dashID}_attr"],'type'=>'divs','divs'=>[
                'frmBOF'=> ['order'=>20,'type'=>'form',  'key' =>"{$dashID}Form"],
                'body'  => ['order'=>50,'type'=>'fields','keys'=>$admKeys],
                'frmEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]];
            $output['forms']["{$dashID}Form"] = ['attr'=>['type'=>'form','action'=>'']];
            $output['fields'] = $admFlds;
        }
        if (!empty($jsReady)) { $output['jsReady']['admin'] = $jsReady; }
    }

    private function viewDashDiv(&$output, $data, $dashID)
    {
        if (!empty($data['legend']) && empty(getModuleCache('bizuno', 'settings', 'general', 'hide_filters', 0))) {
//            $output['divs']['head'] = ['order'=>40,'type'=>'html','html'=>$data['legend']];
        }
        if (empty($data['type'])) { $data['type'] = 'bizWay'; }
        switch ($data['type']) {
            case 'gChart':  googleChart($output, $dashID, $data);  break;
            case 'gColumn': googleColumn($output, $dashID, $data); break;
            case 'gTable':  googleTable($output, $dashID, $data);  break;
            default:
                if       (!empty($data['lists']))  { // for lists
                    $output['divs']['body']= ['order'=>50, 'type'=>'list', 'key'=>$dashID];
                    $output['lists']       = [$dashID=>$data['lists']];
                } elseif (!empty($data['html']))   { // for html strings
                    $output['divs']['body']= ['order'=>50, 'type'=>'html', 'html'=>$data['html']];
                } elseif (!empty($data['data']))   { // structure 
                    $output = array_replace_recursive($output, $data['data']);
                } else {
                    $output['divs']['body']= ['order'=>50, 'type'=>'html', 'html'=>"<span>".lang('no_results')."</span>"];
                }
        }
        if (!empty($data['jsHead'])) { $output['jsHead']['init'] = "\n".$data['jsHead']; }
        if (!empty($data['jsBody'])) { $output['jsBody']['init'] = "\n".$data['jsBody']; }
        if (!empty($data['jsReady'])){ $output['jsReady']['init']= "\n".$data['jsReady']; }
    }
}
