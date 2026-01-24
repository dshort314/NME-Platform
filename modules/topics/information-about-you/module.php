<?php
/**
 * Information About You Module
 * 
 * Registers the module and initializes the handler for Form 70 processing.
 * Also loads JavaScript assets for client-side functionality.
 */

defined('ABSPATH') || exit;

return [
    'id'          => 'information-about-you',
    'name'        => 'Information About You',
    'description' => 'Handles Form 70 submissions, Master form creation, and eligibility calculations',
    'version'     => '1.0.0',
    'type'        => 'topic',
    'form_id'     => 70,
    'requires'    => ['user-context'],
    'boot'        => function(): void {
        // Load and initialize the form handler (server-side processing)
        require_once __DIR__ . '/class-handler.php';
        \NME\Topics\InformationAboutYou\Handler::init();
        
        // Load and initialize assets (client-side JavaScript/CSS)
        require_once __DIR__ . '/class-assets.php';
        \NME\Topics\InformationAboutYou\Assets::init();
    },
];
