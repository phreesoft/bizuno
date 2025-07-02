<?php
/*
 * Bizuno Tools methods
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
 * @version    7.x Last Update: 2025-06-30
 * @filesource /controllers/administrate/tools.php
 */

namespace bizuno;

class administrateTools {
    public    $moduleID   = 'administrate';
    public    $pageID     = 'tools';
    protected $secID      = 'admin';
    protected $domSuffix  = 'Tools';

    function __construct()
    {
        $this->lang        = getLang($this->moduleID);
        $this->supportEmail= defined('BIZUNO_SUPPORT_EMAIL') ? BIZUNO_SUPPORT_EMAIL : '';
        $this->reasons     = [
            'question'  => $this->lang['ticket_question'],
            'bug'       => $this->lang['ticket_bug'],
            'suggestion'=> $this->lang['ticket_suggestion'],
            'account'   => $this->lang['ticket_my_account']];
    }

    /**
     * Support ticket page structure
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function ticketMain(&$layout=[])
    {
        $meta    = getMetaContact(getUserCache('profile', 'userID'), 'user_profile');
        $reasons = [['id'=>'none', 'text' => lang('select')]];
        foreach ($this->reasons as $key => $value) { $reasons[] = ['id'=>$key, 'text'=>$value]; }
        $machines= [['id'=>'pc','text'=>'PC'],['id'=>'mac','text'=>'Mac'],['id'=>'mobile','text'=>'Mobile Phone'],['id'=>'tablet','text'=>'Tablet'],['id'=>'other','text'=>'Other (list below)']];
        $os      = [['id'=>'windows','text'=>'Windows'],['id'=>'osx','text'=>'Apple OSX'],['id'=>'ios','text'=>'iPhone IOS'],['id'=>'android','text'=>'Android'],['id'=>'other','text'=>'Other (list below)']];
        $browsers= [['id'=>'firefox','text'=>'Firefox'],['id'=>'chrome','text'=>'Chrome'],['id'=>'safari','text'=>'Safari'],['id'=>'edge','text'=>'MS Edge'],['id'=>'ie','text'=>'Internet Explorer'],['id'=>'other','text'=>'Other (list below)']];
        $fields = [
            'ticketURL'  => ['order'=>15,'break'=>true,                                   'attr'=>['type'=>'hidden','value'=>$_SERVER['HTTP_HOST']]],
            'langDesc'   => ['order'=>20,'break'=>true,'html'=>$this->lang['ticket_desc'],'attr'=>['type'=>'raw']],
            'selReason'  => ['order'=>25,'break'=>true,'label'=>lang('reason'),           'attr'=>['type'=>'select'], 'values'=>$reasons],
            'ticketUser' => ['order'=>30,'break'=>true,'label'=>lang('primary_name'),     'attr'=>['value'=>$meta['title'],'size'=>40]],
            'ticketEmail'=> ['order'=>35,'break'=>true,'label'=>lang('email'),            'attr'=>['value'=>getUserCache('profile', 'email'),'size'=>60]],
            'ticketDesc' => ['order'=>40,'break'=>true,'label'=>lang('description'),      'attr'=>['type'=>'textarea','rows'=>8,'cols'=>60]],
            'selMachine' => ['order'=>45,'break'=>true,'label'=>lang('Machine'),          'attr'=>['type'=>'select'],'values'=>$machines],
            'selOS'      => ['order'=>50,'break'=>true,'label'=>lang('OS'),               'attr'=>['type'=>'select'],     'values'=>$os],
            'selBrowser' => ['order'=>55,'break'=>true,'label'=>lang('Browser'),          'attr'=>['type'=>'select'],'values'=>$browsers],
            'ticketPhone'=> ['order'=>60,'break'=>true,'label'=>lang('telephone')],
            'ticketFile' => ['order'=>65,'label'=>$this->lang['ticket_attachment'],       'attr'=>['type'=>'file']], // break is auto-removed
            'ticketPhone'=> ['order'=>70,'html'=>"<br />",'attr'=>['type'=>'raw']],
            'btnSubmit'  => ['order'=>75,'events'=>['onClick'=>"jqBiz('#frmTicket').submit();"],'attr'=>['type'=>'button','value'=>lang('submit')]]];
        $data = ['type'=>'page','title'=>lang('support'),
            'divs'  => ['tcktMain'=>['order'=>50,'type'=>'divs','divs'=>[
                'head'   => ['order'=>10,'type'=>'html',  'html'=>"<h1>".lang('support')."</h1>"],
                'formBOF'=> ['order'=>15,'type'=>'form',  'key' =>'frmTicket'],
                'body'   => ['order'=>50,'type'=>'fields','keys'=>array_keys($fields)],
                'formEOF'=> ['order'=>85,'type'=>'html',  'html'=>"</form>"]]]],
            'forms' => ['frmTicket'=>['attr'=>['type'=>'form','method'=>'post','action'=>BIZUNO_AJAX."&bizRt=administrate/tools/ticketSave",'enctype'=>"multipart/form-data"]]],
            'fields'=> $fields];
        $layout = array_replace_recursive($layout, viewMain(), $data);

    }

    /**
     * Support ticket emailed to Bizuno BizNerds
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function ticketSave(&$layout=[])
    {
        global $io;
        $user   = clean('ticketUser', 'text', 'post');
        $email  = clean('ticketEmail','text', 'post');
        $url    = clean('ticketURL',  'text', 'post');
        $tel    = clean('ticketPhone','text', 'post');
        $type   = clean('selReason',  'text', 'post');
        $box    = clean('selMachine', 'text', 'post');
        $os     = clean('selOS',      'text', 'post');
        $brwsr  = clean('selBrowser', 'text', 'post');
        $msg    = str_replace("\n", '<br />', clean('ticketDesc', 'text', 'post'));
        $bizName= getModuleCache('bizuno', 'settings', 'company', 'primary_name');
        $subject= "Support Ticket: $bizName - $user ($email)";
        $message= "$msg<br /><br />Reason: $type<br />Phone: $tel<br />Ref: $url ($box; $os; $brwsr)<br />";
        if (empty($this->supportEmail)) { return msgAdd("You do not have a support email address defined for your business , Please visit the PhreeSoft website for support."); }
        $toName = defined('BIZUNO_SUPPORT_NAME') ? BIZUNO_SUPPORT_NAME : $this->supportEmail;
        msgDebug("\nfiles array: ".print_r($_FILES['ticketFile'], true));
        $mail   = new bizunoMailer($this->supportEmail, $toName, $subject, $message, $email, $user);
        if (!empty($_FILES['ticketFile']['name'])) { if ($io->validateUpload('ticketFile', '', $io->getValidExt('file'))) {
            $mail->attach($_FILES['ticketFile']['tmp_name'], $_FILES['ticketFile']['name']);
        } }
        $_POST['msgFrom'] = 'user'; // Forces the message to be send from users email account (if using gMail)
        $mail->sendMail();
        msgAdd("Your email has been sent to the PhreeSoft Support team. We'll be in contact with you shortly.", 'success');
        $this->ticketMain($layout);
    }

    /**
     * This function extends the PhreeBooks module close fiscal year function to handle Bizuno operations
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function fyCloseHome(&$layout=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $html  = "<p>"."Closing the fiscal year for the Bizuno module consist of deleting audit log entries during or before the fiscal year being closed. "
                . "To prevent the these entries from being removed, check the box below."."</p>";
        $html .= html5('bizuno_keep', ['label' => 'Do not delete audit log entries during or before this closing fiscal year', 'position'=>'after','attr'=>['type'=>'checkbox','value'=>'1']]);
        $layout['tabs']['tabFyClose']['divs'][$this->moduleID] = ['order'=>50,'label'=>$this->lang['title'],'type'=>'html','html'=>$html];
    }

    /**
     * Hook to PhreeBooks Close FY method, adds tasks to the queue to execute AFTER PhreeBooks processes the journal
     */
    public function fyClose()
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $skip = clean('bizuno_keep', 'boolean', 'post');
        if ($skip) { return; } // user wants to keep all records, nothing to do here, move on
        $cron = getUserCron('fyClose');
        $cron['taskClose'][] = ['mID'=>$this->moduleID];
        setUserCron('fyClose', $cron);
    }

    /**
     * continuation of fiscal year close, db purge and old folder purge, as necessary
     * @return string
     */
    public function fyCloseNext($settings=[], &$cron=[])
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $endDate = $cron['fyEndDate'];
        if (!$endDate) { return; }
        $dateFull = $endDate.' 23:59:59';
        $cnt = dbGetValue(BIZUNO_DB_PREFIX.'audit_log', 'COUNT(*) AS cnt', "`date`<='$dateFull'", false);
        $cron['msg'][] = "Read $cnt records to delete from table: audit_log";
        msgDebug("\nExecuting sql: DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE `date`<='$dateFull'");
        dbGetResult("DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE `date`<='$dateFull'");
        return "Finished processing audit_log table";
    }

    /**
     * Verifies the comments from the newest release match the database comments
     * @param type $verbose
     * @return type
     */
    public function repairComments($verbose=true)
    {
        // get the user map
/*      $userMap = dbMetaGet(0, '_ADMIN_ID_MAP');
        metaIdxClean($userMap);
        msgDebug("\nUser map = ".print_r($userMap, true));
        $contMap = dbMetaGet(0, '_CONTACT_MAP');
        msgDebug("\nContact map = ".print_r($contMap, true));
        metaIdxClean($contMap);
        // iterrate through the users and update the appropriate db
        foreach ($userMap as $key => $newIdx) {
            $oldIdx = substr($key, 1);
            if (!isset($contMap[$key])) { continue; }
            msgDebug("\nExecuting sql = "."UPDATE ".BIZUNO_DB_PREFIX."contacts_log SET entered_by=$newIdx WHERE entered_by={$contMap[$key]}");
            dbGetResult("UPDATE ".BIZUNO_DB_PREFIX."contacts_log SET entered_by=$newIdx WHERE entered_by={$contMap[$key]}");
        } */

        // OTHERS
        // "UPDATE ".BIZUNO_DB_PREFIX."audit_log SET user_id=$newIdx WHERE user_id=$oldIdx" // for users table ID's
        // "UPDATE ".BIZUNO_DB_PREFIX."journal_main SET admin_id=$newIdx WHERE admin_id=$oldIdx" // 
        // "UPDATE ".BIZUNO_DB_PREFIX."contacts_log SET entered_by=$newIdx WHERE entered_by={$contMap[$key]}" // for contacts able ID's
        // "UPDATE ".BIZUNO_DB_PREFIX."tnr_gp_history SET rep_id=$newIdx WHERE rep_id={$contMap[$key]}" // for contacts able ID's
        // returns meta uses user ID and it's buried in the meta

        
        
//dbMetaSet(0, '_CONTACT_MAP', $contMap);
/*      $userMap = dbMetaGet(0, '_ADMIN_ID_MAP');
        metaIdxClean($userMap);
        msgDebug("\nUser map = ".print_r($userMap, true));

        $taskMap = dbMetaGet(0, '_PROD_TASK_ID_MAP');
        metaIdxClean($taskMap);
        msgDebug("\nTask map = ".print_r($taskMap, true));
        $rows = dbMetaGet(0, 'production_steps', 'journal', '%');
        if (empty($rows)) { return msgAdd("No rows returned."); }
//      $runaway = 0;
        foreach ($rows as $meta) {
            msgDebug("\nWorking with meta = ".print_r($meta, true));
            $mRefID = $meta['_refID'];
            $metaID = metaIdxClean($meta);
            foreach ($meta as $idx => $step) {
                $meta[$idx]['task_id'] = isset($taskMap['r'.$step['task_id']]) ? $taskMap['r'.$step['task_id']] : $step['task_id'];
                $meta[$idx]['admin_id']= isset($userMap['r'.$step['admin_id']])? $userMap['r'.$step['admin_id']]: $step['admin_id'];
            }
            msgDebug("\nReady to write updated meta rID = $metaID and refID = $mRefID with values = ".print_r($meta, true));
            dbMetaSet($metaID, 'production_steps', $meta, 'journal', $mRefID);
//          $runaway++;
//          if ($runaway > 10) { break; }
        }
        msgAdd('Finished ...', 'success');  
return; */
        msgDebug("\nEntering repairComments with path to tables = ".BIZBOOKS_ROOT.'controllers/bizuno/install/tables.php');
        $tables = [];
        include(BIZBOOKS_ROOT.'controllers/bizuno/install/tables.php'); // loads $tables
        foreach ($tables as $table => $tProps) { // as defined by code
            if (!dbTableExists(BIZUNO_DB_PREFIX.$table)) { continue; }
            $stmt = dbGetResult("SHOW FULL COLUMNS FROM ".BIZUNO_DB_PREFIX."$table");
            if (!$stmt) { return msgAdd("No results for table $table! Bailing"); }
            $structure = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($structure as $field) {
                $default = in_array($field['Default'], ['CURRENT_TIMESTAMP']) ? $field['Default'] : "'{$field['Default']}'"; // don't quote mysql reserved words
                $params  = $field['Type'].' ';
                $params .= $field['Null']=='NO'      ? 'NOT NULL '         : 'NULL ';
                $params .= !empty($field['Default']) ? "DEFAULT $default " : '';
                $params .= $field['Extra']           ? $field['Extra'].' ' : '';
                $newComment = !empty($tProps['fields'][$field['Field']]['comment']) ? $tProps['fields'][$field['Field']]['comment'] : $field['Comment'];
                if ($newComment == $field['Comment']) { continue; } // if not changed, do nothing
                dbGetResult("ALTER TABLE `".BIZUNO_DB_PREFIX."$table` CHANGE `{$field['Field']}` `{$field['Field']}` $params COMMENT '$newComment'");
            }
        }
        // Now the phreeform
        // do this by loading the install tree and rebuilding it.

        if ($verbose) { msgAdd("finished!"); }
    }

    /**
     * Verifies the current database table structure matches the core application and extensions.
     * CAUTION: CAN TAKE A LONG TIME TO RUN
     * @param boolean $verbose - Returns 'Finished' if set to true when complete.
     * @return null
     */
    public function repairTables($verbose=true)
    {
        $tables = [];
        include(BIZBOOKS_ROOT.'controllers/bizuno/install/tables.php'); // loads $tables
        foreach ($tables as $table => $props) {
            $exists = !dbTableExists(BIZUNO_DB_PREFIX.$table) ? false : true;
            $fields = [];
            foreach ($props['fields'] as $field => $values) {
                $temp = ($exists ? "CHANGE `$field` " : '' ) . "`$field` ".$values['format']." ".$values['attr'];
                if (isset($values['comment'])) { $temp .= " COMMENT '".$values['comment']."'"; }
                $fields[] = $temp;
            }
            if ($exists) {
                msgDebug("\nAltering table: $table");
                $sql = "ALTER TABLE `".BIZUNO_DB_PREFIX."$table` ".implode(', ', $fields);
            } else { // add new table
                msgDebug("\nCreating table: $table");
                $sql = "CREATE TABLE IF NOT EXISTS `".BIZUNO_DB_PREFIX."$table` (".implode(', ', $fields).", ".$props['keys']." ) ".$props['attr'];
            }
            dbGetResult($sql);
        }
        if ($verbose) { msgAdd("finished!"); }
    }

    /**
     * Updates the references in the Bizuno cache with a modified values set by user in Settings
     */
    public function statusSave()
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        $tmp = getModuleCache('bizuno', 'references');
        foreach ($tmp as $key => $value) {
            $value = clean('stat_'.$value, 'alpha_num', 'post');
            if (!empty($value)) { $tmp[$key] = $value; }
        }
        setModuleCache('bizuno', 'references', '', $tmp);
        msgAdd(lang('msg_settings_saved'), 'success');
    }
}
