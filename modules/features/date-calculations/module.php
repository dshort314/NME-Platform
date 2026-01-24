<?php
/**
 * Date Calculations Module
 * 
 * Centralized date calculations for lookback periods, physical presence,
 * and filing eligibility. Replaces scattered JavaScript calculations.
 */

namespace NME\Features\DateCalculations;

defined('ABSPATH') || exit;

return [
    'id'          => 'date-calculations',
    'name'        => 'Date Calculations',
    'description' => 'Lookback periods, physical presence, filing eligibility',
    'requires'    => ['field-registry', 'master-form'],
    'boot'        => function() {
        require_once __DIR__ . '/class-date-calculator.php';
    },
];