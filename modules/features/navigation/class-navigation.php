<?php
/**
 * Navigation Handler
 * 
 * Creates navigation buttons and handles conditional form/view display
 * with automatic query parameter management for application forms.
 * Integrates with Access Control to disable buttons for locked users.
 */

namespace NME\Features\Navigation;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\AccessControl\AccessControl;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Navigation {

    /** @var string Current A-Number for GravityView filtering */
    private static string $current_anumber = '';

    /** @var array Page configurations for navigation */
    private static array $page_configs = [];

    /**
     * Initialize navigation
     */
    public static function init(): void {
        self::register_default_configs();

        // Shortcode for navigation buttons
        add_shortcode('application_nav_buttons', [__CLASS__, 'render_navigation_buttons']);

        // Enqueue assets
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

        // Conditional content injection
        add_action('wp_footer', [__CLASS__, 'inject_conditional_content']);

        // Form prepopulation
        add_filter('gform_pre_render', [__CLASS__, 'prepopulate_anumber_field']);

        // AJAX handlers
        add_action('wp_ajax_nme_check_form_entry', [__CLASS__, 'ajax_check_form_entry']);
        add_action('wp_ajax_nopriv_nme_check_form_entry', [__CLASS__, 'ajax_check_form_entry']);

        // Filter for external plugins to extend navigation
        add_filter('nme_navigation_configs', [__CLASS__, 'get_base_configs'], 10);
    }

    /**
     * Register default page configurations
     */
    private static function register_default_configs(): void {
        self::$page_configs = [
            '/application/information-about-you/' => [
                'form_id'        => FieldRegistry::FORM_INFORMATION_ABOUT_YOU,
                'field_id'       => FieldRegistry::IAY_FIELD_ANUMBER,
                'view_id'        => FieldRegistry::VIEW_IAY,
                'has_nav_button' => true,
                'button_id'      => 'iay-button',
                'restricted'     => true,  // Subject to lockout
            ],
            '/application/residences/' => [
                'form_id'        => FieldRegistry::FORM_RESIDENCES,
                'field_id'       => FieldRegistry::RES_FIELD_ANUMBER,
                'view_id'        => FieldRegistry::VIEW_RESIDENCES,
                'has_nav_button' => true,
                'button_id'      => 'residences-button',
                'restricted'     => true,
            ],
            '/application/time-outside-the-us/' => [
                'form_id'        => FieldRegistry::FORM_TIME_OUTSIDE,
                'field_id'       => FieldRegistry::TOC_FIELD_PARENT_ENTRY_ID,
                'view_id'        => FieldRegistry::VIEW_TOC_ALT,
                'has_nav_button' => true,
                'button_id'      => 'time-outside-button',
                'restricted'     => true,
            ],
            '/application/marital-history/' => [
                'form_id'        => FieldRegistry::FORM_MARITAL_HISTORY,
                'field_id'       => 7,  // A-Number field in Form 71
                'view_id'        => FieldRegistry::VIEW_MARITAL_HISTORY,
                'has_nav_button' => true,
                'button_id'      => 'marital-history-button',
                'restricted'     => true,
            ],
            '/application/children/' => [
                'form_id'        => FieldRegistry::FORM_CHILDREN,
                'field_id'       => 3,  // A-Number field in Form 72
                'view_id'        => FieldRegistry::VIEW_CHILDREN,
                'has_nav_button' => true,
                'button_id'      => 'children-button',
                'restricted'     => true,
            ],
            '/application/employment-school/' => [
                'form_id'        => FieldRegistry::FORM_EMPLOYMENT,
                'field_id'       => 3,  // A-Number field in Form 73
                'view_id'        => 0,
                'has_nav_button' => true,
                'button_id'      => 'employment-school-button',
                'restricted'     => true,
            ],
            '/application/additional-information/' => [
                'form_id'        => FieldRegistry::FORM_ADDITIONAL_INFORMATION,
                'field_id'       => 1,  // A-Number field in Form 39
                'view_id'        => FieldRegistry::VIEW_ADDITIONAL_INFO,
                'has_nav_button' => true,
                'button_id'      => 'additional-information-button',
                'restricted'     => true,
            ],
            '/application/documents/' => [
                'form_id'        => 0,
                'field_id'       => 0,
                'view_id'        => 0,
                'has_nav_button' => true,
                'button_id'      => 'documents-button',
                'restricted'     => false,  // Always accessible
            ],
        ];
    }

