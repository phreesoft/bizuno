<?php
/**
 * WordPress Plugin - bizuno-accounting - Portal API direct entry point
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
 * @version    7.x Last Update: 2026-05-20 (sanitize $_SERVER['DOCUMENT_ROOT'] before WP loads)
 * @filesource /portalAPI.php
 */

namespace bizuno;

// Bootstrap WordPress.
//
// This file is a direct file-serving entry point — it's reached BEFORE WP
// loads, so WP's sanitize_text_field() / wp_unslash() etc. aren't available
// yet to clean $_SERVER['DOCUMENT_ROOT']. Use plain-PHP guards instead:
// realpath() normalizes any path traversal and returns false on a bogus
// path, and file_exists() confirms we have a real WordPress install before
// requiring its bootstrap file.
// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- WP's sanitize_* not loaded yet (this file runs BEFORE wp-load.php); we sanitize via realpath() + file_exists() which is safe in plain PHP.
$doc_root = isset( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
if ( !$doc_root || !file_exists( $doc_root . '/wp-load.php' ) ) {
    http_response_code( 500 );
    exit( 'Bizuno portalAPI: unable to locate WordPress (wp-load.php).' );
}
require_once $doc_root . '/wp-load.php';

require_once __DIR__ . '/portalCFG.php';
new portalCtl();
