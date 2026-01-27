<?php
/*
 * Bizuno Extension - Walmart.com Interface
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
 * @version    7.x Last Update: 2026-01-10
 * @filesource /controllers/api/funnels/ifWalmart/ifWalmart.php
 */

namespace bizuno;

class ifWalmart
{
    public $devStatus= true; // this method is still in development status

    public $moduleID = 'api';
    public $methodDir= 'funnels';
    public $code     = 'ifWalmart';
    public $defaults;
    public $settings;
    public $lang     = ['title' => 'Walmart Interface',
        'description' => 'The Walmart interface provides capability to download orders, upload product feeds and help reconcile payments.',
        'walmart_template_desc' => 'Walmart templates are how your Bizuno inventory database fields map to Walmart fields.
            Creating these templates is required for a successful feed to Walmart. To create a template, select the category
            from the pull down and press the `Create Template` button. This will load the fields available to send to Walmart and create a list below.
            You will need to assign a Bizuno field to all the required Walmart fields. The preferred and optional enhance your product descriptions.
            Once your fields have been assigend, press the Save icon to save you changes.
            You should now be able to create upload feeds to Walmart through the Customer -> Walmart Interface menu.',
        'walmart_field' => 'Walmart Feed Index',
        'bizuno_field' => 'Bizuno Inventory Field',
        'walmart_post_success' => 'Successfully posted %3 Walmart orders!',
        'build_inventory' => 'Generate Inventory Feed',
        'import_orders' => 'Import Orders',
        'confirm_shipments' => 'Confirm Shipments',
        'import_payment' => 'Process Payment File',
        'walmart_sales_orders' => 'Open Walmart Sales Orders',
        'build_inventory_desc' => 'Select a Map file and press Go to to build your Walmart product upload feed file and download it to your computer. From there it can be uploaded to Walmart through Seller Central. If no feed files are listed, you need to build one through Module Administration -> Walmart Interface (Settings) -> Walmart Templates.',
        'import_orders_desc' => 'Select order file to import from Walmart and press Go:',
        'confirm_shipments_desc' => 'Select the date to build the Walmart ship confirmation on and press Go:',
        'import_payment_desc' => 'Please select the Walmart payment file to process:',
        'msg_confirm_success' => 'Walmart order confirmation generated.',
        'msg_template_created' => 'Your template file has been written, you may now assign Bizuno fields to the Walmart fields.',
        'msg_order_long_data' => 'Either the primary name or an address has been truncated to fit the journal database field size. Check the ship to information for order # %s and Customer: %s to make sure the lost information is not critical. You will have to reformat the address manually.',
        'err_no_inv_map' => 'Cannot find the Walmart map file for template: %s',
        'err_no_inv_sku' => 'Missing UPC code and Walmart ASIN ID for SKU: %s',
        'err_no_inv_tpl' => 'Cannot find Walmart template file for template: %s',
        'err_no_contact' => "Could not find Walmart contact ID, please make sure you have selected a customer contact in Module Administration -> Walmart Module settings.",
        'err_dup_order' => 'Walmart order # %s is already posted to Bizuno, it will be skipped!',
        'err_confirm_no_contact' => 'Contact ID/Ship date could not be found, no file was generated!',
        'err_no_confirm_found' => 'No valid Walmart orders have been shipped on the date selected!',
        'err_sku_no_weight' => 'SKU %s has no weight. Please edit the inventory record and add a non-zero weight!',
        'err_missing_image' => 'Image at path: %s for sku: %s must be of type jpg or gif for Walmart!',
        'err_no_inv_rows' => 'No inventory items found to be uploaded!',
        'err_inv_no_price' => 'No price was determined for SKU: %s',
        'err_feed_needs_fix' => 'There are errors in your feed file, please fix them before submitting your feed to Walmart.',
        // settings
        'consumer_id_lbl' => 'Consumer ID',
        'private_key_lbl' => 'Private Key',
        'channel_type_lbl' => 'Channel Type',
        'ship_calc_lbl'    => 'Shipping Method',
        'contact_id_lbl'   => 'Contact ID',
        'catalog_field_lbl'=> 'Inv Link Field',
        'gl_acct_sales_lbl'=> 'Sales GL Account',
        'gl_acct_disc_lbl' => 'Discount GL Account',
        'gl_acct_ship_lbl' => 'Freight GL Account',
        'auto_journal_lbl' => 'Post Type',
        'consumer_id_tip' => 'Enter the Consumer ID as supplied by Walmart in response to generating your API access credentials',
        'private_key_tip' => 'Enter the Private Key as supplied by Walmart in response to generating your API access credentials',
        'channel_type_tip' => 'Hidden field, this should be static for Bizuno, if not change type to text and update this description',
        'ship_calc_tip'    => 'Set the shipping method to use to calculate the freight charge and add to product price to generate the Free Freight selling price.',
        'contact_id_tip'   => 'Determines the Customer contact ID to assign all Walmart sales.',
        'catalog_field_tip'=> 'Determines which database field name to use to select items to be uploaded to Walmart, typically a checkbox type',
        'gl_acct_sales_tip'=> 'GL Account to use for recording sales',
        'gl_acct_disc_tip' => 'GL Account to use for recording sales discounts',
        'gl_acct_ship_tip' => 'GL Account to use for recording freight charges',
        'auto_journal_tip' => 'Determines how to post each sale, choices are Sales Orders, Sales, or Auto (Sales if in stock, Sales Order if not)'];

