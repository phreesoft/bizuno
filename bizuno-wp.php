<?php
/**
 * Plugin Name:       Bizuno – Full ERP/Accounting Portal
 * Plugin URI:        https://www.phreesoft.com
 * Description:       Powerful open-source ERP, double-entry accounting, inventory, CRM & business management portal for WordPress. Activate, click the Bizuno menu in admin, and complete the quick install to launch your full system.
 * Version:           7.3.8
 * Requires at least: 6.5
 * Tested up to:      6.9.4
 * Requires PHP:      8.2
 * Author:            PhreeSoft, Inc.
 * Author URI:        https://www.phreesoft.com
 * Author Email:      support@phreesoft.com
 * Text Domain:       bizuno
 * Domain Path:       /locale
 * License:           AGPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/agpl-3.0.txt
 * Update URI:        https://bizuno.com/downloads/bizuno-wp.json
 */

defined( 'ABSPATH' ) || exit;

class bizuno_wp
{
    public function __construct()
    {
        // Class Initialization
        // Actions
        // Filters
    }
}
new bizuno_wp();

// Handle auto-updates via the WordPress core
require_once plugin_dir_path( __FILE__ ) . 'vendor/yahniselsts/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;
$myUpdateChecker = PucFactory::buildUpdateChecker ( 'https://bizuno.com/downloads/bizuno-wp.json', __FILE__, 'bizuno-wp' );
