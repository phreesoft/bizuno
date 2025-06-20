<?php
/*
 * extISO9001 module dashboard - Dashboard for Quality Issues by SKU
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
 * @version    7.x Last Update: 2025-06-20
 * @filesource /controllers/quality/dashboards/qa_by_sku/qa_by_sku.php
 */

namespace bizuno;

class qa_by_sku
{
    public $moduleID = 'quality';
    public $pageID   = 'tickets';
    public $methodID = 'extISO9001'; // needs to be this as the security is based on the old extension name
    public $methodDir= 'dashboards';
    public $code     = 'qa_by_sku';
    public $secID    = 'extISO9001';
    public $category = 'quality';
    public  $struc;
    private $dates;
    public $lang     = ['title'=>'Quality Tickets by SKU',
        'description'=>'Pie chart list of quality issues by SKU for trend analysis',
        'chart_title' => 'Total Issues Found %s = %s'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->dates = [0=>lang('dates_quarter'), 1=>lang('dates_lqtr'), 2=>lang('quarter_neg2'), 3=>lang('quarter_neg3'), 4=>lang('quarter_neg4'), 5=>lang('quarter_neg5')];
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
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'), 'clean'=>'char', 'attr'=>['type'=>'select','value'=>false],'values'=>viewKeyDropdown($this->dates)]];
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
        $cData = $this->getData($opts['range']);
        $title = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['totalRtn']);
        $html  = '<div style="width:100%" id="'.$this->code.'_chart0"></div>';
        $output= ['divID'=>$this->code."_chart0",'type'=>'pie','event'=>"chart0{$this->code}Select",'attr'=>['title'=>$title],'data'=>$cData['chart']];
        $js    = "var data0_{$this->code} = ".json_encode($output, JSON_UNESCAPED_UNICODE).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart0{$this->code});
function chart0{$this->code}() { drawBizunoChart(data0_{$this->code}); }
function chart0{$this->code}Select(chart, data) {
    var cData = chart.getSelection();
    winHref(bizunoHome+'&bizRt=$this->moduleID/$this->pageID/manager&mgrAction=$this->code&rIDList='+cData[0].row);
}";
        return ['html'=>$html, 'jsHead'=>$js];
    }

    /**
     * Pulls the data from the db, sorts and builds chart data array
     * @return array - chart data ready for Google chart API
     */
    public function getData($range, $slices=10)
    {
        msgDebug("\nEntering getData with range = $range and slices = $slices");
        $dates = dbSqlDatesQrtrs($range, 'post_date'); // found date
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $dates['sql']." AND journal_id=30", '', ['id']);
        $raw   = [];
        msgDebug("\nRead the following rows to process: ".print_r($rows, true));
        foreach ($rows as $row) {
            // get the meta and process
            $meta = dbMetaGet(0, 'qa_ticket', 'journal', $row['id']);
            if (empty($meta['sku_id'])) { continue; } // no SKU 
            if (!isset($raw[$meta['sku_id']])) { $raw[$meta['sku_id']] = ['skuID'=>$meta['sku_id'],'title'=>$meta['title'],'cID'=>$meta['contact_id'], 'cnt'=>0, 'qty'=>0, 'rID'=>[]]; }
            $raw[$meta['sku_id']]['qty'] +=$meta['audit_start_by'];
            $raw[$meta['sku_id']]['rID'][]= $row['id'];
            $raw[$meta['sku_id']]['cnt']++;
        }
        $output= sortOrder($raw, 'cnt', 'desc');
        $cnt   = $rTotal  = 0;
        $struc = $fTotals = [];
        $struc['chart'][] = [lang('sku'), lang('total')]; // headings
        $fTotals[] = lang('total');
        msgDebug("\nFirst pass output = ".print_r($output, true));
        foreach ($output as $row) { // build pie chart data
            $skuID = intval($row['skuID']);
            if (!empty($skuID)) {
                $skuDesc = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id=$skuID")." [".lang('qty').": {$row['qty']}]";
            } else {
                $skuDesc = $row['title'];
            }
            if ($cnt < $slices) { $struc['chart'][] = [$skuDesc, $row['cnt']]; }
            else { $rTotal += $row['cnt']; }
            $cnt++;
        }
        $struc['chart'][] = [lang('other'), $rTotal];
        $struc['data']    = array_values($output);
        $struc['totalRtn']= sizeof($rows);
        msgDebug("\nReturning output = ".print_r($struc, true));
        return $struc;
    }
}
