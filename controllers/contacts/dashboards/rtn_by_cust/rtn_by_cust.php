<?php
/*
 * Contacts module dashboard - Dashboard for returns by customer
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
 * @version    7.x Last Update: 2026-03-01
 * @filesource /controllers/contacts/dashboards/rtn_by_cust/rtn_by_cust.php
 */

namespace bizuno;

class rtn_by_cust
{
    public $moduleID = 'contacts';
    public $methodID = 'returns';
    public $methodDir= 'dashboards';
    public $code     = 'rtn_by_cust';
    public $category = 'customers';
    public $slices   = 10; // Number of pie slices
    public  $struc;
    private $dates;
    public $lang     = ['title'=>'Returns by Customer',
        'description'=>'Lists return by customer over various periods.',
        'chart_title' => 'Returns by Customer for: %s (%s total)'];

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
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array','attr'=>['type'=>'users', 'value'=>[0],],'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array','attr'=>['type'=>'roles', 'value'=>[-1]],'admin'=>true],
            // User fields
            'range' => ['order'=>40,'label'=>lang('range'), 'clean'=>'char', 'attr'=>['type'=>'select','value'=>0], 'values'=>viewKeyDropdown($this->dates)]];
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }

    public function render($opts=[])
    {
        $menu  = clean('menu', 'db_field', 'get');
        $cData = $this->getData($opts['range']);
        $title = sprintf($this->lang['chart_title'], $this->dates[$opts['range']], $cData['totalRtn']);
        $legend= !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": {$this->dates[$opts['range']]}" : '';
        $click = "winHref(bizunoHome+'?bizRt=phreebooks/$this->methodID/manager&range={$opts['range']}&mgrAction=$this->code&sliceID='+sel[0].row);";
        return ['type'=>'gChart', 'title'=>$title, 'legend'=>$legend, 'data'=>$cData['chart'], 'click'=>$click];
    }

    public function getData($range)
    {
        $dates= dbSqlDatesQrtrs($range, 'post_date');
        $raw  = $struc = $fTotals= $rIDs = [];
        $cnt  = $rTotal = 0;
        $stmt = dbGetResult("SELECT journal_main.id, contact_id_b, primary_name_b FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id WHERE meta_key='return' AND {$dates['sql']}");
        $rows = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        msgDebug("\nrows result = ".print_r($rows, true));
        foreach ($rows as $row) {
            if (!isset($raw[$row['contact_id_b']])) { $raw[$row['contact_id_b']] = ['cID'=>$row['contact_id_b'], 'name'=>$row['primary_name_b'], 'cnt'=>0, 'rID'=>[]]; }
            $raw[$row['contact_id_b']]['cnt']++;
            $raw[$row['contact_id_b']]['rID'][] = $row['id'];
        }
        $output= sortOrder($raw, 'cnt', 'desc');
        $struc['chart'][] = [lang('primary_name'), lang('total')]; // headings
        $fTotals[] = lang('total');
        foreach ($output as $row) { // build pie chart data
            $name = !empty($row['name']) ? $row['name'] : 'No Contact ID';
            if ($cnt < $this->slices) { 
                $struc['chart'][] = [$name, $row['cnt']];
                $struc['rIDs'][]  = $row['rID']; // is already an array
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
