<?php
/**
 * Residences Handler
 * 
 * Processes Form 38 submissions:
 * - Links entries to parent Form 75
 * - Injects date calculation data for dashboard
 * - Handles duration and gap calculations
 */

namespace NME\Topics\Residences;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;
use NME\Features\DateCalculations\DateCalculator;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 38;

    /** @var array Page IDs where this handler runs */
    const PAGE_IDS = [
        FieldRegistry::PAGE_RES_ADD,
        FieldRegistry::PAGE_RES_DASHBOARD,
        FieldRegistry::PAGE_RES_LIST,
        FieldRegistry::PAGE_RES_EDIT,
    ];

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Inject parent entry data on relevant pages
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);

        // Prepopulate fields
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);

        // Country restriction
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'limit_countries']);
        add_filter('gform_pre_validation_' . self::FORM_ID, [__CLASS__, 'limit_countries']);
        add_filter('gform_pre_submission_filter_' . self::FORM_ID, [__CLASS__, 'limit_countries']);
        add_filter('gform_admin_pre_render_' . self::FORM_ID, [__CLASS__, 'limit_countries']);
    }

    /**
     * Enqueue assets on relevant pages
     */
    public static function maybe_enqueue_assets(): void {
        if (!is_page(self::PAGE_IDS)) {
            return;
        }

        $parent_entry_id = UserContext::get_parent_entry_id();

        if (!$parent_entry_id) {
            return;
        }

        // Add inline script with calculation data
        add_action('wp_footer', function() use ($parent_entry_id) {
            self::output_calculation_data($parent_entry_id);
        });
    }

    /**
     * Output calculation data for JavaScript
     */
    private static function output_calculation_data(int $parent_entry_id): void {
        $controlling_factor = MasterForm::get_controlling_factor($parent_entry_id);
        $application_date = MasterForm::get_application_date($parent_entry_id);

        if (!$controlling_factor || !$application_date) {
            return;
        }

        $js_data = DateCalculator::get_js_data($parent_entry_id);
        ?>
        <script>
            window.nmeResidenceData = <?php echo json_encode($js_data); ?>;
            window.parentEntryResRequired = <?php echo json_encode($controlling_factor); ?>;
            window.parentEntryApplicationDate = <?php echo json_encode($application_date); ?>;
        </script>
        <?php
    }

    /**
     * Prepopulate form fields
     */
    public static function prepopulate_fields(array $form): array {
        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        foreach ($form['fields'] as &$field) {
            // Prepopulate A-Number
            if (isset($field->inputName) && $field->inputName === 'anumber') {
                $field->defaultValue = $anumber;
            }

            // Prepopulate parent_entry_id
            if ((int) $field->id === FieldRegistry::RES_FIELD_PARENT_ENTRY_ID) {
                $field->defaultValue = $parent_entry_id;
            }
        }

        return $form;
    }

    /**
     * Limit country dropdown to United States only
     */
    public static function limit_countries(array $form): array {
        foreach ($form['fields'] as &$field) {
            if ($field->type === 'address') {
                // Force US only
                $field->defaultCountry = 'United States';
                $field->addressType = 'us';
            }
        }

        return $form;
    }

    /**
     * Get entries for a parent
     */
    public static function get_entries_for_parent(int $parent_entry_id): array {
        global $wpdb;

        $entry_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = %s 
             AND em.meta_value = %s
             ORDER BY e.id ASC",
            self::FORM_ID,
            (string) FieldRegistry::RES_FIELD_PARENT_ENTRY_ID,
            (string) $parent_entry_id
        ));

        if (empty($entry_ids) || !class_exists('GFAPI')) {
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
     * Calculate total days for a parent's residences
     */
    public static function get_total_days(int $parent_entry_id): int {
        $entries = self::get_entries_for_parent($parent_entry_id);
        $total = 0;

        foreach ($entries as $entry) {
            $duration = (int) rgar($entry, FieldRegistry::RES_FIELD_DURATION);
            $total += $duration;
        }

        return $total;
    }

    /**
     * Check for gaps in residence history
     */
    public static function find_gaps(int $parent_entry_id): array {
        $entries = self::get_entries_for_parent($parent_entry_id);
        $gaps = [];

        // Sort by from date
        usort($entries, function($a, $b) {
            return strtotime(rgar($a, FieldRegistry::RES_FIELD_FROM_DATE)) 
                 - strtotime(rgar($b, FieldRegistry::RES_FIELD_FROM_DATE));
        });

        for ($i = 0; $i < count($entries) - 1; $i++) {
            $current_to = rgar($entries[$i], FieldRegistry::RES_FIELD_TO_DATE);
            $next_from = rgar($entries[$i + 1], FieldRegistry::RES_FIELD_FROM_DATE);

            $gap_days = DateCalculator::calculate_residence_gap($current_to, $next_from);

            if ($gap_days > 0) {
                $gaps[] = [
                    'after_entry' => $entries[$i]['id'],
                    'before_entry' => $entries[$i + 1]['id'],
                    'days' => $gap_days,
                ];
            }
        }

        return $gaps;
    }
}