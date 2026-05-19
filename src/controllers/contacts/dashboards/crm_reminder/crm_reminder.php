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
 * @filesource /controllers/contacts/dashboards/crm_reminder/crm_reminder.php
 */

namespace bizuno;

class crm_reminder
{
    public  $moduleID  = 'contacts';
    public  $methodDir = 'dashboards';
    public  $code      = 'crm_reminder';
    public  $category  = 'customers';
    private $metaPrefix= 'projects_crm';
    public  $noSettings= true;
    public  $struc;
    public  $lang      = ['title'=>'CRM Reminders',
        'description' => 'Reminders for followup from the CRM projects feature.',
        'msg_settings_info' => 'Reminders are managed through the CRM Manager: Customers -> CRM Projects -> Reminders tab'];

    function __construct()
    {
    }
    public function render()
    {
        $today  = biz_date('Y-m-d');
        $source = dbMetaGet('%', $this->metaPrefix); // multiple meta rows in common table
        $current= dbMetaGet(0, 'projects_list'); // only a single meta row
        $curID  = metaIdxClean($current);
        if (!empty($source)) { // check for new tasks
            foreach ($source as $entry) {
                if ($entry['dateNext'] <= $today) {
                    $current[] = ['title'=>$entry['title'], 'date'=>$entry['dateNext']];
                    $entry['dateNext'] = LocaleSetDateNext($entry['dateNext'], $entry['recur']);
                    $srcID  = metaIdxClean($entry);
                    dbMetaSet($srcID, 'reminder', $entry);
                    $update = true;
                }
            }
            if (!empty($update)) { dbMetaSet($curID, $this->metaPrefix, sortOrder($current, 'date')); }
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
    public function save(&$usrMeta) // removes a row from the list
    {
        $rmID = clean('rID', 'integer', 'get');
        if (!empty($rmID)) { array_splice($usrMeta[$this->code]['opts']['data'], $rmID-1, 1); }
    }
}
