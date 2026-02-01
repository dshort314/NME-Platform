<?php
/**
 * Purgatory Handler
 * 
 * Handles the display of eligibility messages for users who are
 * in "Eligibility Assessment" status (more than 1 year from filing).
 * 
 * Provides the [nme_purgatory_message] shortcode for displaying
 * the stored purgatory message.
 */

namespace NME\Core\Purgatory;

use NME\Core\AccessControl\AccessControl;
use NME\Core\Plugin;

defined('ABSPATH') || exit;

class Purgatory {

    /**
     * Initialize purgatory hooks
     */
    public static function init(): void {
        // Register shortcode
        add_shortcode('nme_purgatory_message', [__CLASS__, 'render_purgatory_message']);

        // Enqueue styles for purgatory page
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    /**
     * Render the purgatory message shortcode
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_purgatory_message($atts = []): string {
        // Must be logged in
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your application status.</p>';
        }

        // Get the stored message
        $message = AccessControl::get_purgatory_message();

        if (empty($message)) {
            // No message stored - user may have navigated here directly
            // Check if they're actually locked out
            if (AccessControl::is_locked_out()) {
                // Locked but no message - show generic message
                $unlock_date = AccessControl::get_formatted_unlock_date();
                return self::render_generic_lockout_message($unlock_date);
            }

            // Not locked out - show welcome/continue message
            return self::render_not_locked_message();
        }

        // Render the stored message with styling
        return self::render_message_container($message);
    }

    /**
     * Render the message container with proper styling
     * 
     * @param string $message The message HTML
     * @return string Styled HTML
     */
    private static function render_message_container(string $message): string {
        $unlock_date = AccessControl::get_formatted_unlock_date();

        ob_start();
        ?>
        <div class="nme-purgatory-message">
            <div class="nme-purgatory-content">
                <?php echo wp_kses_post($message); ?>
            </div>
            
            <?php if ($unlock_date): ?>
            <div class="nme-purgatory-unlock-info">
                <p><strong>Your access will be restored on:</strong> <?php echo esc_html($unlock_date); ?></p>
            </div>
            <?php endif; ?>

            <div class="nme-purgatory-actions">
                <p>In the meantime, you may access the <a href="/application/documents/">Documents</a> section to gather documents for your application.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render generic lockout message when no specific message is stored
     * 
     * @param string|null $unlock_date Formatted unlock date
     * @return string HTML
     */
    private static function render_generic_lockout_message(?string $unlock_date): string {
        ob_start();
        ?>
        <div class="nme-purgatory-message">
            <div class="nme-purgatory-content">
                <p>Based on the information you provided, you are not yet eligible to file for Naturalization.</p>
                
                <?php if ($unlock_date): ?>
                <p>Full access to your application will be restored on <strong><?php echo esc_html($unlock_date); ?></strong>, which is 6 months prior to your earliest filing date.</p>
                <?php else: ?>
                <p>Please check back later when you are closer to your filing eligibility date.</p>
                <?php endif; ?>
            </div>

            <div class="nme-purgatory-actions">
                <p>In the meantime, you may access the <a href="/application/documents/">Documents</a> section to gather documents which will be used in support of your application.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render message for users who are not locked out
     * 
     * @return string HTML
     */
    private static function render_not_locked_message(): string {
        ob_start();
        ?>
        <div class="nme-purgatory-message nme-purgatory-welcome">
            <div class="nme-purgatory-content">
                <p>You currently have full access to your naturalization application.</p>
                <p>Please continue to the <a href="/application/dashboard/">Application Dashboard</a> to proceed with your application.</p>
            </div>

            <div class="nme-purgatory-actions">
                <a href="/application/dashboard/" class="nme-button nme-button-primary">Go to Dashboard</a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Enqueue styles for purgatory page
     */
    public static function enqueue_styles(): void {
        // Only on purgatory page
        if (!is_page('purgatory')) {
            return;
        }

        // Inline styles for purgatory message
        $css = '
            .nme-purgatory-message {
                max-width: 800px;
                margin: 0 auto;
                padding: 30px;
                background-color: #f8f9fa;
                border-radius: 8px;
                border-left: 4px solid #002D72;
            }
            
            .nme-purgatory-message.nme-purgatory-welcome {
                border-left-color: #28a745;
            }
            
            .nme-purgatory-content {
                margin-bottom: 20px;
                line-height: 1.7;
                color: #333;
            }
            
            .nme-purgatory-content p {
                margin-bottom: 15px;
            }
            
            .nme-purgatory-content em {
                color: #d63638;
                font-style: italic;
            }
            
            .nme-purgatory-unlock-info {
                padding: 15px 20px;
                background-color: #fff3cd;
                border: 1px solid #ffc107;
                border-radius: 4px;
                margin-bottom: 20px;
            }
            
            .nme-purgatory-unlock-info p {
                margin: 0;
                color: #856404;
            }
            
            .nme-purgatory-actions {
                padding-top: 15px;
                border-top: 1px solid #dee2e6;
            }
            
            .nme-purgatory-actions a {
                color: #002D72;
                font-weight: 600;
            }
            
            .nme-purgatory-actions a:hover {
                text-decoration: underline;
            }
            
            .nme-button {
                display: inline-block;
                padding: 12px 24px;
                border-radius: 6px;
                text-decoration: none;
                font-weight: 600;
                transition: all 0.2s ease;
            }
            
            .nme-button-primary {
                background-color: #002D72;
                color: #ffffff !important;
            }
            
            .nme-button-primary:hover {
                background-color: #003d8f;
                text-decoration: none;
            }
        ';

        wp_add_inline_style('wp-block-library', $css);
    }
}
