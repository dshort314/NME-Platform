<?php
/**
 * GPNF Handler
 * 
 * Manages nested form entries and their attachment to the Master form (75).
 * Handles entry keys, parent linking, and calculation triggers.
 */

namespace NME\Features\GPNF;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class GPNFHandler {

    /** @var array Nested form configurations */
    private static array $nested_configs = [];

    /**
     * Initialize GPNF handling
     */
    public static function init(): void {
        self::register_default_configs();

        // Register hooks for each nested form
        foreach (self::$nested_configs as $config) {
            add_action(
                'gform_entry_post_save_' . $config['child_form_id'],
                [__CLASS__, 'handle_entry_post_save'],
                10,
                2
            );
        }

        // Calculation updates when entries change status
        add_action('gform_update_status', [__CLASS__, 'handle_status_change'], 10, 3);
    }

    /**
     * Register default nested form configurations
     */
    private static function register_default_configs(): void {
        self::$nested_configs = [
            'residences' => [
                'child_form_id'     => FieldRegistry::FORM_RESIDENCES,
                'parent_form_id'    => FieldRegistry::FORM_MASTER,
                'parent_field_id'   => FieldRegistry::MASTER_FIELD_NESTED_RESIDENCES,
                'child_parent_field'=> FieldRegistry::RES_FIELD_PARENT_ENTRY_ID,
            ],
            'time-outside' => [
                'child_form_id'     => FieldRegistry::FORM_TIME_OUTSIDE,
                'parent_form_id'    => FieldRegistry::FORM_MASTER,
                'parent_field_id'   => FieldRegistry::MASTER_FIELD_NESTED_TOC,
                'child_parent_field'=> FieldRegistry::TOC_FIELD_PARENT_ENTRY_ID,
            ],
            'children' => [
                'child_form_id'     => FieldRegistry::FORM_CHILDREN,
                'parent_form_id'    => FieldRegistry::FORM_MASTER,
                'parent_field_id'   => FieldRegistry::MASTER_FIELD_NESTED_CHILDREN,
                'child_parent_field'=> 1, // Verify this field ID
            ],
            'employment' => [
                'child_form_id'     => FieldRegistry::FORM_EMPLOYMENT,
                'parent_form_id'    => FieldRegistry::FORM_MASTER,
                'parent_field_id'   => FieldRegistry::MASTER_FIELD_NESTED_EMPLOYMENT,
                'child_parent_field'=> 1, // Verify this field ID
            ],
            'criminal' => [
                'child_form_id'     => FieldRegistry::FORM_CRIMINAL_HISTORY,
                'parent_form_id'    => FieldRegistry::FORM_MASTER,
                'parent_field_id'   => FieldRegistry::MASTER_FIELD_NESTED_CRIMINAL,
                'child_parent_field'=> 1, // Verify this field ID
            ],
        ];

        // Allow other modules to add/modify configs
        self::$nested_configs = apply_filters('nme_gpnf_configs', self::$nested_configs);
    }

    // =========================================================================
    // CONFIGURATION ACCESS
    // =========================================================================

    /**
     * Get all nested form configurations
     */
    public static function get_configs(): array {
        return self::$nested_configs;
    }

    /**
     * Get configuration for a specific child form
     */
    public static function get_config_by_form(int $form_id): ?array {
        foreach (self::$nested_configs as $config) {
            if ($config['child_form_id'] === $form_id) {
                return $config;
            }
        }
        return null;
    }

    // =========================================================================
    // ENTRY HANDLING
    // =========================================================================

    /**
     * Handle entry post save - attach to parent
     * 
     * @param array $entry The saved entry
     * @param array $form The form object
     */
    public static function handle_entry_post_save(array $entry, array $form): void {
        $config = self::get_config_by_form((int) $form['id']);

        if (!$config) {
            return;
        }

        $parent_entry_id = self::get_parent_entry_id_from_entry($entry, $config);

        if (!$parent_entry_id) {
            $parent_entry_id = UserContext::get_parent_entry_id();
        }

        if (!$parent_entry_id) {
            error_log('NME Platform GPNF: No parent_entry_id found for entry ' . $entry['id']);
            return;
        }

        // Verify parent entry exists
        if (!MasterForm::entry_exists($parent_entry_id)) {
            error_log('NME Platform GPNF: Parent entry ' . $parent_entry_id . ' does not exist');
            return;
        }

        // Attach the entry
        self::attach_entry_to_parent($entry, $parent_entry_id, $config);

        // Trigger calculation update
        self::trigger_parent_calculations($parent_entry_id);
    }

    /**
     * Get parent_entry_id from the child entry
     */
    private static function get_parent_entry_id_from_entry(array $entry, array $config): ?int {
        $field_id = $config['child_parent_field'];
        $value = $entry[$field_id] ?? null;

        return $value ? (int) $value : null;
    }

