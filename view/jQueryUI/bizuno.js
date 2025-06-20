/*
 * Common javascript file loaded with all pages
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
 * @version    7.x Last Update: 2025-05-06
 * @filesource /assets/bizoffice.js
 */

var bizIsFile      = false;
var bizPostID      = 0;
var bizParentID    = 0;
var bizLastParentID= -1;
var bizFolderChild = [];
var bizScope       = 'home';
var xPosition      = 0; // mouse position when clicked
var yPosition      = 0;

document.addEventListener('mousedown', bizGetClickPosition);

var lang = {
    'accounting':'Accounting',
    'admin':'Admin',
    'add_star':'Add Star',
    'bizuno_editor':'Editor',
    'bizuno_impress':'Impress',
    'bizuno_tables':'Tables',
    'change_color':'Change Color',
    'color_black': 'Black',
    'color_brown': 'Brown',
    'color_red': 'Red',
    'color_orange': 'Orange',
    'color_yellow': 'Yellow',
    'color_green': 'Green',
    'color_blue': 'Blue',
    'color_purple': 'Purple',
    'color_gray': 'Gray',
    'color_white': 'White',
    'delete_perm':'Delete Permanently',
    'duplicate':'Duplicate',
    'download':'Download',
    'edit_blank':'Blank Editor',
    'from_template':'From Template',
    'home':'Home',
    'impress_blank':'Blank Impress',
    'location':'Location',
    'logout':'Log out',
    'mail':'Mail',
    'move_to':'Move To',
    'move_trash':'Move To Trash',
    'new_folder':'New Folder',
    'open':'Open',
    'preview':'Preview',
    'path':'Path',
    'profile':'Profile',
    'recent':'Recent',
    'rename':'Rename',
    'restore':'Restore',
    'search_current':'Search Current',
    'share':'Share',
    'shared':'Shared',
    'starred':'Starred',
    'symlink':'Symlink',
    'tables_blank':'Blank Table',
    'trash':'Trash',
    'upload_file':'Upload File',
    'upload_folder':'Upload Folder',
    'view_details':'View Details',
    'visit_site':'Visit Site',
};

var menuAcct = {
    id:'menuAcct',
    rows:[
        {label:lang.profile,icon:'id-card', action:"bizAction('profile')" },
        {label:lang.logout, icon:'sign-out',action:"bizAction('logout')",iconColor:'red' }
    ]
};

var menuApps = {
    id:'menuApps',
    rows:[
        {label:lang.accounting,icon:'abacus', action:"bizAction('accounting')" },
        {label:lang.admin,     icon:'cog',    action:"bizAction('admin')" },
        {label:lang.mail,      icon:'mailbox',action:"bizAction('mail')" }
    ]
};

var menuColors = {
    id:'menuColors',
    rows:[
        {label:lang.color_black, icon:'circle',action:"bizColorSel('000000')",iconColor:'#000000' },
        {label:lang.color_brown, icon:'circle',action:"bizColorSel('8B4513')",iconColor:'#8B4513' },
        {label:lang.color_red,   icon:'circle',action:"bizColorSel('FF0000')",iconColor:'#FF0000' },
        {label:lang.color_orange,icon:'circle',action:"bizColorSel('FFA500')",iconColor:'#FFA500' },
        {label:lang.color_yellow,icon:'circle',action:"bizColorSel('FFFF00')",iconColor:'#FFFF00' },
        {label:lang.color_green, icon:'circle',action:"bizColorSel('008000')",iconColor:'#008000' },
        {label:lang.color_blue,  icon:'circle',action:"bizColorSel('0000FF')",iconColor:'#0000FF' },
        {label:lang.color_purple,icon:'circle',action:"bizColorSel('800080')",iconColor:'#800080' },
        {label:lang.color_gray,  icon:'circle',action:"bizColorSel('808080')",iconColor:'#808080' },
        {label:lang.color_white, icon:'circle',action:"bizColorSel('FFFFFF')",iconColor:'#FFFFFF' }
    ]
};

