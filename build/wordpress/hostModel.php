<?php
/*
 * WordPress Plugin - bizuno-accounting - Special Model overrides for the hosting platform
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
 * @version    7.x Last Update: 2025-11-21
 * @filesource /hostModel.php
 */

namespace bizuno;

/**
 * Bizuno operates in local time. Returns WordPress safe date in PHP date() format if no timestamp is present, else PHP date() function
 * @param string $format - [default: 'Y-m-d'] From the PHP function date()
 * @param integer $timestamp - Unix timestamp, defaults to now
 * @return string
 */
function portal_date($format='Y-m-d', $timestamp=null) {
    return !is_null($timestamp) ? date( $format, $timestamp ) : \wp_date( $format );
}

/**
 * Special class to pass emails through the WordPress transport
 * We are only here IF the Bizuno library is not selected as the preferred sender
 */
class hostMail
{
    public $FromName;
    public $FromEmail;
    public $ToName;
    public $ToEmail;
    public $toCC;
    public $attach;
    public $Subject;
    public $Body;
    
    function __construct()
    {
        
    }

    /**
     * WordPress mail transport
     * @return boolean - true if successful, false with messageStack errors if not
     */
    public function sendMail()
    {
        $attachments = [];
        $body   = '<html><body>'.$this->Body.'</body></html>';
        $headers= [
            "Content-Type: text/html; charset=UTF-8",
            "From: "    .$this->cleanAddress($this->FromName, $this->FromEmail),
            "Reply-To: ".$this->cleanAddress($this->FromName, $this->FromEmail)];
        foreach ($this->toEmail as $addr) { $to[]     = $this->cleanAddress($addr['name'], $addr['email']); }
        foreach ($this->toCC as $addr)    { $headers[]= 'Cc: '.$this->cleanAddress($addr['name'], $addr['email']); }
        msgDebug("\nReady to send CMS host email with headers = ".print_r($headers, true));
        foreach ($this->attach as $file) {
            if (!empty($file['name'])) { // it's in the $_FILES folder, move to where WordPress can get it
                msgDebug("\nMoving file from temp location: {$file['path']} to Bizuno data folder: ".BIZUNO_DATA."temp/{$file['name']}");
                move_uploaded_file($file['path'], BIZUNO_DATA."temp/{$file['name']}");
                $file['path'] = BIZUNO_DATA."temp/{$file['name']}";
            }
            $attachments[]= $file['path'];
        }
        msgDebug("\nAttachments array = ".print_r($attachments, true));
        $success = \wp_mail( $to, $this->Subject, $body, $headers, $attachments );
        // remove the temp files
        foreach ($attachments as $file) { unlink($file); }
        return $success ? true : false;
    }

    /**
     * Cleans the name and address per WordPress requirements
     * @param string $name
     * @param string $email
     * @return string
     */
    private function cleanAddress($name, $email)
    {
        return clean($name, 'alpha_num').' <'.\sanitize_email($email).'>';
    }
}
