<?php
/*
 * Contacts module dashboard - Dashboard for returns by SKU
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
 * @filesource /controllers/contacts/dashboards/return_metrics/return_metrics.php
 */

namespace bizuno;

class return_metrics
{
    public $moduleID = 'contacts';
    public $methodID = 'returns';
    public $methodDir= 'dashboards';
    public $code     = 'return_metrics';
    public $category = 'quality';
    public  $struc;
    private $dates;
    public $lang     = ['title'=>'Returns By SKU',
        'description'=>'Lists return metrics by SKU over the past 12 months.',
        'chart_title' => 'Returns by SKU for: %s (%s total)'];

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
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users', 'value'=>[0],],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles', 'value'=>[-1]],'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'), 'clean'=>'char', 'attr'=>['type'=>'select','value'=>0],   'values'=>viewKeyDropdown($this->dates)]];
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
        $menu  = clean('menu', 'db_field', 'get');
        $cData = $this->getData($opts['range']);
        $title = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['totalRtn']);
        $html  = '<div style="width:100%" id="'.$this->code.'_chart0"></div>';
        $output= ['divID'=>$this->code."_chart0",'type'=>'pie','event'=>"chart0{$this->code}Select",'attr'=>['title'=>$title],'data'=>$cData['chart']];
        $js    = "ajaxDownload('form{$this->code}');
var data0_{$this->code} = ".json_encode($output, JSON_UNESCAPED_UNICODE).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart0{$this->code});
function chart0{$this->code}() { drawBizunoChart(data0_{$this->code}); }
function chart0{$this->code}Select(chart, data) {
    var cData = chart.getSelection();
    winHref(bizunoHome+'?bizRt=phreebooks/$this->methodID/manager&menu=$menu&range={$opts['range']}&mgrAction=$this->code&rIDList='+cData[0].row);
}";
        return ['html'=>$html, 'jsHead'=>$js];
    }

    /**
     * Generates the pie chart data array
     * @return array - structure with the pie chart data
     */
    public function getData($range, $slices=10)
    {
        $dates = dbSqlDatesQrtrs($range, 'post_date');
        $raw   = $struc = $fTotals= [];
        $cnt   = $oTotal= $rTotal = 0;
        $stmt = dbGetResult("SELECT journal_main.id FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id WHERE meta_key='return' AND {$dates['sql']}");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nrows result = ".print_r($rows, true));
        foreach ($rows as $row) {
            $items = dbGetMulti(BIZUNO_DB_PREFIX."journal_item WHERE ref_id={$row['id']} AND gl_type='itm'");
            msgDebug("\nitem row = ".print_r($items, true));
            if (empty($items)) { continue; }
            $rTotal++;
            foreach ($items as $item) {
                if ( empty($item['sku'])) { continue; }
                if (!isset($raw[$item['sku']])) { $raw[$item['sku']] = ['sku'=>$item['sku'], 'qty'=>0, 'desc'=>$item['description']]; }
                $raw[$item['sku']]['qty']++;
                $raw[$item['sku']]['rID'][] = $row['id'];
            }
        }
        $output = sortOrder($raw, 'qty', 'desc');
        $struc['chart'][] = [lang('sku'), lang('total')]; // headings
        $fTotals[] = lang('total');
        foreach ($output as $vals) {
            if ($cnt < $slices) { $struc['chart'][] = [$vals['sku'], $vals['qty']]; }
            else                { $oTotal += $vals['qty']; }
            $cnt++;
        }
        $struc['chart'][] = [lang('other'), $oTotal];
        $struc['data']    = array_values($output);
        $struc['totalRtn']= $rTotal;
        msgDebug("\nOutput = ".print_r($struc, true));
        return $struc;
    }
}