var menuFile = {
    id:'menuFile',
    rows:[
        {label:lang.preview,       icon:'eye',            action:'bizAction(\'preview\')' },
        {label:lang.open,          icon:'external-link',  action:'bizAction(\'open\')' },
        {label:lang.share,         icon:'share',          action:'bizAction(\'share\')' },
        {label:lang.location,      icon:'folder-tree',    action:'bizAction(\'location\')' },
        {label:lang.path,          icon:'location-arrow', action:'bizAction(\'path\')' },
        {label:lang.symlink,       icon:'link',           action:'bizAction(\'symlink\')' },
        {label:lang.move_to,       icon:'arrows',         action:'bizAction(\'move\')' },
        {label:lang.add_star,      icon:'star',           action:'bizAction(\'star\')' },
        {label:lang.rename,        icon:'file-edit',      action:'bizAction(\'preview\')' },
        {label:lang.change_color,  icon:'paint-brush',    action:'bizAction(\'color\')' },
        {label:lang.search_current,icon:'search-location',action:'bizAction(\'search\')' },
        {label:lang.view_details,  icon:'info-square',    action:'bizAction(\'details\')' },
        {label:lang.duplicate,     icon:'copy',           action:'bizAction(\'duplicate\')' },
        {label:lang.download,      icon:'download',       action:'bizAction(\'download\')' },
        {label:lang.move_trash,    icon:'trash',          action:'bizAction(\'trash\')' }
    ]
};

var menuFolder = {
    id:'menuFolder',
    rows:[
        {label:lang.new_folder,    icon:'folder-plus',  action:'bizFolderAdd();' },
        {label:lang.upload_file,   icon:'file-upload',  action:'btnUploadToggle();' },
/*      {label:lang.upload_folder, icon:'folder-upload',action:'btnUploadToggle();' }, */
        {label:lang.bizuno_editor, icon:'newspaper',    iconColor:'deepskyblue',   action:"bizAction('editorNew');", children:[
            {label:lang.edit_blank,       icon:'file',      action:"bizAction('editorNew');" },
            {label:lang.from_template,    icon:'file-video',action:"bizAction('editorTpl');" }
        ] },
        {label:lang.bizuno_tables, icon:'table',        iconColor:'deepskyblue',action:"bizAction('tablesNew');",  children:[
            {label:lang.tables_blank,     icon:'file',      action:"bizAction('tablesNew');" },
            {label:lang.from_template,  icon:'file-video',action:"bizAction('tablesTpl');" }
        ] },
        {label:lang.bizuno_impress,icon:'presentation', iconColor:'deepskyblue',action:"bizAction('impressNew');", children:[
            {label:lang.impress_blank,   icon:'file',      action:"bizAction('impressNew');" },
            {label:lang.from_template,icon:'file-video',action:"'bizAction('impressTpl');' "}
        ] },
    ]
};

var menuTrash = {
    id:'menuTrash',
    rows:[
        {label:lang.restore,    icon:'trash-undo',action:"bizAction('restore')" },
        {label:lang.delete_perm,icon:'dumpster',  action:"bizAction('trash_perm')",iconColor:'red' }
    ]
};

function bizAction(action) {
    $(".file-menu").hide();
    $(".folder-menu").hide();
    switch (action) {
        case 'accounting':  alert('Clicked accounting'); break;
        case 'admin':       alert('Clicked admin'); break;
        case 'color':       bizColors(); break;
        case 'details':     alert('Clicked details'); break;
        case 'download':    bizJsonAction('bizStorage/fileDownload', bizPostID); break;
        case 'duplicate':   bizJsonAction('bizStorage/fileDuplicate', bizPostID); break;
        case 'editorNew':   alert('Clicked editorNew'); break;
        case 'editorTpl':   alert('Clicked editorTpl'); break;
        case 'impressNew':  alert('Clicked impressNew'); break;
        case 'impressTpl':  alert('Clicked impressTpl'); break;
        case 'location':    alert('Clicked location'); break;
        case 'logout':      bizJsonAction('bizStorage/goLogout'); break;
        case 'move':        alert('Clicked move'); break;
        case 'open':        alert('Clicked open'); break;
        case 'path':        alert('Clicked path'); break;
        case 'preview':     if (!bizIsFile) { bizJsonAction('bizStorage/viewPreview', bizPostID); } break;
        case 'profile':     bizJsonAction('bizStorage/goProfile'); break;
        case 'rename':      alert('Clicked rename'); break;
        case 'restore':     bizJsonAction('bizStorage/fileRestore', bizPostID); break;
        case 'search':      alert('Clicked search'); break;
        case 'share':       bizViewShareInit(); break;
        case 'star':        bizJsonAction('bizStorage/fileStar', bizPostID); break;
        case 'symlink':     alert('Clicked symlink'); break;
        case 'tablesNew':   alert('Clicked tablesNew'); break;
        case 'tablesTpl':   alert('Clicked tablesTpl'); break;
        case 'trash':       if (confirm("Move this folder to the trash?")){ bizJsonAction('bizStorage/fileTrash', bizPostID); } break;
        case 'trash_perm':  if (confirm("Permanently Delete this file?")) { bizJsonAction('bizStorage/fileTrashPerm', bizPostID); } break;
    }
}

