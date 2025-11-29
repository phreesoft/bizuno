<?php
/*
 * Phreebooks dashboard - Late Items from Vendor Purchase Orders
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
 * @version    7.x Last Update: 2025-11-29
 * @filesource /controllers/phreebooks/dashboards/late_j04/late_j04.php
 */

namespace bizuno;

class late_j04
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'late_j04';
    public  $secID    = 'j4_mgr';
    public  $category = 'vendors';
    public  $struc;
    public  $lang     = ['title'=>'Late Purchase Order Items',
        'description'=> 'Lists items from purchase orders that have not been received as of today that have a due date before today. A link to review each entry in a separate window is also provided. Settings are available for enhanced security and control.'];
    private $journalID= 4;

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
            'store_id'=> ['order'=>60,'label'=>lang('store_id'),     'clean'=>'integer', 'attr'=>['type'=>'select',  'value'=>-1],    'values' =>viewStores()],
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
        global $currencies;
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/contacts/functions.php',  'getContactID', 'function');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php','getInvoiceInfo','function');
        $today = biz_date('Y-m-d');
        $filter= "journal_id=$this->journalID AND closed='0'";
        if ($opts['reps'] && getUserCache('profile', 'userID', false, '0')) {
            if (empty(getUserCache('role', 'administrate'))) { $filter.= " AND rep_id='".getUserCache('profile', 'userID', false, '0')."'"; }
        }
        if (!empty(getUserCache('profile', 'restrict_store'))) { $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        elseif ($opts['store_id']>-1)                { $filter.= " AND store_id=".$opts['store_id']; }
        $order = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','journal_id','total_amount','currency','currency_rate','post_date','invoice_num', 'primary_name_b']);
        $total = $counter = 0;
        if (empty($result)) { $rows[] = "<span>".lang('no_results')."</span>"; }
        else {
            foreach ($result as $entry) { // build the list
                $items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id={$entry['id']} AND gl_type='itm' AND date_1<'$today'", '', ['id', 'sku', 'qty', 'description', 'credit_amount', 'debit_amount','date_1']);
                foreach ($items as $item) {
                    $item['total_amount'] = $item['debit_amount'] - $item['credit_amount'];
                    $filled= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', "SUM(qty) AS qty", "item_ref_id={$item['id']} AND gl_type='itm'", false);
                    if ($item['qty'] <= $filled || empty($item['total_amount'])) { continue; }
                    $currencies->iso  = $entry['currency'];
                    $currencies->rate = $entry['currency_rate'];
                    $total += $item['total_amount'];
                    $left   = viewDate($item['date_1'])." - ".viewText($item['description'], $opts['trim']);
                    $right  = viewFormat($item['total_amount'], 'currency');
                    $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=phreebooks/main/manager&jID=$this->journalID&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                    $rows[] = viewDashLink($left, $right, $action);
                    $counter++;
                    if (!empty($opts['num_rows']) && $counter >= $opts['num_rows']) { break; }
                }
                if (!empty($opts['num_rows']) && $counter >= $opts['num_rows']) { break; }
            }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $rows[] = '<div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div>";
        }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": ".getContactID($opts['store_id']).", ".lang('sort')." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }
}
