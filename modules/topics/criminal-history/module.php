<?php
/**
 * Criminal History Module
 * 
 * Handles Form 74 - criminal history questions.
 */

namespace NME\Topics\CriminalHistory;

defined('ABSPATH') || exit;

return [
    'id'          => 'criminal-history',
    'name'        => 'Criminal History',
    'description' => 'Form 74 - criminal history questions',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'gpnf'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
