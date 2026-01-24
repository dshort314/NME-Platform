<?php
/**
 * Residences Module
 * 
 * Handles Form 38 - residence history tracking.
 */

namespace NME\Topics\Residences;

defined('ABSPATH') || exit;

return [
    'id'          => 'residences',
    'name'        => 'Residences',
    'description' => 'Form 38 - residence history with date calculations',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'date-calculations', 'gpnf'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];