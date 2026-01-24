/**
 * NME Application - Main Public JavaScript
 * 
 * This is the main initialization script that coordinates all modules
 * for the NME Application plugin.
 */

(function($) {
    'use strict';

    /**
     * Main initialization function
     */
    function initializeNMEApp() {
        // Check if we're on the correct page (page 703)
        if (!$('body').hasClass('page-id-703')) {
            return;
        }

        // Hide the next button on initial load
        $("#gform_next_button_70_49").hide();
        
        // Check if LPR field is empty and hide both buttons if so
        if (!$('#input_70_23').val()) {
            $("#gform_next_button_70_49").hide();
        }

        // Initialize all modules in the correct order
        
        // 1. Field Visibility must be initialized first as it sets up the form structure
        if (window.NMEApp.FieldVisibility) {
            window.NMEApp.FieldVisibility.init();
        }

        // 2. Form Handlers initialize next as they set up date values and event handlers
        if (window.NMEApp.FormHandlers) {
            window.NMEApp.FormHandlers.init();
        }

        // 3. After form handlers are set up, run initial eligibility check if there's an LPR date
        if (window.NMEApp.EligibilityLogic && $('#input_70_23').val()) {
            window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
        }

        // Log successful initialization
        console.log('NME Application initialized successfully');
    }

    /**
     * Document ready handler
     */
    $(document).ready(function() {
        initializeNMEApp();
    });

    /**
     * Also initialize when Gravity Forms page changes (for multi-page forms)
     */
    $(document).on('gform_page_loaded', function(event, form_id, current_page) {
        if (form_id === 70) {
            // Re-initialize field visibility for page 2
            if (current_page === 2 && window.NMEApp.FieldVisibility) {
                window.NMEApp.FieldVisibility.init();
            }
        }
    });

    /**
     * Global error handler for debugging
     */
    window.addEventListener('error', function(e) {
        if (e.filename && e.filename.includes('nme-app')) {
            console.error('NME App Error:', e.message, 'at', e.filename + ':' + e.lineno);
        }
    });

})(jQuery);