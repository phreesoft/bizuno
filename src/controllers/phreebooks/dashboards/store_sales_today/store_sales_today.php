<?php
/*
 * Dashboard for today's sales by branch
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
 * @filesource /controllers/phreebooks/dashboards/store_sales_today/store_sales_today.php
 */

namespace bizuno;

class store_sales_today
{
    public $moduleID = 'phreebooks';
    public $methodID = 'extStores';
    public $methodDir= 'dashboards';
    public $code     = 'store_sales_today';
    public $secID    = 'j12_mgr';
    public $category = 'customers';
    public $noSettings= true;
    public  $struc;
    public $lang     = ['title'=>'Today\'s Sales by Store',
        'description'=> 'Lists today\'s sales totals by Store. It is assumed that all currencies are in the default ISO. Settings are available for enhanced security and control.'];

    function __construct()
    {
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),    'clean'=>'array',  'attr'=>['type'=>'users',   'value'=>[0],], 'admin' =>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),   'clean'=>'array',  'attr'=>['type'=>'roles',   'value'=>[-1]], 'admin' =>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'),'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0],    'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render()
    {
        global $currencies;
        $currencies->iso  = getDefaultCurrency();
        $currencies->rate = 1;
        $totals= [];
        $total = 0;
        $html  = '<div><div id="'.$this->code.'_attr" style="display:none">'.lang('msg_no_settings')."</div>";
        $filter= "journal_id IN (12,13) AND post_date='".biz_date('Y-m-d')."'";
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $filter, '', ['journal_id','total_amount','store_id']);
        $html .= html5('', ['classes'=>['easyui-datalist'],'attr'=>['type'=>'ul']])."\n";
        foreach ($result as $row) {
            if (!isset($totals[$row['store_id']])) { $totals[$row['store_id']] = ['cnt'=>0, 'total'=>0]; }
            $totals[$row['store_id']]['total'] += $row['journal_id'] == 13 ? -$row['total_amount'] : $row['total_amount'];
            $totals[$row['store_id']]['cnt']++;
        }
        if (sizeof($totals) > 0) {
            foreach ($totals as $store_id => $sales) {
                $html .= html5('', ['attr'=>['type'=>'li']]).'<span style="float:left">'.viewFormat($store_id, 'storeID')." ({$sales['cnt']})</span>";
                $html .= '<span style="float:right">'.viewFormat($sales['total'], 'currency').'</span></li>';
                $total += $sales['total'];
            }
            $html .= '<li><div style="float:right"><b>'.viewFormat($total, 'currency').'</b></div><div style="float:left"><b>'.lang('total')."</b></div></li>\n";
        } else {
            $html .= '<li><span>'.lang('no_results')."</span></li>";
        }
        $html .= '</ul></div>';
        return ['html'=>$html];
    }
}
