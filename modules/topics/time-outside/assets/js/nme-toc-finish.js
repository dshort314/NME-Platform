/**
 * NME TOC Finish Button Evaluation - Page 706
 * 
 * Evaluates trips when user clicks "Finish" button:
 * - 6+ month duration detection (continuous residence disruption)
 * - Overlapping trips detection
 * - Physical presence calculation
 *
 * @package NME\Topics\TimeOutside
 */

(function() {
    'use strict';

    function initTOCFinish() {
        console.log('NME TOC Finish: Script loaded');

        // ================================================================
        // Helper Functions
        // ================================================================

        /**
         * Parse date string in MM/DD/YYYY format
         * @param {string} s - Date string
         * @return {Date|null}
         */
        function parseMDY(s) {
            if (!s) return null;
            const p = s.split('/');
            if (p.length !== 3) return null;
            const m = +p[0], d = +p[1], y = +p[2];
            if (!m || !d || !y) return null;
            return new Date(y, m - 1, d);
        }

        /**
         * Calculate days between dates - INCLUSIVE of both start and end dates
         * Example: Jan 1 to Jan 3 = 3 days (Jan 1, Jan 2, Jan 3)
         * @param {Date} a - Start date
         * @param {Date} b - End date
         * @return {number}
         */
        function daysBetween(a, b) {
            if (!a || !b) return 0;
            const MS_PER_DAY = 1000 * 60 * 60 * 24;
            const utc1 = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
            const utc2 = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
            const diffDays = Math.floor((utc2 - utc1) / MS_PER_DAY);
            return diffDays + 1; // +1 to include both start and end day
        }

        /**
         * Calculate days in filing window - from start date up to but NOT including today
         * @param {Date} a - Start date
         * @param {Date} b - End date
         * @return {number}
         */
        function daysInWindow(a, b) {
            if (!a || !b) return 0;
            const MS_PER_DAY = 1000 * 60 * 60 * 24;
            const utc1 = Date.UTC(a.getFullYear(), a.getMonth(), a.getDate());
            const utc2 = Date.UTC(b.getFullYear(), b.getMonth(), b.getDate());
            return Math.floor((utc2 - utc1) / MS_PER_DAY);
        }

        /**
         * Format days as years, months, days string
         * @param {number} days
         * @return {string}
         */
        function formatDaysAsYMD(days) {
            const y = Math.floor(days / 365);
            const m = Math.floor((days % 365) / 30);
            const d = (days % 365) % 30;
            const parts = [];
            if (y) parts.push(y + ' year' + (y !== 1 ? 's' : ''));
            if (m) parts.push(m + ' month' + (m !== 1 ? 's' : ''));
            if (d) parts.push(d + ' day' + (d !== 1 ? 's' : ''));
            return parts.join(', ') || '0 days';
        }

        // ================================================================
        // Trip Collection
        // ================================================================

        /**
         * Collect all trips from the dashboard table
         * @return {Array}
         */
        function collectTrips() {
            const rows = document.querySelectorAll('.gv-table-view tbody tr');
            console.log('NME TOC Finish: Found ' + rows.length + ' rows');

            const trips = [];
            rows.forEach(function(tr) {
                const depEl = tr.querySelector('.toc-dod');
                const retEl = tr.querySelector('.toc-dor');
                const idxEl = tr.querySelector('.toc-index');
                const destEl = tr.querySelector('.toc-dest');

                console.log('NME TOC Finish: Row elements', {
                    depEl: depEl ? depEl.textContent.trim() : 'not found',
                    retEl: retEl ? retEl.textContent.trim() : 'not found',
                    idxEl: idxEl ? idxEl.textContent.trim() : 'not found',
                    destEl: destEl ? destEl.textContent.trim() : 'not found'
                });

                if (!depEl || !retEl || !idxEl) return;

                const dep = parseMDY(depEl.textContent.trim());
                const ret = parseMDY(retEl.textContent.trim());
                if (!dep || !ret) return;

                trips.push({
                    i: parseInt(idxEl.textContent.trim(), 10) || 0,
                    from: dep,
                    to: ret,
                    destination: destEl ? destEl.textContent.trim() : 'Unknown',
                    fromFormatted: depEl.textContent.trim(),
                    toFormatted: retEl.textContent.trim()
                });
            });

            // Sort by departure date (most recent first)
            trips.sort(function(a, b) {
                return b.from - a.from;
            });

            console.log('NME TOC Finish: Collected ' + trips.length + ' trips', trips);
            return trips;
        }

        // ================================================================
        // Main Evaluation
        // ================================================================

        /**
         * Evaluate TOC data and display results
         */
        function evaluateTOCData() {
            console.log('NME TOC Finish: Starting evaluation');

            const trips = collectTrips();
            const rr = (window.parentEntryResRequired || '').toString().trim();
            console.log('NME TOC Finish: Residence requirement:', rr);

            // Determine filing period (3 or 5 years)
            const years = (rr === 'DM' || rr === 'SC') ? 3 : 5;
            const today = new Date();
            const start = new Date(today);
            start.setFullYear(today.getFullYear() - years);

            console.log('NME TOC Finish: Filing period', {
                years: years,
                start: start.toLocaleDateString(),
                today: today.toLocaleDateString()
            });

            // Check for long trips (6+ months) and overlapping trips
            var longTrips = [];
            var overlaps = [];

            for (var x = 0; x < trips.length; x++) {
                var t = trips[x];
                var len = daysBetween(t.from, t.to);
                console.log('NME TOC Finish: Trip', t.i, 'duration:', len, 'days');

                // 183 days = approximately 6 months
                if (len >= 183) {
                    longTrips.push(t);
                }

                // Check for overlaps with subsequent trips
                for (var y = x + 1; y < trips.length; y++) {
                    var u = trips[y];
                    if (t.from <= u.to && t.to >= u.from) {
                        overlaps.push({ a: t, b: u });
                    }
                }
            }

            // Physical presence calculation
            var required = (years === 3) ? 548 : 913;
            var abroad = 0;

            trips.forEach(function(t) {
                var from = (t.from < start) ? start : t.from;
                var to = (t.to > today) ? today : t.to;
                if (to >= start && from <= today) {
                    var tripDays = daysBetween(from, to);
                    abroad += tripDays;
                }
            });

            var windowDays = daysInWindow(start, today);
            var present = Math.max(0, windowDays - abroad);

            console.log('NME TOC Finish: Physical presence calculation', {
                required: required,
                windowDays: windowDays,
                abroad: abroad,
                present: present
            });

            // Build result message
            var hasIssues = false;
            var msg = '';

            // Check for trips exceeding 6 months
            if (longTrips.length) {
                hasIssues = true;
                
                // Build list of long trips with details
                var tripDetails = longTrips.map(function(t) {
                    return 'trip to ' + t.destination + ' from ' + t.fromFormatted + ' to ' + t.toFormatted;
                });
                
                msg += '<p><strong>Warning:</strong> The following trips exceed 6 months: ' + tripDetails.join('; ') + '</p>';

                // Find the most recent trip that exceeds 6 months
                var mostRecentLongTrip = null;
                for (var i = 0; i < longTrips.length; i++) {
                    if (!mostRecentLongTrip || longTrips[i].to > mostRecentLongTrip.to) {
                        mostRecentLongTrip = longTrips[i];
                    }
                }

                if (mostRecentLongTrip) {
                    // Day after return
                    var dayAfterReturn = new Date(mostRecentLongTrip.to);
                    dayAfterReturn.setDate(dayAfterReturn.getDate() + 1);

                    // Formula: (Day after return + filing period) - 3 months
                    var filingDate = new Date(dayAfterReturn);
                    filingDate.setFullYear(filingDate.getFullYear() + years);
                    filingDate.setMonth(filingDate.getMonth() - 3);

                    msg += '<p>Because you have a trip that exceeds 6 months, continuous residence has been disrupted. You will need to wait until <strong>' +
                        filingDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' }) +
                        '</strong> to file your application.</p>';
                }
            }

            // Check for overlapping trips
            if (overlaps.length) {
                hasIssues = true;
                msg += '<p><strong>Warning:</strong> Overlapping trips detected:</p><ul>';
                overlaps.forEach(function(p) {
                    msg += '<li>Trip to ' + p.a.destination + ' (' + p.a.fromFormatted + ' - ' + p.a.toFormatted + ') overlaps with trip to ' + p.b.destination + ' (' + p.b.fromFormatted + ' - ' + p.b.toFormatted + ')</li>';
                });
                msg += '</ul>';
            }

            // Check physical presence requirement
            if (present < required) {
                hasIssues = true;
                var daysShort = required - present;
                var formattedDays = formatDaysAsYMD(daysShort);
                var futureDate = new Date();
                futureDate.setDate(futureDate.getDate() + daysShort);
                var formattedDate = futureDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });

                msg += '<p>You have not been physically present in the US for the required time in the last ' + years + ' years. You are short by ' + daysShort + ' days (' + formattedDays + '). You will have to wait to file until ' + formattedDate + '.</p>';
            }

            console.log('NME TOC Finish: Evaluation complete', {
                hasIssues: hasIssues,
                longTrips: longTrips,
                overlaps: overlaps
            });

            // Display result using NMEModal via TOCAlerts
            if (hasIssues) {
                window.NMEApp.TOCAlerts.showEvaluationError(msg, function() {
                    redirectToResidences();
                });
                return false;
            }

            var successMsg = '<p>The trips you have taken outside the United States do not adversely impact the timing of your application. You may continue preparing your application.</p>';
            window.NMEApp.TOCAlerts.showEvaluationSuccess(successMsg, function() {
                redirectToResidences();
            });
            return true;
        }

        /**
         * Redirect to Residences page via AJAX
         */
        function redirectToResidences() {
            // Get anumber and parent_entry_id from nmeData
            var anumber = '';
            var parentEntryId = '';

            if (typeof window.nmeData !== 'undefined') {
                anumber = window.nmeData.anumber || '';
                parentEntryId = window.nmeData.parentEntryId || '';
            }

            if (!anumber || !parentEntryId) {
                console.error('NME TOC Finish: Missing anumber or parentEntryId for redirect');
                return;
            }

            // Call AJAX to get redirect URL
            var xhr = new XMLHttpRequest();
            xhr.open('POST', window.nmeAjax.ajaxurl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success && response.data.redirect_url) {
                                window.location.href = response.data.redirect_url;
                            } else {
                                console.error('NME TOC Finish: Failed to get redirect URL', response);
                            }
                        } catch (e) {
                            console.error('NME TOC Finish: Error parsing response', e);
                        }
                    } else {
                        console.error('NME TOC Finish: AJAX error', xhr.status);
                    }
                }
            };

            var params = 'action=get_residences_redirect&anumber=' + encodeURIComponent(anumber) +
                '&parent_entry_id=' + encodeURIComponent(parentEntryId) +
                '&nonce=' + encodeURIComponent(window.nmeAjax.nonce);
            xhr.send(params);
        }

        // ================================================================
        // Button Binding
        // ================================================================

        /**
         * Find the Finish button
         * @return {Element|null}
         */
        function findFinishButton() {
            // Try explicit ID first
            var explicit = document.querySelector('#finish-button');
            if (explicit) {
                console.log('NME TOC Finish: Found finish button by ID #finish-button');
                return explicit;
            }

            // Try alternate selectors
            var finishBtn = document.querySelector('#finish, #toc-finish, [data-toc-finish]');
            if (finishBtn) {
                console.log('NME TOC Finish: Found finish button by alternate selector');
                return finishBtn;
            }

            // Search by text content
            var buttons = document.querySelectorAll('button, a');
            for (var i = 0; i < buttons.length; i++) {
                if ((buttons[i].textContent || '').trim().toLowerCase() === 'finish') {
                    console.log('NME TOC Finish: Found finish button by text content');
                    return buttons[i];
                }
            }

            console.log('NME TOC Finish: No finish button found');
            return null;
        }

        // Bind finish button
        var btn = findFinishButton();
        if (btn) {
            console.log('NME TOC Finish: Binding click event to finish button');
            btn.addEventListener('click', function(e) {
                console.log('NME TOC Finish: Button clicked');
                e.preventDefault();
                evaluateTOCData();
            });
        } else {
            console.error('NME TOC Finish: Could not find finish button!');
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTOCFinish);
    } else {
        initTOCFinish();
    }

})();