<?php
/*
 * Inventory dashboard - Stock levels by month as seen in the journal_history
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
 * @version    7.x Last Update: 2025-05-15
 * @filesource /controllers/inventory/dashboards/inv_stock/inv_stock.php
 */

namespace bizuno;

class inv_stock
{
    public $moduleID = 'inventory';
    public $methodDir= 'dashboards';
    public $code     = 'inv_stock';
    public $secID    = 'inv_mgr';
    public $category = 'inventory';
    public $struc;
    public $lang     = ['title'=>'Inventory Stock Summary',
        'description'=>'Displays your inventory stock levels and valuation.'];

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
        $defInvGL = getModuleCache('inventory', 'settings', 'phreebooks', 'inv_si');
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),     'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),    'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true],
            // User fields
            'glAcct'=> ['order'=>40,'label'=>lang('gl_account'),'clean'=>'cmd',   'attr'=>['type'=>'ledger','value'=>$defInvGL],'types'=>['4']]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $opts - Personalized user/menu options
     * @return modified $layout
     */
    public function render($opts=[])
    {
        if (empty($opts['glAcct'])) { return msgAdd('Please select a valid GL account'); }
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#inv_data').submit();"]];
        $action = BIZUNO_AJAX."&bizRt=inventory/tools/invDataGo";
        $html   = '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $html  .= '<form id="inv_data" action="'.$action.'">'.html5('', $iconExp).'</form>';
        $output = ['divID'=>$this->code."_chart",'type'=>'line','attr'=>['chartArea'=>['left'=>'15%'],'title'=>'GL Acct: '.$opts['glAcct']],'data'=>$this->getData($opts['glAcct'])];
        $js     = "ajaxDownload('inv_data');
var data_{$this->code} = ".json_encode($output).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart{$this->code});
function chart{$this->code}() { drawBizunoChart(data_{$this->code}); };";
        $legend = !getModuleCache('bizuno','settings','general','hide_filters',0) ? ucfirst(lang('filter')).": GL Acct: {$opts['glAcct']}" : '';
        return ['html'=>$html, 'jsHead'=>$js, 'legend'=>$legend];
    }

    public function getData($glAcct)
    {
        $period = calculatePeriod(biz_date('Y-m-d'), true);
        $begPer = $period - 12;
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_history', "gl_account='$glAcct' AND period >= $begPer AND period <= $period", "period");
        $data[] = [lang('period'), lang('value')]; // headings
        foreach ($rows as $row) {
            $bal = $row['beginning_balance'] + $row['debit_amount'] - $row['credit_amount'];
            $data[] = [$row['period'], round($bal)];
        }
        return $data;
    }
}
