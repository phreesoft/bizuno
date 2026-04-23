<?php
/*
 * @name Bizuno ERP - Pro EDI extension - EDI API - Processes EDI with external sources
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
 * @version    7.x Last Update: 2026-04-23
 * @filesource /controllers/phreebooks/ediAPI.php
 *
 * Handles specs:
 *  810 - Invoice (outgoing)
 *  850 - Purchase Order (incoming)
 *  855 - PO Confirm (outgoing)
 *  856 - Shipment Confirm and Tracking # (outgoing)
 *  997 - Acknowledgment (bi-directional)
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/ediSegments.php', 'phreebooksEdiSegments');
bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/journal.php', 'journal');
bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/inventory/prices.php', 'inventoryPrices');

class phreebooksEdiAPI extends phreebooksEdiSegments
{
    public    $moduleID   = 'phreebooks';
    public    $pageID     = 'ediAPI';
    protected $secID      = 'edi';
    protected $nextRefIdx = 'next_edi_num';
    protected $journalID  = 10; // post to sales order, pending review
    private   $isMulti    = ['IT1','LIN','PO1','PID']; // tags that have  multiple, segments
    private   $tmpDir     = 'temp/edi/';
    protected $ediCntrlNum= 0;
    protected $log        = ['errors'=>[]]; // To store the entire transaction on disk
    protected $errors     = [];
    public $struc;
    public $prices;
    public $creds = [];
    public $key;
    public $cID;
    public $cTitle;
    public $ediID;
    public $sepTag;
    public $sepSec;
    public $hostName;
    public $userName;
    public $userPass;
    public $pathPut;
    public $pathGet;
    public $rcvrID;
    public $sftp;
    public $filesReadFromEDI;
    public $ediLines;
    public $working_file;
    public $getFilename;
    public $compSep;
    public $dbLog;
    public $main;
    public $items;
    public $contact;
    public $orderTotal;
    public $ediSONum;
    public $ediResponse;
    
    function __construct()
    {
        $this->prices = new inventoryPrices();
        $this->prices->type = 'c';
        $creds  = dbMetaGet('%','edi_client');
        msgDebug("\nRetrieved creds from meta = ".print_r($creds, true));
        foreach ($creds as $cred) { $this->creds['C'.$cred['cID']] = $cred; } // take individual meta and put them together for processing
        parent::__construct();
    }

    /**
     * Connects to the EDI server
     * @return type
     */
    protected function ediConnect()
    {
        msgDebug("\nConnecting to EDI server @ $this->hostName");
        if (!class_exists('\phpseclib3\Net\SFTP')) { return msgAdd("Class SFTP not found!"); }
        define('NET_SFTP_LOGGING', \phpseclib3\Net\SFTP::LOG_COMPLEX);
        $this->sftp = new \phpseclib3\Net\SFTP($this->hostName);
        if ($this->sftp->login($this->userName, $this->userPass)) { return; }
        $this->sftp = false;
        return msgAdd("Log in to SFTP server failed!");
    }
    public function ediGet()
    {
        // CAUTION - No security check here or cron will fail as no one is logged into Bizuno
        $opt = clean('opt', 'cmd', 'get');
        setSecurityOverride('prices_c', 1);
        foreach ($this->creds as $creds) {
            foreach ($creds as $key => $value) { $this->$key = $value; }
            $this->ediGetFiles();
        }
        if (!empty($this->filesReadFromEDI)) {
            msgAdd("Method ediGet complete, check the debug log for details.", 'success');
            msgLog("Completed EDI get with # of files read: $this->filesReadFromEDI");
        }
        if ($opt=='man') { msgAdd("Completed EDI get with # of files read: $this->filesReadFromEDI", 'info'); }
    }
    protected function ediGetFiles()
    {
        $this->ediConnect();
        if (!is_object($this->sftp)) { return msgAdd("ediGetFiles - Not connected to server!"); }
        $this->sftp->chdir($this->pathGet); // open get directory
        msgDebug("\nConnected to server, working from directory: ".$this->sftp->pwd()); // show that we're in the 'test' directory
        $files = $this->sftp->nlist('.');
        $this->filesReadFromEDI = sizeof($files);
        msgDebug("\nRead from the server files: ".print_r($files, true));
        $this->sftp->chdir('..'); // back to root folder
        if (empty($files)) { return msgDebug("\nReturning from ediGet with no files read!"); }
        foreach ($files as $file) {
            if (in_array($file, ['.', '..'])) { msgDebug("\nNothing to do continuing..."); continue; }
            msgDebug("\nReading file: $file");
            $tmpPath = BIZUNO_DATA.$this->tmpDir.bin2hex(random_bytes(10)).'.edi';
            $this->sftp->chdir($this->pathGet); // open get directory
            $contents = $this->sftp->get($file, $tmpPath);
            $this->sftp->chdir('..'); // go back to the parent directory
            $path = str_replace(BIZUNO_DATA, '', $tmpPath);
            msgDebug("\nReady to process path = $path and EDI contents: ",print_r($contents, true));
//          msgDebug("\nThe current error response array = ".print_r($this->sftp->getSFTPErrors(), true));
//          msgDebug("\nThe current log looks like: ".print_r($this->sftp->getSFTPLog(), true));
            $this->ediParse($path);
            $this->getFilename = $file;
            $this->ediProcess();
            msgDebug("\nDeleting file $file from the SFTP server.");
            $this->sftp->chdir($this->pathGet); // open get directory
            $this->sftp->delete($file, false);
            $this->sftp->chdir('..'); // go back to the parent directory
            @unlink($tmpPath);
        }
    }
    public function ediManual() // manually process file stored in the file system on passed control number and customer
    {
        // This needs to change to check and process the temp/edi folder if the files are still there.
/*      msgDebug("\nEntering ediGetFiles.");
        global $io;
        $lFiles = $io->folderRead($this->tmpDir);
        msgDebug("\nFiles read = ".print_r($lFiles, true));
        if (!empty($lFiles)) {
            foreach($lFiles as $file) {
                msgDebug("\nReady to process EDI contents: ",print_r($file, true));
                $this->path = $this->tmpDir.$file;
                $this->ediParse($this->tmpDir.$file);
                $this->getFilename = $file;
                $this->ediProcess();
            }
        }
        msgAdd("PhreeSoft Trap, finished processing.");
        return; */

        $rID = clean('rID', 'integer', 'request'); // to work with either get or post
return msgAdd("EDI control num = $this->ediCntrlNum. This needs EDI control num to fetch file as the passed ID needs to be from the journal main table.");
        if (empty($rID)) { return msgAdd('Bad record ID'); }
        // get the rcv file from the db
        $row = dbGetRow(BIZUNO_DB_PREFIX.'edi_log', "id=$rID");
        msgDebug("\nReady to process EDI row: ",print_r($row, true));
        if (empty($row)) { return msgAdd("Record ID specified but not found in the database!"); }
        foreach ($this->creds as $creds) {
            if ($creds['cTitle']<>$row['edi_source']) { continue; }
            foreach ($creds as $key => $value) { $this->$key = $value; }
        }
        $this->ediConnect();
        $this->ediParse('', $row['edi_data']);
        $this->cTitle      = $row['edi_source'];
        $this->getFilename = $row['edi_name'];
        $this->ediProcess();
    }
    public function ediTransmit()
    {
        $mID = clean('rID', 'integer', 'get'); // mID is the SO ID for 855 and invoice ID for 810, 856
        $spec= clean('data','integer', 'get');
        if (empty($mID)) { return; }
        $meta = getMetaJournal($mID, "edi_spec_{$spec}");
        if (!empty($meta)) { return msgAdd("This EDI transaction has already been submitted. The EDI Log database record needs to be deleted before it can be resent!"); }
        $this->main  = dbGetRow(BIZUNO_DB_PREFIX.'journal_main', "id=$mID");
        msgDebug("\nRead journal main record $mID: ".print_r($this->main, true));
        $this->items = dbGetMulti(BIZUNO_DB_PREFIX.'journal_item', "ref_id=$mID AND gl_type='itm' AND sku<>''");
        msgDebug("\nRead journal item record with ref_id = $mID: ".print_r($this->items, true));
        if (empty($this->items)) { return msgAdd('No items found. Bailing!'); }
        foreach ($this->creds['C'.$this->main['contact_id_b']] as $key => $value) { $this->$key = $value; }
        $this->ediCntrlNum = getNextReference('next_edi_num');
        $this->dbLog = ['edi_source'=>$this->cTitle,'spec'=>$spec, 'control_num'=>$this->ediCntrlNum, 'main_id'=>$mID];
//      $this->writeLog(); // write log now to get a record started, this prevents duplicate transmits if first is slow and user transmits again.
        $this->ediConnect();
        if (!is_object($this->sftp)) { return msgAdd("ediTransmit - Not connected to server!"); }
        switch ($spec) {
            default:    return msgAdd("Error - Bad spec, expecting 810, 855, or 856");
            case '810': $this->x12_810_rsp(); break; // Invoice
            case '855': $this->x12_855_rsp(); break; // PO Confirm
            case '856': $this->x12_856_rsp(); break; // Shipment Confirm and Tracking #
        }
        $this->writeLog();
        msgAdd("Transmission of EDI data to customer successful!", 'success');
    }
    protected function ediPut($strPut, $spec='997') // Sends the file to the server, and enters result into log
    {
        if (!is_object($this->sftp)) { return msgAdd("ediSet - Not connected to server!"); }
        // put it onto remote SFTP server
        $middle = !empty($this->ediSONum) ? $this->ediSONum : hrtime(true); // use the journal_main id unless there was an error in which use a timestamp
        $filename = $this->rcvrID.'_'.$middle.'_'.$this->ediCntrlNum."_$spec.edi";
        msgDebug("\nediPut with file name $filename with contents:\n$strPut");
        $prefix = $spec=='997' ? 'ack' : 'edi';
        $this->sftp->chdir($this->pathPut); // open put directory
        $this->sftp->put($filename, $strPut);
        if ($this->verifyPut($filename)) {
            msgLog("SFTP file to spec $spec sent to server.");
            $this->dbLog[$prefix.'_name'] = $filename;
            $this->dbLog[$prefix.'_date'] = biz_date('Y-m-d H:i:s');
            $this->dbLog[$prefix.'_data'] = $strPut;
            $this->sftp->chdir('..'); // go back to the root directory
            return true;
        }
        $this->sftp->chdir('..'); // go back to the root directory
        msgAdd("Write back to EDI server failed! I Couldn't find the file.", 'trap');
    }
    protected function verifyPut($fn) // Verify the write by reading back and checking for file
    {
        $files = $this->sftp->nlist('.');
        msgDebug("\nLooking for file $fn. Read from the server for verification: ".print_r($files, true));
        if (empty($files)) { return; }
        foreach ($files as $file) {
            msgDebug("\nReading file: $file");
            if ($file == $fn) { return true; }
        }
        return false;
    }
    protected function ediParse($path='', $contents='')
    {
        global $io;
        msgDebug("\nEntering ediParse with path = $path");
        $this->ediLines = []; // erase
        if (!empty($path)) {
            $disk = $io->fileRead($path);
            $file = !empty($disk['data']) ? $disk['data'] : '';
            msgDebug("\nRead file contents = ".print_r($file, true));
        } else { $file = $contents; }
        if (empty($file)) { return; }
        $this->working_file = $file;
        $lines = explode($this->sepTag, $file);
        msgDebug("\nExploded into array, lines = ".print_r($lines, true));
        while (sizeof($lines) > 0) {
            $seg = array_shift($lines); // segments
            if (empty($seg)) { continue; }
            $els = explode($this->sepSec, $seg);
            if (in_array($els[0], ['N1'])) { // address information
                $addr = [$els];
                while (true) {
                    if (!empty($lines[0]) && in_array(substr($lines[0], 0, 2), ['N2','N3','N4'])) {
                        $seg = array_shift($lines); // segments
                        $addr[] = explode($this->sepSec, $seg);
                    } else { break; }
                }
                $this->ediLines['NX'][] = $addr;
            } else {
                if (in_array($els[0], $this->isMulti)) {
                    $this->ediLines[$els[0]][] = $els;
                } else {
                    $this->ediLines[$els[0]]   = $els;
                }
            }
        }
        msgDebug("\nLeaving ediParse with exploded ediLines = ".print_r($this->ediLines, true));
    }
    protected function ediProcess() // incoming EDI data
    {
        $this->errors     = [];
        // validate ISA, extract ref ID's
        if (trim($this->ediLines['ISA'][8]) <> $this->rcvrID) { $this->errors[] = "Error - Receiver expected to be receiver and feed didn't match";  return; }
        $this->compSep    = $this->ediLines['ISA'][16];
        $this->ediCntrlNum= intval($this->ediLines['ISA'][13]);
        // test for X12 spec #
        switch ($this->ediLines['ST'][1]) {
            default:
                $error = "\nError, bad ST id, expected 850, 997, etc, received {$this->ediLines['ST'][1]}";
                $this->errors[] = $error;
                msgDebug("\n".$error, 'trap');
                $this->dbLog = ['spec'    =>$this->ediLines['ST'][1],'edi_source'=>$this->cTitle,      'control_num'=>$this->ediCntrlNum,
                                'edi_name'=>$this->getFilename,      'edi_data'  =>$this->working_file,'edi_date'   =>biz_date('Y-m-d H:i:s')];
                break;
            case '850':
                if ($this->ediLines['GS'][1] <> 'PO') { $this->errors[] = "Error - Bad GS element 1, expected PO received: ".$this->ediLines['GS'][1]; }
                $this->dbLog = ['spec'    =>$this->ediLines['ST'][1],'edi_source'=>$this->cTitle,      'control_num'=>$this->ediCntrlNum,
                                'edi_name'=>$this->getFilename,      'edi_data'  =>$this->working_file,'edi_date'   =>biz_date('Y-m-d H:i:s')];
                $this->x12_850_rcv();
                $this->x12_997_rsp();
                if (!sizeof($this->errors)) { $this->emailSuccess(); }
                break;
            case '997': // response from source
                $refID= intval($this->ediLines['AK2'][2]); // extract the journal_main record
                $spec = intval($this->ediLines['AK2'][1]);
                $this->dbLog = dbMetaGet(0, "edi_spec_{$spec}", 'journal', $refID);
                metaIdxClean($this->dbLog);        
                $this->dbLog = ['spec'=>$this->ediLines['ST'][1], 'ack_name'=>$this->getFilename, 'ack_data'=>$this->working_file, 'ack_date'=>biz_date('Y-m-d H:i:s')]; // 'id'=>$id, 
                if (empty($this->dbLog)) {
                    $error = "Bizuno cannot find receive record in our DB for our control number $this->ediCntrlNum. This is bad, contact PhreeSoft! ACK data = ".print_r($this->ediLines, true);
                    msgDebug("\n".$error, 'trap');
                    $this->errors[] = $error;
                }
                if ($this->ediLines['AK9'][1]<>'A') {
                    $error = "Received error on 997 response for our control number $this->ediCntrlNum. Need to ask EDI Source what the error was. see Dave!";
                    msgDebug("\n".$error, 'trap');
                    $this->errors[] = $error;
                }
                break; // from EDI receiver in response to 810, 855, 856, etc.
        }
        if (sizeof($this->errors)) {
            $this->dbLog['status'] = 'E';
            $this->emailError("Error processing an EDI read request, please contact PhreeSoft for resolution!");
        } else {
            msgDebug("\nSetting status for spec: {$this->ediLines['ST'][1]}");
            $this->dbLog['status'] = 'A';
        }
        $this->writeLog();
        return sizeof($this->errors) ? false : true;
    }
    protected function x12_810_rsp() // Invoice
    {
        // ['ISA','GS','ST','BIG','NTE','REF','N1','N2','N3','N4','ITD','DTM','FOB','IT1','TDS','SAC','CTT','SE','GE','IEA']
        $this->ediResponse = [];
        $this->ediHeader('810');
        $this->BIG();
//      $this->NTE();      // not in sample
//      $this->REF();      // not in sample
        $this->N1x('BT');
        $this->N1x('RE');
        $this->N1x('SF');
        $this->N1x('ST');
        $this->ITD();
//      $this->DTM();      // not in sample
//      $this->FOB();      // not in sample
        $this->IT1();
        $this->TDS();
        $this->SAC();      // for shipping, extra charges, comes after TDS if only single shipping charge per PO
        $this->ediFooter('810');
        msgDebug("\nReady to return 810 response with: ".print_r($this->ediResponse, true));
        $this->ediPut(implode($this->sepTag, $this->ediResponse), '810');
        return true;
    }
    protected function x12_850_rcv() // PO
    {
        $this->items  = []; // journal line items
        $this->contact= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id='$this->cID'");
        $this->main   = ['gl_acct_id'=>getModuleCache('phreebooks', 'settings', 'customers', 'gl_receivables'),
            'closed'=>0, 'terms'=>$this->contact['terms']]; // journal main record
        $this->orderTotal = 0;
        foreach ($this->ediLines as $key => $line) {
            if ($key=='NX') { // address line, need to sort
                foreach ($line as $address) { $this->N1($address); }
            } elseif (!in_array($key, ['ISA','GS','ST','CTT','SE','GE','IEA'])) {
                if (!method_exists($this, $key)) {
                    msgDebug("\nUnexpected segment $key, with data: ".msgPrint($line), 'trap');
                    continue;
                }
                $this->$key();
            }
        }
        $this->main['total_amount'] = $this->orderTotal;
        $this->main['description']  = "EDI Purchase Order from your customer";
        $this->items[] = ['gl_type'=>'ttl','description'=>'Total: '.$this->main['primary_name_b'],'debit_amount'=>$this->main['total_amount'],
            'gl_account'=>$this->main['gl_acct_id'],'post_date'=>$this->main['post_date']];
        msgDebug("\nReady to post with main  = ".print_r($this->main,  true));
        msgDebug("\nReady to post with items = ".print_r($this->items, true));
        $result = $this->postOrder(); // Post the Sales Order
        $this->ediSONum = $this->dbLog['main_id'] = empty($result) ? 0 : $result;
        return $this->ediSONum;
    }
    protected function x12_855_rsp() // PO Ack
    {
        $this->ediResponse = [];
        $this->ediHeader('855');
        $this->BAK();
//      $this->DTM();    // not in sample
        $this->N1x('ST');
        $this->PO1(); // line item loop - also handles PID and ACK
        $this->ediFooter('855');
        msgDebug("\nReady to return 855 response with: ".print_r($this->ediResponse, true));
        $this->ediPut(implode($this->sepTag, $this->ediResponse), '855');
        return true;
    }
    protected function x12_856_rsp() // Shipment Resp
    {
        $this->cntHL = 1;
        $parent = 0;
        // get all packages associated with this order
        $this->package = [];
        $meta = getMetaJournal($this->main['id'], 'shipment');
        if (empty($meta)) { return msgAdd("Trying to confirm shipment but no records could be found, bailing!"); }
        foreach ($meta['packages']['rows'] as $pkg) { $this->package[] = ['tracking'=>$pkg['tracking_id'], 'weight'=>$this->getWeight()]; }
        $this->ediResponse = [];
        $this->ediHeader('856');
        $this->BSN();
        for ($i=0; $i<sizeof($this->package); $i++) { // to handle multi-package
            $this->HL($parent, 'S', ["REF:$i"]);
            $parent++;
        }
        $this->HL($parent, 'O', ['PRF','TD1','TD5','N1x:ST']);
        $parent++;
//      $this->HL($parent, 'P', ['MAN']); $parent++;
        $notes = json_decode($this->main['notes'], true);
        foreach ($this->items as $idx => $item) {
            if (empty($notes[$idx]['I'])) { $notes[$idx]['I'] = 'VN'.$this->sepSec.$item['sku']; }
            $this->HL($parent,'I', []); // 'DTM' in spec but not sample, if needed then should be included in this HL
            $this->LIN($idx, $idx+1, $notes);
            $this->SN1($item);
        }
        $parent++;
        $this->ediFooter('856');
        msgDebug("\nReady to return 856 response with: ".print_r($this->ediResponse, true));
        $this->ediPut(implode($this->sepTag, $this->ediResponse), '856');
        return true;
    }
    protected function x12_997_rsp() // response to EDI get
    {
        $this->ediResponse = [];
        $this->ediHeader('997');
        $this->AK1();    // AK1*PO*705868~
        $this->AK2();    // AK2*850*0001~
        $this->AK5();    // AK5*A~
        $this->AK9();    // AK9*A*1*1*1~
        $this->ediFooter('997');
        msgDebug("\nReady to return 997 response with array: ".print_r($this->ediResponse, true));
        $edi_997 = implode($this->sepTag, $this->ediResponse);
        $this->ediPut($edi_997, '997');
        return true;
    }
    protected function getSKU($sku='', $qty=1)
    {
        if (empty($sku)) { $this->errors[] = "Error in method getSKU, received bad SKU: $sku"; }
        $skuInfo = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='$sku' OR old_sku='$sku' AND inactive='0'");
        if (empty($skuInfo)) { // used the placeholder so it can be added/crossed
            $skuXref = getModuleCache($this->moduleID, 'settings', 'edi', 'sku_cross', 'ZZZZYYYY');
            $skuInfo = dbGetRow(BIZUNO_DB_PREFIX.'inventory', "sku='$skuXref'");
            if (empty($skuInfo)) { $skuInfo= ['price'=>0, 'full_price'=>0,'description_short'=>'SKU Not Found']; }
        } else {
            $layout['args'] = ['sku'=>$skuInfo['sku'], 'qty'=>$qty, 'cID'=>$this->cID];
            compose('inventory', 'prices', 'quote', $layout);
            $skuInfo['price'] = !empty($layout['content']['price']) ? $layout['content']['price'] : 0;
        }
        return $skuInfo;
    }
    protected function getWeight() {
        if (empty($this->items['sku'])) { return 1; }
        $skuWeight = dbGetValue(BIZUNO_DB_PREFIX.'inventory', 'item_weight', "sku='".addslashes($this->items['sku'])."' OR old_sku='".addslashes($this->items['sku'])."'");
        return !empty($skuWeight) ? $skuWeight : 1;
    }
    /**
     * Takes the raw EDI data for a PO verifies the data and maps it to a sales order, post to Bizuno
     */
    protected function postOrder() // X12 850 - Purchase Order
    {
        if (sizeof($this->errors)) { return msgDebug("Cannot post as there are errors: ".print_r($this->errors, true), 'trap'); }
        // check for PO already posted, since file cannot be deleted at server, this prevents wasted time re-posts resuilting in post errors
        $found = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'id', "contact_id_b='$this->cID' AND purch_order_id='{$this->main['purch_order_id']}'");
        if ($found) {
            msgDebug("\npath = $this->path");
            if (!empty($this->path)) { @unlink(BIZUNO_DATA.$this->path); }
            return msgDebug("\nThe PO: {$this->main['purch_order_id']} is already in the system. Bailing!");
        }
        // ***************************** START TRANSACTION *******************************
        dbTransactionStart();
        $this->main['notes'] = json_encode($this->main['notes']);
        $journal = new journal(0, $this->journalID, $this->main['post_date']);
        $journal->main = array_replace($journal->main, $this->main);
        $struc = dbLoadStructure(BIZUNO_DB_PREFIX.'journal_main', $this->journalID);
        validateData($struc, $journal->main);
        $journal->items = $this->items;
        $journal->main['contact_id_b'] = $this->cID;
