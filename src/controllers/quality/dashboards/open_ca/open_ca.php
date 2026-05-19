<?php
/*
 * Bizuno extension extISO9001 dashboard - Open Corrective Actions
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
 * @filesource /controllers/quality/dashboards/open_ca/open_ca.php
 */

namespace bizuno;

class open_ca
{
    public  $moduleID = 'quality';
    public  $methodID = 'extISO9001';
    public  $methodDir= 'dashboards';
    public  $code     = 'open_ca';
    public  $secID    = 'extISO9001';
    public  $category = 'quality';
    private $journalID= 30;
    public  $struc;
    private $dates;
    public  $lang     = ['title' => 'Quality Tickets Pending Review',
        'description' => 'Lists open Corrective Actions with options to edit.',
        'total_open' => 'Total Open Corrective Actions: '];

    function __construct()
    {
        $this->dates = localeDates(true, true, true);
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
            // User fields
            'range'   => ['order'=>40,'label'=>lang('range'),        'clean'=>'char',    'attr'=>['type'=>'select',  'value'=>'w'],   'values' =>viewKeyDropdown($this->dates)],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=> 0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values' =>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        $rows  = [];
        $dates = dbSqlDates($opts['range'], 'post_date');
        $filter= "post_date>='{$dates['start_date']}' AND post_date<'{$dates['end_date']}' AND journal_id=$this->journalID AND closed='0' AND printed=99";
        $order = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','invoice_num','description','post_date'], $opts['num_rows']);
        foreach ($result as $entry) { // build the list
            $left   = viewDate($entry['post_date'])." - ".viewText($entry['description'], $opts['trim']);
            $right  = '';
            $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=$this->moduleID/correctives/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
            $rows[] = viewDashLink($left, $right, $action);
        }
        $total = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'COUNT(*) AS total', $filter, false);
        if (empty($rows)) { $rows[] = "<span>".lang('no_results').'</span>'; }
        else { $rows[] = '<div><b>'.$this->lang['total_open']." $total</b></div>"; }
        return ['lists'=>$rows];
      }
}
