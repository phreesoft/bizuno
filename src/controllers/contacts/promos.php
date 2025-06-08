<?php
/*
 * @name Bizuno ERP - CRM Promotion Manager Extension
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
 * @version    7.x Last Update: 2025-04-24
 * @filesource /controllers/contacts/promos.php
 */

namespace bizuno;

class proCustPromos extends mgrJournal
{
    public    $moduleID  = 'proCust';
    public    $pageID    = 'promos';
    protected $secID     = 'Promotions';
    protected $domSuffix = 'Promos';
    protected $metaPrefix= 'crm_promotion';
    protected $nextRefIdx= 'next_promo_num';
    private   $blockSize = 25;  // number of emails to send in a single ajax request

    function __construct()
    {
        parent::__construct();
        $this->attachPath= getModuleCache($this->moduleID, 'properties', 'attachPath', $this->pageID);
        $this->managerSettings();
        $this->fieldStructure();
    }

    /**
     * Sets the page fields with their structure
     * @return array - page structure
     */
    private function fieldStructure()
    {
        $this->struc = [ // Props panel
//            '_rID'         => ['panel'=>'properties','order'=> 1,                                     'clean'=>'integer',  'attr'=>['type'=>'hidden', 'value'=>0]], // For common_meta
//            '_table'       => ['panel'=>'properties','order'=> 1,                                     'clean'=>'alpha_num','attr'=>['type'=>'hidden', 'value'=>'']],
//            'type'         => ['panel'=>'properties','order'=> 1,                                     'clean'=>'alpha_num','attr'=>['type'=>'select', 'value'=>''], 'values'=>viewKeyDropdown($this->faTypes)],
//            'ref_num'      => ['panel'=>'properties','order'=>10, 'label'=>$this->lang['asset_num'],  'clean'=>'cmd',      'attr'=>['value'=>'']],
//            'title'        => ['panel'=>'properties','order'=>20, 'label'=>lang('title'),             'clean'=>'text',     'attr'=>['value'=>'']],
//            'description'  => ['panel'=>'properties','order'=>30, 'label'=>lang('description'),       'clean'=>'text',     'attr'=>['value'=>'']],
//            'status'       => ['panel'=>'properties','order'=>40, 'label'=>lang('status'),            'clean'=>'alpha_num','attr'=>['type'=>'hidden', 'value'=>0],'values'=>viewKeyDropdown($this->status)],
//            'store_id'     => ['panel'=>'properties','order'=>50, 'label'=>lang('store_id'),          'clean'=>'integer',  'attr'=>['type'=>sizeof($stores)>1?'select':'hidden', 'value'=>0], 'values'=>$stores],
//            'date_acq'     => ['panel'=>'properties','order'=>60, 'label'=>$this->lang['date_acq'],   'clean'=>'dateMeta', 'attr'=>['type'=>'date',   'value'=>biz_date()]],
//            'cost'         => ['panel'=>'properties','order'=>70, 'label'=>lang('cost'),              'clean'=>'currency', 'attr'=>['type'=>'currency','value'=>'']],
//            'serial_number'=> ['panel'=>'properties','order'=>80, 'label'=>lang('serial_number'),     'clean'=>'cmd',      'attr'=>['value'=>'']],
            ];
    }
/*
'crmPromos' => ['module' => $this->moduleID, 'fields'=> [
        'id'        => ['format'=>'INT(11)',    'attr'=>"NOT NULL AUTO_INCREMENT",'comment'=>'type:hidden;tag:RecordID;order:1'],
        'title'     => ['format'=>'VARCHAR(64)','attr'=>"DEFAULT ''",             'comment'=>'tag:Title;order:10'],
        'start_date'=> ['format'=>'DATE',       'attr'=>"DEFAULT NULL",           'comment'=>'type:date;tag:StartDate;order:20'],
        'end_date'  => ['format'=>'DATE',       'attr'=>"DEFAULT NULL",           'comment'=>'type:date;tag:EndDate;order:30'],
        'subject'   => ['format'=>'VARCHAR(64)','attr'=>"DEFAULT ''",             'comment'=>'tag:Subject;order:40'],
        'email_body'=> ['format'=>'TEXT',       'attr'=>"",                       'comment'=>'type:textarea;tag:EmailBody;order:80'],
        'settings'  => ['format'=>'TEXT',       'attr'=>"",                       'comment'=>'type:json;tag:Settings;order:90']],
    'keys' => 'PRIMARY KEY (id)',
    'attr' => 'ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci'],
'crmPromos_history' => ['module' => $this->moduleID, 'fields'=> [
        'id'       => ['format'=>'INT(11)',    'attr'=>"NOT NULL AUTO_INCREMENT",'comment'=>'type:hidden;tag:RecordID;order:1'],
        'title'    => ['format'=>'VARCHAR(64)','attr'=>"DEFAULT ''",             'comment'=>'tag:Title;order:10'],
        'send_date'=> ['format'=>'DATE',       'attr'=>"DEFAULT NULL",           'comment'=>'type:date;tag:Date;order:20']],
    'keys' => 'PRIMARY KEY (id)',
    'attr' => 'ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci'],
 */
    /**
     *
     * @param type $layout
     * @return type
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess($this->moduleID, 1)) { return; }
        $data = ['title'=>$this->lang['title'],
            'divs'     => [$this->moduleID=>['order'=>30,'type'=>'accordion','key'=>'accPromos']],
            'accordion'=> ['accPromos'=>['divs'=>[
                'divPromosMgr'   => ['order'=>30,'label'=>$this->lang['promos_mgr'],'type'=>'divs','divs'=>[
                    'formBOF' => ['order'=>15,'type'=>'form',    'key' =>'frmPromoMgr'],
                    'fields'  => ['order'=>40,'type'=>'html',    'html'=>$this->getViewMgr()],
                    'datagrid'=> ['order'=>60,'type'=>'datagrid','key' =>'dgHistory'],
                    'formEOF' => ['order'=>85,'type'=>'html',    'html'=>"</form>"]]],
                'divPromosRows'  => ['order'=>50,'label'=>$this->lang['promos_list'],'type'=>'datagrid','key'=>'dgPromos'],
                'divPromosDetail'=> ['order'=>70,'label'=>lang('details'),'type'=>'html', 'html'=>$this->lang['desc_promo_empty']]]]],
            'forms'    => ['frmPromoMgr'=>['attr'=>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/promos/sendMain"]]],
            'datagrid' => [
                'dgPromos' => $this->dgPromos ('dgPromos',  $security),
                'dgHistory'=> $this->dgHistory('dgHistory')],
            'jsBody'   => ['init'=>$this->getViewMgrJS()],
            'jsReady'  => ['init'=>"ajaxForm('frmPromoMgr');"]];
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }

    /**
     *
     * @return type
     */
    private function getViewMgr()
    {
        $validTitles = dbGetMulti(BIZUNO_DB_PREFIX.$this->moduleID, "start_date <= '".biz_date('Y-m-d')."' AND end_date >= '".biz_date('Y-m-d')."'");
        $titles = [['id'=>'0', 'text'=>lang('select')]];
        foreach ($validTitles as $row) { $titles[] = ['id'=>$row['id'], 'text'=>$row['title']]; }
        $options = [['id'=>'newsletter', 'text'=>$this->lang['newsletter']], ['id'=>'all', 'text'=>lang('all')]];
        return '<p>'.$this->lang['promo_desc']."</p>"
        .html5('selTitle',   ['label' => $this->lang['promos_list'],  'values'=>$titles, 'attr'=>['type'=>'select']]).'<br />'
        .html5('selOption',  ['label' => $this->lang['promos_option'],'values'=>$options,'attr'=>['type'=>'select']]).'<br />'
        .html5('senderName', ['label' => lang('from'), 'attr'=>['size'=>32, 'value'=>getModuleCache('bizuno', 'settings', 'company', 'primary_name')]]) .'<br />'
        .html5('senderEmail',['label' => lang('email'),'attr'=>['type'=>'email','value'=>getModuleCache('bizuno', 'settings', 'company', 'email')]])
        .html5('btnEmail',   ['events'=> ['onClick'=>"jqBiz('#frmPromoMgr').submit();"],'attr'=>['type'=>'button','value'=>lang('start')]]).'
<div id="divViewConvert" style="text-align:center;display:none">
    <table style="border:1px solid blue;width:500px;margin-top:50px;margin-left:auto;margin-right:auto;">
        <tr><td>'.jsLang('please_wait').'</td></tr>
        <tr><td id="convertMsg">&nbsp;</td></tr>
        <tr><td><progress id="xfrProgress"></progress></td></tr>
    </table>
</div>';
    }

