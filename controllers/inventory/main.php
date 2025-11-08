<?php
/*
 * Module Inventory main functions
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
 * @version    7.x Last Update: 2025-11-08
 * @filesource /controllers/inventory/main.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/inventory/functions.php', 'inventoryProcess', 'function');

class inventoryMain
{
    public $moduleID = 'inventory';
    public $pageID   = 'main';
    public $lang;
    public $inventoryTypes;
    public $dbDefault;
    public $uom;
    public $myStore;
    public $defaults;

    function __construct()
    {
        $this->lang= getLang($this->moduleID);
        $defaults  = [
            'sales'   => getChartDefault(30),
            'stock'   => getChartDefault(4),
            'nonstock'=> getChartDefault(34),
            'cogs'    => getChartDefault(32),
            'method'  => 'f'];
        $inventoryTypes = [
            'si' => ['id'=>'si','text'=>lang('inventory_type_si'),'hidden'=>0,'tracked'=>1,'order'=>10,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Stock Item
            'sr' => ['id'=>'sr','text'=>lang('inventory_type_sr'),'hidden'=>0,'tracked'=>1,'order'=>15,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Serialized
            'ma' => ['id'=>'ma','text'=>lang('inventory_type_ma'),'hidden'=>0,'tracked'=>1,'order'=>25,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Assembly
            'sa' => ['id'=>'sa','text'=>lang('inventory_type_sa'),'hidden'=>0,'tracked'=>1,'order'=>30,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']], // Serialized Assembly
            'ns' => ['id'=>'ns','text'=>lang('inventory_type_ns'),'hidden'=>0,'tracked'=>0,'order'=>35,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Non-stock
            'lb' => ['id'=>'lb','text'=>lang('inventory_type_lb'),'hidden'=>0,'tracked'=>0,'order'=>40,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Labor
            'sv' => ['id'=>'sv','text'=>lang('inventory_type_sv'),'hidden'=>0,'tracked'=>0,'order'=>45,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Service
            'sf' => ['id'=>'sf','text'=>lang('inventory_type_sf'),'hidden'=>0,'tracked'=>0,'order'=>50,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Flat Rate Service
            'ci' => ['id'=>'ci','text'=>lang('inventory_type_ci'),'hidden'=>0,'tracked'=>0,'order'=>55,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Charge
            'ai' => ['id'=>'ai','text'=>lang('inventory_type_ai'),'hidden'=>0,'tracked'=>0,'order'=>60,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Activity
            'ds' => ['id'=>'ds','text'=>lang('inventory_type_ds'),'hidden'=>0,'tracked'=>0,'order'=>65,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['nonstock'],'gl_cogs'=>false,'method'=>false], // Description
            'ia' => ['id'=>'ia','text'=>lang('inventory_type_ia'),'hidden'=>1,'tracked'=>1,'order'=>99,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>false,'method'=>false], // Assembly Part
            'mi' => ['id'=>'mi','text'=>lang('inventory_type_mi'),'hidden'=>1,'tracked'=>1,'order'=>99,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],   'gl_cogs'=>false,'method'=>false]]; // Master Stock Sub Item
        $this->inventoryTypes = array_merge_recursive($inventoryTypes, getModuleCache('inventory', 'phreebooks'));
        $this->dbDefault = [
            'id'           => 0,
            'store_id'     => 0,
            'gl_sales'     => getModuleCache('inventory', 'settings', 'phreebooks', 'sales_si'),
            'gl_inv'       => getModuleCache('inventory', 'settings', 'phreebooks', 'inv_si'),
            'gl_cogs'      => getModuleCache('inventory', 'settings', 'phreebooks', 'cogs_si'),
            'cost_method'  => getModuleCache('inventory', 'settings', 'phreebooks', 'method_si'),
            'tax_rate_id_v'=> getModuleCache('inventory', 'settings', 'general',    'tax_rate_id_v'),
            'tax_rate_id_c'=> getModuleCache('inventory', 'settings', 'general',    'tax_rate_id_c')];
        $this->uom = [
            'ea'  => $this->lang['uom_ea'],
            'box' => $this->lang['uom_box'],
            'pkg' => $this->lang['uom_pkg'],
            'bag' => $this->lang['uom_bag'],
            'tub' => $this->lang['uom_tub'],
            'spl' => $this->lang['uom_spl'],
            'plt' => $this->lang['uom_plt'],
            'brl' => $this->lang['uom_brl'],
            'rol' => $this->lang['uom_rol'],
            'in'  => $this->lang['uom_in'],
            'ft'  => $this->lang['uom_ft'],
            'yd'  => $this->lang['uom_yd'],
            'cm'  => $this->lang['uom_cm'],
            'mtr' => $this->lang['uom_mtr'],
            'oz'  => $this->lang['uom_oz'],
            'pnt' => $this->lang['uom_pnt'],
            'gal' => $this->lang['uom_gal'],
            'keg' => $this->lang['uom_keg'],
            'ml'  => $this->lang['uom_ml'],
            'ltr' => $this->lang['uom_ltr']];
        if (validateAccess('inv_mgr', 5, false) && !defined('BIZ_RULE_ASSY_UNLOCK') ) { define('BIZ_RULE_ASSY_UNLOCK', true); }
    }

    /**
     * Main entry point for inventory module
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $title = sprintf(lang('tbd_manager'),lang('gl_acct_type_4'));
        $layout= array_replace_recursive($layout, viewMain(), ['title'=>$title,
            'divs'     => [
                'invMgr' => ['order'=>50,'type'=>'accordion','key' =>'accInventory']],
            'accordion'=> ['accInventory'=>['divs'=>[
                'divInventoryManager'=> ['order'=>30,'label'=>$title,         'type'=>'datagrid','key' =>'manager'],
                'divInventoryDetail' => ['order'=>70,'label'=>lang('details'),'type'=>'html',    'html'=>'&nbsp;']]]],
            'datagrid' => ['manager'=>$this->dgInventory('dgInventory', 'none', $security)],
            'jsReady'  =>['init'=>"bizFocus('search', 'dgInventory');"]]);
        // Old Pro stuff to be integrated
        if ($security > 2 && isset($layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'])) {
            $layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'] .= " && row.inventory_type!='mi'";
        } elseif($security > 2) {
            $layout['datagrid']['manager']['columns']['action']['actions']['rename']['display'] = "row.inventory_type!='mi'";
        }
        if ($security > 1 && isset($layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'])) {
            $layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'] .= " && row.inventory_type!='mi'";
        } elseif ($security > 1) {
            $layout['datagrid']['manager']['columns']['action']['actions']['copy']['display'] = "row.inventory_type!='mi'";
        }
    }

    /**
     * Lists inventory rows for the manager grid filtered by users request
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID   = clean('rID',   'integer', 'get');
        $filter= clean('filter',['format'=>'text', 'default'=>'none'], 'get');
        $_POST['search']= getSearch();
        $_POST['rows']  = clean('rows', ['format'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'get');
        msgDebug("\n ready to build inventory datagrid, security = $security");
        $structure = $this->dgInventory('dgInventory', $filter, $security);
        if ($rID) { $structure['source']['filters']['rID'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."inventory.id=$rID"]; }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$structure]]);
    }

    /**
     * Saves the users filter settings in cache
     */
    private function managerSettings()
    {
        $clrSearch= clean('clr', 'boolean', 'get');
        $defRows  = $clrSearch ? 100 : getModuleCache('bizuno', 'settings', 'general', 'max_rows');
        $data     = ['path'=>'inventory', 'values'=>  [
            ['index'=>'rows',  'clean'=>'integer','default'=>$defRows, 'method'=>'request'],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX."inventory.sku"],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0',    'clean'=>'char',   'method'=>'request','default'=>'y'],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        if ($clrSearch) { clearUserCache($data['path']); }
        $this->defaults = updateSelection($data);
    }

    /**
     * Generates the grid structure for managing bills of materials
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerBOM(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1, false)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $assyData= "var assyData = ".json_encode(['total'=>0,'rows'=>[]]).";";
        $locked  = false;
        if (!empty($rID)) {
            $sku     = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
            $locked  = defined('BIZ_RULE_ASSY_UNLOCK') && !empty(BIZ_RULE_ASSY_UNLOCK) ? false : dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='$sku'");
            $tmp     = []; // $tmp is an empty layout to populate in compose
            compose($this->moduleID, $this->pageID, 'managerBOMList', $tmp); // $tmp is an empty layout to populate
            $assyData= "var assyData = ".json_encode($tmp['content']).";";
        }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'    => ['divVendGrid'=> ['order'=>30,'type'=>'datagrid','key'=>'dgAssembly']],
            'datagrid'=> ['dgAssembly' => $this->dgAssembly('dgAssembly', $locked)],
            'jsHead'  => ['mgrBOMdata' => $assyData]]);
        if (!$locked) { $layout['jsReady']['mgrBOM'] = "jqBiz('dgAssembly').edatagrid('addRow');"; }
    }

    /**
     * Lists the rows of a bill of materials
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function managerBOMList(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $skuID  = clean('rID', 'integer', 'get');
        $storeID= clean('bID', ['format'=>'integer', 'default'=>-1], 'get');
        if (empty($skuID)) { return msgAdd("Cannot process assy list, no SKU ID provided!"); }
        $result = dbMetaGet(0, 'bill_of_materials', 'inventory', $skuID);
        msgDebug("\nRead meta valuefrom db: ".print_r($result, true));
        $totCost = $totQty = 0;
        if (empty($result)) { $result = []; }
        else { foreach ($result as $key => $row) {
            if (empty($row['sku'])) { unset($result[$key]); continue; } // non-data rows
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['inventory_type','item_cost'], "sku='{$row['sku']}'");
            $result[$key]['qty_stock']= in_array($inv['inventory_type'], INVENTORY_COGS_TYPES) ? dbGetStoreQtyStock($row['sku'], $storeID) : '-'; // only show stock if tracked in inventory else '-'
            $result[$key]['item_cost']= in_array($inv['inventory_type'], INVENTORY_COGS_TYPES) ? $row['qty'] * $inv['item_cost'] : 0;
            $result[$key]['qty']      = $row['qty'];
            $totCost+= $row['qty'] * $inv['item_cost'];
            $totQty += $row['qty'];
        } }
        $output = ['total'=>sizeof($result),'rows'=>array_values($result),'footer'=>[['action'=>'&nbsp;', 'description'=>lang('total'), 'qty'=>$totQty, 'item_cost'=>$totCost]]];
        msgDebug("\nLeaving managerBOMList with data = ".print_r($output, true));
        $layout = array_replace_recursive($layout, ['content'=>$output]);
        // buy sell adds:
        msgDebug("\nBOM rows for skuID $skuID is ".print_r($layout['content']['rows'], true));
        foreach ($layout['content']['rows'] as $key => $row) {
            $sellQty = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sell_qty', "sku='".addslashes($row['sku'])."'");
            $newCost = $layout['content']['rows'][$key]['item_cost'];
            if (!empty($row['qty_required']) && !empty($sellQty)) {
                $buyCost = $row['item_cost'] / $row['qty_required'];
                $newCost = ($buyCost / $sellQty) * $row['qty_required'];
                msgDebug("\nsellQty = $sellQty and buyCost = $buyCost and newCost = $newCost");
            }
            $layout['content']['rows'][$key]['item_cost'] = $newCost;
        }
    }

    /**
     * Generates the inventory item edit structure
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function edit(&$layout=[])
    {
        $security    = validateAccess('inv_mgr', 1);
        $rID         = clean('rID', 'integer', 'get');
        $tabID       = clean('tabID', 'text', 'get');
        $cost_methods= [['id'=>'f','text'=>lang('cost_method_f')],['id'=>'l','text'=>lang('cost_method_l')],['id'=>'a','text'=>lang('cost_method_a')]];
        $structure   = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
        $dbData      = $rID ? dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id='$rID'") : $this->dbDefault;
        dbStructureFill($structure, $dbData);
        $inv_type    = $structure['inventory_type']['attr']['value'];
        $fldProp     = ['id','qty','dg_assy','store_id','sku','inactive','description_short','upc_code','item_weight','lead_time'];
        $fldStatus   = ['qty_min','qty_restock','qty_stock','qty_po','qty_so','qty_alloc'];
        $fldImage    = ['image_with_path'];
        $fldCust     = ['description_sales','full_price','sale_price','tax_rate_id_c','price_sheet_c', 'block_discount'];
        $fldVend     = ['description_purchase','item_cost','tax_rate_id_v','price_sheet_v','vendor_id'];
        $fldGL       = ['inventory_type','cost_method','gl_sales','gl_inv','gl_cogs'];
        // add additional fields
        $structure['dg_assy'] = ['attr'=>['type'=>'hidden']];
        if (validateAccess('prices_c', 1, false)) {
            $structure['full_price']['break'] = false;
            $structure['show_prices_c'] = ['order'=>41,'icon'=>'price','label'=>lang('prices'),'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=c&itemCost='+bizNumGet('item_cost')+'&fullPrice='+bizNumGet('full_price'), $rID);"]];
            $fldCust[] = 'show_prices_c';
        }
        if (validateAccess('prices_v', 1, false)) {
            $structure['item_cost']['break'] = false;
            $structure['show_prices_v'] = ['order'=>41,'icon'=>'price','label'=>lang('prices'),'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=v&itemCost='+bizNumGet('item_cost')+'&fullPrice='+bizNumGet('full_price'), $rID);"]];
            $fldVend[] = 'show_prices_v';
        }
        if (!empty($structure['image_with_path']['attr']['value'])) {
            $cleanPath = clean($structure['image_with_path']['attr']['value'], 'path_rel');
            if (!file_exists(BIZUNO_DATA."images/$cleanPath")) { $cleanPath = 'images/'; }
            $structure['image_with_path']['attr']['value'] = $cleanPath;
        }
        $imgSrc = $structure['image_with_path']['attr']['value'];
        $imgDir = dirname($structure['image_with_path']['attr']['value']).'/';
        if ($imgDir=='/') { $imgDir = getUserCache('imgMgr', 'lastPath', false , '').'/'; } // pull last folder from cache
        // complete the structure and validate
        $structure['qty']                          = ['order'=>1,'attr'=>['type'=>'hidden','value'=>1]];
        $structure['tax_rate_id_c']['label']       = lang('sales_tax');
        $structure['tax_rate_id_v']['label']       = lang('purchase_tax');
        $structure['qty_stock']['attr']['readonly']= 'readonly';
        $structure['qty_po']['attr']['readonly']   = 'readonly';
        $structure['qty_so']['attr']['readonly']   = 'readonly';
        $structure['qty_alloc']['attr']['readonly']= 'readonly';
        $structure['inventory_type']['values']     = array_values($this->inventoryTypes);
        $structure['cost_method']['values']        = $cost_methods;
        if ($rID) {
            $locked = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='".addslashes($structure['sku']['attr']['value'])."'"); // was inventory_history but if a SO exists will not lock sku field and can change
            $title  = $structure['sku']['attr']['value'].' - '.$structure['description_short']['attr']['value'];
            $structure['where_used']= ['order'=>11,'icon'=>'tools','label'=>lang('inventory_where_used'),'hidden'=>false,'events'=>['onClick'=>"jsonAction('inventory/main/usage', $rID);"]];
            $structure['sku']['break'] = false;
            $fldProp[] = 'where_used';
            if (in_array($inv_type, ['ma','sa']) ) {
                if (isset($structure['show_prices_v'])) { $structure['show_prices_v']['break'] = false; }
                $structure['assy_cost'] = ['order'=>42,'icon'=>'payment','label'=>lang('inventory_assy_cost'),'events'=>['onClick'=>"jsonAction('inventory/main/getCostAssy', $rID);"]];
                $fldVend[] = 'assy_cost';
            }
        } else { // set some defaults
            $locked = false;
            $title  = lang('new');
            $structure['inventory_type']['attr']['value']     = 'si'; // default to stock item
            $structure['inventory_type']['events']            = ['onChange'=>"jsonAction('inventory/main/detailsType', 0, this.value);"];
            $structure['inventory_type']['events']['onChange']= "var type=bizSelGet('inventory_type'); if (invTypeMsg[type]) alert(invTypeMsg[type]);";
        }
        if ($locked) { // check to see if some fields should be locked
            $structure['sku']['attr']['readonly']           = 'readonly';
            $structure['inventory_type']['attr']['readonly']= 'readonly'; // when disabled, data is not passed in POST
            $structure['cost_method']['attr']['readonly']   = 'readonly';
        }
        if (sizeof(getModuleCache('inventory', 'prices'))) {
            $prices_c = [['id'=>0, 'text'=>lang('none')]];
            $cPrices = getMetaCommon('price_c');
            foreach ((array)$cPrices as $row) { if (!empty($row['_rID'])) { $prices_c[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; } }
            $structure['price_sheet_c']['values'] = $prices_c;
            $prices_v = [['id'=>0, 'text'=>lang('none')]];
            $vPrices = getMetaCommon('price_v');
            foreach ((array)$vPrices as $row) { if (!empty($row['_rID'])) { $prices_v[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; } }
            $structure['price_sheet_v']['values'] = $prices_v;
        } else {
            unset($structure['price_sheet_c'], $structure['price_sheet_v']);
        }
        $structure['tax_rate_id_v']['values'] = viewSalesTaxDropdown('v', 'contacts');
        $structure['tax_rate_id_c']['values'] = viewSalesTaxDropdown('c', 'contacts');
        $structure['vendor_id']['values']     = dbBuildDropdown(BIZUNO_DB_PREFIX.'contacts', 'id', 'short_name', "ctype_v='1' AND inactive<>'1' ORDER BY short_name", lang('none'));
        if ($rID && empty($this->inventoryTypes[$inv_type]['gl_inv'])) { $structure['gl_inv']['attr']['type'] = 'hidden'; }
        if ($rID && empty($this->inventoryTypes[$inv_type]['gl_cogs'])){ $structure['gl_cogs']['attr']['type']= 'hidden'; }
        if (sizeof(getModuleCache('phreebooks', 'currency', 'iso'))>1) {
            $structure['full_price']['label'].= ' ('.getDefaultCurrency().')';
            $structure['item_cost']['label'] .= ' ('.getDefaultCurrency().')';
        }
        $hideV= validateAccess('j6_mgr', 1, false) ? false : true;
        $data = ['type'=>'divHTML',
            'divs'    => [
                'toolbar'=> ['order'=> 5,'type'=>'toolbar','key' =>'tbInventory'],
                'heading'=> ['order'=>10,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'formBOF'=> ['order'=>15,'type'=>'form',   'key' =>'frmInventory'],
                'tabs'   => ['order'=>50,'type'=>'tabs',   'key' =>'tabInventory'],
                'formEOF'=> ['order'=>85,'type'=>'html',   'html'=>'</form>']],
            'toolbars'=> ['tbInventory'=>['icons'=>[
                'save' => ['order'=>20,'hidden'=>$security >1?false:true,      'events'=>['onClick'=>"jqBiz('#frmInventory').submit();"]],
                'new'  => ['order'=>40,'hidden'=>$security >1?false:true,      'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', 0);"]],
                'trash'=> ['order'=>80,'hidden'=>$rID&&$security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('inventory/main/delete', $rID);"]]]]],
            'tabs'    => ['tabInventory'=> ['divs'=>[
                'general'  => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genProp' => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'genProp'],
                    'genStat' => ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'genStat'],
                    'genImage'=> ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'genImage'],
                    'genCust' => ['order'=>40,'type'=>'panel','classes'=>['block33'],'key'=>'genCust'],
                    'genVend' => ['order'=>50,'type'=>'panel','classes'=>['block33'],'key'=>'genVend','hidden'=>$hideV],
                    'genGL'   => ['order'=>60,'type'=>'panel','classes'=>['block33'],'key'=>'genGL'],
                    'genAtch' => ['order'=>80,'type'=>'panel','classes'=>['block66'],'key'=>'genAtch']]],
                'movement' => ['order'=>30,'label'=>lang('movement'),'hidden'=>$rID?false:true,'type'=>'html','html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/history/movement&rID=$rID'"]],
                'history'  => ['order'=>35,'label'=>lang('history'), 'hidden'=>$rID?false:true,'type'=>'html','html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/history/historian&rID=$rID'"]],
                'prices_c' => ['order'=>40,'label'=>sprintf(lang('tbd_prices'), lang('ctype_c')), 'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/prices/manager&type=c&dom=div&iID=$rID'"]],
                'prices_v' => ['order'=>41,'label'=>sprintf(lang('tbd_prices'), lang('ctype_v')), 'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/prices/manager&type=v&dom=div&iID=$rID'"]],
                'invImages'=> ['order'=>55,'label'=>lang('images'),     'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/images/imagesLoad&rID=$rID'"]],
                'invAttr'  => ['order'=>65,'label'=>lang('attributes'), 'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/attributes/attrLoad&rID=$rID'"]],
                'invAccy'  => ['order'=>90,'label'=>lang('accessories'),'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/accessory/accessoryEdit&rID=$rID'"]]]]],
            'panels'  => [
                'genProp' => ['label'=>lang('properties'),                              'type'=>'fields','keys'=>$fldProp],
                'genStat' => ['label'=>lang('status'),                                  'type'=>'fields','keys'=>$fldStatus],
                'genImage'=> ['label'=>lang('current_image'),'options'=>['height'=>250],'type'=>'fields','keys'=>$fldImage],
                'genCust' => ['label'=>lang('details').' ('.lang('customers').')',      'type'=>'fields','keys'=>$fldCust],
                'genVend' => ['label'=>lang('details').' ('.lang('vendors').')',        'type'=>'fields','keys'=>$fldVend],
                'genGL'   => ['label'=>lang('details').' ('.lang('general_ledger').')', 'type'=>'fields','keys'=>$fldGL],
                'genAtch' => ['type'=>'attach','defaults'=>['path'=>getModuleCache($this->moduleID,'properties','attachPath', 'inventory'),'prefix'=>"rID_{$rID}_"]]],
            'forms'   => ['frmInventory'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=inventory/main/save"]]],
            'fields'  => $structure,
            'jsHead'  => ['invHead' => "var curIndex=undefined; var invTypeMsg=[]; curIndex=0;
function preSubmit() { bizGridSerializer('dgAssembly', 'dg_assy'); bizGridSerializer('dgVendors', 'invVendors'); return true; }"],
            'jsBody'  => ['init'=>"imgManagerInit('image_with_path', '$imgSrc', '$imgDir', ".json_encode(['style'=>"max-height:200px;max-width:100%;"]).");"],
            'jsReady' => ['init'=>"ajaxForm('frmInventory');\njqBiz('.products ul li:nth-child(3n+3)').addClass('last');"]];
        customTabs($data, 'inventory', 'tabInventory'); // add custom tabs
        if (in_array($data['fields']['inventory_type']['attr']['value'], ['ma','sa'])) { // assembly, add tab
            $data['tabs']['tabInventory']['divs']['bom'] = ['order'=>20,'label'=>lang('inventory_assy_list'),'type'=>'html','html'=>'',
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/main/managerBOM&rID=$rID'"]];
            $data['tabs']['tabInventory']['divs']['invWO'] = ['order'=>52,'label'=>lang('work_orders'),'type'=>'html','html'=>'',
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/build/manager&dom=div&refID=$rID'"]];
        }
        $layout = array_replace_recursive($layout, $data);


        // @TODO - patch for now, needs to be rolled in eventually 
        $this->addBuySell($layout);
        // Stores
        $sku   = $layout['fields']['sku']['attr']['value'];
        $output= [];
        if ($sku && sizeof(getModuleCache('bizuno', 'stores')) > 1) { $output = getStoreStock($sku); }
        if (sizeof(getModuleCache('bizuno', 'stores')) > 1) { // build the limit store, store stock display
            msgDebug("\nextStores adding table to structure");
            $order    = 10;
            $storeKeys= [];
            foreach (getModuleCache('bizuno', 'stores') as $store) {
                if (!isset($output['b'.$store['id']]['stock'])) { $output['b'.$store['id']]['stock'] = 0; }
                $layout['fields']['store_'.$store['id']] = ['order'=>$order,'label'=>$store['text'],'attr'=>['type'=>'number','value'=>$output['b'.$store['id']]['stock'],'readonly'=>true]];
                $storeKeys[] = 'store_'.$store['id'];
                $order = $order + 5;
            }
            $layout['fields']['store_id'] = ['order'=>90,'label'=>lang('limit_store'),'values'=>viewStores(),'attr'=>['type'=>'select']];
            $layout['tabs']['tabInventory']['divs']['general']['divs']['genStore'] = ['order'=>75,'type'=>'panel','classes'=>['block33'],'key'=>'genStore'];
            $layout['panels']['genStore'] = ['label'=>lang('all_stores'),'type'=>'fields','keys'=>$storeKeys];
        }
        // options
        $layout['toolbars']['tbInventory']['icons']['forecast'] = ['icon'=>'mimePpt', 'order'=>70, 'label'=>$this->lang['forecast'],
            'events'=> ['onClick' => "windowEdit('$this->moduleID/tools/invForecast&rID=$rID', 'forecastChart', '{$this->lang['forecast']}', 600, 550);"]];
        if ($layout['fields']['inventory_type']['attr']['value'] == 'ms') {
            $rID = clean('rID', 'integer', 'get');
            $tabID = clean('tabID', 'integer', 'get');
            // Set the current options (in case the tab doesn't get selected)
            $curOpt = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'invOptions', "id=$rID");
            msgDebug("\nRead curOpt = ".print_r($curOpt, true));
            if (!$curOpt) { $curOpt = json_encode([]); }
            $html = html5('invOptions', ['attr'=>  ['type'=>'hidden', 'value'=>$curOpt]]);
            $layout['tabs']['tabInventory']['divs']['tabOptions'] = ['order'=>20, 'label'=>lang('options'),'type'=>'html', 'html'=>$html,
                'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/options/optionsEdit&rID=$rID'"]];
        }
        $defaults = ['sales'=>getChartDefault(30), 'stock'=>getChartDefault(4), 'cogs'=>getChartDefault(32), 'method'=>'f'];
        $layout['fields']['lang']['ms_skus_created'] = $this->lang['skus_created'];
        $layout['fields']['inventory_type']['values']['ms'] = ['id'=>'ms','text'=>lang('inventory_type_ms'),'hidden'=>0,'tracked'=>1,'order'=>20,'gl_sales'=>$defaults['sales'],'gl_inv'=>$defaults['stock'],'gl_cogs'=>$defaults['cogs'],'method'=>$defaults['method']]; // Master Stock
        $layout['jsHead']['ms'] = "invTypeMsg['ms'] = '".addslashes($this->lang['msg_sel_ms'])."'";
        
        if ($tabID) { $layout['tabs']['tabInventory']['selected'] = $tabID; }
    }

    /**
     * Lists the details of a given inventory item from the database table
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function detailsType(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 2)) { return; }
        $type = clean('data', 'text', 'get');
        if (!$type) { msgAdd("No Type passed!"); }
        msgDebug("\n Loading defaults for type = $type");
        $settings = getModuleCache('inventory', 'phreebooks');
        $data = [
            'sales' => isset($settings['sales_'.$type]) ? $settings['sales_'.$type]  : '',
            'inv'   => isset($settings['inv_'.$type])   ? $settings['inv_'.$type]    : '',
            'cogs'  => isset($settings['cog_'.$type])   ? $settings['cog_'.$type]    : '',
            'method'=> isset($settings['method_'.$type])? $settings['method_'.$type] : 'f'];
        $html  = "jqBiz('#gl_sales').val('".$data['sales']."');";
        $html .= "jqBiz('#gl_inv').val('".$data['inv']."');";
        $html .= "jqBiz('#gl_cogs').val('".$data['cogs']."');";
        $html .= "jqBiz('#cost_method').val('".$data['method']."');";
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval', 'actionData'=>$html]]);
    }

    /**
     * Generates the structure for inventory properties popup used in PhreeBooks
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function properties(&$layout=[])
    {
        $sku = clean('sku', 'text', 'get');
        if (empty($sku)) { return msgAdd("Bad sku passed!"); }
        $qty = clean('qty', 'float','get');
        if (empty($qty)) { $qty = 1; }
        $_GET['rID'] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='$sku'");
        compose('inventory', 'main', 'edit', $layout);
        if (!empty($layout['fields']['assy_cost']['events']['onClick'])) {
            $event = str_replace('getCostAssy', "getCostAssy&qty=$qty", $layout['fields']['assy_cost']['events']['onClick']);
            $layout['fields']['assy_cost']['events']['onClick'] = $event;
        }
        unset($layout['tabs']['tabInventory']['divs']['general']['divs']['genAtch']);
        unset($layout['divs']['toolbar'], $layout['divs']['formBOF'], $layout['divs']['formEOF']);
        unset($layout['toolbars'], $layout['forms'], $layout['jsHead'], $layout['jsReady']);
    }

    /**
     * Generates the inventory item save structure for recording user updates
     * @param array $layout - structure coming in
     * @param boolean $makeTransaction - [default true] set to false if the save is already a part of another transaction
     * @return modified structure
     */
    public function save(&$layout=[], $makeTransaction=true)
    {
        global $io;
        $type   = clean('inventory_type', ['format'=>'text','default'=>'si'], 'post');
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'inventory'));
        $values['image_with_path'] = clean('image_with_path', 'path_rel', 'post');
        if (!$security = validateAccess('inv_mgr', isset($values['id']) && $values['id']?3:2)) { return; }
        $rID = isset($values['id']) && $values['id'] ? $values['id'] : 0;
        $dup = dbGetValue(BIZUNO_DB_PREFIX."inventory", 'sku', "sku='".addslashes($values['sku'])."' AND id<>$rID"); // check for duplicate sku's
        if ($dup) { return msgAdd(lang('error_duplicate_id')); }
        if (!$values['sku']) { return msgAdd($this->lang['err_inv_sku_blank']); }
        $readonlys = ['qty_stock','qty_po','qty_so','qty_alloc','creation_date','last_update','last_journal_date']; // some special processing
        foreach ($readonlys as $field) { unset($values[$field]); }
        if (!$rID) { $values['creation_date']= biz_date('Y-m-d h:i:s'); }
        else       { $values['last_update']  = biz_date('Y-m-d h:i:s'); }
        if ($makeTransaction) { dbTransactionStart(); } // START TRANSACTION (needs to be here as we need the id to create links
        $result = dbWrite(BIZUNO_DB_PREFIX."inventory", $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $_POST['id'] = $result; }
        $dgAssy = clean('dg_assy', 'json', 'post');
        if (!empty($dgAssy)) { $this->saveBOM($rID, $type, $values['sku'], $dgAssy); } // handle assemblies
        if ($makeTransaction) { dbTransactionCommit(); }
        if ($io->uploadSave('file_attach', getModuleCache('inventory', 'properties', 'attachPath', 'inventory')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['attach'=>'1'], 'update', "id=$rID");
        }
        msgAdd(lang('msg_database_write'), 'success');
        msgLog(lang('inventory').'-'.lang('save')." - ".$values['sku']." (rID=$rID)");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accInventory').accordion('select', 0); bizGridReload('dgInventory'); jqBiz('#divInventoryDetail').html('&nbsp;');"]]);
        $this->saveProStuff($layout);
    }

    private function saveProStuff(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 2)) { return; }
        $rID = clean('id', 'integer', 'post');
        if (!$rID) { return; }
        // Save inventory Images tab
        // if tab is not viewed, the images are not loaded so check to see if there is at least one image before saving
        // this means that there will always need to be at least one image to save the data
        if (isset($_POST['invImg_0'])) { // tab has been opened, process the data
            $output = [];
            $maxCnt = 100; // set max images per item to 100
            for ($i=0; $i<$maxCnt; $i++) {
                $path = clean('invImg_'.$i, 'text', 'post');
                if (!empty($path) && file_exists(BIZUNO_DATA."images/$path")) {
                    msgDebug("\nSaving path = $path");
                    $output[] = $path; }
            }
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['invImages'=>json_encode($output)], 'update', "id=$rID");
        }
        // save attributes
        $attrCat = clean('invAttrCat', 'alpha_num', 'post');
        if (!empty($attrCat)) {
            $invAttr = ['category'=>$attrCat, 'attrs'=>[]];
            foreach($_POST as $key => $value) {
                if (substr($key, 0, 7)!=='invAttr' || $key=='invAttrCat') { continue; }
                $invAttr['attrs'][$key] = $value;
            }
            ksort($invAttr['attrs']);
            msgDebug("\nReady to write attribute data = ".print_r($invAttr, true));
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['bizProAttr'=>json_encode($invAttr)], 'update', "id=$rID");
        }
        // save options
        $options = clean('invOptions', 'json', 'post');
        msgDebug("\nReached invOptions Save, rID = $rID");
        if (!empty($options)) {
            $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
            if ($inv['inventory_type'] <> 'ms') { return; }
            msgDebug("\nSave, invOptions: ".print_r($options, true));
            if (!$options || sizeof($options) < 1) { return; } // no options have been set
            msgDebug("\nWorking with options = ".print_r($options, true));
            $skuData = [[
                'ms_sku'   => $inv['sku'].'-',
                'ms_title' => $inv['description_short'].'-',
                'ms_dSale' => $inv['description_sales'],
                'ms_dPurch'=> $inv['description_purchase']]];
            for ($i = 0; $i < sizeof($options); $i++) {
                unset($options[$i]['isNewRecord']);
                $attrs = explode(';', $options[$i]['attrs']);
                $labels= explode(';', $options[$i]['labels']);
                $tmpData = $skuData;
                $skuData = [];
                for ($j = 0; $j < sizeof($tmpData); $j++) {
                    for ($k = 0; $k < sizeof($attrs); $k++) {
                        $t = (sizeof($tmpData) * $k) + $j;
                        if (!isset($skuData[$t])) { $skuData[$t] = []; }
                        $skuData[$t] = [
                            'ms_sku'   => $tmpData[$j]['ms_sku']   .$attrs[$k],
                            'ms_title' => $tmpData[$j]['ms_title'] .$attrs[$k],
                            'ms_dSale' => $tmpData[$j]['ms_dSale'] .' -'.$labels[$k],
                            'ms_dPurch'=> $tmpData[$j]['ms_dPurch'].' -'.$labels[$k]];
                    }
                }
            }
            // save production if it's an assembly 
            msgAdd("Hook for saving work order from Inventory screen, Code doesn't exist!");
            // Check length of new SKU to make sure it will fit
            $struc = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory');
            $maxSkuLen = $struc['sku']['attr']['maxlength'];
            dbTransactionStart();
            foreach ($skuData as $row) {
                if (strlen($row['ms_sku']) > $maxSkuLen) {
                    msgAdd(sprintf($this->lang['err_sku_too_long'], $row['ms_sku']));
                    dbTransactionRollback();
                    return;
                }
                $tmp = $inv; // copy the inventory record
                unset($tmp['id']);
                $tmp['sku']                 = $row['ms_sku'];
                $tmp['inventory_type']      = 'mi';
                $tmp['description_short']   = $row['ms_title'];
                $tmp['description_sales']   = $row['ms_dSale'];
                $tmp['description_purchase']= $row['ms_dPurch'];
                $tmp['last_update']         = biz_date('Y-m-d');
                unset($tmp['qty_stock'], $tmp['qty_po'], $tmp['qty_so'], $tmp['qty_alloc'], $tmp['upc_code'], $tmp['creation_date'], $tmp['last_journal_date']);
                $newID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='{$tmp['sku']}'");
                if (!$newID) { $tmp['creation_date'] = biz_date('Y-m-d'); }
                msgDebug("\nReady to write: ".print_r($tmp, true));
                dbWrite(BIZUNO_DB_PREFIX.'inventory', $tmp, $newID?'update':'insert', "id=$newID");
            }
            dbTransactionCommit();
        }
    }

    /**
     * Saves a bill of materials for inventory type AS, MA
     * @param integer $rID - inventory database record id
     * @param string $type - inventory type
     * @param type $sku - item SKU
     * @param type $dgData - JSON encoded list of inventory items that make up the BOM
     * @return boolean null, BOM is not generated in inventory type is not equal to ma or as
     */
    private function saveBOM($rID, $type, $sku, $dgData)
    {
        if (!in_array($type, ['ma', 'sa'])) { return; }
        $locked = defined('BIZ_RULE_ASSY_UNLOCK') && !empty(BIZ_RULE_ASSY_UNLOCK) ? false : dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='$sku'");
        if ($locked) { return; } // journal entry present , not ok to save
        if (empty($dgData)) { return; }
        $bom = [];
        foreach ($dgData['rows'] as $row) {
            if (empty($row['sku'])) { continue; }
            $bom[] = ['sku'=>$row['sku'], 'description'=>$row['description'], 'qty'=>$row['qty']];
        }
        $meta  = dbMetaGet(0, 'bill_of_materials', 'inventory', $rID);
        $metaID= metaIdxClean($meta);
        dbMetaSet($metaID, 'bill_of_materials', $bom, 'inventory', $rID);
    }

    /**
     * Structure for renaming inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function rename(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 4)) { return; }
        $rID    = clean('rID', 'integer','get');
        $oldSKU = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
        $newSKU = clean('data', 'text', 'get');
        // make sure new SKU is not null
        if (empty($newSKU)) { return msgAdd($this->lang['err_inv_sku_blank']); }
        // check for duplicate skus
        $found= dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='".addslashes($newSKU)."'");
        if ($found) { return msgAdd(lang('error_duplicate_id')); }
        renameMetaBOM($oldSKU, $newSKU);
        $data = ['content'=> ['action'=>'eval', 'actionData'=> "bizGridReload('dgInventory');"],
            'dbAction'    => [
                'inventory'        => "UPDATE ".BIZUNO_DB_PREFIX."inventory         SET sku='".addslashes($newSKU)."' WHERE id='$rID'",
                'inventory_history'=> "UPDATE ".BIZUNO_DB_PREFIX."inventory_history SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'",
                'journal_cogs_owed'=> "UPDATE ".BIZUNO_DB_PREFIX."journal_cogs_owed SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'",
                'journal_item'     => "UPDATE ".BIZUNO_DB_PREFIX."journal_item      SET sku='".addslashes($newSKU)."' WHERE sku='".addslashes($oldSKU)."'"]];
        msgLog(lang('inventory').' '.lang('rename')." - $oldSKU ($rID) -> $newSKU");
        $layout = array_replace_recursive($layout, $data);
        
        // Rename Pro Stuff
        $oldInv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        if ('ms' <> $oldInv['inventory_type']) { return; }
        // rename just children as master was renamed in inventory module
        if (isset($layout['dbAction'])) { // then standard rename must have been ok
            $layout['dbAction']['invOpt_inventory'] = "UPDATE ".BIZUNO_DB_PREFIX."inventory           SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_history']   = "UPDATE ".BIZUNO_DB_PREFIX."inventory_history   SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_cogs_owed'] = "UPDATE ".BIZUNO_DB_PREFIX."journal_cogs_owed   SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%'";
            $layout['dbAction']['invOpt_Jrnl_item'] = "UPDATE ".BIZUNO_DB_PREFIX."journal_item        SET sku=REPLACE(sku, '$oldSKU-', '$newSKU-') WHERE sku LIKE '$oldSKU-%' AND gl_type='itm'";
        }
    }

    /**
     * Structure for copying inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 2)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        $newSKU= clean('data','text', 'get'); // new sku
        if (!$newSKU) { return msgAdd($this->lang['err_inv_sku_blank']); }
        $sku   = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        $oldSKU= $sku['sku'];
        // check for duplicate skus
        $found = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='$newSKU'");
        if ($found) { return msgAdd(lang('error_duplicate_id')); }
        // clean up the fields (especially the system fields, retain the custom fields)
        foreach (array_keys($sku) as $key) {
            switch ($key) {
                case 'sku':          $sku[$key] = $newSKU; break; // set the new sku
                case 'creation_date':
                case 'last_update':  $sku[$key] = biz_date('Y-m-d H:i:s'); break;
                case 'id':    // Remove from write list fields
                case 'attach':
                case 'last_journal_date':
                case 'item_cost':
                case 'upc_code':
                case 'image_with_path':
                case 'qty_stock':
                case 'qty_po':
                case 'qty_so':
                case 'qty_alloc': unset($sku[$key]); break;
                default:
            }
        }
        $nID = dbWrite(BIZUNO_DB_PREFIX.'inventory', $sku);
        if ($sku['inventory_type'] == 'ma' || $sku['inventory_type'] == 'sa') { // copy assembly list if it's an assembly
            $bom = dbMetaGet(0, 'bill_of_materials', 'inventory', $rID);
            dbMetaSet(0, 'bill_of_materials', $bom, 'inventory', $nID);
        }
        $prices = dbMetaGet(0, 'price_%', 'inventory', $rID);
        foreach ($prices as $price) {
            metaIdxClean($price);
            if (empty($price['cType'])) { $price['cType'] = 'c'; }
            $price['iID']  = $nID;
            dbMetaSet(0, "price_{$price['cType']}", $price, 'inventory', $nID);
        }
        msgLog(lang('inventory').'-'.lang('copy')." - $oldSKU => $newSKU");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgInventory'); accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', $nID);"]]);
        if ('ms' <> dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "id=$rID")) { return; }
        if (!$newID = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'id', "sku='$newSKU'")) { return; } // must have been an error
        $this->save();
    }

    /**
     * Structure for deleting inventory items
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('Bad Record ID!'); }
        $action= "jqBiz('#accInventory').accordion('select', 0); bizGridReload('dgInventory'); jqBiz('#divInventoryDetail').html('&nbsp;');";
        $item  = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        if (!$item) { return ['content'=>['action'=>'eval','actionData'=>$action]]; }
        $sku   = clean($item['sku'], 'text');
        // Check to see if this item is part of an assembly
        $block0= dbGetMulti(BIZUNO_DB_PREFIX.'inventory_meta', "meta_key='bill_of_materials'");
        $cnt   = 0;
        foreach ($block0 as $row) {
            $bom = json_decode($row['meta_value'], true);
            if (empty($bom)) { continue; }
            foreach ($bom as $value) {
                if (!is_array($value)) { continue; }
                if ($value['sku']==$sku) { $cnt++; }
            }
        }

        if (!empty($cnt)) { return msgAdd(sprintf($this->lang['err_inv_delete_assy'], $sku)); }
        $block1= dbGetValue(BIZUNO_DB_PREFIX.'journal_item', 'id', "sku='".addslashes($sku)."'");
        if ($sku && $block1 && in_array($item['inventory_type'], INVENTORY_COGS_TYPES)) { return msgAdd(sprintf($this->lang['err_inv_delete_gl_entry'], $sku)); }
        $data  = ['content' => ['action'=>'eval','actionData'=>$action],
            'dbAction'=> [
                'inventory'     => "DELETE FROM ".BIZUNO_DB_PREFIX."inventory WHERE id=$rID",
                'inventory_meta'=> "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id=$rID"]];
        $files = glob(getModuleCache('inventory', 'properties', 'attachPath', 'inventory')."rID_{$rID}_*.*");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } } // remove attachments
        msgLog(lang('inventory').' '.lang('delete')." - $sku ($rID)");
        $layout = array_replace_recursive($layout, $data);
        
// @TODO - This needs to be merged in with above
        // Add Pro Stuff
        if ('ms' <> $item['inventory_type']) { return; }
        $cancelDelete = false;
        $rID = clean('rID', 'integer', 'get');
        $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID");
        $sku = $inv['sku'];
        if ('ms' <> $inv['inventory_type']) { return; }
        if (!isset($layout['dbAction']['inventory'])) { return; } // the item is not being deleted, probably an error
        // make sure SKU is not part of an assembly
        // @TODO - if it is and the other SKU's are not in journal then let delete AND remove sku from all assembly BOMs

        if (dbGetValue(BIZUNO_DB_PREFIX."journal_item", 'id', "sku LIKE '$sku-%'"))        { $cancelDelete = msgAdd($this->lang['err_inv_delete_gl_entry']); }
        if ($cancelDelete) { unset($layout['dbAction']); }
        else { // get all ID's for the children
            if (!$mID = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "sku LIKE '$sku-%'", '', ['id'])) { return; }
            $range = [];
            foreach ($mID as $row) { $range[] = $row['id']; }
            $range = '('.implode(',', $range).')';
            $layout['dbAction']['invOpt_inventory'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory      WHERE id IN $range";
            $layout['dbAction']['invOpt_assy_list'] = "DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id IN $range";
            foreach ($mID as $row) { // remove attachments
                $files = glob(getModuleCache('inventory', 'properties', 'attachPath', 'inventory')."rID_{$row['id']}_*.*");
                if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
            }
        }
    }

    /**
     * Calculates the cost of building an assembly
     * @return entry is made in the message queue with current assembly cost
     */
    public function getCostAssy(&$layout=[])
    {
        global $currencies;
        if (!$rID) { $rID = clean('rID', 'integer', 'get'); }
        $cost = dbGetInvAssyCost($rID);
        $currencies = (object)['iso'=>getDefaultCurrency(), 'rate'=>1];
        msgAdd(sprintf($this->lang['msg_inventory_assy_cost'], viewFormat($cost, 'currency')), 'caution');
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $rID   = clean('rID', 'integer','get');
        if (empty($rID)) { return; }
        $qty   = clean('qty', 'float',  'get');
        $boms  = getMetaInventory($rID, 'bill_of_materials');
        if (empty($boms)) { return; } // for non-assemblies, and assemblies with no BOM
        $stores= getModuleCache('bizuno', 'stores');
        $output= $mins = [];
        foreach ($boms as $bom) { $output[] = ['sku'=>$bom['sku'], 'qty'=>$bom['qty'], 'stock'=>getStoreStock($bom['sku'])]; }
        $html  = '<table><tr><th style="border:1px solid black;">SKU</th><th style="border:1px solid black;">Qty/Assy</th>';
        foreach ($stores as $store) { $html .= '<th style="border:1px solid black;">'.$store['text'].'</th>'; }
        $html .= '</tr>';
        foreach ($output as $row) {
            $invType = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'inventory_type', "sku='".addslashes($row['sku'])."'");
            $html .= '<tr><td style="border:1px solid black;">'.$row['sku'].'</td><td style="border:1px solid black;text-align: center;">'.$row['qty'].'</td>';
            foreach ($stores as $store) {
                $stock = !empty($row['stock']['b'.$store['id']]['stock']) ? $row['stock']['b'.$store['id']]['stock'] : 0;
                $html .= '<td style="border:1px solid black;text-align: center;">'.$stock.'</td>';
                if (!isset($mins['b'.$store['id']])) { $mins['b'.$store['id']] = $stock; }
                if (in_array($invType, INVENTORY_COGS_TYPES)) { $mins['b'.$store['id']] = intval(min($mins['b'.$store['id']], $stock/$row['qty'])); }
            }
            $html .= '</tr>';
        }
        $html .= '<tr><td>&nbsp;</td><td>&nbsp;</td>';
        foreach ($mins as $min) { $html .= '<td style="border:1px solid black;text-align: center;">'.$min.'</td>'; }
        $html .= '</tr></table>';
        $data  = ['type'=>'popup', 'attr'=>['id'=>'invAssyQty'], 'title'=> "Store assembly capability to build $qty",
            'divs' => ['body'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Inventory grid structure
     * @param string $name - DOM field name
     * @param string $filter - control to limit filtering by inventory type
     * @param integer $security - users security level
     * @return string - grid structure
     */
    private function dgInventory($name, $filter='none', $security=0)
    {
        $this->managerSettings();
        $yes_no_choices = [['id'=>'a','text'=>lang('all')],['id'=>'y','text'=>lang('active')],['id'=>'n','text'=>lang('inactive')]];
        switch ($this->defaults['f0']) { // clean up the filter
            default:
            case 'a': $f0_value = ""; break;
            case 'y': $f0_value = "inactive='0'"; break;
            case 'n': $f0_value = "inactive='1'"; break;
        }
        $data = ['id'=> $name, 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'     => ['idField'=>'id', 'toolbar'=>"#{$name}Toolbar", 'url'=>BIZUNO_AJAX."&bizRt=inventory/main/managerRows"],
            'events'   => [
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".jsLang('details')."', 'inventory/main/edit', rowData.id); }",
                'rowStyler'    => "function(index, row) { if (row.inactive==1) { return {class:'row-inactive'}; }}"],
            'footnotes'=> ['codes'=>jsLang('color_codes').': <span class="row-inactive">'.jsLang('inactive').'</span>'],
            'source'   => [
                'tables' => ['inventory' => ['table'=>BIZUNO_DB_PREFIX."inventory"]],
                'search' => [BIZUNO_DB_PREFIX.'inventory.id',BIZUNO_DB_PREFIX.'inventory.sku','description_short','description_purchase','description_sales','upc_code'],
                'actions' => [
                    'newInventory'=>['order'=>10,'icon'=>'add',    'hidden'=>$security>1?false:true,'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', 0);"]],
                    'mergeInv'    =>['order'=>30,'icon'=>'merge',  'hidden'=>$security>4?false:true,'events'=>['onClick'=>"jsonAction('$this->moduleID/tools/merge', 0);"]],
                    'woDesign'    =>['order'=>50,'icon'=>'design', 'hidden'=>$security>3?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=$this->moduleID/design/manager');"]],
                    'woTasks'     =>['order'=>55,'icon'=>'inv-adj','hidden'=>$security>3?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=$this->moduleID/tasks/manager');"]],
                    'clrSearch'   =>['order'=>85,'icon'=>'clear',  'events'=>['onClick'=>"bizSelSet('f0', 'y'); bizTextSet('search', ''); ".$name."Reload();"]]],
                'filters'=> [
                    'f0'     => ['order'=>10,'label'=>lang('status'),'break'=>true,'sql'=>$f0_value,'values'=> $yes_no_choices,'attr'=>['type'=>'select','value'=>$this->defaults['f0']]],
                    'search' => ['order'=>90,'attr'=>['value'=>$this->defaults['search']]]],
                'sort' => ['s0'=>  ['order'=>10, 'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns'  => [
                'id'            => ['order'=> 0,'field'=>BIZUNO_DB_PREFIX.'inventory.id',      'attr'=>['hidden'=>true]],
                'inactive'      => ['order'=> 0,'field'=>BIZUNO_DB_PREFIX.'inventory.inactive','attr'=>['hidden'=>true]],
                'attach'        => ['order'=> 0,'field'=>'attach',            'attr'=>['hidden'=>true]],
                'inventory_type'=> ['order'=> 0,'field'=>'inventory_type',    'attr'=>['hidden'=>true]],
                'action' => ['order'=>1, 'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'prices'=> ['order'=>20,'icon'=>'price',  'events'=>['onClick'=>"jsonAction('inventory/prices/details&type=c', idTBD);"]],
                        'edit'  => ['order'=>30,'icon'=>'edit',   'events'=>['onClick'=>"accordionEdit('accInventory', 'dgInventory', 'divInventoryDetail', '".lang('details')."', 'inventory/main/edit', idTBD);"]],
                        'rename'=> ['order'=>40,'icon'=>'rename', 'hidden'=>$security>2?false:true,'events'=>['onClick'=>"var title=prompt('".$this->lang['msg_sku_entry_rename']."'); if (title!=null) jsonAction('inventory/main/rename', idTBD, title);"]],
                        'copy'  => ['order'=>50,'icon'=>'copy',   'hidden'=>$security>2?false:true,'events'=>['onClick'=>"var title=prompt('".$this->lang['msg_sku_entry_copy']."'); if (title!=null) jsonAction('inventory/main/copy', idTBD, title);"]],
                        'chart' => ['order'=>60,'icon'=>'mimePpt','label'=>lang('sales'),'events'=>['onClick'=>"windowEdit('inventory/tools/chartSales&rID=idTBD', 'myInvChart', '&nbsp;', 600, 500);"]],
                        'trash' => ['order'=>90,'icon'=>'trash',  'hidden'=>$security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('inventory/main/delete', idTBD);"]],
                        'attach'=> ['order'=>95,'icon'=>'attachment','display'=>"row.attach=='1'"]]],
                'sku'              => ['order'=>10,'field'=>BIZUNO_DB_PREFIX.'inventory.sku','label'=>pullTableLabel("inventory", 'sku'), 'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true]],
                'description_short'=> ['order'=>20,'field'=>'description_short','label'=>pullTableLabel("inventory", 'description_short'),'attr'=>['width'=>500,'sortable'=>true,'resizable'=>true]],
                'qty_stock'        => ['order'=>30,'field'=>'qty_stock','format'=>'number','label'=>pullTableLabel("inventory", 'qty_stock'),'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right'],'format'=>'buySell'],
                'qty_po'           => ['order'=>40,'field'=>'qty_po',   'format'=>'number','label'=>pullTableLabel("inventory", 'qty_po'),   'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right'],'format'=>'buySell'],
                'qty_so'           => ['order'=>50,'field'=>'qty_so',   'format'=>'number','label'=>pullTableLabel("inventory", 'qty_so'),   'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']],
                'qty_alloc'        => ['order'=>60,'field'=>'qty_alloc','format'=>'number','label'=>pullTableLabel("inventory", 'qty_alloc'),'attr'=>['width'=>150,'sortable'=>true,'resizable'=>true,'align'=>'right']]]];
        switch ($filter) {
            case 'stock': $data['source']['filters']['restrict'] = ['order'=>99, 'sql'=>"inventory_type in ('si','sr','ms','mi','ma')"]; break;
            case 'assy':  $data['source']['filters']['restrict'] = ['order'=>99, 'sql'=>"inventory_type in ('ma')"]; break;
            default:
        }
        // Stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return $data; }
        $this->lang = array_merge(getLang('inventory'), $this->lang);
        $f1  = clean('f1', 'integer', 'request');
        setUserCache('inventory', 'f1', $f1);
        $bID = clean('bID', ['format'=>'integer', 'default'=>-1], 'get'); // restrict all information to a specific store
        $this->myStore = $bID > -1 ? $bID : getUserCache('profile', 'store_id'); // set the store overrides, if any
        $GLOBALS['bizuno_store_id'] = $this->myStore;
        $values  = [['id'=>'0','text'=>lang('all')],['id'=>'1','text'=>lang('stock_all')],['id'=>'2','text'=>lang('active')],['id'=>'3','text'=>lang('store_stock')]];
        // get the datagrid structure, different place for manager than managerRows
        $data['columns']['qty_all'] = $data['columns']['qty_stock'];
        $data['columns']['qty_all']['alias'] = 'qty_stock';
        if (strpos($data['source']['sort']['s0']['field'], 'qty_all') !== false) { // fix the sort criteris
            $data['source']['sort']['s0']['field'] = str_replace('qty_all', 'qty_stock', $data['source']['sort']['s0']['field']);
        }
        $data['columns']['qty_stock'] = ['order'=>25, 'field'=>'inventory.sku', 'alias'=>'sku','label'=>pullTableLabel('inventory', 'qty_store'), 'process'=>'storeStock',
            'attr'=>['sortable'=>false,'resizable'=>true,'align'=>'right']];
        $data['source']['filters']['f1'] = ['order'=>50,'sql'=>'','label'=>$this->lang['store_stock'],'values'=>$values,'attr'=>['type'=>'select','value'=>$f1]];
        msgDebug("\nextStores set home store: $this->myStore with f1 (store filter selection) = $f1");
        switch ($f1) { // clean up the filter
            default:
            case '0': break; // all
            case '1': $data['source']['filters']['f1']['sql'] = "qty_stock>0"; break; // In Stock > 0
            case '2': // Active inventory
                $data['source']['filters']['f1']['sql'] = "qty_stock>0 OR qty_so>0 OR qty_po>0 OR qty_alloc>0";
                break;
            case '3': // Branch Stock > 0
                unset ($data['columns']['qty_stock']['alias']);
                unset ($data['columns']['qty_stock']['format']);
                $data['columns']['qty_stock']['field']  = 'SUM(remaining)';
                $data['source']['tables']['inv_history']= ['table'=>BIZUNO_DB_PREFIX.'inventory_history','join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."inventory.sku=".BIZUNO_DB_PREFIX."inventory_history.sku"];
                $data['source']['filters']['f1']['sql'] = BIZUNO_DB_PREFIX."inventory.qty_stock>=1 AND ".BIZUNO_DB_PREFIX."inventory_history.remaining>0 AND ".BIZUNO_DB_PREFIX."inventory_history.store_id=$this->myStore GROUP BY ".BIZUNO_DB_PREFIX."inventory.sku";
                break;
        }
        
/* Stores from managerRows, probably mostly duplicated from above
        // stores
        if (sizeof(getModuleCache('bizuno', 'stores')) == 1) { return; }
        $this->lang = array_merge(getLang('inventory'), $this->lang);
        $f1  = clean('f1', 'integer', 'request');
        setUserCache('inventory', 'f1', $f1);
        $bID = clean('bID', ['format'=>'integer', 'default'=>-1], 'get'); // restrict all information to a specific store
        if ($bID > -1) { $this->myStore = $bID; } // set the store overrides, if any
        $GLOBALS['bizuno_store_id'] = $this->myStore;
        $values  = [['id'=>'0','text'=>lang('all')],['id'=>'1','text'=>$this->lang['stock_all']],['id'=>'2','text'=>lang('active')],['id'=>'3','text'=>$this->lang['store_stock']]];
        // get the datagrid structure, different place for manager than managerRows
        $data['columns']['qty_all'] = $data['columns']['qty_stock'];
        $data['columns']['qty_all']['alias'] = 'qty_stock';
        if (strpos($data['source']['sort']['s0']['field'], 'qty_all') !== false) { // fix the sort criteris
            $data['source']['sort']['s0']['field'] = str_replace('qty_all', 'qty_stock', $data['source']['sort']['s0']['field']);
        }
        $data['columns']['qty_stock'] = ['order'=>25, 'field'=>'inventory.sku', 'alias'=>'sku','label'=>pullTableLabel('inventory', 'qty_store'), 'process'=>'storeStock',
            'attr'=>['sortable'=>false,'resizable'=>true,'align'=>'right']];
        $data['source']['filters']['f1'] = ['order'=>50,'sql'=>'','label'=>$this->lang['store_stock'],'values'=>$values,'attr'=>['type'=>'select','value'=>$f1]];
        msgDebug("\nextStores set home store: $this->myStore with f1 (store filter selection) = $f1");
        switch ($f1) { // clean up the filter
            default:
            case '0': break; // all
            case '1': $data['source']['filters']['f1']['sql'] = "qty_stock>0"; break; // In Stock > 0
            case '2': // Active inventory
                $data['source']['filters']['f1']['sql'] = "qty_stock>0 OR qty_so>0 OR qty_po>0 OR qty_alloc>0";
                break;
            case '3': // Branch Stock > 0
                unset ($data['columns']['qty_stock']['alias']);
                unset ($data['columns']['qty_stock']['format']);
                $data['columns']['qty_stock']['field']  = 'SUM(remaining)';
                $data['source']['tables']['inv_history']= ['table'=>BIZUNO_DB_PREFIX."inventory_history",'join'=>'JOIN','links'=>BIZUNO_DB_PREFIX."inventory.sku=".BIZUNO_DB_PREFIX."inventory_history.sku"];
                $data['source']['filters']['f1']['sql'] = BIZUNO_DB_PREFIX."inventory.qty_stock>=1 AND ".BIZUNO_DB_PREFIX."inventory_history.remaining>0 AND ".BIZUNO_DB_PREFIX."inventory_history.store_id=$this->myStore GROUP BY ".BIZUNO_DB_PREFIX."inventory.sku";
                break;
        }
        msgDebug("\nExiting stores with columns = ".print_r($data['columns'], true));
*/
        return $data;
    }

    /**
     * Grid structure for assembly material lists
     * @param string $name - DOM field name
     * @param boolean $locked - [default true] leave unlocked if no journal activity has been entered for this sku
     * @return string - grid structure
     */
    private function dgAssembly($name, $locked=true)
    {
        $data = ['id'  => $name,
            'type'=> 'edatagrid',
            'attr'=> ['pagination'=>false, 'rownumbers'=>true, 'singleSelect'=>true, 'showFooter'=>true, 'toolbar'=>"#{$name}Toolbar", 'idField'=>'id'],
            'events'  => ['data'=>'assyData',
                'onClickRow' => "function(rowIndex, row) { curIndex = rowIndex; }",
                'onBeginEdit'=> "function(rowIndex, row) { curIndex = rowIndex; }",
                'onDestroy'  => "function(rowIndex, row) { curIndex = undefined; }",
                'onAdd'      => "function(rowIndex, row) { curIndex = rowIndex; }"],
            'source'  => ['actions'=>['newAssyItem'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns' => ['id'=>['order'=>0,'attr'=>['hidden'=>true]],
                'action'     => ['order'=>1,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'settings'=> ['order'=>10,'icon'=>'settings','events'=>['onClick'=>"inventoryProperties(bizDGgetRow('$name'));"]],
                        'trash'   => ['order'=>80,'icon'=>'trash',   'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'sku'=> ['order'=>30,'label'=>lang('sku'),'attr'=>['sortable'=>true,'resizable'=>true,'align'=>'center'],
                    'events' => ['editor'=>"{type:'combogrid',options:{ url:'".BIZUNO_AJAX."&bizRt=inventory/main/managerRows&clr=1',
                        width:150, panelWidth:320, delay:500, idField:'sku', textField:'sku', mode:'remote',
                        onClickRow: function (idx, cgData) {
                            bizNumEdSet('$name', curIndex, 'qty', 1);
                            bizTextEdSet('$name', curIndex, 'description', cgData.description_short); },
                        columns:[[{field:'sku',              title:'".lang('sku')."',        width:100},
                                  {field:'description_short',title:'".lang('description')."',width:200}]]
                    }}"]],
                'description'=> ['order'=>40,'label'=>lang('description'),'attr'=>['editor'=>'text','sortable'=>true,'resizable'=>true]],
                'qty'        => ['order'=>50,'label'=>lang('qty_needed'), 'attr'=>['value'=>1,'resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }",'editor'=>"{type:'numberbox'}"]],
                'item_cost'  => ['order'=>60,'label'=>lang('cost'), 'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatCurrency(value); }"]],
                'qty_stock'  => ['order'=>80,'label'=>pullTableLabel("inventory", 'qty_stock'),'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }"]],
                'qty_alloc'  => ['order'=>90,'label'=>pullTableLabel("inventory", 'qty_alloc'),'attr'=>['resizable'=>true,'align'=>'right'],
                    'events' => ['formatter'=>"function(value) { return formatNumber(value); }"]]]];
        if ($locked) {
            unset($data['columns']['action']['actions']['trash'], $data['columns']['sku']['events']['editor'], $data['columns']['description']['attr']['editor']);
            unset($data['columns']['qty']['events']['editor'],    $data['source']);
        }
        return $data;
    }

    /**
     * Grid structure for PO/SO history with options
     * @param type $jID
     * @return type
     */
    private function dgJ04J10($jID=10, $sku='')
    {
        $hide_cost= validateAccess("j6_mgr", 1, false) ? false : true;
        $stores   = sizeof(getModuleCache('bizuno', 'stores')>1) ? false : true;
        if ($jID==4) {
            $props = ['name'=>'dgJ04','title'=>lang('open_journal_4'), 'data'=>'dataPO'];
            $label = lang('fill_purchase');
            $invID = 6;
            $icon  = 'sales';
            $hide  = validateAccess("j4_mgr", 1, false);
        } else { // jID=10
            $props = ['name'=>'dgJ10','title'=>lang('open_journal_10'),'data'=>'dataSO'];
            $label = lang('fill_sale');
            $invID = 12;
            $icon  = 'purchase';
            $hide  = validateAccess("j10_mgr", 1, false);
        }
        return ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false, 'idField'=>'id'],
            'events' => ['url'=>"'".BIZUNO_AJAX."&bizRt=inventory/history/historyRows&jID=$jID&sku=$sku'"],
            'columns'=> ['id'=> ['attr'=>['hidden'=>true]],
                'action'     => ['order'=>1,'label'=>lang('action'),'attr'=>['width'=>60,'hidden'=>$hide_cost?true:false],
                    'events' => ['formatter'=>"function(value,row,index) { return {$props['name']}Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'  => ['order'=>20,'icon'=>'edit',  'label'=>lang('edit'),          'hidden'=>$hide>0?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD');"]],
                        'toggle'=> ['order'=>40,'icon'=>'toggle','label'=>lang('toggle_status'), 'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"jsonAction('phreebooks/main/toggleWaiting&jID=$jID&dgID={$props['name']}', idTBD);"]],
                        'dates' => ['order'=>50,'icon'=>'date',  'label'=>lang('delivery_dates'),'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"windowEdit('phreebooks/main/deliveryDates&rID=idTBD', 'winDelDates', '".lang('delivery_dates')."', 500, 400);"]],
                        'fill'  => ['order'=>80,'icon'=>$icon,   'label'=>$label,                'hidden'=>$hide>2?false:true,'events'=>['onClick'=>"winHref(bizunoHome+'&bizRt=phreebooks/main/manager&rID=idTBD&jID=$invID&bizAction=inv');"]]]],
                'invoice_num'=> ['order'=>20,'label'=>lang('invoice_num', $jID),    'attr'=>['width'=>100,'resizable'=>true]],
                'store_id'   => ['order'=>30,'label'=>lang('contacts_short_name_b'),'attr'=>['width'=>100,'resizable'=>true,'hidden'=>$stores]],
                'rep_id'     => ['order'=>30,'label'=>lang('contacts_rep_id_c'),    'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']],
                'post_date'  => ['order'=>40,'label'=>lang('post_date'),            'attr'=>['width'=>150,'resizable'=>true,'sortable'=>true,'align'=>'center']],
                'qty'        => ['order'=>50,'label'=>lang('balance'),              'attr'=>['width'=>100,'resizable'=>true,'align'=>'center']],
                'date_1'     => ['order'=>60,'label'=>jsLang('terminal_date',10),   'attr'=>['width'=>150,'resizable'=>true,'sortable'=>true,'align'=>'center'],
                    'events'=>['styler'=>"function(value,row,index) { if (row.waiting==1) { return {style:'background-color:yellowgreen'}; } }"]]]];
    }

    private function dgJ06J12($jID=12)
    {
        if ($jID==6) {
            $props = ['name'=>'dgJ06','title'=>sprintf(lang('tbd_history'), lang('journal_id', '6')), 'data'=>'dataJ6'];
            $label = jsLang('cost');
        } else {
            $props = ['name'=>'dgJ12','title'=>sprintf(lang('tbd_history'), lang('journal_id', '12')),'data'=>'dataJ12'];
            $label = jsLang('sales');
        }
        return ['id' => $props['name'],
            'attr'   => ['title'=>$props['title'], 'pagination'=>false, 'width'=>350],
            'events' => ['data' =>$props['data']],
            'columns'=> [
                'year' => ['order'=>20,'label'=>lang('year'), 'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'month'=> ['order'=>30,'label'=>lang('month'),'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'qty'  => ['order'=>40,'label'=>lang('qty'),  'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'total'=> ['order'=>50,'label'=>$label,       'attr'=>['width'=>200,'align'=>'right','resizable'=>true],'events'=>['formatter'=>"function(value) { return formatCurrency(value); }"]]]];
    }

    /**
     * Generates the Where Used? pop up window displaying where a sku is used in other sku's
     * @return usage statistics added to message queue
     */
    public function usage()
    {
        if (!$security = validateAccess('inv_mgr', 1)) { return; }
        $refID= clean('rID', 'integer', 'get');
        $sku  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$refID");
        if (empty($sku)) { return msgAdd('Cannot find sku!'); }
        $rows = dbMetaGet(0, 'bill_of_materials', 'inventory', '%');
        metaIdxClean($rows);
        msgDebug("\nRead total BOM rows = ".sizeof($rows));
        $hits = [];
        foreach ($rows as $row) {
            if (empty($row)) { continue; }
            foreach ($row as $value) {
                if (empty($value['sku'])) { continue; }
                if ($value['sku']==$sku) { $hits[] = ['refID'=>$row['_refID'], 'qty'=>$value['qty']]; }
            }
        }
        msgDebug("\nFound hits = ".print_r($hits, true));
        if (empty($hits)) { return msgAdd('Cannot find any usage!'); }
        $output = [];
        foreach ($hits as $row) {
            $inv = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['sku', 'description_short'], "id={$row['refID']}");
            if (!$inv) { $this->cleanOrphan($row['refID']); }
            else       { $output[] = ['qty'=>$row['qty'], 'sku'=>$inv['sku'], 'desc'=>$inv['description_short']]; }
        }
        $output1 = sortOrder($output, 'sku');
        msgAdd("This SKU is used in the following assemblies:", 'info');
        foreach ($output1 as $row) { msgAdd("Qty: {$row['qty']} SKU: {$row['sku']} - {$row['desc']}", 'info'); }
    }

    /**
     * Generates a list of stock available to build a given number of assemblies to determine if enough product is on hand
     * @return status message is added to user message queue
     */
    public function getStockAssy()
    {
        $sID = clean('rID', 'integer', 'get');
        $qty = clean('qty', ['format'=>'float','default'=>1], 'get');
        if (!$sID) { return msgAdd("Bad record ID!"); }
        $bom = dbMetaGet(0, 'bill_of_materials', 'inventory', $sID);
        metaIdxClean($bom);
        if (sizeof($bom) == 0) { return msgAdd($this->lang['err_inv_assy_error']); }
        $shortages = [sprintf($this->lang['err_inv_assy_low_stock'], $qty)];
        foreach ($bom as $row) {
            $stock = dbGetValue(BIZUNO_DB_PREFIX.'inventory', "qty_stock", "sku='{$row['sku']}'");
            if ($row['qty']*$qty > $stock) { $shortages[] = sprintf($this->lang['err_inv_assy_low_list'], $row['sku'], $row['description'], $stock, $row['qty']*$qty); }
        }
        if (sizeof($shortages) > 1) { msgAdd(implode("<br />", $shortages), 'caution'); }
        else { msgAdd($this->lang['msg_inv_assy_stock_good'], 'success'); }
    }

    /**
     * Cleans up the linked inventory database tables if the inventory record is not present
     * @param integer $rID - record ID of the missing inventory item
     * @return null
     */
    private function cleanOrphan($rID=0) {
        if (empty($rID)) { return; }
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."inventory_meta WHERE ref_id='$rID'");
    }
    /**
     * Extends inventory/main/edit
     * @param array $layout - structure coming in
     * @return modified $layout
     */

    private function addBuySell(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        if (empty($rID)) { return; }
        $tabID= clean('tabID', 'integer', 'get');
        $values  = $rID ? dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['buy_uom','sell_uom','sell_qty'], "id=$rID") : ['buy_uom'=>'ea','sell_uom'=>'ea','sell_qty'=>1];
        msgDebug("\nvalues from inventory table = ".print_r($values, true));
        $values['buy_uom'] = str_replace("'", "", $values['buy_uom']); // fixes bug in storing string
        $values['sell_uom']= str_replace("'", "", $values['sell_uom']); // fixes bug in storing string
        $buyDesc = " (".$this->lang['uom_'.$values['buy_uom']].")";
        $sellDesc= " (".$this->lang['uom_'.$values['sell_uom']].")";
        msgDebug("\nbuyDesc = $buyDesc and sellDesc = $sellDesc");
        // convert qty_stock, qty_po and add (UOM) suffix to field value
        $layout['fields']['buy_uom']         = ['order'=>91,'break'=>false,'label'=>$this->lang['buy_uom'], 'lblStyle'=>['min-width'=>'60px'],'options'=>['width'=>100],'values'=>viewKeyDropdown($this->uom),'attr'=>['type'=>'select','value'=>$values['buy_uom']]];
        $layout['fields']['sell_qty']        = ['order'=>92,'break'=>false,'label'=>$this->lang['sell_uom'],'lblStyle'=>['min-width'=>'60px'],'options'=>['width'=>50],'attr'=>['value'=>$values['sell_qty']]];
        $layout['fields']['sell_uom']        = ['order'=>93,'values'=>viewKeyDropdown($this->uom),'options'=>['width'=>100],'attr'=>['type'=>'select','value'=>$values['sell_uom']]];
        $layout['fields']['qty_stock_desc']  = ['order'=>11,'html'=>$sellDesc,'attr'=>['type'=>'raw']];
        $layout['fields']['qty_min_desc']    = ['order'=>21,'html'=>$sellDesc,'attr'=>['type'=>'raw']];
        $layout['fields']['qty_restock_desc']= ['order'=>31,'html'=>$buyDesc, 'attr'=>['type'=>'raw']];
        $layout['fields']['qty_po_desc']     = ['order'=>41,'html'=>$buyDesc, 'attr'=>['type'=>'raw']];
        $layout['fields']['qty_so_desc']     = ['order'=>51,'html'=>$sellDesc,'attr'=>['type'=>'raw']];
        $layout['fields']['qty_alloc_desc']  = ['order'=>61,'html'=>$sellDesc,'attr'=>['type'=>'raw']];
        $layout['fields']['qty_stock']['break']  = false;
        $layout['fields']['qty_min']['break']    = false;
        $layout['fields']['qty_restock']['break']= false;
        $layout['fields']['qty_po']['break']     = false;
        $layout['fields']['qty_so']['break']     = false;
        $layout['fields']['qty_alloc']['break']  = false;
//      $layout['fields']['qty_restock']['attr']['value']= clean($layout['fields']['qty_restock']['attr']['value'], 'float')/ $values['sell_qty'];
        $layout['fields']['qty_po']['attr']['value'] = !empty($values['sell_qty']) ? clean($layout['fields']['qty_po']['attr']['value'], 'float')     / $values['sell_qty'] : 0;
        $layout['panels']['genProp']['keys'] = array_merge($layout['panels']['genProp']['keys'], ['buy_uom','sell_qty','sell_uom']);
        $layout['panels']['genStat']['keys'] = array_merge($layout['panels']['genStat']['keys'], ['qty_min_desc','qty_restock_desc','qty_stock_desc','qty_po_desc','qty_so_desc','qty_alloc_desc']);
        $layout['tabs']['tabInventory']['divs']['vendors'] = ['order'=>55,'label'=>lang('vendors'),'type'=>'html','html'=>'',
            'options'=>['href'=>"'".BIZUNO_AJAX."&bizRt=$this->moduleID/vendors/vendorsLoad&rID=$rID'"]];
    }
}
