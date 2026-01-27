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
 * @version    7.x Last Update: 2026-01-27
 * @filesource /controllers/inventory/bulkEdit.php
 */

namespace bizuno;

class inventoryBulkEdit
{
    public  $moduleID = 'inventory';
    private $fields   = ['description_short', 'item_cost', 'full_price', 'upc_code'];

    function __construct()
    {
        $this->lang    = getExtLang($this->moduleID);
        $this->invTable= dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $this->readonly= ['id','inventory_type','sku','attach','qty_stock','qty_po','qty_so','qty_alloc','serialize','cost_method','store_id',
            'tax_rate_id_c','price_sheet_c','tax_rate_id_v','price_sheet_v','image_with_path','creation_date','last_update','last_journal_date',
            'invImages','invVendors','invAccessory','length','width','height']; // NOTE: length, width, height when values have values (decimal point) cause edit to fail!
        foreach ($this->readonly as $key) { unset($this->invTable[$key]); }
    }

    /**
     * Main page manager
     * @param array $layout -  Structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('invBulkEdit', 1)) { return; }
        $this->managerSettings();
        $jsReady = "function buildMenu(target){
    var state = jqBiz(target).data('datagrid');
    if (!state.columnMenu) {
        state.columnMenu = jqBiz('<div></div>').appendTo('body');
        state.columnMenu.menu({
            onClick: function(item){
                if (item.iconCls == 'tree-checkbox1'){
                    jqBiz(target).datagrid('hideColumn', item.name);
                    jqBiz(this).menu('setIcon', { target: item.target, iconCls: 'tree-checkbox0' });
                } else {
                    jqBiz(target).datagrid('showColumn', item.name);
                    jqBiz(this).menu('setIcon', { target: item.target, iconCls: 'tree-checkbox1' });
                }
            }
        })
        var fields = jqBiz(target).datagrid('getColumnFields',true).concat(jqBiz(target).datagrid('getColumnFields',false));
        for (var i=2; i<fields.length; i++) {
            var field = fields[i];
            var col = jqBiz(target).datagrid('getColumnOption', field);
            state.columnMenu.menu('appendItem', {
                text: col.title,
                name: field,
                iconCls: col.hidden===true ? 'tree-checkbox0' : 'tree-checkbox1'
            });
        }
    }
    return state.columnMenu;
}
jqBiz.extend(jqBiz.fn.datagrid.methods, { columnMenu: function(jq) { return buildMenu(jq[0]); } });";
        $data = ['title'=> $this->lang['title_bulk_edit'],
            'divs'    => ['dgBulkEdit'=>['order'=>50,'label'=>lang('details'), 'type'=>'datagrid', 'key' =>'manager']],
            'datagrid'=> ['manager'   =>$this->dgInvBulk('dgInvBulk')],
            'jsReady' => ['init'      =>$jsReady, 'invBulkEdit'=>"jqBiz('#dgInvBulk').datagrid('enableFilter');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Stores the users preferences, sets defaults
     */
    private function managerSettings()
    {
        $data = ['path'=>'invBulkEdit', 'values'=>[
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'method'=>'request'],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'search','clean'=>'text',   'default'=>''],
            ['index'=>'status','clean'=>'char',   'default'=>'0'], // active
            ['index'=>'sort',  'clean'=>'text',   'default'=>"sku"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC']]];
        $this->defaults = updateSelection($data);
        $fields = clean('fldList', 'array', 'post');
        if (empty($fields)) { return; }
        $this->fields = [];
        foreach ($fields as $field) { $this->fields[] = $field; }
    }

    /**
     * Fetch the rows for the grid
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess('invBulkEdit', 1)) { return; }
        $_POST['rows']  = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'get');
        $_POST['search']= getSearch(['search','q']);
        msgDebug("\n ready to build inventory datagrid, security = $security");
        $structure      = $this->dgInvBulk('dgInvBulk');
        //[filterRules] => [{"field":"sku","op":"contains","value":"30b021"},{"field":"description_short","op":"contains","value":"battery"}]
        $rules          = clean('filterRules', ['format'=>'json','default'=>[]], 'post');
        msgDebug("\nfound filter rules = ".print_r($rules, true));
        if (empty($rules) || !is_array($rules)) { $rules = [['op'=>'contains']]; }
        foreach ($rules as $rule) {
            switch ($rule['op']) {
                default:
                case 'contains':    $op = !empty($rule['value']) ? " LIKE '%{$rule['value']}%'" : ''; break;
                case 'greaterthan': $op = " > '{$rule['value']}'"; break;
                case 'lessthan':    $op = " < '{$rule['value']}'"; break;
                case 'equal':       $op = " = '{$rule['value']}'"; break;
                case 'notequal':    $op = " <>'{$rule['value']}'"; break;
            }
            msgDebug("\nAdding filter op = $op");
            if (!empty($rule['field'])) {
                $structure['source']['filters']['rule_'.$rule['field']] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory.{$rule['field']}$op"];
            }
        }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$structure]]);
    }

    /**
     * Saves the current grid row
     * @param array $layout
     * @return modified $layout
     */
    public function save(&$layout=[])
    {
        if (!$security = validateAccess('invBulkEdit', 3)) { return; }
        $values = clean('data', ['format'=>'json','default'=>[]], 'get');
        $rID = $values['id'];
        $sku = $values['sku'];
        // clean out values that are restricted or not in db, i.e. added by easyUI
        foreach (array_keys($values) as $field) { if (!isset($this->invTable[$field])) { unset($values[$field]); } }
        $values['last_update'] = biz_date('Y-m-d h:i:s');
        msgDebug("\nSaving values = ".print_r($values, true));
        dbWrite(BIZUNO_DB_PREFIX."inventory", $values, 'update', "id=$rID");
        msgAdd(lang('msg_database_write'), 'success');
        msgLog(lang('inventory').'-'.lang('save')." - $sku (rID=$rID)");
    }

    /**
     * Inventory grid structure
     * @param string $name - DOM field name
     * @return string - grid structure
     */
    private function dgInvBulk($name)
    {
        $this->managerSettings();
        $rowVals = [10,20,30,40,50,75,100,200];
        foreach ($rowVals as $val) { $numRows[] = ['id'=>$val, 'text'=>$val]; }
        $statuses = [['id'=>'a','text'=>lang('all')],['id'=>'0','text'=>lang('active')],['id'=>'1','text'=>lang('inactive')]];
        switch ($this->defaults['status']) {
            case '0': $status = "inactive='0'";  break;
            case '1': $status = "inactive='1'";  break;
            default:  $status = '';
        }
        $output = ['id'=>$name, 'type'=>'edatagrid', 'toolbar'=>"#{$name}Toolbar", 'strict'=>true, 'rows'=> $this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'     => ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'clientPaging'=>false, 'remoteFilter'=>true, 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/bulkEdit/managerRows"],
            'events'   => [
                'onClickRow'         => "function(rowIndex, rowData) { jqBiz('#$name').edatagrid('editRow', rowIndex); }",
                'onSave'             => "function(rowIndex, rowData) { var data=JSON.stringify(rowData); jsonAction('$this->moduleID/bulkEdit/save', 0, data); }",
                'onHeaderContextMenu'=> "function(e, field) { e.preventDefault(); jqBiz(this).edatagrid('columnMenu').menu('show', { left:e.pageX, top:e.pageY }); }",
                'rowStyler'          => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; }}"],
            'footnotes'=> ['codes'  => jsLang('color_codes').': <span class="row-inactive">'.jsLang('inactive').'</span>'],
            'source'   => [
                'tables'  => ['inventory'=>['table'=>BIZUNO_DB_PREFIX.'inventory']],
                'filters' => [
                    'status'=> ['order'=>20,'sql'=>$status,'label'=>lang('status'),'values'=>$statuses,'attr'=>['type'=>'select','value'=>$this->defaults['status']]]],
                'sort'    => ['s0'=>['order'=>10, 'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'  => ['id'=> ['order'=>0, 'field'=>'id',      'attr'=>['hidden'=>true]],
                'inactive'=> ['order'=>0, 'field'=>'inactive','attr'=>['hidden'=>true]],
                'sku'     => ['order'=>10,'field'=>'sku','label'=>lang('sku'),'attr'=>['width'=> 80,'sortable'=>true,'resizable'=>true]]]];
        $this->setColumns($output);
        return $output;
    }

    /**
     * Sets the column properties for the items being edited
     * @param array $dg - grid columns
     */
    private function setColumns(&$dg)
    {
        $order = 15;
        foreach ($this->invTable as $field => $props) {
            if (in_array($field, $this->fields)) {
                $dg['columns'][$field] = ['order'=>$order,'field'=>$field,'label'=>$props['label'],'attr'=>['width'=>80,'editor'=>'text','sortable'=>true,'resizable'=>true]];
            } else {
                $dg['columns'][$field] = ['order'=>$order,'field'=>$field,'label'=>$props['label'],'attr'=>['hidden'=>true,'width'=>80,'editor'=>'text','sortable'=>true,'resizable'=>true]];
            }
            $order = $order + 5;
        }
    }
}
