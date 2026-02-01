/**
 * NME Application - Field Visibility Module
 * 
 * This module handles conditional field visibility logic
 * for the NME Application plugin forms.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.FieldVisibility = {};

    // Define field groups
    const conditionalFields = [
        'field_70_12', 'field_70_18', 'field_70_14', 
        'field_70_15', 'field_70_16', 'field_70_17', 'field_70_19'
    ];
    
    // Secondary fields shown when input_12 = "Yes" (EXCLUDES field_70_17)
    const secondaryFields = [
        'field_70_18', 'field_70_14', 'field_70_15', 
        'field_70_16', 'field_70_19'
    ];
    
    // field_70_17 only shows when input_16 = "Naturalization"
    const naturalizationDateField = 'field_70_17';

    // Page 2 field configurations for completion checking
    // Maps field wrapper IDs to their input selectors
    const pageTwoFields = {
        'field_70_11': 'input[name="input_11"]',           // Marital Status (radio)
        'field_70_12': 'input[name="input_12"]',           // Filing based on marriage (radio)
        'field_70_14': '#input_70_14_3, #input_70_14_6',   // Spouse Name (first and last required)
        'field_70_15': '#input_70_15',                     // Spouse DOB
        'field_70_16': 'input[name="input_16"]',           // How spouse became citizen (radio)
        'field_70_17': '#input_70_17',                     // Date spouse became citizen
        'field_70_18': '#input_70_18',                     // Date of Marriage
        'field_70_19': 'input[name="input_19"]'            // Spouse same physical address (radio)
    };

    /**
     * Hide a field by ID
     * @param {string} fieldId - The field ID to hide
     */
    window.NMEApp.FieldVisibility.hideField = function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.display = 'none';
        }
    };

    /**
     * Show a field by ID
     * @param {string} fieldId - The field ID to show
     */
    window.NMEApp.FieldVisibility.showField = function(fieldId) {
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.display = 'block';
        }
    };

    /**
     * Get selected value from radio button group
     * @param {string} inputName - The name attribute of the radio group
     * @returns {string|null} - Selected value or null
     */
    window.NMEApp.FieldVisibility.getRadioValue = function(inputName) {
        const radioButtons = document.querySelectorAll(`input[name="${inputName}"]`);
        for (let radio of radioButtons) {
            if (radio.checked) {
                return radio.value;
            }
        }
        return null;
    };

    /**
     * Clear all selections from a radio button group
     * @param {string} inputName - The name attribute of the radio group
     */
    window.NMEApp.FieldVisibility.clearRadioSelection = function(inputName) {
        const radioButtons = document.querySelectorAll(`input[name="${inputName}"]`);
        radioButtons.forEach(radio => {
            radio.checked = false;
        });
    };

    /**
     * Handle primary radio button change (input_11 - Marital Status)
     */
    window.NMEApp.FieldVisibility.handlePrimaryRadioChange = function() {
        const selectedValue = window.NMEApp.FieldVisibility.getRadioValue('input_11');
        
        if (selectedValue === 'Married') {
            // Clear any existing selection on input_12 before showing
            // This ensures no default value is pre-selected
            window.NMEApp.FieldVisibility.clearRadioSelection('input_12');
            
            // Show field_70_12 when "Married" is selected
            window.NMEApp.FieldVisibility.showField('field_70_12');
            
            // Check if field_70_12 has a value and handle accordingly
            window.NMEApp.FieldVisibility.handleSecondaryRadioChange();
        } else {
            // Hide field_70_12 and all secondary fields for any other value
            window.NMEApp.FieldVisibility.hideField('field_70_12');
            secondaryFields.forEach(fieldId => window.NMEApp.FieldVisibility.hideField(fieldId));
            // Also hide the naturalization date field
            window.NMEApp.FieldVisibility.hideField(naturalizationDateField);
            
            // Set input_12 to "No" when input_11 is anything except "Married"
            const input12No = document.querySelector('input[name="input_12"][value="No"]');
            if (input12No) {
                input12No.checked = true;
                // Trigger change event to ensure any other logic that depends on input_12 runs
                input12No.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // Re-check Page 2 completion after field visibility changes
        window.NMEApp.FieldVisibility.checkPageTwoCompletion();
    };

    /**
     * Handle secondary radio button change (input_12 - Filing based on marriage)
     */
    window.NMEApp.FieldVisibility.handleSecondaryRadioChange = function() {
        const primaryValue = window.NMEApp.FieldVisibility.getRadioValue('input_11');
        
        // Only proceed if primary field is "Married"
        if (primaryValue !== 'Married') {
            return;
        }
        
        const secondaryValue = window.NMEApp.FieldVisibility.getRadioValue('input_12');
        
        if (secondaryValue === 'Yes') {
            // Show all secondary fields when "Yes" is selected
            secondaryFields.forEach(fieldId => window.NMEApp.FieldVisibility.showField(fieldId));
            // Check input_16 to determine if naturalization date field should show
            window.NMEApp.FieldVisibility.handleCitizenshipTypeChange();
        } else {
            // Hide all secondary fields for "No" or no selection
            secondaryFields.forEach(fieldId => window.NMEApp.FieldVisibility.hideField(fieldId));
            // Also hide the naturalization date field
            window.NMEApp.FieldVisibility.hideField(naturalizationDateField);
        }

        // Re-check Page 2 completion after field visibility changes
        window.NMEApp.FieldVisibility.checkPageTwoCompletion();
    };

    /**
     * Handle citizenship type radio button change (input_16 - When spouse became citizen)
     */
    window.NMEApp.FieldVisibility.handleCitizenshipTypeChange = function() {
        const citizenshipType = window.NMEApp.FieldVisibility.getRadioValue('input_16');
        
        if (citizenshipType === 'Naturalization') {
            // Show the naturalization date field only when "Naturalization" is selected
            window.NMEApp.FieldVisibility.showField(naturalizationDateField);
        } else {
            // Hide for "Birth" or no selection
            window.NMEApp.FieldVisibility.hideField(naturalizationDateField);
        }

        // Re-check Page 2 completion after field visibility changes
        window.NMEApp.FieldVisibility.checkPageTwoCompletion();
    };

    /**
     * Check if a field wrapper is currently visible
     * @param {string} fieldId - The field wrapper ID (e.g., 'field_70_12')
     * @returns {boolean} - True if the field is visible
     */
    window.NMEApp.FieldVisibility.isFieldVisible = function(fieldId) {
        const field = document.getElementById(fieldId);
        if (!field) {
            return false;
        }
        // Check if display is not 'none' and element is not hidden
        const style = window.getComputedStyle(field);
        return style.display !== 'none' && style.visibility !== 'hidden';
    };

    /**
     * Check if a field has a value
     * @param {string} selector - jQuery selector for the input(s)
     * @returns {boolean} - True if the field has a value
     */
    window.NMEApp.FieldVisibility.fieldHasValue = function(selector) {
        const elements = $(selector);
        
        if (elements.length === 0) {
            return false;
        }

        // Check if it's a radio button group
        if (elements.first().is(':radio')) {
            return elements.filter(':checked').length > 0;
        }

        // Check if it's a checkbox
        if (elements.first().is(':checkbox')) {
            return elements.filter(':checked').length > 0;
        }

        // For multiple elements (like name fields), check if all have values
        if (elements.length > 1) {
            let allFilled = true;
            elements.each(function() {
                if (!$(this).val() || $(this).val().trim() === '') {
                    allFilled = false;
                    return false; // Break the loop
                }
            });
            return allFilled;
        }

        // For single text/date inputs
        const value = elements.val();
        return value && value.trim() !== '';
    };

    /**
     * Check if all visible Page 2 fields are completed
     * @returns {boolean} - True if all visible fields have values
     */
    window.NMEApp.FieldVisibility.areAllVisibleFieldsComplete = function() {
        for (const [fieldId, inputSelector] of Object.entries(pageTwoFields)) {
            // Skip if field is not visible
            if (!window.NMEApp.FieldVisibility.isFieldVisible(fieldId)) {
                continue;
            }

            // Check if visible field has a value
            if (!window.NMEApp.FieldVisibility.fieldHasValue(inputSelector)) {
                console.log('NME FieldVisibility: Incomplete field:', fieldId);
                return false;
            }
        }

        console.log('NME FieldVisibility: All visible fields complete');
        return true;
    };

    /**
     * Check Page 2 completion and show/hide Submit button accordingly
     */
    window.NMEApp.FieldVisibility.checkPageTwoCompletion = function() {
        // Only run when Page 2 is actually visible
        if (!window.NMEApp.FieldVisibility.isPageTwoVisible()) {
            return;
        }

        const isComplete = window.NMEApp.FieldVisibility.areAllVisibleFieldsComplete();
        
        if (isComplete) {
            $('#gform_submit_button_70').show();
            console.log('NME FieldVisibility: Showing Submit button - all fields complete');
        } else {
            $('#gform_submit_button_70').hide();
            console.log('NME FieldVisibility: Hiding Submit button - fields incomplete');
        }
    };

    /**
     * Setup event listeners for Page 2 field completion checking
     */
    window.NMEApp.FieldVisibility.setupPageTwoCompletionListeners = function() {
        // Listen to all Page 2 input fields for changes
        for (const [fieldId, inputSelector] of Object.entries(pageTwoFields)) {
            $(inputSelector).on('change input', function() {
                window.NMEApp.FieldVisibility.checkPageTwoCompletion();
            });
        }

        console.log('NME FieldVisibility: Page 2 completion listeners configured');
    };

    /**
     * Initialize field visibility on page 2 load
     */
    window.NMEApp.FieldVisibility.initializePageTwoFields = function() {
        // Hide all conditional fields initially
        conditionalFields.forEach(fieldId => window.NMEApp.FieldVisibility.hideField(fieldId));
        
        // Hide Submit button initially on Page 2
        $('#gform_submit_button_70').hide();
        
        // Check current values and set visibility accordingly
        window.NMEApp.FieldVisibility.handlePrimaryRadioChange();

        // Setup completion listeners
        window.NMEApp.FieldVisibility.setupPageTwoCompletionListeners();

        // Initial completion check (in case fields are pre-populated)
        window.NMEApp.FieldVisibility.checkPageTwoCompletion();
    };

    /**
     * Set up event listeners for field visibility
     */
    window.NMEApp.FieldVisibility.setupEventListeners = function() {
        // Listen for changes on the primary radio button (input_11)
        const primaryRadios = document.querySelectorAll('input[name="input_11"]');
        primaryRadios.forEach(radio => {
            radio.addEventListener('change', window.NMEApp.FieldVisibility.handlePrimaryRadioChange);
        });

        // Listen for changes on the secondary radio button (input_12)
        const secondaryRadios = document.querySelectorAll('input[name="input_12"]');
        secondaryRadios.forEach(radio => {
            radio.addEventListener('change', window.NMEApp.FieldVisibility.handleSecondaryRadioChange);
        });

        // Listen for changes on the citizenship type radio button (input_16)
        const citizenshipRadios = document.querySelectorAll('input[name="input_16"]');
        citizenshipRadios.forEach(radio => {
            radio.addEventListener('change', window.NMEApp.FieldVisibility.handleCitizenshipTypeChange);
        });
    };

    /**
     * Check if Page 2 element exists in the DOM (for init purposes)
     * @returns {boolean} - True if page 2 element exists
     */
    window.NMEApp.FieldVisibility.isPageTwo = function() {
        const pageContainer = document.getElementById('gform_page_70_2');
        return pageContainer !== null;
    };

    /**
     * Check if Page 2 is actually visible (not just in DOM)
     * Gravity Forms keeps all pages in DOM but hides inactive ones
     * @returns {boolean} - True if page 2 is visible
     */
    window.NMEApp.FieldVisibility.isPageTwoVisible = function() {
        const pageContainer = document.getElementById('gform_page_70_2');
        if (!pageContainer) {
            return false;
        }
        const style = window.getComputedStyle(pageContainer);
        return style.display !== 'none';
    };

    /**
     * Initialize the field visibility module
     */
    window.NMEApp.FieldVisibility.init = function() {
        if (window.NMEApp.FieldVisibility.isPageTwoVisible()) {
            window.NMEApp.FieldVisibility.initializePageTwoFields();
            window.NMEApp.FieldVisibility.setupEventListeners();
        }
    };

    /**
     * Hide both navigation buttons
     * 
     * Used when no LPR date has been entered yet - user cannot proceed
     * until they enter their LPR date.
     */
    window.NMEApp.FieldVisibility.hideAllButtons = function() {
        $("#gform_next_button_70_49").hide();
        $("#gform_submit_button_70").hide();
    };

    /**
     * Show or hide the Next button based on eligibility
     * 
     * Controls both the Next button (for users who need page 2) and the
     * Submit button (for users eligible to file now who skip page 2).
     * 
     * NOTE: This only operates on Page 1. On Page 2, the Submit button
     * visibility is controlled by checkPageTwoCompletion().
     * 
     * @param {boolean} show - True to show Next button (hide Submit), 
     *                         False to show Submit button (hide Next)
     */
    window.NMEApp.FieldVisibility.toggleNextButton = function(show) {
        // Only operate on Page 1 - don't interfere with Page 2 button logic
        if (window.NMEApp.FieldVisibility.isPageTwoVisible()) {
            return;
        }

        if (show) {
            // Show Next, hide Submit (not eligible yet, need page 2)
            $("#gform_next_button_70_49").show();
            $("#gform_submit_button_70").hide();
        } else {
            // Hide Next, show Submit (eligible now, skip page 2)
            $("#gform_next_button_70_49").hide();
            $("#gform_submit_button_70").show();
        }
    };

    /**
     * Apply CSS highlighting to specified fields
     * @param {array} fieldIds - Array of field IDs to highlight
     * @param {boolean} highlight - Whether to apply or remove highlighting
     */
    window.NMEApp.FieldVisibility.highlightFields = function(fieldIds, highlight) {
        const fields = fieldIds.join(', ');
        if (highlight) {
            $(fields).css({
               "background-color": "#ffff90",
               "color": "#000",
               "font-weight": "bold"
            });
        } else {
            $(fields).css({
                "background-color": "inherit",
                "color": "inherit"
            });
        }
    };

    // Expose a shorthand reference for convenience
    window.NMEFieldVis = window.NMEApp.FieldVisibility;

})(jQuery, window, document);