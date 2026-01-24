<?php
/**
 * Master Form Module
 * 
 * CRUD operations for Form 75 (Master application form).
 * Replaces scattered GFAPI and $wpdb calls throughout the codebase.
 */

namespace NME\Core\MasterForm;

defined('ABSPATH') || exit;

return [
    'id'          => 'master-form',
    'name'        => 'Master Form',
    'description' => 'CRUD operations for Form 75 (Master)',
    'requires'    => ['field-registry'],
    'boot'        => function() {
        require_once __DIR__ . '/class-master-form.php';
    },
];