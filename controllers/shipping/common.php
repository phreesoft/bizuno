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
 * @copyright  2008-2025, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2025-09-09
 * @filesource /controllers/shipping/common.php
 */

namespace bizuno;

bizAutoLoad(dirname(__FILE__).'/functions.php', 'shippingView', 'function');

class shippingCommon
{
    public    $moduleID  = 'shipping';
//  public    $pageID    = 'manager'; // Set at the calling class
    protected $domSuffix = 'Ship';
    protected $secID     = 'shipping';
    protected $metaPrefix= 'shipment_';
    protected $nextRefIdx= 'next_shipment_num';
    protected $ltlClass  = '125';
    private   $volumeDef = 1500; // default volume for a box in square inches
    private   $maxBoxWt  = 50; // maximum weight to put into a box
    private   $freightWt = 150; // Weight from which the shipment is palletized and shipped LTL
    private   $palletWt  = 1500;
    public $defaults; 
    public $lang;
    public $options;
    public $settings;
    public $thermalTransport;
    public $shipment;
    public $addrStruc;
    public $carriers;
    public $myCarriers;

    function __construct()
    {
        $this->defaults = ['general'=>[
            'gl_shipping_c' => getChartDefault(30),
            'gl_shipping_v' => getChartDefault(34),
            'bill_hq'       => 0, 'block_trash'  =>0, 'skip_guess'  =>0, 'weight_uom'=>'LB', 'dim_uom'=>'IN',
            'max_pkg_weight'=> 70,'pallet_weight'=>25,'ltl_class'   =>'125',
            'resi_checked'  => 1, 'contact_req'  =>0, 'address1_req'=>1, 'address2_req'=>0, 'city_req'=>1, 'state_req'=>1, 'postal_code_req'=>1]];
        $this->lang     = getExtLang($this->moduleID);
        $this->options  = $this->getOptions();
        $this->settings = array_replace_recursive($this->defaults, getModuleCache($this->moduleID, 'settings'));
        $this->thermalTransport = defined('BIZUNO_3P_QZ_TRAY') ? BIZUNO_3P_QZ_TRAY : BIZBOOKS_URL_ROOT.'scripts/qz-tray/';
        $this->shipment = ['Qty'=>1, 'Wt'=>0, 'L'=>8, 'W'=>6, 'H'=>4, 'Ins'=>0];
        $this->addrStruc= dbLoadStructure(BIZUNO_DB_PREFIX.'contacts');
        unset($this->addrStruc['address_id'],$this->addrStruc['type']);
        unset($this->addrStruc['telephone2'],$this->addrStruc['email2'],$this->addrStruc['telephone3'],$this->addrStruc['email3']);
        unset($this->addrStruc['telephone4'],$this->addrStruc['email4'],$this->addrStruc['website'],   $this->addrStruc['notes']);
        $this->carriers = ['freeshipper', 'flat']; // pick a couple of default carriers to install
        $carriers = $carriers = getMetaMethod('carriers');
        $this->myCarriers = [];
        foreach ($carriers as $key => $value) { if (!empty($value['status'])) { $this->myCarriers[$key] = $value; } }
    }

