<?php
/**
 * Preliminary Eligibility Assessment Module
 * 
 * Handles Form 78 - screening assessment before full application.
 * Shows modals for "negative" answers and routes to appropriate result pages.
 * 
 * This is a standalone screening form - it does NOT sync to the Master Form.
 */

namespace NME\Features\PreliminaryEligibility;

defined('ABSPATH') || exit;

return [
    'id'          => 'preliminary-eligibility',
    'name'        => 'Preliminary Eligibility',
    'type'        => 'features',
    'description' => 'Form 78 - preliminary eligibility screening',
    'requires'    => ['field-registry'],
    'boot'        => function() {
        require_once __DIR__ . '/class-config.php';
        require_once __DIR__ . '/class-handler.php';
        require_once __DIR__ . '/class-admin.php';
        require_once __DIR__ . '/class-assets.php';
        
        Handler::init();
        Admin::init();
        Assets::init();
    },
];
