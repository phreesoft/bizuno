<?php
/*
 * Administration functions for bizAdmin controller
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
 * @license    http://opensource.org/licenses/OSL-3.0  Open Software License (OSL 3.0)
 * @version    1.x Last Update: 2025-08-03
 * @filesource /controllers/bizAdmin/admin.php
 */

/*
 * Structure of a file:
   wp_posts table:
    'post_title'    => [string] Post title (stripped of file extension)
    'post_author'   => [integer] if left empty, defaults to current user
    'post_parent'   => [integer] Parent Id of the post, 0 is the root (home) folder
    'post_mime_type'=> [string] Mime type as needed for proper upload and download , i.e. application/pdf
    'post_content'  => [string] filename.ext // Full filename including extension
    'post_type'     => CONST bizoffice // constant, identifies the post as a bizuno office item
    'post_status'   => [string] default => draft // Can also be trash, revision, symlink
    'comment_status'=> CONST closed // constant
    'ping_status'   => CONST closed // constant
   wp_postmeta:
    'mycolors_12345678'    => '' [string] // for styling icons for easier reference, value is wp_user->ID with permission
    '_is_star_12345678'  => 1 [boolean] // zero or more, file has been starred by user, value is wp_user->ID with permission
    'can_edit_12345678' => 1 [boolean] // zero or more, leave empty or 0 for no edit capabililty, value is wp_user->ID with permission
    'can_read_12345678' => 1 [boolean] // zero or more, leave empty or 0 for no read capabililty, value is wp_user->ID with permission
    '_symlink_12345678'  => 1 [integer] // For symlinks points to the source post for action, value is wp_posts->ID of source file
 */

namespace bizoffice;

class bizAdmin
{
    public  $moduleID= 'bizAdmin';
    private $bizPath = 'data/office';
    private $validExt= ['.bz2','.gif','.jpeg','.jpg','.ods','.pdf','.png','.zip']; // valid extensions for upload
    private $defColor= 'deepskyblue';
    private $jsHead  = [];
    private $jsBody  = [];
    private $jsReady = [];
    private $jsResize= [];
    private $files   = [];

    function __construct()
    {
        $this->wpUser  = wp_get_current_user();
        $this->lang    = loadBaseLang();
        $this->postID  = clean('rID',   ['format'=>'integer',  'default'=>0],     'get');
        $this->parentID= clean('parent',['format'=>'integer',  'default'=>0],     'get');
        $this->scope   = clean('scope', ['format'=>'alpha_num','default'=>'home'],'get');
        $this->pathInfo= $this->getPathInfo();
        msgDebug("\nFinished __construct with scope = $this->scope and parentID = $this->parentID and postID = $this->postID and pathInfo = ".print_r($this->pathInfo, true));
    }

    /**
     * generates the structure for the home page
     */
    public function main()
    {
        $homeImg = '<img type="img" src="'.BIZOFFICE_URL.'bizuno.png" height="48">';
        $btnApp  = '<button onClick="bizMenuClick(\'folder\', xPosition, yPosition+5);"><span style="font-size:3em; color:deepskyblue;"><i class="fad fa-plus"></i></span><span style="font-size:1.5em;"> '.lang('new').'</span></button>';
        $btnAcct = '<button onClick="bizMenuClick(\'acct\',xPosition-150, yPosition+5);"><span style="font-size:3em; color:deepskyblue;"><i class="fad fa-user-circle"></i></span><span style="font-size:1.5em;"> '.$this->wpUser->display_name.'</span></button>';
        $btnApps = '<button onClick="bizMenuClick(\'apps\',xPosition-150, yPosition+5);"><span style="font-size:3em; color:deepskyblue;"><i class="fad fa-th"></i></span><span style="font-size:1.5em;">&nbsp;</span></button>';
        $modalHtm= '<div id="bizModal" class="modal"><span id="bizModalClose" class="close">&times;</span><img class="modal-content" id="bizModalImg"><div id="bizModalCaption"></div></div>';
        $this->jsReady['init']  = "bizLayoutInit();";
        $this->jsReady['menus'] = "bizMenuInit();";
        $this->jsReady['layout']= "$('.ui-layout-resizer').css('background',''); $('.ui-layout-toggler').css('background-color','#ffffff');";
        $this->getFiles();
        $layout = ['type'=>'page',
            'divs'  => [
                'north'=> ['order'=>10,'type'=>'divs','classes'=>['ui-layout-north'], 'divs'=>[
                    'left'  => ['order'=>10,'type'=>'html','styles'=>['float'=>'left'], 'html'=>$homeImg],
                    'right' => ['order'=>20,'type'=>'divs','styles'=>['float'=>'right'],'divs'=>[
                        'acct' => ['order'=>10,'type'=>'html','styles'=>['float'=>'right'],'html'=>$btnAcct],
                        'apps' => ['order'=>20,'type'=>'html','styles'=>['float'=>'right'],'html'=>$btnApps]]],
                    'center'=> ['order'=>30,'type'=>'html','classes'=>['center-head'],  'html'=>'SEARCH HERE'],
                    ]],
//              'south'=> ['order'=>90,'type'=>'html','classes'=>['ui-layout-south'], 'html'=>'SOUTH'],
//              'east' => ['order'=>70,'type'=>'html','classes'=>['ui-layout-east'],  'html'=>'EAST'],
                'west' => ['order'=>30,'type'=>'divs','classes'=>['ui-layout-west'],  'divs'=>[
                    'newApp'=> ['order'=>10,'type'=>'html','html'=>$btnApp],
                    'tree'  => ['order'=>20,'type'=>'html','html' =>'<div id="jstree"></div>']]],
                'body' => ['order'=>50,'type'=>'divs','classes'=>['ui-layout-center'],'styles'=>['height'=>'100%'],'divs'=>[
                    'drop' => ['order'=>10,'type'=>'html','html'=>$this->viewDropzone()],
                    'body' => ['order'=>50,'type'=>'divs','divs'=>$this->divCenterStructure(),'attr'=>['id'=>'bodyStorage']],
                    'modal'=> ['order'=>99,'type'=>'html','html'=>$modalHtm]]],
                ],
            'fields'  => ['btnApp'=>['order'=>10,'classes'=>['icon-plus'],'attr'=>['type'=>'button','value'=>'New']]],
            'jsHead'  => $this->jsHead,
            'jsBody'  => $this->jsBody,
            'jsReady' => $this->jsReady,
            'jsResize'=> $this->jsResize];
        msgDebug("\nlayout storage manager = ".print_r($layout, true));
        return $layout;
    }

