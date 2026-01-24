<?php
/**
 * Module Loader
 * 
 * Scans modules directory, resolves dependencies, and initializes modules in correct order.
 */

namespace NME\Core;

defined('ABSPATH') || exit;

class ModuleLoader {

    /** @var array Registered modules */
    private static array $modules = [];

    /** @var array Modules that have been loaded */
    private static array $loaded = [];

    /** @var array Load order after dependency resolution */
    private static array $load_order = [];

    /**
     * Discover and load all modules
     */
    public static function init(): void {
        self::discover_modules();
        self::resolve_dependencies();
        self::load_modules();
    }

    /**
     * Scan module directories for module.php files
     */
    private static function discover_modules(): void {
        $module_types = ['core', 'features', 'topics', 'admin'];
        
        foreach ($module_types as $type) {
            $type_path = NME_PLATFORM_PATH . "modules/{$type}";
            
            if (!is_dir($type_path)) {
                continue;
            }

            $dirs = glob($type_path . '/*', GLOB_ONLYDIR);
            
            foreach ($dirs as $dir) {
                $module_file = $dir . '/module.php';
                
                if (file_exists($module_file)) {
                    $config = include $module_file;
                    
                    if (is_array($config) && isset($config['id'])) {
                        $config['type'] = $type;
                        $config['path'] = $dir;
                        self::$modules[$config['id']] = $config;
                    }
                }
            }
        }
    }

    /**
     * Resolve dependencies and determine load order
     */
    private static function resolve_dependencies(): void {
        $resolved = [];
        $unresolved = array_keys(self::$modules);

        // Simple topological sort
        $max_iterations = count($unresolved) * count($unresolved);
        $iterations = 0;

        while (!empty($unresolved) && $iterations < $max_iterations) {
            $iterations++;
            
            foreach ($unresolved as $key => $module_id) {
                $module = self::$modules[$module_id];
                $requires = $module['requires'] ?? [];
                
                // Check if all dependencies are resolved
                $deps_met = true;
                foreach ($requires as $dep) {
                    if (!in_array($dep, $resolved)) {
                        $deps_met = false;
                        break;
                    }
                }

                if ($deps_met) {
                    $resolved[] = $module_id;
                    unset($unresolved[$key]);
                }
            }
            
            $unresolved = array_values($unresolved); // Re-index
        }

        // Any remaining modules have unmet dependencies
        if (!empty($unresolved)) {
            foreach ($unresolved as $module_id) {
                $module = self::$modules[$module_id];
                $missing = array_diff($module['requires'] ?? [], $resolved);
                error_log("NME Platform: Module '{$module_id}' has unmet dependencies: " . implode(', ', $missing));
            }
        }

        self::$load_order = $resolved;
    }

    /**
     * Load modules in resolved order
     */
    private static function load_modules(): void {
        foreach (self::$load_order as $module_id) {
            $module = self::$modules[$module_id];
            
            // Call boot function if defined
            if (isset($module['boot']) && is_callable($module['boot'])) {
                try {
                    call_user_func($module['boot']);
                    self::$loaded[] = $module_id;
                } catch (\Exception $e) {
                    error_log("NME Platform: Failed to load module '{$module_id}': " . $e->getMessage());
                }
            } else {
                // Module with no boot function is still considered loaded
                self::$loaded[] = $module_id;
            }
        }
    }

    /**
     * Get all registered modules
     */
    public static function get_modules(): array {
        return self::$modules;
    }

    /**
     * Get loaded modules
     */
    public static function get_loaded(): array {
        return self::$loaded;
    }

    /**
     * Check if a module is loaded
     */
    public static function is_loaded(string $module_id): bool {
        return in_array($module_id, self::$loaded);
    }

    /**
     * Get a module's configuration
     */
    public static function get_module(string $module_id): ?array {
        return self::$modules[$module_id] ?? null;
    }
}
