<?php
/*
 * Shipping Extension - Ship and Rate methods
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
 * @filesource /controllers/shipping/ship.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/common.php', 'shippingCommon');

class shippingShip extends shippingCommon
{
    public $pageID = 'ship';

    function __construct()
    {
        parent::__construct();
    }

    /**
     * This method pulls the label from HTML form and ends back to populate a popup windows before labelGet
     * @return raw HTML to populate the label window
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function labelMain(&$layout=[])
    {
        msgDebug("\nEntering labelMain");
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $rID     = clean('rID', 'integer', 'get');
        $dbData  = $this->getShipmentData($rID);
        if (empty($dbData['email_s'])) { $dbData['email_s'] = !empty($dbData['email_b']) ? $dbData['email_b']  : ''; } // If shipping email is empty, use billing
        $shipper = $this->loadCarrier($dbData['carrier']);
        if ($rID && !method_exists($shipper, 'labelGet')) {
            msgDebug("\nNo label method, just a log entry");
            $js  = "accordionEdit('accShipping', 'dgShipping', 'dtlShipping', '".jslang('details')."', '$this->moduleID/manager/edit', '$rID');";
            $layout = array_replace_recursive($layout, ['type'=>'divHTML',
                'divs'   => ['divLabel'=>['order'=>50,'label'=>lang('shipment'),'type'=>'html','html'=>'<div id="divLabel">&nbsp;</div>']],
                'jsReady'=> ['jsLabel'=>$js]]);
            return;
        }
        $fields= $this->shipmentFields($dbData, $shipper);
        msgDebug("\nfields = ".print_r($fields, true));
        $keys  = $this->setCarrierKeys($shipper);
        msgDebug("\nkeys = ".print_r($keys, true));
        $js    = $this->labelJS();
        $data  = ['type'=>'divHTML',
            'toolbars' => ['tbShipping'=>['icons'=>[
                'print' => ['order'=>20,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmLabel').submit();"]],
                'new'   => ['order'=>40,'events'=>['onClick'=>"accordionEdit('accShipping', 'dgShipping', 'divLabel', '".lang('label_generator', $this->moduleID)."', '$this->moduleID/$this->pageID/labelMain', 0);"]],
                'rate'  => ['order'=>70,'label'=>lang('rate_quote'),'icon'=>'quote','events'=>['onClick'=>"pkgEstimate();"]]]]],
            'divs' => [
                'toolbar' => ['order'=>10,'type'=>'toolbar', 'key'=>'tbShipping'],
                'formBOF' => ['order'=>15,'type'=>'form',    'key'=>'frmLabel'],
                'general' => ['order'=>50,'type'=>'divs',    'classes'=>['areaView'],'divs'=>[
                    'shipTo'  => ['order'=>10,'type'=>'panel','key'=>'shipTo', 'classes'=>['block33']],
                    'details' => ['order'=>20,'type'=>'panel','key'=>'details','classes'=>['block33']],
                    'options' => ['order'=>30,'type'=>'panel','key'=>'options','classes'=>['block33']],
                    'pnlPkg'  => ['order'=>40,'type'=>'panel','key'=>'pnlPkg', 'classes'=>['block66']],
                    'hazmat'  => ['order'=>50,'type'=>'panel','key'=>'hazmat','styles'=>['display'=>'none'],'classes'=>['block33'],'attr'=>['id'=>'divHazmat']]]],
                'formEOF' => ['order'=>90,'type'=>'html',     'html'=>"</form>"]],
            'panels' => [
                'pnlPkg'  => ['label'=>lang('ship_pkg_detail', $this->moduleID),'type'=>'datagrid','key'   =>'dgPkg','attr'=>['id'=>'pnlPkg']],
                'details' => ['label'=>lang('details'),               'type'=>'fields',  'keys'  =>$keys['details']],
                'shipTo'  => ['label'=>lang('ship_to'),'type'=>'address', 'keys'=>$keys['address_d'], 'attr'=>['id'=>'address'],
                    'settings'=>['suffix'=>'','search'=>false,'clear'=>false,'validate'=>true]],
                'options' => ['label'=>lang('options'),               'type'=>'fields',  'keys'  =>$keys['options']],
                'hazmat'  => ['label'=>lang('options'),               'type'=>'fields',  'keys'  =>$keys['hazmat']]],
            'forms'   => ['frmLabel'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/labelGet"]]],
            'datagrid'=> ['dgPkg'=>$this->dgPkg('dgPkg', $dbData['pkg'])],
            'fields'  => $fields,
            'jsBody'  => $js['jsBody'],
            'jsReady' => $js['jsReady']];
        if (method_exists($shipper, 'pkgPanel')) { $shipper->pkgPanel($data, $dbData['pkg']); }
        if (!empty($keys['settings'])) {
            $data['divs']['general']['divs']['settings'] = ['order'=>60,     'type'=>'panel', 'key' =>'settings', 'classes'=>['block33']];
            $data['panels']['settings'] = ['label'=>lang('settings'),        'type'=>'fields','keys'=>$keys['settings']];
        }
        if (!empty($keys['notify'])) {
            $data['divs']['general']['divs']['notify'] = ['order'=>70,       'type'=>'panel', 'key' =>'notify', 'classes'=>['block33']];
            $data['panels']['notify'] = ['label'=>lang('notifications'),     'type'=>'fields','keys'=>$keys['notify']];
        }
        if (!empty($keys['ltl'])) {
            $data['divs']['general']['divs']['ltlInfo']= ['order'=>80,       'type'=>'panel', 'key' =>'ltlInfo','classes'=>['block33']];
            $data['panels']['ltlInfo']= ['label'=>lang('ltl_details', $this->moduleID),'type'=>'fields','keys'=>$keys['ltl']];
        }
        $layout= array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param array $data - database data
     * @param object $shipper - carrier class
     * @return string
     */
    private function shipmentFields($data=[], $shipper='')
    {
        msgDebug("\nEntering shipmentFields."); // with shipper = ".print_r($shipper, true));
        $shipMethods = $shipPkgs = $shipPickup = $shipConfirms = $shipCods = $shipCurrencies = $shipReturns = $LTLClasses = $ShipBillTo = [];
        $method      = isset($data['method_code']) ? explode(':', $data['method_code']) : [$shipper->code,'GND'];
        $dimUOMs     = [['id'=>'IN', 'text'=>lang('dim_in', $this->moduleID)],    ['id'=>'CM', 'text'=>lang('dim_cm', $this->moduleID)]];
        $weightUOMs  = [['id'=>'LBS','text'=>lang('weight_lbs', $this->moduleID)],['id'=>'KGS','text'=>lang('weight_kgs', $this->moduleID)]];
        $currencyUOMs= [['id'=>'USD','text'=>'USD']];
        $ltlDesc     = !empty($shipper->settings['ltl_desc']) ? $shipper->settings['ltl_desc'] : '';
        $ltlClass    = !empty($shipper->settings['ltl_class'])? $shipper->settings['ltl_class']: $this->ltlClass;
        $shippers    = viewDropdown($this->myCarriers, 'id', 'title', true);
        $shipMethod  = '';
        foreach ($shipper->options['rateCodes'] as $shortCode) {
            $shipMethods[] = ['id'=>$shortCode, 'text'=>!empty($shipper->lang[$shortCode]) ? $shipper->lang[$shortCode] : (!empty(lang($shortCode, $this->moduleID)) ? lang($shortCode, $this->moduleID) : lang('none'))];
            if ($shortCode == $method[1]) { $shipMethod = $shortCode; }
        }
        $residential = false;
        $settings    = isset($data['ship_settings']) ? explode(";", $data['ship_settings']) : [];
        $title       = 'shipping';
        $billType    = 'SENDER';
        $billAcct    = '';
        foreach ($settings as $part) {
            $value = explode(":", $part);
            if ($value[0]=='title') { $title = isset($value[1]) ? $value[1] : $title; }
            if ($value[0]=='resi' && $value[1]) { $residential = true; }
            if ($value[0]=='type') {
                switch ($value[1]) {
                    default:
                    case 'sender':   $billType = 'SENDER';      break;
                    case '3rdparty': $billType = 'THIRD_PARTY'; break;
                    case 'recip':    $billType = 'RECIPIENT';   break;
                    case 'collect':  $billType = 'COLLECT';     break;
                }
                $billAcct = isset($value[2]) ? $value[2] : '';
            }
        }
        if (!isset($data['pkg'])) { $data['pkg'] = ['Qty'=>1, 'Wt'=>0, 'L'=>8, 'W'=>6, 'H'=>4, 'Ins'=>0]; } // package defaults
        $fields = [ // Options
            'pkg_array'    => ['order'=> 1,'attr'=>['type'=>'hidden']], // for grids
            'frt_billed'   => ['order'=> 1,'attr'=>['type'=>'hidden','value'=>isset($data['freight']) ? $data['freight'] : 0]],
            'method_code'  => ['order'=> 1,'attr'=>['type'=>'hidden','value'=>"$shipper->code:GND"]], // for address validation
            // Details
            'carrier'      => ['order'=> 5,'values'=>$shippers,'label'=>lang('carrier'),'attr'=>['type'=>'select', 'value'=>$shipper->code],
                'options' => ['onChange'=>"function (newVal, oldVal) { shipGetMethods(newVal); }"]],
            'ship_method'  => ['order'=>10,'label'=>lang('method'),'values'=>$shipMethods,'attr'=> ['type'=>'select','value'=>$shipMethod]],
            'ship_bill_to' => ['order'=>15,'label'=>lang('ship_bill_to'),        'values'=>viewKeyDropdown($shipper->options['PaymentMap']), 'attr'=>['type'=>'select', 'value'=>$billType]],
            'ship_bill_act'=> ['order'=>20,'label'=>lang('bill_acct_num', $this->moduleID),'attr'=> ['value'=>$billAcct]],
            'ship_ref_1'   => ['order'=>25,'label'=>lang('reference1', $this->moduleID),   'attr'=>['value'=>isset($data['invoice_num']) ? $data['invoice_num'] : '']],
            'ship_ref_2'   => ['order'=>30,'label'=>lang('reference2', $this->moduleID),   'attr'=>['value'=>isset($data['purch_order_id']) ? $data['purch_order_id'] : '']],
            'ship_date'    => ['order'=>35,'label'=>lang('ship_date'),           'break'=>true,'attr'=> ['type'=>'date', 'value'=>isset($data['post_date']) ? $data['post_date'] : biz_date('Y-m-d')]],
            'store_id_b'   => ['order'=>40,'label'=>lang('billing_account', $this->moduleID), 'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$data['store_id']]],
            'store_id_p'   => ['order'=>45,'label'=>lang('ship_pickup_from', $this->moduleID),'values'=>viewStores(),'attr'=>['type'=>'select','value'=>$data['store_id']]],
            // details
            'residential'  => ['order'=>10,'label'=>lang('residential_address'),'attr'=>['type'=>'checkbox','checked'=>$residential]],
            'ship_handling'=> ['order'=>20,'label'=>lang('ship_handling', $this->moduleID),'break'=>true,'attr'=>['type'=>'checkbox']],
            'ship_saturday'=> ['order'=>25,'label'=>lang('ship_saturday', $this->moduleID),'break'=>true,'attr'=>['type'=>'checkbox']],
            'ship_return'  => ['order'=>30,'label'=>lang('ship_return', $this->moduleID),  'break'=>true,'attr'=>['type'=>'checkbox']],
            'insurance'    => ['order'=>35,'label'=>lang('inc_insurance', $this->moduleID),'attr'=>['type'=>'checkbox','checked'=>false, 'size'=>8]],
            'ship_cod'     => ['order'=>45,'label'=>lang('ship_cod', $this->moduleID),     'attr'=>['type'=>'checkbox', 'value'=>1]],
            'ship_cod_val' => ['order'=>46,'label'=>lang('ship_cod_val', $this->moduleID), 'break'=>true,'options'=>['width'=>100],'styles'=>['text-align'=>'right'],'attr'=>['type'=>'currency', 'value'=>isset($data['total_amount']) ? $data['total_amount'] : 0]],
            'ship_cod_cur' => ['order'=>47,'label'=>lang('ship_cod_cur', $this->moduleID), 'break'=>true,'values'=>$currencyUOMs, 'attr'=> ['type'=>'select', 'value'=>!empty($data['currency']) ? $data['currency'] : getDefaultCurrency()]],
            'ship_cod_type'=> ['order'=>48,'label'=>lang('ship_cod_type', $this->moduleID),'break'=>true,'values'=>viewKeyDropdown($shipper->options['CODMap']),'attr'=>['type'=>'select','value'=>$shipper->ship_cod_type]],
            'extra1'       => ['order'=>70,'label'=>lang('extras', $this->moduleID),'values'=>viewKeyDropdown($this->options['extras'], true),'attr'=>['type'=>'select','name'=>'extra1[]','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>[]]],
            // Hazmat
//          'hazmat'       => ['order'=>11,'label'=>lang('hazardous'],    'break'=>true,'attr'=>['type'=>'selNoYes','checked'=>false]],
            // settings
            'ship_pkg'     => ['order'=>10,'label'=>lang('ship_pkg', $this->moduleID),     'break'=>true,'values'=>viewKeyDropdown($shipper->options['PackageMap']),'attr'=>['type'=>'select', 'value'=>$shipper->ship_pkg]],
            'ship_pickup'  => ['order'=>15,'label'=>lang('ship_pickup', $this->moduleID),  'break'=>true,'values'=>viewKeyDropdown($shipper->options['PickupMap']), 'attr'=>['type'=>'select', 'value'=>$shipper->ship_pickup]],
            'weightUOM'    => ['order'=>20,'label'=>lang('weight_uom', $this->moduleID),   'break'=>true,'values'=>$weightUOMs,  'attr'=>['type'=>'select','value'=>$shipper->weightUOM]],
            'dimUOM'       => ['order'=>25,'label'=>lang('dim_uom', $this->moduleID),      'break'=>true,'values'=>$dimUOMs,     'attr'=>['type'=>'select','value'=>$shipper->dimUOM]],
            'currencyUOM'  => ['order'=>30,'label'=>lang('currency'),            'break'=>true,'values'=>$currencyUOMs,'attr'=>['type'=>'select','value'=>!empty($data['currency']) ? $data['currency'] : getDefaultCurrency()]],
            // notify
            'ship_confirm' => ['order'=>10,'label'=>lang('ship_confirm', $this->moduleID), 'attr'=> ['type'=>'checkbox', 'checked'=>!empty($data['email_s'])?true:false]],
            'confirm_type' => ['order'=>20,'values'=>viewKeyDropdown($shipper->options['SignatureMap']),'attr'=> ['type'=>'select', 'value'=>$shipper->confirm_type]],
            // ltl
            'ltl_desc'     => ['order'=>10,'label'=>lang('ltl_desc', $this->moduleID),     'attr'=> ['value'=>$ltlDesc]],
            'ltl_class'    => ['order'=>20,'label'=>lang('ltl_class', $this->moduleID),    'values'=>viewKeyDropdown($shipper->options['LTLClasses']),'attr'=> ['type'=>'select', 'value'=>$ltlClass]],
            // Rate fields
            'num_boxes'   => ['order'=>10,'label'=>lang('num_boxes'),'attr'=>['type'=>'integer','value'=>1,'size'=>5]],
            'weight'      => ['order'=>20,'label'=>lang('ship_weight', $this->moduleID),'attr'=>['type'=>'float','value'=>1, 'size'=>10]],
            'length'      => ['order'=>40,'label'=>lang('dimensions', $this->moduleID),'break'=>false,'attr' =>['type'=>'integer','value'=>8,'size'=>3]],
            'txtWidth'    => ['order'=>49,'html' =>'X','break'=>false,'attr'=>['type'=>'raw']],
            'width'       => ['order'=>50,'break'=>false,'attr'=>['type'=>'integer','value'=>6,'size'=>3]],
            'txtHeight'   => ['order'=>59,'html' =>'X','break'=>false,'attr'=>['type'=>'raw']],
            'height'      => ['order'=>60,'attr'=>['type'=>'integer','value'=>4,'size'=>3]],
            'total_amount'=> ['order'=>40,'attr'=>['type'=>'hidden', 'value'=>0]],
            'ins_amount'  => ['order'=>61,'label'=>lang('amt_insurance', $this->moduleID),'attr'=>['type'=>'currency','value'=>!empty($data['pkg']['Ins'])?$data['pkg']['Ins']:0]]];
        dbStructureFill($this->addrStruc, $data, '_s');
        return array_replace($fields, $this->addrStruc);
    }

    /**
     * Generates the JavaScript for package shipments
     * @return array - JavaScript jsBody and jsReady
     */
    private function labelJS()
    {
        $js = [];
        $js['jsBody']['init'] = "
function pkgUpdate() {
    var totalQty = 0;
    var totalWt  = 0;
    var totalVal = 0;
    if (!bizGridExists('dgPkg')) { return; }
    var rowData  = jqBiz('#dgPkg').datagrid('getData');
    for (var rowIndex=0; rowIndex<rowData.total; rowIndex++) {
        var qty  = bizNumEdGet('dgPkg', rowIndex, 'qty');
        var wt   = bizNumEdGet('dgPkg', rowIndex, 'weight');
        var val  = bizNumEdGet('dgPkg', rowIndex, 'value');
        totalQty+= qty;
        totalWt += qty * Math.round(wt);
        totalVal+= qty * val;
//      alert('calculated toal qty = '+qty+' and weight = '+wt+' and value = '+val);
    }
    jqBiz('#dgPkg').datagrid('reloadFooter',[{qty: totalQty, weight: totalWt, value: totalVal }]);
}
function shipGetMethods(carrier) {
    if (typeof carrier == 'undefined' || carrier == '') { return; }
    jqBiz.ajax({
        url: bizunoAjax+'&bizRt=$this->moduleID/ship/getCarrierOpts&carrier='+carrier,
        success: function (data) {
            processJson(data);
            bizSelVals('ship_method',  data.ship_methods);
            bizSelVals('ship_pkg',     data.ship_pkgs);
            bizSelVals('ship_pickup',  data.ship_pickups);
            bizSelVals('ship_bill_to', data.ship_bill_tos);
            bizSelVals('ship_cod_type',data.ship_cod_types);
            bizSelVals('confirm_type', data.confirm_types);
            bizSelVals('ltl_class',    data.ltl_classes);
            bizSelSet('ship_method',   data.ship_method);
            bizSelSet('ship_bill_to',  data.ship_bill_to);
            bizSelSet('ship_pkg',      data.ship_pkg);
            bizSelSet('ship_pickup',   data.ship_pickup);
            bizSelSet('ship_cod_type', data.ship_cod_type);
            bizSelSet('confirm_type',  data.confirm_type);
            bizSelSet('ltl_class',     data.ltl_class);
            bizTextSet('ltl_desc',     data.ltl_desc);
            jqBiz('#pnlPkg').panel('refresh',bizunoAjax+'&bizRt=$this->moduleID/ship/getPanelPkg&carrier='+carrier);
        }
    });
}
function pkgFields(rowIndex, qty, wt, length, width, height, value) {
    bizNumEdSet('dgPkg', rowIndex, 'qty',   qty);
    bizNumEdSet('dgPkg', rowIndex, 'weight',wt);
    bizNumEdSet('dgPkg', rowIndex, 'length',length);
    bizNumEdSet('dgPkg', rowIndex, 'width', width);
    bizNumEdSet('dgPkg', rowIndex, 'height',height);
    bizNumEdSet('dgPkg', rowIndex, 'value', value);
    pkgUpdate();
}
function pkgEstimate() {
    var data = { ship:{} };
    jqBiz('#address_s input').each(function() { if (jqBiz(this).val()) data.ship[jqBiz(this).attr('name')] = jqBiz(this).val(); });
    var resi = bizCheckBoxGet('residential');
    if (bizGridExists('dgPkg')) {
        jqBiz('#dgPkg').edatagrid('saveRow');
        pkgUpdate();
        data.pkg = jqBiz('#dgPkg').edatagrid('getData');
    } else {
        data.pkg = {rows:[{ qty:1, weight:bizNumGet('weight'), length:bizNumGet('length'), width:bizNumGet('width'), height:bizNumGet('height'), value:0 }]};
    }
    var href = bizunoAjax+'&bizRt=$this->moduleID/ship/rateMain&resi='+resi+'&data='+encodeURIComponent(JSON.stringify(data));
    var json = { action:'window', id:'shippingEst', title:bizLangJS('SHIPPING_ESTIMATOR'), width:1000, height:600, href:href };
    processJson(json);
}
function selHazmat() {
    alert('Hazmat Toggled!');
    bizDivToggle('divHazmat');
}
function preSubmit() {
    if (bizGridExists('dgPkg')) {
        jqBiz('#dgPkg').edatagrid('saveRow');
        pkgUpdate();
        bizGridSerializer('dgPkg', 'pkg_array');
    }
    return true;
}";
        $js['jsReady']['init'] = "ajaxForm('frmLabel'); if (bizGridExists('dgPkg')) { jqBiz('#dgPkg').edatagrid('addRow'); }";
        return $js;
    }

    /**
     *
     * @param type $rID
     */
    private function getShipmentData($rID=0)
    {
        msgDebug("\nEntering getShipmentData with rID = $rID");
        $carriers= array_keys($this->myCarriers);
        $defMeth = !empty($carriers) ? array_shift($carriers) : '';
        $defaults= ['carrier'=>$defMeth,'pkg'=>[],'store_id'=>getUserCache('profile', 'store_id', false, 0)]; // defaults
        if (empty($rID)) { return $defaults; }
        $dbData  = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$rID");
        $encMeth = explode(":", $dbData['method_code']);
        $dbData['carrier'] = isset($encMeth[0]) ? $encMeth[0] : $defMeth;
        // Try to guess the shipment dims and weight
        $result  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$rID");
        $items   = [];
        foreach ($result as $row) {
            if ($row['gl_type']=='frt') { $dbData['ship_settings'] = $row['description']; }
            if ($row['gl_type']=='itm' && $row['sku']) {
                $inv = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='{$row['sku']}'");
                if ($inv !== false) { $items[] = array_merge(['qty'=>$row['qty']], $inv); }
            }
        }
        $this->guessShipment($items);
        $dbData['pkg']= $this->shipment;
        $output = array_replace_recursive($this->getShipmentDefaults(), $dbData);
        msgDebug("\nReturning from getShipmentData with dbData = ".print_r($output, true));
        return $output;
    }

    private function getShipmentDefaults()
    {
        return [
            'primary_name' => '',   'contact'     => '', 'address1'     => '',  'address2'     => '',
            'city'         => '',   'state'       => '', 'postal_code'  => '',  'country'      => '',
            'telephone1'   => '',   'email'       => '', 'ship_bill_to' => '',  'ship_bill_act'=> '',
            'carrier'      => '',   'method_code' => '', 'pkg_array'    => [],  'frt_billed'   => '',
            'ship_method'  => '',   'ship_ref_1'  => '', 'ship_ref_2'   => '',  'ship_date'    => '',
            'store_id_b'   => 0,    'store_id_p'  => 0,  'residential'  => '',  'ship_handling'=> '',
            'ship_saturday'=> '',   'ship_return' => '', 'insurance'    => '',  'ship_cod'     => '',
            'ship_cod_val' => '',   'ship_cod_cur'=> '', 'ship_cod_type'=> '',  'extra1'       => '',
            'ship_pkg'     => [],   'ship_pickup' => '', 'weightUOM'    => 'LB','dimUOM'       => 'IN',
            'currencyUOM'  => 'USD','ship_confirm'=> '', 'confirm_type' => '',
            'ltl_desc'     => '',   'ltl_class'   => '125'];
    }

    /*
     *
     */
    private function setCarrierKeys($shipper)
    {
        msgDebug("\nEntering setCarrierKeys");
        if (method_exists($shipper, 'labelKeys')) { $keys = $shipper->labelKeys(); }
        else { $keys =  [
            'address_d'=> ['primary_name','contact','address1','address2','city','state','postal_code','country','telephone1','email'],
            'details'  => ['ship_bill_to','ship_bill_act','carrier','method_code','pkg_array','frt_billed',
                'ship_method','ship_ref_1','ship_ref_2','ship_date','store_id_b','store_id_p'],
            'options'  => ['residential','ship_handling','ship_saturday','ship_return','insurance', // 'ship_hazmat',
                'ship_cod','ship_cod_val','ship_cod_cur','ship_cod_type','extra1'],
            'hazmat'   => [],
            'settings' => ['ship_pkg','ship_pickup','weightUOM','dimUOM','currencyUOM'],
            'notify'   => ['ship_confirm','confirm_type'],
            'ltl'      => ['ltl_desc','ltl_class']];
            msgDebug("\nLeaving setCarrierKeys with default keys.");
        }
        return $keys;
    }

    /**
     * Retrieves a label from the selected shipper
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function labelGet(&$layout=[])
    {
        msgDebug("\nEntering labelGet.");
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $carrier  = clean('carrier', 'text', 'post');
        $storeID  = clean('store_id_p', ['format'=>'text','default'=>0], 'post');
        if (!$carrier) { return msgAdd('The proper carrier was not passed!'); }
        $request  = $this->prepLabel();
        $labelData= retrieve_carrier_function($carrier, 'labelGet', $request);
        if (!is_array($labelData) || sizeof($labelData) == 0) { return; }
        msgDebug("\nLabel return array = ".print_r($labelData, true));
        dbTransactionStart();
        $package  = $rIDs = $meta = [];
        $bookTotal= $costTotal = 0;
        foreach ($labelData as $value) { // each package
            if (empty($meta)) { 
                $meta = ['ref_num'=> getNextReference('next_shipment_num'),        'store_id'=>$storeID, 'method_code'=>"$carrier:{$value['method']}", // 'id'=>$row['id'], 
                    'ship_date'   => biz_date('Y-m-d H:i:s', $value['ship_date']), 'deliver_date'=> !empty($value['delivery_date']) ? $value['delivery_date'] : 'null',
                    'notes'       => isset($value['notes']) ? $value['notes'] : ''];
            }
            $package[] = ['tracking_id'=>$value['tracking'], 'deliver_date'=>substr(!empty($value['delivery_date'])?$value['delivery_date']:'', 0, 8), 'cost'=>$value['net_cost'], 'book'=>$value['book_cost']];
            $bookTotal += $value['book_cost'];
            $costTotal += $value['net_cost'];
        }
        $meta['total_cost'] = $costTotal;
        $meta['total_book'] = $bookTotal;
        $meta['packages']= ['total'=>sizeof($package), 'rows'=>$package];
        $refID = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "journal_id=12 AND invoice_num='{$value['ref_id']}'"); // ref_id should be the same for all packages
        $meta['table'] = empty($refID) ? 'common' : 'journal';
        if (!empty($refID)) { dbWrite(BIZUNO_DB_PREFIX.'journal_main', ['waiting'=>'0'], 'update', "id=$refID"); }
        else {  msgAdd('Bizuno could not find an invoice to tie this label to.'); }
        $metaID = dbMetaSet(0, 'shipment', $meta, $meta['table'], $refID);
        msgLog(lang('ship')." - ".lang('reference').": {$value['ref_id']} ".lang('method')." ".viewProcess("$carrier:{$value['method']}", 'shipInfo')." ".lang('num_boxes').": ".sizeof($labelData));
        dbTransactionCommit();
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"
            accordionEdit('accShipping', 'dgShipping', 'divLabel', '{lang('label_generator']}', '$this->moduleID/ship/labelMain', 0);
            bizGridReload('dgShipping');
            jqBiz('#accShipping').accordion('select',0);
            jqBiz('#selInvoice').combogrid('clear');
            jqBiz('#selInvoice').combogrid('grid').datagrid({data:[]});
            bizFocus('selInvoice');
            winOpen('shippingLabel', '$this->moduleID/$this->pageID/labelView&table={$meta['table']}&metaID=$metaID');"]]);
    }

    /**
     *
     * @param array $layout
     */
    private function prepLabel(&$layout=[])
    {
        $fields = [ // Options
            'frt_billed'   => clean('frt_billed',   'float',  'post'),
            'method_code'  => clean('method_code',  'cmd',    'post'),
            // Details
            'carrier'      => clean('carrier',      'cmd',    'post'),
            'ship_method'  => clean('ship_method',  'cmd',    'post'),
            'ship_bill_to' => clean('ship_bill_to', 'cmd',    'post'),
            'ship_bill_act'=> clean('ship_bill_act','cmd',    'post'),
            'ship_ref_1'   => clean('ship_ref_1',   'cmd',    'post'),
            'ship_ref_2'   => clean('ship_ref_2',   'cmd',    'post'),
            'ship_date'    => clean('ship_date',    'date',   'post'),
            // details
            'ship_handling'=> clean('ship_handling','float',  'post'), // Sender Account
            'ship_saturday'=> clean('ship_saturday','integer','post'),
            'ship_return'  => clean('ship_return',  'integer','post'),
            'insurance'    => clean('insurance',    'integer','post'),
            'ship_cod'     => clean('ship_cod',     'integer','post'),
            'ship_cod_val' => clean('ship_cod_val', 'cmd',    'post'),
            'ship_cod_cur' => clean('ship_cod_cur', 'cmd',    'post'),
            'ship_cod_type'=> clean('ship_cod_type','cmd',    'post'),
            'extra1'       => clean('extra1',       'array',  'post'),
            // Hazmat
//          'hazmat'       => clean('hazmat',       'TND',    'post'),
            // settings
            'ship_pkg'     => clean('ship_pkg',     'cmd',    'post'),
            'ship_pickup'  => clean('ship_pickup',  'cmd',    'post'),
            'weightUOM'    => clean('weightUOM',    'cmd',    'post'),
            'dimUOM'       => clean('dimUOM',       'cmd',    'post'),
            'currencyUOM'  => clean('currencyUOM',  'cmd',    'post'),
            // notify
            'ship_confirm' => clean('ship_confirm', 'integer','post'),
            'confirm_type' => clean('confirm_type', 'cmd',    'post'),
            // ltl
            'ltl_desc'     => clean('ltl_desc',     'text',   'post'),
            'ltl_class'    => clean('ltl_class',    'cmd',    'post')];
        $packages = clean('pkg_array', 'json', 'post');
        $fields['pkgs'] = !empty($packages['rows']) ? $packages['rows'] : [];
        $this->fieldsAddress($fields, ['suffix'=>'_s','cID'=>clean('store_id_p', 'integer', 'post')]); // shipper
        $this->fieldsAddress($fields, ['suffix'=>'_o','cID'=>clean('store_id_p', 'integer', 'post')]); // origin
        $this->fieldsAddress($fields, ['suffix'=>'_p','cID'=>clean('store_id_b', 'integer', 'post')]); // payor
        $this->fieldsAddress($fields, ['suffix'=>'','src'=>'post']); // destination
        if (!empty(clean('residential', 'integer', 'post'))) { $fields['destination']['residential'] = true; } // check for resi
        msgDebug("\nReady to process rates with pkg = ".print_r($fields, true));
        return $fields;
    }

    /**
     * Generates view of the label after it was retrieved from the shipper
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function labelView(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID   = clean('rID',   'integer', 'get');
        $metaID= clean('metaID','integer', 'get');
        $table = clean('table', 'db_field','get');
        $html  = $jsHead = $jsBody = $jsReady = '';
        if (!empty($rID)) { // passed from the manager  
            $meta = getMetaJournal($rID, 'shipment');
        } else {
            $meta = dbMetaGet($metaID, 'shipment', $table);
        }
        msgDebug("\nRead meta for shipping = ".print_r($meta, true));
        if (empty($meta)) { return msgAdd("Failed to pull the record for rID = $metaID and table $table"); }
        $date   = explode('-', substr($meta['ship_date'], 0, 10));
        $method = explode(':', $meta['method_code']);
        $carrier= $method[0];
        foreach($meta['packages']['rows'] as $package) {
            msgDebug("\nRead package to find label = ".print_r($package, true));
            $path   = "data/shipping/labels/$carrier/{$date[0]}/{$date[1]}/{$date[2]}/{$package['tracking_id']}*.*";
            msgDebug("\nlooking for path = $path");
            $files  = $io->folderReadGlob($path);
            msgDebug("\nRead glob files = ".print_r($files, true));
            if (empty($files)) { msgAdd("Label file $path cannot be found!"); continue; }
            foreach ($files as $file) {
                $ext = strtolower(substr($file, strrpos($file, '.')+1));
                switch ($ext) {
                    case 'gif':
                        $jsLabelGIF[] = ['type'=>$ext, 'rID'=>$metaID,'path'=>$file];
                        $html  .= html5('', ['break'=>true,'attr'=>['type'=>'img', 'src'=>BIZUNO_URL_FS.getUserCache('business', 'bizID')."/".$jsLabelGIF[0]['path']]]);
                        break;
                    case 'pdf':
                        $html  .= html5('', ['break'=>true,'events'=>['onClick'=>"labelPDF($metaID, '$file');"],'attr'=>['type'=>'button','value'=>'Download PDF']]);
                        break;
                    case 'lpt': // accumulate the labels so only one button prints all thermal labels
                        $enTherm= true;
                        $dataTherm[] = ['type'=>'raw','format'=>'base64','data'=>base64_encode(file_get_contents(BIZUNO_DATA.$file))];
                        break;
                }
            }
        }
        if (!empty($enTherm)) { // create a single button for all thermal labels
            $jsHead.= "var thermData = ".json_encode($dataTherm).";\n";
            $html  .= html5('', ['break'=>true,'events'=>['onClick'=>"labelThermal(thermData);"],'attr'=>['type'=>'button', 'value'=>'Print Thermal']]);
        }
        $jsReady .= "if (typeof(qz)!=='undefined') {
    if (typeof qzCertificate !== 'undefined') {
        qz.security.setCertificatePromise(function(resolve, reject) { resolve(qzCertificate); });
    } else {
        qz.security.setCertificatePromise(function(resolve, reject) {
            fetch('{$this->thermalTransport}digital-certificate.txt', {cache: 'no-store', headers: {'Content-Type': 'text/plain'}})
                .then(function(data) { data.ok ? resolve(data.text()) : reject(data.text()); });
        });
    }
} else {
    alert('QZ-tray was not loaded from the Bizuno server, labels will not print! try reloading the page.');
}";
        $jsHead .= "
function labelThermal(thermData) {
    qz.websocket.connect().then(function() {
        return qz.printers.find('zebra');
    }).then(function(printer) {
        var config = qz.configs.create(printer);
        qz.print(config, thermData);
    }).catch(function(e) { alert('Houston, we have a problem: '+e); });
    setTimeout(function() { window.close(); }, 10000);
}
function labelPDF(rID, path) {
    jqBiz.fileDownload(bizunoAjax+'&bizRt=$this->moduleID/ship/labelDownload&rID='+rID+'&data='+path, {
        failCallback: function (response, url) { processJson(JSON.parse(response)); },
        httpMethod: 'POST',
        data: ''
    });
}";
        $data = ['type'=>'page', 'title'=>lang('label_generator', $this->moduleID),
            'divs'    => [
                'toolbar' => ['order'=>20,'type'=>'toolbar','key' =>'tbLabel'],
                'divLabel'=> ['order'=>60,'type'=>'html',   'html'=>$html]],
            'toolbars'=> ['tbLabel'=>['icons'=>['close'=>['order'=>10,'events'=>['onClick'=>"window.close();"]]]]],
            'jsReady' => ['init'=>$jsReady]];
        if (!empty($enTherm)) {
            $data['head']['qzTray']    = ['order'=>90,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_URL_SCRIPTS.'qz-tray/qz-tray.js"></script>'];
//          $data['head']['qzJsrasign']= ['order'=>91,'type'=>'html','html'=>'<script src="https://cdn.rawgit.com/kjur/jsrsasign/c057d3447b194fa0a3fdcea110579454898e093d/jsrsasign-all-min.js"></script>'];
            $data['head']['qzJsrasign']= ['order'=>91,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_URL_SCRIPTS.'qz-tray/jsrsasign.js"></script>'];
            $data['head']['qzSign']    = ['order'=>92,'type'=>'html','html'=>'<script type="text/javascript" src="'.$this->thermalTransport.'sign-message.js"></script>'];
        } elseif (!file_exists(BIZUNO_URL_SCRIPTS.'assets/qz-tray/qz-tray.js')) {
            msgAdd("Thermal labels are not available to print, proper transport is not installed!");
        }
        $data['jsHead']['init'] = $jsHead; // needs to be last
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Downloads a label
     * @return type
     */
    public function labelDownload()
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $path = clean('data','filename','get'); // relative file path from BIZUNO_DATA
        $io->download('file', pathinfo($path, PATHINFO_DIRNAME)."/", pathinfo($path, PATHINFO_BASENAME), false); // doesn't return if successful
    }
}