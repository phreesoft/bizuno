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
 * @version    7.x Last Update: 2026-03-16
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
        langFillLabels($this->struc);
        $this->struc = array_replace($this->struc, $mail->struc); // bring in the mail settings
    }
    
    public function edit(&$layout=[])
    {
        msgTrap();
        if (empty(getUserCache('profile', 'userID'))) { return; }
        $metaVal = dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID'));
        msgDebug("\nRead meta pre process = ".print_r($metaVal, true));
        $rID  = metaIdxClean($metaVal); // need a rID since we enter directly from a menu selection
        $args = ['dom'=>'page', '_rID'=>$rID, '_table'=>'contacts', '_refID'=>getUserCache('profile', 'userID'), 'title'=>lang('edit_profile')];
        parent::editMeta($layout, 3, $args);
        msgDebug("\nAfter editMeta, layout = ".msgPrint($layout));
        // Add security tab
        $secHTML = $this->addPasskey();
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['options']['divs']['security'] = ['order'=>80,'type'=>'panel','classes'=>['block33'],'key'=>'security'];
        $layout['panels']['security']= ['order'=>80,'label'=>lang('security'),'type'=>'html','html'=>$secHTML['html']];
        $layout['jsBody']['webauthn'] = "jqBiz.cachedScript('https://unpkg.com/@simplewebauthn/browser@13/dist/bundle/index.umd.min.js');";
        $layout['jsHead']['passkey'] = $secHTML['jsHead'];
        $layout['tabs']["tab{$this->domSuffix}"]['divs']['reminders'] = ['order'=>50,'label'=>lang('reminders', $this->moduleID),'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=bizuno/reminder/manager'"]];
        unset($layout['toolbars']["tb{$this->domSuffix}"]['icons']['new'], $layout['toolbars']["tb{$this->domSuffix}"]['icons']['copy']); // Don't allow new, copy here
        $layout['fields']['gmail_app_pw2']['attr']['type'] = 'hidden'; // not used for profile
        $layout['fields']['gmail_app_pw3']['attr']['type'] = 'hidden';
        $layout['fields']['gmail_app_pw4']['attr']['type'] = 'hidden';
    }
    
    private function addPasskey()
    {
        msgDebug("\nEntering addPasskey");
        if (!BIZUNO_WEBAUTHN_ENABLED) { return ''; }
        $html   = '<br />
<div>'.'Would you like to add a <strong>passkey</strong> for faster, more secure, passwordless login using your fingerprint, face ID, or device PIN?'.'</div>
<div>'.'This takes ~15 seconds and makes future logins much easier and more secure.'.'</div>
<input type="hidden" name="addPasskey" value="1">';
        $html  .= html5('btnConfirm', ['events'=>['onClick'=>"jsonAction('$this->moduleID/profile/passkeySetup')"],'attr'=>['type'=>'button','value'=>'Yes — Set Up Passkey Now']]); 
        $jsHead = "// Called when button is clicked → jsonAction already triggers passkeySetup
// But you need to handle the response and proceed to registration

// In your profile.js or inline script:
function handlePasskeySetupResponse(response) {
    if (!response.success) {
        alert(response.message || 'Setup failed');
        return;
    }
    const options = response.options;
    startRegistration(options)
        .then(credential => {
            // Send credential to verify endpoint
            return fetch('?bizRt=bizuno/profile/passkeyVerify', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(credential)
            });
        })
        .then(resp => resp.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                // Optional: refresh panel or hide setup prompt
                location.reload();
            } else {
                alert(result.message || 'Verification failed');
            }
        })
        .catch(err => {
            console.error(err);
            alert('Error during passkey setup: ' + err.message);
        });
}

// Hook into jsonAction response (Bizuno way)
bizJsonSuccess = function(data) {
    if (data.bizRt === 'bizuno/profile/passkeySetup') {
        handlePasskeySetupResponse(data);
    }
};";
        return ['jsHead'=>$jsHead, 'html'=>$html]; 
    }

    /**
     * Handles passkey registration setup (called via jsonAction from profile)
     * Returns PublicKeyCredentialCreationOptions as JSON
     */
