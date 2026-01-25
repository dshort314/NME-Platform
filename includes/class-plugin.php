<?php
/**
 * Main Plugin Class
 * 
 * Initializes the module loader and registers admin menu.
 * Provides per-module debug toggle functionality.
 */

namespace NME\Core;

defined('ABSPATH') || exit;

class Plugin {

    /** @var string Plugin version */
    const VERSION = NME_PLATFORM_VERSION;

    /**
     * Initialize the plugin
     */
    public static function init(): void {
        // Register admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_menu']);
        
        // Register AJAX handler for debug toggle
        add_action('wp_ajax_nme_toggle_debug', [__CLASS__, 'handle_debug_toggle']);
        
        // Enqueue global debug script on frontend
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_debug_script']);
        
        // Enqueue global debug script on admin (if needed later)
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_debug_script']);
    }

    /**
     * Enqueue the global debug utility script and pass all debug flags
     */
    public static function enqueue_debug_script(): void {
        wp_enqueue_script(
            'nme-debug',
            NME_PLATFORM_URL . 'modules/core/debug/nme-debug.js',
            [],
            self::VERSION,
            false // Load in header so it's available to all other scripts
        );

        // Get all module debug flags
        $debug_flags = self::get_all_debug_flags();
        
        // Pass debug flags to JavaScript
        wp_localize_script('nme-debug', 'nme_debug_flags', $debug_flags);
    }

    /**
     * Get debug flags for all registered modules
     * 
     * @return array Associative array of module_id => bool
     */
    public static function get_all_debug_flags(): array {
        $modules = ModuleLoader::get_modules();
        $flags = [];
        
        foreach ($modules as $id => $module) {
            $flags[$id] = self::is_debug_enabled($id);
        }
        
        return $flags;
    }

    /**
     * Check if debug is enabled for a specific module
     * 
     * @param string $module_id Module identifier
     * @return bool
     */
    public static function is_debug_enabled(string $module_id): bool {
        $settings = self::get_debug_settings();
        return isset($settings['modules'][$module_id]) 
            ? (bool) $settings['modules'][$module_id] 
            : false;
    }

    /**
     * Set debug enabled/disabled for a module
     * 
     * @param string $module_id Module identifier
     * @param bool $enabled Whether debug is enabled
     * @return bool Success
     */
    public static function set_debug_enabled(string $module_id, bool $enabled): bool {
        $settings = get_option('nme_platform_settings', []);
        
        if (!isset($settings['debug'])) {
            $settings['debug'] = ['modules' => [], 'global' => false];
        }
        
        $settings['debug']['modules'][$module_id] = $enabled;
        
        return update_option('nme_platform_settings', $settings);
    }

    /**
     * Get all debug settings
     * 
     * @return array
     */
    public static function get_debug_settings(): array {
        $settings = get_option('nme_platform_settings', []);
        return $settings['debug'] ?? ['modules' => [], 'global' => false];
    }

    /**
     * Get URL for a module's assets directory
     * 
     * @param string $module_id Module identifier
     * @return string|null URL or null if module not found
     */
    public static function get_module_url(string $module_id): ?string {
        $modules = ModuleLoader::get_modules();
        
        if (!isset($modules[$module_id])) {
            return null;
        }
        
        $module = $modules[$module_id];
        $type = $module['type'] ?? 'features';
        
        return NME_PLATFORM_URL . 'modules/' . $type . '/' . $module_id . '/';
    }

    /**
     * Get filesystem path for a module
     * 
     * @param string $module_id Module identifier
     * @return string|null Path or null if module not found
     */
    public static function get_module_path(string $module_id): ?string {
        $modules = ModuleLoader::get_modules();
        
        if (!isset($modules[$module_id])) {
            return null;
        }
        
        $module = $modules[$module_id];
        $type = $module['type'] ?? 'features';
        
        return NME_PLATFORM_PATH . 'modules/' . $type . '/' . $module_id . '/';
    }

