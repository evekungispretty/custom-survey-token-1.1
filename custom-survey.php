<?php
/*
Plugin Name: Custom Survey Manager
Description: Create and manage custom surveys with token-based access
Version: 2.0
*/
if (!defined('ABSPATH')) {
    exit;
}

define('SURVEY_PLUGIN_VERSION', '2.0');
define('SURVEY_PLUGIN_URL', plugins_url('', __FILE__));

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/display-responses.php';
require_once plugin_dir_path(__FILE__) . 'meta-box.php';
require_once plugin_dir_path(__FILE__) . 'includes/token-management.php';
require_once plugin_dir_path(__FILE__) . 'templates/token-entry-page.php';


// Add this near the top of your plugin file to detect the site URL
$site_url = get_site_url();
define('THRIVES_BASE_URL', $site_url);

// Debug function for REST API
function debug_rest_api($message) {
    if (WP_DEBUG) {
        error_log('THRIVES REST API: ' . $message);
    }
}

// Single REST API registration function
function register_thrives_rest_routes() {
    debug_rest_api('Registering REST routes...');
    
    // Add CORS headers for REST API endpoints
    add_action('rest_api_init', function() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', function($value) {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization');
            return $value;
        });
    });

    // Register the token verification endpoint with proper error handling
    register_rest_route('thrives/v1', '/verify-token', array(
        'methods' => array('POST', 'OPTIONS'),
        'callback' => 'handle_token_verification_api',
        'permission_callback' => '__return_true',
        'args' => array(
            'token' => array(
                'required' => true,
                'validate_callback' => function($param) {
                    return preg_match('/^[A-Z]\d{4}$/', $param);
                }
            )
        )
    ));

    // Add fallback AJAX endpoint for when REST fails
    add_action('wp_ajax_verify_study_token', 'handle_token_verification_ajax');
    add_action('wp_ajax_nopriv_verify_study_token', 'handle_token_verification_ajax');
}
add_action('rest_api_init', 'register_thrives_rest_routes');

// Update the JavaScript configuration to include the REST API base URL
function update_survey_config($config) {
    $rest_url = rest_url('thrives/v1');
    debug_rest_api('REST URL: ' . $rest_url);
    
    $config['restUrl'] = $rest_url;
    $config['restNonce'] = wp_create_nonce('wp_rest');
    
    return $config;
}
add_filter('survey_system_config', 'update_survey_config');

// Modified token verification handler
function handle_token_verification_api($request) {
    debug_rest_api('Token verification API endpoint called');
    
    // Log the request details for debugging
    if (WP_DEBUG) {
        error_log('REST Request Details: ' . print_r($request->get_params(), true));
        error_log('Headers: ' . print_r(getallheaders(), true));
    }
    
    try {
        $params = $request->get_json_params();
        if (empty($params)) {
            $params = $request->get_params();
        }
        
        $token = isset($params['token']) ? sanitize_text_field($params['token']) : '';
        $isInitialLogin = isset($params['isInitialLogin']) ? (bool)$params['isInitialLogin'] : false;
        
        if (empty($token)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'ID is required',
                'code' => 'token_required'
            ), 400);
        }

        // Verify token status
        $token_data = verify_token_status($token);

        if (!$token_data) {
            debug_rest_api('Token verification failed: Invalid or expired token');
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Invalid or expired ID',
                'code' => 'invalid_token'
            ), 401);
        }

        // Only increment login count on initial login
        if ($isInitialLogin) {
            increment_login_count($token);
            debug_rest_api('Initial login: Token verified and login count incremented: ' . $token);
        }

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'ID verified successfully',
            'study_group' => $token_data->study_group,
            'expiry' => $token_data->expiry_timestamp
        ), 200);
        
    } catch (Exception $e) {
        debug_rest_api('Token verification error: ' . $e->getMessage());
        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'An error occurred during verification',
            'code' => 'server_error',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ), 500);
    }
}

