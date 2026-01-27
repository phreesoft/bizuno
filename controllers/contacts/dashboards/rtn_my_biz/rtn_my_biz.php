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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-12-12
 * @filesource /controllers/contacts/dashboards/rtn_my_biz/rtn_my_biz.php
 */

namespace bizuno;

class rtn_my_biz
{
    public  $moduleID = 'contacts';
    public  $methodID = 'returns';
    public  $methodDir= 'dashboards';
    public  $code     = 'rtn_my_biz';
    public  $category = 'quality';
    public  $slices   = 10; // Number of pie slices
    public  $struc;
    private $dates;
    public  $lang     = ['title'=>'Returns By SKU (Preventable)',
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
    public function render($opts=[])
    {
        $cData = $this->getData($opts['range']);
        $title = "Returns by SKU = ".$cData['totalRtn']." entries";
        $legend= !empty(getModuleCache('bizuno','settings','general','hide_filters',0)) ? lang('filter').": {$this->dates[$opts['range']]}" : '';
        $click = "winHref(bizunoHome+'?bizRt=phreebooks/$this->methodID/manager&range={$opts['range']}&mgrAction=$this->code&sliceID='+sel[0].row);";
        return ['type'=>'gChart', 'title'=>$title, 'legend'=>$legend, 'data'=>$cData['chart'], 'click'=>$click];
    }
    public function getData($range)
    {
        msgDebug("\nEntering rtnSKUs with range = $range");
        $dates = dbSqlDatesQrtrs($range, 'post_date'); // found date
        $stmt  = dbGetResult("SELECT journal_main.id, post_date, meta_value FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE {$dates['sql']} AND meta_key='return'");
        $rows  = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $output= [];
        foreach ($rows as $row) { // preventable='1'
            $meta = json_decode($row['meta_value'], true);
            msgDebug("\nmeta = ".print_r($meta, true));
            if (empty($meta) || empty($meta['receive_details']['rows'])) { continue; }
            foreach ($meta['receive_details']['rows'] as $item) {
                if ( empty($item['sku'])) { continue; }
                if (!isset($output[$item['sku']])) { $output[$item['sku']] = ['qty'=>0,'desc'=>$item['desc']]; } // ,'fault'=>[]
                $output[$item['sku']]['qty']++; // uncomment if just want to count orders
//              $output[$item['sku']]['qty'] += !empty($item['qty']) ? $item['qty'] : 1; // uncomment if want to count total qty returned
                $output[$item['sku']]['rID'][] = $row['id'];
            }
        }
        arsort($output);
        $cnt   = $rTotal = 0;
        $struc = [];
        $struc['chart'][]= [lang('sku'), lang('total')]; // headings
        foreach ($output as $vals) {
            if ($cnt < $this->slices) {
                $struc['chart'][] = [$vals['desc'], $vals['qty']];
                $struc['rIDs'][]  = $vals['rID']; // is already an array
            } else {
                $rTotal += $vals['qty'];
                if (empty($struc['rIDs'][$this->slices])) { $struc['rIDs'][$this->slices] = []; }
                $struc ['rIDs'][$this->slices] = array_unique(array_merge($struc['rIDs'][$this->slices], $vals['rID']));
            }
            $cnt++;
        }
        $struc['chart'][] = [lang('other'), $rTotal];
        $struc['totalRtn']= sizeof($output);
        msgDebug("\nOutput = ".print_r($struc, true));
        return $struc;
    }
}
