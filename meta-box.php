<?php
// meta-box.php
if (!defined('ABSPATH')) {
    exit;
}

class Survey_Question_Meta_Box {
    public function __construct() {
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('save_post_survey_question', array($this, 'save_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function enqueue_scripts($hook) {
        // Only load on post.php and post-new.php
        if (!in_array($hook, array('post.php', 'post-new.php'))) {
            return;
        }
    
        // Only load for our custom post type
        if (get_post_type() !== 'survey_question') {
            return;
        }
    
        // Enqueue WordPress media scripts
        wp_enqueue_media();
        wp_enqueue_script('jquery-ui-sortable');
    
        // Enqueue your custom admin scripts
        wp_enqueue_script(
            'survey-admin',
            plugins_url('/custom-survey-token-1.1/assets/js/admin.js', dirname(__FILE__)),
            array('jquery', 'jquery-ui-sortable'),
            SURVEY_PLUGIN_VERSION,
            true
        );
    
        // Enqueue admin styles
        wp_enqueue_style(
            'survey-admin',
            plugins_url('/custom-survey-token-1.1/assets/css/admin.css', dirname(__FILE__)),
            array(),
            SURVEY_PLUGIN_VERSION
        );
    
        // Add localization for JavaScript if needed
        wp_localize_script('survey-admin', 'surveyAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('survey_admin_nonce'),
            'strings' => array(
                'confirmDeleteOption' => __('Are you sure you want to delete this option?', 'survey-plugin'),
                'correctAnswerLabel' => __('Correct Answer', 'survey-plugin')
            )
        ));
    }

    public function add_meta_box() {
        add_meta_box(
            'survey_question_settings',
            'Question Settings',
            array($this, 'render_meta_box'),
            'survey_question',
            'normal',
            'high'
        );
    }

    public function render_meta_box($post) {
        // Get saved values
        $type = get_post_meta($post->ID, 'question_type', true);
        $required = get_post_meta($post->ID, 'required', true);
        $options = get_post_meta($post->ID, 'question_options', true);
        $image = get_post_meta($post->ID, 'question_image', true);
        $feedback = get_post_meta($post->ID, 'question_feedback', true);
        $correct_answers = get_post_meta($post->ID, 'correct_answers', true);

        
        // Set default type if none exists
        if (empty($type)) {
            $type = 'text';
        }

        // Decode options and feedback
        $options_array = $options ? json_decode($options, true) : array(array('text' => ''));
        $feedback_array = $feedback ? json_decode($feedback, true) : array();
        $correct_answers_array = $correct_answers ? json_decode($correct_answers, true) : array();

        // Add nonce for security
        wp_nonce_field('survey_question_meta', 'survey_question_meta_nonce');
        ?>
        <div class="survey-meta-box">
            <!-- Question Type -->
            <div class="meta-box-row">
                <label class="setting-label">
                    <strong><?php esc_html_e('Question Type:', 'survey-plugin'); ?></strong>
                </label>
                <select name="question_type" id="question_type" class="widefat">
                    <option value="text" <?php selected($type, 'text'); ?>><?php esc_html_e('Short Text', 'survey-plugin'); ?></option>
                    <option value="textarea" <?php selected($type, 'textarea'); ?>><?php esc_html_e('Long Answer', 'survey-plugin'); ?></option>
                    <option value="multiple_text" <?php selected($type, 'multiple_text'); ?>>Multiple Short Texts</option>
                    <option value="radio" <?php selected($type, 'radio'); ?>><?php esc_html_e('Multiple Choice (Single)', 'survey-plugin'); ?></option>
                    <option value="checkbox" <?php selected($type, 'checkbox'); ?>><?php esc_html_e('Multiple Choice (Multiple)', 'survey-plugin'); ?></option>
                </select>
            </div>

            <!--Multiple short text box-->

            <div id="multiple_text_settings" class="meta-box-row" style="display: <?php echo ($type == 'multiple_text') ? 'block' : 'none'; ?>">
                    <label class="setting-label">
                        <strong>Text Input Settings:</strong>
                    </label>
                    <?php
                    $text_inputs = get_post_meta($post->ID, 'text_inputs', true);
                    $text_inputs = $text_inputs ? json_decode($text_inputs, true) : array(
                        array('label' => 'Input 1', 'placeholder' => '')
                    );
                    ?>
                    <div id="text-inputs-container">
                        <?php foreach ($text_inputs as $index => $input): ?>
                            <div class="text-input-row">
                                <span class="dashicons dashicons-menu handle"></span>
                                <div class="text-input-fields">
                                    <input type="text" 
                                        class="widefat text-input-label" 
                                        name="text_inputs[<?php echo $index; ?>][label]" 
                                        value="<?php echo esc_attr($input['label']); ?>"
                                        placeholder="Label (e.g., First Wish)">
                                    <input type="text" 
                                        class="widefat text-input-placeholder" 
                                        name="text_inputs[<?php echo $index; ?>][placeholder]" 
                                        value="<?php echo esc_attr($input['placeholder']); ?>"
                                        placeholder="Placeholder text (optional)">
                                </div>
                                <span class="dashicons dashicons-trash remove-text-input"></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="button add-text-input">Add Another Text Input</button>
            </div>
            
            <!-- Question Image -->
            <div class="meta-box-row">
                <label class="setting-label">
                    <strong><?php esc_html_e('Question Image:', 'survey-plugin'); ?></strong>
                </label>
                <div class="image-upload-container">
                    <input type="hidden" 
                        name="question_image" 
                        id="question_image" 
                        value="<?php echo esc_attr($image); ?>">
                    
                    <div class="image-preview-wrapper" <?php echo empty($image) ? 'style="display:none;"' : ''; ?>>
                        <?php if (!empty($image)) : ?>
                            <img src="<?php echo esc_url($image); ?>" 
                                class="question-image-preview">
                        <?php endif; ?>
                    </div>
                    
                    <div class="image-controls">
                        <button type="button" class="button upload-image">
                            <?php esc_html_e('Add Image', 'survey-plugin'); ?>
                        </button>
                        <button type="button" class="button remove-image" <?php echo empty($image) ? 'style="display:none;"' : ''; ?>>
                            <?php esc_html_e('Remove Image', 'survey-plugin'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Multiple Choice Options -->
            <div id="options_section" class="meta-box-row" <?php if ($type !== 'radio' && $type !== 'checkbox') : ?>style="display: none;"<?php endif; ?>>
                <label class="setting-label">
                    <strong><?php esc_html_e('Answer Options:', 'survey-plugin'); ?></strong>
                </label>
                <div id="options-container">
                    <?php foreach ($options_array as $index => $option) : ?>
                        <div class="option-row">
                            <span class="dashicons dashicons-menu handle"></span>
                            <div class="option-content">
                                <input type="text" 
                                       class="widefat option-text" 
                                       name="question_options[]" 
                                       value="<?php echo esc_attr($option['text']); ?>">
                                
                                <div class="correct-answer-toggle">
                                    <label class="correct-answer-label">
                                        <input type="<?php echo esc_attr($type === 'radio' ? 'radio' : 'checkbox'); ?>"
                                               name="correct_answers<?php echo $type === 'checkbox' ? '[]' : ''; ?>"
                                               value="<?php echo esc_attr($option['text']); ?>"
                                               <?php checked(in_array($option['text'], $correct_answers_array)); ?>>
                                        <?php esc_html_e('Correct Answer', 'survey-plugin'); ?>
                                    </label>
                                </div>
                            </div>
                            <span class="dashicons dashicons-trash remove-option"></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button add-option"><?php esc_html_e('Add Option', 'survey-plugin'); ?></button>
                <div id="other-option-settings" class="meta-box-row">
                    <label>
                        <input type="checkbox" 
                            name="has_other_option" 
                            id="has_other_option" 
                            value="1" 
                            <?php checked(get_post_meta($post->ID, 'has_other_option', true), '1'); ?>>
                        <?php esc_html_e('Include "Other" option with text input', 'survey-plugin'); ?>
                    </label>
                    <div class="other-option-label" style="margin-top: 10px; <?php echo get_post_meta($post->ID, 'has_other_option', true) ? '' : 'display: none;'; ?>">
                        <input type="text" 
                            class="widefat"
                            name="other_option_label" 
                            value="<?php echo esc_attr(get_post_meta($post->ID, 'other_option_label', true) ?: 'Other:'); ?>"
                            placeholder="Label for Other option (e.g., 'Other, please specify:')">
                    </div>
                </div>
            </div>

            <!-- Feedback Section -->
            <div class="meta-box-row">
                <label class="setting-label">
                    <strong><?php esc_html_e('Question Feedback:', 'survey-plugin'); ?></strong>
                </label>
                <div id="feedback-container">
                    <?php if ($type === 'radio' || $type === 'checkbox') : ?>
                        <div class="feedback-options">
                            <?php foreach ($options_array as $option) : 
                                $feedback_text = isset($feedback_array[$option['text']]) ? $feedback_array[$option['text']] : '';
                            ?>
                                <div class="feedback-row">
                                    <label><?php printf(esc_html__('Feedback for "%s":', 'survey-plugin'), esc_html($option['text'])); ?></label>
                                    <textarea name="question_feedback[<?php echo esc_attr($option['text']); ?>]" 
                                              class="widefat feedback-text" 
                                              rows="2"><?php echo esc_textarea($feedback_text); ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : 
                        $general_feedback = isset($feedback_array['general']) ? $feedback_array['general'] : '';
                    ?>
                        <textarea name="question_feedback[general]" 
                                  class="widefat feedback-text" 
                                  rows="2" 
                                  placeholder="<?php esc_attr_e('Enter general feedback for this question...', 'survey-plugin'); ?>"
                        ><?php echo esc_textarea($general_feedback); ?></textarea>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Required Toggle -->
            <div class="meta-box-row">
                <label>
                    <input type="checkbox" name="required" value="1" <?php checked($required, '1'); ?>>
                    <?php esc_html_e('Required question', 'survey-plugin'); ?>
                </label>
            </div>
        </div>

        <style>
            .image-upload-container {
                margin-top: 10px;
            }

            .image-preview-wrapper {
                margin: 10px 0;
            }

            .question-image-preview {
                max-width: 100%;
                max-height: 200px;
                display: block;
                margin-bottom: 10px;
            }

            .image-controls {
                display: flex;
                gap: 10px;
            }
                        .survey-meta-box {
                padding: 10px;
            }
            .meta-box-row {
                margin-bottom: 15px;
                padding: 10px;
                background: #f9f9f9;
                border: 1px solid #e5e5e5;
            }
            .option-row {
                display: flex;
                align-items: center;
                margin-bottom: 10px;
                background: #fff;
                padding: 8px;
                border: 1px solid #e5e5e5;
            }
            .option-content {
                flex: 1;
                margin: 0 10px;
            }
            .correct-answer-toggle {
                margin-top: 5px;
            }
            .correct-answer-label {
                display: flex;
                align-items: center;
                gap: 5px;
                color: #2271b1;
            }
            .feedback-row {
                margin-bottom: 10px;
                padding: 10px;
                background: #fff;
                border: 1px solid #e5e5e5;
            }

            .goal-field-row {
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        background: #fff;
        padding: 8px;
        border: 1px solid #e5e5e5;
            }
            .goal-field-content {
                flex: 1;
                margin: 0 10px;
            }
            .goal-field-row .handle {
                cursor: move;
                color: #999;
            }
            .goal-field-row .remove-goal-field {
                cursor: pointer;
                color: #999;
            }
            .goal-field-row .remove-goal-field:hover {
                color: #d63638;
            }
                </style>
        <?php
    }

    public function save_meta_box($post_id) {
        // Verify nonce first
        if (!isset($_POST['survey_question_meta_nonce']) || 
            !wp_verify_nonce($_POST['survey_question_meta_nonce'], 'survey_question_meta')) {
            return;
        }
    
        // Check for autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
    
        // Verify permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    
        // Debug log
        error_log('Saving question meta for post ' . $post_id);
        error_log('POST data: ' . print_r($_POST, true));
    
        // Save question type
        if (isset($_POST['question_type'])) {
            $question_type = sanitize_text_field($_POST['question_type']);
            update_post_meta($post_id, 'question_type', $question_type);
            error_log('Saved question type: ' . $question_type);
        }
    
        // Save options - with improved error handling
        if (isset($_POST['question_options'])) {
            $options = array_map(function($text) {
                // Use wp_kses_post instead of sanitize_text_field to preserve apostrophes and quotes
                return array('text' => wp_kses_post(stripslashes($text)));
            }, $_POST['question_options']);
            
            // Filter out empty options while preserving array keys
            $options = array_filter($options, function($option) {
                return !empty($option['text']);
            });
            
            if (!empty($options)) {
                update_post_meta($post_id, 'question_options', wp_slash(json_encode($options)));
            }
        }
    
        // Save correct answers with improved handling
        if (isset($_POST['correct_answers'])) {
            $correct_answers = is_array($_POST['correct_answers']) ? 
                $_POST['correct_answers'] : array($_POST['correct_answers']);
            
            $correct_answers = array_filter($correct_answers); // Remove empty values
            $correct_answers = array_map('sanitize_text_field', $correct_answers);
            
            if (!empty($correct_answers)) {
                update_post_meta($post_id, 'correct_answers', json_encode($correct_answers));
                error_log('Saved correct answers: ' . json_encode($correct_answers));
            } else {
                update_post_meta($post_id, 'correct_answers', json_encode(array()));
                error_log('Saved empty correct answers array');
            }
        }
    
        // Save "Other" option settings
        update_post_meta($post_id, 'has_other_option', 
            isset($_POST['has_other_option']) ? '1' : '0');
        
        if (isset($_POST['other_option_label'])) {
            update_post_meta($post_id, 'other_option_label', 
                sanitize_text_field($_POST['other_option_label']));
        }
    
        // Save required status
        update_post_meta($post_id, 'required', isset($_POST['required']) ? '1' : '0');
    
        // Save feedback with improved handling
        if (isset($_POST['question_feedback']) && is_array($_POST['question_feedback'])) {
            $feedback_array = array();
            foreach ($_POST['question_feedback'] as $key => $value) {
                if (!empty($value)) {
                    $feedback_array[sanitize_text_field($key)] = sanitize_textarea_field($value);
                }
            }
            if (!empty($feedback_array)) {
                update_post_meta($post_id, 'question_feedback', json_encode($feedback_array));
                error_log('Saved feedback: ' . json_encode($feedback_array));
            }
        }
    
        // Clear any transients or caches
        wp_cache_delete($post_id, 'post_meta');
    }
}

new Survey_Question_Meta_Box();