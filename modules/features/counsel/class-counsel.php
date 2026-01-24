<?php
/**
 * Counsel
 * 
 * Manages bouncer modals that appear when users answer certain
 * eligibility questions on Form 39 (Additional Information).
 */

namespace NME\Features\Counsel;

use NME\Core\FieldRegistry\FieldRegistry;

defined('ABSPATH') || exit;

class Counsel {

    /** @var int Form ID this module handles */
    const FORM_ID = 39;

    /** @var int Field ID for the pass/bounce flag */
    const FLAG_FIELD_ID = 924;

    /** @var string Value that triggers bounce */
    const FLAG_VALUE_BOUNCE = 'Bounce';

    /** @var array Field IDs where YES triggers modal */
    const YES_FIELDS = [
        774, 777, 776, 775, 779, 778, 780, 786, 785, 784, 783, 782, 781,
        799, 798, 797, 796, 795, 794, 793, 792, 791, 790, 789, 788, 787,
        805, 803, 802, 801, 800, 817, 937, 815, 814, 813, 812, 811, 818,
        861, 858, 862, 867, 868, 875, 874, 873, 878, 877, 889
    ];

    /** @var array Field IDs where NO triggers modal */
    const NO_FIELDS = [
        859, 883, 882, 890, 888, 887, 886, 885, 940
    ];

    /**
     * Initialize counsel functionality
     */
    public static function init(): void {
        // Only load on frontend
        if (is_admin()) {
            return;
        }

        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'maybe_enqueue_assets']);
        add_filter('gform_confirmation_' . self::FORM_ID, [__CLASS__, 'process_confirmation'], 10, 4);
    }

    /**
     * Enqueue assets when Form 39 is rendered
     */
    public static function maybe_enqueue_assets(array $form): array {
        add_action('wp_footer', [__CLASS__, 'output_modal_html']);
        add_action('wp_footer', [__CLASS__, 'output_inline_script']);
        
        return $form;
    }

    /**
     * Process form confirmation - redirect if bounced
     */
    public static function process_confirmation($confirmation, array $form, array $entry, bool $ajax): mixed {
        $flag_value = rgar($entry, self::FLAG_FIELD_ID);

        if ($flag_value === self::FLAG_VALUE_BOUNCE) {
            // Redirect to counsel page
            $redirect_url = self::get_bounce_redirect_url();
            $confirmation = ['redirect' => $redirect_url];
        }

        return $confirmation;
    }

    /**
     * Get the redirect URL for bounced submissions
     */
    private static function get_bounce_redirect_url(): string {
        // Default to page 704, can be filtered
        $page_id = apply_filters('nme_counsel_bounce_page_id', 704);
        return get_permalink($page_id) ?: home_url();
    }

    /**
     * Get counsel configuration for JavaScript
     */
    public static function get_js_config(): array {
        return [
            'formId'         => self::FORM_ID,
            'flagFieldId'    => self::FLAG_FIELD_ID,
            'flagValueBounce'=> self::FLAG_VALUE_BOUNCE,
            'yesFields'      => self::YES_FIELDS,
            'noFields'       => self::NO_FIELDS,
            'messages'       => self::get_messages(),
        ];
    }

    /**
     * Get counsel messages from database or defaults
     */
    public static function get_messages(): array {
        $settings = get_option('nme_platform_settings', []);
        $messages = $settings['counsel']['messages'] ?? [];

        // Default messages if not configured
        if (empty($messages)) {
            $messages = [
                'default_yes' => 'This answer may affect your eligibility. Please consult with an immigration attorney.',
                'default_no'  => 'This answer may affect your eligibility. Please consult with an immigration attorney.',
                'confirm'     => 'I understand',
                'title'       => 'Important Notice',
            ];
        }

        return $messages;
    }

    /**
     * Output modal HTML in footer
     */
    public static function output_modal_html(): void {
        $messages = self::get_messages();
        ?>
        <div id="nme-counsel-modal" class="nme-modal" style="display:none;">
            <div class="nme-modal-overlay"></div>
            <div class="nme-modal-content">
                <h3 class="nme-modal-title"><?php echo esc_html($messages['title'] ?? 'Important Notice'); ?></h3>
                <div class="nme-modal-body">
                    <p id="nme-counsel-message"></p>
                </div>
                <div class="nme-modal-footer">
                    <button type="button" id="nme-counsel-confirm" class="nme-modal-button">
                        <?php echo esc_html($messages['confirm'] ?? 'I understand'); ?>
                    </button>
                </div>
            </div>
        </div>
        <style>
            .nme-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 99999;
            }
            .nme-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
            }
            .nme-modal-content {
                position: relative;
                max-width: 500px;
                margin: 100px auto;
                background: #fff;
                border-radius: 8px;
                padding: 24px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            .nme-modal-title {
                margin: 0 0 16px;
                font-size: 1.25em;
            }
            .nme-modal-body {
                margin-bottom: 20px;
            }
            .nme-modal-button {
                background: #0073aa;
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 4px;
                cursor: pointer;
            }
            .nme-modal-button:hover {
                background: #005a87;
            }
        </style>
        <?php
    }

    /**
     * Output inline JavaScript for modal functionality
     */
    public static function output_inline_script(): void {
        $config = self::get_js_config();
        ?>
        <script>
        (function() {
            var config = <?php echo json_encode($config); ?>;
            var modal = document.getElementById('nme-counsel-modal');
            var messageEl = document.getElementById('nme-counsel-message');
            var confirmBtn = document.getElementById('nme-counsel-confirm');
            var currentField = null;

            function showModal(message) {
                messageEl.textContent = message;
                modal.style.display = 'block';
            }

            function hideModal() {
                modal.style.display = 'none';
                currentField = null;
            }

            function getFieldMessage(fieldId, triggerValue) {
                var key = 'field_' + fieldId + '_' + triggerValue.toLowerCase();
                return config.messages[key] || 
                       config.messages['default_' + triggerValue.toLowerCase()] ||
                       'This answer may affect your eligibility.';
            }

            function checkField(input) {
                var fieldId = parseInt(input.name.replace(/[^\d]/g, ''));
                var value = input.value;

                if (config.yesFields.indexOf(fieldId) !== -1 && value === 'Yes') {
                    showModal(getFieldMessage(fieldId, 'yes'));
                    updateFlag();
                } else if (config.noFields.indexOf(fieldId) !== -1 && value === 'No') {
                    showModal(getFieldMessage(fieldId, 'no'));
                    updateFlag();
                }
            }

            function updateFlag() {
                var flagInput = document.querySelector('input[name="input_' + config.flagFieldId + '"]');
                if (flagInput) {
                    flagInput.value = config.flagValueBounce;
                }
            }

            // Bind to radio changes
            document.addEventListener('change', function(e) {
                if (e.target.type === 'radio' && e.target.closest('.gform_wrapper')) {
                    checkField(e.target);
                }
            });

            // Confirm button
            if (confirmBtn) {
                confirmBtn.addEventListener('click', hideModal);
            }

            // Close on overlay click
            var overlay = modal ? modal.querySelector('.nme-modal-overlay') : null;
            if (overlay) {
                overlay.addEventListener('click', hideModal);
            }
        })();
        </script>
        <?php
    }

    /**
     * Get all field IDs this module monitors
     */
    public static function get_all_fields(): array {
        return array_merge(self::YES_FIELDS, self::NO_FIELDS);
    }
}