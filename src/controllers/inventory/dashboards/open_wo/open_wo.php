<?php
/*
 * Bizuno extension inventory production dashboard - Open Work Orders
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
 * @filesource /controllers/inventory/dashboards/open_wo/open_wo.php
 */

namespace bizuno;

class open_wo
{
    public  $moduleID = 'inventory';
    public  $methodID = 'build';
    public  $methodDir= 'dashboards';
    public  $code     = 'open_wo';
    public  $secID    = 'build';
    public  $category = 'inventory';
    private $journalID= 32;
    public  $struc;
    public  $lang     = ['title'=>'Open Work Orders',
        'description' => 'Lists the open Work Orders with links to edit and review the details.',
        'total_open' => 'Total Open WOs:'];

    function __construct()
    {
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
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>'select',  'value'=>-1],    'values' =>viewStores()],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=> 5],    'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render($opts=[])
    {
        msgDebug("\nEntering $this->code:render with opts = ".print_r($opts, true));
        $order   = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $filter= "journal_id=$this->journalID AND closed='0'";
        if ($opts['store_id'] > -1) { $filter .= " AND store_id='{$opts['store_id']}'"; }
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','journal_id','post_date','invoice_num', 'description'], $opts['num_rows']);
        if (empty($result)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else {
            foreach ($result as $entry) {
                $left   = viewDate($entry['post_date'])." - ".viewText($entry['description'], $opts['trim']);
                $right  = '';
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=$this->moduleID/$this->methodID/manager&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
        }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
      }
}
