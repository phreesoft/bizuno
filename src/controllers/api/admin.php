<?php
/*
 * Module api - admin class
 * 
 * Handles installation and registry initialization 
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
 * @version    7.x Last Update: 2025-06-05
 * @filesource /controllers/api/admin.php
 */

namespace bizuno;

bizAutoLoad(BIZBOOKS_ROOT.'controllers/api/common.php', 'apiCommon');

class apiAdmin extends apiCommon
{
    public $moduleID = 'api';
    public $methodDir= 'funnels';
    public $channels = ['ifAmazon','ifBigCom','ifGoogle','ifStripe','ifWooCommerce']; // 'ifWalmart'
    public $settings = [];
    public $structure= [];

    function __construct()
    {
        parent::__construct();
        $this->settings = array_replace_recursive(getStructureValues($this->settingsStructure()), getModuleCache($this->moduleID, 'settings', false, false, []));
        $this->structure= [
            'dirMethods'=> ['funnels'],
            'hooks'     => [
                'inventory' =>['main'=>['manager'=>['order'=>80,'method'=>'invManager']]],
                'phreebooks'=>['main'=>['edit'   =>['order'=>80,'method'=>'pbEdit']]]]];
        $order = 60;
        $props = getMetaMethod($this->methodDir);
        foreach ($props as $channel => $prop) { // set the methods on the menu bar
            if (empty($prop['status'])) { continue; }
            $this->structure['menuBar']['child']['customers']['child'][$channel] = ['order'=>$order,'label'=>$prop['title'],'icon'=>$channel,'events'=>['onClick'=>"hrefClick('api/admin/home&modID=$channel');"]];
            $order++;
        }
    }

    /**
     * User configurable settings structure
     * @return array structure for settings forms
     */
    private function settingsStructure()
    {
        $selAPI= [['id'=>'','text'=>lang('auto_detect')],['id'=>'10','text'=>lang('journal_id_10')],['id'=>'12','text'=>lang('journal_id_12')]];
        $data  = [
            'bizuno_api' => ['order'=>50,'label'=>lang('bizuno_api'),'fields'=>[
                'auto_detect'   => ['values'=>$selAPI,'attr'=>['type'=>'select']],
                'gl_receivables'=> ['attr'=>['type'=>'ledger','id'=>'bizuno_api_gl_receivables','value'=>getModuleCache('phreebooks','settings','customers','gl_receivables')]],
                'gl_sales'      => ['attr'=>['type'=>'ledger','id'=>'bizuno_api_gl_sales',      'value'=>getModuleCache('phreebooks','settings','customers','gl_sales')]],
                'gl_discount'   => ['attr'=>['type'=>'ledger','id'=>'bizuno_api_gl_discount',   'value'=>getModuleCache('phreebooks','settings','customers','gl_discount')]],
                'gl_tax'        => ['attr'=>['type'=>'ledger','id'=>'bizuno_api_gl_tax',        'value'=>getModuleCache('phreebooks','settings','customers','gl_liability')]],
                'tax_rate_id'   => ['values'=>viewSalesTaxDropdown('c'),'attr'=>['type'=>'select','value'=>0]]]],
            'phreesoft_api' => ['order'=>60,'label'=>lang('phreesoft_api'),'fields'=>[
                'api_user'      => ['attr'=>['value'=>'']],
                'api_pass'      => ['attr'=>['type'=>'password','value'=>'']]]]];
        settingsFill($data, $this->moduleID);
        return $data;
    }

    private function setMenu()
    {
    }

    /**
     * Loads the method object and returns valid object
     * @return modID object
     */
    private function getMethod()
    {
        $modID = clean('modID', 'cmd', 'get');
        $meta  = dbMetaGet(0, "methods_{$this->methodDir}");
        if (empty($meta[$modID])) { return msgAdd('Bad channel ID!'); }
// @TODO - remove after release of 7.1
$meta[$modID]['path'] = str_replace('bizuno-core', 'vendor/phreesoft/bizuno', $meta[$modID]['path']);
        msgDebug("\nEntering getMetehod with search pth = ".$meta[$modID]['path']."$modID.php");
        bizAutoLoad($meta[$modID]['path']."$modID.php");
        $fqdn = "\\bizuno\\$modID";
        $chan = new $fqdn();
        return $chan;
    }

    /**
     * Pulls up the requested home page
     * @param type $layout
     */
    public function home(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->home($layout);
    }

    public function cartConfirm(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->cartConfirm($layout);
    }

    public function apiInvCount(&$layout=[], $result=[])
    {
        $chan = $this->getMethod();
        $chan->apiInvCount($layout, $result);
    }

    /**
     * This method uploads a single inventory item to WooCommerce
     * @see apiImport::apiInventory()
     */
    public function productToStore(&$layout=[], $invID=0)
    {
        $chan = $this->getMethod();
        $chan->productToStore($layout, $invID);
    }

    public function cartSync(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->cartSync($layout);
    }

    public function confirmGo(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->confirmGo($layout);
    }

    public function getTaxVersion(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->getTaxVersion($layout);
    }

    public function getTaxTable(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->getTaxTable($layout);
    }

    public function inventoryGo(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->inventoryGo($layout);
    }

    public function invRefresh(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->invRefresh($layout);
    }

    public function invRefreshNext(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->invRefreshNext($layout);
    }

    public function ordersGo(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->ordersGo($layout);
    }

    /**
     * Hook to add the Amazon upload button to reconcile payments
     * @param type $layout
     * @return type
     */
    public function pbEdit(&$layout) // extends /phreebooks/main/edit
    {
        msgDebug("\nEntering api/admin/pbEdit");
        // foreach enabled module, see if they have to add anything to the edit screen
        $_GET['modID'] = 'ifAmazon';
        $chan = $this->getMethod();
        $chan->pbEdit($layout);
    }

