<?php
/**
 * Eligibility Calculator
 * 
 * Server-side eligibility determination for Form 70 (Information About You).
 * 
 * CRITICAL: This is a faithful PHP port of the JavaScript eligibility logic
 * in nme-eligibility-logic.js and date calculations from nme-form-handlers.js.
 * 
 * DO NOT ALTER ANY CALCULATION.
 * DO NOT TRY TO IMPROVE ANY CALCULATION.
 * DO NOT MAKE ASSUMPTIONS.
 * 
 * Every branch, comparison, and date computation is reproduced exactly
 * as written in the JavaScript source files.
 * 
 * @package NME\Topics\InformationAboutYou
 */

namespace NME\Topics\InformationAboutYou;

defined('ABSPATH') || exit;

class EligibilityCalculator {

    /**
     * Parse a date string into a DateTime object.
     * Handles m/d/Y, Y-m-d, m-d-Y, and Y-m-d H:i:s formats.
     * 
     * @param string|null $date_string The date string to parse
     * @return \DateTime|null Parsed DateTime or null if invalid
     */
    private static function parse_date(?string $date_string): ?\DateTime {
        if (empty($date_string)) {
            return null;
        }

        $date_string = trim($date_string);

        // Try formats in order
        $formats = ['m/d/Y', 'Y-m-d', 'm-d-Y', 'Y-m-d H:i:s'];
        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat($format, $date_string);
            if ($date !== false) {
                // Reset time to midnight to match JS Date behavior
                $date->setTime(0, 0, 0);
                return $date;
            }
        }

