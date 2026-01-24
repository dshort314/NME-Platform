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

    /** @var string Plugin version constant for cache-busting */
    const VERSION = NME_PLATFORM_VERSION;

    /** @var bool Whether plugin has been initialized */
    private static bool $initialized = false;

    /** @var array Cached settings */
    private static array $settings = [];

    /**
     * Initialize the plugin
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Load settings
        self::$settings = get_option('nme_platform_settings', []);

        // Load the module loader
        require_once NME_PLATFORM_PATH . 'includes/class-module-loader.php';
        ModuleLoader::init();

        // Admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 10);

        // Handle debug toggle AJAX
        add_action('wp_ajax_nme_toggle_debug', [__CLASS__, 'handle_debug_toggle']);
    }

    /**
     * Check if debug is enabled for a specific module
     * 
     * @param string $module_id Module ID to check
     * @return bool Whether debug is enabled
     */
    public static function is_debug_enabled(string $module_id): bool {
        // Refresh settings cache if empty
        if (empty(self::$settings)) {
            self::$settings = get_option('nme_platform_settings', []);
        }

        // Check module-specific setting first
        if (isset(self::$settings['debug']['modules'][$module_id])) {
            return (bool) self::$settings['debug']['modules'][$module_id];
        }

        // Fall back to global setting
        return (bool) (self::$settings['debug']['global'] ?? false);
    }

    /**
     * Enable or disable debug for a module
     * 
     * @param string $module_id Module ID
     * @param bool $enabled Whether to enable debug
     * @return bool Success
     */
    public static function set_debug_enabled(string $module_id, bool $enabled): bool {
        if (!isset(self::$settings['debug'])) {
            self::$settings['debug'] = ['modules' => [], 'global' => false];
        }

        self::$settings['debug']['modules'][$module_id] = $enabled;

        return update_option('nme_platform_settings', self::$settings);
    }

    /**
     * Get all debug settings
     * 
     * @return array Debug settings
     */
    public static function get_debug_settings(): array {
        return self::$settings['debug'] ?? ['modules' => [], 'global' => false];
    }

    /**
     * Get URL to a module's directory
     * 
     * @param string $module_id Module ID (e.g., 'preliminary-eligibility')
     * @return string URL with trailing slash
     */
    public static function get_module_url(string $module_id): string {
        // Get the module info to determine its type
        $modules = ModuleLoader::get_modules();
        
        if (isset($modules[$module_id])) {
            $type = $modules[$module_id]['type'] ?? 'features';
            return NME_PLATFORM_URL . 'modules/' . $type . '/' . $module_id . '/';
        }
        
        // Fallback: search all type directories
        $types = ['core', 'features', 'topics', 'admin'];
        foreach ($types as $type) {
            $path = NME_PLATFORM_PATH . 'modules/' . $type . '/' . $module_id . '/';
            if (is_dir($path)) {
                return NME_PLATFORM_URL . 'modules/' . $type . '/' . $module_id . '/';
            }
        }
        
        // Default fallback
        return NME_PLATFORM_URL . 'modules/features/' . $module_id . '/';
    }

    /**
     * Get filesystem path to a module's directory
     * 
     * @param string $module_id Module ID
     * @return string Path with trailing slash
     */
    public static function get_module_path(string $module_id): string {
        $modules = ModuleLoader::get_modules();
        
        if (isset($modules[$module_id])) {
            $type = $modules[$module_id]['type'] ?? 'features';
            return NME_PLATFORM_PATH . 'modules/' . $type . '/' . $module_id . '/';
        }
        
        // Fallback: search all type directories
        $types = ['core', 'features', 'topics', 'admin'];
        foreach ($types as $type) {
            $path = NME_PLATFORM_PATH . 'modules/' . $type . '/' . $module_id . '/';
            if (is_dir($path)) {
                return $path;
            }
        }
        
        return NME_PLATFORM_PATH . 'modules/features/' . $module_id . '/';
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
                        <th style="width: 80px; text-align: center;">Debug</th>
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
                                ? (bool) $debug_settings['modules'][$id] 
                                : false;
                            ?>
                            <tr>
                                <td><code><?php echo esc_html($id); ?></code></td>
                                <td><?php echo esc_html($module['name'] ?? $id); ?></td>
                                <td><?php echo esc_html($module['type'] ?? '—'); ?></td>
                                <td>
                                    <?php 
                                    $deps = $module['requires'] ?? [];
                                    echo $deps ? esc_html(implode(', ', $deps)) : '—';
                                    ?>
                                </td>
                                <td>
                                    <?php if (in_array($id, $loaded)): ?>
                                        <span style="color: green;">✓ Loaded</span>
                                    <?php else: ?>
                                        <span style="color: red;">✗ Not loaded</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <input type="checkbox" 
                                           class="nme-debug-toggle" 
                                           data-module="<?php echo esc_attr($id); ?>"
                                           <?php checked($is_debug); ?>
                                           title="Toggle debug logging for <?php echo esc_attr($module['name'] ?? $id); ?>">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <p class="description" style="margin-top: 10px;">
                <strong>Debug:</strong> When enabled, the module will write detailed logs to the PHP error log. 
                Useful for troubleshooting but may impact performance.
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
