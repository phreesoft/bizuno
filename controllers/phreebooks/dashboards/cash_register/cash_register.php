<?php
/*
 * Contacts dashboard - New Customers
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
 * @filesource /controllers/phreebooks/dashboards/cash_register/cash_register.php
 */

namespace bizuno;

class cash_register
{
    public $moduleID  = 'phreebooks';
    public $methodDir = 'dashboards';
    public $code      = 'cash_register';
    public $secID     = 'mgr_c';
    public $category  = 'banking';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title' => 'Bank Account Balances',
        'description' => 'Lists the current balances in each cash gl account.'];

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
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    /**
     * Generates the structure for the dashboard view
     * @global object $currencies - Sets the currency values for proper display
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function render()
    {
        $total  = 0;
        $cashGL = [];
        $glAccts= getModuleCache('phreebooks', 'chart');
        msgDebug("\nworking with gl accts = ".print_r($glAccts, true));
        foreach ($glAccts as $glAcct => $props) {
            if (!isset($props['type']) || !empty($props['type']) || !empty($props['inactive'])) { continue; } // cash accounts are of type 0, or inactive
            $balance = dbGetGLBalance($glAcct, biz_date());
            $cashGL[]= [$props['id'], $props['title'], ['v'=>$balance,'f'=>viewFormat($balance, 'currency')]];
            $total  += $balance;
        }
        $cashGL[]= [lang('total'), '', ['v'=>$total,'f'=>viewFormat($total, 'currency')]];
//      $html = '<span style="width:100%" id="'.$this->code.'_chart"></span>';
        $html = '<div id="'.$this->code.'"></div><div id="'.$this->code.'_chart" style="margin-top: 20px; overflow-x:auto;"></div>';
        $js   = "function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', '".jslang('gl_account')."');
    data.addColumn('string', '".jslang('bank')      ."');
    data.addColumn('number', '".jslang('balance')   ."');
    data.addRows(".json_encode($cashGL).");
    const chartOptions = {
        backgroundColor: 'transparent',
        titleTextStyle: { color: 'var(--chart-text)' },
        hAxis: { textStyle: { color: 'var(--chart-text)' } },
        vAxis: { textStyle: { color: 'var(--chart-text)' }, gridlines: { color: 'var(--chart-grid)' } },
        legend: { textStyle: { color: 'var(--chart-text)' } },
        datalessTable: true };

//    const chart = new google.visualization.LineChart(document.getElementById('{$this->code}_chart'));
//    chart.draw(data, chartOptions);

    const table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, { showRowNumber: false, width: '100%',  height: '100%', allowHtml: true,
        cssClassNames: { headerRow: 'my-table-header', tableRow: 'my-table-row', hoverTableRow: 'my-table-hover', oddTableRow: 'my-table-odd' }
    });
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
/*  
    data.setColumnProperties(0, {style:'font-style:bold;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'100%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n"; */
        return ['html'=>$this->getHTML($cashGL)]; //, 'jsHead'=>$this->getJS(), 'jsReady'=>"chart{$this->code}();)"];
    }
    private function getHTML($cashGL)
    {
        return "
<style>#chart_div, #custom_table { width: 100% !important; height: 100% !important; min-height: 50px; position: relative; }</style>
<div class=\"google-chart-container\">
    <div id=\"chart_div\"></div>
  <div id=\"custom-table\"></div>
</div>
  <script>
    google.charts.load('current', {packages:['corechart', 'table']});
    google.charts.setOnLoadCallback(drawChart);

    function drawChart() {
      const data = new google.visualization.DataTable();
      data.addColumn('string', '".jslang('gl_account')."');
      data.addColumn('string', '".jslang('account')   ."');
      data.addColumn('number', '".jslang('balance')   ."');
      data.addRows(".json_encode($cashGL).");

      const options = {
        width: '100%',
        height: '100%',
        chartArea: { width: '90%', height: '80%' },
        backgroundColor: 'transparent',
        titleTextStyle: { color: 'var(--text)' },
        hAxis: { textStyle: { color: 'var(--text)' } },
        vAxis: { textStyle: { color: 'var(--text)' }, gridlines: { color: 'var(--grid)' } },
        legend: { textStyle: { color: 'var(--text)' } },
        datalessTable: true
      };
      
//      const chart = new google.visualization.LineChart(document.getElementById('chart_div'));
//      chart.draw(data, options);

      new google.visualization.Table(document.getElementById('custom-table')).draw(data, {
        showRowNumber: false,
        cssClassNames: {
          headerRow: 'my-table-header',
          tableRow: 'my-table-row',
          hoverTableRow: 'my-table-hover',
          oddTableRow: 'my-table-odd'
        }
      });
    }
    // Re-draw on theme change
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', drawChart);
    new MutationObserver(drawChart).observe(document.body, { attributes: true, attributeFilter: ['class'] });
  </script>
</body>
";
    }
/*
LATEST GROK:
<!DOCTYPE html>
<html>
<head>
    <script src="https://www.gstatic.com/charts/loader.js"></script>
    <style>
        .google-chart-wrapper {
            width: 100%;
            height: 100%;
            min-height: 250px;
            position: relative;
            border: 1px solid #ddd;   
        }
        #chart_div {
            width: 100% !important;
            height: 100% !important;
            position: absolute;
            top: 0; left: 0;
        }
    </style>
</head>
<body>

<div style="height:500px; border:2px solid red;">
    <div class="google-chart-wrapper">
        <div id="chart_div"></div>
    </div>
</div>

<script>
google.charts.load('current', {packages:['corechart']});
google.charts.setOnLoadCallback(drawChart);

function drawChart() {
    const data = google.visualization.arrayToDataTable([
        ['Year', 'Sales'], ['2021', 1000], ['2022', 1170], ['2023', 660], ['2024', 1030]
    ]);

    const options = {
        title: 'Auto 100% Height Chart',
        height: '100%',
        width: '100%',
        chartArea: {width:'90%', height:'80%'},
        backgroundColor: 'transparent'
    };

    const chart = new google.visualization.LineChart(document.getElementById('chart_div'));
    chart.draw(data, options);

    // Auto-resize forever
    const resize = () => chart.draw(data, options);
    $(window).resize(resize);
    setTimeout(resize, 100);  // initial fix for some EasyUI cases
}
</script>
</body>
</html>
 */    
    
}
