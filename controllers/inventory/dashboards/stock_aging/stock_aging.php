<?php
/*
 * Inventory dashboard - list aging stock that needs attention
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
 * @filesource /controllers/inventory/dashboards/stock_aging/stock_aging.php
 */

namespace bizuno;

class stock_aging
{
    public $moduleID = 'inventory';
    public $methodDir= 'dashboards';
    public $code     = 'stock_aging';
    public $secID    = 'inv_mgr';
    public $category = 'inventory';
    public  $struc;
    private $ageFld;
    public $lang = ['title'=>'Stock Aging',
        'description'=>'Lists the inventory that past the shelf life. For best results, add a custom inventory database field named: shelf_life and populate with number of weeks the product will remain fresh before some form of attention.',
        'age_default' => 'Default display age (weeks)'];

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
        $weeks = [1,2,3,4,6,8,13,26,39,52,104];
        foreach ($weeks as $week) { $ages[] = ['id'=>$week, 'text'=>$week]; }
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),             'clean'=>'array',  'attr'=>['type'=>'users', 'value'=>[0],],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),            'clean'=>'array',  'attr'=>['type'=>'roles', 'value'=>[-1]],'admin'=>true],
            // User fields
            'defAge'=> ['order'=>40,'label'=>$this->lang['age_default'],'clean'=>'integer','attr'=>['type'=>'select','value'=>52],  'values'=>$ages]];
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
        $ttlQty      = $ttlCost = $value = 0;
        $this->ageFld= dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'shelf_life') ? true : false;
        $iconExp     = ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#form{$this->code}').submit();"]];
        $action      = BIZUNO_AJAX."&bizRt=$this->category/tools/stockAging";
        $html        = '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $html       .= '<form id="form'.$this->code.'" action="'.$action.'">'.html5('', $iconExp).'</form>';
        $js          = "ajaxDownload('form{$this->code}');
function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', '".jsLang('post_date')."');
    data.addColumn('string', '".jsLang('inventory_description_short')."');
    data.addColumn('number','" .jsLang('remaining')."');
    data.addColumn('number', '".jsLang('value')."');
    data.addRows([";
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "remaining>0", 'post_date', ['sku', 'post_date', 'remaining', 'unit_cost']);
        foreach ($rows as $row) {
            $ageDate = $this->getAgingValue($row['sku'], $opts['defAge']);
            msgDebug("\nsku {$row['sku']} comparing ageDate: $ageDate with post date: {$row['post_date']}");
            if ($row['post_date'] >= $ageDate) { continue; }
            $ttlQty += $row['remaining'];
            $value   = $row['unit_cost'] * $row['remaining'];
            $ttlCost+= $value;
            $js     .= "['".viewFormat($row['post_date'], 'date')."','".viewProcess($row['sku'], 'sku_name')."',{v: ".intval($row['remaining'])."},{v:$value, f:'".viewFormat($value,'currency')."'}],";
        }
        $js .= "['".jslang('total')."',' ',{v: ".intval($ttlQty)."},{v: $value, f: '".viewFormat($ttlCost,'currency')."'}]]);
    data.setColumnProperties(0, {style:'font-style:bold;font-size:22px;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'90%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
        return ['html'=>$html, 'jsHead'=>$js];
    }

    /**
     * Retrieves the aging date based on the SKU provided
     * @param string $sku - SKU to search
     * @return string - aged date to compare for filter
     */
    private function getAgingValue($sku, $defAge)
    {
        if (!empty($this->skuDates[$sku])) { return $this->skuDates[$sku]; }
        $numWeeks = $this->ageFld ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'shelf_life', "sku='$sku'") : $defAge;
        $this->skuDates[$sku] = localeCalculateDate(biz_date('Y-m-d'), -($numWeeks * 7));
        msgDebug("\n num weeks = $numWeeks and calculated date = {$this->skuDates[$sku]}");
        return $this->skuDates[$sku];
    }
}
