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

        // Initialize all modules in the correct order
        
        // 1. Field Visibility must be initialized first as it sets up the form structure
        if (window.NMEApp.FieldVisibility) {
            window.NMEApp.FieldVisibility.init();
        }

        // 2. Form Handlers initialize next as they set up date values and event handlers
        if (window.NMEApp.FormHandlers) {
            window.NMEApp.FormHandlers.init();
        }

        // 3. Run eligibility check - this controls button visibility
        // If no LPR value, it will hide the Next button and show Submit
        // If LPR value exists, it will show/hide based on eligibility
        if (window.NMEApp.EligibilityLogic) {
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
     * Gravity Forms post-render handler
     * 
     * This fires AFTER Gravity Forms has fully rendered the form.
     * Re-run eligibility to ensure button state is correct.
     */
    $(document).on('gform_post_render', function(event, form_id, current_page) {
        if (form_id === 70) {
            if (current_page === 1 && window.NMEApp.EligibilityLogic) {
                window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
            }
            
            if (current_page === 2 && window.NMEApp.FieldVisibility) {
                window.NMEApp.FieldVisibility.init();
            }
        }
    });

    /**
     * Also handle AJAX page navigation within the form
     */
    $(document).on('gform_page_loaded', function(event, form_id, current_page) {
        if (form_id === 70) {
            if (current_page === 1 && window.NMEApp.EligibilityLogic) {
                window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
            }
            
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