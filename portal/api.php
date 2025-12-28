<?php
/**
 * Bizuno Portal entry point
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
 * @version    7.x Last Update: 2025-12-26
 * @filesource /portal/api.php
 */

namespace bizuno;

class portalApi
{
    private $ShipTaxSt= ['AR','CT','GA','IL','KS','KY','MI','MS','NE','NJ','NM','NY',
        'NC','ND','OH','OK','PA','RI','SC','SD','TN','TX','UT','VT','WA','WV','WI'];
    public  $lang;
    
    function __construct()
    {
        $iso = !empty($_COOKIE['bizunoEnv']['lang']) ? $_COOKIE['bizunoEnv']['lang'] : 'en_US';
        $lang = [];
        include(BIZUNO_FS_LIBRARY . "locale/$iso/portal.php"); // replace $lang
        $this->lang = $lang;
    }

    public function fs()
    {
        $fn   = $fBad = $eBad = false;
        $parts= explode('/', clean('src', 'path_rel', 'get'), 2);
        msgDebug("\nBIZUNO_DATA = ".(defined('BIZUNO_DATA') ? BIZUNO_DATA : 'undefined')." and parts = ".print_r($parts, true));
        if (defined('BIZUNO_DATA') && !empty(BIZUNO_DATA)) {
            if (!empty($parts[1])) {
                $io  = new io(); // needs BIZUNO_DATA
                $fn  = (empty($parts[0]) ? BIZUNO_FS_LIBRARY : BIZUNO_DATA).$parts[1];
                $ext = strtolower(pathinfo($parts[1], PATHINFO_EXTENSION));
                msgDebug("\nLooking for fn = $fn");
                $fBad= !file_exists($fn) ? true : false;
                $validExts = array_merge($io->getValidExt('image'), $io->getValidExt('script'));
                $eBad= !in_array($ext, $validExts) ? true : false;
            } else { $fBad = true; }
        } else { $fBad = true; }
        msgDebug("\neBad = $eBad and fBad = $fBad");
        if ($eBad || $fBad) { $fn = BIZUNO_FS_LIBRARY.'view/images/bizuno.png'; }
        // Send out the image
        header("Accept-Ranges: bytes");
        header("Content-Type: "  .getMimeType($fn));
        header("Content-Length: ".filesize($fn));
        header("Last-Modified: " .date(DATE_RFC2822, filemtime($fn)));
//msgDebugWrite();
        readfile($fn);
        exit();
    }

