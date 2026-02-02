<?php
/**
 * Bug Reports
 * 
 * Custom post type for tracking NME Platform bugs with
 * section/subsection categorization and threaded comments.
 */

namespace NME\Admin\BugReports;

defined('ABSPATH') || exit;

class BugReports {

    /** @var string Post type slug */
    const POST_TYPE = 'nme_bug_report';

    /** @var string Admin page slug */
    const PAGE_SLUG = 'nme-bug-reports';

    /** @var array Main sections with sort order */
    const SECTIONS = [
        'preliminary-eligibility' => ['label' => 'Preliminary Eligibility Assessment', 'order' => 1],
        'information-about-you'   => ['label' => 'Information About You', 'order' => 2],
        'time-outside-us'         => ['label' => 'Time Outside the US', 'order' => 3],
        'residences'              => ['label' => 'Residences', 'order' => 4],
        'marital-history'         => ['label' => 'Marital History', 'order' => 5],
        'children'                => ['label' => 'Children', 'order' => 6],
        'employment-school'       => ['label' => 'Employment & School', 'order' => 7],
        'additional-information'  => ['label' => 'Additional Information', 'order' => 8],
        'documents'               => ['label' => 'Documents', 'order' => 9],
        'general'                 => ['label' => 'General/Plugin-wide', 'order' => 10],
        'other'                   => ['label' => 'Other', 'order' => 99],
    ];

    /** @var array Preliminary Eligibility subsections with sort order */
    const SUBSECTIONS_PRELIMINARY = [
        'initial-questions'    => ['label' => 'Initial Questions', 'order' => 1],
        'travel-residence'     => ['label' => 'Travel & Residence', 'order' => 2],
        'residence'            => ['label' => 'Residence', 'order' => 3],
        'language'             => ['label' => 'Language', 'order' => 4],
        'civics'               => ['label' => 'Civics', 'order' => 5],
        'tax'                  => ['label' => 'Tax', 'order' => 6],
        'child-support'        => ['label' => 'Child Support', 'order' => 7],
        'legal-issues'         => ['label' => 'Legal Issues', 'order' => 8],
        'criminal-history'     => ['label' => 'Criminal History', 'order' => 9],
        'additional-questions' => ['label' => 'Additional Questions', 'order' => 10],
        'other'                => ['label' => 'Other', 'order' => 99],
    ];

    /** @var array Additional Information subsections with sort order */
    const SUBSECTIONS_ADDITIONAL = [
        'biographical'                 => ['label' => '1. Biographical', 'order' => 1],
        'civics-taxes'                 => ['label' => '2. Civics & Taxes', 'order' => 2],
        'subversion-extremism'         => ['label' => '3. Subversion & Extremism', 'order' => 3],
        'terror-acts'                  => ['label' => '4. Terror Acts', 'order' => 4],
        'human-rights'                 => ['label' => '5. Human Rights', 'order' => 5],
        'armed-groups'                 => ['label' => '6. Armed Groups', 'order' => 6],
        'detention-work'               => ['label' => '7. Detention Work', 'order' => 7],
        'weapons-use-threats'          => ['label' => '8. Weapons Use/Threats', 'order' => 8],
        'weapons-child-soldiers'       => ['label' => '9. Weapons & Child Soldiers', 'order' => 9],
        'criminal-history'             => ['label' => '10. Criminal History', 'order' => 10],
        'violations-misrepresentation' => ['label' => '11. Violations & Misrepresentation', 'order' => 11],
        'removal-selective-service'    => ['label' => '12. Removal & Selective Service', 'order' => 12],
        'us-military'                  => ['label' => '13. U.S. Military', 'order' => 13],
        'nobility'                     => ['label' => '14. Nobility', 'order' => 14],
        'oath-duties'                  => ['label' => '15. Oath & Duties', 'order' => 15],
        'other'                        => ['label' => 'Other', 'order' => 99],
    ];

    /** @var array Priority levels */
    const PRIORITIES = [
        'low'      => 'Low',
        'medium'   => 'Medium',
        'high'     => 'High',
        'critical' => 'Critical',
    ];

    /** @var array Status options */
    const STATUSES = [
        'open'        => 'Open',
        'in-progress' => 'In Progress',
		'review'	  => 'Review',
		'revise'	  => 'Revise',
        'resolved'    => 'Resolved',
        'closed'      => 'Closed',
		'informational' => 'Informational',
    ];

    /**
     * Get section label
     */
    public static function get_section_label(string $key): string {
        return self::SECTIONS[$key]['label'] ?? $key;
    }

    /**
     * Get subsection label
     */
    public static function get_subsection_label(string $section, string $key): string {
        if ($section === 'preliminary-eligibility') {
            return self::SUBSECTIONS_PRELIMINARY[$key]['label'] ?? $key;
        } elseif ($section === 'additional-information') {
            return self::SUBSECTIONS_ADDITIONAL[$key]['label'] ?? $key;
        }
        return $key;
    }

    /**
     * Get section order
     */
    public static function get_section_order(string $key): int {
        return self::SECTIONS[$key]['order'] ?? 99;
    }

    /**
     * Get subsection order
     */
    public static function get_subsection_order(string $section, string $key): int {
        if ($section === 'preliminary-eligibility') {
            return self::SUBSECTIONS_PRELIMINARY[$key]['order'] ?? 99;
        } elseif ($section === 'additional-information') {
            return self::SUBSECTIONS_ADDITIONAL[$key]['order'] ?? 99;
        }
        return 0;
    }

    /** @var string Option name for notification subscribers */
    const OPTION_SUBSCRIBERS = 'nme_bug_report_subscribers';

