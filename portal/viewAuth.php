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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-02-20
 * @filesource /portal/viewAuth.php
 */

namespace bizuno;

use Webauthn\Server;
use Webauthn\MetadataService\MetadataStatement;
use Webauthn\MetadataService\MetadataStatementRepository;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialSourceRepository;

class portalViewAuth
{
    private $server;
    private $db; // your DB connection or Bizuno model
    private $errors  = '';
    public  $lang;

    function __construct()
    {
        $iso = !empty($_COOKIE['bizunoEnv']['lang']) ? $_COOKIE['bizunoEnv']['lang'] : 'en_US';
        $lang = [];
        require(BIZUNO_FS_LIBRARY . "locale/$iso/portal.php"); // replace $lang
        $this->lang = $lang;
        // Initialize WebAuthn server once
//        $rp = new PublicKeyCredentialRpEntity('Bizuno ERP', 'yourdomain.com');
//        $this->server = new Server($rp, /* credential repository */, /* other services */);
    }
    public function login(&$layout=[])
    {
        global $db;
        if (function_exists("\\bizuno\\portalLogin")) { return portalLogin($layout, $this->errors, $this->lang); } // hook for customization
        msgDebug("\nEntering portalViewAuth::guest/login.");
        // if POST vars are set then try to log in else show form
        if (isset($_POST['bizUser']) && isset($_POST['bizPass'])) {
            msgDebug("\nCredentials sent, trying to validate.");
            if ($this->validateUser($layout)) { // if validated, return to load home page
                msgDebug("\nUser validated, reloading!");
                $layout = ['type'=>'guest','jsReady'=>['reload'=>"location.reload();"]];
                return;
            }
        }
        // Show login form
        $src = BIZUNO_LOGO;
        if (dbTableExists(BIZUNO_DB_PREFIX.'configuration') && $db->connected) { // getModuleCache('bizuno', 'settings', 'company', 'logo');
            $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
            $stg = !empty($cfg) ? json_decode($cfg, true) : [];
            if (!empty($stg['settings']['company']['logo'])) { $src = BIZUNO_URL_FS.BIZUNO_BIZID."/images/{$stg['settings']['company']['logo']}"; }
        }
        $logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>$src,'height'=>48]];
        $jsHead = "
     const { startRegistration, startAuthentication } = SimpleWebAuthnBrowser;
     // Registration (after password login, or in profile settings)
     document.getElementById('btn-register-passkey')?.addEventListener('click', async () => {
       const response = await fetch('/bizuno/portal/webauthn/register/options', { method: 'POST' });
       const opts = await response.json();
       const credential = await startRegistration(opts);
       await fetch('/bizuno/portal/webauthn/register/verify', {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify(credential)
       });
       alert('Passkey registered!');
     });
     // Login
     document.getElementById('btn-passkey-login')?.addEventListener('click', async () => {
       const response = await fetch('/bizuno/portal/webauthn/auth/options', { method: 'POST' });
       const opts = await response.json();
       const assertion = await startAuthentication(opts);
       const verify = await fetch('/bizuno/portal/webauthn/auth/verify', {
         method: 'POST',
         headers: { 'Content-Type': 'application/json' },
         body: JSON.stringify(assertion)
       });
       if (verify.ok) {
         window.location = '/bizuno/portal/dashboard'; // or wherever after login
       } else {
         alert('Authentication failed');
       }
     });
";
        $authn= ['type'=>'html','html'=>'<script src="https://unpkg.com/@simplewebauthn/browser@9/dist/simplewebauthn-browser.min.js"></script>'];
        $html = '<div>'.html5('', $logo).'</div>
    <div class="text">'.$this->lang['welcome'].'</div>
    <div class="field"><input type="text" name="bizUser" placeholder="'.$this->lang['email'].'"></div>
    <div class="field"><input type="password" name="bizPass" placeholder="'.$this->lang['password'].'"></div>
    <div class="field"><select name="bizLang"><option value="en_US">English (US)</option></select></div>
    <button>'.$this->lang['signin'].'</button>
    <button id="btn-passkey-login" type="button">Login with Passkey / Biometric</button>
    <button id="btn-register-passkey" type="button" style="display:none;">Register Passkey</button>
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
                'forms'  => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
                
    // $authn probably won't work, needs to be added to head as html but will most likely be inside of <script> tag
                
                'jsHead' => ['authn'=>$authn, 'head'=>$jsHead],
                'jsReady'=> ['init'=>"appendPrefs();"]];
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
            msgDebug("\nUser validated, setting cookie.");
            $user = ['userID'=>$user['id'], 'psID'=>0, 'userEmail'=>$email, 'userRole'=>$profile['role_id'], 'userName'=>$user['primary_name']];
            setUserCookie($user);
            $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
            return true;
        }
        $this->errors = $this->lang['err_invalid_creds'];
    }

    
