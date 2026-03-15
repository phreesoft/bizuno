<?php
/*
 * Module Bizuno main methods
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/bizuno/main.php
 */

namespace bizuno;

class bizunoMain
{
    public $moduleID = 'bizuno';
    public $lang;

    function __construct()
    {
    }

    /**
     * generates the structure for the home page and any main menu dashboard page
     * @param array $layout - structure coming in
     */
    public function bizunoHome(&$layout)
    {
        $mIDdef= getUserCache('profile', 'userID') ? 'home' : 'portal';
        $title = getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        if (empty($title)) { $title = portalGetBizIDVal(getUserCache('business', 'bizID'), 'title'); }
        $menuID= clean('menuID', ['format'=>'text','default'=>$mIDdef], 'get');
        $data  = ['title'=>"$title - ".getModuleCache('bizuno', 'properties', 'title'),
            'jsHead'=>['menu_id'=>"var menuID='$menuID';"]];
        viewDashJS($data);
        $layout = array_replace_recursive(viewMain(), $data);
    }

    /**
     *
     * @global type $io
     * @param type $layout
     * @return type
     */
    public function attachRows(&$layout=[])
    {
        global $io;
        if (!validateAccess('profile', 1)) { return; }
        $mID   = clean('mID',   'cmd',     'get');
        $prefix= clean('prefix','filename','get');
        if (empty($mID)) { msgAdd('Bad ID'); return; }
        $path  = getModuleCache($mID,'properties','attachPath')."$prefix";
        $rows  = $io->fileReadGlob($path, $io->getValidExt('file'));
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }

    public function dashboard(&$layout=[])
    {
        $data  = [];
        viewDashJS($data);
        $menuID= clean('menuID', ['format'=>'text','default'=>'home'], 'get');
        $data['jsHead']['menu_id'] = "var menuID='$menuID';";
        $cols  = getColumns();
        $width = round(100/$cols, 0);
        $html  = '';
        for ($i=0; $i<$cols; $i++) { $html .= "\n".'<div style="width:'.$width.'%"></div>'; }
        $data['divs']['bodyDash'] = ['order'=>50,'styles'=>['clear'=>'both'],'attr'=>['id'=>'dashboard'],'type'=>'html','html'=>$html];
        $layout = array_replace_recursive(viewMain(), $data);
    }

    /**
     * Used to refresh session timer to keep log in alive. Forces sign off after 8 hours if no user actions are detected.
     */
    public function sessionRefresh(&$layout) {
    } // nothing to do, just reset session clock

    /**
     * generates the pop up encryption form
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function encryptionForm(&$layout) {
        if (!validateAccess('profile', 1)) { return; }
        $icnSave= ['icon'=>'save','events'=>['onClick'=>"jsonAction('bizuno/main/encryptionSet', 0, jqBiz('#pwEncrypt').val());"]];
        $inpEncr= ['options'=>['value'=>"''"],'attr'=>['type'=>'password','value'=>'']];
        $html   = lang('msg_enter_encrypt_key').'<br />'.html5('pwEncrypt', $inpEncr).html5('', $icnSave);
        $js     = "jqBiz('#winEncrypt').keypress(function(event) {
    var keycode = (event.keyCode ? event.keyCode : event.which);
    if (keycode==13) jsonAction('bizuno/main/encryptionSet', 0, jqBiz('#pwEncrypt').val());
});
bizFocus('pwEncrypt');";
        $data = ['type'=>'divHTML',
            'divs'   => ['divEncrypt'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['init'=>$js]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Validates and sets the encryption key, if successful
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function encryptionSet(&$layout)
    {
        if (!validateAccess('profile', 1)) { return; }
        $error  = false;
        $key    = clean('data', 'password', 'get');
        $encKey = getModuleCache('bizuno', 'encKey', false, false, '');
        if (!$encKey) { return msgAdd(lang('err_encryption_not_set', $this->moduleID)); }
        if ($key && $encKey) {
            $stack = explode(':', $encKey);
            if (sizeof($stack) != 2) { $error = true; }
            if (md5($stack[1] . $key) <> $stack[0]) { $error = true; }
        } else { $error = true; }
        if ($error) { return msgAdd(lang('err_login_failed')); }
        setUserCache('profile', 'admin_encrypt', $key);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winEncrypt'); jqBiz('#ql_encrypt').hide();"]]);
    }

    /*
     * Downloads a file to the user
     */
    public function fileDownload()
    {
        global $io;
        if (!validateAccess('phreeform', 1, true)) { return; } // changed to 'phreeform' security to enable download across Bizuno modules
        $path = clean('pathID', 'path', 'get');
        $file = clean('fileID', 'file', 'get');
        $parts = explode(":", $file, 2);
        if (sizeof($parts) > 1) { // if file contains a prefix the format will be: prefix:prefixFilename
            $dir  = $path.$parts[0];
            $file = str_replace($parts[0], '', $parts[1]);
        } else {
            $dir  = $path;
            $file = $file;
        }
        msgLog(lang('download').' - '.$file);
        msgDebug("\n".lang('download').' - '.$file);
        $io->download('file', $dir, $file);
    }

    /**
     * Deletes a file from the myBiz folder
     * @param array $layout - structure coming in
     */
    public function fileDelete(&$layout=[])
    {
        global $io;
        $secID= clean('secID','cmd', 'get');
        $dgID = clean('rID',  'text','get');
        $file = clean('data', 'text','get');
        if (!validateAccess(!empty($secID)?$secID:'admin', 4)) { return; }
        msgDebug("\nDeleting dgID = $dgID and file = $file");
        $io->fileDelete($file);
        msgLog(lang('delete').' - '.$file);
        msgDebug("\n".lang('delete').' - '.$file);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval',
            'actionData'=>"var row=jqBiz('#$dgID').datagrid('getSelected');
var idx=jqBiz('#$dgID').datagrid('getRowIndex', row);
jqBiz('#$dgID').datagrid('deleteRow', idx);"]]);
    }
}
