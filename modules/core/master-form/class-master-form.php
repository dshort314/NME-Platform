<?php
/**
 * Master Form
 * 
 * All interactions with Form 75 (Master) go through this class.
 * Provides a clean API instead of scattered GFAPI/wpdb calls.
 */

namespace NME\Core\MasterForm;

use NME\Core\FieldRegistry\FieldRegistry;

defined('ABSPATH') || exit;

class MasterForm {

    // =========================================================================
    // READ OPERATIONS
    // =========================================================================

    /**
     * Get a complete Master entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return array|null Entry array or null if not found
     */
    public static function get_entry(int $entry_id): ?array {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $entry = \GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            return null;
        }

        return $entry;
    }

    /**
     * Get a single field value from a Master entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @param int|string $field_id The field ID to retrieve
     * @return mixed Field value or null
     */
    public static function get_field(int $entry_id, $field_id) {
        global $wpdb;

        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT meta_value FROM {$wpdb->prefix}gf_entry_meta 
             WHERE entry_id = %d AND meta_key = %s",
            $entry_id,
            (string) $field_id
        ));

        return $value;
    }

    /**
     * Get multiple field values from a Master entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @param array $field_ids Array of field IDs to retrieve
     * @return array Associative array of field_id => value
     */
    public static function get_fields(int $entry_id, array $field_ids): array {
        global $wpdb;

        if (empty($field_ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($field_ids), '%s'));
        $params = array_merge([$entry_id], array_map('strval', $field_ids));

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value FROM {$wpdb->prefix}gf_entry_meta 
             WHERE entry_id = %d AND meta_key IN ($placeholders)",
            ...$params
        ), ARRAY_A);

        $values = [];
        foreach ($results as $row) {
            $values[$row['meta_key']] = $row['meta_value'];
        }

        return $values;
    }

    /**
     * Get the controlling factor for an entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return string|null Controlling factor value (DM, SC, LPR, LPRM, LPRS)
     */
    public static function get_controlling_factor(int $entry_id): ?string {
        return self::get_field($entry_id, FieldRegistry::MASTER_FIELD_CONTROLLING_FACTOR);
    }

    /**
     * Get the application date for an entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return string|null Application date
     */
    public static function get_application_date(int $entry_id): ?string {
        return self::get_field($entry_id, FieldRegistry::MASTER_FIELD_APPLICATION_DATE);
    }

    /**
     * Check if a Master entry exists
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return bool
     */
    public static function entry_exists(int $entry_id): bool {
        global $wpdb;

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}gf_entry 
             WHERE id = %d AND form_id = %d AND status = 'active'",
            $entry_id,
            FieldRegistry::FORM_MASTER
        ));

        return $exists !== null;
    }

    // =========================================================================
    // WRITE OPERATIONS
    // =========================================================================

    /**
     * Create a new Master entry
     * 
     * @param array $values Associative array of field_id => value
     * @return int|null New entry ID or null on failure
     */
    public static function create_entry(array $values): ?int {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $entry = [
            'form_id' => FieldRegistry::FORM_MASTER,
        ];

        foreach ($values as $field_id => $value) {
            $entry[$field_id] = $value;
        }

        $result = \GFAPI::add_entry($entry);

        if (is_wp_error($result)) {
            error_log('NME Platform: Failed to create Master entry - ' . $result->get_error_message());
            return null;
        }

        return (int) $result;
    }

    /**
     * Update a single field in a Master entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @param int|string $field_id The field ID to update
     * @param mixed $value The new value
     * @return bool Success
     */
    public static function update_field(int $entry_id, $field_id, $value): bool {
        if (!class_exists('GFAPI')) {
            return false;
        }

        $result = \GFAPI::update_entry_field($entry_id, (string) $field_id, $value);

        if (is_wp_error($result)) {
            error_log("NME Platform: Failed to update field {$field_id} in entry {$entry_id} - " . $result->get_error_message());
            return false;
        }

        return $result !== false;
    }

    /**
     * Update multiple fields in a Master entry
     * 
     * @param int $entry_id The Form 75 entry ID
     * @param array $values Associative array of field_id => value
     * @return bool Success (true if all updates succeeded)
     */
    public static function update_fields(int $entry_id, array $values): bool {
        $success = true;

        foreach ($values as $field_id => $value) {
            if (!self::update_field($entry_id, $field_id, $value)) {
                $success = false;
            }
        }

        return $success;
    }

    /**
     * Update a complete Master entry
     * 
     * @param array $entry Complete entry array with 'id' key
     * @return bool Success
     */
    public static function update_entry(array $entry): bool {
        if (!class_exists('GFAPI')) {
            return false;
        }

        if (!isset($entry['id'])) {
            return false;
        }

        $result = \GFAPI::update_entry($entry);

        if (is_wp_error($result)) {
            error_log('NME Platform: Failed to update Master entry - ' . $result->get_error_message());
            return false;
        }

        return $result !== false;
    }

    // =========================================================================
    // CONVENIENCE METHODS
    // =========================================================================

    /**
     * Get lookback years for an entry based on its controlling factor
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return int 3 or 5
     */
    public static function get_lookback_years(int $entry_id): int {
        $cf = self::get_controlling_factor($entry_id);
        return $cf ? FieldRegistry::get_lookback_years($cf) : FieldRegistry::LOOKBACK_5_YEAR;
    }

    /**
     * Get days required for an entry based on its controlling factor
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return int 548 or 913
     */
    public static function get_days_required(int $entry_id): int {
        $cf = self::get_controlling_factor($entry_id);
        return $cf ? FieldRegistry::get_days_required($cf) : FieldRegistry::DAYS_REQUIRED_5_YEAR;
    }

    /**
     * Check if an entry is a 3-year filer
     * 
     * @param int $entry_id The Form 75 entry ID
     * @return bool
     */
    public static function is_three_year_filer(int $entry_id): bool {
        $cf = self::get_controlling_factor($entry_id);
        return $cf ? FieldRegistry::is_three_year($cf) : false;
    }

    /**
     * Get the Form 75 form object
     * 
     * @return array|null Form array or null
     */
    public static function get_form(): ?array {
        if (!class_exists('GFAPI')) {
            return null;
        }

        $form = \GFAPI::get_form(FieldRegistry::FORM_MASTER);

        if (!$form) {
            return null;
        }

        return $form;
    }
}