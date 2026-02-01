<?php
/**
 * User Context
 * 
 * Single point of access for user application data.
 * Caches values per request to avoid repeated database calls.
 */

namespace NME\Core\UserContext;

defined('ABSPATH') || exit;

class UserContext {

    /** @var array Cached values per user ID */
    private static array $cache = [];

    /** @var string User meta key for A-Number */
    const META_ANUMBER = 'anumber';

    /** @var string User meta key for parent entry ID */
    const META_PARENT_ENTRY_ID = 'parent_entry_id';

    /** @var string User meta key for date of birth */
    const META_DOB = 'dob';

    /** @var string User meta key for eligibility unlock date (purgatory) */
    const META_ELIGIBILITY_UNLOCK_DATE = 'nme_eligibility_unlock_date';

    /** @var string User meta key for purgatory message */
    const META_PURGATORY_MESSAGE = 'nme_purgatory_message';

    /** @var string User meta key for controlling description */
    const META_CONTROLLING_DESC = 'nme_controlling_desc';

    // =========================================================================
    // GETTERS
    // =========================================================================

    /**
     * Get the current user's A-Number
     */
    public static function get_anumber(?int $user_id = null): ?string {
        return self::get_meta(self::META_ANUMBER, $user_id);
    }

    /**
     * Get the current user's parent entry ID (Form 75 Master entry)
     */
    public static function get_parent_entry_id(?int $user_id = null): ?int {
        $value = self::get_meta(self::META_PARENT_ENTRY_ID, $user_id);
        return $value ? (int) $value : null;
    }

    /**
     * Get the current user's date of birth
     */
    public static function get_dob(?int $user_id = null): ?string {
        return self::get_meta(self::META_DOB, $user_id);
    }

    /**
     * Get all user context values at once
     */
    public static function get_all(?int $user_id = null): array {
        return [
            'anumber'         => self::get_anumber($user_id),
            'parent_entry_id' => self::get_parent_entry_id($user_id),
            'dob'             => self::get_dob($user_id),
        ];
    }

    // =========================================================================
    // SETTERS
    // =========================================================================

    /**
     * Set the user's A-Number
     */
    public static function set_anumber(string $value, ?int $user_id = null): bool {
        return self::set_meta(self::META_ANUMBER, $value, $user_id);
    }

    /**
     * Set the user's parent entry ID
     */
    public static function set_parent_entry_id(int $value, ?int $user_id = null): bool {
        return self::set_meta(self::META_PARENT_ENTRY_ID, $value, $user_id);
    }

    /**
     * Set the user's date of birth
     */
    public static function set_dob(string $value, ?int $user_id = null): bool {
        return self::set_meta(self::META_DOB, $value, $user_id);
    }

    /**
     * Set all user context values at once
     */
    public static function set_all(array $values, ?int $user_id = null): bool {
        $success = true;

        if (isset($values['anumber'])) {
            $success = self::set_anumber($values['anumber'], $user_id) && $success;
        }

        if (isset($values['parent_entry_id'])) {
            $success = self::set_parent_entry_id((int) $values['parent_entry_id'], $user_id) && $success;
        }

        if (isset($values['dob'])) {
            $success = self::set_dob($values['dob'], $user_id) && $success;
        }

        return $success;
    }

    // =========================================================================
    // DELETE
    // =========================================================================

    /**
     * Delete a specific user meta value
     */
    public static function delete_meta(string $key, ?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Clear cache
        unset(self::$cache[$user_id][$key]);

        return delete_user_meta($user_id, $key);
    }

    /**
     * Delete all user context values
     */
    public static function delete_all(?int $user_id = null): bool {
        $success = true;
        $success = self::delete_meta(self::META_ANUMBER, $user_id) && $success;
        $success = self::delete_meta(self::META_PARENT_ENTRY_ID, $user_id) && $success;
        $success = self::delete_meta(self::META_DOB, $user_id) && $success;
        $success = self::delete_meta(self::META_ELIGIBILITY_UNLOCK_DATE, $user_id) && $success;
        $success = self::delete_meta(self::META_PURGATORY_MESSAGE, $user_id) && $success;
        $success = self::delete_meta(self::META_CONTROLLING_DESC, $user_id) && $success;
        return $success;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Check if user has started an application (has parent_entry_id)
     */
    public static function has_application(?int $user_id = null): bool {
        return self::get_parent_entry_id($user_id) !== null;
    }

    /**
     * Check if user has a valid A-Number set
     */
    public static function has_anumber(?int $user_id = null): bool {
        $anumber = self::get_anumber($user_id);
        return $anumber !== null && $anumber !== '';
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Get a user meta value with caching
     */
    private static function get_meta(string $key, ?int $user_id = null): ?string {
        $user_id = $user_id ?? get_current_user_id();

        if (!$user_id) {
            return null;
        }

        // Check cache first
        if (isset(self::$cache[$user_id][$key])) {
            return self::$cache[$user_id][$key];
        }

        // Get from database
        $value = get_user_meta($user_id, $key, true);

        // Cache it (even empty values to avoid repeated lookups)
        if (!isset(self::$cache[$user_id])) {
            self::$cache[$user_id] = [];
        }
        self::$cache[$user_id][$key] = $value ?: null;

        return self::$cache[$user_id][$key];
    }

    /**
     * Set a user meta value and update cache
     */
    private static function set_meta(string $key, $value, ?int $user_id = null): bool {
        $user_id = $user_id ?? get_current_user_id();

        if (!$user_id) {
            return false;
        }

        $result = update_user_meta($user_id, $key, $value);

        // Update cache
        if (!isset(self::$cache[$user_id])) {
            self::$cache[$user_id] = [];
        }
        self::$cache[$user_id][$key] = (string) $value;

        return $result !== false;
    }

    /**
     * Clear the cache (useful for testing or after bulk operations)
     */
    public static function clear_cache(?int $user_id = null): void {
        if ($user_id) {
            unset(self::$cache[$user_id]);
        } else {
            self::$cache = [];
        }
    }
}