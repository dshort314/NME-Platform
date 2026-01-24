<?php
/**
 * Date Calculator
 * 
 * All date-related calculations for the naturalization application.
 * Provides both PHP methods and JavaScript data for client-side use.
 */

namespace NME\Features\DateCalculations;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class DateCalculator {

    // =========================================================================
    // LOOKBACK PERIOD CALCULATIONS
    // =========================================================================

    /**
     * Get the lookback start date for an application
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @return string|null Date in Y-m-d format, or null if data missing
     */
    public static function get_lookback_start_date(int $parent_entry_id): ?string {
        $application_date = MasterForm::get_application_date($parent_entry_id);
        $controlling_factor = MasterForm::get_controlling_factor($parent_entry_id);

        if (!$application_date || !$controlling_factor) {
            return null;
        }

        $years = FieldRegistry::get_lookback_years($controlling_factor);
        
        return self::subtract_years($application_date, $years);
    }

    /**
     * Get the lookback end date (application date)
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @return string|null Date in Y-m-d format
     */
    public static function get_lookback_end_date(int $parent_entry_id): ?string {
        return MasterForm::get_application_date($parent_entry_id);
    }

    /**
     * Get the total days in the lookback period
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @return int|null Number of days, or null if data missing
     */
    public static function get_lookback_days(int $parent_entry_id): ?int {
        $start = self::get_lookback_start_date($parent_entry_id);
        $end = self::get_lookback_end_date($parent_entry_id);

        if (!$start || !$end) {
            return null;
        }

        return self::days_between($start, $end);
    }

    // =========================================================================
    // PHYSICAL PRESENCE CALCULATIONS
    // =========================================================================

    /**
     * Get the required physical presence days
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @return int 548 or 913
     */
    public static function get_required_presence_days(int $parent_entry_id): int {
        return MasterForm::get_days_required($parent_entry_id);
    }

    /**
     * Calculate physical presence from days abroad
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @param int $days_abroad Total days spent outside US in lookback period
     * @return array ['days_present' => int, 'days_required' => int, 'meets_requirement' => bool]
     */
    public static function calculate_physical_presence(int $parent_entry_id, int $days_abroad): array {
        $lookback_days = self::get_lookback_days($parent_entry_id);
        $required = self::get_required_presence_days($parent_entry_id);

        if ($lookback_days === null) {
            return [
                'days_present'      => 0,
                'days_required'     => $required,
                'meets_requirement' => false,
            ];
        }

        $days_present = max(0, $lookback_days - $days_abroad);

        return [
            'days_present'      => $days_present,
            'days_required'     => $required,
            'meets_requirement' => $days_present >= $required,
        ];
    }

    // =========================================================================
    // TRIP DURATION CALCULATIONS
    // =========================================================================

    /**
     * Calculate duration of a trip in days (inclusive)
     * 
     * @param string $departure_date Y-m-d format
     * @param string $return_date Y-m-d format
     * @return int Number of days
     */
    public static function calculate_trip_duration(string $departure_date, string $return_date): int {
        return self::days_between($departure_date, $return_date);
    }

    /**
     * Check if a trip is a "long trip" (6+ months / 183+ days)
     * 
     * @param string $departure_date Y-m-d format
     * @param string $return_date Y-m-d format
     * @return bool
     */
    public static function is_long_trip(string $departure_date, string $return_date): bool {
        $days = self::calculate_trip_duration($departure_date, $return_date);
        return $days >= 183;
    }

    /**
     * Calculate the portion of a trip that falls within the lookback period
     * 
     * @param string $departure_date Trip departure Y-m-d
     * @param string $return_date Trip return Y-m-d
     * @param string $lookback_start Lookback period start Y-m-d
     * @param string $lookback_end Lookback period end Y-m-d
     * @return int Days of trip within lookback period
     */
    public static function calculate_trip_days_in_period(
        string $departure_date,
        string $return_date,
        string $lookback_start,
        string $lookback_end
    ): int {
        $trip_start = max(strtotime($departure_date), strtotime($lookback_start));
        $trip_end = min(strtotime($return_date), strtotime($lookback_end));

        if ($trip_end < $trip_start) {
            return 0;
        }

        return (int) floor(($trip_end - $trip_start) / 86400) + 1;
    }

    // =========================================================================
    // FILING DATE CALCULATIONS
    // =========================================================================

