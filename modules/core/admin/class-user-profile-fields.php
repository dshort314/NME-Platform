<?php
/**
 * Admin User Profile Fields
 * 
 * Displays all NME-related user meta fields on the WordPress admin
 * user profile page for easy viewing and debugging.
 */

namespace NME\Core\Admin;

use NME\Core\UserContext\UserContext;
use NME\Core\AccessControl\AccessControl;

defined('ABSPATH') || exit;

class UserProfileFields {

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Display fields on profile page
        add_action('show_user_profile', [__CLASS__, 'render_profile_fields'], 20);
        add_action('edit_user_profile', [__CLASS__, 'render_profile_fields'], 20);

        // Save fields from profile page
        add_action('personal_options_update', [__CLASS__, 'save_profile_fields']);
        add_action('edit_user_profile_update', [__CLASS__, 'save_profile_fields']);

        // Add admin styles
        add_action('admin_head', [__CLASS__, 'admin_styles']);
    }

    /**
     * Render NME fields on user profile page
     * 
     * @param \WP_User $user The user object
     */
    public static function render_profile_fields(\WP_User $user): void {
        // Only show to users who can edit users (administrators)
        if (!current_user_can('edit_users')) {
            return;
        }

        // Get all NME user meta (keys match UserContext constants)
        $anumber = get_user_meta($user->ID, 'anumber', true);
        $parent_entry_id = get_user_meta($user->ID, 'parent_entry_id', true);
        $dob = get_user_meta($user->ID, 'dob', true);
        
        // Access control / lockout fields
        $unlock_date = get_user_meta($user->ID, 'nme_eligibility_unlock_date', true);
        $purgatory_message = get_user_meta($user->ID, 'nme_purgatory_message', true);
        $controlling_desc = get_user_meta($user->ID, 'nme_controlling_desc', true);

        // Calculate lockout status
        $is_locked = false;
        $lock_status_text = 'Not Locked';
        $lock_status_class = 'nme-status-ok';

        if (!empty($unlock_date)) {
            $unlock_datetime = \DateTime::createFromFormat('Y-m-d', $unlock_date);
            if ($unlock_datetime) {
                $today = new \DateTime('today');
                if ($today < $unlock_datetime) {
                    $is_locked = true;
                    $lock_status_text = 'LOCKED until ' . $unlock_datetime->format('F j, Y');
                    $lock_status_class = 'nme-status-locked';
                } else {
                    $lock_status_text = 'Expired (was ' . $unlock_datetime->format('F j, Y') . ')';
                    $lock_status_class = 'nme-status-expired';
                }
            }
        }

        // Format DOB for display
        $dob_display = $dob;
        if (!empty($dob)) {
            $dob_obj = \DateTime::createFromFormat('Y-m-d', $dob);
            if (!$dob_obj) {
                $dob_obj = \DateTime::createFromFormat('m/d/Y', $dob);
            }
            if ($dob_obj) {
                $dob_display = $dob_obj->format('F j, Y') . ' (' . $dob . ')';
            }
        }

        ?>
        <div class="nme-admin-section">
            <h2>NME Application Data</h2>
            <p class="description">These fields are managed by the NME Application system. Edit with caution.</p>
            
            <table class="form-table nme-admin-table" role="presentation">
                <tbody>
                    <!-- Core Identity Fields -->
                    <tr>
                        <th colspan="2" class="nme-section-header">
                            <span class="dashicons dashicons-id"></span> Core Identity
                        </th>
                    </tr>
                    <tr>
                        <th><label for="nme_anumber">A-Number</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_anumber" 
                                   id="nme_anumber" 
                                   value="<?php echo esc_attr($anumber); ?>" 
                                   class="regular-text" />
                            <p class="description">Alien Registration Number (format: A#########)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nme_parent_entry_id">Parent Entry ID</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_parent_entry_id" 
                                   id="nme_parent_entry_id" 
                                   value="<?php echo esc_attr($parent_entry_id); ?>" 
                                   class="regular-text" />
                            <p class="description">Master Form (Form 75) Entry ID</p>
                            <?php if (!empty($parent_entry_id)): ?>
                                <p>
                                    <a href="<?php echo admin_url('admin.php?page=gf_entries&view=entry&id=75&lid=' . intval($parent_entry_id)); ?>" 
                                       target="_blank" 
                                       class="button button-small">
                                        View Master Entry
                                    </a>
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nme_dob">Date of Birth</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_dob" 
                                   id="nme_dob" 
                                   value="<?php echo esc_attr($dob); ?>" 
                                   class="regular-text" 
                                   placeholder="YYYY-MM-DD or MM/DD/YYYY" />
                            <?php if (!empty($dob_display) && $dob_display !== $dob): ?>
                                <p class="description"><?php echo esc_html($dob_display); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Access Control / Lockout Fields -->
                    <tr>
                        <th colspan="2" class="nme-section-header">
                            <span class="dashicons dashicons-lock"></span> Access Control (Eligibility Lockout)
                        </th>
                    </tr>
                    <tr>
                        <th>Lockout Status</th>
                        <td>
                            <span class="nme-status-badge <?php echo esc_attr($lock_status_class); ?>">
                                <?php echo esc_html($lock_status_text); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nme_eligibility_unlock_date">Unlock Date</label></th>
                        <td>
                            <input type="date" 
                                   name="nme_eligibility_unlock_date" 
                                   id="nme_eligibility_unlock_date" 
                                   value="<?php echo esc_attr($unlock_date); ?>" 
                                   class="regular-text" />
                            <p class="description">Date when user regains full access (leave empty to remove lockout)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nme_controlling_desc">Controlling Description</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_controlling_desc" 
                                   id="nme_controlling_desc" 
                                   value="<?php echo esc_attr($controlling_desc); ?>" 
                                   class="regular-text" 
                                   readonly />
                            <p class="description">Eligibility calculation code (e.g., LPRC - 1C, LPR3 - 2G)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="nme_purgatory_message">Purgatory Message</label></th>
                        <td>
                            <textarea name="nme_purgatory_message" 
                                      id="nme_purgatory_message" 
                                      rows="5" 
                                      class="large-text code"><?php echo esc_textarea($purgatory_message); ?></textarea>
                            <p class="description">HTML message displayed on /purgatory/ page</p>
                            <?php if (!empty($purgatory_message)): ?>
                                <details class="nme-message-preview">
                                    <summary>Preview Message</summary>
                                    <div class="nme-message-preview-content">
                                        <?php echo wp_kses_post($purgatory_message); ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Quick Actions -->
                    <tr>
                        <th colspan="2" class="nme-section-header">
                            <span class="dashicons dashicons-admin-tools"></span> Quick Actions
                        </th>
                    </tr>
                    <tr>
                        <th>Clear Lockout</th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       name="nme_clear_lockout" 
                                       id="nme_clear_lockout" 
                                       value="1" />
                                Clear all lockout data (unlock date, message, controlling desc)
                            </label>
                            <p class="description">Check this and save to immediately remove the user's lockout.</p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Save NME fields from user profile page
     * 
     * @param int $user_id The user ID being saved
     */
    public static function save_profile_fields(int $user_id): void {
        // Security check
        if (!current_user_can('edit_users')) {
            return;
        }

        // Check if clearing lockout
        if (isset($_POST['nme_clear_lockout']) && $_POST['nme_clear_lockout'] == '1') {
            delete_user_meta($user_id, 'nme_eligibility_unlock_date');
            delete_user_meta($user_id, 'nme_purgatory_message');
            delete_user_meta($user_id, 'nme_controlling_desc');
        } else {
            // Save individual fields (keys match UserContext constants)

            // A-Number
            if (isset($_POST['nme_anumber'])) {
                $anumber = sanitize_text_field($_POST['nme_anumber']);
                if (!empty($anumber)) {
                    update_user_meta($user_id, 'anumber', $anumber);
                } else {
                    delete_user_meta($user_id, 'anumber');
                }
            }

            // Parent Entry ID
            if (isset($_POST['nme_parent_entry_id'])) {
                $parent_entry_id = absint($_POST['nme_parent_entry_id']);
                if ($parent_entry_id > 0) {
                    update_user_meta($user_id, 'parent_entry_id', $parent_entry_id);
                } else {
                    delete_user_meta($user_id, 'parent_entry_id');
                }
            }

            // Date of Birth
            if (isset($_POST['nme_dob'])) {
                $dob = sanitize_text_field($_POST['nme_dob']);
                if (!empty($dob)) {
                    update_user_meta($user_id, 'dob', $dob);
                } else {
                    delete_user_meta($user_id, 'dob');
                }
            }

            // Unlock Date
            if (isset($_POST['nme_eligibility_unlock_date'])) {
                $unlock_date = sanitize_text_field($_POST['nme_eligibility_unlock_date']);
                if (!empty($unlock_date)) {
                    update_user_meta($user_id, 'nme_eligibility_unlock_date', $unlock_date);
                } else {
                    delete_user_meta($user_id, 'nme_eligibility_unlock_date');
                }
            }

            // Purgatory Message (allow HTML)
            if (isset($_POST['nme_purgatory_message'])) {
                $message = wp_kses_post($_POST['nme_purgatory_message']);
                if (!empty($message)) {
                    update_user_meta($user_id, 'nme_purgatory_message', $message);
                } else {
                    delete_user_meta($user_id, 'nme_purgatory_message');
                }
            }
        }
    }

    /**
     * Output admin styles for the profile fields
     */
    public static function admin_styles(): void {
        $screen = get_current_screen();
        if (!$screen || ($screen->id !== 'profile' && $screen->id !== 'user-edit')) {
            return;
        }

        ?>
        <style>
            .nme-admin-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-left: 4px solid #002D72;
                padding: 20px;
                margin: 20px 0;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }

            .nme-admin-section h2 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
                color: #002D72;
            }

            .nme-admin-table {
                margin-top: 15px;
            }

            .nme-section-header {
                background: #f5f5f5;
                padding: 12px 15px !important;
                font-size: 14px;
                font-weight: 600;
                color: #333;
                border-bottom: 1px solid #ddd;
            }

            .nme-section-header .dashicons {
                margin-right: 8px;
                color: #002D72;
            }

            .nme-status-badge {
                display: inline-block;
                padding: 4px 12px;
                border-radius: 3px;
                font-weight: 600;
                font-size: 13px;
            }

            .nme-status-ok {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
            }

            .nme-status-locked {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
            }

            .nme-status-expired {
                background: #fff3cd;
                color: #856404;
                border: 1px solid #ffeeba;
            }

            .nme-message-preview {
                margin-top: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
            }

            .nme-message-preview summary {
                padding: 8px 12px;
                background: #f5f5f5;
                cursor: pointer;
                font-weight: 500;
            }

            .nme-message-preview-content {
                padding: 15px;
                background: #fafafa;
                border-top: 1px solid #ddd;
                line-height: 1.6;
            }

            .nme-message-preview-content p {
                margin: 0 0 10px 0;
            }

            .nme-message-preview-content p:last-child {
                margin-bottom: 0;
            }

            .nme-message-preview-content em {
                color: #d63638;
            }

            .nme-admin-table input[readonly] {
                background: #f5f5f5;
                color: #666;
            }
        </style>
        <?php
    }
}