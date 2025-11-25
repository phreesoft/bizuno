<?php
/*
 * Bizuno Accounting - Dashboard - Unprinted Sales Orders
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
 * @version    7.x Last Update: 2025-11-24
 * @filesource /controllers/phreebooks/dashboards/unprinted_orders/unprinted_orders.php
 */

namespace bizuno;

class unprinted_orders
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'unprinted_orders';
    public  $secID    = 'j10_mgr';
    public  $category = 'customers';
    public  $struc;
    public  $lang     = ['title'=>'Unprinted Sales Orders',
        'description'=> 'Lists unprinted sales orders with button to print directly from the dashboard. Monitors the printed flag in the journal main database table which is set when a form is generated with the Set Printed option enabled. Settings are available for enhanced security and control.'];

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
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',  'value'=>[0],],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',  'value'=>[-1]],  'admin'=>true],
            // User fields
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner','value'=>5],     'options'=>['min'=> 0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>60,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner','value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select', 'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render($opts=[])
    {
        $order  = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        if ($opts['num_rows']) { $order .= " LIMIT ".$opts['num_rows']; }
        $filter = "journal_id=12 AND closed='0' AND printed='0'";
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        $result = dbGetMulti(BIZUNO_DB_PREFIX."journal_main", $filter, $order);
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) { // build the list
                $left   = viewDate($entry['post_date']).' - '.viewText($entry['primary_name_b'], $opts['trim']);
                $right  = html5('', ['icon'=>'email','events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=cust:j12&date=a&xfld=journal_main.id&xcr=equal&xmin={$entry['id']}');"]]);
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/main/manager&jID=12&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
        }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }
}