    /**
     * This method is a hook to add an icon to the inventory manager to upload a single inventory item to the cart
     * @param $data - data structure, returned by reference
     */
    public function invManager(&$layout=[])
    {

        // foreach enabled module, see if they have to add anything to the edit screen
        $layout['datagrid']['manager']['columns']['woocommerce_sync'] = ['order'=>0,'field'=>'woocommerce_sync','attr'=>['hidden'=>true]];
        $layout['datagrid']['manager']['columns']['action']['actions']['woocommerce'] = ['icon'=>'ifWooCommerce','label'=>'Upload to WooComerce','size'=>'small','order'=>90,
            'display'=>"row.woocommerce_sync=='1'",'events'=>['onClick'=>"jsonAction('$this->moduleID/admin/productToStore&modID=ifWooCommerce', idTBD);"]];
    }

    /**
     * Generates the popup for reconciliation of cash receipts
     * @param array $layout
     * @return modified $layout
     */
    public function paymentFileForm(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->paymentFileForm($layout);
    }

    /**
     * Processes the cash receipts in bulk
     * @param type $layout
     */
    public function paymentProcess(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->paymentProcess($layout);
    }

    /**
     * Reconcile or process IF data
     * @param type $layout
     */
    public function reconcileGo(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->reconcile($layout);
    }

    /**
     * Pull the files for the reconcile grid
     * @param type $layout
     */
    public function reconcileList(&$layout=[])
    {
        $chan = $this->getMethod();
        $chan->reconcileGrid($layout);
    }

    /**
     * Generates the css for the users theme preference, also adds myExt icons
     * This needs to be 
     * @param type $layout
     */
    public function viewCSS()
    {
        $icnSet = clean('icons', ['format'=>'cmd','default'=>'default'], 'get');
        $path   = BIZBOOKS_ROOT  .'view/icons/';
        $pathURL= BIZBOOKS_URL_FS.'0/view/icons/';
        if (!file_exists("{$path}$icnSet.php")) { $icnSet = 'default'; }// icons cannot be found, use default
        $icons = [];
        $output="/* $icnSet */\n";
        require("{$path}$icnSet.php");
        foreach ($icons as $idx => $icon) {
            $output .= ".icon-$idx  { background:url('{$pathURL}$icnSet/16x16/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconM-$idx { background:url('{$pathURL}$icnSet/24x24/{$icon['path']}') no-repeat; }\n";
            $output .= ".iconL-$idx { background:url('{$pathURL}$icnSet/32x32/{$icon['path']}') no-repeat; }\n";
        }
        if (defined('BIZUNO_DATA')) {
            $this->addCSS($output, 'custom', '16');
            $this->addCSS($output, 'custom', '32');
        }
        header("Content-type: text/css; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    /**
     * 
     * @param type $output
     * @param type $type
     * @param type $size
     */
    private function addCSS(&$output, $type='pro', $size=32)
    {
        switch ($type) {
            default:
            case 'custom':
                $dirPath= BIZUNO_DATA    ."myExt/view/icons/{$size}x{$size}/";
                $dirURL = BIZBOOKS_URL_FS.getUserCache('business','bizID')."/myExt/view/icons/{$size}x{$size}/";
                break;
        }
        $suffix = $size == 32 ? 'L' : '';
        $output .= "/* $dirURL */\n";
        if (is_dir($dirPath)) {
            $icons = scandir($dirPath);
            foreach ($icons as $icon) {
                if ($icon=='.' || $icon=='..') { continue; }
                $path_parts = pathinfo($icon);
                $output .= ".icon{$suffix}-{$path_parts['filename']} { background:url('{$dirURL}$icon') no-repeat; }\n";
            }
        }
    }

    /**
     * Pulls the roles from the ISP hosted Bizuno database and returns to the PhreeSoft admin server
     * @param type $layout
     * @return types
     */
    public function getRoles(&$layout=[])
    {
        $bizID = clean('bizID', 'alpha_num', 'get');
        msgDebug("\nEntering getRoles with bizID = $bizID");
        if (empty($bizID) || !$this->validatePSrequest($bizID)) { return msgAdd('Illegal Access!'); }
        $result= dbMetaGet('%', 'bizuno_role');
        $roles = [];
        if (!empty($result)) {
            foreach ($result as $row) { $roles[] = ['roleID'=>$row['_rID'], 'label'=>$row['title']]; }
        } else {
            $roles = [];
        }
        $layout = array_replace_recursive($layout, ['content'=>['roles'=>$roles]]);
    }

    private function validatePSrequest($bizID='')
    {
        msgDebug("\nEntering validatePSrequest with bizID = $bizID and remote address = ".$_SERVER['REMOTE_ADDR']);
        return (PHREESOFT_IP==$_SERVER['REMOTE_ADDR']) ? true : false;
    }

    /**
     * Settings home screen
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function adminHome(&$layout=[])
    {
        if (!$security = validateAccess('admin', 1)) { return; }
        $layout = array_replace_recursive($layout, adminStructure($this->moduleID, $this->settingsStructure(), $this->lang));
    }
    
    /**
     * Saves the users settings
     */
    public function adminSave()
    {
        readModuleSettings($this->moduleID, $this->settingsStructure());
    }
    public function initialize() 
    {
        return true;
    }
    public function install()
    {
        msgDebug("\nEntering $this->moduleID:install");
        $bAdmin = new bizunoSettings();
        foreach ($this->structure['dirMethods'] as $dirMeth) { $bAdmin->adminInstMethods($this->moduleID, $dirMeth); }
    }
}
