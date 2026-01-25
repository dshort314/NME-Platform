/**
 * NME Platform - Global Modal System
 * 
 * Provides a standardized modal system for all modules.
 * Supports multiple modal types with consistent behavior.
 * 
 * Usage:
 * 
 *   // Simple info modal
 *   NMEModal.info({
 *       title: 'Information',
 *       message: 'Your message here.'
 *   });
 * 
 *   // Confirmation modal with back/continue
 *   NMEModal.confirm({
 *       title: 'Confirm Action',
 *       message: '<p>Are you sure?</p>',
 *       revertField: '#input_70_12',
 *       onConfirm: function() { console.log('Confirmed'); },
 *       onCancel: function() { console.log('Cancelled'); }
 *   });
 * 
 *   // Warning modal
 *   NMEModal.warning({
 *       title: 'Warning',
 *       message: 'This action cannot be undone.',
 *       confirmText: 'Proceed Anyway'
 *   });
 * 
 *   // Generic show method
 *   NMEModal.show({
 *       type: 'info',
 *       title: 'Title',
 *       message: 'Content'
 *   });
 */

(function($, window) {
    'use strict';

    const MODULE_ID = 'modals';

    /**
     * Debug helper
     */
    function debug(...args) {
        if (typeof NMEDebug !== 'undefined') {
            NMEDebug(MODULE_ID, ...args);
        }
    }

    /**
     * Default configurations for each modal type
     */
    const TYPE_DEFAULTS = {
        info: {
            icon: 'ℹ',
            buttons: [
                { text: 'I Understand', type: 'primary', action: 'close' }
            ],
            showClose: false
        },
        confirm: {
            icon: '?',
            buttons: [
                { text: 'Back', type: 'secondary', action: 'cancel' },
                { text: 'Continue', type: 'primary', action: 'confirm' }
            ],
            showClose: false
        },
        warning: {
            icon: '⚠',
            buttons: [
                { text: 'I Understand', type: 'primary', action: 'close' }
            ],
            showClose: false
        },
        error: {
            icon: '✕',
            buttons: [
                { text: 'OK', type: 'primary', action: 'close' }
            ],
            showClose: false
        },
        success: {
            icon: '✓',
            buttons: [
                { text: 'OK', type: 'primary', action: 'close' }
            ],
            showClose: false
        }
    };

    /**
     * Current active modal reference
     */
    let activeModal = null;

    /**
     * Clear/revert a field to empty state
     * @param {string} selector - jQuery selector for the field
     */
    function clearField(selector) {
        const $field = $(selector);
        
        if ($field.length === 0) {
            debug('clearField: Field not found:', selector);
            return;
        }

        debug('clearField: Clearing field:', selector);

        // Determine field type and clear appropriately
        if ($field.is(':radio')) {
            // Radio button - uncheck all in the group
            const name = $field.attr('name');
            $('input[name="' + name + '"]').prop('checked', false).trigger('change');
        } else if ($field.is(':checkbox')) {
            // Checkbox - uncheck
            $field.prop('checked', false).trigger('change');
        } else if ($field.is('select')) {
            // Select - reset to first option (usually placeholder)
            $field.prop('selectedIndex', 0).trigger('change');
        } else {
            // Text, date, etc. - clear value
            $field.val('').trigger('change');
        }
    }

    /**
     * Build modal HTML structure
     * @param {Object} options - Modal options
     * @returns {string} HTML string
     */
    function buildModalHTML(options) {
        const type = options.type || 'info';
        const defaults = TYPE_DEFAULTS[type] || TYPE_DEFAULTS.info;
        
        // Merge options with defaults
        const config = {
            title: options.title || '',
            message: options.message || '',
            icon: options.icon !== undefined ? options.icon : defaults.icon,
            buttons: options.buttons || defaults.buttons,
            showClose: options.showClose !== undefined ? options.showClose : defaults.showClose,
            confirmText: options.confirmText,
            cancelText: options.cancelText
        };

        // Override button text if provided
        if (config.confirmText || config.cancelText) {
            config.buttons = config.buttons.map(btn => {
                if (btn.action === 'confirm' && config.confirmText) {
                    return { ...btn, text: config.confirmText };
                }
                if (btn.action === 'cancel' && config.cancelText) {
                    return { ...btn, text: config.cancelText };
                }
                if (btn.action === 'close' && config.confirmText) {
                    return { ...btn, text: config.confirmText };
                }
                return btn;
            });
        }

        let html = '<div class="nme-modal-overlay nme-modal-type-' + type + '" role="dialog" aria-modal="true">';
        html += '<div class="nme-modal">';

        // Close button (optional)
        if (config.showClose) {
            html += '<button type="button" class="nme-modal-close" aria-label="Close" data-action="close">&times;</button>';
        }

        // Header
        if (config.title || config.icon) {
            html += '<div class="nme-modal-header">';
            if (config.icon) {
                html += '<div class="nme-modal-icon" aria-hidden="true">' + config.icon + '</div>';
            }
            if (config.title) {
                html += '<h2 class="nme-modal-title">' + config.title + '</h2>';
            }
            html += '</div>';
        }

        // Body
        if (config.message) {
            html += '<div class="nme-modal-body">';
            // If message doesn't start with HTML tag, wrap in <p>
            if (!config.message.trim().startsWith('<')) {
                html += '<p>' + config.message + '</p>';
            } else {
                html += config.message;
            }
            html += '</div>';
        }

        // Footer with buttons
        if (config.buttons && config.buttons.length > 0) {
            html += '<div class="nme-modal-footer">';
            config.buttons.forEach(btn => {
                const btnClass = 'nme-modal-btn nme-modal-btn-' + (btn.type || 'primary');
                html += '<button type="button" class="' + btnClass + '" data-action="' + btn.action + '">';
                html += btn.text;
                html += '</button>';
            });
            html += '</div>';
        }

        html += '</div>'; // .nme-modal
        html += '</div>'; // .nme-modal-overlay

        return html;
    }

    /**
     * Show a modal
     * @param {Object} options - Modal configuration
     * @returns {Object} Modal instance with close method
     */
    function showModal(options) {
        debug('showModal called with options:', options);

        // Close any existing modal first
        if (activeModal) {
            closeModal(activeModal, false);
        }

        // Build and append modal
        const html = buildModalHTML(options);
        const $overlay = $(html).appendTo('body');
        const $modal = $overlay.find('.nme-modal');

        // Store options for callbacks
        $overlay.data('options', options);

        // Show modal with animation
        requestAnimationFrame(() => {
            $overlay.addClass('nme-modal-visible');
        });

        // Set up event handlers
        setupModalEvents($overlay, options);

        // Focus management
        $modal.attr('tabindex', '-1').focus();

        // Store reference
        activeModal = $overlay;

        debug('Modal shown:', options.type || 'info');

        return {
            close: function() {
                closeModal($overlay, true);
            },
            getElement: function() {
                return $overlay;
            }
        };
    }

    /**
     * Set up event handlers for modal
     * @param {jQuery} $overlay - Modal overlay element
     * @param {Object} options - Modal options
     */
    function setupModalEvents($overlay, options) {
        // Button clicks
        $overlay.on('click', '[data-action]', function(e) {
            e.preventDefault();
            const action = $(this).data('action');
            handleModalAction($overlay, action, options);
        });

        // Overlay click (close if allowed)
        $overlay.on('click', function(e) {
            if (e.target === this && options.closeOnOverlay !== false) {
                handleModalAction($overlay, 'close', options);
            }
        });

        // Escape key
        $(document).on('keydown.nmeModal', function(e) {
            if (e.key === 'Escape' && options.closeOnEscape !== false) {
                handleModalAction($overlay, 'close', options);
            }
        });

        // Trap focus within modal
        $overlay.on('keydown', function(e) {
            if (e.key === 'Tab') {
                trapFocus($overlay, e);
            }
        });
    }

    /**
     * Handle modal button action
     * @param {jQuery} $overlay - Modal overlay element
     * @param {string} action - Action type (close, confirm, cancel)
     * @param {Object} options - Modal options
     */
    function handleModalAction($overlay, action, options) {
        debug('Modal action:', action);

        switch (action) {
            case 'confirm':
                if (typeof options.onConfirm === 'function') {
                    options.onConfirm();
                }
                closeModal($overlay, true);
                break;

            case 'cancel':
                // Clear the field if specified
                if (options.revertField) {
                    clearField(options.revertField);
                }
                if (typeof options.onCancel === 'function') {
                    options.onCancel();
                }
                closeModal($overlay, true);
                break;

            case 'close':
            default:
                if (typeof options.onClose === 'function') {
                    options.onClose();
                }
                closeModal($overlay, true);
                break;
        }
    }

    /**
     * Close modal
     * @param {jQuery} $overlay - Modal overlay element
     * @param {boolean} animate - Whether to animate closing
     */
    function closeModal($overlay, animate) {
        if (!$overlay || $overlay.length === 0) {
            return;
        }

        debug('Closing modal');

        // Remove event handlers
        $(document).off('keydown.nmeModal');
        $overlay.off('click keydown');

        if (animate) {
            $overlay.removeClass('nme-modal-visible');
            setTimeout(function() {
                $overlay.remove();
            }, 200);
        } else {
            $overlay.remove();
        }

        if (activeModal === $overlay) {
            activeModal = null;
        }
    }

    /**
     * Trap focus within modal for accessibility
     * @param {jQuery} $overlay - Modal overlay element
     * @param {Event} e - Keydown event
     */
    function trapFocus($overlay, e) {
        const $focusable = $overlay.find('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        const $first = $focusable.first();
        const $last = $focusable.last();

        if (e.shiftKey && document.activeElement === $first[0]) {
            e.preventDefault();
            $last.focus();
        } else if (!e.shiftKey && document.activeElement === $last[0]) {
            e.preventDefault();
            $first.focus();
        }
    }

    /**
     * NMEModal Public API
     */
    const NMEModal = {
        /**
         * Show a modal with full configuration
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        show: function(options) {
            return showModal(options);
        },

        /**
         * Show an info modal (simple acknowledgment)
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        info: function(options) {
            return showModal({ ...options, type: 'info' });
        },

        /**
         * Show a confirmation modal (Continue/Back)
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        confirm: function(options) {
            return showModal({ ...options, type: 'confirm' });
        },

        /**
         * Show a warning modal
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        warning: function(options) {
            return showModal({ ...options, type: 'warning' });
        },

        /**
         * Show an error modal
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        error: function(options) {
            return showModal({ ...options, type: 'error' });
        },

        /**
         * Show a success modal
         * @param {Object} options - Modal configuration
         * @returns {Object} Modal instance
         */
        success: function(options) {
            return showModal({ ...options, type: 'success' });
        },

        /**
         * Close the currently active modal
         */
        close: function() {
            if (activeModal) {
                closeModal(activeModal, true);
            }
        },

        /**
         * Check if a modal is currently open
         * @returns {boolean}
         */
        isOpen: function() {
            return activeModal !== null;
        },

        /**
         * Register a custom modal type
         * @param {string} typeName - Name for the new type
         * @param {Object} defaults - Default configuration for the type
         */
        registerType: function(typeName, defaults) {
            if (TYPE_DEFAULTS[typeName]) {
                debug('Warning: Overwriting existing modal type:', typeName);
            }
            TYPE_DEFAULTS[typeName] = defaults;
            debug('Registered modal type:', typeName);
        }
    };

    // Expose globally
    window.NMEModal = NMEModal;

    debug('NMEModal initialized');

})(jQuery, window);