/**
 * This function uses the jquery plugin filedownload to perform a controlled file download with error messages if a failure occurs
 */
function bizAjaxDownload(json) {
    var bizBlob = base64toBlob(json.data, json.mime);
    saveAs(bizBlob, json.name);
}

function base64toBlob(base64Data, contentType) {
    contentType = contentType || '';
    var sliceSize = 1024;
    var byteCharacters = atob(base64Data);
    var bytesLength = byteCharacters.length;
    var slicesCount = Math.ceil(bytesLength / sliceSize);
    var byteArrays = new Array(slicesCount);
    for (var sliceIndex = 0; sliceIndex < slicesCount; ++sliceIndex) {
        var begin = sliceIndex * sliceSize;
        var end = Math.min(begin + sliceSize, bytesLength);
        var bytes = new Array(end - begin);
        for (var offset = begin, i = 0; offset < end; ++i, ++offset) {
            bytes[i] = byteCharacters[offset].charCodeAt(0);
        }
        byteArrays[sliceIndex] = new Uint8Array(bytes);
    }
    return new Blob(byteArrays, { type: contentType });
}

/**
 * This function prepares a form to be submited via ajax
 * @param string formID - form ID to be submitted
 * @param boolean skipPre - set to true to skip the preCheck before submit
 * @returns false - but submits the form data via AJAX if all test pass
 */
function bizAjaxForm(formID) {
    $('#'+formID).submit(function (e) {
        e.preventDefault();
        e.stopImmediatePropagation(); // may have to uncomment this to prevent double submits
        var frmData = new FormData(this);
        // Patch for Safari 11-? browsers hanging on forms submits with EMPTY file fields.
//      if (navigator.userAgent.indexOf('Safari') !== -1) { jqBiz('#'+formID).find("input[type=file]").each(function(index, field) { if (jqBiz('#'+field.id).val() == '') { frmData.delete(field.id); } }); }
        $('#'+formID).find('input[type="hidden"]').each(function(index, field) {
            if ($('#'+field.id).val() !== '') { frmData.append(field.id, field.value); } });
        $('#'+formID).find('input[type="checkbox"]').each(function(index, field) {
            if ($(this).prop("checked") === true) { frmData.append(field.id, '1'); } });
        $.ajax({
            url:        $('#'+formID).attr('action'),
            type:       'post', // breaks with GET
            data:       frmData,
            dataType:   'json',
            mimeType:   'multipart/form-data',
            contentType:false,
            processData:false,
            cache:      false,
            success:    function (data) { bizProcessJson(data); }
        });
        return false;
    });
}

function bizCenterRefresh() {
    bizJsonAction('bizStorage/divCenter');
}

function bizColors() {
    content = bizColorsInit(menuColors);
    $('#divMsg')
        .dialog({
            autoOpen: false,
            height: 550,
            width:  250,
            title: 'Pick a Color'
        }).dialog( { modal:true } );
    $('#divMsg').dialog('open').html(content);
    $('#bizColorMenu').menu().focus();
}

/**
 * Generates the menus based on the
 * @param {type} type
 * @param {type} src
 * @returns {undefined}
 */
function bizColorsInit(src) {
    var menuDiv = '<div><ul id="bizColorMenu">';
    for (var i=0; i<src.rows.length; i++) {
        var icon = (typeof src.rows[i].icon !== 'undefined') ? src.rows[i].icon : 'question';
        menuDiv += '<li onClick="'+src.rows[i].action+'"><div><span class="treeIcon" style="font-size:2em; color:'+src.rows[i].iconColor+';"><i class="fad fa-'+src.rows[i].icon+'"></i></span>'+src.rows[i].label+'</div></li>';
    }
    menuDiv += '</ul></div>';
    return menuDiv;
}

