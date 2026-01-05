<?php
/**
 * Plugin Name: Bizuno
 * Plugin URI:  https://www.phreesoft.com
 * Description: Bizuno is a powerful ERP/Accounting application designed to streamline every facet of your business. Once installed and activated, Bizuno behaves like a stand-alone page within your WordPress website.
 * Version:     7.3.5
 * Requires PHP: 8.2
 * Requires at least: 6.5
 * Tested up to: 6.9
 * Author:      PhreeSoft, Inc.
 * Author URI:  http://www.PhreeSoft.com
 * Text Domain: bizuno
 * License:     Affero GPL 3.0
 * License URI: https://www.gnu.org/licenses/agpl-3.0.txt
 * Domain Path: /locale
 * Update URI:  https://bizuno.com/downloads/bizuno-wp.json
 */

defined( 'ABSPATH' ) || exit;

class bizuno_wp
{
    public function __construct()
    {
    }
}
new bizuno_wp();

// Handle auto-updates via the WordPress core
require_once plugin_dir_path( __FILE__ ) . 'vendor/yahniselsts/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker ( 'https://bizuno.com/downloads/bizuno-wp.json', __FILE__, 'bizuno-wp' );
