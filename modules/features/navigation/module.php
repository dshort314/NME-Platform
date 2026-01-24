<?php
/**
 * Navigation Module
 * 
 * Handles navigation buttons, routing between application sections,
 * and conditional display based on completion status.
 */

namespace NME\Features\Navigation;

defined('ABSPATH') || exit;

return [
    'id'          => 'navigation',
    'name'        => 'Navigation',
    'description' => 'Navigation buttons and routing between application sections',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-navigation.php';
        Navigation::init();
    },
];