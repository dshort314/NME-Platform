<?php
/**
 * Information About You Assets
 * 
 * Enqueues JavaScript and CSS for Form 70 on page 703.
 * These scripts control field visibility, date calculations,
 * eligibility logic, and user interactions.
 * 
 * Debug logging is controlled globally via NME Platform > Dashboard.
 * The global nme-debug.js script handles all debug flag passing.
 */

namespace NME\Topics\InformationAboutYou;

defined('ABSPATH') || exit;

class Assets {

    /** @var int Page ID where Form 70 is embedded */
    const PAGE_ID = 703;

    /** @var int Form ID */
    const FORM_ID = 70;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
    }

    /**
     * Enqueue stylesheets for page 703
     */
    public static function enqueue_styles(): void {
        if (!is_page(self::PAGE_ID)) {
            return;
        }

        wp_enqueue_style(
            'nme-iay-public',
            NME_PLATFORM_URL . 'modules/topics/information-about-you/assets/css/nme-app-public.css',
            [],
            NME_PLATFORM_VERSION,
            'all'
        );
    }

    /**
     * Enqueue scripts for page 703
     * 
     * Scripts are loaded in dependency order:
     * 1. nme-debug (global) - Debug utility (loaded by Plugin class)
     * 2. date-calculations - Core date parsing and formatting
     * 3. modal-alerts - Modal dialog system
     * 4. eligibility-logic - Controlling factor determination
     * 5. form-handlers - Form event handlers
     * 6. field-visibility - Conditional field display
     * 7. nme-app-public - Main initialization
     */
    public static function enqueue_scripts(): void {
        if (!is_page(self::PAGE_ID)) {
            return;
        }

        $base_url = NME_PLATFORM_URL . 'modules/topics/information-about-you/assets/js/';
        $version = NME_PLATFORM_VERSION;

        // Ensure jQuery is loaded
        wp_enqueue_script('jquery');

        // 1. Date Calculations - depends on nme-debug for logging
        wp_enqueue_script(
            'nme-date-calculations',
            $base_url . 'nme-date-calculations.js',
            ['jquery', 'nme-debug'],
            $version,
            true
        );

        // 2. Modal Alerts - depends on global NMEModal system
        wp_enqueue_script(
            'nme-modal-alerts',
            $base_url . 'nme-modal-alerts.js',
            ['jquery', 'nme-debug', 'nme-modals'],
            $version,
            true
        );

        // 3. Eligibility Logic - depends on date-calculations
        wp_enqueue_script(
            'nme-eligibility-logic',
            $base_url . 'nme-eligibility-logic.js',
            ['jquery', 'nme-debug', 'nme-date-calculations'],
            $version,
            true
        );

        // 4. Form Handlers - depends on date-calculations
        wp_enqueue_script(
            'nme-form-handlers',
            $base_url . 'nme-form-handlers.js',
            ['jquery', 'nme-debug', 'nme-date-calculations'],
            $version,
            true
        );

        // 5. Field Visibility
        wp_enqueue_script(
            'nme-field-visibility',
            $base_url . 'nme-field-visibility.js',
            ['jquery', 'nme-debug'],
            $version,
            true
        );

        // 6. Main initialization script - depends on all modules
        wp_enqueue_script(
            'nme-app-public',
            $base_url . 'nme-app-public.js',
            [
                'jquery',
                'nme-debug',
                'nme-date-calculations',
                'nme-modal-alerts',
                'nme-eligibility-logic',
                'nme-form-handlers',
                'nme-field-visibility'
            ],
            $version,
            true
        );

        // Localize script with PHP data (debug flag now handled globally by nme-debug)
        wp_localize_script('nme-app-public', 'nme_app_ajax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('nme_app_nonce'),
            'form_id'  => self::FORM_ID,
            'page_id'  => self::PAGE_ID,
        ]);
    }
}