//      $journal->main['store_id']     = $this->creds['C'.$this->cID]['store_id']; // store_id needs to be set manually as it's not in the record
        msgDebug("\nReady to post order, main = ".print_r($journal->main, true));
        msgDebug("\nitems = ".print_r($journal->items, true));
        if (!$journal->Post()) { dbTransactionRollback(); return; }
        // ***************************** END TRANSACTION *******************************
        dbTransactionCommit();
        $invoiceRef= lang("invoice_num_{$journal->main['journal_id']}");
        $billName  = isset($journal->main['primary_name_b']) ? $journal->main['primary_name_b'] : $journal->main['description'];
        msgAdd(sprintf(lang('msg_gl_post_success'), $invoiceRef, $journal->main['invoice_num']), 'success');
        msgLog('Bizuno EDI -'.lang('save')." $invoiceRef ".$journal->main['invoice_num']." - $billName (rID={$journal->main['id']}) ".lang('total').": ".viewFormat($journal->main['total_amount'], 'currency'));
        $_GET['rID'] = $journal->main['id']; // set the ID for hook processing
        return $journal->main['id'];
    }
    protected function writeLog()
    {
        $refID= !empty($this->dbLog['main_id']) ? $this->dbLog['main_id'] : 0;
        $spec = $this->dbLog['spec'];
        if (empty($spec) || ('997'<>$spec && empty($refID))) { return msgDebug("\nEDI ERROR - Trying to write the log but refID OR spec is empty!", 'trap'); }
        $meta = dbMetaGet(0, "edi_spec_{$spec}", 'journal', $refID);
        $rID  = metaIdxClean($meta);        
        dbMetaSet($rID, "edi_spec_{$spec}", $this->dbLog, 'journal', $refID);
    }
    protected function emailSuccess()
    {
        $toEmail  = getModuleCache($this->moduleID, 'settings', 'edi', 'email_to', getModuleCache('bizuno', 'settings', 'company', 'email'));
        $toName   = getModuleCache('bizuno', 'settings', 'company', 'contact', 'Sales Team');
        $fromEmail= getModuleCache('bizuno', 'settings', 'company', 'email');
        $fromName = 'Bizuno EDI';
        $subject  = "Purchase Order ".$this->main['purch_order_id']." from ".$this->main['primary_name_b'];
        $body     = "The purchase order should already be in Bizuno but here are the details anyway: ".print_r($this->main, true);
        $mail     = new bizunoMailer($toEmail, $toName, $subject, $body, $fromEmail, $fromName);
        if (!$mail->sendMail()) { msgAdd("The email failed to send for the errors mentions above.", 'trap'); }
    }
    protected function emailError($subject) {
        $toEmail  = getModuleCache($this->moduleID, 'settings', 'edi', 'email_error', getModuleCache('bizuno', 'settings', 'company', 'email'));
        $toName   = getModuleCache('bizuno', 'settings', 'company', 'contact_ar', 'Sales Team');
        $fromEmail= getModuleCache('bizuno', 'settings', 'company', 'email');
        $fromName = 'Bizuno EDI';
        $body     = "The EDI proccessing team found the following errrors: ".implode("<br />", $this->errors)."<br /><br />";
        $body    .= "The failed EDI file read contained: <br />".str_replace("\n", "<br />", print_r($this->ediLines, true));
        $mail     = new bizunoMailer($toEmail, $toName, $subject, $body, $fromEmail, $fromName);
        if (!$mail->sendMail()) { msgAdd("The email failed to send for the errors mentions above.", 'trap'); }
    }
}
