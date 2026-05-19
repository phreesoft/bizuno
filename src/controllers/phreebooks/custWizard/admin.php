<?php
/*
 * @name Bizuno ERP - Customer Sale Wizard Extension
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
 * @version    7.x Last Update: 2026-03-15
 * @filesource /controllers/phreebooks/custWizard/admin.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/main.php', 'phreebooksMain');

class custWizardAdmin {
    public  $moduleID   = 'custWizard';
    private $category   = 'customers';
    private $tmpSecurity= 0;

    function __construct()
    {
        $this->settings = getModuleCache($this->moduleID, 'settings', false, false, []);
        $this->structure= [
            'prerequisites'=> ['bizuno'=>'3.0'],
            'category'     => $this->category,
            'url'          => BIZUNO_URL_PORTAL."/controllers/$this->moduleID/",
            'hooks'        => ['phreebooks'=>['main'=>['manager'=>['page'=>'admin','class'=>'custWizardAdmin','order'=>50]]]]];
    }

    /**
     * Modifications to the PhreeBooks manager for this extension
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function manager(&$layout=[])
    {
        $jID = clean('jID', 'integer', 'get');
        if (in_array($jID, [3,4,6,9,10,12])) {
            $layout['jsHead']['custWizard'] = "function custWizardEdit(jID) {
    jqBiz('#winStatus').remove();
    var title = jqBiz('#j'+jID+'_mgr').text();
    document.title = title;
    var p = jqBiz('#accJournal').accordion('getPanel', 1);
    if (p) {
        p.panel('setTitle',title);
        jqBiz('#dgPhreeBooks').datagrid('loaded');
        jqBiz('#divJournalDetail').panel({href:bizunoAjax+'&bizRt=custWizard/admin/wizardEdit&jID='+jID});
        jqBiz('#accJournal').accordion('select', title);
    }
}";
            $layout['datagrid']['manager']['source']['actions']['orderWiz'] = ['order'=>15,'icon'=>'custWizard','label'=>lang('title', $this->moduleID),'events'=>['onClick'=>"custWizardEdit($jID, 0);"]];
        }
    }

    /**
     * Modifications to the PhreeBooks edit method to handle the wizard accordions
     * @param array $layout - structure coming in
     */
    public function wizardEdit(&$layout=[])
    {
        $jID = clean('jID', 'integer', 'get');
        $this->overrideSecurity();
        compose('phreebooks', 'main', 'edit', $layout);
        $fldKeys = ['id','journal_id','so_po_ref_id','terms','override_user','override_pass','recur_id','recur_frequency','item_array','xChild','xAction','store_id',
            'purch_order_id','invoice_num','waiting','closed','terms_text','terms_edit','post_date','terminal_date','rep_id','currency','currency_rate'];
        $layout['divs']['divDetail'] = ['order'=>50, 'type'=>'accordion','key'=>'accCustWizard'];
        $layout['accordion']['accCustWizard'] = ['divs'=>[
            'billAD'   => ['order'=>20,'type'=>'divs','label'=>lang('bill_to'),'divs'=>[
                'address' => ['order'=>10,'type'=>'panel','key'=>'billAD', 'classes'=>['block25']],
                'btnNext' => ['order'=>90,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(1);"],'attr'=>['type'=>'button','value'=>lang('next')]])]]],
            'shipAD'   => ['order'=>30,'type'=>'divs','label'=>lang('ship_to'),'divs'=>[
                'btnBack' => ['order'=>10,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(0);"],'attr'=>['type'=>'button','value'=>lang('back')]])],
                'address' => ['order'=>20,'type'=>'panel','key'=>'shipAD', 'classes'=>['block25']],
                'btnNext' => ['order'=>90,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(2);"],'attr'=>['type'=>'button','value'=>lang('next')]])]]],
            'props'    => ['order'=>40,'type'=>'divs','label'=>lang('properties'),'divs'=>[
                'btnBack' => ['order'=>10,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(1);"],'attr'=>['type'=>'button','value'=>lang('back')]])."<br />"],
                'fields'  => ['order'=>30,'type'=>'panel','key'=>'props',  'classes'=>['block25']],
                'btnNext' => ['order'=>90,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(3);"],'attr'=>['type'=>'button','value'=>lang('next')]])]]],
            'dgItems'  => ['order'=>50,'type'=>'divs','label'=>lang('products'),'divs'=>[
                'btnBack' => ['order'=>10,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(2);"],'attr'=>['type'=>'button','value'=>lang('back')]])."<br />"],
                'dgItem'  => ['order'=>50,'type'=>'panel','key'=>'dgItems','classes'=>['block99']],
                'btnNext' => ['order'=>90,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(4);"],'attr'=>['type'=>'button','value'=>lang('next')]])]]],
            'totals'   => ['order'=>60,'type'=>'divs','label'=>lang('totals'),'divs'=>[
                'btnBack' => ['order'=>10,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(3);"],'attr'=>['type'=>'button','value'=>lang('back')]])."<br />"],
                'totals'  => ['order'=>40,'type'=>'panel','key'=>'totals', 'classes'=>['block25R']],
                'btnNext' => ['order'=>90,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(5);"],'attr'=>['type'=>'button','value'=>lang('next')]])]]],
            'attach'   => ['order'=>90,'type'=>'divs','label'=>lang('attachments'),'divs'=>[
                'btnBack' => ['order'=>10,'type'=>'html','html'=>html5('',['events'=>['onClick'=>"custWizardToggle(4);"],'attr'=>['type'=>'button','value'=>lang('back')]])."<br />"],
                'attach'  => ['order'=>90,'type'=>'panel','key'=>'divAtch','classes'=>['block50']]]]]];
        unset($layout['divs']['dgItems'],$layout['divs']['divAttach']); // move inside accordion
        $layout['toolbars']['tbPhreeBooks']['icons']['new']['events']['onClick'] = "custWizardEdit($jID, 0);";
//        $layout['lang']['copy_billing'] = lang('copy_billing', $this->moduleID);
        $layout['jsHead']['custWizard']  = "function custWizardToggle(intAcc) {
    jqBiz('#accCustWizard').accordion('select', intAcc);
    if (intAcc != 3) { return; }
    var rowData = jqBiz('#dgJournalItem').edatagrid('getData');
    if (rowData.total == 0) { jqBiz('#dgJournalItem').edatagrid('addRow').edatagrid('fitColumns'); }
}";
        $this->restoreSecurity();
        msgDebug("\nlayout after wizard mods = ".print_r($layout, true));
    }

    /**
     * Overrides security of and PhreeBooks edit so the page can be rendered
     */
    private function overrideSecurity()
    {
        $this->tmpSecurity = getUserCache('role', 'security', 'j12_mgr');
        setSecurityOverride('j12_mgr', 2);
    }

    /**
     * Restores security to this modules value after wizard activity
     */
    private function restoreSecurity()
    {
        setSecurityOverride('j12_mgr', $this->tmpSecurity);
    }
}
