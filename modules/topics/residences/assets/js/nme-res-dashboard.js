/**
 * NME Residence Dashboard - Page 705
 * 
 * Handles dashboard functionality including:
 * - Add button link modification with sequence, duration, and state parameters
 * - Duration calculations and display
 * - Entry storage for edit operations
 * - Residency validation display
 *
 * @package NME\Topics\Residences
 */

(function($) {
    'use strict';

    /**
     * Initialize residence dashboard
     */
    function initResidenceDashboard() {
        console.log('NME Residence Dashboard: Initializing');

        // Get user data from localized script
        var anumber = (typeof nmeResData !== 'undefined') ? nmeResData.anumber : '';
        var parentEntryId = (typeof nmeResData !== 'undefined') ? nmeResData.parentEntryId : '';

        // ============================================================
        // Add Residence Link Modification
        // ============================================================
        updateButtonLink();

        // ============================================================
        // Duration Calculations
        // ============================================================
        var totalDuration = calculateTotalDuration();
        updateDurationDisplay(totalDuration);

        // ============================================================
        // Entry Storage for Edit Operations
        // ============================================================
        setupEntryStorage();

        // ============================================================
        // Residency Validation
        // ============================================================
        validateResidency(totalDuration);

        /**
         * Update the Add Residence button link with proper parameters
         */
        function updateButtonLink() {
            // Select all elements for the state column
            var stateElements = document.querySelectorAll('#gv-field-38-13\\.3');
            var count = stateElements.length;
            var button = document.getElementById('res-add');

            // Subtract 2 from count to exclude header and footer
            var sequence = count - 1;
            var rowcount = count - 2;
            var endDate = new Date().toISOString().split('T')[0]; // Default to current date
            var prevState = '';
            var totalDaysAllResidences = 0;

            if (rowcount > 0) {
                var fromDateElements = document.querySelectorAll('#gv-field-38-3');
                var toDateElements = document.querySelectorAll('#gv-field-38-4');
                var prevStateElements = document.querySelectorAll('#gv-field-38-13\\.4');

                if (fromDateElements.length > 1 && prevStateElements.length > 1) {
                    // Get the last row's 'from date'
                    var lastFromDateElement = fromDateElements[fromDateElements.length - 2];
                    var lastDate = new Date(lastFromDateElement.textContent.trim());
                    lastDate.setDate(lastDate.getDate() - 1);
                    endDate = lastDate.toISOString().split('T')[0];

                    // Get the last row's previous state
                    var lastStateElement = prevStateElements[prevStateElements.length - 2];
                    prevState = lastStateElement.textContent.trim();
                }

                // Calculate total duration across all residences
                var tableRows = document.querySelectorAll('tbody tr');
                console.log('NME Residence Dashboard: Calculating total days across all residences');

                for (var i = 0; i < tableRows.length; i++) {
                    var row = tableRows[i];
                    var fromEl = row.querySelector('.res-from-date');
                    var toEl = row.querySelector('.res-to-date');

                    if (fromEl && toEl) {
                        var fromDate = new Date(fromEl.textContent.trim());
                        var toDate = new Date(toEl.textContent.trim());

                        if (!isNaN(fromDate) && !isNaN(toDate)) {
                            var diffTime = Math.abs(toDate - fromDate);
                            var rowDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                            totalDaysAllResidences += rowDays;
                        }
                    }
                }
            }

            console.log('NME Residence Dashboard: TOTAL days across all residences:', totalDaysAllResidences);

            if (button) {
                var currentUrl = new URL(button.href);
                currentUrl.searchParams.set('sequence', sequence);
                currentUrl.searchParams.set('end-date', endDate);
                currentUrl.searchParams.set('receive-duration', totalDaysAllResidences);
                if (prevState) {
                    currentUrl.searchParams.set('prev-state', prevState);
                }
                currentUrl.searchParams.set('anumber', anumber);
                currentUrl.searchParams.set('parent_entry_id', parentEntryId);

                button.href = currentUrl.toString();

                console.log('NME Residence Dashboard: Button updated', {
                    sequence: sequence,
                    endDate: endDate,
                    prevState: prevState,
                    totalDaysAllResidences: totalDaysAllResidences,
                    finalUrl: currentUrl.toString()
                });
            }
        }

        /**
         * Calculate total duration from all residence entries
         */
        function calculateTotalDuration() {
            var resRequired = window.parentEntryResRequired ? window.parentEntryResRequired.toString().trim() : null;
            var applicationDateStr = window.parentEntryApplicationDate ? window.parentEntryApplicationDate.toString().trim() : null;

            console.log('NME Residence Dashboard: Residence requirement:', resRequired, 'Application Date:', applicationDateStr);

            // Parse application date
            var applicationDate = parseApplicationDate(applicationDateStr);

            // Calculate lookback start date
            var computedDate = new Date(applicationDate);
            if (resRequired === 'DM' || resRequired === 'SC') {
                computedDate.setFullYear(computedDate.getFullYear() - 3);
            } else if (resRequired === 'LPRM' || resRequired === 'LPRS' || resRequired === 'LPR') {
                computedDate.setFullYear(computedDate.getFullYear() - 5);
            }

            // Update the date requirement display
            var formattedDate = (computedDate.getMonth() + 1) + '/' + computedDate.getDate() + '/' + computedDate.getFullYear();
            var dateResReqSpan = document.getElementById('dateResReq');
            if (dateResReqSpan) {
                dateResReqSpan.textContent = 'Please list residences from ' + formattedDate + ' until today.';
            }

            // Calculate and sum durations
            var rowElements = document.querySelectorAll('.res-index');
            var totalDuration = 0;

            rowElements.forEach(function(indexElement) {
                var row = indexElement.parentElement;
                var fromDateCell = row.querySelector('.res-from-date');
                var toDateCell = row.querySelector('.res-to-date');

                if (fromDateCell && toDateCell) {
                    var fromDateText = fromDateCell.textContent.trim();
                    var toDateText = toDateCell.textContent.trim();

                    var fromDate = new Date(fromDateText);
                    var toDate = new Date(toDateText);

                    if (!isNaN(fromDate) && !isNaN(toDate)) {
                        var diffTime = Math.abs(toDate - fromDate);
                        var diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
                        totalDuration += diffDays;
                    }
                }
            });

            // Store residence count
            var allRows = document.querySelectorAll('tr');
            var validRows = Array.from(allRows).filter(function(row) {
                var indexEl = row.querySelector('.res-index');
                return indexEl && !isNaN(parseInt(indexEl.textContent.trim()));
            });
            localStorage.setItem('res-count', validRows.length);

            return totalDuration;
        }

        /**
         * Update duration display element
         */
        function updateDurationDisplay(totalDuration) {
            var durationSpan = document.getElementById('res-duration');
            if (durationSpan) {
                durationSpan.textContent = totalDuration;
            }
        }

        /**
         * Setup entry storage for edit operations
         */
        function setupEntryStorage() {
            // Handle view/edit entry links for storing adjacent dates
            var viewEntryLinks = document.querySelectorAll('.res-view-entry a, .gv-field-38-edit_link a');
            
            viewEntryLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var currentRow = e.target.closest('tr');
                    if (!currentRow) return;

                    // Get previous entry from date
                    var prevRow = currentRow.previousElementSibling;
                    while (prevRow) {
                        var prevIndexEl = prevRow.querySelector('.res-index');
                        if (prevIndexEl && !isNaN(parseInt(prevIndexEl.textContent.trim()))) break;
                        prevRow = prevRow.previousElementSibling;
                    }
                    
                    var previousEntryFrom = '';
                    if (prevRow) {
                        var fromEl = prevRow.querySelector('.res-from-date');
                        if (fromEl) previousEntryFrom = fromEl.textContent.trim();
                    }

                    // Get next entry to date
                    var nextRow = currentRow.nextElementSibling;
                    while (nextRow) {
                        var nextIndexEl = nextRow.querySelector('.res-index');
                        if (nextIndexEl && !isNaN(parseInt(nextIndexEl.textContent.trim()))) break;
                        nextRow = nextRow.nextElementSibling;
                    }
                    
                    var subsequentEntryTo = '';
                    if (nextRow) {
                        var toEl = nextRow.querySelector('.res-to-date');
                        if (toEl) subsequentEntryTo = toEl.textContent.trim();
                    }

                    localStorage.setItem('previousEntryFrom', previousEntryFrom);
                    localStorage.setItem('subsequentEntryTo', subsequentEntryTo);

                    console.log('NME Residence Dashboard: Stored adjacent dates', {
                        previousEntryFrom: previousEntryFrom,
                        subsequentEntryTo: subsequentEntryTo
                    });
                });
            });

            // Handle add button click
            var addButton = document.getElementById('res-add');
            if (addButton) {
                addButton.addEventListener('click', function() {
                    var indexElements = document.querySelectorAll('.res-index');
                    var lastIndexEl = null;
                    
                    for (var i = indexElements.length - 1; i >= 0; i--) {
                        var el = indexElements[i];
                        if (!isNaN(parseInt(el.textContent.trim()))) {
                            lastIndexEl = el;
                            break;
                        }
                    }
                    
                    var lastFromValue = '';
                    if (lastIndexEl) {
                        var lastRow = lastIndexEl.closest('tr');
                        if (lastRow) {
                            var fromEl = lastRow.querySelector('.res-from-date');
                            if (fromEl) {
                                lastFromValue = fromEl.textContent.trim();
                            }
                        }
                    }
                    
                    localStorage.setItem('previousEntryFrom', lastFromValue);
                    console.log('NME Residence Dashboard: Add button clicked, stored previousEntryFrom:', lastFromValue);
                });
            }
        }

        /**
         * Validate residency requirements and display result
         */
        function validateResidency(totalDuration) {
            var resResultDiv = document.getElementById('res-result');
            if (!resResultDiv) {
                console.log('NME Residence Dashboard: Residency validation skipped - res-result element not found');
                return;
            }

            var resRequired = window.parentEntryResRequired ? window.parentEntryResRequired.toString().trim() : null;
            var applicationDateStr = window.parentEntryApplicationDate ? window.parentEntryApplicationDate.toString().trim() : null;

            // Parse application date
            var applicationDate = parseApplicationDate(applicationDateStr);

            // Calculate lookback dates
            var computedDate = new Date(applicationDate);
            if (resRequired === 'DM' || resRequired === 'SC') {
                computedDate.setFullYear(computedDate.getFullYear() - 3);
            } else if (resRequired === 'LPRM' || resRequired === 'LPRS' || resRequired === 'LPR') {
                computedDate.setFullYear(computedDate.getFullYear() - 5);
            }

            var oneDay = 1000 * 60 * 60 * 24;
            var reqDays = Math.round((applicationDate - computedDate) / oneDay);

            // Calculate gaps between residences
            var gapTotal = calculateGaps();
            var resTime = totalDuration + gapTotal;

            // Get last 'From' date
            var lastFromDate = getLastFromDate();
            var formattedDateParsed = computedDate;

            // Display validation result
            if (resRequired === 'DM' || resRequired === 'SC') {
                if (resTime >= reqDays && lastFromDate <= formattedDateParsed) {
                    resResultDiv.innerHTML = '<p>You may continue with your application based on your marriage to your US Citizen Spouse and 3 years of residence history.</p>';
                } else {
                    resResultDiv.innerHTML = '<p>Your disclosed residence history will not be three (3) years by the time that USCIS may conduct your interview. Please continue to enter additional residences.</p>';
                }
            } else if (resRequired === 'LPRM' || resRequired === 'LPRS' || resRequired === 'LPR') {
                if (resTime >= reqDays && lastFromDate <= formattedDateParsed) {
                    resResultDiv.innerHTML = '<p>You may continue with your application based on 5 years of residence history.</p>';
                } else {
                    resResultDiv.innerHTML = '<p>Your disclosed residence history will not be five (5) years by the time that USCIS may conduct your interview. Please continue to enter additional residences.</p>';
                }
            } else {
                resResultDiv.innerHTML = '<p>An unexpected residency requirement value was encountered. Please contact us.</p>';
            }

            // 3-Month State Residency Rule Check
            check3MonthStateRule();
        }

        /**
         * Calculate total gaps between residence entries
         */
        function calculateGaps() {
            var gapTotal = 0;
            var validRows = Array.from(document.querySelectorAll('tr')).filter(function(row) {
                var indexEl = row.querySelector('.res-index');
                return indexEl && !isNaN(parseInt(indexEl.textContent.trim()));
            });

            for (var i = 0; i < validRows.length - 1; i++) {
                var currentRow = validRows[i];
                var nextRow = validRows[i + 1];

                var currentFromEl = currentRow.querySelector('.res-from-date');
                var nextToEl = nextRow.querySelector('.res-to-date');

                if (currentFromEl && nextToEl) {
                    var currentFromDate = parseDate(currentFromEl.textContent.trim());
                    var nextToDate = parseDate(nextToEl.textContent.trim());

                    if (currentFromDate && nextToDate) {
                        var gap = Math.round((currentFromDate - nextToDate) / (1000 * 60 * 60 * 24));
                        if (gap > 0) {
                            gapTotal += gap;
                        }
                    }
                }
            }

            return gapTotal;
        }

        /**
         * Get the last (oldest) from date
         */
        function getLastFromDate() {
            var indexElements = document.querySelectorAll('.res-index');
            var lastIndexEl = null;
            
            for (var i = indexElements.length - 1; i >= 0; i--) {
                var el = indexElements[i];
                if (!isNaN(parseInt(el.textContent.trim()))) {
                    lastIndexEl = el;
                    break;
                }
            }

            var lastFromValue = '';
            if (lastIndexEl) {
                var lastRow = lastIndexEl.closest('tr');
                if (lastRow) {
                    var fromEl = lastRow.querySelector('.res-from-date');
                    if (fromEl) {
                        lastFromValue = fromEl.textContent.trim();
                    }
                }
            }

            return parseDate(lastFromValue);
        }

        /**
         * Check 3-month state residency rule
         */
        function check3MonthStateRule() {
            var residenceRows = document.querySelectorAll('tbody tr');
            var thresholdDate = new Date();
            thresholdDate.setMonth(thresholdDate.getMonth() - 3);

            var mostRecentState = null;
            var lowestFromDate = null;
            var prevRowToDate = null;
            var validSequence = true;

            for (var i = 0; i < residenceRows.length; i++) {
                var row = residenceRows[i];
                var stateElem = row.querySelector('.res-state');
                var fromElem = row.querySelector('.res-from-date');
                var toElem = row.querySelector('.res-to-date');

                if (!stateElem || !fromElem || !toElem) continue;

                var state = stateElem.textContent.trim();
                var fromDate = new Date(fromElem.textContent.trim());
                var toDate = new Date(toElem.textContent.trim());

                if (mostRecentState === null) {
                    mostRecentState = state;
                    lowestFromDate = fromDate;
                    prevRowToDate = toDate;
                } else {
                    if (state !== mostRecentState) break;
                    var gapDays = (fromDate - prevRowToDate) / (1000 * 60 * 60 * 24);
                    if (gapDays > 30) {
                        validSequence = false;
                        break;
                    }
                    lowestFromDate = fromDate;
                    prevRowToDate = toDate;
                }

                if (fromDate <= thresholdDate) break;
            }

            if (!validSequence || (lowestFromDate && lowestFromDate > thresholdDate)) {
                var filingDate = new Date(lowestFromDate);
                filingDate.setMonth(filingDate.getMonth() + 3);
                alert('You must live in the same state for a minimum of 3 months before you can file. You must enter additional residences within the same state where you presently reside reaching back 3 months or more otherwise you must wait until ' + filingDate.toLocaleDateString() + ' to file');
            }
        }

        /**
         * Parse application date string
         */
        function parseApplicationDate(dateStr) {
            var applicationDate = null;
            
            if (dateStr) {
                if (dateStr.indexOf('/') !== -1) {
                    var parts = dateStr.split('/');
                    if (parts.length === 3) {
                        applicationDate = new Date(parts[2], parts[0] - 1, parts[1]);
                    }
                } else if (dateStr.indexOf('-') !== -1) {
                    var parts = dateStr.split('-');
                    if (parts.length === 3) {
                        applicationDate = new Date(parts[0], parts[1] - 1, parts[2]);
                    }
                }
            }

            if (!applicationDate || isNaN(applicationDate.getTime())) {
                console.warn('NME Residence Dashboard: No valid Application Date, falling back to today');
                applicationDate = new Date();
            }

            return applicationDate;
        }

        /**
         * Parse date string in various formats
         */
        function parseDate(str) {
            if (!str) return null;
            var parts = str.split('-');
            if (parts.length === 3) {
                return new Date(parts[0], parts[1] - 1, parts[2]);
            }
            parts = str.split('/');
            if (parts.length === 3) {
                return new Date(parts[2], parts[0] - 1, parts[1]);
            }
            return new Date(str);
        }
    }

    // Initialize when DOM is ready
    $(document).ready(function() {
        initResidenceDashboard();
    });

})(jQuery);