        return null;
    }

    /**
     * Format a DateTime to m/d/Y (MM/DD/YYYY) format.
     * Matches the JavaScript DateCalculations.formatDate() output.
     * 
     * @param \DateTime|null $date The date to format
     * @return string Formatted date or empty string if null
     */
    private static function format_date(?\DateTime $date): string {
        if ($date === null) {
            return '';
        }
        return $date->format('m/d/Y');
    }

    /**
     * Add years to a date with optional day adjustment.
     * Reproduces JavaScript: DateCalculations.addYears(date, years, dayAdjustment)
     * 
     * JS implementation:
     *   let newDate = new Date(date);
     *   newDate.setFullYear(newDate.getFullYear() + years);
     *   newDate.setDate(newDate.getDate() + dayAdjustment);
     * 
     * @param \DateTime|null $date The base date
     * @param int $years Number of years to add
     * @param int $day_adjustment Number of days to adjust (default 0)
     * @return \DateTime|null New date or null if input null
     */
    private static function add_years(?\DateTime $date, int $years, int $day_adjustment = 0): ?\DateTime {
        if ($date === null) {
            return null;
        }

        $new_date = clone $date;

        // Step 1: Add years (matches JS setFullYear)
        $new_date->modify("+{$years} years");

        // Step 2: Add day adjustment (matches JS setDate(getDate() + dayAdjustment))
        if ($day_adjustment !== 0) {
            if ($day_adjustment > 0) {
                $new_date->modify("+{$day_adjustment} days");
            } else {
                $abs_days = abs($day_adjustment);
                $new_date->modify("-{$abs_days} days");
            }
        }

        return $new_date;
    }

    /**
     * Subtract months from a date with optional day adjustment.
     * Reproduces JavaScript: DateCalculations.subtractMonths(date, months, dayAdjustment)
     * 
     * JS implementation:
     *   let newDate = new Date(date);
     *   newDate.setMonth(newDate.getMonth() - months);
     *   newDate.setDate(newDate.getDate() + dayAdjustment);
     * 
     * @param \DateTime|null $date The base date
     * @param int $months Number of months to subtract
     * @param int $day_adjustment Number of days to adjust (default 0)
     * @return \DateTime|null New date or null if input null
     */
    private static function subtract_months(?\DateTime $date, int $months, int $day_adjustment = 0): ?\DateTime {
        if ($date === null) {
            return null;
        }

        $new_date = clone $date;

        // Step 1: Subtract months (matches JS setMonth)
        $new_date->modify("-{$months} months");

        // Step 2: Add day adjustment (matches JS setDate(getDate() + dayAdjustment))
        if ($day_adjustment !== 0) {
            if ($day_adjustment > 0) {
                $new_date->modify("+{$day_adjustment} days");
            } else {
                $abs_days = abs($day_adjustment);
                $new_date->modify("-{$abs_days} days");
            }
        }

        return $new_date;
    }

    /**
     * Check if a DateTime is valid (not null).
     * Reproduces: dates.DM instanceof Date && !isNaN(dates.DM.getTime())
     * 
     * @param \DateTime|null $date
     * @return bool
     */
    private static function is_valid_date(?\DateTime $date): bool {
        return $date !== null;
    }

    /**
     * Compute all derived dates from source dates.
     * Reproduces the calculations in nme-form-handlers.js:
     *   updateLPR(), updateDM(), updateSC()
     * 
     * @param array $source_dates Associative array with keys: Today, LPR, DM, SC
     * @return array All computed dates
     */
    public static function compute_derived_dates(array $source_dates): array {
        $Today = $source_dates['Today'];
        $LPR   = $source_dates['LPR'];
        $DM    = $source_dates['DM'];
        $SC    = $source_dates['SC'];

        $dates = [
            'Today' => $Today,
            'LPR'   => $LPR,
            'DM'    => $DM,
            'SC'    => $SC,
        ];

        // LPR-derived dates (from updateLPR)
        // dates.LPR2  = DateCalc.addYears(dates.LPR, 2, -90);
        // dates.LPR3  = DateCalc.addYears(dates.LPR, 3, -90);
        // dates.LPR4  = DateCalc.addYears(dates.LPR, 4, -90);
        // dates.LPRC  = DateCalc.addYears(dates.LPR, 5, -90);
        // dates.LPR36 = DateCalc.subtractMonths(dates.LPR3, 6);
        // dates.LPRC6 = DateCalc.subtractMonths(dates.LPRC, 6);
        $dates['LPR2']  = self::add_years($LPR, 2, -90);
        $dates['LPR3']  = self::add_years($LPR, 3, -90);
        $dates['LPR4']  = self::add_years($LPR, 4, -90);
        $dates['LPRC']  = self::add_years($LPR, 5, -90);
        $dates['LPR36'] = self::subtract_months($dates['LPR3'], 6);
        $dates['LPRC6'] = self::subtract_months($dates['LPRC'], 6);

        // DM-derived dates (from updateDM)
        // dates.DM2  = DateCalc.addYears(dates.DM, 2);
        // dates.DMC  = DateCalc.addYears(dates.DM, 3);
        // dates.DMC6 = DateCalc.subtractMonths(dates.DMC, 6);
        $dates['DM2']  = self::add_years($DM, 2);
        $dates['DMC']  = self::add_years($DM, 3);
        $dates['DMC6'] = self::subtract_months($dates['DMC'], 6);

        // SC-derived dates (from updateSC)
        // dates.SC2  = DateCalc.addYears(dates.SC, 2);
        // dates.SCC  = DateCalc.addYears(dates.SC, 3);
        // dates.SCC6 = DateCalc.subtractMonths(dates.SCC, 6);
        $dates['SC2']  = self::add_years($SC, 2);
        $dates['SCC']  = self::add_years($SC, 3);
        $dates['SCC6'] = self::subtract_months($dates['SCC'], 6);

        return $dates;
    }

    /**
     * Determine the controlling factor for eligibility.
     * 
     * THIS IS A FAITHFUL PORT OF determineControllingFactor() FROM
     * nme-eligibility-logic.js. Every branch is reproduced exactly.
     * 
     * @param array $dates All dates (source + derived) from compute_derived_dates()
     * @param string $married_value "Yes" or "No" - the effective marriedValue
     * @return array With keys: controllingFactor, controllingDate, controllingDesc, status
     */
    public static function determine_controlling_factor(array $dates, string $married_value): array {
        $Today = $dates['Today'];
        $LPR   = $dates['LPR'];
        $LPR2  = $dates['LPR2'];
        $LPR3  = $dates['LPR3'];
        $LPR4  = $dates['LPR4'];
        $LPRC  = $dates['LPRC'];
        $LPR36 = $dates['LPR36'];
        $LPRC6 = $dates['LPRC6'];
        $DM    = $dates['DM'];
        $DM2   = $dates['DM2'];
        $DMC   = $dates['DMC'];
        $DMC6  = $dates['DMC6'];
        $SC    = $dates['SC'];
        $SC2   = $dates['SC2'];
        $SCC   = $dates['SCC'];
        $SCC6  = $dates['SCC6'];

        $controllingFactor = null;
        $controllingDate   = null;
        $controllingDesc   = null;
        $status            = "";

        // Check if LPR date is empty - if so, clear everything and return
        // JS: if (!dates.LPR || !(dates.LPR instanceof Date) || isNaN(dates.LPR.getTime()))
        if (!self::is_valid_date($LPR)) {
            return [
                'controllingFactor' => '',
                'controllingDate'   => '',
                'controllingDesc'   => '',
                'status'            => '',
            ];
        }

        // Determine initial controlling factor
        // JS: if (dates.marriedValue === 'No')
        if ($married_value === 'No') {
            $controllingFactor = 'LPR';
        }
        // JS: else if (dates.marriedValue === 'Yes')
        elseif ($married_value === 'Yes') {
            // JS: let laterDate = (dates.DMC >= dates.SCC) ? dates.DMC : dates.SCC;
            $laterDate = ($DMC >= $SCC) ? $DMC : $SCC;

            // JS: if (laterDate === dates.DMC)
            if ($laterDate === $DMC) {
                // JS: controllingFactor = (dates.LPRC >= dates.DMC) ? 'DM' : (dates.LPR2 >= dates.DMC) ? 'DM' : 'LPRM';
                if ($LPRC >= $DMC) {
                    $controllingFactor = 'DM';
                } elseif ($LPR2 >= $DMC) {
                    $controllingFactor = 'DM';
                } else {
                    $controllingFactor = 'LPRM';
                }
            } else {
                // JS: controllingFactor = (dates.LPRC >= dates.SCC) ? 'SC' : (dates.LPR2 >= dates.SCC) ? 'SC' : 'LPRS';
                if ($LPRC >= $SCC) {
                    $controllingFactor = 'SC';
                } elseif ($LPR2 >= $SCC) {
                    $controllingFactor = 'SC';
                } else {
                    $controllingFactor = 'LPRS';
                }
            }
        }

        // ============================================================
        // LPR logic for unmarried
        // JS: if (controllingFactor === 'LPR')
        // ============================================================
        if ($controllingFactor === 'LPR') {
            // JS: if (dates.Today >= dates.LPRC)
            if ($Today >= $LPRC) {
                $controllingDate = $LPRC;
                $controllingDesc = "LPRC - 1A";
                $status = "Eligible Now";
            }
            // JS: else if (dates.Today >= dates.LPR4)
            elseif ($Today >= $LPR4) {
                $controllingDate = $LPRC;
                $controllingDesc = "LPRC - 1B";
                $status = "Prepare, but file later";
            }
            // JS: else
            else {
                $controllingDate = $LPRC;
                $controllingDesc = "LPRC - 1C";
                $status = "Eligibility Assessment";
            }
        }

        // ============================================================
        // If exactly one of DM or SC is present, clear everything
        // JS: if ((dates.DM instanceof Date && !isNaN(dates.DM.getTime())) !== 
        //         (dates.SC instanceof Date && !isNaN(dates.SC.getTime())))
        // ============================================================
        if (self::is_valid_date($DM) !== self::is_valid_date($SC)) {
            $controllingDate = "";
            $controllingDesc = "";
            $status = "";
        }

        // ============================================================
        // LPRM / LPRS logic if spouse+citizen both present
        // JS: if ((dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && 
        //         (dates.SC instanceof Date && !isNaN(dates.SC.getTime())))
        // ============================================================
        if (self::is_valid_date($DM) && self::is_valid_date($SC)) {

            // JS: if (controllingFactor === 'LPRM')
            if ($controllingFactor === 'LPRM') {
                // JS: if (dates.Today >= dates.LPR4)
                if ($Today >= $LPR4) {
                    $controllingDate = $LPRC;
                    $controllingDesc = "LPRC - Married No Benefit PF";
                    $status = "Prepare, but file later";
                }
                // JS: else
                else {
                    $controllingDate = $LPRC;
                    $controllingDesc = "LPRC - Married No Benefit EA";
                    $status = "Eligibility Assessment";
                }
            }

            // JS: if (controllingFactor === 'LPRS')
            if ($controllingFactor === 'LPRS') {
                // JS: if (dates.Today >= dates.LPR4)
                if ($Today >= $LPR4) {
                    $controllingDate = $LPRC;
                    $controllingDesc = "LPRC - Spouse No Benefit PF";
                    $status = "Prepare, but file later";
                }
                // JS: else
                else {
                    $controllingDate = $LPRC;
                    $controllingDesc = "LPRC - Spouse No Benefit EA";
                    $status = "Eligibility Assessment";
                }
            }
        }

        // ============================================================
        // DM controlling factor
        // JS: if (controllingFactor === 'DM' && 
        //         (dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && 
        //         (dates.SC instanceof Date && !isNaN(dates.SC.getTime())))
        // ============================================================
        if ($controllingFactor === 'DM' && self::is_valid_date($DM) && self::is_valid_date($SC)) {

            // JS: if (dates.Today >= dates.DMC && dates.Today >= dates.LPR3)
            if ($Today >= $DMC && $Today >= $LPR3) {
                $controllingDate = $DMC;
                $controllingDesc = "DMC - 2A";
                $status = "Eligible Now";
            }
            // JS: else if (dates.Today >= dates.DMC)
            elseif ($Today >= $DMC) {
                $controllingDate = $LPR3;
                $controllingDesc = "LPR3 - 2B";
                $status = "Prepare, but file later";
            }
            // JS: else if (dates.Today >= dates.DM2)
            elseif ($Today >= $DM2) {
                // JS: if (dates.DM2 >= dates.LPR3)
                if ($DM2 >= $LPR3) {
                    $controllingDate = $DMC;
                    $controllingDesc = "DMC - 2D";
                    $status = "Prepare, but file later";
                }
                // JS: else if (dates.DM2 >= dates.LPR2)
                elseif ($DM2 >= $LPR2) {
                    $controllingDate = $DMC;
                    $controllingDesc = "DMC - 2E";
                    $status = "Prepare, but file later";
                }
                // JS: else if (dates.Today >= dates.LPR2)
                elseif ($Today >= $LPR2) {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2F";
                    $status = "Prepare, but file later";
                }
                // JS: else
                else {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2G";
                    $status = "Eligibility Assessment";
                }
            }
            // JS: else (Today < DM2)
            else {
                // JS: if (dates.DM2 >= dates.LPR2)
                if ($DM2 >= $LPR2) {
                    $controllingDate = $DMC;
                    $controllingDesc = "DMC - 2H";
                    $status = "Eligibility Assessment";
                }
                // JS: else
                else {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2I";
                    $status = "Eligibility Assessment";
                }
            }
        }

        // ============================================================
        // SC controlling factor
        // JS: if (controllingFactor === 'SC' && 
        //         (dates.DM instanceof Date && !isNaN(dates.DM.getTime())) && 
        //         (dates.SC instanceof Date && !isNaN(dates.SC.getTime())))
        // ============================================================
        if ($controllingFactor === 'SC' && self::is_valid_date($DM) && self::is_valid_date($SC)) {

            // JS: if (dates.Today >= dates.SCC && dates.Today >= dates.LPR3)
            if ($Today >= $SCC && $Today >= $LPR3) {
                $controllingDate = $SCC;
                $controllingDesc = "SCC - 2A";
                $status = "Eligible Now";
            }
            // JS: else if (dates.Today >= dates.SCC)
            elseif ($Today >= $SCC) {
                $controllingDate = $LPR3;
                $controllingDesc = "LPR3 - 2B";
                $status = "Prepare, but file later";
            }
            // JS: else if (dates.Today >= dates.SC2)
            elseif ($Today >= $SC2) {
                // JS: if (dates.SC2 >= dates.LPR3)
                if ($SC2 >= $LPR3) {
                    $controllingDate = $SCC;
                    $controllingDesc = "SCC - 2D";
                    $status = "Prepare, but file later";
                }
                // JS: else if (dates.SC2 >= dates.LPR2)
                elseif ($SC2 >= $LPR2) {
                    $controllingDate = $SCC;
                    $controllingDesc = "SCC - 2E";
                    $status = "Prepare, but file later";
                }
                // JS: else if (dates.Today >= dates.LPR2)
                elseif ($Today >= $LPR2) {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2F";
                    $status = "Prepare, but file later";
                }
                // JS: else
                else {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2G";
                    $status = "Eligibility Assessment";
                }
            }
            // JS: else (Today < SC2)
            else {
                // JS: if (dates.SC2 >= dates.LPR2)
                if ($SC2 >= $LPR2) {
                    $controllingDate = $SCC;
                    $controllingDesc = "SCC - 2H";
                    $status = "Eligibility Assessment";
                }
                // JS: else
                else {
                    $controllingDate = $LPR3;
                    $controllingDesc = "LPR3 - 2I";
                    $status = "Eligibility Assessment";
                }
            }
        }

        return [
            'controllingFactor' => $controllingFactor ?? '',
            'controllingDate'   => $controllingDate,
            'controllingDesc'   => $controllingDesc ?? '',
            'status'            => $status,
        ];
    }

    /**
     * Run full eligibility recalculation for a Form 70 entry.
     * 
     * This is the main entry point. It:
     * 1. Reads source dates from the entry
     * 2. Applies the marriedValue override (if Today >= LPRC, treat as "No")
     * 3. Computes all derived dates
     * 4. Runs the eligibility determination
     * 5. Returns all results (does NOT save - caller decides what to do)
     * 
     * @param array $entry The Gravity Forms entry array
     * @param \DateTime $today The "today" date to use for calculations
     * @return array With keys:
     *   'dates' => all computed dates (DateTime objects)
     *   'result' => controllingFactor, controllingDate, controllingDesc, status
     *   'married_value' => the effective marriedValue used
     */
    public static function recalculate(array $entry, \DateTime $today): array {
        // Read source dates from entry
        $LPR = self::parse_date($entry['23'] ?? null);
        $DM  = self::parse_date($entry['18'] ?? null);
        $SC  = self::parse_date($entry['17'] ?? null);

        // Read stored marriedValue from entry (field 12)
        $stored_married_value = trim($entry['12'] ?? '');

        // Compute derived dates (need LPRC to determine marriedValue override)
        $source_dates = [
            'Today' => $today,
            'LPR'   => $LPR,
            'DM'    => $DM,
            'SC'    => $SC,
        ];
        $dates = self::compute_derived_dates($source_dates);

        // Apply marriedValue override:
        // If Today >= LPRC, treat marriedValue as "No" regardless of stored value
        // But do NOT clear any stored values
        $effective_married_value = $stored_married_value;
        if (self::is_valid_date($dates['LPRC']) && $today >= $dates['LPRC']) {
            $effective_married_value = 'No';
        }

        // Run eligibility determination
        $result = self::determine_controlling_factor($dates, $effective_married_value);

        // Format controllingDate for storage (m/d/Y)
        if ($result['controllingDate'] instanceof \DateTime) {
            $result['controllingDate'] = self::format_date($result['controllingDate']);
        } elseif ($result['controllingDate'] === null) {
            $result['controllingDate'] = '';
        }
        // If it's already a string (empty string from the "one of DM/SC" clear), leave it

        return [
            'dates'          => $dates,
            'result'         => $result,
            'married_value'  => $effective_married_value,
        ];
    }

    /**
     * Format all derived dates for entry storage (m/d/Y).
     * Returns an array mapping Form 70 field IDs to formatted date strings.
     * 
     * @param array $dates The dates array from compute_derived_dates()
     * @return array Field ID => formatted date string
     */
    public static function format_dates_for_storage(array $dates): array {
        return [
            '25' => self::format_date($dates['LPR2']),   // LPR + 2 years - 90 days
            '28' => self::format_date($dates['LPR3']),   // LPR + 3 years - 90 days
            '27' => self::format_date($dates['LPR4']),   // LPR + 4 years - 90 days
            '26' => self::format_date($dates['LPRC']),   // LPR + 5 years - 90 days
            '32' => self::format_date($dates['DM2']),    // DM + 2 years
            '31' => self::format_date($dates['DMC']),    // DM + 3 years
            '30' => self::format_date($dates['SC2']),    // SC + 2 years
            '29' => self::format_date($dates['SCC']),    // SC + 3 years
        ];
    }

    /**
     * Map Form 70 field IDs to Form 75 field IDs for syncing.
     * 
     * @return array Form 70 field ID => Form 75 field ID
     */
    public static function get_form75_field_map(): array {
        return [
            // Derived dates
            '25' => '898',   // LPR2
            '28' => '900',   // LPR3
            '27' => '899',   // LPR4
            '26' => '901',   // LPRC
            '32' => '904',   // DM2
            '31' => '903',   // DMC
            '30' => '905',   // SC2
            '29' => '902',   // SCC
            // Eligibility fields
            '34' => '894',   // Controlling Factor
            '35' => '895',   // Application Date (controllingDate)
            '36' => '896',   // Description (controllingDesc)
            '37' => '897',   // Status
            // Today
            '24' => '32',    // Today's Date
        ];
    }
}
