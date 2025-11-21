<?php
/*
 * Handles the backup and restore functions
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
 * @version    7.x Last Update: 2025-07-08
 * @filesource /controllers/administrate/backup.php
 */

namespace bizuno;

class administrateBackup
{
    public    $moduleID   = 'administrate';
    public    $pageID     = 'backup';
    protected $secID      = 'admin';
    protected $domSuffix  = 'Backup';
    private $update_queue = [];

    function __construct()
    {
        $this->lang = getLang($this->moduleID);
        $this->max_execution_time = 20000;
        $this->dirBackup = 'backups/';
    }

    /**
     * Page entry point for the backup methods
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        global $io;
        if (!$security = validateAccess($this->secID, 1)) { return; }
//        parent::managerMain($layout, $security, ['dom'=>'div']);
        $data = ['type'=>'divHTML', 'title'=>lang('bizuno_backup'),
            'divs'   => ['body'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbBackup'],
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'backup' => ['order'=>20,'type'=>'panel','key'=>'backup', 'classes'=>['block33']],
                    'divAtch'=> ['order'=>30,'type'=>'panel','key'=>'divAtch','classes'=>['block66']],
                    'audit'  => ['order'=>40,'type'=>'panel','key'=>'audit',  'classes'=>['block33']]]]]]],
            'toolbars'=> ['tbBackup'=>['icons'=>[
                'restore'=> ['order'=>20,'hidden'=>$security>3?false:true,'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/backup/managerRestore');"]]]]],
            'panels' => [
                'backup' => ['title'=>lang('bizuno_backup'),'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmBackup'],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['backupDesc','btnBackup']], // 'incFiles' is a later feature ???
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'audit'  => ['title'=>$this->lang['audit_log_backup'],'type'=>'divs','divs'=>[
                    'formBOF'=> ['order'=>10,'type'=>'form',  'key' =>'frmAudit'],
                    'body'   => ['order'=>30,'type'=>'fields','keys'=>['auditDesc','btnAudit','audClnDesc','dateClean','btnClean']],
                    'formEOF'=> ['order'=>90,'type'=>'html',  'html'=>"</form>"]]],
                'divAtch'=> ['type'=>'attach','defaults'=>['dgName'=>'dgBackup','path'=>$this->dirBackup,'title'=>lang('files'),'url'=>BIZUNO_URL_AJAX."&bizRt=administrate/backup/mgrRows",'ext'=>$io->getValidExt('backup')]]],
            'forms'   => [
                'frmBackup'=> ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=administrate/backup/save"]],
                'frmAudit' => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=administrate/backup/cleanAudit"]]],
            'fields'  => [
                'backupDesc'=> ['order'=>10,'html'=>$this->lang['desc_backup'],          'attr'=>['type'=>'raw']],
//              'incFiles'  => ['order'=>20,'label'=>$this->lang['desc_backup_all'],'attr'=>['type'=>'checkbox', 'value'=>'all']],
                'btnBackup' => ['order'=>30,'icon'=>'backup','label'=>lang('go'),'align'=>'right','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmBackup').submit();"]],
                'auditDesc' => ['order'=>10,'html'=>$this->lang['audit_log_backup_desc'],'attr'=>['type'=>'raw']],
                'btnAudit'  => ['order'=>20,'icon'=>'backup','label'=>lang('go'),'align'=>'right','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('administrate/backup/saveAudit');"]],
                'audClnDesc'=> ['order'=>30,'html'=>"<br /><hr />".$this->lang['desc_audit_log_clean'],'attr'=>['type'=>'raw']],
                'dateClean' => ['order'=>40,'attr'=>['type'=>'date', 'value'=>localeCalculateDate(biz_date('Y-m-d'), 0, -1)]],
                'btnClean'  => ['order'=>50,'icon'=>'next',  'label'=>lang('go'),'align'=>'right','events'=>['onClick'=>"jqBiz('body').addClass('loading'); jqBiz('#frmAudit').submit();"]]],
            'jsReady' => ['init'=>"ajaxForm('frmBackup'); ajaxForm('frmAudit');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Entry point for Bizuno db Restore page
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function managerRestore(&$layout)
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $upload_mb= min((int)(ini_get('upload_max_filesize')), (int)(ini_get('post_max_size')), (int)(ini_get('memory_limit')));
        $data     = ['type'=>'divHTML', 'title'=>lang('bizuno_restore'),
            'divs'    => ['body'=>['classes'=>['areaView'],'type'=>'divs','divs'=>[
                'toolbar'=> ['order'=>10,'type'=>'toolbar','key'=>'tbRestore'],
                'heading'=> ['order'=>15,'type'=>'html',   'html'=>"<h1>".lang('bizuno_restore')."</h1>"],
                'manager'=> ['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'restore'=> ['order'=>30,'type'=>'panel','key'=>'restore','classes'=>['block66']]]]]]],
            'panels' => [
                'restore'=> ['type'=>'divs','divs'=>[
                    'dgRstr' => ['order'=>40,'type'=>'datagrid','key' =>'dgRestore'],
                    'formBOF'=> ['order'=>50,'type'=>'form',    'key' =>'frmRestore'],
                    'body'   => ['order'=>60,'type'=>'fields',  'keys'=>['txtFile','fldFile','btnFile'],
                    'formEOF'=> ['order'=>90,'type'=>'html',    'html'=>"</form>"]]]]],
            'toolbars'=> ['tbRestore' => ['icons'=>['cancel'=>['order'=>10,'events'=>['onClick'=>"bizPanelReload('bizBody', 'administrate/backup/manager');"]]]]],
            'datagrid'=> ['dgRestore' => $this->dgRestore('dgRestore')],
            'forms'   => ['frmRestore'=> ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=administrate/backup/uploadRestore",'enctype'=>"multipart/form-data"]]],
            'fields'  => [
                'txtFile'=> ['order'=>10,'html'=>lang('msg_io_upload_select')." ".sprintf(lang('max_upload'), $upload_mb)."<br />",'attr'=>['type'=>'raw']],
                'fldFile'=> ['order'=>15,'attr'=>['type'=>'file']],
                'btnFile'=> ['order'=>20,'events'=>['onClick'=>"jqBiz('#frmRestore').submit();"],'attr'=>['type'=>'button','value'=>lang('upload')]]],
            'jsReady' => ['init'=>"ajaxForm('frmRestore');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     * Load stored backup files through AJAX call
     * @param array $layout - structure coming in
     */
    public function mgrRows(&$layout=[])
    {
        global $io;
        $rows   = $io->fileReadGlob($this->dirBackup, $io->getValidExt('backup'));
        $totRows= sizeof($rows);
        $rowNum = clean('rows',['format'=>'integer','default'=>10],'post');
        $pageNum= clean('page',['format'=>'integer','default'=>1], 'post');
        $output = array_slice($rows, ($pageNum-1)*$rowNum, $rowNum);
        $layout = array_replace_recursive($layout, ['type'=>'raw', 'content'=>json_encode(['total'=>$totRows, 'rows'=>$output])]);
    }

