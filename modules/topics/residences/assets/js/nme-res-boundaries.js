/**
 * NME Residence Boundaries - Pages 514, 705
 * 
 * Enforces date boundaries between residence entries:
 * - Prevents gaps greater than 30 days between residences
 * - Prevents overlapping residence periods
 * - Uses localStorage to track adjacent entry dates
 *
 * @package NME\Topics\Residences
 */

(function($) {
    'use strict';

    /**
     * Initialize residence boundary validation
     */
    function initResidenceBoundaries() {
        console.log('NME Residence Boundaries: Script loaded');

        // Maximum allowed gap between residences (in days)
        var maxRangeDays = 30;

        // Retrieve stored boundary dates from localStorage
        var previousEntryFromStr = localStorage.getItem('previousEntryFrom');
        var subsequentEntryToStr = localStorage.getItem('subsequentEntryTo');

        console.log('NME Residence Boundaries: Retrieved from localStorage', {
            previousEntryFrom: previousEntryFromStr,
            subsequentEntryTo: subsequentEntryToStr
        });

        // Get the date input fields
        var fromInput = document.getElementById('input_38_3'); // "From" date field
        var toInput = document.getElementById('input_38_4');   // "To" date field

        if (!fromInput || !toInput) {
            console.log('NME Residence Boundaries: Input fields not found');
            return;
        }

        console.log('NME Residence Boundaries: Current values', {
            from: fromInput.value,
            to: toInput.value
        });

        // ============================================================
        // "From" Field Validation (fires on blur)
        // ============================================================
        fromInput.addEventListener('blur', function() {
            console.log('NME Residence Boundaries: From blur triggered:', fromInput.value);
            var fromDate = parseDate(fromInput.value);

            // Validate against the subsequent entry's "To" date, if available
            if (subsequentEntryToStr) {
                var subsequentEntryToDate = parseDate(subsequentEntryToStr);
                var allowedFromMin = addDays(subsequentEntryToDate, 1);
                var allowedFromMax = addDays(subsequentEntryToDate, maxRangeDays);

                // Cannot be earlier than subsequentEntryTo + 1 day (would cause overlap)
                if (fromDate < allowedFromMin) {
                    alert("The 'From' date cannot be earlier than " + formatDate(allowedFromMin) +
                        ". You have attempted to edit the 'From' date in a manner that would cause it to overlap with the previous residence. " +
                        "If you wish to make changes to the dates in this manner, you must Return to the Dashboard, delete any incorrect entries and re-enter them.");
                    fromInput.value = formatDate(allowedFromMin);
                }
                // Cannot be later than subsequentEntryTo + 30 days (would cause too large a gap)
                else if (fromDate > allowedFromMax) {
                    alert("The 'From' date cannot be later than " + formatDate(allowedFromMax) +
                        ". You have attempted to edit the 'From' date in a manner that would cause there to be a gap in residence history greater than 30 days from your previous residence. " +
                        "If you wish to make changes to the dates in this manner, you must Return to the Dashboard, delete any incorrect entries and re-enter them.");
                    fromInput.value = formatDate(allowedFromMax);
                }
            }
        });

        // ============================================================
        // "To" Field Validation (fires on blur)
        // ============================================================
        toInput.addEventListener('blur', function() {
            console.log('NME Residence Boundaries: To blur triggered:', toInput.value);
            var toDate = parseDate(toInput.value);
            var computedToMin = null;
            var computedToMax = null;
            var specialInput = document.getElementById('input_38_12'); // Sequence number

            // Special case for the first residence entry (sequence = 1)
            if (specialInput && specialInput.value.trim() === '1') {
                // First residence entry: "To" date can be up to today
                computedToMax = new Date();
                console.log('NME Residence Boundaries: First residence entry detected');
            }
            // Normal case: Use previous entry from date as reference
            else if (previousEntryFromStr) {
                var previousEntryFromDate = parseDate(previousEntryFromStr);
                // Allow max 30 days gap between residences
                computedToMin = addDays(previousEntryFromDate, -maxRangeDays);
                // Cannot be later than day before next residence starts
                computedToMax = addDays(previousEntryFromDate, -1);
            }
            // Fallback if no reference dates available
            else {
                computedToMin = new Date();
            }

            // Enforce minimum "To" date (cannot have gap > 30 days)
            if (computedToMin && toDate < computedToMin) {
                alert("The 'To' date cannot be earlier than " + formatDate(computedToMin) +
                    ". You have attempted to edit the 'To' date in a manner that would cause there to be a gap in residence history greater than 30 days from your subsequent residence. " +
                    "If you wish to make changes to the dates in this manner, you must Return to the Dashboard, delete any incorrect entries and re-enter them.");
                toInput.value = formatDate(computedToMin);
            }

            // Enforce maximum "To" date (cannot overlap with next residence)
            if (computedToMax && toDate > computedToMax) {
                alert("The 'To' date cannot be later than " + formatDate(computedToMax) +
                    ". You have attempted to edit the 'To' date in a manner that would cause it to overlap with the subsequent residence. " +
                    "If you wish to make changes to the dates in this manner, you must Return to the Dashboard, delete any incorrect entries and re-enter them.");
                toInput.value = formatDate(computedToMax);
            }

            console.log('NME Residence Boundaries: To validation complete:', toInput.value);
        });

        // Delegated blur listener (using capture since blur doesn't bubble)
        document.addEventListener('blur', function(e) {
            if (e.target.id === 'input_38_3') {
                console.log('NME Residence Boundaries: Delegated From blur:', e.target.value);
            }
            if (e.target.id === 'input_38_4') {
                console.log('NME Residence Boundaries: Delegated To blur:', e.target.value);
            }
        }, true);
    }

    // ============================================================
    // Helper Functions
    // ============================================================

    /**
     * Parse a date string in different formats
     * Handles both "YYYY-MM-DD" and "MM/DD/YYYY" formats
     * @param {string} str - Date string to parse
     * @return {Date} - JavaScript Date object
     */
    function parseDate(str) {
        if (!str) return null;
        
        // Try to parse "YYYY-MM-DD" (native datepicker) first
        var parts = str.split('-');
        if (parts.length === 3) {
            return new Date(parts[0], parts[1] - 1, parts[2]);
        }
        // Fallback for "MM/DD/YYYY"
        parts = str.split('/');
        if (parts.length === 3) {
            return new Date(parts[2], parts[0] - 1, parts[1]);
        }
        return new Date(str);
    }

    /**
     * Format a Date object as "MM/DD/YYYY"
     * @param {Date} date - Date to format
     * @return {string} - Formatted date string
     */
    function formatDate(date) {
        var mm = date.getMonth() + 1;
        var dd = date.getDate();
        var yyyy = date.getFullYear();
        return (mm < 10 ? '0' + mm : mm) + '/' + (dd < 10 ? '0' + dd : dd) + '/' + yyyy;
    }

    /**
     * Add days to a date
     * @param {Date} date - Original date
     * @param {number} days - Number of days to add (can be negative)
     * @return {Date} - New date object
     */
    function addDays(date, days) {
        var result = new Date(date);
        result.setDate(result.getDate() + days);
        return result;
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initResidenceBoundaries();
    });

})(jQuery);
