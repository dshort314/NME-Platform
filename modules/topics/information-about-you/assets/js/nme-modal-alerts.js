/**
 * NME Application - Modal Alerts Module
 * 
 * This module provides modal dialogs for the Information About You form.
 * Uses the global NMEModal system for consistent appearance and behavior.
 * 
 * All modals are now handled by the centralized NMEModal utility.
 * This module provides form-specific convenience methods.
 */

(function($, window, document) {
    'use strict';

    const MODULE_ID = 'information-about-you';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.ModalAlerts = {};

    /**
     * Debug helper
     */
    function debug(...args) {
        if (typeof NMEDebug !== 'undefined') {
            NMEDebug(MODULE_ID, ...args);
        }
    }

    /**
     * Track previous eligibility status to detect changes
     */
    let previousStatus = null;
    let previousControllingDesc = null;

    /**
     * Show marriage filing delay alert
     */
    window.NMEApp.ModalAlerts.showMarriageFilingDelayAlert = function() {
        debug('Showing marriage filing delay alert');

        NMEModal.info({
            title: 'Filing Timeline Consideration',
            message: '<p>If you are not in a rush and the time period to wait is not lengthy, it is advisable not to file based upon marriage to your U.S. citizen spouse because, first, there is less documentation for you to submit and, second, the government does not need to assess the validity of your marriage. In addition, you may also get a faster decision because there is less for the government to review.</p>' +
                '<p>For example, if you have been a legal permanent resident for 4 years and 7 months (only two months away from when you can file without relying upon your U.S. citizen spouse), it may be worthwhile to wait 2 months to reach this filing date instead of applying based upon marriage to your U.S. citizen spouse now.</p>' +
                '<p><strong>Important:</strong> USCIS does not consider the fact that you are married to a U.S. citizen as any advantage in deciding your application, i.e., that USCIS will decide your application faster, overlook problems with your application, etc.</p>' +
                '<p>If you wish to file based upon waiting 4 years and 9 months, then select "No." If you wish to proceed now, as married to a U.S. citizen spouse, despite that your 4 years and 9 months is upcoming shortly, then select "Yes."</p>'
        });
    };

    /**
     * Show spouse eligibility confirmation alert with Continue and Back buttons
     */
    window.NMEApp.ModalAlerts.showSpouseEligibilityAlert = function() {
        debug('Showing spouse eligibility alert');

        NMEModal.confirm({
            title: 'Spouse Eligibility Requirements',
            message: '<p><strong>To qualify to file within 3 years you must:</strong></p>' +
                '<ol>' +
                '<li>Be currently married (not separated, divorced, or widowed), and</li>' +
                '<li>Currently living with your U.S. citizen spouse (not living apart)</li>' +
                '</ol>' +
                '<p>If these conditions do not apply, you cannot elect to file as a spouse of a U.S. citizen and should wait until 4 years and 9 months has passed since becoming a legal permanent resident.</p>' +
                '<p><strong>Please confirm your answer by selecting "Continue". If not, please select "Back" and change your answer.</strong></p>',
            confirmText: 'Continue',
            cancelText: 'Back',
            revertField: 'input[name="input_12"]',
            onConfirm: function() {
                debug('Spouse eligibility confirmed');
            },
            onCancel: function() {
                debug('Spouse eligibility cancelled, field cleared');
            }
        });
    };

    /**
     * Show residency requirement alert with Continue and Back buttons
     * @param {Date} lprcDate - The LPRC date (already calculated as LPR + 5 years - 90 days)
     */
    window.NMEApp.ModalAlerts.showResidencyRequirementAlert = function(lprcDate) {
        debug('Showing residency requirement alert');

        var dateStr = lprcDate.toLocaleDateString('en-US', {
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        });

        NMEModal.confirm({
            title: 'Marital Union Requirement',
            message: '<p><strong>USCIS Requirement:</strong> You must live in marital union with your U.S. citizen spouse for the 3 years immediately preceding your filing date in order to file early.</p>' +
                '<p>If you need to revise your answer, you may do so now. If you are residing separately from your spouse, you have two options:</p>' +
                '<ol>' +
                '<li><strong>Exception Consultation:</strong> If you still wish to file early based upon your marriage to your U.S. citizen spouse, you may revert to an ELIGIBILITY ASSESSMENT and provide the information to an immigration attorney to discuss applying for the exception to the residential requirement; or</li>' +
                '<li><strong>Standard Timeline:</strong> You may elect to wait until your filing date of <strong>' + dateStr + '</strong>, which is 4 years and 9 months from the date you became a legal permanent resident. You will receive a notice from us six (6) months prior to the date on which you are eligible to file in order to resume your application.</li>' +
                '</ol>' +
                '<p><strong>Please confirm your answer by selecting "Continue". If not, please select "Back" and change your answer.</strong></p>',
            confirmText: 'Continue',
            cancelText: 'Back',
            revertField: 'input[name="input_19"]',
            onConfirm: function() {
                debug('Residency requirement confirmed');
            },
            onCancel: function() {
                debug('Residency requirement cancelled, field cleared');
            }
        });
    };

    /**
     * Show age verification alert
     */
    window.NMEApp.ModalAlerts.showAgeVerificationAlert = function() {
        debug('Showing age verification alert');

        NMEModal.warning({
            title: 'Age Requirement Issue',
            message: '<p><strong>Age Verification Problem:</strong> You have indicated that you are not yet 18 years of age, despite indicating in our preliminary assessment that you were over 18 years of age and confirming so before purchasing your license to use this product.</p>' +
                '<p>You will be directed to our ELIGIBILITY ASSESSMENT at this time and you will only be permitted to resume your application when you turn 18 years of age.</p>',
            confirmText: 'Proceed to Assessment',
            onClose: function() {
                window.location.href = '/eligibility-assessment/';
            }
        });
    };

    /**
     * Show generic confirmation dialog
     * @param {string} title - Dialog title
     * @param {string} message - Dialog message
     * @param {function} onConfirm - Callback when confirmed
     * @param {function} onCancel - Callback when cancelled
     */
    window.NMEApp.ModalAlerts.showConfirmDialog = function(title, message, onConfirm, onCancel) {
        debug('Showing confirm dialog:', title);

        NMEModal.confirm({
            title: title,
            message: message,
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    };

    /**
     * Display eligibility status message as a modal
     * Only shows if status has changed from previous state.
     * 
     * @param {string} message - The message to display
     * @param {string} status - The eligibility status (for determining modal type)
     * @param {string} controllingDesc - The controlling description code
     */
    window.NMEApp.ModalAlerts.displayApplicationMessage = function(message, status, controllingDesc) {
        // Don't show empty messages
        if (!message || message.trim() === '') {
            debug('displayApplicationMessage: Empty message, skipping');
            return;
        }

        // Check if status has actually changed
        if (status === previousStatus && controllingDesc === previousControllingDesc) {
            debug('displayApplicationMessage: Status unchanged, skipping modal');
            return;
        }

        debug('displayApplicationMessage: Status changed from', previousStatus, 'to', status);
        debug('displayApplicationMessage: Description changed from', previousControllingDesc, 'to', controllingDesc);

        // Update tracked state
        previousStatus = status;
        previousControllingDesc = controllingDesc;

        // Determine modal type based on status
        let modalType = 'info';
        let title = 'Eligibility Status';

        if (status === 'Eligible Now') {
            modalType = 'success';
            title = 'You Are Eligible to File';
        } else if (status === 'Prepare, but file later') {
            modalType = 'info';
            title = 'Eligibility Status: Prepare Now, File Later';
        } else if (status === 'Eligibility Assessment') {
            modalType = 'warning';
            title = 'Eligibility Assessment Required';
        }

        // Show the modal
        NMEModal.show({
            type: modalType,
            title: title,
            message: '<p>' + message + '</p>'
        });
    };

    /**
     * Clear application message tracking
     * Called when form fields change to reset state detection
     */
    window.NMEApp.ModalAlerts.clearApplicationMessage = function() {
        // Don't reset tracking here - we want to detect actual status changes
        // This function is kept for API compatibility
        debug('clearApplicationMessage called');
    };

    /**
     * Display LPR-specific message as a modal
     * Shows important reminders about field values.
     * 
     * @param {string} message - The message to display
     */
    window.NMEApp.ModalAlerts.displayLPRMessage = function(message) {
        // Don't show empty messages
        if (!message || message.trim() === '') {
            debug('displayLPRMessage: Empty message, skipping');
            return;
        }

        debug('displayLPRMessage:', message);

        // This is typically a reminder about highlighted fields
        // Only show once per session to avoid annoyance
        if (window.NMEApp.ModalAlerts._lprMessageShown) {
            debug('displayLPRMessage: Already shown this session, skipping');
            return;
        }

        window.NMEApp.ModalAlerts._lprMessageShown = true;

        NMEModal.info({
            title: 'Important Reminder',
            message: message
        });
    };

    /**
     * Clear LPR message tracking
     */
    window.NMEApp.ModalAlerts.clearLPRMessage = function() {
        // Keep for API compatibility
        debug('clearLPRMessage called');
    };

    /**
     * Reset all message tracking
     * Call this when starting a fresh form or on page load
     */
    window.NMEApp.ModalAlerts.resetMessageTracking = function() {
        debug('Resetting message tracking');
        previousStatus = null;
        previousControllingDesc = null;
        window.NMEApp.ModalAlerts._lprMessageShown = false;
    };

    /**
     * Show loading overlay on a specific element
     * @param {string} selector - jQuery selector for the element
     */
    window.NMEApp.ModalAlerts.showLoading = function(selector) {
        $(selector).addClass('nme-loading');
    };

    /**
     * Hide loading overlay
     * @param {string} selector - jQuery selector for the element
     */
    window.NMEApp.ModalAlerts.hideLoading = function(selector) {
        $(selector).removeClass('nme-loading');
    };

    /**
     * Show toast notification
     * @param {string} message - The message to display
     * @param {string} type - Type of toast (success, error, info, warning)
     * @param {number} duration - How long to show (milliseconds, default 3000)
     */
    window.NMEApp.ModalAlerts.showToast = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 3000;

        debug('Showing toast:', type, message);

        // Remove existing toasts
        $('.nme-toast').remove();

        var $toast = $('<div class="nme-toast nme-toast-' + type + '">' + message + '</div>');
        
        // Add toast styles if not already present
        if (!$('#nme-toast-styles').length) {
            $('<style id="nme-toast-styles">' +
                '.nme-toast { position: fixed; top: 20px; right: 20px; z-index: 100000; ' +
                'padding: 15px 20px; border-radius: 4px; color: white; font-weight: 600; ' +
                'max-width: 300px; opacity: 0; transform: translateX(100%); ' +
                'transition: all 0.3s ease; } ' +
                '.nme-toast.show { opacity: 1; transform: translateX(0); } ' +
                '.nme-toast-success { background-color: #28a745; } ' +
                '.nme-toast-error { background-color: #dc3545; } ' +
                '.nme-toast-warning { background-color: #ffc107; color: #212529; } ' +
                '.nme-toast-info { background-color: #17a2b8; }' +
                '</style>').appendTo('head');
        }

        $toast.appendTo('body');
        
        // Trigger animation
        setTimeout(function() {
            $toast.addClass('show');
        }, 10);

        // Auto hide
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() {
                $toast.remove();
            }, 300);
        }, duration);
    };

    // Expose a shorthand reference for convenience
    window.NMEModals = window.NMEApp.ModalAlerts;

})(jQuery, window, document);
