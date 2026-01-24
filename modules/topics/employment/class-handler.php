<?php
/**
 * Employment Handler
 * 
 * Processes Form 73 submissions:
 * - Links entries to parent Form 75 via GPNF
 * - Prepopulates user context fields
 */

namespace NME\Topics\Employment;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 73;

    /** @var int Field ID for parent entry reference */
    const FIELD_PARENT_ENTRY_ID = 1;

    /** @var int Field ID for A-Number */
    const FIELD_ANUMBER = 3;

    /** @var int Master Form field that stores nested employment entries */
    const MASTER_NESTED_FIELD = 773;

    /** @var array Page IDs where this module operates */
    const PAGE_IDS = [709, 856];

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Prepopulate fields on form render
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_validation_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_submission_filter_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_admin_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);

        // Enqueue page-specific scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_scripts']);

        if (Plugin::is_debug_enabled('employment')) {
            error_log('NME Platform [employment]: Handler initialized');
        }
    }

    /**
     * Prepopulate user context fields
     */
    public static function prepopulate_fields(array $form): array {
        if ((int) $form['id'] !== self::FORM_ID) {
            return $form;
        }

        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        foreach ($form['fields'] as &$field) {
            if ((int) $field->id === self::FIELD_ANUMBER && $anumber) {
                $field->defaultValue = $anumber;
            }
            if ((int) $field->id === self::FIELD_PARENT_ENTRY_ID && $parent_entry_id) {
                $field->defaultValue = $parent_entry_id;
            }
        }

        return $form;
    }

    /**
     * Maybe enqueue scripts on employment pages
     */
    public static function maybe_enqueue_scripts(): void {
        if (!is_page(self::PAGE_IDS)) {
            return;
        }

        // Page-specific scripts would go here
        // Currently this is a placeholder for any JavaScript the original plugin used
        
        if (Plugin::is_debug_enabled('employment')) {
            error_log('NME Platform [employment]: On employment page, scripts would be enqueued');
        }
    }

    /**
     * Get the Master Form field ID for nested employment entries
     */
    public static function get_master_nested_field(): int {
        return self::MASTER_NESTED_FIELD;
    }

    /**
     * Get page IDs
     */
    public static function get_page_ids(): array {
        return self::PAGE_IDS;
    }
}
