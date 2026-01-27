<?php
/*
 * Bizuno extension extISO9001 dashboard - My Open Corrective Actions
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
 * @version    7.x Last Update: 2025-11-24
 * @filesource /controllers/quality/dashboards/my_qual_obj/my_qual_obj.php
 */

namespace bizuno;

class my_qual_obj
{
    public  $moduleID  = 'quality';
    public  $methodID  = 'extISO9001';
    public  $methodDir = 'dashboards';
    public  $code      = 'my_qual_obj';
    public  $secID     = 'extISO9001';
    private $metaPrefix= 'quality_objective';
    public  $category  = 'quality';
    public  $struc;
    public  $lang      = ['title'=>'My Quality Objectives',
        'description'=>'Lists the open Quality Objectives assigned to the user with links to edit and review the details.',
        'total_open' => 'Total Objectives:'];

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
            // User fields
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        $rows  = [];
        $cID   = getUserCache('profile', 'userID');
        $result= getMetaCommon($this->metaPrefix);
        msgDebug("\nread meta = ".print_r($result, true));
        foreach ($result as $entry) { // build the list
            if (in_array($entry['status'], ['85']) || $entry['entered_by']<>$cID) { continue; }
            $left   = viewDate($entry['creation_date'])." - ".viewText($entry['title'], $opts['trim']);
            $right  = '';
            $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=$this->moduleID/objectives/manager&rID={$entry['_rID']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['ref_num']}"]]);
            $rows[] = viewDashLink($left, $right, $action);
        }
        if (empty($rows)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else {
            $output = sortOrder($rows, 'ref_num', strtolower($opts['order'])=='asc'?'asc':'desc');
            $rows[] = '<div><b>'.$this->lang['total_open']." ".sizeof($output)."</b></div>"; }
        return ['lists'=>$rows];
      }
}
