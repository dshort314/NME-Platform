<?php
/**
 * Additional Information Module
 * 
 * Handles Form 39 - additional eligibility questions.
 */

namespace NME\Topics\AdditionalInformation;

defined('ABSPATH') || exit;

return [
    'id'          => 'additional-information',
    'name'        => 'Additional Information',
    'description' => 'Form 39 - additional eligibility questions',
    'requires'    => ['field-registry', 'user-context', 'master-form', 'counsel'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