    /**
     * Sets the page fields for creating a shipment
     * @return array - page structure
     */
    public function fieldShipment()
    {
        $this->struc = [
            'address_id'  => ['panel'=>'address','order'=> 1, 'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'ref_id'      => ['panel'=>'address','order'=> 1, 'clean'=>'integer', 'attr'=>['type'=>'hidden', 'value'=>0]],
            'type'        => ['panel'=>'address','order'=> 1, 'clean'=>'char',    'attr'=>['type'=>'hidden', 'value'=>'s']],
            'name_first'  => ['panel'=>'general','order'=>10, 'label'=>lang('name_first'),   'clean'=>'text',     'attr'=>['size'=>32, 'value'=>'']],
            'name_last'   => ['panel'=>'general','order'=>20, 'label'=>lang('name_last'),    'clean'=>'text',     'attr'=>['size'=>32, 'value'=>'']],
            'title'       => ['panel'=>'general','order'=>30, 'label'=>lang('title'),        'clean'=>'text',     'attr'=>['size'=>32, 'value'=>'']],
            'primary_name'=> ['panel'=>'address','order'=>10, 'label'=>lang('primary_name'), 'clean'=>'text',     'attr'=>['size'=>48, 'value'=>'']],
            'contact'     => ['panel'=>'address','order'=>15, 'label'=>lang('contact'),      'clean'=>'text',     'attr'=>['size'=>48, 'value'=>'']],
            'address1'    => ['panel'=>'address','order'=>20, 'label'=>lang('address1'),     'clean'=>'text',     'attr'=>['size'=>48, 'value'=>'']],
            'address2'    => ['panel'=>'address','order'=>25, 'label'=>lang('address2'),     'clean'=>'text',     'attr'=>['size'=>48, 'value'=>'']],
            'city'        => ['panel'=>'address','order'=>30, 'label'=>lang('city'),         'clean'=>'text',     'attr'=>['size'=>24, 'value'=>'']],
            'state'       => ['panel'=>'address','order'=>35, 'label'=>lang('state'),        'clean'=>'text',     'attr'=>['size'=>24, 'value'=>'']],
            'postal_code' => ['panel'=>'address','order'=>40, 'label'=>lang('postal_code'),  'clean'=>'cmd',      'attr'=>['size'=>12, 'value'=>'']],
            'country'     => ['panel'=>'address','order'=>45, 'label'=>lang('country'),      'clean'=>'alpha_num','attr'=>['type'=>'country', 'value'=>'USA']],
            'telephone1'  => ['panel'=>'contact','order'=>10, 'label'=>lang('telephone'),    'clean'=>'filename', 'attr'=>['size'=>20, 'value'=>'']],
            'telephone2'  => ['panel'=>'contact','order'=>15, 'label'=>lang('telephone2'),   'clean'=>'filename', 'attr'=>['size'=>20, 'value'=>'']],
            'telephone3'  => ['panel'=>'contact','order'=>20, 'label'=>lang('telephone3'),   'clean'=>'filename', 'attr'=>['size'=>20, 'value'=>'']],
            'telephone4'  => ['panel'=>'contact','order'=>25, 'label'=>lang('telephone4'),   'clean'=>'filename', 'attr'=>['size'=>20, 'value'=>'']],
            'email'       => ['panel'=>'contact','order'=>30, 'label'=>lang('email'),        'clean'=>'email',    'attr'=>['size'=>64, 'value'=>'']],
            'email2'      => ['panel'=>'contact','order'=>35, 'label'=>lang('email_m'),      'clean'=>'email',    'attr'=>['size'=>64, 'value'=>'']],
            'email3'      => ['panel'=>'contact','order'=>40, 'label'=>lang('email_p'),      'clean'=>'email',    'attr'=>['size'=>64, 'value'=>'']],
            'email4'      => ['panel'=>'contact','order'=>45, 'label'=>lang('email_r'),      'clean'=>'email',    'attr'=>['size'=>64, 'value'=>'']],
            'website'     => ['panel'=>'contact','order'=>50, 'label'=>lang('website'),      'clean'=>'url_full', 'attr'=>['size'=>48, 'value'=>'']],
            'notes'       => ['panel'=>'notes',  'order'=>10,                                'clean'=>'text',     'attr'=>['type'=>'editor']]];
    }

    protected function loadCarrier($carrier=false)
    {
        msgDebug("\nEntering loadCarrier with carrier = ".print_r($carrier, true));
        if (!is_string($carrier)) { msgAdd("carrier = ".print_r($carrier, true)); return; }
        if (!empty($carrier)) {
            if (!file_exists(dirname(__FILE__)."/carriers/$carrier/$carrier.php")) { return msgAdd("Could not find carrier class, looking for $carrier!"); }
            bizAutoLoad(dirname(__FILE__)."/carriers/$carrier/$carrier.php", $carrier);
            $fqcn    = "\\bizuno\\$carrier";
            msgDebug("\nCreating class $carrier");
            $shipper = new $fqcn();
        } else {
            $shipper = new \stdClass();
            $shipper->code = '';
            $shipper->lang['GND'] = $this->lang['GND'];
        }
        if (!isset($shipper->options)) { $shipper->options = []; }

        if (!isset($shipper->options['PackageMap']))   { $shipper->options['PackageMap']   = [''=>lang('none')]; }
        if (!isset($shipper->options['PickupMap']))    { $shipper->options['PickupMap']    = [''=>lang('none')]; }
        if (!isset($shipper->options['CODMap']))       { $shipper->options['CODMap']       = [''=>lang('none')]; }
        if (!isset($shipper->options['SignatureMap'])) { $shipper->options['SignatureMap'] = [''=>lang('none')]; }
        if (!isset($shipper->options['rateCodes']))    { $shipper->options['rateCodes']    = [''=>lang('none')]; }
        if (!isset($shipper->options['PaymentMap']))   { $shipper->options['PaymentMap']   = [''=>lang('none')]; }
        if (!isset($shipper->options['LTLClasses']))   { $shipper->options['LTLClasses']   = [''=>lang('none')]; }
        $shipper->weightUOM    = !empty($this->settings['weight_uom'])    ? $this->settings['weight_uom']: 'LBS';
        $shipper->dimUOM       = !empty($this->settings['dim_uom'])       ? $this->settings['dim_uom']   : 'IN';
        $shipper->ship_pkg     = !empty($shipper->options['PackageMap'])  ? array_shift(array_keys((array)$shipper->options['PackageMap']))   : '';
        $shipper->ship_pickup  = !empty($shipper->options['PickupMap'])   ? array_shift(array_keys((array)$shipper->options['PickupMap']))    : '';
        $shipper->ship_cod_type= !empty($shipper->options['CODMap'])      ? array_shift(array_keys((array)$shipper->options['CODMap']))       : '';
        $shipper->confirm_type = !empty($shipper->options['SignatureMap'])? array_shift(array_keys((array)$shipper->options['SignatureMap'])) : '';
        return $shipper;
    }

    /**
     * Sets the fields for viewing and populates based on the args
     * @param array - Working fields array to append to
     * @param array $args -
     *   'src': [default 'post'] - if no rID then data is gathered from post
     *   'suffix': [default '_d'] - _s (Shipper), _o (Origin), _d (Destination), _p (Payment)
     *   'rID': [default 0] - journal_main record ID to fetch data
     */
    public function fieldsAddress(&$output, $args=[])
    {
        msgDebug("\nEntering fieldsAddress with args = ".print_r($args, true));
        $opts = array_replace(['src'=>'post','suffix'=>'_d','rID'=>0], $args);
        switch ($opts['suffix']) {
            default:
            case '_d': $bID=0;                                      $target='destination';break;
            case '_o': $bID=clean('store_id_p', 'integer', 'post'); $target='origin';     break;
            case '_p': $bID=clean('store_id_b', 'integer', 'post'); $target='payor';      break;
            case '_s': $bID=clean('store_id_p', 'integer', 'post'); $target='shipper';    break;
        }
        $data = $this->fieldsAddressValues($opts, $bID);
        $output[$target] = $data;
    }

    private function fieldsAddressValues($opts=[], $bID=0)
    {
        $output = ['bID'=>$bID];
        $data   = !empty($opts['rID']) ? dbGetJournalRecord($opts['rID']) : [];
        $contact=  isset($opts['cID']) ? dbGetContact($opts['cID']) : [];
        foreach ($this->addrStruc as $idx => $row) {
            $value = $suffix = '';
            if (!empty($opts['rID'])) { // record ID from journal entry
                $suffix = in_array($opts['suffix'], ['d']) ? '_s' : '_b';
                if ('country'==$idx) { $value = clean($data[$idx.$suffix], ['format'=>'country', 'option'=>'ISO2']); } // Get ISO2 for some carriers
                else { $value = isset($data[$idx.$suffix]) ? $data[$idx.$suffix] : ''; }
            } elseif (isset($opts['cID'])) { // contact ID, can be zero for HQ
                if ('country'==$idx) { $value = clean($contact[$idx], ['format'=>'country', 'option'=>'ISO2']); } // Get ISO2 for some carriers
                else { $value = isset($contact[$idx]) ? $contact[$idx] : ''; }
            } elseif ('post'==$opts['src']) { // post field
                if ('country'==$idx) { $value = clean($idx.$opts['suffix'], ['format'=>'country', 'option'=>'ISO2'], 'post'); }// Get ISO2 for some carriers
                else { $value = clean($idx.$opts['suffix'], ['format'=>$row['format'], 'default'=>$row['default']], 'post'); }
            }
            if (!empty($value)) { $output[$idx] = $value; } // only add value if not null
        }
        return $output;
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function getCarrierOpts(&$layout=[])
    {
        msgDebug("\nEntering getCarrierOpts.");
        if (!$security = validateAccess($this->secID, 2)) { return; }
        $carrier = clean('carrier', 'cmd', 'get');
        if (empty($carrier)) { return; }
        $shipper = $this->loadCarrier($carrier);
        // @TODO this is duplicated here for creating labels on the fly, should be merged and put into loadCarrier
        foreach ($shipper->options['rateCodes'] as $shortCode) {
            $shipper->shipMethods[] = ['id'=>$shortCode, 'text'=>!empty($shipper->lang[$shortCode]) ? $shipper->lang[$shortCode] : $this->lang[$shortCode]];
        }
        $package = array_keys((array)$shipper->options['PackageMap']);
        $pickup  = array_keys((array)$shipper->options['PickupMap']);
        $cod_type= array_keys((array)$shipper->options['CODMap']);
        $sig_type= array_keys((array)$shipper->options['SignatureMap']);
        $data    = [
            'ship_method'   => $shipper->shipMethods[0]['id'],
            'ship_bill_to'  => 'SENDER',
            'ship_pkg'      => array_shift($package),
            'ship_pickup'   => array_shift($pickup),
            'ship_cod_type' => array_shift($cod_type),
            'confirm_type'  => array_shift($sig_type),
            'ltl_class'     => !empty($shipper->settings['ltl_class'])? $shipper->settings['ltl_class']: $this->ltlClass,
            'ltl_desc'      => !empty($shipper->settings['ltl_desc']) ? $shipper->settings['ltl_desc'] : '',
            'ship_methods'  => $shipper->shipMethods,
            'ship_bill_tos' => viewKeyDropdown($shipper->options['PaymentMap']),
            'ship_pkgs'     => viewKeyDropdown($shipper->options['PackageMap']),
            'ship_pickups'  => viewKeyDropdown($shipper->options['PickupMap']),
            'ship_cod_types'=> viewKeyDropdown($shipper->options['CODMap']),
            'confirm_types' => viewKeyDropdown($shipper->options['SignatureMap']),
            'ltl_classes'   => viewKeyDropdown($shipper->options['LTLClasses'])];
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode($data)]);
    }

