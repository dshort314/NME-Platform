<?php
/**
 * Access Control Module
 * 
 * Manages eligibility-based access restrictions for users who are
 * more than 1 year away from their filing date.
 */

namespace NME\Core\AccessControl;

defined('ABSPATH') || exit;

return [
    'id'          => 'access-control',
    'name'        => 'Access Control',
    'description' => 'Manages eligibility-based access restrictions for users in "Eligibility Assessment" status',
    'version'     => '1.0.0',
    'type'        => 'core',
    'requires'    => [],
    'boot'        => function(): void {
        require_once __DIR__ . '/class-access-control.php';
        AccessControl::init();
    },
];
