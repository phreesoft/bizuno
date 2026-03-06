<?php
/*
 * Administration methods for the contacts module
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
 * @version    7.x Last Update: 2026-02-28
 * @filesource /controllers/contacts/admin.php
 */

namespace bizuno;

class contactsAdmin
{
    public $moduleID = 'contacts';
    public $pageID = 'admin';
    public $lang;
    public $settings;
    public $structure;
    public $phreeformProcessing;

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure= [
            'api'       => ['path'=>'contacts/api/contactsAPI'],
            'attachPath'=> ['contacts'=>'data/contacts/uploads/'],
            'menuBar'   => ['child'=>[
                'customers'=> ['order'=>10,'label'=>('customers'),'group'=>'cust','icon'=>'sales','child'=>[
                    'mgr_c'   => ['order'=>10,'label'=>sprintf(lang('tbd_manager'), lang('ctype_c')),'icon'=>'users','route'=>'contacts/main/manager&type=c'],
                    'prices_c'=> ['order'=>70,'label'=>('ctype_c_prc'),'icon'=>'price',  'route'=>'inventory/prices/manager&type=c'],
                    'projects'=> ['order'=>80,'label'=>('projects'),   'icon'=>'support','route'=>"$this->moduleID/projects/manager"],
                    'promos'  => ['order'=>90,'label'=>('promotions'), 'icon'=>'email',  'route'=>"$this->moduleID/promos/manager"],
                    'rpt_c'   => ['order'=>99,'label'=>('reports'),    'icon'=>'mimeDoc','route'=>'phreeform/main/manager&gID=cust']]],
                'vendors'  => ['order'=>20,'label'=>('vendors'),  'group'=>'vend','icon'=>'purchase','child'=>[
                    'mgr_v'   => ['order'=>10,'label'=>sprintf(lang('tbd_manager'), lang('ctype_v')),'icon'=>'users','route'=>"contacts/main/manager&type=v"],
                    'prices_v'=> ['order'=>70,'label'=>('ctype_v_prc'),'icon'=>'price',  'route'=>'inventory/prices/manager&type=v'],
                    'rpt_v'   => ['order'=>99,'label'=>('reports'),    'icon'=>'mimeDoc','route'=>'phreeform/main/manager&gID=vend']]]]],
//          'hooks'     => ['phreebooks'=>['tools'=>['fyCloseHome'=>['order'=>50,'page'=>'tools'],'fyClose'=>['order'=>50,'page'=>'tools']]]],
            ];
        $this->phreeformProcessing = [
            'qtrNeg0'    => ['text'=>lang('dates_quarter').' (contact_id_b)'],
            'qtrNeg1'    => ['text'=>lang('dates_lqtr')   .' (contact_id_b)'],
            'qtrNeg2'    => ['text'=>lang('quarter_neg2') .' (contact_id_b)'],
            'qtrNeg3'    => ['text'=>lang('quarter_neg3') .' (contact_id_b)'],
            'qtrNeg4'    => ['text'=>lang('quarter_neg4') .' (contact_id_b)'],
            'qtrNeg5'    => ['text'=>lang('quarter_neg5') .' (contact_id_b)'],
            'contactID'  => ['text'=>lang('short_name'),                 'module'=>'bizuno','function'=>'viewFormat'],
            'contactName'=> ['text'=>lang('primary_name'),               'module'=>'bizuno','function'=>'viewFormat'],
            'cIDStatus'  => ['text'=>lang('status')    .' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'cIDAttn'    => ['text'=>lang('contact')   .' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'cIDTele1'   => ['text'=>lang('telephone') .' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'cIDTele4'   => ['text'=>lang('telephone4').' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'cIDEmail'   => ['text'=>lang('email')     .' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'cIDWeb'     => ['text'=>lang('website')   .' (Contact rID)','module'=>'bizuno','function'=>'viewFormat'],
            'contactGID' => ['text'=>lang('contacts_gov_id_number') .' ('.lang('id').')','group'=>lang('title', $this->moduleID),'module'=>'bizuno','function'=>'viewFormat']];
        setProcessingDefaults($this->phreeformProcessing, $this->moduleID, lang('title', $this->moduleID));
    }

    /**
     * Sets the structure of the user settings for the contacts module
     * @return array - user settings
     */
    public function settingsStructure()
    {
        $data = [
//          'general'  => ['order'=>10,'label'=>lang('general'),'fields'=>[]],
            'contacts' => ['order'=>20,'label'=>lang('address_book'),'fields'=>[
                'primary_name'=> ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'address1'    => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'city'        => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'state'       => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'postal_code' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'telephone1'  => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'email'       => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    public function initialize()
    {
        // Rebuild some option values
        $metaStat= dbMetaGet('%', 'options_contact_status');
        $idxStat = metaIdxClean($metaStat); // remove the indexes
        $stat0   = [['id'=>'0','text'=>lang('active')], ['id'=>'1','text'=>lang('inactive'),'color'=>'DarkRed'], ['id'=>'2','text'=>lang('locked'),'color'=>'DarkOrange']];
        $stat1   = sortOrder($stat0, 'text');
        dbMetaSet($idxStat, 'options_contact_status', $stat1);
        
        $metaCRM= dbMetaGet('%', 'options_crm_actions');
        $idxFreq= metaIdxClean($metaCRM); // remove the indexes
        $crm0   = ['new' =>lang('contacts_crm_new_call', $this->moduleID), 'ret' =>lang('contacts_crm_call_back', $this->moduleID), 'flw' =>lang('contacts_crm_follow_up', $this->moduleID),
                   'lead'=>lang('contacts_crm_new_lead', $this->moduleID), 'trn' =>lang('training'), 'inac'=>lang('inactive')];
        asort($crm0);
        dbMetaSet($idxFreq, 'options_crm_actions', $crm0);
        // Put them in the cache for runtime access
        setModuleCache('contacts', 'statuses', false, $stat1);
        setModuleCache('contacts', 'actions_crm', false, $crm0);
        return true;
    }

    /**
     * Builds the home menu for settings of the contacts module
     * @param array $layout - current working structure
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $clnDefault = localeCalculateDate(biz_date('Y-m-d'), 0, -1);
        $fields = [
            'j9CloseDesc'  => ['order'=>10,'html' =>lang('close_j9_desc', $this->moduleID),'attr'=>['type'=>'raw']],
            'dateJ9Close'  => ['order'=>20,'label'=>lang('close_j9_label', $this->moduleID),'attr'  =>['type'=>'date','value'=>$clnDefault]],
            'btnJ9Close'   => ['order'=>30,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('contacts/tools/j9Close', 0, jqBiz('#dateJ9Close').datebox('getValue'));"],
                'attr' => ['type'=>'button','value'=>lang('start')]],
            'syncAtchDesc' => ['order'=>10,'html'=>lang('sync_attach_desc', $this->moduleID),'attr'=>['type'=>'raw']],
            'btnSyncAttach'=> ['order'=>20,'events'=>['onClick' => "jqBiz('body').addClass('loading'); jsonAction('contacts/tools/syncAttachments&verbose=1');"],
                'attr' => ['type'=>'button','value'=>lang('go')]]];
        $data  = [
            'tabs'    => ['tabAdmin'=>['divs'=>[
//                'fields'=> ['order'=>40,'label'=>lang('extra_fields'),'type'=>'html','html'=>'','options'=>["href"=>"'".BIZUNO_URL_AJAX."&bizRt=administrate/fields/manager&module=$this->moduleID&table=contacts'"]],
                'tools' => ['order'=>80,'label'=>lang('tools'),'type'=>'divs','divs'=>[
                    'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                        'closeJ9' => ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'closeJ9'],
                        'syncAtch'=> ['order'=>40,'type'=>'panel','classes'=>['block33'],'key'=>'syncAtch']]]]]]]],
            'panels'  => [
                'closeJ9' => ['label'=>lang('close_j9_title', $this->moduleID),   'type'=>'fields','keys'=>['j9CloseDesc','dateJ9Close','btnJ9Close']],
                'syncAtch'=> ['label'=>lang('sync_attach_title', $this->moduleID),'type'=>'fields','keys'=>['syncAtchDesc','btnSyncAttach']]],
            'fields'  => $fields];
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
        // crm
        $layout['fields']['enable_crm']= ['order'=>60, 'attr'=>['type'=>'selNoYes','checked'=>!empty($this->settings['enable_crm'])?true:false]];
        $layout['fields']['restrict']  = ['order'=>70, 'attr'=>['type'=>'selNoYes','checked'=>!empty($this->settings['restrict'])?true:false]];
        $settings = $this->settingsStructure();
        $layout['lists']['crm'] = $settings['crm']['fields'];
        $layout['accordion']['accSettings']['divs']['crm'] = ['order'=>80, 'ui'=>'none', 'label'=>lang('ctype_i'), 'type'=>'list', 'key'=>'crm'];
//      $layout['panel']['settings']['keys'] = array_merge($layout['panel']['settings']['keys'], ['enable_crm', 'restrict']);
        msgDebug("\nAfter cutomization adminHomeContact with layout = ".print_r($layout, true));
    }

    /**
     * Saves the users settings
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
}
