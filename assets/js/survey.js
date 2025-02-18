jQuery(document).ready(function($) {
    'use strict';
    
    const $form = $('#survey-form');
    if (!$form.length) return;

    function initializeSurveyForm() {
        // Wait for access control to be ready
        if (!window.accessControl) {
            setTimeout(initializeSurveyForm, 100);
            return;
        }

        // Get token from access control
        const token = window.accessControl.token;
        
        // If no token, let access control handle the login message
        if (!token) {
            // Don't redirect, let access control handle it
            return;
        }

        // Set token in form
        $('#survey-token').val(token);
        
        // Enable the form
        $form.removeClass('loading');
        setupFormHandlers();
    }

    function setupFormHandlers() {
        $form.on('submit', function(e) {
            e.preventDefault();
            const $submitButton = $form.find('.submit-button');
            if ($submitButton.prop('disabled')) return false;
            
            $submitButton.prop('disabled', true).css('opacity', '0.7');
            
            const formData = new FormData(this);
            formData.append('action', 'submit_survey');
            formData.append('nonce', surveySystem.surveyNonce);

            $.ajax({
                url: surveySystem.ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: handleSubmissionSuccess,
                error: handleSubmissionError
            });
            
            return false;
        });
    }

    function handleSubmissionSuccess(response) {
        if (response.success) {
            // Update progress tracking
            if (window.progressTracker) {
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
            showSuccessMessage(response.data);
        } else {
            handleSubmissionError(response);
        }
    }

    function handleSubmissionError(error) {
        const $submitButton = $form.find('.submit-button');
        $submitButton.prop('disabled', false).css('opacity', '1');
        
        const message = error.data ? error.data.message : 'An error occurred. Please try again.';
        $('.status-text').text('Error: ' + message);
    }

    function showSuccessMessage(data) {
        $form.find('.question-block, .survey-submit').fadeOut(300, function() {
            $form.find('.survey-success-message').fadeIn(300);
        });
    }

    // Add loading state to form initially
    $form.addClass('loading');
    
    // Start initialization
    initializeSurveyForm();
});