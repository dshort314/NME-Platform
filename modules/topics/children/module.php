<?php
/**
 * Children Module
 * 
 * Handles Form 72 - children information (GPNF nested form).
 */

namespace NME\Topics\Children;

defined('ABSPATH') || exit;

return [
    'id'          => 'children',
    'name'        => 'Children',
    'type'        => 'topics',
    'description' => 'Form 72 - children information',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'gpnf-integration'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