    /**
     * Get base navigation configurations (for filter)
     */
    public static function get_base_configs(array $configs = []): array {
        return array_merge($configs, self::$page_configs);
    }

    /**
     * Get all page configurations (including those added by filters)
     */
    public static function get_all_configs(): array {
        return apply_filters('nme_navigation_configs', self::$page_configs);
    }

    /**
     * Check if current user is locked out
     * 
     * @return bool True if user is locked out
     */
    public static function is_user_locked(): bool {
        if (!class_exists('\\NME\\Core\\AccessControl\\AccessControl')) {
            return false;
        }

        return AccessControl::is_locked_out();
    }

    /**
     * Get lockout information for JavaScript
     * 
     * @return array Lockout data
     */
    public static function get_lockout_data(): array {
        if (!class_exists('\\NME\\Core\\AccessControl\\AccessControl')) {
            return [
                'is_locked' => false,
                'unlock_date' => null,
                'unlock_date_formatted' => null,
            ];
        }

        return [
            'is_locked' => AccessControl::is_locked_out(),
            'unlock_date' => AccessControl::get_unlock_date(),
            'unlock_date_formatted' => AccessControl::get_formatted_unlock_date(),
        ];
    }

    /**
     * Enqueue navigation assets
     */
    public static function enqueue_assets(): void {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';

        if (strpos($current_url, '/application/') === false) {
            return;
        }

        $module_url = NME_PLATFORM_URL . 'modules/features/navigation/assets/';

        // CSS
        wp_enqueue_style(
            'nme-navigation',
            $module_url . 'css/navigation.css',
            [],
            NME_PLATFORM_VERSION
        );

        // JavaScript
        wp_enqueue_script(
            'nme-navigation',
            $module_url . 'js/navigation.js',
            ['jquery'],
            NME_PLATFORM_VERSION,
            true
        );

        // Localize script data including lockout status
        $lockout_data = self::get_lockout_data();

        wp_localize_script(
            'nme-navigation',
            'nmeNavigation',
            [
                'ajaxurl'               => admin_url('admin-ajax.php'),
                'nonce'                 => wp_create_nonce('nme_navigation_nonce'),
                'userid'                => get_current_user_id(),
                'anumber'               => UserContext::get_anumber(),
                'parent_entry_id'       => UserContext::get_parent_entry_id(),
                'is_locked'             => $lockout_data['is_locked'],
                'unlock_date'           => $lockout_data['unlock_date'],
                'unlock_date_formatted' => $lockout_data['unlock_date_formatted'],
                'purgatory_url'         => home_url('/purgatory/'),
            ]
        );
    }

