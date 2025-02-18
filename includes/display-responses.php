<?php
// File: includes/display-responses.php

if (!defined('ABSPATH')) {
    exit;
}
function display_survey_responses() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    global $wpdb;
    $response_table = $wpdb->prefix . 'survey_responses';
    $tokens_table = $wpdb->prefix . 'survey_tokens';

    // Get filters
    $form_id_filter = isset($_GET['form_id']) ? sanitize_text_field($_GET['form_id']) : '';
    $study_group_filter = isset($_GET['study_group']) ? sanitize_text_field($_GET['study_group']) : '';
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : '';

    // Basic query for responses
    $query = "SELECT r.*, p.post_title as question_title, t.study_group 
             FROM {$response_table} r 
             LEFT JOIN {$wpdb->posts} p ON r.question_id = p.ID 
             LEFT JOIN {$tokens_table} t ON r.token = t.token";

    $where = array();
    $where_values = array();

    // Add filters if they exist
    if (!empty($form_id_filter)) {
        $where[] = 'r.form_id = %s';
        $where_values[] = $form_id_filter;
    }
    if (!empty($study_group_filter)) {
        $where[] = 't.study_group = %s';
        $where_values[] = $study_group_filter;
    }
    if (!empty($date_from)) {
        $where[] = 'DATE(r.created_at) >= %s';
        $where_values[] = $date_from;
    }
    if (!empty($date_to)) {
        $where[] = 'DATE(r.created_at) <= %s';
        $where_values[] = $date_to;
    }

    // Add WHERE clause if filters exist
    if (!empty($where)) {
        $query .= ' WHERE ' . implode(' AND ', $where);
    }

    // Add ordering
    $query .= ' ORDER BY r.created_at DESC';

    // Get results
    $results = !empty($where_values) ? 
               $wpdb->get_results($wpdb->prepare($query, $where_values)) : 
               $wpdb->get_results($query);

    // Get form IDs for filter
    $form_ids = $wpdb->get_col("SELECT DISTINCT form_id FROM {$response_table} ORDER BY form_id ASC");
    
    // Get study groups for filter
    $study_groups = $wpdb->get_col("SELECT DISTINCT study_group FROM {$tokens_table} WHERE study_group IS NOT NULL ORDER BY study_group ASC");

    // Get statistics
    $stats = $wpdb->get_row("
        SELECT 
            COUNT(DISTINCT token) as total_participants,
            COUNT(*) as total_responses,
            COUNT(DISTINCT form_id) as total_forms
        FROM {$response_table}
    ");

    ?>
    <div class="wrap">
        <h1>Survey Responses</h1>

        <!-- Statistics -->
        <div class="survey-analytics">
            <div class="stat-box">
                <h3>Total Participants</h3>
                <span class="stat-number"><?php echo number_format_i18n($stats->total_participants); ?></span>
            </div>
            <div class="stat-box">
                <h3>Total Responses</h3>
                <span class="stat-number"><?php echo number_format_i18n($stats->total_responses); ?></span>
            </div>
            <div class="stat-box">
                <h3>Forms Used</h3>
                <span class="stat-number"><?php echo number_format_i18n($stats->total_forms); ?></span>
            </div>
        </div>

        <!-- Filters -->
        <div class="tablenav top">
            <div class="alignleft actions">
                <form method="get" class="survey-filters">
                    <input type="hidden" name="page" value="survey-responses">
                    
                    <select name="form_id">
                        <option value="">All Forms</option>
                        <?php foreach ($form_ids as $form_id): ?>
                            <option value="<?php echo esc_attr($form_id); ?>" 
                                    <?php selected($form_id_filter, $form_id); ?>>
                                <?php echo esc_html($form_id); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="study_group">
                        <option value="">All Groups</option>
                        <?php foreach ($study_groups as $group): ?>
                            <option value="<?php echo esc_attr($group); ?>" 
                                    <?php selected($study_group_filter, $group); ?>>
                                <?php echo esc_html($group); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>">
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>">

                    <input type="submit" class="button" value="Apply Filters">
                    
                    <?php if (!empty($form_id_filter) || !empty($study_group_filter) || !empty($date_from) || !empty($date_to)): ?>
                        <a href="<?php echo admin_url('admin.php?page=survey-responses'); ?>" class="button">Reset</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Export -->
            <div class="alignright">
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <?php wp_nonce_field('export_responses_nonce'); ?>
                    <input type="hidden" name="action" value="export_survey_responses">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-download"></span>
                        Export to CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Results Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Question</th>
                    <th>Response</th>
                    <th>User ID</th>
                    <th>Study Group</th>
                    <th>Form ID</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($results): ?>
                    <?php foreach ($results as $row): ?>
                        <tr>
                            <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row->created_at))); ?></td>
                            <td><?php echo esc_html($row->question_title); ?></td>
                            <td><?php echo esc_html($row->response); ?></td>
                            <td><?php echo esc_html($row->token); ?></td>
                            <td><?php echo esc_html($row->study_group ?: 'N/A'); ?></td>
                            <td><?php echo esc_html($row->form_id); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No responses found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <style>
        .survey-analytics {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        .stat-box {
            background: #fff;
            padding: 20px;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            flex: 1;
            text-align: center;
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #37A0EA;
        }
        .survey-filters {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .survey-filters select,
        .survey-filters input[type="date"] {
            max-width: 200px;
        }
    </style>
    <?php
}