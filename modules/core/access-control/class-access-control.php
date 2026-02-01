<?php
/**
 * Access Control
 * 
 * Manages eligibility-based access restrictions for users who are
 * more than 1 year away from their filing date ("Eligibility Assessment" status).
 * 
 * When a user submits Form 70 (Information About You) and their status is
 * "Eligibility Assessment", they are redirected to /purgatory/ and locked out
 * of most application pages until their unlock date (6 months before filing date).
 */

namespace NME\Core\AccessControl;

use NME\Core\Plugin;

defined('ABSPATH') || exit;

class AccessControl {

    /** @var string User meta key for unlock date */
    const META_UNLOCK_DATE = 'nme_eligibility_unlock_date';

    /** @var string User meta key for purgatory message */
    const META_PURGATORY_MESSAGE = 'nme_purgatory_message';

    /** @var string User meta key for controlling description (for reference) */
    const META_CONTROLLING_DESC = 'nme_controlling_desc';

    /** @var array Page paths that are always accessible (even when locked) */
    const ALLOWED_PATHS = [
        '/purgatory/',
        '/application/documents/',
        '/logout/',
        '/my-account/',
        '/wp-admin/',
    ];

    /** @var array Page paths that require eligibility access */
    const RESTRICTED_PATHS = [
        '/application/information-about-you/',
        '/application/information-about-you-view/',
        '/application/time-outside-the-us/',
        '/application/time-outside-the-us-view/',
        '/application/residences/',
        '/application/residence-view/',
        '/application/marital-history/',
        '/application/marital-history-view/',
        '/application/children/',
        '/application/children-view/',
        '/application/employment-school/',
        '/application/employment-school-view/',
        '/application/additional-information/',
        '/application/additional-information-view/',
        '/application/dashboard/',
    ];

    /**
     * Initialize access control hooks
     */
    public static function init(): void {
        // Check access on page load (high priority to run early)
        add_action('template_redirect', [__CLASS__, 'check_page_access'], 5);
    }

    /**
     * Check if user is currently locked out
     * 
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool True if user is locked out
     */
    public static function is_locked_out(?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        $unlock_date = get_user_meta($user_id, self::META_UNLOCK_DATE, true);
        
        if (empty($unlock_date)) {
            return false;
        }

        // Parse the unlock date
        $unlock_datetime = \DateTime::createFromFormat('Y-m-d', $unlock_date);
        if (!$unlock_datetime) {
            // Try alternate format
            $unlock_datetime = \DateTime::createFromFormat('m/d/Y', $unlock_date);
        }

        if (!$unlock_datetime) {
            // Invalid date stored, clear it
            self::clear_lockout($user_id);
            return false;
        }

        // Compare with today
        $today = new \DateTime('today');
        
        if ($today >= $unlock_datetime) {
            // Unlock date has passed, clear the lockout
            self::clear_lockout($user_id);
            return false;
        }

        return true;
    }

    /**
     * Get the unlock date for a user
     * 
     * @param int|null $user_id User ID (defaults to current user)
     * @return string|null Unlock date in Y-m-d format, or null if not locked
     */
    public static function get_unlock_date(?int $user_id = null): ?string {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return null;
        }

        $unlock_date = get_user_meta($user_id, self::META_UNLOCK_DATE, true);
        
