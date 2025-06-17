<?php
/*
 * @name Bizuno ERP - Bizuno Pro Payment Module
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
 * @version    7.x Last Update: 2025-06-16
 * @filesource /controllers/payment/nacha.php
 *
 */

namespace bizuno;

class paymentNacha
{
    public  $moduleID  = 'payment';
    public  $pageID    = 'nacha';
    private $dirBackup = 'data/banking/nacha/';
    private $lineLength= 94; // number of characters per line in the output, does not change
    private $output    = [];
    private $rowCount  = 0;
    private $totalDeb  = 0;
    private $totalCred = 0;
    private $totalHash = 0;
    public  $lang;
    public  $refNum;
    public  $myData;

    function __construct($mapID='')
    {
        $this->lang= getExtLang($this->moduleID);
        $this->refNum = 'ACH'.biz_date('ymdHi'); // .'-'; // bank groups these into single withdrawal
        $banks = getMetaCommon('ach_banks');
        msgDebug("\nConstucting nacha with mapID = $mapID and banks meta = ".print_r($banks, true));
        foreach ($banks as $bank) { if ($bank['mapID']==$mapID) { $this->myData = $bank; break; } }
        $this->myData['transit_routing'] = substr($this->myData['biz_route'], 0, 8); // Transit routing Number, less check digit
    }

