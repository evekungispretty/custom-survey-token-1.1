jQuery(document).ready(function($) {
    console.log('Admin JS loaded');

    // Handle question type changes
    $('#question_type').on('change', function() {
        const selectedType = $(this).val();
        
        // Hide all question-specific sections first
        $('#options_section, #multiple_text_settings').hide();
        
        // Show relevant sections based on question type
        switch(selectedType) {
            case 'radio':
            case 'checkbox':
                $('#options_section').show();
                // Update input types for correct answers
                $('.correct-answer-toggle input').each(function() {
                    $(this).attr('type', selectedType);
                    if (selectedType === 'checkbox') {
                        $(this).attr('name', 'correct_answers[]');
                    } else {
                        $(this).attr('name', 'correct_answers');
                    }
                });
                break;
            case 'multiple_text':
                $('#multiple_text_settings').show();
                break;
        }
        
        // Update feedback section based on question type
        updateFeedbackFields();
    });

    // Function to ensure option values are synced
    function syncOptionValues() {
        $('.option-text').each(function() {
            const optionText = $(this).val();
            $(this).closest('.option-content')
                   .find('.correct-answer-toggle input')
                   .val(optionText);
        });
    }

    // Function to update feedback fields
    function updateFeedbackFields() {
        const questionType = $('#question_type').val();
        const feedbackContainer = $('#feedback-container');
        
        // Clear existing feedback fields
        feedbackContainer.empty();
        
        if (questionType === 'radio' || questionType === 'checkbox') {
            // Add feedback fields for each option
            const options = $('.option-text').map(function() {
                return $(this).val();
            }).get();
            
            const feedbackHTML = $('<div class="feedback-options"></div>');
            options.forEach(function(option) {
                if (option) {
                    feedbackHTML.append(`
                        <div class="feedback-row">
                            <label>Feedback for "${option}":</label>
                            <textarea name="question_feedback[${option}]" 
                                      class="widefat feedback-text" 
                                      rows="2"></textarea>
                        </div>
                    `);
                }
            });
            feedbackContainer.append(feedbackHTML);
        } else {
            // Add general feedback field
            feedbackContainer.append(`
                <textarea name="question_feedback[general]" 
                          class="widefat feedback-text" 
                          rows="2" 
                          placeholder="Enter general feedback for this question..."></textarea>
            `);
        }
    }

    // Handle adding new options
    $('.add-option').on('click', function() {
        const questionType = $('#question_type').val();
        const optionsContainer = $('#options-container');
        
        const newOption = $(`
            <div class="option-row">
                <span class="dashicons dashicons-menu handle"></span>
                <div class="option-content">
                    <input type="text" 
                           class="widefat option-text" 
                           name="question_options[]" 
                           value="">
                    <div class="correct-answer-toggle">
                        <label class="correct-answer-label">
                            <input type="${questionType === 'radio' ? 'radio' : 'checkbox'}"
                                   name="correct_answers${questionType === 'checkbox' ? '[]' : ''}"
                                   value="">
                            Correct Answer
                        </label>
                    </div>
                </div>
                <span class="dashicons dashicons-trash remove-option"></span>
            </div>
        `);
        
        optionsContainer.append(newOption);
        newOption.find('.option-text').focus();
        updateFeedbackFields();
    });

    // Handle removing options
    $(document).on('click', '.remove-option', function() {
        $(this).closest('.option-row').remove();
        updateFeedbackFields();
    });

    // Handle "Other" option toggle
    $('#has_other_option').on('change', function() {
        $('.other-option-label').toggle(this.checked);
    });

    // Handle multiple text inputs
    $('.add-text-input').on('click', function() {
        const container = $('#text-inputs-container');
        const index = container.children().length;
        
        const newInput = $(`
            <div class="text-input-row">
                <span class="dashicons dashicons-menu handle"></span>
                <div class="text-input-fields">
                    <input type="text" 
                           class="widefat text-input-label" 
                           name="text_inputs[${index}][label]" 
                           placeholder="Label (e.g., First Wish)">
                    <input type="text" 
                           class="widefat text-input-placeholder" 
                           name="text_inputs[${index}][placeholder]" 
                           placeholder="Placeholder text (optional)">
                </div>
                <span class="dashicons dashicons-trash remove-text-input"></span>
            </div>
        `);
        
        container.append(newInput);
    });

    // Handle removing text inputs
    $(document).on('click', '.remove-text-input', function() {
        $(this).closest('.text-input-row').remove();
    });

    // Sync when typing in option text
    $(document).on('input', '.option-text', function() {
        syncOptionValues();
    });

    // Sync before form submission
    $('form#post').on('submit', function(e) {
        syncOptionValues();
    });

    // Make options sortable
    if ($.fn.sortable) {
        $('#options-container').sortable({
            handle: '.handle',
            axis: 'y',
            update: function() {
                syncOptionValues();
                updateFeedbackFields();
            }
        });

        $('#text-inputs-container').sortable({
            handle: '.handle',
            axis: 'y'
        });
    }

    // Trigger initial state setup
    $('#question_type').trigger('change');
});