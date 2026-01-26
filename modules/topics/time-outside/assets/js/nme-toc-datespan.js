/**
 * NME TOC DateSpan Display - Pages 582 & 706
 * 
 * Displays the correct time period message based on:
 * - Residence requirement (DM/SC = 3 years, LPR/LPRM/LPRS = 5 years)
 * - Application Date (eligibility filing date from Master Form field 895)
 *
 * The lookback period is calculated FROM the Application Date, not from today.
 *
 * @package NME\Topics\TimeOutside
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var rr = window.parentEntryResRequired ? window.parentEntryResRequired.toString().trim() : null;
        var applicationDateStr = window.parentEntryApplicationDate ? window.parentEntryApplicationDate.toString().trim() : null;

        console.log('NME TOC DateSpan: Residence Requirement:', rr, 'Application Date:', applicationDateStr);

        // Parse the application date (expected format: MM/DD/YYYY or YYYY-MM-DD)
        var applicationDate = null;

        if (applicationDateStr) {
            if (applicationDateStr.indexOf('/') !== -1) {
                // MM/DD/YYYY format
                var parts = applicationDateStr.split('/');
                if (parts.length === 3) {
                    applicationDate = new Date(parts[2], parts[0] - 1, parts[1]);
                }
            } else if (applicationDateStr.indexOf('-') !== -1) {
                // YYYY-MM-DD format
                var parts = applicationDateStr.split('-');
                if (parts.length === 3) {
                    applicationDate = new Date(parts[0], parts[1] - 1, parts[2]);
                }
            }
        }

        // If no valid application date, fall back to today (shouldn't happen in normal use)
        if (!applicationDate || isNaN(applicationDate.getTime())) {
            console.warn('NME TOC DateSpan: No valid Application Date found, falling back to today');
            applicationDate = new Date();
        }

        // Calculate the lookback period based on residence requirement
        // The lookback period is calculated FROM the Application Date (eligibility date), not today
        var startDate = new Date(applicationDate);

        if (rr === 'DM' || rr === 'SC') {
            // 3-year lookback for marriage-based or special categories
            startDate.setFullYear(startDate.getFullYear() - 3);
        } else if (rr === 'LPRM' || rr === 'LPRS' || rr === 'LPR') {
            // 5-year lookback for standard LPR categories
            startDate.setFullYear(startDate.getFullYear() - 5);
        }

        var formattedStartDate = (startDate.getMonth() + 1) + '/' + startDate.getDate() + '/' + startDate.getFullYear();
        var formattedApplicationDate = (applicationDate.getMonth() + 1) + '/' + applicationDate.getDate() + '/' + applicationDate.getFullYear();

        console.log('NME TOC DateSpan: Lookback start date:', formattedStartDate, 'Application/Filing date:', formattedApplicationDate);

        // Expose lookback start date to window for validation scripts
        window.tocLookbackStartDate = startDate;
        window.tocLookbackStartDateFormatted = formattedStartDate;

        // Update the dateSpan element with the message
        var msg = 'Please list all time outside the United States from ' + formattedStartDate + ' until today. Start with your most recent trip and work backwards. Do not include day trips (where the entire trip was completed within 24 hours).';

        var span = document.getElementById('dateSpan');
        if (span) {
            span.textContent = msg;
        }
    });

})();