    /**
     *
     * @return string
     */
    private function getViewMgrJS()
    {
        return "function xfrRequest() {
    jqBiz('#divViewConvert').show();
    jqBiz.ajax({ url:bizunoAjax+'&bizRt=$this->moduleID/promos/nextBlock', async:false, success:xfrResponse });
}
function xfrResponse(json) {
    if (json.message) displayMessage(json.message);
    jqBiz('#convertMsg').html(json.msg+' percent = '+json.percent);
    jqBiz('#xfrProgress').attr({ value:json.percent,max:100});
    if (json.percent < 100) { window.setTimeout(\"xfrRequest('')\", 500); }
    else                    { jqBiz('#dgHistory').datagrid('reload'); }
}";
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function managerRows(&$layout=[])
    {
        if (!$security = validateAccess($this->moduleID, 1)) { return; }
        $_POST['search'] = getSearch();
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgPromos','datagrid'=>['dgPromos'=>$this->dgPromos('dgPromos', $security)]]);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function managerRowsHistory(&$layout=[])
    {
        if (!$security = validateAccess($this->moduleID, 1)) { return; }
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'dgHistory','datagrid'=>['dgHistory'=>$this->dgHistory('dgHistory')]]);
    }

    /**
     *
     * @param string $name
     * @param integer $security
     * @return array
     *
     */
    private function dgPromos($name, $security=0)
    {
        $sort  = clean('sort', ['format'=>'text', 'default'=>'title'], 'post');
        $order = clean('order',['format'=>'text', 'default'=>'asc'], 'post');
        $search= getSearch();
        return ['id'=>$name, 'page'=>clean('page', ['format'=>'integer', 'default'=>1], 'post'), 'rows'=>clean('rows', ['format'=>'integer', 'default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post'),
            'attr'   => ['toolbar'=>"#{$name}Toolbar", 'idField'=>'id', 'url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/promos/managerRows"],
            'events' => ['onDblClickRow' => "function(rowIndex, rowData){ accordionEdit('accPromos', 'dgPromos', 'divPromosDetail', '".jsLang('details')."', '$this->moduleID/promos/edit', rowData.id); }"],
            'source' => [
                'tables' => [$this->moduleID => ['table'=>BIZUNO_DB_PREFIX."crmPromos"]],
                'actions' => [
                    'newPromo' =>['order'=>10,'icon'=>'new',  'events'=>['onClick'=>"accordionEdit('accPromos', 'dgPromos', 'divPromosDetail', '".jsLang('details')."', '$this->moduleID/promos/edit', 0);"]],
                    'clrSearch'=>['order'=>50,'icon'=>'clear','events'=>['onClick'=>"bizTextSet('search', ''); ".$name."Reload();"]]],
                'search' => ['title', 'subject', 'email_body'],
                'filters'=> ['search'=>['order'=>90,'label'=>lang('search'),'attr'=>['value'=>$search]]],
                'sort'   => ['s0'    =>['order'=>10, 'field'=>("$sort $order")]]],
            'columns' => [
                'id'     => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."id", 'attr'=>  ['hidden'=>true]],
                'action' => ['order'=>1, 'label'=>lang('action'),
                    'events' => ['formatter'=>"function(value,row,index){ return ".$name."Formatter(value,row,index); }"],
                    'actions'=> [
                        'edit' => ['order'=>30,'icon'=>'edit','label'=>lang('edit'),
                            'events'=> ['onClick' => "accordionEdit('accPromos', 'dgPromos', 'divPromosDetail', '".jsLang('details')."', '$this->moduleID/promos/edit', idTBD);"]],
                        'copy' => ['order'=>50,'icon'=>'copy',
                            'events' => ['onClick'=>"var title=prompt('".$this->lang['msg_copy_promo']."'); if (title!=null) jsonAction('$this->moduleID/promos/copy', idTBD, title);"]],
                        'trash'=> ['order'=>90,'icon'=>'trash','label'=>lang('delete'), 'hidden'=>$security>3?false:true,
                            'events'=> ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/promos/delete', idTBD);"]]]],
                'title'     => ['order'=>10, 'field'=>BIZUNO_DB_PREFIX."title",
                    'label' => lang('title'),'attr'=>['width'=>40,'sortable'=>true,'resizable'=>true]],
                'start_date'=> ['order'=>60, 'field'=>BIZUNO_DB_PREFIX."start_date",
                    'label' => $this->lang['start_date'],'format'=>'date','attr'=>['width'=>40,'sortable'=>true,'resizable'=>true]],
                'end_date'  => ['order'=>70, 'field'=>BIZUNO_DB_PREFIX."end_date",
                    'label' => $this->lang['end_date'],'format'=>'date','attr'=>['width'=>40,'sortable'=>true,'resizable'=>true]]]];
    }

    /**
     *
     * @param type $name
     * @return type
     */
    private function dgHistory($name)
    {
        $sort = clean('sort', ['format'=>'text', 'default'=>'send_date'], 'post');
        $order= clean('order',['format'=>'text', 'default'=>'desc'], 'post');
        return ['id'=>$name, 'page'=>clean('page', ['format'=>'integer', 'default'=>1], 'post'), 'rows'=>clean('rows', ['format'=>'integer', 'default'=>getModuleCache('bizuno', 'settings', 'general', 'max_rows')], 'post'),
            'attr'   => ['title'=>lang('history'), 'url'=>BIZUNO_AJAX."&bizRt=$this->moduleID/promos/managerRowsHistory"],
            'source' => [
                'tables'=> ["{$this->moduleID}_history"=>  ['table'=>BIZUNO_DB_PREFIX."{$this->moduleID}_history"]],
                'sort'  => ['s0'=>  ['order'=>10, 'field'=>"$sort $order"]]],
            'columns'=> [
                'id'     => ['order'=>0, 'field'=>BIZUNO_DB_PREFIX."id", 'attr'=>['hidden'=>true]],
                'send_date'=> ['order'=>20, 'field'=>BIZUNO_DB_PREFIX."crmPromos.start_date",
                    'label' => $this->lang['promo_date'],'format'=>'date','attr'=>['width'=>50,'sortable'=>true,'resizable'=>true]],
                'title'     => ['order'=>50, 'field'=>BIZUNO_DB_PREFIX."crmPromos.title",
                    'label' => lang('title'),'attr'=>['width'=>200,'sortable'=>true,'resizable'=>true]]]];
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function edit(&$layout=[])
    {
        if (!$security = validateAccess($this->moduleID, 1)) { return; }
        $rID   = clean('rID', 'integer', 'get');
        $struc = dbLoadStructure(BIZUNO_DB_PREFIX.$this->moduleID);
        if ($rID) { dbStructureFill($struc, dbGetRow(BIZUNO_DB_PREFIX."crmPromos", "id=$rID")); }
        $struc['start_date']['label']= $this->lang['start_date'];
        $struc['end_date']['label']  = $this->lang['end_date'];
        $struc['email_body']['label']= '';
        $data  = ['type'=>'divHTML',
            'divs'     => [
                'toolbar' => ['order'=>10,'type'=>'toolbar','key' =>'tbPromos'],
                'formBOF' => ['order'=>20,'type'=>'form',   'key' =>'frmPromo'],
                'fields'  => ['order'=>50,'type'=>'fields', 'keys'=>array_keys($struc)],
                'formEOF' => ['order'=>99,'type'=>'html',   'html'=>'</form>']],
            'toolbars' => ['tbPromos'=>['icons'=>['save'=>['order'=>40,'hidden'=>$security>1?false:true,'events'=>['onClick'=>"jqBiz('#frmPromo').submit();"]]]]],
            'forms'    => ['frmPromo'=>['attr' =>['type'=>'form','action'=>BIZUNO_AJAX."&bizRt=$this->moduleID/promos/save"]]],
            'fields'   => $fields,
            'jsReady'  => ['init'=>"ajaxForm('frmPromo');"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function copy(&$layout=[])
    {
        if (!$security = validateAccess('inv_mgr', 2)) { return; }
        $rID  = clean('rID', 'integer', 'get');
        $title= clean('data','text', 'get');
        $orig = dbGetRow(BIZUNO_DB_PREFIX."crmPromos", "id=$rID");
        $oID = $orig['id'];
        unset($orig['id']);
        $orig['title'] = $title;
        $nID = dbWrite(BIZUNO_DB_PREFIX."crmPromos", $orig);
        msgLog($this->lang['title'].'-'.lang('copy')." $title ($oID => $nID)");
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval','actionData'=>"jqBiz('#dgPromos').datagrid('reload'); accordionEdit('accPromos', 'dgPromos', 'divPromosDetail', '".lang('details')."', '$this->moduleID/promos/edit', $nID);"]]);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function save(&$layout=[])
    {
        $values = requestData(dbLoadStructure(BIZUNO_DB_PREFIX."crmPromos"));
        if (!$security = validateAccess($this->moduleID, $values['id']?3:2)) { return; }
        // save here
        $result = dbWrite(BIZUNO_DB_PREFIX."crmPromos", $values, $values['id']?'update':'insert', "id={$values['id']}");
        if (!$values['id']) { $values['id'] = $_POST['id'] = $result; }
        msgLog(lang($this->moduleID).' - '.lang('save')." - {$values['title']} (rID={$values['id']})");
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#accPromos').accordion('select', 1); jqBiz('#dgPromos').datagrid('reload'); jqBiz('#divPromosDetail').html('".$this->lang['desc_promo_empty']."');"]]);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function delete(&$layout=[])
    {
        if (!$security = validateAccess($this->moduleID, 4)) { return; }
        $rID = clean('rID', 'integer', 'get');
        if (!$rID) { return msgAdd('The record was not deleted, the proper id was not passed!'); }
        $title = dbGetValue(BIZUNO_DB_PREFIX."crmPromos", 'title', "id=$rID");
        msgLog($this->lang['title'].' '.lang('delete')." - $title (rID=$rID)");
        $data = [
            'content' => ['action'=>'eval', 'actionData'=>"jqBiz('#dgPromos').datagrid('reload');"],
            'dbAction'=> [BIZUNO_DB_PREFIX."crmPromos" => "DELETE FROM ".BIZUNO_DB_PREFIX."crmPromos WHERE id=$rID"]];
        $layout = array_replace_recursive($layout, $data);
    }

    /**
     *
     * @param type $layout
     * @return type
     */
    public function sendMain(&$layout=[])
    {
        $sender= clean('senderName', 'text',   'post');
        $email = clean('senderEmail','email',  'post');
        $title = clean('selTitle',   'integer','post');
        $option= clean('selOption',  'text',   'post');
        if (!$sender || !$email || !$title) { return msgAdd("You have not filled in all the fields, please check the form and resubmit!"); }
        $content = dbGetRow(BIZUNO_DB_PREFIX.$this->moduleID, "id=$title");
        $rows = $this->getDistro($option);
        setUserCron($this->moduleID, [
            'title'  => $content['title'],
            'sender' => $sender,
            'email'  => $email,
            'subject'=> $content['subject'],
            'body'   => $content['email_body'],
            'cnt'    => 0,
            'done'   => false,
            'total'  => sizeof($rows),
            'rows'   => $rows]);
        $layout = array_replace_recursive($layout, ['content' => ['action'=>'eval', 'actionData'=>"xfrRequest();"]]);
    }

    /**
     *
     * @param type $option
     * @return type
     */
    private function getDistro($option)
    {
        switch ($option) {
            case 'newsletter': $crit = "newsletter='1' AND "; break; // just newsletter field checked
            case 'all':        $crit = "";                      break; // all
            default: return []; // nothing to do here, return empty array
        }
        $result = dbGetMulti(BIZUNO_DB_PREFIX."contacts", $crit."type='m'", '', ['primary_name', 'email']);
        msgDebug("\nReturning email list = ".print_r($result, true));
        return $result;
    }

    /**
     *
     * @param type $layout
     */
    public function nextBlock(&$layout=[])
    {
        bizAutoLoad(BIZBOOKS_ROOT."model/mail.php", 'bizunoMailer');
        $cron = getUserCron($this->moduleID);
        $mailer = new bizunoMailer('', '', $cron['subject'], $cron['body'], $cron['email'], $cron['sender']);
        msgDebug("\nStarting with cnt = ".$cron['cnt']);
        $cnt = $this->blockSize;
        while ($cnt > 0 && count($cron['rows']) > 0) {
            $nextEmail = array_shift($cron['rows']);
            setUserCron($this->moduleID, $cron);
            if (!$nextEmail) { $cron['done'] = true; break; }
            $mailer->ToName  = $nextEmail['primary_name'];
            $mailer->toEmail = [['email'=>$nextEmail['email'], 'name'=>$nextEmail['primary_name']]];
            $mailer->sendMail();
            $cron['cnt']++;
            $cnt--;
        }
        if ($cron['done'] || sizeof($cron['rows']) == 0) {
            msgLog("{$this->lang['title']} {$this->lang['msg_email_complete']} - ({$cron['total']} contacts) {$cron['title']}");
            dbWrite(BIZUNO_DB_PREFIX."{$this->moduleID}_history", ['title'=>$cron['title'], 'send_date'=>biz_date('Y-m-d')]);
            $data = ['content'=>['percent'=>'100','msg'=>$this->lang['msg_email_complete']]];
            clearUserCron($this->moduleID);
        } else { // return to update progress bar and start next step
            $percent = floor(100*$cron['cnt']/$cron['total']);
            $data = ['content'=>['percent'=>$percent,'msg'=>sprintf($this->lang['msg_email_progress'], $cron['cnt'], $cron['total'])]];
        }
        $layout = array_replace_recursive($layout, $data);
    }
}
