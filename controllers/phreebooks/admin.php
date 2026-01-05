<?php
/*
 * Administration functions for PhreeBooks module
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
 * @version    7.x Last Update: 2026-01-02
 * @filesource /controllers/phreebooks/admin.php
 */

namespace bizuno;

bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/functions.php', 'phreebooksProcess', 'function');

class phreebooksAdmin {

    public $moduleID = 'phreebooks';
    public $pageID   = 'admin';
    private $defMethods = ['totals'=>[
        'achBalBeg', 'achBalEnd', 'achDiscount', 'achSubtotal', 'achTotal', 'balance', 'balanceBeg', 'balanceEnd', 'debitcredit',
        'discount', 'discountChk', 'shipping', 'subtotal', 'subtotalChk', 'tax_other', 'total', 'total_bal', 'total_pmt']];
    public $lang;
    public $assets;
    public $settings;
    public $structure;
    public $phreeformProcessing;
    public $phreeformFormatting;
    public $notes;

    function __construct() {
        $this->lang = getLang($this->moduleID);
        $this->assets    = [0, 2, 4, 6, 8, 12, 32, 34]; // gl_account types that are assets
        $this->settings  = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure = [
            'dirMethods'=> ['totals', 'payroll'],
            'attachPath'=> ['phreebooks'=>'data/phreebooks/uploads/', 'edi'=>'data/edi/', 'returns'=>'data/returns/uploads/', 'payroll'=>'data/payroll/uploads/'],
            'menuBar'   => ['child'=>[
                'banking' => ['order'=>40,'label'=>('banking'),'group'=>'bnk','icon'=>'bank',      'child'=>[
                    'j17_mgr' => ['order'=>70,'label'=>'journal_id_17',     'icon'=>'payment',   'route'=>"$this->moduleID/main/manager&jID=17"],
                    'j18_mgr' => ['order'=>10,'label'=>'journal_id_18',     'icon'=>'payment',   'route'=>"$this->moduleID/main/manager&jID=18"],
                    'j20_bulk'=> ['order'=>55,'label'=>'journal_id_20_bulk','icon'=>'bank-check','route'=>"$this->moduleID/main/manager&jID=20&bizAction=bulk"],
                    'j20_mgr' => ['order'=>50,'label'=>'journal_id_20',     'icon'=>'bank-check','route'=>"$this->moduleID/main/manager&jID=20"],
                    'j22_mgr' => ['order'=>20,'label'=>'journal_id_22',     'icon'=>'bank-check','route'=>"$this->moduleID/main/manager&jID=22"],
                    'nacha'   => ['order'=>85,'label'=>'journal_id_20_ach', 'icon'=>'nacha',     'route'=>'payment/nacha/manager'],
                    'register'=> ['order'=>80,'label'=>'bank_register',     'icon'=>'register',  'route'=>"$this->moduleID/register/manager"],
                    'recon'   => ['order'=>85,'label'=>'bank_recon',        'icon'=>'apply',     'route'=>"$this->moduleID/reconcile/manager"],
                    'rpt_bank'=> ['order'=>99,'label'=>'reports',           'icon'=>'mimeDoc',   'route'=>"phreeform/main/manager&gID=bnk"]]],
                'customers' => ['child'=>[
                    'sales' => ['order'=>20,'label'=>'journal_id_12_mgr',  'icon'=>'sales',    'route'=>"$this->moduleID/main/manager&jID=12&mgr=1",'child'=>[
                        'j9_mgr' => ['order'=>30,'label'=>'journal_id_9',  'icon'=>'quote',    'route'=>"$this->moduleID/main/manager&jID=9"],
                        'j10_mgr'=> ['order'=>20,'label'=>'journal_id_10', 'icon'=>'order',    'route'=>"$this->moduleID/main/manager&jID=10"],
                        'j12_mgr'=> ['order'=>10,'label'=>'journal_id_12', 'icon'=>'sales',    'route'=>"$this->moduleID/main/manager&jID=12"],
                        'j13_mgr'=> ['order'=>40,'label'=>'journal_id_13', 'icon'=>'credit',   'route'=>"$this->moduleID/main/manager&jID=13"]]],
                    'returns'    => ['order'=>30,'label'=>'returns',       'icon'=>'return',   'route'=>"$this->moduleID/returns/manager"],
                    'fulfillment'=> ['order'=>40,'label'=>'fulfillment',   'icon'=>'fill',     'route'=>"$this->moduleID/fulfillment/fulfillMain"]]],
                'inventory' => ['child'=>[
                    'j14_mgr' => ['order'=>35,'label'=>'journal_id_14',    'icon'=>'tools',    'route'=>"$this->moduleID/main/manager&jID=14"],
                    'j15_mgr' => ['order'=>52,'label'=>'journal_id_15',    'icon'=>'transfer', 'route'=>"$this->moduleID/main/manager&jID=15"],
                    'j16_mgr' => ['order'=>50,'label'=>'journal_id_16',    'icon'=>'inv-adj',  'route'=>"$this->moduleID/main/manager&jID=16"]]],
                'ledger'    => ['order'=>50,'label'=>'general_ledger','group'=>'gl','icon'=>'journal','child'=>[
                    'j2_mgr'  => ['order'=>10, 'label'=>'journal_id_2',    'icon'=>'journal',  'route'=>"$this->moduleID/main/manager&jID=2&mgr=1"],
//                  'cashflow'=> ['order'=>80,'label'=>lang('cash_flow')),   'icon'=>'linechart','route'=>"hrefClick('phreebooks/budget/cashFlow');"],
                    'budget'  => ['order'=>90,'label'=>'budget',           'icon'=>'budget',    'route'=>"$this->moduleID/budget/manager"],
                    'rpt_jrnl'=> ['order'=>99,'label'=>'reports',          'icon'=>'mimeDoc',   'route'=>"phreeform/main/manager&gID=gl"],
                    'rpt_gl'  => ['order'=>99,'label'=>'reports',          'icon'=>'mimeDoc',   'route'=>'phreeform/main/manager&gID=gl']]],
                'tools'     => ['child'=>[
                    'j0_mgr'  => ['order'=>75,'label'=>'journal_id_0',                           'icon'=>'search','route'=>"$this->moduleID/main/manager&jID=0"],
                    'edi'     => ['order'=>85,'label'=>sprintf(lang('tbd_manager'), lang('edi')),'icon'=>'edi',   'route'=>"$this->moduleID/ediMain/manager"]]],
                'vendors'   => ['child'=>[
                    'purch' => ['order'=>20,'label'=>'journal_id_6_mgr',   'icon'=>'purchase','route'=>"$this->moduleID/main/manager&jID=6&mgr=1",'child'=>[
                        'j3_mgr' => ['order'=>30,'label'=>'journal_id_3',  'icon'=>'quote',   'route'=>"$this->moduleID/main/manager&jID=3"],
                        'j4_mgr' => ['order'=>20,'label'=>'journal_id_4',  'icon'=>'order',   'route'=>"$this->moduleID/main/manager&jID=4"],
                        'j6_mgr' => ['order'=>10,'label'=>'journal_id_6',  'icon'=>'purchase','route'=>"$this->moduleID/main/manager&jID=6"],
                        'j7_mgr' => ['order'=>40,'label'=>'journal_id_7',  'icon'=>'credit',  'route'=>"$this->moduleID/main/manager&jID=7"]]],
                    ]]]],
            'hooks' => [
                'administrate'=>['roles'=>['edit'=>['order'=>10,'method'=>'rolesEdit'], 'save'=>['order'=>10,'method'=>'rolesSave']]],
                'bizuno'      =>['admin'=>['loadBrowserSession'=>['order'=>50]]]],
            'api' => ['path' => 'phreebooks/api/journalAPI', 'attr' => ['jID' => 12]]]; // default to import sales
        $this->phreeformProcessing = [
            'bnkReg'    => ['text'=>lang('bank_register_format'),            'group'=>lang('banking')],
            'bnkCard'   => ['text'=>lang('bank_cc_card_type'),               'group'=>lang('banking')],
            'bnkHint'   => ['text'=>lang('bank_cc_card_hint'),               'group'=>lang('banking')],
            'bnkCode'   => ['text'=>lang('bank_cc_card_code'),               'group'=>lang('banking')],
            'rep_id'    => ['text'=>lang('contacts_rep_id_c'),               'module'=>'bizuno','function'=>'viewFormat'],
            'taxTitle'  => ['text'=>lang('tax_rates_title'),                 'module'=>'bizuno','function'=>'viewFormat'],
            'cTerms'    => ['text'=>lang('terms')." (contact ID)",           'module'=>'bizuno','function'=>'viewFormat'],
            'terms'     => ['text'=>lang('terms')." (".lang('customers').")",'module'=>'bizuno','function'=>'viewFormat'],
            'terms_v'   => ['text'=>lang('terms')." (".lang('vendors').")",  'module'=>'bizuno','function'=>'viewFormat'],
            'AgeCur'    => ['text'=>$this->lang['pb_inv_age_00']],
            'Age30'     => ['text'=>$this->lang['pb_inv_age_30']],
            'Age60'     => ['text'=>$this->lang['pb_inv_age_60']],
            'Age90'     => ['text'=>$this->lang['pb_inv_age_90']],
            'Age120'    => ['text'=>$this->lang['pb_inv_age_120']],
            'Age61'     => ['text'=>$this->lang['pb_inv_age_61']],
            'Age91'     => ['text'=>$this->lang['pb_inv_age_91']],
            'Age121'    => ['text'=>$this->lang['pb_inv_age_121']],
            'age_00'    => ['text'=>$this->lang['pb_cnt_age_00']],
            'age_30'    => ['text'=>$this->lang['pb_cnt_age_30']],
            'age_60'    => ['text'=>$this->lang['pb_cnt_age_60']],
            'age_90'    => ['text'=>$this->lang['pb_cnt_age_90']],
            'age_120'   => ['text'=>$this->lang['pb_cnt_age_120']],
            'age_61'    => ['text'=>$this->lang['pb_cnt_age_61']],
            'age_91'    => ['text'=>$this->lang['pb_cnt_age_91']],
            'age_121'   => ['text'=>$this->lang['pb_cnt_age_121']],
            'begBal'    => ['text'=>lang('beginning_balance')],
            'endBal'    => ['text'=>lang('ending_balance')],
            'subTotal'  => ['text'=>lang('subtotal')],
            'invBalance'=> ['text'=>lang('balance')],
            'invCOGS'   => ['text'=>lang('inventory_cogs')],
            'invRefNum' => ['text'=>lang('invoice_num_2')],
            'invUnit'   => ['text'=>$this->lang['pb_inv_unit']],
            'itmTaxAmt' => ['text'=>$this->lang['pb_line_item_tax']],
            'orderCOGS' => ['text'=>lang('pb_order_cogs')],
            'pmtDate'   => ['text'=>lang('payment_due_date')],
            'pmtDisc'   => ['text'=>lang('payment_discount')],
            'pmtNet'    => ['text'=>lang('payment_net_due')],
            'paymentDue'=> ['text'=>lang('payment_due')],
            'paymentRcv'=> ['text'=>lang('payment_received')],
            'paymentRef'=> ['text'=>lang('payment_reference')],
            'ship_bal'  => ['text'=>lang('shipped_balance')],
            'shipBalVal'=> ['text'=>lang('shipped_balance_value')],
            'ship_prior'=> ['text'=>lang('shipped_prior')],
            'taxJrnl'   => ['text'=>$this->lang['pb_tax_by_journal']],
            'taxRate'   => ['text'=>lang('tax_rates_tax_rate')." (taxID)"],
            'ttlJrnl'   => ['text'=>$this->lang['pb_total_by_journal']],
            'soStatus'  => ['text'=>$this->lang['pb_so_status']],
            'isCur'     => ['text'=>lang('gl_acct_type_30')],
            'isYtd'     => ['text'=>$this->lang['pb_is_ytd']],
            'isBdgt'    => ['text'=>lang('budget')],
            'isBytd'    => ['text'=>$this->lang['pb_is_budget_ytd']],
            'isLcur'    => ['text'=>$this->lang['ly_actual']],
            'isLytd'    => ['text'=>$this->lang['pb_is_last_ytd']],
            'isLBgt'    => ['text'=>$this->lang['ly_budget']],
            'isLBtd'    => ['text'=>$this->lang['pb_is_last_bdgt_ytd']]];
        setProcessingDefaults($this->phreeformProcessing, $this->moduleID, $this->lang['title']);
        $this->phreeformFormatting = [
            'j_desc'    => ['text'=>lang('journal_id'),    'module'=>'bizuno','function'=>'viewFormat'],
            'glType'    => ['text'=>lang('gl_acct_type'),  'module'=>'bizuno','function'=>'viewFormat'],
            'glTitle'   => ['text'=>lang('gl_acct_title'), 'module'=>'bizuno','function'=>'viewFormat'],
            'glActive'  => ['text'=>lang('gl_acct_active'),'module'=>'bizuno','function'=>'viewFormat']];
        setProcessingDefaults($this->phreeformFormatting, $this->moduleID, $this->lang['title']);
        $this->notes = [$this->lang['note_phreebooks_install_1'],$this->lang['note_phreebooks_install_2'],$this->lang['note_phreebooks_install_3']];
    }

