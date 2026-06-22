<?php
/*
 * Bizuno Extension - Walmart.com Marketplace Interface (REST API v3)
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
 * @version    7.x Last Update: 2026-06-20
 * @filesource /controllers/api/funnels/walmart/walmart.php
 *
 * Implements the Walmart Marketplace REST API v3:
 *   Auth     - POST /v3/token (OAuth2 client-credentials, Basic auth)
 *   Orders   - GET  /v3/orders/released, POST /v3/orders/{po}/acknowledge
 *   Shipping - POST /v3/orders/{po}/shipping
 *   Inventory- PUT  /v3/inventory?sku=, PUT /v3/price
 *   Items    - POST /v3/feeds?feedType=MP_ITEM, GET /v3/feeds/{id}
 *   Settle   - GET  /v3/report/reconreport/availableReconFiles, .../reconFile
 *
 * NOTE: exact request/response field names are coded from the published Walmart v3
 * spec and MUST be validated against the Walmart sandbox before production use.
 * Set environment=sandbox in the funnel settings to exercise against sandbox.
 */

namespace bizuno;

class walmart extends apiExport
{
    public    $moduleID   = 'api';
    public    $methodDir  = 'funnels';
    public    $code       = 'walmart';
    protected $metaPrefix = 'walmart';
    private   $refreshRows = 50;  // SKUs pushed per inventory cron step
    public    $defaults;
    public    $settings;
    public    $lang = ['title' => 'Walmart Interface',
        'acronym'     => 'Walmart',
        'description' => 'The Walmart Marketplace interface connects to the Walmart REST API (v3) to download orders, confirm shipments, push inventory and pricing, set up items and reconcile settlement payments.',
        // panel titles / descriptions
        'import_orders'        => 'Import Orders',
        'import_orders_desc'   => 'Pull new released Walmart orders since the date below and post them to PhreeBooks. For unattended imports, point your server cron at the secured URL shown under Settings.',
        'inventory_sync'       => 'Inventory &amp; Price Sync',
        'inventory_sync_desc'  => 'Push available quantity and current price to Walmart for every item flagged with the catalog link field.',
        'confirm_shipments'    => 'Confirm Shipments',
        'confirm_shipments_desc'=> 'Select the ship date and press Go to send tracking to Walmart for orders shipped that day.',
        'item_setup'           => 'Item Setup Feed',
        'item_setup_desc'      => 'Build and submit a Walmart MP_ITEM product feed from your catalog using the selected template map.',
        'recon_title'          => 'Settlement Reports',
        'recon_desc'           => 'List and import Walmart settlement (recon) reports to reconcile marketplace payments.',
        'open_orders'          => 'Open Walmart Sales Orders',
        // messages
        'walmart_post_success' => 'Successfully posted %u Walmart order(s)!',
        'msg_confirm_success'  => 'Walmart order confirmation transmitted.',
        'msg_order_long_data'  => 'The ship-to name or address for order # %s, customer %s exceeds the journal field size and was truncated. Verify the address manually.',
        'msg_template_created' => 'Your template file has been written, you may now assign Bizuno fields to the Walmart fields.',
        'msg_no_orders'        => 'No new Walmart orders were found to import.',
        'msg_feed_submitted'   => 'Walmart item feed submitted, feed ID: %s',
        'msg_cron_url'         => 'Scheduled import URL (add to your server cron):',
        // errors
        'err_no_creds'      => 'Walmart API credentials are not configured. Enter the Client ID and Client Secret in Module Administration -> Walmart Interface settings.',
        'err_token'         => 'Could not obtain a Walmart access token. Verify the Client ID/Secret and environment.',
        'err_no_contact'    => 'Could not find the Walmart customer contact. Select a customer in the Walmart settings.',
        'err_dup_order'     => 'Walmart order # %s is already posted to Bizuno, it will be skipped.',
        'err_confirm_no_contact' => 'Contact ID / ship date could not be found, nothing was transmitted.',
        'err_no_confirm_found'   => 'No Walmart orders were shipped on the date selected.',
        'err_no_inv_rows'   => 'No inventory items are flagged for the Walmart catalog.',
        // template builder
        'walmart_maps'      => 'Walmart Templates',
        'walmart_field'     => 'Walmart Feed Index',
        'bizuno_field'      => 'Bizuno Inventory Field',
        'err_no_inv_tpl'    => 'Cannot find Walmart template file for template: %s',
        'walmart_template_desc' => 'Walmart templates map your Bizuno inventory fields to Walmart MP_ITEM fields. Select a category and press the field rows to assign a Bizuno field, then Save.',
        // settings labels
        'client_id_lbl'    => 'Client ID',
        'client_secret_lbl'=> 'Client Secret',
        'environment_lbl'  => 'Environment',
        'channel_type_lbl' => 'Channel Type',
        'ship_node_lbl'    => 'Ship Node',
        'contact_id_lbl'   => 'Customer ID',
        'catalog_field_lbl'=> 'Inv Link Field',
        'ship_std_lbl'     => 'Standard Method',
        'ship_exp_lbl'     => 'Expedited Method',
        'gl_acct_sales_lbl'=> 'Sales GL Account',
        'gl_acct_ar_lbl'   => 'AR GL Account',
        'gl_acct_tax_lbl'  => 'Sales Tax GL Account',
        'gl_acct_disc_lbl' => 'Discount GL Account',
        'gl_acct_ship_lbl' => 'Freight GL Account',
        'auto_journal_lbl' => 'Post Type',
        // settings tips
        'client_id_tip'    => 'Client ID from the Walmart Developer Portal (API Key Management).',
        'client_secret_tip'=> 'Client Secret paired with the Client ID. Used with OAuth2 client-credentials to fetch a 15-minute access token.',
        'environment_tip'  => 'Use Sandbox while testing, Production when live.',
        'channel_type_tip' => 'Optional WM_CONSUMER.CHANNEL.TYPE / partner Channel Type, if Walmart issued one.',
        'ship_node_tip'    => 'Optional ship node id to filter orders for a specific fulfillment node.',
        'contact_id_tip'   => 'Customer contact ID assigned to all Walmart sales.',
        'catalog_field_tip'=> 'Inventory database field (typically a checkbox) used to select items to sync to Walmart.',
        'ship_std_tip'     => 'Carrier/method for standard Walmart shipments.',
        'ship_exp_tip'     => 'Carrier/method for expedited/express Walmart shipments.',
        'gl_acct_sales_tip'=> 'GL Account for recording sales.',
        'gl_acct_ar_tip'   => 'GL Account for the receivable (balancing) entry.',
        'gl_acct_tax_tip'  => 'GL Account for sales tax collected.',
        'gl_acct_disc_tip' => 'GL Account for sales discounts.',
        'gl_acct_ship_tip' => 'GL Account for freight charges.',
        'auto_journal_tip' => 'How to post each sale: Sales Order, Sale, or Auto (Sale if in stock, else Sales Order).'];

