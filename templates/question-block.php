<?php
// question-block.php

if (!defined('ABSPATH')) {
    exit;
}

// Get question metadata
$type = get_post_meta($question->ID, 'question_type', true);
$required = get_post_meta($question->ID, 'required', true);
$options = get_post_meta($question->ID, 'question_options', true);
$image = get_post_meta($question->ID, 'question_image', true);
$feedback = get_post_meta($question->ID, 'question_feedback', true);
$correct_answers = get_post_meta($question->ID, 'correct_answers', true);

$options_array = $options ? json_decode($options, true) : [];
$feedback_array = $feedback ? json_decode($feedback, true) : [];
$correct_answers_array = $correct_answers ? json_decode($correct_answers, true) : [];
?>

<div class="question-block" data-question-id="<?php echo esc_attr($question->ID); ?>">
    <h4 class="question-title">
        <?php echo esc_html($question->post_title); ?>
        <?php if ($required) : ?>
            <span class="required">*</span>
        <?php endif; ?>
    </h4>

    <?php if (!empty($image)) : ?>
        <div class="question-image">
            <img src="<?php echo esc_url($image); ?>" 
                 alt="<?php echo esc_attr(sprintf(__('Image for question: %s', 'survey-plugin'), $question->post_title)); ?>">
        </div>
    <?php endif; ?>

    <div class="question-input">

            <?php switch ($type):
            case 'multiple_text': 
                // Get text inputs metadata and decode it
                $text_inputs = get_post_meta($question->ID, 'text_inputs', true);
                $text_inputs = $text_inputs ? json_decode($text_inputs, true) : array();

                // Only proceed if we have text inputs defined
                if (!empty($text_inputs)) :
                    // Loop through each text input
                    foreach ($text_inputs as $input) :
                        ?>
                        <div class="text-input-field">
                            <label><?php echo esc_html($input['label']); ?></label>
                            <input type="text" 
                                name="q_<?php echo esc_attr($question->ID); ?>[]" 
                                class="survey-text"
                                placeholder="<?php echo esc_attr($input['placeholder']); ?>"
                                <?php echo $required ? 'required' : ''; ?>>
                        </div>
                        <?php 
                    endforeach; // Close the foreach loop
                endif; // Close the if statement
                break; 

                case 'radio': ?>
                    <div class="radio-options">
                        <?php foreach ($options_array as $option): ?>
                            <label class="radio-label">
                            <input type="radio" 
                                name="q_<?php echo esc_attr($question->ID); ?>" 
                                value="<?php echo esc_attr($option['text']); ?>"
                                data-correct="<?php echo esc_attr(in_array($option['text'], $correct_answers_array) ? '1' : '0'); ?>"
                                <?php echo $required ? 'required' : ''; ?>>
                            <span class="radio-text"><?php echo wp_kses_post($option['text']); ?></span>

                            </label>
                        <?php endforeach; ?>
                        
                        <?php 
                        $has_other = get_post_meta($question->ID, 'has_other_option', true);
                        $other_label = get_post_meta($question->ID, 'other_option_label', true) ?: 'Other:';

                        if ($has_other): ?>
                            <div class="other-option-container">
                                <label class="<?php echo esc_attr($type); ?>-label">
                                    <input type="<?php echo esc_attr($type); ?>" 
                                        name="q_<?php echo esc_attr($question->ID); ?><?php echo $type === 'checkbox' ? '[]' : ''; ?>" 
                                        value="other"
                                        class="other-option-input">
                                    <span class="<?php echo esc_attr($type); ?>-text"><?php echo esc_html($other_label); ?></span>
                                </label>
                                <input type="text" 
                                    class="other-text-input"
                                    name="q_<?php echo esc_attr($question->ID); ?>_other"
                                    placeholder="Please specify">
                            </div>
                        <?php endif; ?>
                    </div>
                <?php break;

                case 'checkbox': ?>
                    <div class="checkbox-options">
                        <?php foreach ($options_array as $option): ?>
                            <label class="checkbox-label">
                            <input type="checkbox" 
                                name="q_<?php echo esc_attr($question->ID); ?>[]" 
                                value="<?php echo esc_attr($option['text']); ?>"
                                data-correct="<?php echo esc_attr(in_array($option['text'], $correct_answers_array) ? '1' : '0'); ?>">
                            <span class="checkbox-text"><?php echo wp_kses_post($option['text']); ?></span>
                            </label>
                        <?php endforeach; ?>
                        
                        <?php 
                        $has_other = get_post_meta($question->ID, 'has_other_option', true);
                        $other_label = get_post_meta($question->ID, 'other_option_label', true) ?: 'Other:';
                        
                        if ($has_other): ?>
                            <div class="other-option-container">
                                <label class="<?php echo esc_attr($type); ?>-label">
                                    <input type="<?php echo esc_attr($type); ?>" 
                                           name="q_<?php echo esc_attr($question->ID); ?><?php echo $type === 'checkbox' ? '[]' : ''; ?>" 
                                           value="other"
                                           class="other-option-input">
                                    <span class="<?php echo esc_attr($type); ?>-text"><?php echo esc_html($other_label); ?></span>
                                </label>
                                <input type="text" 
                                       class="other-text-input"
                                       name="q_<?php echo esc_attr($question->ID); ?>_other"
                                       placeholder="Please specify">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php break;

            default: ?>
                <input type="text" 
                    name="q_<?php echo esc_attr($question->ID); ?>"
                    class="survey-text"
                    <?php echo $required ? 'required' : ''; ?>>
        <?php endswitch; ?>
    </div>

    <div class="question-feedback" style="display: none;">
        <?php if ($type === 'radio' || $type === 'checkbox'): ?>
            <?php 
            // Debug the feedback data
            // echo '<!-- Debug: Feedback Array: ' . print_r($feedback_array, true) . ' -->';
            foreach ($options_array as $option): 
                // The key should match exactly what's in the option text
                $option_text = $option['text'];
                $feedback_text = isset($feedback_array[$option_text]) ? $feedback_array[$option_text] : '';
                
                if (!empty($feedback_text)): 
            ?>
                <div class="feedback-text" data-option="<?php echo esc_attr($option_text); ?>" style="display: none;">
                    <div class="feedback-content"><?php echo esc_html($feedback_text); ?></div>
                </div>
            <?php 
                endif;
            endforeach; 
            ?>
        <?php endif; ?>
    </div>
</div>