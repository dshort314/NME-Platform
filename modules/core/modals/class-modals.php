<?php
/**
 * Modals Class
 * 
 * Enqueues the global modal CSS and JavaScript.
 * These are loaded on all frontend pages to ensure modals
 * are available whenever needed.
 */

namespace NME\Core\Modals;

defined('ABSPATH') || exit;

class Modals {

    /**
     * Initialize hooks
     */
    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /**
     * Enqueue modal CSS and JavaScript on frontend
     */
    public static function enqueue_assets(): void {
        $base_url = NME_PLATFORM_URL . 'modules/core/modals/assets/';
        $version = NME_PLATFORM_VERSION;

        // Enqueue CSS
        wp_enqueue_style(
            'nme-modals',
            $base_url . 'css/nme-modals.css',
            [],
            $version,
            'all'
        );

        // Enqueue JavaScript - depends on jQuery and nme-debug
        wp_enqueue_script(
            'nme-modals',
            $base_url . 'js/nme-modals.js',
            ['jquery', 'nme-debug'],
            $version,
            true
        );
    }
}
