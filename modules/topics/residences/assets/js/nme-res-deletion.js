/**
 * NME Residence Deletion Handler - Page 705
 * 
 * Handles residence entry deletion with:
 * - Custom confirmation dialogs
 * - Cascade deletion for non-last entries
 * - Loading spinner during operations
 *
 * @package NME\Topics\Residences
 */

(function($) {
    'use strict';

    /**
     * Initialize deletion handler
     */
    function initDeletionHandler() {
        console.log('NME Residence Deletion: Setting up handlers');

        // Create custom confirm container
        createConfirmContainer();

        // Remove default onclick handlers and attach custom ones
        $('a[href*="action=delete"][href*="gvid=513"]').removeAttr('onclick');

        $(document).on('click', 'a[href*="action=delete"][href*="gvid=513"]', function(e) {
            e.preventDefault();
            var link = $(this);
            var href = link.attr('href');

            var currentRow = link.closest('tr');
            var isLastEntry = false;

            // Determine if this is the last entry
            var tableRows = currentRow.closest('tbody').find('tr');
            var validRowCount = 0;
            var currentRowIndex = -1;

            tableRows.each(function(index) {
                var indexElement = $(this).find('.res-index');
                if (indexElement.length && !isNaN(parseInt(indexElement.text().trim()))) {
                    validRowCount++;
                    if (this === currentRow[0]) {
                        currentRowIndex = validRowCount;
                    }
                }
            });

            isLastEntry = (currentRowIndex === validRowCount);

            // Custom message based on position
            var customMessage;
            if (!isLastEntry) {
                customMessage = 'Your deletion of this residence will require you to re-enter any previous residences, if any. Do you wish to continue?';
            } else {
                customMessage = 'Are you sure you want to delete this entry? This cannot be undone.';
            }

            customConfirm(customMessage).then(function(result) {
                if (result) {
                    console.log('NME Residence Deletion: User confirmed deletion');

                    // Show loading spinner
                    showSpinner();

                    if (!isLastEntry) {
                        // Cascade delete: delete this row and all subsequent rows
                        var rowsToDelete = link.closest('tr').nextAll().addBack();
                        var deleteUrls = [];

                        rowsToDelete.each(function() {
                            var delLink = $(this).find('a[href*="action=delete"][href*="gvid=513"]').first();
                            if (delLink.length) {
                                deleteUrls.push(delLink.attr('href'));
                            }
                        });

                        deleteSequentially(deleteUrls);
                    } else {
                        // Single deletion
                        window.location.href = href;
                    }
                }
            });
        });
    }

    /**
     * Create the custom confirm container
     */
    function createConfirmContainer() {
        var container = document.getElementById('custom-confirm-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'custom-confirm-container';
            container.style.position = 'fixed';
            container.style.top = '0';
            container.style.left = '0';
            container.style.width = '100%';
            container.style.height = '100%';
            container.style.pointerEvents = 'none';
            container.style.zIndex = '10000';
            document.body.appendChild(container);
        }
    }

    /**
     * Show custom confirmation dialog
     * @param {string} message - Message to display
     * @return {Promise} - Resolves to true (OK) or false (Cancel)
     */
    function customConfirm(message) {
        return new Promise(function(resolve) {
            var container = document.getElementById('custom-confirm-container');

            var confirmBox = document.createElement('div');
            confirmBox.className = 'custom-confirm-box';
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

            var okButton = document.createElement('button');
            okButton.textContent = 'OK';
            okButton.style.padding = '5px 10px';
            okButton.style.marginRight = '10px';
            okButton.style.border = 'none';
            okButton.style.borderRadius = '3px';
            okButton.style.cursor = 'pointer';
            okButton.style.backgroundColor = '#fff';
            okButton.style.color = '#f44336';
            okButton.addEventListener('click', function() {
                confirmBox.remove();
                resolve(true);
            });
            btnContainer.appendChild(okButton);

            var cancelButton = document.createElement('button');
            cancelButton.textContent = 'Cancel';
            cancelButton.style.padding = '5px 10px';
            cancelButton.style.border = 'none';
            cancelButton.style.borderRadius = '3px';
            cancelButton.style.cursor = 'pointer';
            cancelButton.style.backgroundColor = '#fff';
            cancelButton.style.color = '#f44336';
            cancelButton.addEventListener('click', function() {
                confirmBox.remove();
                resolve(false);
            });
            btnContainer.appendChild(cancelButton);

            confirmBox.appendChild(btnContainer);
            container.appendChild(confirmBox);
        });
    }

    /**
     * Show loading spinner overlay
     */
    function showSpinner() {
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

        // Add spinner style if not present
        if (!document.getElementById('spinner-style')) {
            var spinnerStyle = document.createElement('style');
            spinnerStyle.id = 'spinner-style';
            spinnerStyle.innerHTML = '@keyframes spinner { to { transform: rotate(360deg); } } .spinner { width: 40px; height: 40px; border: 4px solid #ccc; border-top-color: #f44336; border-radius: 50%; animation: spinner 0.6s linear infinite; }';
            document.head.appendChild(spinnerStyle);
        }
    }

    /**
     * Delete entries sequentially
     * @param {Array} urls - Array of deletion URLs
     */
    function deleteSequentially(urls) {
        if (!urls.length) {
            var spinner = document.getElementById('spinner-overlay');
            if (spinner) spinner.remove();
            window.location.reload();
            return;
        }

        var url = urls.shift();
        $.ajax({
            url: url,
            type: 'GET',
            success: function() {
                console.log('NME Residence Deletion: Deleted entry:', url);
                deleteSequentially(urls);
            },
            error: function() {
                console.log('NME Residence Deletion: Failed to delete:', url);
                deleteSequentially(urls);
            }
        });
    }

    // Expose customConfirm globally for potential use elsewhere
    window.customConfirm = customConfirm;

    // Initialize when DOM is ready
    $(document).ready(function() {
        initDeletionHandler();
    });

})(jQuery);
