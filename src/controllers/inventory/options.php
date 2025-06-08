<?php
/*
 * Bizuno Extension - Bizuno Pro Inventory plugin - Options
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/inventory/options.php
 */

namespace bizuno;

class proInvOptions
{
    public  $moduleID = 'inventory';

    function __construct()
    {
    }

    /**
     * Edit structure for this extension
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function optionsEdit(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        $curOpt= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invOptions', "id=$rID");
        msgDebug("\nRead curOpt = ".print_r($curOpt, true));
        if (empty($curOpt)) { $curOpt = json_encode([]); }
        $data  = ['type'=>'divHTML',
            'divs'    => [
                'general' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genProp'=> ['order'=>10,'type'=>'panel','classes'=>['block50'],'key'=>'genProp'],
                    'genAttr'=> ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'genAttr'],
                    'genSku' => ['order'=>30,'type'=>'panel','classes'=>['block50'],'key'=>'genSku'],
                    'genOpt' => ['order'=>40,'type'=>'panel','classes'=>['block50'],'key'=>'genOpt']]]],
            'panels'  => [
                'genProp'=> ['label'=>lang('properties'),         'type'=>'fields',  'keys'=>['invOptions','msTitle']],
                'genSku' => ['label'=>$this->lang['skus_created'],'type'=>'datagrid','key'=>'dgSKUs'],
                'genOpt' => ['label'=>lang('options'),            'type'=>'datagrid','key'=>'dgOptions'],
                'genAttr'=> ['label'=>lang('attributes'),         'type'=>'datagrid','key'=>'dgOptAttr']],
            'datagrid'=> [
                'dgSKUs'   => $this->dgSKUs('dgSKUs'),
                'dgOptions'=> $this->dgOptions('dgOptions', $rID),
                'dgOptAttr'=> $this->dgOptAttr('dgOptAttr')],
            'fields'  => [
                'invOptions'=> ['attr'=>['type'=>'hidden','value'=>$curOpt]],
                'msTitle'   => ['label'=>lang('title')]],
            'jsHead'  => ['jsOptions'=>"var dgOptionsData = ".$curOpt.";\n".$this->getViewJS()]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @return string
     */
    private function getViewJS()
    {
        return "
function msSaveAttrs() {
    var skuAttr = new Array();
    var skuLbl  = new Array();
    jqBiz('#dgOptAttr').edatagrid('saveRow');
    var rowData = jqBiz('#dgOptAttr').edatagrid('getRows');
    for (var idx=0; idx<rowData.length; idx++) {
        if (!rowData[idx]['label']) continue;
        skuLbl[idx]  = rowData[idx]['label'];
        skuAttr[idx] = rowData[idx]['suffix'];
    }
    if (!skuAttr.length) return;
    var strAttrs = skuAttr.join(';');
    var strLbls  = skuLbl.join(';');
    var title = jqBiz('#msTitle').val();
    var optData = jqBiz('#dgOptions').datagrid('getData');
    optData['rows'].push({ 'attrs':strAttrs, 'option':title, 'labels':strLbls });
    jqBiz('#dgOptions').datagrid({ data:optData });
    jqBiz('#msTitle').val('');
    jqBiz('#dgOptAttr').datagrid({ data:[{}] });
    var serOpt = jqBiz('#dgOptions').datagrid('getData');
    jqBiz('#invOptions').val(JSON.stringify(serOpt['rows']));
    msBuildSkuList();
}
function msBuildSkuList() {
    var skuData = [ { ms_sku:jqBiz('#sku').val()+'-', ms_desc:jqBiz('#description_sales').val() } ];
    var options = jqBiz('#dgOptions').edatagrid('getRows');
    for (var i=0; i<options.length; i++) {
        var attrs = options[i]['attrs'].split(';');
        var labels= options[i]['labels'].split(';');
        var tmpData = skuData;
        skuData = [];
        for (j=0; j<tmpData.length; j++) {
            for (k=0; k<attrs.length; k++) {
                var t = (tmpData.length * k) + j;
                if (typeof skuData[t] == 'undefined') skuData[t] = {};
                skuData[t].ms_sku  = tmpData[j].ms_sku +''+attrs[k]; // force string
                skuData[t].ms_desc = tmpData[j].ms_desc+' -'+labels[k];
            }
        }
    }
    jqBiz('#dgSKUs').datagrid({ data: skuData });
}
function invOptionsDel(idx) {
    var rowData = jqBiz('#dgOptions').datagrid('getData');
    rowData['rows'].splice(idx, 1);
    jqBiz('#dgOptions').datagrid({ data:rowData });
    jqBiz('#invOptions').val(JSON.stringify(rowData['rows']));
}
function invOptionsEdit(idx) {
    var rowData = jqBiz('#dgOptions').edatagrid('getRows')[idx];
    jqBiz('#msTitle').val(rowData['option']);
    var sfx = rowData['attrs'].split(';');
    var lbl = rowData['labels'].split(';');
    dgData = [];
    for (i=0; i<lbl.length; i++) dgData.push({'id':i,'label':lbl[i],'suffix':sfx[i]});
    jqBiz('#dgOptAttr').datagrid({ data:dgData });
}";
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgOptions($name='dgOptions')
    {
        return ['id'=>$name, 'type'=>'datagrid',
            'attr'   => ['toolbar'=>"#{$name}Bar"],
            'events' => ['data'=>"{$name}Data",
                'onDblClickRow'=>"function(rowIndex, rowData){ invOptionsEdit(rowIndex); }"],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'), 'attr'=>['width'=>80], 'events'=>['formatter'=>$name.'Formatter'],
                    'actions'   => [
                        'invOptEdit' => ['order'=>30,'icon'=>'edit', 'label'=>lang('edit'), 'events'=>['onClick'=>"var rowIndex=jqBiz('#$name').datagrid('getRowIndex', jqBiz('#$name').datagrid('getSelected')); invOptionsEdit(rowIndex);"]],
                        'invOptTrash'=> ['order'=>90,'icon'=>'trash','label'=>lang('trash'),'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) { var rowIndex=jqBiz('#$name').datagrid('getRowIndex', jqBiz('#$name').datagrid('getSelected')); invOptionsDel(rowIndex); msBuildSkuList(); }"]]]],
                'option'=> ['order'=>10,'label'=>lang('title'),     'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]],
                'labels'=> ['order'=>20,'label'=>lang('labels'),    'attr'=>['width'=>160,'sortable'=>true,'resizable'=>true]],
                'attrs' => ['order'=>30,'label'=>lang('attributes'),'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true]]]];
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgOptAttr($name='dgOptAttr')
    {
        return ['id'=>$name, 'type'=>'edatagrid', 'rows'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'page'=>1,
            'attr' => ['toolbar'=>"#{$name}Bar", 'idField'=>'id'],
            'source' => ['actions'=>[
                'newAttr' => ['order'=>10,'icon'=>'add', 'events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]],
                'saveAttr'=> ['order'=>50,'icon'=>'save','events'=>['onClick'=>"msSaveAttrs();"]]]],
            'columns'=> ['id'=>['order'=>0, 'attr'=>['hidden'=>true]],
                'action' => ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>150],'events'=>['formatter'=>"{$name}Formatter"],
                    'actions'   => ['invAttrTrash'=> ['order'=>90,'icon'=>'trash','events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'label' => ['order'=>10,'label'=>lang('label'),'attr'=>['width'=>120,'editor'=>'text','sortable'=>true,'resizable'=>true]],
                'suffix'=> ['order'=>20,'label'=>$this->lang['sku_suffix'],'attr'=>['width'=>240,'editor'=>'text','sortable'=>true,'resizable'=>true]]]];
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgSKUs($name='dgSKUs')
    {
        return ['id'=>$name, 'type'=>'edatagrid', 'rows'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'page'=>1,
            'attr'   => ['idField'=>'id'],
            'columns'=> [
                'id'     => ['order'=>0, 'attr'=>['hidden'=>true]],
                'ms_sku' => ['order'=>10,'label'=>lang('sku'),        'attr'=>['width'=>120,'sortable'=>true,'resizable'=>true]],
                'ms_desc'=> ['order'=>20,'label'=>lang('description'),'attr'=>['width'=>480,'sortable'=>true,'resizable'=>true]]]];
    }
}
