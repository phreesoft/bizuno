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
 * @version 7.x Last Update: 2026-03-20
 * @filesource /portal/viewAuth.php
 */
namespace bizuno;

class portalViewAuth
{
    private $errors = '';
    public  $lang;
    private $logo;

    function __construct()
    {
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
        
// Temporary – run once for your user
/*
$userID = 808;  // e.g. 2 or whatever your contacts.id is
$settings = getUserAuthMethods($userID);
$settings['2fa'] = [
    'enabled' => true,
    'method'  => 'email'
    // leave other sub-keys empty for now
];
updateUserAuthMethods($userID, $settings);
msgDebug("\n2FA enabled manually for testing on user $userID");
 */
        
        
        msgDebug("\nEntering portalViewAuth::guest/login.");
        // Handle 2FA verification submission
        if (isset($_POST['step']) && $_POST['step'] === 'verify_2fa') {
            $email = clean('bizUser', 'email', 'post');
            $code  = clean('code', ['format'=>'integer','default'=>0], 'post');

            if ($this->verify2faCode($code, $email)) {
                // Success → complete login
                $user = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id', 'primary_name'], "ctype_u='1' AND email='$email'");
                if (!empty($user['id'])) {
                    $profile = getMetaContact($user['id'], 'user_profile');
                    $userData = [
                        'userID'    => $user['id'],
                        'psID'      => 0,
                        'userEmail' => $email,
                        'userRole'  => $profile['role_id'] ?? 0,
                        'userName'  => $user['primary_name']
                    ];
                    setUserCookie($userData);
                    msgDebug("\n2FA verified → user logged in");
                    $authMethods = getUserAuthMethods($userID);
                    if (empty($authMethods['passkeys'])) { // Redirect to a dedicated setup page instead of immediate reload
                        $layout = ['type'=>'guest', 'jsReady'=>['reload'=>"window.location.href = '".BIZUNO_URL_PORTAL."';"]];
                    } else {
                        $layout = ['type'=>'guest', 'jsReady'=>['reload'=>"location.reload();"]];
                    }
                    return;
                }
            } else {
                // Failure → redisplay form with error
                $this->errors = $this->lang['err_invalid_code'] ?? 'Invalid or expired code.';
                $this->view2faCodeEntry($layout, $email);
                return;
            }
        }
        
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
            }
        } elseif (isset($_POST['bizUser']) && isset($_POST['bizPass'])) {
            msgDebug("\nCredentials sent, trying to validate.");
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
    private function viewIntro(&$layout=[])
    {
        $html = '<div>'.html5('', $this->logo).'</div>
    <div class="text">'.$this->lang['welcome'].'</div>
    <div class="field"><input type="text" name="bizUser" placeholder="'.$this->lang['email'].'" autofocus></div>
    <div class="field"><select name="bizLang"><option value="en_US">English (US)</option></select></div>
    <button type="submit">'.$this->lang['next'].' →</button>';
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs' => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form', 'key' =>'frmLogin'],
                'body' => ['order'=>51,'type'=>'html', 'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html', 'html'=>"</form>"]]]],
            'forms' => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
            'jsReady'=> ['init'=>"appendPrefs();"]];
    }

    /**
     * Handles Step 2: Authentication
     * @param array $layout
     * @param string $email
     */
    public function viewAuth(&$layout=[], $email='')
    {
        $user = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id'], "ctype_u='1' AND email='".clean('bizUser','email','post')."'");
        $userID = $user['id'] ?? 0;
        $hasPasskeys = false;
        $jsAuth = "";
        $htmlExtra = "";
        $html = '<div>'.html5('', $this->logo).'</div>
    <div class="text">'.$this->lang['please_auth'].'<input type="hidden" name="bizUser" value="'.htmlspecialchars($email).'"></div>';
        $html .= '<div class="field"><input type="password" name="bizPass" placeholder="'.$this->lang['password'].'" '.($hasPasskeys ? 'autocomplete="off"' : '').'></div>
    <button type="submit">'.$this->lang['signin'].'</button><br />
    <div><a href="'.BIZUNO_URL_PORTAL.'?bizRt=portal/api/lostPW">'.$this->lang['password_lost'].'</a></div>';
        $html .= $htmlExtra;
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs' => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form', 'key' =>'frmLogin'],
                'body' => ['order'=>51,'type'=>'html', 'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html', 'html'=>"</form>"]]]],
            'forms' => ['frmLogin'=>['attr'=>['type'=>'form','method'=>'post']]],
            'jsReady'=> ['authn'=>$jsAuth]];
    }

    public function view2faCodeEntry(&$layout, $email)
    {
        $attemptsUsed = isset($_SESSION['biz_2fa_temp']['attempts']) ? $_SESSION['biz_2fa_temp']['attempts'] : 0;
        $remaining    = max(0, 5 - $attemptsUsed);

        $html = '<div>' . html5('', $this->logo) . '</div>'
              . '<div class="text">Two-Factor Authentication</div>'
              . '<div class="info">Enter the 6-digit code sent to <strong>' . htmlspecialchars($email) . '</strong></div>';

        if ($remaining < 5 && $remaining > 0) {
            $html .= '<div class="info small">Attempts remaining: ' . $remaining . '</div>';
        }

        $html .= '<div class="field">'
               . '<input type="hidden" name="bizUser" value="' . htmlspecialchars($email) . '">'
               . '<input type="hidden" name="step" value="verify_2fa">'
               . '<input type="text" name="code" placeholder="123456" maxlength="6" pattern="[0-9]{6}" '
               . 'inputmode="numeric" required autofocus autocomplete="one-time-code">'
               . '</div>'
               . '<button type="submit">' . ($this->lang['verify'] ?? 'Verify') . '</button>'
               . '<div class="small"><a href="' . BIZUNO_URL_PORTAL . '">Start over</a> | '
               . '<a href="#" onclick="event.preventDefault(); location.reload();">Resend code</a></div>';

        if (!empty($this->errors)) {
            $html .= '<div class="error">' . htmlspecialchars($this->errors) . '</div>';
        }

        $layout = [
            'type'  => 'guest',
            'divs'  => [
                'body' => [
                    'order'   => 50,
                    'type'    => 'divs',
                    'classes' => ['login-form'],
                    'divs'    => [
                        'formBOF' => ['order'=>20, 'type'=>'form', 'key'=>'frm2fa'],
                        'body'    => ['order'=>51, 'type'=>'html', 'html'=>$html],
                        'formEOF' => ['order'=>90, 'type'=>'html', 'html'=>'</form>']
                    ]
                ]
            ],
            'forms' => [
                'frm2fa' => ['attr'=>['type'=>'form', 'method'=>'post']]
            ],
            'jsReady' => ['focus'=>"document.querySelector('input[name=\"code\"]').focus();"]
        ];
    }


    private function validateUser(&$layout=[])
    {
        msgDebug("\nEntering validateUser.");
        $email = clean('bizUser', 'email', 'post');
        $user = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['id', 'primary_name'], "ctype_u='1' AND email='$email'");
        if (empty($user['id'])) {
            $this->errors = $this->lang['err_invalid_creds'];
            return false;
        }
        // Check if this is a passkey login attempt (we'll set this flag later in verify)
