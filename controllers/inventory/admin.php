<?php
/*
 * Module inventory - Installation, Initialization and Settings
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
 * @version    7.x Last Update: 2025-07-07
 * @filesource /controllers/inventory/admin.php
 */

namespace bizuno;

class inventoryAdmin
{
    public $moduleID = 'inventory';
    public $pageID   = 'main';
    public $lang;
    public $defaults;
    public $settings;
    public $structure;
    public $phreeformProcessing;
    public $phreeformFormatting;
    public $job_units;
    public $notes;

    function __construct()
    {
        $this->lang      = getLang($this->moduleID);
//        $this->invMethods= ['byContact', 'bySKU', 'quantity', 'fxdDiscount']; // for install, pre-select some pricing methods to install
        $this->defaults  = [
            'sales'   => getChartDefault(30),
            'stock'   => getChartDefault(4),
            'nonstock'=> getChartDefault(34),
            'cogs'    => getChartDefault(32),
            'method'  => 'f'];
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure = [
            'api'       => ['path'=>'inventory/api/inventoryAPI'],
            'attachPath'=> ['inventory'=>'data/inventory/uploads/', 'production'=>'data/production/uploads/'],
            'menuBar'   => ['child'=>[
                'inventory'=> ['order'=>30,   'label'=>('inventory'),'group'=>'inv','icon'=>'inventory','child'=>[
                    'inv_mgr'    => ['order'=>20,'label'=>('gl_acct_type_4_mgr'),'icon'=>'inventory','route'=>"$this->moduleID/$this->pageID/manager"],
                    'invBulkEdit'=> ['order'=>70,'label'=>('bulk_edit'),         'icon'=>'edit',     'route'=>"$this->moduleID/bulkEdit/manager"],
                    'woProd'     => ['order'=>80,'label'=>('production'),        'icon'=>'work',     'route'=>"$this->moduleID/build/manager",'child'=>[
                        'woDesign'=>['order'=>10,'label'=>('wo_design'),         'icon'=>'design',   'route'=>"$this->moduleID/design/manager"],
                        'woTasks' =>['order'=>20,'label'=>('wo_tasks'),          'icon'=>'mimeLst',  'route'=>"$this->moduleID/tasks/manager"]]],
                    'rpt_inv'    => ['order'=>99,'label'=>('reports'),           'icon'=>'mimeDoc',  'route'=>'phreeform/main/manager&gID=inv']]]]],
            'hooks' => [
                'administrate'=>['roles'=>['edit'=>['order'=>10,'method'=>'rolesEdit'], 'save'=>['order'=>10,'method'=>'rolesSave']]],
                'phreebooks'  =>['tools'=>['fyCloseHome'=>['order'=>50,'page'=>'tools'],'fyClose'=>['order'=>50,'page'=>'tools']]]]];
        $this->phreeformProcessing = [
            'image_sku' => ['text'=>lang('image')." (".lang('sku').")"],
            'inv_image' => ['text'=>lang('image')." (".lang('id').")"],
            'inv_sku'   => ['text'=>lang('sku')." (".lang('id').")"],
            'inv_assy'  => ['text'=>lang('inventory_assy_cost')           ." (".lang('id') .")"],
            'inv_shrt'  => ['text'=>lang('inventory_description_short')   ." (".lang('id') .")"],
            'sku_name'  => ['text'=>lang('inventory_description_short')   ." (".lang('sku').")"],
            'inv_j06_id'=> ['text'=>lang('inventory_description_purchase')." (".lang('id').")"],
            'inv_j06'   => ['text'=>lang('inventory_description_purchase')." (".lang('sku').")"],
            'inv_j12_id'=> ['text'=>lang('inventory_description_sales')   ." (".lang('id').")"],
            'inv_j12'   => ['text'=>lang('inventory_description_sales')   ." (".lang('sku').")"],
            'inv_mv0'   => ['text'=>lang('current_sales')    .' (sku)'],
            'inv_mv1'   => ['text'=>lang('last_1month_sales').' (sku)'],
            'inv_mv3'   => ['text'=>lang('last_3month_sales').' (sku)'],
            'inv_mv6'   => ['text'=>lang('last_6month_sales').' (sku)'],
            'inv_mv12'  => ['text'=>lang('annual_sales')     .' (sku)'],
            'inv_stk'   => ['text'=>lang('qty_min')          .' (sku)'],
            'storeStock'=> ['text'=>lang('store_stock'),         'group'=>lang('ctype_b')],
            'sbBOM'     => ['text'=>$this->lang['sb_proc_bom'],  'group'=>lang('work_orders')],
            'sbOnOrder' => ['text'=>$this->lang['sb_proc_order'],'group'=>lang('work_orders')],
            'sbSteps'   => ['text'=>$this->lang['sb_step_list'], 'group'=>lang('work_orders')],
            'sbTask'    => ['text'=>$this->lang['sb_proc_task'], 'group'=>lang('work_orders')],
            'sbTaskList'=> ['text'=>$this->lang['sb_task_list'], 'group'=>lang('work_orders')],
            'sbRefDraw' => ['text'=>$this->lang['sb_proc_draw'], 'group'=>lang('work_orders')],
            'sbRefDocs' => ['text'=>$this->lang['sb_proc_docs'], 'group'=>lang('work_orders')]];
        $this->phreeformFormatting = [
            'buySell'=>['text'=>$this->lang['buy_sell_title'], 'group'=>$this->lang['title'], 'module'=>$this->moduleID, 'function'=>'inventoryView']];
        $this->setPriceProcessing($this->phreeformProcessing); // build dynamic processing based on quantity price sheets available
        setProcessingDefaults($this->phreeformProcessing, $this->moduleID, $this->lang['title']);
        $this->job_units = ['0'=>lang('minutes'), '1'=>lang('hours'), '2'=>lang('days')];
        $this->notes = [$this->lang['note_inventory_install_1']];
    }