    /**
     * Sets the user defined settings structure
     * @return array - structure used in the main settings tab
     */
    public function settingsStructure() {
        $glDefaults = [
            'asset'        => getChartDefault(6),
            'cash'         => getChartDefault(0),
            'cash_ach'     => getChartDefault(0),
            'expense'      => getChartDefault(34),
            'inventory'    => getChartDefault(4),
            'liability'    => getChartDefault(22),
            'payables'     => getChartDefault(20),
            'receivables'  => getChartDefault(2),
            'sales'        => getChartDefault(30)];
        $data = [
            'general'  => ['order'=>10,'label'=>lang('general'),'fields'=>[
                'round_tax_auth' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'shipping_taxed' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'isolate_stores' => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'upd_rep_terms'  => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]],
            'customers'=> ['order'=>20,'label'=>lang('customers'),'fields'=>[
                'gl_receivables' => ['attr'=>['type'=>'ledger','id'=>'customers_gl_receivables', 'value'=>$glDefaults['receivables']]],
                'gl_sales'       => ['attr'=>['type'=>'ledger','id'=>'customers_gl_sales',       'value'=>$glDefaults['sales']]],
                'gl_cash'        => ['attr'=>['type'=>'ledger','id'=>'customers_gl_cash',        'value'=>$glDefaults['cash']]],
                'gl_cash_ach'    => ['attr'=>['type'=>'ledger','id'=>'customers_gl_cash_ach',    'value'=>$glDefaults['cash_ach']]],
                'gl_discount'    => ['attr'=>['type'=>'ledger','id'=>'customers_gl_discount',    'value'=>$glDefaults['sales']]],
                'gl_deposit'     => ['attr'=>['type'=>'ledger','id'=>'customers_gl_deposit',     'value'=>$glDefaults['liability']]],
                'gl_liability'   => ['attr'=>['type'=>'ledger','id'=>'customers_gl_liability',   'value'=>$glDefaults['liability']]],
                'gl_expense'     => ['attr'=>['type'=>'ledger','id'=>'customers_gl_expense',     'value'=>$glDefaults['expense']]],
                'tax_rate_id_c'  => ['label'=>lang('sales_tax'),'defaults'=>['type'=>'c','target'=>'contacts'], 'attr'=>['type'=>'tax','value'=>0]],
                'terms_text'     => ['break'=>false],
                'terms'          => ['break'=>false,'attr'=>['type'=>'hidden','value'=>'2']],
                'terms_edit'     => ['icon'=>'settings','label'=>lang('terms'),'attr'=>['type'=>'hidden'],'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=c&prefix=customers_&default=1', 0, jqBiz('#customers_terms').val());"]],
                'show_status'    => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'include_all'    => ['attr'=>['type'=>'selNoYes', 'value'=>0]],
                'ck_dup_po'      => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]],
            'vendors'  => ['order'=>30,'label'=>lang('vendors'),'fields'=>[
                'gl_payables'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_payables',    'value'=>$glDefaults['payables']]],
                'gl_purchases'   => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_purchases',   'value'=>$glDefaults['inventory']]],
                'gl_cash'        => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_cash',        'value'=>$glDefaults['cash']]],
                'gl_cash_ach'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_cash_ach',    'value'=>$glDefaults['cash_ach']]],
                'gl_discount'    => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_discount',    'value'=>$glDefaults['payables']]],
                'gl_deposit'     => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_deposit',     'value'=>$glDefaults['asset']]],
                'gl_liability'   => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_liability',   'value'=>$glDefaults['liability']]],
                'gl_expense'     => ['attr'=>['type'=>'ledger','id'=>'vendors_gl_expense',     'value'=>$glDefaults['expense']]],
                'tax_rate_id_v'  => ['label'=>lang('purchase_tax'),'defaults'=>['type'=>'v','target'=>'contacts'], 'attr'=>['type'=>'tax','value'=>0]],
                'terms_text'     => ['break'=>false],
                'terms'          => ['break'=>false,'attr'=>['type'=>'hidden','value'=>'3:0:0:30:1000.00']],
                'terms_edit'     => ['icon'=>'settings','label'=>lang('terms'),'attr'=>['type'=>'hidden'],'events'=>['onClick'=>"jsonAction('contacts/main/editTerms&type=v&prefix=vendors_&default=1', 0, jqBiz('#vendors_terms').val());"]],
                'show_status'    => ['attr'=>['type'=>'selNoYes', 'value'=>1]],
                'rm_item_ship'   => ['attr'=>['type'=>'selNoYes', 'value'=>0]]]]];
        settingsFill($data, $this->moduleID);
        $data['customers']['fields']['terms_text']['attr']['value']= viewTerms($data['customers']['fields']['terms']['attr']['value'],true, 'c');
        $data['vendors']['fields']['terms_text']['attr']['value']  = viewTerms($data['vendors']['fields']['terms']['attr']['value'],  true, 'v');
        return $data;
    }

