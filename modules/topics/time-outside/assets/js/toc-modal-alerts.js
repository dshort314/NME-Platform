/**
 * NME Application - TOC Modal Alerts Module
 * 
 * This module contains TOC-specific modal messages and callbacks.
 * All modals use the global NMEModal system for consistent styling.
 *
 * @package NME\Topics\TimeOutside
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.TOCAlerts = {};

    // ================================================================
    // Validation Alerts
    // ================================================================

    /**
     * Show 6+ month trip warning
     */
    window.NMEApp.TOCAlerts.showSixMonthWarning = function() {
        NMEModal.warning({
            title: 'Long Trip Detected',
            message: 'You have entered a trip greater than 6 months; confirm your entries are correct by selecting "OK" or edit the entries now. Be advised that when you click "Finish" on the dashboard, the system will provide you the correct date on or after which you will be permitted to file.',
            buttonText: 'OK'
        });
    };

    /**
     * Show departure date too late error
     * @param {string} boundaryDate - Formatted boundary date string
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showDepartureTooLate = function(boundaryDate, onClose) {
        NMEModal.warning({
            title: 'Invalid Departure Date',
            message: 'Departure date cannot be later than ' + boundaryDate + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
            buttonText: 'OK',
            onClose: onClose
        });
    };

    /**
     * Show return date too late error
     * @param {string} boundaryDate - Formatted boundary date string
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showReturnTooLate = function(boundaryDate, onClose) {
        NMEModal.warning({
            title: 'Invalid Return Date',
            message: 'Return date cannot be later than ' + boundaryDate + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
            buttonText: 'OK',
            onClose: onClose
        });
    };

    /**
     * Show departure date too early error
     * @param {string} boundaryDate - Formatted boundary date string
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showDepartureTooEarly = function(boundaryDate, onClose) {
        NMEModal.warning({
            title: 'Invalid Departure Date',
            message: 'Departure date cannot be earlier than ' + boundaryDate + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
            buttonText: 'OK',
            onClose: onClose
        });
    };

    /**
     * Show return date too early error
     * @param {string} boundaryDate - Formatted boundary date string
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showReturnTooEarly = function(boundaryDate, onClose) {
        NMEModal.warning({
            title: 'Invalid Return Date',
            message: 'Return date cannot be earlier than ' + boundaryDate + '. You must enter your trips from latest to earliest. Revise the date or delete the previous trip in order to enter this trip prior to the one entered above.',
            buttonText: 'OK',
            onClose: onClose
        });
    };

    /**
     * Show trip before lookback period error
     * @param {string} lookbackDate - Formatted lookback start date string
     * @param {function} onClose - Optional callback when modal closes (redirects to dashboard)
     */
    window.NMEApp.TOCAlerts.showTripBeforeLookbackDate = function(lookbackDate, onClose) {
        NMEModal.warning({
            title: 'Trip Outside Filing Period',
            message: 'Trips that end before ' + lookbackDate + ' should not be listed. Only trips with a return date on or after ' + lookbackDate + ' are relevant to your naturalization application.',
            buttonText: 'OK',
            onClose: onClose
        });
    };

    // ================================================================
    // Finish/Evaluation Alerts
    // ================================================================

    /**
     * Show evaluation error modal (red) - issues found
     * @param {string} html - HTML content for the message
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showEvaluationError = function(html, onClose) {
        NMEModal.error({
            title: 'Trip Evaluation Issues',
            message: html,
            buttonText: 'OK',
            onClose: onClose
        });
    };

    /**
     * Show evaluation success modal (green) - no issues
     * @param {string} html - HTML content for the message
     * @param {function} onClose - Optional callback when modal closes
     */
    window.NMEApp.TOCAlerts.showEvaluationSuccess = function(html, onClose) {
        NMEModal.success({
            title: 'Review of Trip(s) Complete',
            message: html,
            buttonText: 'OK',
            onClose: onClose
        });
    };

    // ================================================================
    // Dashboard Alerts
    // ================================================================

    /**
     * Show delete confirmation for cascade delete (not last entry)
     * @param {function} onConfirm - Callback when confirmed
     * @param {function} onCancel - Callback when cancelled
     */
    window.NMEApp.TOCAlerts.showCascadeDeleteConfirm = function(onConfirm, onCancel) {
        NMEModal.confirm({
            title: 'Delete Trip',
            message: 'Your deletion of this trip will require you to re-enter any previous trips, if any. Do you wish to continue?',
            confirmText: 'OK',
            cancelText: 'Cancel',
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    };

    /**
     * Show delete confirmation for single delete (last entry)
     * @param {function} onConfirm - Callback when confirmed
     * @param {function} onCancel - Callback when cancelled
     */
    window.NMEApp.TOCAlerts.showSingleDeleteConfirm = function(onConfirm, onCancel) {
        NMEModal.confirm({
            title: 'Delete Trip',
            message: 'Are you sure you want to delete this entry? This cannot be undone.',
            confirmText: 'OK',
            cancelText: 'Cancel',
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    };

    /**
     * Show No Trips confirmation
     * @param {string} lookbackDate - Formatted lookback start date string
     * @param {function} onConfirm - Callback when confirmed
     * @param {function} onCancel - Callback when cancelled
     */
    window.NMEApp.TOCAlerts.showNoTripsConfirm = function(lookbackDate, onConfirm, onCancel) {
        NMEModal.confirm({
            title: 'No Trips Outside US',
            message: 'Are you sure you have not traveled outside the United States since ' + lookbackDate + '?',
            confirmText: 'Yes, No Trips',
            cancelText: 'Cancel',
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    };

    // Expose shorthand reference
    window.NMETOCAlerts = window.NMEApp.TOCAlerts;

})(jQuery, window, document);