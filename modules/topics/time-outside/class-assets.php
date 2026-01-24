<?php
/**
 * Time Outside Assets
 *
 * Enqueues JavaScript and CSS for Time Outside functionality.
 * 
 * Pages:
 * - 582: Add form (Form 42)
 * - 706: Dashboard/View (GravityView ID 581 for edit)
 *
 * @package NME\Topics\TimeOutside
 */

namespace NME\Topics\TimeOutside;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    /**
     * Form ID for Time Outside entries
     */
    const FORM_ID = 42;

    /**
     * Page IDs
     */
    const PAGE_ADD = 582;
    const PAGE_DASHBOARD = 706;

    /**
     * GravityView ID for edit
     */
    const GRAVITYVIEW_EDIT_ID = 581;

    /**
     * Initialize assets
     */
    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_scripts' ] );
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue_styles' ] );
    }

    /**
     * Get the module URL
     *
     * @return string
     */
    private static function get_module_url(): string {
        // Get URL relative to plugin
        $plugin_dir = defined( 'NME_PLATFORM_DIR' ) ? NME_PLATFORM_DIR : WP_PLUGIN_DIR . '/nme-platform/';
        $module_path = str_replace( $plugin_dir, '', __DIR__ );
        $plugin_url = defined( 'NME_PLATFORM_URL' ) ? NME_PLATFORM_URL : plugins_url( 'nme-platform/' );
        
        return trailingslashit( $plugin_url . $module_path );
    }

    /**
     * Get module version for cache busting
     *
     * @return string
     */
    private static function get_version(): string {
        return defined( 'NME_PLATFORM_VERSION' ) ? NME_PLATFORM_VERSION : '1.0.0';
    }

    /**
     * Check if we're on a TOC page
     *
     * @return bool
     */
    private static function is_toc_page(): bool {
        return is_page( self::PAGE_ADD ) || is_page( self::PAGE_DASHBOARD );
    }

    /**
     * Check if we're on the dashboard page
     *
     * @return bool
     */
    private static function is_dashboard_page(): bool {
        return is_page( self::PAGE_DASHBOARD );
    }

    /**
     * Check if we're on the add page
     *
     * @return bool
     */
    private static function is_add_page(): bool {
        return is_page( self::PAGE_ADD );
    }

    /**
     * Enqueue scripts for TOC pages
     */
    public static function enqueue_scripts(): void {
        if ( ! self::is_toc_page() ) {
            return;
        }

        $module_url = self::get_module_url();
        $version = self::get_version();

        // Ensure jQuery is loaded
        wp_enqueue_script( 'jquery' );

        // Dashboard page (706) scripts
        if ( self::is_dashboard_page() ) {
            // TOC Finish - Evaluates trips for 6+ month duration, overlaps, physical presence
            wp_enqueue_script(
                'nme-toc-finish',
                $module_url . 'assets/js/nme-toc-finish.js',
                [],
                $version,
                true
            );

            // TOC Dashboard - Button updates, date storage, deletion logic
            wp_enqueue_script(
                'nme-toc-dashboard',
                $module_url . 'assets/js/nme-toc-dashboard.js',
                [ 'jquery' ],
                $version,
                true
            );

            // Localize dashboard script with user data
            wp_localize_script( 'nme-toc-dashboard', 'nmeData', [
                'anumber'       => get_user_meta( get_current_user_id(), 'anumber', true ),
                'parentEntryId' => get_user_meta( get_current_user_id(), 'parent_entry_id', true ),
            ] );
        }

        // Both add (582) and dashboard (706) pages
        // DateSpan - Displays date range message based on residence requirement
        wp_enqueue_script(
            'nme-toc-datespan',
            $module_url . 'assets/js/nme-toc-datespan.js',
            [],
            $version,
            true
        );

        // Skip button injector - Shows Skip/Cancel buttons based on entry state
        wp_enqueue_script(
            'nme-toc-skip-button',
            $module_url . 'assets/js/toc-skip-button-injector.js',
            [ 'jquery' ],
            $version,
            true
        );

        // Localize skip button script with AJAX data
        wp_localize_script( 'nme-toc-skip-button', 'nmeAjax', [
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'nme-toc-ajax-nonce' ),
        ] );

        // TOC Validation - Date validation for add/edit pages
        wp_enqueue_script(
            'nme-toc-validation',
            $module_url . 'assets/js/nme-toc-validation.js',
            [ 'jquery' ],
            $version,
            true
        );
    }

    /**
     * Enqueue styles for TOC pages
     */
    public static function enqueue_styles(): void {
        if ( ! self::is_toc_page() ) {
            return;
        }

        $module_url = self::get_module_url();
        $version = self::get_version();

        wp_enqueue_style(
            'nme-time-outside',
            $module_url . 'assets/css/time-outside.css',
            [],
            $version
        );
    }
}
