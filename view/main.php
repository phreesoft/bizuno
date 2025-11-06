<?php
/*
 * Main view file, has common class and support functions
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
 * @version    7.x Last Update: 2025-11-06
 * @filesource /view/main.php
 */

namespace bizuno;

final class view
{
    private $server = 'https://www.bizuno.com';
    private $fontAwe= 'https://kit.fontawesome.com/302352c521.js';
    public  $html   = ''; // fuly formed HTML/data to send to client
    private $myDevice;

    function __construct($data=[])
    {
        // declare global data until all modules are converted to new nested structure
        global $viewData;
        $this->myDevice = !empty(getUserCache('profile', 'device')) ? getUserCache('profile', 'device') : 'desktop'; // options are desktop [default], tablet, or mobile
        $viewData = $data;
        $this->render($data); // dies after complete
    }

    /**
     * Main function to take the layout and build the HTML/AJAX response
     * @global array $msgStack - Any messages that had been set during processing
     * @param array $data - The layout to render from
     * @param string $scope -
     * @return string - Either HTML or JSON depending on expected response
     */
    private function render($data=[])
    {
        global $msgStack;
        dbWriteCache();
        $type = !empty($data['type']) ? $data['type'] : 'json';
        switch ($type) {
            case 'datagrid':
                $content = dbTableRead($data['datagrid'][$data['key']]);
                $content['message'] = $msgStack->error;
                msgDebug("\n datagrid results number of rows = ".(isset($content['rows']) ? sizeof($content['rows']) : 0));
                echo json_encode($content);
                $msgStack->debugWrite();
                exit();
            case 'metagrid':
                $content = dbMetaRead($data);
                $content['message'] = $msgStack->error;
                msgDebug("\n metagrid results = ".(isset($content['rows']) ? sizeof($content['rows']) : 0));
                echo json_encode($content);
                $msgStack->debugWrite();
                exit();
            case 'raw':
                msgDebug("\n sending type: raw and data = {$data['content']}");
                echo $data['content'];
                $msgStack->debugWrite();
                exit();
            case 'divHTML':
                $this->renderDivs($data); // may add JS, generates 'body'
                $dom = $this->html;
                $dom.= $this->renderJS($data);
                msgDebug("\n sending type: divHTML and data = $dom");
                echo $dom;
                $msgStack->debugWrite();
                break;
            case 'guest':
                $dom = $this->viewGuestDOM($data);
                msgDebug("\n sending type: guest and data = $dom");
                echo $dom;
                $msgStack->debugWrite();
                break;
            case 'migrate': // hybrid 
                $this->setEnvHTML($data);
                $dom  = "<!DOCTYPE HTML>\n<html>\n<head>\n".$this->renderHead($data)."</head>\n";
                $dom .= "  <body>\n".$this->renderGuestDivs($data)."  </body>\n";
                $dom .= "</html>\n";
                msgDebug("\n sending type: guest and data = $dom");
                echo $dom;
                $msgStack->debugWrite();
                break;
            case 'page':
                $dom = $this->viewPageDOM($data); // formats final HTML to specific host expectations
                msgDebug("\n sending type: page and data = $dom");
                echo $dom;
                $msgStack->debugWrite();
                break;
            case 'popup':
                $dom = $this->renderPopup($data); // make layout changes per device then treat like div
                msgDebug("\n sending type: popup and data = $dom");
                echo $dom;
                $msgStack->debugWrite();
                exit();
            case 'json':
            default:
                if (isset($data['action'])){ $data['content']['action']= $data['action']; }
                if (isset($data['divID'])) { $data['content']['divID'] = $data['divID']; }
                $this->renderDivs($data);
                $this->html .= $this->renderJS($data, false);
                $data['content']['html'] = empty($data['content']['html']) ? $this->html : $data['content']['html'].$this->html;
                $data['content']['message'] = $msgStack->error;
                msgDebug("\n json return (before encoding) = ".print_r($data['content'], true));
                if (strlen(ob_get_contents())) { ob_clean(); } // in case there is something there, this will clear everything to prevent json errors
                echo json_encode($data['content']);
                $msgStack->debugWrite();
                exit();
        }
    }

    private function viewGuestDOM($data)
    {
        msgDebug("\nEntering viewGuestDOM");
        // Page head Meta 
        $data['head']['metaTitle']   = ['order'=>20,'type'=>'html','html'=>'<title>'.'Bizuno'.'</title>'];
        $data['head']['metaTitle']   = ['order'=>22,'type'=>'html','html'=>'<meta name="robots" content="noindex">'];
        $data['head']['metaContent'] = ['order'=>24,'type'=>'html','html'=>'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'];
        $data['head']['metaViewport']= ['order'=>26,'type'=>'html','html'=>'<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.9, maximum-scale=0.9" />'];
        $data['head']['metaIcon']    = ['order'=>28,'type'=>'html','html'=>'<link rel="icon" type="image/png" href="'.BIZUNO_ICON.'" />'];
        // Page head CSS
        $data['head']['cssBizuno']   = ['order'=>46,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_FS.'0/view/portal.css" />'];
        // Page head JavaScript 
        $data['head']['jsjQuery']    = ['order'=>60,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_SCRIPTS.'jQuery-3.7.1.js"></script>'];
        $data['head']['jsBizuno']    = ['order'=>62,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_FS.'0/view/portal.js"></script>'];
        if (in_array($this->myDevice, ['mobile', 'tablet'])) { // add the mobile extensions
            $data['head']['metaMobile']= ['order'=>30,'type'=>'html','html'=>'<meta name="mobile-web-app-capable" content="yes" />'];
        }
        $dom  = "<!DOCTYPE HTML>\n<html>\n<head>\n".$this->renderHead($data)."</head>\n";
        $dom .= "  <body>\n".$this->renderGuestDivs($data)."  </body>\n";
        $dom .= "</html>\n";
        return $dom;
    }
    