    /**
     * Render navigation buttons shortcode
     */
    public static function render_navigation_buttons(): string {
        $is_locked = self::is_user_locked();
        $lockout_data = self::get_lockout_data();

        ob_start();
        ?>
        <div class="nme-nav-container" id="application-navigation" data-locked="<?php echo $is_locked ? 'true' : 'false'; ?>">
            <?php if ($is_locked): ?>
            <div class="nme-nav-lockout-notice">
                <p><strong>Limited Access:</strong> Your application access is currently restricted. Full access will be restored on <?php echo esc_html($lockout_data['unlock_date_formatted']); ?>.</p>
                <p><a href="/purgatory/">View your eligibility status</a></p>
            </div>
            <?php endif; ?>

            <div class="nme-nav-grid dark-theme">
                <!-- Information About You -->
                <a href="#" id="iay-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-view-url="/application/information-about-you-view/" 
                   data-form-url="/application/information-about-you/" 
                   data-form-id="70" 
                   data-field-id="10" 
                   data-view-page-id="753" 
                   data-form-page-id="703"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Information About You</span>
                </a>

                <!-- Time Outside the US -->
                <a href="/application/time-outside-the-us/" id="time-outside-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="705"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Time Outside the US</span>
                </a>
                
                <!-- Residences -->
                <a href="/application/residences/" id="residences-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="706"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Residences</span>
                </a>

                <!-- Marital History -->
                <a href="/application/marital-history/" id="marital-history-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="707"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Marital History</span>
                </a>

                <!-- Children -->
                <a href="/application/children/" id="children-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="708"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Children</span>
                </a>

                <!-- Employment & School -->
                <a href="/application/employment-school/" id="employment-school-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="709"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Employment & School</span>
                </a>
            </div>

            <div class="nme-nav-grid dark-theme centered-row">
                <!-- Additional Information -->
                <a href="/application/additional-information/" id="additional-information-button" class="nme-nav-button <?php echo $is_locked ? 'disabled locked' : ''; ?>" 
                   data-page-id="710"
                   data-restricted="true"
                   <?php echo $is_locked ? 'aria-disabled="true"' : ''; ?>>
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Additional Information</span>
                </a>

                <!-- Documents - Always accessible -->
                <a href="/application/documents/" id="documents-button" class="nme-nav-button" 
                   data-page-id="712"
                   data-restricted="false">
                    <span class="nme-nav-button-icon"></span>
                    <span class="nme-nav-button-title">Documents</span>
                </a>
            </div>
            
            <?php 
            // Allow other plugins/modules to add buttons
            do_action('nme_navigation_buttons'); 
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Inject conditional content into pages
     */
    public static function inject_conditional_content(): void {
        // Only run on main query and single pages
        if (!is_main_query() || !is_page()) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        if (empty($anumber)) {
            return;
        }

        // Find matching configuration for current page
        $matching_config = null;
        $all_configs = self::get_all_configs();

        foreach ($all_configs as $page_url => $config) {
            if (strpos($current_url, $page_url) !== false) {
                $matching_config = $config;
                break;
            }
        }

        if (!$matching_config || $matching_config['form_id'] == 0) {
            return;
        }

        // Skip the IAY button (uses different navigation logic)
        $skip_buttons = ['iay-button'];
        if (in_array($matching_config['button_id'], $skip_buttons)) {
            return;
        }

        // Check if entry exists
        $entry_exists = self::check_entry_exists(
            $matching_config['form_id'],
            $matching_config['field_id'],
            $anumber
        );

        if ($entry_exists && !empty($matching_config['view_id'])) {
            // Entry exists - show GravityView in the div
            $view_content = self::get_gravityview_content($matching_config['view_id'], $anumber);
            if (!empty($view_content)) {
                ?>
                <script type="text/javascript">
                jQuery(document).ready(function($) {
                    console.log('NME Navigation: Entry exists - showing GravityView in div');
                    
                    // Hide the original form
                    $('#gform_wrapper_<?php echo esc_js($matching_config['form_id']); ?>').hide();
                    
                    // Put GravityView content in the target div
                    var targetDiv = $('#cwm-conditional-content');
                    if (targetDiv.length > 0) {
                        targetDiv.html(<?php echo json_encode($view_content); ?>);
                        console.log('NME Navigation: GravityView loaded successfully');
                    } else {
                        console.log('NME Navigation: Target div #cwm-conditional-content not found');
                    }
                });
                </script>
                <?php
            }
        } else {
            // No entry exists - move form into the div and add query parameters
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                console.log('NME Navigation: No entry exists - moving form to div and adding query params');
                
                var form = $('#gform_wrapper_<?php echo esc_js($matching_config['form_id']); ?>');
                var targetDiv = $('#cwm-conditional-content');
                
                if (form.length > 0 && targetDiv.length > 0) {
                    form.detach().appendTo(targetDiv);
                    console.log('NME Navigation: Form moved to div successfully');
                } else {
                    console.log('NME Navigation: Form or target div not found');
                }
                
                // Add query parameters to current URL if they don't exist
                var currentUrl = new URL(window.location);
                var anumber = '<?php echo esc_js($anumber); ?>';
                var parentEntryId = '<?php echo esc_js($parent_entry_id); ?>';
                var hasChanges = false;
                
                if (anumber && !currentUrl.searchParams.has('anumber')) {
                    currentUrl.searchParams.set('anumber', anumber);
                    hasChanges = true;
                }
                
                if (parentEntryId && !currentUrl.searchParams.has('parent_entry_id')) {
                    currentUrl.searchParams.set('parent_entry_id', parentEntryId);
                    hasChanges = true;
                }
                
                if (hasChanges) {
                    window.history.replaceState({}, '', currentUrl);
                    console.log('NME Navigation: Added query parameters to URL');
                }
            });
            </script>
            <?php
        }
    }

    /**
     * Get GravityView content filtered by A-Number
     */
    private static function get_gravityview_content(int $view_id, string $anumber): string {
        if (empty($view_id)) {
            return '';
        }

        // Add filter to restrict entries to current user's A-Number
        add_filter('gravityview_search_criteria', [__CLASS__, 'filter_gravityview_by_anumber'], 10, 3);

        // Store A-Number for use in filter
        self::$current_anumber = $anumber;

        // Use the shortcode directly
        $view_content = do_shortcode('[gravityview id="' . $view_id . '"]');

        // Remove the filter after use
        remove_filter('gravityview_search_criteria', [__CLASS__, 'filter_gravityview_by_anumber'], 10);

        return $view_content;
    }

    /**
     * Filter GravityView search criteria to show only current user's A-Number entries
     */
    public static function filter_gravityview_by_anumber(array $criteria, int $form_id, int $view_id): array {
        if (empty(self::$current_anumber)) {
            return $criteria;
        }

        // Find the field ID for the current view's configuration
        $field_id = null;
        $all_configs = self::get_all_configs();

        foreach ($all_configs as $page_url => $config) {
            if (isset($config['view_id']) && $config['view_id'] == $view_id) {
                $field_id = $config['field_id'];
                break;
            }
        }

        if (!$field_id) {
            return $criteria;
        }

        // Add search criteria to filter by A-Number
        if (!isset($criteria['search_criteria'])) {
            $criteria['search_criteria'] = [];
        }

        $criteria['search_criteria'][] = [
            'key'      => $field_id,
            'value'    => self::$current_anumber,
            'operator' => 'is',
        ];

        return $criteria;
    }

    /**
     * Prepopulate A-Number field in Gravity Forms
     */
    public static function prepopulate_anumber_field(array $form): array {
        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        $anumber = UserContext::get_anumber();

        if (empty($anumber)) {
            return $form;
        }

        // Find matching configuration for current page
        $matching_config = null;
        $all_configs = self::get_all_configs();

        foreach ($all_configs as $page_url => $config) {
            if (strpos($current_url, $page_url) !== false && $form['id'] == $config['form_id']) {
                $matching_config = $config;
                break;
            }
        }

        if (!$matching_config) {
            return $form;
        }

        // Check if entry already exists
        $entry_exists = self::check_entry_exists(
            $matching_config['form_id'],
            $matching_config['field_id'],
            $anumber
        );

        if (!$entry_exists) {
            // Prepopulate the field with A-Number
            foreach ($form['fields'] as &$field) {
                if ($field->id == $matching_config['field_id']) {
                    $field->defaultValue = $anumber;
                    break;
                }
            }
        }

        return $form;
    }

    /**
     * Check if entry exists in Gravity Form
     */
    public static function check_entry_exists(int $form_id, int $field_id, string $anumber): bool {
        global $wpdb;

        $entry_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) 
             FROM {$wpdb->prefix}gf_entry e
             JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND em.meta_key = %s 
             AND em.meta_value = %s
             AND e.status = 'active'",
            $form_id,
            (string) $field_id,
            $anumber
        ));

        return $entry_count > 0;
    }

    /**
     * AJAX handler to check if an entry exists in a Gravity Form
     */
    public static function ajax_check_form_entry(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'nme_navigation_nonce')) {
            wp_send_json_error('Security check failed');
            return;
        }

        // Get parameters
        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
        $field_id = isset($_POST['field_id']) ? (int) $_POST['field_id'] : 0;
        $anumber = isset($_POST['anumber']) ? sanitize_text_field($_POST['anumber']) : '';

        // Validate
        if (!$form_id || !$field_id || empty($anumber)) {
            wp_send_json_error('Missing required parameters');
            return;
        }

        $entry_exists = self::check_entry_exists($form_id, $field_id, $anumber);

        // Log if debug enabled
        if (Plugin::is_debug_enabled('navigation')) {
            error_log('NME Platform - Navigation: Entry check for Form ' . $form_id . ', Field ' . $field_id . ', A-Number ' . $anumber . ': ' . ($entry_exists ? 'EXISTS' : 'NOT FOUND'));
        }

        wp_send_json_success([
            'entry_exists' => $entry_exists,
            'form_id'      => $form_id,
            'field_id'     => $field_id,
            'anumber'      => $anumber,
        ]);
    }
}