<?php
/*
 * @name Bizuno ERP - Google Interface Extension
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
 * @version    7.x Last Update: 2025-11-22
 * @filesource /controllers/api/funnels/ifGoogle/ifGoogle.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/api/export.php', 'apiExport');

class ifGoogle extends apiExport
{
    public $moduleID = 'api';
    public $methodDir= 'funnels';
    public $code     = 'ifGoogle';
    public $defaults;
    public $settings;
    public $map;
    public $lang     = ['title' => 'Google Interface',
        'acronym' => 'Google',
        'description' => 'The Google interface provides capability to upload product feeds.',
        // settings
        'feed_fn_lbl' => 'Feed Filename',
        'feed_fn_tip' => 'Google feed filename (as registered with Google Merchant)',
        'ftp_url_lbl' => 'FTP URL',
        'ftp_url_tip' => 'FTP URL to send feeds',
        'ftp_port_lbl' => 'FTP Port',
        'ftp_port_tip' => 'FTP port to send feeds',
        'ftp_user_lbl' => 'FTP Username',
        'ftp_pass_lbl' => 'FTP Password',
        'build_inventory' => 'Generate Inventory Feed',
        'upload_to_google' => 'Upload to Google via FTP',
        'build_inventory_desc' => 'Press Go to to build your Google Shopping product feed file and download it to your computer. From there it can be uploaded to Google through Google Merchant.',
        'err_no_inv_sku' => 'Item %s does not have an assigend GTIN or UPC! It will be skipped.',
        'err_sku_no_weight' => 'SKU %s has no weight. Please edit the inventory record and add a non-zero weight!',
        'err_missing_image' => 'Image at path: %s for sku: %s must be of type jpg or gif for amazon!',
        'err_no_inv_rows' => 'No inventory items found to be uploaded!',
        'err_inv_no_price' => 'No price was determined for SKU: %s',
        'err_feed_needs_fix' => 'There are errors above in your feed file, these SKUs will not be sent to Google.'];

    function __construct()
    {
        parent::__construct();
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $this->defaults= ['feed_fn'=>'google_products.txt','ftp_url'=>'uploads.google.com','ftp_port'=>21,'ftp_user'=>'','ftp_pass'=>''];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
        // the following map should be placed in a new file but for now, do it this way. Some fields may be custom in the DB and names may differ
        $this->map     = [ // format 'google_field' => 'bizuno_field',
            // Basic Product Information
            'id'                     => 'sku', // identifier for each item has to be unique within your account
            'title'                  => 'description_short', // This is the name of your item which is required
            'description'            => 'description_sales', // Describe its most relevant attributes
            'google_product_category'=> 'google_category', // category of the product being submitted, see https://support.google.com/merchants/answer/1705911
            // json version: "googleProductCategory": "Electronics > Electronics Accessories > Power > Batteries"
            'product_type'           => 'google_category', // indicates the category of the product being submitted
            'link'                   => 'description_short', // The user is sent to this URL when your item is clicked on Google Shopping
            'mobile_link'            => 'description_short', // links to mobile-optimized versions of the landing pages
            'image_link'             => 'image_with_path', // This is the URL of the main image for a product
            'additional_image_link'  => '', // If you have additional images for this item, include them in this attribute
            'condition'              => 'condition', // Choices are: new, used, refurbished
            // Availability & Price
            'availability'           => 'qty_stock', // Choice are: in stock, out of stock, preorder
//            'availability_date'            => '', // for preorder, use this attribute to indicate when the product becomes available for delivery
            'price'                  => 'full_price', //
//            'sale_price'                => '', // advertised sale price of the item
//            'sale_price_effective_date' => '', // the date range during which the sale price applies
            // Unique Product Identifiers
            'brand'                  => 'manufacturer', // Required according to the Unique Product Identifier Rules
            'gtin'                   => 'upc', // Global Trade Item Numbers (GTINs), 8-, 12-, or 13-digit number (UPC, EAN, JAN, or ISBN)
            'mpn'                    => 'model', // A Manufacturer Part Number is used to reference and identify a product
//            'identifier_exists'            => '', // value of FALSE when the item does not have unique product identifiers appropriate to its category, such as GTIN, MPN, and brand.
            // Apparel Products
//            'gender'                    => '', //
//            'age_group'                    => '', //
//            'color'                        => '', //
//            'size'                        => '', //
//            'size_type'                    => '', //
//            'size_system'                => '', //
            // Product Variants
//            'item_group_id'                => '', // All items that are color/material/pattern/size variants of the same product must have the same item group id
//            'color'                        => '', //
//            'material'                    => '', //
//            'pattern'                    => '', //
//            'size'                        => '', //
            // Tax & Shipping
//            'tax'                        => 'sales_taxable', // see google feed for formats, e.g. US:CO:2.9,US:80241:8, BEST WAY: Can be set at Google to have them calculate
//            'shipping'                    => 'price_shipping', // see google feed for formats, BEST WAY: Create default shipping table at Google
            'shipping_weight'        => 'item_weight', // We accept only the following units of weight: lb, oz, g, kg
            'shipping_length'        => 'pkg_length',
            'shipping_width'         => 'pkg_width',
            'shipping_height'        => 'pkg_height',
            'shipping_label'         => '',
            // Product Combinations
//            'multipack'                    => '', //
//            'is_bundle'                    => '', //
//            'adult'                     => '',
            // AdWords Attributes
//            'adwords_redirect'            => '', //
            // Custom Label Attributes for Shopping Campaigns
//            'custom_label_0'            => '', // custom_label_0 - custom_label_4
            // Additional Attributes
//            'excluded_destination'        => '', //
//            'expiration_date'            => '', //
//            // Unit prices (US Only)
//            'unit_pricing_measure'        => '', //
//            'unit_pricing_base_measure' => '', //
            // Merchant Promotions Attribute
//            'promotion_id'                => '', //
        ];
    }

    public function settingsStructure()
    {
        $data = [
            'feed_fn' => ['attr'=>['value'=>$this->settings['feed_fn']]],
            'ftp_url' => ['attr'=>['value'=>$this->settings['ftp_url']]],
            'ftp_port'=> ['attr'=>['value'=>$this->settings['ftp_port']]],
            'ftp_user'=> ['attr'=>['value'=>$this->settings['ftp_user']]],
            'ftp_pass'=> ['attr'=>['value'=>$this->settings['ftp_pass']]]];
        foreach (array_keys($data) as $key) {
            $data[$key]['label'] = !empty($this->lang[$key."_lbl"]) ? $this->lang[$key."_lbl"] : $key;
            if (!empty($this->lang[$key."_tip"])) { $data[$key]['tip'] = $this->lang[$key."_tip"]; }
        }
        return $data;
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function home(&$layout=[])
    {
        if (!$security = validateAccess('ifGoogle', 1)) { return; }
        $data = ['title'=> $this->lang['title'],
            'divs'=>[
                'head'    => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR'  => ['order'=> 2,'type'=>'html',  'html'=>"<br />"],
                'manager' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setInv'  => ['order'=>20,'type'=>'panel','key'=>'setInv','classes'=>['block33']]]]],
            'panels' => [
                'setInv' => ['label'=>$this->lang['build_inventory'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmInventory'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['build_inventory_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['btnInv']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms'  => ['frmInventory'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/inventoryGo&modID=ifGoogle&dl=1"]]],
            'fields' => [
                'imgLogo'=> ['attr'  =>['type'=>'img','height'=>60,'src'=>BIZUNO_URL_FS.'0/controllers/api/funnels/ifGoogle/google_large.png']],
                'btnInv' => ['events'=>['onClick'=>"jqBiz('#frmInventory').submit();"],'attr'=>['type'=>'button','value'=>lang('go')]]],
            'jsReady'=> ['init'    =>"ajaxDownload('frmInventory');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function invManager(&$layout=[])
    {
        if (!validateAccess('ifGoogle', 3, false)) { return; }
        $layout['datagrid']['manager']['source']['actions']['googleUpload'] = ['order'=>55,'icon'=>'ifGoogle','label'=>$this->lang['upload_to_google'],'events'=>['onClick'=>"jsonAction('$this->moduleID/admin/inventoryGo&modID=ifGoogle');"]];
    }

    /**
     *
     * @global type $io
     * @param type $layout
     * @return type
     */
    public function inventoryGo(&$layout=[]) {
        global $io;
        $dl    = clean('dl', 'integer', 'get');
        $rows  = [];
        $result= dbGetMulti(BIZUNO_DB_PREFIX."inventory", "inactive='0' AND google_sync='1'", 'sku');
        foreach ($result as $item) {
            $incomplete = false;
            $aItem = [];
            foreach ($this->map as $idx => $attr) {
                switch ($idx) { // error check required fields
                    case 'availability':
                        if (in_array($item['inventory_type'], ['ma', 'sa'])) { // for assemblies, see how many we can build
                            $result = getMetaInventory($item['sku'], 'bill_of_materials');
                            foreach ($result as $row) { $min_qty = empty($row['qty']) ? 0 : min($min_qty, floor($item['qty_stock'] / $row['qty'])); }
                            $item['qty_stock'] = $min_qty;
                        }
                        $available = $item['qty_stock'] - $item['qty_so'] - $item['qty_alloc'];
                        $aItem[$idx] =  $available > 0 ? 'in stock' : 'out of stock';
                        // if not available, calculate estmated date
//                      if ($aItem[$idx] == 0) { $aItem['availability_date'] = localeCalculateDate(biz_date('Y-m-d'), $item['lead_time']); }
                        break;
                    case 'condition':
                        $aItem[$idx] = 'new';
                        break;
                    case 'google_product_category':
                        $aItem[$idx] = $item['google_category']; // "Electronics > Electronics Accessories > Power > Batteries";
                        break;
                    case 'gtin':
                        if (!$attr) { msgAdd(sprintf($this->lang['err_no_inv_sku'], $item['sku'])); $incomplete = true; }
                        else {
                            $aItem[$idx] = $item['upc_code'];
                            $aItem['identifier_exists'] = $item['upc_code'] ? 'true' : '';
                        }
                        break;
                    case 'image_link':
                        $aItem[$idx] = BIZUNO_URL_FS.getUserCache('business', 'bizID')."/images/{$item['image_with_path']}";
                        break;
                    case 'link':
                    case 'mobile_link':
                        $imgCleaned = clean($item['description_short'], 'filename');
                        $website = strtolower(getModuleCache('bizuno', 'settings', 'company', 'website'));
                        if (strpos($website, 'http') === false) { $website = 'http://'.$website; }
                        $aItem[$idx] = "$website/$imgCleaned";
                        break;
                    case 'price':
                        $price['args'] =['iID'=>$item['id']];
                        compose('inventory', 'prices', 'quote', $price);
                        $aItem[$idx] = $layout['content']['price'].' '.getDefaultCurrency();
//                        $aItem[$idx]['value']   = isset($layout['content']['price']) ? $layout['content']['price'] : 0;
//                        $aItem[$idx]['currency']= getDefaultCurrency();
                        if (!$layout['content']['price']) { msgAdd(sprintf($this->lang['err_inv_no_price'], $item['sku'])); break; }
                        break;
                    case 'shipping':
//                        $aItem[$idx] = ''; // handled at Google through shared shipping tables
                        break;
                    case 'shipping_weight': // shippingWeight
                        if ($item['item_weight'] == 0) { msgAdd(sprintf($this->lang['err_sku_no_weight'], $item['sku'])); break; }
                        $aItem[$idx] = $item['item_weight'].' '.$item['weight_uom'];
                        break;
                    case 'shipping_label': $aItem[$idx] = 'Non-spillable battery, Non-hazardous'; break;
                    case 'tax': // e.g. US:CO:2.9,US:80241:8
//                        $aItem[$idx] = 'US:CO:4.0,US:80241:8.5'; // handled through Google calculations
                        break;
                    default:
                        $aItem[$idx] = $attr && isset($item[$attr]) ? str_replace(["\r","\n","\t"], ' ', $item[$attr]) : '';
                }
            }
            if (!$incomplete) { $rows[] = $aItem; }
        }
        if (sizeof($rows) == 0) { return msgAdd("result = ".sizeof($result)." and: ".$this->lang['err_no_inv_rows']); }
        if (msgErrors()) { msgAdd($this->lang['err_feed_needs_fix']); }
        $output = [];
        $cnt = 0;
        foreach ($rows as $row) {
            if (sizeof($output) == 0) { $output[] = implode("\t", array_keys($row)); } // set the header
            $output[] = implode("\t", $row);
            $cnt++;
        }
        // Write the file
        $settings = $this->settings['general'];
//      msgAdd("Ready to write to: temp/{$settings['feed_fn']} at url {$settings['ftp_url']} to port {$settings['ftp_port']} with user {$settings['ftp_user']}", 'caution');
        if ($dl) { // download the data
            $io->download('data', implode("\n", $output), $settings['feed_fn'], true);
        } else {
            $io->fileWrite(implode("\n", $output), "temp/".$settings['feed_fn'], true, false, true);
            // Send the data
/* sftp needs debugging and don't forget to install php library
            if (!$io->sftp_connect($settings['sftp_url'], $settings['sftp_port'])) { return; }
            return msgAdd("successfully connected to Google Server");
            if (!$io->sftp_login($settings['sftp_user'], $settings['sftp_pass'])) { return; }
            return msgAdd("successfully logged into Google Server");
            if (!$io->sftp_uploadFile("temp/{$settings['feed_fn']}", "temp/".$settings['feed_fn'])) { return; }
*/
            if (!$con = $io->ftpConnect($settings['ftp_url'], $settings['ftp_user'], $settings['ftp_pass'], $settings['ftp_port'])) { return; }
            if (!$io->ftpUploadFile($con, "temp/".$settings['feed_fn'])) { return; }
            $io->fileDelete("temp/".$settings['feed_fn']);
            msgLog("Google inventory feed file generated.");
            msgAdd("Uploaded $cnt items to Google.", 'success');
        }
    }

    /**
     * Adds fields to product table that are needed for this interface
     * @return true if successful
     */
    function install()
    {
        $lbl = sprintf($this->lang['cart_sync'],$this->lang['acronym']);
        $cat = sprintf($this->lang['cart_cat'], $this->lang['acronym']);
        $id  = validateTab('inventory', lang('estore'), 90);
        if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'google_sync'))    {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD google_sync ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;label:$lbl;tag:GoogleSync;tab:$id;order:23;group:General'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'google_category')){
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD google_category VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'label:$cat;tag:GoogleCategory;tab:$id;order:24;group:General'");
        }
        parent::installStoreFields();
        return true;
    }

    /**
     * Remove all fields and revert install changes.
     * @return - true if successful
     */
    function remove()
    {
//        if (dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'google_sync'))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP google_sync"); }
//        if (dbFieldExists(BIZUNO_DB_PREFIX."inventory", 'google_category')) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP google_category"); }
//        parent::removeStoreFields();
        return true;
    }
}