// Add this function to your plugin or theme
function check_rest_api_status() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $problems = array();
    $status = array();

    // Check permalinks
    $permalink_structure = get_option('permalink_structure');
    $status[] = "Permalink structure: " . ($permalink_structure ? $permalink_structure : 'Plain');
    if (!$permalink_structure) {
        $problems[] = 'Permalinks are set to plain. Please change them to Post name or another option.';
    }

    // Check REST API availability
    $rest_url = get_rest_url(null, 'thrives/v1/verify-token');
    $status[] = "REST API URL: " . $rest_url;

    // Test external accessibility
    $response = wp_remote_post($rest_url, array(
        'body' => json_encode(array('token' => 'A1234')),
        'headers' => array('Content-Type' => 'application/json'),
        'timeout' => 15
    ));

    if (is_wp_error($response)) {
        $problems[] = 'REST API is not accessible: ' . $response->get_error_message();
    } else {
        $status[] = "REST API Response Code: " . wp_remote_retrieve_response_code($response);
    }

    // Output status and problems
    echo '<div class="wrap">';
    echo '<h2>REST API Status Check</h2>';
    
    echo '<h3>Status Information:</h3>';
    echo '<ul>';
    foreach ($status as $info) {
        echo '<li>' . esc_html($info) . '</li>';
    }
    echo '</ul>';

    if (!empty($problems)) {
        echo '<div class="notice notice-error">';
        echo '<h3>Issues Found:</h3>';
        echo '<ul>';
        foreach ($problems as $problem) {
            echo '<li>' . esc_html($problem) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    echo '</div>';
}

// Add this to functions.php or your plugin file
add_action('rest_api_init', function() {
    add_filter('rest_url_prefix', function($prefix) {
        return 'wp-json';  // Ensure consistent REST URL prefix
    });
});
// Add module taxonomy for questions
function register_module_taxonomy() {
    $labels = array(
        'name'              => 'Modules',
        'singular_name'     => 'Module',
        'search_items'      => 'Search Modules',
        'all_items'         => 'All Modules',
        'parent_item'       => 'Parent Module',
        'parent_item_colon' => 'Parent Module:',
        'edit_item'         => 'Edit Module',
        'update_item'       => 'Update Module',
        'add_new_item'      => 'Add New Module',
        'new_item_name'     => 'New Module Name',
        'menu_name'         => 'Modules'
    );

    $args = array(
        'hierarchical'      => true, // Make it hierarchical like categories
        'labels'            => $labels,
        'show_ui'          => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'          => array('slug' => 'question-module'),
        'show_in_rest'     => true, // Enable Gutenberg editor support
    );

    register_taxonomy('question_module', array('survey_question'), $args);
}
add_action('init', 'register_module_taxonomy', 0);

// Add custom meta box for module order
function add_module_order_meta_box() {
    add_meta_box(
        'module_order_meta_box',
        'Module Order',
        'render_module_order_meta_box',
        'survey_question',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_module_order_meta_box');

// Render the module order meta box
function render_module_order_meta_box($post) {
    $module_order = get_post_meta($post->ID, '_module_order', true);
    wp_nonce_field('module_order_meta_box', 'module_order_nonce');
    ?>
    <p>
        <label for="module_order">Question Order in Module:</label>
        <input type="number" 
               id="module_order" 
               name="module_order" 
               value="<?php echo esc_attr($module_order); ?>" 
               class="small-text"
               min="0"
               step="1">
    </p>
    <p class="description">
        Set the order of this question within its module. Lower numbers appear first.
    </p>
    <?php
}

// Save the module order meta
function save_module_order_meta($post_id) {
    // Verify nonce
    if (!isset($_POST['module_order_nonce']) || 
        !wp_verify_nonce($_POST['module_order_nonce'], 'module_order_meta_box')) {
        return;
    }

    // Check autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check permissions
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // Save module order
    if (isset($_POST['module_order'])) {
        $order = intval($_POST['module_order']);
        update_post_meta($post_id, '_module_order', $order);
    }
}
add_action('save_post_survey_question', 'save_module_order_meta');

// Add custom admin columns
function add_module_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['module'] = 'Module';
            $new_columns['module_order'] = 'Order';
        }
    }
    return $new_columns;
}
add_filter('manage_survey_question_posts_columns', 'add_module_columns');

// Populate custom columns
function populate_module_columns($column, $post_id) {
    switch ($column) {
        case 'module':
            $terms = get_the_terms($post_id, 'question_module');
            if ($terms && !is_wp_error($terms)) {
                $module_names = array();
                foreach ($terms as $term) {
                    $module_names[] = $term->name;
                }
                echo esc_html(implode(', ', $module_names));
            }
            break;
        case 'module_order':
            $order = get_post_meta($post_id, '_module_order', true);
            echo $order ? esc_html($order) : '—';
            break;
    }
}
add_action('manage_survey_question_posts_custom_column', 'populate_module_columns', 10, 2);

// Make the module order column sortable
function make_module_order_sortable($columns) {
    $columns['module_order'] = 'module_order';
    return $columns;
}
add_filter('manage_edit-survey_question_sortable_columns', 'make_module_order_sortable');

// Handle custom sorting
function handle_module_order_sorting($query) {
    if (!is_admin()) {
        return;
    }

    $orderby = $query->get('orderby');
    if ('module_order' === $orderby) {
        $query->set('meta_key', '_module_order');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'handle_module_order_sorting');

// Add menu item for the diagnostic tool
add_action('admin_menu', function() {
    add_submenu_page(
        'survey-responses',
        'REST API Status',
        'REST API Status',
        'manage_options',
        'rest-api-status',
        'check_rest_api_status'
    );
});

// Create plugin tables on activation
register_activation_hook(__FILE__, 'survey_plugin_activate');
function survey_plugin_activate() {
    global $wpdb;
    
    // Create survey responses table
    $response_table = $wpdb->prefix . 'survey_responses';
    $wpdb->query("DROP TABLE IF EXISTS $response_table");
    
    $sql_responses = "CREATE TABLE $response_table (
        id int NOT NULL AUTO_INCREMENT,
        question_id bigint(20) NOT NULL,
        response text NOT NULL,
        token varchar(5) NOT NULL, 
        form_id varchar(50),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY question_id (question_id),
        KEY token (token)
    ) " . $wpdb->get_charset_collate();
    
    // Create tokens table
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    $wpdb->query("DROP TABLE IF EXISTS $tokens_table");
    
    $sql_tokens = "CREATE TABLE $tokens_table (
        token varchar(5) NOT NULL,
        user_name varchar(100),
        study_group varchar(50),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        expires_at datetime,
        last_used datetime DEFAULT NULL,
        is_used tinyint(1) DEFAULT 0,
        PRIMARY KEY (token)
    ) " . $wpdb->get_charset_collate();

    // Create progress tracking table
    $progress_table = $wpdb->prefix . 'survey_progress';
    $wpdb->query("DROP TABLE IF EXISTS $progress_table");

    $sql_progress = "CREATE TABLE $progress_table (
        id int NOT NULL AUTO_INCREMENT,
        token varchar(5) NOT NULL,
        form_id varchar(50) NOT NULL,
        current_page_id bigint(20),
        current_page_url varchar(255),
        module_progress text,
        last_visited_url varchar(255),
        last_visited_timestamp datetime,
        responses longtext,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP,
        is_complete tinyint(1) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY token_form (token, form_id),
        KEY token (token)
    ) " . $wpdb->get_charset_collate();
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_responses);
    dbDelta($sql_tokens);
    dbDelta($sql_progress);

}

