/*
 * Common javascript file loaded with the portal
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
 * @version    7.x Last Update: 2026-04-01
 * @filesource /view/themes/kendoUI/portal.js
 */

var jqBiz = $.noConflict();
var bizID = 0;

const isDarkMode    = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : '';
const screenWidth   = screen.width; // screen dims
const screenHeight  = screen.height;
const viewportWidth = window.innerWidth; // window dims
const viewportHeight= window.innerHeight;

// Appends the users viewport size, screen size, and display mode preferences to the login screen
function appendPrefs() {
    const form = jqBiz('#frmLogin');
    if (!form.length) return;
    let action = window.location.href;
    // Only append if not already present
    if (!action.includes('mode='))   { action += (action.includes('?') ? '&' : '?') + 'mode=' + isDarkMode; }
    if (!action.includes('screen=')) { action += (action.includes('?') ? '&' : '?') + 'screen=' + screenWidth; }
    form.attr('action', action);
}

/**
 * This function extracts the returned messageStack messages and displays then according to the severity
 */
function displayMessage(message) {
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
    if (msgText) alert( '<p>Error/Caution/Warning</p>' + msgText );
    // Now process Info and Success
    if (message.info) {
        msgText = '';
        msgTitle= bizLangJS('INFORMATION');
        msgID   = Math.floor((Math.random() * 1000000) + 1); // random ID to keep boxes from stacking and crashing easyui
        for (var i=0; i<message.info.length; i++) {
            if (typeof message.info[i].title !== 'undefined') { msgTitle = message.info[i].title; }
            msgText += '<span>'+message.info[i].text+'</span><br />';
        }
        alert ( '<p>Info</p>' + msgText );
    }
    if (message.success) {
        msgText = '';
//        for (var i=0; i<message.success.length; i++) { msgText += '<span>'+message.success[i].text+'</span><br />'; }
//        jqBiz.messager.show({title:bizLangJS('MESSAGE'), msg:msgText, timeout:5000, width:400, height:200, style:{ right:'', top:'', bottom:-document.body.scrollTop-document.documentElement.scrollTop }});
    }
}
