<?php
/*
 * API Export controller
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
 * @version    7.x Last Update: 2026-01-14
 * @filesource /controllers/api/export.php
 */

namespace bizuno;

class apiExport
{
    public $moduleID= 'api';
    public $pageID  = 'export';

    function __construct() { }

    public function apiInventory(&$product=[])
    {
        $rID = clean($product['RecordID'], 'integer');
        if (!$rID) { return msgDebug("\nBad ID passed. Needs to be the inventory field id tag name (RecordID)."); }
        setSecurityOverride('prices_c', 1);
        setSecurityOverride('j12_mgr', 1);
        $pDetails['args'] = ['iID'=>$rID];
        compose('inventory', 'prices', 'quote', $pDetails);
        $product['Price'] = $pDetails['content']['price'];
        if (!empty($pDetails['content']['regular_price'])){ $product['RegularPrice']= $pDetails['content']['regular_price']; }
        if (!empty($pDetails['content']['sale_price']))   { $product['SalePrice']   = $pDetails['content']['sale_price']; }
        if ( isset($pDetails['content']['levels']) && sizeof($pDetails['content']['levels']) > 0) { $product['PriceLevels'] = $pDetails['content']['levels']; }
        $product['WeightUOM']   = getModuleCache('inventory', 'settings', 'general', 'weight_uom','LB');
        $product['DimensionUOM']= getModuleCache('inventory', 'settings', 'general', 'dim_uom',   'IN');
        $this->getImage($product);
        $this->getAccessories($product);
        $this->getAttributes($product);
    }
    private function getImage(&$product)
    {
        global $io;
        $product['Images'] = [];
        $product['ProductImageData'] = $product['ProductImageDirectory'] = $product['ProductImageFilename'] = '';
        if (!empty($product['Image'])) { // primary image file
            $info = pathinfo($product['Image']);
            $data = $io->fileRead("images/{$product['Image']}"); // will flag error if file is not there, keep this to fix db
            $product['ProductImageData']     = $data ? base64_encode($data['data']) : '';
            $product['ProductImageDirectory']= $info['dirname']."/";
            $product['ProductImageFilename'] = $info['basename'];
        }
        if (empty($product['invImages'])) { return; }
        $images = json_decode($product['invImages'], true);
        foreach ($images as $image) { // invImages extension list
            $info = pathinfo($image);
            $data = $io->fileRead("images/$image");
            if (!empty($data)) {
                $product['Images'][] = ['Title'=>$info['basename'], 'Path'=>$info['dirname']."/", 'Filename'=>$info['basename'], 'Data'=>$data ? base64_encode($data['data']) : ''];
            } else {
                msgAdd("\nI can't find the image for sku: {$product['SKU']}. Please fix this in the Inventory Manager editor for this SKU in the Images tab and saving the record.");
            }
        }
    }
    private function getAccessories(&$product)
    {
        if (isset($product['invAccessory'])) {
            $vals = json_decode($product['invAccessory'], true);
            if (!is_array($vals)) { return; }
            unset($product['invAccessory']);
            foreach ($vals as $rID) {
                $product['invAccessory'][] = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'sku', "id=$rID");
            }
        }
    }
    private function getAttributes(&$product)
    {
        msgDebug("\nEntering api:export:getAttributes");
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

    public function apiSync(&$layout=[])
    {
        $skus  = [];
        if (!isset($layout['data']['syncSkus'])) { $layout['data']['syncSkus'] = []; }
        $layout['data']['syncDelete'] = clean('syncDelete', 'integer', 'get');
        $field = !empty($layout['data']['syncTag']) ? $layout['data']['syncTag'] : 'cart_sync';
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "`$field`='1'"); //  AND inactive='0'
        foreach ($result as $row) { $skus[] = $row['sku']; }
        $layout['data']['syncSkus'] = json_encode($skus);
        msgDebug("\nSending aipSync content = ".print_r($layout, true));
    }

    /**
     * Takes package information in and returns the Bizuno enabled shipping rates and services
     * @param array $layout - Structure coming in ($layout['pkg'] is where the information is)
     */
    public function shippingRates(&$layout=[])
    {
        msgDebug("\nEntering export/shippingRates with layout = ".print_r($layout, true));
        $pkg = [
            'destination' => [
                'address1'   => $layout['pkg']['destination']['address'],
                'address2'   => $layout['pkg']['destination']['address_1'],
                'city'       => $layout['pkg']['destination']['city'],
                'state'      => $layout['pkg']['destination']['state'],
                'postal_code'=> $layout['pkg']['destination']['postcode'],
                'country'    => clean($layout['pkg']['destination']['country'], ['format'=>'country','option'=>'ISO2'])],
            'settings' => [
                'ship_date'  => date('Y-m-d'),
                'order_total'=> !empty($layout['pkg']['cart_subtotal']) ? $layout['pkg']['cart_subtotal'] : 0,
                'weight'     => !empty($layout['pkg']['destination']['totalWeight']) ? $layout['pkg']['destination']['totalWeight'] : 1,
//              'length'     => clean('length',     'float',  'post'),
//              'width'      => clean('width',      'float',  'post'),
//              'height'     => clean('height',     'float',  'post'),
                'num_boxes'  => 1, // Assume 1 for now as dims may not be entered
                'ltl_class'  => 60, // clean('ltl_class',  'text',   'post'),
                'residential'=> 1,  // clean('residential','boolean','post'),
                'verify_add' => true]];
//        $pkg['ship']['country2'] = $layout['pkg']['destination']['country'];
//        $pkg['ship']['country3'] = $pkg['ship']['country'];
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/shipping/rate.php', 'shippingShip');
        $quote = new shippingRate();
        $quote->fieldsAddress($pkg, ['suffix'=>'_o','cID'=>0]); // origin
        $quote->fieldsAddress($pkg, ['suffix'=>'_s','cID'=>0]); // shipper
        $quote->fieldsAddress($pkg, ['suffix'=>'_p','cID'=>0]); // payor
//        $quote->fieldsAddress($pkg, ['suffix'=>'_s','src'=>'post']); // destination
        msgDebug("\ncalling rateAPI with pkg = ".print_r($pkg, true));
        $layout['rates'] = $quote->rateAPI($pkg);
    }

    /**
     * Install common fields into inventory db table shared amongst all the interfaces
     */
    protected function installStoreFields()
    {
        $id = validateTab('inventory', lang('estore'), 80);
        if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'msrp'))             { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD msrp DOUBLE NOT NULL DEFAULT '0' COMMENT 'label:Mfg Suggested Retail Price;tag:MSRPrice;tab:$id;order:42'"); }
        // All of these fields have been moved to attributes or removed. Used for customization only.
//      if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'description_long')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD description_long TEXT COMMENT 'type:textarea;label:Long Description;tag:DescriptionLong;tab:$id;order:10'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'manufacturer'))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD manufacturer VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'label:Manufacturer;tag:Manufacturer;tab:$id;order:40'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'model'))            { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD model VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'label:Model;tag:Model;tab:$id;order:41'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'meta_keywords'))    { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD meta_keywords VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'label:Meta Keywords;tag:MetaKeywords;tab:$id;o rder:90;group:General'"); }
//      if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', 'meta_description')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD meta_description VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'label:Meta Description;tag:MetaDescription;tab:$id;order:91;group:General'"); }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/admin.php', 'inventoryAdmin');
        $inv = new inventoryAdmin();
        $inv->installPhysicalFields();
    }
}
