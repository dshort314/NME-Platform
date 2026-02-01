/**
 * NME Platform Navigation
 * Handles conditional links based on Gravity Form entries
 * Integrates with Access Control for eligibility lockouts
 */
jQuery(document).ready(function($) {
    
    // Bail if nmeNavigation is not defined
    if (typeof nmeNavigation === 'undefined') {
        console.log('NME Navigation: nmeNavigation object not found');
        return;
    }
    
    /**
     * Main setup function for conditional links
     */
    function setupConditionalLinks() {
        var currentUserId = nmeNavigation.userid;
        var anumber = nmeNavigation.anumber;
        var parentEntryId = nmeNavigation.parent_entry_id;
        var isLocked = nmeNavigation.is_locked;
        
        if (!currentUserId) {
            console.log('NME Navigation: No user ID found');
            return;
        }
        
        console.log('NME Navigation: Setting up conditional links', {
            userId: currentUserId,
            anumber: anumber,
            parentEntryId: parentEntryId,
            isLocked: isLocked
        });
        
        // If user is locked out, disable restricted buttons
        if (isLocked) {
            console.log('NME Navigation: User is locked out until ' + nmeNavigation.unlock_date_formatted);
            disableRestrictedButtons();
            return;
        }
        
        // First, check if Information About You entry exists - this controls all other buttons
        if (!anumber || anumber === '') {
            console.log('NME Navigation: No A-Number exists, disabling all buttons except Information About You');
            
            // No A-Number exists, only Information About You button should work
            $('#iay-button').attr('href', '/application/information-about-you/');
            
            // Disable all other buttons
            disableOtherButtons();
            return;
        }
        
        // A-Number exists, now check if Information About You entry exists
        console.log('NME Navigation: A-Number exists, checking Information About You entry');
        
        $.ajax({
            url: nmeNavigation.ajaxurl,
            type: 'POST',
            data: {
                action: 'nme_check_form_entry',
                nonce: nmeNavigation.nonce,
                form_id: 70,
                field_id: 10,
                anumber: anumber
            },
            success: function(response) {
                console.log('NME Navigation: Information About You check response', response);
                if (response.success) {
                    if (response.data.entry_exists) {
                        console.log('NME Navigation: Information About You entry exists, enabling all buttons');
                        // Information About You entry exists, set it to view page
                        $('#iay-button').attr('href', '/application/information-about-you-view/');
                        
                        // Now enable and configure all other buttons
                        setupOtherButtons(anumber, parentEntryId);
                    } else {
                        console.log('NME Navigation: Information About You entry does not exist, disabling other buttons');
                        // No Information About You entry exists, link to form page
                        $('#iay-button').attr('href', '/application/information-about-you/');
                        
                        // Disable all other buttons
                        disableOtherButtons();
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('NME Navigation: Error checking Information About You entry', error);
                $('#iay-button').attr('href', '/application/information-about-you/');
                disableOtherButtons();
            }
        });
    }
    
    /**
     * Disable all buttons that are marked as restricted (for locked users)
     */
    function disableRestrictedButtons() {
        var restrictedButtons = $('.nme-nav-button[data-restricted="true"]');
        
        restrictedButtons.each(function() {
            var button = $(this);
            button.removeAttr('href');
            button.addClass('disabled locked');
            button.attr('aria-disabled', 'true');
            button.css({
                'opacity': '0.5',
                'cursor': 'not-allowed',
                'pointer-events': 'none'
            });
            
            // Add click handler to redirect to purgatory
            button.on('click', function(e) {
                e.preventDefault();
                window.location.href = nmeNavigation.purgatory_url;
                return false;
            });
        });
        
        // Documents button should remain active
        var documentsButton = $('#documents-button');
        if (documentsButton.length > 0) {
            documentsButton.removeClass('disabled locked');
            documentsButton.removeAttr('aria-disabled');
            documentsButton.css({
                'opacity': '1',
                'cursor': 'pointer',
                'pointer-events': 'auto'
            });
        }
    }
    
    /**
     * Disable all buttons except Information About You
     */
    function disableOtherButtons() {
        var otherButtons = [
            '#time-outside-button',
            '#residences-button',
            '#marital-history-button',
            '#children-button',
            '#employment-school-button',
            '#additional-information-button',
            '#documents-button'
        ];
        
        otherButtons.forEach(function(buttonSelector) {
            var button = $(buttonSelector);
            if (button.length > 0) {
                button.removeAttr('href');
                button.css({
                    'opacity': '0.5',
                    'cursor': 'not-allowed',
                    'pointer-events': 'none'
                });
            }
        });
    }
    
    /**
     * Setup other buttons after Information About You entry is confirmed
     */
    function setupOtherButtons(anumber, parentEntryId) {
        console.log('NME Navigation: Setting up other buttons');
        
        // Buttons with conditional form/view URLs
        var conditionalButtons = [
            {
                selector: '#time-outside-button',
                formId: 42,
                fieldId: 4,
                viewUrl: '/application/time-outside-the-us-view/',
                formUrl: '/application/time-outside-the-us/'
            },
            {
                selector: '#residences-button',
                formId: 38,
                fieldId: 1,
                viewUrl: '/application/residence-view/',
                formUrl: '/application/residences/',
                addEndDate: true
            },
            {
                selector: '#children-button',
                formId: 72,
                fieldId: 3,
                viewUrl: '/application/children-view/',
                formUrl: '/application/children/'
            },
            {
                selector: '#employment-school-button',
                formId: 73,
                fieldId: 3,
                viewUrl: '/application/employment-school-view/',
                formUrl: '/application/employment-school/'
            },
            {
                selector: '#additional-information-button',
                formId: 39,
                fieldId: 1,
                viewUrl: '/application/additional-information-view/',
                formUrl: '/application/additional-information/'
            }
        ];
        
        conditionalButtons.forEach(function(buttonConfig) {
            var button = $(buttonConfig.selector);
            if (button.length > 0) {
                console.log('NME Navigation: Checking entry for button', buttonConfig.selector);
                
                $.ajax({
                    url: nmeNavigation.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nme_check_form_entry',
                        nonce: nmeNavigation.nonce,
                        form_id: buttonConfig.formId,
                        field_id: buttonConfig.fieldId,
                        anumber: anumber
                    },
                    success: function(response) {
                        console.log('NME Navigation: Response for', buttonConfig.selector, response);
                        if (response.success) {
                            if (response.data.entry_exists) {
                                // Entry exists, link to View page
                                console.log('NME Navigation: Entry exists for', buttonConfig.selector, '- linking to view');
                                button.attr('href', buttonConfig.viewUrl);
                            } else {
                                // No entry exists, link to Form page with query parameters
                                console.log('NME Navigation: No entry for', buttonConfig.selector, '- linking to form with params');
                                var finalFormUrl = buildFormUrl(buttonConfig, anumber, parentEntryId);
                                button.attr('href', finalFormUrl);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('NME Navigation: Error checking entry for', buttonConfig.selector, error);
                        button.attr('href', buttonConfig.formUrl);
                    }
                });
            }
        });
        
        // Buttons that just need query parameters (no view URL)
        var simpleButtons = [
            {
                selector: '#marital-history-button',
                formId: 71,
                fieldId: 7,
                basePath: '/application/marital-history/'
            },
            {
                selector: '#documents-button',
                formId: 0,
                fieldId: 0,
                basePath: '/application/documents/'
            }
        ];
        
        simpleButtons.forEach(function(buttonConfig) {
            var button = $(buttonConfig.selector);
            console.log('NME Navigation: Processing button', buttonConfig.selector);
            
            if (button.length > 0) {
                // Skip buttons that do not have form IDs configured
                if (buttonConfig.formId === 0 || buttonConfig.fieldId === 0) {
                    console.log('NME Navigation: Skipping button (no form ID)', buttonConfig.selector);
                    var href = addQueryParams(buttonConfig.basePath, anumber, parentEntryId);
                    button.attr('href', href);
                    return;
                }
                
                console.log('NME Navigation: Checking entry for', buttonConfig.selector);
                
                $.ajax({
                    url: nmeNavigation.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nme_check_form_entry',
                        nonce: nmeNavigation.nonce,
                        form_id: buttonConfig.formId,
                        field_id: buttonConfig.fieldId,
                        anumber: anumber
                    },
                    success: function(response) {
                        console.log('NME Navigation: AJAX Response for', buttonConfig.selector, response);
                        if (response.success) {
                            if (response.data.entry_exists) {
                                // Entry exists, no query parameters needed
                                console.log('NME Navigation: Entry EXISTS for', buttonConfig.selector);
                                button.attr('href', buttonConfig.basePath);
                            } else {
                                // No entry exists, add query parameters
                                console.log('NME Navigation: Entry DOES NOT EXIST for', buttonConfig.selector);
                                var href = addQueryParams(buttonConfig.basePath, anumber, parentEntryId);
                                button.attr('href', href);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('NME Navigation: AJAX Error for', buttonConfig.selector, error);
                        var href = addQueryParams(buttonConfig.basePath, anumber, parentEntryId);
                        button.attr('href', href);
                    }
                });
            }
        });
    }
    
    /**
     * Build form URL with query parameters
     */
    function buildFormUrl(buttonConfig, anumber, parentEntryId) {
        var queryParams = new URLSearchParams();
        queryParams.append('sequence', '1');
        queryParams.append('anumber', anumber);
        
        if (parentEntryId && parentEntryId !== '') {
            queryParams.append('parent_entry_id', parentEntryId);
        }
        
        // Add end-date parameter only for residences button
        if (buttonConfig.addEndDate) {
            var currentDate = new Date();
            var month = String(currentDate.getMonth() + 1).padStart(2, '0');
            var day = String(currentDate.getDate()).padStart(2, '0');
            var year = currentDate.getFullYear();
            var formattedDate = month + '/' + day + '/' + year;
            queryParams.append('end-date', formattedDate);
        }
        
        return buttonConfig.formUrl + '?' + queryParams.toString();
    }
    
    /**
     * Add query parameters to a URL
     */
    function addQueryParams(basePath, anumber, parentEntryId) {
        var queryParams = new URLSearchParams();
        
        if (anumber && anumber !== '') {
            queryParams.append('anumber', anumber);
        }
        
        if (parentEntryId && parentEntryId !== '') {
            queryParams.append('parent_entry_id', parentEntryId);
        }
        
        if (queryParams.toString()) {
            var separator = basePath.includes('?') ? '&' : '?';
            return basePath + separator + queryParams.toString();
        }
        
        return basePath;
    }
    
    // Initialize
    setupConditionalLinks();
});