function bizColorSel(fColor) {
    $('#divMsg').dialog('close');
    bizJsonAction('bizStorage/fileColor&bizColor='+fColor, bizPostID);
}

/**
 * This function extracts the returned messageStack messages and displays then according to the severity
 */
function bizDisplayMessage(message) {
    var msgText = '';
    var imgIcon = '';
    // Process errors and warnings
    if (message.error) for (var i=0; i<message.error.length; i++) {
        msgText += '<span>'+message.error[i].text+'</span><br />';
        imgIcon = 'error';
    }
    if (message.warning) for (var i=0; i<message.warning.length; i++) {
        msgText += '<span>'+message.warning[i].text+'</span><br />';
        if (!imgIcon) imgIcon = 'warning';
    }
    if (message.caution) for (var i=0; i<message.caution.length; i++) {
        msgText += '<span>'+message.caution[i].text+'</span><br />';
        if (!imgIcon) imgIcon = 'warning';
    }
    if (msgText) {
        alert(msgText);
//        $.messager.alert({title:'',msg:msgText,icon:imgIcon,width:600});
    }
    // Now process Info and Success
    if (message.info) {
        msgText = '';
        msgTitle= bizLangJS('INFORMATION');
        msgID   = Math.floor((Math.random() * 1000000) + 1); // random ID to keep boxes from stacking and crashing easyui
        for (var i=0; i<message.info.length; i++) {
            if (typeof message.info[i].title !== 'undefined') { msgTitle = message.info[i].title; }
            msgText += '<span>'+message.info[i].text+'</span><br />';
        }
        bizProcessJson( { action:'window', id:msgID, title:msgTitle, html:msgText } );
    }
    if (message.success) {
        msgText = '';
        for (var i=0; i<message.success.length; i++) {
            msgText += '<span>'+message.success[i].text+'</span><br />';
        }
        $("#divMsg").html('<div class="msgBottom">'+msgText+'</div>').show().delay(5000).fadeOut();
    }
}

function bizDropzone() {
    $('#bizDropZone').FancyFileUpload({
        fileupload:     { url:bizunoAjax+'&bizRt=bizStorage/fileUpload&parent='+bizParentID },
        added :         function (e, data) { this.find('.ff_fileupload_actions button.ff_fileupload_start_upload').click(); },
        uploadcompleted:function (e, data) { bizCenterRefresh(); },
        retries:        0
    });
}

function bizDropzoneInit(show)
{
    if (bizLastParentID != bizParentID) {
        $('#dropzone').css('display','none');
        $('#bizDropZone').FancyFileUpload('destroy');
        bizDropzone();
        bizLastParentID = bizParentID;
    }
    if (show) { btnUploadToggle(); }
}

function bizFolderAdd() {
    $(".folder-menu").hide();
    // get the current folderID
    var folderName = prompt("Please enter the new folder name:");
    if (folderName.length < 1) { alert("Length cannot be blank"); }
    bizJsonAction('bizStorage/folderAdd&fName='+folderName, bizPostID);
}

function bizGetClickPosition(e) {
    xPosition = e.clientX;
    yPosition = e.clientY;
}

/**
 *
 * @param {type} path
 * @param {type} rID
 * @param {type} jData
 * @returns {Boolean}
 */
function bizJsonAction(path, rID, jData) {
    if  (typeof path == 'undefined') return alert('ERROR: The destination path is required, no value was provided.');
    var pathClean = path.replace(/&amp;/g,"&") + '&parent='+bizParentID+'&scope='+bizScope;
    var remoteURL = bizunoAjax+'&bizRt='+pathClean;
    if (typeof rID   != 'undefined') remoteURL += '&rID='+rID;
    if (typeof jData != 'undefined') remoteURL += '&data='+encodeURIComponent(jData);
    $.ajax({ type:'GET', url:remoteURL, success:function (data) { bizProcessJson(data); }, dataType: 'json' });
    return false;
}

function bizLayoutInit() {
    $('body').layout({ applyDefaultStyles: true, north:{resizable:false} });
}

/**
 * Generates the menus based on the
 * @param {type} type
 * @param {type} src
 * @returns {undefined}
 */
