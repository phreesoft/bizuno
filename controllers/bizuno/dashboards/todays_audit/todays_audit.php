<?php
/*
 * Bizuno dashboard - Audit/Activity Log
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/bizuno/dashboards/todays_audit/todays_audit.php
 */

namespace bizuno;

class todays_audit
{
    public  $moduleID = 'bizuno';
    public  $methodDir= 'dashboards';
    public  $code     = 'todays_audit';
    public  $category = 'general';
    private $titles   = [];
    public  $struc;
    public  $lang     = ['title'=>'Today\'s Activity',
        'description'=> 'Lists today\'s activities. Settings are available for enhanced security and control.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'=>true],
            'reps'    => ['order'=>30,'label'=>lang('just_reps'),    'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],     'admin'=>true],
            // User fields
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('trim_label'),   'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>20]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render($opts=[])
    {
        $today  = biz_date('Y-m-d');
        $filter = "date>'{$today}'";
        if ($opts['reps']) {
            if (getUserCache('role', 'security', 'admin', false, 0)<3) { $filter.= " AND user_id='".getUserCache('profile', 'userID')."'"; }
        }
        $order  = $opts['order']=='desc' ? 'date DESC' : 'date';
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'audit_log', $filter, $order, ['date','user_id','log_entry'], $opts['num_rows']);
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) { // build the list
                $left   = substr($entry['date'], 11).($opts['reps'] ? ' - ' : ' ('.getContactById($entry['user_id']).') ');
                $left  .= viewText($entry['log_entry'], $opts['trim']?$opts['trim']:999);
                $right  = '';
                $action = '';
                $rows[] = viewDashLink($left, $right, $action);
            }
        }
        return ['lists'=>$rows];
      }
}
