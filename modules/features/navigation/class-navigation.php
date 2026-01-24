<?php
/**
 * Navigation
 * 
 * Manages navigation between application sections.
 * Provides button rendering, routing, and completion checking.
 */

namespace NME\Features\Navigation;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class Navigation {

    /** @var array Navigation configuration */
    private static array $nav_configs = [];

    /**
     * Initialize navigation
     */
    public static function init(): void {
        self::register_default_configs();

        // Hooks will be registered here when we migrate functionality
        // add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        // add_filter('gform_pre_render', [__CLASS__, 'prepopulate_anumber_field']);
        // add_filter('gravityview_search_criteria', [__CLASS__, 'filter_gravityview_by_anumber'], 10, 3);
    }

    /**
     * Register default navigation configurations
     */
    private static function register_default_configs(): void {
        self::$nav_configs = [
            'information-about-you' => [
                'id'           => 'iay',
                'label'        => 'Information About You',
                'form_id'      => FieldRegistry::FORM_INFORMATION_ABOUT_YOU,
                'form_page_id' => FieldRegistry::PAGE_IAY_FORM,
                'view_page_id' => FieldRegistry::PAGE_IAY_VIEW,
                'view_id'      => FieldRegistry::VIEW_IAY,
                'icon'         => 'icon-information-dark.svg',
                'order'        => 10,
            ],
            'marital-history' => [
                'id'           => 'marital',
                'label'        => 'Marital History',
                'form_id'      => FieldRegistry::FORM_MARITAL_HISTORY,
                'form_page_id' => FieldRegistry::PAGE_MARITAL_HISTORY,
                'view_page_id' => FieldRegistry::PAGE_MARITAL_HISTORY,
                'view_id'      => FieldRegistry::VIEW_MARITAL_HISTORY,
                'icon'         => 'icon-marital-dark.svg',
                'order'        => 20,
            ],
            'residences' => [
                'id'           => 'residences',
                'label'        => 'Residences',
                'form_id'      => FieldRegistry::FORM_RESIDENCES,
                'form_page_id' => FieldRegistry::PAGE_RES_ADD,
                'view_page_id' => FieldRegistry::PAGE_RES_DASHBOARD_1,
                'view_id'      => FieldRegistry::VIEW_RESIDENCES,
                'icon'         => 'icon-home-dark.svg',
                'order'        => 30,
            ],
            'time-outside' => [
                'id'           => 'toc',
                'label'        => 'Time Outside the US',
                'form_id'      => FieldRegistry::FORM_TIME_OUTSIDE,
                'form_page_id' => FieldRegistry::PAGE_TOC_ADD,
                'view_page_id' => FieldRegistry::PAGE_TOC_DASHBOARD_1,
                'view_id'      => FieldRegistry::VIEW_TOC,
                'icon'         => 'icon-clock-dark.svg',
                'order'        => 40,
            ],
            'children' => [
                'id'           => 'children',
                'label'        => 'Children',
                'form_id'      => FieldRegistry::FORM_CHILDREN,
                'form_page_id' => FieldRegistry::PAGE_CHILDREN,
                'view_page_id' => FieldRegistry::PAGE_CHILDREN,
                'view_id'      => FieldRegistry::VIEW_CHILDREN,
                'icon'         => 'icon-children-dark.svg',
                'order'        => 50,
            ],
            'employment' => [
                'id'           => 'employment',
                'label'        => 'Employment & Schools',
                'form_id'      => FieldRegistry::FORM_EMPLOYMENT,
                'form_page_id' => FieldRegistry::PAGE_EMPLOYMENT,
                'view_page_id' => FieldRegistry::PAGE_EMPLOYMENT,
                'view_id'      => null,
                'icon'         => 'icon-employment-dark.svg',
                'order'        => 60,
            ],
            'additional-information' => [
                'id'           => 'additional',
                'label'        => 'Additional Information',
                'form_id'      => FieldRegistry::FORM_ADDITIONAL_INFORMATION,
                'form_page_id' => FieldRegistry::PAGE_ADDITIONAL_INFO,
                'view_page_id' => FieldRegistry::PAGE_ADDITIONAL_INFO,
                'view_id'      => FieldRegistry::VIEW_ADDITIONAL_INFO,
                'icon'         => 'icon-additional-dark.svg',
                'order'        => 70,
            ],
        ];

        // Allow other modules to add/modify navigation configs
        self::$nav_configs = apply_filters('nme_navigation_configs', self::$nav_configs);
    }

    // =========================================================================
    // CONFIGURATION ACCESS
    // =========================================================================

    /**
     * Get all navigation configurations
     */
    public static function get_configs(): array {
        return self::$nav_configs;
    }

    /**
     * Get a specific navigation configuration
     */
    public static function get_config(string $key): ?array {
        return self::$nav_configs[$key] ?? null;
    }

    /**
     * Get navigation configurations sorted by order
     */
    public static function get_sorted_configs(): array {
        $configs = self::$nav_configs;
        uasort($configs, function($a, $b) {
            return ($a['order'] ?? 99) <=> ($b['order'] ?? 99);
        });
        return $configs;
    }

    // =========================================================================
    // ENTRY CHECKING
    // =========================================================================

