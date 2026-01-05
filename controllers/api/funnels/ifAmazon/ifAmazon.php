<?php
/*
 * @name Bizuno ERP - Amazon Interface Extension
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
 * @version    7.x Last Update: 2026-01-05
 * @filesource /controllers/api/funnels/ifAmazon/ifAmazon.php
 */

namespace bizuno;

class ifAmazon {
    public $moduleID = 'api';
    public $methodDir= 'funnels';
    public $code     = 'ifAmazon';
    public $defaults;
    public $settings;
    public $lang = ['title' => 'Amazon Interface',
        'description' => 'The Amazon interface provides capability to download orders, upload product feeds and help reconcile payments.',
        'amazon_field' => 'Amazon Feed Index',
        'bizuno_field' => 'Bizuno Inventory Field',
        'amazon_post_success' => 'Successfully posted %u amazon orders!',
        'build_inventory' => 'Generate Inventory Feed',
        'import_orders' => 'Import Orders',
        'confirm_shipments' => 'Confirm Shipments',
        'import_payment' => 'Process Payment File',
        'amazon_sales_orders' => 'Open Amazon Sales Orders',
        'build_inventory_desc' => 'Select a Map file and press Go to to build your Amazon product upload feed file and download it to your computer. From there it can be uploaded to Amazon through Seller Central. If no feed files are listed, you need to build one through Module Administration -> Amazon Interface (Settings) -> Amazon Templates.',
        'import_orders_desc' => 'Select order file to import from Amazon and press Go:',
        'confirm_shipments_desc' => 'Select the date to build the Amazon ship confirmation on and press Go:',
        'import_payment_desc' => 'Please select the Amazon payment file to process:',
        'msg_confirm_success' => 'Amazon order confirmation generated.',
        'msg_template_created' => 'Your template file has been written, you may now assign Bizuno fields to the Amazon fields.',
        'msg_order_long_data' => 'Order: %s, Field: %s, Text: %s',
        'err_no_inv_map' => 'Cannot find the Amazon map file for template: %s',
        'err_no_inv_sku' => 'Missing UPC code and Amazon ASIN ID for SKU: %s',
        'err_no_inv_tpl' => 'Cannot find amazon template file for template: %s',
        'err_no_contact' => "Could not find Amazon contact ID, please make sure you have selected a customer contact in Module Administration -> Amazon Module settings.",
        'err_dup_order' => 'Amazon order # %s is already posted to Bizuno, it will be skipped!',
        'err_confirm_no_contact' => 'Contact ID/Ship date could not be found, no file was generated!',
        'err_no_confirm_found' => 'No valid Amazon orders have been shipped on the date selected!',
        'err_sku_no_weight' => 'SKU %s has no weight. Please edit the inventory record and add a non-zero weight!',
        'err_missing_image' => 'Image at path: %s for sku: %s must be of type jpg or gif for amazon!',
        'err_no_inv_rows' => 'No inventory items found to be uploaded!',
        'err_inv_no_price' => 'No price was determined for SKU: %s',
        'err_feed_needs_fix' => 'There are errors in your feed file, please fix them before submitting your feed to Amazon.',
        // settings
        'contact_id_lbl'   => 'Customer ID',
        'catalog_field_lbl'=> 'Inv Link Field',
        'price_sheet_lbl'  => 'Amazon Price Sheet',
        'ship_std_lbl'     => 'Standard Method',
        'ship_exp_lbl'     => 'Expedited Method',
        'gift_wrap_sku_lbl'=> 'Gift Wrap SKU',
        'notes_sku_lbl'    => 'Notes SKU',
        'gl_acct_sales_lbl'=> 'Sales GL Account',
        'gl_acct_ar_lbl'   => 'AR GL Account',
        'gl_acct_sales_lbl'=> 'Sales GL Account',
        'gl_acct_tax_lbl'  => 'Sales Tax GL Account',
        'gl_acct_disc_lbl' => 'Discount GL Account',
        'gl_acct_ship_lbl' => 'Freight GL Account',
        'auto_journal_lbl' => 'Post Type',
        'contact_id_tip'   => 'Determines the Customer contact ID to assign all Amazon sales.',
        'catalog_field_tip'=> 'Determines which database field name to use to select items to be uploaded to Amazon, typically a checkbox type',
        'price_sheet_tip'  => 'Determines which price sheet to use to determine the item price',
        'ship_std_tip'     => 'Freight carrier/method to use for Standard Shipments',
        'ship_exp_tip'     => 'Freight carrier/method to use for Expedited Shipments',
        'gift_wrap_sku_tip'=> 'SKU to use for Gift Wrap option',
        'notes_sku_tip'    => 'SKU to use for order notes',
        'gl_acct_sales_tip'=> 'GL Account to use for recording sales',
        'gl_acct_tax_tip'  => 'GL Account to use for recording sales tax',
        'gl_acct_disc_tip' => 'GL Account to use for recording sales discounts',
        'gl_acct_ship_tip' => 'GL Account to use for recording freight charges',
        'auto_journal_tip' => 'Determines how to post each sale, choices are Sales Orders, Sales, or Auto (Sales if in stock, Sales Order if not)',
        'amazon_maps'      => 'Amazon Templates',
        'walmart_maps'     => 'Walmart Templates',
        'amazon_template_desc' => 'Amazon templates are how your Bizuno inventory database fields map to Amazon fields.
            Creating these templates is required for a successful feed to Amazon. To create a template, select the category
            from the pull down and press the `Create Template` button. This will load the fields available to send to Amazon and create a list below.
            You will need to assign a Bizuno field to all the required amazon fields. The preferred and optional enhance your product descriptions.
            Once your fields have been assigend, press the Save icon to save you changes.
            You should now be able to create upload feeds to amazon through the Customer -> Amazon Interface menu.'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $this->defaults= ['contact_id'=>0,'catalog_field'=>'amazon','ship_std'=>0,'ship_exp'=>0,'gift_wrap_sku'=>'','notes_sku'=>'','auto_journal'=>0,
            'gl_acct_sales'=>getModuleCache('phreebooks','settings','customers','gl_sales'),
            'gl_acct_ar'   =>getModuleCache('phreebooks','settings','customers','gl_receivables'),
            'gl_acct_tax'  =>getModuleCache('phreebooks','settings','vendors',  'gl_liability'),
            'gl_acct_disc' =>getModuleCache('phreebooks','settings','customers','gl_discount'),
            'gl_acct_ship' =>getModuleCache('shipping',  'settings','customers','gl_shipping_c') ? getModuleCache('shipping', 'settings', 'customers', 'gl_shipping_c') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales')];
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        $autoJID = [
            ['id'=>'0', 'text'=>lang('auto_detect')],
            ['id'=>'10','text'=>lang('journal_id_10')],
            ['id'=>'12','text'=>lang('journal_id_12')]];
        $choices = [['id'=>'', 'text'=>lang('select')]];
        $meta = getMetaMethod('carriers');
        if (sizeof($meta)) { 
            foreach ($meta as $settings) {
                if (!empty($settings['status']) && !empty($settings['settings']['services'])) { $choices = array_merge_recursive($choices, $settings['settings']['services']); }
            }
        }
        $data = [
            'contact_id'   => ['defaults'=>['type'=>'c'],'attr'=>['type'=>'contact','value'=>$this->settings['contact_id']]],
            'catalog_field'=> ['events'=>['onClick'=>"amazonFields('general_catalog_field')"], 'attr'=>['value'=>$this->settings['catalog_field']]],
            'ship_std'     => ['values'=>$choices, 'attr'=>['type'=>'select', 'value'=>$this->settings['ship_std']]],
            'ship_exp'     => ['values'=>$choices, 'attr'=>['type'=>'select', 'value'=>$this->settings['ship_exp']]],
            'gift_wrap_sku'=> ['events'=>['onClick'=>"amazonSKU();"],'attr'=>['value'=>$this->settings['gift_wrap_sku']]],
            'notes_sku'    => ['events'=>['onClick'=>"amazonSKU();"],'attr'=>['value'=>$this->settings['notes_sku']]],
            'gl_acct_sales'=> ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_sales','value'=>$this->settings['gl_acct_sales']]],
            'gl_acct_ar'   => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ar',   'value'=>$this->settings['gl_acct_ar']]],
            'gl_acct_tax'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_tax',  'value'=>$this->settings['gl_acct_tax']]],
            'gl_acct_disc' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_disc', 'value'=>$this->settings['gl_acct_disc']]],
            'gl_acct_ship' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ship', 'value'=>$this->settings['gl_acct_ship']]],
            'auto_journal' => ['values'=>$autoJID, 'attr'=>['type'=>'select', 'value'=>$this->settings['auto_journal']]]];
        foreach (array_keys($data) as $key) {
            $data[$key]['label'] = !empty($this->lang[$key."_lbl"]) ? $this->lang[$key."_lbl"] : $key;
            if (!empty($this->lang[$key."_tip"])) { $data[$key]['tip'] = $this->lang[$key."_tip"]; }
        }
        return $data;
    }