    public function divCenter()
    {
        $this->getFiles();
        return ['divs'=>$this->divCenterStructure(),'content'=>['action'=>'divHTML','divID'=>'bodyStorage'],'jsReady'=>$this->jsReady];
    }

    private function divCenterStructure()
    {
        $this->jsReady['dropzone'] = !empty($this->files) ? "bizDropzoneInit(false);" : "bizDropzoneInit(true);";
        return [
            'head'   => ['order'=> 0,'type'=>'html','html'=>'<script type="text/javascript">'."bizPostID=$this->postID; bizParentID=$this->parentID; bizScope='$this->scope';</script>"],
            'crumb'  => ['order'=>10,'type'=>'html','html'=>$this->viewBreadcrumb()],
            'dirHd'  => ['order'=>30,'type'=>'html','html'=>$this->viewFolderHead()],
            'dirDtl' => ['order'=>40,'type'=>'html','html'=>$this->viewFolderDetail()],
            'fileHd' => ['order'=>50,'type'=>'html','html'=>$this->viewFileHeader()],
            'fileDtl'=> ['order'=>60,'type'=>'html','html'=>$this->viewFileDetail()],
            'space'  => ['order'=>99,'type'=>'html','html'=>'<div style="width:100%;height:500px;">&nbsp;</div>']];
    }

    /**
     *
     * @param type $props - minimum required: $props['fn'=>'filename','mime'=>{default 'folder'}]
     * @return integer - ID if successful, 0 otherwise
     */
    private function addFile($props=[])
    {
        $mime    = !empty($props['mime'])  ? $props['mime']  : 'folder';
        $clnName = wp_strip_all_tags( $props['fn'] );
        $title   = strpos($clnName, '.')!==false ? substr($clnName, 0, strrpos($clnName, '.')) : $clnName;
        $postData= [
            'post_title'    => $title,
            'post_parent'   => $this->parentID,
            'post_mime_type'=> $mime,
            'post_content'  => $clnName, // full filename including extension
            'post_type'     => 'bizoffice',
            'post_status'   => 'draft',
            'comment_status'=> 'closed',
            'ping_status'   => 'closed',
//          'meta_input'    => [ 'key' => 'value' ], // becomes data for postmeta table
//          'post_author'   => get_current_user_id(),  // defaults to current user
//          'post_name'     => '', // use sanitized name
//          'post_excerpt'  => '',
//          'post_date'     => biz_date('Y-m-d h:i:s'); // defaults to current time
//          'post_category' => [8,39],
        ];
        msgDebug("\nInserting into WP database: ".print_r($postData, true));
        $pID = wp_insert_post( $postData );
        if (empty($pID)) { msgAdd("Error inserting post!"); }
        return $pID;
    }

    /**
     * Fetches the list of files for a given folder
     * @global \bizoffice\class $wpdb
     */
    private function getFiles()
    {
        global $wpdb;
        $sql = false;
        msgDebug("\nEntering getFiles with scope='$this->scope' and postID=$this->postID and parent=$this->parentID and userID=".$this->wpUser->ID);
        switch ($this->scope) {
            default:
            case 'home':
                $sql = "SELECT ID FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_type='bizoffice' AND post_status NOT IN ('trash', 'revision') AND post_parent=".intval($this->parentID);
                break;
            case 'recent':
                $this->postID= $this->parentID = 0;
                $sql = "SELECT ID FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_type='bizoffice' AND post_status<>'trash' ORDER BY post_modified DESC LIMIT 10";
                break;
            case 'shared': $files = $this->getFilesShared(); break;
            case 'starred':
                $sql = "SELECT ID FROM $wpdb->posts p JOIN $wpdb->postmeta m on p.ID=m.post_id WHERE p.post_author={$this->wpUser->ID} AND p.post_type='bizoffice' AND m.meta_key='_is_starred' AND meta_value='1'";
                break;
            case 'trash':
                $this->postID= $this->parentID = 0;
                $sql = "SELECT ID FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_type='bizoffice' AND post_status='trash'";
                break;
        }
        if (!empty($sql)) {
            msgDebug("\nReady to execute sql = $sql");
            $files = $wpdb->get_results($sql);
            msgDebug("\nReady to process files = ".print_r($files, true));
        }
        foreach ($files as $file) {
            if (in_array($this->scope, ['shared','starred','trash']) && $this->pruneChild($file->ID)) { continue; }
            $post = get_post($file->ID);
//          $meta = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE post_id=$file->ID");
            $props = [ 'id'=>$file->ID, 'path'=>$post->guid, 'fn'=>$post->post_title, 'ext'=>pathinfo($post->post_content, PATHINFO_EXTENSION),
                       'date'=>$post->post_date, 'status'=>$post->post_status, 'type'=>$post->post_mime_type ];
            $this->files[] = $props;
        }
    }

