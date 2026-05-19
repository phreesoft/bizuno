<?php
/*
 * extISO9001 module dashboard - Dashboard for Quality Issues by Vendor
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/quality/dashboards/qa_by_vendor/qa_by_vendor.php
 */

namespace bizuno;

class qa_by_vendor
{
    public  $moduleID = 'quality';
    public  $pageID   = 'tickets';
    public  $methodDir= 'dashboards';
    public  $code     = 'qa_by_vendor';
    public  $secID    = 'extISO9001';
    public  $category = 'quality';
    public  $struc;
    private $dates;
    public  $lang     = ['title'=>'Quality Tickets by Contact',
        'description'=>'Pie chart list of quality issues by vendor for trend analysis.',
        'chart_title' => 'Total Issues Found %s = %s'];

    function __construct()
    {
        $this->dates = [0=>lang('dates_quarter'), 1=>lang('dates_lqtr'), 2=>lang('quarter_neg2'), 3=>lang('quarter_neg3'), 4=>lang('quarter_neg4'), 5=>lang('quarter_neg5')];
        $this->fieldStructure();
    }
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
    public function render($opts=[])
    {
        $cData = $this->getData($opts['range']);
        $title = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['totalRtn']);
        $legend= !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": {$this->dates[$opts['range']]}" : '';
        $click = "winHref(bizunoHome+'?bizRt=$this->moduleID/$this->pageID/manager&range={$opts['range']}&mgrAction=$this->code&rIDList='+sel[0].row);";
        return ['type'=>'gChart', 'title'=>$title, 'legend'=>$legend, 'data'=>$cData['chart'], 'click'=>$click];
    }
    public function getData($range, $slices=10)
    {
        $dates = dbSqlDatesQrtrs($range, 'post_date');
        $raw   = $struc = $fTotals = $rIDs = [];
        $cnt   = $rTotal= 0;
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $dates['sql']." AND journal_id=30 AND contact_id_b<>0", '', ['id', 'contact_id_b', 'description', 'rep_id']);
        foreach ($rows as $row) {
            if (!isset($raw[$row['contact_id_b']])) { $raw[$row['contact_id_b']] = ['cID'=>$row['contact_id_b'], 'cnt'=>0, 'rID'=>[]]; }
            $raw[$row['contact_id_b']]['cnt']++;
            $raw[$row['contact_id_b']]['rID'][] = $row['id'];
        }
        $output= sortOrder($raw, 'cnt', 'desc');
        $struc['chart'][] = [lang('Contact'), lang('total')]; // headings
        $fTotals[] = lang('total');
        foreach ($output as $row) { // build pie chart data
            $name = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id={$row['cID']}");
            if ($cnt < $slices) {
                $struc['chart'][] = [$name, $row['cnt']];
                $struc['rIDs'][]  = $row['rID'];
            }
            else {
                $rTotal += $row['cnt'];
                if (empty($struc['rIDs'][$this->slices])) { $struc['rIDs'][$this->slices] = []; }
                $struc ['rIDs'][$this->slices] = array_unique(array_merge($struc['rIDs'][$this->slices], $row['rID']));
            }
            $cnt++;
        }
        $struc['chart'][] = [lang('other'), $rTotal];
        $struc['totalRtn']= sizeof($rows);
        msgDebug("\ngetData output = ".print_r($struc, true));
        return $struc;
    }
}
