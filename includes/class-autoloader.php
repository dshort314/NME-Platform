<?php
/**
 * Autoloader for NME Platform
 * 
 * Maps namespaces to directories:
 * - NME\Core\*           → includes/
 * - NME\Topics\*         → modules/topics/
 * - NME\Features\*       → modules/features/
 * - NME\Admin\*          → modules/admin/
 */

namespace NME\Core;

defined('ABSPATH') || exit;

class Autoloader {

    /**
     * Register the autoloader
     */
    public static function register(): void {
        spl_autoload_register([__CLASS__, 'autoload']);
    }

    /**
     * Autoload classes based on namespace
     */
    public static function autoload(string $class): void {
        // Only handle NME namespace
        if (strpos($class, 'NME\\') !== 0) {
            return;
        }

        $relative_class = substr($class, 4); // Remove 'NME\'
        $parts = explode('\\', $relative_class);

        if (empty($parts)) {
            return;
        }

        $path = self::get_path($parts);
        
        if ($path && file_exists($path)) {
            require_once $path;
        }
    }

    /**
     * Convert namespace parts to file path
     */
    private static function get_path(array $parts): ?string {
        $type = $parts[0]; // Core, Topics, Features, or Admin
        
        switch ($type) {
            case 'Core':
                // NME\Core\ClassName → includes/class-classname.php
                array_shift($parts); // Remove 'Core'
                $class_name = array_pop($parts);
                $file = 'class-' . self::to_filename($class_name) . '.php';
                return NME_PLATFORM_PATH . 'includes/' . $file;

            case 'Topics':
            case 'Features':
            case 'Admin':
                // NME\Topics\TopicName\ClassName → modules/topics/topic-name/class-classname.php
                array_shift($parts); // Remove type (Topics/Features/Admin)
                
                if (count($parts) < 2) {
                    return null;
                }
                
                $module_name = self::to_filename(array_shift($parts));
                $class_name = array_pop($parts);
                $file = 'class-' . self::to_filename($class_name) . '.php';
                $type_folder = strtolower($type);
                
                return NME_PLATFORM_PATH . "modules/{$type_folder}/{$module_name}/{$file}";

            default:
                return null;
        }
    }

    /**
     * Convert CamelCase to kebab-case for filenames
     */
    private static function to_filename(string $class_name): string {
        // UserContext → user-context
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name);
        return strtolower($result);
    }
}
