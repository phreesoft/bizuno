<?php
/*
 * Bizuno Extension - Inventory Attributes
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
 * @filesource /controllers/inventory/attributes.php
 */

// @TODO - These need to be moved from the inventory cache to common meta, add drag and drop

namespace bizuno;

class inventoryAttributes
{
    public  $moduleID   = 'inventory';

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
    }

    /**
     * Extends /inventory/main/edit to load attribute to build the panel
     * @param array $layout
     */
    public function attrLoad(&$layout=[])
    {
        $rID    = clean('rID', 'integer','get');
        $invAttr= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'bizProAttr', "id=$rID");
        $attrs  = empty($invAttr) ? ['category'=>'', 'attrs'=>[]] : json_decode($invAttr, true);
        msgDebug("\nRead sku rID = $rID with values = ".print_r($invAttr, true));
        $titles = $this->getTitles(true);
        $fields = ['invAttrCat' => ['order'=>10,'label'=>$this->lang['category'],'values'=>$titles,'attr'=>['type'=>'select', 'value'=>$attrs['category']],
            'events' => ['onChange'=>"jqBiz('#panelAttr').panel('refresh',bizunoAjax+'&bizRt=$this->moduleID/attributes/attrDetails&rID='+jqBiz('#id').val()+'&data='+bizSelGet('invAttrCat'));"]]];
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'   => [
                'head' => ['order'=>10,'type'=>'fields','keys'=>['invAttrCat']],
                'body' => ['order'=>20,'label'=>'dave','type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'attr' => ['order'=>10,'type'=>'panel','classes'=>['block50'],'key'=>'panelAttr']]]],
            'panels' => ['panelAttr'=>['type'=>'html','html'=>'&nbsp;','attr'=>['id'=>'panelAttr']]],
            'fields' => $fields,
            'jsReady'=> ['init'=>"jqBiz('#panelAttr').panel('refresh',bizunoAjax+'&bizRt=$this->moduleID/attributes/attrDetails&rID='+jqBiz('#id').val()+'&data='+bizSelGet('invAttrCat'));"]]);
    }

    /**
     * Extends inventory/main/edit to pull the attributes and populate the grid once the panel has loaded
     * @param array $layout - Structure coming in
     */
    public function attrDetails(&$layout=[])
    {
        $rID    = clean('rID', 'integer','get');
        $title  = clean('data','text',   'get');
        $invAttr= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'bizProAttr', "id=$rID");
        $order  = 20;
        $fields = $keys = [];
        if (!empty($title)) {
            $values = json_decode($invAttr, true);
            $labels = getModuleCache('inventory', 'attr', $title, false, []);
            foreach ((array)$labels as $key => $label) {
                $fields[$key] = ['order'=>$order,'label'=>$label,'position'=>'after','attr'=>['value'=>!empty($values['attrs'][$key])?$values['attrs'][$key]:'']];
                $keys[] = $key;
                $order++;
            }
        }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML', 'divs'=>['main'=>['type'=>'fields','keys'=>$keys]], 'fields'=>$fields]);
    }

    /**
     * Load the attribute detail admin page
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminAttrLoad(&$layout=[])
    {
        $title  = clean('rID', 'text', 'get');
        $titles = $this->getTitles();
        if (!$title && sizeof($titles) > 0) { $title = $titles[0]['id']; }
        $jsHead = "function invAttrSave() {
    var title = bizSelGet('attrCat');
    jqBiz('#dgInvAttr').edatagrid('saveRow');
    var dgRows = JSON.stringify(jqBiz('#dgInvAttr').datagrid('getData'));
    jsonAction('$this->moduleID/attributes/adminAttrSave', title, dgRows);
}";
        $fields = ['attrCat'=>['order'=>10,'label'=>$this->lang['category'],'values'=>$titles,'attr'=>['type'=>'select','value'=>$title],
            'options' =>['editable'=>'true','onChange'=>"function() { jqBiz('#dgInvAttr').datagrid({url:'".BIZUNO_AJAX."&bizRt=$this->moduleID/attributes/adminRows&attrCat='+bizSelGet('attrCat')}); }"]]];
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => [
                'tbAttr'=> ['order'=>10, 'type'=>'toolbar', 'key' =>'tbInvAttr'],
                'head'  => ['order'=>20, 'type'=>'html',    'html'=>$this->lang['attr_intro']."<br /><br />"],
                'fields'=> ['order'=>30, 'type'=>'fields',  'keys'=>['attrCat']],
                'body'  => ['order'=>50, 'type'=>'datagrid','key' =>'dgInvAttr']],
            'toolbars'=> ['tbInvAttr'=>['icons'=>['save'=>['order'=>10,'label'=>lang('save'),'icon'=>'save','events'=>['onClick'=>"invAttrSave();"]]]]],
            'fields'  => $fields,
            'jsHead'  => ['invAttrB'=>$jsHead],
            'datagrid'=> ['dgInvAttr'=>$this->dgAttr('dgInvAttr', $title)]]);
    }

    /**
     *
     * @param type $layout
     */
    public function adminRows(&$layout=[])
    {
        $title = clean('attrCat', 'text', 'get');
        $output= [];
        $rows  = getModuleCache('inventory', 'attr', $title, false, []);
        foreach ($rows as $key => $label) { $output[] = ['index'=>$key, 'label'=>$label]; }
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($output), 'rows'=>$output])]);
    }

    /**
     * Saves the users choices of attributes for a given category
     */
    public function adminAttrSave(&$layout=[])
    {
        $title= clean('rID', 'text', 'get');
        $rows = clean('data','json', 'get');
        $attr = getModuleCache('inventory', 'attr');
        if (empty($rows['rows'])) { // all rows deleted, delete category
            unset($attr[$title]);
        } else { // edit or add
            $invAttr = [];
            foreach ($rows['rows'] as $row) {
                if (empty($row['index'])) { continue; }
                $invAttr[$row['index']] = $row['label'];
            }
            ksort($invAttr);
            $attr[$title] = $invAttr;
        }
        setModuleCache('inventory', 'attr', false, $attr);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizPanelRefresh('invAttr');"]]);
        msgAdd(lang('msg_settings_saved'), 'success');
    }

    /**
     * Pulls the category titles from the cache for pull down lists
     * @param type $addNull
     * @return type
     */
    private function getTitles($addNull = false) {
        $tmp   = array_keys(getModuleCache('inventory', 'attr'));
        $titles= $addNull ? [['id'=>'','text'=>lang('none')]] : [];
        foreach ($tmp as $value) { $titles[] = ['id'=>$value, 'text'=>$value]; }
        return $titles;
    }

    /**
     * Hook to handle the inventory attributes import from .csv format
     * @return null
     */
    public function apiImport()
    {
        msgDebug("\nEntering inventory:apiImport");
        if (!$security = validateAccess('admin', 2)) { return; }
        set_time_limit(600); // 10 minutes
        $structure= dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $rows = $this->prepData($structure);
        foreach ($rows as $row) {
            if (empty($row['sku'])) { continue; }
            if (empty($row['invAttrCat'])) { continue; }
            $attrs = getModuleCache('inventory', 'attr');
            if (empty($row['invAttrCat']) || empty($attrs)) { continue; } // nothing to do here
            $found = false;
            foreach ($attrs as $cat => $rows) {
                if (strtolower($cat)==strtolower($row['invAttrCat'])) { $found = true; break; } // we have a hit
            }
            if (!$found) { continue; } // if not found then we don't know the keys
            $output = [];
            foreach (array_keys($rows) as $idx) { if (!empty($row[$idx])) { $output[$idx] = $row[$idx]; } }
            ksort($output);
            msgDebug("\nReady to write attribute data = ".print_r($output, true));
            $rID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($row['sku'])."'");
            if (empty($rID)) { continue; } // SKU not found, here is not the place to create a new part
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['bizProAttr'=>json_encode(['category'=>$cat, 'attrs'=>$output])], 'update', "id=$rID");
        }
    }

    /**
     * reads the uploaded file and converts it into a keyed array
     * @param array $fields - table field structure
     * @return array - keyed data array of file contents
     */
    public function prepData($fields)
    {
        global $io;
        $skipRows= clean('selInvSkips', ['format'=>'integer', 'default'=>0], 'post');
        if (!$io->validateUpload('fileInventory', '', ['csv','txt'])) { return; } // removed type=text as windows edited files fail the test
        $this->template = $output = [];
        foreach ($fields as $field => $props) { $this->template[$props['tag']] = trim($field); }
        $rows    = array_map('str_getcsv', file($_FILES['fileInventory']['tmp_name']));
        for ($i=0; $i<$skipRows; $i++) { array_shift($rows); }
        $head    = array_shift($rows);
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        return $output;
    }

    /**
     * Prepare the attributes to upload to the cart
     * @param type $layout
     */
    public function apiInventory(&$product=[])
    {
        msgDebug("\nEntering inventory:attributes:apiInventory");
        if (empty($product['bizProAttr'])) { return; }
        $data = json_decode($product['bizProAttr'], true);
        $cat  = !empty($data['category']) ? $data['category'] : '';
        if (empty($cat)) { return; }
        $labels= getModuleCache('inventory', 'attr', $cat);
        if (empty($labels)) { return; }
        $product['AttributeCategory'] = $cat;
        foreach ($data['attrs'] as $key => $value) {
            $product['Attributes'][] = ['index'=>$key, 'title'=>!empty($labels[$key]) ? $labels[$key] : 'uncategorized', 'value'=>$value];
        }
        unset($product['bizProAttr']);
    }

    /**
     *
     * @param type $name
     * @param type $title
     * @return type
     */
    private function dgAttr($name, $title='')
    {
        return ['id' => $name,'type'=>'edatagrid',
            'attr'   => ['width'=>400,'toolbar'=>"#{$name}Toolbar",'idField'=>'index','rownumbers'=>true,'pagination'=>false,
                'url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/attributes/adminRows&attrCat=$title"],
            'events' => [
                'onLoadSuccess'=> "function(data)     { jqBiz('#dgInvAttr').datagrid('enableDnd'); if (data.total == 0) { jqBiz('#$name').edatagrid('addRow'); } }",
                'onClickRow'   => "function(rowIndex) { curIndex = rowIndex; }",
                'onBeginEdit'  => "function(rowIndex) { curIndex = rowIndex; }",
                'onDestroy'    => "function(rowIndex) { curIndex = undefined; }",
                'onAdd'        => "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=> [
                'attrNew' => ['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'=> ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>80],
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['trash'=>['icon'=>'trash','order'=>20,'size'=>'small','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jqBiz('#$name').edatagrid('deleteRow', curIndex);"]]]],
                'index' => ['order'=>20,'label'=>lang('index'),'attr'=>['width'=>140,'editor'=>'text','resizable'=>true]],
                'label' => ['order'=>40,'label'=>lang('label'),'attr'=>['width'=>280,'editor'=>'text','resizable'=>true]]]];
    }
}
