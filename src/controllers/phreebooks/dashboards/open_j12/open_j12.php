<?php
/*
 * PhreeBooks Dashboard - Open Customer Sales/Invoices
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
 * @filesource /controllers/phreebooks/dashboards/open_j12/open_j12.php
 */

namespace bizuno;

class open_j12
{
    public  $moduleID = 'phreebooks';
    public  $methodDir= 'dashboards';
    public  $code     = 'open_j12';
    public  $secID    = 'j12_mgr';
    public  $category = 'customers';
    public  $struc;
    public  $lang     = ['title'=>'Unpaid Invoices',
        'description'=> 'Lists unpaid customer Sales/Invoices. A link to review each entry in a separate window is also provided. Settings are available for enhanced security and control.'];
    private $journalID= 12;
    
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
        $this->struc = [ // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[ 0]],'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],'admin'=>true],
            'stores'  => ['order'=>30,'label'=>lang('store_priv'),   'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=> 0],  'admin'=>true],
            'reps'    => ['order'=>40,'label'=>lang('rep_priv'),     'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=> 0],  'admin'=>true],
            // User fields
            'disp_due'=> ['order'=>40,'label'=>lang('disp_due'),     'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0]],
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5], 'options'=>['min'=> 0,'max'=>50,'width'=>100]],
            'limit'   => ['order'=>60,'label'=>lang('hide_future'),  'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>1]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'selOrder','value'=>'desc']]];
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
        msgDebug("\nEntering $this->code:render with opts = ".print_r($opts, true));
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/functions.php', 'getPaymentInfo', 'function');
        $filter= "journal_id=$this->journalID AND closed='0'";
        if (!empty($opts['reps'])  && validateAccess($this->secID, 1)<4){ $filter.= " AND rep_id='".getUserCache('profile', 'userID', false, '0')."'"; }
        if ( isset($opts['limit']) && !empty($opts['limit']))           { $filter.= " AND post_date<='".biz_date('Y-m-d')."'"; }
        if (!empty($opts['stores'])&& validateAccess($this->secID, 1)<4){ $filter.= " AND store_id=".getUserCache('profile', 'store_id'); }
        $order = $opts['order']=='desc' ? 'post_date DESC, invoice_num DESC' : 'post_date, invoice_num';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','journal_id','total_amount','currency','currency_rate','post_date','terms','invoice_num', 'primary_name_b'], $opts['num_rows']);
        $total = 0;
        if (empty($result)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else {
            if (!empty($opts['disp_due'])) { // calculate due date and
                foreach ($result as $key => $entry) { $result[$key]['post_date'] = getTermsDate($entry['terms'], 'c', $entry['post_date']); }
                $result = sortOrder($result, 'post_date', $opts['order']=='desc' ? 'desc' : 'asc');
            }
            foreach ($result as $entry) {
                $currencies->iso  = $entry['currency'];
                $currencies->rate = $entry['currency_rate'];
                $entry['total_amount'] += getPaymentInfo($entry['id'], $entry['journal_id']);
                $total += $entry['total_amount'];
                $left   = viewDate($entry['post_date'])." - ".viewText($entry['primary_name_b'], $opts['trim']);
                $right  = viewFormat($entry['total_amount'], 'currency');
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&jID=$this->journalID&rID={$entry['id']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $rows[] = '<div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div>";
        }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }
}