// Register Custom Post Type
add_action('init', 'register_survey_questions');
function register_survey_questions() {
    register_post_type('survey_question', [
        'public' => true,
        'labels' => [
            'name' => 'Quiz Questions',
            'singular_name' => 'Quiz Question',
            'add_new' => 'Add New Question',
            'add_new_item' => 'Add New Question',
            'edit_item' => 'Edit Question'
        ],
        'supports' => ['title'],
        'menu_icon' => 'dashicons-list-view',
        'show_in_menu' => true
    ]);
}

// Add admin menus
function add_survey_admin_menus() {
    // Main Survey Responses page
    add_menu_page(
        'User Management',        // Page title
        'User Management',        // Menu title
        'manage_options',        // Capability required
        'survey-responses',      // Menu slug
        'display_survey_responses', // Function to display the page
        'dashicons-feedback',    // Icon
        30                      // Position
    );

    // Add the parent page as a submenu item too
    add_submenu_page(
        'survey-responses',      // Parent slug
        'All Responses',         // Page title
        'All Responses',         // Menu title
        'manage_options',        // Capability required
        'survey-responses',      // Menu slug (same as parent)
        'display_survey_responses' // Function to display the page
    );

    // Token Management submenu
    add_submenu_page(
        'survey-responses',      // Parent slug
        'ID Management',         // Page title
        'ID Management',         // Menu title
        'manage_options',        // Capability required
        'survey-tokens',         // Menu slug
        'display_token_management' // Function to display the page
    );

    // Only one REST API Status submenu
    add_submenu_page(
        'survey-responses',      // Parent slug
        'REST API Status',       // Page title
        'REST API Status',       // Menu title
        'manage_options',        // Capability required
        'rest-api-status',      // Menu slug
        'check_rest_api_status'  // Function to display the page
    );

    // Remove duplicate REST API Status entry if it exists
    global $submenu;
    if (isset($submenu['survey-responses'])) {
        foreach ($submenu['survey-responses'] as $key => $item) {
            if ($item[2] === 'rest-api-status' && $key > 0) {
                unset($submenu['survey-responses'][$key]);
            }
        }
    }
}
add_action('admin_menu', 'add_survey_admin_menus');

// // Add a check before registering the menu
// add_action('plugins_loaded', function() {
//     if (function_exists('display_survey_responses')) {
//         add_action('admin_menu', 'add_survey_admin_menus');
//     } else {
//         error_log('Failed to register survey menus - display_survey_responses not found');
//     }
// });

// Register AJAX handler for login message
add_action('wp_ajax_get_login_message', 'handle_get_login_message');
add_action('wp_ajax_nopriv_get_login_message', 'handle_get_login_message');

function handle_get_login_message() {
    debug_to_console('Login message handler triggered');
    
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'survey_token_nonce')) {
        debug_to_console('Nonce verification failed');
        wp_send_json_error(array(
            'message' => 'Security check failed',
            'debug' => 'Nonce verification failed'
        ));
        return;
    }

    // Get template path
    $template_path = plugin_dir_path(__FILE__) . 'templates/login-message.php';
    debug_to_console('Looking for template at: ' . $template_path);

    // Check if file exists
    if (!file_exists($template_path)) {
        debug_to_console('Template file not found at: ' . $template_path);
        wp_send_json_error(array(
            'message' => 'Template file not found',
            'debug' => 'Template path: ' . $template_path
        ));
        return;
    }

    // Include the template file
    require_once $template_path;
    
    if (!function_exists('render_login_message')) {
        debug_to_console('render_login_message function not found');
        wp_send_json_error(array(
            'message' => 'Template function not found',
            'debug' => 'render_login_message function missing'
        ));
        return;
    }

    // Get the rendered message
    $message = render_login_message();
    
    debug_to_console('Message rendered successfully');
    
    wp_send_json_success(array(
        'message' => $message,
        'debug' => 'Template rendered successfully'
    ));
}