    /**
     * Home landing page for this module
     * @param array $layout - Structure coming in
     * @return modified $layout
     */
    public function home(&$layout=[])
    {
        if (!$security = validateAccess('ifAmazon', 1)) { return; }
        $this->journalMainSaveDefaults();
        $maps = [];
        $files = glob(BIZUNO_DATA."data/ifAmazon/*.map");
        if (is_array($files)) { foreach ($files as $value) {
            $tmp1 = str_replace(".map", "", $value);
            $tmp2 = str_replace(BIZUNO_DATA."data/ifAmazon/", "", $tmp1);
            $maps[] = ['id'=>$tmp2, 'text'=>$tmp2];
        } }
        $fields = [
            'imgLogo'     => ['styles' =>['cursor'=>'pointer'], 'events' =>['onClick'=>"winHref('https://sellercentral.amazon.com');"],'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/logo.png"]],
            'selMap'      => ['values' =>$maps, 'attr'=> ['type'=>'select']],
            'btnInventory'=> ['events' =>['onClick'=>"jqBiz('#frmInventory').submit();"],'attr'=>['type'=>'button','value'=>lang('go')]],
            'fileOrders'  => ['attr'   =>['type'=>'file']],
            'btnOrders'   => ['events' =>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmOrders').submit();"], 'attr'=>['type'=>'button','value'=>lang('go')]],
            'dateShip'    => ['classes'=>['easyui-datebox'],'attr'=>['type'=>'date','value'=>biz_date('Y-m-d')]],
            'btnConfirm'  => ['events' =>['onClick'=>"jqBiz('#frmConfirm').submit();"], 'attr'=>['type'=>'button','value'=>lang('go')]]];
        $data = ['title'=>$this->lang['title'],
            'divs'=>[
                'head'    => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR'  => ['order'=> 2,'type'=>'html',  'html'=>"<br />"],
                'manager' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setInv'  => ['order'=>20,'type'=>'panel','key'=>'setInv',  'classes'=>['block33']],
                    'setOrder'=> ['order'=>30,'type'=>'panel','key'=>'setOrder','classes'=>['block33']],
                    'setShip' => ['order'=>40,'type'=>'panel','key'=>'setShip', 'classes'=>['block33']]]]],
            'panels' => [
                'setInv'  => ['title'=>$this->lang['build_inventory'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmInventory'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['build_inventory_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['selMap','btnInventory']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'setOrder'=> ['title'=>$this->lang['import_orders'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmOrders'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['import_orders_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['fileOrders','btnOrders']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'setShip' => ['title'=>$this->lang['confirm_shipments'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmConfirm'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['confirm_shipments_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['dateShip','btnConfirm']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms'  => [
                'frmInventory'=> ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/inventoryGo&modID=$this->code"]],
                'frmOrders'   => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/ordersGo&modID=$this->code"]],
                'frmConfirm'  => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/confirmGo&modID=$this->code"]]],
            'fields' => $fields,
            'jsReady'=>['init'=>"ajaxDownload('frmInventory');\najaxForm('frmOrders');\najaxDownload('frmConfirm');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function pbEdit(&$layout) // extends /phreebooks/main/edit
    {
        if (empty($layout['fields']['journal_id'])) { return; }
        $jID = $layout['fields']['journal_id']['attr']['value'];
        $cID = $layout['fields']['contact_id_b']['attr']['value'];
        msgDebug("\nEntering ifAmazon for customization with jID = $jID and contact ID = $cID");
        if (empty($cID)) { return; }
        if ($jID==18 && $cID==$this->settings['contact_id']) {
            $layout['jsHead'][$this->moduleID] = "var AmazonGlAr = '{$this->settings['gl_acct_ar']}';\nvar AmazonGlTax = '{$this->settings['gl_acct_tax']}'";
            $layout['toolbars']['tbPhreeBooks']['icons']['ifAmazon'] = ['order'=>80,'label'=>$this->lang['import_payment'],'events'=>['onClick'=>"reconcileAmazon();"]];
            $layout['jsBody']['ifAmazon'] = "jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/$this->code.js&ver=".MODULE_BIZUNO_VERSION."');";
        }
    }

    /**
     *
     * @param type $jID
     */
    private function journalMainSaveDefaults($jID=10)
    {
        $data = ['path'=>'ifAmazon'.$jID, 'values'=>[
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'DESC'],
            ['index'=>'period','clean'=>'text',   'default'=>getModuleCache('phreebooks', 'fy', 'period')],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     *
     * @global type $msgStack
     * @global \bizuno\type $io
     * @param type $layout
     * @return type
     */
    public function inventoryGo(&$layout=[])
    {
        global $msgStack, $io;
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'availableQty', 'function');
        $dbField= $this->settings['catalog_field'];
        $map    = clean('selMap', 'text', 'post');
        if (!file_exists (BIZUNO_DATA."data/ifAmazon/$map.map")) { return msgAdd(sprintf($this->lang['err_no_inv_map'], $map)); }
        $map    = json_decode(file_get_contents(BIZUNO_DATA."data/ifAmazon/$map.map"), true);
        $rows   = [];
        $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory", "inactive='0' AND `$dbField`='1'", 'sku');
        foreach ($result as $key => $item) {
            msgDebug("\nworking with item ".print_r($item, true));
            $aItem = [];
            foreach ($map['fields'] as $idx => $attr) {
                switch ($idx) { // error check required fields
                    case 'gtin_exemption_reason':
                        $aItem[$idx] =  isset($item[$attr['value']]) && $item[$attr['value']] ? 'ReplacementPart' : '';
                        break;
                    case 'item_length':
                    case 'item_width':
                    case 'item_height':
                        $aItem[$idx] = round($item[$attr['value']], 2);
                        break;
                    case 'item_sku':
                        if (!$attr['value']) { msgAdd(sprintf($this->lang['err_no_inv_sku'], $item['sku'])); }
                        else { $aItem[$idx] = $this->csvEncode($item[$attr['value']]); }
                        break;
                    case 'item_weight':
                        if ($item['item_weight'] == 0) { msgAdd(sprintf($this->lang['err_sku_no_weight'], $item['sku'])); }
                        else { $aItem[$idx] = $this->csvEncode($item['item_weight']); }
                        break;
                    case 'quantity':
                        $aItem[$idx] = availableQty($item);
                        break;
                    case 'standard_price': // was item-price???
                        $price['args'] =['cID'=>$this->settings['contact_id'], 'iID'=>$item['id']];
                        compose('inventory', 'prices', 'quote', $price);
                        $aItem[$idx] = isset($price['content']['price']) ? $price['content']['price'] : 0;
                        if (!$aItem[$idx]) { msgAdd(sprintf($this->lang['err_inv_no_price'], $item['sku'])); }
                        break;
                    case 'main_image_url':
                        $aItem[$idx] = BIZUNO_URL_FS.getUserCache('business', 'bizID')."/images/{$item['image_with_path']}";
                        break;
                    default:
                        $aItem[$idx] = $attr['value'] && isset($item[$attr['value']]) ? str_replace(["\r","\n","\t"], ' ', $item[$attr['value']]) : '';
                }
            }
            $rows[$key] = $aItem;
        }
        if (sizeof($rows) == 0) { return msgAdd($this->lang['err_no_inv_rows']); }
        if (sizeof($msgStack->error) > 0) { return msgAdd($this->lang['err_feed_needs_fix']); }
        $output = [];
        $temp   = []; // to extract the second header row
        foreach ($map['fields'] as $idx => $attr) { $temp[] = $attr['label'];  }
        $output[] = $map['header']; // line 1 from Amazon template (not to be modified), version and category
        $output[] = implode("\t", $temp); // line 2 from map labels
        $output[] = implode("\t", array_keys($map['fields'])); // from map indexes
        foreach ($rows as $row) { $output[] = implode("\t", $row); }
        msgLog("Amazon inventory upload file generated.");
        $io->download('data', implode("\n", $output), "AmazonInventoryFeed-".biz_date('Y-m-d').".txt");
    }

    /**
     *
     * @global \bizuno\type $io
     * @param array $layout
     * @return modified $layout
     */
    public function ordersGo(&$layout=[])
    {
        global $io;
        msgDebug("\nWorking with settings = ".print_r($this->settings, true));
        if (!$security = validateAccess('ifAmazon', 2)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php',  'journal');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php','phreebooksProcess','function');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'getStoreStock',    'function');
        if (!$io->validateUpload('fileOrders', 'text', 'txt')) { return; }
        $strucMain = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main');
        $strucItem = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item');
        // load the amazon contact record info
        $cID = isset($this->settings['contact_id']) ? $this->settings['contact_id'] : false;
        if (!$cID) { msgAdd($this->lang['err_no_contact']); return; }
        $contact = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
        $commonMain = [
            'post_date'     => biz_date('Y-m-d'), // forces orders posted today
            'terminal_date' => biz_date('Y-m-d'),
            'waiting'       => '1',
            'terms'         => $contact['terms'],
            'store_id'      => $contact['store_id'],
            'rep_id'        => $contact['rep_id'],
            'gl_acct_id'    => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'), // make this a setting?
            'contact_id_b'  => $contact['id'],
            'address_id_b'  => 0, // no longer used
            'primary_name_b'=> $contact['primary_name'],
            'contact_b'     => $contact['contact'],
            'address1_b'    => $contact['address1'],
            'address2_b'    => $contact['address2'],
            'city_b'        => $contact['city'],
            'state_b'       => $contact['state'],
            'postal_code_b' => $contact['postal_code'],
            'country_b'     => $contact['country'],
            'telephone1_b'  => $this->cleanPhone($contact['telephone1']),
            'email_b'       => $contact['email'],
            'drop_ship'     => '1'];
        // iterate through the map to set journal post variables, orders may be on more than 1 line
        // ***************************** START TRANSACTION *******************************
        dbTransactionStart();
        $itemCnt = 1;
        $items   = [];
        $totals  = [];
        $nextData= [];
        $inStock = true;
        $orderCnt= 0;
        $runaway = 0;
        $rows    = file($_FILES['fileOrders']['tmp_name']);
        $heading = array_shift($rows); // heading
        $this->headings = explode("\t", $heading);
        $row     = array_shift($rows); // first order
        if (!$row) { return msgAdd("There were no orders to process!", 'caution'); }
        $data= $this->processRow($row);
        while (true) {
            if (!$row) { break; }
            $main = $commonMain;
            $main['purch_order_id'] = $data['order-id'];
            $main['description']    = "Amazon Order # ".$data['order-id'];
            $main['method_code']    = $data['ship-service-level']=='Expedited' ? $this->settings['ship_exp'] : $this->settings['ship_std'];
            // Amazon misspelled recepient, test for both cases and correct
            if (isset($data['recepient-name'])) { $data['recipient-name'] = $data['recepient-name']; }
            $main['primary_name_s'] = $data['recipient-name'];
            $main['address1_s']     = $data['ship-address-1'];
            $main['address2_s']     = $data['ship-address-2'];
            $main['contact_s']      = $data['ship-address-3'];
            $main['city_s']         = $data['ship-city'];
            $main['state_s']        = $this->localeProcess($data['ship-state'], 'state');
            $main['postal_code_s']  = $data['ship-postal-code'];
            $main['country_s']      = 'USA'; // $data['ship-country'];
            $main['telephone1_s']   = $this->cleanPhone($data['buyer-phone-number']);
            $main['email_s']        = $data['buyer-email'];
            // build the item, check stock if auto_journal
            $inv = dbGetRow(BIZUNO_DB_PREFIX."inventory", "sku='".addslashes($data['sku'])."'");
            if (empty($inv)) {
                return msgAdd("SKU {$data['sku']} cannot be found in Bizuno. No transactions were processed, please correct the error and retry.");
            }
            $inStock = $this->findStock($main, $inv, $data['quantity-purchased']);
            $items[] = [
                'item_cnt'      => $itemCnt,
                'gl_type'       => 'itm',
                'sku'           => $data['sku'],
                'qty'           => $data['quantity-purchased'],
                'description'   => $data['product-name'],
                'credit_amount' => $data['item-price'],
                'gl_account'    => $this->settings['gl_acct_sales'] ? $this->settings['gl_acct_sales'] : $inv['gl_sales'],
                'tax_rate_id'   => 0, // sales tax measured at the order level as tax shipping depends on state.
                'full_price'    => $inv['full_price'],
                'post_date'     => substr($data['purchase-date'], 0, 10)];
            $itemCnt++;
            // preset some totals to keep running balance
            if (!isset($totals['discount']))    { $totals['discount']    = 0; }
            if (!isset($totals['sales_tax']))   { $totals['sales_tax']   = 0; }
            if (!isset($totals['total_amount'])){ $totals['total_amount']= 0; }
            if (!isset($totals['freight']))     { $totals['freight']     = 0; }
            // fill in order info
            $totals['discount']    += $data['item-promotion-discount'] + $data['ship-promotion-discount'];
            $totals['sales_tax']   += $data['item-tax'] + $data['shipping-tax'];
            $totals['total_amount']+= $data['item-price'] + $data['item-tax'] + $data['shipping-price'] + $data['shipping-tax']; // missing from file: $data['gift-wrap-price'] and $data['gift-wrap-tax']
            $totals['freight']     += $data['shipping-price'];
            // check for continuation order
            $row = array_shift($rows);
            if ($runaway++ > 1000) { msgAdd("runaway reached, exiting!"); break; }
            if ($row) { // check for continuation order
                $nextData = $this->processRow($row);
                msgDebug("\nContinuing order check, Next order = {$nextData['order-id']} and this order = {$main['purch_order_id']}");
                if ($nextData['order-id'] == $main['purch_order_id']) {
                    $data = $nextData;
                    continue; // more items for the same order
                }
            }
            // check some things
            if (strlen($data['recipient-name']) > 32) {
                msgAdd(sprintf($this->lang['msg_order_long_data'], $data['order-id'], lang('primary_name'), $data['recipient-name']), 'caution');
            }
            if (strlen($data['ship-address-1']) > 32) {
                msgAdd(sprintf($this->lang['msg_order_long_data'], $data['order-id'], lang('address1'), $data['ship-address-1']), 'caution');
            }
            if (strlen($data['ship-address-2']) > 32) {
                msgAdd(sprintf($this->lang['msg_order_long_data'], $data['order-id'], lang('address2'), $data['ship-address-2']), 'caution');
            }
            // finish main and item to post
            $main['total_amount'] = $totals['total_amount'];
            $items[] = [
                'qty'          => 1,
                'gl_type'      => 'frt',
                'description'  => "Shipping Amazon # {$data['order-id']}",
                'credit_amount'=> $totals['freight'],
                'gl_account'   => !empty($this->settings['gl_acct_ship']) ? $this->settings['gl_acct_ship'] : getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'),
                'tax_rate_id'  => 0,
                'post_date'    => substr($data['purchase-date'], 0, 10)];
            if ($totals['sales_tax'] > 0) { $items[] = [ // sales tax is added at the order level
                'qty'          => 1,
                'gl_type'      => 'glt',
                'description'  => "Sales tax collected Amazon # {$data['order-id']}",
                'credit_amount'=> $totals['sales_tax'],
                'gl_account'   => !empty($this->settings['gl_acct_tax']) ? $this->settings['gl_acct_tax'] : getModuleCache('phreebooks','settings','vendors','gl_liability'),
                'post_date'    => substr($data['purchase-date'], 0, 10)];
            }
            $items[] = [
                'qty'          => 1,
                'gl_type'      => 'ttl',
                'description'  => "Total Amazon # ".$data['order-id'],
                'debit_amount' => $totals['total_amount'],
                'gl_account'   => !empty($this->settings['gl_acct_ar']) ? $this->settings['gl_acct_ar'] : getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'),
                'post_date'    => substr($data['purchase-date'], 0, 10)];
            // set some specific journal information, first post journal
            switch ($this->settings['auto_journal']) {
                case '10': $jID = 10; break;
                case '12': $jID = 12; break;
                default:   $jID = $inStock ? 12 : 10; break; // auto detect
            }
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "purch_order_id='{$main['purch_order_id']}'");
            if ($dup) {
                msgDebug("duplicate order id = $dup and main = ".print_r($main, true));
                msgAdd(sprintf($this->lang['err_dup_order'], $data['order-id']), 'caution');
            } else {
                $ledger = new journal(0, $jID, $main['post_date']);
                $ledger->main  = array_merge($ledger->main, $main);
                validateData($strucMain, $ledger->main);
                for ($i=0; $i<sizeof($items); $i++) { validateData($strucItem, $items[$i]); }
                $ledger->items = $items;
                if (!$ledger->Post()) { return; }
                $orderCnt++;
            }
            // prepare for next order.
            $data   = $nextData;
            $itemCnt= 1;
            $items  = [];
            $totals = [];
            $inStock= true;
        }
        if ($orderCnt) { if (!$ledger->updateJournalHistory(getModuleCache('phreebooks', 'fy', 'period'))) { return; } }
        dbTransactionCommit();
        // ***************************** END TRANSACTION *******************************
        msgAdd(sprintf($this->lang['amazon_post_success'], $orderCnt), 'success');
        msgLog(sprintf($this->lang['amazon_post_success'], $orderCnt));
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    private function findStock(&$main, $inv, $qty)
    {
        if (sizeof(getModuleCache('bizuno', 'stores')) < 2) { // only one store
            return $inv['qty_stock'] < $qty ? false : true; // becomes inStock
        } // else multi-store, find if it is in one of the stores
        $stock = getStoreStock($inv['sku'], $inv['item_cost']);
        msgDebug("\nRead stock at all stores for SKU = {$inv['sku']} = ".print_r($stock, true));
        $totalStk = 0;
        foreach ($stock as $sKey => $item) { // @TODO new feature to geolocate store based on ship to location
            $totalStk += $item['stock'];
            if ($item['stock'] >= $qty) { $main['store_id'] = substr($sKey, 1); return true; }
        }
        if ($totalStk >= $qty) {
            msgAdd("Order {$main['purch_order_id']} has enough parts to fill the order but they are in more than one branch.", 'info');
        }
        return false;
    }

    /**
     * Shortens an Amazon phone by eliminating unneeded fill.
     * @param type $tele
     * @return type
     */
    private function cleanPhone($tele)
    {
        $step1 = str_replace('+1 ', '', $tele);
        $step2 = str_replace(' ext. ', 'x', $step1);
        if (strlen($step2) > 20) { msgAdd("Telephone number $tele cannot be reduced to fit. Please edit it manually", 'caution'); }
        return $step2;
    }

    /**
     *
     * @global \bizuno\type $io
     * @return type
     */
    public function confirmGo()
    {
        global $io;
        if (!$security = validateAccess('ifAmazon', 2)) { return; }
        $cID   = isset($this->settings['contact_id']) ? $this->settings['contact_id'] : false;
        $shipDate= clean('dateShip', 'date', 'post');
        if (!$shipDate || !$cID) { return msgAdd($this->lang['err_confirm_no_contact'], 'error'); }
//      $fields = ["order-id","order-item-id","quantity","ship-date","carrier-code","carrier-name","tracking-number","ship-method"]; // these fields are the same for all templates
        // remove carrier-name as it causes warnings when used with carrier-code
        $fields= ['order-id','order-item-id','quantity','ship-date','carrier-code','tracking-number','ship-method']; // these fields are the same for all templates
        $stmt    = dbGetResult("SELECT journal_main.id AS mainID, journal_meta.id AS metaID, contact_id_b, post_date, purch_order_id, meta_value
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE meta_key='shipment' AND post_date='$shipDate'");
        $result  = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $rows    = $no_dups= [];
        $carriers= getMetaMethod('carriers');
        msgDebug("\nProps for all carriers = ".print_r($carriers, true));
        foreach ($result as $row) {
            if ($row['contact_id_b'] <> $cID) { continue; } // it's NOT an Amazon order
            $meta   = json_decode($row['meta_value'], true);
            msgDebug("\nWorking on decoded row = ".print_r($meta, true));
            $method = explode(":", $meta['method_code']);
            $shpmnt = $this->extractService($meta['method_code'], $carriers[$method[0]]);
            foreach ($meta['packages']['rows'] as $pkg) {
                $rows[] = [
                    'order-id'       => $row['purch_order_id'],
                    'order-item-id'  => '',
                    'quantity'       => '',
                    'ship-date'      => substr($meta['ship_date'], 0, 10),
                    'carrier-code'   => $shpmnt['code'],
//                  'carrier-name'   => $carrier, // $title, when $title OR $carrier is sent, amazon generates warnings.
                    'tracking-number'=> $pkg['tracking_id'],
                    'ship-method'    => $shpmnt['method']];
                $meta['amazon_confirm'] = 1;
                dbMetaSet($row['metaID'], 'shipment', $meta, 'journal', $row['mainID']);
            }
        }
        if (sizeof($rows) == 0) { return msgAdd($this->lang['err_no_confirm_found'], 'caution'); }
        $output   = [];
        $output[] = implode("\t", $fields); // ship confirm require tab delimited
        foreach ($rows as $row) { $output[] = implode("\t", $row); }
        msgLog($this->lang['msg_confirm_success']);
        $io->download('data', implode("\n", $output), "AmazonShipConfirm-{$shipDate}.csv");
    }

    /**
     *
     * @param type $method_code
     * @param type $props
     * @param type $default
     * @return type
     */
    private function extractService($method_code, $props=[], $default='GND')
    {
        // values = Blue Package, USPS, UPS, UPSMI, FedEx, DHL, DHL Global Mail, Fastway, UPS Mail Innovations, Lasership, Royal Mail, FedEx SmartPost, OSM, OnTrac, Streamlite, Newgistics, Canada Post, City Link, GLS, GO!, Hermes Logistik Gruppe, Parcelforce, TNT, Target, SagawaExpress, NipponExpress, YamatoTransport, Other
        $carrier= 'Other';
//      $title  = 'Other Carrier';
        if (!empty($props['id'])) {
            $carrier= !empty($props['acronym']) ? $props['acronym'] : $props['id'];
//          $title  = $props['title'];
        }
        $method = !in_array($default, ['GND', 'GDR']) ? 'Expedited' : 'Standard';
        foreach ($props['settings']['services'] as $service) {
            if ($method_code == $service['id']) {
                $parts = explode(' ', $service['text'], 2);
                if ('Endicia'==$parts[0]) {
                    $tmp = explode(' ', $parts[1], 2); // for Endicia remove the duplicate
                    $carrier= 'USPS';
                    $method = trim($tmp[1]);
                } else {
                    $carrier= $parts[0];
                    $method = !empty($parts[1]) ? trim($parts[1]) : $service['text'];
                }
            }
        }
        return ['code'=>$carrier, 'title'=>$carrier, 'method'=>$method];
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function paymentFileForm(&$layout=[])
    {
        if (!$security = validateAccess('ifAmazon', 3)) { return; }
        $html  = '<p>'.$this->lang['import_payment_desc']."</p>";
        $html .= html5('frmNewPmt', ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/paymentProcess&modID=ifAmazon"]]);
        $html .= html5('amazon_pmt',['attr'=>['type'=>'file']]);
        $html .= html5('iconGO', ['icon'=>'next', 'events'=>['onClick'=>"jqBiz('#frmNewPmt').submit(); bizWindowClose('winNewPmt'); jqBiz('body').addClass('loading');"]]);
        $html .= "</form>";
        $data = ['type'=>'popup','title'=>$this->lang['import_payment'],'attr'=>['id'=>'winNewPmt','width'=>400,'height'=>200],
            'divs'   => ['winNewPmt'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['ifAmazon' =>"ajaxForm('frmNewPmt');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @global \bizuno\type $io
     * @param type $layout
     * @return type
     */
    public function paymentProcess(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('ifAmazon', 3)) { return; }
        if (!$io->validateUpload('amazon_pmt')) { return; }
        $contents = file($_FILES['amazon_pmt']['tmp_name']);
        msgDebug("\nread ".sizeof($contents)." lines from the uploaded file.");
        $contents[0] = str_replace('-', '_', $contents[0]); // breaks javascript to use dashes
        $output = base64_encode(implode("\n", $contents)); // base64_encode file to preserve tabs and line feeds.
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval', 'actionData'=>"processAmazon(json);", 'payments'=>$output]]);
    }

    /**
     *
     * @param type $row
     * @param type $delimiter
     * @return type
     */
    private function processRow($row, $delimiter="\t")
    {
        $data = explode($delimiter, $row);
        $output = [];
        foreach ($this->headings as $key => $value) { $output[$value] = isset($data[$key]) ? $data[$key] : ''; }
        return $output;
    }

    private function adminHome(&$layout=[])
    {
        $listAm  = glob(BIZUNO_FS_LIBRARY.'controllers/api/funnels/amazon/source/*');
        $templAm = [['id'=>'', 'text'=>lang('select')]];
        foreach ($listAm as $option) { if (is_dir($option)) {
            $tpl = substr($option, strrpos($option, '/')+1);
            $templAm[] = ['id'=>$tpl, 'text'=>$tpl];
        } }
        $layout['tabs']['tabAdmin']['divs'][$channel] = ['order'=>80,'label'=>$this->lang['amazon_maps'],'type'=>'divs','divs'=>[
            'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'getMap' => ['order'=>20,'type'=>'panel','classes'=>['block66'],'key'=>$this->code]]]]];
        $layout['panels'][$channel] = ['type'=>'fields','keys'=>['tplDescAm','selTempAm','divMapAm']];
        $layout['fields']['tplDescAm'] = ['order'=>10,'html'=>$this->lang['amazon_template_desc'],   'attr'=>['type'=>'raw']];
        $layout['fields']['selTempAm'] = ['order'=>20,'values'=>$templAm,'events'=>['onChange'=>"jsonAction('api/admin/templateStructure&modID=ifAmazon', 0, bizSelGet('selTempAm'));"], 'attr'=>['type'=>'select']];
        $layout['fields']['divMapAm']  = ['order'=>90,'html'=>'<div id="divAmazonMap">&nbsp;</div>', 'attr'=>['type'=>'raw']];
        $layout['jsHead'][$channel] = "jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/$this->code.js&ver=".MODULE_BIZUNO_VERSION."');";
    }

  /**
   * Extracts the template info with headings and builds a PHP map template,
   * it should only be run once per template then edited to map to Bizuno
   */
    private function loadInvTemplate($tpl, $force=true)
    {
        $rows = file(BIZUNO_FS_LIBRARY."controllers/ifAmazon/source/$tpl/Template.txt");
        $line1 = explode("\t", trim($rows[0])); // header row contains titles
        $line2 = explode("\t", trim($rows[1])); // English titled indexes
        $line3 = explode("\t", trim($rows[2])); // amazon keyed fields
        $output = ['header'=>trim($rows[0]),'fields'=>[],'groups'=>[]];
        $grpCnt = 0;
        for ($i=0; $i<sizeof($line3); $i++) {
            if ($i>3 && isset($line1[$i])) { // it's a group heading
                $grpCnt++;
                $output['groups'][$grpCnt] = $line1[$i];
            }
            $output['fields'][$line3[$i]] = ['label'=>$line2[$i], 'group'=>$grpCnt];
        }
        // now the definitions file
        if (($handle = fopen(BIZUNO_FS_LIBRARY."controllers/ifAmazon/source/$tpl/Data Definitions.txt", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                if (isset($data[6]) && isset($output['fields'][$data[6]])) {
                    $output['fields'][$data[1]]['tip']     = $data[3]."\n\nRange: ".$data[4]."\n\nExample: ".$data[5];
                    $output['fields'][$data[1]]['required']= $data[6];
                }
            }
            fclose($handle);
        }
        return $output;
    }

    /**
     *
     * @global type $io
     * @param type $tpl
     * @param type $output
     */
    private function saveInvTemplate($tpl, $output) {
        global $io;
        $io->fileWrite(json_encode($output), "data/ifAmazon/$tpl.map", true);
        msgDebug("output = ".print_r($output, true));
        msgAdd($this->lang['msg_template_created'], 'success');

    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function templateStructure(&$layout=[])
    {
        if (!$security = validateAccess('ifAmazon', 3)) { return; }
        $tpl = clean('data', 'text', 'get');
        if (!$tpl) { return msgAdd("No template file selected!"); }
        $structure = $this->loadInvTemplate($tpl, true);
        $temp = [];
        if (file_exists(BIZUNO_DATA."data/ifAmazon/$tpl.map")) { // get current settings
            $temp = json_decode(file_get_contents(BIZUNO_DATA."data/ifAmazon/$tpl.map"), true);
            unset($temp['header']); // remove the header in case of new template
        }
        $fields = array_replace_recursive($temp, $structure);
        $this->saveInvTemplate($tpl, $fields);
        $data = [
            'content'=> ['action'=>'divHTML','divID'=>'divAmazonMap'],
            'divs'   => ['divTpl'=>['order'=>10,'type'=>'html','html'=>$this->viewTemplate]],
            'forms'  => ['frmTemplate'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifAmazon/admin/templateSave"]]],
            'icnSave'=> ['icon'=>'save','events'=>['onClick'=>"jqBiz('#frmTemplate').submit();"]],
            'fldTpl' => ['attr'=>['type'=>'hidden', 'value'=>"$tpl"]],
            'lang'   => [
                'amazon_field' => $this->lang['amazon_field'],
                'bizuno_field' => $this->lang['bizuno_field']],
            'fields' => [], // filled below
            'jsHead' => ['initAmazon'=>"jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/$this->code.js&ver=".MODULE_BIZUNO_VERSION."');"]];
        foreach ($fields['fields'] as $key => $value) {
            $data['fields'][$key] = [
                'group' => $value['group']>0 ? $fields['groups'][$value['group']] : '',
                'title' => (isset($value['required']) && $value['required'] ? "(".$value['required'].") " : "(Optional) ") . $value['label'],
                'attr'  => ['value'  => !empty($value['value']) ? $value['value'] : ''],
                'events'=> ['onClick'=> "amazonFields('$key');"]];
            if (!empty($value['tip'])) {
                $value['tip'] = str_replace(["\n", "\r", "'", '"'], [" ", " ", "\'", ''], $value['tip']); // double quotes causes javascript issues
                $data['fields'][$key]['help'] = $value['tip'];
            }
        }
        $invStructure = dbLoadStructure(BIZUNO_DB_PREFIX."inventory");
        $invFields = [];
        foreach ($invStructure as $field => $attr) { $invFields[] = ['field'=>$field, 'title'=>$attr['label']]; }
        $temp2 = [];
        foreach ($invFields as $key => $value) { $temp2[$key] = $value['title']; }
        array_multisort($temp2, SORT_ASC, $invFields);
        $data['jsBody']['invFields'] = formatDatagrid($invFields, 'invFields');
        $layout = array_replace_recursive($layout, $data);
    }

    private function viewTemplate()
    {
        global $viewData;
        $html  = html5('icnSave',    $viewData['icnSave']);
        $html .= html5('frmTemplate',$viewData['forms']['frmTemplate']);
        $html .= html5('template' ,  $viewData['fldTpl']);
        $html .= '<table style="border-collapse:collapse;width:800px;margin-left:auto;margin-right:auto;">';
        $html .= '  <tr class="panel-header"><th>'.$viewData['lang']['bizuno_field']."</th><th>".lang('title')."</th><th>".$viewData['lang']['amazon_field']."</th></tr>";
        $lastGroup = '';
        if (isset($viewData['fields'])) { foreach ($viewData['fields'] as $idx => $settings) {
            if (!empty($settings['help'])) {
                $icnHelp = ['icon'=>'tip','size'=>'small', 'events'=>['onClick'=>"jqBiz('#win_$idx').window({title:'".$settings['title']."',content:'".$settings['help']."',width:450,height:200});"]];
            } else {
                $icnHelp = ['icon'=>'blank','size'=>'small'];
            }
            if ($settings['group'] && $lastGroup != $settings['group']) {
                $html .= '<tr><th colspan="3" class="panel-header" style="text-align:left">'.$settings['group']."</th></tr>";
                $lastGroup = $settings['group'];
            }
            $html .= "<tr>";
            $html .= "  <td>".html5($idx, $settings)."</td>";
            $html .= '  <td>'.html5('',   $icnHelp).' '.$settings['title']."</td>";
            $html .= '  <td>'.$idx."</td>";
            $html .= "</tr>";
        } }
        // build the divs for the window popup
        if (isset($viewData['fields'])) { foreach ($viewData['fields'] as $idx => $settings) { $html .= '<div id="win_'.$idx.'"></div>'; } }
        $html .= "</table>";
        $html .= "</form>";
        htmlQueue("function amazonFields(id) {
    var fldValue = jqBiz('#'+id).val();
    jqBiz('#'+id).combogrid({data:invFields, value:fldValue, panelWidth:525, idField:'field', textField:'title',
        columns:[[{field:'field',title:'".lang('field')."',width:250}, {field:'title',title:'".lang('title')."',width:250},]]
    });
}", 'jsHead');
        htmlQueue("ajaxForm('frmTemplate');", 'jsReady');
        return $html;
    }

    /**
     *
     * @global \bizuno\type $io
     * @return type
     */
    public function templateSave()
    {
        global $io;
        if (!$security = validateAccess('ifAmazon', 3)) { return; }
        $tpl = clean('template', 'text', 'post');
        if (!$tpl) { return msgAdd("No template found to save!"); }
        if (!file_exists(BIZUNO_DATA."data/ifAmazon/$tpl.map")) { return msgAdd("Sorry, I cannot file the template file in your file space"); }
        $fields = json_decode(file_get_contents(BIZUNO_DATA."data/ifAmazon/$tpl.map"), true);
        // clean the post variables
        foreach (array_keys($fields['fields']) as $key) {
            $setting = clean($key, 'text', 'post');
            if ($setting) { $fields['fields'][$key]['value'] = $setting; }
        }
        $io->fileWrite(json_encode($fields), "data/ifAmazon/$tpl.map", true, false, true);
        msgDebug("\nOutput = ".print_r($fields, true));
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     *
     * @param type $value
     * @return type
     */
    private function csvEncode($value)
    {
        return strpos($value, ',') !== false ? '"'.$value.'"' : $value;
    }

    /**
     *
     * @param type $value
     * @param type $action
     * @return type
     */
    private function localeProcess($value, $action)
    {
        switch ($action) {
            case 'state':
                $value = clean($value, 'alpha_num');
                if (strlen($value) > 2) { // full state returned, get the code
                    $state = strtolower($value);
                    $temp = localeLoadDB();
                    foreach ($temp->Locale as $value) { if ($value->Country->ISO3 == 'USA') {
                        foreach ($value->Country->Region as $region) { if (strtolower($region->Title) == $state) { return ($region->Code); } }
                    } }
                }
                return is_string($value) ? strtoupper($value) : $value;
            default: // return $value
        }
        return $value;
    }
}