    /**
     * This method executes a backup and download
     * @todo add include files capability
     * @param array $layout - structure coming in
     * @return Doesn't return if successful, returns messageStack error if not.
     */
    public function save(&$layout)
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        // @todo - Need to implement this, perhaps make sure file size is not too big or drop option
//      $incFiles = clean('data', 'text', 'post');
        // set execution time limit to a large number to allow extra time
        if (ini_get('max_execution_time') < $this->max_execution_time) { set_time_limit($this->max_execution_time); }
        $filename = clean(getModuleCache('bizuno', 'settings', 'company', 'id'), 'filename').'-'.biz_date('Ymd-His');
        if (!dbDump($filename, $this->dirBackup)) { return msgAdd(lang('err_io_write_failed'), 'trap'); }
        msgLog($this->lang['msg_backup_success']);
        msgAdd($this->lang['msg_backup_success'], 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgBackup');"]]);
    }

    /**
     * This method backs up the audit log database sends the result to the backups folder.
     * @param array $layout - structure coming in
     * @return json to reload grid
     */
    public function saveAudit(&$layout)
    {
        if (!$security = validateAccess($this->secID, 2)) { return; }
        if (!dbDump('bizuno_log-'.biz_date('Ymd-His'), $this->dirBackup, BIZUNO_DB_PREFIX.'audit_log')) { return msgAdd(lang('err_io_write_failed')); }
        msgAdd($this->lang['msg_backup_success'], 'success');
        $layout = array_replace_recursive($layout,['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgBackup');"]]);
    }

    /**
     * Cleans old entries from the audit_log table prior to user specified data
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function cleanAudit(&$layout)
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $toDate = clean('dateClean', ['format'=>'date', 'default'=>localeCalculateDate(biz_date('Y-m-d'), 0, -1)], 'post'); // default to -1 month from today
        $data['dbAction'] = [BIZUNO_DB_PREFIX."audit_log"=>"DELETE FROM ".BIZUNO_DB_PREFIX."audit_log WHERE date<='$toDate 23:59:59'"];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     * Method to receive a file to upload into the backup folder for db restoration
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function uploadRestore(&$layout)
    {
        global $io;
        $io->uploadSave('fldFile', $this->dirBackup);
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizGridReload('dgRestore');"]]);
    }

    /**
     * This method restores a .gzip db backup file to the database, replacing the current tables
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function saveRestore(&$layout)
    {
        if (!$security = validateAccess($this->secID, 4)) { return; }
        $dbFile = clean('data', 'filename', 'get');
        if (!file_exists(BIZUNO_DATA.$dbFile)) { return msgAdd("Bad filename passed! ".BIZUNO_DATA.$dbFile); }
        ini_set('memory_limit','1024M');
        set_time_limit(3600); // One hour
        if ($this->dbRestore($dbFile)) { 
            bizClrCookie('bizunoSession'); // forces a logout
            bizCacheExpClear();
            $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"location.href='".BIZUNO_URL_PORTAL."'"]]);
        } else {
            msgAdd("There was an error during the restore. Most likely nothing was done, sorry.");
        }
    }

    private function dbRestore($filename)
    {
        msgDebug("\nEntering dbRestore with path = BIZUNO_DATA/$filename");
        $output = $retValue = null;
        $bizCreds= getUserCache('business');
        $dbFile  = BIZUNO_DATA.$filename;
        $dbHost  = BIZUNO_DB_CREDS['host'];
        $dbName  = !empty(BIZUNO_DB_CREDS['name']) ? BIZUNO_DB_CREDS['name'] : (!empty($bizCreds['bizDB']) ? $bizCreds['bizDB'] : '');
        $dbUser  = BIZUNO_DB_CREDS['user'];
        $dbPass  = BIZUNO_DB_CREDS['pass'];
        if (empty($dbName) || empty($dbUser) || empty($dbPass)) { return msgAdd('invalid_credentials'); }
        $ext     = strtolower(pathinfo($dbFile, PATHINFO_EXTENSION));
        msgDebug("\nLooking for how to process extension: $ext");
        if (in_array($ext, ['sql'])) { // raw sql in text format
            $cmd = "mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName < $dbFile";
        } elseif (in_array($ext, ['zip'])) { // in zip format
           $cmd = "unzip -p $dbFile | mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName";
        } else { // assume gz format
            $cmd = "gunzip < $dbFile | mysql --host=$dbHost --user=$dbUser --password=$dbPass --default_character_set=utf8 --database=$dbName";
        }
        msgDebug("\n Executing command: $cmd");
        if (!function_exists('exec')) { return msgAdd("php exec is disabled, the restore cannot be achieved this way!"); }
        $result = exec($cmd, $output, $retValue); // start the restore, script may time out but restore will continue until it's finished
        msgDebug("\n returned result: ".print_r($result, true));
//      msgDebug("\n returned output: ".print_r($output, true)); // echoes the uncompressed sql, VERY LONG makes large debug files!
        msgDebug("\n returned status value: " .print_r($retValue, true));
        return (!empty($retValue)) ? false : true;
    }
    
    /**
     * Grid to list files to restore
     * @param string $name - HTML element id of the grid
     * @return array $data - grid structure
     */
    private function dgRestore($name='dgRestore')
    {
        $data = ['id'=>$name, 'title'=>lang('files'),
            'attr'   => ['idField'=>'title', 'url'=>BIZUNO_URL_AJAX."&bizRt=administrate/backup/mgrRows"],
            'columns'=> [
                'action'=> ['order'=> 1,'label'=>lang('action'),  'attr'=>['width'=>60],
                    'events' =>['formatter'=>"function(value,row,index) { return {$name}Formatter(value,row,index); }"],
                    'actions'=> [
                        'start' => ['order'=>30,'icon'=>'import','label'=>lang('restore'),'events'=>['onClick'=>"if(confirm('".$this->lang['msg_restore_confirm']."')) { jqBiz('body').addClass('loading'); jsonAction('administrate/backup/saveRestore', 0, '{$this->dirBackup}idTBD'); }"]],
                        'trash' => ['order'=>70,'icon'=>'trash','events'=>['onClick'=>"if(confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/main/fileDelete','$name','{$this->dirBackup}idTBD');"]]]],
                'title' => ['order'=>10,'label'=>lang('filename'),'attr'=>['width'=>200,'align'=>'center','resizable'=>true]],
                'size'  => ['order'=>20,'label'=>lang('size'),    'attr'=>['width'=>100,'align'=>'center','resizable'=>true]],
                'date'  => ['order'=>30,'label'=>lang('date'),    'attr'=>['width'=>100,'align'=>'center','resizable'=>true]]]];
        return $data;
    }
}