    function __construct()
    {
        localizeLang($this->lang, $this->methodDir, $this->code);
        $this->lang    = array_replace(getLang($this->moduleID), $this->lang);
        $this->defaults= ['consumer_id'=>0,'private_key'=>'','channel_type'=>'','contact_id'=>0,'catalog_field'=>'walmart','ship_calc'=>'','auto_journal'=>0,
            'gl_acct_sales'=>getModuleCache('phreebooks','settings','customers','gl_sales'),
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
        return [
            'consumer_id'  => ['attr'=>['value'=>$this->settings['contact_id']]],
            'private_key'  => ['attr'=>['type'=>'textarea', 'size'=>80, 'value'=>$this->settings['private_key']]],
            'channel_type' => ['attr'=>['type'=>'hidden','value'=>$this->settings['channel_type']]],
            'contact_id'   => ['defaults'=>['type'=>'c'],'attr'=>['type'=>'contact','value'=>$this->settings['contact_id']]],
            'catalog_field'=> ['events'=>['onClick'=>"walmartFields('general_catalog_field')"],'attr'=>['value'=>$this->settings['catalog_field']]],
            'ship_calc'    => ['values'=>$choices, 'attr'=>['type'=>'select','value'=>$this->settings['ship_calc']]],
            'gl_acct_sales'=> ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_sales','value'=>$this->settings['gl_acct_sales']]],
            'gl_acct_disc' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_disc', 'value'=>$this->settings['gl_acct_disc']]],
            'gl_acct_ship' => ['attr'=>['type'=>'ledger','id'=>'general_gl_acct_ship', 'value'=>$this->settings['gl_acct_ship']]],
            'auto_journal' => ['values'=>$autoJID,'attr'=>['type'=>'select','value'=>$this->settings['auto_journal']]]];
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function home(&$layout=[])
    {
        if (!$security = validateAccess('walmart', 1)) { return; }
        $this->journalMainSaveDefaults();
        $title = $this->lang['title'];
        $maps = [];
        $files = glob(BIZUNO_DATA."data/ifWalmart/*.map");
        if (is_array($files)) { foreach ($files as $value) {
            $tmp1 = str_replace(".map", "", $value);
            $tmp2 = str_replace(BIZUNO_DATA."data/ifWalmart/", "", $tmp1);
            $maps[] = ['id'=>$tmp2, 'text'=>$tmp2];
        } }
        $data = [
            'title'=> $title,
            'divs'=>[
                'head'    => ['order'=> 1,'type'=>'fields','keys'=>['imgLogo']],
                'lineBR'  => ['order'=> 2,'type'=>'html',  'html'=>"<br />"],
                'manager' => ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'setInv'  => ['order'=>20,'type'=>'panel','key'=>'setInv',  'classes'=>['block33']],
                    'setOrder'=> ['order'=>30,'type'=>'panel','key'=>'setOrder','classes'=>['block33']],
                    'setShip' => ['order'=>40,'type'=>'panel','key'=>'setShip', 'classes'=>['block33']]]]],
            'panels' => [
                'setInv'  => ['label'=>$this->lang['build_inventory'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmInventory'],
                    'desc'   => ['order'=>20,'type'=>'html',  'html'=>"<p>".$this->lang['build_inventory_desc']."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['selMap','btnInventory']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
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
            'forms'   => [
                'frmInventory'=> ['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifWalmart/admin/inventoryGo"]],
                'frmOrders'   => ['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifWalmart/admin/ordersGo"]],
                'frmConfirm'  => ['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifWalmart/admin/confirmGo"]]],
            'fields' => [
                'imgWalmart'  => ['attr'   => ['type'=>'img', 'src'=>BIZUNO_FS_LIBRARY.'0/controllers/api/funnels/ifWalmart/logo.png']],
                'selMap'      => ['values' => $maps, 'attr'=> ['type'=>'select']],
                'btnInventory'=> ['events' => ['onClick'=>"jqBiz('#frmInventory').submit();"], 'attr'=> ['type'=>'button', 'value'=>lang('go')]],
                'fileOrders'  => ['attr'   => ['type'=>'file']],
                'btnOrders'   => ['events' => ['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmOrders').submit();"], 'attr'=> ['type'=>'button', 'value'=>lang('go')]],
                'dateShip'    => ['classes'=> ['easyui-datebox'], 'attr'=> ['type'=>'date', 'value'=>biz_date('Y-m-d')]],
                'btnConfirm'  => ['events' => ['onClick'=>"jqBiz('#frmConfirm').submit();"], 'attr'=> ['type'=>'button', 'value'=>lang('go')]]],
            'attachPrefix'=> ""];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    private function adminHome(&$layout=[])
    {
        $listWm = glob(BIZUNO_FS_LIBRARY.'controllers/api/funnels/walmart/source/*');
        $templWm = [['id'=>'', 'text'=>lang('select')]];
        foreach ($listWm as $option) { if (is_dir($option)) {
            $tpl = substr($option, strrpos($option, '/')+1);
            $templWm[] = ['id'=>$tpl, 'text'=>$tpl];
        } }
        $layout['tabs']['tabAdmin']['divs'][$channel] = ['order'=>85,'label'=>$this->lang['walmart_maps'],'type'=>'divs','divs'=>[
            'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'getMap' => ['order'=>20,'type'=>'panel','classes'=>['block66'],'key'=>$this->code]]]]];
        $layout['panels'][$channel] = ['type'=>'fields','keys'=>['tplDescWm','selTempWm','divMapWm']];
        $layout['fields']['tplDescWm'] = ['order'=>10,'html'=>$this->lang['walmart_template_desc'],  'attr'=>['type'=>'raw']];
        $layout['fields']['selTempWm'] = ['order'=>20,'values'=>$templWm,'events'=>['onChange'=>"jsonAction('api/admin/templateStructure&modID=ifWalmart', 0, bizSelGet('selTempWm'));"],'attr'=>['type'=>'select']];
        $layout['fields']['divMapWm']  = ['order'=>90,'html'=>'<div id="divWalmartMap">&nbsp;</div>','attr'=>['type'=>'raw']];
        $layout['jsHead'][$channel] = "jqBiz.cachedScript('".BIZUNO_URL_FS."0/controllers/api/$this->methodDir/$this->code/$this->code.js?ver=".MODULE_BIZUNO_VERSION."');";
        $layout['jsReady'][$channel]= "walmartContact();";
    }

  /**
   * This code extracts the template info with headings and builds a php map template,
   * it should only be run once per template then edited to map to Bizuno
   */
    private function loadInvTemplate($tpl, $force=true)
    {
        $tmp   = array_map('str_getcsv', file(BIZUNO_FS_LIBRARY."controllers/ifWalmart/source/$tpl/$tpl.csv")); // pull title, extract count and required/optional
        $titles= array_shift($tmp); // just need the first row
        msgDebug("\nWorking with titles = ".print_r($titles, true));
        $map   = [];
        foreach ($titles as $title) {
            $values = $this->getTitleDetails($title);
            $map[$values['title']] = $values;
        }
        // now the definitions file
        if (($handle = fopen(BIZUNO_FS_LIBRARY."controllers/ifWalmart/source/$tpl/Definitions.csv", "r")) !== false) {
            while (($data = fgetcsv($handle, 0, "\t")) !== false) {
                if (isset($data[1]) && isset($output['fields'][$data[1]])) {
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
     * @param type $strTitle
     * @return type
     */
    private function getTitleDetails($strTitle)
    {
        // if contains * then required, else optional
        $required = strpos($row, '*') ? 'Required' : 'Optional';
        $row      = str_replace('(Optional)', '', $row);
        $multiple = strpos($row, '(#');
        $count    = $multiple ? substr($row, $multiple + 2, strpos($row, ')') - $multiple + 2) : 1;
        $title    = trim(str_replace("(#$count)", '', $row));
        msgDebug("\nREturning with values title = $title and required = $required and count = $count");
        return ['title' =>$title, 'required'=>$required, 'count'=>$count];
    }

    /**
     *
     * @global type $io
     * @param type $tpl
     * @param type $output
     */
    private function saveInvTemplate($tpl, $output) {
        global $io;
        $io->fileWrite(json_encode($output), "data/ifWalmart/$tpl.map", true);
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
        if (!$security = validateAccess('walmart', 3)) { return; }
        $tpl = clean('data', 'text', 'get');
        if (!$tpl) { return msgAdd("No template file selected!"); }
        $structure = $this->loadInvTemplate($tpl, true);
        $temp = [];
        if (file_exists(BIZUNO_DATA."data/ifWalmart/$tpl.map")) { // get current settings
            $temp = json_decode(file_get_contents(BIZUNO_DATA."data/ifWalmart/$tpl.map"), true);
            unset($temp['header']); // remove the header in case of new template
        }
        $fields = array_replace_recursive($structure, $temp);
        $this->saveInvTemplate($tpl, $fields);
        $data = [
            'content'=> ['action'=>'divHTML','divID'=>'divWalmartMap'],
            'divs'   => ['divTpl'=>['oreder'=>10,'type'=>'html','html'=>$this->viewTemplate]],
            'forms'  => ['frmTemplate'=>['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifWalmart/admin/templateSave"]]],
            'icnSave'=> ['icon'=>'save','events'=>  ['onClick'=>"jqBiz('#frmTemplate').submit();"]],
            'fldTpl' => ['attr'=>  ['type'=>'hidden', 'value'=>"$tpl"]],
            'lang'   => [
                'walmart_field' => $this->lang['walmart_field'],
                'bizuno_field' => $this->lang['bizuno_field'],
                ],
            'fields' => [], // filled below
            ];
        foreach ($fields['fields'] as $key => $value) {
            $data['fields'][$key] = [
                'group'   => $value['group']>0 ? $fields['groups'][$value['group']] : '',
                'title'   => (isset($value['required']) && $value['required'] ? "(".$value['required'].") " : "(Optional) ") . $value['label'],
                'help'    => isset($value['tip']) && $value['tip'] ? str_replace("'", "\'", $value['tip']) : '',
                'attr'    => ['value'=>$value['value']],
                'events'  => ['onClick' => "walmartFields('$key')"],
                ];
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
            $icnHelp = ['icon'=>'tip', 'size'=>'small',
                'events'=>  ['onClick'=>"jqBiz('#win_$idx').window({title:'".$settings['title']."',content:'".$settings['help']."',width:450,height:200});"]];
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
        htmlQueue("function walmartFields(id) {
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
        if (!$security = validateAccess('walmart', 3)) { return; }
        $tpl = clean('template', 'text', 'post');
        if (!$tpl) { return msgAdd("No template found to save!"); }
        if (!file_exists(BIZUNO_DATA."data/ifWalmart/$tpl.map")) { return msgAdd("Sorry, I cannot file the template file in your file space"); }
        $fields = json_decode(file_get_contents(BIZUNO_DATA."data/ifWalmart/$tpl.map"), true);
        // clean the post variables
        foreach ($fields['fields'] as $key => $value) {
            $setting = clean($key, 'text', 'post');
            if ($setting) { $fields['fields'][$key]['value'] = $setting; }
        }
        $io->fileWrite(json_encode($fields), "data/ifWalmart/$tpl.map", true, false, true);
        msgDebug("output = ".print_r($fields, true));
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function edit(&$layout) // extends /phreebooks/main/edit
    {
        if (!isset($layout['fields'])) { return; }
        $jID = $layout['fields']['journal_id']['attr']['value'];
        if (isset($layout['fields']['contact_id_b']['attr']['value']) && $layout['fields']['contact_id_b']['attr']['value']) {
            $cID = $layout['fields']['contact_id_b']['attr']['value'];
            if ($jID==18 && $cID==$this->settings['general']['contact_id']) {
                $layout['toolbars']['tbPhreeBooks']['icons']['ifWalmart'] = [
                    'label' =>$this->lang['import_payment'],
                    'order' =>80,
                    'events'=>  ['onClick'=>"reconcileWalmart();"]];
                $layout['divs']['ifWalmart'] = ['order'=>0, 'type'=>'html', 'html'=>'<script type="text/javascript" src="'.BIZUNO_FS_LIBRARY.'controllers/ifWalmart/ifWalmart.js"></script>'];
            }
        }
    }

    /**
     *
     * @param type $jID
     */
    private function journalMainSaveDefaults($jID=10)
    {
        $data = ['path'=>'ifWalmart'.$jID,
            'values' => [
                ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')],
                ['index'=>'page',  'clean'=>'integer','default'=>'1'],
                ['index'=>'sort',  'clean'=>'text',   'default'=>BIZUNO_DB_PREFIX.'journal_main.invoice_num'],
                ['index'=>'order', 'clean'=>'text',   'default'=>'DESC'],
                ['index'=>'period','clean'=>'text',   'default'=>getModuleCache('phreebooks', 'fy', 'period')],
                ['index'=>'search','clean'=>'text',   'default'=>''],
                ],
            ];
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
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/inventory/prices.php", 'inventoryPrices');
        $dbField = $this->settings['general']['catalog_field'];
        $map = clean('selMap', 'text', 'post');
        if (!file_exists (BIZUNO_DATA."data/ifWalmart/$map.map")) { return msgAdd(sprintf($this->lang['err_no_inv_map'], $map)); }
        $map    = json_decode(file_get_contents(BIZUNO_DATA."data/ifWalmart/$map.map"), true);
        $rows   = [];
        $result = dbGetMulti(BIZUNO_DB_PREFIX."inventory", "inactive='0' AND `$dbField`='1'", 'sku');
        foreach ($result as $key => $item) {
            $aItem = [];
            foreach ($map['fields'] as $idx => $attr) {
                switch ($idx) { // error check required fields
                    case 'gtin_exemption_reason':
                        $aItem[$idx] =  isset($item[$attr['value']]) && $item[$attr['value']] ? 'ReplacementPart' : '';
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
                        if (in_array($item['inventory_type'], ['ma', 'sa'])) { // for assemblies, see how many we can build
                            $result = getMetaInventory($item['sku'], 'bill_of_materials');
                            foreach ($result as $row) { $min_qty = empty($row['qty']) ? 0 : min($min_qty, floor($item['qty_stock'] / $row['qty'])); }
                            $item['qty_stock'] = $min_qty;
                        }
                        $available = $item['qty_stock'] - $item['qty_so'] - $item['qty_alloc'];
                        $aItem[$idx] = max(0, $available); // no negative numbers
                        break;
                    case 'standard_price': // was item-price???
                        $price['args'] =['cID'=>$this->settings['general']['contact_id'], 'iID'=>$item['id']];
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
        $temp   = []; // to extract teh second header row
        foreach ($map['fields'] as $idx => $attr) { $temp[] = $attr['label'];  }
        $output[] = $map['header']; // line 1 from Walmart template (not to be modified), version and category
        $output[] = implode("\t", $temp); // line 2 from map labels
        $output[] = implode("\t", array_keys($map['fields'])); // from map indexes
        foreach ($rows as $row) { $output[] = implode("\t", $row); }
        msgLog("Walmart inventory upload file generated.");
        $io->download('data', implode("\n", $output), "WalmartInventoryFeed-".biz_date('Y-m-d').".txt");
    }

    /**
     *
     * @global \bizuno\type $io
     * @param type $layout
     * @return type
     */
    public function ordersGo(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('walmart', 2)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/journal.php", 'journal');
        bizAutoLoad(BIZUNO_FS_LIBRARY."controllers/phreebooks/functions.php", 'phreebooksProcess', 'function');
        if (!$io->validateUpload('fileOrders', 'text', 'txt')) { return; }
        // load the walmart contact record info
        $cID = isset($this->settings['general']['contact_id']) ? $this->settings['general']['contact_id'] : false;
        if (!$cID) { msgAdd($this->lang['err_no_contact'], 'error'); return; }
        $address = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$cID");
        $commonMain = [
            'post_date'     => biz_date('Y-m-d'), // substr($data['purchase-date'], 0, 10); // forces orders posted today
            'terminal_date' => biz_date('Y-m-d'),
            'waiting'       => '1', // set the waiting to ship flag
//            'store_id'      => '0', // $this->settings['general']['branch_id'],
            'rep_id'        => getUserCache('profile', 'userID', false, 0),
            'gl_acct_id'    => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'), // make this a setting?
            'contact_id_b'  => $address['ref_id'],
            'address_id_b'  => 0,
            'primary_name_b'=> $address['primary_name'],
            'contact_b'     => $address['contact'],
            'address1_b'    => $address['address1'],
            'address2_b'    => $address['address2'],
            'city_b'        => $address['city'],
            'state_b'       => $address['state'],
            'postal_code_b' => $address['postal_code'],
            'country_b'     => $address['country'],
            'telephone1_b'  => $address['telephone1'],
            'email_b'       => $address['email'],
            'drop_ship'     => '1',
            ];
        // iterate through the map to set journal post variables, orders may be on more than 1 line
        // ***************************** START TRANSACTION *******************************
        dbTransactionStart();
        $itemCnt = 1;
        $items   = [];
        $totals  = [];
        $inStock = true;
        $orderCnt= 0;
        $runaway = 0;
        $rows    = file($_FILES['fileOrders']['tmp_name']);
        $row     = array_shift($rows); // heading
        $this->headings = explode("\t", $row);
        $row     = array_shift($rows); // first order
        if (!$row) { return msgAdd("There were no orders to process!", 'caution'); }
        $data= $this->processRow($row);
        while (true) {
            if (!$row) { break; }
            $main = $commonMain;
            $main['purch_order_id'] = $data['order-id'];
            $main['description']    = "Walmart Order # ".$data['order-id'];
            $main['method_code']    = $data['ship-service-level']=='Expedited' ? $this->settings['general']['ship_exp'] : $this->settings['general']['ship_std'];
            // Walmart misspelled recepient, test for both cases and correct
            if (isset($data['recepient-name'])) { $data['recipient-name'] = $data['recepient-name']; }
            if (strlen($data['recipient-name']) > 32 || strlen($data['ship-address-1']) > 32 || strlen($data['ship-address-2']) > 32) {
                msgAdd(sprintf($this->lang['msg_order_long_data'], $data['order-id'], $data['recipient-name']), 'caution');
            }
            $main['primary_name_s'] = $data['recipient-name'];
            $main['address1_s']     = $data['ship-address-1'];
            $main['address2_s']     = $data['ship-address-2'];
            $main['contact_s']      = $data['ship-address-3'];
            $main['city_s']         = $data['ship-city'];
            $main['state_s']        = $this->localeProcess($data['ship-state'], 'state');
            $main['postal_code_s']  = $data['ship-postal-code'];
            $main['country_s']      = 'USA'; // $data['ship-country'];
            $main['telephone1_s']   = $data['buyer-phone-number'];
            $main['email_s']        = $data['buyer-email'];
            // build the item, check stock if auto_journal
            $inv = dbGetRow(BIZUNO_DB_PREFIX."inventory", "sku='{$data['sku']}'");
            if ($inv['qty_stock'] < $data['quantity-purchased']) { $inStock = false; }
            $items[] = [
                'item_cnt'      => $itemCnt,
                'gl_type'       => 'itm',
                'sku'           => $data['sku'],
                'qty'           => $data['quantity-purchased'],
                'description'   => $data['product-name'],
                'credit_amount' => $data['item-price'],
                'gl_account'    => $this->settings['general']['gl_acct_sales'] ? $this->settings['general']['gl_acct_sales'] : $inv['gl_sales'],
                'tax_rate_id'   => 0,
                'full_price'    => $inv['full_price'],
                'post_date'     => substr($data['purchase-date'], 0, 10),
                ];
            // preset some totals to keep running balance
            if (!isset($totals['discount']))    { $totals['discount']    = 0; }
            if (!isset($totals['sales_tax']))   { $totals['sales_tax']   = 0; }
            if (!isset($totals['total_amount'])){ $totals['total_amount']= 0; }
            if (!isset($totals['freight']))     { $totals['freight']     = 0; }
            // fill in order info
            $totals['discount']    += $data['item-promotion-discount'] + $data['ship-promotion-discount'];
            $totals['sales_tax']   += $data['item-tax'];
            $totals['total_amount']+= $data['item-price'] + $data['item-tax'] + $data['shipping-price'] + $data['shipping-tax']; // missing from file: $data['gift-wrap-price'] and $data['gift-wrap-tax']
            $totals['freight']     += $data['shipping-price'];
            // check for continuation order
            $row = array_shift($rows);
            if ($runaway++ > 1000) { msgAdd("runaway reached, exiting!", 'error'); break; }
            if ($row) { // check for continuation order
                $nextData = $this->processRow($row);
                msgDebug("\nContinuing order check, Next order = {$nextData['order-id']} and this order = {$main['purch_order_id']}");
                if ($nextData['order-id'] == $main['purch_order_id']) {
                    $data = $nextData;
                    $itemCnt++;
                    continue; // more items for the same order
                }
            }
            // finish main and item to post
            $main['total_amount'] = $totals['total_amount'];
            $items[] = [
                'qty'          => 1,
                'gl_type'      => 'frt',
                'description'  => "Shipping Walmart # ".$data['order-id'],
                'credit_amount'=> $totals['freight'],
                'gl_account'   => $this->settings['general']['gl_acct_ship'] ? $this->settings['general']['gl_acct_ship'] : getModuleCache('shipping', 'settings', 'general', 'gl_shipping_c'),
                'tax_rate_id'  => 0,
                'post_date'    => substr($data['purchase-date'], 0, 10),
                ];
            $items[] = [
                'qty'          => 1,
                'gl_type'      => 'ttl',
                'description'  => "Total Walmart # ".$data['order-id'],
                'debit_amount' => $totals['total_amount'],
                'gl_account'   => getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'), // make this a setting?
                'post_date'    => substr($data['purchase-date'], 0, 10),
                ];
            // set some specific journal information, first post journal
            switch ($this->settings['general']['auto_journal']) {
                case '10': $jID = 10; break;
                case '12': $jID = 12; break;
                default:   $jID = $inStock ? 12 : 10; break; // auto detect
            }
            $dup = dbGetValue(BIZUNO_DB_PREFIX."journal_main", "id", "purch_order_id='{$main['purch_order_id']}'");
            if ($dup) {
                msgDebug("duplicate order id = $dup and main = ".print_r($main, true));
                msgAdd(sprintf($this->lang['err_dup_order'], $data['order-id']), 'caution');
            } else {
                $ledger = new journal(0, $jID, $main['post_date']);
                $ledger->main  = array_merge($ledger->main, $main);
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
        msgAdd(sprintf($this->lang['walmart_post_success'], $orderCnt), 'success');
        msgLog(sprintf($this->lang['walmart_post_success'], $orderCnt));
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    /**
     *
     * @global \bizuno\type $io
     * @return type
     */
    public function confirmGo()
    {
        global $io;
        if (!$security = validateAccess('walmart', 2)) { return; }
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
        $io->download('data', implode("\n", $output), "WalmartShipConfirm-{$shipDate}.csv");
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function paymentFileForm(&$layout=[])
    {
        if (!$security = validateAccess('walmart', 3)) { return; }
        $html  = '<p>'.lang('desc_new_price_sheets')."</p>";
        $html .= html5('frmNewPmt', ['attr'=>  ['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=ifWalmart/admin/paymentProcess"]]);
        $html .= html5('walmart_pmt',  ['attr'=>  ['type'=>'file']]);
        $html .= html5('iconGO', ['icon'=>'next', 'events'=>  ['onClick'=>"jqBiz('#frmNewPmt').submit(); bizWindowClose('winNewPmt'); jqBiz('body').addClass('loading');"]]);
        $html .= "</form>";
        $data = ['type'=>'popup','title'=>$this->lang['import_payment_desc'],'attr'=>['id'=>'winNewPmt','width'=>400,'height'=>200],
            'divs'   => ['winNewPmt'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsReady'=> ['jsForm'   =>"ajaxForm('frmNewPmt');"]];
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
        if (!$security = validateAccess('walmart', 3)) { return; }
        if (!$io->validateUpload('walmart_pmt')) { return; }
        $contents = file($_FILES['walmart_pmt']['tmp_name']);
        msgDebug("\nread ".sizeof($contents)." lines from the uploaded file.");
        $contents[0] = str_replace('-', '_', $contents[0]); // breaks javascript to use dashes
        $output = base64_encode(implode("\n", $contents)); // base64_encode file to preserve tabs and line feeds.
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval', 'actionData'=>"processWalmart(json);", 'payments'=>$output]]);
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
                    foreach ($temp['Locale'] as $iso3 => $value) { if ($iso3=='USA') {
                        foreach ($value['Regions'] as $code => $region) { if (strtolower($region['Title']) == $state) { return ($code); } }
                    } }
                }
                return is_string($value) ? strtoupper($value) : $value;
            default: // return $value
        }
        return $value;
    }

    private function viewAdminMaps()
    {
        global $viewData;
        return "<p>".$viewData['lang']['walmart_template_desc']."</p>".html5('selTemplate', $viewData['selTemplate']).'<div id="divWalmartMap">&nbsp;</div>';
    }
}
