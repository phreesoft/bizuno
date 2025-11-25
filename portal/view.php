<?php
/**
 * Bizuno Portal entry point
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
 * @version    7.x Last Update: 2025-11-25
 * @filesource /portal/view.php
 */

namespace bizuno;

class portalView
{
    private $errors  = '';
    private $defCur  = 'USD';
    private $defChart= 'retail-single.csv';
    public  $lang;
    public  $locale  = '';

    function __construct()
    {
        $iso = !empty($_COOKIE['bizunoEnv']['lang']) ? $_COOKIE['bizunoEnv']['lang'] : 'en_US';
        $lang = [];
        require(BIZUNO_FS_LIBRARY . "locale/$iso/portal.php"); // replace $lang
        $this->lang = $lang;
    }

    /**
     * Generates the login screen structure
     * @return array
     */
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

    /**
     * Validates credentials for a user to log in. ONLY EXECUTED AT THE PORTAL!
     * @param type $layout
     * @return type
     */
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

    public function lostCreds(&$layout=[])
    {
        global $db;
        msgDebug("\nEntering guest/lostCreds.");
        $layout = ['type'=>'guest', 'jsReady'=>['reload'=>"location.reload();"]];
        // if POST vars are set then try to reset with email address
        if (isset($_POST['bizUser'])) {
            msgDebug("\nCredentials sent, trying to validate.");
            $this->validateReset($layout); // if validated, return to load home page
        }
        // Show lost password form
        $src = BIZUNO_LOGO;
        if ($db->connected) { // getModuleCache('bizuno', 'settings', 'company', 'logo');
            $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
            $stg = !empty($cfg) ? json_decode($cfg, true) : [];
            if (!empty($stg['settings']['company']['logo'])) { $src = BIZUNO_URL_FS.BIZUNO_BIZID."/images/{$stg['settings']['company']['logo']}"; }
        }
        $logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>$src,'height'=>48]];
        $html = '<div>'.html5('', $logo).'</div>
    <div class="text">'.$this->lang['password_lost'].'</div>
    <div class="field"><input type="text" name="bizUser" placeholder="'.$this->lang['email'].'"> </div>
    <button>'.$this->lang['password_reset'].'</button>';
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs'  => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form',  'key' =>'frmLost'],
                'body'    => ['order'=>51,'type'=>'html',  'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms' => ['frmLost'=>['attr'=>['type'=>'form','method'=>'post']]]];
    }

    private function validateReset(&$layout=[])
    {
        global $db;
        msgDebug("\nEntering validateReset.");
        if ($_SERVER['SERVER_NAME']<>BIZUNO_PORTAL) { return $this->lang['err_illegal_access']; }
        $userID = preg_replace("/[^a-zA-Z0-9\-\_\.\@]/", '', $_POST['bizUser']); // email address
        if (!empty($userID) && $db->connected) { // if connected to the db, then find user
            $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "ctype_u='1' AND email='$userID'");
            if (!empty($cID)) {
                // generate email and send it. Return with check email message
                $code  = format_uuidv4();
                $exp   = strtotime('+15 minutes', time());
                $exists= dbMetaGet(0, 'user_reset', 'contacts', $cID);
                msgDebug("\nExists = ".print_r($exists, true));
                $rID = metaIdxClean($exists);
                dbMetaSet($rID, 'user_reset', "$exp:$code", 'contacts', $cID);
                $this->sendResetEmail($cID, $code);
                $this->errors = "Reset email sent! Please check your email for a link to reset your password.";
                return;
            }
        }
        $this->errors = $this->lang['err_invalid_creds'];
    }

    private function sendResetEmail($cID, $code)
    {
        $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
        $stg = !empty($cfg) ? json_decode($cfg, true) : [];
        $fromName  = !empty($stg['settings']['company']['primary_name']) ? $stg['settings']['company']['primary_name'] : 'PhreeSoft Webmaster';
        $fromEmail = !empty($stg['settings']['company']['email'])        ? $stg['settings']['company']['email']        : 'support@phreesoft.com';
        $contact   = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['email', 'primary_name'], "id=$cID");
        $msgSubject= "Password reset request from $fromName";
        $msgBody   = "<h1>Password Reset Request</h1><p>A password reset has been requested for {$contact['primary_name']} has been requested.</p>";
        $msgBody  .= '<p>Please click <a href="'.BIZUNO_URL_PORTAL."?bizRt=portal/api/lostVal&userID=$cID&userCode=$code".'">here</a> to be directed to your site to reset your password or copy and paste the following link to you browser:</p>';
        $msgBody  .= '<p>'.BIZUNO_URL_PORTAL."?bizRt=portal/api/lostVal&userID=$cID&userCode=$code".'</p>';
        $msgBody  .= '<p>If you did not request this reset, please see your administrator.</p>';
        msgDebug("\nready to send the email");
        $bizMail = new bizunoMailer($contact['email'], $contact['primary_name'], $msgSubject, $msgBody, $fromEmail, $fromName);
        $bizMail->sendMail();
    }

    public function lostNewPW(&$layout=[])
    {
        global $db;
        msgDebug("\nEntering guest/lostNewPW.");
        $layout = ['type'=>'guest']; // ,'jsReady'=>['reload'=>"location.reload();"]
        // if POST vars are set then try to reset with email address
        if (isset($_GET['userID']) && isset($_GET['userCode'])) {
            msgDebug("\nNew passwords sent, trying to validate.");
            if ($this->validateAuth($layout)) { return; }
        }
        // Show lost password form
        $src = BIZUNO_LOGO;
        if ($db->connected) { // getModuleCache('bizuno', 'settings', 'company', 'logo');
            $cfg = dbGetValue(BIZUNO_DB_PREFIX.'configuration', 'config_value', "config_key='bizuno'");
            $stg = !empty($cfg) ? json_decode($cfg, true) : [];
            if (!empty($stg['settings']['company']['logo'])) { $src = BIZUNO_URL_FS.BIZUNO_BIZID."/images/{$stg['settings']['company']['logo']}"; }
        }
        $logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>$src,'height'=>48]];
        $html = '<div>'.html5('', $logo).'</div>
    <div class="text">'.$this->lang['password_lost'].'</div>
    <div class="field"><input type="password" name="bizPass0" placeholder="'.$this->lang['password'].'"> </div>
    <div class="field"><input type="password" name="bizPass1" placeholder="'.$this->lang['password_retype'].'"> </div>
    <button>'.$this->lang['password_reset'].'</button>';
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        $layout = ['type'=>'guest',
            'divs'  => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF' => ['order'=>20,'type'=>'form',  'key' =>'frmReset'],
                'body'    => ['order'=>51,'type'=>'html',  'html'=>$html],
                'formEOF' => ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms' => ['frmReset'=>['attr'=>['type'=>'form','method'=>'post']]]];
    }

    public function validateAuth (&$layout=[])
    {
        global $db;
        if (empty($_POST['bizPass0']) || empty($_POST['bizPass1'])) { return; } // entering for first time
        if (strlen($_POST['bizPass0'])<8 || $_POST['bizPass0']<>$_POST['bizPass1']) { $this->errors = $this->lang['err_invalid_creds']; return; }
        $cID = intval($_GET['userID']); // cID
        $code= preg_replace("/[^a-f0-9\-]/", '', $_GET['userCode']); // uuid code
        msgDebug("\nEntering validateauth with cID = $cID and code = $code");
        $val = dbMetaGet(0, 'user_reset', 'contacts', $cID);
        if (empty($val)) { $this->errors = $this->lang['err_invalid_creds']; return; }
        msgDebug("\nFetched code from meta = ".print_r($val, true));
        $codeID = metaIdxClean($val);
        $tmp = explode(':', $val['value']);
        if (intval($tmp[0])>time() && $code==$tmp[1]) {
            msgDebug("\nIn window, checking code.");
            $exists= dbMetaGet(0, 'user_auth', 'contacts', $cID);
            msgDebug("\nExists = ".print_r($exists, true));
            $rID = metaIdxClean($exists);
            $peppered = hash_hmac('sha256', $_POST['bizPass0'], BIZUNO_KEY);
            $hashed = password_hash($peppered, PASSWORD_DEFAULT);
            dbMetaSet($rID, 'user_auth', $hashed, 'contacts', $cID);
            dbMetaDelete($codeID, 'contacts');
            $layout['jsReady']['reload'] = "window.location.href = '".BIZUNO_URL_PORTAL."'";
            return true;
        } else {
            $this->errors = $this->lang['err_invalid_creds'];
        }
    }

    /******************************* Install Methods *************************************/
    public function install(&$layout=[])
    {
        msgDebug("\nEntering install");
        if (isset($_POST['biz_user']) && isset($_POST['biz_pass'])) { // check for post to start install
            if ($this->installBizuno($layout)) { return; }
        }
        // Show install form
        $logo = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS.'0/view/images/bizuno.png','height'=>48]];
        $html = '<div>'.html5('', $logo).'</div>
    <div class="info">'.$this->lang['install_intro'].'</div><br />
    <div class="info">'.$this->lang['biz_user']     .'</div><div class="field"><input type="text" name="biz_user" value=""></div>
    <div class="info">'.$this->lang['biz_pass']     .'</div><div class="field"><input type="password" name="biz_pass" value=""></div>
    <div class="info">'.$this->lang['biz_title']    .'</div><div class="field"><input type="text" name="biz_title" value="'.$this->lang['my_business'].'"></div>
    <div class="info">'.$this->lang['currency']     .'</div><div class="field">'.$this->getSelCur().'</div>
    <button>'.$this->lang['install'].'</button>';
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        msgDebug("\nStarting to generate layout");
        $layout = ['type'=>'guest',
            'divs'  => ['body' =>['order'=>50,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF'=> ['order'=>20,'type'=>'form','key' =>'frmInstall'],
                'body'   => ['order'=>51,'type'=>'html','html'=>$html],
                'formEOF'=> ['order'=>90,'type'=>'html','html'=>"</form>"]]]],
            'forms' => ['frmInstall'=>['attr'=>['type'=>'form','method'=>'post']]]];
        msgDebug("\nReturning layout ".print_r($layout, true));
    }

    private function installBizuno(&$layout=[])
    {
        msgDebug("\nEntering controllers/installBizuno");
        // Do some error checking
        if (!file_exists(BIZUNO_DATA)) { // first, try to make it
            @mkdir(BIZUNO_DATA);
            if (!file_exists(BIZUNO_DATA) || !is_writable(BIZUNO_DATA)) { 
                $this->errors .= 'I cannot find your data folder! Does the path exist and is it writeable?';
                return;
            }
        }
        $email = clean('biz_user', 'email', 'post');
        if (empty($email)) { 
            $this->errors .= 'Your email is invalid, please correct and try again!';
            return;
        }
        if (!$this->installTestDB()) {
            $this->errors .= "I'm having difficulties connecting to your database, Please checkthe credentials in your PortalCFG.php file!";
            return;
        }
        $cookie = base64_encode(json_encode([1, 0, $email, $this->instRole, $_SERVER['REMOTE_ADDR']]));
        bizSetCookie('bizunoUser',   $email, time()+(60*60*24*7)); // 7 days
        bizSetCookie('bizunoSession',$cookie,time()+(60*60*10)); // 10 hours
        setUserCache('profile', 'userID', 1); // Local user ID
        setUserCache('profile', 'email',  $email);
        setUserCache('profile', 'psID',   0); // PhreeSoft user ID, zero in this case
        $_POST['biz_fy']      = biz_date('Y'); // default fiscal year to this year
        $_POST['biz_chart']   = $this->defChart;
        $_POST['biz_timezone']= $this->guessTimeZone($this->locale);
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/install.php', 'bizInstall');
        $installer = new bizInstall();
        $installer->installBizuno($layout);
        if (isset($GLOBALS['BIZUNO_INSTALL_CID'])) { // since we are local, need to set role and password contact meta
            $peppered = hash_hmac('sha256', $_POST['biz_pass'], BIZUNO_KEY);
            $hashed = password_hash($peppered, PASSWORD_DEFAULT);
            dbMetaSet(0, 'user_auth', $hashed, 'contacts', $GLOBALS['BIZUNO_INSTALL_CID']);
        }
        return true;
    }

    private function getSelCur()
    {
        msgDebug("\nEntering getSelCur");
        $html = '<select name="biz_currency">';
        $opts = viewCurrencySel($this->locale);
        foreach ($opts as $opt) { $html .= '<option value="'.$opt['id'].'"'.($opt['id']==$this->defCur?' selected':'').'>'.$opt['text'].'</option>'; }
        $html.= '</select>';
        msgDebug(" ... and returning $html");
        return $html;
    }
    private function guessTimeZone($locale=[])
    {
        if (empty($locale)) { $locale= localeLoadDB(); }
        $ipInfo= file_get_contents('http://ip-api.com/json/'.$_SERVER['REMOTE_ADDR']);
        $data  = json_decode($ipInfo);
        $output= 'America/New_York';
        if (empty($data->timezone)) { return $output; }
        foreach ($locale->Timezone as $value) {
            if ($data->timezone == $value->Code) { $output = $value->Code;  break; }
        }
        return $output;
    }
    private function installTestDB()
    {
        global $db;
        if (!defined('BIZUNO_DB_CREDS')) { $this->errors .= 'DB credentials have not been defined, install cannot continue!'; return ;}
        $db = new db(BIZUNO_DB_CREDS);
        if (!$db->connected) { $this->errors .= 'Bizuno cannot connect to your DB, please check your credentials!'; return; }
        return true;
    }

    /******************************* Migrate Methods *************************************/
    public function migrate(&$layout=[])
    {
        msgDebug("\nEntering migrate");
        $migrate= clean('migrate','integer', 'get');
        $inStep = clean('inStep', 'integer', 'get');
        if (!empty($migrate)) { // check for post to start migration
            msgDebug("\nEntering controllers/installBizuno");
            if ($_SERVER['SERVER_NAME']<>BIZUNO_PORTAL) { return msgAdd("err_illegal_access"); }
            $creds = getUserCookie();
            setUserCache('profile', 'psID',  $creds[1]); // PhreeSoft user ID
            setUserCache('profile', 'email', $creds[2]); // User email
            loadBusinessCache();
            if (empty($inStep)){ $this->migrateBizuno($layout); }
            else               { $this->migrateBizunoNext($layout); }
            return;
        }
        // Show migrate form
        $js    = '<link rel="stylesheet" href="'.BIZUNO_URL_FS.'0/view/portal.css" />';
        $logo  = ['label'=>getModuleCache('bizuno','properties','title'),'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS.'0/view/images/bizuno.png','height'=>48]];
        $html  = '<div>'.html5('', $logo).'</div>'."\n".'<div class="info">'.$this->lang['migrate_intro'].'</div><br />'."\n".'<button>'.$this->lang['migrate'].'</button>'."\n";
        if (!empty($this->errors)) { $html .= '<div class="error">'.$this->errors.'</div>'; }
        msgDebug("\nStarting to generate layout");
        $layout= ['type'=>'migrate',
            'divs'   => [
                'head'=> ['order'=> 5,'type'=>'html','html'=>$js],
                'body'=> ['order'=>10,'type'=>'divs','classes'=>['login-form'],'divs'=>[
                'formBOF'=> ['order'=>20,'type'=>'form','key' =>'frmMigrate'],
                'body'   => ['order'=>51,'type'=>'html','html'=>$html],
                'formEOF'=> ['order'=>90,'type'=>'html','html'=>"</form>"]]]],
            'forms'  =>['frmMigrate'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&migrate=1"]]],
            'jsReady'=>['init'=>"ajaxForm('frmMigrate');"]];
        msgDebug("\nReturning layout ".print_r($layout, true));
    }
    public function migrateBizuno(&$layout=[])
    {
        $dbVer = getModuleCache('bizuno', 'properties', 'version');
        msgDebug("\nEntering migrateBizuno with dbVersion = $dbVer and MODULE_BIZUNO_VERSION = ".MODULE_BIZUNO_VERSION);
        if (version_compare($dbVer, '7.0') >= 0) { return; } // already there
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/migrate-7.0.php');
        $charts = getModuleCache('phreebooks', 'chart');
        if (empty($charts)) { // if the COA is not present, bail on migrate since pre 7.0 it only survived in the cache
            return msgAdd('The chart of accounts is missing! Bailing');
        }
        $cron = migrateBizunoPrep();
        msgDebug("\nInitializing cron Bizuno migrate with cron = ".print_r($cron, true));
        setModuleCache('bizuno', 'cron', 'migrateBizuno', $cron);
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval', 'actionData'=>"cronInit('migrateBizuno','&migrate=1&inStep=1');"]]);
    }
    public function migrateBizunoNext(&$layout=[])
    {
        msgDebug("\nEntering migrateBizunoNext.");
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/migrate-7.0.php');
        $cron = getModuleCache('bizuno', 'cron', 'migrateBizuno');
        migrateBizuno($cron);
        msgDebug("\nBack from migrateBizuno with cron = ".print_r($cron, true));
        $ttlRecords = number_format($cron['ttlRecord']);
        $ttlSteps  = $cron['ttlSteps']+1; // because we go past it to stop
        $msg = "Completed Step: {$cron['curStep']} of $ttlSteps<br />Block {$cron['curBlk']} of {$cron['ttlBlk']}<br />Total of $ttlRecords records.<br />";
        if ($cron['curStep']>$cron['ttlSteps']) { // wrap up this iteration
            $msg .= "<p>Database table migrate completed! Press OK to go to your business.</p>";
            $msg .= html5('btnGo', ['events'=>['onClick'=>"window.location='https://".BIZUNO_PORTAL."';"], 'attr'=>['type'=>'button','value'=>lang('finish')], 'styles'=>['cursor'=>'pointer']]);
            msgLog($msg);
            $data = ['content'=>['percent'=>100,'msg'=>$msg,'baseID'=>'migrateBizuno','urlID'=>"&migrate=1&inStep=1"]];
            setModuleCache('bizuno', 'properties', 'version', '7.0');
            bizClrCookie('bizunoSession'); // forces a logout
            bizCacheExpClear();
            clearModuleCache('bizuno', 'cron', 'migrateBizuno');
            dbGetResult('DROP TABLE IF EXISTS '.BIZUNO_DB_PREFIX.'address_book'); // Drop this table here as we use it to determine if we need to migrate
        } else { // return to update progress bar and start next step
            $blkPrcnt= floor(100*($cron['curBlk'])/$cron['ttlBlk']);
            $data = ['content'=>['percent'=>$blkPrcnt,'msg'=>$msg,'baseID'=>'migrateBizuno','urlID'=>"&migrate=1&inStep=1"]];
            setModuleCache('bizuno', 'cron', 'migrateBizuno', $cron);
        }
        $layout = array_replace_recursive($layout, $data);
    }
}