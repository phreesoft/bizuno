<?php
/*
 * @name Bizuno ERP - Bizuno Pro Payment Module - Wallet
 *
 * For now assume the only processor is PayFabric, as PhreeSoft is a Partner
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
 * @version    7.x Last Update: 2025-07-23
 * @filesource /controllers/payment/wallet.php
 */

namespace bizuno;

if (!defined('PAYMENT_PAYFABRIC_WALLET'))     { define('PAYMENT_PAYFABRIC_WALLET',     'https://www.payfabric.com/Payment/'); }
if (!defined('PAYMENT_PAYFABRIC_WALLET_TEST')){ define('PAYMENT_PAYFABRIC_WALLET_TEST','https://sandbox.payfabric.com/Payment/'); }

class paymentWallet
{
    public  $moduleID = 'payment';
    private $mode     = 'prod'; // choices are 'test' (Test) or 'prod' (Production)
    public $lang;
    public $props;
    public $cID;
    public $type;
    public $pfID;

    function __construct()
    {
        $this->lang = getExtLang($this->moduleID);
        $this->props= getMetaMethod('gateways', 'payfabric');
        msgDebug("\nConstructing wallet with props = ".print_r($this->props, true));
        if (!empty($this->props)) {
            bizAutoLoad($this->props['path'].'payfabric.php');
            $this->payfabric = new \bizuno\payfabric($this->props['settings']);
        }
        $this->cID = clean('rID', 'integer','get');
        $this->type= clean('type','char',   'get');
        $this->pfID= getWalletID($this->cID);
    }

