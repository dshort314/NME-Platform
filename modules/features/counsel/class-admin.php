<?php
/**
 * Counsel Admin
 * 
 * Admin settings page for configuring counsel modal messages.
 * Registers under NME Platform menu.
 */

namespace NME\Features\Counsel;

defined('ABSPATH') || exit;

class Admin {

    /** @var string Admin page slug */
    const PAGE_SLUG = 'nme-counsel';

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
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page(): void {
        add_submenu_page(
            'nme-platform',
            'Application Counsel',
            'Application Counsel',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get all settings
     */
    public static function get_settings(): array {
        $settings = get_option(self::OPTION_KEY, []);
        
        $defaults = [
            'messages' => [
                'title'       => 'Important Notice',
                'confirm'     => 'I understand',
                'default_yes' => 'This answer may affect your eligibility. Please consult with an immigration attorney.',
                'default_no'  => 'This answer may affect your eligibility. Please consult with an immigration attorney.',
            ],
            'field_overrides' => [],
            'bounce_page_id'  => 704,
        ];

        if (isset($settings['counsel']) && is_array($settings['counsel'])) {
            return array_replace_recursive($defaults, $settings['counsel']);
        }

        return $defaults;
    }

    /**
     * Save settings
     */
    public static function save_settings(array $data): void {
        $settings = get_option(self::OPTION_KEY, []);

        $clean = [
            'messages' => [
                'title'       => sanitize_text_field($data['messages']['title'] ?? 'Important Notice'),
                'confirm'     => sanitize_text_field($data['messages']['confirm'] ?? 'I understand'),
                'default_yes' => wp_kses_post($data['messages']['default_yes'] ?? ''),
                'default_no'  => wp_kses_post($data['messages']['default_no'] ?? ''),
            ],
            'field_overrides' => [],
            'bounce_page_id'  => (int) ($data['bounce_page_id'] ?? 704),
        ];

        // Process field-specific overrides
        if (isset($data['field_overrides']) && is_array($data['field_overrides'])) {
            foreach ($data['field_overrides'] as $field_id => $message) {
                $field_id = (int) $field_id;
                $message = trim($message);
                if ($field_id > 0 && !empty($message)) {
                    $clean['field_overrides'][$field_id] = wp_kses_post($message);
                }
            }
        }

        $settings['counsel'] = $clean;
        update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Render admin page
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nme-platform'));
        }

        // Handle save
        if (isset($_POST['nme_counsel_nonce']) && wp_verify_nonce($_POST['nme_counsel_nonce'], 'nme_counsel_save')) {
            $data = isset($_POST['nme_counsel']) ? $_POST['nme_counsel'] : [];
            self::save_settings($data);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $settings = self::get_settings();
        $messages = $settings['messages'];
        $field_overrides = $settings['field_overrides'];
        $bounce_page_id = $settings['bounce_page_id'];

        // Get form for field labels
        $form = class_exists('GFAPI') ? \GFAPI::get_form(Counsel::FORM_ID) : [];
        $field_map = [];

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

                $field_map[$fid] = $label;
            }
        }

        // Get all monitored fields
        $all_fields = Counsel::get_all_fields();

        ?>
        <div class="wrap">
            <h1>Application Counsel</h1>
            <p>Configure modal messages shown when users answer eligibility questions on Form 39 (Additional Information).</p>

            <form method="post" action="">
                <?php wp_nonce_field('nme_counsel_save', 'nme_counsel_nonce'); ?>

                <h2>General Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="modal_title">Modal Title</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_counsel[messages][title]" 
                                   id="modal_title"
                                   value="<?php echo esc_attr($messages['title']); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="confirm_button">Confirm Button Text</label></th>
                        <td>
                            <input type="text" 
                                   name="nme_counsel[messages][confirm]" 
                                   id="confirm_button"
                                   value="<?php echo esc_attr($messages['confirm']); ?>"
                                   class="regular-text">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bounce_page">Bounce Redirect Page</label></th>
                        <td>
                            <?php
                            wp_dropdown_pages([
                                'name'              => 'nme_counsel[bounce_page_id]',
                                'id'                => 'bounce_page',
                                'selected'          => $bounce_page_id,
                                'show_option_none'  => '— Select Page —',
                                'option_none_value' => 0,
                            ]);
                            ?>
                            <p class="description">Page users are redirected to after submitting with a "bounce" flag.</p>
                        </td>
                    </tr>
                </table>

                <h2>Default Messages</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="default_yes">Default YES Message</label></th>
                        <td>
                            <?php
                            wp_editor($messages['default_yes'], 'default_yes_editor', [
                                'textarea_name' => 'nme_counsel[messages][default_yes]',
                                'media_buttons' => false,
                                'teeny'         => true,
                                'editor_height' => 120,
                            ]);
                            ?>
                            <p class="description">Shown when user answers YES to a trigger field (unless overridden).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="default_no">Default NO Message</label></th>
                        <td>
                            <?php
                            wp_editor($messages['default_no'], 'default_no_editor', [
                                'textarea_name' => 'nme_counsel[messages][default_no]',
                                'media_buttons' => false,
                                'teeny'         => true,
                                'editor_height' => 120,
                            ]);
                            ?>
                            <p class="description">Shown when user answers NO to a trigger field (unless overridden).</p>
                        </td>
                    </tr>
                </table>

                <h2>Field-Specific Overrides</h2>
                <p>Leave blank to use the default message for that field type (YES or NO).</p>

                <?php if (empty($all_fields)): ?>
                    <p>No trigger fields configured.</p>
                <?php else: ?>
                    <table class="widefat striped" style="max-width: 900px;">
                        <thead>
                            <tr>
                                <th style="width: 80px;">Field ID</th>
                                <th style="width: 80px;">Trigger</th>
                                <th>Question</th>
                                <th>Custom Message</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_fields as $fid): 
                                $is_yes = in_array($fid, Counsel::YES_FIELDS, true);
                                $trigger = $is_yes ? 'YES' : 'NO';
                                $label = $field_map[$fid] ?? 'Field ' . $fid;
                                $override = $field_overrides[$fid] ?? '';
                            ?>
                            <tr>
                                <td><strong><?php echo esc_html($fid); ?></strong></td>
                                <td><code><?php echo esc_html($trigger); ?></code></td>
                                <td><?php echo esc_html($label); ?></td>
                                <td>
                                    <textarea name="nme_counsel[field_overrides][<?php echo esc_attr($fid); ?>]"
                                              rows="2" 
                                              style="width: 100%;"><?php echo esc_textarea($override); ?></textarea>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary">Save Changes</button>
                </p>
            </form>
        </div>
        <?php
    }
}
