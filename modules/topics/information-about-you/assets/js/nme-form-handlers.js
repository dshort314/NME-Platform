/**
 * NME Application - Form Handlers Module
 * 
 * This module handles form event handlers, session storage,
 * and date field updates for the NME Application plugin.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.FormHandlers = {};

    // Global variables for dates
    window.NMEApp.FormHandlers.dates = {
        LPR: null, DM: null, SC: null, Today: null,
        LPR2: null, LPR3: null, LPR4: null, LPRC: null,
        DMC: null, DM2: null, SCC: null, SC2: null,
        LPR36: null, LPRC6: null, DMC6: null, SCC6: null,
        Birth5: null,
        marriedValue: null
    };

    /**
     * Persist values to session storage
     */
    window.NMEApp.FormHandlers.persistToSession = function() {
        // Persist Today date
        $('#input_70_24').on('change', function() {
            let todayVal = $(this).val();
            if (todayVal) {
                sessionStorage.setItem('gform_today_70', todayVal);
            }
        });

        // Persist LPR date
        $('#input_70_23').on('change', function() {
            let lprVal = $(this).val();
            if (lprVal) {
                sessionStorage.setItem('gform_lpr_70', lprVal);
            }
        });
    };

    /**
     * Restore values from session storage on page load
     */
    window.NMEApp.FormHandlers.restoreFromSession = function() {
        $(document).bind('gform_page_loaded', function(event, formId, currentPage) {
            if (formId === 70 && currentPage === 2) {
                let storedToday = sessionStorage.getItem('gform_today_70');
                if (storedToday && !$('#input_70_24').val()) {
                    $('#input_70_24').val(storedToday).trigger('change');
                }
                let storedLPR = sessionStorage.getItem('gform_lpr_70');
                if (storedLPR && !$('#input_70_23').val()) {
                    $('#input_70_23').val(storedLPR).trigger('change');
                }
            }
        });
    };

    /**
     * Update Today date and trigger related updates
     */
    window.NMEApp.FormHandlers.updateToday = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        dates.Today = window.NMEApp.DateCalculations.parseDate($('#input_70_24').val(), true);
        window.NMEApp.FormHandlers.updateBirth5();
        window.NMEApp.FormHandlers.updateMarriageVisibility();
    };

    /**
     * Update Birth5 (5 years before today)
     */
    window.NMEApp.FormHandlers.updateBirth5 = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        dates.Birth5 = window.NMEApp.DateCalculations.addYears(dates.Today, -5);
    };

    /**
     * Update LPR dates
     */
    window.NMEApp.FormHandlers.updateLPR = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.LPR = DateCalc.parseDate($('#input_70_23').val());
        if (!dates.LPR) return;

        dates.LPR2  = DateCalc.addYears(dates.LPR, 2, -90);
        dates.LPR3  = DateCalc.addYears(dates.LPR, 3, -90);
        dates.LPR4  = DateCalc.addYears(dates.LPR, 4, -90);
        dates.LPRC  = DateCalc.addYears(dates.LPR, 5, -90);
        dates.LPR36 = DateCalc.subtractMonths(dates.LPR3, 6);
        dates.LPRC6 = DateCalc.subtractMonths(dates.LPRC, 6);

        $('#input_70_25').val(DateCalc.formatDate(dates.LPR2));
        $('#input_70_28').val(DateCalc.formatDate(dates.LPR3));
        $('#input_70_27').val(DateCalc.formatDate(dates.LPR4));
        $('#input_70_26').val(DateCalc.formatDate(dates.LPRC));

        window.NMEApp.FormHandlers.updateMarriageVisibility();
    };

    /**
     * Update DM (Date of Marriage) dates
     */
    window.NMEApp.FormHandlers.updateDM = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.DM = DateCalc.parseDate($('#input_70_18').val());
        if (!dates.DM) return;

        dates.DM2  = DateCalc.addYears(dates.DM, 2);
        dates.DMC  = DateCalc.addYears(dates.DM, 3);
        dates.DMC6 = DateCalc.subtractMonths(dates.DMC, 6);

        $('#input_70_32').val(DateCalc.formatDate(dates.DM2));
        $('#input_70_31').val(DateCalc.formatDate(dates.DMC));
    };

    /**
     * Update SC (Spouse Citizenship) dates
     */
    window.NMEApp.FormHandlers.updateSC = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.SC = DateCalc.parseDate($('#input_70_17').val());
        if (!dates.SC) return;

        dates.SC2  = DateCalc.addYears(dates.SC, 2);
        dates.SCC  = DateCalc.addYears(dates.SC, 3);
        dates.SCC6 = DateCalc.subtractMonths(dates.SCC, 6);

        $('#input_70_30').val(DateCalc.formatDate(dates.SC2));
        $('#input_70_29').val(DateCalc.formatDate(dates.SCC));
    };

    /**
     * Update spouse birth logic
     * field_70_17 should ONLY show when "Naturalization" is explicitly selected
     */
    window.NMEApp.FormHandlers.updateScBirth = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        let sc_birth = $('input[name="input_16"]:checked').val();
        
        // Clear the date field value
        $('#input_70_17').val('');
        dates.SC = null;
        
        if (sc_birth === "Birth") {
            // "At Birth" selected - hide field_70_17 and use spouse DOB as citizenship date
            $("#field_70_17").hide();
            $("#input_70_17").val($('#input_70_15').val());
            dates.SC = window.NMEApp.DateCalculations.parseDate($('#input_70_15').val());
            window.NMEApp.FormHandlers.updateSC();
        } else if (sc_birth === "Naturalization") {
            // "Naturalization" explicitly selected - show field_70_17
            $("#field_70_17").show();
        } else {
            // No selection (null/undefined) - keep field_70_17 hidden
            $("#field_70_17").hide();
        }
    };

    /**
     * Update married value and show/hide related fields
     */
    window.NMEApp.FormHandlers.updateMarriedValue = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        dates.marriedValue = $('input[name="input_12"]:checked').val();
        
        if (dates.marriedValue === "Yes") {
            $("#field_70_18, #field_70_16").show();
            // field_70_17 visibility is controlled by updateScBirth based on input_16 value
            window.NMEApp.FormHandlers.updateScBirth();
        } else {
            $("#field_70_16").hide();
            $("#field_70_18, #field_70_17").hide().find('input').val('');
            $("#input_70_31, #input_70_32, #input_70_29, #input_70_30").val('');
            dates.DM = dates.DMC = dates.DM2 = dates.SC = dates.SCC = dates.SC2 = null;
        }
    };

    /**
     * Update marriage visibility based on Today < LPRC
     */
    window.NMEApp.FormHandlers.updateMarriageVisibility = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        const maritalStatus = $('input[name="input_11"]:checked').val();
        
        if (dates.Today instanceof Date && !isNaN(dates.Today.getTime()) && 
            dates.Today < dates.LPRC && maritalStatus === 'Married') {
            $("#field_70_12").show();
        } else {
            $("#field_70_12").hide();
            $('input[name="input_12"][value="No"]').prop('checked', true);
            window.NMEApp.FormHandlers.updateMarriedValue();
            $("#input_70_18, #input_70_17, #input_70_31, #input_70_32, #input_70_29, #input_70_30").val('');
            dates.DM = dates.DMC = dates.DM2 = dates.SC = dates.SCC = dates.SC2 = null;
        }
    };

    /**
     * Check marriage filing delay alert
     */
    window.NMEApp.FormHandlers.checkMarriageFilingDelayAlert = function() {
        const dates = window.NMEApp.FormHandlers.dates;
        var primary = $('input[name="input_11"]:checked').val();
        
        if (primary === 'Married' && dates.Today instanceof Date && dates.LPRC instanceof Date) {
            var diffMs = dates.LPRC - dates.Today;
            var diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);
            
            if (diffMonths < 3) {
                window.NMEApp.ModalAlerts.showMarriageFilingDelayAlert();
            }
        }
    };

    /**
     * Check age verification
     */
    window.NMEApp.FormHandlers.checkAge18OrOlder = function() {
        const todayValue = $('#input_70_24').val();
        const dobValue = $('#input_70_5').val();
        
        if (!todayValue || !dobValue) {
            return;
        }
        
        const today = window.NMEApp.DateCalculations.parseDate(todayValue);
        const dob = window.NMEApp.DateCalculations.parseDate(dobValue);
        
        if (!today || !dob) {
            return;
        }
        
        const age = window.NMEApp.DateCalculations.calculateAge(dob, today);
        
        if (age < 18) {
            window.NMEApp.ModalAlerts.showAgeVerificationAlert();
        }
    };

    /**
     * Setup all form event handlers
     */
    window.NMEApp.FormHandlers.setupEventHandlers = function() {
        // Date field change handlers
        $('#input_70_23').on('change', window.NMEApp.FormHandlers.updateLPR);
        $('#input_70_18').on('change', window.NMEApp.FormHandlers.updateDM);
        $('#input_70_17').on('change', window.NMEApp.FormHandlers.updateSC);
        $('#input_70_24').on('change', window.NMEApp.FormHandlers.updateToday);
        
        // Radio button change handlers
        $('input[name="input_12"]').on('change', window.NMEApp.FormHandlers.updateMarriedValue);
        $('input[name="input_16"]').on('change', window.NMEApp.FormHandlers.updateScBirth);
        
        // Marital status change handler
        $('input[name="input_11"]').on('change', function() {
            if ($(this).val() === 'Married') {
                window.NMEApp.FormHandlers.checkMarriageFilingDelayAlert();
            }
        });

        // Spouse filing choice handler
        $('input[name="input_12"]').on('change', function() {
            if ($(this).val() === 'Yes') {
                window.NMEApp.ModalAlerts.showSpouseEligibilityAlert();
            }
        });

        // Residency requirement handler
        $('input[name="input_19"]').on('change', function() {
            if ($(this).val() === 'No') {
                const dates = window.NMEApp.FormHandlers.dates;
                window.NMEApp.ModalAlerts.showResidencyRequirementAlert(dates.LPRC);
            }
        });

        // Age verification handlers
        $('#input_70_24, #input_70_5').on('change', function() {
            window.NMEApp.FormHandlers.checkAge18OrOlder();
        });

        // Main driver fields change handler for eligibility determination
        $('#input_70_23, #input_70_18, #input_70_17, #input_70_24, #input_70_12, #input_70_16')
        .on('change', function() {
            window.NMEApp.ModalAlerts.clearApplicationMessage();
            
            // Check if LPR field is empty
            if (!$('#input_70_23').val()) {
                window.NMEApp.FieldVisibility.toggleNextButton(false);
                window.NMEApp.FieldVisibility.highlightFields(
                    ["#input_70_5", "#input_70_10", "#input_70_23"], 
                    false
                );
                return;
            }
            
            // Trigger eligibility determination
            if (window.NMEApp.EligibilityLogic) {
                window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
            }
        });
    };

    /**
     * Initialize form values on page load
     */
    window.NMEApp.FormHandlers.initializeFormValues = function() {
        window.NMEApp.FormHandlers.updateToday();
        
        if ($('#input_70_23').val()) {
            window.NMEApp.FormHandlers.updateLPR();
        }
        if ($('#input_70_18').val()) {
            window.NMEApp.FormHandlers.updateDM();
        }
        if ($('#input_70_17').val()) {
            window.NMEApp.FormHandlers.updateSC();
        }
        if ($('input[name="input_12"]:checked').length) {
            window.NMEApp.FormHandlers.updateMarriedValue();
        }
    };

    /**
     * Initialize the form handlers module
     */
    window.NMEApp.FormHandlers.init = function() {
        window.NMEApp.FormHandlers.persistToSession();
        window.NMEApp.FormHandlers.restoreFromSession();
        window.NMEApp.FormHandlers.setupEventHandlers();
        window.NMEApp.FormHandlers.initializeFormValues();
        
        // Trigger initial eligibility determination if function exists
        if (window.NMEApp.EligibilityLogic && window.NMEApp.EligibilityLogic.determineAndUpdateEligibility) {
            window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
        }
    };

    // Expose a shorthand reference for convenience
    window.NMEFormHandlers = window.NMEApp.FormHandlers;

})(jQuery, window, document);