    /**
     * Attach a child entry to its parent
     */
    private static function attach_entry_to_parent(array $entry, int $parent_entry_id, array $config): void {
        if (!class_exists('GFAPI')) {
            return;
        }

        // Build GPNF entry key format
        $entry_key = self::build_entry_key($entry['id'], $parent_entry_id, $config);

        // Update the child entry with GPNF metadata
        $entry['gpnf_entry_parent'] = $parent_entry_id;
        $entry['gpnf_entry_parent_form'] = $config['parent_form_id'];
        $entry['gpnf_entry_nested_form_field'] = $config['parent_field_id'];

        $result = \GFAPI::update_entry($entry);

        if (is_wp_error($result)) {
            error_log('NME Platform GPNF: Failed to update entry ' . $entry['id'] . ' - ' . $result->get_error_message());
        }
    }

    /**
     * Build GPNF entry key
     * 
     * Format: entry_id,hash
     */
    private static function build_entry_key(int $entry_id, int $parent_entry_id, array $config): string {
        // GPNF uses a specific hash format - this is a simplified version
        // The actual hash may need to match GPNF's implementation
        $hash = wp_hash($entry_id . $parent_entry_id . $config['parent_field_id']);
        return $entry_id . ',' . substr($hash, 0, 10);
    }

    // =========================================================================
    // STATUS CHANGES
    // =========================================================================

    /**
     * Handle entry status changes
     * 
     * @param int $entry_id Entry ID
     * @param string $status New status
     * @param string $prev_status Previous status
     */
    public static function handle_status_change(int $entry_id, string $status, string $prev_status): void {
        if (!class_exists('GFAPI')) {
            return;
        }

        $entry = \GFAPI::get_entry($entry_id);

        if (is_wp_error($entry)) {
            return;
        }

        $config = self::get_config_by_form((int) $entry['form_id']);

        if (!$config) {
            return;
        }

        // Get parent entry ID
        $parent_entry_id = $entry['gpnf_entry_parent'] ?? null;

        if (!$parent_entry_id) {
            $parent_entry_id = self::get_parent_entry_id_from_entry($entry, $config);
        }

        if ($parent_entry_id) {
            self::trigger_parent_calculations((int) $parent_entry_id);
        }
    }

    // =========================================================================
    // CALCULATIONS
    // =========================================================================

    /**
     * Trigger recalculation of parent form calculated fields
     */
    public static function trigger_parent_calculations(int $parent_entry_id): void {
        if (!class_exists('GFAPI')) {
            return;
        }

        $entry = MasterForm::get_entry($parent_entry_id);

        if (!$entry) {
            return;
        }

        $form = MasterForm::get_form();

        if (!$form) {
            return;
        }

        // Trigger Gravity Forms calculation refresh
        // This will recalculate any fields that depend on nested form totals
        do_action('gform_after_update_entry', $form, $parent_entry_id, $entry);
    }

    // =========================================================================
    // QUERY HELPERS
    // =========================================================================

    /**
     * Get all child entries for a parent
     * 
     * @param int $parent_entry_id Parent Form 75 entry ID
     * @param int $child_form_id Child form ID
     * @return array Array of entry arrays
     */
    public static function get_child_entries(int $parent_entry_id, int $child_form_id): array {
        global $wpdb;

        $config = self::get_config_by_form($child_form_id);

        if (!$config) {
            return [];
        }

        $entry_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = %s 
             AND em.meta_value = %s",
            $child_form_id,
            (string) $config['child_parent_field'],
            (string) $parent_entry_id
        ));

        if (empty($entry_ids)) {
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
     * Count child entries for a parent
     * 
     * @param int $parent_entry_id Parent Form 75 entry ID
     * @param int $child_form_id Child form ID
     * @return int Count
     */
    public static function count_child_entries(int $parent_entry_id, int $child_form_id): int {
        global $wpdb;

        $config = self::get_config_by_form($child_form_id);

        if (!$config) {
            return 0;
        }

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = %s 
             AND em.meta_value = %s",
            $child_form_id,
            (string) $config['child_parent_field'],
            (string) $parent_entry_id
        ));
    }

    /**
     * Get sum of a field across all child entries
     * 
     * @param int $parent_entry_id Parent Form 75 entry ID
     * @param int $child_form_id Child form ID
     * @param int $field_id Field to sum
     * @return float Sum
     */
    public static function sum_child_field(int $parent_entry_id, int $child_form_id, int $field_id): float {
        global $wpdb;

        $config = self::get_config_by_form($child_form_id);

        if (!$config) {
            return 0.0;
        }

        $sum = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(CAST(em_sum.meta_value AS DECIMAL(10,2)))
             FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em_parent ON e.id = em_parent.entry_id
             INNER JOIN {$wpdb->prefix}gf_entry_meta em_sum ON e.id = em_sum.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em_parent.meta_key = %s 
             AND em_parent.meta_value = %s
             AND em_sum.meta_key = %s",
            $child_form_id,
            (string) $config['child_parent_field'],
            (string) $parent_entry_id,
            (string) $field_id
        ));

        return (float) ($sum ?? 0);
    }
}