function survey_enqueue_scripts() {
    // First, enqueue all styles
    wp_enqueue_style(
        'survey-styles', 
        SURVEY_PLUGIN_URL . '/assets/css/survey.css'
    );

    // Ensure jQuery is loaded as a main dependency
    wp_enqueue_script('jquery');

    // Create nonces with consistent naming
    $survey_response_nonce = wp_create_nonce('get_survey_responses');
    $token_nonce = wp_create_nonce('survey_token_nonce');
    $progress_nonce = wp_create_nonce('progress_nonce');
    $rest_nonce = wp_create_nonce('wp_rest');

    // Create a unified configuration object that all scripts can access
    $survey_system_config = array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'homeUrl' => home_url(),
        'currentUrl' => get_permalink(),
        'isDebug' => WP_DEBUG,
        'tokenEntryUrl' => get_permalink(get_option('survey_token_entry_page_id')),
        'tokenNonce' => $token_nonce,
        'surveyNonce' => $survey_response_nonce,  // Updated to use our consistent nonce
        'progressNonce' => $progress_nonce,
        'restUrl' => rest_url('thrives/v1'),
        'restNonce' => $rest_nonce,
        'moduleId' => get_the_ID(),
        'modulesHomeUrl' => home_url('/modules-home/'),
        'defaultFormId' => 'default',
        'protectedPaths' => array(
            '/modules-home/module-',
            '/my-progress/',
            '/modules-home/'
        )
    );

    // Debug the REST URL if debugging is enabled
    if (WP_DEBUG) {
        error_log('THRIVES REST URL: ' . $survey_system_config['restUrl']);
    }

    // First, enqueue access control script as it needs to run first
    wp_enqueue_script(
        'access-control',
        SURVEY_PLUGIN_URL . '/assets/js/access-control.js',
        array('jquery'),
        SURVEY_PLUGIN_VERSION,
        true
    );
    
    // Main survey script
    wp_enqueue_script(
        'survey-script', 
        SURVEY_PLUGIN_URL . '/assets/js/survey.js', 
        array('jquery', 'access-control'),
        SURVEY_PLUGIN_VERSION, 
        true
    );

    // Progress tracking functionality
    wp_enqueue_script(
        'progress-tracking', 
        SURVEY_PLUGIN_URL . '/assets/js/progress-tracking.js', 
        array('jquery', 'survey-script'), 
        SURVEY_PLUGIN_VERSION, 
        true
    );

    // Survey responses display
    wp_enqueue_script(
        'survey-responses', 
        SURVEY_PLUGIN_URL . '/assets/js/survey-responses.js', 
        array('jquery', 'access-control'), 
        SURVEY_PLUGIN_VERSION, 
        true
    );

    // Token handling script
    wp_enqueue_script(
        'survey-token',
        SURVEY_PLUGIN_URL . '/assets/js/survey-token.js',
        array('jquery', 'access-control'),
        SURVEY_PLUGIN_VERSION,
        true
    );

    // Localize the unified configuration for access control
    wp_localize_script('access-control', 'surveySystem', $survey_system_config);

    // Localize script-specific configurations for backward compatibility
    wp_localize_script('survey-script', 'surveyConfig', array(
        'ajaxurl' => $survey_system_config['ajaxurl'],
        'nonce' => $survey_system_config['surveyNonce'],
        'tokenEntryUrl' => $survey_system_config['tokenEntryUrl'],
        'homeUrl' => $survey_system_config['homeUrl'],
        'isDebug' => $survey_system_config['isDebug']
    ));

    wp_localize_script('progress-tracking', 'progressData', array(
        'ajaxurl' => $survey_system_config['ajaxurl'],
        'nonce' => $survey_system_config['progressNonce'],
        'isDebug' => $survey_system_config['isDebug'],
        'currentUrl' => $survey_system_config['currentUrl'],
        'moduleId' => $survey_system_config['moduleId'],
        'homeUrl' => $survey_system_config['modulesHomeUrl'],
        'defaultFormId' => $survey_system_config['defaultFormId']
    ));

    // Localize survey responses with consistent nonce
    wp_localize_script('survey-responses', 'surveyResponseConfig', array(
        'ajaxurl' => $survey_system_config['ajaxurl'],
        'nonce' => $survey_response_nonce,
        'debug' => $survey_system_config['isDebug']
    ));
}
add_action('wp_enqueue_scripts', 'survey_enqueue_scripts', 10);


// Add shortcode for displaying survey
add_shortcode('display_survey', 'render_survey_form');
function render_survey_form($atts) {
    $atts = shortcode_atts(array(
        'questions' => '',
        'form_id' => 'default',
        'next_page' => '',
        'token_page' => '' // URL for token entry page
    ), $atts);

    // Check if we have questions
    if (!empty($atts['questions'])) {
        $question_ids = array_map('trim', explode(',', $atts['questions']));
        $questions = get_posts([
            'post_type' => 'survey_question',
            'posts_per_page' => -1,
            'post__in' => $question_ids,
            'orderby' => 'post__in'
        ]);
    } else {
        $questions = get_posts([
            'post_type' => 'survey_question',
            'posts_per_page' => -1,
            'orderby' => 'menu_order',
            'order' => 'ASC'
        ]);
    }

    ob_start();
    include plugin_dir_path(__FILE__) . 'templates/survey-form.php';
    return ob_get_clean();
}