/*        if (!empty($_POST['usePasskey']) && $_POST['usePasskey'] === 'verified') {
            $profile = getMetaContact($user['id'], 'user_profile');
            $userData = [
                'userID' => $user['id'],
                'psID'   => 0,
                'userEmail' => $email,
                'userRole' => $profile['role_id'] ?? 0,
                'userName' => $user['primary_name']
            ];
            setUserCookie($userData);
            $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
            msgDebug("\nPasskey login successful - 2FA bypassed");
            return true;
        } */
        // Normal password flow
        if (empty($_POST['bizPass'])) {
            $this->errors = $this->lang['err_invalid_creds'];
            return false;
        }
        $encPW = getMetaContact($user['id'], 'user_auth');
        $peppered = hash_hmac('sha256', $_POST['bizPass'], BIZUNO_KEY);
        if (!password_verify($peppered, $encPW['value'] ?? '')) {
            $this->errors = $this->lang['err_invalid_creds'];
            return false;
        }
        $userID = $user['id'];
        $authMethods = getUserAuthMethods($userID);
        if (!empty($authMethods['2fa']['enabled'])) {
            $method = $authMethods['2fa']['method'] ?? 'none';
            if ($method === 'email') {
                $this->sendEmailVerificationCode($userID, $email);
                $this->view2faCodeEntry($layout, $email);
                return false; // pause here
            }
        }
        // No 2FA or completed → login
        $profile = getMetaContact($userID, 'user_profile');
        $userData = [
            'userID' => $userID,
            'psID'   => 0,
            'userEmail' => $email,
            'userRole' => $profile['role_id'] ?? 0,
            'userName' => $user['primary_name']
        ];
        setUserCookie($userData);
        $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
        return true;
    }

    /**
     * Generate + send 6-digit code, store hashed in session only
     */
    private function sendEmailVerificationCode($userID, $email)
    {
        $code = random_int(100000, 999999);

        // Use consistent hash (no random salt)
        $hash = hash('sha256', (string)$code);

        $_SESSION['biz_2fa_temp'] = [
            'uid'       => (int)$userID,
            'method'    => 'email',
            'hash'      => $hash,
            'expires'   => time() + 600,
            'attempts'  => 0
        ];

        $subject = "Your Bizuno Verification Code";
        $body = "Code: {$code}\n\n"
              . "This code expires in 10 minutes.\n"
              . "If this wasn't you, secure your account now.";
        // Use Bizuno mailer if available, fallback to mail()
        if (class_exists('bizuno\\mailer')) {
            bizAutoLoad(BIZUNO_FS_LIBRARY . 'model/mail.php');
            $mailer = new mailer();
            $mailer->send(['to'=>$email, 'subject'=>$subject, 'body'=>$body]);
        } else {
            mail($email, $subject, $body, "From: Bizuno Support<support@phreesoft.com>\r\n");
        }
        msgDebug("\nsession leaving  sendEmailVerificationCode is: ".msgPrint($_SESSION));
        msgDebug("\n2FA email code ($code) sent to {$email}");
    }
    
    /**
    * Check if submitted code matches the session-stored hash
    * Includes basic brute-force protection (max 5 attempts)
    *
    * @param int    $code   User-submitted 6-digit code
    * @param string $email  For logging/context only
    * @return bool
    */
    private function verify2faCode($code, $email)
    {
        msgDebug("\nsession at verify2faCode is: ".msgPrint($_SESSION));
        
        if (empty($_SESSION['biz_2fa_temp'])) {
            msgDebug("\nNo 2FA session data found");
            return false;
        }

        $session = &$_SESSION['biz_2fa_temp'];

        if ($session['expires'] < time()) {
            msgDebug("\n2FA code expired for $email");
            unset($_SESSION['biz_2fa_temp']);
            $this->errors = "Code expired or invalid.";
            return false;
        }

        $session['attempts'] = ($session['attempts'] ?? 0) + 1;

        if ($session['attempts'] > 5) {
            msgDebug("\nToo many 2FA attempts for $email");
            unset($_SESSION['biz_2fa_temp']);
            $this->errors = "Too many incorrect attempts.";
            return false;
        }

        // Consistent verify (no password_verify needed)
        $submittedHash = hash('sha256', (string)$code);

        if ($submittedHash === $session['hash']) {
            msgDebug("\n2FA code verified successfully for $email");
            unset($_SESSION['biz_2fa_temp']);
            return true;
        }

        msgDebug("\nIncorrect 2FA code attempt #{$session['attempts']} for $email | Submitted hash: $submittedHash | Stored hash: {$session['hash']}");
        return false;
    }

}