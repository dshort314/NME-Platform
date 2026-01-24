<?php
/**
 * Time Outside Module
 *
 * Handles Time Outside the US (TOC) functionality including:
 * - Trip entry and validation (Form 42)
 * - Physical presence calculations
 * - 6-month trip detection and continuous residence disruption
 * - Trip overlap detection
 * - Dashboard evaluation (Page 706)
 *
 * @package NME\Topics\TimeOutside
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'id'          => 'time-outside',
    'name'        => 'Time Outside',
    'description' => 'Time Outside the US entry, validation, and physical presence calculations',
    'version'     => '1.0.0',
    'requires'    => ['user-context'],
    
    'boot'        => function(): void {
        // Load the Assets class first (handles script/style enqueuing)
        require_once __DIR__ . '/class-assets.php';
        \NME\Topics\TimeOutside\Assets::init();
        
        // Load the Handler class (PHP hooks, AJAX, data injection)
        require_once __DIR__ . '/class-handler.php';
        \NME\Topics\TimeOutside\Handler::init();
    },
];
