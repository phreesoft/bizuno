<?php
/*
 * @name Bizuno ERP - Self-contained sales tax calculator + reporter
 *       (Was a thin proxy to PhreeSoft's REST API; rewritten 2026-04-28 to
 *        call Geocodio + Zip-Tax directly. API keys live under
 *        api.settings.sales_tax_api.{geocodio_key, ziptax_key} and are
 *        configured via the API admin settings page.)
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
 * @version    7.x Last Update: 2026-04-28
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
    private   $geocodeURL= 'https://api.geocod.io/v1.9/geocode';
    private   $zipTaxURL = 'https://api.zip-tax.com/request/v60';
    private   $skipCity  = true;
    private   $nexusMeta;
    private   $states;
    public    $settings;
    private   $statesDetail;
    private   $statesNoTax;
    private   $stateTaxShip;

    function __construct()
    {
        $this->nexusMeta= getMetaCommon($this->metaPrefix);
        if (!isset($this->nexusMeta['states'])) { $this->nexusMeta = ['states'=>$this->nexusMeta]; } // patch for older versions
        msgDebug("\nRead nexus meta from common table = ".print_r($this->nexusMeta, true));
        $this->states   = [
            'AK','AL','AR','AZ','CA','CO','CT','DC','DE','FL','GA','HI','IA','ID','IL','IN','KS',
            'KY','LA','MA','MD','ME','MI','MN','MO','MS','MT','NC','ND','NE','NH','NJ','NM','NV',
            'NY','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VA','VT','WA','WI','WV','WY'];
        $this->statesDetail = ['AZ','CA','CO','GA','IL','MN','NC','OH','PA','TN','UT','VA','WA'];
        $this->statesNoTax  = ['DE','MT','NH','OR'];
        $this->stateTaxShip = ['AR','CT','GA','IL','KS','KY','MI','MS','NE','NJ','NM','NY',
            'NC','ND','OH','OK','PA','RI','SC','SD','TN','TX','UT','VT','WA','WV','WI'];
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
     * Calculates the sales tax for a shipping address using the configured
     * Zip-Tax API (and optionally Geocodio for ZIP+4 enhancement). Results
     * are cached per (zip_key, state) in bizuno_tax_rate_cache and expire at
     * the next quarter boundary. Pure GET endpoint that returns the dollar
     * tax amount as a raw scalar (the totals widget reads it via $.ajax).
     * @param array $layout - Structure
     */
    public function getTaxRate(&$layout=[])
    {
        $layout = array_replace_recursive($layout, ['type'=>'raw','content'=>0]);
        if (!validateAccess('j9_mgr', 1) && !validateAccess('j10_mgr', 1) && !validateAccess('j12_mgr', 1)) { return; }
        $args = [
            'total'      => clean('total',      'float',    'get'),
            'shipping'   => clean('shipping',   'float',    'get'),
            'address1'   => clean('address1',   'text',     'get'),
            'address2'   => clean('address2',   'text',     'get'),
            'city'       => clean('city',       'alpha_num','get'),
            'state'      => strtoupper(clean('state','alpha_num','get')),
            'country'    => strtoupper(clean('country','alpha_num','get')) ?: 'USA',
            'zipCode'    => clean('postal_code','chars',    'get')];
        msgDebug("\nEntering getTaxRate with args = ".print_r($args, true));
        if (!in_array($args['state'], $this->nexusMeta['states'])) { return; } // outside nexus, no tax owed
        if (in_array($args['state'], $this->statesNoTax))          { return; } // state has no sales tax at all
        if (empty($args['zipCode']))                                { return msgAdd("Missing or invalid postal code provided."); }
        $rate    = $this->lookupTaxRate($args);
        if ($rate === null) { return; } // lookup error already messaged
        $taxShip = in_array($args['state'], $this->stateTaxShip);
        $taxable = $taxShip ? floatval($args['total']) : (floatval($args['total']) - floatval($args['shipping']));
        $tax     = round($taxable * $rate, 2);
        msgDebug("\nExiting getTaxRate with rate=$rate, taxable=$taxable, tax=$tax");
        $layout['content'] = $tax;
    }

    /**
     * Combined-rate lookup with quarterly cache. Returns a decimal rate
     * (e.g. 0.08125) or null on hard error. Geocodio is used to resolve a
     * 5-digit ZIP up to ZIP+4 when the API key is present — this gives
     * cache hits at finer geographic resolution. When no Geocodio key is
     * set, we cache by 5-digit ZIP and accept the coarser hit rate.
     */
    private function lookupTaxRate($args)
    {
        global $io;
        $this->ensureCacheTable();
        $zipClean    = preg_replace('/[^0-9]/', '', $args['zipCode']);
        $zipLen      = strlen($zipClean);
        $zip5        = $zipLen >= 5 ? substr($zipClean, 0, 5) : '';
        if ($zip5 === '') { return msgAdd("Invalid ZIP: {$args['zipCode']}"); }
        $zipPlus4    = null;
        if ($zipLen === 9)      { $zipPlus4 = $zipClean; }
        elseif ($zipLen === 5 && $args['country'] === 'USA' && $args['state'] && $args['city'] && $args['address1']) {
            $zipPlus4 = $this->resolveZipPlus4($zip5, $args); // null when key is missing or no match
        }
        $cacheKey    = $zipPlus4 ?: $zip5;
        $today       = biz_date('Y-m-d');
        $cached      = dbGetRow(BIZUNO_DB_PREFIX.'tax_rate_cache', "zip_key='$cacheKey' AND state='{$args['state']}'");
        if ($cached && $cached['expires_at'] <= $today) {
            dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."tax_rate_cache WHERE id={$cached['id']}");
            $cached = [];
        }
        if ($cached) {
            msgDebug("\ntax_rate_cache hit: zip_key=$cacheKey state={$args['state']} rate={$cached['combined_rate']}");
            return (float)$cached['combined_rate'];
        }
        $apiKey = (string)getModuleCache('api', 'settings', 'sales_tax_api', 'ziptax_key');
        if ($apiKey === '') {
            msgAdd("Zip-Tax API key not configured. Set it under API Settings → Sales Tax APIs.");
            return null;
        }
        $fullAddr = trim("{$args['address1']} {$args['address2']}, {$args['city']}, {$args['state']} $zip5");
        $opts = ['headers' => ['X-API-KEY' => $apiKey, 'Content-Type' => 'application/json']];
        $body = ['format' => 'json', 'countryCode' => $args['country'], 'address' => $fullAddr];
        msgDebug("\nzip-tax request: addr=$fullAddr");
        $resp = json_decode($io->cURL($this->zipTaxURL, $body, 'get', $opts), true);
        msgDebug("\nzip-tax response = ".print_r($resp, true));
        $rate = 0.0;
        if (isset($resp['taxSummaries']) && is_array($resp['taxSummaries'])) {
            foreach ($resp['taxSummaries'] as $summary) {
                if (($summary['taxType'] ?? '') === 'SALES_TAX') {
                    $rate = (float)($summary['displayRates'][0]['rate'] ?? 0.0);
                    break;
                }
            }
        }
        $jurState = $jurCounty = $jurCity = '';
        if (isset($resp['baseRates']) && is_array($resp['baseRates'])) {
            foreach ($resp['baseRates'] as $jur) {
                $type = $jur['jurType'] ?? '';
                if ($type === 'US_STATE_SALES_TAX')  { $jurState  = $jur['jurName'] ?? ''; }
                if ($type === 'US_COUNTY_SALES_TAX') { $jurCounty = $jur['jurName'] ?? ''; }
                if ($type === 'US_CITY_SALES_TAX')   { $jurCity   = $jur['jurName'] ?? ''; }
            }
        }
        dbWrite(BIZUNO_DB_PREFIX.'tax_rate_cache', [
            'zip_key'      => $cacheKey,
            'state'        => $args['state'],
            'combined_rate'=> $rate,
            'state_name'   => $jurState,
            'county'       => $jurCounty,
            'city'         => $jurCity,
            'fetched_at'   => $today,
            'expires_at'   => $this->getNextQuarterStart()]);
        return $rate;
    }

    /**
     * Geocodio ZIP+4 lookup. Returns the 9-digit ZIP (5 + 4 concatenated,
     * digits only) or null when the API key isn't configured, the call
     * fails, or no plus-4 was returned for the address.
     */
    private function resolveZipPlus4($zip5, $args)
    {
        global $io;
        $apiKey = (string)getModuleCache('api', 'settings', 'sales_tax_api', 'geocodio_key');
        if ($apiKey === '') { return null; } // optional service
        $fullAddr = trim("{$args['address1']} {$args['address2']}, {$args['city']}, {$args['state']} $zip5");
        $resp = $io->cURL($this->geocodeURL, ['q' => $fullAddr, 'fields' => 'zip4', 'api_key' => $apiKey], 'get');
        if (!$resp) { msgDebug("\ngeocodio: no response for $fullAddr"); return null; }
        $data = json_decode($resp, true);
        $plus4 = $data['results'][0]['fields']['zip4']['plus4'][0] ?? null;
        return $plus4 ? ($zip5 . $plus4) : null;
    }

    /** Date string for the start of the next calendar quarter (cache TTL anchor). */
    private function getNextQuarterStart()
    {
        $month = (int)biz_date('m');
        $year  = (int)biz_date('Y');
        if ($month <=  3) { return "$year-04-01"; }
        if ($month <=  6) { return "$year-07-01"; }
        if ($month <=  9) { return "$year-10-01"; }
        return ($year + 1) . "-01-01";
    }

    /**
     * Lazy CREATE TABLE for the rate cache. Idempotent — runs on every
     * lookup but the IF NOT EXISTS makes it cheap. Avoids forcing a
     * migration on existing installs.
     */
    private function ensureCacheTable()
    {
        $tbl = BIZUNO_DB_PREFIX.'tax_rate_cache';
        dbGetResult("CREATE TABLE IF NOT EXISTS $tbl (
            id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            zip_key VARCHAR(10) NOT NULL DEFAULT '',
            state CHAR(2) NOT NULL DEFAULT '',
            combined_rate DECIMAL(7,5) NOT NULL DEFAULT 0,
            state_name VARCHAR(64) DEFAULT NULL,
            county VARCHAR(64) DEFAULT NULL,
            city VARCHAR(64) DEFAULT NULL,
            fetched_at DATE DEFAULT NULL,
            expires_at DATE DEFAULT NULL,
            PRIMARY KEY (id),
            KEY zip_state (zip_key, state)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    /**
     * Calculates the sales-by-state report for tax filing. Pulls invoices
     * (j12) and credits (j13) for the requested fiscal period, buckets them
     * into state/county/city totals (using cache table for jurisdiction
     * names where present), and downloads a CSV.
     */
    public function calcTaxCollected()
    {
        global $io;
        msgDebug("\nEntering calcTaxCollected");
        $mktplcs = [];
        if (!empty($this->nexusMeta['marketplaces'])) {
            foreach ($this->nexusMeta['marketplaces'] as $row) { $mktplcs[] = $row['cID']; }
        }
        $period = clean('taxMonth', 'integer', 'post');
        $rows   = dbGetMulti(BIZUNO_DB_PREFIX.'journal_main', "period=$period AND journal_id IN (12, 13)", 'post_date',
            ['id', 'journal_id', 'invoice_num', 'post_date', 'total_amount', 'sales_tax', 'freight', 'contact_id_b',
             'address1_s', 'address2_s', 'city_s', 'state_s', 'postal_code_s', 'country_s']);
        $invoices = [['post_date','type','invoice','shipping','tax','total','cust_id','exempt','address1','address2','city','state','postal_code','country']];
        foreach ($rows as $row) {
            $isCM = ($row['journal_id'] == 13);
            $invoices[] = [
                $row['post_date'], $isCM?'credit':'sale', $row['invoice_num'],
                $isCM ? -$row['freight']      : $row['freight'],
                $isCM ? -$row['sales_tax']    : $row['sales_tax'],
                $isCM ? -$row['total_amount'] : $row['total_amount'],
                $row['contact_id_b'], empty($row['sales_tax']) ? 1 : 0,
                $row['address1_s'], $row['address2_s'], $row['city_s'], $row['state_s'], $row['postal_code_s'], $row['country_s']];
        }
        msgDebug("\nReady to roll up ".(sizeof($invoices)-1)." invoices/credits");
        $report = $this->generateTaxReport(['invoices'=>$invoices, 'marketplaces'=>$mktplcs, 'nexus'=>$this->nexusMeta['states']]);
        $output = $this->generateFile($report);
        $io->download('data', implode("\n", $output), "Period_{$period}_Sales-".biz_date('Y-m-d').".csv");
    }

    /**
     * Bucket the invoice list into [state][county][city] => totals. States
     * outside USA collapse to '_INT'. Cities/counties only get pulled
     * (from tax_rate_cache, by ZIP) when the state requires detailed
     * filing — see $statesDetail. Marketplace customers are skipped.
     */
    private function generateTaxReport($data)
    {
        $output    = [];
        $mktplaces = !empty($data['marketplaces']) ? $data['marketplaces'] : [];
        $nexusSt   = !empty($data['nexus']) ? $data['nexus'] : [];
        if (empty($data['invoices'])) { return $output; }
        $header = array_shift($data['invoices']);
        foreach ($data['invoices'] as $line) {
            $row = array_combine($header, $line);
            if (in_array($row['cust_id'], $mktplaces)) { msgDebug("\nSkipping invoice {$row['invoice']}, marketplace"); continue; }
            $state = ('USA' !== strtoupper($row['country'])) ? '_INT' : strtoupper($row['state']);
            $nexus = in_array($row['state'], $nexusSt) ? 1 : '';
            $exempt= in_array($row['state'], $this->statesNoTax) || empty($row['tax']) ? 1 : '';
            $info  = in_array($state, $this->statesDetail)
                ? $this->getJurisdictionFromCache($row['postal_code'], $row['state'])
                : ['county'=>'_all', 'state_rate'=>0, 'county_rate'=>0, 'city_rate'=>0];
            $cnty  = !empty($row['tax']) ? ($info['county'] ?: '_unknown') : '_exempt';
            $city  = !empty($row['tax']) && (!$this->skipCity || substr($cnty, -1) === '*') ? ucwords(strtolower($row['city'])) : '_all';
            if (!isset($output[$state][$cnty][$city])) {
                $output[$state][$cnty][$city] = ['nexus'=>$nexus, 'exempt'=>$exempt, 'cnt'=>0,
                    'total_sales'=>0, 'total_tax'=>0, 'calc_tax'=>0,
                    'rate_state'=>$info['state_rate'], 'rate_county'=>$info['county_rate'], 'rate_city'=>$info['city_rate']];
            }
            $output[$state][$cnty][$city]['cnt']++;
            $output[$state][$cnty][$city]['total_sales'] += $row['total'];
            $output[$state][$cnty][$city]['total_tax']   += $row['tax'];
        }
        return $output;
    }

    /**
     * Best-effort jurisdiction lookup from the local tax_rate_cache. We
     * stored state/county/city names alongside each cached rate; for
     * detail-state reporting we surface those. Multiple cache rows for the
     * same ZIP get an asterisk on the county name (legacy ambiguity flag).
     */
    private function getJurisdictionFromCache($zip, $state)
    {
        $default = ['county'=>'', 'state_rate'=>0, 'county_rate'=>0, 'city_rate'=>0];
        if (empty($zip)) { return $default; }
        $zip5 = substr(preg_replace('/[^0-9]/', '', $zip), 0, 5);
        if ($zip5 === '') { return $default; }
        $rows = dbGetMulti(BIZUNO_DB_PREFIX.'tax_rate_cache',
            "zip_key LIKE '$zip5%' AND state='".strtoupper($state)."'");
        if (empty($rows)) { return $default; }
        $county = $rows[0]['county'] ?? '';
        if (count($rows) > 1) { $county .= '*'; } // ambiguity — multiple ZIP+4 buckets for this ZIP5
        return ['county'=>$county, 'state_rate'=>0, 'county_rate'=>0, 'city_rate'=>0]; // Zip-Tax returns combined rate only; sub-rates left at 0
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