    /**
     * Platform specific DOM for full page generation
     * @param type $data
     */
    private function viewPageDOM($data)
    {
        msgDebug("\nEntering viewPageDOM");
        $dom  = '';
        $this->setEnvHTML($data); // load the <head> HTML for pages
        if (in_array($this->myDevice, ['mobile', 'tablet'])) { // add the mobile extensions
            $data['head']['metaMobile']= ['order'=>30,'type'=>'html','html'=>'<meta name="mobile-web-app-capable" content="yes" />'];
//          $data['head']['cssMobile'] = ['order'=>50,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SCRIPTS.'jquery-easyui/themes/mobile.css" />'];
            $data['head']['jsMobile']  = ['order'=>66,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_SCRIPTS.'jquery-easyui/jquery.easyui.mobile.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
        }
        $dom .= "<!DOCTYPE HTML>\n<html>\n<head>\n".$this->renderHead($data)."</head>\n";
        if (in_array($this->myDevice, ['mobile', 'tablet'])) {
            $dom .= "<body>\n".'  <div class="easyui-navpanel" fit="true">'."\n".$this->renderDivsMobile($data)."  </div>\n";
            $dom .= $this->renderJS($data);
            $dom .= '  <iframe id="attachIFrame" src="" style="display:none;visibility:hidden;"></iframe>'; // For file downloads
            $dom .= '  <div class="modal"></div><div id="divChart"></div>';
            $dom .= '  <div id="navPopup" class="easyui-navpanel"></div>'."</body>";
        } else {
            $dom .= '  <body class="easyui-layout">'."\n".$this->renderDivs($data)."  </body>\n<!-- EOF-Layout-->\n";
        }
        $dom .= "</html>\n";
        return $dom;
    }

    /**
     * [Uses jQuery-easyUI] manually generates head used for full screen user access
     * @param array $data -
     * @return modified $data
     */
    private function setEnvHTML(&$data=[])
    {
        $icons   = getUserCache('profile', 'icons', false, 'default');
        $theme   = getUserCache('profile', 'theme', false, 'bizuno');
        if ($theme=='auto') { $theme = getuserCache('profile', 'mode')=='dark' ? 'black' : 'bizuno'; } // change to dark mode
        $logoPath= getModuleCache('bizuno', 'settings', 'company', 'logo');
        $favicon = $logoPath ? BIZBOOKS_URL_FS.getUserCache('business', 'bizID')."/images/$logoPath" : BIZUNO_ICON;
//        $cssPath = defined('BIZUNO_SHARED') && !empty(BIZUNO_SHARED) ? 'myPortal' : 'ispPortal';
        $js  = "var bizID = '".getUserCache('business','bizID', false, 0)."';\n";
        $js .= "var bizunoHome = '".BIZUNO_HOME."';\n";
        $js .= "var bizunoAjax = '".BIZUNO_AJAX."';\n";
        $js .= "var bizunoAjaxFS = '".BIZBOOKS_URL_FS."';\n";
        // Create page Head HTML
        $data['head']['metaTitle']   = ['order'=>20,'type'=>'html','html'=>'<title>'.(!empty($data['title']) ? $data['title'] : getModuleCache('bizuno', 'properties', 'title')).'</title>'];
        $data['head']['metaPath']    = ['order'=>22,'type'=>'html','html'=>'<!-- route:'.clean('bizRt',['format'=>'path_rel','default'=>''],'get').' -->'];
        $data['head']['metaContent'] = ['order'=>24,'type'=>'html','html'=>'<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'];
        $data['head']['metaViewport']= ['order'=>26,'type'=>'html','html'=>'<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=0.9, maximum-scale=0.9" />'];
        $data['head']['metaRobots']  = ['order'=>28,'type'=>'html','html'=>'<meta name="robots" content="noindex" />'];
        $data['head']['metaIcon']    = ['order'=>30,'type'=>'html','html'=>'<link rel="icon" type="image/png" href="'.$favicon.'" />'];
        // CSS Links
        $data['head']['cssTheme']    = ['order'=>40,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SCRIPTS .'jquery-easyui/themes/'.$theme.'/easyui.css" />'];
        $data['head']['cssIcon']     = ['order'=>42,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SCRIPTS .'jquery-easyui/themes/icon.css" />'];
        $data['head']['cssStyle']    = ['order'=>44,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZBOOKS_URL_FS.'0/view/easyUI/stylesheet.css&ver='.MODULE_BIZUNO_VERSION.'" />'];
        $data['head']['cssBizuno']   = ['order'=>46,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SRVR    .'?bizRt=portal/api/viewCSS&icons='.$icons.'" />'];
//      $data['head']['cssBizuno']   = ['order'=>46,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_AJAX    .'&bizRt=api/admin/viewCSS&icons='.$icons.'" />'];
        $data['head']['cssMobile']   = ['order'=>50,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SCRIPTS .'jquery-easyui/themes/mobile.css" />'];
        $data['head']['cssEasyExt']  = ['order'=>54,'type'=>'html','html'=>'<link rel="stylesheet" href="'.BIZUNO_SRVR    .'?bizRt=portal/api/easyuiCSS" />']; // combines all of the easyUI extension css
        // JavaScript Links 
        $data['head']['jsjQuery']    = ['order'=>60,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_SCRIPTS .'jQuery-3.7.1.js"></script>'];
        $data['head']['jsFontAwe']   = ['order'=>62,'type'=>'html','html'=>'<script type="text/javascript" src="'.$this->fontAwe .'" crossorigin="anonymous"></script>'];
        $data['head']['jsBizuno']    = ['order'=>64,'type'=>'html','html'=>'<script type="text/javascript">'.$js."</script>"];
        $data['head']['jsEasyUI']    = ['order'=>66,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_SCRIPTS .'jquery-easyui/jquery.easyui.min.js?ver='.MODULE_BIZUNO_VERSION.'"></script>'];
        $data['head']['jsCommon']    = ['order'=>78,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZBOOKS_URL_FS.'0/view/easyUI/common.js&ver='.MODULE_BIZUNO_VERSION.'"></script>']; 
        $data['head']['jsEasyExt']   = ['order'=>80,'type'=>'html','html'=>'<script type="text/javascript" src="'.BIZUNO_SRVR    .'?bizRt=portal/api/easyuiJS"></script>']; // combines all of the easyUI extension js
    }

    /**
     * Renders the HTML for the <head> tag
     * @param type $data
     */
    protected function renderHead(&$data=[])
    {
        global $html5;
        msgDebug("\nEntering renderHead");
        $html = '';
        $head = sortOrder($data['head']);
        if (!empty($head)) {
            foreach ($head as $value) {
                $html .= "\t";
                $html5->buildDiv($html, $value);
            }
        }
        if (!empty($data['jsHead'])) {
            $html .= '<script type="text/javascript">'."\n".implode("\n", $data['jsHead'])."\n</script>\n";
            $data['jsHead'] = [];
        }
        return $html;
    }

    /**
     *
     * @global \bizuno\class $html5
     * @param type $data
     * @return type
     */
    protected function renderGuestDivs($data)
    {
        global $html5;
        if (empty($data)) { return ''; }
        msgDebug("\nEntering renderGuestDivs");
        $this->html = $html5->buildDivs($data, 'divs'); // generates $this->html body but can add headers and footers
        $html = '';
        $html.= $this->html;
        $html.= $this->renderJS($data);
        return "$html\n";
    }

    /**
     *
     * @global \bizuno\class $html5
     * @param type $data
     * @return type
     */
    protected function renderDivs($data)
    {
        global $html5;
        if (empty($data)) { return ''; }
        $html = '';
        msgDebug("\nEntering renderDivs");
        $this->html = $html5->buildDivs($data, 'divs'); // generates $this->html body but can add headers and footers
        msgDebug("\n    Starting North region");
        if (!empty($data['north'])) { $html .= $html5->buildDivs($data, 'north')."<!-- EOF-North Region-->\n"; }
        msgDebug("\n    Starting South region");
        if (!empty($data['south'])) { $html .= $html5->buildDivs($data, 'south')."<!-- EOF-South Region-->\n"; }
        msgDebug("\n    Starting East region");
        if (!empty($data['east']))  { $html .= $html5->buildDivs($data, 'east') ."<!-- EOF-East Region-->\n"; }
        msgDebug("\n    Starting West region");
        if (!empty($data['west']))  { msgDebug("\nBuilding west"); $html .= $html5->buildDivs($data, 'west') ."<!-- EOF-West Region-->\n"; }
        $html .= '<div id="bizBody" data-options="region:\'center\'">'."\n";
        $html .= $this->html;
        $html .= $this->renderJS($data);
        $html .= "</div><!-- EOF-Center Region-->\n";
        // These need to be after the center region or they will not be there in admin pages
        $html .= '<iframe id="attachIFrame" src="" style="display:none;visibility:hidden;"></iframe>'; // For file downloads
        $html .= '<div class="modal"></div><div id="divChart"></div>'."\n";
        return "$html\n";
    }

    /**
     *
     * @global \bizuno\class $html5
     * @param type $data
     * @return type
     */
    protected function renderDivsMobile($data)
    {
        global $html5;
        msgDebug("\nEntering renderDivsMobile with data = ".print_r($data, true));
        if (empty($data)) { return ''; }
        $header = $footer = '';
        msgDebug("\nEntering renderDivsMobile");
        $this->html = $html5->buildDivs($data, 'divs'); // generates $this->html body but can add headers and footers
        if (!empty($data['header'])) {
            $header .= "<header>\n";
            $html5->buildDiv($header, $data['header']);
            $header .= "</header>\n";
        }
        if (!empty($data['footer'])) {
            $footer .= "<footer>\n";
            $html5->buildDiv($footer, $data['footer']);
            $footer .= "</footer>\n";
        }
        return "$header\n$footer\n".'<div id="bizBody">'."$this->html\n</div>\n";
    }

    /**
     *
     * @global \bizuno\class $html5
     * @param type $data
     * @param type $addMsg
     * @return string
     */
    protected function renderJS($data, $addMsg=true)
    {
        global $html5;
        $dom = '';
        msgDebug("\nEntering renderJS");
        if (!isset($data['jsHead']))   { $data['jsHead']  = []; }
        if (!isset($data['jsBody']))   { $data['jsBody']  = []; }
        if (!isset($data['jsReady']))  { $data['jsReady'] = []; }
        if (!isset($data['jsResize'])) { $data['jsResize']= []; }
        // gather everything together
        $jsHead  = array_merge($data['jsHead'],  $html5->jsHead);
        $jsBody  = array_merge($data['jsBody'],  $html5->jsBody);
        $jsReady = array_merge($data['jsReady'], $html5->jsReady);
        $jsResize= array_merge($data['jsResize'],$html5->jsResize);
        msgDebug("\n jsHead = ".print_r($jsHead, true));
        msgDebug("\n jsBody = ".print_r($jsBody, true));
        msgDebug("\n jsReady = ".print_r($jsReady, true));
        msgDebug("\n jsResize = ".print_r($jsResize, true));
        if (sizeof($jsResize)) { $jsHead['reSize'] = "
var windowWidth = jqBiz(window).width();
jqBiz(window).on('resize', function() {
  clearTimeout(window.resizeTimer);
  window.resizeTimer = setTimeout(function() { resizeEverything(); }, 1000);
});
function resizeEverything() { ".implode(" ", $jsResize)." }"; }
        if ($addMsg) { $jsReady['msgStack'] = $html5->addMsgStack(); }
        // Render the output
        if (!empty($jsHead)) { // first
            if ($this->is_multidimensional($jsHead)) { msgDebug("\nTrying to catch this error for jsHead: ".print_r($jsHead, true), 'trap'); } // trying to catch this weird error.
            $dom .= '<script type="text/javascript">'."\n".implode("\n", is_array($jsHead) ? $jsHead : (array)$jsHead)."\n</script>\n";
        }
        if (!empty($jsBody)) { // second
            $dom .= '<script type="text/javascript">'."\n".implode("\n", is_array($jsBody) ? $jsBody : (array)$jsBody)."\n</script>\n";
        }
        if (!empty($jsReady)) { // doc ready, last
            $dom .= '<script type="text/javascript">'."jqBiz(document).ready(function() {\n".implode("\n", (array)$jsReady)."\n});\n</script>\n";
        }
        return $dom;
    }

    function is_multidimensional($array=[]) {
        foreach ($array as $value) { if (is_array($value)) { return true; } }
        return false;
    }

    /**
     * Renders popups which vary based on the type of device
     * @global array $msgStack
     * @param type $data
     * @return type
     */
    public function renderPopup($data)
    {
        global $msgStack, $html5;
        switch($this->myDevice) {
            case 'mobile': // set a new panel
                if (biz_validate_user()) {
                    $data['header'] = ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
//                      'left'  => ['order'=>10,'type'=>'menu','size'=>'small','hideLabels'=>true,'classes'=>['m-left'], 'options'=>['plain'=>'true'],
//                          'data'=>$html5->layoutMenuLeft('back')],
                        'center'=> ['order'=>20,'type'=>'html','classes'=>['m-title'],'html'=>!empty($data['title']) ? $data['title'] : ''],
                        'right' => ['order'=>30,'type'=>'menu','size'=>'small','hideLabels'=>true,'classes'=>['m-right'],'options'=>['plain'=>'true'],
                            'data'=>$html5->layoutMenuLeft('back')]]];
                } else {
                    $data['header'] = ['classes'=>['m-toolbar'],'type'=>'divs','divs'=>[
                        'center'=> ['order'=>20,'type'=>'html','classes'=>['m-title'],'html'=>!empty($data['title']) ? $data['title'] : '']]];
                }
                $data['jsReady'][] = "jqBiz.mobile.go('#navPopup'); jqBiz.parser.parse('#navPopup');"; // load the div, init easyui components
                $dom  = $this->renderDivs($data);
                $dom .= $this->renderJS($data);
                $data['content']['action']= 'newDiv';
                $data['content']['html']  = $dom;
                break;
            case 'tablet':
            case 'desktop': // set a javascript popup window
            default:
                $data['content']['action']= 'window';
                $data['content']['title'] = $data['title'];
                $data['content'] = array_merge($data['content'], !empty($data['attr']) ? $data['attr'] : []);
                $this->renderDivs($data);
                $this->html .= $this->renderJS($data, false);
                $data['content']['html']  = empty($data['content']['html']) ? $this->html : $data['content']['html'].$this->html;
                break;
        }
        $data['content']['message'] = $msgStack->error;
        return json_encode($data['content']);
    }
}

