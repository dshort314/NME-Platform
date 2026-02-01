/**
 * NME Application - TOC (Time Outside Country) Validation Module
 *
 * This module handles TOC validation including:
 * - Trip duration checks (6+ month warning)
 * - Boundary date validation (for edit pages)
 * - Overlap detection with field 11 (for add pages)
 * - Display of all previous trips in table format
 *
 * @package NME\Topics\TimeOutside
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.TOCValidation = {};

    // ================================================================
    // Date Parsing and Formatting
    // ================================================================

    /**
     * Parse date in MM/DD/YYYY format or YYYY-MM-DD format
     * @param {string} dateStr - Date string to parse
     * @return {Date|null} - Parsed date or null if invalid
     */
    window.NMEApp.TOCValidation.parseDate = function(dateStr) {
        if (!dateStr) return null;

        // Handle YYYY-MM-DD format (from date input fields)
        if (dateStr.indexOf('-') !== -1) {
            var parts = dateStr.split('-');
            if (parts.length === 3) {
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }
        }

        // Handle MM/DD/YYYY format
        var parts = dateStr.split('/');
        if (parts.length !== 3) return null;
        // Note: JavaScript months are 0-based, so we subtract 1 from the month
        return new Date(parts[2], parts[0] - 1, parts[1]);
    };

    /**
     * Format date as MM/DD/YYYY
     * @param {Date} date - Date to format
     * @return {string} - Formatted date string
     */
    window.NMEApp.TOCValidation.formatDate = function(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        var year = date.getFullYear();
        return month + '/' + day + '/' + year;
    };

    // ================================================================
    // 6-Month Trip Detection
    // ================================================================

    /**
     * Check if date difference is 6 months and 1 day or more
     * @param {Date} dateFrom - Departure date
     * @param {Date} dateTo - Return date
     * @return {boolean} - True if exceeds 6 months
     */
    window.NMEApp.TOCValidation.isMoreThanSixMonths = function(dateFrom, dateTo) {
        // Add 6 months to departure date
        var sixMonthsDate = new Date(dateFrom);
        sixMonthsDate.setMonth(sixMonthsDate.getMonth() + 6);

        // Add 1 day to get to 6 months and 1 day threshold
        var sixMonthsAndOneDay = new Date(sixMonthsDate);
        sixMonthsAndOneDay.setDate(sixMonthsAndOneDay.getDate() + 1);

        // If return date is on or after this threshold, it exceeds 6 months
        return dateTo >= sixMonthsAndOneDay;
    };

    /**
     * Check date difference and show notification for 6+ month trips
     */
    window.NMEApp.TOCValidation.checkDateDifference = function() {
        if (typeof $ === 'undefined') return;

        var departureDate = window.NMEApp.TOCValidation.parseDate($('#input_42_5').val());
        var returnDate = window.NMEApp.TOCValidation.parseDate($('#input_42_6').val());

        // Only check if both dates are valid
        if (departureDate && returnDate) {
            if (window.NMEApp.TOCValidation.isMoreThanSixMonths(departureDate, returnDate)) {
                window.NMEApp.TOCAlerts.showSixMonthWarning();
            }
        }
    };

    // ================================================================
    // Add Page Validation (Field 11 Overlap)
    // ================================================================

    /**
     * Check for date overlaps with field #input_42_11 when adding an entry
     * Field 11 contains the "Initial Date" (boundary from previous trip)
     */
    window.NMEApp.TOCValidation.checkDateOverlapWithField11 = function() {
        if (typeof $ === 'undefined') return;

        var field11Date = window.NMEApp.TOCValidation.parseDate($('#input_42_11').val());
        var departureDate = window.NMEApp.TOCValidation.parseDate($('#input_42_5').val());
        var returnDate = window.NMEApp.TOCValidation.parseDate($('#input_42_6').val());

        // Only check if field11 has a valid date
        if (field11Date) {
            // Create boundary date (1 day before field11Date)
            var boundaryDate = new Date(field11Date);
            boundaryDate.setDate(boundaryDate.getDate() - 1);

            var formattedBoundary = window.NMEApp.TOCValidation.formatDate(boundaryDate);

            // Check departure date
            if (departureDate && departureDate > boundaryDate) {
                window.NMEApp.TOCAlerts.showDepartureTooLate(formattedBoundary, function() {
                    $('#input_42_5').val('');
                });
                return;
            }

            // Check return date
            if (returnDate && returnDate > boundaryDate) {
                window.NMEApp.TOCAlerts.showReturnTooLate(formattedBoundary, function() {
                    $('#input_42_6').val('');
                });
                return;
            }
        }
    };

    // ================================================================
    // Edit Page Validation (Boundary Dates)
    // ================================================================

    /**
     * Check boundary dates when editing an entry
     * Uses localStorage values set by dashboard when clicking edit link
     */
    window.NMEApp.TOCValidation.checkBoundaryDates = function() {
        if (typeof $ === 'undefined') return;

        var departureDate = window.NMEApp.TOCValidation.parseDate($('#input_42_5').val());
        var returnDate = window.NMEApp.TOCValidation.parseDate($('#input_42_6').val());

        // Get boundary dates from localStorage (set by dashboard script)
        var previousTripDepartureStr = localStorage.getItem('previousTripDeparture');
        var nextTripReturnStr = localStorage.getItem('nextTripReturn');

        var prevTripDeparture = window.NMEApp.TOCValidation.parseDate(previousTripDepartureStr);
        var nextTripReturn = window.NMEApp.TOCValidation.parseDate(nextTripReturnStr);

        // Check against previous trip departure date (upper boundary)
        if (prevTripDeparture && departureDate) {
            // Create boundary date (1 day before prevTripDeparture)
            var prevBoundaryDate = new Date(prevTripDeparture);
            prevBoundaryDate.setDate(prevBoundaryDate.getDate() - 1);
            var formattedPrevBoundary = window.NMEApp.TOCValidation.formatDate(prevBoundaryDate);

            if (departureDate > prevBoundaryDate) {
                window.NMEApp.TOCAlerts.showDepartureTooLate(formattedPrevBoundary, function() {
                    $('#input_42_5').val('');
                });
                return;
            }

            if (returnDate > prevBoundaryDate) {
                window.NMEApp.TOCAlerts.showReturnTooLate(formattedPrevBoundary, function() {
                    $('#input_42_6').val('');
                });
                return;
            }
        }

        // Check against next trip return date (lower boundary)
        if (nextTripReturn && departureDate) {
            // Create boundary date (1 day after nextTripReturn)
            var nextBoundaryDate = new Date(nextTripReturn);
            nextBoundaryDate.setDate(nextBoundaryDate.getDate() + 1);
            var formattedNextBoundary = window.NMEApp.TOCValidation.formatDate(nextBoundaryDate);

            if (departureDate < nextBoundaryDate) {
                window.NMEApp.TOCAlerts.showDepartureTooEarly(formattedNextBoundary, function() {
                    $('#input_42_5').val('');
                });
                return;
            }

            if (returnDate < nextBoundaryDate) {
                window.NMEApp.TOCAlerts.showReturnTooEarly(formattedNextBoundary, function() {
                    $('#input_42_6').val('');
                });
                return;
            }
        }
    };

    // ================================================================
    // Previous Trips Table Display
    // ================================================================

    /**
     * Display all previous trips in a simple multi-line list below the dateSpan element
     * Only displays on add page (582), not on dashboard (706)
     */
    window.NMEApp.TOCValidation.displayPreviousTripsTable = function() {
        if (typeof $ === 'undefined') return;

        // Only display on add page, not dashboard
        if (window.nmeTocPageType === 'dashboard') {
            console.log('NME TOC Validation: Skipping previous trips display on dashboard');
            return;
        }

        // Get all trips from localStorage
        var allTripsJson = localStorage.getItem('allTocTrips');
        var allTrips = [];

        if (allTripsJson) {
            try {
                allTrips = JSON.parse(allTripsJson);
            } catch (e) {
                console.log('NME TOC Validation: Could not parse allTocTrips from localStorage');
            }
        }

        // If no trips stored in new format, fall back to single preceding trip
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

        // Only display if there are trips
        if (!allTrips || allTrips.length === 0) {
            console.log('NME TOC Validation: No previous trips to display');
            return;
        }

        // Check if display already exists
        if ($('#previous-trips-list').length) {
            return;
        }

        // Build multi-line list
        var html = '<div id="previous-trips-list" style="margin: 12px 0; font-size: 13px; color: #555;">' +
            '<strong>Previous Trips:</strong><br>';

        for (var i = 0; i < allTrips.length; i++) {
            var trip = allTrips[i];
            html += trip.departure + ' – ' + trip.return + ' | ' + trip.destination + '<br>';
        }

        html += '</div>';

        // Insert after dateSpan
        var dateSpan = $('#dateSpan');
        if (dateSpan.length) {
            dateSpan.after(html);
            console.log('NME TOC Validation: Displayed ' + allTrips.length + ' previous trips');
        }
    };

    /**
     * Display preceding trip details below the dateSpan element (legacy - for edit pages)
     */
    window.NMEApp.TOCValidation.displayPrecedingTrip = function() {
        if (typeof $ === 'undefined') return;

        var departure = localStorage.getItem('precedingTripDeparture');
        var returnDate = localStorage.getItem('precedingTripReturn');
        var destination = localStorage.getItem('precedingTripDestination');

        // Only display if there's preceding trip data
        if (!departure && !returnDate && !destination) {
            console.log('NME TOC Validation: No preceding trip data to display');
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
            console.log('NME TOC Validation: Displayed preceding trip info');
        } else {
            // Fallback: prepend to form
            var form = $('#gform_42, .gv-edit-entry-wrapper');
            if (form.length) {
                form.prepend(html);
                console.log('NME TOC Validation: Displayed preceding trip info (fallback location)');
            }
        }
    };

    // ================================================================
    // Lookback Period Validation (Form Submit)
    // ================================================================

    /**
     * Check if return date is before the lookback start date
     * If so, trip should not be entered - redirect to dashboard
     * @return {boolean} - True if valid (return date is on or after lookback), false if invalid
     */
    window.NMEApp.TOCValidation.checkReturnDateWithinLookback = function() {
        if (typeof $ === 'undefined') return true;

        var returnDate = window.NMEApp.TOCValidation.parseDate($('#input_42_6').val());
        var lookbackDate = window.tocLookbackStartDate;

        // If no lookback date set, allow submission
        if (!lookbackDate || !(lookbackDate instanceof Date) || isNaN(lookbackDate.getTime())) {
            console.log('NME TOC Validation: No lookback date available, skipping check');
            return true;
        }

        // If no return date entered, allow form validation to handle it
        if (!returnDate) {
            return true;
        }

        // Compare: return date must be on or after lookback start date
        // Normalize both dates to start of day for comparison
        var returnDateNorm = new Date(returnDate.getFullYear(), returnDate.getMonth(), returnDate.getDate());
        var lookbackDateNorm = new Date(lookbackDate.getFullYear(), lookbackDate.getMonth(), lookbackDate.getDate());

        if (returnDateNorm < lookbackDateNorm) {
            var formattedLookback = window.tocLookbackStartDateFormatted || window.NMEApp.TOCValidation.formatDate(lookbackDate);
            
            window.NMEApp.TOCAlerts.showTripBeforeLookbackDate(formattedLookback, function() {
                // Redirect to dashboard on modal close
                window.location.href = '/application/time-outside-the-us-view/';
            });
            
            return false;
        }

        return true;
    };

    /**
     * Setup form submit validation
     * Intercepts Gravity Forms submit to check lookback date
     */
    window.NMEApp.TOCValidation.setupFormSubmitValidation = function() {
        if (typeof $ === 'undefined') return;

        // Hook into Gravity Forms submit
        $(document).on('submit', '#gform_42', function(e) {
            if (!window.NMEApp.TOCValidation.checkReturnDateWithinLookback()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Also hook into GravityView edit form submit (same form ID)
        $(document).on('click', '.gv-button-update', function(e) {
            if (!window.NMEApp.TOCValidation.checkReturnDateWithinLookback()) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        console.log('NME TOC Validation: Form submit validation configured');
    };

    // ================================================================
    // Initialization
    // ================================================================

    /**
     * Initialize TOC validation for add page
     */
    window.NMEApp.TOCValidation.initAddPageValidation = function() {
        if (typeof $ === 'undefined') {
            console.log('NME TOC Validation: jQuery not available for add page validation');
            return;
        }

        console.log('NME TOC Validation: Setting up add page validation');

        // Add event listeners to date fields
        $('#input_42_5, #input_42_6').on('change', function() {
            window.NMEApp.TOCValidation.checkDateDifference();
            window.NMEApp.TOCValidation.checkDateOverlapWithField11();
        });

        // Add event listener to field 11
        $('#input_42_11').on('change', function() {
            if ($('#input_42_5').val() || $('#input_42_6').val()) {
                window.NMEApp.TOCValidation.checkDateOverlapWithField11();
            }
        });
    };

    /**
     * Initialize TOC validation for edit page
     */
    window.NMEApp.TOCValidation.initEditPageValidation = function() {
        if (typeof $ === 'undefined') {
            console.log('NME TOC Validation: jQuery not available for edit page validation');
            return;
        }

        console.log('NME TOC Validation: Setting up edit page validation');

        // Add event listeners to date fields
        $('#input_42_5, #input_42_6').on('change', function() {
            window.NMEApp.TOCValidation.checkDateDifference();
            window.NMEApp.TOCValidation.checkBoundaryDates();
        });
    };

    /**
     * Initialize TOC validation on page load
     * @param {boolean} isAddPage - Whether this is an add page
     * @param {boolean} isEditPage - Whether this is an edit page
     */
    window.NMEApp.TOCValidation.init = function(isAddPage, isEditPage) {
        // Use default values if parameters not provided
        isAddPage = isAddPage || false;
        isEditPage = isEditPage || false;

        console.log('NME TOC Validation: Initializing', { isAddPage: isAddPage, isEditPage: isEditPage });

        if (isAddPage) {
            window.NMEApp.TOCValidation.initAddPageValidation();
            // Display all previous trips in table format for add page
            window.NMEApp.TOCValidation.displayPreviousTripsTable();
        }

        if (isEditPage) {
            window.NMEApp.TOCValidation.initEditPageValidation();
            // Display single preceding trip for edit page (legacy format)
            window.NMEApp.TOCValidation.displayPrecedingTrip();
        }

        // Setup form submit validation for both add and edit pages
        if (isAddPage || isEditPage) {
            window.NMEApp.TOCValidation.setupFormSubmitValidation();
        }

        // Check on page load in case dates are pre-filled
        setTimeout(function() {
            if (typeof $ !== 'undefined' && $('#input_42_5').val() && $('#input_42_6').val()) {
                window.NMEApp.TOCValidation.checkDateDifference();

                if (isAddPage) {
                    window.NMEApp.TOCValidation.checkDateOverlapWithField11();
                } else if (isEditPage) {
                    window.NMEApp.TOCValidation.checkBoundaryDates();
                }
            }
        }, 500);
    };

    // Expose shorthand reference for convenience
    window.NMETOCVal = window.NMEApp.TOCValidation;

})(window.jQuery, window, document);