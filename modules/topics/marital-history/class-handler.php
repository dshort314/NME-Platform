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

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 71;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // After submission: Sync to Master
        add_action(
            'gform_after_submission_' . self::FORM_ID,
            [__CLASS__, 'after_submission'],
            10,
            2
        );

        // GravityView edit updates
        add_action(
            'gravityview/edit_entry/after_update',
            [__CLASS__, 'handle_gravityview_update'],
            10,
            3
        );
    }

    /**
     * After submission: Sync to Master
     */
    public static function after_submission(array $entry, array $form): void {
        $parent_entry_id = UserContext::get_parent_entry_id();

        if (!$parent_entry_id) {
            error_log('NME Platform: No parent_entry_id for Form 71 submission');
            return;
        }

        if (!MasterForm::entry_exists($parent_entry_id)) {
            error_log('NME Platform: Master entry ' . $parent_entry_id . ' does not exist');
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
     * Sync Form 71 entry to Master
     */
    private static function sync_to_master(int $master_entry_id, array $entry): void {
        // Field mappings from Form 71 to Form 75
        // Form 71 Field => Form 75 Field
        $mappings = [
            1 => FieldRegistry::MASTER_FIELD_ARMED_FORCES,      // Armed Forces status
            3 => FieldRegistry::MASTER_FIELD_TIMES_MARRIED,     // Times married
            4 => FieldRegistry::MASTER_FIELD_SPOUSE_ANUMBER,    // Spouse A-Number
            5 => FieldRegistry::MASTER_FIELD_SPOUSE_TIMES_MARRIED, // Spouse times married
        ];

        $updates = [];

        foreach ($mappings as $form71_field => $form75_field) {
            $value = rgar($entry, $form71_field);
            if ($value !== '') {
                $updates[$form75_field] = $value;
            }
        }

        if (!empty($updates)) {
            MasterForm::update_fields($master_entry_id, $updates);
        }
    }

    /**
     * Get field mappings
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