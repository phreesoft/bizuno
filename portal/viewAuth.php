<?php
/**
 * Bizuno Portal - Handles Sign in
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
 * @version    7.x Last Update: 2025-12-01
 * @filesource /portal/viewAuth.php
 */

namespace bizuno;

class portalViewAuth
{
    private $errors  = '';
    public  $lang;

    function __construct()
    {
        $iso = !empty($_COOKIE['bizunoEnv']['lang']) ? $_COOKIE['bizunoEnv']['lang'] : 'en_US';
        $lang = [];
        require(BIZUNO_FS_LIBRARY . "locale/$iso/portal.php"); // replace $lang
        $this->lang = $lang;
    }
    public function login(&$layout=[])
    {
        global $db;
        if (function_exists("\\bizuno\\portalLogin")) { return portalLogin($layout, $this->errors, $this->lang); } // hook for customization
        msgDebug("\nEntering portalView::guest/login.");
        // if POST vars are set then try to log in else show form
        if (isset($_POST['bizUser']) && isset($_POST['bizPass'])) {
            msgDebug("\nCredentials sent, trying to validate.");
            if ($this->validateUser($layout)) { msgDebug("\nUser validated, reloading!"); $layout = ['type'=>'guest','jsReady'=>['reload'=>"location.reload();"]]; return; } // if validated, return to load home page
        }
        // Show login form
        $src = BIZUNO_LOGO;
        if (dbTableExists(BIZUNO_DB_PREFIX.'configuration') && $db->connected) { // getModuleCache('bizuno', 'settings', 'company', 'logo');
            $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
            $stg = !empty($cfg) ? json_decode($cfg, true) : [];
            if (!empty($stg['settings']['company']['logo'])) { $src = BIZUNO_URL_FS.BIZUNO_BIZID."/images/{$stg['settings']['company']['logo']}"; }
        }
        $logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>$src,'height'=>48]];
        $html = '<div>'.html5('', $logo).'</div>
    <div class="text">'.$this->lang['welcome'].'</div>
    <div class="field"><input type="text" name="bizUser" placeholder="'.$this->lang['email'].'"></div>
    <div class="field"><input type="password" name="bizPass" placeholder="'.$this->lang['password'].'"></div>
    <div class="field"><select name="bizLang"><option value="en_US">English (US)</option></select></div>
    <button>'.$this->lang['signin'].'</button>
    <div><a href="'.BIZUNO_URL_PORTAL.'?bizRt=portal/api/lostPW">'.$this->lang['password_lost'].'</a></div>'; // removed icons <div class="fas fa-envelope"></div> AND <div class="fas fa-lock"></div>
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $ajax = clean('ajax', 'boolean', 'get');
        if (!empty($ajax)) { // It's an ajax call so we need to reload page to reach login screen
            $layout = ['type'=>'guest','jsReady'=>['reload'=>"location.reload();"]];
        }  else {
            $layout = ['type'=>'guest',
                'divs'  => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                    'formBOF' => ['order'=>20,'type'=>'form',  'key' =>'frmLogin'],
                    'body'    => ['order'=>51,'type'=>'html',  'html'=>$html],
                    'formEOF' => ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
                'forms' => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
                'jsReady'=>['init'=>"appendPrefs();"]];
        }
    }
    private function validateUser(&$layout=[])
    {
        msgDebug("\nEntering validateUser.");
        $email= clean('bizUser', 'email', 'post'); // email address
        $user = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id', 'primary_name'], "ctype_u='1' AND email='$email'");
        if (empty($user['id']) || empty($_POST['bizPass'])) {
            $this->errors = $this->lang['err_invalid_creds'];
            return;
        }
        $encPW   = getMetaContact($user['id'], 'user_auth');
        $profile = getMetaContact($user['id'], 'user_profile');
        $peppered= hash_hmac('sha256', $_POST['bizPass'], BIZUNO_KEY);
        if (password_verify($peppered, $encPW['value'])) {
            $user = ['userID'=>$user['id'], 'psID'=>0, 'userEmail'=>$email, 'userRole'=>$profile['role_id'], 'userName'=>$user['primary_name']];
            setUserCookie($user);
            $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
            return true;
        }
        $this->errors = $this->lang['err_invalid_creds'];
    }
}