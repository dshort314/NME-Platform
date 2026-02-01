/**
 * NME Application - Modal Alerts Module
 * 
 * This module handles all modal dialogs and alert functions
 * used throughout the NME Application plugin.
 * 
 * All modals now use the global NMEModal system for consistent styling.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.ModalAlerts = {};

    // Track if application message has been shown (page 2 only)
    var applicationMessageShown = false;
    
    // Session storage key for LPR message flag
    var LPR_MESSAGE_KEY = 'nme_lpr_message_shown';

    /**
     * Show a custom HTML modal with content
     * @param {string} htmlContent - The HTML content to display in the modal
     * @param {function} onClose - Optional callback when modal is closed
     */
    window.NMEApp.ModalAlerts.showHtmlModal = function(htmlContent, onClose) {
        NMEModal.info({
            title: '',
            message: htmlContent,
            buttonText: 'Close',
            onClose: onClose
        });
    };

    /**
     * Show marriage filing delay alert
     */
    window.NMEApp.ModalAlerts.showMarriageFilingDelayAlert = function() {
        var message = ''
            + '<p>If you are not in a rush and the time period to wait is not lengthy, it is advisable not to file based upon marriage to your U.S. citizen spouse because, first, there is less documentation for you to submit and, second, the government does not need to assess the validity of your marriage. In addition, you may also get a faster decision because there is less for the government to review.</p>'
            + '<p>For example, if you have been a legal permanent resident for 4 years and 7 months (only two months away from when you can file without relying upon your U.S. citizen spouse), it may be worthwhile to wait 2 months to reach this filing date instead of applying based upon marriage to your U.S. citizen spouse now.</p>'
            + '<p><strong>Important:</strong> USCIS does not consider the fact that you are married to a U.S. citizen as any advantage in deciding your application, i.e., that USCIS will decide your application faster, overlook problems with your application, etc.</p>'
            + '<p>If you wish to file based upon waiting 4 years and 9 months, then select "No." If you wish to proceed now, as married to a U.S. citizen spouse, despite that your 4 years and 9 months is upcoming shortly, then select "Yes."</p>';

        NMEModal.info({
            title: 'Filing Timeline Consideration',
            message: message,
            buttonText: 'I Understand'
        });
    };

    /**
     * Show spouse eligibility confirmation alert with Continue and Back buttons
     */
    window.NMEApp.ModalAlerts.showSpouseEligibilityAlert = function() {
        var message = ''
            + '<p><strong>To qualify to file within 3 years you must:</strong></p>'
            + '<ol>'
            + '<li>Be currently married (not separated, divorced, or widowed), and</li>'
            + '<li>Currently living with your U.S. citizen spouse (not living apart)</li>'
            + '</ol>'
            + '<p>If these conditions do not apply, you cannot elect to file as a spouse of a U.S. citizen and should wait until 4 years and 9 months has passed since becoming a legal permanent resident.</p>'
            + '<p><strong>Please confirm your answer by selecting "Continue". If not, please select "Back" and change your answer to "No."</strong></p>';

        NMEModal.confirm({
            title: 'Spouse Eligibility Requirements',
            message: message,
            confirmText: 'Continue',
            cancelText: 'Back',
            onCancel: function() {
                // Clear input_12 selection - do not auto-select any value
                var input12Radios = document.querySelectorAll('input[name="input_12"]');
                input12Radios.forEach(function(radio) {
                    radio.checked = false;
                });
                // Trigger change event to update any dependent logic
                if (input12Radios.length > 0) {
                    input12Radios[0].dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
    };

    /**
     * Show residency requirement alert with Continue and Back buttons
     * @param {Date} lprcDate - The LPRC date (already calculated as LPR + 5 years - 90 days)
     */
    window.NMEApp.ModalAlerts.showResidencyRequirementAlert = function(lprcDate) {
        var message = ''
            + '<p><strong>USCIS Requirement:</strong> You must live in marital union with your U.S. citizen spouse for the 3 years immediately preceding your filing date in order to file early as the spouse of a U.S. citizen.</p>'
            + '<p>Note that "marital union" requires "residing together", plainly understood. For example, if there is a separation in which one of you leaves the marital residence for any length of time, it would be considered to be breaking the three year time period for residing together in marital union. There are exceptions in relation to required travel and relocation for employment. Covering these exceptions, and others, to the "marital union" rule are not covered by Naturalization Made Easy and, therefore, your application will be changed to an Eligibility Assessment for you to discuss this and any other exceptions which may apply to the "marital union" rule with an attorney.</p>'
            + '<p><strong>Please confirm your answer by selecting "Continue". Otherwise, if you wish to change your answer, please select "Back".</strong></p>';

        NMEModal.confirm({
            title: 'Marital Union Requirement',
            message: message,
            confirmText: 'Continue',
            cancelText: 'Back',
            onCancel: function() {
                // Clear input_19 selection - do not auto-select any value
                var input19Radios = document.querySelectorAll('input[name="input_19"]');
                input19Radios.forEach(function(radio) {
                    radio.checked = false;
                });
                // Trigger change event to update any dependent logic
                if (input19Radios.length > 0) {
                    input19Radios[0].dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
    };

    /**
     * Show age verification alert
     */
    window.NMEApp.ModalAlerts.showAgeVerificationAlert = function() {
        var message = ''
            + '<p><strong>Age Verification Problem:</strong> You have indicated that you are not yet 18 years of age, despite indicating in our preliminary assessment that you were over 18 years of age and confirming so before purchasing your license to use this product.</p>'
            + '<p>You will be directed to our ELIGIBILITY ASSESSMENT at this time and you will only be permitted to resume your application when you turn 18 years of age.</p>';

        NMEModal.warning({
            title: 'Age Requirement Issue',
            message: message,
            buttonText: 'Proceed to Assessment',
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
        NMEModal.confirm({
            title: title,
            message: message,
            confirmText: 'Confirm',
            cancelText: 'Cancel',
            onConfirm: onConfirm,
            onCancel: onCancel
        });
    };

    /**
     * Display application message as modal (page 2 only)
     * @param {string} message - The message to display
     * @param {string} containerId - Ignored (kept for API compatibility)
     */
    window.NMEApp.ModalAlerts.displayApplicationMessage = function(message, containerId) {
        // Don't show empty messages
        if (!message || message.trim() === '') {
            return;
        }
        
        // Only show on Gravity Form page 2
        var isPage2 = $('#gform_page_70_2').is(':visible');
        if (!isPage2) {
            return;
        }
        
        // Don't repeat the same message
        if (applicationMessageShown) {
            return;
        }
        applicationMessageShown = true;

        // Determine modal type based on message content
        var type = 'info';
        var title = 'Application Status';
        
        if (message.toLowerCase().includes('eligible now')) {
            type = 'success';
            title = 'Eligible to File';
        } else if (message.toLowerCase().includes('not currently eligible')) {
            type = 'warning';
            title = 'Not Yet Eligible';
        }

        NMEModal.show({
            type: type,
            title: title,
            message: message,
            buttonText: 'I Understand'
        });
    };

    /**
     * Clear application message (resets flag so modal can show again)
     * @param {string} containerId - Ignored (kept for API compatibility)
     */
    window.NMEApp.ModalAlerts.clearApplicationMessage = function(containerId) {
        applicationMessageShown = false;
    };

    /**
     * Display LPR-specific message as modal (page 1 only)
     * @param {string} message - The message to display
     */
    window.NMEApp.ModalAlerts.displayLPRMessage = function(message) {
        // Check sessionStorage for flag
        if (sessionStorage.getItem(LPR_MESSAGE_KEY) === 'true') return;
        sessionStorage.setItem(LPR_MESSAGE_KEY, 'true');
        
        NMEModal.info({
            title: 'Filing Status',
            message: message,
            buttonText: 'I Understand'
        });
    };

    /**
     * Clear LPR-specific message (resets flag so modal can show again)
     * Only call this when user changes LPR date value
     */
    window.NMEApp.ModalAlerts.clearLPRMessage = function() {
        sessionStorage.removeItem(LPR_MESSAGE_KEY);
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
        
        // Remove existing toasts
        $('.nme-toast').remove();

        var $toast = $('<div class="nme-toast nme-toast-' + type + '">' + message + '</div>');
        
        // Add toast styles if not already present
        if (!$('#nme-toast-styles').length) {
            $('<style id="nme-toast-styles">' +
                '.nme-toast { position: fixed; top: 20px; right: 20px; z-index: 10000; ' +
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