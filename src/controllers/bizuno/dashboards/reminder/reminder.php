<?php
/*
 * Bizuno dashboard - List reminders on a given recurring schedule, works with profile add-on
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
 * @filesource /controllers/bizuno/dashboards/reminder/reminder.php
 */

namespace bizuno;

class reminder
{
    public  $methodDir = 'dashboards';
    public  $code      = 'reminder';
    public  $category  = 'general';
    private $metaPrefix= 'reminder_list';
    public  $struc;
    public  $lang      = ['title'=>'My Reminders',
        'description'=> 'Displays scheduled reminders to help you remember the things you need to do. Reminders are set in My Business -> Profile -> Reminders tab.',
        'msg_settings_info' => 'Reminders are managed through your profile: My Business -> My Profile -> Reminders tab'];

    function __construct()
    {
    }
    public function render() // Renders the dashboard contents in HTML
    {
        $today  = biz_date('Y-m-d');
        $source = dbMetaGet('%', 'reminder', 'contacts', getUserCache('profile', 'userID')); // multiple meta rows
        metaIdxClean($source); // just remove the table indexes
        $current= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID')); // only a single meta row
        $curID  = metaIdxClean($current);
        if (!empty($source)) { // check for new tasks
            foreach ($source as $entry) {
                if ($entry['dateNext'] <= $today) {
                    $current[] = ['title'=>$entry['title'], 'date'=>$entry['dateNext']];
                    $entry['dateNext'] = LocaleSetDateNext($entry['dateNext'], $entry['recur']);
                    $srcID  = metaIdxClean($entry);
                    dbMetaSet($srcID, 'reminder', $entry, 'contacts', getUserCache('profile', 'userID'));
                    $update = true;
                }
            }
            if (!empty($update)) {
                $current = sortOrder($current, 'date');
                dbMetaSet($curID, $this->metaPrefix, $current, 'contacts', getUserCache('profile', 'userID'));
            }
        } else { $rows[] = '<span>'.$this->lang['msg_settings_info'].'</span>'; }
        // Build list
        $rows = [];
        if ( empty($current)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else { for ($i=0,$j=1; $i<sizeof($current); $i++,$j++) {
            $content= viewFormat($current[$i]['date'], 'date').' - '.$current[$i]['title'];
            $trash  = '<span style="float:right">'.html5('', ['icon'=>'trash','size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { dashSubmit('$this->code', $j); }"]]);
            $rows[] = viewDashList($content, $trash);
        } }
        return ['lists'=>$rows];
    }
    public function save() // removes a row from the list
    {
        $rmID = clean('rID', 'integer', 'get');
        if (!empty($rmID)) {
            $current= dbMetaGet(0, $this->metaPrefix, 'contacts', getUserCache('profile', 'userID')); // only a single meta row
            $curID  = metaIdxClean($current);
            array_splice($current, $rmID-1, 1);
            dbMetaSet($curID, $this->metaPrefix, $current, 'contacts', getUserCache('profile', 'userID'));
        }
    }
}
