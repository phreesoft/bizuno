<?php
/*
 * @name Bizuno ERP - Big Commerce Interface channel
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
 * @filesource /controllers/api/funnels/ifBigCom/ifBigCom.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/api/export.php', 'apiExport');

class ifBigCom extends apiExport
{
    public  $moduleID = 'api';
    public  $methodDir= 'funnels';
    public  $code     = 'ifBigCom';
    private $inStock  = true; // Assume product is in stock, cleared during journal item creation
    public  $defaults;
    public  $settings;
    public  $lang     = ['title' => 'BigCommerce Interface',
        'description' => 'The BigCommerce interface provides capability to download orders, upload product feeds and help reconcile payments.',
        'import_orders' => 'Import Orders',
        'build_inventory' => 'Generate Inventory Feed',
        'confirm_shipments' => 'Confirm Shipments',
        'confirm_shipments_desc' => 'Select the date to build the BigCommerce ship confirmation on and press Go:',
        'import_orders_desc' => 'Select order file to import from BigCommerce and press Go:',
        'bigcom_post_success' => 'Successfully posted %s BigCommerce orders!',
        'err_dup_order' => 'BigCommerce order # %s is already posted to Bizuno, it will be skipped!',
        'err_confirm_no_contact' => 'Contact ID/Ship date could not be found, no file was generated!',
        'err_no_confirm_found' => 'No valid BigCommerce orders have been shipped on the date selected!',
        'msg_confirm_success' => 'BigCommerce order confirmation generated.',
        // settings
        'store_id_lbl'     => 'Store to Credit',
        'contact_id_lbl'   => 'Customer ID',
        'catalog_field_lbl'=> 'Inv Link Field',
        'prefix_order_lbl' => 'Order Prefix',
        'prefix_cust_lbl'  => 'Prefix For New Customers',
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
        'contact_id_tip'   => 'Determines the Customer contact ID to assign all BigCommerce sales.',
        'catalog_field_tip'=> 'Determines which database field name to use to select items to be uploaded to BigCommerce, typically a checkbox type',
        'price_sheet_tip'  => 'Determines which price sheet to use to determine the item price',
        'ship_std_tip'     => 'Freight carrier/method to use for Standard Shipments',
        'ship_exp_tip'     => 'Freight carrier/method to use for Expedited Shipments',
        'gl_acct_sales_tip'=> 'GL Account to use for recording sales',
        'gl_acct_tax_tip'  => 'GL Account to use for recording sales tax',
        'gl_acct_disc_tip' => 'GL Account to use for recording sales discounts',
        'gl_acct_ship_tip' => 'GL Account to use for recording freight charges',
        'auto_journal_tip' => 'Determines how to post each sale, choices are Sales Orders, Sales, or Auto (Sales if in stock, Sales Order if not)'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $this->defaults= ['store_id'=>0,'ship_std'=>0,'ship_exp'=>0,'auto_journal'=>0,'prefix_order'=>'BC','prefix_cust'=>'BC',
            'gl_acct_sales'=>getModuleCache('phreebooks','settings','customers','gl_sales'),
            'gl_acct_ar'   =>getModuleCache('phreebooks','settings','customers','gl_receivables'),
            'gl_acct_tax'  =>getModuleCache('phreebooks','settings','vendors',  'gl_liability'),
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
            'store_id'     => ['values'=>viewStores(), 'attr'=>['type'=>'select', 'value'=>$this->settings['store_id']]],
            'ship_std'     => ['values'=>$choices, 'attr'=>['type'=>'select', 'value'=>$this->settings['ship_std']]],
            'ship_exp'     => ['values'=>$choices, 'attr'=>['type'=>'select', 'value'=>$this->settings['ship_exp']]],
            'auto_journal' => ['values'=>$autoJID, 'attr'=>['type'=>'select', 'value'=>$this->settings['auto_journal']]],
            'prefix_order' => ['attr'=>['value'=>$this->settings['prefix_order']]],
            'prefix_cust'  => ['attr'=>['value'=>$this->settings['prefix_cust']]],
            'gl_acct_sales'=> ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_sales','value'=>$this->settings['gl_acct_sales']]],
            'gl_acct_ar'   => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ar',   'value'=>$this->settings['gl_acct_ar']]],
            'gl_acct_tax'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_tax',  'value'=>$this->settings['gl_acct_tax']]],
            'gl_acct_ship' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ship', 'value'=>$this->settings['gl_acct_ship']]],
            ];
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
        if (!$security = validateAccess($this->code, 1)) { return; }
        $fields = [
            'imgLogo'     => ['styles' =>['cursor'=>'pointer'], 'events' =>['onClick'=>"winHref('https://www.bigcommerce.com');"],
                'attr'=>['type'=>'img','height'=>100,'src'=>BIZBOOKS_URL_ROOT."0/controllers/$this->moduleID/channels/$this->code/$this->code.png"]],
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
//                  'setInv'  => ['order'=>20,'type'=>'panel','key'=>'setInv',  'classes'=>['block33']],
                    'setOrder'=> ['order'=>30,'type'=>'panel','key'=>'setOrder','classes'=>['block33']],
                    'setShip' => ['order'=>40,'type'=>'panel','key'=>'setShip', 'classes'=>['block33']]]]],
            'panels' => [
/*                'setInv'  => ['label'=>$this->lang['build_inventory'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmInventory'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['build_inventory_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['selMap','btnInventory']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]], */
                'setOrder'=> ['label'=>$this->lang['import_orders'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmOrders'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['import_orders_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['fileOrders','btnOrders']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'setShip' => ['label'=>$this->lang['confirm_shipments'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmConfirm'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['confirm_shipments_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['dateShip','btnConfirm']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms'  => [
                'frmInventory'=> ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/inventoryGo&modID=$this->code"]],
                'frmOrders'   => ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/ordersGo&modID=$this->code"]],
                'frmConfirm'  => ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/admin/confirmGo&modID=$this->code"]]],
            'fields' => $fields,
            'jsReady'=>['init'=>"ajaxDownload('frmInventory');\najaxForm('frmOrders');\najaxDownload('frmConfirm');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Uploads the orders into Bizuno
     * @param array $layout
     * @return modified $layout
     *
     * BigCommerce Order .csv fields:
     * "Order ID","Customer ID","Customer Name","Customer Email","Customer Phone",
     * "Order Date","Order Status","Subtotal (inc tax)","Subtotal (ex tax)","Tax Total",
     * "Shipping Cost (inc tax)","Shipping Cost (ex tax)","Ship Method","Handling Cost (inc tax)",
     * "Handling Cost (ex tax)","Order Total (inc tax)","Order Total (ex tax)",
     * "Payment Method","Total Quantity","Total Shipped","Date Shipped","Order Currency Code",
     * "Exchange Rate","Order Notes","Customer Message","Billing First Name","Billing Last Name",
     * "Billing Company","Billing Street 1","Billing Street 2","Billing Suburb","Billing State",
     * "Billing Zip","Billing Country","Billing Phone","Billing Email","Billing Purchase Order #",
     * "Shipping First Name","Shipping Last Name","Shipping Company","Shipping Street 1",
     * "Shipping Street 2","Shipping Suburb","Shipping State","Shipping Zip","Shipping Country",
     * "Shipping Phone","Shipping Email","Shipping Purchase Order #",
     * "Product Details", (This data appears to be encoded into a single csv column)
     * "Store Credit Redeemed","Gift Certificate Amount Redeemed","Gift Certificate Code",
     * "Gift Certificate Expiration Date","Coupon Details","Refund Amount"
     */
    public function ordersGo(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->code, 1)) { return; }
        $this->itemCnt = 1;
        $orderCnt = 0;
        msgDebug("\nWorking with settings = ".print_r($this->settings, true));
        bizAutoLoad(BIZBOOKS_ROOT.'controllers/phreebooks/journal.php', 'journal');
        $strucMain = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main');
        $strucItem = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item');
        if (!$io->validateUpload('fileOrders', '', ['csv','txt'])) { return; }
        ini_set('auto_detect_line_endings', true);
        // ***************************** START TRANSACTION *******************************
        dbTransactionStart();
        $handle= fopen($_FILES['fileOrders']['tmp_name'], 'r');
        $keys  = fgetcsv($handle);
        while (($values = fgetcsv($handle)) !== false) {
            if (sizeof($values) <= 2) { continue; } // blank lines, single column header, skip
            if (sizeof($keys) <> sizeof($values)) { return msgAdd("The csv file is malformed, the total number of columns are not the same between the header and data!"); }
            $row = array_combine($keys, $values);
            msgDebug("\nProcessing row => ".print_r($row, true));
            // clean some fields up
            $row['Order ID']        = $this->settings['prefix_order'].str_pad($row['Order ID'],   6, '0', STR_PAD_LEFT);
            $row['Customer ID']     = $this->settings['prefix_cust'] .str_pad($row['Customer ID'],6, '0', STR_PAD_LEFT);
            // dates come in fixed EU format: 18/05/2022 => 2022-05-18
            $row['Order Date']      = substr($row['Order Date'], 6, 4).'-'.substr($row['Order Date'], 3, 2).'-'.substr($row['Order Date'], 0, 2);
            $row['Billing Country'] = clean($row['Billing Country'], 'country'); // full country to ISO3
            $row['Shipping Country']= clean($row['Shipping Country'],'country'); // full country to ISO3
            $this->mapMain($row);
            $this->guessCustomer($row);
            $this->addItem($row);
            $this->addDiscount($row);
            $this->addHandling($row);
            $this->addFreight($row);
            $this->addTax($row);
            $this->addTotal($row);
            switch ($this->settings['auto_journal']) {
                case '10': $jID = 10; break;
                case '12': $jID = 12; break;
                default:   $jID = $this->inStock ? 12 : 10; break; // auto detect
            }
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "purch_order_id='{$row['Order ID']}'");
            if ($dup) {
                msgDebug("duplicate order id = $dup and main = ".print_r($this->main, true));
                msgAdd(sprintf($this->lang['err_dup_order'], $row['Order ID']), 'caution');
            } else {
                $ledger = new journal(0, $jID, $this->main['post_date']);
                $ledger->main  = array_merge($ledger->main, $this->main);
                validateData($strucMain, $ledger->main);
                for ($i=0; $i<sizeof($this->items); $i++) { validateData($strucItem, $this->items[$i]); }
                $ledger->items = $this->items;
                if (empty($this->main['contact_id_b'])) {
                    msgDebug("\nAdding customer, need to set POST variables so new contact is saved properly!");
                    $this->setCustomerPOST();
                    $ledger->updateContact_b = true;
                }
                msgDebug("\nReady to post with ledger = ".print_r($ledger, true));
                if (!$ledger->Post()) { return; }
                $orderCnt++;
            }
            $this->items   = []; // reset the item array
            $this->itemCnt = 1; // reset line item counter
            $this->inStock = true; // reset for next order
        }
        if ($orderCnt) { if (!$ledger->updateJournalHistory(getModuleCache('phreebooks', 'fy', 'period'))) { return; } }
        dbTransactionCommit();
        // ***************************** END TRANSACTION *******************************
        msgAdd(sprintf($this->lang['bigcom_post_success'], $orderCnt), 'success');
        msgLog(sprintf($this->lang['bigcom_post_success'], $orderCnt));
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    private function guessCustomer(&$row=[])
    {
        msgDebug("\nEntering guessCustomer with customer ID = {$row['Customer ID']}");
        $custID = $this->settings['prefix_cust'].str_pad($row['Customer ID'], 6, '0', STR_PAD_LEFT);
        if ($row['Customer ID']==$this->settings['prefix_cust'].'000000') { // checked out as guest, do we allow this?
            // find the generic E-Store contact and return that ID
            $this->main['contact_id_b'] = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='BC-Guest'");
            msgDebug("\nGuest Checkout was used: returning contact_id_b = {$this->main['contact_id_b']}");
            return;
        }
        $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='$custID'");
        if (!empty($cID)) {
            $this->main['contact_id_b'] = $cID;
            msgDebug("\nFound existing short name ($custID) match contact_id_b = {$this->main['contact_id_b']}");
        }
        // try to find a customer match in Bizuno. These must be manually merged to provide adequate verification.
        $cIDs = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "email='{$this->main['email']}'");
        msgDebug("\nSearching for matching customers in the db, found rows: ".print_r($cIDs, true));
        foreach ($cIDs as $cID) {
            $cName = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'short_name', "id={$cID['ref_id']} AND type='c'");
            if (!empty($cName)) { msgAdd("This Customer ID ($custID), Name: {$cID['primary_name']} - Bizuno found a customer match with a different Customer ID ($cName), they need to be reviewed and possible merged.", 'info'); }
        }
        msgDebug("\nReturning with no customer found in db");
    }

    /**
     *
     * @param type $row
     */
    private function mapMain($row=[])
    {
        // check to see if the contact is already in the database, just to get the contact id and address id
        // shipping shopld always be free-form entry
        // IDEA: fraud check to see if shipping is way diferent than billing.
        //
        // NOT USED: "Order Status","Subtotal (inc tax)","Subtotal (ex tax)",
        // "Payment Method","Order Currency Code","Exchange Rate","Store Credit Redeemed","Gift Certificate Amount Redeemed",
        // "Gift Certificate Code","Gift Certificate Expiration Date","Coupon Details","Refund Amount"
        //
        $this->main = [
            'description'   => "BigCommerce Order # {$row['Order ID']}",
            'purch_order_id'=> $this->settings['prefix_order'].$row['Order ID'], // "Billing Purchase Order #", "Shipping Purchase Order #",
            'post_date'     => $row['Order Date'],
            'terminal_date' => biz_date(), // today
            'admin_id'      => '', // from cache
            'store_id'      => $this->settings['store_id'],
            'rep_id'        => 0, // 0, for nobody
            'terms'         => '', // prepaid, or use business default
            'tax_rate_id'   => 0, // use tax other
            'gl_acct_id'    => '', // pull from settings
            'notes'         => $row['Order Notes']."<br />".$row['Customer Message'],
            'contact_id_b'  => 0, // "Customer ID", pull from Bizuno contacts if exists, else new
            'address_id_b'  => 0,
            'primary_name_b'=> !empty($row['Billing Company']) ? $row['Billing Company'] : "{$row['Billing First Name']} {$row['Billing Last Name']}",
            'contact_b'     => !empty($row['Billing Company']) ? "Attn: {$row['Billing First Name']} {$row['Billing Last Name']}" : '',
            'address1_b'    => $row['Billing Street 1'],
            'address2_b'    => $row['Billing Street 2'],
            'city_b'        => $row['Billing Suburb'],
            'state_b'       => $row['Billing State'],
            'postal_code_b' => $row['Billing Zip'],
            'country_b'     => $row['Billing Country'],
            'telephone1_b'  => $row['Billing Phone'], // "Customer Phone",
            'email_b'       => $row['Billing Email'], // "Customer Email",
            'contact_id_s'  => 0, // Free form entry
            'address_id_s'  => 0, // Free form entry
            'primary_name_s'=> !empty($row['Shipping Company']) ? $row['Shipping Company'] : "{$row['Shipping First Name']} {$row['Shipping Last Name']}",
            'contact_s'     => !empty($row['Shipping Company']) ? "Attn: {$row['Shipping First Name']} {$row['Shipping Last Name']}" : '',
            'address1_s'    => $row['Shipping Street 1'],
            'address2_s'    => $row['Shipping Street 2'],
            'city_s'        => $row['Shipping Suburb'],
            'state_s'       => $row['Shipping State'],
            'postal_code_s' => $row['Shipping Zip'],
            'country_s'     => $row['Shipping Country'],
            'telephone1_s'  => $row['Shipping Phone'],
            'email_s'       => $row['Shipping Email'],
//          'invoice_num'   => '',
//          'drop_ship'     => '', // leave unchecked, may be changed by processor
        ];
    }

    /**
     * Copies the address from the current journal entry into the POST corresponding variable to create new customer, clears the record ID's
     */
    private function setCustomerPOST()
    {
        $_POST['id_b']          = 0;
        $_POST['address_id_b']  = 0;
        $_POST['primary_name_b']= $this->main['primary_name_b'];
        $_POST['contact_b']     = $this->main['contact_b'];
        $_POST['address1_b']    = $this->main['address1_b'];
        $_POST['address2_b']    = $this->main['address2_b'];
        $_POST['city_b']        = $this->main['city_b'];
        $_POST['state_b']       = $this->main['state_b'];
        $_POST['postal_code_b'] = $this->main['postal_code_b'];
        $_POST['country_b']     = $this->main['country_b'];
        $_POST['telephone1_b']  = $this->main['telephone1_b'];
        $_POST['email_b']       = $this->main['email_b'];
    }

    /**
     * Builds the item journal item entry
     * Multiple line items are separated with a vertical bar '|'
     * @param type $row
     */
    private function addItem($row=[])
    {
        // "Product Details","Total Quantity","Total Shipped","Date Shipped",
        // build the item, check stock if auto_journal
        // Product ID: 2471,
        // Product Qty: 20,
        // Product SKU: ELITE 2000 AA,
        // Product Name: AA NiMH  2000mAh Flat Top  High Rate Cells,
        // Product Weight: 0.0700,
        // Product Variation Details: ,
        // Product Unit Price: 2.49,
        // Product Total Price: 49.80
        // |
        $lines = explode('|', $row['Product Details']);
        foreach ($lines as $line) {
            $props = explode(',', $line);
            foreach ($props as $prop) {
                $tmp = explode(':', $prop);
                $data[trim($tmp[0])] = isset($tmp[1]) ? trim($tmp[1]) : '';
            }
            $inv = dbGetRow(BIZUNO_DB_PREFIX."inventory", "sku='{$data['Product SKU']}'");
            if (empty($inv)) { // Item ordered is not in the Bizuno inventory database, enter a null SKU and must be fixed manually
                $inv = ['sku'   => '',
                    'qty_stock' => 0,
                    'full_price'=> $data['Product Unit Price'],
                    'gl_sales'  => getModuleCache('phreebooks','settings','customers','gl_sales'),
                ];
            }
            if ($inv['qty_stock'] < $data['Product Qty']) { $this->inStock = false; }
            $this->items[] = [
                'item_cnt'     => $this->itemCnt,
                'gl_type'      => 'itm',
                'sku'          => $inv['sku'],
                'qty'          => $data['Product Qty'],
                'description'  => $data['Product Name'],
                'credit_amount'=> $data['Product Total Price'],
                'gl_account'   => $this->settings['gl_acct_sales'] ? $this->settings['gl_acct_sales'] : $inv['gl_sales'],
                'tax_rate_id'  => 0, // sales tax measured at the order level as tax shipping depends on state.
                'full_price'   => $inv['full_price'],
                'post_date'    => $row['Order Date']];
            $this->itemCnt++;
            $data = [];
        }
    }

    private function addDiscount($row=[])
    {
        // Big Commerce doesn't seem to do discounts or they are applied to the item price.
    }

    private function addFreight($row=[])
    {
        // "Shipping Cost (inc tax)", "Shipping Cost (ex tax)", "Ship Method"
        $method = $this->guessShipMethod($row['Ship Method']);
        $this->items[] = [
            'qty'          => 1,
            'gl_type'      => 'frt',
            'description'  => "Shipping BigCommerce # {$row['Order ID']}",
            'credit_amount'=> $row['Shipping Cost (ex tax)'],
            'gl_account'   => !empty($this->settings['gl_acct_ship']) ? $this->settings['gl_acct_ship'] : getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'),
            'tax_rate_id'  => 0,
            'post_date'    => $row['Order Date']];
        $this->main['freight'] = $row['Shipping Cost (inc tax)'];
        $this->main['method_code'] = $method;
    }

    private function addHandling($row=[])
    {
        // "Handling Cost (inc tax)", "Handling Cost (ex tax)"
        if (!empty($row['Handling Cost (inc tax)'])) {
            // @TODO - Is this used?
        }
    }

    /**
     * Sales tax is added at the order level
     * @param type $row
     */
    private function addTax($row=[])
    {
        if ($row['Tax Total'] > 0) {
            $this->items[] = [
                'qty'          => 1,
                'gl_type'      => 'glt',
                'description'  => "Sales tax collected BigCommerce # {$row['Order ID']}",
                'credit_amount'=> $row['Tax Total'],
                'gl_account'   => getModuleCache('phreebooks','settings','vendors','gl_liability'),
                'post_date'    => $row['Order Date']];
            $this->main['sales_tax'] = (float)$row['Tax Total'];
        } else {
            $this->main['sales_tax'] = 0;
        }
    }

    private function addTotal($row=[])
    {
        // "Order Total (inc tax)", "Order Total (ex tax)",
        $this->main['total_amount'] = $row['Order Total (inc tax)'];
        $this->items[] = [
            'qty'          => 1,
            'gl_type'      => 'ttl',
            'description'  => "Total BigCommerce # ".$row['Order ID'],
            'debit_amount' => $row['Order Total (inc tax)'],
            'gl_account'   => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'), // make this a setting?
            'post_date'    => $row['Order Date']];
    }

    private function guessShipMethod($method)
    {
        switch ($method) {
            case 'Federal Express (Priority Overnight)': return 'fedex:1DA';
            case 'Federal Express (Ground)':             return 'fedex:GND';
            default: return '';
        }
    }

    /**
     *
     * @global \bizuno\type $io
     * @return type
     */
    public function confirmGo()
    {
        global $io;
        if (!$security = validateAccess($this->code, 1)) { return; }
return msgAdd("This feature has not been completed at this time!");

        $cID   = isset($this->settings['contact_id']) ? $this->settings['contact_id'] : false;
        $shipDate= clean('dateShip', 'date', 'post');
        if (!$shipDate || !$cID) { return msgAdd($this->lang['err_confirm_no_contact'], 'error'); }
//      $fields = ["order-id","order-item-id","quantity","ship-date","carrier-code","carrier-name","tracking-number","ship-method"]; // these fields are the same for all templates
        // remove carrier-name as it causes warnings when used with carrier-code
        $fields= ['order-id','order-item-id','quantity','ship-date','carrier-code','tracking-number','ship-method']; // these fields are the same for all templates
        $stmt    = dbGetResult("SELECT journal_main.id, journal_meta.id, contact_id_b, post_date, meta_value
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE meta_key='shipment' AND post_date='$shipDate'");
        $result  = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $rows    = $no_dups= [];
        $carriers= getMetaMethod('carriers');
        msgDebug("\nProps for all carriers = ".print_r($carriers, true));
        foreach ($result as $row) {
            msgDebug("\nWorking on row = ".print_r($row, true));
            if ($row['contact_id_b'] <> $cID) { continue; } // it's NOT an Amazon order
            $meta   = json_decode($row['meta_value'], true);
            $method = explode(":", $meta['method_code']);
            $shpmnt = $this->extractService($row['method_code'], $carriers[$method[0]]);
            $rows[] = [
                'order-id'       => $row['purch_order_id'],
                'order-item-id'  => '',
                'quantity'       => '',
                'ship-date'      => substr($row['ship_date'], 0, 10),
                'carrier-code'   => $shpmnt['code'],
//              'carrier-name'   => $carrier, // $title, when $title OR $carrier is sent, amazon generates warnings.
                'tracking-number'=> $row['tracking_id'],
                'ship-method'    => $shpmnt['method']];
            $meta['amazon_confirm'] = 1;
            dbMetaSet($row['journal_meta.id'], 'shipment', $meta, 'journal', $row['journal_main.id']);
        }
        if (sizeof($rows) == 0) { return msgAdd($this->lang['err_no_confirm_found'], 'caution'); }
        $output   = [];
        $output[] = implode("\t", $fields); // ship confirm require tab delimited
        foreach ($rows as $row) { $output[] = implode("\t", $row); }
        msgLog($this->lang['msg_confirm_success']);
        $io->download('data', implode("\n", $output), "BigComShipConfirm-{$shipDate}.csv");
    }

    private function extractService($method_code, $services=[], $default='GND')
    {
        foreach ($services as $service) {
            if ($method_code == $service['id']) {
                $parts = explode('-', $service['text'], 2);
                return trim($parts[1]);
            }
        }
        return $default!='GND' && $default!='GDR' ? 'Expedited' : 'Standard';
    }
}
