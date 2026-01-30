<?php
/*
 * Module contacts main methods
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
 * @version    7.x Last Update: 2026-01-30
 * @filesource /controllers/contacts/main.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/contacts/functions.php', 'getContactSecID', 'function');

class contactsMain
{
    private   $moduleID  = 'contacts';
    private   $pageID    = 'main';
    protected $domSuffix = 'Contacts';
    private   $secID;
    private   $refTries  = 10; // number of attempts to pull a new refernce before punting. Helps fix bad Vendor IDs and Customer IDs
    private   $metaPfxAdd= 'address_';
    public    $cTypes    = ['b', 'c', 'e', 'i', 'j', 'u', 'v'];
    public    $addFields = ['primary_name', 'contact', 'address1', 'address2', 'city', 'state', 'postal_code', 'country',
        'email', 'email2', 'email3', 'email4', 'telephone1', 'telephone2', 'telephone3', 'telephone4',
        'website' , 'contact_first', 'contact_last', 'flex_field_1'];
    public    $defaults;
    public    $lang;
    public    $type;
    private   $f0_default;
    public    $contact;
    private   $restrict;
    private   $stores;
    private   $myStore;

    function __construct($type='c')
    {
        $this->lang      = getLang($this->moduleID);
        $this->type      = clean('type', ['format'=>'char', 'default'=>$type], 'get');
        $this->secID     = getContactSecID($this->type);
        $this->stores    = getModuleCache('bizuno', 'stores');
        $this->f0_default= in_array($this->type, ['b','e','u','v']) ? '0' : 'a'; // set search default to active only (0) or all (a)
        $this->setDefaults();
    }

    private function setDefaults()
    {
        $postTaxID= clean('tax_rate_id', ['format'=>'integer','default'=>null], 'post');
        $defTaxID = $this->type=='v' ? getModuleCache('phreebooks', 'settings', 'vendors', 'tax_rate_id_v') : getModuleCache('phreebooks', 'settings', 'customers', 'tax_rate_id_c');
        $this->contact = ['id'=>0, "ctype_{$this->type}"=>'1', 'inactive'=>'0', 'terms'=>'0', 'price_sheet'=>0, 'rep_id'=>0, 'store_id'=>0, 'gov_id_number'=>'', 'newsletter'=>'0',
            'gl_account'=>$this->type=='v'? getModuleCache('phreebooks', 'settings', 'vendors', 'gl_expense') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales'),
            'tax_rate_id'=> $postTaxID !== null ? $postTaxID : $defTaxID, 'first_date'=>biz_date('Y-m-d'), 'date_last'=>biz_date('Y-m-d')];
    }

    /**
     * Main manager constructor for all contact types
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $dom  = clean('dom', ['format'=>'db_field','default'=>'page'], 'get');
        $title= sprintf(lang('tbd_manager'), lang("ctype_{$this->type}")); 
        if ($rID) {
            $jsReady = "jqBiz(document).ready(function() { accordionEdit('accContacts', 'dgContacts', 'divContactsDetail', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&type=$this->type', $rID); });";
        } else {
            $jsReady = "bizFocus('search', 'dgContacts');";
        }
        $data = ['type'=>'page','title'=>$title,
            'divs'     => ['contacts'=>['order'=>50,'type'=>'accordion','key'=>'accContacts']],
            'accordion'=> ['accContacts'=>['divs'=>[
                'divContactsManager'=>['order'=>30,'type'=>'datagrid','label'=>$title,         'key' =>'manager'],
                'divContactsDetail' =>['order'=>70,'type'=>'html',    'label'=>lang('details'),'html'=>'&nbsp;']]]],
            'datagrid' =>['manager'=>$this->dgContacts('Contacts', $this->type, $security)],
            'jsReady'  =>['init'=>$jsReady]];
        if ($dom == 'div') { // probably a status popup
            $data['type'] = 'divHTML';
            $layout = array_replace_recursive($layout, $data);
        } else {
            $layout = array_replace_recursive($layout, viewMain(), $data);
        }
    }

    /**
     * Gets the results to populate the active contact grid
     * @param array $layout -  working structure
     * @return modified $layout
     */
    public function managerRows(&$layout=[])
    {
        $rID  = clean('rID', 'integer','get');
        if (!$security = 'mgr_a'==$this->secID ? 1 : validateAccess($this->secID, 1)) { return; } // allow access for type a, otherwise dependent on role
        $_POST['search'] = getSearch();
        $data = $this->dgContacts('Contacts', $this->type, $security, $rID);
        $this->managerFilters($data); // set filters based on type
        if ($rID) {
            $_POST['search'] = ''; // preload hit which is erased if searching is started
            $data['source']['filters']['rID'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts.id=$rID"];
        }
        $data['strict'] = true;
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$data]]);
    }

    /**
     * Variation of managerRows for drop downs, extends rows to 250, filters data returned, adds type circles
     * @param array $layout - working structure
     * @return modified $layout
     */
    public function managerRowsSel(&$layout=[])
    {
        $rID  = clean('rID', 'integer', 'get');
        if (!$security = 'mgr_a'==$this->secID ? 1 : validateAccess($this->secID, 1)) { return; } // allow access for type a, otherwise dependent on role
        $_POST['search'] = getSearch();
        $data = $this->dgContacts('Contacts', $this->type, $security, $rID);
        $data['rows'] = 250;
        $this->managerFilters($data); // set filters based on type
        if ($rID) {
            $_POST['search'] = ''; // preload hit which is erased if searching is started
            $data['source']['filters']['rID'] = ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts.id=$rID"];
        }
        $content= dbTableRead($data);
        $output = $this->managerRowsView($content['rows']);
        msgDebug("\n datagrid results number of rows = ".(isset($output['rows']) ? sizeof($output['rows']) : 0));
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>json_encode($output)]);
    }

    private function managerFilters(&$data)
    {
        $cnt = 0;
        if ($this->type<>'a') {
            $data['source']['filters']['cType'] = ['order'=>0,'hidden'=>true,'sql'=>"ctype_{$this->type}='1'"];
            return;
        }
        $types = [];
        foreach ($this->cTypes as $type) {
            if (!empty(getUserCache('role', 'security', "mgr_$type" )) || !empty(getUserCache('role', 'administrate'))) { $types[] = "ctype_{$type}='1'"; }
            $data['source']['filters']["cType"] = ['order'=>0,'hidden'=>true,'sql'=>'('.implode(' OR ', $types).')'];
            $cnt++;
        }
    }

    private function managerRowsView(&$rows)
    {
        msgDebug("\nEntering managerRowsView with number of rows = ".sizeof($rows));
        $output = [];
        $colors= ['black', 'brown', 'red', 'orange', 'yellow', 'green', 'blue', 'violet', 'grey', 'white'];
        foreach ($rows as $row) {
            $html  = '';
            foreach ($this->cTypes as $color => $type) {
                if (!empty($row['ctype_'.$type])) {
                    $html .= '<span style="font-size: 2em; color: '.$colors[$color].';"><i class="fa-duotone fa-solid fa-circle-'.$type.' fa-xs"></i></span>'; }
            }
            $output[] = [
                'id'           => $row['id'],
                'type'         => $html,
                'short_name'   => $row['short_name'],
                'primary_name' => $row['primary_name'],
                'contact'      => $row['contact'],
                'address1'     => $row['address1'],
                'address2'     => $row['address2'],
                'city'         => $row['city'],
                'state'        => $row['state'],
                'postal_code'  => $row['postal_code'],
                'country'      => $row['country'],
                'email'        => $row['email'],
//              'email2'       => $row['email2'],
//              'email3'       => $row['email3'],
//              'email4'       => $row['email4'],
                'telephone1'   => $row['telephone1'],
//              'telephone2'   => $row['telephone2'],
//              'telephone3'   => $row['telephone3'],
//              'telephone4'   => $row['telephone4'],
//              'website'      => $row['website'],
//              'contact_first'=> $row['contact_first'],
//              'flex_field_1' => $row['flex_field_1'],
//              'contact_last' => $row['contact_last'],
                ];
        }
        return ['total'=>sizeof($output), 'rows'=>$output];
    }
    /**
     * Sets the cache registry settings of current user selections
     * @param char $type - contact type code
     */
    private function managerSettings($type=false)
    {
        if (!$type) { $type = $this->type; }
        $data = ['values'=>[
            ['index'=>'rows',  'clean'=>'integer','default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows'), 'method'=>'request'],
            ['index'=>'page',  'clean'=>'integer','default'=>1],
            ['index'=>'sort',  'clean'=>'text',   'default'=>'short_name'],
            ['index'=>'order', 'clean'=>'text',   'default'=>'ASC'],
            ['index'=>'f0',    'clean'=>'char',   'default'=>$this->f0_default],
            ['index'=>'search','clean'=>'text',   'default'=>'']]];
        $this->defaults = updateSelection($data);
    }

    /**
     * Editor for all contact types, customized by type specified
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        $structure = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts');
        // merge data with structure
        $cData = !empty($rID) ? dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$rID") : $this->contact;
        msgDebug("\nRead data from DB = ".print_r($cData, true));
        dbStructureFill($structure, $cData);
        if ($rID) { // set some defaults
            $title = $structure['short_name']['attr']['value'].' - '.$cData['primary_name'];
            $structure['first_date']['attr']['readonly'] = true;
            $structure['date_last']['attr']['readonly']= true;
        } else {
            $title = lang('new');
            $type  = $this->type=='v' ? 'next_vend_id_num' : 'next_cust_id_num';
            $structure['short_name']['attr']['value'] = getNextReference($type);
            $structure['first_date']['attr']['type'] = 'hidden';
            $structure['date_last']['attr']['type']= 'hidden';
            $structure['country']['attr']['value']   = getModuleCache('bizuno','settings','company','country');
        }
        $fldAddr = ['primary_name', 'contact', 'address1', 'address2', 'city', 'state', 'postal_code', 'country'];
        $fldCont = ['contact_first', 'contact_last', 'flex_field_1', 'telephone1', 'telephone2', 'telephone3', 'telephone4', 'email', 'email2', 'email3', 'email4', 'website'];
        $fldAcct = ['short_name','inactive','rep_id','tax_rate_id','price_sheet','store_id','terms','terms_text','terms_edit','first_date','date_last','last_date_1','last_date_2','histPay'];
        $fldProp = ['id','account_number','gov_id_number','gl_account','recordID','tax_exempt','marketplace'];
        // set some special cases
//      $structure['type']['attr']['value']  = $this->type;
        $structure['email']['label']         = lang('email_sales');
        $structure['email2']['label']        = lang('email_ar');
        $structure['email3']['label']        = lang('email_purch');
        $structure['email4']['label']        = lang('email_ap');
        $structure['short_name']['tooltip']  = lang('msg_leave_null_to_assign_ref');
        $structure['inactive']['label']      = lang('status');
        $structure['inactive']['values']     = getModuleCache('contacts', 'statuses');
        $structure['rep_id']['values']       = viewRoleDropdown();
        $structure['tax_rate_id']['defaults']= ['value'=>$structure['tax_rate_id']['attr']['value'],'type'=>$this->type,'target'=>'inventory','callback'=>"var foo=0;"];
        // set some new fields
        $structure['terms_text']= ['order'=>61,'label'=>lang('terms'),'break'=>false,
            'attr'=>['value'=>viewTerms($structure['terms']['attr']['value'], true, $this->type), 'readonly'=>'readonly']];
        $structure['terms_edit']= ['order'=>62,'icon'=>'settings','label'=>lang('terms'),'events'=>['onClick'=>"jsonAction('$this->moduleID/$this->pageID/editTerms&type=$this->type',$rID,jqBiz('#terms').val());"]];
        $structure['recordID']  = ['order'=>99,'html'=>'<p>Record ID: '.$structure['id']['attr']['value']."</p>",'attr'=>['type'=>'raw']];
        $structure['histPay']   = ['order'=>95,'attr'=>['type'=>'button','value'=>$this->lang['payment_history']],'events'=>['onClick'=>"jsonAction('$this->moduleID/history/payment', $rID);"]];
        $status = $this->editStatus($structure, $rID);
        $prices = [['id'=>0, 'text'=>lang('none')]];
        $sheets = dbMetaGet('%', in_array($this->type, ['b','v'])?'price_v':'price_c'); // [getMetaCommon()];
        if (!empty($sheets)) { foreach ((array)$sheets as $row) { $prices[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; } }
        $structure['price_sheet']['values'] = $prices;
        switch ($this->type) {
            case 'c': $formID = 'cust:ltr'; break;
            case 'v': $formID = 'vend:ltr'; break;
            default:  $formID = false;
        }
        $data = ['type'=>'divHTML', 'title'=>$title,
            'divs'    => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbContacts'],
                'heading' => ['order'=>15,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmContact'],
                'tabs'    => ['order'=>50,'type'=>'tabs',   'key' =>'tabContacts'],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'toolbars'=> [
                'tbContacts'=> ['icons' => [
                    'save'    => ['order'=>20,'hidden'=>$security >1?false:true,       'events'=>['onClick'=>"if (jqBiz('#frmContact').form('validate')) { jqBiz('body').addClass('loading'); jqBiz('#frmContact').submit(); }"]],
                    'new'     => ['order'=>40,'icon'=>'add','hidden'=>$security >1?false:true,'events'=>['onClick'=>"accordionEdit('accContacts', 'dgContacts', 'divContactsDetail', '".lang('details')."', '$this->moduleID/$this->pageID/edit&type=$this->type', 0);"]],
                    'icnEmail'=> ['order'=>60,'hidden'=>$rID && $formID?false:true,    'events'=>['onClick'=>"winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&xfld=contacts.id&xcr=equal&xmin=$rID');"],'label'=>lang('email'),'icon'=>'email'],
                    'trash'   => ['order'=>80,'hidden'=>$rID && $security>3?false:true,'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/delete&type=$this->type', $rID, 'reset');"]]]]],
            'tabs'    => ['tabContacts'=>['divs'=>[
                'general'   => ['order'=>10,'label'=>lang('general'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'genAddA' => ['order'=>10,'type'=>'panel','key'=>'genAddA','classes'=>['block33']],
                    'genCont' => ['order'=>20,'type'=>'panel','key'=>'genCont','classes'=>['block33']],
                    'genStat' => ['order'=>30,'type'=>'panel','key'=>'genStat','classes'=>['block33']],
                    'genAcct' => ['order'=>40,'type'=>'panel','key'=>'genAcct','classes'=>['block33']],
                    'genProp' => ['order'=>50,'type'=>'panel','key'=>'genProp','classes'=>['block33']],
                    'genAtch' => ['order'=>85,'type'=>'panel','key'=>'genAtch','classes'=>['block66']]]],
                'history' => ['order'=>30,'label'=>lang('history'), 'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/history/manager&type=$this->type&rID=$rID'"]],
                'wallet'  => ['order'=>35,'label'=>lang('wallet'),'hidden'=>$rID?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=payment/wallet/manager&type=$this->type&rID=$rID'"]],
                'prices_c'=> ['order'=>40, 'label'=>sprintf(lang('tbd_prices'), lang('ctype_c')),'hidden'=>!empty($cData['ctype_c'])?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=inventory/prices/manager&dom=div&type=c&cID=$rID&table=contacts'"]],
                'prices_v'=> ['order'=>40, 'label'=>sprintf(lang('tbd_prices'), lang('ctype_v')),'hidden'=>!empty($cData['ctype_v'])?false:true,'type'=>'html', 'html'=>'',
                    'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=inventory/prices/manager&dom=div&type=v&cID=$rID&table=contacts'"]],
                'bill_add'=> ['order'=>45,'label'=>lang('address_type_b'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/address/manager&dom=div&type=$this->type&aType=b&refID=$rID'"]],
                'ship_add'=> ['order'=>50,'label'=>lang('address_type_s'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/address/manager&dom=div&type=$this->type&aType=s&refID=$rID'"]],
                'notes'   => ['order'=>70,'label'=>lang('notes'), 'type'=>'html', 'html'=>'',
                    'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/getTabNotes&type=$this->type&rID=$rID'"]]]]],
            'panels'  => [
                'genAddA' => ['label'=>lang('address_type_m'),'type'=>'address','keys'=>$fldAddr,'settings'=>['limit'=>'a','required'=>true]],
                'genCont' => ['label'=>lang('contact_info'),  'type'=>'fields', 'keys'=>$fldCont],
                'genStat' => ['label'=>$status['label'],      'type'=>'fields', 'keys'=>$status['fields']],
                'genAcct' => ['label'=>lang('account'),       'type'=>'fields', 'keys'=>$fldAcct],
                'genProp' => ['label'=>lang('properties'),    'type'=>'fields', 'keys'=>$fldProp],
                'genAtch' => ['type'=>'attach','defaults'=>['dgName'=>$this->moduleID.'Attach','path'=>getModuleCache($this->moduleID,'properties','attachPath','contacts'),'prefix'=>"rID_{$rID}_"]]],
            'forms'   => ['frmContact'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/save&type=$this->type"]]],
            'fields'  => $structure,
            'jsReady' => ['init'=>"ajaxForm('frmContact');"]];
        if (!validateAccess('admin', 4, false)) { unset($data['tabs']['tabContacts']['divs']['general']['divs']['genRole']); }
        // Some mods for new records
        if (!$rID) { unset($data['tabs']['tabContacts']['divs']['bill_add'], $data['tabs']['tabContacts']['divs']['ship_add']); }
        if       (!$rID && !empty($this->restrict)) { // Stores
            $data['fields']['store_id']['attr']['value'] = $this->myStore;
            $data['fields']['store_id']['attr']['type'] = 'hidden';
        } elseif (!$rID) {
            $data['fields']['store_id']['attr']['value'] = $this->myStore;
        }
        if (sizeof($this->stores) > 1) {
            $data['fields']['store_id']['attr']['type'] = 'select';
            $data['fields']['store_id']['values'] = viewStores();
        }
        if (in_array($this->type, ['c', 'v'])) { // Add CRM tab and enable ACH to General page
            $data['tabs']['tabContacts']['divs']['crm_add'] = ['order'=>25,'label'=>lang('address_type_i'), 'type'=>'html', 'html'=>'',
                'options'=> ['href'=>"'".BIZUNO_URL_AJAX."&bizRt=contacts/address/manager&dom=div&type=$this->type&aType=i&refID=$rID'"]];
            $data['panels']['genProp']['keys'][] = 'ach_enable';
        } else {
            unset($data['tabs']['tabContacts']['divs']['genStat'], $data['tabs']['tabContacts']['divs']['wallet'],
                  $data['tabs']['tabContacts']['divs']['prices_c'],$data['tabs']['tabContacts']['divs']['prices_v']);
        }
        if ($this->type=='c' && !empty(getMetaContact($rID, 'crm_project'))) { // only show CRM projects tab for customers
            $data['tabs']['tabContacts']['divs']['crm_proj'] = ['order'=>27,'label'=>lang('ctype_j'),'type'=>'html','html'=>'',
            'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/projects/manager&cID=$rID'"]];
        }
        customTabs($data, 'contacts', 'tabContacts');
        $this->editCustomType($data, $rID); // customize based on type
        $layout = array_replace_recursive($layout, $data);
    }

    private function editCustomType(&$data, $rID)
    {
        msgDebug("\nEntering editCustomType with rID = $rID and type = $this->type");
        switch ($this->type) {
            case 'b':
                unset($data['fields']['tax_rate_id'], $data['fields']['store_id']);
                $data['fields']['gl_account']['label']           = lang('default_gl_account').': '.lang('gl_acct_type_30'); // sales gl acct
                $data['fields']['terms']['label']                = lang('default_gl_account').': '.lang('gl_acct_type_4');  // Inv gl acct
                $data['fields']['account_number']['label']       = lang('default_gl_account').': '.lang('gl_acct_type_2');  // AR gl acct
                $data['fields']['gov_id_number']['label']        = lang('default_gl_account').': '.lang('gl_acct_type_20'); // AP gl acct
                $data['fields']['terms']['attr']['type']         = 'ledger';
                $data['fields']['account_number']['attr']['type']= 'ledger';
                $data['fields']['gov_id_number']['attr']['type'] = 'ledger';
                $data['fields']['terms']['order']                = 19;
                $data['fields']['account_number']['order']       = 25;
                $data['panels']['genProp']['keys'][] = 'terms';
                break;
            case 'c': // Customers
                $data['tabs']['tabContacts']['divs']['payment'] = ['order'=>60,'label'=>lang('payment'),'hidden'=>$rID && getUserCache('profile', 'admin_encrypt')?false:true,'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=payment/main/manager&rID=$rID'"]];
                break;
            case 'j': // Projects/Jobs
            case 'v': // Vendors
                break;
            case 'e': // Employees
                $data['panels']['genAcct']['keys'] = ['contact_first', 'contact_last', 'flex_field_1', 'short_name', 'inactive', 'store_id'];
                $data['panels']['genCont']['keys'] = ['telephone1','telephone2','telephone3','telephone4','email'];
                $data['panels']['genProp']['keys'] = ['id','gov_id_number','recordID','account_number'];
                $data['fields']['contact_first']['order']= 7;
                $data['fields']['contact_last']['order'] = 8;
                $data['fields']['flex_field_1']['order'] = 25;
                $data['fields']['account_number']['label'] = lang('sign_off_pin');
                break;
            case 'u': // Users
                // Handled as a hook in administrate
                break;
        }
        if (in_array($this->type, ['c','v'])) {
            $jIdx = $this->type=='v' ? "j6_mgr" : "j12_mgr";
            if (!validateAccess($jIdx, 1, false)) { $data['tabs']['tabContacts']['divs']['history']['hidden'] = true; }
            if (!getModuleCache('shipping', 'properties', 'status')) { unset($data['tabs']['tabContacts']['divs']['ship_add']); }
        } else {
            unset($data['tabs']['tabContacts']['divs']['crm_add'], $data['tabs']['tabContacts']['divs']['history']);
            unset($data['tabs']['tabContacts']['divs']['bill_add'],$data['tabs']['tabContacts']['divs']['ship_add']);
        }
    }

    /**
     * Pull the detailed aging for this contact
     * @param array $structure - Working fields array
     * @param integer $rID - contact record ID
     * @return array with label and fields list
     */
    private function editStatus(&$structure, $rID=0)
    {
        $output = ['label'=>'', 'fields'=>[]];
        msgDebug("\nEntering contacts::editStatus with rID = $rID");
        if (!in_array($this->type, ['c','v'])) { return $output; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/main.php', 'phreebooksMain');
        $layout = [];
        $pb = new phreebooksMain();
        $pb->detailStatus($layout, $rID);
        $idxs = ['current','late30','late60','late90','late120','late121','total'];
        foreach ($idxs as $idx) { $structure[$idx]= !empty($layout['fields'][$idx]) ? $layout['fields'][$idx] : []; }
        return ['label'=>$layout['panels']['genBal']['label'], 'fields'=>$idxs];
    }

    /**
     * Saves posted data to a contact record
     * @param array $layout - current working structure
     * @param boolean $makeTransaction - [default: true] Makes the save operation a transaction, should only be set to false if this method is part of another transaction
     * @return modified $laylout
     */
    public function save(&$layout=[], $makeTransaction=true)
    {
        global $io;
        $rID  = clean('id', 'integer', 'post');
        $title= clean('short_name', 'text', 'post');
        msgDebug("\nEntering $this->moduleID::save with rID = $rID and title = $title");
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        if ($makeTransaction) { dbTransactionStart(); } // START TRANSACTION (needs to be here as we need the id to create links
        if (!$result = $this->dbContactSave($this->type, '')) { return; } // Main record
        if (!$rID) { $rID = $result; }
        $_GET['rID'] = $_POST['id'] = $rID; // save for custom processing
        $this->saveLog($layout, $rID);
        if ($makeTransaction) { dbTransactionCommit(); }
        if ($io->uploadSave('file_attach', getModuleCache('contacts', 'properties', 'attachPath', 'contacts')."rID_{$rID}_")) {
            dbWrite(BIZUNO_DB_PREFIX.'contacts', ['attach'=>'1'], 'update', "id=$rID");
        }
        msgAdd(lang('msg_record_saved'), 'success'); // doesn't hang if returning to manager
        msgLog(sprintf(lang('tbd_manager'), lang("type_{$this->type}"))." - ".lang('save')." - $title (rID=$rID)");
        $data = ['content' => ['action'=>'eval','actionData'=>"jqBiz('#accContacts').accordion('select', 0); bizGridReload('dgContacts'); jqBiz('#divContactsDetail').html('&nbsp;');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Saves a log entry to a specified contact record
     * @param integer $rID - db record id of the contact to update/save log data
     */
    public function saveNotes()
    {
        $rID   = clean('rID', 'integer', 'get');
        if (!$rID) { return; }
        $meta  = dbMetaGet(0, 'notes', 'contacts', $rID);
        $metaID= metaIdxClean($meta);
        dbMetaSet($metaID, 'notes', clean('notes', 'text', 'post'), 'contacts', $rID);
        msgAdd(lang('msg_record_saved'), 'success');
    }

    /**
     * Saves a log entry to a specified contact record
     * @param integer $rID - db record id of the contact to update/save log data
     */
    public function saveLog(&$layout, $id=0)
    {
        $rID = $id ? $id : clean('rID', 'integer', 'get');
        $action= clean('crm_action','text', 'post');
        $note  = clean('crm_note',  'text', 'post');
        if (!$rID || !$note) { return; }
        $values = [
            'contact_id'=> $rID,
            'entered_by'=> clean('crm_rep_id','integer','post'),
            'log_date'  => clean('crm_date',  'date',   'post'),
            'action'    => $action,
            'notes'     => $note];
        dbWrite(BIZUNO_DB_PREFIX.'contacts_log', $values);
        $data = ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgLog'); jqBiz('#crm_note').val('');"]];
        if (!$id) { msgAdd(lang('msg_record_saved'), 'success'); } // if stand alone
        $layout = array_replace_recursive($layout, $data);

    }

    /**
     * Deletes a contact records from the database
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $rID   = clean('rID',  'integer', 'get');
        $action= clean('data', 'text', 'get');
        if (!$rID) { return msgAdd('The record was not deleted, the proper id was not passed!'); }
        // error check, no delete if a journal entry exists
        $block = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "contact_id_b='$rID' OR contact_id_s='$rID' OR store_id='$rID'");
        if ($block) { return msgAdd($this->lang['err_contacts_delete']); }
        $short_name = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'short_name', "id='$rID'");
        $actionData = "bizGridReload('dgContacts'); accordionEdit('accContacts','dgContacts','divContactsDetail','".jsLang('details')."','$this->moduleID/$this->pageID/edit&type=$this->type', 0);";
        if (!empty($action)) {
            $parts = explode(':', $action);
            switch ($parts[0]) {
                case 'reload': $actionData = "bizGridReload('{$parts[1]}');"; break; // just reload the datagrid
            }
        }
        $data = ['content'=>['action'=>'eval','actionData'=>$actionData],'dbAction'=>[
            'contacts'     => 'DELETE FROM '.BIZUNO_DB_PREFIX."contacts WHERE id=$rID",
            'contacts_meta'=> 'DELETE FROM '.BIZUNO_DB_PREFIX."contacts_meta WHERE ref_id=$rID",
            'contacts_log' => 'DELETE FROM '.BIZUNO_DB_PREFIX."contacts_log WHERE contact_id=$rID"]];
        $files = glob(getModuleCache('contacts', 'properties', 'attachPath', 'contacts')."rID_{$rID}_*.zip");
        if (is_array($files)) { foreach ($files as $filename) { @unlink($filename); } }
        msgLog(lang('contacts_title')." ".lang('delete')." - $short_name (rID=$rID)");
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Shows the details of a contact record, typically used for popups where no editing will take place
     * @param array $layout - structure
     * @return modified $layout
     */
    public function details(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID    = clean('rID', 'integer','get');
        $prefix = clean('prefix','text', 'get');
        $suffix = clean('suffix','text', 'get');
        $fill   = clean('fill',  'char', 'get');
        $address[] = addressLoad($rID);
        $address[0]['type'] = 'm';
        if (!$rID) { // biz_id=0, send company information
            $data = ['prefix'=>$prefix, 'suffix'=>$suffix, 'fill'=>$fill, 'contact'=>[], 'address'=>$address];
        } else {
            $contact= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$rID");
            $type   = $contact['ctype_v']=='1' ? 'v' : 'c';
            // Fix a few things
            $contact['terms_text']   = viewTerms($contact['terms'], true, $type);
            $contact['terminal_date']= getTermsDate($contact['terms'], $type);
            if (getModuleCache('shipping', 'properties', 'status') && getModuleCache('shipping', 'settings', 'general', 'gl_shipping_'.$type)) {
                $contact['ship_gl_acct_id'] = getModuleCache('shipping', 'settings', 'general', 'gl_shipping_'.$type);
            }
            $this->addAddresses($address, 'b');
            $this->addAddresses($address, 's');
            $data = ['prefix'=>$prefix, 'suffix'=>$suffix, 'fill'=>$fill, 'contact'=>$contact, 'address'=>$address];
            $data['showStatus'] = empty(getModuleCache('phreebooks', 'settings', $type=='v'?'vendors':'customers', 'show_status')) ? '0' : '1';
        }
        msgDebug("\nSending data = ".print_r($data, true));
        $layout = array_replace_recursive($layout, ['content'=>$data]);
    }

    private function addAddresses(&$address, $type='s')
    {
        $rID  = clean('rID', 'integer','get');
        $addr = dbMetaGet('%', "address_{$type}", 'contacts', $rID);
        metaIdxClean($addr);
        foreach ($addr as $row) {
            $row['address_id']= metaIdxClean($row);
            $row['type']      = $type;
            $row['ref_id']    = $rID;
            msgDebug("\nAdding address of type $type = ".print_r($row, true));
            $address[]        = $row;
        }
    }

    /**
     * Builds the contact popup (including hooks and customizations)
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function properties(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd("Bad cID passed!"); }
        compose('contacts', 'main', 'edit', $layout);
        unset($layout['divs']['formBOF'], $layout['divs']['formEOF'], $layout['divs']['toolbar']);
        unset($layout['tabs']['tabContacts']['divs']['general']['divs']['getAttach']);
        unset($layout['jsHead'], $layout['jsReady']);
        $layout['panels']['genAtch']['defaults']['noUpload']= true;
        $layout['panels']['genAtch']['defaults']['delPath'] = '';
    }

    /**
     * This method saves a contact to table: contacts
     * @param string $request - typically the post variables, leave false to use $_POST variables
     * @param char $cType - [default c] contact type, c (customer), v (vendor), e (employee), b (branch), i (CRM), j (projects)
     * @param string $suffix - [default null] field suffix to extract data from the request data
     * @param boolean $required - [default true] field suffix to extract data from the request data
     * @return $rID - record ID of the create/affected contact record
     */
    public function dbContactSave($cType='c', $suffix='', $required=true)
    {
        $rID   = clean('id'.$suffix, 'integer', 'post');
        if (!$security = validateAccess($this->secID, $rID?3:2)) { return; }
        $title = clean('primary_name'.$suffix, 'text', 'post');
        if (empty($title)) { $title = clean('primary_namem'.$suffix, 'text', 'post'); } // may happen either with or without address suffix
        $values= requestData(dbLoadStructure(BIZUNO_DB_PREFIX.'contacts'));
        $values['ctype_'.$cType] = '1'; // force the type or set if a suffix is used
        if (!$rID) { $values = array_merge($this->contact, $values); }
        else       { $values['date_last'] = biz_date('Y-m-d'); }
        // if contact is not required and these fields are set, do not create/update the contacts table
        if (!$required) { if (isset($values['contact_first']) && empty($values['contact_first']) &&
                              isset($values['contact_last'])  && empty($values['contact_last'])  &&
                              empty($title)) { return; } }
        if (!$rID && empty($values['short_name'])) { $values['short_name'] = $this->getNextShortName($values, $cType, $rID); } // auto generate new ID
        if (!empty($values['short_name'])) { // check for duplicate short_names
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($values['short_name'])."' AND id<>$rID");
            if ($dup) { return msgAdd(lang('error_duplicate_id')); }
        } else { unset($values['short_name']); } // existing record and no Contact ID passed, leave it alone
        if (empty($values['inactive'])) { $values['inactive'] = 0; } // fixes bug in conversions and prevents null inactive value
        $result = dbWrite(BIZUNO_DB_PREFIX.'contacts', $values, $rID?'update':'insert', "id=$rID");
        if (!$rID) { $rID = $result; }
        $_POST['id'.$suffix] = $rID; // save for customization
        msgDebug("\n  Finished adding/updating contact, id = $rID");
        return $rID;
    }

    /**
     * Generates the short_name for new contacts based on user preferences
     * @param array $values - post variables
     * @param string $cType - suffix from post variables to pull values
     * @param integer $rID - db record ID
     * @return type
     */
    private function getNextShortName($values, $cType, $rID)
    {
        msgDebug("\nIn getNextShortName with type = $cType and address = ".print_r($address, true));
        $output = getNextReference($this->type=='v' ? 'next_vend_id_num' : 'next_cust_id_num');
        if (!isset($values['short_name'])) { $values['short_name'] = ''; }
        while ($this->refTries > 0) {
            $this->refTries--;
            if (empty($values['short_name'])) { continue; }
            $dup = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($values['short_name'])."' AND id<>$rID"); //  AND type='$cType'
            if     ($dup && $this->refTries > 0) { $this->getNextShortName($values, $cType, $rID); } // loop back through as this auto ID is used
            elseif ($dup) { return msgAdd(lang('error_duplicate_id')); }
        }
        return $output;
    }

    public function addressUpdate($args=[])
    {
        $defaults= ['cID'=>0, 'aID'=>0, 'cType'=>'c', 'aType'=>'s', 'suffix'=>'_s', 'verbose'=>true, 'dropShip'=>false];
        $opts    = array_replace($defaults, $args);
        msgDebug("\nEntering addressUpdate with merged opts = ".print_r($opts, true));
        $priName = clean("primary_name{$opts['suffix']}", 'text', 'post'); // primary name is always required
        // Error check
        if (empty($priName)) {
            if (!empty($opts['verbose'])) { msgAdd(lang('primary_name_required')); }
            return false;
        }
        // Let's go
        if (!empty($opts['cID'])) { // get contact record
            $this->contact = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id={$opts['cID']}");
        } else { // create new contact
            $this->setDefaults();
            unset($this->contact['id']); // need to clear the id to prevent dups
            $this->contact['short_name'] = $this->getNextShortName(['short_name'=>''], $opts['cType'], 0);
            $this->contact['primary_name'] = $priName;
            $this->contact["ctype_{$opts['cType']}"] = '1';
            $opts['cID'] = dbWrite(BIZUNO_DB_PREFIX.'contacts', $this->contact);
        }
        if (empty($args['cID']) || (empty($opts['aID']) && $opts['aType']=='b')) { // new/update contact record address
            foreach ($this->addFields as $field) { $this->contact[$field] = clean($field.$opts['suffix'], 'text', 'post'); }
            if (empty($args['cID'])) { $this->contact['rep_id'] = clean('rep_id', 'integer', 'post'); } // if new record, set the rep_id
            dbSanitizeDates($this->contact, ['first_date', 'date_last', 'last_date_1', 'last_date_2']);
            msgDebug("\nReady to write updated contact record = ".print_r($this->contact, true));
            dbWrite(BIZUNO_DB_PREFIX.'contacts', $this->contact, 'update', "id={$opts['cID']}");
        } else { // update address meta
            $meta  = !empty($opts['aID']) ? dbMetaGet($opts['aID'], "address_{$opts['aType']}", 'contacts', $opts['cID']) : ['type'=>$opts['aType'], 'primary_name'=>$priName];
            metaIdxClean($meta);
            foreach ($this->addFields as $field) { $meta[$field] = clean($field.$opts['suffix'], 'text', 'post'); }
            $metaID= dbMetaSet($opts['aID'], "address_{$opts['aType']}", $meta, 'contacts', $opts['cID']);
            if (empty($opts['aID'])) { $opts['aID'] = $metaID; } // if new get the ID to return
        }
        return ['cID'=>$opts['cID'], 'aID'=>$opts['aID']];
    }

    /**
     * This function builds the grid structure for retrieving contacts
     * @param string $name - grid div id
     * @param char $type - contact type, c - customers, v - vendors, etc.
     * @param integer $security - access level range 0-4
     * @param string $rID - contact record id for CRM retrievals to limit results.
     * @return array $data - structure of the grid to render
     */
    protected function dgContacts($name, $type, $security=0, $rID=false)
    {
        $this->managerSettings($type);
        $statuses = array_merge([['id'=>'a','text'=>lang('all')]], getModuleCache('contacts','statuses'));
        $f0_value = "";
        if ($this->defaults['f0']<>'a') { $f0_value = "inactive='{$this->defaults['f0']}'"; }
        $data = ['id'=>"dg$name", 'rows'=>$this->defaults['rows'], 'page'=>$this->defaults['page'],
            'attr'=> ['idField'=>'id', 'toolbar'=>"#dg{$name}Toolbar", 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRows&type=$type".($rID?"&rID=$rID":'')],
            'events' => [
                'onDblClickRow'=> "function(rowIndex, rowData){ accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".jsLang('details')."', '$this->moduleID/$this->pageID/edit&type=$type&ref=$rID', rowData.id); }"],
            'footnotes' => $this->dgContactsFootnotes(),
            'source'    => [
                'tables' => ['contacts'=>['table'=>BIZUNO_DB_PREFIX.'contacts']],
                'search' => ['id', 'short_name', 'primary_name', 'city', 'postal_code', 'email'], // ,'contact','telephone1','telephone2','telephone3','telephone4','address1','address2'
                'actions' => [
                    'newContact'  =>['order'=>10,'icon'=>'add',  'events'=>['onClick'=>"accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".lang('details')."', '$this->moduleID/$this->pageID/edit&type=$type&ref=$rID', 0, '');"]],
                    'mergeContact'=>['order'=>40,'icon'=>'merge','hidden'=>$security>3?false:true,'events'=>['onClick'=>"jsonAction('$this->moduleID/tools/merge', 0);"]],
                    'clrSearch'   =>['order'=>50,'icon'=>'clear','events'=>['onClick'=>"jqBiz('#f0').val('$this->f0_default'); bizTextSet('search', ''); dg{$name}Reload();"]]],
                'filters' => [
                    'f0'    => ['order'=>10,'break'=>true,'label'=>lang('status'),'sql'=>$f0_value,'values'=>$statuses, 'attr'=>['type'=>'select','value'=>$this->defaults['f0']]],
                    'search'=> ['order'=>90,'attr'=>['id'=>'search', 'value'=>$this->defaults['search']]]],
                'sort' => ['s0'=>['order'=>10,'field'=>($this->defaults['sort'].' '.$this->defaults['order'])]]],
            'columns' => [
                'id'        => ['order'=>0,'field'=>'id',        'attr'=>['hidden'=>true]],
                'address2'  => ['order'=>0,'field'=>'address2',  'attr'=>['hidden'=>true]],
                'email'     => ['order'=>0,'field'=>'email',     'attr'=>['hidden'=>true]],
                'inactive'  => ['order'=>0,'field'=>'inactive',  'attr'=>['hidden'=>true]],
                'attach'    => ['order'=>0,'field'=>'attach',    'attr'=>['hidden'=>true]],
                'gl_account'=> ['order'=>0,'field'=>'gl_account','attr'=>['hidden'=>true]],
                'action'    => ['order'=>1,'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return dg{$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit'  => ['order'=>20, 'icon'=>'edit', 'label'=>lang('edit'),
                            'events'=> ['onClick' => "accordionEdit('acc$name', 'dg$name', 'div{$name}Detail', '".lang('details')."', '$this->moduleID/$this->pageID/edit&type=$type&ref=$rID', idTBD);"]],
                        'delete'=> ['order'=>60, 'icon'=>'trash', 'label'=>lang('delete'),'hidden'=>$security>3?false:true,
                            'events'=> ['onClick' => "if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/delete', idTBD, 'reload:dg$name');"]],
                        'chart' => ['order'=>80, 'icon'=>'mimePpt', 'label'=>lang('sales'),'hidden'=>in_array($type, ['c','v'])?false:true,
                            'events'=> ['onClick' => "windowEdit('$this->moduleID/tools/chartSales&cType=$type&rID=idTBD', 'myChart', '&nbsp;', 700, 450);"]],
                        'gLineP'=> ['order'=>83,'icon'=>'mimeXls','label'=>lang('purchases'),'hidden'=>in_array($type, ['v'])?false:true,
                            'events'=>['onClick'=>"windowEdit('$this->moduleID/tools/chartHistPurch&rID=idTBD', 'myContPurch', '&nbsp;', 600, 500);"]],
                        'gLineS'=> ['order'=>85,'icon'=>'mimeDoc','label'=>lang('sales'),    'hidden'=>in_array($type, ['c'])?false:true,
                            'events'=>['onClick'=>"windowEdit('$this->moduleID/tools/chartHistSales&rID=idTBD', 'myContSales', '&nbsp;', 600, 500);"]],
                        'attach' => ['order'=>95,'icon'=>'attachment','display'=>"row.attach=='1'"]]],
                'short_name'   => ['order'=>10, 'field'=>'short_name', 'label' => lang('short_name'),'events'=>['styler'=>$this->dgContactsStyler()],
                    'attr' => ['width'=>100, 'sortable'=>true, 'resizable'=>true]],
                'primary_name' => ['order'=>20, 'field'=>'primary_name', 'label' => lang('primary_name'),
                    'attr' => ['width'=>240, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?true:false]],
                'contact_first'=> ['order'=>20, 'field' => 'contact_first', 'label' => lang('contact_first'),
                    'attr' => ['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?false:true]],
                'contact_last' => ['order'=>25, 'field'=>'contact_last', 'label' => lang('contact_last'),
                    'attr' => ['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['e','i'])?false:true]],
                'role_id'      => ['order'=>25, 'field' => 'id', 'label' => lang('role'),
                    'attr' => ['width'=>150, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?false:true], 'format'=>'roleName'],
                'store_id'    => ['order'=>30, 'field' => 'store_id', 'label' => lang('store_id'),
                    'attr' => ['width'=>150, 'sortable'=>true,'resizable'=>true, 'hidden'=> sizeof($this->stores)>1?false:true], 'format'=>'storeID'],
                'flex_field_1'=> ['order'=>35, 'field'=>'flex_field_1', 'label' => lang('flex_field_1'),
                    'attr' => ['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['i'])?false:true]],
                'email'       => ['order'=>40, 'field'=>'email', 'label' => lang('email'),
                    'attr' => ['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?false:true]],
                'address1'    => ['order'=>40, 'field'=>'address1', 'label' => lang('address1'),
                    'attr' => ['width'=>200, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?true:false]],
                'city'        => ['order'=>40, 'field'=>'city', 'label' => lang('city'),
                    'attr' => ['width'=>80, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?true:false]],
                'state'       =>  ['order'=>50, 'field'=>'state', 'label' => lang('state'),
                    'attr' => ['width'=>60, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?true:false]],
                'postal_code' => ['order'=>60, 'field'=>'postal_code', 'label' => lang('postal_code'),
                    'attr' => ['width'=>60, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?true:false]],
                'telephone1'  => ['order'=>70, 'field'=>'telephone1', 'label' => lang('telephone1'),
                    'attr' => ['width'=>100, 'sortable'=>true, 'resizable'=>true, 'hidden'=> in_array($type,['u'])?true:false]]],
            ];
        if ($type == 'i') {
            $data['source']['search'][] = 'contact_first';
            $data['source']['search'][] = 'contact_last';
            $data['source']['search'][] = 'flex_field_1';
        }
        if (getUserCache('profile', 'restrict_user', false, 0)) { // check for user restrictions
            $uID = getUserCache('profile', 'userID', false, 0);
            $data['source']['filters']['restrict_user'] = ['order'=>99, 'hidden'=>true, 'sql'=>"rep_id='$uID'"];
        }
        if (getUserCache('profile', 'device') == 'mobile') {
            $data['columns']['short_name']['attr']['hidden']  = true;
            $data['columns']['flex_field_1']['attr']['hidden']= true;
            $data['columns']['address1']['attr']['hidden']    = true;
            $data['columns']['state']['attr']['hidden']       = true;
            $data['columns']['postal_code']['attr']['hidden'] = true;
            $data['columns']['store_id']['attr']['hidden']    = true;
        }
        if (!empty($this->restrict)) {
            $data['source']['filters']['store_id'] = ['order'=>0,'hidden'=>true,'sql'=>"(store_id=-1 OR store_id={$this->myStore})"];
            unset($data['columns']['store_id']);
        }
        return $data;
    }

    private function dgContactsStyler()
    {
        $html = 'function(value,row,index) { ';
        $statuses = getModuleCache('contacts','statuses');
        msgDebug("\nstatuses = ".print_r($statuses, true));
        foreach ($statuses as $status) {
            if (empty($status['color'])) { continue; }
            $html .= "if (row.inactive=='{$status['id']}') { return {class:'row-{$status['color']}'}; }";
        }
        return $html.'}';
    }

    private function dgContactsFootnotes()
    {
        $html = lang('color_codes').': ';
        $statuses = getModuleCache('contacts','statuses');
        foreach ($statuses as $status) {
            if (empty($status['color'])) { continue; }
            $html .= '<span class="row-'.$status['color'].'">&nbsp;'.$status['text'].'&nbsp;</span>&nbsp;';
        }
        return ['codes'=>$html];
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function getTabNotes(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 1)) { return; }
        $rID   = clean('rID', 'integer','get');
        if (empty($rID)) { return msgAdd(lang('bad_data')); }
//dbGetResult("DELETE FROM contacts_meta WHERE ref_id=$rID");
        $notes = getMetaContact($rID, 'notes');
        msgDebug("\nRead notes from meta = ".print_r($notes, true));
        $fldLog= [
            'notes'     => ['order'=>50,'label'=>'',          'attr'=>['type'=>'editor','value'=>$notes['value']]],
            'crm_date'  => ['order'=>10,'label'=>lang('date'),'attr'=>['type'=>'date',  'value'=>viewDate(biz_date('Y-m-d'))]],
            'crm_rep_id'=> ['order'=>20,'label'=>lang('log_entered_by'),'values'=>viewRoleDropdown('all'),'attr'=>['type'=>'select','value'=>getUserCache('profile', 'userID', false, '0')]],
            'crm_action'=> ['order'=>30,'label'=>lang('action'),        'values'=>viewKeyDropdown(getModuleCache('contacts', 'actions_crm'), true),'attr'=>['type'=>'select']],
            'crm_note'  => ['order'=>40,'label'=>'',          'attr'=>['type'=>'textarea','rows'=>5]]];
        $data  = ['type'=>'divHTML',
            'divs'   => [
                'general'=> ['order'=>50,'type'=>'divs','attr'=>['id'=>'crmDiv'],'classes'=>['areaView'],'divs'=>[
                    'notes'=> ['order'=>30,'type'=>'panel','key'=>'notes','classes'=>['block33'],'attr'=>['id'=>'divNotes']],
                    'cLog' => ['order'=>60,'type'=>'panel','key'=>'cLog', 'classes'=>['block66'],'attr'=>['id'=>'divLog']]]]],
            'toolbars'=> [
                'tbNote'=> ['icons'=>['save'=>['order'=>10,'icon'=>'save','hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('$this->moduleID/$this->pageID/saveNotes&rID=$rID', 'divNotes');"]]]],
                'tbLog' => ['icons'=>['save'=>['order'=>10,'icon'=>'save','hidden'=>$rID && $security >1?false:true,'events'=>['onClick'=>"divSubmit('$this->moduleID/$this->pageID/saveLog&rID=$rID', 'divLog');"]]]]],
            'panels' => [
                'notes'=> ['type'=>'divs','divs'=>[
                    'tbNote' => ['order'=>20,'type'=>'toolbar','key'=>'tbNote'],
                    'fldNote'=> ['order'=>50,'type'=>'fields', 'keys'=>['notes']]]],
                'cLog' => ['label'=>lang('contacts_log'),'type'=>'divs','divs'=>[
                    'tbLog'  => ['order'=>20,'type'=>'toolbar', 'key'=>'tbLog'],
                    'fldLog' => ['order'=>50,'type'=>'fields',  'keys'=>['crm_note','crm_date','crm_rep_id','crm_action']],
                    'dgLog'  => ['order'=>80,'type'=>'datagrid','key'=>'dgLog']]]],
            'fields' => $fldLog,
            'datagrid'=>['dgLog'=>$this->dgLog('dgLog', $rID, $security)]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Gets the rows for the contacts log grid
     * @param array $layout - working structure
     * @return modified $layout
     */
    public function managerRowsLog(&$layout=[])
    {
        $rID   = clean('rID', 'integer', 'get');
        $layout= array_replace_recursive($layout, ['type'=>'datagrid','key'=>'log','datagrid'=>['log'=>$this->dgLog('dgLog', $rID)]]);
    }

    /**
     * The method deletes a record from the contacts_log table
     * @param integer $rID - typically a $_GET variable but can also be passed to the function in an array
     * @return array with dbAction and content to remove entry from grid
     */
    public function deleteLog(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd("Bad ID submitted!"); }
        msgLog(lang('contacts_log').' '.lang('delete')." - ($rID)");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"bizGridReload('dgLog');"],
            'dbAction'=> [BIZUNO_DB_PREFIX."contacts_log"=>"DELETE FROM ".BIZUNO_DB_PREFIX."contacts_log WHERE id=$rID"]]);
    }

    /**
     * Builds the grid structure for the contacts log
     * @param string $name - HTML grid field name
     * @param integer $rID - database contact record id
     * @param integer $security - users approved security level
     * @return array - grid structure
     */
    private function dgLog($name, $rID=0, $security=0)
    {
        $rows  = clean('rows', ['format'=>'integer',  'default'=>10],        'post');
        $page  = clean('page', ['format'=>'integer',  'default'=> 1],        'post');
        $sort  = clean('sort', ['format'=>'text',     'default'=>'log_date'],'post');
        $order = clean('order',['format'=>'text',     'default'=>'desc'],    'post');
        $search= clean('search_log',['format'=>'text','default'=>''],        'post');
        return ['id'=>$name, 'rows'=>$rows, 'page'=>$page,
            'attr'   => ['nowrap'=>false, 'toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/managerRowsLog&rID=$rID"],
            'source' => [
                'tables' => ['contacts_log'=>['table'=>BIZUNO_DB_PREFIX."contacts_log"]],
                'search' => ['notes'],
                'actions' => [
                    'clrSearch' => ['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search_log', ''); {$name}Reload();"]]],
                'filters'=> [
                    'search'=> ['order'=>90,'attr'=>['id'=>"search_log", 'value'=>$search]],
                    'rID'   => ['order'=>99,'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."contacts_log.contact_id='$rID'"]],
                'sort'   => ['s0' => ['order'=>10,'field'=>"$sort $order"]]],
            'columns' => [
                'id' => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."contacts_log.id",'attr'=>['hidden'=>true]],
                'action' => ['order'=>1,'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'logTrash' => ['order'=>80,'icon'=>'trash','label'=>lang('delete'),'hidden'=>$security>3?false:true,
                            'events' => ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/$this->pageID/deleteLog', idTBD);"]]]],
                'log_date'  => ['order'=>10,'field'=>"log_date",  'label'=>lang('date'),          'attr'=>['width'=> 50, 'resizable'=>true], 'format'=>'date'],
                'entered_by'=> ['order'=>20,'field'=>"entered_by",'label'=>lang('log_entered_by'),'attr'=>['width'=> 50, 'resizable'=>true], 'format'=>'contactID'],
                'action'    => ['order'=>30,'field'=>"action",    'label'=>lang('action'),        'attr'=>['width'=> 50, 'resizable'=>true], 'format'=>'cache:contacts:actions_crm'],
                'notes'     => ['order'=>40,'field'=>"notes",     'label'=>lang('notes'),         'attr'=>['width'=>200, 'resizable'=>true]]]];
    }

    /**
     * Builds the editor for contact payment terms
     * @param array $layout - current working structure
     * @param char $defType - contact type
     */
    public function editTerms(&$layout=[], $defType='c')
    {
        $prefix = clean('prefix', ['format'=>'text','default'=>''],  'get');
        $default= clean('default',['format'=>'integer','default'=>0],'get');
        $fields = $this->getTermsDiv($defType, $default);
        $data   = ['type'=>'popup','title'=>lang('terms'),'attr'=>['id'=>'winTerms','width'=>650],
            'toolbars' => ['tbTerms'=>['icons'=>['next'=>['order'=>20,'events'=>['onClick'=>"jqBiz('#frmTerms').submit();"]]]]],
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbTerms'],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmTerms'],
                'winTerms'=> ['order'=>50,'type'=>'fields', 'keys'=>array_keys($fields)],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'forms'    => ['frmTerms'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/setTerms&prefix=$prefix"]]],
            'fields'   => $fields,
            'jsReady'  => ['init'=>"ajaxForm('frmTerms');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $defType
     * @return array
     */
    private function getTermsDiv($defType='c', $default=0)
    {
        $type   = clean('type',['format'=>'char',   'default'=>$defType],'get');
        $cID    = clean('id',  ['format'=>'integer','default'=>0],       'get');
        $encoded= clean('data',['format'=>'text',   'default'=>false],   'get');
        if     (!$encoded && $cID)      { $encoded = dbGetValue(BIZUNO_DB_PREFIX."contacts", 'terms', "id=$cID"); }
        elseif (!$encoded && $type=='v'){ $encoded = getModuleCache('phreebooks', 'settings', 'vendors', 'terms'); }
        elseif (!$encoded)              { $encoded = getModuleCache('phreebooks', 'settings', 'customers', 'terms'); }
        $terms  = explode(':', $encoded);
        $defNET = isset($terms[3]) && $terms[0]==3 ? $terms[3] : '30';
        $defDOM = isset($terms[3]) && $terms[0]==4 ? $terms[3] : biz_date('Y-m-d');
        $fields = [
            'terms_disc'  => ['options'=>['width'=>40],'attr'=>['type'=>'float',  'value'=>isset($terms[1])?$terms[1]:0,'maxlength'=>3]],
            'terms_early' => ['options'=>['width'=>40],'attr'=>['type'=>'float',  'value'=>isset($terms[2])?$terms[2]:0,'maxlength'=>3]],
            'terms_net'   => ['options'=>['width'=>40],'attr'=>['type'=>'integer','value'=>$defNET,'maxlength'=>'3']]];
        $custom = ' - '.sprintf(lang('terms_discount'), html5('terms_disc', $fields['terms_disc']), html5('terms_early', $fields['terms_early'])).' '.sprintf(lang('terms_net'), html5('terms_net',$fields['terms_net']));
        $output = [
            'radio0'    => ['order'=>10,'label'=>lang('terms_default').' ['.viewTerms('0', false, $terms[0]).']','attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>0,'checked'=>$terms[0]==0?true:false]],
            'radio3'    => ['order'=>20,'break'=>false,'label'=>lang('terms_custom'),'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>3,'checked'=>$terms[0]==3?true:false]],
            'r1Disc'    => ['order'=>21,'html' =>$custom,'attr'=>['type'=>'raw']],
            'radio6'    => ['order'=>30,'label'=>lang('terms_now'),                  'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>6,'checked'=>$terms[0]==6?true:false]],
            'radio2'    => ['order'=>40,'label'=>lang('terms_prepaid'),              'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>2,'checked'=>$terms[0]==2?true:false]],
            'radio1'    => ['order'=>50,'label'=>lang('terms_cod'),                  'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>1,'checked'=>$terms[0]==1?true:false]],
            'radio4'    => ['order'=>60,'break'=>false,'label'=>lang('terms_dom'),   'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>4,'checked'=>$terms[0]==4?true:false]],
            'terms_date'=> ['order'=>61,'attr' =>['type'=>'date', 'value'=>$defDOM]],
            'radio5'    => ['order'=>70,'label'=>lang('terms_eom'),    'attr'=>['type'=>'radio','id'=>'terms_type','name'=>'terms_type','value'=>5,'checked'=>$terms[0]==5?true:false]],
            'hr1'       => ['order'=>71,'html' =>'<hr>','attr'=>['type'=>'raw']],
            'credit'    => ['order'=>80,'label'=>lang('terms_credit_limit'),'attr'=>['type' =>'currency','value'=>isset($terms[4])?$terms[4]:'1000']]];
        if ($default) { unset($output['radio0']); }
        return $output;
    }

    /**
     * Gets translated text for a specified encoded term passed through ajax
     * @param array $layout - current working structure
     * @return array - modified $layout
     */
    public function setTerms(&$layout=[])
    {
        $type  = clean('terms_type','integer','post');
        $prefix= clean('prefix',    'cmd',    'get');
        $enc   = "$type:".clean('terms_disc', 'float', 'post').":".clean('terms_early', 'integer', 'post').":";
        $enc  .= ($type==4 ? clean('terms_date', 'date', 'post') : clean('terms_net', 'integer', 'post')).":".clean('credit', 'currency', 'post');
        msgDebug("\n Received and created encoded terms = $enc");
        $data = ['content'=>['action'=>'eval','actionData'=>"bizTextSet('{$prefix}terms', '$enc'); bizTextSet('{$prefix}terms_text', '".jsLang(viewTerms($enc))."'); bizWindowClose('winTerms');"]];
        $layout = array_replace_recursive($layout, $data);
    }
}
