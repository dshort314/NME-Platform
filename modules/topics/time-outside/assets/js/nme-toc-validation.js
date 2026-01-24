/**
 * NME Application - TOC (Time Outside Country) Validation Module
 *
 * This module handles TOC validation including:
 * - Trip duration checks (6+ month warning)
 * - Boundary date validation (for edit pages)
 * - Overlap detection with field 11 (for add pages)
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
                window.alert('You have entered a trip greater than 6 months; confirm your entries are correct by selecting "ok" or edit the entries now. Be advised that when you click "Finish" on the dashboard, the system will provide you the correct date on or after which you will be permitted to file.');
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
                window.alert('Departure date cannot be later than ' + formattedBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_5').val('');
                return;
            }

            // Check return date
            if (returnDate && returnDate > boundaryDate) {
                window.alert('Return date cannot be later than ' + formattedBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_6').val('');
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
                window.alert('Departure date cannot be later than ' + formattedPrevBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_5').val('');
                return;
            }

            if (returnDate > prevBoundaryDate) {
                window.alert('Return date cannot be later than ' + formattedPrevBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_6').val('');
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
                window.alert('Departure date cannot be earlier than ' + formattedNextBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_5').val('');
                return;
            }

            if (returnDate < nextBoundaryDate) {
                window.alert('Return date cannot be earlier than ' + formattedNextBoundary + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.');
                $('#input_42_6').val('');
                return;
            }
        }
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
        }

        if (isEditPage) {
            window.NMEApp.TOCValidation.initEditPageValidation();
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
