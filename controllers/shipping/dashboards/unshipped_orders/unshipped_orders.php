<?php
/*
 * Bizuno extension shipping dashboard - Unshipped Orders
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
 * @filesource /controllers/shipping/dashboards/unshipped_orders/unshipped_orders.php
 */

namespace bizuno;

class unshipped_orders
{
    public $moduleID = 'shipping';
    public $methodDir= 'dashboards';
    public $code     = 'unshipped_orders';
    public $category = 'customers';
    protected $secID = 'shipping';
    public  $struc;
    public $lang     = ['title'=>'Unshipped Orders',
        'description' => 'Lists unshipped sales.'];

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
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'  =>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'  =>true],
            'reps'    => ['order'=>30,'label'=>lang('just_reps'),    'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],     'admin'  =>true],
            // User fields
            'store_id'=> ['order'=>40,'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>'select',  'value'=>-1],    'values' =>viewStores()],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5],     'options'=>['min'=>0,'max'=>50,'width'=>100]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],    'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'select',  'value'=>'desc'],'values' =>$order]];
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
        $order   = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $filter  = "journal_id=12 AND waiting='1' AND CHAR_LENGTH(method_code) > 2";
        if (!empty(getUserCache('profile', 'restrict_store')) && sizeof(getModuleCache('bizuno', 'stores')) > 1) {
            $filter .= " AND store_id=".getUserCache('profile', 'store_id', false, -1);
        } elseif ($opts['store_id'] > -1) {
            $filter .= " AND store_id='{$opts['store_id']}'";
        }
        $result  = dbGetMulti(BIZUNO_DB_PREFIX."journal_main", $filter, $order, ['id','post_date','journal_id','invoice_num','store_id','method_code','primary_name_b'], $opts['num_rows']);
        $carriers= getMetaMethod('carriers');
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) { // build the list
                $frmGrp = "cust:j{$entry['journal_id']}";
                $temp   = explode(':', $entry['method_code']);
                $store  = sizeof(getModuleCache('bizuno', 'stores')) > 1 ? viewFormat($entry['store_id'], 'storeID').' ' : '';
                $carrier= "[$store".(isset($carriers[$temp[0]]['acronym']) ? $carriers[$temp[0]]['acronym'] : $entry['method_code']).']';
                $left   = biz_date('m/d', strtotime($entry['post_date']))." #{$entry['invoice_num']} $carrier ".viewText($entry['primary_name_b'], $opts['trim']);
                $right  = html5('', ['icon'=>'print','size'=>'small','events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group={$frmGrp}&date=a&xfld=journal_main.id&xcr=equal&xmin={$entry['id']}');"]]);
                $action = ''; //html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/main/manager&jID=12&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
            $total  = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'COUNT(*) AS total', $filter, false);
            $rows[] = viewDashLink('<b>'.lang('total').'</b>', '<b>'.sprintf(lang('num_of_num'), sizeof($result), $total).'</b>');
        }
        $js = "setTimeout(function () { bizPanelRefresh('{$this->code}'); }, 300*1000);";
        return ['lists'=>$rows, 'jsHead'=>$js];
    }
}
