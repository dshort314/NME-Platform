<?php
/**
 * Preliminary Eligibility Handler
 * 
 * Processes Form 78 submissions:
 * - Evaluates answers against trigger conditions
 * - Calculates dynamic dates for placeholders
 * - Routes to appropriate result pages
 * - Stores result messages in transients for destination pages
 */

namespace NME\Features\PreliminaryEligibility;

use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Process form confirmation (determines result page)
        add_filter('gform_confirmation_' . Config::FORM_ID, [__CLASS__, 'process_results'], 10, 4);

        // AJAX handler for destination pages to retrieve messages
        add_action('wp_ajax_nme_get_prelim_message', [__CLASS__, 'ajax_get_message']);
        add_action('wp_ajax_nopriv_nme_get_prelim_message', [__CLASS__, 'ajax_get_message']);

        if (Plugin::is_debug_enabled('preliminary-eligibility')) {
            error_log('NME Platform [preliminary-eligibility]: Handler initialized');
        }
    }

    /**
     * Process form submission and determine result
     */
    public static function process_results($confirmation, array $form, array $entry, bool $ajax) {
        $settings = Admin::get_settings();
        $c1_default = !empty($settings['c1_default']) ? wpautop($settings['c1_default']) : '';
        $c2_default = !empty($settings['c2_default']) ? wpautop($settings['c2_default']) : '';
        $overrides = $settings['overrides'] ?? [];

        // Calculate dynamic dates
        $dates = self::calculate_dynamic_dates($entry);

        // Get field labels and order from form
        $field_labels = [];
        $field_order = [];
        $order_index = 0;

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

                // Replace date placeholders in labels
                $label = self::replace_placeholders($label, $dates);

                $field_labels[$fid] = $label;
                $field_order[$fid] = $order_index++;
            }
        }

        // Message resolver
        $get_message = function(int $fid, bool $is_yes) use ($overrides, $c1_default, $c2_default, $dates) {
            // Check overrides first
            if (isset($overrides[$fid])) {
                $override = $overrides[$fid];
                $is_complex = in_array($fid, Config::COMPLEX_FIELDS, true);

                if ($is_complex) {
                    if ($is_yes && !empty($override['yes'])) {
                        return self::replace_placeholders(wpautop($override['yes']), $dates);
                    }
                    if (!$is_yes && !empty($override['no'])) {
                        return self::replace_placeholders(wpautop($override['no']), $dates);
                    }
                } else {
                    if (!empty($override['both'])) {
                        return self::replace_placeholders(wpautop($override['both']), $dates);
                    }
                }
            }

            // Use severity-based default
            $severity = Config::get_field_severity($fid);
            $msg = ($severity === 'C2') ? ($c2_default ?: $c1_default) : ($c1_default ?: $c2_default);
            return self::replace_placeholders($msg, $dates);
        };

        $negative_results = [];
        $success_messages = [];

        // Check YES fields
        foreach (Config::YES_FIELDS as $fid) {
            $value = isset($entry[$fid]) ? strtolower(trim($entry[$fid])) : '';
            if ($value === 'yes') {
                $result = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? 'Field ' . $fid,
                    'message'  => $get_message($fid, true),
                    'order'    => $field_order[$fid] ?? 9999,
                ];

                if (Config::is_warning_only($fid)) {
                    $success_messages[] = $result;
                } else {
                    $negative_results[] = $result;
                }
            }
        }

        // Check NO fields
        foreach (Config::NO_FIELDS as $fid) {
            $value = isset($entry[$fid]) ? strtolower(trim($entry[$fid])) : '';
            if ($value === 'no') {
                $negative_results[] = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? 'Field ' . $fid,
                    'message'  => $get_message($fid, false),
                    'order'    => $field_order[$fid] ?? 9999,
                ];
            }
        }

        // Check COMPLEX fields
        foreach (Config::COMPLEX_FIELDS as $fid) {
            $value = isset($entry[$fid]) ? strtolower(trim($entry[$fid])) : '';
            if ($value === 'yes' || $value === 'no') {
                $is_yes = ($value === 'yes');
                $result = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? 'Field ' . $fid,
                    'message'  => $get_message($fid, $is_yes),
                    'order'    => $field_order[$fid] ?? 9999,
                ];

                if (Config::is_yes_only_non_disqualifying($fid)) {
                    if ($is_yes) {
                        $success_messages[] = $result;
                    } else {
                        $negative_results[] = $result;
                    }
                } else {
                    $negative_results[] = $result;
                }
            }
        }

        // Special case: Field 30 NO is disqualifying
        if (isset($entry[30]) && strtolower(trim($entry[30])) === 'no') {
            $negative_results[] = [
                'field_id' => 30,
                'question' => $field_labels[30] ?? 'Field 30',
                'message'  => $get_message(30, false),
                'order'    => $field_order[30] ?? 9999,
            ];
        }

        // Check CODE fields (numeric threshold)
        foreach (Config::CODE_FIELDS as $fid) {
            $value = isset($entry[$fid]) ? trim($entry[$fid]) : '';
            $num = floatval(str_replace(',', '', $value));
            if ($num > Config::CODE_THRESHOLD) {
                $negative_results[] = [
                    'field_id' => $fid,
                    'question' => $field_labels[$fid] ?? 'Field ' . $fid,
                    'message'  => $get_message($fid, true),
                    'order'    => $field_order[$fid] ?? 9999,
                ];
            }
        }

        // Sort by form order
        usort($negative_results, fn($a, $b) => $a['order'] - $b['order']);

        // No negative results = success
        if (empty($negative_results)) {
            $redirect_url = home_url(Config::PAGE_QUALIFY);

            if (!empty($success_messages)) {
                usort($success_messages, fn($a, $b) => $a['order'] - $b['order']);
                $transient_key = 'prelim_success_' . $entry['id'];
                set_transient($transient_key, $success_messages, 3600);
                $redirect_url = add_query_arg('success_key', $transient_key, $redirect_url);
            }

            return ['redirect' => $redirect_url];
        }

        // Determine severity (C2 if any C2 fields triggered)
        $has_c2 = false;
        foreach ($negative_results as $result) {
            if (Config::get_field_severity($result['field_id']) === 'C2') {
                $has_c2 = true;
                break;
            }
        }

        // Build HTML for result page
        $html = '';
        foreach ($negative_results as $result) {
            $html .= '<div class="prelim-negative-result" style="margin: 20px 0; padding: 15px; border-left: 4px solid #d63638; background: #f9f9f9;">';
            $html .= '<h3 style="margin-top: 0; color: #d63638;">Question: ' . esc_html($result['question']) . '</h3>';
            $html .= '<div class="prelim-message">' . ($result['message'] ?: '<p>This response may require further review.</p>') . '</div>';
            $html .= '</div>';
        }

        // Store in transient
        $transient_key = 'prelim_negative_' . $entry['id'];
        set_transient($transient_key, $html, 3600);

        // Route to appropriate page
        $redirect_url = $has_c2 ? home_url(Config::PAGE_LAWYER) : home_url(Config::PAGE_DEFERRAL);
        $redirect_url = add_query_arg('message_key', $transient_key, $redirect_url);

        return ['redirect' => $redirect_url];
    }

    /**
     * Calculate dynamic dates based on entry data
     */
    public static function calculate_dynamic_dates(array $entry): array {
        $dates = [];

        // LPR date field
        $lpr_date_raw = isset($entry[65]) ? trim($entry[65]) : '';

        // Today field or current date
        $today_raw = isset($entry[66]) ? trim($entry[66]) : '';
        $today = !empty($today_raw) && strtotime($today_raw) !== false
            ? new \DateTime('@' . strtotime($today_raw))
            : new \DateTime();

        $format = fn($dt) => $dt->format('m/d/Y');

        $dates['[Today]'] = $format($today);
        $dates['[Current Date]'] = $format($today);

        // USC Calculated Date = Today + 1 year + 1 day (for marriage track)
        $usc_calc = clone $today;
        $usc_calc->modify('+1 year +1 day');
        $dates['[USC_CALCULATED_DATE]'] = $format($usc_calc);
        $dates['[USC Calculated Date]'] = $format($usc_calc);

        // LPR-based calculations
        if (!empty($lpr_date_raw) && strtotime($lpr_date_raw) !== false) {
            $lpr = new \DateTime('@' . strtotime($lpr_date_raw));

            $dates['[LPR Date]'] = $format($lpr);

            // 5 years
            $five_year = clone $lpr;
            $five_year->modify('+5 years');
            $dates['[USC Date]'] = $format($five_year);
            $dates['[Eligibility Date]'] = $format($five_year);
            $dates['[5 Year Date]'] = $format($five_year);

            // LPR + 4 years - 90 days (earliest filing for 5-year track)
            $lpr4 = clone $lpr;
            $lpr4->modify('+4 years -90 days');
            $dates['[LPR4]'] = $format($lpr4);
            $dates['[Earliest Filing Date]'] = $format($lpr4);
            $dates['[Filing Date]'] = $format($lpr4);

            // LPR + 2 years - 90 days (earliest for 3-year marriage track)
            $lpr2 = clone $lpr;
            $lpr2->modify('+2 years -90 days');
            $dates['[LPR2]'] = $format($lpr2);
            $dates['[Marriage Track Filing Date]'] = $format($lpr2);

            // 3 years
            $three_year = clone $lpr;
            $three_year->modify('+3 years');
            $dates['[3 Year Date]'] = $format($three_year);
        }

        return apply_filters('nme_prelim_calculated_dates', $dates, $entry);
    }

    /**
     * Replace date placeholders in text
     */
    public static function replace_placeholders(string $text, array $dates): string {
        foreach ($dates as $placeholder => $value) {
            $text = str_replace($placeholder, $value, $text);
        }
        return $text;
    }

    /**
     * AJAX handler for destination pages
     */
    public static function ajax_get_message(): void {
        $message_key = isset($_POST['message_key']) ? sanitize_text_field($_POST['message_key']) : '';

        if (empty($message_key)) {
            wp_send_json_error('No message key provided');
        }

        $content = get_transient($message_key);

        if ($content === false) {
            wp_send_json_error('Message not found or expired');
        }

        delete_transient($message_key);
        wp_send_json_success($content);
    }
}