    public function manager(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('nacha', 3)) { return; }
        $data = ['title'=>sprintf(lang('journal_id_20_ach'), lang('tbd_manager')),
            'divs'  => ['body'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'nacha'  => ['order'=>20,'type'=>'panel','key'=>'nacha',  'classes'=>['block33']],
                    'divAtch'=> ['order'=>30,'type'=>'panel','key'=>'divAtch','classes'=>['block66']]]]]]],
            'panels'=> [
                'nacha' => ['label'=>$this->lang['panel_nacha'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmNacha'],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['nachaDesc','btnBackup']], // 'incFiles' is a later feature ???
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'divAtch'=> ['type'=>'attach','defaults'=>['dgName'=>'dgBackup','path'=>$this->dirBackup,'title'=>$this->lang['file_nacha'],'url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/nacha/mgrRows",'ext'=>$io->getValidExt('txt')]]],
            'forms'  => [
                'frmNacha'=> ['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=bizuno/nacha/save"]]],
            'fields' => [
                'nachaDesc' => ['order'=>10,'html'=>$this->lang['desc_nacha'],'attr'=>['type'=>'raw']],
                'btnBackup' => ['order'=>30,'icon'=>'nacha','label'=>lang('go'),'align'=>'right','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmNacha').submit();"]]],
            'jsReady'=> ['init'=>"ajaxForm('frmNacha');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Load stored NACHA files through AJAX call
     * @param array $layout - structure coming in
     */
    public function mgrRows(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess('nacha', 3)) { return; }
        $rows   = $io->fileReadGlob($this->dirBackup, $io->getValidExt('txt'), 'desc');
        $totRows= sizeof($rows);
        $rowNum = clean('rows',['format'=>'integer','default'=>10],'post');
        $pageNum= clean('page',['format'=>'integer','default'=> 1], 'post');
        $output = array_slice($rows, ($pageNum-1)*$rowNum, $rowNum);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>$totRows, 'rows'=>$output])]);
    }

    public function process(&$ledger) {
        msgDebug("\nEntering NACHA process with primary_name = {$ledger->main['primary_name_b']} and cID = {$ledger->main['contact_id_b']}");
        // if current contact has ACH enabled, generate a new ref number
        $ach = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['short_name','ach_enable','ach_routing','ach_account'], "id='{$ledger->main['contact_id_b']}'");
        $this->ach = $ledger->ach = [];
        if (!empty($ach['ach_enable'])) {
            msgDebug("\nACH is enabled, creds: ".print_r($ach, true));
            $this->rowCount++;
            $this->ach = $ledger->ach = ['routing'=>str_pad($ach['ach_routing'], 9, '0', STR_PAD_LEFT),'account'=>$ach['ach_account']];
            $ledger->main['invoice_num'] = $this->refNum; // . str_pad($this->rowCount, 2, '0', STR_PAD_LEFT); // bank groups these into single withdrawal
            $this->primary_name= $ledger->main['primary_name_b'];
            $this->short_name  = preg_replace("/[^a-zA-Z0-9]/", "", strtoupper($ach['short_name']));
            $this->totalHash  += substr($ledger->ach['routing'], 0, 8);
            $total = number_format($ledger->main['total_amount']*100, 0, '', ''); // rounds and removes decimal point
            $this->totalCred  += intval($total);
            $this->addNachaRow('details', $total);
        }
    }

    public function openACH($format='ccd')
    {
        $map = [];
        if (file_exists(BIZBOOKS_ROOT."controllers/phreebooks/nachaMaps/$format.php")) {
            require(BIZBOOKS_ROOT."controllers/phreebooks/nachaMaps/$format.php");
        }
        else {  msgAdd("Map file of type $format doesn't exist. Bailing!"); }
        $this->map = $map;
        msgDebug("\nWorking with map format $format"); // which looks like: ".print_r($this->map, true));
        $this->addNachaRow('file_head');
        $this->addNachaRow('batch_head');
    }

    public function closeACH()
    {
        global $io;
        $this->addNachaRow('batch_ctl');
        $this->addNachaRow('file_ctl');
        msgDebug("\nReady to save file, output file looks like: ".print_r($this->output, true));
        foreach ($this->output as $index => $row) { // check each line for total length, 94 characters
            if (strlen($row) <> $this->lineLength) { return msgAdd("Houston, we have a problem! Line $index is not the right length. Bailing!"); }
        }
        if (empty($this->rowCount)) {
            return msgAdd("No transactions were via ACH, there is nothing to see here.", 'info');
        }
        $data = implode("\n", $this->output);
        msgDebug("\nNacha file generated = \n\n".print_r($data, true)."\n\n");
        $filename = biz_date('Ymd-his')."-{$this->map['id']}.txt";
        $io->fileWrite($data, "{$this->dirBackup}$filename");
        $script = "jqBiz('#attachIFrame').attr('src','".BIZUNO_AJAX."&bizRt=bizuno/main/fileDownload&pathID=$this->dirBackup&fileID=$filename');";
        $button = '<button onclick="'.$script.'">Click here to download the NACHA file</button>';
        msgAdd("<p>A total of $this->rowCount ACH records were created.</p><p> $button </p>", 'info');
        msgLog("A total of $this->rowCount ACH records were created.");
    }

    private function addNachaRow($index, $total=0)
    {
        $output = '';
        foreach ($this->map[$index] as $row) {
            if ($row['format']=='const') {
                $output .= $this->formatter($row);
                continue;
            }
            switch ($row['data']) { // swap out the data and format
                case 'batch_number':   $row['data'] = $this->myData['batch_number'];         break;
                case 'block_count':    $row['data'] = $this->getBlockCnt();                  break;
                case 'biz_entry':      $row['data'] = $this->myData['biz_entry'];            break;
                case 'contact_id':     $row['data'] = $this->myData['cID'];                  break;
                case 'date_mdy':       $row['data'] = biz_date(substr($row['data'], 5));     break;
                case 'date_text':      $row['data'] = strtoupper(biz_date('M d'));           break;
                case 'date_ymd':       $row['data'] = biz_date(substr($row['data'], 5));     break;
                case 'biz_id':         $row['data'] = $this->myData['biz_id'];               break;
                case 'date_plus_1':    $row['data'] = date('ymd', strtotime(biz_date().' +1 Weekday')); break;
                case 'biz_route':      $row['data'] = $this->myData['biz_route'];            break;
                case 'biz_route_act':  $row['data'] = substr($this->myData['biz_route'],0,8);break;
                case 'biz_route_chk':  $row['data'] = substr($this->myData['biz_route'],8,1);break;
                case 'hash_total':     $row['data'] = substr($this->totalHash, -10);         break;
                case 'biz_name':       $row['data'] = $this->myData['biz_name'];             break;
                case 'payee_account':  $row['data'] = $this->ach['account'];                 break;
                case 'payee_amount':   $row['data'] = $total;                                break;
                case 'payee_id':       $row['data'] = $this->short_name;                     break;
                case 'payee_name':     $row['data'] = $this->primary_name;                   break;
                case 'payee_routing':  $row['data'] = $this->ach['routing'];                 break;
                case 'payee_route_act':$row['data'] = substr($this->ach['routing'], 0, 8);   break;
                case 'payee_route_chk':$row['data'] = substr($this->ach['routing'], 8, 1);   break;
                case 'row_count':      $row['data'] = $this->rowCount;                       break;
                case 'time_hi':        $row['data'] = biz_date(substr($row['data'], 5));     break;
                case 'total_credit':   $row['data'] = $this->totalCred;                      break;
                case 'total_debit':    $row['data'] = $this->totalDeb;                       break;
                case 'trace_number':   $row['data'] = $this->myData['transit_routing'] . str_pad($this->rowCount, 7, '0', STR_PAD_LEFT); break;
                default: msgAdd("Bummer I could not find data index: {$row['data']}. This is bad!", 'trap');
            }
            $formatted = $this->formatter($row);
//            msgDebug("\nReceived back: $formatted and sent to formatter data: ".print_r($row, true));
            $output .= $formatted;
        }
        msgDebug("\nReady to write output line for index $index: \n$output");
        $this->output[] = $output;
    }

    // ***************************************************************************************************************
    //                               Support Methods
    // ***************************************************************************************************************
    /**
     * Formats the data according to a specified length, justify and pad
     * @param array $row - what to do and how to do it
     */
    private function formatter($row)
    {
        $temp = substr($row['data'], 0, $row['length']);
        return str_pad($temp, $row['length'], $row['pad'], $row['justify']=='r'?STR_PAD_LEFT:STR_PAD_RIGHT);
    }

    /**
     * If more than 1 per day, then increment based on # of files generated that day
     */
    private function getBlockCnt()
    {
        global $io;
        $filename = $this->dirBackup.biz_date('Ymd');
        $hits = $io->fileReadGlob($filename, $io->getValidExt('txt'));
        return empty($hits) ? 1 : sizeof($hits)+1;
    }
}
