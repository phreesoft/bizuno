<?php
/*
 * @name Bizuno ERP - Bizuno Pro Logistics plugin
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/shipping/admin.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'shippingCommon');

class shippingAdmin extends shippingCommon
{
    public  $moduleID  = 'shipping';
    public  $methodDir = 'carriers';
    public  $syncMonths= 3;
    private $defMethods= ['best_way', 'flat', 'freeshipper', 'item', 'percent', 'thirdparty'];
    public $structure;
    public $phreeformProcessing;
    public $phreeformFormatting;

    function __construct()
    {
        parent::__construct();
        $this->structure= [
            'dirMethods'=> ['carriers'],
            'menuBar'   => ['child'=>[
                'tools'    => ['child'=>[
                    'shipping'=>['order'=>10,'label'=>sprintf(lang('tbd_manager'),lang('shipping')),'icon'=>'shipping','route'=>"$this->moduleID/manager/manager"]]],
                'inventory'=> ['child'=>[
                    'receiving'=>['order'=>80,'label'=>lang('title_receiving', $this->moduleID),'icon'=>'invrec','route'=>"$this->moduleID/invReceiving/receivingMain');"]]]]],
            'hooks'     => [
                'contacts'=> [
                    'main' =>[
                        'edit'       => ['order'=>60,'method'=>'fedExEdit'],
                        'save'       => ['order'=>60,'method'=>'fedExSave'],
                        'delete'     => ['order'=>60,'method'=>'fedExDelete']]],
                'inventory'=> [
                    'api'  => [
                        'apiImport'  => ['order'=>50]],
                    'main' =>[
                        'edit'       => ['order'=>90,'method'=>'invEdit'],
                        'save'       => ['order'=>90,'page'=>'manager','method'=>'shpmtDetailsSave']]],
                'phreebooks'=>[
                    'main' =>[
                        'manager'    => ['order'=>27],
                        'managerRows'=> ['order'=>25,'method'=>'manager'],
                        'edit'       => ['order'=>90]]]]];
        $this->phreeformProcessing= [
            'shipReq'  =>['text'=>lang('order_details'),  'group'=>lang('title', $this->moduleID),'module'=>$this->moduleID,'function'=>'shippingView'],
            'shipRecon'=>['text'=>lang('reconciled'),     'group'=>lang('title', $this->moduleID),'module'=>$this->moduleID,'function'=>'shippingView'],
            'shipTrack'=>['text'=>lang('tracking_id'),    'group'=>lang('title', $this->moduleID),'module'=>$this->moduleID,'function'=>'shippingView']];
        $this->phreeformFormatting = [
            'shipInfo' =>['text'=>lang('delivery_method'),'group'=>lang('title', $this->moduleID),'module'=>$this->moduleID,'function'=>'shippingView']];
    }

    /**
     *
     * @return array
     */
    public function settingsStructure()
    {
        $wghts = [['id'=>'LB', 'text'=>lang('pounds')], ['id'=>'KG', 'text'=>lang('kgs')]];
        $dims  = [['id'=>'IN', 'text'=>lang('inches')], ['id'=>'CM', 'text'=>lang('centimeters')]];
        return ['general' => ['order'=>10,'label'=>lang('general'),'fields'=>[
            'bill_hq'        => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['general']['bill_hq']]],
            'block_trash'    => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['block_trash']]],
            'skip_guess'     => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['skip_guess']]],
            'gl_shipping_c'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_shipping_c','value'=>$this->settings['general']['gl_shipping_c']]],
            'gl_shipping_v'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_shipping_v','value'=>$this->settings['general']['gl_shipping_v']]],
            'weight_uom'     => ['values'=>$wghts,'attr'=>['type'=>'select','value'=>$this->settings['general']['weight_uom']]],
            'dim_uom'        => ['values'=>$dims, 'attr'=>['type'=>'select','value'=>$this->settings['general']['dim_uom']]],
            'max_pkg_weight' => ['attr'=>['value'=>$this->settings['general']['max_pkg_weight']]],
            'pallet_weight'  => ['attr'=>['value'=>$this->settings['general']['pallet_weight']]],
            'ltl_class'      => ['values'=>viewKeyDropdown($this->options['ltlClasses'], true),'attr'=>['type'=>'select','value'=>$this->settings['general']['ltl_class']]],
            'resi_checked'   => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['resi_checked']]],
            'contact_req'    => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['contact_req']]],
            'address1_req'   => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['address1_req']]],
            'address2_req'   => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['address2_req']]],
            'city_req'       => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['city_req']]],
            'state_req'      => ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['state_req']]],
            'postal_code_req'=> ['attr'=>['type'=>'selNoYes','value'=>$this->settings['general']['postal_code_req']]]]]];
    }

    /**
     *
     * @param type $layout
     */
    public function fundsBuy(&$layout=[])
    {
        msgDebug("\nreached shipping/admin/fundsBuy");
        $carrier= clean('carrier', 'cmd', 'get');
        if (empty($carrier)) { $carrier = 'endicia'; }
        $auth   = $this->loadCarrier($carrier);
        $auth->fundsBuy();
    }

    /**
     * This method extends the PhreeBooks Sales manager to include a tracking popup in the action bar
     * @param type $layout
     * @return type
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('j12_mgr', 1)) { return; }
        $jID = clean('jID', 'integer', 'get');
        switch ($jID) {
            case 12: // block delete if setting says so
                if (getModuleCache('shipping', 'settings', 'general', 'block_trash', 0)) {
                    $layout['datagrid']['manager']['columns']['action']['actions']['trash']['display'] =
    "(row.journal_id!='12' && row.journal_id!='6') || (row.journal_id=='12' && row.waiting=='1' && (row.closed=='0' || row.total_amount==0)) || (row.journal_id=='6' && (row.closed=='0' || row.total_amount==0))";
                } // continue for other journals
                $layout['datagrid']['manager']['columns']['action']['actions']['ship'] = ['order'=>15,'icon'=>'barcode',
                    'label'=>lang('shipping_log'),'events'=>['onClick'=>"jsonAction('shipping/manager/shippingLog', idTBD);"],
                    'display'=>"row.journal_id==12 && row.waiting==0"];
            case  9:
            case 10:
            case 13:
            case 15:
                $layout['datagrid']['manager']['columns']['method_code'] = ['order'=>80,'field'=>BIZUNO_DB_PREFIX.'journal_main.method_code','format'=>'shipInfo',
                    'label'=>lang("method_code_12"),'attr'=>['width'=>160, 'resizable'=>true]];
                break;
            default:
        }
        if (getUserCache('profile', 'device')=='mobile') {
            $layout['datagrid']['manager']['columns']['method_code']['attr']['hidden'] = true;
        }
    }

    /**
     * Hook for phreebooks->main->edit to prohibit Sales from being deleted IF setting prohibits it
     * @param array $layout
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess('j12_mgr', 1)) { return; }
        $jID = !empty($layout['fields']['journal_id']['attr']['value']) ? $layout['fields']['journal_id']['attr']['value'] : 0;
        if (in_array($jID, [12]) && getModuleCache('shipping', 'settings', 'general', 'block_trash', 0)) { // see if the waiting flag is present
            $unshipped = $layout['fields']['waiting']['attr']['value'];
            if (!$unshipped) { unset($layout['toolbars']['tbPhreeBooks']['icons']['trash']); }
        }
    }

    public function invEdit(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        if (empty($rID)) { return; }
        $layout['tabs']['tabInventory']['divs']['shipping'] = ['order'=>75,'label'=>lang('shipping'),'type'=>'html','html'=>'',
            'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=shipping/manager/shpmtDetailsEdit&rID=$rID'"]];
    }

    /**
     * Hook to handle the inventory attributes import from .csv format
     * @return null
     */
    public function apiImport()
    {
        global $io;
        msgDebug("\nEntering hook shipping:apiImport.");
        if (!$security = validateAccess('admin', 2)) { return; }
        $invShip = [];
        if (!$io->validateUpload('fileInventory', '', ['csv','txt'])) { return; }
        $rows = array_map('str_getcsv', file($_FILES['fileInventory']['tmp_name']));
        $head = array_shift($rows);
        foreach ($rows as $row) {
            $values = array_combine($head, $row);
            if (empty($values['sku'])) { continue; }
            $invShip = [
                'box_q' =>clean($values['invAttrH00'], ['format'=>'integer','default'=>1]),
                'box_l' =>clean($values['invAttrH01'], 'integer'),
                'box_w' =>clean($values['invAttrH02'], 'integer'),
                'box_h' =>clean($values['invAttrH03'], 'integer'),
                'box_wt'=>clean($values['invAttrH04'], 'integer'),
                'plt_q' =>clean($values['invAttrH05'], ['format'=>'integer','default'=>1]),
                'plt_l' =>clean($values['invAttrH06'], 'integer'),
                'plt_w' =>clean($values['invAttrH07'], 'integer'),
                'plt_h' =>clean($values['invAttrH08'], 'integer'),
                'plt_wt'=>clean($values['invAttrH09'], 'integer'),];
            msgDebug("\nReady to write ship data = ".print_r($invShip, true));
            dbWrite(BIZUNO_DB_PREFIX.'inventory', ['bizProShip'=>json_encode($invShip)], 'update', "sku='".addslashes($values['sku'])."'");
        }
    }

    /**
     * This function handles extra actions that don't fall into the typical shipping process
     * Actions are handled through the carrier method. See Endicia method for an example.
     * @param type $layout
     * @return type
     */
    public function extraAction(&$layout)
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $details = clean('data', 'text', 'get');
        $parts   = explode(":", $details);
        $carrier = $parts[0];
        $action  = isset($parts[1]) ? $parts[1] : false;
        if (!$action || !$carrier) { return msgAdd("Shipping Extra action does not have enough information!"); }
        $meta = getMetaMethod('carriers', $carrier);
        if (!empty($meta)) { return msgAdd("Could not find carrier class, looking for $carrier!"); }
        $fqcn = "\\bizuno\\$carrier";
        bizAutoLoad($meta['path']."$carrier.php", $fqcn);
        $shipper = new $fqcn();
        $data = [];
        if (method_exists($shipper, $action)) { $data = $shipper->$action(); }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param array $layout - page structure coming in
     * @return modified $layout
     */
    public function install(&$layout=[])
    {
        msgDebug("\nEntering $this->moduleID:install");
        $registry = getModuleCache($this->moduleID, 'properties');
        $registry['path'] = rtrim(BIZUNO_FS_LIBRARY."controllers/$this->moduleID", '/').'/';
        setModuleCache($this->moduleID, 'properties', false, $registry);
        $bAdmin = new bizunoSettings();
        foreach ($this->structure['dirMethods'] as $dirMeth) { $bAdmin->adminInstMethods($this->moduleID, $dirMeth, $this->defMethods); }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'bizProShip')) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD bizProShip TEXT DEFAULT NULL COMMENT 'label:Bizuno Pro Shipping;tag:bizProShip'");
        }
        return true;
    }

    /**
     * Removes all shipping carriers from cache and files
     * @return boolean
     */
    public function remove()
    {
        $carriers = array_keys(getModuleCache('shipping','carriers'));
        foreach ($carriers as $carrier) {
            if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
                $fqcn = "\\bizuno\\$carrier";
                bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn);
                $properties = new $fqcn();
                if (method_exists($properties, 'remove')) { $properties->remove(); }
            }
        }
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'bizProShip')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP `bizProShip`"); }
        // @todo remove all labels and files? Probably should ask first!
        return true;
    }

    /**
     *
     */
    public function signup()
    {
        $carrier = clean('carrier', 'alpha_num', 'get');
        if (file_exists (dirname(__FILE__)."/carriers/$carrier/$carrier.php")) {
            $fqcn   = "\\bizuno\\$carrier";
            bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $fqcn);
            $admin  = new $fqcn();
            if (isset($admin->lang['instructions'])) { msgAdd($admin->lang['instructions'], 'info', lang('instructions')); }
            else                                     { msgAdd('No special instructions found!'); }
        } else { msgAdd("Carrier $carrier not found!"); }
    }

    /**
     *
     * @param array $layout - page structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $myPkgs= getModuleCache($this->moduleID, 'myPackages');
        $data  = [
            'toolbars'=> ['tbPkg'=>['icons'=>[
                'save' => ['order'=>20,'label'=>lang('save'),'icon'=>'save','events'=>['onClick'=>"myPkgSave();"]]]]],
            'tabs'    => ['tabAdmin'=>['divs'=>[
                'myPkg'=>['order'=>70,'label'=>lang('packages', $this->moduleID),'type'=>'divs','divs'=>[
                    'flds'   => ['order'=> 1,'type'=>'fields',  'keys'=>['myPkgs']],
                    'toolbar'=> ['order'=>10,'type'=>'toolbar', 'key' =>'tbPkg'],
                    'body'   => ['order'=>50,'type'=>'datagrid','key' =>'dgMyPkg']]],
                'tools'=>['order'=>80,'label'=>lang('tools'),'type'=>'html','html'=>'','options'=>["href"=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/tools/manager'"]]]]],
            'datagrid'=> ['dgMyPkg'=>$this->dgMyPkg('dgMyPkg')],
            'fields'  => ['myPkgs'=>['attr'=>['type'=>'hidden']]],
            'jsHead'  => [$this->moduleID=>"var myPackages = ".json_encode(['total'=>sizeof($myPkgs), 'rows'=>$myPkgs]).";
function myPkgSave() {
    jqBiz('#dgMyPkg').edatagrid('saveRow');
    bizGridSerializer('dgMyPkg', 'myPkgs');
    jsonAction('$this->moduleID/admin/adminSavePkg&myPkgs='+jqBiz('#myPkgs').val());
}"]];
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
    }

    /**
     * Saves the users settings
     */
    public function adminSave()
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    /**
     * Extends contacts/main/edit to add tab for multi-store shipping
     * @param array $layout
     */
    public function fedExEdit(&$layout=[])
    {
        $rID   = clean('rID', 'integer','get');
        $type  = clean('type','char',   'get');
        if ($type != 'b') { return; }
        $meta  = getMetaContact($rID, 'fedex');
        msgDebug("\nRead meta data = ".print_r($meta, true));
        $values= !empty($meta) ? $meta : ['acct_number'=>'','ltl_acct_num'=>'']; // ,'rest_api_key'=>'','rest_secret'=>'','meter_number'=>'','auth_key'=>'','auth_pw'=>'','sp_hub'=>''];
        $fields= [
            'acct_number' =>['label'=>lang('acct_number'), 'position'=>'after','options'=>['groupSeparator'=>"''"],'attr'=>['type'=>'integer','value'=>$values['acct_number'], 'maxlength'=>9]],
            'ltl_acct_num'=>['label'=>lang('ltl_acct_num'),'position'=>'after','options'=>['groupSeparator'=>"''"],'attr'=>['type'=>'integer','value'=>$values['ltl_acct_num'],'maxlength'=>9]],
            'gnd_econ_hub'=>['label'=>lang('sp_hub'),      'position'=>'after','options'=>['groupSeparator'=>"''"],'attr'=>['type'=>'integer','value'=>$values['gnd_econ_hub'],'maxlength'=>4]]];
        $html  = "Enter the FedEx credentials for this store.<br />".html5('acct_number', $fields['acct_number']);
        $html .= "<br />".html5('ltl_acct_num',$fields['ltl_acct_num'])."<br />".html5('gnd_econ_hub',$fields['gnd_econ_hub']);
        $layout['tabs']['tabContacts']['divs']['fedex'] = ['order'=>95,'label'=>'FedEx','type'=>'html','html'=>$html];
    }

    /**
     * Extends /contacts/main/save to store the fedEx creds
     */
    public function fedExSave()
    {
        $type   = clean('type','char','get');
        if ($type != 'b') { return; }
        $rID    = clean('id', 'integer', 'post');
        $meta   = dbMetaGet(0, 'fedex', 'contacts', $rID);
        $metaIdx= metaIdxClean($meta);
        $meta['acct_number'] = clean('acct_number', 'integer','post');
        $meta['ltl_acct_num']= clean('ltl_acct_num','integer','post');
        $meta['gnd_econ_hub']= clean('gnd_econ_hub','integer','post');
        if (empty($meta['acct_number'])) { return; }
        dbMetaSet($metaIdx, 'fedex', $meta, 'contacts', $rID);
    }

    /**
     * Extends contacts/main/delete
     * @return type
     */
    public function fedExDelete()
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return; }
        $meta   = dbMetaGet(0, 'fedex', 'contacts', $rID);
        $metaIdx= metaIdxClean($meta);
        if (!empty($metaIdx)) { dbMetaDelete($metaIdx, 'contacts'); }
    }

    /**
     * Saves the custom package settings from tab
     */
    public function adminSavePkg()
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $myPkgs= clean('myPkgs', 'json', 'get');
        $temp  = [];
        foreach ($myPkgs['rows'] as $row) {
            if (!empty($row['length']) && !empty($row['width']) && !empty($row['height'])) {
                $temp[] = ['length'=>$row['length'], 'width'=>$row['width'], 'height'=>$row['height']];
            }
        }
        $setPkgs = sortOrder($temp, 'length');
        setModuleCache($this->moduleID, 'myPackages', false, $setPkgs);
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     * Grid structure for package dimensions and weights
     * @param string $name - grid DOM id
     * @param array $pkg - shipment details
     * @return array - grid structure
     */
    private function dgMyPkg($name) { // myPackages
        if (empty($pkg)) { $pkg = ['Qty'=>1, 'Wt'=>1, 'L'=>8,'W'=>6,'H'=>4,'Ins'=>0]; }
        return ['id' =>$name,'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'fitColumns'=>false],
            'events' => ['data'   =>'myPackages'],
            'source' => ['actions'=>['newItem'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'  => ['order'=>1, 'label'=>lang('action'), 'attr'=> ['width'=>60],
                    'events'  => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions' => ['trash'=> ['icon'=>'trash','order'=>20,'size'=>'small','events'=> ['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'length'  => ['order'=>10, 'label'=>lang('length'),'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0}}"]],
                'width'   => ['order'=>20, 'label'=>lang('width'), 'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0}}"]],
                'height'  => ['order'=>30, 'label'=>lang('height'),'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0}}"]]]];
    }
}