    /**
     * Check if an entry exists for a form and user
     * 
     * @param int $form_id Gravity Form ID
     * @param string|null $anumber User's A-Number (null for current user)
     * @return bool
     */
    public static function entry_exists(int $form_id, ?string $anumber = null): bool {
        global $wpdb;

        $anumber = $anumber ?? UserContext::get_anumber();

        if (!$anumber) {
            return false;
        }

        // Get the field ID that stores anumber for this form
        $anumber_field = self::get_anumber_field_for_form($form_id);

        if (!$anumber_field) {
            return false;
        }

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND e.status = 'active'
             AND em.meta_key = %s 
             AND em.meta_value = %s
             LIMIT 1",
            $form_id,
            (string) $anumber_field,
            $anumber
        ));

        return $exists !== null;
    }

    /**
     * Get the anumber field ID for a given form
     */
    private static function get_anumber_field_for_form(int $form_id): ?int {
        $map = [
            FieldRegistry::FORM_INFORMATION_ABOUT_YOU  => FieldRegistry::IAY_FIELD_ANUMBER,
            FieldRegistry::FORM_RESIDENCES             => FieldRegistry::RES_FIELD_ANUMBER,
            FieldRegistry::FORM_TIME_OUTSIDE           => FieldRegistry::TOC_FIELD_ANUMBER,
            // Add more as needed
        ];

        return $map[$form_id] ?? null;
    }

    /**
     * Check if the Master entry exists for current user
     */
    public static function has_master_entry(?int $user_id = null): bool {
        $parent_entry_id = UserContext::get_parent_entry_id($user_id);

        if (!$parent_entry_id) {
            return false;
        }

        return MasterForm::entry_exists($parent_entry_id);
    }

    // =========================================================================
    // URL HELPERS
    // =========================================================================

    /**
     * Get the URL for a navigation section
     * 
     * @param string $section_key Navigation config key
     * @param bool $view_mode Whether to return view URL (true) or form URL (false)
     * @return string|null URL or null if section not found
     */
    public static function get_section_url(string $section_key, bool $view_mode = true): ?string {
        $config = self::get_config($section_key);

        if (!$config) {
            return null;
        }

        $page_id = $view_mode ? $config['view_page_id'] : $config['form_page_id'];

        return get_permalink($page_id);
    }

    /**
     * Get URL with parent_entry_id parameter appended
     */
    public static function get_section_url_with_context(string $section_key, bool $view_mode = true): ?string {
        $url = self::get_section_url($section_key, $view_mode);

        if (!$url) {
            return null;
        }

        $parent_entry_id = UserContext::get_parent_entry_id();
        $anumber = UserContext::get_anumber();

        if ($parent_entry_id) {
            $url = add_query_arg('parent_entry_id', $parent_entry_id, $url);
        }

        if ($anumber) {
            $url = add_query_arg('anumber', $anumber, $url);
        }

        return $url;
    }

    // =========================================================================
    // PREPOPULATION
    // =========================================================================

    /**
     * Prepopulate anumber field in forms
     * 
     * @param array $form Gravity Form array
     * @return array Modified form
     */
    public static function prepopulate_anumber_field(array $form): array {
        $anumber = UserContext::get_anumber();

        if (!$anumber) {
            return $form;
        }

        foreach ($form['fields'] as &$field) {
            if (isset($field->inputName) && $field->inputName === 'anumber') {
                $field->defaultValue = $anumber;
            }
        }

        return $form;
    }

    // =========================================================================
    // GRAVITYVIEW FILTERING
    // =========================================================================

    /**
     * Filter GravityView entries by current user's anumber
     * 
     * @param array $criteria Search criteria
     * @param array $form_ids Form IDs
     * @param int $view_id GravityView ID
     * @return array Modified criteria
     */
    public static function filter_gravityview_by_anumber(array $criteria, array $form_ids, int $view_id): array {
        $anumber = UserContext::get_anumber();

        if (!$anumber) {
            return $criteria;
        }

        // Determine which field to filter by based on form
        $form_id = $form_ids[0] ?? null;
        $anumber_field = self::get_anumber_field_for_form($form_id);

        if ($anumber_field) {
            $criteria['search_criteria']['field_filters'][] = [
                'key'   => (string) $anumber_field,
                'value' => $anumber,
            ];
        }

        return $criteria;
    }

    // =========================================================================
    // JAVASCRIPT DATA
    // =========================================================================

    /**
     * Get navigation data for JavaScript
     * 
     * @return array Data for wp_localize_script
     */
    public static function get_js_data(): array {
        $configs = self::get_sorted_configs();
        $js_configs = [];

        foreach ($configs as $key => $config) {
            $js_configs[$key] = [
                'id'         => $config['id'],
                'label'      => $config['label'],
                'formId'     => $config['form_id'],
                'formUrl'    => self::get_section_url($key, false),
                'viewUrl'    => self::get_section_url($key, true),
                'hasEntry'   => self::entry_exists($config['form_id']),
            ];
        }

        return [
            'configs'       => $js_configs,
            'anumber'       => UserContext::get_anumber() ?? '',
            'parentEntryId' => UserContext::get_parent_entry_id() ?? 0,
            'hasMasterEntry'=> self::has_master_entry(),
        ];
    }
}