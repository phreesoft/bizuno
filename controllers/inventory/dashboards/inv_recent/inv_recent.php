<?php
/*
 * PhreeBooks Dashboard - Today's Vendor Purchases
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
 * @version    7.x Last Update: 2026-03-01
 * @filesource /controllers/inventory/dashboards/inv_recent/inv_recent.php
 */

namespace bizuno;

class inv_recent
{
    public $moduleID = 'inventory';
    public $methodDir= 'dashboards';
    public $code     = 'inv_recent';
    public $secID    = 'j6_mgr';
    public $category = 'inventory';
    public  $struc;
    public $lang     = ['title'=>'Items Recently Received',
        'description'=>'Displays inventory items that were received in the last week.'];
    private $journalID= 12;

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }

    private function fieldStructure()
    {
        $order = [['id'=>'asc','text'=>lang('decreasing')],['id'=>'desc','text'=>lang('decreasing')]];
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'=>true],
            'reps'    => ['order'=>30,'label'=>lang('just_reps'),    'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],     'admin'=>true],
            // User fields
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>'select','value'=>false],   'values'=>viewStores()],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values'=>$order]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render($opts=[])
    {
        $rows  = [];
        $today = biz_date('Y-m-d');
        $lstWk = localeCalculateDate($today, -7);
        $filter= "journal_id=6 AND post_date>'$lstWk' AND post_date<='$today'";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($opts['store_id'] > -1) {
            $filter .= " AND store_id='{$opts['store_id']}'";
        }
        $order = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','post_date','purch_order_id','store_id'], $opts['num_rows']);
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $row) { // row has store id if that matters
                $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$row['id']} AND gl_type='itm' AND sku<>''");
                foreach ($items as $item) {
                    $type   = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "sku='".addslashes($item['sku'])."'");
                    if (in_array($type, ['sv','lb'])) { continue; }
                    $elDOM  = ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/main/manager&jID=6&rID={$row['id']}');"],'attr'=>['type'=>'button','value'=>"#{$row['purch_order_id']}"]];
                    $store  = sizeof(getModuleCache('bizuno', 'stores')) > 1 ? viewFormat($opts['num_rows'], 'storeID').' ' : '';
                    $left   = biz_date('m/d', strtotime($row['post_date']))." $store - ({$item['qty']}) ".viewText($item['sku'], $opts['trim']);
                    $right  = ''; // viewText($item['qty']);
                    $action = html5('', $elDOM);
                    $rows[] = viewDashLink($left, $right, $action);
                }
            }
        }
        $legend = ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '');
        return ['lists'=>$rows, 'legend'=>$legend];
    }
}