/**
 * Formats a system value to the locale view format
 * @global array $currencies
 * @param mixed $value - value to be formatted
 * @param string $format - Specifies the formatting to apply
 * @return string
 */
function viewFormat($value, $format = '')
{
/*  if (empty($GLOBALS['DAVE_DEBUG'])) { $GLOBALS['DAVE_DEBUG'] = 0; }
    $GLOBALS['DAVE_DEBUG']++;
    if ($GLOBALS['DAVE_DEBUG'] > 100000) {
        msgAdd("Trap hit");
        msgDebugWrite();
        exit(); // Entering BuildDataArray
    } */
//  msgDebug("\nIn viewFormat value = $value and format = $format");
    switch ($format) {
        case 'blank':      return '';
        case 'blankNull':  return $value ? $value : '';
        case 'contactID':  return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'short_name',   "id='$value'")) ? $result : getModuleCache('bizuno', 'settings', 'company', 'id');
        case 'contactGID': return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'gov_id_number',"id='$value'")) ? $result : '';
        case 'contactName':if (!$value) { return ''; }
            return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'primary_name', "id='$value'")) ? $result : '';
        case 'contactType':return pullTableLabel(BIZUNO_DB_PREFIX.'contacts', 'type', $value);
        case 'cIDStatus':
            $result  = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'short_name',   "id='$value'");
            $statuses= getModuleCache('contacts', 'statuses');
            foreach ($statuses as $stat) { if ($stat['id']==$result) { return $stat['text']; } }
            return '';
        case 'cIDAttn':    return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'contact',   "id='$value'")) ? $result : '';
        case 'cIDTele1':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'telephone1',"id='$value'")) ? $result : '';
        case 'cIDTele4':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'telephone4',"id='$value'")) ? $result : '';
        case 'cIDEmail':   return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'email',     "id='$value'")) ? $result : '';
        case 'cIDWeb':     return ($result = dbGetValue(BIZUNO_DB_PREFIX.'contacts', 'website',   "id='$value'")) ? $result : '';
        case 'curNull0':
        case 'currency':
        case 'curLong':
        case 'curExc':     return viewCurrency($value, $format);
        case 'date':
        case 'dateNoY':    return viewDate($value, false, $format);
        case 'datetime':   return viewDate($value, true);
        case 'dateLong':   return viewDate($value).' '.substr($value, strpos($value, ' '));
        case 'encryptName':return ''; // no longer supported
        case 'glActive':  return !empty(getModuleCache('phreebooks', 'chart', $value, 'inactive')) ? lang('yes') : '';
        case 'glType':    $acct = getModuleCache('phreebooks', 'chart', $value, $value);
            if (!isset($acct['type'])) { return $value; }
            return lang("gl_acct_type_{$acct['type']}");
        case 'glTypeLbl': return is_numeric($value) ? lang("gl_acct_type_{$value}") : $value;
        case 'glTitle':   return getModuleCache('phreebooks', 'chart', $value, 'title');
        case 'fa_condition': return $value=='u' ? lang('used') : lang('new');
        case 'fa_type':   $types = getModuleCache('bizuno', 'options', 'faTypes');
                          return isset($types[$value]) ? $types[$value] : $value;
        case 'lc':        return mb_strtolower($value);
        case 'j_desc':    return lang("journal_id_$value");
        case 'json':      return json_decode($value, true);
        case 'neg':       return -$value;
        case 'n2wrd':     return viewNumToWords($value);
        case 'null0':     return (round((float)$value, 4) == 0) ? '' : $value;
        case 'number':    return number_format((float)$value, getModuleCache('bizuno', 'settings', 'locale', 'number_precision'), getModuleCache('bizuno', 'settings', 'locale', 'number_decimal'), getModuleCache('bizuno', 'settings', 'locale', 'number_thousand'));
        case 'percent':   return floatval($value) ? number_format($value * 100, 1)." %" : '';
        case 'printed':   return $value ? '' : lang('duplicate');
        case 'precise':   $output = number_format((float)$value, getModuleCache('bizuno', 'settings', 'locale', 'number_precision'));
            $zero = number_format(0, getModuleCache('bizuno', 'settings', 'locale', 'number_precision')); // to handle -0.00
            return ($output == '-'.$zero) ? $zero : $output;
        case 'rep_id':    $result = getContactById($value);
            return !empty($result['text']) ? $result['text'] : $value;
        case 'roleName':   $rID = intval($value); // pulls the role name from the contact ID
            $meta  = dbMetaGet(0, 'user_profile', 'contacts', $rID);
            $roleID= !empty($meta['role_id']) ? $meta['role_id'] : 0;
            if (!isset($GLOBALS['BIZUNO_ROLES'])) { $GLOBALS['BIZUNO_ROLES'] = dbMetaGet('%', 'bizuno_role'); }
            foreach ($GLOBALS['BIZUNO_ROLES'] as $row) { if ($roleID==$row['_rID']) { return $row['title']; } }
            return $rID;
        case 'rmaStatus': // pass the record status field and go from there