// Need to remove composer remove symfony/cache from server 4, not needed???
// Use a single meta_key for all credentials per user, e.g. webauthn_credentials (recommended for simplicity)
// Example stored value (JSON):
/*
[
  {
    "credentialId": "base64url_encoded_credential_id_here",
    "type": "public-key",
    "transports": ["usb", "internal"],
    "attestationType": "none",
    "credentialPublicKey": "base64url_encoded_public_key_here",
    "counter": 42,
    "userHandle": "base64url_encoded_user_handle_if_needed",
    "otherFields": {...}
  },
  {
    "...": "... second credential ..."
  }
]
 */
    
    public function findOneByCredentialId(string $credentialId): ?PublicKeyCredentialSource
    {
        // $credentialId is binary string → convert to base64url for safe comparison/storage
        $idBase64 = base64_encode($credentialId);
        $idBase64 = str_replace(['+', '/'], ['-', '_'], $idBase64);
        $idBase64 = rtrim($idBase64, '=');

        // Fetch the JSON array for the user (you'll need user_id from context or separate lookup)
        // For this method, you may need to scan all users' meta or have an index/helper table
        // Simplest (but slower): query all 'webauthn_credentials' and search in PHP

        $metaRows = $this->db->get_results("SELECT contact_id, meta_value FROM bizuno_contact_meta WHERE meta_key = 'webauthn_credentials'");
        foreach ($metaRows as $row) {
            $credentials = json_decode($row->meta_value, true) ?? [];
            foreach ($credentials as $credData) {
                if (($credData['credentialId'] ?? '') === $idBase64) {
                    // Rehydrate
                    return PublicKeyCredentialSource::createFromArray($credData);
                }
            }
        }
        return null;
    }

    public function findAllForUserEntity(PublicKeyCredentialUserEntity $userEntity): array
    {
        $userId = $userEntity->getId(); // binary
        $userIdInt = 0; /* convert your binary userHandle back to Bizuno contact_id */;
        $meta = $this->db->get_row($this->db->prepare(
            "SELECT meta_value FROM bizuno_contact_meta WHERE contact_id = %d AND meta_key = 'webauthn_credentials'",
            $userIdInt
        ));
        if (!$meta) { return []; }
        $data = json_decode($meta->meta_value, true) ?? [];
        $sources = [];
        foreach ($data as $item) { $sources[] = PublicKeyCredentialSource::createFromArray($item); }
        return $sources;
    }

    // Required: save during registration
    public function saveCredentialSource(PublicKeyCredentialSource $publicKeyCredentialSource): void
    {
        $userIdInt = 0; /* extract contact_id from $publicKeyCredentialSource->getUserHandle() */
        $meta = $this->db->get_row($this->db->prepare(
            "SELECT meta_value FROM bizuno_contact_meta WHERE contact_id = %d AND meta_key = 'webauthn_credentials'",
            $userIdInt
        ));
        $credentials = $meta ? json_decode($meta->meta_value, true) ?? [] : [];
        // Add/update (usually just append, as credentialId is unique)
        $credentials[] = $publicKeyCredentialSource->jsonSerialize();
        $json = json_encode($credentials);
        if ($meta) {
            $this->db->update('bizuno_contact_meta', ['meta_value' => $json], ['contact_id' => $userIdInt, 'meta_key' => 'webauthn_credentials']);
        } else {
            $this->db->insert('bizuno_contact_meta', ['contact_id' => $userIdInt, 'meta_key' => 'webauthn_credentials', 'meta_value' => $json]);
        }
    }

    // GET/POST /webauthn/register/options
    public function registerOptions()
    {
        $user = $this->getCurrentUser(); // after password login
        $userEntity = new PublicKeyCredentialUserEntity( $user['email'], $user['id'], $user['display_name'] );
        $options = $this->server->generatePublicKeyCredentialCreationOptions( $userEntity,
            PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            [new PublicKeyCredentialParameters()] );// algorithms
        $_SESSION['webauthn_register'] = $options; // temp store challenge
        echo json_encode($options->jsonSerialize());
    }

    // POST /webauthn/register/verify
    public function registerVerify()
    {
        $options = $_SESSION['webauthn_register'];
        $credential = $this->server->createCredential($options, $requestBody); // parse JSON input
        // Save to DB (your bizuno_webauthn_credentials table)
        $this->saveCredential($credential, $userId);
        unset($_SESSION['webauthn_register']);
        echo json_encode(['success' => true]);
    }

    // Similar for auth/options and auth/verify (use load credentials for user, generate assertion options, verify assertion)

}