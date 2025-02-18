// file: admin.js
jQuery(document).ready(function($) {
    // Function to ensure option values are synced
    function syncOptionValues() {
        $('.option-text').each(function() {
            const optionText = $(this).val();
            $(this).closest('.option-content')
                   .find('.correct-answer-toggle input')
                   .val(optionText);
        });
    }

    // Sync when typing in option text
    $(document).on('input', '.option-text', function() {
        syncOptionValues();
    });

    // Sync before form submission
    $('form#post').on('submit', function(e) {
        syncOptionValues();
    });

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

    // Make sure options are properly saved when sorting
    if ($.fn.sortable) {
        $('#options-container').sortable({
            handle: '.handle',
            axis: 'y',
            update: function() {
                syncOptionValues();
                updateFeedbackFields();
            }
        });
    }
});