function bizMenu(type, src) {
    var menuDiv = '<div class="'+type+'-menu"><ul>';
    for (var i=0; i<src.rows.length; i++) {
        var icon = (typeof src.rows[i].icon !== 'undefined') ? src.rows[i].icon : 'question';
        menuDiv += '<li><div onMouseDown="'+src.rows[i].action+'"><span class="treeIcon"><i class="fad fa-'+src.rows[i].icon+'"></i></span>'+src.rows[i].label+'</div></li>';
    }
    menuDiv += '</ul></div>';
    $(menuDiv).appendTo('body');
}

function bizMenuAction(e, data) {
    var elID = data.selected[0];
//  alert('Entering bizMenuAction scope = '+bizScope+' and parentID = '+bizParentID+' and  elID = '+elID);
    var item = elID.substr(1); // removes the first character
    if (!isNaN(item) && !isNaN(parseFloat(item))) { // numeric so it's a node
        bizScope   = 'home';
        bizParentID= elID.replace(/[^\d.]/g, ''); // strip non numeric
    } else { // all others
        bizScope   = elID.substr(5); // removes the menu_
        bizParentID=0;
    }
//    alert('Reloading bizMenuAction scope = '+bizScope+' and parentID = '+bizParentID+' and  elID = '+elID);
    bizJsonAction('bizStorage/divCenter');
}

/**
 * Callback from bizMenuAction to set the tree data
 * @returns {undefined}
 */
function bizMenuCallback(menuWest) {
    $('#jstree').jstree({
        'core' : {
            'themes': { 'stripes' : false },
            'data': function (obj, cb) { cb.call(this, menuWest); }
        }
    });
    $("#jstree").on("loaded.jstree", function (e, data) { // prevents duplicate calls at startup
        $('#jstree').on('select_node.jstree', function (e, data) { bizMenuAction(e, data); });
    });
//    $('#jstree').jstree(true).refresh(); // causes change event, perhaps look into select event and open event
}

/**
 * Callback from bizMenuAction to set the tree data
 * @returns {undefined}
 */
function bizMenuRefresh(menuWest) {
    $('#jstree').jstree(true).settings.core.data = menuWest;
    $('#jstree').jstree(true).refresh(true);
}

function bizMenuCbRecurse() {

}

function bizMenuClick(action, xPos, yPos) {
//  alert('clicked: '+action+' with x = '+xPos+' and y = '+yPos);
    switch (action) {
        case 'acct':   $(".acct-menu").toggle(100).css(  { top: yPos + "px", left: xPos + "px" }); break;
        case 'apps':   $(".apps-menu").toggle(100).css(  { top: yPos + "px", left: xPos + "px" }); break;
        case 'file':   $(".file-menu").toggle(100).css(  { top: yPos + "px", left: xPos + "px" }); break;
        default:
        case 'folder': $(".folder-menu").toggle(100).css({ top: yPos + "px", left: xPos + "px" }); break;
        case 'trash':  $(".trash-menu").toggle(100).css( { top: yPos + "px", left: xPos + "px" }); break;
    }
}

function bizMenuInit() {
    bizMenu('acct',   menuAcct);
    bizMenu('apps',   menuApps);
    bizMenu('file',   menuFile);
    bizMenu('folder', menuFolder);
    bizMenu('trash',  menuTrash);
    // disable right click and show custom context menu
    $("#bodyStorage").bind('contextmenu', function (e) {
        var yPos = e.pageY+5;
        var xPos = e.pageX;
        if (bizIsFile===true && bizScope!=='trash') { bizMenuClick('file',  xPos, yPos); }
        else if (bizIsFile===true)                  { bizMenuClick('trash', xPos, yPos); }
        else                                        { bizMenuClick('folder',xPos, yPos); }
        return false;
    });
    $(document).on('mousedown', function(e) {
        $(".acct-menu").hide();
        $(".apps-menu").hide();
        $(".folder-menu").hide();
        $(".file-menu").hide();
        $(".trash-menu").hide();
        e.preventDefault();
        e.stopImmediatePropagation();
    });
    $('.acct-menu').bind('contextmenu',function() { return false; });
    $('.acct-menu li').click(function() { $(".acct-menu").hide(); });
    $('.apps-menu').bind('contextmenu',function() { return false; });
    $('.apps-menu li').click(function() { $(".apps-menu").hide(); });
    $('.file-menu').bind('contextmenu',function() { return false; });
    $('.file-menu li').click(function() { $(".file-menu").hide(); });
    $('.folder-menu').bind('contextmenu',function() { return false; });
    $('.folder-menu li').click(function() { $(".folder-menu").hide(); });
    $('.trash-menu').bind('contextmenu',function() { return false; });
    $('.trash-menu li').click(function() { $(".trash-menu").hide(); });
    bizJsonAction('bizStorage/getFolderTree');
}

