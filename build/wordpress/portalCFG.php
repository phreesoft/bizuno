<?php
/**
 * WordPress Plugin - bizuno-accounting - Portal Configuration
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
 * @version    7.x Last Update: 2026-05-20 (self-contained: src/, vendor/, scripts/ all inside the plugin; no sibling library plugin required)
 * @filesource /portalCFG.php
 */

namespace bizuno;

global $wpdb;

// ─── Business-specific ───────────────────────────────────────────────────────
//
// These are deliberately weak defaults; production sites should override the
// BIZUNO_KEY (used to encrypt stored PII) by defining it in wp-config.php
// before the plugin loads. The default key here is fine for development /
// trial use but should be replaced before storing any real customer data.
if ( !defined( 'BIZUNO_BIZID' ) )       { define( 'BIZUNO_BIZID',       '1' ); }                                  // Bizuno Business ID (multi-business installs)
if ( !defined( 'BIZUNO_DATA' ) )        { define( 'BIZUNO_DATA',        wp_get_upload_dir()['basedir'] . '/bizuno/' ); }  // Uploads / attachments / cache / backups
if ( !defined( 'BIZUNO_KEY' ) )         { define( 'BIZUNO_KEY',         '0123456789abcdef' ); }                   // 16-char encryption key for stored PII
if ( !defined( 'BIZUNO_DB_PREFIX' ) )   { define( 'BIZUNO_DB_PREFIX',   $wpdb->prefix . 'bizuno_' ); }            // Bizuno tables share WP's prefix + bizuno_
if ( !defined( 'BIZUNO_DB_CREDS' ) )    { define( 'BIZUNO_DB_CREDS',    [
    'type'   => 'mysql',
    'host'   => DB_HOST,
    'name'   => DB_NAME,
    'user'   => DB_USER,
    'pass'   => DB_PASSWORD,
    'prefix' => BIZUNO_DB_PREFIX,
] ); }

// ─── Platform-specific: filesystem paths ─────────────────────────────────────
//
// Everything Bizuno needs ships inside this plugin directory now:
//   <plugin>/src/      — Bizuno PHP library
//   <plugin>/vendor/   — composer dependencies (fpdf, fpdi, phpmailer, …)
//   <plugin>/scripts/  — third-party UI assets (jquery-easyui, jQuery UI, …)
//
// Pre-7.3.9 the library lived in a sibling `bizuno-wp` plugin; that's gone
// now and the dual-path detection that used to live here is no longer
// needed.
if ( !defined( 'BIZUNO_FS_PORTAL' ) )   { define( 'BIZUNO_FS_PORTAL',   plugin_dir_path( __FILE__ ) ); }
if ( !defined( 'BIZUNO_FS_LIBRARY' ) )  { define( 'BIZUNO_FS_LIBRARY',  plugin_dir_path( __FILE__ ) . 'src/' ); }
if ( !defined( 'BIZUNO_FS_ASSETS' ) )   { define( 'BIZUNO_FS_ASSETS',   plugin_dir_path( __FILE__ ) . 'vendor/' ); }

// ─── Platform-specific: URLs ─────────────────────────────────────────────────
if ( !defined( 'BIZUNO_URL_AJAX' ) )    { define( 'BIZUNO_URL_AJAX',    admin_url() . 'admin-ajax.php?action=bizuno_ajax' ); }
if ( !defined( 'BIZUNO_URL_API' ) )     { define( 'BIZUNO_URL_API',     plugin_dir_url( __FILE__ ) . 'portalAPI.php?bizRt=' ); }
if ( !defined( 'BIZUNO_URL_FS' ) )      { define( 'BIZUNO_URL_FS',      plugin_dir_url( __FILE__ ) . 'portalAPI.php?bizRt=portal/api/fs&src=' ); }
if ( !defined( 'BIZUNO_URL_PORTAL' ) )  { define( 'BIZUNO_URL_PORTAL',  home_url() . '/bizuno?' ); }
if ( !defined( 'BIZUNO_URL_SCRIPTS' ) ) { define( 'BIZUNO_URL_SCRIPTS', plugin_dir_url( __FILE__ ) . 'scripts/' ); }
// View assets (icons, theme images) live under src/ post-Phase-2. Match the
// standalone install convention: no trailing slash, callers append paths
// like '/view/icons/sales.png' themselves.
if ( !defined( 'BIZUNO_URL_VIEW' ) )    { define( 'BIZUNO_URL_VIEW',    rtrim( plugin_dir_url( __FILE__ ), '/' ) . '/src' ); }

// ─── WP-specific quirks ──────────────────────────────────────────────────────
// WordPress historically adds magic-quote slashes to all input arrays. Bizuno's
// input cleaner strips them when this flag is on.
if ( !defined( 'BIZUNO_STRIP_SLASHES' ) ) { define( 'BIZUNO_STRIP_SLASHES', true ); }

// ─── Boot Bizuno ─────────────────────────────────────────────────────────────
require_once ( BIZUNO_FS_LIBRARY . 'portal/controller.php' );
require_once ( BIZUNO_FS_LIBRARY . 'bizunoCFG.php' );
