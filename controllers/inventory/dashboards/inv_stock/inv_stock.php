<?php
/*
 * Inventory dashboard - Stock levels by month as seen in the journal_history
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
 * @version    7.x Last Update: 2025-11-30
 * @filesource /controllers/inventory/dashboards/inv_stock/inv_stock.php
 */

namespace bizuno;

class inv_stock
{
    public $moduleID = 'inventory';
    public $methodDir= 'dashboards';
    public $code     = 'inv_stock';
    public $secID    = 'inv_mgr';
    public $category = 'inventory';
    public $struc;
    public $lang     = ['title'=>'Inventory Stock Summary',
        'description'=>'Displays your inventory stock levels and valuation by GL account.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->fieldStructure();
    }
    private function fieldStructure()
    {
        $this->struc = [
            // Admin fields
            'users' => ['order'=>10,'label'=>lang('users'), 'clean'=>'array', 'attr'=>['type'=>'users', 'value'=>[0],], 'admin'=>true],
            'roles' => ['order'=>20,'label'=>lang('groups'),'clean'=>'array', 'attr'=>['type'=>'roles', 'value'=>[-1]], 'admin'=>true]];
            // User fields - None
        metaPopulate($this->struc, getMetaDashboard($this->code)); // override with user global settings
    }
    public function render()
    {
        $data  = $bVal = $skuGL = [];
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'inventory_history', "remaining>0");
        foreach ($rows as $row) {
            if (!isset($bVal[$row['store_id']])) { $bVal[$row['store_id']] = 0; }
            $bVal[$row['store_id']] += $row['remaining'] * $row['unit_cost'];
        }
        $data[]= [lang('store'), lang('value')];
        foreach ($bVal as $bID => $value) {
            $title = empty($bID) ? getModuleCache('bizuno', 'settings', 'company', 'primary_name') : viewFormat($bID, 'contactName');
            $data[]= [$title, ['v'=>$value, 'f'=>viewFormat($value, 'currency')]];
        }
        $header= [['title'=>lang('store'), 'type'=>'string'], ['title'=>lang('amount'), 'type'=>'number']];
        return ['type'=>'gChart', 'header'=>$header, 'data'=>$data, 'callback'=>BIZUNO_URL_AJAX.'&bizRt=inventory/tools/invDataGo'];
    }
}
