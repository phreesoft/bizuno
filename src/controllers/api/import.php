<?php
/*
 * Functions to support API operations through Bizuno
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
 * @filesource /controllers/api/import.php
 */

namespace bizuno;

class apiImport
{
    public $moduleID = 'api';
    public $pageID = 'import';

    function __construct()
    {
        $this->lang = getLang('bizuno'); // needs to be hardcoded as this is extended by extensions
    }

    /**
     * Main entry point structure for the import/export operations
     * @param array $layout - structure coming in
     * @return modified structure
     */
    public function impExpMain(&$layout=[])
    {
        if (!$security = validateAccess('impexp', 2)) { return; }
        $title= lang('bizuno_impexp');
        $data = ['title'=> $title,
            'divs'    => [
                'toolbar'=> ['order'=>20,'type'=>'toolbar','key' =>'tbImpExp'],
                'heading'=> ['order'=>30,'type'=>'html',   'html'=>"<h1>$title</h1>"],
                'biz_io' => ['order'=>60,'type'=>'tabs',   'key' =>'tabImpExp']],
            'tabs'=>[
                'tabImpExp'=>['divs'=>['module'=>['order'=>10,'type'=>'divs','label'=>lang('module'),'divs'=>['body'=>['order'=>50,'type'=>'tabs','key'=>'tabAPI']]]]],
                'tabAPI'   => ['styles'=>['height'=>'300px'],'attr'=>['tabPosition'=>'left', 'fit'=>true, 'headerWidth'=>250]]],
            'lang'    => $this->lang];
        $apis = getModuleCache('bizuno', 'api', false, false, []);
        msgDebug("\nLooking for APIs = ".print_r($apis, true));
        foreach ($apis as $settings) {
            $parts= explode('/', $settings['path']);
            $path = bizAutoLoadMap(getModuleCache($parts[0], 'properties', 'path'));
            msgDebug("\npath = $path and parts = ".print_r($parts, true));
            if (empty($path)) { continue; }
            if (file_exists ($path."/{$parts[1]}.php")) {
                $fqcn = "\\bizuno\\".$parts[0].ucfirst($parts[1]);
                bizAutoLoad($path."/{$parts[1]}.php", $fqcn);
                $tmp = new $fqcn();
                $tmp->{$parts[2]}($data); // looks like phreebooksAPI($data)
            }
        }
        $layout = array_replace_recursive($layout, viewMain(), $data);
    }
}
