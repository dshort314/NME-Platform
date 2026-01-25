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
    };

    /**
     * Initialize field visibility on page 2 load
     */
    window.NMEApp.FieldVisibility.initializePageTwoFields = function() {
        // Hide all conditional fields initially
        conditionalFields.forEach(fieldId => window.NMEApp.FieldVisibility.hideField(fieldId));
        
        // Check current values and set visibility accordingly
        window.NMEApp.FieldVisibility.handlePrimaryRadioChange();
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
     * Check if we're on page 2 of the form
     * @returns {boolean} - True if on page 2
     */
    window.NMEApp.FieldVisibility.isPageTwo = function() {
        const pageContainer = document.getElementById('gform_page_70_2');
        return pageContainer !== null;
    };

    /**
     * Initialize the field visibility module
     */
    window.NMEApp.FieldVisibility.init = function() {
        if (window.NMEApp.FieldVisibility.isPageTwo()) {
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
     * @param {boolean} show - True to show Next button (hide Submit), 
     *                         False to show Submit button (hide Next)
     */
    window.NMEApp.FieldVisibility.toggleNextButton = function(show) {
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