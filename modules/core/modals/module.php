<?php
/**
 * Modals Module
 * 
 * Provides a standardized modal system for the entire NME Platform.
 * All modules should use this for consistent user interactions.
 */

defined('ABSPATH') || exit;

return [
    'id'          => 'modals',
    'name'        => 'Modal System',
    'description' => 'Standardized modal dialogs for user interactions across all modules',
    'version'     => '1.0.0',
    'type'        => 'core',
    'requires'    => [],
    'boot'        => function(): void {
        require_once __DIR__ . '/class-modals.php';
        \NME\Core\Modals\Modals::init();
    },
];
