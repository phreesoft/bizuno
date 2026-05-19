<?php
/*
 * PhreeBooks dashboard - Inventory Re-Stock by Vendor
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
 * @filesource /controllers/inventory/dashboards/inv_status/inv_status.php
 *
 */

namespace bizuno;

class inv_status
{
    public $moduleID  = 'inventory';
    public $methodDir = 'dashboards';
    public $code      = 'inv_status';
    public $secID     = 'inv_mgr';
    public $category  = 'inventory';
    public $noSettings= true;
    public  $struc;
    public $lang = ['title'=>'Inventory Reorder by Vendor',
        'description'=>'Displays total value of inventory that needs to be restocked by preferred vendor.'];

    function __construct()
    {
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
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
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "qty_stock<(qty_min+qty_so+qty_alloc-qty_po)", '', ['sku','vendor_id','qty_min','qty_so','qty_alloc','qty_po','qty_stock']);
        $vendors = [];
        foreach ($rows as $row) { $vendors[$row['vendor_id']][] = $row; }
        $data = [[lang('name'), lang('total')]];
        foreach ($vendors as $id => $skus) {
            $vName = viewFormat($id, 'contactName');
            $total = 0;
            foreach ($skus as $sku) {
                $cost    = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_cost', "sku='{$sku['sku']}'");
                $balance = $sku['qty_min']+$sku['qty_so']+$sku['qty_alloc']-$sku['qty_po']-$sku['qty_stock'];
                msgDebug("\nsku = {$sku['sku']} and cost = $cost and balance = $balance");
                $total  += $balance * $cost;
            }
            msgDebug("\nvendor = $vName and total = $total");
            $data[] = [$vName, $total];
        }
        return ['type'=>'gColumn', 'data'=>$data];
        
        
        
        $html = '<div style="width:100%" id="'.$this->code.'_chart"></div>';
        $output = ['divID'=>$this->code."_chart",'type'=>'column','attr'=>['legend'=>['position'=>"right"]],'data'=>$cData];
        $js = "var data_{$this->code} = ".json_encode($output).";\n";
        $js.= "function chart{$this->code}() { drawBizunoChart(data_{$this->code}); };\n";
        $js.= "google.charts.load('current', {'packages':['corechart']});\n";
        $js.= "google.charts.setOnLoadCallback(chart{$this->code});\n";
        return ['html'=>$html, 'jsHead'=>$js];
    }
}
