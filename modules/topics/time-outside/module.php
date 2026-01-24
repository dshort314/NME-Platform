<?php
/**
 * Time Outside Module
 * 
 * Handles Form 42 - trips outside the US.
 */

namespace NME\Topics\TimeOutside;

defined('ABSPATH') || exit;

return [
    'id'          => 'time-outside',
    'name'        => 'Time Outside the US',
    'description' => 'Form 42 - travel history with physical presence calculations',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'date-calculations', 'gpnf'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];