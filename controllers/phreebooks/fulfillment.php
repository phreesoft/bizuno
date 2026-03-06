<?php
/*
 * @name Bizuno ERP - Customer Fulfillment Extension
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
 * @filesource /controllers/phreebooks/fulfillment.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/main.php', 'phreebooksMain');

class phreebooksFulfillment {
    public  $moduleID   = 'phreebooks';
    public  $pageID     = 'fulfillment';
    private $tmpSecurity= 0;

    function __construct()
    {
        $this->settings = getModuleCache($this->moduleID, 'settings', false, false, []);
    }

    /**
     * Creates the main entry screen for this extension
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function fulfillMain(&$layout=[])
    {
        $this->overrideSecurity();
        $_GET['jID'] = 12; // Sales Journal
        compose('phreebooks', 'main', 'manager', $layout);
        unset($layout['accordion']['accJournal']['divs']['divJournalManager']);
        unset($layout['datagrid']['manager']);
        $layout['jsReady']['init'] = "jqBiz('#contactSel').combogrid({width:240,panelWidth:525,delay:500,idField:'id',textField:'primary_name_b',mode:'remote',
    url:       bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/fulfillList&jID=10',
    onSelect:  function (id, data){ jqBiz('#divJournalDetail').panel('refresh', bizunoAjax+'&bizRt=$this->moduleID/$this->pageID/fulfillEdit&jID=12&bizAction=inv&iID='+data.id); },
    columns:[[{field:'id',      hidden:true},
        {field:'primary_name_b',title:'".jsLang('primary_name')."', width:200},
        {field:'invoice_num',   title:'".jsLang('invoice_num_10')."', width:100},
        {field:'purch_order_id',title:'".jsLang('purch_order_id_10')."', width:100},
        {field:'post_date',     title:'".jsLang('date')."', width: 100}]]
});";
        $layout['jsReady']['focusField'] = "bizFocus('contactSel');";
        $html = html5('contactSel', ['label'=>lang('search')])."\n";
        $layout['accordion']['accJournal']['divs']['divJournalDetail'] = ['order'=>60, 'label'=>lang('title', $this->moduleID), 'type'=>'html','html'=>$html];
        $this->restoreSecurity();
    }

    /**
     * Grid request for data
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function fulfillList(&$layout=[])
    {
        if (!$security = validateAccess($this->pageID, 2)) { return; }
        $this->overrideSecurity();
        $ctl  = new phreeBooksMain();
        $_POST['search'] = getSearch();
        $data = $ctl->dgPhreeBooks('dgPhreeBooks', $security);
        $data['source']['filters']['closed']= ['order'=>99, 'hidden'=>true, 'sql'=>BIZUNO_DB_PREFIX."journal_main.closed='0'"];
        $data['source']['filters']['jID']['sql'] = BIZUNO_DB_PREFIX."journal_main.journal_id=10";
        $data['source']['sort'] = ['s0'=> ['order'=>10, 'field'=>BIZUNO_DB_PREFIX."journal_main.invoice_num"]];
        unset($data['source']['filters']['period']);
        $layout = array_replace_recursive($layout, ['type'=>'datagrid','key'=>'manager','datagrid'=>['manager'=>$data]]);
        $this->restoreSecurity();
    }

    /**
     * Modifies the PhreeBooks edit screen to handle the fulfillment extension changes
     * @param type $layout
     * @return modified $layout
     */
    public function fulfillEdit(&$layout=[])
    {
        if (!$security = validateAccess($this->pageID, 2)) { return; }
        $this->overrideSecurity();
        compose('phreebooks', 'main', 'edit', $layout);
        $layout['divs']['divDetail']['divs']['totals']['styles']['display'] = 'none';
        $layout['divs']['status']['html'] = '<span id="ship_method_text" style="font-size:20px;color:red">&nbsp;</span>';
        $layout['jsReady']['status'] = "jqBiz('#ship_method_text').text(jqBiz('#method_code option:selected').text());";
        $layout['datagrid']['item']['columns']['trans_code'] = ['order'=>70,'label'=>lang('notes'),'attr'=>['width'=>400,'editor'=>'text','resizable'=>true]];
        $layout['datagrid']['item']['columns']['tax_rate_id']['attr']['hidden']= 'true';
        $layout['datagrid']['item']['columns']['gl_account']['attr']['hidden'] = 'true';
        $layout['datagrid']['item']['columns']['price']['attr']['hidden']      = 'true';
        $layout['datagrid']['item']['columns']['total']['attr']['hidden']      = 'true';
        $layout['datagrid']['item']['columns']['sku']['events']['editor']      = "{type:'text'}"; // set sku editor to text instead of combo
        $layout['datagrid']['item']['columns']['qty']['events']['editor'] = "{type:'numberbox',options:{onChange:function(){ ordersCalc('qty'); totalUpdate(); } } }";
        unset($layout['datagrid']['item']['columns']['action']['actions']['trash']);
        unset($layout['datagrid']['item']['columns']['action']['actions']['price']);
        unset($layout['datagrid']['item']['events']['onBeforeEdit']); // block sku editor
        unset($layout['toolbars']['tbPhreeBooks']['icons']['payment']);
        unset($layout['toolbars']['tbPhreeBooks']['icons']['recur']);
        unset($layout['toolbars']['tbPhreeBooks']['icons']['trash']);
        unset($layout['toolbars']['tbPhreeBooks']['icons']['jSave']['child']['optPayment']);
        unset($layout['toolbars']['tbPhreeBooks']['icons']['jSave']['child']['optSaveAs']);
        unset($layout['toolbars']['tbPhreeBooks']['icons']['jSave']['child']['optMoveTo']);
        $layout['toolbars']['tbPhreeBooks']['icons']['new']['events']['onClick'] = "location.reload();";
        $layout['forms']['frmJournal']['attr']['action'] =BIZUNO_URL_AJAX."&bizRt=$this->moduleID/$this->pageID/fulfillSave&jID=12";
        $this->restoreSecurity();
    }

    /**
     *
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function fulfillSave(&$layout=[])
    {
        if (!$security = validateAccess($this->pageID, 2)) { return; }
        $this->overrideSecurity();
        compose('phreebooks', 'main', 'save', $layout);
        $xChild  = clean('xChild', 'text', 'post');
        $jsonAction = "window.location=bizunoHome+'?bizRt=$this->moduleID/$this->pageID/fulfillMain';";
        switch ($xChild) { // child screens to spawn
            case 'print':
                $formID = getDefaultFormID(12);
                $rID    = clean('rID', 'integer', 'post');
                $jsonAction .= " winOpen('phreeformOpen', 'phreeform/render/open&group=$formID&date=a&xfld=journal_main.id&xcr=equal&xmin=$rID');";
        }
        msgDebug("\nfulfillSave with xChild = $xChild and json returning: \n $jsonAction");
        if (msgErrors() === 0) { $layout['content'] = ['action'=>'eval','actionData'=>$jsonAction]; }
        $this->restoreSecurity();
    }

    /**
     * Temporarily set permission to PhreeBooks to work in that module, only if logged in and cache set
     */
    private function overrideSecurity()
    {
        $this->tmpSecurity = getUserCache('role', 'security', 'j12_mgr', false, 0);
        setSecurityOverride('j12_mgr', 2);
    }

    /**
     * Restores security for PhreeBooks Journal 4 after fulfillment activity
     */
    private function restoreSecurity()
    {
        setSecurityOverride('j12_mgr', $this->tmpSecurity);
    }
}
