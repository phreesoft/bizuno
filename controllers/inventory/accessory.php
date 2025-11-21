<?php
/*
 * Bizuno Extension - Inventory Accessory Extension
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
 * @version    7.x Last Update: 2025-06-18
 * @filesource /controllers/inventory/accessory.php
 */

namespace bizuno;

class inventoryAccessory
{
    public  $moduleID = 'inventory';

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function accessoryEdit(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $js = "var accessoryIndex = 0;
function invAccessorySave() {
    var items = jqBiz('#dgAccessory').datagrid('getData');
    var cnt   = 0;
    var output= [];
    for (var i=0; i<items.rows.length; i++) if (items.rows[i].id) { output[cnt] = parseInt(items.rows[i].id); cnt++; }
    var serializedItems = JSON.stringify(output);
    jqBiz('#invAccessory').val(serializedItems);
}";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs' => [
                'invAccDG'    => ['order'=>20,'type'=>'datagrid','key'=>'dgAccessory'],
                'invAccFooter'=> ['order'=>90,'label'=>$this->lang['accessories'],'type'=>'html','html'=>html5('invAccessory', ['attr'=>['type'=>'hidden']])]],
            'jsHead' => ['jsAccEdit'=>$js],
            'datagrid' => ['dgAccessory' => $this->dgAccessory('dgAccessory', $rID)]]);
    }

    /**
     *
     * @param type $name
     * @param type $skuID
     * @return string
     */
    private function dgAccessory($name, $skuID=0)
    {
        $data = ['id'=>$name,'type'=>'edatagrid',
            'attr'   => ['toolbar'  => "#{$name}Bar",'singleSelect'=> true,
                'url'=> BIZUNO_URL_AJAX."&bizRt=$this->moduleID/accessory/accessoryList&rID=$skuID"],
            'events' => [
                'onLoadSuccess'=> "function(data)     { invAccessorySave(); if (data.total == 0) jqBiz('#$name').edatagrid('addRow'); }",
                'onAdd'        => "function(rowIndex) { accessoryIndex = rowIndex; }",
                'onDestroy'    => "function()         { invAccessorySave(); }",
                'onClickRow'   => "function(rowIndex) { accessoryIndex = rowIndex; }",
                'onAfterEdit'  => "function()         { invAccessorySave(); }"],
            'source' => ['actions'=>['newAttr'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'id'    => ['order'=>0,'attr'=>['hidden'=>true]],
                'action'=> ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"{$name}Formatter"],
                    'actions'   => [
                        'edit'  => ['icon'=>'edit', 'order'=>30,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('editRow');"]],
                        'delete'=> ['icon'=>'trash','order'=>90,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'sku'=> ['order'=>10, 'label'=>lang('sku'),
                    'attr' => ['width'=>150, 'sortable'=>true, 'resizable'=>true, 'align'=>'center'],
                    'events'=>  ['editor'=>"{type:'combogrid',options:{ url:bizunoAjax+'&bizRt=inventory/main/managerRows&clr=1',
                        width:150, panelWidth:300, delay:500, idField:'sku', textField:'sku', mode:'remote',
                        onClickRow: function (idx, data) {
                            jqBiz('#dgAccessory').edatagrid('getRows')[accessoryIndex]['id'] = data.id;
                            jqBiz('#dgAccessory').edatagrid('getRows')[accessoryIndex]['description_short'] = data.description_short;
                            jqBiz('#dgAccessory').edatagrid('getRows')[accessoryIndex]['full_price'] = data.full_price;
                            jqBiz('#$name').datagrid('endEdit');
                            jqBiz('#$name').edatagrid('addRow');
                        },
                        columns:[[{field:'sku', title:'".jsLang('sku')."', width:100},
                                  {field:'description_short',title:'".jsLang('description')."',width:200}]]
                    }}"]],
                'description_short'=> ['order'=>20,'label'=>lang('description'),'attr'=>['width'=>400,'resizable'=>true]],
                'full_price'       => ['order'=>30,'label'=>lang('journal_item_full_price'), 'attr'=>  ['type'=>'currency','width'=>120,'resizable'=>true],
                    'events'=>  ['formatter'=>"function(value,row){ return formatCurrency(value); }"]]]];
        return $data;
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function accessoryList(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $output = [];
        $skuID = clean('rID', 'integer', 'get');
        if ($skuID) {
            $vals = json_decode(dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invAccessory', "id=$skuID"));
            foreach ($vals as $rID) { $output[] = dbGetValue(BIZUNO_DB_PREFIX."inventory", ['id','sku','description_short','full_price'], "id=$rID"); }
        }
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$output])]);
    }
}
