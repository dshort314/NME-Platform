<?php
/**
 * Tooltips Runtime - NME Platform
 *
 * Injects "Guidance" buttons into form field labels with click-to-show tooltips.
 * Matches original NME-Settings frontend behavior exactly.
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Tooltips;

if (!defined('ABSPATH')) {
    exit;
}

class Runtime {

    /**
     * Option key for tooltips settings
     */
    const OPTION_KEY = 'nme_platform_settings';

    /**
     * Initialize runtime hooks
     */
    public static function init() {
        add_action('wp_head', [__CLASS__, 'add_tooltip_styles']);
        add_action('wp_footer', [__CLASS__, 'add_tooltip_scripts']);

        // Hook into Gravity Forms for each allowed form
        $allowed = Tooltips::get_allowed_forms();
        foreach ($allowed as $form_id) {
            add_action("gform_enqueue_scripts_{$form_id}", function() use ($form_id) {
                self::enqueue_form_scripts($form_id);
            });
        }
    }

    /**
     * Get all tooltip settings
     */
    public static function get_all_settings() {
        $settings = get_option(self::OPTION_KEY, []);
        return isset($settings['tooltips']['forms']) ? $settings['tooltips']['forms'] : [];
    }

    /**
     * Check if we have any tooltips configured
     */
    public static function has_tooltips() {
        $allowed = Tooltips::get_allowed_forms();
        $all_settings = self::get_all_settings();

        foreach ($allowed as $form_id) {
            if (!empty($all_settings[$form_id])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Enqueue scripts for specific form - injects tooltip data inline
     */
    public static function enqueue_form_scripts($form_id) {
        $all_settings = self::get_all_settings();
        $tooltips = isset($all_settings[$form_id]) ? $all_settings[$form_id] : [];

        if (empty($tooltips)) return;

        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipData = <?php echo wp_json_encode($tooltips); ?>;
            var formId = <?php echo (int) $form_id; ?>;

            Object.keys(tooltipData).forEach(function(fieldId) {
                var tooltipMessage = tooltipData[fieldId];

                // Try to find Gravity Form field
                var fieldSelector = '#field_' + formId + '_' + fieldId;
                var fieldElement = document.querySelector(fieldSelector);
                var fieldLegend = fieldElement ? fieldElement.querySelector('.gfield_label') : null;

                if (fieldLegend && !fieldLegend.querySelector('.gf-tooltip-trigger')) {
                    // Create tooltip trigger with "Guidance" button styling
                    var tooltipTrigger = document.createElement('span');
                    tooltipTrigger.className = 'gf-tooltip-trigger';
                    tooltipTrigger.setAttribute('data-tooltip', tooltipMessage);
                    tooltipTrigger.innerHTML = 'Guidance';
                    tooltipTrigger.style.marginLeft = '10px';

                    // Style the guidance button (inline styles for immediate effect)
                    tooltipTrigger.style.backgroundColor = '#28a745';
                    tooltipTrigger.style.color = '#ffffff';
                    tooltipTrigger.style.padding = '4px 8px';
                    tooltipTrigger.style.borderRadius = '4px';
                    tooltipTrigger.style.fontSize = '12px';
                    tooltipTrigger.style.fontWeight = 'bold';
                    tooltipTrigger.style.cursor = 'pointer';
                    tooltipTrigger.style.border = 'none';
                    tooltipTrigger.style.display = 'inline-block';
                    tooltipTrigger.style.textDecoration = 'none';
                    tooltipTrigger.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                    tooltipTrigger.style.transition = 'all 0.2s ease';

                    // Add hover effects
                    tooltipTrigger.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = '#218838';
                        this.style.transform = 'translateY(-1px)';
                        this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.15)';
                    });

                    tooltipTrigger.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = '#28a745';
                        this.style.transform = 'translateY(0)';
                        this.style.boxShadow = '0 2px 4px rgba(0,0,0,0.1)';
                    });

                    // Create the actual tooltip container
                    var tooltipContainer = document.createElement('div');
                    tooltipContainer.className = 'gf-tooltip-container';
                    tooltipContainer.innerHTML = tooltipMessage;

                    // Make the label container position relative
                    fieldLegend.style.position = 'relative';
                    fieldLegend.appendChild(tooltipTrigger);
                    fieldLegend.appendChild(tooltipContainer);
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Add CSS styles
     */
    public static function add_tooltip_styles() {
        if (!self::has_tooltips()) return;

        $css_url = plugin_dir_url(__FILE__) . 'assets/css/frontend.css';
        wp_enqueue_style('nme-tooltips-frontend', $css_url, [], '1.0.0');
    }

    /**
     * Add JavaScript for enhanced functionality
     */
    public static function add_tooltip_scripts() {
        if (!self::has_tooltips()) return;

        $js_url = plugin_dir_url(__FILE__) . 'assets/js/frontend.js';
        wp_enqueue_script('nme-tooltips-frontend', $js_url, ['jquery'], '1.0.0', true);
    }
}
