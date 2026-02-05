<?php
/**
 * Delete Entries
 * 
 * Admin tool to delete all Gravity Forms entries for a user.
 * Optionally emails a backup of Form 75 data before deletion.
 */

namespace NME\Admin\DeleteEntries;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\MasterForm\MasterForm;

defined('ABSPATH') || exit;

class DeleteEntries {

    /** @var string Admin page slug */
    const PAGE_SLUG = 'nme-delete-entries';

    /**
     * Initialize admin functionality
     */
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [__CLASS__, 'add_submenu_page'], 20);
        add_action('admin_post_nme_delete_entries', [__CLASS__, 'handle_delete_request']);
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page(): void {
        add_submenu_page(
            'nme-platform',
            'Delete User Entries',
            'Delete Entries',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Render the admin page
     */
    public static function render_page(): void {
        $message = '';
        $message_type = '';

        if (isset($_GET['deleted'])) {
            $count = (int) $_GET['deleted'];
            $message = sprintf('Successfully deleted %d entries.', $count);
            $message_type = 'success';
        }

        if (isset($_GET['error'])) {
            $message = sanitize_text_field($_GET['error']);
            $message_type = 'error';
        }

        ?>
        <div class="wrap">
            <h1>Delete User Entries</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width: 600px; padding: 20px;">
                <h2>Delete All Entries for a User</h2>
                <p>This will permanently delete all Gravity Forms entries associated with a user's A-Number.</p>
                
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="nme_delete_entries">
                    <?php wp_nonce_field('nme_delete_entries', 'nme_delete_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="user_select">Select User</label>
                            </th>
                            <td>
                                <?php
                                wp_dropdown_users([
                                    'name'             => 'user_id',
                                    'id'               => 'user_select',
                                    'show_option_none' => '— Select User —',
                                    'option_none_value'=> '',
                                ]);
                                ?>
                                <p class="description">Or enter A-Number directly below</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="anumber">A-Number</label>
                            </th>
                            <td>
                                <input type="text" 
                                       name="anumber" 
                                       id="anumber" 
                                       class="regular-text"
                                       placeholder="e.g., 123456789">
                                <p class="description">9-digit A-Number (overrides user selection)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Options</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_backup" value="1" checked>
                                    Email backup of Form 75 data before deletion
                                </label>
                                <br><br>
								<label for="backup_email">Backup Emails (comma-separated):</label><br>
                                <input type="text" 
                                       name="backup_email" 
                                       id="backup_email" 
                                       class="regular-text"
                                       value="nme@wpexpertcare.com, contact@verderlex.net">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Confirmation</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="confirm_delete" value="1" required>
                                    I understand this action cannot be undone
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary button-large" 
                                onclick="return confirm('Are you sure you want to delete all entries for this user?');">
                            Delete All Entries
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Handle the delete request
     */
    public static function handle_delete_request(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nme_delete_nonce'] ?? '', 'nme_delete_entries')) {
            wp_die('Security check failed');
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        // Check confirmation
        if (empty($_POST['confirm_delete'])) {
            self::redirect_with_error('Please confirm the deletion');
            return;
        }

        // Get A-Number
        $anumber = sanitize_text_field($_POST['anumber'] ?? '');
        
        if (empty($anumber) && !empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
            $anumber = UserContext::get_anumber($user_id);
        }

        if (empty($anumber)) {
            self::redirect_with_error('No A-Number provided or found for user');
            return;
        }

        // Send backup if requested
        if (!empty($_POST['send_backup'])) {
            $backup_email = sanitize_text_field($_POST['backup_email'] ?? '');
            if ($backup_email) {
                self::send_backup_email($anumber, $backup_email);
            }
        }

        // Delete entries
        $deleted_count = self::delete_entries_for_anumber($anumber);

        // Clear user meta if user was selected
        if (!empty($_POST['user_id'])) {
            $user_id = (int) $_POST['user_id'];
            UserContext::delete_all($user_id);
        }

        // Redirect with success
        wp_redirect(add_query_arg([
            'page'    => self::PAGE_SLUG,
            'deleted' => $deleted_count,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Delete all entries for an A-Number
     */
    public static function delete_entries_for_anumber(string $anumber): int {
        global $wpdb;

        if (!class_exists('GFAPI')) {
            return 0;
        }

        $deleted_count = 0;

        // Forms and their A-Number field IDs
        $forms = [
            FieldRegistry::FORM_MASTER                 => FieldRegistry::MASTER_FIELD_ANUMBER,
            FieldRegistry::FORM_INFORMATION_ABOUT_YOU  => FieldRegistry::IAY_FIELD_ANUMBER,
            FieldRegistry::FORM_RESIDENCES             => FieldRegistry::RES_FIELD_ANUMBER,
            FieldRegistry::FORM_TIME_OUTSIDE           => FieldRegistry::TOC_FIELD_ANUMBER,
        ];

        foreach ($forms as $form_id => $anumber_field) {
            // Find entries
            $entry_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT e.id FROM {$wpdb->prefix}gf_entry e
                 INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
                 WHERE e.form_id = %d 
                 AND em.meta_key = %s 
                 AND em.meta_value = %s",
                $form_id,
                (string) $anumber_field,
                $anumber
            ));

            // Delete each entry
            foreach ($entry_ids as $entry_id) {
                $result = \GFAPI::delete_entry($entry_id);
                if (!is_wp_error($result)) {
                    $deleted_count++;
                }
            }
        }

        return $deleted_count;
    }

    /**
     * Send backup email with Form 75 data
     */
    private static function send_backup_email(string $anumber, string $email): bool {
        global $wpdb;

        // Find Form 75 entry
        $entry_id = $wpdb->get_var($wpdb->prepare(
            "SELECT e.id FROM {$wpdb->prefix}gf_entry e
             INNER JOIN {$wpdb->prefix}gf_entry_meta em ON e.id = em.entry_id
             WHERE e.form_id = %d 
             AND em.meta_key = %s 
             AND em.meta_value = %s
             LIMIT 1",
            FieldRegistry::FORM_MASTER,
            (string) FieldRegistry::MASTER_FIELD_ANUMBER,
            $anumber
        ));

        if (!$entry_id) {
            return false;
        }

        $entry = MasterForm::get_entry((int) $entry_id);

        if (!$entry) {
            return false;
        }

        // Build email content
        $subject = sprintf('NME Platform Backup - A-Number: %s', $anumber);
        
        $body = "Form 75 (Master) Entry Backup\n";
        $body .= "=============================\n\n";
        $body .= sprintf("A-Number: %s\n", $anumber);
        $body .= sprintf("Entry ID: %s\n", $entry_id);
        $body .= sprintf("Date Created: %s\n", $entry['date_created'] ?? 'N/A');
        $body .= sprintf("Backup Date: %s\n\n", current_time('mysql'));
        $body .= "Entry Data:\n";
        $body .= "-----------\n\n";

        foreach ($entry as $key => $value) {
            if (is_numeric($key) && !empty($value)) {
                $body .= sprintf("Field %s: %s\n", $key, $value);
            }
        }

        $emails = array_map('trim', explode(',', $email));
		$emails = array_filter($emails, 'is_email');

		if (empty($emails)) {
			return false;
		}

		return wp_mail($emails, $subject, $body);
    }

    /**
     * Redirect with error message
     */
    private static function redirect_with_error(string $message): void {
        wp_redirect(add_query_arg([
            'page'  => self::PAGE_SLUG,
            'error' => urlencode($message),
        ], admin_url('admin.php')));
        exit;
    }
}