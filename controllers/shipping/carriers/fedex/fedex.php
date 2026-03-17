<?php
/*
 * Shipping extension for Federal Express - Manager
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
 * @version    7.x Last Update: 2026-03-20
 * @filesource /controllers/shipping/carriers/fedex/manager.php
 *
 * FedEx Developer Site: https://www.fedex.com/us/developer/web-services/process.html?tab=tab1
 */

namespace bizuno;

define ('FEDEX_TRACKING_URL', 'https://www.fedex.com/fedextrack/?trknbr=TRACKINGNUM');

bizAutoLoad(dirname(__FILE__).'/common.php', 'fedexCommon');
bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/shipping/functions.php', 'viewCarrierServices', 'function');

class fedex extends fedexCommon
{
    public  $moduleID  = 'shipping';
    public  $methodDir = 'carriers';
    private $frtCollect= []; // used to aggregate Freight Collect by Invoice number
    private $reconcile_path;

    function __construct()
    {
        parent::__construct();
        $tabImage = BIZUNO_URL_FS."0/controllers/$this->moduleID/$this->methodDir/$this->code/tab_logo.png";
        $this->lang['tabTitle']= "<span class='ui-tab-image'><img src='".$tabImage."' height='30' /></span>";
        $this->reconcile_path  = "data/shipping/reconcile/$this->code/";
    }

