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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-12-09
 * @filesource /controllers/quality/dashboards/qa_by_sku/qa_by_sku.php
 */

namespace bizuno;

class qa_by_sku
{
    public  $moduleID = 'quality';
    public  $pageID   = 'tickets';
    public  $methodID = 'extISO9001'; // needs to be this as the security is based on the old extension name
    public  $methodDir= 'dashboards';
    public  $code     = 'qa_by_sku';
    public  $secID    = 'extISO9001';
    public  $category = 'quality';
    public  $slices   = 10; // Number of pie slices
    public  $struc;
    private $dates;
    public  $lang     = ['title'=>'Quality Tickets by SKU',
        'description'=>'Pie chart list of quality issues by SKU for trend analysis',
        'chart_title' => 'Total Issues Found %s = %s'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
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
        $menu  = clean('menu', 'db_field', 'get');
        $cData = $this->getData($opts['range']);
        $title = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['totalRtn']);
        $legend= !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": {$this->dates[$opts['range']]}" : '';
        $click = "winHref(bizunoHome+'?bizRt=$this->moduleID/$this->pageID/manager&menu=$menu&range={$opts['range']}&mgrAction=$this->code&rIDList='+sel[0].row);";
        return ['type'=>'gChart', 'title'=>$title, 'legend'=>$legend, 'data'=>$cData['chart'], 'click'=>$click];
    }
    public function getData($range)
    {
        $dates = dbSqlDatesQrtrs($range, 'post_date');
        $raw   = $struc = $fTotals = $rIDs = [];
        $cnt   = $rTotal= 0;
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', $dates['sql']." AND journal_id=30", '', ['id']);
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
        $struc['chart'][] = [lang('sku'), lang('total')]; // headings
        $fTotals[] = lang('total');
        foreach ($output as $row) { // build pie chart data
            $skuID  = intval($row['skuID']);
            $skuDesc= empty($skuID) ? $row['title'] : dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'description_short', "id=$skuID")." [".lang('qty').": {$row['qty']}]";
            if ($cnt < $this->slices) {
                $struc['chart'][] = [$skuDesc, $row['cnt']];
                $struc['rIDs'][]  = $row['rID'];
            } else {
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
