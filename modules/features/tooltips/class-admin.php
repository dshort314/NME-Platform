<?php
/**
 * Tooltips Admin - NME Platform
 * 
 * Matches original NME-Settings admin UI exactly.
 * Uses migrated data in nme_platform_settings['tooltips']
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Tooltips;

if (!defined('ABSPATH')) {
    exit;
}

class Admin {

    /**
     * Option key for tooltips settings (migrated location)
     */
    const OPTION_KEY = 'nme_platform_settings';

    /**
     * Initialize admin hooks
     */
    public static function init() {
        add_action('admin_menu', [__CLASS__, 'add_submenu_page'], 20);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_ajax_nme_tooltips_get_row', [__CLASS__, 'ajax_get_tooltip_row']);
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page() {
        add_submenu_page(
            'nme-platform',
            __('Form Field Tooltips', 'nme-platform'),
            __('Tooltips', 'nme-platform'),
            'manage_options',
            'nme-tooltips',
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Get allowed form IDs
     */
    public static function allowed_forms() {
        return Tooltips::get_allowed_forms();
    }

    /**
     * Get all tooltip settings
     */
    public static function get_all_settings() {
        $settings = get_option(self::OPTION_KEY, []);
        return isset($settings['tooltips']['forms']) ? $settings['tooltips']['forms'] : [];
    }

    /**
     * Get settings for a specific form
     */
    public static function get_form_settings($form_id) {
        $all = self::get_all_settings();
        return isset($all[$form_id]) && is_array($all[$form_id]) ? $all[$form_id] : [];
    }

    /**
     * Save settings for a specific form
     */
    public static function save_form_settings($form_id, $tooltips) {
        $settings = get_option(self::OPTION_KEY, []);
        
        if (!isset($settings['tooltips'])) {
            $settings['tooltips'] = ['forms' => []];
        }
        if (!isset($settings['tooltips']['forms'])) {
            $settings['tooltips']['forms'] = [];
        }

        // Clean and validate tooltips
        $clean_tooltips = [];
        if (is_array($tooltips)) {
            foreach ($tooltips as $field_id => $data) {
                $field_id = (int) $field_id;
                if ($field_id <= 0) continue;

                $message = isset($data['message']) ? $data['message'] : '';
                $message = is_string($message) ? wp_unslash($message) : '';
                $message = wp_kses_post($message);

                if ($message !== '') {
                    $clean_tooltips[$field_id] = $message;
                }
            }
        }

        if (empty($clean_tooltips)) {
            unset($settings['tooltips']['forms'][$form_id]);
        } else {
            $settings['tooltips']['forms'][$form_id] = $clean_tooltips;
        }

        update_option(self::OPTION_KEY, $settings);
    }

    /**
     * Get form title from Gravity Forms
     */
    public static function get_form_title($form_id) {
        if (!class_exists('GFAPI')) return "Form $form_id";

        $form = \GFAPI::get_form($form_id);
        if (is_array($form) && isset($form['title'])) {
            return $form['title'];
        }

        return "Form $form_id";
    }

    /**
     * Get form fields for validation
     */
    public static function get_form_fields($form_id) {
        if (!class_exists('GFAPI')) return [];

        $form = \GFAPI::get_form($form_id);
        if (!is_array($form) || empty($form['fields'])) return [];

        $fields = [];
        foreach ($form['fields'] as $field) {
            $field_id = is_object($field) ? (int) $field->id : (int) ($field['id'] ?? 0);
            $label = '';

            if (is_object($field)) {
                $label = !empty($field->adminLabel) ? $field->adminLabel : $field->label;
            } else {
                $label = !empty($field['adminLabel']) ? $field['adminLabel'] : ($field['label'] ?? '');
            }

            if ($field_id > 0) {
                $fields[$field_id] = $label ?: "Field $field_id";
            }
        }

        return $fields;
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_assets($hook) {
        if (strpos($hook, 'nme-tooltips') === false) {
            return;
        }

        // Get module URL - construct it manually to avoid dependency issues
        $module_url = plugin_dir_url(__FILE__);

        wp_enqueue_style(
            'nme-tooltips-admin',
            $module_url . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'nme-tooltips-admin',
            $module_url . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        $selected_form = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;
        
        wp_localize_script('nme-tooltips-admin', 'nmeTooltips', [
            'nonce'       => wp_create_nonce('nme_tooltips_ajax'),
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'formFields'  => $selected_form ? self::get_form_fields($selected_form) : [],
            'selectedForm' => $selected_form,
        ]);
    }

    /**
     * Render the admin page - MATCHES ORIGINAL NME-SETTINGS UI
     */
    public static function render_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'nme-platform'));
        }

        $allowed_forms = self::allowed_forms();
        $selected_form = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0;

        // Default to first allowed form if none selected
        if (!$selected_form && !empty($allowed_forms)) {
            $selected_form = $allowed_forms[0];
        }

        // Validate selected form is in allowed list
        if ($selected_form && !in_array($selected_form, $allowed_forms, true)) {
            $selected_form = 0;
        }

        // Handle form submission
        if (isset($_POST['nme_tooltips_nonce']) && wp_verify_nonce($_POST['nme_tooltips_nonce'], 'nme_tooltips_save')) {
            $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
            $tooltips = isset($_POST['tooltips']) ? (array) $_POST['tooltips'] : [];

            if ($form_id && in_array($form_id, $allowed_forms, true)) {
                self::save_form_settings($form_id, $tooltips);
                echo '<div class="notice notice-success is-dismissible"><p>Tooltips saved successfully.</p></div>';
                $selected_form = $form_id;
            }
        }

        $current_tooltips = $selected_form ? self::get_form_settings($selected_form) : [];
        $form_fields = $selected_form ? self::get_form_fields($selected_form) : [];
        ?>
        <div class="wrap nme-tooltips-wrap">
            <h1><?php esc_html_e('Form Field Tooltips', 'nme-platform'); ?></h1>
            <p><?php esc_html_e('Add helpful guidance tooltips to form fields. Select a form below to manage its tooltips.', 'nme-platform'); ?></p>

            <?php if (empty($allowed_forms)): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('No forms are configured for tooltips.', 'nme-platform'); ?></p>
                </div>
            <?php else: ?>

                <!-- Form Selection Dropdown -->
                <div class="nme-form-selector">
                    <h2><?php esc_html_e('Select Form', 'nme-platform'); ?></h2>
                    <select id="form-selector" onchange="window.location.href='<?php echo admin_url('admin.php?page=nme-tooltips&form_id='); ?>' + this.value">
                        <option value=""><?php esc_html_e('Choose a form...', 'nme-platform'); ?></option>
                        <?php foreach ($allowed_forms as $form_id): ?>
                            <option value="<?php echo esc_attr($form_id); ?>" <?php selected($form_id, $selected_form); ?>>
                                <?php echo esc_html(self::get_form_title($form_id)); ?> (ID: <?php echo esc_html($form_id); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($selected_form): ?>
                    <form method="post" action="" class="nme-tooltips-form">
                        <?php wp_nonce_field('nme_tooltips_save', 'nme_tooltips_nonce'); ?>
                        <input type="hidden" name="form_id" value="<?php echo esc_attr($selected_form); ?>">

                        <h2><?php echo esc_html(self::get_form_title($selected_form)); ?> - Tooltips</h2>

                        <div class="nme-tooltips-container">
                            <div class="nme-tooltips-header">
                                <h3>Current Tooltips</h3>
                                <button type="button" class="button button-secondary" id="add-tooltip-btn">
                                    <span class="dashicons dashicons-plus-alt"></span> Add Tooltip
                                </button>
                            </div>

                            <div id="tooltips-list">
                                <?php if (!empty($current_tooltips)): ?>
                                    <?php foreach ($current_tooltips as $field_id => $message): ?>
                                        <div class="tooltip-row" data-field-id="<?php echo esc_attr($field_id); ?>">
                                            <div class="tooltip-controls">
                                                <label>Field ID:</label>
                                                <input type="number"
                                                       name="tooltips[<?php echo esc_attr($field_id); ?>][field_id]"
                                                       value="<?php echo esc_attr($field_id); ?>"
                                                       min="1"
                                                       class="small-text tooltip-field-id"
                                                       readonly>
                                                <?php if (isset($form_fields[$field_id])): ?>
                                                    <span class="field-info field-exists"><?php echo esc_html($form_fields[$field_id]); ?></span>
                                                <?php else: ?>
                                                    <span class="field-info field-missing">⚠️ Field not found in form</span>
                                                <?php endif; ?>
                                                <button type="button" class="button-link-delete remove-tooltip-btn">
                                                    <span class="dashicons dashicons-trash"></span> Remove
                                                </button>
                                            </div>
                                            <div class="tooltip-message">
                                                <label>Tooltip Message:</label>
                                                <?php
                                                wp_editor(
                                                    $message,
                                                    'tooltip_editor_' . $field_id,
                                                    [
                                                        'textarea_name' => 'tooltips[' . $field_id . '][message]',
                                                        'media_buttons' => false,
                                                        'teeny'         => true,
                                                        'editor_height' => 100,
                                                        'textarea_rows' => 3,
                                                    ]
                                                );
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="no-tooltips">
                                        <p><?php esc_html_e('No tooltips configured for this form yet.', 'nme-platform'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p class="submit">
                            <button type="submit" class="button button-primary"><?php esc_html_e('Save Tooltips', 'nme-platform'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * AJAX handler to get a new tooltip row with WYSIWYG editor
     */
    public static function ajax_get_tooltip_row() {
        if (!wp_verify_nonce($_POST['nonce'], 'nme_tooltips_ajax')) {
            wp_die('Security check failed');
        }

        $form_id = isset($_POST['form_id']) ? (int) $_POST['form_id'] : 0;
        $field_id = isset($_POST['field_id']) ? (int) $_POST['field_id'] : 0;
        $allowed_forms = self::allowed_forms();

        if (!$form_id || !in_array($form_id, $allowed_forms, true) || !$field_id) {
            wp_send_json_error('Invalid form or field ID');
        }

        $form_fields = self::get_form_fields($form_id);
        $field_label = isset($form_fields[$field_id]) ? $form_fields[$field_id] : '';

        ob_start();
        ?>
        <div class="tooltip-row" data-field-id="<?php echo esc_attr($field_id); ?>">
            <div class="tooltip-controls">
                <label>Field ID:</label>
                <input type="number"
                       name="tooltips[<?php echo esc_attr($field_id); ?>][field_id]"
                       value="<?php echo esc_attr($field_id); ?>"
                       min="1"
                       class="small-text tooltip-field-id">
                <?php if ($field_label): ?>
                    <span class="field-info field-exists"><?php echo esc_html($field_label); ?></span>
                <?php else: ?>
                    <span class="field-info field-missing">⚠️ Field not found in form</span>
                <?php endif; ?>
                <button type="button" class="button-link-delete remove-tooltip-btn">
                    <span class="dashicons dashicons-trash"></span> Remove
                </button>
            </div>
            <div class="tooltip-message">
                <label>Tooltip Message:</label>
                <?php
                wp_editor(
                    '',
                    'tooltip_editor_' . $field_id,
                    [
                        'textarea_name' => 'tooltips[' . $field_id . '][message]',
                        'media_buttons' => false,
                        'teeny'         => true,
                        'editor_height' => 100,
                        'textarea_rows' => 3,
                    ]
                );
                ?>
            </div>
        </div>
        <?php

        $html = ob_get_clean();
        wp_send_json_success(['html' => $html, 'field_label' => $field_label]);
    }
}