    public function settingsStructure()
    {
        $servers  = [['id'=>'test','text'=>lang('test')],['id'=>'prod','text'=>lang('production')]];
        $printers = [['id'=>'pdf', 'text'=>lang('plain_paper', $this->moduleID)], ['id'=>'thermal', 'text'=>lang('thermal', $this->moduleID)]];
        $services = [];
        foreach ($this->options['rateCodes'] as $code) { $services[] = ['id'=>$code, 'text'=>$this->lang[$code]]; }
        return [
            'test_mode'    => ['label'=>$this->lang['test_mode'],    'position'=>'after','values'=>$servers,'attr'=>['type'=>'select','value'=>$this->settings['test_mode']]],
            'default'      => ['label'=>lang('shipping_settings_default_rate', $this->moduleID),'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['default']]],
            'order'        => ['label'=>lang('sort_order'),          'position'=>'after','attr'=>['type'=>'integer','size'=>3,'value'=>$this->settings['order']]],
            'service_types'=> ['label'=>lang('shipping_settings_default_service', $this->moduleID),'position'=>'after','values'=>$services,'attr'=>['type'=>'select','size'=>15,'multiple'=>'multiple','format'=>'array','value'=>$this->settings['service_types']]],
            'acct_number'  => ['label'=>$this->lang['acct_number'],  'position'=>'after','attr'=>['value'=>$this->settings['acct_number']]],
            'rest_api_key' => ['label'=>lang('rest_api_key_lbl', $this->moduleID),'position'=>'after','attr'=>['size'=>48,'value'=>$this->settings['rest_api_key']]],
            'rest_secret'  => ['label'=>lang('rest_secret_lbl', $this->moduleID), 'position'=>'after','attr'=>['size'=>48,'value'=>$this->settings['rest_secret']]],
            'ltl_acct_num' => ['label'=>$this->lang['ltl_acct_num'], 'position'=>'after','attr'=>['value'=>$this->settings['ltl_acct_num']]],
            'ltl_class'    => ['label'=>$this->lang['def_ltl_class'],'position'=>'after','values'=>viewKeyDropdown($this->options['LTLClasses']),'attr'=>['type'=>'select','value'=>$this->settings['ltl_class']]],
            'ltl_desc'     => ['label'=>$this->lang['def_ltl_desc'], 'position'=>'after','attr'=>['value'=>$this->settings['ltl_desc']]],
            'bill_hq'      => ['label'=>lang('bill_hq_lbl', $this->moduleID),  'position'=>'after','attr'=>['type'=>'selNoYes','value'=>$this->settings['bill_hq']]],
            'gl_acct_c'    => ['label'=>lang('gl_shipping_c_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_c",'value'=>$this->settings['gl_acct_c']]],
            'gl_acct_v'    => ['label'=>lang('gl_shipping_v_lbl', $this->moduleID),'position'=>'after','attr'=>['type'=>'ledger','id'=>"{$this->code}_gl_acct_v",'value'=>$this->settings['gl_acct_v']]],
            'max_weight'   => ['label'=>$this->lang['max_weight'],   'position'=>'after','attr'=>['type'=>'integer','size'=>3,'maxlength'=>3,'value'=>$this->settings['max_weight']]],
            'sp_hub'       => ['label'=>$this->lang['sp_hub'],       'position'=>'after','attr'=>['size'=>35,'value'=>$this->settings['sp_hub']]],
            'max_sp_weight'=> ['label'=>$this->lang['max_sp_weight'],'position'=>'after','attr'=>['size'=>35,'value'=>$this->settings['max_sp_weight']]],
            'recon_fee'    => ['label'=>$this->lang['recon_fee'],    'position'=>'after','styles'=>['text-align'=>'right'],'attr'=>['type'=>'float', 'size'=>10,'value'=>$this->settings['recon_fee']]],
            'recon_percent'=> ['label'=>$this->lang['recon_percent'],'position'=>'after','styles'=>['text-align'=>'right'],'attr'=>['type'=>'float', 'size'=> 5,'value'=>$this->settings['recon_percent']]],
            'printer_type' => ['label'=>$this->lang['printer_type'], 'position'=>'after','values'=>$printers,'attr'=>['type'=>'select','value'=>$this->settings['printer_type']]],
            'printer_name' => ['label'=>$this->lang['printer_name'], 'position'=>'after','attr'=>['value'=>$this->settings['printer_name']]],
            'label_pdf'    => ['label'=>$this->lang['label_pdf'],    'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_pdf']]],
            'label_thermal'=> ['label'=>$this->lang['label_thermal'],'position'=>'after','values'=>$this->options['paperTypes'],'attr'=>['type'=>'select','value'=>$this->settings['label_thermal']]]];
    }

    public function settingSave()
    {
        msgDebug("\nEntering FedEx settingsSave");
        $meta    = dbMetaGet(0, "methods_{$this->methodDir}");
        $metaIdx = metaIdxClean($meta);
        $srvTypes= [];
        $defs    = explode(':', $this->defaults['service_types']);
        foreach ($defs as $type) { // Resequence service types to fix order from select to match default order
            if (strpos($meta[$this->code]['settings']['service_types'], $type)!==false) { $srvTypes[] = $type; }
        }
        $meta[$this->code]['settings']['service_types'] = implode(':', $srvTypes);
        msgDebug("\nResequenced service types = ".print_r($meta[$this->code]['settings']['service_types'], true));
        $meta[$this->code]['settings']['services'] = viewCarrierServices($this->code, $this->settings['service_types'], $this->lang);
        msgDebug("\nSetting settings:services to: ".print_r($meta[$this->code]['settings']['services'], true));
        dbMetaSet($metaIdx, "methods_{$this->methodDir}", $meta);
    }

    public function manager(&$layout=[])
    {
        $data = ['type'=>'divHTML',
            'divs'     => [
                'track' => ['order'=>20,'type'=>'panel','key'=>'pnlTrack','classes'=>['block25']],
                'recon' => ['order'=>30,'type'=>'panel','key'=>'pnlRecon','classes'=>['block50']]],
            'panels' => [
                'pnlTrack' => ['title'=>lang('track_shipments_title', $this->moduleID),'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',    'key' =>'frmFedExTrack'],
                    'desc'   => ['order'=>20,'type'=>'html',    'html'=>"<p>".lang('track_shipments_desc', $this->moduleID)."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields',  'keys'=>['frmFedExTrack','dateFedExTrack','btnFedExTrack']],
                    'formEOF'=> ['order'=>90,'type'=>'html',    'html'=>"</form>"]]],
                'pnlRecon' => ['title'=>lang('reconcile_bill_title', $this->moduleID),'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',    'key' =>'frmFedExRecon'],
                    'desc'   => ['order'=>20,'type'=>'html',    'html'=>"<p>".lang('reconcile_bill_desc', $this->moduleID)."</p>"],
                    'body'   => ['order'=>30,'type'=>'fields',  'keys'=>['fileFedExRecon','btnFedExRecon']],
                    'dgRecon'=> ['order'=>70,'type'=>'datagrid','key' =>"dgReconcile{$this->code}"],
                    'formEOF'=> ['order'=>90,'type'=>'html',    'html'=>"</form>"]]]],
            'forms' => [
                'frmFedExRecon' => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=shipping/reconcile/reconcileInvoice&carrier=$this->code"]],
                'frmFedExTrack' => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=shipping/track/trackBulk&carrier=$this->code"]]],
            'datagrid'=> ["dgReconcile{$this->code}"=>$this->dgReconcile("dgReconcile{$this->code}")],
            'fields' => [
                'fileFedExRecon'=> ['attr'=>['type'=>'file']],
                'btnFedExRecon' => ['icon'=>'next','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmFedExRecon').submit();"]],
                'dateFedExTrack'=> ['attr'=>['type'=>'date','value'=>localeCalculateDate(biz_date('Y-m-d'), -7)]],
                'btnFedExTrack' => ['icon'=>'next','events'=>['onClick'=>"jqBiz('#frmFedExTrack').submit();"]],
                'btnFedExShip'  => ['icon'=>'next','events'=>['onClick'=>"windowEdit('shipping/ship/labelMain&rID=0&data=$this->code', 'winLabel', '".$this->lang['title']."', 800, 700);"]]],
            'jsReady' => ["init{$this->code}"=>"ajaxDownload('frmFedExTrack'); ajaxForm('frmFedExRecon');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    public function validateAddress($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/address.php', 'fedexAddress');
        $api = new fedexAddress($this->settings, $this->options, $this->lang);
        return $api->validateAddress($request);
    }

    public function rateQuote($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/rate.php', 'fedexRate');
        $api = new fedexRate($this->settings, $this->options, $this->lang);
        return $api->rateQuote($request);
    }

    public function labelGet($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'fedexShip');
        $api = new fedexShip($this->settings, $this->options, $this->lang);
        return $api->labelGet($request);
    }

