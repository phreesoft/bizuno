<?php
/*
 * @name Bizuno ERP - Paychex Payroll Interface Extension (https://www.paychex.com)
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
 * @version    7.x Last Update: 2026-04-24
 * @filesource /controllers/phreebooks/payroll/paychex/paychex.php
 */

namespace bizuno;

class paychex
{
    public  $moduleID    = 'phreebooks';
    public  $methodDir   = 'payroll';
    public  $code        = 'paychex';
    private $totalDebits = 0;
    private $totalCredits= 0;
    public  $lang        = ['title'=>'Paychex Payroll',
        'description'=> 'The Paychex Payroll interface provides the ability to import your payroll operations from the Paychex.com website as journal entries in Bizuno.',
        'import_desc'=> 'Select an Paychex Payroll file (Reports -> Payroll Details -> Download Spreadsheet) to import into Bizuno as a general journal entry and press the Next icon. File types that can be imported include payroll downloads. (File must be of type csv and have the extension .csv)'];
 
    function __construct() { }

    public function importForm(&$layout=[])
    {
        $fields= [
            'imgLogo' => ['order'=>10,'attr'=>['type'=>'img','height'=>'45','src'=>BIZUNO_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/logo.png"]],
            'desc'    => ['order'=>20,'html'=>"<p>{$this->lang['import_desc']}</p>",'attr'=>['type'=>'raw']],
            'selFile' => ['order'=>30,'attr'=>['type'=>'file']],
            'btnGo'   => ['order'=>40,'icon'=>'next', 'events'=>['onClick'=>"jqBiz('#formIOP').submit();"]]];
        $data = ['type'=>'popup','title'=>lang('import_journal_title'),'attr'=>['id'=>'winIPP'],
            'divs'     => [
                'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>'formIOP'],
                'body'   => ['order'=>40,'type'=>'fields','keys'=>['imgLogo','desc','selFile','btnGo']],
                'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]],
            'forms'    => ['formIOP'=>['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->methodDir/importGo&modID=$this->code"]]],
            'fields'   => $fields,
            'jsReady'  => ['initIOP'=>"ajaxForm('formIOP', true);"]]; // do not check for preSubmit as it picks the journal edit function ???
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Imports the uploaded file and creates a GL entry
     * @global class $io - import/export class
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function importGo(&$layout=[])
    {
        if (!$security = validateAccess('j2_mgr', 2)) { return; }
        $items = [];
        $rows  = $this->processCSV();
        msgDebug("\nWorking with item rows = ".print_r($rows, true));
        $today = biz_date('Y-m-d');
        if (empty($rows)) { return msgAdd("Bad file uploaded, please check it out and try again!"); }
        $diff = round($this->totalDebits - $this->totalCredits, 2);
        if ($diff != 0) { return msgAdd("Debits ($this->totalDebits) do not equal Credits ($this->totalCredits) = $diff, bailing!", 'trap'); }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
        foreach ($rows as $row) {
            $items[] = [
                'qty'           => 1,
                'gl_type'       => 'gl',
                'gl_account'    => $row['glAcct'],
                'description'   => $row['desc'],
                'debit_amount'  => $row['debit'],
                'credit_amount' => $row['credit'],
                'post_date'     => $today];
        }
        // *************** START TRANSACTION *************************
        dbTransactionStart();
        $ledger = new journal(0, 2);
        $ledger->main['primary_name_b']= 'Paychex Payroll';
        $ledger->main['description']   = "Paychex Payroll for $today";
        $ledger->main['invoice_num']   = 'Paychex-'.biz_date('Ymd');
        $ledger->main['total_amount']  = $this->totalDebits;
        $ledger->items                 = $items;
        if (!$ledger->Post()) { msgAdd("A trace file was created to troubleshoot this issue.", 'trap'); return; }
        dbTransactionCommit();
        // ***************  END TRANSACTION  *************************
        msgAdd(sprintf(lang('msg_gl_post_success'), lang('invoice_num_2'), $ledger->main['invoice_num']), 'success');
        msgLog('Paychex Payroll-'.lang('save')." ".lang('invoice_num_2')." ".$ledger->main['invoice_num']." (rID={$ledger->main['id']})");
        $layout = array_replace_recursive($layout, ['rID'=>$ledger->main['id'], 'content'=>  ['action'=>'eval','actionData'=>"jqBiz('#dgPhreeBooks').datagrid('reload'); jqBiz('#winIPP').window('destroy');"]]);
    }

    private function processCSV()
    {
        global $io;
        $output = [];
        $glAccts= getModuleCache('phreebooks', 'chart');
        if (!$io->validateUpload('selFile', '', ['csv','txt'])) { return; }
        $handle= fopen($_FILES['selFile']['tmp_name'], 'r');
        $keys  = [];
        while (($values = fgetcsv($handle)) !== FALSE) {
            if (sizeof($values) <= 2) { continue; } // blank lines, single column header, skip
            if (empty($keys)) {
                foreach ($values as $key) { $keys[] = clean($key, 'alpha_num'); }
                msgDebug("\nGenerated keys => ".print_r($keys, true));
                continue;
            } // first valid line are the keys
            if (sizeof($keys) <> sizeof($values)) { return msgAdd("The csv file is malformed, the total number of columns are not the same between the header and data!"); }
            $row = array_combine($keys, $values);
            msgDebug("\nProcessing row => ".print_r($row, true));
            if (empty($output[$row['GL ACCOUNT']])) {
                $output[$row['GL ACCOUNT']] = [
                    'glAcct'=> $row['GL ACCOUNT'],
                    'desc'  => !empty($glAccts[$row['GL ACCOUNT']]['title']) ? $glAccts[$row['GL ACCOUNT']]['title'] : $row['DESCRIPTION'],
                    'debit' => !empty($row['DEBIT']) ? $row['DEBIT'] : 0,
                    'credit'=> !empty($row['CREDIT'])? $row['CREDIT']: 0];
            } else {
                $output[$row['GL ACCOUNT']]['debit'] += !empty($row['DEBIT']) ? $row['DEBIT'] : 0;
                $output[$row['GL ACCOUNT']]['credit']+= !empty($row['CREDIT'])? $row['CREDIT']: 0;
            }
            $this->totalDebits += !empty($row['DEBIT']) ? $row['DEBIT'] : 0;
            $this->totalCredits+= !empty($row['CREDIT'])? $row['CREDIT']: 0;
        }
        fclose($handle);
        return $output;
    }
}
