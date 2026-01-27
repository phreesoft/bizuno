<?php
/*
 * Contacts dashboard - Past Due Invoices
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
 * @filesource /controllers/contacts/dashboards/past_due_j12/past_due_j12.php
 */

namespace bizuno;

class past_due_j12
{
    public  $moduleID = 'contacts';
    public  $methodDir= 'dashboards';
    public  $code     = 'past_due_j12';
    public  $secID    = 'j12_mgr';
    public  $category = 'banking';
    public  $struc;
    private $choices;
    public  $lang     = ['title'=>'Past Due Invoices',
        'description'=> 'Lists past due customer invoices. A link to edit the customer record is provided to set/clear account status. Settings are available for enhanced security and control.'];
    private $journalID= 12;
    
    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->choices = getModuleCache('contacts','statuses');
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users'   => ['order'=>10,'label'=>lang('users'),        'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],],  'admin'=>true],
            'roles'   => ['order'=>20,'label'=>lang('groups'),       'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]],  'admin'=>true],
            // User fields
            'num_rows'=> ['order'=>50,'label'=>lang('limit_results'),'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>5], 'options'=>['min'=> 0,'max'=>50,'width'=>100]],
            'limit'   => ['order'=>60,'label'=>lang('hide_future'),  'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>1]],
            'trim'    => ['order'=>70,'label'=>lang('truncate_fit'), 'clean'=>'integer', 'attr'=>['type'=>'spinner', 'value'=>20],'options'=>['min'=>10,'max'=>80,'width'=>100]],
            'order'   => ['order'=>80,'label'=>lang('sort_order'),   'clean'=>'db_field','attr'=>['type'=>'selOrder']]];
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
        $today = biz_date();
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'getPaymentInfo', 'function');
        $filter= "journal_id=$this->journalID AND closed='0'";
        $order = $opts['order']=='desc' ? 'post_date DESC' : 'post_date';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, $order, ['id','journal_id','total_amount','currency','currency_rate','post_date','terms','invoice_num','contact_id_b','primary_name_b']);
        msgDebug("\n today = $today and db read = ".print_r($result, true));
        $total = 0;
        if (empty($result)) { $rows[] = '<span>'.lang('no_results').'</span>'; }
        else {
            $output = [];
            foreach ($result as $row) {
                $dueDate = getTermsDate($row['terms'], 'c', $row['post_date']);
                if ($dueDate >= $today) { continue; } // not past due
                $row['post_date'] = $dueDate; //, $opts['num_rows']
                $output[] = $row;
                if (!empty($opts['num_rows']) && sizeof($output) >= $opts['num_rows']) { break; }
            }
            $values = sortOrder($output, 'post_date', $opts['order']=='desc' ? 'desc' : 'asc');
            foreach ($values as $entry) {
                $currencies->iso  = $entry['currency'];
                $currencies->rate = $entry['currency_rate'];
                $entry['total_amount'] += getPaymentInfo($entry['id'], $entry['journal_id']);
                $total += $entry['total_amount'];
                $left   = viewDate($entry['post_date'])." - ".$this->rowStyler($entry['contact_id_b'], viewText($entry['primary_name_b'], $opts['trim']));
                $right  = viewFormat($entry['total_amount'], 'currency');
                $action = html5('', ['events'=>['onClick'=>"winHref(bizunoHome+'?bizRt=contacts/main/manager&rID={$entry['contact_id_b']}');"],'attr'=>['type'=>'button','value'=>"#{$entry['invoice_num']}"]]);
                $rows[] = viewDashLink($left, $right, $action);
            }
            $currencies->iso  = getDefaultCurrency();
            $currencies->rate = 1;
            $rows[] = '<div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div>";
        }
        $legend = !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? ucfirst(lang('filter')).": ".ucfirst(lang('sort'))." ".strtoupper($opts['order']).(!empty($opts['num_rows']) ? " ({$opts['num_rows']});" : '') : '';
        return ['lists'=>$rows, 'legend'=>$legend];
    }

    private function rowStyler($cID, $cText)
    {
        $cStatus= dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'inactive', "id=$cID");
        foreach ($this->choices as $status) {
            if (empty($status['color'])) { continue; }
            if ($status['id']==$cStatus) { return '<span class="row-'.$status['color'].'">'.$cText.'</span>'; }
        }
        return $cText;
    }
}
