<?php
/**
 * Main Plugin Class
 * 
 * Initializes the module loader and registers admin menu.
 */

namespace NME\Core;

defined('ABSPATH') || exit;

class Plugin {

    /** @var bool Whether plugin has been initialized */
    private static bool $initialized = false;

    /**
     * Initialize the plugin
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        // Load the module loader
        require_once NME_PLATFORM_PATH . 'includes/class-module-loader.php';
        ModuleLoader::init();

        // Admin menu
        add_action('admin_menu', [__CLASS__, 'register_admin_menu'], 10);
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
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($modules)): ?>
                        <tr>
                            <td colspan="5">No modules found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($modules as $id => $module): ?>
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
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}