<?php
/**
 * Counsel Admin - NME Platform
 *
 * Admin interface for Application Counsel settings.
 * Ported from NME-Settings, matches original UI exactly.
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Counsel;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    /**
     * Option key (same as original)
     */
    const OPTION_KEY = 'nme_settings_bouncer';

    /**
     * Initialize admin hooks
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_submenu_page'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page() {
        add_submenu_page(
            'nme-platform',
            __('Application Counsel', 'nme-platform'),
            __('Application Counsel', 'nme-platform'),
            'manage_options',
            'nme-counsel',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get settings for the form
     */
    public static function get_settings() {
        $all = get_option(self::OPTION_KEY, []);
        $form_id = Counsel::get_form_id();

        $defaults = [
            'default_message'       => '',
            'default_no_message'    => '',
            'confirm_message'       => '',
            'arrest_bounce_message' => '',
            'overrides'             => [],
        ];

        if (isset($all[$form_id]) && is_array($all[$form_id])) {
            return array_merge($defaults, $all[$form_id]);
        }
        return $defaults;
    }

    /**
     * Save settings
     */
    public static function save_settings($data) {
        $all = get_option(self::OPTION_KEY, []);
        $form_id = Counsel::get_form_id();

        $default_yes_raw = isset($data['default_message']) ? $data['default_message'] : '';
        $default_no_raw  = isset($data['default_no_message']) ? $data['default_no_message'] : '';
        $confirm_raw     = isset($data['confirm_message']) ? $data['confirm_message'] : '';
        $arrest_raw      = isset($data['arrest_bounce_message']) ? $data['arrest_bounce_message'] : '';

        if (is_string($default_yes_raw)) $default_yes_raw = wp_unslash($default_yes_raw);
        if (is_string($default_no_raw))  $default_no_raw  = wp_unslash($default_no_raw);
        if (is_string($confirm_raw))     $confirm_raw     = wp_unslash($confirm_raw);
        if (is_string($arrest_raw))      $arrest_raw      = wp_unslash($arrest_raw);

        $clean = [
            'default_message'       => wp_kses_post($default_yes_raw),
            'default_no_message'    => wp_kses_post($default_no_raw),
            'confirm_message'       => wp_kses_post($confirm_raw),
            'arrest_bounce_message' => wp_kses_post($arrest_raw),
            'overrides'             => [],
        ];

        if (isset($data['overrides']) && is_array($data['overrides'])) {
            foreach ($data['overrides'] as $fid => $pair) {
                $enabled = isset($pair['enabled']) && (string)$pair['enabled'] === '1';
                $msg     = isset($pair['message']) ? $pair['message'] : '';
                $fid     = (int) $fid;

                if (is_string($msg)) $msg = wp_unslash($msg);
                if ($enabled && $fid > 0 && $msg !== '') {
                    $clean['overrides'][$fid] = wp_kses_post($msg);
                }
            }
        }

        $all[$form_id] = $clean;
        update_option(self::OPTION_KEY, $all);
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'nme-counsel') === false) {
            return;
        }

        $module_url = plugin_dir_url(__FILE__);

        wp_enqueue_script(
            'nme-counsel-admin',
            $module_url . 'assets/js/admin.js',
            ['jquery'],
            '1.5.0',
            true
        );

        wp_enqueue_style(
            'nme-counsel-admin',
            $module_url . 'assets/css/admin.css',
            [],
            '1.5.0'
        );
    }

    /**
     * Render admin page - matches original NME-Settings UI
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nme-platform'));
        }

        $form_id  = Counsel::get_form_id();
        $settings = self::get_settings();
        $yes_fields = Counsel::get_yes_fields();
        $no_fields  = Counsel::get_no_fields();

        // Build field map from Gravity Forms
        $field_map = [];
        if (class_exists('\\GFAPI') && $form_id) {
            $form = \GFAPI::get_form($form_id);
            if (is_array($form) && !empty($form['fields'])) {
                foreach ($form['fields'] as $f) {
                    $field_map[(int)$f->id] = [
                        'label' => (string)$f->label,
                        'type'  => (string)$f->type,
                    ];
                }
            }
        }

        // Handle form submission
        if (isset($_POST['nme_counsel_nonce']) && wp_verify_nonce($_POST['nme_counsel_nonce'], 'nme_counsel_save')) {
            $data = isset($_POST['nme_counsel']) ? (array) $_POST['nme_counsel'] : [];
            self::save_settings($data);
            $settings = self::get_settings();
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $default_yes = $settings['default_message'];
        $default_no  = $settings['default_no_message'];
        $confirm_msg = isset($settings['confirm_message']) ? $settings['confirm_message'] : '';
        $arrest_msg  = isset($settings['arrest_bounce_message']) ? $settings['arrest_bounce_message'] : '';
        $overrides   = is_array($settings['overrides']) ? $settings['overrides'] : [];

        ?>
        <div class="wrap nme-counsel-wrap">
            <h1><?php esc_html_e('Application Counsel', 'nme-platform'); ?></h1>
            <p><?php esc_html_e('Configure the default and per-question counseling messages.', 'nme-platform'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('nme_counsel_save', 'nme_counsel_nonce'); ?>

                <!-- Default YES -->
                <h2><?php esc_html_e('Default "Yes" Message', 'nme-platform'); ?></h2>
                <?php
                wp_editor(
                    $default_yes,
                    'nme_counsel_default_yes',
                    [
                        'textarea_name' => 'nme_counsel[default_message]',
                        'media_buttons' => false,
                        'teeny'         => true,
                        'editor_height' => 180,
                    ]
                );
                ?>

                <!-- Default NO -->
                <h2 style="margin-top:24px;"><?php esc_html_e('Default "No" Message', 'nme-platform'); ?></h2>
                <?php
                wp_editor(
                    $default_no,
                    'nme_counsel_default_no',
                    [
                        'textarea_name' => 'nme_counsel[default_no_message]',
                        'media_buttons' => false,
                        'teeny'         => true,
                        'editor_height' => 180,
                    ]
                );
                ?>

                <!-- Confirmation Message -->
                <h2 style="margin-top:24px;"><?php esc_html_e('Confirmation Message', 'nme-platform'); ?></h2>
                <p class="description"><?php esc_html_e('This message appears after the user clicks "Answer is Correct"', 'nme-platform'); ?></p>
                <?php
                wp_editor(
                    $confirm_msg,
                    'nme_counsel_confirm',
                    [
                        'textarea_name' => 'nme_counsel[confirm_message]',
                        'media_buttons' => false,
                        'teeny'         => true,
                        'editor_height' => 180,
                    ]
                );
                ?>

                <!-- Arrest & Criminal Bounce Message -->
                <h2 style="margin-top:24px;"><?php esc_html_e('Arrest & Criminal Bounce', 'nme-platform'); ?></h2>
                <p class="description"><?php esc_html_e('This message appears when field 940 contains "I have been convicted of a crime" and user clicks "Answer is Correct"', 'nme-platform'); ?></p>
                <?php
                wp_editor(
                    $arrest_msg,
                    'nme_counsel_arrest',
                    [
                        'textarea_name' => 'nme_counsel[arrest_bounce_message]',
                        'media_buttons' => false,
                        'teeny'         => true,
                        'editor_height' => 180,
                    ]
                );
                ?>

                <h2 style="margin-top:24px;"><?php esc_html_e('Per-Question Overrides', 'nme-platform'); ?></h2>

                <?php if (empty($yes_fields) && empty($no_fields)): ?>
                    <p><?php esc_html_e('No configured fields.', 'nme-platform'); ?></p>
                <?php else: ?>

                <table class="widefat striped fixed nme-counsel-table">
                    <thead>
                        <tr>
                            <th style="width:5%;text-align:center;"><?php esc_html_e('Use Custom Message', 'nme-platform'); ?></th>
                            <th><?php esc_html_e('Question', 'nme-platform'); ?></th>
                        </tr>
                    </thead>

                    <tbody>

                    <!-- YES Group -->
                    <tr>
                        <th colspan="2" style="background:#fafafa;padding:8px;font-size:15px;">
                            <?php esc_html_e('Message on "Yes" Answers', 'nme-platform'); ?>
                        </th>
                    </tr>

                    <?php foreach ($yes_fields as $fid): ?>
                        <?php if (!isset($field_map[$fid])) continue; ?>
                        <?php
                            $label    = $field_map[$fid]['label'] ?: ('Field ' . $fid);
                            $existing = isset($overrides[$fid]) ? (string)$overrides[$fid] : '';
                            $checked  = $existing !== '' ? 'checked' : '';
                            $toggleId = 'nme_counsel_override_' . $fid;
                        ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox"
                                       id="<?php echo esc_attr($toggleId); ?>"
                                       class="nme-counsel-toggle"
                                       name="nme_counsel[overrides][<?php echo esc_attr($fid); ?>][enabled]"
                                       value="1" <?php echo $checked; ?>>
                            </td>
                            <td>
                                <strong>#<?php echo esc_html($fid); ?></strong> — <?php echo esc_html($label); ?>
                                <div class="nme-counsel-editor-area" <?php echo $checked ? '' : 'style="display:none"'; ?>>
                                    <?php
                                    wp_editor(
                                        $existing,
                                        'nme_counsel_editor_' . $fid,
                                        [
                                            'textarea_name' => 'nme_counsel[overrides]['.$fid.'][message]',
                                            'media_buttons' => false,
                                            'teeny'         => true,
                                            'editor_height' => 150,
                                        ]
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>


                    <!-- NO Group -->
                    <tr>
                        <th colspan="2" style="background:#fafafa;padding:8px;font-size:15px;">
                            <?php esc_html_e('Message on "No" Answers', 'nme-platform'); ?>
                        </th>
                    </tr>

                    <?php foreach ($no_fields as $fid): ?>
                        <?php if (!isset($field_map[$fid])) continue; ?>
                        <?php
                            $label    = $field_map[$fid]['label'] ?: ('Field ' . $fid);
                            $existing = isset($overrides[$fid]) ? (string)$overrides[$fid] : '';
                            $checked  = $existing !== '' ? 'checked' : '';
                            $toggleId = 'nme_counsel_override_' . $fid;
                        ?>
                        <tr>
                            <td style="text-align:center;">
                                <input type="checkbox"
                                       id="<?php echo esc_attr($toggleId); ?>"
                                       class="nme-counsel-toggle"
                                       name="nme_counsel[overrides][<?php echo esc_attr($fid); ?>][enabled]"
                                       value="1" <?php echo $checked; ?>>
                            </td>
                            <td>
                                <strong>#<?php echo esc_html($fid); ?></strong> — <?php echo esc_html($label); ?>
                                <div class="nme-counsel-editor-area" <?php echo $checked ? '' : 'style="display:none"'; ?>>
                                    <?php
                                    wp_editor(
                                        $existing,
                                        'nme_counsel_editor_' . $fid,
                                        [
                                            'textarea_name' => 'nme_counsel[overrides]['.$fid.'][message]',
                                            'media_buttons' => false,
                                            'teeny'         => true,
                                            'editor_height' => 150,
                                        ]
                                    );
                                    ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    </tbody>
                </table>
                <?php endif; ?>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php esc_html_e('Save Changes', 'nme-platform'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }
}
