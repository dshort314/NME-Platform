<?php
/**
 * Counsel Runtime - NME Platform
 *
 * Frontend runtime for Application Counsel modal system.
 * Ported from NME-Settings, matches original behavior exactly.
 *
 * @package NME_Platform
 */

namespace NME_Platform\Modules\Features\Counsel;

if (!defined('ABSPATH')) {
    exit;
}

class Runtime {

    /**
     * Option key
     */
    const OPTION_KEY = 'nme_settings_bouncer';

    /**
     * Initialize runtime hooks
     */
    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts'], 20);
        
        // Server-side confirmation handler
        add_filter('gform_confirmation_' . Counsel::get_form_id(), [__CLASS__, 'process_counsel_results'], 10, 4);
        
        // Page button injection for application page
        add_action('wp_footer', [__CLASS__, 'add_application_page_button']);
        
        // AJAX handler
        add_action('wp_ajax_nme_process_application', [__CLASS__, 'ajax_process_application']);
    }

    /**
     * Get runtime config for JavaScript
     */
    public static function get_config() {
        $form_id = Counsel::get_form_id();
        $all = get_option(self::OPTION_KEY, []);
        $cfg = isset($all[$form_id]) && is_array($all[$form_id]) ? $all[$form_id] : [];
        
        $cfg['form_id'] = $form_id;
        $cfg['yes_fields'] = Counsel::get_yes_fields();
        $cfg['no_fields'] = Counsel::get_no_fields();

        // Process messages with wpautop
        if (!empty($cfg['default_message'])) {
            $cfg['default_message'] = wpautop($cfg['default_message']);
        }
        if (!empty($cfg['default_no_message'])) {
            $cfg['default_no_message'] = wpautop($cfg['default_no_message']);
        }
        if (!empty($cfg['confirm_message'])) {
            $cfg['confirm_message'] = wpautop($cfg['confirm_message']);
        }
        if (!empty($cfg['arrest_bounce_message'])) {
            $cfg['arrest_bounce_message'] = wpautop($cfg['arrest_bounce_message']);
        }

        // Process overrides
        if (!empty($cfg['overrides']) && is_array($cfg['overrides'])) {
            foreach ($cfg['overrides'] as $fid => $html) {
                $cfg['overrides'][$fid] = wpautop($html);
            }
        }

        $cfg['flag_field_id'] = defined('NME_COUNSEL_FLAG_FIELD_ID') ? (int) NME_COUNSEL_FLAG_FIELD_ID : 924;
        $cfg['flag_value_bounce'] = defined('NME_COUNSEL_FLAG_VALUE_BOUNCE') ? NME_COUNSEL_FLAG_VALUE_BOUNCE : 'Bounce';
        $cfg['arrest_field_id'] = 940;

        return $cfg;
    }

    /**
     * Enqueue runtime scripts
     */
    public static function enqueue_scripts() {
        $module_url = plugin_dir_url(__FILE__);
        $handle = 'nme-counsel-runtime';

        wp_register_script(
            $handle,
            $module_url . 'assets/js/runtime.js',
            ['jquery'],
            '1.6.0',
            true
        );

        $cfg = self::get_config();
        wp_add_inline_script($handle, 'window.NME_Counsel_Settings = ' . wp_json_encode($cfg) . ';', 'before');
        wp_enqueue_script($handle);
    }

    /**
     * Process counsel results on form confirmation
     */
    public static function process_counsel_results($confirmation, $form, $entry, $ajax) {
        $yes_ids = Counsel::get_yes_fields();
        $no_ids = Counsel::get_no_fields();

        $form_id = Counsel::get_form_id();
        $all = get_option(self::OPTION_KEY, []);
        $settings = isset($all[$form_id]) && is_array($all[$form_id]) ? $all[$form_id] : [];

        $default_yes = isset($settings['default_message']) ? wpautop($settings['default_message']) : '';
        $default_no  = isset($settings['default_no_message']) ? wpautop($settings['default_no_message']) : '';
        $overrides   = isset($settings['overrides']) ? $settings['overrides'] : [];

        // Build field map for order and labels
        $field_labels = [];
        $field_order  = [];
        $order_index  = 0;

        if (is_array($form) && !empty($form['fields'])) {
            foreach ($form['fields'] as $f) {
                $fid = is_object($f) ? (int)$f->id : (int)($f['id'] ?? 0);
                if ($fid <= 0) continue;

                $label = '';
                if (is_object($f)) {
                    if (!empty($f->adminLabel)) $label = $f->adminLabel;
                    if ($label === '' && isset($f->label)) $label = $f->label;
                } else {
                    if (!empty($f['adminLabel'])) $label = $f['adminLabel'];
                    if ($label === '' && isset($f['label'])) $label = $f['label'];
                }
                if ($label === '') $label = "Field $fid";

                $field_labels[$fid] = $label;
                $field_order[$fid]  = $order_index++;
            }
        }

        // Message resolver
        $get_message = function($fid, $is_no) use ($overrides, $default_yes, $default_no) {
            if (isset($overrides[$fid]) && !empty($overrides[$fid])) {
                return wpautop($overrides[$fid]);
            }
            if ($is_no) {
                return $default_no ?: '<p>This response requires review.</p>';
            }
            return $default_yes ?: '<p>This response requires review.</p>';
        };

        $negative_results = [];

        // Check YES fields
        foreach ($yes_ids as $fid) {
            $value = isset($entry[$fid]) ? strtolower(trim($entry[$fid])) : '';
            if ($value === 'yes') {
                $negative_results[] = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? "Field $fid",
                    'message'  => $get_message($fid, false),
                    'order'    => $field_order[$fid] ?? 9999,
                ];
            }
        }

        // Check NO fields
        foreach ($no_ids as $fid) {
            $value = isset($entry[$fid]) ? strtolower(trim($entry[$fid])) : '';
            if ($value === 'no') {
                $negative_results[] = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? "Field $fid",
                    'message'  => $get_message($fid, true),
                    'order'    => $field_order[$fid] ?? 9999,
                ];
            }
        }

        // Sort by field order
        usort($negative_results, function($a, $b) {
            return $a['order'] <=> $b['order'];
        });

        // No issues → redirect to success
        if (empty($negative_results)) {
            return ['redirect' => home_url('/application-complete/')];
        }

        // Build confirmation HTML
        $html  = '<div class="gform_confirmation_wrapper"><div class="gform_confirmation_message">';
        $html .= '<h2>Application Review Required</h2>';
        $html .= '<p>Based on your responses, the following items require attention:</p>';

        foreach ($negative_results as $item) {
            $html .= '<div class="counsel-negative-result" style="margin:20px 0; padding:15px; border-left:4px solid #d63638; background:#f9f9f9;">';
            $html .= '<h3 style="margin-top:0; color:#d63638;">Question: ' . esc_html($item['question']) . '</h3>';
            $html .= '<div class="counsel-message">' . $item['message'] . '</div>';
            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Add application page button
     */
    public static function add_application_page_button() {
        if (!is_page(704)) return;

        $user_id = get_current_user_id();
        if (!$user_id) return;

        $parent_entry_id = get_user_meta($user_id, 'parent_entry_id', true);
        if (!$parent_entry_id) return;

        // Get icon URL
        if (defined('NME_APP_PLUGIN_URL')) {
            $icon_url = NME_APP_PLUGIN_URL . 'public/images/icons/';
        } else {
            $icon_url = content_url() . '/plugins/NME-Application-main/public/images/icons/';
        }
        ?>
        <script>
        jQuery(function($){
            var iconUrl = '<?php echo esc_js($icon_url); ?>';
            var buttonsHtml = `
                <div class="nme-nav-grid light-theme justified-row">
                    <a href="#" id="nme-process-application" class="nme-nav-button nme-nav-button-light">
                        <i class="icon check-icon" style="background-image: url('${iconUrl}icon-evaluate-light.svg');"></i>
                        <span>Evaluate Application</span>
                    </a>
                    <a href="/application/print/" id="print-application-button" class="nme-nav-button nme-nav-button-light">
                        <i class="icon print-icon" style="background-image: url('${iconUrl}icon-printer-light.svg');"></i>
                        <span>Print Application</span>
                    </a>
                </div>
            `;
            $('#application-navigation').append(buttonsHtml);

            if (!$('#nme-application-results').length) {
                $('.application-container').append('<div id="nme-application-results" style="margin-top:30px;width:100%;"></div>');
            }

            $(document).on('click','#nme-process-application',function(e){
                e.preventDefault();
                var $btn=$(this), $results=$('#nme-application-results');
                var $span = $btn.find('span');
                $span.text('Processing...');
                $btn.css('opacity','0.5').css('pointer-events','none');
                $results.html('');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type:'POST',
                    data:{
                        action:'nme_process_application',
                        entry_id:<?php echo absint($parent_entry_id); ?>,
                        nonce:'<?php echo wp_create_nonce('nme_process_app_' . $parent_entry_id); ?>'
                    },
                    success:function(response){
                        $span.text('Evaluate Application');
                        $btn.css('opacity','1').css('pointer-events','auto');

                        if(response.success){
                            if(response.data.redirect){
                                window.location.href = response.data.redirect;
                            } else {
                                $results.html(response.data.html);
                                $('html,body').animate({
                                    scrollTop:$results.offset().top - 100
                                },500);
                            }
                        } else {
                            $results.html('<div class="nme-error"><p><strong>Error:</strong> '+response.data+'</p></div>');
                        }
                    },
                    error:function(){
                        $span.text('Evaluate Application');
                        $btn.css('opacity','1').css('pointer-events','auto');
                        $results.html('<div class="nme-error"><p>An error occurred while processing your application.</p></div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for processing application
     */
    public static function ajax_process_application() {
        $entry_id = isset($_POST['entry_id']) ? absint($_POST['entry_id']) : 0;

        if (!$entry_id || !wp_verify_nonce($_POST['nonce'], 'nme_process_app_' . $entry_id)) {
            wp_send_json_error('Invalid request');
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error('You must be logged in');
        }

        $user_parent_entry = get_user_meta($user_id, 'parent_entry_id', true);
        if ($user_parent_entry != $entry_id) {
            wp_send_json_error('Unauthorized access');
        }

        $entry = \GFAPI::get_entry($entry_id);
        if (is_wp_error($entry)) {
            wp_send_json_error('Entry not found');
        }

        $form = \GFAPI::get_form($entry['form_id']);
        if (!$form || $form['id'] != 75) {
            wp_send_json_error('Invalid form');
        }

        $result = self::process_counsel_results('', $form, $entry, false);

        if (is_array($result) && isset($result['redirect'])) {
            wp_send_json_success(['redirect' => $result['redirect']]);
        }

        wp_send_json_success(['html' => $result]);
    }
}
