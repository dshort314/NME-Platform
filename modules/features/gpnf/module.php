<?php
/**
 * GPNF Module
 * 
 * Handles Gravity Perks Nested Forms integration.
 * Manages attachment of child entries to parent Form 75.
 */

namespace NME\Features\GPNF;

defined('ABSPATH') || exit;

return [
    'id'          => 'gpnf',
    'name'        => 'Nested Forms (GPNF)',
    'description' => 'Gravity Perks Nested Forms attachment and calculations',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-gpnf-handler.php';
        GPNFHandler::init();
    },
];