// Add shortcode for token entry
add_shortcode('survey_token_entry', 'render_survey_token_entry');
function render_survey_token_entry($atts) {
    $atts = shortcode_atts(array(
        'redirect' => '',
        'form_id' => 'default'
    ), $atts);

    return render_token_entry();
}


// Enqueue token scripts and localize data
function enqueue_survey_token_scripts() {
    if (has_shortcode(get_post()->post_content, 'survey_token_entry_page') || 
        has_shortcode(get_post()->post_content, 'display_survey')) {
        
        wp_enqueue_script(
            'survey-token',
            SURVEY_PLUGIN_URL . '/assets/js/survey-token.js',
            array('jquery'),
            '1.0',
            true
        );

        wp_localize_script('survey-token', 'surveyTokenData', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('survey_token_nonce'), // Changed to match verification
            'tokenEntryUrl' => get_permalink(get_option('survey_token_entry_page_id')),
            'homeUrl' => home_url(),
            'redirect_url' => get_permalink(get_option('survey_token_entry_page_id'))
        ));
    }
}
add_action('wp_enqueue_scripts', 'enqueue_survey_token_scripts');

// Add settings to specify token entry page
function register_survey_settings() {
    register_setting('survey_options', 'survey_token_entry_page_id');
    
    add_settings_section(
        'survey_token_settings',
        'Survey Token Settings',
        null,
        'survey-settings'
    );
    
    add_settings_field(
        'survey_token_entry_page',
        'Token Entry Page',
        'render_token_page_setting',
        'survey-settings',
        'survey_token_settings'
    );
}
add_action('admin_init', 'register_survey_settings');

function render_token_page_setting() {
    $page_id = get_option('survey_token_entry_page_id');
    wp_dropdown_pages(array(
        'name' => 'survey_token_entry_page_id',
        'selected' => $page_id,
        'show_option_none' => 'Select a page...'
    ));
    echo '<p class="description">Select the page where users will enter their token. Add the shortcode [survey_token_entry_page] to this page.</p>';
}

function handle_verify_study_token() {
    try {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'survey_token_nonce')) {
            wp_send_json_error(array(
                'message' => 'Security check failed. Please refresh the page and try again.'
            ));
            return;
        }

        $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
        $isInitialLogin = isset($_POST['isInitialLogin']) ? (bool)$_POST['isInitialLogin'] : false;
        
        if (empty($token)) {
            wp_send_json_error(array('message' => 'ID is required'));
            return;
        }

        // Use helper function to verify token
        $token_data = verify_token_status($token);

        if (!$token_data) {
            wp_send_json_error(array('message' => 'Invalid or expired ID'));
            return;
        }

        // Only increment login count on initial login
        if ($isInitialLogin) {
            increment_login_count($token);
            error_log('Initial login: Token verified and login count incremented for: ' . $token);
        }

        wp_send_json_success(array(
            'message' => 'ID verified successfully',
            'study_group' => $token_data->study_group,
            'expiry' => $token_data->expiry_timestamp
        ));

    } catch (Exception $e) {
        error_log('Token verification error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'An error occurred during verification. Please try again.'
        ));
    }
}

// Make sure these hooks are registered
add_action('wp_ajax_verify_study_token', 'handle_verify_study_token');
add_action('wp_ajax_nopriv_verify_study_token', 'handle_verify_study_token');

