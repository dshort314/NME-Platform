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

        /**
         * Display modal alert
         * @param {string} html - HTML content
         * @param {boolean} isError - Red if true, green if false
         */
        function modal(html, isError) {
            const w = document.body;
            const b = document.createElement('div');
            b.style.position = 'fixed';
            b.style.inset = '0';
            b.style.display = 'flex';
            b.style.alignItems = 'center';
            b.style.justifyContent = 'center';
            b.style.zIndex = '10000';
            b.style.backgroundColor = 'rgba(0, 0, 0, 0.3)';

            const i = document.createElement('div');
            i.style.maxWidth = '720px';
            i.style.padding = '20px';
            i.style.borderRadius = '8px';
            i.style.background = isError ? '#f44336' : '#2e7d32';
            i.style.color = '#fff';
            i.style.boxShadow = '0 10px 30px rgba(0,0,0,.3)';
            i.innerHTML = '<div style="margin-bottom:12px;line-height:1.4;">' + html + '</div>';

            const ok = document.createElement('button');
            ok.textContent = 'OK';
            ok.style.background = '#fff';
            ok.style.color = isError ? '#f44336' : '#2e7d32';
            ok.style.border = '0';
            ok.style.borderRadius = '4px';
            ok.style.padding = '6px 12px';
            ok.style.cursor = 'pointer';
            ok.style.fontWeight = 'bold';
            ok.addEventListener('click', function() {
                w.removeChild(b);
            });

            i.appendChild(ok);
            b.appendChild(i);
            w.appendChild(b);
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

                console.log('NME TOC Finish: Row elements', {
                    depEl: depEl ? depEl.textContent.trim() : 'not found',
                    retEl: retEl ? retEl.textContent.trim() : 'not found',
                    idxEl: idxEl ? idxEl.textContent.trim() : 'not found'
                });

                if (!depEl || !retEl || !idxEl) return;

                const dep = parseMDY(depEl.textContent.trim());
                const ret = parseMDY(retEl.textContent.trim());
                if (!dep || !ret) return;

                trips.push({
                    i: parseInt(idxEl.textContent.trim(), 10) || 0,
                    from: dep,
                    to: ret
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
                    longTrips.push(t.i);
                }

                // Check for overlaps with subsequent trips
                for (var y = x + 1; y < trips.length; y++) {
                    var u = trips[y];
                    if (t.from <= u.to && t.to >= u.from) {
                        overlaps.push({ a: t.i, b: u.i });
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
                msg += '<p><strong>Warning:</strong> The following trips exceed 6 months: Row ' + longTrips.join(', Row ') + '</p>';

                // Find the most recent trip that exceeds 6 months
                var mostRecentLongTrip = null;
                for (var i = 0; i < trips.length; i++) {
                    if (longTrips.indexOf(trips[i].i) !== -1) {
                        if (!mostRecentLongTrip || trips[i].to > mostRecentLongTrip.to) {
                            mostRecentLongTrip = trips[i];
                        }
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
                    msg += '<li>Row ' + p.a + ' overlaps with Row ' + p.b + '</li>';
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

            // Display result
            if (hasIssues) {
                modal(msg, true);
                return false;
            }

            modal('<p>You may proceed with your application.</p>', false);
            return true;
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
