<?php
/**
 * Purgatory Module
 * 
 * Handles the display of eligibility messages for users who are
 * in "Eligibility Assessment" status.
 */

namespace NME\Core\Purgatory;

defined('ABSPATH') || exit;

return [
    'id'          => 'purgatory',
    'name'        => 'Purgatory',
    'description' => 'Displays eligibility messages for users in "Eligibility Assessment" status',
    'version'     => '1.0.0',
    'type'        => 'core',
    'requires'    => ['access-control'],
    'boot'        => function(): void {
        require_once __DIR__ . '/class-purgatory.php';
        Purgatory::init();
    },
];