return "Needs Fixin: $value";
            // Get the meta from the journal Main ID, then the status code and map to getModulecache value
            return isset($extRtn->lang["rtn_status_".$value]) ? $extRtn->lang["rtn_status_".$value] : $value;
        case 'rnd0d':     return !is_numeric($value) ? $value : number_format(round($value, 0), 0, '.', '');
        case 'rnd2d':     return !is_numeric($value) ? $value : number_format(round($value, 2), 2, '.', '');
        case 'storeID':   $rID = intval($value);
            foreach (getModuleCache('bizuno', 'stores') as $row) { if ($rID==$row['id']) { return $row['text']; } }
            if ($rID == -1) { return lang('all'); }
            return $value;
        case 'stripTags': return html_entity_decode(strip_tags($value));
        case 'cTerms':    return viewTerms($value, true, 'c');
        case 'terms':     return viewTerms($value); // must be passed encoded terms, default terms will use customers default
        case 'terms_v':   return viewTerms($value, true, 'v');
        case 'today':     return biz_date('Y-m-d');
        case 'uc':        return mb_strtoupper($value);
        case 'yesBno':    return !empty($value) ? lang('yes') : '';
    }
    if (getModuleCache('phreeform', 'formatting', $format, 'function')) {
        $func = getModuleCache('phreeform', 'formatting')[$format]['function'];
        $fqfn = __NAMESPACE__."\\$func";
        if (!function_exists($fqfn)) {
            $module = getModuleCache('phreeform', 'formatting')[$format]['module'];
            $path = getModuleCache($module, 'properties', 'path');
            msgDebug("\nAutoloading module = $module and path = $path");
            if (!bizAutoLoad("{$path}functions.php", $func, 'function')) {
                msgDebug("\nFATAL ERROR looking for file {$path}functions.php and function $func and format $format, but did not find", 'trap');
                return $value;
            }
        }
        return $fqfn($value, $format);
    }
    if (substr($format, 0, 7) == 'metaFld') { // pull the value from the json encoded field, used for reports
        msgDebug("\nThis metaFld settings = ".print_r($GLOBALS['pfFieldSettings'], true));
        $field = !empty($GLOBALS['pfFieldSettings']['meta_index']) ? $GLOBALS['pfFieldSettings']['meta_index'] : false;
        if (!$field) { return 'Error - No index provided!'; }
        $data = json_decode($value, true);
        if (is_null($data)) { return 'Error - Data is not encoded!'; }
        return isset($data[$field]) ? $data[$field] : '';
    } elseif (substr($format, 0, 7) == 'jsonFld') { // pull the value from the json encoded field, used for forms
        msgDebug("\nThis field settings = ".print_r($GLOBALS['pfFieldSettings'], true));
        $field = !empty($GLOBALS['pfFieldSettings']->settings->procFld) ? $GLOBALS['pfFieldSettings']->settings->procFld : false;
        if (!$field) { return 'Error - No index provided!'; }
        $data = json_decode($value, true);
        if (is_null($data)) { return 'Error - Data is not encoded!'; }
        return isset($data[$field]) ? $data[$field] : '';
    } elseif (substr($format, 0, 5) == 'dbVal') { // retrieve a specific db field value from the reference $value field
        if (!$value) { return ''; }
        $tmp = explode(';', $format); // $format = dbVal;table;field;ref or dbVal;table;field:index;ref
        if (sizeof($tmp) <> 4) { return $value; } // wrong element count, return $value
        $fld = explode(':', $tmp[2]);
        $result = dbGetValue(BIZUNO_DB_PREFIX.$tmp[1], $fld[0], $tmp[3]."='$value'", false);
        if (isset($fld[1])) {
            $settings = json_decode($result, true);
            return isset($settings[$fld[1]]) ? $settings[$fld[1]] : 'set';
        } else { return $result ? $result : '-'; }
    } elseif (substr($format, 0, 5) == 'attch') { // see if the record has any attachments
        if (!$value) { return '0'; }
        $tmp = explode(':', $format); // $format = attch:path (including prefix)
        if (sizeof($tmp) <> 2) { return '0'; } // wrong element count, return 0
        $path = str_replace('idTBD', $value, $tmp[1]).'*';
        $result = glob(BIZUNO_DATA.$path);
        if ($result===false) { return '0'; }
        return sizeof($result) > 0 ? '1' : '0';
    } elseif (substr($format, 0, 5) == 'cache') {
        $tmp = explode(':', $format); // $format = cache:module:index
        if (sizeof($tmp) <> 3 || empty($value)) { return ''; } // wrong element count, return empty string
        return getModuleCache($tmp[1], $tmp[2], $value, false, $value);
    }
    return $value;
}

/**
 *
 * @param type $content
 * @param type $action
 */
function viewDashLink($left='', $right='', $action='')
{
    return '<div class="dashHover"><span style="width:100%;float:left;height:20px;"><span style="float:left" class="menuHide dashAction">'.$action.'</span>'.$left.'<span style="float:right">'.$right.'</span></span></div>';
}

/**
 *
 * @param type $content
 * @param type $action
 */
function viewDashList($content='', $action='')
{
    return '<div class="dashHover"><span style="width:100%;float:left">'.$content.'<span style="float:right" class="menuHide dashAction">'.$action.'</span></span></div>';
}

/**
 * This function takes the db formatted date and converts it into a locale specific format as defined in the settings
 * @param date $raw_date - raw date in db format
 * @param bool $long - [default: false] Long format
 * @param sring $action - [default: date] The desired output format
 * @return string - Formatted date for rendering
 */
function viewDate($raw_date = '', $long=false, $action='date')
{
    // from db to locale display format
    if (empty($raw_date) || $raw_date=='0000-00-00' || $raw_date=='0000-00-00 00:00:00' || strtolower($raw_date)=='null') { return ''; }
    $error  = false;
    $year   = substr($raw_date,  0, 4);
    $month  = substr($raw_date,  5, 2);
    $day    = substr($raw_date,  8, 2);
    $hour   = $long ? intval(substr($raw_date, 11, 2)) : 0;
    $minute = $long ? intval(substr($raw_date, 14, 2)) : 0;
    $second = $long ? intval(substr($raw_date, 17, 2)) : 0;
    if ($month<    1 || $month>   12) { $error = true; }
    if ($day  <    1 || $day  >   31) { $error = true; }
    if ($year < 1900 || $year > 2099) { $error = true; }
    if ($error) {
        $date_time = time();
    } else {
        $date_time = mktime($hour, $minute, $second, $month, $day, $year);
    }
    $format = getModuleCache('bizuno', 'settings', 'locale', 'date_short').($long ? ' h:i:s a' : '');
    if ($action=='dateNoY') { $format = trim(str_replace('Y', '', $format), "-./"); }// no year
    return biz_date($format, $date_time);
}

function viewDiv(&$output, $prop)
{
    global $html5;
    $html5->buildDiv($output, $prop);
}

/**
 * This function generates the format for a drop down based on an array
 * @param array $source - source data, typically pulled directly from the db
 * @param string $idField (default `id`) - specifies the associative key to use for the id field
 * @param string $textField (default `text`) - specifies the associative key to use for the description field
 * @param string $addNull (default false) - set to true to include 'None' at beginning of select list
 * @return array - data values ready to be rendered by function html5 for select element
 */
 function viewDropdown($source, $idField='id', $textField='text', $addNull=false)
{
    $output = $addNull ? [['id'=>'0', 'text'=>lang('none')]] : [];
    if (is_array($source)) { foreach ($source as $row) { $output[] = ['id'=>$row[$idField],'text'=>$row[$textField]]; } }
    return $output;
}

/**
 * Filters grid data to fit within the grid parameters, page, rows, sort, & order
 * @param array $arrData - data to filter
 * @param array $options - overrides for POST variables, values: page, rows, sort, order
 */
function viewGridFilter($arrData, $options=[])
{
    $maxRows= getModuleCache('bizuno', 'settings', 'general', 'max_rows');
    $page   = !empty($options['page']) ? $options['page'] : clean('page', ['format'=>'integer',  'default'=>1],       'post');
    $rows   = !empty($options['rows']) ? $options['rows'] : clean('rows', ['format'=>'integer',  'default'=>$maxRows],'post');
    $sort   = !empty($options['sort']) ? $options['sort'] : clean('sort', ['format'=>'cmd',      'default'=>''],       'post');
    $order  = !empty($options['order'])? $options['order']: clean('order',['format'=>'alpha_num','default'=>'asc'],    'post');
    $tmp = sortOrder($arrData, $sort, $order);
    return array_slice($tmp, ($page-1)*$rows, $rows);
}

/**
 *
 * @param type $data
 */
function viewDashJS(&$data)
{
    $data['jsBody']['jsHome'] = "
var widthPanel = 400;
var panels = new Array();
var numCols = Math.floor(windowWidth / widthPanel); // in pixels
function createDivs() {
    windowWidth = jqBiz(window).width();
    numCols = Math.floor(windowWidth / widthPanel); // in pixels
    jqBiz('#dashboard').remove(); // was .empty()
    var dashDiv = jqBiz('<div />').appendTo('#bizBody'); // try recreating the div from scratch
    dashDiv.attr('id', 'dashboard');
    for (var i = 0; i < numCols; i++) { jqBiz('#dashboard').append('<div></div>'); }
    jqBiz('#dashboard').portal( { border:false, onStateChange:function() { var state = getPortalState(); jqBiz.ajax({ url:'".BIZUNO_AJAX."&bizRt=bizuno/dashboard/organize&menuID='+menuID+'&numCols='+numCols+'&state='+state }); } });
    jqBiz.ajax({ url: '".BIZUNO_AJAX."&bizRt=bizuno/dashboard/render&menuID='+menuID+'&numCols='+numCols, success: addPanels });
}
function getPanelOptions(id) {
    for (var i=0; i<panels.length; i++) if (panels[i].id == id) return panels[i];
    return undefined;
}
function getPortalState() {
    var aa = [];
    for (var columnIndex=0; columnIndex<numCols; columnIndex++){
        var cc = [];
        var panels = jqBiz('#dashboard').portal('getPanels', columnIndex);
        for (var i=0; i<panels.length; i++) cc.push(panels[i].attr('id'));
        aa.push(cc.join(','));
    }
    return aa.join(':');
}
function addPanels(json) {
    if (json.message) displayMessage(json.message);
    for (var i=0; i<json.Dashboard.length; i++) { panels.push(json.Dashboard[i]); }
    var portalState = json.State;
    var columns     = portalState.split(':');
    for (var columnIndex=0; columnIndex<columns.length; columnIndex++){
        var cc = columns[columnIndex].split(',');
        for (var j=0; j<cc.length; j++) {
            var options = getPanelOptions(cc[j]);
            if (options) {
                var p = jqBiz('<div></div>').attr('id',options.id).appendTo('body');
                var panelHref = options.href;
                options.href = '';
                p.panel(options);
                if (isMobile()) { p.panel('panel').draggable('disable'); }
                p.panel({ href:panelHref,onBeforeClose:function() { if (confirm('".jsLang('msg_confirm_delete')."')) { dashboardDelete(this); } else { return false } } });
                jqBiz('#dashboard').portal('add',{ panel:p, columnIndex:columnIndex });
            }
        }
    }
}";
    $data['jsReady']['initDash'] = "createDivs();";
    $data['jsResize']['initDash']= "
    var panels = jqBiz('#dashboard').portal('getPanels');
    for (var i=0; i<panels.length; i++) { jqBiz('#dashboard').portal('remove', panels[i]); }
    panels = new Array();
    createDivs();";
}

