<?php
/**
 * Residences Handler
 *
 * Handles all residence-related PHP functionality including:
 * - Parent entry data injection for JavaScript
 * - Country limitation for Form 38 (US only)
 * - localStorage clearing on dashboard load
 * - AJAX handlers
 *
 * @package NME\Topics\Residences
 */

namespace NME\Topics\Residences;

use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Handler {

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
     * Master Form field IDs
     */
    const MASTER_FIELD_CONTROLLING_FACTOR = 894;
    const MASTER_FIELD_APPLICATION_DATE = 895;

    /**
     * Initialize handler
     */
    public static function init(): void {
        // Inject parent entry data into JavaScript
        add_action( 'wp_head', [ __CLASS__, 'inject_parent_entry_data' ], 1 );
        
        // Clear localStorage on dashboard load
        add_action( 'wp_head', [ __CLASS__, 'clear_residence_variables' ] );
        
        // Country limitation for Form 38
        add_filter( 'gform_pre_render_' . self::FORM_ID, [ __CLASS__, 'limit_countries' ] );
        add_filter( 'gform_pre_validation_' . self::FORM_ID, [ __CLASS__, 'limit_countries' ] );
        add_filter( 'gform_pre_submission_filter_' . self::FORM_ID, [ __CLASS__, 'limit_countries' ] );
        add_filter( 'gform_admin_pre_render_' . self::FORM_ID, [ __CLASS__, 'limit_countries' ] );
        
        // AJAX handler for checking residence entries
        add_action( 'wp_ajax_check_residence_entries_exist', [ __CLASS__, 'ajax_check_entries_exist' ] );
        add_action( 'wp_ajax_nopriv_check_residence_entries_exist', [ __CLASS__, 'ajax_check_entries_exist' ] );
    }

    /**
     * Check if we're on a residence page
     *
     * @return bool
     */
    private static function is_residence_page(): bool {
        return is_page( self::PAGE_ADD ) || is_page( self::PAGE_DASHBOARD ) || is_page( self::PAGE_EDIT );
    }

    /**
     * Inject parent entry data into JavaScript
     * Includes Controlling Factor (field 894) and Application Date (field 895)
     */
    public static function inject_parent_entry_data(): void {
        if ( ! self::is_residence_page() ) {
            return;
        }

        global $wpdb;

        // Get parent_entry_id from multiple sources
        $parent_entry_id = isset( $_GET['parent_entry_id'] ) ? intval( $_GET['parent_entry_id'] ) : null;

        if ( ! $parent_entry_id ) {
            $parent_entry_id = UserContext::get_parent_entry_id();
        }

        // Fallback: try to get from current entry's meta
        if ( ! $parent_entry_id && isset( $_GET['entry_id'] ) ) {
            $current_entry_id = intval( $_GET['entry_id'] );
            $parent_entry_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = 'parent_entry_id'",
                $current_entry_id
            ) );
        }

        $controlling_factor = null;
        $application_date = null;

        if ( $parent_entry_id ) {
            // Get Controlling Factor (field 894)
            $controlling_factor = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %s",
                intval( $parent_entry_id ),
                self::MASTER_FIELD_CONTROLLING_FACTOR
            ) );

            // Get Application Date (field 895)
            $application_date = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %s",
                intval( $parent_entry_id ),
                self::MASTER_FIELD_APPLICATION_DATE
            ) );
        }

        ?>
        <script>
            window.parentEntryResRequired = <?php echo json_encode( $controlling_factor ); ?>;
            window.parentEntryApplicationDate = <?php echo json_encode( $application_date ); ?>;
            console.log('NME Residence: Parent entry ID:', <?php echo json_encode( $parent_entry_id ); ?>, 
                        'Controlling Factor:', <?php echo json_encode( $controlling_factor ); ?>, 
                        'Application Date:', <?php echo json_encode( $application_date ); ?>);
        </script>
        <?php
    }

    /**
     * Clear residence localStorage variables on dashboard load
     * Only clears on dashboard page (705), NOT on edit or add pages
     */
    public static function clear_residence_variables(): void {
        // Only clear on dashboard page, not when in GravityView edit mode
        if ( ! is_page( self::PAGE_DASHBOARD ) ) {
            return;
        }

        // Don't clear if in GravityView edit mode
        if ( isset( $_GET['gvid'] ) && intval( $_GET['gvid'] ) === 513 ) {
            return;
        }

        ?>
        <script>
            // Only reset localStorage on dashboard load to prepare for new operations
            // DO NOT clear on edit pages (514) or add pages (504) as they need the stored values
            localStorage.removeItem('previousEntryFrom');
            localStorage.removeItem('subsequentEntryTo');
            localStorage.removeItem('res-count');
            console.log('NME Residence: Cleared localStorage variables on dashboard load');
        </script>
        <?php
    }

    /**
     * Limit countries to United States only for Form 38
     *
     * @param array $form The form array
     * @return array Modified form array
     */
    public static function limit_countries( array $form ): array {
        // Override the default countries list with just the United States
        add_filter( 'gform_countries', function( $countries ) {
            return [ 'United States' ];
        } );

        return $form;
    }

    /**
     * AJAX handler to check if residence entries exist for a user
     */
    public static function ajax_check_entries_exist(): void {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'nme-res-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Security check failed' ] );
            return;
        }

        $anumber = sanitize_text_field( $_POST['anumber'] ?? '' );

        if ( empty( $anumber ) ) {
            wp_send_json_error( [ 'message' => 'A-Number is required' ] );
            return;
        }

        global $wpdb;

        // Check if any residence entries exist for this A-Number
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = '4'
             AND em.meta_value = %s",
            self::FORM_ID,
            $anumber
        ) );

        wp_send_json_success( [
            'has_entries' => intval( $count ) > 0,
            'count'       => intval( $count ),
        ] );
    }
}
