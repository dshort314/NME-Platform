<?php
/**
 * Information About You Module
 * 
 * Handles Form 70 - the entry point that creates the Master (Form 75) entry
 * and sets user meta (anumber, parent_entry_id, dob).
 */

namespace NME\Topics\InformationAboutYou;

defined('ABSPATH') || exit;

return [
    'id'          => 'information-about-you',
    'name'        => 'Information About You',
    'description' => 'Form 70 - creates Master entry and user meta',
    'requires'    => ['field-registry', 'user-context', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-handler.php';
        Handler::init();
    },
];