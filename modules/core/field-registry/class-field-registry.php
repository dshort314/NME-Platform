<?php
/**
 * Field Registry
 * 
 * All form IDs, field IDs, page IDs, and view IDs as constants.
 * This is the single source of truth - no magic numbers elsewhere.
 */

namespace NME\Core\FieldRegistry;

defined('ABSPATH') || exit;

class FieldRegistry {

    // =========================================================================
    // FORM IDS
    // =========================================================================
    
    const FORM_MASTER                  = 75;
    const FORM_INFORMATION_ABOUT_YOU   = 70;
    const FORM_MARITAL_HISTORY         = 71;
    const FORM_ADDITIONAL_INFORMATION  = 39;
    const FORM_RESIDENCES              = 38;
    const FORM_TIME_OUTSIDE            = 42;
    const FORM_CHILDREN                = 72;
    const FORM_EMPLOYMENT              = 73;
    const FORM_CRIMINAL_HISTORY        = 74;
    const FORM_PRELIMINARY_ELIGIBILITY = 78;

    // =========================================================================
    // FORM 75 (MASTER) FIELD IDS
    // =========================================================================
    
    // Self-reference
    const MASTER_FIELD_SELF_REF        = 892;
    
    // User identification
    const MASTER_FIELD_ANUMBER         = 1;
    const MASTER_FIELD_DOB             = 737;
    const MASTER_FIELD_LPR_DATE        = 738;
    
    // Controlling factors
    const MASTER_FIELD_CONTROLLING_FACTOR = 894;
    const MASTER_FIELD_APPLICATION_DATE   = 895;
    
    // Marital fields (written by Form 71)
    const MASTER_FIELD_ARMED_FORCES    = 759;
    const MASTER_FIELD_TIMES_MARRIED   = 761;
    const MASTER_FIELD_SPOUSE_ANUMBER  = 768;
    const MASTER_FIELD_SPOUSE_TIMES_MARRIED = 769;
    
    // Nested form attachment fields
    const MASTER_FIELD_NESTED_RESIDENCES  = 727;
    const MASTER_FIELD_NESTED_TOC         = 728;
    const MASTER_FIELD_NESTED_CHILDREN    = 772;
    const MASTER_FIELD_NESTED_EMPLOYMENT  = 773;
    const MASTER_FIELD_NESTED_CRIMINAL    = 891;

    // =========================================================================
    // FORM 70 (INFORMATION ABOUT YOU) FIELD IDS
    // =========================================================================
    
    const IAY_FIELD_ANUMBER            = 10;
    const IAY_FIELD_DOB                = 5;
    const IAY_FIELD_LPR_DATE           = 23;
    const IAY_FIELD_MARITAL_STATUS     = 11;
    const IAY_FIELD_PARENT_ENTRY_ID    = 50;

    // =========================================================================
    // FORM 38 (RESIDENCES) FIELD IDS
    // =========================================================================
    
    const RES_FIELD_ANUMBER            = 1;
    const RES_FIELD_FROM_DATE          = 3;
    const RES_FIELD_TO_DATE            = 4;
    const RES_FIELD_DURATION           = 5;
    const RES_FIELD_TOTAL_DURATION     = 8;
    const RES_FIELD_PARENT_ENTRY_ID    = 11;
    const RES_FIELD_STATE              = 13; // Subfield .4 for state

    // =========================================================================
    // FORM 42 (TIME OUTSIDE) FIELD IDS
    // =========================================================================
    
    const TOC_FIELD_ANUMBER            = 4;
    const TOC_FIELD_DEPARTURE_DATE     = 5;
    const TOC_FIELD_RETURN_DATE        = 6;
    const TOC_FIELD_COUNTRIES          = 7;
    const TOC_FIELD_DURATION           = 8;
    const TOC_FIELD_DURATION_RECEIVED  = 9;
    const TOC_FIELD_TOTAL_DURATION     = 10;
    const TOC_FIELD_PARENT_ENTRY_ID    = 12;

    // =========================================================================
    // CONTROLLING FACTOR VALUES
    // =========================================================================
    
    const CF_DATE_OF_MARRIAGE          = 'DM';
    const CF_SPOUSE_CITIZEN            = 'SC';
    const CF_LPR                       = 'LPR';
    const CF_LPR_MARRIAGE              = 'LPRM';
    const CF_LPR_SPOUSE                = 'LPRS';

    // =========================================================================
    // LOOKBACK PERIODS
    // =========================================================================
    
    const LOOKBACK_3_YEAR              = 3;
    const LOOKBACK_5_YEAR              = 5;
    const DAYS_REQUIRED_3_YEAR         = 548;
    const DAYS_REQUIRED_5_YEAR         = 913;

    // =========================================================================
    // PAGE IDS
    // =========================================================================
    
    // Information About You
    const PAGE_IAY_FORM                = 703;
    const PAGE_IAY_STYLES              = 704;
    const PAGE_IAY_VIEW                = 753;
    
    // Marital History
    const PAGE_MARITAL_HISTORY         = 707;
    
    // Residences
    const PAGE_RES_ADD                 = 504;
    const PAGE_RES_DASHBOARD           = 505;
    const PAGE_RES_LIST                = 506;
    const PAGE_RES_EDIT                = 514;
    
    // Time Outside
    const PAGE_TOC_ADD                 = 582;
    const PAGE_TOC_DASHBOARD_1         = 705;
    const PAGE_TOC_DASHBOARD_2         = 706;
    
    // Children
    const PAGE_CHILDREN                = 708;
    const PAGE_CHILDREN_ALT            = 841;
    
    // Employment
    const PAGE_EMPLOYMENT              = 709;
    const PAGE_EMPLOYMENT_ALT          = 856;
    
    // Additional Information / Criminal
    const PAGE_ADDITIONAL_INFO         = 710;

    // =========================================================================
    // GRAVITYVIEW IDS
    // =========================================================================
    
    const VIEW_IAY                     = 720;
    const VIEW_MARITAL_HISTORY         = 721;
    const VIEW_ADDITIONAL_INFO         = 701;
    const VIEW_RESIDENCES              = 702;
    const VIEW_RESIDENCES_EDIT         = 513;
    const VIEW_TOC                     = 581;
    const VIEW_TOC_ALT                 = 719;
    const VIEW_CHILDREN                = 839;

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get lookback years for a controlling factor
     */
    public static function get_lookback_years(string $controlling_factor): int {
        return in_array($controlling_factor, [self::CF_DATE_OF_MARRIAGE, self::CF_SPOUSE_CITIZEN])
            ? self::LOOKBACK_3_YEAR
            : self::LOOKBACK_5_YEAR;
    }

    /**
     * Get days required for a controlling factor
     */
    public static function get_days_required(string $controlling_factor): int {
        return in_array($controlling_factor, [self::CF_DATE_OF_MARRIAGE, self::CF_SPOUSE_CITIZEN])
            ? self::DAYS_REQUIRED_3_YEAR
            : self::DAYS_REQUIRED_5_YEAR;
    }

    /**
     * Check if controlling factor is 3-year
     */
    public static function is_three_year(string $controlling_factor): bool {
        return in_array($controlling_factor, [self::CF_DATE_OF_MARRIAGE, self::CF_SPOUSE_CITIZEN]);
    }
}