/**
 * Pulls the average sales over the past 12 months of the specified SKU, with cache for multiple hits
 * @param type integer - number of sales, zero if not found or none
 */
function viewInvSales($sku='',$range='m12')
{
    if (empty($GLOBALS['invSkuSales'])) {
        $dates  = localeGetDates();
        $month0 = $dates['ThisYear'].'-'.substr('0'.$dates['ThisMonth'], -2).'-01';
        $monthE = localeCalculateDate($month0, 0,  1,  0);
        $month1 = localeCalculateDate($month0, 0, -1,  0);
        $month3 = localeCalculateDate($month0, 0, -3,  0);
        $month6 = localeCalculateDate($month0, 0, -6,  0);
        $month12= localeCalculateDate($month0, 0,  0, -1);
        $sql    = "SELECT m.post_date, m.journal_id, i.sku, i.qty FROM ".BIZUNO_DB_PREFIX."journal_main m JOIN ".BIZUNO_DB_PREFIX."journal_item i ON m.id=i.ref_id
            WHERE m.post_date >= '$month12' AND m.post_date < '$monthE' AND m.journal_id IN (12,13,14,16) AND i.sku<>'' ORDER BY i.sku";
        $stmt   = dbGetResult($sql);
        $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        msgDebug("\nReturned annual sales by SKU rows = ".sizeof($result));
        foreach ($result as $row) {
            if (empty($GLOBALS['invSkuSales'][$row['sku']])) { $GLOBALS['invSkuSales'][$row['sku']] = ['m0'=>0,'m1'=>0,'m3'=>0,'m6'=>0,'m12'=>0]; }
            if (in_array($row['journal_id'], [13,14])) { $row['qty'] = -$row['qty']; }
            if ($row['post_date'] >= $month0) { $GLOBALS['invSkuSales'][$row['sku']]['m0'] += $row['qty']; }
            else { // prior month(s)
                if ($row['post_date'] >= $month1) { $GLOBALS['invSkuSales'][$row['sku']]['m1'] += $row['qty'];    }
                if ($row['post_date'] >= $month3) { $GLOBALS['invSkuSales'][$row['sku']]['m3'] += $row['qty']/3;  }
                if ($row['post_date'] >= $month6) { $GLOBALS['invSkuSales'][$row['sku']]['m6'] += $row['qty']/6;  }
                $GLOBALS['invSkuSales'][$row['sku']]['m12']+= $row['qty']/12;
            }
        }
    }
    return !empty($GLOBALS['invSkuSales'][$sku][$range]) ? number_format($GLOBALS['invSkuSales'][$sku][$range], 2, '.', '') : 0;
}

/**
 * Calculates the min stock level and compares to current level, returns new min stock if in band else null
 * @param string $sku - db sku field
 */
function viewInvMinStk($sku)
{
    $tolerance= 0.10; // 10% tolerance band
    $yrSales  = viewInvSales($sku);
    $curMinStk= dbGetValue(BIZUNO_DB_PREFIX."inventory", ['qty_min','lead_time'], "sku='$sku'");
    $newMinStk= ($yrSales/12) * (($curMinStk['lead_time']/30) + 1); // 30 days of stock
    return abs($newMinStk - $curMinStk['qty_min']) > abs($curMinStk['qty_min'] * $tolerance) ? number_format($newMinStk,0) : '';
}

/**
 * This function takes a keyed array and converts it into a format needed to render a HTML drop down
 * @param array $source
 * @param boolean $addNone - inserts at the beginning a choice of None and returns a value of 0 if selected
 * @param boolean $addAll - inserts at the beginning a choice of All and returns a value of a if selected
 * @return array $output - contains array compatible with function HTML5 to render a drop down input element
 */
function viewKeyDropdown($source, $addNone=false, $addAll=false)
{
    $output = [];
    if (!empty($addNone)) { $output[] = ['id'=>'0', 'text'=>lang('none')]; }
    if (!empty($addAll))  { $output[] = ['id'=>'a', 'text'=>lang('all')]; }
    if (is_array($source)) { foreach ($source as $key => $value) { $output[] = ['id'=>$key, 'text'=>$value]; } }
    return $output;
}

function viewNumToWords($value=0)
{
    $lang = getUserCache('profile', 'language', false, 'en_US');
    if ($lang <> 'en_US') {
        if (file_exists(BIZBOOKS_ROOT."locale/".getUserCache('profile', 'language')."/functions.php")) { // PhreeBooks 5
            bizAutoLoad(BIZBOOKS_ROOT."locale/".getUserCache('profile', 'language')."/functions.php", 'viewCurrencyToWords', 'function');
        } elseif (file_exists(BIZUNO_DATA."locale/".getUserCache('profile', 'language')."/functions.php")) { // WordPress
            bizAutoLoad(BIZUNO_DATA."locale/".getUserCache('profile', 'language')."/functions.php", 'viewCurrencyToWords', 'function');
        }
    }
    bizAutoLoad(BIZBOOKS_ROOT."locale/en_US/functions.php", 'viewCurrencyToWords', 'function');
    return viewCurrencyToWords($value);
}

/**
 * Processes a string of data with a user specified process, returns unprocessed if function not found
 * @param mixed $strData - data to process
 * @param string $Process - process to apply to the data
 * @return mixed - processed string if process found, original string if not
 */
function viewProcess($strData, $Process=false)
{
    if (empty($Process)) { return $strData; }
    msgDebug("\nEntering viewProcess with strData = $strData and process = $Process");
    if ($Process && getModuleCache('phreeform', 'processing', $Process, 'function')) {
        $func = getModuleCache('phreeform', 'processing')[$Process]['function'];
        $fqfn = "\\bizuno\\$func";
        if (!function_exists($fqfn)) { // Try to find it
            $mID  = getModuleCache('phreeform', 'processing')[$Process]['module'];
            if (!bizAutoLoad(getModuleCache($mID, 'properties', 'path').'functions.php', $fqfn, 'function')) { return $strData; }
        }
        return $fqfn($strData, $Process);
    } elseif(strpos($Process, 'meta:')===0) { // it's a meta field, 
        // get the meta, the $strData should be the meta recordID
        $parts = explode(':', $Process); // format meta:field:table [default table: common]
        if (empty($parts[2])) { $parts[2]='common'; }
        switch($parts[2]) {
            case 'contacts':  $table='contacts'; break;
            case 'inventory': $table='inventory';break;
            case 'journal':   $table='journal';  break;
            default:
            case'common':     $table='common';   break;
        }
        $meta = dbMetaGet($strData, '', $table);
//        msgDebug("\nFetched meta = ".print_r($meta, true));
        return isset($meta[$parts[1]]) ? $meta[$parts[1]] : ''; // return null if not found
    }
    msgDebug(" ... function not found!");
    return $strData;
}

/**
 * Builds the select values of current active role in the system.
 */
function viewRoles()
{
    $roles = [];
    $result= dbMetaGet('%', 'bizuno_role');
    if (!empty($result)) { foreach ($result as $row) { $roles[] = ['id'=>$row['_rID'], 'text'=>$row['title']]; } }
    array_unshift($roles, ['roleID'=>0, 'label'=>lang('select')]);
    return sortOrder($roles, 'text');
}

/**
 * Determines the users screen size
 * small - mobile device, phone, restrict to one column
 * medium - tablet, ipad, restrict to two columns
 * large - laptop, desktop, unlimited columns
 */
function viewScreenSize()
{
    $size = 'large';
    return $size;
}

/**
 * Adds the select All to the list of available stores.
 * @return array - Bizuno cache of stores with All added to first entry
 */
