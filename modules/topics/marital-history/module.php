<?php
/**
 * Marital History Module
 * 
 * Handles Form 71 - syncs marital data to Master (Form 75).
 */

namespace NME\Topics\MaritalHistory;

defined('ABSPATH') || exit;

return [
    'id'          => 'marital-history',
    'name'        => 'Marital History',
    'type'        => 'topics',
    'description' => 'Form 71 - marital history synced to Master',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