    private function getFilesShared()
    {
        global $wpdb;
        $IDs = [];
        $filter = "(m.meta_key='can_read_".$this->wpUser->ID."' OR m.meta_key='can_edit_".$this->wpUser->ID."')";
        if (!empty($this->parentID)) { // then it is a folder
            $sql = "SELECT ID FROM $wpdb->posts p JOIN $wpdb->postmeta m on p.ID=m.post_id WHERE post_parent=".intval($this->parentID)." AND $filter AND meta_value='1' AND p.post_type='bizoffice'";
        } else {
            $sql = "SELECT ID FROM $wpdb->posts p JOIN $wpdb->postmeta m on p.ID=m.post_id WHERE $filter AND meta_value='1' AND p.post_type='bizoffice'";
        }
        msgDebug("\nReady to execute sql = $sql");
        $files = $wpdb->get_results($sql);
        msgDebug("\nReady to process files = ".print_r($files, true));
        foreach ($files as $file) {
            $postID = new \stdClass();
            $postID->ID = $file->ID;
            $IDs[$file->ID] = $postID;
        }
        msgDebug("\nReturning shared file ID list = ".print_r($IDs, true));
        return array_values($IDs);
    }

    /**
     * Tests for a parent with share privileges and returns
     * @param integer $postID - parentId of post to test
     * @return boolean - true if parent is also shared, false otherwise
     */
    private function pruneChild($postID=0)
    {
        global $wpdb;
        if (empty($postID) || !empty($this->parentID)) { return; }
        $result = $wpdb->get_row("SELECT post_parent FROM $wpdb->posts WHERE ID=$postID");
        msgDebug("\npruneChild result = ".print_r($result, true));
        if (empty($result->post_parent)) { return; }
        $filter = "ID=$result->post_parent AND (m.meta_key='can_read_".$this->wpUser->ID."' OR m.meta_key='can_edit_".$this->wpUser->ID."')";
        $isShared = $wpdb->get_row("SELECT ID FROM $wpdb->posts p JOIN $wpdb->postmeta m on p.ID=m.post_id WHERE $filter AND meta_value='1'");
        msgDebug("\nisShared = ".print_r($isShared, true));
        return !empty($isShared->ID) ? true : false;
    }

    public function fileColor()
    {
        $color = clean('bizColor', ['format'=>'color', 'default'=>'#000000'], 'get');
        $key = $this->padID($this->wpUser->ID, 'mycolors_');
        if (!empty($this->postID)) { update_post_meta( $this->postID, $key, $color ); }
        msgDebug("\nFinished updating color selection for padded user $key with color $color");
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh();"]];
    }

    public function fileDownload()
    {
        // make sure it's a file
        $post = get_post( $this->postID );
        if (empty($post) || in_array($post->post_mime_type, ['folder'])) { return msgAdd('Download Error!'); }
        // make sure user has access
        $fExt  = strtolower(pathinfo($post->post_content, PATHINFO_EXTENSION));
        $fID   = BIZOFFICE_DATA.$this->bizPath."{$this->pathInfo['path']}".$this->padID($this->postID, 'f').".$fExt";
        msgDebug("\nReading source file with full path name: $fID");
        $data  = file_get_contents($fID);
        $output= [
            'mime' => $post->post_mime_type,
            'name' => $post->post_content,
            'size' => strlen($data),
            'data' => base64_encode($data),
        ];
        msgDebug("\nfileDownload returning filename: {$output['name']} of length: {$output['data']} with mime: {$output['mime']}");
        return ['content'=>['action'=>'eval','actionData'=>"bizAjaxDownload(".json_encode($output).");"]];
    }

    public function fileDuplicate()
    {
        global $io;
        $origPost = get_post( $this->postID );
        if (empty($origPost) || in_array($origPost->post_mime_type, ['folder'])) { return; }
        // @TODO Check to see if copy already exists, if so then add another copy until new version is found
        $newFn = str_replace($origPost->post_title, $origPost->post_title.' copy', $origPost->post_content);
        $sExt  = strtolower(pathinfo($newFn, PATHINFO_EXTENSION));
        $sID   = BIZOFFICE_DATA.$this->bizPath."{$this->pathInfo['path']}".$this->padID($this->postID, 'f').".$sExt";
        msgDebug("\nReading source file with full path name: $sID");
        $data  = file_get_contents($sID);
        $props = ['fn'=>$newFn, 'mime'=>$origPost->post_mime_type];
        msgDebug("\nAdding file with props: ".print_r($props, true));
        $rID   = $this->addFile($props);
        if (empty($rID)) { return; } // post failed, don't save file
        $fID   = $this->bizPath."/{$this->pathInfo['path']}".$this->padID($rID, 'f').".$sExt";
        msgDebug("\nDuplicating file with full path name: $fID with length=".strlen($data));
        $io->fileWrite($data, $fID);
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh();"]];
    }

    public function fileRestore()
    {
        if (empty($this->postID)) { return msgAdd("Bad ID!"); }
        $postData = [ 'ID'=>$this->postID, 'post_status'=>'draft' ];
        msgDebug("\nUpdating post ID: $this->postID with status draft.");
        $result = wp_update_post( $postData );
        if (is_wp_error($result)) { return $this->msgAdd("WP SQL Error: ".$result->get_error_message()); }
        $treeData = $this->getFolderTree();
        $treeAction = str_replace('bizMenuCallback(', 'bizMenuRefresh(', $treeData['content']['actionData']);
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh(0, '/'); $treeAction"]];
    }

    public function fileShareLink()
    {
        $canEdit = clean('canEdit', ['format'=>'integer','default'=>0], 'get');
        update_post_meta( $this->postID, "_share_{$this->userID}", $canEdit?2:1 );
    }

    public function fileShareUnlink()
    {

    }

    /**
     * Sets the file/folder as starred (bookmarked)
     */
    public function fileStar()
    {
        $dup = get_post_meta( $this->postID, '_is_starred', true );
        if (empty($dup)) { update_post_meta( $this->postID, '_is_starred', '1' ); }
        msgAdd('The file/folder has been starred.', 'success');
    }

