<?php
/*
 * This class is a wrapper to PHPMailer to handle bizuno messaging
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
 * @version    7.x Last Update: 2026-02-03
 * @filesource /model/mail.php
 */

namespace bizuno;

use PHPMailer\PHPMailer\PHPMailer; //Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; // Only needed for debugging but doesn't hurt to be there.

class bizunoMailer
{
    public $struc;
    public $toEmail;
    public $ToName;
    public $Subject;
    public $Body;
    public $FromEmail;
    public $FromName;
    public $toCC      = [];
    public $attach    = [];

    /**
     * Prepares the mail transport to send emails.
     * @param mixed  $toEmail - email addresses, can be array, separated with comma or semi-colons
     * @param string $toName - Textual recipient
     * @param string $subject - The subject for the email, null is allowed to leave subject blank
     * @param string $body - The HTML body for the email, null is allowed to leave body blank
     * @param mixed  $fromEmail - [default: user email] email addresses, can be array, separated with comma or semi-colons
     * @param string $fromName - [default: user title] Textual sender name
     */
    public function __construct($toEmail='', $toName='', $subject='', $body='', $fromEmail='', $fromName='')
    {
        msgDebug("\nEntering mail:__construct with mail to: $toName <$toEmail> from: $fromName <$fromEmail>");
        $this->fieldStructure();
        if     (sizeof($results = explode(',', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toEmail[] = ['email'=>trim($email), 'name'=>trim($toName)]; } }
        elseif (sizeof($results = explode(';', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toEmail[] = ['email'=>trim($email), 'name'=>trim($toName)]; } }
        else { $this->toEmail[] = ['email'=>trim($toEmail), 'name'=>trim($toName)]; }
        $this->ToName    = $toName;
        $this->Subject   = $subject;
        $this->Body      = $body;
        $this->FromEmail = !empty($fromEmail) ? $fromEmail : getUserCache('profile', 'email');
        $this->FromName  = !empty($fromName)  ? $fromName  : getUserCache('profile', 'title');
        msgDebug("\nSending from: $this->FromName email: $this->FromEmail to: ".print_r($this->toEmail, true));
    }

    /**
     * Fields as needed for setting mail credentials and access preferences. For local host, no special fields, emails sent from server name 
     * @return array - page structure
     */
    protected function fieldStructure()
    {
        $selMail= [['id'=>'host','text'=>lang('PhreeSoft Hosted')],['id'=>'smtp','text'=>lang('SMTP Email')],['id'=>'gmail','text'=>lang('google_gmail')]];
        $this->struc = [ 
            'mail_mode'    => ['tab'=>'options','panel'=>'mail','order'=>10,'clean'=>'text',   'attr'=>['type'=>'select',  'value'=>'host'],'values'=>$selMail],
            'smtp_host'    => ['tab'=>'options','panel'=>'mail','order'=>25,'clean'=>'url',    'attr'=>['type'=>'text',    'value'=>'']],
            'smtp_port'    => ['tab'=>'options','panel'=>'mail','order'=>30,'clean'=>'integer','attr'=>['type'=>'integer', 'value'=>'']],
            'smtp_user'    => ['tab'=>'options','panel'=>'mail','order'=>35,'clean'=>'text',   'attr'=>['value'=>'']],
            'smtp_pass'    => ['tab'=>'options','panel'=>'mail','order'=>40,'clean'=>'text',   'attr'=>['type'=>'password','value'=>'']],
            'gmail_app_pw' => ['tab'=>'options','panel'=>'mail','order'=>75,'clean'=>'text',   'attr'=>['value'=>'']],
            'gmail_app_pw2'=> ['tab'=>'options','panel'=>'mail','order'=>75,'clean'=>'text',   'attr'=>['value'=>'']],
            'gmail_app_pw3'=> ['tab'=>'options','panel'=>'mail','order'=>75,'clean'=>'text',   'attr'=>['value'=>'']],
            'gmail_app_pw4'=> ['tab'=>'options','panel'=>'mail','order'=>75,'clean'=>'text',   'attr'=>['value'=>'']]];
        langFillLabels($this->struc);
    }

    /**
     * This sends an e-mail to one or more recipients, handles errors.
     * @return boolean - true if successful, false with messageStack errors if not
     */
    public function sendMail() {
        if ( class_exists( "\\bizuno\\hostMail" ) ) { // If host has special requirements for sending emails.
            $hostMailer           = new hostMail();
            $hostMailer->FromName = $this->FromName;
            $hostMailer->FromEmail= $this->FromEmail;
            $hostMailer->ToName   = $this->ToName;
            $hostMailer->ToEmail  = $this->ToEmail;
            $hostMailer->toCC     = $this->toCC;
            $hostMailer->attach   = $this->attach;
            $hostMailer->Subject  = $this->Subject;
            $hostMailer->Body     = $this->Body;
            return $hostMailer->sendMail();
        }
        return $this->bizunoMailerSendMail();
    }

    private function bizunoMailerSendMail()
    {
        global $mail;
//      error_reporting(E_ALL & ~E_NOTICE); // This is to eliminate errors from undefined constants in phpmailer
        $mail = new PHPMailer(true);
        $mail->CharSet = defined('CHARSET') ? CHARSET : 'utf-8'; // default "iso-8859-1";
        $mail->isHTML(true); // set email format to HTML
        $mail->setLanguage(substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2), BIZUNO_FS_LIBRARY.'apps/PHPMailer/language/');
        if (!$mail->validateAddress($this->FromEmail)) { return msgAdd(sprintf(lang('error_invalid_email'), $this->FromEmail)); }
        $mail->setFrom($this->FromEmail, $this->FromName);
//      $mail->addReplyTo($this->FromEmail, $this->FromName); // don't need anymore since users can now send from their own address
        $mail->Subject = $this->Subject;
        $mail->Body    = '<html><body>'.$this->Body.'</body></html>';
        // clean message for text only mail recipients
        $textOnly = str_replace(['<br />','<br/>','<BR />','<BR/>','<br>','<BR>'], "\n", $this->Body);
        $mail->AltBody =  strip_tags($textOnly);
        foreach ($this->toEmail as $addr) {
            if (!$mail->ValidateAddress($addr['email'])) { return msgAdd(sprintf(lang('error_invalid_email'), "{$addr['name']} <{$addr['email']}>")); }
            $mail->addAddress($addr['email'], $addr['name']);
        }
        foreach ($this->toCC as $addr) {
            if (!$mail->ValidateAddress($addr['email'])) { return msgAdd(sprintf(lang('error_invalid_email'), "{$addr['name']} <{$addr['email']}>")); }
            $mail->addCC($addr['email'], $addr['name']);
        }
        foreach ($this->attach as $file) { $mail->AddAttachment($file['path'], $file['name']); }
        try {
            $creds = getMailCreds();
            switch ($creds['mail_mode']) {
                case 'smtp':
                    if (!$this->sendSMTP($creds)) { return; }
                    break; // SMTP to users email server
                case 'gmail': 
                    if (!$this->sendGoogle($creds)) { return; }
                    break; // gMail - Google Workspace
                default: // host server, i.e. postfix
                    msgDebug("\nSending via the postfix mailer.");
                    $mail->send();
            }
        } catch (phpmailerException $e) {
            msgAdd("bizMail phpmailerException: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->errorMessage());
        } catch (Exception $e) {
            msgAdd("bizMail Exception: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->getMessage());
        }
        msgAdd('Message has been sent.', 'success');
        return true;
    }

    private function sendSMTP($creds)
    {
        global $mail;
        msgDebug("\nSending via SMTP with creds = ".print_r($creds, true));
        $debugOutput = '';
        try {
// Used to capture connection and other STMP issues, tied to a specific email address
//if (getUserCache('profile', 'email')=='support@phreesoft.com') { $mail->SMTPDebug = 4; $mail->Debugoutput = function ($str, $level) use (&$debugOutput) { $debugOutput .= $str . "\n"; }; }
            $mail->isSMTP();
            $mail->SMTPAuth  = true;
            $mail->Host      = $creds['smtp_host'];
            $mail->Port      = $creds['smtp_port'];
            $mail->Username  = $creds['smtp_user'];
            $mail->Password  = $creds['smtp_pass'];
            if (587==$mail->Port) { $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; }
            if (465==$mail->Port) { $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; }
            $mail->send();
        } catch (phpmailerException $e) {
            msgDebug("\nphpMailer Debug level output = $debugOutput");
            msgAdd("SMTP phpmailerException: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->errorMessage());
        } catch (Exception $e) {
            msgDebug("\nphpMailer Debug level output = $debugOutput");
            msgAdd("SMTP Exception: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->getMessage());
        }
        $mail->smtpClose();
        return true;
    }

    private function sendGoogle($creds)
    {
        global $mail;
        $key  = clean('msgFrom', 'text', 'post');
        msgDebug("\nReached mail redirect to send Google with key = $key and creds: ".print_r($creds, true));
        if ('user'==$key) {
            $meta = getMetaContact(getUserCache('profile', 'userID'), 'user_profile');
            $email= $meta['email'];
            $appPW= $meta['gmail_app_pw']; // gmail_app_pw2
        } else {
            if (empty($key)) { $key = 'gen'; }
            $map  = ['gen'=>'', 'ap'=>'_ap','ar'=>'_ar'];
            $myBiz= getModuleCache('bizuno', 'settings', 'company');
            msgDebug("\nRead biz settings = ".print_r($myBiz, true));
            $email = $myBiz['email'.$map[$key]];
            $myMail= getModuleCache('bizuno', 'settings', 'mail');
            msgDebug("\nRead biz mail = ".print_r($myMail, true));
            $mapPW= ['gen'=>'','ap'=>'2',  'ar'=>'3'];
            $appPW= $myMail['gmail_app_pw'.$mapPW[$key]];
        }
        msgDebug("\nEmail changed to: $email and app pw to $appPW");
        try {
            $mail->isSMTP();
            $mail->Host      = 'smtp.gmail.com'; 
            $mail->SMTPAuth  = true;
            $mail->Username  = $email;
            $mail->Password  = trim(str_replace(' ', '', $appPW));
            $mail->SMTPSecure= 'tls';
            $mail->Port      = 587;
            $mail->send();
        } catch (phpmailerException $e) {
            msgAdd("Google phpmailerException: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->errorMessage());
        } catch (Exception $e) {
            msgAdd("Google Exception: Email send failed to: $this->ToName", 'trap');
            return msgAdd($e->getMessage());
        }
        $mail->smtpClose();
        return true;
    }
    
    /**
     * Adds one or more CC's to the email
     * @param mixed $toEmail - email addresses, can be array, separated with comma or semi-colons
     * @param string $toName - Textual recipient
     */
    public function addToCC($toEmail, $toName='')
    {
        if       (sizeof($results = explode(',', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toCC[] = ['email'=>$email, 'name'=>'']; }
        } elseif (sizeof($results = explode(';', $toEmail)) > 1) {
            foreach ($results as $email) { $this->toCC[] = ['email'=>$email, 'name'=>'']; }
        } else                           { $this->toCC[] = ['email'=>$toEmail, 'name'=>$toName]; }
    }

    /**
     * Adds an attachment to the email
     * @param string $path - full path of the attachment file
     * @param string $name - name to be assigned to the file, leave null to use file system name
     */
    public function attach($path, $name='') {
        $this->attach[] = ['path'=>$path, 'name'=>$name];
    }
}
