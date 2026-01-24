<?php
/**
 * Additional Information Handler
 * 
 * Processes Form 39 submissions:
 * - Syncs Section 9 questions to Form 75 (Master)
 * - Syncs demographics fields to Master
 * - Handles GravityView edit updates
 * 
 * NOTE: Form 39 uses the SAME field IDs as Form 75 for Section 9 questions.
 * This means fields sync directly without translation (field 774 → field 774).
 */

namespace NME\Topics\AdditionalInformation;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 39;

    /** @var int Field ID for parent entry reference (same as Master's self-ref) */
    const FIELD_PARENT_ENTRY_ID = 892;

    /** @var int Field ID for A-Number */
    const FIELD_ANUMBER = 1;

    /** @var int Page ID */
    const PAGE_ID = 710;

    /** @var int GravityView ID */
    const VIEW_ID = 701;

    /**
     * Section 9 question field IDs (same in Form 39 and Form 75)
     * These are Yes/No questions that sync directly
     */
    const SECTION_9_FIELDS = [
        774, 775, 776, 777, 778, 779, 780, 781, 782, 783,
        784, 785, 786, 787, 788, 789, 790, 791, 792, 793,
        794, 795, 796, 797, 798, 799, 800, 801, 802, 803,
        804, 805, 806, 807, 808, 809, 810, 811, 812, 813,
        814, 815, 816, 817, 818, 819, 820, 821, 822, 823,
        824, 825, 826, 827, 828, 829, 830, 831, 832, 833,
        834, 835, 836, 837, 838, 839, 840, 841, 842, 843,
        844, 845, 846, 847, 848, 849, 850, 851, 852, 853,
        854, 855, 856, 857, 858, 859, 860, 861, 862, 863,
        864, 865, 866, 867, 868, 869, 870, 871, 872, 873,
        874, 875, 876, 877, 878, 879, 880, 881, 882, 883,
        884, 885, 886, 887, 888, 889, 890,
    ];

    /**
     * Demographics field IDs (same in Form 39 and Form 75)
     */
    const DEMOGRAPHICS_FIELDS = [
        926, 927, 928, 929, 930, 931, 932, 933, 934, 935,
    ];

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

        if (Plugin::is_debug_enabled('additional-information')) {
            error_log('NME Platform [additional-information]: Handler initialized');
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
            if (Plugin::is_debug_enabled('additional-information')) {
                error_log('NME Platform [additional-information]: No parent_entry_id found for sync');
            }
            return;
        }

        // Verify parent entry exists
        if (!class_exists('GFAPI')) {
            return;
        }

        $parent_entry = \GFAPI::get_entry($parent_entry_id);

        if (is_wp_error($parent_entry)) {
            if (Plugin::is_debug_enabled('additional-information')) {
                error_log('NME Platform [additional-information]: Parent entry ' . $parent_entry_id . ' does not exist');
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
     * Sync Form 39 fields to Master Form 75
     * 
     * Since Form 39 uses the same field IDs as Form 75 for Section 9 questions,
     * we simply copy the values directly without field ID translation.
     */
    private static function sync_to_master(int $master_entry_id, array $entry): void {
        $updates = [];

        // Sync Section 9 fields (direct mapping - same field IDs)
        foreach (self::SECTION_9_FIELDS as $field_id) {
            $value = rgar($entry, (string) $field_id);
            if ($value !== '' && $value !== null) {
                $updates[$field_id] = $value;
            }
        }

        // Sync Demographics fields (direct mapping - same field IDs)
        foreach (self::DEMOGRAPHICS_FIELDS as $field_id) {
            $value = rgar($entry, (string) $field_id);
            if ($value !== '' && $value !== null) {
                $updates[$field_id] = $value;
            }
        }

        if (!empty($updates)) {
            MasterForm::update_fields($master_entry_id, $updates);

            if (Plugin::is_debug_enabled('additional-information')) {
                error_log('NME Platform [additional-information]: Synced ' . count($updates) . ' fields to Master entry ' . $master_entry_id);
            }
        }
    }

    /**
     * Get all syncable field IDs
     */
    public static function get_syncable_fields(): array {
        return array_merge(self::SECTION_9_FIELDS, self::DEMOGRAPHICS_FIELDS);
    }

    /**
     * Get Section 9 field IDs
     */
    public static function get_section_9_fields(): array {
        return self::SECTION_9_FIELDS;
    }

    /**
     * Get Demographics field IDs
     */
    public static function get_demographics_fields(): array {
        return self::DEMOGRAPHICS_FIELDS;
    }
}
