/**
 * TOC Skip Button Injector
 *
 * Injects navigation buttons based on entry state:
 * - "No Trips Taken" - shown if NO TOC entries exist (updates Master Form and redirects to Residences)
 * - "Back" - shown if TOC entries exist (returns to dashboard)
 *
 * Also handles redirect when all TOC entries are deleted.
 *
 * @package NME\Topics\TimeOutside
 */

(function($) {
    'use strict';

    /**
     * Inject skip/cancel button based on entry state
     */
    function injectSkipButton() {
        // Only run on form 42 (TOC form) on add page
        if (!$('#gform_42').length) {
            return;
        }

        console.log('TOC Skip: Checking if button should be injected');

        // Get A-number from hidden field
        var anumber = $('#input_42_4').val();
        var parentEntryId = $('#input_42_12').val();

        if (!anumber) {
            console.log('TOC Skip: No A-number found');
            return;
        }

        // Check if nmeAjax is available
        if (typeof window.nmeAjax === 'undefined') {
            console.log('TOC Skip: nmeAjax not available');
            return;
        }

        // Check if entries exist via AJAX
        $.ajax({
            url: window.nmeAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'check_toc_entries_exist',
                anumber: anumber,
                nonce: window.nmeAjax.nonce
            },
            success: function(response) {
                console.log('TOC Skip: Response', response);

                if (response.success && !response.data.has_entries) {
                    // No entries exist - show "No Trips Taken" button
                    injectNoTripsButton(anumber, parentEntryId);
                } else {
                    // Entries exist - show "Back" button
                    injectBackButton();
                }
            },
            error: function(xhr, status, error) {
                console.error('TOC Skip: AJAX error', error);
            }
        });
    }

    /**
     * Inject "No Trips Taken" button (no entries exist)
     */
    function injectNoTripsButton(anumber, parentEntryId) {
        // Check if button already exists
        if ($('#no-trips-button').length) {
            console.log('TOC Skip: No Trips button already exists');
            return;
        }

        // Style the footer for proper alignment
        styleFormFooter();

        // Create No Trips button
        var noTripsButton = $('<a/>', {
            href: '#',
            id: 'no-trips-button',
            'class': 'gvx-nav-btn gform_button button',
            text: 'No Trips Taken',
            css: {
                'margin-right': '10px',
                'line-height': '13px'
            }
        });

        // Inject button before the Submit button
        $('.gform_footer #gform_submit_button_42').before(noTripsButton);

        console.log('TOC Skip: No Trips button injected - no entries exist');

        // Handle click
        noTripsButton.on('click', function(e) {
            e.preventDefault();
            handleNoTripsClick(anumber, parentEntryId);
        });
    }

    /**
     * Handle No Trips button click
     */
    function handleNoTripsClick(anumber, parentEntryId) {
        // Get lookback date for confirmation message
        var lookbackDate = window.tocLookbackStartDateFormatted || 'the filing period start date';

        // Show confirmation modal
        if (typeof window.NMEApp !== 'undefined' && window.NMEApp.TOCAlerts) {
            window.NMEApp.TOCAlerts.showNoTripsConfirm(
                lookbackDate,
                function() {
                    // On confirm - call AJAX to set No Trips and get redirect URL
                    setNoTripsAndRedirect(anumber, parentEntryId);
                },
                function() {
                    // On cancel - do nothing
                    console.log('TOC Skip: No Trips cancelled');
                }
            );
        } else {
            // Fallback if TOCAlerts not available
            NMEModal.confirm({
                title: 'No Trips Outside US',
                message: 'Are you sure you have not traveled outside the United States since ' + lookbackDate + '?',
                confirmText: 'Yes, No Trips',
                cancelText: 'Cancel',
                onConfirm: function() {
                    setNoTripsAndRedirect(anumber, parentEntryId);
                }
            });
        }
    }

    /**
     * Call AJAX to set No Trips flag and redirect to Residences
     */
    function setNoTripsAndRedirect(anumber, parentEntryId) {
        $.ajax({
            url: window.nmeAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'set_no_trips',
                anumber: anumber,
                parent_entry_id: parentEntryId,
                nonce: window.nmeAjax.nonce
            },
            success: function(response) {
                console.log('TOC Skip: Set No Trips response', response);

                if (response.success && response.data.redirect_url) {
                    window.location.href = response.data.redirect_url;
                } else {
                    console.error('TOC Skip: Failed to set No Trips', response);
                    alert('An error occurred. Please try again.');
                }
            },
            error: function(xhr, status, error) {
                console.error('TOC Skip: AJAX error setting No Trips', error);
                alert('An error occurred. Please try again.');
            }
        });
    }

    /**
     * Inject "Back" button (entries exist)
     */
    function injectBackButton() {
        // Check if button already exists
        if ($('#toc-back-button').length) {
            console.log('TOC Skip: Back button already exists');
            return;
        }

        // Style the footer for proper alignment
        styleFormFooter();

        // Create Back button
        var backButton = $('<a/>', {
            href: '/application/time-outside-the-us-view/',
            id: 'toc-back-button',
            'class': 'gvx-nav-btn gform_button button',
            text: 'Back',
            css: {
                'margin-right': '10px',
                'line-height': '13px'
            }
        });

        // Inject before Submit button
        $('.gform_footer #gform_submit_button_42').before(backButton);

        console.log('TOC Skip: Back button injected - entries exist');
    }

    /**
     * Style form footer for proper button alignment
     */
    function styleFormFooter() {
        $('.gform_footer').css({
            'display': 'flex',
            'align-items': 'center',
            'justify-content': 'flex-start'
        });

        // Push Submit button to the right
        $('#gform_submit_button_42').css('margin-left', 'auto');
    }

    /**
     * Inject "No Trips Taken" button on dashboard when no entries exist
     */
    function injectNoTripsButtonOnDashboard() {
        // Check if we're on the dashboard page
        if (window.location.pathname.indexOf('time-outside-the-us-view') === -1) {
            return;
        }

        // Count visible TOC entry rows
        var entryRows = $('.gv-table-view tbody tr').filter(function() {
            return $(this).find('.toc-index').length > 0;
        });

        console.log('TOC Skip: Dashboard has ' + entryRows.length + ' entry rows');

        // Only show No Trips button if no entries exist
        if (entryRows.length > 0) {
            return;
        }

        // Check if button already exists
        if ($('#no-trips-dashboard-button').length) {
            return;
        }

        // Get anumber and parent_entry_id from data attributes or nmeData
        var anumber = '';
        var parentEntryId = '';

        if (typeof window.nmeData !== 'undefined') {
            anumber = window.nmeData.anumber || '';
            parentEntryId = window.nmeData.parentEntryId || '';
        }

        if (!anumber || !parentEntryId) {
            console.log('TOC Skip: Missing anumber or parentEntryId for dashboard button');
            return;
        }

        // Find a good place to inject the button (near the Add button)
        var addButton = $('#toc-add');
        if (!addButton.length) {
            console.log('TOC Skip: Could not find Add button on dashboard');
            return;
        }

        // Create No Trips button
        var noTripsButton = $('<a/>', {
            href: '#',
            id: 'no-trips-dashboard-button',
            'class': 'gvx-nav-btn gform_button button',
            text: 'No Trips Taken',
            css: {
                'margin-left': '10px'
            }
        });

        // Inject button after the Add button
        addButton.after(noTripsButton);

        console.log('TOC Skip: No Trips button injected on dashboard');

        // Handle click
        noTripsButton.on('click', function(e) {
            e.preventDefault();
            handleNoTripsClick(anumber, parentEntryId);
        });
    }

    /**
     * Handle redirect when all TOC entries are deleted
     * Monitors the dashboard for successful deletions
     */
    function setupDeletionRedirect() {
        console.log('TOC Skip: Setting up deletion redirect handler');

        // Watch for deletion clicks
        $(document).on('click', 'a[href*="action=delete"][href*="gvid=581"]', function() {
            // After deletion completes, check if any entries remain
            setTimeout(function() {
                checkAndRedirectIfEmpty();
            }, 1000);
        });

        // Also check on page load in case we just deleted the last one
        checkAndRedirectIfEmpty();
    }

    /**
     * Check if TOC entries exist, show No Trips button if none exist
     */
    function checkAndRedirectIfEmpty() {
        // Count visible TOC entry rows
        var entryRows = $('.gv-table-view tbody tr').filter(function() {
            return $(this).find('.toc-index').length > 0;
        });

        console.log('TOC Skip: Found ' + entryRows.length + ' entry rows');

        if (entryRows.length === 0) {
            console.log('TOC Skip: No entries found, injecting No Trips button');
            injectNoTripsButtonOnDashboard();
        }
    }

    // ================================================================
    // Initialization
    // ================================================================

    // Run when Gravity Forms renders
    $(document).on('gform_post_render', function(event, formId) {
        if (formId === 42) {
            injectSkipButton();
        }
    });

    // Also run on page load
    $(document).ready(function() {
        injectSkipButton();

        // Set up for dashboard page (706)
        if (window.location.pathname.indexOf('time-outside-the-us-view') !== -1) {
            setupDeletionRedirect();
            injectNoTripsButtonOnDashboard();
        }
    });

})(jQuery);