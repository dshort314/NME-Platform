/**
 * NME Platform - Global Debug Utility
 * 
 * Provides centralized console logging controlled by Dashboard checkboxes.
 * Each module's debug output can be toggled independently.
 * 
 * Usage:
 *   NMEDebug('information-about-you', 'Message here', optionalData);
 *   NMEDebug.log('module-id', 'Message');
 *   NMEDebug.warn('module-id', 'Warning message');
 *   NMEDebug.error('module-id', 'Error message');
 *   NMEDebug.table('module-id', dataArray);
 *   NMEDebug.group('module-id', 'Group Label');
 *   NMEDebug.groupEnd('module-id');
 * 
 * The debug flags are set via NME Platform > Dashboard checkboxes.
 * Flags are passed from PHP via wp_localize_script as `nme_debug_flags`.
 */

(function(window) {
    'use strict';

    /**
     * Check if debug is enabled for a module
     * @param {string} moduleId - The module identifier
     * @returns {boolean}
     */
    function isEnabled(moduleId) {
        return typeof nme_debug_flags !== 'undefined' && 
               nme_debug_flags[moduleId] === true;
    }

    /**
     * Get formatted prefix for log messages
     * @param {string} moduleId - The module identifier
     * @returns {string}
     */
    function getPrefix(moduleId) {
        // Convert module-id to MODULE-ID for visibility
        return '[NME:' + moduleId + ']';
    }

    /**
     * Main debug function - logs to console if module debug is enabled
     * @param {string} moduleId - The module identifier
     * @param {...any} args - Arguments to log
     */
    function NMEDebug(moduleId, ...args) {
        if (isEnabled(moduleId)) {
            console.log(getPrefix(moduleId), ...args);
        }
    }

    /**
     * Standard log (alias for main function)
     */
    NMEDebug.log = function(moduleId, ...args) {
        if (isEnabled(moduleId)) {
            console.log(getPrefix(moduleId), ...args);
        }
    };

    /**
     * Warning log
     */
    NMEDebug.warn = function(moduleId, ...args) {
        if (isEnabled(moduleId)) {
            console.warn(getPrefix(moduleId), ...args);
        }
    };

    /**
     * Error log
     */
    NMEDebug.error = function(moduleId, ...args) {
        if (isEnabled(moduleId)) {
            console.error(getPrefix(moduleId), ...args);
        }
    };

    /**
     * Info log
     */
    NMEDebug.info = function(moduleId, ...args) {
        if (isEnabled(moduleId)) {
            console.info(getPrefix(moduleId), ...args);
        }
    };

    /**
     * Table log - useful for arrays and objects
     */
    NMEDebug.table = function(moduleId, data, columns) {
        if (isEnabled(moduleId)) {
            console.log(getPrefix(moduleId), 'Table:');
            console.table(data, columns);
        }
    };

    /**
     * Start a collapsed group
     */
    NMEDebug.group = function(moduleId, label) {
        if (isEnabled(moduleId)) {
            console.groupCollapsed(getPrefix(moduleId), label);
        }
    };

    /**
     * Start an expanded group
     */
    NMEDebug.groupExpanded = function(moduleId, label) {
        if (isEnabled(moduleId)) {
            console.group(getPrefix(moduleId), label);
        }
    };

    /**
     * End a group
     */
    NMEDebug.groupEnd = function(moduleId) {
        if (isEnabled(moduleId)) {
            console.groupEnd();
        }
    };

    /**
     * Log current state of an object with label
     */
    NMEDebug.state = function(moduleId, label, obj) {
        if (isEnabled(moduleId)) {
            console.log(getPrefix(moduleId), '=== ' + label + ' ===');
            Object.keys(obj).forEach(function(key) {
                console.log('  ' + key + ':', obj[key]);
            });
            console.log(getPrefix(moduleId), '='.repeat(label.length + 8));
        }
    };

    /**
     * Time tracking - start
     */
    NMEDebug.time = function(moduleId, label) {
        if (isEnabled(moduleId)) {
            console.time(getPrefix(moduleId) + ' ' + label);
        }
    };

    /**
     * Time tracking - end
     */
    NMEDebug.timeEnd = function(moduleId, label) {
        if (isEnabled(moduleId)) {
            console.timeEnd(getPrefix(moduleId) + ' ' + label);
        }
    };

    /**
     * Check if debug is enabled for a module (public method)
     * Useful for conditional blocks that do more than just logging
     */
    NMEDebug.isEnabled = isEnabled;

    /**
     * Get all currently enabled modules (for debugging the debugger)
     */
    NMEDebug.getEnabledModules = function() {
        if (typeof nme_debug_flags === 'undefined') {
            return [];
        }
        return Object.keys(nme_debug_flags).filter(function(key) {
            return nme_debug_flags[key] === true;
        });
    };

    /**
     * Log initialization message when any module has debug enabled
     */
    NMEDebug.init = function() {
        var enabled = NMEDebug.getEnabledModules();
        if (enabled.length > 0) {
            console.log('[NME:debug] Debug enabled for modules:', enabled.join(', '));
        }
    };

    // Expose globally
    window.NMEDebug = NMEDebug;

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', NMEDebug.init);
    } else {
        NMEDebug.init();
    }

})(window);
