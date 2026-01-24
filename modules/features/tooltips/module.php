<?php
/**
 * Tooltips Module
 * 
 * Adds guidance tooltips to form fields across multiple Gravity Forms.
 */

namespace NME\Features\Tooltips;

defined('ABSPATH') || exit;

return [
    'id'          => 'tooltips',
    'name'        => 'Tooltips',
    'description' => 'Field-level help text tooltips for forms',
    'requires'    => ['field-registry'],
    'boot'        => function() {
        require_once __DIR__ . '/class-tooltips.php';
        Tooltips::init();
    },
];