    /**
     * Generates the css for the users theme preference, also adds myExt icons
     * This needs to be 
     * @param type $layout
     */
    public function viewCSS()
    {
        $icnSet = clean('icons', ['format'=>'cmd','default'=>'default'], 'get');
        $path   = BIZUNO_FS_LIBRARY.'view/icons/';
        $pathURL= BIZUNO_URL_PORTAL.'/view/icons/';
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
            $this->addCSS($output, 16);
            $this->addCSS($output, 32);
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
    private function addCSS(&$output, $size=32)
    {
        $dirPath= BIZUNO_DATA    ."myExt/view/icons/{$size}x{$size}/";
        $dirURL = BIZUNO_URL_FS.(defined('BIZUNO_BIZID')?BIZUNO_BIZID:'0')."/myExt/view/icons/{$size}x{$size}/";
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
     * Builds the jQuery EasyUI extensions into a single loaded script
     */
    public function easyuiJS()
    {
        $basePath = BIZUNO_FS_LIBRARY.'/scripts/jquery-easyui-ext';
        $output  = '';
        $output .= file_get_contents("$basePath/portal/jquery.portal.js")           ."\n"; // Portal
        $output .= file_get_contents("$basePath/color/jquery.color.js")             ."\n"; // Color
        $output .= file_get_contents("$basePath/edatagrid/jquery.edatagrid.js")     ."\n"; // Editable DataGrid
        $output .= file_get_contents("$basePath/datagrid-filter/datagrid-filter.js")."\n"; // Datagrid Filter
        $output .= file_get_contents("$basePath/datagrid-dnd/datagrid-dnd.js")      ."\n"; // Datagrid Drag-n-Drop Rows
        $output .= file_get_contents("$basePath/texteditor/jquery.texteditor.js")   ."\n"; // Text Editor
        header("Content-type: text/javascript; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    /**
     * Builds the jQuery EasyUI extensions into a single loaded file, remaps icons to proper path on www.bizuno.com
     */
    public function easyuiCSS()
    {
        $basePath= BIZUNO_FS_LIBRARY.'scripts/jquery-easyui-ext';
        $icons   = [];
        $output  = '';
        $output .= file_get_contents("$basePath/texteditor/texteditor.css")   ."\n"; // Text Editor
        $this->mapImagePath($icons, $basePath, 'texteditor'); // map icons for this extension
        $output .= "\n".implode("\n", $icons);
        msgDebugWrite();
        header("Content-type: text/css; charset: UTF-8");
        header("Content-Length: ".strlen($output));
        echo $output;
        exit();
    }

    private function mapImagePath(&$icons, $basePath, $extName)
    {
        $imgPath = "$basePath/$extName/images";
        if (!is_dir($imgPath)) { return; }
        $urlPath = BIZUNO_URL_SCRIPTS."jquery-easyui-ext/$extName";
        $files = scandir($imgPath);
        foreach ($files as $file) {
            if ($file == "." || $file == "..") { continue; }
            $name   = substr($file, 0, strpos($file, '.'));
            $icons[]= ".icon-$name { background: url('$urlPath/images/$file') center center; }\n";
        }
    }

    public function lostPW(&$layout=[])
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->lostCreds($layout);
    }

    public function lostVal(&$layout=[])
    {
        require(BIZUNO_FS_LIBRARY . 'portal/viewMaint.php');
        $view = new portalViewMaint();
        $view->lostNewPW($layout);
    }

    /**
     * Signs a user out of Bizuno, destroys the cookie
     */
    public function logout(&$layout=[])
    {
        bizClrCookie('bizunoSession');
        $layout = array_replace_recursive($layout, ['type'=>'page', 'jsHead'=>['redir'=>"window.location='".BIZUNO_URL_PORTAL."';"]]);
        if (function_exists("\\bizuno\\portalLogout")) { portalLogout($layout); }
    }

    public function getBizRoles(&$layout=[])
    {
        $bizID = clean('bizID', 'alpha_num', 'get');
//      $bizDB = clean('bizDB', 'alpha_num', 'get');
        msgDebug("\nEntering getRoles with bizID = $bizID");
        if (empty($bizID) || !$this->validatePSrequest($bizID)) { return msgAdd('Illegal Access!'); }
        $result= dbMetaGet('%', 'bizuno_role');
        $roles = [];
        if (!empty($result)) {
            foreach ($result as $row) { $roles[] = ['roleID'=>$row['_rID'], 'label'=>$row['title']]; }
        } else { $roles = []; }
        $layout = array_replace_recursive($layout, ['content'=>['roles'=>$roles]]);
    }

    public function getSalesTax(&$layout=[])
    {
        $args  = [
            'total'   => clean('total',  'float',    'post'),
            'shipping'=> clean('freight','float',    'post'),
            'city'    => clean('city',   'text',     'post'),
            'state'   => clean('state',  'text',     'post'),
            'zipCode' => clean('zip',    'text',     'post'),
            'country' => clean('country','alpha_num','post')];
        // sanity check
        msgDebug("\nEntering getSalesTax with args = ".print_r($args, true));
        $this->cleanZip($args['zipCode'], $args['country']);
        if (empty($args['zipCode'])) { return msgAdd('There are no rates to display, postal code was not provided!'); }
        // see if shipping is taxable
        $taxShip = in_array($args['state'], $this->ShipTaxSt) ? true : false;
        // check for other state specific parameters

        // retrieve from the tax table
        $rate  = dbGetValue('sales_tax_map', 'tax_rate', "zipcode='{$args['zipCode']}'");
        msgDebug("\nresult of db query rate = ".print_r($rate, true));
        $tax   = !$taxShip ? floatval($args['total']) * $rate : (floatval($args['total']) + floatval($args['shipping'])) * $rate;
        $layout= ['type'=>'raw', 'content'=>json_encode(['sales_tax'=>round($tax, 2), 'rate'=>$rate, 'msg'=>''])];
    }

    private function cleanZip(&$zip, $country='USA')
    {
        $zip = preg_replace("/[^a-zA-Z0-9]/", "", strtoupper($zip));
        switch ($country) {
            case 'CA':
            case 'CAN': $zip = substr(trim($zip), 0, 6); break;
            case 'US':
            case 'USA': $zip = substr(trim($zip), 0, 5); break;
            default: // leave it alone
        }
    }

    public function shipGetRates(&$layout=[])
    {
        
        
        // @TODO - NEED TO VALIDATE CREDENTIALS FROM POST VARIABLES
        
        loadBusinessCache();
        compose('api', 'shipping', 'getRates', $layout);
    }
    
    public function orderAdd(&$layout=[])
    {

        // Need a new way to authenticate since the users are now local.

        $security = getUserCache('role', 'security');
        $security['prices_c'] = 2;
        $security['j10_mgr'] = 2; // Need both sales and sales order since user has an option.
        $security['j12_mgr'] = 2;
        setUserCache('role', 'security', $security);
        loadBusinessCache();
        compose('api', 'order', 'add', $layout);
    }
// 
    
    /**
     * Executes an EDI cron to poll ALL EDI sources for new orders.
     * @param array $layout
     * 
     * command: https://biz.mydomain.com?bizRt=portal/api/ediCron
     */
    public function ediCron(&$layout=[])
    {
        getLang('phreebooks');
        loadBusinessCache();
        $user   = getModuleCache('api', 'settings', 'phreesoft_api', 'api_user');
        $userID = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'id', "ctype_u='1' AND email='$user'");
        $profile= getMetaContact($userID, 'user_profile');
        msgDebug("\nEDI cron with user = $user and userID = $userID and profile = ".print_r($profile, true));
        setUserCache('profile', '', $profile);
        $role   = !empty($profile['role_id']) ? dbMetaGet($profile['role_id'], 'bizuno_role') : 0;
        setUserCache('role', '', $role);
        msgDebug("\nUser has been set, ready to compose");
        compose('phreebooks', 'ediAPI', 'ediGet', $layout);
    }
    
    /**
     * Handles customization calls for cron and unauthorized users where the response contains public data.
     * @param type $layout
     */
    public function myAPI()
    {
        if (file_exists(BIZUNO_DATA.'myExt/controllers/api/myAPI.php')) {
            require(BIZUNO_DATA.'myExt/controllers/api/myAPI.php');
            loadBusinessCache();           
            $ctl = new apiMyAPI();
            $ctl->goAction();
        }
    }

    /************************ Support Metohds **************************/
    private function validatePSrequest($bizID='')
    {
        msgDebug("\nEntering validatePSrequest with bizID = $bizID and remote address = ".$_SERVER['REMOTE_ADDR']." and PHREESOFT_IP = ".PHREESOFT_IP);
        return (PHREESOFT_IP==$_SERVER['REMOTE_ADDR']) ? true : false;
    }
}