    public function fileTrash()
    {
        if (empty($this->postID)) { return msgAdd("Bad ID!"); }
        $postData = [ 'ID'=>$this->postID, 'post_status'=>'trash' ];
        msgDebug("\nUpdating post ID: $this->postID with status trash.");
        $result = wp_update_post( $postData );
        if (is_wp_error($result)) { return $this->msgAdd("WP SQL Error: ".$result->get_error_message()); }
        $treeData = $this->getFolderTree();
        $treeAction = str_replace('bizMenuCallback(', 'bizMenuRefresh(', $treeData['content']['actionData']);
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh(0, '/'); $treeAction"]];
    }

    public function fileTrashPerm()
    {
        global $wpdb, $io;
        $postList = [];
        $allTrash = clean('emptyTrash', 'integer', 'get');
        msgDebug("\nEntering fileTrashPerm with allTrash = $allTrash");
        if ($allTrash) {
            $postList  = $this->getDbResult("SELECT ID, post_content, post_parent, post_mime_type FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_type='bizoffice' AND post_status='trash'", 'rows');
        } else { // single file/folder
            $result    = $this->getDbResult("SELECT ID, post_content, post_parent, post_mime_type FROM $wpdb->posts WHERE ID=$this->postID AND post_author={$this->wpUser->ID} AND post_status='trash'");
            $postList[]= $result;
            if ($result->post_mime_type == 'folder') { $this->getFolderFiles($postList, $result->ID); }
        }
        msgDebug("\nRead process primary trash list: ".print_r($postList, true));
        // fetch the revisions and add to list
        $this->getRevisions($postList);
        msgDebug("\nAfter getRevisions, list is now: ".print_r($postList, true));
        foreach ($postList as $post) {
            $result = wp_delete_post( $post->ID, true );
            if (is_wp_error($result)) { return $this->msgAdd("WP SQL Error: ".$result->get_error_message()); }
            // delete the file/folder
            $pathInfo = $this->getPathInfo($post->ID);
            if ($post->post_mime_type == 'folder') {
                $fID = $this->padID($post->ID, 'd');
                $io->folderDelete($this->bizPath.$pathInfo['path'].$fID);
            } else {
                $fID = $this->padID($post->ID, 'f').'.*';
                $io->fileDelete($this->bizPath.$pathInfo['path'].$fID);
            }
        }
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh();"]];
    }

    /**
     * wrapper for enabling file upload capability (https://github.com/blueimp/jQuery-File-Upload)
     * @param type $data
     */
    public function fileUpload()
    {
        global $wpdb;
        msgDebug("\nfiles contains: ".print_r($_FILES, true));
        $curID = $this->getDbResult("SELECT ID FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_status='draft' AND post_content='{$_FILES['files']['name'][0]}' AND post_parent=$this->parentID");
        msgDebug("\nResult from previous version search = ".print_r($curID, true));
        if (!empty($curID)) {
            $postData = [ 'ID'=>$curID->ID, 'post_status'=>'revision' ];
            msgDebug("\nUpdating post ID: $curID->ID with status revision.");
            $result = wp_update_post( $postData );
            if (is_wp_error($result)) { return $this->msgAdd("WP SQL Error: ".$result->get_error_message()); }
        }
        $rID = $this->uploadSave('files', $this->pathInfo['path']);
        return ['content'=>['success'=>$rID?true:false,'action'=>'eval','actionData'=>"bizCenterRefresh();"]];
    }

    public function folderAdd()
    {
        global $io;
        $fName = clean('fName', 'text', 'get');
        if (empty($fName)) { return msgAdd("Folder name cannot be blank!"); }
        $props = ['fn'=>$fName, 'mime'=>'folder'];
        msgDebug("\nAdding folder with props: ".print_r($props, true));
        $rID = $this->addFile($props);
        $fID = $this->padID($rID, 'd');
        msgDebug("\nAdding folder named: $fID");
        $io->validatePath($this->bizPath.$this->pathInfo['path']."$fID/index.php", true, true);
        $treeData = $this->getFolderTree();
        $treeAction = str_replace('bizMenuCallback(', 'bizMenuRefresh(', $treeData['content']['actionData']);
        return ['content'=>['action'=>'eval','actionData'=>"bizCenterRefresh(); $treeAction"]];
    }

    /**
     * Adds the padding to the ID value and adds a prefix
     * @param integer $rID - id field to pad
     * @param string $prefix - prefix to put in front of padded value
     * @return string
     */
    private function padID($rID, $prefix)
    {
        return $prefix.str_pad($rID, 8, '0', STR_PAD_LEFT);
    }

    private function getDbResult($sql='', $scope='row')
    {
        global $wpdb;
        if (empty($sql)) { return; }
        msgDebug("\nExecuting scope $scope with sql = $sql");
        if ($scope=='row') {
            $result = $wpdb->get_row($sql);
        } else {
            $result = $wpdb->get_results($sql);
        }
        if (is_wp_error($result)) {
            msgDebug("\nWP SQL Error: ".$result->get_error_message());
            return $this->msgAdd("WP SQL Error: ".$result->get_error_message());
        }
        msgDebug("\nQuery successful");
        return $result;
    }

    /**
     * Gets the details of the particular file
     */
    public function getFileInfo()
    {

    }

    /**
     * Pulls the contents of the current folder and orders by the requested property
     */
    public function getFileOrder()
    {

    }

    /**
     * Gets the contents of a given folder for the main area
     */
    public function getFolderData()
    {

    }

    /**
     * Gets the main list tree on left sidebar
     */
    public function getFolders()
    {

    }

