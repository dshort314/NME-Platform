<?php
/**
 * Delete Entries Module
 * 
 * Admin utility to delete user entries with optional email backup.
 */

namespace NME\Admin\DeleteEntries;

defined('ABSPATH') || exit;

return [
    'id'          => 'delete-entries',
    'name'        => 'Delete Entries',
    'description' => 'Admin utility to clean up user data with email backup',
    'requires'    => ['field-registry', 'user-context'],
    'boot'        => function() {
        require_once __DIR__ . '/class-delete-entries.php';
        DeleteEntries::init();
    },
];