<?php
/**
 * Employment Module
 * 
 * Handles Form 73 - employment and school history.
 */

namespace NME\Topics\Employment;

defined('ABSPATH') || exit;

return [
    'id'          => 'employment',
    'name'        => 'Employment & Schools',
    'description' => 'Form 73 - employment and school history',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'gpnf'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
