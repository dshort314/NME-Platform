/**
 * NME Application - Date Calculations Module
 * 
 * This module handles all date parsing, formatting, and calculation functions
 * used throughout the NME Application plugin.
 */

(function($, window, document) {
    'use strict';

    // Create namespace
    window.NMEApp = window.NMEApp || {};
    window.NMEApp.DateCalculations = {};

    /**
     * Format a date to MM/DD/YYYY format
     * @param {Date} date - The date to format
     * @returns {string} - Formatted date string or empty string if invalid
     */
    window.NMEApp.DateCalculations.formatDate = function(date) {
        if (date instanceof Date && !isNaN(date.getTime())) {
            let day = ('0' + date.getDate()).slice(-2);
            let month = ('0' + (date.getMonth() + 1)).slice(-2);
            let year = date.getFullYear();
            return month + '/' + day + '/' + year;
        }
        return '';
    };

    /**
     * Parse a date value into a Date object
     * @param {string} value - The date string to parse
     * @param {boolean} useTodayIfInvalid - Whether to return today's date if parsing fails
     * @returns {Date|null} - Parsed date or null/today if invalid
     */
    window.NMEApp.DateCalculations.parseDate = function(value, useTodayIfInvalid = false) {
        let date = new Date(value);
        return isNaN(date.getTime()) ? (useTodayIfInvalid ? new Date() : null) : date;
    };

    /**
     * Add years to a date with optional day adjustment
     * @param {Date} date - The base date
     * @param {number} years - Number of years to add
     * @param {number} dayAdjustment - Number of days to adjust (default 0)
     * @returns {Date|null} - New date or null if input invalid
     */
    window.NMEApp.DateCalculations.addYears = function(date, years, dayAdjustment = 0) {
        if (!date) return null;
        let newDate = new Date(date);
        newDate.setFullYear(newDate.getFullYear() + years);
        newDate.setDate(newDate.getDate() + dayAdjustment);
        return newDate;
    };

    /**
     * Subtract months from a date with optional day adjustment
     * @param {Date} date - The base date
     * @param {number} months - Number of months to subtract
     * @param {number} dayAdjustment - Number of days to adjust (default 0)
     * @returns {Date|null} - New date or null if input invalid
     */
    window.NMEApp.DateCalculations.subtractMonths = function(date, months, dayAdjustment = 0) {
        if (!date) return null;
        let newDate = new Date(date);
        newDate.setMonth(newDate.getMonth() - months);
        newDate.setDate(newDate.getDate() + dayAdjustment);
        return newDate;
    };

    /**
     * Calculate age from date of birth
     * @param {Date} dob - Date of birth
     * @param {Date} referenceDate - Date to calculate age at (usually today)
     * @returns {number|null} - Age in years or null if invalid
     */
    window.NMEApp.DateCalculations.calculateAge = function(dob, referenceDate) {
        if (!dob || !referenceDate) return null;
        
        let age = referenceDate.getFullYear() - dob.getFullYear();
        const monthDiff = referenceDate.getMonth() - dob.getMonth();
        
        // Adjust age if birthday hasn't occurred this year yet
        if (monthDiff < 0 || (monthDiff === 0 && referenceDate.getDate() < dob.getDate())) {
            age--;
        }
        
        return age;
    };

    /**
     * Calculate the difference in months between two dates
     * @param {Date} date1 - First date
     * @param {Date} date2 - Second date
     * @returns {number|null} - Difference in months or null if invalid
     */
    window.NMEApp.DateCalculations.monthsDifference = function(date1, date2) {
        if (!date1 || !date2) return null;
        
        const diffMs = date2 - date1;
        const diffMonths = diffMs / (1000 * 60 * 60 * 24 * 30);
        
        return diffMonths;
    };

    /**
     * Format a date for display in alerts and messages
     * @param {Date} date - The date to format
     * @returns {string} - Formatted date string for display
     */
    window.NMEApp.DateCalculations.formatDateForDisplay = function(date) {
        if (!date || !(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }
        
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    /**
     * Validate if a date string is in valid format
     * @param {string} dateString - The date string to validate
     * @returns {boolean} - True if valid date format
     */
    window.NMEApp.DateCalculations.isValidDateString = function(dateString) {
        if (!dateString) return false;
        
        const date = new Date(dateString);
        return date instanceof Date && !isNaN(date.getTime());
    };

    // Expose a shorthand reference for convenience
    window.NMEDateCalc = window.NMEApp.DateCalculations;

})(jQuery, window, document);
