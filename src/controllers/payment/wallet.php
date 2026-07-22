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
 * @copyright  2008-2026, PhreeSoft, Inc.
 * @license    https://www.gnu.org/licenses/agpl-3.0.txt
 * @version    7.x Last Update: 2026-07-22
 * @filesource /controllers/payment/wallet.php
 */

namespace bizuno;

class paymentWallet
{
    public  $moduleID = 'payment';
    public  $props;
    public  $cID;
    public  $type;
    public  $gateway;     // first installed, active wallet-capable gateway instance
    public  $gatewayCode; // e.g. 'payfabric', 'authorizenet'
    public  $pfID;

    function __construct()
    {
        $this->cID         = clean('rID', 'integer','get');
        $this->type        = clean('type','char',   'get');
        $this->pfID        = getWalletID($this->cID);
        $this->gateway     = $this->resolveGateway();
        $this->gatewayCode = $this->gateway ? $this->gateway->code : '';
        msgDebug("\nWallet manager bound to gateway: $this->gatewayCode");
    }

    /**
     * Pick the first active payment gateway that exposes a walletList() method.
     * Order is determined by the gateway's `order` setting (lowest first), matching
     * the priority used by the payment-method dropdown elsewhere. Returns null when
     * no installed gateway provides a wallet — callers must guard against that.
     */
    private function resolveGateway()
    {
        $methods = getMetaMethod('gateways');
        if (empty($methods) || !is_array($methods)) { return null; }
        $candidates = [];
        foreach ($methods as $key => $row) {
            if (empty($row['status']) || empty($row['path'])) { continue; }
            $code = !empty($row['id']) ? $row['id'] : $key;
            $candidates[] = ['code'=>$code, 'row'=>$row, 'order'=>!empty($row['settings']['order']) ? (int)$row['settings']['order'] : 99];
        }
        // Honor the explicit setting from /controllers/payment/admin.php first, fall back to auto-pick.
        $preferred = getModuleCache($this->moduleID, 'settings', 'general', 'wallet_provider', '');
        if ($preferred) {
            foreach ($candidates as $cand) {
                if ($cand['code'] === $preferred) {
                    if ($inst = $this->loadGatewayCandidate($cand)) { return $inst; }
                    break; // preferred gateway is set but failed to load — fall through to auto-pick
                }
            }
        }
        $candidates = sortOrder($candidates);
        foreach ($candidates as $cand) {
            if ($inst = $this->loadGatewayCandidate($cand)) { return $inst; }
        }
        return null;
    }

    /**
     * Try to instantiate a gateway candidate row and confirm it exposes walletList().
     * Returns the instance on success, null on any failure (silent — caller iterates).
     */
    private function loadGatewayCandidate($cand)
    {
        $code = $cand['code'];
        // bizAutoLoad resolves BIZUNO_FS_LIBRARY/BIZUNO_DATA placeholders inside $row['path'].
        if (!bizAutoLoad($cand['row']['path']."$code.php")) { return null; }
        $cls = "\\bizuno\\$code";
        if (!class_exists($cls)) { return null; }
        $inst = new $cls();
        if (!method_exists($inst, 'walletList')) { return null; }
        $this->props = $cand['row'];
        return $inst;
    }