    function __construct()
    {
        parent::__construct();
        $this->defaults = [
            'client_id'=>'', 'client_secret'=>'', 'environment'=>'sandbox', 'channel_type'=>'', 'ship_node'=>'',
            'contact_id'=>0, 'catalog_field'=>'walmart', 'ship_std'=>0, 'ship_exp'=>0, 'auto_journal'=>0,
            'last_order_sync'=>'',
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
        $envOpts = [['id'=>'sandbox','text'=>'Sandbox'], ['id'=>'production','text'=>'Production']];
        $choices = [['id'=>'', 'text'=>lang('select')]];
        $meta = getMetaMethod('carriers');
        if (sizeof($meta)) {
            foreach ($meta as $settings) {
                if (!empty($settings['status']) && !empty($settings['settings']['services'])) { $choices = array_merge_recursive($choices, $settings['settings']['services']); }
            }
        }
        $data = [
            'client_id'    => ['attr'=>['value'=>$this->settings['client_id']]],
            'client_secret'=> ['attr'=>['type'=>'password','value'=>$this->settings['client_secret']]],
            'environment'  => ['values'=>$envOpts,'attr'=>['type'=>'select','value'=>$this->settings['environment']]],
            'channel_type' => ['attr'=>['value'=>$this->settings['channel_type']]],
            'ship_node'    => ['attr'=>['value'=>$this->settings['ship_node']]],
            'contact_id'   => ['defaults'=>['type'=>'c'],'attr'=>['type'=>'contact','value'=>$this->settings['contact_id']]],
            'catalog_field'=> ['events'=>['onClick'=>"walmartFields('general_catalog_field')"],'attr'=>['value'=>$this->settings['catalog_field']]],
            'ship_std'     => ['values'=>$choices, 'attr'=>['type'=>'select','value'=>$this->settings['ship_std']]],
            'ship_exp'     => ['values'=>$choices, 'attr'=>['type'=>'select','value'=>$this->settings['ship_exp']]],
            'gl_acct_sales'=> ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_sales','value'=>$this->settings['gl_acct_sales']]],
            'gl_acct_ar'   => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ar',   'value'=>$this->settings['gl_acct_ar']]],
            'gl_acct_tax'  => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_tax',  'value'=>$this->settings['gl_acct_tax']]],
            'gl_acct_disc' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_disc', 'value'=>$this->settings['gl_acct_disc']]],
            'gl_acct_ship' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ship', 'value'=>$this->settings['gl_acct_ship']]],
            'auto_journal' => ['values'=>$autoJID,'attr'=>['type'=>'select','value'=>$this->settings['auto_journal']]]];
        foreach (array_keys($data) as $key) {
            $data[$key]['label'] = !empty($this->lang[$key."_lbl"]) ? $this->lang[$key."_lbl"] : $key;
            if (!empty($this->lang[$key."_tip"])) { $data[$key]['tip'] = $this->lang[$key."_tip"]; }
        }
        return $data;
    }

    /***************************************************************************************************
     * Walmart Marketplace REST API v3 client
     ***************************************************************************************************/

    private function apiBase()
    {
        return (!empty($this->settings['environment']) && $this->settings['environment']=='production')
            ? 'https://marketplace.walmartapis.com' : 'https://sandbox.walmartapis.com';
    }

    private function correlationId()
    {
        return function_exists('bizuno\\format_uuidv4') ? format_uuidv4() : bin2hex(random_bytes(16));
    }

    /**
     * Fetches (and caches) a Walmart OAuth2 access token. Tokens live ~15 minutes; cached in the
     * shared bizuno 'rest' module cache keyed by the API base URL (mirrors io::restOauthToken caching).
     * @return string|null token, or null (with msgAdd) on failure
     */
    private function getToken()
    {
        global $io;
        $base = $this->apiBase();
        $cache= getModuleCache('bizuno', 'rest');
        if (!is_array($cache)) { $cache = []; }
        if (!empty($cache[$base]['token']) && !empty($cache[$base]['expires_in']) && $cache[$base]['expires_in'] > time()+30) {
            return $cache[$base]['token'];
        }
        if (empty($this->settings['client_id']) || empty($this->settings['client_secret'])) { return msgAdd($this->lang['err_no_creds']); }
        $auth = base64_encode($this->settings['client_id'].':'.$this->settings['client_secret']);
        $opts = ['headers'=>[
            'Authorization'        => "Basic $auth",
            'WM_SVC.NAME'          => 'Walmart Marketplace',
            'WM_QOS.CORRELATION_ID'=> $this->correlationId(),
            'Accept'               => 'application/json',
            'Content-Type'         => 'application/x-www-form-urlencoded']];
        $raw  = $io->cURL("$base/v3/token", 'grant_type=client_credentials', 'post', $opts);
        $resp = json_decode($raw, true);
        if (empty($resp['access_token'])) { msgDebug("\nWalmart token response: ".print_r($resp, true)); return msgAdd($this->lang['err_token']); }
        $cache[$base] = ['token'=>$resp['access_token'], 'expires_in'=>time()+intval($resp['expires_in'])];
        setModuleCache('bizuno', 'rest', '', $cache);
        return $resp['access_token'];
    }

    /**
     * Performs an authenticated Walmart v3 API call.
     * @param string $method - get|post|put
     * @param string $path   - path after the base, e.g. 'v3/orders/released'
     * @param array  $query  - query string params
     * @param mixed  $body   - array (json-encoded) or raw string body for post/put
     * @return array|null decoded JSON response
     */
    private function apiCall($method, $path, $query=[], $body=null)
    {
        global $io;
        $token = $this->getToken();
        if (empty($token)) { return; }
        $base    = $this->apiBase();
        $method  = strtolower($method);
        $headers = [
            'WM_SEC.ACCESS_TOKEN'  => $token,
            'WM_QOS.CORRELATION_ID'=> $this->correlationId(),
            'WM_SVC.NAME'          => 'Walmart Marketplace',
            'WM_MARKET'            => 'us',
            'Accept'               => 'application/json',
            'Content-Type'         => 'application/json'];
        if (!empty($this->settings['channel_type'])) { $headers['WM_CONSUMER.CHANNEL.TYPE'] = $this->settings['channel_type']; }
        $opts = ['headers'=>$headers];
        if ($method == 'get') {
            $raw = $io->cURL("$base/$path", $query, 'get', $opts);
        } else {
            $url = "$base/$path";
            if (!empty($query)) { $url .= '?'.http_build_query($query); }
            $payload = is_array($body) ? json_encode($body) : (string)$body;
            $raw = $io->cURL($url, $payload, $method, $opts);
        }
        $resp = json_decode($raw, true);
        if (isset($resp['error'])) {
            foreach ($this->arrify($resp['error']) as $err) {
                $desc = !empty($err['description']) ? $err['description'] : (is_string($err) ? $err : json_encode($err));
                msgAdd("Walmart API: $desc", 'caution');
            }
        }
        return $resp;
    }

    /**
     * Normalizes a Walmart JSON node that may be a single object or a list into a numeric array.
     */
    private function arrify($node)
    {
        if (empty($node) || !is_array($node)) { return empty($node) ? [] : [$node]; }
        return (array_keys($node) === range(0, count($node)-1)) ? $node : [$node];
    }

    /**
     * Quick credential test invoked from the settings UI (route api/admin/validateCreds).
     */
    public function validateCreds(&$layout=[])
    {
        $token = $this->getToken();
        if (!empty($token)) { msgAdd("Connected to Walmart (".$this->apiBase().") successfully.", 'success'); }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>'']]);
    }

    /***************************************************************************************************
     * Landing page
     ***************************************************************************************************/

    public function home(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 1)) { return; }
        $this->journalMainSaveDefaults();
        $maps  = [];
        $files = glob(BIZUNO_DATA."data/walmart/*.map");
        if (is_array($files)) { foreach ($files as $value) {
            $tmp = str_replace([BIZUNO_DATA."data/walmart/", ".map"], '', $value);
            $maps[] = ['id'=>$tmp, 'text'=>$tmp];
        } }
        $cronURL = BIZUNO_URL_FS."?bizRt=portal/api/funnelCron&modID=$this->code&token=YOUR_API_TOKEN";
        $fields = [
            'imgLogo'    => ['styles'=>['cursor'=>'pointer'],'events'=>['onClick'=>"winHref('https://seller.walmart.com');"],'attr'=>['type'=>'img','height'=>50,'src'=>BIZUNO_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/logo.jpg"]],
            'dateOrders' => ['classes'=>['easyui-datebox'],'attr'=>['type'=>'date','value'=>$this->defaultSince()]],
            'btnOrders'  => ['events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/admin/ordersGo&modID=$this->code&since='+jqBiz('#dateOrders').val());"],'attr'=>['type'=>'button','value'=>lang('go')]],
            'cronNote'   => ['order'=>40,'html'=>'<small>'.$this->lang['msg_cron_url'].'<br /><code>'.$cronURL.'</code></small>','attr'=>['type'=>'raw']],
            'btnInv'     => ['events'=>['onClick'=>"jsonAction('$this->moduleID/admin/invRefresh&modID=$this->code');"],'attr'=>['type'=>'button','value'=>lang('go')]],
            'dateShip'   => ['classes'=>['easyui-datebox'],'attr'=>['type'=>'date','value'=>biz_date('Y-m-d')]],
            'btnConfirm' => ['events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/admin/confirmGo&modID=$this->code&dateShip='+jqBiz('#dateShip').val());"],'attr'=>['type'=>'button','value'=>lang('go')]],
            'selMap'     => ['values'=>$maps,'attr'=>['type'=>'select']],
            'btnItem'    => ['events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/admin/itemFeed&modID=$this->code&map='+jqBiz('#selMap').val());"],'attr'=>['type'=>'button','value'=>lang('go')]],
            'btnRecon'   => ['events'=>['onClick'=>"jsonAction('$this->moduleID/admin/reconcileList&modID=$this->code');"],'attr'=>['type'=>'button','value'=>lang('go')]]];
        $data = ['title'=>$this->lang['title'],
            'divs'=>[
                'head'   => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR' => ['order'=> 2,'type'=>'html','html'=>"<br />"],
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setOrder'=> ['order'=>10,'type'=>'panel','key'=>'setOrder','classes'=>['block33']],
                    'setInv'  => ['order'=>20,'type'=>'panel','key'=>'setInv',  'classes'=>['block33']],
                    'setShip' => ['order'=>30,'type'=>'panel','key'=>'setShip', 'classes'=>['block33']],
                    'setItem' => ['order'=>40,'type'=>'panel','key'=>'setItem', 'classes'=>['block33']],
                    'setRecon'=> ['order'=>50,'type'=>'panel','key'=>'setRecon','classes'=>['block33']]]],
                'orders' => ['order'=>70,'type'=>'panel','key'=>'dgOrders']],
            'panels'=>[
                'setOrder'=> ['title'=>$this->lang['import_orders'],'type'=>'divs','divs'=>[
                    'desc'=> ['order'=>10,'type'=>'html','html'=>"<p>".$this->lang['import_orders_desc']."</p>"],
                    'body'=> ['order'=>20,'type'=>'fields','keys'=>['dateOrders','btnOrders','cronNote']]]],
                'setInv'  => ['title'=>$this->lang['inventory_sync'],'type'=>'divs','divs'=>[
                    'desc'=> ['order'=>10,'type'=>'html','html'=>"<p>".$this->lang['inventory_sync_desc']."</p>"],
                    'body'=> ['order'=>20,'type'=>'fields','keys'=>['btnInv']]]],
                'setShip' => ['title'=>$this->lang['confirm_shipments'],'type'=>'divs','divs'=>[
                    'desc'=> ['order'=>10,'type'=>'html','html'=>"<p>".$this->lang['confirm_shipments_desc']."</p>"],
                    'body'=> ['order'=>20,'type'=>'fields','keys'=>['dateShip','btnConfirm']]]],
                'setItem' => ['title'=>$this->lang['item_setup'],'type'=>'divs','divs'=>[
                    'desc'=> ['order'=>10,'type'=>'html','html'=>"<p>".$this->lang['item_setup_desc']."</p>"],
                    'body'=> ['order'=>20,'type'=>'fields','keys'=>['selMap','btnItem']]]],
                'setRecon'=> ['title'=>$this->lang['recon_title'],'type'=>'divs','divs'=>[
                    'desc'=> ['order'=>10,'type'=>'html','html'=>"<p>".$this->lang['recon_desc']."</p>"],
                    'body'=> ['order'=>20,'type'=>'fields','keys'=>['btnRecon']]]],
                'dgOrders'=> ['title'=>$this->lang['open_orders'],'type'=>'datagrid','key'=>'dgWalmart']],
            'datagrid'=>['dgWalmart'=>$this->dgWalmart()],
            'fields'  => $fields,
            'jsHead'  => ['init'=>"jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/$this->methodDir/$this->code/$this->code.js?ver=".MODULE_BIZUNO_VERSION."');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    private function defaultSince()
    {
        if (!empty($this->settings['last_order_sync'])) { return substr($this->settings['last_order_sync'], 0, 10); }
        return biz_date('Y-m-d', strtotime('-7 days'));
    }

    /**
     * Server-side datagrid listing posted Walmart sales orders for the configured customer.
     */
    private function dgWalmart()
    {
        $cID = (int)$this->settings['contact_id'];
        return ['id'=>'dgWalmart','rows'=>getModuleCache('bizuno','settings','general','max_rows'),'page'=>1,
            'attr'  => ['idField'=>'id','url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/ordersData&modID=$this->code"],
            'source'=> ['tables'=>['j'=>['table'=>BIZUNO_DB_PREFIX.'journal_main']],
                'search'=> [BIZUNO_DB_PREFIX.'journal_main.purch_order_id', BIZUNO_DB_PREFIX.'journal_main.primary_name_s'],
                'filters'=> [
                    'cust' => ['hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_main.contact_id_b='$cID'"],
                    'jrnl' => ['hidden'=>true,'sql'=>BIZUNO_DB_PREFIX."journal_main.journal_id IN (10,12)"]],
                'sort'  => ['s0'=>['order'=>10,'field'=>BIZUNO_DB_PREFIX.'journal_main.post_date DESC']]],
            'columns'=> [
                'id'            => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX.'journal_main.id','attr'=>['hidden'=>true]],
                'purch_order_id'=> ['order'=>10,'field'=>BIZUNO_DB_PREFIX.'journal_main.purch_order_id','label'=>'Walmart PO #','attr'=>['width'=>140,'sortable'=>true]],
                'invoice_num'   => ['order'=>20,'field'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num','label'=>lang('journal_main_invoice_num'),'attr'=>['width'=>120,'sortable'=>true]],
                'post_date'     => ['order'=>30,'field'=>BIZUNO_DB_PREFIX.'journal_main.post_date','label'=>lang('journal_main_post_date'),'attr'=>['width'=>100,'sortable'=>true]],
                'primary_name_s'=> ['order'=>40,'field'=>BIZUNO_DB_PREFIX.'journal_main.primary_name_s','label'=>lang('ship_to'),'attr'=>['width'=>200]],
                'total_amount'  => ['order'=>50,'field'=>BIZUNO_DB_PREFIX.'journal_main.total_amount','label'=>lang('total'),'format'=>'currency','attr'=>['width'=>110,'align'=>'right','sortable'=>true]]]];
    }

    /**
     * Datagrid data feed for the open orders grid (route api/admin/ordersData).
     */
    public function ordersData(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 1)) { return; }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgWalmart','datagrid'=>['dgWalmart'=>$this->dgWalmart()]]);
    }

    /***************************************************************************************************
     * Orders - download and post
     ***************************************************************************************************/

    /**
     * Manual "Run now" order pull (route api/admin/ordersGo).
     */
    public function ordersGo(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 2)) { return; }
        $since = clean('since', 'date', 'get');
        if (empty($since)) { $since = $this->defaultSince(); }
        $cnt = $this->ordersGet($since);
        if ($cnt !== false) {
            msgAdd($cnt ? sprintf($this->lang['walmart_post_success'], $cnt) : $this->lang['msg_no_orders'], 'success');
            msgLog(sprintf($this->lang['walmart_post_success'], (int)$cnt));
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading'); bizGridReload('dgWalmart');"]]);
    }

    /**
     * Unattended order pull invoked by the secured cron endpoint
     * (portal/api/funnelCron -> api/admin/cronGet). No interactive security check: the portal
     * layer authenticates via api token and binds the API user context before composing here.
     */
    public function cronGet(&$layout=[])
    {
        $since = !empty($this->settings['last_order_sync']) ? substr($this->settings['last_order_sync'], 0, 10) : $this->defaultSince();
        $cnt   = $this->ordersGet($since);
        msgLog("Walmart cron import complete, posted ".(int)$cnt." order(s).");
        $layout = array_replace_recursive($layout, ['content'=>['posted'=>(int)$cnt]]);
    }

    /**
     * Pulls released Walmart orders created on/after $since, posts each to PhreeBooks, acknowledges,
     * and advances the last_order_sync watermark.
     * @param string $since - Y-m-d
     * @return int|false number of orders posted, or false on hard failure
     */
    private function ordersGet($since)
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/journal.php", 'journal');
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/functions.php", 'phreebooksProcess', 'function');
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/inventory/functions.php",  'getStoreStock',     'function');
        $contact = $this->billToContact();
        if (empty($contact)) { return false; }
        $commonMain = $this->commonMain($contact);
        $startISO = gmdate('Y-m-d\TH:i:s\Z', strtotime($since));
        $endpoint = 'v3/orders/released';
        $query    = ['createdStartDate'=>$startISO, 'limit'=>100];
        if (!empty($this->settings['ship_node'])) { $query['shipNode'] = $this->settings['ship_node']; }
        $posted  = 0;
        $runaway = 0;
        do {
            $resp   = $this->apiCall('get', $endpoint, $query);
            if (!is_array($resp)) { break; }
            $orders = isset($resp['list']['elements']['order']) ? $this->arrify($resp['list']['elements']['order']) : [];
            foreach ($orders as $order) {
                $result = $this->postOrder($order, $commonMain);
                if ($result === true) {
                    $posted++;
                    $this->acknowledge($order['purchaseOrderId']);
                }
            }
            $cursor = !empty($resp['list']['meta']['nextCursor']) ? $resp['list']['meta']['nextCursor'] : '';
            if ($cursor) { $endpoint = 'v3/orders'; parse_str(ltrim($cursor, '?'), $query); }
        } while ($cursor && $runaway++ < 200);
        $this->saveSetting('last_order_sync', biz_date('Y-m-d H:i:s'));
        return $posted;
    }

    /**
     * Maps a single Walmart order JSON object to a balanced PhreeBooks journal and posts it.
     * @return true on post, 'dup' if already posted, false on error
     */
    private function postOrder($order, $commonMain)
    {
        $po = isset($order['purchaseOrderId']) ? $order['purchaseOrderId'] : '';
        if ($po === '') { return false; }
        $dup = dbGetValue(BIZUNO_DB_PREFIX."journal_main", "id", "purch_order_id='".addslashes($po)."'");
        if ($dup) { msgAdd(sprintf($this->lang['err_dup_order'], $po), 'caution'); return 'dup'; }
        $ship = isset($order['shippingInfo']) ? $order['shippingInfo'] : [];
        $addr = isset($ship['postalAddress']) ? $ship['postalAddress'] : [];
        $postDate = !empty($order['orderDate']) ? $this->msToDate($order['orderDate']) : biz_date('Y-m-d');
        $main = $commonMain;
        $main['purch_order_id'] = $po;
        $main['description']    = "Walmart Order # $po";
        $main['method_code']    = $this->shipMethodApi(isset($ship['methodCode']) ? $ship['methodCode'] : '');
        $main['primary_name_s'] = $this->v($addr, 'name');
        $main['address1_s']     = $this->v($addr, 'address1');
        $main['address2_s']     = $this->v($addr, 'address2');
        $main['contact_s']      = '';
        $main['city_s']         = $this->v($addr, 'city');
        $main['state_s']        = $this->localeProcess($this->v($addr, 'state'), 'state');
        $main['postal_code_s']  = $this->v($addr, 'postalCode');
        $main['country_s']      = $this->v($addr, 'country') ? $this->v($addr, 'country') : 'USA';
        $main['telephone1_s']   = $this->cleanPhone($this->v($ship, 'phone'));
        $main['email_s']        = $this->v($order, 'customerEmailId');
        foreach ([$main['primary_name_s'], $main['address1_s'], $main['address2_s']] as $chk) {
            if (strlen($chk) > 32) { msgAdd(sprintf($this->lang['msg_order_long_data'], $po, $main['primary_name_s']), 'caution'); break; }
        }
        $lines   = isset($order['orderLines']['orderLine']) ? $this->arrify($order['orderLines']['orderLine']) : [];
        if (empty($lines)) { return msgAdd("Walmart order $po has no order lines, skipped.", 'caution') ? false : false; }
        $items   = [];
        $itemCnt = 1;
        $totals  = ['sales_tax'=>0, 'freight'=>0, 'total_amount'=>0];
        $inStock = true;
        dbTransactionStart();
        foreach ($lines as $line) {
            $lineItem = isset($line['item']) ? $line['item'] : [];
            $sku  = $this->v($lineItem, 'sku');
            $desc = $this->v($lineItem, 'productName');
            $qty  = $this->num($this->v(isset($line['orderLineQuantity']) ? $line['orderLineQuantity'] : [], 'amount'));
            if ($qty <= 0) { $qty = 1; }
            $inv  = $sku !== '' ? dbGetRow(BIZUNO_DB_PREFIX."inventory", "sku='".addslashes($sku)."'") : [];
            if (empty($inv)) {
                dbTransactionRollback();
                return msgAdd("SKU $sku from Walmart order $po cannot be found in Bizuno. No transaction posted for this order.") ? false : false;
            }
            // sum charges: PRODUCT -> price, SHIPPING -> freight, any charge tax -> sales tax
            $price = 0;
            foreach ($this->arrify(isset($line['charges']['charge']) ? $line['charges']['charge'] : []) as $charge) {
                $amt = $this->num($this->v(isset($charge['chargeAmount']) ? $charge['chargeAmount'] : [], 'amount'));
                $tax = $this->num($this->v(isset($charge['tax']['taxAmount']) ? $charge['tax']['taxAmount'] : [], 'amount'));
                $totals['sales_tax'] += $tax;
                if (($this->v($charge, 'chargeType')) == 'SHIPPING') { $totals['freight'] += $amt; }
                else { $price += $amt; } // PRODUCT (and any other non-shipping charge) counts toward the line
            }
            if (!$this->findStock($main, $inv, $qty)) { $inStock = false; }
            $items[] = [
                'item_cnt'      => $itemCnt,
                'gl_type'       => 'itm',
                'sku'           => $sku,
                'qty'           => $qty,
                'description'   => $desc,
                'credit_amount' => $price,
                'gl_account'    => $this->settings['gl_acct_sales'] ? $this->settings['gl_acct_sales'] : $inv['gl_sales'],
                'tax_rate_id'   => 0,
                'full_price'    => $inv['full_price'],
                'post_date'     => $postDate];
            $itemCnt++;
            $totals['total_amount'] += $price;
        }
        $totals['total_amount'] += $totals['sales_tax'] + $totals['freight'];
        $main['total_amount']    = $totals['total_amount'];
        $items[] = ['qty'=>1,'gl_type'=>'frt','description'=>"Shipping Walmart # $po",'credit_amount'=>$totals['freight'],
            'gl_account'=>!empty($this->settings['gl_acct_ship']) ? $this->settings['gl_acct_ship'] : getModuleCache('shipping','settings','general','gl_shipping_c'),
            'tax_rate_id'=>0,'post_date'=>$postDate];
        if ($totals['sales_tax'] > 0) { $items[] = ['qty'=>1,'gl_type'=>'glt','description'=>"Sales tax collected Walmart # $po",'credit_amount'=>$totals['sales_tax'],
            'gl_account'=>!empty($this->settings['gl_acct_tax']) ? $this->settings['gl_acct_tax'] : getModuleCache('phreebooks','settings','vendors','gl_liability'),
            'post_date'=>$postDate]; }
        $items[] = ['qty'=>1,'gl_type'=>'ttl','description'=>"Total Walmart # $po",'debit_amount'=>$totals['total_amount'],
            'gl_account'=>!empty($this->settings['gl_acct_ar']) ? $this->settings['gl_acct_ar'] : getModuleCache('phreebooks','settings','customers','gl_receivables'),
            'post_date'=>$postDate];
        switch ($this->settings['auto_journal']) {
            case '10': $jID = 10; break;
            case '12': $jID = 12; break;
            default:   $jID = $inStock ? 12 : 10; break;
        }
        $strucMain = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main');
        $strucItem = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_item');
        $ledger = new journal(0, $jID, $main['post_date']);
        $ledger->main  = array_merge($ledger->main, $main);
        validateData($strucMain, $ledger->main);
        for ($i=0; $i<sizeof($items); $i++) { validateData($strucItem, $items[$i]); }
        $ledger->items = $items;
        if (!$ledger->Post()) { dbTransactionRollback(); msgAdd("Post error on Walmart order $po."); return false; }
        $ledger->updateJournalHistory(getModuleCache('phreebooks', 'fy', 'period'));
        dbTransactionCommit();
        msgLog("Walmart order $po posted to journal $jID.");
        return true;
    }

    /**
     * Acknowledges receipt of an order to Walmart (POST /v3/orders/{po}/acknowledge).
     */
    private function acknowledge($po)
    {
        if (empty($po)) { return; }
        $resp = $this->apiCall('post', "v3/orders/".rawurlencode($po)."/acknowledge", [], '');
        msgDebug("\nWalmart acknowledge $po response: ".print_r($resp, true));
    }

    private function billToContact()
    {
        $cID = !empty($this->settings['contact_id']) ? $this->settings['contact_id'] : 0;
        if (!$cID) { msgAdd($this->lang['err_no_contact'], 'error'); return []; }
        $contact = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
        if (empty($contact)) { msgAdd($this->lang['err_no_contact'], 'error'); return []; }
        return $contact;
    }

    private function commonMain($contact)
    {
        return [
            'post_date'     => biz_date('Y-m-d'),
            'terminal_date' => biz_date('Y-m-d'),
            'waiting'       => '1',
            'terms'         => $contact['terms'],
            'store_id'      => $contact['store_id'],
            'rep_id'        => $contact['rep_id'],
            'gl_acct_id'    => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'),
            'contact_id_b'  => $contact['id'],
            'address_id_b'  => 0,
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
    }

    /***************************************************************************************************
     * Ship confirmation
     ***************************************************************************************************/

    /**
     * Transmits tracking to Walmart for every Bizuno order shipped on the selected date
     * (route api/admin/confirmGo). POST /v3/orders/{po}/shipping.
     */
    public function confirmGo(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 2)) { return; }
        $cID     = !empty($this->settings['contact_id']) ? $this->settings['contact_id'] : false;
        $shipDate= clean('dateShip', 'date', 'post');
        if (empty($shipDate)) { $shipDate = clean('dateShip', 'date', 'get'); }
        if (!$shipDate || !$cID) { return msgAdd($this->lang['err_confirm_no_contact'], 'error'); }
        $stmt = dbGetResult("SELECT journal_main.id AS mainID, journal_meta.id AS metaID, contact_id_b, purch_order_id, meta_value
            FROM ".BIZUNO_DB_PREFIX."journal_main JOIN ".BIZUNO_DB_PREFIX."journal_meta ON journal_main.id=journal_meta.ref_id
            WHERE meta_key='shipment' AND post_date='$shipDate'");
        $result  = $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : [];
        $carriers= getMetaMethod('carriers');
        $sent    = 0;
        foreach ($result as $row) {
            if ($row['contact_id_b'] <> $cID) { continue; } // not a Walmart order
            $po     = $row['purch_order_id'];
            $meta   = json_decode($row['meta_value'], true);
            $method = explode(":", $meta['method_code']);
            $shpmnt = $this->extractService($meta['method_code'], isset($carriers[$method[0]]) ? $carriers[$method[0]] : []);
            $tracks = [];
            foreach ($meta['packages']['rows'] as $pkg) {
                $tracks[] = ['carrier'=>$shpmnt['code'], 'method'=>$shpmnt['method'],
                    'number'=>$pkg['tracking_id'], 'url'=>!empty($pkg['tracking_url']) ? $pkg['tracking_url'] : ''];
            }
            if (empty($tracks)) { continue; }
            if ($this->shipOrder($po, $tracks, $meta['ship_date'])) {
                $sent++;
                $meta['walmart_confirm'] = 1;
                dbMetaSet($row['metaID'], 'shipment', $meta, 'journal', $row['mainID']);
            }
        }
        if ($sent == 0) { return msgAdd($this->lang['err_no_confirm_found'], 'caution'); }
        msgAdd($this->lang['msg_confirm_success'], 'success');
        msgLog($this->lang['msg_confirm_success']." ($sent order(s))");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    /**
     * Builds and POSTs the orderShipment payload for one purchase order. Fetches the live order to
     * resolve Walmart line numbers/quantities, then marks every line Shipped with the tracking data.
     */
    private function shipOrder($po, $tracks, $shipDate)
    {
        $order = $this->apiCall('get', "v3/orders/".rawurlencode($po));
        $oData = isset($order['order']) ? $order['order'] : (isset($order['list']['elements']['order']) ? $this->arrify($order['list']['elements']['order'])[0] : $order);
        $lines = isset($oData['orderLines']['orderLine']) ? $this->arrify($oData['orderLines']['orderLine']) : [];
        if (empty($lines)) { msgAdd("Walmart order $po could not be retrieved to confirm shipment.", 'caution'); return false; }
        $track  = $tracks[0]; // Walmart accepts one tracking set per line; use the first package
        $shipMs = $this->dateToMs($shipDate);
        $oLines = [];
        foreach ($lines as $line) {
            $qty = $this->v(isset($line['orderLineQuantity']) ? $line['orderLineQuantity'] : [], 'amount');
            $oLines[] = ['lineNumber'=>(string)$this->v($line, 'lineNumber'),
                'orderLineStatuses'=>['orderLineStatus'=>[[
                    'status'=>'Shipped',
                    'statusQuantity'=>['unitOfMeasurement'=>'EACH','amount'=>(string)($qty !== '' ? $qty : '1')],
                    'trackingInfo'=>[
                        'shipDateTime' => $shipMs,
                        'carrierName'  => ['carrier'=>$track['carrier']],
                        'methodCode'   => $this->wmShipMethod($track['method']),
                        'trackingNumber'=> $track['number'],
                        'trackingURL'  => $track['url']]]]]];
        }
        $body = ['orderShipment'=>['orderLines'=>['orderLine'=>$oLines]]];
        $resp = $this->apiCall('post', "v3/orders/".rawurlencode($po)."/shipping", [], $body);
        if (isset($resp['error'])) { return false; }
        return true;
    }

    /***************************************************************************************************
     * Inventory & price push (client-driven cron loop)
     ***************************************************************************************************/

    public function invRefresh(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 3)) { return; }
        $crit  = "`{$this->settings['catalog_field']}`='1' AND inactive='0'";
        $result= dbGetMulti(BIZUNO_DB_PREFIX.'inventory', $crit, 'sku', ['id']);
        if (sizeof($result) == 0) { return msgAdd($this->lang['err_no_inv_rows']); }
        $rows = [];
        foreach ($result as $row) { $rows[] = $row['id']; }
        setUserCron('wmInvRefresh', ['cnt'=>0,'acted'=>0,'total'=>sizeof($rows),'rows'=>$rows]);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"cronInit('wmInvRefresh','$this->moduleID/admin/invRefreshNext&modID=$this->code');"]]);
    }

    public function invRefreshNext(&$layout=[])
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/functions.php', 'availableQty', 'function');
        $cron   = getUserCron('wmInvRefresh');
        $numRows= $this->refreshRows;
        while ($numRows-- > 0) {
            $skuID = array_shift($cron['rows']);
            if (empty($skuID)) { break; }
            $item  = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "id=$skuID");
            if (empty($item)) { continue; }
            $qty   = availableQty($item);
            if ($this->updateInventory($item['sku'], $qty)) { $cron['acted']++; }
            $pd = ['args'=>['cID'=>$this->settings['contact_id'], 'iID'=>$skuID]];
            compose('inventory', 'prices', 'quote', $pd);
            if (!empty($pd['content']['price'])) { $this->updatePrice($item['sku'], $pd['content']['price']); }
            $cron['cnt']++;
        }
        $urlID = "$this->moduleID/admin/invRefreshNext&modID=$this->code";
        if (sizeof($cron['rows']) == 0) {
            msgLog("Completed Walmart sync of {$cron['total']} inventory items.");
            $data = ['content'=>['percent'=>100,'msg'=>"Processed {$cron['total']} items, updated {$cron['acted']}.",'baseID'=>'wmInvRefresh','urlID'=>$urlID]];
            clearUserCron('wmInvRefresh');
        } else {
            $percent = floor(100*$cron['cnt']/$cron['total']);
            $data = ['content'=>['percent'=>$percent,'msg'=>"Completed {$cron['cnt']} of {$cron['total']} items.",'baseID'=>'wmInvRefresh','urlID'=>$urlID]];
            setUserCron('wmInvRefresh', $cron);
        }
        $layout = array_replace_recursive($layout, $data);
    }

    private function updateInventory($sku, $qty)
    {
        $body = ['sku'=>$sku, 'quantity'=>['unit'=>'EACH','amount'=>(int)$qty]];
        $resp = $this->apiCall('put', 'v3/inventory', ['sku'=>$sku], $body);
        return !isset($resp['error']);
    }

    private function updatePrice($sku, $price)
    {
        $body = ['sku'=>$sku, 'pricing'=>[['currentPriceType'=>'BASE','currentPrice'=>['currency'=>'USD','amount'=>round($price, 2)]]]];
        $resp = $this->apiCall('put', 'v3/price', [], $body);
        return !isset($resp['error']);
    }

    /***************************************************************************************************
     * Item setup feed (MP_ITEM)
     ***************************************************************************************************/

    /**
     * Builds an MP_ITEM JSON feed from catalog items using the selected template map and submits it
     * (route api/admin/itemFeed). Walmart returns a feedId polled via feedStatus().
     */
    public function itemFeed(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 3)) { return; }
        $mapName = clean('map', 'text', 'get');
        if (!$mapName || !file_exists(BIZUNO_DATA."data/walmart/$mapName.map")) { return msgAdd(sprintf($this->lang['err_no_inv_tpl'], $mapName)); }
        $map    = json_decode(file_get_contents(BIZUNO_DATA."data/walmart/$mapName.map"), true);
        $dbField= $this->settings['catalog_field'];
        $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory", "inactive='0' AND `$dbField`='1'", 'sku');
        if (sizeof($result) == 0) { return msgAdd($this->lang['err_no_inv_rows']); }
        $mpItems = [];
        foreach ($result as $item) {
            $entry = [];
            foreach ($map['fields'] as $wmField => $attr) {
                if (empty($attr['value'])) { continue; }
                $entry[$wmField] = isset($item[$attr['value']]) ? $item[$attr['value']] : '';
            }
            if (!empty($entry)) { $mpItems[] = $entry; }
        }
        if (empty($mpItems)) { return msgAdd($this->lang['err_no_inv_rows']); }
        // MP_ITEM feed envelope. NOTE: required attributes are product-type specific; the map must
        // include the Walmart-required fields for the chosen category. Validate against sandbox.
        $feed = ['MPItemFeedHeader'=>['version'=>'5.0','requestBatchId'=>$this->correlationId(),'mart'=>'WALMART_US','sellingChannel'=>'mpsetupbymatch'],
                 'MPItem'=>$mpItems];
        $resp = $this->apiCall('post', 'v3/feeds', ['feedType'=>'MP_ITEM'], $feed);
        $feedID = !empty($resp['feedId']) ? $resp['feedId'] : '';
        if ($feedID) { msgAdd(sprintf($this->lang['msg_feed_submitted'], $feedID), 'success'); msgLog("Walmart MP_ITEM feed submitted: $feedID"); }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');", 'feedId'=>$feedID]]);
    }

    /**
     * Polls the status of a submitted feed (route api/admin/feedStatus).
     */
    public function feedStatus(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 1)) { return; }
        $feedID = clean('feedId', 'text', 'get');
        if (!$feedID) { return msgAdd("No feed ID supplied."); }
        $resp = $this->apiCall('get', "v3/feeds/".rawurlencode($feedID), ['includeDetails'=>'true']);
        $status= !empty($resp['feedStatus']) ? $resp['feedStatus'] : 'UNKNOWN';
        msgAdd("Walmart feed $feedID status: $status".(isset($resp['itemsReceived'])?" (received {$resp['itemsReceived']}, succeeded ".(isset($resp['itemsSucceeded'])?$resp['itemsSucceeded']:0).")":''), 'info');
        $layout = array_replace_recursive($layout, ['content'=>['status'=>$status]]);
    }

    /***************************************************************************************************
     * Settlement (recon) reports
     ***************************************************************************************************/

    /**
     * Lists available Walmart settlement recon files (route api/admin/reconcileList -> reconcileGrid).
     */
    public function reconcileGrid(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 3)) { return; }
        $resp  = $this->apiCall('get', 'v3/report/reconreport/availableReconFiles', ['reportVersion'=>'v1']);
        $dates = !empty($resp['availableApReportDates']) ? $this->arrify($resp['availableApReportDates']) : [];
        $html  = '<p>'.$this->lang['recon_desc'].'</p>';
        if (empty($dates)) {
            $html .= '<p>'.lang('msg_no_records_found').'</p>';
        } else {
            $html .= '<ul>';
            foreach ($dates as $d) {
                $html .= '<li><a href="#" onClick="jsonAction(\''.$this->moduleID.'/admin/reconcileGo&modID='.$this->code.'&reportDate='.urlencode($d).'\'); return false;">'.htmlspecialchars($d).'</a></li>';
            }
            $html .= '</ul>';
        }
        $layout = array_replace_recursive($layout, ['type'=>'divHTML','divID'=>'bizPop','content'=>['action'=>'window','id'=>'winRecon','title'=>$this->lang['recon_title'],'html'=>$html,'width'=>400,'height'=>300]]);
    }

    /**
     * Downloads a selected recon report and feeds it to the existing reconcile flow
     * (route api/admin/reconcileGo -> reconcile).
     */
    public function reconcile(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 3)) { return; }
        $reportDate = clean('reportDate', 'text', 'get');
        if (!$reportDate) { return msgAdd("No report date selected."); }
        $resp = $this->apiCall('get', 'v3/report/reconreport/reconFile', ['reportVersion'=>'v1','reportDate'=>$reportDate]);
        // Walmart returns the recon file (CSV, often within a zip). Hand the contents to the JS reconcile
        // routine, mirroring the legacy paymentProcess()/processWalmart() flow.
        $contents = is_array($resp) ? json_encode($resp) : (string)$resp;
        $output   = base64_encode($contents);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"processWalmart(json);",'payments'=>$output]]);
    }

    /**
     * Legacy hook retained so a manually uploaded recon file can still be processed by the JS flow.
     */
    public function paymentProcess(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->code, 3)) { return; }
        if (!$io->validateUpload('walmart_pmt')) { return; }
        $contents = file($_FILES['walmart_pmt']['tmp_name']);
        $contents[0] = str_replace('-', '_', $contents[0]);
        $output = base64_encode(implode("\n", $contents));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"processWalmart(json);",'payments'=>$output]]);
    }

    /**
     * Adds the Walmart payment-reconcile button to the PhreeBooks edit screen for the Walmart customer.
     */
    public function pbEdit(&$layout)
    {
        if (empty($layout['fields']['journal_id'])) { return; }
        $jID = $layout['fields']['journal_id']['attr']['value'];
        $cID = !empty($layout['fields']['contact_id_b']['attr']['value']) ? $layout['fields']['contact_id_b']['attr']['value'] : 0;
        if (empty($cID)) { return; }
        if ($jID==18 && $cID==$this->settings['contact_id']) {
            $layout['toolbars']['tbPhreeBooks']['icons']['walmart'] = ['order'=>80,'label'=>$this->lang['recon_title'],'events'=>['onClick'=>"reconcileWalmart();"]];
            $layout['jsBody']['walmart'] = "jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/funnels/$this->code/$this->code.js&ver=".MODULE_BIZUNO_VERSION."');";
        }
    }

    /***************************************************************************************************
     * Template (field map) builder - reused to drive the MP_ITEM feed
     ***************************************************************************************************/

    private function adminHome(&$layout=[])
    {
        $listWm  = glob(BIZUNO_FS_LIBRARY.'controllers/api/funnels/walmart/source/*');
        $templWm = [['id'=>'', 'text'=>lang('select')]];
        foreach ($listWm as $option) { if (is_dir($option)) {
            $tpl = substr($option, strrpos($option, '/')+1);
            $templWm[] = ['id'=>$tpl, 'text'=>$tpl];
        } }
        $layout['tabs']['tabAdmin']['divs'][$this->code] = ['order'=>85,'label'=>$this->lang['walmart_maps'],'type'=>'divs','divs'=>[
            'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'getMap' => ['order'=>20,'type'=>'panel','classes'=>['block66'],'key'=>$this->code]]]]];
        $layout['panels'][$this->code] = ['type'=>'fields','keys'=>['tplDescWm','selTempWm','divMapWm']];
        $layout['fields']['tplDescWm'] = ['order'=>10,'html'=>$this->lang['walmart_template_desc'],  'attr'=>['type'=>'raw']];
        $layout['fields']['selTempWm'] = ['order'=>20,'values'=>$templWm,'events'=>['onChange'=>"jsonAction('api/admin/templateStructure&modID=walmart', 0, bizSelGet('selTempWm'));"],'attr'=>['type'=>'select']];
        $layout['fields']['divMapWm']  = ['order'=>90,'html'=>'<div id="divWalmartMap">&nbsp;</div>','attr'=>['type'=>'raw']];
        $layout['jsHead'][$this->code] = "jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/$this->methodDir/$this->code/$this->code.js?ver=".MODULE_BIZUNO_VERSION."');";
    }

    private function loadInvTemplate($tpl, $force=true)
    {
        $file = BIZUNO_FS_LIBRARY."controllers/api/funnels/walmart/source/$tpl/$tpl.csv";
        if (!file_exists($file)) { msgAdd(sprintf($this->lang['err_no_inv_tpl'], $tpl)); return ['header'=>'','fields'=>[],'groups'=>[]]; }
        $tmp    = array_map('str_getcsv', file($file));
        $titles = array_shift($tmp);
        $output = ['header'=>implode("\t", $titles), 'fields'=>[], 'groups'=>[]];
        foreach ($titles as $title) {
            $values = $this->getTitleDetails($title);
            if ($values['title'] === '') { continue; }
            $output['fields'][$values['title']] = ['label'=>$values['label'], 'group'=>0, 'required'=>$values['required'], 'count'=>$values['count'], 'value'=>''];
        }
        $defFile = BIZUNO_FS_LIBRARY."controllers/api/funnels/walmart/source/$tpl/Definitions.csv";
        if (file_exists($defFile) && ($handle = fopen($defFile, "r")) !== false) {
            while (($data = fgetcsv($handle)) !== false) {
                if (isset($data[0]) && isset($output['fields'][$data[0]])) { $output['fields'][$data[0]]['tip'] = isset($data[1]) ? $data[1] : ''; }
            }
            fclose($handle);
        }
        return $output;
    }

    private function getTitleDetails($strTitle)
    {
        $required = strpos($strTitle, '*') !== false ? 'Required' : 'Optional';
        $row      = str_replace(['(Optional)', '*'], '', $strTitle);
        $multiple = strpos($row, '(#');
        $count    = $multiple !== false ? (int)substr($row, $multiple + 2, strpos($row, ')', $multiple) - ($multiple + 2)) : 1;
        $title    = trim(preg_replace('/\(#\d+\)/', '', $row));
        return ['title'=>$title, 'label'=>$title, 'required'=>$required, 'count'=>$count];
    }

    private function saveInvTemplate($tpl, $output)
    {
        global $io;
        $io->fileWrite(json_encode($output), "data/walmart/$tpl.map", true);
        msgAdd($this->lang['msg_template_created'], 'success');
    }

    public function templateStructure(&$layout=[])
    {
        if (!$security = validateAccess($this->code, 3)) { return; }
        $tpl = clean('data', 'text', 'get');
        if (!$tpl) { return msgAdd("No template file selected!"); }
        $structure = $this->loadInvTemplate($tpl, true);
        $temp = [];
        if (file_exists(BIZUNO_DATA."data/walmart/$tpl.map")) {
            $temp = json_decode(file_get_contents(BIZUNO_DATA."data/walmart/$tpl.map"), true);
            unset($temp['header']);
        }
        $fields = array_replace_recursive($structure, $temp);
        $this->saveInvTemplate($tpl, $fields);
        $data = [
            'content'=> ['action'=>'divHTML','divID'=>'divWalmartMap'],
            'divs'   => ['divTpl'=>['order'=>10,'type'=>'html','html'=>$this->viewTemplate]],
            'forms'  => ['frmTemplate'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/admin/templateSave&modID=$this->code"]]],
            'icnSave'=> ['icon'=>'save','events'=>['onClick'=>"jqBiz('#frmTemplate').submit();"]],
            'fldTpl' => ['attr'=>['type'=>'hidden', 'value'=>"$tpl"]],
            'lang'   => ['walmart_field'=>$this->lang['walmart_field'], 'bizuno_field'=>$this->lang['bizuno_field']],
            'fields' => []];
        foreach ($fields['fields'] as $key => $value) {
            $data['fields'][$key] = [
                'group'   => !empty($value['group']) ? $fields['groups'][$value['group']] : '',
                'title'   => (isset($value['required']) && $value['required'] ? "(".$value['required'].") " : "(Optional) ") . $value['label'],
                'help'    => isset($value['tip']) && $value['tip'] ? str_replace("'", "\'", $value['tip']) : '',
                'attr'    => ['value'=>!empty($value['value']) ? $value['value'] : ''],
                'events'  => ['onClick' => "walmartFields('$key')"]];
        }
        $invStructure = dbLoadStructure(BIZUNO_DB_PREFIX."inventory");
        $invFields = [];
        foreach ($invStructure as $field => $attr) { $invFields[] = ['field'=>$field, 'title'=>$attr['label']]; }
        $srtInvFlds = sortOrder($invFields, 'title');
        $data['jsHead']['invFields'] = formatDatagrid($srtInvFlds, 'invFields');
        $layout = array_replace_recursive($layout, $data);
    }

    private function viewTemplate()
    {
        global $viewData;
        $html  = html5('icnSave',     $viewData['icnSave']);
        $html .= html5('frmTemplate', $viewData['forms']['frmTemplate']);
        $html .= html5('template' ,   $viewData['fldTpl']);
        $html .= '<table style="border-collapse:collapse;width:800px;margin-left:auto;margin-right:auto;">';
        $html .= '  <tr class="panel-header"><th>'.$viewData['lang']['bizuno_field']."</th><th>".lang('title')."</th><th>".$viewData['lang']['walmart_field']."</th></tr>";
        $lastGroup = '';
        if (isset($viewData['fields'])) { foreach ($viewData['fields'] as $idx => $settings) {
            if (!empty($settings['help'])) {
                $icnHelp = ['icon'=>'tip', 'size'=>'small', 'events'=>['onClick'=>"jqBiz('#win_$idx').window({title:'".$settings['title']."',content:'".$settings['help']."',width:450,height:200});"]];
            } else { $icnHelp = ['icon'=>'blank','size'=>'small']; }
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
        if (isset($viewData['fields'])) { foreach ($viewData['fields'] as $idx => $settings) { $html .= '<div id="win_'.$idx.'"></div>'; } }
        $html .= "</table></form>";
        htmlQueue("function walmartFields(id) {
            var fldValue = jqBiz('#'+id).val();
            jqBiz('#'+id).combogrid({data:invFields, value:fldValue, panelWidth:525, idField:'field', textField:'title',
                columns:[[{field:'field',title:'".lang('field')."',width:250}, {field:'title',title:'".lang('title')."',width:250},]]
            });
        }", 'jsHead');
        htmlQueue("ajaxForm('frmTemplate');", 'jsReady');
        return $html;
    }

    public function templateSave()
    {
        global $io;
        if (!$security = validateAccess($this->code, 3)) { return; }
        $tpl = clean('template', 'text', 'post');
        if (!$tpl) { return msgAdd("No template found to save!"); }
        if (!file_exists(BIZUNO_DATA."data/walmart/$tpl.map")) { return msgAdd("Sorry, I cannot find the template file in your file space"); }
        $fields = json_decode(file_get_contents(BIZUNO_DATA."data/walmart/$tpl.map"), true);
        foreach (array_keys($fields['fields']) as $key) {
            $setting = clean($key, 'text', 'post');
            if ($setting) { $fields['fields'][$key]['value'] = $setting; }
        }
        $io->fileWrite(json_encode($fields), "data/walmart/$tpl.map", true, false, true);
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /***************************************************************************************************
     * Install / remove lifecycle
     ***************************************************************************************************/

    public function install()
    {
        $id = validateTab('inventory', lang('estore'), 90);
        if (!dbFieldExists(BIZUNO_DB_PREFIX.'inventory', $this->metaPrefix)) {
            dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory ADD `{$this->metaPrefix}` ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'type:checkbox;label:Walmart;tag:Walmart;tab:$id;order:26;group:{$this->metaPrefix}'");
        }
        parent::installStoreFields();
        return true;
    }

    public function remove()
    {
        if (dbFieldExists(BIZUNO_DB_PREFIX.'inventory', $this->metaPrefix)) { dbGetResult("ALTER TABLE ".BIZUNO_DB_PREFIX."inventory DROP `{$this->metaPrefix}`"); }
        return true;
    }

    /***************************************************************************************************
     * Helpers
     ***************************************************************************************************/

    private function journalMainSaveDefaults($jID=10)
    {
        $data = ['path'=>'walmart'.$jID, 'values'=>[
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
            ['index'=>'page',  'clean'=>'integer','default'=>'1'],
            ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'DESC'],
            ['index'=>'period','clean'=>'text',   'default'=>getModuleCache('phreebooks', 'fy', 'period')],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * Persists a single setting back into the funnel's methods_funnels meta (e.g. last_order_sync).
     */
    private function saveSetting($key, $value)
    {
        $meta = dbMetaGet(0, "methods_{$this->methodDir}");
        if (empty($meta[$this->code])) { return; }
        $rID  = metaIdxClean($meta);
        $meta[$this->code]['settings'][$key] = $value;
        dbMetaSet($rID, "methods_{$this->methodDir}", $meta);
        $this->settings[$key] = $value;
    }

    private function v($arr, $key)
    {
        return (is_array($arr) && isset($arr[$key])) ? $arr[$key] : '';
    }

    private function num($val)
    {
        $val = preg_replace('/[^0-9.\-]/', '', (string)$val);
        return ($val === '' || $val === '-' || $val === '.') ? 0 : (float)$val;
    }

    private function msToDate($ms)
    {
        if (!is_numeric($ms)) { $ts = strtotime($ms); return $ts ? date('Y-m-d', $ts) : biz_date('Y-m-d'); }
        return date('Y-m-d', (int)($ms/1000));
    }

    private function dateToMs($date)
    {
        $ts = is_numeric($date) ? (int)$date : strtotime($date);
        if (!$ts) { $ts = time(); }
        return $ts * 1000;
    }

    /**
     * Maps a Walmart shipping methodCode to the configured Bizuno carrier method (orders import).
     */
    private function shipMethodApi($methodCode)
    {
        $expedited = in_array($methodCode, ['Express','ExpeditedDelivery','OneDay','NextDay','TwoDay','WhiteGlove']);
        if ($expedited && !empty($this->settings['ship_exp'])) { return $this->settings['ship_exp']; }
        return !empty($this->settings['ship_std']) ? $this->settings['ship_std'] : '';
    }

    /**
     * Maps a Bizuno carrier method label to a Walmart shipping methodCode (ship confirmation).
     */
    private function wmShipMethod($method)
    {
        $m = strtolower($method);
        if (strpos($m, 'next') !== false || strpos($m, 'overnight') !== false) { return 'OneDay'; }
        if (strpos($m, '2nd') !== false || strpos($m, 'two') !== false || strpos($m, 'second') !== false) { return 'TwoDay'; }
        if (strpos($m, 'express') !== false || strpos($m, 'expedited') !== false) { return 'Express'; }
        return 'Standard';
    }

    private function findStock(&$main, $inv, $qty)
    {
        if (sizeof(getModuleCache('bizuno', 'stores')) < 2) { return $inv['qty_stock'] < $qty ? false : true; }
        $stock = getStoreStock($inv['sku'], $inv['item_cost']);
        $totalStk= 0;
        $partial = false;
        foreach ($stock as $sKey => $item) {
            if ($item['stock'] <= 0) { continue; }
            $totalStk += $item['stock'];
            if ($item['stock'] <  $qty)              { $partial = true; }
            if ($item['stock'] >= $qty && !$partial) { $main['store_id'] = substr($sKey, 1); return true; }
        }
        if ($totalStk >= $qty) { msgAdd("Order {$main['purch_order_id']} has enough stock to fill the order but it is split across branches.", 'info'); }
        return false;
    }

    private function cleanPhone($tele)
    {
        $step1 = str_replace('+1 ', '', (string)$tele);
        $step2 = str_replace(' ext. ', 'x', $step1);
        if (strlen($step2) > 20) { msgAdd("Telephone number $tele cannot be reduced to fit. Please edit it manually", 'caution'); }
        return $step2;
    }

    private function extractService($method_code, $props=[], $default='GND')
    {
        $carrier= 'Other';
        if (!empty($props['id'])) { $carrier = !empty($props['acronym']) ? $props['acronym'] : $props['id']; }
        $method = !in_array($default, ['GND', 'GDR']) ? 'Expedited' : 'Standard';
        if (!empty($props['settings']['services'])) { foreach ($props['settings']['services'] as $service) {
            if ($method_code == $service['id']) {
                $parts = explode(' ', $service['text'], 2);
                if ('Endicia'==$parts[0]) {
                    $tmp = explode(' ', $parts[1], 2);
                    $carrier= 'USPS';
                    $method = trim($tmp[1]);
                } else {
                    $carrier= $parts[0];
                    $method = !empty($parts[1]) ? trim($parts[1]) : $service['text'];
                }
            }
        } }
        return ['code'=>$carrier, 'title'=>$carrier, 'method'=>$method];
    }

    private function localeProcess($value, $action)
    {
        switch ($action) {
            case 'state':
                $value = clean($value, 'alpha_num');
                if (strlen($value) > 2) {
                    $state = strtolower($value);
                    $temp = localeLoadDB();
                    foreach ($temp['Locale'] as $iso3 => $value) { if ($iso3=='USA') {
                        foreach ($value['Regions'] as $code => $region) { if (strtolower($region['Title']) == $state) { return ($code); } }
                    } }
                }
                return is_string($value) ? strtoupper($value) : $value;
            default:
        }
        return $value;
    }
}
