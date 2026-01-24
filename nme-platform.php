<?php
/**
 * Plugin Name: NME Platform
 * Description: Naturalization application management system
 * Version: 1.0.0
 * Author: Darin Kershner
 * Text Domain: nme-platform
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined('ABSPATH') || exit;

// Plugin constants
define('NME_PLATFORM_VERSION', '1.0.0');
define('NME_PLATFORM_PATH', plugin_dir_path(__FILE__));
define('NME_PLATFORM_URL', plugin_dir_url(__FILE__));

// Autoloader
require_once NME_PLATFORM_PATH . 'includes/class-autoloader.php';
NME\Core\Autoloader::register();

// Initialize plugin
add_action('plugins_loaded', function() {
    require_once NME_PLATFORM_PATH . 'includes/class-plugin.php';
    NME\Core\Plugin::init();
}, 10);