    /**
     * Builds the view for the wallet tab on the contacts edit screen
     * @param array $layout - Structure
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('j12_mgr', 2)) { return; }
        if (empty($this->payfabric)) { // not signed up, insert instructions to signup to PayFabric
            $html  = "Sign up to PayFabric to save on e-check and credit card processing. Click Here.";
//          $html  = $this->getPayFabricBanner();
            $layout= ['type'=>'divHTML','divs'=>['body'=>['order'=>50,'type'=>'html','html'=>$html]]];
            return;
        }
        $divCC   = $divCK = $panels = [];
        $cards   = $this->list($this->cID);
        $walletID= getWalletID($this->cID);
        msgDebug("\nRead cards from PayFabric = ".print_r($cards, true));
        foreach ($cards as $card) {
            if (!isset($card['type'])) { $card['type'] = 'credit'; }
            if (in_array($card['type'], ['e-check'])) {
                $divCK[$card['id']] = ['order'=>10,'type'=>'panel','key'=>$card['id'],'classes'=>['block50']];
            } else {
                $divCC[$card['id']] = ['order'=>10,'type'=>'panel','key'=>$card['id'],'classes'=>['block50']];
            }
            $panels[$card['id']] = $this->viewCard($card, $security);
        }
        $fields = [
            'newCC'     => ['order'=>10,'events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/wallet/add', $this->cID);"],'attr'=>['type'=>'button','value'=>lang('Add Credit Card')]],
//          'is_default'=> ['order'=>80,'label'=>lang('default'),'attr'=>['type'=>'checkbox']],
        ];
        $layout = ['type'=>'divHTML',
            'divs'=>[
                'header'  =>['order'=> 5,'type'=>'html','html'=>"<h1>".lang('wallet')." (Wallet ID: $walletID)</h1><h2>Your credit and debit cards:</h2>"],
                'lstCard' =>['order'=>20,'type'=>'divs','classes'=>['areaView'],'divs'=>$divCC],
//                  'hdChk'   =>['order'=>25,'type'=>'html','html'=>"<h2>Your checking accounts</h2>"],
//                  'lstChk'  =>['order'=>30,'type'=>'divs','classes'=>['areaView'],'divs'=>$divCK],
                'addNewHd'=>['order'=>35,'type'=>'html','html'=>"<h1>Add new payment method</h1><h2>Credit or debit cards</h2>"],
                'addNewCC'=>['order'=>45,'type'=>'fields','keys'=>['newCC']],
//                  'addNew01'=>['order'=>45,'type'=>'html','html'=>"<h2>Checking account</h2>"],
//                  'addNewCK'=>['order'=>50,'type'=>'divs','classes'=>['areaView'],'divs'=>[
//                      'newCK' => ['order'=>10,'type'=>'panel','key'=>'newCK','classes'=>['block50']]]],
                ],
            'panels' => $panels,
            'fields' => $fields,
            'jsHead' => ['init'=>$this->payfabric->eventJS($this->cID)]];
        if (empty($cards)) {
            $html = "The wallet is empty, let's add a credit card or e-check.";
            $layout['divs']['start'] = ['order'=> 5,'type'=>'html','html'=>$html];
        }
        if (in_array($this->type, ['v','c'])) {
            $dtlACH = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['ach_bank', 'ach_routing', 'ach_account'], "id=$this->cID"); // 'ach_enable',
//          $layout['fields']['ach_enable'] = ['order'=>10,'label'=>lang('ach_enable'), 'attr'=>['type'=>'checkbox','checked'=>$dtlACH['ach_enable']]];
            $layout['fields']['ach_bank']   = ['order'=>20,'label'=>lang('ach_bank'),   'attr'=>['value'=>$dtlACH['ach_bank']]];
            $layout['fields']['ach_routing']= ['order'=>30,'label'=>lang('ach_routing'),'attr'=>['value'=>str_pad($dtlACH['ach_routing'], 9, '0', STR_PAD_LEFT)]];
            $layout['fields']['ach_account']= ['order'=>40,'label'=>lang('ach_account'),'attr'=>['value'=>$dtlACH['ach_account']]];
            $layout['panels']['vendACH']    = ['title'=>"ACH Payment Details",'opts'=>['icon'=>'edi'],
                'divs' => ['ediInfo'=>['order'=>30,'type'=>'fields','keys'=>['ach_enable','ach_bank','ach_routing','ach_account']]]];
            $layout['divs']['vendACH'] = ['order'=>10,'type'=>'divs','classes'=>['areaView'],'divs'=>[
                    'ach' => ['order'=>10,'type'=>'panel','key'=>'vendACH','classes'=>['block50']]]];
        }
        msgDebug("\nstructure leaving manager = ".print_r($layout, true));
    }

    // ***************************************************************************************************************
    //                               Wallet Methods
    // ***************************************************************************************************************
    /* Not implemented:
     * - Retrieve a Credit Card / eCheck
     * - Lock Credit Card / eCheck
     * - Unlock Credit Card / eCheck
     *
     * Create a Credit Card / eCheck
     */
    public function add(&$layout)
    {
        if (empty($this->payfabric) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $token  = $this->payfabric->getToken();
        $params = ["customer=$this->pfID", "token=$token", "tender=CreditCard"];
        $address= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$this->cID");
        if (!empty($address['address1']))   { $params[] = "Street1=".urlencode($address['address1']); }
        if (!empty($address['address2']))   { $params[] = "Street2=".urlencode($address['address2']); }
        if (!empty($address['city']))       { $params[] = "City="   .urlencode($address['city']); }
        if (!empty($address['state']))      { $params[] = "State="  .urlencode($address['state']); }
        if (!empty($address['postal_code'])){ $params[] = "Zip="    .urlencode($address['postal_code']); }
        if (!empty($address['country']))    { $params[] = "Country=".urlencode($address['country']); }
        if (!empty($address['email']))      { $params[] = "Email="  .urlencode($address['email']); }
        if (!empty($address['telephone1'])) { $params[] = "Phone="  .urlencode($address['telephone1']); }
        $url    = ($this->mode=='test' ? PAYMENT_PAYFABRIC_WALLET_TEST : PAYMENT_PAYFABRIC_WALLET);
        $url   .= "Web/Wallet/Create?".implode("&", $params); // ."&ReturnURI=%23" {TENDER} = CreditCard or ECheck
        $layout= array_replace_recursive($layout, $this->viewIFrame($url));
    }
    /**
     * Retrieve expired Credit Cards and delete them
     */
    public function clean()
    {
        return msgAdd("This functionality is not yet working. Please submit a support ticket if you need this!");
    }
    /**
     * Remove Credit Card / eCheck if requested by Customer
     */
    public function delete(&$layout=[])
    {
        if (empty($this->payfabric) || !$security = validateAccess('j12_mgr', 4)) { return; }
        $cardID  = clean('cardID', 'cmd', 'get');
        $response= $this->payfabric->walletDelete($cardID);
        if (empty($response)) { return msgAdd("Error deleting the card!"); }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizPanelRefresh('wallet');"]]);
    }
    /**
     * Update a Credit Card / eCheck
     */
    public function edit(&$layout)
    {
        if (empty($this->payfabric) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $cardID= clean('cardID', 'cmd', 'get');
        $token = $this->payfabric->getToken();
        $url   = ($this->mode=='test' ? PAYMENT_PAYFABRIC_WALLET_TEST : PAYMENT_PAYFABRIC_WALLET);
        $url  .= "Web/Wallet/Edit?card=$cardID&token=$token"; // &ReturnURI=%23
        $layout= array_replace_recursive($layout, $this->viewIFrame($url));
    }
    /**
     * Retrieve Credit Cards / eChecks
     */
    public function list()
    {
        if (empty($this->payfabric) || !$security = validateAccess('j12_mgr', 2)) { return; }
        // Do this at the payment method as it is also performed while accepting payments
        return $this->payfabric->walletList($this->pfID);
    }
    /**
     * Reloads the credit cards in the combo after wallet add that was away from customer wallet tab
     */
    public function reload(&$layout=[])
    {
        if (empty($this->payfabric) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $this->payfabric->walletReload($layout, $this->pfID);
    }

    public function modifyID($srcID='', $destID='')
    {
        msgDebug("\nEntering modifyID with srcID = $srcID AND destID = $destID");
        if (!$security = validateAccess('j12_mgr', 2)) { return; }
        if (empty($srcID) || empty($destID)) { return msgAdd(lang('illegal_access')); }
        return $this->payfabric->walletRename($srcID, ['NewCustomerNumber'=>$destID]) ? true : false;
    }

    // ***************************************************************************************************************
    //                               Support Methods
    // ***************************************************************************************************************
    private function viewIFrame($httpUrl)
    {
        $jsHead = 'var iframe_ = \'<iframe src="'.$httpUrl.'" frameborder="0" style="border:0;width:100%;height:99.4%;"></iframe>\';';
        $jsReady= "jqBiz('#pnlIFrame').panel({ content: iframe_ });";
        $html = '<div id="pnlIFrame" style="width:420px;height:850px;"></div>';
        return ['type'=>'popup','title'=>lang('wallet'),'attr'=>['id'=>'winIFrame','width'=>420, 'height'=>850],
            'divs'   => ['main'=>['order'=>50,'type'=>'panel','key'=>'embed']],
            'panels' => ['embed' => ['order'=>10,'type'=>'divs','divs'=>[
                'iFrame' => ['order'=>10,'type'=>'html','html'=>$html]]]],
            'jsHead' => ['init'=>$jsHead],
            'jsReady'=> ['init'=>$jsReady]];
    }

    private function viewCard($card)
    {
        if (in_array($card['type'], ['checking'])) { // e-check
            $html = "<table style=\"width:100%;\"><tr><th>".$this->lang['name_on_card']."</th><th>".lang('address_type_b')."</th></tr>";
            $html.= "<tr><td>".html5('',['attr'=>['type'=>'address']])."</th><th>".html5('',['attr'=>['type'=>'address']])."</th></tr>";
            $html.= "<tr><td>".html5('',['attr'=>['type'=>'address']])."</th><th>".html5('',['attr'=>['type'=>'address']])."</th></tr>";
        } else { // credit card
            $default = !empty($card['IsDefaultCard']) ? "Default Card" : '<a style="color:blue;cursor:pointer" onClick="alert(\'Make me default\');">Set as Default</a>';
            $html = "<table style=\"width:100%;\"><tr><td>".$this->viewAddress($card)."</td><td style=\"text-align:right;\">$default</td></tr>";
        }
        $html .= '<tr><td style="text-align:left">'
            .html5('',['events'=>['onClick'=>"jqBiz('body').addClass('loading'); jsonAction('$this->moduleID/wallet/edit&cardID={$card['id']}', $this->cID);"],
                'attr'=>['type'=>'button','value'=>lang('edit')]])
            .'</td><td style="text-align:right">'
            .html5('',['events'=>['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('$this->moduleID/wallet/delete&cardID={$card['id']}', $this->cID);"],
                'attr'=>['type'=>'button','value'=>lang('delete')]])
            ."</td></tr></table>";
        return ['title'=>$card['text'],'opts'=>['icon'=>'wallet','collapsible'=>true,'collapsed'=>true],
            'divs' => ['body' => ['order'=>50,'type'=>'html','html'=>$html]]];
    }

    private function viewAddress($card=[])
    {
        $html  = "{$card['CardHolder']['FirstName']} {$card['CardHolder']['LastName']}<br />";
        $html .= "{$card['Billto']['Line1']}<br />";
        if (!empty($card['Billto']['Line2'])) { $html .= "{$card['Billto']['Line2']}<br />"; }
        if (!empty($card['Billto']['Line3'])) { $html .= "{$card['Billto']['Line3']}<br />"; }
        $html .= "{$card['Billto']['City']}, {$card['Billto']['State']}  {$card['Billto']['Zip']}<br />";
        $html .= "{$card['Billto']['Phone']} | {$card['Billto']['Email']}<br />";
        return $html;
    }
}
