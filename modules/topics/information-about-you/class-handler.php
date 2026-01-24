<?php
/**
 * Information About You Handler
 * 
 * Processes Form 70 submissions:
 * - Creates Form 75 (Master) entry
 * - Maps fields from Form 70 to Form 75
 * - Sets user meta (anumber, parent_entry_id, dob)
 * - Handles GravityView edit updates
 */

namespace NME\Topics\InformationAboutYou;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 70;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Pre-submission: Create Master entry
        add_filter(
            'gform_pre_submission_filter_' . self::FORM_ID,
            [__CLASS__, 'create_master_entry'],
            10,
            1
        );

        // After submission: Update calculated values and user meta
        add_action(
            'gform_after_submission_' . self::FORM_ID,
            [__CLASS__, 'after_submission'],
            10,
            2
        );

        // Confirmation redirect
        add_filter(
            'gform_confirmation_' . self::FORM_ID,
            [__CLASS__, 'modify_confirmation'],
            10,
            4
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
     * Create Master entry before Form 70 submission
     */
    public static function create_master_entry(array $form): array {
        if (!class_exists('GFAPI')) {
            return $form;
        }

        // Get submitted values
        $anumber = rgpost('input_' . FieldRegistry::IAY_FIELD_ANUMBER);
        $dob = rgpost('input_' . FieldRegistry::IAY_FIELD_DOB);
        $lpr_date = rgpost('input_' . FieldRegistry::IAY_FIELD_LPR_DATE);

        // Build Master entry data
        $master_data = self::map_fields_to_master($_POST);

        // Create Master entry
        $master_entry_id = MasterForm::create_entry($master_data);

        if (!$master_entry_id) {
            error_log('NME Platform: Failed to create Master entry for Form 70 submission');
            return $form;
        }

        // Update Master entry with self-reference
        MasterForm::update_field($master_entry_id, FieldRegistry::MASTER_FIELD_SELF_REF, $master_entry_id);

        // Store Master entry ID in Form 70's hidden field
        $_POST['input_' . FieldRegistry::IAY_FIELD_PARENT_ENTRY_ID] = $master_entry_id;

        return $form;
    }

    /**
     * After submission: Update user meta and calculated fields
     */
    public static function after_submission(array $entry, array $form): void {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        // Get values from entry
        $anumber = rgar($entry, FieldRegistry::IAY_FIELD_ANUMBER);
        $dob = rgar($entry, FieldRegistry::IAY_FIELD_DOB);
        $parent_entry_id = rgar($entry, FieldRegistry::IAY_FIELD_PARENT_ENTRY_ID);

        // Set user meta
        if ($anumber) {
            UserContext::set_anumber($anumber, $user_id);
        }

        if ($dob) {
            UserContext::set_dob($dob, $user_id);
        }

        if ($parent_entry_id) {
            UserContext::set_parent_entry_id((int) $parent_entry_id, $user_id);
        }

        // Update calculated fields in Master
        if ($parent_entry_id) {
            self::update_master_calculated_fields((int) $parent_entry_id, $entry);
        }
    }

    /**
     * Modify confirmation to redirect to view page
     */
    public static function modify_confirmation($confirmation, array $form, array $entry, bool $ajax) {
        $view_url = get_permalink(FieldRegistry::PAGE_IAY_VIEW);

        if ($view_url) {
            $confirmation = ['redirect' => $view_url];
        }

        return $confirmation;
    }

    /**
     * Handle GravityView edit updates
     */
    public static function handle_gravityview_update(array $form, string $entry_id, $gv_entry): void {
        // Only process Form 70
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

        $parent_entry_id = rgar($entry, FieldRegistry::IAY_FIELD_PARENT_ENTRY_ID);

        if (!$parent_entry_id) {
            return;
        }

        // Sync updated fields to Master
        self::sync_to_master((int) $parent_entry_id, $entry);
    }

    /**
     * Map Form 70 POST data to Master entry fields
     */
    private static function map_fields_to_master(array $post_data): array {
        $master_data = [];

        // Field mappings: Form 70 field => Form 75 field
        $mappings = self::get_field_mappings();

        foreach ($mappings as $form70_field => $form75_field) {
            $input_key = 'input_' . $form70_field;

            // Handle compound fields (like name with .3, .6 suffixes)
            if (strpos($form70_field, '.') !== false) {
                $input_key = 'input_' . str_replace('.', '_', $form70_field);
            }

            if (isset($post_data[$input_key])) {
                $master_data[$form75_field] = $post_data[$input_key];
            }
        }

        return $master_data;
    }

    /**
     * Sync Form 70 entry to Master
     */
    private static function sync_to_master(int $master_entry_id, array $entry): void {
        $mappings = self::get_field_mappings();
        $updates = [];

        foreach ($mappings as $form70_field => $form75_field) {
            $value = rgar($entry, $form70_field);
            if ($value !== '') {
                $updates[$form75_field] = $value;
            }
        }

        if (!empty($updates)) {
            MasterForm::update_fields($master_entry_id, $updates);
        }
    }

    /**
     * Update calculated fields in Master entry
     */
    private static function update_master_calculated_fields(int $master_entry_id, array $entry): void {
        // Get values needed for calculations
        $dob = rgar($entry, FieldRegistry::IAY_FIELD_DOB);
        $lpr_date = rgar($entry, FieldRegistry::IAY_FIELD_LPR_DATE);
        $marital_status = rgar($entry, FieldRegistry::IAY_FIELD_MARITAL_STATUS);

        // Calculate controlling factor based on marital status and other factors
        // This is a simplified version - actual logic may be more complex
        $controlling_factor = self::determine_controlling_factor($entry);

        // Calculate application date (today's date or specified date)
        $application_date = date('Y-m-d');

        $updates = [
            FieldRegistry::MASTER_FIELD_CONTROLLING_FACTOR => $controlling_factor,
            FieldRegistry::MASTER_FIELD_APPLICATION_DATE   => $application_date,
        ];

        MasterForm::update_fields($master_entry_id, $updates);
    }

    /**
     * Determine the controlling factor based on entry data
     * 
     * This determines whether the applicant is a 3-year or 5-year filer.
     */
    private static function determine_controlling_factor(array $entry): string {
        // Default to LPR (5-year)
        // Actual logic would check marital status, spouse citizenship, etc.
        // This needs to match existing business logic

        $marital_status = rgar($entry, FieldRegistry::IAY_FIELD_MARITAL_STATUS);

        // Placeholder - actual implementation would be more complex
        // based on eligibility questions
        return FieldRegistry::CF_LPR;
    }

    /**
     * Get field mappings from Form 70 to Form 75
     */
    private static function get_field_mappings(): array {
        return [
            // A-Number
            FieldRegistry::IAY_FIELD_ANUMBER => FieldRegistry::MASTER_FIELD_ANUMBER,
            
            // DOB
            FieldRegistry::IAY_FIELD_DOB => FieldRegistry::MASTER_FIELD_DOB,
            
            // LPR Date
            FieldRegistry::IAY_FIELD_LPR_DATE => FieldRegistry::MASTER_FIELD_LPR_DATE,

            // Add additional field mappings as needed
            // Format: Form70_FieldID => Form75_FieldID
        ];
    }

    /**
     * Get protected fields that cannot be edited via GravityView
     */
    public static function get_protected_fields(): array {
        return [
            FieldRegistry::IAY_FIELD_DOB,
            FieldRegistry::IAY_FIELD_ANUMBER,
            FieldRegistry::IAY_FIELD_LPR_DATE,
        ];
    }
}