<?php
/**
 * Information About You - View Handler
 * 
 * Handles recalculation of date-dependent eligibility fields
 * when a user views their Form 70 entry on page 753 (GravityView 720).
 * 
 * When a user returns days/weeks/months after initial entry, the "Today"
 * date stored in their entry is stale. This handler updates field 24 (Today)
 * to the current date, recalculates all eligibility fields, and syncs
 * the updated values to both Form 70 and Form 75 (Master).
 * 
 * Short-circuit: If the stored Today date matches the current date, 
 * no recalculation is performed.
 * 
 * Debug testing: When debug mode is enabled and user is admin, a query
 * parameter ?nme_test_date=MM/DD/YYYY can be used to simulate any date.
 * 
 * @package NME\Topics\InformationAboutYou
 */

namespace NME\Topics\InformationAboutYou;

use NME\Core\Plugin;
use NME\Core\UserContext\UserContext;

defined('ABSPATH') || exit;

class ViewHandler {

    /** @var int Page ID where GravityView 720 is displayed */
    const PAGE_ID = 753;

    /** @var int Form ID */
    const FORM_ID = 70;

    /** @var string Module ID for debug logging */
    const MODULE_ID = 'information-about-you';

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Hook early enough to run before GravityView queries entries
        add_action('wp', [__CLASS__, 'maybe_recalculate_on_view'], 5);
    }

    /**
     * Check if we're on page 753 and recalculate if needed
     */
    public static function maybe_recalculate_on_view(): void {
        // Only run on page 753
        if (!is_page(self::PAGE_ID)) {
            return;
        }

        // Must be a logged-in user
        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        $debug = Plugin::is_debug_enabled(self::MODULE_ID);

        if ($debug) {
            error_log('NME Platform - IAY ViewHandler: Page 753 loaded by user ' . $user_id);
        }

        // Get the user's Form 70 entry
        $entry = self::get_user_entry($user_id);
        if (!$entry) {
            if ($debug) {
                error_log('NME Platform - IAY ViewHandler: No Form 70 entry found for user ' . $user_id);
            }
            return;
        }

        $entry_id = $entry['id'];

        // Determine "today" - allow debug override
        $today = self::get_today_date();

        // Format today for comparison and storage
        $today_formatted = $today->format('m/d/Y');

        // Short-circuit: if stored Today matches current date, do nothing
        $stored_today = trim($entry['24'] ?? '');
        if ($stored_today === $today_formatted) {
            if ($debug) {
                error_log('NME Platform - IAY ViewHandler: Stored Today (' . $stored_today . ') matches current date. No recalculation needed.');
            }
            return;
        }

        if ($debug) {
            error_log('NME Platform - IAY ViewHandler: Stored Today (' . $stored_today . ') differs from current date (' . $today_formatted . '). Recalculating...');
        }

        // Run the recalculation
        self::perform_recalculation($entry, $today, $debug);
    }

    /**
     * Get the current user's Form 70 entry.
     * Looks up by A-Number (field 10) matching user meta.
     * 
     * @param int $user_id
     * @return array|null The entry or null
     */
    private static function get_user_entry(int $user_id): ?array {
        // Get the user's A-Number
        $anumber = get_user_meta($user_id, 'anumber', true);
        if (empty($anumber)) {
            return null;
        }

        // Search Form 70 for entry with this A-Number (field 10)
        $search_criteria = [
            'status'        => 'active',
            'field_filters' => [
                [
                    'key'   => '10',
                    'value' => $anumber,
                ],
            ],
        ];

        $entries = \GFAPI::get_entries(self::FORM_ID, $search_criteria, null, ['offset' => 0, 'page_size' => 1]);

        if (empty($entries)) {
            return null;
        }

        return $entries[0];
    }

    /**
     * Determine the "today" date.
     * In debug mode, admins can override via query parameter.
     * 
     * @return \DateTime
     */
    private static function get_today_date(): \DateTime {
        // Check for debug date override
        if (Plugin::is_debug_enabled(self::MODULE_ID) && current_user_can('manage_options')) {
            $test_date = isset($_GET['nme_test_date']) ? sanitize_text_field($_GET['nme_test_date']) : '';
            if (!empty($test_date)) {
                $parsed = \DateTime::createFromFormat('m/d/Y', $test_date);
                if ($parsed !== false) {
                    $parsed->setTime(0, 0, 0);
                    error_log('NME Platform - IAY ViewHandler: DEBUG - Using test date override: ' . $test_date);
                    return $parsed;
                } else {
                    error_log('NME Platform - IAY ViewHandler: DEBUG - Could not parse test date: ' . $test_date . ' (expected MM/DD/YYYY)');
                }
            }
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        return $today;
    }

    /**
     * Perform the full recalculation and save results.
     * 
     * @param array $entry The Form 70 entry
     * @param \DateTime $today The date to use as "today"
     * @param bool $debug Whether debug logging is enabled
     */
    private static function perform_recalculation(array $entry, \DateTime $today, bool $debug): void {
        $entry_id = $entry['id'];
        $today_formatted = $today->format('m/d/Y');

        // Run the eligibility calculator
        $calc_result = EligibilityCalculator::recalculate($entry, $today);

        $dates  = $calc_result['dates'];
        $result = $calc_result['result'];
        $effective_married_value = $calc_result['married_value'];

        if ($debug) {
            error_log('NME Platform - IAY ViewHandler: Entry ' . $entry_id . ' recalculation results:');
            error_log('  Effective marriedValue: ' . $effective_married_value);
            error_log('  Controlling Factor: ' . $result['controllingFactor']);
            error_log('  Controlling Date: ' . (is_string($result['controllingDate']) ? $result['controllingDate'] : 'N/A'));
            error_log('  Controlling Desc: ' . $result['controllingDesc']);
            error_log('  Status: ' . $result['status']);
        }

        // === Update Form 70 entry ===

        // Update field 24 (Today)
        \GFAPI::update_entry_field($entry_id, '24', $today_formatted);

        // Update derived date fields (25, 26, 27, 28, 29, 30, 31, 32)
        $date_fields = EligibilityCalculator::format_dates_for_storage($dates);
        foreach ($date_fields as $field_id => $value) {
            \GFAPI::update_entry_field($entry_id, $field_id, $value);
        }

        // Update eligibility fields (34, 35, 36, 37)
        \GFAPI::update_entry_field($entry_id, '34', $result['controllingFactor']);
        \GFAPI::update_entry_field($entry_id, '35', $result['controllingDate']);
        \GFAPI::update_entry_field($entry_id, '36', $result['controllingDesc']);
        \GFAPI::update_entry_field($entry_id, '37', $result['status']);

        if ($debug) {
            error_log('NME Platform - IAY ViewHandler: Updated Form 70 entry ' . $entry_id . ' fields.');
        }

        // === Sync to Form 75 (Master) ===
        $parent_entry_id = trim($entry['50'] ?? '');
        if (empty($parent_entry_id)) {
            // Try user meta as fallback
            $user_id = get_current_user_id();
            $parent_entry_id = get_user_meta($user_id, 'parent_entry_id', true);
        }

        if (!empty($parent_entry_id)) {
            $field_map = EligibilityCalculator::get_form75_field_map();

            // Build the values to sync
            $values_to_sync = [];

            // Today
            $values_to_sync['24'] = $today_formatted;

            // Derived dates
            foreach ($date_fields as $f70_id => $value) {
                $values_to_sync[$f70_id] = $value;
            }

            // Eligibility fields
            $values_to_sync['34'] = $result['controllingFactor'];
            $values_to_sync['35'] = $result['controllingDate'];
            $values_to_sync['36'] = $result['controllingDesc'];
            $values_to_sync['37'] = $result['status'];

            // Sync each field to Form 75
            foreach ($values_to_sync as $f70_id => $value) {
                if (isset($field_map[$f70_id])) {
                    $f75_id = $field_map[$f70_id];
                    \GFAPI::update_entry_field($parent_entry_id, $f75_id, $value);
                }
            }

            if ($debug) {
                error_log('NME Platform - IAY ViewHandler: Synced to Form 75 entry ' . $parent_entry_id);
            }
        } else {
            error_log('NME Platform - IAY ViewHandler: WARNING - No parent entry ID found for entry ' . $entry_id . '. Form 75 not updated.');
        }

        if ($debug) {
            error_log('NME Platform - IAY ViewHandler: Recalculation complete for entry ' . $entry_id);
        }
    }
}
