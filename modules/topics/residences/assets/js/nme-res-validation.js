/**
 * NME Residence Validation - Pages 504, 514, 705
 * 
 * Validates date relationships between residence fields:
 * - From date must be before To date
 * - To date cannot be more than 30 days before From date
 * - 90-day state residency rule enforcement
 *
 * @package NME\Topics\Residences
 */

(function($) {
    'use strict';

    /**
     * Initialize residence date validation
     */
    function initResidenceValidation() {
        console.log('NME Residence Validation: Script loaded');

        // Get references to form fields
        var input38_3 = document.getElementById('input_38_3');   // "From" date
        var input38_4 = document.getElementById('input_38_4');   // "To" date
        var input38_15 = document.getElementById('input_38_15'); // Related date field
        var input38_12 = document.getElementById('input_38_12'); // Sequence number

        // Exit if required fields are missing
        if (!input38_3 || !input38_4) {
            console.log('NME Residence Validation: Required fields not found');
            return;
        }

        // Store initial values for reset
        var initialInput38_3 = input38_3.value;
        var initialInput38_4 = input38_4.value;

        console.log('NME Residence Validation: Initial values', {
            from: initialInput38_3,
            to: initialInput38_4,
            sequence: input38_12 ? input38_12.value : 'N/A'
        });

        // ============================================================
        // "To" Date Validation
        // ============================================================
        input38_4.addEventListener('change', function() {
            var sequence = input38_12 ? parseInt(input38_12.value.trim()) || 0 : 0;

            // Skip basic validation for new entries (sequence > 1)
            // Only run basic validation for first residence entry (sequence = 1)
            if (sequence === 1) {
                var toDateValue = new Date(this.value);
                var fromDateValue = new Date(input38_3.value);

                var daysDiff = daysBetweenDates(toDateValue, fromDateValue);
                console.log('NME Residence Validation: Days between To and From:', daysDiff);

                // To date must be before From date (older)
                if (daysDiff < 0) {
                    resetAndAlert('input_38_4', initialInput38_4,
                        'The date entered must be older than the date you began living at the new residence');
                }

                // To date cannot be more than 30 days before From date
                if (daysDiff > 30) {
                    resetAndAlert('input_38_4', initialInput38_4,
                        'The date entered cannot be more than 30 days prior to the date you began living at your new residence.');
                }
            } else {
                console.log('NME Residence Validation: Skipping basic date validation for sequence:', sequence);
            }
        });

        // ============================================================
        // "From" Date Validation
        // ============================================================
        input38_3.addEventListener('change', function() {
            var fromDateValue = new Date(this.value);
            var toDateValue = new Date(input38_4.value);

            // Basic validation: From date cannot be after To date
            if (fromDateValue && toDateValue && fromDateValue > toDateValue) {
                resetAndAlert('input_38_3', initialInput38_3,
                    'The From date cannot be after the To date');
                return;
            }

            // Wait for Google Address Autocomplete to populate fields
            setTimeout(function() {
                validateStateResidency();
            }, 500);

            var daysDifference = daysBetweenDates(toDateValue, fromDateValue);
            console.log('NME Residence Validation: Days between From and To:', daysDifference);
        });

        // ============================================================
        // State Field Validation (for 90-day rule)
        // ============================================================
        var stateField = document.getElementById('input_38_13_4');
        if (stateField) {
            stateField.addEventListener('change', validateStateResidency);
            stateField.addEventListener('input', validateStateResidency);

            // MutationObserver for autocomplete changes
            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'value') {
                        setTimeout(validateStateResidency, 100);
                    }
                });
            });
            observer.observe(stateField, { attributes: true, attributeFilter: ['value'] });
        }

        /**
         * Validate 90-day state residency rule
         */
        function validateStateResidency() {
            var currentStateInput = document.getElementById('input_38_13_4'); // Current entry state
            var previousStateInput = document.getElementById('input_38_16');  // Previous state from URL
            var durationInput = document.getElementById('input_38_14');       // Duration field
            var sequenceInput = document.getElementById('input_38_12');       // Sequence number

            if (!currentStateInput || !previousStateInput || !sequenceInput) {
                console.log('NME Residence Validation: 90-day validation - Required fields not found');
                return;
            }

            var currentState = currentStateInput.value.trim();
            var previousState = previousStateInput.value.trim();
            var sequence = parseInt(sequenceInput.value.trim()) || 0;

            // Get receive-duration from field or URL
            var daysInPreviousState = 0;
            if (durationInput && durationInput.value) {
                daysInPreviousState = parseInt(durationInput.value) || 0;
            } else {
                var urlParams = new URLSearchParams(window.location.search);
                daysInPreviousState = parseInt(urlParams.get('receive-duration')) || 0;
            }

            console.log('NME Residence Validation: 90-day check', {
                sequence: sequence,
                currentState: currentState,
                previousState: previousState,
                daysInPreviousState: daysInPreviousState
            });

            // 90-day rule:
            // - Only applies if sequence > 1 (not the first residence)
            // - Only applies if current state is DIFFERENT from previous state
            // - Only applies if days in previous state < 90
            var shouldApply90DayRule = (
                sequence > 1 &&
                currentState &&
                previousState &&
                currentState !== previousState &&
                daysInPreviousState < 90
            );

            console.log('NME Residence Validation: Should apply 90-day rule?', shouldApply90DayRule);

            if (shouldApply90DayRule) {
                resetAndAlert('input_38_3', initialInput38_3,
                    'You must live in the same State for 90 consecutive days before your application can be submitted.');
            }
        }

        // Run validation after page load to catch pre-filled values
        setTimeout(function() {
            validateStateResidency();
        }, 1000);
    }

    // ============================================================
    // Helper Functions
    // ============================================================

    /**
     * Calculate days between two dates
     * @param {Date} earlierDate - The earlier date
     * @param {Date} laterDate - The later date
     * @return {number} - Number of days between dates
     */
    function daysBetweenDates(earlierDate, laterDate) {
        var oneDay = 24 * 60 * 60 * 1000;
        return Math.round((laterDate - earlierDate) / oneDay);
    }

    /**
     * Reset a field to its original value and show an alert
     * @param {string} inputId - ID of the input field to reset
     * @param {string} originalValue - Original value to restore
     * @param {string} message - Alert message to display
     */
    function resetAndAlert(inputId, originalValue, message) {
        var input = document.getElementById(inputId);
        if (input) {
            input.value = originalValue;
        }
        alert(message);
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initResidenceValidation();
    });

})(jQuery);
