<?php
/**
 * Counsel Module - NME Platform
 *
 * Application Counsel - displays modal warnings for specific field answers.
 * Ported from NME-Settings, matches original behavior exactly.
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Counsel;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Configuration Constants
 */
if (!defined('NME_COUNSEL_FORM_ID')) {
    define('NME_COUNSEL_FORM_ID', 39);
}
if (!defined('NME_COUNSEL_FLAG_FIELD_ID')) {
    define('NME_COUNSEL_FLAG_FIELD_ID', 924);
}
if (!defined('NME_COUNSEL_FLAG_VALUE_BOUNCE')) {
    define('NME_COUNSEL_FLAG_VALUE_BOUNCE', 'Bounce');
}

/**
 * Fields that trigger modal when answered YES
 */
if (!defined('NME_COUNSEL_YES_FIELDS')) {
    define('NME_COUNSEL_YES_FIELDS', json_encode([
        774, 777, 776, 775, 779, 778, 780, 786, 785, 784, 783, 782, 781, 799, 798, 797, 796,
        795, 794, 793, 792, 791, 790, 789, 788, 787, 805, 803, 802, 801, 800, 817, 937, 815, 
        814, 813, 812, 811, 818, 861, 858, 862, 867, 868, 875, 874, 873, 878, 877, 889
    ]));
}

/**
 * Fields that trigger modal when answered NO
 */
if (!defined('NME_COUNSEL_NO_FIELDS')) {
    define('NME_COUNSEL_NO_FIELDS', json_encode([
        859, 883, 882, 890, 888, 887, 886, 885, 940
    ]));
}

class Counsel {

    /**
     * Module version
     */
    const VERSION = '1.0.0';

    /**
     * Option key (same as original NME-Settings)
     */
    const OPTION_KEY = 'nme_settings_bouncer';

    /**
     * Initialize the module
     */
    public static function init() {
        self::load_dependencies();

        if (is_admin()) {
            Admin::init();
        }

        // Runtime always loads (has its own frontend checks)
        Runtime::init();
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
     * Get YES field IDs
     */
    public static function get_yes_fields() {
        $raw = NME_COUNSEL_YES_FIELDS;
        $list = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        return array_map('intval', (array) $list);
    }

    /**
     * Get NO field IDs
     */
    public static function get_no_fields() {
        $raw = NME_COUNSEL_NO_FIELDS;
        $list = is_string($raw) ? json_decode($raw, true) : (array) $raw;
        return array_map('intval', (array) $list);
    }

    /**
     * Get all monitored field IDs (union of YES and NO)
     */
    public static function get_all_fields() {
        $list = array_merge(self::get_yes_fields(), self::get_no_fields());
        return array_values(array_unique(array_filter($list, function($n) { return $n > 0; })));
    }

    /**
     * Get form ID
     */
    public static function get_form_id() {
        return defined('NME_COUNSEL_FORM_ID') ? (int) NME_COUNSEL_FORM_ID : 39;
    }

    /**
     * Get module info for dashboard
     */
    public static function get_info() {
        return [
            'name'        => 'Application Counsel',
            'description' => 'Modal warnings for sensitive application questions',
            'version'     => self::VERSION,
            'type'        => 'features',
        ];
    }
}

// Initialize the module
add_action('plugins_loaded', [Counsel::class, 'init'], 15);
