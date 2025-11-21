<?php
/*
 * Image manager popup
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
 * @version    7.x Last Update: 2025-06-25
 * @filesource /controllers/bizuno/image.php
 */

namespace bizuno;

class bizunoImage
{
    function __construct() { }

    /**
     * This method generates a popup window for managing images in the images/root folder
     * @param $layout - structure coming in
     */
    public function manager(&$layout=[])
    {
        global $io;
        if (!validateAccess('imgmgr', 1)) { return; }
        $dom    = clean('dom',  'alpha_num', 'get');
        $search = clean('imgSearch', 'text', 'get');
        $target = clean('imgTarget', 'text', 'get');
        $action = clean('imgAction', ['format'=>'text',    'default'=>''], 'get');
        $path   = clean('imgMgrPath',['format'=>'path_rel','default'=>'images/'], 'get');
        if ($action) {
            switch ($action) {
                case 'parent':  $path   = dirname($path); break;
                case 'refresh': $search = '';             break;
                case 'upload':  return $io->uploadSave('imgFile', "images/$path/", '', 'image');
            }
            if (substr($action, 0, 6) == 'newdir') {
                $parts = explode(":", $action);
                if (!empty($parts[1])) { // folder name
                    $io->fileWrite('<?php', str_replace('//', '/', "images/$path/{$parts[1]}/index.php"), true, false, true);
                    $action = 'refresh';
                } else { return msgAdd("Folder name is required!"); }
            }
        }
        $title = jsLang('image_manager').": $path/";
        $frmImgFields = html5('imgTarget', ['attr'=>['type'=>'hidden','value'=>$target]]);
        $frmImgFields.= html5('imgAction', ['attr'=>['type'=>'hidden','value'=>'']]);
        $frmImgFields.= html5('imgMgrPath',['attr'=>['type'=>'hidden','value'=>$path]]);
        $data = ['type'=>$dom=='page'?'page':'popup', 'title'=>jsLang('image_manager').": $path/",
            'attr'    => ['id'=>'winImgMgr','width'=>860,'height'=>600],
            'divs'    => [
                'frmStart'  => ['order'=>10,'type'=>'html',   'html'=>html5('frmImgMgr',['attr'=>['type'=>'form']])],
                'toolbar'   => ['order'=>20,'type'=>'toolbar','key' =>'tbImgMgr'],
                'frmEnd'    => ['order'=>30,'type'=>'html',   'html'=>'</form>'],
                'frmImgFlds'=> ['order'=>40,'type'=>'html',   'html'=>$frmImgFields],
                'images'    => ['order'=>50,'type'=>'html',   'html'=>$this->managerRows($path, $search, $target)]],
            'toolbars'=> ['tbImgMgr'=>['icons'=>[
                'imgClose'  => ['order'=>10,'icon'=>'close',  'label'=>lang('close'),  'events'=>['onClick'=>"bizWindowClose('winImgMgr');"]],
                'imgParent' => ['order'=>20,'icon'=>'up',     'label'=>lang('up'),     'events'=>['onClick'=>"imgAction('parent');"]],
                'imgRefresh'=> ['order'=>30,'icon'=>'refresh','label'=>lang('refresh'),'events'=>['onClick'=>"imgAction('refresh');"]],
                'imgNewDir' => ['order'=>40,'icon'=>'dirNew', 'label'=>lang('add_folder'),
                    'events'=> ['onClick'=>"var title=prompt('".lang('msg_new_folder_name')."'); if (title!=null) imgAction('newdir:'+title);"]],
                'imgSearch' => ['order'=>50,'type'=>'field',  'label'=>lang('search'), 'attr'  =>['value'  =>$search]],
                'imgSrchGo' => ['order'=>60,'icon'=>'search', 'label'=>lang('go'),     'events'=>['onClick'=>"imgAction('search');"]],
                'imgFile'   => ['order'=>70,'type'=>'field',  'attr'=>['type'=>'file']],
                'imgUpload' => ['order'=>80,'icon'=>'import', 'label'=>lang('upload'), 'events'=>['onClick'=>"imgAction('upload');"]]]]],
            'jsHead'  => ['init'=>$this->managerJS()],
            'jsReady' => ['init'=>"jqBiz('#winImgMgr').window({'title':'".addslashes($title)."'});"]];
        if (in_array($action, ['parent','refresh','search'])) { $data['type'] = 'divHTML'; } // just the window contents
        setUserCache('imgMgr', 'lastPath', $path);
            $layout = array_replace_recursive($layout, $data);
    }

