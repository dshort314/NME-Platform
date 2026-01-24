<?php
/**
 * Residences Assets
 *
 * Enqueues JavaScript and CSS for Residences functionality.
 * 
 * Pages:
 * - 504: Add form (Form 38)
 * - 705: Dashboard/View (main list)
 * - 514: Edit page
 * - GravityView 513 for editing
 *
 * @package NME\Topics\Residences
 */

namespace NME\Topics\Residences;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Assets {

    /**
     * Form ID for Residence entries
     */
    const FORM_ID = 38;

    /**
     * Page IDs
     */
    const PAGE_ADD = 504;
    const PAGE_DASHBOARD = 705;
    const PAGE_EDIT = 514;

    /**
     * GravityView ID for edit
     */
    const GRAVITYVIEW_EDIT_ID = 513;

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
     * Check if we're on a Residence page
     *
     * @return bool
     */
    public static function is_residence_page(): bool {
        return is_page( self::PAGE_ADD ) || is_page( self::PAGE_DASHBOARD ) || is_page( self::PAGE_EDIT );
    }

    /**
     * Check if we're on the dashboard page
     *
     * @return bool
     */
    public static function is_dashboard_page(): bool {
        return is_page( self::PAGE_DASHBOARD );
    }

    /**
     * Check if we're on the add page
     *
     * @return bool
     */
    public static function is_add_page(): bool {
        return is_page( self::PAGE_ADD );
    }

    /**
     * Check if we're on the edit page
     *
     * @return bool
     */
    public static function is_edit_page(): bool {
        return is_page( self::PAGE_EDIT );
    }

    /**
     * Check if we're in GravityView edit mode
     *
     * @return bool
     */
    public static function is_gravityview_edit(): bool {
        return isset( $_GET['gvid'] ) && intval( $_GET['gvid'] ) === self::GRAVITYVIEW_EDIT_ID;
    }

    /**
     * Enqueue scripts for Residence pages
     */
    public static function enqueue_scripts(): void {
        if ( ! self::is_residence_page() ) {
            return;
        }

        $module_url = self::get_module_url();
        $version = self::get_version();

        // Ensure jQuery is loaded
        wp_enqueue_script( 'jquery' );

        // Dashboard page (705) scripts
        if ( self::is_dashboard_page() && ! self::is_gravityview_edit() ) {
            // Residence Dashboard - Button updates, duration calculations, entry storage
            wp_enqueue_script(
                'nme-res-dashboard',
                $module_url . 'assets/js/nme-res-dashboard.js',
                [ 'jquery' ],
                $version,
                true
            );

            // Localize dashboard script with user data
            wp_localize_script( 'nme-res-dashboard', 'nmeResData', [
                'anumber'       => get_user_meta( get_current_user_id(), 'anumber', true ),
                'parentEntryId' => get_user_meta( get_current_user_id(), 'parent_entry_id', true ),
            ] );

            // Residence Deletion Handler
            wp_enqueue_script(
                'nme-res-deletion',
                $module_url . 'assets/js/nme-res-deletion.js',
                [ 'jquery' ],
                $version,
                true
            );
        }

        // Edit page (514) and dashboard (705) - validation scripts
        if ( self::is_edit_page() || self::is_dashboard_page() ) {
            // Date validation script
            wp_enqueue_script(
                'nme-res-validation',
                $module_url . 'assets/js/nme-res-validation.js',
                [ 'jquery' ],
                $version,
                true
            );

            // Boundary validation script
            wp_enqueue_script(
                'nme-res-boundaries',
                $module_url . 'assets/js/nme-res-boundaries.js',
                [ 'jquery' ],
                $version,
                true
            );
        }

        // Add page (504) - validation for new entries
        if ( self::is_add_page() ) {
            wp_enqueue_script(
                'nme-res-validation',
                $module_url . 'assets/js/nme-res-validation.js',
                [ 'jquery' ],
                $version,
                true
            );
        }
    }

    /**
     * Enqueue styles for Residence pages
     */
    public static function enqueue_styles(): void {
        if ( ! self::is_residence_page() ) {
            return;
        }

        $module_url = self::get_module_url();
        $version = self::get_version();

        wp_enqueue_style(
            'nme-residences',
            $module_url . 'assets/css/residences.css',
            [],
            $version
        );
    }
}