// Handle form submission
add_action('wp_ajax_submit_survey', 'handle_survey_submission');
add_action('wp_ajax_nopriv_submit_survey', 'handle_survey_submission');
function handle_survey_submission() {
    global $wpdb;
    $response_table = $wpdb->prefix . 'survey_responses';
    $tokens_table = $wpdb->prefix . 'survey_tokens';



    if (!isset($_POST['survey_nonce']) || !wp_verify_nonce($_POST['survey_nonce'], 'submit_survey_nonce')) {
        error_log('Nonce verification failed');
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }

    $token = sanitize_text_field($_POST['token']);
    error_log('Token received: ' . $token);
    
    // Verify token
    $token_valid = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tokens_table WHERE token = %s AND expires_at > NOW()",
        $token
    ));

    if (!$token_valid) {
        error_log('Token validation failed');
        wp_send_json_error(['message' => 'Invalid or expired token']);
        return;
    }

    error_log('Token validated successfully');

    try {
        $form_id = isset($_POST['form_id']) ? sanitize_text_field($_POST['form_id']) : 'default';
        $inserted = 0;
        
        foreach($_POST as $key => $value) {
            if(strpos($key, 'q_') === 0) {
                $question_id = (int)substr($key, 2);
                
                if(is_array($value)) {
                    $value = implode(', ', array_map('sanitize_text_field', $value));
                } else {
                    $value = sanitize_text_field($value);
                }

                $data = array(
                    'question_id' => $question_id,
                    'response' => $value,
                    'token' => $token,
                    'form_id' => $form_id,
                    'created_at' => current_time('mysql')
                );


                $result = $wpdb->insert($response_table, $data);
                
                if($result !== false) {
                    $inserted++;
                } else {
                    error_log('Insert failed. SQL Error: ' . $wpdb->last_error);
                }
            }
        }


        if($inserted > 0) {
            wp_send_json_success(['message' => 'Thank you for your response!']);
        } else {
            wp_send_json_error(['message' => 'No responses were saved']);
        }

    } catch (Exception $e) {
        error_log('Survey submission error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
}

// Handle export
add_action('admin_post_export_survey_responses', 'handle_survey_export');
function handle_survey_export() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    if (!isset($_POST['export_responses']) || 
        !isset($_POST['_wpnonce']) || 
        !wp_verify_nonce($_POST['_wpnonce'], 'export_responses_nonce')) {
        wp_die('Invalid request');
    }

    global $wpdb;
    $response_table = $wpdb->prefix . 'survey_responses';
    $tokens_table = $wpdb->prefix . 'survey_tokens';

    $where = array();
    $where_values = array();

    if (!empty($_POST['form_id_export'])) {
        $where[] = 'r.form_id = %s';
        $where_values[] = sanitize_text_field($_POST['form_id_export']);
    }
    if (!empty($_POST['study_group_export'])) {
        $where[] = 't.study_group = %s';
        $where_values[] = sanitize_text_field($_POST['study_group_export']);
    }

    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $query = "
        SELECT 
            r.created_at as 'Date',
            p.post_title as 'Question',
            r.response as 'Response',
            r.token as 'Token',
            t.study_group as 'Study Group',
            r.form_id as 'Form ID'
        FROM $response_table r
        LEFT JOIN {$wpdb->posts} p ON r.question_id = p.ID
        LEFT JOIN $tokens_table t ON r.token = t.token
        $where_clause
        ORDER BY r.created_at DESC
    ";

    if (!empty($where_values)) {
        $query = $wpdb->prepare($query, $where_values);
    }

    $results = $wpdb->get_results($query, ARRAY_A);

    if (empty($results)) {
        wp_die('No data to export');
    }

    $filename = 'survey-responses-' . date('Y-m-d');
    if (!empty($_POST['form_id_export'])) {
        $filename .= '-form-' . sanitize_file_name($_POST['form_id_export']);
    }
    if (!empty($_POST['study_group_export'])) {
        $filename .= '-group-' . sanitize_file_name($_POST['study_group_export']);
    }
    $filename .= '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // Add UTF-8 BOM
    fputcsv($output, array_keys($results[0]));

    foreach ($results as $row) {
        fputcsv($output, array_map('sanitize_text_field', $row));
    }

    fclose($output);
    exit;
}

add_shortcode('view_survey_responses', 'render_survey_responses_view');
function render_survey_responses_view($atts) {
    error_log('Survey responses shortcode is being executed');
    
    // Enqueue necessary scripts
    wp_enqueue_script(
        'survey-responses', 
        SURVEY_PLUGIN_URL . '/assets/js/survey-responses.js',
        array('jquery'), 
        SURVEY_PLUGIN_VERSION,
        true
    );

    // Add necessary configuration
    wp_localize_script('survey-responses', 'surveyConfig', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('submit_survey_nonce'),
        'debug' => WP_DEBUG
    ));

    // Return the responses card structure
    ob_start();
    ?>
    <div class="progress-card">
        <div class="card-header">
            <h3>My Quiz Responses</h3>
        </div>
        <div id="responses-section">
            <div id="responses-loading" class="loading-indicator">Loading responses...</div>
            <div id="responses-container"></div>
            <div id="responses-error" style="display: none;"></div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

add_shortcode('view_survey_responses', 'render_survey_responses_view');

// responses grouped by modules
// Add this to your custom-survey.php file

