<?php
/**
 * User Context Module
 * 
 * Centralized access to user meta: anumber, parent_entry_id, dob.
 * Replaces scattered get_user_meta() calls throughout the codebase.
 */

namespace NME\Core\UserContext;

defined('ABSPATH') || exit;

return [
    'id'          => 'user-context',
    'name'        => 'User Context',
    'description' => 'Centralized user meta access (anumber, parent_entry_id, dob)',
    'requires'    => ['field-registry'],
    'boot'        => function() {
        require_once __DIR__ . '/class-user-context.php';
    },
];