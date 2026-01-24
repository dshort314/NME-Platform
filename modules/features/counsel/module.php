<?php
/**
 * Counsel Module
 * 
 * Handles bouncer modals for Form 39 (Additional Information).
 * Triggers modals on Yes/No answers for eligibility questions.
 */

namespace NME\Features\Counsel;

defined('ABSPATH') || exit;

return [
    'id'          => 'counsel',
    'name'        => 'Application Counsel',
    'description' => 'Bouncer modals for eligibility questions on Form 39',
    'requires'    => ['field-registry'],
    'boot'        => function() {
        require_once __DIR__ . '/class-counsel.php';
        require_once __DIR__ . '/class-admin.php';
        
        Counsel::init();
        Admin::init();
    },
];
