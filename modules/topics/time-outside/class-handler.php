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

        // AJAX handler for setting "No Trips" flag and getting redirect URL
        add_action( 'wp_ajax_set_no_trips', [ __CLASS__, 'ajax_set_no_trips' ] );

        // AJAX handler for getting Residences redirect URL (after Finish evaluation)
        add_action( 'wp_ajax_get_residences_redirect', [ __CLASS__, 'ajax_get_residences_redirect' ] );

        // Clear "No Trips" flag when a trip is submitted
        add_action( 'gform_entry_created', [ __CLASS__, 'clear_no_trips_on_submission' ], 10, 2 );
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
        window.nmeTocPageType = <?php echo is_page( self::PAGE_DASHBOARD ) ? "'dashboard'" : "'add'"; ?>;
        console.log("NME TOC: Parent entry ID: <?php echo esc_js( $parent_entry_id ); ?>", 
                    "Res required value: <?php echo esc_js( $res_required_value ); ?>", 
                    "Application Date: <?php echo esc_js( $application_date ); ?>",
                    "Page type: <?php echo is_page( self::PAGE_DASHBOARD ) ? 'dashboard' : 'add'; ?>");
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
        console.log('NME TOC: Cleared boundary date variables on dashboard load');
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
        // Edit page is GV 581 but NOT on dashboard page 706
        $is_edit_page = ( $gravityview_id === self::GRAVITYVIEW_EDIT_ID ) && ! is_page( self::PAGE_DASHBOARD );

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
            // Previous Trips Table Display (fallback for add page)
            // ================================================================

            function displayPreviousTripsTable() {
                if (!isAddPage) return;

                // Get all trips from localStorage
                var allTripsJson = localStorage.getItem('allTocTrips');
                var allTrips = [];

                if (allTripsJson) {
                    try {
                        allTrips = JSON.parse(allTripsJson);
                    } catch (e) {
                        console.log('NME TOC Validation: Could not parse allTocTrips');
                    }
                }

                // Fallback to single preceding trip
                if (!allTrips || allTrips.length === 0) {
                    var departure = localStorage.getItem('precedingTripDeparture');
                    var returnDate = localStorage.getItem('precedingTripReturn');
                    var destination = localStorage.getItem('precedingTripDestination');

                    if (departure || returnDate || destination) {
                        allTrips = [{
                            departure: departure || '',
                            return: returnDate || '',
                            destination: destination || ''
                        }];
                    }
                }

                if (!allTrips || allTrips.length === 0) return;
                if ($('#previous-trips-list').length) return;

                // Build multi-line list
                var html = '<div id="previous-trips-list" style="margin: 12px 0; font-size: 13px; color: #555;">' +
                    '<strong>Previous Trips:</strong><br>';

                for (var i = 0; i < allTrips.length; i++) {
                    var trip = allTrips[i];
                    html += trip.departure + ' – ' + trip.return + ' | ' + trip.destination + '<br>';
                }

                html += '</div>';

                var dateSpan = $('#dateSpan');
                if (dateSpan.length) {
                    dateSpan.after(html);
                }
            }

            // ================================================================
            // Preceding Trip Display (fallback for edit page)
            // ================================================================

            function displayPrecedingTrip() {
                if (!isEditPage) return;

                var departure = localStorage.getItem('precedingTripDeparture');
                var returnDate = localStorage.getItem('precedingTripReturn');
                var destination = localStorage.getItem('precedingTripDestination');

                // Only display if there's preceding trip data
                if (!departure && !returnDate && !destination) {
                    return;
                }

                // Check if display already exists
                if ($('#preceding-trip-info').length) {
                    return;
                }

                // Build the display HTML
                var html = '<div id="preceding-trip-info" style="' +
                    'display: block; ' +
                    'padding: 15px; ' +
                    'margin: 15px 0; ' +
                    'background-color: #f0f4ff; ' +
                    'border-left: 4px solid #0073aa; ' +
                    'border-radius: 4px; ' +
                    'font-size: 14px; ' +
                    'line-height: 1.5; ' +
                    'color: #333;' +
                    '">' +
                    '<strong>Previous Trip:</strong><br>';

                if (departure) {
                    html += 'Departure: ' + departure + '<br>';
                }
                if (returnDate) {
                    html += 'Return: ' + returnDate + '<br>';
                }
                if (destination) {
                    html += 'Destination: ' + destination;
                }

                html += '</div>';

                // Insert after dateSpan
                var dateSpan = $('#dateSpan');
                if (dateSpan.length) {
                    dateSpan.after(html);
                } else {
                    // Fallback: prepend to form
                    var form = $('#gform_42, .gv-edit-entry-wrapper');
                    if (form.length) {
                        form.prepend(html);
                    }
                }
            }

            // Display trips if validation module didn't already
            if (typeof window.NMEApp === 'undefined' || !window.NMEApp.TOCValidation) {
                if (isAddPage) {
                    displayPreviousTripsTable();
                } else if (isEditPage) {
                    displayPrecedingTrip();
                }
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
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showSixMonthWarning();
                        } else {
                            NMEModal.warning({
                                title: 'Long Trip Detected',
                                message: 'You have entered a trip greater than 6 months; confirm your entries are correct by selecting "OK" or edit the entries now. Be advised that when you click "Finish" on the dashboard, the system will provide you the correct date on or after which you will be permitted to file.',
                                buttonText: 'OK'
                            });
                        }
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
                    const formattedBoundary = formatDate(boundaryDate);

                    if (departureDate && departureDate > boundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showDepartureTooLate(formattedBoundary, function() {
                                $('#input_42_5').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Departure Date',
                                message: 'Departure date cannot be later than ' + formattedBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_5').val('');
                                }
                            });
                        }
                        return;
                    }

                    if (returnDate && returnDate > boundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showReturnTooLate(formattedBoundary, function() {
                                $('#input_42_6').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Return Date',
                                message: 'Return date cannot be later than ' + formattedBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_6').val('');
                                }
                            });
                        }
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
                    const formattedPrevBoundary = formatDate(prevBoundaryDate);

                    if (departureDate > prevBoundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showDepartureTooLate(formattedPrevBoundary, function() {
                                $('#input_42_5').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Departure Date',
                                message: 'Departure date cannot be later than ' + formattedPrevBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_5').val('');
                                }
                            });
                        }
                        return;
                    }

                    if (returnDate > prevBoundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showReturnTooLate(formattedPrevBoundary, function() {
                                $('#input_42_6').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Return Date',
                                message: 'Return date cannot be later than ' + formattedPrevBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_6').val('');
                                }
                            });
                        }
                        return;
                    }
                }

                // Check against next trip return date
                if (nextTripReturn && departureDate) {
                    const nextBoundaryDate = new Date(nextTripReturn);
                    nextBoundaryDate.setDate(nextBoundaryDate.getDate() + 1);
                    const formattedNextBoundary = formatDate(nextBoundaryDate);

                    if (departureDate < nextBoundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showDepartureTooEarly(formattedNextBoundary, function() {
                                $('#input_42_5').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Departure Date',
                                message: 'Departure date cannot be earlier than ' + formattedNextBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_5').val('');
                                }
                            });
                        }
                        return;
                    }

                    if (returnDate < nextBoundaryDate) {
                        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                            window.NMEApp.TOCAlerts.showReturnTooEarly(formattedNextBoundary, function() {
                                $('#input_42_6').val('');
                            });
                        } else {
                            NMEModal.warning({
                                title: 'Invalid Return Date',
                                message: 'Return date cannot be earlier than ' + formattedNextBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
                                buttonText: 'OK',
                                onClose: function() {
                                    $('#input_42_6').val('');
                                }
                            });
                        }
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

            // ================================================================
            // Lookback Period Validation (Form Submit)
            // ================================================================

            function checkReturnDateWithinLookback() {
                var returnDateStr = $('#input_42_6').val();
                var returnDate = parseDate(returnDateStr);
                var lookbackDate = window.tocLookbackStartDate;

                // If no lookback date set, allow submission
                if (!lookbackDate || !(lookbackDate instanceof Date) || isNaN(lookbackDate.getTime())) {
                    return true;
                }

                // If no return date entered, allow form validation to handle it
                if (!returnDate) {
                    return true;
                }

                // Compare: return date must be on or after lookback start date
                var returnDateNorm = new Date(returnDate.getFullYear(), returnDate.getMonth(), returnDate.getDate());
                var lookbackDateNorm = new Date(lookbackDate.getFullYear(), lookbackDate.getMonth(), lookbackDate.getDate());

                if (returnDateNorm < lookbackDateNorm) {
                    var formattedLookback = window.tocLookbackStartDateFormatted || formatDate(lookbackDate);
                    
                    if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
                        window.NMEApp.TOCAlerts.showTripBeforeLookbackDate(formattedLookback, function() {
                            window.location.href = '/application/time-outside-the-us-view/';
                        });
                    } else {
                        NMEModal.warning({
                            title: 'Trip Outside Filing Period',
                            message: 'Trips that end before ' + formattedLookback + ' should not be listed. Only trips with a return date on or after ' + formattedLookback + ' are relevant to your naturalization application.',
                            buttonText: 'OK',
                            onClose: function() {
                                window.location.href = '/application/time-outside-the-us-view/';
                            }
                        });
                    }
                    
                    return false;
                }

                return true;
            }

            // Hook into form submit
            $('#gform_42').on('submit', function(e) {
                if (!checkReturnDateWithinLookback()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });

            // Hook into GravityView edit form submit
            $(document).on('click', '.gv-button-update', function(e) {
                if (!checkReturnDateWithinLookback()) {
                    e.preventDefault();
                    e.stopPropagation();
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler to check if TOC entries exist for a user
     *
     * Used by skip button injector to determine whether to show
     * "Skip to Residences" or "Back" button.
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

    /**
     * AJAX handler to set "No Trips" flag and get redirect URL for Residences
     *
     * Updates Master Form 75 field 936 to "No Trips" and returns
     * the appropriate redirect URL for the Residences add page.
     */
    public static function ajax_set_no_trips(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nme-toc-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
            return;
        }

        global $wpdb;

        $anumber = isset( $_POST['anumber'] ) ? sanitize_text_field( $_POST['anumber'] ) : '';
        $parent_entry_id = isset( $_POST['parent_entry_id'] ) ? intval( $_POST['parent_entry_id'] ) : 0;

        if ( empty( $anumber ) || empty( $parent_entry_id ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters' ] );
            return;
        }

        // Update Master Form 75 field 936 to "No Trips"
        $update_result = \GFAPI::update_entry_field( $parent_entry_id, '936', 'No Trips' );

        if ( is_wp_error( $update_result ) ) {
            wp_send_json_error( [ 'message' => 'Failed to update Master Form: ' . $update_result->get_error_message() ] );
            return;
        }

        // Check for existing Residence entries (Form 38) by A-Number (field 1)
        $residence_form_id = 38;
        $residence_anumber_field = '1';
        $residence_end_date_field = '4';

        // Get count and latest entry for Residences
        $residence_entries = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, em_date.meta_value as end_date
            FROM {$wpdb->prefix}gf_entry e
            INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
            LEFT JOIN {$wpdb->prefix}gf_entry_meta em_date ON e.id = em_date.entry_id AND em_date.meta_key = %s
            WHERE e.form_id = %d 
            AND e.status = 'active'
            AND em.meta_key = %s
            AND em.meta_value = %s
            ORDER BY e.id DESC",
            $residence_end_date_field,
            $residence_form_id,
            $residence_anumber_field,
            $anumber
        ) );

        $sequence = 1;
        $end_date = date( 'm/d/Y' ); // Current date as default

        if ( ! empty( $residence_entries ) ) {
            $sequence = count( $residence_entries ) + 1;
            
            // Get end date from most recent entry
            $latest_entry = $residence_entries[0];
            if ( ! empty( $latest_entry->end_date ) ) {
                $end_date = $latest_entry->end_date;
            }
        }

        // Build redirect URL
        $redirect_url = add_query_arg( [
            'sequence'        => $sequence,
            'anumber'         => $anumber,
            'parent_entry_id' => $parent_entry_id,
            'end-date'        => $end_date,
        ], '/application/residences/' );

        wp_send_json_success( [
            'redirect_url' => $redirect_url,
            'sequence'     => $sequence,
            'end_date'     => $end_date,
        ] );
    }

    /**
     * AJAX handler to get Residences redirect URL
     *
     * Returns the appropriate redirect URL for the Residences add page
     * without modifying any form fields. Used after Finish evaluation.
     */
    public static function ajax_get_residences_redirect(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'nme-toc-ajax-nonce' ) ) {
            wp_send_json_error( [ 'message' => 'Invalid nonce' ] );
            return;
        }

        global $wpdb;

        $anumber = isset( $_POST['anumber'] ) ? sanitize_text_field( $_POST['anumber'] ) : '';
        $parent_entry_id = isset( $_POST['parent_entry_id'] ) ? intval( $_POST['parent_entry_id'] ) : 0;

        if ( empty( $anumber ) || empty( $parent_entry_id ) ) {
            wp_send_json_error( [ 'message' => 'Missing required parameters' ] );
            return;
        }

        // Check for existing Residence entries (Form 38) by A-Number (field 1)
        $residence_form_id = 38;
        $residence_anumber_field = '1';
        $residence_end_date_field = '4';

        // Get count and latest entry for Residences
        $residence_entries = $wpdb->get_results( $wpdb->prepare(
            "SELECT e.id, em_date.meta_value as end_date
            FROM {$wpdb->prefix}gf_entry e
            INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
            LEFT JOIN {$wpdb->prefix}gf_entry_meta em_date ON e.id = em_date.entry_id AND em_date.meta_key = %s
            WHERE e.form_id = %d 
            AND e.status = 'active'
            AND em.meta_key = %s
            AND em.meta_value = %s
            ORDER BY e.id DESC",
            $residence_end_date_field,
            $residence_form_id,
            $residence_anumber_field,
            $anumber
        ) );

        $sequence = 1;
        $end_date = date( 'm/d/Y' ); // Current date as default

        if ( ! empty( $residence_entries ) ) {
            $sequence = count( $residence_entries ) + 1;
            
            // Get end date from most recent entry
            $latest_entry = $residence_entries[0];
            if ( ! empty( $latest_entry->end_date ) ) {
                $end_date = $latest_entry->end_date;
            }
        }

        // Build redirect URL
        $redirect_url = add_query_arg( [
            'sequence'        => $sequence,
            'anumber'         => $anumber,
            'parent_entry_id' => $parent_entry_id,
            'end-date'        => $end_date,
        ], '/application/residences/' );

        wp_send_json_success( [
            'redirect_url' => $redirect_url,
            'sequence'     => $sequence,
            'end_date'     => $end_date,
        ] );
    }

    /**
     * Clear "No Trips" flag when a trip is submitted
     *
     * If a user previously marked "No Trips" but later adds a trip,
     * this clears field 936 on the Master Form (Form 75).
     *
     * @param array|null $entry The submitted entry
     * @param array $form The form object
     */
    public static function clear_no_trips_on_submission( $entry, $form ): void {
        // Entry can be null during form rendering/validation
        if ( empty( $entry ) || ! is_array( $entry ) ) {
            error_log( 'NME TOC: clear_no_trips - Entry is empty or not array' );
            return;
        }

        // Only process Form 42 (TOC form)
        $form_id = rgar( $entry, 'form_id' );
        if ( intval( $form_id ) !== self::FORM_ID ) {
            return;
        }

        error_log( 'NME TOC: clear_no_trips - Processing Form 42 entry' );

        // Ensure GFAPI is available
        if ( ! class_exists( 'GFAPI' ) ) {
            error_log( 'NME TOC: clear_no_trips - GFAPI not available' );
            return;
        }

        // Field 12 contains the parent entry ID (Master Form 75)
        $parent_entry_id = rgar( $entry, '12' );

        if ( empty( $parent_entry_id ) ) {
            error_log( 'NME TOC: clear_no_trips - No parent_entry_id in field 12' );
            return;
        }

        error_log( 'NME TOC: clear_no_trips - Parent entry ID: ' . $parent_entry_id );

        // Get the parent entry
        $parent_entry = \GFAPI::get_entry( intval( $parent_entry_id ) );

        if ( is_wp_error( $parent_entry ) || ! is_array( $parent_entry ) ) {
            error_log( 'NME TOC: clear_no_trips - Could not get parent entry' );
            return;
        }

        // Get current value of field 936
        $current_value = rgar( $parent_entry, '936' );
        error_log( 'NME TOC: clear_no_trips - Field 936 current value: ' . $current_value );

        // Only update if currently set to "No Trips"
        if ( $current_value === 'No Trips' ) {
            $result = \GFAPI::update_entry_field( intval( $parent_entry_id ), '936', '' );
            error_log( 'NME TOC: clear_no_trips - Updated field 936, result: ' . ( $result ? 'success' : 'failed' ) );
        }
    }
}