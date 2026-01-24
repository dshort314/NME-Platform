<?php
/**
 * Preliminary Eligibility Assets
 * 
 * Enqueues frontend JS/CSS for Form 78:
 * - Modal system for triggered answers
 * - Dynamic label replacements
 * - Destination page message retrieval
 */

namespace NME\Features\PreliminaryEligibility;

use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Assets {

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Form page assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_form_assets'], 20);

        // Destination page assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_destination_assets'], 25);

        // TRD date calculator for edit page
        add_action('wp_head', [__CLASS__, 'add_edit_page_date_calculator']);
    }

    /**
     * Enqueue assets for Form 78 pages
     */
    public static function enqueue_form_assets(): void {
        // Only load when Form 78 might be present
        // Could add more specific page detection if needed

        $settings = Admin::get_settings();

        $config = [
            'form_id'          => Config::FORM_ID,
            'yes_fields'       => Config::YES_FIELDS,
            'no_fields'        => Config::NO_FIELDS,
            'complex'          => Config::COMPLEX_FIELDS,
            'code_fields'      => Config::CODE_FIELDS,
            'code_thresh'      => Config::CODE_THRESHOLD,
            'severity_map'     => Config::FIELD_SEVERITY,
            'default_severity' => Config::DEFAULT_SEVERITY,
            'c1_default'       => !empty($settings['c1_default']) ? wpautop($settings['c1_default']) : '',
            'c2_default'       => !empty($settings['c2_default']) ? wpautop($settings['c2_default']) : '',
            'overrides'        => [],
            'label_targets'    => Config::LABEL_REPLACEMENTS,
        ];

        // Process overrides
        if (!empty($settings['overrides']) && is_array($settings['overrides'])) {
            foreach ($settings['overrides'] as $fid => $row) {
                $fid = (int) $fid;
                if ($fid <= 0) continue;

                if (isset($row['both'])) {
                    $config['overrides'][$fid] = ['both' => wpautop($row['both'])];
                } else {
                    $config['overrides'][$fid] = [
                        'yes' => isset($row['yes']) ? wpautop($row['yes']) : '',
                        'no'  => isset($row['no']) ? wpautop($row['no']) : '',
                    ];
                }
            }
        }

        // Register and enqueue runtime JS
        wp_register_script(
            'nme-prelim-runtime',
            Plugin::get_module_url('preliminary-eligibility') . 'assets/js/runtime.js',
            ['jquery'],
            Plugin::VERSION,
            true
        );

        wp_add_inline_script(
            'nme-prelim-runtime',
            'window.NME_Prelim_Settings = ' . wp_json_encode($config) . ';',
            'before'
        );

        wp_enqueue_script('nme-prelim-runtime');

        // Enqueue CSS
        wp_enqueue_style(
            'nme-prelim-frontend',
            Plugin::get_module_url('preliminary-eligibility') . 'assets/css/frontend.css',
            [],
            Plugin::VERSION
        );
    }

    /**
     * Enqueue assets for destination pages
     */
    public static function enqueue_destination_assets(): void {
        if (!is_page()) return;

        global $post;
        $slug = $post->post_name ?? '';

        if (!in_array($slug, ['eligible-greater-than-1-year', 'see-a-lawyer'], true)) {
            return;
        }

        wp_enqueue_script(
            'nme-prelim-destinations',
            Plugin::get_module_url('preliminary-eligibility') . 'assets/js/destination-pages.js',
            ['jquery'],
            Plugin::VERSION,
            true
        );

        wp_localize_script('nme-prelim-destinations', 'nmePrelimDestinations', [
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'page_slug' => $slug,
        ]);
    }

    /**
     * Add TRD date calculator script for edit page (page 873)
     */
    public static function add_edit_page_date_calculator(): void {
        if (!is_page(873)) return;

        ?>
        <script>
        function calculateAndUpdateLegends() {
            const today = new Date();

            // Field 18: today - 4 years 6 months
            const targetDate1 = new Date(today.getFullYear() - 4, today.getMonth() + 6, today.getDate());
            const formattedDate1 = `${String(targetDate1.getMonth() + 1).padStart(2, '0')}/${String(targetDate1.getDate()).padStart(2, '0')}/${targetDate1.getFullYear()}`;
            const legendElement1 = document.querySelector('#field_78_18 .gfield_label');
            if (legendElement1) {
                legendElement1.textContent = `Did you return to the U. S. no later than ${formattedDate1} from your trip that exceeded 6 months?`;
            }

            // Field 69: today - 2 years 6 months
            const targetDate3 = new Date(today.getFullYear() - 2, today.getMonth() + 6, today.getDate());
            const formattedDate3 = `${String(targetDate3.getMonth() + 1).padStart(2, '0')}/${String(targetDate3.getDate()).padStart(2, '0')}/${targetDate3.getFullYear()}`;
            const legendElement3 = document.querySelector('#field_78_69 .gfield_label');
            if (legendElement3) {
                legendElement3.textContent = `Did you return to the U. S. no later than ${formattedDate3} from your trip that exceeded 6 months?`;
            }

            // Field 4: today + 1 year
            const targetDate2 = new Date(today.getFullYear() + 1, today.getMonth(), today.getDate());
            const formattedDate2 = `${String(targetDate2.getMonth() + 1).padStart(2, '0')}/${String(targetDate2.getDate()).padStart(2, '0')}/${targetDate2.getFullYear()}`;
            const legendElement2 = document.querySelector('#field_78_4 .gfield_label');
            if (legendElement2) {
                legendElement2.textContent = `Have you been a legal permanent resident for longer than 4 years and 9 months or will reach that time on or before ${formattedDate2}?`;
            }
        }

        document.addEventListener('DOMContentLoaded', calculateAndUpdateLegends);
        </script>
        <?php
    }
}
