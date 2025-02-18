<?php if (!defined('ABSPATH')) exit; 

$nextPageUrl = isset($atts['next_page']) ? esc_url($atts['next_page']) : '';
$homePageUrl = isset($atts['home_page']) ? esc_url($atts['home_page']) : 'https://education.ufl.edu/thrives/modules-home';
// $is_admin = current_user_can('manage_options') ? '1' : '0';

?>


<form id="survey-form" class="survey-form" data-next-page="<?php echo esc_attr($nextPageUrl); ?>">
    <!-- Hidden fields for form identification and security -->
    <input type="hidden" id="survey-token" name="token" value="">
    <?php wp_nonce_field('submit_survey_nonce', 'survey_nonce'); ?>
    <input type="hidden" name="form_id" value="<?php echo esc_attr($atts['form_id']); ?>">
    
    <!-- Questions -->
    <?php 
    if (!empty($questions)) :
        foreach($questions as $question): 
            // Get question metadata
            $type = get_post_meta($question->ID, 'question_type', true);
            $required = get_post_meta($question->ID, 'required', true);
            $image = get_post_meta($question->ID, 'question_image', true);
            
            // Include the question block template
            include plugin_dir_path(dirname(__FILE__)) . 'templates/question-block.php';
        endforeach;
    else:
    ?>
        <div class="no-questions-message">
            <?php esc_html_e('No questions found.', 'survey-plugin'); ?>
        </div>
    <?php endif; ?>


    <!-- Question Feedback -->
    <div class="question-feedback" style="display: none;">
    <?php if ($type === 'radio' || $type === 'checkbox'): ?>
        <?php foreach ($options_array as $option): 
            $feedback_text = isset($feedback_array[$option['text']]) ? $feedback_array[$option['text']] : '';
            if (!empty($feedback_text)): ?>
                <div class="feedback-text" data-option="<?php echo esc_attr($option['text']); ?>" style="display: none;">
                    <div class="feedback-icon">ℹ️</div>
                    <div class="feedback-content"><?php echo esc_html($feedback_text); ?></div>
                </div>
            <?php endif;
        endforeach; ?>
    <?php else: ?>
        <?php 
        $general_feedback = isset($feedback_array['general']) ? $feedback_array['general'] : '';
        if (!empty($general_feedback)): ?>
            <div class="feedback-text general-feedback">
                <div class="feedback-icon">ℹ️</div>
                <div class="feedback-content"><?php echo esc_html($general_feedback); ?></div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    </div>
        
    <!-- Submit Section -->
    <?php if (!empty($questions)) : ?>
        <div class="survey-submit">
            <button type="submit" class="submit-button"><?php esc_html_e('Submit', 'survey-plugin'); ?></button>
            <div class="submit-status" style="display: none;">
                <div class="spinner"></div>
                <span class="status-text"></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Success Message Template (hidden by default) -->
    <div class="survey-success-message" style="display: none;">
        <div class="success-content">
            <div class="success-icon">✓</div>
            <p><?php esc_html_e('Thank you for your response!', 'survey-plugin'); ?></p>
            <div class="navigation-buttons">
                <?php if ($nextPageUrl): ?>
                    <a href="<?php echo esc_url($nextPageUrl); ?>" class="survey-next-button">
                        <?php esc_html_e('Next', 'survey-plugin'); ?>
                    </a>
                <?php endif; ?>
                <?php if ($homePageUrl): ?>
                    <a href="<?php echo esc_url($homePageUrl); ?>" class="survey-home-button">
                        <?php esc_html_e('Back to Home', 'survey-plugin'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<style>

.et_pb_video.vid-1-1 {
    display: none;
}
.survey-form {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    font-family: inherit;
}

.question-block {
    margin-bottom: 30px;
    padding: 20px;
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.question-block h4 {
    margin: 0 0 15px 0;
    color: #333;
    font-size: 1.1em;
}

.question-block .required {
    color: #e74c3c;
}

/* Image Styles */
.question-image {
    margin: 15px 0;
}

.question-image img {
    max-width: 100%;
    height: auto;
    border-radius: 4px;
}

/* Input Styles */
.question-input .survey-text, .survey-textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 1em;
    transition: border-color 0.3s ease;
}

.survey-textarea {
    min-height: 100px;
    resize: vertical;
}

.survey-text:focus, .survey-textarea:focus {
    border-color: #37A0EA;
    outline: none;
    box-shadow: 0 0 0 2px rgba(55, 160, 234, 0.2);
}

.question-block .text-input-field {
    margin-bottom: 20px;
}

/* Radio and Checkbox Styles */
.radio-options, .checkbox-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.radio-label, .checkbox-label {
    display: flex;
    align-items: center;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 4px;
    transition: background-color 0.2s ease;
}

.radio-label:hover, .checkbox-label:hover {
    background-color: #f8f9fa;
}

.radio-text, .checkbox-text {
    margin-left: 10px;
    color: #444;
}

/* Feedback Styles */
.question-feedback {
    margin-top: 15px;
}

.feedback-text {
    background-color: #f8f9fa;
    border-left: 4px solid #37A0EA;
    padding: 12px 15px;
    margin-top: 10px;
    border-radius: 0 4px 4px 0;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.feedback-icon {
    font-size: 1.2em;
    line-height: 1;
    flex-shrink: 0;
}

.feedback-content {
    color: #2c3e50;
    font-size: 0.9em;
    line-height: 1.5;
}

/* Submit Button */
.survey-submit {
    text-align: center;
    margin-top: 30px;
}

.submit-button, .survey-next-button {
    background-color: #00476b;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 20px;
    font-size: 1.1em;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.submit-button:hover, .survey-next-button:hover {
    background-color: #05202E;
}

/* Success Message */
.navigation-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
    margin-top: 20px;
}

.survey-next-button,
.survey-home-button {
    display: inline-block;
    padding: 10px 30px;
    border-radius: 20px;
    text-decoration: none;
    font-size: 16px;
    transition: background-color 0.3s ease;
}

.survey-home-button {
    background-color: #6c757d;
    color: white;
}

.survey-home-button:hover {
    background-color: #5a6268;
    color: white;
    text-decoration: none;
}

@media (max-width: 480px) {
    .navigation-buttons {
        flex-direction: column;
        gap: 10px;
    }
    
    .survey-next-button,
    .survey-home-button {
        width: 100%;
        text-align: center;
    }
}

/* Responsive Design */
@media (max-width: 768px) {
    .survey-form {
        padding: 15px;
    }
    
    .question-block {
        padding: 15px;
    }
    
    .submit-button {
        width: 100%;
    }
}

.submit-status {
    margin-top: 15px;
    text-align: center;
}

.spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid #f3f3f3;
    border-top: 3px solid #37A0EA;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-right: 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.status-text {
    color: #666;
    font-size: 0.9em;
}

/* Debug info styles */
.debug-info {
    background: #f8f9fa;
    padding: 10px;
    margin-bottom: 20px;
    border: 1px solid #dee2e6;
    border-radius: 10px;
    font-family: monospace;
}
</style>

<script>
jQuery(document).ready(function($) {
    console.log('Script initialized');


    var surveySystem = surveySystem || {
        ajaxurl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
        tokenEntryUrl: '<?php echo esc_url(get_permalink(get_option('survey_token_entry_page_id'))); ?>',
        surveyNonce: '<?php echo wp_create_nonce('survey_nonce'); ?>',
        isDebug: <?php echo defined('WP_DEBUG') && WP_DEBUG ? 'true' : 'false'; ?>

    };
    // Wait for access control to be ready
function initializeSurveyForm() {
    // Wait for access control to be ready
    if (!window.accessControl) {
        setTimeout(initializeSurveyForm, 100);
        return;
    }

    const $form = $('#survey-form');
    const token = window.accessControl.token;
    
    // If no token, let access control handle the login message display
    if (!token) {
        $form.addClass('loading');
        return;
    }

    // Set token in form
    $('#survey-token').val(token);
    $form.removeClass('loading');
    
    setupFormHandlers();
}

    function setupFormHandlers() {
        const $form = $('#survey-form');
        const $submitButton = $form.find('.submit-button');
        const $submitStatus = $form.find('.submit-status');
        const $statusText = $submitStatus.find('.status-text');
        const nextPageUrl = $form.data('next-page');

        $form.on('submit', function(e) {
            e.preventDefault();
            if ($submitButton.prop('disabled')) return false;
            
            $submitButton.prop('disabled', true).css('opacity', '0.7');
            $submitStatus.show();
            $statusText.text('Submitting your response...');
            
            const formData = new FormData($form[0]);
            formData.append('action', 'submit_survey');
            formData.append('nonce', surveySystem.surveyNonce);
            
            $.ajax({
                url: surveySystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Survey submission response:', response);
                    
                    if (response.success) {
                        // Verify token is still valid
                        window.accessControl.verifyToken()
                            .then(() => {
                                handleSuccessfulSubmission();
                            })
                            .catch(() => {
                                console.log('Token verification failed, redirecting');
                                window.location.href = surveySystem.tokenEntryUrl;
                            });
                    } else {
                        handleSubmissionError(response.data?.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Survey submission error:', error);
                    handleSubmissionError('Error submitting form. Please try again.');
                }
            });
            
            return false;
        });

        function handleSuccessfulSubmission() {
            $statusText.text('Response saved successfully!');
            
            // Update progress tracking
            if (window.progressTracker) {
                const currentUrl = window.location.pathname;
                const progressData = {
                    title: document.title,
                    url: currentUrl,
                    moduleNumber: window.progressTracker.extractModuleNumber(currentUrl),
                    timestamp: new Date().toISOString()
                };
                
                window.progressTracker.moduleProgress[currentUrl] = {
                    ...progressData,
                    visited: true
                };
                
                window.progressTracker.saveProgress();
                window.progressTracker.updateProgressUI();
            }

            // Show video if exists
            const $video = $('.et_pb_video.vid-1-1');
            if ($video.length) {
                $video.show().css({
                    'display': 'block',
                    'opacity': '1',
                    'visibility': 'visible'
                });
            }
            
            // Show success message
            $form.find('.question-block, .survey-submit').fadeOut(300, function() {
                $form.find('.survey-success-message').fadeIn(300, function() {
                    if (nextPageUrl) {
                        $('.survey-next-button').attr('href', nextPageUrl);
                    } else {
                        $('.survey-next-button').hide();
                    }
                });
            });
        }

        function handleSubmissionError(message) {
            if (message === 'Invalid or expired token') {
                window.accessControl.clearToken();
                window.location.href = surveySystem.tokenEntryUrl;
            } else {
                $statusText.text('Error: ' + (message || 'Unknown error occurred'));
                $submitButton.prop('disabled', false).css('opacity', '1');
            }
        }

        // Feedback handlers remain the same
        $('#survey-form input[type="radio"]').on('change', function() {
        const selectedValue = $(this).val();
        console.log('Selected value:', selectedValue);

        const $question = $(this).closest('.question-block');
        const $feedback = $question.find('.question-feedback');
        const $selectedFeedback = $feedback.find(`.feedback-text[data-option="${selectedValue}"]`);

        if ($selectedFeedback.length) {
            $feedback.show();
            $feedback.find('.feedback-text').hide();
            $selectedFeedback.fadeIn(300);
        }
    });
        $('#survey-form input[type="checkbox"]').on('change', function() {
            const $question = $(this).closest('.question-block');
            const $feedback = $question.find('.question-feedback');
            const $selectedFeedback = $feedback.find(`.feedback-text[data-option="${$(this).val()}"]`);
            console.log('Found feedback:', $feedback.length);
            console.log('Found selected feedback:', $selectedFeedback.length);
            
            if ($(this).is(':checked') && $selectedFeedback.length) {
                $feedback.fadeIn(300);
                $selectedFeedback.fadeIn(300);
            } else {
                $selectedFeedback.fadeOut(300);
                if (!$question.find('input[type="checkbox"]:checked').length) {
                    $feedback.fadeOut(300);
                }
            }
        });

        $('#survey-form input[type="text"], #survey-form textarea').on('blur', function() {
            const $question = $(this).closest('.question-block');
            const $feedback = $question.find('.question-feedback');
            const $generalFeedback = $feedback.find('.general-feedback');
            
            if ($generalFeedback.length && $(this).val().trim() !== '') {
                $feedback.fadeIn(300);
                $generalFeedback.fadeIn(300);
            } else {
                $feedback.fadeOut(300);
            }
        });
    }

    // Start initialization
    initializeSurveyForm();
});
</script>