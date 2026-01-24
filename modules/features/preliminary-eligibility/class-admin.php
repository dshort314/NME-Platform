<?php
/**
 * Preliminary Eligibility Admin
 * 
 * Admin settings page for configuring per-question message overrides.
 * Integrates with NME Settings hub if available.
 */

namespace NME\Features\PreliminaryEligibility;

use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Admin {

    /**
     * Initialize admin hooks
     */
    public static function init(): void {
        // Register settings page via NME Settings hub if available
        add_action('plugins_loaded', [__CLASS__, 'maybe_register_with_hub'], 20);

        // Standalone admin page fallback
        add_action('admin_menu', [__CLASS__, 'maybe_add_standalone_page'], 99);
    }

    /**
     * Register with NME Settings hub if it exists
     */
    public static function maybe_register_with_hub(): void {
        if (!class_exists('\\NME_Settings\\Modules')) {
            return;
        }

        \NME_Settings\Modules::register([
            'id'         => 'prelim',
            'menu_title' => 'Preliminary Eligibility',
            'page_title' => 'Preliminary Eligibility',
            'cap'        => 'manage_options',
            'menu_slug'  => 'nme-preliminary-eligibility',
            'render_cb'  => [__CLASS__, 'render_page'],
        ]);
    }

    /**
     * Add standalone admin page if NME Settings hub not available
     */
    public static function maybe_add_standalone_page(): void {
        if (class_exists('\\NME_Settings\\Modules')) {
            return; // Hub handles it
        }

        add_submenu_page(
            'nme-platform',
            'Preliminary Eligibility',
            'Preliminary Eligibility',
            'manage_options',
            'nme-preliminary-eligibility',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get all settings
     */
    public static function get_settings(): array {
        $all = get_option(Config::OPTION_KEY, []);
        $form_id = Config::FORM_ID;

        $defaults = [
            'c1_default' => '',
            'c2_default' => '',
            'overrides'  => [],
        ];

        if (isset($all[$form_id]) && is_array($all[$form_id])) {
            return array_replace_recursive($defaults, $all[$form_id]);
        }

        return $defaults;
    }

    /**
     * Save settings
     */
    public static function save_settings(array $data): void {
        $all = get_option(Config::OPTION_KEY, []);
        $form_id = Config::FORM_ID;

        $c1 = isset($data['c1_default']) ? wp_unslash($data['c1_default']) : '';
        $c2 = isset($data['c2_default']) ? wp_unslash($data['c2_default']) : '';

        $clean = [
            'c1_default' => wp_kses_post($c1),
            'c2_default' => wp_kses_post($c2),
            'overrides'  => [],
        ];

        if (isset($data['overrides']) && is_array($data['overrides'])) {
            foreach ($data['overrides'] as $fid => $row) {
                $fid = (int) $fid;
                if ($fid <= 0) continue;

                $enabled = isset($row['enabled']) && (string) $row['enabled'] === '1';
                $is_complex = in_array($fid, Config::COMPLEX_FIELDS, true);

                if ($is_complex) {
                    $yes = isset($row['yes']) ? wp_unslash($row['yes']) : '';
                    $no = isset($row['no']) ? wp_unslash($row['no']) : '';
                    if ($enabled && ($yes !== '' || $no !== '')) {
                        $clean['overrides'][$fid] = [
                            'yes' => wp_kses_post($yes),
                            'no'  => wp_kses_post($no),
                        ];
                    }
                } else {
                    $msg = isset($row['both']) ? wp_unslash($row['both']) : '';
                    if ($enabled && $msg !== '') {
                        $clean['overrides'][$fid] = ['both' => wp_kses_post($msg)];
                    }
                }
            }
        }

        $all[$form_id] = $clean;
        update_option(Config::OPTION_KEY, $all);
    }

    /**
     * Get trigger description for a field
     */
    private static function get_trigger_description(int $fid): string {
        if (in_array($fid, Config::COMPLEX_FIELDS, true)) {
            return 'Complex';
        }
        if (in_array($fid, Config::YES_FIELDS, true)) {
            return 'Yes';
        }
        if (in_array($fid, Config::NO_FIELDS, true)) {
            return 'No';
        }
        if (in_array($fid, Config::CODE_FIELDS, true)) {
            return 'Code';
        }
        return '?';
    }

    /**
     * Render admin page
     */
    public static function render_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nme-platform'));
        }

        // Handle save
        if (isset($_POST['nme_prelim_nonce']) && wp_verify_nonce($_POST['nme_prelim_nonce'], 'nme_prelim_save')) {
            $data = isset($_POST['nme_prelim']) ? (array) $_POST['nme_prelim'] : [];
            self::save_settings($data);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $settings = self::get_settings();
        $c1 = $settings['c1_default'];
        $c2 = $settings['c2_default'];
        $overrides = is_array($settings['overrides']) ? $settings['overrides'] : [];

        // Get form fields for labels
        $form = class_exists('GFAPI') ? \GFAPI::get_form(Config::FORM_ID) : [];
        $field_map = [];
        $ordered_ids = [];

        if (is_array($form) && !empty($form['fields'])) {
            $all_trigger_fields = Config::get_all_trigger_fields();
            $want = array_flip($all_trigger_fields);

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

                if (isset($want[$fid])) {
                    $ordered_ids[] = $fid;
                }
            }
        }

        ?>
        <div class="wrap">
            <h1>Preliminary Eligibility</h1>
            <p>
                Configure default messages and per-question overrides.<br>
                <strong>C1</strong> = Deferral page, <strong>C2</strong> = See a Lawyer page.
            </p>

            <form method="post" action="">
                <?php wp_nonce_field('nme_prelim_save', 'nme_prelim_nonce'); ?>

                <h2>Default Messages</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                    <div>
                        <h3>C1 (Deferral)</h3>
                        <?php
                        wp_editor($c1, 'nme_prelim_c1', [
                            'textarea_name' => 'nme_prelim[c1_default]',
                            'media_buttons' => false,
                            'teeny'         => true,
                            'editor_height' => 160,
                        ]);
                        ?>
                    </div>
                    <div>
                        <h3>C2 (See Lawyer)</h3>
                        <?php
                        wp_editor($c2, 'nme_prelim_c2', [
                            'textarea_name' => 'nme_prelim[c2_default]',
                            'media_buttons' => false,
                            'teeny'         => true,
                            'editor_height' => 160,
                        ]);
                        ?>
                    </div>
                </div>

                <h2 style="margin-top:24px;">Per-Question Overrides</h2>

                <?php if (empty($ordered_ids)): ?>
                    <p>No fields configured or form not found.</p>
                <?php else: ?>
                    <table class="widefat striped fixed">
                        <thead>
                            <tr>
                                <th style="width:14%;">Custom Message</th>
                                <th>Question</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ordered_ids as $fid):
                                if (!isset($field_map[$fid])) continue;

                                $label = $field_map[$fid];
                                $is_complex = in_array($fid, Config::COMPLEX_FIELDS, true);
                                $sev = Config::FIELD_SEVERITY[$fid] ?? '';
                                $row = $overrides[$fid] ?? [];
                                $has = $is_complex ? (!empty($row['yes']) || !empty($row['no'])) : !empty($row['both']);
                                $trigger_desc = self::get_trigger_description($fid);
                                $is_warning = Config::is_warning_only($fid);
                            ?>
                            <tr>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                               class="nme-prelim-toggle"
                                               name="nme_prelim[overrides][<?php echo esc_attr($fid); ?>][enabled]"
                                               value="1" <?php checked($has, true); ?>>
                                        Use custom message
                                    </label>
                                </td>
                                <td>
                                    <strong>#<?php echo esc_html($fid); ?></strong>
                                    <span class="description" style="margin-left:6px;">
                                        Trigger: <code><?php echo esc_html($trigger_desc); ?></code>
                                        <?php if ($sev && !$is_warning): ?>
                                            • Default: <code><?php echo esc_html($sev); ?></code>
                                        <?php endif; ?>
                                    </span>
                                    — <?php echo esc_html($label); ?>

                                    <div class="nme-prelim-editor-area" <?php echo $has ? '' : 'style="display:none"'; ?>>
                                        <?php if ($is_complex): ?>
                                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-top:10px;">
                                                <div>
                                                    <h4 style="margin:8px 0 4px;">Message for YES</h4>
                                                    <?php
                                                    wp_editor(
                                                        $row['yes'] ?? '',
                                                        'nme_prelim_editor_' . $fid . '_yes',
                                                        [
                                                            'textarea_name' => 'nme_prelim[overrides][' . $fid . '][yes]',
                                                            'media_buttons' => false,
                                                            'teeny'         => true,
                                                            'editor_height' => 140,
                                                        ]
                                                    );
                                                    ?>
                                                </div>
                                                <div>
                                                    <h4 style="margin:8px 0 4px;">Message for NO</h4>
                                                    <?php
                                                    wp_editor(
                                                        $row['no'] ?? '',
                                                        'nme_prelim_editor_' . $fid . '_no',
                                                        [
                                                            'textarea_name' => 'nme_prelim[overrides][' . $fid . '][no]',
                                                            'media_buttons' => false,
                                                            'teeny'         => true,
                                                            'editor_height' => 140,
                                                        ]
                                                    );
                                                    ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div style="margin-top:10px;">
                                                <?php
                                                wp_editor(
                                                    $row['both'] ?? '',
                                                    'nme_prelim_editor_' . $fid . '_both',
                                                    [
                                                        'textarea_name' => 'nme_prelim[overrides][' . $fid . '][both]',
                                                        'media_buttons' => false,
                                                        'teeny'         => true,
                                                        'editor_height' => 140,
                                                    ]
                                                );
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
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

        <script>
        (function($){
            $(document).on('change', '.nme-prelim-toggle', function(){
                $(this).closest('tr').find('.nme-prelim-editor-area').toggle(this.checked);
            });
        })(jQuery);
        </script>
        <style>
            .nme-prelim-editor-area .wp-editor-wrap { max-width: 800px; }
        </style>
        <?php
    }
}
