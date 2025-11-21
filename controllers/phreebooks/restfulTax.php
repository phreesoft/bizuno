<?php
/*
 * @name Bizuno ERP - PhreeSoft API Interface for RESTful API Tax Operations
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
 * @version    7.x Last Update: 2025-08-06
 * @filesource /controllers/phreebooks/restfulTax.php
 */

namespace bizuno;

class phreebooksRestfulTax
{
    public    $moduleID  = 'phreebooks';
    public    $pageID    = 'restfulTax';
    protected $secID     = 'admin';
    protected $domSuffix = 'Nexus';
    protected $metaPrefix= 'nexus';
    private   $server   = 'https://www.phreesoft.com';
    private   $skipCity = true;
    public    $lang;
    private   $nexusSt;
    private   $states;
    public    $settings;
    private   $statesDetail;
    private   $statesNoTax;

    function __construct()
    {
        $this->lang    = getLang($this->moduleID);
        $this->nexusSt = getMetaCommon($this->metaPrefix);
        $this->states  = [
            'AK','AL','AR','AZ','CA','CO','CT','DC','DE','FL','GA',
            'HI','IA','ID','IL','IN','KS','KY','LA','MA','MD',
            'ME','MI','MN','MO','MS','MT','NC','ND','NE','NH',
            'NJ','NM','NV','NY','OH','OK','OR','PA','RI','SC',
            'SD','TN','TX','UT','VA','VT','WA','WI','WV','WY'];
        $this->statesDetail= ['AZ','CA','CO','GA','IL','MN','NC','OH','PA','TN','UT','VA','WA'];
        $this->statesNoTax = ['DE','MT','NH','OR'];
    }

