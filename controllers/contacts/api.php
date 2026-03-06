<?php
/*
 * Handles the import/export and other API related operations
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
 * @filesource /controllers/contacts/api.php
 */

namespace bizuno;

class contactsApi
{
    public $moduleID = 'contacts';

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
    }

    /**
     * This method builds the div for operating the API to import data, information includes import templates and forms, export forms
     * @param array $layout - input data passed as array of tags, may also be passed as $_POST variables
     */
    public function contactsAPI(&$layout)
    {
        $fields = [
            'btnConapi_tpl'=> ['icon'=>'download','label'=>lang('download'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_URL_AJAX."&bizRt=contacts/api/apiTemplate');"]],
            'fileContacts' => ['attr'=>['type'=>'file']],
            'btnConapi_imp'=> ['icon'=>'import','label'=>lang('import'),'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmConApiImport').submit();"]],
            'btnConapi_exp'=> ['icon'=>'export','label'=>lang('export'),'events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src','".BIZUNO_URL_AJAX."&bizRt=contacts/api/apiExport');"]]];
        $forms = ['frmConApiImport'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=contacts/api/apiImport"]]];
        $html  = '<p>'.lang('conapi_desc', $this->moduleID).'</p>
<p>'.lang('conapi_template', $this->moduleID).html5('', $fields['btnConapi_tpl']).'</p><hr />'.html5('frmConApiImport',  $forms['frmConApiImport']).'
<p>'.lang('conapi_import', $this->moduleID)  .html5('fileContacts', $fields['fileContacts']).html5('', $fields['btnConapi_imp'])."</p></form>\n<hr />
<p>".lang('conapi_export', $this->moduleID)  .html5('', $fields['btnConapi_exp']).'</p>';
        $layout['jsReady']['contactsImport'] = "ajaxForm('frmConApiImport');";
        $layout['tabs']['tabAPI']['divs'][$this->moduleID] = ['order'=>20,'label'=>getModuleCache($this->moduleID, 'properties', 'title'),'type'=>'html','html'=>$html];
    }

    /**
     * Sets the import templates used to map received data to Bizuno structure, downloads to user
     * Doesn't return if successful
     */
    public function apiTemplate()
    {
        global $io;
        $tables = [];
        require(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/tables.php');
        $cMap   = $tables['contacts']['fields'];
        $header = [];
        $props  = [];
        $cFields= dbLoadStructure(BIZUNO_DB_PREFIX.'contacts', '', 'Contact');
        foreach ($cFields as $field => $settings) {
            if (isset($cMap[$field]['import']) && !$cMap[$field]['import']) { continue; } // skip values that cannot be imported
            $header[]= csvEncapsulate($settings['tag']);
            $req = !empty($cMap[$field]['required']) ? ' [Required]' : ' [Optional]';
            $desc= isset($cMap[$field]['desc']) ? " - {$cMap[$field]['desc']}" : (isset($settings['label']) ? " - {$settings['label']}" : '');
            $props[] = csvEncapsulate($settings['tag'].$req.$desc);
        }
        $content = implode(",", $header)."\n\nField Information:\n".implode("\n",$props);
        $io->download('data', $content, 'ContactsTemplate.csv');
    }

    /**
     * Imports from a csv file to the contacts database table according to the template
     * @param type $layout - structure coming in
     * @return modified layout
     */
    public function apiImport(&$layout, $rows=false, $verbose=true)
    {
        if (!$security = validateAccess('admin', 2)) { return; }
        $tables  = $map = $this->template = [];
        require(BIZUNO_FS_LIBRARY."controllers/bizuno/install/tables.php"); // replaces $map
        $cMap    = $tables['contacts']['fields'];
        $cFields = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts', '', 'Contact');
        foreach ($cFields  as $field => $props) { $template[$props['tag']] = trim($field); }
        if (!$rows) { $rows = $this->prepData(); }
        $cnt     = $newCnt = $updCnt = 0;
        dbTransactionStart();
        foreach ($rows as $row) {
            $cData = $amData = $asData = [];
            foreach ($row as $tag => $value) { if (isset($template[$tag])) {
                if (strpos($tag, 'Contact')    ===0) { $cData[$template[$tag]] = trim(str_replace('""', '"', $value)); }
            } }
            // commented out to skip blank rows
            if (!isset($cData['short_name'])) { return msgAdd("The Contact ID field cannot be found and is a required field. The operation was aborted!"); }
            if (empty($cData['type']))       { msgAdd(sprintf("Missing Type on row: %s. The row will be skipped!", $cnt+1)); continue; }
            $cData['type'] = trim(strtolower($cData['type']));
            if (!in_array($cData['type'], ['c','v','b','i','e','j'])) { msgAdd("Contact: {$cData['short_name']} has an invalid type, skipping!"); continue; }
            if (empty($cData['short_name'])) { $cData['short_name'] = $this->getShortName($cData['type'], $amData); }
            if (empty($cData['short_name'])) { msgAdd(sprintf("The Contact ID cannot be auto-set on primary_name: %s. The row will be skipped!", $amData['primary_name'])); continue; }
            // clean out the un-importable fields
            foreach ($cMap as $field => $settings) { if (!$settings['import']) { unset($cData[$field]); } }
            $cID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "short_name='".addslashes($cData['short_name'])."' AND type='{$cData['type']}'");
            if (!isset($cData['last_update'])) { $cData['last_update'] = biz_date('Y-m-d'); }
            if ($cID) {
                $cData['id'] = $cID;
                if ($security < 2) { msgAdd('Your permissions prevent altering an existing record, the entry will be skipped!'); continue; }
                validateData($cFields, $cData);
                dbWrite(BIZUNO_DB_PREFIX.'contacts', $cData, 'update', "id=$cID");
                $isNew = false;
                $updCnt++;
            } else {
                $defGL = $cData['type']=='v' ? getModuleCache('phreebooks', 'settings', 'vendors', 'gl_expense') : getModuleCache('phreebooks', 'settings', 'customers', 'gl_sales');
                if (!isset($cData['gl_account']) || !$cData['gl_account']) { $cData['gl_account'] = $defGL; }
                $cData['first_date'] = biz_date('Y-m-d');
                validateData($cFields, $cData);
                $cID   = dbWrite(BIZUNO_DB_PREFIX.'contacts', $cData);
                $isNew = true;
                $newCnt++;
            }
            $cnt++;
        }
        dbTransactionCommit();
        if ($verbose) { msgAdd(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt), 'info'); }
        msgLog(sprintf("Imported total rows: %s, Added: %s, Updated: %s", $cnt, $newCnt, $updCnt));
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('body').removeClass('loading');"]]);
    }

    /**
     * reads the uploaded file and converts it into a keyed array
     * @param array $fields - table field structure
     * @return array - keyed data array of file contents
     */
    private function prepData()
    {
        global $io;
        if (!$io->validateUpload('fileContacts', '', ['csv'])) { return; } // removed type=text as windows edited files fail the test
        $output= [];
        $rows  = array_map('str_getcsv', file($_FILES['fileContacts']['tmp_name']));
        $head  = array_shift($rows);
        foreach ($rows as $row) { $output[] = array_combine($head, $row); }
        return $output;
    }

    /**
     * Auto generates a short name based on the users preferences
     */
    private function getShortName($type='c', $address=[])
    {
        $meth = getModuleCache('contacts', 'settings', 'general', "short_name_$type", 'auto');
        if ($meth=='email' && !empty($address['email']))      { return $address['email']; }
        if ($meth=='tele'  && !empty($address['telephone1'])) { return $address['telephone1']; }
        // else fall through and generate the next auto-increment
        $str_field = $type=='c' ? 'next_cust_id_num' : 'next_vend_id_num';
        $output = getNextReference($str_field);
        return $output;
    }

    /**
     * Exports the inventory table in csv format including all custom fields.
     * @return doesn't unless there is an error, exits script on success
     */
    /**
     * Exports data from the database table contacts to a user
     * Doesn't return if successful
     */
    public function apiExport()
    {
        global $io;
        if (!$security = validateAccess('admin', 1)) { return; }
        $tables  = $merged = $output = [];
        require(BIZUNO_FS_LIBRARY.'controllers/bizuno/install/tables.php');
        $cMap    = $tables['contacts']['fields'];
        $cFields = dbLoadStructure(BIZUNO_DB_PREFIX.'contacts',     '', 'Contact');
        $header  = [];
        foreach ($cFields  as $field => $settings) { if (!empty($cMap[$field]['export'])) { $header[] = $settings['tag']; } }
        $cValues = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', '', 'short_name');
         // merge the contacts table and address_table
        foreach ($cValues as $row) { $merged[$row['id']]['contact'] = $row; }
        foreach ($merged  as $row) {
            if (!isset($row['contact'])) { continue; }
            $data = [];
            foreach ($cFields  as $field => $settings) { if (!empty($cMap[$field]['export'])) { $data[]= csvEncapsulate($row['contact'][$field]); } }
            $output[] = implode(',', $data);
        }
        msgDebug("\noutput = ".print_r($output, true));
        $io->download('data', implode(",", $header)."\n".implode("\n", $output), 'Contacts-'.biz_date('Y-m-d').'.csv');
    }
}