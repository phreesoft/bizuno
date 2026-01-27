<?php
/*
 * @name Bizuno ERP - Inventory Vendor Extension
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
 * @version    7.x Last Update: 2025-06-18
 * @filesource /controllers/inventory/vendors.php
 */

namespace bizuno;

class inventoryVendors
{
    public  $moduleID = 'inventory';

    function __construct()
    {
        $this->lang     = getExtLang($this->moduleID);
        $this->settings = getModuleCache($this->moduleID, 'settings', false, false, []);
    }

    /**
     * Extends the inventory prices method when fetching cost for the specific vendor
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function quote(&$layout=[])
    {
        $values = isset($layout['source']) ? $layout['source'] : [];
        if (isset($values['cType']) && $values['cType'] == 'v') {
            if (!$values['iID'] || !$values['cID']) { return; }
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invVendors', "id={$values['iID']}");
            if (!$inv) { return; } // no vendor list
            $vendors = json_decode($inv, true);
            $rows = isset($vendors['rows']) ? $vendors['rows'] : $vendors;
            foreach ($rows as $row) { if ($values['cID'] == $row['id']) {
                $values['iSheetv']= $row['sheet'];
                $values['iCost']  = $row['cost'];
                unset($layout['content']['price']); // clear the price before recalculate
                $prices = new inventoryPrices();
                $prices->pricesLevels($layout['content'], $values);
            } }
        }
    }

    /**
     * Generates the structure for the tab on the inventory editor
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function vendorsLoad(&$layout=[])
    {
        if (!$security= validateAccess('inv_mgr', 1, false)) { return; }
        if (!$rID     = clean('rID', 'integer', 'get'))      { return; }
        $dbVend = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invVendors', "id=$rID");
        if (empty($dbVend)) { $dbVend = '[]'; } // empty json value
        msgDebug("\nRead curVend = ".print_r($dbVend, true));
        $curVend= $this->getContactTitle($dbVend);
        $js     = "var dgVendorsData = ".$curVend.";\nvar dgVendorsSheets=".json_encode(getModuleCache('inventory', 'prices_v')).";";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => [
                'divVendHead' => ['order'=> 0,'type'=>'html',    'html'=>html5('invVendors',['attr'=>['type'=>'hidden']])],
                'divVendGrid' => ['order'=>30,'type'=>'datagrid','key'=>'dgVendors']],
            'jsHead'  => ['jsVendors'=>$js],
            'datagrid'=> ['dgVendors'=>$this->dgInvVendors('dgVendors')]]);
    }

    /**
     * Grid for creating the vendor list for this SKU
     * @param string $name - DOM name of the grid
     * @return array - grid structure
     */
    private function dgInvVendors($name)
    {
        return ['id' =>$name, 'type'=>'edatagrid',
            'attr'   =>['toolbar'=>"#{$name}Toolbar",'rownumbers'=> true],
            'events' => ['data'=>"{$name}Data",
                'onClickRow'=> "function(rowIndex) { jqBiz('#$name').edatagrid('editRow', rowIndex); }",
                'onAdd'     => "function(rowIndex, row) {
    bizTextEdSet('$name', rowIndex, 'desc', bizTextGet('description_purchase'));
    bizNumEdSet('$name', rowIndex, 'cost', bizNumGet('item_cost'));
    bizNumEdSet('$name', rowIndex, 'qty_pkg', 1); }"],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'=> ['order'=>1,'label'=>lang('action'),
                    'actions'=> ['invVendTrash'=>  ['icon'=>'trash','order'=>20,'events'=>  ['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]],
                    'events' => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"]],
                'id'    => ['order'=>10, 'label'=>pullTableLabel("contacts", 'short_name', 'v'),
                    'attr' => ['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'center'],
                    'events'=>  ['formatter'=>"function(value, row) { return row.primary_name; }",
                        'editor'=>"{type:'combogrid',options:{width:130,panelWidth:750,delay:900,idField:'id',textField:'primary_name',mode:'remote',
    url:bizunoAjax+'&bizRt=contacts/main/managerRows&clr=1&type=v&rows=9999',selectOnNavigation:false,
    columns: [[
      {field:'id',          hidden:true},
      {field:'short_name',  width:100,title:'".jsLang('short_name')."'},
      {field:'primary_name',width:200,title:'".jsLang('primary_name')."'},
      {field:'address1',    width:100,title:'".jsLang('address1')."'},
      {field:'city',        width:100,title:'".jsLang('city')."'},
      {field:'state',       width: 50,title:'".jsLang('state')."'},
      {field:'postal_code', width:100,title:'".jsLang('postal_code')."'},
      {field:'telephone1',  width:100,title:'".jsLang('telephone')."'}
    ]] }}"]],
                'desc' =>  ['order'=>20,'label'=>lang('description_purchase'),'attr'=>['editor'=>'text','width'=>240,'sortable'=>true,'resizable'=>true],
                    'events'=>  ['editor'=>"{type:'text',options:{}}"]],
                'cost' =>  ['order'=>30,'label'=>lang('item_cost'),  'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true],
                    'events'=>  ['editor'=>"{type:'numberbox',options:{formatter:function(value){return formatCurrency(value, false);}}}"]],
                'qty_pkg'=>  ['order'=>40,'label'=>$this->lang['qty_package'], 'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true],
                    'events'=>  ['editor'=>"{type:'numberbox',options:{formatter:function(value){return formatPrecise(value);}}}"]],
                'tax' =>  ['order'=>50,'label'=>lang('tax_rate_id'),  'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true],
                    'events'=>  ['editor'=>dgHtmlTaxData($name, 'tax', 'v')]],
                'sheet' =>  ['order'=>60,'label'=>lang('price_sheet'),'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true],
                    'events'=>  ['editor'=>"{type:'combobox',options:{valueField:'id',textField:'text',data:dgVendorsSheets}}"]]]];
    }

    /**
     * Converts the database encoded values to grid values
     * @param array $settings - JSON encoded vendor grid list from database
     * @return type
     */
    private function getContactTitle($settings=[])
    {
        $rows = json_decode($settings, true);
        if (!is_array($rows) || !$rows) { $rows = ['rows'=>  []]; }
        if (!isset($rows['rows'])) { $rows= ['rows' => $rows]; }
        foreach ($rows['rows'] as $idx => $row) {
            if (!isset($row['primary_name'])) {
                $rows['rows'][$idx]['primary_name'] = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id='{$row['id']}'");
            }
        }
        $rows['total'] = sizeof($rows['rows']);
        return json_encode($rows);
    }
}
