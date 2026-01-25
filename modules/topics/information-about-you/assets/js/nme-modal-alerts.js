/**
 * NME Application - Modal Alerts Module
 * 
 * This module handles all modal dialogs and alert functions
 * used throughout the NME Application plugin.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.ModalAlerts = {};

    /**
     * Show a custom HTML modal with content
     * @param {string} htmlContent - The HTML content to display in the modal
     * @param {function} onClose - Optional callback when modal is closed
     */
    window.NMEApp.ModalAlerts.showHtmlModal = function(htmlContent, onClose) {
        // Remove any existing modals first
        $('#customOverlay, #customModal').remove();

        // Create and append overlay and modal
        var $overlay = $('<div id="customOverlay"></div>').appendTo('body');
        var $modal = $('<div id="customModal"></div>').html(htmlContent).appendTo('body');

        // Wire up close button if it exists
        $modal.find('.close-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            if (typeof onClose === 'function') {
                onClose();
            }
        });

        // Also close on overlay click
        $overlay.on('click', function() {
            $overlay.remove();
            $modal.remove();
            if (typeof onClose === 'function') {
                onClose();
            }
        });

        // Close on Escape key
        $(document).on('keydown.nmeModal', function(e) {
            if (e.keyCode === 27) { // ESC key
                $overlay.remove();
                $modal.remove();
                $(document).off('keydown.nmeModal');
                if (typeof onClose === 'function') {
                    onClose();
                }
            }
        });

        // Focus management for accessibility
        $modal.attr('tabindex', '-1').focus();
    };

    /**
     * Show marriage filing delay alert
     */
    window.NMEApp.ModalAlerts.showMarriageFilingDelayAlert = function() {
        var html = ''
            + '<h3>Filing Timeline Consideration</h3>'
            + '<p>If you are not in a rush and the time period to wait is not lengthy, it is advisable not to file based upon marriage to your U.S. citizen spouse because, first, there is less documentation for you to submit and, second, the government does not need to assess the validity of your marriage. In addition, you may also get a faster decision because there is less for the government to review.</p>'
            + '<p>For example, if you have been a legal permanent resident for 4 years and 7 months (only two months away from when you can file without relying upon your U.S. citizen spouse), it may be worthwhile to wait 2 months to reach this filing date instead of applying based upon marriage to your U.S. citizen spouse now.</p>'
            + '<p><strong>Important:</strong> USCIS does not consider the fact that you are married to a U.S. citizen as any advantage in deciding your application, i.e., that USCIS will decide your application faster, overlook problems with your application, etc.</p>'
            + '<p>If you wish to file based upon waiting 4 years and 9 months, then select "No." If you wish to proceed now, as married to a U.S. citizen spouse, despite that your 4 years and 9 months is upcoming shortly, then select "Yes."</p>'
            + '<button class="close-btn">I Understand</button>';

        window.NMEApp.ModalAlerts.showHtmlModal(html);
    };

    /**
     * Show spouse eligibility confirmation alert with Continue and Back buttons
     */
    window.NMEApp.ModalAlerts.showSpouseEligibilityAlert = function() {
        var html = ''
            + '<h3>Spouse Eligibility Requirements</h3>'
            + '<p><strong>To qualify to file within 3 years you must:</strong></p>'
            + '<ol>'
            + '<li>Be currently married (not separated, divorced, or widowed), and</li>'
            + '<li>Currently living with your U.S. citizen spouse (not living apart)</li>'
            + '</ol>'
            + '<p>If these conditions do not apply, you cannot elect to file as a spouse of a U.S. citizen and should wait until 4 years and 9 months has passed since becoming a legal permanent resident.</p>'
            + '<p><strong>Please confirm your answer by selecting "Continue". If not, please select "Back" and change your answer to "No."</strong></p>'
            + '<div style="text-align: center; margin-top: 20px;">'
            + '<button class="close-btn continue-btn" style="margin-right: 10px; background-color: #0073aa;">Continue</button>'
            + '<button class="close-btn back-btn" style="background-color: #666;">Back</button>'
            + '</div>';

        // Remove any existing modals first
        $('#customOverlay, #customModal').remove();

        var $overlay = $('<div id="customOverlay"></div>').appendTo('body');
        var $modal = $('<div id="customModal"></div>').html(html).appendTo('body');

        // Handle Continue button - just close the modal
        $modal.find('.continue-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            $(document).off('keydown.nmeModal');
        });

        // Handle Back button - close modal and set input_12 to "No"
        $modal.find('.back-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            $(document).off('keydown.nmeModal');
            
            // Set input_12 to "No"
            var input12No = document.querySelector('input[name="input_12"][value="No"]');
            if (input12No) {
                input12No.checked = true;
                // Trigger change event to update field visibility
                input12No.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Close on Escape key (acts like Back)
        $(document).on('keydown.nmeModal', function(e) {
            if (e.keyCode === 27) { // ESC key
                $overlay.remove();
                $modal.remove();
                $(document).off('keydown.nmeModal');
            }
        });

        // Focus management for accessibility
        $modal.attr('tabindex', '-1').focus();
    };

    /**
     * Show residency requirement alert with Continue and Back buttons
     * @param {Date} lprcDate - The LPRC date (already calculated as LPR + 5 years - 90 days)
     */
    window.NMEApp.ModalAlerts.showResidencyRequirementAlert = function(lprcDate) {
        // lprcDate is already the filing date (LPR + 5 years - 90 days)
        // No additional calculation needed
        var dateStr = lprcDate.toLocaleDateString('en-US', {
            year: 'numeric', 
            month: 'long', 
            day: 'numeric'
        });

        var html = ''
            + '<h3>Marital Union Requirement</h3>'
            + '<p><strong>USCIS Requirement:</strong> You must live in marital union with your U.S. citizen spouse for the 3 years immediately preceding your filing date in order to file early.</p>'
            + '<p>If you need to revise your answer, you may do so now. If you are residing separately from your spouse, you have two options:</p>'
            + '<ol>'
            + '<li><strong>Exception Consultation:</strong> If you still wish to file early based upon your marriage to your U.S. citizen spouse, you may revert to an ELIGIBILITY ASSESSMENT and provide the information to an immigration attorney to discuss applying for the exception to the residential requirement; or</li>'
            + '<li><strong>Standard Timeline:</strong> You may elect to wait until your filing date of <strong>' + dateStr + '</strong>, which is 4 years and 9 months from the date you became a legal permanent resident. You will receive a notice from us six (6) months prior to the date on which you are eligible to file in order to resume your application.</li>'
            + '</ol>'
            + '<p><strong>Please confirm your answer by selecting "Continue". If not, please select "Back" and change your answer to "Yes."</strong></p>'
            + '<div style="text-align: center; margin-top: 20px;">'
            + '<button class="close-btn continue-btn" style="margin-right: 10px; background-color: #0073aa;">Continue</button>'
            + '<button class="close-btn back-btn" style="background-color: #666;">Back</button>'
            + '</div>';

        // Remove any existing modals first
        $('#customOverlay, #customModal').remove();

        var $overlay = $('<div id="customOverlay"></div>').appendTo('body');
        var $modal = $('<div id="customModal"></div>').html(html).appendTo('body');

        // Handle Continue button - just close the modal
        $modal.find('.continue-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            $(document).off('keydown.nmeModal');
        });

        // Handle Back button - close modal and set input_19 to "Yes"
        $modal.find('.back-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            $(document).off('keydown.nmeModal');
            
            // Set input_19 to "Yes"
            var input19Yes = document.querySelector('input[name="input_19"][value="Yes"]');
            if (input19Yes) {
                input19Yes.checked = true;
                // Trigger change event
                input19Yes.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        // Close on Escape key
        $(document).on('keydown.nmeModal', function(e) {
            if (e.keyCode === 27) { // ESC key
                $overlay.remove();
                $modal.remove();
                $(document).off('keydown.nmeModal');
            }
        });

        // Focus management for accessibility
        $modal.attr('tabindex', '-1').focus();
    };

    /**
     * Show age verification alert
     */
    window.NMEApp.ModalAlerts.showAgeVerificationAlert = function() {
        var html = ''
            + '<h3>Age Requirement Issue</h3>'
            + '<p><strong>Age Verification Problem:</strong> You have indicated that you are not yet 18 years of age, despite indicating in our preliminary assessment that you were over 18 years of age and confirming so before purchasing your license to use this product.</p>'
            + '<p>You will be directed to our ELIGIBILITY ASSESSMENT at this time and you will only be permitted to resume your application when you turn 18 years of age.</p>'
            + '<button class="close-btn">Proceed to Assessment</button>';

        window.NMEApp.ModalAlerts.showHtmlModal(html, function() {
            // Redirect to eligibility assessment or take appropriate action
            window.location.href = '/eligibility-assessment/';
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
        var html = ''
            + '<h3>' + title + '</h3>'
            + '<p>' + message + '</p>'
            + '<div style="text-align: center; margin-top: 20px;">'
            + '<button class="close-btn confirm-btn" style="margin-right: 10px; background-color: #0073aa;">Confirm</button>'
            + '<button class="close-btn cancel-btn" style="background-color: #666;">Cancel</button>'
            + '</div>';

        // Remove any existing modals first
        $('#customOverlay, #customModal').remove();

        var $overlay = $('<div id="customOverlay"></div>').appendTo('body');
        var $modal = $('<div id="customModal"></div>').html(html).appendTo('body');

        // Handle confirm button
        $modal.find('.confirm-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            if (typeof onConfirm === 'function') {
                onConfirm();
            }
        });

        // Handle cancel button
        $modal.find('.cancel-btn').on('click', function() {
            $overlay.remove();
            $modal.remove();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });

        // Close on overlay click (acts as cancel)
        $overlay.on('click', function() {
            $overlay.remove();
            $modal.remove();
            if (typeof onCancel === 'function') {
                onCancel();
            }
        });
    };

    /**
     * Display application message in the designated container
     * @param {string} message - The message to display
     * @param {string} containerId - The ID of the container element (default: 'application-message')
     */
    window.NMEApp.ModalAlerts.displayApplicationMessage = function(message, containerId = 'application-message') {
        $('#' + containerId).html(message);
        
        // Add appropriate styling based on message content
        var $container = $('#' + containerId);
        if (message.toLowerCase().includes('eligible now')) {
            $container.addClass('nme-success').removeClass('nme-error');
        } else if (message.toLowerCase().includes('not currently eligible')) {
            $container.addClass('nme-error').removeClass('nme-success');
        }
    };

    /**
     * Clear application message
     * @param {string} containerId - The ID of the container element (default: 'application-message')
     */
    window.NMEApp.ModalAlerts.clearApplicationMessage = function(containerId = 'application-message') {
        $('#' + containerId).html('').removeClass('nme-success nme-error');
    };

    /**
     * Display LPR-specific message
     * @param {string} message - The message to display
     */
    window.NMEApp.ModalAlerts.displayLPRMessage = function(message) {
        $("#application-message-lpr").html(message);
    };

    /**
     * Clear LPR-specific message
     */
    window.NMEApp.ModalAlerts.clearLPRMessage = function() {
        $("#application-message-lpr").html('');
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
    window.NMEApp.ModalAlerts.showToast = function(message, type = 'info', duration = 3000) {
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