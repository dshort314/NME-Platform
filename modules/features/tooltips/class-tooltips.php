<?php
/**
 * Tooltips
 * 
 * Manages field-level tooltips across Gravity Forms.
 * Tooltips are configured per form/field in the admin.
 */

namespace NME\Features\Tooltips;

use NME\Core\FieldRegistry\FieldRegistry;

defined('ABSPATH') || exit;

class Tooltips {

    /** @var array Form IDs that can have tooltips */
    const ALLOWED_FORMS = [
        FieldRegistry::FORM_MASTER,
        FieldRegistry::FORM_INFORMATION_ABOUT_YOU,
        FieldRegistry::FORM_TIME_OUTSIDE,
        FieldRegistry::FORM_RESIDENCES,
        FieldRegistry::FORM_MARITAL_HISTORY,
        FieldRegistry::FORM_CHILDREN,
        FieldRegistry::FORM_EMPLOYMENT,
        FieldRegistry::FORM_CRIMINAL_HISTORY,
        FieldRegistry::FORM_ADDITIONAL_INFORMATION,
        FieldRegistry::FORM_PRELIMINARY_ELIGIBILITY,
    ];

    /** @var array Cached tooltips */
    private static array $tooltips_cache = [];

    /**
     * Initialize tooltips functionality
     */
    public static function init(): void {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        // Hook into each allowed form
        foreach (self::ALLOWED_FORMS as $form_id) {
            add_filter('gform_pre_render_' . $form_id, [__CLASS__, 'maybe_enqueue_assets']);
            add_filter('gform_field_content_' . $form_id, [__CLASS__, 'add_tooltip_to_field'], 10, 5);
        }
    }

    /**
     * Enqueue tooltip assets when form is rendered
     */
    public static function maybe_enqueue_assets(array $form): array {
        static $enqueued = false;

        if (!$enqueued) {
            add_action('wp_footer', [__CLASS__, 'output_styles']);
            add_action('wp_footer', [__CLASS__, 'output_script']);
            $enqueued = true;
        }

        return $form;
    }

    /**
     * Add tooltip markup to field
     */
    public static function add_tooltip_to_field(string $content, $field, $value, int $lead_id, int $form_id): string {
        $field_id = (int) $field->id;
        $tooltip = self::get_tooltip($form_id, $field_id);

        if (empty($tooltip)) {
            return $content;
        }

        // Add tooltip icon after field label
        $tooltip_html = sprintf(
            '<span class="nme-tooltip" data-tooltip="%s"><span class="nme-tooltip-icon">?</span></span>',
            esc_attr($tooltip)
        );

        // Insert after the label
        $content = preg_replace(
            '/(<label[^>]*>.*?<\/label>)/s',
            '$1' . $tooltip_html,
            $content,
            1
        );

        return $content;
    }

    /**
     * Get tooltip for a specific form/field
     */
    public static function get_tooltip(int $form_id, int $field_id): ?string {
        $tooltips = self::get_tooltips_for_form($form_id);
        return $tooltips[$field_id] ?? null;
    }

    /**
     * Get all tooltips for a form
     */
    public static function get_tooltips_for_form(int $form_id): array {
        if (isset(self::$tooltips_cache[$form_id])) {
            return self::$tooltips_cache[$form_id];
        }

        $settings = get_option('nme_platform_settings', []);
        $all_tooltips = $settings['tooltips']['forms'] ?? [];

        self::$tooltips_cache[$form_id] = $all_tooltips[$form_id] ?? [];

        return self::$tooltips_cache[$form_id];
    }

    /**
     * Set tooltip for a specific form/field
     */
    public static function set_tooltip(int $form_id, int $field_id, string $text): bool {
        $settings = get_option('nme_platform_settings', []);

        if (!isset($settings['tooltips'])) {
            $settings['tooltips'] = ['forms' => []];
        }

        if (!isset($settings['tooltips']['forms'][$form_id])) {
            $settings['tooltips']['forms'][$form_id] = [];
        }

        $settings['tooltips']['forms'][$form_id][$field_id] = $text;

        // Clear cache
        unset(self::$tooltips_cache[$form_id]);

        return update_option('nme_platform_settings', $settings);
    }

    /**
     * Remove tooltip for a specific form/field
     */
    public static function remove_tooltip(int $form_id, int $field_id): bool {
        $settings = get_option('nme_platform_settings', []);

        if (isset($settings['tooltips']['forms'][$form_id][$field_id])) {
            unset($settings['tooltips']['forms'][$form_id][$field_id]);

            // Clear cache
            unset(self::$tooltips_cache[$form_id]);

            return update_option('nme_platform_settings', $settings);
        }

        return true;
    }

    /**
     * Get allowed form IDs
     */
    public static function get_allowed_forms(): array {
        return self::ALLOWED_FORMS;
    }

    /**
     * Output tooltip styles
     */
    public static function output_styles(): void {
        ?>
        <style>
            .nme-tooltip {
                position: relative;
                display: inline-block;
                margin-left: 5px;
                cursor: help;
            }
            .nme-tooltip-icon {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 16px;
                height: 16px;
                background: #0073aa;
                color: #fff;
                border-radius: 50%;
                font-size: 11px;
                font-weight: bold;
                line-height: 1;
            }
            .nme-tooltip-content {
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #333;
                color: #fff;
                padding: 8px 12px;
                border-radius: 4px;
                font-size: 13px;
                line-height: 1.4;
                white-space: normal;
                width: max-content;
                max-width: 280px;
                z-index: 9999;
                margin-bottom: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            }
            .nme-tooltip-content::after {
                content: '';
                position: absolute;
                top: 100%;
                left: 50%;
                transform: translateX(-50%);
                border: 6px solid transparent;
                border-top-color: #333;
            }
        </style>
        <?php
    }

    /**
     * Output tooltip script
     */
    public static function output_script(): void {
        ?>
        <script>
        (function() {
            document.addEventListener('mouseenter', function(e) {
                if (!e.target.classList.contains('nme-tooltip')) return;
                
                var tooltip = e.target;
                var text = tooltip.getAttribute('data-tooltip');
                
                if (!text) return;
                
                // Remove any existing tooltip content
                var existing = tooltip.querySelector('.nme-tooltip-content');
                if (existing) existing.remove();
                
                // Create tooltip content
                var content = document.createElement('div');
                content.className = 'nme-tooltip-content';
                content.textContent = text;
                tooltip.appendChild(content);
            }, true);
            
            document.addEventListener('mouseleave', function(e) {
                if (!e.target.classList.contains('nme-tooltip')) return;
                
                var content = e.target.querySelector('.nme-tooltip-content');
                if (content) content.remove();
            }, true);
        })();
        </script>
        <?php
    }

    /**
     * Clear all cached tooltips
     */
    public static function clear_cache(): void {
        self::$tooltips_cache = [];
    }
}