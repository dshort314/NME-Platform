<?php
/**
 * Preliminary Eligibility Handler
 * 
 * Processes Form 78 submissions:
 * - Determines 3-year vs 5-year filing eligibility
 * - Sets controlling factor
 */

namespace NME\Topics\PreliminaryEligibility;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 78;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        
        add_action(
            'gform_after_submission_' . self::FORM_ID,
            [__CLASS__, 'after_submission'],
            10,
            2
        );
    }

    /**
     * Prepopulate form fields
     */
    public static function prepopulate_fields(array $form): array {
        $anumber = UserContext::get_anumber();

        foreach ($form['fields'] as &$field) {
            if (isset($field->inputName) && $field->inputName === 'anumber') {
                $field->defaultValue = $anumber;
            }
        }

        return $form;
    }

    /**
     * After submission: Update controlling factor in Master
     */
    public static function after_submission(array $entry, array $form): void {
        $parent_entry_id = UserContext::get_parent_entry_id();

        if (!$parent_entry_id) {
            return;
        }

        $controlling_factor = self::determine_controlling_factor($entry);

        if ($controlling_factor) {
            MasterForm::update_field(
                $parent_entry_id,
                FieldRegistry::MASTER_FIELD_CONTROLLING_FACTOR,
                $controlling_factor
            );
        }
    }

    /**
     * Determine controlling factor from eligibility answers
     * 
     * This is a placeholder - actual logic needs to be ported from original plugin
     */
    private static function determine_controlling_factor(array $entry): string {
        // Default to 5-year (LPR)
        // Actual implementation would check specific field values
        // to determine DM, SC, LPR, LPRM, or LPRS
        
        return FieldRegistry::CF_LPR;
    }
}