    public function labelDelete($tracking_number='', $method='GND', $store_id=0) {
        bizAutoLoad(dirname(__FILE__).'/ship.php', 'fedexShip');
        $api = new fedexShip($this->settings, $this->options, $this->lang);
        return $api->labelDelete($tracking_number, $method, $store_id);
    }

    public function trackBulk($request=[]) {
        bizAutoLoad(dirname(__FILE__).'/tracking.php', 'fedexTracking');
        $api = new fedexTracking($this->settings, $this->options, $this->lang);
        return $api->trackBulk($request);
    }

    /**
     * This function takes a csv file downloaded from FedEx and reconciles the invoice to the shipping log
     * The format must be from the (Flat File) Invoice Summary format found on the Download link from FedEx My Account page.
     */
    public function reconcileInvoice(&$layout)
    {
        global $io;
        msgDebug("\n Starting to reconcile bill!");
        $issues  = ['NoRec'=>[],'TooMany'=>[],'OverQuote'=>[],'OverInv'=>[], 'Dups'=>[]];
        $metaVals= $refIDs = [];
        $count   = 0;
        $inv_num = $stmt_num = $inv_date = '';
        if (!$io->validateUpload('fileFedExRecon', '', ['csv','txt'])) { msgAdd("File not found!"); return; }
        $csv     = array_map('str_getcsv', file($_FILES['fileFedExRecon']['tmp_name']));
        $head    = array_shift($csv); // pull the header row
        if ($head[0]<>'Consolidated Account Number') { return msgAdd("This doesn't look like the correct file. Please check your csv file and resubmit!"); }
        dbTransactionStart();
        foreach ($csv as $row) {
            $record   = $this->fedExParse($row, $head); // since FedEx writes crappy software we need to parse special!
            msgDebug("\nRead row = ".print_r($record, true));
            $ref_num  = $record['Original Customer Reference'];
            $refParts = explode('-', $ref_num, 2);
            $inv_num  = $refParts[0];
            $pkg_num  = isset($refParts[1]) ? intval($refParts[1])-1 : 0;
            $extraRef = !empty($record['Original Ref#2'])           ? ", Ref #2: {$record['Original Ref#2']}"           : "";
            $extraRef.= !empty($record['Original Ref#3/PO Number']) ? ", Ref #3: {$record['Original Ref#3/PO Number']}" : "";
            $payor_id = $record['Payor'];
            $track_num= trim($record['Ground Tracking ID Prefix'].' '.$record['Express or Ground Tracking ID']);
            $rcv_name = $record['Recipient Company'];
            $ship_name= $record['Shipper Company'];
            $ship_date= $record['Shipment Date'];
            $cost     = clean($record['Net Charge Amount'], 'currency');
            if (!$payor_id) { continue; } // weekly service charge and other non-shipment related.
            if (empty($stmt_num)) { $stmt_num= $record['Invoice Number']; } // Invoice Number
            if (empty($inv_date)) { $inv_date= $record['Invoice Date']; }   // Invoice Date
            if ($record['Ground Service']=='Ground, Prepaid, Return Print Label') {
                $this->getCollect($record);
                continue;
            }
            msgDebug("\nLooking for invoice num = $inv_num");
            if (!empty($inv_num)) { // get the meta
                $stmt = dbGetResult("SELECT journal_main.id AS id, journal_meta.id AS metaID, journal_meta.ref_id, primary_name_b, freight, purch_order_id, meta_value
                    FROM ".BIZUNO_DB_PREFIX."journal_main JOIN journal_meta ON journal_main.id=journal_meta.ref_id 
                    WHERE invoice_num='$inv_num' AND journal_id IN (12, 13) AND meta_key='shipment'");
                $jMain   = $stmt ? $stmt->fetch(\PDO::FETCH_ASSOC) : [];
                msgDebug("\nRead invoice data from db = ".print_r($jMain, true));
                if (empty($jMain)) {
                    $issues['NoRec'][] = sprintf($this->lang['recon_no_records'], $ship_date, $ref_num, $track_num, $ship_name, $rcv_name, $cost);
                    continue;
                }
                if (empty($metaVals['r'.$jMain['metaID']])) { $metaVals['r'.$jMain['metaID']] = json_decode($jMain['meta_value'], true); }
                msgDebug("\nRead pkg_num = $pkg_num and metaVals from db = ".print_r($metaVals['r'.$jMain['metaID']], true));
                $refIDs['r'.$jMain['metaID']]  = $jMain['ref_id'];
                $estCost = !empty($metaVals['r'.$jMain['metaID']]['packages']['rows'][$pkg_num]['cost']) ? $metaVals['r'.$jMain['metaID']]['packages']['rows'][$pkg_num]['cost'] : 0;
                msgDebug("\nRead estCost = ".print_r($estCost, true));
                if (empty($metaVals['r'.$jMain['metaID']]['actual_cost'])) { $metaVals['r'.$jMain['metaID']]['actual_cost'] = 0; }
                $metaVals['r'.$jMain['metaID']]['actual_cost'] += $cost;
            } else {
                $issues['NoRec'][] = sprintf($this->lang['recon_no_records'], $ship_date, $ref_num, $track_num, $ship_name, $rcv_name, $cost);
                continue;
            }
            $estimate = ($estCost + $this->settings['recon_fee']) * (1 + $this->settings['recon_percent']/100);
            msgDebug("\ncost = $cost and adjusted estimate = $estimate");
            if ($cost > $estimate) {
                $extra = ". Customer: {$jMain['primary_name_b']}".$extraRef;
                $issues['OverQuote'][] = sprintf($this->lang['recon_cost_over'], $ship_date, $ref_num, $track_num, $cost, $estCost).$extra;
            }
            $custInv = !empty($jMain['freight']) ? $jMain['freight'] : 0;
//          $quoteplus= ($custInv + $this->settings['recon_fee']) * (1 + $this->settings['recon_percent']/100);
            msgDebug("\nRead from customer invoice freight charge: $custInv and FedEx Net Charge: $cost");
            if ($cost > $custInv) {
                $extra = ". Customer: {$jMain['primary_name_b']}".$extraRef;
                $issues['OverInv'][] = sprintf($this->lang['recon_cost_over_inv'], $ship_date, $ref_num, $track_num, $cost, $custInv).$extra;
            }
            if (!empty($metaVals['r'.$jMain['metaID']]['packages']['rows'][$pkg_num]['reconciled'])) { $issues['Dups'][] = $inv_num; }
            else { $metaVals['r'.$jMain['metaID']]['packages']['rows'][$pkg_num]['reconciled'] = 1; }
            $count++;
        }
        if (!empty($metaVals)) { 
            foreach ($metaVals as $key => $metaVal) {
                $rID   = substr($key, 1);
                $refID = $refIDs[$key];
                msgDebug("\nReady to write to rID = $rID, refID = $refID, with values: ".print_r($metaVal, true));
//                dbMetaSet($rID, 'shipment', $metaVal, 'journal', $refID);
            }
        }
        $output = $this->viewRecon($stmt_num, $inv_date, $count, $issues);
        // write file
        msgDebug("\nWriting to file: ".$this->reconcile_path."fedex-$inv_num-$inv_date.txt values: $output");
        $io->fileWrite($output, $this->reconcile_path."fedex-$stmt_num-$inv_date.txt");
        dbTransactionCommit();
        $layout = array_replace_recursive($layout, ['content'=>  ['action'=>'eval', 'actionData'=>"jqBiz('#dgReconcile{$this->code}').datagrid('reload');"]]);
    }

    private function getCollect($record)
    {
        $invNum = explode('-', $record['Original Customer Reference']);
        if (!isset($this->frtCollect[$invNum[0]])) { $this->frtCollect[$invNum[0]] = ['total'=>0, 'ref1'=>'', 'ref2'=>'', 'ref3'=>'']; }
        $this->frtCollect[$invNum[0]]['total'] += $record['Net Charge Amount'];
        $this->frtCollect[$invNum[0]]['ref1'] = $record['Original Customer Reference'];
        $this->frtCollect[$invNum[0]]['ref2'] = $record['Original Ref#2'];
        $this->frtCollect[$invNum[0]]['ref3'] = $record['Original Ref#3/PO Number'];
    }

    private function viewRecon($stmt_num, $inv_date, $count, $issues=[])
    {
        $output  = $this->lang['recon_title'].biz_date('Y-m-d')."\n";
        $output .= sprintf($this->lang['recon_intro'], $stmt_num, $inv_date)."\n\n";
        $output .= "NO RECORDS FOUND\n";
        $output .= implode("\n", $issues['NoRec'])."\n";
        $output .= "\n\nTOO MANY RECORDS FOUND\n";
        $output .= implode("\n", $issues['TooMany'])."\n";
        $output .= "\n\nCHARGED MORE THAN QUOTED BY FEDEX\n";
        $output .= implode("\n", $issues['OverQuote'])."\n";
        $output .= "\n\nINVOICE MORE THAT CHARGED CUSTOMER\n";
        $output .= implode("\n", $issues['OverInv'])."\n";
        $output .= "\n\n";
        if (!empty($this->frtCollect)) {
            $output .= "FREIGHT COLLECT SHIPMENTS";
            foreach ($this->frtCollect as $inv => $vals) {
                $name   = dbGetValue(BIZUNO_DB_PREFIX.'journal_main', 'primary_name_b', "invoice_num='$inv' AND journal_id IN (12,13)");
                $extra  = ". Customer: $name. Ref #1: {$vals['ref1']}, Ref #2 {$vals['ref2']}, Ref #3: {$vals['ref3']}";
                $output.= "\nReturn services freight charge for invoice # $inv was: ".viewFormat($vals['total'], 'currency').$extra;
            }
            $output .= "\n";
        }
        $output .= "\n".sprintf($this->lang['recon_summary'], $count)."\n";
        return $output;
    }

    private function dgReconcile($name)
    {
        return ['id'=>$name, 'title'=>lang('history'),
            'attr'   => ['idField'=>'title', 'url'=>BIZUNO_URL_AJAX."&bizRt=shipping/reconcile/reconcileList&carrier=$this->code"],
            'columns'=> [
                'action' => ['order'=>1,'label'=>lang('action'),'events'=>['formatter'=>"function(value,row,index) { return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'download'=>['order'=>30,'icon'=>'download','events'=>['onClick'=>"jqBiz('#attachIFrame').attr('src',bizunoAjax+'&bizRt=bizuno/main/fileDownload&pathID=$this->reconcile_path&fileID=idTBD');"]],
                        'trash'   =>['order'=>70,'icon'=>'trash',   'events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/main/fileDelete','$name','{$this->reconcile_path}idTBD');"]]]],
                'title'=> ['order'=>10,'label'=>lang('filename'),'attr'=>['align'=>'center','resizable'=>true]],
                'size' => ['order'=>20,'label'=>lang('size'),    'attr'=>['align'=>'right', 'resizable'=>true]],
                'date' => ['order'=>30,'label'=>lang('date'),    'attr'=>['align'=>'center','resizable'=>true]]]];
    }

    /**
     * Load reconcile history to populate the grid
     */
    public function reconcileList(&$layout)
    {
        global $io;
        $rows = $io->fileReadGlob($this->reconcile_path, ['txt']);
        msgDebug("\n Added FedEx datagrid data rows: ".print_r($rows, true));
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>sizeof($rows), 'rows'=>$rows])]);
    }

// ***************************************************************************************************************
//                                Support Functions
// ***************************************************************************************************************
    private function fedExParse($data, $head)
    {
        $output = [];
        foreach ($head as $idx => $label) {
            //"Tracking ID Charge Description","Tracking ID Charge Amount",
            switch ($label) {
                case 'Tracking ID Charge Description': // there are zero to 25 of these, same label!!!
                    $desc = $data[$idx];
                    break;
                case 'Tracking ID Charge Amount';
                    if (empty($data[$idx])) { break; }
                    $output['charges'][] = ['text'=>$desc, 'value'=>$data[$idx]];
                    break;
                default:
                    $output[trim($label)] = trim($data[$idx]);
                    break;
            }
        }
        return $output;
    }
}
