<?php
/**
 * Preliminary Eligibility Configuration
 * 
 * All field lists, severity mappings, and thresholds.
 * Centralizes configuration that was previously scattered across constants.
 */

namespace NME\Features\PreliminaryEligibility;

use NME\Core\FieldRegistry\FieldRegistry;

defined('ABSPATH') || exit;

class Config {

    /** @var int Form ID */
    const FORM_ID = 78;

    /** @var string Option key for admin settings */
    const OPTION_KEY = 'nme_prelim_settings';

    /** @var int Threshold for CODE fields (trips > this value trigger modal) */
    const CODE_THRESHOLD = 12;

    /** @var string Default severity when not specified */
    const DEFAULT_SEVERITY = 'C1';

    /**
     * Fields that trigger modal when answered YES
     */
    const YES_FIELDS = [
        3,   // Parent became citizen prior
        20,  // Traveled outside US
        28,  // (warning only)
        29,  // (warning only)
        46,  // 
        48,  // 
        49,  // 
        52,  // 
        53,  // (warning only)
        77,  // Other activities indicating lack of good moral character
        80,  // 
        87,  // Currently (first position answer)
    ];

    /**
     * Fields that trigger modal when answered NO
     */
    const NO_FIELDS = [
        1,   // Are you 18 years of age or older?
        88,  // Will you have lived with spouse for 3 years?
        89,  // Will spouse have been citizen for 3 years?
        18,  // Did you return to US no later than [date]?
        42,  // 
        45,  // 
        69,  // Did you return (alternate track)
        79,  // Disability question
        82,  // Long answer (second position)
        83,  // Long answer (second position)
        84,  // I don't agree
        91,  // I agree that I ought to retain counsel
        24,  // 
        72,  // 
    ];

    /**
     * Fields that can trigger on BOTH yes and no (with different messages)
     */
    const COMPLEX_FIELDS = [
        7,   // VAWA track selection
        30,  // (YES warning only, NO disqualifies)
        35,  // (YES warning only, NO disqualifies)
        54,  // (YES warning only, NO disqualifies)
    ];

    /**
     * Fields that trigger based on numeric threshold (> CODE_THRESHOLD)
     */
    const CODE_FIELDS = [
        21,  // Number of trips (5-year track)
        71,  // Number of trips (3-year track)
    ];

    /**
     * Fields where YES shows warning but doesn't disqualify
     */
    const WARNING_ONLY_FIELDS = [28, 29, 30, 53];

    /**
     * Fields where YES is warning-only but NO disqualifies
     */
    const YES_ONLY_NON_DISQUALIFYING = [35, 54];

    /**
     * Per-field severity defaults (C1 or C2)
     * C1 = Deferral page, C2 = See a Lawyer page
     */
    const FIELD_SEVERITY = [
        // NO -> C1
        1  => 'C1',
        88 => 'C1',
        89 => 'C1',
        18 => 'C1',
        69 => 'C1',
        // YES -> C2
        3  => 'C2',
        20 => 'C2',
        46 => 'C2',
        48 => 'C2',
        49 => 'C2',
        52 => 'C2',
        53 => 'C2',
        77 => 'C2',
        79 => 'C2',
        87 => 'C2',
        // NO -> C2
        80 => 'C2',
        82 => 'C2',
        83 => 'C2',
        84 => 'C2',
        91 => 'C2',
        // CODE -> C1
        21 => 'C1',
        71 => 'C1',
        // Intentionally no default: 24, 28, 29, 30, 35, 42, 45, 54, 72
    ];

    /**
     * Result page URLs
     */
    const PAGE_QUALIFY = '/yes-you-qualify/';
    const PAGE_DEFERRAL = '/eligible-greater-than-1-year/';
    const PAGE_LAWYER = '/see-a-lawyer/';

    /**
     * Label replacements - fields with dynamic date placeholders
     * Format: field_id => ['template' => '...', 'placeholder' => '[USC_CALCULATED_DATE]']
     * or field_id => ['html' => '...'] for static HTML replacement
     */
    const LABEL_REPLACEMENTS = [
        77 => [
            'html' => 'I have engaged in other activities which indicate a lack of "good moral character" at any time – whether or not as an adult or minor.  Note that this includes past or current membership in organizations promoting communism; engaging in violence; engaged in war crimes, persecution of any kind; membership in foreign military, police, or armed group; anything weapons related.  If you believe that you might have engaged conduct indicating a lack of moral character or conduct similar to that above, you must review the specific items <a href="/prohibited-conduct/" target="_blank">here</a> before selecting "No".'
        ],
        88 => [
            'template' => 'By [USC_CALCULATED_DATE], will you have lived with your spouse for 3 years?',
            'placeholder' => '[USC_CALCULATED_DATE]'
        ],
        89 => [
            'template' => 'By [USC_CALCULATED_DATE], will your U. S. Citizen spouse have been a U.S. Citizen for three (3) years?',
            'placeholder' => '[USC_CALCULATED_DATE]'
        ],
    ];

    /**
     * Get all field IDs that have any trigger
     */
    public static function get_all_trigger_fields(): array {
        return array_unique(array_merge(
            self::YES_FIELDS,
            self::NO_FIELDS,
            self::COMPLEX_FIELDS,
            self::CODE_FIELDS
        ));
    }

    /**
     * Get severity for a field
     */
    public static function get_field_severity(int $field_id): string {
        return self::FIELD_SEVERITY[$field_id] ?? self::DEFAULT_SEVERITY;
    }

    /**
     * Check if field is warning-only (doesn't disqualify)
     */
    public static function is_warning_only(int $field_id): bool {
        return in_array($field_id, self::WARNING_ONLY_FIELDS, true);
    }

    /**
     * Check if field is YES-only non-disqualifying
     */
    public static function is_yes_only_non_disqualifying(int $field_id): bool {
        return in_array($field_id, self::YES_ONLY_NON_DISQUALIFYING, true);
    }
}