    private function getFolderFiles(&$working, $parent)
    {
        global $wpdb;
        msgDebug("\nEntering getFolderFiles with parent = $parent and size of working = ".sizeof($working));
        $sql = "SELECT ID, post_content, post_parent, post_mime_type FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_parent=$parent AND post_status='draft' AND post_type='bizoffice'";
        $children = $this->getDbResult($sql, 'rows');
        foreach ($children as $row) {
            $working[] = $row;
            if ($row->post_mime_type=='folder') { $this->getFolderTreeChildren($working, $row->ID); }
        }
    }

    /**
     *
     * @return type
     */
    public function getFolderTree()
    {
        $folders = [
    ['id'=>$this->padID(0, 't'),"text"=>'<span class="treeIcon"><i class="fad fa-cabinet-filing"></i></span>'.lang('home'), 'state'=>['selected'=>true],'children'=>$this->getFolderTreeChildren([], 0)],
    ['id'=>"menu_shared", "text"=>'<span class="treeIcon"><i class="fad fa-share"></i></span>'         .lang('shared')],
    ['id'=>"menu_recent", "text"=>'<span class="treeIcon"><i class="fad fa-calendar-week"></i></span>' .lang('recent')],
    ['id'=>"menu_starred","text"=>'<span class="treeIcon"><i class="fad fa-star"></i></span>'          .lang('starred')],
    ['id'=>"menu_trash",  "text"=>'<span class="treeIcon"><i class="fad fa-trash"></i></span>'         .lang('trash')],
];
        msgDebug("\nReturning from getFolderTree with folders = ".print_r($folders, true));
        return ['content'=>['action'=>'eval', 'actionData'=>"bizMenuCallback(".json_encode($folders).");"]];
        // {'id'=>"menu_home", "parent"=>"node_1234",  "text"=>'<span class="treeIcon"><i class="fad fa-cabinet-filing"></i></span>'.lang('home'], 'children':true, 'state':{'selected':true}},
    }

    private function getFolderTreeChildren($working, $parent)
    {
        global $wpdb;
        msgDebug("\nEntering getFolderTreeChildren with parent = $parent and working = ".print_r($working, true));
        $sql = "SELECT ID, post_content FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_parent=$parent AND post_type='bizoffice' AND post_status<>'trash' AND post_mime_type='folder'";
        $newParent = $this->getDbResult($sql, 'rows');
        foreach ($newParent as $row) {
            $branch = ['id'=>$this->padID($row->ID, 't'),  "text"=>'<span class="treeIcon"><i class="fad fa-cabinet-filing"></i></span>'.$row->post_content];
            // see if this branch has any children, if so recurse
            $kidSQL = "SELECT ID FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_parent=$row->ID AND post_type='bizoffice' AND post_status<>'trash' AND post_mime_type='folder'";
            $hasKids= $this->getDbResult($kidSQL); // at least one row
            if (!empty($hasKids)) {
                $branch['children'] = $this->getFolderTreeChildren([], $row->ID);
            }
            $working[] = $branch;
        }
        msgDebug("\nReturning with tree array = ".print_r($working, true));
        return $working;
    }

    private function getParentID($parentID=0)
    {
        global $wpdb;
        if (empty($parentID)) { return 0; }
        $newParent = $wpdb->get_row("SELECT ID FROM $wpdb->posts WHERE post_parent=".intval($parentID)." AND post_type='bizoffice'");
        return empty($newParent) ? 0 : $newParent;
    }

    /**
     * Pulls the file path and breadcrumb HTMl for the given parent
     */
    private function getPathInfo()
    {
        global $wpdb;
        $crumbs = [];
        $parent = $this->parentID;
        while (!empty($parent)) {
            $result = $wpdb->get_row("SELECT post_title, post_parent FROM $wpdb->posts WHERE ID=$parent");
            msgDebug("\nresults from query = ".print_r($result, true));
            $crumbs['r'.$parent] = ['path'=>$this->padID($parent, 'd').'/', 'label'=>$result->post_title];
            $parent = $result->post_parent;
        }
        $output = ['r0'=>['path'=>'/','label'=>lang($this->scope)]] + array_reverse($crumbs);
        $html   = $path = '';
        foreach ($output as $key => $crumb) {
            $path .= $crumb['path'];
            $html .= '<span style="cursor:pointer" onClick="bizParentID='.intval(substr($key, 1)).'; bizCenterRefresh();">'.$crumb['label'].'</span> -> ';
        }
        return ['path'=>$path, 'breadcrumb'=>substr($html, 0, strlen($html)-4)];
    }

    /**
     * Takes a list of posts (draft status) and adds any prior revisions (typically for trash permanently and doc history)
     * @param array $postList
     */
    private function getRevisions(&$postList)
    {
        global $wpdb;
        $working = $postList;
        foreach ($working as $post) {
            if ($post->post_mime_type == 'folder') { continue; } // Folders don't have revisions
            $result = $this->getDbResult("SELECT ID, post_content, post_mime_type FROM $wpdb->posts WHERE post_author={$this->wpUser->ID} AND post_parent=$post->post_parent AND post_content='$post->post_content' AND post_status='revision'", 'rows');
            if (!empty($result)) { $postList = array_merge($postList, $result); }
        }
    }

