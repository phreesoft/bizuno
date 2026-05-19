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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-03-15
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
    public $struc;
    public $lang = ['title'=>'Stock Aging',
        'description'=>'Lists the inventory that past the shelf life. For best results, add a custom inventory database field named: shelf_life and populate with number of weeks the product will remain fresh before some form of attention.',
        'age_default' => 'Default Shelf Life (Weeks)'];

    function __construct()
    {
        $this->fieldStructure();
    }
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
    public function render($opts=[])
    {
        msgDebug("\nEntering render with opts = ".print_r($opts, true));
        $data  = $skuLife = [];
        $ttlQty= $ttlCost = $value = 0;
        $hasFld= dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'shelf_life') ? true : false;
        if ($hasFld) {
            $allLife = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "inventory_type IN ('".implode("','", INVENTORY_COGS_TYPES)."')", 'sku', ['sku', 'shelf_life']);
            foreach ($allLife as $life) { $skuLife[$life['sku']] = !empty($life['shelf_life']) ? $life['shelf_life'] : $opts['defAge']; }
        }
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "remaining>0", 'post_date', ['sku', 'post_date', 'remaining', 'unit_cost']);
        foreach ($rows as $row) {
            $numWks  = !empty($skuLife[$row['sku']]) ? $skuLife[$row['sku']] : $opts['defAge'];
            $postDate= substr($row['post_date'], 0, 10);
            msgDebug("\nsku {$row['sku']} comparing ageDate: $numWks with post date: $postDate");
            if ($postDate >= $numWks) { continue; }
            msgDebug(" ... is getting old, adding {$row['sku']} to list");
            $ttlQty += $row['remaining'];
            $value   = $row['unit_cost'] * $row['remaining'];
            $ttlCost+= $value;
            $data[]  = [substr($postDate, 0, 7), $row['sku'], ['v'=>intval($row['remaining'])], ['v'=>$value,'f'=>viewFormat($value, 'currency')]];
        }
        $data[]= [lang('total'), '', ['v'=>intval($ttlQty)], ['v'=>$ttlCost,'f'=>viewFormat($ttlCost,'currency')]];
        $header= [['title'=>lang('post_date'), 'type'=>'string'], ['title'=>lang('description'), 'type'=>'string'],
            ['title'=>lang('remaining'), 'type'=>'number'], ['title'=>lang('value'), 'type'=>'number']];
        return ['type'=>'gTable', 'header'=>$header, 'data'=>$data, 'callback'=>BIZUNO_URL_AJAX.'&bizRt=inventory/tools/stockAging'];
    }
}
