<?php
/*
 * Bizuno Pro - Constacts Dashboard - sales_by_rep
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
 * @version    7.x Last Update: 2025-11-29
 * @filesource /controllers/contacts/dashboards/sales_by_rep/sales_by_rep.php
 */

namespace bizuno;

class sales_by_rep
{
    public  $moduleID = 'contacts';
    public  $methodDir= 'dashboards';
    public  $code     = 'sales_by_rep';
    public  $category = 'customers';
    public  $struc;
    private $dates;
    public  $lang     = ['title'=>'Sales by Rep',
        'description'=>'Displays total sales by Rep with options for different date ranges.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->dates = localeDates(true, true, true, false, true);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'),    'clean'=>'array',  'attr'=>['type'=>'users',   'value'=>[0]], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),   'clean'=>'array',  'attr'=>['type'=>'roles',   'value'=>[-1]],'admin'=>true],
            'reps'  => ['order'=>30,'label'=>lang('just_reps'),'clean'=>'boolean','attr'=>['type'=>'selNoYes','value'=>0],   'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'),    'clean'=>'char',   'attr'=>['type'=>'select',  'value'=>'l'], 'values'=>viewKeyDropdown($this->dates)]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render($opts=[])
    {
        $data    = $this->getTotals($opts['range']);
        $header  = [['title'=>lang('name'), 'type'=>'string'], ['title'=>lang('total'), 'type'=>'number']];
        $callback= BIZUNO_URL_AJAX."&bizRt=$this->moduleID/tools/salesByRep&range={$opts['range']}";
        return ['type'=>'gTable', 'header'=>$header, 'data'=>$data, 'callback'=>$callback];
    }
    public function getTotals($range)
    {
        $data   = $output = [];
        $running= 0;
        msgDebug("\nEntering getTotals with range = $range");
        $dates  = dbSqlDates($range); // this quarter
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "journal_id IN (12,13) AND post_date>='{$dates['start_date']}' AND post_date<'{$dates['end_date']}'", '', ['journal_id','total_amount','rep_id']);
        foreach ($rows as $row) {
            $total = $row['journal_id']==13 ? -$row['total_amount'] : $row['total_amount'];
            if (empty($output['r'.$row['rep_id']])) { $output['r'.$row['rep_id']] = 0; }
            $output['r'.$row['rep_id']] += $total;
            $running += $total;
        }
        foreach ($output as $rep => $value) {
            $data[]= [viewFormat(substr($rep, 1), 'contactID'), ['v'=>$value,'f'=>viewFormat($value, 'currency')]];
        }
        $data[]= [lang('total'), ['v'=>$running,'f'=>viewFormat($running, 'currency')]];
        msgDebug("\nReturning from getTotals with data: ".print_r($data, true));
        return $data;
    }
}