    /**
     * Special initialization actions set during first startup and cache creation
     * @return boolean true
     */
    public function initialize() {
        periodAutoUpdate();
        // Rebuild some option values
        $metaStat= dbMetaGet('%', 'options_return_status');
        $idxStat = metaIdxClean($metaStat); // remove the indexes
        $status = [
             '1' =>$this->lang['rtn_status_1'],  '2'=>$this->lang['rtn_status_2'],  '3'=>$this->lang['rtn_status_3'],
             '4' =>$this->lang['rtn_status_4'],  '5'=>$this->lang['rtn_status_5'],  '6'=>$this->lang['rtn_status_6'],
             '7' =>$this->lang['rtn_status_7'],  '8'=>$this->lang['rtn_status_8'],  '9'=>$this->lang['rtn_status_9'],
            '10'=>$this->lang['rtn_status_10'],'90'=>$this->lang['rtn_status_90'],'99'=>$this->lang['rtn_status_99']];
        asort($status);
        dbMetaSet($idxStat, 'options_return_status', $status);
        $metaCode= dbMetaGet('%', 'options_return_codes');
        $idxCode = metaIdxClean($metaCode); // remove the indexes
        $codes  = [
            '1' =>$this->lang['rtn_code_1'],    '2'=>$this->lang['rtn_code_2'],    '3'=>$this->lang['rtn_code_3'],
            '4' =>$this->lang['rtn_code_4'],    '5'=>$this->lang['rtn_code_5'],    '6'=>$this->lang['rtn_code_6'],
            '7' =>$this->lang['rtn_code_7'],   '80'=>$this->lang['rtn_code_80'],  '99'=>$this->lang['rtn_code_99']];
        asort($codes);
        dbMetaSet($idxCode, 'options_return_codes', $codes);
        $metaAct= dbMetaGet('%', 'options_qa_status');
        $idxAct = metaIdxClean($metaAct); // remove the indexes
        $actions= [
             '1'=>$this->lang['rtn_action_1'], '2'=>$this->lang['rtn_action_2'], '3'=>$this->lang['rtn_action_3'],
             '4'=>$this->lang['rtn_action_4'], '5'=>$this->lang['rtn_action_5'], '6'=>$this->lang['rtn_action_6'],
            '99'=>$this->lang['rtn_action_99']];
        asort($status);
        dbMetaSet($idxAct, 'options_qa_status', $actions);
        // Put them in the cache for runtime access
        setModuleCache('bizuno', 'options', 'return_status', $status);
        setModuleCache('bizuno', 'options', 'return_codes',  $codes);
        setModuleCache('bizuno', 'options', 'return_actions',$actions);
        return true;
    }

