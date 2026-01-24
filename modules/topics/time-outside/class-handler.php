<?php
/**
 * Time Outside Handler
 *
 * Handles PHP-side functionality for Time Outside:
 * - Inject parent entry data (Controlling Factor, Application Date) to JavaScript
 * - Clear localStorage on dashboard load
 * - Inline validation scripts for add/edit pages
 * - AJAX handler for checking if TOC entries exist
 *
 * IMPORTANT: JavaScript is authoritative for all date calculations.
 * This handler only provides data to JavaScript and handles AJAX requests.
 * The actual trip validation, physical presence calculations, and 6-month
 * detection are all performed client-side.
 *
 * @package NME\Topics\TimeOutside
 */

namespace NME\Topics\TimeOutside;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Handler {

    /**
     * Form ID for Time Outside entries
     */
    const FORM_ID = 42;

    /**
     * Master Form field IDs
     */
    const FIELD_CONTROLLING_FACTOR = 894;
    const FIELD_APPLICATION_DATE = 895;

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
     * Initialize handler
     */
    public static function init(): void {
        // Inject parent entry data (Controlling Factor, Application Date)
        add_action( 'wp_head', [ __CLASS__, 'inject_parent_entry_data' ], 1 );

        // Clear localStorage on dashboard load
        add_action( 'wp_head', [ __CLASS__, 'clear_toc_variables' ], 2 );

        // Add/Edit page validations (inline script)
        add_action( 'wp_head', [ __CLASS__, 'add_edit_validations' ], 20 );

        // AJAX handler for checking if TOC entries exist
        add_action( 'wp_ajax_check_toc_entries_exist', [ __CLASS__, 'ajax_check_entries_exist' ] );
        add_action( 'wp_ajax_nopriv_check_toc_entries_exist', [ __CLASS__, 'ajax_check_entries_exist' ] );
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
     * Get the current GravityView ID if available
     *
     * @return int|null
     */
    private static function get_gravityview_id(): ?int {
        if ( function_exists( 'gravityview' ) ) {
            $current_view = gravityview()->views->get();
            return $current_view ? $current_view->ID : null;
        }
        
        if ( function_exists( 'gravityview_get_view_id' ) ) {
            return gravityview_get_view_id( get_the_ID() );
        }
        
        return null;
    }

    /**
     * Inject parent entry data into JavaScript
     *
     * Sets window.parentEntryResRequired (Controlling Factor) and
     * window.parentEntryApplicationDate for JavaScript calculations.
     */
    public static function inject_parent_entry_data(): void {
        if ( ! self::is_toc_page() ) {
            return;
        }

        global $wpdb;

        // Try to get parent_entry_id from multiple sources
        $parent_entry_id = self::get_parent_entry_id();

        $res_required_value = null;
        $application_date = null;

        if ( $parent_entry_id ) {
            // Get Controlling Factor (field 894) from Master Form entry
            $res_required_value = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %s",
                intval( $parent_entry_id ),
                self::FIELD_CONTROLLING_FACTOR
            ) );

            // Get Application Date (field 895) from Master Form entry
            $application_date = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %s",
                intval( $parent_entry_id ),
                self::FIELD_APPLICATION_DATE
            ) );
        }

        // Output to JavaScript
        ?>
        <script>
        window.parentEntryResRequired = <?php echo wp_json_encode( $res_required_value ); ?>;
        window.parentEntryApplicationDate = <?php echo wp_json_encode( $application_date ); ?>;
        console.log("NME TOC: Parent entry ID: <?php echo esc_js( $parent_entry_id ); ?>", 
                    "Res required value: <?php echo esc_js( $res_required_value ); ?>", 
                    "Application Date: <?php echo esc_js( $application_date ); ?>");
        </script>
        <?php
    }

    /**
     * Get parent entry ID from various sources
     *
     * @return int|null
     */
    private static function get_parent_entry_id(): ?int {
        global $wpdb;

        // 1. Try URL parameter
        if ( isset( $_GET['parent_entry_id'] ) ) {
            return intval( $_GET['parent_entry_id'] );
        }

        // 2. Try user meta (primary source for logged-in users)
        $user_id = get_current_user_id();
        if ( $user_id ) {
            $parent_id = get_user_meta( $user_id, 'parent_entry_id', true );
            if ( $parent_id ) {
                return intval( $parent_id );
            }
        }

        // 3. Try current entry's meta (for edit pages with entry_id in URL)
        if ( isset( $_GET['entry_id'] ) ) {
            $current_entry_id = intval( $_GET['entry_id'] );
            $parent_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
                 WHERE entry_id = %d AND meta_key = %s",
                $current_entry_id,
                self::FIELD_CONTROLLING_FACTOR
            ) );
            if ( $parent_id ) {
                return intval( $parent_id );
            }
        }

        return null;
    }

    /**
     * Clear localStorage variables on dashboard page load
     *
     * Prevents stale boundary dates from affecting new operations.
     * Only clears on dashboard page, NOT when editing (gvid=581).
     */
    public static function clear_toc_variables(): void {
        // Only on dashboard page 706, and NOT when in edit mode
        if ( ! is_page( self::PAGE_DASHBOARD ) ) {
            return;
        }

        // Don't clear if we're in GravityView edit mode
        if ( isset( $_GET['gvid'] ) && intval( $_GET['gvid'] ) === self::GRAVITYVIEW_EDIT_ID ) {
            return;
        }

        ?>
        <script>
        localStorage.removeItem('previousTripDeparture');
        localStorage.removeItem('nextTripReturn');
        console.log('NME TOC: Cleared localStorage variables on dashboard load');
        </script>
        <?php
    }

    /**
     * Add inline validation script for add/edit pages
     *
     * This provides the inline JavaScript that initializes validation
     * based on whether we're on add page (582) or edit page (GV 581).
     */
    public static function add_edit_validations(): void {
        $gravityview_id = self::get_gravityview_id();

        $is_add_page = is_page( self::PAGE_ADD );
        $is_edit_page = ( $gravityview_id === self::GRAVITYVIEW_EDIT_ID );

        if ( ! $is_add_page && ! $is_edit_page ) {
            return;
        }

        ?>
        <script>
        jQuery(document).ready(function($) {
            // Store page context for validation scripts
            var isAddPage = <?php echo $is_add_page ? 'true' : 'false'; ?>;
            var isEditPage = <?php echo $is_edit_page ? 'true' : 'false'; ?>;

            // Retrieve stored boundary dates from localStorage (for edit pages)
            var previousTripDepartureStr = localStorage.getItem('previousTripDeparture');
            var nextTripReturnStr = localStorage.getItem('nextTripReturn');

            console.log("NME TOC Validation: Retrieved boundary dates from localStorage:");
            console.log("Previous Trip Departure:", previousTripDepartureStr);
            console.log("Next Trip Return:", nextTripReturnStr);

            // Initialize TOC validation module if available
            if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCValidation) {
                window.NMEApp.TOCValidation.init(isAddPage, isEditPage);
            }

            // ================================================================
            // Inline validation functions (fallback if module not loaded)
            // ================================================================

            function parseDate(dateStr) {
                if (!dateStr) return null;
                // Handle YYYY-MM-DD format
                if (dateStr.includes('-')) {
                    const parts = dateStr.split('-');
                    if (parts.length === 3) {
                        return new Date(parts[0], parts[1] - 1, parts[2]);
                    }
                }
                // Handle MM/DD/YYYY format
                const parts = dateStr.split('/');
                if (parts.length !== 3) return null;
                return new Date(parts[2], parts[0] - 1, parts[1]);
            }

            function formatDate(date) {
                return (date.getMonth() + 1) + '/' + date.getDate() + '/' + date.getFullYear();
            }

            function isMoreThanSixMonths(dateFrom, dateTo) {
                let sixMonthsDate = new Date(dateFrom);
                sixMonthsDate.setMonth(sixMonthsDate.getMonth() + 6);
                let sixMonthsAndOneDay = new Date(sixMonthsDate);
                sixMonthsAndOneDay.setDate(sixMonthsAndOneDay.getDate() + 1);
                return dateTo >= sixMonthsAndOneDay;
            }

            function checkDateDifference() {
                const departureDate = parseDate($('#input_42_5').val());
                const returnDate = parseDate($('#input_42_6').val());

                if (departureDate && returnDate) {
                    if (isMoreThanSixMonths(departureDate, returnDate)) {
                        window.alert('You have entered a trip greater than 6 months; confirm your entries are correct by selecting "ok" or edit the entries now. Be advised that when you click "Finish" on the dashboard, the system will provide you the correct date on or after which you will be permitted to file.');
                    }
                }
            }

            function checkDateOverlapWithField11() {
                if (!isAddPage) return;

                const field11Date = parseDate($('#input_42_11').val());
                const departureDate = parseDate($('#input_42_5').val());
                const returnDate = parseDate($('#input_42_6').val());

                if (field11Date) {
                    const boundaryDate = new Date(field11Date);
                    boundaryDate.setDate(boundaryDate.getDate() - 1);

                    if (departureDate && departureDate > boundaryDate) {
                        window.alert("Departure date cannot be later than " + formatDate(boundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_5').val('');
                        return;
                    }

                    if (returnDate && returnDate > boundaryDate) {
                        window.alert("Return date cannot be later than " + formatDate(boundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_6').val('');
                        return;
                    }
                }
            }

            function checkBoundaryDates() {
                if (!isEditPage) return;

                const departureDate = parseDate($('#input_42_5').val());
                const returnDate = parseDate($('#input_42_6').val());
                const prevTripDeparture = parseDate(previousTripDepartureStr);
                const nextTripReturn = parseDate(nextTripReturnStr);

                // Check against previous trip departure date
                if (prevTripDeparture && departureDate) {
                    const prevBoundaryDate = new Date(prevTripDeparture);
                    prevBoundaryDate.setDate(prevBoundaryDate.getDate() - 1);

                    if (departureDate > prevBoundaryDate) {
                        window.alert("Departure date cannot be later than " + formatDate(prevBoundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_5').val('');
                        return;
                    }

                    if (returnDate > prevBoundaryDate) {
                        window.alert("Return date cannot be later than " + formatDate(prevBoundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_6').val('');
                        return;
                    }
                }

                // Check against next trip return date
                if (nextTripReturn && departureDate) {
                    const nextBoundaryDate = new Date(nextTripReturn);
                    nextBoundaryDate.setDate(nextBoundaryDate.getDate() + 1);

                    if (departureDate < nextBoundaryDate) {
                        window.alert("Departure date cannot be earlier than " + formatDate(nextBoundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_5').val('');
                        return;
                    }

                    if (returnDate < nextBoundaryDate) {
                        window.alert("Return date cannot be earlier than " + formatDate(nextBoundaryDate) + ". You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.");
                        $('#input_42_6').val('');
                        return;
                    }
                }
            }

            // Add event listeners to date fields
            $('#input_42_5, #input_42_6').on('change', function() {
                checkDateDifference();

                if (isAddPage) {
                    checkDateOverlapWithField11();
                } else if (isEditPage) {
                    checkBoundaryDates();
                }
            });

            // Add event listener to field 11 for add page
            if (isAddPage) {
                $('#input_42_11').on('change', function() {
                    if ($('#input_42_5').val() || $('#input_42_6').val()) {
                        checkDateOverlapWithField11();
                    }
                });
            }

            // Check on page load in case dates are pre-filled
            setTimeout(function() {
                if ($('#input_42_5').val() && $('#input_42_6').val()) {
                    checkDateDifference();

                    if (isAddPage) {
                        checkDateOverlapWithField11();
                    } else if (isEditPage) {
                        checkBoundaryDates();
                    }
                }
            }, 500);
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to check if TOC entries exist for a user
     *
     * Used by skip button injector to determine whether to show
     * "Skip to Residences" or "Cancel" button.
     */
    public static function ajax_check_entries_exist(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nme-toc-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
            return;
        }

        global $wpdb;

        $anumber = isset( $_POST['anumber'] ) ? sanitize_text_field( $_POST['anumber'] ) : '';

        if ( empty( $anumber ) ) {
            wp_send_json_error( [ 'message' => 'No A-number provided' ] );
            return;
        }

        // Count Form 42 entries for this A-number (field 4 = A-Number)
        $entry_count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*)
            FROM {$wpdb->prefix}gf_entry e
            INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
            WHERE e.form_id = %d 
            AND e.status = 'active'
            AND em.meta_key = '4'
            AND em.meta_value = %s",
            self::FORM_ID,
            $anumber
        ) );

        $has_entries = ( $entry_count > 0 );

        wp_send_json_success( [
            'has_entries' => $has_entries,
            'entry_count' => intval( $entry_count ),
        ] );
    }
}
