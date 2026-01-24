<?php
/**
 * Additional Information Handler
 * 
 * Processes Form 39 submissions:
 * - Works with Counsel module for bouncer modals
 * - Handles eligibility questions
 */

namespace NME\Topics\AdditionalInformation;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 39;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        
        // GravityView edit updates
        add_action(
            'gravityview/edit_entry/after_update',
            [__CLASS__, 'handle_gravityview_update'],
            10,
            3
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
     * Handle GravityView edit updates
     */
    public static function handle_gravityview_update(array $form, string $entry_id, $gv_entry): void {
        if ((int) $form['id'] !== self::FORM_ID) {
            return;
        }

        // Additional processing after edit if needed
    }
}
