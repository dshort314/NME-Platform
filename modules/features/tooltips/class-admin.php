<?php
/**
 * Tooltips Admin
 * 
 * Admin settings page for configuring form field tooltips.
 * Registers under NME Platform menu.
 */

namespace NME\Features\Tooltips;

use NME\Core\FieldRegistry\FieldRegistry;

defined('ABSPATH') || exit;

class Admin {

    /** @var string Admin page slug */
    const PAGE_SLUG = 'nme-tooltips';

    /** @var string Option key */
    const OPTION_KEY = 'nme_platform_settings';

    /**
     * Initialize admin hooks
     */
    public static function init(): void {
        if (!is_admin()) {
            return;
        }

        add_action('admin_menu', [__CLASS__, 'add_submenu_page'], 20);
        add_action('wp_ajax_nme_save_tooltip', [__CLASS__, 'ajax_save_tooltip']);
        add_action('wp_ajax_nme_delete_tooltip', [__CLASS__, 'ajax_delete_tooltip']);
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page(): void {
        add_submenu_page(
            'nme-platform',
            'Form Tooltips',
            'Tooltips',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get form name by ID
     */
    private static function get_form_name(int $form_id): string {
        $names = [
            FieldRegistry::FORM_MASTER                 => 'Master Form (75)',
            FieldRegistry::FORM_INFORMATION_ABOUT_YOU  => 'Information About You (70)',
            FieldRegistry::FORM_TIME_OUTSIDE           => 'Time Outside US (76)',
            FieldRegistry::FORM_RESIDENCES             => 'Residences (77)',
            FieldRegistry::FORM_MARITAL_HISTORY        => 'Marital History (71)',
            FieldRegistry::FORM_CHILDREN               => 'Children (72)',
            FieldRegistry::FORM_EMPLOYMENT             => 'Employment (73)',
            FieldRegistry::FORM_CRIMINAL_HISTORY       => 'Criminal History (74)',
            FieldRegistry::FORM_ADDITIONAL_INFORMATION => 'Additional Information (39)',
            FieldRegistry::FORM_PRELIMINARY_ELIGIBILITY => 'Preliminary Eligibility (78)',
        ];

        return $names[$form_id] ?? 'Form ' . $form_id;
    }

    /**
     * Render admin page
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nme-platform'));
        }

        $allowed_forms = Tooltips::get_allowed_forms();
        $current_form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : ($allowed_forms[0] ?? 0);

        // Get tooltips for current form
        $tooltips = Tooltips::get_tooltips_for_form($current_form_id);

        // Get form fields
        $form = class_exists('GFAPI') ? \GFAPI::get_form($current_form_id) : [];
        $fields = [];

        if (is_array($form) && !empty($form['fields'])) {
            foreach ($form['fields'] as $f) {
                $fid = is_object($f) ? (int) $f->id : (int) ($f['id'] ?? 0);
                if ($fid <= 0) continue;

                $label = '';
                if (is_object($f)) {
                    if (!empty($f->adminLabel)) $label = (string) $f->adminLabel;
                    if ($label === '' && isset($f->label)) $label = (string) $f->label;
                } else {
                    if (!empty($f['adminLabel'])) $label = (string) $f['adminLabel'];
                    if ($label === '' && isset($f['label'])) $label = (string) $f['label'];
                }
                if ($label === '') $label = 'Field ' . $fid;

                $type = is_object($f) ? ($f->type ?? 'unknown') : ($f['type'] ?? 'unknown');

                $fields[$fid] = [
                    'id'    => $fid,
                    'label' => $label,
                    'type'  => $type,
                ];
            }
        }

        ?>
        <div class="wrap">
            <h1>Form Tooltips</h1>
            <p>Add help text tooltips to form fields. Tooltips appear as a "?" icon next to field labels.</p>

            <h2 class="nav-tab-wrapper">
                <?php foreach ($allowed_forms as $form_id): 
                    $active = ($form_id === $current_form_id) ? 'nav-tab-active' : '';
                    $url = add_query_arg(['page' => self::PAGE_SLUG, 'form_id' => $form_id], admin_url('admin.php'));
                ?>
                    <a href="<?php echo esc_url($url); ?>" class="nav-tab <?php echo $active; ?>">
                        <?php echo esc_html(self::get_form_name($form_id)); ?>
                    </a>
                <?php endforeach; ?>
            </h2>

            <div style="margin-top: 20px;">
                <?php if (empty($fields)): ?>
                    <p>No fields found for this form, or the form does not exist.</p>
                <?php else: ?>
                    <table class="widefat striped" style="max-width: 1000px;">
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 100px;">Type</th>
                                <th style="width: 250px;">Field Label</th>
                                <th>Tooltip Text</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $fid => $field): 
                                $tooltip_text = $tooltips[$fid] ?? '';
                                $has_tooltip = !empty($tooltip_text);
                            ?>
                            <tr data-field-id="<?php echo esc_attr($fid); ?>">
                                <td><strong><?php echo esc_html($fid); ?></strong></td>
                                <td><code><?php echo esc_html($field['type']); ?></code></td>
                                <td><?php echo esc_html($field['label']); ?></td>
                                <td>
                                    <textarea class="nme-tooltip-text" 
                                              rows="2" 
                                              style="width: 100%;"
                                              placeholder="Enter tooltip text..."><?php echo esc_textarea($tooltip_text); ?></textarea>
                                </td>
                                <td>
                                    <button type="button" class="button nme-save-tooltip">Save</button>
                                    <?php if ($has_tooltip): ?>
                                        <button type="button" class="button nme-delete-tooltip" style="color: #a00;">Delete</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <script>
                    jQuery(function($) {
                        var formId = <?php echo (int) $current_form_id; ?>;

                        $('.nme-save-tooltip').on('click', function() {
                            var $btn = $(this);
                            var $row = $btn.closest('tr');
                            var fieldId = $row.data('field-id');
                            var text = $row.find('.nme-tooltip-text').val().trim();

                            $btn.prop('disabled', true).text('Saving...');

                            $.post(ajaxurl, {
                                action: 'nme_save_tooltip',
                                form_id: formId,
                                field_id: fieldId,
                                text: text,
                                _wpnonce: '<?php echo wp_create_nonce('nme_tooltip_nonce'); ?>'
                            }, function(response) {
                                $btn.prop('disabled', false).text('Save');
                                if (response.success) {
                                    $btn.text('Saved!');
                                    setTimeout(function() { $btn.text('Save'); }, 1500);
                                    
                                    // Add delete button if not present
                                    if (text && !$row.find('.nme-delete-tooltip').length) {
                                        $btn.after(' <button type="button" class="button nme-delete-tooltip" style="color: #a00;">Delete</button>');
                                    }
                                } else {
                                    alert('Error saving tooltip: ' + (response.data || 'Unknown error'));
                                }
                            }).fail(function() {
                                $btn.prop('disabled', false).text('Save');
                                alert('Error saving tooltip');
                            });
                        });

                        $(document).on('click', '.nme-delete-tooltip', function() {
                            var $btn = $(this);
                            var $row = $btn.closest('tr');
                            var fieldId = $row.data('field-id');

                            if (!confirm('Delete this tooltip?')) return;

                            $btn.prop('disabled', true).text('Deleting...');

                            $.post(ajaxurl, {
                                action: 'nme_delete_tooltip',
                                form_id: formId,
                                field_id: fieldId,
                                _wpnonce: '<?php echo wp_create_nonce('nme_tooltip_nonce'); ?>'
                            }, function(response) {
                                if (response.success) {
                                    $row.find('.nme-tooltip-text').val('');
                                    $btn.remove();
                                } else {
                                    $btn.prop('disabled', false).text('Delete');
                                    alert('Error deleting tooltip');
                                }
                            }).fail(function() {
                                $btn.prop('disabled', false).text('Delete');
                                alert('Error deleting tooltip');
                            });
                        });
                    });
                    </script>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save tooltip
     */
    public static function ajax_save_tooltip(): void {
        check_ajax_referer('nme_tooltip_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $field_id = (int) ($_POST['field_id'] ?? 0);
        $text = sanitize_textarea_field($_POST['text'] ?? '');

        if ($form_id <= 0 || $field_id <= 0) {
            wp_send_json_error('Invalid form or field ID');
        }

        if (empty($text)) {
            // Empty text = remove tooltip
            Tooltips::remove_tooltip($form_id, $field_id);
        } else {
            Tooltips::set_tooltip($form_id, $field_id, $text);
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Delete tooltip
     */
    public static function ajax_delete_tooltip(): void {
        check_ajax_referer('nme_tooltip_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $form_id = (int) ($_POST['form_id'] ?? 0);
        $field_id = (int) ($_POST['field_id'] ?? 0);

        if ($form_id <= 0 || $field_id <= 0) {
            wp_send_json_error('Invalid form or field ID');
        }

        Tooltips::remove_tooltip($form_id, $field_id);
        wp_send_json_success();
    }
}