    /**
     * Grid structure for package dimensions and weights
     * @param string $name - grid DOM id
     * @param array $pkg - shipment details
     * @return array - grid structure
     */
    protected function dgPkg($name, $pkg=false) {
        if (empty($pkg)) { $pkg = ['Qty'=>1, 'Wt'=>1, 'L'=>8,'W'=>6,'H'=>4,'Ins'=>0]; }
        $pieceWt = ceil($pkg['Wt'] / $pkg['Qty']);
        return ['id' =>$name,'type'=>'edatagrid',
            'attr'   => ['toolbar'=>"#{$name}Toolbar",'fitColumns'=>true,'showFooter'=>true],
            'events' => [
                'onAdd'     => "function(rowIndex, row) { pkgFields(rowIndex, '{$pkg['Qty']}', '$pieceWt', '{$pkg['L']}', '{$pkg['W']}', '{$pkg['H']}', '{$pkg['Ins']}'); }",
                'onClickRow'=> "function(rowIndex) { lastIndex = rowIndex; }",
                'onDestroy' => "function(rowIndex, row) { pkgUpdate(); }"],
            'source' => [
                'actions' => ['newItem'=>['order'=>10,'icon'=>'add','size'=>'large','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'  => ['order'=>1, 'label'=>lang('action'), 'attr'=>['width'=>60],
                    'events'  => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions' => ['trash'=> ['icon'=>'trash','order'=>20,'size'=>'small','events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'qty'     => ['order'=>20, 'label'=>lang('quantity'),'attr'=>['width'=>100,'sortable'=>true,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0,value:'{$pkg['Qty']}',onChange:function(newVal, oldVal){ pkgUpdate();}}}"]],
                'weight'  => ['order'=>30, 'label'=>$this->lang['weight_each'],'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0,value:'{$pkg['Wt']}',onChange:function(newVal, oldVal){ pkgUpdate();}}}"]],
                'length'  => ['order'=>50, 'label'=>lang('length'),'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0,value:'{$pkg['L']}'}}"]],
                'width'   => ['order'=>60, 'label'=>lang('width'), 'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0,value:'{$pkg['W']}'}}"]],
                'height'  => ['order'=>70, 'label'=>lang('height'),'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{precision:0,value:'{$pkg['H']}'}}"]],
                'value'   => ['order'=>80, 'label'=>lang('value'), 'attr'=>['width'=>100,'resizable'=>true],
                    'events'=> ['editor'=>"{type:'numberbox',options:{value:'{$pkg['Ins']}',onChange:function(newVal, oldVal){ pkgUpdate();}}}"]]]];
    }

    /**
     * AJAX method to get the panel
     * @param type $layout
     */
    public function getPanelPkg(&$layout=[])
    {
        msgDebug("\nEntering getPanelPkg");
        $carrier= clean('carrier', 'cmd', 'get');
        $shipper= $this->loadCarrier($carrier);
        $data   = ['type'=>'divHTML',
            'divs'    => ['pnlPkg'=>['type'=>'datagrid','key'=>'dgPkg']],
            'datagrid'=> ['dgPkg' =>$this->dgPkg('dgPkg', [], false)],
            'fields'  => []];
        if (method_exists($shipper, 'pkgPanel')) {
            msgDebug("\nCalling $carrier pkgPanel");
            $shipper->pkgPanel($data, [], true);
        }
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $items
     */
    protected function guessShipment($items=[])
    {
        msgDebug("\nEntering guessShipment with items = ".print_r($items, true));
        $weight = $ttlWt = $ttlBox = $ttlIns = 0;
        // $this->shipment = ['Qty'=>1, 'Wt'=>0, 'L'=>8, 'W'=>6, 'H'=>4];
        foreach ($items as $row) {
            if (empty($row['sku'])) { continue; }
            $sku    = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='".addslashes($row['sku'])."'");
            $temp   = empty($sku['bizProAttr']) ? ['category'=>'', 'attrs'=>[]] : json_decode($sku['bizProAttr'], true);
            $sku['bizProAttr'] = !empty($temp['attrs']) ? $temp['attrs'] : [];
            msgDebug("\nFetched item atributes: ".print_r($sku['bizProAttr'], true));
            $sku['bizProShip'] = !empty($sku['bizProShip']) ? json_decode($sku['bizProShip'], true) : [];
            msgDebug("\nFetched item ship dims: ".print_r($sku['bizProShip'], true));
            $volume = $this->guessVolume($sku, $row['qty']);
            $weight = $this->guessWeight($sku, $row['qty']);
            $boxes  = $this->guessBoxes($sku, $volume, $weight, $row['qty']);
            $ttlWt += $weight;
            $ttlBox+= $boxes;
            $ttlIns+= $sku['item_cost'] * $row['qty'];
        }
        if ($weight > $this->freightWt) { // palletize
            $this->shipment['Qty'] = max(ceil($ttlWt/$this->palletWt), 1);
            $this->shipment['Wt']  = ceil($ttlWt) + ($this->shipment['Qty'] * $this->settings['pallet_weight']);
            $this->guessPallet($sku, $volume, $weight); // recalculate the dims
        } else {
            $this->shipment['Qty'] = max($ttlBox, 1);
            $this->shipment['Wt']  = ceil($ttlWt);
        }
        $this->shipment['Ins'] = ceil($ttlIns);
        msgDebug("\nLeaving guessShipment with this->shipment = ".print_r($this->shipment, true));
    }

    /**
     *
     * @param type $sku
     * @param type $qty
     * @return type
     */
    private function guessWeight($sku, $qty)
    {
        $weight = $qty * floatval($sku['item_weight']); // using the inventory record
        if (!empty($sku['bizProAttr']['invAttrF08'])) {
            $weight = $qty * floatval($sku['bizProAttr']['invAttrF08']); // attr weight exists, use that instead
        }
        msgDebug("\nReturning from guessWeight with weight = $weight");
        return $weight;
    }

    /**
     *
     * @param type $sku
     * @param type $volume
     * @param type $weight
     * @param type $qty
     * @return type
     */
    private function guessBoxes($sku, $volume, $weight, $qty)
    {
        if (empty($volume)) { return; }
        $boxes = max(ceil($volume/$this->volumeDef), 1);
        if (!empty($sku['bizProShip']['box_l'])) { $boxes = max($boxes, ceil($qty/$sku['bizProShip']['box_q'])); } // box_q is 1 by default, but box_l is zero
        else                                     { $boxes = max($boxes, ceil($weight/$this->maxBoxWt), 1); }
        // Take a stab at the box size
        $vol_box = $volume / $boxes;
        if (!empty($sku['bizProShip']['box_l']) && $boxes>1) { $this->shipment['L'] = $sku['bizProShip']['box_l']; }
        else                                                 { $this->shipment['L'] = max(ceil(pow($vol_box, 1/3) * 1.3), 8); }
        if (!empty($sku['bizProShip']['box_w']) && $boxes>1) { $this->shipment['W'] = $sku['bizProShip']['box_w']; }
        else                                                 { $this->shipment['W'] = max(ceil(pow($vol_box, 1/3)      ), 6); }
        if (!empty($sku['bizProShip']['box_h']) && $boxes>1) { $this->shipment['H'] = $sku['bizProShip']['box_h']; }
        else                                                 { $this->shipment['H'] = max(ceil(pow($vol_box, 1/3) / 1.3), 4); }
        return $boxes;
    }

    /**
     *
     * @param type $sku
     * @param type $volume
     * @param type $weight
     */
    private function guessPallet($sku, $volume, $weight)
    {
        $vol_plt = ceil($volume / ($weight / $this->palletWt));
        if (!empty($sku['bizProShip']['plt_l'])) { $this->shipment['L'] = $sku['bizProShip']['plt_l']; }
        else                                     { $this->shipment['L'] = max(ceil(pow($vol_plt, 1/3)), 48); }
        if (!empty($sku['bizProShip']['plt_w'])) { $this->shipment['W'] = $sku['bizProShip']['plt_w']; }
        else                                     { $this->shipment['W'] = max(ceil(pow($vol_plt, 1/3)), 42); }
        if (!empty($sku['bizProShip']['plt_h'])) { $this->shipment['H'] = $sku['bizProShip']['plt_h']; }
        else                                     { $this->shipment['H'] = ceil(pow($vol_plt, 1/3)) + 6; } // 6 inches pallet height
    }

    /**
     *
     * @param type $sku
     * @param type $qty
     * @return type
     */
    private function guessVolume($sku, $qty)
    {
        $length = !empty($sku['bizProAttr']['invAttrF00']) ? floatval($sku['bizProAttr']['invAttrF00']) : 0;
        $width  = !empty($sku['bizProAttr']['invAttrF01']) ? floatval($sku['bizProAttr']['invAttrF01']) : 0;
        $height = !empty($sku['bizProAttr']['invAttrF02']) ? floatval($sku['bizProAttr']['invAttrF02']) : 0;
        $volume = $qty * $length * $width * $height * 1.1; // 1.1 is fudge factor for packaging
        msgDebug("\nReturning from guessVolume with volume = $volume");
        return $volume;
    }

    /**
     * This list is from FedEx and probably should be made more generic. If the carrier doesn't use this the rate estimates may not be accurate.
     * @return type
     */
    private function getOptions()
    {
        return [
            'ltlClasses' => ['0'=>lang('select'), '050'=> '50','055'=> '55','060'=> '60','065'=> '65','070'=> '70','077'=>'77.5','085'=> '85',
                '092'=>'92.5','100'=>'100','110'=>'110','125'=>'125','150'=>'150','175'=>'175','200'=>'200','250'=>'250', '300'=>'300'],
            'extras' => [
                'BLIND_SHIPMENT' => 'BLIND_SHIPMENT',
                'BROKER_SELECT_OPTION' => 'BROKER_SELECT_OPTION',
                'CALL_BEFORE_DELIVERY' => 'CALL_BEFORE_DELIVERY',
                'COD' => 'COD',
                'COD_REMITTANCE' => 'COD_REMITTANCE',
                'CUSTOM_DELIVERY_WINDOW' => 'CUSTOM_DELIVERY_WINDOW',
                'CUT_FLOWERS' => 'CUT_FLOWERS',
                'DANGEROUS_GOODS' => 'DANGEROUS_GOODS',
                'DELIVERY_ON_INVOICE_ACCEPTANCE' => 'DELIVERY_ON_INVOICE_ACCEPTANCE',
                'DETENTION' => 'DETENTION',
                'DO_NOT_BREAK_DOWN_PALLETS' => 'DO_NOT_BREAK_DOWN_PALLETS',
                'DO_NOT_STACK_PALLETS' => 'DO_NOT_STACK_PALLETS',
                'DRY_ICE' => 'DRY_ICE',
                'EAST_COAST_SPECIAL' => 'EAST_COAST_SPECIAL',
                'ELECTRONIC_TRADE_DOCUMENTS' => 'ELECTRONIC_TRADE_DOCUMENTS',
                'EVENT_NOTIFICATION' => 'EVENT_NOTIFICATION',
                'EXCLUDE_FROM_CONSOLIDATION' => 'EXCLUDE_FROM_CONSOLIDATION',
                'EXCLUSIVE_USE' => 'EXCLUSIVE_USE',
                'EXHIBITION_DELIVERY' => 'EXHIBITION_DELIVERY',
                'EXHIBITION_PICKUP' => 'EXHIBITION_PICKUP',
                'EXPEDITED_ALTERNATE_DELIVERY_ROUTE' => 'EXPEDITED_ALTERNATE_DELIVERY_ROUTE',
                'EXPEDITED_ONE_DAY_EARLIER' => 'EXPEDITED_ONE_DAY_EARLIER',
    //          'EXPEDITED_SERVICE_MONITORING_AND_DELIVERY EXPEDITED_STANDARD_DAY_EARLY_DELIVERY' => 'EXPEDITED_SERVICE_MONITORING_AND_DELIVERY',
                'EXTRA_LABOR' => 'EXTRA_LABOR',
                'EXTREME_LENGTH' => 'EXTREME_LENGTH',
                'FEDEX_ONE_RATE' => 'FEDEX_ONE_RATE',
                'FLATBED_TRAILER' => 'FLATBED_TRAILER',
                'FOOD' => 'FOOD',
                'FREIGHT_GUARANTEE' => 'FREIGHT_GUARANTEE',
                'FREIGHT_TO_COLLECT' => 'FREIGHT_TO_COLLECT',
                'FUTURE_DAY_SHIPMENT' => 'FUTURE_DAY_SHIPMENT',
                'HOLD_AT_LOCATION' => 'HOLD_AT_LOCATION',
                'HOLIDAY_DELIVERY' => 'HOLIDAY_DELIVERY',
                'HOLIDAY_GUARANTEE' => 'HOLIDAY_GUARANTEE',
                'HOME_DELIVERY_PREMIUM' => 'HOME_DELIVERY_PREMIUM',
                'INSIDE_DELIVERY' => 'INSIDE_DELIVERY',
                'INSIDE_PICKUP' => 'INSIDE_PICKUP',
                'INTERNATIONAL_CONTROLLED_EXPORT_SERVICE' => 'INTERNATIONAL_CONTROLLED_EXPORT_SERVICE',
                'INTERNATIONAL_MAIL_SERVICE' => 'INTERNATIONAL_MAIL_SERVICE',
    //          'INTERNATIONAL_TRAFFIC_IN_ARMS_REGULATIONS' => 'INTERNATIONAL_TRAFFIC_IN_ARMS_REGULATIONS',
                'LIFTGATE_DELIVERY' => 'LIFTGATE_DELIVERY',
                'LIFTGATE_PICKUP' => 'LIFTGATE_PICKUP',
                'LIMITED_ACCESS_DELIVERY' => 'LIMITED_ACCESS_DELIVERY',
                'LIMITED_ACCESS_PICKUP' => 'LIMITED_ACCESS_PICKUP',
                'MARKING_OR_TAGGING' => 'MARKING_OR_TAGGING',
                'NON_BUSINESS_TIME' => 'NON_BUSINESS_TIME',
                'PALLET_SHRINKWRAP' => 'PALLET_SHRINKWRAP',
                'PALLET_WEIGHT_ALLOWANCE' => 'PALLET_WEIGHT_ALLOWANCE',
                'PALLETS_PROVIDED' => 'PALLETS_PROVIDED',
                'PENDING_COMPLETE' => 'PENDING_COMPLETE',
                'PENDING_SHIPMENT' => 'PENDING_SHIPMENT',
                'PERMIT' => 'PERMIT',
                'PHARMACY_DELIVERY' => 'PHARMACY_DELIVERY',
                'POISON' => 'POISON',
                'PORT_DELIVERY' => 'PORT_DELIVERY',
                'PORT_PICKUP' => 'PORT_PICKUP',
                'PRE_DELIVERY_NOTIFICATION' => 'PRE_DELIVERY_NOTIFICATION',
                'PRE_EIG_PROCESSING' => 'PRE_EIG_PROCESSING',
                'PRE_MULTIPLIER_PROCESSING' => 'PRE_MULTIPLIER_PROCESSING',
                'PROTECTION_FROM_FREEZING' => 'PROTECTION_FROM_FREEZING',
                'REGIONAL_MALL_DELIVERY' => 'REGIONAL_MALL_DELIVERY',
                'REGIONAL_MALL_PICKUP' => 'REGIONAL_MALL_PICKUP',
                'RETURN_SHIPMENT' => 'RETURN_SHIPMENT',
                'RETURNS_CLEARANCE' => 'RETURNS_CLEARANCE',
    //          'RETURNS_CLEARANCE_SPECIAL_ROUTING_REQUIRED SATURDAY_DELIVERY' => 'RETURNS_CLEARANCE_SPECIAL_ROUTING_REQUIRED',
                'SATURDAY_PICKUP' => 'SATURDAY_PICKUP',
                'SHIPMENT_ASSEMBLY' => 'SHIPMENT_ASSEMBLY',
                'SORT_AND_SEGREGATE' => 'SORT_AND_SEGREGATE',
                'SPECIAL_DELIVERY' => 'SPECIAL_DELIVERY',
                'SPECIAL_EQUIPMENT' => 'SPECIAL_EQUIPMENT',
                'STORAGE' => 'STORAGE',
                'SUNDAY_DELIVERY' => 'SUNDAY_DELIVERY',
                'THIRD_PARTY_CONSIGNEE' => 'THIRD_PARTY_CONSIGNEE',
                'TOP_LOAD' => 'TOP_LOAD',
                'USPS_DELIVERY' => 'USPS_DELIVERY',
                'USPS_PICKUP' => 'USPS_PICKUP',
                'WEIGHING' => 'WEIGHING']];
    }
}