    /**
     * Builds the view for the wallet tab on the contacts edit screen
     * @param array $layout - Structure
     */
    public function manager(&$layout=[])
    {
        if (!$security = validateAccess('j12_mgr', 2)) { return; }
        if (empty($this->gateway)) { // no wallet-capable gateway is installed/active
            $html  = "No payment gateway with wallet support is currently enabled. ";
            $html .= "Activate a wallet-capable gateway (e.g. PayFabric or Authorize.net) under Payment Methods to manage stored cards here.";
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
            'jsHead' => ['init'=>method_exists($this->gateway, 'eventJS') ? $this->gateway->eventJS($this->cID) : '']];
        if (empty($cards)) {
            $html = "The wallet is empty, let's add a credit card or e-check.";
            $layout['divs']['start'] = ['order'=> 5,'type'=>'html','html'=>$html];
        }
        if (in_array($this->type, ['v','c'])) {
            $dtlACH = dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['ach_bank', 'ach_routing', 'ach_account'], "id=$this->cID"); // 'ach_enable',
//          $layout['fields']['ach_enable'] = ['order'=>10,'label'=>lang('ach_enable'), 'attr'=>['type'=>'checkbox','checked'=>$dtlACH['ach_enable']]];
            $layout['fields']['ach_bank']   = ['order'=>20,'label'=>lang('ach_bank'),   'attr'=>['value'=>$dtlACH['ach_bank']]];
            $layout['fields']['ach_routing']= ['order'=>30,'label'=>lang('ach_routing'),'attr'=>['value'=>str_pad((string)$dtlACH['ach_routing'], 9, '0', STR_PAD_LEFT)]];
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
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $address = dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$this->cID") ?: [];
        if (method_exists($this->gateway, 'walletAddURL')) { // gateway-hosted iframe add (e.g. PayFabric)
            $url    = $this->gateway->walletAddURL($this->pfID, $address);
            $layout = array_replace_recursive($layout, $this->viewIFrame($url));
        } elseif (method_exists($this->gateway, 'walletAddForm')) { // native Bizuno card-entry form (e.g. Authorize.net)
            $layout = array_replace_recursive($layout, $this->gateway->walletAddForm($this->cID, $address));
        } else {
            return msgAdd("The {$this->gatewayCode} gateway does not support adding cards from this screen — cards are saved during a payment when the 'Save card to wallet' option is checked.", 'info');
        }
    }
    /**
     * Persist a card submitted from a native (non-iframe) add-card form. Only gateways that
     * collect the card on a Bizuno-rendered form (walletAddForm) reach this — iframe-hosted
     * gateways (PayFabric) post the card back to the gateway directly and never call save().
     */
    public function save(&$layout=[])
    {
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $cardID = clean('cardID', 'cmd', 'get'); // set only when editing an existing card
        if (!empty($cardID)) {
            if (!method_exists($this->gateway, 'walletEditSave')) {
                return msgAdd("The {$this->gatewayCode} gateway does not support editing cards from this screen.", 'info');
            }
            $result = $this->gateway->walletEditSave($this->cID, $cardID, $this->pfID);
        } else {
            if (!method_exists($this->gateway, 'walletAddSave')) {
                return msgAdd("The {$this->gatewayCode} gateway does not support saving cards from this screen.", 'info');
            }
            $result = $this->gateway->walletAddSave($this->cID, $this->pfID);
        }
        if (empty($result['ok'])) { return; } // the gateway already surfaced the reason via msgAdd()
        msgAdd(lang('msg_database_write'), 'success');
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizWindowClose('winCardAdd'); bizPanelRefresh('wallet');"]]);
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
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 4)) { return; }
        $cardID  = clean('cardID', 'cmd', 'get');
        $response= $this->gateway->walletDelete($cardID, $this->pfID);
        if (empty($response)) { return msgAdd("Error deleting the card!"); }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"bizPanelRefresh('wallet');"]]);
    }
    /**
     * Update a Credit Card / eCheck
     */
    public function edit(&$layout)
    {
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 2)) { return; }
        $cardID = clean('cardID', 'cmd', 'get');
        if (method_exists($this->gateway, 'walletEditURL')) { // gateway-hosted iframe edit (e.g. PayFabric)
            $url    = $this->gateway->walletEditURL($cardID);
            $layout = array_replace_recursive($layout, $this->viewIFrame($url));
        } elseif (method_exists($this->gateway, 'walletEditForm')) { // native Bizuno card-entry form (e.g. Authorize.net)
            $address= dbGetRow(BIZUNO_DB_PREFIX.'contacts', "id=$this->cID") ?: [];
            $form   = $this->gateway->walletEditForm($this->cID, $cardID, $address);
            if (empty($form)) { return; } // the gateway already surfaced the reason via msgAdd()
            $layout = array_replace_recursive($layout, $form);
        } else {
            return msgAdd("The {$this->gatewayCode} gateway does not support editing cards from this screen.", 'info');
        }
    }
    /**
     * Retrieve Credit Cards / eChecks
     */
    public function list()
    {
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 2)) { return []; }
        return $this->gateway->walletList($this->pfID);
    }
    /**
     * Reloads the credit cards in the combo after wallet add that was away from customer wallet tab
     */
    public function reload(&$layout=[])
    {
        if (empty($this->gateway) || !$security = validateAccess('j12_mgr', 2)) { return; }
        if (!method_exists($this->gateway, 'walletReload')) { return; }
        $this->gateway->walletReload($layout, $this->pfID);
    }

    public function modifyID($srcID='', $destID='')
    {
        msgDebug("\nEntering modifyID with srcID = $srcID AND destID = $destID");
        if (!$security = validateAccess('j12_mgr', 2)) { return; }
        if (empty($srcID) || empty($destID)) { return msgAdd(lang('illegal_access')); }
        if (empty($this->gateway) || !method_exists($this->gateway, 'walletRename')) { return false; }
        return $this->gateway->walletRename($srcID, ['NewCustomerNumber'=>$destID]) ? true : false;
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
            $html = "<table style=\"width:100%;\"><tr><th>".lang('name_on_card', $this->moduleID)."</th><th>".lang('address_type_b')."</th></tr>";
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
        $holder = $card['CardHolder'] ?? [];
        $bill   = $card['Billto']     ?? [];
        $name   = trim(($holder['FirstName'] ?? '').' '.($holder['LastName'] ?? ''));
        $cs     = trim(($bill['City'] ?? '').($bill['State'] ?? '') ? ($bill['City'] ?? '').', '.($bill['State'] ?? '').'  '.($bill['Zip'] ?? '') : '');
        $contact= trim((!empty($bill['Phone']) ? $bill['Phone'] : '').(!empty($bill['Phone']) && !empty($bill['Email']) ? ' | ' : '').($bill['Email'] ?? ''));
        $html   = '';
        if ($name)               { $html .= "$name<br />"; }
        if (!empty($bill['Line1'])) { $html .= "{$bill['Line1']}<br />"; }
        if (!empty($bill['Line2'])) { $html .= "{$bill['Line2']}<br />"; }
        if (!empty($bill['Line3'])) { $html .= "{$bill['Line3']}<br />"; }
        if ($cs)                 { $html .= "$cs<br />"; }
        if ($contact)            { $html .= "$contact<br />"; }
        if ($html === '')        { $html = '<em>(no billing address on file)</em>'; }
        return $html;
    }
}
