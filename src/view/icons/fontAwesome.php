<?php
/*
 * Sets the list of available icons and how to access them. Typically called from the dynamic css pull
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
 * @filesource /view/theme/default/fontAwesome.php
 */

$icons = [
    'add'        => ['path'=>'plus'],
    'apply'      => ['path'=>'check'],
    'attachment' => ['path'=>'paperclip'],
    'average'    => ['path'=>'pause'],
    'back'       => ['path'=>'arrow-alt-left'],
    'previous'   => ['path'=>'arrow-alt-left'], // merge
    'backup'     => ['path'=>'save'],
    'bank'       => ['path'=>'university'],
    'bank-check' => ['path'=>'money-check'],
    'barcode'    => ['path'=>'barcode-alt'],
    'bkmrkDel'   => ['path'=>'bookmark'], // bookmark-minus ???
    'bookmark'   => ['path'=>'bookmark'],
    'budget'     => ['path'=>'file-invoice-dollar'],
    'cancel'     => ['path'=>'times-circle'],
    'chat'       => ['path'=>'comments-alt'],
    'collapse'   => ['path'=>'minus-square'],
    'checkin'    => ['path'=>'cloud-upload'],
    'upload'     => ['path'=>'upload'], // merge ???
    'checkout'   => ['path'=>'cloud-download'],
    'update'     => ['path'=>'cloud-download'], // merge ???
    'clear'      => ['path'=>'sync'],
    'close'      => ['path'=>'window-close'],
    'continue'   => ['path'=>'arrow-alt-right'],
    'next'       => ['path'=>'arrow-alt-right'], // merge
    'copy'       => ['path'=>'copy'],
    'credit'     => ['path'=>'money-check-edit'],
    'date'       => ['path'=>'calendar'],
    'debug'      => ['path'=>'debug'],
    'delete'     => ['path'=>'trash-alt'],
    'trash'      => ['path'=>'trash-alt'], // merge
    'design'     => ['path'=>'drafting-compass'],
    'dashboard'  => ['path'=>'solar-panel'],
    'dirNew'     => ['path'=>'folder-plus'],
    'docNew'     => ['path'=>'file-plus'],
    'download'   => ['path'=>'download'],
    'drag'       => ['path'=>'sort'],
    'edit'       => ['path'=>'edit'],
    'rename'     => ['path'=>'edit'], // merge
    'email'      => ['path'=>'envelope'],
    'employee'   => ['path'=>'id-badge'],
    'encrypt-off'=> ['path'=>'shield-alt'],
    'expand'     => ['path'=>'plus-square'],
    'exit'       => ['path'=>'sign-out'],
    'logout'     => ['path'=>'sign-out'], // merge
    'export'     => ['path'=>'file-export'],
    'fileMgr'    => ['path'=>'cabinet-filing'],
    'fillup'     => ['path'=>'eject'],
    'fullscreen' => ['path'=>'expand'],
    'help'       => ['path'=>'question-square'],
    'home'       => ['path'=>'home'],
    'import'     => ['path'=>'file-import'],
    'inv-adj'    => ['path'=>'calculator-alt'],
    'inventory'  => ['path'=>'inventory'],
    'invoice'    => ['path'=>'file-invoice-dollar'],
    'journal'    => ['path'=>'balance-scale'],
    'register'   => ['path'=>'balance-scale'], // merge
    'loading'    => ['path'=>'spinner-third'],
    'lock'       => ['path'=>'lock-alt'],
    'locked'     => ['path'=>'lock-alt'], // merge
    'merge'      => ['path'=>'code-merge'],
    'mimeDir'    => ['path'=>'folder-tree'],
    'mimeDoc'    => ['path'=>'file-alt'],
    'mimeDrw'    => ['path'=>'file-image'],
    'mimeHtml'   => ['path'=>'file-csv'],
    'mimeImg'    => ['path'=>'file-image'],
    'mimeLst'    => ['path'=>'file-alt'],
    'mimePdf'    => ['path'=>'file-pdf'],
    'mimePpt'    => ['path'=>'presentation'],
    'mimeTxt'    => ['path'=>'file'],
    'mimeXls'    => ['path'=>'file-spreadsheet'],
    'mimeZip'    => ['path'=>'file-archive'],
    'mimeXML'    => ['path'=>'code'],
    'message'    => ['path'=>'sticky-note'],
    'more'       => ['path'=>'ellipsis-v'],
    'move'       => ['path'=>'expand-arrows'],
    'new'        => ['path'=>'file-medical'],
    'newFolder'  => ['path'=>'folder-plus'],
    'no_image'   => ['path'=>'exclamation-square'],
    'open'       => ['path'=>'folder-open'],
    'order'      => ['path'=>'pen'],
    'payment'    => ['path'=>'cash-register'],
    'phpmyadmin' => ['path'=>'database'],
    'pos'        => ['path'=>'desktop'],
    'preview'    => ['path'=>'print-search'],
    'price'      => ['path'=>'tag'],
    'print'      => ['path'=>'print'],
    'profile'    => ['path'=>'preferences-desktop-locale'],
    'purchase'   => ['path'=>'address-card'],
    'quality'    => ['path'=>'badge-sheriff'], // needs better icon
    'quote'      => ['path'=>'comment-alt-edit'],
    'recur'      => ['path'=>'share-square'],
    'refresh'    => ['path'=>'sync-alt'],
    'report'     => ['path'=>'file-chart-line'],
    'reset'      => ['path'=>'redo'],
    'restore'    => ['path'=>'window-restore'],
    'roles'      => ['path'=>'hat-cowboy'],
    'sales'      => ['path'=>'file-invoice-dollar'],
    'save'       => ['path'=>'save'],
    'saveprint'  => ['path'=>'envelope-open-dollar'],
    'save_as'    => ['path'=>'share'],
    'search'     => ['path'=>'search'],
    'select_all' => ['path'=>'clipboard-list-check'],
    'settings'   => ['path'=>'cog'],
    'shipping'   => ['path'=>'truck'],
    'truck'      => ['path'=>'truck'], // merge
    'steps'      => ['path'=>'shoe-prints'],
    'support'    => ['path'=>'ticket'],
    'tip'        => ['path'=>'lightbulb-on'],
    'toggle'     => ['path'=>'toggle-on'],
    'tools'      => ['path'=>'tools'],
    'transfer'   => ['path'=>'usb-drive'],
    'unlock'     => ['path'=>'unlock'],
    'up'         => ['path'=>'arrow-alt-up'],
    'users'      => ['path'=>'users'],
    'web'        => ['path'=>'globe-americas'],
    'winNew'     => ['path'=>'window-restore'],
    'work'       => ['path'=>'hard-hat'],
    'wp-admin'   => ['path'=>'wordpress'],
];