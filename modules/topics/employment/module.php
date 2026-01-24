<?php
/**
 * Employment Module
 * 
 * Handles Form 73 - employment and school history (GPNF nested form).
 */

namespace NME\Topics\Employment;

defined('ABSPATH') || exit;

return [
    'id'          => 'employment',
    'name'        => 'Employment & Schools',
    'type'        => 'topics',
    'description' => 'Form 73 - employment and school history',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'gpnf-integration'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