function print_r(theObj){
   if(theObj.constructor == Array || theObj.constructor == Object){
      for(var p in theObj){
         if(theObj[p].constructor == Array || theObj[p].constructor == Object){
            print_r(theObj[p]);
         } else {
            echo("<li>["+p+"] => "+theObj[p]+"</li>");
         }
      }
   }
}

/**
 * This function processes the returned json data array
 */
function bizProcessJson(json) {
    if (!json) return false;
    if ( json.message) bizDisplayMessage(json.message);
    if ( json.extras)  eval(json.extras);
    switch (json.action) {
        case 'divHTML': $('#'+json.divID).html(json.html).text(); break;
        case 'eval':    if (json.actionData) eval(json.actionData); break;
        case 'href':    if (json.link) window.location = json.link.replace(/&amp;/g,"&"); break;
        default: // if (!json.action) alert('response had no action! Bailing...');
    }
}

function bizPreview(src, title) {
    $('#bizModal').css('display', 'block');
    $('#bizModalImg').attr('src', src);
    $('#bizModalCaption').html(title);
    $('#bizModalClose').click(function(){ $('#bizModal').css('display', 'none'); });
}

function bizShareUserTrash(event) {
    $(event).closest('tr').remove();
}

function btnEmptyTrash() {
    if (confirm("Plesae confirm to empty your trash?")) { bizJsonAction('bizStorage/fileTrashPerm&emptyTrash=1'); }
}

function btnInfoToggle() {
    alert('btnInfoToggle');
}

function btnShareSubmitResp() {
    $('#divMsg').dialog('close');
}

function btnSortToggle() {
    alert('btnSortToggle');
}

function btnUploadToggle() {
    $("#dropzone").toggle();
}

/**
 * Adds a selected user to the table and clears the select and ID input values
 * @returns {undefined}
 */
function bizViewShareAdd(event, ui) {
//    alert('adding user id: '+ui.item.id);
    ui.item.label = ui.item.label.replace(/</g, "&lt;");
    ui.item.label = ui.item.label.replace(/>/g, "&gt;");
    var newRow = '<tr><td><span id="'+ui.item.id+'" onClick="if (confirm("Are you sure?")) { $(this).closest("tr").remove(); }" class="treeIcon"><i class="fad fa-trash"></i></span></td>';
    newRow += '<td><input type="hidden" id="can_read_'+ui.item.id+'" value="'+ui.item.id+'">'+ui.item.label+'</td>';
    newRow += '<td style="text-align:center"><span><input type="checkbox" id="can_edit_'+ui.item.id+'" value="1"></span></td></tr>';
    $('#bizShareTable tr:first').after(newRow);
    $('#shareUsers').val('').focus();
}

function bizViewShareInit() {
    $(".folder-menu").hide();
    var page = bizunoAjax+'&bizRt=bizStorage/getShareForm&rID='+bizPostID;
    $('#divMsg')
        .html('&nbsp;')
        .dialog({
            text: 'Standby',
            autoOpen: false,
            show: { effect: "blind",   duration: 1000 },
            hide: { effect: "explode", duration: 1000 },
            height: 625,
            width:  600,
            title: lang.share
        }).dialog({modal: true});
    $('#divMsg').load(page).dialog('open');
}

(function($) {

    $.cachedScript = function( url, options ) {
        options = $.extend( options || {}, { dataType: "script", cache: true, url: url });
        return $.ajax( options );
    };

    $.fn.serializeObject = function() {
        var o = {};
        var a = this.serializeArray();
        $.each(a, function() {
            if (o[this.name] !== undefined) {
                if (!o[this.name].push) {
                    o[this.name] = [o[this.name]];
                }
                o[this.name].push(this.value || '');
            } else {
                o[this.name] = this.value || '';
            }
        });
        return o;
    };

    // jsTree defaults
    $.jstree.defaults.core.themes.icons = false;
    $.jstree.defaults.core.themes.dots = false;
    $.jstree.defaults.core.themes.stripes = false; // Doesn't seem to work!

}(jQuery));
