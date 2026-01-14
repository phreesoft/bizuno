<?php
/*
 * Module Bizuno admin functions
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
 * @version    7.x Last Update: 2026-01-14
 * @filesource /controllers/bizuno/admin.php
 */

namespace bizuno;

class bizunoAdmin
{
    public  $moduleID    = 'bizuno';
    private $mailDefaults= ['smtp_host'=>'mail.mydomain.com', 'smtp_port'=>587, 'smtp_user'=>'', 'smtp_pass'=>''];
    public $lang;
    public $settings;
    public $structure;
    public $dirlist;
    public $phreeformProcessing;
    public $phreeformFormatting;
    public $phreeformSeparators;

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure= [
            'attachPath'=> ['docs'=>'data/docs/uploads/', 'fixedAssets'=>'data/fixedAssets/uploads/', 'maint'=>'data/maint/uploads/'],
            'usersAttachPath'=> "data/$this->moduleID/users/uploads",
            'menuBar' => ['child'=>[
                'quality'=> ['order'=>70,'label'=>lang('quality'),'icon'=>'extQuality','group'=>'quality','child'=>[
                    'mgr_docs' => ['order'=>60,'label'=>sprintf(lang('tbd_manager'), lang('document')),   'icon'=>'fileMgr','route'=>'office/docs/manager'],
                    'mgr_maint'=> ['order'=>90,'label'=>sprintf(lang('tbd_manager'), lang('maintenance')),'icon'=>'maint',  'route'=>"administrate/maint/manager"]]],
                'tools'  => ['order'=>90,'label'=>('tools'),  'icon'=>'tools','group'=>'tool','child'=>[
                    'imgmgr'   => ['order'=>75,'label'=>sprintf(lang('tbd_manager'), lang('image')),      'icon'=>'mimeImg','route'=>"bizuno/image/manager&dom=page"], // was jsonAction
//                  'drillDown'=> ['order'=>90,'label'=>('gl_drill_down'),                                'icon'=>'search', 'route'=>"$this->moduleID/drillDown/manager"]],
                    'impexp'   => ['order'=>85,'label'=>('bizuno_impexp'),                                'icon'=>'refresh','route'=>"api/import/impExpMain"]]]]],
            'hooks'   => [
                'administrate'=> ['tools'=>['fyCloseHome'=>['order'=>50,'page'=>'tools'],'fyClose'=>['order'=>50,'page'=>'tools']]]]];
        $this->dirlist = ['backups','data','images','temp'];
        $this->phreeformProcessing = [
            'json'      => ['text'=>$this->lang['pf_proc_json'],    'group'=>lang('tools')],
            'jsonFld'   => ['text'=>$this->lang['pf_proc_json_fld'],'group'=>lang('tools')],
            'today'     => ['text'=>lang('today'),                  'group'=>lang('date')],
            'faType'    => ['text'=>lang('asset_type'),    'group'=>$this->lang['title'],'module'=>$this->moduleID,'function'=>'administrateView'],
            'faCond'    => ['text'=>lang('purch_cond'),    'group'=>$this->lang['title'],'module'=>$this->moduleID,'function'=>'administrateView']];
        setProcessingDefaults($this->phreeformProcessing, $this->moduleID, $this->lang['title']);
        $this->phreeformFormatting = [
            'uc'       => ['text'=>$this->lang['pf_proc_uc'],      'group'=>lang('text')],
            'lc'       => ['text'=>$this->lang['pf_proc_lc'],      'group'=>lang('text')],
            'yesBno'   => ['text'=>$this->lang['pf_proc_yesbno'],  'group'=>lang('text')],
            'blank'    => ['text'=>$this->lang['pf_proc_blank'],   'group'=>lang('text')],
            'printed'  => ['text'=>$this->lang['pf_proc_printed'], 'group'=>lang('text')],
            'stripTags'=> ['text'=>lang('strip_tags'),             'group'=>lang('text')],
            'neg'      => ['text'=>$this->lang['pf_proc_neg'],     'group'=>lang('numeric')],
            'n2wrd'    => ['text'=>$this->lang['pf_proc_n2wrd'],   'group'=>lang('numeric')],
            'null0'    => ['text'=>$this->lang['pf_proc_null0'],   'group'=>lang('numeric')],
            'rnd0d'    => ['text'=>$this->lang['pf_proc_rnd0d'],   'group'=>lang('numeric')],
            'rnd2d'    => ['text'=>$this->lang['pf_proc_rnd2d'],   'group'=>lang('numeric')],
            'currency' => ['text'=>lang('currency'),               'group'=>lang('numeric')],
            'curLong'  => ['text'=>lang('currency_long'),          'group'=>lang('numeric')],
            'curNull0' => ['text'=>$this->lang['pf_cur_null_zero'],'group'=>lang('numeric')],
            'percent'  => ['text'=>lang('percent'),                'group'=>lang('numeric')],
            'precise'  => ['text'=>$this->lang['pf_proc_precise'], 'group'=>lang('numeric')],
            'date'     => ['text'=>$this->lang['pf_proc_date'],    'group'=>lang('date')],
            'dateLong' => ['text'=>$this->lang['pf_proc_datelong'],'group'=>lang('date')],
            'storeID'  => ['text'=>lang('short_name_b'),           'group'=>lang('ctype_b')],
            'rmaStatus'=> ['text'=>$this->lang['pf_rma_status'],   'group'=>lang('returns')]];
        setProcessingDefaults($this->phreeformFormatting, $this->moduleID, $this->lang['title']);
        $this->phreeformSeparators = [
            'sp'     => ['text'=>$this->lang['pf_sep_space1'], 'module'=>$this->moduleID,'function'=>'viewSeparator'],
            '2sp'    => ['text'=>$this->lang['pf_sep_space2'], 'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'comma'  => ['text'=>$this->lang['pf_sep_comma'],  'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'com-sp' => ['text'=>$this->lang['pf_sep_commasp'],'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'dash-sp'=> ['text'=>$this->lang['pf_sep_dashsp'], 'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'sep-sp' => ['text'=>$this->lang['pf_sep_sepsp'],  'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'nl'     => ['text'=>$this->lang['pf_sep_newline'],'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'semi-sp'=> ['text'=>$this->lang['pf_sep_semisp'], 'module'=>$this->moduleID,'function'=>'viewSeparator'],
            'del-nl' => ['text'=>$this->lang['pf_sep_delnl'],  'module'=>$this->moduleID,'function'=>'viewSeparator']];
//        $this->notes = [$this->lang['note_bizuno_install_1']];
    }

    /**
     * User configurable settings structure
     * @return array structure for settings forms
     */
    private function settingsStructure()
    {
        foreach ([0,1,2,3,4] as $value) { $selPrec[] = ['id'=>$value, 'text'=>$value]; }
        $selSep= [['id'=>'.','text'=>'Dot (.)'],['id'=>',','text'=>'Comma (,)'],['id'=>' ','text'=>'Space ( )'],['id'=>"'",'text'=>"Apostrophe (')"]];
        $zones = viewTimeZoneSel();
        $mail  = new bizunoMailer();
        $selDate= [
            ['id'=>'m/d/Y','text'=>'mm/dd/yyyy'],['id'=>'d/m/Y','text'=>'dd/mm/yyyy'],
            ['id'=>'Y/m/d','text'=>'yyyy/mm/dd'],['id'=>'d.m.Y','text'=>'dd.mm.yyyy'],
            ['id'=>'Y.m.d','text'=>'yyyy.mm.dd'],['id'=>'dmY',  'text'=>'ddmmyyyy'],
            ['id'=>'Ymd',  'text'=>'yyyymmdd'],  ['id'=>'Y-m-d','text'=>'yyyy-mm-dd']];
        $data  = [
            'general' => ['order'=>10,'label'=>lang('general'),'fields'=>[
                'password_min'    => ['options'=>['min'=> 8],           'attr'=>['type'=>'integer','value'=> 8]],
                'max_rows'        => ['options'=>['min'=>10,'max'=>100],'attr'=>['type'=>'integer','value'=>20]],
                'session_max'     => ['options'=>['min'=> 0,'max'=>300],'attr'=>['type'=>'integer','value'=> 0]], // min zero for auto refresh
                'hide_filters'    => ['attr'=>['type'=>'selNoYes']]]],
            'company' => ['order'=>20,'label'=>lang('company'),'fields'=>[
                'id'              => ['label'=>lang('contacts',     'short_name', 'b'),'attr'=>['value'=>getUserCache('profile', 'biz_title')]],
                'primary_name'    => ['label'=>lang('primary_name'),   'attr'=>['value'=>getUserCache('profile', 'biz_title')]],
                'contact'         => ['label'=>lang('contact')],
                'email'           => ['label'=>lang('email_gen')],
                'contact_ap'      => ['label'=>lang('contact', 'p')],
                'email_ap'        => ['label'=>lang('email_ap')],
                'contact_ar'      => ['label'=>lang('contact', 'r')],
                'email_ar'        => ['label'=>lang('email_ar')],
                'store_mgr'       => ['label'=>lang('contact', 'd')],
                'email_mgr'       => ['label'=>lang('email_mgr')],
                'address1'        => ['label'=>lang('address1')],
                'address2'        => ['label'=>lang('address2')],
                'city'            => ['label'=>lang('city')],
                'state'           => ['label'=>lang('state')],
                'postal_code'     => ['label'=>lang('postal_code')],
                'country'         => ['label'=>lang('country'),'attr'=>['type'=>'country']],
                'telephone1'      => ['label'=>lang('telephone')],
                'telephone2'      => ['label'=>lang('telephone2')],
                'telephone3'      => ['label'=>lang('telephone3')],
                'telephone4'      => ['label'=>lang('telephone4')],
                'website'         => ['label'=>lang('website')],
                'gov_id_number'   => ['label'=>lang('contacts','gov_id_number')],
                'logo'            => ['attr'=>['type'=>'hidden']]]],
            'mail' => ['order'=>40,'label'=>lang('mail'),'fields'=>$mail->struc],
            'locale' => ['order'=>70,'label'=>lang('locale'),'fields'=>[
                'timezone'        => ['values'=>$zones,  'options'=>['width'=>400],'attr'=>['type'=>'select','value'=>'America/New_York']],
                'number_precision'=> ['values'=>$selPrec,'attr'=>['type'=>'select','value'=>'2']],
                'number_decimal'  => ['values'=>$selSep, 'attr'=>['type'=>'select','value'=>'.']],
                'number_thousand' => ['values'=>$selSep, 'attr'=>['type'=>'select','value'=>',']],
                'number_prefix'   => ['attr'=>['value'=>'']],
                'number_suffix'   => ['attr'=>['value'=>'']],
                'number_neg_pfx'  => ['attr'=>['value'=>'-']],
                'number_neg_sfx'  => ['attr'=>['value'=>'']],
                'date_short'      => ['values'=>$selDate,'attr'=>['type'=>'select','value'=>'m/d/Y']]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    public function initialize()
    {
        // Rebuild some option values
        $metaFreqs= dbMetaGet('%', 'options_frequencies');
        $idxFreq  = metaIdxClean($metaFreqs); // remove the indexes
        $freqs    = ['d'=>lang('daily'), 'w'=>lang('weekly'), 'b'=>lang('bi-weekly'), 'h'=>lang('semi-monthly'), 'm'=>lang('monthly'),
            'q'=>lang('quarterly'), 'y'=>lang('yearly'), '3'=>lang('3-yearly'), 'z'=>lang('one_time')];
        dbMetaSet($idxFreq, 'options_frequencies', $freqs);
        
        $metaLT   = dbMetaGet('%', 'options_lead_times');
        $idxLT    = metaIdxClean($metaLT); // remove the indexes
        $leadTimes= ['1d'=>$this->lang['lead01'], '2d'=>$this->lang['lead02'], '1w'=>$this->lang['lead07'], '2w'=>$this->lang['lead14'], '1m'=>$this->lang['lead30']];
        dbMetaSet($idxLT, 'options_lead_times', $leadTimes);
        
        $metaFA   = dbMetaGet('%', 'options_fxdast_types');
        $idxFA    = metaIdxClean($metaFA); // remove the indexes
        $faTypes  = ['bd'=>$this->lang['fa_type_bd'], 'pc'=>$this->lang['fa_type_pc'], 'fn'=>$this->lang['fa_type_fn'], 'ld'=>$this->lang['fa_type_ld'],
            'ma'=>$this->lang['fa_type_ma'], 'sw'=>$this->lang['fa_type_sw'], 'te'=>$this->lang['fa_type_te'], 'vh'=>$this->lang['fa_type_vh']];
        dbMetaSet($idxFA, 'options_fxdast_types', $faTypes);
        // Put them in the cache for runtime access
        setModuleCache('bizuno', 'options', 'frequencies', $freqs);
        setModuleCache('bizuno', 'options', 'lead_times',  $leadTimes);
        setModuleCache('bizuno', 'options', 'faTypes',     $faTypes);
        return true;
    }

    /**
     * This method pulls common data and uploads to browser to speed up page updates. It should be extended by every module that wants to upload static data for a browser session
     */
    public function loadBrowserSession(&$layout=[])
    {
        // load the default currency, locale
        $locale   = getModuleCache('bizuno', 'settings', 'locale');
        if (empty($locale)) { $locale = ['date_short'=>'Y-m-d', 'timezone'=>'America/Chicago']; } // set defaults
        $dateDelim= substr(preg_replace("/[a-zA-Z]/", "", $locale['date_short']), 0, 1);
        $locales  = localeLoadDB(); // load countries
        msgDebug("\nLoaded countries = ".print_r($locales, true));
        $countries= [];
        $defISO   = $defTitle = getModuleCache('bizuno', 'settings', 'company', 'country');
        foreach ($locales['Locale'] as $iso3 => $value) {
            $countries[] = ['iso3'=>$iso3, 'iso2'=>$value['ISO2'], 'title'=>$value['Title']];
            if ($defISO == $iso3) { $defTitle = $value['Title']; }
        }
        $regions = [];
        foreach ($locales['Locale'] as $iso3 => $value) {
            if (empty($value['Regions'])) { continue; }
            foreach ($value['Regions'] as $state => $region) { $regions[$iso3][] = ['code'=>$state, 'title'=>$region['Title']]; }
        }
        $ISOCurrency = getDefaultCurrency();
        $data = [
            'version'   => MODULE_BIZUNO_VERSION,
            'calendar'  => ['format'=>$locale['date_short'], 'delimiter'=>$dateDelim],
            'country'   => ['iso'=>$defISO,'title'=>$defTitle],
            'currency'  => ['defaultCur'=>$ISOCurrency, 'currencies'=>getModuleCache('phreebooks', 'currency', 'iso')],
            'language'  => substr(getUserCache('profile', 'language', false, 'en_US'), 0, 2),
            'timezone'  => $locale['timezone'],
            'locale'    => [
                'precision'=> isset($locale['number_precision'])? $locale['number_precision']: '2',
                'decimal'  => isset($locale['number_decimal'])  ? $locale['number_decimal']  : '.',
                'thousand' => isset($locale['number_thousand']) ? $locale['number_thousand'] : ',',
                'prefix'   => isset($locale['number_prefix'])   ? $locale['number_prefix']   : '',
                'suffix'   => isset($locale['number_suffix'])   ? $locale['number_suffix']   : '',
                'neg_pfx'  => isset($locale['number_neg_pfx'])  ? $locale['number_neg_pfx']  : '-',
                'neg_sfx'  => isset($locale['number_neg_sfx'])  ? $locale['number_neg_sfx']  : ''],
            'dictionary'=> $this->getBrowserLang(),
            'countries' => ['total'=>sizeof($countries), 'rows'=>$countries],
            'regions'   => $regions];
        $layout = array_replace_recursive($layout, ['content'=>$data]);
    }

    private function getBrowserLang()
    {
        return ['ACCOUNT'=>lang('account'),
            'CITY'       =>lang('city'),
            'CLOSE'      =>lang('close'),
            'CONTACT_ID' =>lang('short_name'),
            'EDIT'       =>lang('edit'),
            'FINISHED'   =>lang('finished'),
            'FROM'       =>lang('from'),
            'INFORMATION'=>lang('information'),
            'MESSAGE'    =>lang('message'),
            'NAME'       =>lang('primary_name'),
            'PLEASE_WAIT'=>lang('please_wait'),
            'PROPERTIES' =>lang('properties'),
            'SELECT'     =>lang('select'),
            'SETTINGS'   =>lang('settings'),
            'SHIPPING_ESTIMATOR'=>lang('shipping_estimator'),
            'STATE'      =>lang('state'),
            'TITLE'      =>lang('title'),
            'TO'         =>lang('to'),
            'TOTAL'      =>lang('total'),
            'TRASH'      =>lang('trash'),
            'TYPE'       =>lang('type'),
            'VALUE'      =>lang('value'),
            'VIEW'       =>lang('view')];
    }

    /**
     * Structure for Settings main page for module Bizuno
     * @param array $layout - structure coming in
     * @return array - modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        msgDebug("\nEditing with settings = ".print_r(getModuleCache('bizuno', 'settings'), true));
        $output = $this->getAdminFields();
        $imgSrc = getModuleCache('bizuno', 'settings', 'company', 'logo');
        $imgDir = dirname($imgSrc) == '/' ? '/' : dirname($imgSrc).'/';
        if ($imgDir=='/') { $imgDir = getUserCache('imgMgr', 'lastPath', false , '').'/'; } // pull last folder from cache
        $stmt   = dbGetResult("SHOW TABLE STATUS");
        $stats  = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $data   = [
            'tabs'    => ['tabAdmin'=>['divs'=>[
                'tabMaint'=> ['order'=>20,'label'=>lang('maintenance'),  'type'=>'html','html'=>'',       'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=administrate/adminMaint/manager'"]],
                'sched'   => ['order'=>30,'label'=>$this->lang['fa_schedules'],'type'=>'html', 'html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=administrate/fixedAssets/adminSchedLoad'"]],
                'tabs'    => ['order'=>40,'label'=>lang('extra_tabs'),   'type'=>'html','html'=>'',       'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=administrate/tabs/manager'"]],
                'fields'  => ['order'=>50,'label'=>lang('extra_fields'), 'type'=>'html','html'=>'',       'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=administrate/fields/manager'"]],
                'tools'   => ['order'=>90,'label'=>lang('tools'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'recalc' => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'recalc'],
                    'fixTbl' => ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'fixTbl'],
                    'stsSet' => ['order'=>40,'type'=>'panel','classes'=>['block66'],'key'=>'stsSet']]],
                'stats'   => ['order'=>90,'label'=>lang('statistics'),'styles'=>['width'=>'700px;','height'=>'250px'],'type'=>'datagrid','key'=>'bizStats']]]],
            'panels'  => [
                'stsSet' => ['title'=>$this->lang['admin_status_update'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmStatus'],
                    'body'   => ['order'=>20,'type'=>'fields','keys'=>$output['keys']['keys0']],
                    'formEOF'=> ['order'=>30,'type'=>'html',  'html'=>"</form>"]]],
                'recalc' => ['label'=>$this->lang['fa_recalc_title'],   'type'=>'fields','keys'=>$output['keys']['keys1']],
                'fixTbl' => ['label'=>$this->lang['admin_fix_tables'],  'type'=>'fields','keys'=>$output['keys']['keys2']]],
            'datagrid'=> ['bizStats'=>$this->dgStats('bizStats')],
            'forms'   => ['frmStatus'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=administrate/tools/statusSave"]]],
            'fields'  => $output['fields'],
            'jsHead'  => [$this->moduleID=>"var bizStatsData = ".json_encode($stats).";"],
            'jsBody'  => ['company_logo'=>"imgManagerInit('company_logo', '$imgSrc', '$imgDir', ".json_encode(['style'=>"max-height:200px;max-width:200px;"]).");"],
            'jsReady' => [$this->moduleID=>"ajaxForm('frmStatus'); jqBiz('#bizStats').datagrid({data:bizStatsData}).datagrid('clientPaging');"]];
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
    }

    private function getAdminFields()
    {
        $status = [];
        $result = getMetaCommon('bizuno_refs');
        foreach ($result as $key => $value) {
            if       (isset($this->lang[$key]))     { $label = $this->lang[$key]; }
            elseif (strpos($key, 'next_ref_j')===0) { $label = sprintf(lang('next_ref'), lang('journal_id_'.substr($key, 10))); }
            else                                    { $label = sprintf(lang('next_ref'), lang($key)); }
            $status[$key]['position']= 'after';
            $status[$key]['label']   = $label;
            $status[$key]['attr']['value'] = $value;
        }
        ksort($status);
        $output = [
            'keys'  =>[
                'keys0' => ['btnStatus'],
                'keys1' => ['recalcDesc','btnRecalc'],
                'keys2' => ['descFixTables','fix_tbl_btn']],
            'fields'=>[
                'recalcDesc'    => ['order'=>10,'html'=>$this->lang['fa_recalc_desc'],'attr'=>['type'=>'raw']],
                'btnRecalc'     => ['order'=>80,'attr'=>['type'=>'button','value'=>lang('start')],'events'=>['onClick'=>"jsonAction('administrate/fixedAssets/depValueBulk');"]],
                'btnStatus'     => ['order'=>99,'icon'=>'save','label'=>'save','events'=>['onClick'=>"jqBiz('#frmStatus').submit();"]],
                'descFixTables' => ['order'=>10,'html'=>$this->lang['desc_update_tables'],'attr'=>['type'=>'raw']],
                'fix_tbl_btn'   => ['order'=>20,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('administrate/tools/repairTables');"],'attr'=>['type'=>'button','value'=>lang('go')]]]];
        foreach ($status as $key => $settings) {
            if ($key == 'id') { continue; }
            $output['fields']["stat_$key"] = $settings;
            $output['keys']['keys0'][] = "stat_$key";
        }
        return $output;
    }

    /**
     * Special operations to save settings page beyond core settings
     * Check for company name change and update portal
     */
    public function adminSave()
    {
        $newTitle = clean('company_primary_name', 'text', 'post');
        $timezone = clean('locale_timezone', 'text', 'post');
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgStats($name='bizStats')
    {
        return ['id'=>$name, 'columns'=> [
            'Name'          => ['order'=>10,'label'=>lang('table'),              'attr'=>['width'=>200]],
            'Engine'        => ['order'=>20,'label'=>$this->lang['db_engine'],   'attr'=>['width'=>100]],
            'Rows'          => ['order'=>30,'label'=>$this->lang['db_rows'],     'attr'=>['width'=>100]],
            'Collation'     => ['order'=>40,'label'=>$this->lang['db_collation'],'attr'=>['width'=>200]],
            'Data_length'   => ['order'=>50,'label'=>$this->lang['db_data_size'],'attr'=>['width'=>100]],
            'Index_length'  => ['order'=>60,'label'=>$this->lang['db_idx_size'], 'attr'=>['width'=>100]],
            'Auto_increment'=> ['order'=>70,'label'=>$this->lang['db_next_id'],  'attr'=>['width'=>100]]]];
    }
}
