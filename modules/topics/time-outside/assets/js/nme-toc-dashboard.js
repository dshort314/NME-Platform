/**
 * NME TOC Dashboard Scripts - Page 706
 * 
 * Handles:
 * - Add button URL updates (sequence, init-date, anumber, parent_entry_id)
 * - Adjacent trip date storage for edit pages
 * - Deletion handling with cascade delete support
 *
 * @package NME\Topics\TimeOutside
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('NME TOC Dashboard: Initializing');

        // ================================================================
        // Add Button URL Updates
        // ================================================================

        /**
         * Update the Add button href with current sequence and init-date
         */
        function updateTOCLink() {
            var body = document.querySelector('.gv-table-view tbody');
            var rows = body ? body.querySelectorAll('tr') : [];
            var tocsequence = 1;
            var initDate = new Date().toISOString().split('T')[0];

            if (rows.length > 0) {
                var last = rows[rows.length - 1];
                var idxCell = last.querySelector('.toc-index');

                if (idxCell && idxCell.textContent) {
                    var n = parseInt(idxCell.textContent.trim(), 10);
                    if (!isNaN(n)) {
                        tocsequence = n + 1;
                    }
                }

                // Get last departure date for init-date
                var dateCell = last.querySelector('.toc-dod') || last.querySelector('#gv-field-42-5');
                if (dateCell && dateCell.textContent) {
                    var t = dateCell.textContent.trim();
                    var p = t.split('/');
                    if (p.length === 3) {
                        var m = p[0].padStart(2, '0');
                        var d = p[1].padStart(2, '0');
                        var y = p[2];
                        initDate = y + '-' + m + '-' + d;
                    }
                }
            }

            var btn = document.getElementById('toc-add');
            if (btn) {
                var u = new URL(btn.href, window.location.origin);
                u.searchParams.set('sequence', tocsequence);
                u.searchParams.set('init-date', initDate);

                // Get user data from localized script
                if (typeof window.nmeData !== 'undefined') {
                    u.searchParams.set('anumber', window.nmeData.anumber || '');
                    u.searchParams.set('parent_entry_id', window.nmeData.parentEntryId || '');
                }

                btn.href = u.toString();

                console.log('NME TOC Dashboard: Button updated', {
                    sequence: tocsequence,
                    initDate: initDate,
                    finalUrl: u.toString()
                });
            }
        }

        // Initialize button link
        updateTOCLink();

        // ================================================================
        // Store Preceding Trip Details for Add Page
        // ================================================================

        /**
         * Store the last trip's details in localStorage for display on add page
         */
        function storePrecedingTripForAdd() {
            var body = document.querySelector('.gv-table-view tbody');
            var rows = body ? body.querySelectorAll('tr') : [];

            var precedingTripDeparture = '';
            var precedingTripReturn = '';
            var precedingTripDestination = '';

            if (rows.length > 0) {
                var last = rows[rows.length - 1];
                
                var depEl = last.querySelector('.toc-dod');
                var retEl = last.querySelector('.toc-dor');
                var destEl = last.querySelector('.toc-dest');

                if (depEl) precedingTripDeparture = depEl.textContent.trim();
                if (retEl) precedingTripReturn = retEl.textContent.trim();
                if (destEl) precedingTripDestination = destEl.textContent.trim();
            }

            localStorage.setItem('precedingTripDeparture', precedingTripDeparture);
            localStorage.setItem('precedingTripReturn', precedingTripReturn);
            localStorage.setItem('precedingTripDestination', precedingTripDestination);

            console.log('NME TOC Dashboard: Stored preceding trip for add page', {
                departure: precedingTripDeparture,
                return: precedingTripReturn,
                destination: precedingTripDestination
            });
        }

        // Store preceding trip when clicking Add button
        var addBtn = document.getElementById('toc-add');
        if (addBtn) {
            addBtn.addEventListener('click', function() {
                storePrecedingTripForAdd();
            });
        }

        // ================================================================
        // Adjacent Trip Date Storage for Edit Pages
        // ================================================================

        /**
         * Store adjacent trip dates and preceding trip details in localStorage when clicking edit links
         */
        function setupEditStorage() {
            var editLinks = document.querySelectorAll('.gv-field-42-edit_link a, .gv-field-42-custom a');

            editLinks.forEach(function(link) {
                link.addEventListener('click', function(e) {
                    var currentRow = e.target.closest('tr');
                    if (!currentRow) return;

                    // Find previous valid row (with valid index)
                    var prevRow = currentRow.previousElementSibling;
                    while (prevRow) {
                        var prevIndexEl = prevRow.querySelector('.toc-index');
                        if (prevIndexEl && !isNaN(parseInt(prevIndexEl.textContent.trim(), 10))) {
                            break;
                        }
                        prevRow = prevRow.previousElementSibling;
                    }

                    var previousTripDeparture = '';
                    var precedingTripDeparture = '';
                    var precedingTripReturn = '';
                    var precedingTripDestination = '';

                    if (prevRow) {
                        var departureEl = prevRow.querySelector('.toc-dod');
                        var returnEl = prevRow.querySelector('.toc-dor');
                        var destEl = prevRow.querySelector('.toc-dest');

                        if (departureEl) {
                            previousTripDeparture = departureEl.textContent.trim();
                            precedingTripDeparture = previousTripDeparture;
                        }
                        if (returnEl) {
                            precedingTripReturn = returnEl.textContent.trim();
                        }
                        if (destEl) {
                            precedingTripDestination = destEl.textContent.trim();
                        }
                    }

                    // Find next valid row
                    var nextRow = currentRow.nextElementSibling;
                    while (nextRow) {
                        var nextIndexEl = nextRow.querySelector('.toc-index');
                        if (nextIndexEl && !isNaN(parseInt(nextIndexEl.textContent.trim(), 10))) {
                            break;
                        }
                        nextRow = nextRow.nextElementSibling;
                    }

                    var nextTripReturn = '';
                    if (nextRow) {
                        var returnEl = nextRow.querySelector('.toc-dor');
                        if (returnEl) {
                            nextTripReturn = returnEl.textContent.trim();
                        }
                    }

                    // Store boundary dates for validation
                    localStorage.setItem('previousTripDeparture', previousTripDeparture);
                    localStorage.setItem('nextTripReturn', nextTripReturn);

                    // Store preceding trip details for display
                    localStorage.setItem('precedingTripDeparture', precedingTripDeparture);
                    localStorage.setItem('precedingTripReturn', precedingTripReturn);
                    localStorage.setItem('precedingTripDestination', precedingTripDestination);

                    console.log('NME TOC Dashboard: Stored adjacent trip dates', {
                        previousTripDeparture: previousTripDeparture,
                        nextTripReturn: nextTripReturn
                    });

                    console.log('NME TOC Dashboard: Stored preceding trip for edit page', {
                        departure: precedingTripDeparture,
                        return: precedingTripReturn,
                        destination: precedingTripDestination
                    });
                });
            });
        }

        setupEditStorage();

        // ================================================================
        // Deletion Handler
        // ================================================================

        // Remove default onclick handlers from delete links
        $('a[href*="action=delete"][href*="gvid=581"]').removeAttr('onclick');

        // Handle delete clicks with custom confirm and cascade delete
        $(document).on('click', 'a[href*="action=delete"][href*="gvid=581"]', function(e) {
            e.preventDefault();

            var link = $(this);
            var href = link.attr('href');
            var currentRow = link.closest('tr');

            // Determine if this is the last entry
            var tableRows = currentRow.closest('tbody').find('tr');
            var validRowCount = 0;
            var currentRowIndex = -1;

            tableRows.each(function(index) {
                var indexElement = $(this).find('.toc-index');
                if (indexElement.length && !isNaN(parseInt(indexElement.text().trim()))) {
                    validRowCount++;
                    if (this === currentRow[0]) {
                        currentRowIndex = validRowCount;
                    }
                }
            });

            var isLastEntry = (currentRowIndex === validRowCount);

            // Use appropriate confirm dialog based on position
            var confirmFunction = isLastEntry 
                ? window.NMEApp.TOCAlerts.showSingleDeleteConfirm 
                : window.NMEApp.TOCAlerts.showCascadeDeleteConfirm;

            confirmFunction(
                // onConfirm
                function() {
                    console.log('NME TOC Dashboard: User confirmed deletion');

                    // Show spinner overlay
                    var spinnerOverlay = document.createElement('div');
                    spinnerOverlay.id = 'spinner-overlay';
                    spinnerOverlay.style.position = 'fixed';
                    spinnerOverlay.style.top = '0';
                    spinnerOverlay.style.left = '0';
                    spinnerOverlay.style.width = '100%';
                    spinnerOverlay.style.height = '100%';
                    spinnerOverlay.style.background = 'rgba(255, 255, 255, 0.8)';
                    spinnerOverlay.style.display = 'flex';
                    spinnerOverlay.style.justifyContent = 'center';
                    spinnerOverlay.style.alignItems = 'center';
                    spinnerOverlay.style.zIndex = '10002';
                    spinnerOverlay.innerHTML = '<div class="spinner"></div>';
                    document.body.appendChild(spinnerOverlay);

                    // Add spinner styles if not present
                    if (!document.getElementById('spinner-style')) {
                        var spinnerStyle = document.createElement('style');
                        spinnerStyle.id = 'spinner-style';
                        spinnerStyle.innerHTML = '@keyframes spinner { to { transform: rotate(360deg); } } .spinner { width: 40px; height: 40px; border: 4px solid #ccc; border-top-color: #f44336; border-radius: 50%; animation: spinner 0.6s linear infinite; }';
                        document.head.appendChild(spinnerStyle);
                    }

                    if (!isLastEntry) {
                        // Cascade delete: delete this row and all subsequent rows
                        var rowsToDelete = link.closest('tr').nextAll().addBack();
                        var deleteUrls = [];

                        rowsToDelete.each(function() {
                            var delLink = $(this).find('a[href*="action=delete"][href*="gvid=581"]').first();
                            if (delLink.length) {
                                deleteUrls.push(delLink.attr('href'));
                            }
                        });

                        /**
                         * Delete entries sequentially
                         * @param {Array} urls
                         */
                        function deleteNext(urls) {
                            if (!urls.length) {
                                spinnerOverlay.remove();
                                window.location.reload();
                                return;
                            }

                            var url = urls.shift();
                            $.ajax({
                                url: url,
                                type: 'GET',
                                success: function() {
                                    console.log('NME TOC Dashboard: Deleted entry using URL:', url);
                                    deleteNext(urls);
                                },
                                error: function() {
                                    console.log('NME TOC Dashboard: Failed to delete entry using URL:', url);
                                    deleteNext(urls);
                                }
                            });
                        }

                        deleteNext(deleteUrls);
                    } else {
                        // Single delete
                        window.location.href = href;
                    }
                },
                // onCancel
                function() {
                    console.log('NME TOC Dashboard: User cancelled deletion');
                }
            );
        });

        console.log('NME TOC Dashboard: Deletion handlers configured');
    });

})(jQuery);