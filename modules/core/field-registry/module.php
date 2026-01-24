<?php
/**
 * Field Registry Module
 * 
 * Central repository for all form IDs, field IDs, page IDs, and view IDs.
 * No dependencies - this loads first.
 */

namespace NME\Core\FieldRegistry;

defined('ABSPATH') || exit;

return [
    'id'          => 'field-registry',
    'name'        => 'Field Registry',
    'description' => 'Central constants for forms, fields, pages, and views',
    'requires'    => [],
    'boot'        => function() {
        require_once __DIR__ . '/class-field-registry.php';
    },
];
