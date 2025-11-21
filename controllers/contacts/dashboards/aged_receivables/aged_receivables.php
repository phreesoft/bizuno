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
 * @version    7.x Last Update: 2025-06-17
 * @filesource /controllers/contacts/dashboards/aged_receivables/aged_receivables.php
 */

namespace bizuno;

class aged_receivables
{
    public $moduleID  = 'contacts';
    public $methodDir = 'dashboards';
    public $code      = 'aged_receivables';
    public $secID     = 'j18_mgr';
    public $category  = 'customers';
    public $noSettings= true;
    public  $struc;
    public $lang      = ['title'=>'Aged Receivables',
        'description'=>'Displays aged receivables. Does not include accounts that are current.'];

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
            'users' => ['order'=>10,'label'=>lang('users'),     'clean'=>'array',   'attr'=>['type'=>'users',   'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),    'clean'=>'array',   'attr'=>['type'=>'roles',   'value'=>[-1]], 'admin'=>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'), 'clean'=>'boolean', 'attr'=>['type'=>'selNoYes','value'=>0],    'admin'=>true]];
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
        $iconExp= ['attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#frm$this->code').submit();"]];
        $action = BIZUNO_URL_AJAX."&bizRt=phreebooks/tools/agingData";
        $data   = $this->getTotals();
        $html   = '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $html  .= '<form id="frm'.$this->code.'" action="'.$action.'">'.html5('', $iconExp).'</form>';
        $js     = "ajaxDownload('frm$this->code');
function chart{$this->code}() {
    var data = new google.visualization.DataTable();
    data.addColumn('string', ' ');
    data.addColumn('string', ' ');
    data.addRows([
      ['".jslang('Current')      ."','".addslashes(viewFormat($data['balance_0'],  'currency'))."'],
      ['".jslang('Late 1-30')    ."','".addslashes(viewFormat($data['balance_30'], 'currency'))."'],
      ['".jslang('Late 31-60')   ."','".addslashes(viewFormat($data['balance_60'], 'currency'))."'],
      ['".jslang('Late 61-90')   ."','".addslashes(viewFormat($data['balance_90'], 'currency'))."'],
      ['".jslang('Late 91-120')  ."','".addslashes(viewFormat($data['balance_120'],'currency'))."'],
      ['".jslang('Late over 120')."','".addslashes(viewFormat($data['balance_121'],'currency'))."'] ]);
    data.setColumnProperties(0, {style:'font-style:bold;text-align:center'});
    var table = new google.visualization.Table(document.getElementById('{$this->code}_chart'));
    table.draw(data, {showRowNumber:false, width:'75%', height:'100%'});
}
google.charts.load('current', {'packages':['table']});
google.charts.setOnLoadCallback(chart{$this->code});\n";
        return ['html'=>$html, 'jsHead'=>$js];
    }

    public function getTotals()
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'calculate_aging', 'function');
        $output= ['data'=>[['Customer','Post Date','Ref Number','Current','1-30','31-60','61-90','91-120','Over 120']],
            'balance_121' => 0,'balance_120' => 0,'balance_91' => 0,'balance_90' => 0,'balance_61' => 0,'balance_60' => 0,'balance_30' => 0,'balance_0' => 0];
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "journal_id IN (12, 13) AND closed='0'", 'primary_name_b', ['DISTINCT contact_id_b'], 0, false);
        foreach ($rows as $row) {
            $aging = calculate_aging($row['contact_id_b']);
            $output['balance_0']  += $aging['balance_0'];
            $output['balance_30'] += $aging['balance_30'];
            $output['balance_60'] += $aging['balance_60'];
            $output['balance_90'] += $aging['balance_90'];
            $output['balance_120']+= $aging['balance_120'];
            $output['balance_61'] += $aging['balance_61'];
            $output['balance_91'] += $aging['balance_91'];
            $output['balance_121']+= $aging['balance_121'];
            foreach ($aging['data'] as $entry) { $output['data'][] = $entry; }
        }
        return $output;
    }
}
