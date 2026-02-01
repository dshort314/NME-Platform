<?php
/**
 * Admin Module
 * 
 * Provides admin interface components for the NME Platform,
 * including user profile fields for viewing/editing NME user meta.
 */

namespace NME\Core\Admin;

defined('ABSPATH') || exit;

return [
    'id'          => 'admin',
    'name'        => 'Admin',
    'description' => 'Admin interface components including user profile fields',
    'version'     => '1.0.0',
    'type'        => 'core',
    'requires'    => [],
    'boot'        => function(): void {
        require_once __DIR__ . '/class-user-profile-fields.php';
        UserProfileFields::init();
    },
];
