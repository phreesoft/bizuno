<?php
/*
 * @name Bizuno ERP - WooCommerce Interface Extension
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
 * @version    7.x Last Update: 2026-02-13
 * @filesource /controllers/api/funnels/ifWooCommerce/ifWooCommerce.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/api/export.php', 'apiExport');

class ifWooCommerce extends apiExport
{
    public    $moduleID   = 'api';
    public    $methodDir  = 'funnels';
    public    $code       = 'ifWooCommerce';
    protected $domSuffix  = 'wpWoo';
    protected $metaPrefix = 'woocommerce';
    private   $refreshRows= 100; // number of inventory items to pass in a single cron call
    private   $psServer   = 'https://www.phreesoft.com';
    private   $defaults   = ['rest_url'=>'', 'rest_user'=>'', 'rest_pass'=>'', 'inc_inactive'=>''];
    public    $settings;
    public    $lang       = ['title' => 'WooCommerce Interface',
        'acronym' => 'WooCommerce',
        'description' => 'The WooCommerce interface extension provides an interface to the WooCommerce shopping cart. Features include product upload/sync, order download/status updates and more.',
        'inc_inactive_lbl' => 'Include inactive products with the upload?',
        'bulk_upload_log' => 'WooCommerce inventory bulk upload request. %s total products to be uploaded',
        'inventory_refresh' => 'Quick refresh of pricing and stock levels (fast)',
        'upload_opt1' => 'Full Upload (Slowest - replace/regenerate all images)',
        'upload_opt2' => 'Full Product Details (Skip images if present)',
        'upload_opt3' => 'Product Data, Tags, Categories, Pricing (No Images)',
//      'upload_opt4' => 'Product Core Info (No Categories/Images)', No longer used, didn't save much time
        'upload_fltr' => 'Prefix to filter data sent (Saves time by only uploading SKU\'s that start with the specified prefix)',
        'test_tax_lbl'=>'Sales Tax Rates',
        'test_tax_desc'=>'Check with the PhreeSoft server for new sales tax rates. If new rates are available then they can be downloaded and imported into your WordPress site. Remember to update your Nexus settings (Settings -> PhreeBooks -> Nexus) as only the enabled Nexus states will be imported. Import at WooCommerce -> Settings -> Tax (Delete your existing rates first! <a target="_blank" href="https://support.taxjar.com/article/313-delete-all-tax-rates-in-woocommerce">HERE</a>)',
        'check_now'=>'Check Now',
        'get_table' => 'Download Sales Tax Table'];

    function __construct()
    {
        parent::__construct();
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $userMeta      = getMetaMethod($this->methodDir, $this->code);
        $this->settings= array_replace($this->defaults, !empty($userMeta['settings']) ? $userMeta['settings'] : []);
    }

    public function settingsStructure()
    {
        return [
            'rest_url'    =>['order'=>10,'label'=>lang('url'),     'attr'=>['value'=>$this->settings['rest_url']]],
            'rest_user'   =>['order'=>20,'label'=>lang('username'),'attr'=>['value'=>$this->settings['rest_user']]],
            'rest_pass'   =>['order'=>30,'label'=>lang('password'),'attr'=>['type'=>'password','value'=>$this->settings['rest_pass']]],
            'inc_inactive'=>['order'=>70,'label'=>$this->lang['inc_inactive_lbl'],'attr'=>['type'=>'selNoYes','value'=>$this->settings['inc_inactive']]]];
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
            'imgLogo'   => ['styles' =>['cursor'=>'pointer'], 'attr'=>['type'=>'img','height'=>50,'src'=>BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/logo.png"]],
            'radio1'    => ['order'=>20,'break' =>true,'label' =>$this->lang['upload_opt1'],'attr'=>['type'=>'radio','value'=>1,'id'=>'optUpload','name'=>'optUpload']],
            'radio2'    => ['order'=>21,'break' =>true,'label' =>$this->lang['upload_opt2'],'attr'=>['type'=>'radio','value'=>2,'id'=>'optUpload','name'=>'optUpload']],
            'radio3'    => ['order'=>22,'break' =>true,'label' =>$this->lang['upload_opt3'],'attr'=>['type'=>'radio','value'=>3,'id'=>'optUpload','name'=>'optUpload','checked'=>true]],
            'fltr'      => ['order'=>25,'break' =>true,'label' =>$this->lang['upload_fltr'],'attr'=>['size'=>6]],
            'btnInv'    => ['order'=>80,'events'=>['onClick'=>"bulkUpload();"],             'attr'=>['type'=>'button',  'value'=>lang('go')]],
            'btnQkInv'  => ['order'=>10,'events'=>['onClick'=>"jqBiz('#btnQkInv').hide(); jsonAction('$this->moduleID/admin/invRefresh&modID=$this->code');"],'attr'=>['type'=>'button','value'=>$this->lang['inventory_refresh']]],
            'selSync'   => ['order'=>10,'break' =>true,'label'=>$this->lang['sync_delete'], 'attr'=>['type'=>'checkbox','value'=>1]],
            'btnSync'   => ['order'=>80,'events'=>['onClick'=>"jsonAction('$this->moduleID/admin/cartSync&modID=$this->code&syncDelete='+bizCheckBoxGet('selSync'));"],  'attr'=>['type'=>'button','value'=>lang('go')]],
            'calConfirm'=> ['order'=>10,'break' =>true,'label'=>$this->lang['status_date'], 'attr'=>['type'=>'date',    'value'=>biz_date('Y-m-d')]],
            'btnConfirm'=> ['order'=>80,'events'=>['onClick'=>"jsonAction('$this->moduleID/admin/cartConfirm&modID=$this->code&dateShip='+jqBiz('#calConfirm').val());"],'attr'=>['type'=>'button','value'=>lang('confirm')]]];
        $data = ['title'=>$this->lang['title'],
            'divs'   => ['divIfWC'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'head'   => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR' => ['order'=> 2,'type'=>'html',  'html'=>"<br />"],
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setInv' => ['order'=>20,'type'=>'panel','key'=>'setInv', 'classes'=>['block33']],
                    'setSync'=> ['order'=>30,'type'=>'panel','key'=>'setSync','classes'=>['block33']],
                    'setConf'=> ['order'=>40,'type'=>'panel','key'=>'setConf','classes'=>['block33']],
                    'testTax'=> ['order'=>80,'type'=>'panel','key'=>'testTax','classes'=>['block33']]]]]]],
            'panels' => [
                'setInv' => ['title'=>$this->lang['upload_title'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmInv'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>{$this->lang['upload_info']}</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['radio1','radio2','radio3','fltr','btnInv']],
                    'status' => ['order'=>50,'type'=>'html',  'html'=>"<progress></progress>"],
                    'refresh'=> ['order'=>70,'type'=>'fields','keys'=>['btnQkInv']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'setSync'=> ['title'=>$this->lang['sync_title'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmSync'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>{$this->lang['sync_info']}</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['selSync','btnSync']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'setConf'=> ['title'=>$this->lang['status_title'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmConfirm'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>{$this->lang['status_info']}</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['calConfirm','btnConfirm']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]]],
            'forms'  => [
                'frmInv'    =>['attr'=>['type'=>'form','action'=>'']],
                'frmSync'   =>['attr'=>['type'=>'form','action'=>'']],
                'frmConfirm'=>['attr'=>['type'=>'form','action'=>'']]],
            'fields' => $fields,
            'jsHead' => ['init'=>$this->getViewJS()],
            'jsReady'=> ['init'=>"ajaxForm('frmInv');\najaxForm('frmSync');\najaxForm('frmConfirm');\njqBiz('progress').attr({value:0,max:100});"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Generates the JavaScript for the landing page
     * @return string - JavaScript actions
     */
    private function getViewJS()
    {
        return "var skuList = new Array();
var cnt     = 0;
var cntTotal= 0;
var cntCur  = 0;
var runaway = 0;
function bulkUpload() { // fetch the sku count
    jqBiz.ajax({
        url: '".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/apiInvCount&modID=$this->code&fltr='+bizTextGet('fltr'),
        success: function(json) {
            processJson(json);
            skuList = json.items;
            cntTotal= skuList.length;
            var id  = skuList.shift();
            if (id) {
                runaway = 999999;
                jqBiz('progress').after('".str_replace("\n", '', html5('', ['events'=>['onClick'=>"runaway=0;"],'attr'=>['type'=>'button', 'value'=>lang('cancel'), 'id'=>'upCancel']]))."');
                bizButtonOpt('upCancel', 'iconCls', 'icon-cancel');
                productUpload(id);
            } else { alert ('No items to upload!'); }
        }
    });
}
function productUpload(rID) {
    if (!rID) return;
    jqBiz.ajax({
        url: '".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/productToStore&modID=$this->code&rID='+rID+'&optUpload='+jqBiz('input[name=optUpload]:checked').val()+'&quiet=1',
        success: function(json) {
            processJson(json);
            var rID = skuList.shift();
            if (rID) {
                jqBiz('progress').attr({value:((cntCur/cntTotal)*100),max:100});
                cntCur++;
                runaway--;
                if (runaway > 0) { productUpload(rID); }
                else             { jqBiz('#upCancel').hide(); }
            } else {
                jqBiz('#upCancel').hide();
                jqBiz('progress').attr({value:100,max:100});
                alert('Bulk Upload Complete! '+cntTotal+' products uploaded');
            }
        }
    });
}";
    }

    /**
     * This method uploads a single inventory item to WooCommerce
     * @see apiImport::apiInventory()
     */
    public function productToStore($invID=0)
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'availableQty', 'function');
        $rID    = !empty($invID) ? $invID : clean('rID', 'integer', 'get');
        msgDebug("\nEntering productToStore with invID = $rID");
        if (empty($rID)) { return msgAdd('bad inventory ID passed!'); }
        $struc  = dbLoadStructure(BIZUNO_DB_PREFIX.'inventory'); // map array to table
        $result = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$rID"); // build inventory table map (may contain custom fields added by user), mask fields that cannot be updated
        $product= [];
        foreach ($result as $key => $value) { $product[$struc[$key]['tag']] = $value; }
        $product[$struc['qty_stock']['tag']] = availableQty($result); // adjust out so's and allocations.
        $product['SEO_URL']  = clean($result['description_short'], 'alpha_num'); // set the permalink url
        $product['sendMode'] = clean('optUpload','integer','get');
        $product['skuFilter']= clean('fltr',     'cmd',    'get');
        //remove invOptions field
        if (!empty($product['invOptions'])) { $product['invOptions'] = $this->prepVariations($product['SKU'], $product['invOptions']); }
        if (array_key_exists('invVendors', $product)) { $product['invVendors'] = ''; }
        $prices = $this->setPriceLevels($rID);
        $output = array_merge($product, $prices);
        msgDebug("\nReady to compose api/export/apiInventory");
        compose('api', 'export', 'apiInventory', $output);
        $args   = ['data'=>$output,'class'=>'api_product','method'=>'productImport', 'type'=>'post', 'endpoint'=>'product/update'];
        msgDebug("\nCalling portal API with product of length: ".sizeof($output));
        $resp   = $this->apiAction($args);
        $postID = !empty($resp['ID']) ? $resp['ID'] : 0;
        if (!empty($postID)) { msgAdd("Successfully imported SKU: {$output['SKU']}", 'success'); }
    }

    /**
     *
     * @param string sku
     * @param array $invOptions
     * @return array
     */
    private function prepVariations($sku, $invOptions=[])
    {
        $output= [];
        $options  = json_decode($invOptions, true);
        if (empty($options)) { return msgAdd("\nExpecting some data in InvOptions but there was an error"); }
        msgDebug("\nStart processing attributes = ".print_r($options, true));
        foreach ($options as $value) {
            $value['attrs'] = explode(';', $value['attrs']);
            $value['labels']= explode(';', $value['labels']);
            $output['attributes'][] = ['name'=>$value['option'], 'options'=>$value['labels']];
            $aAttrs[] = $value['attrs'];
            $aLbls[]  = $value['labels'];
        }
        $allSfxs = $this->combinations($aAttrs);
        msgDebug("\nFinished processing attributes, allSfxs is = ".print_r($allSfxs, true));
        $allLbls = $this->combinations($aLbls);
        msgDebug("\nFinished processing attributes, allLbls is = ".print_r($allLbls, true));
        // build all possible SKU's from options
        $variants = [];
        foreach ($allLbls as $key => $value) {
            $attributes = [];
            foreach ($value as $key1 => $lbl) { $attributes[$options[$key1]['option']] = $lbl; }
            $thisSku = $sku.'-'.implode('', $allSfxs[$key]);
            $variants[$thisSku] = $attributes;
        }
        msgDebug("\nBuilt variation array = ".print_r($variants, true));
        $sqlList = [];
        foreach (array_keys($variants) as $key) { $sqlList[] = addslashes($key); }
        $skus = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', "sku IN ('".implode("','", $sqlList)."')", '', ['id','sku','item_weight','sale_price','full_price','qty_stock']);
        msgDebug("\nRead SKUs to upload= ".print_r($skus, true));
        // prep the variation array
        foreach ($skus as $props) {
            $output['variations'][] = [
                'sku'          => $props['sku'],
//              'regular_price'=> $props['full_price'], // this needs to be the sell price
                'sale_price'   => $props['sale_price'],
                'weight'       => $props['item_weight'],
                'stock'        => $props['qty_stock'],
                'attributes'   => $variants[$props['sku']]];
        }
        msgDebug("\nFinished processing variations, output is = ".print_r($output, true));
        return $output;
    }

    private function combinations($arrays, $i = 0) {
        if (!isset($arrays[$i])) { return array(); }
        if ($i == count($arrays) - 1) { return $arrays[$i]; }
        // get combinations from subsequent arrays
        $tmp = $this->combinations($arrays, $i + 1);
        $result = array();
        foreach ($arrays[$i] as $v) {
            foreach ($tmp as $t) { $result[] = is_array($t) ? array_merge(array($v), $t) : array($v, $t); }
        }
        return $result;
    }

    /**
     * Quick inventory upload in blocks with limited daily update info
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function invRefresh(&$layout=[])
    {
        $crit = "woocommerce_sync='1'";
        if ( empty($this->settings['inc_inactive'])) { $crit .= " AND inactive='0'"; }
        $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', $crit, 'sku', ['id']); // removed , 'sku' not needed here
        if (sizeof($result) == 0) { return msgAdd("No items have been tagged to upload to your store!"); }
        foreach ($result as $row) { $rows[] = $row['id']; }
// $rows = array_slice($rows, 0, 10); // to limit the number of results for testing, comment out when ready to run
        msgDebug("\nRows to process = ".print_r($rows, true));
        setUserCron('invRefresh', ['cnt'=>0,'acted'=>0,'total'=>sizeof($rows),'rows'=>$rows]);
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"cronInit('invRefresh','$this->moduleID/admin/invRefreshNext&modID=$this->code');"]]);
    }

    /**
     * Execution of the next cron step of inventory refresh
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function invRefreshNext(&$layout=[])
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'availableQty', 'function');
        $cron   = getUserCron('invRefresh');
        $numRows= $this->refreshRows;
        $data   = [];
        while ($numRows > 0) {
            $skuID  = array_shift($cron['rows']);
            if (empty($skuID)) { break; }
            $data[] = $this->setPriceLevels($skuID);
            $numRows--;
            $cron['cnt']++;
        }
        msgDebug("\nReady to send number of rows = ".sizeof($data));
        $args = ['data'=>$data, 'class'=>'api_product', 'method'=>'productRefresh', // local
            'type'=>'put', 'endpoint'=>'product/refresh']; // RESTful
        $resp = $this->apiAction($args);
        $cron['acted'] += $resp['acted'];
        msgDebug("\nresp = ".print_r($resp, true));
        if (sizeof($cron['rows']) == 0) {
            msgLog("Completed {$cron['total']} inventory items.)");
            $message= "Processed {$cron['total']} total items, updated {$cron['acted']}.".(!empty($resp['note'])?"<br />{$resp['note']}":'');
            $data   = ['content'=>['percent'=>100,'msg'=>$message,'baseID'=>'invRefresh','urlID'=>"$this->moduleID/admin/invRefreshNext&modID=$this->code"]];
            clearUserCron('invRefresh');
        } else { // return to update progress bar and start next step
            $percent= floor(100*$cron['cnt']/$cron['total']);
            $message= "Completed {$cron['cnt']} of {$cron['total']} inventory items.".(!empty($resp['note'])?"<br />{$resp['note']}":'');
            $data   = ['content'=>['percent'=>$percent,'msg'=>$message,'baseID'=>'invRefresh','urlID'=>"$this->moduleID/admin/invRefreshNext&modID=$this->code"]];
            setUserCron('invRefresh', $cron);
        }
        $layout = array_replace_recursive($layout, $data);
    }

    private function setPriceLevels($skuID)
    {
        $fields = ['id', 'sku', 'qty_stock', 'qty_so', 'qty_alloc', 'full_price', 'inventory_type', 'item_weight'];
        $result = dbGetValue(BIZUNO_DB_PREFIX.'inventory', $fields, "id=$skuID");
        $stock  = availableQty($result);
        $pDetails['args'] = ['iID'=>$skuID];
        compose('inventory', 'prices', 'quote', $pDetails);
        msgDebug("\nAfter compose with pDetails = ".print_r($pDetails, true));
        $product= [
            'SKU'         => $result['sku'],
            'QtyStock'    => $stock,
            'Weight'      => $result['item_weight'],
            'Type'        => $result['inventory_type'],
            'FullPrice'   => $result['full_price'],
            'Price'       => $pDetails['content']['price'],
            'RegularPrice'=> $pDetails['content']['price'],
            'SalePrice'   => !empty($pDetails['content']['sale_price']) ? $pDetails['content']['sale_price'] : ''];
        $tiers = $this->updateByItem($pDetails, $stock);
        if (!empty($tiers)) { $product['PriceTiers'] = $tiers; }
        msgDebug("\nWorking with product: ".print_r($product, true));
        return $product;
    }

    private function updateByItem($prices, $stock=0)
    {
        msgDebug("\nEntering updateByItem with stock = $stock"); // with prices = ".print_r($prices, true));
        if (empty($prices['content']['levels'])) { return; }
        $sellQtys= [];
        if (sizeof($prices['content']['levels'])==1) { $prices['content']['levels'][0]['default'] = 1; }
        foreach ($prices['content']['levels'] as $sheet) {
            if (empty($sheet['default'])) { continue; } // needs to be a default sheet
            if (sizeof($sheet['sheets'])==1) { continue; } // probably a fixed price so move on to the next one
            foreach ($sheet['sheets'] as $level) {
                if (empty($level['qty']) || empty($level['price'])) { continue; } // skip empty rows
                $sellQtys[] = ['qty'=>$level['qty'], 'price'=>round($level['price'], 2)]; // this will use the last default sheet if multiple defaults are selected.
            }
        }
        msgDebug("\nCleaning up priceTiers resulted in the number of rows: ".sizeof($sellQtys));
        return $sellQtys;
    }

    /**
     * 
     * @see apiImport::apiInvCount()
     */
    public function apiInvCount(&$layout=[], $result=[])
    {
        $output = [];
        if (empty($result)) {
            $fltr = clean('fltr', 'cmd', 'get');
            $crit = "woocommerce_sync='1'"; // Removed AND inactive='0' to upload all no matter what the status, status is handled at the cart
            if ( empty($this->settings['inc_inactive'])) { $crit .= " AND inactive='0'"; }
            if (!empty($fltr)) { $crit .= " AND sku LIKE '$fltr%'"; }
            $result = dbGetMulti(BIZUNO_DB_PREFIX.'inventory', $crit, '', $field='id');
        }
        foreach ($result as $row) { $output[] = $row['id']; }
        $layout = array_replace_recursive($layout, ['content' => ['items' => $output]]);
        msgLog(sprintf($this->lang['bulk_upload_log'], sizeof($layout['content']['items'])));
    }

    /**
     * Synchronizes the products listed in the store with the products expected to be there according to the Bizuno settings.
     */
    public function cartSync(&$layout=[])
    {
        msgDebug("\nWorking in cartSync with settings = ".print_r($this->settings, true));
        $layout = ['data'=>['syncTag'=>'woocommerce_sync']];
        compose('api', 'export', 'apiSync', $layout);
        $args = ['data'=>$layout['data'], 'class'=>'api_product', 'method'=>'productSync', // local
            'type'=>'post', 'endpoint'=>'product/sync']; // RESTful
        $this->apiAction($args);
    }

    /**
     * This method uploads all shipping tracking information for orders on a given day
     * @see
     */
    public function cartConfirm()
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/shipping/functions.php', 'getCarrierText', 'function');
        $output  = ['head'=>[], 'body'=>[]];
        $shipDate= clean('dateShip', 'date', 'get');
        msgDebug("\nEntering cartConfirm with ship_date = $shipDate and settings = ".print_r($this->settings, true));
        $stmt    = dbGetResult("SELECT journal_main.id, journal_meta.id, invoice_num, method_code, purch_order_id, meta_value
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
            WHERE meta_key='shipment' AND post_date='$shipDate'");
        $rows    = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        foreach ($rows as $row) {
            $meta = json_decode($row['meta_value'], true);
            $meth = getCarrierText($row['method_code']);
            $output['head'][$row['purch_order_id']] = 'Shipped '.viewFormat(substr($meta['ship_date'], 0, 10), 'date')." via $meth, tracking number(s):";
            $trackNum = [];
            foreach ((array)$meta['packages']['rows'] as $pkg) { $trackNum[] = $pkg['tracking_id']; }
            $output['body'][$row['purch_order_id']] = implode(', ', $trackNum);
        } 
        msgDebug("\nReady to send cart confirmation with output = ".print_r($output, true));
        $args = ['data'=>$output, 'class'=>'api_order', 'method'=>'shipConfirm', // local
            'type'=>'post', 'endpoint'=>'order/confirm']; // RESTful
        $this->apiAction($args);
    }

    /**
     * Preps the request to a remote WordPress server hosting the e-store
     * @param array $layout
     * @param array $args
     * @return type
     */
    public function apiAction($args=[])
    {
        global $io;
        $io->restHeaders = ['email'=>$this->settings['rest_user'], 'pass'=>$this->settings['rest_pass']];
        $resp = $io->restRequest($args['type'], $this->settings['rest_url'], "wp-json/bizuno-api/v1/{$args['endpoint']}", ['data'=>$args['data']]);
        msgDebug("\napiAction received back from REST: ".print_r($resp, true));
        if (isset($resp['message'])) {
            if (is_string($resp['message'])) { msgAdd($resp['message'], 'info'); } // probably an error
            else                             { msgMerge($resp['message']); }
        }
        return $resp;
    }
    
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang));
    }
    public function install()
    {
        $lbl = sprintf($this->lang['cart_sync'],$this->lang['acronym']);
        $cat = sprintf($this->lang['cart_cat'], $this->lang['acronym']);
        $tag = sprintf($this->lang['cart_tags'],$this->lang['acronym']);
        $slug= sprintf($this->lang['cart_slug'],$this->lang['acronym']);
        $id  = validateTab('inventory', lang('estore'), 90);
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_sync")) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD {$this->metaPrefix}_sync ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;label:$lbl;tag:{$this->metaPrefix}Sync;tab:$id;order:25;group:{$this->metaPrefix}'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_category")) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD {$this->metaPrefix}_category VARCHAR(255) DEFAULT NULL COMMENT 'label:$cat;tag:{$this->metaPrefix}Category;tab:$id;order:26;group:{$this->metaPrefix}'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_tags")) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD {$this->metaPrefix}_tags VARCHAR(255) DEFAULT NULL COMMENT 'label:$tag;tag:{$this->metaPrefix}Tags;tab:$id;order:27;group:{$this->metaPrefix}'");
        }
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_slug")) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD {$this->metaPrefix}_slug VARCHAR(128) DEFAULT NULL COMMENT 'label:$slug;tag:{$this->metaPrefix}Slug;tab:$id;order:28;group:{$this->metaPrefix}'");
        }
        parent::installStoreFields();
        return true;
    }
    public function remove()
    {
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_sync"))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP {$this->metaPrefix}_sync"); }
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_category")) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP {$this->metaPrefix}_category"); }
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_tags"))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP {$this->metaPrefix}_tags"); }
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', "{$this->metaPrefix}_slug"))     { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP {$this->metaPrefix}_slug"); }
//        parent::removeStoreFields();
        return true;
    }
}