    /**
     * lists the rows to populate the window per users search criteria
     * @param string $path - relative path from images root, must not contain trailing slash (/), empty string for root folder
     * @param string $srch - search string, if any
     * @param string $target - folder to put uploaded images
     * @return string - HTML to render window
     */
    private function managerRows($path='', $srch='', $target='')
    {
        global $io;
        msgDebug("\nFinding rows, working with path: $path");
        $output  = '';
        $search  = strtolower($srch);
        if (!is_dir(BIZUNO_DATA."images/$path")) { // if the folder is not there, make it
            $io->fileWrite('', str_replace('//', '/', "images/$path/index.php"), true, false, true);
        }
        $theList = scandir(BIZUNO_DATA."images/$path");
        msgDebug("\nWorking path is now: $path and the list = ".print_r($theList, true));
        foreach ($theList as $fn) {
            if ($fn=='.' || $fn=='..') { continue; }
            if ($search && strpos(strtolower($fn), $search) === false) { continue; }
            $newPath = clean("$path/$fn", 'path_rel'); // remove double slashes, if present
            if (is_dir(BIZUNO_DATA."images/$newPath")) {
                $src = BIZUNO_URL_FS."0/view/icons/default/32x32/folder.png";
                $isDir = true;
            } else {
                $ext = pathinfo(BIZUNO_DATA."images/$newPath", PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), $io->getValidExt('image'))) { continue; }
                $src = BIZUNO_URL_FS.getUserCache('business', 'bizID')."/images/$newPath";
                msgDebug("\nsrc for image = $src");
                $isDir = false;
            }
            $output .= '<div style="float:left;width:150px;height:150px;border:2px solid #a1a1a1;margin:5px"><div style="float:right">';
            $output .= html5('', ['icon'=>'trash','size'=>'small','label'=>lang('trash'),
                'events'=>  ['onClick'=>"if (confirm('".jsLang('msg_confirm_delete')."')) jsonAction('bizuno/image/delete&path=$path&fn=$fn&target=$target&search=$search');"]]);
            $output .= '</div><div>'.html5($fn, ['events'=>['onClick'=>"imgClickImg('$fn".($isDir?"/":'')."');"],'attr'=>['type'=>'img','height'=>125,'width'=>125,'src'=>$src]])."</div>";
            $output .= '<div>'.$fn.'</div></div>';
        }
        return $output;
    }

    /**
     *
     * @return type
     */
    private function managerJS()
    {
        return "function imgAction(action) { jqBiz('#imgAction').val(action); imgRefresh(); }
function imgClickImg(strImage) {
    var lastChar = strImage.substr(strImage.length - 1);
    if (lastChar == '/') {
        jqBiz('#imgMgrPath').val(jqBiz('#imgMgrPath').val()+'/'+strImage);
        jqBiz('#imgAction').val('refresh');
        imgRefresh();
    } else if (jqBiz('#imgTarget').val()) {
        var target = jqBiz('#imgTarget').val();
        var path   = jqBiz('#imgMgrPath').val();
        var fullPath= path ? path+'/'+strImage : strImage;
        jqBiz('#imgTarget').val(fullPath);
        jqBiz('#'+target).val(fullPath);
        jqBiz('#img_'+target).attr('src', bizunoAjaxFS+'&src=".getUserCache('business', 'bizID')."/images/'+fullPath);
        bizWindowClose('winImgMgr');
    }
}
function imgRefresh() {
    var target = jqBiz('#imgTarget').val();
    var path   = jqBiz('#imgMgrPath').val();
    var search = jqBiz('#imgSearch').val();
    var action = jqBiz('#imgAction').val();
    var shref  = '".BIZUNO_URL_AJAX."&bizRt=bizuno/image/manager&imgTarget='+target+'&imgMgrPath='+path+'&imgSearch='+search+'&imgAction=';
    if (action == 'upload') {
        jqBiz('#frmImgMgr').submit(function (e) {
            jqBiz.ajax({
                url:        shref+'upload',
                type:       'post',
                data:       new FormData(this),
                mimeType:   'multipart/form-data',
                contentType:false,
                cache:      false,
                processData:false,
                success:    function (data) { processJson(data); jqBiz('#winImgMgr').window('refresh',shref+'refresh'); }
            });
            e.preventDefault();
        });
        jqBiz('#frmImgMgr').submit();
    } else {
        jqBiz('#winImgMgr').window('refresh', shref+action);
    }
}";
    }

    /**
     * Deletes an image from a folder
     * @param array $layout - structure coming in
     * @return modified $layout
     */
    public function delete(&$layout=[])
    {
        global $io;
        if (!validateAccess('imgmgr', 4)) { return; }
        $target  = clean('target','text', 'get');
        $search  = clean('search','text', 'get');
        $path    = clean('path',  'path_rel','get');
        $fn      = clean('fn',    'text', 'get');
        $href    = BIZUNO_URL_AJAX."&bizRt=bizuno/image/manager&imgTarget=$target&imgMgrPath=$path&imgSearch=$search&imgAction=refresh";
        $fullPath= clean("images/$path/$fn", 'path_rel'); // remove double slashes, if present
        msgDebug("\nLooking for file to delete file or folder at full path: $fullPath");
        if (is_dir(BIZUNO_DATA.$fullPath)) {
            msgDebug("\nCalling io to Delete folder: $fullPath");
//          $files = scandir(BIZUNO_DATA.$fullPath); // includes . & .. & index.php plus maybe more if 'empty'
//          if (sizeof($files) > 2) { return msgAdd("Folder must be empty before it can be deleted! sizeof = ".sizeof($files)); }
            $io->folderDelete($fullPath);
        } else {
            msgDebug("\nCalling io to Delete file: $fullPath");
            $io->fileDelete($fullPath);
        }
        $layout = array_replace_recursive($layout, ['content'=>['action'=>'eval','actionData'=>"jqBiz('#winImgMgr').window('refresh','$href');"]]);
    }

    public function view(&$layout=[])
    {
        $bID   = clean('rID', 'integer', 'get');
        $img   = clean('data', 'path', 'get');
        $html  = html5('', ['styles'=>['max-width'=>'100%;','max-height'=>'100%;'],'events'=>['onClick'=>"jqBiz('#winImage').window('close');"],
            'attr'=>['type'=>'img','src'=>BIZUNO_URL_FS."$bID/images/$img"]]);
        $data  = ['type'=>'popup','title'=>lang('current_image'),'attr'=>['id'=>'winImage'], // ,'width'=>600,'height'=>600
            'divs'=>['winImage'=>['order'=>50,'type'=>'html','html'=>$html]]];
        $layout= array_replace_recursive($layout, $data);
    }
}
