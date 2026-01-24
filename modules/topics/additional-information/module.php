<?php
/**
 * Additional Information Module
 * 
 * Handles Form 39 - Section 9 questions that sync to Master (Form 75).
 * Unlike other forms, Form 39 shares field IDs with Form 75 for direct mapping.
 */

namespace NME\Topics\AdditionalInformation;

defined('ABSPATH') || exit;

return [
    'id'          => 'additional-information',
    'name'        => 'Additional Information',
    'type'        => 'topics',
    'description' => 'Form 39 - Section 9 questions synced to Master',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];