function viewStores()
{
    $output = [['id'=>-1, 'text'=>lang('all')]];
//  $result = dbGetMulti(BIZUNO_DB_PREFIX.'contacts', "ctype_b='1'", 'short_name');
    $result = getModuleCache('bizuno', 'stores');
    foreach ($result as $row) { $output[] = ['id'=>$row['id'], 'text'=>$row['text']]; } // short_name
    return $output;
}

/**
 * Generates a value if at a sub menu dashboard page.
 * @param string $menuID - Derives from menuID get variable
 * @return array structure of menu
 */
function viewSubMenu($menuID=false) {
    if (!$menuID) { $menuID = clean('menuID', 'cmd', 'get'); }
    if (empty($menuID) || $menuID=='home') { return; } // only show submenu when viewing a dashboard
    $menus = dbGetRoleMenu();
    if (!isset($menus['menuBar']['child'][$menuID])) { return ''; }
    $prop = $menus['menuBar']['child'][$menuID];
    return $prop;
}

/**
 * Takes a text string and truncates it to a given length, if the string is longer will append ... to the truncated string
 * @param string $text - Text to test/truncate
 * @param type $length - (Default: 25) Maximum length of string
 * @return string - truncated string (with ...) of over length $length
 */
function viewText($text, $length=25)
{
    if (empty($text)) { $text=''; }
    return strlen($text)>$length ? substr($text, 0, $length).'...' : $text;
}

/**
 * This function pulls the available languages from the Locale folder and prepares for drop down menu
 */
function viewLanguages($skipDefault=false)
{
    $output = [];
    if (!$skipDefault) { $output[] = ['id'=>'','text'=>lang('default')]; }
    $output[]= ['id'=>'en_US','text'=>'English (U.S.) [en_US]']; // put English first
return $output;
}

function viewMgrBase($grid, $title, $domSuffix)
{
    $data = ['title'=> $title,
        'divs'     => [
//          'heading'        => ['order'=> 5,'type'=>'html',     'html'=>"<h1>$title</h1>"],
            "div{$domSuffix}"=> ['order'=>50,'type'=>'accordion','key' =>"acc{$domSuffix}"]],
        'accordion'=> ["acc{$domSuffix}"=>['divs'=>[
            "mgr{$domSuffix}" => ['order'=>40,'label'=>$title,         'type'=>'datagrid','key' =>"dg{$domSuffix}"],
            "dtl{$domSuffix}" => ['order'=>60,'label'=>lang('details'),'type'=>'html',    'html'=>'&nbsp;']]]],
        'datagrid' => ["dg{$domSuffix}"=>$grid],
        'jsReady'  => ['init'=>"bizFocus('search', 'dg{$domSuffix}');"]];
    return $data;
}

/**
 * Generates a list of available methods to render a pull down menu
 * @param string $module - Lists the module to pull methods from
 * @param string $type - Lists the grouping (default = 'methods')
 * @return array $output - active payment modules list ready for pull down display
 */
function viewMethods($module, $type='methods')
{
    $output = [];
    $methods = sortOrder(getModuleCache($module, $type));
    foreach ($methods as $mID => $value) {
        if (isset($value['status']) && $value['status']) {
            $output[] = ['id'=>$mID, 'text'=>$value['title'], 'order'=>$value['settings']['order']];
        }
    }
    return $output; // should be sorted during registry build
}

/**
 * This recursive function formats the structure needed by jquery easyUI to populate a tree remotely (by ajax call)
 * @param array $data - contains the tree structure information
 * @param integer $parent - database record id of the parent of a given element (used for recursion)
 * @return array $output - structured array ready to be sent back to browser (after json encoding)
 */
function viewTree($data, $parent=0, $sort=true)
{
    global $bizunoLang;
    $output  = [];
    $parents = [];
    foreach ($data as $idx => $row) {
        if (empty($row['parent_id'])) { $row['parent_id'] = 0; }
        $parents[$row['parent_id']] = $row['parent_id'];
        if (!empty($bizunoLang[$row['title']])) { $data[$idx]['title'] = lang($row['title']); }
    }
    if ($sort) { $data = sortOrder($data, 'title'); }
    foreach ($data as $row) {
        if ($row['parent_id'] != $parent) { continue; }
        $temp = ['id'=>$row['_rID'], 'text'=>lang($row['title'])];
        $attr = [];
        if (isset($row['url']))       { $attr['url']       = $row['url']; }
        if (isset($row['mime_type'])) { $attr['mime_type'] = $row['mime_type']; }
        if (sizeof($attr) > 0) { $temp['attributes'] = json_encode($attr); }
        if (in_array($temp['id'], $parents)) { // folder with contents
            $temp['state']    = 'closed';
            $temp['children'] = viewTree($data, $temp['id'], $sort);
        } elseif (isset($row['mime_type']) && $row['mime_type']=='dir') { // empty folder, force to be folder
            $temp['state']    = 'closed';
            $temp['children'] = [['text'=>lang('msg_no_documents')]];
        }
        $output[] = $temp;
    }
    return $output;
}

function trimTree(&$data)
{
    if (!isset($data['children'])) { return; } // leaf
    $allEmpty = true;
    foreach ($data['children'] as $idx => $child) {
        $childEmpty = true;
        $attr = !empty($data['children'][$idx]['attributes']) ? json_decode($data['children'][$idx]['attributes'], true) : [];
        if (isset($attr['mime_type']) && $attr['mime_type']=='dir') {
            msgDebug("\nTrimming branch {$child['text']}");
            trimTree($data['children'][$idx]);
        }
        if (!empty($data['children'][$idx]['id'])) { $childEmpty = $allEmpty = false; }
        if ($childEmpty) { unset($data['children'][$idx]); }
    }
    if ($allEmpty) {
        msgDebug("\nBranch {$data['text']} is empty unsetting id.");
        $data = ['id'=>false, 'children'=>[]];
    }
    $data['children'] = array_values($data['children']);
}

/**
 * Generates the textual display of payment terms from the encoded value
 * @param string $terms_encoded - Encoded terms to use as source data
 * @param boolean $short - (Default: true) if true, generates terms in short form, otherwise long form
 * @param type $type - (Default: c) Contact type, c - Customers, v - Vendors
 * @param type $inc_limit - (Default: false) Include the Credit Limit in the text as well
 * @return string
 */
function viewTerms($terms_encoded='', $short=true, $type='c', $inc_limit=false)
{
    if ($type=='id') { // type == id for cID passed
        $cID   = intval($terms_encoded);
        $result= dbGetValue(BIZUNO_DB_PREFIX.'contacts', ['terms',"ctype_{$type}"], "id=$cID");
        if (empty($result)) { return 'N/A'; }
        $type  = $result['type'];
        $terms_encoded = $result['terms'];
    }
    $idx = $type=='v' ? 'vendors' : 'customers';
    $terms_def = explode(':', getModuleCache('phreebooks', 'settings', $idx, 'terms'));
    if (!$terms_encoded) { $terms = $terms_def; }
    else                 { $terms = explode(':', $terms_encoded); }
    $credit_limit = isset($terms[4]) ? $terms[4] : (isset($terms_def[4]) ? $terms_def[4] : 1000);
    if ($terms[0]==0) { $terms = $terms_def; }
    $output = '';
    switch ($terms[0]) {
        default:
        case '0': // Default terms
        case '3': // Special terms
            if ((isset($terms[1]) || isset($terms[2])) && $terms[1]) { $output = sprintf($short ? lang('terms_discount_short') : lang('terms_discount'), $terms[1], $terms[2]).' '; }
            if (!isset($terms[3])) { $terms[3] = 30; }
            $output .=  sprintf($short ? lang('terms_net_short') : lang('terms_net'), $terms[3]);
            break;
        case '1': $output = lang('terms_cod');     break; // Cash on Delivery (COD)
        case '2': $output = lang('terms_prepaid'); break; // Prepaid
        case '4': $output = sprintf(lang('terms_date'), viewFormat($terms[3], 'date')); break; // Due on date
        case '5': $output = lang('terms_eom');     break; // Due at end of month
        case '6': $output = lang('terms_now');     break; // Due upon receipt
    }
    if ($inc_limit) { $output .= ' '.lang('terms_credit_limit').' '.viewFormat($credit_limit, 'currency'); }
    return $output;
}

/**
 * ISO source is always the default currency as all values are stored that way. Setting isoDest forces the default to be converted to that ISO
 * @global object $currencies - details of the ISO currency ->iso and ->rate
 * @param float $value
 * @param string $format - How to format the data
 * @return string - Formatted data to $currencies->iso if specified, else default currency
 */
