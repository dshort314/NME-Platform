<?php
/**
 * Children Module
 * 
 * Handles Form 72 - children information.
 */

namespace NME\Topics\Children;

defined('ABSPATH') || exit;

return [
    'id'          => 'children',
    'name'        => 'Children',
    'description' => 'Form 72 - children information',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'gpnf'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];