function handle_get_survey_responses() {
    // Verify nonce
    if (!check_ajax_referer('get_survey_responses', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Security check failed'));
        exit;
    }

    $token = isset($_POST['token']) ? sanitize_text_field($_POST['token']) : '';
    if (empty($token)) {
        wp_send_json_error(array('message' => 'Token is required'));
        exit;
    }

    global $wpdb;
    $response_table = $wpdb->prefix . 'survey_responses';
    
    // Get responses with question text and taxonomy information
    $query = $wpdb->prepare("
        SELECT 
            r.response,
            r.created_at,
            r.form_id,
            p.post_title as question,
            p.ID as question_id,
            GROUP_CONCAT(DISTINCT t.name) as module_name,
            GROUP_CONCAT(DISTINCT parent_terms.name) as parent_module_name
        FROM {$response_table} r
        LEFT JOIN {$wpdb->posts} p ON r.question_id = p.ID
        LEFT JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
        LEFT JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'question_module'
        LEFT JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
        LEFT JOIN {$wpdb->term_taxonomy} parent_tt ON tt.parent = parent_tt.term_id
        LEFT JOIN {$wpdb->terms} parent_terms ON parent_tt.term_id = parent_terms.term_id
        WHERE r.token = %s
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ", $token);

    $results = $wpdb->get_results($query);

    if ($wpdb->last_error) {
        error_log('Database error in survey responses: ' . $wpdb->last_error);
        wp_send_json_error(array('message' => 'Database error occurred'));
        exit;
    }

    if (empty($results)) {
        wp_send_json_success(array()); // Return empty array instead of error
        exit;
    }

    // Format the responses
    $formatted_responses = array_map(function($row) {
        return array(
            'question' => $row->question,
            'response' => $row->response,
            'created_at' => $row->created_at,
            'form_id' => $row->form_id,
            'module' => $row->module_name ? explode(',', $row->module_name)[0] : 'Uncategorized',
            'parent_module' => $row->parent_module_name ? explode(',', $row->parent_module_name)[0] : 'General'
        );
    }, $results);

    wp_send_json_success($formatted_responses);
}

// Register the AJAX handlers
add_action('wp_ajax_get_survey_responses', 'handle_get_survey_responses');
add_action('wp_ajax_nopriv_get_survey_responses', 'handle_get_survey_responses');


// AJAX handler for saving both types of progress
add_action('wp_ajax_save_user_progress', 'handle_save_user_progress');
add_action('wp_ajax_nopriv_save_user_progress', 'handle_save_user_progress');

// Add this debugging function at the top of your plugin file
function debug_progress($message, $data = null) {
    if (WP_DEBUG) {
        error_log('PROGRESS DEBUG: ' . $message);
        if ($data !== null) {
            error_log('DATA: ' . print_r($data, true));
        }
    }
}

/**
 * Comprehensive schema management for survey progress tracking
 */

 function get_required_progress_columns() {
    return array(
        'id' => 'int(11) NOT NULL AUTO_INCREMENT',
        'token' => 'varchar(5) NOT NULL',
        'form_id' => 'varchar(50) NOT NULL',
        'current_page_id' => 'bigint(20)',
        'current_page_url' => 'varchar(255)',
        'module_progress' => 'longtext',
        'responses' => 'longtext',
        'last_visited_url' => 'varchar(255)',
        'last_visited_timestamp' => 'datetime',
        'last_updated' => 'datetime DEFAULT CURRENT_TIMESTAMP',
        'is_complete' => 'tinyint(1) DEFAULT 0'
    );
}

function sync_progress_table_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'survey_progress';
    $debug_log = array();
    
    // Get current table structure
    $existing_columns = array();
    $current_columns = $wpdb->get_results("SHOW COLUMNS FROM $table_name");
    
    if ($current_columns) {
        foreach ($current_columns as $col) {
            $existing_columns[$col->Field] = $col;
        }
    }
    
    // Get required columns
    $required_columns = get_required_progress_columns();
    
    // Add missing columns
    foreach ($required_columns as $column_name => $definition) {
        if (!isset($existing_columns[$column_name])) {
            $sql = "ALTER TABLE $table_name ADD COLUMN $column_name $definition";
            if ($column_name === 'id') {
                $sql .= " PRIMARY KEY";
            }
            $wpdb->query($sql);
            $debug_log[] = "Added column: $column_name";
        }
    }
    
    // Set up indexes if they don't exist
    $indexes = array(
        'token_form' => "ALTER TABLE $table_name ADD UNIQUE KEY token_form (token, form_id)",
        'token' => "ALTER TABLE $table_name ADD KEY token (token)"
    );
    
    $existing_indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
    $existing_index_names = wp_list_pluck($existing_indexes, 'Key_name');
    
    foreach ($indexes as $index_name => $sql) {
        if (!in_array($index_name, $existing_index_names)) {
            $wpdb->query($sql);
            $debug_log[] = "Added index: $index_name";
        }
    }
    
    if (WP_DEBUG && !empty($debug_log)) {
        error_log('Progress table updates: ' . print_r($debug_log, true));
    }
    
    return true;
}

function verify_progress_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'survey_progress';
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    
    if (!$table_exists) {
        // Create the table
        $charset_collate = $wpdb->get_charset_collate();
        $columns = get_required_progress_columns();
        
        $sql = "CREATE TABLE $table_name (\n";
        foreach ($columns as $column => $definition) {
            $sql .= "$column $definition,\n";
        }
        $sql .= "PRIMARY KEY (id),\n";
        $sql .= "UNIQUE KEY token_form (token, form_id),\n";
        $sql .= "KEY token (token)\n";
        $sql .= ") $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        error_log("Created progress table: $table_name");
        return true;
    }
    
    // Sync existing table schema
    return sync_progress_table_schema();
}

// Add initialization hooks
add_action('init', 'verify_progress_table');
add_action('plugins_loaded', 'verify_progress_table');
register_activation_hook(__FILE__, 'verify_progress_table');


