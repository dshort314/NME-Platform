<?php
/**
 * Time Outside Handler
 * 
 * Processes Form 42 submissions:
 * - Links entries to parent Form 75
 * - Injects date calculation data for dashboard
 * - Handles physical presence calculations
 * - Detects long trips (6+ months)
 */

namespace NME\Topics\TimeOutside;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;
use NME\Features\DateCalculations\DateCalculator;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 42;

    /** @var array Page IDs where this handler runs */
    const PAGE_IDS = [
        FieldRegistry::PAGE_TOC_ADD,
        FieldRegistry::PAGE_TOC_DASHBOARD_1,
        FieldRegistry::PAGE_TOC_DASHBOARD_2,
    ];

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Inject parent entry data on relevant pages
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue_assets']);

        // Prepopulate fields
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
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
            window.nmeTocData = <?php echo json_encode($js_data); ?>;
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
            if (isset($field->inputName) && $field->inputName === 'anumber') {
                $field->defaultValue = $anumber;
            }

            if ((int) $field->id === FieldRegistry::TOC_FIELD_PARENT_ENTRY_ID) {
                $field->defaultValue = $parent_entry_id;
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
            (string) FieldRegistry::TOC_FIELD_PARENT_ENTRY_ID,
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
     * Calculate total days abroad for a parent
     */
    public static function get_total_days_abroad(int $parent_entry_id): int {
        $entries = self::get_entries_for_parent($parent_entry_id);
        $total = 0;

        foreach ($entries as $entry) {
            $duration = (int) rgar($entry, FieldRegistry::TOC_FIELD_DURATION);
            $total += $duration;
        }

        return $total;
    }

    /**
     * Find long trips (6+ months / 183+ days)
     */
    public static function find_long_trips(int $parent_entry_id): array {
        $entries = self::get_entries_for_parent($parent_entry_id);
        $long_trips = [];

        foreach ($entries as $entry) {
            $departure = rgar($entry, FieldRegistry::TOC_FIELD_DEPARTURE_DATE);
            $return = rgar($entry, FieldRegistry::TOC_FIELD_RETURN_DATE);

            if ($departure && $return && DateCalculator::is_long_trip($departure, $return)) {
                $long_trips[] = [
                    'entry_id'  => $entry['id'],
                    'departure' => $departure,
                    'return'    => $return,
                    'days'      => DateCalculator::calculate_trip_duration($departure, $return),
                    'countries' => rgar($entry, FieldRegistry::TOC_FIELD_COUNTRIES),
                ];
            }
        }

        return $long_trips;
    }

    /**
     * Calculate physical presence status
     */
    public static function get_physical_presence_status(int $parent_entry_id): array {
        $days_abroad = self::get_total_days_abroad($parent_entry_id);
        return DateCalculator::calculate_physical_presence($parent_entry_id, $days_abroad);
    }
}