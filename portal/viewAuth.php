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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade Bizuno to newer
 * versions in the future. If you wish to customize Bizuno for your
 * needs please contact PhreeSoft for more information.
 *
 * @name Bizuno ERP
 * @author Dave Premo, PhreeSoft <support@phreesoft.com>
 * @copyright 2008-2026, PhreeSoft, Inc.
 * @license https://www.gnu.org/licenses/agpl-3.0.txt
 * @version 7.x Last Update: 2026-02-25
 * @filesource /portal/viewAuth.php
 */
namespace bizuno;

use lbuchs\WebAuthn\WebAuthn;
use lbuchs\WebAuthn\Binary\ByteBuffer;

class portalViewAuth
{
    private $webauthn;
    private $errors = '';
    public  $lang;
    private $logo;

    function __construct()
    {
msgTrap();
        global $db;
        $src = BIZUNO_LOGO;
        $iso = !empty($_COOKIE['bizunoEnv']['lang']) ? $_COOKIE['bizunoEnv']['lang'] : 'en_US';
        $lang = [];
        require(BIZUNO_FS_LIBRARY . "locale/$iso/portal.php");
        $this->lang = $lang;
        // Show login form
        if (dbTableExists(BIZUNO_DB_PREFIX.'configuration') && $db->connected) {
            $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
            $stg = !empty($cfg) ? json_decode($cfg, true) : [];
            if (!empty($stg['settings']['company']['logo'])) {
                $src = BIZUNO_URL_FS . BIZUNO_BIZID . "/images/{$stg['settings']['company']['logo']}";
            }
        }
        $this->logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>$src,'height'=>48]];
    }

    /**
     * Main method to process sign in process
     * @global type $db
     * @param type $layout
     * @return type
     */
    public function login(&$layout = [])
    {
        global $db;
        msgDebug("\nEntering portalViewAuth::guest/login.");
        if (function_exists("\\bizuno\\portalLogin")) { return portalLogin($layout, $this->errors, $this->lang); }
        
        if (!isset($_POST['bizUser']) || empty($_POST['bizUser'])) { // Step 1: initial load of page, show intro
            $this->viewIntro($layout);
        } elseif ( isset($_POST['bizUser']) && !isset($_POST['bizPass'])) { // Step 2: User name has been entered
            msgDebug("\nUser email sent, make sure user exists in db, on to step 2.");
            $email = clean('bizUser', 'email', 'post');
            $user = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id', 'primary_name'], "ctype_u='1' AND email='$email'");
            if (empty($user['id'])) {
                $this->errors = $this->lang['err_invalid_creds'];
                $this->viewIntro($layout);
            } else {
                msgDebug("\nEmail exists, checking if passkey is enabled.");
                $this->viewAuth($layout, $email);
                $creds = getMetaContact($user['id'], 'webauthn_credentials');
                if (empty($creds)) { // Add sign up for passkey language
                    $layout['divs']['body']['divs']['body']['html'] .= $this->addPasskey();
                } else {
                    $layout['divs']['body']['divs']['body']['html'] .= $this->showPasskey();
                }
            }
        } elseif (isset($_POST['bizUser']) && isset($_POST['bizPass'])) { // Step 3: Password has been entered (not using webauthn)
            msgDebug("\nCredentials sent, trying to validate.");
            
            // Three options, No 2PA, 2PA with code, 2PA with passkey
            
            // No 2PA, just validate user and carry on
            
            // 2PA with code, generate code, send via email to user, show code screen
            
            // 2PA with passkey, ??? maybe popup, maybe button, where in login sequence? what does Amazon do? google?
            
            // Add other possibilities, Google, Facebook, ???
            
            
            if ($this->validateUser($layout)) { // if validated, return to load home page
                msgDebug("\nUser validated, reloading!");
                $layout = ['type'=>'guest','jsReady'=>['reload'=>"location.reload();"]];
            }
        } else {
            $this->viewIntro($layout);
        }
    }

    /**
     * Handles Step 1: User name
     * @param array $layout
     */
    public function viewIntro(&$layout=[])
    {
        $jsAuth = "";
        $htmlExtra = "";
/*      if (BIZUNO_WEBAUTHN_ENABLED) {
            $jsAuth    = $this->viewAuthnJS();
            $htmlExtra = '
<script src="https://unpkg.com/@simplewebauthn/browser@13/dist/bundle/index.umd.min.js"></script>
<input type="hidden" name="bizPasskey" value="1">
<button id="btn-passkey-login" type="button">Login with Passkey / Biometric</button>
<button id="btn-register-passkey" type="button" style="display:none;">Register Passkey</button>';
        }
 */
        $html = '<div>'.html5('', $this->logo).'</div>
<div class="text">'.$this->lang['welcome'].'</div>
<div class="field"><input type="text" name="bizUser" placeholder="'.$this->lang['email'].'"></div>
<div class="field"><select name="bizLang"><option value="en_US">English (US)</option></select></div>
<button>'.$this->lang['signin'].'</button>' . $htmlExtra;
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs' => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form', 'key' =>'frmLogin'],
                'body' => ['order'=>51,'type'=>'html', 'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html', 'html'=>"</form>"]]]],
            'forms' => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
            'jsReady'=> ['authn'=>$jsAuth, 'init'=>"appendPrefs();"]];
    }

    /**
     * Handles Step 2: Authentication
     * @param array $layout
     * @param string $email
     */
    public function viewAuth(&$layout=[], $email='')
    {
        $jsAuth = "";
        $htmlExtra = "";
        if (BIZUNO_WEBAUTHN_ENABLED) {
            $jsAuth    = $this->viewAuthnJS();
            $htmlExtra = '<script src="https://unpkg.com/@simplewebauthn/browser@13/dist/bundle/index.umd.min.js"></script>'."\n";
        }
        $html = '<div>'.html5('', $this->logo).'</div>
<div class="text">'.$this->lang['please_auth'].'</div>
<div class="field"><input type="hidden" name="bizUser" value="'.$email.'"></div>
<div class="field"><input type="password" name="bizPass" placeholder="'.$this->lang['password'].'"></div>
<button>'.$this->lang['signin'].'</button>
' . $htmlExtra . '
<div><a href="'.BIZUNO_URL_PORTAL.'?bizRt=portal/api/lostPW">'.$this->lang['password_lost'].'</a></div>';
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs' => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form', 'key' =>'frmLogin'],
                'body' => ['order'=>51,'type'=>'html', 'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html', 'html'=>"</form>"]]]],
            'forms' => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
            'jsReady'=> ['authn'=>$jsAuth]];
    }

    /**
     * Adds the passkey HTML if needed to get user to sign up.
     * @return string
     */
    public function addPasskey()
    {
        msgDebug("\nEntering addPasskey");
        if (!BIZUNO_WEBAUTHN_ENABLED) { return ''; }
        return '<hr />
<div class="info">'.'Would you like to add a <strong>passkey</strong> for faster, more secure, passwordless login using your fingerprint, face ID, or device PIN?'.'</div>
<div class="info">'.'This takes ~15 seconds and makes future logins much easier and more secure.'.'</div>
<input type="hidden" name="addPasskey" value="1">
<button id="btn-register-passkey" type="button">'.'Yes — Set Up Passkey Now'.'</button>';
    }
    
    /**
     * Handles the HTML to sign in with Passkey
     * @return string
     */
    public function showPasskey()
    {
        msgDebug("\nEntering showPasskey");
        if (!BIZUNO_WEBAUTHN_ENABLED) { return ''; }
        return '<hr />
<input type="hidden" name="usePasskey" value="1">
<button id="btn-passkey-login" type="button">Sign In with Passkey / Biometric</button>';
    }
    
    private function viewAuthnJS()
    {
        return "const { startRegistration, startAuthentication } = SimpleWebAuthnBrowser;
// Register passkey (shown after password login or in profile)
document.getElementById('btn-register-passkey')?.addEventListener('click', async () => {
  try {
    const resp = await fetch('".BIZUNO_URL_PORTAL."?bizRt=portal/api/webauthnRegisterOptions', { method: 'POST' });
    if (!resp.ok) throw new Error('Options failed');
    const opts = await resp.json();
    const cred = await startRegistration(opts);
    const verify = await fetch('".BIZUNO_URL_PORTAL."?bizRt=portal/api/webauthnRegisterVerify', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(cred)
    });
    if (verify.ok) {
      alert('Passkey registered successfully!');
      location.reload();
    } else {
      alert('Registration failed');
    }
  } catch (err) {
    console.error(err);
    alert('Error: ' + err.message);
  }
});
// Login with passkey
document.getElementById('btn-passkey-login')?.addEventListener('click', async () => {
  const email = document.querySelector('input[name=\"bizUser\"]')?.value.trim();
  if (!email) return alert('Please enter your email first');
  try {
    const resp = await fetch('".BIZUNO_URL_PORTAL."?bizRt=portal/api/webauthnAuthOptions?bizUser=' + encodeURIComponent(email), { method: 'POST' });
    if (!resp.ok) throw new Error(await resp.text());
    const opts = await resp.json();
    const assertion = await startAuthentication(opts);
    const verify = await fetch('".BIZUNO_URL_PORTAL."?bizRt=portal/api/webauthnAuthVerify', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(assertion)
    });
    if (verify.ok) {
      location.reload();
    } else {
      alert('Authentication failed');
    }
  } catch (err) {
    console.error(err);
    alert('Error: ' + err.message);
  }
});";
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
            msgDebug("\nUser validated, setting cookie.");
            $user = ['userID'=>$user['id'], 'psID'=>0, 'userEmail'=>$email, 'userRole'=>$profile['role_id'], 'userName'=>$user['primary_name']];
            setUserCookie($user);
            $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
            return true;
        }
        $this->errors = $this->lang['err_invalid_creds'];
    }
}