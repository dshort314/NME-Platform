/**
 * NME TOC Dashboard Scripts - Page 706
 * 
 * Handles:
 * - Add button URL updates (sequence, init-date, anumber, parent_entry_id)
 * - Adjacent trip date storage for edit pages
 * - Custom confirm dialogs
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
        // Adjacent Trip Date Storage for Edit Pages
        // ================================================================

        /**
         * Store adjacent trip dates in localStorage when clicking edit links
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
                    if (prevRow) {
                        var departureEl = prevRow.querySelector('.toc-dod');
                        if (departureEl) {
                            previousTripDeparture = departureEl.textContent.trim();
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

                    localStorage.setItem('previousTripDeparture', previousTripDeparture);
                    localStorage.setItem('nextTripReturn', nextTripReturn);

                    console.log('NME TOC Dashboard: Stored adjacent trip dates', {
                        previousTripDeparture: previousTripDeparture,
                        nextTripReturn: nextTripReturn
                    });
                });
            });
        }

        setupEditStorage();

        // ================================================================
        // Custom Confirm Dialog
        // ================================================================

        /**
         * Show custom confirm dialog
         * @param {string} message
         * @return {Promise<boolean>}
         */
        function customConfirm(message) {
            return new Promise(function(resolve) {
                var container = document.body;

                var confirmBox = document.createElement('div');
                confirmBox.style.position = 'fixed';
                confirmBox.style.top = '50%';
                confirmBox.style.left = '50%';
                confirmBox.style.transform = 'translate(-50%, -50%)';
                confirmBox.style.padding = '20px';
                confirmBox.style.borderRadius = '8px';
                confirmBox.style.backgroundColor = '#f44336';
                confirmBox.style.color = '#fff';
                confirmBox.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.5)';
                confirmBox.style.zIndex = '10001';
                confirmBox.style.pointerEvents = 'auto';
                confirmBox.style.maxWidth = '80%';
                confirmBox.style.textAlign = 'center';

                var messageEl = document.createElement('div');
                messageEl.style.marginBottom = '15px';
                messageEl.textContent = message;
                confirmBox.appendChild(messageEl);

                var btnContainer = document.createElement('div');

                var okBtn = document.createElement('button');
                okBtn.textContent = 'OK';
                okBtn.style.padding = '5px 10px';
                okBtn.style.marginRight = '10px';
                okBtn.style.border = 'none';
                okBtn.style.borderRadius = '3px';
                okBtn.style.cursor = 'pointer';
                okBtn.style.backgroundColor = '#fff';
                okBtn.style.color = '#f44336';
                okBtn.addEventListener('click', function() {
                    confirmBox.remove();
                    resolve(true);
                });
                btnContainer.appendChild(okBtn);

                var cancelBtn = document.createElement('button');
                cancelBtn.textContent = 'Cancel';
                cancelBtn.style.padding = '5px 10px';
                cancelBtn.style.border = 'none';
                cancelBtn.style.borderRadius = '3px';
                cancelBtn.style.cursor = 'pointer';
                cancelBtn.style.backgroundColor = '#fff';
                cancelBtn.style.color = '#f44336';
                cancelBtn.addEventListener('click', function() {
                    confirmBox.remove();
                    resolve(false);
                });
                btnContainer.appendChild(cancelBtn);

                confirmBox.appendChild(btnContainer);
                container.appendChild(confirmBox);
            });
        }

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

            // Custom message based on position
            var customMessage;
            if (!isLastEntry) {
                customMessage = 'Your deletion of this trip will require you to re-enter any previous trips, if any. Do you wish to continue?';
            } else {
                customMessage = 'Are you sure you want to delete this entry? This cannot be undone.';
            }

            customConfirm(customMessage).then(function(result) {
                if (!result) return;

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
            });
        });

        console.log('NME TOC Dashboard: Deletion handlers configured');
    });

})(jQuery);