    public function settingsStructure()
    {
        $weights  = [['id'=>'LB','text'=>lang('pounds')], ['id'=>'KG', 'text'=>lang('kilograms')]];
        $dims     = [
            ['id'=>'IN','text'=>lang('inches')],
            ['id'=>'FT','text'=>lang('feet')],
            ['id'=>'MM','text'=>lang('millimeters')],
            ['id'=>'CM','text'=>lang('centimeters')],
            ['id'=>'M', 'text'=>lang('meters')]];
        $autoCosts= [['id'=>'0','text'=>lang('none')],  ['id'=>'PO', 'text'=>lang('journal_id_4')], ['id'=>'PR', 'text'=>lang('journal_id_6')]];
        $invCosts = [['id'=>'f','text'=>lang('inventory_cost_method_f')], ['id'=>'l', 'text'=>lang('inventory_cost_method_l')], ['id'=>'a', 'text'=>lang('inventory_cost_method_a')]];
        $si = lang('inventory_type_si');
        $ms = lang('inventory_type_ms');
        $ma = lang('inventory_type_ma');
        $sr = lang('inventory_type_sr');
        $sa = lang('inventory_type_sa');
        $ns = lang('inventory_type_ns');
        $sv = lang('inventory_type_sv');
        $lb = lang('inventory_type_lb');
        $ai = lang('inventory_type_ai');
        $ci = lang('inventory_type_ci');
        $data = [
            'general'=> ['order'=>10,'label'=>lang('general'),'fields'=>[
                'weight_uom'     => ['values'=>$weights,  'attr'=>['type'=>'select', 'value'=>'LB']],
                'dim_uom'        => ['values'=>$dims,     'attr'=>['type'=>'select', 'value'=>'IN']],
                'tax_rate_id_c'  => ['defaults'=>['type'=>'c','target'=>'contacts'],'attr'=>['type'=>'tax','value'=>0]],
                'tax_rate_id_v'  => ['defaults'=>['type'=>'v','target'=>'contacts'],'attr'=>['type'=>'tax','value'=>0]],
                'auto_add'       => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'auto_cost'      => ['values'=>$autoCosts,'attr'=>['type'=>'select', 'value'=>0]],
                'allow_neg_stock'=> ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'stock_usage'    => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'inc_assemblies' => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'inc_committed'  => ['attr'=>['type'=>'selNoYes', 'value'=>1]]]],
            'phreebooks'=> ['order'=>20,'label'=>getModuleCache('phreebooks', 'properties', 'title'),'fields'=>[
                'sales_si'  => ['label'=>$this->lang['inv_sales_lbl'].$si,'tip'=>$this->lang['inv_sales_'].lang('inventory_type_si'),'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_si','value'=>$this->defaults['sales']]],
                'inv_si'    => ['label'=>$this->lang['inv_inv_lbl'].$si,  'tip'=>$this->lang['inv_inv_']  .$si,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_si',  'value'=>$this->defaults['stock']]],
                'cogs_si'   => ['label'=>$this->lang['inv_cogs_lbl'].$si, 'tip'=>$this->lang['inv_cogs_'] .$si,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_si', 'value'=>$this->defaults['cogs']]],
                'method_si' => ['label'=>$this->lang['inv_meth_lbl'].$si, 'tip'=>$this->lang['inv_meth_'] .$si,'values'=>$invCosts,'attr'=>['type'=>'select',        'value'=>$this->defaults['method']]],
                'sales_ms'  => ['label'=>$this->lang['inv_sales_lbl'].$ms,'tip'=>$this->lang['inv_sales_'].$ms,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_ms','value'=>$this->defaults['sales']]],
                'inv_ms'    => ['label'=>$this->lang['inv_inv_lbl'].$ms,  'tip'=>$this->lang['inv_inv_']  .$ms,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_ms',  'value'=>$this->defaults['stock']]],
                'cogs_ms'   => ['label'=>$this->lang['inv_cogs_lbl'].$ms, 'tip'=>$this->lang['inv_cogs_'] .$ms,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_ms', 'value'=>$this->defaults['cogs']]],
                'method_ms' => ['label'=>$this->lang['inv_meth_lbl'].$ms, 'tip'=>$this->lang['inv_meth_'] .$ms,'values'=>$invCosts,'attr'=>['type'=>'select',        'value'=>$this->defaults['method']]],
                'sales_ma'  => ['label'=>$this->lang['inv_sales_lbl'].$ma,'tip'=>$this->lang['inv_sales_'].$ma,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_ma','value'=>$this->defaults['sales']]],
                'inv_ma'    => ['label'=>$this->lang['inv_inv_lbl'].$ma,  'tip'=>$this->lang['inv_inv_']  .$ma,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_ma',  'value'=>$this->defaults['stock']]],
                'cogs_ma'   => ['label'=>$this->lang['inv_cogs_lbl'].$ma, 'tip'=>$this->lang['inv_cogs_'] .$ma,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_ma', 'value'=>$this->defaults['cogs']]],
                'method_ma' => ['label'=>$this->lang['inv_meth_lbl'].$ma, 'tip'=>$this->lang['inv_meth_'] .$ma,'values'=>$invCosts,'attr'=>['type'=>'select',        'value'=>$this->defaults['method']]],
                'sales_sr'  => ['label'=>$this->lang['inv_sales_lbl'].$sr,'tip'=>$this->lang['inv_sales_'].$sr,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_sr','value'=>$this->defaults['sales']]],
                'inv_sr'    => ['label'=>$this->lang['inv_inv_lbl'].$sr,  'tip'=>$this->lang['inv_inv_']  .$sr,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_sr',  'value'=>$this->defaults['stock']]],
                'cogs_sr'   => ['label'=>$this->lang['inv_cogs_lbl'].$sr, 'tip'=>$this->lang['inv_cogs_'] .$sr,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_sr', 'value'=>$this->defaults['cogs']]],
                'method_sr' => ['label'=>$this->lang['inv_meth_lbl'].$sr, 'tip'=>$this->lang['inv_meth_'] .$sr,'values'=>$invCosts,'attr'=>['type'=>'select',        'value'=>$this->defaults['method']]],
                'sales_sa'  => ['label'=>$this->lang['inv_sales_lbl'].$sa,'tip'=>$this->lang['inv_sales_'].$sa,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_sa','value'=>$this->defaults['sales']]],
                'inv_sa'    => ['label'=>$this->lang['inv_inv_lbl'].$sa,  'tip'=>$this->lang['inv_inv_']  .$sa,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_sa',  'value'=>$this->defaults['stock']]],
                'cogs_sa'   => ['label'=>$this->lang['inv_cogs_lbl'].$sa, 'tip'=>$this->lang['inv_cogs_'] .$sa,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_sa', 'value'=>$this->defaults['cogs']]],
                'method_sa' => ['label'=>$this->lang['inv_meth_lbl'].$sa, 'tip'=>$this->lang['inv_meth_'] .$sa,'values'=>$invCosts,'attr'=>['type'=>'select',        'value'=>$this->defaults['method']]],
                'sales_ns'  => ['label'=>$this->lang['inv_sales_lbl'].$ns,'tip'=>$this->lang['inv_sales_'].$ns,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_ns','value'=>$this->defaults['sales']]],
                'inv_ns'    => ['label'=>$this->lang['inv_inv_lbl'].$ns,  'tip'=>$this->lang['inv_inv_']  .$ns,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_ns',  'value'=>$this->defaults['nonstock']]],
                'cogs_ns'   => ['label'=>$this->lang['inv_cogs_lbl'].$ns, 'tip'=>$this->lang['inv_cogs_'] .$ns,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_ns', 'value'=>$this->defaults['cogs']]],
                'sales_sv'  => ['label'=>$this->lang['inv_sales_lbl'].$sv,'tip'=>$this->lang['inv_sales_'].$sv,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_sv','value'=>$this->defaults['sales']]],
                'inv_sv'    => ['label'=>$this->lang['inv_inv_lbl'].$sv,  'tip'=>$this->lang['inv_inv_']  .$sv,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_sv',  'value'=>$this->defaults['nonstock']]],
                'cogs_sv'   => ['label'=>$this->lang['inv_cogs_lbl'].$sv, 'tip'=>$this->lang['inv_cogs_'] .$sv,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_sv', 'value'=>$this->defaults['cogs']]],
                'sales_lb'  => ['label'=>$this->lang['inv_sales_lbl'].$lb,'tip'=>$this->lang['inv_sales_'].$lb,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_lb','value'=>$this->defaults['sales']]],
                'inv_lb'    => ['label'=>$this->lang['inv_inv_lbl'].$lb,  'tip'=>$this->lang['inv_inv_']  .$lb,'attr'=>['type'=>'ledger','id'=>'phreebooks_inv_lb',  'value'=>$this->defaults['nonstock']]],
                'cogs_lb'   => ['label'=>$this->lang['inv_cogs_lbl'].$lb, 'tip'=>$this->lang['inv_cogs_'] .$lb,'attr'=>['type'=>'ledger','id'=>'phreebooks_cogs_lb', 'value'=>$this->defaults['cogs']]],
                'sales_ai'  => ['label'=>$this->lang['inv_sales_lbl'].$ai,'tip'=>$this->lang['inv_sales_'].$ai,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_ai','value'=>$this->defaults['sales']]],
                'sales_ci'  => ['label'=>$this->lang['inv_sales_lbl'].$ci,'tip'=>$this->lang['inv_sales_'].$ci,'attr'=>['type'=>'ledger','id'=>'phreebooks_sales_ci','value'=>$this->defaults['sales']]]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    /**
     * Adds the processing options for price sheets based on the users database settings
     * @param array $processing
     * @return - modified $processing
     */
    private function setPriceProcessing(&$processing)
    {

        $rows = getMetaContact(0, 'prices_quantity_', true);
        if (empty($rows)) { return; }
        foreach ($rows as $row) {
            $settings = json_decode($row['settings'], true);
            $processing["skuPS:{$row['id']}"] = ['text'=>lang('price').": {$settings['title']} (".lang('sku').")"];
        }
    }

    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $fields = [
            'invValDesc'   => ['order'=>10,'html'=>$this->lang['inv_tools_val_inv_desc'], 'attr'=>['type'=>'raw']],
            'btnHistTest'  => ['order'=>20,'label'=>$this->lang['inv_tools_repair_test'], 'attr'=>['type'=>'button','value'=>$this->lang['inv_tools_btn_test']],
                'events' => ['onClick'=>"jsonAction('$this->moduleID/tools/historyTestRepair', 0, 'test');"]],
            'btnHistFix'   => ['order'=>10,'label'=>$this->lang['inv_tools_repair_fix'],  'attr'=>['type'=>'button', 'value'=>$this->lang['inv_tools_btn_repair']],
                'events' => ['onClick'=>"jsonAction('$this->moduleID/tools/historyTestRepair', 0, 'fix');"]],
            'invDrillDesc' => ['order'=>10,'html'=>$this->lang['inv_sku_drill_desc'],     'attr'=>['type'=>'raw']],
            'invDrillSku'  => ['order'=>20,'attr'=> ['type'=>'inventory']],
            'invDrillDate' => ['order'=>30,'attr'=> ['type'=>'date',  'value'=>localeCalculateDate(biz_date('Y-m-d'), 0, -6)]],
            'btnDrillGo'   => ['order'=>40,'attr'=> ['type'=>'button','value'=>lang('go')],
                'events' => ['onClick'=>"jsonAction('$this->moduleID/tools/skuDrillDown', bizSelGet('invDrillSku'), jqBiz('#invDrillDate').datebox('getText'));"]],
            'invRecalcDesc'=> ['order'=>10,'html'=>$this->lang['inv_sku_recalc_desc'],    'attr'=>['type'=>'raw']],
            'btnRecalcGo'  => ['order'=>40,'attr'=> ['type'=>'button','value'=>lang('go')],
                'events' => ['onClick'=>"jsonAction('$this->moduleID/tools/recalcHistory');"]],
            'invAllocDesc' => ['order'=>10,'html'=>$this->lang['inv_tools_qty_alloc_desc'],'attr'=>['type'=>'raw']],
            'btnAllocFix'  => ['order'=>20,'label'=>'', 'attr'=>['type'=>'button', 'value'=>$this->lang['inv_tools_qty_alloc_label']],
                'events' => ['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/tools/qtyAllocRepair');"]],
            'invSoPoDesc'  => ['order'=>10,'html'=>$this->lang['inv_tools_validate_so_po_desc'],'attr'=>['type'=>'raw']],
            'btnJournalFix'=> ['order'=>20,'label'=>'', 'attr'=>['type'=>'button', 'value'=>$this->lang['inv_tools_btn_so_po_fix']],
                'events' => ['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/tools/onOrderRepair');"]],
            'invPriceDesc' => ['order'=>10,'html'=>$this->lang['inv_tools_price_assy_desc'],'attr'=>['type'=>'raw']],
            'btnPriceAssy' => ['order'=>20,'label'=>'', 'attr'=>['type'=>'button', 'value'=>lang('go')],
                'events' => ['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/tools/priceAssy');"]],
            'analyzeDesc'=> ['order'=>10,'html' =>$this->lang['inv_analyze_desc'],'attr'=>['type'=>'raw']],
            'analyzeBtn' => ['order'=>20,'attr'=>['type'=>'button', 'value'=>lang('go')],
                'events' => ['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/tools/invBalance');"]]];
        $data = [
            'tabs' => ['tabAdmin'=> ['divs'=>[
                'woTasks'=> ['order'=>60,'label'=>sprintf(lang('tbd_tasks'), lang('work_order')),'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/tasks/manager'"]],
                'invAttr'=> ['order'=>70,'label'=>$this->lang['attributes'],'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/attributes/adminAttrLoad'"]],
                'tools'  => ['order'=>80,'label'=>lang('tools'),'type'=>'divs','divs'=>[
                    'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                        'invVal'   => ['order'=>10,'type'=>'panel','classes'=>['block50'],'key'=>'invVal'],
                        'invDrill' => ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'invDrill'],
                        'invRecalc'=> ['order'=>30,'type'=>'panel','classes'=>['block50'],'key'=>'invRecalc'],
                        'invAlloc' => ['order'=>40,'type'=>'panel','classes'=>['block50'],'key'=>'invAlloc'],
                        'invSoPo'  => ['order'=>50,'type'=>'panel','classes'=>['block50'],'key'=>'invSoPo'],
                        'invPrice' => ['order'=>60,'type'=>'panel','classes'=>['block50'],'key'=>'invPrice'],
                        'analyze'  => ['order'=>70,'type'=>'panel','classes'=>['block50'],'key'=>'analyze']]]]]]]],
            'panels'  => [
                'invVal'   => ['label'=>$this->lang['inv_tools_val_inv'],     'type'=>'fields','keys'=>['invValDesc',   'btnHistTest','btnHistFix']],
                'invDrill' => ['label'=>$this->lang['inv_sku_drill_title'],   'type'=>'fields','keys'=>['invDrillDesc', 'invDrillSku','invDrillDate','btnDrillGo']],
                'invRecalc'=> ['label'=>$this->lang['inv_sku_recalc_title'],  'type'=>'fields','keys'=>['invRecalcDesc','btnRecalcGo']],
                'invAlloc' => ['label'=>$this->lang['inv_tools_qty_alloc'],   'type'=>'fields','keys'=>['invAllocDesc', 'btnAllocFix']],
                'invSoPo'  => ['label'=>$this->lang['inv_tools_repair_so_po'],'type'=>'fields','keys'=>['invSoPoDesc','btnJournalFix']],
                'invPrice' => ['label'=>$this->lang['inv_tools_price_assy'],  'type'=>'fields','keys'=>['invPriceDesc', 'btnPriceAssy']]],
            'fields' => $fields];
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
        // Inv Pro Tools
        if (!$security = validateAccess('admin', 1)) { return; }
        // dd tab for work order tasks
        $layout['panel']['analyze'] = ['label'=>$this->lang['inv_sku_drill_title'], 'type'=>'fields', 'keys'=>['analyzeDesc', 'analyzeBtn']];
    }

    /**
     *
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    /**
     * Extends the Roles - Edit - PhreeBooks tab to add Sales and Purchase access
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function rolesEdit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        $role= dbMetaGet($rID, 'bizuno_role');
        $layout['fields']['group_mfg']= ['order'=>50,'label'=>$this->lang['role_mfg'],'tip'=>'',
            'attr'=>['type'=>'checkbox','checked'=>!empty($role['groups']['mfg'])?true:false]];
        $layout['tabs']['tabRoles']['divs']['inventory']['divs']['props'] = ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'invSettings'];
        $layout['panels']['invSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>['group_mfg']];
    }

    /**
     * Extends the Roles settings to Save the PhereeBooks Specific settings
     * @return boolean null
     */
    public function rolesSave()
    {
        if (!$security = validateAccess('admin', 3))       { return; }
        if (empty($rID = clean('_rID', 'integer', 'post'))){ return; }
        $role = dbMetaGet($rID, 'bizuno_role');
        metaIdxClean($role);
        $role['groups']['mfg'] = clean('group_mfg', 'boolean', 'post');
        dbMetaSet($rID, 'bizuno_role', $role);
    }

    public function initialize()
    {
        // load common price sheets into cache
        $pricesC= $pricesV = ['id'=>0, 'text'=>lang('none')];
        $rowsC  = dbGetMulti(BIZUNO_DB_PREFIX.'common_meta', "meta_key LIKE 'price_c%'");
        foreach ($rowsC as $row) {
            $values = json_decode($row['meta_value'], true);
            $pricesC[] = ['id'=>$row['id'], 'text'=>$values['title']];
        }
        setModuleCache('inventory', 'prices_c', '', $pricesC);
        $rowsV = dbGetMulti(BIZUNO_DB_PREFIX.'common_meta', "meta_key LIKE 'price_v%'");
        foreach ($rowsV as $row) {
            $values = json_decode($row['meta_value'], true);
            $pricesV[] = ['id'=>$row['id'], 'text'=>$values['title']];
        }
        setModuleCache('inventory', 'prices_v', '', $pricesV);
        return true;
    }

    /**
     * @TODO - Deprecated
     * @param type $layout
     */
    public function install()
    {
//        $bAdmin = new bizunoSettings();
//        foreach ($this->invMethods as $method) {
//            $bAdmin->methodInstall($layout, ['module'=>'inventory', 'path'=>'prices', 'method'=>$method], false);
//        }
    }

    /**
     * This method adds standard definition physical fields to the inventory table
     */
    public function installPhysicalFields()
    {
        $id = validateTab('inventory', lang('physical'), 80);
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'length')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD length FLOAT NOT NULL DEFAULT '0' COMMENT 'tag:ProductLength;tab:$id;order:20'"); }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'width'))  { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD width  FLOAT NOT NULL DEFAULT '0' COMMENT 'tag:ProductWidth;tab:$id;order:30'"); }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'height')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD height FLOAT NOT NULL DEFAULT '0' COMMENT 'tag:ProductHeight;tab:$id;order:40'"); }
    }
}
