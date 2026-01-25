/**
 * NME Application - Form Handlers Module
 * 
 * This module handles form event handlers, session storage,
 * and date field updates for the NME Application plugin.
 * 
 * Debug logging is controlled by the Dashboard checkbox for
 * the 'information-about-you' module. Uses global NMEDebug utility.
 */

(function($, window, document) {
    'use strict';

    // Module ID for debug logging
    const MODULE_ID = 'information-about-you';

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
     * Debug logger - wrapper for global NMEDebug utility
     * @param {...any} args - Arguments to log
     */
    window.NMEApp.FormHandlers.debug = function(...args) {
        if (typeof NMEDebug !== 'undefined') {
            NMEDebug(MODULE_ID, ...args);
        }
    };

    /**
     * Debug helper to log dates object state
     * @param {string} context - Context description for the log
     */
    window.NMEApp.FormHandlers.logDatesState = function(context) {
        if (typeof NMEDebug === 'undefined' || !NMEDebug.isEnabled(MODULE_ID)) {
            return;
        }
        
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        NMEDebug.state(MODULE_ID, 'DATES (' + context + ')', {
            LPR: dates.LPR ? DateCalc.formatDate(dates.LPR) : 'null',
            LPRC: dates.LPRC ? DateCalc.formatDate(dates.LPRC) : 'null',
            DM: dates.DM ? DateCalc.formatDate(dates.DM) : 'null',
            DMC: dates.DMC ? DateCalc.formatDate(dates.DMC) : 'null',
            SC: dates.SC ? DateCalc.formatDate(dates.SC) : 'null',
            SCC: dates.SCC ? DateCalc.formatDate(dates.SCC) : 'null',
            Today: dates.Today ? DateCalc.formatDate(dates.Today) : 'null',
            marriedValue: dates.marriedValue
        });
    };

    /**
     * Persist values to session storage
     */
    window.NMEApp.FormHandlers.persistToSession = function() {
        $('#input_70_24').on('change', function() {
            let todayVal = $(this).val();
            if (todayVal) {
                sessionStorage.setItem('gform_today_70', todayVal);
            }
        });

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
        const debug = window.NMEApp.FormHandlers.debug;
        
        $(document).bind('gform_page_loaded', function(event, formId, currentPage) {
            debug('gform_page_loaded - formId:', formId, 'currentPage:', currentPage);
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
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateToday = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateToday called, skipEligibility:', skipEligibility);
        
        const dates = window.NMEApp.FormHandlers.dates;
        dates.Today = window.NMEApp.DateCalculations.parseDate($('#input_70_24').val(), true);
        debug('Today parsed as:', dates.Today);
        
        window.NMEApp.FormHandlers.updateBirth5();
        window.NMEApp.FormHandlers.updateMarriageVisibility();
        
        if (skipEligibility !== true) {
            window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
        }
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
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateLPR = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateLPR called, skipEligibility:', skipEligibility);
        debug('input_70_23 value:', $('#input_70_23').val());
        
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.LPR = DateCalc.parseDate($('#input_70_23').val());
        debug('LPR parsed as:', dates.LPR);
        
        if (!dates.LPR) {
            debug('LPR is null/invalid, clearing derived fields');
            dates.LPR2 = dates.LPR3 = dates.LPR4 = dates.LPRC = null;
            dates.LPR36 = dates.LPRC6 = null;
            $('#input_70_25, #input_70_28, #input_70_27, #input_70_26').val('');
            
            if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
            return;
        }

        dates.LPR2  = DateCalc.addYears(dates.LPR, 2, -90);
        dates.LPR3  = DateCalc.addYears(dates.LPR, 3, -90);
        dates.LPR4  = DateCalc.addYears(dates.LPR, 4, -90);
        dates.LPRC  = DateCalc.addYears(dates.LPR, 5, -90);
        dates.LPR36 = DateCalc.subtractMonths(dates.LPR3, 6);
        dates.LPRC6 = DateCalc.subtractMonths(dates.LPRC, 6);

        debug('Calculated LPRC:', DateCalc.formatDate(dates.LPRC));

        $('#input_70_25').val(DateCalc.formatDate(dates.LPR2));
        $('#input_70_28').val(DateCalc.formatDate(dates.LPR3));
        $('#input_70_27').val(DateCalc.formatDate(dates.LPR4));
        $('#input_70_26').val(DateCalc.formatDate(dates.LPRC));

        window.NMEApp.FormHandlers.updateMarriageVisibility();
        
        if (skipEligibility !== true) {
            window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
        }
    };

    /**
     * Update DM (Date of Marriage) dates
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateDM = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateDM called, skipEligibility:', skipEligibility);
        debug('input_70_18 value:', $('#input_70_18').val());
        
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.DM = DateCalc.parseDate($('#input_70_18').val());
        debug('DM parsed as:', dates.DM);
        
        if (!dates.DM) {
            debug('DM is null/invalid, clearing derived fields');
            dates.DM2 = dates.DMC = dates.DMC6 = null;
            $('#input_70_32, #input_70_31').val('');
            
            if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
            return;
        }

        dates.DM2  = DateCalc.addYears(dates.DM, 2);
        dates.DMC  = DateCalc.addYears(dates.DM, 3);
        dates.DMC6 = DateCalc.subtractMonths(dates.DMC, 6);

        debug('Calculated DMC:', DateCalc.formatDate(dates.DMC));

        $('#input_70_32').val(DateCalc.formatDate(dates.DM2));
        $('#input_70_31').val(DateCalc.formatDate(dates.DMC));
        
        if (skipEligibility !== true) {
            window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
        }
    };

    /**
     * Update SC (Spouse Citizenship) dates
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateSC = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateSC called, skipEligibility:', skipEligibility);
        debug('input_70_17 value:', $('#input_70_17').val());
        
        const dates = window.NMEApp.FormHandlers.dates;
        const DateCalc = window.NMEApp.DateCalculations;
        
        dates.SC = DateCalc.parseDate($('#input_70_17').val());
        debug('SC parsed as:', dates.SC);
        
        if (!dates.SC) {
            debug('SC is null/invalid, clearing derived fields');
            dates.SC2 = dates.SCC = dates.SCC6 = null;
            $('#input_70_30, #input_70_29').val('');
            
            if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
            return;
        }

        dates.SC2  = DateCalc.addYears(dates.SC, 2);
        dates.SCC  = DateCalc.addYears(dates.SC, 3);
        dates.SCC6 = DateCalc.subtractMonths(dates.SCC, 6);

        debug('Calculated SCC:', DateCalc.formatDate(dates.SCC));

        $('#input_70_30').val(DateCalc.formatDate(dates.SC2));
        $('#input_70_29').val(DateCalc.formatDate(dates.SCC));
        
        if (skipEligibility !== true) {
            window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
        }
    };

    /**
     * Update spouse birth logic
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateScBirth = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateScBirth called, skipEligibility:', skipEligibility);
        
        const dates = window.NMEApp.FormHandlers.dates;
        let sc_birth = $('input[name="input_16"]:checked').val();
        debug('input_16 value:', sc_birth);
        
        $('#input_70_17').val('');
        dates.SC = null;
        
        if (sc_birth === "Birth") {
            $("#field_70_17").hide();
            $("#input_70_17").val($('#input_70_15').val());
            dates.SC = window.NMEApp.DateCalculations.parseDate($('#input_70_15').val());
            window.NMEApp.FormHandlers.updateSC(skipEligibility);
        } else if (sc_birth === "Naturalization") {
            $("#field_70_17").show();
            if ($('#input_70_17').val()) {
                window.NMEApp.FormHandlers.updateSC(skipEligibility);
            } else if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
        } else {
            $("#field_70_17").hide();
            dates.SC2 = dates.SCC = dates.SCC6 = null;
            $('#input_70_30, #input_70_29').val('');
            
            if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
        }
    };

    /**
     * Update married value and show/hide related fields
     * @param {boolean} skipEligibility - If true, skip eligibility recalculation
     */
    window.NMEApp.FormHandlers.updateMarriedValue = function(skipEligibility) {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('updateMarriedValue called, skipEligibility:', skipEligibility);
        
        const dates = window.NMEApp.FormHandlers.dates;
        dates.marriedValue = $('input[name="input_12"]:checked').val();
        debug('marriedValue:', dates.marriedValue);
        
        if (dates.marriedValue === "Yes") {
            $("#field_70_18, #field_70_16").show();
            window.NMEApp.FormHandlers.updateScBirth(skipEligibility);
        } else {
            $("#field_70_16").hide();
            $("#field_70_18, #field_70_17").hide().find('input').val('');
            $("#input_70_31, #input_70_32, #input_70_29, #input_70_30").val('');
            dates.DM = dates.DMC = dates.DM2 = dates.SC = dates.SCC = dates.SC2 = null;
            dates.DMC6 = dates.SCC6 = null;
            
            if (skipEligibility !== true) {
                window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
            }
        }
    };

    /**
     * Update marriage visibility based on Today < LPRC
     */
    window.NMEApp.FormHandlers.updateMarriageVisibility = function() {
        const debug = window.NMEApp.FormHandlers.debug;
        const dates = window.NMEApp.FormHandlers.dates;
        const maritalStatus = $('input[name="input_11"]:checked').val();
        
        debug('updateMarriageVisibility - maritalStatus:', maritalStatus);
        debug('Today < LPRC?', dates.Today < dates.LPRC);
        
        if (dates.Today instanceof Date && !isNaN(dates.Today.getTime()) && 
            dates.Today < dates.LPRC && maritalStatus === 'Married') {
            $("#field_70_12").show();
        } else {
            $("#field_70_12").hide();
            $('input[name="input_12"][value="No"]').prop('checked', true);
            window.NMEApp.FormHandlers.updateMarriedValue(true);
            $("#input_70_18, #input_70_17, #input_70_31, #input_70_32, #input_70_29, #input_70_30").val('');
            dates.DM = dates.DMC = dates.DM2 = dates.SC = dates.SCC = dates.SC2 = null;
            dates.DMC6 = dates.SCC6 = null;
        }
    };

    /**
     * Trigger eligibility recalculation
     */
    window.NMEApp.FormHandlers.triggerEligibilityRecalculation = function() {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('triggerEligibilityRecalculation called');
        
        window.NMEApp.FormHandlers.logDatesState('before eligibility');
        
        if (window.NMEApp.ModalAlerts) {
            window.NMEApp.ModalAlerts.clearApplicationMessage();
        }
        
        if (!$('#input_70_23').val()) {
            debug('LPR field empty, clearing results');
            if (window.NMEApp.FieldVisibility) {
                window.NMEApp.FieldVisibility.toggleNextButton(false);
                window.NMEApp.FieldVisibility.highlightFields(
                    ["#input_70_5", "#input_70_10", "#input_70_23"], 
                    false
                );
            }
            if (window.NMEApp.EligibilityLogic) {
                window.NMEApp.EligibilityLogic.clearResults();
            }
            return;
        }
        
        if (window.NMEApp.EligibilityLogic && window.NMEApp.EligibilityLogic.determineAndUpdateEligibility) {
            debug('Calling determineAndUpdateEligibility');
            window.NMEApp.EligibilityLogic.determineAndUpdateEligibility();
        } else {
            debug('ERROR: EligibilityLogic or determineAndUpdateEligibility not found!');
        }
        
        // Log field values after update (only when debug enabled)
        if (typeof NMEDebug !== 'undefined' && NMEDebug.isEnabled(MODULE_ID)) {
            NMEDebug.group(MODULE_ID, 'After eligibility - field values');
            NMEDebug(MODULE_ID, 'input_70_34 (Controlling Factor):', $('#input_70_34').val());
            NMEDebug(MODULE_ID, 'input_70_35 (Application Date):', $('#input_70_35').val());
            NMEDebug(MODULE_ID, 'input_70_36 (Description):', $('#input_70_36').val());
            NMEDebug(MODULE_ID, 'input_70_37 (Status):', $('#input_70_37').val());
            NMEDebug(MODULE_ID, '#application-message HTML:', $('#application-message').html());
            NMEDebug(MODULE_ID, '#application-message-lpr HTML:', $('#application-message-lpr').html());
            NMEDebug.groupEnd(MODULE_ID);
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
        const debug = window.NMEApp.FormHandlers.debug;
        debug('setupEventHandlers called');
        
        $('#input_70_23').on('change', function() {
            debug('input_70_23 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateLPR();
        });
        
        $('#input_70_18').on('change', function() {
            debug('input_70_18 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateDM();
        });
        
        $('#input_70_17').on('change', function() {
            debug('input_70_17 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateSC();
        });
        
        $('#input_70_24').on('change', function() {
            debug('input_70_24 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateToday();
        });
        
        $('#input_70_15').on('change', function() {
            debug('input_70_15 (Spouse DOB) changed to:', $(this).val());
            let sc_birth = $('input[name="input_16"]:checked').val();
            if (sc_birth === "Birth") {
                $('#input_70_17').val($(this).val());
                window.NMEApp.FormHandlers.updateSC();
            }
        });
        
        $('input[name="input_12"]').on('change', function() {
            debug('input_12 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateMarriedValue();
        });
        
        $('input[name="input_16"]').on('change', function() {
            debug('input_16 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateScBirth();
        });
        
        $('input[name="input_11"]').on('change', function() {
            debug('input_11 changed to:', $(this).val());
            window.NMEApp.FormHandlers.updateMarriageVisibility();
            
            if ($(this).val() === 'Married') {
                window.NMEApp.FormHandlers.checkMarriageFilingDelayAlert();
            }
            
            window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
        });

        $('input[name="input_12"]').on('change', function() {
            if ($(this).val() === 'Yes') {
                window.NMEApp.ModalAlerts.showSpouseEligibilityAlert();
            }
        });

        $('input[name="input_19"]').on('change', function() {
            if ($(this).val() === 'No') {
                const dates = window.NMEApp.FormHandlers.dates;
                window.NMEApp.ModalAlerts.showResidencyRequirementAlert(dates.LPRC);
            }
        });

        $('#input_70_24, #input_70_5').on('change', function() {
            window.NMEApp.FormHandlers.checkAge18OrOlder();
        });
    };

    /**
     * Initialize form values on page load
     */
    window.NMEApp.FormHandlers.initializeFormValues = function() {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('initializeFormValues called');
        
        window.NMEApp.FormHandlers.updateToday(true);
        
        if ($('#input_70_23').val()) {
            debug('Found LPR value on init:', $('#input_70_23').val());
            window.NMEApp.FormHandlers.updateLPR(true);
        }
        if ($('#input_70_18').val()) {
            debug('Found DM value on init:', $('#input_70_18').val());
            window.NMEApp.FormHandlers.updateDM(true);
        }
        if ($('#input_70_17').val()) {
            debug('Found SC value on init:', $('#input_70_17').val());
            window.NMEApp.FormHandlers.updateSC(true);
        }
        if ($('input[name="input_12"]:checked').length) {
            debug('Found married value on init:', $('input[name="input_12"]:checked').val());
            window.NMEApp.FormHandlers.updateMarriedValue(true);
        }
        
        debug('Running final eligibility calculation');
        window.NMEApp.FormHandlers.triggerEligibilityRecalculation();
    };

    /**
     * Initialize the form handlers module
     */
    window.NMEApp.FormHandlers.init = function() {
        const debug = window.NMEApp.FormHandlers.debug;
        debug('FormHandlers.init called');
        
        window.NMEApp.FormHandlers.persistToSession();
        window.NMEApp.FormHandlers.restoreFromSession();
        window.NMEApp.FormHandlers.setupEventHandlers();
        window.NMEApp.FormHandlers.initializeFormValues();
    };

    // Expose a shorthand reference for convenience
    window.NMEFormHandlers = window.NMEApp.FormHandlers;

})(jQuery, window, document);