    /**
     * User settings main entry page
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout = []) {
        if (!$security = validateAccess('admin', 1)) { return; }
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/currency.php', 'phreebooksCurrency');
        $currency= new phreebooksCurrency();
        $repost  = $this->getViewRepost();
        // Add the Sales Tax Collected panel to tools tab
        $period= getModuleCache('phreebooks', 'fy', 'period') - 1;
        $taxNxs  = '<div><form id="frmTaxCalc" action="'.BIZUNO_URL_AJAX.'&bizRt=phreebooks/restfulTax/calcTaxCollected">';
        $taxNxs .= "<p>".lang('tax_calc_desc')."</p>\n";
        $taxNxs .= html5('period', ['label'=>lang('period'),'values'=>viewKeyDropdown(localeDates(false, false, false, false, true)),'attr'=>['type'=>'select','value'=>$period]]);
        $taxNxs .= '<p>'.html5('', ['order'=>80,'attr'=>['type'=>'button','value'=>lang('download')],'events'=>['onClick'=>"jqBiz('#frmTaxCalc').submit();"]]).'</p>';
        $taxNxs .= "</form><script>ajaxDownload('frmTaxCalc');</script></div>";
        $fields  = [
            'glTestDesc'   => ['order'=>10,'html'=>$this->lang['pbtools_gl_test_desc'],'attr'=>['type'=>'raw']],
            'btnRepairGL'  => ['order'=>20,'attr'=>['type'=>'button','value'=>lang('start')],'events'=>['onClick'=>"jsonAction('phreebooks/tools/glRepair');"]],
            'pruneCogsDesc'=> ['order'=>10,'html'=>$this->lang['pb_prune_cogs_desc'],'attr'=>['type'=>'raw']],
            'btnPruneCogs' => ['order'=>20,'attr'=>['type'=>'button', 'value'=>lang('start')],'events'=>['onClick'=>"jsonAction('phreebooks/tools/pruneCogs');"]],
            'cleanAtchDesc'=> ['order'=>10,'html'=>$this->lang['pb_attach_clean_desc'],'attr'=>['type'=>'raw']],
            'btnAtchCln'   => ['order'=>80,'attr'=> ['type'=>'button','value'=>lang('start')],
                'events' => ['onClick'=>"if (confirm('".$this->lang['pb_attach_clean_confirm']."')) { getPurgeDates(); }"]],
            'purgeGlDesc'  => ['order'=>10,'html'=>$this->lang['msg_gl_db_purge_confirm'],'attr'=>['type'=>'raw']],
            'purge_db'     => ['order'=>20,'styles'=>['text-align'=>'right'],'attr'=>['size'=>7]],
            'btn_purge'    => ['order'=>30,'attr'=>['type'=>'button', 'value'=>$this->lang['phreebooks_purge_db_journal']],
                'events' => ['onClick'=>"if (confirm('".$this->lang['msg_gl_db_purge_confirm']."')) jsonAction('phreebooks/tools/glPurge', 0, jqBiz('#purge_db').val());"]]];
        $data    = [
            'tabs'    => ['tabAdmin'=>['divs'=>[
                'tabGL'    => ['order'=>20,'label'=>lang('chart_of_accts'), 'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/chart/manager'"]],
                'tabCur'   => ['order'=>30,'label'=>lang('currencies'),     'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/currency/manager'"]],
                'tabNexus' => ['order'=>40,'label'=>lang('nexus'),          'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/restfulTax/manager'"]],
                'tabTaxc'  => ['order'=>45,'label'=>lang('sales_tax'),      'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/tax/manager&type=c'"]],
                'tabTaxv'  => ['order'=>50,'label'=>lang('purchase_tax'),   'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/tax/manager&type=v'"]],
                'tabFY'    => ['order'=>80,'label'=>lang('fiscal_calendar'),'type'=>'html','html'=>'',
                    'options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=phreebooks/admin/managerFY'"]],
                'tabEdi'   => ['order'=>70,'label'=>$this->lang['tab_title'],'type'=>'html','html'=>'','options'=>['href'=>"'".BIZUNO_URL_AJAX."&bizRt=$this->moduleID/adminEdi/manager'"]],
                'tools'    => ['order'=>80,'label'=>lang('tools'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                'tabTools' => ['order'=>90,'label'=>lang('tools'),'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'testGL'   => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'testGL'],
                    'cleanAtch'=> ['order'=>20,'type'=>'panel','classes'=>['block33'],'key'=>'cleanAtch'],
                    'pruneCOGS'=> ['order'=>30,'type'=>'panel','classes'=>['block33'],'key'=>'pruneCOGS'],
                    'repostGL' => ['order'=>40,'type'=>'panel','classes'=>['block66'],'key'=>'repostGL'],
                    'ediGet'   => ['order'=>50,'type'=>'panel','classes'=>['block33'],'key'=>'ediGet'],
                    'taxCalc'  => ['order'=>60,'type'=>'panel','classes'=>['block33'],'key'=>'taxCalc'],
                    'ediMan'   => ['order'=>70,'type'=>'panel','classes'=>['block33'],'key'=>'ediMan']]],
                    'purgeGL'  => ['order'=>90,'type'=>'panel','hidden'=> $security>4?false:true,'classes'=>['block33'],'key'=>'purgeGL']]]]]],
            'panels'  => [
                'repostGL' => ['label'=>$this->lang['phreebooks_repost_title'],'type'=>'html',  'html'=>$repost],
                'testGL'   => ['label'=>$this->lang['title_gl_test'],          'type'=>'fields','keys'=>['glTestDesc','btnRepairGL']],
                'pruneCOGS'=> ['label'=>$this->lang['pb_prune_cogs_title'],    'type'=>'fields','keys'=>['pruneCogsDesc','btnPruneCogs']],
                'cleanAtch'=> ['label'=>$this->lang['pb_attach_clean_title'],  'type'=>'fields','keys'=>['cleanAtchDesc','dateAtchCln','btnAtchCln']],
                'ediGet'   => ['title'=>$this->lang['edi_get_title'],'type'=>'divs','divs'=>[
                    'desc'   => ['order'=>10,'type'=>'html','html'=>$this->lang['edi_get_desc']],
                    'btnGo'  => ['order'=>30,'type'=>'html','html'=>"<p>".html5('', ['attr'=>['type'=>'button','value'=>lang('go')],'events'=>['onClick'=>"jsonAction('$this->moduleID/ediAPI/ediGet&opt=man');"]])."</p>"]]],
                'ediMan'   => ['title'=>$this->lang['edi_man_title'],'type'=>'divs','divs'=>[
                    'desc'   => ['order'=>10,'type'=>'html','html'=>$this->lang['edi_man_desc']],
                    'formBOF'=> ['order'=>15,'type'=>'form','key' =>'frmEdiMan'],
                    'ediRID' => ['order'=>20,'type'=>'html','html'=>"<p>".html5('rID', ['attr'=>['type'=>'text']])."</p>"],
                    'btnGo'  => ['order'=>30,'type'=>'html','html'=>"<p>".html5('', ['attr'=>['type'=>'button','value'=>lang('go')],'events'=>['onClick'=>"jqBiz('#frmEdiMan').submit();"]])."</p>"]],
                    'formEOF'=> ['order'=>85,'type'=>'html','html'=>'</form>']],
                'purgeGL'  => ['label'=>$this->lang['msg_gl_db_purge'],        'type'=>'fields','keys'=>['purgeGlDesc','purge_db','btn_purge']],
                'taxCalc'  => ['label'=>lang('tax_collected'),'type'=>'html',  'html'=>$taxNxs]],
            'datagrid'=> ['dgCurrency'  =>$currency->dgCurrency('dgCurrency', $security)],
            'forms'   => [
                'frmEdiMan'  => ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/ediAPI/ediManual"]],
                'frmCurrency'=> ['attr'=>['type'=>'form','action'=>BIZUNO_URL_AJAX."&bizRt=$this->moduleID/currency/save"]]],
            'fields'  => $fields,
            'jsHead'  => ['purgeAttch' => $this->getViewPurgeAttach(),
                'dataCurrency'=> "var dataCurrency = ".json_encode(array_values(getModuleCache('phreebooks','currency','iso'))).";",
                'dataEDI'     => "var dataEDI = "     .json_encode(array_values(getModuleCache($this->moduleID,'edi'))).";"],
            'jsBody'  => ['init'=>"jqBiz('#repost_begin').datebox({ required:true }); jqBiz('#repost_end').datebox({ required:true });"],
            'jsReady' => ['spTools'=>"ajaxForm('frmEdiMan');"]]; // ajaxForm('frmImpTax');
        $jIDs = [2,3,4,6,7,9,10,12,13,14,15,16,17,18,20,22]; // 19, 21 - POS, POP
        $order= 20;
        foreach ($jIDs as $jID) {
            $numMonths = in_array($jID, [4, 10]) ? -24 : -6;
            $data['fields']["atchCln_{$jID}"] = ['order'=>$order,'label'=>lang("journal_id_{$jID}"),'attr'=>['type'=>'date','value'=>localeCalculateDate(biz_date(), 0, $numMonths)]];
            $data['panels']['cleanAtch']['keys'][] = "atchCln_{$jID}";
            $order++;
        }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang), $data);
    }

    private function getViewPurgeAttach()
    {
        return "
function getPurgeDates() {
    jIDs = [2,3,4,6,7,9,10,12,13,14,15,16,17,18,20,22];
    purgeDates = {};
    jqBiz.each(jIDs, function (index, value) { purgeDates['j'+value] = bizDateGet('atchCln_'+value); });
    jsonAction('phreebooks/tools/cleanAttach', 0, JSON.stringify(purgeDates));
}";
    }

    private function getViewRepost()
    {
        $repost      = ['position'=>'after','attr'=>['type'=>'checkbox']]; // label comes later
        $repost_begin= ['attr'=>['type'=>'date',   'value'=>biz_date('Y-m-d')]];
        $repost_end  = ['attr'=>['type'=>'date',   'value'=>biz_date('Y-m-d')]];
        $btn_repost  = ['icon'=>'save','size'=>'large','events'=>['onClick'=>"divSubmit('phreebooks/tools/glRepostBulk', 'glRepost');"]];

        $output  = '<div id="glRepost">';
        $output .= " <p>".$this->lang['msg_gl_repost_journals_confirm']."</p>\n";
        $output .= ' <table style="border-style:none;margin-left:auto;margin-right:auto;">'."\n";
        $output .= "  <tbody>\n";
        $output .= '   <tr class="panel-header">'."\n";
        $output .= "    <th>".lang('gl_acct_type_2')."</th>\n<th>".lang('gl_acct_type_20')."</th>\n<th>".lang('gl_acct_type_0')."</th>\n<th>".lang('gl_acct_type_4')."</th>\n<th>&nbsp;</th>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_id_9');
        $output .= "    <td>".html5('jID[9]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_3');
        $output .= "    <td>".html5('jID[3]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_2');
        $output .= "    <td>".html5('jID[2]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_14');
        $output .= "    <td>".html5('jID[14]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_id_10');
        $output .= "    <td>".html5('jID[10]', $repost)."</td>\n";
        $repost['label'] = lang('journal_id_4');
        $output .= "    <td>".html5('jID[4]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_18');
        $output .= "    <td>".html5('jID[18]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_16');
        $output .= "    <td>".html5('jID[16]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_id_12');
        $output .= "    <td>".html5('jID[12]', $repost)."</td>\n";
        $repost['label'] = lang('journal_id_6');
        $output .= "    <td>".html5('jID[6]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_20');
        $output .= "    <td>".html5('jID[20]', $repost)."</td>\n";
        $output .= '    <td style="text-align:right">'.lang('start')."</td>\n";
        $output .= "    <td>".html5('repost_begin',  $repost_begin)."</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_id_13');
        $output .= "    <td>".html5('jID[13]', $repost)."</td>\n";
        $repost['label'] = lang('journal_id_7');
        $output .= "    <td>".html5('jID[7]',  $repost)."</td>\n";
        $repost['label'] = lang('journal_id_17');
        $output .= "    <td>".html5('jID[17]', $repost)."</td>\n";
        $output .= '    <td style="text-align:right">'.lang('end')."</td>\n";
        $output .= "    <td>".html5('repost_end', $repost_end)."</td>\n";
        $output .= "   </tr>\n<tr>\n";
        $repost['label'] = lang('journal_id_19');
        $output .= "    <td>".html5('jID[19]', $repost)."</td>\n";
        $repost['label'] = lang('journal_id_21');
        $output .= "    <td>".html5('jID[21]', $repost)."</td>\n";
        $repost['label'] = lang('journal_id_22');
        $output .= "    <td>".html5('jID[22]', $repost)."</td>\n";
        $output .= "    <td>&nbsp;</td>\n";
        $output .= '    <td rowspan="2" style="text-align:right">'.html5('btn_repost', $btn_repost)."</td>\n";
        $output .= "   </tr>\n</tbody>\n</table></div>";
        return $output;
    }

    /**
     * Saves the user defined settings
     */
    public function adminSave() {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }

