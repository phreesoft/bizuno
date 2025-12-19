<?php
/*
 * Module api - order class
 * 
 * Handles all API operations related to importing orders, exporting status
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
 * @version    7.x Last Update: 2025-12-19
 * @filesource /controllers/api/order.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/api/common.php', 'apiCommon');

class apiOrder extends apiCommon
{
    public $moduleID = 'api';
    public $pageID   = 'order';

    function __construct($options=[]) {
        parent::__construct($options);
    }
    
    /**************** REST Endpoints to add order and set tracking info *************/
    /**
     * Adds the order received from the cart into Bizuno
     * @param type $request
     */
    public function add(&$layout=[])
    {
        $postID = $this->apiJournalEntry();
        $output = ['result'=>!empty($postID)?'Success':'Fail', 'ID'=>$postID];
        $layout= array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode($output)]);
        msgDebug("\nReturning from API order/add with layout = ".print_r($layout, true));
    }

    /*******************************************************************/
    /**
     * Posts Bizuno formatted API order to POST variables and creates a journal entry
     * @return
     */
    public function apiJournalEntry($order=[])
    {
        msgTrap();
        $layout = [];
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/main.php', 'journal');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'availableQty', 'function');
        $this->mapPost($order); // map the input to the proper post format to use existing
        msgDebug("\nModified post = ".print_r($_POST, true));
        compose('phreebooks', 'main', 'save', $layout); // compose doesn't work because user is not logged in
        msgDebug("\nAfter phrebooks compose, layout = ".print_r($layout, true));
        $this->setJournalPayment();
        return $layout['rID'];
    }

    private function mapPost($values=[])
    {
        if (empty($values)) { $values = $_POST; }
        $defjID    = getModuleCache('api', 'settings', 'bizuno_api', 'auto_detect');
        msgDebug("\nRead Auto Detect = $defjID");
        $this->jID = !empty($defjID) ? $defjID : 12; // defaults to Invoice if empty or Auto
        $account   = $this->getContactID($values['Billing']['Email']);
        $_POST['waiting'] = 1;
        $_POST['AddUpdate_b'] = 1;
        $_POST['id'] = 0; // force new journal entry
        $_POST['purch_order_id'] = $values['General']['PurchaseOrderID'];
        $_POST['store_id'] = 0;
        $_POST['rep_id'] = 0;
        $_POST['post_date'] = viewFormat($values['General']['OrderDate'], 'date');
        $_POST['terminal_date'] = viewFormat($values['General']['OrderDate'], 'date');
        $_POST['currency'] = getDefaultCurrency();
        $_POST['terms'] = 0; // should be Bizuno default
        $_POST['contact_id_b'] = $account;
        $_POST['address_id_b'] = 0; // no longer used
        $_POST['primary_name_b'] = $values['Billing']['PrimaryName'];
        $_POST['contact_b'] = $values['Billing']['Contact'];
        $_POST['address1_b'] = $values['Billing']['Address1'];
        $_POST['address2_b'] = $values['Billing']['Address2'];
        $_POST['city_b'] = $values['Billing']['City'];
        $_POST['state_b'] = $values['Billing']['State'];
        $_POST['postal_code_b'] = $values['Billing']['PostalCode'];
        $_POST['country_b'] = clean($values['Billing']['Country'], 'country');
        $_POST['telephone1_b'] = $values['Billing']['Telephone'];
        $_POST['email_b'] = $values['Billing']['Email'];
        $_POST['contact_id_s'] = $account;
        $_POST['address_id_s'] = 0;
        $_POST['primary_name_s'] = $values['Shipping']['PrimaryName'];
        $_POST['contact_s'] = $values['Shipping']['Contact'];
        $_POST['address1_s'] = $values['Shipping']['Address1'];
        $_POST['address2_s'] = $values['Shipping']['Address2'];
        $_POST['city_s'] = $values['Shipping']['City'];
        $_POST['state_s'] = $values['Shipping']['State'];
        $_POST['postal_code_s'] = $values['Shipping']['PostalCode'];
        $_POST['country_s'] = clean($values['Shipping']['Country'], 'country');
        $_POST['telephone1_s'] = $values['Shipping']['Telephone'];
        $_POST['email_s'] = $values['Shipping']['Email'];
        $itmCnt  = 1;
        $items   = [];
        $subTotal= 0;
        foreach ($values['Item'] as $item) { // Process items
            $this->getStockLevels($item['ItemID'], $item['Quantity']);
            $items[] = [
                'item_cnt'   => $itmCnt,
                'sku'        => $item['ItemID'],
                'description'=> str_replace('"', '\"', $item['Description']), // becasue of WordPress, need to add extra slashes or decode will fail
                'qty'        => $item['Quantity'],
                'gl_account' => $this->setDefGLItem(),
//              'notUsed0'   => $item['UnitPrice'],
//              'notUsed1'   => $item['SalesTaxAmount'],
                'tax_rate_id'=> 0,
                'total'      => $item['TotalPrice']];
            $subTotal += $item['TotalPrice'];
            $itmCnt++;
        } // glt
        $_POST['item_array'] = json_encode(['total'=>sizeof($items), 'rows'=>$items]);
        $_POST['journal_id'] = $_GET['jID'] = $this->jID; // must be after items so the auto journal can be set
        // Shipping
        $_POST['freight'] = $values['General']['ShippingTotal'];
        $_POST['method_code'] = $this->guessShipMethod($values['General']['ShippingCarrier']); // fedex:GND
        // Work the totals
        $_POST['totals_subtotal'] = $subTotal;
        $_POST['totals_shipping_bill_type'] = 'sender'; // for now but allows third party billing
        $_POST['totals_shipping_bill_acct'] = '';
        $_POST['totals_total_txid'] = $values['Payment']['TransactionID'];
        $_POST['total_amount'] = $values['General']['OrderTotal'];
        $_POST['gl_acct_id'] = $this->setDefGL($this->jID);
        $_POST['notes'] = $values['General']['OrderNotes'];
        // Payment map
        $_POST['pmt_method'] = $this->guessPaymentMethod($values['Payment']['Method']); // => ppcp-gateway
        $_POST['pmt_title'] = $values['Payment']['Title']; // => Credit or debit cards (via PayPal)
        $_POST['pmt_status'] = $values['Payment']['Status']; // => processing
        $_POST['pmt_transid'] = $values['Payment']['TransactionID']; // => 4RW06302HK8809324
        $this->guessTaxMethod($values);
        // Clear the Post variables now that they have been remapped.
        unset($_POST['General'], $_POST['Payment'], $_POST['Billing'], $_POST['Shipping'], $_POST['Item']);
    }
    private function getContactID($email='')
    {
        msgDebug("\nEntering getContactID with email = $email");
        if (empty($email)) { return 0; }
        $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "email='".addslashes($email)."' and ctype_c='1'");
        return !empty($cID) ? $cID : 0;

    }
    private function getStockLevels($sku, $qty)
    {
        $stock = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['inventory_type', 'qty_stock'], "sku='".addslashes($sku)."'");
        msgDebug("\nEntering getStockLevels with sku = $sku and Qty = $qty and in stock = $stock");
        if (in_array($stock['inventory_type'], INVENTORY_COGS_TYPES) && $qty > $stock['qty_stock']) { $this->jID = 10; }
    }
    private function guessPaymentMethod($fromCart='creditcard')
    {
        $test = strtolower($fromCart);
        if (strpos($test, 'payfabric')   !==false) { return 'payfabric'; }
        if (strpos($test, 'paypal')      !==false) { return 'paypal'; }
        if (strpos($test, 'ppcp-gateway')!==false) { return 'paypal'; } // returned from WordPress PayPal plugin
        if (strpos($test, 'elevon')      !==false) { return 'converge'; }
        if (strpos($test, 'converge')    !==false) { return 'converge'; }
        return $fromCart;
    }
    private function guessShipMethod($carrier)
    {
        switch (strtolower($carrier)) {
            default:
            case 'bestway':
            case 'ground': return 'fedex:GND';
            case '1day':   return 'fedex:1DA';
            case '2day':   return 'fedex:2DA';
            case 'ltl':    return 'fedex:FRT';
        }
    }
    private function guessTaxMethod($values)
    {
        if (!in_array($values['Shipping']['Country'], ['US', 'USA'])) { return; }
        if (!empty(getModuleCache('phreebooks', 'totals', 'tax_other', 'status'))) {
            $_POST['totals_tax_other']= $values['General']['SalesTaxAmount'];
            $_POST['tax_exempt']      = '1'; // disable the rest tax from being calculated
        } else {
            $_POST['totals_tax_rest'] = $values['General']['SalesTaxAmount'];
        }
    }
    private function setDefGL()
    {
        msgDebug("\nEntering setDefGL");
        if (in_array($this->jID, [3,4,6,7])) { return getModuleCache('phreebooks', 'settings', 'vendors', 'gl_payables'); }
        return getModuleCache('api','settings','bizuno_api','gl_receivables',getModuleCache('phreebooks','settings','customers','gl_receivables'));
    }

    private function setDefGLItem()
    {
        msgDebug("\nEntering setDefGLItem");
        if (in_array($this->jID, [3,4,6,7])) { return getModuleCache('phreebooks', 'settings', 'vendors', 'gl_purchases'); }
        return getModuleCache('api', 'settings', 'bizuno_api', 'gl_sales', getModuleCache('phreebooks','settings','customers','gl_sales'));
    }

    /**
     * Sets the payment status of an order
     * @return null
     */
    private function setJournalPayment()
    {
        $rID    = clean('rID', 'integer', 'post');
        if (empty($rID)) { return; }
        $method = clean('pmt_method', 'cmd', 'post');
        $title  = clean('pmt_title', 'cmd', 'post');
        $transID= clean('pmt_transid', 'cmd', 'post');
        $status = clean('pmt_status', 'cmd', 'post');
        $bizStat= in_array($status, ['auth','authorize','on-hold', 'processing']) ? 'auth': 'cap';
        $iID    = dbGetValue(BIZUNO_DB_PREFIX.'journal_item', ['id','description'], "ref_id=$rID AND gl_type='ttl'");
        $iID['description'] .= ";method:$method;title:$title;status:$bizStat";
        switch ($bizStat) {
            case 'auth':
//              if (empty($transID)) { $pmtInfo['transaction_id'] .= $pmtInfo['auth_code']; }
                if (empty($transID)) {
                    msgAdd('The order has been authorized but the Authorization Code is not present, the payment for this order must be completed in Bizuno AND at the merchant website.', 'caution');
                }
                break;
            case 'cap':
                // This can be written but needs to know the payment method, fetch the order record
                // check to make sure it was posted successfully
                // make sure it was journal 12 NOT 10, if 10 flag as payment received but product not available???
                // build the save $this->main array, try to map the merchant to get gl_account and reference_id no need to cURL merchant
                // post it, close it as it is now paid
                msgAdd('The order has been paid at the cart, the payment for this order must be completed manually in Bizuno.', 'caution');
            case 'unpaid':
            default:
        }
        dbWrite(BIZUNO_DB_PREFIX.'journal_item', ['description'=>$iID['description'],'trans_code'=>$transID], 'update', "id={$iID['id']}");
    }
}