    /**
     * Generates the nexus and sales tax settings for the PhreeSoft sales tax Service
     * @param type $layout
     */
    public function manager(&$layout=[])
    {
        // pre-check the state where the business is based, if possible
        // add links to SoS sites where each state defines their nexus
        if (!$security = validateAccess('admin', 1)) { return; }
        $fields = [
            'nexusSt'=>['order'=>30,'break'=>true,'label'=>lang('nexus_states'),'options'=>['multiple'=>'true'],'values'=>$this->viewStates(),'attr'=>['type'=>'select','name'=>'nexusSt[]', 'value'=>$this->nexusSt]],
        ];
        $fields['btnSaveNexus'] = ['order'=>70,'attr'=>['type'=>'button','value'=>lang('save')],
            'events'=>['onClick'=>"jqBiz('#frmNexus').submit();"]];
        $data = ['type'=>'divHTML',
            'divs'   => ['restTax'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'nexus'=> ['order'=>20,'type'=>'panel','key'=>'nexus','classes'=>['block33']]]]]]],
            'panels' => [
                'nexus'=> ['label'=>'Nexus','type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form','key' =>'frmNexus'],
                    'desc'   => ['order'=>20,'type'=>'html','html'=>"<p>Pick your Nexus states and press Save:</p>"],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['btnSaveNexus','nexusSt']],
                    'formEOF'=> ['order'=>90,'type'=>'html','html'=>"</form>"]]]],
            'forms'  => ['frmNexus'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/save"]]],
            'fields' => $fields,
            'jsReady'=> ['init'=>"ajaxForm('frmNexus'); bizSelSet('nexusSt',".json_encode($this->nexusSt).");"]];
        $layout = array_replace_recursive($layout, $data);
        msgDebug("\nlayout is now: ".print_r($layout, true));
    }

    /**
     * Builds the view for the state to enable nexus
     */
    private function viewStates()
    {
        foreach ($this->states as $state) { $output[] = ['id'=>$state, 'text'=>$state]; }
        return $output;
    }
    public function save()
    {
        $states = clean('nexusSt', 'array', 'post');
        msgDebug("\nEntering and saving restfulTax:save with states = ".print_r($states, true));
        $metaVal= dbMetaGet(0, $this->metaPrefix);
        $rID    = metaIdxClean($metaVal); // remove the indexes
        dbMetaSet($rID, $this->metaPrefix, $states);
        msgAdd("Settings saved.", 'success');
    }

    /**
     * Fetches the sales tax rate from the PhreeSoft Server via RESTful API
     * @param array $layout - Structure
     */
    public function getTaxRate(&$layout=[])
    {
        global $portal;
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>0]);
        if (!validateAccess('j9_mgr', 2)) { return; }
        $salesTax = 0;
        msgDebug("\nEntering getTaxRate with settings = ".print_r($this->settings, true));
        $props = [
            'total'      => clean('total',      'float','get'),
            'shipping'   => clean('shipping',   'float','get'),
            'city'       => clean('city',       'alpha_num','get'),
            'state'      => strtoupper(clean('state','alpha_num','get')),
            'country'    => clean('country',    'alpha_num','get'),
            'postal_code'=> clean('postal_code','chars','get')]; // Just USA for now, Canada is alphanum and has 6 characters, maybe by country
        $isTaxable = in_array($props['state'], $this->nexusSt) ? true : false;
        if (!$isTaxable) { return; }
        if (empty($props['postal_code'])) { return msgAdd("Missing or invalid postal code provided."); }
        
        $portal->restHeaders = ['email'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_user'), 'pass'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_pass')];
        $result = $portal->restRequest('get', $this->server, 'wp-json/phreesoft-api/v1/sales_tax', $props);
        if (!empty($result['sales_tax'])) { $salesTax = $result['sales_tax']; }
        msgDebug("\nExiting getTaxRate with layout = ".print_r($layout, true));
        $layout['content'] = $salesTax;
    }
    
    /**
     * Calculates the sales by state for sales tax reporting purposes
     * @return will return errors/test results if action=test otherwise downloads csv file
     */
    public function calcTaxCollected()
    {
        global $io;
        msgDebug("\nEntering calcTaxCollected with nexus states = ".print_r($this->nexusSt, true));
        // get customer ID's that are marketplaces as tax for these is withheld separately.
        $mktplaces = [];
        
        
        $data  = [];
        $period= clean('period', 'integer',  'post');
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "period=$period AND journal_id IN (12, 13)", 'state_s', ['id', 'journal_id', 'invoice_num', 'total_amount', 'sales_tax', 'contact_id_b', 'city_s', 'state_s', 'postal_code_s', 'country_s']);
        foreach ($rows as $row) {
            if (in_array($row['contact_id_b'], $mktplaces)) { continue; }
            $state = 'USA'<>strtoupper($row['country_s']) ? '_INT' : strtoupper($row['state_s']);
            $nexus = in_array($row['state_s'], $this->nexusSt)    ? 1 : '';
            $exempt= in_array($row['state_s'], $this->statesNoTax)? 1 : '';
            $info  = in_array($state, $this->statesDetail) ? $this->getCounty($row['postal_code_s']) : ['county'=>'_all', 'rate_state'=>0, 'rate_county'=>0, 'rate_city'=>0];
            $cnty  = !empty($row['sales_tax']) ? $info['county'] : '_exempt';
            $city  = !empty($row['sales_tax']) && !$this->skipCity ? strtolower($row['city_s']) : '_all';
            if (!isset($data[$state][$cnty][$city])) { 
                $data[$state][$cnty][$city] = ['nexus'=>$nexus, 'exempt'=>$exempt, 'cnt'=>0, 'total_sales'=>0, 'total_tax'=>0, 'calc_tax'=>0, 'rate_state'=>$info['rate_state'], 'rate_county'=>$info['rate_county'], 'rate_city'=>$info['rate_city']];
            }
            $data[$state][$cnty][$city]['cnt']++;
            $data[$state][$cnty][$city]['total_sales']+= $row['journal_id']==13 ? -$row['total_amount']: $row['total_amount'];
            $data[$state][$cnty][$city]['total_tax']  += $row['journal_id']==13 ? -$row['sales_tax']   : $row['sales_tax'];
        }
        msgDebug("\nReady to generate output with data = ".print_r($data, true));
        $output = $this->generateFile($data);
        $io->download('data', implode("\n", $output), "Period_{$period}_Sales-".biz_date('Y-m-d').".csv");
    }

    private function getCounty($zip='')
    {
        msgDebug("\nEntering getCounty with zip = $zip");
        if (empty($zip)) { return 'unknown'; }
        $zipcode = substr(trim($zip), 0, 5); // make sure the zip doesn't have the +4
        $results = dbGetMulti(BIZUNO_DB_PREFIX.'sales_tax_map', "zipcode='$zipcode'");
        switch (sizeof($results)) {
            case 0: return []; // not found, this is bad
            case 1: break; // best case, only one county for this zip, return it
            default: $results[0]['county'] .= '*'; // multiple county possible, need to get geolocation, but for now return the first with asterisk
        }
        msgDebug("\nLeaving getCounty with zip = $zip and county result = {$results[0]['county']}");
        return $results[0];
    }

    private function generateFile($data=[])
    {
        $output  = [];
        $output[]= implode(',', ['State', 'County', 'City', 'Nexus', 'Exempt', 'Num Orders', 'Total Sales', 'Total Tax', 'Calculated Tax', 'State Rate', 'County Rate', 'City Rate']);
        ksort($data);
        foreach ($data as $state => $counties) {
            ksort($counties);
            foreach ($counties as $county => $cities) {
                ksort($cities);
                foreach ($cities as $city => $values) {
                    // calculate some amounts
                    if (!empty($values['total_tax']) && !empty($values['rate_state'])) {
                        $values['calc_tax'] = round($values['total_sales'] * ($values['rate_state'] + $values['rate_county'] + $values['rate_city']), 2);
                    }
                    $array = array_merge([$state, $county, $city], array_values($values));
                    msgDebug("\nReady to implode array = ".print_r($array, true));
                    $output[] = implode(',', $array); }
            }
        }
        return $output;
    }
}