    /**
     * Initialize the module
     */
    public static function init(): void {
        add_action('init', [__CLASS__, 'register_post_type']);
        add_action('admin_menu', [__CLASS__, 'add_submenu_page'], 20);
        add_action('admin_post_nme_create_bug_report', [__CLASS__, 'handle_create_report']);
        add_action('admin_post_nme_add_bug_comment', [__CLASS__, 'handle_add_comment']);
        add_action('admin_post_nme_edit_bug_comment', [__CLASS__, 'handle_edit_comment']);
        add_action('admin_post_nme_update_bug_status', [__CLASS__, 'handle_update_status']);
        add_action('admin_post_nme_delete_bug_report', [__CLASS__, 'handle_delete_report']);
        add_action('admin_post_nme_import_bug_report', [__CLASS__, 'handle_import_report']);
        add_action('admin_post_nme_update_bug_subscribers', [__CLASS__, 'handle_update_subscribers']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
        add_action('wp_ajax_nme_get_comment', [__CLASS__, 'ajax_get_comment']);
    }

    /**
     * Get notification subscribers (user IDs)
     */
    public static function get_subscribers(): array {
        $subscribers = get_option(self::OPTION_SUBSCRIBERS, []);
        return is_array($subscribers) ? $subscribers : [];
    }

    /**
     * Send notification email
     */
    public static function send_notification(string $type, int $report_id, int $exclude_user_id = 0, array $extra = []): void {
        $subscribers = self::get_subscribers();
        
        if (empty($subscribers)) {
            return;
        }
        
        // Remove the user who triggered the action
        $subscribers = array_filter($subscribers, function($id) use ($exclude_user_id) {
            return (int) $id !== $exclude_user_id;
        });
        
        if (empty($subscribers)) {
            return;
        }
        
        // Get report details
        $report = get_post($report_id);
        if (!$report) {
            return;
        }
        
        $topic = get_post_meta($report_id, '_nme_bug_topic', true);
        $section = get_post_meta($report_id, '_nme_bug_section', true);
        $section_label = self::get_section_label($section);
        $report_url = admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=single&report_id=' . $report_id);
        
        $actor = get_userdata($exclude_user_id);
        $actor_name = $actor ? $actor->display_name : 'Someone';
        
        // Build email based on type
        switch ($type) {
            case 'new_report':
                $subject = '[NME Bug] New: ' . $topic;
                $message = "{$actor_name} created a new bug report.\n\n";
                $message .= "Section: {$section_label}\n";
                $message .= "Topic: {$topic}\n\n";
                $message .= "View report: {$report_url}";
                break;
                
            case 'new_comment':
                $subject = '[NME Bug] Comment: ' . $topic;
                $message = "{$actor_name} commented on a bug report.\n\n";
                $message .= "Topic: {$topic}\n\n";
                if (!empty($extra['comment_preview'])) {
                    $message .= "Comment:\n" . $extra['comment_preview'] . "\n\n";
                }
                $message .= "View report: {$report_url}";
                break;
                
            case 'status_change':
                $new_status = $extra['new_status'] ?? '';
                $status_label = self::STATUSES[$new_status] ?? $new_status;
                $subject = '[NME Bug] Status → ' . $status_label . ': ' . $topic;
                $message = "{$actor_name} changed the status of a bug report.\n\n";
                $message .= "Topic: {$topic}\n";
                $message .= "New Status: {$status_label}\n\n";
                $message .= "View report: {$report_url}";
                break;
                
            default:
                return;
        }
        
        // Get email addresses
        $to = [];
        foreach ($subscribers as $user_id) {
            $user = get_userdata($user_id);
            if ($user && $user->user_email) {
                $to[] = $user->user_email;
            }
        }
        
        if (empty($to)) {
            return;
        }
        
        // Send email
        $headers = ['Content-Type: text/plain; charset=UTF-8'];
        wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Get user's last read timestamp for a report
     */
    public static function get_user_last_read(int $report_id, int $user_id = 0): string {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $read_data = get_post_meta($report_id, '_nme_bug_read_by', true);
        if (!is_array($read_data)) {
            return '';
        }
        return $read_data[$user_id] ?? '';
    }

    /**
     * Mark report as read by user
     */
    public static function mark_as_read(int $report_id, int $user_id = 0): void {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $read_data = get_post_meta($report_id, '_nme_bug_read_by', true);
        if (!is_array($read_data)) {
            $read_data = [];
        }
        $read_data[$user_id] = current_time('mysql');
        update_post_meta($report_id, '_nme_bug_read_by', $read_data);
    }

    /**
     * Check if report has unread comments for user
     */
    public static function has_unread_comments(int $report_id, int $user_id = 0): bool {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $last_read = self::get_user_last_read($report_id, $user_id);
        $last_activity = get_post_meta($report_id, '_nme_bug_last_activity', true);
        
        // If never read, check if there's any activity
        if (empty($last_read)) {
            // Check if user created the report - if so, they've "read" the initial state
            $author_id = get_post_meta($report_id, '_nme_bug_author', true);
            if ((int) $author_id === $user_id) {
                // Check if there are comments from others
                $comments = get_post_meta($report_id, '_nme_bug_comments', true);
                if (!is_array($comments) || empty($comments)) {
                    return false;
                }
                // Check if any comment is from someone else
                foreach ($comments as $comment) {
                    if ((int) ($comment['author_id'] ?? 0) !== $user_id) {
                        return true;
                    }
                }
                return false;
            }
            return !empty($last_activity);
        }
        
        // Compare timestamps
        if (empty($last_activity)) {
            return false;
        }
        
        return strtotime($last_activity) > strtotime($last_read);
    }

    /**
     * Count unread comments for user
     */
    public static function count_unread_comments(int $report_id, int $user_id = 0): int {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $last_read = self::get_user_last_read($report_id, $user_id);
        $comments = get_post_meta($report_id, '_nme_bug_comments', true);
        
        if (!is_array($comments) || empty($comments)) {
            return 0;
        }
        
        $unread = 0;
        foreach ($comments as $comment) {
            // Skip own comments
            if ((int) ($comment['author_id'] ?? 0) === $user_id) {
                continue;
            }
            
            // If never read, count all others' comments
            if (empty($last_read)) {
                $unread++;
                continue;
            }
            
            // Count if comment is newer than last read
            if (!empty($comment['date']) && strtotime($comment['date']) > strtotime($last_read)) {
                $unread++;
            }
        }
        
        return $unread;
    }

    /**
     * Register the custom post type
     */
    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name'          => 'Bug Reports',
                'singular_name' => 'Bug Report',
            ],
            'public'       => false,
            'show_ui'      => false,
            'supports'     => ['title', 'editor', 'comments'],
            'capabilities' => [
                'create_posts' => 'manage_options',
            ],
        ]);
    }

    /**
     * Add submenu page under NME Platform
     */
    public static function add_submenu_page(): void {
        add_submenu_page(
            'nme-platform',
            'Bug Reports',
            'Bug Reports',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public static function enqueue_admin_assets(string $hook): void {
        if (strpos($hook, self::PAGE_SLUG) === false) {
            return;
        }

        // Enqueue media uploader
        wp_enqueue_media();

        wp_enqueue_style(
            'nme-bug-reports',
            NME_PLATFORM_URL . 'modules/admin/bug-reports/bug-reports.css',
            [],
            NME_PLATFORM_VERSION
        );

        wp_enqueue_script(
            'nme-bug-reports',
            NME_PLATFORM_URL . 'modules/admin/bug-reports/bug-reports.js',
            ['jquery'],
            NME_PLATFORM_VERSION,
            true
        );

        // Extract just labels for JS
        $preliminary_labels = [];
        foreach (self::SUBSECTIONS_PRELIMINARY as $key => $data) {
            $preliminary_labels[$key] = $data['label'];
        }
        $additional_labels = [];
        foreach (self::SUBSECTIONS_ADDITIONAL as $key => $data) {
            $additional_labels[$key] = $data['label'];
        }

        wp_localize_script('nme-bug-reports', 'nmeBugReports', [
            'subsectionsPreliminary' => $preliminary_labels,
            'subsectionsAdditional'  => $additional_labels,
            'ajaxUrl'                => admin_url('admin-ajax.php'),
            'getCommentNonce'        => wp_create_nonce('nme_get_comment'),
        ]);
    }

    /**
     * Render the admin page
     */
    public static function render_page(): void {
        $view = $_GET['view'] ?? 'list';
        $report_id = (int) ($_GET['report_id'] ?? 0);

        ?>
        <div class="wrap nme-bug-reports">
            <h1>Bug Reports</h1>
            
            <?php self::render_notices(); ?>
            
            <?php if ($view === 'new'): ?>
                <?php self::render_new_form(); ?>
            <?php elseif ($view === 'import'): ?>
                <?php self::render_import_form(); ?>
            <?php elseif ($view === 'settings'): ?>
                <?php self::render_settings(); ?>
            <?php elseif ($view === 'single' && $report_id): ?>
                <?php self::render_single_report($report_id); ?>
            <?php else: ?>
                <?php self::render_list(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render notices
     */
    private static function render_notices(): void {
        if (isset($_GET['created'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Bug report created successfully.</p></div>';
        }
        if (isset($_GET['imported'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Bug report imported successfully from OneNote.</p></div>';
        }
        if (isset($_GET['commented'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Comment added successfully.</p></div>';
        }
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Status updated successfully.</p></div>';
        }
        if (isset($_GET['edited'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Comment updated successfully.</p></div>';
        }
        if (isset($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Bug report deleted successfully.</p></div>';
        }
        if (isset($_GET['settings_saved'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Notification settings saved.</p></div>';
        }
        if (isset($_GET['error'])) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html($_GET['error']) . '</p></div>';
        }
    }

    /**
     * Render the bug list view
     */
    private static function render_list(): void {
        $status_filter = sanitize_text_field($_GET['status'] ?? '');
        
        $args = [
            'post_type'      => self::POST_TYPE,
            'posts_per_page' => 100,
            'orderby'        => 'meta_value',
            'meta_key'       => '_nme_bug_section_order',
            'order'          => 'ASC',
        ];

        if ($status_filter) {
            $args['meta_query'] = [
                [
                    'key'   => '_nme_bug_status',
                    'value' => $status_filter,
                ],
            ];
        }

        $reports = get_posts($args);

        // Secondary sort by subsection order
        usort($reports, function($a, $b) {
            $section_order_a = (int) get_post_meta($a->ID, '_nme_bug_section_order', true);
            $section_order_b = (int) get_post_meta($b->ID, '_nme_bug_section_order', true);
            
            if ($section_order_a !== $section_order_b) {
                return $section_order_a - $section_order_b;
            }
            
            $subsection_order_a = (int) get_post_meta($a->ID, '_nme_bug_subsection_order', true);
            $subsection_order_b = (int) get_post_meta($b->ID, '_nme_bug_subsection_order', true);
            
            return $subsection_order_a - $subsection_order_b;
        });

        // Group reports by section
        $grouped = [];
        foreach ($reports as $report) {
            $section = get_post_meta($report->ID, '_nme_bug_section', true);
            if (!isset($grouped[$section])) {
                $grouped[$section] = [];
            }
            $grouped[$section][] = $report;
        }

        ?>
        <div class="nme-bug-header">
            <div class="nme-bug-header-buttons">
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=new')); ?>" 
                   class="button button-primary">New Bug Report</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=import')); ?>" 
                   class="button">Manual Entry (with history)</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=settings')); ?>" 
                   class="button">⚙ Settings</a>
            </div>
            
            <form method="get" class="nme-bug-filter">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::PAGE_SLUG); ?>">
                <select name="status">
                    <option value="">All Statuses</option>
                    <?php foreach (self::STATUSES as $key => $label): ?>
                        <option value="<?php echo esc_attr($key); ?>" <?php selected($status_filter, $key); ?>>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="button">Filter</button>
                <button type="button" class="button" id="nme-expand-all">Expand All</button>
                <button type="button" class="button" id="nme-collapse-all">Collapse All</button>
            </form>
        </div>

        <?php if (empty($reports)): ?>
            <div class="nme-bug-panel">
                <p>No bug reports found.</p>
            </div>
        <?php else: ?>
            <div class="nme-accordion">
                <?php foreach (self::SECTIONS as $section_key => $section_data): ?>
                    <?php 
                    if (!isset($grouped[$section_key])) {
                        continue;
                    }
                    $section_reports = $grouped[$section_key];
                    $section_count = count($section_reports);
                    
                    // Count unread in this section
                    $section_unread = 0;
                    foreach ($section_reports as $report) {
                        $section_unread += self::count_unread_comments($report->ID);
                    }
                    ?>
                    <div class="nme-accordion-section" data-section="<?php echo esc_attr($section_key); ?>">
                        <div class="nme-accordion-header">
                            <span class="nme-accordion-toggle">▶</span>
                            <span class="nme-accordion-title"><?php echo esc_html($section_data['label']); ?></span>
                            <span class="nme-accordion-count"><?php echo esc_html($section_count); ?> issue<?php echo $section_count !== 1 ? 's' : ''; ?></span>
                            <?php if ($section_unread > 0): ?>
                                <span class="nme-unread-badge"><?php echo esc_html($section_unread); ?> new</span>
                            <?php endif; ?>
                        </div>
                        <div class="nme-accordion-content">
                            <table class="widefat striped nme-bug-table-compact">
                                <thead>
                                    <tr>
                                        <th class="col-topic">Topic</th>
                                        <th class="col-priority">Priority</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-activity">Activity</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($section_reports as $report): ?>
                                        <?php
                                        $subsection = get_post_meta($report->ID, '_nme_bug_subsection', true);
                                        $topic = get_post_meta($report->ID, '_nme_bug_topic', true);
                                        $priority = get_post_meta($report->ID, '_nme_bug_priority', true);
                                        $status = get_post_meta($report->ID, '_nme_bug_status', true);
                                        $author_id = get_post_meta($report->ID, '_nme_bug_author', true);
                                        $author = get_userdata($author_id);
                                        
                                        $comments = get_post_meta($report->ID, '_nme_bug_comments', true);
                                        $comment_count = is_array($comments) ? count($comments) : 0;
                                        $last_activity = get_post_meta($report->ID, '_nme_bug_last_activity', true);
                                        if (!$last_activity) {
                                            $last_activity = $report->post_date;
                                        }
                                        
                                        $unread_count = self::count_unread_comments($report->ID);
                                        $has_unread = $unread_count > 0;
                                        
                                        $subsection_label = $subsection ? self::get_subsection_label($section_key, $subsection) : '';
                                        ?>
                                        <tr class="<?php echo $has_unread ? 'nme-has-unread' : ''; ?>">
                                            <td class="col-topic">
                                                <?php if ($subsection_label): ?>
                                                    <div class="nme-bug-subsection"><?php echo esc_html($subsection_label); ?></div>
                                                <?php endif; ?>
                                                <div class="nme-bug-topic">
                                                    <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG . '&view=single&report_id=' . $report->ID)); ?>">
                                                        <?php if ($has_unread): ?>
                                                            <span class="nme-unread-indicator" title="<?php echo esc_attr($unread_count); ?> unread"></span>
                                                        <?php endif; ?>
                                                        <?php echo esc_html($topic); ?>
                                                    </a>
                                                    <span class="nme-bug-author">— <?php echo esc_html($author ? $author->display_name : 'Unknown'); ?></span>
                                                </div>
                                            </td>
                                            <td class="col-priority">
                                                <span class="nme-priority nme-priority-<?php echo esc_attr($priority); ?>">
                                                    <?php echo esc_html(self::PRIORITIES[$priority] ?? $priority); ?>
                                                </span>
                                            </td>
                                            <td class="col-status">
                                                <span class="nme-status nme-status-<?php echo esc_attr($status); ?>">
                                                    <?php echo esc_html(self::STATUSES[$status] ?? $status); ?>
                                                </span>
                                            </td>
                                            <td class="col-activity">
                                                <div class="nme-bug-comments-count">
                                                    <?php echo esc_html($comment_count); ?> comment<?php echo $comment_count !== 1 ? 's' : ''; ?>
                                                    <?php if ($has_unread): ?>
                                                        <span class="nme-unread-badge"><?php echo esc_html($unread_count); ?> new</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="nme-bug-last-activity">
                                                    <?php echo esc_html(date('M j, g:i a', strtotime($last_activity))); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Render the new bug report form
     */
    private static function render_new_form(): void {
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">← Back to List</a>
        
        <div class="nme-bug-panel nme-bug-form-panel">
            <h2>Submit New Bug Report</h2>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="nme_create_bug_report">
                <?php wp_nonce_field('nme_create_bug_report', 'nme_bug_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="section">Section <span class="required">*</span></label></th>
                        <td>
                            <select name="section" id="section" required>
                                <option value="">— Select Section —</option>
                                <?php foreach (self::SECTIONS as $key => $data): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($data['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="subsection-row" style="display: none;">
                        <th scope="row"><label for="subsection">Subsection <span class="required">*</span></label></th>
                        <td>
                            <select name="subsection" id="subsection">
                                <option value="">— Select Subsection —</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="topic">Specific Topic <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="topic" id="topic" class="large-text" required
                                   placeholder="e.g., Question 5 - Date picker not calculating correctly">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Description <span class="required">*</span></label></th>
                        <td>
                            <?php 
                            wp_editor('', 'description', [
                                'textarea_name' => 'description',
                                'textarea_rows' => 10,
                                'media_buttons' => true,
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]); 
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="priority">Priority</label></th>
                        <td>
                            <select name="priority" id="priority">
                                <?php foreach (self::PRIORITIES as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'medium'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Submit Bug Report</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render single report view
     */
    private static function render_single_report(int $report_id): void {
        $report = get_post($report_id);
        
        if (!$report || $report->post_type !== self::POST_TYPE) {
            echo '<div class="notice notice-error"><p>Bug report not found.</p></div>';
            return;
        }

        // Mark as read for current user
        self::mark_as_read($report_id);

        $section = get_post_meta($report_id, '_nme_bug_section', true);
        $subsection = get_post_meta($report_id, '_nme_bug_subsection', true);
        $topic = get_post_meta($report_id, '_nme_bug_topic', true);
        $priority = get_post_meta($report_id, '_nme_bug_priority', true);
        $status = get_post_meta($report_id, '_nme_bug_status', true);
        $author_id = get_post_meta($report_id, '_nme_bug_author', true);
        $author = get_userdata($author_id);

        // Build section label
        $section_label = self::get_section_label($section);
        if ($subsection) {
            $section_label .= ' → ' . self::get_subsection_label($section, $subsection);
        }

        // Get comments
        $comments = get_post_meta($report_id, '_nme_bug_comments', true);
        if (!is_array($comments)) {
            $comments = [];
        }

        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">← Back to List</a>
        
        <div class="nme-bug-single">
            <div class="nme-bug-main">
                <div class="nme-bug-panel">
                    <div class="nme-bug-header-single">
                        <h2>Bug #<?php echo esc_html($report_id); ?>: <?php echo esc_html($topic); ?></h2>
                        <span class="nme-status nme-status-<?php echo esc_attr($status); ?>">
                            <?php echo esc_html(self::STATUSES[$status] ?? $status); ?>
                        </span>
                    </div>
                    
                    <table class="nme-bug-meta">
                        <tr>
                            <th>Section:</th>
                            <td><?php echo esc_html($section_label); ?></td>
                        </tr>
                        <tr>
                            <th>Priority:</th>
                            <td>
                                <span class="nme-priority nme-priority-<?php echo esc_attr($priority); ?>">
                                    <?php echo esc_html(self::PRIORITIES[$priority] ?? $priority); ?>
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th>Submitted By:</th>
                            <td><?php echo esc_html($author ? $author->display_name : 'Unknown'); ?></td>
                        </tr>
                        <tr>
                            <th>Date:</th>
                            <td><?php echo esc_html(get_the_date('F j, Y \a\t g:i a', $report)); ?></td>
                        </tr>
                    </table>
                    
                    <div class="nme-bug-description">
                        <h3>Description</h3>
                        <div class="nme-bug-content">
                            <?php echo wp_kses_post(wpautop($report->post_content)); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Comments Section -->
                <div class="nme-bug-panel nme-bug-comments">
                    <h3>Comments &amp; Updates</h3>
                    
                    <?php if (empty($comments)): ?>
                        <p class="nme-no-comments">No comments yet.</p>
                    <?php else: ?>
                        <?php foreach ($comments as $index => $comment): ?>
                            <?php $comment_author = get_userdata($comment['author_id']); ?>
                            <div class="nme-comment <?php echo !empty($comment['status_change']) ? 'nme-comment-status' : ''; ?>" data-comment-index="<?php echo esc_attr($index); ?>">
                                <div class="nme-comment-header">
                                    <strong><?php echo esc_html($comment_author ? $comment_author->display_name : 'Unknown'); ?></strong>
                                    <span class="nme-comment-date">
                                        <?php echo esc_html(date('M j, Y \a\t g:i a', strtotime($comment['date']))); ?>
                                    </span>
                                    <?php if (!empty($comment['edited'])): ?>
                                        <span class="nme-comment-edited">(edited)</span>
                                    <?php endif; ?>
                                    <?php if (!empty($comment['content']) && empty($comment['status_change'])): ?>
                                        <button type="button" class="button-link nme-edit-comment" 
                                                data-report-id="<?php echo esc_attr($report_id); ?>"
                                                data-comment-index="<?php echo esc_attr($index); ?>">Edit</button>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($comment['status_change'])): ?>
                                    <div class="nme-status-change">
                                        Changed status to: 
                                        <span class="nme-status nme-status-<?php echo esc_attr($comment['status_change']); ?>">
                                            <?php echo esc_html(self::STATUSES[$comment['status_change']] ?? $comment['status_change']); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($comment['content'])): ?>
                                    <div class="nme-comment-content">
                                        <?php echo wp_kses_post(wpautop($comment['content'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <!-- Add Comment Form -->
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="nme-add-comment" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="nme_add_bug_comment">
                        <input type="hidden" name="report_id" value="<?php echo esc_attr($report_id); ?>">
                        <?php wp_nonce_field('nme_add_bug_comment', 'nme_comment_nonce'); ?>
                        
                        <h4>Add Comment</h4>
                        <?php 
                        wp_editor('', 'comment', [
                            'textarea_name' => 'comment',
                            'textarea_rows' => 6,
                            'media_buttons' => true,
                            'teeny'         => true,
                            'quicktags'     => true,
                        ]); 
                        ?>
                        
                        <div class="nme-comment-actions">
                            <button type="submit" class="button button-primary">Add Comment</button>
                        </div>
                    </form>
                    
                    <!-- Edit Comment Form (inline, hidden by default) -->
                    <div id="nme-edit-comment-panel" class="nme-edit-panel" style="display: none;">
                        <h4>Edit Comment</h4>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="nme-edit-comment-form">
                            <input type="hidden" name="action" value="nme_edit_bug_comment">
                            <input type="hidden" name="report_id" value="<?php echo esc_attr($report_id); ?>">
                            <input type="hidden" name="comment_index" id="edit-comment-index" value="">
                            <?php wp_nonce_field('nme_edit_bug_comment', 'nme_edit_comment_nonce'); ?>
                            
                            <?php 
                            wp_editor('', 'edit_comment_content', [
                                'textarea_name' => 'comment_content',
                                'textarea_rows' => 8,
                                'media_buttons' => true,
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]); 
                            ?>
                            
                            <div class="nme-edit-actions">
                                <button type="button" class="button nme-edit-cancel">Cancel</button>
                                <button type="submit" class="button button-primary">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="nme-bug-sidebar">
                <div class="nme-bug-panel">
                    <h3>Update Status</h3>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                        <input type="hidden" name="action" value="nme_update_bug_status">
                        <input type="hidden" name="report_id" value="<?php echo esc_attr($report_id); ?>">
                        <?php wp_nonce_field('nme_update_bug_status', 'nme_status_nonce'); ?>
                        
                        <select name="status" class="nme-status-select">
                            <?php foreach (self::STATUSES as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($status, $key); ?>>
                                    <?php echo esc_html($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <textarea name="status_comment" rows="3" class="large-text" 
                                  placeholder="Optional comment about this status change..."></textarea>
                        
                        <button type="submit" class="button">Update Status</button>
                    </form>
                </div>
                
                <div class="nme-bug-panel nme-bug-danger-zone">
                    <h3>Danger Zone</h3>
                    <p>Permanently delete this bug report and all its comments.</p>
                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" 
                          onsubmit="return confirm('Are you sure you want to delete this bug report? This cannot be undone.');">
                        <input type="hidden" name="action" value="nme_delete_bug_report">
                        <input type="hidden" name="report_id" value="<?php echo esc_attr($report_id); ?>">
                        <?php wp_nonce_field('nme_delete_bug_report', 'nme_delete_nonce'); ?>
                        <button type="submit" class="button button-link-delete">Delete Bug Report</button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle create bug report
     */
    public static function handle_create_report(): void {
        if (!wp_verify_nonce($_POST['nme_bug_nonce'] ?? '', 'nme_create_bug_report')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $section = sanitize_text_field($_POST['section'] ?? '');
        $subsection = sanitize_text_field($_POST['subsection'] ?? '');
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $description = wp_kses_post($_POST['description'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'medium');

        if (empty($section) || empty($topic) || empty($description)) {
            self::redirect_with_error('Please fill in all required fields');
            return;
        }

        // Validate subsection requirement
        if (in_array($section, ['preliminary-eligibility', 'additional-information']) && empty($subsection)) {
            self::redirect_with_error('Please select a subsection');
            return;
        }

        $post_id = wp_insert_post([
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $topic,
            'post_content' => $description,
        ]);

        if (is_wp_error($post_id)) {
            self::redirect_with_error('Failed to create bug report');
            return;
        }

        // Calculate sort orders
        $section_order = self::get_section_order($section);
        $subsection_order = $subsection ? self::get_subsection_order($section, $subsection) : 0;

        update_post_meta($post_id, '_nme_bug_section', $section);
        update_post_meta($post_id, '_nme_bug_subsection', $subsection);
        update_post_meta($post_id, '_nme_bug_section_order', $section_order);
        update_post_meta($post_id, '_nme_bug_subsection_order', $subsection_order);
        update_post_meta($post_id, '_nme_bug_topic', $topic);
        update_post_meta($post_id, '_nme_bug_priority', $priority);
        update_post_meta($post_id, '_nme_bug_status', 'open');
        update_post_meta($post_id, '_nme_bug_author', get_current_user_id());
        update_post_meta($post_id, '_nme_bug_comments', []);
        update_post_meta($post_id, '_nme_bug_last_activity', current_time('mysql'));

        // Send notification
        self::send_notification('new_report', $post_id, get_current_user_id());

        wp_redirect(add_query_arg([
            'page'    => self::PAGE_SLUG,
            'view'    => 'single',
            'report_id' => $post_id,
            'created' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle add comment
     */
    public static function handle_add_comment(): void {
        if (!wp_verify_nonce($_POST['nme_comment_nonce'] ?? '', 'nme_add_bug_comment')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $report_id = (int) ($_POST['report_id'] ?? 0);
        $comment = wp_kses_post($_POST['comment'] ?? '');

        if (!$report_id || empty($comment)) {
            self::redirect_to_report($report_id, 'error', 'Please enter a comment');
            return;
        }

        $comments = get_post_meta($report_id, '_nme_bug_comments', true);
        if (!is_array($comments)) {
            $comments = [];
        }

        $now = current_time('mysql');

        $comments[] = [
            'author_id' => get_current_user_id(),
            'date'      => $now,
            'content'   => $comment,
        ];

        update_post_meta($report_id, '_nme_bug_comments', $comments);
        update_post_meta($report_id, '_nme_bug_last_activity', $now);

        // Send notification with comment preview
        $preview = wp_strip_all_tags($comment);
        $preview = strlen($preview) > 200 ? substr($preview, 0, 200) . '...' : $preview;
        self::send_notification('new_comment', $report_id, get_current_user_id(), ['comment_preview' => $preview]);

        self::redirect_to_report($report_id, 'commented');
    }

    /**
     * Handle status update
     */
    public static function handle_update_status(): void {
        if (!wp_verify_nonce($_POST['nme_status_nonce'] ?? '', 'nme_update_bug_status')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $report_id = (int) ($_POST['report_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        $status_comment = wp_kses_post($_POST['status_comment'] ?? '');

        if (!$report_id || empty($new_status)) {
            self::redirect_to_report($report_id, 'error', 'Invalid request');
            return;
        }

        $old_status = get_post_meta($report_id, '_nme_bug_status', true);

        if ($old_status !== $new_status) {
            update_post_meta($report_id, '_nme_bug_status', $new_status);

            $now = current_time('mysql');

            // Add status change comment
            $comments = get_post_meta($report_id, '_nme_bug_comments', true);
            if (!is_array($comments)) {
                $comments = [];
            }

            $comments[] = [
                'author_id'     => get_current_user_id(),
                'date'          => $now,
                'content'       => $status_comment,
                'status_change' => $new_status,
            ];

            update_post_meta($report_id, '_nme_bug_comments', $comments);
            update_post_meta($report_id, '_nme_bug_last_activity', $now);

            // Send notification
            self::send_notification('status_change', $report_id, get_current_user_id(), ['new_status' => $new_status]);
        }

        self::redirect_to_report($report_id, 'updated');
    }

    /**
     * Redirect to report with message
     */
    private static function redirect_to_report(int $report_id, string $status, string $error = ''): void {
        $args = [
            'page'      => self::PAGE_SLUG,
            'view'      => 'single',
            'report_id' => $report_id,
        ];

        if ($status === 'error') {
            $args['error'] = urlencode($error);
        } else {
            $args[$status] = 1;
        }

        wp_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /**
     * Redirect with error message
     */
    private static function redirect_with_error(string $message): void {
        wp_redirect(add_query_arg([
            'page'  => self::PAGE_SLUG,
            'view'  => 'new',
            'error' => urlencode($message),
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle delete bug report
     */
    public static function handle_delete_report(): void {
        if (!wp_verify_nonce($_POST['nme_delete_nonce'] ?? '', 'nme_delete_bug_report')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $report_id = (int) ($_POST['report_id'] ?? 0);

        if (!$report_id) {
            wp_die('Invalid report ID');
        }

        $report = get_post($report_id);
        if (!$report || $report->post_type !== self::POST_TYPE) {
            wp_die('Bug report not found');
        }

        wp_delete_post($report_id, true);

        wp_redirect(add_query_arg([
            'page'    => self::PAGE_SLUG,
            'deleted' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Handle edit comment
     */
    public static function handle_edit_comment(): void {
        if (!wp_verify_nonce($_POST['nme_edit_comment_nonce'] ?? '', 'nme_edit_bug_comment')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $report_id = (int) ($_POST['report_id'] ?? 0);
        $comment_index = (int) ($_POST['comment_index'] ?? -1);
        $comment_content = wp_kses_post($_POST['comment_content'] ?? '');

        if (!$report_id || $comment_index < 0) {
            self::redirect_to_report($report_id, 'error', 'Invalid request');
            return;
        }

        $comments = get_post_meta($report_id, '_nme_bug_comments', true);
        if (!is_array($comments) || !isset($comments[$comment_index])) {
            self::redirect_to_report($report_id, 'error', 'Comment not found');
            return;
        }

        // Update the comment content
        $comments[$comment_index]['content'] = $comment_content;
        $comments[$comment_index]['edited'] = current_time('mysql');
        $comments[$comment_index]['edited_by'] = get_current_user_id();

        update_post_meta($report_id, '_nme_bug_comments', $comments);

        self::redirect_to_report($report_id, 'edited');
    }

    /**
     * AJAX handler to get comment content for editing
     */
    public static function ajax_get_comment(): void {
        check_ajax_referer('nme_get_comment', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $report_id = (int) ($_POST['report_id'] ?? 0);
        $comment_index = (int) ($_POST['comment_index'] ?? -1);

        if (!$report_id || $comment_index < 0) {
            wp_send_json_error('Invalid request');
        }

        $comments = get_post_meta($report_id, '_nme_bug_comments', true);
        if (!is_array($comments) || !isset($comments[$comment_index])) {
            wp_send_json_error('Comment not found');
        }

        wp_send_json_success([
            'content' => $comments[$comment_index]['content'] ?? '',
        ]);
    }

    /**
     * Render import form
     */
    private static function render_import_form(): void {
        // Get users for author mapping
        $users = get_users(['role__in' => ['administrator', 'editor']]);
        $current_user_id = get_current_user_id();
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">← Back to List</a>
        
        <div class="nme-bug-panel nme-bug-form-panel">
            <h2>Import / Manual Entry</h2>
            <p class="description">Create a bug report with multiple pre-dated comments. First entry becomes the description, additional entries become comments.</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="nme-import-form">
                <input type="hidden" name="action" value="nme_import_bug_report">
                <?php wp_nonce_field('nme_import_bug_report', 'nme_import_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="import_section">Section <span class="required">*</span></label></th>
                        <td>
                            <select name="section" id="import_section" required>
                                <option value="">— Select Section —</option>
                                <?php foreach (self::SECTIONS as $key => $data): ?>
                                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($data['label']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr id="import-subsection-row" style="display: none;">
                        <th scope="row"><label for="import_subsection">Subsection <span class="required">*</span></label></th>
                        <td>
                            <select name="subsection" id="import_subsection">
                                <option value="">— Select Subsection —</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="import_topic">Topic <span class="required">*</span></label></th>
                        <td>
                            <input type="text" name="topic" id="import_topic" class="large-text" required
                                   placeholder="Brief description of the issue">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="import_priority">Priority</label></th>
                        <td>
                            <select name="priority" id="import_priority">
                                <?php foreach (self::PRIORITIES as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected($key, 'medium'); ?>>
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="import_status">Status</label></th>
                        <td>
                            <select name="status" id="import_status">
                                <?php foreach (self::STATUSES as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                
                <div class="nme-import-layout">
                    <div class="nme-import-entries-column">
                        <h3>Entries</h3>
                        <p class="description">Click "Edit" to modify content in the editor. First entry = description.</p>
                        
                        <div id="nme-entries-container">
                            <div class="nme-entry" data-index="0">
                                <div class="nme-entry-header">
                                    <span class="nme-entry-number">Entry #1 (Description)</span>
                                    <div class="nme-entry-actions">
                                        <button type="button" class="button button-small nme-edit-entry-content">Edit</button>
                                        <button type="button" class="button-link nme-remove-entry" style="display: none;">Remove</button>
                                    </div>
                                </div>
                                <div class="nme-entry-fields">
                                    <div class="nme-entry-row">
                                        <label>Author:</label>
                                        <select name="entries[0][author_id]" class="nme-entry-author">
                                            <?php foreach ($users as $user): ?>
                                                <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user->ID, $current_user_id); ?>>
                                                    <?php echo esc_html($user->display_name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        
                                        <label>Date:</label>
                                        <input type="date" name="entries[0][date]" class="nme-entry-date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                                        
                                        <label>Time:</label>
                                        <input type="time" name="entries[0][time]" class="nme-entry-time" value="12:00">
                                    </div>
                                    <input type="hidden" name="entries[0][content]" class="nme-entry-content-hidden" value="">
                                    <div class="nme-entry-preview">
                                        <em>No content yet - click Edit</em>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <p>
                            <button type="button" class="button" id="nme-add-entry">+ Add Entry</button>
                        </p>
                    </div>
                    
                    <div class="nme-import-editor-column">
                        <h3>Content Editor</h3>
                        <p class="description nme-editor-status">Select an entry and click "Edit" to begin.</p>
                        
                        <div id="nme-entry-editor-panel">
                            <input type="hidden" id="nme-editing-index" value="">
                            <?php 
                            wp_editor('', 'entry_content_editor', [
                                'textarea_name' => 'entry_content_editor_dummy',
                                'textarea_rows' => 12,
                                'media_buttons' => true,
                                'teeny'         => false,
                                'quicktags'     => true,
                            ]); 
                            ?>
                            <div class="nme-editor-actions">
                                <button type="button" class="button button-primary" id="nme-save-entry-content">Save to Entry</button>
                                <button type="button" class="button" id="nme-cancel-entry-edit">Cancel</button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Hidden template for new entries -->
                <script type="text/template" id="nme-entry-template">
                    <div class="nme-entry" data-index="{{INDEX}}">
                        <div class="nme-entry-header">
                            <span class="nme-entry-number">Entry #{{NUM}} (Comment)</span>
                            <div class="nme-entry-actions">
                                <button type="button" class="button button-small nme-edit-entry-content">Edit</button>
                                <button type="button" class="button-link nme-remove-entry">Remove</button>
                            </div>
                        </div>
                        <div class="nme-entry-fields">
                            <div class="nme-entry-row">
                                <label>Author:</label>
                                <select name="entries[{{INDEX}}][author_id]" class="nme-entry-author">
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo esc_attr($user->ID); ?>" <?php selected($user->ID, $current_user_id); ?>>
                                            <?php echo esc_html($user->display_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                
                                <label>Date:</label>
                                <input type="date" name="entries[{{INDEX}}][date]" class="nme-entry-date" value="<?php echo esc_attr(date('Y-m-d')); ?>" required>
                                
                                <label>Time:</label>
                                <input type="time" name="entries[{{INDEX}}][time]" class="nme-entry-time" value="12:00">
                            </div>
                            <input type="hidden" name="entries[{{INDEX}}][content]" class="nme-entry-content-hidden" value="">
                            <div class="nme-entry-preview">
                                <em>No content yet - click Edit</em>
                            </div>
                        </div>
                    </div>
                </script>
                
                <hr style="margin: 20px 0;">
                
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">Create Bug Report</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle import
     */
    public static function handle_import_report(): void {
        if (!wp_verify_nonce($_POST['nme_import_nonce'] ?? '', 'nme_import_bug_report')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $section = sanitize_text_field($_POST['section'] ?? '');
        $subsection = sanitize_text_field($_POST['subsection'] ?? '');
        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $priority = sanitize_text_field($_POST['priority'] ?? 'medium');
        $status = sanitize_text_field($_POST['status'] ?? 'open');
        $entries = $_POST['entries'] ?? [];

        if (empty($section) || empty($topic) || empty($entries)) {
            wp_redirect(add_query_arg([
                'page'  => self::PAGE_SLUG,
                'view'  => 'import',
                'error' => urlencode('Please fill in all required fields'),
            ], admin_url('admin.php')));
            exit;
        }

        // Validate subsection requirement
        if (in_array($section, ['preliminary-eligibility', 'additional-information']) && empty($subsection)) {
            wp_redirect(add_query_arg([
                'page'  => self::PAGE_SLUG,
                'view'  => 'import',
                'error' => urlencode('Please select a subsection'),
            ], admin_url('admin.php')));
            exit;
        }

        // Process entries
        $processed_entries = [];
        foreach ($entries as $entry) {
            $author_id = (int) ($entry['author_id'] ?? get_current_user_id());
            $date = sanitize_text_field($entry['date'] ?? date('Y-m-d'));
            $time = sanitize_text_field($entry['time'] ?? '12:00');
            $content = wp_kses_post($entry['content'] ?? '');
            
            if (empty($content)) {
                continue;
            }
            
            $mysql_date = $date . ' ' . $time . ':00';
            
            $processed_entries[] = [
                'author_id' => $author_id,
                'date'      => $mysql_date,
                'content'   => nl2br($content),
            ];
        }
        
        if (empty($processed_entries)) {
            wp_redirect(add_query_arg([
                'page'  => self::PAGE_SLUG,
                'view'  => 'import',
                'error' => urlencode('Please add at least one entry with content'),
            ], admin_url('admin.php')));
            exit;
        }

        // Sort by date
        usort($processed_entries, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Use first entry as description
        $first_entry = array_shift($processed_entries);
        $description = $first_entry['content'];
        $created_date = $first_entry['date'];
        $author_id = $first_entry['author_id'];

        // Create the post
        $post_id = wp_insert_post([
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $topic,
            'post_content' => $description,
            'post_date'    => $created_date,
        ]);

        if (is_wp_error($post_id)) {
            wp_redirect(add_query_arg([
                'page'  => self::PAGE_SLUG,
                'view'  => 'import',
                'error' => urlencode('Failed to create bug report'),
            ], admin_url('admin.php')));
            exit;
        }

        // Calculate sort orders
        $section_order = self::get_section_order($section);
        $subsection_order = $subsection ? self::get_subsection_order($section, $subsection) : 0;

        // Get last activity time
        $last_activity = $created_date;
        if (!empty($processed_entries)) {
            $last_entry = end($processed_entries);
            $last_activity = $last_entry['date'];
        }

        update_post_meta($post_id, '_nme_bug_section', $section);
        update_post_meta($post_id, '_nme_bug_subsection', $subsection);
        update_post_meta($post_id, '_nme_bug_section_order', $section_order);
        update_post_meta($post_id, '_nme_bug_subsection_order', $subsection_order);
        update_post_meta($post_id, '_nme_bug_topic', $topic);
        update_post_meta($post_id, '_nme_bug_priority', $priority);
        update_post_meta($post_id, '_nme_bug_status', $status);
        update_post_meta($post_id, '_nme_bug_author', $author_id);
        update_post_meta($post_id, '_nme_bug_comments', $processed_entries);
        update_post_meta($post_id, '_nme_bug_last_activity', $last_activity);

        wp_redirect(add_query_arg([
            'page'      => self::PAGE_SLUG,
            'view'      => 'single',
            'report_id' => $post_id,
            'imported'  => 1,
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * Render settings page
     */
    private static function render_settings(): void {
        $subscribers = self::get_subscribers();
        $users = get_users(['role__in' => ['administrator', 'editor']]);
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=' . self::PAGE_SLUG)); ?>" class="button">← Back to List</a>
        
        <div class="nme-bug-panel nme-bug-form-panel" style="max-width: 600px;">
            <h2>Notification Settings</h2>
            <p class="description">Select which users should receive email notifications for new bug reports, comments, and status changes.</p>
            
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="nme_update_bug_subscribers">
                <?php wp_nonce_field('nme_update_bug_subscribers', 'nme_subscribers_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Email Notifications</th>
                        <td>
                            <fieldset>
                                <legend class="screen-reader-text">Users to notify</legend>
                                <?php foreach ($users as $user): ?>
                                    <label style="display: block; margin-bottom: 8px;">
                                        <input type="checkbox" 
                                               name="subscribers[]" 
                                               value="<?php echo esc_attr($user->ID); ?>"
                                               <?php checked(in_array($user->ID, $subscribers)); ?>>
                                        <?php echo esc_html($user->display_name); ?>
                                        <span style="color: #666;">(<?php echo esc_html($user->user_email); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description" style="margin-top: 10px;">
                                Users will NOT receive notifications for their own actions (e.g., if Jim creates a report, Jim won't be emailed).
                            </p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Handle update subscribers
     */
    public static function handle_update_subscribers(): void {
        if (!wp_verify_nonce($_POST['nme_subscribers_nonce'] ?? '', 'nme_update_bug_subscribers')) {
            wp_die('Security check failed');
        }

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $subscribers = $_POST['subscribers'] ?? [];
        $subscribers = array_map('intval', (array) $subscribers);
        $subscribers = array_filter($subscribers);

        update_option(self::OPTION_SUBSCRIBERS, $subscribers);

        wp_redirect(add_query_arg([
            'page'           => self::PAGE_SLUG,
            'view'           => 'settings',
            'settings_saved' => 1,
        ], admin_url('admin.php')));
        exit;
    }
}