    // Pulls the first look share dialog form1
    public function getShareForm()
    {
        $jsBody = "$('#shareUsers').autocomplete({
    source: function( request, response ) {
        $.ajax({
         url:      bizunoAjax+'&bizRt=bizStorage/getShareUsers&rID=$this->postID',
         type:     'get',
         dataType: 'json',
         data:     { bizq: request.term },
         success:  function( data ) { response( data ); }
        });
    },
    select: function (event, ui) { bizViewShareAdd(event, ui); return false; },
});";
        // get the current share list from $this->postID;
        $userList[$this->wpUser->ID] = ['id'=>$this->wpUser->ID, 'name'=>$this->wpUser->display_name, 'email'=>$this->wpUser->user_email, 'can_edit'=>2];
        $meta = array_keys(get_post_meta( $this->postID ));
        msgDebug("\nread meta from db: ".print_r($meta, true));
        foreach ($meta as $key) {
            $subject= substr($key, 0, 9);
            if (!in_array($subject, ['can_read_', 'can_edit_'])) { continue; }
            $userID = intval(substr($key, 9));
            $uData  = get_userdata($userID);
            $userList[$uData->ID] = ['id'=>$uData->ID, 'name'=>$uData->display_name, 'email'=>$uData->user_email];
            if ($subject=='can_read_' && !isset($userList[$uData->ID]['can_edit'])) {
                $userList[$uData->ID]['can_edit'] = 0;
            } elseif ($subject=='can_edit_') {
                $userList[$uData->ID]['can_edit'] = 1;
            }
        }
        $layout = ['type'=>'divHTML','content'=>'',
            'divs'   => ['body'=>['order'=>10,'type'=>'html','html'=>$this->viewShareForm($userList)]],
            'jsBody' => ['init'=>$jsBody],
            'jsReady'=> ['init'=>"$('#shareUsers').focus(); bizAjaxForm('bizShareForm');"]];
        msgDebug("\nReturning from getShareForm with layout = ".print_r($layout, true));
        return $layout;
    }

    /**
     * Pulls the hit list of users for the share auto complete
     */
    public function getShareUsers()
    {
        global $wpdb;
        $q     = clean('bizq', ['format'=>'alpha', 'default'=>''], 'get');
        $sql = "SELECT * FROM $wpdb->users WHERE user_nicename LIKE '%$q%' OR user_email LIKE '%$q%' OR display_name LIKE '%$q%'";
        $result= $wpdb->get_results($sql);
        if (is_null($result)) {
            $output = '';
        } else {
            foreach ($result as $row) {
                $user[] = ['id'=>$row->ID, 'label'=>addslashes($row->display_name)." <$row->user_email>"];
            }
            $output = json_encode($user);
        }
        return ['type'=>'raw','content'=>$output];
    }

    private function getThumb()
    {
        return 'thumb image';
    }

    /**
     * Generates a .jpg image preview of the file
     */
    public function getThumbs()
    {
        //
        // imagemagik
    }

    /**
     * Fetches the icon color, default is standard color
     * @param integer $id - file
     */
    private function guessColor($postID)
    {
        $key   = $this->padID($this->wpUser->ID, 'mycolors_');
        $color = get_post_meta( $postID, $key, true);
        return !empty($color) ? "#$color" : $this->defColor;
    }

    /**
     *
     * @param type $ext
     * @return string
     */
    private function guessIcon($ext)
    {
        msgDebug("\nlooking for icon for extension = $ext");
        if (in_array($ext, ['png','jpg','jpeg','webp'])) { return 'image'; }
        if (in_array($ext, ['pdf']))       { return 'file-pdf'; }
        if (in_array($ext, ['doc','odt'])) { return 'file-word'; }
        if (in_array($ext, ['txt','php',''])) { return 'file-alt'; }
        if (in_array($ext, ['xls','ods'])) { return 'file-spreadsheet'; }
        if (in_array($ext, ['zip','bz','gz'])) { return 'file-archive'; }
        if (in_array($ext, ['mpg','mp4','avi'])) { return 'video'; }
        return 'file'; // folders have no extension
    }

    /**
     * logs the current user out and redirects to home page
     */
    public function goLogout()
    {
        wp_logout();
        return ['content'=>['action'=>'href','link'=>BIZOFFICE_SRVR]];
    }

    /**
     * redirects to profile page
     */
    public function goProfile()
    {
        return ['content'=>['action'=>'href','link'=>BIZOFFICE_SRVR."wp-admin/profile.php"]];
    }

    /**
     * Moves a folder to another folder
     */
    public function moveFolder()
    {

    }

    /**
     * Generates the menu for the general body and on sidebar folders to add files
     */
    private function setMenuBody()
    {
    }

    /**
     * Sets the menu for a file/folder on right click
     */
    private function setMenuFile()
    {
    }

    private function setPath($parentID=0, $working='')
    {
        if (empty($parentID))  { return '/'; }
        if (!empty($parentID)) {
            $newParent= $this->getParentID($parentID, $working);
            $working  = $this->padID($newParent, '')."/$working";
            $this->setPath($newParent, $working);
        }
    }

    /**
     * Creates a new revision of a doc in the database
     */
    public function setRevision()
    {

    }

    public function setShareUsers()
    {
        $keyed= $newUsers = [];
        $meta = get_post_meta( $this->postID );
        msgDebug("\nread meta from db: ".print_r($meta, true));
        foreach ($meta as $key => $value) {
            $subject = substr($key, 0, 9);
            if (!in_array($subject, ['can_read_', 'can_edit_'])) { continue; }
            $keyed[$key] = $value[0];
        }
        msgDebug("\nKeyed array is: ".print_r($keyed, true));
        foreach ($_POST as $key => $value) {
            $subject = substr($key, 0, 9);
            $userID = intval(substr($key, 9));
            if (!in_array($subject, ['can_read_', 'can_edit_'])) { continue; }
            if (!array_key_exists($key, $keyed) && $userID<>$this->wpUser->ID) { $newUsers[$key] = 1; }
            unset($keyed[$key]);
        }
        msgDebug("\naddUsers array is: ".print_r($newUsers, true));
        foreach ($newUsers as $key => $value) {
            $pID = get_post_meta( $this->postID, $key, true );
            if (empty($pID)) {
                msgDebug("\nAdding new user to access file.");
                add_post_meta( $this->postID, $key, $value, true );
                $this->setSharePropagate($this->postID, $key);
                // send welcome email
            }
        }
        msgDebug("\nKeyed array before delete is: ".print_r($keyed, true));
        foreach ($keyed as $key => $value) {
            msgDebug("\nDeleting user from access file.");
            delete_post_meta( $this->postID, $key );
            $this->delSharePropagate($this->postID, $key);
        }
        return ['content'=>['action'=>'eval', 'actionData'=>"btnShareSubmitResp();"]];
    }

    /**
     * Sets the share permission if a folder to all children
     * @global class $wpdb
     * @param integer $postID
     * @param string $key
     * @return null
     */
    private function setSharePropagate($postID=0, $key='')
    {
        global $wpdb;
        $type = get_post_mime_type($postID);
        if ( 'folder' <> $type ) { return; }
        msgDebug("\nEntering setSharePropagate with postID = $postID, looking for children.");
        $files = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_parent=$postID AND post_type='bizoffice'");
        msgDebug("\nFound children = ".print_r($files, true));
        foreach ($files as $file) {
            add_post_meta( $file->ID, $key, 1, true );
            $this->setSharePropagate($file->ID, $key);
        }
    }

    /**
     * Deletes the share permission if a folder to all children
     * @global class $wpdb
     * @param integer $postID
     * @param string $key
     * @return null
     */
    private function delSharePropagate($postID=0, $key='')
    {
        global $wpdb;
        $type = get_post_mime_type($postID);
        if ( 'folder' <> $type ) { return; }
        msgDebug("\nEntering delSharePropagate with postID = $postID, looking for children.");
        $files = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_parent=$postID AND post_type='bizoffice'");
        msgDebug("\nFound children = ".print_r($files, true));
        foreach ($files as $file) {
            delete_post_meta( $file->ID, $key );
            $this->delSharePropagate($file->ID, $key);
        }
    }

    /**
     * Saves an uploaded file, validates first, creates path if not there
     * @param string $index - index of the $_FILES array where the file is located
     * @param string $dest - destination path/filename where the uploaded files are to be placed
     * @param string $prefix - File name prefix to prepend
     * @param string $mime - MIME types to allow
     * @return boolean true on success, false (with msg) on error
     */
    public function uploadSave($index='files', $path='/')
    {
        global $io;
        msgDebug("\nEntering uploadSave with index = $index and path = $path");
        if (!isset($_FILES[$index])) { return msgDebug("\nTried to save uploaded file but nothing uploaded!"); }
        if (!$this->validateUpload($index)) { msgDebug("\nFailed validation!"); return; }
        $data = file_get_contents($_FILES[$index]['tmp_name'][0]);
        $props = ['fn'=>$_FILES[$index]['name'][0], 'mime'=>$_FILES[$index]['type'][0]];
        msgDebug("\nAdding file with props: ".print_r($props, true));
        $rID = $this->addFile($props);
        if (empty($rID)) { return; } // post failed, don't save file
        $fExt = '.'.strtolower(pathinfo($_FILES[$index]['name'][0], PATHINFO_EXTENSION));
        $fID = $this->bizPath."/$path/".$this->padID($rID, 'f').$fExt;
        msgDebug("\nAdding file with full path name: $fID");
        $io->fileWrite($data, $fID);
        return $rID;
    }

    /**
     * This method tests an uploaded file for validity
     * @param string $index - Index of $_FILES array to find the uploaded file
     * @param string $type [default ''] validates the type of file updated
     * @param mixed $ext [default ''] restrict to specific extension(s)
     * @param string $verbose [default true] Suppress error messages for the upload operation
     * @return boolean - true on success, false if failure
     */
    public function validateUpload($index='files')
    {
        msgDebug("\nEntering validateUpload with index = $index");
        if (!isset($_FILES[$index])) { return; }
        if ($_FILES[$index]['error'][0]) { // php error uploading file
            switch ($_FILES[$index]['error'][0]) {
                case UPLOAD_ERR_INI_SIZE:   msgDebug("The uploaded file exceeds the upload_max_filesize directive in php.ini!"); break;
                case UPLOAD_ERR_FORM_SIZE:  msgDebug("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form!"); break;
                case UPLOAD_ERR_PARTIAL:    msgDebug("The uploaded file was only partially uploaded!"); break;
                case UPLOAD_ERR_NO_FILE:    msgDebug("No file was uploaded!"); break;
                case UPLOAD_ERR_NO_TMP_DIR: msgDebug("Missing a temporary folder!"); break;
                case UPLOAD_ERR_CANT_WRITE: msgDebug("Cannot write file!"); break;
                case UPLOAD_ERR_EXTENSION:  msgDebug("Invalid upload extension!"); break;
                default:  msgDebug("Unknown upload error: ".$_FILES[$index]['error'][0]);
            } }
        elseif (!empty($_FILES[$index]['error'][0]))               { return; }
        elseif (!is_uploaded_file($_FILES[$index]['tmp_name'][0])) { return; }
        elseif ($_FILES[$index]['size'][0] == 0)                   { return; }
        $fExt = '.'.strtolower(pathinfo($_FILES[$index]['name'][0], PATHINFO_EXTENSION));
        msgDebug("\nValidating extension $fExt");
        return in_array($fExt, $this->validExt) ? true : false;
    }

    private function viewBreadcrumb()
    {
        $html  = '<div style="float:right">';
        if ($this->scope=='trash') {
            $html .= '  <button onClick="btnEmptyTrash();"><span class="dirIcon"><i class="fad fa-dumpster"></i></span></button>&nbsp;';
        }
        $html .= '  <button onClick="btnShareForm();"><span class="dirIcon"><i class="fad fa-share"></i></span></button>&nbsp;';
        $html .= '  <button onClick="btnUploadToggle();"><span class="dirIcon"><i class="fad fa-upload"></i></span></button>&nbsp;';
        $html .= '  <button onClick="btnSortToggle();"><span class="dirIcon"><i class="fad fa-list"></i></span></button>&nbsp;';
        $html .= '  <button onClick="btnInfoToggle();"><span class="dirIcon"><i class="fad fa-info-square"></i></span></button>';
        $html .= '</div>';
        $html .= '<div id="breadcrumb">'.$this->pathInfo['breadcrumb'].'</div><hr />';
        return $html;
    }

    /**
     * Pulls the format of the body view (blocks versus list)
     */
    public function viewDetail()
    {
        // for blocks only show thumb above, label below (file name and icon)
        // for list, folders first then files (no matter what order)
        //   Headings: Name (followed by order icon), Owner, Last Modified, File Size
    }

    /**
     *
     * @return string
     */
    private function viewDropzone()
    {
        $html  = '<div id="dropzone" style="display:none">';
        $html .= '<input id="bizDropZone" type="file" name="files[]" accept="'.implode(',', $this->validExt).'" multiple>';
        $html .= '</div>';
        return $html;
    }

    /**
     *
     * @return string
     */
    private function viewFileDetail()
    {
        $html = '<div id="fileDetail" class="areaView">';
        foreach ($this->files as $file) {
            if ($file['type']=='folder') { continue; }
            $icon  = $this->guessIcon($file['ext']);
            $color = $this->guessColor($file['id']);
//          $thumb = $this->getThumb($file['fn']);
            $html .= '<div id="f'.$file['id'].'" class="blockFile" onMouseOver="bizIsFile=true;bizPostID='.$file['id'].';bizScope=\''.$this->scope.'\';" onMouseOut="bizIsFile=false;">';
            $html .= '<div class="dtlIcon"><span style="color:'.$color.';"><i class="fad fa-'.$icon.'"></i></span></div>';
            $html .= '<div class="dtlText">'.$file['fn'].'</div></div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     *
     * @return type
     */
    private function viewFileHeader()
    {
        return '<div id="fileHeader"><h3>'.lang('files').'</h3></div>';
    }

    /**
     *
     * @return string
     */
    private function viewFolderDetail()
    {
        $html = '<div id="dirDetail" class="areaView">';
        foreach ($this->files as $file) {
            if ($file['type']<>'folder') { continue; }
            $html .= '  <div id="d'.$file['id'].'" class="blockDir" onMouseOver="bizIsFile=true;bizPostID='.$file['id'].';bizScope=\''.$this->scope.'\';" onMouseOut="bizIsFile=false;" onDblClick="bizParentID='.$file['id'].'; bizCenterRefresh();">
    <span class="dirIcon"><i class="fad fa-folder"></i></span><span class="dirText">'.$file['fn'].'</span>
</div>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     *
     * @return type
     */
    private function viewFolderHead()
    {
        return '<div id="dirHeader"><h3>'.lang('folders').'</h3></div>';
    }

    /**
     * Preview a file, if possible
     */
    public function viewPreview()
    {
        $action = '';
        if (!empty($this->postID)) {
            $post = get_post($this->postID);
            msgDebug("\nresult of post = ".print_r($post, true));
            if ($post->post_mime_type == 'folder') { return msgAdd("Folders are not previewable"); }
            $ext = pathinfo($post->post_content, PATHINFO_EXTENSION);
            if (in_array($ext, ['jpeg', 'jpg', 'gif', 'svg', 'png'])) {
                $fExt  = '.'.strtolower(pathinfo($post->post_content, PATHINFO_EXTENSION));
                $imgSrc= BIZOFFICE_DATA_URL.$this->bizPath.$this->pathInfo['path'].$this->padID($this->postID, 'f').$fExt;
                msgDebug("\nBuilt imgSrc = $imgSrc");
                $action= "bizPreview('$imgSrc', '$post->post_title');";
            } else {
                $action= "alert('cannot preview image: $post->post_content');";
            }
        } else {
            msgAdd("This document cannot be previewed!");
        }
        return ['content'=>['action'=>'eval','actionData'=>$action]];
    }

    /**
     *
     * @param type $userList
     * @return string
     */
    private function viewShareForm($userList=[])
    {
        $html  = '<form id="bizShareForm" action="'.BIZOFFICE_AJAX.'&bizRt=bizStorage/setShareUsers&rID='.$this->postID.'">';
        $html .= '<p>'.lang('share_intro').'</p>';
        $html .= '<label for="shareUsers">'.lang('share_with').'</label>';
        $html .= '<input type="select" id="shareUsers" name="shareUsers">';
        $html .= '<br /><br /><button type="submit">'.lang('invite').'</button>';
        $html .= '<table id="bizShareTable"><th>&nbsp;</th><th>'.lang('shared_users').'</th><th>'.lang('can_edit').'</th>';
        foreach ($userList as $user) {
            if ($user['can_edit']==2) {
                $trTD = '&nbsp;';
                $rdTD = '';
                $edTD = lang('owner');
            } else {
                $trTD = '<span id="'.$user['id'].'" onClick="if (confirm(\'Are you sure?\')) { $(this).closest(\'tr\').remove(); }" class="treeIcon"><i class="fad fa-trash"></i></span>';
                $rdTD = '<input type="hidden" id="can_read_'.$user['id'].'" value="1">';
                $edTD = '<input type="checkbox" id="can_edit_'.$user['id'].'" value="1"'.($user['can_edit']?' checked':'').'>';
            }
            $html .= '<tr><td>'.$trTD.'</td>';
            $html .= '<td>'.$rdTD.$user['name'].' &lt;'.$user['email'].'&gt;</td>';
            $html .= '<td style="text-align:center"><span>'.$edTD.'</span></td></tr>'; // Can edit checkbox
        }
        $html .= '</table></form>';
        return $html;
    }
}
