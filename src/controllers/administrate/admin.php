<?php
/*
 * Module Bizuno Administration - Admin 
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
 * @version    7.x Last Update: 2025-06-01
 * @filesource /controllers/administrate/admin.php
 */

namespace bizuno;

class administrateAdmin
{
    public    $moduleID = 'administrate';
    public    $pageID   = 'admin';
    protected $secID    = 'admin';
    protected $domSuffix= 'Admin';
    private   $mailDefs = [];

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->defaults = ['store_id'=>0, 'restrict_store'=>0, 'restrict_user'=>0, 'restrict_period'=>0, // 'role_id'=>0, 
            'cash_acct'=> getModuleCache('phreebooks','settings','customers','gl_cash'),
            'ar_acct'  => getModuleCache('phreebooks','settings','customers','gl_receivables'),
            'ap_acct'  => getModuleCache('phreebooks','settings','vendors',  'gl_payables'),
            'smtp_enable'=>0, 'smtp_host'=>'https://smtp.gmail.com', 'smtp_port'=>587, 'smtp_user'=>'', 'smtp_pass'=>''];
        $this->structure = [
            'hooks'=>['contacts'=>['main'=>['edit'=>['order'=>10,'method'=>'contactsEdit'], 'save'=>['order'=>10,'method'=>'contactsSave']]]]];
    }

    /**
     * Extends the users editor for Phreebooks specific fields
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function contactsEdit(&$layout=[])
    {
        global $portal;
        $type= clean('type', 'char', 'get');
        $rID = clean('rID', 'integer', 'get');
        msgDebug("\nEntering administrate::contactsEdit with type = $type");
        if ((empty($rID) && method_exists($portal, 'contactTypeUser')) ||
            !validateAccess('admin', 4)) { return; } // Only existing records, admins and type user
        // unset some panels
        msgDebug("\nPassed security");
        unset($layout['tabs']['tabContacts']['divs']['history'],$layout['tabs']['tabContacts']['divs']['wallet'],
              $layout['tabs']['tabContacts']['divs']['prices'], $layout['tabs']['tabContacts']['divs']['bill_add'],
              $layout['tabs']['tabContacts']['divs']['ship_add']);
        unset($layout['tabs']['tabContacts']['divs']['general']['divs']['genAddA'], $layout['tabs']['tabContacts']['divs']['general']['divs']['genProp'],
              $layout['tabs']['tabContacts']['divs']['general']['divs']['genStat'], $layout['tabs']['tabContacts']['divs']['general']['divs']['genAtch']);
        // Get the meta to add the new options and process
        $meta   = getMetaContact($rID, 'user_profile');
        msgDebug("\nRead meta = ".print_r($meta, true));
        $opts = array_replace_recursive($this->defaults, $meta);
        msgDebug("\nAfter replace, meta = ".print_r($opts, true));
        // Adjust fields and add role select
        $rFields= ['role_id'=>['order'=>10,'label'=>lang('role'),'attr'=>['type'=>'roles','value'=>$meta['role_id']],'options'=>['hideAll'=>true,'single'=>true]]];
        $layout['panels']['genCont']['keys'] = ['role_id', 'email', 'telephone1', 'id', 'primary_name'];
        // add contact type access panel
        $fldRole= ['ctype_b','ctype_c','ctype_e','ctype_i','ctype_j','ctype_u','ctype_v'];
        $layout['tabs']['tabContacts']['divs']['general']['divs']['genRole'] = ['order'=>45,'type'=>'panel','key'=>'genRole','classes'=>['block33']];
        $layout['panels']['genRole'] = ['label'=>lang('contact_type'),  'type'=>'fields', 'keys'=>$fldRole];
        // Add phreebooks panel
        $pbFields = [
            'store_id'       => ['order'=>10,'label'=>$this->lang['store_id_lbl'],       'tip'=>$this->lang['store_id_tip'],       'attr'=>['type'=>'select',  'value'=>$opts['store_id']], 'values'=>viewStores()],
            'restrict_store' => ['order'=>20,'label'=>$this->lang['restrict_store_lbl'], 'tip'=>$this->lang['restrict_store_tip'], 'attr'=>['type'=>'checkbox','checked'=>!empty($opts['restrict_store'])?true:false]],
            'restrict_user'  => ['order'=>30,'label'=>$this->lang['restrict_user_lbl'],  'tip'=>$this->lang['restrict_user_tip'],  'attr'=>['type'=>'checkbox','checked'=>!empty($opts['restrict_user']) ?true:false]],
            'restrict_period'=> ['order'=>40,'label'=>$this->lang['restrict_period_lbl'],'tip'=>$this->lang['restrict_period_tip'],'attr'=>['type'=>'checkbox','checked'=>!empty($opts['restrict_period']?true:false)]],
            'cash_acct'      => ['order'=>50,'label'=>$this->lang['gl_cash_lbl'],        'tip'=>$this->lang['gl_cash_tip'],        'attr'=>['type'=>'ledger',  'value'=>$opts['cash_acct']]],
            'ar_acct'        => ['order'=>60,'label'=>$this->lang['gl_receivables_lbl'], 'tip'=>$this->lang['gl_receivables_tip'], 'attr'=>['type'=>'ledger',  'value'=>$opts['ar_acct']]],
            'ap_acct'        => ['order'=>70,'label'=>$this->lang['gl_purchases_lbl'],   'tip'=>$this->lang['gl_purchases_tip'],   'attr'=>['type'=>'ledger',  'value'=>$opts['ap_acct']]]];
        $layout['tabs']['tabContacts']['divs']['general']['divs']['genPB'] = ['order'=>75,'type'=>'panel','key'=>'genPB','classes'=>['block33']];
        $layout['panels']['genPB'] = ['label'=>lang('phreebooks_defaults'), 'type'=>'fields', 'keys'=>array_keys($pbFields)];
        $layout['fields'] = array_merge($layout['fields'], $pbFields, $rFields);
    }

    /**
     * Extends the users save method with Phreebooks specific fields
     * @return boolean null
     */
    public function contactsSave(&$layout=[])
    {
        $type= clean('type', 'char', 'get');
        msgDebug("\nEntering phreebooks::contactsSave with type = $type");
        if (!$cID = clean('id', 'integer', 'post')) { return; }
        if (!in_array($type, ['u']) || !validateAccess('admin', 4)) { return; } // Only users
        $meta= dbMetaGet(0, 'user_profile', 'contacts', $cID);
        $rID = metaIdxClean($meta);
        $data= [
            'role_id'        => clean('role_id',        'integer','post'),
            'store_id'       => clean('store_id',       'integer','post'),
            'restrict_store' => clean('restrict_store', 'boolean','post'),
            'restrict_user'  => clean('restrict_user',  'boolean','post'),
            'restrict_period'=> clean('restrict_period','boolean','post'),
            'cash_acct'      => clean('cash_acct',      'cmd',    'post'),
            'ar_acct'        => clean('ar_acct',        'cmd',    'post'),
            'ap_acct'        => clean('ap_acct',        'cmd',    'post'),
            'smtp_host'      => clean('smtp_host',      'url',    'post'),
            'smtp_port'      => clean('smtp_port',      'integer','post'),
            'smtp_user'      => clean('smtp_user',      'email',  'post')];
        $pass = clean('smtp_pass', 'text', 'post');
        if (!empty($pass)) { $data['smtp_pass'] = $pass; }
        $output = array_replace(!empty($meta)?$meta:[], $data);
        dbMetaSet($rID, 'user_profile', $output, 'contacts', $cID);
    }
}