function viewCurrency($value, $format='currency')
{
    global $currencies;
    if ($format=='curNull0' && (float)$value == 0) { return ''; }
    if (!is_numeric($value)) { return $value; }
    $isoDef = getDefaultCurrency();
    $iso    = !empty($currencies->iso)  ? $currencies->iso  : $isoDef;
    $isoVals= getModuleCache('phreebooks', 'currency', 'iso', $iso);
    if (empty($isoVals)) { $isoVals = ['dec_pt'=>'.', 'dec_len'=>2, 'sep'=>',', 'prefix'=>'$', 'suffix'=>'']; } // when not logged in default to USD
    $rate   = !empty($currencies->rate) ? $currencies->rate : ($iso==$isoDef ? 1 : $isoVals['value']);
    $newNum = number_format($value * $rate, $isoVals['dec_len'], $isoVals['dec_pt'], $isoVals['sep']);
    $zero   = number_format(0, $isoVals['dec_len']); // to handle -0.00
    if ($newNum == '-'.$zero)       { $newNum  = $zero; }
    if (!empty($isoVals['prefix'])) { $newNum  = $isoVals['prefix'].' '.$newNum; }
    if (!empty($isoVals['suffix'])) { $newNum .= ' '.$isoVals['suffix']; }
    msgDebug("\nviewCurrency default: $isoDef, used: $iso, rate: $rate, starting value = $value, ending value $newNum");
    return $newNum;
}

/**
 * This function builds the currency drop down based on the locale XML file.
 * @return multitype:multitype:NULL
 */
function viewCurrencySel($curData=[])
{
    $output = [];
    if (empty($curData)) { $curData= localeLoadDB(); }
    foreach ($curData->Locale as $value) {
        if (isset($value->Currency->ISO)) {
            $output[$value->Currency->ISO] = ['id'=>$value->Currency->ISO, 'text'=>$value->Currency->Title];
        }
    }
    return sortOrder($output, 'text');
}

function viewTimeZoneSel($locale=[])
{
    $zones = [];
    if (empty($locale)) { $locale= localeLoadDB(); }
    foreach ($locale->Timezone as $value) {
        $zones[] = ['id' => $value->Code, 'text'=> $value->Description];
    }
    return $zones;
}

/**
 * This function build a drop down array of users based on their assigned role
 * @param string $type (Default -> sales) - The role type to build list from, set to all for all users
 * @param boolean $inactive (Default - false) - Whether or not to include inactive users
 * @return array $output - formatted result ready for drop down field values
 */
function viewRoleDropdown($type='all', $inactive=false)
{
    msgDebug("\nEntering viewRoleDropdown with type = $type and inactive = ".($inactive?'true':'false'));
    $output = [];
    $users = getModuleCache('bizuno', 'users');
    foreach ($users as $user) {
        if (!$inactive && !empty($user['inactive'])) { continue; } // skip inactive
        if ($type=='all' || !empty($user['groups'][$type])) {
            $output[] = ['id'=>$user['id'], 'text'=>$user['text']];
        }
    }
    $ordered = sortOrder($output, 'text');
    array_unshift($ordered, ['id'=>0, 'text'=>lang('none')]);
    return $ordered;
}

/**
 * This function builds a drop down for sales tax selection drop down menus
 * @param string $type - Choices are [default] 'c' for customers or 'v' for vendors
 * @param string $opts - Choices are NULL, 'contacts' for Per Contact option or 'inventory' for Per Inventory item option
 * @return array - result ready for render
 */
function viewSalesTaxDropdown($type='c', $opts='')
{
    $output = [];
    if ($opts=='contacts')  { $output[] = ['id'=>'-1', 'text'=>lang('per_contact'),  'status'=>0, 'tax_rate'=>'-']; }
    if ($opts=='inventory') { $output[] = ['id'=>'-1', 'text'=>lang('per_inventory'),'status'=>0, 'tax_rate'=>'-']; }
    $output[] = ['id'=>'0', 'text'=>lang('none'), 'status'=>0, 'tax_rate'=>0];
    foreach (getModuleCache('phreebooks', 'sales_tax', $type, false, []) as $row) {
        if ($row['status'] == 0) { $output[] = ['id'=>$row['id'], 'text'=>$row['title'], 'status'=>$row['status'], 'tax_rate'=>$row['rate']]; }
    }
    return $output;
}

/**
 * Takes a number in full integer style and converts to short hand format MB, GB, etc.
 * @param string $path - Full path to the file including the users root folder (since the path is not part of the returned value)
 * @return string - Textual string in block size format
 */
function viewFilesize($path)
{
    $bytes = sprintf('%u', filesize($path));
    if ($bytes > 0) {
        $unit = intval(log($bytes, 1024));
        $units = ['B', 'KB', 'MB', 'GB'];
        if (array_key_exists($unit, $units) === true) { return sprintf('%d %s', $bytes / pow(1024, $unit), $units[$unit]); }
    }
    return $bytes;
}

/**
 * Takes a file extension and tries to determine the MIME type to assign to it.
 * @param string $type - extension of the file
 * @return string - MIME type code
 */
function viewMimeIcon($type)
{
    $icon = strtoupper($type);
    switch ($icon) {
        case 'DRW':
        case 'JPG':
        case 'JPEG':
        case 'GIF':
        case 'PNG': return 'mimeImg';
        case 'DIR': return 'mimeDir';
        case 'DOC':
        case 'FRM': return 'mimeDoc';
        case 'DRW': return 'mimeDrw';
        case 'PDF': return 'mimePdf';
        case 'PPT': return 'mimePpt';
        case 'ODS':
        case 'XLS': return 'mimeXls';
        case 'ZIP': return 'mimeZip';
        case 'HTM':
        case 'HTML':return 'mimeHtml';
        case 'PHP':
        case 'RPT':
        case 'TXT':
        default:    return 'mimeTxt';
    }
}

/**
 * This function builds the core structure for rendering HTML pages. It will include the menu and footer
 * @return array $data - structure for rendering HTML pages with header and footer
 */
function viewMain()
{
    global $html5;
    $menuID = clean('menuID', ['format'=>'cmd', 'default'=>'home'], 'get');
    $myDevice= !empty(getUserCache('profile', 'device')) ? getUserCache('profile', 'device') : 'desktop';
    switch ($myDevice) {
        case 'mobile':  $data = $html5->layoutMobile($menuID);  break;
        case 'tablet': // use desktop layout as the screen is big enough
        default:
        case 'desktop': $data = $html5->layoutDesktop($menuID); break;
    }
    if (!empty($GLOBALS['bizuno_not_installed'])) { unset($data['header'], $data['footer']); }
    return $data;
}

/**
 * Generates the main view for modules settings and properties. If the module has settings, the structure will be generated here as well
 * @param string $module - Module ID
 * @param array $structure - Current working structure, Typically will be empty array
 * @param string $lang -
 * @return array - Newly formed layout
 */
function adminStructure($module, $structure=[], $lang=[])
{
    $props= getModuleCache($module, 'properties');
    msgDebug("\nmodule $module properties = ".print_r($props, true));
    $title= getModuleCache($module, 'properties', 'title').' - '.lang('settings');
    $html = "<h1>$title</h1><p>".getModuleCache($module, 'properties', 'description').'</p>';
    $data = ['type'=>'divHTML', 'statsModule'=>$module, 'security'=>getUserCache('role', 'administrate'),
        'divs'    => [
            'intro'  => ['order'=>15,'type'=>'html', 'html'=>$html],
            'main'   => ['order'=>50,'type'=>'tabs','key'=>'tabAdmin']],
        'toolbars'=> ['tbAdmin' =>['icons'=>['save'=>['order'=>20,'events'=>['onClick'=>"jqBiz('#frmAdmin').submit();"]]]]],
        'forms'   => ['frmAdmin'=>['attr'=>['type'=>'form', 'action'=>BIZUNO_AJAX."&bizRt=$module/admin/adminSave"]]],
        'tabs'    => ['tabAdmin'=>['divs'=>['settings'=>['order'=>10,'label'=>lang('settings'),'type'=>'divs','divs'=>[
            'toolbar'=> ['order'=>10,'type'=>'toolbar',  'key' =>'tbAdmin'],
            'formBOF'=> ['order'=>15,'type'=>'form',     'key' =>'frmAdmin'],
            'body'   => ['order'=>50,'type'=>'accordion','key' =>'accSettings'],
            'formEOF'=> ['order'=>85,'type'=>'html',     'html'=>"</form>"]]]]]],
        'jsReady'=>['init'=>"ajaxForm('frmAdmin');"]];
    if (!empty($structure)) { adminSettings($data, $structure, $lang); }
    else                    { unset($data['tabs']['tabAdmin']['divs']['settings'], $data['jsReady']['init']); }
    $methDirs = []; // ['dashboards'];
    if     (isset($props['dirMethods']) && is_array($props['dirMethods'])) { $methDirs = array_merge($methDirs, $props['dirMethods']); }
    elseif (!empty($props['dirMethods'])) { $methDirs[] = $props['dirMethods']; }
    $order = 70;
    foreach ($methDirs as $folder) { // keys = 'totals', 'carriers', 'funnels', 'gateways', etc.
        $data['tabs']['tabAdmin']['divs']['tab'.$folder] = ['order'=>$order,'label'=>lang($folder),'type'=>'html','html'=>'',
            'options'=> ['href'=>"'".BIZUNO_AJAX."&bizRt=bizuno/settings/adminMethods&module=$module&folder=$folder'"]];
        $order++;
    }
    return $data; // since this now just a div, don't need viewMain()
}

