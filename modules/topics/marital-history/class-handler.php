<?php
/**
 * Marital History Handler
 * 
 * Processes Form 71 submissions:
 * - Syncs fields to Form 75 (Master)
 * - Handles GravityView edit updates
 */

namespace NME\Topics\MaritalHistory;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 71;

    /** @var int Field ID for parent entry reference */
    const FIELD_PARENT_ENTRY_ID = 8;

    /** @var int Field ID for A-Number */
    const FIELD_ANUMBER = 7;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Prepopulate fields on form render
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_validation_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_submission_filter_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_admin_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);

        // After submission: Sync to Master
        add_action('gform_after_submission_' . self::FORM_ID, [__CLASS__, 'handle_submission'], 10, 2);

        // GravityView edit updates
        add_action('gravityview/edit_entry/after_update', [__CLASS__, 'handle_gravityview_update'], 10, 3);

        if (Plugin::is_debug_enabled('marital-history')) {
            error_log('NME Platform [marital-history]: Handler initialized');
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
     * Handle form submission - sync to Master
     */
    public static function handle_submission(array $entry, array $form): void {
        if ((int) $form['id'] !== self::FORM_ID) {
            return;
        }

        $parent_entry_id = rgar($entry, self::FIELD_PARENT_ENTRY_ID);

        if (!$parent_entry_id) {
            $parent_entry_id = UserContext::get_parent_entry_id();
        }

        if (!$parent_entry_id) {
            if (Plugin::is_debug_enabled('marital-history')) {
                error_log('NME Platform [marital-history]: No parent_entry_id found for sync');
            }
            return;
        }

        // Verify parent entry exists
        if (!class_exists('GFAPI')) {
            return;
        }

        $parent_entry = \GFAPI::get_entry($parent_entry_id);

        if (is_wp_error($parent_entry)) {
            if (Plugin::is_debug_enabled('marital-history')) {
                error_log('NME Platform [marital-history]: Parent entry ' . $parent_entry_id . ' does not exist');
            }
            return;
        }

        self::sync_to_master($parent_entry_id, $entry);
    }

    /**
     * Handle GravityView edit updates
     */
    public static function handle_gravityview_update(array $form, string $entry_id, $gv_entry): void {
        if ((int) $form['id'] !== self::FORM_ID) {
            return;
        }

        if (!class_exists('GFAPI')) {
            return;
        }

        $entry = \GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            return;
        }

        $parent_entry_id = UserContext::get_parent_entry_id();

        if (!$parent_entry_id) {
            return;
        }

        self::sync_to_master($parent_entry_id, $entry);
    }

    /**
     * Sync Form 71 fields to Master Form 75
     */
    private static function sync_to_master(int $master_entry_id, array $entry): void {
        // Field mappings: Form 71 Field => Form 75 Field
        $mappings = [
            1 => FieldRegistry::MASTER_FIELD_ARMED_FORCES,         // Armed Forces status
            3 => FieldRegistry::MASTER_FIELD_TIMES_MARRIED,        // Times married
            4 => FieldRegistry::MASTER_FIELD_SPOUSE_ANUMBER,       // Spouse A-Number
            5 => FieldRegistry::MASTER_FIELD_SPOUSE_TIMES_MARRIED, // Spouse times married
        ];

        $updates = [];

        foreach ($mappings as $form71_field => $form75_field) {
            $value = rgar($entry, $form71_field);
            if ($value !== '' && $value !== null) {
                $updates[$form75_field] = $value;
            }
        }

        if (!empty($updates)) {
            MasterForm::update_fields($master_entry_id, $updates);

            if (Plugin::is_debug_enabled('marital-history')) {
                error_log('NME Platform [marital-history]: Synced ' . count($updates) . ' fields to Master entry ' . $master_entry_id);
            }
        }
    }

    /**
     * Get field mappings (for reference/debugging)
     */
    public static function get_field_mappings(): array {
        return [
            1 => FieldRegistry::MASTER_FIELD_ARMED_FORCES,
            3 => FieldRegistry::MASTER_FIELD_TIMES_MARRIED,
            4 => FieldRegistry::MASTER_FIELD_SPOUSE_ANUMBER,
            5 => FieldRegistry::MASTER_FIELD_SPOUSE_TIMES_MARRIED,
        ];
    }
}
