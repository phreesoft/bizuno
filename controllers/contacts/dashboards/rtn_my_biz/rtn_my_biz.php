<?php
/*
 * Contacts module dashboard - Pie chart dashboard for return by SKU for the past 12 months where My Business is at fault (Preventable)
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
 * @version    7.x Last Update: 2025-07-06
 * @filesource /controllers/contacts/dashboards/rtn_my_biz/rtn_my_biz.php
 */

namespace bizuno;

class rtn_my_biz
{
    public $moduleID = 'contacts';
    public $methodID = 'returns';
    public $methodDir= 'dashboards';
    public $code     = 'rtn_my_biz';
    public $category = 'quality';
    public  $struc;
    private $dates;
    public $lang     = ['title'=>'Returns By SKU (Preventable)',
        'description'=>'Lists return metrics by SKU where My Business (you) are at fault. These returns are considered preventable.'];

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
            'users' => ['order'=>10,'label'=>lang('users'),    'clean'=>'array','attr'=>['type'=>'users', 'value'=>[0]],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),   'clean'=>'array','attr'=>['type'=>'roles', 'value'=>[-1]],'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'),    'clean'=>'char', 'attr'=>['type'=>'select','value'=>0],   'values'=>viewKeyDropdown($this->dates)]];
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
        $cData = $this->rtnSKUs($opts['range']);
        $html  = '<div style="width:100%" id="'.$this->code.'_chart0"></div>';
        $output= ['divID'=>$this->code."_chart0",'type'=>'pie','attr'=>['title'=>"Returns by SKU = ".$cData['totalRtn']." entries"],'data'=>$cData['chart']];
        $js    = "ajaxDownload('form{$this->code}');
var data0_{$this->code} = ".json_encode($output, JSON_UNESCAPED_UNICODE).";
google.charts.load('current', {'packages':['corechart']});
google.charts.setOnLoadCallback(chart0{$this->code});
function chart0{$this->code}() { drawBizunoChart(data0_{$this->code}); };";
        return ['html'=>$html, 'jsHead'=>$js];
    }

    /**
     * Generates the pie chart data array
     * @param integer $pieces - number of pie pieces
     * @return array - structure with the pie chart data
     */
    public function rtnSKUs($range)
    {
        msgDebug("\nEntering rtnSKUs with range = $range");
        $dates = dbSqlDatesQrtrs($range, 'post_date'); // found date
        $stmt = dbGetResult("SELECT journal_main.id, post_date, meta_value FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE {$dates['sql']} AND meta_key='return'");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nrows result = ".print_r($rows, true));
//      $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "post_date>='{$dates['start_date']}' AND post_date<'{$dates['end_date']}' AND preventable='1'", '', ['creation_date','close_details']); // ,'fault'
        $output= [];
        foreach ($rows as $row) { // preventable='1'
            $meta = json_decode($row['meta_value'], true);
            if (empty($meta)) { continue; }
            foreach ($meta['details'] as $item) {
                if (empty($item['sku'])) { continue; }
                if (!isset($output[$item['sku']])) { $output[$item['sku']] = ['qty'=>0,'desc'=>$item['desc']]; } // ,'fault'=>[]
//              $output[$item['sku']]['qty'] += intval($item['qty']); // gets total pieces returned, can be misleading, i.e. some SKUs come back in qty if not needed
                $output[$item['sku']]['qty']++;
//              if (!isset($output[$item['sku']]['fault'][$row['fault']])) { $output[$item['sku']]['fault'][$row['fault']] = 0; }
//              $output[$item['sku']]['fault'][$row['fault']]++; // fault code index
            }
        }
        arsort($output);
        $cnt   = $rTotal = 0;
        $struc = [];
        $struc['chart'][]= [lang('sku'), lang('total')]; // headings
        foreach ($output as $vals) {
            if ($cnt < $pieces) { $struc['chart'][] = [$vals['desc'], $vals['qty']]; }
            else                { $rTotal += $vals['qty']; }
            $cnt++;
        }
        $struc['chart'][] = [lang('other'), $rTotal];
        $struc['totalRtn']= sizeof($output);
        msgDebug("\nOutput = ".print_r($struc, true));
        return $struc;
    }
}
