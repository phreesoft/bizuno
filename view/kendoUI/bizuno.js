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
 * @version    7.x Last Update: 2025-12-01
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
    var form = jqBiz('#frmLogin');
    if (typeof form === 'undefined') { return; } // Not at login screen
    jqBiz('#frmLogin').attr('action', window.location.href + "?mode=" + isDarkMode + "&screen=" + screenWidth);
}