    /**
     * Calculate future filing date after a long trip disruption
     * 
     * A trip of 6+ months breaks continuous residence. The applicant must
     * wait until they've re-established the required period.
     * 
     * @param string $return_date Date returned from long trip Y-m-d
     * @param string $controlling_factor DM, SC, LPR, LPRM, or LPRS
     * @return string Earliest filing date Y-m-d
     */
    public static function calculate_filing_date_after_long_trip(
        string $return_date,
        string $controlling_factor
    ): string {
        $years = FieldRegistry::get_lookback_years($controlling_factor);
        
        // Day after return + required years - 3 months (90 days early filing)
        $day_after_return = date('Y-m-d', strtotime($return_date . ' +1 day'));
        $future_date = self::add_years($day_after_return, $years);
        $filing_date = self::subtract_months($future_date, 3);

        return $filing_date;
    }

    /**
     * Calculate the 90-day state residency requirement date
     * 
     * Applicant must live in filing state for 90 days before filing.
     * 
     * @param string $moved_to_state_date Date moved to current state Y-m-d
     * @return string Earliest filing date based on state residency Y-m-d
     */
    public static function calculate_state_residency_date(string $moved_to_state_date): string {
        return date('Y-m-d', strtotime($moved_to_state_date . ' +90 days'));
    }

    /**
     * Check if applicant meets 90-day state residency requirement
     * 
     * @param string $moved_to_state_date Date moved to current state Y-m-d
     * @param string|null $as_of_date Check as of this date (default: today)
     * @return bool
     */
    public static function meets_state_residency_requirement(
        string $moved_to_state_date,
        ?string $as_of_date = null
    ): bool {
        $as_of_date = $as_of_date ?? date('Y-m-d');
        $required_date = self::calculate_state_residency_date($moved_to_state_date);

        return strtotime($as_of_date) >= strtotime($required_date);
    }

    // =========================================================================
    // RESIDENCE DURATION CALCULATIONS
    // =========================================================================

    /**
     * Calculate duration of a residence period in days (inclusive)
     * 
     * @param string $from_date Y-m-d format
     * @param string $to_date Y-m-d format
     * @return int Number of days
     */
    public static function calculate_residence_duration(string $from_date, string $to_date): int {
        return self::days_between($from_date, $to_date);
    }

    /**
     * Calculate gap between two residence periods
     * 
     * @param string $previous_to_date End of previous residence Y-m-d
     * @param string $current_from_date Start of current residence Y-m-d
     * @return int Gap in days (0 if contiguous or overlapping)
     */
    public static function calculate_residence_gap(string $previous_to_date, string $current_from_date): int {
        $gap = self::days_between($previous_to_date, $current_from_date) - 1;
        return max(0, $gap);
    }

    // =========================================================================
    // JAVASCRIPT DATA PROVIDER
    // =========================================================================

    /**
     * Get calculation data for JavaScript
     * 
     * Returns all values needed for client-side calculations.
     * 
     * @param int $parent_entry_id Form 75 entry ID
     * @return array Data for wp_localize_script
     */
    public static function get_js_data(int $parent_entry_id): array {
        $controlling_factor = MasterForm::get_controlling_factor($parent_entry_id);
        $application_date = MasterForm::get_application_date($parent_entry_id);

        return [
            'controllingFactor'  => $controlling_factor ?? '',
            'applicationDate'    => $application_date ?? '',
            'lookbackYears'      => $controlling_factor ? FieldRegistry::get_lookback_years($controlling_factor) : 5,
            'lookbackStartDate'  => self::get_lookback_start_date($parent_entry_id) ?? '',
            'daysRequired'       => $controlling_factor ? FieldRegistry::get_days_required($controlling_factor) : 913,
            'isThreeYear'        => $controlling_factor ? FieldRegistry::is_three_year($controlling_factor) : false,
            'longTripThreshold'  => 183,
        ];
    }

    // =========================================================================
    // INTERNAL DATE HELPERS
    // =========================================================================

    /**
     * Calculate days between two dates (inclusive)
     */
    private static function days_between(string $start, string $end): int {
        $start_ts = strtotime($start);
        $end_ts = strtotime($end);

        if ($start_ts === false || $end_ts === false) {
            return 0;
        }

        return (int) floor(abs($end_ts - $start_ts) / 86400) + 1;
    }

    /**
     * Subtract years from a date
     */
    private static function subtract_years(string $date, int $years): string {
        return date('Y-m-d', strtotime($date . " -{$years} years"));
    }

    /**
     * Add years to a date
     */
    private static function add_years(string $date, int $years): string {
        return date('Y-m-d', strtotime($date . " +{$years} years"));
    }

    /**
     * Subtract months from a date
     */
    private static function subtract_months(string $date, int $months): string {
        return date('Y-m-d', strtotime($date . " -{$months} months"));
    }
}