    /**
     * Handle AJAX debug toggle
     */
    public static function handle_debug_toggle(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'nme_debug_toggle')) {
            wp_send_json_error('Invalid nonce');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $enabled = ($_POST['enabled'] ?? '') === 'true';

        if (empty($module_id)) {
            wp_send_json_error('Missing module ID');
        }

        $result = self::set_debug_enabled($module_id, $enabled);

        if ($result) {
            wp_send_json_success(['module_id' => $module_id, 'enabled' => $enabled]);
        } else {
            wp_send_json_error('Failed to save setting');
        }
    }

    /**
     * Register admin menu
     */
    public static function register_admin_menu(): void {
        // Main menu page
        add_menu_page(
            'NME Platform',
            'NME Platform',
            'manage_options',
            'nme-platform',
            [__CLASS__, 'render_admin_page'],
            'dashicons-forms',
            30
        );

        // Add "Dashboard" as first submenu (points to same page as parent)
        add_submenu_page(
            'nme-platform',
            'NME Platform Dashboard',
            'Dashboard',
            'manage_options',
            'nme-platform',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Render the main admin page
     */
    public static function render_admin_page(): void {
        $modules = ModuleLoader::get_modules();
        $loaded = ModuleLoader::get_loaded();
        $debug_settings = self::get_debug_settings();
        $nonce = wp_create_nonce('nme_debug_toggle');
        
        ?>
        <div class="wrap">
            <h1>NME Platform</h1>
            <p>Version <?php echo esc_html(NME_PLATFORM_VERSION); ?></p>
            
            <h2>Loaded Modules</h2>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>Module ID</th>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Dependencies</th>
                        <th>Status</th>
                        <th style="width: 100px; text-align: center;">Console Debug</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modules)): ?>
                        <tr>
                            <td colspan="6">No modules found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($modules as $id => $module): ?>
                            <?php 
                            $is_debug = isset($debug_settings['modules'][$id]) 
                                ? $debug_settings['modules'][$id] 
                                : false;
                            $is_loaded = in_array($id, $loaded);
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($id); ?></code></td>
                                <td><?php echo esc_html($module['name'] ?? $id); ?></td>
                                <td><?php echo esc_html($module['type'] ?? 'unknown'); ?></td>
                                <td><?php echo esc_html(implode(', ', $module['requires'] ?? [])); ?></td>
                                <td>
                                    <?php if ($is_loaded): ?>
                                        <span style="color: green;">✓ Loaded</span>
                                    <?php else: ?>
                                        <span style="color: red;">✗ Not Loaded</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           class="nme-debug-toggle" 
                                           data-module="<?php echo esc_attr($id); ?>"
                                           <?php checked($is_debug); ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p class="description" style="margin-top: 10px;">
                <strong>Console Debug:</strong> When enabled, the module will write detailed logs to the browser's JavaScript console. 
                Open DevTools (F12) → Console tab to view. Useful for troubleshooting frontend issues.
                <br><br>
                <em>Note: You must refresh the frontend page after toggling for changes to take effect.</em>
            </p>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.nme-debug-toggle').on('change', function() {
                var $checkbox = $(this);
                var moduleId = $checkbox.data('module');
                var enabled = $checkbox.is(':checked');
                
                $checkbox.prop('disabled', true);
                
                $.ajax({
                    url: ajaxurl,
                    method: 'POST',
                    data: {
                        action: 'nme_toggle_debug',
                        nonce: '<?php echo esc_js($nonce); ?>',
                        module_id: moduleId,
                        enabled: enabled
                    },
                    success: function(response) {
                        $checkbox.prop('disabled', false);
                        if (!response.success) {
                            alert('Failed to save debug setting: ' + (response.data || 'Unknown error'));
                            $checkbox.prop('checked', !enabled);
                        }
                    },
                    error: function() {
                        $checkbox.prop('disabled', false);
                        alert('Failed to save debug setting');
                        $checkbox.prop('checked', !enabled);
                    }
                });
            });
        });
        </script>
        <?php
    }
}
