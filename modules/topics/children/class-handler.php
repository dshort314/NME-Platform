<?php
/**
 * Children Handler
 * 
 * Processes Form 72 submissions:
 * - Links entries to parent Form 75
 * - Prepopulates user context fields
 */

namespace NME\Topics\Children;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 72;

    /** @var int Field ID for parent entry reference - VERIFY THIS */
    const FIELD_PARENT_ENTRY_ID = 1;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
    }

    /**
     * Prepopulate form fields
     */
    public static function prepopulate_fields(array $form): array {
        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        foreach ($form['fields'] as &$field) {
            if (isset($field->inputName) && $field->inputName === 'anumber') {
                $field->defaultValue = $anumber;
            }

            if ((int) $field->id === self::FIELD_PARENT_ENTRY_ID) {
                $field->defaultValue = $parent_entry_id;
            }
        }

        return $form;
    }

    /**
     * Get entries for a parent
     */
    public static function get_entries_for_parent(int $parent_entry_id): array {
        global $wpdb;

        $entry_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = %s 
             AND em.meta_value = %s
             ORDER BY e.id ASC",
            self::FORM_ID,
            (string) self::FIELD_PARENT_ENTRY_ID,
            (string) $parent_entry_id
        ));

        if (empty($entry_ids) || !class_exists('GFAPI')) {
            return [];
        }

        $entries = [];
        foreach ($entry_ids as $entry_id) {
            $entry = \GFAPI::get_entry($entry_id);
            if (!is_wp_error($entry)) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Count children for a parent
     */
    public static function count_children(int $parent_entry_id): int {
        return count(self::get_entries_for_parent($parent_entry_id));
    }
}