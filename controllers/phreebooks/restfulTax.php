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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-01-28
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
    private   $server    = 'https://www.phreesoft.com';
//  private   $skipCity  = true;
    public    $lang;
    private   $nexusMeta;
    private   $states;
    public    $settings;
    private   $statesDetail;
    private   $statesNoTax;

    function __construct()
    {
        $this->lang     = getLang($this->moduleID);
        $this->nexusMeta= getMetaCommon($this->metaPrefix);
        if (!isset($this->nexusMeta['states'])) { $this->nexusMeta = ['states'=>$this->nexusMeta]; } // patch for older versions
        $this->states   = [
            'AK','AL','AR','AZ','CA','CO','CT','DC','DE','FL','GA','HI','IA','ID','IL','IN','KS',
            'KY','LA','MA','MD','ME','MI','MN','MO','MS','MT','NC','ND','NE','NH','NJ','NM','NV',
            'NY','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VA','VT','WA','WI','WV','WY'];
        $this->statesDetail= ['AZ','CA','CO','GA','IL','MN','NC','OH','PA','TN','UT','VA','WA'];
        $this->statesNoTax = ['DE','MT','NH','OR'];
    }

    /**
     * Generates the nexus and sales tax settings for the PhreeSoft sales tax Service
     * @param type $layout
     */
    public function manager(&$layout=[])
    {
        $states = [];
        foreach ($this->states as $st) { $states[] = ['id'=>$st, 'text'=>$st]; }
        // pre-check the state where the business is based, if possible
        // add links to SoS sites where each state defines their nexus
        if (!$security = validateAccess('admin', 1)) { return; }
        $fields = [
            'nexusSt'   => ['order'=>30,'break'=>true,'label'=>lang('nexus_states'),'options'=>['multiple'=>'true'],'values'=>$states,'attr'=>['type'=>'select','name'=>'nexusSt[]', 'value'=>$this->nexusMeta['states']]],
            'btnSave'   => ['order'=>70,'attr'=>['type'=>'button','value'=>lang('save')], 'events'=>['onClick'=>"jqBiz('#frmNexus').submit();"]],
            'contactIDs'=> ['order'=> 1,'attr'=>['type'=>'hidden']]];
        $data = ['type'=>'divHTML',
            'divs'    => ['restTax'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'nexus'  => ['order'=>20,'type'=>'panel','key'=>'nexus','classes'=>['block33']]]]]]],
            'panels'  => [
                'nexus'=> ['label'=>lang('nexus'),'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form','key' =>'frmNexus'],
                    'descMkt'=> ['order'=>20,'type'=>'html','html'=>"<p>"."Enter any marketplace customers that collect sales tax on your behalf."."</p>"],
                    'mktplc' => ['order'=>30,'type'=>'datagrid','key' =>'dgContactIDs'],
                    'descNxs'=> ['order'=>50,'type'=>'html','html'=>"<br /><p>"."Select any states that you have a nexus to collect sales tax."."</p>"],
                    'body'   => ['order'=>60,'type'=>'fields','keys'=>['btnSave','nexusSt','contactIDs']],
                    'formEOF'=> ['order'=>90,'type'=>'html','html'=>"</form>"]]]],
            'datagrid'=> ['dgContactIDs'=>$this->dgMkplcs('dgContactIDs')],
            'forms'   => ['frmNexus'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/save"]]],
            'fields'  => $fields,
            'jsHead'  => ['noTaxCIDs'=>"var noTaxCIDs=".(!empty($this->nexusMeta['marketplaces'])?json_encode($this->nexusMeta['marketplaces']):'[]').";"],
            'jsReady' => ['init'=>"ajaxForm('frmNexus'); bizSelSet('nexusSt',".json_encode($this->nexusMeta['states']).");"]];
        $layout = array_replace_recursive($layout, $data);
        msgDebug("\nlayout is now: ".print_r($layout, true));
    }

    /**
     * Adds the marketplace grid HTML to get the list of contacts that collect sales tax on the behalf of this business, e.g. Amazon.
     */
    private function dgMkplcs($name)
    {

        $data = ['id' => $name, 'type'=>'edatagrid', 'title'=> 'Marketplace Customers',
            'attr'    => ['toolbar'=>"#{$name}Toolbar",'rownumbers'=>true, 'showFooter'=>true, 'pagination'=>false],
            'events'  => ['data'=> 'noTaxCIDs',
                'onClickRow'  => "function(rowIndex) { jqBiz('#$name').edatagrid('editRow', rowIndex); }",
                'onBeforeEdit'=> "function(rowIndex) { curIndex = rowIndex; }"],
            'source' => ['actions'=>['new'=>['order'=>10,'icon'=>'add','events'=>['onClick'=>"jqBiz('#$name').edatagrid('addRow');"]]]],
            'columns'=> [
                'action'=> ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index){ return {$name}Formatter(value,row,index); }"],
                    'actions'=> ['invVendTrash'=>['icon'=>'trash','order'=>20,'events'=>['onClick'=>"jqBiz('#$name').edatagrid('destroyRow');"]]]],
                'cTitle'=> ['order'=>0, 'attr'=>['hidden'=>'true']],
                'cID'   => ['order'=>10,'label'=>lang('short_name_v'), 'attr'=>['width'=>200,'resizable'=>true,'align'=>'center'],
                    'events' => ['formatter'=>"function(value, row) { return row.cTitle; }",'editor'=>dgEditContact($name, 'c')]],
                'text'  => ['order'=>20,'label'=>lang('primary_name'),'attr'=>['width'=>300,'resizable'=>true,'editor'=>'text']]]];
        return $data;
    }

    public function save()
    {
        $states = clean('nexusSt',    'array', 'post');
        $mkplcs = clean('contactIDs', 'json', 'post');
        $metaVal= dbMetaGet(0, $this->metaPrefix);
        msgDebug("\nEntering and saving restfulTax:save with stored meta = ".print_r($metaVal, true));
        $rID    = metaIdxClean($metaVal); // remove the indexes
        $newMeta = ['states'=>$states, 'marketplaces'=>$mkplcs];
        dbMetaSet($rID, $this->metaPrefix, $newMeta);
        msgAdd("Settings saved.", 'success');
    }

    /**
     * Fetches the sales tax rate from the PhreeSoft Server via RESTful API
     * @param array $layout - Structure
     */
    public function getTaxRate(&$layout=[])
    {
        global $io;
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>0]);
        if (!validateAccess('j9_mgr', 1) && !validateAccess('j10_mgr', 1) && !validateAccess('j12_mgr', 1)) { return; }
        $salesTax = 0;
        msgDebug("\nEntering getTaxRate with settings = ".print_r($this->settings, true));
        $props = [
            'total'      => clean('total',      'float',    'get'),
            'shipping'   => clean('shipping',   'float',    'get'),
            'address1'   => clean('address1',   'text',     'get'),
            'address2'   => clean('address2',   'text',     'get'),
            'city'       => clean('city',       'alpha_num','get'),
            'state'      => strtoupper(clean('state','alpha_num','get')),
            'country'    => clean('country',    'alpha_num','get'),
            'postal_code'=> clean('postal_code','chars',    'get')]; // Just USA for now, Canada is alphanum and has 6 characters, maybe by country
        $isTaxable = in_array($props['state'], $this->nexusMeta['states']) ? true : false;
        if (!$isTaxable) { return; }
        if (empty($props['postal_code'])) { return msgAdd("Missing or invalid postal code provided."); }
        $io->restHeaders = ['email'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_user'), 'pass'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_pass')];
        $result = $io->restRequest('get', $this->server, 'wp-json/phreesoft-api/v1/sales_tax', $props);
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
msgTrap();
        global $io;
        msgDebug("\nEntering calcTaxCollected with nexus states = ".print_r($this->nexusMeta['states'], true));
        // get customer ID's that are marketplaces as tax for these is withheld separately.
        $mktplaces = [];
        $period= clean('period', 'integer', 'post');
        $rows  = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "period=$period AND journal_id IN (12, 13)", 'post_date', ['id', 'journal_id', 'invoice_num', 'post_date', 'total_amount', 'sales_tax', 'freight', 'contact_id_b', 'address1_s', 'address2_s', 'city_s', 'state_s', 'postal_code_s', 'country_s']);
        $data  = [['post_date', 'invoice', 'shipping', 'tax', 'total', 'cust_id', 'exempt', 'address1', 'address2', 'city', 'state', 'zip_code', 'country']];
        foreach ($rows as $row) {
            $data[] = [
                $row['post_date'], $row['invoice_num'], $row['journal_id']==13?-$row['freight']:$row['freight'],
                $row['journal_id']==13?-$row['sales_tax']:$row['sales_tax'], $row['journal_id']==13?-$row['total_amount']:$row['total_amount'],
                $row['contact_id_b'], empty($row['sales_tax']) ? 1 : 0,
                $row['address1_s'], $row['address2_s'], $row['city_s'], $row['state_s'], $row['postal_code_s'], $row['country_s']];
        }   
        $io->restHeaders = ['email'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_user'), 'pass'=>getModuleCache('api', 'settings', 'phreesoft_api', 'api_pass')];
        $post  = ['bizID'=>BIZUNO_BIZID, 'nexus'=>$this->nexusMeta['states'], 'marketplaces'=>$mktplaces, 'invoices'=>$data];
        $result= $io->restRequest('post', $this->server, 'wp-json/phreesoft-api/v1/sales_report', $post);
        msgDebug("\nreceived back from PhreeSoft the following tax summary = ".print_r($result, true));
        if (is_array($result)) { return msgAdd("Unexpected response from PhreeSoft: ".print_r($result, true), 'info'); } // probably an error, expecting json
        $raw = json_decode($result, true);
//        $output = $this->generateFile($raw);
//        $io->download('data', implode("\n", $output), "Period_{$period}_Sales-".biz_date('Y-m-d').".csv");
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
