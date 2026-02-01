/**
 * Bug Reports Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        
        // =====================
        // Accordion functionality with localStorage persistence
        // =====================
        
        var STORAGE_KEY = 'nme_bug_accordion_state';
        var EXPIRATION_HOURS = 24;
        
        // Get saved state from localStorage
        function getSavedState() {
            try {
                var saved = localStorage.getItem(STORAGE_KEY);
                if (!saved) return null;
                
                var data = JSON.parse(saved);
                
                // Check if expired
                if (data.timestamp) {
                    var age = Date.now() - data.timestamp;
                    var maxAge = EXPIRATION_HOURS * 60 * 60 * 1000;
                    if (age > maxAge) {
                        localStorage.removeItem(STORAGE_KEY);
                        return null;
                    }
                }
                
                return data.state || null;
            } catch (e) {
                return null;
            }
        }
        
        // Save current state to localStorage with timestamp
        function saveState() {
            try {
                var state = {};
                $('.nme-accordion-section').each(function() {
                    var section = $(this).data('section');
                    state[section] = $(this).hasClass('open');
                });
                localStorage.setItem(STORAGE_KEY, JSON.stringify({
                    timestamp: Date.now(),
                    state: state
                }));
            } catch (e) {
                // localStorage not available
            }
        }
        
        // Initialize accordion state
        var savedState = getSavedState();
        
        // First: apply saved state or expand all
        if (savedState) {
            $('.nme-accordion-section').each(function() {
                var section = $(this).data('section');
                if (savedState[section]) {
                    $(this).addClass('open');
                }
            });
        } else {
            // First visit or expired: expand all
            $('.nme-accordion-section').addClass('open');
        }
        
        // Second: always open sections with unread items (override saved state)
        $('.nme-accordion-section').each(function() {
            if ($(this).find('.nme-has-unread').length > 0) {
                $(this).addClass('open');
            }
        });
        
        // Save initial state (in case unread sections were forced open)
        saveState();
        
        // Toggle section and save state
        $(document).on('click', '.nme-accordion-header', function() {
            var $section = $(this).closest('.nme-accordion-section');
            $section.toggleClass('open');
            saveState();
        });
        
        $('#nme-collapse-all').on('click', function() {
            $('.nme-accordion-section').removeClass('open');
            saveState();
        });
        
        $('#nme-expand-all').on('click', function() {
            $('.nme-accordion-section').addClass('open');
            saveState();
        });
        
        // =====================
        // Other variables
        // =====================
        
        var $section = $('#section');
        var $subsectionRow = $('#subsection-row');
        var $subsection = $('#subsection');
        var $editPanel = $('#nme-edit-comment-panel');
        var $addCommentForm = $('.nme-add-comment');

        // Import form elements
        var $importSection = $('#import_section');
        var $importSubsectionRow = $('#import-subsection-row');
        var $importSubsection = $('#import_subsection');

        // Make all links in bug content and comments open in new window
        $('.nme-bug-content a, .nme-comment-content a').attr('target', '_blank').attr('rel', 'noopener noreferrer');

        // =====================
        // Edit Comment (Single Report View)
        // =====================
        
        $(document).on('click', '.nme-edit-comment', function(e) {
            e.preventDefault();
            
            var reportId = $(this).data('report-id');
            var commentIndex = $(this).data('comment-index');
            
            $('#edit-comment-index').val(commentIndex);
            
            $.post(nmeBugReports.ajaxUrl, {
                action: 'nme_get_comment',
                nonce: nmeBugReports.getCommentNonce,
                report_id: reportId,
                comment_index: commentIndex
            }, function(response) {
                if (response.success) {
                    var content = response.data.content || '';
                    
                    if (typeof tinymce !== 'undefined' && tinymce.get('edit_comment_content')) {
                        tinymce.get('edit_comment_content').setContent(content);
                    }
                    $('#edit_comment_content').val(content);
                    
                    $addCommentForm.hide();
                    $editPanel.show();
                    
                    $('html, body').animate({
                        scrollTop: $editPanel.offset().top - 100
                    }, 300);
                } else {
                    alert('Error loading comment: ' + (response.data || 'Unknown error'));
                }
            }).fail(function() {
                alert('Error loading comment. Please try again.');
            });
        });

        $(document).on('click', '.nme-edit-cancel', function(e) {
            e.preventDefault();
            
            if (typeof tinymce !== 'undefined' && tinymce.get('edit_comment_content')) {
                tinymce.get('edit_comment_content').setContent('');
            }
            $('#edit_comment_content').val('');
            $('#edit-comment-index').val('');
            
            $editPanel.hide();
            $addCommentForm.show();
        });

        // =====================
        // Section/Subsection Dropdowns
        // =====================
        
        function handleSubsectionChange(section, $row, $select) {
            var subsections = null;

            if (section === 'preliminary-eligibility') {
                subsections = nmeBugReports.subsectionsPreliminary;
            } else if (section === 'additional-information') {
                subsections = nmeBugReports.subsectionsAdditional;
            }

            $select.html('<option value="">— Select Subsection —</option>');

            if (subsections) {
                $.each(subsections, function(key, label) {
                    $select.append($('<option></option>').val(key).text(label));
                });
                $row.show();
                $select.prop('required', true);
            } else {
                $row.hide();
                $select.prop('required', false);
            }
        }

        if ($section.length) {
            $section.on('change', function() {
                handleSubsectionChange($(this).val(), $subsectionRow, $subsection);
            });
            if ($section.val()) {
                $section.trigger('change');
            }
        }

        if ($importSection.length) {
            $importSection.on('change', function() {
                handleSubsectionChange($(this).val(), $importSubsectionRow, $importSubsection);
            });
            if ($importSection.val()) {
                $importSection.trigger('change');
            }
        }

        // =====================
        // Manual Entry System (Import Page)
        // =====================
        
        var $entriesContainer = $('#nme-entries-container');
        
        // Only run manual entry code if we're on that page
        if (!$entriesContainer.length) {
            return;
        }
        
        var entryIndex = 0;
        
        // Get editor content
        function getEditorContent() {
            if (typeof tinymce !== 'undefined' && tinymce.get('entry_content_editor')) {
                return tinymce.get('entry_content_editor').getContent();
            }
            return $('#entry_content_editor').val();
        }
        
        // Set editor content
        function setEditorContent(content) {
            if (typeof tinymce !== 'undefined' && tinymce.get('entry_content_editor')) {
                tinymce.get('entry_content_editor').setContent(content || '');
            }
            $('#entry_content_editor').val(content || '');
        }
        
        // Edit entry button
        $(document).on('click', '.nme-edit-entry-content', function(e) {
            e.preventDefault();
            
            var $entry = $(this).closest('.nme-entry');
            var index = $entry.data('index');
            var currentContent = $entry.find('.nme-entry-content-hidden').val() || '';
            
            // Highlight entry
            $('.nme-entry').removeClass('nme-entry-editing');
            $entry.addClass('nme-entry-editing');
            
            // Load content into editor
            setEditorContent(currentContent);
            $('#nme-editing-index').val(index);
            
            // Update status
            var entryNum = $entry.find('.nme-entry-number').text();
            $('.nme-editor-status').html('Editing: <strong>' + entryNum + '</strong>');
            
            // Scroll to editor
            $('html, body').animate({
                scrollTop: $('#nme-entry-editor-panel').offset().top - 50
            }, 300);
        });
        
        // Save entry content
        $(document).on('click', '#nme-save-entry-content', function(e) {
            e.preventDefault();
            
            var index = $('#nme-editing-index').val();
            
            if (index === '' || index === undefined) {
                alert('No entry selected. Click "Edit" on an entry first.');
                return;
            }
            
            var content = getEditorContent();
            var $entry = $('.nme-entry[data-index="' + index + '"]');
            
            if (!$entry.length) {
                alert('Entry not found.');
                return;
            }
            
            // Save to hidden field
            $entry.find('.nme-entry-content-hidden').val(content);
            
            // Update preview
            if (content && content.trim()) {
                var tempDiv = document.createElement('div');
                tempDiv.innerHTML = content;
                var textContent = tempDiv.textContent || tempDiv.innerText || '';
                var preview = textContent.length > 200 ? textContent.substring(0, 200) + '...' : textContent;
                $entry.find('.nme-entry-preview').html('<div style="color:#333;">' + preview + '</div>');
            } else {
                $entry.find('.nme-entry-preview').html('<em>No content yet - click Edit</em>');
            }
            
            // Clear editing state
            $entry.removeClass('nme-entry-editing');
            $('#nme-editing-index').val('');
            setEditorContent('');
            $('.nme-editor-status').text('Content saved. Select another entry to edit.');
        });
        
        // Cancel edit
        $(document).on('click', '#nme-cancel-entry-edit', function(e) {
            e.preventDefault();
            $('.nme-entry').removeClass('nme-entry-editing');
            $('#nme-editing-index').val('');
            setEditorContent('');
            $('.nme-editor-status').text('Edit cancelled. Select an entry to edit.');
        });
        
        // Add new entry
        $(document).on('click', '#nme-add-entry', function(e) {
            e.preventDefault();
            entryIndex++;
            var template = $('#nme-entry-template').html();
            template = template.replace(/\{\{INDEX\}\}/g, entryIndex);
            template = template.replace(/\{\{NUM\}\}/g, entryIndex + 1);
            $entriesContainer.append(template);
            
            // Auto-open editor for new entry
            $entriesContainer.find('.nme-entry:last .nme-edit-entry-content').trigger('click');
        });

        // Remove entry
        $(document).on('click', '.nme-remove-entry', function(e) {
            e.preventDefault();
            var $entry = $(this).closest('.nme-entry');
            var index = $entry.data('index');
            
            // Clear editor if editing this entry
            if ($('#nme-editing-index').val() == index) {
                $('#nme-editing-index').val('');
                setEditorContent('');
                $('.nme-editor-status').text('Select an entry to edit.');
            }
            
            $entry.remove();
            updateEntryNumbers();
        });

        function updateEntryNumbers() {
            $entriesContainer.find('.nme-entry').each(function(i) {
                var label = i === 0 ? 'Entry #1 (Description)' : 'Entry #' + (i + 1) + ' (Comment)';
                $(this).find('.nme-entry-number').text(label);
                
                if (i === 0) {
                    $(this).find('.nme-remove-entry').hide();
                } else {
                    $(this).find('.nme-remove-entry').show();
                }
            });
        }
        
        // Form validation
        $('#nme-import-form').on('submit', function(e) {
            var hasContent = false;
            $entriesContainer.find('.nme-entry-content-hidden').each(function() {
                if ($(this).val() && $(this).val().trim()) {
                    hasContent = true;
                }
            });
            
            if (!hasContent) {
                e.preventDefault();
                alert('Please add content to at least the first entry.');
                return false;
            }
        });
    });

})(jQuery);