<?php
/**
 * Criminal History Handler
 * 
 * Processes Form 74 submissions:
 * - Links entries to parent Form 75 via GPNF
 * - Prepopulates user context fields
 * - Handles link from Additional Information page
 */

namespace NME\Topics\CriminalHistory;

use NME\Core\FieldRegistry\FieldRegistry;
use NME\Core\UserContext\UserContext;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Handler {

    /** @var int Form ID */
    const FORM_ID = 74;

    /** @var int Field ID for parent entry reference */
    const FIELD_PARENT_ENTRY_ID = 1;

    /** @var int Field ID for A-Number */
    const FIELD_ANUMBER = 3;

    /** @var int Master Form field that stores nested criminal history entries */
    const MASTER_NESTED_FIELD = 891;

    /** @var int Page ID for Additional Information (contains link to criminal history) */
    const PAGE_ADDITIONAL_INFO = 710;

    /**
     * Initialize hooks
     */
    public static function init(): void {
        // Prepopulate fields on form render
        add_filter('gform_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_validation_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_pre_submission_filter_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);
        add_filter('gform_admin_pre_render_' . self::FORM_ID, [__CLASS__, 'prepopulate_fields']);

        // Add criminal history link script on Additional Information page
        add_action('wp_footer', [__CLASS__, 'maybe_add_link_script']);

        // AJAX handler for getting user meta (used by link script)
        add_action('wp_ajax_nme_get_criminal_link_params', [__CLASS__, 'ajax_get_link_params']);

        if (Plugin::is_debug_enabled('criminal-history')) {
            error_log('NME Platform [criminal-history]: Handler initialized');
        }
    }

    /**
     * Prepopulate user context fields
     */
    public static function prepopulate_fields(array $form): array {
        if ((int) $form['id'] !== self::FORM_ID) {
            return $form;
        }

        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        foreach ($form['fields'] as &$field) {
            if ((int) $field->id === self::FIELD_ANUMBER && $anumber) {
                $field->defaultValue = $anumber;
            }
            if ((int) $field->id === self::FIELD_PARENT_ENTRY_ID && $parent_entry_id) {
                $field->defaultValue = $parent_entry_id;
            }
        }

        return $form;
    }

    /**
     * Add link script on Additional Information page
     * 
     * This handles the "Add Criminal History" link that appears when
     * certain Yes answers are selected in the Additional Information form.
     */
    public static function maybe_add_link_script(): void {
        if (!is_page(self::PAGE_ADDITIONAL_INFO)) {
            return;
        }

        $anumber = UserContext::get_anumber();
        $parent_entry_id = UserContext::get_parent_entry_id();

        if (!$anumber || !$parent_entry_id) {
            return;
        }

        // Build the criminal history form URL with query params
        $criminal_page_url = home_url('/application/criminal-history/');
        $criminal_url = add_query_arg([
            'anumber' => $anumber,
            'parent_entry_id' => $parent_entry_id,
        ], $criminal_page_url);

        ?>
        <script type="text/javascript">
        (function($) {
            'use strict';
            
            $(document).ready(function() {
                // Find any criminal history links and update them with proper params
                var criminalUrl = <?php echo json_encode($criminal_url); ?>;
                
                $('a[href*="criminal-history"]').each(function() {
                    var $link = $(this);
                    var currentHref = $link.attr('href');
                    
                    // Only update if it doesn't already have query params
                    if (currentHref.indexOf('anumber=') === -1) {
                        $link.attr('href', criminalUrl);
                    }
                });
            });
        })(jQuery);
        </script>
        <?php

        if (Plugin::is_debug_enabled('criminal-history')) {
            error_log('NME Platform [criminal-history]: Link script added on page ' . self::PAGE_ADDITIONAL_INFO);
        }
    }

    /**
     * AJAX handler to get link parameters
     */
    public static function ajax_get_link_params(): void {
        check_ajax_referer('nme_criminal_link', 'nonce');

        wp_send_json_success([
            'anumber' => UserContext::get_anumber(),
            'parent_entry_id' => UserContext::get_parent_entry_id(),
        ]);
    }

    /**
     * Get the Master Form field ID for nested criminal history entries
     */
    public static function get_master_nested_field(): int {
        return self::MASTER_NESTED_FIELD;
    }
}
