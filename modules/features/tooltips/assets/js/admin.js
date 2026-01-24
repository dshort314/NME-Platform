(function($) {
    'use strict';
    
    let tooltipCounter = 0;
    
    $(document).ready(function() {
        initTooltipAdmin();
    });
    
    function initTooltipAdmin() {
        // Set initial counter based on existing tooltips
        tooltipCounter = $('.tooltip-row').length;
        
        // Add tooltip button
        $('#add-tooltip-btn').on('click', addTooltipRow);
        
        // Remove tooltip buttons (event delegation)
        $(document).on('click', '.remove-tooltip-btn', removeTooltipRow);
        
        // Field ID change validation
        $(document).on('change', '.tooltip-field-id', validateFieldId);
        
        // Update field info when field ID changes
        $(document).on('input', '.tooltip-field-id', updateFieldInfo);
        
        // Hide "no tooltips" message if tooltips exist
        updateNoTooltipsMessage();
    }
    
    function addTooltipRow() {
        const $container = $('#tooltips-list');
        const fieldId = generateUniqueFieldId();
        
        // Show loading state
        const $loadingRow = $('<div class="tooltip-row tooltips-loading" style="text-align: center; padding: 20px;">Loading editor...</div>');
        $container.append($loadingRow);
        
        // Get the selected form ID
        const formId = typeof nmeTooltips !== 'undefined' ? nmeTooltips.selectedForm : 0;
        
        if (!formId) {
            $loadingRow.remove();
            alert('Please select a form first');
            return;
        }
        
        // AJAX request to get a proper editor row from PHP
        $.ajax({
            url: nmeTooltips.ajaxurl,
            type: 'POST',
            data: {
                action: 'nme_tooltips_get_row',
                nonce: nmeTooltips.nonce,
                form_id: formId,
                field_id: fieldId
            },
            success: function(response) {
                $loadingRow.remove();
                
                if (response.success) {
                    const $newRow = $(response.data.html);
                    $newRow.hide();
                    $container.append($newRow);
                    $newRow.slideDown(300, function() {
                        // Initialize TinyMCE for the new editor
                        initializeTinyMCE($newRow);
                    });
                    
                    // Focus on field ID input
                    $newRow.find('.tooltip-field-id').focus().select();
                    
                    // Update counter
                    tooltipCounter++;
                    
                    // Hide "no tooltips" message
                    updateNoTooltipsMessage();
                } else {
                    alert('Error creating tooltip row: ' + response.data);
                }
            },
            error: function() {
                $loadingRow.remove();
                alert('Error creating tooltip row. Please try again.');
            }
        });
    }
        
    function removeTooltipRow(e) {
        e.preventDefault();
        
        const $row = $(this).closest('.tooltip-row');
        const fieldId = $row.data('field-id') || $row.find('.tooltip-field-id').val();
        
        if (confirm('Are you sure you want to remove the tooltip for field #' + fieldId + '?')) {
            $row.addClass('removing');
            $row.slideUp(300, function() {
                $(this).remove();
                updateNoTooltipsMessage();
            });
        }
    }
    
    function validateFieldId() {
        const $input = $(this);
        const value = parseInt($input.val());
        const $row = $input.closest('.tooltip-row');
        
        // Remove existing error states
        $input.removeClass('error');
        $row.find('.error-message').remove();
        
        if (isNaN(value) || value <= 0) {
            showFieldError($input, 'Field ID must be a positive number');
            return;
        }
        
        // Check if field exists in the form
        if (typeof nmeTooltips !== 'undefined' && nmeTooltips.formFields) {
            const formFields = nmeTooltips.formFields;
            if (!formFields[value]) {
                showFieldError($input, 'This field ID does not exist in the selected form');
                return;
            }
        }
        
        // Check for duplicates
        const $otherInputs = $('.tooltip-field-id').not($input);
        let hasDuplicate = false;
        
        $otherInputs.each(function() {
            if (parseInt($(this).val()) === value) {
                hasDuplicate = true;
                return false;
            }
        });
        
        if (hasDuplicate) {
            showFieldError($input, 'This field ID is already used by another tooltip');
            return;
        }
        
        // Update row data attribute
        $row.attr('data-field-id', value);
        
        // Update name attributes
        updateRowFieldId($row, value);
    }
    
    function updateFieldInfo() {
        const $input = $(this);
        const $fieldInfo = $input.siblings('.field-info');
        const fieldId = parseInt($input.val());
        
        if (isNaN(fieldId) || fieldId <= 0) {
            $fieldInfo.text('').removeClass('field-exists field-missing');
            return;
        }
        
        // Check if we have form fields data from the server
        if (typeof nmeTooltips !== 'undefined' && nmeTooltips.formFields) {
            const formFields = nmeTooltips.formFields;
            
            if (formFields[fieldId]) {
                // Field exists - show the actual field label
                $fieldInfo.text(formFields[fieldId])
                         .removeClass('field-missing')
                         .addClass('field-exists');
            } else {
                // Field doesn't exist in the form
                $fieldInfo.text('⚠️ Field not found in form')
                         .removeClass('field-exists')
                         .addClass('field-missing');
            }
        } else {
            // Fallback if no form data available
            $fieldInfo.text('Field ' + fieldId).removeClass('field-exists field-missing');
        }
    }
    
    function updateRowFieldId($row, newFieldId) {
        // Update all name attributes in the row
        $row.find('[name]').each(function() {
            const $el = $(this);
            const oldName = $el.attr('name');
            const newName = oldName.replace(/\[\d+\]/, '[' + newFieldId + ']');
            $el.attr('name', newName);
        });
        
        // Update editor ID if it exists
        const $editor = $row.find('.wp-editor-area');
        if ($editor.length) {
            const oldId = $editor.attr('id');
            const newId = 'tooltip_editor_' + newFieldId;
            $editor.attr('id', newId);
        }
    }
    
    function generateUniqueFieldId() {
        let fieldId = 1;
        const existingIds = [];
        
        $('.tooltip-field-id').each(function() {
            const val = parseInt($(this).val());
            if (!isNaN(val) && val > 0) {
                existingIds.push(val);
            }
        });
        
        while (existingIds.includes(fieldId)) {
            fieldId++;
        }
        
        return fieldId;
    }
    
    function showFieldError($input, message) {
        $input.addClass('error');
        
        const $error = $('<div class="error-message" style="color: #d63638; font-size: 12px; margin-top: 3px;"></div>');
        $error.text(message);
        
        $input.after($error);
    }
    
    function updateNoTooltipsMessage() {
        const $noTooltips = $('.no-tooltips');
        const $tooltipRows = $('.tooltip-row:not(.removing)');
        
        if ($tooltipRows.length === 0) {
            $noTooltips.show();
        } else {
            $noTooltips.hide();
        }
    }

    function initializeTinyMCE($row) {
        // WordPress automatically initializes editors when HTML contains wp_editor() output
        // Just trigger WordPress's built-in initialization
        setTimeout(function() {
            if (typeof tinymce !== 'undefined') {
                // Let WordPress handle initialization automatically
                tinymce.EditorManager.execCommand('mceAddEditor', true, $row.find('textarea').attr('id'));
            }
        }, 100);
    }
    
    // Form submission validation
    $('.nme-tooltips-form').on('submit', function(e) {
        let hasErrors = false;
        
        $('.tooltip-field-id').each(function() {
            const $input = $(this);
            const value = parseInt($input.val());
            
            if (isNaN(value) || value <= 0) {
                hasErrors = true;
                showFieldError($input, 'Field ID must be a positive number');
            } else if (typeof nmeTooltips !== 'undefined' && nmeTooltips.formFields) {
                // Check if field exists in form
                const formFields = nmeTooltips.formFields;
                if (!formFields[value]) {
                    hasErrors = true;
                    showFieldError($input, 'This field ID does not exist in the selected form');
                }
            }
        });
        
        // Check for empty messages - handle both TinyMCE and textarea content
        $('.tooltip-row').each(function() {
            const $row = $(this);
            const $textarea = $row.find('textarea[name*="[message]"]');
            const textareaId = $textarea.attr('id');
            let message = '';
            
            // Get content from TinyMCE if active, otherwise from textarea
            if (textareaId && typeof tinymce !== 'undefined') {
                const editor = tinymce.get(textareaId);
                if (editor) {
                    message = editor.getContent().trim();
                } else {
                    message = $textarea.val().trim();
                }
            } else {
                message = $textarea.val().trim();
            }
            
            if (message === '' || message === '<p></p>') {
                hasErrors = true;
                $textarea.addClass('error');
                if (!$textarea.next('.error-message').length) {
                    $textarea.after('<div class="error-message" style="color: #d63638; font-size: 12px; margin-top: 3px;">Tooltip message cannot be empty</div>');
                }
            } else {
                $textarea.removeClass('error');
                $textarea.next('.error-message').remove();
            }
        });
        
        // Before submitting, sync TinyMCE content to textareas
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        
        if (hasErrors) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: $('.error').first().offset().top - 100
            }, 500);
        }
    });
    
})(jQuery);