function adminSettings(&$data, $structure, $lang)
{
    $order = 50;
    msgDebug("\nlang = ".print_r($lang, true));
    foreach ($structure as $category => $entry) {
        $data['accordion']['accSettings']['divs'][$category] = ['order'=>$order,'ui'=>'none','label'=>$entry['label'],'type'=>'list','key'=>$category];
        if (empty($entry['fields'])) { continue; }
        msgDebug("\naccordion data = ".print_r($entry['fields'], true));
        langFillLabels($entry['fields'], $lang);
        foreach ($entry['fields'] as $key => $props) {
            $props['attr']['id'] = $category."_".$key;
            if ( empty($props['attr']['type'])){ $props['attr']['type']= 'text'; }
            if ( empty($props['langKey']))     { $props['langKey']     = $key; }
            if ($props['attr']['type']=='password') { $props['attr']['value']= ''; }
            $label = isset($props['label'])? $props['label']: lang($key);
            $tip   = isset($props['tip'])  ? $props['tip']  : (isset($lang['set_'.$key]) ? $lang['set_'.$key] : '');
            $props['label']= !empty($lang[$props['langKey']."_lbl"]) ? $lang[$props['langKey']."_lbl"] : $label;
            $props['tip']  = !empty($lang[$props['langKey']."_tip"]) ? $lang[$props['langKey']."_tip"] : $tip;
            $props['desc'] = !empty($lang[$props['langKey']."_desc"])? $lang[$props['langKey']."_desc"]: '';
            $data['lists'][$category][$key] = $props;
        }
        $order++;
    }
}

/**
 * Builds the HTML for custom tabs, sorts, generates structure
 * @param array $data - Current working layout to modify
 * @param array $structure - Current structure to process data
 * @param string $module - Module ID
 * @param string $tabID - id of the tab container to insert tabs
 * @return string - Updated $data with custom tabs HTML added
 */
function customTabs(&$data, $module, $tabID)
{
    msgDebug("\nEntering customTabs with module = $module and tabID = $tabID");
    $metas = dbMetaGet('%', 'tabs');
    $tabs = [];
    metaIdxClean($metas);
    foreach ($metas as $meta) {
        if ($meta['table']<>$module) { continue; }
        $tabs[$meta['_rID']] = ['table_id'=>$meta['table'], 'sort_order'=>$meta['order'], 'title'=>$meta['title']];
    }
    msgDebug("\nread tabs = ".print_r($tabs, true));
    if (empty($tabs)) { return; }
    $data['fields'] = sortOrder($data['fields']);
    foreach ($data['fields'] as $key => $field) { // gather by groups
        if (isset($field['tab']) && $field['tab'] > 0) { $tabs[$field['tab']]['groups'][$field['group']]['fields'][$key] = $field; }
    }
    foreach ($tabs as $tID => $tab) {
        if (!isset($tab['groups'])) { continue; }
        if (!isset($tab['title'])) { $tab['title'] = 'Untitled'; }
        if (!isset($tab['group'])) { $tab['group'] = $tab['title']; }
        $groups = sortOrder($tab['groups']);
        $data['tabs'][$tabID]['divs']["tab_$tID"] = ['order'=>isset($tab['sort_order']) ? $tab['sort_order'] : 50,'label'=>$tab['title'],'type'=>'divs','classes'=>['areaView']];
        foreach ($groups as $gID =>$group) {
            if (empty($group['fields'])) { continue; }
            $keys = [];
            $title = isset($group['title']) ? $group['title'] : $gID;
            foreach ($group['fields'] as $fID => $field) {
                $keys[] = $fID;
                switch($field['attr']['type']) {
                    case 'radio':
                        $cur = isset($data['fields'][$fID]['attr']['value']) ? $data['fields'][$fID]['attr']['value'] : '';
                        foreach ($field['opts'] as $elem) {
                            $data['fields'][$fID]['attr']['value'] = $elem['id'];
                            $data['fields'][$fID]['attr']['checked'] = $cur == $elem['id'] ? true : false;
                            $data['fields'][$fID]['label'] = $elem['text'];
                        }
                        break;
                    case 'select': $data['fields'][$fID]['values'] = $field['opts']; // set the choices and render
                    default:
                }
            }
            $data['tabs'][$tabID]['divs']["tab_$tID"]['divs']["{$tID}_{$gID}"] = ['order'=>10,'type'=>'panel','classes'=>['block50'],'key'=>"tab_{$tID}_{$gID}"];
            $data['panels']["tab_{$tID}_{$gID}"] = ['label'=>$title,'type'=>'fields','keys'=>$keys];
        }
    }
    msgDebug("\nstructure = ".print_r($data, true));
}

/**
 * This function builds an HTML element based on the properties passed, element of type INPUT is the default if not specified
 * @param string $id - becomes the DOM id and name of the element
 * @param array $prop - structure of the HTML element
 * @return string - HTML5 compatible element
 */
function html5($id='', $prop=[])
{
    global $html5;
    return $html5->render($id, $prop);
}

/**
 * Adds HTML content to the specified queue in preparation to render.
 * @global class $html5 - UI HTML render class
 * @param string $html - Content to add to queue
 * @param type $type - [default: body] Which queue to use, choices are: jsHead, jsBody, jsReady, jsResize, and body
 */
function htmlQueue($html, $type='body')
{
    global $html5;
    switch ($type) {
        case 'jsHead':   $html5->jsHead[]  = $html; break;
        case 'jsBody':   $html5->jsBody[]  = $html; break;
        case 'jsReady':  $html5->jsReady[] = $html; break;
        case 'jsResize': $html5->jsResize[]= $html; break;
        default:
        case 'body':     $this->html .= $html; break;
    }
}

/**
 * Searches a given directory for a filename match and generates HTML if found
 * @param string $path - path from the users root to search
 * @param string $filename - File name to search for
 * @param integer $height - Height of the image, width is auto-sized by the browser
 * @return string - HTML of image
 */
function htmlFindImage($settings, $height=32)
{
    msgDebug("\nEntering htmlFindImage");
    if (strpos($settings['path'], 'BIZUNO_DATA/') ===0) { // use client folder path
        $url = bizAutoLoadMap($settings['url']);
    } else { // use the bizuno folder via api FS
        $url = BIZBOOKS_URL_FS."0/controllers/{$settings['module']}/{$settings['folder']}/{$settings['id']}/";
    }
    return html5('', ['attr'=>['type'=>'img','src'=>"{$url}logo.png", 'height'=>$height]]);
}

/**
 * @TODO - move to html5.php
 * @param type $id
 * @param type $field
 * @param type $type
 * @param type $xClicks
 * @return type
 */
function dgHtmlTaxData($id, $field, $type='c', $xClicks='')
{
    return "{type:'combogrid',options:{data: bizDefaults.taxRates.$type.rows,width:120,panelWidth:210,idField:'id',textField:'text',
        onClickRow:function (idx, data) { jqBiz('#$id').edatagrid('getRows')[curIndex]['$field'] = data.id; $xClicks },
        rowStyler:function(idx, row) { if (row.status==1) { return {class:'journal-waiting'}; } else if (row.status==2) { return {class:'row-inactive'}; }  },
        columns: [[{field:'id',hidden:true},{field:'text',width:120,title:'".jsLang('tax_rate_id')."'},{field:'tax_rate',width:70,title:'".jsLang('amount')."',align:'center'}]]
    }}";
}

/**
 * This function formats database data into a JavaScript array
 * @param array $dbData - raw data from database of rows matching given criteria
 * @param string $name - JavaScript variable name linked to the grid to populate with data
 * @param array $structure - used for identifying the formatting of data prior to building the string
 * @param array $override - map to replace database field name to the grid column name
 * @return string $output - JavaScript string of data used to populate grids
 */
function formatDatagrid($dbData, $name, $structure=[], $override=[])
{
    msgDebug("\nEntering formatDatagrid with name = $name and dbData = ".print_r($dbData, true));
    $rows = [];
    if (is_array($dbData)) {
        foreach ($dbData as $row) {
            $temp = [];
            foreach ($row as $field => $value) {
                if (isset($override[$field])) {
                    msgDebug("\nExecuting override = {$override[$field]['type']}");
                    switch ($override[$field]['type']) {
                        case 'trash': $field = false; break;
                        case 'field': $field = $override[$field]['index']; break;
                        default:
                    }
                }
                if (is_array($value) || is_object($value))     { $value = json_encode($value); }
                if (isset($structure[$field]['attr']['type'])) {
                    if ($structure[$field]['attr']['type'] == 'currency') { $structure[$field]['attr']['type'] = 'float'; }
                    $value = viewFormat($value, $structure[$field]['attr']['type']);
                }
                if (!empty($field)) { $temp[$field] = $value; }
            }
            $rows[] = $temp;
        }
    }
    return "var $name = ".json_encode(['total'=>sizeof($rows), 'rows'=>$rows]).";\n";
}
