<?php
/*
 * Bizuno dashboard - Embedded Google Calendar
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
 * @filesource /controllers/bizuno/dashboards/google_calendar/google_calendar.php
 */

namespace bizuno;

class google_calendar
{
    public $moduleID = 'bizuno';
    public $methodDir= 'dashboards';
    public $code     = 'google_calendar';
    public $category = 'general';
    public $noSettings= true;
    public  $struc;
    public $lang = ['title'=>'Google Calendar',
        'description'=>'Embed your Google Calendar into this dashboard. Enter your google email address in your profile (My Business -> Profile). NOTE: your browser must also be logged into your Google account under the same email address.',
        'err_no_email'=>'No calendars to display. Please enter your Google Email in your profile! (My Business -> Profile)',
        'gmail_account' => 'To view your Google Calendar, please enter your Google Email address in your User account. Settings -> Directory -> Users'];

    function __construct()
    {
    }

    public function render()
    {
        $gmail = getUserCache('profile', 'email');
        $gzone = getModuleCache('bizuno', 'settings', 'locale', 'timezone', 'America/New_York');
        if (empty($gmail)) { $html = $this->lang['gmail_account']; }
        else {
            $html = '<iframe src="https://calendar.google.com/calendar/embed?src='.urlencode($gmail).'&ctz='.urlencode($gzone).'" style="border: 0" width="100%" height="300" frameborder="0" scrolling="no"></iframe>';
        }
        return ['html'=>$html];
    }
}