        return !empty($unlock_date) ? $unlock_date : null;
    }

    /**
     * Get the purgatory message for a user
     * 
     * @param int|null $user_id User ID (defaults to current user)
     * @return string|null Message HTML or null if not set
     */
    public static function get_purgatory_message(?int $user_id = null): ?string {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return null;
        }

        $message = get_user_meta($user_id, self::META_PURGATORY_MESSAGE, true);
        
        return !empty($message) ? $message : null;
    }

    /**
     * Set lockout for a user
     * 
     * @param string $unlock_date Unlock date (Y-m-d or m/d/Y format)
     * @param string $message Purgatory message HTML
     * @param string $controlling_desc Controlling description for reference
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Success
     */
    public static function set_lockout(
        string $unlock_date, 
        string $message, 
        string $controlling_desc = '',
        ?int $user_id = null
    ): bool {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        // Normalize date to Y-m-d format
        $date_obj = \DateTime::createFromFormat('Y-m-d', $unlock_date);
        if (!$date_obj) {
            $date_obj = \DateTime::createFromFormat('m/d/Y', $unlock_date);
        }

        if (!$date_obj) {
            error_log('NME Platform - Access Control: Invalid unlock date format: ' . $unlock_date);
            return false;
        }

        $normalized_date = $date_obj->format('Y-m-d');

        // Store the lockout data
        update_user_meta($user_id, self::META_UNLOCK_DATE, $normalized_date);
        update_user_meta($user_id, self::META_PURGATORY_MESSAGE, $message);
        
        if (!empty($controlling_desc)) {
            update_user_meta($user_id, self::META_CONTROLLING_DESC, $controlling_desc);
        }

        $debug_mode = Plugin::is_debug_enabled('access-control');
        if ($debug_mode) {
            error_log('NME Platform - Access Control: Set lockout for user ' . $user_id . ' until ' . $normalized_date);
        }

        return true;
    }

    /**
     * Clear lockout for a user
     * 
     * @param int|null $user_id User ID (defaults to current user)
     * @return bool Success
     */
    public static function clear_lockout(?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();
        
        if (!$user_id) {
            return false;
        }

        delete_user_meta($user_id, self::META_UNLOCK_DATE);
        delete_user_meta($user_id, self::META_PURGATORY_MESSAGE);
        delete_user_meta($user_id, self::META_CONTROLLING_DESC);

        $debug_mode = Plugin::is_debug_enabled('access-control');
        if ($debug_mode) {
            error_log('NME Platform - Access Control: Cleared lockout for user ' . $user_id);
        }

        return true;
    }

    /**
     * Calculate unlock date from controlling/application date
     * 
     * The unlock date is 6 months before the application/filing date.
     * 
     * @param string $application_date Application/filing date
     * @return string|null Unlock date in Y-m-d format, or null on error
     */
    public static function calculate_unlock_date(string $application_date): ?string {
        if (empty($application_date)) {
            return null;
        }

        // Parse the application date
        $date_obj = \DateTime::createFromFormat('Y-m-d', $application_date);
        if (!$date_obj) {
            $date_obj = \DateTime::createFromFormat('m/d/Y', $application_date);
        }
        if (!$date_obj) {
            $date_obj = \DateTime::createFromFormat('d/m/Y', $application_date);
        }

        if (!$date_obj) {
            error_log('NME Platform - Access Control: Could not parse application date: ' . $application_date);
            return null;
        }

        // Subtract 6 months
        $date_obj->sub(new \DateInterval('P6M'));

        return $date_obj->format('Y-m-d');
    }

    /**
     * Check if current page requires access control
     * 
     * Runs on template_redirect to enforce lockout
     */
    public static function check_page_access(): void {
        // Only check for logged-in users
        if (!is_user_logged_in()) {
            return;
        }

        $current_url = $_SERVER['REQUEST_URI'] ?? '';
        
        // Check if this is a restricted path
        $is_restricted = false;
        foreach (self::RESTRICTED_PATHS as $restricted_path) {
            if (strpos($current_url, $restricted_path) !== false) {
                $is_restricted = true;
                break;
            }
        }

        if (!$is_restricted) {
            return;
        }

        // Check if path is explicitly allowed
        foreach (self::ALLOWED_PATHS as $allowed_path) {
            if (strpos($current_url, $allowed_path) !== false) {
                return;
            }
        }

        // Check if user is locked out
        if (self::is_locked_out()) {
            $debug_mode = Plugin::is_debug_enabled('access-control');
            if ($debug_mode) {
                error_log('NME Platform - Access Control: Redirecting locked user to purgatory from ' . $current_url);
            }

            wp_redirect(home_url('/purgatory/'));
            exit;
        }
    }

    /**
     * Check if a status indicates "Eligibility Assessment" (more than 1 year away)
     * 
     * @param string $status The eligibility status
     * @param string $controlling_desc The controlling description
     * @return bool True if user should be locked out
     */
    public static function is_eligibility_assessment(string $status, string $controlling_desc = ''): bool {
        // Check status directly
        if ($status === 'Eligibility Assessment') {
            return true;
        }

        // Also check controlling description for "EA" suffix patterns
        $ea_patterns = [
            'LPRC - 1C',
            'LPRC - Married No Benefit EA',
            'LPRC - Spouse No Benefit EA',
            'LPR3 - 2G',
            'LPR3 - 2I',
            'DMC - 2H',
            'SCC - 2H',
            'SCC - 2I',
        ];

        foreach ($ea_patterns as $pattern) {
            if ($controlling_desc === $pattern) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get formatted unlock date for display
     * 
     * @param int|null $user_id User ID (defaults to current user)
     * @return string|null Formatted date or null
     */
    public static function get_formatted_unlock_date(?int $user_id = null): ?string {
        $unlock_date = self::get_unlock_date($user_id);
        
        if (!$unlock_date) {
            return null;
        }

        $date_obj = \DateTime::createFromFormat('Y-m-d', $unlock_date);
        
        if (!$date_obj) {
            return $unlock_date;
        }

        return $date_obj->format('F j, Y');
    }
}