    public function managerFY(&$layout=[])
    {
        $dbMaxFY= dbGetValue(BIZUNO_DB_PREFIX . "journal_periods", ["MAX(fiscal_year) AS fiscal_year", "MAX(period) AS period"], false, false);
        $maxFY  = $dbMaxFY['fiscal_year'] > 0 ? $dbMaxFY['fiscal_year'] : 0;
        $html   = $this->getViewFY();
        $fields = [
            'btnNewFy'  => ['order'=>10,'attr'=>['type'=>'button','value' => $this->lang['phreebooks_new_fiscal_year']],
                'events' => ['onClick'=>"if (confirm('".sprintf($this->lang['msg_gl_fiscal_year_confirm'], $maxFY + 1)."')) { jqBiz('body').addClass('loading'); jsonAction('phreebooks/tools/fyAdd'); }"]],
            'btnCloseFy'=> ['order'=>20,'attr'=>['type'=>'button','value'=>$this->lang['del_fiscal_year_btn']],'events'=>['onClick'=>"jsonAction('phreebooks/tools/fyCloseValidate');"]],
        ];
        $data = ['type'=>'divHTML',
            'divs'  => ['divFY'=>['order'=>50,'type'=>'divs','divs'=>[
                'general' => ['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'newFY'  => ['order'=>10,'type'=>'panel','classes'=>['block33'],'key'=>'newFY'],
                    'editPer'=> ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'editPer']]]]]],
            'panels'=> [
                'newFY'  => ['label'=>lang('fiscal_years'),   'type'=>'fields','keys'=>['btnNewFy','btnCloseFy']],
                'editPer'=> ['label'=>lang('journal_periods'),'type'=>'html',  'html'=>$html['body']]],
            'fields'=> $fields,
            'jsBody'=> ['init' =>$html['jsBody']]];
        $layout = array_replace_recursive($layout, $data);
    }

    private function getViewFY()
    {
        $FYs       = $outputJS = [];
        $stmt      = dbGetResult("SELECT DISTINCT fiscal_year FROM ".BIZUNO_DB_PREFIX."journal_periods");
        $dbFYs     = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($dbFYs as $row) { $FYs[] = ['id' => $row['fiscal_year'], 'text' => $row['fiscal_year']]; }
        $fy        = clean('fy', ['format'=>'integer', 'default'=>getModuleCache('phreebooks', 'fy', 'fiscal_year', false, biz_date('Y'))], 'get');
        $fiscalY   = ['label'=>lang('fiscal_year'),'values'=>$FYs,'attr'=>['type'=>'select','value'=>$fy],
            'events' => ['onChange'=>"var tab=jqBiz('#tabAdmin').tabs('getSelected'); tab.panel( 'refresh', '".BIZUNO_URL_AJAX."&bizRt=phreebooks/admin/managerFY&fy='+bizSelGet('fy') );"]];
        $btnSaveFy = ['icon'=>'save','size'=>'large',
            'events' => ['onClick'=>"divSubmit('phreebooks/tools/fySave', 'fyCal');"]];
        $max_posted= dbGetValue(BIZUNO_DB_PREFIX."journal_main",    "MAX(period) AS period", false, false);
        $dbPer     = dbGetMulti(BIZUNO_DB_PREFIX."journal_periods", "fiscal_year=$fy", "period");
        $periods   = [];
        foreach ($dbPer as $row) { $periods[$row['period']] = ['start' => $row['start_date'], 'end' => $row['end_date']]; }
        $output    = "<p>".$this->lang['msg_gl_fiscal_year_edit'].'</p>
            <div id="fyCal" style="text-align:center">'.html5('fy', $fiscalY).html5('btnSaveFy', $btnSaveFy).'
            <table style="border-style:none;margin-left:auto;margin-right:auto;">
                <thead class="panel-header">
                    <tr><th width="33%">'.lang('period').'</th><th width="33%">'.lang('start').'</th><th width="33%">'.lang('end')."</th></tr>
                </thead>
                <tbody>\n";
        foreach ($periods as $period => $value) {
            $output .= '    <tr><td style="text-align:center">'.$period."</td>";
            if ($period > $max_posted) { // only allow changes if nothing has been posted above this period
                $output .= '<td>'.html5("pStart[$period]",['attr'=>['type'=>'date','value'=>$value['start']]])."</td>"; // new Date(2012, 6, 1)
                $outputJS[] = "jqBiz('#pStart_$period').datebox({required:true, onSelect:function(date){ var nDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()-1); jqBiz('#pEnd_".($period-1)."').datebox('setValue', nDate); } });\n";
                $output .= '<td>'.html5("pEnd[$period]",  ['attr'=>['type'=>'date','value'=>$value['end']]])."</td>\n";
                $outputJS[] = "jqBiz('#pEnd_$period').datebox({  required:true, onSelect:function(date){ var nDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()+1); jqBiz('#pStart_".($period+1)."').datebox('setValue', nDate); } });\n";
            } else {
                $output .= '<td style="text-align:center">'.viewDate($value['start'])."</td>";
                $output .= '<td style="text-align:center">'.viewDate($value['end'])."</td>\n";
            }
            $output .= "</tr>\n";
        }
        $output .= "</tbody>\n</table>\n</div>";
        return ['body'=>$output,'jsBody'=>$outputJS];
    }

    /**
     * Operations that need to be completed when first installing Bizuno for the PhreeBooks module
     */
    public function installFirst()
    {
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/chart.php', 'phreebooksChart');
        $coa = new phreebooksChart();
        msgDebug("\n  Loading chart of accounts");
        $coa->chartInstall(getUserCache('profile', 'chart'));
        msgDebug("\n  Building fiscal year.");
        $current_year= biz_date('Y');
        $start_year  = getUserCache('profile', 'first_fy');
        $start_period= 1;
        $runaway     = 0;
        while ($start_year <= $current_year) {
            setNewFiscalYear($start_year, $start_period, "$start_year-01-01");
            $start_year++;
            $start_period = $start_period + 12;
            $runaway++;
            if ($runaway > 10) { break; }
        }
        msgDebug("\n  Updating current period");
        $props = dbGetPeriodInfo(biz_date('m'));
        setModuleCache('phreebooks', 'fy', false, $props);
        msgDebug("\n  Building and checking chart history");
        buildChartOfAccountsHistory();
        clearUserCache('profile', 'chart');
        clearUserCache('profile', 'first_fy');
    }

    /**
     * Installs the currency settings and total methods at first install, can be modified by user later
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function install(&$layout=[])
    {
        msgDebug("\nEntering $this->moduleID:install");
        // Install the requried and basic list of totals
        $bAdmin = new bizunoSettings();
        foreach ($this->structure['dirMethods'] as $dirMeth) {
            $defaults = isset($this->defMethods[$dirMeth]) ? $this->defMethods[$dirMeth] : [];
            $bAdmin->adminInstMethods($this->moduleID, $dirMeth, $defaults);
        }
        // set the currency settings
        $defISO    = getModuleCache('phreebooks', 'currency', 'defISO');
        bizAutoLoad(BIZUNO_FS_LIBRARY.'controllers/phreebooks/currency.php', 'phreebooksCurrency');
        $currency  = new phreebooksCurrency();
        $currencies= getModuleCache('phreebooks', 'currency', 'iso', false, []);
        $currencies[$defISO] = $currency->currencySettings($defISO);
        setModuleCache('phreebooks', 'currency', 'iso', $currencies);
    }

    /**
     * Operations needed to build the browser cache at first log in specific to PhreeBooks module
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function loadBrowserSession(&$layout=[])
    {
        $accts = []; // load gl Accounts
        $chart = dbMetaGet(0, 'chart_of_accounts');
        metaIdxClean($chart);
        if (is_array($chart)) { foreach ($chart as $row) {
            $row['asset']  = in_array($row['type'], $this->assets) ? 1 : 0;
            $row['type']   = viewFormat($row['type'], 'glTypeLbl');
            $accts[]       = $row; // need to remove keys
        } }
        $layout['content']['dictionary']    = array_merge($layout['content']['dictionary'], $this->getBrowserLang());
        $layout['content']['glAccounts']    = ['total'=>sizeof($accts),  'rows'=>$accts];
        $cRates = loadTaxes('c');
        $layout['content']['taxRates']['c'] = ['total'=>sizeof($cRates), 'rows'=>$cRates];
        $vRates = loadTaxes('v');
        $layout['content']['taxRates']['v'] = ['total'=>sizeof($vRates), 'rows'=>$vRates];
        msgDebug("\nSending back data = " . print_r($layout['content'], true));
    }

    private function getBrowserLang()
    {
        $lang = [];
        $journals = [0, 2, 3, 4, 6, 7, 9, 10, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22];
        foreach ($journals as $jID) { $lang['TITLE_J'.$jID] = lang("journal_id_$jID"); }
        return array_merge($lang, [
            'PB_INVOICE_RQD'     => $this->lang['msg_invoice_rqd'],
            'PB_INVOICE_WAITING' => $this->lang['msg_inv_waiting'],
            'PB_NEG_STOCK'       => $this->lang['msg_negative_stock'],
            'PB_RECUR_EDIT'      => $this->lang['msg_recur_edit'],
            'PB_SAVE_AS_CLOSED'  => $this->lang['msg_save_as_closed'],
            'PB_SAVE_AS_LINKED'  => $this->lang['msg_save_as_linked'],
            'PB_GL_ASSET_INC'    => $this->lang['bal_increase'],
            'PB_GL_ASSET_DEC'    => $this->lang['bal_decrease'],
            'PB_DBT_CRT_NOT_ZERO'=> $this->lang['err_debits_credits_not_zero']]);
    }

    /**
     * Extends the Roles - Edit - PhreeBooks tab to add Sales and Purchase access
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function rolesEdit(&$layout=[])
    {
        $rID = clean('rID', 'integer', 'get');
        $role= dbMetaGet($rID, 'bizuno_role');
        $layout['fields']['group_sales'] = ['order'=>20,'label'=>$this->lang['pb_role_cust_users'],'tip'=>$this->lang['msg_pb_admin_roles'],
            'attr'=>['type'=>'checkbox','checked'=>!empty($role['groups']['sales']) ? true : false]];
        $layout['fields']['group_purch'] = ['order'=>20,'label'=>$this->lang['pb_role_vend_users'], 'tip'=>$this->lang['msg_pb_admin_roles'],
            'attr'=>['type'=>'checkbox','checked'=>!empty($role['groups']['purch']) ? true : false]];
        $layout['fields']['group_csr'] = ['order'=>50,'label'=>$this->lang['role_rtn'], 'tip'=>$this->lang['roles_title'],
            'attr'=>['type'=>'checkbox','checked'=>!empty($role['groups']['csr'])?true:false]];
        $layout['tabs']['tabRoles']['divs']['customers']['divs']['props']= ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'custSettings'];
        $layout['tabs']['tabRoles']['divs']['vendors']['divs']['props']  = ['order'=>20,'type'=>'panel','classes'=>['block50'],'key'=>'vendSettings'];
        $layout['panels']['custSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>['group_sales','group_csr']];
        $layout['panels']['vendSettings'] = ['label'=>lang('settings'),'type'=>'fields','keys'=>['group_purch']];
    }

    /**
     * Extends the Roles settings to Save the PhereeBooks Specific settings
     * @return boolean null
     */
    public function rolesSave()
    {
        if (!$security = validateAccess('admin', 3)) { return; }
        if (empty($rID = clean('_rID', 'integer', 'post'))){ return; }
        $role= dbMetaGet($rID, 'bizuno_role');
        metaIdxClean($role);
        $role['groups']['sales']= clean('group_sales', 'boolean', 'post');
        $role['groups']['purch']= clean('group_purch', 'boolean', 'post');
        $role['groups']['csr']  = clean('group_csr', 'boolean', 'post');
        dbMetaSet($rID, 'bizuno_role', $role);
}

    /**
     * Saves the users preferred order total sequence and methods used to set the order screen totals fields
     * @return null, but session and registry are updated
     */
    public function orderTotals()
    {
        $data = clean('data', 'text', 'get');
        if (!$data) { return msgAdd("Bad values sent!"); }
        $vals = explode(';', $data);
        $output = [];
        foreach ($vals as $method) {
            $parts = explode(':', $method);
            $idx = array_shift($parts);
            $output[$idx] = [];
            $order = 1;
            foreach ($parts as $val) {
                if ($val) {
                    $output[$idx][] = ['name' => $val, 'order' => $order];
                } // 'path'=>$path_if_not_in_phreebooks /totals folder
                $order++;
            }
        }
        setModuleCache('phreebooks', 'totals', false, $output);
        msgAdd(lang('msg_settings_saved'), 'success');
    }
}
