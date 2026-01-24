<?php
/**
 * Tooltips Module - NME Platform
 *
 * Provides configurable tooltips ("Guidance" buttons) for Gravity Forms fields.
 * Matches original NME-Settings behavior exactly.
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Tooltips;

if (!defined('ABSPATH')) {
    exit;
}

class Tooltips {

    /**
     * Module version
     */
    const VERSION = '1.0.0';

    /**
     * Allowed form IDs for tooltips
     */
    const ALLOWED_FORMS = [75, 70, 42, 38, 71, 72, 73, 74, 39, 78];

    /**
     * Initialize the module
     */
    public static function init() {
        // Load dependencies
        self::load_dependencies();

        // Initialize admin only in admin context
        if (is_admin()) {
            Admin::init();
        }

        // Initialize frontend runtime only on frontend
        if (!is_admin()) {
            Runtime::init();
        }
    }

    /**
     * Load required class files
     */
    private static function load_dependencies() {
        $module_path = dirname(__FILE__) . '/';

        require_once $module_path . 'class-admin.php';
        require_once $module_path . 'class-runtime.php';
    }

    /**
     * Get allowed form IDs
     */
    public static function get_allowed_forms() {
        return self::ALLOWED_FORMS;
    }

    /**
     * Get module info for dashboard
     */
    public static function get_info() {
        return [
            'name'        => 'Tooltips',
            'description' => 'Add "Guidance" tooltip buttons to form fields',
            'version'     => self::VERSION,
            'type'        => 'features',
        ];
    }
}

// Initialize the module
add_action('plugins_loaded', [Tooltips::class, 'init'], 15);