// Enhanced progress tracking handler
function handle_save_user_progress() {
    try {
        error_log('Progress save handler initiated');
        
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'progress_nonce')) {
            wp_send_json_error(array('message' => 'Security verification failed'), 403);
            return;
        }
        
        // Validate required fields
        $required_fields = array('token', 'form_id', 'page_url');
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                wp_send_json_error(array('message' => "Missing required field: $field"), 400);
                return;
            }
        }
        
        global $wpdb;
        $progress_table = $wpdb->prefix . 'survey_progress';
        
        // Ensure table and schema are up to date
        verify_progress_table();
        
        // Prepare data for save
        $data = array(
            'token' => sanitize_text_field($_POST['token']),
            'form_id' => sanitize_text_field($_POST['form_id']),
            'current_page_id' => isset($_POST['page_id']) ? intval($_POST['page_id']) : 0,
            'current_page_url' => esc_url_raw($_POST['page_url']),
            'module_progress' => isset($_POST['module_progress']) ? wp_json_encode($_POST['module_progress']) : null,
            'last_visited_url' => esc_url_raw($_POST['page_url']),
            'last_visited_timestamp' => current_time('mysql'),
            'last_updated' => current_time('mysql')
        );
        
        // Log the data we're about to save
        error_log('Attempting to save progress with data: ' . print_r($data, true));
        
        // Check if record exists
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $progress_table WHERE token = %s AND form_id = %s",
            $data['token'],
            $data['form_id']
        ));
        
        if ($existing) {
            // Update existing record
            $where = array(
                'token' => $data['token'],
                'form_id' => $data['form_id']
            );
            
            $result = $wpdb->update($progress_table, $data, $where);
            error_log("Update result: " . ($result !== false ? "Success" : "Failed - " . $wpdb->last_error));
        } else {
            // Insert new record
            $result = $wpdb->insert($progress_table, $data);
            error_log("Insert result: " . ($result !== false ? "Success" : "Failed - " . $wpdb->last_error));
        }
        
        if ($result === false) {
            throw new Exception($wpdb->last_error ?: 'Database operation failed');
        }
        
        wp_send_json_success(array(
            'message' => 'Progress saved successfully',
            'timestamp' => current_time('mysql')
        ));
        
    } catch (Exception $e) {
        error_log('Progress save error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Failed to save progress',
            'debug' => WP_DEBUG ? $e->getMessage() : null
        ), 500);
    }
}

// Register AJAX handlers
add_action('wp_ajax_save_user_progress', 'handle_save_user_progress');
add_action('wp_ajax_nopriv_save_user_progress', 'handle_save_user_progress');

function handle_get_user_progress() {
    error_log('Progress retrieval initiated');

    if (!wp_verify_nonce($_POST['nonce'], 'progress_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        return;
    }

    global $wpdb;
    $progress_table = $wpdb->prefix . 'survey_progress';
    
    $token = sanitize_text_field($_POST['token']);
    $form_id = sanitize_text_field($_POST['form_id']);

    // Log the query parameters
    error_log("Fetching progress for token: $token, form_id: $form_id");

    $progress = $wpdb->get_row($wpdb->prepare(
        "SELECT current_page_id, current_page_url, module_progress, responses, 
                last_visited_url, last_visited_timestamp 
         FROM $progress_table 
         WHERE token = %s AND form_id = %s",
        $token,
        $form_id
    ));

    // Log the raw database result
    error_log('Raw database result: ' . print_r($progress, true));

    if ($progress) {
        // Ensure module_progress is valid JSON
        if ($progress->module_progress) {
            try {
                // Attempt to decode and re-encode to ensure valid JSON
                $decoded = json_decode($progress->module_progress);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $progress->module_progress = json_encode($decoded);
                } else {
                    error_log('JSON decode error: ' . json_last_error_msg());
                    $progress->module_progress = '{}';
                }
            } catch (Exception $e) {
                error_log('Error processing module_progress: ' . $e->getMessage());
                $progress->module_progress = '{}';
            }
        } else {
            $progress->module_progress = '{}';
        }

        // Log the processed response
        error_log('Processed progress data: ' . print_r($progress, true));

        wp_send_json_success(['progress' => $progress]);
    } else {
        // Return empty progress structure
        wp_send_json_success([
            'progress' => [
                'module_progress' => '{}',
                'current_page_id' => null,
                'current_page_url' => null,
                'last_visited_url' => null,
                'last_visited_timestamp' => null
            ]
        ]);
    }
}

// Register AJAX handlers
add_action('wp_ajax_get_user_progress', 'handle_get_user_progress');
add_action('wp_ajax_nopriv_get_user_progress', 'handle_get_user_progress');
// Add this to your custom-survey.php file
add_shortcode('show_progress_overview', 'render_progress_overview');

function render_progress_overview($atts) {
    // Add debug output to verify shortcode execution
    error_log('Progress overview shortcode executed');
    
    // Return the progress card structure
    return '
        <div class="progress-card">
            <div class="card-header">
                <h3>Learning Progress</h3>
            </div>
            <div id="user-progress-overview" class="progress-dashboard">
                <div class="loading-indicator">Loading progress...</div>
            </div>
        </div>
    ';
}
// Add this to custom-survey.php
function check_progress_table_structure() {
    if (!WP_DEBUG) return;
    
    global $wpdb;
    $progress_table = $wpdb->prefix . 'survey_progress';
    
    $columns = $wpdb->get_results("SHOW COLUMNS FROM $progress_table");
    error_log('Progress table structure: ' . print_r($columns, true));
}
add_action('admin_init', 'check_progress_table_structure');

function add_login_count_column() {
    global $wpdb;
    $tokens_table = $wpdb->prefix . 'survey_tokens';
    
    $check_column = $wpdb->get_results("SHOW COLUMNS FROM $tokens_table LIKE 'login_count'");
    
    if (empty($check_column)) {
        $wpdb->query("ALTER TABLE $tokens_table 
                     ADD COLUMN login_count int DEFAULT 0 AFTER last_used");
        error_log('Added login_count column to tokens table');
    }
}
