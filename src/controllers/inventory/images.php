<?php
/*
 * @name Bizuno ERP - Inventory Images Extension
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
 * @filesource /controllers/inventory/images.php
 */

namespace bizuno;

class inventoryImages
{
    public  $moduleID = 'inventory';

    function __construct()
    {
    }

    /**
     * Generates the extra images associated with this inventory item
     * @param array $layout - structure coming in
     */
    public function imagesLoad(&$layout=[])
    {
        $rID      = clean('rID', 'integer', 'get');
        $data     = ['fields'=>['invImageAdd'=>['icon'=>'add','label'=>lang('add_image', $this->moduleID),'events'=>['onClick'=>"invImagesAdd();"]]]];
        $imgCnt   = $needsUpdate = 0;
        $html = $jsReady = '';
        if ($rID) {
            // try to find the root folder by loading the main image
            $images  = dbGetValue(BIZUNO_DB_PREFIX.'inventory', ['image_with_path', 'invImages'], "id='$rID'");
            msgDebug("\nRetrieved images from db for skuID: $rID = ".print_r($images, true));
            $dirPath = !empty($images['image_with_path']) ? dirname($images['image_with_path']).'/' : '/';
            $theList = !empty($images['invImages']) ? json_decode($images['invImages'], true) : [];
            msgDebug("\ntheList: ".print_r($theList, true));
            foreach ($theList as $idx => $src) {
                if (!file_exists(BIZUNO_DATA.'images/'.$src)) { // clean out the list for orphaned images
                    unset($theList[$idx]);
                    $needsUpdate = 1;
                    continue;
                }
                $dirPath = dirname($src); 
                $html   .= '<div style="float:left;width:150px;height:150px;border:2px solid #a1a1a1;margin:5px">';
                $html   .= html5('invImg_'.$imgCnt, ['attr'=>  ['type'=>'hidden', 'value'=>$src]]);
                $jsReady.= "imgManagerInit('invImg_$imgCnt', '$src', '".dirname($src)."/');\n";
                $html   .= '</div>';
                $imgCnt++;
            }
            if ($needsUpdate) {
                msgDebug("\nUpdating db since image list has been changed outside of Bizuno to: ".print_r($theList, true));
                dbWrite(BIZUNO_DB_PREFIX.'inventory', ['invImages'=>json_encode($theList)], 'update', "id='$rID'");
            }
        }
        msgDebug("\nExtracted lastPath = $dirPath");
        $html .= '
<div id="divInvImgAdd" style="clear:both">'.html5('', $data['fields']['invImageAdd']).'</div>';
        $js = "var invImageCnt = $imgCnt;
var divInvImg = '".str_replace("\n", '', html5('invImg_divTBD', ['attr'=>  ['type'=>'hidden']]))."';
function invImagesAdd() {
    var divHTML = '<div style=\"float:left;width:150px;height:150px;border:2px solid #a1a1a1;margin:5px\">';
    divHTML += divInvImg.replace(/divTBD/g, invImageCnt)+'</div>';
    jqBiz('#divInvImgAdd').before(divHTML);
    imgManagerInit('invImg_'+invImageCnt, '', '$dirPath');
    invImageCnt++;
}";
        $layout = array_replace_recursive($layout, ['type'=>'divHTML',
            'divs'   => ['invImages'=>['order'=>50,'type'=>'html','html'=>$html]],
            'jsHead' => ['imgHead'=>$js],
            'jsReady'=> ['imgReady'=>$jsReady]]);
    }
}
