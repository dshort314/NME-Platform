/**
 * TOC Skip Button Injector
 *
 * Injects navigation buttons based on entry state:
 * - "Skip to Residences" - shown if NO TOC entries exist (allows skipping TOC)
 * - "Cancel" - shown if TOC entries exist (returns to dashboard)
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
        // Only run on form 42 (TOC form)
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
                    // No entries exist - show "Skip to Residences" button
                    injectSkipToResidencesButton();
                } else {
                    // Entries exist - show "Cancel" button
                    injectCancelButton();
                }
            },
            error: function(xhr, status, error) {
                console.error('TOC Skip: AJAX error', error);
            }
        });
    }

    /**
     * Inject "Skip to Residences" button (no entries exist)
     */
    function injectSkipToResidencesButton() {
        // Check if button already exists
        if ($('#skip-to-residences-button').length) {
            console.log('TOC Skip: Skip button already exists');
            return;
        }

        // Style the footer for proper alignment
        styleFormFooter();

        // Create Skip button
        var skipButton = $('<a/>', {
            href: '#',
            id: 'skip-to-residences-button',
            'data-nme-nav': 'residences',
            'class': 'gvx-nav-btn gform_button button',
            text: 'Skip to Residences',
            css: {
                'margin-right': '10px',
                'line-height': '13px'
            }
        });

        // Inject button before the Submit button
        $('.gform_footer #gform_submit_button_42').before(skipButton);

        console.log('TOC Skip: Skip button injected - no entries exist');

        // Trigger navigation script to process the Skip button
        if (typeof window.NMENavigation !== 'undefined' && window.NMENavigation.setupButton) {
            setTimeout(function() {
                window.NMENavigation.setupButton($('#skip-to-residences-button'));
            }, 100);
        }
    }

    /**
     * Inject "Cancel" button (entries exist)
     */
    function injectCancelButton() {
        // Check if button already exists
        if ($('#toc-cancel-button').length) {
            console.log('TOC Skip: Cancel button already exists');
            return;
        }

        // Style the footer for proper alignment
        styleFormFooter();

        // Create Cancel button
        var cancelButton = $('<a/>', {
            href: '/application/time-outside-the-us-view/',
            id: 'toc-cancel-button',
            'class': 'gvx-nav-btn gform_button button',
            text: 'Cancel',
            css: {
                'margin-right': '10px',
                'line-height': '13px'
            }
        });

        // Inject before Submit button
        $('.gform_footer #gform_submit_button_42').before(cancelButton);

        console.log('TOC Skip: Cancel button injected - entries exist');
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
     * Check if TOC entries exist, redirect to add form if none exist
     */
    function checkAndRedirectIfEmpty() {
        // Count visible TOC entry rows
        var entryRows = $('.gv-table-view tbody tr').filter(function() {
            return $(this).find('.toc-index').length > 0;
        });

        console.log('TOC Skip: Found ' + entryRows.length + ' entry rows');

        if (entryRows.length === 0) {
            console.log('TOC Skip: No entries found, redirecting to add form');

            // Get URL from button with data-nme-nav="time-outside"
            var tocButton = $('[data-nme-nav="time-outside"]');

            if (tocButton.length && tocButton.attr('href') && tocButton.attr('href') !== '#') {
                var redirectUrl = tocButton.attr('href');
                console.log('TOC Skip: Redirecting to:', redirectUrl);
                window.location.href = redirectUrl;
            } else {
                // Fallback: construct URL manually
                var anumber = $('[data-nme-nav="time-outside"]').data('anumber') ||
                    $('.gv-table-view').data('anumber') ||
                    '';
                var parentEntryId = $('[data-nme-nav="time-outside"]').data('parent-entry-id') ||
                    $('.gv-table-view').data('parent-entry-id') ||
                    '';

                if (anumber && parentEntryId) {
                    var redirectUrl = '/application/time-outside-the-us/?sequence=1&anumber=' + anumber + '&parent_entry_id=' + parentEntryId;
                    console.log('TOC Skip: Redirecting to (fallback):', redirectUrl);
                    window.location.href = redirectUrl;
                }
            }
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

        // Set up deletion redirect handler on dashboard page (706)
        if (window.location.pathname.indexOf('time-outside-the-us-view') !== -1) {
            setupDeletionRedirect();
        }
    });

})(jQuery);
