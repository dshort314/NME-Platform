<?php
/**
 * Preliminary Eligibility Module
 * 
 * Handles Form 78 - preliminary eligibility screening.
 */

namespace NME\Topics\PreliminaryEligibility;

defined('ABSPATH') || exit;

return [
    'id'          => 'preliminary-eligibility',
    'name'        => 'Preliminary Eligibility',
    'description' => 'Form 78 - preliminary eligibility screening',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
