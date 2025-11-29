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
 * @version    7.x Last Update: 2025-11-29
 * @filesource /controllers/contacts/dashboards/new_cust/new_cust.php
 */

namespace bizuno;

class new_cust
{
    public $moduleID  = 'contacts';
    public $methodDir = 'dashboards';
    public $code      = 'new_cust';
    public $category  = 'customers';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title'=>'New Customers',
        'description'=>'Displays the number of newly added customers today, this week, and this month.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [ // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),    'clean'=>'array',  'attr'=>['type'=>'users',   'value'=>[0],],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),   'clean'=>'array',  'attr'=>['type'=>'roles',   'value'=>[-1]],'admin'=>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'),'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0],   'admin'=>true]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $table = [
            [lang('today'),     ['v'=>$this->getTotals('c')]],
            [lang('dates_wtd'), ['v'=>$this->getTotals('e')]],
            [lang('dates_mtd'), ['v'=>$this->getTotals('g')]]];
        $header= [['title'=>lang('range'), 'type'=>'string'], ['title'=>lang('total'), 'type'=>'number']];
        return ['type'=>'gTable', 'header'=>$header, 'data'=>$table];


        $html= '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $js  = "function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', ' ');
    data.addColumn('number', ' ');
    data.addRows([['".jslang('today')."',".$this->getTotals('c')."],['".jslang('dates_wtd')."',".$this->getTotals('e')."],['".jslang('dates_mtd')."',".$this->getTotals('g')."]]);
    data.setColumnProperties(0, {style:'font-style:bold;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'50%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
        return ['html'=>$html, 'jsHead'=>$js];
    }
    private function getTotals($range='c')
    {
        $dates = dbSqlDates($range, 'first_date');
        if (!$stmt = dbGetResult("SELECT COUNT(*) AS count FROM ".BIZUNO_DB_PREFIX."contacts WHERE ctype_c='1' AND ".$dates['sql'])) {
            return msgAdd(lang('err_bad_sql'));
        }
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return !empty($result['count']) ? $result['count'] : 0;
    }
}
