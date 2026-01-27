<?php
/*
 * Contacts dashboard - Returns by Status
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
 * @filesource /controllers/contacts/dashboards/rtn_by_status/rtn_by_status.php
 */

namespace bizuno;

class rtn_by_status
{
    public  $moduleID = 'contacts';
    public  $methodID = 'returns';
    public  $methodDir= 'dashboards';
    public  $code     = 'rtn_by_status';
    public  $category = 'quality';
    public  $struc;
    private $status;
    private $dates;
    public  $lang     = ['title' => 'Returns by Status',
        'description'=> 'Lists open customer returns filtered using a user specified status.',
        'total_open' => 'Total Returns:'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->status= getModuleCache('bizuno', 'options', 'return_status');
        $this->dates = localeDates(true, true, true);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[ 0]],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'=>true],
            // User fields
            'num_rows'=> ['order'=>40,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=> 5],    'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>60,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'DESC'],'values'=>$order],
            'status'  => ['order'=>70,'label'=>lang('status'),       'clean'=>'db_field','attr'=>['type'=>'select',  'value'=> 1],    'values'=>viewKeyDropdown($this->status)],
            'range'   => ['order'=>80,'label'=>lang('range'),        'clean'=>'char',    'attr'=>['type'=>'select',  'value'=>'l'],   'values'=>viewKeyDropdown($this->dates)]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render($opts=[])
    {
        msgDebug("\nstatuses = ".print_r($this->status, true));
        $rows  = [];
        $order = $opts['order']=='desc' ? 'post_date DESC' : 'post_date';
        $dates = dbSqlDates($opts['range']);
        $stmt  = dbGetResult("SELECT journal_main.id, post_date, meta_value FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE post_date>='{$dates['start_date']}' AND post_date<'{$dates['end_date']}' AND meta_key='return' ORDER BY $order"); // status='{$opts['status']}
        $result= $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nrows result = ".print_r($result, true));
        foreach ($result as $entry) { // build the list
            $meta = json_decode($entry['meta_value'], true);
            if ($meta['status']<>$opts['status']) { continue; }
            // if the status matches and limit not exceeded then add to list
            $left   = viewDate($entry['post_date'])." - ".viewText($meta['caller_name'], $opts['trim']);
            $right  = '';
            $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=$this->moduleID/returns/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$meta['ref_num']}"]]);
            $rows[] = viewDashLink($left, $right, $action);
            if (!empty($opts['limit']) && sizeof($rows)>=$opts['limit']) { break; } 
        }
        $rows[]= '<div><b>'.$this->lang['total_open']." ".sizeof($rows).'</b></div>';
        if (empty($rows)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        if (empty($opts['status'])) { $opts['status'] = 1; }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? ucfirst(lang('filter')).": {$this->status[$opts['status']]}; {$this->dates[$opts['range']]}" : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }
}
