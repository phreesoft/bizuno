<?php
/*
 * PhreeBooks dashboard - Summary sales/purchases by week/month
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/phreebooks/dashboards/summary_6_12/summary_6_12.php
 */

namespace bizuno;

class summary_6_12
{
    public $moduleID = 'phreebooks';
    public $methodDir= 'dashboards';
    public $code     = 'summary_6_12';
    public $secID    = 'j2_mgr';
    public $category = 'vendors';
    public  $struc;
    private $dates;
    public $lang     = ['title'=>'Sales/Purchases Summary',
        'description'=>'Shows summary results of sales and purchases per week with raw data download capability.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->dates = localeDates(true, true, true, false, true);
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
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users', 'value'=>[0],],  'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles', 'value'=>[-1]],  'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'), 'clean'=>'char', 'attr'=>['type'=>'select','value'=>'l'],'values'=>viewKeyDropdown($this->dates)]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     *
     * @return type
     */
    public function render($opts=[])
    {
        $total_v= $total_c = 0;
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#sum_6_12').submit();"]];
        $data   = $this->dataSales($opts['range']);
        $action = BIZUNO_URL_AJAX."&bizRt=phreebooks/tools/jrnlData&code=6_12&range={$opts['range']}";
        $html   = '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $html  .= '<form id="sum_6_12" action="'.$action.'">'.html5('', $iconExp).'</form>';
        $js     = "ajaxDownload('sum_6_12');
function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', '".jsLang('date')."');
    data.addColumn('string', '".jsLang('purchases')."');
    data.addColumn('string', '".jsLang('sales')."');
    data.addRows([";
        foreach ($data as $date => $values) {
            $total_v += $values['v'];
            $total_c += $values['c'];
            $js .= "['".viewFormat($date, 'date')."','".viewFormat($values['v'],'currency')."','".viewFormat($values['c'],'currency')."'],";
        }
        $js .= "['".jslang('total')."','".viewFormat($total_v,'currency')."','".viewFormat($total_c,'currency')."']]);
    data.setColumnProperties(0, {style:'font-style:bold;font-size:22px;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'90%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
        $legend = getModuleCache('bizuno','settings','general','hide_filters',0) ? ucfirst(lang('filter')).": {$this->dates[$opts['range']]}" : '';
        return ['html'=>$html, 'jsHead'=>$js, 'legend'=>$legend];
    }

    /**
     *
     * @param type $range
     * @return type
     */
    public function dataSales($range='l')
    {
        msgDebug("\nEntering dataSales range = $range");
        $dates  = dbSqlDates($range);
        $arrIncs= $this->createDateRange($dates['start_date'], $dates['end_date']);
        $incKeys= array_keys($arrIncs);
        $crit   = $dates['sql']." AND journal_id IN (12,13)";
        $this->setData($arrIncs, $incKeys, 'c', $crit);
        $crit   = $dates['sql']." AND journal_id IN (6,7)";
        $this->setData($arrIncs, $incKeys, 'v', $crit);
        msgDebug("\nreturning with results = ".print_r($arrIncs, true));
        return $arrIncs;
    }

    /**
     *
     * @param type $startDate
     * @param type $endDate
     * @param type $inc
     * @return int
     */
    private function createDateRange($startDate, $endDate, $inc='w')
    {
        msgDebug("\nEntering createDateRange, start = $startDate, end = $endDate");
        $begin    = new \DateTime($startDate);
        $end      = new \DateTime($endDate);
        $interval = new \DateInterval($inc=='m'?'P1M':'P7D'); // 1 Month : 1 Day
        $dateRange= new \DatePeriod($begin, $interval, $end);
        $range    = [];
        foreach ($dateRange as $date) { $range[$date->format('Y-m-d')] = ['c'=>0,'v'=>0]; }
        return $range;
    }

    /**
     *
     * @param type $arrIncs
     * @param type $incKeys
     * @param type $type
     * @param type $crit
     */
    private function setData(&$arrIncs, $incKeys, $type, $crit)
    {
        $result  = dbGetMulti(BIZUNO_DB_PREFIX."journal_main", $crit, 'post_date', ['journal_id','post_date','total_amount']);
        foreach ($result as $row) {
            $value = in_array($row['journal_id'], [7,13]) ? -$row['total_amount'] : $row['total_amount'];
            foreach ($incKeys as $key => $date) {
                if ($row['post_date'] < $date) {
                    $idx = $incKeys[$key-1];
                    $arrIncs[$idx][$type] += $value;
                    break;
                }
            }
        }
    }
}