public function passkeySetup(&$layout = [])
{
    msgTrap();

    if (!BIZUNO_WEBAUTHN_ENABLED) {
        msgAdd('Passkey support not enabled');
        return;
    }

    $userID   = getUserCache('profile', 'userID');
    $userName = getUserCache('profile', 'title') ?: 'User';

    msgDebug("\nEntering passkeySetup with userID = $userID");
    if (empty($userID)) {
        msgAdd('User not authenticated');
        return;
    }

    $rpName = 'Bizuno ERP';
    $rpId   = parse_url(BIZUNO_URL_PORTAL, PHP_URL_HOST);

    // Initialize without the 3rd param as we will handle conversion manually
    $webauthn = new \lbuchs\WebAuthn\WebAuthn($rpName, $rpId);
    $webauthn->timeout = 60;

    $userHandle = (string)$userID;

    // Get existing credentials
    $meta = getMetaContact($userID, 'webauthn_credentials');
    $existing = !empty($meta['value']) ? json_decode($meta['value'], true) : [];
    $exclude = [];

    foreach ($existing as $cred) {
        if (!empty($cred['id'])) {
            $exclude[] = new \lbuchs\WebAuthn\PublicKeyCredentialDescriptor(
                'public-key',
                \lbuchs\WebAuthn\Binary\ByteBuffer::fromBase64Url($cred['id'])
            );
        }
    }

    msgDebug("\nReady to try to create passskey args.");
    try {
        $options = $webauthn->getCreateArgs(
            $userHandle,
            $userName,
            $userName,
            null,
            null,
            $exclude
        );

        // --- MANUAL BINARY TO BASE64URL CONVERSION ---
        // This bypasses the library's internal MIME (=?BINARY?B?) serializer
        $toB64Url = function($buffer) {
            if ($buffer instanceof \lbuchs\WebAuthn\Binary\ByteBuffer) {
                $raw = $buffer->getBinaryString();
                return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
            }
            return $buffer;
        };

        $pk = $options->publicKey;
        
        // Convert the primary binary fields
        $pk->challenge = $toB64Url($pk->challenge);
        $pk->user->id  = $toB64Url($pk->user->id);

        // Convert excluded credential IDs if they exist
        if (!empty($pk->excludeCredentials)) {
            foreach ($pk->excludeCredentials as &$cred) {
                if (isset($cred->id)) {
                    $cred->id = $toB64Url($cred->id);
                }
            }
        }

        // Now that objects are strings, json_encode will be "clean"
        $jsonOptions = json_encode($options, JSON_UNESCAPED_SLASHES);

        msgDebug("\nFinal cleaned options: " . $jsonOptions);

        $layout = array_replace_recursive($layout, [
            'content' => [
                'action'     => 'eval',
                'actionData' => "performPasskeyRegistration($jsonOptions);"
            ]
        ]);

    } catch (\Exception $e) {
        msgDebug("\nWebAuthn getCreateArgs failed: " . $e->getMessage());
        $layout = array_replace_recursive($layout, [
            'content' => [
                'success' => false,
                'message' => 'Failed to generate registration options: ' . $e->getMessage()
            ]
        ]);
    }

    msgDebug("\nlayout at return = ".msgPrint($layout));
}

    
    

    /**
     * Verifies the passkey registration response (called from JS after startRegistration)
     */
    public function passkeyVerify()
    {
        global $currentUser;
        if (!BIZUNO_WEBAUTHN_ENABLED || empty($_SESSION['webauthn_challenge_reg'])) {
            return msgAdd('Invalid session or not enabled');
        }

        $userID = $currentUser['profile']['userID'] ?? 0;
        if (empty($userID)) {
            return msgAdd('Not authenticated');
        }

        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        if (!$data) {
            return msgAdd('Invalid request data');
        }

        try {
            $webauthn = new \lbuchs\WebAuthn\WebAuthn('Bizuno ERP', parse_url(BIZUNO_URL, PHP_URL_HOST));
            $credential = $webauthn->processCreate(
                $data['clientDataJSON'] ?? '',
                $data['attestationObject'] ?? '',
                BIZUNO_URL, // expected origin
                $_SESSION['webauthn_challenge_reg']
            );
            // Prepare credential data to store
            $credData = [
                'id'          => $credential->getCredentialId()->getBase64Url(),
                'publicKey'   => $credential->getPublicKey()->getBase64Url(),
                'signCount'   => $credential->getSignCount(),
                'transports'  => $credential->getTransports(),
                'userHandle'  => $credential->getUserHandle(),
                'attestation' => $credential->getAttestationType(),
            ];
            // Load existing and append
            $existing = getMetaContact($userID, 'webauthn_credentials'); // $this->getUserPasskeys($userID);
            $existing[] = $credData;

            // Save back to meta
            $jsonSave = json_encode($existing);
            saveMetaContact($userID, 'webauthn_credentials', $jsonSave);
            // Clean up session
            unset($_SESSION['webauthn_challenge_reg']);
            msgAdd('Passkey registered successfully!', 'success');
        } catch (\Exception $e) {
            msgDebug("\nPasskey registration error: " . $e->getMessage());
            msgAdd('Registration failed: ' . $e->getMessage());
        }
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
