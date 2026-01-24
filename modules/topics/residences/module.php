<?php
/**
 * Residences Module
 *
 * Handles Residence history functionality including:
 * - Residence entry and validation (Form 38)
 * - Duration calculations and gap detection
 * - 90-day state residency rule enforcement
 * - Boundary validation between entries
 * - Dashboard evaluation (Page 705)
 *
 * @package NME\Topics\Residences
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

return [
    'id'          => 'residences',
    'name'        => 'Residences',
    'description' => 'Residence history entry, validation, and duration calculations',
    'version'     => '1.0.0',
    'requires'    => ['user-context', 'master-form'],
    
    'boot'        => function(): void {
        // Load the Assets class first (handles script/style enqueuing)
        require_once __DIR__ . '/class-assets.php';
        \NME\Topics\Residences\Assets::init();
        
        // Load the Handler class (PHP hooks, AJAX, data injection)
        require_once __DIR__ . '/class-handler.php';
        \NME\Topics\Residences\Handler::init();
    },
];
