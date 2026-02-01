/**
 * NME Application - Eligibility Logic Module
 * 
 * This module handles the complex eligibility determination logic
 * including the controlling factor calculations for the NME Application plugin.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.EligibilityLogic = {};

    /**
     * Determine the controlling factor for eligibility
     * @param {Object} dates - Object containing all date values
     * @returns {Object} - Object with controllingFactor, date, description, status, and message
     */
    window.NMEApp.EligibilityLogic.determineControllingFactor = function(dates) {
        const DateCalc = window.NMEApp.DateCalculations;
        
        let controllingFactor = null;
        let controllingDate = null;
        let controllingDesc = null;
        let status = "";
        let applicationMessage = "";

        // Check if LPR date is empty - if so, clear everything and return
        if (!dates.LPR || !(dates.LPR instanceof Date) || isNaN(dates.LPR.getTime())) {
            return {
                controllingFactor: "",
                controllingDate: "",
                controllingDesc: "",
                status: "",
                applicationMessage: ""
            };
        }

        // Determine initial controlling factor
        if (dates.marriedValue === 'No') {
            controllingFactor = 'LPR';
        } else if (dates.marriedValue === 'Yes') {
            let laterDate = (dates.DMC >= dates.SCC) ? dates.DMC : dates.SCC;
            if (laterDate === dates.DMC) {
                controllingFactor = (dates.LPRC >= dates.DMC) ? 'DM' : (dates.LPR2 >= dates.DMC) ? 'DM' : 'LPRM';
            } else {
                controllingFactor = (dates.LPRC >= dates.SCC) ? 'SC' : (dates.LPR2 >= dates.SCC) ? 'SC' : 'LPRS';
            }
        }

        // LPR logic for unmarried
        if (controllingFactor === 'LPR') {
            if (dates.Today >= dates.LPRC) {
                controllingDate = dates.LPRC;
                controllingDesc = "LPRC - 1A";
                status = "Eligible Now";
                applicationMessage = "";
            } else if (dates.Today >= dates.LPR4) {
                controllingDate = dates.LPRC;
                controllingDesc = "LPRC - 1B";
                status = "Prepare, but file later";
                applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPRC)}.  <em>Note: filing earlier than this date will result in a denial of your case without a refund.</em>`;
            } else {
                controllingDate = dates.LPRC;
                controllingDesc = "LPRC - 1C";
                status = "Eligibility Assessment";
                applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.LPRC)}.   Moreover, you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to this site.  Full access will be restored on ${DateCalc.formatDate(dates.LPRC6)}, which is 6 months prior to  ${DateCalc.formatDate(dates.LPRC)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
            }
        }

        // If exactly one of DM or SC is present, clear everything
        if ((dates.DM instanceof Date && !isNaN(dates.DM.getTime())) !== (dates.SC instanceof Date && !isNaN(dates.SC.getTime()))) {
            controllingDate = "";
            controllingDesc = "";
            status = "";
        }

        // LPRM / LPRS logic if spouse+citizen both present
        if ((dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && (dates.SC instanceof Date && !isNaN(dates.SC.getTime()))) {
            if (controllingFactor === 'LPRM') {
                if (dates.Today >= dates.LPR4) {
                    controllingDate = dates.LPRC;
                    controllingDesc = "LPRC - Married No Benefit PF";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPRC)}.  Note: filing earlier than this date will result in a denial of your case without a refund. Please note that based upon your date of Legal Permanent Residency, it has been determined that you ought to file without relying upon the marriage to your U.S. citizen spouse because you can thereby file sooner.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you may rely upon the above referenced date.  Please select "Next" to continue.`;
                } else {
                    controllingDate = dates.LPRC;
                    controllingDesc = "LPRC - Married No Benefit EA";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPRC)}.  Please note that based upon your date of Legal Permanent Residency, it has been determined that you ought to file without relying upon the marriage to your U.S. citizen spouse because you can thereby file sooner.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPRC6)}, which is 6 months prior to ${DateCalc.formatDate(dates.LPRC)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
            if (controllingFactor === 'LPRS') {
                if (dates.Today >= dates.LPR4) {
                    controllingDate = dates.LPRC;
                    controllingDesc = "LPRC - Spouse No Benefit PF";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPRC)}.  Note: filing earlier than this date will result in a denial of your case without a refund. Please note that based upon your date of Legal Permanent Residency, it has been determined that you ought to file without relying upon the marriage to your U.S. citizen spouse because you can thereby file sooner.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you may rely upon the above referenced date.  Please select "Next" to continue.`;
                } else {
                    controllingDate = dates.LPRC;
                    controllingDesc = "LPRC - Spouse No Benefit EA";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPRC)}.  Please note that based upon your date of Legal Permanent Residency, it has been determined that you ought to file without relying upon the marriage to your U.S. citizen spouse because you can thereby file sooner.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPRC6)}, which is 6 months prior to ${DateCalc.formatDate(dates.LPRC)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
        }

        // DM controlling factor
        if (controllingFactor === 'DM' && (dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && (dates.SC instanceof Date && !isNaN(dates.SC.getTime()))) {
            if (dates.Today >= dates.DMC && dates.Today >= dates.LPR3) {
                controllingDate = dates.DMC;
                controllingDesc = "DMC - 2A";
                status = "Eligible Now";
                applicationMessage = `Based upon the information you entered you are eligible now to file for Naturalization – please confirm that your date of marriage and date of spouse's citizenship (if applicable) are correct before proceeding.`;
            }
            else if (dates.Today >= dates.DMC) {
                controllingDate = dates.LPR3;
                controllingDesc = "LPR3 - 2B";
                status = "Prepare, but file later";
                applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPR3)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
            }
            else if (dates.Today >= dates.DM2) {
                if (dates.DM2 >= dates.LPR3) {
                    controllingDate = dates.DMC;
                    controllingDesc = "DMC - 2D";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.DMC)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else if (dates.DM2 >= dates.LPR2) {
                    controllingDate = dates.DMC;
                    controllingDesc = "DMC - 2E";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.DMC)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else if (dates.Today >= dates.LPR2) {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2F";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPR3)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2G";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.LPR3)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPR36)} which is 6 months prior to ${DateCalc.formatDate(dates.LPR3)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
            else {
                if (dates.DM2 >= dates.LPR2) {
                    controllingDate = dates.DMC;
                    controllingDesc = "DMC - 2H";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.DMC)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these dates, then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.DMC6)} which is 6 months prior to ${DateCalc.formatDate(dates.DMC)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                } else {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2I";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.LPR3)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPR36)} which is 6 months prior to ${DateCalc.formatDate(dates.LPR3)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
        }

        // SC controlling factor
        if (controllingFactor === 'SC' && (dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && (dates.SC instanceof Date && !isNaN(dates.SC.getTime()))) {
            if (dates.Today >= dates.SCC && dates.Today >= dates.LPR3) {
                controllingDate = dates.SCC;
                controllingDesc = "SCC - 2A";
                status = "Eligible Now";
                applicationMessage = `Based upon the information you entered you are eligible now to file for Naturalization – please confirm that your date of marriage and date of spouse's citizenship (if applicable) are correct before proceeding.`;
            }
            else if (dates.Today >= dates.SCC) {
                controllingDate = dates.LPR3;
                controllingDesc = "LPR3 - 2B";
                status = "Prepare, but file later";
                applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPR3)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
            }
            else if (dates.Today >= dates.SC2) {
                if (dates.SC2 >= dates.LPR3) {
                    controllingDate = dates.SCC;
                    controllingDesc = "SCC - 2D";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.SCC)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else if (dates.SC2 >= dates.LPR2) {
                    controllingDate = dates.SCC;
                    controllingDesc = "SCC - 2E";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.SCC)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else if (dates.Today >= dates.LPR2) {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2F";
                    status = "Prepare, but file later";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after ${DateCalc.formatDate(dates.LPR3)}. Note: filing earlier than this date will result in a denial of your case without a refund. If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).`;
                } else {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2G";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.LPR3)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPR36)} which is 6 months prior to ${DateCalc.formatDate(dates.LPR3)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
            else {
                if (dates.SC2 >= dates.LPR2) {
                    controllingDate = dates.SCC;
                    controllingDesc = "SCC - 2H";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.SCC)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these dates, then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.SCC6)} which is 6 months prior to ${DateCalc.formatDate(dates.SCC)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                } else {
                    controllingDate = dates.LPR3;
                    controllingDesc = "LPR3 - 2I";
                    status = "Eligibility Assessment";
                    applicationMessage = `As of today, you are not currently eligible to file for Naturalization – you can file, however, on or after  ${DateCalc.formatDate(dates.LPR3)}.  If you believe that this is in error, please go back and check whether you correctly entered your date of marriage and date of spouse's citizenship (if applicable).  If you have correctly entered these date(s), then you have sought to apply more than one (1) year early and, therefore, pursuant to the terms of use you will have limited access to the site.  Full access will be restored on ${DateCalc.formatDate(dates.LPR36)} which is 6 months prior to ${DateCalc.formatDate(dates.LPR3)} which is the date on or after which you are eligible to file.   In the meantime, you will have access to "Documents' if you wish to gather the documents which will be used in support of your application.`;
                }
            }
        }

        return {
            controllingFactor: controllingFactor,
            controllingDate: controllingDate,
            controllingDesc: controllingDesc,
            status: status,
            applicationMessage: applicationMessage
        };
    };

    /**
     * Update form fields and UI based on controlling factor
     * @param {Object} result - Result from determineControllingFactor
     */
    window.NMEApp.EligibilityLogic.updateFormWithResults = function(result) {
        const DateCalc = window.NMEApp.DateCalculations;
        
        console.log("controllingDesc:", result.controllingDesc);
        
        // Check if LPR field has a value
        if (!$('#input_70_23').val()) {
            // No LPR date - hide both buttons
            window.NMEApp.FieldVisibility.hideAllButtons();
            window.NMEApp.FieldVisibility.highlightFields(
                ["#input_70_5", "#input_70_10", "#input_70_23"], 
                false
            );
            return;
        }
        
        // LPR date exists - show appropriate button based on eligibility
        if (result.controllingDesc === "LPRC - 1A") {
            // Eligible Now - show Submit button (skip page 2)
            window.NMEApp.ModalAlerts.displayLPRMessage(
                "Based upon the information you entered you may file now.  Please make sure the values highlighted above are correct as you <b>cannot change them later</b>"
            );
            window.NMEApp.FieldVisibility.toggleNextButton(false);
            window.NMEApp.FieldVisibility.highlightFields(
                ["#input_70_5", "#input_70_10", "#input_70_23"], 
                true
            );
        } else {
            // Not eligible yet - show Next button (go to page 2)
            window.NMEApp.FieldVisibility.toggleNextButton(true);
            window.NMEApp.ModalAlerts.displayLPRMessage(
                '<p>Before you are permitted to continue with your application, you must confirm the following information for security purposes: (1) your date of birth, (2) your Alien Number, and (3) your date of Legal Permanent Residency.  These items <b>CANNOT BE CHANGED OR EDITED</b> later and customer service will not be able to change these for you.  If you fail to correct these now, you will be required to purchase another session with Naturalization Made Easy ("NME").</p><p>You may correct the entries by clicking "I understand" and making those changes before clicking "Next" in order to move onto the next page.  Once you click "Next", you hereby confirm your responses are correct with respect to your date of birth, Alien Number, and date of Legal Permanent Residency.</p>'
            );
            window.NMEApp.FieldVisibility.highlightFields(
                ["#input_70_5", "#input_70_10", "#input_70_23"], 
                true
            );
        }

        // Set radio button value based on controllingFactor
        if (result.controllingFactor === "LPR" || result.controllingFactor === "LPRM" || result.controllingFactor === "LPRS") {
            $('input[name="input_20"][value="LPR"]').prop('checked', true).trigger('change');
        } else if (result.controllingFactor) {
            $('input[name="input_20"][value="Spouse"]').prop('checked', true).trigger('change');
        } else {
            // Clear selection if no controllingFactor
            $('input[name="input_20"]').prop('checked', false).trigger('change');
        }
        
        // Update form fields
        $('#input_70_34').val(result.controllingFactor || "").trigger('change');
        $('#input_70_35').val(result.controllingDate ? DateCalc.formatDate(result.controllingDate) : "").trigger('change');
        $('#input_70_36').val(result.controllingDesc || "").trigger('change');
        $('#input_70_37').val(result.status || "").trigger('change');
        
        // Display application message
        window.NMEApp.ModalAlerts.displayApplicationMessage(result.applicationMessage);
    };

    /**
     * Main function to determine and update eligibility
     * This is called by form handlers when relevant fields change
     */
    window.NMEApp.EligibilityLogic.determineAndUpdateEligibility = function() {
        // Get dates from FormHandlers
        const dates = window.NMEApp.FormHandlers.dates;
        
        // Determine controlling factor
        const result = window.NMEApp.EligibilityLogic.determineControllingFactor(dates);
        
        // Update form with results
        window.NMEApp.EligibilityLogic.updateFormWithResults(result);
    };

    /**
     * Clear all eligibility results from the form
     */
    window.NMEApp.EligibilityLogic.clearResults = function() {
        $('#input_70_34').val("").trigger('change');
        $('#input_70_35').val("").trigger('change');
        $('#input_70_36').val("").trigger('change');
        $('#input_70_37').val("").trigger('change');
        window.NMEApp.ModalAlerts.clearApplicationMessage();
        window.NMEApp.ModalAlerts.clearLPRMessage();
        window.NMEApp.FieldVisibility.hideAllButtons();
        window.NMEApp.FieldVisibility.highlightFields(
            ["#input_70_5", "#input_70_10", "#input_70_23"], 
            false
        );
    };

    // Expose a shorthand reference for convenience
    window.NMEEligibility = window.NMEApp.EligibilityLogic;

})(jQuery, window, document);