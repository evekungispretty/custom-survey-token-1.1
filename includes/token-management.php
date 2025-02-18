<?php
// File: includes/token-management.php
if (!defined('ABSPATH')) {
    exit;
}
function increment_login_count($token) {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    
    return $wpdb->query($wpdb->prepare("
        UPDATE $tokens_table 
        SET login_count = login_count + 1,
            last_used = NOW()
        WHERE token = %s
    ", $token));
}

function verify_token_status($token) {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    
    return $wpdb->get_row($wpdb->prepare("
        SELECT *, 
               UNIX_TIMESTAMP(expires_at) * 1000 as expiry_timestamp
        FROM $tokens_table 
        WHERE token = %s 
        AND expires_at > NOW()
        AND is_used = 0
    ", $token));
}
function add_username_column() {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    
    // First, check if the column already exists
    $check_column = $wpdb->get_results("SHOW COLUMNS FROM $tokens_table LIKE 'user_name'");
    
    if (empty($check_column)) {
        // Add the column if it doesn't exist
        $wpdb->query("ALTER TABLE $tokens_table 
                     ADD COLUMN user_name varchar(100) AFTER token");
        error_log('Added user_name column to tokens table');
    } else {
        error_log('user_name column already exists');
    }
}

// Run this once on admin page load
add_action('admin_init', 'add_username_column');

function display_token_management() {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';

    //Token Creation

    if (isset($_POST['create_token']) && check_admin_referer('create_token_nonce')) {

    
    $token = strtoupper(sanitize_text_field($_POST['manual_token']));
    $user_name = sanitize_text_field($_POST['user_name']);
    $study_group = sanitize_text_field($_POST['study_group']);
    $expiry_days = intval($_POST['expiry_days']);
    $expires_at = date('Y-m-d H:i:s', strtotime("+$expiry_days days"));


        // Validate token format
        if (!preg_match('/^[A-Z]\d{4}$/', $token)) {
            add_settings_error(
                'tokens_updated',
                'token_invalid',
                'Invalid token format. Must be one letter followed by four numbers (e.g., A1234).',
                'error'
            );
        } else {
            // Check if token already exists
            $existing_token = $wpdb->get_var($wpdb->prepare(
                "SELECT token FROM $tokens_table WHERE token = %s",
                $token
            ));

            if ($existing_token) {
                add_settings_error(
                    'tokens_updated',
                    'token_exists',
                    'This token already exists. Please use a different combination.',
                    'error'
                );
            } else {
                $result = $wpdb->insert($tokens_table, [
                    'token' => $token,
                    'user_name' => $user_name,    // Add this line
                    'study_group' => $study_group,
                    'expires_at' => $expires_at,
                    'is_used' => 0
                ]);

                if ($result) {
                    add_settings_error(
                        'tokens_updated',
                        'token_created',
                        'Token created successfully.',
                        'updated'
                    );
                }
            }
        }
    }

        // Handle single token deletion
    if (isset($_POST['action']) && $_POST['action'] === 'delete_single_token') {
        if (!isset($_POST['delete_token_nonce']) || 
            !wp_verify_nonce($_POST['delete_token_nonce'], 'delete_single_token')) {
            add_settings_error(
                'tokens_updated',
                'token_delete_failed',
                'Security check failed.',
                'error'
            );
        } else {
            $token = sanitize_text_field($_POST['token']);
            $deleted = $wpdb->delete(
                $tokens_table,
                array('token' => $token),
                array('%s')
            );
            
            if ($deleted) {
                add_settings_error(
                    'tokens_updated',
                    'token_deleted',
                    'ID deleted successfully.',
                    'updated'
                );
            } else {
                add_settings_error(
                    'tokens_updated',
                    'token_delete_failed',
                    'Failed to delete ID.',
                    'error'
                );
            }
        }
    }

    // Handle token deletion
    if (isset($_POST['delete_tokens']) && check_admin_referer('delete_tokens_nonce')) {
        $condition = sanitize_text_field($_POST['delete_condition']);
        
        switch ($condition) {
            case 'expired':
                $deleted = $wpdb->query("DELETE FROM $tokens_table WHERE expires_at < NOW()");
                break;
            case 'used':
                $deleted = $wpdb->query("DELETE FROM $tokens_table WHERE is_used = 1");
                break;
            case 'all':
                $deleted = $wpdb->query("DELETE FROM $tokens_table");
                break;
            default:
                $deleted = 0;
        }
        
        if ($deleted !== false) {
            add_settings_error(
                'tokens_updated',
                'tokens_deleted',
                sprintf('%d tokens deleted successfully.', $deleted),
                'updated'
            );
        }
    }

    // Handle bulk actions
    if (isset($_POST['bulk_action']) && isset($_POST['token_ids']) && check_admin_referer('bulk_token_actions')) {
        $action = sanitize_text_field($_POST['bulk_action']);
        $token_ids = array_map('sanitize_text_field', $_POST['token_ids']);
        
        switch ($action) {
            case 'delete':
                $wpdb->query("DELETE FROM $tokens_table WHERE token IN ('" . implode("','", $token_ids) . "')");
                add_settings_error('tokens_updated', 'tokens_deleted', 'Selected tokens deleted.', 'updated');
                break;
            case 'deactivate':
                $wpdb->query("UPDATE $tokens_table SET is_used = 1 WHERE token IN ('" . implode("','", $token_ids) . "')");
                add_settings_error('tokens_updated', 'tokens_deactivated', 'Selected tokens deactivated.', 'updated');
                break;
        }
    }

    settings_errors('tokens_updated');

    // Get current token counts
    $total_logins = $wpdb->get_var("SELECT SUM(login_count) FROM $tokens_table");
$average_logins = $wpdb->get_var("
    SELECT ROUND(AVG(login_count), 1) 
    FROM $tokens_table 
    WHERE login_count > 0
");
    $total_tokens = $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table");
    $active_tokens = $wpdb->get_var("SELECT COUNT(*) FROM $tokens_table WHERE expires_at > NOW() AND is_used = 0");
    ?>
    <div class="wrap">
        <h1>ID Management</h1>

        <!-- Token Statistics -->
        <div class="token-stats">
            <div class="stat-box">
                <h3>Total IDs</h3>
                <span class="stat-number"><?php echo number_format_i18n($total_tokens); ?></span>
            </div>
            <div class="stat-box">
                <h3>Active IDs</h3>
                <span class="stat-number"><?php echo number_format_i18n($active_tokens); ?></span>
            </div>
            <div class="stat-box">
                <h3>Total Logins</h3>
                <span class="stat-number"><?php echo number_format_i18n($total_logins); ?></span>
            </div>
            <div class="stat-box">
                <h3>Average Logins per Active User</h3>
                <span class="stat-number"><?php echo $average_logins; ?></span>
            </div>
        </div>
<div class="token-card-wrap">
        <!-- Create Manual Token Form -->
        <div class="card">
        <h2>Create New ID</h2>
        <form method="post" action="">
            <?php wp_nonce_field('create_token_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="manual_token">ID</label></th>
                    <td>
                        <input type="text" 
                               name="manual_token" 
                               id="manual_token" 
                               required 
                               placeholder="A1234" 
                               maxlength="5"
                               style="text-transform: uppercase;">
                        <p class="description">Enter one letter followed by four numbers (e.g., A1234)</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="user_name">Participant Name</label></th>
                    <td>
                        <input type="text" 
                               name="user_name" 
                               id="user_name" 
                               class="regular-text" 
                               placeholder="John Doe">
                        <p class="description">Enter the participant's name for identification purposes</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="study_group">Study Group</label></th>
                    <td>
                        <input type="text" name="study_group" id="study_group" class="regular-text">
                        <p class="description">Optional identifier for grouping participants</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="expiry_days">Expiry Days</label></th>
                    <td>
                        <input type="number" name="expiry_days" id="expiry_days" 
                               min="1" value="365" required>
                        <p class="description">Number of days until token expires</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="create_token" class="button button-primary" 
                       value="Create ID">
            </p>
        </form>
    </div>

        <!-- Delete Tokens Form -->
<div class="card">
    <h2>Delete IDs</h2>
    <form method="post" action="" onsubmit="return confirm('Are you sure? This action cannot be undone.');">
        <?php wp_nonce_field('delete_tokens_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="delete_condition">Delete Condition</label></th>
                <td>
                    <select name="delete_condition" id="delete_condition" required>
                        <option value="expired">Expired IDs</option>
                        <option value="used">Used Tokens</option>
                        <option value="all">All Tokens</option>
                    </select>
                    <p class="description">Select which tokens to delete</p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="delete_tokens" class="button button-secondary" 
                   value="Delete IDs">
        </p>
    </form>
</div>
</div>  
<!-- Token table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="check-column"><input type="checkbox" id="select-all-tokens"></th>
                        <th>ID</th>
                        <th>Participant Name</th>
                        <th>Study Group</th>
                        <th>Login Count</th>
                        <th>Created</th>
                        <th>Expires</th>
                        <th>Status</th>
                        <th>Actions</th>  <!-- New column for actions -->
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $tokens = $wpdb->get_results("
                        SELECT *, 
                        CASE 
                            WHEN is_used = 1 THEN 'Used'
                            WHEN expires_at < NOW() THEN 'Expired'
                            ELSE 'Active'
                        END as status
                        FROM $tokens_table 
                        ORDER BY created_at DESC
                    ");

                    if ($tokens):
                        foreach ($tokens as $token):
                            $status_class = $token->status === 'Active' ? 'status-active' : 'status-inactive';
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="token_ids[]" value="<?php echo esc_attr($token->token); ?>">
                                </td>
                                <td><?php echo esc_html($token->token); ?></td>
                                <td><?php echo esc_html($token->user_name ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($token->study_group ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($token->login_count); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->created_at))); ?></td>
                                <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($token->expires_at))); ?></td>
                                <td class="<?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($token->status); ?>
                                </td>
                                <td>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this ID?');">
                                        <?php wp_nonce_field('delete_single_token', 'delete_token_nonce'); ?>
                                        <input type="hidden" name="action" value="delete_single_token">
                                        <input type="hidden" name="token" value="<?php echo esc_attr($token->token); ?>">
                                        <button type="submit" class="button button-small button-link-delete">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach;
                    else: ?>
                        <tr>
                            <td colspan="8">No tokens found.</td>  <!-- Updated colspan to match new column count -->
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
           
        </form>
    </div>



    <style>

        .token-stats,  .token-card-wrap  {
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
        .card {
            max-width: 800px;
            margin: 20px 0;
            padding: 20px;
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .status-active {
            color: #4CAF50;
        }
        .status-inactive {
            color: #F44336;
        }
        .button-link-delete {
            color: #dc3232;
        }

        .button-link-delete:hover {
            color: #a00;
            background: #f3f3f3;
        }
            </style>

    <script>
    jQuery(document).ready(function($) {
        // Handle select all checkbox
        $('#select-all-tokens').on('change', function() {
            $('input[name="token_ids[]"]').prop('checked', $(this).prop('checked'));
        });

        // Update select all when individual checkboxes change
        $('input[name="token_ids[]"]').on('change', function() {
            var allChecked = $('input[name="token_ids[]"]').length === 
                            $('input[name="token_ids[]"]:checked').length;
            $('#select-all-tokens').prop('checked', allChecked);
        });
    });
    </script>
    <?php
}

// Function to mark token as used
function mark_token_used($token) {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    
    return $wpdb->update(
        $tokens_table,
        ['is_used' => 1, 'last_used' => current_time('mysql')],
        ['token' => $token],
        ['%d', '%s'],
        ['%s']
    );
}