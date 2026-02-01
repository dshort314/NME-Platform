<?php
/**
 * Bug Reports Module
 * 
 * Internal bug tracking system for NME Platform issues.
 */

namespace NME\Admin\BugReports;

defined('ABSPATH') || exit;

return [
    'id'          => 'bug-reports',
    'name'        => 'Bug Reports',
    'description' => 'Internal bug tracking for NME Platform issues',
    'requires'    => [],
    'boot'        => function() {
        require_once __DIR__ . '/class-bug-reports.php';
        BugReports::init();
    },
];
